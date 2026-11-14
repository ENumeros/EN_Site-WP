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

// ─── Registo de eventos (opção 'cp_eventos_staff', mais recente primeiro) ──

function cp_backoffice_registar_evento( $titulo, $corpo, $url, $empresa_id = 0 ) {
	$eventos = cp_backoffice_listar_eventos( 0 );
	array_unshift( $eventos, [
		'titulo'     => $titulo,
		'corpo'      => $corpo,
		'url'        => $url,
		'empresa_id' => (int) $empresa_id,
		'criado_em'  => current_time( 'mysql' ),
	] );
	update_option( 'cp_eventos_staff', array_slice( $eventos, 0, 100 ), false );
}

function cp_backoffice_listar_eventos( $limite = 50 ) {
	$eventos = get_option( 'cp_eventos_staff' );
	$eventos = is_array( $eventos ) ? $eventos : [];
	return $limite > 0 ? array_slice( $eventos, 0, $limite ) : $eventos;
}
