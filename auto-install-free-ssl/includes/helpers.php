<?php

/**
 * Pure helper functions shared by the plugin and PHPUnit bootstrap.
 *
 * @package Auto-Install Free SSL
 */
if ( !function_exists( 'aifs_is_os_windows' ) ) {
    /**
     * Detects if the OS is Windows.
     *
     * @return bool
     */
    function aifs_is_os_windows() {
        return strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN';
    }

}
if ( !function_exists( 'aifs_server_software' ) ) {
    /**
     * Detect server software (apache | nginx | ms-iis).
     *
     * @return false|string
     */
    function aifs_server_software() {
        $ss = ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( $_SERVER['SERVER_SOFTWARE'] ) : '' );
        if ( strpos( $ss, 'apache' ) !== false ) {
            return 'apache';
        }
        if ( strpos( $ss, 'nginx' ) !== false ) {
            return 'nginx';
        }
        if ( strpos( $ss, 'microsoft-iis' ) !== false || strpos( $ss, 'iis' ) !== false ) {
            return 'ms-iis';
        }
        return false;
    }

}
if ( !function_exists( 'aifs_is_loopback' ) ) {
    /**
     * Check whether an IP is loopback (IPv4 or IPv6).
     *
     * @param string $ip IP address.
     * @return bool
     */
    function aifs_is_loopback(  $ip  ) {
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            return strpos( $ip, '127.' ) !== false && strpos( $ip, '127.' ) === 0;
        } elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            $normalized = inet_ntop( inet_pton( $ip ) );
            return $normalized === '::1';
        }
        return false;
    }

}
if ( !function_exists( 'aifs_get_domain' ) ) {
    /**
     * Return the site host from WordPress.
     *
     * @param bool $remove_www Whether to strip www.
     * @return string
     */
    function aifs_get_domain(  $remove_www = true  ) {
        $site_url = ( function_exists( 'get_site_url' ) ? get_site_url() : home_url() );
        $site_url = parse_url( $site_url );
        $domain = ( isset( $site_url['host'] ) ? $site_url['host'] : 'example.com' );
        if ( $remove_www && strpos( $domain, 'www.' ) !== false && strpos( $domain, 'www.' ) === 0 ) {
            $domain = substr( $domain, 4 );
        }
        return $domain;
    }

}
if ( !function_exists( 'aifs_get_app_settings' ) ) {
    /**
     * Merge all plugin options into a single array.
     * Improved since 3.5.1
     *
     * @return array
     */
    function aifs_get_app_settings() {
        $basic_settings = get_option( 'basic_settings_auto_install_free_ssl' );
        if ( $basic_settings && is_array( $basic_settings ) ) {
            $app_settings = $basic_settings;
        } else {
            //return false;
            // M-4: this early return must still guarantee 'admin_email' is an array
            // -- see the normalization below, mirrored here for the early-exit path.
            return array(
                'admin_email' => array(),
            );
            // @todo - not tested - to fix this error: [30-Dec-2024 14:29:10 UTC] PHP Warning:  Trying to access array offset on value of type bool in /home/USERNAME/public_html/DIRECTORY_NAME/wp-content/plugins/auto-install-free-ssl-premium/FreeSSLAuto/src/Admin/Factory.php on line 1486
        }
        $cpanel_settings = get_option( 'cpanel_settings_auto_install_free_ssl' );
        if ( $cpanel_settings && is_array( $cpanel_settings ) ) {
            $app_settings = array_merge( $app_settings, $cpanel_settings );
        }
        $exclude_domains = get_option( 'exclude_domains_auto_install_free_ssl' );
        if ( $exclude_domains && is_array( $exclude_domains ) ) {
            $app_settings = array_merge( $app_settings, $exclude_domains );
        }
        $dns_provider = get_option( 'dns_provider_auto_install_free_ssl' );
        if ( $dns_provider && is_array( $dns_provider ) ) {
            $app_settings = array_merge( $app_settings, $dns_provider );
        }
        $all_domains = get_option( 'all_domains_auto_install_free_ssl' );
        if ( $all_domains && is_array( $all_domains ) ) {
            $app_settings = array_merge( $app_settings, $all_domains );
        }
        /*if ( get_option( 'domains_to_revoke_cert_auto_install_free_ssl' ) ) {
        			$app_settings = array_merge( $app_settings, get_option( 'domains_to_revoke_cert_auto_install_free_ssl' ) );
        		}*/
        $domains_revoke_cert = get_option( 'aifs_domains_to_revoke_cert' );
        if ( $domains_revoke_cert && is_array( $domains_revoke_cert ) ) {
            $app_settings = array_merge( $app_settings, $domains_revoke_cert );
        }
        // M-4: several call sites do in_array( $x, $app_settings['admin_email'] )
        // with no array guard of their own. When the option is unset the index is
        // null, and in_array()/implode() on null is a TypeError on PHP 8, breaking
        // every notification email. Guarantee this is always an array.
        if ( !isset( $app_settings['admin_email'] ) || !is_array( $app_settings['admin_email'] ) ) {
            $app_settings['admin_email'] = array();
        }
        return $app_settings;
    }

}
if ( !function_exists( 'aifs_ip_of_this_server' ) ) {
    /**
     * Get IPv4 or IPv6 of this server.
     * Attempts to return the public IP address for online servers and handles localhost too.
     * improved since 4.6.0
     *
     * @return mixed|string
     * @since 3.6.0
     */
    function aifs_ip_of_this_server() {
        // Step 1: Check SERVER_ADDR if it exists and is not a loopback address
        if ( isset( $_SERVER['SERVER_ADDR'] ) && !aifs_is_loopback( $_SERVER['SERVER_ADDR'] ) ) {
            return $_SERVER['SERVER_ADDR'];
        }
        $domain = aifs_get_domain( true );
        $factory = new \AutoInstallFreeSSL\FreeSSLAuto\Admin\Factory();
        if ( $factory->is_ip_address( $domain ) ) {
            return $domain;
        } else {
            // Get this website's address (domain name)
            $websiteAddress = aifs_get_domain( false );
            // Step 2: Try resolving the website address to an IP address
            $ip = gethostbyname( $websiteAddress );
            // Resolve hostname to IP (IPv4)
            // If the resolved IP is a valid IP and not a loopback address, return it
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) && !aifs_is_loopback( $ip ) ) {
                return $ip;
            }
            // Step 3: Try resolving the website address to an IP address using dns_get_record
            $records = dns_get_record( $websiteAddress, DNS_A | DNS_AAAA );
            if ( $records ) {
                foreach ( $records as $record ) {
                    if ( $record['type'] == 'A' ) {
                        $ip = $record['ip'];
                        // If the resolved IP is a valid IPV4 and not a loopback address, return it
                        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && !aifs_is_loopback( $ip ) ) {
                            return $ip;
                        }
                    } elseif ( $record['type'] == 'AAAA' ) {
                        $ip = $record['ipv6'];
                        // If the resolved IP is a valid IPV6 and not a loopback address, return it
                        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && !aifs_is_loopback( $ip ) ) {
                            return $ip;
                        }
                    }
                }
            }
            // Step 4: Fallback to SERVER_ADDR (loopback for localhost)
            //return $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';  // requires PHP 7.0 or later. But this plugin supports PHP 5.6
            return ( isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1' );
            //updated since 4.6.1
        }
    }

}
// --- Relocated from auto-install-free-ssl.php (WP_UnitTestCase migration) ---
if ( !function_exists( 'aifs_get_environment_errors' ) ) {
    /**
     * Get the environment errors to check if the environment is compatible with the plugin.
     *
     * @since 4.6.2
     * @return string[] Error codes (empty when compatible).
     */
    function aifs_get_environment_errors() {
        $errors = array();
        if ( !defined( 'PHP_VERSION_ID' ) || PHP_VERSION_ID < 50600 ) {
            $errors[] = 'php_version';
        }
        global $wp_version;
        if ( !empty( $wp_version ) && version_compare( $wp_version, '4.1', '<' ) ) {
            // @since 4.7.0 added WordPress version check and removed allow_url_fopen check
            $errors[] = 'wordpress_version';
        }
        if ( !extension_loaded( 'openssl' ) ) {
            $errors[] = 'openssl';
        }
        if ( !extension_loaded( 'curl' ) ) {
            $errors[] = 'curl';
        }
        return $errors;
    }

}
if ( !function_exists( 'aifs_wp_http_block_external_puts_acme_at_risk' ) ) {
    /**
     * Whether this site's own WP_HTTP_BLOCK_EXTERNAL setting is blocking outgoing ACME requests
     * without an accessible-hosts allow-list entry for the Let's Encrypt API host(s).
     *
     * This is deliberately NOT folded into aifs_get_environment_errors(): that function gates
     * plugin activation and hard-stops the admin UI, which would be wildly disproportionate here
     * -- Force HTTPS and every other plugin feature works fine regardless of this setting. This
     * is a soft, actionable warning surfaced via aifs_admin_notice_wp_http_block_external().
     *
     * Both parameters exist purely so unit tests can simulate either condition: once a
     * wp-config.php constant is defined() in a PHP process, it can never be changed or undefined
     * for the remainder of that process, so tests cannot toggle the real WP_HTTP_BLOCK_EXTERNAL /
     * WP_ACCESSIBLE_HOSTS constants between cases. Production callers should omit both and let
     * this read the real constants.
     *
     * @since 5.0.0
     * @param bool|null   $block_external   Override for WP_HTTP_BLOCK_EXTERNAL (null = read the real constant).
     * @param string|null $accessible_hosts Override for WP_ACCESSIBLE_HOSTS (null = read the real constant).
     * @return bool True when ACME issuance is at risk of being silently blocked.
     */
    function aifs_wp_http_block_external_puts_acme_at_risk(  $block_external = null, $accessible_hosts = null  ) {
        if ( null === $block_external ) {
            $block_external = defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL;
        }
        if ( !$block_external ) {
            return false;
        }
        if ( null === $accessible_hosts ) {
            $accessible_hosts = ( defined( 'WP_ACCESSIBLE_HOSTS' ) ? WP_ACCESSIBLE_HOSTS : null );
        }
        if ( null === $accessible_hosts || '' === trim( (string) $accessible_hosts ) ) {
            // No allow-list at all -- every external host, including the ACME API, is blocked.
            return true;
        }
        // Same two hosts named in Acme\Client::curl()'s reactive log message.
        $acme_hosts = array('acme-v02.api.letsencrypt.org', 'acme-staging-v02.api.letsencrypt.org');
        $allow_list = preg_split( '/,\\s*/', trim( (string) $accessible_hosts ) );
        foreach ( $acme_hosts as $acme_host ) {
            $allowed = false;
            foreach ( $allow_list as $allowed_host ) {
                $allowed_host = trim( $allowed_host );
                if ( '' === $allowed_host ) {
                    continue;
                }
                if ( false !== strpos( $allowed_host, '*' ) ) {
                    // Mirrors WP_Http::block_request()'s own wildcard-domain matching.
                    $pattern = '/^' . str_replace( '\\*', '.+', preg_quote( $allowed_host, '/' ) ) . '$/i';
                    if ( preg_match( $pattern, $acme_host ) ) {
                        $allowed = true;
                        break;
                    }
                } elseif ( 0 === strcasecmp( $allowed_host, $acme_host ) ) {
                    $allowed = true;
                    break;
                }
            }
            if ( !$allowed ) {
                // At least one ACME host isn't allow-listed -- issuance is at risk.
                return true;
            }
        }
        return false;
    }

}
if ( !function_exists( 'aifs_get_environment_error_messages' ) ) {
    /**
     * Translate environment error codes into user-facing messages.
     *
     * @since 4.7.1
     * @param string[]|null $errors Optional error codes; defaults to current environment errors.
     * @return string[]
     */
    function aifs_get_environment_error_messages(  $errors = null  ) {
        if ( null === $errors ) {
            $errors = aifs_get_environment_errors();
        }
        $messages = array();
        if ( in_array( 'php_version', $errors, true ) ) {
            $messages[] = __( 'You need at least PHP 5.6.0', 'auto-install-free-ssl' );
        }
        if ( in_array( 'wordpress_version', $errors, true ) ) {
            // @since 4.7.0 added WordPress version check error message and removed allow_url_fopen check
            $messages[] = __( 'You need WordPress 4.1 or later', 'auto-install-free-ssl' );
        }
        if ( in_array( 'openssl', $errors, true ) ) {
            $messages[] = __( 'You need OpenSSL extension enabled with PHP', 'auto-install-free-ssl' );
        }
        if ( in_array( 'curl', $errors, true ) ) {
            $messages[] = __( 'You need Curl extension enabled with PHP', 'auto-install-free-ssl' );
        }
        return $messages;
    }

}
if ( !function_exists( 'aifs_is_plugin_admin_page' ) ) {
    /**
     * Whether the current admin request is one of this plugin's screens.
     *
     * @since 4.7.1
     * @return bool
     */
    function aifs_is_plugin_admin_page() {
        if ( !isset( $_GET['page'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }
        $page = sanitize_key( wp_unslash( $_GET['page'] ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'auto_install_free_ssl' === $page ) {
            return true;
        }
        // All other plugin screens use the aifs_ prefix (Force HTTPS, Log, Generate SSL, etc.).
        return 0 === strpos( $page, 'aifs_' );
    }

}
if ( !function_exists( 'aifs_should_skip_environment_hard_stop' ) ) {
    /**
     * Whether a hard stop should be skipped so admins can still manage plugins
     * and reach this plugin's own screens (which show the environment-error
     * admin notice instead of dying).
     *
     * @since 4.7.1
     * @return bool
     */
    function aifs_should_skip_environment_hard_stop() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return true;
        }
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            return true;
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return true;
        }
        global $pagenow;
        if ( !empty( $pagenow ) ) {
            $escape_pages = array(
                'plugins.php',
                'plugin-install.php',
                'update.php',
                'update-core.php'
            );
            if ( in_array( $pagenow, $escape_pages, true ) ) {
                return true;
            }
        }
        // Keep plugin screens reachable (Settings, Force HTTPS, Log, etc.).
        if ( aifs_is_plugin_admin_page() ) {
            return true;
        }
        return false;
    }

}
if ( !function_exists( 'aifs_is_existing_user' ) ) {
    /**
     * Detects if the user using the plugin since the free only version
     * @return false|mixed|void
     */
    function aifs_is_existing_user() {
        return get_option( 'aifs_user_since_free_only_version', true );
    }

}
if ( !function_exists( 'aifs_user_since_free_only_version' ) ) {
    /**
     * Detects, if the user installed the plugin since free only version
     * @since 3.0.6
     */
    function aifs_user_since_free_only_version() {
        if ( get_option( 'basic_settings_auto_install_free_ssl' ) ) {
            $basic_settings_existing = get_option( 'basic_settings_auto_install_free_ssl' );
            /*if(empty($basic_settings_existing)){
            			return true;
            		}*/
            if ( is_array( $basic_settings_existing ) ) {
                $data = new AutoInstallFreeSSL\FreeSSLAuto\Admin\AutoDataEntry();
                $basic_settings_default_v3 = $data->basic_settings_default_v3();
                if ( $basic_settings_existing['use_wildcard'] != $basic_settings_default_v3['use_wildcard'] ) {
                    return true;
                } elseif ( $basic_settings_existing['is_staging'] != $basic_settings_default_v3['is_staging'] ) {
                    return true;
                } elseif ( $basic_settings_existing['country_code'] != $basic_settings_default_v3['country_code'] ) {
                    return true;
                } elseif ( $basic_settings_existing['state'] != $basic_settings_default_v3['state'] ) {
                    return true;
                } elseif ( $basic_settings_existing['organization'] != $basic_settings_default_v3['organization'] ) {
                    return true;
                } elseif ( $basic_settings_existing['certificate_directory'] != $basic_settings_default_v3['certificate_directory'] ) {
                    return true;
                } elseif ( $basic_settings_existing['days_before_expiry_to_renew_ssl'] != $basic_settings_default_v3['days_before_expiry_to_renew_ssl'] ) {
                    return true;
                } elseif ( $basic_settings_existing['using_cdn'] != $basic_settings_default_v3['using_cdn'] ) {
                    return true;
                } elseif ( $basic_settings_existing['key_size'] != $basic_settings_default_v3['key_size'] ) {
                    return true;
                } else {
                    return false;
                }
            }
            /*else{
            			return true; //User didn't configured, but installed v2
            		}*/
        }
        return false;
    }

}
if ( !function_exists( 'aifs_do_output_buffer' ) ) {
    /**
     * required for successful redirect
     */
    function aifs_do_output_buffer() {
        ob_start();
    }

}
if ( !function_exists( 'aifs_display_flash_notices' ) ) {
    /**
     * If there are admin notices in the option table, display them and remove from the option table to prevent them being displayed forever
     *
     * @since 2.0.0
     */
    function aifs_display_flash_notices() {
        $notices = get_option( 'aifs_flash_notices' );
        if ( $notices != false && is_array( $notices ) && count( $notices ) > 0 ) {
            // Iterate through the notices to display them, if exist in option table
            foreach ( $notices as $notice ) {
                $style = ( $notice['type'] == "success" ? 'style="color: #46b450;"' : '' );
                // L-7: escape every substitution -- $notice['type']/['dismissible'] are
                // normally internally-generated enum-like values and $style is a
                // hard-coded attribute clause (not user data, so left unescaped -- it's
                // not a plain attribute value and esc_attr() would corrupt it).
                // $notice['notice'] is free-form and may include dynamic content (e.g.
                // a domain name) -- but at least one call site (AcmeV2.php) intentionally
                // passes safe formatting markup (e.g. <span style="...">), same as
                // write_log()'s use of wp_kses_post() elsewhere in this codebase, so
                // wp_kses_post() is used here instead of esc_html() to strip dangerous
                // tags/attributes while preserving that intentional formatting.
                printf(
                    '<div class="notice notice-%1$s %2$s" %3$s><p>%4$s</p></div>',
                    esc_attr( $notice['type'] ),
                    esc_attr( $notice['dismissible'] ),
                    $style,
                    wp_kses_post( $notice['notice'] )
                );
            }
            // Now delete the option
            delete_option( 'aifs_flash_notices' );
        }
    }

}
if ( !function_exists( 'aifs_add_flash_notice' ) ) {
    /**
     * @param string $notice (The notice text)
     * @param string $type (This can be "success", "info", "warning", "error". "success" is default.)
     * @param boolean $is_dismissible (Set this TRUE to add is-dismissible functionality)
     *
     *
     * Add a flash notice to the options table which will be displayed upon page refresh or redirect
     *
     * @since 2.0.0
     */
    /* function aifs_add_flash_notice(string $notice, string $type = "success", bool $is_dismissible = true ) {
     * Removing parameter type hint to make compatible with PHP 5.6. Using scalar type hints like string is supported since PHP 7. */
    function aifs_add_flash_notice(  $notice, $type = "success", $is_dismissible = true  ) {
        // Get the notices already saved in the option table, if any, or return an empty array
        $notices = get_option( 'aifs_flash_notices', array() );
        $dismissible_text = ( $is_dismissible ? "is-dismissible" : "" );
        // Add the new notice
        array_push( $notices, array(
            "notice"      => $notice,
            "type"        => $type,
            "dismissible" => $dismissible_text,
        ) );
        // Now update the option with the notices
        update_option( 'aifs_flash_notices', $notices );
    }

}
if ( !function_exists( 'aifs_admin_first_name' ) ) {
    /**
     * Return first name of the WordPress Admin
     * @return string
     * @since 3.0.0
     */
    function aifs_admin_first_name() {
        $admin_email = get_option( 'admin_email' );
        $admin = get_user_by( 'email', $admin_email );
        if ( $admin !== false ) {
            return $admin->first_name;
        } else {
            return "";
        }
    }

}
if ( !function_exists( 'aifs_admin_last_name' ) ) {
    /**
     * Return last name of the WordPress Admin
     * @return string
     * @since 3.2.14
     */
    function aifs_admin_last_name() {
        $admin_email = get_option( 'admin_email' );
        $admin = get_user_by( 'email', $admin_email );
        if ( $admin !== false ) {
            return $admin->last_name;
        } else {
            return "";
        }
    }

}
if ( !function_exists( 'aifs_is_free_version' ) ) {
    /**
     * Check if the plugin is free version
     * @return bool
     * @since 3.0.0
     */
    function aifs_is_free_version() {
        return aifssl_fs()->is_free_plan();
    }

}
if ( !function_exists( 'aifs_can_manage_multi_domain' ) ) {
    /**
     * Check if licensed for unlimited domains (pro_unlimited plan) and user has set aifs_is_multi_domain = 1
     * @return bool
     * @since 3.0.0
     */
    function aifs_can_manage_multi_domain() {
        //For free version always return false
        return false;
    }

}
if ( !function_exists( 'aifs_use_wildcard' ) ) {
    /**
     * Check if the user can use wildcard SSL
     * @return bool
     * @since 3.2.15
     */
    function aifs_use_wildcard() {
        //For free version always return false
        return false;
    }

}
if ( !function_exists( 'aifs_license_is_unlimited' ) ) {
    /**
     * Check if the premium license is for unlimited websites
     * @return bool
     * @since 3.0.0
     */
    function aifs_license_is_unlimited() {
        //For free version always return false
        return false;
    }

}
if ( !function_exists( 'aifs_license_is_free_premium_six_months__premium_only' ) ) {
}
if ( !function_exists( 'aifs_powered_by_css_style' ) ) {
    /**
     * CSS style for Powered by text
     * @return string
     * @since 3.0.0
     */
    function aifs_powered_by_css_style() {
        global $wp_version;
        $version_parts = explode( ".", $wp_version );
        $version_base = (int) $version_parts[0];
        if ( $version_base === 5 || $version_base === 6 ) {
            $style = 'class="header-footer"';
        } else {
            $style = 'id="message" class="updated below-h2 header-footer"';
        }
        return $style;
    }

}
if ( !function_exists( 'aifs_header' ) ) {
    /**
     * Returns the header
     * @return string
     * @since 3.0.6
     */
    function aifs_header() {
        return '<h1 class="aifs-header" style=\'background-image: url("' . AIFS_URL . 'assets/img/icon.jpg"); background-color: #ffffff; margin: -3% -1.9% 3% -2%; padding: 2.1% 0; \'>
	            		<span style="margin-left: 14%; color: green;">' . AIFS_NAME . ' <sub style="color: gray; font-size: 0.65em;">' . AIFS_VERSION . '</sub></span> <span style="float: right; margin-right: 2%;"><a href="' . menu_page_url( 'aifs_force_https', false ) . '" class="button">' . __( "Force HTTPS", 'auto-install-free-ssl' ) . '</a></span>
	        		</h1>';
    }

}
if ( !function_exists( 'aifs_powered_by' ) ) {
    /**
     * Returns Powered by text
     * @return string
     * @since 3.0.0
     */
    function aifs_powered_by() {
        if ( aifssl_fs()->can_use_premium_code() ) {
            $help_link = aifssl_fs()->contact_url();
        } else {
            $help_link = "https://freessl.tech/free-ssl-certificate-for-wordpress-website/#help";
        }
        if ( aifs_is_free_version() ) {
            $documentation_link = "https://freessl.tech/wordpress-letsencrypt-free-ssl-certificate-documentation/?utm_source=users_website&utm_medium=dashboard&utm_campaign=aifs_free&utm_content=footer";
        } else {
            $documentation_link = "https://freessl.tech/free-ssl-certificate-for-wordpress-website/#documentation";
        }
        $review_link = "https://wordpress.org/support/plugin/auto-install-free-ssl/reviews/?filter=5#new-post";
        $html = '<div ' . aifs_powered_by_css_style() . ' style="margin-top: 4%;">
	            <p>' . __( "Need Help?", 'auto-install-free-ssl' ) . ' <a href="' . $help_link . '" target="_blank">' . __( "click here", 'auto-install-free-ssl' ) . '</a> <span style="margin-left: 15%;">' . __( "For documentation", 'auto-install-free-ssl' ) . ', <a href="' . $documentation_link . '" target="_blank">' . __( "click here", 'auto-install-free-ssl' ) . '</a>.</span> ';
        if ( get_option( 'aifs_display_review' ) !== false ) {
            /* translators: %1$s: Opening HTML 'a' tag; %2$s: Closing 'a' tag; (Opening and closing 'a' tags create a hyperlink with the enclosed text.) */
            $html .= '<span style="float: right; margin-right: 2%;">' . sprintf( __( 'Please rate us %1$s★★★★★%2$s on %1$sWordPress.org%2$s to help us spread the word.', 'auto-install-free-ssl' ), '<a href="' . $review_link . '" target="_blank">', '</a>' ) . '</span>';
        }
        $html .= '</p>
	        	</div>';
        return $html;
    }

}
if ( !function_exists( 'aifs_get_ca_terms_of_service_url' ) ) {
    /**
     * Get the CA termsOfService URL
     * @return mixed|string
     * @since 4.3.0
     */
    function aifs_get_ca_terms_of_service_url() {
        // Fetch the option from the WordPress database
        $tos = get_option( 'aifs_ca_terms_of_service_url' );
        $current_time = time();
        $two_days_ago = $current_time - 2 * 24 * 60 * 60;
        // 2 days ago in seconds
        // Check if the option exists and 'last_updated' is greater than two days ago
        if ( $tos && is_array( $tos ) && isset( $tos['url'] ) && isset( $tos['last_updated'] ) && $tos['last_updated'] >= $two_days_ago ) {
            return $tos['url'];
        } else {
            // If no option exists or it's older than 2 days, update the option
            $client = new \AutoInstallFreeSSL\FreeSSLAuto\Acme\Client(AIFS_LE_ACME_V2_LIVE);
            $terms_url = $client->getTermsOfService();
            // Update the option in the database
            $tos_data = array(
                'url'          => $terms_url,
                'last_updated' => $current_time,
            );
            update_option( 'aifs_ca_terms_of_service_url', $tos_data, false );
            // Return the updated URL
            return $terms_url;
        }
    }

}
if ( !function_exists( 'aifs_this_plugin_activation_count' ) ) {
    /**
     * Get this plugin's activation count (free and premium version) to find out whether both version is active.
     * @return int
     * @since 4.5.0
     */
    function aifs_this_plugin_activation_count() {
        // L-9: is_plugin_active() lives in wp-admin/includes/plugin.php, which
        // isn't autoloaded on the frontend. In practice the plugin's bootstrap
        // requires it before any real call site runs, but this defensive
        // require_once keeps this function (like the rest of includes/helpers.php)
        // safe to load standalone, per CLAUDE.md.
        if ( !function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $count = 0;
        if ( is_plugin_active( AIFS_FREE_PLUGIN_BASENAME ) ) {
            $count++;
        }
        if ( is_plugin_active( AIFS_PREMIUM_PLUGIN_BASENAME ) ) {
            $count++;
        }
        return $count;
    }

}
if ( !function_exists( 'aifs_this_plugin_installation_count' ) ) {
    /**
     * Get this plugin's installation count (free and premium version) to find out whether both version is installed (both active and inactive).
     * @return int
     * @since 4.5.0
     */
    function aifs_this_plugin_installation_count() {
        // L-9: get_plugins() lives in wp-admin/includes/plugin.php, which isn't
        // autoloaded on the frontend -- see aifs_this_plugin_activation_count().
        if ( !function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $count = 0;
        // Get the list of all installed plugins (both active and inactive)
        $installed_plugins = get_plugins();
        if ( isset( $installed_plugins[AIFS_FREE_PLUGIN_BASENAME] ) ) {
            $count++;
        }
        if ( isset( $installed_plugins[AIFS_PREMIUM_PLUGIN_BASENAME] ) ) {
            $count++;
        }
        return $count;
    }

}
if ( !function_exists( 'aifs_random_bytes' ) ) {
    /**
     * Cryptographically secure random bytes (random_bytes() compatible).
     *
     * Prefers native random_bytes() on PHP 7+, then OpenSSL with a strong-CSPRNG
     * check. Never falls back to mt_rand() — callers use this for IVs and tokens.
     *
     * @param int $length Number of bytes.
     * @return string
     * @throws Exception when a CSPRNG is unavailable or returns weak bytes.
     */
    function aifs_random_bytes(  $length  ) {
        $length = (int) $length;
        if ( $length < 1 ) {
            throw new Exception('Length must be greater than 0');
        }
        if ( function_exists( 'random_bytes' ) ) {
            // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.random_bytesFound -- guarded by function_exists() above; only reached on PHP 7+.
            return random_bytes( $length );
        }
        if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes( $length, $strong );
            if ( false !== $bytes && $strong && strlen( $bytes ) === $length ) {
                return $bytes;
            }
        }
        throw new Exception('Could not generate cryptographically secure random bytes. ' . 'Enable the OpenSSL extension with a working CSPRNG (required on PHP 5.6).');
    }

}
if ( !function_exists( 'aifs_cron_request_authorized' ) ) {
    /**
     * M-16: cron.php is reachable over HTTP with no token/capability check of its
     * own. This is the (backward-compatible) authorization check for that HTTP
     * path: unauthenticated unless an admin has explicitly configured a secret
     * (an AIFS_CRON_SECRET constant, or one generated via the Add Cron Job admin
     * screen), in which case $_GET['aifs_cron_key'] must match it. Never applies
     * to a CLI invocation (`php -q cron.php`, which has no $_GET at all) -- an
     * existing system-cron entry keeps working even after a secret is configured.
     *
     * @param bool|null $is_cli Test-only override for the PHP_SAPI check (pass
     *                          false to exercise the HTTP-request code path from
     *                          a CLI-run test suite). Leave null in production.
     * @return bool
     */
    function aifs_cron_request_authorized(  $is_cli = null  ) {
        $is_cli = ( null === $is_cli ? 'cli' === PHP_SAPI : $is_cli );
        if ( $is_cli ) {
            return true;
        }
        $configured_secret = ( defined( 'AIFS_CRON_SECRET' ) ? AIFS_CRON_SECRET : get_option( 'aifs_cron_secret', '' ) );
        if ( empty( $configured_secret ) ) {
            return true;
        }
        $provided_key = ( isset( $_GET['aifs_cron_key'] ) ? (string) $_GET['aifs_cron_key'] : '' );
        return '' !== $provided_key && hash_equals( (string) $configured_secret, $provided_key );
    }

}
if ( !function_exists( 'aifs_cpanel_effective_auth_method' ) ) {
    /**
     * Determine which credential (password vs API token) cPanel::configureUapiCurlAuth() would
     * use for the given stored app settings, without needing a cPanel instance. Mirrors that
     * method's precedence exactly. Unlike configureUapiCurlAuth(), this works on the RAW
     * (possibly still-encrypted) settings array -- it only checks non-emptiness, since we only
     * need to know which credential is CONFIGURED, not decrypt it.
     *
     * @since 5.0.0
     * @param array $app_settings Stored cPanel app settings (e.g. from aifs_get_app_settings(),
     *                            or the 'cpanel_settings_auto_install_free_ssl' option), with
     *                            'password', 'api_token' and/or 'auth_method' keys.
     * @return string 'password' | 'api_token' | ''
     */
    function aifs_cpanel_effective_auth_method(  $app_settings  ) {
        if ( isset( $app_settings['auth_method'] ) && 'password' === $app_settings['auth_method'] && !empty( $app_settings['password'] ) ) {
            return 'password';
        }
        if ( !empty( $app_settings['api_token'] ) ) {
            return 'api_token';
        }
        if ( !empty( $app_settings['password'] ) ) {
            return 'password';
        }
        return '';
    }

}
if ( !function_exists( 'aifs_acquire_cron_run_lock' ) ) {
    /**
     * M-16: an unconditional short-window run lock (independent of the secret
     * above) so repeated hits to cron.php -- accidental or malicious -- can't
     * stack concurrent issuance runs. Always active; blunts the resource-
     * exhaustion vector without requiring any admin action.
     *
     * @param int $ttl_seconds Lock duration in seconds.
     * @return bool True if the lock was acquired (no run currently in progress).
     */
    function aifs_acquire_cron_run_lock(  $ttl_seconds = 60  ) {
        if ( get_transient( 'aifs_cron_running_lock' ) ) {
            return false;
        }
        set_transient( 'aifs_cron_running_lock', 1, $ttl_seconds );
        return true;
    }

}