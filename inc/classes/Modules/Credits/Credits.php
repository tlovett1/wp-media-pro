<?php
/**
 * Credits functionality
 *
 * @package wpmp
 */

namespace WPMP\Modules\Credits;

use WPMP\Module;
use WPMP\Utils;

/**
 * Credits module
 */
class Credits extends Module {
	/**
	 * Setup module
	 */
	public function setup() {
		if ( empty( Utils\get_settings( 'enable_credits' ) ) ) {
			return;
		}

		add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_fields' ], 10, 2 );
		add_filter( 'attachment_fields_to_save', [ $this, 'save_fields' ], 10, 2 );
		add_action( 'enqueue_block_editor_assets', [ $this, 'register_block' ] );
	}

	public function register_block() {
		wp_enqueue_script(
			'wpmp-credits-block',
			WPMP_URL . '/dist/js/credits.js',
			[
				'wp-blocks',
				'wp-i18n',
				'wp-element',
				'wp-editor',
			],
			WPMP_VERSION,
			true
		);
	}

	/**
	 * Save credit fields
	 *
	 * @param array $post Current post
	 * @param array $attachment Media attachment
	 * @return array
	 */
	public function save_fields( $post, $attachment ) {

		if ( ! empty( $attachment['wpmp_credits'] ) ) {
			update_post_meta( $post['ID'], 'wpmp_credits', sanitize_text_field( $attachment['wpmp_credits'] ) );
		} else {
			delete_post_meta( $post['ID'], 'wpmp_credits' );
		}

		return $post;
	}

	/**
	 * Add credits to attachment fields
	 *
	 * @param array    $form_fields Form fields
	 * @param \WP_Post $post Post object
	 * @return array
	 */
	public function attachment_fields( $form_fields, $post ) {
		$form_fields['wpmp_credits'] = [
			'label' => esc_html__( 'Credits', 'wpmp' ),
			'class' => 'widefat',
			'value' => sanitize_text_field( get_post_meta( $post->ID, 'wpmp_credits', true ) ),
			'input' => 'text',
		];

		return $form_fields;
	}
}
