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
use InvalidArgumentException;
class Factory {
    public $logger;

    public $countryCode;

    public $state;

    public $organization;

    public $certificatesBase;

    public $acme_version;

    public $is_staging;

    public function __construct( $certificatesBase, $acme_version, $is_staging ) {
        if ( !defined( 'ABSPATH' ) ) {
            die( __( "Access denied", 'auto-install-free-ssl' ) );
        }
        $this->certificatesBase = $certificatesBase;
        $this->acme_version = $acme_version;
        $this->is_staging = $is_staging;
        $this->logger = new Logger();
    }

    /**
     *
     *
     * Returns the private key.
     *
     * @param string $path
     *
     * @since 1.0.0
     *
     * @return string the certificate begin flag
     */
    public function readPrivateKey( $path ) {
        if ( false === ($key = openssl_pkey_get_private( 'file://' . $path )) ) {
            //throw new \RuntimeException(openssl_error_string());
            $this->logger->exception_sse_friendly( openssl_error_string(), __FILE__, __LINE__ );
        }
        return $key;
    }

    /**
     *
     *
     * Returns the certificate begin flag.
     *
     * @since 1.0.0
     *
     * @return string the certificate begin flag
     */
    public function get_cert_begin() {
        return '-----BEGIN CERTIFICATE-----';
    }

    /**
     *
     *
     * Returns the certificate end flag.
     *
     * @since 1.0.0
     *
     * @return string the certificate end flag
     */
    public function get_cert_end() {
        return '-----END CERTIFICATE-----';
    }

    /**
     *
     *
     *  Perse PEM from response body.
     *
     * @param string $body
     *
     * @return string
     */
    public function parsePemFromBody( $body ) {
        $pem = chunk_split( base64_encode( $body ), 64, "\n" );
        return "-----BEGIN CERTIFICATE-----\n" . $pem . "-----END CERTIFICATE-----\n";
    }

    /**
     *
     *
     * Returns the certificate Parent directory.
     *
     * @return string
     */
    public function getCertificatesDir() {
        $acme_dir_name = 'acme_v' . $this->acme_version;
        return ( $this->is_staging ? $this->certificatesBase . DS . $acme_dir_name . DS . 'staging' : $this->certificatesBase . DS . $acme_dir_name . DS . 'live' );
    }

    /**
     *
     *
     * Get the domain path.
     *
     * @param string $domain
     *
     * @return string
     */
    public function getDomainPath( $domain ) {
        //If www. found at the beginning, remove it
        if ( strpos( $domain, 'www.' ) !== false && strpos( $domain, 'www.' ) === 0 ) {
            $domain = substr( $domain, 4 );
        }
        return $this->getCertificatesDir() . DS . $domain . DS;
    }

    /**
     * Get the Confirmed and Domain Specific SSL certificate directory.
     * Returns false if no SSL certificate found.
     * @param $domains_array
     *
     * @return false|string
     * @since 3.6.0
     */
    public function getConfirmedSslDir( $domains_array ) {
        $certificates_directory = $this->getCertificatesDir();
        $verified_dir = false;
        foreach ( $domains_array as $domain ) {
            if ( is_file( $certificates_directory . DS . $domain . DS . 'certificate.pem' ) ) {
                $verified_dir = $certificates_directory . DS . $domain . DS;
                break;
            }
        }
        return $verified_dir;
    }

    /**
     *
     *
     * Get the CSR content from path.
     *
     * @param string $csrPath
     *
     * @return string
     */
    public function getCsrContent( $csrPath ) {
        $csr = file_get_contents( $csrPath );
        preg_match( '~REQUEST-----(.*)-----END~s', $csr, $matches );
        return trim( Base64UrlSafeEncoder::encode( base64_decode( $matches[1], true ) ) );
    }

    /**
     *
     *
     * Generate key pair.
     *
     * @param string $outputDirectory
     * @param int    $key_size
     *
     * @throws \RuntimeException
     */
    public function generateKey( $outputDirectory, $key_size ) {
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => $key_size,
        ];
        if ( aifs_is_os_windows() ) {
            $config['config'] = __DIR__ . DS . 'openssl.cnf';
        }
        $res = openssl_pkey_new( $config );
        if ( $res === false ) {
            //$error = __( "Could not generate key pair! Check your OpenSSL configuration. Got this OpenSSL Error: ", 'auto-install-free-ssl' ) . PHP_EOL;
            $error = "Could not generate key pair! Check your OpenSSL configuration. Got this OpenSSL Error: " . PHP_EOL;
            //since 3.6.1, Don't translate exception message.
            while ( $message = openssl_error_string() ) {
                $error .= $message . PHP_EOL;
            }
            //throw new \RuntimeException($error);
            $this->logger->exception_sse_friendly( $error, __FILE__, __LINE__ );
        }
        if ( !openssl_pkey_export(
            $res,
            $privateKey,
            null,
            $config
        ) ) {
            //throw new \RuntimeException("Key export failed!");
            //$error = __( "RSA keypair export failed!! Error: ", 'auto-install-free-ssl' ) . PHP_EOL;
            $error = "RSA keypair export failed!! Error: " . PHP_EOL;
            //since 3.6.1, Don't translate exception message.
            while ( $message = openssl_error_string() ) {
                $error .= $message . PHP_EOL;
            }
            $this->logger->exception_sse_friendly( $error, __FILE__, __LINE__ );
        }
        $details = openssl_pkey_get_details( $res );
        if ( !is_dir( $outputDirectory ) ) {
            @mkdir( $outputDirectory, 0700, true );
        }
        if ( !is_dir( $outputDirectory ) ) {
            //throw new \RuntimeException("Can't create directory ${outputDirectory}. Please manually create the directory in your certificate directory and set permission 0700 and try again.");
            /* translators: %s: A directory path */
            //$this->logger->exception_sse_friendly(sprintf(__("Can't create directory %s. Please manually create the directory in your certificate directory, set permission 0700, and try again.", 'auto-install-free-ssl'), $outputDirectory), __FILE__, __LINE__);
            $this->logger->exception_sse_friendly( sprintf( "Can't create directory %s. Please manually create the directory in your certificate directory, set permission 0700, and try again.", $outputDirectory ), __FILE__, __LINE__ );
            //since 3.6.1, Don't translate exception message.
        }
        file_put_contents( $outputDirectory . DS . 'private.pem', $privateKey );
        file_put_contents( $outputDirectory . DS . 'public.pem', $details['key'] );
    }

    /**
     *
     *
     * Generate SSL Certificate Signing Request (CSR).
     *
     * @param string $privateKey
     * @param array  $domains
     * @param int    $key_size
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    public function generateCSR( $privateKey, array $domains, $key_size ) {
        $domain = reset( $domains );
        $san = implode( ',', array_map( function ( $dns ) {
            return 'DNS:' . $dns;
        }, $domains ) );
        $tmpConf = tmpfile();
        $tmpConfMeta = stream_get_meta_data( $tmpConf );
        $tmpConfPath = $tmpConfMeta['uri'];
        // workaround to get SAN working
        fwrite( $tmpConf, 'HOME = .
RANDFILE = $ENV::HOME/.rnd
[ req ]
default_bits = ' . $key_size . '
default_keyfile = privkey.pem
distinguished_name = req_distinguished_name
req_extensions = v3_req
[ req_distinguished_name ]
countryName = Country Name (2 letter code)
[ v3_req ]
basicConstraints = CA:FALSE
subjectAltName = ' . $san . '
keyUsage = nonRepudiation, digitalSignature, keyEncipherment' );
        /**
         * @var Ambiguous
         */
        //The Distinguished Name or subject fields to be used in the certificate.
        $dn = [
            'CN' => $domain,
        ];
        if ( \strlen( $this->countryCode ) > 0 ) {
            $dn['C'] = $this->countryCode;
        }
        if ( \strlen( $this->state ) > 0 ) {
            $dn['ST'] = $this->state;
        }
        if ( \strlen( $this->organization ) > 0 ) {
            $dn['O'] = $this->organization;
        }
        $csr = openssl_csr_new( $dn, $privateKey, [
            'config'     => $tmpConfPath,
            'digest_alg' => 'sha256',
        ] );
        if ( !$csr ) {
            //throw new \RuntimeException("CSR couldn't be generated! ".openssl_error_string());
            //$this->logger->exception_sse_friendly(__( "CSR couldn't be generated!", 'auto-install-free-ssl' ) . " ". openssl_error_string(), __FILE__, __LINE__);
            $this->logger->exception_sse_friendly( "CSR couldn't be generated! " . openssl_error_string(), __FILE__, __LINE__ );
            //since 3.6.1, Don't translate exception message.
        }
        openssl_csr_export( $csr, $csr );
        fclose( $tmpConf );
        $csrPath = $this->getDomainPath( $domain ) . 'csr_last.csr';
        file_put_contents( $csrPath, $csr );
        return $this->getCsrContent( $csrPath );
    }

    /**
     *
     *
     * Compare and verify the content of Payload with the content of challenge URI
     *
     * WP only function
     *
     * @param string $uri
     * @param string $payload
     * @return boolean
     *
     * @since 2.1.0
     */
    public function verify_internally_http_wp( $payload, $uri ) {
        $args = array(
            'sslverify' => false,
        );
        $remote_get = wp_remote_get( $uri, $args );
        if ( is_wp_error( $remote_get ) ) {
            $response = 'error';
        } else {
            $response = trim( wp_remote_retrieve_body( $remote_get ) );
        }
        if ( trim( $payload ) === $response ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     *
     * Fix htaccess in the challenge parent directory (.well-known)
     *
     * WP only function
     *
     * @param string $dir_path
     * @return boolean
     *
     * @since 2.1.0
     */
    public function fix_htaccess_challenge_dir( $dir_path ) {
        //wp-load.php not including following file. May be insert_with_markers being called early.
        if ( !function_exists( 'insert_with_markers' ) ) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
        $htaccess_path = $dir_path . '/.htaccess';
        $rules = array(
            '<IfModule mod_rewrite.c>',
            'RewriteCond %{REQUEST_FILENAME} !.well-known/',
            'RewriteRule "(^|/)\\.(?!well-known)" - [F]',
            '</IfModule>'
        );
        $result = insert_with_markers( $htaccess_path, AIFS_NAME, $rules );
        return $result;
    }

}
