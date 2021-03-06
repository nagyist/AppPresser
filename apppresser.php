<?php
/*
Plugin Name: AppPresser
Plugin URI: http://apppresser.com
Description: A mobile app development framework for WordPress.
Text Domain: apppresser
Domain Path: /languages
Version: 1.1.0
Author: AppPresser Team
Author URI: http://apppresser.com
License: GPLv2
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class AppPresser {

	const VERSION           = '1.1.0';
	const SETTINGS_NAME     = 'appp_settings';
	public static $settings = 'false';
	public static $instance = null;
	public static $is_app   = null;
	public static $l10n     = array();
	public static $dir_path;
	public static $inc_path;
	public static $inc_url;
	public static $css_url;
	public static $img_url;
	public static $js_url;
	public static $dir_url;
	public static $pg_url;

	/**
	 * Creates or returns an instance of this class.
	 * @since  1.0.0
	 * @return AppPresser A single instance of this class.
	 */
	public static function get() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Let's start Pressin' Apps!
	 * @since  1.0.0
	 */
	function __construct() {

		// Define plugin constants
		self::$dir_path = trailingslashit( plugin_dir_path( __FILE__ ) );
		self::$dir_url  = trailingslashit( plugins_url( dirname( plugin_basename( __FILE__ ) ) ) );
		self::$inc_path = self::$dir_path . 'inc/';
		self::$inc_url  = self::$dir_url  . 'inc/';
		self::$css_url  = self::$dir_url  . 'css/';
		self::$img_url  = self::$dir_url  . 'images/';
		self::$js_url   = self::$dir_url  . 'js/';
		self::$pg_url   = self::$dir_url  . 'pg/';

		self::$l10n = array(
			'ajaxurl'                     => admin_url( 'admin-ajax.php' ),
			'debug'                       => defined( 'WP_DEBUG' ) && WP_DEBUG || defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'home_url'                    => home_url(),
			'mobile_browser_theme_switch' => appp_get_setting( 'mobile_browser_theme_switch' ),
			'admin_theme_switch'          => appp_get_setting( 'admin_theme_switch' ),
			'is_appp_true'                => self::is_app(),
		);

		// Only use minified files if SCRIPT_DEBUG is off
		$this->minified = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Load translations
		load_plugin_textdomain( 'apppresser', false, 'apppresser/languages' );

		// Setup our activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Hook in all our important pieces
		add_action( 'plugins_loaded', array( $this, 'includes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_cordova' ), 8 );
		add_action( 'wp_head', array( $this, 'do_appp_script' ), 1 );

		// Strip query var from enqueued cordova script
		add_filter( 'script_loader_src', array( $this, 'remove_query_arg' ), 9999 );

		require_once( self::$inc_path . 'AppPresser_Admin_Settings.php' );
		require_once( self::$inc_path . 'plugin-updater.php' );
		require_once( self::$inc_path . 'AppPresser_Theme_Customizer.php' );
		$this->theme_customizer = new AppPresser_Theme_Customizer();

	}

	/**
	 * Manually add some vars and our script tag so that we can head off the page if need be
	 * @since  1.0.3
	 */
	function do_appp_script() {

		// If PHP can read the cookie, we'll enqueue the standard way
		if ( is_user_logged_in() || self::is_app() ) {
			wp_enqueue_script( 'appp-core', self::$js_url ."appp{$this->minified}.js", null, self::VERSION );
			wp_localize_script( 'appp-core', 'apppCore', self::$l10n );
			return;
		}

		if ( ! self::$l10n['mobile_browser_theme_switch'] && ! self::$l10n['admin_theme_switch'] )
			return;

		// Otherwise we want to include the script ASAP to redirect the page if need be.

		foreach ( self::$l10n as $key => $value ) {
			$l10n[$key] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8');
		}

		?>
		<script type='text/javascript'>
		/* <![CDATA[ */
		window.apppCore = <?php echo json_encode( $l10n ); ?>;
		/* ]]> */
		</script>
		<script src="<?php echo self::$js_url; ?>appp<?php echo $this->minified; ?>.js" type="text/javascript"></script>
		<?php
	}

	/**
	 * Include all our important files.
	 * @since  1.0.0
	 */
	function includes() {

		require_once( self::$inc_path . 'AppPresser_Theme_Switcher.php' );
		$this->theme_switcher = new AppPresser_Theme_Switcher();
		// Uncomment when we add back in the app panel
		// require_once( self::$inc_path . 'body-class-meta-box.php' );

	}

	/**
	 * Activation hook for the plugin.
	 * @since  1.0.0
	 */
	function activate() {

		// code to execute when plugin is activated

		// @TODO: Define default settings upon activation

	}

	/**
	 * Enqueue phonegap/cordove if in app-wrapper
	 * @since  1.0.0
	 */
	function maybe_enqueue_cordova() {

		// If not in an app, don't enqueue cordova scripts
		if ( ! self::is_app() )
			return;

		$filename = "cordova{$this->minified}.js";
		$is_ios   = appp_is_ios();

		// Only enqueue cordova for iOS
		if ( $is_ios ) {
			$os = 'ios';
			wp_enqueue_script( 'cordova-core', self::$pg_url .'ios/'. $filename, null, self::VERSION );
		}
		// Or Android
		elseif ( appp_is_android() ) {
			$os = 'android';
			wp_enqueue_script( 'cordova-core', self::$pg_url .'android/'. $filename, null, self::VERSION );
		}

		wp_localize_script( 'cordova-core', 'apppCordova', array(
			'included_plugins' => $this->phonegap_plugins( $os ),
			// If updating cordova.js, replace instances of 'cordova.js' with apppCordova.filename
			'filename' => $filename,
		) );
	}

	/**
	 * Deactivation hook for the plugin.
	 * @since  1.0.0
	 */
	function deactivate() {
		// code to execute when plugin is deactivated
	}

	/**
	 * Remove wp version param from cordova enqueued scripts (so script loading doesn't break)
	 *
	 * This will mean that it's harder to break caching on the cordova script
	 *
	 * @since  1.0.3
	 * @param  string  $src URL
	 * @return string       Modified URL
	 */
	function remove_query_arg( $src ) {
		if ( false !== strpos( $src, "cordova{$this->minified}.js" ) )
			$src = remove_query_arg( 'ver', $src );
		return $src;
	}

	/**
	 * Gets list of phonegap plugins to be loaded.
	 * @since  1.1.0
	 * @param  string $os Operating System
	 * @return array      Array of plugin configurations
	 */
	public function phonegap_plugins( $os = 'ios' ) {

		// By default, AppPresser will only load one plugin
		$default_plugins = array(
			// 'org.apache.cordova.file.LocalFileSystem',
			'org.apache.cordova.device.device',
		);

		// Filter allows other plugins to load additional phonegap plugins
		$plugins_include = apply_filters( 'apppresser_phonegap_plugins_include', $default_plugins, $os, $this );

		// Retrieve plugin config arrays
		return $this->pluck_phonegap_plugins( $plugins_include, $os );
	}

	/**
	 * Get phonegap plugins config by their IDs
	 * @since  1.1.0
	 * @param  array $keep Array of keys to pluck from the phonegap plugin list
	 * @param  string $os  Operating System
	 * @return array       Filtered list
	 */
	public function pluck_phonegap_plugins( $keep = array(), $os = 'ios' ) {

		$plugins = $this->all_phonegap_plugins( $os );
		$return = array();
		foreach ( $keep as $id ) {
			if ( isset( $plugins[ $id ] ) ) {
				$data = $plugins[ $id ];
				$data['id'] = $id;
				$return[] = $data;
			}
		}
		return $return;
	}

	/**
	 * Gets the array of registered phonegap files
	 * @since  1.1.0
	 * @param  string $os Operating System
	 * @return array      All phonegap plugin config arrays
	 */
	public function all_phonegap_plugins( $os = 'ios' ) {
		if ( isset( $this->phonegap_plugins ) )
			return $this->phonegap_plugins;

		// Include our base list of plugins
		require_once( self::$inc_path . 'phonegap-plugins/all-plugins.php' );
		// Only iOS and Android for now
		$os = $os == 'ios' ? 'ios' : 'android';
		require_once( self::$inc_path . 'phonegap-plugins/'. $os .'-plugins.php' );

		// Filter allows additional phonegap plugins to be added to the list of available plugins
		$this->phonegap_plugins = apply_filters( 'apppresser_phonegap_plugins_list', $plugins, $os, $this );
		return $this->phonegap_plugins;
	}

	/**
	 * Utility method for getting our plugin's settings
	 * @since  1.0.0
	 * @param  string $key      Optional key to get a specific option
	 * @param  string $fallback Fallback option if none is found.
	 * @return mixed            Array of all options, a specific option, or false if specific option not found.
	 */
	public static function settings( $key = false, $fallback = false ) {
		if ( self::$settings === 'false' ) {
			self::$settings = get_option( self::SETTINGS_NAME );
			self::$settings = empty( self::$settings ) ? array() : (array) self::$settings;
		}
		if ( $key ) {
			$setting = isset( self::$settings[ $key ] ) ? self::$settings[ $key ] : false;
			// Override value or supply fallback
			$return = apply_filters( 'apppresser_setting_default', $setting, $key, self::$settings, $fallback );
			return $return ? $return : $fallback;

		}
		return self::$settings;
	}

	/**
	 * Checks if WP install is displaying the NEW WordPress style (previously the MP6 plugin)
	 * @since  1.0.0
	 * @return boolean Whether admin has new style
	 */
	public static function is_mp6() {
		global $wp_version;
		return version_compare( $wp_version, '3.7.9', '>' ) || is_plugin_active( 'mp6/mp6.php' );
	}

	/**
	 * Gets the app_is_app variable
	 * @since  1.0.0
	 * @return boolean Variable value
	 */
	public static function is_app() {
		if ( self::$is_app !== null )
			return self::$is_app;

		self::$is_app = isset( $_GET['appp'] ) && $_GET['appp'] == 1 || isset( $_COOKIE['AppPresser_Appp'] ) && $_COOKIE['AppPresser_Appp'] === 'true';

		return self::$is_app;
	}

}

// Singleton rather than a global.. If they want access, they can use:
AppPresser::get();

/**
 * Function wrapper for AppPresser::settings()
 * @since  1.0.0
 * @param  string $key      Optional key to get a specific option
 * @param  string $fallback Fallback option if none is found.
 * @return mixed            Array of all options, a specific option, or false if specific option not found.
 */
function appp_get_setting( $key = false, $fallback = false ) {
	return AppPresser::settings( $key, $fallback );
}

/**
 * AppPresser detect iOS function
 * @since  1.0.0
 * @return true if device is running iOS
 */
function appp_is_ios() {
	$ua = strtolower( $_SERVER['HTTP_USER_AGENT'] );
	return ( strstr( $ua, 'iphone' ) || strstr( $ua, 'ipod' ) || strstr( $ua, 'ipad' )
	);
}

/**
 * AppPresser detect Android function
 * @since  1.0.0
 * @return true if device is running Android
 */
function appp_is_android() {
	$ua = strtolower( $_SERVER['HTTP_USER_AGENT'] );
	return ( false !== stripos( $ua, 'android' ) );
}
