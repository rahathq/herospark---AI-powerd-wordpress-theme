<?php
/** Theme setup and homepage settings. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function herospark_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
}
add_action( 'after_setup_theme', 'herospark_setup' );

function herospark_styles() {
    wp_enqueue_style( 'herospark', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
}
add_action( 'wp_enqueue_scripts', 'herospark_styles' );

function herospark_defaults() {
    return array(
        'context' => '',
        'title' => 'Big ideas deserve a bright beginning.',
        'paragraph' => 'Every great project starts with a spark. Turn your next idea into something thoughtful, memorable, and made to connect with the people who matter.',
    );
}

function herospark_content() {
    $saved = get_option( 'herospark_content', array() );
    return wp_parse_args( is_array( $saved ) ? $saved : array(), herospark_defaults() );
}

function herospark_sanitize_content( $input ) {
    $previous = herospark_content();
    if ( ! is_array( $input ) ) {
        return $previous;
    }
    $clean = array();
    foreach ( array( 'context', 'title', 'paragraph' ) as $key ) {
        $value = isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? $input[ $key ] : $previous[ $key ];
        $clean[ $key ] = 'title' === $key ? sanitize_text_field( $value ) : sanitize_textarea_field( $value );
    }
    if ( '' === trim( $clean['title'] ) || '' === trim( $clean['paragraph'] ) ) {
        add_settings_error( 'herospark_content', 'herospark_empty', __( 'Please enter both a hero title and paragraph. Your previous content has been kept.', 'herospark' ), 'error' );
        return $previous;
    }
    return $clean;
}

function herospark_register_settings() {
    register_setting( 'herospark_settings', 'herospark_content', array(
        'type' => 'array',
        'sanitize_callback' => 'herospark_sanitize_content',
        'default' => herospark_defaults(),
        'show_in_rest' => false,
    ) );
}
add_action( 'admin_init', 'herospark_register_settings' );

require_once get_theme_file_path( '/inc/history.php' );
require_once get_theme_file_path( '/inc/content-studio.php' );
require_once get_theme_file_path( '/inc/gemini.php' );
