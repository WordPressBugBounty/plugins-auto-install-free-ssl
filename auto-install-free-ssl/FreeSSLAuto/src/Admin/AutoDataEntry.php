<?php

/**
 * @package Auto-Install Free SSL
 * This package is a WordPress Plugin. It issues and installs free SSL certificates in cPanel shared hosting with complete automation.
 *
 * @author Anindya Sundar Mandal <anindya@SpeedUpWebsite.info>
 * @copyright  Copyright (C) 2020, Anindya Sundar Mandal
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @link       https://SpeedUpWebsite.info
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
 * Automatic Data Entry for Single Step SSL Installation or one click SSL installation and Free (Manual) version
 *
 */
class AutoDataEntry
{
    public $factory;
    public $is_cpanel;

    /**
     * Start up
     * @param bool $is_cpanel
     */
    public function __construct($is_cpanel = false)
    {
	    if (!defined('ABSPATH')) {
		    die(__( "Access denied", 'auto-install-free-ssl' ));
	    }
        
        $this->factory =  new Factory();
        
        $this->is_cpanel = $is_cpanel;

    }
    
    
    /**
     * Make other necessary data entry automatically (basic settings etc)
     */
    public function data_entry(){

        //If SSL issued for multi
        /*$is_multi_domain = (int) $this->factory->is_multi_domain__premium_only();

        if(!get_option('aifs_is_multi_domain')){
        	if(!$is_multi_domain && aifs_license_is_unlimited__premium_only()){
        		//@ to do redirect to aifs_is_multi_domain settings page


	        }
        	else{
		        add_option('aifs_is_multi_domain', $is_multi_domain);
	        }
        }*/

        //Get this website's domain
        $domain = aifs_get_domain(true);
        $serveralias = 'www.'.$domain;

        if(strlen($domain) > 3 && strpos($domain, '.') !== false){
            //Get current user details
            //$current_user = wp_get_current_user();
            //$admin_email = array();
            //$email = get_option('admin_email');

            //Basic settings array
            //$basic_settings = $this->basic_settings_default_v3();

            /*
             * Now, update in database
             */
            update_option('basic_settings_auto_install_free_ssl', $this->basic_settings_default_v3());
            
            
            //All Domains settings: useful if not cPanel
            $domains_settings = [
            //Set this domain details below
                //@value array
                'all_domains' => [
                    [
                        'domain' => $domain,
                        'serveralias' => $serveralias,
                        'documentroot' => $this->factory->document_root_wp()
                    ],
                ]                        
            ];
            
            
            /*
             * Now, update in database
             */
            
            update_option('all_domains_auto_install_free_ssl', $domains_settings);
            
            
            //Exclude domains        
            $exclude_domains = [
            // Exclution list
                //@value array
                'domains_to_exclude' => []
            ];
            
            
            /*
             * Now, update in database
             */
            
            update_option('exclude_domains_auto_install_free_ssl', $exclude_domains);
            
            
            // DNS service providers - required only if you want to issue Wildcard SSL
            
            $dns_service_provider = [
                
                //@value array
                'dns_provider' => [
                    [
                        'name' => false, //Supported providers are GoDaddy, Namecheap, Cloudflare (please write as is)
                        //Write false if your DNS provider if not supported. In that case, you'll need to add the DNS TXT record manually. You'll receive the TXT record details by automated email. PLEASE NOTE THAT in such case you must set 'dns_provider_takes_longer_to_propagate' => true  //@value string or boolean
                        'api_identifier' => '', //API Key or email id or user name   //@value string
                        'api_credential' => '', //API secret. Or key, if api_identifier is an email id   //@value string
                        'dns_provider_takes_longer_to_propagate' => true, //By default this app waits 2 minutes before attempt to verify DNS-01 challenge. But if your DNS provider takes more time to propagate out, set this true. Please keep in mind, depending on the propagation status of your DNS server, this settings may put the app waiting for hours.  //@value boolean
                        'domains' => [], //Domains registered with this DNS provider   //@value array
                        'server_ip' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null
                    ],
                ]
            ];
            
            
            /*
             * Now, update in database
             */
            
            update_option('dns_provider_auto_install_free_ssl', $dns_service_provider);
            
            
            //Set cron_notify = on
            
            $cron_notify = [
                'cron_notify' => 'on'            
            ];
            
            /*
             * Now, update in database
             */        
            update_option('add_cron_job_auto_install_free_ssl', $cron_notify);

            //@since 4.0.0
            update_option('aifs_enable_ssl_renewal_reminder', 1);
      }
    }

	/**
	 * Returns basic settings default values
	 * @return array
	 */
    public function basic_settings_default_v3(){
	    $admin_email = [get_option('admin_email')];

    	return [
		    //Acme version
		    //@value integer
		    'acme_version' => 2,

		    //Don't use wildcard SSL
		    //@value boolean
		    'use_wildcard' => false,

		    //We need real SSL
		    //@value boolean
		    'is_staging' => false,

		    //Admin email
		    //@value array
		    'admin_email' => $admin_email,

		    //Country code of the admin
		    //2 DIGIT ISO code
		    //@value string
		    'country_code' => '',

		    //State of the admin
		    //@value string
		    'state' => '',

		    //Organization of the admin
		    //@value string
		    'organization' => '',

		    //Home directory of this server.
		    //@value string
		    'homedir' => $this->factory->set_ssl_parent_directory(),

		    //Certificate directory
		    //@value string
		    'certificate_directory' => 'ssl-cert',

		    //How many days before the expiry date you want to renew the SSL?
		    //@value numeric
		    'days_before_expiry_to_renew_ssl' => 30,

		    //Is your web hosting control panel cPanel? For this case we set it to false
		    //@value boolean
		    'is_cpanel' => $this->is_cpanel ? $this->is_cpanel : $this->factory->is_cpanel(),

		    //IP of this server
		    'server_ip' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null,

		    //Are you using cloudflare or any other CDN?
		    //TRUE will ensure SSL expiry check will not depend on HTTP method
		    //@value boolean
		    'using_cdn' => true,

		    //Key size of the SSL
		    //@value integer
		    'key_size' => 2048

	    ];
    }

}