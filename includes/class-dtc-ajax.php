<?php
if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_dtc_calculate', 'dtc_handle_ajax_calculation');
add_action('wp_ajax_nopriv_dtc_calculate', 'dtc_handle_ajax_calculation');
add_action('wp_ajax_dtc_save_settings', 'dtc_handle_save_settings');
add_action('wp_ajax_dtc_send_quote_email', 'dtc_handle_send_quote_email');
add_action('wp_ajax_nopriv_dtc_send_quote_email', 'dtc_handle_send_quote_email');

function dtc_money_format($num) {
    $num = round($num);
    $str = (string)$num;
    if (strlen($str) <= 3) return $str;
    $last3 = substr($str, -3);
    $rest = substr($str, 0, -3);
    $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
    return $rest . ',' . $last3;
}

function dtc_handle_save_settings() {
    check_ajax_referer('dtc_nonce', 'nonce');
    if (!dtc_is_privileged_user()) wp_send_json_error('Unauthorized');

    $config = json_decode(stripslashes($_POST['new_config']), true);
    if ($config && isset($config['destinations'])) {
        update_option('dtc_config', wp_json_encode($config));
        wp_send_json_success('Settings saved successfully!');
    } else {
        wp_send_json_error('Invalid configuration format.');
    }
}

function dtc_handle_ajax_calculation() {
    check_ajax_referer('dtc_nonce', 'nonce');
    parse_str($_POST['form_data'], $data);
    
    $config_json = get_option('dtc_config', json_encode(dtc_get_default_config()));
    $full_cfg = json_decode($config_json, true);

    $dest = sanitize_text_field($data['destination'] ?? '');
    if (!isset($full_cfg['destinations'][$dest])) wp_send_json_error('Invalid Destination');
    
    $cfg = $full_cfg['destinations'][$dest];

    $pax = max(1, intval($data['tour_pax'] ?? 4)); $tp = $pax; 
    
    $hotel_cat = sanitize_text_field($data['hotel_category'] ?? '');
    $hotel_name = $cfg['hotel_categories'][$hotel_cat]['name'] ?? 'Hotel';
    $hotel_percent = 0;
    if (isset($cfg['hotel_categories'][$hotel_cat]['percent'])) {
        $hotel_percent = floatval($cfg['hotel_categories'][$hotel_cat]['percent']);
    } elseif (isset($cfg['hotel_categories'][$hotel_cat]['multiplier'])) {
        $hotel_percent = ($cfg['hotel_categories'][$hotel_cat]['multiplier'] - 1) * 100;
    }

    $tour_date = sanitize_text_field($data['tour_date'] ?? date('Y-m-d'));
    
    $surcharge_percent = 0; $season_name = 'Normal Season';
    $m_d = date('m-d', strtotime($tour_date));
    foreach ($cfg['seasonal_surcharges'] as $season) {
        $start = $season['start']; $end = $season['end'];
        if ($start > $end) {
            if ($m_d >= $start || $m_d <= $end) { $surcharge_percent = $season['surcharge_percent']; $season_name = $season['name']; break; }
        } else {
            if ($m_d >= $start && $m_d <= $end) { $surcharge_percent = $season['surcharge_percent']; $season_name = $season['name']; break; }
        }
    }
    
    $pickup_loc = sanitize_text_field($data['pickup_location'] ?? '');
    $trip_days = max(1, intval($data['trip_days'] ?? 7));
    $serv = sanitize_text_field($data['service_type'] ?? 'both');
    $service_name = $cfg['service_types'][$serv] ?? 'Package';

    $mapped_vehicles = [];
    foreach ($cfg['vehicles'] as $k => $v) {
        $mapped_vehicles[$k] = $v;
        $daily = is_array($v['price_per_day']) ? (isset($v['price_per_day'][$pickup_loc]) ? $v['price_per_day'][$pickup_loc] : (reset($v['price_per_day']) ?: 0)) : $v['price_per_day'];
        $mapped_vehicles[$k]['price'] = $daily * $trip_days;
    }

    $vr = [];
    if ($serv === 'cab') {
        $vr[] = ['html' => '<span class="u-badge badge-room-std">N/A</span>', 'cost' => 0];
    } else {
        if (($data['room_mode'] ?? 'auto') === 'custom') {
            $vr[] = dtc_calc_custom($data['custom_rooms'] ?? [], $cfg['rooms'], 'badge-room-std');
        } else {
            $vr = dtc_calc_auto($pax, $cfg['rooms'], $data['room_pref'] ?? 'any', 'badge-room-std', false);
        }
    }

    $vv = [];
    if ($serv === 'hotel') {
        $vv[] = ['html' => '<span class="u-badge badge-veh">N/A</span>', 'cost' => 0];
    } else {
        if (($data['vehicle_mode'] ?? 'auto') === 'custom') {
            $vv[] = dtc_calc_custom($data['custom_vehicles'] ?? [], $mapped_vehicles, 'badge-veh');
        } else {
            $vv = dtc_calc_auto($pax, $mapped_vehicles, $data['cab_pref'] ?? 'any', 'badge-veh', true);
        }
    }

    $results = [];
    foreach($vv as $vobj) { 
        foreach($vr as $robj) {
            if (!$vobj || !$robj) continue; 
            
            $base_pax_cost = ($serv === 'cab') ? 0 : $cfg['base_cost_per_pax'];
            $base_cost = $vobj['cost'] + $robj['cost'] + ($tp * $base_pax_cost);
            
            $active_multiplier = ($serv === 'cab') ? 1.0 : (1 + ($hotel_percent / 100));
            $agent_price = $base_cost * $active_multiplier * (1 + ($surcharge_percent / 100)); 
            
            $profit_margin = ($serv === 'cab' || $serv === 'hotel') ? ($cfg['profit_margin_per_pax'] * 0.5) : $cfg['profit_margin_per_pax'];
            $pp = ceil(($agent_price / $tp + $profit_margin) / 500) * 500;
            
            $gst_pp = round($pp * 0.05);
            $total_pp = $pp + $gst_pp;
            $grand_total = $total_pp * $tp;
            $profit = $grand_total - $agent_price;

            $results[] = [
                'r_h' => $robj['html'], 
                'v_h' => $vobj['html'], 
                'base_pp' => $pp,
                'gst_pp' => $gst_pp,
                'tot_pp' => $total_pp,
                'grand_total' => $grand_total,
                'agent_price' => $agent_price,
                'profit' => $profit
            ];
        }
    }

    $results = array_map("unserialize", array_unique(array_map("serialize", $results)));
    usort($results, function($a,$b){ return $a['grand_total'] <=> $b['grand_total']; });

    if (empty($results)) wp_send_json_success('<div style="padding:15px;color:red;text-align:center;">No valid combinations found.</div>');

    $is_privileged = dtc_is_privileged_user();

    ob_start();
    echo '<div class="res-container"><table class="tour-table"><thead><tr>';
    echo '<th>Rooms Breakdown</th><th>Vehicle Type</th><th style="min-width:110px;">Price PP</th>';
    if ($is_privileged) echo '<th>Agent</th><th>Profit</th>';
    echo '</tr></thead><tbody id="dtc-table-body">';

    $end_date_str = date('d-M-Y', strtotime($tour_date . ' + ' . ($trip_days - 1) . ' days'));

    foreach ($results as $row) {
        $grand_base = $row['base_pp'] * $tp;
        $grand_gst = $row['gst_pp'] * $tp;
        $grand_final = $row['tot_pp'] * $tp;

        $tooltip = "
        <div class='dtc-tooltip' style='text-align:left;'>
            <div style='display:flex; justify-content:space-between; margin-bottom:4px; gap:15px;'><span>Total {$tp} Pax:</span> <span>₹".dtc_money_format($grand_base)."</span></div>
            <div style='display:flex; justify-content:space-between; margin-bottom:4px; gap:15px;'><span>GST (5%):</span> <span>₹".dtc_money_format($grand_gst)."</span></div>
            <div style='display:flex; justify-content:space-between; border-top:1px solid rgba(255,255,255,0.3); margin-top:4px; padding-top:4px; font-weight:bold; gap:15px;'><span>Total:</span> <span>₹".dtc_money_format($grand_final)."</span></div>
        </div>";

        $r_text = strip_tags(str_replace('</span>', ', ', $row['r_h']));
        $v_text = strip_tags(str_replace('</span>', ', ', $row['v_h']));
        $r_text = rtrim(trim($r_text), ',');
        $v_text = rtrim(trim($v_text), ',');

        $row_data = [
            'dest_id' => $dest,
            'dest_name' => $cfg['name'],
            'pax' => $tp,
            'days' => $trip_days,
            'start' => date('d-M-Y', strtotime($tour_date)),
            'end' => $end_date_str,
            'service' => $service_name,
            'hotel' => $hotel_name,
            'rooms' => $r_text,
            'veh' => $v_text,
            'base_pp' => $row['base_pp'],
            'gst_pp' => $row['gst_pp'],
            'tot_pp' => $row['tot_pp'],
            'grand_total' => $grand_final
        ];
        $json_attr = esc_attr(json_encode($row_data));

        echo "<tr class='dtc-res-row' data-info='{$json_attr}'>";
        echo "<td>{$row['r_h']}</td><td>{$row['v_h']}</td>";
        echo "<td>
                <div style='display:flex; align-items:center; gap:6px;'>
                    <div style='font-weight:bold; color:#16a34a; font-size:14px;'>₹".dtc_money_format($row['base_pp'])."</div>
                    <div class='dtc-info-wrap' tabindex='0'>
                        <span class='dtc-info-icon'>i</span>
                        {$tooltip}
                    </div>
                </div>
              </td>";
        
        if ($is_privileged) {
            echo "<td style='background:#fffbea; color:#b45309;'>₹".dtc_money_format($row['agent_price'])."</td>";
            echo "<td style='background:#f0fdf4; color:#16a34a; font-weight:bold;'>₹".dtc_money_format($row['profit'])."</td>";
        }
        echo "</tr>";
    }
    echo '</tbody></table></div>';
    
    echo '<div id="dtc-pagination" style="text-align:center; padding:15px 0;"></div>';
    
    echo "<script>
        (function(){
            var rows = document.querySelectorAll('#dtc-table-body tr.dtc-res-row');
            var perPage = 5;
            var pages = Math.ceil(rows.length / perPage);
            
            window.dtcShowPage = function(p) {
                rows.forEach(function(row, i) {
                    row.style.display = (i >= (p-1)*perPage && i < p*perPage) ? '' : 'none';
                });
                
                if(pages > 1) {
                    var html = '';
                    for(var i=1; i<=pages; i++) {
                        var bg = (i===p) ? '#0073aa' : '#f1f1f1';
                        var col = (i===p) ? '#fff' : '#333';
                        html += '<button type=\"button\" onclick=\"dtcShowPage('+i+')\" style=\"margin:0 4px; padding:6px 12px; cursor:pointer; background:'+bg+'; color:'+col+'; border:1px solid #ccc; border-radius:4px; font-weight:bold;\">'+i+'</button>';
                    }
                    document.getElementById('dtc-pagination').innerHTML = html;
                }
            };
            if(rows.length > 0) { dtcShowPage(1); }
        })();
    </script>";

    wp_send_json_success(ob_get_clean());
}

function dtc_calc_custom($keys, $settings, $badge_class) {
    if (empty($keys)) return null;
    $counts = array_count_values((array)$keys);
    $cost = 0; $badges = "";
    foreach ($counts as $k => $qty) {
        if (!isset($settings[$k])) continue;
        $cost += $qty * $settings[$k]['price'];
        $badges .= "<span class='u-badge {$badge_class}'>{$qty}x {$settings[$k]['name']}</span>";
    }
    return ['html' => "<div class='badge-list'>$badges</div>", 'cost' => $cost];
}

function dtc_calc_auto($target_pax, $settings, $pref, $badge_class, $is_veh) {
    $items = []; foreach ($settings as $k => $v) { if ($pref === 'any' || $pref === $k) $items[$k] = $v; }
    $results = []; $keys = array_keys($items);
    if (empty($keys)) return [];
    
    $solve = function($idx, $cap, $combo) use (&$solve, &$results, $target_pax, $items, $keys, $is_veh) {
        if ($cap >= $target_pax) {
            $empty = $cap - $target_pax;
            if ($is_veh && $pref === 'any' && $empty > 4) return;
            if (!$is_veh && $empty > 2) return;
            $is_min = true;
            foreach ($combo as $k => $qty) { if ($qty > 0 && ($cap - $items[$k]['capacity'] >= $target_pax)) { $is_min = false; break; } }
            if ($is_min) $results[] = $combo;
            return;
        }
        for ($i = $idx; $i < count($keys); $i++) {
            $k = $keys[$i]; $combo[$k] = ($combo[$k] ?? 0) + 1;
            $solve($i, $cap + $items[$k]['capacity'], $combo);
            $combo[$k]--;
        }
    };
    $solve(0, 0, []);

    $formatted = [];
    foreach ($results as $combo) {
        $cost = 0; $badges = "";
        foreach ($combo as $k => $qty) {
            if ($qty <= 0) continue;
            $cost += $qty * $items[$k]['price'];
            $badges .= "<span class='u-badge {$badge_class}'>{$qty}x {$items[$k]['name']}</span>";
        }
        $formatted[] = ['html' => "<div class='badge-list'>$badges</div>", 'cost' => $cost];
    }
    return $formatted;
}

function dtc_handle_send_quote_email() {
    check_ajax_referer('dtc_nonce', 'nonce');
    $email = sanitize_email($_POST['email'] ?? '');
    $html_content = stripslashes($_POST['html_content'] ?? '');
    
    if (empty($email)) wp_send_json_error("Please enter a valid email address.");
    if (empty($html_content)) wp_send_json_error("Quotation content is missing.");

    $subject = "Tour Quotation from SOULFUL TOUR & TRAVELS";
    
    // Grab the global BCC setting
    $bcc_email = get_option('dtc_bcc_email', '');
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    // If a BCC email is set, append it to the headers array
    if (!empty($bcc_email) && is_email($bcc_email)) {
        $headers[] = 'Bcc: ' . $bcc_email;
    }
    
    $email_body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
        {$html_content}
        <p style='margin-top: 20px; font-size: 12px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px;'>
            Thank you for choosing us!<br>If you have any questions, please reply to this email.
        </p>
    </div>";

    if (wp_mail($email, $subject, $email_body, $headers)) { 
        wp_send_json_success("Quotation successfully sent to " . $email); 
    } else { 
        wp_send_json_error("Failed to send email. Please check your server's mail configuration."); 
    }
}