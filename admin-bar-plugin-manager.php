<?php
/**
 * Plugin Name: Admin Bar Plugin Manager
 * Plugin URI: http://ounceoftalent.com
 * Description: Provides an admin bar menu item for Plugins, allowing you to easily activate or deactivate plugins.
 * Author: David Smith
 * Version: 0.1
 * Author URI: http://ounceoftalent.com
 */
class GW_Admin_Bar_Plugin_Manager {

	private static $instance = null;

	public static function get_instance() {
		if( null == self::$instance )
			self::$instance = new self;
		return self::$instance;
	}

	private function __construct() {

		add_action( 'admin_head', array( $this, 'enhance_admin_bar_scripts_styles' ), 99 );
		add_action( 'admin_bar_menu', array( $this, 'enhance_admin_bar' ) );

		add_action( 'init', array( $this, 'handle_actions' ) );

	}

	public function enhance_admin_bar( $wp_admin_bar ) {
		global $wp_admin_bar;

		if( ! is_admin() || ! is_admin_bar_showing() || ! current_user_can( 'activate_plugins' ) )
			return null;

		$classes = array( 'gwabpm-admin-bar' );
		$menu_id = 'gw-admin-bar-plugin-manager';

		$args = array(
			'id'        => $menu_id,
			'parent'    => 'top-secondary',
			'title'     => __( 'Plugins', 'gw-admin-bar-plugin-manager' ),
			'meta'      => array( 'class' => implode( ' ', $classes ) )
		);

		$wp_admin_bar->add_node( $args );

		$items = $this->get_menu_items( array( 'parent' => $menu_id ) );

		foreach( $items as $item ) {
			$wp_admin_bar->add_node( $item );
		}

	}

	public function get_menu_items( $defaults = array() ) {

		$items   = array();
		$plugins = get_plugins();

		foreach( $plugins as $plugin_slug => $plugin ) {

			$is_active = is_plugin_active( $plugin_slug );
			$classes   = array();
			$classes[] = $is_active ? 'active' : 'inactive';

			$url = add_query_arg( array(
				'gwabpm_action' => $is_active ? 'deactivate' : 'activate',
				'plugin' => $plugin_slug,
				'redirect' => urlencode( $_SERVER['REQUEST_URI'] )
			), admin_url( 'admin.php' ) );

			$path     = '';
			$template = '%1$s <span style="opacity:0.3;">%2$s</span>';
			$titles   = wp_list_pluck( $plugins, 'Title' );
			$counts   = array_count_values( $titles );


			if( $counts[ $plugin['Title'] ] > 1 ) {
				$path = dirname( $plugin_slug );
				$template = '%1$s <span style="opacity:0.3;">/%3$s</span> <span style="opacity:0.3;">%2$s</span>';
			}

			$items[] = wp_parse_args( array(
				'id'     => sanitize_title_with_dashes( $plugin['Title'] . $path . $plugin['Version'] ),
				'title'  => sprintf( $template, $plugin['Title'], $plugin['Version'], $path ),
				'href'   => $url,
				'meta'   => array( 'class' => implode( ' ', $classes ) )
			), $defaults );

		}

		return $items;
	}

	public function enhance_admin_bar_scripts_styles() {
		?>

		<style type="text/css">
			.gwabpm-admin-bar .inactive { opacity: 0.5; }
			.gwabpm-admin-bar .ab-sub-wrapper { display: block; overflow-y: auto; overflow-x: hidden; }
			.gwabpm-admin-bar > div.ab-item.search-active { padding: 0 !important; }
			.gwabpm-admin-bar input { padding: 0 10px !important; background-color: #333; border: 0; color: #ccc; }
			.gwabpm-admin-bar .ab-submenu li.selected { background-color: #666 !important; }
			.gwabpm-admin-bar .ab-submenu li span { line-height: inherit !important; }
		</style>

		<script type="text/javascript">
			jQuery( document ).ready( function($) {

				var menu             = $( '#wp-admin-bar-gw-admin-bar-plugin-manager' ),
					rootItem         = menu.children( 'div.ab-item' ),
					rootText         = $( '<span></span>' ).text( rootItem.text() ),
					searchInput      = $( '<input type="text" placeholder="Search plugins..." style="display:none;" />' ),
					subMenuWrap      = menu.find( '.ab-sub-wrapper' ),
					subMenu          = subMenuWrap.children( 'ul' ),
					origHTML         = subMenu.html(),
					selectedIndex    = -1,
					delta            = 500,
					lastKeypressTime = 0,
					keyMap           = [],
					triggerKeyCode   = 80;

				$( document ).on( 'keydown keyup', function( e ) {

					// Ignore events that are inside inputs/textarea
                    if ( $(e.target).is('textarea, input' ) ) {
						return;
                    }

					e = e || event; // to deal with IE
					keyMap[ e.keyCode ] = e.type == 'keydown';

					// listen for shift + double p
					var isKeyComboPressed = keyMap[16] && keyMap[ triggerKeyCode ];

					if( ! isKeyComboPressed ) {
						return;
					} else if( isKeyComboPressed && e.keyCode != triggerKeyCode ) {
						lastKeypressTime = 0;
						keyMap = [];
					}

					var thisKeypressTime = new Date();

					if ( thisKeypressTime - lastKeypressTime <= delta ) {
						rootItem.click();
						thisKeypressTime = 0;
						return false;
					}

					lastKeypressTime = thisKeypressTime;

				} );

				rootItem.html( '' ).append( rootText, searchInput );
				subMenuWrap.css( {
					'maxHeight':  $( window ).height() - $( '#wpadminbar' ).height(),
					'minWidth':   subMenuWrap.width()
				} );

				rootItem.click( function() {

					if( searchInput.is( ':visible' ) ) {
						return;
					}

					rootText.hide();
					rootItem.addClass( 'search-active' );
					searchInput.show().focus();

				} );

				searchInput.keyup( function( e ) {

					// any character except enter, up arrow, down arrow
					if( $.inArray( e.which, [ 13, 38, 40 ] ) == -1 ) {
						selectedIndex = -1;
						filterList();
					}

				} ).keydown( function( e ) {

					if( e.which == 13 /* enter */ ) {
						location.href = subMenu.children().eq( selectedIndex ).find( 'a' ).attr( 'href' );
					} else if( e.which == 38 /* up arrow */ ) {
						navigateList( -1 );
					} else if( e.which == 40 /* down arrow */ ) {
						navigateList( 1 );
					} else if( e.which == 27 /* escape */ ) {
						searchInput.blur();
					}

				} ).focus( function() {

					subMenuWrap.show();

				} ).blur( function() {

					subMenuWrap.hide();
					rootText.show();
					rootItem.removeClass( 'search-active' );

					searchInput.val( '' ).hide();
					filterList();
					selectedIndex = -1;

				} );

				function navigateList( move ) {

					var $items = subMenu.children();

					selectedIndex = Math.max( selectedIndex + move, 0 );
					selectedIndex = Math.min( selectedIndex, $items.length - 1 );

					$items.removeClass( 'selected' ).eq( selectedIndex ).addClass( 'selected' );

				}

				function filterList() {

					var listItems = $( origHTML ).filter( 'li' );

					subMenu.html( '' );

					listItems.filter( function() {

						var search = searchInput.val(),
							regex  = new RegExp( search, 'i' );

						return regex.test( $( this ).text() );
					} ).appendTo( subMenu );

				}

			} );
		</script>

		<?php
	}

	public function handle_actions() {

		if( ! isset( $_REQUEST['gwabpm_action'] ) || ! isset( $_REQUEST['plugin'] ))
			return;

		$action = $_REQUEST['gwabpm_action'];
		$plugin = $_REQUEST['plugin'];

		switch( $action ) {
			case 'activate':
				$this->activate_plugin( $plugin );
				break;
			case 'deactivate':
				$this->deactivate_plugin( $plugin );
				break;
			case 'confirmation':
				add_action( 'admin_notices', array( $this, 'display_action_confirmation' ) );
				break;
		}

	}

	public function activate_plugin( $plugin_slug ) {

		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$plugins = get_plugins();
		$current_plugin = $plugins[$plugin_slug];

		if( ! isset( $_REQUEST['alternate_version_deactivated'] ) ) {
			foreach( $plugins as $plugin_file => $plugin ) {

				$not_current_plugin = $plugin_file != $plugin_slug;
				$has_same_name      = $plugin['Title'] == $current_plugin['Title'];
				$is_active          = is_plugin_active($plugin_file);

				if( $not_current_plugin  && $has_same_name && $is_active ) {
					deactivate_plugins( $plugin_file, false, is_network_admin() );
					wp_redirect( add_query_arg( 'alternate_version_deactivated', $_SERVER['REQUEST_URI'] ) );
					exit;
				}

			}
		}

		$result = activate_plugin( $plugin_slug, $this->get_redirect_url( $plugin_slug, 'activate' ), is_network_admin() );

	}

	public function deactivate_plugin( $plugin ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		deactivate_plugins( $plugin, false, is_network_admin() );
		wp_redirect( $this->get_redirect_url( $plugin, 'deactivate' ) );
		exit;
	}

	public function get_redirect_url( $plugin, $action ) {
		return add_query_arg( array(
			'gwabpm_action' => 'confirmation',
			'completed_action' => $action,
			'plugin' => urlencode( $plugin )
		), $_REQUEST['redirect'] );
	}

	public function display_action_confirmation() {

		$plugin = get_plugin_data( WP_PLUGIN_DIR . '/' . urldecode( $_REQUEST['plugin'] ), false );
		$action = $_REQUEST['completed_action'];

		switch( $action ) {
			case 'activate':
				$action_description = 'activated';
				break;
			case 'deactivated':
				$action_description = 'deactivated';
				break;
			default:
				$action_description = $action;
		}

		?>

		<div class="updated">
			<p><?php printf( __( '%1$s v.%3$s has been %2$s successfully!', 'gw-admin-bar-plugin-manager' ), $plugin['Title'], $action_description, $plugin['Version'] ); ?></p>
		</div>

		<?php
	}

}

function gw_admin_bar_plugin_manager() {
	return GW_Admin_Bar_Plugin_Manager::get_instance();
}

gw_admin_bar_plugin_manager();