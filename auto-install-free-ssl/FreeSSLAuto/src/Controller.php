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
use AutoInstallFreeSSL\FreeSSLAuto\Acme\Factory;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\Factory as AdminFactory;
use Exception;
//Common actions, even if the control panel is not cPanel
class Controller {
    /**
     * Initiates the Controller class.
     */
    public function __construct() {
        if ( !defined( 'ABSPATH' ) ) {
            die( __( "Access denied", 'auto-install-free-ssl' ) );
        }
        $this->logger = new Logger();
    }

    /**
     * Make array of the domains pointing to the same document root of a domain.
     * Improved since 4.0.0
     * @param array $domains_array_raw
     * @param array $domains_to_exclude
     *
     * @return array
     */
    public function domainsArray( $domains_array_raw, $domains_to_exclude ) {
        $domains_array = [];
        foreach ( $domains_array_raw as $domain ) {
            //if ( !(aifs_can_manage_multi_domain() && \in_array( $domain, $domains_to_exclude, true )) || $this->sslRequiredForFirstTime() ) {
            if ( !in_array( $domain, $domains_to_exclude, true ) ) {
                //@since 4.0.0, we may need this for single domain mode too, even if $this->sslRequiredForFirstTime() is true.
                $domains_array[] = $domain;
            } else {
                /* translators: %s: A domain name, e.g., example.com */
                $this->logger->log( sprintf( __( "%s is in your exclusion list; skip it.", 'auto-install-free-ssl' ), $domain ) );
            }
        }
        //remove offline domains
        $domains_online = [];
        $ip_of_this_server = aifs_ip_of_this_server();
        //Improved since 3.6.0
        foreach ( $domains_array as $key => $domain ) {
            if ( false === strpos( $domain, '*.' ) && (bool) get_option( 'aifs_selected_verification_method_dns01' ) === false ) {
                //check if domain is online, only for non-wildcard domains
                //$socket = @fsockopen($domain, 80, $errno, $errstr, 30);
                //if ($socket) {
                if ( $this->isDomainOnlineFsockopen( $domain ) || $this->isDomainOnlineCurl( $domain ) ) {
                    //@since 4.0.0
                    //Domain is online
                    //if ( (bool) get_option('aifs_verify_ip_for_mail_dot_domain_alias_only') === false || (strpos( $domain, 'mail.' ) !== false && strpos( $domain, 'mail.' ) === 0) ) {
                    //if ($ip_of_this_server !== false && ((bool) get_option('aifs_verify_ip_for_mail_dot_domain_alias_only') === false || (strpos( $domain, 'mail.' ) !== false && strpos( $domain, 'mail.' ) === 0)) ) {
                    if ( $ip_of_this_server !== false && ((bool) get_option( 'aifs_verify_ip_for_all_domain_alias' ) || strpos( $domain, 'mail.' ) !== false && strpos( $domain, 'mail.' ) === 0) ) {
                        $dns = dns_get_record( $domain, DNS_A );
                        if ( $dns[0]['type'] == "A" && $dns[0]['ip'] == $ip_of_this_server ) {
                            $domains_online[] = $domain;
                        } else {
                            /*$this->logger->log( sprintf(
                              /* translators: %1$s: A IP address, e.g., 192.168.1.1, %2$s: A domain name, e.g., example.com, %3$s: Another IP address, e.g., 10.0.0.1 */
                            /* __( 'The IP of this server is %1$s. But %2$s points to different IP (%3$s). So, skipping it.', 'auto-install-free-ssl' ),
                                $ip_of_this_server,
                                $domain,
                                $dns[0]['ip']
                               ) );*/
                            $this->logger->log( sprintf(
                                'The IP of this server is %1$s. But %2$s points to different IP (%3$s). So, skipping it.',
                                $ip_of_this_server,
                                $domain,
                                $dns[0]['ip']
                            ) );
                            //since 3.6.1, Don't translate this soft error message.
                        }
                    } else {
                        $domains_online[] = $domain;
                    }
                } else {
                    //domain offline
                    /* translators: %s: A domain name, e.g., example.com */
                    //$this->logger->log( sprintf(__( "%s is offline. Skipping it.", 'auto-install-free-ssl' ), $domain) );
                    $this->logger->log( sprintf( "%s is offline. Skipping it.", $domain ) );
                    //since 3.6.1, Don't translate this soft error message.
                }
            } else {
                //Domain is wildcard and will be validated by DNS-01 challange, so, online check not required
                $domains_online[] = $domain;
            }
        }
        /**
         * Rearrange array if the website's domain is in $domains_online
         */
        $this_domain = aifs_get_domain( true );
        if ( in_array( $this_domain, $domains_online ) || in_array( "www." . $this_domain, $domains_online ) ) {
            $temp_domains_array = $domains_online;
            unset($domains_online);
            $group_1 = [];
            $group_2 = [];
            foreach ( $temp_domains_array as $domain ) {
                if ( $this_domain == $domain ) {
                    $group_1[] = $this_domain;
                } elseif ( "www." . $this_domain == $domain ) {
                    $group_1[] = "www." . $this_domain;
                } else {
                    $group_2[] = $domain;
                }
            }
            $domains_online = array_merge( $group_1, $group_2 );
        }
        return $domains_online;
    }

    /**
     * Checks if a domain is online using fsockopen.
     *
     * @param string $domain The domain name to check.
     * @param int $port The port to check (default is 80).
     * @param int $timeout The timeout in seconds (default is 10).
     * @return bool True if the domain is online, false otherwise.
     *
     * @since 4.0.0
     */
    public function isDomainOnlineFsockopen( $domain, $port = 80, $timeout = 10 ) {
        // Remove http:// or https:// from the domain if present
        $domain = preg_replace( '#^https?://#', '', $domain );
        // Try to open a socket connection to the domain on the specified port
        $connection = @fsockopen(
            $domain,
            $port,
            $errno,
            $errstr,
            $timeout
        );
        if ( $connection ) {
            // Connection successful
            fclose( $connection );
            return true;
        } else {
            // Connection failed
            //error_log("Connection failed: $errstr ($errno)");
            $this->logger->log( sprintf(
                '%1$s - Connection failed: %2$s (%3$s)',
                $domain,
                $errstr,
                $errno
            ) );
            return false;
        }
    }

    /**
     * Checks if a domain is online using curl.
     *
     * @param string $domain The domain name to check.
     * @param int $timeout The timeout in seconds (default is 10).
     * @return bool True if the domain is online, false otherwise.
     *
     * @since 4.0.0
     */
    public function isDomainOnlineCurl( $domain, $timeout = 10 ) {
        // Check if http:// or https:// is present in the domain
        if ( preg_match( '#^https?://#', $domain ) ) {
            $url = $domain;
        } else {
            $url = "http://" . $domain;
        }
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
        curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_NOBODY, true );
        // We don't need the body, just the response
        curl_exec( $ch );
        $httpcode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );
        //$this->logger->log( sprintf('%1$s - isDomainOnlineCurl() HTTP code: %2$s', $domain, $httpcode) );
        $this->logger->write_log( 'info', sprintf( '%1$s - isDomainOnlineCurl() HTTP code: %2$s', $domain, $httpcode ), [
            'event' => 'ping',
        ] );
        return $httpcode >= 200 && $httpcode < 400;
        // 2xx and 3xx status codes mean the domain is online
    }

    /**
     * Make array of the domains pointing to the same document root of a domain.
     * Without any filter (as is/raw)
     *
     * @param array $single_domain
     *
     * @return array
     * @since 3.2.10
     */
    public function domains_array_raw( $single_domain ) {
        $domains_array = [];
        $domains_array[] = $single_domain['domain'];
        if ( \strlen( $single_domain['serveralias'] ) > 1 ) {
            $domains = explode( ' ', $single_domain['serveralias'] );
            foreach ( $domains as $domain ) {
                if ( $single_domain['domain'] !== $domain ) {
                    $domains_array[] = $domain;
                }
            }
        }
        return $domains_array;
    }

    /**
     * Checks whether SSL certificate MUST required for the given domain.
     * Improved since 3.6.0
     * @return bool
     */
    public function sslRequiredForFirstTime() {
        //For free version always return true
        return true;
    }

    /**
     *
     *
     * Get the wildcard base domain.
     *
     * @param string $domain_as_is
     *
     * @return string
     */
    public function getWildcardBase( $domain_as_is ) {
        $registeredDomain = aifs_getRegisteredDomain( $domain_as_is );
        if ( null === $registeredDomain ) {
            return false;
        }
        //compute wildcard domain
        if ( \strlen( $domain_as_is ) > \strlen( $registeredDomain ) ) {
            //may be it's a subdomain
            $part = str_replace( $registeredDomain, '', $domain_as_is );
            $domain_elements_array = explode( '.', $part );
            if ( 2 === \count( $domain_elements_array ) && 'www' === $domain_elements_array[0] ) {
                //www.domain.com is not considered as wildcard domain
                return $domain_as_is;
            }
            //get the position of first . and ONLY replace the part left to it with *
            $pos = strpos( $domain_as_is, '.' );
            return '*' . substr( $domain_as_is, $pos );
        } elseif ( \strlen( $domain_as_is ) === \strlen( $registeredDomain ) ) {
            return $domain_as_is;
        }
    }

}
