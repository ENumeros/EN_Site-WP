<?php get_header(); ?>

<main id="main" class="site-main single-main">
    <div class="container single-container">

        <?php while ( have_posts() ) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class( 'single-article' ); ?>>
            <header class="single-header">
                <div class="single-header__meta">
                    <time datetime="<?php echo get_the_date( 'c' ); ?>">
                        <?php echo get_the_date( 'd \d\e F \d\e Y' ); ?>
                    </time>
                    <?php the_category( ' · ' ); ?>
                </div>
                <h1 class="single-header__title"><?php the_title(); ?></h1>
            </header>

            <?php if ( has_post_thumbnail() ) : ?>
            <div class="single-thumbnail">
                <?php the_post_thumbnail( 'large' ); ?>
            </div>
            <?php endif; ?>

            <div class="single-content entry-content">
                <?php the_content(); ?>
            </div>

            <footer class="single-footer">
                <?php the_tags( '<div class="single-tags">', ', ', '</div>' ); ?>
                <div class="single-nav">
                    <?php previous_post_link( '<div class="single-nav__prev">%link</div>', '&larr; %title' ); ?>
                    <?php next_post_link( '<div class="single-nav__next">%link</div>', '%title &rarr;' ); ?>
                </div>
            </footer>
        </article>

        <?php endwhile; ?>

    </div>
</main>

<?php get_footer(); ?>
