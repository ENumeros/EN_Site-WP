<?php
/**
 * Mensagens — várias conversas por empresa, cada uma com o seu assunto,
 * entre o cliente e a equipa. Guardado como postmeta no post da empresa,
 * mesmo princípio de "caixa de correio" usado em Lembretes/Pendentes —
 * mas esta é a primeira ferramenta que atualiza sem recarregar a página:
 * leitura e escrita vão por REST (ver ─── REST ───), não por admin-post.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Dados: conversas ───────────────────────────────────────────────────────

function cp_ac_conversas_raw( $empresa_id ) {
	$conversas = get_post_meta( $empresa_id, '_cp_conversas', true );
	return is_array( $conversas ) ? $conversas : [];
}

// Mensagens já existentes antes de haver conversas (lançadas direto no
// fio único, sem 'conversa_id') ficam todas juntas numa conversa "Geral"
// criada aqui — lazy, sem script de migração à parte, e idempotente: na
// próxima chamada já não há nenhuma por migrar.
function cp_ac_conversas_migrar_legado( $empresa_id ) {
	$mensagens = cp_ac_mensagens( $empresa_id );
	$sem_conversa = array_values( array_filter( $mensagens, function ( $mensagem ) {
		return empty( $mensagem['conversa_id'] );
	} ) );
	if ( empty( $sem_conversa ) ) return;

	usort( $sem_conversa, function ( $a, $b ) {
		return strcmp( $a['enviado_em'], $b['enviado_em'] );
	} );

	$conversas   = cp_ac_conversas_raw( $empresa_id );
	$conversa_id = wp_generate_uuid4();
	$conversas[] = [
		'id'                 => $conversa_id,
		'assunto'            => __( 'Geral', 'contabilidade-pro' ),
		'criado_por_tipo'    => $sem_conversa[0]['autor_tipo'],
		'criado_por_user_id' => $sem_conversa[0]['autor_user_id'],
		'criado_em'          => $sem_conversa[0]['enviado_em'],
	];
	update_post_meta( $empresa_id, '_cp_conversas', $conversas );

	$mensagens = array_map( function ( $mensagem ) use ( $conversa_id ) {
		if ( empty( $mensagem['conversa_id'] ) ) {
			$mensagem['conversa_id'] = $conversa_id;
		}
		return $mensagem;
	}, $mensagens );
	update_post_meta( $empresa_id, '_cp_mensagens', $mensagens );
}

// Lista de conversas, mais recente primeiro, cada uma com a última
// mensagem e quantas ainda não foram lidas por $user_id (omitido =
// utilizador atual). A "última mensagem" nunca é guardada à parte — é
// sempre calculada a partir de _cp_mensagens, para nunca desalinhar.
function cp_ac_conversas( $empresa_id, $user_id = null ) {
	cp_ac_conversas_migrar_legado( $empresa_id );
	$user_id   = $user_id ?: get_current_user_id();
	$mensagens = cp_ac_mensagens( $empresa_id );

	$conversas = array_map( function ( $conversa ) use ( $mensagens, $user_id ) {
		$da_conversa = array_values( array_filter( $mensagens, function ( $mensagem ) use ( $conversa ) {
			return $mensagem['conversa_id'] === $conversa['id'];
		} ) );
		$ultima = ! empty( $da_conversa ) ? end( $da_conversa ) : null;

		$conversa['ultima_mensagem']    = $ultima ? $ultima['texto'] : '';
		$conversa['ultima_mensagem_em'] = $ultima ? $ultima['enviado_em'] : $conversa['criado_em'];
		$conversa['por_ler']            = cp_ac_mensagens_por_ler( $conversa['id'], $da_conversa, $user_id );
		return $conversa;
	}, cp_ac_conversas_raw( $empresa_id ) );

	usort( $conversas, function ( $a, $b ) {
		return strcmp( $b['ultima_mensagem_em'], $a['ultima_mensagem_em'] );
	} );

	return $conversas;
}

function cp_ac_conversa_adicionar( $empresa_id, $assunto, $user_id, $equipa ) {
	$conversas = cp_ac_conversas_raw( $empresa_id );
	$conversa  = [
		'id'                 => wp_generate_uuid4(),
		'assunto'            => $assunto,
		'criado_por_tipo'    => $equipa ? 'equipa' : 'cliente',
		'criado_por_user_id' => (int) $user_id,
		'criado_em'          => current_time( 'mysql' ),
	];
	$conversas[] = $conversa;
	update_post_meta( $empresa_id, '_cp_conversas', $conversas );
	return $conversa;
}

// Nunca confiar que um conversa_id vindo de um pedido é mesmo da
// empresa já resolvida (cp_ac_mensagem_resolver_empresa()) — confirma
// sempre antes de listar ou escrever mensagens.
function cp_ac_conversa_pertence( $conversa_id, $empresa_id ) {
	foreach ( cp_ac_conversas_raw( $empresa_id ) as $conversa ) {
		if ( $conversa['id'] === $conversa_id ) return true;
	}
	return false;
}

// ─── Dados: mensagens ───────────────────────────────────────────────────────

function cp_ac_mensagens( $empresa_id ) {
	$mensagens = get_post_meta( $empresa_id, '_cp_mensagens', true );
	return is_array( $mensagens ) ? $mensagens : [];
}

function cp_ac_mensagens_conversa( $empresa_id, $conversa_id ) {
	return array_values( array_filter( cp_ac_mensagens( $empresa_id ), function ( $mensagem ) use ( $conversa_id ) {
		return $mensagem['conversa_id'] === $conversa_id;
	} ) );
}

function cp_ac_mensagem_adicionar( $empresa_id, $conversa_id, $texto, $user_id, $equipa ) {
	$utilizador = get_userdata( $user_id );
	$mensagens  = cp_ac_mensagens( $empresa_id );
	$mensagem   = [
		'id'            => wp_generate_uuid4(),
		'conversa_id'   => $conversa_id,
		'texto'         => $texto,
		'autor_tipo'    => $equipa ? 'equipa' : 'cliente',
		'autor_user_id' => (int) $user_id,
		'autor_nome'    => $utilizador ? $utilizador->display_name : '',
		'enviado_em'    => current_time( 'mysql' ),
	];
	$mensagens[] = $mensagem;
	update_post_meta( $empresa_id, '_cp_mensagens', $mensagens );
	return $mensagem;
}

// A que empresa um pedido se refere: a equipa escolhe explicitamente
// (vários clientes); nunca confiar num empresa_id vindo de um cliente —
// é sempre a empresa ativa da sua própria sessão. Mesma lógica de
// cp_ac_tarefa_resolver_empresa() em inc/area-cliente-tarefas.php.
function cp_ac_mensagem_resolver_empresa( $empresa_id_pedido, $user_id ) {
	if ( cp_backoffice_utilizador_tem_acesso( $user_id ) ) {
		return (int) $empresa_id_pedido;
	}
	return cp_ac_empresa_ativa( $user_id );
}

// ─── Lidas / por ler (por conversa, não por empresa) ───────────────────────
// Usermeta 'cp_mensagens_lidas' = [ conversa_id => data/hora até à qual
// já viu ]. Um utilizador pode ter acesso a várias empresas (equipa, ou
// um cliente com mais do que uma) — por isso por conversa, nunca um
// único marcador global.

function cp_ac_mensagens_marcador_lido( $user_id, $conversa_id ) {
	$marcadores = get_user_meta( $user_id, 'cp_mensagens_lidas', true );
	$marcadores = is_array( $marcadores ) ? $marcadores : [];
	return isset( $marcadores[ $conversa_id ] ) ? $marcadores[ $conversa_id ] : '';
}

function cp_ac_mensagens_marcar_lida( $user_id, $conversa_id ) {
	$marcadores = get_user_meta( $user_id, 'cp_mensagens_lidas', true );
	$marcadores = is_array( $marcadores ) ? $marcadores : [];
	$marcadores[ $conversa_id ] = current_time( 'mysql' );
	update_user_meta( $user_id, 'cp_mensagens_lidas', $marcadores );
}

// Mensagens da outra parte (nunca as que o próprio utilizador escreveu)
// enviadas depois do marcador de leitura dessa conversa. Recebe as
// mensagens já filtradas pela conversa (quem chama já as tem, em
// cp_ac_conversas()) para não repetir a leitura do postmeta.
function cp_ac_mensagens_por_ler( $conversa_id, $mensagens_da_conversa, $user_id ) {
	$marcador = cp_ac_mensagens_marcador_lido( $user_id, $conversa_id );
	$por_ler  = 0;
	foreach ( $mensagens_da_conversa as $mensagem ) {
		if ( (int) $mensagem['autor_user_id'] === (int) $user_id ) continue;
		if ( '' === $marcador || $mensagem['enviado_em'] > $marcador ) $por_ler++;
	}
	return $por_ler;
}

// ─── REST ───────────────────────────────────────────────────────────────────
// A validação de que a empresa/conversa pertence mesmo ao utilizador
// fica dentro de cada callback — o permission_callback só confirma
// sessão iniciada, mesmo padrão de cp_push_rest_permissao() em
// inc/area-cliente-push.php.

function cp_ac_mensagens_rest_routes() {
	register_rest_route( 'cp/v1', '/conversas', [
		[
			'methods'             => 'GET',
			'callback'            => 'cp_ac_rest_conversas_listar',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_conversas_criar',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
	] );
	register_rest_route( 'cp/v1', '/mensagens', [
		[
			'methods'             => 'GET',
			'callback'            => 'cp_ac_rest_mensagens_listar',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_mensagens_criar',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
	] );
	register_rest_route( 'cp/v1', '/mensagens/marcar-lida', [
		[
			'methods'             => 'POST',
			'callback'            => 'cp_ac_rest_mensagens_marcar_lida',
			'permission_callback' => 'cp_ac_mensagens_rest_permissao',
		],
	] );
}
add_action( 'rest_api_init', 'cp_ac_mensagens_rest_routes' );

function cp_ac_mensagens_rest_permissao() {
	return is_user_logged_in();
}

// Resolve e valida a empresa de um pedido; devolve o ID ou um WP_Error.
function cp_ac_mensagens_rest_resolver_empresa( $request, $user_id ) {
	$empresa_id = cp_ac_mensagem_resolver_empresa( (int) $request->get_param( 'empresa_id' ), $user_id );
	if ( ! $empresa_id || 'cp_empresa' !== get_post_type( $empresa_id ) ) {
		return new WP_Error( 'cp_empresa_invalida', __( 'Empresa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	return $empresa_id;
}

function cp_ac_rest_conversas_listar( $request ) {
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$conversas = array_map( function ( $conversa ) {
		$conversa['ultima_mensagem_ha'] = human_time_diff( strtotime( $conversa['ultima_mensagem_em'] ), current_time( 'timestamp' ) );
		return $conversa;
	}, cp_ac_conversas( $empresa_id, $user_id ) );

	$por_ler_total = array_sum( wp_list_pluck( $conversas, 'por_ler' ) );

	return rest_ensure_response( [
		'conversas'     => $conversas,
		'por_ler_total' => $por_ler_total,
	] );
}

function cp_ac_rest_conversas_criar( $request ) {
	$user_id    = get_current_user_id();
	$equipa     = cp_backoffice_utilizador_tem_acesso( $user_id );
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$assunto = sanitize_text_field( (string) $request->get_param( 'assunto' ) );
	$texto   = sanitize_textarea_field( (string) $request->get_param( 'texto' ) );
	if ( '' === trim( $assunto ) ) {
		return new WP_Error( 'cp_assunto_vazio', __( 'Escreva um assunto.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	if ( '' === trim( $texto ) ) {
		return new WP_Error( 'cp_mensagem_vazia', __( 'Escreva uma mensagem.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}

	$conversa = cp_ac_conversa_adicionar( $empresa_id, $assunto, $user_id, $equipa );
	$mensagem = cp_ac_mensagem_adicionar( $empresa_id, $conversa['id'], $texto, $user_id, $equipa );

	cp_ac_mensagens_notificar( $empresa_id, $assunto, $texto, $equipa );

	return rest_ensure_response( [ 'conversa' => $conversa, 'mensagem' => $mensagem ] );
}

function cp_ac_rest_mensagens_listar( $request ) {
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$conversa_id = sanitize_text_field( (string) $request->get_param( 'conversa_id' ) );
	if ( '' === $conversa_id || ! cp_ac_conversa_pertence( $conversa_id, $empresa_id ) ) {
		return new WP_Error( 'cp_conversa_invalida', __( 'Conversa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}

	// O "há X tempo" é calculado aqui (fuso do site) e não em JS — evita
	// ter de repetir o cálculo de datas em JavaScript e desalinhar com o
	// fuso horário usado no resto do tema (human_time_diff()).
	$mensagens = array_map( function ( $mensagem ) {
		$mensagem['enviado_ha'] = human_time_diff( strtotime( $mensagem['enviado_em'] ), current_time( 'timestamp' ) );
		return $mensagem;
	}, cp_ac_mensagens_conversa( $empresa_id, $conversa_id ) );

	return rest_ensure_response( [ 'mensagens' => $mensagens ] );
}

function cp_ac_rest_mensagens_marcar_lida( $request ) {
	$user_id    = get_current_user_id();
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$conversa_id = sanitize_text_field( (string) $request->get_param( 'conversa_id' ) );
	if ( '' === $conversa_id || ! cp_ac_conversa_pertence( $conversa_id, $empresa_id ) ) {
		return new WP_Error( 'cp_conversa_invalida', __( 'Conversa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	cp_ac_mensagens_marcar_lida( $user_id, $conversa_id );
	return rest_ensure_response( [ 'ok' => true ] );
}

function cp_ac_rest_mensagens_criar( $request ) {
	$user_id    = get_current_user_id();
	$equipa     = cp_backoffice_utilizador_tem_acesso( $user_id );
	$empresa_id = cp_ac_mensagens_rest_resolver_empresa( $request, $user_id );
	if ( is_wp_error( $empresa_id ) ) return $empresa_id;

	$conversa_id = sanitize_text_field( (string) $request->get_param( 'conversa_id' ) );
	if ( '' === $conversa_id || ! cp_ac_conversa_pertence( $conversa_id, $empresa_id ) ) {
		return new WP_Error( 'cp_conversa_invalida', __( 'Conversa inválida.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}
	$texto = sanitize_textarea_field( (string) $request->get_param( 'texto' ) );
	if ( '' === trim( $texto ) ) {
		return new WP_Error( 'cp_mensagem_vazia', __( 'Escreva uma mensagem.', 'contabilidade-pro' ), [ 'status' => 400 ] );
	}

	$mensagem = cp_ac_mensagem_adicionar( $empresa_id, $conversa_id, $texto, $user_id, $equipa );

	$assunto = '';
	foreach ( cp_ac_conversas_raw( $empresa_id ) as $conversa ) {
		if ( $conversa['id'] === $conversa_id ) { $assunto = $conversa['assunto']; break; }
	}
	cp_ac_mensagens_notificar( $empresa_id, $assunto, $texto, $equipa );

	return rest_ensure_response( [ 'mensagem' => $mensagem ] );
}

function cp_ac_mensagens_notificar( $empresa_id, $assunto, $texto, $equipa ) {
	if ( $equipa ) {
		cp_push_notificar_empresa(
			$empresa_id,
			sprintf( /* translators: %s: assunto da conversa */ __( 'Nova mensagem — %s', 'contabilidade-pro' ), $assunto ),
			$texto,
			home_url( '/area-de-cliente/' )
		);
	} else {
		cp_push_notificar_equipa(
			sprintf( /* translators: %s: assunto da conversa */ __( 'Nova mensagem de cliente — %s', 'contabilidade-pro' ), $assunto ),
			sprintf(
				/* translators: 1: nome da empresa, 2: texto da mensagem */
				__( '%1$s: %2$s', 'contabilidade-pro' ),
				get_the_title( $empresa_id ),
				$texto
			),
			add_query_arg( 'empresa_id', $empresa_id, home_url( '/backoffice/' ) ),
			$empresa_id
		);
	}
}

// ─── UI partilhada (Área de Cliente + Backoffice) ──────────────────────────

// Caixa de escrever + enviar — usada em qualquer sítio onde se escreve
// numa conversa já aberta (Backoffice, modal da conversa). Precisa de
// $conversa_id; para "Nova conversa" usa-se cp_ac_render_conversa_form().
function cp_ac_render_mensagem_form( $empresa_id, $conversa_id, $form_id ) {
	?>
	<form id="<?php echo esc_attr( $form_id ); ?>" data-cp-mensagens-form class="ac-mensagem-form">
		<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
		<input type="hidden" name="conversa_id" value="<?php echo esc_attr( $conversa_id ); ?>">
		<textarea name="texto" required rows="1" class="ac-mensagem-campo" placeholder="<?php esc_attr_e( 'Escreva uma mensagem…', 'contabilidade-pro' ); ?>"></textarea>
		<button class="ac-btn ac-btn-ghost" type="submit"><?php esc_html_e( 'Enviar', 'contabilidade-pro' ); ?></button>
	</form>
	<?php
}

// Formulário de "Nova conversa" (assunto + primeira mensagem) — usado no
// Backoffice e na modal do atalho rápido do "+".
function cp_ac_render_conversa_form( $empresa_id, $form_id ) {
	?>
	<form id="<?php echo esc_attr( $form_id ); ?>" data-cp-conversa-form style="display:flex;flex-direction:column;gap:10px">
		<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
		<input type="text" name="assunto" required placeholder="<?php esc_attr_e( 'Assunto — ex.: dúvida sobre o IVA', 'contabilidade-pro' ); ?>" style="padding:10px 12px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13.5px">
		<textarea name="texto" required rows="2" placeholder="<?php esc_attr_e( 'Escreva a mensagem…', 'contabilidade-pro' ); ?>" style="padding:10px 12px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13.5px;font-family:inherit;resize:vertical"></textarea>
		<button class="ac-btn ac-btn-primary" type="submit" style="align-self:flex-start"><?php esc_html_e( 'Iniciar conversa', 'contabilidade-pro' ); ?></button>
	</form>
	<?php
}

// Lista de mensagens de uma conversa (sem moldura à volta) — partilhada
// entre o Backoffice e a modal de conversa da Área de Cliente.
function cp_ac_render_mensagens_lista( $mensagens ) {
	?>
	<div id="cp-mensagens-lista" class="ac-mensagens-lista">
		<?php if ( empty( $mensagens ) ) : ?>
			<div class="ac-mensagens-vazio"><?php esc_html_e( 'Ainda sem mensagens nesta conversa.', 'contabilidade-pro' ); ?></div>
		<?php else : ?>
			<?php foreach ( $mensagens as $mensagem ) : ?>
				<div class="ac-mensagem ac-mensagem-<?php echo esc_attr( $mensagem['autor_tipo'] ); ?>" data-id="<?php echo esc_attr( $mensagem['id'] ); ?>">
					<div class="ac-mensagem-meta">
						<?php
						printf(
							/* translators: 1: nome de quem enviou, 2: há quanto tempo, ex. "3 horas" */
							esc_html__( '%1$s — há %2$s', 'contabilidade-pro' ),
							esc_html( $mensagem['autor_nome'] ),
							esc_html( human_time_diff( strtotime( $mensagem['enviado_em'] ), current_time( 'timestamp' ) ) )
						);
						?>
					</div>
					<div class="ac-mensagem-texto"><?php echo esc_html( $mensagem['texto'] ); ?></div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}

// Backoffice: seletor de conversa (populado a partir de $empresa_id) +
// a conversa escolhida (histórico completo + enviar), ou "Nova conversa".
function cp_ac_render_mensagens( $empresa_id, $conversa_id = '' ) {
	$conversas = cp_ac_conversas( $empresa_id );
	?>
	<div class="ac-card" style="padding:22px;margin-bottom:18px">
		<form method="get" action="<?php echo esc_url( get_permalink() ); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
			<input type="hidden" name="empresa_id" value="<?php echo esc_attr( $empresa_id ); ?>">
			<label for="cp-bo-conversa" style="font-size:12.5px;font-weight:600"><?php esc_html_e( 'Conversa', 'contabilidade-pro' ); ?></label>
			<select name="conversa_id" id="cp-bo-conversa" onchange="this.form.submit()" style="padding:9px 12px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13.5px">
				<option value=""><?php esc_html_e( 'Nova conversa…', 'contabilidade-pro' ); ?></option>
				<?php foreach ( $conversas as $conversa ) : ?>
					<option value="<?php echo esc_attr( $conversa['id'] ); ?>" <?php selected( $conversa_id, $conversa['id'] ); ?>><?php echo esc_html( $conversa['assunto'] ); ?><?php echo $conversa['por_ler'] ? ' (' . (int) $conversa['por_ler'] . ')' : ''; ?></option>
				<?php endforeach; ?>
			</select>
		</form>
	</div>

	<?php if ( '' !== $conversa_id && cp_ac_conversa_pertence( $conversa_id, $empresa_id ) ) : ?>
		<div class="ac-card" style="padding:22px">
			<?php cp_ac_render_mensagens_lista( cp_ac_mensagens_conversa( $empresa_id, $conversa_id ) ); ?>
			<div style="margin-top:14px">
				<?php cp_ac_render_mensagem_form( $empresa_id, $conversa_id, 'cp-mensagens-form' ); ?>
			</div>
		</div>
	<?php else : ?>
		<div class="ac-card" style="padding:22px">
			<div class="ac-sec-head">
				<h3 style="font-size:16px"><?php esc_html_e( 'Nova conversa', 'contabilidade-pro' ); ?></h3>
			</div>
			<?php cp_ac_render_conversa_form( $empresa_id, 'cp-conversa-form' ); ?>
		</div>
	<?php endif; ?>
	<?php
}

// Área de Cliente: lista de conversas dentro do dropdown do utilizador
// (sem moldura própria — o dropdown já é o cartão). O conteúdo de cada
// conversa (mensagens) é carregado à parte, por JS, na modal partilhada
// #cp-conversa-modal — não dá para pré-renderizar todas aqui.
function cp_ac_render_conversas_dropdown( $empresa_id ) {
	$conversas = cp_ac_conversas( $empresa_id );
	?>
	<div class="ac-dropdown-mensagens">
		<div class="ac-dropdown-mensagens-head"><?php esc_html_e( 'Mensagens', 'contabilidade-pro' ); ?></div>
		<div id="cp-conversas-lista">
			<?php if ( empty( $conversas ) ) : ?>
				<div class="ac-mensagens-vazio"><?php esc_html_e( 'Sem conversas — comece uma abaixo.', 'contabilidade-pro' ); ?></div>
			<?php else : ?>
				<?php foreach ( $conversas as $conversa ) : ?>
					<button type="button" class="ac-conversa-row" data-conversa-id="<?php echo esc_attr( $conversa['id'] ); ?>">
						<span class="ac-conversa-row-head">
							<span class="ac-conversa-assunto"><?php echo esc_html( $conversa['assunto'] ); ?></span>
							<?php if ( $conversa['por_ler'] ) : ?><span class="ac-conversa-badge"><?php echo (int) $conversa['por_ler']; ?></span><?php endif; ?>
						</span>
						<span class="ac-conversa-resumo"><?php echo esc_html( wp_trim_words( $conversa['ultima_mensagem'], 10 ) ); ?></span>
					</button>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<button type="button" class="ac-btn ac-btn-ghost" data-cp-fab-target="cp-nova-conversa-modal" style="width:100%;margin-top:8px"><?php esc_html_e( 'Nova conversa', 'contabilidade-pro' ); ?></button>
	</div>
	<?php
}

// Modal partilhada de uma conversa (Área de Cliente) — conteúdo (título,
// lista, formulário) preenchido por JS a cada abertura via fetch.
function cp_ac_render_conversa_modal() {
	?>
	<div id="cp-conversa-modal-backdrop" class="ac-modal-backdrop" data-cp-fab-modal hidden></div>
	<div id="cp-conversa-modal" class="ac-modal" role="dialog" aria-modal="true" aria-labelledby="cp-conversa-modal-title" hidden>
		<div class="ac-modal-head">
			<h3 id="cp-conversa-modal-title" style="font-size:16px"></h3>
			<button type="button" class="ac-modal-close" data-cp-fab-modal-close aria-label="<?php esc_attr_e( 'Fechar', 'contabilidade-pro' ); ?>">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
			</button>
		</div>
		<div id="cp-conversa-modal-corpo"></div>
	</div>
	<?php
}

// Entrada no menu de ações rápidas do Dashboard (botão "+" flutuante) —
// mesmo padrão de cp_ac_render_documento_fab_item() em
// inc/area-cliente-documentos.php: abre a modal de "Nova conversa".
function cp_ac_render_mensagem_fab_item() {
	?>
	<button type="button" class="ac-fab-menu-item" data-cp-fab-target="cp-nova-conversa-modal" role="menuitem">
		<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5h16v11H8l-4 3.5z"></path></svg>
		<span><?php esc_html_e( 'Nova conversa', 'contabilidade-pro' ); ?></span>
	</button>
	<?php
}

function cp_ac_render_mensagem_modal( $empresa_id ) {
	?>
	<div id="cp-nova-conversa-modal-backdrop" class="ac-modal-backdrop" data-cp-fab-modal hidden></div>
	<div id="cp-nova-conversa-modal" class="ac-modal" role="dialog" aria-modal="true" aria-labelledby="cp-nova-conversa-modal-title" hidden>
		<div class="ac-modal-head">
			<h3 id="cp-nova-conversa-modal-title" style="font-size:16px"><?php esc_html_e( 'Nova conversa', 'contabilidade-pro' ); ?></h3>
			<button type="button" class="ac-modal-close" data-cp-fab-modal-close aria-label="<?php esc_attr_e( 'Fechar', 'contabilidade-pro' ); ?>">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
			</button>
		</div>
		<?php cp_ac_render_conversa_form( $empresa_id, 'cp-conversa-form-fab' ); ?>
	</div>
	<?php
}
