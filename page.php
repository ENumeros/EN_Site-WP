<?php get_header(); ?>

<main id="main" class="site-main page-main">
    <div class="container page-container">

        <?php while ( have_posts() ) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class( 'page-article' ); ?>>
            <header class="page-header">
                <h1 class="page-header__title"><?php the_title(); ?></h1>
            </header>
            <div class="page-content entry-content">
                <?php the_content(); ?>
                <?php
                wp_link_pages( [
                    'before' => '<div class="page-links">' . esc_html__( 'Páginas:', 'contabilidade-pro' ),
                    'after'  => '</div>',
                ] );
                ?>
            </div>
        </article>

        <?php endwhile; ?>

    </div>
</main>

<?php get_footer(); ?>
