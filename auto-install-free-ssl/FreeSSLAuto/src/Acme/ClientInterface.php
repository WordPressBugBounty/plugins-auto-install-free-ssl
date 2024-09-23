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

interface ClientInterface {
    /**
     * Constructor.
     *
     * @param string $base the ACME API base all relative requests are sent to
     */
    public function __construct( $base );

    /**
     * Send a POST request.
     *
     * @param string $url  URL to post to
     * @param array  $data fields to sent via post
     *
     * @return array|string the parsed JSON response, raw response on error
     */
    public function post( $url, $data );

    /**
     * @param string $url URL to request via get
     *
     * @return array|string the parsed JSON response, raw response on error
     */
    public function get( $url );

    /**
     * Return the Location header of the last request.
     *
     * returns null if last request had no location header
     *
     * @return null|string
     */
    public function getLastLocation();

    /**
     * Return the HTTP status code of the last request.
     *
     * @return int
     */
    public function getLastCode();

}
