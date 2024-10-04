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

use AutoInstallFreeSSL\FreeSSLAuto\Acme\Factory as AcmeFactory;
use AutoInstallFreeSSL\FreeSSLAuto\Acme\AcmeV2;
use AutoInstallFreeSSL\FreeSSLAuto\Controller;
use AutoInstallFreeSSL\FreeSSLAuto\Logger;
use DateTime;

/**
 * This is free version of this plugin. No automation.
 *
 * @since 3.0.0
 */
class GenerateSSLmanually
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;
    private $save_button_text;
    public $factory;
    public $logger;
	public $appConfig;
	public $return_array_step1;
	/**
	 * @var false|mixed|void
	 */
	private $plan_selected;

	/**
     * Start up
     */
    public function __construct()
    {
	    if (!defined('ABSPATH')) {
		    die(__( "Access denied", 'auto-install-free-ssl' ));
	    }

        //Add bootstrap CSS
        //wp_enqueue_style('aifs_bootstrap_css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css');

	    //Add bootstrap JS

        $this->plan_selected = get_option('aifs_plan_selected');

        $this->options = get_option('aifs_generate_ssl_manually') ? get_option('aifs_generate_ssl_manually') : add_option('aifs_generate_ssl_manually');
        
        //hook if the user selected free plan
        if (aifs_is_free_version()) {
            add_action('admin_menu', array( $this, 'add_generate_ssl_manually_menu' ));
            add_action('admin_init', array( $this, 'generate_ssl_manually_page_init' ));
	        add_action( 'admin_init', array( $this, 'download_http01_challenge_file_handler' ) );
	        //add_action( 'admin_init', array( $this, 'generate_ssl_step_3' ) );
        }
        
        $this->factory = new Factory();
	    $this->appConfig = aifs_get_app_settings();
	    $this->logger = new Logger();

        //$this->save_button_text = __("Generate Free SSL", 'auto-install-free-ssl');
	    $this->save_button_text = __("Next Step", 'auto-install-free-ssl');

	    //$date = new \DateTime();
	    $this->return_array_step1 = get_option('aifs_return_array_step1_manually') ? get_option('aifs_return_array_step1_manually') : add_option('aifs_return_array_step1_manually');

	    /*if(is_array($this->return_array_step1) && ($date->getTimestamp() >= strtotime($this->return_array_step1['response']['expires'])) && (!isset($this->return_array_step1['ssl_cert_generated']) || !$this->return_array_step1['ssl_cert_generated'])){
		    //reset option 
	        unset($this->return_array_step1);
		    update_option( 'aifs_return_array_step1_manually', $this->return_array_step1 );
		    wp_redirect(menu_page_url('aifs_generate_ssl_manually'), 301);
	    }*/
    }
    
    /**
     * Add the sub menu
     */
    public function add_generate_ssl_manually_menu()
    {
        add_submenu_page('auto_install_free_ssl', __("Generate SSL Manually Page", 'auto-install-free-ssl'), __("Generate SSL", 'auto-install-free-ssl'), 'manage_options', 'aifs_generate_ssl_manually', array( $this, 'create_generate_ssl_manually_admin_page' ));
    }


    /**
     * Options page callback
     */
    public function create_generate_ssl_manually_admin_page()
    {
	    $date = new DateTime();

	    if(is_array($this->return_array_step1) && ($date->getTimestamp() >= strtotime($this->return_array_step1['response']['expires'])) && (!isset($this->return_array_step1['ssl_cert_generated']) || !$this->return_array_step1['ssl_cert_generated'])){
		    //reset option
		    unset($this->return_array_step1);
		    update_option( 'aifs_return_array_step1_manually', $this->return_array_step1 );
		    wp_redirect(menu_page_url('aifs_generate_ssl_manually'), 301);
	    }

	    $tos = false;

	    //$this->logger->clean_log_directory();

        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            /**
             * Check if TOS selected
             */

            if(isset($this->options['agree_to_le_terms']) && isset($this->options['agree_to_freessl_tech_tos_pp'])){
	            $tos = true;
            }
            /*else{
	            $tos = false;
            }*/

            //Validate output
            if (!$tos) {
                ?>
                <div style="background: #ff0000; color: #ffffff; padding: 2px; margin-top: 3%;">
                  	<p style="margin-left: 15px; font-size: 1.1em;">
                  		<?= __("Oops! Please agree to both Terms & Conditions and try again.", 'auto-install-free-ssl') ?>
                  	</p>          	
              	</div>
                <?php
            }
            else{

                $this->generate_ssl_step_1();

                /*//Call Step 2
                $this->generate_ssl_step_2();

	            $this->generate_ssl_step_3();*/

                //wp_redirect(menu_page_url('auto_install_free_ssl').'&settings-updated=true', 301);
            }

        } ?>

        <div class="wrap">

	        <?= aifs_header() ?>

            <?php
            if(!$tos && !isset($this->return_array_step1['current_step_number']) && !isset( $_POST['aifs_challenge_type'] )) {
                //Step no. is 1
	            $domain = aifs_get_domain( true );

	            $server_ip = aifs_ip_of_this_server();
	            if ( ( $server_ip && $this->factory->is_localhost( $server_ip ) ) || $this->factory->is_localhost( $domain ) ) {
		            echo '<div class="aifs-ip-address">';
		            /* translators: %s: an IP address, e.g., 127.0.0.1 */
		            echo '<strong>' . sprintf( __( "Your WordPress website is hosted on a local server (localhost): %s", 'auto-install-free-ssl' ), $domain ) . '</strong>';
		            /* translators: "Let's Encrypt™" is a nonprofit SSL certificate authority. */
		            echo '<p>' . __( "Let's Encrypt™ does not issue SSL certificates for localhost. Let's Encrypt™ certificates are designed for public domain names, which need to be accessible over the internet for the validation process. Since localhost is not a publicly accessible domain but rather a hostname used for local development, it cannot be validated by Let's Encrypt™.", 'auto-install-free-ssl' ) . '</p>';
		            //echo '<p>' . __("You can create a self-signed certificate for local development environments, which secures your local connections without requiring validation from a Certificate Authority (CA).", 'auto-install-free-ssl') . '</p>';
		            //echo '<p>' . sprintf(__('%1$sNote:%2$s Browsers do not trust self-signed certificates by default, so you may need to add exceptions in your browser to avoid security warnings during local development.', 'auto-install-free-ssl'), '<strong>', '</strong>') . '</p>';

		            echo '<div class="aifs-ip-address-inner">';
		            echo "After you complete developing the website in this local development environment, please do the following to secure your website:<br />";
		            echo "<ol>";
		            echo "<li>Use an existing web hosting server or purchase one, which should be publicly accessible online.</li>";
		            echo "<li>Use an existing domain name or purchase a domain name.</li>";
		            echo "<li>Associate the domain name with the IP address or nameservers of the web hosting server, i.e., set A record or NS records.</li>";
		            echo "<li>Migrate the website from this local development environment to the web hosting server using a Migration and Backup Plugin like 'Duplicator'.</li>";
		            echo "<li>If necessary, replace '" . $domain . "' with the domain name from all database tables using a plugin like 'Better Search Replace'.</li>";
		            echo "</ol>";
		            echo "<br /><strong>After you complete the above steps, please try generating an SSL certificate with this plugin.</strong>";
		            echo '</div>';
		            echo '</div>';
	            } else if ( $this->factory->is_ip_address( $domain ) ) {
		            echo '<div class="aifs-ip-address">';
		            /* translators: %s: an IP address, e.g., 127.0.0.1 */
		            echo '<strong>' . sprintf( __( "Your WordPress website is hosted directly on the IP address: %s", 'auto-install-free-ssl' ), $domain ) . '</strong>';
		            /* translators: "Let's Encrypt™" is a nonprofit SSL certificate authority. */
		            echo '<p>' . __( "Let's Encrypt™ issues SSL certificates for domain names rather than bare IP addresses. Free SSL certificates are designed to secure domain names, providing encrypted connections between users and websites. Using SSL certificates with domain names is considered the industry's best practice.", 'auto-install-free-ssl' ) . '</p>';
		            //echo '<p>' . __("If you're looking to secure a website, it's recommended to associate a domain name with the IP address rather than using the bare IP address. This enhances the user experience and aligns with security and usability standards.", 'auto-install-free-ssl') . '</p>';

		            echo '<div class="aifs-ip-address-inner">';
		            echo "Please do the following first to secure your website:<br />";
		            echo "<ol>";
		            echo "<li>Use an existing domain name or purchase a domain name.</li>";
		            echo "<li>Associate the domain name with this IP address, i.e., set A record.</li>";
		            echo "<li>Then open <a href='" . admin_url( 'options-general.php' ) . "' target='_blank'>General Settings</a>, update 'WordPress Address (URL)', & 'Site Address (URL)' with the domain name, and click the 'Save Changes' button.</li>";
		            echo "<li>Replace this IP address (" . $domain . ") with the domain name from all database tables using a plugin like 'Better Search Replace'.</li>";
		            echo "</ol>";
		            echo "<br /><strong>After you complete the above steps, please try generating an SSL certificate with this plugin.</strong>";
		            echo '</div>';
		            echo '</div>';
	            }
            }
            ?>

            <table style="width: 100%; margin-bottom: 2%;">
                <tr>
                    <td class="card block-body" style="width: 55%; padding-top: 1%; padding-bottom: 2%; padding-left: 2%;">

                    <?php
                    if(is_array($this->return_array_step1) && isset($this->return_array_step1['ssl_cert_generated']) && $this->return_array_step1['ssl_cert_generated']){
	                    $heading = __("Free SSL Certificate was Generated", 'auto-install-free-ssl');
                    }
                    else{
	                    $heading = __("Generate Free SSL Certificate", 'auto-install-free-ssl');
                    }

                    echo '<h1 style="color: #076507; text-align: center;">' . $heading . '</h1>';
                    echo '<h3 style="color: #076507; text-align: center;">' . __( "for", 'auto-install-free-ssl' ) . " " . aifs_get_domain() . '</h3>';

                    $aifs_current_step_number = 0;

                    if(!$tos && !isset($this->return_array_step1['current_step_number']) && !isset( $_POST['aifs_challenge_type'] )) {
	                    //echo "<h2 style='color: black'>Step 1 of 3</h2>";
	                    $aifs_current_step_number = 1;

                        echo $this->progress_bar();

                        echo '<form method="post" action="options.php">';

	                    settings_fields( 'aifs_generate_ssl_manually_group' );
	                    do_settings_sections( 'aifs_generate_ssl_manually_admin' );

	                    $confirmation_text = __( "Are you ready to start?", 'auto-install-free-ssl' );
	                    submit_button( $this->save_button_text . "&nbsp;&nbsp;&nbsp;&nbsp;>>", 'button-primary button-hero', 'submit', false , 'onclick="return aifs_confirm_initiate(\''. $confirmation_text .'\')"');

	                    //echo '<a href="' . menu_page_url( 'auto_install_free_ssl', false ) . '" id="aifs-cancel" class="page-title-action button">' . __( "Cancel", 'auto-install-free-ssl' ) . '</a>';

	                    echo '</form>';
                    }

                    if($this->generate_ssl_all_domains_verified_already() || isset( $_POST['aifs_challenge_type']) || (is_array($this->return_array_step1) && $this->return_array_step1['current_step_number'] == 3))
                    {
	                    $aifs_current_step_number = 3;
	                    //Call Step 3
	                    $this->generate_ssl_step_3();
                    }
                    elseif($tos || (is_array($this->return_array_step1) && $this->return_array_step1['current_step_number'] == 2))
                    {
	                    $aifs_current_step_number = 2;
	                    //Call Step 2
	                    $this->generate_ssl_step_2();
                    }

                    ?>


                    </td>

                    <?php
                    /*
                     * Show here only if it is Step 3 and an SSL is installed (if required, check Expiry date of SSL directory vs installed SSL) and aifs_force_ssl is not set or is zero.
                     *
                     * In all other cases, show the documentation (text and/or video) relevant to that Step
                     */

                    if($aifs_current_step_number == 3 && isset($this->return_array_step1['ssl_cert_generated']) && $this->return_array_step1['ssl_cert_generated']){ ?>
                        <td style="width: 2%;"></td>
                        <td class="card block-body" style="padding-left: 1.5%;">
                            <p style="margin-bottom: 4%;"><?= __( "Did you know that some providers, like Godaddy, charge at least USD 99.99 per website per year for SSL Certificates? But with our plugin, it's free!", 'auto-install-free-ssl' ) ?></p>
                            <hr />
	                        <?php if(isset($this->appConfig['is_cpanel']) && $this->appConfig['is_cpanel']){ ?>
                                <h3>
                                    <?php
                                    /* translators: 'cPanel' is web hosting control panel software developed by cPanel, LLC. */
                                    echo __( "cPanel: How to Install SSL Certificate", 'auto-install-free-ssl' ) ?>
                                </h3>

                                <!-- <iframe width="100%" height="251" src="https://www.youtube.com/embed/9x-kMz6Eo1E?rel=0" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe> -->
                                <div style="padding:53.33% 0 0 0;position:relative;"><iframe src="https://player.vimeo.com/video/745428583?h=73867938d9&title=0&byline=0&portrait=0" style="position:absolute;top:0;left:0;width:100%;height:100%;" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div><script src="https://player.vimeo.com/api/player.js"></script>
                                <br />
	                        <?php } ?>

                            <?php if($this->factory->is_plesk()){ ?>
                                <h3 id="plesk">
                                    <?php
                                    /* translators: 'Plesk' is a web hosting control panel software developed by Plesk International GmbH. */
                                    echo __( "Plesk: How to Install SSL Certificate", 'auto-install-free-ssl' ) ?>
                                </h3>

                                <!-- <iframe width="100%" height="251" src="https://www.youtube.com/embed/o4_N4QRVd48?rel=0" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe> -->
                                <div style="padding:53.33% 0 0 0;position:relative;"><iframe src="https://player.vimeo.com/video/745440419?h=347619a800&title=0&byline=0&portrait=0" style="position:absolute;top:0;left:0;width:100%;height:100%;" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div><script src="https://player.vimeo.com/api/player.js"></script>
                                <br />
                            <?php } ?>

	                        <?php if($this->factory->control_panel_is_unknown()){ ?>
                                <h3 style="line-height: 2em;"><?= __( "Please log in to your web hosting control panel to install the SSL certificate", 'auto-install-free-ssl' ) ?></h3>
                                <p><?= __( "Most web hosting companies provide an option to install SSL certificates. Please contact your web hosting provider if you are unaware of this option.", 'auto-install-free-ssl' ) ?></p>
	                        <?php } ?>

                            <!--
                            <p><?php //echo __( "Did you know that automated SSL Certificate installation is possible with our Premium Version?" ) ?></p>
                            <p><?php //echo sprintf(__( 'Do you need automation for all the steps? %1$sUpgrade to the %2$sPremium Version%3$s.', 'auto-install-free-ssl' ), '<br />', '<a href="'. $this->factory->upgrade_url() .'">', '</a>') ?></p>
                            -->
                        </td>
                    <?php } ?>

                </tr>

            </table>

            <div class="overlay"></div>
            <div class="spanner">
                <div class="loader"></div>
                <p class="loader_text"><?= __( "Processing, please wait ...", 'auto-install-free-ssl' ) ?></p>
            </div>

            <?php $this->pro_version_promotion(); ?>

            <?php
            if(!get_option('aifs_force_ssl') && $aifs_current_step_number == 3 && isset($this->return_array_step1['ssl_cert_generated']) && $this->return_array_step1['ssl_cert_generated'] && get_option('aifs_is_generated_ssl_installed')){ ?>
            <table style="width: 100%; margin-top: 2%;" id="force-https">
                <tr>
	                <?php
	                $forcehttps = new ForceSSL();
					echo $forcehttps->force_ssl_ui();
	                ?>
                </tr>
            </table>
            <?php } ?>

	        <?= aifs_powered_by() ?>
        </div>
        <?php
    }


	/**
	 * Promotion of Pro version
	 */
	public function pro_version_promotion(){
		$why = __( "why?", 'auto-install-free-ssl' );
		/* translators: "Let's Encrypt" is a nonprofit SSL certificate authority. */
		$ca = __( "Let's Encrypt™", 'auto-install-free-ssl' );

		/* translators: %s: Name of the SSL certificate authority, e.g., Let's Encrypt */
		$explanation = sprintf(__( "The validity of %s free SSL is 90 days. They recommend renewing 30 days before expiry.", 'auto-install-free-ssl' ), $ca);

		/* translators: "Let's Encrypt" is a nonprofit SSL certificate authority. */
		$explanation .= "\n\n" . __( "The validity period of free SSL certificates being 90 days is not a trial but rather a design choice of Let's Encrypt™ that prioritizes security. With shorter validity periods, Let's Encrypt™ encourages frequent certificate renewal, ensuring that websites always have up-to-date and secure certificates. This approach reduces the potential impact of compromised certificates.", 'auto-install-free-ssl' );

        /* translators: %s: First name of the admin user */
        $text = sprintf(__( 'Hello %s, this FREE version requires manual SSL renewal every 60 days.', 'auto-install-free-ssl' ), aifs_admin_first_name()) . ' (<abbr title="'. $explanation .'">'. $why .'</abbr>)';
        //$text = sprintf(__("Tired of renewing & installing SSL certificates manually every 60 days? Try the Premium Version and let them happen automatically!", 'auto-install-free-ssl'));
        $banner_heading = __( "Our Premium plugin automatically Renews the SSL certificate", 'auto-install-free-ssl' );
        $style = "font-size: 15px;";

        $number_of_ssl_generated = get_option('aifs_number_of_ssl_generated');
        //$style = "";

		if($number_of_ssl_generated == 0 && (!is_array($this->return_array_step1) || $this->return_array_step1['current_step_number'] == 1)){
		    return; //don't display promotion for first time usage if step is 1
		}

        if($number_of_ssl_generated) {
            $generated_ssl = $this->factory->get_generated_ssl_details();

            //Assuming User will install the generated SSL in 2 days (if Cloudflare)
            if(is_array($this->return_array_step1) && $this->return_array_step1['current_step_number'] == 3 && !get_option('aifs_is_generated_ssl_installed')){
                //$text = __( "Facing difficulties installing the SSL certificate? Try Premium Version, and the plugin will generate & install SSL automatically!", 'auto-install-free-ssl' );
                $text = __( "Facing difficulties installing the SSL certificate?", 'auto-install-free-ssl' );
                $banner_heading = __( "Our Premium plugin automatically Installs the SSL certificate", 'auto-install-free-ssl' );
                $style = "background-color: white; color: black; padding: 5px; font-size: 15px;";
            }

            if($generated_ssl !== false) {
                $expiry_timestamp                = $generated_ssl['validTo_time_t'];
                $days_before_expiry_to_renew_ssl = 30;
                $renewal_timestamp               = $expiry_timestamp - ( $days_before_expiry_to_renew_ssl * 24 * 60 * 60 );
                $stop_promo_time = $generated_ssl['validFrom_time_t'] + (2 * 24 * 60 * 60);//2 days from SSL generation

                if(get_option('aifs_is_generated_ssl_installed') && time() > $stop_promo_time && time() < $renewal_timestamp){
                    return; //don't display promotion
                }

                if ( time() > $renewal_timestamp ) {
                    //display 30- days Before expiry
                    //$text = __( "Tired of renewing & installing SSL certificates manually every 60 days? Try Premium Version, and the plugin will do it automatically!", 'auto-install-free-ssl' );
                    $text           = __( "Tired of renewing & installing SSL certificates manually every 60 days?", 'auto-install-free-ssl' );
                    $banner_heading = __( "Our Premium plugin automatically Renews and Installs the SSL certificate", 'auto-install-free-ssl' );
                    $style          = "background-color: white; color: black; padding: 5px; font-size: 15px;";
                }
            }

        }
        else {
            //No SSL generated till now
            /*
             * Step 1 : No text
             * Step 2: Facing difficulties generating (in step 3: 'installing') an SSL certificate? Try the Premium Version and let this happen automatically, including SSL installation!
             *
             */
        }

        //@since 3.6.6 display the following text in renewal too, if the step is 2
        if(is_array($this->return_array_step1) && $this->return_array_step1['current_step_number'] == 2){
            //$text = __( "Facing difficulties verifying domain ownership and generating a free SSL certificate? Try Premium Version; the plugin will do it automatically & install the SSL!", 'auto-install-free-ssl' );
            $text = __( "Facing difficulties verifying domain ownership and generating a free SSL certificate?", 'auto-install-free-ssl' );
            $banner_heading = __( "Our Premium plugin automatically Verifies Domain Ownership", 'auto-install-free-ssl' );
            //$style = " line-height: 3em;";
            $style = "background-color: white; color: black; padding: 5px; font-size: 15px;";
        }

        //if($text){
		$offer_details = $this->factory->get_offer_details();

            /*if($this->factory->is_cpanel()){
	            $coupon_code = $offer_details['coupon_code'];
                $query_string = "hide_coupon=true&checkout=true";
                $set_up = __( "We'll do the one-time setup for you if you can't do this (worth $49 per website).", 'auto-install-free-ssl' );
            }
            else{
                $coupon_code = false;
                $query_string = false;
                $set_up = __( "We'll manually do the one-time setup for you (worth $49 per website).", 'auto-install-free-ssl' );
            }*/

            if($this->factory->is_cpanel() || (time() > strtotime("June 1, 2024") && time() < strtotime("July 3, 2024"))){
                $coupon_code = $offer_details['coupon_code'];
                $query_string = "hide_coupon=true&checkout=true";
            }
            else{
                $coupon_code = false;
                $query_string = false;
            }

            if($this->factory->is_cpanel()){
                $set_up = __( "We'll do the one-time setup for you if you can't do this (worth $49 per website).", 'auto-install-free-ssl' );
            }
            else{
                $set_up = __( "We'll manually do the one-time setup for you (worth $49 per website).", 'auto-install-free-ssl' );
            }

            if($coupon_code && time() < $offer_details['offer_end_time']) {
                /* translators: %1$s: Discount percentage (includes % sign), %2$s: Coupon code for the discount */
                $discount_info = sprintf(__( '%1$s discount code: %2$s', 'auto-install-free-ssl' ), $offer_details['discount_percentage'], ('<span style="font-weight: bold; text-transform: uppercase;">' . $coupon_code . '</span>'));

	            $difference = $offer_details['offer_end_time'] - time();
	            // Calculate days, hours, minutes, and seconds
	            $days = floor($difference / (24 * 3600));
	            $difference %= (24 * 3600);
	            $hours = floor($difference / 3600);
	            $difference %= 3600;
	            $minutes = floor($difference / 60);
	            $seconds = $difference % 60;

	            // Format the countdown string
                if($days > 0){
	                $countdown = sprintf("%02d:%02d:%02d:%02d", $days, $hours, $minutes, $seconds);
                }
                else {
	                $countdown = sprintf( "%02d:%02d:%02d", $hours, $minutes, $seconds );
                }

	            $discount_info .= " (" . __("expires in", 'auto-install-free-ssl') . " " . $countdown . ")";

            }
            else{
	            $coupon_code = false;
	            $query_string = false;
                $discount_info = "";
            }

            ?>
            <div class="aifs-banner" id="pro">
                <p class="aifs-banner-intro" style="<?= $style ?>"><?= $text ?></p>
                <p class="aifs-banner-heading"><?= $banner_heading ?></p>
                <!-- <p class="aifs-banner-heading"><?php //echo __( "Enjoy 100% automation with our Premium version", 'auto-install-free-ssl' ) ?></p> -->

                <div class="aifs-banner-columns">
                    <div class="aifs-banner-left-column">
                        <ul>
                            <li><?= __( "Automatic Verification of Domain Ownership", 'auto-install-free-ssl' ) ?></li>
                            <li><?= __( "Automatic SSL Certificate Generation", 'auto-install-free-ssl' ) ?></li>
                            <li><?= __( "Automatic Installation of SSL", 'auto-install-free-ssl' ) ?><?= !$this->factory->is_cpanel() ? " *" : "" ?></li>
                            <li><?= __( "Automatic Renewal of SSL", 'auto-install-free-ssl' ) ?></li>
                            <li><?= __( "Automatic Cron Job", 'auto-install-free-ssl' ) ?></li>
                        </ul>

                        <p><?= "<strong>" . __( "BONUS:", 'auto-install-free-ssl' ) . "</strong> <i>" . $set_up . "</i>" ?></p>
                        <!-- <p style="margin-top: 5%;"><i>Why waste your valuable time with manual SSL renewal every 60 days?</i></p> -->

                        <div class="aifs-banner-call-to-action"><a class="aifs-banner-button" href="<?= $this->factory->upgrade_url($coupon_code, $query_string) ?>"><?= __( "Upgrade Now", 'auto-install-free-ssl' ) ?></a> <span style="margin-left: 3%;"><?= $discount_info ?></span></div>

                    </div>
                    <div class="aifs-banner-right-column">
                        <?php if($this->factory->is_cpanel()){ ?>
                            <iframe class="aifs-banner-video" src="https://player.vimeo.com/video/745390051?h=94ba682137&title=1&byline=0&portrait=0" style="" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
		                <?php }else{ ?>
                            <div class="aifs-banner-inner-box">
                                <p>
                                    <?php
                                    /* translators: 'cPanel' is web hosting control panel software developed by cPanel, LLC. */
                                    echo "* " . __( "The default option to automatically install SSL certificates requires cPanel API. But your web hosting control panel is not cPanel.", 'auto-install-free-ssl' )
                                    ?>
                                </p>
                                <p><?= __( "So, based on your web hosting environment, we'll manually handle your automation setup, including automatic SSL installation with either a bash script or Cloudflare CDN.", 'auto-install-free-ssl' ) ?><br /><?= __( "VPS root access is required for the bash script method.", 'auto-install-free-ssl' ) ?></p>
                            </div>
	                    <?php } ?>
                    </div>
                    <script src="https://player.vimeo.com/api/player.js"></script>
                </div>
            </div>

            <!--
            <table style="width: 100%;">
                <tr>
                    <td class="card block-body" style="width: 100%; padding: 0.5% 1.5% 0.5% 1.5%;" colspan="2">
                        <p style="color: green; font-size: 1.2em;"><?php //echo $text ?> &nbsp;&nbsp;<a class="aifs-review-now aifs-review-button" style="margin-left: 0;<?php //echo $style ?>" href="<?php //echo $this->factory->upgrade_url($coupon_code, $query_string) ?>"><?php //echo __( "UPGRADE", 'auto-install-free-ssl' ) ?></a></p>
                    </td>
                </tr>
            </table> -->

	    <?php
	}

	/**
	 * Promotion of Pro version
	 */
    public function pro_version_promotion_v0(){
        ?>
            <table style="width: 100%; margin-top: 2%;" id="comparison">
                <tr>
                    <td class="card block-body" style="width: 100%; padding: 0.5% 1.5% 0.5% 1.5%; background-color: #adff2f;" colspan="2">
                        <p style="color: black; font-size: 1.2em;"><?php //echo sprintf(__('Hello %1$s! Enjoy %2$s automation with our PRO version. Why waste your valuable time with manual SSL renewal every 60 days?', 'auto-install-free-ssl'), aifs_admin_first_name(), ($this->factory->is_cpanel()? __("100%", 'auto-install-free-ssl') : "")) ?></p>
                    </td>
                </tr>
                <tr>
                    <td class="card block-body" style="width: 50%; padding-left: 1.5%;">
                        <h3><?php //echo __( "Comparison of Free vs. Pro Version", 'auto-install-free-ssl' ) ?></h3>
                        <p style="color: green;">&nbsp;</p>
                        <table style="width: 100%; margin-top: 2%;" border="1" cellspacing="0">
                            <thead>
                                <tr>
                                    <th id="columnname" class="manage-column column-columnname" scope="col"><?php //echo __( "Feature", 'auto-install-free-ssl' ) ?></th>
                                    <th id="columnname" class="manage-column column-columnname" scope="col"><?php //echo __( "Free", 'auto-install-free-ssl' ) ?></th>
                                    <th id="columnname" class="manage-column column-columnname pro-version" scope="col"><?php //echo __( "Pro", 'auto-install-free-ssl' ) ?></th>
                                    <th id="columnname" class="manage-column column-columnname pro-version" scope="col"><?php //echo __( "Pro Unlimited", 'auto-install-free-ssl' ) ?></th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr class="alternate">
                                    <td scope="row"><?php //echo __( "Domain Verification", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "Manual", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "Automatic", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "Automatic", 'auto-install-free-ssl' ) ?></td>
                                </tr>
                                <tr>
                                    <td><?php //echo __( "Generate SSL", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "Manual", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __("Automatic", 'auto-install-free-ssl') ?></td>
                                    <td class="pro-version"><?php //echo __("Automatic", 'auto-install-free-ssl') ?></td>
                                </tr>
                                <tr class="alternate">
                                    <td><?php //echo __( "SSL Installation", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "Manual", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __("Automatic", 'auto-install-free-ssl') ?></td>
                                    <td class="pro-version"><?php //echo __("Automatic", 'auto-install-free-ssl') ?></td>
                                </tr>
                                <tr>
                                    <td><?php //echo __( "Cron Job", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "No", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "Automatic", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "Automatic", 'auto-install-free-ssl' ) ?></td>
                                </tr>
                                <tr class="alternate">
                                    <td><?php //echo __( "SSL Renewal", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "Manual", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "Automatic", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "Automatic", 'auto-install-free-ssl' ) ?></td>
                                </tr>
                                <tr>
                                    <td><?php //echo __( "Time Required to Set Up", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "20+ Min (per 60 days)", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "1 Min (once)", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "1 Min (once)", 'auto-install-free-ssl' ) ?> <sup>*</sup></td>
                                </tr>
                                <tr class="alternate">
                                    <td><?php //echo __( "Wildcard SSL", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "No", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "No", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "Yes", 'auto-install-free-ssl' ) ?></td>
                                </tr>
                                <tr>
                                    <td><?php //echo __( "Multisite Support", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "No", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "No", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "Yes", 'auto-install-free-ssl' ) ?></td>
                                </tr>
                                <tr class="alternate">
                                    <td><?php //echo __( "SSL Expiration Chance", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "High", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "No", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "No", 'auto-install-free-ssl' ) ?></td>
                                </tr>
                                <tr>
                                    <td><?php //echo sprintf( __( "One installation works on all%s websites of a cPanel", 'auto-install-free-ssl' ), '<br />') ?></td>
                                    <td><?php //echo __( "No", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "No", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "Yes", 'auto-install-free-ssl' ) ?></td>
                                </tr>
                                <tr class="alternate">
                                    <td><?php //echo __( "Support", 'auto-install-free-ssl' ) ?></td>
                                    <td><?php //echo __( "Forum", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "Email", 'auto-install-free-ssl' ) ?></td>
                                    <td class="pro-version"><?php //echo __( "Email/Phone", 'auto-install-free-ssl' ) ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <p style="text-align: right;"><a href="<?php //echo $this->factory->upgrade_url() ?>" class="button button-primary"><?php //echo __( "Upgrade to Pro", 'auto-install-free-ssl' ) ?></a></p>
	                    <p>* <em><?php //echo sprintf(__( 'If you need the plugin to work on all websites in the same %1$s, you need %2$s minutes (once).', 'auto-install-free-ssl' ), ((isset($this->appConfig['is_cpanel']) && $this->appConfig['is_cpanel']) ? 'cPanel' : 'hosting'), ((isset($this->appConfig['is_cpanel']) && $this->appConfig['is_cpanel']) ? '9' : '10')) ?></em></p>
                    </td>

                    <td class="card block-body" style="width: 50%; padding-left: 1.5%;">
                        <h3><?php //echo __( "One-Click Automation of Free SSL on cPanel [1:42 Min]", 'auto-install-free-ssl' ) ?></h3>
                        <p style="color: green;"><?php //echo __( "Video Tutorial of the Pro / Unlimited Version.", 'auto-install-free-ssl' ) ?></p>

                       <!-- <iframe width="100%" height="281" src="https://www.youtube.com/embed/XYgNPcj_zaM?rel=0" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe> -->
                        <div style="padding:53.33% 0 0 0;position:relative;"><iframe src="https://player.vimeo.com/video/745390051?h=94ba682137&title=0&byline=0&portrait=0" style="position:absolute;top:0;left:0;width:100%;height:100%;" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div><script src="https://player.vimeo.com/api/player.js"></script>
                    </td>
                </tr>
            </table>
        <?php
    }


    /**
     * Register and add settings
     */
    public function generate_ssl_manually_page_init()
    {
        register_setting(
            'aifs_generate_ssl_manually_group', // Option group
            'aifs_generate_ssl_manually', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'aifs_generate_ssl_section_id', // Section ID
            "",
            array( $this, 'print_section_info' ), // Callback
            'aifs_generate_ssl_manually_admin' // Page
        );

        add_settings_field(
            'admin_email',
            /* translators: %s: HTML code to create a superscript with the text '(required)' */
            sprintf(__("Admin email %s(required)%s", 'auto-install-free-ssl'), '<sup>', '</sup>'),
            array( $this, 'admin_email_callback' ),
            'aifs_generate_ssl_manually_admin',
            'aifs_generate_ssl_section_id'
        );

	    add_settings_field(
		    'agree_to_le_terms',
		    /* translators: %1$s: Opening HTML 'div' tag; %2$s: Opening HTML 'a' tag; %3$s: Closing 'a' tag; %4$s: Closing HTML 'sup' tag ("Let's Encrypt™" is a nonprofit SSL certificate authority. Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
		    sprintf(__('%1$sI agree to the %2$sLet\'s Encrypt™ Subscriber Agreement %3$s(required)%4$s', 'auto-install-free-ssl'), '<div id="agree_to_le_terms">', '<a href="'. aifs_get_ca_terms_of_service_url() .'" target="_blank">', '</a> <sup>', '</sup></div>'),
		    array( $this, 'agree_to_le_terms_callback' ),
		    'aifs_generate_ssl_manually_admin',
		    'aifs_generate_ssl_section_id'
	    );

	    add_settings_field(
		    'agree_to_freessl_tech_tos_pp',
		    /* translators: %1$s: Opening HTML 'a' tag; %2$s: Closing 'a' tag; %3$s: Opening HTML 'a' tag; %4$s: Closing 'a' tag; %5$s: Closing 'sup' tag (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
		    sprintf(__('I agree to the FreeSSL.tech %1$sTerms of Service%2$s and %3$sPrivacy Policy %4$s(required)%5$s', 'auto-install-free-ssl'), '<a href="https://freessl.tech/terms-of-service" target="_blank">', '</a>', '<a href="https://freessl.tech/privacy-policy" target="_blank">', '</a> <sup>', '</sup>'),
		    array( $this, 'agree_to_freessl_tech_tos_pp_callback' ),
		    'aifs_generate_ssl_manually_admin',
		    'aifs_generate_ssl_section_id'
	    );

    }

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input (Contains all settings fields as array keys)
	 *
	 * @return array
	 */
    public function sanitize($input)
    {
        $new_input = array();

        if (isset($input['agree_to_le_terms'])) {
            $new_input['agree_to_le_terms'] = sanitize_text_field($input['agree_to_le_terms']);
        }

	    if (isset($input['agree_to_freessl_tech_tos_pp'])) {
		    $new_input['agree_to_freessl_tech_tos_pp'] = sanitize_text_field($input['agree_to_freessl_tech_tos_pp']);
	    }

	    if(isset($input['admin_email'])){
	        $basic_settings = get_option('basic_settings_auto_install_free_ssl');
		    $basic_settings['admin_email'][0] = sanitize_email($input['admin_email']);
		    update_option('basic_settings_auto_install_free_ssl', $basic_settings);

		    if(get_option('aifs_is_admin_email_invalid')) {
			    update_option( 'admin_email', sanitize_email( $input['admin_email'] ) );
		    }
            //refresh this variable, as admin_email has been updated
		    $this->appConfig = aifs_get_app_settings();
	    }

        //Set agree_to_le_terms, agree_to_freessl_tech_tos_pp if not set and if NOT multi domain
        /*if(!(bool)get_option('aifs_is_multi_domain')){

            $basic_settings = get_option('basic_settings_auto_install_free_ssl') ? get_option('basic_settings_auto_install_free_ssl') : add_option('basic_settings_auto_install_free_ssl');

            if(!isset($basic_settings['agree_to_le_terms']) || !isset($basic_settings['agree_to_freessl_tech_tos_pp'])){
                $basic_settings['agree_to_le_terms'] = true;
                $basic_settings['agree_to_freessl_tech_tos_pp'] = true;
                update_option('basic_settings_auto_install_free_ssl', $basic_settings);
            }
        }*/
                
        return $new_input;
    }

    /**
     * Print the Section text
     */
    public function print_section_info()
    {
	    echo $this->factory->domain_alias_availability_text();

	    /* translators: %s: Name of a button, e.g., Next Step */
	    echo "<p>" . sprintf(__( "Please click the '%s' button to proceed.", 'auto-install-free-ssl' ), $this->save_button_text) . "</p>";

	    //echo "<p>" . __( "The SSL certificate authority Let's Encrypt™ needs a valid email ID to register your account. We'll send SSL renewal reminders to this email ID.", 'auto-install-free-ssl' ) . "</p>";
	    echo "<p>" . __( "The SSL certificate authority Let's Encrypt™ needs a valid email ID to register your account.", 'auto-install-free-ssl' ) . "</p>";

            //echo "<br />"; $this->save_button_text

            echo "<style>
                    .form-table{
                        margin-top: 0%;
                    }
                    p.submit{
                        margin-top: 0%;
                    }
                </style>";
        //}

        //echo "<br /><br />";

    }

	/**
	 * agree_to_le_terms
	 */
	public function agree_to_le_terms_callback()
	{
		?>
        <div id="agree_to_le_terms">
            <input type="checkbox" id="agree_to_le_terms" name="aifs_generate_ssl_manually[agree_to_le_terms]" required="required"<?php echo (isset($this->options['agree_to_le_terms']) && 'on' === $this->options['agree_to_le_terms']) ? ' checked' : null; ?> />
        </div>
		<?php
	}

	/**
	 * agree_to_freessl_tech_tos_pp
	 */
	public function agree_to_freessl_tech_tos_pp_callback()
	{
		?>
        <input type="checkbox" id="agree_to_freessl_tech_tos_pp" name="aifs_generate_ssl_manually[agree_to_freessl_tech_tos_pp]" required="required"<?php echo (isset($this->options['agree_to_freessl_tech_tos_pp']) && 'on' === $this->options['agree_to_freessl_tech_tos_pp']) ? ' checked' : null; ?> />

		<?php
	}


	/**
	 * admin_email
     * Improved since 3.6.8
	 */
	public function admin_email_callback()
	{
		if(isset($this->appConfig['admin_email'][0]) && strpos($this->appConfig['admin_email'][0], "secureserver.net") === false){
		    $admin_email = $this->appConfig['admin_email'][0];
		}
		else if(strpos(get_option('admin_email'), "secureserver.net") === false){
			$admin_email = get_option('admin_email');
		}
		else{
			$admin_email = "";
		}

		printf(
			'<input type="email" id="admin_email" name="aifs_generate_ssl_manually[admin_email]" required="required" value="%s" style="width: 300px;" />',
			$admin_email
		);
	}


	public function generate_ssl_step_1(){

	    //return false;

	    if(!isset($this->return_array_step1['current_step_number'])){

		    //$this->logger->clean_log_directory();

			$installed_hosts = null;
			$homedir         = $this->appConfig['homedir'];
			/*$cPanel = [
				'is_cpanel' => $this->appConfig['is_cpanel'],
			];*/
			$cPanel        = [
				'is_cpanel' => false
			];

			/*$all_domains   = $this->appConfig['all_domains'];
			$single_domain = $all_domains[0];*/
		    $domain = aifs_get_domain(true);

		    $single_domain = [
				    'domain' => $domain,
				    'serveralias' => 'www.'.$domain,
				    'documentroot' => $this->factory->document_root_wp() //@todo 20 June 2024, this is creating issue. Check FreeSSLAuto.php what happens there.
            ];

			$controller = new Controller();
		    $domains_array_raw = $controller->domains_array_raw($single_domain);
		    //domains array
			$domains_array = $controller->domainsArray( $domains_array_raw, $this->appConfig['domains_to_exclude'] );

			/*if(!is_array($this->appConfig['admin_email'])){
				$this->appConfig['admin_email'] = [get_option('admin_email')];
			}*/

			$freessl = new AcmeV2( $homedir . DS . $this->appConfig['certificate_directory'], $this->appConfig['admin_email'], $this->appConfig['is_staging'], $this->appConfig['dns_provider'], $this->appConfig['key_size'], $cPanel, $this->appConfig['server_ip'] );

			if ( count( $domains_array ) > 0 ) {
				//Start the process to generate SSL
				/* translators: %s: A domain name, e.g., example.com */
				$this->logger->log( sprintf(__("Generating SSL for %s", 'auto-install-free-ssl'), $domains_array[0]) );
				$this->logger->log( __( "The domains array is given below: ", 'auto-install-free-ssl' ));

				if ( $this->logger->is_cli() ) {
					$this->logger->log( print_r( $domains_array, true ) );
				} else {
					$this->logger->log( '<pre>' . print_r( $domains_array, true ) . '</pre>' );
				}

				try {
					//$freessl->obtainSsl($domains_array, $single_domain['documentroot'], false, $this->appConfig['country_code'], $this->appConfig['state'], $this->appConfig['organization']);

                    $return_array_step1 = $freessl->step1GetAuthenticationTokens( $domains_array, $single_domain['documentroot'], false, $this->appConfig['country_code'], $this->appConfig['state'], $this->appConfig['organization'] );

					//20 June 2024 - delete after test
					//$this->logger->log( '<pre>' . print_r( $return_array_step1, true ) . '</pre>' );

                    $expected_status_codes = [200, 201, 202, 204];

					if (!in_array($return_array_step1['response']['curlinfo_http_code'], $expected_status_codes, true)) {
						$error_text = "<span style='color: red;'>";
						$error_text .= "Oops! Let's Encrypt™ server returned an error; it is given below.";
						$error_text .= "</span>";
						$error_text .=  " <span style='font-weight: bold;'>". __( "Please check the log for details information.", 'auto-install-free-ssl' ) ."</span> ";

						/* translators: %1$s: Opening HTML 'a' tag; %2$s: Closing 'a' tag (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
						$error_text .= sprintf( __( 'Please try again after some time and %1$sclick here%2$s if you need help.', 'auto-install-free-ssl' ), '<a href="https://freessl.tech/free-ssl-certificate-for-wordpress-website/#help" target="_blank">', '</a>' ) . "<br />";

						$error_text .= '<pre>'. print_r($return_array_step1, true) .'</pre>';

						aifs_add_flash_notice($error_text, "error");
						$redirect_url = admin_url('admin.php?page=aifs_generate_ssl_manually');
						wp_redirect($redirect_url);
						exit;
					}

					$return_array_step1['domains_array'] = $domains_array;
					$return_array_step1['current_step_number'] = 2;
					update_option( 'aifs_return_array_step1_manually', $return_array_step1 );
					$this->return_array_step1 = $return_array_step1;

				} catch ( \Exception $e ) {
					$this->logger->log_v2( 'error', $e->getMessage() );
					$this->logger->log_v2( 'error', $e->getTraceAsString(), [ 'event' => 'exit' ] );
				}
			}
		}
	}


	public function generate_ssl_step_2() {

		/*echo "<pre>";
		print_r($this->return_array_step1);
		echo "</pre>";*/

	    $return_array_step1 = $this->return_array_step1;

        /* In DNS-01 display this suggestion

            $suggestion = "<p style='color: red;'><br /><strong>We suggest using HTTP-01 challenge for this domain. It is faster than DNS-01 in most use cases.</strong></p>

                           <p>Moreover, all DNS service providers don't let you set multiple TXT records for the same hostname. Please contact your DNS service provider to ensure if they support multiple TXT records for the same hostname.</p>";
         *
         */

        //Get the active tab from the $_GET param
        $default_tab = "http-01";
        $tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;

		echo $this->progress_bar(2);
        ?>
        <!-- <h2 style='color: black'>Step 2 of 3</h2> -->

        <p style="text-align: center;"><i><?= __( "You are a step away from saving $90", 'auto-install-free-ssl' ) ?></i></p>
        <p style="font-size: large; text-align: center;"><?= __( "Please complete any one from HTTP-01 and DNS-01 challenges to verify your domain /subdomain ownership.", 'auto-install-free-ssl' ) ?></p>

        <!-- Tabs start -->
        <br />

        <!--
        <nav class="nav-tab-wrapper">
            <a href="?page=aifs_generate_ssl_manually" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>"><?= __( "HTTP-01", 'auto-install-free-ssl' ) ?></a>
            <a href="?page=aifs_generate_ssl_manually&tab=dns-01" class="nav-tab <?php if($tab==='dns-01'):?>nav-tab-active<?php endif; ?>"><?= __( "DNS-01", 'auto-install-free-ssl' ) ?></a>
        </nav>

        <div class="tab-content"> -->
        <?php

        //if($tab == 'http-01'){
            ?>
        <table style="width: 100%; margin-bottom: 1%;">
        <tr>
        <td class="card block-body" style="width: 49%; padding-top: 1%; padding-bottom: 2%; padding-left: 2%;">
	        <span style="font-size: large; font-weight: bold;"><?= __( "HTTP-01", 'auto-install-free-ssl' ) ?></span>
            <!-- <span style="font-weight: bold; float: right;"><span class="dashicons dashicons-format-video"></span>
		    <?php if(!isset($this->appConfig['is_cpanel']) || !$this->appConfig['is_cpanel']){ ?>
                <a href="https://www.youtube.com/watch?v=SaFgDjlqA9c" target="_blank"><?= __( "Video", 'auto-install-free-ssl' ) ?></a></span> -->
            <?php }else{ ?>
                <a href="https://www.youtube.com/watch?v=FYoob-hkEZg" target="_blank"><?= __( "Video", 'auto-install-free-ssl' ) ?></a></span> -->
	        <?php } ?>

            <hr /><br />

            <p><strong><?= __( "1.", 'auto-install-free-ssl' ) ?></strong> <?= __( "Please open an FTP client (e.g., FileZilla) or File Manager of your web hosting control panel.", 'auto-install-free-ssl' ) ?></p>

            <p><strong><?= __( "2.", 'auto-install-free-ssl' ) ?></strong> <?= /* translators: %1$s: A domain name, e.g., example.com; %2$s: Name of a directory, e.g., '.well-known'; %3$s: Name of another directory, e.g., 'acme-challenge' */ sprintf(__('Browse to the document root of %1$s. Create a directory %2$s and another directory %3$s inside the %2$s directory.', 'auto-install-free-ssl'), ('<em>' . key($return_array_step1['domain_data']) . '</em>'), '<strong>.well-known</strong>', '<strong>acme-challenge</strong>') ?>

                <?= aifs_server_software() == 'ms-iis' ? " " . __( "If you face any issues (e.g., 'Invalid path specified') creating directories, create 'Virtual Directory' instead.", 'auto-install-free-ssl' ) : "" ?></p>

            <p><strong><?= __( "3.", 'auto-install-free-ssl' ) ?></strong> <?= __( "Download the following HTTP-01 challenge files:", 'auto-install-free-ssl' ) ?></p>

            <div class="challenge-files">
            <?php
	        ///echo count($return_array_step1['domain_data']) . " domains to verify.<br />";
            $n = 1;
            $file_url = wp_nonce_url( get_site_url().$_SERVER['REQUEST_URI'], 'aifs_challenge_http', 'aifschallengehttp' )."&domain=";

            $challenge_file_links = [];

            //$uri = "http://${domain}/.well-known/acme-challenge/".$challenge['token'];

            foreach ($return_array_step1['domain_data'] as $domain => $data) {
                if(!$data['verified']) {
	                echo "<pre>";
	                echo "<a href='" . $file_url . $domain . "'><span class='dashicons dashicons-download'></span>&nbsp;" . __( "Challenge File", 'auto-install-free-ssl' ) . " $n</a>";
	                echo "</pre>";
	                $challenge_file_links[] = "http://$domain/.well-known/acme-challenge/" . $data['challenge']['token'];
	                $n ++;
                }
	        }

	        /*foreach ($return_array_step1['domain_data'] as $domain => $data) {
		        echo "<strong>$domain</strong><br />";
		        echo "<pre>";
		        print_r($data['http-01']);
		        echo "</pre><br /><br />";
	        }*/
	        ?>
            </div>

            <p><strong><?= __( "4.", 'auto-install-free-ssl' ) ?></strong> <?php /* translators: %s: Name of the directory, i.e., 'acme-challenge' */ ?> <?= sprintf(__( "Upload the above-downloaded challenge files into the %s directory (mentioned in serial number 2).", 'auto-install-free-ssl' ), "'acme-challenge'") ?>

                <?= aifs_server_software() == 'ms-iis' ? " " . __( "If you face any issues uploading files, create files with the same file name and content.", 'auto-install-free-ssl' ) : "" ?></p>

            <!-- Use foreach to avoid blank link -->
            <p><br /><?= __( "Uploaded files should be available at", 'auto-install-free-ssl' ) ?> </p>

            <?php
                /*$n = 1;
                foreach ($challenge_file_links as $link){
                    echo "<a href='$link' target='_blank'> Link $n</a>, ";
                }*/

                if(isset($challenge_file_links[0])){
	                $link = $challenge_file_links[0];
	                echo "<a href='$link' target='_blank'> " . __( "Link 1", 'auto-install-free-ssl' ) . "</a> ";
                }

                if(isset($challenge_file_links[1])){
                    $link = $challenge_file_links[1];
                    echo __( "and", 'auto-install-free-ssl' ) . " <a href='$link' target='_blank'> " . __( "Link 2", 'auto-install-free-ssl' )."</a> ";
                }

                $code = '<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <staticContent>
            <mimeMap fileExtension="." mimeType="text/plain" />
        </staticContent>
    </system.webServer>
</configuration>';

            ?>
            .</p>

            <p><?= __( "The content of the above links should EXACTLY match the content of the downloaded files.", 'auto-install-free-ssl' ) ?><?= aifs_server_software() == 'ms-iis' ? " " . /* translators: %1$s: Name of a file, e.g., 'web.config'; %2$s: Name of a directory, e.g., '.well-known' */ sprintf( __( 'If the above links display errors, create a %1$s file inside the %2$s directory and paste the following content into it:', 'auto-install-free-ssl' ), '<strong>web.config</strong>', '.well-known') . ' <pre><code>'.htmlspecialchars($code).'</code></pre>' : "" ?></p>

            <p><br /><em><?= __( "Is everything okay? Now click the button below.", 'auto-install-free-ssl' ) ?></em></p>

        <?php echo $this->verify_domain_form(); ?>
        </td>

            <td class="" style="width: 2%;"></td>

        <td class="card block-body" style="width: 49%; padding-top: 1%; padding-bottom: 2%; padding-left: 2%;">
        <?php
        //}
        //elseif($tab == 'dns-01'){
	        $registeredDomain = aifs_getRegisteredDomain( aifs_get_domain(true) );
            $sl_1 = __( "1.", 'auto-install-free-ssl' );
	        ?>
            <span style="font-size: large; font-weight: bold;"><?= __( "DNS-01", 'auto-install-free-ssl' ) ?></span><hr /><br />

            <?php /* translators: %s: A domain name, e.g., example.com */ ?>
            <p><strong><?= $sl_1 ?></strong> <?= sprintf(__( "Please log in to your DNS service provider's account, for %s, and add the TXT records given below:", 'auto-install-free-ssl' ), "<strong><em>$registeredDomain</em></strong>") ?></p>
	        <?php
	        $set_no = "A";
	        foreach ($return_array_step1['domain_data'] as $domain => $data) {
		        if(!$data['verified']) {
			        echo "<br /><p><u>".__( "Record Set", 'auto-install-free-ssl' )." $set_no</u></p>";
			        echo "<p>".__( "TXT record name/host:", 'auto-install-free-ssl' )." <strong>" . $data['dns-01']['dns_txt_name'] . "</strong></p>";
			        echo "<p>".__( "TXT record value:", 'auto-install-free-ssl' )." <strong>" . $data['dns-01']['dns_txt_record'] . "</strong></p>";
			        $set_no = "B";
		        }
	        }
	        ?>

	        <?php /* translators: %1$s: Opening HTML 'strong' tag; %2$s: Closing 'strong' tag; (Opening and closing 'strong' tags make the enclosed text bold.) */ ?>
            <p><br /><?= sprintf(__('TTL (Time to Live) for both: %1$s1 minute%2$s recommended. Otherwise, the minimum value supported by your DNS service provider.', 'auto-install-free-ssl'), '<strong>', '</strong>') ?></p>

            <p><br /><strong><?= __( "2.", 'auto-install-free-ssl' ) ?></strong> <?php /* translators: %1$s: Opening HTML 'strong' tag; %2$s: Closing 'strong' tag; %3$s: Opening HTML 'em' tag; %4$s: Closing 'em' tag (Opening and closing 'strong' tags make the enclosed text bold. Opening and closing 'em' tags make the enclosed text emphasized.) */ ?> <?= sprintf(__('Please wait %1$sat least%2$s 15 minutes for the above DNS records to propagate and %3$sclick the button below%4$s.', 'auto-install-free-ssl'), '<strong>', '</strong>', '<em>', '</em>') ?></p>


	        <?php
	        /*foreach ($return_array_step1['domain_data'] as $domain => $data) {
		        echo "<strong>$domain</strong><br />";
		        echo "<pre>";
		        print_r($data['dns-01']);
		        echo "</pre><br /><br />";
	        }*/

	        echo $this->verify_domain_form("dns-01");
        //}

        ?>
        </td>
        </tr>
        </table>
        <!-- </div> -->
        <!-- Tabs end -->

        <?php
            /*echo "<pre>";
            print_r($return_array_step1);
            echo "</pre>";*/

	}


	public function verify_domain_form($challenge_type = "http-01"){
		$html = '<form method="post" action="'.admin_url('admin.php?page=aifs_generate_ssl_manually').'">	
        			 <input type="hidden" name="aifs_challenge_type" value="'.$challenge_type.'" />'.
		        wp_nonce_field('aifsverifydomain', 'aifs_verify_domain', false, false);

		/* translators: %s: Type of the challenge (HTTP-01 or DNS-01) */
        $confirmation_text = sprintf(__("Are you sure you have completed the %s challenges?", 'auto-install-free-ssl'), strtoupper($challenge_type));

        //$button_text = __( 'Verify Domain &amp; Get SSL', 'auto-install-free-ssl' );
		/* translators: %s: Type of domain verification (HTTP-01 or DNS-01) */
		$button_text = sprintf( __( "Verify Domain (%s) & Generate Free SSL", 'auto-install-free-ssl' ), strtoupper($challenge_type));
        $css_class = "button button-primary button-hero";

		$html .=	 '<button type="submit" name="aifs_submit" class="'.$css_class.'" onclick="return aifs_confirm(\''. $confirmation_text .'\')">'. $button_text .'</button>
      			</form>';

		return $html;

	}


	/**
	 * Download HTTP-01 challenge file handler
	 */
	public function download_http01_challenge_file_handler(){

		//initialize the Acme Factory class
		$acmeFactory = new AcmeFactory($this->appConfig['homedir'].'/'.$this->appConfig['certificate_directory'], $this->appConfig['acme_version'], $this->appConfig['is_staging']);

		if ( isset( $_GET['aifschallengehttp'] ) ) {
			if ( !wp_verify_nonce( $_GET['aifschallengehttp'], 'aifs_challenge_http' ) ) {
				wp_die(__( "Access denied", 'auto-install-free-ssl' ));
			}

            $domain = $_GET['domain'];
			$file_name = $this->return_array_step1['domain_data'][$domain]['http-01']['file_name'];
			$file_content = $this->return_array_step1['domain_data'][$domain]['http-01']['payload'];
			$domain_path = $acmeFactory->getDomainPath($domain);
			$file_path = $domain_path . DS . $file_name;

			if (!is_dir($domain_path)) {
				@mkdir($domain_path, 0700, true);
			}

			file_put_contents($file_path, $file_content);

			$factory = new Factory();
			$factory->download_file($file_path, $file_name, true);

		}

	}


	public function generate_ssl_step_3(){

		if ( isset( $_POST['aifs_challenge_type'] ) && !wp_verify_nonce($_POST['aifs_verify_domain'], 'aifsverifydomain') ) {
			wp_die(__( "Access denied", 'auto-install-free-ssl' ));
		}
		else {
			$home_options = new HomeOptions();

			//echo "<h2 style='color: black'>Step 3 of 3</h2>";

		    if(isset($this->return_array_step1['ssl_cert_generated']) && $this->return_array_step1['ssl_cert_generated']){
			    echo $this->progress_bar(3);

			    /* translators: %1$s: a domain name, e.g., example.com; %2$s: Opening HTML 'strong' tag; %3$s: Closing 'strong' tag. (Opening and closing 'strong' tags make the enclosed text bold.) */
			    //echo sprintf(__('SSL certificate already issued to %1$s, and %2$sthe plugin has saved you $90.%3$s', 'auto-install-free-ssl'), ("<em>" . $this->return_array_step1['domains_array'][0] . "</em>"), '<strong>', '</strong>');

			    /* translators: %s: a domain name, e.g., example.com */
			    //echo "<p>" . sprintf(__('SSL certificate already issued to %s', 'auto-install-free-ssl'), "<em>" . $this->return_array_step1['domains_array'][0] . "</em>") . "</p>";

			    //echo __("Please download it from the links given below.", 'auto-install-free-ssl');

		        echo $home_options->this_domain_ssl_data();

		        echo '<div id="ssl_renewal_form">';
                echo "<br />". $this->regenerate_ssl_form();
                echo "</div>";

			    //Display reset/renew SSL button to restart from step 1
			     /*$this->return_array_step1['current_step_number'] = 2;
			     unset($this->return_array_step1['ssl_cert_generated']);
			     update_option( 'aifs_return_array_step1_manually', $this->return_array_step1 );*/

			    /*echo "<pre>";
			    print_r($this->return_array_step1);
			    echo "</pre>";*/

		    }
		    else {
		        //return false;
			    $homedir = $this->appConfig['homedir'];
			    $cPanel  = [
				    'is_cpanel' => false
			    ];
			    $freessl = new AcmeV2( $homedir . DS . $this->appConfig['certificate_directory'], $this->appConfig['admin_email'], $this->appConfig['is_staging'], $this->appConfig['dns_provider'], $this->appConfig['key_size'], $cPanel, $this->appConfig['server_ip'] );

			    $number_of_validated_domains_internal = 0;
			    $number_of_validated_domains = 0;
			    $error_text = "";

			    foreach ( $this->return_array_step1['domain_data'] as $domain => $value ) {
				    if ( $value['verified'] ) {
					    //Domain ownership already verified. Skip the verification process
					    //$this->logger->log("Domain (${domain}) already verified. Skip the verification process...");
					    //++ $number_of_validated_domains;
                        ++ $number_of_validated_domains_internal;
				    } else {
				        /*
				         * Run internal check here
				         */
				        if($_POST['aifs_challenge_type'] == "http-01"){

                            if ( $freessl->verifyDomainOwnershipHttp01Internal( $domain, $value ) ) {
                                ++ $number_of_validated_domains_internal;
                            } else {
                                //save error msg in a variable
                                if ( strlen( $error_text ) > 1 ) {
                                    $error_text .= "<br />";
                                }
                                $error_text .= "<span style='color: red;'>";
                                $error_text .= __( "Domain", 'auto-install-free-ssl' ) . ": " . $domain . "  →  ";
                                $error_text .= __( "Oops! We could not verify HTTP-01 challenges. Please check whether the uploaded HTTP challenge files are publicly accessible. Some hosts purposefully block BOT access to the acme-challenge folder, then please try DNS-based verification.", 'auto-install-free-ssl' ) . " ";
                                $error_text .= "</span>";
                                /*$error_text .= "<span style='color: green;'>";
                                $error_text .= __("Upgrade to the PRO version for fully automatic domain verification, automated SSL installation & renewal.", 'auto-install-free-ssl');
                                $error_text .= "</span>";*/
                            }

				        }

				        if($_POST['aifs_challenge_type'] == "dns-01"){
				            if($freessl->verifyDomainOwnershipDns01Internal($domain, $value, false)) {
					            ++ $number_of_validated_domains_internal;
				            }
				            else{
					            //save error msg in a variable
					            if(strlen($error_text) > 1){
						            $error_text .= "<br />";
					            }
					            $error_text .= "<span style='color: red;'>";
					            $error_text .= __("Domain", 'auto-install-free-ssl') . ": " . $domain . "  →  ";
					            $error_text .= __("Oops! We could not verify DNS records. Please check whether you have added the DNS records correctly. Did you add DNS records just now? Please try again after 15 minutes.", 'auto-install-free-ssl') . " ";
					            $error_text .= "</span>";
					            /*$error_text .= "<span style='color: green;'>";
					            $error_text .= __("Upgrade to the PRO version for fully automatic domain verification, automated SSL installation & renewal.", 'auto-install-free-ssl');
					            $error_text .= "</span>";*/
				            }
				        }
				    }
			    }


                if(\count( $this->return_array_step1['response']['authorizations'] ) === $number_of_validated_domains_internal ) {
                    foreach ( $this->return_array_step1['domain_data'] as $domain => $value ) {
                        if($value['verified']){
	                        ++ $number_of_validated_domains;
                        }
                        else if ($freessl->step2VerifyDomainOwnership( $domain, $value, $_POST['aifs_challenge_type'], $this->return_array_step1 ) ) {
                            ++ $number_of_validated_domains;
                        }
                        else{
	                        //save error msg in a variable
	                        if(strlen($error_text) > 1){
		                        $error_text .= "<br />";
	                        }
	                        $error_text .= "<span style='color: red;'>";
	                        /* translators: %s: A domain name, e.g., example.com ("Let's Encrypt" is a nonprofit SSL certificate authority.) */
	                        $error_text .= sprintf(__("Oops! Let's Encrypt™ could not validate ownership of the domain %s due to some error.", 'auto-install-free-ssl'), $domain);
	                        $error_text .= "</span>";
                        }
                    }
                }
                else{
                    /*
                     * display saved error msg in Admin alert and redirect
                    */
	                //$error_text = "<span style='color: red;'>$error_text</span>";
                    //$error_text  .= "authorizations: " .count( $this->return_array_step1['response']['authorizations'] ) ."  number_of_validated_domains_internal: ".$number_of_validated_domains_internal ." ";

                    $error_text .= "<span style='color: green;'>";
                    /* translators: %1$s: Opening HTML 'a' tag; %2$s: Closing 'a' tag (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
                    $error_text .= "<br />" . sprintf( __( 'Upgrade to the %1$sPremium Version%2$s for fully automatic domain verification, automated SSL installation & renewal.', 'auto-install-free-ssl' ), '<a href="' . $this->factory->upgrade_url() . '">', '</a>' );
                    $error_text .= "</span>";

	                /*echo "<pre>";
                        print_r($this->return_array_step1);
	                echo "</pre>";
	                echo $error_text;
	                return;*/

	                aifs_add_flash_notice($error_text, "error");
	                $redirect_url = admin_url('admin.php?page=aifs_generate_ssl_manually');
	                /*if($_POST['aifs_challenge_type'] == "dns-01"){
		                $redirect_url .= '&tab='.$_POST['aifs_challenge_type'];
	                }*/
	                wp_redirect($redirect_url);
	                exit;
                }


			    //Proceed to issue SSL only if total number of domains = total number of validated domains
			    if ( \count( $this->return_array_step1['response']['authorizations'] ) === $number_of_validated_domains ) {
				    // requesting certificate

				    if ( $freessl->step3GenerateSSL( $this->return_array_step1['domains_array'], false, $this->return_array_step1 ) ) {
					    //return true;
                        //reload option to keep 'response_final' saved in step3GenerateSSL
					    $this->return_array_step1 = get_option('aifs_return_array_step1_manually');

					    //update option
					    $this->return_array_step1['current_step_number'] = 3;
					    $this->return_array_step1['ssl_cert_generated']  = true;
					    update_option( 'aifs_return_array_step1_manually', $this->return_array_step1 );

					    echo $this->progress_bar(3);
					    echo "<h3 style='background: green; color: white; line-height: 1.6em; padding: 2%;'>";

					    //echo sprintf(__('Congratulations! SSL certificate has been issued to %s, and the plugin has saved you $90.', 'auto-install-free-ssl'), ("<em>" . $this->return_array_step1['domains_array'][0] . "</em>")) . "<br /><br />";

					    /* translators: %s: a domain name, e.g., example.com */
					    echo sprintf(__('Congratulations! SSL certificate has been issued to %s, and the plugin has saved you money.', 'auto-install-free-ssl'), "<em>" . $this->return_array_step1['domains_array'][0] . "</em>") . "<br /><br />";

					    echo __("Please download it from the links given below.", 'auto-install-free-ssl');

					    echo "</h3>";

					    echo $home_options->this_domain_ssl_data();

				    } else {
					    echo $this->progress_bar(3);
					    //return false;
					    /* translators: %s: A domain name, e.g., example.com */
					    $error_text .= "<span style='color: red'>". sprintf(__("Sorry, the SSL certificate was NOT issued to %s due to an error. Please check the log for details.", 'auto-install-free-ssl'), ("<em>" . $this->return_array_step1['domains_array'][0] . "</em>")) ."</span>";
					    $error_text .=  " <span style='font-weight: bold;'>". __("Please try again later.", 'auto-install-free-ssl') ."</span>";

					    //$this->logger->log_v2( 'error', sprintf("Sorry, the SSL certificate was NOT issued to %s due to an error. Please try again after some time.", ("<em>" . $this->return_array_step1['domains_array'][0] . "</em>")) );

					    aifs_add_flash_notice($error_text, "error");
					    $redirect_url = admin_url('admin.php?page=aifs_generate_ssl_manually');
					    /*if($_POST['aifs_challenge_type'] == "dns-01"){
						    $redirect_url .= '&tab='.$_POST['aifs_challenge_type'];
					    }*/
					    wp_redirect($redirect_url);
					    exit;
				    }

			    } else {
				    //SSL certificate can't be issued
				    echo $this->progress_bar(3);

				    /* translators: %1$d: A number; %2$d: Another number; %3$s: A domain name, e.g., example.com */
				    $text = sprintf(__('The number of authorizations: %1$d. But the number of validated domains: %2$d. Sorry, the SSL certificate can not be issued to %3$s.', 'auto-install-free-ssl'), count( $this->return_array_step1['response']['authorizations'] ), $number_of_validated_domains, $this->return_array_step1['domains_array'][0]) . " ";
				    $text_for_log = sprintf('The number of authorizations: %1$d. But the number of validated domains: %2$d. Sorry, the SSL certificate can not be issued to %3$s.', count( $this->return_array_step1['response']['authorizations'] ), $number_of_validated_domains, $this->return_array_step1['domains_array'][0]) . " "; //since 3.6.1, Don't translate this error message.

				    $difference = \count( $this->return_array_step1['response']['authorizations'] ) - $number_of_validated_domains;

				    if($difference > 1){
					    /* translators: %d: A plural number */
					    $text .= sprintf(__('%d domains were not validated.', 'auto-install-free-ssl'), $difference);
					    $text_for_log .= sprintf('%d domains were not validated.', $difference); //since 3.6.1, Don't translate this error message.
				    }
				    else{
					    /* translators: %d: A singular number, i.e., 1 */
					    $text .= sprintf(__('%d domain was not validated.', 'auto-install-free-ssl'), $difference);
					    $text_for_log .= sprintf('%d domain was not validated.', $difference); //since 3.6.1, Don't translate this error message.
				    }

				    $this->logger->log_v2( 'error', $text_for_log );

				    //save error msg in a variable
				    if(strlen($error_text) > 1){
					    $error_text .= "<br />";
				    }
				    $error_text .= $text;

				    //return false;
				    $error_text .=  " <span style='font-weight: bold;'>". __( "Please check the log for details information.", 'auto-install-free-ssl' ) ."</span>";

				    aifs_add_flash_notice($error_text, "error");
				    $redirect_url = admin_url('admin.php?page=aifs_generate_ssl_manually');
				    /*if($_POST['aifs_challenge_type'] == "dns-01"){
					    $redirect_url .= '&tab='.$_POST['aifs_challenge_type'];
				    }*/
				    wp_redirect($redirect_url);
				    exit;
			    }

		    }
			/*echo "<pre>";
			print_r($this->return_array_step1);
			echo "</pre>";*/
		}

		//After click re generate SSL button
        if(isset( $_POST['aifs_proceed_regenerate'] )) {
	        if ( ! wp_verify_nonce( $_POST['aifs_regenerate_ssl'], 'aifsregeneratessl' ) ) {
		        wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
	        } else {
		        //reset option
		        unset($this->return_array_step1);
				update_option( 'aifs_return_array_step1_manually', $this->return_array_step1 );
				//delete_option( 'aifs_is_generated_ssl_installed' ); //Moved to step3GenerateSSL() in AcmeV2.php
		        wp_redirect(menu_page_url('aifs_generate_ssl_manually'), 301);
	        }
        }
	}


	/**
	 * @param int $step_number
	 *
	 * @return string
	 */
	public function progress_bar($step_number = 1){

	    $initiate = __( "Initiate", 'auto-install-free-ssl' );
		$verify = __( "Verify Domain", 'auto-install-free-ssl' );
		$download = __( "Download & Install SSL", 'auto-install-free-ssl' );

	    $html = ' <div class="prcontainer">
          <ul class="progressbar">';

		if($step_number == 1) {
			$html .= '<li class="active">' . $initiate . '</li>
            <li>' . $verify . '</li>
            <li>' . $download . '</li>';
		}
		elseif($step_number == 2) {
			$html .= '<li class="done">' . $initiate . '</li>
            <li class="active">' . $verify . '</li>
            <li>' . $download . '</li>';
		}
		elseif($step_number == 3) {
		    if(isset($this->return_array_step1['ssl_cert_generated']) && $this->return_array_step1['ssl_cert_generated'] && get_option('aifs_is_generated_ssl_installed')){
		        $class = "done";
			}
		    else{
			    $class = "active";
		    }

			$html .= '<li class="done">' . $initiate . '</li>
            <li class="done">' . $verify . '</li>
            <li class="'. $class .'">' . $download . '</li>';
		}
        elseif($step_number == 4) {
			$html .= '<li class="done">' . $initiate . '</li>
            <li class="done">' . $verify . '</li>
            <li class="done">' . $download . '</li>';
		}

		$html .= '</ul>
        </div>';

		return $html;
	}


	public function regenerate_ssl_form($button_text = null, $button_small = false){
		$html = '<form method="post" action="'.admin_url('admin.php?page=aifs_generate_ssl_manually').'">	
        			 <input type="hidden" name="aifs_proceed_regenerate" value="yes" />'.
		        wp_nonce_field('aifsregeneratessl', 'aifs_regenerate_ssl', false, false);

		$confirmation_text = __("You need to complete every step again manually. Will you proceed?", 'auto-install-free-ssl');

		$button_text = is_null($button_text) ? __( "Re-generate (renew) SSL", 'auto-install-free-ssl' ) : $button_text;

		if($button_small) {
			$css_class = "button button-primary";
		}
		else{
			$css_class = "button button-primary button-hero";
		}

		/*$html .=	 '<button type="submit" name="aifs_submit" class="'.$css_class.'" onclick="return confirm(\''. $confirmation_text .'\')">'. $button_text .'</button>
      			</form>';*/

		$html .=	 '<button type="submit" name="aifs_submit" class="'.$css_class.'" onclick="return aifs_confirm(\''. $confirmation_text .'\')">'. $button_text .'</button>
      			</form>';

		return $html;

	}


	public function generate_ssl_all_domains_verified_already(){
		$number_of_validated_domains = 0;
		if(isset($this->return_array_step1['domain_data']) && isset($this->return_array_step1['response']['authorizations'])) {
			foreach ( $this->return_array_step1['domain_data'] as $domain => $value ) {
				if ( $value['verified'] ) {
					++ $number_of_validated_domains;
				}
			}

			//return true only if total number of domains = total number of validated domains
			if ( \count( $this->return_array_step1['response']['authorizations'] ) === $number_of_validated_domains ) {
				return true;
			} else {
				return false;
			}
		}
		else{
		    return false;
		}

	}

}