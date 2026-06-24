<?php
/**
 * Plugin Name: Skilitsa Park Visits
 * Plugin URI: https://skilitsa.com/dog-park-plugin
 * Description: Προτείνει την καλύτερη ώρα για επίσκεψη σε πάρκα σκύλων με βάση τον καιρό και τις συνθήκες του πάρκου.
 * Version: 0.19.3
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
define('DOGPARK_VERSION', '0.19.3');
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
require_once DOGPARK_PLUGIN_DIR . 'includes/class-extra.php';

// Activation/Deactivation hooks
register_activation_hook(__FILE__, 'dogpark_activate');
register_deactivation_hook(__FILE__, 'dogpark_deactivate');

function dogpark_activate() {
    DogPark_Cache::create_tables();
    DogPark_Parks::create_tables();
    DogPark_Scheduler::schedule_events();
    // Insert test park if not exists
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM wp_dogpark_parks WHERE name = %s", 'Test Park'));
    if(!$exists){
        $wpdb->insert('wp_dogpark_parks', [
            'name' => 'Test Park',
            'latitude' => 37.9755,
            'longitude' => 23.7345,
            'shade' => 'good',
            'water' => 1,
            'drainage' => 'good',
            'lighting' => 'good',
            'notes' => 'Auto-generated test park.'
        ]);
    }
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

// REST endpoint to trigger CSV import from Google Drive
add_action('rest_api_init', function() {
    register_rest_route('dog-park/v1', '/import-parks', [
        'methods' => 'POST',
        'callback' => function() {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Insufficient permissions', ['status' => 403]);
            }
            
            // Debug info
            $debug = [];
            
            // Check token file
            $token_path = '/tmp/google_token.json';
            $debug['token_path'] = $token_path;
            $debug['token_file_exists'] = file_exists($token_path) ? 'yes' : 'no';
            
            if (file_exists($token_path)) {
                $token_data = json_decode(file_get_contents($token_path), true);
                $debug['token_loaded'] = $token_data ? 'yes' : 'no';
                $debug['access_token_present'] = isset($token_data['token']) ? 'yes' : 'no';
            }
            
            // Call the web-compatible import method
            $result = DogPark_Scheduler::import_parks(true);
            
            // Merge debug into result
            if (is_array($result)) {
                $result['_debug'] = $debug;
            }
            
            return $result;
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);
});

// Add Settings link on Plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links){
    $settings_link = '<a href="options-general.php?page=dogpark-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Register Google Sheet ID setting
add_action('admin_init', function(){
    register_setting('dogpark_settings', 'dogpark_google_sheet_id', [
        'default' => '1FH02025PooN0NGOC_MS1IGLp3DkztxIV',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
});

// REST endpoint to get parks list for block dropdown
add_action('rest_api_init', function() {
    register_rest_route('dog-park/v1', '/parks-list', [
        'methods' => 'GET',
        'callback' => function() {
            return DogPark_Parks::get_all_parks();
        },
        'permission_callback' => '__return_true',
    ]);
});
