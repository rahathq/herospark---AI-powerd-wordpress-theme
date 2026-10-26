<?php
/** Fallback template for posts, archives, pages, and search. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
get_header();
?>
<main id="main-content" class="herospark-content-area" tabindex="-1">
    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <article <?php post_class( 'herospark-entry' ); ?> id="post-<?php the_ID(); ?>">
                <?php if ( is_singular() ) : ?>
                    <h1><?php the_title(); ?></h1>
                    <div class="herospark-entry-content"><?php the_content(); wp_link_pages(); ?></div>
                <?php else : ?>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php the_excerpt(); ?>
                <?php endif; ?>
            </article>
        <?php endwhile; ?>
        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <h1><?php esc_html_e( 'Nothing here just yet.', 'herospark' ); ?></h1>
        <p><?php esc_html_e( 'Try a search or head back to the homepage.', 'herospark' ); ?></p>
        <?php get_search_form(); ?>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
