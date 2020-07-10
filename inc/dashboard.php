<?php
/**
 * Dashboard functionality
 *
 * @package wpmp
 */

namespace WPMP\Dashboard;

use WPMP\Utils;

/**
 * Setup hooks
 *
 * @return void
 */
function setup() {
	add_action( 'admin_menu', __NAMESPACE__ . '\\admin_menu' );
	add_action( 'admin_init', __NAMESPACE__ . '\\admin_init' );
}

/**
 * Setup options page
 *
 * @return void
 */
function admin_menu() {
	add_options_page(
		esc_html__( 'WP Media Pro', 'wpmp' ),
		esc_html__( 'WP Media Pro', 'wpmp' ),
		'manage_options',
		'wp-media-pro',
		__NAMESPACE__ . '\\screen_options'
	);
}

/**
 * Register setting
 *
 * @return void
 */
function admin_init() {
	register_setting( 'wpmp_settings', 'wpmp_settings', __NAMESPACE__ . '\\sanitize_options' );
}

/**
 * Sanitize options
 *
 * @param array $option Option to sanitize
 * @return array
 */
function sanitize_options( $option ) {

	$new_option = [];

	$new_option['enable_credits']    = ! empty( $option['enable_credits'] );
	$new_option['enable_folders']    = ! empty( $option['enable_folders'] );
	$new_option['enable_media_tags'] = ! empty( $option['enable_media_tags'] );
	$new_option['show_single_view']  = ! empty( $option['show_single_view'] );

	return $new_option;
}

/**
 * Output settings page
 */
function screen_options() {
	$settings = Utils\get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'WP Media Pro', 'simple-cache' ); ?></h1>

		<form action="options.php" method="post">
			<?php settings_fields( 'wpmp_settings' ); ?>

			<table class="form-table" role="presentation">

				<tbody>
					<tr>
						<th scope="row">
							<label for="wpmp_enable_folders"><?php esc_html_e( 'Media Folders', 'wpmp' ); ?></label>
						</th>
						<td>
							<label><input id="wpmp_enable_folders" <?php checked( true, $settings['enable_folders'] ); ?> type="checkbox" value="1" name="wpmp_settings[enable_folders]"> <?php esc_html_e( 'Enable', 'wpmp' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wpmp_enable_media_tags"><?php esc_html_e( 'Media Tags', 'wpmp' ); ?></label>
						</th>
						<td>
							<label><input id="wpmp_enable_media_tags" <?php checked( true, $settings['enable_media_tags'] ); ?> type="checkbox" value="1" name="wpmp_settings[enable_media_tags]"> <?php esc_html_e( 'Enable', 'wpmp' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wpmp_enable_credits"><?php esc_html_e( 'Media Credits', 'wpmp' ); ?></label>
						</th>
						<td>
							<label><input id="wpmp_enable_credits" <?php checked( true, $settings['enable_credits'] ); ?> type="checkbox" value="1" name="wpmp_settings[enable_credits]"> <?php esc_html_e( 'Enable', 'wpmp' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wpmp_show_single_view"><?php esc_html_e( 'Show Single Media File View', 'wpmp' ); ?></label>
						</th>
						<td>
							<label><input id="wpmp_show_single_view" <?php checked( true, $settings['show_single_view'] ); ?> type="checkbox" value="1" name="wpmp_settings[show_single_view]"> <?php esc_html_e( 'Yes', 'wpmp' ); ?></label>
							<p class="description">
								<?php esc_html_e( 'By default, WordPress creates pages on the front end for each media file. This is usually not needed and bad for SEO. This disables that.', 'wpmp' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes', 'wpmp' ); ?>">
			</p>
		</form>
	</div>
	<?php
}
