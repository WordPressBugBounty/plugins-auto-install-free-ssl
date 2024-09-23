<?php

/**
 * @package Auto-Install Free SSL
 * This package is a WordPress Plugin. It issues and installs free SSL certificates in cPanel shared hosting with complete automation.
 *
 * @author Free SSL Dot Tech <support@freessl.tech>
 * @copyright  Copyright (C) 2019-2020, Anindya Sundar Mandal
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @link       https://freessl.tech
 * @since      Class available since Release 2.0.0
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

use AutoInstallFreeSSL\FreeSSLAuto\Email;
use AutoInstallFreeSSL\FreeSSLAuto\Logger;
/**
 * Class to force SSL
 *  @since 2.0.0
 *
 */
class ForceSSL {
    /**
     *
     *
     * Add action hooks
     *
     * @since 2.0.0
     * 
     */
    private $logger;

    /**
     * @var false|mixed|void
     */
    private $admin_email;

    private $factory;

    public function __construct() {
        if ( !defined( 'ABSPATH' ) ) {
            die( __( "Access denied", 'auto-install-free-ssl' ) );
        }
        //Following function should be called for any value of aifs_force_ssl, so that revert to HTTP possible from admin dashboard
        add_action( 'admin_init', array($this, 'aifs_force_ssl_controller') );
        if ( get_option( 'aifs_force_ssl' ) == 1 ) {
            /* Go back to HTTP if it triggered */
            add_action( 'wp', array($this, 'revert_force_ssl') );
            /* 301 HTTPS redirection */
            add_action( 'wp_loaded', array($this, 'force_ssl'), 20 );
            /* Fix mixed content */
            if ( is_admin() ) {
                add_action( "admin_init", array($this, 'start_buffer_wp'), 100 );
            } else {
                add_action( "init", array($this, 'start_buffer_wp') );
            }
            add_action( "shutdown", array($this, 'end_buffer_wp'), 999 );
        }
        $this->admin_email = get_option( 'admin_email' );
        $this->factory = new Factory();
        $this->logger = new Logger();
    }

    /**
     *
     *
     * Revert to HTTP using secret nonce (i.e., link)
     *
     * @since 2.0.0
     */
    public function revert_force_ssl() {
        $revert_nonce = ( get_option( 'aifs_revert_http_nonce' ) ? get_option( 'aifs_revert_http_nonce' ) : false );
        if ( isset( $_GET['aifs_revert_http_nonce'] ) && $revert_nonce != false ) {
            if ( $revert_nonce == $_GET['aifs_revert_http_nonce'] ) {
                update_option( 'aifs_force_ssl', 0 );
                //Update siteurl and home options with HTTP
                update_option( 'siteurl', str_ireplace( 'https:', 'http:', get_option( 'siteurl' ) ) );
                update_option( 'home', str_ireplace( 'https:', 'http:', get_option( 'home' ) ) );
                exit( __( "Your website reverted to HTTP successfully. Now you can access your website over http://", 'auto-install-free-ssl' ) );
            } else {
                wp_die( __( "Access was denied due to an invalid secret code. Please use the link in the latest email (when you activated force HTTPS last time).", 'auto-install-free-ssl' ) );
            }
        }
    }

    /**
     * Force SSL redirect
     * Improved since 3.6.3
     *
     * @since 2.0.0
     */
    public function force_ssl() {
        /* Force SSL for javascript if not WordPress dashboard - improved since 3.6.3 */
        if ( !is_admin() ) {
            add_action( 'wp_print_scripts', array($this, 'force_ssl_javascript') );
        }
        /* Force SSL wordpress redirect */
        add_action(
            'wp',
            array($this, 'force_ssl_wp'),
            40,
            3
        );
    }

    /**
     *
     *
     * Force enable SSL with javascript
     *
     * @since 2.0.0
     */
    public function force_ssl_javascript() {
        $script = '<script>';
        $script .= 'if (document.location.protocol != "https:") {';
        $script .= 'document.location = document.URL.replace(/^http:/i, "https:");';
        $script .= '}';
        $script .= '</script>';
        echo $script;
    }

    /**
     *
     *
     * Force SSL with WordPress 301 redirect
     *
     * @since 2.0.0
     */
    public function force_ssl_wp() {
        if ( !is_ssl() ) {
            $redirect_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            wp_redirect( $redirect_url, 301 );
            exit;
        }
    }

    /**
     *
     *
     * Filter the buffer to convert all HTTP to HTTPS
     *
     * @since 2.0.0
     */
    public function filter_buffer_wp( $buffer ) {
        if ( substr( $buffer, 0, 5 ) == "<?xml" ) {
            return $buffer;
        }
        $home = str_replace( "https://", "http://", get_option( 'home' ) );
        $home_no_www = str_replace( "://www.", "://", $home );
        $home_yes_www = str_replace( "://", "://www.", $home_no_www );
        /* In the escaped version we only replace the home_url, not it's www or non-www counterpart. So it may not be used. */
        $escaped_home = str_replace( "/", "\\/", $home );
        $search_array = array(
            $home_yes_www,
            $home_no_www,
            $escaped_home,
            "src='http://",
            'src="http://'
        );
        $ssl_array = str_replace( array("http://", "http:\\/\\/"), array("https://", "https:\\/\\/"), $search_array );
        /* Replace these links now */
        $buffer = str_replace( $search_array, $ssl_array, $buffer );
        /* replace all HTTP links except hyperlinks */
        /* all tags with src attr are already fixed by str_replace */
        $pattern = array(
            '/url\\([\'"]?\\K(http:\\/\\/)(?=[^)]+)/i',
            '/<link [^>]*?href=[\'"]\\K(http:\\/\\/)(?=[^\'"]+)/i',
            '/<meta property="og:image" [^>]*?content=[\'"]\\K(http:\\/\\/)(?=[^\'"]+)/i',
            '/<form [^>]*?action=[\'"]\\K(http:\\/\\/)(?=[^\'"]+)/i'
        );
        $buffer = preg_replace( $pattern, 'https://', $buffer );
        /* handle multiple images in srcset */
        $buffer = preg_replace_callback( '/<img[^\\>]*[^\\>\\S]+srcset=[\'"]\\K((?:[^"\'\\s,]+\\s*(?:\\s+\\d+[wx])(?:,\\s*)?)+)["\']/', array($this, 'replace_srcset_wp'), $buffer );
        return $buffer;
    }

    /**
     *
     *
     * Replace HTTP to HTTPS
     *
     * @since 2.0.0
     */
    public function replace_srcset_wp( $matches ) {
        return str_replace( "http://", "https://", $matches[0] );
    }

    /**
     *
     *
     * Start buffer
     *
     * @since 2.0.0
     */
    public function start_buffer_wp() {
        ob_start( array($this, 'filter_buffer_wp') );
    }

    /**
     *
     *
     * End buffer
     *
     * @since 2.0.0
     */
    public function end_buffer_wp() {
        if ( ob_get_length() ) {
            ob_end_flush();
        }
    }

    /**
     *
     *
     * Force SSL form: pass 1 to get activation form, 0 for deactivation form
     * 
     * @param int $aifs_force_ssl
     * @return string
     * 
     * @since 2.0.0
     */
    /* public function force_ssl_form(int $aifs_force_ssl = 1){
     *  Removing parameter type hint to make compatible with PHP 5.6. Using scalar type hints like string is supported since PHP 7.
     */
    public function force_ssl_form( $aifs_force_ssl = 1 ) {
        $html = '<form method="post" style="margin-top: 4%;">
        			 <input type="hidden" name="aifs_force_ssl" value="' . $aifs_force_ssl . '" />' . wp_nonce_field(
            'aifsforcessl',
            'aifs-activate-force-ssl',
            false,
            false
        );
        if ( $aifs_force_ssl ) {
            $confirmation_text = __( "Are you sure you want to activate force HTTPS?", 'auto-install-free-ssl' );
            $button_text = __( "Activate Force HTTPS", 'auto-install-free-ssl' );
            $css_class = "button button-primary button-hero";
        } else {
            $confirmation_text = __( "Do you want to Deactivate force HTTPS and revert to HTTP?", 'auto-install-free-ssl' );
            $button_text = __( "Revert to HTTP", 'auto-install-free-ssl' );
            $css_class = "button page-title-action";
        }
        $html .= '<button type="submit" name="aifs_submit" class="' . $css_class . '" onclick="return aifs_confirm(\'' . $confirmation_text . '\')">' . $button_text . '</button>
      			</form>';
        return $html;
    }

    /**
     * Creates the UI of force HTTPS activation and deactivation.
     * Need to wrap in <table><tr></tr></table>
     *
     * @param string $step_number
     * @param boolean $display_all
     * @param array $all_domains
     * @param int $padding_bottom_percent
     * @param boolean $include_td
     *
     * @return string
     * @since 3.0.0 (code exist since 2.0.0)
     */
    public function force_ssl_ui(
        $step_number = "",
        $display_all = false,
        $all_domains = [],
        $padding_bottom_percent = 1,
        $include_td = true
    ) {
        $site_url = aifs_get_domain( false );
        $ssl_issued_to_this_domain = $this->factory->is_ssl_issued_and_valid( $site_url );
        $html = "\n                <!-- Force HTTPS section -->\n                ";
        if ( $display_all || $ssl_issued_to_this_domain ) {
            if ( $include_td ) {
                $html .= '<td class="card block-body" style="padding-left: 1.5%; padding-bottom: ' . $padding_bottom_percent . '%;">';
            }
            if ( !get_option( 'aifs_force_ssl' ) ) {
                $html .= '<h3 class="block-title">' . $step_number . " " . __( "Activate Force HTTPS", 'auto-install-free-ssl' ) . '</h3>';
                if ( ($display_all || !get_option( 'aifs_ssl_installed_on_this_website' )) && !is_ssl() ) {
                    $html .= '<p style="color: red; font-weight: bold;">' . __( "Do this only if an SSL certificate has been installed on this website.", 'auto-install-free-ssl' ) . '</p>';
                }
                $html .= '<p>' . __( "To remove the mixed content warning and see a padlock in the browser's address bar, you need to click the button below (only once). This click will activate force SSL, and all your website resources will load over HTTPS.", 'auto-install-free-ssl' ) . '</p>';
                if ( !is_ssl() ) {
                    $html .= '<p>' . __( "Clicking this button will immediately force your website to load over HTTPS and may prompt you to log in again.", 'auto-install-free-ssl' ) . '</p>';
                    $html .= '<p><strong>' . __( "WARNING", 'auto-install-free-ssl' ) . ':</strong> ';
                    /* translators: %s: A website address with a hyperlink, e.g., https://example.com */
                    $html .= sprintf( __( "If the SSL certificate is not installed correctly, clicking this button may cause issues accessing the website. So, please click this link first: %s and check for HTTPS in the address bar.", 'auto-install-free-ssl' ), '<a href="https://' . $site_url . '" target="_blank">https://' . $site_url . '</a>' ) . '</p>';
                }
                $html .= '<p>' . __( "If you face issues after clicking this button, please revert to HTTP.", 'auto-install-free-ssl' ) . '<strong> ' . __( "Please don't worry; as soon as you click the button, we'll send you an automated email with a link. If you need to revert to HTTP, click that link.", 'auto-install-free-ssl' ) . '</strong> ' . __( "Please check your spam folder if you don't find that email in your inbox.", 'auto-install-free-ssl' ) . '</p>';
                /* translators: %s: HTML code to create a hyperlink with the text 'click here' */
                $html .= '<p>' . sprintf( __( "But if the issue persists even after you click that link, please %sclick here%s for documentation on more options on how to revert to HTTP.", 'auto-install-free-ssl' ), '<a href="https://freessl.tech/free-ssl-certificate-for-wordpress-website/#reverthttp" target="_blank">', '</a>' ) . '</p>';
                $html .= $this->force_ssl_form( 1 );
            } else {
                $html .= '<h3 class="block-title">' . __( "Optional: Deactivate Force HTTPS", 'auto-install-free-ssl' ) . '</h3>';
                $html .= '<p>' . __( "If force HTTPS is causing issues with your website, click the button below to Deactivate the force HTTPS feature and revert to HTTP. After you fix the SSL issues, you may activate force HTTPS again.", 'auto-install-free-ssl' ) . '</p>';
                /* Display revert button */
                $html .= $this->force_ssl_form( 0 );
            }
            if ( $include_td ) {
                $html .= '</td>';
            }
        } else {
            if ( $include_td ) {
                $html .= '<td class="card block-body">';
            }
            $html .= '<h3 style="line-height: 2em;">' . __( "No SSL is installed on this website. Please install an SSL, and you'll get the option to Activate Force HTTPS.", 'auto-install-free-ssl' ) . '</h3>';
            if ( $include_td ) {
                $html .= '</td>';
            }
        }
        return $html;
    }

    /**
     * Check for valid SSL and Set value of aifs_force_ssl
     * 
     * @since 2.0.0
     */
    public function aifs_force_ssl_controller() {
        /*
         * Override: http://www.example.com/wp-admin/admin.php?page=aifs_force_https&aifsaction=aifs_force_https_override&checked_ssl_manually=done&valid_ssl_installed=yes
         */
        if ( isset( $_POST['aifs-activate-force-ssl'] ) ) {
            if ( !wp_verify_nonce( $_POST['aifs-activate-force-ssl'], 'aifsforcessl' ) ) {
                wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
            } else {
                /* Check if a valid SSL installed on this website - START */
                if ( isset( $_POST['aifs_force_ssl'] ) && absint( $_POST['aifs_force_ssl'] ) == 1 ) {
                    $ssl_details = $this->factory->is_ssl_installed_on_this_website();
                    if ( $ssl_details !== true && !$ssl_details['domain_site']['ssl_installed'] ) {
                        //Make the text
                        if ( !$ssl_details['domain_other_version']['ssl_installed'] ) {
                            $text_display = sprintf(
                                /* translators: %1$s: Opening HTML 'strong' tag; %2$s: Closing 'strong' tag; %3$s: A domain name, e.g., example.com; %4$s: Another domain name, e.g., www.example.com; (Opening and closing 'strong' tags make the enclosed text bold.) */
                                __( 'No %1$svalid%2$s SSL is installed on %3$s and %4$s. %1$sPlease install an SSL certificate on this website and try again.%2$s', 'auto-install-free-ssl' ),
                                '<strong>',
                                '</strong>',
                                $ssl_details['domain_site']['url'],
                                $ssl_details['domain_other_version']['url']
                            );
                            if ( strcmp( $ssl_details['domain_site']['error_cause'], $ssl_details['domain_other_version']['error_cause'] ) == 0 ) {
                                $text_display .= "<br />" . __( "Error cause", 'auto-install-free-ssl' ) . ": " . $ssl_details['domain_site']['error_cause'] . ".";
                            } else {
                                $text_display .= "<br />" . __( "Error cause for", 'auto-install-free-ssl' ) . " " . $ssl_details['domain_site']['url'] . ": " . $ssl_details['domain_site']['error_cause'] . ". " . __( "Error cause for", 'auto-install-free-ssl' ) . " " . $ssl_details['domain_other_version']['url'] . ": " . $ssl_details['domain_other_version']['error_cause'] . ".";
                            }
                        } else {
                            $general_settings = admin_url( 'options-general.php' );
                            $link_title = __( "Click here to change WordPress Address (URL) & Site Address (URL)", 'auto-install-free-ssl' );
                            $text_display = sprintf(
                                /* translators: %1$s: A domain name, e.g., example.com; %2$s: Another domain name, e.g., www.example.com; %3$s: Opening HTML 'strong' tag; %4$s: Opening HTML 'a' tag; %5$s: Closing 'a' tag; %6$s: Closing 'strong' tag. (Opening and closing 'strong' tags make the enclosed text bold. Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
                                __( 'The installed SSL covers only %1$s. But it does not cover %2$s. %3$sPlease either change your %4$sWordPress Address (URL) & Site Address (URL)%5$s or install an SSL certificate that covers %2$s. %6$s', 'auto-install-free-ssl' ),
                                $ssl_details['domain_other_version']['url'],
                                $ssl_details['domain_site']['url'],
                                '<strong>',
                                '<a href="' . $general_settings . '" title="' . $link_title . '">',
                                '</a>',
                                '</strong>'
                            );
                            $text_display .= __( "Error cause", 'auto-install-free-ssl' ) . ": " . $ssl_details['domain_site']['error_cause'] . ".";
                        }
                        aifs_add_flash_notice( $text_display, 'error' );
                        return;
                    }
                }
                /* Check if a valid SSL installed on this website - END */
                $this->aifs_force_ssl_implement( absint( $_POST['aifs_force_ssl'] ) );
            }
        }
    }

    /**
     * Set value of aifs_force_ssl
     *
     * @param int $force_ssl
     * @since 3.2.10
     */
    public function aifs_force_ssl_implement( $force_ssl ) {
        if ( update_option( 'aifs_force_ssl', $force_ssl ) ) {
            //set 'aifs_display_review' = 1 if this option doesn't exist
            /*if(!get_option('aifs_display_review'))
            		add_option('aifs_display_review', 1);*/
            if ( $force_ssl == 1 ) {
                $revert_nonce = uniqid( 'aifs' ) . time() . uniqid();
                if ( update_option( 'aifs_revert_http_nonce', $revert_nonce ) ) {
                    $this->aifs_send_revert_nonce_by_email( $revert_nonce );
                    $success_text = __( "Congratulations! Force HTTPS has been activated successfully.", 'auto-install-free-ssl' );
                    $this->logger->write_log( 'info', $success_text, [
                        'event' => 'ping',
                    ] );
                    //Display success/activated message (notice)
                    aifs_add_flash_notice( $success_text );
                }
                //Update siteurl and home options with HTTPS - this is required to fix dynamic CSS issue with premium themes
                update_option( 'siteurl', str_ireplace( 'http:', 'https:', get_option( 'siteurl' ) ) );
                update_option( 'home', str_ireplace( 'http:', 'https:', get_option( 'home' ) ) );
                //redirect to plugin main page, so that HTTPS be forced immediately. This will send the user to the login page over HTTPS.
                if ( !is_ssl() ) {
                    //$redirect_url = "https://".aifs_get_domain()."/wp-login.php?redirect_to=".urlencode(admin_url('admin.php?page=auto_install_free_ssl'));
                    wp_redirect( admin_url( 'admin.php?page=auto_install_free_ssl' ) );
                    exit;
                }
            } else {
                //Update siteurl and home options with HTTP
                update_option( 'siteurl', str_ireplace( 'https:', 'http:', get_option( 'siteurl' ) ) );
                update_option( 'home', str_ireplace( 'https:', 'http:', get_option( 'home' ) ) );
                $success_text = __( "Force HTTPS has been Deactivated successfully, and you have reverted to HTTP.", 'auto-install-free-ssl' );
                $this->logger->write_log( 'info', $success_text, [
                    'event' => 'ping',
                ] );
                //Display success message (Deactivated)
                aifs_add_flash_notice( $success_text );
            }
        }
    }

    /**
     *
     *
     * Send the revert nonce by email
     * 
     * @param string $revert_nonce
     * 
     * @since 2.0.0
     */
    public function aifs_send_revert_nonce_by_email( $revert_nonce ) {
        $app_settings = aifs_get_app_settings();
        //Send email to admin email id and Let's Encrypt registrant email id (provided in basic settings) if both are different
        if ( in_array( $this->admin_email, $app_settings['admin_email'] ) ) {
            $to = implode( ',', $app_settings['admin_email'] );
        } else {
            $to = $this->admin_email . "," . implode( ',', $app_settings['admin_email'] );
        }
        $revert_url = str_replace( 'https:', 'http:', site_url() ) . "/?aifs_revert_http_nonce=" . $revert_nonce;
        /* translators: %1$s: Name of this plugin, i.e., 'Auto-Install Free SSL'; %2$s: A domain name, e.g., example.com */
        $subject = sprintf( __( '\'%1$s\' has activated Force HTTPS on your website %2$s.', 'auto-install-free-ssl' ), AIFS_NAME, aifs_get_domain( false ) );
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From:wordpress@' . aifs_get_domain();
        //Email body
        $body = "<html><body>";
        $body .= __( "Hello", 'auto-install-free-ssl' ) . " " . aifs_admin_first_name() . ",<br /><br />";
        /* translators: %s: A domain name, e.g., example.com */
        $body .= sprintf( __( "We have successfully activated Force HTTPS on your website, %s.", 'auto-install-free-ssl' ), aifs_get_domain( false ) ) . "<br /><br />";
        $body .= sprintf(
            /* translators: %1$s and %2$s: HTML code to make the enclosed text bold. Please keep their order the same. Otherwise, you'll break the style. %3$s: An email id. */
            __( 'Please refresh your website to get the padlock. Do not you see it? If your theme can %1$sregenerate CSS files%2$s, please do it. Otherwise, you can just search the theme and plugins for %1$shardcoded URLs%2$s, if any, and fix them. Do not hesitate to contact us at %3$s for any help.', 'auto-install-free-ssl' ),
            '<strong>',
            '</strong>',
            '<em>support@freessl.tech</em>'
        ) . '<br /><br />';
        $body .= __( "You may face issues if the SSL certificate is not installed correctly or an invalid SSL certificate is installed on your website. Your WordPress website may be inaccessible too. In that case, please click the link given below to deactivate force HTTPS and revert to HTTP.", 'auto-install-free-ssl' ) . "<br />";
        $body .= "<a href='{$revert_url}'>{$revert_url}</a><br /><br />";
        $body .= __( "Clicking the above link will instantly deactivate force HTTPS and revert your website to HTTP.", 'auto-install-free-ssl' ) . "<br /><br />";
        /* translators: %s: placeholders for HTML code create a hyperlink with the word 'click here'. */
        $body .= sprintf( __( "But if the issue persists, %sclick here%s for documentation on more options on how to revert to HTTP.", 'auto-install-free-ssl' ), '<a href="https://freessl.tech/free-ssl-certificate-for-wordpress-website/#reverthttp" target="_blank">', '</a>' ) . "<br /><br />";
        $email = new Email();
        $body .= $email->add_review_request_in_email();
        $body .= $email->add_email_signature();
        $body .= "</body></html>";
        //now send the email
        if ( wp_mail(
            $to,
            $subject,
            $body,
            $headers
        ) ) {
            $this->logger->write_log( 'info', __( "Revert URL was sent successfully by email.", 'auto-install-free-ssl' ), [
                'event' => 'ping',
            ] );
        } else {
            $this->logger->write_log( 'info', __( "Sorry, there was an issue sending the Revert URL by email.", 'auto-install-free-ssl' ), [
                'event' => 'ping',
            ] );
        }
    }

}
