<?php
/** Content Studio and private Gemini credential storage. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function herospark_admin_menu() {
    add_theme_page( __( 'Content Studio', 'herospark' ), __( 'Content Studio', 'herospark' ), 'manage_options', 'herospark', 'herospark_settings_page' );
}
add_action( 'admin_menu', 'herospark_admin_menu' );

function herospark_admin_styles( $hook ) {
    if ( 'appearance_page_herospark' !== $hook ) {
        return;
    }
    wp_enqueue_style( 'herospark-studio', get_theme_file_uri( '/assets/css/admin.css' ), array(), wp_get_theme()->get( 'Version' ) );
    wp_enqueue_script( 'herospark-studio', get_theme_file_uri( '/assets/js/admin.js' ), array(), wp_get_theme()->get( 'Version' ), true );
    wp_localize_script( 'herospark-studio', 'herosparkStudio', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'herospark_generate' ),
        'emptyBrief' => __( 'Enter your project context before generating.', 'herospark' ),
        'generating' => __( 'Generating…', 'herospark' ),
        'failure' => __( 'Could not read the generation result. Reload Content Studio to check whether it was saved before trying again.', 'herospark' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'herospark_admin_styles' );

function herospark_config_key() {
    return defined( 'HEROSPARK_GEMINI_API_KEY' ) && is_string( HEROSPARK_GEMINI_API_KEY ) ? trim( HEROSPARK_GEMINI_API_KEY ) : '';
}

/** Server-side use only. Never print the returned credential. */
function herospark_get_api_key() {
    $configured = herospark_config_key();
    if ( '' !== $configured ) {
        return $configured;
    }
    $saved = get_option( 'herospark_gemini_api_key', '' );
    return is_string( $saved ) ? $saved : '';
}

function herospark_save_api_key() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage this connection.', 'herospark' ), '', array( 'response' => 403 ) );
    }
    if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
        wp_die( esc_html__( 'Please submit the connection form.', 'herospark' ), '', array( 'response' => 405 ) );
    }
    check_admin_referer( 'herospark_save_api_key', 'herospark_api_nonce' );

    $status = 'unchanged';
    if ( '' !== herospark_config_key() ) {
        $status = 'managed';
    } elseif ( isset( $_POST['herospark_remove_key'] ) && '1' === $_POST['herospark_remove_key'] ) {
        delete_option( 'herospark_gemini_api_key' );
        $status = 'removed';
    } else {
        $key = isset( $_POST['herospark_api_key'] ) && is_string( $_POST['herospark_api_key'] ) ? trim( wp_unslash( $_POST['herospark_api_key'] ) ) : '';
        if ( '' !== $key ) {
            // Accept opaque token formats, including longer Gemini authorization keys.
            // Reject whitespace and control characters; Google verifies authenticity later.
            if ( preg_match( '/\A[A-Za-z0-9._~+\/=-]{20,4096}\z/', $key ) ) {
                update_option( 'herospark_gemini_api_key', $key, false );
                $status = 'saved';
            } else {
                $status = 'invalid';
            }
        }
    }
    wp_safe_redirect( add_query_arg( array( 'page' => 'herospark', 'connection' => $status ), admin_url( 'themes.php' ) ) );
    exit;
}
add_action( 'admin_post_herospark_save_api_key', 'herospark_save_api_key' );

function herospark_connection_notice() {
    $status = isset( $_GET['connection'] ) && is_string( $_GET['connection'] ) ? sanitize_key( wp_unslash( $_GET['connection'] ) ) : '';
    $messages = array(
        'saved' => __( 'API key saved. Enter your project brief and choose Generate with Gemini to use it.', 'herospark' ),
        'removed' => __( 'The saved API key has been removed.', 'herospark' ),
        'unchanged' => __( 'Your saved connection has been kept.', 'herospark' ),
        'managed' => __( 'This connection is managed through your site configuration.', 'herospark' ),
        'invalid' => __( 'Paste the complete key copied from Google AI Studio, without spaces or quotation marks (20–4096 characters). Your previous key has been kept.', 'herospark' ),
    );
    if ( isset( $messages[ $status ] ) ) {
        $class = 'invalid' === $status ? 'notice notice-error' : 'notice notice-success';
        echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $status ] ) . '</p></div>';
    }
}

function herospark_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $content = herospark_content();
    $managed = '' !== herospark_config_key();
    $has_key = '' !== herospark_get_api_key();
    ?>
    <div class="wrap herospark-studio">
        <div class="hs-studio-heading">
            <div><p class="hs-studio-brand"><?php esc_html_e( 'HEROSPARK', 'herospark' ); ?></p>
            <h1><?php esc_html_e( 'Content Studio', 'herospark' ); ?></h1>
            <p class="hs-studio-intro"><?php esc_html_e( 'Shape your homepage, from the first idea to the final words.', 'herospark' ); ?></p></div>
            <a class="button hs-view-site" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View homepage ↗', 'herospark' ); ?></a>
        </div>
        <?php settings_errors(); ?>
        <?php herospark_connection_notice(); ?>
        <?php herospark_history_notice(); ?>
        <div id="hs-generation-result" role="status" aria-live="polite" hidden><p></p></div>
        <div class="hs-studio-grid">
            <form method="post" action="options.php" class="hs-content-form">
                <?php settings_fields( 'herospark_settings' ); ?>
                <section class="hs-card" aria-labelledby="hs-context-heading">
                    <div class="hs-card-heading"><span class="hs-step" aria-hidden="true">01</span><div><h2 id="hs-context-heading"><?php esc_html_e( 'Project brief', 'herospark' ); ?></h2><p><?php esc_html_e( 'A little context helps your content find its direction.', 'herospark' ); ?></p></div></div>
                    <label for="herospark-context"><?php esc_html_e( 'Project context & keywords', 'herospark' ); ?></label>
                    <textarea rows="5" maxlength="4000" id="herospark-context" name="herospark_content[context]" aria-describedby="hs-context-help" placeholder="<?php esc_attr_e( 'Example: A local coffee shop serving organic coffee and fresh pastries. Audience: neighbourhood families. Tone: warm and welcoming.', 'herospark' ); ?>"><?php echo esc_textarea( $content['context'] ); ?></textarea>
                    <p class="hs-help" id="hs-context-help"><?php esc_html_e( 'Include your business, audience, services, and preferred tone.', 'herospark' ); ?></p>
                    <div class="hs-generation"><button type="button" id="hs-generate" class="button button-primary" <?php disabled( ! $has_key ); ?> aria-describedby="hs-generation-help"><?php esc_html_e( 'Generate with Gemini', 'herospark' ); ?></button></div>
                    <p class="hs-help" id="hs-generation-help"><?php esc_html_e( 'Generating sends this brief to Google and replaces your saved hero title and paragraph. You can edit the result below. Save an API key first if the button is disabled.', 'herospark' ); ?></p>
                <noscript><p class="hs-help">JavaScript is required for AI generation. Manual editing remains available.</p></noscript>
                </section>
                <section class="hs-card" aria-labelledby="hs-content-heading">
                    <div class="hs-card-heading"><span class="hs-step" aria-hidden="true">02</span><div><h2 id="hs-content-heading"><?php esc_html_e( 'Homepage content', 'herospark' ); ?></h2><p><?php esc_html_e( 'Make your first impression clear and memorable.', 'herospark' ); ?></p></div></div>
                    <label for="herospark-title"><?php esc_html_e( 'Hero title', 'herospark' ); ?></label>
                    <input type="text" id="herospark-title" name="herospark_content[title]" value="<?php echo esc_attr( $content['title'] ); ?>" required>
                    <label for="herospark-paragraph" class="hs-field-spacing"><?php esc_html_e( 'Introductory paragraph', 'herospark' ); ?></label>
                    <textarea rows="5" id="herospark-paragraph" name="herospark_content[paragraph]" aria-describedby="hs-content-help" required><?php echo esc_textarea( $content['paragraph'] ); ?></textarea>
                    <p class="hs-help" id="hs-content-help"><?php esc_html_e( 'Saved changes appear directly in your homepage hero.', 'herospark' ); ?></p>
                    <div class="hs-card-actions"><?php submit_button( __( 'Save homepage content', 'herospark' ), 'primary', 'submit', false ); ?></div>
                </section>
            </form>
            <aside class="hs-sidebar" aria-label="<?php esc_attr_e( 'AI connection', 'herospark' ); ?>">
                <section class="hs-card" aria-labelledby="hs-connection-heading">
                    <div class="hs-connection-icon" aria-hidden="true">✳</div>
                    <h2 id="hs-connection-heading"><?php esc_html_e( 'AI connection', 'herospark' ); ?></h2>
                    <p class="hs-help"><?php esc_html_e( 'Connect your own Google Gemini API key.', 'herospark' ); ?></p>
                    <p class="hs-connection-status"><span class="hs-status-dot <?php echo $has_key ? 'hs-status-saved' : ''; ?>" aria-hidden="true"></span><?php echo esc_html( $managed ? __( 'Managed in site configuration', 'herospark' ) : ( $has_key ? __( 'Key saved', 'herospark' ) : __( 'No API key saved', 'herospark' ) ) ); ?></p>
                    <?php if ( $managed ) : ?>
                        <p class="hs-help"><?php esc_html_e( 'Your site configuration provides the API key. Update or remove it there to use this field instead.', 'herospark' ); ?></p>
                    <?php else : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
                            <input type="hidden" name="action" value="herospark_save_api_key">
                            <?php wp_nonce_field( 'herospark_save_api_key', 'herospark_api_nonce' ); ?>
                            <label for="herospark-api-key"><?php echo esc_html( $has_key ? __( 'Replace API key', 'herospark' ) : __( 'Gemini API key', 'herospark' ) ); ?></label>
                            <input type="password" id="herospark-api-key" name="herospark_api_key" value="" maxlength="4096" autocomplete="new-password" spellcheck="false" autocapitalize="none" aria-describedby="hs-key-help">
                            <p class="hs-help" id="hs-key-help"><?php esc_html_e( 'Leave blank to keep your saved key. The stored key is never displayed here.', 'herospark' ); ?></p>
                            <?php if ( $has_key ) : ?>
                                <label class="hs-checkbox"><input type="checkbox" name="herospark_remove_key" value="1"> <?php esc_html_e( 'Remove saved key', 'herospark' ); ?></label>
                            <?php endif; ?>
                            <button type="submit" class="button button-secondary hs-save-connection"><?php esc_html_e( 'Save connection', 'herospark' ); ?></button>
                        </form>
                    <?php endif; ?>
                    <p class="hs-key-link"><a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get a Gemini API key ↗', 'herospark' ); ?></a></p>
                    <p class="hs-help hs-connection-note"><?php esc_html_e( 'Your key stays on the server. Google receives your project brief only when you generate. Usage is subject to your Google project’s quota and billing settings.', 'herospark' ); ?></p>
                </section>
                <section class="hs-card hs-tip"><h2><?php esc_html_e( 'Start with your audience', 'herospark' ); ?></h2><p class="hs-help"><?php esc_html_e( 'The best introductions explain who you help, what you offer, and why it matters. Keep your headline short and your paragraph focused.', 'herospark' ); ?></p></section>
            </aside>
        </div>
        <div id="hs-history-container"><?php echo herospark_history_html(); // All dynamic values are escaped in the renderer. ?></div>
    </div>
    <?php
}
