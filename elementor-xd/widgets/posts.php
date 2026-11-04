<?php
/**
 * Bài viết XD – danh sách bài viết dạng lưới / danh sách / slider (Blog Posts).
 *
 * @package Elementor_XD
 */

namespace EXD\Widgets;

use EXD\Base;
use EXD\H;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Posts extends Base {

	protected $exd_slug    = 'posts';
	protected $exd_title   = 'Bài viết XD';
	protected $exd_icon    = 'eicon-posts-grid';
	protected $exd_keys    = array( 'posts', 'blog', 'news', 'bai viet', 'tin tức' );
	protected $exd_style   = array( 'exd-fx' );
	protected $exd_dynamic = true;

	/** Chỉ nạp CSS/JS slider khi chọn kiểu Slider. */
	public function get_style_depends(): array {
		$d = parent::get_style_depends();
		if ( H::is_preview_request() || 'slider' === $this->exd_get( 'layout', 'grid' ) ) {
			$d[] = 'exd-w-slider';
		}
		return $d;
	}

	public function get_script_depends(): array {
		if ( H::is_preview_request() || 'slider' === $this->exd_get( 'layout', 'grid' ) ) {
			return array( 'exd-core', 'exd-w-slider' );
		}
		return array();
	}

	private function post_types(): array {
		$out = array();
		foreach ( get_post_types( array( 'public' => true, 'show_in_nav_menus' => true ), 'objects' ) as $pt ) {
			if ( 'page' === $pt->name ) {
				continue;
			}
			$out[ $pt->name ] = $pt->labels->singular_name;
		}
		return $out ?: array( 'post' => 'Bài viết' );
	}

	private function terms(): array {
		$out   = array();
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'number'     => 300,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[ $t->term_id ] = $t->name;
			}
		}
		return $out;
	}

	protected function register_controls() {
		/* Truy vấn */
		$this->start_controls_section( 'sec_query', array( 'label' => 'Lấy bài' ) );
		$this->add_control(
			'post_type',
			array(
				'label'   => 'Loại nội dung',
				'type'    => Controls_Manager::SELECT,
				'options' => $this->post_types(),
				'default' => 'post',
			)
		);
		$this->add_control(
			'cats',
			array(
				'label'       => 'Chuyên mục',
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->terms(),
				'condition'   => array( 'post_type' => 'post' ),
			)
		);
		$this->add_control(
			'ids',
			array(
				'label'       => 'Chọn bài theo ID',
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '12, 45, 78',
				'description' => 'Để trống để lấy theo chuyên mục / mới nhất.',
			)
		);
		$this->add_control(
			'number',
			array(
				'label'   => 'Số bài',
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 50,
				'default' => 3,
			)
		);
		$this->add_control(
			'offset',
			array(
				'label'   => 'Bỏ qua (offset)',
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'default' => 0,
			)
		);
		$this->add_control(
			'orderby',
			array(
				'label'   => 'Sắp xếp theo',
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'date'          => 'Ngày đăng',
					'modified'      => 'Ngày cập nhật',
					'title'         => 'Tiêu đề',
					'comment_count' => 'Số bình luận',
					'menu_order'    => 'Thứ tự',
					'rand'          => 'Ngẫu nhiên',
				),
				'default' => 'date',
			)
		);
		$this->add_control(
			'order',
			array(
				'label'   => 'Thứ tự',
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'DESC' => 'Giảm dần',
					'ASC'  => 'Tăng dần',
				),
				'default' => 'DESC',
			)
		);
		$this->add_control(
			'exclude_current',
			array(
				'label'        => 'Bỏ bài đang xem',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->end_controls_section();

		/* Bố cục */
		$this->start_controls_section( 'sec_layout', array( 'label' => 'Bố cục' ) );
		$this->add_control(
			'layout',
			array(
				'label'        => 'Kiểu hiển thị',
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'grid'   => 'Lưới',
					'list'   => 'Danh sách',
					'slider' => 'Slider (vuốt)',
				),
				'default'      => 'grid',
				'prefix_class' => 'exd-lay-',
				'render_type'  => 'template',
			)
		);
		$this->exd_preset(
			array(
				'card'    => 'Thẻ',
				'minimal' => 'Tối giản',
				'overlay' => 'Chữ trên ảnh',
				'push'    => 'Hộp chữ đè lên ảnh',
				'side'    => 'Ảnh bên trái',
			),
			'card',
			'Kiểu thẻ'
		);
		$this->add_responsive_control(
			'cols',
			array(
				'label'          => 'Số cột',
				'type'           => Controls_Manager::SELECT,
				'options'        => array_combine( range( 1, 6 ), range( 1, 6 ) ),
				'default'        => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'selectors'      => array( '{{WRAPPER}} .exd-posts' => '--exd-cols: {{VALUE}}; --exd-per: {{VALUE}};' ),
				'condition'      => array( 'layout!' => 'list' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'label'     => 'Khoảng cách',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'max' => 80 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-posts' => '--exd-gap: {{SIZE}}px;' ),
			)
		);
		$this->add_control(
			'show_img',
			array(
				'label'        => 'Ảnh đại diện',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			)
		);
		$this->add_control(
			'img_size',
			array(
				'label'     => 'Cỡ ảnh',
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'medium'       => 'Medium',
					'medium_large' => 'Medium large',
					'large'        => 'Large',
				),
				'default'   => 'medium_large',
				'condition' => array( 'show_img' => 'yes' ),
			)
		);
		$this->exd_ratio( '.exd-post__media' );
		$this->add_control(
			'show_cat',
			array(
				'label'        => 'Chuyên mục',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'meta',
			array(
				'label'    => 'Thông tin',
				'type'     => Controls_Manager::SELECT2,
				'multiple' => true,
				'options'  => array(
					'date'     => 'Ngày',
					'author'   => 'Tác giả',
					'comments' => 'Bình luận',
					'read'     => 'Thời gian đọc',
				),
				'default'  => array( 'date' ),
			)
		);
		$this->add_control(
			'tag',
			array(
				'label'   => 'Thẻ tiêu đề',
				'type'    => Controls_Manager::SELECT,
				'options' => array_combine( array( 'h2', 'h3', 'h4', 'h5', 'div' ), array( 'H2', 'H3', 'H4', 'H5', 'div' ) ),
				'default' => 'h3',
			)
		);
		$this->add_control(
			'title_lines',
			array(
				'label'     => 'Số dòng tiêu đề tối đa',
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'max'       => 5,
				'selectors' => array( '{{WRAPPER}} .exd-post__title' => '-webkit-line-clamp: {{VALUE}}; display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden;' ),
			)
		);
		$this->add_control(
			'excerpt',
			array(
				'label'   => 'Số chữ tóm tắt (0 = ẩn)',
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => 80,
				'default' => 18,
			)
		);
		$this->add_control(
			'more',
			array(
				'label'   => 'Chữ "đọc thêm"',
				'type'    => Controls_Manager::TEXT,
				'default' => 'Đọc tiếp',
			)
		);
		$this->exd_fx( 'zoom' );
		$this->exd_align( '.exd-post__body' );
		$this->exd_accent_control();
		$this->end_controls_section();

		$this->start_controls_section(
			'sec_slider',
			array(
				'label'     => 'Tuỳ chọn slider',
				'condition' => array( 'layout' => 'slider' ),
			)
		);
		$this->add_control(
			'autoplay',
			array(
				'label'        => 'Tự chạy',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
			)
		);
		$this->add_control(
			'interval',
			array(
				'label'     => 'Giây mỗi lượt',
				'type'      => Controls_Manager::NUMBER,
				'default'   => 5,
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'arrows',
			array(
				'label'        => 'Mũi tên',
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'circle' => 'Tròn nền trắng',
					'square' => 'Vuông',
					'none'   => 'Ẩn',
				),
				'default'      => 'circle',
				'prefix_class' => 'exd-nav-',
			)
		);
		$this->add_control(
			'dots',
			array(
				'label'        => 'Chấm',
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'pills' => 'Chấm kéo dài',
					'dots'  => 'Chấm tròn',
					'none'  => 'Ẩn',
				),
				'default'      => 'pills',
				'prefix_class' => 'exd-dot-',
			)
		);
		$this->add_responsive_control(
			'peek',
			array(
				'label'     => 'Hé lộ thẻ kế (px)',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'max' => 200 ) ),
				'selectors' => array( '{{WRAPPER}} .exd-posts' => '--exd-peek: {{SIZE}}px;' ),
			)
		);
		$this->end_controls_section();

		/* Style */
		$this->exd_style_box( 'card', 'Thẻ', '.exd-post' );
		$this->exd_style_image( 'img', 'Ảnh', '.exd-post__media img', array( 'wrap' => '.exd-post__media', 'hover_on' => '.exd-post', 'condition' => array( 'show_img' => 'yes' ) ) );
		$this->exd_style_box( 'body', 'Khung chữ', '.exd-post__body', array( 'hover_on' => '.exd-post' ) );
		$this->exd_style_text( 'cat', 'Chuyên mục', '.exd-post__cat', array( 'gap' => false ) );
		$this->exd_style_text( 'title', 'Tiêu đề', '.exd-post__title', array( 'hover_on' => '.exd-post' ) );
		$this->exd_style_text( 'meta', 'Thông tin (ngày, tác giả)', '.exd-post__meta', array( 'hover' => false ) );
		$this->exd_style_text( 'excerpt', 'Tóm tắt', '.exd-post__excerpt', array( 'hover' => false ) );
		$this->exd_style_text( 'more', 'Đọc thêm', '.exd-post__more', array( 'gap' => false, 'hover_on' => '.exd-post' ) );
	}

	private function query( $s ): \WP_Query {
		$args = array(
			'post_type'           => $s['post_type'] ?: 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $s['number'] ),
			'offset'              => max( 0, (int) $s['offset'] ),
			'orderby'             => $s['orderby'],
			'order'               => 'ASC' === $s['order'] ? 'ASC' : 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $s['ids'] ) ) );
		if ( $ids ) {
			$args['post__in'] = $ids;
			$args['orderby']  = 'post__in';
			unset( $args['offset'] );
		} elseif ( ! empty( $s['cats'] ) && 'post' === $args['post_type'] ) {
			$args['category__in'] = array_map( 'absint', (array) $s['cats'] );
		}
		if ( 'yes' === $s['exclude_current'] && is_singular() ) {
			$args['post__not_in'] = array( get_queried_object_id() );
		}
		return new \WP_Query( apply_filters( 'exd/posts/query_args', $args, $this ) );
	}

	protected function render() {
		$s = $this->exd_settings();
		$q = $this->query( $s );
		if ( ! $q->have_posts() ) {
			if ( H::is_editor() ) {
				echo '<div class="exd-post__empty">Chưa có bài viết phù hợp.</div>';
			}
			return;
		}
		$tag    = H::tag( $s['tag'], 'h3' );
		$slider = 'slider' === $s['layout'];
		$meta   = (array) $s['meta'];
		$cols   = max( 1, (int) ( $s['cols'] ?? 3 ) );

		if ( $slider ) {
			$conf = array(
				'auto'  => 'yes' === $s['autoplay'] ? (int) round( max( 1, (float) $s['interval'] ) * 1000 ) : 0,
				'pause' => 1,
				'loop'  => 1,
				'fade'  => 0,
			);
			echo '<div class="exd-posts exd-sl" data-exd-sl="' . esc_attr( wp_json_encode( $conf ) ) . '" role="region" aria-roledescription="carousel" aria-label="Bài viết"><div class="exd-sl__track" tabindex="0">';
		} else {
			echo '<div class="exd-posts">';
		}

		$n = 0;
		while ( $q->have_posts() ) {
			$q->the_post();
			$pid = get_the_ID();
			$url = get_permalink();
			echo $slider ? '<div class="exd-sl__slide">' : ''; // phpcs:ignore
			echo '<article class="exd-post exd-fx-host">';
			if ( 'yes' === $s['show_img'] && has_post_thumbnail() ) {
				echo '<a class="exd-post__media exd-media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">';
				the_post_thumbnail(
					$s['img_size'] ?: 'medium_large',
					array(
						'loading'  => 'lazy',
						'decoding' => 'async',
						'sizes'    => H::sizes( 'list' === $s['layout'] ? 3 : $cols ),
						'alt'      => '',
					)
				);
				echo '</a>';
			}
			echo '<div class="exd-post__body">';
			if ( 'yes' === $s['show_cat'] ) {
				$tax  = 'post' === get_post_type() ? 'category' : '';
				$term = $tax ? get_the_terms( $pid, $tax ) : false;
				if ( $term && ! is_wp_error( $term ) ) {
					echo '<a class="exd-post__cat" href="' . esc_url( get_term_link( $term[0] ) ) . '">' . esc_html( $term[0]->name ) . '</a>';
				}
			}
			echo '<' . $tag . ' class="exd-post__title"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title() ) . '</a></' . $tag . '>'; // phpcs:ignore
			if ( $meta ) {
				$parts = array();
				if ( in_array( 'date', $meta, true ) ) {
					$parts[] = '<time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time>';
				}
				if ( in_array( 'author', $meta, true ) ) {
					$parts[] = '<span>' . esc_html( get_the_author() ) . '</span>';
				}
				if ( in_array( 'comments', $meta, true ) ) {
					$parts[] = '<span>' . esc_html( get_comments_number() . ' bình luận' ) . '</span>';
				}
				if ( in_array( 'read', $meta, true ) ) {
					$words   = count( preg_split( '/\s+/u', trim( wp_strip_all_tags( get_post_field( 'post_content', $pid ) ) ) ) );
					$parts[] = '<span>' . esc_html( max( 1, (int) ceil( $words / 220 ) ) . ' phút đọc' ) . '</span>';
				}
				echo '<div class="exd-post__meta">' . implode( '<i aria-hidden="true"></i>', $parts ) . '</div>'; // phpcs:ignore
			}
			if ( (int) $s['excerpt'] > 0 ) {
				echo '<p class="exd-post__excerpt">' . esc_html( wp_trim_words( get_the_excerpt(), (int) $s['excerpt'], '…' ) ) . '</p>';
			}
			if ( '' !== $s['more'] ) {
				echo '<a class="exd-post__more" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $s['more'] . ': ' . get_the_title() ) . '">' . esc_html( $s['more'] ) . H::arrow() . '</a>'; // phpcs:ignore
			}
			echo '</div></article>';
			echo $slider ? '</div>' : ''; // phpcs:ignore
			$n++;
		}
		wp_reset_postdata();

		if ( $slider ) {
			echo '</div>';
			if ( $n > 1 ) {
				$chev = '<svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
				echo '<button type="button" class="exd-sl__arrow exd-sl__prev" aria-label="Trước">' . $chev . '</button><button type="button" class="exd-sl__arrow exd-sl__next" aria-label="Sau">' . $chev . '</button>'; // phpcs:ignore
				echo '<div class="exd-sl__dots">';
				for ( $i = 0; $i < $n; $i++ ) {
					echo '<button type="button" class="exd-sl__dot' . ( 0 === $i ? ' is-active' : '' ) . '" aria-label="' . esc_attr( 'Tới mục ' . ( $i + 1 ) ) . '"><i></i></button>';
				}
				echo '</div>';
			}
		}
		echo '</div>';
	}
}
