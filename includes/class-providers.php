<?php

class DogPark_Providers {
    
    private static $providers = [
        'openmeteo' => [
            'url' => 'https://api.open-meteo.com/v1/forecast',
            'params' => [
                'hourly' => 'temperature_2m,precipitation_probability,uv_index,windspeed_10m',
                'timezone' => 'auto',
                'forecast_days' => 2
            ],
            'enabled' => true
        ],
        'openweather' => [
            'url' => 'https://api.openweathermap.org/data/2.5/onecall',
            'params' => [
                'exclude' => 'minutely,daily,alerts',
                'units' => 'metric',
                'appid' => ''
            ],
            'enabled' => false
        ],
        'google' => [
            'url' => 'https://weather.googleapis.com/weather/',
            'params' => [],
            'enabled' => false
        ]
    ];
    
    public static function init() {
        $api_providers = get_option('dogpark_api_providers', []);
        foreach ($api_providers as $provider => $settings) {
            if (isset(self::$providers[$provider])) {
                self::$providers[$provider]['enabled'] = $settings['enabled'];
                if (isset($settings['api_key']) && $provider !== 'openmeteo') {
                    self::$providers[$provider]['params']['appid'] = $settings['api_key'];
                }
            }
        }
    }
    
    public static function fetch_forecast($latitude, $longitude) {
        self::init();
        
        $providers_order = ['openmeteo', 'openweather', 'google'];
        $attempts = [];
        
        foreach ($providers_order as $provider) {
            if (!self::$providers[$provider]['enabled']) {
                continue;
            }
            
            $max_retries = 3;
            for ($retry = 0; $retry < $max_retries; $retry++) {
                $result = self::call_provider($provider, $latitude, $longitude);
                if ($result['success']) {
                    return self::normalize_data($result['data'], $provider);
                }
                
                $attempts[] = [
                    'provider' => $provider,
                    'retry' => $retry + 1,
                    'error' => $result['error'],
                    'timestamp' => current_time('mysql')
                ];
                
                if ($retry < $max_retries - 1) {
                    // Short exponential backoff (1s, 2s). The old 5-minute delay meant a
                    // single flaky provider could stall the whole daily refresh run for
                    // hours across ~100+ parks and blow past PHP's max_execution_time.
                    sleep(min(2 ** $retry, 5));
                }
            }
        }
        
        // Log all failures
        self::log_failures($attempts);
        
        // Notify admin if enabled
        if (get_option('dogpark_admin_emails')) {
            self::notify_admin($attempts);
        }
        
        return false;
    }
    
    private static function call_provider($provider, $latitude, $longitude) {
        $params = self::$providers[$provider]['params'];
        $params['latitude'] = $latitude;
        $params['longitude'] = $longitude;
        
        $url = self::$providers[$provider]['url'] . '?' . http_build_query($params);
        
        $response = wp_remote_get($url, ['timeout' => 30]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Invalid JSON response'];
        }
        
        if (isset($data['error'])) {
            return ['success' => false, 'error' => $data['error']['message'] ?? 'Unknown API error'];
        }
        
        return ['success' => true, 'data' => $data];
    }
    
    private static function normalize_data($data, $provider) {
        $normalized = [
            'provider' => $provider,
            'daily' => [],
            'hourly' => []
        ];
        
        switch ($provider) {
            case 'openmeteo':
                $hourly = $data['hourly'];
                $timezone = $data['timezone'];
                
                foreach ($hourly['time'] as $i => $time) {
                    $hour = date('G', strtotime($time));
                    if ($hour >= 6 && $hour <= 23) { // Only 6 AM to 11 PM
                        $normalized['hourly'][] = [
                            'hour' => $hour . ':00',
                            'temp' => $hourly['temperature_2m'][$i],
                            'rain' => $hourly['precipitation_probability'][$i],
                            'uv' => $hourly['uv_index'][$i],
                            'wind' => $hourly['windspeed_10m'][$i]
                        ];
                    }
                }
                break;
            
            case 'openweather':
                $hourly = $data['hourly'];
                foreach ($hourly as $i => $hour_data) {
                    $hour = date('G', $hour_data['dt'] + $data['timezone_offset']);
                    if ($hour >= 6 && $hour <= 23) {
                        $normalized['hourly'][] = [
                            'hour' => $hour . ':00',
                            'temp' => $hour_data['temp'],
                            'rain' => $hour_data['pop'] * 100,
                            'uv' => $hour_data['uvi'] ?? 0,
                            'wind' => $hour_data['wind_speed']
                        ];
                    }
                }
                break;
            
            case 'google':
                // Placeholder for Google Weather normalization
                break;
        }
        
        return $normalized;
    }
    
    private static function log_failures($attempts) {
        $log = get_option('dogpark_failure_log', []);
        $log[] = [
            'timestamp' => current_time('mysql'),
            'attempts' => $attempts
        ];
        update_option('dogpark_failure_log', $log);
    }
    
    private static function notify_admin($attempts) {
        $subject = 'Dog Park: Failed to fetch weather data';
        $message = "All weather providers failed to fetch data:\n\n";
        foreach ($attempts as $attempt) {
            $message .= sprintf(
                "Provider: %s, Retry: %d, Error: %s\n",
                $attempt['provider'],
                $attempt['retry'],
                $attempt['error']
            );
        }
        
        $admin_email = get_option('admin_email');
        wp_mail($admin_email, $subject, $message);
    }
}