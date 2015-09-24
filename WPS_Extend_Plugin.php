<?php
/**
 * Contains WPS_Extend_Plugin class. and wps_extend_plugins function.
 *
 * @package    WPS_Core
 * @author     Travis Smith <t@wpsmith.net>
 * @copyright  2015 WP Smith, Travis Smith
 * @link       https://github.com/wpsmith/WPS-Extend-Plugin/
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
	 * @package WPS_Core
	 */
	class WPS_Extend_Plugin {

		/**
		 * Dependent plugin name, plugin relative path (e.g., 'addthis/addthis_social_widget.php' )
		 *
		 * @var string|array
		 */
		private $plugin = '';

		/**
		 * Action being performed on plugins page.
		 *
		 * @var string
		 */
		private $action = '';

		/**
		 * Minimum version of dependent plugin required.
		 * @var string
		 */
		private $min_version;

		/**
		 * Reference to the current plugin's root.
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
		 * Transient name.
		 *
		 * @var string
		 */
		private $transient = '';

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
			$this->transient   = substr( 'wpsep-' . plugin_basename( $root_file ), 0, 40 );

			/*
			 * Cannot add a notice since plugin has been deactivated
			 * Add notice since WP always seems to assume that the plugin was updated.
			 * Cannot use 'deactivate_' . $plugin hook as it does not fire if plugin is silently deactivated (such as during an update)
			 */
			if ( 'plugins.php' === basename( $_SERVER['PHP_SELF'] ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				$this->set_action_type();

				// Add admin notice
				add_action( 'admin_notices', array( $this, 'admin_notice' ) );

				// Late Deactivation so we can output the notifications
				add_filter( 'plugin_action_links_' . $plugin, array( $this, 'plugin_action_links_maybe_deactivate' ) );
				add_filter( 'network_admin_plugin_action_links_' . $plugin, array( $this, 'plugin_action_links_maybe_deactivate' ) );

				// Fix Current Plugin Action Links
				add_filter( 'plugin_action_links_' . plugin_basename( $root_file ), array( $this, 'plugin_action_links' ), 10, 4 );
				add_filter( 'network_admin_plugin_action_links_' . plugin_basename( $root_file ), array( $this, 'plugin_action_links' ), 10, 4 );

				// Add notice on Plugin Row
				add_action( 'after_plugin_row_' . plugin_basename( $root_file ), array( $this, 'plugin_row' ) );
			} else {
				// Maybe deactivate on update of active_plugins and active_sitewide_plugins options
				// deactivated_plugin action and deactivate_ . $plugin do not fire if plugin is being deactivated silently
				add_action( 'update_option_active_sitewide_plugins', array( $this, 'maybe_deactivate' ), 10, 2 );
				add_action( 'update_option_active_plugins', array( $this, 'maybe_deactivate' ), 10, 2 );
			}

		}

		/**
		 * Conditional helper function to determine which generic action is being taken.
		 *
		 * @param string $action Action ('activate' or 'deactivate').
		 *
		 * @return bool Whether performing an activation action or deactivation action.
		 */
		private function is_action( $action ) {
			if ( 'activate' === $action ) {
				return ( 'activate' === $this->action || 'activate-multi' === $this->action );
			}
			if ( 'deactivate' === $action ) {
				return ( 'deactivate' === $this->action || 'deactivate-multi' === $this->action );
			}

			return false;
		}

		/**
		 * Sets the action being taken by the plugins.php page.
		 */
		private function set_action_type() {
			if ( isset( $_REQUEST['deactivate-multi'] ) && $_REQUEST['deactivate-multi'] ) {
				$this->action = 'deactivate-multi';
			} elseif ( isset( $_REQUEST['activate-multi'] ) && $_REQUEST['activate-multi'] ) {
				$this->action = 'activate-multi';
			} elseif ( isset( $_REQUEST['deactivate'] ) && $_REQUEST['deactivate'] ) {
				$this->action = 'deactivate';
			} elseif ( isset( $_REQUEST['activate'] ) && $_REQUEST['activate'] ) {
				$this->action = 'activate';
			}
		}

		/**
		 * Maybe fix the action links as WordPress believes the plugin is active when it may have been deactivated.
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
		public function plugin_action_links_maybe_deactivate( $actions ) {
			if ( ! $this->is_active() ) {
				self::deactivate_self( $this->root_file );
			}

			return $actions;
		}

		/**
		 * Maybe fix the action links as WordPress believes the plugin is active when it may have been deactivated.
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
				if ( isset( $actions['deactivate'] ) ) {
					$params = self::get_url_params( $actions['deactivate'] );
					$params = wp_parse_args( $params, array( 's' => '', ) );
					unset( $actions['deactivate'] );

					// Change action link deactivate to activate
					$screen = get_current_screen();
					if ( $screen->in_admin( 'network' ) ) {
						if ( current_user_can( 'manage_network_plugins' ) ) {
							/* translators: %s: plugin name */
							$actions['activate'] = '<a href="' . wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $params['paged'] . '&amp;s=' . $params['s'], 'activate-plugin_' . $plugin_file ) . '" class="edit" aria-label="' . esc_attr( sprintf( __( 'Network Activate %s' ), $plugin_data['Name'] ) ) . '">' . __( 'Network Activate' ) . '</a>';
						}
						if ( current_user_can( 'delete_plugins' ) ) {
							/* translators: %s: plugin name */
							$actions['delete'] = '<a href="' . wp_nonce_url( 'plugins.php?action=delete-selected&amp;checked[]=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $params['paged'] . '&amp;s=' . $params['s'], 'bulk-plugins' ) . '" class="delete" aria-label="' . esc_attr( sprintf( __( 'Delete %s' ), $plugin_data['Name'] ) ) . '">' . __( 'Delete' ) . '</a>';
						}
					} else {
						/* translators: %s: plugin name */
						$actions['activate'] = '<a href="' . wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $params['paged'] . '&amp;s=' . $params['s'], 'activate-plugin_' . $plugin_file ) . '" class="edit" aria-label="' . esc_attr( sprintf( __( 'Activate %s', $this->text_domain ), $plugin_data['Name'] ) ) . '">' . __( 'Activate', $this->text_domain ) . '</a>';

						if ( ! is_multisite() && current_user_can( 'delete_plugins' ) ) {
							/* translators: %s: plugin name */
							$actions['delete'] = '<a href="' . wp_nonce_url( 'plugins.php?action=delete-selected&amp;checked[]=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $params['paged'] . '&amp;s=' . $params['s'], 'bulk-plugins' ) . '" class="delete" aria-label="' . esc_attr( sprintf( __( 'Delete %s', $this->text_domain ), $plugin_data['Name'] ) ) . '">' . __( 'Delete', $this->text_domain ) . '</a>';
						}
					}
				}
			}

			return $actions;
		}

		/**
		 * Outputs an admin notice if plugin is trying to be activated when dependent plugin is not activated.
		 */
		public function admin_notice() {
			if ( ! $this->is_active() ) {
				printf( '<div class="error notice is-dismissible"><p class="extension-message">%s</p></div>', $this->get_message() );
			}
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
					WP_CLI::error( $this->get_message( 'deactivate' ) );
				}
			}

		}

		public function recently_activated( $value, $old_value ) {
//$this->pr( $old_value, '$old_value' );
//$this->pr( $value, '$value' );
			$current = array_diff_key( $value, $old_value );
//$this->pr( $current, '$current' );
//wp_die();
			// Check if our plugin was just now deactivated
			if ( isset( $current[ $this->plugin ] ) ) {
				$this->set_transient( 'was_active', 1 );
			}
			return $value;
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
		 * Sets the message based on the needed notification type.
		 *
		 * @param string $type Notification type (deactivate, activate, update).
		 */
		private function set_message( $type ) {
			$dependency = $this->get_plugin_data( 'Name' ) ? $this->get_plugin_data( 'Name' ) : $this->plugin;
			switch ( $type ) {
				case 'deactivate':
					$current       = $this->get_plugin_data( 'Name', 'current' ) ? $this->get_plugin_data( 'Name', 'current' ) : plugin_basename( $this->root_file );
					$this->message = sprintf(
						__( '%1$s (v%2$s) is required for %3$s. Deactivating %3$s.', $this->text_domain ),
						$dependency,
						$this->min_version,
						$current
					);
					break;
				case 'upgrade':
				case 'update':
				case 'activate':
				default:
					if ( 'update' === $type || 'upgrade' === $type || !$this->is_plugin_at_min_version() ) {
						$action = 'update';
					} else {
						$action = 'activate';
					}
					$this->message = sprintf(
						__( '%s (v%s) is required. Please %s it before activating this plugin.', $this->text_domain ),
						$dependency,
						$this->min_version,
						$action
					);
					break;
			}
		}

		/**
		 * Returns the plugin data.
		 *
		 * @param null|string $attr Specific data to return.
		 * @param null|string $plugin Specific plugin to return plugin_data.
		 *
		 * @return string|array Specific attribute value or all values.
		 */
		private function get_plugin_data( $attr = null, $plugin = null ) {
			// Default to dependency plugin
			if ( ! $plugin || 'dependency' === $plugin ) {
				$plugin = $this->plugin;
				$plugin_path = trailingslashit( plugin_dir_path( dirname( $this->root_file ) ) ) . $this->plugin;

			// Allow current plugin_data to be returned
			} elseif ( 'current' === $plugin ) {
				$plugin = plugin_basename( $this->root_file );
				$plugin_path = plugin_dir_path( dirname( $this->root_file ) ) . plugin_basename( $this->root_file );

			// Un-supported plugin request, do nothing
			} else {
				return array();
			}

			// Maybe get fresh plugin_data
			if ( ! isset( $this->plugin_data[ $plugin ] ) || ( isset( $this->plugin_data[ $plugin ] ) && ! $this->plugin_data[ $plugin ] ) ) {
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');
				$this->plugin_data = get_plugin_data( $plugin_path, false, false );
			}

			// Maybe return specific attribute
			if ( $attr && isset( $this->plugin_data[ $attr ] ) ) {
				return $this->plugin_data[ $attr ];
			}

			// Return everything
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
		public static function deactivate_self( $file, $network_wide = false ) {
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

			// Check Plugin Active
			if ( ! is_plugin_active( $this->plugin ) ) {
				if ( $this->is_action( 'activate' ) ) {
					$this->set_message( 'activate' );
				} elseif ( $this->is_action( 'deactivate' ) ) {
					$this->set_message( 'deactivate' );
				}

				return false;
			}

			// Plugin Active, Check Version
			if ( ! $this->is_plugin_at_min_version() ) {
				$this->set_message( 'update' );

				return false;
			}

			// Maybe remove was_active transient
//			if ( $active ) {
//				$this->delete_transient( 'was_active' );
//			}
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
			if ( ! $this->is_active()  ) {
				printf( '</tr><tr class="plugin-update-tr"><td colspan="5" class="plugin-update"><div class="update-message" style="background-color: #ffebe8;">%s</div></td>', $this->get_message() );
			}
		}

	}
}