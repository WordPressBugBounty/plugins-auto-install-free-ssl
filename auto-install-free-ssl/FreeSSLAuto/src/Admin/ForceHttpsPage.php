<?php

/**
 * @package Auto-Install Free SSL
 * This package is a WordPress Plugin. It issues and installs free SSL certificates in cPanel shared hosting with complete automation.
 *
 * @author Free SSL Dot Tech <support@freessl.tech>
 * @copyright  Copyright (C) 2019-2024, Anindya Sundar Mandal
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @link       https://freessl.tech
 * @since      Class available since Release 3.0.8
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
 * Create a page that provides access to the Activate/Deactivate Force HTTPS option.
 *
 */
class ForceHttpsPage
{
	private static $instance; // @since 4.5.0, to keep track of its initialization
    public  $factory;

    /**
     * Start up
     * Private constructor to prevent multiple instantiations @since 4.5.0
     *
     * @param bool $register_hooks Whether to register admin hooks (pass false in unit tests).
     */
    private function __construct($register_hooks = true)
    {
	    if (!defined('ABSPATH')) {
		    die( "Access denied" );
	    }

        $this->factory =  new Factory();

        if ($register_hooks) {
            add_action('admin_menu', array($this, 'force_https_page_menu' ));
        }
    }

	/**
     * This method ensures that only one instance of the class is created.
     * @since 4.5.0
	 * @param bool $register_hooks Whether to register hooks on first construction.
	 * @return ForceHttpsPage
	 */
	public static function getInstance($register_hooks = true) {
		if (!isset(self::$instance)) {
			// When hooked to do_action( 'init' ) with accepted_args >= 1, WP may pass null.
			if ( null === $register_hooks ) {
				$register_hooks = true;
			}
			self::$instance = new self( (bool) $register_hooks );
		}
		return self::$instance;
	}

	/**
	 * Inject a custom admin factory (used by unit tests).
	 *
	 * @param Factory $factory Factory instance.
	 */
	public function setFactory($factory)
	{
		$this->factory = $factory;
	}


    /**
     *
     * Add the sub menu
     */
    public function force_https_page_menu()
    {
        add_submenu_page('auto_install_free_ssl', __("Force HTTPS Page", 'auto-install-free-ssl'), __("Force HTTPS", 'auto-install-free-ssl'), 'manage_options', 'aifs_force_https', array( $this, 'force_https_admin_page'));

        if(aifs_is_free_version()){
	        $link = menu_page_url( 'auto_install_free_ssl', false ) . "&comparison=yes";
	        aifssl_fs()->add_submenu_link_item( __( "Free vs. Premium", 'auto-install-free-ssl' ), $link, 'free-vs-premium' );
        }

        if((!aifs_is_free_version() || (aifssl_fs()->get_user() !== false && aifssl_fs()->get_user()->id == 5953244)) && !aifs_license_is_unlimited()) {
	        $menu_title = aifs_is_free_version() ? __( "Upgrade to Premium", 'auto-install-free-ssl' ) : __( "Upgrade License", 'auto-install-free-ssl' );
            aifssl_fs()->add_submenu_link_item( $menu_title, $this->factory->upgrade_url(false, "&checkout=true"), 'upgrade-license' );
        }
    }


    /**
     *
     * Activate/Deactivate Force HTTPS page callback
     */
    public function force_https_admin_page()
    {
	    //$forcehttps = new ForceSSL();
	    $forcehttps = ForceSSL::getInstance();

	    $override = isset($_GET['aifsaction']) && $_GET['aifsaction'] == "aifs_force_https_override" && isset($_GET['checked_ssl_manually']) && $_GET['checked_ssl_manually'] == "done" && isset($_GET['valid_ssl_installed']) && $_GET['valid_ssl_installed'] == "yes";
	    if($override){
		    // H-10: this manual troubleshooting override enabled Force HTTPS
		    // (rewriting siteurl/home) from three fixed $_GET values with no nonce
		    // at all -- a crafted link could CSRF any logged-in admin who opened it.
		    // The existing nonce check at aifs_force_ssl_controller() only covers the
		    // $_POST form path, not this GET override. current_user_can() is also
		    // already enforced by add_submenu_page()'s 'manage_options' capability,
		    // but is asserted again here for defence in depth / clarity.
		    if ( ! current_user_can( 'manage_options' ) ) {
			    wp_die( __( "Access denied", 'auto-install-free-ssl' ) );
		    }
		    check_admin_referer( 'aifs_force_https_override', 'aifsoverridenonce' );

		    $forcehttps->aifs_force_ssl_implement(1);
	    }

        ?>
        <div class="wrap">
            <?php
            //echo '<h1>'. __("SSL Log", 'auto-install-free-ssl'). ' : ' . AIFS_NAME .'</h1>';
            echo aifs_header();
            ?>
            <table style="width: 100%;">
                <tr>
			        <?php
			            echo $forcehttps->force_ssl_ui("", false, [], 5);
			        ?>

                </tr>
            </table>

            <div class="overlay"></div>
            <div class="spanner">
                <div class="loader"></div>
                <p class="loader_text"><?= __( "Processing, please wait ...", 'auto-install-free-ssl' ) ?></p>
            </div>

	        <?= aifs_powered_by() ?>
        </div>
        <br /><br />
<?php
    }
}
