<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="herospark-skip-link" href="#main-content"><?php esc_html_e( 'Skip to content', 'herospark' ); ?></a>
<div class="herospark-site-shell">
    <header class="herospark-site-header">
        <a class="herospark-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><span class="herospark-brand-icon" aria-hidden="true">✳</span><span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span></a>
        <span class="herospark-header-note"><?php esc_html_e( 'A little spark. A world of possibility.', 'herospark' ); ?></span>
    </header>
