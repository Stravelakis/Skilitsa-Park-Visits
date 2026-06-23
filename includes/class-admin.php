<?php

class DogPark_Admin {
    
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }
    
    public static function add_menu() {
        add_options_page(
            'Dog Park Settings',
            'Dog Park',
            'manage_options',
            'dogpark-settings',
            [__CLASS__, 'render_settings']
        );
    }
    
    public static function register_settings() {
        register_setting('dogpark_settings', 'dogpark_scoring_weights', [
            'default' => ['rain' => 40, 'heat' => 25, 'uv' => 15, 'wind' => 10],
            'sanitize_callback' => [__CLASS__, 'sanitize_weights']
        ]);
        
        register_setting('dogpark_settings', 'dogpark_admin_emails', [
            'default' => 0,
            'sanitize_callback' => 'absint'
        ]);
        
        register_setting('dogpark_settings', 'dogpark_api_providers', [
            'default' => [
                'openmeteo' => ['enabled' => 1, 'api_key' => ''],
                'openweather' => ['enabled' => 0, 'api_key' => ''],
                'google' => ['enabled' => 0, 'api_key' => '']
            ],
            'sanitize_callback' => [__CLASS__, 'sanitize_providers']
        ]);

        register_setting('dogpark_settings', 'dogpark_google_sheet_id', [
            'default' => '1FH02025PooN0NGOC_MS1IGLp3DkztxIV',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }
    
    public static function sanitize_weights($weights) {
        $total = array_sum($weights);
        if ($total !== 100) {
            add_settings_error('dogpark_settings', 'invalid_weights', 'Τα βάρη πρέπει να αθροίζουν στο 100%.');
        }
        return $weights;
    }
    
    public static function sanitize_providers($providers) {
        foreach ($providers as &$provider) {
            $provider['enabled'] = isset($provider['enabled']) ? 1 : 0;
        }
        return $providers;
    }
    
    public static function render_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        ?>
        <div class="wrap">
            <h1>Dog Park Settings</h1>
            <p>Configure how the plugin calculates the best visit time and where it gets the park data from.</p>
            
            <form method="post" action="options.php">
                <?php settings_fields('dogpark_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Scoring Weights (%)</th>
                        <td>
                            <fieldset>
                                <label><input type="number" name="dogpark_scoring_weights[rain]" value="<?php echo esc_attr(get_option('dogpark_scoring_weights')['rain']); ?>" min="0" max="100"> Rain (Βροχή)</label><br>
                                <label><input type="number" name="dogpark_scoring_weights[heat]" value="<?php echo esc_attr(get_option('dogpark_scoring_weights')['heat']); ?>" min="0" max="100"> Heat (Ζέστη)</label><br>
                                <label><input type="number" name="dogpark_scoring_weights[uv]" value="<?php echo esc_attr(get_option('dogpark_scoring_weights')['uv']); ?>" min="0" max="100"> UV Index (Υπεριώδης)</label><br>
                                <label><input type="number" name="dogpark_scoring_weights[wind]" value="<?php echo esc_attr(get_option('dogpark_scoring_weights')['wind']); ?>" min="0" max="100"> Wind (Άνεμος)</label>
                            </fieldset>
                            <p class="description">These weights determine which weather factor is most important. Total must equal 100%.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Admin Notifications</th>
                        <td>
                            <label><input type="checkbox" name="dogpark_admin_emails" value="1" <?php checked(get_option('dogpark_admin_emails'), 1); ?>> Notify admins on provider failures</label>
                            <p class="description">Check this to receive a system alert if a weather provider (like Open-Meteo) goes offline.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Providers</th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="dogpark_api_providers[openmeteo][enabled]" value="1" <?php checked(get_option('dogpark_api_providers')['openmeteo']['enabled'], 1); ?>> Open-Meteo (Free)</label><br>
                                <label><input type="checkbox" name="dogpark_api_providers[openweather][enabled]" value="1" <?php checked(get_option('dogpark_api_providers')['openweather']['enabled'], 1); ?>> OpenWeather (API Key Required)<br>
                                <input type="text" name="dogpark_api_providers[openweather][api_key]" value="<?php echo esc_attr(get_option('dogpark_api_providers')['openweather']['api_key']); ?>" placeholder="API Key"></label><br>
                                <label><input type="checkbox" name="dogpark_api_providers[google][enabled]" value="1" <?php checked(get_option('dogpark_api_providers')['google']['enabled'], 1); ?>> Google Weather (API Key Required)<br>
                                <input type="text" name="dogpark_api_providers[google][api_key]" value="<?php echo esc_attr(get_option('dogpark_api_providers')['google']['api_key']); ?>" placeholder="API Key"></label>
                            </fieldset>
                            <p class="description">Choose which weather service to use. Open-Meteo is free and requires no key.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Google Sheet ID</th>
                        <td>
                            <input type="text" name="dogpark_google_sheet_id" value="<?php echo esc_attr(get_option('dogpark_google_sheet_id')); ?>" class="regular-text">
                            <p class="description">Paste the ID of your Google Sheet here (the long string in the URL). The plugin uses this to import the park list.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}