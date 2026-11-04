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
<style>
	:root{
		--ac-bg:#F3F5F8; --ac-surface:#FFFFFF; --ac-blue:#7E90B5; --ac-navy:#1F3752; --ac-navy-strong:#16293B;
		--ac-gold:#C9A227; --ac-text:#1F3752; --ac-muted:#5B6B7D; --ac-line:#E2E6EC; --ac-row-hover:#F9FAFC;
		--ac-on-navy:#FFFFFF;
		--ac-sidebar-bg:#1F3752; --ac-sidebar-text:#C4D0E0; --ac-sidebar-text-muted:#9FB0C7;
		--ac-sidebar-hover:rgba(255,255,255,.07); --ac-sidebar-active:#2C4563; --ac-sidebar-border:rgba(255,255,255,.22);
		--ac-badge-amber-bg:#FBF1DB; --ac-badge-amber-fg:#8A6A12;
		--ac-badge-green-bg:#E3EFE6; --ac-badge-green-fg:#2F6B3E;
		--ac-badge-red-bg:#F6E2E2; --ac-badge-red-fg:#92372E;
		--ac-badge-blue-bg:#E7EBF2; --ac-badge-blue-fg:#45597A;
	}
	:root[data-theme="dark"]{
		--ac-bg:#101A27; --ac-surface:#17222F; --ac-blue:#9BAEDA; --ac-navy:#24384E; --ac-navy-strong:#2F4A68;
		--ac-gold:#D9B64C; --ac-text:#E7ECF4; --ac-muted:#9FB0C7; --ac-line:#2A3647; --ac-row-hover:#1C2835;
		--ac-on-navy:#FFFFFF;
		--ac-sidebar-bg:#0D1620; --ac-sidebar-text:#C4D0E0; --ac-sidebar-text-muted:#8697AD;
		--ac-sidebar-hover:rgba(255,255,255,.06); --ac-sidebar-active:#223349; --ac-sidebar-border:rgba(255,255,255,.22);
		--ac-badge-amber-bg:rgba(201,162,39,.18); --ac-badge-amber-fg:#E3C878;
		--ac-badge-green-bg:rgba(63,139,86,.2); --ac-badge-green-fg:#84CE97;
		--ac-badge-red-bg:rgba(178,70,60,.22); --ac-badge-red-fg:#E8897C;
		--ac-badge-blue-bg:rgba(94,122,166,.24); --ac-badge-blue-fg:#AFC3E3;
	}
	*{box-sizing:border-box}
	body{margin:0;background:var(--ac-bg);color:var(--ac-text);font-family:'Public Sans',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
	h1,h2,h3{font-family:'Fraunces',Georgia,serif;margin:0;font-weight:600}
	a{color:var(--ac-blue);text-decoration:none}
	a:hover{color:var(--ac-navy)}
	.ac-card{background:var(--ac-surface);border:1px solid var(--ac-line);border-radius:16px}
	.ac-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 20px;border-radius:9px;font-size:14px;font-weight:600;border:1px solid transparent;cursor:pointer;font-family:'Public Sans',sans-serif}
	.ac-btn-primary{background:var(--ac-navy);color:var(--ac-on-navy)}
	.ac-btn-primary:hover{background:var(--ac-navy-strong);color:var(--ac-on-navy)}

	/* Ecrãs isolados (login / sem empresa / seletor) */
	.ac-center{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px}
	.ac-center-card{width:100%;max-width:420px;padding:40px 36px;text-align:center}
	.ac-mark{width:52px;height:52px;border-radius:50%;background:var(--ac-navy);color:var(--ac-on-navy);display:flex;align-items:center;justify-content:center;font-family:'Fraunces',serif;font-weight:600;font-size:19px;margin:0 auto 16px}

	/* Seletor de empresa */
	.ac-empresa-row{display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--ac-line);border-radius:10px;margin-bottom:10px;font-weight:600;color:var(--ac-text)}
	.ac-empresa-row:hover{border-color:var(--ac-blue);background:var(--ac-row-hover)}

	/* Dashboard (app shell) */
	.ac-shell{display:flex;flex-wrap:wrap;min-height:100vh}
	.ac-sidebar{flex:0 0 240px;background:var(--ac-sidebar-bg);color:var(--ac-sidebar-text);padding:24px 18px;box-sizing:border-box;display:flex;flex-direction:column;gap:22px;border-right:1px solid var(--ac-sidebar-border)}
	.ac-sidebar-logo{display:flex;align-items:center;gap:10px}
	.ac-sidebar-mark{width:34px;height:34px;border-radius:50%;background:var(--ac-gold);color:var(--ac-navy);display:flex;align-items:center;justify-content:center;font-family:'Fraunces',serif;font-weight:600;font-size:14px;flex:none}
	.ac-sidebar-word{font-family:'Fraunces',serif;font-size:14px;line-height:1.25;color:var(--ac-on-navy)}
	.ac-sidebar-word small{display:block;font-family:'Public Sans',sans-serif;font-size:10px;color:var(--ac-sidebar-text-muted);font-weight:400;letter-spacing:.05em;text-transform:uppercase;margin-top:2px}
	.ac-nav-link{display:flex;align-items:center;gap:12px;padding:9px 12px;border-radius:8px;color:var(--ac-sidebar-text);font-size:14px;font-weight:500;margin-bottom:2px}
	.ac-nav-link:hover{background:var(--ac-sidebar-hover);color:var(--ac-on-navy)}
	.ac-nav-link.active{background:var(--ac-sidebar-active);color:var(--ac-on-navy);box-shadow:inset 3px 0 0 var(--ac-gold)}
	.ac-nav-link svg{flex:none}
	.ac-nav-group{display:flex;flex-direction:column;gap:2px}
	.ac-nav-section-label{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--ac-sidebar-text-muted);padding:16px 12px 6px}
	.ac-nav-group>.ac-nav-section-label:first-child{padding-top:4px}
	.ac-sidebar-foot{margin-top:auto;border-top:1px solid var(--ac-sidebar-border);padding-top:16px}
	.ac-main{flex:999 1 560px;min-width:0}
	.ac-topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 32px;background:var(--ac-surface);border-bottom:1px solid var(--ac-line)}
	.ac-empresa-chip{display:flex;align-items:center;gap:8px;padding:7px 14px;border-radius:999px;border:1px solid var(--ac-line);background:var(--ac-bg);font-size:13px;font-weight:600;color:var(--ac-text)}
	.ac-content{padding:32px;display:flex;flex-direction:column;gap:26px;box-sizing:border-box}
	.ac-user{display:flex;align-items:center;gap:14px}
	.ac-avatar{width:34px;height:34px;border-radius:50%;background:var(--ac-blue);color:var(--ac-on-navy);display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12.5px;flex:none}
	.ac-user-name{font-size:13.5px;font-weight:600;color:var(--ac-text)}

	/* Alternador de tema (claro/escuro) */
	.ac-theme-toggle{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;border:1px solid var(--ac-line);background:var(--ac-bg);color:var(--ac-muted);cursor:pointer;flex:none;padding:0}
	.ac-theme-toggle:hover{color:var(--ac-navy);border-color:var(--ac-blue)}
	.ac-theme-toggle svg{width:16px;height:16px;display:block}
	.ac-theme-toggle .ac-theme-toggle__moon{display:none}
	:root[data-theme="dark"] .ac-theme-toggle .ac-theme-toggle__sun{display:none}
	:root[data-theme="dark"] .ac-theme-toggle .ac-theme-toggle__moon{display:block}

	/* Componentes partilhados pelas vistas (Tarefas, Conta Corrente, etc.) */
	.ac-stat-label{font-size:12px;font-weight:600;color:var(--ac-muted);text-transform:uppercase;letter-spacing:.03em}
	.ac-btn-ghost{background:var(--ac-surface);border-color:var(--ac-line);color:var(--ac-text)}
	.ac-btn-ghost:hover{background:var(--ac-bg)}
	.ac-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:11.5px;font-weight:600;white-space:nowrap}
	.ac-badge-amber{background:var(--ac-badge-amber-bg);color:var(--ac-badge-amber-fg)}
	.ac-badge-green{background:var(--ac-badge-green-bg);color:var(--ac-badge-green-fg)}
	.ac-badge-red{background:var(--ac-badge-red-bg);color:var(--ac-badge-red-fg)}
	.ac-badge-blue{background:var(--ac-badge-blue-bg);color:var(--ac-badge-blue-fg)}
	.ac-table{width:100%;border-collapse:collapse;font-size:14px}
	.ac-table th{text-align:left;padding:12px 16px;color:var(--ac-muted);font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--ac-line)}
	.ac-table td{padding:14px 16px;border-bottom:1px solid var(--ac-line);color:var(--ac-text);vertical-align:middle}
	.ac-table tr:last-child td{border-bottom:none}
	.ac-table tr.ac-month-divider td{background:var(--ac-bg);font-size:11.5px;font-weight:600;color:var(--ac-muted);text-transform:uppercase;letter-spacing:.04em;padding:9px 16px;border-bottom:1px solid var(--ac-line)}
	.ac-table-wrap{overflow-x:auto;border:1px solid var(--ac-line);border-radius:12px;background:var(--ac-surface)}
	.ac-table-wrap .ac-table{min-width:560px}
	.ac-icon-btn-sm{width:32px;height:32px;border-radius:8px;border:1px solid var(--ac-line);background:var(--ac-surface);display:inline-flex;align-items:center;justify-content:center;color:var(--ac-muted);cursor:pointer;flex:none}
	.ac-icon-btn-sm:hover{background:var(--ac-bg)}
	.ac-field label{font-size:11.5px;font-weight:600;color:var(--ac-muted);display:block;margin-bottom:4px}
	.ac-field select,.ac-field input,.ac-field textarea{padding:10px 12px;border:1px solid var(--ac-line);border-radius:8px;font-size:13.5px;font-family:'Public Sans',sans-serif;color:var(--ac-text);background:var(--ac-surface);box-sizing:border-box}
	.ac-sec-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px}
	.ac-sec-sub{font-size:12.5px;color:var(--ac-muted);margin-top:3px}
	.ac-todo-item{display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid var(--ac-line)}
	.ac-todo-item:last-child{border-bottom:none;padding-bottom:0}
	.ac-todo-check{width:22px;height:22px;border-radius:50%;border:1.5px solid var(--ac-line);flex:none;margin-top:1px;display:flex;align-items:center;justify-content:center;color:#fff;cursor:pointer}
	.ac-todo-check.done{background:#2F6B3E;border-color:#2F6B3E}
	.ac-todo-title{font-size:14.5px;font-weight:600}
	.ac-todo-title.done{color:var(--ac-muted);text-decoration:line-through;font-weight:500}
	.ac-todo-desc{font-size:12.5px;color:var(--ac-muted);margin-top:3px}
	.ac-todo-meta{font-size:12px;color:var(--ac-muted);margin-top:4px}
	.ac-req-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:14px 0;border-bottom:1px solid var(--ac-line);flex-wrap:wrap}
	.ac-req-row:last-child{border-bottom:none;padding-bottom:0}
	.ac-dropzone{border:1.5px dashed var(--ac-line);border-radius:12px;padding:30px;display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center;background:var(--ac-bg)}
	.ac-status-banner{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:12px;margin-bottom:18px}
	.ac-status-banner.amber{background:var(--ac-badge-amber-bg)}
	.ac-status-banner.green{background:var(--ac-badge-green-bg)}
	.ac-status-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex:none}
	.ac-status-icon.amber{background:var(--ac-badge-amber-bg);color:var(--ac-badge-amber-fg)}
	.ac-status-icon.green{background:var(--ac-badge-green-bg);color:var(--ac-badge-green-fg)}
	.ac-status-title{font-size:14px;font-weight:600}
	.ac-status-sub{font-size:12.5px;margin-top:2px}
	.ac-status-sub.amber{color:var(--ac-badge-amber-fg)}
	.ac-status-sub.green{color:var(--ac-badge-green-fg)}
	.ac-config-note{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:600;color:var(--ac-badge-amber-fg);background:var(--ac-badge-amber-bg);padding:3px 9px;border-radius:999px;width:fit-content}
	.ac-mv-item{padding:16px 0;border-bottom:1px solid var(--ac-line)}
	.ac-mv-item:last-child{border-bottom:none;padding-bottom:0}
	.ac-mv-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
	.ac-mv-amount{font-family:'Fraunces',serif;font-size:16px;flex:none;width:100px}
	.ac-mv-neg{color:var(--ac-text)}
	.ac-mv-pos{color:var(--ac-badge-green-fg)}
	.ac-mv-line{font-size:12px;color:var(--ac-muted);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;margin-top:3px}
	.ac-mv-q{font-size:13.5px;color:var(--ac-text);margin-top:6px;padding:8px 10px;background:var(--ac-bg);border-radius:8px}
	.ac-mv-reply{margin-top:14px;padding-top:14px;border-top:1px dashed var(--ac-line);display:flex;flex-direction:column;gap:10px}
	.ac-mv-reply textarea{width:100%;min-height:64px;resize:vertical}
	.ac-mv-reply-actions{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
	.ac-attach-chip{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;color:var(--ac-muted);background:var(--ac-bg);border:1px solid var(--ac-line);border-radius:999px;padding:5px 10px}
	.ac-trend{display:inline-flex;align-items:center;gap:4px;font-size:12.5px;font-weight:600}
	.ac-trend-up{color:var(--ac-badge-green-fg)}
	.ac-bar-col{display:flex;flex-direction:column;align-items:center;gap:8px;flex:1}
	.ac-bar{width:28px;border-radius:5px 5px 0 0;background:var(--ac-line)}
	.ac-bar.current{background:var(--ac-gold)}
	.ac-bar.projection{background:transparent;border:1.5px dashed var(--ac-line);border-bottom:none}
	.ac-report-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--ac-line)}
	.ac-report-row:last-child{border-bottom:none;padding-bottom:0}
	.ac-tl-item{display:flex;gap:18px;padding:18px 0;border-bottom:1px solid var(--ac-line)}
	.ac-tl-item:last-child{border-bottom:none;padding-bottom:0}
	.ac-tl-date{flex:none;width:62px;height:62px;border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center}
	.ac-tl-date .day{font-family:'Fraunces',serif;font-size:20px;line-height:1}
	.ac-tl-date .mon{font-size:10px;text-transform:uppercase;letter-spacing:.04em;margin-top:2px}
</style>
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

<script>
(function () {
	var btn = document.getElementById( 'ac-theme-toggle' );
	if ( ! btn ) return;
	btn.addEventListener( 'click', function () {
		var root    = document.documentElement;
		var current = root.getAttribute( 'data-theme' ) === 'dark' ? 'dark' : 'light';
		var next    = current === 'dark' ? 'light' : 'dark';
		root.setAttribute( 'data-theme', next );
		btn.setAttribute( 'aria-pressed', next === 'dark' ? 'true' : 'false' );
		try { localStorage.setItem( 'cp-theme', next ); } catch ( e ) {}
	});
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
