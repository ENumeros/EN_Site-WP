<?php
/**
 * Área de Cliente — Colaboradores (ferramenta `colaboradores`, 2026-11-28).
 *
 * O cliente consulta os colaboradores da empresa (a lista que a Work lê da
 * CentralGest) e pode pedir um colaborador novo. Quem o regista na
 * CentralGest é a equipa; o WordPress nunca fala com a CentralGest.
 *
 * Fluxo:
 *   Work → POST /cp/v1/colaboradores              lista completa (substitui tudo)
 *   cliente → admin-post cp_colaborador_novo      grava o pedido ("enviado") e avisa a equipa
 *   Work → GET  /cp/v1/colaboradores/pedidos/novos      pedidos ainda por receber
 *   Work → POST /cp/v1/colaboradores/pedidos/confirmar  passam a "recebido"
 *   Work → POST /cp/v1/colaboradores/pedidos/estado     "registado" ou "recusado"
 *                                                       (motivo); avisa o cliente
 *
 * Como em Pedir faturas, o pedido fica aqui depois de confirmado: o cliente
 * acompanha o estado. A Work continua a ser a fonte de verdade.
 * O cliente só cancela enquanto a Work ainda não recebeu o pedido.
 *
 * Postmeta no post da empresa:
 *   `_cp_colaboradores`        = [ lista => [ {numero, nome, categoria, nif, email,
 *                                localidade, ativo} ], atualizado_em ]
 *   `_cp_colaboradores_pedidos` = [ {id, criado_em, criado_por, criado_por_nome,
 *                                dados => {nome, nif, email, categoria, localidade,
 *                                observacoes}, estado, recebido_em, tratado_em, motivo} ]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const CP_AC_COLAB_MAX_PEDIDOS = 200;

// ─── Funções puras (testadas em tests/Unit/ColaboradoresTest.php) ───────────

/** Lista vinda da Work, só com os campos conhecidos. Ativos primeiro, por nome. */
function cp_ac_colab_normalizar_lista( $lista ) {
	$saida = [];
	foreach ( (array) $lista as $c ) {
		if ( ! is_array( $c ) ) continue;
		$nome = sanitize_text_field( (string) ( $c['nome'] ?? '' ) );
		if ( '' === $nome ) continue;
		$saida[] = [
			'numero'     => sanitize_text_field( (string) ( $c['numero'] ?? '' ) ),
			'nome'       => $nome,
			'categoria'  => sanitize_text_field( (string) ( $c['categoria'] ?? '' ) ),
			'nif'        => sanitize_text_field( (string) ( $c['nif'] ?? '' ) ),
			'email'      => sanitize_text_field( (string) ( $c['email'] ?? '' ) ),
			'localidade' => sanitize_text_field( (string) ( $c['localidade'] ?? '' ) ),
			'ativo'      => ! empty( $c['ativo'] ),
		];
	}
	usort( $saida, function ( $a, $b ) {
		return ( (int) $b['ativo'] <=> (int) $a['ativo'] ) ?: strcasecmp( $a['nome'], $b['nome'] );
	} );
	return $saida;
}

/**
 * Valida o formulário "Novo colaborador". Devolve [ dados, erros ].
 * Nome obrigatório; NIF e email, se preenchidos, têm de ser válidos.
 */
function cp_ac_colab_validar( $entrada ) {
	$t     = function ( $k, $max ) use ( $entrada ) {
		return mb_substr( trim( sanitize_text_field( (string) ( $entrada[ $k ] ?? '' ) ) ), 0, $max );
	};
	$dados = [
		'nome'        => $t( 'nome', 150 ),
		'nif'         => preg_replace( '/\s+/', '', $t( 'nif', 20 ) ),
		'email'       => $t( 'email', 150 ),
		'categoria'   => $t( 'categoria', 100 ),
		'localidade'  => $t( 'localidade', 80 ),
		'observacoes' => mb_substr( trim( sanitize_textarea_field( (string) ( $entrada['observacoes'] ?? '' ) ) ), 0, 1000 ),
	];
	$erros = [];
	if ( '' === $dados['nome'] ) {
		$erros[] = __( 'Indique o nome do colaborador.', 'contabilidade-pro' );
	}
	if ( '' !== $dados['nif'] && ! cp_ac_fat_nif_valido( $dados['nif'] ) ) {
		$erros[] = __( 'O NIF não é válido (9 algarismos).', 'contabilidade-pro' );
	}
	if ( '' !== $dados['email'] && '' === sanitize_email( $dados['email'] ) ) {
		$erros[] = __( 'O email não é válido.', 'contabilidade-pro' );
	}
	return [ $dados, $erros ];
}

/** Rótulo e cor de cada estado, para o cliente. */
function cp_ac_colab_estado_info( $estado ) {
	$mapa = [
		'enviado'   => [ __( 'Enviado', 'contabilidade-pro' ), 'amber' ],
		'recebido'  => [ __( 'Em análise', 'contabilidade-pro' ), 'blue' ],
		'registado' => [ __( 'Registado', 'contabilidade-pro' ), 'green' ],
		'recusado'  => [ __( 'Não registado', 'contabilidade-pro' ), 'red' ],
	];
	return $mapa[ $estado ] ?? [ $estado, 'blue' ];
}

/** Mantém os mais recentes; só corta pedidos já tratados. Entrada: mais recente primeiro. */
function cp_ac_colab_podar( $pedidos, $max = CP_AC_COLAB_MAX_PEDIDOS ) {
	$pedidos = array_values( (array) $pedidos );
	$excesso = count( $pedidos ) - $max;
	if ( $excesso <= 0 ) return $pedidos;
	$saida = [];
	foreach ( array_reverse( $pedidos ) as $p ) {
		if ( $excesso > 0 && in_array( $p['estado'], [ 'registado', 'recusado' ], true ) ) { $excesso--; continue; }
		$saida[] = $p;
	}
	return array_reverse( $saida );
}

// ─── Leitura / escrita ──────────────────────────────────────────────────────

function cp_ac_colab_lista( $empresa_id ) {
	$c = get_post_meta( $empresa_id, '_cp_colaboradores', true );
	if ( ! is_array( $c ) ) $c = [];
	return $c + [ 'lista' => [], 'atualizado_em' => '' ];
}

function cp_ac_colab_pedidos( $empresa_id ) {
	$p = get_post_meta( $empresa_id, '_cp_colaboradores_pedidos', true );
	return is_array( $p ) ? array_values( $p ) : [];
}

function cp_ac_colab_pedidos_gravar( $empresa_id, $pedidos ) {
	usort( $pedidos, function ( $a, $b ) { return strcmp( (string) $b['criado_em'], (string) $a['criado_em'] ); } );
	update_post_meta( $empresa_id, '_cp_colaboradores_pedidos', cp_ac_colab_podar( $pedidos ) );
}

function cp_ac_colab_url() {
	return add_query_arg( 'vista', 'colaboradores', home_url( '/area-de-cliente/' ) );
}

// ─── REST (Work) ────────────────────────────────────────────────────────────

function cp_ac_rest_routes_colaboradores() {
	$empresa = [ 'work_id' => [ 'required' => true, 'type' => 'string' ] ];
	register_rest_route( 'cp/v1', '/colaboradores', [
		'methods'             => 'POST',
		'callback'            => 'cp_ac_rest_colaboradores',
		'permission_callback' => 'cp_ac_rest_permissao',
		'args'                => $empresa + [ 'colaboradores' => [ 'required' => false, 'type' => 'array' ] ],
	] );
	register_rest_route( 'cp/v1', '/colaboradores/pedidos/novos', [
		'methods'             => 'GET',
		'callback'            => 'cp_ac_rest_colaboradores_novos',
		'permission_callback' => 'cp_ac_rest_permissao',
		'args'                => $empresa,
	] );
	register_rest_route( 'cp/v1', '/colaboradores/pedidos/confirmar', [
		'methods'             => 'POST',
		'callback'            => 'cp_ac_rest_colaboradores_confirmar',
		'permission_callback' => 'cp_ac_rest_permissao',
		'args'                => $empresa + [ 'ids' => [ 'required' => true, 'type' => 'array' ] ],
	] );
	register_rest_route( 'cp/v1', '/colaboradores/pedidos/estado', [
		'methods'             => 'POST',
		'callback'            => 'cp_ac_rest_colaboradores_estado',
		'permission_callback' => 'cp_ac_rest_permissao',
		'args'                => $empresa + [
			'id'     => [ 'required' => true, 'type' => 'string' ],
			'estado' => [ 'required' => true, 'type' => 'string' ],
		],
	] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_colaboradores' );

function cp_ac_rest_colaboradores( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$lista = cp_ac_colab_normalizar_lista( $request->get_param( 'colaboradores' ) );
	update_post_meta( $empresa_id, '_cp_colaboradores', [ 'lista' => $lista, 'atualizado_em' => current_time( 'mysql' ) ] );
	return rest_ensure_response( [ 'ok' => true, 'colaboradores' => count( $lista ) ] );
}

function cp_ac_rest_colaboradores_novos( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$novos = array_values( array_filter( cp_ac_colab_pedidos( $empresa_id ), function ( $p ) { return 'enviado' === $p['estado']; } ) );
	$saida = array_map( function ( $p ) {
		return [ 'id' => $p['id'], 'criado_em' => $p['criado_em'], 'criado_por_nome' => $p['criado_por_nome'] ?? '', 'dados' => $p['dados'] ];
	}, array_reverse( $novos ) );
	return rest_ensure_response( [ 'pedidos' => $saida ] );
}

function cp_ac_rest_colaboradores_confirmar( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$ids     = array_map( 'sanitize_text_field', (array) $request->get_param( 'ids' ) );
	$pedidos = cp_ac_colab_pedidos( $empresa_id );
	$n       = 0;
	foreach ( $pedidos as &$p ) {
		if ( 'enviado' === $p['estado'] && in_array( $p['id'], $ids, true ) ) {
			$p['estado']      = 'recebido';
			$p['recebido_em'] = current_time( 'mysql' );
			$n++;
		}
	}
	unset( $p );
	if ( $n ) cp_ac_colab_pedidos_gravar( $empresa_id, $pedidos );
	return rest_ensure_response( [ 'ok' => true, 'confirmados' => $n ] );
}

function cp_ac_rest_colaboradores_estado( $request ) {
	$empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
	if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
	$estado = sanitize_key( $request->get_param( 'estado' ) );
	if ( ! in_array( $estado, [ 'recebido', 'registado', 'recusado' ], true ) ) {
		return new WP_Error( 'cp_dados_invalidos', __( 'Estado inválido (recebido, registado ou recusado).', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	$id      = sanitize_text_field( $request->get_param( 'id' ) );
	$pedidos = cp_ac_colab_pedidos( $empresa_id );
	$indice  = array_search( $id, array_column( $pedidos, 'id' ), true );
	if ( false === $indice ) {
		return new WP_Error( 'cp_pedido_nao_encontrado', __( 'Pedido de colaborador não encontrado.', 'contabilidade-pro' ), [ 'status' => 404 ] );
	}
	$anterior                      = $pedidos[ $indice ]['estado'];
	$pedidos[ $indice ]['estado']     = $estado;
	$pedidos[ $indice ]['tratado_em'] = 'recebido' === $estado ? '' : current_time( 'mysql' );
	$pedidos[ $indice ]['motivo']     = 'recusado' === $estado ? sanitize_textarea_field( (string) $request->get_param( 'motivo' ) ) : '';
	$nome                          = $pedidos[ $indice ]['dados']['nome'];
	cp_ac_colab_pedidos_gravar( $empresa_id, $pedidos );

	if ( $estado !== $anterior && in_array( $estado, [ 'registado', 'recusado' ], true ) ) {
		cp_push_notificar_empresa(
			$empresa_id,
			__( 'Colaboradores', 'contabilidade-pro' ),
			'registado' === $estado
				/* translators: %s: nome do colaborador */
				? sprintf( __( '%s já está registado', 'contabilidade-pro' ), $nome )
				/* translators: %s: nome do colaborador */
				: sprintf( __( 'O pedido para registar %s não foi aceite', 'contabilidade-pro' ), $nome ),
			cp_ac_colab_url(),
			'colaboradores'
		);
	}
	return rest_ensure_response( [ 'ok' => true ] );
}

// ─── Ações do cliente (admin-post + reload) ─────────────────────────────────

function cp_ac_handle_colaborador_novo() {
	if ( ! is_user_logged_in() ) wp_die( esc_html__( 'Sessão inválida.', 'contabilidade-pro' ) );
	if ( ! isset( $_POST['cp_colab_nonce'] ) || ! wp_verify_nonce( $_POST['cp_colab_nonce'], 'cp_colaborador_novo' ) ) {
		wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
	}
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_tarefa_resolver_empresa( isset( $_POST['empresa_id'] ) ? absint( $_POST['empresa_id'] ) : 0, $user_id );
	if ( ! $empresa_id || ! cp_ac_utilizador_tem_ferramenta( $user_id, $empresa_id, 'colaboradores' ) ) {
		wp_safe_redirect( add_query_arg( 'cp_erro', 'colab_sem_acesso', cp_ac_colab_url() ) );
		exit;
	}
	list( $dados, $erros ) = cp_ac_colab_validar( wp_unslash( $_POST ) );
	if ( $erros ) {
		// O formulário volta preenchido: os dados ficam dois minutos, só para este utilizador.
		set_transient( 'cp_colab_form_' . $user_id, [ 'erros' => $erros, 'dados' => $dados ], 120 );
		wp_safe_redirect( add_query_arg( 'cp_erro', 'colab_invalido', cp_ac_colab_url() ) . '#cp-colab-novo' );
		exit;
	}
	$pedidos   = cp_ac_colab_pedidos( $empresa_id );
	$pedidos[] = [
		'id'              => wp_generate_uuid4(),
		'criado_em'       => current_time( 'mysql' ),
		'criado_por'      => $user_id,
		'criado_por_nome' => wp_get_current_user()->display_name,
		'dados'           => $dados,
		'estado'          => 'enviado',
		'recebido_em'     => '',
		'tratado_em'      => '',
		'motivo'          => '',
	];
	cp_ac_colab_pedidos_gravar( $empresa_id, $pedidos );

	if ( ! cp_backoffice_utilizador_tem_acesso( $user_id ) ) {
		cp_push_notificar_equipa(
			__( 'Novo colaborador', 'contabilidade-pro' ),
			/* translators: 1: empresa, 2: nome do colaborador */
			sprintf( __( '%1$s pede para registar %2$s', 'contabilidade-pro' ), get_the_title( $empresa_id ), $dados['nome'] ),
			add_query_arg( 'empresa_id', $empresa_id, home_url( '/backoffice/' ) ),
			$empresa_id,
			'colaboradores'
		);
	}
	wp_safe_redirect( add_query_arg( 'cp_ok', 'colab_pedido', cp_ac_colab_url() ) );
	exit;
}
add_action( 'admin_post_cp_colaborador_novo', 'cp_ac_handle_colaborador_novo' );

function cp_ac_handle_colaborador_cancelar() {
	if ( ! is_user_logged_in() ) wp_die( esc_html__( 'Sessão inválida.', 'contabilidade-pro' ) );
	if ( ! isset( $_POST['cp_colab_nonce'] ) || ! wp_verify_nonce( $_POST['cp_colab_nonce'], 'cp_colaborador_cancelar' ) ) {
		wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
	}
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_tarefa_resolver_empresa( isset( $_POST['empresa_id'] ) ? absint( $_POST['empresa_id'] ) : 0, $user_id );
	$id         = isset( $_POST['pedido_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pedido_id'] ) ) : '';
	$cancelado  = false;
	if ( $empresa_id && cp_ac_utilizador_tem_ferramenta( $user_id, $empresa_id, 'colaboradores' ) ) {
		$pedidos = cp_ac_colab_pedidos( $empresa_id );
		$resto   = array_values( array_filter( $pedidos, function ( $p ) use ( $id ) {
			return ! ( $p['id'] === $id && 'enviado' === $p['estado'] ); // só antes de a Work o receber
		} ) );
		if ( count( $resto ) < count( $pedidos ) ) {
			cp_ac_colab_pedidos_gravar( $empresa_id, $resto );
			$cancelado = true;
		}
	}
	wp_safe_redirect( add_query_arg( $cancelado ? 'cp_ok' : 'cp_erro', $cancelado ? 'colab_cancelado' : 'colab_nao_cancelado', cp_ac_colab_url() ) );
	exit;
}
add_action( 'admin_post_cp_colaborador_cancelar', 'cp_ac_handle_colaborador_cancelar' );

// ─── Vista ──────────────────────────────────────────────────────────────────

function cp_ac_vista_colaboradores() {
	$empresa_id = cp_ac_empresa_ativa();
	$dados      = cp_ac_colab_lista( $empresa_id );
	$lista      = $dados['lista'];
	$pedidos    = cp_ac_colab_pedidos( $empresa_id );
	$user_id    = get_current_user_id();
	$form       = get_transient( 'cp_colab_form_' . $user_id );
	if ( $form ) delete_transient( 'cp_colab_form_' . $user_id );
	$ok         = isset( $_GET['cp_ok'] ) ? sanitize_key( wp_unslash( $_GET['cp_ok'] ) ) : '';
	$erro       = isset( $_GET['cp_erro'] ) ? sanitize_key( wp_unslash( $_GET['cp_erro'] ) ) : '';
	$erros      = ( 'colab_invalido' === $erro && $form ) ? (array) $form['erros'] : [];
	$ant        = $erros ? (array) $form['dados'] : [];
	$n_ativos   = count( array_filter( $lista, function ( $c ) { return $c['ativo']; } ) );
	$v          = function ( $k ) use ( $ant ) { return esc_attr( $ant[ $k ] ?? '' ); };
	?>
	<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
		<div>
			<h1 style="font-size:23px"><?php esc_html_e( 'Colaboradores', 'contabilidade-pro' ); ?></h1>
			<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Os colaboradores da empresa, tal como estão registados na contabilidade.', 'contabilidade-pro' ); ?></div>
		</div>
		<button type="button" class="ac-btn ac-btn-primary" onclick="var f=document.getElementById('cp-colab-novo');f.hidden=false;f.scrollIntoView({behavior:'smooth'});document.getElementById('cp-colab-nome').focus()">+ <?php esc_html_e( 'Novo colaborador', 'contabilidade-pro' ); ?></button>
	</div>

	<?php if ( 'colab_pedido' === $ok ) : ?>
		<div class="ac-card" style="padding:14px 18px;color:var(--ac-badge-green-fg)"><?php esc_html_e( 'Pedido enviado. Avisamos quando o colaborador estiver registado.', 'contabilidade-pro' ); ?></div>
	<?php elseif ( 'colab_cancelado' === $ok ) : ?>
		<div class="ac-card" style="padding:14px 18px"><?php esc_html_e( 'Pedido cancelado.', 'contabilidade-pro' ); ?></div>
	<?php elseif ( 'colab_nao_cancelado' === $erro ) : ?>
		<div class="ac-card" style="padding:14px 18px;color:var(--ac-badge-red-fg)"><?php esc_html_e( 'Este pedido já está a ser tratado e não pode ser cancelado aqui. Envie-nos uma mensagem.', 'contabilidade-pro' ); ?></div>
	<?php elseif ( 'colab_sem_acesso' === $erro ) : ?>
		<div class="ac-card" style="padding:14px 18px;color:var(--ac-badge-red-fg)"><?php esc_html_e( 'Não tem acesso aos colaboradores desta empresa.', 'contabilidade-pro' ); ?></div>
	<?php endif; ?>

	<div class="ac-card" style="padding:22px" id="cp-colab-novo"<?php echo $erros ? '' : ' hidden'; ?>>
		<h3 style="font-size:16px;margin-bottom:6px"><?php esc_html_e( 'Novo colaborador', 'contabilidade-pro' ); ?></h3>
		<div style="color:var(--ac-muted);font-size:13px;margin-bottom:16px"><?php esc_html_e( 'Envie-nos os dados e tratamos do registo. Se faltar alguma coisa, contactamos.', 'contabilidade-pro' ); ?></div>
		<?php if ( $erros ) : ?>
			<div style="color:var(--ac-badge-red-fg);font-size:13.5px;margin-bottom:14px">
				<?php foreach ( $erros as $e ) : ?><div><?php echo esc_html( $e ); ?></div><?php endforeach; ?>
			</div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ac-colab-form">
			<?php wp_nonce_field( 'cp_colaborador_novo', 'cp_colab_nonce' ); ?>
			<input type="hidden" name="action" value="cp_colaborador_novo">
			<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
			<div class="ac-field ac-colab-largo"><label for="cp-colab-nome"><?php esc_html_e( 'Nome completo', 'contabilidade-pro' ); ?></label>
				<input type="text" id="cp-colab-nome" name="nome" value="<?php echo $v( 'nome' ); ?>" maxlength="150" required></div>
			<div class="ac-field"><label for="cp-colab-nif"><?php esc_html_e( 'NIF', 'contabilidade-pro' ); ?></label>
				<input type="text" id="cp-colab-nif" name="nif" value="<?php echo $v( 'nif' ); ?>" inputmode="numeric" maxlength="20"></div>
			<div class="ac-field"><label for="cp-colab-email"><?php esc_html_e( 'Email', 'contabilidade-pro' ); ?></label>
				<input type="email" id="cp-colab-email" name="email" value="<?php echo $v( 'email' ); ?>" maxlength="150"></div>
			<div class="ac-field"><label for="cp-colab-categoria"><?php esc_html_e( 'Categoria / função', 'contabilidade-pro' ); ?></label>
				<input type="text" id="cp-colab-categoria" name="categoria" value="<?php echo $v( 'categoria' ); ?>" maxlength="100"></div>
			<div class="ac-field"><label for="cp-colab-localidade"><?php esc_html_e( 'Localidade', 'contabilidade-pro' ); ?></label>
				<input type="text" id="cp-colab-localidade" name="localidade" value="<?php echo $v( 'localidade' ); ?>" maxlength="80"></div>
			<div class="ac-field ac-colab-largo"><label for="cp-colab-obs"><?php esc_html_e( 'Observações (opcional)', 'contabilidade-pro' ); ?></label>
				<textarea id="cp-colab-obs" name="observacoes" rows="3" maxlength="1000" placeholder="<?php esc_attr_e( 'Data de início, horário, vencimento…', 'contabilidade-pro' ); ?>"><?php echo esc_textarea( $ant['observacoes'] ?? '' ); ?></textarea></div>
			<div class="ac-colab-largo" style="display:flex;gap:8px">
				<button type="submit" class="ac-btn ac-btn-primary"><?php esc_html_e( 'Enviar pedido', 'contabilidade-pro' ); ?></button>
				<button type="button" class="ac-btn ac-btn-ghost" onclick="document.getElementById('cp-colab-novo').hidden=true"><?php esc_html_e( 'Cancelar', 'contabilidade-pro' ); ?></button>
			</div>
		</form>
	</div>

	<?php if ( $pedidos ) : ?>
	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head"><h3 style="font-size:16px"><?php esc_html_e( 'Os seus pedidos', 'contabilidade-pro' ); ?></h3></div>
		<div class="ac-table-wrap">
			<table class="ac-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Pedido em', 'contabilidade-pro' ); ?></th>
						<th><?php esc_html_e( 'Nome', 'contabilidade-pro' ); ?></th>
						<th><?php esc_html_e( 'Categoria', 'contabilidade-pro' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'contabilidade-pro' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $pedidos as $p ) :
					list( $rotulo, $cor ) = cp_ac_colab_estado_info( $p['estado'] );
					?>
					<tr>
						<td style="white-space:nowrap"><?php echo esc_html( mysql2date( 'd/m/Y', $p['criado_em'] ) ); ?></td>
						<td><?php echo esc_html( $p['dados']['nome'] ); ?></td>
						<td><?php echo esc_html( $p['dados']['categoria'] ?: '—' ); ?></td>
						<td>
							<span class="ac-badge ac-badge-<?php echo esc_attr( $cor ); ?>"><?php echo esc_html( $rotulo ); ?></span>
							<?php if ( 'recusado' === $p['estado'] && '' !== $p['motivo'] ) : ?>
								<div class="ac-sec-sub"><?php echo esc_html( $p['motivo'] ); ?></div>
							<?php endif; ?>
						</td>
						<td style="white-space:nowrap">
							<?php if ( 'enviado' === $p['estado'] ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Cancelar este pedido?', 'contabilidade-pro' ) ); ?>')">
									<?php wp_nonce_field( 'cp_colaborador_cancelar', 'cp_colab_nonce' ); ?>
									<input type="hidden" name="action" value="cp_colaborador_cancelar">
									<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
									<input type="hidden" name="pedido_id" value="<?php echo esc_attr( $p['id'] ); ?>">
									<button type="submit" class="ac-pag-desfazer"><?php esc_html_e( 'Cancelar', 'contabilidade-pro' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( ! $lista ) : ?>
		<?php cp_ac_vazio( __( 'Ainda sem colaboradores', 'contabilidade-pro' ), __( 'Os colaboradores registados na contabilidade aparecem aqui.', 'contabilidade-pro' ) ); ?>
	<?php else : ?>
	<div class="ac-card" style="padding:22px">
		<div class="ac-sec-head">
			<div>
				<h3 style="font-size:16px"><?php esc_html_e( 'Equipa', 'contabilidade-pro' ); ?></h3>
				<?php if ( $dados['atualizado_em'] ) : ?>
					<div class="ac-sec-sub"><?php
						/* translators: %s: data */
						echo esc_html( sprintf( __( 'Atualizado a %s', 'contabilidade-pro' ), mysql2date( 'd/m/Y', $dados['atualizado_em'] ) ) );
					?></div>
				<?php endif; ?>
			</div>
			<span class="ac-badge ac-badge-green"><?php
				/* translators: %d: número de colaboradores ativos */
				echo esc_html( sprintf( _n( '%d ativo', '%d ativos', $n_ativos, 'contabilidade-pro' ), $n_ativos ) );
			?></span>
		</div>
		<div class="ac-table-wrap">
			<table class="ac-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Nº', 'contabilidade-pro' ); ?></th>
						<th><?php esc_html_e( 'Nome', 'contabilidade-pro' ); ?></th>
						<th><?php esc_html_e( 'Categoria', 'contabilidade-pro' ); ?></th>
						<th><?php esc_html_e( 'NIF', 'contabilidade-pro' ); ?></th>
						<th><?php esc_html_e( 'Email', 'contabilidade-pro' ); ?></th>
						<th><?php esc_html_e( 'Localidade', 'contabilidade-pro' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'contabilidade-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $lista as $c ) : ?>
					<tr<?php echo $c['ativo'] ? '' : ' style="opacity:.65"'; ?>>
						<td><?php echo esc_html( $c['numero'] ?: '—' ); ?></td>
						<td style="font-weight:600"><?php echo esc_html( $c['nome'] ); ?></td>
						<td><?php echo esc_html( $c['categoria'] ?: '—' ); ?></td>
						<td><?php echo esc_html( $c['nif'] ?: '—' ); ?></td>
						<td><?php echo esc_html( $c['email'] ?: '—' ); ?></td>
						<td><?php echo esc_html( $c['localidade'] ?: '—' ); ?></td>
						<td><span class="ac-badge ac-badge-<?php echo $c['ativo'] ? 'green' : 'amber'; ?>"><?php echo $c['ativo'] ? esc_html__( 'Ativo', 'contabilidade-pro' ) : esc_html__( 'Inativo', 'contabilidade-pro' ); ?></span></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>
	<?php
}
