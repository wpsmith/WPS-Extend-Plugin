<?php
/**
 * Contains WPS_Extend_Plugin class. and wps_extend_plugins function.
 *
 * @package    WPS_Core
 * @author     Travis Smith <t@wpsmith.net>
 * @copyright  2015 WP Smith, Travis Smith
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @version    1.0.0
 * @since      File available since Release 1.0.0
 */


if ( !class_exists( 'WPS_Extend_Plugin' ) ) {
	/**
	 * Class WPS_Extend_Plugin
	 *
	 * Extends an existing plugin.
	 *
	 * @since  Version 1.0.0
	 * @author Travis Smith <t@wpsmith.net>
	 *
	 * @todo   WP CLI needs some testing
	 */
	class WPS_Extend_Plugin {

		/**
		 * Plugin name (e.g., 'addthis/addthis_social_widget.php' )
		 *
		 * @var string|array
		 */
		private $plugin = '';

		/**
		 * Minimum version of Gravity Forms required.
		 * @var string
		 */
		private $min_version;

		/**
		 * Reference to the plugin root.
		 * The full path and filename of the file with symlinks resolved.
		 *
		 * @var string
		 */
		private $root_file = __FILE__;

		/**
		 * Text domain.
		 *
		 * @var string
		 */
		public $text_domain = 'wps';

		/**
		 * Message to be displayed.
		 *
		 * @var string
		 */
		public $message = '';

		/**
		 * Plugin data.
		 *
		 * @var array
		 */
		public $plugin_data = array();

		/**
		 * Constructor
		 *
		 * @param string      $plugin      Plugin activation "slug"
		 * @param string      $root_file   Plugin basename, File reference path to root including filename.
		 * @param string|null $min_version Minimum version allowed.
		 * @param string|null $text_domain Text domain.
		 */
		public function __construct( $plugin, $root_file, $min_version = null, $text_domain = null ) {
			// Setup
			$this->plugin      = $plugin;
			$this->root_file   = $root_file;
			$this->min_version = $min_version ? $min_version : $this->min_version;
			$this->text_domain = $text_domain ? $text_domain : $this->text_domain;

			// Cannot add a notice since plugin has been deactivated
			// Add notice since WP always seems to assume that the plugin was updated.
			if ( 'plugins.php' === basename( $_SERVER['PHP_SELF'] ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 4 );
				add_filter( 'network_admin_plugin_action_links', array( $this, 'plugin_action_links' ), 10, 4 );
				add_action( 'after_plugin_row_' . plugin_basename( $root_file ), array( $this, 'plugin_row' ) );
			} else {
				add_action( 'update_option_active_sitewide_plugins', array( $this, 'maybe_deactivate' ), 10, 2 );
				add_action( 'update_option_active_plugins', array( $this, 'maybe_deactivate' ), 10, 2 );
			}
		}

		/**
		 *
		 * @param array  $actions     An array of plugin action links.
		 * @param string $plugin_file Path to the plugin file.
		 * @param array  $plugin_data An array of plugin data.
		 * @param string $context     The plugin context. Defaults are 'All', 'Active',
		 *                            'Inactive', 'Recently Activated', 'Upgrade',
		 *                            'Must-Use', 'Drop-ins', 'Search'.
		 *
		 * @return array $actions      Maybe an array of modified plugin action links.
		 */
		public function plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {
			if ( ! $this->is_active() ) {
				self::deactivate_self( $this->root_file );
				if ( isset( $actions['deactivate'] ) ) {
					$params = self::get_url_params( $actions['deactivate'] );
					$params = wp_parse_args( $params, array( 's' => '', ) );
					unset( $actions['deactivate'] );

					/* translators: %s: plugin name */
					$actions['activate'] = '<a href="' . wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $params['paged'] . '&amp;s=' . $params['s'], 'activate-plugin_' . $plugin_file ) . '" class="edit" aria-label="' . esc_attr( sprintf( __( 'Activate %s', $this->text_domain ), $plugin_data['Name'] ) ) . '">' . __( 'Activate', $this->text_domain ) . '</a>';

					if ( ! is_multisite() && current_user_can( 'delete_plugins' ) ) {
						/* translators: %s: plugin name */
						$actions['delete'] = '<a href="' . wp_nonce_url( 'plugins.php?action=delete-selected&amp;checked[]=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $params['paged'] . '&amp;s=' . $params['s'], 'bulk-plugins' ) . '" class="delete" aria-label="' . esc_attr( sprintf( __( 'Delete %s', $this->text_domain ), $plugin_data['Name'] ) ) . '">' . __( 'Delete', $this->text_domain ) . '</a>';
					}
				}
			}

			return $actions;
		}

		/**
		 * Deactivate ourself if dependent plugin is deactivated.
		 *
		 * @param mixed $old_value The old option value.
		 * @param mixed $value     The new option value.
		 */
		public function maybe_deactivate( $old_value, $value ) {
			if ( ! $this->is_active() ) {
				self::deactivate_self( $this->root_file );

				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					WP_CLI::error( $this->get_message() );
				}
			}

		}

		/**
		 * Returns the message to be displayed.
		 *
		 * @return string Message
		 */
		private function get_message() {
			return $this->message;
		}

		/**
		 * Returns the plugin data.
		 *
		 * @param null $attr Specific data to return.
		 *
		 * @return string|array Specific attribute value or all values.
		 */
		private function get_plugin_data( $attr = null ) {
			if ( ! $this->plugin_data ) {
				$this->plugin_data = get_plugin_data( trailingslashit( plugin_dir_path( dirname( $this->root_file ) ) ) . $this->plugin, false, false );
			}

			if ( $attr && isset( $this->plugin_data[ $attr ] ) ) {
				return $this->plugin_data[ $attr ];
			}

			return $this->plugin_data;

		}

		/**
		 * Returns an array of parameters from HTML markup containing a link.
		 *
		 * @param string $text HTML to be parsed.
		 *
		 * @return array Associative array of parameters and values.
		 */
		private static function get_url_params( $text ) {
			// Capture parameters
			preg_match( "/<a\s[^>]*href=\"([^\"]*)\"[^>]*>(.*)<\/a>/", $text, $output );
			if ( $output ) {
				preg_match_all( '/([^?&=#]+)=([^&#]*)/', html_entity_decode( urldecode( $output[1] ) ), $m );

				//combine the keys and values onto an assoc array
				return array_combine( $m[1], $m[2] );
			}

			return array();
		}

		/**
		 * Deactivates the plugin.
		 *
		 * Function attempts to determine whether to deactivate extension plugin based on whether the depdendent
		 * plugin is active or not.
		 *
		 * @uses deactivate_plugins Deactivate a single plugin or multiple plugins.
		 *
		 * @param string $file         Single plugin or list of plugins to deactivate.
		 * @param mixed  $network_wide Whether to deactivate the plugin for all sites in the network.
		 *                             A value of null (the default) will deactivate plugins for both the site and the
		 *                             network.
		 */
		public static function deactivate_self( $file, $network_wide = null ) {
			if ( is_multisite() && false !== $network_wide ) {
				$network_wide = is_plugin_active_for_network( $file );
			}

			deactivate_plugins( plugin_basename( $file ), true, $network_wide );
		}

		/**
		 * Checks whether the dependent plugin(s) is/are active by checking the active_plugins list.
		 *
		 * @return bool Whether the dependent plugin is active.
		 */
		public function is_active() {
			$active = true;

			$name = $this->get_plugin_data( 'Name' ) ? $this->get_plugin_data( 'Name' ) : $this->plugin;

			// Check Plugin Active
			if ( ! is_plugin_active( $this->plugin ) ) {
				$this->message = sprintf(
					__( '%s (v%s) is required. Please activate it before activating this plugin.', $this->text_domain ),
					$name,
					$this->min_version
				);

				return false;
			}

			// Plugin Active, Check Version
			if ( ! $this->is_plugin_at_min_version() ) {
				$this->message = sprintf( __( '%s (v%s) is required. Please update it before activating this plugin.', $this->text_domain ), $name, $this->min_version );

				return false;
			}

			// All Good!
			return $active;
		}

		/**
		 * Determins whether the given plugin is at the minimum version.
		 *
		 * @return bool Whether the plugin is at the minimum version.
		 */
		private function is_plugin_at_min_version() {
			if ( ! $this->min_version ) {
				return true;
			}

			return ( floatval( $this->get_plugin_data( 'Version' ) ) >= floatval( $this->min_version ) );
		}

		/**
		 * Adds a notice to the plugin row to inform the user of the dependency.
		 */
		public function plugin_row() {
			if ( ! $this->is_active() ) {
				printf( '</tr><tr class="plugin-update-tr"><td colspan="5" class="plugin-update"><div class="update-message" style="background-color: #ffebe8;">%s</div></td>', $this->get_message() );

			}
		}

	}
}

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