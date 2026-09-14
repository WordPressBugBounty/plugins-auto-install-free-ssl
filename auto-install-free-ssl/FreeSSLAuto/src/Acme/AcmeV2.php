<?php

/**
 * @package Auto-Install Free SSL
 * This package is a WordPress Plugin. It issues and installs free SSL certificates in cPanel shared hosting with complete automation.
 *
 * @author Free SSL Dot Tech <support@freessl.tech>
 * @copyright  Copyright (C) 2019-2024, Anindya Sundar Mandal
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
namespace AutoInstallFreeSSL\FreeSSLAuto\Acme;

use AutoInstallFreeSSL\FreeSSLAuto\Premium\DnsApi\DnsApi;
use AutoInstallFreeSSL\FreeSSLAuto\Logger;
use AutoInstallFreeSSL\FreeSSLAuto\Admin\Factory as AdminFactory;
class AcmeV2 {
    public $le_live = AIFS_LE_ACME_V2_LIVE;

    //Let's Encrypt live
    public $le_staging = AIFS_LE_ACME_V2_STAGING;

    //Let's Encrypt staging
    public $ca_api_url;

    public $contact = [];

    public $is_staging;

    public $certificatesBaseDir;

    private $dns_provider = [];

    private $cPanel = [];

    private $server_ip;

    private $challenge;

    private $webRootDir;

    private $logger;

    private $client;

    private $accountKeyPath;

    private $kid;

    private $factory;

    private $adminFactory;

    /** @var bool True after ACME account registration succeeds and AdminFactory is available. */
    private $account_ready = false;

    private $accountKeyDetails;

    public $key_size;

    public $certificatesDir;

    /**
     * Initiates the Let's Encrypt main class.
     *
     * @param string      $certificatesBaseDir
     * @param array       $contact
     * @param bool        $is_staging
     * @param array       $dns_provider
     * @param int         $key_size
     * @param array       $cPanel
     * @param string      $server_ip
     * @param string|null $challenge_type Optional ACME challenge type (http-01|dns-01). When null, reads sanitized $_POST['aifs_challenge_type'] at the edge for free-version redirects only.
     */
    public function __construct(
        $certificatesBaseDir,
        $contact,
        $is_staging,
        $dns_provider,
        $key_size,
        $cPanel,
        $server_ip,
        $challenge_type = null
    ) {
        if ( !defined( 'ABSPATH' ) ) {
            die( "Access denied" );
        }
        $this->is_staging = $is_staging;
        //choose the appropriate ACME API endpoint
        $this->ca_api_url = ( $this->is_staging ? $this->le_staging : $this->le_live );
        $this->contact = $contact;
        $this->logger = new Logger();
        $this->client = new Client($this->ca_api_url);
        $this->key_size = $key_size;
        $factory = new Factory($certificatesBaseDir, 2, $this->is_staging);
        $this->factory = $factory;
        $this->certificatesDir = $factory->getCertificatesDir();
        $this->accountKeyPath = $this->certificatesDir . AIFS_DS . '_account' . AIFS_DS . 'private.pem';
        $this->kid = ( is_file( \dirname( $this->accountKeyPath ) . AIFS_DS . 'kid.txt' ) ? file_get_contents( \dirname( $this->accountKeyPath ) . AIFS_DS . 'kid.txt' ) : '' );
        $this->dns_provider = $dns_provider;
        $this->cPanel = $cPanel;
        $this->server_ip = $server_ip;
        //Register new ACME Account, if not registered already
        $return_array = $this->registerNewAcmeAccount();
        if ( !$return_array['proceed'] ) {
            if ( aifs_is_free_version() ) {
                //If free version, save flush message and redirect.
                aifs_add_flash_notice( '<span style="font-size: larger; color: red;">' . $return_array['error_text'] . '</span>', "error" );
                $redirect_url = admin_url( 'admin.php?page=aifs_generate_ssl_manually' );
                $resolved_challenge = $this->resolveChallengeType( $challenge_type );
                if ( 'dns-01' === $resolved_challenge ) {
                    $redirect_url .= '&tab=' . rawurlencode( $resolved_challenge );
                }
                if ( wp_safe_redirect( $redirect_url ) ) {
                    exit;
                }
                // Redirect cancelled (e.g. tests) or failed — leave account_ready false.
                return;
            } else {
                die( __( "Exit", 'auto-install-free-ssl' ) );
            }
        }
        $this->adminFactory = new AdminFactory();
        $this->account_ready = true;
    }

    /**
     * Whether ACME account setup finished successfully.
     *
     * @return bool
     */
    public function isAccountReady() {
        return (bool) $this->account_ready;
    }

    /**
     * Resolve challenge type from an explicit value or sanitized POST input.
     *
     * @param string|null $challenge_type Explicit challenge type from the caller edge.
     * @return string Empty string, http-01, or dns-01.
     */
    protected function resolveChallengeType( $challenge_type ) {
        if ( null !== $challenge_type && '' !== $challenge_type ) {
            $candidate = sanitize_text_field( $challenge_type );
        } elseif ( isset( $_POST['aifs_challenge_type'] ) ) {
            $candidate = sanitize_text_field( wp_unslash( $_POST['aifs_challenge_type'] ) );
        } else {
            $candidate = '';
        }
        if ( 'http-01' === $candidate || 'dns-01' === $candidate ) {
            return $candidate;
        }
        return '';
    }

    /**
     * Inject a custom logger (used by unit tests).
     *
     * @param Logger $logger Logger instance.
     *
     * @return void
     */
    public function setLogger( $logger ) {
        $this->logger = $logger;
        if ( $this->client ) {
            $this->client->setLogger( $logger );
        }
        if ( $this->factory ) {
            $this->factory->setLogger( $logger );
        }
    }

    /**
     * Get available authentication options
     *
     * @param array $domains
     * @param $webRootDir
     * @param $reuseCsr
     * @param $countryCode
     * @param $state
     * @param $organization
     *
     * @return array
     */
    public function step1GetAuthenticationTokens(
        $domains,
        $webRootDir,
        $reuseCsr,
        $countryCode,
        $state,
        $organization
    ) {
        $this->webRootDir = $webRootDir;
        $this->factory->countryCode = $countryCode;
        $this->factory->state = $state;
        $this->factory->organization = $organization;
        $return_array_step1 = array(
            'response'    => array(
                'curlinfo_http_code' => 0,
            ),
            'domain_data' => array(),
        );
        if ( !$this->isAccountReady() ) {
            $this->logger->exception_sse_friendly( 'ACME account is not ready; cannot start certificate order.', __FILE__, __LINE__ );
            return $return_array_step1;
        }
        $this->logger->log( __( "Starting the SSL certificate generation process with ACME V2", 'auto-install-free-ssl' ) );
        $privateAccountKey = $this->factory->readPrivateKey( $this->accountKeyPath );
        $this->accountKeyDetails = openssl_pkey_get_details( $privateAccountKey );
        // start domains authentication
        $dns = [];
        foreach ( $domains as $domain ) {
            $dns[] = [
                'type'  => 'dns',
                'value' => $domain,
            ];
        }
        // 1. getting available authentication options
        $this->logger->log( __( "Requesting challenges for the array of domains", 'auto-install-free-ssl' ) );
        $newOrderUrl = $this->client->getUrl( 'newOrder' );
        if ( empty( $newOrderUrl ) ) {
            $this->logger->exception_sse_friendly( 'ACME newOrder URL is unavailable.', __FILE__, __LINE__ );
            return $return_array_step1;
        }
        $urlParts = explode( '/', $newOrderUrl );
        $response = $this->signedRequestV2( $newOrderUrl, [
            'url'         => end( $urlParts ),
            'identifiers' => $dns,
        ] );
        $this->assertAcmeSuccess( $response, 'certificate order' );
        $expected_status_codes = array(
            200,
            201,
            202,
            204
        );
        $http_code = $this->client->getLastCode();
        $order_complete = is_array( $response ) && !empty( $response['authorizations'] ) && !empty( $response['finalize'] ) && in_array( $http_code, $expected_status_codes, true );
        if ( !$order_complete ) {
            if ( is_array( $response ) && in_array( $http_code, $expected_status_codes, true ) && (empty( $response['authorizations'] ) || empty( $response['finalize'] )) ) {
                $this->logger->exception_sse_friendly( 'Let\'s Encrypt returned an incomplete certificate order response: ' . json_encode( $response ), __FILE__, __LINE__ );
            }
            if ( !is_array( $response ) ) {
                $response = array();
            }
            if ( !isset( $response['curlinfo_http_code'] ) ) {
                $response['curlinfo_http_code'] = $http_code;
            }
            // Ensure callers that only check HTTP status treat incomplete bodies as failure.
            if ( in_array( (int) $response['curlinfo_http_code'], $expected_status_codes, true ) && (empty( $response['authorizations'] ) || empty( $response['finalize'] )) ) {
                $response['curlinfo_http_code'] = 0;
            }
            $return_array_step1['response'] = $response;
            return $return_array_step1;
        }
        $return_array_step1['response'] = $response;
        //@todo
        $n = 0;
        //$number_of_validated_domains = 0;
        foreach ( $response['authorizations'] as $authorization ) {
            ++$n;
            $this->logger->log( "Domain {$n}" );
            $response2 = $this->client->get( $authorization );
            if ( isset( $response2['wildcard'] ) && $response2['wildcard'] ) {
                $domain = "*." . $response2['identifier']['value'];
            } else {
                $domain = $response2['identifier']['value'];
            }
            $return_array_step1['domain_data'][$domain]['response2'] = $response2;
            // L-11: guard against a shapeless $response2 the same way the
            // already-hardened call sites elsewhere in this file do.
            if ( is_array( $response2 ) && isset( $response2['status'] ) && 'valid' === $response2['status'] ) {
                //Domain ownership already verified. Skip the verification process
                /* translators: %s: A domain name, e.g., example.com */
                $this->logger->log( sprintf( __( "Domain (%s) already verified. Skip the verification process.", 'auto-install-free-ssl' ), $domain ) );
                $return_array_step1['domain_data'][$domain]['verified'] = true;
                //++$number_of_validated_domains;
            } else {
                $return_array_step1['domain_data'][$domain]['verified'] = false;
                //Start the Domain verification process
                if ( empty( $response2['challenges'] ) ) {
                    /* translators: %s: A domain name, e.g., example.com */
                    //$msg = sprintf(__("Challenge for %s is not available. Whole response:", 'auto-install-free-ssl'), $domain) . " <br />\n";
                    $msg = sprintf( "Challenge for %s is not available. Whole response:", $domain ) . " <br />\n";
                    //since 3.6.1, Don't translate this error message.
                    if ( $this->logger->is_cli() ) {
                        $msg .= print_r( $response2, true );
                        throw new \RuntimeException($msg);
                    } else {
                        $msg .= '<pre>' . print_r( $response2, true ) . '</pre>';
                        $this->logger->log_v2( 'error', $msg, [
                            'event' => 'exit',
                        ] );
                    }
                }
                //ACME V2 supported challenge types are HTTP-01 and DNS-01.
                $challenge_type_tmp = 'http-01';
                $challenge = $this->extract_selected_challenge_details( $response2, $challenge_type_tmp );
                if ( !$challenge ) {
                    //"http-01" is NOT available. Check for "dns-01"
                    $challenge_type_tmp = 'dns-01';
                    $challenge = $this->extract_selected_challenge_details( $response2, $challenge_type_tmp );
                    if ( !$challenge ) {
                        //"dns-01" is NOT available
                        /* translators: %s: A domain name, e.g., example.com */
                        //$msg = sprintf(__("Neither 'HTTP-01' nor 'DNS-01' challenge for %s is available. Whole response:", 'auto-install-free-ssl'), $domain) . " <br />\n";
                        $msg = sprintf( "Neither 'HTTP-01' nor 'DNS-01' challenge for %s is available. Whole response:", $domain ) . " <br />\n";
                        //since 3.6.1, Don't translate this error message.
                        if ( $this->logger->is_cli() ) {
                            $msg .= print_r( $response2, true );
                            throw new \RuntimeException($msg);
                        } else {
                            $msg .= '<pre>' . print_r( $response2, true ) . '</pre>';
                            $this->logger->log_v2( 'error', $msg, [
                                'event' => 'exit',
                            ] );
                        }
                    }
                    //"dns-01" is available
                    $this->logger->log( __( "The 'HTTP-01' challenge was NOT found, but the 'DNS-01' challenge has been found.", 'auto-install-free-ssl' ) );
                    if ( !aifs_is_free_version() ) {
                        /* translators: %1$s: Challenge type (HTTP-01 or DNS-01); %2$s: A domain name, e.g., example.com */
                        $this->logger->log( " " . sprintf( __( 'So, using the \'%1$s\' challenge for %2$s.', 'auto-install-free-ssl' ), 'DNS-01', $domain ) );
                    }
                    //$this->challenge = 'dns-01';
                    $return_array_step1['domain_data'][$domain]['challenge_type'] = 'dns-01';
                } else {
                    //"http-01" is available
                    $this->logger->log( __( "'HTTP-01' challenge found.", 'auto-install-free-ssl' ) );
                    if ( !aifs_is_free_version() ) {
                        /* translators: %1$s: Challenge type (HTTP-01 or DNS-01); %2$s: A domain name, e.g., example.com */
                        $this->logger->log( " " . sprintf( __( 'So, using the \'%1$s\' challenge for %2$s.', 'auto-install-free-ssl' ), 'HTTP-01', $domain ) );
                    }
                    //$this->challenge = 'http-01';
                    $return_array_step1['domain_data'][$domain]['challenge_type'] = 'http-01';
                }
                $return_array_step1['domain_data'][$domain]['challenge'] = $challenge;
                /* translators: %s: A domain name, e.g., example.com */
                $this->logger->log( sprintf( __( "We got the challenge token for %s.", 'auto-install-free-ssl' ), $domain ) );
                $location = $this->client->getLastLocation();
                $header = [
                    'e'   => Base64UrlSafeEncoder::encode( $this->accountKeyDetails['rsa']['e'] ),
                    'kty' => 'RSA',
                    'n'   => Base64UrlSafeEncoder::encode( $this->accountKeyDetails['rsa']['n'] ),
                ];
                $payload = trim( $challenge['token'] . '.' . Base64UrlSafeEncoder::encode( hash( 'sha256', json_encode( $header ), true ) ) );
                // C-5: store the raw key-authorization under a challenge-agnostic key
                // for BOTH challenge types. step2VerifyDomainOwnership() used to read
                // only $value['http-01']['payload'] regardless of $challenge_type, so
                // on a dns-01 authorization (which never wrote that key) it sent
                // 'keyAuthorization' => null and Let's Encrypt rejected the request --
                // wildcard/DNS-01 issuance was completely broken. The legacy
                // ['http-01']['payload'] key is still written below so any stored
                // in-flight state from an older plugin version still resolves.
                $return_array_step1['domain_data'][$domain]['payload'] = $payload;
                //HTTP-01
                if ( $return_array_step1['domain_data'][$domain]['challenge_type'] == 'http-01' ) {
                    $return_array_step1['domain_data'][$domain]['http-01']['file_name'] = $challenge['token'];
                    $return_array_step1['domain_data'][$domain]['http-01']['payload'] = $payload;
                }
                //DNS-01
                if ( aifs_is_free_version() || $return_array_step1['domain_data'][$domain]['challenge_type'] == 'dns-01' ) {
                    $dns_txt_record = Base64UrlSafeEncoder::encode( hash( 'sha256', $payload, true ) );
                    //$domain = str_replace( '*', '', $domain );
                    $registeredDomain = aifs_getRegisteredDomain( $domain );
                    //$sub_domain = '_acme-challenge.' . $domain;
                    $sub_domain = '_acme-challenge.' . str_replace( '*.', '', $domain );
                    $dns_txt_name = str_replace( '.' . $registeredDomain, '', $sub_domain );
                    $return_array_step1['domain_data'][$domain]['dns-01']['sub_domain'] = $sub_domain;
                    $return_array_step1['domain_data'][$domain]['dns-01']['dns_txt_record'] = $dns_txt_record;
                    $return_array_step1['domain_data'][$domain]['dns-01']['dns_txt_name'] = $dns_txt_name;
                }
            }
        }
        return $return_array_step1;
    }

    /**
     *
     *
     * @param $domain
     * @param $value
     *
     * @return bool
     */
    public function verifyDomainOwnershipHttp01Internal( $domain, $value ) {
        $challenge = $value['challenge'];
        json_encode( $challenge );
        $this->logger->log( "Verifying domain ownership of {$domain}" );
        //@since 4.6.0
        $payload = $value['http-01']['payload'];
        $uri = "http://" . $domain . "/.well-known/acme-challenge/" . $challenge['token'];
        $web_root_dir = $this->get_web_root_dir( $domain );
        if ( !$this->factory->verify_internally_http_wp( $payload, $uri ) ) {
            //@todo 20 April 2025: make the next line like this to provide imp debug information:   $this->logger->log( sprintf(__('Final Internal Validation: the Payload content (%1$s) successfully matched the content of %2$s.', 'auto-install-free-ssl'), $payload, $uri));
            //$this->logger->log( __( "1st Internal Validation:", 'auto-install-free-ssl' ) . " " . __( "Payload content does not match the challenge URI's content.", 'auto-install-free-ssl' ) );
            //@since 4.6.0, to provide important debug information
            /* translators: %1$s: A data, i.e., string of characters; %2$s: A URL, e.g., http://example.com/.well-known/acme-challenge/egIiS7rwd */
            $this->logger->log_v2( 'error', __( "1st Internal Validation:", 'auto-install-free-ssl' ) . " " . sprintf( __( 'Payload content (%1$s) does not match the content of the challenge URI %2$s.', 'auto-install-free-ssl' ), $payload, $uri ) );
            //Create htaccess rules in .well-known directory to fix the issue
            if ( $this->factory->fix_htaccess_challenge_dir( $web_root_dir . AIFS_DS . '.well-known' ) ) {
                $this->logger->log( __( ".htaccess rules have been created successfully in the '.well-known' directory.", 'auto-install-free-ssl' ) );
            } else {
                $this->logger->log( __( "Oops! There was an error creating .htaccess rules in the '.well-known' directory.", 'auto-install-free-ssl' ) );
            }
            if ( !$this->factory->verify_internally_http_wp( $payload, $uri ) ) {
                //$this->logger->log( __( "2nd Internal Validation:", 'auto-install-free-ssl' ) . " " . __( "Payload content does not match the challenge URI's content.", 'auto-install-free-ssl' ) );
                //@since 4.6.0, to provide important debug information
                /* translators: %1$s: A data, i.e., string of characters; %2$s: A URL, e.g., http://example.com/.well-known/acme-challenge/egIiS7rwd */
                $this->logger->log_v2( 'error', __( "2nd Internal Validation:", 'auto-install-free-ssl' ) . " " . sprintf( __( 'Payload content (%1$s) does not match the content of the challenge URI %2$s.', 'auto-install-free-ssl' ), $payload, $uri ) );
                if ( !aifs_is_free_version() ) {
                    if ( $this->saveAuthenticationTokenHttp01Alternative__premium_only( $domain, $value ) ) {
                        return true;
                    } else {
                        return false;
                    }
                }
            } else {
                //Put success log text here
                /* translators: %1$s: A data, i.e., string of characters; %2$s: A URL, e.g., http://example.com/.well-known/acme-challenge/egIiS7rwd */
                $this->logger->log( sprintf( __( 'Final Internal Validation: the Payload content (%1$s) successfully matched the content of %2$s.', 'auto-install-free-ssl' ), $payload, $uri ) );
                return true;
            }
        } else {
            //Put success log text here
            /* translators: %1$s: A data, i.e., string of characters; %2$s: A URL, e.g., http://example.com/.well-known/acme-challenge/egIiS7rwd */
            $this->logger->log( sprintf( __( 'Final Internal Validation: the Payload content (%1$s) successfully matched the content of %2$s.', 'auto-install-free-ssl' ), $payload, $uri ) );
            return true;
        }
    }

    /**
     *
     *
     * @param $domain
     * @param $value
     * @param bool $sleep_execution
     *
     * @return bool
     */
    public function verifyDomainOwnershipDns01Internal( $domain, $value, $sleep_execution = true ) {
        $registeredDomain = aifs_getRegisteredDomain( $domain );
        /*$sub_domain = $value['dns-01']['dns_txt_name'];
        		$dns_txt_record = $value['dns-01']['sub_domain'];//dns_txt_record*/
        $sub_domain = $value['dns-01']['sub_domain'];
        $dns_txt_record = $value['dns-01']['dns_txt_record'];
        //dns_txt_record
        //Remove DNS provider records other than $domain
        $dns_provider = array_reduce( $this->dns_provider, function ( $v, $w ) use(&$registeredDomain) {
            return ( $v ? $v : (( \in_array( $registeredDomain, $w['domains'], true ) ? $w : false )) );
        } );
        if ( $sleep_execution ) {
            //DNS TXT record needs time to propagate. So, delay the execution for 5 minutes
            $this->logger->log( __( "Execution sleeping for 2 minutes.", 'auto-install-free-ssl' ) );
            $this->sleepSeconds( 120 );
            $this->logger->log( __( "Execution resumed after 2 minutes of sleep.", 'auto-install-free-ssl' ) );
        }
        //Loop to check TXT propagation status
        if ( $dns_provider !== false && is_array( $dns_provider ) && $dns_provider['dns_provider_takes_longer_to_propagate'] ) {
            // @since 4.5.0
            $this->logger->log( __( "Now check whether the TXT record has been propagated.", 'auto-install-free-ssl' ) );
            $propagated = false;
            //actual value is false
            //waiting loop
            do {
                $result = $this->lookupDnsTxtRecords( $sub_domain );
                //@todo not working as expected. Too much delay in detecting DNS record.
                //Remove domain records other than $dns_txt_record
                $txt_details = array_reduce( $result, function ( $v, $w ) use(&$dns_txt_record) {
                    return ( $v ? $v : (( $w['txt'] === $dns_txt_record ? $w : false )) );
                } );
                if ( null !== $txt_details ) {
                    if ( $txt_details['txt'] === $dns_txt_record ) {
                        $propagated = true;
                        /* translators: %1$s: DNS TXT record */
                        $this->logger->log( sprintf( __( "TXT record (%s) has been propagated successfully.", 'auto-install-free-ssl' ), $dns_txt_record ) );
                    }
                }
                if ( !$propagated ) {
                    $min = __( "2", 'auto-install-free-ssl' );
                    /* translators: %1$s: DNS TXT record; %2$s: Number of minutes, e.g., 2 */
                    $this->logger->log( sprintf( __( 'TXT record (%1$s) has NOT been propagated till now, sleeping for %2$s minutes.', 'auto-install-free-ssl' ), $dns_txt_record, $min ) );
                    $this->sleepSeconds( 120 );
                }
            } while ( !$propagated );
            return true;
        } else {
            //First, verify internally. Then send to LE server to verify
            $result = $this->lookupDnsTxtRecords( $sub_domain );
            //Remove domain records other than $dns_txt_record
            $txt_details = array_reduce( $result, function ( $v, $w ) use(&$dns_txt_record) {
                return ( $v ? $v : (( $w['txt'] === $dns_txt_record ? $w : false )) );
            } );
            if ( null === $txt_details || $txt_details['txt'] !== $dns_txt_record ) {
                /* translators: %1$s: DNS TXT record; %2$s: A sub-domain, e.g., sub.example.com */
                $this->logger->log_v2( 'debug', sprintf( __( 'TXT record %1$s for %2$s has NOT been propagated till now. Please check whether the TXT record was set correctly. You may also set \'dns_provider_takes_longer_to_propagate\' => true and try again.', 'auto-install-free-ssl' ), $dns_txt_record, $sub_domain ) );
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     *
     *
     * @param $domain
     * @param $value
     * @param $challenge_type
     * @param array $return_array_step1
     *
     * @return bool
     */
    public function step2VerifyDomainOwnership(
        $domain,
        $value,
        $challenge_type,
        $return_array_step1 = []
    ) {
        if ( $value['challenge']['type'] == $challenge_type ) {
            $challenge = $value['challenge'];
        } else {
            $challenge = $this->extract_selected_challenge_details( $value['response2'], $challenge_type );
            if ( aifs_is_free_version() && count( $return_array_step1 ) > 0 ) {
                $return_array_step1['domain_data'][$domain]['challenge_type'] = $challenge_type;
                $return_array_step1['domain_data'][$domain]['challenge'] = $challenge;
                update_option( 'aifs_return_array_step1_manually', $return_array_step1 );
            }
        }
        json_encode( $challenge );
        // C-5: previously always read $value['http-01']['payload'], which
        // step1GetAuthenticationTokens() only ever populated for the http-01 branch --
        // a dns-01 authorization has no such key, so $payload was null and Let's
        // Encrypt rejected the challenge-completion request. Read the
        // challenge-agnostic 'payload' key step1 now writes for both challenge types;
        // fall back to the legacy ['http-01']['payload'] key for any in-flight state
        // stored by an older plugin version.
        if ( isset( $value['payload'] ) ) {
            $payload = $value['payload'];
        } elseif ( isset( $value['http-01']['payload'] ) ) {
            $payload = $value['http-01']['payload'];
        } else {
            $payload = null;
        }
        $response2 = $value['response2'];
        $this->logger->log( __( "Sending request to challenge", 'auto-install-free-ssl' ) );
        // L-11: guard against a shapeless $response2.
        $result['status'] = ( is_array( $response2 ) && isset( $response2['status'] ) ? $response2['status'] : null );
        $ended = !('pending' === $result['status']);
        // L-10: bound the waiting loop (max attempts + wall-clock deadline) so a
        // persistently 'pending' challenge can't spin forever.
        $max_attempts = 60;
        // ~2 minutes at 2s/attempt.
        $deadline = time() + 300;
        // 5-minute wall-clock ceiling regardless of attempt count.
        $attempt_number = 0;
        // waiting loop
        do {
            ++$attempt_number;
            if ( $attempt_number > $max_attempts || time() > $deadline ) {
                $ended = true;
                /* translators: %1$s: A domain name, e.g., example.com; %2$s: Number of attempts */
                $this->logger->log_v2( 'error', sprintf( __( 'Gave up waiting for domain ownership verification of %1$s to complete after %2$s attempts.', 'auto-install-free-ssl' ), $domain, $attempt_number ), [
                    'event' => 'exit',
                ] );
                break;
            }
            if ( !$ended ) {
                // send request to challenge
                $result = $this->signedRequestV2( $challenge['url'], [
                    'type'             => $challenge_type,
                    'keyAuthorization' => $payload,
                    'token'            => $challenge['token'],
                ] );
                // START since 3.6.1 (added July 16, 2023)
                /*if($this->logger->is_cli()) {
                					$result_text = print_r($result, true);
                				}
                				else{
                					$result_text = '<pre>'. print_r($result, true) .'</pre>';
                				}
                
                				$this->logger->log("Let's Encrypt server response (Challenge result): <br />\n". $result_text);*/
                // END since 3.6.1 (added July 16, 2023)
            }
            // H-6: the two integer comparisons here (400 === $result['status'] /
            // 404 === $result['status']) were dead code -- $result['status'] is an
            // ACME status string ('pending'/'valid'/'invalid'), never an int, so
            // these could never be true. They were clearly meant to test the HTTP
            // response code instead; use $this->client->getLastCode() for that.
            $last_http_code = $this->client->getLastCode();
            if ( empty( $result['status'] ) || 'invalid' === $result['status'] || 400 === $last_http_code || 404 === $last_http_code ) {
                $ended = true;
                //$msg = "<br />\n " . __( "Content of", 'auto-install-free-ssl' ) . " " . $challenge['url'] . "  <br />\n";
                $msg = "<br />\n Content of " . $challenge['url'] . "  <br />\n";
                //since 3.6.1, Don't translate this error message.
                $challenge_url_content = $this->client->get( $challenge['url'] );
                if ( $this->logger->is_cli() ) {
                    $msg .= print_r( $challenge_url_content, true );
                } else {
                    $msg .= '<pre>' . print_r( $challenge_url_content, true ) . '</pre>';
                }
                $this->logger->log_v2( 'error', $msg );
                //$msg = __( "Verification ended with an error", 'auto-install-free-ssl' ) . ": <br />\n";
                $msg = "Verification ended with an error: <br />\n";
                //since 3.6.1, Don't translate this error message.
                if ( $this->logger->is_cli() ) {
                    $msg .= print_r( $result, true );
                    throw new \RuntimeException($msg);
                } else {
                    $msg .= '<pre>' . print_r( $result, true ) . '</pre>';
                    $this->logger->log_v2( 'error', $msg, [
                        'event' => 'exit',
                    ] );
                }
            }
            /*// send request to challenge
            		$result = $this->signedRequestV2(
            			$challenge['url'],
            			[
            				//"resource" => "challenge",
            				'type' => $challenge_type,
            				'keyAuthorization' => $payload,
            				'token' => $challenge['token'],
            			]
            		);*/
            // L-11: guard against a shapeless $result (e.g. signedRequestV2()
            // returning null on an empty nonce).
            if ( is_array( $result ) && isset( $result['status'] ) && 'valid' === $result['status'] ) {
                $ended = true;
                // if premium version START
                if ( !aifs_is_free_version() && 'http-01' === $challenge_type ) {
                    $web_root_dir = $this->get_web_root_dir( $domain );
                    $directory = $web_root_dir . AIFS_DS . '.well-known' . AIFS_DS . 'acme-challenge';
                    $tokenPath = $directory . AIFS_DS . $challenge['token'];
                    if ( @unlink( $tokenPath ) ) {
                        $this->logger->log( __( "Deleted challenge file", 'auto-install-free-ssl' ) . ": " . $tokenPath );
                    } else {
                        $this->logger->log( __( "The challenge file was not deleted due to an error. You may delete it manually.", 'auto-install-free-ssl' ) . " : " . $tokenPath );
                    }
                }
                // if premium version END
            }
            if ( !$ended ) {
                $sec = __( "2", 'auto-install-free-ssl' );
                /* translators: %s: Number of seconds, e.g., 2 */
                $this->logger->log( sprintf( __( "Verification pending, sleeping %s seconds.", 'auto-install-free-ssl' ), $sec ) );
                //sleep(1);
                $this->sleepSeconds( 2 );
            }
        } while ( !$ended );
        // L-11: guard against a shapeless $result; strtoupper(null) is a PHP 8.1+
        // deprecation.
        $final_status = ( is_array( $result ) && isset( $result['status'] ) ? $result['status'] : 'unknown' );
        /* translators: %1$s: Status of the verification, e.g., 'valid'; %2$s: A domain name, e.g., example.com */
        $this->logger->log( sprintf( __( 'Verification ended with status: \'%1$s\' for the domain %2$s', 'auto-install-free-ssl' ), strtoupper( $final_status ), $domain ) );
        if ( 'valid' === $final_status ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Step 3 of Generate SSL
     * Improved since 3.6.2
     *
     * @param $domains
     * @param $reuseCsr
     * @param $return_array_step1
     *
     * @return bool
     */
    public function step3GenerateSSL( $domains, $reuseCsr, $return_array_step1 ) {
        $domainPath = $this->factory->getDomainPath( $domains[0] );
        //die('Domain path: '. $domainPath.'\r\n');
        //Overwrite private key, CSR, certificate files if exists already
        // generate private key for domain
        $this->factory->generateKey( $domainPath, $this->key_size );
        // load domain key
        $privateDomainKey = $this->factory->readPrivateKey( $domainPath . AIFS_DS . 'private.pem' );
        $csr = ( $reuseCsr && is_file( $domainPath . AIFS_DS . 'csr_last.csr' ) ? $this->factory->getCsrContent( $domainPath . AIFS_DS . 'csr_last.csr' ) : $this->factory->generateCSR( $privateDomainKey, $domains, $this->key_size ) );
        // request certificates creation
        $result = $this->signedRequestV2( $return_array_step1['response']['finalize'], [
            'csr' => $csr,
        ] );
        if ( aifs_is_free_version() ) {
            $return_array_step1['response_final'] = $result;
            update_option( 'aifs_return_array_step1_manually', $return_array_step1 );
        }
        if ( 200 !== $this->client->getLastCode() ) {
            //$this->logger->exception_sse_friendly(__( "Invalid response code", 'auto-install-free-ssl' ) . ": " . $this->client->getLastCode().", ".json_encode($result), __FILE__, __LINE__);
            $this->logger->exception_sse_friendly( "Invalid response code: " . $this->client->getLastCode() . ", " . json_encode( $result ), __FILE__, __LINE__ );
            //since 3.6.1, Don't translate exception message
            return false;
        }
        if ( !\is_array( $result ) || empty( $result['certificate'] ) ) {
            $this->logger->exception_sse_friendly( "Invalid finalize response or empty 'certificate' key from Let's Encrypt™. Please try again after some time. Response: " . json_encode( $result ), __FILE__, __LINE__ );
            return false;
        } else {
            $location = $result['certificate'];
            // L-10: bound this loop -- previously a persistent 202 (pending) spun
            // forever with no cap.
            $cert_max_attempts = 60;
            $cert_deadline = time() + 300;
            $cert_attempt_number = 0;
            // waiting loop
            $certificates = [];
            while ( 1 ) {
                ++$cert_attempt_number;
                if ( $cert_attempt_number > $cert_max_attempts || time() > $cert_deadline ) {
                    $this->logger->exception_sse_friendly( "Gave up waiting for certificate generation to complete after {$cert_attempt_number} attempts.", __FILE__, __LINE__ );
                    $certificates = [];
                    break;
                }
                //$this->client->getLastLinks();
                $fullchain = $this->client->get( $location, null, true );
                $this->logger->log( __( "Location value", 'auto-install-free-ssl' ) . ": " . $location );
                if ( 202 === $this->client->getLastCode() ) {
                    $sec = __( "1", 'auto-install-free-ssl' );
                    /* translators: %s: Number of second */
                    $this->logger->log( sprintf( __( "Certificate generation pending, sleeping %s second.", 'auto-install-free-ssl' ), $sec ) );
                    sleep( 1 );
                } elseif ( 200 === $this->client->getLastCode() ) {
                    $this->logger->log( __( "We have got a certificate! YAY!", 'auto-install-free-ssl' ) );
                    //$certificates = explode( "\n\n", $result );
                    $certificates = $this->splitFullChain( $fullchain );
                    //@since 4.6.3
                    break;
                } else {
                    //$this->logger->exception_sse_friendly(__( "Can't get a certificate: HTTP code", 'auto-install-free-ssl' ) . ": ". $this->client->getLastCode(), __FILE__, __LINE__);
                    $this->logger->exception_sse_friendly( "Can't get a certificate: HTTP code: " . $this->client->getLastCode(), __FILE__, __LINE__ );
                    //since 3.6.1, Don't translate exception message.
                    $certificates = [];
                    break;
                    //return false;
                }
            }
            if ( empty( $fullchain ) || empty( $certificates ) ) {
                //$this->logger->exception_sse_friendly(__( "No certificates generated", 'auto-install-free-ssl' ), __FILE__, __LINE__);
                $this->logger->exception_sse_friendly( "No certificates generated. Please try again.", __FILE__, __LINE__ );
                //since 3.6.1, Don't translate exception message.
                return false;
            } else {
                $this->logger->log( __( "Saving Certificate (CRT) certificate.pem", 'auto-install-free-ssl' ) );
                file_put_contents( $domainPath . AIFS_DS . 'certificate.pem', $certificates['certificate'] );
                $this->logger->log( __( "Saving (CABUNDLE) cabundle.pem", 'auto-install-free-ssl' ) );
                //unset($certificates[0]);
                //file_put_contents($domainPath.AIFS_DS.'cabundle.pem', implode( "\n\n", $certificates ));
                file_put_contents( $domainPath . AIFS_DS . 'cabundle.pem', $certificates['cabundle'] );
                $this->logger->log( __( "Saving fullchain.pem", 'auto-install-free-ssl' ) );
                file_put_contents( $domainPath . AIFS_DS . 'fullchain.pem', $fullchain );
                /* translators: "Let's Encrypt" is a nonprofit SSL certificate authority. */
                $this->logger->log_v2( 'SUCCESS', __( "Done!!!! Let's Encrypt™ ACME V2 SSL certificate successfully issued!!", 'auto-install-free-ssl' ), [
                    'event' => 'gist',
                ] );
                update_option( 'aifs_number_of_ssl_generated', get_option( 'aifs_number_of_ssl_generated' ) + 1 );
                //@since 3.4.0
                //if not multi domain or generating SSL for this website
                //improved @since 4.0.0
                $this_domain = aifs_get_domain( true );
                if ( !aifs_can_manage_multi_domain() || in_array( $this_domain, $domains ) || in_array( 'www.' . $this_domain, $domains ) ) {
                    delete_option( 'aifs_is_generated_ssl_installed' );
                    //@since 3.4.0
                }
                return true;
            }
        }
    }

    /**
     * Split fullchain.pem into EE cert and CA bundle (intermediate + root).
     * cPanel's installssl API requires the CA bundle to include the root.
     *
     * @param string $fullchain  The raw PEM content of fullchain.pem
     * @return array ['certificate' => string, 'cabundle' => string]
     * @since 4.6.3
     */
    public function splitFullChain( $fullchain ) {
        // Extract all PEM blocks
        preg_match_all( '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $fullchain, $matches );
        $certs = $matches[0];
        if ( count( $certs ) < 2 ) {
            //throw new \RuntimeException('Full chain must contain at least 2 certificates.');
            $this->logger->exception_sse_friendly( "Full chain must contain at least 2 certificates.", __FILE__, __LINE__ );
            return [];
        }
        $certificate = $certs[0];
        // [1] End-Entity (EE) / Leaf Certificate
        $cabundle = implode( "\n\n", array_slice( $certs, 1 ) );
        // [2] Intermediate CA Certificate + [3] Root CA Certificate + ...
        return [
            'certificate' => $certificate,
            'cabundle'    => $cabundle,
        ];
    }

    /**
     *
     * Improved since 3.6.6
     * @param $domain
     *
     * @return mixed
     */
    public function get_web_root_dir( $domain ) {
        if ( isset( $this->webRootDir ) ) {
            return ( \is_array( $this->webRootDir ) ? $this->webRootDir[$domain] : $this->webRootDir );
        } else {
            return $this->adminFactory->document_root_wp();
        }
    }

    /**
     *
     *
     * @param array $response2
     * @param string $challenge_type
     *
     * @return array|null
     */
    public function extract_selected_challenge_details( $response2, $challenge_type ) {
        return array_reduce( $response2['challenges'], function ( $v, $w ) use(&$challenge_type) {
            return ( $v ? $v : (( $w['type'] === $challenge_type ? $w : false )) );
        } );
    }

    /**
     * Look up DNS TXT records for DNS-01 verification (override in tests).
     *
     * @param string $hostname Hostname to query.
     *
     * @return array
     */
    protected function lookupDnsTxtRecords( $hostname ) {
        $result = dns_get_record( $hostname, DNS_TXT );
        return ( is_array( $result ) ? $result : array() );
    }

    /**
     * Sleep for the given number of seconds (override in tests).
     *
     * @param int $seconds Seconds to sleep.
     *
     * @return void
     */
    protected function sleepSeconds( $seconds ) {
        sleep( $seconds );
    }

    /**
     *
     *
     * @param type $key_path
     * @return type
     */
    private function postNewReg( $key_path ) {
        /* translators: "Let's Encrypt" is a nonprofit SSL certificate authority. */
        $this->logger->log( __( "Sending registration to the Let's Encrypt™ server", 'auto-install-free-ssl' ) );
        $data = [
            'termsOfServiceAgreed' => true,
        ];
        //Add 'mailto:' with email id
        if ( $this->contact ) {
            $contact_array = [];
            foreach ( $this->contact as $contact ) {
                $contact_array[] = 'mailto:' . $contact;
            }
            $data['contact'] = $contact_array;
        }
        $newAccountUrl = $this->client->getUrl( 'newAccount' );
        return $this->signedRequest( $newAccountUrl, $data, $key_path );
    }

    /**
     *
     *
     * @param type $uri
     * @param array $payload
     * @param type $key_path
     * @return type
     */
    private function signedRequest( $newAccountUrl, array $payload, $key_path ) {
        $privateKey = $this->factory->readPrivateKey( $key_path );
        $details = openssl_pkey_get_details( $privateKey );
        $header['alg'] = 'RS256';
        //RS256 for LE
        $header['jwk'] = [
            'kty' => 'RSA',
            'n'   => Base64UrlSafeEncoder::encode( $details['rsa']['n'] ),
            'e'   => Base64UrlSafeEncoder::encode( $details['rsa']['e'] ),
        ];
        $protected = $header;
        $protected['nonce'] = $this->getLastNonce();
        if ( '' === $protected['nonce'] ) {
            // getLastNonce() already logged a clear, actionable error (bounded retries
            // exhausted). Do not dispatch a request that is guaranteed to fail with a
            // confusing ACME badNonce/malformed-JWS error -- abort here instead, matching
            // Client::curl()'s own "precondition failed, return without dispatching" pattern.
            // Invalidate the last HTTP code too, since it otherwise still holds whatever
            // status the nonce-fetch retries happened to receive, which callers checking
            // getLastCode() after this abort would wrongly read as this request's outcome.
            $this->client->invalidateLastCode();
            return null;
        }
        $protected['url'] = $newAccountUrl;
        /*
        echo "Protected Outer: <br />". str_replace('\\/', '/', json_encode($protected))."<br /><br />";
        
        echo "<br /><br />Array 2 (Protected outer)<br /><br /><pre>";
        print_r($protected);
        echo "</pre><br /><br />";
        */
        $payload64 = Base64UrlSafeEncoder::encode( json_encode( $payload ) );
        $protected64 = Base64UrlSafeEncoder::encode( str_replace( '\\/', '/', json_encode( $protected ) ) );
        openssl_sign(
            $protected64 . '.' . $payload64,
            $signed,
            $privateKey,
            'SHA256'
        );
        $signed64 = Base64UrlSafeEncoder::encode( $signed );
        $data = [
            'protected' => $protected64,
            'payload'   => $payload64,
            'signature' => $signed64,
        ];
        /* translators: %s: URL of the API */
        $this->logger->log( sprintf( __( "Sending signed request to %s", 'auto-install-free-ssl' ), $newAccountUrl ) );
        return $this->client->post( $newAccountUrl, json_encode( $data ) );
    }

    /**
     *
     *
     * @param type $uri
     * @param array $payload
     * @return type
     */
    private function signedRequestV2( $uri, array $payload ) {
        $privateKey = $this->factory->readPrivateKey( $this->accountKeyPath );
        $details = openssl_pkey_get_details( $privateKey );
        $header = [
            'alg' => 'RS256',
            'kid' => $this->kid,
        ];
        $protected = $header;
        $protected['nonce'] = $this->getLastNonce();
        if ( '' === $protected['nonce'] ) {
            // Same abort-before-dispatch guard as signedRequest() above, including the
            // getLastCode() invalidation so a stale nonce-fetch status code can't be
            // mistaken for this (never sent) request's outcome.
            $this->client->invalidateLastCode();
            return null;
        }
        $protected['url'] = $uri;
        //$payload64 = Base64UrlSafeEncoder::encode(str_replace('\\/', '/', json_encode($payload))); //use JSON_UNESCAPED_SLASHES
        //$protected64 = Base64UrlSafeEncoder::encode(str_replace('\\/', '/', json_encode($protected)));//use JSON_UNESCAPED_SLASHES
        $payload64 = Base64UrlSafeEncoder::encode( json_encode( $payload, JSON_UNESCAPED_SLASHES ) );
        //use JSON_UNESCAPED_SLASHES
        $protected64 = Base64UrlSafeEncoder::encode( json_encode( $protected, JSON_UNESCAPED_SLASHES ) );
        //use JSON_UNESCAPED_SLASHES
        openssl_sign(
            $protected64 . '.' . $payload64,
            $signed,
            $privateKey,
            'SHA256'
        );
        $signed64 = Base64UrlSafeEncoder::encode( $signed );
        $data = [
            'protected' => $protected64,
            'payload'   => $payload64,
            'signature' => $signed64,
        ];
        /* translators: %s: URL of the API */
        $this->logger->log( sprintf( __( "Sending signed request to %s", 'auto-install-free-ssl' ), $uri ) );
        return $this->client->post( $uri, json_encode( $data ) );
    }

    /**
     * Boundary-safe check that $path is $base itself or a descendant of it.
     * Avoids the classic bug where strpos($path, $base) === 0 wrongly matches
     * a sibling path (e.g. "/home/user2" satisfying base "/home/user").
     *
     * @param string $path Absolute path to check.
     * @param string $base Absolute base directory.
     * @return bool
     */
    public static function isPathWithinBase( $path, $base ) {
        $base = rtrim( $base, \DIRECTORY_SEPARATOR );
        return $path === $base || 0 === strpos( $path, $base . \DIRECTORY_SEPARATOR );
    }

    /**
     * Assert that the last ACME HTTP response was a success status code.
     *
     * @param mixed  $response  Decoded ACME response body.
     * @param string $operation Human-readable operation name for error messages.
     * @return void
     */
    private function assertAcmeSuccess( $response, $operation ) {
        $code = $this->client->getLastCode();
        $expected_status_codes = array(
            200,
            201,
            202,
            204
        );
        if ( \in_array( $code, $expected_status_codes, true ) ) {
            return;
        }
        $this->logger->exception_sse_friendly( $this->formatAcmeErrorMessage( $response, $operation ), __FILE__, __LINE__ );
    }

    /**
     * Build a clear ACME error message, preferring the server "detail" field when present.
     *
     * @param mixed  $response  Decoded ACME response body.
     * @param string $operation Human-readable operation name.
     * @return string
     */
    private function formatAcmeErrorMessage( $response, $operation ) {
        if ( \is_array( $response ) && !empty( $response['detail'] ) ) {
            return 'Let\'s Encrypt rejected the ' . $operation . ' request: ' . $response['detail'];
        }
        return 'Let\'s Encrypt rejected the ' . $operation . ' request (HTTP ' . $this->client->getLastCode() . '): ' . json_encode( $response );
    }

    /**
     * Fetch the last ACME Replay-Nonce, requesting a fresh one from the CA when needed.
     *
     * Bounded to a handful of attempts (instead of recursing without limit) so a persistently
     * unavailable ACME directory/newNonce endpoint fails with a clear error rather than
     * exhausting the PHP call stack/memory.
     *
     * @param int $attempt Current attempt number (internal; callers should omit).
     * @return string
     */
    private function getLastNonce( $attempt = 0 ) {
        if ( !is_null( $this->client->lastHeader ) && preg_match( '~Replay\\-Nonce: (.+)~i', $this->client->lastHeader, $matches ) ) {
            return trim( $matches[1] );
        }
        if ( $attempt >= 3 ) {
            $this->logger->exception_sse_friendly( "Could not obtain a Replay-Nonce from the Let's Encrypt server after 3 attempts.", __FILE__, __LINE__ );
            return '';
        }
        $newNonceUrl = $this->client->getUrl( 'newNonce' );
        $this->client->curl( 'HEAD', $newNonceUrl );
        return $this->getLastNonce( $attempt + 1 );
    }

    /**
     * Register new ACME account, if not exist
     *
     */
    public function registerNewAcmeAccount() {
        //check if the account key exist
        if ( !is_file( $this->accountKeyPath ) || strlen( $this->kid ) < 10 || !is_file( \dirname( $this->accountKeyPath ) . AIFS_DS . 'public.pem' ) ) {
            // generate and save new private key for the account
            $this->logger->log( __( "Starting new account registration", 'auto-install-free-ssl' ) );
            $this->factory->generateKey( \dirname( $this->accountKeyPath ), $this->key_size );
            $response = $this->postNewReg( $this->accountKeyPath );
            $return_array = [];
            // L-11: guard against a shapeless $response.
            if ( is_array( $response ) && isset( $response['status'] ) && 'valid' === $response['status'] ) {
                $this->kid = $this->client->getLastLocation();
                $this->logger->log( 'kid: ' . $this->kid );
                //Save the kid in a text file
                if ( file_put_contents( \dirname( $this->accountKeyPath ) . AIFS_DS . 'kid.txt', $this->kid ) !== false ) {
                    $this->logger->log( __( "Congrats! A new account has been registered successfully.", 'auto-install-free-ssl' ) );
                    $return_array['proceed'] = true;
                    if ( get_option( 'aifs_is_admin_email_invalid' ) ) {
                        update_option( 'aifs_is_admin_email_invalid', 0 );
                    }
                } else {
                    /* translators: %1$s: A directory path, e.g., /home/username/ssl-cert; %2$s: A kid (characters) generated by Let's Encrypt */
                    //$error_text = sprintf(__( 'Error creating kid.txt file. Please create a text file with the filename \'kid.txt\' in this path %1$s and paste the following text in it: %2$s Then try again.', 'auto-install-free-ssl' ), "<strong>" . \dirname($this->accountKeyPath) . "</strong>", "<pre>" . $this->kid . "</pre>");
                    //$this->logger->log_v2('error', $error_text . " " . __( "Closing the connection", 'auto-install-free-ssl' ), ['event' => 'exit']);
                    $error_text = sprintf( 'Error creating kid.txt file. Please create a text file with the filename \'kid.txt\' in this path %1$s and paste the following text in it: %2$s Then try again.', "<strong>" . \dirname( $this->accountKeyPath ) . "</strong>", "<pre>" . $this->kid . "</pre>" );
                    $this->logger->log_v2( 'error', $error_text . " Closing the connection", [
                        'event' => 'exit',
                    ] );
                    //since 3.6.1, Don't translate this error message.
                    $return_array['error_text'] = $error_text;
                    $return_array['proceed'] = false;
                }
            } else {
                /* translators: "Let's Encrypt" is a nonprofit SSL certificate authority. */
                $this->logger->log_v2( 'debug', __( "Sorry, there was a problem registering the account. Please try again. Let's Encrypt™ server response given below.", 'auto-install-free-ssl' ) );
                //Delete the key files as the registration failed
                if ( is_file( $this->accountKeyPath ) ) {
                    unlink( \dirname( $this->accountKeyPath ) . AIFS_DS . 'private.pem' );
                    unlink( \dirname( $this->accountKeyPath ) . AIFS_DS . 'public.pem' );
                }
                if ( $this->logger->is_cli() ) {
                    $this->logger->log_v2( 'debug', print_r( $response, true ) );
                    die( __( "Closing the connection", 'auto-install-free-ssl' ) );
                } else {
                    $this->logger->log_v2( 'debug', '<pre>' . print_r( $response, true ) . '</pre>' );
                    $this->logger->log_v2( 'debug', __( "Closing the connection", 'auto-install-free-ssl' ), [
                        'event' => 'exit',
                    ] );
                }
                // L-11: strpos() on a missing/null 'type' is a PHP 8.1+ deprecation.
                if ( isset( $response['type'] ) && strpos( $response['type'], 'invalidEmail' ) !== false ) {
                    $error_text = sprintf(
                        /* translators: %1$s: An email id; %2$s: Opening HTML 'strong' tag; %3$s: Closing 'strong' tag. (Opening and closing 'strong' tags make the enclosed text bold. "Let's Encrypt" is a nonprofit SSL certificate authority.) */
                        __( 'Oops! An invalid email id (%1$s) was set as the admin email of this WordPress website. But Let\'s Encrypt™ expects we should register an account with a working email address. So please %2$s fill in the following text field with your real email id %3$s and try again. This will update the admin email too.', 'auto-install-free-ssl' ),
                        implode( ', ', $this->contact ),
                        '<strong>',
                        '</strong>'
                    );
                    if ( !get_option( 'aifs_is_admin_email_invalid' ) ) {
                        update_option( 'aifs_is_admin_email_invalid', 1 );
                    }
                } else {
                    /* translators: "Let's Encrypt" is a nonprofit SSL certificate authority. */
                    $error_text = __( "Oops! There is an error registering your Let's Encrypt™ account. Error details are given below:", 'auto-install-free-ssl' ) . '<br /><pre>' . print_r( $response, true ) . '</pre>';
                }
                $return_array['error_text'] = $error_text;
                $return_array['proceed'] = false;
            }
        } else {
            $this->logger->log( __( "The account is already registered. Continuing...", 'auto-install-free-ssl' ) );
            $return_array['proceed'] = true;
            if ( get_option( 'aifs_is_admin_email_invalid' ) ) {
                update_option( 'aifs_is_admin_email_invalid', 0 );
            }
        }
        return $return_array;
    }

}
