<footer class="site-footer" id="site-footer">
    <div class="site-footer__bottom">
        <div class="container">
            <p>&copy; <?php echo date_i18n( 'Y' ); ?> <?php bloginfo( 'name' ); ?>, Lda.</p>

            <div class="footer-right">
                <?php
                if ( has_nav_menu( 'footer' ) ) {
                    wp_nav_menu( [
                        'theme_location' => 'footer',
                        'menu_id'        => 'footer-menu',
                        'container'      => false,
                        'menu_class'     => 'footer-nav',
                        'depth'          => 1,
                        'fallback_cb'    => false,
                    ] );
                } else {
                    echo '<a href="' . esc_url( home_url( '/politica-de-privacidade' ) ) . '" class="footer-nav__link">' . esc_html__( 'Política de Privacidade', 'contabilidade-pro' ) . '</a>';
                }
                ?>
            </div>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
