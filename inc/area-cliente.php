<?php
/**
 * Área de Cliente — Empresas, Ferramentas e Matriz de Acesso por Empresa
 *
 * Objetivo: controlar que "ferramentas" (ecrãs) da Área de Cliente cada
 * empresa vê. Permite ativar uma funcionalidade nova só numa empresa para
 * testar, e só depois ligar a todas.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Registo de Ferramentas ────────────────────────────────────────────────
// Lista central das ferramentas que podem ser ligadas/desligadas por
// empresa. O Dashboard não entra aqui — está sempre visível para quem tem
// pelo menos uma empresa associada. Para adicionar uma ferramenta nova,
// basta acrescentar uma linha aqui; fica OFF por defeito em todas as
// empresas até a ligares na matriz.

function cp_ac_ferramentas() {
	return [
		'tarefas'            => [
			'label' => __( 'Lembretes', 'contabilidade-pro' ),
			'grupo' => '',
			'icon'  => '<rect x="3.5" y="4" width="17" height="16" rx="2"></rect><path d="M7.5 9.5 9 11l2.5-3"></path><path d="M14 9h4"></path><path d="M7.5 15.5 9 17l2.5-3"></path><path d="M14 15h4"></path>',
		],
		'mensagens'          => [
			'label' => __( 'Mensagens', 'contabilidade-pro' ),
			'grupo' => '',
			'icon'  => '<path d="M4 5.5h16v11H8l-4 3.5z"></path>',
		],
		'visao_financeira'   => [
			'label' => __( 'Visão financeira', 'contabilidade-pro' ),
			'grupo' => __( 'Gestão', 'contabilidade-pro' ),
			'icon'  => '<path d="M4 19V5"></path><path d="M4 19h16"></path><path d="M8 19v-6l3.5 2.5L15 9l4 4"></path>',
		],
		'conta_corrente'     => [
			'label' => __( 'Conta Corrente', 'contabilidade-pro' ),
			'grupo' => __( 'Gestão', 'contabilidade-pro' ),
			'icon'  => '<path d="M3 7.5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><path d="M16 12.5h3v3h-3a1.5 1.5 0 0 1 0-3z"></path><path d="M3 9h15"></path>',
		],
		'pagamentos'         => [
			'label' => __( 'Pagamentos ao Estado', 'contabilidade-pro' ),
			'grupo' => __( 'Gestão', 'contabilidade-pro' ),
			'icon'  => '<rect x="3" y="6" width="18" height="13" rx="2"></rect><path d="M3 10h18"></path><path d="M7 15h4"></path>',
		],
		'pedir_faturas'      => [
			'label' => __( 'Pedir faturas', 'contabilidade-pro' ),
			'grupo' => __( 'Gestão', 'contabilidade-pro' ),
			'icon'  => '<path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path><path d="M12 11v6M9 14h6"></path>',
		],
		'calendario_fiscal'  => [
			'label' => __( 'Calendário', 'contabilidade-pro' ),
			'grupo' => __( 'Gestão', 'contabilidade-pro' ),
			'icon'  => '<rect x="3.5" y="5" width="17" height="15" rx="2"></rect><path d="M8 3v4M16 3v4M3.5 10h17"></path>',
		],
		'documentos'         => [
			'label' => __( 'Documentos', 'contabilidade-pro' ),
			'grupo' => __( 'Documentos', 'contabilidade-pro' ),
			'icon'  => '<path d="M7.5 17.5a4 4 0 0 1-.8-7.92 5 5 0 0 1 9.72-1.84A4.5 4.5 0 0 1 16.5 17.5h-9z"></path><path d="M12 14v-5M9.5 11.5 12 9l2.5 2.5"></path>',
		],
		'documentos_em_falta'     => [
			'label' => __( 'Documentos em Falta', 'contabilidade-pro' ),
			'grupo' => __( 'Documentos', 'contabilidade-pro' ),
			'icon'  => '<path d="M4 12h4l1.5 3h5L16 12h4"></path><path d="M5.5 6h13L20 12v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6z"></path>',
		],
		'movimentos_a_esclarecer' => [
			'label' => __( 'Movimentos a Esclarecer', 'contabilidade-pro' ),
			'grupo' => __( 'Documentos', 'contabilidade-pro' ),
			'icon'  => '<path d="M4 7h16M4 12h10M4 17h7"></path><path d="M17 14l3 3-3 3"></path>',
		],
		'extratos'                => [
			'label' => __( 'Extratos', 'contabilidade-pro' ),
			'grupo' => __( 'Documentos', 'contabilidade-pro' ),
			'icon'  => '<path d="M5 4h14v16l-3.5-2-3.5 2-3.5-2-3.5 2z"></path><path d="M8.5 9h7M8.5 12.5h7M8.5 16h4"></path>',
		],
		'saft'                    => [
			'label' => __( 'SAF-T', 'contabilidade-pro' ),
			'grupo' => __( 'Documentos', 'contabilidade-pro' ),
			'icon'  => '<path d="M7.5 17.5a4 4 0 0 1-.8-7.92 5 5 0 0 1 9.72-1.84A4.5 4.5 0 0 1 16.5 17.5h-9z"></path><path d="M9.5 13.5h5"></path>',
		],
		'mapas_gestao'       => [
			'label' => __( 'Mapas de Gestão', 'contabilidade-pro' ),
			'grupo' => __( 'Contabilidade', 'contabilidade-pro' ),
			'icon'  => '<path d="M5 19V9M12 19V5M19 19v-7"></path><path d="M3 19h18"></path>',
		],
		'recibos_vencimento' => [
			'label' => __( 'Recibos de Vencimento', 'contabilidade-pro' ),
			'grupo' => __( 'Recursos Humanos', 'contabilidade-pro' ),
			'icon'  => '<path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v4h4"></path><path d="M8.5 13h7M8.5 16.5h7M8.5 9.5h3"></path>',
		],
	];
}

// ─── CPT: Empresas ──────────────────────────────────────────────────────────
// Cada empresa-cliente é um post deste tipo. No futuro passa a ser criada
// automaticamente pela Work via API; por agora gere-se à mão em wp-admin.

function cp_ac_registar_cpt_empresa() {
	register_post_type( 'cp_empresa', [
		'labels' => [
			'name'          => __( 'Empresas', 'contabilidade-pro' ),
			'singular_name' => __( 'Empresa', 'contabilidade-pro' ),
			'add_new_item'  => __( 'Adicionar Empresa', 'contabilidade-pro' ),
			'edit_item'     => __( 'Editar Empresa', 'contabilidade-pro' ),
			'search_items'  => __( 'Procurar Empresas', 'contabilidade-pro' ),
			'not_found'     => __( 'Nenhuma empresa encontrada.', 'contabilidade-pro' ),
		],
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'cp_area_cliente',
		'supports'        => [ 'title' ],
		'menu_icon'       => 'dashicons-building',
		'capability_type' => 'post',
	] );
}
add_action( 'init', 'cp_ac_registar_cpt_empresa' );

// "work_id - Nome", para a equipa confirmar rapidamente que está na
// empresa certa (o code interno da app Work só interessa à equipa — o
// cliente nunca vê isto, só o nome). Sem work_id (empresa ainda não
// sincronizada), mostra só o nome.
// Código do cliente na Work (o que a equipa conhece) + nome. O _cp_work_id é
// só o id interno da Work e não se mostra; uma empresa sincronizada antes de
// a Work enviar o código aparece só com o nome.
function cp_ac_empresa_label( $empresa_id ) {
	$codigo = (string) get_post_meta( $empresa_id, '_cp_codigo', true );
	$nome   = get_the_title( $empresa_id );
	return '' !== $codigo ? $codigo . ' - ' . $nome : $nome;
}

// Ordena empresas (posts cp_empresa) pelo número de cliente da Work, como a
// equipa as conhece (001, 002, …, A01, …; ordem natural). As que ainda não
// receberam o número vão no fim, por nome.
function cp_ac_ordenar_empresas( array $empresas ) {
	usort( $empresas, function ( $a, $b ) {
		$ca = (string) get_post_meta( $a->ID, '_cp_codigo', true );
		$cb = (string) get_post_meta( $b->ID, '_cp_codigo', true );
		if ( ( '' === $ca ) !== ( '' === $cb ) ) return '' === $ca ? 1 : -1;
		$r = strnatcasecmp( $ca, $cb );
		return 0 !== $r ? $r : strnatcasecmp( $a->post_title, $b->post_title );
	} );
	return $empresas;
}

// ─── Associação Utilizador ↔ Empresas ──────────────────────────────────────
// Lista de IDs de "cp_empresa" guardada no usermeta 'cp_empresas'.

function cp_ac_user_empresas( $user_id = null ) {
	$user_id = $user_id ?: get_current_user_id();
	if ( ! $user_id ) return [];
	// Equipa em "Ver como o cliente" (ver inc/area-cliente-backoffice.php):
	// só ao desenhar /area-de-cliente e só para o próprio utilizador.
	if ( (int) $user_id === get_current_user_id() && function_exists( 'cp_bo_pre_visualizacao' ) && cp_bo_pre_visualizacao() ) {
		return [ cp_bo_pre_visualizacao() ];
	}
	$ids = get_user_meta( $user_id, 'cp_empresas', true );
	$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	return array_values( array_filter( $ids, function ( $id ) {
		return get_post_type( $id ) === 'cp_empresa' && get_post_status( $id ) === 'publish';
	} ) );
}

function cp_ac_set_user_empresas( $user_id, array $empresa_ids ) {
	update_user_meta( $user_id, 'cp_empresas', array_map( 'intval', $empresa_ids ) );
}

// Campo no perfil do utilizador (wp-admin → Utilizadores → Editar) para
// associar empresas. O nonce é o nativo do próprio formulário de perfil.
function cp_ac_user_profile_empresas_field( $user ) {
	if ( ! current_user_can( 'edit_users' ) ) return;
	$empresas     = cp_ac_ordenar_empresas( get_posts( [ 'post_type' => 'cp_empresa', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) );
	$selecionadas = cp_ac_user_empresas( $user->ID );
	?>
	<h2><?php esc_html_e( 'Área de Cliente — Empresas', 'contabilidade-pro' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><label for="cp_empresas"><?php esc_html_e( 'Empresas associadas', 'contabilidade-pro' ); ?></label></th>
			<td>
				<?php if ( empty( $empresas ) ) : ?>
					<p><em><?php esc_html_e( 'Ainda não existem empresas criadas.', 'contabilidade-pro' ); ?></em></p>
				<?php else :
					foreach ( $empresas as $empresa ) : ?>
						<label style="display:block;margin-bottom:4px">
							<input type="checkbox" name="cp_empresas[]" value="<?php echo esc_attr( $empresa->ID ); ?>"
								<?php checked( in_array( $empresa->ID, $selecionadas, true ) ); ?>>
							<?php echo esc_html( $empresa->post_title ); ?>
						</label>
					<?php endforeach;
				endif; ?>
				<p class="description"><?php esc_html_e( 'A que empresa(s) este utilizador tem acesso na Área de Cliente.', 'contabilidade-pro' ); ?></p>
			</td>
		</tr>
		<?php if ( $selecionadas ) :
			$acessos  = cp_ac_user_acessos( $user->ID );
			$nomes    = wp_list_pluck( cp_ac_ferramentas(), 'label' ); ?>
		<tr>
			<th><?php esc_html_e( 'Ferramentas por empresa', 'contabilidade-pro' ); ?></th>
			<td>
				<?php foreach ( $selecionadas as $empresa_id ) :
					$ativas = cp_ac_utilizador_ferramentas_ativas( $user->ID, $empresa_id ); ?>
					<p style="margin:0 0 6px">
						<strong><?php echo esc_html( cp_ac_empresa_label( $empresa_id ) ); ?>:</strong>
						<?php if ( ! array_key_exists( $empresa_id, $acessos ) ) :
							esc_html_e( 'tudo o que a empresa tem', 'contabilidade-pro' );
						elseif ( ! $ativas ) :
							esc_html_e( 'só o Dashboard', 'contabilidade-pro' );
						else :
							echo esc_html( implode( ', ', array_map( function ( $k ) use ( $nomes ) { return $nomes[ $k ]; }, $ativas ) ) );
						endif; ?>
					</p>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Definido na Work, na ficha do cliente (Acessos à Área de Cliente).', 'contabilidade-pro' ); ?></p>
			</td>
		</tr>
		<?php endif; ?>
	</table>
	<?php
}
add_action( 'show_user_profile', 'cp_ac_user_profile_empresas_field' );
add_action( 'edit_user_profile', 'cp_ac_user_profile_empresas_field' );

function cp_ac_save_user_profile_empresas( $user_id ) {
	if ( ! current_user_can( 'edit_users' ) ) return;
	$empresas = isset( $_POST['cp_empresas'] ) ? array_map( 'intval', (array) $_POST['cp_empresas'] ) : [];
	cp_ac_set_user_empresas( $user_id, $empresas );
}
add_action( 'personal_options_update', 'cp_ac_save_user_profile_empresas' );
add_action( 'edit_user_profile_update', 'cp_ac_save_user_profile_empresas' );

// ─── Matriz de Ferramentas por Empresa ─────────────────────────────────────
// Guardada como postmeta '_cp_ferramentas_ativas' no post da empresa.
// Uma ferramenta nova, ainda não gravada em nenhuma empresa, fica OFF por
// defeito — é assim que se testa uma funcionalidade nova só num cliente
// antes de a ligar a todos.

function cp_ac_empresa_ferramentas_ativas( $empresa_id ) {
	$ativas = get_post_meta( $empresa_id, '_cp_ferramentas_ativas', true );
	return is_array( $ativas ) ? $ativas : [];
}

function cp_ac_empresa_tem_ferramenta( $empresa_id, $chave ) {
	return in_array( $chave, cp_ac_empresa_ferramentas_ativas( $empresa_id ), true );
}

function cp_ac_set_empresa_ferramentas( $empresa_id, array $chaves ) {
	$validas = array_keys( cp_ac_ferramentas() );
	$chaves  = array_values( array_intersect( $chaves, $validas ) );
	update_post_meta( $empresa_id, '_cp_ferramentas_ativas', $chaves );
}

// ─── Acessos por utilizador (dentro de cada empresa) ───────────────────────
// A matriz da empresa é o teto; cada utilizador pode ficar restrito a parte
// dela, empresa a empresa. Usermeta 'cp_ferramentas_por_empresa' =
// [ empresa_post_id => [chaves] ]. Empresa ausente do mapa = o utilizador
// herda tudo o que a empresa tem (é o caso de quase todos, e da equipa).
// Uma empresa presente com uma lista é "restrita": ferramenta nova ligada na
// empresa não aparece a este utilizador até a Work a acrescentar. Quem manda
// é a Work (campo "acessos" em POST /cp/v1/utilizadores).

function cp_ac_user_acessos( $user_id ) {
	$mapa = get_user_meta( $user_id, 'cp_ferramentas_por_empresa', true );
	if ( ! is_array( $mapa ) ) return [];
	$limpo = [];
	foreach ( $mapa as $empresa_id => $chaves ) {
		if ( is_array( $chaves ) ) $limpo[ (int) $empresa_id ] = array_values( array_map( 'strval', $chaves ) );
	}
	return $limpo;
}

function cp_ac_set_user_acessos( $user_id, array $mapa ) {
	$validas = array_keys( cp_ac_ferramentas() );
	$limpo   = [];
	foreach ( $mapa as $empresa_id => $chaves ) {
		if ( ! (int) $empresa_id || ! is_array( $chaves ) ) continue;
		$limpo[ (int) $empresa_id ] = array_values( array_intersect( array_map( 'strval', $chaves ), $validas ) );
	}
	if ( $limpo ) {
		update_user_meta( $user_id, 'cp_ferramentas_por_empresa', $limpo );
	} else {
		delete_user_meta( $user_id, 'cp_ferramentas_por_empresa' );
	}
}

// Ferramentas que este utilizador vê nesta empresa: empresa ∩ utilizador.
function cp_ac_utilizador_ferramentas_ativas( $user_id, $empresa_id ) {
	$da_empresa = cp_ac_empresa_ferramentas_ativas( $empresa_id );
	$acessos    = cp_ac_user_acessos( $user_id );
	if ( ! array_key_exists( (int) $empresa_id, $acessos ) ) return $da_empresa;
	return array_values( array_intersect( $da_empresa, $acessos[ (int) $empresa_id ] ) );
}

function cp_ac_utilizador_tem_ferramenta( $user_id, $empresa_id, $chave ) {
	return in_array( $chave, cp_ac_utilizador_ferramentas_ativas( $user_id, $empresa_id ), true );
}

// ─── Empresa ativa na sessão ────────────────────────────────────────────────
// Guardada no usermeta — sobrevive a trocar de separador, mas é sempre
// confirmada contra as empresas a que o utilizador tem mesmo acesso.

function cp_ac_empresa_ativa( $user_id = null ) {
	$user_id  = $user_id ?: get_current_user_id();
	if ( (int) $user_id === get_current_user_id() && function_exists( 'cp_bo_pre_visualizacao' ) && cp_bo_pre_visualizacao() ) {
		return cp_bo_pre_visualizacao(); // sem gravar cp_empresa_ativa da equipa
	}
	$empresas = cp_ac_user_empresas( $user_id );
	if ( empty( $empresas ) ) return 0;

	$guardada = (int) get_user_meta( $user_id, 'cp_empresa_ativa', true );
	if ( $guardada && in_array( $guardada, $empresas, true ) ) return $guardada;

	// Sem seleção válida: se só houver uma empresa, essa é a ativa.
	if ( count( $empresas ) === 1 ) {
		update_user_meta( $user_id, 'cp_empresa_ativa', $empresas[0] );
		return $empresas[0];
	}
	return 0; // várias empresas, nenhuma escolhida ainda → mostrar seletor
}

function cp_ac_definir_empresa_ativa( $empresa_id, $user_id = null ) {
	$user_id = $user_id ?: get_current_user_id();
	if ( in_array( (int) $empresa_id, cp_ac_user_empresas( $user_id ), true ) ) {
		update_user_meta( $user_id, 'cp_empresa_ativa', (int) $empresa_id );
		return true;
	}
	return false;
}

// Pedidos via URL numa página da Área de Cliente:
//   ?cp_empresa=123  → troca para essa empresa
//   ?cp_trocar=1     → limpa a seleção (mostra o seletor outra vez)
function cp_ac_handle_pedidos_url() {
	if ( ! is_user_logged_in() ) return;
	if ( isset( $_GET['cp_trocar'] ) ) {
		delete_user_meta( get_current_user_id(), 'cp_empresa_ativa' );
		return;
	}
	if ( isset( $_GET['cp_empresa'] ) ) {
		cp_ac_definir_empresa_ativa( absint( $_GET['cp_empresa'] ) );
	}
}
add_action( 'template_redirect', 'cp_ac_handle_pedidos_url' );

// ─── Gate principal: o utilizador atual tem a ferramenta X na empresa ativa? ──
// Empresa ∩ acessos do próprio utilizador (ver cp_ac_utilizador_ferramentas_ativas).

function cp_ac_tem_ferramenta( $chave ) {
	// Equipa em "Vista cliente": vê todas as ferramentas que existem, mesmo
	// as que o cliente não vê (a marca no menu diz quais estão ativas).
	if ( function_exists( 'cp_bo_pre_visualizacao' ) && cp_bo_pre_visualizacao() ) {
		return array_key_exists( $chave, cp_ac_ferramentas() );
	}
	$empresa = cp_ac_empresa_ativa();
	if ( ! $empresa ) return false;
	return cp_ac_utilizador_tem_ferramenta( get_current_user_id(), $empresa, $chave );
}

// ─── Gate dos formulários do cliente (admin-post / REST) ───────────────────
// Esconder o ecrã não chega: um pedido feito à mão chegava ao handler. Cada
// ação do cliente confirma que ele tem a ferramenta nesta empresa. A equipa
// (Backoffice) não passa por aqui — trabalha em qualquer empresa.

function cp_ac_pode_usar_ferramenta( $empresa_id, $chave, $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	if ( function_exists( 'cp_backoffice_utilizador_tem_acesso' ) && cp_backoffice_utilizador_tem_acesso( $user_id ) ) return true;
	return $empresa_id && cp_ac_utilizador_tem_ferramenta( $user_id, (int) $empresa_id, $chave );
}

function cp_ac_exigir_ferramenta( $empresa_id, $chave ) {
	if ( cp_ac_pode_usar_ferramenta( $empresa_id, $chave ) ) return;
	wp_die( esc_html__( 'Não tem acesso a esta ferramenta.', 'contabilidade-pro' ), '', [ 'response' => 403 ] );
}

// ─── Administração: matriz de ferramentas por empresa ─────────────────────

function cp_ac_admin_menu() {
	add_menu_page(
		__( 'Área de Cliente', 'contabilidade-pro' ),
		__( 'Área de Cliente', 'contabilidade-pro' ),
		'manage_options',
		'cp_area_cliente',
		'cp_ac_render_matriz_page',
		'dashicons-groups',
		56
	);
	add_submenu_page(
		'cp_area_cliente',
		__( 'Ferramentas por Empresa', 'contabilidade-pro' ),
		__( 'Ferramentas por Empresa', 'contabilidade-pro' ),
		'manage_options',
		'cp_area_cliente',
		'cp_ac_render_matriz_page'
	);
}
add_action( 'admin_menu', 'cp_ac_admin_menu' );

function cp_ac_render_matriz_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	// SITE-002: a matriz é gerida só na Work (Portal do cliente › Ferramentas).
	// Cada POST /cp/v1/empresas com "ferramentas" substitui a lista inteira, por
	// isso o que se ligasse aqui era apagado na gravação seguinte da Work. Aqui
	// fica só a consulta — sem formulário nem tratamento de POST.
	$empresas    = cp_ac_ordenar_empresas( get_posts( [ 'post_type' => 'cp_empresa', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) );
	$ferramentas = cp_ac_ferramentas();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Ferramentas por Empresa', 'contabilidade-pro' ); ?></h1>
		<div class="notice notice-info inline" style="margin:12px 0 0">
			<p><?php esc_html_e( 'Só de leitura. A matriz de ferramentas gere-se na app Work (Portal do cliente › Ferramentas), que a envia para aqui — o que se mudasse neste ecrã seria apagado no envio seguinte. O Dashboard está sempre visível e não aparece aqui.', 'contabilidade-pro' ); ?></p>
		</div>

		<?php if ( empty( $empresas ) ) : ?>
			<p><em><?php esc_html_e( 'Ainda não existem empresas. São criadas pela Work ao sincronizar cada cliente.', 'contabilidade-pro' ); ?></em></p>
		<?php else : ?>
		<table class="widefat striped" style="margin-top:16px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Empresa', 'contabilidade-pro' ); ?></th>
					<?php foreach ( $ferramentas as $chave => $info ) : ?>
						<th style="text-align:center"><?php echo esc_html( $info['label'] ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $empresas as $empresa ) :
					$ativas = cp_ac_empresa_ferramentas_ativas( $empresa->ID ); ?>
					<tr>
						<td><strong><?php echo esc_html( $empresa->post_title ); ?></strong></td>
						<?php foreach ( $ferramentas as $chave => $info ) :
							$ligada = in_array( $chave, $ativas, true ); ?>
							<td style="text-align:center" title="<?php echo esc_attr( $ligada ? __( 'Ligada', 'contabilidade-pro' ) : __( 'Desligada', 'contabilidade-pro' ) ); ?>">
								<?php if ( $ligada ) : ?>
									<span class="dashicons dashicons-yes" style="color:#2f6b3e" aria-label="<?php esc_attr_e( 'Ligada', 'contabilidade-pro' ); ?>"></span>
								<?php else : ?>
									<span style="color:#a7aaad" aria-label="<?php esc_attr_e( 'Desligada', 'contabilidade-pro' ); ?>">—</span>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<?php
		// Passado como objeto (não array) de propósito: do_action() tem uma
		// compatibilidade com PHP4 que desembrulha silenciosamente um array
		// de 1 elemento quando esse elemento é um objeto — exatamente o caso
		// de haver só 1 empresa — entregando o WP_Post sozinho ao callback
		// em vez do array. Um objeto evita esse desembrulho em qualquer
		// contagem de empresas.
		do_action( 'cp_ac_matriz_extra', (object) [ 'empresas' => $empresas ] );
		?>
	</div>
	<?php
}


// ─── API de sincronização de Empresas (Work → Área de Cliente) ────────────
// Endpoint próprio em /wp-json/cp/v1/empresas usado pela app interna (Work)
// para sincronizar empresas-cliente. Sincronização é one-way: a Work envia
// dados para aqui, o WordPress nunca escreve de volta na Work. Autenticação
// via WordPress Application Passwords (utilizador com permissão para editar
// o CPT cp_empresa).

function cp_ac_rest_routes() {
    register_rest_route( 'cp/v1', '/empresas', [
        [
            'methods'             => 'GET',
            'callback'            => 'cp_ac_rest_listar_empresas',
            'permission_callback' => 'cp_ac_rest_permissao',
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_empresa',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id' => [ 'required' => true, 'type' => 'string' ],
                'nome'    => [ 'required' => true, 'type' => 'string' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes' );

function cp_ac_rest_permissao() {
    return current_user_can( 'edit_posts' );
}

function cp_ac_rest_listar_empresas( $request ) {
    $empresas = get_posts( [
        'post_type'      => 'cp_empresa',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ] );
    $resultado = [];
    foreach ( $empresas as $empresa ) {
        $resultado[] = [
            'id'      => $empresa->ID,
            'nome'    => $empresa->post_title,
            'work_id' => get_post_meta( $empresa->ID, '_cp_work_id', true ),
            'ferramentas' => cp_ac_empresa_ferramentas_ativas( $empresa->ID ),
        ];
    }
    return rest_ensure_response( $resultado );
}

function cp_ac_rest_sincronizar_empresa( $request ) {
    $work_id = sanitize_text_field( $request->get_param( 'work_id' ) );
    $nome    = sanitize_text_field( $request->get_param( 'nome' ) );
    $codigo  = $request->get_param( 'codigo' );
    $ferramentas_param = $request->get_param( 'ferramentas' );

    if ( '' === $work_id || '' === $nome ) {
        return new WP_Error(
            'cp_dados_invalidos',
            __( 'work_id e nome são obrigatórios.', 'contabilidade-pro' ),
            [ 'status' => 400 ]
        );
    }

    $existente = get_posts( [
        'post_type'   => 'cp_empresa',
        'meta_key'    => '_cp_work_id',
        'meta_value'  => $work_id,
        'numberposts' => 1,
        'post_status' => 'any',
    ] );

    if ( ! empty( $existente ) ) {
        $post_id = $existente[0]->ID;
        wp_update_post( [
            'ID'          => $post_id,
            'post_title'  => $nome,
            'post_status' => 'publish',
        ] );
        $criado = false;
    } else {
        $post_id = wp_insert_post( [
            'post_type'   => 'cp_empresa',
            'post_title'  => $nome,
            'post_status' => 'publish',
        ] );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }
        update_post_meta( $post_id, '_cp_work_id', $work_id );
        $criado = true;
    }

    // Sem 'codigo' no pedido (versões antigas da Work) não se mexe no que há.
    if ( null !== $codigo ) {
        update_post_meta( $post_id, '_cp_codigo', sanitize_text_field( (string) $codigo ) );
    }

    if ( is_array( $ferramentas_param ) ) {
        cp_ac_set_empresa_ferramentas( $post_id, array_map( 'sanitize_key', $ferramentas_param ) );
    }

    return rest_ensure_response( [
        'id'          => $post_id,
        'criado'      => $criado,
        'ferramentas' => cp_ac_empresa_ferramentas_ativas( $post_id ),
    ] );
}


// ─── API de criação de Utilizadores (Work → Área de Cliente) ──────────────
// Endpoint próprio em /wp-json/cp/v1/utilizadores usado pela app interna
// (Work) para criar a conta de login do cliente no WordPress e associar-lhe
// as empresas a que deve ter acesso (por work_id de empresa, já resolvido
// para o ID do post cp_empresa correspondente). A password gerada nunca é
// devolvida na resposta nem enviada pela Work — ao criar o utilizador
// dispara-se o fluxo nativo do WordPress para o cliente definir a sua
// própria password (link de "definir password").

function cp_ac_rest_routes_utilizadores() {
    register_rest_route( 'cp/v1', '/utilizadores', [
        [
            'methods'             => 'GET',
            'callback'            => 'cp_ac_rest_listar_utilizadores',
            'permission_callback' => 'cp_ac_rest_permissao_utilizadores',
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_criar_utilizador',
            'permission_callback' => 'cp_ac_rest_permissao_utilizadores',
            'args'                => [
                'work_id'  => [ 'required' => true, 'type' => 'string' ],
                'nome'     => [ 'required' => true, 'type' => 'string' ],
                'email'    => [ 'required' => true, 'type' => 'string' ],
                'empresas' => [ 'required' => false, 'type' => 'array' ],
                // Opcional: 'cliente' (subscriber, Área de Cliente) ou
                // 'equipa' (papel equipa_en, Backoffice — sem empresas).
                // Omitido = comportamento antigo (cria como cliente e, numa
                // atualização, não mexe no papel).
                'tipo'     => [ 'required' => false, 'type' => 'string', 'enum' => [ 'cliente', 'equipa' ] ],
                // Opcional (SITE-001): false desativa a conta (não entra, sessões
                // abertas terminadas, sem apagar nada); true reativa. Omitido = não
                // mexe. Nunca se aplica a administradores.
                'ativo'    => [ 'required' => false, 'type' => 'boolean' ],
                // Opcional: { "<work_id empresa>": null | ["chave", ...] }.
                // Omitido = não mexe nos acessos do utilizador; enviado (mesmo
                // {}) substitui o mapa inteiro. null ou empresa ausente = herda
                // tudo o que a empresa tem; lista = só essas (∩ empresa). Ver
                // cp_ac_utilizador_ferramentas_ativas().
                'acessos'  => [ 'required' => false ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_utilizadores' );

// Campo "acessos" do POST /cp/v1/utilizadores (ver o comentário nos args).
// Só aceita empresas que vieram em "empresas" neste mesmo pedido — um acesso
// a uma empresa que o utilizador não tem não faz sentido e é ignorado.
function cp_ac_rest_aplicar_acessos( $user_id, $acessos, array $post_por_work, $tipo ) {
    if ( 'equipa' === $tipo ) {
        delete_user_meta( $user_id, 'cp_ferramentas_por_empresa' );
        return;
    }
    if ( ! is_array( $acessos ) ) return; // omitido: não mexe
    $mapa = [];
    foreach ( $acessos as $empresa_work_id => $chaves ) {
        $empresa_work_id = (string) $empresa_work_id;
        if ( ! isset( $post_por_work[ $empresa_work_id ] ) || ! is_array( $chaves ) ) continue;
        $mapa[ $post_por_work[ $empresa_work_id ] ] = array_map( 'sanitize_key', $chaves );
    }
    cp_ac_set_user_acessos( $user_id, $mapa );
}

function cp_ac_rest_permissao_utilizadores() {
    return current_user_can( 'create_users' );
}

function cp_ac_rest_listar_utilizadores( $request ) {
    $utilizadores = get_users( [
        'meta_key'     => '_cp_work_id',
        'meta_compare' => 'EXISTS',
    ] );
    $resultado = [];
    foreach ( $utilizadores as $utilizador ) {
        $resultado[] = [
            'id'       => $utilizador->ID,
            'nome'     => $utilizador->display_name,
            'email'    => $utilizador->user_email,
            'work_id'  => get_user_meta( $utilizador->ID, '_cp_work_id', true ),
            'tipo'     => in_array( 'equipa_en', (array) $utilizador->roles, true ) ? 'equipa' : 'cliente',
            'empresas' => get_user_meta( $utilizador->ID, 'cp_empresas', true ),
            'acessos'  => cp_ac_user_acessos( $utilizador->ID ),
            'convite_enviado_em' => (string) get_user_meta( $utilizador->ID, '_cp_convite_enviado_em', true ),
        ];
    }
    return rest_ensure_response( $resultado );
}

function cp_ac_rest_criar_utilizador( $request ) {
    $work_id       = sanitize_text_field( $request->get_param( 'work_id' ) );
    $nome          = sanitize_text_field( $request->get_param( 'nome' ) );
    $email         = sanitize_email( $request->get_param( 'email' ) );
    $empresas_work = $request->get_param( 'empresas' );
    $empresas_work = is_array( $empresas_work ) ? array_map( 'sanitize_text_field', $empresas_work ) : [];
    $tipo          = $request->get_param( 'tipo' );
    $tipo          = in_array( $tipo, [ 'cliente', 'equipa' ], true ) ? $tipo : null;
    $papel         = 'equipa' === $tipo ? 'equipa_en' : 'subscriber';
    if ( 'equipa' === $tipo ) {
        $empresas_work = []; // a equipa vê todas as empresas no Backoffice
    }

    if ( '' === $work_id || '' === $nome || ! is_email( $email ) ) {
        return new WP_Error(
            'cp_dados_invalidos',
            __( 'work_id, nome e um email válido são obrigatórios.', 'contabilidade-pro' ),
            [ 'status' => 400 ]
        );
    }

    // Resolve os work_id das empresas para os IDs dos posts cp_empresa já
    // sincronizados. Uma empresa ainda não sincronizada é ignorada e
    // reportada em "empresas_nao_encontradas", para a Work saber que tem
    // de a sincronizar primeiro.
    $empresas_ids    = [];
    $nao_encontradas = [];
    $post_por_work   = [];
    foreach ( $empresas_work as $empresa_work_id ) {
        $posts = get_posts( [
            'post_type'   => 'cp_empresa',
            'meta_key'    => '_cp_work_id',
            'meta_value'  => $empresa_work_id,
            'numberposts' => 1,
            'post_status' => 'any',
        ] );
        if ( ! empty( $posts ) ) {
            $empresas_ids[]                            = $posts[0]->ID;
            $post_por_work[ (string) $empresa_work_id ] = (int) $posts[0]->ID;
        } else {
            $nao_encontradas[] = $empresa_work_id;
        }
    }

    // Já existe utilizador para este work_id? Atualiza em vez de duplicar.
    $existentes = get_users( [
        'meta_key'   => '_cp_work_id',
        'meta_value' => $work_id,
        'number'     => 1,
    ] );

    if ( ! empty( $existentes ) ) {
        $user_id = $existentes[0]->ID;
        // Num administrador a Work nunca muda nome nem email: mudar o email
        // de uma conta com plenos poderes por API abriria a porta a tomar
        // conta dela pela recuperação de senha.
        if ( ! user_can( $user_id, 'manage_options' ) ) {
            wp_update_user( [
                'ID'           => $user_id,
                'display_name' => $nome,
                'user_email'   => $email,
            ] );
        }
        // Papel só muda se a Work o indicar, e nunca a um administrador
        // (não despromover quem gere o site por engano).
        if ( null !== $tipo && ! user_can( $user_id, 'manage_options' ) ) {
            ( new WP_User( $user_id ) )->set_role( $papel );
        }
        if ( 'equipa' === $tipo ) {
            delete_user_meta( $user_id, 'cp_empresas' );
        } elseif ( ! empty( $empresas_ids ) ) {
            update_user_meta( $user_id, 'cp_empresas', array_map( 'intval', $empresas_ids ) );
        }
        cp_ac_rest_aplicar_acessos( $user_id, $request->get_param( 'acessos' ), $post_por_work, $tipo );
        cp_ac_aplicar_ativo( $user_id, $request->get_param( 'ativo' ) );
        return rest_ensure_response( [
            'id'                       => $user_id,
            'criado'                   => false,
            'empresas_nao_encontradas' => $nao_encontradas,
        ] );
    }

    // Email já usado por outra conta WordPress (sem work_id associado)?
    // Para a equipa, aproveita essa conta (ex.: o email geral, que já é
    // administrador): liga-a ao work_id e dá-lhe o papel equipa_en, a não
    // ser que já seja administrador. Não envia email de senha — a conta já
    // tem uma. Uma conta já ligada a outro utilizador da Work nunca é
    // aproveitada; para clientes continua a dar 409.
    $user_id_existente = email_exists( $email );
    if ( $user_id_existente && 'equipa' === $tipo
        && '' === (string) get_user_meta( $user_id_existente, '_cp_work_id', true ) ) {
        update_user_meta( $user_id_existente, '_cp_work_id', $work_id );
        if ( ! user_can( $user_id_existente, 'manage_options' ) ) {
            ( new WP_User( $user_id_existente ) )->set_role( 'equipa_en' );
        }
        delete_user_meta( $user_id_existente, 'cp_empresas' );
        delete_user_meta( $user_id_existente, 'cp_ferramentas_por_empresa' );
        return rest_ensure_response( [
            'id'                       => $user_id_existente,
            'criado'                   => false,
            'conta_existente'          => true,
            'empresas_nao_encontradas' => [],
        ] );
    }
    if ( $user_id_existente ) {
        return new WP_Error(
            'cp_email_existente',
            __( 'Já existe uma conta WordPress com este email.', 'contabilidade-pro' ),
            [ 'status' => 409 ]
        );
    }

    $username = sanitize_user( current( explode( '@', $email ) ), true );
    if ( username_exists( $username ) ) {
        $username .= '-' . wp_rand( 100, 999 );
    }

    $user_id = wp_insert_user( [
        'user_login'   => $username,
        'user_email'   => $email,
        'display_name' => $nome,
        'user_pass'    => wp_generate_password( 32, true, true ), // nunca comunicada
        'role'         => $papel,
    ] );

    if ( is_wp_error( $user_id ) ) {
        return $user_id;
    }

    update_user_meta( $user_id, '_cp_work_id', $work_id );
    if ( ! empty( $empresas_ids ) ) {
        update_user_meta( $user_id, 'cp_empresas', array_map( 'intval', $empresas_ids ) );
    }
    cp_ac_rest_aplicar_acessos( $user_id, $request->get_param( 'acessos' ), $post_por_work, $tipo );
    cp_ac_aplicar_ativo( $user_id, $request->get_param( 'ativo' ) );

    // Dispara o fluxo nativo do WordPress para o cliente definir a própria
    // password (a password gerada acima nunca é enviada nem devolvida). Para
    // clientes, o texto é o convite da Entre Números (cp_ac_email_convite).
    // Uma conta criada já desativada não recebe convite.
    if ( false !== $request->get_param( 'ativo' ) ) {
        wp_new_user_notification( $user_id, null, 'user' );
    }

    return rest_ensure_response( [
        'id'                       => $user_id,
        'criado'                   => true,
        'empresas_nao_encontradas' => $nao_encontradas,
        'convite_enviado_em'       => (string) get_user_meta( $user_id, '_cp_convite_enviado_em', true ),
    ] );
}


// ─── Conta desativada pela Work (SITE-001) ─────────────────────────────────
// Desativar um utilizador na Work grava `_cp_inativo` aqui: deixa de conseguir
// entrar (nem repor a senha) e as sessões abertas terminam. Nada é apagado —
// reativar volta a dar o acesso tal como estava. Administradores nunca.

// Pura (testada): esta conta está bloqueada?
function cp_ac_calc_conta_bloqueada( $marca_inativo, $e_admin ) {
    return ! $e_admin && ! empty( $marca_inativo );
}

function cp_ac_conta_bloqueada( $user_id ) {
    return cp_ac_calc_conta_bloqueada( get_user_meta( $user_id, '_cp_inativo', true ), user_can( $user_id, 'manage_options' ) );
}

function cp_ac_aplicar_ativo( $user_id, $ativo ) {
    if ( null === $ativo || user_can( $user_id, 'manage_options' ) ) return;
    if ( $ativo ) {
        delete_user_meta( $user_id, '_cp_inativo' );
        return;
    }
    update_user_meta( $user_id, '_cp_inativo', current_time( 'mysql' ) );
    if ( class_exists( 'WP_Session_Tokens' ) ) {
        WP_Session_Tokens::get_instance( $user_id )->destroy_all();
    }
}

function cp_ac_bloquear_login_inativo( $user, $password ) {
    if ( $user instanceof WP_User && cp_ac_conta_bloqueada( $user->ID ) ) {
        return new WP_Error( 'cp_conta_inativa', __( 'Esta conta está desativada. Se precisa de acesso, contacte a Entre Números.', 'contabilidade-pro' ) );
    }
    return $user;
}
add_filter( 'wp_authenticate_user', 'cp_ac_bloquear_login_inativo', 10, 2 );

function cp_ac_sem_reposicao_inativo( $permitir, $user_id ) {
    return cp_ac_conta_bloqueada( $user_id ) ? false : $permitir;
}
add_filter( 'allow_password_reset', 'cp_ac_sem_reposicao_inativo', 10, 2 );

// ─── Convite para a Área de Cliente ────────────────────────────────────────
// O email nativo "definir a senha" do WordPress, com o texto da Entre Números,
// para os clientes criados pela Work. A equipa continua com o email nativo.
// A Work pode pedir que se volte a enviar (POST /cp/v1/utilizadores/convite):
// gera um link novo, válido 24 h como qualquer reposição de senha.

// Texto do convite (pura). $empresas: nomes das empresas do utilizador.
function cp_ac_calc_texto_convite( $nome, array $empresas, $email, $url_senha, $url_area ) {
    $nome    = trim( (string) $nome );
    $empresas = array_values( array_filter( array_map( 'trim', $empresas ) ) );
    if ( 1 === count( $empresas ) ) {
        $para = ' para ' . $empresas[0];
    } elseif ( $empresas ) {
        $ultima = array_pop( $empresas );
        $para   = ' para ' . implode( ', ', $empresas ) . ' e ' . $ultima;
    } else {
        $para = '';
    }

    $corpo  = ( '' !== $nome ? "Olá {$nome},\n\n" : "Olá,\n\n" );
    $corpo .= "Criámos o seu acesso à Área de Cliente da Entre Números{$para}. ";
    $corpo .= "É lá que nos pode enviar documentos, falar connosco e acompanhar o que temos em curso.\n\n";
    $corpo .= "Para começar, defina a sua senha neste link:\n{$url_senha}\n\n";
    $corpo .= "O link é válido durante 24 horas. Se expirar, use \"Esqueceu-se da sua senha?\" na página de entrada ou peça-nos um novo convite.\n\n";
    $corpo .= "Depois, entre sempre por {$url_area} com este email ({$email}) e a senha que escolher.\n\n";
    $corpo .= "Entre Números";

    return [ 'assunto' => 'Convite para a Área de Cliente — Entre Números', 'corpo' => $corpo ];
}

// Cliente criado pela Work (nunca equipa nem administrador).
function cp_ac_e_cliente_work( $user ) {
    if ( ! $user instanceof WP_User ) return false;
    if ( '' === (string) get_user_meta( $user->ID, '_cp_work_id', true ) ) return false;
    if ( in_array( 'equipa_en', (array) $user->roles, true ) || user_can( $user, 'manage_options' ) ) return false;
    return true;
}

function cp_ac_email_convite( $email, $user, $blogname ) {
    if ( ! cp_ac_e_cliente_work( $user ) ) return $email;
    // Link novo (substitui o que o WordPress acabou de gerar e não chega a sair).
    $chave = get_password_reset_key( $user );
    if ( is_wp_error( $chave ) ) return $email;
    $url_senha = network_site_url( 'wp-login.php?action=rp&key=' . $chave . '&login=' . rawurlencode( $user->user_login ), 'login' );
    $empresas  = array_map( 'get_the_title', cp_ac_user_empresas( $user->ID ) );
    $texto     = cp_ac_calc_texto_convite( $user->display_name, $empresas, $user->user_email, $url_senha, home_url( '/area-de-cliente/' ) );
    update_user_meta( $user->ID, '_cp_convite_enviado_em', current_time( 'mysql' ) );
    return [
        'to'      => $user->user_email,
        'subject' => $texto['assunto'],
        'message' => $texto['corpo'],
        'headers' => [ 'Content-Type: text/plain; charset=UTF-8' ],
    ];
}
add_filter( 'wp_new_user_notification_email', 'cp_ac_email_convite', 10, 3 );

// ─── Clientes nunca vão parar ao wp-admin ──────────────────────────────────
// Depois de definir a senha (link do convite ou "Esqueceu-se da sua
// senha?"), o cliente entra em wp-login.php e o WordPress mandava-o para o
// wp-admin, que para um subscriber é o ecrã Perfil (paleta de cores, etc.).
// Quem não é da equipa (sem cp_backoffice nem manage_options) vai sempre
// para a Área de Cliente: ao entrar (login_redirect) e se abrir qualquer
// página do wp-admin (admin_init). admin-post.php e admin-ajax.php ficam
// livres — os formulários da Área de Cliente passam por lá.

function cp_ac_area_cliente_url() {
    $pagina = get_page_by_path( 'area-de-cliente' );
    return $pagina ? get_permalink( $pagina ) : home_url( '/area-de-cliente/' );
}

function cp_ac_so_cliente( $user_id ) {
    return $user_id && ! cp_backoffice_utilizador_tem_acesso( $user_id );
}

// Pura (testada): para onde vai um utilizador depois de entrar.
// Um cliente vai para a Área de Cliente se não pediu nada ou se o pedido
// era o wp-admin; um pedido para outra página do site é respeitado.
function cp_ac_calc_destino_login( $e_cliente, $pedido, $redirect_to, $url_area ) {
    if ( ! $e_cliente ) return $redirect_to;
    $pedido = (string) $pedido;
    if ( '' === $pedido || false !== strpos( $pedido, '/wp-admin' ) || false !== strpos( $pedido, 'wp-login.php' ) ) {
        return $url_area;
    }
    return $redirect_to;
}

function cp_ac_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
    if ( ! ( $user instanceof WP_User ) ) return $redirect_to;
    return cp_ac_calc_destino_login( cp_ac_so_cliente( $user->ID ), $requested_redirect_to, $redirect_to, cp_ac_area_cliente_url() );
}
add_filter( 'login_redirect', 'cp_ac_login_redirect', 30, 3 );

function cp_ac_fechar_wp_admin_a_clientes() {
    if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return;
    if ( 'admin-post.php' === ( $GLOBALS['pagenow'] ?? '' ) ) return;
    if ( ! cp_ac_so_cliente( get_current_user_id() ) ) return;
    wp_safe_redirect( cp_ac_area_cliente_url() );
    exit;
}
add_action( 'admin_init', 'cp_ac_fechar_wp_admin_a_clientes' );

function cp_ac_rest_routes_convite() {
    register_rest_route( 'cp/v1', '/utilizadores/convite', [
        'methods'             => 'POST',
        'callback'            => 'cp_ac_rest_reenviar_convite',
        'permission_callback' => 'cp_ac_rest_permissao_utilizadores',
        'args'                => [
            'work_id' => [ 'required' => true, 'type' => 'string' ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_convite' );

function cp_ac_rest_reenviar_convite( $request ) {
    $work_id = sanitize_text_field( $request->get_param( 'work_id' ) );
    $users   = '' === $work_id ? [] : get_users( [ 'meta_key' => '_cp_work_id', 'meta_value' => $work_id, 'number' => 1 ] );
    if ( empty( $users ) ) {
        return new WP_Error( 'cp_utilizador_nao_encontrado', __( 'Nenhum utilizador do site com este work_id.', 'contabilidade-pro' ), [ 'status' => 404 ] );
    }
    $user = $users[0];
    if ( ! cp_ac_e_cliente_work( $user ) ) {
        return new WP_Error( 'cp_convite_so_clientes', __( 'O convite só se envia a clientes — a equipa entra com a senha que já tem.', 'contabilidade-pro' ), [ 'status' => 409 ] );
    }

    $falhou = false;
    $apanhar = function () use ( &$falhou ) { $falhou = true; };
    add_action( 'wp_mail_failed', $apanhar );
    wp_new_user_notification( $user->ID, null, 'user' );
    remove_action( 'wp_mail_failed', $apanhar );
    if ( $falhou ) {
        return new WP_Error( 'cp_email_falhou', __( 'O site não conseguiu enviar o email do convite.', 'contabilidade-pro' ), [ 'status' => 502 ] );
    }

    return rest_ensure_response( [
        'ok'                 => true,
        'convite_enviado_em' => (string) get_user_meta( $user->ID, '_cp_convite_enviado_em', true ),
    ] );
}


// ─── Util: resolver empresa pelo work_id ───────────────────────────────────

function cp_ac_empresa_by_work_id( $work_id ) {
    $work_id = sanitize_text_field( $work_id );
    if ( '' === $work_id ) return 0;
    $posts = get_posts( [
        'post_type'   => 'cp_empresa',
        'meta_key'    => '_cp_work_id',
        'meta_value'  => $work_id,
        'numberposts' => 1,
        'post_status' => 'any',
    ] );
    return empty( $posts ) ? 0 : $posts[0]->ID;
}

// Resposta de erro consistente quando o work_id não corresponde a nenhuma
// empresa sincronizada (a Work tem de sincronizar a empresa primeiro).
function cp_ac_rest_erro_empresa_nao_encontrada() {
    return new WP_Error(
        'cp_empresa_nao_encontrada',
        __( 'Nenhuma empresa sincronizada com este work_id.', 'contabilidade-pro' ),
        [ 'status' => 404 ]
    );
}

// Guarda um ficheiro recebido por multipart (campo $campo de $_FILES, via
// WP_REST_Request::get_file_params()) na media library e devolve
// [ 'id' => attachment_id, 'url' => attachment_url ] ou um WP_Error.
function cp_ac_rest_guardar_ficheiro( $request, $campo ) {
    $ficheiros = $request->get_file_params();
    if ( empty( $ficheiros[ $campo ] ) || UPLOAD_ERR_OK !== $ficheiros[ $campo ]['error'] ) {
        return new WP_Error(
            'cp_ficheiro_em_falta',
            sprintf( /* translators: %s: nome do campo */ __( 'Ficheiro em falta ou inválido no campo "%s".', 'contabilidade-pro' ), $campo ),
            [ 'status' => 400 ]
        );
    }
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }
    $anexo_id = media_handle_upload( $campo, 0, [], [
        'test_form' => false,
        'action'    => 'cp_ac_upload',
    ] );
    if ( is_wp_error( $anexo_id ) ) {
        return $anexo_id;
    }
    return [
        'id'  => $anexo_id,
        'url' => wp_get_attachment_url( $anexo_id ),
    ];
}


// ════════════════════════════════════════════════════════════════════════
// Pendentes (Work → Área de Cliente → Work)
// ════════════════════════════════════════════════════════════════════════
// Dois grupos por empresa: "documentos pendentes" (a Work pede um documento
// específico ao cliente) e "movimentos a esclarecer" (linhas do extrato
// bancário que a Work precisa que o cliente explique). A Work envia a
// lista completa (substitui sempre tudo) via POST /pendentes. O cliente
// responde no site (upload e/ou texto); essas respostas ficam guardadas em
// '_cp_pendentes_respostas' até a Work as ir buscar (GET /respostas) e
// confirmar que as processou (POST /respostas/confirmar), altura em que são
// apagadas daqui — o WordPress nunca é a fonte de verdade definitiva, é só
// uma caixa de correio de ida e volta.

function cp_ac_pendentes_documentos( $empresa_id ) {
    $itens = get_post_meta( $empresa_id, '_cp_pendentes_documentos', true );
    return is_array( $itens ) ? $itens : [];
}

// Documentos em falta já tratados (desde 2026-11-24): a Work envia-os à
// parte em `documentos_tratados`, só para o cliente os ver no filtro
// "Tratados" — nunca aceitam resposta (não estão em
// cp_ac_pendentes_documentos, que é a lista validada no envio).
// estado: 'enviado' (o cliente mandou), 'recebido' (a contabilidade já tem
// o documento) ou 'tratado' (já lançado).
function cp_ac_pendentes_documentos_tratados( $empresa_id ) {
    $itens = get_post_meta( $empresa_id, '_cp_pendentes_documentos_tratados', true );
    return is_array( $itens ) ? $itens : [];
}

// Pura (testada): lista de documentos tratados vinda da Work, limpa.
// Estado desconhecido fica 'tratado'; itens sem id saem.
function cp_ac_calc_documentos_tratados( $lista ) {
    $saida = [];
    foreach ( (array) $lista as $doc ) {
        if ( ! is_array( $doc ) || empty( $doc['id'] ) ) continue;
        $estado  = isset( $doc['estado'] ) ? (string) $doc['estado'] : '';
        $saida[] = [
            'id'        => sanitize_text_field( $doc['id'] ),
            'titulo'    => isset( $doc['titulo'] ) ? sanitize_text_field( $doc['titulo'] ) : '',
            'pedido_em' => isset( $doc['pedido_em'] ) ? sanitize_text_field( $doc['pedido_em'] ) : '',
            'mes'       => isset( $doc['mes'] ) && preg_match( '/^\d{4}-\d{2}$/', (string) $doc['mes'] ) ? (string) $doc['mes'] : '',
            'estado'    => in_array( $estado, [ 'enviado', 'recebido', 'tratado' ], true ) ? $estado : 'tratado',
        ];
    }
    return $saida;
}

// Pura (testada): qual filtro mostrar na vista Documentos em Falta —
// 'pendentes' por omissão, 'tratados' só se pedido.
function cp_ac_calc_filtro_documentos( $pedido ) {
    return 'tratados' === $pedido ? 'tratados' : 'pendentes';
}

function cp_ac_pendentes_movimentos( $empresa_id ) {
    $itens = get_post_meta( $empresa_id, '_cp_pendentes_movimentos', true );
    return is_array( $itens ) ? $itens : [];
}

function cp_ac_pendentes_respostas( $empresa_id ) {
    $itens = get_post_meta( $empresa_id, '_cp_pendentes_respostas', true );
    return is_array( $itens ) ? $itens : [];
}

// Tipos de ficheiro aceites em cada resposta (decisão de 2026-11-13):
// documentos em falta e movimentos → PDF ou imagem (seguem para a
// contabilidade, como os Documentos); extratos → PDF ou Excel; SAF-T → XML.
// Sem isto o WordPress usava a lista dele, que não tem XML.
function cp_ac_pendente_mimes_permitidos( $tipo ) {
    if ( 'extrato' === $tipo ) {
        return [
            'pdf'  => 'application/pdf',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }
    if ( 'saft' === $tipo ) {
        return [ 'xml' => 'text/xml' ];
    }
    return cp_ac_documento_mimes_permitidos();
}

// O WordPress só aceita tipos que estejam também na lista geral de uploads
// do site (get_allowed_mime_types), e o XML não está. Junta os tipos desta
// resposta a essa lista só enquanto se trata este envio, para o XML não
// ficar aceite em mais nenhum upload do site.
function cp_ac_pendente_permitir_tipos( $tipo, $ligar ) {
    static $filtro = null;
    if ( $ligar ) {
        $extra  = cp_ac_pendente_mimes_permitidos( $tipo );
        $filtro = function ( $mimes ) use ( $extra ) { return array_merge( $mimes, $extra ); };
        add_filter( 'upload_mimes', $filtro );
    } elseif ( $filtro ) {
        remove_filter( 'upload_mimes', $filtro );
        $filtro = null;
    }
}

// Para o atributo accept do campo de ficheiro (".pdf,.xls,.xlsx").
function cp_ac_pendente_accept( $tipo ) {
    $ext = [];
    foreach ( array_keys( cp_ac_pendente_mimes_permitidos( $tipo ) ) as $chave ) {
        foreach ( explode( '|', $chave ) as $e ) $ext[] = '.' . $e;
    }
    return implode( ',', $ext );
}

function cp_ac_pendente_tipos_texto( $tipo ) {
    if ( 'extrato' === $tipo ) return __( 'PDF ou Excel', 'contabilidade-pro' );
    if ( 'saft' === $tipo ) return __( 'XML (o ficheiro SAF-T)', 'contabilidade-pro' );
    return __( 'PDF ou imagem (JPG, PNG)', 'contabilidade-pro' );
}

// Devolve a resposta (se existir) para um item (documento ou movimento).
function cp_ac_pendente_resposta_do_item( $empresa_id, $item_id ) {
    foreach ( cp_ac_pendentes_respostas( $empresa_id ) as $resposta ) {
        if ( $resposta['item_id'] === $item_id ) return $resposta;
    }
    return null;
}

function cp_ac_rest_routes_pendentes() {
    register_rest_route( 'cp/v1', '/pendentes', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_pendentes',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id'    => [ 'required' => true, 'type' => 'string' ],
                'documentos' => [ 'required' => false, 'type' => 'array' ],
                'movimentos' => [ 'required' => false, 'type' => 'array' ],
                'documentos_tratados' => [ 'required' => false, 'type' => 'array' ],
            ],
        ],
    ] );
    register_rest_route( 'cp/v1', '/pendentes/respostas', [
        [
            'methods'             => 'GET',
            'callback'            => 'cp_ac_rest_listar_respostas_pendentes',
            'permission_callback' => 'cp_ac_rest_permissao',
        ],
    ] );
    register_rest_route( 'cp/v1', '/pendentes/respostas/confirmar', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_confirmar_respostas_pendentes',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id' => [ 'required' => true, 'type' => 'string' ],
                'ids'     => [ 'required' => true, 'type' => 'array' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_pendentes' );

// ─── Email de pendentes pedido pela Work (WORK-032/033, Etapas 5 e 6) ──────
// A Work prepara o texto (modelo revisto pela equipa) e pede ao site que o
// envie — o site é quem tem o email (wp_mail) e a notificação push. Só sai
// para utilizadores da empresa que veem Documentos em Falta ou Movimentos a
// Esclarecer (nunca equipa); se a Work mandar a lista `emails`, envia só à
// interseção. Nunca é automático: só quando alguém carrega na Work.

function cp_ac_pend_destinatarios( $empresa_id ) {
    $destinatarios = [];
    foreach ( get_users( [ 'meta_key' => 'cp_empresas', 'meta_compare' => 'EXISTS' ] ) as $u ) {
        if ( ! in_array( (int) $empresa_id, cp_ac_user_empresas( $u->ID ), true ) ) continue;
        if ( ! cp_ac_utilizador_tem_ferramenta( $u->ID, (int) $empresa_id, 'documentos_em_falta' )
            && ! cp_ac_utilizador_tem_ferramenta( $u->ID, (int) $empresa_id, 'movimentos_a_esclarecer' ) ) continue;
        if ( cp_backoffice_utilizador_tem_acesso( $u->ID ) ) continue;
        if ( ! is_email( $u->user_email ) ) continue;
        $destinatarios[] = $u;
    }
    return $destinatarios;
}

// Pura (testada): dos emails válidos da empresa, quais recebem. Sem pedido
// (null) recebem todos; com pedido, só os pedidos que são válidos.
function cp_ac_calc_filtrar_destinatarios( array $validos, $pedidos ) {
    $validos_min = array_map( 'strtolower', $validos );
    if ( null === $pedidos ) return [ 'enviar' => $validos, 'ignorados' => [] ];
    $enviar = $ignorados = [];
    foreach ( (array) $pedidos as $email ) {
        $i = array_search( strtolower( trim( (string) $email ) ), $validos_min, true );
        if ( false === $i ) $ignorados[] = (string) $email;
        elseif ( ! in_array( $validos[ $i ], $enviar, true ) ) $enviar[] = $validos[ $i ];
    }
    return [ 'enviar' => $enviar, 'ignorados' => $ignorados ];
}

function cp_ac_rest_routes_pendentes_email() {
    register_rest_route( 'cp/v1', '/pendentes/email', [
        'methods'             => 'POST',
        'callback'            => 'cp_ac_rest_pendentes_email',
        'permission_callback' => 'cp_ac_rest_permissao',
        'args'                => [
            'work_id' => [ 'required' => true, 'type' => 'string' ],
            'assunto' => [ 'required' => true, 'type' => 'string' ],
            'corpo'   => [ 'required' => true, 'type' => 'string' ],
            'emails'  => [ 'required' => false, 'type' => 'array' ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_pendentes_email' );

function cp_ac_rest_pendentes_email( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
    $assunto = trim( wp_strip_all_tags( (string) $request->get_param( 'assunto' ) ) );
    $corpo   = trim( (string) $request->get_param( 'corpo' ) );
    if ( '' === $assunto || '' === $corpo ) {
        return new WP_Error( 'cp_email_vazio', __( 'Falta o assunto ou o texto do email.', 'contabilidade-pro' ), [ 'status' => 400 ] );
    }

    $validos = [];
    foreach ( cp_ac_pend_destinatarios( $empresa_id ) as $u ) $validos[] = $u->user_email;
    $pedidos = $request->get_param( 'emails' );
    $escolha = cp_ac_calc_filtrar_destinatarios( $validos, null === $pedidos ? null : (array) $pedidos );
    if ( empty( $escolha['enviar'] ) ) {
        return new WP_Error( 'cp_sem_destinatarios', __( 'Nenhum utilizador desta empresa vê os Pendentes no site.', 'contabilidade-pro' ), [ 'status' => 409, 'ignorados' => $escolha['ignorados'] ] );
    }

    $enviados = [];
    $falhados = [];
    foreach ( $escolha['enviar'] as $email ) {
        if ( wp_mail( $email, $assunto, $corpo, [ 'Content-Type: text/plain; charset=UTF-8' ] ) ) $enviados[] = $email;
        else $falhados[] = $email;
    }
    if ( $enviados ) {
        cp_push_notificar_empresa(
            $empresa_id,
            __( 'Pendentes', 'contabilidade-pro' ),
            $assunto,
            add_query_arg( 'vista', 'documentos_em_falta', home_url( '/area-de-cliente/' ) ),
            'documentos_em_falta'
        );
    }
    return rest_ensure_response( [
        'ok'        => ! empty( $enviados ),
        'enviados'  => $enviados,
        'falhados'  => $falhados,
        'ignorados' => $escolha['ignorados'],
    ] );
}

function cp_ac_rest_sincronizar_pendentes( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    // Guardado antes de sobrescrever, só para detetar o que é mesmo novo
    // (para notificar o cliente só quando há novidade a sério).
    $ids_documentos_antes = wp_list_pluck( cp_ac_pendentes_documentos( $empresa_id ), 'id' );
    $ids_movimentos_antes = wp_list_pluck( cp_ac_pendentes_movimentos( $empresa_id ), 'id' );

    $documentos_in = $request->get_param( 'documentos' );
    $movimentos_in = $request->get_param( 'movimentos' );

    $documentos = [];
    if ( is_array( $documentos_in ) ) {
        foreach ( $documentos_in as $doc ) {
            if ( empty( $doc['id'] ) ) continue;
            $documentos[] = [
                'id'        => sanitize_text_field( $doc['id'] ),
                'titulo'    => isset( $doc['titulo'] ) ? sanitize_text_field( $doc['titulo'] ) : '',
                'pedido_em' => isset( $doc['pedido_em'] ) ? sanitize_text_field( $doc['pedido_em'] ) : '',
            ];
        }
    }

    $movimentos = [];
    if ( is_array( $movimentos_in ) ) {
        foreach ( $movimentos_in as $mov ) {
            if ( empty( $mov['id'] ) ) continue;
            $movimentos[] = [
                'id'        => sanitize_text_field( $mov['id'] ),
                'data'      => isset( $mov['data'] ) ? sanitize_text_field( $mov['data'] ) : '',
                'valor'     => isset( $mov['valor'] ) ? (float) $mov['valor'] : 0,
                'descricao' => isset( $mov['descricao'] ) ? sanitize_text_field( $mov['descricao'] ) : '',
                'pergunta'  => isset( $mov['pergunta'] ) ? sanitize_text_field( $mov['pergunta'] ) : '',
            ];
        }
    }

    update_post_meta( $empresa_id, '_cp_pendentes_documentos', $documentos );
    update_post_meta( $empresa_id, '_cp_pendentes_movimentos', $movimentos );
    // Só quando a Work manda (uma Work antiga não manda: fica o que estava).
    $tratados_in = $request->get_param( 'documentos_tratados' );
    if ( null !== $tratados_in ) {
        update_post_meta( $empresa_id, '_cp_pendentes_documentos_tratados', cp_ac_calc_documentos_tratados( $tratados_in ) );
    }

    $ha_documento_novo = (bool) array_diff( wp_list_pluck( $documentos, 'id' ), $ids_documentos_antes );
    $ha_movimento_novo = (bool) array_diff( wp_list_pluck( $movimentos, 'id' ), $ids_movimentos_antes );
    if ( $ha_documento_novo && cp_ac_empresa_tem_ferramenta( $empresa_id, 'documentos_em_falta' ) ) {
        cp_push_notificar_empresa(
            $empresa_id,
            __( 'Novos documentos em falta', 'contabilidade-pro' ),
            __( 'Tem documentos novos à espera de resposta.', 'contabilidade-pro' ),
            add_query_arg( 'vista', 'documentos_em_falta', home_url( '/area-de-cliente/' ) ),
            'documentos_em_falta'
        );
    }
    if ( $ha_movimento_novo && cp_ac_empresa_tem_ferramenta( $empresa_id, 'movimentos_a_esclarecer' ) ) {
        cp_push_notificar_empresa(
            $empresa_id,
            __( 'Novos movimentos a esclarecer', 'contabilidade-pro' ),
            __( 'Tem movimentos bancários novos à espera de resposta.', 'contabilidade-pro' ),
            add_query_arg( 'vista', 'movimentos_a_esclarecer', home_url( '/area-de-cliente/' ) ),
            'movimentos_a_esclarecer'
        );
    }

    return rest_ensure_response( [
        'ok'                => true,
        'total_documentos'  => count( $documentos ),
        'total_movimentos'  => count( $movimentos ),
        'total_documentos_tratados' => count( cp_ac_pendentes_documentos_tratados( $empresa_id ) ),
    ] );
}

function cp_ac_rest_listar_respostas_pendentes( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    return rest_ensure_response( [
        'respostas' => cp_ac_pendentes_respostas( $empresa_id ),
    ] );
}

function cp_ac_rest_confirmar_respostas_pendentes( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $ids = array_map( 'sanitize_text_field', (array) $request->get_param( 'ids' ) );

    $restantes = array_values( array_filter( cp_ac_pendentes_respostas( $empresa_id ), function ( $resposta ) use ( $ids ) {
        return ! in_array( $resposta['item_id'], $ids, true );
    } ) );
    update_post_meta( $empresa_id, '_cp_pendentes_respostas', $restantes );

    return rest_ensure_response( [ 'ok' => true, 'restantes' => count( $restantes ) ] );
}

// ─── Resposta do cliente a um pendente (upload e/ou texto) ─────────────────
// Formulário simples no ecrã "Pendentes" — não é REST, é o próprio cliente
// autenticado a submeter via admin-post.php, como o formulário de contacto.

function cp_ac_handle_pendente_resposta() {
    if ( ! is_user_logged_in() ) wp_die( esc_html__( 'Sessão inválida.', 'contabilidade-pro' ) );
    if ( ! isset( $_POST['cp_pendente_nonce'] ) || ! wp_verify_nonce( $_POST['cp_pendente_nonce'], 'cp_pendente_resposta' ) ) {
        wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
    }

    $empresa_id = cp_ac_empresa_ativa();
    $item_id    = isset( $_POST['item_id'] ) ? sanitize_text_field( wp_unslash( $_POST['item_id'] ) ) : '';
    $tipo_in    = isset( $_POST['tipo'] ) ? sanitize_key( wp_unslash( $_POST['tipo'] ) ) : '';
    $tipo       = in_array( $tipo_in, [ 'documento', 'extrato', 'saft' ], true ) ? $tipo_in : 'movimento';
    cp_ac_exigir_ferramenta( $empresa_id, [
        'documento' => 'documentos_em_falta',
        'movimento' => 'movimentos_a_esclarecer',
        'extrato'   => 'extratos',
        'saft'      => 'saft',
    ][ $tipo ] );
    $texto      = isset( $_POST['texto'] ) ? sanitize_textarea_field( wp_unslash( $_POST['texto'] ) ) : '';
    // "Sem dados este mês" só faz sentido em Extratos e SAF-T — um mês sem
    // movimento bancário ou sem SAF-T é uma situação real, não um
    // esquecimento (ver inc/area-cliente-vistas.php, vistas Extratos/SAF-T).
    $sem_dados  = in_array( $tipo, [ 'extrato', 'saft' ], true ) && ! empty( $_POST['sem_dados'] );
    $voltar     = wp_get_referer() ? wp_get_referer() : home_url( '/' );

    // O item tem mesmo de pertencer à empresa ativa do utilizador.
    $listas_por_tipo = [
        'documento' => 'cp_ac_pendentes_documentos',
        'movimento' => 'cp_ac_pendentes_movimentos',
        'extrato'   => 'cp_ac_extratos',
        'saft'      => 'cp_ac_saft',
    ];
    // Um extrato/SAF-T que a Work já recebeu ('recebido') não aceita resposta.
    $itens_tipo  = array_filter( call_user_func( $listas_por_tipo[ $tipo ], $empresa_id ), function ( $item ) {
        return ! isset( $item['estado'] ) || 'recebido' !== $item['estado'];
    } );
    $ids_validos = wp_list_pluck( $itens_tipo, 'id' );
    if ( ! $empresa_id || '' === $item_id || ! in_array( $item_id, $ids_validos, true ) ) {
        wp_safe_redirect( add_query_arg( 'cp_erro', 'pendente_invalido', $voltar ) );
        exit;
    }

    $anexo_id  = 0;
    $anexo_url = '';
    $anexo_nome = '';
    if ( ! $sem_dados && ! empty( $_FILES['anexo'] ) && UPLOAD_ERR_NO_FILE !== $_FILES['anexo']['error'] ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        $anexo_nome_original = isset( $_FILES['anexo']['name'] ) ? sanitize_file_name( $_FILES['anexo']['name'] ) : '';
        $mimes = cp_ac_pendente_mimes_permitidos( $tipo );
        cp_ac_pendente_permitir_tipos( $tipo, true );
        $tipo_ficheiro = wp_check_filetype_and_ext( $_FILES['anexo']['tmp_name'], $_FILES['anexo']['name'], $mimes );
        if ( empty( $tipo_ficheiro['type'] ) ) {
            cp_ac_pendente_permitir_tipos( $tipo, false );
            wp_safe_redirect( add_query_arg( 'cp_erro', 'anexo_tipo_' . $tipo, $voltar ) );
            exit;
        }
        // Anexo ligado à empresa e marcado com o item (2026-11-24): é assim
        // que a limpeza por mês pedida pela Work (POST cp/v1/limpeza) o
        // encontra depois de a Work o recolher. Não aparece em Documentos
        // (essa lista só tem os anexos com '_cp_doc_cliente').
        $resultado = media_handle_upload( 'anexo', $empresa_id, [], [ 'test_form' => false, 'mimes' => $mimes ] );
        cp_ac_pendente_permitir_tipos( $tipo, false );
        if ( ! is_wp_error( $resultado ) ) {
            update_post_meta( $resultado, '_cp_resposta_tipo', $tipo );
            update_post_meta( $resultado, '_cp_resposta_item', $item_id );
            $anexo_id   = $resultado;
            $anexo_url  = wp_get_attachment_url( $resultado );
            $anexo_nome = $anexo_nome_original;
        }
    }

    if ( ! $sem_dados && '' === $texto && ! $anexo_id ) {
        wp_safe_redirect( add_query_arg( 'cp_erro', 'resposta_vazia', $voltar ) );
        exit;
    }

    $respostas = cp_ac_pendentes_respostas( $empresa_id );
    // Substitui uma resposta anterior ao mesmo item, se existir.
    $respostas = array_values( array_filter( $respostas, function ( $r ) use ( $item_id ) {
        return $r['item_id'] !== $item_id;
    } ) );
    $respostas[] = [
        'item_id'       => $item_id,
        'tipo'          => $tipo,
        'texto'         => $texto,
        'anexo_id'      => $anexo_id,
        'anexo_url'     => $anexo_url,
        'anexo_nome'    => $anexo_nome,
        'estado'        => $sem_dados ? 'sem_dados' : 'enviado',
        'respondido_em' => current_time( 'mysql' ),
        'respondido_por' => 'cliente',
    ];
    update_post_meta( $empresa_id, '_cp_pendentes_respostas', $respostas );

    cp_push_notificar_equipa(
        __( 'Nova resposta de cliente', 'contabilidade-pro' ),
        sprintf(
            /* translators: %s: nome da empresa */
            __( '%s respondeu a um pendente.', 'contabilidade-pro' ),
            get_the_title( $empresa_id )
        ),
        get_edit_post_link( $empresa_id ),
        $empresa_id,
        'pendentes'
    );

    wp_safe_redirect( add_query_arg( 'cp_sucesso', 'resposta_enviada', $voltar ) );
    exit;
}
add_action( 'admin_post_cp_pendente_responder', 'cp_ac_handle_pendente_resposta' );


// ════════════════════════════════════════════════════════════════════════
// Extratos & SAF-T (Work → Área de Cliente → Work)
// ════════════════════════════════════════════════════════════════════════
// Mesmo mecanismo de Pendentes acima (a Work envia a checklist do que
// espera, substituindo sempre tudo; o cliente responde no site; a resposta
// fica em '_cp_pendentes_respostas', partilhada com documentos/movimentos,
// até a Work a ir buscar e confirmar). A Work pode já vir com um item
// marcado 'estado' => 'sem_dados' quando a equipa já sabe, do lado dela,
// que não há nada a enviar nesse mês — poupa o cliente de ter de marcar.
// Extratos tem uma dimensão a mais: conta_id/conta_nome (um extrato por
// mês, por conta). SAF-T é só por mês.

function cp_ac_extratos( $empresa_id ) {
    $itens = get_post_meta( $empresa_id, '_cp_extratos', true );
    return is_array( $itens ) ? $itens : [];
}

function cp_ac_saft( $empresa_id ) {
    $itens = get_post_meta( $empresa_id, '_cp_saft', true );
    return is_array( $itens ) ? $itens : [];
}

// Contas bancárias da empresa, derivadas dos próprios itens de Extratos —
// não há um registo à parte para manter sincronizado; cada item que a Work
// envia já traz conta_id/conta_nome.
function cp_ac_contas_bancarias( $empresa_id ) {
    $contas = [];
    foreach ( cp_ac_extratos( $empresa_id ) as $item ) {
        if ( empty( $item['conta_id'] ) ) continue;
        $contas[ $item['conta_id'] ] = isset( $item['conta_nome'] ) ? $item['conta_nome'] : $item['conta_id'];
    }
    return $contas;
}

function cp_ac_rest_routes_extratos_saft() {
    register_rest_route( 'cp/v1', '/extratos', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_extratos',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id'  => [ 'required' => true, 'type' => 'string' ],
                'extratos' => [ 'required' => false, 'type' => 'array' ],
            ],
        ],
    ] );
    register_rest_route( 'cp/v1', '/saft', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_saft',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id' => [ 'required' => true, 'type' => 'string' ],
                'saft'    => [ 'required' => false, 'type' => 'array' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_extratos_saft' );

// Valida e normaliza um item de checklist (Extratos ou SAF-T): id/mes
// obrigatórios, mes no formato AAAA-MM, estado 'pendente' por omissão,
// 'sem_dados' quando a Work já o marcou assim ou 'recebido' quando a Work já
// o tem (desde 2026-11-23: o cliente vê também o que já entregou, a partir
// de 2026 — a Work decide o que manda). $campos_extra dá os campos
// específicos de cada tipo (ex.: conta_id/conta_nome, só em Extratos).
function cp_ac_normalizar_item_checklist( $item, array $campos_extra = [] ) {
    if ( empty( $item['id'] ) || empty( $item['mes'] ) || ! preg_match( '/^\d{4}-\d{2}$/', $item['mes'] ) ) {
        return null;
    }
    $estado      = isset( $item['estado'] ) ? $item['estado'] : '';
    $normalizado = [
        'id'     => sanitize_text_field( $item['id'] ),
        'mes'    => sanitize_text_field( $item['mes'] ),
        'estado' => in_array( $estado, [ 'sem_dados', 'recebido' ], true ) ? $estado : 'pendente',
    ];
    foreach ( $campos_extra as $campo ) {
        $normalizado[ $campo ] = isset( $item[ $campo ] ) ? sanitize_text_field( $item[ $campo ] ) : '';
    }
    return $normalizado;
}

// Tabs por ano de Extratos/SAF-T: os anos que têm itens mais o ano
// corrente, que existe sempre e é a tab por omissão; mais recente primeiro.
// $pedido é o ?ano= do URL — se não for um destes anos, abre o corrente.
// 'por_tratar' conta, por ano, os itens à espera do cliente ($por_tratar
// diz se um item conta), para a tab mostrar que há lá coisas em falta.
function cp_ac_calc_tabs_anos( array $itens, $ano_corrente, $pedido = null, $por_tratar = null ) {
    $ano_corrente = (int) $ano_corrente;
    $anos         = [ $ano_corrente => 0 ];
    foreach ( $itens as $item ) {
        if ( empty( $item['mes'] ) || ! preg_match( '/^(\d{4})-\d{2}$/', $item['mes'], $m ) ) continue;
        $ano = (int) $m[1];
        if ( ! isset( $anos[ $ano ] ) ) $anos[ $ano ] = 0;
        if ( $por_tratar && call_user_func( $por_tratar, $item ) ) $anos[ $ano ]++;
    }
    krsort( $anos );
    $pedido = (int) $pedido;
    return [
        'anos'       => array_keys( $anos ),
        'ativo'      => isset( $anos[ $pedido ] ) ? $pedido : $ano_corrente,
        'por_tratar' => $anos,
    ];
}

function cp_ac_rest_sincronizar_extratos( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    // Sem push aqui: o aviso ao cliente é o lembrete do dia 05, que a Work
    // pede por POST /lembrete (ver cp_ac_rest_lembrete).
    $extratos = [];
    foreach ( (array) $request->get_param( 'extratos' ) as $item ) {
        $normalizado = cp_ac_normalizar_item_checklist( $item, [ 'conta_id', 'conta_nome' ] );
        if ( $normalizado ) $extratos[] = $normalizado;
    }
    update_post_meta( $empresa_id, '_cp_extratos', $extratos );

    return rest_ensure_response( [ 'ok' => true, 'total' => count( $extratos ) ] );
}

function cp_ac_rest_sincronizar_saft( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    // Sem push aqui: o aviso ao cliente é o lembrete do dia 02 (POST /lembrete).
    $saft = [];
    foreach ( (array) $request->get_param( 'saft' ) as $item ) {
        $normalizado = cp_ac_normalizar_item_checklist( $item );
        if ( $normalizado ) $saft[] = $normalizado;
    }
    update_post_meta( $empresa_id, '_cp_saft', $saft );

    return rest_ensure_response( [ 'ok' => true, 'total' => count( $saft ) ] );
}

// ─── Lembretes push pedidos pela Work ──────────────────────────────────────
// A Work decide quando (dia 02 SAF-T, dia 05 extratos), a quem (só o que
// ainda falta, conforme a configuração na ficha do cliente) e o texto. O
// site só confirma que a ferramenta está ligada e entrega o push aos
// utilizadores da empresa que a veem. Ferramenta desligada não é erro
// (enviado = false), para a Work não insistir.

function cp_ac_rest_routes_lembrete() {
    register_rest_route( 'cp/v1', '/lembrete', [
        'methods'             => 'POST',
        'callback'            => 'cp_ac_rest_lembrete',
        'permission_callback' => 'cp_ac_rest_permissao',
        'args'                => [
            'work_id'    => [ 'required' => true, 'type' => 'string' ],
            'ferramenta' => [ 'required' => true, 'type' => 'string', 'enum' => [ 'saft', 'extratos' ] ],
            'titulo'     => [ 'required' => true, 'type' => 'string' ],
            'corpo'      => [ 'required' => true, 'type' => 'string' ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_lembrete' );

function cp_ac_rest_lembrete( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();
    $ferramenta = (string) $request->get_param( 'ferramenta' );
    $titulo     = sanitize_text_field( (string) $request->get_param( 'titulo' ) );
    $corpo      = sanitize_text_field( (string) $request->get_param( 'corpo' ) );
    if ( '' === $titulo || '' === $corpo ) {
        return new WP_Error( 'cp_lembrete_vazio', __( 'Falta o título ou o texto do lembrete.', 'contabilidade-pro' ), [ 'status' => 400 ] );
    }
    if ( ! cp_ac_empresa_tem_ferramenta( $empresa_id, $ferramenta ) ) {
        return rest_ensure_response( [ 'ok' => true, 'enviado' => false, 'motivo' => 'ferramenta_desligada' ] );
    }
    cp_push_notificar_empresa(
        $empresa_id,
        $titulo,
        $corpo,
        add_query_arg( 'vista', $ferramenta, home_url( '/area-de-cliente/' ) ),
        $ferramenta
    );
    return rest_ensure_response( [ 'ok' => true, 'enviado' => true ] );
}


// ════════════════════════════════════════════════════════════════════════
// Conta Corrente (Work → Área de Cliente)
// ════════════════════════════════════════════════════════════════════════
// Resumo (saldo, faturado, pago) + linhas (faturas/recibos). A Work envia
// tudo de uma vez via POST /conta-corrente (substitui sempre tudo, mas
// preserva o documento já anexado a uma linha que continue a existir). O
// PDF de cada documento é enviado à parte, por multipart, via POST
// /conta-corrente/documento, identificando a linha pelo seu "id".

function cp_ac_conta_corrente( $empresa_id ) {
    $dados = get_post_meta( $empresa_id, '_cp_conta_corrente', true );
    if ( ! is_array( $dados ) ) $dados = [];
    $dados += [ 'resumo' => [], 'linhas' => [] ];
    return $dados;
}

// Linhas recebidas da Work → linhas guardadas. Aceita o formato antigo
// (id, documento, valor, estado) e o que a Work envia de facto desde sempre
// (numero_documento, tipo fatura/recibo/resumo, debito, credito, pendente,
// por_pagar, ext_poc_cab_id, ano) — até 2026-11-19 estas linhas eram todas
// descartadas por não trazerem "id". O id passa a ser o ext_poc_cab_id
// (com #2, #3… quando o mesmo documento dá mais do que uma linha).
// Função pura: testada em tests/Unit/ContaCorrenteTest.php.
function cp_ac_cc_normalizar_linhas( $linhas_in ) {
    $linhas = [];
    $vistos = [];
    if ( ! is_array( $linhas_in ) ) return $linhas;
    foreach ( $linhas_in as $linha ) {
        if ( ! is_array( $linha ) ) continue;
        $ext = isset( $linha['ext_poc_cab_id'] ) ? sanitize_text_field( (string) $linha['ext_poc_cab_id'] ) : '';
        if ( ! empty( $linha['id'] ) ) {
            $id = sanitize_text_field( (string) $linha['id'] );
        } elseif ( '' !== $ext ) {
            $id = $ext;
        } elseif ( isset( $linha['tipo'] ) && 'resumo' === $linha['tipo'] ) {
            $id = 'resumo-' . ( isset( $linha['ano'] ) ? sanitize_text_field( (string) $linha['ano'] ) : '' );
        } else {
            continue;
        }
        $base = $id;
        $n    = isset( $vistos[ $base ] ) ? $vistos[ $base ] + 1 : 1;
        $vistos[ $base ] = $n;
        if ( $n > 1 ) $id = $base . '#' . $n;

        $tipo_in = isset( $linha['tipo'] ) ? sanitize_text_field( (string) $linha['tipo'] ) : '';
        $debito  = isset( $linha['debito'] ) ? (float) $linha['debito'] : 0.0;
        $credito = isset( $linha['credito'] ) ? (float) $linha['credito'] : 0.0;
        if ( isset( $linha['valor'] ) ) {
            $valor = (float) $linha['valor'];
        } elseif ( 'resumo' === $tipo_in ) {
            $valor = isset( $linha['saldo'] ) ? (float) $linha['saldo'] : 0.0;
        } else {
            $valor = $debito > 0 ? $debito : $credito;
        }
        $por_pagar = isset( $linha['por_pagar'] ) ? round( (float) $linha['por_pagar'], 2 ) : 0.0;
        if ( isset( $linha['estado'] ) ) {
            $estado = sanitize_text_field( (string) $linha['estado'] );
        } elseif ( 'resumo' === $tipo_in ) {
            $estado = '';
        } elseif ( 'recibo' === $tipo_in ) {
            $estado = __( 'Pago', 'contabilidade-pro' );
        } elseif ( ! empty( $linha['pendente'] ) ) {
            $estado = $debito > 0 ? __( 'Por pagar', 'contabilidade-pro' ) : __( 'Crédito', 'contabilidade-pro' );
        } else {
            $estado = __( 'Paga', 'contabilidade-pro' );
        }
        $rotulos = [ 'fatura' => __( 'Fatura', 'contabilidade-pro' ), 'recibo' => __( 'Recibo', 'contabilidade-pro' ), 'resumo' => __( 'Saldo', 'contabilidade-pro' ) ];

        $linhas[] = [
            'id'             => $id,
            'data'           => isset( $linha['data'] ) ? sanitize_text_field( (string) $linha['data'] ) : '',
            'documento'      => isset( $linha['documento'] ) ? sanitize_text_field( (string) $linha['documento'] )
                : ( isset( $linha['numero_documento'] ) ? sanitize_text_field( (string) $linha['numero_documento'] ) : '' ),
            'tipo'           => isset( $rotulos[ $tipo_in ] ) ? $rotulos[ $tipo_in ] : $tipo_in,
            'valor'          => $valor,
            'estado'         => $estado,
            'ext_poc_cab_id' => $ext,
            'debito'         => $debito,
            'por_pagar'      => $por_pagar,
            'anexo_id'       => 0,
            'anexo_url'      => '',
        ];
    }
    return $linhas;
}

// Faturas da empresa com valor por receber (para os Recebimentos do
// Backoffice): linhas a débito com por_pagar > 0, mais antigas primeiro.
function cp_ac_cc_faturas_em_aberto( array $linhas ) {
    $abertas = array_values( array_filter( $linhas, function ( $l ) {
        return ! empty( $l['ext_poc_cab_id'] ) && ( isset( $l['debito'] ) ? (float) $l['debito'] : 0 ) > 0
            && ( isset( $l['por_pagar'] ) ? (float) $l['por_pagar'] : 0 ) > 0;
    } ) );
    usort( $abertas, function ( $a, $b ) { return strcmp( (string) $a['data'], (string) $b['data'] ); } );
    return $abertas;
}

function cp_ac_rest_routes_conta_corrente() {
    register_rest_route( 'cp/v1', '/conta-corrente', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_conta_corrente',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id' => [ 'required' => true, 'type' => 'string' ],
                'resumo'  => [ 'required' => false, 'type' => 'object' ],
                'linhas'  => [ 'required' => false, 'type' => 'array' ],
            ],
        ],
    ] );
    register_rest_route( 'cp/v1', '/conta-corrente/documento', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_conta_corrente_documento',
            'permission_callback' => 'cp_ac_rest_permissao',
            // linha_id (formato antigo) ou ext_poc_cab_id (o que a Work envia).
            'args'                => [
                'work_id'        => [ 'required' => true, 'type' => 'string' ],
                'linha_id'       => [ 'required' => false, 'type' => 'string' ],
                'ext_poc_cab_id' => [ 'required' => false, 'type' => 'string' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_conta_corrente' );

function cp_ac_rest_sincronizar_conta_corrente( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $atual         = cp_ac_conta_corrente( $empresa_id );
    $anexos_por_id = [];
    foreach ( $atual['linhas'] as $linha ) {
        if ( ! empty( $linha['anexo_id'] ) ) {
            $anexos_por_id[ $linha['id'] ] = [
                'anexo_id'  => $linha['anexo_id'],
                'anexo_url' => $linha['anexo_url'],
            ];
        } elseif ( ! empty( $linha['ficheiro_removido'] ) ) {
            // Ficheiro apagado pela limpeza da equipa (cp/v1/limpeza): a
            // linha continua a dizer que o PDF foi retirado.
            $anexos_por_id[ $linha['id'] ] = [ 'anexo_id' => '', 'anexo_url' => '', 'ficheiro_removido' => $linha['ficheiro_removido'] ];
        }
    }

    $resumo_in = $request->get_param( 'resumo' );
    $resumo    = [];
    if ( is_array( $resumo_in ) ) {
        foreach ( [ 'saldo_atual', 'saldo_nota', 'faturado_ano', 'faturado_nota', 'pago_ano', 'pago_nota' ] as $campo ) {
            if ( isset( $resumo_in[ $campo ] ) ) {
                $resumo[ $campo ] = in_array( $campo, [ 'saldo_atual', 'faturado_ano', 'pago_ano' ], true )
                    ? (float) $resumo_in[ $campo ]
                    : sanitize_text_field( $resumo_in[ $campo ] );
            }
        }
    }

    $linhas = [];
    foreach ( cp_ac_cc_normalizar_linhas( $request->get_param( 'linhas' ) ) as $nova ) {
        $id = $nova['id'];
        if ( isset( $anexos_por_id[ $id ] ) ) {
            $nova['anexo_id']  = $anexos_por_id[ $id ]['anexo_id'];
            $nova['anexo_url'] = $anexos_por_id[ $id ]['anexo_url'];
            if ( ! empty( $anexos_por_id[ $id ]['ficheiro_removido'] ) ) {
                $nova['ficheiro_removido'] = $anexos_por_id[ $id ]['ficheiro_removido'];
            }
        }
        $linhas[] = $nova;
    }

    update_post_meta( $empresa_id, '_cp_conta_corrente', [ 'resumo' => $resumo, 'linhas' => $linhas ] );
    // Quando chegou (mostrado no Backoffice › Contas correntes).
    update_post_meta( $empresa_id, '_cp_conta_corrente_em', current_time( 'mysql' ) );

    return rest_ensure_response( [ 'ok' => true, 'total_linhas' => count( $linhas ) ] );
}

function cp_ac_rest_conta_corrente_documento( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $linha_id = sanitize_text_field( (string) $request->get_param( 'linha_id' ) );
    $ext      = sanitize_text_field( (string) $request->get_param( 'ext_poc_cab_id' ) );
    if ( '' === $linha_id && '' === $ext ) {
        return new WP_Error( 'cp_linha_em_falta', __( 'Indique linha_id ou ext_poc_cab_id.', 'contabilidade-pro' ), [ 'status' => 400 ] );
    }

    $dados    = cp_ac_conta_corrente( $empresa_id );
    $ficheiro = cp_ac_rest_guardar_ficheiro( $request, 'ficheiro' );
    if ( is_wp_error( $ficheiro ) ) return $ficheiro;

    // Por ext_poc_cab_id o PDF vai para todas as linhas desse documento (um
    // recibo pode dar duas linhas: o valor e uma sobra).
    $encontrada = false;
    foreach ( $dados['linhas'] as &$linha ) {
        $bate = '' !== $linha_id ? $linha['id'] === $linha_id : ( isset( $linha['ext_poc_cab_id'] ) && $linha['ext_poc_cab_id'] === $ext );
        if ( $bate ) {
            $linha['anexo_id']  = $ficheiro['id'];
            $linha['anexo_url'] = $ficheiro['url'];
            $encontrada = true;
            if ( '' !== $linha_id ) break;
        }
    }
    unset( $linha );

    if ( ! $encontrada ) {
        return new WP_Error(
            'cp_linha_nao_encontrada',
            __( 'Não existe nenhuma linha com este linha_id. Sincroniza primeiro via POST /conta-corrente.', 'contabilidade-pro' ),
            [ 'status' => 404 ]
        );
    }

    update_post_meta( $empresa_id, '_cp_conta_corrente', $dados );

    return rest_ensure_response( [ 'ok' => true, 'anexo_id' => $ficheiro['id'], 'anexo_url' => $ficheiro['url'] ] );
}


// ════════════════════════════════════════════════════════════════════════
// Mapas de Gestão (Work → Área de Cliente)
// ════════════════════════════════════════════════════════════════════════
// Um mapa por mês (chave "YYYY-MM"), sempre com um PDF. Criar/substituir um
// mês é sempre o mesmo pedido multipart (POST /mapas-gestao); remover um
// mês é POST /mapas-gestao/remover.

function cp_ac_mapas_gestao( $empresa_id ) {
    $mapas = get_post_meta( $empresa_id, '_cp_mapas_gestao', true );
    return is_array( $mapas ) ? $mapas : [];
}

function cp_ac_rest_routes_mapas_gestao() {
    register_rest_route( 'cp/v1', '/mapas-gestao', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_mapa_gestao',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id' => [ 'required' => true, 'type' => 'string' ],
                'mes'     => [ 'required' => true, 'type' => 'string' ],
                'titulo'  => [ 'required' => false, 'type' => 'string' ],
            ],
        ],
    ] );
    register_rest_route( 'cp/v1', '/mapas-gestao/remover', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_remover_mapa_gestao',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id' => [ 'required' => true, 'type' => 'string' ],
                'mes'     => [ 'required' => true, 'type' => 'string' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_mapas_gestao' );

function cp_ac_rest_sincronizar_mapa_gestao( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $mes = sanitize_text_field( $request->get_param( 'mes' ) );
    if ( ! preg_match( '/^\d{4}-\d{2}$/', $mes ) ) {
        return new WP_Error(
            'cp_mes_invalido',
            __( 'mes tem de estar no formato AAAA-MM.', 'contabilidade-pro' ),
            [ 'status' => 400 ]
        );
    }

    $ficheiro = cp_ac_rest_guardar_ficheiro( $request, 'ficheiro' );
    if ( is_wp_error( $ficheiro ) ) return $ficheiro;

    $titulo_param = $request->get_param( 'titulo' );
    $titulo       = $titulo_param ? sanitize_text_field( $titulo_param ) : sprintf(
        /* translators: %s: mês no formato AAAA-MM */
        __( 'Mapa de Gestão — %s', 'contabilidade-pro' ),
        $mes
    );

    $mapas = cp_ac_mapas_gestao( $empresa_id );
    $anterior = null;
    $mapas = array_values( array_filter( $mapas, function ( $m ) use ( $mes, &$anterior ) {
        if ( $m['mes'] === $mes ) { $anterior = $m; return false; }
        return true;
    } ) );
    if ( $anterior && ! empty( $anterior['anexo_id'] ) ) {
        wp_delete_attachment( $anterior['anexo_id'], true );
    }

    $mapas[] = [
        'mes'          => $mes,
        'titulo'       => $titulo,
        'publicado_em' => current_time( 'mysql' ),
        'anexo_id'     => $ficheiro['id'],
        'anexo_url'    => $ficheiro['url'],
    ];
    // Mais recente primeiro.
    usort( $mapas, function ( $a, $b ) { return strcmp( $b['mes'], $a['mes'] ); } );

    update_post_meta( $empresa_id, '_cp_mapas_gestao', $mapas );

    if ( cp_ac_empresa_tem_ferramenta( $empresa_id, 'mapas_gestao' ) ) {
        cp_push_notificar_empresa(
            $empresa_id,
            __( 'Novo mapa de gestão', 'contabilidade-pro' ),
            $titulo,
            add_query_arg( 'vista', 'mapas_gestao', home_url( '/area-de-cliente/' ) ),
            'mapas_gestao'
        );
    }

    return rest_ensure_response( [ 'ok' => true, 'mes' => $mes, 'anexo_url' => $ficheiro['url'] ] );
}

function cp_ac_rest_remover_mapa_gestao( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $mes      = sanitize_text_field( $request->get_param( 'mes' ) );
    $mapas    = cp_ac_mapas_gestao( $empresa_id );
    $removido = null;
    $restantes = array_values( array_filter( $mapas, function ( $m ) use ( $mes, &$removido ) {
        if ( $m['mes'] === $mes ) { $removido = $m; return false; }
        return true;
    } ) );

    if ( ! $removido ) {
        return new WP_Error(
            'cp_mapa_nao_encontrado',
            __( 'Não existe nenhum mapa para este mês.', 'contabilidade-pro' ),
            [ 'status' => 404 ]
        );
    }

    if ( ! empty( $removido['anexo_id'] ) ) {
        wp_delete_attachment( $removido['anexo_id'], true );
    }
    update_post_meta( $empresa_id, '_cp_mapas_gestao', $restantes );

    return rest_ensure_response( [ 'ok' => true ] );
}


// ════════════════════════════════════════════════════════════════════════
// Recibos de Vencimento (Work → Área de Cliente)
// ════════════════════════════════════════════════════════════════════════
// Um recibo por colaborador/mês, chave (work_id, cod_emp, ano, mes), sempre
// com um PDF. Upsert: se já existir um recibo com esta chave, substitui o
// ficheiro e os campos — nunca duplica. O "n_processamento" é controlo
// interno da Work e não é enviado para aqui.

function cp_ac_recibos_vencimento( $empresa_id ) {
    $recibos = get_post_meta( $empresa_id, '_cp_recibos_vencimento', true );
    return is_array( $recibos ) ? $recibos : [];
}

function cp_ac_rest_routes_recibos_vencimento() {
    register_rest_route( 'cp/v1', '/recibos-vencimento', [
        [
            'methods'             => 'POST',
            'callback'            => 'cp_ac_rest_sincronizar_recibo_vencimento',
            'permission_callback' => 'cp_ac_rest_permissao',
            'args'                => [
                'work_id'            => [ 'required' => true, 'type' => 'string' ],
                'cod_emp'            => [ 'required' => true, 'type' => 'string' ],
                'nome_emp'           => [ 'required' => true, 'type' => 'string' ],
                'ano'                => [ 'required' => true, 'type' => 'integer' ],
                'mes'                => [ 'required' => true, 'type' => 'integer' ],
                'tipo_processamento' => [ 'required' => false, 'type' => 'string' ],
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'cp_ac_rest_routes_recibos_vencimento' );

function cp_ac_rest_sincronizar_recibo_vencimento( $request ) {
    $empresa_id = cp_ac_empresa_by_work_id( $request->get_param( 'work_id' ) );
    if ( ! $empresa_id ) return cp_ac_rest_erro_empresa_nao_encontrada();

    $cod_emp  = sanitize_text_field( $request->get_param( 'cod_emp' ) );
    $nome_emp = sanitize_text_field( $request->get_param( 'nome_emp' ) );
    $ano      = (int) $request->get_param( 'ano' );
    $mes      = (int) $request->get_param( 'mes' );

    if ( '' === $cod_emp || $mes < 1 || $mes > 12 ) {
        return new WP_Error(
            'cp_dados_invalidos',
            __( 'cod_emp é obrigatório e mes tem de estar entre 1 e 12.', 'contabilidade-pro' ),
            [ 'status' => 400 ]
        );
    }

    $tipo_param         = $request->get_param( 'tipo_processamento' );
    $tipo_processamento = $tipo_param ? sanitize_text_field( $tipo_param ) : '';

    $ficheiro = cp_ac_rest_guardar_ficheiro( $request, 'ficheiro' );
    if ( is_wp_error( $ficheiro ) ) return $ficheiro;

    $recibos  = cp_ac_recibos_vencimento( $empresa_id );
    $anterior = null;
    $recibos  = array_values( array_filter( $recibos, function ( $r ) use ( $cod_emp, $ano, $mes, &$anterior ) {
        if ( $r['cod_emp'] === $cod_emp && (int) $r['ano'] === $ano && (int) $r['mes'] === $mes ) {
            $anterior = $r;
            return false;
        }
        return true;
    } ) );
    if ( $anterior && ! empty( $anterior['anexo_id'] ) ) {
        wp_delete_attachment( $anterior['anexo_id'], true );
    }

    $recibos[] = [
        'cod_emp'            => $cod_emp,
        'nome_emp'           => $nome_emp,
        'ano'                => $ano,
        'mes'                => $mes,
        'tipo_processamento' => $tipo_processamento,
        'anexo_id'           => $ficheiro['id'],
        'anexo_url'          => $ficheiro['url'],
        'atualizado_em'      => current_time( 'mysql' ),
    ];

    update_post_meta( $empresa_id, '_cp_recibos_vencimento', $recibos );

    return rest_ensure_response( [ 'id' => $ficheiro['id'], 'criado' => ! $anterior ] );
}
