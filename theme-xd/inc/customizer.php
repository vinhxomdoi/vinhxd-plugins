<?php
/**
 * Theme XD - WordPress Customizer
 *
 * @package ThemeXD
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'customize_register', 'txd_customizer_register' );
function txd_customizer_register( $wp_customize ) {

    /* Panel chính */
    $wp_customize->add_panel( 'txd_panel', [
        'title'    => __( 'Theme XD', 'theme-xd' ),
        'priority' => 30,
    ]);

    /* ===== SECTION: Layout ===== */
    $wp_customize->add_section( 'txd_layout', [
        'title' => __( 'Layout & Sidebar', 'theme-xd' ),
        'panel' => 'txd_panel',
    ]);

    $wp_customize->add_setting( 'txd_sidebar_position', [
        'default'           => 'right',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control( 'txd_sidebar_position', [
        'label'   => __( 'Vị trí Sidebar', 'theme-xd' ),
        'section' => 'txd_layout',
        'type'    => 'radio',
        'choices' => [
            'right' => __( 'Phải', 'theme-xd' ),
            'left'  => __( 'Trái', 'theme-xd' ),
            'none'  => __( 'Không có Sidebar', 'theme-xd' ),
        ],
    ]);

    $wp_customize->add_setting( 'txd_content_width', [
        'default'           => '1200',
        'sanitize_callback' => 'absint',
    ]);
    $wp_customize->add_control( 'txd_content_width', [
        'label'       => __( 'Chiều rộng tối đa (px)', 'theme-xd' ),
        'section'     => 'txd_layout',
        'type'        => 'number',
        'input_attrs' => [ 'min' => 800, 'max' => 1600, 'step' => 10 ],
    ]);
}

/* CSS từ Customizer được gộp vào txd_inline_css() trong functions.php */
