<?php

/**
 * @package Auto-Install Free SSL
 * This package is a WordPress Plugin. It issues and installs free SSL certificates in cPanel shared hosting with complete automation.
 *
 * @author Free SSL Dot Tech <support@freessl.tech>
 * @copyright  Copyright (C) 2019-2024, Anindya Sundar Mandal
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @link       https://freessl.tech
 * @since      Class available since Release 3.0.0
 *
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace AutoInstallFreeSSL\FreeSSLAuto\Admin;


/**
 * Create a page that provides access to the log files
 *
 */
class Log
{
	private static $instance; // @since 4.5.0, to keep track of its initialization
    public $factory;
	public $page_url;

	/**
     * Start up
     * Private constructor to prevent multiple instantiations @since 4.5.0
     *
     * @param bool $register_hooks Whether to register admin hooks (pass false in unit tests).
     */
	private function __construct($register_hooks = true)
    {
	    if (!defined('ABSPATH')) {
		    die( "Access denied" );
	    }
        
        $this->factory =  new Factory();

        if ($register_hooks) {
            add_action('admin_menu', array($this, 'log_page_menu'));
        }

	    $site_url = parse_url( get_site_url() );
	    $this->page_url = $site_url['scheme'] . "://" . $site_url['host'] . ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );
    }

	/**
     * This method ensures that only one instance of the class is created.
	 * @since 4.5.0
	 * @param bool $register_hooks Whether to register hooks on first construction.
	 * @return Log
	 */
	public static function getInstance($register_hooks = true) {
		if (!isset(self::$instance)) {
			// When hooked to do_action( 'init' ) with accepted_args >= 1, WP may pass null.
			if ( null === $register_hooks ) {
				$register_hooks = true;
			}
			self::$instance = new self( (bool) $register_hooks );
		}
		return self::$instance;
	}

	/**
	 * Inject a custom admin factory (used by unit tests).
	 *
	 * @param Factory $factory Factory instance.
	 */
	public function setFactory($factory)
	{
		$this->factory = $factory;
	}
    
    
    /**
     *
     * Add the sub menu
     */
    public function log_page_menu()
    {
        add_submenu_page('auto_install_free_ssl', __("Log Page", 'auto-install-free-ssl'), __("Log", 'auto-install-free-ssl'), 'manage_options', 'aifs_log', array( $this, 'log_admin_page' ));
    }


	/**
	 *
	 * Log page callback
	 *
	 */
    public function log_admin_page()
    {
        //$this->plugin_data_handler();
        
        global $wp_version;
        $version_parts = explode(".", $wp_version);
        $version_base = (int)$version_parts[0];
        ?>
        <div class="wrap">

            <?php
            //echo '<h1>'. __("SSL Log", 'auto-install-free-ssl'). ' : ' . AIFS_NAME .'</h1>';
            echo aifs_header();

            $log_directory = AIFS_UPLOAD_DIR . AIFS_DS . 'log' . AIFS_DS;

            $files = glob($log_directory.'*', GLOB_MARK);

            $files = array_values(array_diff($files, array($log_directory . "web.config"))); //remove web.config and re-index array

            rsort($files);
            ?>

            <div style="padding: 2%;">
                <table>
                    <tr>
                        <td class="card" style="width: 15%;">
                            <span class="dashicons dashicons-arrow-down"></span> <span style="font-weight: bold;"><?= __( "View log by Date", 'auto-install-free-ssl' ) ?></span>
                            <div style="overflow-y: scroll; height: 400px; margin-top: 2%;">
                                <table>

                                    <?php
                                    $view_log_url = wp_nonce_url( $this->page_url, 'aifs_view_log', 'aifsviewlog' )."&date=";

                                    foreach ($files as $file):
                                    ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $date = str_replace('.log', '', basename($file));
                                            /* translators: %s: A date */
                                            $title = sprintf(__("Click here to view the log of %s", 'auto-install-free-ssl'), $date);
                                            echo '<a href="'.$view_log_url . $date.'" title="'. $title .'">'. $date .'</a>';
                                            //$file_date = wp_date($date); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                </table>
                            </div>
                        </td>

                        <td style="width: 2%;"></td>

                        <td class="card" style="width: 83%;">
                            <span style="color: #0aa524; font-size: 1.3em;"><?= __( "This log helps you understand the cause of an issue. Most users fix the issues by reading this log.", 'auto-install-free-ssl' ) ?></span>
                            <div style="overflow-y: scroll; height: 400px; margin-top: 2%;">
                                <?php
                                    if ( isset( $_GET['aifsviewlog'] ) ) {
                                        if ( !wp_verify_nonce( $_GET['aifsviewlog'], 'aifs_view_log' ) ) {
                                            echo __( "Access denied", 'auto-install-free-ssl' );
                                        }
                                        else {
                                            // H-8: $_GET['date'] used to be concatenated into the log
                                            // path with no validation at all, allowing directory
                                            // traversal (e.g. ../../../../wp-config.php). Require a
                                            // strict YYYY-MM-DD shape and basename() defensively.
                                            $requested_date = isset( $_GET['date'] ) ? trim( wp_unslash( $_GET['date'] ) ) : '';

                                            if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $requested_date ) ) {
                                                $log_directory = AIFS_UPLOAD_DIR . AIFS_DS . 'log' . AIFS_DS;
                                                $file_path = $log_directory . basename( $requested_date ) . '.log';
                                            }
                                        }
                                    }
                                    else{
                                        $file_path = trim($files[0]); //$files[0] contains complete path including '.log'
                                    }

                                    if(count($files) > 0 && isset( $file_path ) && is_file( $file_path )) {
                                        // H-9: escape the raw log body BEFORE any of the
                                        // display-formatting substitutions below. write_log()
                                        // already runs content through wp_kses_post() before
                                        // writing it, but display_log__premium_only() (the live
                                        // SSE stream) does not, and neither covered a file
                                        // reached via the (now-fixed) H-8 traversal -- esc_html()
                                        // here is the last line of defence regardless of how the
                                        // file's content got there.
                                        $text = esc_html( file_get_contents( $file_path ) );
                                        $text = str_replace( "\n", "<br />", $text );
	                                    // Match against the now-escaped "<pre>"/"</pre>" markers
	                                    // (kses-allowed tags that write_log() may have preserved)
	                                    // so the existing code-block presentation still works.
	                                    $text = str_replace( "&lt;pre&gt;", "<pre><code>", $text );
	                                    echo str_replace( "&lt;/pre&gt;", "</code></pre>", $text );
                                    }
                                    else{
                                        echo "<br /><em>" . __( "No log is available.", 'auto-install-free-ssl' ) . "</em>";
                                    }
                                ?>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- START - Delete or keep plugin data on deactivation -->
                <!--
                <table style="width: 100%; margin-top: 2%;">
                    <tr>
                        <td class="card">
                            <?php
                            /*if(!get_option('aifs_delete_plugin_data_on_deactivation')){
                                $link_to_delete_data = wp_nonce_url( $this->page_url, 'aifs_delete_data_on_deactivate', 'aifsdeleteplugindata' );
	                            $title = __( "DELETE plugin data on deactivation", 'auto-install-free-ssl' );
	                            $confirm = __( "Would you like to DELETE plugin data on deactivation?", 'auto-install-free-ssl' );*/
	                            /* translators: %1$s: Name of this plugin, i.e., 'Auto-Install Free SSL'; %2$s: Opening HTML 'a' tag; %3$s: Closing 'a' tag (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
	                            /*echo sprintf(__( '%1$s keeps the plugin data intact on deactivation (or uninstallation). If you\'d like to delete plugin data, you can %2$sclick here.%3$s', 'auto-install-free-ssl' ), '<strong>' . AIFS_NAME . '</strong>', '<a href="' . $link_to_delete_data . '" title="'.$title.'" class="button" onclick="return confirm(\''.$confirm.'\')">', '</a>');
                            }
                            else {
	                            $link_to_keep_data = wp_nonce_url( $this->page_url, 'aifs_keep_data_intact_on_deactivate', 'aifskeepplugindataintact' );
	                            $title = __( "You could keep plugin data INTACT on deactivation", 'auto-install-free-ssl' );*/
	                            /* translators: %1$s: Name of this plugin, i.e., 'Auto-Install Free SSL'; %2$s: Opening HTML 'a' tag; %3$s: Closing 'a' tag (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
	                            /*echo sprintf( __( '%1$s will delete the plugin data upon deactivation (or uninstallation). Feel free to %2$sclick here%3$s if you\'d like to keep plugin data intact.', 'auto-install-free-ssl' ), '<strong>' . AIFS_NAME . '</strong>', '<a href="' . $link_to_keep_data . '" title="'.$title.'" class="button button-primary">', '</a>' );
                            }*/
                            ?>
                        </td>
                    </tr>
                </table>
                 -->
                <!-- END - Delete or keep plugin data on deactivation -->
            </div>
	        <?= aifs_powered_by() ?>
        </div>
        <br /><br />
<?php
    }


	/**
	 * Sets value of the option aifs_delete_plugin_data_on_deactivation as per the request.
	 * UI that links here is currently disabled; method kept for redirects/tests.
	 */
    public function plugin_data_handler(){
	    if ( isset( $_GET['aifsdeleteplugindata'] ) ) {
		    if ( !wp_verify_nonce( $_GET['aifsdeleteplugindata'], 'aifs_delete_data_on_deactivate' ) ) {
			    wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
		    }
		    update_option( 'aifs_delete_plugin_data_on_deactivation', 1);
		    if ( wp_safe_redirect($this->factory->aifs_remove_parameters_from_url($this->page_url, ['aifsdeleteplugindata'])) ) {
		    	exit;
		    }

		    //Display success message
		    aifs_add_flash_notice( __("Settings successfully updated! We'll DELETE the plugin data on deactivation.", 'auto-install-free-ssl'));
	    }
	    else if ( isset( $_GET['aifskeepplugindataintact'] ) ) {
			    if ( !wp_verify_nonce( $_GET['aifskeepplugindataintact'], 'aifs_keep_data_intact_on_deactivate' ) ) {
				    wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
			    }
			    update_option( 'aifs_delete_plugin_data_on_deactivation', 0);
			    if ( wp_safe_redirect($this->factory->aifs_remove_parameters_from_url($this->page_url, ['aifskeepplugindataintact'])) ) {
			    	exit;
			    }

			    //Display success message
		        aifs_add_flash_notice( __("Settings successfully updated! We'll keep the plugin data INTACT on deactivation.", 'auto-install-free-ssl'));
	    }	    
    }

}
