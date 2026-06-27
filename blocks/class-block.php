<?php

class DogPark_Block {
    public static function init() {
        add_action('init', [__CLASS__, 'register_block']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_route']);
    }
    
    public static function register_block() {
        register_block_type(__DIR__ . '/dog-park-best-hour', [
            'render_callback' => [__CLASS__, 'render_block'],
            'attributes' => [
                'parkId' => ['type' => 'number', 'default' => 0],
                'isDarkMode' => ['type' => 'boolean', 'default' => false]
            ]
        ]);
    }
    
    public static function register_rest_route() {
        register_rest_route('dog-park/v1', '/parks', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_parks'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public static function get_parks() {
        return DogPark_Parks::get_all_parks();
    }
    
    public static function render_block($attributes) {
        $park_id = $attributes['parkId'] ?? 0;
        $is_dark = $attributes['isDarkMode'] ?? false;
        $today = current_time('Y-m-d');
        
        if ($park_id === 0) {
            return '<div class="dogpark-block">' . esc_html__('Select a park to see the best hour for your σκυλίτσα!', 'dogpark') . '</div>';
        }
        
        $park = DogPark_Parks::get_park_by_id($park_id);
        if (!$park) {
            return '<div class="dogpark-block">' . esc_html__('Park not found.', 'dogpark') . '</div>';
        }
        
        $cache = DogPark_Cache::get_cache($park_id, $today);
        if (!$cache) {
            // Try tomorrow's forecast as fallback
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $cache = DogPark_Cache::get_cache($park_id, $tomorrow);
            if ($cache) {
                return self::render_fallback($park, $cache, $is_dark);
            }
            return '<div class="dogpark-block">' . esc_html__('Δεδομένα μη διαθέσιμα. Δοκίμασε αργότερα!', 'dogpark') . '</div>';
        }
        
        $hourly_data = json_decode($cache->hourly_data, true);
        $best_hour_data = $hourly_data[$cache->best_hour];
        
        ob_start();
        ?>
        <div class="dogpark-block <?php echo $is_dark ? 'dark-mode' : ''; ?>">
            <div class="dogpark-best-hour">
                <?php echo esc_html__('Καλύτερη ώρα: ', 'dogpark'); ?>
                <strong><?php echo esc_html($cache->best_hour); ?></strong>
                <span class="dogpark-score">
                    <?php echo str_repeat('★', round($best_hour_data['score'] / 20)); ?>
                </span>
            </div>
            
            <div class="dogpark-temp-range">
                <?php echo esc_html(sprintf(__('%d°C - %d°C', 'dogpark'), $cache->temperature_low, $cache->temperature_high)); ?>
            </div>
            
            <details class="dogpark-dropdown">
                <summary><?php echo esc_html__('Άλλες ώρες', 'dogpark'); ?></summary>
                <?php foreach ($hourly_data as $hour => $data): ?>
                    <div class="dogpark-hour-row" data-hour="<?php echo esc_attr($hour); ?>">
                        <span>
                            <?php echo esc_html($hour); ?>
                            <span class="dogpark-score">
                                <?php echo str_repeat('★', round($data['score'] / 20)); ?>
                            </span>
                            <?php echo esc_html(sprintf(__('%d°C', 'dogpark'), $data['temp'])); ?>
                            <?php echo esc_html(sprintf(__('(%d%% βροχή)', 'dogpark'), $data['rain'])); ?>
                        </span>
                        <div class="dogpark-hour-details">
                            <span class="dogpark-tooltip">
                                <?php echo esc_html($data['explanation']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </details>
            
            <div class="dogpark-branding">
                <a href="https://skilitsa.com" target="_blank">Powered by skilitsa.com <span class="dog-icon">🐶</span></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private static function render_fallback($park, $cache, $is_dark) {
        ob_start();
        ?>
        <div class="dogpark-block <?php echo $is_dark ? 'dark-mode' : ''; ?>">
            <div class="dogpark-best-hour">
                <?php echo esc_html__('Καλύτερη ώρα αύριο: ', 'dogpark'); ?>
                <strong><?php echo esc_html($cache->best_hour); ?></strong>
                <span class="dogpark-score">
                    <?php echo str_repeat('★', round(json_decode($cache->hourly_data, true)[$cache->best_hour]['score'] / 20)); ?>
                </span>
            </div>
            <div class="dogpark-temp-range">
                <?php echo esc_html(sprintf(__('%d°C - %d°C', 'dogpark'), $cache->temperature_low, $cache->temperature_high)); ?>
            </div>
            <p class="dogpark-fallback-note">
                <?php echo esc_html__('Σημείωση: Χρήση πρόγνωσης για αύριο λόγω αποτυχίας ενημέρωσης.', 'dogpark'); ?>
            </p>
            <div class="dogpark-branding">
                <a href="https://skilitsa.com" target="_blank">Powered by skilitsa.com <span class="dog-icon">🐶</span></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}