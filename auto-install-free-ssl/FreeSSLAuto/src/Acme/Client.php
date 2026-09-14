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

use AutoInstallFreeSSL\FreeSSLAuto\Logger;
class Client implements ClientInterface {
    public $lastHeader;

    private $lastCode;

    private $base;

    private $logger;

    private $directoryCache;

    public function __construct( $base ) {
        if ( !defined( 'ABSPATH' ) ) {
            die( "Access denied" );
        }
        $this->base = $base;
        $this->logger = new Logger();
        $this->directoryCache = null;
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
        if ( null === $url || '' === $url ) {
            $this->logger->exception_sse_friendly( 'ACME request URL is missing.', __FILE__, __LINE__ );
            return;
        }
        if ( $generate_cert ) {
            $headers = array(
                'Accept'       => 'application/pem-certificate-chain',
                'Content-Type' => 'application/jose+json',
            );
        } else {
            $headers = array(
                'Accept'       => 'application/json',
                'Content-Type' => 'application/jose+json',
            );
        }
        $request_url = ( preg_match( '~^https~', $url ) ? $url : $this->base . $url );
        $args = array(
            'method'      => $method,
            'headers'     => $headers,
            'timeout'     => 60,
            'redirection' => 0,
        );
        if ( 'POST' === $method && null !== $data ) {
            $args['body'] = $data;
        }
        $response = wp_remote_request( $request_url, $args );
        if ( is_wp_error( $response ) ) {
            $this->invalidateDirectoryCache();
            if ( 'http_request_not_executed' === $response->get_error_code() ) {
                // wp_remote_request() is subject to WP_HTTP_BLOCK_EXTERNAL / WP_ACCESSIBLE_HOSTS
                // (raw cURL, used before this class was migrated to the WP HTTP API, never was).
                // Give an actionable message instead of the generic WP_Error text so admins who've
                // locked down outbound HTTP know exactly what to allow-list.
                $this->logger->exception_sse_friendly( "This site's wp-config.php is blocking outgoing HTTP requests (WP_HTTP_BLOCK_EXTERNAL) and has not allow-listed the Let's Encrypt™ ACME API host. Add 'acme-v02.api.letsencrypt.org' and 'acme-staging-v02.api.letsencrypt.org' to WP_ACCESSIBLE_HOSTS in wp-config.php (comma-separated with any hosts already listed), then try again.", __FILE__, __LINE__ );
            } else {
                $this->logger->exception_sse_friendly( 'HTTP request error: ' . $response->get_error_message(), __FILE__, __LINE__ );
            }
        }
        $this->lastHeader = $this->buildHeaderString( $response );
        $this->lastCode = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        $result = ( null === $data ? $body : $data );
        if ( is_array( $result ) ) {
            $result['curlinfo_http_code'] = $this->lastCode;
        }
        //Log every Let's Encrypt server response (regardless of the HTTP status code) in an AI-agent friendly way. @since 4.7.1
        if ( get_option( 'aifs_log_all_ca_server_response_array_json' ) ) {
            $this->log_response_array_json__ai_friendly( $result, $request_url, $method );
        }
        //Valid / expected status code
        $expected_status_codes = array(
            200,
            201,
            202,
            204
        );
        //Check status code
        if ( !\in_array( $this->lastCode, $expected_status_codes, true ) ) {
            if ( !$this->isAcmeProblemDocument( $result ) ) {
                $this->invalidateDirectoryCache();
            }
            // Prefer ACME "detail" when present for clearer troubleshooting.
            if ( \is_array( $result ) && !empty( $result['detail'] ) ) {
                $this->logger->log_v2( 'error', sprintf( "Sorry, the Let's Encrypt™ server response (%d) is unexpected: %s", $this->lastCode, $result['detail'] ) );
            } else {
                $this->logger->log_v2( 'error', sprintf( "Sorry, the Let's Encrypt™ server response (%d) is unexpected. The complete server response is given below.", $this->lastCode ) );
                //since 3.6.1, Don't translate this error message.
            }
            if ( $this->logger->is_cli() ) {
                $this->logger->log_v2( 'error', print_r( $result, true ) );
                // Throw instead of die()'ing the whole CLI cron process on the first bad ACME
                // response -- FreeSSLAuto::run()'s per-domain try/catch can then keep going with
                // the remaining domains instead of the entire run being killed here.
                $this->logger->exception_sse_friendly(
                    "Closing the connection",
                    __FILE__,
                    __LINE__,
                    false
                );
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
     * Build a raw header string from a wp_remote_* response.
     *
     * @param array|\WP_Error $response HTTP response.
     *
     * @return string
     */
    private function buildHeaderString( $response ) {
        if ( is_wp_error( $response ) ) {
            return '';
        }
        $headers = wp_remote_retrieve_headers( $response );
        $lines = array();
        if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
            foreach ( $headers->getAll() as $name => $values ) {
                foreach ( (array) $values as $value ) {
                    $lines[] = $name . ': ' . $value;
                }
            }
        } elseif ( is_array( $headers ) ) {
            foreach ( $headers as $name => $value ) {
                foreach ( (array) $value as $single ) {
                    $lines[] = $name . ': ' . $single;
                }
            }
        }
        if ( empty( $lines ) ) {
            return '';
        }
        return implode( "\r\n", $lines ) . "\r\n";
    }

    /**
     * Determine whether a response body is a well-formed ACME problem document.
     *
     * @param mixed $result Response body.
     *
     * @return bool
     */
    private function isAcmeProblemDocument( $result ) {
        return is_array( $result ) && isset( $result['type'], $result['detail'] ) && is_string( $result['type'] ) && is_string( $result['detail'] );
    }

    /**
     * Determine whether a decoded response body is a valid ACME directory document.
     *
     * @param mixed $result Response body.
     *
     * @return bool
     */
    private function isDirectoryDocument( $result ) {
        $expected_status_codes = array(
            200,
            201,
            202,
            204
        );
        if ( !is_array( $result ) || $this->isAcmeProblemDocument( $result ) ) {
            return false;
        }
        if ( !\in_array( $this->lastCode, $expected_status_codes, true ) ) {
            return false;
        }
        return isset( $result['newOrder'] ) || isset( $result['newNonce'] ) || isset( $result['newAccount'] );
    }

    /**
     * Clear the cached directory response.
     *
     * @return void
     */
    private function invalidateDirectoryCache() {
        $this->directoryCache = null;
    }

    /**
     * Fetch and cache the ACME directory document.
     *
     * @return array|null Cached directory map, or null when unavailable.
     */
    private function getDirectory() {
        if ( is_array( $this->directoryCache ) ) {
            return $this->directoryCache;
        }
        $dir_array = $this->curl( 'GET', $this->base . '/directory' );
        if ( $this->isDirectoryDocument( $dir_array ) ) {
            $this->directoryCache = $dir_array;
        } else {
            $this->invalidateDirectoryCache();
        }
        return $this->directoryCache;
    }

    /**
     * Log the complete Let's Encrypt™ server response in an AI-agent friendly format.
     *
     * The response is wrapped inside a uniquely named pair of markers so that any AI model
     * (or a script) reading the raw log file can reliably detect and extract the response:
     *
     * - `[[AIFS_CA_RESPONSE_JSON_FORMAT_START]]` / `[[AIFS_CA_RESPONSE_JSON_FORMAT_END]]` when the
     *   log payload can be json_encode()d successfully.
     * - `[[AIFS_CA_RESPONSE_ARRAY_FORMAT_START]]` / `[[AIFS_CA_RESPONSE_ARRAY_FORMAT_END]]` when
     *   json_encode() fails (e.g. malformed UTF-8).
     *
     * Response headers, HTTP method, and endpoint URL are attached here for logging only (not added
     * to the returned `$result`). The endpoint URL and HTTP method are passed in explicitly from
     * `curl()`; response headers are read from `$this->lastHeader`.
     *
     * @since 4.7.1
     *
     * @param mixed  $result      The decoded (array) or raw (string) Let's Encrypt server response body.
     * @param string $request_url The resolved ACME endpoint URL that was called.
     * @param string $method      The HTTP method used for the request (e.g. GET, POST).
     *
     * @return void
     */
    private function log_response_array_json__ai_friendly( $result, $request_url, $method ) {
        if ( is_array( $result ) ) {
            $log_payload = $result;
        } else {
            $log_payload = array(
                'curlinfo_http_code' => $this->lastCode,
                'response_body'      => $result,
            );
        }
        $log_payload['acme_endpoint_url'] = $request_url;
        $log_payload['http_method'] = $method;
        $log_payload['response_headers'] = $this->lastHeader;
        $marker_start = '[[AIFS_CA_RESPONSE_JSON_FORMAT_START]]';
        $marker_end = '[[AIFS_CA_RESPONSE_JSON_FORMAT_END]]';
        $json_format = json_encode( $log_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        if ( false === $json_format ) {
            $response_body = '/* json_encode failed: ' . json_last_error_msg() . ' */' . PHP_EOL . print_r( $log_payload, true );
            $marker_start = '[[AIFS_CA_RESPONSE_ARRAY_FORMAT_START]]';
            $marker_end = '[[AIFS_CA_RESPONSE_ARRAY_FORMAT_END]]';
        } else {
            $response_body = $json_format;
        }
        $ai_friendly_log = $marker_start . "\n" . $response_body . "\n" . $marker_end;
        $message = sprintf( "Let's Encrypt™ server response (%d), logged below for troubleshooting", $this->lastCode ) . ": <br />\n" . '<pre>' . $ai_friendly_log . '</pre>';
        //since 4.7.1, Don't translate this log message.
        $this->logger->log_v2( 'info', $message );
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
     * Get Let's Encrypt API URLs from the ACME directory document.
     *
     * @param string $key Directory key (e.g. newOrder, newNonce, newAccount).
     *
     * @return string|null Endpoint URL, or null when the directory is unavailable.
     */
    public function getUrl( $key ) {
        $dir_array = $this->getDirectory();
        if ( !is_array( $dir_array ) || !isset( $dir_array[$key] ) ) {
            $this->logger->exception_sse_friendly( 'ACME directory is unavailable or missing key: ' . $key, __FILE__, __LINE__ );
            return null;
        }
        return $dir_array[$key];
    }

    /**
     *
     *
     * @return string|null
     */
    public function getLastLocation() {
        if ( !is_null( $this->lastHeader ) && preg_match( '~Location: (.+)~i', $this->lastHeader, $matches ) ) {
            return trim( $matches[1] );
        }
        return null;
    }

    /**
     *
     *
     * @return int|null Null when no request has been dispatched yet, or the last
     *                   dispatched request's result was invalidated via invalidateLastCode().
     */
    public function getLastCode() {
        return $this->lastCode;
    }

    /**
     * Clear the last HTTP status code.
     *
     * Callers that abort a signed request before dispatching it (e.g. because no nonce could
     * be obtained) must call this so getLastCode() cannot be mistaken for the outcome of the
     * request that was never sent -- it would otherwise still report whatever code the last
     * unrelated HTTP call (such as a nonce-fetch retry) happened to receive.
     *
     * @return void
     */
    public function invalidateLastCode() {
        $this->lastCode = null;
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
