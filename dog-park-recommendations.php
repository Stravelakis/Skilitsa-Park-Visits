<?php
/**
 * Plugin Name: Skilitsa Park Visits
 * Plugin URI: https://skilitsa.com/dog-park-plugin
 * Description: Προτείνει την καλύτερη ώρα για επίσκεψη σε πάρκα σκύλων με βάση τον καιρό και τις συνθήκες του πάρκου.
 * Version: 0.01
 * Author: skilitsa.com
 * Author URI: https://skilitsa.com
 * License: AGPLv3
 * Text Domain: dogpark
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants
define('DOGPARK_VERSION', '0.01');
define('DOGPARK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DOGPARK_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include core files
require_once DOGPARK_PLUGIN_DIR . 'includes/class-cache.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-parks.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-providers.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-scoring.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-admin.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-scheduler.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-text.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-visitor-form.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-admin-suggestions.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-admin-parks.php';
require_once DOGPARK_PLUGIN_DIR . 'blocks/class-block.php';

// Activation/Deactivation hooks
register_activation_hook(__FILE__, 'dogpark_activate');
register_deactivation_hook(__FILE__, 'dogpark_deactivate');

function dogpark_activate() {
    DogPark_Cache::create_tables();
    DogPark_Parks::create_tables();
    DogPark_Scheduler::schedule_events();
}

function dogpark_deactivate() {
    DogPark_Scheduler::clear_events();
}

// Initialize admin
DogPark_Admin::init();
DogPark_Admin_Suggestions::init();

// Initialize Gutenberg blocks
DogPark_Block::init();
DogPark_Visitor_Form::init();

// REST endpoint to get WPS Hide Login URL
add_action('rest_api_init', function() {
    register_rest_route('dog-park/v1', '/login-url', [
        'methods' => 'GET',
        'callback' => function() {
            $login_slug = get_option('whl_page', 'wp-login');
            $home_url = home_url();
            return [
                'login_url' => $home_url . '/' . $login_slug,
                'slug' => $login_slug,
            ];
        },
        'permission_callback' => '__return_true',
    ]);
});

add_action('rest_api_init', function() {
    register_rest_route('dog-park/v1', '/wps-hide-login', [
        'methods' => 'GET',
        'callback' => function() {
            $slug = get_option('whl_page');
            return [
                'slug' => $slug ? $slug : 'wp-login',
                'full_url' => home_url() . '/' . ($slug ? $slug : 'wp-login'),
            ];
        },
        'permission_callback' => '__return_true',
    ]);
});

// REST endpoint to trigger CSV import from Google Drive
add_action('rest_api_init', function() {
    register_rest_route('dog-park/v1', '/import-parks', [
        'methods' => 'POST',
        'callback' => function() {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Insufficient permissions', ['status' => 403]);
            }
            
            // Call the web-compatible import method
            $result = DogPark_Scheduler::import_parks(true);
            
            return $result;
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);
});