<?php

/**
 * @package Auto-Install Free SSL
 * This package is a WordPress Plugin. It issues and installs free SSL certificates in cPanel shared hosting with complete automation.
 *
 * @author Free SSL Dot Tech <support@freessl.tech>
 * @copyright  Copyright (C) 2019-2020, Anindya Sundar Mandal
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
 * Home page options
 *
 */
class AdminNotice {
    public $factory;

    public $options;

    public $acmeFactory;

    public $return_array_step1;

    public $page_url;

    /**
     * Start up
     */
    public function __construct() {
        if ( !defined( 'ABSPATH' ) ) {
            die( __( "Access denied", 'auto-install-free-ssl' ) );
        }
        // Set class property
        $this->options = ( get_option( 'basic_settings_auto_install_free_ssl' ) ? get_option( 'basic_settings_auto_install_free_ssl' ) : add_option( 'basic_settings_auto_install_free_ssl' ) );
        $this->factory = new Factory();
        /*
         * Review display option @since 1.1.0
         *
         */
        $this->factory->add_display_review();
        // Logic refactored since 3.4.0
        /*if(isset($this->options['homedir'])){
        
        			//initialize the Acme Factory class
        			$this->acmeFactory = new AcmeFactory($this->options['homedir'].'/'.$this->options['certificate_directory'], $this->options['acme_version'], $this->options['is_staging']);
        
        			//get the path of SSL files
        			$certificates_directory = $this->acmeFactory->getCertificatesDir();
        
        			if(is_dir($certificates_directory)){
        
        				//get the domains for which SSL is present in the certificate directory
        				$all_domains = $this->factory->getExistingSslList($certificates_directory);
        
        				//If at least one SSL cert exists in the $certificates_directory, set 'aifs_display_review' = 1 if this option doesn't exist
        				if (count($all_domains) > 0) {
        
        					if(strlen(get_option('aifs_display_review')) === 0){ //!get_option('aifs_display_review')
        
        						if(aifs_is_free_version()) {
        							//Display review if a valid SSL installed -> this may slowdown a bit
        							//$display_review = $this->factory->is_ssl_installed_on_this_website() === true;
        							$display_review = true;
        						}
        						else{
        							//Premium version. So, display review on cPanel only and if a valid SSL installed
        							//$display_review = $this->factory->is_cpanel() && $this->factory->is_ssl_installed_on_this_website() === true;
        							$display_review = $this->factory->is_cpanel();
        						}
        
        						if($display_review) {
        							add_option( 'aifs_display_review', 1 );
        						}
        					}
        				}
        			}
        		}*/
        if ( is_admin() ) {
            add_action( 'admin_notices', array($this, 'aifs_display_admin_notice') );
        } else {
            //add_action( 'init', array( $this, 'aifs_display_admin_notice' ) );//Send the email even if the frontend page loaded : Not working
        }
        add_action( 'admin_init', array($this, 'aifs_admin_notice_handler') );
        /*
         * Announcement display option @since 2.2.2
         *
         */
        if ( !get_option( 'aifs_display_free_premium_offer' ) ) {
            add_option( 'aifs_display_free_premium_offer', 1 );
        }
        if ( !get_option( 'aifs_display_discount_offer_existing_users' ) ) {
            add_option( 'aifs_display_discount_offer_existing_users', 1 );
        }
        $this->return_array_step1 = ( get_option( 'aifs_return_array_step1_manually' ) ? get_option( 'aifs_return_array_step1_manually' ) : add_option( 'aifs_return_array_step1_manually' ) );
        if ( !defined( 'AIFS_COUNTDOWN_DURATION' ) ) {
            define( 'AIFS_COUNTDOWN_DURATION', 11.7 * 60 * 60 + 38 );
            // 74.1 h + 38 s
        }
        $site_url = parse_url( get_site_url() );
        $this->page_url = $site_url['scheme'] . "://" . $site_url['host'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * Display Admin notice.
     * Improved since 3.6.0
     *
     * @since 2.1.1
     */
    public function aifs_display_admin_notice() {
        $admin_notice_text = get_option( 'aifs_admin_notice_if_cpanel_connection_fails' );
        if ( !empty( trim( $admin_notice_text ) ) ) {
            $display_review_request = false;
        } else {
            $display_review_request = true;
        }
        if ( isset( $this->options['homedir'] ) ) {
            $counter = (int) get_option( 'aifs_admin_notice_display_counter' );
            /*if(aifs_is_free_version()) {
            			aifs_is_existing_user(); //Calling to detect existing users and send their information to FreeSSL.tech server
            		}*/
            if ( aifs_is_free_version() ) {
                if ( isset( $_GET['page'] ) && $_GET['page'] == "auto_install_free_ssl" ) {
                    return;
                }
                if ( $counter % 3 == 0 ) {
                    $this->aifs_display_review_request( $display_review_request );
                } else {
                    /*
                     * Display announcement only if 'aifs_display_review' exists,
                     * i.e., at least one SSL cert issued
                     */
                    //if ( get_option( 'aifs_display_review' ) !== false ) {
                    $this->aifs_display_announcement();
                    //}
                }
            } else {
                if ( isset( $_GET['page'] ) && $_GET['page'] == "aifs_issue_free_ssl" ) {
                    return;
                }
                if ( $counter % 2 == 0 ) {
                    $this->aifs_display_review_request( $display_review_request );
                } else {
                    $this->aifs_display_announcement();
                }
                /*if ( $counter % 2 == 0 ) {
                			$this->aifs_display_review_request( true );
                		} else {
                			if ( aifssl_fs()->can_use_premium_code__premium_only() ) {
                				$this->aifs_display_admin_notice_if_cpanel_connection_fails__premium_only( $cpanel_password_missing, $cpanel_api_token_missing, $dns_api_credential_missing );
                			}
                		}*/
            }
            $this->aifs_admin_notice_display_counter();
        }
        //Display SSL renewal reminder
        if ( aifs_is_free_version() ) {
            $this->aifs_display_ssl_renewal_reminder();
        }
    }

    /**
     * Display SSL renewal reminder with admin notice
     *
     */
    public function aifs_display_ssl_renewal_reminder() {
        if ( !get_option( 'aifs_enable_ssl_renewal_reminder' ) ) {
            //@since 4.0.0
            return;
        }
        if ( isset( $_GET['page'] ) && $_GET['page'] == "aifs_generate_ssl_manually" ) {
            return;
        }
        $remind_later_interval_sec = 3 * 24 * 60 * 60;
        //3 days
        if ( time() < (int) get_option( 'aifs_renew_ssl_later_requested_timestamp' ) + $remind_later_interval_sec ) {
            return;
        }
        $certificate = $this->factory->this_domain_get_ssl_file_path();
        if ( $certificate ) {
            $cert_array = openssl_x509_parse( openssl_x509_read( file_get_contents( $certificate ) ) );
            $expiry_timestamp = $cert_array['validTo_time_t'];
            $days_before_expiry_to_renew_ssl = 30;
            //@todo correct this value
            //$expiry_date = str_replace('-', 'at', wp_date('F j, Y - h:i:s A', $expiry_timestamp));
            $expiry_date = ( function_exists( 'wp_date' ) ? wp_date( 'F j, Y', $expiry_timestamp ) : date( 'F j, Y', $expiry_timestamp ) . " " . __( "UTC", 'auto-install-free-ssl' ) );
            $issuerShort = $cert_array['issuer']['O'];
            $renewal_timestamp = $expiry_timestamp - $days_before_expiry_to_renew_ssl * 24 * 60 * 60;
            if ( time() > $renewal_timestamp ) {
                if ( $this->factory->is_cpanel() ) {
                    $upgrade_url = $this->factory->upgrade_url( "SSLAutoInstall20", "hide_coupon=true&checkout=true" );
                    $upgrade_button_text = __( "Go Premium (20% off)", 'auto-install-free-ssl' );
                } else {
                    $upgrade_url = $this->factory->upgrade_url();
                    $upgrade_button_text = __( "Go Premium", 'auto-install-free-ssl' );
                }
                $renew_url = menu_page_url( 'aifs_generate_ssl_manually', false );
                $remind_later = wp_nonce_url( $this->page_url, 'aifs_renew_ssl_later', 'aifsrenewssllater' );
                $generate_ssl = new GenerateSSLmanually();
                $renew_button_text = __( "Renew SSL Now", 'auto-install-free-ssl' );
                /* translators: %s: A date, e.g., December 30, 2023. */
                $msg_before_expiry = sprintf( __( "Your visitors will see a security warning in red and may leave your website if you don't renew the SSL certificate before the expiry date %s.", 'auto-install-free-ssl' ), $expiry_date );
                $html = '<div class="notice notice-error aifs-review">
	                        <div class="aifs-review-box">
	                          <img class="aifs-notice-img-left" src="' . AIFS_URL . 'assets/img/ssl-error.jpg" />
	                          <p style="text-align: justify;">' . __( "Hello", 'auto-install-free-ssl' ) . ' ' . aifs_admin_first_name() . ', <span style="color: red;">' . (( time() > $expiry_timestamp ? __( "Your visitors will see a security warning in red and may leave your website if you don't renew the SSL certificate URGENTLY.", 'auto-install-free-ssl' ) : $msg_before_expiry )) . '</span> ';
                /* translators: %s: Name of the SSL certificate authority, e.g., Let's Encrypt */
                $html .= '<span style="font-size: small;">(' . sprintf( __( "The validity of %s free SSL is 90 days. They recommend renewing 30 days before expiry.", 'auto-install-free-ssl' ), $issuerShort ) . ')</span><!-- <br /><strong>~' . AIFS_NAME . '</strong>-->
	                          <br /><span style="font-size: medium; line-height: 2em;">' . __( "Tired of renewing & installing SSL certificates manually every 60 days? Try Premium Version, and the plugin will do it automatically!", 'auto-install-free-ssl' ) . '</span></p>
	                        </div>
	                        <div style="margin-left: 8%; margin-top: -1%; margin-bottom: -2%;">
	                        	' . $generate_ssl->regenerate_ssl_form( $renew_button_text, true ) . '
	                        	<!-- <a class="aifs-review-now aifs-review-button" href="' . $renew_url . '">' . __( 'Renew SSL Now', 'auto-install-free-ssl' ) . '</a> -->
	                        	<span style="margin-left: 35%; position: relative; top: -25px;"><a class="aifs-review-now aifs-review-button" href="' . $upgrade_url . '" rel="nofollow">' . $upgrade_button_text . '</a></span>
	                        	<span style="margin-left: 20%; position: relative; top: -25px;"><a class="aifs-review-button" href="' . $remind_later . '" rel="nofollow" onclick="return confirm(\'' . __( "Do you want to be reminded later to renew your SSL certificate?", 'auto-install-free-ssl' ) . '\')">' . __( "Remind later", 'auto-install-free-ssl' ) . '</a></span>
	                      	</div>
	                      </div>';
                echo $html;
            }
        }
    }

    /**
     * Display review request
     * Updated since 4.0.0
     * @param $display_review_request
     *
     * @since 1.1.0
     */
    public function aifs_display_review_request( $display_review_request ) {
        if ( isset( $_GET['page'] ) && $_GET['page'] == "aifs_generate_ssl_manually" && is_array( $this->return_array_step1 ) && isset( $this->return_array_step1['current_step_number'] ) && $this->return_array_step1['current_step_number'] != 3 ) {
            return;
        }
        $display_review = get_option( 'aifs_display_review' );
        //Get the value of aifs_display_review
        if ( $display_review_request && is_ssl() && $display_review == 1 ) {
            $already_done = wp_nonce_url( $this->page_url, 'aifs_reviewed', 'aifsrated' );
            $remind_later = wp_nonce_url( $this->page_url, 'aifs_review_later', 'aifslater' );
            $dont_want_button = ( aifs_is_free_version() ? __( "I don't want to, though you saved my money", 'auto-install-free-ssl' ) : __( "I don't want to", 'auto-install-free-ssl' ) );
            $contact_url = ( aifs_is_free_version() ? "https://freessl.tech/free-ssl-certificate-for-wordpress-website/#contact-us" : aifssl_fs()->contact_url() );
            //$main_text = sprintf(__( "%s has saved you $90 by providing Free SSL Certificates and will save more. Please share your experience with us on WordPress (probably with a five-star rating). That will help boost our motivation and spread the word.", 'auto-install-free-ssl' ), '<strong>' . AIFS_NAME . '</strong>');
            /* translators: %s: Name of this plugin, i.e., 'Auto-Install Free SSL' */
            $main_text = sprintf( __( "%s has saved you money by providing Free SSL Certificates and will save more. Some providers, like Godaddy, charge at least USD 99.99 per website per year for SSL, but with our plugin, you have it for free!", 'auto-install-free-ssl' ), '<strong>' . AIFS_NAME . '</strong>' ) . '<br />';
            /* translators: %1$s: Opening HTML 'a' tag; %2$s: Closing 'a' tag (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
            $main_text .= sprintf( __( 'Please consider writing a review on WordPress.org to spread the word (with a five-star rating if you feel we have earned it). Your feedback would be greatly appreciated. Please %1$sclick here%2$s if you need any help.', 'auto-install-free-ssl' ), '<a href="' . $contact_url . '" target="_blank">', '</a>' );
            $html = '<div class="notice notice-success aifs-review">
                        <div class="aifs-review-box">
                          <img class="aifs-notice-img-left" src="' . AIFS_URL . 'assets/img/icon.jpg" />
                          <p>' . __( "Hey", 'auto-install-free-ssl' ) . ' ' . aifs_admin_first_name() . ', ' . $main_text . ' <br />~Anindya</p>
                        </div>
                        <a class="aifs-review-now aifs-review-button" href="https://wordpress.org/support/plugin/auto-install-free-ssl/reviews/?filter=5#new-post" target="_blank">' . __( "Click here to Review", 'auto-install-free-ssl' ) . '</a>
                        <a class="aifs-review-button" href="' . $already_done . '" rel="nofollow" onclick="return confirm(\'Are you sure you have reviewed ' . AIFS_NAME . ' plugin?\')">' . __( "I have done", 'auto-install-free-ssl' ) . '</a>
                        <a class="aifs-review-button" href="' . $already_done . '" rel="nofollow" onclick="return confirm(\'Are you sure you do NOT want to review ' . AIFS_NAME . ' plugin?\')">' . $dont_want_button . '</a>
                        <a class="aifs-review-button" href="' . $remind_later . '" rel="nofollow" onclick="return confirm(\'Are you sure you need ' . AIFS_NAME . ' to remind you later?\')">' . __( "Remind me later", 'auto-install-free-ssl' ) . '</a>
                      </div>';
            echo $html;
        }
    }

    /**
     * Display announcement
     *
     * @since 2.2.2 (refactored since 3.2.13)
     */
    public function aifs_display_announcement() {
        if ( aifs_is_free_version() ) {
            if ( aifs_is_existing_user() ) {
                /*if(time() < strtotime("January 1, 2023")) {
                			$this->general_announcement();
                		}
                		else {*/
                $this->discount_offer_to_existing_users();
                //}
            } else {
                $this->general_announcement();
            }
        } else {
        }
    }

    /**
     * Display announcement to any free users
     * but free user restriction not applied here
     *
     * @since 3.2.13
     */
    public function general_announcement() {
        if ( isset( $_GET['page'] ) && $_GET['page'] == "aifs_generate_ssl_manually" ) {
            return;
        }
        //Get the value of aifs_display_free_premium_offer
        $display_announcement = get_option( 'aifs_display_free_premium_offer' );
        if ( $display_announcement == 1 ) {
            $already_done = wp_nonce_url( $this->page_url, 'aifs_announcement_already_read', 'aifsannouncementdone' );
            $remind_later = wp_nonce_url( $this->page_url, 'aifs_announcement_read_later', 'aifsannouncementlater' );
            /*if ( aifs_is_existing_user() ) {
            				$link                           = menu_page_url( 'auto_install_free_ssl', false );
            				$link_text                      = __( "Claim for FREE", 'auto-install-free-ssl' );
            				$already_done_text              = __( "I have already claimed", 'auto-install-free-ssl' );
            				$dont_want_text                 = __( "I don't want it", 'auto-install-free-ssl' );
            				$already_done_confirmation_text = sprintf(__( "Are you sure you have already claimed the Premium Version of %s for FREE?", 'auto-install-free-ssl' ), AIFS_NAME);
            				$dont_want_confirmation_text    = sprintf(__( "Are you sure you do NOT want the Premium Version of %s for FREE?", 'auto-install-free-ssl' ), AIFS_NAME);
            
            			} else {*/
            $link = menu_page_url( 'auto_install_free_ssl', false ) . "&comparison=yes";
            $link_text = __( "Comparison Table", 'auto-install-free-ssl' );
            $already_done_text = __( "Got it", 'auto-install-free-ssl' );
            $dont_want_text = __( "I don't want to", 'auto-install-free-ssl' );
            /* translators: %s: Name of this plugin, i.e., 'Auto-Install Free SSL' */
            $already_done_confirmation_text = sprintf( __( "Are you sure you know the benefits of the Premium Version of %s compared to the free version?", 'auto-install-free-ssl' ), AIFS_NAME );
            /* translators: %s: Name of this plugin, i.e., 'Auto-Install Free SSL' */
            $dont_want_confirmation_text = sprintf( __( "Are you sure you do NOT want to learn the benefits of the Premium Version of %s compared to the free version?", 'auto-install-free-ssl' ), AIFS_NAME );
            //}
            $html = '<div class="notice notice-success aifs-review">
                    <div class="aifs-review-box">                      
                      <p style="line-height: 1.9em;">' . __( "Hello", 'auto-install-free-ssl' ) . ' ' . aifs_admin_first_name() . ', ';
            if ( aifs_is_existing_user() ) {
                //$html .= '<a href="' . $link . '">' . __( "click here", 'auto-install-free-ssl' ) . '</a> ' . __( 'and claim a Premium License of', 'auto-install-free-ssl' ) . ' <strong>' . AIFS_NAME . '</strong> ' . __( 'for FREE!', 'auto-install-free-ssl' ) . '<br />';
                //$html .= sprintf(__( '%1$s click here%2$s and claim a Premium License of %3$s for FREE!', 'auto-install-free-ssl' ), '<a href="' . $link . '">', '</a>', '<strong>' . AIFS_NAME . '</strong>');
            }
            $html .= sprintf(
                /* translators: %1$s: Opening HTML 'a' tag; %2$s: Closing 'a' tag; %3$s: Name of this plugin, i.e., 'Auto-Install Free SSL'; (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
                __( 'As we mentioned in our %1$s announcement %2$s dated November 3, 2020, we released the premium version of %3$s on June 30, 2022, which is fully automated.', 'auto-install-free-ssl' ),
                '<a href="https://freessl.tech/blog/auto-install-free-ssl-needs-your-help-to-survive" target="_blank">',
                '</a>',
                '<strong>' . AIFS_NAME . '</strong>'
            ) . '</p>';
            $html .= '<img class="aifs-notice-img-right" src="' . AIFS_URL . 'assets/img/icon.jpg" />
                    </div>
                    <a class="aifs-review-now aifs-review-button" href="' . $link . '">' . $link_text . '</a>
                    <a class="aifs-review-button" href="' . $already_done . '" rel="nofollow" onclick="return confirm(\'' . $already_done_confirmation_text . '\')">' . $already_done_text . '</a>
                    <a class="aifs-review-button" href="' . $already_done . '" rel="nofollow" onclick="return confirm(\'' . $dont_want_confirmation_text . '\')">' . $dont_want_text . '</a>';
            /* translators: %s: Name of this plugin, i.e., 'Auto-Install Free SSL' */
            $html .= '<a class="aifs-review-button" href="' . $remind_later . '" rel="nofollow" onclick="return confirm(\'' . sprintf( __( "Do you need %s to remind you later?", 'auto-install-free-ssl' ), AIFS_NAME ) . '\')">';
            $html .= __( "Remind me later", 'auto-install-free-ssl' ) . '</a>                                      
                    </div>';
            echo $html;
        }
    }

    /**
     * if aifs_is_existing_user() or the user using free premium six months license
     * has date restriction
     *
     * @since 3.2.13
     */
    public function discount_offer_to_existing_users() {
        if ( isset( $_GET['page'] ) && $_GET['page'] == "aifs_generate_ssl_manually" && is_array( $this->return_array_step1 ) && isset( $this->return_array_step1['current_step_number'] ) && $this->return_array_step1['current_step_number'] != 1 ) {
            return;
        }
        if ( time() < strtotime( "February 1, 2024" ) ) {
            //Get the value of aifs_display_free_premium_offer
            $display_announcement = get_option( 'aifs_display_discount_offer_existing_users' );
            if ( $display_announcement == 1 ) {
                $already_done = wp_nonce_url( $this->page_url, 'aifs_discount_offer_already_read', 'aifsdiscountofferdone' );
                $remind_later = wp_nonce_url( $this->page_url, 'aifs_discount_offer_read_later', 'aifsdiscountofferlater' );
                //$link                           = aifssl_fs()->get_upgrade_url();
                $link = $this->factory->upgrade_url_for_existing_users();
                $link_text = __( "Grab the offer now!", 'auto-install-free-ssl' );
                //$link_confirmation_text         = sprintf( __( "The current free premium license is for unlimited sites. So, if you choose any other license, the large green button will say DOWNGRADE. Please do not worry; this is normal. Just click on it.", 'auto-install-free-ssl' ) );
                $already_done_text = __( "I have already purchased", 'auto-install-free-ssl' );
                $dont_want_text = __( "I don't want it", 'auto-install-free-ssl' );
                /* translators: %s: Name of this plugin, i.e., 'Auto-Install Free SSL' */
                $already_done_confirmation_text = sprintf( __( "Are you sure you have already purchased the Premium license of %s?", 'auto-install-free-ssl' ), AIFS_NAME );
                if ( aifs_is_free_version() ) {
                    /* translators: %s: Name of this plugin, i.e., 'Auto-Install Free SSL' */
                    $dont_want_confirmation_text = sprintf( __( "If you do not purchase a premium license, you will need to renew your SSL certificate manually every 60 days. Are you sure you do not want the Premium Version of %s?", 'auto-install-free-ssl' ), AIFS_NAME );
                } else {
                    $dont_want_confirmation_text = "";
                    //$dont_want_confirmation_text = sprintf(__( "Are you sure you do not want the Premium Version of %s? Suppose you do not purchase a premium license. In that case, your installation will be downgraded to the free plan from January 1, 2023, and you will need to renew your SSL certificate manually every 60 days.", 'auto-install-free-ssl' ), AIFS_NAME);
                }
                $html = '<div class="notice notice-success aifs-review">
		                    <div class="aifs-review-box">                      
		                      <p style="line-height: 1.9em;">' . __( "Hello", 'auto-install-free-ssl' ) . ' ' . aifs_admin_first_name() . ', ';
                if ( aifs_is_free_version() ) {
                    //Text only for free version
                    $html .= sprintf(
                        /* translators: %1$s: Opening HTML 'a' tag; %2$s: Closing 'a' tag; %3$s: Name of this plugin, i.e., 'Auto-Install Free SSL'; (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
                        __( 'As we mentioned in our %1$s announcement %2$s dated November 3, 2020, we released the premium version of %3$s on June 30, 2022, which is fully automated.', 'auto-install-free-ssl' ),
                        '<a href="https://freessl.tech/blog/auto-install-free-ssl-needs-your-help-to-survive" target="_blank">',
                        '</a>',
                        '<strong>' . AIFS_NAME . '</strong>'
                    ) . ' ';
                    $html .= '<span style="color: red;">' . __( "This free version doesn't have any automation feature.", 'auto-install-free-ssl' ) . '</span><br />';
                } else {
                    //Text for premium version
                    $html .= "";
                    //$html .= '<span style="color: red;">' . sprintf( __( 'the current free premium %1$sunlimited sites%2$s license of \'%3$s\' expires on December 31, 2022.', 'auto-install-free-ssl' ), '<strong>', '</strong>', AIFS_NAME ) . '</span><br />';
                }
                $html .= sprintf(
                    /* translators: %1$s: Opening HTML 'a' tag; %2$s: Closing 'a' tag; %3$s: Discount percentage (includes % sign); %4$s: Coupon code for the discount; (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
                    __( '%1$sClick here%2$s to purchase a premium license using this %3$s discount code: %4$s', 'auto-install-free-ssl' ),
                    '<a href="' . $link . '"' . (( aifs_is_free_version() ? '' : ' target="_blank"' )) . '>',
                    '</a>',
                    ( time() < strtotime( "February 1, 2023" ) ? __( "30%", 'auto-install-free-ssl' ) : __( "20%", 'auto-install-free-ssl' ) ),
                    '<span style="color: green; font-weight: bold;">' . (( time() < strtotime( "February 1, 2023" ) ? 'THANKYOU' : 'THANKYOU20' )) . '</span>'
                ) . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                $html .= '<span style="font-size: small; font-style: italic;">' . __( "The offer expires soon.", 'auto-install-free-ssl' ) . '</span></p>';
                $html .= '<img class="aifs-notice-img-right" src="' . AIFS_URL . 'assets/img/icon.jpg" />
		                    </div>';
                $html .= '<a class="aifs-review-now aifs-review-button" href="' . $link . '"' . (( aifs_is_free_version() ? '' : ' target="_blank"' )) . '>' . $link_text . '</a>';
                $html .= '<a class="aifs-review-button" href="' . $already_done . '" rel="nofollow" onclick="return confirm(\'' . $already_done_confirmation_text . '\')">' . $already_done_text . '</a>
		                    <a class="aifs-review-button" href="' . $already_done . '" rel="nofollow" onclick="return confirm(\'' . $dont_want_confirmation_text . '\')">' . $dont_want_text . '</a>';
                /* translators: %s: Name of this plugin, i.e., 'Auto-Install Free SSL' */
                $html .= '<a class="aifs-review-button" href="' . $remind_later . '" rel="nofollow" onclick="return confirm(\'' . sprintf( __( "Do you need %s to remind you later?", 'auto-install-free-ssl' ), AIFS_NAME ) . '\')">';
                $html .= __( "Remind me later", 'auto-install-free-ssl' ) . '</a>                                      
		                    </div>';
                echo $html;
            }
        }
    }

    /**
     *
     *
     * Execute admin notice actions
     *
     * @since 1.1.0 (renamed since 2.2.2)
     */
    public function aifs_admin_notice_handler() {
        //Review
        if ( isset( $_GET['aifsrated'] ) ) {
            if ( !wp_verify_nonce( $_GET['aifsrated'], 'aifs_reviewed' ) ) {
                wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
            }
            update_option( 'aifs_display_review', 0 );
            wp_redirect( $this->factory->aifs_remove_parameters_from_url( $this->page_url, ['aifsrated'] ) );
        } else {
            if ( isset( $_GET['aifslater'] ) ) {
                if ( !wp_verify_nonce( $_GET['aifslater'], 'aifs_review_later' ) ) {
                    wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
                }
                update_option( 'aifs_display_review', 5 );
                wp_schedule_single_event( strtotime( "+5 days", time() ), 'aifs_display_review_init' );
                wp_redirect( $this->factory->aifs_remove_parameters_from_url( $this->page_url, ['aifslater'] ) );
            }
        }
        //Announcement
        if ( isset( $_GET['aifsannouncementdone'] ) ) {
            if ( !wp_verify_nonce( $_GET['aifsannouncementdone'], 'aifs_announcement_already_read' ) ) {
                wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
            }
            update_option( 'aifs_display_free_premium_offer', 0 );
            wp_redirect( $this->factory->aifs_remove_parameters_from_url( $this->page_url, ['aifsannouncementdone'] ) );
        } else {
            if ( isset( $_GET['aifsannouncementlater'] ) ) {
                if ( !wp_verify_nonce( $_GET['aifsannouncementlater'], 'aifs_announcement_read_later' ) ) {
                    wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
                }
                update_option( 'aifs_display_free_premium_offer', 5 );
                wp_schedule_single_event( strtotime( "+3 days", time() ), 'aifs_display_announcement_init' );
                wp_redirect( $this->factory->aifs_remove_parameters_from_url( $this->page_url, ['aifsannouncementlater'] ) );
            }
        }
        //SSL Renewal reminder
        if ( isset( $_GET['aifsrenewssllater'] ) ) {
            if ( !wp_verify_nonce( $_GET['aifsrenewssllater'], 'aifs_renew_ssl_later' ) ) {
                wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
            }
            update_option( 'aifs_renew_ssl_later_requested_timestamp', time() );
            wp_redirect( $this->factory->aifs_remove_parameters_from_url( $this->page_url, ['aifsrenewssllater'] ) );
        }
        //Discount offer to existing users
        if ( isset( $_GET['aifsdiscountofferdone'] ) ) {
            if ( !wp_verify_nonce( $_GET['aifsdiscountofferdone'], 'aifs_discount_offer_already_read' ) ) {
                wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
            }
            update_option( 'aifs_display_discount_offer_existing_users', 0 );
            wp_redirect( $this->factory->aifs_remove_parameters_from_url( $this->page_url, ['aifsdiscountofferdone'] ) );
        } else {
            if ( isset( $_GET['aifsdiscountofferlater'] ) ) {
                if ( !wp_verify_nonce( $_GET['aifsdiscountofferlater'], 'aifs_discount_offer_read_later' ) ) {
                    wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
                }
                update_option( 'aifs_display_discount_offer_existing_users', 5 );
                wp_schedule_single_event( strtotime( "+3 days", time() ), 'aifs_display_discount_offer_init' );
                wp_redirect( $this->factory->aifs_remove_parameters_from_url( $this->page_url, ['aifsdiscountofferlater'] ) );
            }
        }
    }

    /**
     *
     *
     * Admin notice display counter. Required to display more than one admin notices alternately
     *
     * @since 2.2.2
     */
    public function aifs_admin_notice_display_counter() {
        if ( !get_option( 'aifs_admin_notice_display_counter' ) ) {
            add_option( 'aifs_admin_notice_display_counter', 1 );
        } else {
            $counter = ( get_option( 'aifs_admin_notice_display_counter' ) < 99999999 ? get_option( 'aifs_admin_notice_display_counter' ) : 0 );
            //if equal to 99999999, reset to 0
            update_option( 'aifs_admin_notice_display_counter', $counter + 1 );
        }
    }

}
