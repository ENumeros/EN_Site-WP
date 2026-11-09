<?php
/**
 * Backoffice — página autónoma para a equipa, sem o cabeçalho/rodapé do
 * site público. Fase 1: central de notificações + atalhos para o
 * wp-admin (ver "Backoffice" em CLAUDE.md) — não substitui nenhum ecrã
 * de gestão existente.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php esc_html_e( 'Backoffice', 'contabilidade-pro' ); ?> — <?php bloginfo( 'name' ); ?></title>
<link rel="manifest" href="<?php echo esc_url( home_url( '/manifest-backoffice.webmanifest' ) ); ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?php esc_attr_e( 'Backoffice', 'contabilidade-pro' ); ?>">
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
			<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/monogram.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="ac-center-logo">
			<h1 style="font-size:21px"><?php esc_html_e( 'Backoffice', 'contabilidade-pro' ); ?></h1>
			<p style="color:var(--ac-muted);font-size:13px;margin:10px 0 26px"><?php esc_html_e( 'Entre Números — Contabilidade & Consultoria', 'contabilidade-pro' ); ?></p>
			<a href="<?php echo esc_url( $login_url ); ?>" class="ac-btn ac-btn-primary" style="width:100%"><?php esc_html_e( 'Iniciar sessão', 'contabilidade-pro' ); ?></a>
		</div>
	</div>
	<?php

// ─── 2. Autenticado mas sem acesso ao backoffice ───────────────────────────
elseif ( ! cp_backoffice_utilizador_tem_acesso() ) :
	?>
	<div class="ac-center">
		<div class="ac-card ac-center-card">
			<h1 style="font-size:19px"><?php esc_html_e( 'Sem acesso ao backoffice', 'contabilidade-pro' ); ?></h1>
			<p style="color:var(--ac-muted);font-size:13.5px;margin:12px 0 0"><?php esc_html_e( 'A sua conta não tem acesso a esta área. Contacte um administrador se achar que isto é um engano.', 'contabilidade-pro' ); ?></p>
		</div>
	</div>
	<?php

// ─── 3. Backoffice — central de notificações + atalhos ─────────────────────
else :
	$eventos = cp_backoffice_listar_eventos( 50 );
	?>
	<div class="ac-shell">
		<div class="ac-sidebar-backdrop" id="ac-sidebar-backdrop"></div>
		<nav class="ac-sidebar" id="ac-sidebar" aria-label="<?php esc_attr_e( 'Navegação do Backoffice', 'contabilidade-pro' ); ?>">
			<div class="ac-sidebar-logo">
				<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/monogram.png' ); ?>" alt="" class="ac-sidebar-mark-img">
				<div class="ac-sidebar-word"><?php bloginfo( 'name' ); ?><small><?php esc_html_e( 'Backoffice', 'contabilidade-pro' ); ?></small></div>
			</div>

			<?php
			$bo_empresa_id  = isset( $_GET['empresa_id'] ) ? absint( $_GET['empresa_id'] ) : 0;
			$bo_conversa_id = isset( $_GET['conversa_id'] ) ? sanitize_text_field( wp_unslash( $_GET['conversa_id'] ) ) : '';
			?>
			<div class="ac-nav-group">
				<a class="ac-nav-link<?php echo $bo_empresa_id ? '' : ' active'; ?>" href="<?php echo esc_url( get_permalink() ); ?>">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v3M12 18v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M3 12h3M18 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"></path><circle cx="12" cy="12" r="4"></circle></svg>
					<?php esc_html_e( 'Notificações', 'contabilidade-pro' ); ?>
				</a>
				<a class="ac-nav-link<?php echo $bo_empresa_id ? ' active' : ''; ?>" href="#cp-backoffice-tarefas">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="4" width="17" height="16" rx="2"></rect><path d="M7.5 9.5 9 11l2.5-3"></path><path d="M14 9h4"></path><path d="M7.5 15.5 9 17l2.5-3"></path><path d="M14 15h4"></path></svg>
					<?php esc_html_e( 'Lembretes', 'contabilidade-pro' ); ?>
				</a>
				<a class="ac-nav-link<?php echo $bo_empresa_id ? ' active' : ''; ?>" href="#cp-backoffice-mensagens">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5h16v11H8l-4 3.5z"></path></svg>
					<?php esc_html_e( 'Mensagens', 'contabilidade-pro' ); ?>
				</a>
				<a class="ac-nav-link" href="<?php echo esc_url( admin_url() ); ?>">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"></path></svg>
					<?php esc_html_e( 'Abrir wp-admin', 'contabilidade-pro' ); ?>
				</a>
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
				<div style="display:flex;align-items:center;gap:14px">
					<button type="button" class="ac-sidebar-toggle" id="ac-sidebar-toggle" aria-expanded="false" aria-controls="ac-sidebar" aria-label="<?php esc_attr_e( 'Abrir menu', 'contabilidade-pro' ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
					</button>
					<span class="ac-empresa-chip"><?php esc_html_e( 'Equipa', 'contabilidade-pro' ); ?></span>
				</div>

				<?php
				$bo_user      = wp_get_current_user();
				$bo_user_name = $bo_user->display_name ? $bo_user->display_name : $bo_user->user_login;
				$bo_parts     = preg_split( '/\s+/', trim( $bo_user_name ) );
				$bo_initials  = mb_strtoupper( mb_substr( $bo_parts[0], 0, 1 ) . ( isset( $bo_parts[1] ) ? mb_substr( $bo_parts[1], 0, 1 ) : mb_substr( $bo_parts[0], 1, 1 ) ) );
				?>
				<div class="ac-user">
					<button type="button" class="ac-theme-toggle" id="ac-theme-toggle" aria-pressed="false" aria-label="<?php esc_attr_e( 'Alternar modo escuro', 'contabilidade-pro' ); ?>">
						<svg class="ac-theme-toggle__sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
						<svg class="ac-theme-toggle__moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
					</button>
					<div class="ac-user-menu">
						<button type="button" class="ac-user-trigger" id="cp-user-menu-btn" aria-haspopup="true" aria-expanded="false">
							<div class="ac-avatar"><?php echo esc_html( $bo_initials ); ?></div>
							<span class="ac-user-name"><?php echo esc_html( $bo_user_name ); ?></span>
						</button>
						<div class="ac-user-dropdown" id="cp-user-dropdown" hidden>
							<span id="cp-push-msg" class="ac-dropdown-msg" hidden></span>
							<button type="button" class="ac-dropdown-item" id="cp-push-btn" aria-pressed="false" hidden><?php esc_html_e( 'Ativar notificações', 'contabilidade-pro' ); ?></button>
						</div>
					</div>
				</div>
			</header>

			<div class="ac-content">
				<div>
					<h1 style="font-size:23px"><?php esc_html_e( 'Notificações', 'contabilidade-pro' ); ?></h1>
					<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'O que mudou recentemente na Área de Cliente — cada entrada abre o sítio certo no wp-admin para agir.', 'contabilidade-pro' ); ?></div>
				</div>

				<?php if ( empty( $eventos ) ) : ?>
					<?php cp_ac_vazio( __( 'Sem eventos recentes', 'contabilidade-pro' ), __( 'Quando houver novidade — por exemplo, um cliente responder a um pendente — aparece aqui.', 'contabilidade-pro' ) ); ?>
				<?php else : ?>
					<div class="ac-card" style="padding:22px">
						<?php foreach ( $eventos as $evento ) : ?>
							<div class="ac-req-row">
								<div>
									<div style="font-size:14px;font-weight:600"><?php echo esc_html( $evento['titulo'] ); ?></div>
									<div class="ac-sec-sub"><?php echo esc_html( $evento['corpo'] ); ?></div>
									<?php if ( ! empty( $evento['empresa_id'] ) && get_the_title( $evento['empresa_id'] ) ) : ?>
										<div class="ac-evento-empresa"><?php echo esc_html( get_the_title( $evento['empresa_id'] ) ); ?></div>
									<?php endif; ?>
								</div>
								<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
									<span class="ac-sec-sub">
										<?php
										printf(
											/* translators: %s: há quanto tempo, ex. "3 horas" */
											esc_html__( 'há %s', 'contabilidade-pro' ),
											esc_html( human_time_diff( strtotime( $evento['criado_em'] ), current_time( 'timestamp' ) ) )
										);
										?>
									</span>
									<?php if ( ! empty( $evento['url'] ) ) : ?>
										<a class="ac-btn ac-btn-ghost" style="padding:7px 12px" href="<?php echo esc_url( $evento['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Abrir no wp-admin', 'contabilidade-pro' ); ?></a>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div id="cp-backoffice-tarefas" style="margin-top:36px">
					<div>
						<h1 style="font-size:23px"><?php esc_html_e( 'Lembretes', 'contabilidade-pro' ); ?></h1>
						<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Deixe uma nota rápida para um cliente, ou conclua um lembrete dele.', 'contabilidade-pro' ); ?></div>
					</div>

					<div class="ac-card" style="padding:22px;margin-top:18px">
						<form method="get" action="<?php echo esc_url( get_permalink() ); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
							<label for="cp-bo-empresa" style="font-size:12.5px;font-weight:600"><?php esc_html_e( 'Empresa', 'contabilidade-pro' ); ?></label>
							<select name="empresa_id" id="cp-bo-empresa" onchange="this.form.submit()" style="padding:9px 12px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-text);font-size:13.5px">
								<option value=""><?php esc_html_e( 'Escolha uma empresa…', 'contabilidade-pro' ); ?></option>
								<?php foreach ( get_posts( [ 'post_type' => 'cp_empresa', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $bo_empresa ) : ?>
									<option value="<?php echo esc_attr( $bo_empresa->ID ); ?>" <?php selected( $bo_empresa_id, $bo_empresa->ID ); ?>><?php echo esc_html( cp_ac_empresa_label( $bo_empresa->ID ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</form>
					</div>

					<?php if ( $bo_empresa_id && get_post_type( $bo_empresa_id ) === 'cp_empresa' ) : ?>
						<?php cp_ac_render_tarefas( $bo_empresa_id, true ); ?>
					<?php endif; ?>
				</div>

				<div id="cp-backoffice-mensagens" style="margin-top:36px">
					<div>
						<h1 style="font-size:23px"><?php esc_html_e( 'Mensagens', 'contabilidade-pro' ); ?></h1>
						<div style="color:var(--ac-muted);font-size:14px;margin-top:6px"><?php esc_html_e( 'Converse com um cliente — escolha a empresa acima, em Lembretes.', 'contabilidade-pro' ); ?></div>
					</div>

					<?php if ( $bo_empresa_id && get_post_type( $bo_empresa_id ) === 'cp_empresa' ) : ?>
						<?php cp_ac_render_mensagens( $bo_empresa_id, $bo_conversa_id ); ?>
					<?php endif; ?>
				</div>
			</div>
		</main>
	</div>
	<?php
endif;
?>

<?php wp_footer(); ?>
</body>
</html>
