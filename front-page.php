<?php
/** Homepage hero, used for both latest-posts and static front-page settings. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$hero = herospark_content();
get_header();
?>
<main id="main-content" class="herospark-homepage" tabindex="-1">
    <section class="herospark-hero" aria-labelledby="hero-title">
        <div class="herospark-hero-copy">
            <p class="herospark-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( 'Something great starts here', 'herospark' ); ?></p>
            <h1 id="hero-title"><?php echo esc_html( $hero['title'] ); ?></h1>
            <p class="herospark-hero-paragraph"><?php echo esc_html( $hero['paragraph'] ); ?></p>
            <div class="herospark-hero-signature"><span class="herospark-signature-line" aria-hidden="true"></span><?php esc_html_e( 'Small beginnings. Endless possibilities.', 'herospark' ); ?></div>
        </div>
        <div class="herospark-hero-art" aria-hidden="true">
            <div class="herospark-art-grid"></div>
            <div class="herospark-orbit herospark-orbit-one"></div><div class="herospark-orbit herospark-orbit-two"></div>
            <span class="herospark-art-top">A NEW PERSPECTIVE</span>
            <div class="herospark-spark">✳</div>
            <span class="herospark-art-dot herospark-dot-one"></span><span class="herospark-art-dot herospark-dot-two"></span>
            <div class="herospark-art-bottom"><span>Ideas into<br>possibilities.</span><span class="herospark-art-arrow">↗</span></div>
        </div>
    </section>
    <div class="herospark-homepage-note"><span><?php esc_html_e( 'A fresh start', 'herospark' ); ?></span><span><?php esc_html_e( 'Thoughtful words. Meaningful connections.', 'herospark' ); ?></span><span aria-hidden="true">✳</span></div>
</main>
<?php get_footer(); ?>
