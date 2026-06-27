<?php
class DogPark_Extra {
    public static function init() {
        // Settings link on Plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links){
            $settings_link = '<a href="options-general.php?page=dogpark-settings">Settings</a>';
            array_unshift($links, $settings_link);
            return $links;
        });
        // Register Google Sheet ID setting (already added in admin, but ensure it's registered)
        add_action('admin_init', function(){
            register_setting('dogpark_settings', 'dogpark_google_sheet_id', [
                'default' => '1FH02025PooN0NGOC_MS1IGLp3DkztxIV',
                'sanitize_callback' => 'sanitize_text_field'
            ]);
        });
        // Shortcode to display test park
        add_shortcode('dogpark_test', function(){
            global $wpdb;
            $park = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dogpark_parks WHERE name = %s", 'Test Park'));
            if(!$park) return 'No test park found.';
            return sprintf('Test Park: %s (%.5f, %.5f)', esc_html($park->name), $park->latitude, $park->longitude);
        });
    }
}
DogPark_Extra::init();
