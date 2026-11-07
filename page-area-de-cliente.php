<?php
/**
 * Área de Cliente — página autónoma, sem o cabeçalho/rodapé do site público.
 * (login → seleção de empresa → dashboard, com a navegação lateral filtrada
 * pela matriz de ferramentas em wp-admin → Área de Cliente)
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php esc_html_e( 'Área de Cliente', 'contabilidade-pro' ); ?> — <?php bloginfo( 'name' ); ?></title>
<link rel="manifest" href="<?php echo esc_url( home_url( '/manifest.webmanifest' ) ); ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?php esc_attr_e( 'Área de Cliente', 'contabilidade-pro' ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( get_template_directory_uri() . '/assets/img/favicon-192.png' ); ?>">
<script>
(function () {
    try {
        var stored = localStorage.getItem( 'cp-theme' );
        var theme = stored || ( window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light' );
        document.documentElement.setAttribute( 'data-theme', theme );
    } catch ( e ) {}
})();
</script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500&family=Public+Sans:wght@400;500;600;700&display=swap">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'ac-page' ); ?>>
<?php wp_body_open(); ?>

<?php

// ─── 1. Sem sessão iniciada ─────────────────────────────────────────────────
if ( ! is_user_logged_in() ) :
	$login_url = wp_login_url( get_permalink() );
	?>
	<div class="ac-center">
		<div class="ac-card ac-center-card">
			<div class="ac-mark">EN</div>
			<h1 style="font-size:21px"><?php esc_html_e( 'Área de Cliente', 'contabilidade-pro' ); ?></h1>
			<p style="color:var(--ac-muted);font-size:13px;margin:10px 0 26px"><?php esc_html_e( 'Entre Números — Contabilidade & Consultoria', 'contabilidade-pro' ); ?></p>
			<a href="<?php echo esc_url( $login_url ); ?>" class="ac-btn ac-btn-primary" style="width:100%"><?php esc_html_e( 'Iniciar sessão', 'contabilidade-pro' ); ?></a>
		</div>
	</div>
	<?php

// ─── 2. Sem empresas associadas ─────────────────────────────────────────────
elseif ( empty( cp_ac_user_empresas() ) ) :
	?>
	<div class="ac-center">
		<div class="ac-card ac-center-card">
			<h1 style="font-size:19px"><?php esc_html_e( 'Sem empresas associadas', 'contabilidade-pro' ); ?></h1>
			<p style="color:var(--ac-muted);font-size:13.5px;margin:12px 0 24px"><?php esc_html_e( 'A sua conta ainda não tem nenhuma empresa associada. Contacte-nos para resolvermos.', 'contabilidade-pro' ); ?></p>
			<a href="mailto:<?php echo esc_attr( cp_opt( 'cp_email', get_option( 'admin_email' ) ) ); ?>" class="ac-btn ac-btn-primary"><?php esc_html_e( 'Falar connosco', 'contabilidade-pro' ); ?></a>
		</div>
	</div>
	<?php

else :
	$empresas_user = cp_ac_user_empresas();
	$empresa_ativa = cp_ac_empresa_ativa();

	// ─── 3. Várias empresas, nenhuma escolhida ainda → Seletor ─────────────
	if ( ! $empresa_ativa && count( $empresas_user ) > 1 ) :
		?>
		<div class="ac-center">
			<div class="ac-card" style="width:100%;max-width:440px;padding:36px 32px">
				<h1 style="font-size:19px"><?php esc_html_e( 'Escolha a empresa', 'contabilidade-pro' ); ?></h1>
				<p style="color:var(--ac-muted);font-size:13px;margin:8px 0 20px"><?php esc_html_e( 'Tem acesso a mais do que uma empresa. Selecione com qual quer trabalhar.', 'contabilidade-pro' ); ?></p>
				<?php foreach ( $empresas_user as $empresa_id ) : ?>
					<a class="ac-empresa-row" href="<?php echo esc_url( add_query_arg( 'cp_empresa', $empresa_id, get_permalink() ) ); ?>">
						<?php echo esc_html( get_the_title( $empresa_id ) ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php

	// ─── 4. Dashboard — app shell com nav filtrada pela matriz ─────────────
	else :
		$ferramentas       = cp_ac_ferramentas();
		$houve_ferramenta  = false;
		$vista_pedida      = isset( $_GET['vista'] ) ? sanitize_key( wp_unslash( $_GET['vista'] ) ) : '';
		$vista             = ( '' !== $vista_pedida && isset( $ferramentas[ $vista_pedida ] ) && cp_ac_tem_ferramenta( $vista_pedida ) ) ? $vista_pedida : '';
		?>
		<div class="ac-shell">
			<nav class="ac-sidebar" aria-label="<?php esc_attr_e( 'Navegação da Área de Cliente', 'contabilidade-pro' ); ?>">
				<div class="ac-sidebar-logo">
					<div class="ac-sidebar-mark">EN</div>
					<div class="ac-sidebar-word"><?php bloginfo( 'name' ); ?><small><?php esc_html_e( 'Área de Cliente', 'contabilidade-pro' ); ?></small></div>
				</div>

				<div class="ac-nav-group">
					<a class="ac-nav-link<?php echo '' === $vista ? ' active' : ''; ?>" href="<?php echo esc_url( get_permalink() ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5"></path><path d="M5.5 10v9a1 1 0 0 0 1 1H9.5v-6h5v6H17.5a1 1 0 0 0 1-1v-9"></path></svg>
						<?php esc_html_e( 'Dashboard', 'contabilidade-pro' ); ?>
					</a>
					<?php
					$ac_grupo_atual = null;
					foreach ( $ferramentas as $chave => $info ) :
						if ( ! cp_ac_tem_ferramenta( $chave ) ) continue;
						$houve_ferramenta = true;
						$ac_grupo = isset( $info['grupo'] ) ? $info['grupo'] : '';
						if ( '' !== $ac_grupo && $ac_grupo !== $ac_grupo_atual ) :
							?>
							<div class="ac-nav-section-label"><?php echo esc_html( $ac_grupo ); ?></div>
							<?php
						endif;
						$ac_grupo_atual = $ac_grupo;
						?>
						<a class="ac-nav-link<?php echo $vista === $chave ? ' active' : ''; ?>" href="<?php echo esc_url( cp_ac_vista_url( $chave ) ); ?>">
							<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo isset( $info['icon'] ) ? $info['icon'] : ''; ?></svg>
							<?php echo esc_html( $info['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</div>

				<div class="ac-sidebar-foot">
					<a class="ac-nav-link" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5H6a1.5 1.5 0 0 0-1.5 1.5v11A1.5 1.5 0 0 0 6 19h3"></path><path d="M15 15.5 19.5 11 15 6.5"></path><path d="M19 11H9.5"></path></svg>
						<?php esc_html_e( 'Sair', 'contabilidade-pro' ); ?>
					</a>
				</div>
			</nav>

			<main class="ac-main">
				<header class="ac-topbar">
					<?php if ( count( $empresas_user ) > 1 ) : ?>
						<a class="ac-empresa-chip" href="<?php echo esc_url( add_query_arg( 'cp_trocar', '1', get_permalink() ) ); ?>" title="<?php esc_attr_e( 'Trocar de empresa', 'contabilidade-pro' ); ?>">
							<?php echo esc_html( get_the_title( $empresa_ativa ) ); ?> ↕
						</a>
					<?php else : ?>
						<span class="ac-empresa-chip"><?php echo esc_html( get_the_title( $empresa_ativa ) ); ?></span>
					<?php endif; ?>

					<?php
					$ac_user       = wp_get_current_user();
					$ac_user_name  = $ac_user->display_name ? $ac_user->display_name : $ac_user->user_login;
					$ac_user_parts = preg_split( '/\s+/', trim( $ac_user_name ) );
					$ac_initials   = mb_strtoupper( mb_substr( $ac_user_parts[0], 0, 1 ) . ( isset( $ac_user_parts[1] ) ? mb_substr( $ac_user_parts[1], 0, 1 ) : mb_substr( $ac_user_parts[0], 1, 1 ) ) );
					?>
					<div class="ac-user">
						<span id="cp-push-msg" class="ac-sec-sub" style="max-width:220px" hidden></span>
						<button type="button" class="ac-btn ac-btn-ghost" id="cp-push-btn" style="padding:7px 12px;font-size:12.5px" aria-pressed="false" hidden><?php esc_html_e( 'Ativar notificações', 'contabilidade-pro' ); ?></button>
						<button type="button" class="ac-theme-toggle" id="ac-theme-toggle" aria-pressed="false" aria-label="<?php esc_attr_e( 'Alternar modo escuro', 'contabilidade-pro' ); ?>">
							<svg class="ac-theme-toggle__sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
							<svg class="ac-theme-toggle__moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
						</button>
						<div class="ac-avatar"><?php echo esc_html( $ac_initials ); ?></div>
						<span class="ac-user-name"><?php echo esc_html( $ac_user_name ); ?></span>
					</div>
				</header>

				<div class="ac-content">
					<?php
					if ( '' === $vista ) {
						cp_ac_vista_dashboard( $ac_user_name, $ferramentas );
					} else {
						$ac_fn = 'cp_ac_vista_' . $vista;
						if ( function_exists( $ac_fn ) ) {
							call_user_func( $ac_fn );
						}
					}
					?>
				</div>
			</main>
		</div>
		<?php
	endif;
endif;
?>

<?php wp_footer(); ?>
</body>
</html>
