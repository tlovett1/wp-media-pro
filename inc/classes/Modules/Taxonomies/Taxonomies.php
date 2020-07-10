<?php
/**
 * Taxonomies functionality
 *
 * @package wpmp
 */

namespace WPMP\Modules\Taxonomies;

use WPMP\Module;
use WPMP\Utils;

/**
 * Taxonomies module class
 */
class Taxonomies extends Module {
	/**
	 * Setup taxonomies module
	 */
	public function setup() {
		if ( empty( Utils\get_settings( 'enable_media_tags' ) ) ) {
			return;
		}

		add_action( 'init', [ $this, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_wpmp_get_taxonomy_terms', [ $this, 'ajax_get_taxonomy_terms' ] );
		add_action( 'wp_ajax_wpmp_remove_taxonomy_term', [ $this, 'ajax_remove_taxonomy_term' ] );
		add_action( 'wp_ajax_wpmp_add_taxonomy_term', [ $this, 'ajax_add_taxonomy_term' ] );
	}

	/**
	 * Delete a taxonomy erm
	 */
	public function ajax_remove_taxonomy_term() {
		if ( empty( $_POST['postId'] ) || empty( $_POST['termId'] ) || empty( $_POST['taxonomy'] ) ) {
			wp_send_json_error();
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmp_taxonomies' ) ) {
			wp_send_json_error();
		}

		$post_id  = filter_input( INPUT_POST, 'postId', FILTER_SANITIZE_NUMBER_INT );
		$term_id  = filter_input( INPUT_POST, 'termId', FILTER_SANITIZE_NUMBER_INT );
		$taxonomy = filter_input( INPUT_POST, 'taxonomy', FILTER_SANITIZE_STRING );

		$terms = wp_get_object_terms(
			$post_id,
			$taxonomy,
			[
				'hide_empty' => false,
			]
		);

		$term_ids = [];

		foreach ( $terms as $term ) {
			if ( (int) $term->term_id !== (int) $term_id ) {
				$term_ids[] = $term->term_id;
			}
		}

		wp_set_object_terms(
			$post_id,
			$term_ids,
			$taxonomy,
			false
		);

		wp_send_json_success( $terms );
	}

	/**
	 * Create new terms via ajax
	 */
	public function ajax_add_taxonomy_term() {
		if ( empty( $_POST['postId'] ) || empty( $_POST['taxonomy'] ) || empty( $_POST['term'] ) ) {
			wp_send_json_error();
		}

		$post_id  = filter_input( INPUT_POST, 'postId', FILTER_SANITIZE_NUMBER_INT );
		$term     = filter_input( INPUT_POST, 'term', FILTER_SANITIZE_STRING );
		$taxonomy = filter_input( INPUT_POST, 'taxonomy', FILTER_SANITIZE_STRING );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmp_taxonomies' ) ) {
			wp_send_json_error();
		}

		$exists = get_term_by( 'name', $term, $taxonomy );

		if ( ! empty( $exists ) ) {
			$new_term = $exists;
		} else {
			$new_term = wp_insert_term( $term, $taxonomy );

			if ( is_wp_error( $new_term ) ) {
				wp_send_json_error();
			}

			$new_term = get_term( $new_term['term_id'] );
		}

		wp_set_object_terms(
			$post_id,
			[ $new_term->term_id ],
			$taxonomy,
			true
		);

		wp_send_json_success( $new_term );
	}

	/**
	 * Get media terms via ajax
	 */
	public function ajax_get_taxonomy_terms() {
		if ( empty( $_POST['postId'] ) || empty( $_POST['taxonomy'] ) ) {
			wp_send_json_error();
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmp_taxonomies' ) ) {
			wp_send_json_error();
		}

		$post_id  = filter_input( INPUT_POST, 'postId', FILTER_SANITIZE_NUMBER_INT );
		$taxonomy = filter_input( INPUT_POST, 'taxonomy', FILTER_SANITIZE_STRING );

		$terms = wp_get_object_terms(
			$post_id,
			$taxonomy,
			[
				'hide_empty' => false,
			]
		);

		wp_send_json_success( $terms );
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		wp_enqueue_media();

		wp_enqueue_script( 'wpmp-taxonomies', WPMP_URL . '/dist/js/taxonomies.js', [ 'wp-edit-post', 'media-views', 'jquery', 'media-models' ], WPMP_VERSION, true );

		$args = [
			'nonce'                => wp_create_nonce( 'wpmp_taxonomies' ),
			'wpmp-media-tag_terms' => get_terms(
				[
					'taxonomy'   => 'wpmp-media-tag',
					'hide_empty' => true,
				]
			),
		];

		wp_localize_script(
			'wpmp-taxonomies',
			'wpmpTaxonomies',
			$args
		);

		wp_enqueue_style( 'wpmp-taxonomies', WPMP_URL . '/dist/css/taxonomies-styles.css', [], WPMP_VERSION );
	}

	/**
	 * Register media taxonomies
	 */
	public function register() {
		$labels = array(
			'name'     => esc_html__( 'Media Tags', 'wpmp' ),
			'singular' => esc_html__( 'Media Tag', 'wpmp' ),
			'plural'   => esc_html__( 'Media Tags', 'wpmp' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => false,
		);

		register_taxonomy( 'wpmp-media-tag', [ 'attachment' ], $args );
	}
}
