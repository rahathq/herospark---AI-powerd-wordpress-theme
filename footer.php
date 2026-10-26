<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
    <footer class="herospark-site-footer">
        <span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
        <span><?php esc_html_e( 'Made with a spark of possibility.', 'herospark' ); ?></span>
    </footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
