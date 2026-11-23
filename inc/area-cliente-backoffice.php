<?php
/**
 * Backoffice — central de notificações + atalhos para a equipa.
 *
 * Fase 1 (ver CLAUDE.md): só mostra o que mudou recentemente e aponta
 * para o wp-admin para agir — não substitui nenhum ecrã de gestão.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Papel da equipa ────────────────────────────────────────────────────────
// Criado uma única vez (idempotente). A Work atribui-o ao criar um
// utilizador do tipo "equipa" (POST /cp/v1/utilizadores, campo `tipo`);
// também se pode escolher no ecrã nativo wp-admin → Utilizadores.
// Também corre em `init`: `after_switch_theme` só dispara ao ativar o
// tema, por isso um site onde o tema já estava ativo nunca tinha o papel.

function cp_backoffice_registar_papel_equipa() {
	if ( get_role( 'equipa_en' ) ) return;
	add_role( 'equipa_en', __( 'Equipa Entre Números', 'contabilidade-pro' ), [
		'read'          => true,
		'cp_backoffice' => true,
	] );
}
add_action( 'after_switch_theme', 'cp_backoffice_registar_papel_equipa' );
add_action( 'init', 'cp_backoffice_registar_papel_equipa' );

function cp_backoffice_utilizador_tem_acesso( $user_id = null ) {
	$user_id = $user_id ?: get_current_user_id();
	return user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'cp_backoffice' );
}

// ─── Equipa sem empresas → Backoffice ──────────────────────────────────────
// Quem é da equipa (cp_backoffice ou administrador) e não tem empresas como
// cliente não tem nada a ver na Área de Cliente: ao iniciar sessão vai logo
// para o Backoffice, e se abrir /area-de-cliente também é levado para lá.
// Exceção: um administrador que pediu o wp-admin (ex.: wp-login.php a
// partir do painel) continua a ir para lá.

function cp_backoffice_url() {
	$pagina = get_page_by_path( 'backoffice' );
	return $pagina ? get_permalink( $pagina ) : home_url( '/backoffice/' );
}

function cp_backoffice_e_destino_do_utilizador( $user_id ) {
	return $user_id && cp_backoffice_utilizador_tem_acesso( $user_id ) && empty( cp_ac_user_empresas( $user_id ) );
}

function cp_backoffice_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if ( ! ( $user instanceof WP_User ) || ! cp_backoffice_e_destino_do_utilizador( $user->ID ) ) {
		return $redirect_to;
	}
	if ( false !== strpos( (string) $requested_redirect_to, '/wp-admin' ) && user_can( $user, 'manage_options' ) ) {
		return $redirect_to;
	}
	return cp_backoffice_url();
}
add_filter( 'login_redirect', 'cp_backoffice_login_redirect', 20, 3 );

function cp_backoffice_desviar_area_cliente() {
	if ( is_page( 'area-de-cliente' ) && is_user_logged_in() && cp_backoffice_e_destino_do_utilizador( get_current_user_id() ) ) {
		wp_safe_redirect( cp_backoffice_url() );
		exit;
	}
}
add_action( 'template_redirect', 'cp_backoffice_desviar_area_cliente' );

// ─── Ver como o cliente (pré-visualização, só leitura) ─────────────────────
// A equipa abre /area-de-cliente de uma empresa a partir do Backoffice
// (entrada "Vista cliente" no menu). Vê TODAS as ferramentas que existem,
// mesmo as que o cliente não vê; no menu, as que estão ativas para pelo
// menos um utilizador (cliente) da empresa têm uma marca. Fica num cookie de sessão do browser (não em usermeta), por
// isso só vale nesse browser e nunca contamina pedidos REST da Work (que
// usam Application Password, sem cookie). Só é aplicado ao desenhar a
// página /area-de-cliente — formulários e REST continuam a ver a equipa
// como equipa. Não é uma fronteira de segurança (a equipa já pode agir em
// qualquer empresa): o "só leitura" é para não agir por engano em nome do
// cliente, e é garantido no browser (submit e POSTs bloqueados).

const CP_BO_COOKIE_VER_EMPRESA = 'cp_bo_ver_empresa';

// Decisão pura: que empresa mostrar em pré-visualização (0 = nenhuma).
function cp_bo_calc_empresa_pre_visualizacao( $e_equipa, $pedida, array $empresas_validas ) {
	$pedida = (int) $pedida;
	if ( ! $e_equipa || $pedida <= 0 ) return 0;
	return in_array( $pedida, array_map( 'intval', $empresas_validas ), true ) ? $pedida : 0;
}

function cp_bo_empresa_publicada( $empresa_id ) {
	return $empresa_id && get_post_type( $empresa_id ) === 'cp_empresa' && get_post_status( $empresa_id ) === 'publish';
}

// Empresa em pré-visualização neste pedido (0 = modo normal).
function cp_bo_pre_visualizacao() {
	return isset( $GLOBALS['cp_bo_pre_visualizacao'] ) ? (int) $GLOBALS['cp_bo_pre_visualizacao'] : 0;
}

function cp_bo_url_ver_como_cliente( $empresa_id ) {
	$url = add_query_arg( 'cp_ver_empresa', (int) $empresa_id, home_url( '/area-de-cliente/' ) );
	return wp_nonce_url( $url, 'cp_ver_empresa_' . (int) $empresa_id );
}

function cp_bo_url_vista_cliente() {
	return add_query_arg( 'vista', 'cliente', cp_backoffice_url() );
}

// Decisão pura: chaves que pelo menos um utilizador vê nesta empresa.
// $acessos_por_utilizador: um item por utilizador-cliente da empresa — null
// (herda tudo o que a empresa tem) ou a lista restrita dele. Mantém a
// ordem da matriz da empresa.
function cp_bo_calc_ferramentas_ativas_clientes( array $da_empresa, array $acessos_por_utilizador ) {
	$vistas = [];
	foreach ( $acessos_por_utilizador as $acessos ) {
		$vistas = array_merge( $vistas, null === $acessos ? $da_empresa : array_intersect( $da_empresa, (array) $acessos ) );
	}
	return array_values( array_intersect( $da_empresa, array_unique( $vistas ) ) );
}

// Utilizadores-cliente ativos da empresa (nunca equipa nem contas
// desativadas pela Work). Lê o usermeta diretamente: cp_ac_user_empresas()
// do próprio utilizador está trocada durante a pré-visualização.
function cp_bo_ferramentas_ativas_clientes( $empresa_id ) {
	static $cache = [];
	$empresa_id = (int) $empresa_id;
	if ( isset( $cache[ $empresa_id ] ) ) return $cache[ $empresa_id ];
	$acessos = [];
	foreach ( get_users( [ 'meta_key' => 'cp_empresas', 'meta_compare' => 'EXISTS' ] ) as $u ) {
		$empresas = get_user_meta( $u->ID, 'cp_empresas', true );
		if ( ! is_array( $empresas ) || ! in_array( $empresa_id, array_map( 'intval', $empresas ), true ) ) continue;
		if ( cp_backoffice_utilizador_tem_acesso( $u->ID ) || get_user_meta( $u->ID, '_cp_inativo', true ) ) continue;
		$mapa      = cp_ac_user_acessos( $u->ID );
		$acessos[] = array_key_exists( $empresa_id, $mapa ) ? $mapa[ $empresa_id ] : null;
	}
	return $cache[ $empresa_id ] = cp_bo_calc_ferramentas_ativas_clientes( cp_ac_empresa_ferramentas_ativas( $empresa_id ), $acessos );
}

// Marca no menu da Área de Cliente (só em pré-visualização).
function cp_bo_marca_ferramenta( $chave ) {
	$empresa = cp_bo_pre_visualizacao();
	if ( ! $empresa ) return '';
	if ( in_array( $chave, cp_bo_ferramentas_ativas_clientes( $empresa ), true ) ) {
		return '<span class="ac-nav-ativa" title="' . esc_attr__( 'Ativa para o cliente', 'contabilidade-pro' ) . '" aria-label="' . esc_attr__( 'Ativa para o cliente', 'contabilidade-pro' ) . '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"></path></svg></span>';
	}
	return '<span class="ac-nav-inativa-sr">' . esc_html__( '(o cliente não vê)', 'contabilidade-pro' ) . '</span>';
}

// $para: 'cliente' (Vista cliente, para trocar de empresa) ou 'resumo'.
function cp_bo_url_sair_pre_visualizacao( $para = 'cliente' ) {
	return add_query_arg( 'cp_sair_ver', 'resumo' === $para ? 'resumo' : '1', home_url( '/area-de-cliente/' ) );
}

function cp_bo_gravar_cookie_ver_empresa( $valor, $expira ) {
	setcookie( CP_BO_COOKIE_VER_EMPRESA, (string) $valor, [
		'expires'  => $expira,
		'path'     => COOKIEPATH ? COOKIEPATH : '/',
		'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	] );
}

// Prioridade 1: antes de cp_ac_handle_pedidos_url() e do desvio da equipa
// para o Backoffice (ambos a 10), que já têm de ver a empresa escolhida.
function cp_bo_tratar_pre_visualizacao() {
	if ( ! is_page( 'area-de-cliente' ) || ! is_user_logged_in() ) return;
	$user_id  = get_current_user_id();
	$e_equipa = cp_backoffice_utilizador_tem_acesso( $user_id );

	if ( isset( $_GET['cp_sair_ver'] ) ) {
		cp_bo_gravar_cookie_ver_empresa( '', time() - HOUR_IN_SECONDS );
		$destino = 'resumo' === $_GET['cp_sair_ver'] ? cp_backoffice_url() : cp_bo_url_vista_cliente();
		wp_safe_redirect( $e_equipa ? $destino : home_url( '/area-de-cliente/' ) );
		exit;
	}

	if ( isset( $_GET['cp_ver_empresa'] ) ) {
		$pedida = absint( $_GET['cp_ver_empresa'] );
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( $e_equipa && cp_bo_empresa_publicada( $pedida ) && wp_verify_nonce( $nonce, 'cp_ver_empresa_' . $pedida ) ) {
			cp_bo_gravar_cookie_ver_empresa( $pedida, 0 ); // cookie de sessão
		}
		wp_safe_redirect( get_permalink() );
		exit;
	}

	$no_cookie = isset( $_COOKIE[ CP_BO_COOKIE_VER_EMPRESA ] ) ? absint( $_COOKIE[ CP_BO_COOKIE_VER_EMPRESA ] ) : 0;
	$empresa   = cp_bo_calc_empresa_pre_visualizacao( $e_equipa, $no_cookie, cp_bo_empresa_publicada( $no_cookie ) ? [ $no_cookie ] : [] );
	if ( $empresa ) {
		$GLOBALS['cp_bo_pre_visualizacao'] = $empresa;
	}
}
add_action( 'template_redirect', 'cp_bo_tratar_pre_visualizacao', 1 );

// Ao iniciar sessão começa sempre fora da pré-visualização.
function cp_bo_limpar_pre_visualizacao_no_login() {
	if ( isset( $_COOKIE[ CP_BO_COOKIE_VER_EMPRESA ] ) ) {
		cp_bo_gravar_cookie_ver_empresa( '', time() - HOUR_IN_SECONDS );
	}
}
add_action( 'wp_login', 'cp_bo_limpar_pre_visualizacao_no_login' );

function cp_bo_body_class_pre_visualizacao( $classes ) {
	if ( cp_bo_pre_visualizacao() ) $classes[] = 'ac-pre-visualizacao';
	return $classes;
}
add_filter( 'body_class', 'cp_bo_body_class_pre_visualizacao' );

// Faixa no topo da Área de Cliente + bloqueio de submissões/POSTs no browser.
function cp_bo_render_faixa_pre_visualizacao() {
	$empresa = cp_bo_pre_visualizacao();
	if ( ! $empresa ) return;
	$aviso = __( 'Pré-visualização só de leitura — nada é gravado. Para agir, use o Backoffice.', 'contabilidade-pro' );
	?>
	<div class="ac-pre-visualizacao-faixa" role="status">
		<span>
			<strong><?php esc_html_e( 'Está a ver como o cliente', 'contabilidade-pro' ); ?></strong> ·
			<?php echo esc_html( cp_ac_empresa_label( $empresa ) ); ?> ·
			<?php esc_html_e( 'só leitura', 'contabilidade-pro' ); ?>
			<span class="ac-pre-visualizacao-legenda">
				<span class="ac-nav-ativa" aria-hidden="true"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"></path></svg></span>
				<?php esc_html_e( '= ativa para pelo menos um utilizador do cliente', 'contabilidade-pro' ); ?>
				<?php $ferramentas = cp_ac_ferramentas(); foreach ( [ 'tarefas', 'mensagens' ] as $chave ) : // sem entrada no menu (vivem no Dashboard) ?>
					· <?php echo esc_html( $ferramentas[ $chave ]['label'] ); ?>
					<?php echo in_array( $chave, cp_bo_ferramentas_ativas_clientes( $empresa ), true ) ? esc_html__( 'ativa', 'contabilidade-pro' ) : esc_html__( 'o cliente não vê', 'contabilidade-pro' ); ?>
				<?php endforeach; ?>
			</span>
		</span>
		<span style="display:flex;gap:8px;flex-wrap:wrap">
			<a class="ac-btn ac-btn-ghost" href="<?php echo esc_url( cp_bo_url_sair_pre_visualizacao() ); ?>"><?php esc_html_e( 'Trocar de cliente', 'contabilidade-pro' ); ?></a>
			<a class="ac-btn ac-btn-ghost" href="<?php echo esc_url( cp_bo_url_sair_pre_visualizacao( 'resumo' ) ); ?>"><?php esc_html_e( 'Voltar ao Backoffice', 'contabilidade-pro' ); ?></a>
		</span>
	</div>
	<script>
	window.cpPreVisualizacao = true;
	window.addEventListener( 'submit', function ( evento ) {
		evento.preventDefault();
		evento.stopImmediatePropagation();
		window.alert( <?php echo wp_json_encode( $aviso ); ?> );
	}, true );
	</script>
	<?php
}

// ─── Registo de eventos (opção 'cp_eventos_staff', mais recente primeiro) ──

// $tipo: documentos | mensagens | pendentes | lembretes — usado nos filtros
// do Resumo (cp_bo_calc_tipo_evento() deduz o dos eventos antigos).
function cp_backoffice_registar_evento( $titulo, $corpo, $url, $empresa_id = 0, $tipo = '' ) {
	$eventos = cp_backoffice_listar_eventos( 0 );
	array_unshift( $eventos, [
		'titulo'     => $titulo,
		'corpo'      => $corpo,
		'url'        => $url,
		'empresa_id' => (int) $empresa_id,
		'tipo'       => (string) $tipo,
		'criado_em'  => current_time( 'mysql' ),
	] );
	update_option( 'cp_eventos_staff', array_slice( $eventos, 0, 100 ), false );
}

function cp_backoffice_listar_eventos( $limite = 50 ) {
	$eventos = get_option( 'cp_eventos_staff' );
	$eventos = is_array( $eventos ) ? $eventos : [];
	return $limite > 0 ? array_slice( $eventos, 0, $limite ) : $eventos;
}

// Backoffice → "Vista cliente": escolher a empresa e abrir a Área de Cliente
// como ela (cada opção leva o link já assinado com o nonce dessa empresa).
function cp_bo_render_vista_cliente() {
	$empresas = cp_ac_ordenar_empresas( get_posts( [ 'post_type' => 'cp_empresa', 'posts_per_page' => -1, 'post_status' => 'publish' ] ) );
	?>
	<div>
		<h1 style="font-size:23px"><?php esc_html_e( 'Vista cliente', 'contabilidade-pro' ); ?></h1>
		<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Abra a Área de Cliente de uma empresa, só para ver. Aparecem todas as ferramentas; as que estão ativas para o cliente têm um ✓ no menu.', 'contabilidade-pro' ); ?></div>
	</div>
	<?php if ( empty( $empresas ) ) : ?>
		<?php cp_ac_vazio( __( 'Ainda não há empresas', 'contabilidade-pro' ), __( 'As empresas chegam da Work quando o cliente tem conta na Área de Cliente.', 'contabilidade-pro' ) ); ?>
	<?php else : ?>
		<div class="ac-card" style="padding:22px;margin-top:18px">
			<form style="display:flex;gap:10px;flex-wrap:wrap;align-items:center" onsubmit="event.preventDefault();if(this.elements.destino.value){window.location=this.elements.destino.value;}">
				<label for="cp-bo-vista-cliente" style="font-size:12.5px;font-weight:600"><?php esc_html_e( 'Empresa', 'contabilidade-pro' ); ?></label>
				<select name="destino" id="cp-bo-vista-cliente" onchange="if(this.value){window.location=this.value;}" style="padding:9px 12px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13.5px;max-width:100%">
					<option value=""><?php esc_html_e( 'Escolha uma empresa…', 'contabilidade-pro' ); ?></option>
					<?php foreach ( $empresas as $empresa ) : ?>
						<option value="<?php echo esc_url( cp_bo_url_ver_como_cliente( $empresa->ID ) ); ?>"><?php echo esc_html( cp_ac_empresa_label( $empresa->ID ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="ac-btn ac-btn-primary"><?php esc_html_e( 'Abrir', 'contabilidade-pro' ); ?></button>
			</form>
		</div>
	<?php endif; ?>
	<?php
}
