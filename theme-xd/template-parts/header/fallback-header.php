<header class="txd-fallback-header" role="banner">
    <div class="txd-container">
        <div class="site-title">
            <?php if ( has_custom_logo() ) : ?>
                <?php the_custom_logo(); ?>
            <?php else : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
            <?php endif; ?>
        </div>
        <nav aria-label="<?php _e( 'Menu chính', 'theme-xd' ); ?>">
            <?php wp_nav_menu([
                'theme_location' => 'primary',
                'menu_class'     => 'txd-fallback-nav',
                'container'      => false,
                'depth'          => 1,
                'fallback_cb'    => false,
            ]); ?>
        </nav>
    </div>
</header>
