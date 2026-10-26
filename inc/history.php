<?php
/** Previous homepage versions, stored separately from API credentials. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Copy only content fields; never archive arbitrary options or credentials. */
function herospark_history_content( $value ) {
    if ( ! is_array( $value ) ) {
        return null;
    }
    $content = array();
    foreach ( array( 'context', 'title', 'paragraph' ) as $field ) {
        if ( ! isset( $value[ $field ] ) || ! is_string( $value[ $field ] ) ) {
            return null;
        }
        $content[ $field ] = $value[ $field ];
    }
    return $content;
}

function herospark_content_fingerprint() {
    return hash( 'sha256', wp_json_encode( herospark_history_content( herospark_content() ) ) );
}

function herospark_get_history() {
    $saved = get_option( 'herospark_content_history', array() );
    if ( ! is_array( $saved ) ) {
        return array();
    }
    $history = array();
    foreach ( $saved as $entry ) {
        if ( ! is_array( $entry ) || ! isset( $entry['id'], $entry['archived_at'], $entry['content'] ) || ! is_string( $entry['id'] ) || ! preg_match( '/\A[a-f0-9-]{36}\z/', $entry['id'] ) || ! is_int( $entry['archived_at'] ) ) {
            continue;
        }
        $content = herospark_history_content( $entry['content'] );
        if ( null === $content || '' === trim( $content['title'] ) || '' === trim( $content['paragraph'] ) ) {
            continue;
        }
        $history[] = array( 'id' => $entry['id'], 'archived_at' => $entry['archived_at'], 'content' => $content );
        if ( count( $history ) >= 20 ) {
            break;
        }
    }
    return $history;
}

/** This runs for both the Settings API save and Gemini's update_option call. */
function herospark_archive_before_change( $value, $old_value ) {
    $next = herospark_history_content( $value );
    $previous = herospark_history_content( $old_value );
    if ( null === $previous ) {
        $previous = herospark_defaults();
    }
    if ( null === $next || $next === $previous ) {
        return $value;
    }
    $history = herospark_get_history();
    // Retrying a failed content write should not add the same snapshot twice.
    if ( isset( $history[0] ) && $history[0]['content'] === $previous ) {
        return $value;
    }
    array_unshift( $history, array(
        'id' => wp_generate_uuid4(),
        'archived_at' => time(),
        'content' => $previous,
    ) );
    $history = array_slice( $history, 0, 20 );
    update_option( 'herospark_content_history', $history, false );
    if ( get_option( 'herospark_content_history', array() ) !== $history ) {
        add_settings_error( 'herospark_content', 'history_storage', __( 'Your changes were not saved because the previous version could not be archived. Please try again.', 'herospark' ), 'error' );
        return $old_value;
    }
    return $value;
}
add_filter( 'pre_update_option_herospark_content', 'herospark_archive_before_change', 10, 2 );

function herospark_restore_version( $id, $expected ) {
    if ( ! is_string( $expected ) || ! hash_equals( herospark_content_fingerprint(), $expected ) ) {
        return new WP_Error( 'stale', __( 'The homepage changed since this page was opened. Review the latest content before restoring a version.', 'herospark' ) );
    }
    foreach ( herospark_get_history() as $entry ) {
        if ( $entry['id'] !== $id ) {
            continue;
        }
        // Reuse the same sanitization as a manual content save.
        $content = herospark_sanitize_content( $entry['content'] );
        if ( $content === herospark_content() ) {
            return new WP_Error( 'same', __( 'This version already matches your current homepage.', 'herospark' ) );
        }
        update_option( 'herospark_content', $content );
        if ( herospark_content() !== $content ) {
            return new WP_Error( 'storage', __( 'The version could not be restored. Your current saved content has been kept.', 'herospark' ) );
        }
        return true;
    }
    return new WP_Error( 'missing', __( 'This version is no longer available. It may have fallen outside the 20-version history limit.', 'herospark' ) );
}

function herospark_handle_restore() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to restore content.', 'herospark' ), '', array( 'response' => 403 ) );
    }
    if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
        wp_die( esc_html__( 'Use a version’s Restore button to submit this request.', 'herospark' ), '', array( 'response' => 405 ) );
    }
    $id = isset( $_POST['version_id'] ) && is_string( $_POST['version_id'] ) ? wp_unslash( $_POST['version_id'] ) : '';
    if ( ! preg_match( '/\A[a-f0-9-]{36}\z/', $id ) ) {
        wp_die( esc_html__( 'The selected version is not valid.', 'herospark' ), '', array( 'response' => 400 ) );
    }
    check_admin_referer( 'herospark_restore_' . $id, 'herospark_restore_nonce_' . $id );
    $expected = isset( $_POST['expected_content'] ) && is_string( $_POST['expected_content'] ) ? wp_unslash( $_POST['expected_content'] ) : '';
    $result = herospark_restore_version( $id, $expected );
    $status = is_wp_error( $result ) ? $result->get_error_code() : 'restored';
    wp_safe_redirect( add_query_arg( array( 'page' => 'herospark', 'history' => $status ), admin_url( 'themes.php' ) ) );
    exit;
}
add_action( 'admin_post_herospark_restore_content', 'herospark_handle_restore' );

function herospark_history_notice() {
    $status = isset( $_GET['history'] ) && is_string( $_GET['history'] ) ? sanitize_key( wp_unslash( $_GET['history'] ) ) : '';
    $messages = array(
        'restored' => __( 'Version restored. Your previous homepage content is now available in history.', 'herospark' ),
        'stale' => __( 'The homepage changed since this page was opened. Review the latest content before restoring a version.', 'herospark' ),
        'same' => __( 'This version already matches your current homepage.', 'herospark' ),
        'missing' => __( 'This version is no longer available. Review the versions below.', 'herospark' ),
        'storage' => __( 'The version could not be restored. Your current saved content has been kept.', 'herospark' ),
    );
    if ( isset( $messages[ $status ] ) ) {
        $class = 'restored' === $status ? 'notice notice-success' : 'notice notice-warning';
        echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $status ] ) . '</p></div>';
    }
}

/** Escaped server-rendered markup, also returned after successful generation. */
function herospark_history_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '';
    }
    $history = herospark_get_history();
    $current = herospark_history_content( herospark_content() );
    $fingerprint = herospark_content_fingerprint();
    ob_start();
    ?>
    <section class="hs-card hs-history" aria-labelledby="hs-history-heading">
        <div class="hs-card-heading"><span class="hs-step" aria-hidden="true">03</span><div><h2 id="hs-history-heading"><?php esc_html_e( 'Content history', 'herospark' ); ?></h2><p><?php esc_html_e( 'Review and restore your 20 most recent previous versions.', 'herospark' ); ?></p></div></div>
        <p class="hs-help hs-history-help"><?php esc_html_e( 'Each version includes the project brief, heading, and paragraph. Restoring publishes it immediately and keeps your current saved content in history. Unsaved edits are replaced.', 'herospark' ); ?></p>
        <?php if ( empty( $history ) ) : ?>
            <p class="hs-history-empty"><?php esc_html_e( 'No previous versions yet. When you next change and save or generate content, the previous version will appear here. Earlier changes cannot be recovered.', 'herospark' ); ?></p>
        <?php else : ?>
            <?php foreach ( $history as $entry ) : ?>
                <?php $same = $entry['content'] === $current; ?>
                <details class="hs-history-entry">
                    <summary><span class="hs-history-title"><?php echo esc_html( $entry['content']['title'] ); ?></span><span class="hs-history-date"><?php echo esc_html( sprintf( __( 'Archived %s', 'herospark' ), wp_date( get_option( 'date_format', 'F j, Y' ) . ' · ' . get_option( 'time_format', 'g:i a' ), $entry['archived_at'] ) ) ); ?></span></summary>
                    <div class="hs-history-preview">
                        <h3><?php esc_html_e( 'Hero title', 'herospark' ); ?></h3><p><?php echo esc_html( $entry['content']['title'] ); ?></p>
                        <h3><?php esc_html_e( 'Introductory paragraph', 'herospark' ); ?></h3><p><?php echo esc_html( $entry['content']['paragraph'] ); ?></p>
                        <h3><?php esc_html_e( 'Project brief', 'herospark' ); ?></h3><p><?php echo esc_html( '' !== $entry['content']['context'] ? $entry['content']['context'] : __( 'No brief was saved for this version.', 'herospark' ) ); ?></p>
                        <?php if ( $same ) : ?>
                            <span class="hs-badge"><?php esc_html_e( 'Matches current content', 'herospark' ); ?></span>
                        <?php else : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hs-restore-form">
                                <input type="hidden" name="action" value="herospark_restore_content">
                                <input type="hidden" name="version_id" value="<?php echo esc_attr( $entry['id'] ); ?>">
                                <input type="hidden" name="expected_content" value="<?php echo esc_attr( $fingerprint ); ?>">
                                <?php wp_nonce_field( 'herospark_restore_' . $entry['id'], 'herospark_restore_nonce_' . $entry['id'], false ); ?>
                                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Restore this version', 'herospark' ); ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
