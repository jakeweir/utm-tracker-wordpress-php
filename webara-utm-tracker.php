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

// Exit if accessed directly 
if (!defined('ABSPATH')) {
    echo "These aren't the droids you're looking for...";
    exit;
}

// Driver class
if (!class_exists('WTC_UTM_Plugin')) {
    class WTC_UTM_Plugin
    {
        public function __construct()
        {
            add_action('init', array($this, 'init'), 10, 0);
            //add_action('woocommerce_checkout_create_order', array($this, 'save_utm_as_meta'), 20, 1);
            add_action('woocommerce_thankyou', array($this, 'save_utm_as_meta'), 20, 1);
            add_action('load-post.php', array($this, 'setup_order_meta_box')); 
                      
        }

        function save_utm_as_meta( $order_id ) 
        {
            $cookies = $this->create_cookie_jar();
            $meta = $this->get_params_from_cookie_jar($cookies);
            $meta_sanitized = esc_url_raw($meta);
            $order = wc_get_order($order_id);
            $order->update_meta_data( '_wtc_utm_ad_history', $meta_sanitized );
            $order->save();
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
            //
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
            $uri = add_query_arg($wp->query_vars, home_url($wp->request));
            return esc_url($uri);
        }

        function create_cookie_jar()
        {
            $cookie_jar = array();
            foreach ($_COOKIE as $name => $value) {
                if (strpos($name, 'wbr_ad_seen_') === 0) {
                    $cookie_jar[$name] = $value;
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
            add_meta_box('wtc-utm-ad-history', esc_html__( 'Ad History', 'woocommerce' ), array($this, 'populate_ad_history_meta_box'), 'shop_order', 'side', 'core' );
        }

        function populate_ad_history_meta_box($order_id)
        {
            $order = wc_get_order($order_id);
            $meta_data = $order->get_meta('_wtc_utm_ad_history', 'false');

            if (is_array($meta_data) || is_object($meta_data))
            {
                foreach ($meta_data as $cookie => $values)
                {
                    echo "Advert: ".$cookie."<br><br>";
                    foreach($values as $param => $value)
                    {
                        echo $param." : ".$value."<br>";
                    }
                }

            }
            else 
            {    
                echo "Ad History unavailable for this order";
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














// /** 
//  *  DISPLAY 'AD HISTORY' META BOX WITHIN ADMIN AREA OF ORDER
//  */

// // Add Meta Container to admin shop_order pages
// add_action('add_meta_boxes', 'webara_add_meta_boxes');

// if (!function_exists('webara_add_meta_boxes')) {
//     function webara_add_meta_boxes()
//     {
//         add_meta_box('webara_ad_history', __('Ad History', 'woocommerce'), 'webara_add_ad_history_meta_box', 'shop_order', 'side', 'core');
//     }
// }

// // Adding Meta field in the meta container admin shop_order pages
// if (!function_exists('webara_add_ad_history_meta_box')) {
//     function webara_add_ad_history_meta_box()
//     {
//         global $post;

//         $meta_field_data = get_post_meta($post->ID, '_my_field_slug', true) ? get_post_meta($post->ID, '_my_field_slug', true) : '';

//         echo "<div>test data" . $meta_field_data . "</div>";

//         // echo '<input type="hidden" name="mv_other_meta_field_nonce" value="' . wp_create_nonce() . '">
//         // <p style="border-bottom:solid 1px #eee;padding-bottom:13px;">
//         //     <input type="text" style="width:250px;";" name="my_field_name" placeholder="' . $meta_field_data . '" value="' . $meta_field_data . '"></p>';

//     }
// }









// // Save the data of the Meta field
// add_action('save_post', 'mv_save_wc_order_other_fields', 10, 1);
// if (!function_exists('mv_save_wc_order_other_fields')) {

//     function mv_save_wc_order_other_fields($post_id)
//     {

//         // We need to verify this with the proper authorization (security stuff).

//         // Check if our nonce is set.
//         if (!isset($_POST['mv_other_meta_field_nonce'])) {
//             return $post_id;
//         }
//         $nonce = $_REQUEST['mv_other_meta_field_nonce'];

//         //Verify that the nonce is valid.
//         if (!wp_verify_nonce($nonce)) {
//             return $post_id;
//         }

//         // If this is an autosave, our form has not been submitted, so we don't want to do anything.
//         if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
//             return $post_id;
//         }

//         // Check the user's permissions.
//         if ('page' == $_POST['post_type']) {

//             if (!current_user_can('edit_page', $post_id)) {
//                 return $post_id;
//             }
//         } else {

//             if (!current_user_can('edit_post', $post_id)) {
//                 return $post_id;
//             }
//         }
//         // --- Its safe for us to save the data ! --- //

//         // Sanitize user input  and update the meta field in the database.
//         update_post_meta($post_id, '_my_field_slug', $_POST['my_field_name']);
//     }
// }























// add_action('add_meta_boxes', array($this, 'webara_add_meta_box'));
// add_action('woocommerce_checkout_update_order_meta', array($this, 'webara_woo_checkout_update_order_meta'));


// function webara_woo_checkout_update_order_meta($order_id, $data) 
// {
//     $cookie_jar = $this->create_cookie_jar();
//     $cookies = $this->get_params_from_cookie_jar($cookie_jar);

//     add_post_meta($order_id, 'utm-params', $cookies);
// }

// function webara_add_meta_box()
// {
//     add_meta_box('webara_ad_history', __('Ad History', 'woocommerce'), 'webara_add_ad_history_meta_box', 'shop_order', 'side', 'core');
// }

// function webara_add_ad_history_meta_box()
// {
//     global $post;

//     if(!empty($post->ID))
//     {
//         $ad_source = get_post_meta($post->ID, 'utm-source', true);
//         echo 'Test:  ' . $ad_source;
//     }

// }