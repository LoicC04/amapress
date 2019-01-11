<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once( 'class-amapress-posts-list-table.php' );

class TitanFrameworkOptionShowPosts extends TitanFrameworkOption {
	public $defaultSecondarySettings = array(
		'default'            => '', // show this when blank
		'post_type'          => 'post',
		'num'                => - 1,
		'post_status'        => 'any',
		'orderby'            => 'post_date',
		'order'              => 'DESC',
		'custom_request_uri' => null,
		'custom_request'     => null,
	);

	/*
	 * Display for options and meta
	 */
	public function display() {
		$this->echoOptionHeader();

		$prefix  = $this->getID() . '_';
		$meta_id = $this->getID() . 'frag';
		echo "<span id='$meta_id' />";
		$query = null;
		if ( ! empty( $this->settings['custom_request_uri'] ) ) {
			$query = new WP_Query( $this->settings['custom_request_uri'] );
		} else if ( is_array( $this->settings['custom_request'] ) ) {
			$query = new WP_Query( $this->settings['custom_request'] );
		} else if ( is_callable( $this->settings['custom_request'], false ) ) {
			$query = call_user_func( $this->settings['custom_request'], $this );
		} else {
			$args = array(
				'post_type'      => $this->settings['post_type'],
				'posts_per_page' => $this->settings['num'],
				'post_status'    => $this->settings['post_status'],
				'orderby'        => $this->settings['orderby'],
				'order'          => $this->settings['order'],
			);
			foreach ( $_GET as $k => $v ) {
				if ( strpos( $k, $prefix ) === 0 ) {
					$args[ substr( $k, strlen( $prefix ) ) ] = $v;
				}
			}
			if ( is_string( $this->settings['parent'] ) && ! empty( $this->settings['parent'] ) ) {
				$args['meta_query'] = array(
					array(
						'meta_key'   => $this->settings['parent'],
						'meta_value' => $this->owner->postID,
						'type'       => 'NUMERIC',
						'compare'    => '=',
					)
				);
			}
			$query = new WP_Query( $args );
		}

		$query->have_posts();
		$list = new Amapress_Posts_List_Table( $prefix, $this->settings['post_type'], $meta_id, $this->owner->postID, $query,
			array( 'plural' => $this->settings['post_type'] . 's' ) );
		$list->prepare_items();
		$list->views();
		$list->display();
		wp_reset_postdata();

		if ( isset( $this->settings['add_new'] ) && is_string( $this->settings['add_new'] ) ) {
			$label = $this->settings['add_new'];
			$url   = admin_url( 'post-new.php?post_type=' . $this->settings['post_type'] );
			if ( is_string( $this->settings['parent'] ) && ! empty( $this->settings['parent'] ) ) {
				$url = add_query_arg( $this->settings['parent'], $this->owner->postID );
			}
			echo "<a href='$url'>$label</a>";
		}

		$this->echoOptionFooter();
	}

	/*
	 * Display for options and meta
	 */
	public function columnDisplayValue( $post_id ) {
	}

	/*
	 * Display for theme customizer
	 */
	public function registerCustomizerControl( $wp_customize, $section, $priority = 1 ) {
//		$wp_customize->add_control( new TitanFrameworkOptionSelectPostsControl( $wp_customize, $this->getID(), array(
//			'label' => $this->settings['name'],
//			'section' => $section->settings['id'],
//			'settings' => $this->getID(),
//			'description' => $this->settings['desc'],
//			'post_type' => $this->settings['post_type'],
//			'posts_per_page' => $this->settings['num'],
//			'post_status' => $this->settings['post_status'],
//			'required' => $this->settings['required'],
//			'orderby' => $this->settings['orderby'],
//			'order' => $this->settings['order'],
//			'priority' => $priority,
//		) ) );
	}
}