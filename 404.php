<?php get_header(); ?>

<main id="main" class="site-main page-main">
    <div class="container" style="text-align:center; padding: 6rem clamp(20px,5vw,80px);">
        <h1 style="font-family: var(--font-serif); font-size: clamp(4rem, 15vw, 8rem); color: var(--c-accent); line-height:1; margin-bottom: 0;">404</h1>
        <h2 style="margin-bottom: 1rem; font-family: var(--font-serif); color: var(--c-navy);"><?php esc_html_e( 'Página não encontrada', 'contabilidade-pro' ); ?></h2>
        <p style="color: #5B6478; margin-bottom: 2rem;">
            <?php esc_html_e( 'A página que procura não existe ou foi movida.', 'contabilidade-pro' ); ?>
        </p>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn--primary">
            <?php esc_html_e( '← Voltar ao Início', 'contabilidade-pro' ); ?>
        </a>
    </div>
</main>

<?php get_footer(); ?>
