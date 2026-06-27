<?php

class DogPark_Visitor_Form {
    public static function init() {
        add_action('init', [__CLASS__, 'register_block']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_route']);
    }
    
    public static function register_block() {
        register_block_type(__DIR__ . '/visitor-form', [
            'render_callback' => [__CLASS__, 'render_block'],
        ]);
    }
    
    public static function register_rest_route() {
        register_rest_route('dog-park/v1', '/suggestions', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_submission'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public static function handle_submission($request) {
        $params = $request->get_params();
        
        // Check nonce
        if (!wp_verify_nonce($params['dogpark_suggest_nonce'] ?? '', 'dogpark_suggest')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }
        
        // Check honeypot
        if (!empty($params['dogpark_hp'])) {
            // Silent discard — show success to fool bots
            wp_send_json_success(['message' => 'ok']);
        }

        // Rate limiting
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        $key = 'dogpark_rl_' . md5($ip);
        if (get_transient($key)) {
            wp_send_json_error(['message' => 'Πολλές υποβολές. Δοκίμασε σε λίγο. ⏳']);
        }
        set_transient($key, 1, 60); // 1 submission per minute per IP

        $suggestion_id = self::save_suggestion($params);
        if (is_wp_error($suggestion_id)) {
            return $suggestion_id;
        }
        
        // Notify admin if emails are enabled
        if (get_option('dogpark_admin_emails')) {
            self::notify_admin($suggestion_id);
        }
        
        return ['success' => true, 'suggestion_id' => $suggestion_id];
    }
    
    private static function save_suggestion($params) {
        global $wpdb;
        
        $data = [
            'park_id' => isset($params['park_id']) && $params['park_id'] !== 'new' ? $params['park_id'] : null,
            'name' => sanitize_text_field($params['name']),
            'email' => isset($params['email']) ? sanitize_email($params['email']) : null,
            'address' => sanitize_textarea_field($params['address']),
            'shade' => sanitize_text_field($params['shade']),
            'water' => isset($params['water']) ? 1 : null,
            'drainage' => sanitize_text_field($params['drainage']),
            'lighting' => sanitize_text_field($params['lighting']),
            'notes' => sanitize_textarea_field($params['notes']),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($wpdb->prefix . 'dogpark_suggestions', $data);
        return $wpdb->insert_id;
    }
    
    private static function notify_admin($suggestion_id) {
        $suggestion = self::get_suggestion($suggestion_id);
        $subject = 'New Dog Park Suggestion: ' . ($suggestion->name ?: 'Unnamed Park');
        $message = "A new dog park suggestion has been submitted:\n\n";
        $message .= "Park: " . ($suggestion->park_id ? DogPark_Parks::get_park_by_id($suggestion->park_id)->name : 'New Park') . "\n";
        $message .= "Name: " . ($suggestion->name ?: 'N/A') . "\n";
        $message .= "Email: " . ($suggestion->email ?: 'N/A') . "\n";
        $message .= "Location: " . $suggestion->address . "\n";
        $message .= "Shade: " . $suggestion->shade . "\n";
        $message .= "Water: " . ($suggestion->water ? 'Yes' : 'No') . "\n";
        $message .= "Drainage: " . $suggestion->drainage . "\n";
        $message .= "Lighting: " . $suggestion->lighting . "\n";
        $message .= "Notes: " . ($suggestion->notes ?: 'None') . "\n";
        
        $admin_email = get_option('admin_email');
        wp_mail($admin_email, $subject, $message);
    }
    
    public static function render_block($attributes) {
        wp_enqueue_script(
            'dogpark-suggestion-form',
            plugins_url('../build/suggestion-form.js', __FILE__),
            [],
            DOGPARK_VERSION,
            true
        );
        wp_localize_script('dogpark-suggestion-form', 'dogparkFormSettings', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('dogpark_suggest'),
            'restUrl' => rest_url('dog-park/v1/')
        ]);

        ob_start();
        ?>
        <div class="dogpark-suggestion-form">
            <h3><?php echo esc_html__('Πες μας για ένα πάρκο που αξίζει! 🐶', 'dogpark'); ?></h3>
            <form id="dogpark-suggestion">
                <?php wp_nonce_field('dogpark_suggest', 'dogpark_suggest_nonce'); ?>
                <input type="text" name="dogpark_hp" value="" class="dogpark-honeypot" autocomplete="off" tabindex="-1">
                <div class="form-field">
                    <label for="dogpark-park"><?php echo esc_html__('Επιλέξτε πάρκο ή προσθέστε νέο', 'dogpark'); ?></label>
                    <select id="dogpark-park" name="park_id" required>
                        <option value=""><?php echo esc_html__('Επιλέξτε πάρκο...', 'dogpark'); ?></option>
                        <?php foreach (DogPark_Parks::get_all_parks() as $park): ?>
                            <option value="<?php echo esc_attr($park->id); ?>"><?php echo esc_html($park->name); ?></option>
                        <?php endforeach; ?>
                        <option value="new"><?php echo esc_html__('Προσθήκη νέου πάρκου', 'dogpark'); ?></option>
                    </select>
                </div>
                
                <div class="form-field" id="dogpark-new-park-fields" style="display: none;">
                    <label for="dogpark-name"><?php echo esc_html__('Όνομα πάρκου', 'dogpark'); ?></label>
                    <input type="text" id="dogpark-name" name="name">
                    
                    <label for="dogpark-address"><?php echo esc_html__('Τοποθεσία ή σημείο αναφοράς', 'dogpark'); ?></label>
                    <textarea id="dogpark-address" name="address" rows="3" required></textarea>
                </div>
                
                <div class="form-field">
                    <label><?php echo esc_html__('Σκιά', 'dogpark'); ?></label>
                    <select name="shade" required>
                        <option value="unknown"><?php echo esc_html__('Άγνωστο', 'dogpark'); ?></option>
                        <option value="good"><?php echo esc_html__('Καλή', 'dogpark'); ?></option>
                        <option value="partial"><?php echo esc_html__('Μερική', 'dogpark'); ?></option>
                        <option value="bad"><?php echo esc_html__('Κακή', 'dogpark'); ?></option>
                    </select>
                </div>
                
                <div class="form-field">
                    <label>
                        <input type="checkbox" name="water" value="1">
                        <?php echo esc_html__('Υπάρχει νερό διαθέσιμο;', 'dogpark'); ?>
                    </label>
                </div>
                
                <div class="form-field">
                    <label><?php echo esc_html__('Αποχέτευση', 'dogpark'); ?></label>
                    <select name="drainage" required>
                        <option value="unknown"><?php echo esc_html__('Άγνωστο', 'dogpark'); ?></option>
                        <option value="good"><?php echo esc_html__('Καλή', 'dogpark'); ?></option>
                        <option value="moderate"><?php echo esc_html__('Μέτρια', 'dogpark'); ?></option>
                        <option value="bad"><?php echo esc_html__('Κακή', 'dogpark'); ?></option>
                    </select>
                </div>
                
                <div class="form-field">
                    <label><?php echo esc_html__('Φωτισμός', 'dogpark'); ?></label>
                    <select name="lighting" required>
                        <option value="unknown"><?php echo esc_html__('Άγνωστο', 'dogpark'); ?></option>
                        <option value="good"><?php echo esc_html__('Καλός', 'dogpark'); ?></option>
                        <option value="bad"><?php echo esc_html__('Κακός', 'dogpark'); ?></option>
                    </select>
                </div>
                
                <div class="form-field">
                    <label for="dogpark-email"><?php echo esc_html__('Email (για ενημερώσεις)', 'dogpark'); ?></label>
                    <input type="email" id="dogpark-email" name="email">
                </div>
                
                <div class="form-field">
                    <label for="dogpark-notes"><?php echo esc_html__('Σχόλια', 'dogpark'); ?></label>
                    <textarea id="dogpark-notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="form-field">
                    <label>
                        <input type="checkbox" name="privacy" required>
                        <?php echo esc_html__('Δέχομαι να λαμβάνω έως 2 emails το μήνα για ενημερώσεις', 'dogpark'); ?>
                    </label>
                </div>
                
                <button type="submit"><?php echo esc_html__('Υποβολή', 'dogpark'); ?></button>
            </form>
            
            <div id="dogpark-submission-message" style="display: none;">
                <?php echo esc_html__('Τέλεια! Η πρότασή σου καταγράφηκε. Αν το πάρκο αξίζει, σύντομα θα το βρεις εδώ! 🐾', 'dogpark'); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private static function get_suggestion($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dogpark_suggestions WHERE id = %d", $id));
    }
}