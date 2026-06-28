<?php

class DogPark_Scheduler {
    
    public static function init() {
        add_action('dogpark_refresh_forecast', [__CLASS__, 'refresh_all_parks']);
    }
    
    public static function schedule_events() {
        if (!wp_next_scheduled('dogpark_refresh_forecast')) {
            // Schedule daily at 3 AM EEST (adjust for DST)
            wp_schedule_event(
                strtotime('today 03:00 EEST'),
                'daily',
                'dogpark_refresh_forecast'
            );
        }
    }
    
    public static function clear_events() {
        wp_clear_scheduled_hook('dogpark_refresh_forecast');
    }
    
    public static function refresh_all_parks() {
        $parks = DogPark_Parks::get_all_parks();
        $today = current_time('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        foreach ($parks as $park) {
            // Refresh today's forecast
            $forecast_today = DogPark_Providers::fetch_forecast($park->latitude, $park->longitude);
            if ($forecast_today) {
                $scoring_today = DogPark_Scoring::calculate_best_hour($park->id, $forecast_today);
                DogPark_Cache::set_cache([
                    'park_id' => $park->id,
                    'date' => $today,
                    'best_hour' => $scoring_today['best_hour'],
                    'temperature_low' => $scoring_today['temp_range'][0],
                    'temperature_high' => $scoring_today['temp_range'][1],
                    'hourly_data' => wp_json_encode($scoring_today['hourly_scores']),
                    'provider' => $forecast_today['provider'],
                    'last_updated' => current_time('mysql')
                ]);
            }
            
            // Refresh tomorrow's forecast (for fallback)
            DogPark_Cache::delete_cache($park->id, $tomorrow); // Ensure fresh data
            $forecast_tomorrow = DogPark_Providers::fetch_forecast($park->latitude, $park->longitude);
            if ($forecast_tomorrow) {
                $scoring_tomorrow = DogPark_Scoring::calculate_best_hour($park->id, $forecast_tomorrow);
                DogPark_Cache::set_cache([
                    'park_id' => $park->id,
                    'date' => $tomorrow,
                    'best_hour' => $scoring_tomorrow['best_hour'],
                    'temperature_low' => $scoring_tomorrow['temp_range'][0],
                    'temperature_high' => $scoring_tomorrow['temp_range'][1],
                    'hourly_data' => wp_json_encode($scoring_tomorrow['hourly_scores']),
                    'provider' => $forecast_tomorrow['provider'],
                    'last_updated' => current_time('mysql')
                ]);
            }
        }
    }
    
    // CLI Command: wp dogpark refresh
    public static function register_cli_command() {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('dogpark refresh', [__CLASS__, 'cli_refresh']);
            WP_CLI::add_command('dogpark import-parks', [__CLASS__, 'cli_import_parks']);
        }
    }
    
    public static function cli_refresh() {
        self::refresh_all_parks();
        WP_CLI::success('Refreshed all park forecasts.');
    }

    public static function cli_import_parks($args, $assoc_args) {
        $source = isset($assoc_args['source']) ? $assoc_args['source'] : 'csv';
        $file = isset($assoc_args['file']) ? $assoc_args['file'] : '';
        $fetch_from_drive = isset($assoc_args['fetch-from-drive']) && $assoc_args['fetch-from-drive'];
        
        if ($fetch_from_drive) {
            $file = self::download_csv_from_drive();
            if (!$file) {
                WP_CLI::error('Failed to download CSV from Google Drive');
            }
        } elseif (empty($file) || !file_exists($file)) {
            WP_CLI::error('File not found. Use --file=/path/to/file.csv or --fetch-from-drive');
        }

        if ($source === 'csv') {
            $result = self::import_from_csv($file);
        } else {
            WP_CLI::error("Unknown source: {$source}. Use 'csv'.");
        }
        
        // Clean up temp file if downloaded from Drive
        if ($fetch_from_drive && $file && file_exists($file)) {
            @unlink($file);
        }
        
        // Output summary in CLI format
        if (isset($result['imported'])) {
            WP_CLI::success("Done! Imported: {$result['imported']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}, Errors: {$result['errors']}");
        }
    }

    // Web-compatible import method (returns array instead of using WP_CLI)
    public static function import_parks($fetch_from_drive = true) {
        if ($fetch_from_drive) {
            $file = self::download_csv_from_drive_web();
            if (!$file) {
                return ['success' => false, 'message' => 'Failed to download CSV from Google Drive'];
            }
        } else {
            return ['success' => false, 'message' => 'File not provided and fetch-from-drive is false'];
        }

        $result = self::import_from_csv_web($file);
        
        // Clean up temp file if downloaded from Drive
        if ($file && file_exists($file)) {
            @unlink($file);
        }
        
        return $result;
    }

    private static function download_csv_from_drive_web() {
        $file_id = '1FH02025PooN0NGOC_MS1IGLp3DkztxIV';
        $temp_file = sys_get_temp_dir() . '/dog_parks_import_' . time() . '.csv';
        
        $token_path = '/tmp/google_token.json';
        if (!file_exists($token_path)) {
            return false;
        }
        
        $token_data = json_decode(file_get_contents($token_path), true);
        $access_token = $token_data['access_token'] ?? null;
        
        if (!$access_token) {
            return false;
        }
        
        $url = "https://www.googleapis.com/drive/v3/files/{$file_id}?alt=media";
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 60,
        ]);

        error_log('DOGPARK DEBUG: Drive download - token exists: ' . ($access_token ? 'yes' : 'no'), 3, '/tmp/dogpark-debug.log');
        error_log('DOGPARK DEBUG: Drive download - is_wp_error: ' . (is_wp_error($response) ? 'yes' : 'no'), 3, '/tmp/dogpark-debug.log');
        if (is_wp_error($response)) {
            error_log('DOGPARK DEBUG: Drive download - error: ' . $response->get_error_message(), 3, '/tmp/dogpark-debug.log');
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        error_log('DOGPARK DEBUG: Drive download - HTTP code: ' . $code, 3, '/tmp/dogpark-debug.log');
        error_log('DOGPARK DEBUG: Drive download - body length: ' . strlen($body), 3, '/tmp/dogpark-debug.log');

        if ($code !== 200) {
            error_log('DOGPARK DEBUG: Drive download - non-200 response: ' . substr($body, 0, 500));
            return false;
        }
        
        file_put_contents($temp_file, $body);
        
        if (file_exists($temp_file) && filesize($temp_file) > 0) {
            return $temp_file;
        }
        
        return false;
    }

    private static function import_from_csv_web($file) {
        $handle = fopen($file, 'r');
        if (!$handle) {
            return ['success' => false, 'message' => "Could not open file: {$file}"];
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['success' => false, 'message' => 'Empty CSV file'];
        }

        // Normalize header names
        $header = array_map(function($h) {
            return strtolower(trim($h));
        }, $header);

        // Column index mapping
        $col = [];
        foreach ($header as $i => $h) {
            $col[$h] = $i;
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) {
                $skipped++;
                continue;
            }

            try {
                $park_data = self::parse_csv_row($row, $col);
                if (!$park_data) {
                    $skipped++;
                    continue;
                }

                // Check if park exists (by name + municipality)
                $existing = self::find_existing_park($park_data['name'], $park_data['municipality']);
                
                if ($existing) {
                    DogPark_Parks::update_park($existing->id, $park_data);
                    $updated++;
                } else {
                    DogPark_Parks::insert_park($park_data);
                    $imported++;
                }
            } catch (Exception $e) {
                $errors++;
            }
        }

        fclose($handle);

        return [
            'success' => true,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private static function parse_csv_row($row, $col) {
        // Get values with defaults
        $get = function($key, $default = '') use ($row, $col) {
            return isset($col[$key]) && isset($row[$col[$key]]) ? trim($row[$col[$key]]) : $default;
        };

        $municipality = $get('location / municipality');
        $park_name = $get('park name / area');
        
        if (empty($park_name) || $park_name === 'N/A') {
            return null;
        }

        // Determine if private
        $is_private = stripos($park_name, '(private)') !== false || stripos($municipality, '(private)') !== false;
        
        // Parse coordinates from Google Maps link
        $maps_link = $get('google maps');
        $coordinates = self::extract_coordinates($maps_link);
        
        // Use municipality coordinates as fallback (from sheet)
        if (!$coordinates) {
            // We'll need to parse from municipality string like "Athens (Petralona)" 
            // or use the coordinates column from the sheet
            $coordinates = self::extract_coordinates_from_municipality($municipality);
        }

        // Map fencing type to shade
        $fencing = $get('fencing type');
        $shade = self::map_fencing_to_shade($fencing);

        // Map benches to water
        $benches = $get('benches');
        $water = self::map_benches_to_water($benches);

        // Map lighting
        $lighting_raw = $get('lighting');
        $lighting = self::map_lighting($lighting_raw);

        // Drainage - infer from size/entry type
        $drainage = 'unknown';
        $size = $get('size');
        $entry = $get('entry type');

        // Build notes
        $notes_parts = [];
        if ($municipality) $notes_parts[] = "Δήμος/Περιοχή: {$municipality}";
        if ($size && $size !== 'N/A') $notes_parts[] = "Μέγεθος: {$size}";
        if ($entry && $entry !== 'N/A') $notes_parts[] = "Είσοδος: {$entry}";
        if ($fencing) $notes_parts[] = "Φραγμή: {$fencing}";
        $agility = $get('agility equipment & features');
        if ($agility && $agility !== 'N/A') $notes_parts[] = "Εξοπλισμός: {$agility}";
        $link = $get('official link');
        if ($link && $link !== 'N/A') $notes_parts[] = "Σύνδεσμος: {$link}";
        if ($maps_link && $maps_link !== 'N/A') $notes_parts[] = "Google Maps: {$maps_link}";
        if ($is_private) $notes_parts[] = "Ιδιωτικό πάρκο";

        $name = $park_name;
        if ($municipality) {
            $name = "{$park_name} ({$municipality})";
        }

        return [
            'name' => $name,
            'latitude' => $coordinates['lat'] ?? null,
            'longitude' => $coordinates['lng'] ?? null,
            'shade' => $shade,
            'water' => $water,
            'drainage' => $drainage,
            'lighting' => $lighting,
            'notes' => implode("\n", $notes_parts),
        ];
    }

    private static function extract_coordinates($maps_link) {
        if (empty($maps_link) || $maps_link === 'N/A') {
            return null;
        }
        
        // Parse Google Maps URL: https://www.google.com/maps/search/?api=1&query=Serafeio+Dog+Park+Athens
        // or https://www.google.com/maps/search/?api=1&query=37.997200,23.738100
        if (preg_match('/query=([^&]+)/', $maps_link, $matches)) {
            $query = urldecode($matches[1]);
            // Check if query contains lat,lng
            if (preg_match('/(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/', $query, $coord_match)) {
                return ['lat' => (float)$coord_match[1], 'lng' => (float)$coord_match[2]];
            }
        }
        return null;
    }

    private static function extract_coordinates_from_municipality($municipality) {
        // Municipality strings like "Athens (Petralona)", "Galatsi (Alsos Veikou)"
        // We don't have exact coordinates per municipality in the CSV
        // Return null - will need manual entry or use defaults
        return null;
    }

    private static function map_fencing_to_shade($fencing) {
        if (empty($fencing) || $fencing === 'N/A') {
            return 'unknown';
        }
        
        $fencing_lower = strtolower($fencing);
        
        // Good shade: heavy-duty, forest mesh, enclosed (2 zones), tall perimeter
        if (preg_match('/heavy.duty|forest.mesh|enclosed.*2.zone|tall.perimeter|heavy.mesh|certified.iron|security.fencing|upgraded.wooden/i', $fencing_lower)) {
            return 'good';
        }
        
        // Partial shade: mesh, enclosed, standard mesh, wire mesh, metal mesh, wood & mesh, galvanized
        if (preg_match('/mesh|enclosed|wire.mesh|metal.mesh|wood.&.mesh|galvanized|standard.mesh|marine.grade|enclosed.mesh/i', $fencing_lower)) {
            return 'partial';
        }
        
        // Bad shade: compromise urban fencing, repurposed playground fencing, basic boundary, mobile metal fencing, single-gate, open
        if (preg_match('/compromise|repurposed|basic.boundary|mobile.metal|single.gate|open|planned/i', $fencing_lower)) {
            return 'bad';
        }
        
        return 'unknown';
    }

    private static function map_benches_to_water($benches) {
        if (empty($benches) || $benches === 'N/A') {
            return null;
        }
        
        $benches_lower = strtolower($benches);
        
        // Water available: yes, kiosks, picnic tables (implies facilities)
        if (preg_match('/yes|kiosk|pergola|shaded|picnic.table/i', $benches_lower)) {
            return true;
        }
        
        // No water: no, recycled wood (just benches)
        if (preg_match('/no|recycled.wood|n\/a/i', $benches_lower)) {
            return false;
        }
        
        return null;
    }

    private static function map_lighting($lighting) {
        if (empty($lighting) || $lighting === 'N/A') {
            return 'unknown';
        }
        
        $lighting_lower = strtolower($lighting);
        
        // Good lighting: full led, high-lumen, upgraded, modern, floodlight
        if (preg_match('/full.led|high.lumen|upgraded|modern|floodlight|bioclimate/i', $lighting_lower)) {
            return 'good';
        }
        
        // Bad lighting: standard, street, nature trail, rest area, planned, port led, solar led (planned)
        if (preg_match('/standard|street|nature.trail|rest.area|planned|port.led|solar.led/i', $lighting_lower)) {
            return 'bad';
        }
        
        return 'unknown';
    }

    private static function find_existing_park($name, $municipality) {
        global $wpdb;
        
        // Try exact match on name (which includes municipality)
        $park = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dogpark_parks WHERE name = %s",
            $name
        ));
        
        if ($park) {
            return $park;
        }
        
        // Try partial match
        $park = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dogpark_parks WHERE name LIKE %s",
            "%{$name}%"
        ));
        
        return $park;
    }
}