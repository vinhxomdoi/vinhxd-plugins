<?php
/**
 * Khối hiển thị dùng chung giữa nhiều widget (Banner, Banner Grids, Slider).
 *
 * @package Elementor_XD
 */

namespace EXD;

defined( 'ABSPATH' ) || exit;

final class Parts {

	/**
	 * In 1 banner.
	 *
	 * @param array                $d   image, img_size, eager, sizes, eyebrow, title, tag, text,
	 *                                  btn_text, btn_link, btn2_text, btn2_link, link, cls, attrs.
	 * @param \Elementor\Widget_Base $w   Widget đang render (để dùng add_link_attributes).
	 * @param string               $key Khoá render attribute duy nhất.
	 */
	public static function banner( array $d, $w, $key = 'bn' ) {
		$link   = ! empty( $d['link']['url'] ) ? $d['link'] : null;
		$tag    = H::tag( $d['tag'] ?? 'h2', 'h2' );
		$eager  = ! empty( $d['eager'] );
		$img    = H::img(
			$d['image'] ?? array(),
			$d['img_size'] ?? 'full',
			array(
				'class'         => 'exd-bn__img',
				'alt'           => $d['alt'] ?? ( $d['title'] ?? '' ),
				'sizes'         => $d['sizes'] ?? '100vw',
				'loading'       => $eager ? 'eager' : 'lazy',
				'fetchpriority' => $eager ? 'high' : '',
			)
		);

		$cls = 'exd-bn exd-fx-host' . ( $link ? ' exd-bn--link' : '' ) . ( empty( $d['image']['url'] ) ? ' exd-bn--noimg' : '' ) . ( ! empty( $d['cls'] ) ? ' ' . $d['cls'] : '' );
		echo '<div class="' . esc_attr( $cls ) . '"' . ( $d['attrs'] ?? '' ) . '>'; // phpcs:ignore
		echo '<div class="exd-bn__bg exd-media">' . $img . '</div>'; // phpcs:ignore
		echo '<div class="exd-bn__inner"><div class="exd-bn__content">';
		if ( ! empty( $d['eyebrow'] ) ) {
			echo '<span class="exd-bn__eyebrow">' . esc_html( $d['eyebrow'] ) . '</span>';
		}
		if ( ! empty( $d['title'] ) ) {
			echo '<' . $tag . ' class="exd-bn__title">' . H::highlight( $d['title'] ) . '</' . $tag . '>'; // phpcs:ignore
		}
		if ( ! empty( $d['text'] ) ) {
			echo '<div class="exd-bn__text">' . wp_kses_post( $d['text'] ) . '</div>';
		}
		$btns = '';
		foreach ( array( 'btn', 'btn2' ) as $b ) {
			if ( empty( $d[ $b . '_text' ] ) ) {
				continue;
			}
			$k = $key . '_' . $b;
			$w->add_render_attribute( $k, 'class', array( 'exd-btn', 'exd-bn__' . $b, 'btn' === $b ? 'exd-btn--primary' : 'exd-btn--ghost' ) );
			if ( ! empty( $d[ $b . '_link' ]['url'] ) ) {
				$w->add_link_attributes( $k, $d[ $b . '_link' ] );
				$btns .= '<a ' . $w->get_render_attribute_string( $k ) . '>' . esc_html( $d[ $b . '_text' ] ) . ( 'btn' === $b ? H::arrow() : '' ) . '</a>';
			} else {
				$btns .= '<span ' . $w->get_render_attribute_string( $k ) . '>' . esc_html( $d[ $b . '_text' ] ) . ( 'btn' === $b ? H::arrow() : '' ) . '</span>';
			}
		}
		if ( $btns ) {
			echo '<div class="exd-bn__btns exd-btns">' . $btns . '</div>'; // phpcs:ignore
		}
		echo '</div></div>';
		if ( $link ) {
			$k = $key . '_link';
			$w->add_render_attribute( $k, 'class', 'exd-cover-link' );
			$w->add_link_attributes( $k, $link );
			echo '<a ' . $w->get_render_attribute_string( $k ) . '><span class="exd-sr">' . esc_html( wp_strip_all_tags( $d['title'] ?? 'Xem' ) ) . '</span></a>'; // phpcs:ignore
		}
		echo '</div>';
	}
}
