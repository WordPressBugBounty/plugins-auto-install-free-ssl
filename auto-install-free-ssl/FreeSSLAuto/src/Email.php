<?php

/**
 * @package Auto-Install Free SSL
 * This package is a WordPress Plugin. It issues and installs free SSL certificates in cPanel shared hosting with complete automation.
 *
 * @author Free SSL Dot Tech <support@freessl.tech>
 * @copyright  Copyright (C) 2019-2020, Anindya Sundar Mandal
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @link       https://freessl.tech
 * @since      Class available since Release 1.0.0
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
namespace AutoInstallFreeSSL\FreeSSLAuto;

use DateTime;
use Exception;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\Factory;
class Email {
    private $logger;

    public $admin_email;

    private $factory;

    public function __construct() {
        if ( !defined( 'ABSPATH' ) ) {
            die( __( "Access denied", 'auto-install-free-ssl' ) );
        }
        $this->logger = new Logger();
        $this->admin_email = get_option( 'admin_email' );
        $this->factory = new Factory();
    }

    /**
     * Add review request in automated email notification
     * Updated since 4.0.0
     *
     * @param $admin_first_name
     * @return string
     *
     * @since 2.0.0
     */
    public function add_review_request_in_email( $admin_first_name = null ) {
        $this->factory->add_display_review();
        $display_review = get_option( 'aifs_display_review' );
        if ( $display_review !== false && $display_review !== 0 ) {
            //If aifs_display_review is set to 1, add the review request
            $contact_url = ( aifs_is_free_version() ? "https://freessl.tech/free-ssl-certificate-for-wordpress-website/#contact-us" : aifssl_fs()->contact_url() );
            $html = "<div style='background-color: #000000; padding: 15px; margin-bottom: 18px;'>\n                            <div style='color: #FFFF00; font-size: 1em; margin-bottom: 16px;'>\n                                " . __( "Hey", 'auto-install-free-ssl' ) . " " . (( is_null( $admin_first_name ) ? aifs_admin_first_name() : $admin_first_name )) . ", ";
            //$html .=            sprintf(__( "%s has saved you $90 by providing Free SSL Certificates and will save more. Please share your experience with us on WordPress (probably with a five-star rating). That will help boost our motivation and spread the word.", 'auto-install-free-ssl' ), '<strong>' . AIFS_NAME . '</strong>');
            /* translators: %s: Name of this plugin, i.e., 'Auto-Install Free SSL' */
            $html .= sprintf( __( "%s has saved you money by providing Free SSL Certificates and will save more. Some providers, like Godaddy, charge at least USD 99.99 per website per year for SSL, but with our plugin, you have it for free!", 'auto-install-free-ssl' ), '<strong>' . AIFS_NAME . '</strong>' ) . '<br />';
            /* translators: %1$s: Opening HTML 'a' tag; %2$s: Closing 'a' tag (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
            $html .= sprintf( __( 'Please consider writing a review on WordPress.org to spread the word (with a five-star rating if you feel we have earned it). Your feedback would be greatly appreciated. Please %1$sclick here%2$s if you need any help.', 'auto-install-free-ssl' ), '<a href="' . $contact_url . '" target="_blank">', '</a>' );
            $html .= " <br />\n                            </div>\n                            <a style='background: #399642; color: #ffffff; text-decoration: none; padding: 7px 15px; border-radius: 5px;' href='https://wordpress.org/support/plugin/auto-install-free-ssl/reviews/?filter=5#new-post' target='_blank'>" . __( "Sure! You Deserve It.", 'auto-install-free-ssl' ) . "</a>\n                      </div>";
        } else {
            $html = "";
        }
        return $html;
    }

    /**
     * Sends SSL renewal reminder email
     *
     * @throws Exception
     */
    public function send_ssl_renewal_reminder_email() {
        if ( !get_option( 'aifs_enable_ssl_renewal_reminder' ) ) {
            //@since 4.0.0
            return;
        }
        $app_settings = aifs_get_app_settings();
        $certificate = $this->factory->this_domain_get_ssl_file_path();
        if ( $certificate ) {
            $cert_array = openssl_x509_parse( openssl_x509_read( file_get_contents( $certificate ) ) );
            $expiry_timestamp = $cert_array['validTo_time_t'];
            //$days_before_expiry_to_renew_ssl = 30;
            //$renewal_timestamp = $expiry_timestamp - ($days_before_expiry_to_renew_ssl * 24 * 60 * 60);
            $now = new DateTime();
            $expiry = new DateTime('@' . $expiry_timestamp);
            $interval = (int) $now->diff( $expiry )->format( '%R%a' );
            $last_reminder_timestamp = get_option( 'aifs_ssl_renewal_reminder_email_last_sent_timestamp' );
            $interval_from_last_reminder = 0;
            if ( $last_reminder_timestamp ) {
                $last_reminder = new \DateTime('@' . (int) $last_reminder_timestamp);
                $interval_from_last_reminder = (int) $last_reminder->diff( $now )->format( '%R%a' );
            }
            if ( $interval <= 30 && $interval >= -12 && (!$last_reminder_timestamp || $interval_from_last_reminder >= 15) ) {
                $exp = ( function_exists( 'wp_date' ) ? wp_date( 'F j, Y - h:i:s A', $expiry_timestamp ) : date( 'F j, Y - h:i:s A', $expiry_timestamp ) . " " . __( "UTC", 'auto-install-free-ssl' ) );
                $expiry_date = str_replace( '-', __( "at", 'auto-install-free-ssl' ), $exp );
                $issuerShort = $cert_array['issuer']['O'];
                $renew_url = admin_url( "admin.php?page=aifs_generate_ssl_manually" );
                $renew_url = ( strpos( $renew_url, get_site_url() ) !== false ? $renew_url . "#ssl_renewal_form" : get_site_url() . "/wp-admin/admin.php?page=aifs_generate_ssl_manually#ssl_renewal_form" );
                if ( aifs_is_existing_user() ) {
                    if ( time() < strtotime( "February 1, 2023" ) ) {
                        $discount_percentage = __( "30%", 'auto-install-free-ssl' );
                        $coupon_code = "30ThankYou";
                    } elseif ( time() < strtotime( "February 1, 2024" ) ) {
                        $discount_percentage = __( "20%", 'auto-install-free-ssl' );
                        $coupon_code = "20ThankYou";
                    }
                } else {
                    if ( $this->factory->is_cpanel() ) {
                        $discount_percentage = __( "20%", 'auto-install-free-ssl' );
                        $coupon_code = "SSLAutoInstall";
                    } else {
                        $discount_percentage = __( "10%", 'auto-install-free-ssl' );
                        $coupon_code = "10AutoInstallSSL";
                    }
                }
                if ( $interval > 0 ) {
                    /* translators: %1$s: A domain name, e.g., example.com, %2$s: Number of days */
                    $subject = sprintf( __( 'Your SSL certificate, for %1$s, is expiring in %2$s days', 'auto-install-free-ssl' ), $cert_array['subject']['CN'], $interval );
                } else {
                    /* translators: %s: A domain name, e.g., example.com */
                    $subject = sprintf( __( "Your SSL certificate, for %s, is EXPIRED!", 'auto-install-free-ssl' ), $cert_array['subject']['CN'] ) . " ";
                    $subject .= __( "Renew urgently.", 'auto-install-free-ssl' );
                }
                //Email body
                $body = "<html><body><p>" . __( "Hello", 'auto-install-free-ssl' ) . " " . aifs_admin_first_name() . ",</p>";
                if ( $interval > 0 ) {
                    /* translators: %1$s: A domain name, e.g., example.com, %2$s: Number of days */
                    $body .= "<p>" . sprintf( __( 'Your SSL certificate, for %1$s, will expire in %2$s days.', 'auto-install-free-ssl' ), str_replace( 'DNS:', '', $cert_array['extensions']['subjectAltName'] ), $interval );
                } else {
                    /* translators: %s: A domain name, e.g., example.com */
                    $body .= "<p>" . sprintf( __( "Your SSL certificate, for %s, is EXPIRED!", 'auto-install-free-ssl' ), str_replace( 'DNS:', '', $cert_array['extensions']['subjectAltName'] ) );
                }
                /* translators: %s: A date, e.g., December 30, 2023. */
                $body .= " " . sprintf( __( "The expiry date is %s.", 'auto-install-free-ssl' ), $expiry_date ) . "</p>";
                $before_expiry = __( "before the expiry date", 'auto-install-free-ssl' );
                $urgently = __( "URGENTLY", 'auto-install-free-ssl' );
                /* translators: %s: Words, either 'before the expiry date' or 'URGENTLY' based on a predefined condition.  */
                $body .= "<p style='color: red; font-size: 1.3em;'>" . sprintf( __( "If you don't renew the SSL certificate %s, your visitors will see a security warning in red and leave your website.", 'auto-install-free-ssl' ), ( $interval > 0 ? $before_expiry : $urgently ) ) . "</p>";
                /* translators: %s: Name of the SSL certificate authority, e.g., Let's Encrypt */
                $body .= "<p>" . sprintf( __( "The validity of %s free SSL is 90 days. They recommend renewing 30 days before expiry.", 'auto-install-free-ssl' ), $issuerShort ) . "</p>";
                /* translators: %s: HTML code to create a hyperlink with the text 'click here'. */
                $body .= "<p>" . sprintf( __( "%sClick here%s to Renew your SSL today.", 'auto-install-free-ssl' ), '<a href="' . $renew_url . '">', '</a>' ) . "</p>";
                //$body .= "<p>" . sprintf(__( '%1$sDo you want automatic renewal every 60 days?%2$s %3$sClick here%4$s to upgrade to the Premium Version using this %5$s discount code: %6$s The offer expires soon.%7$s', 'auto-install-free-ssl' ), '<strong>', '</strong>', '<a href="'. $this->factory->upgrade_url() .'">', '</a>', $discount_percentage, ('<span style="color: green; font-weight: bold; text-transform: uppercase;">' . $coupon_code . '</span><br /><span style="font-style: italic;">'), '</span>') . "</p>";
                //$body .= "<p>" . sprintf(__( '%1$sTired of renewing & installing SSL certificates manually every 60 days?%2$s %3$sClick here%4$s to try Premium Version; the plugin will do everything automatically! %5$s discount code: %6$s The offer expires soon.%7$s', 'auto-install-free-ssl' ), '<strong>', '</strong>', '<a href="'. $this->factory->upgrade_url() .'">', '</a>', $discount_percentage, ('<span style="color: green; font-weight: bold; text-transform: uppercase;">' . $coupon_code . '</span><br /><span style="font-style: italic;">'), '</span>') . "</p>";
                $body .= "<p><strong>" . __( "Tired of renewing & installing SSL certificates manually every 60 days?", 'auto-install-free-ssl' ) . "</strong> ";
                /* translators: %s: placeholders for HTML code to create a hyperlink with the text 'Click here'. */
                $body .= sprintf( __( '%sClick here%s to try Premium Version; the plugin will do everything automatically!', 'auto-install-free-ssl' ), '<a href="' . $this->factory->upgrade_url() . '">', '</a>' ) . " ";
                /* translators: %1$s: Discount percentage (includes % sign), %2$s: Coupon code for the discount */
                $body .= sprintf( __( '%1$s discount code: %2$s', 'auto-install-free-ssl' ), $discount_percentage, '<span style="color: green; font-weight: bold; text-transform: uppercase;">' . $coupon_code . '</span>' );
                $body .= '<br /><span style="font-style: italic;">' . __( "The offer expires soon.", 'auto-install-free-ssl' ) . '</span>';
                $body .= "</p>";
                $body .= "<p>" . __( "Please ignore this email if you have renewed the SSL certificate already.", 'auto-install-free-ssl' ) . "</p>";
                $body .= $this->add_email_signature();
                $body .= "</body></html>";
                //Send email to admin email id and Let's Encrypt registrant email id (provided in basic settings) if both are different
                if ( in_array( $this->admin_email, $app_settings['admin_email'] ) ) {
                    $to = implode( ',', $app_settings['admin_email'] );
                } else {
                    $to = $this->admin_email . "," . implode( ',', $app_settings['admin_email'] );
                }
                // Set content-type header
                $headers = [];
                $headers[] = 'MIME-Version: 1.0';
                //$headers[] = 'Content-type: text/html; charset=iso-8859-1';
                $headers[] = 'Content-Type: text/html; charset=UTF-8';
                $headers[] = 'From:wordpress@' . aifs_get_domain();
                // Send the email
                if ( wp_mail(
                    $to,
                    $subject,
                    $body,
                    $headers
                ) ) {
                    //$email_db[$cert_array['subject']['CN']]['last_sent_timestamp'] = time();
                    update_option( 'aifs_ssl_renewal_reminder_email_last_sent_timestamp', time() );
                    $this->logger->log( __( "The SSL renewal reminder email has been sent successfully.", 'auto-install-free-ssl' ) );
                } else {
                    $this->logger->log( __( "Sorry, there was an issue sending the SSL renewal reminder email.", 'auto-install-free-ssl' ) );
                }
            }
        }
    }

    /**
     *
     *
     * Add email signature
     * @return string
     *
     * @since 2.0.0
     */
    public function add_email_signature() {
        $html = __( "This is a system-generated email from your website.", 'auto-install-free-ssl' ) . "<br />";
        $html .= __( "Do not reply to this automated email.", 'auto-install-free-ssl' ) . "<br /><br />";
        $html .= "--------------<br />";
        $html .= __( "Regards", 'auto-install-free-ssl' ) . ",<br />";
        $html .= __( "Team", 'auto-install-free-ssl' ) . " <a href='https://freessl.tech/free-ssl-certificate-for-wordpress-website' target='_blank' style='font-weight: bold;'>" . AIFS_NAME . "</a><br />";
        //$html .= "Powered by <a href='https://getwww.me'>GetWWW.me</a> (Beautiful WordPress website design service) and <a href='https://speedify.tech/wordpress-website-speed-optimization-service'>SpeedUpWebsite.info</a> (WordPress website speed optimization service)<br />";
        return $html;
    }

}
