<?php
/**
 * Barra de ícones de redes sociais + WhatsApp.
 * Usar: get_template_part( 'template-parts/social-icons' );
 */
?>
<div class="contact-social-row">
    <?php $cp_linkedin = cp_opt( 'cp_linkedin', 'https://www.google.pt' ); if ( $cp_linkedin ) : ?>
    <a href="<?php echo esc_url( $cp_linkedin ); ?>" class="contact-social" target="_blank" rel="noopener" aria-label="LinkedIn">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
    </a>
    <?php endif; ?>

    <?php $cp_instagram = cp_opt( 'cp_instagram', 'https://www.instagram.com/e.numeros' ); if ( $cp_instagram ) : ?>
    <a href="<?php echo esc_url( $cp_instagram ); ?>" class="contact-social" target="_blank" rel="noopener" aria-label="Instagram">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
    </a>
    <?php endif; ?>

    <?php $cp_facebook = cp_opt( 'cp_facebook', 'https://www.facebook.com/profile.php?id=100070119392664&locale=pt_PT' ); if ( $cp_facebook ) : ?>
    <a href="<?php echo esc_url( $cp_facebook ); ?>" class="contact-social" target="_blank" rel="noopener" aria-label="Facebook">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 10-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0022 12z"/></svg>
    </a>
    <?php endif; ?>

</div>
