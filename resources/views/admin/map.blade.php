@extends('layouts.admin')

@section('content')
<div class="page active" style="flex-direction:row; height: 100%;">
    <!-- Left Sidebar with Controls -->
    <div class="map-bar" style="width: 320px; background: rgba(4,6,15,0.92); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden;">
        <div class="map-bar-header" style="padding: 16px; border-bottom: 1px solid var(--border);">
            <div style="font-size: 11px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-3); margin-bottom: 16px;">Territory Controls</div>
            
            <!-- Layer Toggles -->
            <div id="mapToggles">
                <div class="tog-row" data-layer="shops" style="display: flex; align-items: center; justify-content: space-between; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 8px; cursor: pointer;">
                    <span class="tog-label"><span class="tog-dot" style="background: #4f6ef7; width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px;"></span>Shop locations</span>
                    <div class="tog-switch on" style="width: 36px; height: 20px; background: var(--accent); border-radius: 10px; position: relative; cursor: pointer;"><div style="position: absolute; width: 16px; height: 16px; background: white; border-radius: 50%; top: 2px; right: 2px;"></div></div>
                </div>
                <div class="tog-row" data-layer="territory" style="display: flex; align-items: center; justify-content: space-between; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 8px; cursor: pointer;">
                    <span class="tog-label"><span class="tog-dot" style="background: #1fcfaa; width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px;"></span>Territory union</span>
                    <div class="tog-switch on" style="width: 36px; height: 20px; background: var(--accent); border-radius: 10px; position: relative; cursor: pointer;"><div style="position: absolute; width: 16px; height: 16px; background: white; border-radius: 50%; top: 2px; right: 2px;"></div></div>
                </div>
            </div>
            
            <!-- Radius Selector -->
            <div style="margin-top: 12px;">
                <label style="font-size: 10px; font-family: var(--mono); color: var(--text-3); display: block; margin-bottom: 6px;">TERRITORY RADIUS (KM)</label>
                <select id="radiusSelect" class="form-select" style="width: 100%; padding: 8px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 12px;">
                    <option value="25" selected>25 km (Default)</option>
                    <option value="50">50 km</option>
                    <option value="100">100 km</option>
                    <option value="200">200 km</option>
                </select>
            </div>
            
            <!-- Compare Selector -->
            <div style="margin-top: 12px;">
                <label style="font-size: 10px; font-family: var(--mono); color: var(--text-3); display: block; margin-bottom: 6px;">COMPARE PERIOD</label>
                <select id="compareSelect" class="form-select" style="width: 100%; padding: 8px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 12px;">
                    <option value="feb">Feb 2026 baseline</option>
                    <option value="mar" selected>Mar 2026 vs Feb 2026</option>
                </select>
            </div>
        </div>
        
        <div class="map-bar-body" style="flex: 1; overflow-y: auto; padding: 16px;">
            <!-- Statistics Grid -->
            <div class="map-stat-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px;">
                <div class="mstat-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px;">
                    <div class="mstat-v" style="font-size: 18px; font-weight: 700; color: var(--accent);" id="statShops">{{ $shops->count() }}</div>
                    <div class="mstat-l" style="font-size: 9px; color: var(--text-3); margin-top: 4px;">Active Shops</div>
                </div>
                <div class="mstat-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px;">
                    <div class="mstat-v" style="font-size: 18px; font-weight: 700; color: var(--green);" id="statVerifs">0</div>
                    <div class="mstat-l" style="font-size: 9px; color: var(--text-3); margin-top: 4px;">Verifications</div>
                </div>
                <div class="mstat-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px;">
                    <div class="mstat-v" style="font-size: 18px; font-weight: 700; color: var(--amber);" id="statIncome">₹0</div>
                    <div class="mstat-l" style="font-size: 9px; color: var(--text-3); margin-top: 4px;">Net Income</div>
                </div>
            </div>
            
            <!-- Income Model Table -->
            <div style="margin-bottom: 16px;">
                <div style="font-size: 9px; font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-3); margin-bottom: 10px;">Verification Income Model</div>
                <table class="income-table" style="width: 100%; font-size: 10px;">
                    <tr><td style="color: var(--text-3);">1st verify (Cashfree)</td><td class="iv" style="text-align: right; color: var(--red);">−₹2/cust</td></tr>
                    <tr><td style="color: var(--text-3);">Shop charge</td><td class="iv" style="text-align: right; color: var(--green);">+₹3/verif</td></tr>
                    <tr><td style="color: var(--text-3);">1st time profit</td><td class="iv" style="text-align: right; color: var(--amber);">₹1 net</td></tr>
                    <tr><td style="color: var(--text-3);">Repeat (DB)</td><td class="iv" style="text-align: right; color: var(--green);">₹3 pure</td></tr>
                </table>
            </div>
            
            <!-- Platform Net Income -->
            <div style="background: var(--accent-lo); border: 1px solid rgba(79,110,247,0.25); border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                <div style="font-size: 9px; font-family: var(--mono); color: var(--text-3); margin-bottom: 4px;">Platform Net Income</div>
                <div style="font-size: 20px; font-weight: 700; color: var(--accent);" id="platformNetIncome">₹0</div>
            </div>
            
            <!-- Shops List -->
            <div style="font-size: 9px; font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-3); margin-bottom: 10px;">Shops by Territory</div>
            <div id="mapShopList" style="max-height: 300px; overflow-y: auto;">
                @foreach($shops as $shop)
                <div class="map-shop-row" data-lat="{{ $shop->latitude }}" data-lng="{{ $shop->longitude }}" data-name="{{ $shop->name }}" style="display: flex; align-items: center; gap: 8px; padding: 8px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 6px; cursor: pointer;">
                    <div class="msr-dot" style="width: 10px; height: 10px; border-radius: 50%; background: #4f6ef7;"></div>
                    <div class="msr-info" style="flex: 1;">
                        <div class="msr-name" style="font-size: 11px; font-weight: 600;">{{ $shop->name }}</div>
                        <div class="msr-sub" style="font-size: 9px; color: var(--text-3);">Wallet: ₹{{ number_format($shop->wallet_balance, 2) }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    
    <!-- Right: Map -->
    <div class="map-wrap" style="flex: 1; position: relative;">
        <div id="leafletMap" style="height: 100%; width: 100%;"></div>
        <div class="map-float" style="position: absolute; top: 16px; right: 16px; z-index: 500; display: flex; flex-direction: column; gap: 8px;">
            <button class="map-float-btn" id="fitIndiaBtn" style="background: rgba(7,11,24,0.92); backdrop-filter: blur(16px); border: 1px solid var(--border-hi); border-radius: 8px; padding: 8px 12px; font-family: var(--mono); font-size: 10px; color: var(--text); cursor: pointer;"><i class="fas fa-compress-arrows-alt"></i> Fit India</button>
            <button class="map-float-btn" id="fitOdishaBtn" style="background: rgba(7,11,24,0.92); backdrop-filter: blur(16px); border: 1px solid var(--border-hi); border-radius: 8px; padding: 8px 12px; font-family: var(--mono); font-size: 10px; color: var(--text); cursor: pointer;"><i class="fas fa-map-pin"></i> Fit Odisha</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6.5.0/turf.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize map
        var map = L.map('leafletMap').setView([20.5937, 78.9629], 5);
        
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
        }).addTo(map);
        
        // Data
        var shops = @json($shops);
        var shopMarkersLayer = null;
        var territoryLayer = null;
        var currentRadius = 25;
        
        // Layer visibility states
        var layerState = {
            shops: true,
            territory: true
        };
        
        // Helper: Escape HTML
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        // Create shop markers
        function createShopMarkers() {
            var group = L.layerGroup();
            shops.forEach(function(shop) {
                if (shop.latitude && shop.longitude) {
                    var lat = parseFloat(shop.latitude);
                    var lng = parseFloat(shop.longitude);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        var marker = L.marker([lat, lng], {
                            icon: L.divIcon({
                                html: '<div style="background: #4f6ef7; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><i class="fas fa-store" style="color: white; font-size: 10px;"></i></div>',
                                iconSize: [24, 24],
                                className: ''
                            })
                        }).bindPopup(`
                            <div style="font-family: \'Syne\', sans-serif;">
                                <strong>${escapeHtml(shop.name)}</strong><br>
                                Wallet: ₹${(shop.wallet_balance || 0).toLocaleString()}<br>
                                <small>${shop.business_display_address || 'Address not set'}</small>
                            </div>
                        `);
                        marker.addTo(group);
                    }
                }
            });
            return group;
        }
        
        // Create territory union using Turf.js
        function createTerritoryUnion(radius) {
            var points = [];
            shops.forEach(function(shop) {
                if (shop.latitude && shop.longitude) {
                    points.push(turf.point([parseFloat(shop.longitude), parseFloat(shop.latitude)]));
                }
            });
            
            if (points.length < 2) return null;
            
            // Create buffers around each point
            var buffers = points.map(point => turf.buffer(point, radius, { units: 'kilometers' }));
            
            // Union all buffers
            var union = buffers[0];
            for (var i = 1; i < buffers.length; i++) {
                try {
                    union = turf.union(union, buffers[i]);
                } catch(e) {
                    console.warn('Union failed for buffer', i);
                }
            }
            
            if (!union) return null;
            
            var compareValue = document.getElementById('compareSelect').value;
            var growthColor = compareValue === 'mar' ? '#1fcfaa' : '#4f6ef7';
            
            return L.geoJSON(union, {
                style: {
                    color: growthColor,
                    fillColor: growthColor,
                    fillOpacity: 0.08,
                    weight: 1.5,
                    opacity: 0.6,
                    dashArray: '6, 5'
                },
                interactive: false
            });
        }
        
        // Apply layer visibility
        function applyLayerVisibility() {
            if (shopMarkersLayer) {
                if (layerState.shops) shopMarkersLayer.addTo(map);
                else map.removeLayer(shopMarkersLayer);
            }
            if (territoryLayer) {
                if (layerState.territory) territoryLayer.addTo(map);
                else map.removeLayer(territoryLayer);
            }
        }
        
        // Initialize all layers
        shopMarkersLayer = createShopMarkers();
        territoryLayer = createTerritoryUnion(currentRadius);
        
        // Apply initial visibility
        applyLayerVisibility();
        
        // Fit map to show all markers
        if (shops.length > 0) {
            var bounds = L.latLngBounds([]);
            shops.forEach(function(shop) {
                if (shop.latitude && shop.longitude) {
                    bounds.extend([parseFloat(shop.latitude), parseFloat(shop.longitude)]);
                }
            });
            if (!bounds.isValid()) {
                bounds = L.latLngBounds([[17.8, 81.4], [22.6, 87.5]]);
            }
            map.fitBounds(bounds.pad(0.2));
        }
        
        // Toggle functionality
        document.querySelectorAll('.tog-row').forEach(function(row) {
            var layer = row.dataset.layer;
            var togSwitch = row.querySelector('.tog-switch');
            
            row.addEventListener('click', function(e) {
                e.stopPropagation();
                layerState[layer] = !layerState[layer];
                
                if (layerState[layer]) {
                    togSwitch.classList.add('on');
                    togSwitch.style.background = 'var(--accent)';
                    var innerDiv = togSwitch.querySelector('div');
                    if (innerDiv) innerDiv.style.right = '2px';
                    if (innerDiv) innerDiv.style.left = 'auto';
                } else {
                    togSwitch.classList.remove('on');
                    togSwitch.style.background = 'var(--border-hi)';
                    var innerDiv = togSwitch.querySelector('div');
                    if (innerDiv) innerDiv.style.left = '2px';
                    if (innerDiv) innerDiv.style.right = 'auto';
                }
                
                if (layer === 'territory') {
                    if (layerState.territory) {
                        if (territoryLayer) map.removeLayer(territoryLayer);
                        territoryLayer = createTerritoryUnion(currentRadius);
                        if (territoryLayer) territoryLayer.addTo(map);
                    } else if (territoryLayer) {
                        map.removeLayer(territoryLayer);
                    }
                } else {
                    applyLayerVisibility();
                }
            });
        });
        
        // Radius selector change
        document.getElementById('radiusSelect').addEventListener('change', function(e) {
            currentRadius = parseInt(e.target.value);
            if (layerState.territory) {
                if (territoryLayer) map.removeLayer(territoryLayer);
                territoryLayer = createTerritoryUnion(currentRadius);
                if (territoryLayer) territoryLayer.addTo(map);
            }
        });
        
        // Compare selector change
        document.getElementById('compareSelect').addEventListener('change', function(e) {
            if (layerState.territory) {
                if (territoryLayer) map.removeLayer(territoryLayer);
                territoryLayer = createTerritoryUnion(currentRadius);
                if (territoryLayer) territoryLayer.addTo(map);
            }
        });
        
        // Fit buttons
        document.getElementById('fitIndiaBtn').addEventListener('click', function() {
            map.setView([20.5937, 78.9629], 5);
        });
        document.getElementById('fitOdishaBtn').addEventListener('click', function() {
            map.fitBounds([[17.8, 81.4], [22.6, 87.5]]);
        });
        
        // Shop list click to center map
        document.querySelectorAll('.map-shop-row').forEach(function(row) {
            row.addEventListener('click', function() {
                var lat = parseFloat(this.dataset.lat);
                var lng = parseFloat(this.dataset.lng);
                if (!isNaN(lat) && !isNaN(lng)) {
                    map.setView([lat, lng], 12);
                }
            });
        });
        
        // Update statistics
        document.getElementById('statShops').innerText = shops.length;
        
        // Handle window resize
        window.addEventListener('resize', function() {
            setTimeout(function() { map.invalidateSize(); }, 100);
        });
    });
</script>
@endpush