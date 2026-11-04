<footer class="txd-fallback-footer" role="contentinfo">
    <div class="txd-container">
        <p>
            &copy; <?php echo date('Y'); ?>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
            &mdash; <?php _e( 'Được xây dựng bởi', 'theme-xd' ); ?>
            <a href="https://vinhxd.com" target="_blank" rel="noopener">Vĩnh XD</a>
        </p>
        <?php wp_nav_menu([
            'theme_location' => 'footer',
            'menu_class'     => 'txd-fallback-nav',
            'container'      => false,
            'depth'          => 1,
            'fallback_cb'    => false,
        ]); ?>
    </div>
</footer>
