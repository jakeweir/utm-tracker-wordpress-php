<?php

/**
 * Plugin Name: Webara UTM Tracker
 * Description: Use cookies to track URIs containing UTM parameters to build a history of which ads contributed to a sale.
 * Author: Jake Weir
 * Author URI: https://webara.co.uk
 * Version: 1.0.0
 * Text Domain: webara-utm-tracker
 *  
 */

if (!defined('ABSPATH')) {
    echo "These aren't the droids you're looking for...";
    exit;
}

if (!class_exists('WTC_UTM_Plugin')) {
    class WTC_UTM_Plugin
    {
        public function __construct()
        {
            add_action('init', array($this, 'init'), 10, 0);
            add_action('woocommerce_thankyou', array($this, 'save_utm_as_meta'), 20, 1);
            add_action('load-post.php', array($this, 'setup_order_meta_box'));
        }

        function init()
        {
            $cookie_value = $this->get_URI();
            $cookie_count = $this->get_cookie_count();
            $cookie_prefix = "wbr_ad_seen_";
            $cookie_name = $cookie_prefix . $cookie_count;

            if ($this->check_uri_for_utm_params($cookie_value) === 1) {
                $cookie = new WTC_UTM_Cookie($cookie_name, $cookie_value);
                header("refresh: 0.5; url='" . home_url() . "'");
            }
        }

        function get_cookie_count()
        {
            $cookie_count = 0;
            foreach ($_COOKIE as $name => $value) {
                if (strpos($name, 'wbr_ad_seen_') === 0) {
                    $cookie_count++;
                }
            }
            return $cookie_count;
        }

        function check_uri_for_utm_params(string $uri): int
        {
            $pattern = "/utm_/i";
            if (preg_match($pattern, $uri) === 1) {
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
            foreach ($_COOKIE as $name => $value) {
                if (strpos($name, 'wbr_ad_seen_') === 0) {
                    $cookie_jar[$name] = esc_url_raw($value);
                }
            }
            return $cookie_jar;
        }

        function get_params_from_cookie_jar($cookie_jar)
        {
            $utm_params = array();
            foreach ($cookie_jar as $cookie => $value) {
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

        function populate_ad_history_meta_box($order_id)
        {
            $order = wc_get_order($order_id);
            $meta_data = $order->get_meta('_wtc_utm_ad_history', 'false');

            if (is_array($meta_data) || is_object($meta_data)) {
                foreach ($meta_data as $cookie_name => $values) {
                    echo "Advert: " . esc_html($cookie_name) . "<br><br>";
                    foreach ($values as $param => $value) {
                        echo esc_html($param) . " : " . esc_html($value) . "<br>";
                    }
                    echo "<br>";
                }
            } else {
                $message = esc_html__("This order does not contain any advert history");
                echo $message;
            }
        }
    }
}

if (!class_exists('WTC_UTM_Cookie')) {
    class WTC_UTM_Cookie
    {
        public function __construct($cookie_name = "wbr_ad_seen_", $cookie_value = "organic_traffic")
        {
            $this->create_new_cookie($cookie_name, $cookie_value);
        }

        function create_new_cookie($cookie_name, $cookie_value)
        {
            if (isset($_COOKIE[$cookie_name])) {
                // ensure cookie with correct URL value is not overwritten  
            } else {
                setcookie($cookie_name, $cookie_value, time() + 84600 * 365, COOKIEPATH, COOKIE_DOMAIN, false, true);
            }
        }
    }
}

$wtc_utm_instance = new WTC_UTM_Plugin;
