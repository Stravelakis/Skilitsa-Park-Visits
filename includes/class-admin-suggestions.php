<?php

class DogPark_Admin_Suggestions {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
    }
    
    public static function add_menu() {
        add_submenu_page(
            'dogpark-settings',
            'Suggestions',
            'Suggestions',
            'manage_options',
            'dogpark-suggestions',
            [__CLASS__, 'render_page']
        );
    }
    
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
        
        switch ($action) {
            case 'view':
                self::render_view_suggestion();
                break;
            case 'approve':
                self::handle_approve_suggestion();
                break;
            case 'reject':
                self::handle_reject_suggestion();
                break;
            default:
                self::render_suggestions_list();
                break;
        }
    }
    
    private static function render_suggestions_list() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- static query, no variables, table name only.
        $suggestions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dogpark_suggestions ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1>Dog Park Suggestions</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Park</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suggestions as $suggestion): ?>
                        <tr>
                            <td><?php echo esc_html($suggestion->id); ?></td>
                            <td>
                                <?php
                                if ($suggestion->park_id) {
                                    $park = DogPark_Parks::get_park_by_id($suggestion->park_id);
                                    echo esc_html($park ? $park->name : 'Deleted Park');
                                } else {
                                    echo 'New Park';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($suggestion->name ?: 'N/A'); ?></td>
                            <td><?php echo esc_html($suggestion->email ?: 'N/A'); ?></td>
                            <td><?php echo esc_html(ucfirst($suggestion->status)); ?></td>
                            <td><?php echo esc_html($suggestion->created_at); ?></td>
                            <td>
                                <a href="?page=dogpark-suggestions&action=view&id=<?php echo esc_attr($suggestion->id); ?>">View</a> |
                                <?php if ($suggestion->status === 'pending'): ?>
                                    <a href="?page=dogpark-suggestions&action=approve&id=<?php echo esc_attr($suggestion->id); ?>" onclick="return confirm('Approve this suggestion?')">Approve</a> |
                                    <a href="?page=dogpark-suggestions&action=reject&id=<?php echo esc_attr($suggestion->id); ?>" onclick="return confirm('Reject this suggestion?')">Reject</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private static function render_view_suggestion() {
        global $wpdb;
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $suggestion = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dogpark_suggestions WHERE id = %d", $id));
        
        if (!$suggestion) {
            wp_die(__('Suggestion not found.'));
        }
        
        $park = $suggestion->park_id ? DogPark_Parks::get_park_by_id($suggestion->park_id) : null;
        
        ?>
        <div class="wrap">
            <h1>View Suggestion</h1>
            
            <div class="card">
                <h2>Suggestion Details</h2>
                <table class="form-table">
                    <tr>
                        <th>ID</th>
                        <td><?php echo esc_html($suggestion->id); ?></td>
                    </tr>
                    <tr>
                        <th>Park</th>
                        <td>
                            <?php if ($park): ?>
                                <?php echo esc_html($park->name); ?> (ID: <?php echo esc_html($park->id); ?>)
                            <?php else: ?>
                                New Park
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Name</th>
                        <td><?php echo esc_html($suggestion->name ?: 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo esc_html($suggestion->email ?: 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Location</th>
                        <td><?php echo esc_html($suggestion->address); ?></td>
                    </tr>
                    <tr>
                        <th>Shade</th>
                        <td><?php echo esc_html(ucfirst($suggestion->shade)); ?></td>
                    </tr>
                    <tr>
                        <th>Water</th>
                        <td><?php echo $suggestion->water ? 'Yes' : 'No'; ?></td>
                    </tr>
                    <tr>
                        <th>Drainage</th>
                        <td><?php echo esc_html(ucfirst($suggestion->drainage)); ?></td>
                    </tr>
                    <tr>
                        <th>Lighting</th>
                        <td><?php echo esc_html(ucfirst($suggestion->lighting)); ?></td>
                    </tr>
                    <tr>
                        <th>Notes</th>
                        <td><?php echo esc_html($suggestion->notes ?: 'None'); ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><?php echo esc_html(ucfirst($suggestion->status)); ?></td>
                    </tr>
                    <tr>
                        <th>Date</th>
                        <td><?php echo esc_html($suggestion->created_at); ?></td>
                    </tr>
                </table>
            </div>
            
            <?php if ($suggestion->status === 'pending'): ?>
                <div class="card">
                    <h2>Approve Suggestion</h2>
                    <form method="post" action="?page=dogpark-suggestions&action=approve&id=<?php echo esc_attr($suggestion->id); ?>">
                        <?php wp_nonce_field('dogpark_approve_suggestion'); ?>
                        
                        <div class="form-field">
                            <label for="latitude">Latitude</label>
                            <input type="text" id="latitude" name="latitude" required>
                        </div>
                        
                        <div class="form-field">
                            <label for="longitude">Longitude</label>
                            <input type="text" id="longitude" name="longitude" required>
                        </div>
                        
                        <p class="description">
                            <?php echo __('Enter coordinates for the new park or updated coordinates for the existing park.', 'dogpark'); ?><br>
                            <?php echo __('Use Google Maps or OpenStreetMap to find the coordinates.', 'dogpark'); ?>
                        </p>
                        
                        <button type="submit" class="button button-primary">Approve Suggestion</button>
                    </form>
                </div>
            <?php endif; ?>
            
            <a href="?page=dogpark-suggestions" class="button">Back to List</a>
        </div>
        <?php
    }
    
    private static function handle_approve_suggestion() {
        global $wpdb;
        
        if (!isset($_GET['id']) || !wp_verify_nonce($_POST['_wpnonce'], 'dogpark_approve_suggestion')) {
            wp_die(__('Invalid request.'));
        }
        
        $id = intval($_GET['id']);
        $suggestion = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dogpark_suggestions WHERE id = %d", $id));
        
        if (!$suggestion || $suggestion->status !== 'pending') {
            wp_die(__('Suggestion not found or already processed.'));
        }
        
        $latitude = sanitize_text_field($_POST['latitude']);
        $longitude = sanitize_text_field($_POST['longitude']);
        
        if ($suggestion->park_id) {
            // Update existing park
            DogPark_Parks::update_park($suggestion->park_id, [
                'shade' => $suggestion->shade,
                'water' => $suggestion->water,
                'drainage' => $suggestion->drainage,
                'lighting' => $suggestion->lighting
            ]);
        } else {
            // Add new park
            $park_id = DogPark_Parks::insert_park([
                'name' => $suggestion->name,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'shade' => $suggestion->shade,
                'water' => $suggestion->water,
                'drainage' => $suggestion->drainage,
                'lighting' => $suggestion->lighting,
                'notes' => $suggestion->address
            ]);
        }
        
        // Update suggestion status
        $wpdb->update($wpdb->prefix . 'dogpark_suggestions', ['status' => 'approved'], ['id' => $id]);
        
        // Notify user if email provided
        if ($suggestion->email && get_option('dogpark_admin_emails')) {
            $subject = __('Your Dog Park Suggestion Has Been Approved', 'dogpark');
            $message = __('Thank you! Your suggestion has been approved and added to our system.', 'dogpark');
            wp_mail($suggestion->email, $subject, $message);
        }
        
        wp_redirect(admin_url('admin.php?page=dogpark-suggestions'));
        exit;
    }
    
    private static function handle_reject_suggestion() {
        global $wpdb;
        
        if (!isset($_GET['id']) || !wp_verify_nonce($_GET['_wpnonce'], 'dogpark_reject_suggestion')) {
            wp_die(__('Invalid request.'));
        }
        
        $id = intval($_GET['id']);
        $wpdb->update($wpdb->prefix . 'dogpark_suggestions', ['status' => 'rejected'], ['id' => $id]);
        
        // Notify user if email provided
        $suggestion = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dogpark_suggestions WHERE id = %d", $id));
        if ($suggestion->email && get_option('dogpark_admin_emails')) {
            $subject = __('Your Dog Park Suggestion', 'dogpark');
            $message = __('Thank you for your suggestion, but it has been rejected.', 'dogpark');
            wp_mail($suggestion->email, $subject, $message);
        }
        
        wp_redirect(admin_url('admin.php?page=dogpark-suggestions'));
        exit;
    }
}

DogPark_Admin_Suggestions::init();