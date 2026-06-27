<?php
/**
 * Plugin Name: Skilitsa Park Visits
 * Plugin URI: https://skilitsa.com/dog-park-plugin
 * Description: Προτείνει την καλύτερη ώρα για επίσκεψη σε πάρκα σκύλων με βάση τον καιρό και τις συνθήκες του πάρκου.
 * Version: 0.20.0
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
define('DOGPARK_VERSION', '0.20.0');
define('DOGPARK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DOGPARK_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include core files
require_once DOGPARK_PLUGIN_DIR . 'includes/class-cache.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-parks.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-providers.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-scoring.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-admin.php';
require_once DOGPARK_PLUGIN_DIR . 'includes/class-scheduler.php';
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
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dogpark_parks WHERE name = %s", 'Test Park'));
    if(!$exists){
        $wpdb->insert($wpdb->prefix . 'dogpark_parks', [
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

add_action('plugins_loaded', function() {
    load_plugin_textdomain(
        'dogpark',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

// Initialize admin
DogPark_Admin::init();
DogPark_Admin_Suggestions::init();
DogPark_Admin_Parks::init();
DogPark_Extra::init();
DogPark_Scheduler::register_cli_command();

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
            $upload_dir = wp_upload_dir();
            wp_mkdir_p($upload_dir['basedir'] . '/dogpark');
            $token_path = $upload_dir['basedir'] . '/dogpark/google_token.json';
            $debug['token_path'] = $token_path;
            $debug['token_file_exists'] = file_exists($token_path) ? 'yes' : 'no';
            
            if (file_exists($token_path)) {
                $token_data = json_decode(file_get_contents($token_path), true);
                $debug['token_loaded'] = $token_data ? 'yes' : 'no';
                $debug['access_token_present'] = isset($token_data['token']) ? 'yes' : 'no';
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


// REST endpoint to get parks list for block dropdown
add_action('rest_api_init', function() {
    register_rest_route('dog-park/v1', '/parks-list', [
        'methods' => 'GET',
        'callback' => function() {
            $parks = DogPark_Parks::get_all_parks();
            $restricted = array_map(function($park) {
                return [
                    'id' => $park->id,
                    'name' => $park->name
                ];
            }, $parks);
            return $restricted;
        },
        'permission_callback' => '__return_true',
    ]);
});
