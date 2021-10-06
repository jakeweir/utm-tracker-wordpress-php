<?php

/**
 * Plugin Name: Webara UTM Tracker
 * Description: Use cookies to track URIs containing UTM parameters to build a history of which ads contributed to a sale.
 * Author: Jake Weir
 * Author URI: https://webara.co.uk
 * Version: 1.1.0
 * Text Domain: webara-utm-tracker
 *  
 */

if (!defined('ABSPATH')) 
{
    echo "These aren't the droids you're looking for...";
    exit;
}

if (!class_exists('WTC_UTM_Plugin')) 
{
    class WTC_UTM_Plugin
    {
        public function __construct()
        {
            add_action('init', array($this, 'init'), 10, 0);
            add_action('admin_print_styles', array($this, 'wbr_utm_user_scripts'));
            add_action('woocommerce_thankyou', array($this, 'save_utm_as_meta'), 20, 1);
            add_action('load-post.php', array($this, 'setup_order_meta_box'));
            add_action('woocommerce_email_order_meta', array($this, 'show_ad_history_in_admin_email'), 10, 2);
            
        }

        function init()
        {
            $cookie_value = $this->get_URI();
            $cookie_count = $this->get_cookie_count();
            $cookie_prefix = "wbr_ad_seen_";
            $cookie_name = $cookie_prefix . $cookie_count;

            if ($this->check_uri_for_utm_params($cookie_value) === 1) 
            {
                $cookie = new WTC_UTM_Cookie($cookie_name, $cookie_value);
                header("refresh: 0.5; url='" . home_url() . "'");
            }
        }

        function get_cookie_count()
        {
            $cookie_count = 0;
            foreach ($_COOKIE as $name => $value) 
            {
                if (strpos($name, 'wbr_ad_seen_') === 0) 
                {
                    $cookie_count++;
                }
            }
            return $cookie_count;
        }

        function check_uri_for_utm_params(string $uri): int
        {
            $pattern = "/utm_/i";
            if (preg_match($pattern, $uri) === 1) 
            {
                return 1;
            }
            return 0;
        }

        function get_URI()
        {
            global $wp;
            $uri = add_query_arg($wp->query_vars, '');
            return esc_url_raw($uri);
        }

        function create_cookie_jar()
        {
            $cookie_jar = array();
            foreach ($_COOKIE as $name => $value) 
            {
                if (strpos($name, 'wbr_ad_seen_') === 0) {
                    $cookie_jar[$name] = esc_url_raw($value);
                }
            }
            return $cookie_jar;
        }

        function get_params_from_cookie_jar($cookie_jar)
        {
            $utm_params = array();
            foreach ($cookie_jar as $cookie => $value) 
            {
                parse_str(strpbrk($value, "utm_"), $new_value);
                $utm_params[$cookie] = $new_value;
            }
            return $utm_params;
        }

        function setup_order_meta_box()
        {
            add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        }

        function add_order_meta_box()
        {
            add_meta_box('wtc-utm-ad-history', esc_html__('Ad History', 'woocommerce'), array($this, 'populate_ad_history_meta_box'), 'shop_order', 'side', 'core');
        }

        function save_utm_as_meta($order_id)
        {
            $cookies = $this->create_cookie_jar();
            $meta = $this->get_params_from_cookie_jar($cookies);
            $order = wc_get_order($order_id);
            $order->update_meta_data('_wtc_utm_ad_history', $meta);
            $order->save();
        }

        function wbr_utm_user_scripts()
        {
            $plugin_url = plugin_dir_url(__FILE__);
            wp_enqueue_style('wbr-ad-history-order-metabox.css',  $plugin_url . "/css/wbr-ad-history-order-metabox.css");
        }

        function show_ad_history_in_admin_email( $order, $sent_to_admin) 
        {
            if( $sent_to_admin )
            {
                $meta_data = $order->get_meta('_wtc_utm_ad_history', 'false');
                $ad_count = 1;
                echo "<h2>Advert History</h2>";

                if (is_array($meta_data) || is_object($meta_data)) 
                {
                    foreach ($meta_data as $cookie_name => $values) 
                    {
                        echo "<h4>Advert ".$ad_count."</h4>";
                        foreach ($values as $param => $value) 
                        {
                            echo "<p style='display:inline;'><strong>" . ucfirst(esc_html__((substr($param, 4)))) . " : </strong></p>" . "<p style='display:inline;'>" . esc_html__($value) . "</p>" . " | " ; 
                        }
                        $ad_count++;
                    }
                } 
                else 
                {
                    $message_no_history_found = esc_html__("This order does not contain any advert history");
                    echo $message_no_history_found."<br>";
                }
                echo "<br><br>";
            }
        }

        function populate_ad_history_meta_box($order_id)
        {
            $order = wc_get_order($order_id);
            $meta_data = $order->get_meta('_wtc_utm_ad_history', 'false');
            $message_before_count = "This order was placed after the following ";
            $message_after_count = " ad(s) directed your customer to your site.<br><br>The ads are numbered in the order they were encountered.";
            $ad_count = 1;


            if (is_array($meta_data) || is_object($meta_data)) 
            {
                echo __($message_before_count) . esc_html__(sizeof($meta_data)) . __($message_after_count) . "<br><br>";
                foreach ($meta_data as $cookie_name => $values) 
                {
                    echo "<div class='wbr-utm-metabox-ad-title'>Advert "  . $ad_count . "</div>";
                    foreach ($values as $param => $value) 
                    {
                        echo "<p class='wbr-utm-parameter-name'>" . ucfirst(esc_html__((substr($param, 4)))) . " : " . "</p><p class='wbr-utm-parameter-value'>" . esc_html__($value) . "</p><br>";
                    }
                    echo "<br>";
                    $ad_count++;
                }
            } 
            else 
            {
                $message_no_history_found = esc_html__("This order does not contain any advert history");
                echo $message_no_history_found;
            }
        }
    }
    
}

if (!class_exists('WTC_UTM_Cookie')) 
{
    class WTC_UTM_Cookie
    {
        public function __construct($cookie_name = "wbr_ad_seen_", $cookie_value = "organic_traffic")
        {
            $this->create_new_cookie($cookie_name, $cookie_value);
        }

        function create_new_cookie($cookie_name, $cookie_value)
        {
            if (isset($_COOKIE[$cookie_name])) 
            {
                // ensure cookie with correct URL value is not overwritten  
            } 
            else 
            {
                setcookie($cookie_name, $cookie_value, time() + 84600 * 365, COOKIEPATH, COOKIE_DOMAIN, true, true);
            }
        }
    }
}

$wtc_utm_instance = new WTC_UTM_Plugin;