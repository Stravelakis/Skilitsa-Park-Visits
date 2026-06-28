<?php

class DogPark_Cache {
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$wpdb->prefix}dogpark_weather_cache (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            park_id BIGINT NOT NULL,
            date DATE NOT NULL,
            best_hour VARCHAR(5) NOT NULL,
            temperature_low INT NOT NULL,
            temperature_high INT NOT NULL,
            hourly_data JSON NOT NULL,
            provider VARCHAR(50) NOT NULL,
            last_updated DATETIME NOT NULL,
            KEY (park_id, date)
        ) $charset_collate;";
        
        $sql .= "CREATE TABLE {$wpdb->prefix}dogpark_suggestions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            park_id BIGINT DEFAULT NULL,
            name VARCHAR(100),
            email VARCHAR(100),
            address TEXT,
            shade ENUM('good', 'partial', 'bad', 'unknown') DEFAULT 'unknown',
            water BOOLEAN DEFAULT NULL,
            drainage ENUM('good', 'moderate', 'bad') DEFAULT 'unknown',
            lighting ENUM('good', 'bad', 'unknown') DEFAULT 'unknown',
            notes TEXT,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at DATETIME NOT NULL
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public static function get_cache($park_id, $date) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dogpark_weather_cache WHERE park_id = %d AND date = %s",
                $park_id, 
                $date
            )
        );
    }
    
    public static function set_cache($data) {
        global $wpdb;
        $wpdb->replace($wpdb->prefix . 'dogpark_weather_cache', $data);
    }
    
    public static function delete_cache($park_id, $date) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'dogpark_weather_cache',
            ['park_id' => $park_id, 'date' => $date]
        );
    }
}