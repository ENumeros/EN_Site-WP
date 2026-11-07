<?php
/**
 * Backoffice — central de notificações + atalhos para a equipa.
 *
 * Fase 1 (ver CLAUDE.md): só mostra o que mudou recentemente e aponta
 * para o wp-admin para agir — não substitui nenhum ecrã de gestão.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Papel da equipa ────────────────────────────────────────────────────────
// Criado uma única vez (idempotente); depois disso, atribuir o papel a
// um utilizador é só escolhê-lo no ecrã nativo wp-admin → Utilizadores.

function cp_backoffice_registar_papel_equipa() {
	if ( get_role( 'equipa_en' ) ) return;
	add_role( 'equipa_en', __( 'Equipa Entre Números', 'contabilidade-pro' ), [
		'read'          => true,
		'cp_backoffice' => true,
	] );
}
add_action( 'after_switch_theme', 'cp_backoffice_registar_papel_equipa' );

function cp_backoffice_utilizador_tem_acesso( $user_id = null ) {
	$user_id = $user_id ?: get_current_user_id();
	return user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'cp_backoffice' );
}

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
