<?php
/**
 * Plugin Name: Dog Park Recommendations
 * Description: Προτείνει την καλύτερη ώρα για επίσκεψη σε πάρκα σκύλων με βάση τον καιρό και τις συνθήκες.
 * Version: 0.01
 * Author: skilitsa.com
 * License: AGPLv3
 * Text Domain: dogpark
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

// Initialize visitor form block
DogPark_Visitor_Form::init();

// Initialize Gutenberg block
DogPark_Block::init();