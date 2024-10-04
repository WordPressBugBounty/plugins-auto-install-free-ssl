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
namespace AutoInstallFreeSSL\FreeSSLAuto\Acme;

use AutoInstallFreeSSL\FreeSSLAuto\Logger;
class Client implements ClientInterface {
    public $lastHeader;

    private $lastCode;

    private $base;

    private $logger;

    public function __construct( $base ) {
        if ( !defined( 'ABSPATH' ) ) {
            die( __( "Access denied", 'auto-install-free-ssl' ) );
        }
        $this->base = $base;
        $this->logger = new Logger();
    }

    /**
     *
     *
     * @param $method
     * @param $url
     * @param $data
     * @param $generate_cert
     *
     * @return mixed|string|void
     */
    public function curl(
        $method,
        $url,
        $data = null,
        $generate_cert = false
    ) {
        if ( $generate_cert ) {
            $headers = ['Accept: application/pem-certificate-chain', 'Content-Type: application/jose+json'];
        } else {
            $headers = ['Accept: application/json', 'Content-Type: application/jose+json'];
        }
        $handle = curl_init();
        curl_setopt( $handle, CURLOPT_URL, ( preg_match( '~^https~', $url ) ? $url : $this->base . $url ) );
        curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $handle, CURLOPT_HEADER, true );
        switch ( $method ) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt( $handle, CURLOPT_POST, true );
                curl_setopt( $handle, CURLOPT_POSTFIELDS, $data );
                break;
        }
        $response = curl_exec( $handle );
        $error_number = curl_errno( $handle );
        //@since 3.6.1
        if ( $error_number ) {
            $this->logger->exception_sse_friendly( "cURL error {$error_number}: " . curl_error( $handle ), __FILE__, __LINE__ );
        }
        $header_size = curl_getinfo( $handle, CURLINFO_HEADER_SIZE );
        $header = substr( $response, 0, $header_size );
        $body = substr( $response, $header_size );
        $this->lastHeader = $header;
        $this->lastCode = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
        $data = json_decode( $body, true );
        $result = ( null === $data ? $body : $data );
        if ( is_array( $result ) ) {
            $result['curlinfo_http_code'] = $this->lastCode;
        }
        //Valid / expected status code
        $expected_status_codes = [
            200,
            201,
            202,
            204
        ];
        //Check status code
        if ( !\in_array( $this->lastCode, $expected_status_codes, true ) ) {
            //Failed
            /* translators: %d: A number i.e., HTTP status code. ("Let's Encrypt" is a nonprofit SSL certificate authority.) */
            //$this->logger->log_v2('error', sprintf(__("Sorry, the Let's Encrypt™ server response (%d) is unexpected. The complete server response is given below.", 'auto-install-free-ssl'), $this->lastCode));
            $this->logger->log_v2( 'error', sprintf( "Sorry, the Let's Encrypt™ server response (%d) is unexpected. The complete server response is given below.", $this->lastCode ) );
            //since 3.6.1, Don't translate this error message.
            if ( $this->logger->is_cli() ) {
                $this->logger->log_v2( 'error', print_r( $result, true ) );
                //die(__( "Closing the connection", 'auto-install-free-ssl' ));
                die( "Closing the connection" );
                //since 3.6.1, Don't translate this error message.
            } else {
                $this->logger->log_v2( 'error', '<pre>' . print_r( $result, true ) . '</pre>' );
                //$this->logger->log_v2('error', __( "Closing the connection", 'auto-install-free-ssl' ), ['event' => 'exit']);
                $this->logger->log_v2( 'error', "Closing the connection", [
                    'event' => 'exit',
                ] );
                //since 3.6.1, Don't translate this error message.
            }
        } else {
            if ( get_option( 'aifs_log_all_ca_server_response' ) ) {
                //since 3.6.1
                $result_text = '<pre>' . (( is_array( $result ) ? print_r( $result, true ) : $result )) . '</pre>';
                $this->logger->log( sprintf( "Let's Encrypt™ server response (%d) details is given below", $this->lastCode ) . ": <br />\n" . $result_text );
            }
        }
        return $result;
    }

    /**
     *
     *
     * @param $url
     * @param $data
     * @param $generate_cert
     *
     * @return array|mixed|string|void
     */
    public function post( $url, $data, $generate_cert = false ) {
        return $this->curl(
            'POST',
            $url,
            $data,
            $generate_cert
        );
    }

    /**
     *
     *
     * @param string $url
     * @param null $data
     * @param false $generate_cert
     *
     * @return array|false|mixed|string
     */
    public function get( $url, $data = null, $generate_cert = false ) {
        return $this->curl(
            'GET',
            $url,
            $data,
            $generate_cert
        );
    }

    /**
     *
     *
     * get Let's Encrypt API URLs
     * @param $key
     *
     * @return mixed
     */
    public function getUrl( $key ) {
        $dir_array = $this->get( $this->base . '/directory' );
        return $dir_array[$key];
    }

    /**
     *
     *
     * @return string|null
     */
    public function getLastLocation() {
        if ( preg_match( '~Location: (.+)~i', $this->lastHeader, $matches ) ) {
            return trim( $matches[1] );
        }
        return null;
    }

    /**
     *
     *
     * @return int
     */
    public function getLastCode() {
        return $this->lastCode;
    }

    /**
     * Returns the CA termsOfService URL
     * @return mixed|string
     * @since 4.3.0
     */
    public function getTermsOfService() {
        $meta = $this->getUrl( 'meta' );
        return $meta['termsOfService'];
    }

}
