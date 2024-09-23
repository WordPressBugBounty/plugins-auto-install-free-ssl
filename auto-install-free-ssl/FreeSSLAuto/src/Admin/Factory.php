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
namespace AutoInstallFreeSSL\FreeSSLAuto\Admin;

use Exception;
use InvalidArgumentException;
use AutoInstallFreeSSL\FreeSSLAuto\Acme\Factory as AcmeFactory;
use AutoInstallFreeSSL\FreeSSLAuto\Controller;
use AutoInstallFreeSSL\FreeSSLAuto\Logger;
use AutoInstallFreeSSL\FreeSSLAuto\Email;
use DateTime;
class Factory {
    public $logger;

    public function __construct() {
        if ( !defined( 'ABSPATH' ) ) {
            die( __( "Access denied", 'auto-install-free-ssl' ) );
        }
        $this->logger = new Logger();
    }

    /**
     *
     *
     * get sub-directories in the given directory.
     *
     * @param string $dirPath
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function getSubDirectories( $dirPath ) {
        if ( !is_dir( $dirPath ) ) {
            //throw new InvalidArgumentException("${dirPath} must be a directory");
            /* translators: %s: A directory path */
            //$this->logger->exception_sse_friendly(sprintf(__("%s must be a directory", 'auto-install-free-ssl'), $dirPath), __FILE__, __LINE__);
            $this->logger->exception_sse_friendly( sprintf( "%s must be a directory", $dirPath ), __FILE__, __LINE__ );
            //since 3.6.1, Don't translate exception message.
        }
        if ( '/' !== substr( $dirPath, \strlen( $dirPath ) - 1, 1 ) ) {
            $dirPath .= '/';
        }
        $dirs = [];
        $files = glob( $dirPath . '*', GLOB_MARK );
        foreach ( $files as $file ) {
            if ( is_dir( $file ) ) {
                $dirs[] = $file;
            }
        }
        return $dirs;
    }

    /**
     *
     *
     * get existing SSLs in the given directory.
     *
     * @param $dirPath
     *
     * @return array
     */
    public function getExistingSslList( $dirPath ) {
        $dirs = $this->getSubDirectories( $dirPath );
        $ssl_domains = [];
        foreach ( $dirs as $dir ) {
            $domain = basename( $dir );
            if ( '_account' !== $domain ) {
                //@todo add ->  && is_file($dir . DS . 'certificate.pem')
                $ssl_domains[] = $domain;
            }
        }
        return $ssl_domains;
    }

    /**
     *
     *
     * Check if SSL cert was generated for a given domain, by searching in the plugin's certificate directory
     *
     * @param $domain_as_is
     * @return boolean
     *
     * @since 2.1.0
     */
    public function is_ssl_issued_and_valid( $domain_as_is ) {
        if ( $this->is_ssl_installed_on_this_website() === true ) {
            return true;
        }
        if ( !function_exists( 'aifs_findRegisteredDomain' ) && !function_exists( 'aifs_getRegisteredDomain' ) && !function_exists( 'aifs_validDomainPart' ) ) {
            require_once AIFS_DIR . DS . 'vendor' . DS . 'usrflo' . DS . 'registered-domain-libs' . DS . 'PHP' . DS . 'effectiveTLDs.inc.php';
            require_once AIFS_DIR . DS . 'vendor' . DS . 'usrflo' . DS . 'registered-domain-libs' . DS . 'PHP' . DS . 'regDomain.inc.php';
        }
        $app_settings = aifs_get_app_settings();
        if ( (isset( $app_settings['cpanel_host'] ) || isset( $app_settings['all_domains'] )) && isset( $app_settings['homedir'] ) ) {
            $acmeFactory = new AcmeFactory($app_settings['homedir'] . '/' . $app_settings['certificate_directory'], $app_settings['acme_version'], $app_settings['is_staging']);
            //get the path of SSL files
            $certificates_directory = $acmeFactory->getCertificatesDir();
            if ( is_dir( $certificates_directory ) ) {
                if ( strpos( $domain_as_is, 'www.' ) === false || strpos( $domain_as_is, 'www.' ) != 0 ) {
                    //No www. found at beginning
                    $domain_with_www = 'www.' . $domain_as_is;
                    $domain = $domain_as_is;
                } elseif ( strpos( $domain_as_is, 'www.' ) == 0 ) {
                    // www. found at the beginning
                    $domain_with_www = $domain_as_is;
                    $domain = substr( $domain_as_is, 4 );
                }
                //Search # 1
                if ( is_dir( $certificates_directory . "/" . $domain ) ) {
                    if ( $this->is_cert_file_has_ssl_for( $domain_as_is, $certificates_directory . "/" . $domain ) ) {
                        return true;
                    }
                }
                //Search # 2
                if ( strpos( $domain_as_is, 'www.' ) == 0 ) {
                    // www. found at the beginning
                    $wildcard = "*." . substr( $domain_as_is, 4 );
                    if ( is_dir( $certificates_directory . "/" . $wildcard ) ) {
                        if ( $this->is_cert_file_has_ssl_for( $wildcard, $certificates_directory . "/" . $wildcard ) ) {
                            return true;
                        }
                    }
                }
                //Search # 3
                $controller = new Controller();
                //Try again with the wildcard version
                $wildcard_domain_1 = $controller->getWildcardBase( $domain_as_is );
                if ( is_dir( $certificates_directory . "/" . $wildcard_domain_1 ) ) {
                    if ( $this->is_cert_file_has_ssl_for( $wildcard_domain_1, $certificates_directory . "/" . $wildcard_domain_1 ) ) {
                        return true;
                    }
                }
                //Search # 4
                $wildcard_domain_2 = $controller->getWildcardBase( str_replace( "*.", "", $wildcard_domain_1 ) );
                if ( is_dir( $certificates_directory . "/" . $wildcard_domain_2 ) ) {
                    if ( $this->is_cert_file_has_ssl_for( $domain_as_is, $certificates_directory . "/" . $wildcard_domain_2 ) ) {
                        return true;
                    }
                }
                $wildcard_domain_3 = $controller->getWildcardBase( str_replace( "*.", "", $wildcard_domain_2 ) );
            }
        }
        return false;
    }

    /**
     *
     *
     * @param string $domain_as_is
     * @param string $cert_dir
     *
     * @return boolean
     *
     * @throws Exception
     * @since 2.1.0
     */
    private function is_cert_file_has_ssl_for( $domain_as_is, $cert_dir ) {
        $ssl_cert_file = $cert_dir . '/certificate.pem';
        if ( !file_exists( $ssl_cert_file ) ) {
            // We don't have a SSL certificate
            return false;
        } else {
            // We have a SSL certificate.
            $cert_array = openssl_x509_parse( openssl_x509_read( file_get_contents( $ssl_cert_file ) ) );
            //Get SAN array
            $subjectAltName = explode( ',', str_replace( 'DNS:', '', $cert_array['extensions']['subjectAltName'] ) );
            //remove space and cast as string
            $sansArrayFiltered = array_map( function ( $piece ) {
                return (string) trim( $piece );
            }, $subjectAltName );
            if ( in_array( $domain_as_is, $sansArrayFiltered, true ) ) {
                $now = new DateTime();
                $expiry = new DateTime('@' . $cert_array['validTo_time_t']);
                $interval = (int) $now->diff( $expiry )->format( '%R%a' );
                if ( $interval > 1 ) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    /**
     *
     *
     * Check if a valid SSL installed on this website
     * 
     * @return mixed
     */
    public function is_ssl_installed_on_this_website() {
        $expected_status_codes = [
            200,
            201,
            202,
            204
        ];
        $domain_site = aifs_get_domain( false );
        $test_1 = $this->connect_over_ssl( $domain_site );
        if ( $test_1['error_number'] != 0 || !in_array( $test_1['http_status_code'], $expected_status_codes ) ) {
            if ( strpos( $domain_site, 'www.' ) !== false && strpos( $domain_site, 'www.' ) == 0 ) {
                //If www. found at the beginning
                $domain_other_version = substr( $domain_site, 4 );
            } else {
                $domain_other_version = 'www.' . $domain_site;
            }
            $ssl_details['domain_site'] = array(
                'ssl_installed' => false,
                'url'           => $domain_site,
                'error_cause'   => $test_1['error_cause'],
            );
            $test_2 = $this->connect_over_ssl( $domain_other_version );
            $ssl_details['domain_other_version'] = array(
                'url'         => $domain_other_version,
                'error_cause' => $test_2['error_cause'],
            );
            //check other version
            if ( $test_2['error_number'] == 0 ) {
                if ( in_array( $test_2['http_status_code'], $expected_status_codes ) || $test_2['http_status_code'] == 301 ) {
                    $ssl_details['domain_other_version']['ssl_installed'] = true;
                } else {
                    // Unknown status code
                    $ssl_details['domain_other_version']['ssl_installed'] = false;
                }
            } else {
                //SSL NOT installed on $domain_site && $domain_other_version
                $ssl_details['domain_other_version']['ssl_installed'] = false;
            }
            return $ssl_details;
        } else {
            return true;
            //SSL installed on $domain_site
        }
    }

    /**
     *
     *
     * Connect with the given domain over HTTPS and return 
     * http status code and error details, if any
     * 
     * @param string $domain
     * @return array
     */
    private function connect_over_ssl( $domain ) {
        $handle = curl_init();
        curl_setopt( $handle, CURLOPT_URL, 'https://' . $domain );
        curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, true );
        curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, 2 );
        $response = curl_exec( $handle );
        return array(
            'error_number'     => curl_errno( $handle ),
            'error_cause'      => curl_error( $handle ),
            'http_status_code' => curl_getinfo( $handle, CURLINFO_HTTP_CODE ),
        );
    }

    /**
     * Returns the cPanel host
     * Improved since 3.5.1
     *
     * @param $hostname
     *
     * @param bool $purge_cache
     *
     * @return false|string
     */
    public function getcPanelHost( $hostname, $purge_cache = false ) {
        $cpanel_settings = get_option( 'cpanel_settings_auto_install_free_ssl' );
        if ( !$purge_cache && isset( $cpanel_settings['cpanel_host'] ) ) {
            return $cpanel_settings['cpanel_host'];
        }
        $cpanel_host = false;
        if ( !aifs_is_os_windows() ) {
            $hostname = str_replace( [
                'https://',
                'http://',
                'https://www.',
                'http://www.'
            ], '', $hostname );
            $possible_hosts = [
                "http://" . gethostbyname( 'localhost' ) . ':2083',
                "http://" . gethostbyname( 'localhost' ) . '/cpanel',
                "https://" . $hostname . ':2083',
                "http://" . $hostname . '/cpanel',
                "http://" . $hostname . ':2083',
                "https://" . $hostname . '/cpanel'
            ];
            foreach ( $possible_hosts as $curl_url ) {
                $handle = curl_init();
                curl_setopt( $handle, CURLOPT_URL, $curl_url );
                curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, false );
                curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
                curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $handle, CURLOPT_HEADER, true );
                curl_setopt( $handle, CURLOPT_TIMEOUT, 5 );
                // @since 3.2.3
                curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, true );
                $response = curl_exec( $handle );
                $error_number = curl_errno( $handle );
                //@since 3.6.1
                if ( $error_number ) {
                    $error_text = "cURL error {$error_number}: " . curl_error( $handle );
                    $error_text .= "\n\n File: " . __FILE__ . "\n Line number: " . __LINE__ . "\n\n";
                    $this->logger->write_log( 'error', $error_text, [
                        'event' => 'ping',
                    ] );
                }
                $effective_url = curl_getinfo( $handle, CURLINFO_EFFECTIVE_URL );
                $redirect_url = curl_getinfo( $handle, CURLINFO_REDIRECT_URL );
                $http_status_code = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
                curl_close( $handle );
                $checkURL = $this->checkcPanelInUrl( $effective_url );
                if ( ($http_status_code === 301 || $http_status_code === 200) && ($checkURL['endsWith2083Port'] || $checkURL['endsWithCpanel']) && strpos( $response, 'cPanel' ) !== false ) {
                    $hostname = $checkURL['string'];
                    if ( $checkURL['endsWith2083Port'] ) {
                        //if(strpos($response, 'login') !== false)
                        $hostname = str_replace( ['https://', 'http://'], '', $hostname );
                        $hostname = substr( $hostname, 0, -5 );
                    } else {
                        if ( $checkURL['endsWithCpanel'] ) {
                            //if(strpos($response, ':2083') !== false || strpos($response, ':2082') !== false)
                            $hostname = str_replace( ['https://', 'http://'], '', $hostname );
                            $hostname = substr( $hostname, 0, -7 );
                        }
                    }
                    if ( strpos( $hostname, 'www.' ) !== false && strpos( $hostname, 'www.' ) === 0 ) {
                        //If www. found at the beginning, remove it
                        $hostname = substr( $hostname, 4 );
                    }
                    $cpanel_host = $hostname;
                    break;
                    // Exit the loop if cPanel host is found
                }
            }
            //following if block ensures detection even if all the above methods fail
            if ( $cpanel_host === false ) {
                if ( !$this->is_parent_dir_restricted_by_open_basedir() && is_dir( '/usr/local/cpanel' ) || $this->port_exists( 2083 ) || $this->port_exists( 2082 ) ) {
                    if ( gethostname() !== false ) {
                        $cpanel_host = gethostname();
                        // Improved since 3.6.0
                    } else {
                        $hostname = str_replace( ['https://', 'http://'], '', $hostname );
                        if ( strpos( $hostname, 'www.' ) !== false && strpos( $hostname, 'www.' ) === 0 ) {
                            //If www. found at the beginning, remove it
                            $hostname = substr( $hostname, 4 );
                        }
                        $cpanel_host = $hostname;
                    }
                }
            }
        }
        if ( $cpanel_settings && is_array( $cpanel_settings ) ) {
            $previous_host = $cpanel_settings['cpanel_host'];
            $cpanel_settings['cpanel_host'] = $cpanel_host;
            update_option( 'cpanel_settings_auto_install_free_ssl', $cpanel_settings );
        } else {
            update_option( 'cpanel_settings_auto_install_free_ssl', [
                'cpanel_host' => $cpanel_host,
            ] );
        }
        return $cpanel_host;
    }

    /**
     * Check if '/cpanel' or ':2083' exists in the given URL
     * @param $string
     * @since 3.5.1
     * @return array
     */
    public function checkcPanelInUrl( $string ) {
        // Remove '/' if it exists at the end of the string
        if ( substr( $string, -1 ) === '/' ) {
            $string = rtrim( $string, '/' );
        }
        // Check if '/cpanel' or ':2083' exists at the end of the string
        $endsWithCpanel = substr( $string, -7 ) === '/cpanel';
        $endsWithPort = substr( $string, -5 ) === ':2083';
        return [
            'endsWithCpanel'   => $endsWithCpanel,
            'endsWith2083Port' => $endsWithPort,
            'string'           => $string,
        ];
    }

    /**
     * Returns the cPanel host
     * Improved since 3.5.1
     * @param $hostname
     * @param $is_reset_host
     * @param $strict_check
     *
     * @return false|string
     */
    public function getcPanelHost_v1( $hostname, $is_reset_host = false, $strict_check = false ) {
        $cpanel_settings = ( get_option( 'cpanel_settings_auto_install_free_ssl' ) ? get_option( 'cpanel_settings_auto_install_free_ssl' ) : add_option( 'cpanel_settings_auto_install_free_ssl' ) );
        if ( isset( $cpanel_settings['cpanel_host'] ) ) {
            return $cpanel_settings['cpanel_host'];
        }
        //@todo use gethostbyname('localhost') too
        $cpanel_host = false;
        if ( !aifs_is_os_windows() ) {
            $hostname = str_replace( ['https://', 'https://www.', 'http://www.'], 'http://', $hostname );
            $handle = curl_init();
            curl_setopt( $handle, CURLOPT_URL, $hostname . (( $strict_check ? '/cpanel' : ':2083' )) );
            curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, false );
            curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $handle, CURLOPT_HEADER, true );
            curl_setopt( $handle, CURLOPT_TIMEOUT, 5 );
            // @since 3.2.3
            //if ( $strict_check ) {
            curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, true );
            //}
            $response = curl_exec( $handle );
            $effective_url = curl_getinfo( $handle, CURLINFO_EFFECTIVE_URL );
            $redirect_url = curl_getinfo( $handle, CURLINFO_REDIRECT_URL );
            $http_status_code = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
            $error = curl_errno( $handle );
            curl_close( $handle );
            if ( $http_status_code !== 3 ) {
                if ( $is_reset_host === false && $http_status_code === 301 ) {
                    if ( isset( $redirect_url ) ) {
                        $response = $redirect_url;
                    }
                    $start = strpos( $response, 'https://' );
                    $end = strpos( $response, ':2083' );
                    $link = substr( $response, $start, $end - $start );
                    if ( strpos( $link, 'www.' ) !== false && strpos( $link, 'www.' ) === 0 ) {
                        //If www. found at the beginning, remove it
                        $link = substr( $link, 4 );
                    }
                    return $this->getcPanelHost_v1( $link );
                }
                if ( in_array( $http_status_code, [400] ) ) {
                    $link = str_replace( 'http://', 'https://', $hostname );
                    return $this->getcPanelHost_v1( $link );
                }
                if ( $http_status_code === 0 ) {
                    return $this->getcPanelHost_v1( $hostname );
                }
                $checkURL = $this->checkcPanelInUrl( $effective_url );
                //Improved since 3.5.1
                if ( ($http_status_code === 301 || $http_status_code === 200) && ($checkURL['endsWith2083Port'] || $checkURL['endsWithCpanel']) && strpos( $response, 'cPanel' ) !== false ) {
                    $hostname = $checkURL['string'];
                    if ( $checkURL['endsWith2083Port'] ) {
                        //if(strpos($response, 'login') !== false)
                        $hostname = str_replace( ['https://', 'http://'], '', $hostname );
                        $hostname = substr( $hostname, 0, -5 );
                    } else {
                        if ( $checkURL['endsWithCpanel'] ) {
                            //if(strpos($response, ':2083') !== false || strpos($response, ':2082') !== false)
                            $hostname = str_replace( ['https://', 'http://'], '', $hostname );
                            $hostname = substr( $hostname, 0, -7 );
                        }
                    }
                    if ( strpos( $hostname, 'www.' ) !== false && strpos( $hostname, 'www.' ) === 0 ) {
                        //If www. found at the beginning, remove it
                        $hostname = substr( $hostname, 4 );
                    }
                    $cpanel_host = $hostname;
                } else {
                    if ( !$this->is_parent_dir_restricted_by_open_basedir() && is_dir( '/usr/local/cpanel' ) || $this->port_exists( 2083 ) || $this->port_exists( 2082 ) ) {
                        //Improved since 3.5.1
                        $hostname = str_replace( ['https://', 'http://'], '', $hostname );
                        if ( strpos( $hostname, 'www.' ) !== false && strpos( $hostname, 'www.' ) === 0 ) {
                            //If www. found at the beginning, remove it
                            $hostname = substr( $hostname, 4 );
                        }
                        $cpanel_host = $hostname;
                    }
                }
            }
        }
        $cpanel_settings['cpanel_host'] = $cpanel_host;
        update_option( 'cpanel_settings_auto_install_free_ssl', $cpanel_settings );
        return $cpanel_host;
    }

    /**
     * Detects whether the control panel is cPanel
     * Improved since 3.5.1
     * @return bool
     */
    public function is_cpanel() {
        if ( aifs_is_os_windows() ) {
            return false;
        } else {
            if ( !$this->is_parent_dir_restricted_by_open_basedir() && is_dir( '/usr/local/cpanel' ) ) {
                return true;
            } else {
                if ( $this->port_exists( 2083 ) ) {
                    return true;
                } else {
                    if ( $this->port_exists( 2082 ) ) {
                        return true;
                    } else {
                        if ( $this->getcPanelHost( aifs_get_domain() ) !== false ) {
                            return true;
                        } else {
                            return false;
                        }
                    }
                }
            }
        }
    }

    /**
     * Check if the provided paths restricted by open_basedir
     *
     * @param array $parent_dir
     *
     * @return bool
     * @since 3.2.1
     */
    public function is_parent_dir_restricted_by_open_basedir( $parent_dir = [
        '/usr',
        '/usr/local',
        '/usr/',
        '/usr/local/'
    ] ) {
        $open_basedir = ini_get( "open_basedir" );
        if ( empty( $open_basedir ) ) {
            return false;
        }
        $open_basedir_array = explode( PATH_SEPARATOR, $open_basedir );
        foreach ( $parent_dir as $dir ) {
            if ( in_array( $dir, $open_basedir_array ) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if the control panel of this server is Plesk
     *
     * @return bool
     * @since 3.2.2
     */
    public function is_plesk() {
        if ( $this->is_cpanel() ) {
            return false;
        } else {
            if ( !$this->is_parent_dir_restricted_by_open_basedir() && is_dir( '/usr/local/psa' ) ) {
                return true;
            } else {
                if ( $this->port_exists( 8443 ) ) {
                    return true;
                } else {
                    if ( $this->port_exists( 8880 ) ) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
        }
    }

    /**
     * Check if the web hosting control panel is unknown
     *
     * @return bool
     * @since 3.2.2
     */
    public function control_panel_is_unknown() {
        if ( !$this->is_cpanel() && !$this->is_plesk() ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if the provided port exists on this server or provided hostname_or_ip
     * @param int $port
     * @param null $hostname_or_ip
     *
     * @return bool
     * @since 3.2.2
     */
    public function port_exists( $port, $hostname_or_ip = null ) {
        $ip = ( is_null( $hostname_or_ip ) ? gethostbyname( 'localhost' ) : $hostname_or_ip );
        $fp = @fsockopen(
            $ip,
            $port,
            $error_code,
            $error_message,
            6
        );
        if ( is_resource( $fp ) ) {
            fclose( $fp );
            return true;
        } else {
            return false;
        }
    }

    /**
     * Compute the SSL cert directory, which is writable
     * AIFS_DIR
     * @return mixed|string
     */
    public function set_ssl_parent_directory() {
        $pos_public_html = strpos( AIFS_DIR, DS . 'public_html' );
        if ( isset( $_SERVER['HOME'] ) && is_writable( $_SERVER['HOME'] ) ) {
            return $_SERVER['HOME'];
        } elseif ( $pos_public_html !== false && is_writable( substr( AIFS_DIR, 0, $pos_public_html ) ) ) {
            return substr( AIFS_DIR, 0, $pos_public_html );
        }
        if ( !is_dir( AIFS_UPLOAD_DIR ) ) {
            @mkdir( AIFS_UPLOAD_DIR, 0700, true );
        }
        if ( !is_dir( AIFS_UPLOAD_DIR ) ) {
            //throw new \RuntimeException("Can't create directory '".AIFS_UPLOAD_DIR."'. Please manually create it, set permission 0755 and try again.");
            /* translators: %1$s: A directory path, e.g., /home/user/public_html, %2$s: Directory permissions number, e.g., 0700 */
            //$this->logger->exception_sse_friendly(sprintf(__('Can not create the directory %1$s. Please manually create it, set permission %2$s, and try again.', 'auto-install-free-ssl'), AIFS_UPLOAD_DIR, '0700'), __FILE__, __LINE__);
            $this->logger->exception_sse_friendly( sprintf( 'Can not create the directory %1$s. Please manually create it, set permission %2$s, and try again.', AIFS_UPLOAD_DIR, '0700' ), __FILE__, __LINE__ );
            //since 3.6.1, Don't translate exception message.
        }
        if ( is_writable( AIFS_UPLOAD_DIR ) ) {
            $this->create_security_files( AIFS_UPLOAD_DIR );
            return AIFS_UPLOAD_DIR;
        } elseif ( is_writable( $this->document_root_wp() ) ) {
            $document_root = $this->document_root_wp();
            $pos_public_html = strpos( $document_root, DS . 'public_html' );
            if ( $pos_public_html !== false && is_writable( substr( $document_root, 0, $pos_public_html ) ) ) {
                return substr( $document_root, 0, $pos_public_html );
            } else {
                return $document_root;
            }
        } else {
            if ( !is_writable( AIFS_UPLOAD_DIR ) ) {
                //throw new \RuntimeException("The directory '".AIFS_UPLOAD_DIR."' is not writable. Please manually set permission 0755 or 0777 to this directory and try again.");
                /* translators: %s: A directory path, e.g., /home/user/public_html */
                //$this->logger->exception_sse_friendly(sprintf(__("The directory '%s' is not writable. Please manually set the permission 0755 or 0777 to this directory and try again.", 'auto-install-free-ssl'), AIFS_UPLOAD_DIR), __FILE__, __LINE__);
                $this->logger->exception_sse_friendly( sprintf( "The directory '%s' is not writable. Please manually set the permission 0755 or 0777 to this directory and try again.", AIFS_UPLOAD_DIR ), __FILE__, __LINE__ );
                //since 3.6.1, Don't translate exception message.
            }
        }
        return false;
    }

    /**
     * Returns the document root of this domain, i.e., WP installation.
     *
     * @return false|string
     * @since 3.2.3
     */
    public function document_root_wp() {
        //return substr(ABSPATH, 0, strlen(ABSPATH)-1); //remove / from the end of ABSPATH
        if ( !function_exists( 'get_home_path' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $home_path = get_home_path();
        $last_character = substr( $home_path, strlen( $home_path ) - 1 );
        if ( $last_character == "/" || $last_character == DS ) {
            return substr( $home_path, 0, strlen( $home_path ) - 1 );
            //remove last character
        } else {
            return $home_path;
        }
    }

    /**
     * Create .htaccess or web.config to prevent directory browsing (as per the server software)
     * @param string $dir_path
     * @since 3.2.0
     */
    public function create_security_files( $dir_path ) {
        $data = "Order deny,allow\nDeny from all";
        $this->create_htaccess_file( $dir_path, $data );
        $data = '<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <directoryBrowse enabled="false" />
  </system.webServer>  
</configuration>';
        $this->create_web_dot_config_file( $dir_path, $data );
    }

    /**
     * Create .htaccess file in dir_path with the provided data
     * @param $dir_path
     * @param $data
     * @since 3.2.0
     */
    public function create_htaccess_file( $dir_path, $data ) {
        $file = ".htaccess";
        if ( !file_exists( $dir_path . DS . $file ) && (aifs_server_software() == "apache" || aifs_server_software() === false) ) {
            if ( !file_put_contents( $dir_path . DS . $file, $data ) ) {
                //echo "<pre>$data</pre>";
                //throw new \RuntimeException("Can't create .htaccess file in the directory '".$dir_path."'. Please manually create it, and paste the above code in it.");
                /*$this->logger->exception_sse_friendly(sprintf(
                		/* translators: %1$s: A file name; %2$s: A directory path; %3$s: Code that will be written in the file */
                /*__('Can\'t create the \'%1$s\' file in the directory \'%2$s\'. Please manually create it, and paste this code into it: %3$s', 'auto-install-free-ssl'),
                			$file,
                			$dir_path,
                			"<pre>" . htmlspecialchars($data) . "</pre>"
                		), __FILE__, __LINE__);*/
                $this->logger->exception_sse_friendly( sprintf(
                    'Can\'t create the \'%1$s\' file in the directory \'%2$s\'. Please manually create it, and paste this code into it: %3$s',
                    $file,
                    $dir_path,
                    "<pre>" . htmlspecialchars( $data ) . "</pre>"
                ), __FILE__, __LINE__ );
                //since 3.6.1, Don't translate exception message.
            }
        }
    }

    /**
     * Create web.config file in dir_path with the provided data
     * @param $dir_path
     * @param $data
     * @since 3.2.0
     */
    public function create_web_dot_config_file( $dir_path, $data ) {
        $file = "web.config";
        if ( !file_exists( $dir_path . DS . $file ) && (aifs_server_software() == "ms-iis" || aifs_server_software() === false) ) {
            if ( !file_put_contents( $dir_path . DS . $file, $data ) ) {
                //echo "<pre>$data</pre>";
                //throw new \RuntimeException("Can't create .htaccess file in the directory '".$dir_path."'. Please manually create it, and paste the above code in it.");
                /*$this->logger->exception_sse_friendly(sprintf(
                		/* translators: %1$s: A file name; %2$s: A directory path; %3$s: Code that will be written in the file */
                /*__('Can\'t create the \'%1$s\' file in the directory \'%2$s\'. Please manually create it, and paste this code into it: %3$s', 'auto-install-free-ssl'),
                			$file,
                			$dir_path,
                			"<pre>" . htmlspecialchars($data, ENT_XML1) . "</pre>"
                		), __FILE__, __LINE__);*/
                $this->logger->exception_sse_friendly( sprintf(
                    'Can\'t create the \'%1$s\' file in the directory \'%2$s\'. Please manually create it, and paste this code into it: %3$s',
                    $file,
                    $dir_path,
                    "<pre>" . htmlspecialchars( $data, ENT_XML1 ) . "</pre>"
                ), __FILE__, __LINE__ );
                //since 3.6.1, Don't translate exception message.
            }
        }
    }

    /**
     * @param string $file_path
     * @param null $file_name
     * @param bool $delete_file
     */
    public function download_file( $file_path, $file_name = null, $delete_file = false ) {
        $file_name = ( is_null( $file_name ) ? basename( $file_path ) : $file_name );
        //if file name was provided, take it
        if ( file_exists( $file_path ) ) {
            header( 'Content-Description: File Transfer' );
            header( 'Content-Type: application/octet-stream' );
            header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );
            header( 'Expires: 0' );
            header( 'Cache-Control: must-revalidate' );
            header( 'Pragma: public' );
            header( 'Content-Length: ' . filesize( $file_path ) );
            readfile( $file_path );
            if ( $delete_file ) {
                unlink( $file_path );
            }
            exit;
        }
    }

    /**
     * Check if $url is available online
     * @param $url
     * @return bool
     */
    public function isSiteAvailible( $url ) {
        /*$dns_record = dns_get_record($url, DNS_A);
        
        		echo "<pre>";
        			print_r($dns_record);
        		echo "</pre>";
        		echo "<br />---------------------------------<br />";
        		echo "IP: " . gethostbyname($url);
        		echo "<br />---------------------------------<br />";*/
        $url = "http://" . $url;
        // Check, if a valid url is provided
        if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }
        // Initialize cURL
        $curlInit = curl_init( $url );
        // Set options
        curl_setopt( $curlInit, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $curlInit, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $curlInit, CURLOPT_CONNECTTIMEOUT, 10 );
        curl_setopt( $curlInit, CURLOPT_HEADER, true );
        curl_setopt( $curlInit, CURLOPT_NOBODY, true );
        curl_setopt( $curlInit, CURLOPT_RETURNTRANSFER, true );
        // Get response
        $response = curl_exec( $curlInit );
        $error_number = curl_errno( $curlInit );
        //@since 3.6.1
        if ( $error_number ) {
            $error_text = "cURL error {$error_number}: " . curl_error( $curlInit );
            $error_text .= "\n\n File: " . __FILE__ . "\n Line number: " . __LINE__ . "\n\n";
            $this->logger->write_log( 'error', $error_text, [
                'event' => 'ping',
            ] );
        }
        //$http_code = curl_getinfo($curlInit, CURLINFO_HTTP_CODE);
        // Close a cURL session
        curl_close( $curlInit );
        /*echo "<pre>";
        			print_r($response);
        		echo "</pre>";
        		echo "HTTP code: " . $http_code . "<br />";*/
        return (bool) $response;
    }

    /**
     * Return domain alias (WWW) availability text
     *
     * @return string
     */
    public function domain_alias_availability_text() {
        $domain = aifs_get_domain( true );
        $domain_online = [];
        $offline_domain = "";
        if ( $this->isSiteAvailible( $domain ) ) {
            $domain_online[] = $domain;
        } else {
            $offline_domain = $domain;
        }
        if ( $this->isSiteAvailible( "www." . $domain ) ) {
            $domain_online[] = "www." . $domain;
        } else {
            $offline_domain = "www." . $domain;
        }
        $html = "<p style='font-size: 1.2em; color: #000000;'><strong>" . __( "We'll issue Free SSL for", 'auto-install-free-ssl' ) . ":</strong> ";
        $html .= implode( ' and ', $domain_online ) . "</p>";
        if ( count( $domain_online ) < 2 ) {
            $link = "https://www.whatsmydns.net/#A/" . $offline_domain;
            /* translators: %s: A domain name, e.g., example.com */
            $html .= "<p style='font-size: 1em; color: red;'>" . sprintf( __( "%s is offline.", 'auto-install-free-ssl' ), "<em>{$offline_domain}</em>" );
            $html .= " " . __( "Please point it to this hosting if you need SSL for it too.", 'auto-install-free-ssl' ) . "<br /><br />";
            /* translators: %s: HTML code to create a hyperlink with the text 'click here' */
            $html .= sprintf( __( "After you do so, wait to propagate the DNS record. Please %sclick here%s to check the DNS record propagation status. Proceed if you see green tick marks for almost all locations in the list and map.", 'auto-install-free-ssl' ), "<a href='{$link}' target='_blank'>", "</a>" ) . "</p>";
        }
        return $html;
    }

    /**
     * Remove parameters from a given URL
     *
     * @param string $url
     * @param array $exclude_parameters
     *
     * @return mixed|string
     * @since 2.2.2
     */
    public function aifs_remove_parameters_from_url( $url, $exclude_parameters ) {
        $url_parts = explode( '?', $url );
        if ( empty( $url_parts[1] ) ) {
            return $url;
        }
        $query_string = $url_parts[1];
        $query_parameters = explode( '&', $query_string );
        $query_parameters_filtered = [];
        foreach ( $query_parameters as $parameter ) {
            if ( !empty( $parameter ) ) {
                $parameter_key_value = explode( '=', $parameter );
                if ( !in_array( $parameter_key_value[0], $exclude_parameters ) ) {
                    $query_parameters_filtered[] = $parameter;
                }
            }
        }
        if ( count( $query_parameters_filtered ) > 0 ) {
            return $url_parts[0] . '?' . implode( '&', $query_parameters_filtered );
        } else {
            return $url_parts[0];
        }
    }

    /**
     * Upgrade URL for aifs_is_existing_user() or user using free premium six months license
     * @return string
     * @since 3.2.14
     */
    /*
    	 * public function upgrade_url_for_existing_users_v00(){
    		if(aifs_is_free_version() && aifssl_fs()->get_user()->id != 5953244){
    			$link = aifssl_fs()->get_upgrade_url();
    		}
    		else {
    
    			$link             = "https://checkout.freemius.com/mode/dialog/plugin/10204/plan/17218/?coupon=ThankYou";
    			$admin_email      = get_option( 'admin_email' );
    			$admin_first_name = aifs_admin_first_name();
    			$admin_last_name  = aifs_admin_last_name();
    
    			if ( strpos( $admin_email, "secureserver.net" ) === false && strpos( $admin_email, "example.com" ) === false && strpos( $admin_email, "example.org" ) === false ) {
    				$link .= "&user_email=" . $admin_email;
    			}
    
    			if ( strlen( $admin_first_name ) > 0 ) {
    				$link .= "&user_firstname=" . ucfirst( $admin_first_name );
    			}
    
    			if ( strlen( $admin_last_name ) > 0 ) {
    				$link .= "&user_lastname=" . ucfirst( $admin_last_name );
    			}
    
    			$link .= "&hide_license_key=true&title=Auto-Install%20Free%20SSL&subtitle=Continue%20using%20our%20premium%20features%20after%20Dec%2031,%202022";
    		}
    
    		return $link;
    	}*/
    /**
     * Upgrade URL for aifs_is_existing_user() or user who used free premium six months license.
     * Refactored since 3.3.2
     * @return string
     * @since 3.2.14
     */
    public function upgrade_url_for_existing_users() {
        if ( aifs_is_existing_user() ) {
            $coupon_code = ( time() < strtotime( "February 1, 2023" ) ? "ThankYou" : "ThankYou20" );
        } else {
            $coupon_code = false;
        }
        return $this->upgrade_url( $coupon_code );
    }

    /**
     * Get the Upgrade URL and customize it, if required
     * @param string|bool $coupon_code
     * @param string|bool $query_string
     * @return string
     * @since 3.3.2
     */
    public function upgrade_url( $coupon_code = false, $query_string = false ) {
        if ( aifssl_fs()->get_user() !== false && aifssl_fs()->get_user()->id == 5953244 ) {
            $link = "https://checkout.freemius.com/mode/dialog/plugin/10204/plan/17218/?title=Auto-Install%20Free%20SSL&subtitle=Automatically%20renews%20and%20installs%20SSL%20cert%20in%20your%20sleep!";
            $admin_email = get_option( 'admin_email' );
            $admin_first_name = aifs_admin_first_name();
            $admin_last_name = aifs_admin_last_name();
            if ( aifs_is_free_version() ) {
                if ( strpos( $admin_email, "secureserver.net" ) === false && strpos( $admin_email, "example.com" ) === false && strpos( $admin_email, "example.org" ) === false ) {
                    $link .= "&user_email=" . $admin_email;
                }
                if ( strlen( $admin_first_name ) > 0 ) {
                    $link .= "&user_firstname=" . ucfirst( $admin_first_name );
                }
                if ( strlen( $admin_last_name ) > 0 ) {
                    $link .= "&user_lastname=" . ucfirst( $admin_last_name );
                }
            } else {
                $license = aifssl_fs()->_get_license();
                if ( is_object( $license ) ) {
                    $link .= "&license_key=" . $license->secret_key;
                }
            }
            $link .= "&hide_license_key=true";
        } else {
            $link = aifssl_fs()->get_upgrade_url();
        }
        if ( $coupon_code ) {
            $link .= "&coupon=" . $coupon_code;
        }
        if ( $query_string ) {
            $link .= "&" . $query_string;
        }
        return $link;
    }

    /**
     * Get the SSL file's path for this website.
     * Renamed and improved since 4.0.0
     * @return false|string
     */
    public function this_domain_get_ssl_file_path() {
        $app_settings = aifs_get_app_settings();
        $domain = aifs_get_domain( true );
        $serveralias = 'www.' . $domain;
        //initialize the Acme Factory class
        $acmeFactory = new AcmeFactory($app_settings['homedir'] . '/' . $app_settings['certificate_directory'], $app_settings['acme_version'], $app_settings['is_staging']);
        $certificate = false;
        $possible_domains = [];
        //if(aifs_is_free_version() || !aifs_use_wildcard()){ // include these even if wildcard
        $possible_domains[] = $domain;
        $possible_domains[] = $serveralias;
        //}
        if ( $acmeFactory->getConfirmedSslDir( $possible_domains ) ) {
            $certificate = $acmeFactory->getConfirmedSslDir( $possible_domains ) . 'certificate.pem';
        }
        return $certificate;
    }

    /**
     * Check if this plugin has generated an SSL certificate
     * @return bool
     * @since 3.4.0
     */
    public function is_ssl_generated() {
        $cert_array = $this->get_generated_ssl_details();
        if ( $cert_array ) {
            return isset( $cert_array['validFrom_time_t'] ) && !empty( $cert_array['validFrom_time_t'] ) && isset( $cert_array['validTo_time_t'] ) && !empty( $cert_array['validTo_time_t'] );
        }
        return false;
    }

    /**
     * Get the installed SSL certificate details of any website
     * @param $domain
     * @return array|false
     * @since 3.4.0
     */
    public function get_ssl_details( $domain ) {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $domain );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_VERBOSE, true );
        curl_setopt( $ch, CURLOPT_CERTINFO, true );
        curl_setopt( $ch, CURLOPT_HEADER, true );
        $result = curl_exec( $ch );
        $error_number = curl_errno( $ch );
        //@since 3.6.1
        if ( $error_number ) {
            $error_text = "cURL error {$error_number}: " . curl_error( $ch );
            $error_text .= "\n\n File: " . __FILE__ . "\n Line number: " . __LINE__ . "\n\n";
            $this->logger->write_log( 'error', $error_text, [
                'event' => 'ping',
            ] );
        }
        $info = curl_getinfo( $ch );
        curl_close( $ch );
        if ( is_array( $info['certinfo'] ) && count( $info['certinfo'] ) > 0 ) {
            return $info['certinfo'];
        }
        return false;
    }

    /**
     * Get the installed SSL certificate details THIS website
     * @return array|false
     * @since 3.4.0
     */
    public function get_installed_ssl_details() {
        //$ssl_details = $this->get_ssl_details(get_site_url());
        $ssl_details = $this->get_ssl_details( "https://" . aifs_get_domain( false ) );
        $domain = aifs_get_domain();
        if ( $ssl_details !== false && is_array( $ssl_details ) ) {
            return array_reduce( $ssl_details, function ( $v, $w ) use($domain) {
                //return $v ? $v : ((strpos($w['Subject'], $domain) !== false) ? $w : false);
                if ( $v ) {
                    return $v;
                } else {
                    if ( strpos( $w['Subject'], $domain ) !== false ) {
                        return $w;
                    } else {
                        foreach ( $w as $key => $value ) {
                            if ( strpos( $key, "Subject Alternative Name" ) !== false && strpos( $value, $domain ) !== false ) {
                                return $w;
                            }
                        }
                    }
                }
                return false;
            } );
        }
        return false;
    }

    /**
     * Get the details of the SSL certificate that is generated by this plugin
     * @return array|false
     * @since 3.4.0
     */
    public function get_generated_ssl_details() {
        $certificate = $this->this_domain_get_ssl_file_path();
        if ( $certificate ) {
            $cert_array = openssl_x509_parse( openssl_x509_read( file_get_contents( $certificate ) ) );
            if ( is_array( $cert_array ) ) {
                return $cert_array;
            }
        }
        return false;
    }

    /**
     * Detect if the website is using Cloudflare
     * @return bool
     * @since 3.4.0
     */
    public function is_using_cloudflare() {
        //if((isset($_SERVER['HTTP_CDN_LOOP']) && strtolower($_SERVER['HTTP_CDN_LOOP']) == "cloudflare") || (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && $_SERVER['HTTP_CF_CONNECTING_IP'] !== $_SERVER['REMOTE_ADDR']) || isset($_SERVER['HTTP_CF_IPCOUNTRY']) || isset($_SERVER['HTTP_CF_RAY']) || isset($_SERVER['HTTP_CF_VISITOR'])){
        if ( isset( $_SERVER['HTTP_CDN_LOOP'] ) && strtolower( $_SERVER['HTTP_CDN_LOOP'] ) == "cloudflare" || isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) || isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) || isset( $_SERVER['HTTP_CF_RAY'] ) || isset( $_SERVER['HTTP_CF_VISITOR'] ) ) {
            return true;
        }
        //$ssl_details = $this->get_ssl_details(get_site_url());
        $ssl_details = $this->get_ssl_details( "https://" . aifs_get_domain( false ) );
        if ( $ssl_details !== false && is_array( $ssl_details ) ) {
            foreach ( $ssl_details as $ssl ) {
                if ( strpos( strtolower( $ssl['Subject'] ), "cloudflare" ) !== false ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Detect if the generated SSL, if any, installed
     * @return bool
     * @since 3.4.0
     */
    public function is_generated_ssl_installed() {
        if ( get_option( 'aifs_number_of_ssl_generated' ) ) {
            // or get_option('aifs_number_of_ssl_generated') >= 1
            $using_cloudflare = $this->is_using_cloudflare();
            $generated_ssl = $this->get_generated_ssl_details();
            $installed_ssl = $this->get_installed_ssl_details();
            //Assuming User will install the generated SSL in 2 days (if Cloudflare)
            if ( $using_cloudflare && time() > $generated_ssl['validFrom_time_t'] + 2 * 24 * 60 * 60 || !$using_cloudflare && strcasecmp( $generated_ssl['serialNumberHex'], $installed_ssl['Serial Number'] ) === 0 ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the given API is accessible
     * @param $url
     * @return bool
     * @since 3.4.0
     */
    public function is_api_accessible( $url ) {
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_exec( $ch );
        $error_number = curl_errno( $ch );
        //@since 3.6.1
        if ( $error_number ) {
            $error_text = "cURL error {$error_number}: " . curl_error( $ch );
            $error_text .= "\n\n File: " . __FILE__ . "\n Line number: " . __LINE__ . "\n\n";
            $this->logger->write_log( 'error', $error_text, [
                'event' => 'ping',
            ] );
        }
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );
        if ( $http_code == 403 ) {
            return false;
        } else {
            return true;
        }
    }

    public function get_ssl_details_for_renewal_reminder() {
        $ssl_details = false;
        /*if($this->is_using_cloudflare()){
        			//get generated SSL details and return start date, expiry date and issuer
        			$generated_ssl = $this->get_generated_ssl_details();
        			if($generated_ssl){
        
        			}
        		}
        		else{
        			//get INSTALLED SSL details and return start date, expiry date and issuer
        			//strtotime($installed_ssl['Start date'])
        			$installed_ssl = $this->get_installed_ssl_details();
        			if($installed_ssl){
        				//$ssl_details['']
        			}
        		}*/
        if ( !$this->is_using_cloudflare() ) {
            $installed_ssl = $this->get_installed_ssl_details();
            if ( $installed_ssl ) {
                //$ssl_details['']
                return $ssl_details;
            }
        }
        //If not Cloudflare and no SSL installed, get generated SSL, if any
        $generated_ssl = $this->get_generated_ssl_details();
        if ( $generated_ssl ) {
            return $ssl_details;
        }
        return false;
    }

    /**
     * Add 'aifs_display_review' option if the conditions are true
     * @since 3.4.2
     */
    public function add_display_review() {
        if ( !get_option( 'aifs_number_of_ssl_generated' ) && $this->is_ssl_generated() ) {
            add_option( 'aifs_number_of_ssl_generated', 1 );
            // since 3.4.0
        }
        if ( !get_option( 'aifs_is_generated_ssl_installed' ) && $this->is_generated_ssl_installed() ) {
            // or $number_of_ssl_generated >= 1
            add_option( 'aifs_is_generated_ssl_installed', 1 );
        }
        if ( strlen( get_option( 'aifs_display_review' ) ) === 0 && get_option( 'aifs_is_generated_ssl_installed' ) ) {
            // Logic refactored since 3.4.0
            $display_review = false;
            if ( aifs_is_free_version() ) {
                //Display review if a valid SSL installed -> this may slowdown a bit
                //$display_review = $this->factory->is_ssl_installed_on_this_website() === true;
                $display_review = true;
            } else {
            }
            if ( $display_review ) {
                add_option( 'aifs_display_review', 1 );
            }
        }
    }

    /**
     * Get discount offer details
     * @param bool $comparison_table
     *
     * @return array
     * @since 3.6.8
     */
    public function get_offer_details( $comparison_table = false ) {
        $offer_end_time = get_option( 'aifs_comparison_table_promo_start_time' ) + AIFS_COUNTDOWN_DURATION;
        if ( time() > strtotime( "June 1, 2024" ) && time() < strtotime( "July 3, 2024" ) ) {
            $offer_name = "<strong>Summer Kickoff Sale</strong><br /><br />";
            if ( $comparison_table ) {
                if ( $this->is_cpanel() ) {
                    $coupon_code = "20SUMMER";
                    //id: 50301
                } else {
                    $coupon_code = "20SUMMERTIME";
                    //id: 50302
                }
            } else {
                if ( $this->is_cpanel() ) {
                    $coupon_code = "SUMMER20";
                    //id: 50303
                } else {
                    $coupon_code = "SUMMER";
                    //id: 50304
                }
            }
            $discount_percentage = __( "20%", 'auto-install-free-ssl' );
        } else {
            $offer_name = "";
            if ( $comparison_table ) {
                $coupon_code = "20AutoInstall";
            } else {
                $coupon_code = "AutoInstall20";
            }
            $discount_percentage = __( "20%", 'auto-install-free-ssl' );
        }
        return array(
            'offer_name'          => $offer_name,
            'offer_end_time'      => $offer_end_time,
            'coupon_code'         => $coupon_code,
            'discount_percentage' => $discount_percentage,
        );
    }

    /**
     * Check if the provided string is an IP address
     * @param $domain
     *
     * @return bool
     * @since 4.1.0
     */
    public function is_ip_address( $domain ) {
        $domain = trim( $domain );
        // Trim any whitespace from the input
        if ( filter_var( $domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            return true;
        } elseif ( filter_var( $domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            return true;
        }
        return false;
    }

    /**
     * Check if the provided string is a localhost
     * @param $domain
     *
     * @return bool
     * @since 4.1.0
     */
    public function is_localhost( $domain ) {
        $domain = trim( $domain );
        // Trim any whitespace from the input
        $localhost = [
            'localhost',
            '127.0.0.1',
            '::1',
            '0000:0000:0000:0000:0000:0000:0000:0001',
            '0:0:0:0:0:0:0:1'
        ];
        if ( in_array( $domain, $localhost ) ) {
            return true;
        }
        if ( filter_var( $domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            // Check if the IP address starts with '127.'
            if ( strpos( $domain, '127.' ) !== false && strpos( $domain, '127.' ) === 0 ) {
                return true;
            }
        }
        // try to connect online -> but it won't work. Even localhost IP can be connected.
        return false;
    }

}
