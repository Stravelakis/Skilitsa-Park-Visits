<?php

class DogPark_Admin_Parks {
	
	public static function init() {
		add_action('admin_menu', [__CLASS__, 'add_menu']);
		add_action('admin_init', [__CLASS__, 'register_settings']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
	}
	
	public static function add_menu() {
		add_submenu_page(
			'dogpark-settings',
			'Πάρκα Σκύλων',
			'Πάρκα Σκύλων',
			'manage_options',
			'dogpark-parks',
			[__CLASS__, 'render_page']
		);
	}
	
	public static function register_settings() {
		// No settings to register - we handle form submissions directly
	}
	
	public static function enqueue_scripts($hook) {
		if ($hook !== 'dogpark_settings_page_dogpark-parks') {
			return;
		}
		wp_enqueue_script('dogpark-admin-parks', DOGPARK_PLUGIN_URL . 'assets/js/admin-parks.js', ['jquery'], DOGPARK_VERSION, true);
		wp_localize_script('dogpark-admin-parks', 'DogParkAdmin', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('dogpark_parks_nonce'),
			'shade_options' => ['good' => 'Καλή', 'partial' => 'Μερική', 'bad' => 'Κακή', 'unknown' => 'Άγνωστη'],
			'water_options' => ['1' => 'Ναι', '0' => 'Όχι', '' => 'Άγνωστο'],
			'drainage_options' => ['good' => 'Καλή', 'moderate' => 'Μέτρια', 'bad' => 'Κακή', 'unknown' => 'Άγνωστη'],
			'lighting_options' => ['good' => 'Καλός', 'bad' => 'Κακός', 'unknown' => 'Άγνωστος'],
		]);
	}
	
	public static function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
		
		$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
		$park_id = isset($_GET['park_id']) ? intval($_GET['park_id']) : 0;
		
		// Handle form submissions
		if (isset($_POST['dogpark_park_action'])) {
			check_admin_referer('dogpark_park_action');
			self::handle_form_submission();
			// Redirect to clean URL
			wp_redirect(admin_url('admin.php?page=dogpark-parks'));
			exit;
		}
		
		switch ($action) {
			case 'edit':
				self::render_edit_form($park_id);
				break;
			case 'delete':
				self::handle_delete($park_id);
				break;
			case 'add':
			default:
				self::render_list();
				break;
		}
	}
	
	private static function handle_form_submission() {
		global $wpdb;
		
		$park_id = isset($_POST['park_id']) ? intval($_POST['park_id']) : 0;
		$is_new = ($park_id === 0);
		
		$data = [
			'name' => sanitize_text_field($_POST['name']),
			'latitude' => sanitize_text_field($_POST['latitude']),
			'longitude' => sanitize_text_field($_POST['longitude']),
			'shade' => sanitize_text_field($_POST['shade']),
			'water' => ($_POST['water'] === '1') ? 1 : (($_POST['water'] === '0') ? 0 : null),
			'drainage' => sanitize_text_field($_POST['drainage']),
			'lighting' => sanitize_text_field($_POST['lighting']),
			'notes' => sanitize_textarea_field($_POST['notes']),
		];
		
		// Validate coordinates
		if (!self::validate_coordinates($data['latitude'], $data['longitude'])) {
			add_settings_error('dogpark_parks', 'invalid_coords', 'Μη έγκυρες συντεταγμένες. Χρησιμοποιήστε δεκαδικό μορφή (π.χ. 37.997200, 23.738100).');
			return;
		}
		
		if ($is_new) {
			$park_id = DogPark_Parks::insert_park($data);
			add_settings_error('dogpark_parks', 'park_created', 'Το πάρκο δημιουργήθηκε επιτυχώς.', 'success');
		} else {
			DogPark_Parks::update_park($park_id, $data);
			add_settings_error('dogpark_parks', 'park_updated', 'Το πάρκο ενημερώθηκε επιτυχώς.', 'success');
		}
	}
	
	private static function handle_delete($park_id) {
		if (!$park_id) {
			return;
		}
		
		check_admin_referer('dogpark_delete_park_' . $park_id);
		
		DogPark_Parks::delete_park($park_id);
		// Also clear cache for this park
		DogPark_Cache::delete_cache($park_id, current_time('Y-m-d'));
		DogPark_Cache::delete_cache($park_id, date('Y-m-d', strtotime('+1 day')));
		
		add_settings_error('dogpark_parks', 'park_deleted', 'Το πάρκο διαγράφηκε επιτυχώς.', 'success');
		wp_redirect(admin_url('admin.php?page=dogpark-parks'));
		exit;
	}
	
	private static function validate_coordinates($lat, $lng) {
		if (empty($lat) || empty($lng)) {
			return false;
		}
		$lat_float = floatval($lat);
		$lng_float = floatval($lng);
		return ($lat_float >= -90 && $lat_float <= 90 && $lng_float >= -180 && $lng_float <= 180);
	}
	
	private static function render_list() {
		global $wpdb;
		
		$parks = DogPark_Parks::get_all_parks();
		$today = current_time('Y-m-d');
		
		?>
		<div class="wrap">
			<h1>Πάρκα Σκύλων</h1>
			
			<a href="<?php echo admin_url('admin.php?page=dogpark-parks&action=add'); ?>" class="button button-primary" style="margin-bottom: 20px;">
				Προσθήκη Νέου Πάρκου
			</a>
			
			<?php settings_errors('dogpark_parks'); ?>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 50px;">ID</th>
						<th>Όνομα Πάρκου</th>
						<th>Δήμος / Περιοχή</th>
						<th>Συντεταγμένες</th>
						<th>Σκιά</th>
						<th>Νερό</th>
						<th>Αποχέτευση</th>
						<th>Φωτισμός</th>
						<th>Πρόγνωση σήμερα</th>
						<th style="width: 150px;">Ενέργειες</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($parks as $park): ?>
						<?php
						$cache = DogPark_Cache::get_cache($park->id, $today);
						$forecast_status = $cache ? '<span style="color: green;">✓ Διαθέσιμη</span>' : '<span style="color: orange;">✗ Χωρίς δεδομένα</span>';
						?>
						<tr>
							<td><?php echo esc_html($park->id); ?></td>
							<td><strong><?php echo esc_html($park->name); ?></strong></td>
							<td><?php echo esc_html(self::extract_municipality($park->name)); ?></td>
							<td><?php echo esc_html($park->latitude . ', ' . $park->longitude); ?></td>
							<td><?php echo esc_html(ucfirst(self::translate_shade($park->shade))); ?></td>
							<td><?php echo $park->water ? 'Ναι' : ($park->water === false ? 'Όχι' : 'Άγνωστο'); ?></td>
							<td><?php echo esc_html(ucfirst(self::translate_drainage($park->drainage))); ?></td>
							<td><?php echo esc_html(ucfirst(self::translate_lighting($park->lighting))); ?></td>
							<td><?php echo $forecast_status; ?></td>
							<td>
								<a href="<?php echo admin_url('admin.php?page=dogpark-parks&action=edit&park_id=' . $park->id); ?>" class="button button-small">Επεξεργασία</a>
								<a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dogpark-parks&action=delete&park_id=' . $park->id), 'dogpark_delete_park_' . $park->id); ?>" class="button button-small button-link-delete" onclick="return confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτό το πάρκο;')">Διαγραφή</a>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($parks)): ?>
						<tr>
							<td colspan="10" style="text-align: center; padding: 40px;">Δεν υπάρχουν καταχωρισμένα πάρκα. Πατήστε "Προσθήκη Νέου Πάρκου" για να ξεκινήσετε.</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
	
	private static function render_edit_form($park_id = 0) {
		$is_new = ($park_id === 0);
		$park = null;
		
		if (!$is_new) {
			$park = DogPark_Parks::get_park_by_id($park_id);
			if (!$park) {
				wp_die(__('Park not found.'));
			}
		}
		
		$defaults = [
			'name' => '',
			'latitude' => '',
			'longitude' => '',
			'shade' => 'unknown',
			'water' => '',
			'drainage' => 'unknown',
			'lighting' => 'unknown',
			'notes' => '',
		];
		
		if ($park) {
			$defaults['name'] = $park->name;
			$defaults['latitude'] = $park->latitude;
			$defaults['longitude'] = $park->longitude;
			$defaults['shade'] = $park->shade;
			$defaults['water'] = $park->water === null ? '' : ($park->water ? '1' : '0');
			$defaults['drainage'] = $park->drainage;
			$defaults['lighting'] = $park->lighting;
			$defaults['notes'] = $park->notes;
		}
		
		?>
		<div class="wrap">
			<h1><?php echo $is_new ? 'Προσθήκη Νέου Πάρκου' : 'Επεξεργασία Πάρκου: ' . esc_html($park->name); ?></h1>
			
			<a href="<?php echo admin_url('admin.php?page=dogpark-parks'); ?>" class="button" style="margin-bottom: 20px;">← Επιστροφή στη λίστα</a>
			
			<?php settings_errors('dogpark_parks'); ?>
			
			<form method="post" action="<?php echo admin_url('admin.php?page=dogpark-parks' . (!$is_new ? '&action=edit&park_id=' . $park_id : '&action=add')); ?>">
				<?php wp_nonce_field('dogpark_park_action'); ?>
				<input type="hidden" name="park_id" value="<?php echo esc_attr($park_id); ?>">
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="name">Όνομα Πάρκου <span style="color: red;">*</span></label></th>
						<td>
							<input type="text" id="name" name="name" value="<?php echo esc_attr($defaults['name']); ?>" required style="width: 400px;">
							<p class="description">Π.χ. Serafeio Flagship Park (Athens (Petralona))</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="latitude">Γεωγραφικό Πλάτος (Latitude) <span style="color: red;">*</span></label></th>
						<td>
							<input type="text" id="latitude" name="latitude" value="<?php echo esc_attr($defaults['latitude']); ?>" required step="any" style="width: 200px;">
							<p class="description">Δεκαδικός αριθμός, π.χ. 37.997200</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="longitude">Γεωγραφικό Μήκος (Longitude) <span style="color: red;">*</span></label></th>
						<td>
							<input type="text" id="longitude" name="longitude" value="<?php echo esc_attr($defaults['longitude']); ?>" required step="any" style="width: 200px;">
							<p class="description">Δεκαδικός αριθμός, π.χ. 23.738100</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="shade">Σκιά</label></th>
						<td>
							<select id="shade" name="shade">
								<option value="unknown" <?php selected($defaults['shade'], 'unknown'); ?>>Άγνωστη</option>
								<option value="good" <?php selected($defaults['shade'], 'good'); ?>>Καλή</option>
								<option value="partial" <?php selected($defaults['shade'], 'partial'); ?>>Μερική</option>
								<option value="bad" <?php selected($defaults['shade'], 'bad'); ?>>Κακή</option>
							</select>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="water">Νερό Διαθέσιμο</label></th>
						<td>
							<select id="water" name="water">
								<option value="" <?php selected($defaults['water'], ''); ?>>Άγνωστο</option>
								<option value="1" <?php selected($defaults['water'], '1'); ?>>Ναι</option>
								<option value="0" <?php selected($defaults['water'], '0'); ?>>Όχι</option>
							</select>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="drainage">Αποχέτευση</label></th>
						<td>
							<select id="drainage" name="drainage">
								<option value="unknown" <?php selected($defaults['drainage'], 'unknown'); ?>>Άγνωστη</option>
								<option value="good" <?php selected($defaults['drainage'], 'good'); ?>>Καλή</option>
								<option value="moderate" <?php selected($defaults['drainage'], 'moderate'); ?>>Μέτρια</option>
								<option value="bad" <?php selected($defaults['drainage'], 'bad'); ?>>Κακή</option>
							</select>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="lighting">Φωτισμός</label></th>
						<td>
							<select id="lighting" name="lighting">
								<option value="unknown" <?php selected($defaults['lighting'], 'unknown'); ?>>Άγνωστος</option>
								<option value="good" <?php selected($defaults['lighting'], 'good'); ?>>Καλός</option>
								<option value="bad" <?php selected($defaults['lighting'], 'bad'); ?>>Κακός</option>
							</select>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><label for="notes">Σημειώσεις</label></th>
						<td>
							<textarea id="notes" name="notes" rows="5" style="width: 100%; max-width: 600px;"><?php echo esc_textarea($defaults['notes']); ?></textarea>
							<p class="description">Περιγραφή, εξοπλισμός, συνδέσμοι, email επικοινωνίας, κλπ.</p>
						</td>
					</tr>
				</table>
				
				<?php wp_nonce_field('dogpark_park_action'); ?>
				<input type="hidden" name="park_id" value="<?php echo esc_attr($park_id); ?>">
				
				<?php submit_button($is_new ? 'Προσθήκη Πάρκου' : 'Ενημέρωση Πάρκου', 'primary', 'dogpark_park_action'); ?>
			</form>
		</div>
		<?php
	}
	
	private static function extract_municipality($name) {
		if (preg_match('/\((.*?)\)$/', $name, $matches)) {
			return $matches[1];
		}
		return '';
	}
	
	private static function translate_shade($shade) {
		$map = ['good' => 'καλή', 'partial' => 'μερική', 'bad' => 'κακή', 'unknown' => 'αγνωστη'];
		return $map[$shade] ?? $shade;
	}
	
	private static function translate_drainage($drainage) {
		$map = ['good' => 'καλή', 'moderate' => 'μέτρια', 'bad' => 'κακή', 'unknown' => 'αγνωστη'];
		return $map[$drainage] ?? $drainage;
	}
	
	private static function translate_lighting($lighting) {
		$map = ['good' => 'καλός', 'bad' => 'κακός', 'unknown' => 'αγνωστος'];
		return $map[$lighting] ?? $lighting;
	}
}

DogPark_Admin_Parks::init();