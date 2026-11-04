<?php
/**
 * Entre Números — functions.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Área de Cliente: empresas, ferramentas e matriz de acesso ────────────
require_once get_template_directory() . '/inc/area-cliente.php';
require_once get_template_directory() . '/inc/area-cliente-vistas.php';

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
