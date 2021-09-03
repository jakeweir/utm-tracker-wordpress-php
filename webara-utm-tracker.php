<?php
/**
 * Plugin Name: Webara UTM Tracker
 * Description: Utilise cookies to identify which Facebook Ads have resulted in a purchase.
 * Author: Jake Weir
 * Author URI: https://webara.co.uk
 * Version: 1.0.0
 * Text Domain: webara-utm-tracker
 *  
 */

// Exit if accessed directly 
if ( !defined( 'ABSPATH' ) )
{
    echo "These aren't the droids you're looking for...";
    exit;
}

if ( !class_exists( 'WebaraTrackingCookie') )
{
    class WebaraTrackingCookie
    {
        public function __construct()
        {
            add_action('init', array($this, 'create_cookie'));
        }

        public function create_cookie()
        {
            global $wp;
            $firstvisit = "first_visit";
            $nextcookie = "with-esc-html";
            $uri = esc_html(add_query_arg( $wp->query_vars, home_url( $wp->request ) ));

            if (isset($_COOKIE[$firstvisit])) 
            {
                
            }
            else
            {
                setcookie($firstvisit, $uri, time() + 84600 * 365, COOKIEPATH, COOKIE_DOMAIN, $secure = true, $httponly = true);
            }
        }


    }

}

new WebaraTrackingCookie;
