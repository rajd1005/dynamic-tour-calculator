<?php
if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', 'dtc_add_settings_page');

function dtc_add_settings_page() {
    add_options_page('Tour Calculator Settings', 'Tour Calculator', 'manage_options', 'dtc-settings', 'dtc_render_settings_page');
}

function dtc_is_privileged_user() {
    if (!is_user_logged_in()) return false;
    $user = wp_get_current_user();
    $allowed_roles = get_option('dtc_privileged_roles', ['administrator']);
    $user_roles = (array) $user->roles;
    return count(array_intersect($allowed_roles, $user_roles)) > 0;
}

function dtc_render_settings_page() {
    
    if (isset($_POST['dtc_import_settings']) && check_admin_referer('dtc_save_action', 'dtc_nonce')) {
        if (!empty($_FILES['dtc_import_file']['tmp_name'])) {
            $imported_data = file_get_contents($_FILES['dtc_import_file']['tmp_name']);
            if (json_decode($imported_data) !== null) {
                update_option('dtc_config', $imported_data);
                echo '<div class="updated"><p>Settings successfully imported!</p></div>';
            } else {
                echo '<div class="error"><p>Invalid JSON file format. Import failed.</p></div>';
            }
        }
    }

    if (isset($_POST['dtc_save_settings']) && check_admin_referer('dtc_save_action', 'dtc_nonce')) {
        $allow_guests = isset($_POST['dtc_allow_guests']) ? 'yes' : 'no';
        update_option('dtc_allow_guests', $allow_guests);
        
        $bcc_email = sanitize_email($_POST['dtc_bcc_email'] ?? '');
        update_option('dtc_bcc_email', $bcc_email);

        $roles = isset($_POST['dtc_roles']) ? array_map('sanitize_text_field', $_POST['dtc_roles']) : [];
        update_option('dtc_privileged_roles', $roles);
        
        $json_data = stripslashes($_POST['dtc_config_json']);
        if (json_decode($json_data) !== null) {
            update_option('dtc_config', $json_data);
            echo '<div class="updated"><p>Settings successfully saved!</p></div>';
        } else {
            echo '<div class="error"><p>Invalid JSON format. Please check your syntax and try again.</p></div>';
        }
    }

    $current_config = get_option('dtc_config', json_encode(dtc_get_default_config(), JSON_PRETTY_PRINT));
    $current_roles = get_option('dtc_privileged_roles', ['administrator']);
    $allow_guests = get_option('dtc_allow_guests', 'no');
    $bcc_email = get_option('dtc_bcc_email', '');
    
    global $wp_roles;
    $all_roles = $wp_roles->get_names();
    ?>
    <div class="wrap">
        <h1>Dynamic Tour Calculator Settings</h1>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('dtc_save_action', 'dtc_nonce'); ?>
            
            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; margin-bottom:20px;">
                <h3>1. General Settings</h3>
                <div style="margin-bottom:15px;">
                    <label>
                        <input type="checkbox" name="dtc_allow_guests" value="yes" <?php checked($allow_guests, 'yes'); ?>>
                        <b>Allow Logged-Out Users (Guests) to use the Calculator.</b><br>
                        <span style="color:#666; font-size:12px; margin-left: 20px;">(Note: Guests will never see the Agent/Profit columns or the Settings Button).</span>
                    </label>
                </div>
                <div style="border-top:1px dashed #ccd0d4; padding-top:15px;">
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">Admin BCC Email (For Quotations):</label>
                    <input type="email" name="dtc_bcc_email" value="<?php echo esc_attr($bcc_email); ?>" placeholder="admin@example.com" style="width:300px; padding:5px;">
                    <br><span style="color:#666; font-size:12px;">A hidden copy of every quotation email sent to customers will be forwarded to this address. Leave blank to disable.</span>
                </div>
            </div>
            
            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; margin-bottom:20px;">
                <h3>2. Privileged Roles</h3>
                <p>Select which user roles are allowed to see the <b>Agent & Profit Columns</b> and the <b>Manage Settings Button</b> on the frontend calculator.</p>
                <div style="display:flex; flex-wrap:wrap; gap:15px; margin-top:10px;">
                    <?php foreach ($all_roles as $role_slug => $role_name): ?>
                        <label style="background:#f0f0f1; padding:5px 10px; border-radius:4px; display:inline-block;">
                            <input type="checkbox" name="dtc_roles[]" value="<?php echo esc_attr($role_slug); ?>" <?php checked(in_array($role_slug, $current_roles)); ?>>
                            <?php echo esc_html($role_name); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; margin-bottom:20px;">
                <h3>3. Import / Export Configuration</h3>
                <p>Export your current settings as a JSON file to keep a backup, or import a previously saved configuration.</p>
                <div style="display:flex; gap:20px; align-items:center;">
                    <div>
                        <button type="button" id="dtc-export-btn" class="button button-secondary" style="font-weight:bold; color:#0073aa;">📥 Download JSON Backup</button>
                    </div>
                    <div style="border-left:1px solid #ccc; padding-left:20px;">
                        <input type="file" name="dtc_import_file" accept=".json">
                        <input type="submit" name="dtc_import_settings" class="button button-secondary" style="font-weight:bold; color:#d63638;" value="📤 Import Configuration">
                    </div>
                </div>
            </div>
            
            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px;">
                <h3>4. Master Configuration JSON</h3>
                <p>This box holds the raw JSON data for all destinations. Changes made via the Frontend Settings GUI automatically update this box.</p>
                <textarea id="dtc_config_json_area" name="dtc_config_json" rows="20" style="width:100%; font-family:monospace; background:#f8f9f9; border:1px solid #ccc; padding:10px;"><?php echo esc_textarea($current_config); ?></textarea>
            </div>
            
            <p class="submit"><input type="submit" name="dtc_save_settings" id="submit" class="button button-primary" value="Save Configuration"></p>
        </form>

        <script>
        document.getElementById('dtc-export-btn').addEventListener('click', function() {
            var data = document.getElementById('dtc_config_json_area').value;
            var blob = new Blob([data], {type: 'application/json'});
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'dtc-calculator-backup-' + new Date().toISOString().slice(0,10) + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });
        </script>
    </div>
    <?php
}

function dtc_get_default_config() {
    return [
        'destinations' => [
            'kashmir' => [
                'name' => 'Kashmir',
                'profit_margin_per_pax' => 6000,
                'service_types' => [
                    'both' => 'Package (Hotel + MAP + Cab)',
                    'hotel' => 'Hotel + MAP Only',
                    'cab' => 'Cab Only'
                ],
                'pickups' => [
                    'srinagar' => 'Srinagar R.S / Airport',
                    'jammu' => 'Jammu R.S / Airport'
                ],
                'stay_locations' => [
                    'srinagar' => 'Srinagar',
                    'gulmarg' => 'Gulmarg',
                    'pahalgam' => 'Pahalgam'
                ],
                'places' => [
                    'srinagar_local' => 'Srinagar Local Sightseeing',
                    'gulmarg' => 'Gulmarg',
                    'pahalgam' => 'Pahalgam',
                    'sonamarg' => 'Sonamarg',
                    'doodhpathri' => 'Doodhpathri'
                ],
                'hotel_categories' => [
                    'budget' => ['name' => 'Budget Hotel + MAP', 'percent' => 0],
                    '3star' => ['name' => '3 Star Hotel + MAP', 'percent' => 75],
                    '5star' => ['name' => '5 Star Hotel + MAP', 'percent' => 150]
                ],
                'rooms' => [
                    'standard' => ['name' => 'Standard Double', 'price' => ['srinagar' => 2500, 'gulmarg' => 3000, 'pahalgam' => 2800], 'capacity' => 2],
                    'triple' => ['name' => 'Double + Extra Bed', 'price' => ['srinagar' => 3500, 'gulmarg' => 4000, 'pahalgam' => 3800], 'capacity' => 3]
                ],
                'vehicles' => [
                    'dzire' => ['name' => 'Dzire', 'capacity' => 4, 'price_per_day' => ['srinagar' => 1800, 'jammu' => 3200]],
                    'innova' => ['name' => 'Innova', 'capacity' => 7, 'price_per_day' => ['srinagar' => 2700, 'jammu' => 4000]]
                ],
                'seasonal_surcharges' => [
                    ['name' => 'Winter (Peak)', 'start' => '12-01', 'end' => '02-28', 'surcharge_percent' => 10]
                ]
            ]
        ]
    ];
}