<?php

/**
 * @package Auto-Install Free SSL
 * 
 * Plugin Name: Auto-Install Free SSL
 * Plugin URI:  https://freessl.tech
 * Description: Generate & install Free SSL Certificates, activate force HTTPS redirect with one click to fix insecure links & mixed content warnings, and get automatic Renewal Reminders.
 * Version:     5.0.1
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
 * @copyright  Copyright (C) 2019-2024, Anindya Sundar Mandal - anindya@SpeedUpWebsite.info
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
    die( "Access denied" );
}
use AutoInstallFreeSSL\FreeSSLAuto\Acme\Factory as AcmeFactory;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\HomeOptions;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\Factory;
use AutoInstallFreeSSL\FreeSSLAuto\Email;
use AutoInstallFreeSSL\FreeSSLAuto\Acme\Client;
/*
 * L-13: verified, no change required -- the original finding's description of
 * this structure was inaccurate. For the record, since the misleading `//}` at
 * the end of the Freemius block below invites the wrong reading:
 *
 * This `else` opens here and closes at line 175 (confirmed with token_get_all()
 * brace-depth analysis) -- NOT at the end of the file. It contains the AIFS_*
 * constants, the vendor/autoload.php and includes/helpers.php requires, and the
 * ForceSSL `init` hook. Everything after line 175, including the `is_admin()`
 * block near the end of the file, runs unconditionally.
 *
 * The gating is deliberate re-entrancy protection. Freemius parallel activation
 * is enabled for this plugin (see 'parallel_activation' below), so free and
 * premium can both load and aifssl_fs() can already exist. Re-running this block
 * would redefine constants and double-register hooks. It is safe to skip because
 * PHP constants, declared functions and a registered autoloader are all
 * process-global: whichever copy loads second inherits them from the first.
 *
 * Known limitation (accepted): AIFS_DIR / AIFS_URL / AIFS_*_PLUGIN_BASENAME
 * resolve against whichever copy loaded FIRST. Under parallel activation that
 * state is transient -- Freemius deactivates one copy shortly after -- so this
 * is not worth the risk of restructuring the bootstrap.
 */
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
                    'id'                  => '10204',
                    'slug'                => 'auto-install-free-ssl',
                    'type'                => 'plugin',
                    'public_key'          => 'pk_8e6c4ffc369c2a116adf5dd4fc982',
                    'is_premium'          => false,
                    'has_addons'          => false,
                    'has_paid_plans'      => true,
                    'has_affiliation'     => 'selected',
                    'menu'                => array(
                        'slug'       => 'auto_install_free_ssl',
                        'first-path' => 'admin.php?page=auto_install_free_ssl',
                    ),
                    'parallel_activation' => array(
                        'enabled'                  => true,
                        'premium_version_basename' => 'auto-install-free-ssl-premium/auto-install-free-ssl.php',
                    ),
                    'is_live'             => true,
                    'is_org_compliant'    => true,
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
    //}
    // Define Directory Separator to make the default DIRECTORY_SEPARATOR short
    if ( !defined( 'AIFS_DS' ) ) {
        //formerly 'DS'; renamed @since 4.5.0
        define( 'AIFS_DS', DIRECTORY_SEPARATOR );
    }
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $plugin_data = get_plugin_data( __FILE__, false, false );
    // @since 4.5.0 to fix the PHP notice "Function _load_textdomain_just_in_time was called incorrectly"
    define( 'AIFS_VERSION', $plugin_data['Version'] );
    define( 'AIFS_DIR', plugin_dir_path( __FILE__ ) );
    define( 'AIFS_URL', plugin_dir_url( __FILE__ ) );
    define( 'AIFS_NAME', $plugin_data['Name'] );
    $wp_upload_directory = wp_upload_dir();
    define( 'AIFS_UPLOAD_DIR', $wp_upload_directory['basedir'] . AIFS_DS . 'auto-install-free-ssl' );
    define( 'AIFS_DEFAULT_LE_ACME_VERSION', 2 );
    define( 'AIFS_LE_ACME_V2_LIVE', 'https://acme-v02.api.letsencrypt.org' );
    define( 'AIFS_LE_ACME_V2_STAGING', 'https://acme-staging-v02.api.letsencrypt.org' );
    //@since 4.5.0
    define( 'AIFS_FREE_PLUGIN_BASENAME', aifssl_fs()->get_slug() . AIFS_DS . basename( __FILE__ ) );
    define( 'AIFS_PREMIUM_PLUGIN_BASENAME', aifssl_fs()->get_premium_slug() . AIFS_DS . basename( __FILE__ ) );
    if ( file_exists( __DIR__ . AIFS_DS . 'aifs-config.php' ) ) {
        require_once __DIR__ . AIFS_DS . 'aifs-config.php';
    }
    //if ( aifssl_fs()->can_use_premium_code__premium_only() ) {
    if ( !defined( 'AIFS_ENC_KEY' ) ) {
        define( 'AIFS_ENC_KEY', SECURE_AUTH_KEY );
        //@since 2.1.1
    }
    //}
    if ( !function_exists( 'aifs_findRegisteredDomain' ) && !function_exists( 'aifs_getRegisteredDomain' ) && !function_exists( 'aifs_validDomainPart' ) ) {
        require_once AIFS_DIR . AIFS_DS . 'vendor' . AIFS_DS . 'usrflo' . AIFS_DS . 'registered-domain-libs' . AIFS_DS . 'PHP' . AIFS_DS . 'effectiveTLDs.inc.php';
        require_once AIFS_DIR . AIFS_DS . 'vendor' . AIFS_DS . 'usrflo' . AIFS_DS . 'registered-domain-libs' . AIFS_DS . 'PHP' . AIFS_DS . 'regDomain.inc.php';
    }
    if ( file_exists( AIFS_DIR . 'vendor' . AIFS_DS . 'autoload.php' ) ) {
        require_once AIFS_DIR . 'vendor' . AIFS_DS . 'autoload.php';
    }
    if ( file_exists( AIFS_DIR . 'includes' . AIFS_DS . 'helpers.php' ) ) {
        require_once AIFS_DIR . 'includes' . AIFS_DS . 'helpers.php';
    }
    /**
     * Force SSL on frontend and backend
     */
    //new ForceSSL();
    //ForceSSL::getInstance();
    // accepted_args=0: WP must not pass a null placeholder into getInstance( $register_hooks ).
    add_action(
        'init',
        array('AutoInstallFreeSSL\\FreeSSLAuto\\Admin\\ForceSSL', 'getInstance'),
        12,
        0
    );
}
if ( !function_exists( 'aifs_home_menu' ) ) {
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
     * Hard-stop when the environment is incompatible (admin UI or forced contexts such as cron.php).
     * Frontend and WP-Cron soft-bail elsewhere; Plugins / AJAX screens stay reachable.
     *
     * @since 4.6.2
     * @param bool $force_stop When true, always die (used by standalone cron.php).
     * @return bool True when incompatible (and hard-stop skipped); false when compatible.
     */
    function aifs_maybe_stop_for_environment_errors(  $force_stop = false  ) {
        $errors = aifs_get_environment_errors();
        if ( empty( $errors ) ) {
            return false;
        }
        if ( !$force_stop ) {
            // Do not take down the public site; Force HTTPS does not need these extensions.
            if ( !is_admin() ) {
                return true;
            }
            if ( aifs_should_skip_environment_hard_stop() ) {
                return true;
            }
        }
        $messages = aifs_get_environment_error_messages( $errors );
        $log_msg = implode( '<br />', $messages );
        $log_msg .= '<br /><br />' . sprintf( 
            /* translators: %s: URL to the Plugins admin screen */
            __( 'Please <a href="%s">click here</a> to return to the plugins page.', 'auto-install-free-ssl' ),
            esc_url( admin_url( 'plugins.php' ) )
         );
        if ( function_exists( 'wp_die' ) ) {
            wp_die( $log_msg, esc_html( ( defined( 'AIFS_NAME' ) ? AIFS_NAME : 'Auto-Install Free SSL' ) ), array(
                'response'  => 500,
                'back_link' => true,
            ) );
        }
        die( wp_strip_all_tags( str_replace( '<br />', "\n", $log_msg ) ) );
    }

    /**
     * Admin notice when the environment is incompatible (visible on Plugins screen escape hatch).
     *
     * @since 4.7.1
     */
    function aifs_admin_notice_environment_errors() {
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        $errors = aifs_get_environment_errors();
        if ( empty( $errors ) ) {
            return;
        }
        $messages = aifs_get_environment_error_messages( $errors );
        echo '<div class="notice notice-error"><p><strong>' . esc_html( ( defined( 'AIFS_NAME' ) ? AIFS_NAME : 'Auto-Install Free SSL' ) ) . ':</strong> ';
        echo esc_html__( 'This plugin cannot run on this server until the following requirements are met:', 'auto-install-free-ssl' );
        echo '</p><ul style="list-style:disc;margin-left:1.5em;">';
        foreach ( $messages as $message ) {
            echo '<li>' . esc_html( $message ) . '</li>';
        }
        echo '</ul></div>';
    }

    /**
     * Proactive admin notice when this site's own WP_HTTP_BLOCK_EXTERNAL setting is blocking
     * outgoing ACME requests without an accessible-hosts allow-list entry for the Let's Encrypt
     * API host(s). Complements Acme\Client::curl()'s reactive log-time message (shown only when
     * an ACME request actually fails) with a proactive warning visible before that happens.
     *
     * Shown only when the risk condition actually holds -- see
     * aifs_wp_http_block_external_puts_acme_at_risk() -- so compatible installs are never nagged.
     *
     * @since 5.0.0
     * @param bool|null   $block_external   Test-only override, see aifs_wp_http_block_external_puts_acme_at_risk().
     * @param string|null $accessible_hosts Test-only override, see aifs_wp_http_block_external_puts_acme_at_risk().
     */
    function aifs_admin_notice_wp_http_block_external(  $block_external = null, $accessible_hosts = null  ) {
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( !function_exists( 'aifs_wp_http_block_external_puts_acme_at_risk' ) || !aifs_wp_http_block_external_puts_acme_at_risk( $block_external, $accessible_hosts ) ) {
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>' . esc_html( ( defined( 'AIFS_NAME' ) ? AIFS_NAME : 'Auto-Install Free SSL' ) ) . ':</strong> ';
        echo esc_html__( "This site blocks outgoing HTTP requests (WP_HTTP_BLOCK_EXTERNAL is enabled in wp-config.php) and has not allow-listed the Let's Encrypt ACME API host. SSL certificate issuance and renewal will fail until you add 'acme-v02.api.letsencrypt.org' and 'acme-staging-v02.api.letsencrypt.org' to WP_ACCESSIBLE_HOSTS in wp-config.php.", 'auto-install-free-ssl' );
        echo '</p></div>';
    }

    /**
     * This function will be called during the plugin activation.
     * Improved since 4.0.0
     * */
    function activate_auto_install_free_ssl() {
        $errors = aifs_get_environment_errors();
        if ( !empty( $errors ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            $messages = aifs_get_environment_error_messages( $errors );
            $log_msg = implode( '<br />', $messages );
            $log_msg .= '<br /><br />' . sprintf( 
                /* translators: %s: URL to the Plugins admin screen */
                __( 'Please <a href="%s">click here</a> to return to the plugins page.', 'auto-install-free-ssl' ),
                esc_url( admin_url( 'plugins.php' ) )
             );
            wp_die( $log_msg, esc_html( ( defined( 'AIFS_NAME' ) ? AIFS_NAME : 'Auto-Install Free SSL' ) ), array(
                'response'  => 500,
                'back_link' => true,
            ) );
        }
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
         * Improved since 4.5.0
         */
        if ( aifs_this_plugin_activation_count() <= 1 ) {
            if ( wp_next_scheduled( 'aifs_do_this_daily' ) ) {
                wp_unschedule_event( wp_next_scheduled( 'aifs_do_this_daily' ), 'aifs_do_this_daily' );
            }
            //Freemius after uninstall action
            //aifssl_fs()->add_action('after_uninstall', 'aifs_uninstall_cleanup');
        }
        //register_uninstall_hook(__FILE__, 'aifs_uninstall_cleanup'); //@since 4.5.0
    }

    /**
     * Delete plugin data on plugin deletion (i.e., uninstallation).
     * This function will be called by Freemius after uninstall action.
     * @since 4.0.0
     */
    function aifs_uninstall_cleanup() {
        if ( is_plugin_inactive( AIFS_FREE_PLUGIN_BASENAME ) && is_plugin_inactive( AIFS_PREMIUM_PLUGIN_BASENAME ) && aifs_this_plugin_installation_count() <= 1 ) {
            //@since 4.5.0
            if ( wp_next_scheduled( 'aifs_do_this_daily' ) ) {
                //@since 4.5.0
                wp_unschedule_event( wp_next_scheduled( 'aifs_do_this_daily' ), 'aifs_do_this_daily' );
                error_log( 'Scheduled event unscheduled.' );
            }
            // The free and premium versions are not active.
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
                    'aifs_delete_plugin_data_on_deactivation',
                    'aifs_enable_ssl_renewal_reminder'
                ];
                // aifs_is_admin_email_invalid
                foreach ( $options as $opt ) {
                    delete_option( $opt );
                }
            }
        }
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
    /*
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
     * Attach the JS
     * @param $hook
     */
    function aifs_add_js_enqueue(  $hook  ) {
        // L-12: `($_GET['page'] === 'auto_install_free_ssl' || $_GET['page'] !==
        // 'aifs_generate_ssl_manually' || $_GET['page'] !== 'aifs_force_https')`
        // was a tautology -- a page slug can't simultaneously equal both
        // 'aifs_generate_ssl_manually' and 'aifs_force_https', so at least one of
        // the two !== terms was always true, making the whole OR (and therefore
        // the outer !(...) bypass below) always true for any free-version
        // existing user on any admin.php?page=... screen -- not just this
        // plugin's own pages, but any other plugin's too, since the allow-list a
        // few lines down was then skipped entirely.
        //
        // The plan's literal suggested fix (AND instead of OR between the two
        // !== terms) still leaves that same over-broad bypass for any page slug
        // other than those exact two, e.g. another plugin's admin.php?page=X
        // screen. Checked what script.js actually needs this bypass for: the
        // '#aifs-tasks' fade-in and the settings AJAX toggle switches are only
        // present on the plugin's own dashboard (auto_install_free_ssl); the
        // aifs_generate_ssl_manually/aifs_force_https-specific behavior
        // (aifs_confirm_initiate(), etc.) is already covered by the allow-list
        // below regardless of this outer bypass. So the intended condition is
        // simply "the plugin's own dashboard page" -- the two broken !== clauses
        // added nothing they didn't already get from the allow-list, and are
        // removed here rather than patched, to close the over-broad-enqueue gap
        // completely instead of narrowing it to two page slugs.
        if ( !(aifs_is_free_version() && aifs_is_existing_user() && isset( $_GET['page'] ) && $_GET['page'] === 'auto_install_free_ssl') ) {
            // Note: for these AIFS submenu pages (registered under the custom
            // 'auto_install_free_ssl' top-level menu), WordPress's admin_enqueue_scripts
            // $hook parameter is never literally the string 'admin.php' -- it's
            // the page hookname (e.g. 'toplevel_page_aifs_cpanel_settings'), per
            // get_plugin_page_hookname(). So 'admin.php' !== $hook below is
            // always true in real usage; the effective filter is the page
            // allow-list that follows it. Left as-is (only the outer tautology
            // above was the L-12 finding); verified this reading against
            // wp-admin/includes/plugin.php's get_plugin_page_hookname() before
            // leaving it alone, rather than assuming $hook could equal 'admin.php'.
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
    // Add the above function to admin_notices
    add_action( 'admin_notices', 'aifs_display_flash_notices', 12 );
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
        $errors = aifs_get_environment_errors();
        if ( !empty( $errors ) ) {
            error_log( 'Auto-Install Free SSL: skipped daily cron due to environment errors: ' . implode( ', ', $errors ) );
            return;
        }
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
            // C-3: this handler was guarded only by a nonce -- no capability check at
            // all -- and built a filesystem path directly from unsanitized
            // $_GET['domain']/$_GET['file'] values, allowing arbitrary file read
            // (directory traversal). Require manage_options first.
            if ( !current_user_can( 'manage_options' ) ) {
                wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
            }
            if ( !wp_verify_nonce( $_GET['aifsdownloadssl'], 'aifs_download_ssl' ) ) {
                wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
            }
            // Whitelist the requested file name to the exact set of certificate
            // artefacts this handler is meant to serve.
            $allowed_files = array(
                'certificate.pem',
                'private.pem',
                'cabundle.pem',
                'fullchain.pem'
            );
            $requested_file = ( isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '' );
            if ( !in_array( $requested_file, $allowed_files, true ) ) {
                wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
            }
            $domain = ( isset( $_GET['domain'] ) ? wp_unslash( $_GET['domain'] ) : '' );
            $app_settings = aifs_get_app_settings();
            //initialize the Acme Factory class
            $acmeFactory = new AcmeFactory($app_settings['homedir'] . '/' . $app_settings['certificate_directory'], $app_settings['acme_version'], $app_settings['is_staging']);
            //get the path of SSL files
            /*$certificates_directory = $acmeFactory->getCertificatesDir();
            
            			$file_path = $certificates_directory . AIFS_DS . $_GET['domain'] . AIFS_DS . $_GET['file'];*/
            $domain_path = $acmeFactory->getDomainPath( $domain );
            if ( false === $domain_path ) {
                wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
            }
            $file_path = $domain_path . AIFS_DS . $requested_file;
            // Defence in depth: the resolved path must actually live inside the
            // certificates directory before we ever try to serve it.
            $real_certificates_dir = realpath( $acmeFactory->getCertificatesDir() );
            $real_file_path = realpath( $file_path );
            if ( false === $real_certificates_dir || false === $real_file_path || 0 !== strpos( $real_file_path, $real_certificates_dir . DIRECTORY_SEPARATOR ) ) {
                wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
            }
            $factory = new Factory();
            $factory->download_file(
                $file_path,
                null,
                false,
                $real_certificates_dir
            );
            //wp_redirect($this->aifs_remove_parameters_from_url(get_site_url().$_SERVER['REQUEST_URI'], ['aifsrated']));
        }
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
            $html .= '<p><a href="' . $documentation_link . '" style="text-decoration: none;"><strong style="color: red;">' . __( "Invest 5 minutes now & save \$\$\$", 'auto-install-free-ssl' ) . '</strong></a><a class="aifs-review-now aifs-review-button" style="margin-left: 5%;" href="' . $documentation_link . '">' . __( "Click here for Documentation", 'auto-install-free-ssl' ) . '</a></p>
	         <a href="' . $documentation_link . '" style="text-decoration: none;">
	         <p><span class="dashicons dashicons-video-alt"></span> &nbsp;&nbsp;' . __( "Are you experiencing challenges? Our Free SSL plugin can lead to significant cost savings if you invest just a few minutes in our VIDEO and written Documentation.", 'auto-install-free-ssl' ) . '</p>
	         </a>';
            /*$html .= '<p><a href="' . $documentation_link . '" style="text-decoration: none;"><strong>' . __( "WAIT, did you read our documentation?", 'auto-install-free-ssl' ) . '</strong></a><a class="aifs-review-now aifs-review-button" style="margin-left: 5%;" href="' . $documentation_link . '">' . __( "Click here & Read it", 'auto-install-free-ssl' ) . '</a></p>
              <a href="' . $documentation_link . '" style="text-decoration: none;">
              <p>' . __( "Experiencing challenges? By investing just a few minutes in our VIDEO and written documentation and successfully implementing it, our free SSL plugin can lead to significant cost savings for you.", 'auto-install-free-ssl' ) . '</p>
              </a>';*/
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
}
/** Environment requirements — hard-stop in admin (except Plugins / AJAX); soft-bail elsewhere. @since 4.7.1 */
if ( function_exists( 'aifs_maybe_stop_for_environment_errors' ) ) {
    add_action( 'admin_init', 'aifs_maybe_stop_for_environment_errors', 1 );
}
if ( function_exists( 'aifs_admin_notice_environment_errors' ) ) {
    add_action( 'admin_notices', 'aifs_admin_notice_environment_errors' );
    add_action( 'network_admin_notices', 'aifs_admin_notice_environment_errors' );
}
/** Proactive WP_HTTP_BLOCK_EXTERNAL / ACME host allow-list warning. @since 5.0.0 */
if ( function_exists( 'aifs_admin_notice_wp_http_block_external' ) ) {
    add_action( 'admin_notices', 'aifs_admin_notice_wp_http_block_external' );
    add_action( 'network_admin_notices', 'aifs_admin_notice_wp_http_block_external' );
}
if ( is_admin() ) {
    // activation hook
    register_activation_hook( __FILE__, 'activate_auto_install_free_ssl' );
    // Deactivation hook
    register_deactivation_hook( __FILE__, 'deactivate_auto_install_free_ssl' );
    if ( aifs_this_plugin_activation_count() <= 1 ) {
        // @since 4.5.0
        //Freemius after uninstall action
        aifssl_fs()->add_action( 'after_uninstall', 'aifs_uninstall_cleanup' );
    }
    /** Add 'Settings' option */
    add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'aifs_add_settings_option_in_plugins_page' );
    /** required for successful redirect */
    add_action( 'admin_init', 'aifs_do_output_buffer' );
    /** AIFS Home page */
    add_action( 'admin_menu', 'aifs_home_menu' );
    /** Implementing Translations - load textdomain */
    add_action( 'init', 'aifs_load_textdomain' );
    /**
     * Register admin pages before heavier AdminNotice bootstrap.
     * Order of these init callbacks controls submenu order (Generate SSL, then Force HTTPS, then Log).
     * accepted_args=0 prevents WP from passing null into getInstance( $register_hooks ).
     * @since 4.7.1
     */
    /** Generate SSL manually (free plan) — first submenu after the dashboard */
    add_action(
        'init',
        array('AutoInstallFreeSSL\\FreeSSLAuto\\Admin\\GenerateSSLmanually', 'getInstance'),
        11,
        0
    );
    /** Force HTTPS page */
    add_action(
        'init',
        array('AutoInstallFreeSSL\\FreeSSLAuto\\Admin\\ForceHttpsPage', 'getInstance'),
        11,
        0
    );
    /** Log page */
    add_action(
        'init',
        array('AutoInstallFreeSSL\\FreeSSLAuto\\Admin\\Log', 'getInstance'),
        11,
        0
    );
    /** Display Admin Notice */
    //new AdminNotice();
    //AdminNotice::getInstance();
    add_action(
        'init',
        array('AutoInstallFreeSSL\\FreeSSLAuto\\Admin\\AdminNotice', 'getInstance'),
        12,
        0
    );
    // @since 4.6.2
    /**
     * Premium admin menus: always register when licensed; soft-fail UI when the environment
     * is incompatible (do not hide menus). @since 4.7.1
     */
    //Add the JS
    add_action( 'admin_enqueue_scripts', 'aifs_add_js_enqueue' );
    add_action( 'admin_enqueue_scripts', 'aifs_admin_styles' );
    add_action( 'admin_init', 'aifs_download_file_handler' );
    /**
     * Filter to Customize Freemius Checkout Parameters
     * @since 4.6.1
     */
    aifssl_fs()->add_filter( 'checkout/parameters', function () {
        return array(
            'show_refund_badge'      => true,
            'refund_policy_position' => 'below_form',
            'show_reviews'           => true,
            'billing_cycle_selector' => 'dropdown',
        );
    } );
}