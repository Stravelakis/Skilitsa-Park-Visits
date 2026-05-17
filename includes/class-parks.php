<?php

class DogPark_Parks {
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE wp_dogpark_parks (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            shade ENUM('good', 'partial', 'bad', 'unknown') DEFAULT 'unknown',
            water BOOLEAN DEFAULT NULL,
            drainage ENUM('good', 'moderate', 'bad') DEFAULT 'unknown',
            lighting ENUM('good', 'bad', 'unknown') DEFAULT 'unknown',
            notes TEXT
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public static function get_all_parks() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM wp_dogpark_parks");
    }
    
    public static function get_park_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_dogpark_parks WHERE id = %d", $id));
    }
    
    public static function insert_park($data) {
        global $wpdb;
        $wpdb->insert('wp_dogpark_parks', $data);
        return $wpdb->insert_id;
    }
    
    public static function update_park($id, $data) {
        global $wpdb;
        $wpdb->update('wp_dogpark_parks', $data, ['id' => $id]);
    }
    
    public static function delete_park($id) {
        global $wpdb;
        $wpdb->delete('wp_dogpark_parks', ['id' => $id]);
    }
}