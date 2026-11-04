<?php get_header(); ?>

<main id="main" class="site-main blog-main">
    <div class="container blog-container">

        <header class="blog-header">
            <?php if ( is_home() && ! is_front_page() ) : ?>
                <h1 class="blog-header__title"><?php single_post_title(); ?></h1>
            <?php elseif ( is_search() ) : ?>
                <h1 class="blog-header__title">
                    <?php printf( esc_html__( 'Resultados para: %s', 'contabilidade-pro' ), '<span>' . get_search_query() . '</span>' ); ?>
                </h1>
            <?php elseif ( is_archive() ) : ?>
                <?php the_archive_title( '<h1 class="blog-header__title">', '</h1>' ); ?>
                <?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
            <?php endif; ?>
        </header>

        <div class="blog-grid">
            <?php if ( have_posts() ) : ?>
                <?php while ( have_posts() ) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
                    <?php if ( has_post_thumbnail() ) : ?>
                    <a class="post-card__thumb" href="<?php the_permalink(); ?>">
                        <?php the_post_thumbnail( 'medium_large' ); ?>
                    </a>
                    <?php endif; ?>
                    <div class="post-card__body">
                        <div class="post-card__meta">
                            <time datetime="<?php echo get_the_date( 'c' ); ?>">
                                <?php echo get_the_date( 'd M Y' ); ?>
                            </time>
                        </div>
                        <h2 class="post-card__title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h2>
                        <p class="post-card__excerpt"><?php the_excerpt(); ?></p>
                        <a class="btn btn--outline btn--sm" href="<?php the_permalink(); ?>">
                            <?php esc_html_e( 'Ler mais', 'contabilidade-pro' ); ?>
                        </a>
                    </div>
                </article>
                <?php endwhile; ?>
            <?php else : ?>
                <p class="no-posts"><?php esc_html_e( 'Ainda não há artigos publicados.', 'contabilidade-pro' ); ?></p>
            <?php endif; ?>
        </div>

        <?php the_posts_navigation(); ?>

    </div>
</main>

<?php get_footer(); ?>
