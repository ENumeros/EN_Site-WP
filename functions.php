<?php
/**
 * Entre Números — functions.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Área de Cliente: empresas, ferramentas e matriz de acesso ────────────
require_once get_template_directory() . '/inc/area-cliente.php';
require_once get_template_directory() . '/inc/area-cliente-vistas.php';
require_once get_template_directory() . '/inc/area-cliente-push.php';
require_once get_template_directory() . '/inc/area-cliente-backoffice.php';
require_once get_template_directory() . '/inc/area-cliente-tarefas.php';
require_once get_template_directory() . '/inc/area-cliente-documentos.php';

// ─── Barra de admin do WP: desligada na Área de Cliente/Backoffice ────────
// Estas páginas têm a sua própria topbar (ac-topbar) — a barra nativa do
// WP só duplicava navegação e confundia quem não é administrador.

function cp_desligar_barra_admin( $mostrar ) {
    if ( is_page( 'area-de-cliente' ) || is_page( 'backoffice' ) ) return false;
    return $mostrar;
}
add_filter( 'show_admin_bar', 'cp_desligar_barra_admin' );

// ─── Configuração do tema ──────────────────────────────────────────────────

function cp_setup() {
    load_theme_textdomain( 'contabilidade-pro', get_template_directory() . '/languages' );

    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'html5', [
        'search-form', 'comment-form', 'comment-list',
        'gallery', 'caption', 'style', 'script',
    ] );
    add_theme_support( 'custom-logo', [
        'height'      => 52,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ] );
    add_theme_support( 'customize-selective-refresh-widgets' );

    register_nav_menus( [
        'primary' => __( 'Menu Principal', 'contabilidade-pro' ),
        'footer'  => __( 'Menu Rodapé', 'contabilidade-pro' ),
    ] );
}
add_action( 'after_setup_theme', 'cp_setup' );

// ─── Enqueue styles & scripts ─────────────────────────────────────────────

function cp_enqueue_assets() {
    wp_enqueue_style(
        'cp-google-fonts',
        'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Public+Sans:wght@400;500;600;700&display=swap',
        [],
        null
    );
    wp_enqueue_style(
        'cp-main',
        get_template_directory_uri() . '/assets/css/main.css',
        [ 'cp-google-fonts' ],
        '2.0.1-' . filemtime( get_template_directory() . '/assets/css/main.css' )
    );
    wp_enqueue_script(
        'cp-main',
        get_template_directory_uri() . '/assets/js/main.js',
        [],
        '2.0.1',
        true
    );
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }

    // Área de Cliente e Backoffice partilham o mesmo "app shell" (CSS,
    // alternador de tema, notificações push) — nunca carregado no site
    // público.
    if ( is_page( 'area-de-cliente' ) || is_page( 'backoffice' ) ) {
        wp_enqueue_style(
            'cp-area-cliente',
            get_template_directory_uri() . '/assets/css/area-cliente.css',
            [ 'cp-google-fonts' ],
            '1.0.0-' . filemtime( get_template_directory() . '/assets/css/area-cliente.css' )
        );
        if ( is_page( 'area-de-cliente' ) ) {
            wp_enqueue_script(
                'cp-pwa-install',
                get_template_directory_uri() . '/assets/js/pwa-install.js',
                [],
                '1.0.0',
                true
            );

            wp_enqueue_script(
                'cp-ac-fab',
                get_template_directory_uri() . '/assets/js/ac-fab.js',
                [],
                '1.0.0',
                true
            );
        }

        wp_enqueue_script(
            'cp-ac-theme-toggle',
            get_template_directory_uri() . '/assets/js/ac-theme-toggle.js',
            [],
            '1.0.0',
            true
        );

        wp_enqueue_script(
            'cp-ac-user-menu',
            get_template_directory_uri() . '/assets/js/ac-user-menu.js',
            [],
            '1.0.0',
            true
        );

        wp_enqueue_script(
            'cp-ac-mobile-nav',
            get_template_directory_uri() . '/assets/js/ac-mobile-nav.js',
            [],
            '1.0.0',
            true
        );

        $chaves_vapid = cp_push_vapid_keys();
        wp_enqueue_script(
            'cp-push',
            get_template_directory_uri() . '/assets/js/push.js',
            [],
            '1.0.0',
            true
        );
        wp_add_inline_script(
            'cp-push',
            'window.cpPush = ' . wp_json_encode( [
                'vapidPublicKey' => $chaves_vapid ? $chaves_vapid['public'] : '',
                'restUrl'        => esc_url_raw( rest_url( 'cp/v1' ) ),
                'restNonce'      => wp_create_nonce( 'wp_rest' ),
            ] ) . ';',
            'before'
        );
    }
}
add_action( 'wp_enqueue_scripts', 'cp_enqueue_assets' );

// ─── Widget areas ─────────────────────────────────────────────────────────

function cp_widgets_init() {
    $shared = [
        'before_widget' => '<div class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget__title">',
        'after_title'   => '</h4>',
    ];
    register_sidebar( array_merge( $shared, [
        'name'        => __( 'Rodapé', 'contabilidade-pro' ),
        'id'          => 'footer-1',
        'description' => __( 'Widgets visíveis no rodapé.', 'contabilidade-pro' ),
    ] ) );
    register_sidebar( array_merge( $shared, [
        'name'        => __( 'Barra Lateral', 'contabilidade-pro' ),
        'id'          => 'sidebar-1',
        'description' => __( 'Barra lateral para páginas e artigos.', 'contabilidade-pro' ),
    ] ) );
}
add_action( 'widgets_init', 'cp_widgets_init' );

// ─── Customizer ───────────────────────────────────────────────────────────

function cp_customizer( $wp_customize ) {

    // Painel: Informações do Gabinete
    $wp_customize->add_panel( 'cp_gabinete', [
        'title'    => __( 'Informações do Gabinete', 'contabilidade-pro' ),
        'priority' => 30,
    ] );

    // Secção: Contacto
    $wp_customize->add_section( 'cp_contact', [
        'title' => __( 'Contacto & Morada', 'contabilidade-pro' ),
        'panel' => 'cp_gabinete',
    ] );

    $contact_fields = [
        'cp_address'  => [ 'label' => __( 'Morada', 'contabilidade-pro' ),  'default' => 'Coimbra' ],
        'cp_phone'    => [ 'label' => __( 'Telefone', 'contabilidade-pro' ), 'default' => '+351 968 339 687' ],
        'cp_email'    => [ 'label' => __( 'E-mail', 'contabilidade-pro' ),   'default' => 'geral@enumeros.pt' ],
        'cp_linkedin'  => [ 'label' => __( 'LinkedIn URL', 'contabilidade-pro' ), 'default' => 'https://www.google.pt' ],
        'cp_instagram' => [ 'label' => __( 'Instagram URL', 'contabilidade-pro' ), 'default' => 'https://www.instagram.com/e.numeros' ],
        'cp_facebook'  => [ 'label' => __( 'Facebook URL', 'contabilidade-pro' ), 'default' => 'https://www.facebook.com/profile.php?id=100070119392664&locale=pt_PT' ],
        'cp_client_area_url' => [ 'label' => __( 'Área de Cliente — URL', 'contabilidade-pro' ), 'default' => home_url( '/area-de-cliente' ) ],
        'cp_cal_link'  => [ 'label' => __( 'Cal.com — link do evento (ex: enumeros/reuniao)', 'contabilidade-pro' ), 'default' => 'enumeros/reuniao' ],
    ];

    foreach ( $contact_fields as $id => $args ) {
        $wp_customize->add_setting( $id, [
            'default'           => $args['default'],
            'sanitize_callback' => 'sanitize_text_field',
        ] );
        $wp_customize->add_control( $id, [
            'label'   => $args['label'],
            'section' => 'cp_contact',
            'type'    => 'text',
        ] );
    }

    // Secção: Hero (apenas o botão — o texto do hero está fixo em front-page.php)
    $wp_customize->add_section( 'cp_hero', [
        'title' => __( 'Secção Hero (topo da página)', 'contabilidade-pro' ),
        'panel' => 'cp_gabinete',
    ] );
    $hero_fields = [
        'cp_hero_cta'      => [ 'label' => __( 'Texto do botão principal', 'contabilidade-pro' ), 'default' => 'Marcar reunião' ],
        'cp_hero_cta_url'  => [ 'label' => __( 'Link do botão (#contacto ou URL)', 'contabilidade-pro' ), 'default' => '#contacto' ],
    ];
    foreach ( $hero_fields as $id => $args ) {
        $wp_customize->add_setting( $id, [
            'default'           => $args['default'],
            'sanitize_callback' => 'wp_kses_post',
        ] );
        $wp_customize->add_control( $id, [
            'label'   => $args['label'],
            'section' => 'cp_hero',
            'type'    => 'text',
        ] );
    }

    // Secção: Sobre Nós
    $wp_customize->add_section( 'cp_about', [
        'title' => __( 'Secção Sobre Nós', 'contabilidade-pro' ),
        'panel' => 'cp_gabinete',
    ] );
    $about_fields = [
        'cp_about_text_1' => [
            'label'   => __( 'Sobre — parágrafo 1', 'contabilidade-pro' ),
            'default' => 'Trabalhamos pelo gosto que temos pela contabilidade — não por obrigação. É essa diferença que trazemos para cada cliente.',
        ],
        'cp_about_text_2' => [
            'label'   => __( 'Sobre — parágrafo 2', 'contabilidade-pro' ),
            'default' => 'Não ficamos só pelos números: construímos consigo os mapas de acompanhamento que fazem sentido para o seu negócio, e ajudamos a implementar as ferramentas de gestão e faturação que tornam esse acompanhamento possível no dia a dia.',
        ],
    ];
    foreach ( $about_fields as $id => $args ) {
        $wp_customize->add_setting( $id, [
            'default'           => $args['default'],
            'sanitize_callback' => 'wp_kses_post',
        ] );
        $wp_customize->add_control( $id, [
            'label'   => $args['label'],
            'section' => 'cp_about',
            'type'    => 'textarea',
        ] );
    }
}
add_action( 'customize_register', 'cp_customizer' );

// ─── Helper: obter opção do customizer ────────────────────────────────────

function cp_opt( $key, $fallback = '' ) {
    return get_theme_mod( $key, $fallback );
}

// ─── Ícone do site (favicon) ────────────────────────────────────────────

function cp_favicon() {
    if ( has_site_icon() ) return; // não duplicar se já houver um Site Icon definido no Customizer
    $dir  = get_template_directory() . '/assets/img/';
    $base = get_template_directory_uri() . '/assets/img/';
    $v32  = file_exists( $dir . 'favicon-32.png' ) ? '?v=' . filemtime( $dir . 'favicon-32.png' ) : '';
    $v192 = file_exists( $dir . 'favicon-192.png' ) ? '?v=' . filemtime( $dir . 'favicon-192.png' ) : '';
    echo '<link rel="icon" href="' . esc_url( $base . 'favicon-32.png' . $v32 ) . '" sizes="32x32">' . "\n";
    echo '<link rel="icon" href="' . esc_url( $base . 'favicon-192.png' . $v192 ) . '" sizes="192x192">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url( $base . 'favicon-192.png' . $v192 ) . '">' . "\n";
}
add_action( 'wp_head', 'cp_favicon' );
add_action( 'admin_head', 'cp_favicon' );

// ─── Excerpt ──────────────────────────────────────────────────────────────

function cp_excerpt_length( $length ) { return 25; }
add_filter( 'excerpt_length', 'cp_excerpt_length' );

function cp_excerpt_more( $more ) { return '…'; }
add_filter( 'excerpt_more', 'cp_excerpt_more' );

// ─── Body classes ─────────────────────────────────────────────────────────

function cp_body_classes( $classes ) {
    if ( is_singular() ) $classes[] = 'singular';
    if ( ! is_active_sidebar( 'sidebar-1' ) ) $classes[] = 'no-sidebar';
    return $classes;
}
add_filter( 'body_class', 'cp_body_classes' );

// ─── Registo de utilizadores fechado ao público ────────────────────────────
// Só é criada conta por quem gere o site (wp-admin → Utilizadores →
// Adicionar). Isto garante que fica fechado mesmo que a opção em
// Definições → Geral → "Qualquer pessoa pode registar-se" seja ligada
// sem querer. A recuperação de password em wp-login.php continua disponível.

add_filter( 'option_users_can_register', '__return_zero' );

function cp_bloquear_registo_publico() {
    if ( isset( $_GET['action'] ) && 'register' === $_GET['action'] ) {
        wp_safe_redirect( wp_login_url() );
        exit;
    }
}
add_action( 'login_init', 'cp_bloquear_registo_publico' );

// ─── Site fechado ao público (apenas Área de Cliente acessível) ───────────
// Enquanto os textos não estão aprovados, qualquer visitante não autenticado
// só vê um aviso de "Em construção" na página inicial, com um atalho de
// teclado escondido para a Área de Cliente (para quem já tem o link enviado
// por nós). Todo o resto do site redireciona para a página inicial.
// Administradores autenticados navegam normalmente, para poderem rever o
// site completo antes do lançamento.

function cp_portao_site_publico() {
    if ( is_admin() ) return;
    if ( is_user_logged_in() ) return;

    // Área de Cliente (e as suas ?vista=) e o Backoffice da equipa estão
    // sempre acessíveis, mesmo sem sessão iniciada (mostram o próprio
    // ecrã de login).
    $pedido = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( false !== strpos( $pedido, 'area-de-cliente' ) || false !== strpos( $pedido, 'backoffice' ) ) return;

    // Página inicial: mostra apenas o aviso "Em construção" (com atalho
    // de teclado escondido para a Área de Cliente), até os textos reais
    // serem aprovados.
    if ( is_front_page() ) {
        cp_pagina_em_construcao();
        exit;
    }

    // Todo o resto do site: redireciona para a página inicial.
    wp_safe_redirect( home_url( '/' ) );
    exit;
}
add_action( 'template_redirect', 'cp_portao_site_publico' );

function cp_pagina_em_construcao() {
    $url_area_cliente = esc_url( home_url( '/area-de-cliente/' ) );
    ?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entre Números — Em construção</title>
<style>
    html, body {
        height: 100%;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #0f1115;
        color: #f5f5f0;
        font-family: 'Public Sans', Arial, sans-serif;
        text-align: center;
    }
    .cp-ec { padding: 2rem; max-width: 32rem; }
    .cp-ec img {
        max-width: min(90vw, 28rem);
        height: auto;
        margin: 0 0 1rem;
    }
    .cp-ec p {
        opacity: 1;
        margin: 0;
        font-size: 1.15rem;
        font-weight: 500;
        line-height: 1.5;
        letter-spacing: .02em;
        color: #c9d2ea;
    }
    .cp-ec p.cp-ec-sub {
        margin-top: .4rem;
        font-size: .95rem;
        font-weight: 400;
        opacity: .6;
        color: #f5f5f0;
        letter-spacing: .01em;
    }
</style>
</head>
<body>
<div class="cp-ec">
    <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/banner.png' ); ?>" alt="Entre Números">
    <p>Algo novo está a nascer.</p>
    <p class="cp-ec-sub">Contabilidade, fiscalidade e recursos humanos, num só lugar.</p>
</div>
<script>
document.addEventListener( 'keydown', function ( e ) {
    if ( e.ctrlKey && e.altKey && e.shiftKey && e.key.toLowerCase() === 'a' ) {
        window.location.href = '<?php echo $url_area_cliente; ?>';
    }
} );
</script>
</body>
</html>
    <?php
}

// ─── Página de início de sessão personalizada ──────────────────────────────
// Dá à página de login (wp-login.php) a imagem da marca em vez do ecrã
// genérico do WordPress: mesma paleta escura e tipografia da página
// "Em construção".

function cp_login_estilos() {
    ?>
    <style>
        body.login {
            background: #0f1115;
            font-family: 'Public Sans', Arial, sans-serif;
        }
        body.login #login h1 a {
            background-image: none;
            width: auto;
            height: auto;
            text-indent: 0;
            overflow: visible;
            font-family: Georgia, 'Fraunces', serif;
            font-weight: 500;
            font-size: 1.8rem;
            color: #f5f5f0;
            text-align: center;
            display: block;
            pointer-events: none;
        }
        body.login #login {
            width: 25rem;
        }
        body.login #login form,
        body.login #login .message,
        body.login #login #backtoblog,
        body.login #login #nav {
            background: #181b21;
            border: 1px solid #2a2e37;
            border-radius: 12px;
        }
        body.login #login #backtoblog,
        body.login #login #nav {
            background: transparent;
            border: none;
            text-align: center;
        }
        body.login #login form {
            box-shadow: 0 8px 28px rgba(0,0,0,.35);
            padding: 2rem;
        }
        body.login #login label,
        body.login #login #backtoblog a,
        body.login #login #nav a {
            color: #cfd1d6;
        }
        body.login .input,
        body.login input[type="text"],
        body.login input[type="password"] {
            background: #0f1115;
            border: 1px solid #2a2e37;
            color: #f5f5f0;
            box-shadow: none;
            font-size: 16px;
            padding: 10px 14px;
        }
        body.login .input:focus,
        body.login input[type="text"]:focus,
        body.login input[type="password"]:focus {
            border-color: #8b8fff;
            box-shadow: 0 0 0 1px #8b8fff;
        }
        body.login .wp-core-ui .button-primary {
            background: #6c63ff;
            border-color: #6c63ff;
            box-shadow: none;
            text-shadow: none;
            border-radius: 8px;
        }
        body.login .wp-core-ui .button-primary:hover,
        body.login .wp-core-ui .button-primary:focus {
            background: #574fe0;
            border-color: #574fe0;
        }
        body.login #login_error,
        body.login .message {
            border-left-color: #6c63ff;
            background: #181b21;
            color: #f5f5f0;
        }
        body.login #nav a,
        body.login #backtoblog a {
            text-decoration: none;
        }
        body.login #nav a:hover,
        body.login #backtoblog a:hover {
            color: #f5f5f0;
        }
    </style>
    <?php
}
add_action( 'login_enqueue_scripts', 'cp_login_estilos' );

function cp_login_logo_url() {
    return home_url( '/' );
}
add_filter( 'login_headerurl', 'cp_login_logo_url' );

function cp_login_logo_url_title() {
    return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'cp_login_logo_url_title' );

// ─── Formulário de contacto (sem plugin) ───────────────────────────────────

function cp_handle_contact_form() {
    if ( ! isset( $_POST['cp_nonce'] ) || ! wp_verify_nonce( $_POST['cp_nonce'], 'cp_contact_form' ) ) {
        wp_die( esc_html__( 'Pedido inválido. Volta à página e tenta novamente.', 'contabilidade-pro' ) );
    }

    $name    = isset( $_POST['cf_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cf_name'] ) ) : '';
    $email   = isset( $_POST['cf_email'] ) ? sanitize_email( wp_unslash( $_POST['cf_email'] ) ) : '';
    $message = isset( $_POST['cf_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cf_message'] ) ) : '';

    if ( $name && is_email( $email ) ) {
        $to      = cp_opt( 'cp_email', get_option( 'admin_email' ) );
        $subject = sprintf( /* translators: %s: sender name */ __( 'Novo contacto do site — %s', 'contabilidade-pro' ), $name );
        $body    = "Nome: {$name}\nE-mail: {$email}\n\nMensagem:\n{$message}";
        $headers = [ 'Reply-To: ' . $name . ' <' . $email . '>' ];
        wp_mail( $to, $subject, $body, $headers );
    }

    wp_safe_redirect( home_url( '/#contacto' ) . '?contacto=enviado' );
    exit;
}
add_action( 'admin_post_cp_contact', 'cp_handle_contact_form' );
add_action( 'admin_post_nopriv_cp_contact', 'cp_handle_contact_form' );
