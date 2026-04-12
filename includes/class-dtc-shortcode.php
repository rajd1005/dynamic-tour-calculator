<?php
if (!defined('ABSPATH')) { exit; }

add_shortcode('dynamic_tour_calculator', 'dtc_render_shortcode');

function dtc_render_shortcode() {
    $allow_guests = get_option('dtc_allow_guests', 'no');
    
    if (!is_user_logged_in() && $allow_guests !== 'yes') {
        return '<div style="padding:20px; background:#fee2e2; border:1px solid #ef4444; color:#b91c1c; border-radius:6px; text-align:center; font-weight:bold;">Access Denied. You must be logged in to use the Tour Calculator.</div>';
    }

    $is_privileged = dtc_is_privileged_user();
    ob_start();
    ?>
    <div id="dtc-app-wrapper" class="dtc-wrapper">
        
        <?php if ($is_privileged): ?>
            <div style="text-align:right; margin-bottom:10px;">
                <button type="button" id="btn-open-settings" style="background:#475569; color:#fff; border:none; padding:8px 15px; border-radius:4px; font-weight:bold; cursor:pointer;">⚙️ Manage Settings</button>
            </div>
        <?php endif; ?>

        <form id="dtc-form">
            <div class="input-master-table">
                <div class="compact-box" style="grid-column: 1 / -1; background:#f8fafc; border-color:#cbd5e1;">
                    <div class="compact-label" style="color:#334155; border-bottom:1px dashed #cbd5e1;">1. Trip Parameters</div>
                    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom: 5px;">
                        <div style="flex:1.5; min-width:150px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Destination</label>
                            <select name="destination" id="ui_destination" class="u-field" style="background:#fefce8; color:#b45309; font-weight:bold;" required></select>
                        </div>
                        <div style="flex:1; min-width:80px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Total Pax</label>
                            <input type="number" name="tour_pax" class="u-field" value="" placeholder="Pax" min="1" required style="font-weight:bold;">
                        </div>
                        <div style="flex:1; min-width:110px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Start Date</label>
                            <input type="date" name="tour_date" class="u-field" value="<?php echo date('Y-m-d', strtotime('tomorrow')); ?>" required>
                        </div>
                        <div style="flex:1; min-width:80px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Total Days</label>
                            <input type="number" name="trip_days" class="u-field" value="" placeholder="Days" min="1" required style="font-weight:bold;">
                        </div>
                        <div style="flex:1.5; min-width:150px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Pickup Location</label>
                            <select name="pickup_location" id="ui_pickup_location" class="u-field" style="font-weight:bold;" required></select>
                        </div>
                        <div style="flex:1.5; min-width:150px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Service Type</label>
                            <select name="service_type" id="ui_service_type" class="u-field" required></select>
                        </div>
                        <div id="ui_hotel_cat_box" style="flex:1.5; min-width:120px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Hotel Category</label>
                            <select name="hotel_category" id="ui_hotel_category" class="u-field" style="background:#f0f7ff; font-weight:600; border-color:#bae6fd; color:#0369a1;"></select>
                        </div>
                    </div>
                </div>

                <div class="compact-box" id="box-rooms" style="grid-column: 1 / -1;">
                    <div class="compact-label" style="display:flex; justify-content:space-between; align-items:center;">
                        <span>2. Night Stay Itinerary</span>
                        <a href="#" class="dtc-toggle-btn" data-target="box-rooms-inner" style="font-size:10px; color:#0284c7; text-transform:none; text-decoration:none; font-weight:bold;">[+ Expand / Edit]</a>
                    </div>
                    <div id="box-rooms-inner" style="display:none; margin-top:5px;">
                        
                        <div class="mini-toggles" style="margin-top: 5px;">
                            <input type="radio" id="r_a" name="room_mode" value="auto" checked> <label for="r_a">AUTO-ASSIGN ROOMS (Best Price)</label>
                            <input type="radio" id="r_c" name="room_mode" value="custom"> <label for="r_c">MANUALLY PICK ROOMS</label>
                        </div>
                        
                        <div id="dtc-nights-counter" style="text-align:center; font-size:11px; font-weight:bold; color:#0369a1; background:#e0f2fe; border:1px solid #7dd3fc; border-radius:4px; padding:6px; margin:8px 0; display:none; transition:0.3s;">
                            Total Required: <span class="tot-n">0</span> Nights &nbsp;|&nbsp; Assigned: <span class="ass-n">0</span> &nbsp;|&nbsp; Remaining: <span class="rem-n">0</span>
                        </div>
                        
                        <div id="dtc-div-r-a">
                            <div id="dtc-auto-stays-list" class="builder-list" style="display:flex; flex-direction:column; gap:6px;"></div>
                            <button type="button" id="dtc-add-auto-stay" class="btn-add" style="margin-top:8px; border:1px dashed #0284c7; background:#f0f9ff;">+ Add Location to Itinerary</button>
                        </div>
                        <div id="dtc-div-r-c" class="hidden">
                            <div id="dtc-list-r" class="builder-list" style="display:flex; flex-direction:column; gap:6px;"></div>
                            <button type="button" id="dtc-add-r" class="btn-add" style="margin-top:8px; border:1px dashed #ea580c; background:#fff7ed; color:#ea580c;">+ Add Specific Room</button>
                        </div>
                    </div>
                </div>

                <div class="compact-box" id="box-transport" style="grid-column: 1 / -1;">
                    <div class="compact-label" style="display:flex; justify-content:space-between; align-items:center;">
                        <span>3. Transport & Vehicle</span>
                        <a href="#" class="dtc-toggle-btn" data-target="box-transport-inner" style="font-size:10px; color:#0284c7; text-transform:none; text-decoration:none; font-weight:bold;">[+ Expand / Edit]</a>
                    </div>
                    <div id="box-transport-inner" style="display:none; margin-top:5px;">
                        <div class="mini-toggles" style="margin-top: 5px;">
                            <input type="radio" id="v_a" name="vehicle_mode" value="auto" checked> <label for="v_a">AUTO VEHICLE</label>
                            <input type="radio" id="v_c" name="vehicle_mode" value="custom"> <label for="v_c">CUSTOM VEHICLE</label>
                        </div>
                        <div id="dtc-div-v-a" style="margin-bottom: 5px;"><select name="cab_pref" id="ui_cab_pref" class="u-field"></select></div>
                        <div id="dtc-div-v-c" class="hidden" style="margin-bottom: 5px;"><div id="dtc-list-v" class="builder-list" style="display:flex; flex-direction:column; gap:6px;"></div><button type="button" id="dtc-add-v" class="btn-add" style="margin-top:8px; border:1px dashed #0284c7; background:#f0f9ff;">+ Add Specific Vehicle</button></div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-main" id="btn-submit"><span class="btn-text">CALCULATE PACKAGE PRICE</span><span class="btn-loader hidden">Calculating...</span></button>
        </form>
        <div id="dtc-results"></div>
    </div>

    <div id="dtc-places-modal" class="dtc-modal hidden">
        <div class="dtc-modal-content" style="max-width: 400px; width: 95%;">
            <div class="dtc-modal-header">
                <h2>Select Places & Adjustments</h2>
                <span class="dtc-close" id="close-places-modal">&times;</span>
            </div>
            <div class="dtc-modal-body" style="background:#f8fafc; padding:15px; max-height: 85vh; overflow-y: auto;">
                <p style="margin-top:0; font-size:11px; color:#475569; margin-bottom:10px;">1. Select Sightseeing Places:</p>
                <div id="dtc-places-checkboxes" style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:10px; max-height:160px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
                </div>
                
                <div style="margin-top:15px; border:1px solid #cbd5e1; padding:10px; border-radius:6px; background:#f1f5f9;">
                    <label style="font-size:11px; font-weight:bold; color:#0f172a; margin-bottom:8px; display:block;">2. Quotation Adjustments (Optional)</label>
                    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:8px;">
                        <div style="flex:1; min-width: 120px;">
                            <label style="font-size:10px; font-weight:bold; color:#475569;">Change Base Price (PP):</label>
                            <input type="number" id="mod_base_pp" class="u-field" style="height:28px;">
                        </div>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:8px;">
                        <div style="flex:2; min-width: 120px;">
                            <label style="font-size:10px; font-weight:bold; color:#475569;">Other Service Name:</label>
                            <input type="text" id="mod_other_name" class="u-field" placeholder="e.g. Extra Food" style="height:28px;">
                        </div>
                        <div style="flex:1; min-width: 100px;">
                            <label style="font-size:10px; font-weight:bold; color:#475569;">Amount (Total):</label>
                            <input type="number" id="mod_other_cost" class="u-field" value="0" style="height:28px;">
                        </div>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:10px;">
                        <div style="flex:1; min-width: 100px;">
                            <label style="font-size:10px; font-weight:bold; color:#475569;">Flat Discount (Total ₹):</label>
                            <input type="number" id="mod_disc_flat" class="u-field" value="0" style="height:28px;">
                        </div>
                        <div style="flex:1; min-width: 100px;">
                            <label style="font-size:10px; font-weight:bold; color:#475569;">% Discount (Total):</label>
                            <input type="number" id="mod_disc_perc" class="u-field" value="0" max="100" style="height:28px;">
                        </div>
                    </div>
                </div>

                <button type="button" id="btn-generate-quote" class="btn-main" style="margin-top:15px;">GENERATE QUOTATION</button>
            </div>
        </div>
    </div>

    <div id="dtc-final-modal" class="dtc-modal hidden">
        <div class="dtc-modal-content" style="max-width: 500px; width: 95%; max-height:90vh; display:flex; flex-direction:column;">
            <div class="dtc-modal-header" style="flex-shrink:0;">
                <h2>Quotation Details</h2>
                <span class="dtc-close" id="close-final-modal">&times;</span>
            </div>
            <div class="dtc-modal-body" style="background:#f8fafc; padding:15px; overflow-y:auto; flex-grow:1;">
                
                <div id="dtc-final-summary" style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:15px; font-size:12px; line-height:1.4; color:#334155; margin-bottom:15px;"></div>

                <div style="display:grid; grid-template-columns:1fr; gap:10px;">
                    <button type="button" id="btn-copy-wa" class="btn-main" style="background:#25D366; margin-top:0; display:flex; align-items:center; justify-content:center; gap:8px;">
                        <span style="font-size:16px;">⎘</span> COPY FOR WHATSAPP
                    </button>
                    
                    <div style="background:#e0f2fe; border:1px solid #bae6fd; border-radius:6px; padding:10px;">
                        <label style="font-size:10px; font-weight:bold; color:#0284c7; text-transform:uppercase; margin-bottom:5px; display:block;">Email Quotation to Customer</label>
                        <div style="display:flex; gap:6px;">
                            <input type="email" id="dtc_customer_email" class="u-field" placeholder="customer@email.com" style="flex:1;">
                            <button type="button" id="btn-send-email" style="background:#0284c7; color:#fff; border:none; border-radius:4px; font-weight:bold; padding:0 15px; cursor:pointer;">SEND</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php if ($is_privileged): ?>
    <div id="dtc-settings-modal" class="dtc-modal hidden">
        <div class="dtc-modal-content" style="max-width: 800px; width: 95%;">
            <div class="dtc-modal-header">
                <h2>Manage Destinations & Pricing</h2>
                <span id="close-settings-modal" class="dtc-close">&times;</span>
            </div>
            <div class="dtc-modal-body" style="background:#f8fafc; padding:15px; max-height: 70vh; overflow-y: auto;">
                
                <div style="display:flex; gap:10px; margin-bottom:15px; background:#fff; padding:10px; border:1px solid #cbd5e1; border-radius:6px; flex-wrap:wrap;">
                    <select id="set-dest-select" class="u-field" style="flex:2; min-width:150px;"></select>
                    <button type="button" id="btn-new-dest" class="btn-main" style="flex:1; margin:0; padding:6px; font-size:11px;">+ NEW</button>
                    <button type="button" id="btn-dup-dest" class="btn-main" style="flex:1; margin:0; padding:6px; font-size:11px; background:#f59e0b;">DUPLICATE</button>
                    <button type="button" id="btn-del-dest" class="btn-rem" style="flex:0.5; margin:0; width:auto; border-radius:4px; height:auto;">DELETE</button>
                </div>
                
                <form id="dtc-settings-form">
                    <input type="hidden" id="set-dest-id">
                    <div class="input-master-table">
                        <div class="compact-box">
                            <label>Destination Name</label><input type="text" id="set-dest-name" class="u-field" required>
                            <label>Profit Margin Per Pax (₹)</label><input type="number" id="set-profit" class="u-field" required>
                        </div>
                        <div class="compact-box" style="grid-column: 1 / -1;">
                            <div class="compact-label">Pickups (ID, Name)</div>
                            <div id="set-pickups-list"></div><button type="button" class="btn-add" id="btn-add-set-pickup">+ Add Pickup Location</button>
                        </div>
                        <div class="compact-box" style="grid-column: 1 / -1;">
                            <div class="compact-label">Stay Locations (ID, Name)</div>
                            <div id="set-staylocs-list"></div><button type="button" class="btn-add" id="btn-add-set-stayloc">+ Add Stay Location</button>
                        </div>
                        <div class="compact-box" style="grid-column: 1 / -1;">
                            <div class="compact-label">Places to Visit (ID, Name)</div>
                            <div id="set-places-list"></div><button type="button" class="btn-add" id="btn-add-set-place">+ Add Place</button>
                        </div>
                        <div class="compact-box" style="grid-column: 1 / -1;">
                            <div class="compact-label">Service Types <span style="text-transform:none; font-weight:normal; color:#666;">(ID must be 'both', 'hotel', or 'cab')</span></div>
                            <div id="set-services-list"></div><button type="button" class="btn-add" id="btn-add-set-service">+ Add Service Type</button>
                        </div>
                        <div class="compact-box" style="grid-column: 1 / -1;">
                            <div class="compact-label">Vehicles (ID, Name, Capacity, Prices based on Pickup)</div>
                            <div id="set-vehicles-list"></div><button type="button" class="btn-add" id="btn-add-set-veh">+ Add Vehicle</button>
                        </div>
                        <div class="compact-box" style="grid-column: 1 / -1;">
                            <div class="compact-label">Rooms (ID, Name, Price, Capacity)</div>
                            <div id="set-rooms-list"></div><button type="button" class="btn-add" id="btn-add-set-room">+ Add Room</button>
                        </div>
                        <div class="compact-box" style="grid-column: 1 / -1;">
                            <div class="compact-label">Hotel Categories (ID, Name, % Increase)</div>
                            <div id="set-hotels-list"></div><button type="button" class="btn-add" id="btn-add-set-hotel">+ Add Hotel Category</button>
                        </div>
                        <div class="compact-box" style="grid-column: 1 / -1;">
                            <div class="compact-label">Seasons (Name, Start MM-DD, End MM-DD, Surcharge %)</div>
                            <div id="set-seasons-list"></div><button type="button" class="btn-add" id="btn-add-set-season">+ Add Season</button>
                        </div>
                    </div>
                    <button type="submit" id="btn-save-settings" class="btn-main">SAVE SETTINGS</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script type="text/template" id="dtc-tpl-auto-stay-row">
        <div class="build-row" style="background:#f8fafc; border:1px solid #cbd5e1; display:flex; flex-wrap:wrap; align-items:center; gap:8px; padding:8px;">
            <div style="flex: 1 1 150px;">
                <select name="auto_stay_loc[]" class="u-field dynamic-loc-options" style="border:1px solid #e2e8f0; background:#fff; font-weight:bold; width:100%;" required></select>
            </div>
            <div style="display:flex; align-items:center; gap:5px; flex: 1 1 100px;">
                <input type="number" name="auto_stay_nights[]" class="u-field" placeholder="Nights" required min="1" style="width:70px; text-align:center; border:1px solid #e2e8f0;">
                <span style="font-size:10px; color:#64748b; font-weight:bold;">NIGHTS</span>
            </div>
            <input type="hidden" name="auto_stay_pref[]" value="any">
            <button type="button" class="btn-dup" title="Duplicate">⎘</button>
            <button type="button" class="btn-rem">&times;</button>
        </div>
    </script>
    
    <script type="text/template" id="dtc-tpl-room-row">
        <div class="build-row" style="background:#fff7ed; border:1px solid #fdba74; display:flex; flex-wrap:wrap; align-items:center; gap:8px; padding:8px;">
            <div style="flex: 1 1 120px;">
                <select name="custom_room_loc[]" class="u-field dynamic-loc-options" style="border:1px solid #e2e8f0; width:100%;" required></select>
            </div>
            <div style="flex: 1 1 150px;">
                <select name="custom_rooms[]" class="u-field dynamic-room-options" style="border:1px solid #e2e8f0; font-weight:bold; width:100%;" required></select>
            </div>
            <div style="display:flex; align-items:center; gap:5px; flex: 1 1 80px;">
                <input type="number" name="custom_room_qty[]" class="u-field" placeholder="Qty" value="1" required min="1" style="width:60px; text-align:center; border:1px solid #e2e8f0;">
                <span style="font-size:10px; color:#ea580c; font-weight:bold;">ROOM(S)</span>
            </div>
            <div style="display:flex; align-items:center; gap:5px; flex: 1 1 80px;">
                <input type="number" name="custom_room_nights[]" class="u-field" placeholder="Nights" required min="1" style="width:60px; text-align:center; border:1px solid #e2e8f0;">
                <span style="font-size:10px; color:#64748b; font-weight:bold;">NIGHTS</span>
            </div>
            <button type="button" class="btn-dup" title="Duplicate">⎘</button>
            <button type="button" class="btn-rem">&times;</button>
        </div>
    </script>
    
    <script type="text/template" id="dtc-tpl-veh-row">
        <div class="build-row" style="background:#f0f9ff; border:1px solid #bae6fd; display:flex; flex-wrap:wrap; align-items:center; gap:8px; padding:8px;">
            <div style="display:flex; align-items:center; gap:5px; flex: 1 1 80px;">
                <input type="number" name="custom_veh_qty[]" class="u-field" placeholder="Qty" value="1" required min="1" style="width:60px; text-align:center; border:1px solid #e2e8f0;">
                <span style="font-size:10px; color:#0284c7; font-weight:bold;">VEH(S)</span>
            </div>
            <div style="flex: 1 1 150px;">
                <select name="custom_vehicles[]" class="u-field dynamic-veh-options" style="border:1px solid #e2e8f0; font-weight:bold; width:100%;" required></select>
            </div>
            <div style="display:flex; align-items:center; gap:5px; flex: 1 1 80px;">
                <input type="number" name="custom_veh_days[]" class="u-field" placeholder="Days" min="1" style="width:60px; text-align:center; border:1px solid #e2e8f0;">
                <span style="font-size:10px; color:#64748b; font-weight:bold;">DAYS</span>
            </div>
            <button type="button" class="btn-dup" title="Duplicate">⎘</button>
            <button type="button" class="btn-rem">&times;</button>
        </div>
    </script>
    
    <script type="text/template" id="tpl-set-pickup"><div class="build-row"><input type="text" class="u-field set-p-id" placeholder="ID (srinagar)" style="width:30%;"><input type="text" class="u-field set-p-name" placeholder="Name (Srinagar Airport)" style="width:70%;"><button type="button" class="btn-rem">&times;</button></div></script>
    <script type="text/template" id="tpl-set-stayloc"><div class="build-row"><input type="text" class="u-field set-sl-id" placeholder="ID (srinagar)" style="width:30%;"><input type="text" class="u-field set-sl-name" placeholder="Name (Srinagar)" style="width:70%;"><button type="button" class="btn-rem">&times;</button></div></script>
    <script type="text/template" id="tpl-set-place"><div class="build-row"><input type="text" class="u-field set-pl-id" placeholder="ID (gulmarg)" style="width:30%;"><input type="text" class="u-field set-pl-name" placeholder="Name (Gulmarg)" style="width:70%;"><button type="button" class="btn-rem">&times;</button></div></script>
    <script type="text/template" id="tpl-set-service"><div class="build-row"><input type="text" class="u-field set-sv-id" placeholder="ID (both / hotel / cab)" style="width:30%;"><input type="text" class="u-field set-sv-name" placeholder="Name (Hotel + Cab Package)" style="width:70%;"><button type="button" class="btn-rem">&times;</button></div></script>
    <script type="text/template" id="tpl-set-veh"><div class="build-row"><input type="text" class="u-field set-v-id" placeholder="ID (dzire)" style="width:15%;"><input type="text" class="u-field set-v-name" placeholder="Name" style="width:25%;"><input type="number" class="u-field set-v-cap" placeholder="Cap" style="width:15%;"><input type="text" class="u-field set-v-price" placeholder="Prices (sri:1800, jammu:3200)" style="width:40%;"><button type="button" class="btn-rem">&times;</button></div></script>
    <script type="text/template" id="tpl-set-room"><div class="build-row"><input type="text" class="u-field set-r-id" placeholder="ID (standard)" style="width:20%;"><input type="text" class="u-field set-r-name" placeholder="Name" style="width:30%;"><input type="number" class="u-field set-r-cap" placeholder="Cap" style="width:15%;"><input type="text" class="u-field set-r-price" placeholder="Prices (sri:2500, gul:3000)" style="width:35%;"><button type="button" class="btn-rem">&times;</button></div></script>
    <script type="text/template" id="tpl-set-hotel"><div class="build-row"><input type="text" class="u-field set-h-id" placeholder="ID (3star)" style="width:25%;"><input type="text" class="u-field set-h-name" placeholder="Name" style="width:45%;"><input type="number" step="0.01" class="u-field set-h-perc" placeholder="% Increase (e.g. 75)" style="width:25%;"><button type="button" class="btn-rem">&times;</button></div></script>
    <script type="text/template" id="tpl-set-season"><div class="build-row"><input type="text" class="u-field set-s-name" placeholder="Season Name" style="width:35%;"><input type="text" class="u-field set-s-start" placeholder="Start (MM-DD)" style="width:25%;"><input type="text" class="u-field set-s-end" placeholder="End (MM-DD)" style="width:25%;"><input type="number" class="u-field set-s-perc" placeholder="Sur %" style="width:15%;"><button type="button" class="btn-rem">&times;</button></div></script>
    <?php
    return ob_get_clean();
}