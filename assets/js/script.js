jQuery(document).ready(function($) {
    
    let masterCfg = dtc_obj.config;
    let cachedCustomVehOptions = '';
    let cachedCustomRoomOptions = '';

    let activeRowData = null;

    function formatIN(num) {
        return Number(num).toLocaleString('en-IN');
    }

    function populateMainDestinations() {
        let destHtml = '<option value="" disabled selected>-- Select Destination --</option>';
        $.each(masterCfg.destinations, function(k, v) { destHtml += `<option value="${k}">${v.name}</option>`; });
        $('#ui_destination').html(destHtml);
        updateDestinationFields();
    }

    function updateDestinationFields() {
        let dest = $('#ui_destination').val();
        
        if(!dest || !masterCfg.destinations[dest]) {
            $('#ui_pickup_location, #ui_service_type, #ui_hotel_category, #ui_cab_pref, #ui_room_pref').html('<option value="" disabled selected>-- Select --</option>');
            $('#dtc-list-v, #dtc-list-r').empty();
            return;
        }

        let dCfg = masterCfg.destinations[dest];

        let pickupHtml = '<option value="" disabled selected>-- Select Pickup --</option>';
        $.each(dCfg.pickups, function(k, v) { pickupHtml += `<option value="${k}">${v}</option>`; });
        $('#ui_pickup_location').html(pickupHtml);

        let servHtml = '';
        $.each(dCfg.service_types, function(k, v) { servHtml += `<option value="${k}">${v}</option>`; });
        $('#ui_service_type').html(servHtml);

        let hotelHtml = '';
        $.each(dCfg.hotel_categories, function(k, v) { hotelHtml += `<option value="${k}">${v.name}</option>`; });
        $('#ui_hotel_category').html(hotelHtml);

        let vehPrefHtml = '<option value="any">Any Cab Combo</option>';
        cachedCustomVehOptions = '';
        $.each(dCfg.vehicles, function(k, v) {
            vehPrefHtml += `<option value="${k}">Prefer ${v.name}</option>`;
            cachedCustomVehOptions += `<option value="${k}">${v.name} (${v.capacity} Pax)</option>`;
        });
        $('#ui_cab_pref').html(vehPrefHtml);
        
        let roomPrefHtml = '<option value="any">Any Room Combo</option>';
        cachedCustomRoomOptions = '';
        $.each(dCfg.rooms, function(k, v) {
            roomPrefHtml += `<option value="${k}">Prefer ${v.name}</option>`;
            cachedCustomRoomOptions += `<option value="${k}">${v.name} (${v.capacity} Pax)</option>`;
        });
        $('#ui_room_pref').html(roomPrefHtml);

        $('#dtc-list-v').empty(); $('#dtc-list-r').empty();
    }

    populateMainDestinations();
    $('#ui_destination').change(updateDestinationFields);

    $(document).on('click', '.dtc-toggle-btn', function(e) {
        e.preventDefault();
        let target = $(this).data('target');
        $('#' + target).slideToggle('fast');
        let currentText = $(this).text();
        $(this).text(currentText.includes('+') ? '[- Hide Settings]' : '[+ Expand / Edit]');
    });

    $('input[name="room_mode"]').change(function() { $('#dtc-div-r-a').toggleClass('hidden', this.value !== 'auto'); $('#dtc-div-r-c').toggleClass('hidden', this.value !== 'custom'); });
    $('input[name="vehicle_mode"]').change(function() { $('#dtc-div-v-a').toggleClass('hidden', this.value !== 'auto'); $('#dtc-div-v-c').toggleClass('hidden', this.value !== 'custom'); });
    
    $('#ui_service_type').change(function() {
        let s = $(this).val();
        if(s === 'hotel') { $('#box-transport').hide(); $('#box-rooms').show(); $('#ui_hotel_cat_box').show(); } 
        else if(s === 'cab') { $('#box-rooms').hide(); $('#box-transport').show(); $('#ui_hotel_cat_box').hide(); } 
        else { $('#box-rooms, #box-transport').show(); $('#ui_hotel_cat_box').show(); }
    });

    $('#dtc-add-r').click(function() { let r = $($('#dtc-tpl-room-row').html()); r.find('.dynamic-room-options').html(cachedCustomRoomOptions); $('#dtc-list-r').append(r); });
    $('#dtc-add-v').click(function() { let r = $($('#dtc-tpl-veh-row').html()); r.find('.dynamic-veh-options').html(cachedCustomVehOptions); $('#dtc-list-v').append(r); });
    $(document).on('click', '.btn-rem', function() { $(this).parent().remove(); });

    $('#dtc-form').submit(function(e) {
        e.preventDefault();
        let btn = $('#btn-submit');
        btn.find('.btn-text').addClass('hidden'); btn.find('.btn-loader').removeClass('hidden'); btn.prop('disabled', true);
        $.post(dtc_obj.ajax_url, { action: 'dtc_calculate', nonce: dtc_obj.nonce, form_data: $(this).serialize() }, function(response) {
            $('#dtc-results').html(response.success ? response.data : '<div style="padding:15px;color:red;text-align:center;">Failed.</div>');
            btn.find('.btn-text').removeClass('hidden'); btn.find('.btn-loader').addClass('hidden'); btn.prop('disabled', false);
        });
    });

    // ==========================================
    // PLACES & QUOTATION FLOW
    // ==========================================
    
    $(document).on('click', '.dtc-res-row', function(e) {
        if ($(e.target).closest('.dtc-info-wrap').length) return;
        if (!dtc_obj.is_logged_in) return;
        
        let dataStr = $(this).attr('data-info');
        if(!dataStr) return;
        
        activeRowData = JSON.parse(dataStr);
        let dest = activeRowData.dest_id;
        let places = masterCfg.destinations[dest].places || {};
        
        let html = '';
        if(Object.keys(places).length === 0) {
            html = '<div style="color:#64748b; font-style:italic; font-size:12px;">No places added for this destination.</div>';
        } else {
            $.each(places, function(k, name) {
                html += `<label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; font-weight:600; color:#1e293b;">
                            <input type="checkbox" class="dtc-place-cb" value="${name}" checked>
                            ${name}
                         </label>`;
            });
        }
        $('#dtc-places-checkboxes').html(html);
        $('#dtc-places-modal').removeClass('hidden');
    });

    $('#btn-generate-quote').click(function() {
        let selectedPlaces = [];
        $('.dtc-place-cb:checked').each(function() { selectedPlaces.push($(this).val()); });
        
        activeRowData.selected_places = selectedPlaces;
        
        let d = activeRowData;
        let genDate = new Date().toLocaleString('en-IN', { hour12: true });
        
        let placesText = selectedPlaces.length > 0 ? selectedPlaces.map(p => `• ${p}`).join('\n') : '• N/A';
        let placesHtml = selectedPlaces.length > 0 ? selectedPlaces.map(p => `<li>${p}</li>`).join('') : '<li>N/A</li>';

        let grandBase = d.base_pp * d.pax;
        let grandGst = d.gst_pp * d.pax;

        let nights = d.days > 1 ? d.days - 1 : 0;
        let durationText = `${d.days} Days / ${nights} Nights`;

        // --- GENERATE WHATSAPP TEXT ---
        let waText = `*SOULFUL TOUR & TRAVELS*
_GSTIN: 19AXIPD7432L1Z5_

*Quotation Details*
• Destination: ${d.dest_name}
• Service: ${d.service}
• Duration: ${durationText}
• Dates: ${d.start} to ${d.end}
• Pax: ${d.pax}
• Hotel: ${d.hotel}

*Accommodation & Transport*
• Rooms: ${d.rooms}
• Vehicle: ${d.veh}

*Places to Visit*
${placesText}

*Pricing (INR)*
• Base Price PP: ₹ ${formatIN(d.base_pp)}
• GST (5%) PP: ₹ ${formatIN(d.gst_pp)}
• Total Price PP: ₹ ${formatIN(d.tot_pp)}
*Grand Total: ₹ ${formatIN(d.grand_total)}*

_Generated on: ${genDate}_`;

        $('#btn-copy-wa').attr('data-text', waText);

        // --- GENERATE ULTRA-COMPACT HTML VIEW ---
        let htmlContent = `
        <div style="text-align:center; border-bottom:1px solid #0073aa; padding-bottom:5px; margin-bottom:8px;">
            <h2 style="margin:0; color:#0073aa; font-size:14px; text-transform:uppercase;">SOULFUL TOUR & TRAVELS</h2>
            <div style="font-size:9px; color:#64748b; font-weight:bold;">GSTIN: 19AXIPD7432L1Z5</div>
        </div>
        
        <table style="width:100%; border-collapse:collapse; font-size:10px; margin-bottom:8px; line-height:1.2;">
            <tr>
                <td style="padding:4px; border:1px solid #e2e8f0; width:50%;"><b>Dest:</b> ${d.dest_name}</td>
                <td style="padding:4px; border:1px solid #e2e8f0; width:50%;"><b>Type:</b> ${d.service}</td>
            </tr>
            <tr>
                <td style="padding:4px; border:1px solid #e2e8f0;"><b>Dates:</b> ${d.start} to ${d.end}</td>
                <td style="padding:4px; border:1px solid #e2e8f0;"><b>Duration:</b> ${durationText}</td>
            </tr>
            <tr>
                <td style="padding:4px; border:1px solid #e2e8f0;"><b>Pax:</b> ${d.pax}</td>
                <td style="padding:4px; border:1px solid #e2e8f0;"><b>Hotel:</b> ${d.hotel}</td>
            </tr>
            <tr>
                <td colspan="2" style="padding:4px; border:1px solid #e2e8f0;"><b>Rooms:</b> ${d.rooms}</td>
            </tr>
            <tr>
                <td colspan="2" style="padding:4px; border:1px solid #e2e8f0;"><b>Vehicle:</b> ${d.veh}</td>
            </tr>
        </table>

        <div style="margin-bottom:8px; border:1px solid #e2e8f0; border-radius:4px; padding:6px; line-height:1.2;">
            <div style="font-weight:bold; color:#0073aa; font-size:10px; margin-bottom:3px;">Places to Visit:</div>
            <ul style="margin:0; padding-left:15px; color:#334155; font-size:9px;">${placesHtml}</ul>
        </div>

        <div style="background:#f0f9ff; border:1px solid #bae6fd; padding:6px 8px; border-radius:4px; font-size:10px; line-height:1.2;">
            <div style="display:flex; justify-content:space-between; margin-bottom:3px; color:#475569;">
                <div>Base Total (${d.pax} Pax):</div> <div>₹ ${formatIN(grandBase)} <span style="font-size:8px;">(₹${formatIN(d.base_pp)} PP)</span></div>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px; color:#475569; border-bottom:1px dashed #bae6fd; padding-bottom:4px;">
                <div>GST (5%):</div> <div>+ ₹ ${formatIN(grandGst)} <span style="font-size:8px;">(₹${formatIN(d.gst_pp)} PP)</span></div>
            </div>
            <div style="display:flex; justify-content:space-between; margin-top:4px; font-weight:bold; font-size:13px; color:#0369a1;">
                <div>GRAND TOTAL:</div> <div>₹ ${formatIN(d.grand_total)} <span style="font-size:9px; color:#0284c7;">(₹${formatIN(d.tot_pp)} PP)</span></div>
            </div>
        </div>
        
        <div style="text-align:center; font-size:8px; color:#94a3b8; margin-top:6px;">Generated: ${genDate}</div>
        `;

        $('#dtc-final-summary').html(htmlContent);
        $('#dtc-places-modal').addClass('hidden');
        $('#dtc-final-modal').removeClass('hidden');
    });

    $('#btn-copy-wa').click(function() {
        let text = $(this).attr('data-text');
        let btn = $(this);
        navigator.clipboard.writeText(text).then(function() {
            let originalHtml = btn.html();
            btn.html('✓ COPIED').css('background', '#16a34a');
            setTimeout(() => { btn.html(originalHtml).css('background', '#25D366'); }, 2000);
        });
    });

    $('#btn-send-email').click(function() {
        let email = $('#dtc_customer_email').val();
        if(!email) { alert("Please enter an email address."); return; }
        
        let htmlContent = $('#dtc-final-summary').html();
        let btn = $(this);
        btn.text('...').prop('disabled', true);
        
        $.post(dtc_obj.ajax_url, { 
            action: 'dtc_send_quote_email', 
            nonce: dtc_obj.nonce, 
            email: email, 
            html_content: htmlContent 
        }, function(response) {
            alert(response.data);
            btn.text('SEND').prop('disabled', false);
            if(response.success) $('#dtc_customer_email').val('');
        }).fail(function() {
            alert("Server Error");
            btn.text('SEND').prop('disabled', false);
        });
    });

    $('.dtc-close').click(function() { $(this).closest('.dtc-modal').addClass('hidden'); });
    $('.dtc-modal').click(function(e) { if(e.target === this) $(this).addClass('hidden'); });

    // ==========================================
    // SETTINGS GUI MODAL (ADMIN ONLY)
    // ==========================================
    if (dtc_obj.is_admin) {
        $('#btn-open-settings').click(function() { loadSettingsDestinations(); $('#dtc-settings-modal').removeClass('hidden'); });
        $('#close-settings-modal').click(function() { $('#dtc-settings-modal').addClass('hidden'); });
        
        function loadSettingsDestinations() {
            let html = '';
            $.each(masterCfg.destinations, function(k, v) { html += `<option value="${k}">${v.name}</option>`; });
            $('#set-dest-select').html(html);
            loadDestinationData($('#set-dest-select').val());
        }

        $('#set-dest-select').change(function() { loadDestinationData($(this).val()); });

        $('#btn-new-dest').click(function() {
            let nid = prompt("Enter a unique ID for new destination (e.g. goa, kerala):");
            if(nid && !masterCfg.destinations[nid]) {
                masterCfg.destinations[nid] = {
                    name: "New " + nid, base_cost_per_pax: 0, profit_margin_per_pax: 0,
                    service_types: { both: "Package", hotel: "Hotel Only", cab: "Cab Only" },
                    pickups: {}, places: {}, hotel_categories: {}, rooms: {}, vehicles: {}, seasonal_surcharges: []
                };
                loadSettingsDestinations();
                $('#set-dest-select').val(nid).change();
            }
        });

        // NEW DUPLICATE DESTINATION LOGIC
        $('#btn-dup-dest').click(function() {
            let id = $('#set-dest-select').val();
            if(!id || !masterCfg.destinations[id]) return;

            let nid = prompt("Enter a unique ID for the duplicated destination (e.g. kashmir_copy):");
            if(nid && !masterCfg.destinations[nid]) {
                // Deep clone the selected destination
                let clonedDest = JSON.parse(JSON.stringify(masterCfg.destinations[id]));
                clonedDest.name = clonedDest.name + " (Copy)";
                masterCfg.destinations[nid] = clonedDest;
                
                loadSettingsDestinations();
                $('#set-dest-select').val(nid).change();
            } else if (masterCfg.destinations[nid]) {
                alert("Error: This Destination ID already exists!");
            }
        });

        $('#btn-del-dest').click(function() {
            let id = $('#set-dest-select').val();
            if(confirm("Delete this destination?")) {
                delete masterCfg.destinations[id];
                loadSettingsDestinations();
            }
        });

        function loadDestinationData(id) {
            if(!id || !masterCfg.destinations[id]) return;
            let d = masterCfg.destinations[id];
            
            $('#set-dest-id').val(id);
            $('#set-dest-name').val(d.name);
            $('#set-base-cost').val(d.base_cost_per_pax);
            $('#set-profit').val(d.profit_margin_per_pax);
            
            $('#set-pickups-list, #set-places-list, #set-services-list, #set-vehicles-list, #set-rooms-list, #set-hotels-list, #set-seasons-list').empty();

            $.each(d.pickups, function(k, v){ addSetPickup(k, v); });
            $.each(d.places || {}, function(k, v){ addSetPlace(k, v); });
            $.each(d.service_types, function(k, v){ addSetService(k, v); });

            $.each(d.vehicles, function(k, v){ 
                let pStr = [];
                if (typeof v.price_per_day === 'object') { $.each(v.price_per_day, function(pk, pv) { pStr.push(pk + ':' + pv); }); } 
                else { pStr.push('default:' + v.price_per_day); }
                addSetVeh(k, v.name, v.capacity, pStr.join(', ')); 
            });
            
            $.each(d.rooms, function(k, v){ addSetRoom(k, v.name, v.price, v.capacity); });
            
            $.each(d.hotel_categories, function(k, v){ 
                let perc = v.percent !== undefined ? v.percent : ((v.multiplier || 1) - 1) * 100;
                addSetHotel(k, v.name, perc); 
            });

            $.each(d.seasonal_surcharges, function(i, v){ addSetSeason(v.name, v.start, v.end, v.surcharge_percent); });
        }

        $('#btn-add-set-pickup').click(function(){ addSetPickup('', ''); });
        function addSetPickup(id, name) { let r = $($('#tpl-set-pickup').html()); r.find('.set-p-id').val(id); r.find('.set-p-name').val(name); $('#set-pickups-list').append(r); }

        $('#btn-add-set-place').click(function(){ addSetPlace('', ''); });
        function addSetPlace(id, name) { let r = $($('#tpl-set-place').html()); r.find('.set-pl-id').val(id); r.find('.set-pl-name').val(name); $('#set-places-list').append(r); }

        $('#btn-add-set-service').click(function(){ addSetService('', ''); });
        function addSetService(id, name) { let r = $($('#tpl-set-service').html()); r.find('.set-sv-id').val(id); r.find('.set-sv-name').val(name); $('#set-services-list').append(r); }

        $('#btn-add-set-veh').click(function(){ addSetVeh('', '', '', ''); });
        function addSetVeh(id, name, cap, pr) { let r = $($('#tpl-set-veh').html()); r.find('.set-v-id').val(id); r.find('.set-v-name').val(name); r.find('.set-v-cap').val(cap); r.find('.set-v-price').val(pr); $('#set-vehicles-list').append(r); }

        $('#btn-add-set-room').click(function(){ addSetRoom('', '', '', ''); });
        function addSetRoom(id, name, pr, cap) { let r = $($('#tpl-set-room').html()); r.find('.set-r-id').val(id); r.find('.set-r-name').val(name); r.find('.set-r-price').val(pr); r.find('.set-r-cap').val(cap); $('#set-rooms-list').append(r); }

        $('#btn-add-set-hotel').click(function(){ addSetHotel('', '', ''); });
        function addSetHotel(id, name, perc) { let r = $($('#tpl-set-hotel').html()); r.find('.set-h-id').val(id); r.find('.set-h-name').val(name); r.find('.set-h-perc').val(perc); $('#set-hotels-list').append(r); }

        $('#btn-add-set-season').click(function(){ addSetSeason('', '', '', ''); });
        function addSetSeason(name, start, end, perc) { let r = $($('#tpl-set-season').html()); r.find('.set-s-name').val(name); r.find('.set-s-start').val(start); r.find('.set-s-end').val(end); r.find('.set-s-perc').val(perc); $('#set-seasons-list').append(r); }

        // Save Settings AJAX
        $('#dtc-settings-form').submit(function(e) {
            e.preventDefault();
            let id = $('#set-dest-id').val();
            let d = masterCfg.destinations[id];
            
            d.name = $('#set-dest-name').val();
            d.base_cost_per_pax = parseFloat($('#set-base-cost').val());
            d.profit_margin_per_pax = parseFloat($('#set-profit').val());

            d.pickups = {}; $('#set-pickups-list .build-row').each(function(){ let k=$(this).find('.set-p-id').val(); if(k) d.pickups[k] = $(this).find('.set-p-name').val(); });
            
            d.places = {}; $('#set-places-list .build-row').each(function(){ let k=$(this).find('.set-pl-id').val(); if(k) d.places[k] = $(this).find('.set-pl-name').val(); });
            
            d.service_types = {}; $('#set-services-list .build-row').each(function(){ let k=$(this).find('.set-sv-id').val(); if(k) d.service_types[k] = $(this).find('.set-sv-name').val(); });

            d.vehicles = {};
            $('#set-vehicles-list .build-row').each(function(){ 
                let k=$(this).find('.set-v-id').val(); 
                if(k) {
                    let priceRaw = $(this).find('.set-v-price').val().split(',');
                    let priceObj = {};
                    priceRaw.forEach(pr => {
                        let pts = pr.split(':');
                        if (pts.length > 1) { priceObj[pts[0].trim()] = parseFloat(pts[1].trim()) || 0; } 
                        else if (pts[0].trim() !== '') { priceObj['default'] = parseFloat(pts[0].trim()); }
                    });

                    d.vehicles[k] = { name: $(this).find('.set-v-name').val(), capacity: parseInt($(this).find('.set-v-cap').val()), price_per_day: priceObj }; 
                }
            });

            d.rooms = {}; $('#set-rooms-list .build-row').each(function(){ let k=$(this).find('.set-r-id').val(); if(k) d.rooms[k] = {name: $(this).find('.set-r-name').val(), price: parseFloat($(this).find('.set-r-price').val()), capacity: parseInt($(this).find('.set-r-cap').val())}; });

            d.hotel_categories = {};
            $('#set-hotels-list .build-row').each(function(){ 
                let k=$(this).find('.set-h-id').val(); 
                if(k) d.hotel_categories[k] = { name: $(this).find('.set-h-name').val(), percent: parseFloat($(this).find('.set-h-perc').val() || 0) }; 
            });

            d.seasonal_surcharges = [];
            $('#set-seasons-list .build-row').each(function(){ let n=$(this).find('.set-s-name').val(); if(n) d.seasonal_surcharges.push({name: n, start: $(this).find('.set-s-start').val(), end: $(this).find('.set-s-end').val(), surcharge_percent: parseFloat($(this).find('.set-s-perc').val())}); });

            let btn = $('#btn-save-settings'); btn.text('SAVING...').prop('disabled', true);
            $.post(dtc_obj.ajax_url, { action: 'dtc_save_settings', nonce: dtc_obj.nonce, new_config: JSON.stringify(masterCfg) }, function(res) {
                if(res.success) { alert(res.data); populateMainDestinations(); $('#dtc-settings-modal').addClass('hidden'); }
                else { alert("Error saving."); }
                btn.text('SAVE SETTINGS').prop('disabled', false);
            });
        });
    }
});