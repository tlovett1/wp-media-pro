<?php
/**
 * Utility functions
 *
 * @package wpmp
 */

namespace WPMP\Utils;

/**
 * Getting setting defaults
 *
 * @since 1.0
 * @return array
 */
function get_setting_defaults() {
	return [
		'enable_media_tags' => true,
		'enable_folders'    => true,
		'enable_credits'    => true,
		'show_single_view'  => false,
	];
}

/**
 * Get plugin settings with defaults
 *
 * @param string $setting Particular setting to retrieve
 * @since  1.0
 * @return array
 */
function get_settings( $setting = null ) {
	$defaults = get_setting_defaults();

	$settings = get_option( 'wpmp_settings', [] );
	$settings = wp_parse_args( $settings, $defaults );

	if ( ! empty( $setting ) ) {
		return $settings[ $setting ];
	}

	return $settings;
}
