<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="https://gmpg.org/xfn/11">
<script>
(function () {
    try {
        var stored = localStorage.getItem( 'cp-theme' );
        var theme = stored || ( window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light' );
        document.documentElement.setAttribute( 'data-theme', theme );
    } catch ( e ) {}
})();
</script>
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main">
    <?php esc_html_e( 'Saltar para o conteúdo', 'contabilidade-pro' ); ?>
</a>

<header class="site-header" id="site-header">
    <div class="site-header__inner container">

        <div class="site-header__brand">
            <?php if ( has_custom_logo() ) : ?>
                <div class="site-header__logo"><?php the_custom_logo(); ?></div>
            <?php else : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-header__logo" rel="home">
                    <?php
                    $cp_logo_path = get_template_directory() . '/assets/img/logo.png';
                    $cp_logo_src  = get_template_directory_uri() . '/assets/img/logo.png';
                    if ( file_exists( $cp_logo_path ) ) {
                        $cp_logo_src .= '?v=' . filemtime( $cp_logo_path );
                    }
                    ?>
                    <img src="<?php echo esc_url( $cp_logo_src ); ?>" alt="<?php bloginfo( 'name' ); ?>" height="52">
                </a>
            <?php endif; ?>
        </div>

        <nav class="site-nav" id="site-nav" aria-label="<?php esc_attr_e( 'Menu principal', 'contabilidade-pro' ); ?>">
            <?php
            wp_nav_menu( [
                'theme_location'  => 'primary',
                'menu_id'         => 'primary-menu',
                'container'       => false,
                'menu_class'      => 'site-nav__list',
                'fallback_cb'     => 'cp_fallback_menu',
            ] );
            ?>
            <a href="#contacto" class="btn btn--primary site-nav__cta">
                <?php esc_html_e( 'Marcar reunião', 'contabilidade-pro' ); ?>
            </a>
        </nav>

        <div class="site-header__actions">
            <a href="#contacto" class="btn btn--primary btn--sm">
                <?php esc_html_e( 'Marcar reunião', 'contabilidade-pro' ); ?>
            </a>
            <a href="<?php echo esc_url( cp_opt( 'cp_client_area_url', home_url( '/area-de-cliente' ) ) ); ?>" class="btn btn--outline btn--sm" id="cp-client-area-link" hidden>
                <?php esc_html_e( 'Área de Cliente', 'contabilidade-pro' ); ?>
            </a>
            <button class="theme-toggle" id="theme-toggle" aria-pressed="false" aria-label="<?php esc_attr_e( 'Alternar modo escuro', 'contabilidade-pro' ); ?>">
                <svg class="theme-toggle__icon theme-toggle__icon--sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                <svg class="theme-toggle__icon theme-toggle__icon--moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
            <button class="hamburger" id="hamburger" aria-controls="site-nav" aria-expanded="false" aria-label="<?php esc_attr_e( 'Abrir menu', 'contabilidade-pro' ); ?>">
                <span></span><span></span><span></span>
            </button>
        </div>

    </div>
</header>

<script>
(function () {
    try {
        var link = document.getElementById( 'cp-client-area-link' );
        if ( ! link ) return;
        var CHAVE = 'cp-ac-revelado';
        function revelar() {
            link.hidden = false;
            try { localStorage.setItem( CHAVE, '1' ); } catch ( e ) {}
        }
        if ( localStorage.getItem( CHAVE ) === '1' ) revelar();
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.ctrlKey && e.altKey && e.shiftKey && ( e.key === 'A' || e.key === 'a' ) ) revelar();
        } );
    } catch ( e ) {}
})();
</script>

<?php

function cp_fallback_menu() {
    echo '<ul class="site-nav__list">';
    echo '<li><a href="' . esc_url( home_url( '/servicos' ) ) . '">Serviços</a></li>';
    echo '<li><a href="' . esc_url( home_url( '/#sobre' ) ) . '">Sobre</a></li>';
    echo '<li><a href="' . esc_url( home_url( '/#contacto' ) ) . '">Contactos</a></li>';
    echo '</ul>';
}
