<?php

/**
 * @package Auto-Install Free SSL
 * 
 * Plugin Name: Auto-Install Free SSL
 * Plugin URI:  https://freessl.tech
 * Description: Generate & install Free SSL Certificates, activate force HTTPS redirect with one click to fix insecure links & mixed content warnings, and get automatic Renewal Reminders.
 * Version:     4.3.0
 * Requires at least: 4.1
 * Requires PHP:      5.6
 * Author:      Free SSL Dot Tech
 * Author URI:  https://freessl.tech
 * License:     GNU General Public License, version 3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: auto-install-free-ssl
 * Domain Path: /languages/
 * Network:     true
 *
 * @author      Free SSL Dot Tech
 * @category    Plugin
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License version 3 or higher
 * 
 * @copyright  Copyright (C) 2019-2022, Anindya Sundar Mandal - anindya@SpeedUpWebsite.info
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
 *
 */
/* Deny direct access */
if ( !defined( 'ABSPATH' ) ) {
    die( __( "Access denied", 'auto-install-free-ssl' ) );
}
if ( function_exists( 'aifssl_fs' ) ) {
    aifssl_fs()->set_basename( false, __FILE__ );
} else {
    /* START Freemius*/
    if ( !function_exists( 'aifssl_fs' ) ) {
        // Create a helper function for easy SDK access.
        function aifssl_fs() {
            global $aifssl_fs;
            if ( !isset( $aifssl_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/freemius/start.php';
                $aifssl_fs = fs_dynamic_init( array(
                    'id'              => '10204',
                    'slug'            => 'auto-install-free-ssl',
                    'type'            => 'plugin',
                    'public_key'      => 'pk_8e6c4ffc369c2a116adf5dd4fc982',
                    'is_premium'      => false,
                    'has_addons'      => false,
                    'has_paid_plans'  => true,
                    'has_affiliation' => 'selected',
                    'menu'            => array(
                        'slug'       => 'auto_install_free_ssl',
                        'first-path' => 'admin.php?page=auto_install_free_ssl',
                    ),
                    'is_live'         => true,
                ) );
            }
            return $aifssl_fs;
        }

        // Init Freemius.
        aifssl_fs();
        // Signal that SDK was initiated.
        do_action( 'aifssl_fs_loaded' );
    }
    /* END Freemius*/
}
$log_msg = "";
if ( !defined( 'PHP_VERSION_ID' ) || PHP_VERSION_ID < 50400 ) {
    //wp_die( __( "You need at least PHP 5.4.0", 'auto-install-free-ssl' ) );
    $log_msg .= __( "You need at least PHP 5.4.0", 'auto-install-free-ssl' ) . "<br />";
}
if ( !extension_loaded( 'openssl' ) ) {
    //wp_die( __( "You need OpenSSL extension enabled with PHP", 'auto-install-free-ssl' ) );
    $log_msg .= __( "You need OpenSSL extension enabled with PHP", 'auto-install-free-ssl' ) . "<br />";
}
if ( !extension_loaded( 'curl' ) ) {
    //wp_die( __( "You need Curl extension enabled with PHP", 'auto-install-free-ssl' ) );
    $log_msg .= __( "You need Curl extension enabled with PHP", 'auto-install-free-ssl' ) . "<br />";
}
if ( !ini_get( 'allow_url_fopen' ) ) {
    //wp_die( __( "You need to set PHP directive allow_url_fopen = On. Please contact your web hosting company for help.", 'auto-install-free-ssl' ) );
    $log_msg .= __( "You need to set PHP directive allow_url_fopen = On. Please contact your web hosting company for help.", 'auto-install-free-ssl' ) . "<br />";
}
if ( strlen( $log_msg ) > 10 ) {
    $log_msg .= '<br />Please <a href="' . admin_url( 'plugins.php' ) . '">click here</a> to return to the plugins page.';
    wp_die( $log_msg );
}
// Define Directory Separator to make the default DIRECTORY_SEPARATOR short
if ( !defined( 'DS' ) ) {
    define( 'DS', DIRECTORY_SEPARATOR );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugin_data = get_plugin_data( __FILE__ );
define( 'AIFS_VERSION', $plugin_data['Version'] );
define( 'AIFS_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIFS_URL', plugin_dir_url( __FILE__ ) );
define( 'AIFS_NAME', $plugin_data['Name'] );
$wp_upload_directory = wp_upload_dir();
define( 'AIFS_UPLOAD_DIR', $wp_upload_directory['basedir'] . DS . 'auto-install-free-ssl' );
define( 'AIFS_DEFAULT_LE_ACME_VERSION', 2 );
define( 'AIFS_LE_ACME_V2_LIVE', 'https://acme-v02.api.letsencrypt.org' );
define( 'AIFS_LE_ACME_V2_STAGING', 'https://acme-staging-v02.api.letsencrypt.org' );
if ( file_exists( __DIR__ . DS . 'aifs-config.php' ) ) {
    require_once __DIR__ . DS . 'aifs-config.php';
}
//if ( aifssl_fs()->can_use_premium_code__premium_only() ) {
if ( !defined( 'AIFS_ENC_KEY' ) ) {
    define( 'AIFS_ENC_KEY', SECURE_AUTH_KEY );
    //@since 2.1.1
}
//}
if ( !function_exists( 'aifs_findRegisteredDomain' ) && !function_exists( 'aifs_getRegisteredDomain' ) && !function_exists( 'aifs_validDomainPart' ) ) {
    require_once AIFS_DIR . DS . 'vendor' . DS . 'usrflo' . DS . 'registered-domain-libs' . DS . 'PHP' . DS . 'effectiveTLDs.inc.php';
    require_once AIFS_DIR . DS . 'vendor' . DS . 'usrflo' . DS . 'registered-domain-libs' . DS . 'PHP' . DS . 'regDomain.inc.php';
}
if ( version_compare( phpversion(), '5.3.0' ) >= 0 && !class_exists( 'AutoInstallFreeSSL\\FreeSSLAuto\\FreeSSLAuto' ) ) {
    if ( file_exists( __DIR__ . DS . 'vendor' . DS . 'autoload.php' ) ) {
        require_once __DIR__ . DS . 'vendor' . DS . 'autoload.php';
    }
}
use AutoInstallFreeSSL\FreeSSLAuto\Acme\Factory as AcmeFactory;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\ForceSSL;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\HomeOptions;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\AdminNotice;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\GenerateSSLmanually;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\Factory;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\ForceHttpsPage;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\Log;
use AutoInstallFreeSSL\FreeSSLAuto\Email;
use AutoInstallFreeSSL\FreeSSLAuto\Acme\Client;
/**
 * Force SSL on frontend and backend
 */
new ForceSSL();
/** Create the menu */
function aifs_home_menu() {
    /** Top level menu */
    add_menu_page(
        __( "Auto-Install SSL Dashboard", 'auto-install-free-ssl' ),
        __( "Auto-Install Free SSL", 'auto-install-free-ssl' ),
        'manage_options',
        'auto_install_free_ssl',
        'aifs_home_options',
        'dashicons-lock',
        65
    );
}

/** Register the above function using the admin_menu action hook and attach all other options  */
if ( is_admin() ) {
    // activation hook
    register_activation_hook( __FILE__, 'activate_auto_install_free_ssl' );
    // Deactivation hook
    register_deactivation_hook( __FILE__, 'deactivate_auto_install_free_ssl' );
    //Freemius after uninstall action
    aifssl_fs()->add_action( 'after_uninstall', 'aifs_uninstall_cleanup' );
    /** Add 'Settings' option */
    add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'aifs_add_settings_option_in_plugins_page' );
    /** required for successful redirect */
    add_action( 'admin_init', 'aifs_do_output_buffer' );
    /** AIFS Home page */
    add_action( 'admin_menu', 'aifs_home_menu' );
    /** Implementing Translations - load textdomain */
    add_action( 'init', 'aifs_load_textdomain' );
    /** Display Admin Notice */
    new AdminNotice();
    /** Generate SSL manually */
    new GenerateSSLmanually();
    //Add the JS
    add_action( 'admin_enqueue_scripts', 'aifs_add_js_enqueue' );
    add_action( 'admin_enqueue_scripts', 'aifs_admin_styles' );
    add_action( 'admin_init', 'aifs_download_file_handler' );
    /** Force HTTPS page */
    new ForceHttpsPage();
    /** Log page */
    new Log();
}
/** Add 'Settings' option */
function aifs_add_settings_option_in_plugins_page(  $links  ) {
    $links[] = '<a href="' . admin_url( 'admin.php?page=auto_install_free_ssl' ) . '">' . __( "Settings", 'auto-install-free-ssl' ) . '</a>';
    return $links;
}

/** Attach the home page */
function aifs_home_options() {
    if ( !current_user_can( 'manage_options' ) ) {
        wp_die( __( "You do not have sufficient permissions to access this page.", 'auto-install-free-ssl' ) );
    }
    $home_options = new HomeOptions();
    $home_options->display();
}

/** Implementing Translations - load textdomain */
function aifs_load_textdomain() {
    load_plugin_textdomain( 'auto-install-free-ssl', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}

/**
 * This function will be called during the plugin activation.
 * Improved since 4.0.0
 * */
function activate_auto_install_free_ssl() {
    if ( !get_option( 'aifs_user_since_free_only_version' ) ) {
        //add_option( 'aifs_user_since_free_only_version', 0 ); //If this value is not set (or was set 0), it's an NEW user
        add_option( 'aifs_user_since_free_only_version', (int) aifs_user_since_free_only_version() );
    }
    //$app_settings = aifs_get_app_settings();
    $basic_settings = get_option( 'basic_settings_auto_install_free_ssl' );
    /**
     * if already basic settings etc exists, don't run next code block
     */
    //if ( !isset( $app_settings['acme_version'] ) || !isset( $app_settings['key_size'] ) || !isset($app_settings['all_domains']) || count($app_settings['all_domains']) == 0 ) { //This will over-right already entered data with version 2
    if ( $basic_settings === false || !isset( $basic_settings['acme_version'] ) && !isset( $basic_settings['key_size'] ) ) {
        $data = new AutoInstallFreeSSL\FreeSSLAuto\Admin\AutoDataEntry();
        $data->data_entry();
    }
    // Schedule daily Cron Job Event (if not done already) - moved here since 4.0.0
    if ( !wp_next_scheduled( 'aifs_do_this_daily' ) ) {
        wp_schedule_event( current_time( 'timestamp' ), 'daily', 'aifs_do_this_daily' );
    }
}

/**
 * This function will be called during the plugin deactivation
 * Improved since 4.0.0
 * */
function deactivate_auto_install_free_ssl() {
    if ( !get_option( 'aifs_user_since_free_only_version' ) ) {
        add_option( 'aifs_user_since_free_only_version', 1 );
    }
    /*
     * Moved Delete plugin data on deactivation logic to aifs_uninstall_cleanup(),
     * which will be called by Freemius after uninstall action.
     * @since 4.0.0
     */
    /*
     * Remove the cron job
     * @since 3.2.7
     */
    if ( wp_next_scheduled( 'aifs_do_this_daily' ) ) {
        wp_unschedule_event( wp_next_scheduled( 'aifs_do_this_daily' ), 'aifs_do_this_daily' );
    }
}

/**
 * Delete plugin data on plugin deletion (i.e., uninstallation).
 * This function will be called by Freemius after uninstall action.
 * @since 4.0.0
 */
function aifs_uninstall_cleanup() {
    if ( get_option( 'aifs_free_plan_selected' ) ) {
        delete_option( 'aifs_free_plan_selected' );
    }
    if ( get_option( 'aifs_comparison_table_promo_start_time' ) ) {
        // @since 4.0.0
        delete_option( 'aifs_comparison_table_promo_start_time' );
    }
    /*
     * Removed 'aifs_user_since_free_only_version' since 3.5.1
     * @since 3.2.7
     */
    if ( get_option( 'aifs_delete_plugin_data_on_deactivation' ) ) {
        $options = [
            'basic_settings_auto_install_free_ssl',
            'all_domains_auto_install_free_ssl',
            'cpanel_settings_auto_install_free_ssl',
            'exclude_domains_auto_install_free_ssl',
            'dns_provider_auto_install_free_ssl',
            'add_cron_job_auto_install_free_ssl',
            'aifs_display_announcement',
            'aifs_generate_ssl_manually',
            'aifs_return_array_step1_manually',
            'aifs_free_plan_selected',
            'aifs_domains_to_revoke_cert',
            'aifs_ssl_installed_on_this_website',
            'aifs_force_ssl',
            'aifs_revert_http_nonce',
            'aifs_display_free_premium_offer',
            'aifs_is_multi_domain',
            'aifs_multi_domain',
            'aifs_display_review',
            'aifs_admin_notice_display_counter',
            'aifs_renew_ssl_later_requested_timestamp',
            'aifs_ssl_renewal_reminder_email_last_sent_timestamp',
            'aifs_display_discount_offer_existing_users',
            'aifs_is_generated_ssl_installed',
            'aifs_number_of_ssl_generated',
            'aifs_ca_terms_of_service_url',
            'aifs_delete_plugin_data_on_deactivation'
        ];
        // aifs_is_admin_email_invalid
        foreach ( $options as $opt ) {
            delete_option( $opt );
        }
    }
}

/**
 * Detects if the OS Windows
 * @return bool
 * @since 3.2.0
 */
function aifs_is_os_windows() {
    if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
        return true;
    } else {
        return false;
    }
}

/**
 * Detects the Server Software and returns it in lower case (apache | nginx | ms-iis). Returns FALSE if unable to detect.
 * @return false|string
 * @since 3.2.0
 */
function aifs_server_software() {
    $ss = strtolower( $_SERVER['SERVER_SOFTWARE'] );
    if ( strpos( $ss, 'apache' ) !== false ) {
        return "apache";
    }
    if ( strpos( $ss, 'nginx' ) !== false ) {
        return "nginx";
    }
    if ( strpos( $ss, 'microsoft-iis' ) !== false || strpos( $ss, 'iis' ) !== false ) {
        return "ms-iis";
    }
    return false;
}

/**
 * Detects if the user using the plugin since the free only version
 * @return false|mixed|void
 */
function aifs_is_existing_user() {
    return get_option( 'aifs_user_since_free_only_version', true );
}

/**
 * Detects, if the user installed the plugin since free only version
 * @since 3.0.6
 */
function aifs_user_since_free_only_version() {
    if ( get_option( 'basic_settings_auto_install_free_ssl' ) ) {
        $basic_settings_existing = get_option( 'basic_settings_auto_install_free_ssl' );
        /*if(empty($basic_settings_existing)){
        			return true;
        		}*/
        if ( is_array( $basic_settings_existing ) ) {
            $data = new AutoInstallFreeSSL\FreeSSLAuto\Admin\AutoDataEntry();
            $basic_settings_default_v3 = $data->basic_settings_default_v3();
            if ( $basic_settings_existing['use_wildcard'] != $basic_settings_default_v3['use_wildcard'] ) {
                return true;
            } elseif ( $basic_settings_existing['is_staging'] != $basic_settings_default_v3['is_staging'] ) {
                return true;
            } elseif ( $basic_settings_existing['country_code'] != $basic_settings_default_v3['country_code'] ) {
                return true;
            } elseif ( $basic_settings_existing['state'] != $basic_settings_default_v3['state'] ) {
                return true;
            } elseif ( $basic_settings_existing['organization'] != $basic_settings_default_v3['organization'] ) {
                return true;
            } elseif ( $basic_settings_existing['certificate_directory'] != $basic_settings_default_v3['certificate_directory'] ) {
                return true;
            } elseif ( $basic_settings_existing['days_before_expiry_to_renew_ssl'] != $basic_settings_default_v3['days_before_expiry_to_renew_ssl'] ) {
                return true;
            } elseif ( $basic_settings_existing['using_cdn'] != $basic_settings_default_v3['using_cdn'] ) {
                return true;
            } elseif ( $basic_settings_existing['key_size'] != $basic_settings_default_v3['key_size'] ) {
                return true;
            } else {
                return false;
            }
        }
        /*else{
        			return true; //User didn't configured, but installed v2
        		}*/
    }
    return false;
}

/**
 * required for successful redirect
 */
function aifs_do_output_buffer() {
    ob_start();
}

/**
 * Set 1 for the user who is using this plugin since free-only version (V 1 or 2)
 */
/*if(!get_option('aifs_user_since_free_only_version')){
		add_option('aifs_user_since_free_only_version', 1);
	}*/
//$app_settings = aifs_get_app_settings();
//if ( aifssl_fs()->can_use_premium_code__premium_only() ) {
/**
 * if already basic settings etc exists, don't run next two lines
 */
//if ( !isset( $app_settings['acme_version'] ) || !isset( $app_settings['key_size'] ) || !isset($app_settings['all_domains']) || count($app_settings['all_domains']) == 0 ) { //This will over-right already entered data with version 2
/*if ( !isset( $app_settings['acme_version'] ) && !isset( $app_settings['key_size'] ) ) {
			$data = new AutoInstallFreeSSL\FreeSSLAuto\Admin\AutoDataEntry();
			$data->data_entry();
		}*/
//}
/**
 * Fires just after activation - redirect to the plugin dashboard
 *
 * If a plugin is silently activated (such as during an update), this hook does not fire.
 *
 * @param $plugin
 */
/*function aifs_activation_redirect( $plugin ) {
		if ( $plugin == plugin_basename( __FILE__ ) ) {

			//$redirect_url = "admin.php?page=auto_install_free_ssl";
			$redirect_url = menu_page_url( 'auto_install_free_ssl' );

			/* This is throwing access issue with freemius

			if ( aifs_is_free_version() ) {
				$redirect_url = "admin.php?page=aifs_generate_ssl_manually";
			} else {
				$redirect_url = "admin.php?page=auto_install_free_ssl";
			} */
/*
			//exit( wp_redirect( admin_url( $redirect_url ) ) );
			wp_redirect( $redirect_url, 301 );
		}
	}*/
//add_action( 'activated_plugin', 'aifs_activation_redirect' );
/**
 * Merge all the options in a single array.
 * Improved since 3.5.1
 * */
function aifs_get_app_settings() {
    $basic_settings = get_option( 'basic_settings_auto_install_free_ssl' );
    if ( $basic_settings && is_array( $basic_settings ) ) {
        $app_settings = $basic_settings;
    } else {
        return false;
    }
    $cpanel_settings = get_option( 'cpanel_settings_auto_install_free_ssl' );
    if ( $cpanel_settings && is_array( $cpanel_settings ) ) {
        $app_settings = array_merge( $app_settings, $cpanel_settings );
    }
    $exclude_domains = get_option( 'exclude_domains_auto_install_free_ssl' );
    if ( $exclude_domains && is_array( $exclude_domains ) ) {
        $app_settings = array_merge( $app_settings, $exclude_domains );
    }
    $dns_provider = get_option( 'dns_provider_auto_install_free_ssl' );
    if ( $dns_provider && is_array( $dns_provider ) ) {
        $app_settings = array_merge( $app_settings, $dns_provider );
    }
    $all_domains = get_option( 'all_domains_auto_install_free_ssl' );
    if ( $all_domains && is_array( $all_domains ) ) {
        $app_settings = array_merge( $app_settings, $all_domains );
    }
    /*if ( get_option( 'domains_to_revoke_cert_auto_install_free_ssl' ) ) {
    			$app_settings = array_merge( $app_settings, get_option( 'domains_to_revoke_cert_auto_install_free_ssl' ) );
    		}*/
    $domains_revoke_cert = get_option( 'aifs_domains_to_revoke_cert' );
    if ( $domains_revoke_cert && is_array( $domains_revoke_cert ) ) {
        $app_settings = array_merge( $app_settings, $domains_revoke_cert );
    }
    return $app_settings;
}

/**
 * Get the domain of this WordPress website
 *
 * @param bool $remove_www
 *
 * @return string
 *
 * @since 1.0.0
 */
/* function aifs_get_domain(bool $remove_www = true){
 *  Removing parameter type hint to make compatible with PHP 5.6. Using scalar type hints like string is supported since PHP 7. */
function aifs_get_domain(  $remove_www = true  ) {
    $site_url = get_site_url();
    $site_url = parse_url( $site_url );
    $domain = $site_url['host'];
    if ( $remove_www && strpos( $domain, 'www.' ) !== false && strpos( $domain, 'www.' ) === 0 ) {
        //If www. found at the beginning
        $domain = substr( $domain, 4 );
    }
    return $domain;
}

/**
 * Get IPv4 or IPv6 of this server
 * improved since 4.1.0
 * @return mixed|string
 * @since 3.6.0
 */
function aifs_ip_of_this_server() {
    $serverIP = false;
    if ( isset( $_SERVER['SERVER_ADDR'] ) ) {
        $serverIP = $_SERVER['SERVER_ADDR'];
    } else {
        // Get the website address (domain name)
        $websiteAddress = aifs_get_domain( false );
        // Try getting IP using gethostbyname
        $ip = gethostbyname( $websiteAddress );
        // Check if gethostbyname returned a valid IP
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $serverIP = $ip;
        } else {
            if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
                $serverIP = $ip;
            } else {
                // Fallback to dns_get_record if gethostbyname didn't return a valid IP
                $records = dns_get_record( $websiteAddress, DNS_A );
                $serverIP = ( isset( $records[0]['ip'] ) ? $records[0]['ip'] : '' );
            }
        }
    }
    if ( $serverIP && (filter_var( $serverIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) || filter_var( $serverIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 )) ) {
        return $serverIP;
    } else {
        return false;
    }
}

//Attach the JS
function aifs_add_js_enqueue(  $hook  ) {
    // Only add to this admin.php admin page -> page=aifs_add_dns_service_provider
    if ( !(aifs_is_free_version() && aifs_is_existing_user() && isset( $_GET['page'] ) && ($_GET['page'] === 'auto_install_free_ssl' || $_GET['page'] !== 'aifs_generate_ssl_manually' || $_GET['page'] !== 'aifs_force_https')) ) {
        if ( !isset( $_GET['page'] ) || 'admin.php' !== $hook && $_GET['page'] !== 'aifs_add_dns_service_provider' && $_GET['page'] !== 'aifs_basic_settings' && $_GET['page'] !== 'auto_install_free_ssl' && $_GET['page'] !== 'aifs_cpanel_settings' && $_GET['page'] !== 'aifs_generate_ssl_manually' && $_GET['page'] !== 'aifs_force_https' ) {
            return;
        }
    }
    wp_register_script( 'aifs_script_1', AIFS_URL . 'assets/js/script.js', array('jquery') );
    /* translators: "Let's Encrypt" is a nonprofit SSL certificate authority. */
    $agree_to_le_terms = __( "Please read and agree to the Let's Encrypt™ Subscriber Agreement", 'auto-install-free-ssl' );
    // Localize the script with new data
    $translation_array = array(
        'password_or_api_token' => __( "Please provide either a Password or an API Token", 'auto-install-free-ssl' ),
        'admin_email'           => __( "Please provide the Admin Email id", 'auto-install-free-ssl' ),
        'le_terms'              => $agree_to_le_terms,
        'freessl_tech_tos_pp'   => __( "Please read and agree to FreeSSL.tech Terms of Service and Privacy Policy", 'auto-install-free-ssl' ),
    );
    wp_localize_script( 'aifs_script_1', 'aifs_js_variable', $translation_array );
    wp_enqueue_script( 'aifs_script_1' );
    //wp_enqueue_script( 'aifs_script_1', AIFS_URL . 'assets/js/script.js', array( 'jquery' ) );
}

/**
 * Enqueue admin CSS and JS
 *
 * @since 1.1.0
 */
function aifs_admin_styles() {
    wp_enqueue_style(
        'aifs_style_1',
        AIFS_URL . 'assets/css/aifs-admin.css',
        false,
        AIFS_VERSION,
        'all'
    );
}

/**
 * Set review option to 1 to display the review request
 *
 * @since 1.1.0
 */
function aifs_set_display_review_option() {
    update_option( 'aifs_display_review', 1 );
}

add_action( 'aifs_display_review_init', 'aifs_set_display_review_option' );
/**
 * Set announcement option to 1 to display the announcement request again
 *
 * @since 2.2.2
 */
function aifs_set_display_announcement_option() {
    update_option( 'aifs_display_free_premium_offer', 1 );
}

add_action( 'aifs_display_announcement_init', 'aifs_set_display_announcement_option' );
/**
 * Set discount offer option to 1 to display the discount offer again
 *
 * @since 3.2.13
 */
function aifs_set_display_discount_offer_option() {
    update_option( 'aifs_display_discount_offer_existing_users', 1 );
}

add_action( 'aifs_display_discount_offer_init', 'aifs_set_display_discount_offer_option' );
/**
 * If there are admin notices in the option table, display them and remove from the option table to prevent them being displayed forever
 *
 * @since 2.0.0
 */
function aifs_display_flash_notices() {
    $notices = get_option( 'aifs_flash_notices' );
    if ( $notices != false && count( $notices ) > 0 ) {
        // Iterate through the notices to display them, if exist in option table
        foreach ( $notices as $notice ) {
            $style = ( $notice['type'] == "success" ? 'style="color: #46b450;"' : '' );
            printf(
                '<div class="notice notice-%1$s %2$s" %3$s><p>%4$s</p></div>',
                $notice['type'],
                $notice['dismissible'],
                $style,
                $notice['notice']
            );
        }
        // Now delete the option
        delete_option( 'aifs_flash_notices' );
    }
}

// Add the above function to admin_notices
add_action( 'admin_notices', 'aifs_display_flash_notices', 12 );
/**
 * @param string $notice (The notice text)
 * @param string $type (This can be "success", "info", "warning", "error". "success" is default.)
 * @param boolean $is_dismissible (Set this TRUE to add is-dismissible functionality)
 *
 *
 * Add a flash notice to the options table which will be displayed upon page refresh or redirect
 *
 * @since 2.0.0
 */
/* function aifs_add_flash_notice(string $notice, string $type = "success", bool $is_dismissible = true ) {
 * Removing parameter type hint to make compatible with PHP 5.6. Using scalar type hints like string is supported since PHP 7. */
function aifs_add_flash_notice(  $notice, $type = "success", $is_dismissible = true  ) {
    // Get the notices already saved in the option table, if any, or return an empty array
    $notices = get_option( 'aifs_flash_notices', array() );
    $dismissible_text = ( $is_dismissible ? "is-dismissible" : "" );
    // Add the new notice
    array_push( $notices, array(
        "notice"      => $notice,
        "type"        => $type,
        "dismissible" => $dismissible_text,
    ) );
    // Now update the option with the notices
    update_option( 'aifs_flash_notices', $notices );
}

/*// Schedule Cron Job Event (if not done already)
	function aifs_custom_cron_job() {
		if ( ! wp_next_scheduled( 'aifs_do_this_daily' ) ) {
			wp_schedule_event( current_time( 'timestamp' ), 'daily', 'aifs_do_this_daily' );
		}
	}

	add_action( 'wp', 'aifs_custom_cron_job' );*/
// Scheduled Action Hook
// Daily cron
function aifs_do_this_daily() {
    if ( aifs_is_free_version() ) {
        //Send renewal reminder email
        $email = new Email();
        $email->send_ssl_renewal_reminder_email();
        //Clean the log directory
        $logger = new AutoInstallFreeSSL\FreeSSLAuto\Logger();
        $logger->clean_log_directory();
    }
}

add_action( 'aifs_do_this_daily', 'aifs_do_this_daily' );
// AJAX action to update option
// @since 4.0.0
add_action( 'wp_ajax_aifs_update_option', array(new HomeOptions(), 'aifs_update_option') );
/**
 * Download file handler
 */
function aifs_download_file_handler() {
    if ( isset( $_GET['aifsdownloadssl'] ) ) {
        if ( !wp_verify_nonce( $_GET['aifsdownloadssl'], 'aifs_download_ssl' ) ) {
            wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
        }
        $app_settings = aifs_get_app_settings();
        //initialize the Acme Factory class
        $acmeFactory = new AcmeFactory($app_settings['homedir'] . '/' . $app_settings['certificate_directory'], $app_settings['acme_version'], $app_settings['is_staging']);
        //get the path of SSL files
        /*$certificates_directory = $acmeFactory->getCertificatesDir();
        
        			$file_path = $certificates_directory . DS . $_GET['domain'] . DS . $_GET['file'];*/
        $domain_path = $acmeFactory->getDomainPath( $_GET['domain'] );
        $file_path = $domain_path . DS . $_GET['file'];
        $factory = new Factory();
        $factory->download_file( $file_path );
        //wp_redirect($this->aifs_remove_parameters_from_url(get_site_url().$_SERVER['REQUEST_URI'], ['aifsrated']));
    }
}

/**
 * Return first name of the WordPress Admin
 * @return string
 * @since 3.0.0
 */
function aifs_admin_first_name() {
    $admin_email = get_option( 'admin_email' );
    $admin = get_user_by( 'email', $admin_email );
    if ( $admin !== false ) {
        return $admin->first_name;
    } else {
        return "";
    }
}

/**
 * Return last name of the WordPress Admin
 * @return string
 * @since 3.2.14
 */
function aifs_admin_last_name() {
    $admin_email = get_option( 'admin_email' );
    $admin = get_user_by( 'email', $admin_email );
    if ( $admin !== false ) {
        return $admin->last_name;
    } else {
        return "";
    }
}

/**
 * Check if the plugin is free version
 * @return bool
 * @since 3.0.0
 */
function aifs_is_free_version() {
    return aifssl_fs()->is_free_plan();
}

/**
 * Check if licensed for unlimited domains (pro_unlimited plan) and user has set aifs_is_multi_domain = 1
 * @return bool
 * @since 3.0.0
 */
function aifs_can_manage_multi_domain() {
    //For free version always return false
    return false;
}

/**
 * Check if the user can use wildcard SSL
 * @return bool
 * @since 3.2.15
 */
function aifs_use_wildcard() {
    //For free version always return false
    return false;
}

/**
 * Check if the premium license is for unlimited websites
 * @return bool
 * @since 3.0.0
 */
function aifs_license_is_unlimited() {
    //For free version always return false
    return false;
}

/**
 * CSS style for Powered by text
 * @return string
 * @since 3.0.0
 */
function aifs_powered_by_css_style() {
    global $wp_version;
    $version_parts = explode( ".", $wp_version );
    $version_base = (int) $version_parts[0];
    if ( $version_base === 5 || $version_base === 6 ) {
        $style = 'class="header-footer"';
    } else {
        $style = 'id="message" class="updated below-h2 header-footer"';
    }
    return $style;
}

/**
 * Returns the header
 * @return string
 * @since 3.0.6
 */
function aifs_header() {
    return '<h1 class="aifs-header" style=\'background-image: url("' . AIFS_URL . 'assets/img/icon.jpg"); background-color: #ffffff; margin: -3% -1.9% 3% -2%; padding: 2.1% 0; \'>
            		<span style="margin-left: 14%; color: green;">' . AIFS_NAME . ' <sub style="color: gray; font-size: 0.65em;">' . AIFS_VERSION . '</sub></span> <span style="float: right; margin-right: 2%;"><a href="' . menu_page_url( 'aifs_force_https', false ) . '" class="button">' . __( "Force HTTPS", 'auto-install-free-ssl' ) . '</a></span>
        		</h1>';
}

/**
 * Returns Powered by text
 * @return string
 * @since 3.0.0
 */
function aifs_powered_by() {
    if ( aifssl_fs()->can_use_premium_code() ) {
        $help_link = aifssl_fs()->contact_url();
    } else {
        $help_link = "https://freessl.tech/free-ssl-certificate-for-wordpress-website/#help";
    }
    if ( aifs_is_free_version() ) {
        $documentation_link = "https://freessl.tech/wordpress-letsencrypt-free-ssl-certificate-documentation/?utm_source=users_website&utm_medium=dashboard&utm_campaign=aifs_free&utm_content=footer";
    } else {
        $documentation_link = "https://freessl.tech/free-ssl-certificate-for-wordpress-website/#documentation";
    }
    $review_link = "https://wordpress.org/support/plugin/auto-install-free-ssl/reviews/?filter=5#new-post";
    $html = '<div ' . aifs_powered_by_css_style() . ' style="margin-top: 4%;">
            <p>' . __( "Need Help?", 'auto-install-free-ssl' ) . ' <a href="' . $help_link . '" target="_blank">' . __( "click here", 'auto-install-free-ssl' ) . '</a> <span style="margin-left: 15%;">' . __( "For documentation", 'auto-install-free-ssl' ) . ', <a href="' . $documentation_link . '" target="_blank">' . __( "click here", 'auto-install-free-ssl' ) . '</a>.</span> ';
    if ( get_option( 'aifs_display_review' ) !== false ) {
        /* translators: %1$s: Opening HTML 'a' tag; %2$s: Closing 'a' tag; (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
        $html .= '<span style="float: right; margin-right: 2%;">' . sprintf( __( 'Please rate us %1$s★★★★★%2$s on %1$sWordPress.org%2$s to help us spread the word.', 'auto-install-free-ssl' ), '<a href="' . $review_link . '" target="_blank">', '</a>' ) . '</span>';
    }
    $html .= '</p>
        	</div>';
    return $html;
}

/**
 * Show the contact submenu item only when the user have a valid non-expired license.
 *
 * @param $is_visible The filtered value. Whether the submenu item should be visible or not.
 * @param $menu_id    The ID of the submenu item.
 *
 * @return bool If true, the menu item should be visible.
 */
function aifs_is_submenu_visible(  $is_visible, $menu_id  ) {
    if ( 'contact' != $menu_id ) {
        return $is_visible;
    }
    return aifssl_fs()->can_use_premium_code();
}

aifssl_fs()->add_filter(
    'is_submenu_visible',
    'aifs_is_submenu_visible',
    10,
    2
);
function aifs_deactivation_text(  $uninstall_reasons  ) {
    $html = '<div class="card block-body" style="width: 100%; padding-left: 2%; margin-left: -1%; margin-top: -3.5%;">';
    if ( aifs_is_free_version() ) {
        $documentation_link = "https://freessl.tech/wordpress-letsencrypt-free-ssl-certificate-documentation/?utm_source=users_website&utm_medium=dashboard&utm_campaign=aifs_free&utm_content=deactivation_promo";
        $html .= '<p><a href="' . $documentation_link . '" style="text-decoration: none;"><strong>' . __( "WAIT, did you read our documentation?", 'auto-install-free-ssl' ) . '</strong></a><a class="aifs-review-now aifs-review-button" style="margin-left: 5%;" href="' . $documentation_link . '">' . __( "Click here & Read it", 'auto-install-free-ssl' ) . '</a></p>
	         <a href="' . $documentation_link . '" style="text-decoration: none;">
	         <p>' . __( "Experiencing challenges? By investing just a few minutes in our VIDEO and written documentation and successfully implementing it, our free SSL plugin can lead to significant cost savings for you.", 'auto-install-free-ssl' ) . '</p>
	         </a>';
    } else {
    }
    $html .= '</div>';
    if ( !(aifssl_fs()->is_paying() && !aifssl_fs()->is_premium()) ) {
        $uninstall_reasons['long-term'][] = $uninstall_reasons['short-term'][] = $uninstall_reasons['non-registered-and-non-anonymous-short-term'][] = array(
            'id'                => 200,
            'text'              => $html,
            'input_type'        => '',
            'input_placeholder' => 'aifsdeactivationpromo',
        );
    }
    return $uninstall_reasons;
}

aifssl_fs()->add_filter( 'uninstall_reasons', 'aifs_deactivation_text' );
/**
 * Get the CA termsOfService URL
 * @return mixed|string
 * @since 4.3.0
 */
function aifs_get_ca_terms_of_service_url() {
    // Fetch the option from the WordPress database
    $tos = get_option( 'aifs_ca_terms_of_service_url' );
    $current_time = time();
    $two_days_ago = $current_time - 2 * 24 * 60 * 60;
    // 2 days ago in seconds
    // Check if the option exists and 'last_updated' is greater than two days ago
    if ( $tos && is_array( $tos ) && isset( $tos['url'] ) && isset( $tos['last_updated'] ) && $tos['last_updated'] >= $two_days_ago ) {
        return $tos['url'];
    } else {
        // If no option exists or it's older than 2 days, update the option
        $client = new Client(AIFS_LE_ACME_V2_LIVE);
        $terms_url = $client->getTermsOfService();
        // Update the option in the database
        $tos_data = array(
            'url'          => $terms_url,
            'last_updated' => $current_time,
        );
        update_option( 'aifs_ca_terms_of_service_url', $tos_data, false );
        // Return the updated URL
        return $terms_url;
    }
}
