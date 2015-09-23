<?php

// Extend Gravity Forms
new WPS_Extend_Plugin( 'gravityforms/gravityforms.php', __FILE__, '1.9', 'my-plugin-text-domain' );

// Extend AddThis
new WPS_Extend_Plugin( 'addthis/addthis_social_widget.php', __FILE__, '1.9.13', 'my-plugin-text-domain' );

// Extend Jetpack, Gravity Forms, Display Posts Shortcode, Soliloquy
wps_extend_plugins( array(
	'gravityforms/gravityforms.php'                       => '1.9',
	'display-posts-shortcode/display-posts-shortcode.php' => '2.5',
	'jetpack/jetpack.php'                                 => '3.7',
	'soliloquy/soliloquy.php'                             => '2.4.3',
), __FILE__, 'my-plugin-text-domain' )

if ( !function_exists( 'wps_extend_plugins' ) ) {
	/**
	 * Determines whether the plugins are active and available taking appropriate action if not.
	 *
	 * @since  Version 1.0.0
	 * @author Travis Smith <t@wpsmith.net>
	 *
	 * @see    WPS_Extend_Plugin
	 *
	 * @param array       $plugins
	 * @param string      $root_file   Plugin basename, File reference path to root including filename.
	 * @param string|null $text_domain Text domain.
	 */
	function wps_extend_plugins( $plugins, $root_file, $text_domain = null ) {
		$plugin_extensions = array();
		foreach ( $plugins as $plugin => $min_version ) {
			$plugin_extensions[ $plugin ] = new WPS_Extend_Plugin( $plugin, $root_file, $min_version, $text_domain );
		}
	}
}