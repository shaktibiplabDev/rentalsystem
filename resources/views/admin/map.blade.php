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
                    <div class="tog-switch on" style="width: 36px; height: 20px; background: var(--accent); border-radius: 10px; position: relative; cursor: pointer;">
                        <div style="position: absolute; width: 16px; height: 16px; background: white; border-radius: 50%; top: 2px; right: 2px;"></div>
                    </div>
                </div>
                <div class="tog-row" data-layer="territory" style="display: flex; align-items: center; justify-content: space-between; padding: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 8px; cursor: pointer;">
                    <span class="tog-label"><span class="tog-dot" style="background: #1fcfaa; width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px;"></span>Territory union</span>
                    <div class="tog-switch on" style="width: 36px; height: 20px; background: var(--accent); border-radius: 10px; position: relative; cursor: pointer;">
                        <div style="position: absolute; width: 16px; height: 16px; background: white; border-radius: 50%; top: 2px; right: 2px;"></div>
                    </div>
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
        </div>
        
        <div class="map-bar-body" style="flex: 1; overflow-y: auto; padding: 16px;">
            <!-- Statistics Grid -->
            <div class="map-stat-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px;">
                <div class="mstat-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px;">
                    <div class="mstat-v" style="font-size: 18px; font-weight: 700; color: var(--accent);" id="statShops">{{ $shops->count() }}</div>
                    <div class="mstat-l" style="font-size: 9px; color: var(--text-3); margin-top: 4px;">Shops on Map</div>
                </div>
                <div class="mstat-card" style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px;">
                    <div class="mstat-v" style="font-size: 18px; font-weight: 700; color: var(--green);" id="statTotalWallet">₹{{ number_format($shops->sum('wallet_balance'), 2) }}</div>
                    <div class="mstat-l" style="font-size: 9px; color: var(--text-3); margin-top: 4px;">Total Wallet</div>
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
            
            <!-- Shops List -->
            <div style="font-size: 9px; font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-3); margin-bottom: 10px;">Shops on Map</div>
            <div id="mapShopList">
                @forelse($shops as $shop)
                <div class="map-shop-row" data-lat="{{ $shop->latitude }}" data-lng="{{ $shop->longitude }}" data-name="{{ $shop->name }}" data-wallet="{{ $shop->wallet_balance }}" data-address="{{ $shop->business_display_address ?? 'Address not set' }}" style="display: flex; align-items: center; gap: 8px; padding: 8px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 6px; cursor: pointer;">
                    <div class="msr-dot" style="width: 10px; height: 10px; border-radius: 50%; background: #4f6ef7;"></div>
                    <div class="msr-info" style="flex: 1;">
                        <div class="msr-name" style="font-size: 11px; font-weight: 600;">{{ $shop->name }}</div>
                        <div class="msr-sub" style="font-size: 9px; color: var(--text-3);">₹{{ number_format($shop->wallet_balance, 2) }}</div>
                    </div>
                </div>
                @empty
                <div style="padding: 20px; text-align: center; color: var(--text-3);">
                    No shops with coordinates found.<br>
                    <small>Add latitude/longitude to shop records to display on map.</small>
                </div>
                @endforelse
            </div>
        </div>
    </div>
    
    <!-- Right: Map -->
    <div class="map-wrap" style="flex: 1; position: relative;">
        <div id="leafletMap" style="height: 100%; width: 100%;"></div>
        <div class="map-float" style="position: absolute; top: 16px; right: 16px; z-index: 500; display: flex; flex-direction: column; gap: 8px;">
            <button class="map-float-btn" id="fitIndiaBtn" style="background: rgba(7,11,24,0.92); backdrop-filter: blur(16px); border: 1px solid var(--border-hi); border-radius: 8px; padding: 8px 12px; font-family: var(--mono); font-size: 10px; color: var(--text); cursor: pointer;">
                <i class="fas fa-compress-arrows-alt"></i> Fit India
            </button>
            <button class="map-float-btn" id="fitOdishaBtn" style="background: rgba(7,11,24,0.92); backdrop-filter: blur(16px); border: 1px solid var(--border-hi); border-radius: 8px; padding: 8px 12px; font-family: var(--mono); font-size: 10px; color: var(--text); cursor: pointer;">
                <i class="fas fa-map-pin"></i> Fit Odisha
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6.5.0/turf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/polygon-clipping@0.15.7/dist/polygon-clipping.umd.min.js"></script>
<script id="shops-data-json" type="application/json">
@json($shops, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
</script>
<script>
    // Capture very-early script failures.
    window.addEventListener('error', function(e) {
        console.error('Map page JS error:', e.message, e.filename, e.lineno);
    });

    document.addEventListener('DOMContentLoaded', function() {
        try {
        if (typeof L === 'undefined') {
            console.error('Leaflet not loaded.');
            return;
        }

        // Initialize map - center on India
        var map = L.map('leafletMap').setView([20.5937, 78.9629], 5);
        
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
        }).addTo(map);
        
        // Data from Laravel (safe JSON parsing to avoid inline-script breakage).
        var shopsRaw = [];
        try {
            var shopsJsonEl = document.getElementById('shops-data-json');
            shopsRaw = shopsJsonEl ? JSON.parse(shopsJsonEl.textContent || '[]') : [];
        } catch (jsonErr) {
            console.error('Failed to parse shops JSON:', jsonErr);
            shopsRaw = [];
        }
        var shops = Array.isArray(shopsRaw) ? shopsRaw : Object.values(shopsRaw || {});
        var shopMarkersLayer = null;
        var territoryLayer = null;
        var currentRadius = 25;
        var debugBox = null;

        function mountDebugBox() {
            debugBox = document.createElement('div');
            debugBox.style.cssText = 'position:absolute;left:10px;bottom:10px;z-index:1200;background:rgba(7,11,24,0.9);border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:6px 8px;font-size:10px;font-family:monospace;color:#9bb4d6;pointer-events:none;';
            document.querySelector('.map-wrap').appendChild(debugBox);
        }

        function setDebug(text) {
            if (!debugBox) return;
            debugBox.textContent = text;
        }

        function getValidShopCoords() {
            var coords = [];
            shops.forEach(function(shop) {
                var latRaw = shop.latitude ?? shop.lat;
                var lngRaw = shop.longitude ?? shop.lng;
                if (latRaw !== null && latRaw !== undefined && lngRaw !== null && lngRaw !== undefined) {
                    var lat = parseFloat(latRaw);
                    var lng = parseFloat(lngRaw);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        coords.push({ lat: lat, lng: lng, shop: shop });
                    }
                }
            });
            return coords;
        }
        
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
            getValidShopCoords().forEach(function(entry) {
                var shop = entry.shop;
                var marker = L.marker([entry.lat, entry.lng], {
                    icon: L.divIcon({
                        html: '<div style="background: #4f6ef7; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);"><i class="fas fa-store" style="color: white; font-size: 12px;"></i></div>',
                        iconSize: [28, 28],
                        className: ''
                    })
                }).bindPopup(`
                    <div style="font-family: 'Syne', sans-serif; min-width: 200px;">
                        <strong style="font-size: 14px;">${escapeHtml(shop.name)}</strong><br>
                        💰 Wallet: ₹${Number(shop.wallet_balance || 0).toLocaleString()}<br>
                        📍 ${escapeHtml(shop.business_display_address || 'Address not set')}<br>
                        ${shop.business_phone ? '📞 ' + shop.business_phone : ''}
                    </div>
                `);
                marker.addTo(group);
            });
            return group;
        }

        // Fallback polygon territory (convex-like) when geometric union is unavailable.
        function createTerritoryFallback(radius) {
            var coords = getValidShopCoords();
            if (!coords.length) return null;

            // 1 point: create a diamond polygon around the shop.
            if (coords.length === 1) {
                var c = coords[0];
                var dLat = Math.max(0.05, radius / 111); // km -> deg (approx)
                var dLng = Math.max(0.05, radius / (111 * Math.cos(c.lat * Math.PI / 180)));
                return L.polygon([
                    [c.lat + dLat, c.lng],
                    [c.lat, c.lng + dLng],
                    [c.lat - dLat, c.lng],
                    [c.lat, c.lng - dLng],
                ], {
                    color: '#1fcfaa',
                    fillColor: '#1fcfaa',
                    fillOpacity: 0.2,
                    weight: 2,
                    opacity: 0.95,
                    dashArray: '6, 5',
                    interactive: false
                });
            }

            // >=2 points: convex hull polygon (monotonic chain).
            var points = coords.map(function(p) { return { x: p.lng, y: p.lat }; });
            points.sort(function(a, b) {
                return a.x === b.x ? a.y - b.y : a.x - b.x;
            });

            function cross(o, a, b) {
                return (a.x - o.x) * (b.y - o.y) - (a.y - o.y) * (b.x - o.x);
            }

            var lower = [];
            for (var i = 0; i < points.length; i++) {
                while (lower.length >= 2 && cross(lower[lower.length - 2], lower[lower.length - 1], points[i]) <= 0) {
                    lower.pop();
                }
                lower.push(points[i]);
            }

            var upper = [];
            for (var j = points.length - 1; j >= 0; j--) {
                while (upper.length >= 2 && cross(upper[upper.length - 2], upper[upper.length - 1], points[j]) <= 0) {
                    upper.pop();
                }
                upper.push(points[j]);
            }

            upper.pop();
            lower.pop();
            var hull = lower.concat(upper);
            if (hull.length < 3) {
                // Degenerate 2-point case: light rectangle-ish polygon.
                var a = coords[0], b = coords[1];
                var d = Math.max(0.03, radius / 180);
                return L.polygon([
                    [a.lat + d, a.lng + d],
                    [b.lat + d, b.lng + d],
                    [b.lat - d, b.lng - d],
                    [a.lat - d, a.lng - d],
                ], {
                    color: '#1fcfaa',
                    fillColor: '#1fcfaa',
                    fillOpacity: 0.2,
                    weight: 2,
                    opacity: 0.95,
                    dashArray: '6, 5',
                    interactive: false
                });
            }

            var latLngs = hull.map(function(p) { return [p.y, p.x]; });
            return L.polygon(latLngs, {
                color: '#1fcfaa',
                fillColor: '#1fcfaa',
                fillOpacity: 0.2,
                weight: 2,
                opacity: 0.95,
                dashArray: '6, 5',
                interactive: false
            });
        }

        // Create a geodesic-like circle polygon around a lat/lng.
        function circlePolygonLngLat(lat, lng, radiusKm, steps) {
            var points = [];
            var latRad = lat * Math.PI / 180;
            var kmPerDegLat = 111.32;
            var kmPerDegLng = Math.max(0.000001, 111.32 * Math.cos(latRad));
            for (var i = 0; i <= steps; i++) {
                var t = (i / steps) * Math.PI * 2;
                var dLat = (radiusKm / kmPerDegLat) * Math.sin(t);
                var dLng = (radiusKm / kmPerDegLng) * Math.cos(t);
                points.push([lng + dLng, lat + dLat]); // [lng, lat]
            }
            return [points]; // Polygon rings
        }

        // True "merged circles" territory: union of shop-radius circles.
        function createMergedCircleTerritory(radius) {
            var coords = getValidShopCoords();
            if (!coords.length) return null;

            if (typeof polygonClipping === 'undefined' || typeof polygonClipping.union !== 'function') {
                console.warn('polygon-clipping unavailable; using fallback territory.');
                return createTerritoryFallback(radius);
            }

            var steps = 56; // smoother circle boundary
            var merged = null;

            for (var i = 0; i < coords.length; i++) {
                var c = coords[i];
                var circlePoly = [circlePolygonLngLat(c.lat, c.lng, radius, steps)]; // MultiPolygon
                try {
                    merged = merged ? polygonClipping.union(merged, circlePoly) : circlePoly;
                } catch (e) {
                    console.warn('Circle union failed at index', i, e);
                }
            }

            if (!merged || !merged.length) {
                return createTerritoryFallback(radius);
            }

            return L.geoJSON({
                type: 'Feature',
                geometry: {
                    type: 'MultiPolygon',
                    coordinates: merged
                },
                properties: {}
            }, {
                style: {
                    color: '#1fcfaa',
                    fillColor: '#1fcfaa',
                    fillOpacity: 0.2,
                    weight: 2,
                    opacity: 0.95,
                    dashArray: '6, 5'
                },
                interactive: false
            });
        }
        
        // Create merged-circle territory polygon (primary), with fallback if needed.
        function createTerritoryUnion(radius) {
            var coords = getValidShopCoords();
            if (coords.length < 1) return null;

            var mergedCircleLayer = createMergedCircleTerritory(radius);
            if (mergedCircleLayer) {
                return mergedCircleLayer;
            }

            if (typeof turf === 'undefined') {
                console.warn('Turf is unavailable. Rendering fallback territory.');
                return createTerritoryFallback(radius);
            }

            // Turf API compatibility (some bundles expose helpers under turf.helpers).
            var turfPoint = turf.point || (turf.helpers && turf.helpers.point);
            var turfBuffer = turf.buffer;
            var turfConvex = turf.convex;
            var turfFeatureCollection = turf.featureCollection;

            if (!turfPoint || !turfBuffer || !turfConvex || !turfFeatureCollection) {
                console.warn('Turf API incomplete. Rendering polygon fallback territory.');
                return createTerritoryFallback(radius);
            }

            var points = coords.map(function(entry) {
                return turfPoint([entry.lng, entry.lat]);
            });

            try {
                // For larger datasets, avoid expensive iterative union.
                if (points.length > 12) {
                    var fc = turfFeatureCollection(points);
                    var hull = turfConvex(fc);

                    if (hull) {
                        var hullBuffered = turfBuffer(hull, radius, { units: 'kilometers' });
                        return L.geoJSON(hullBuffered, {
                            style: {
                                color: '#1fcfaa',
                    fillColor: '#1fcfaa',
                    fillOpacity: 0.2,
                    weight: 2,
                    opacity: 0.95,
                    dashArray: '6, 5'
                },
                interactive: false
            });
        }

                    return createTerritoryFallback(radius);
                }

                // Small set: build hull and buffer it. No union dependency.
                var fcSmall = turfFeatureCollection(points);
                var hullSmall = turfConvex(fcSmall);
                if (!hullSmall) {
                    return createTerritoryFallback(radius);
                }

                var buffered = turfBuffer(hullSmall, radius, { units: 'kilometers' });
                if (!buffered) {
                    return createTerritoryFallback(radius);
                }

                return L.geoJSON(buffered, {
                    style: {
                        color: '#1fcfaa',
                        fillColor: '#1fcfaa',
                        fillOpacity: 0.08,
                        weight: 1.5,
                        opacity: 0.6,
                        dashArray: '6, 5'
                    },
                    interactive: false
                });
            } catch (e) {
                console.warn('Territory generation failed, using fallback polygon.', e);
                return createTerritoryFallback(radius);
            }
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
        
        // Initialize layers if shops exist
        mountDebugBox();
        var validCoords = getValidShopCoords();
        if (validCoords.length > 0) {
            shopMarkersLayer = createShopMarkers();
            territoryLayer = createTerritoryUnion(currentRadius);
            applyLayerVisibility();
            
            // Fit map to show all markers
            var bounds = L.latLngBounds([]);
            validCoords.forEach(function(entry) {
                bounds.extend([entry.lat, entry.lng]);
            });
            if (bounds.isValid()) {
                map.fitBounds(bounds.pad(0.2));
            }
            setDebug('shops=' + shops.length + ' valid=' + validCoords.length + ' mode=active');
        } else {
            setDebug('shops=' + shops.length + ' valid=0 (check latitude/longitude fields)');
        }
        
        // Toggle functionality
        document.querySelectorAll('.tog-row').forEach(function(row) {
            var layer = row.dataset.layer;
            var togSwitch = row.querySelector('.tog-switch');
            var innerDiv = togSwitch.querySelector('div');
            
            row.addEventListener('click', function(e) {
                e.stopPropagation();
                layerState[layer] = !layerState[layer];
                
                // Update toggle UI
                if (layerState[layer]) {
                    togSwitch.style.background = 'var(--accent)';
                    if (innerDiv) {
                        innerDiv.style.right = '2px';
                        innerDiv.style.left = 'auto';
                    }
                } else {
                    togSwitch.style.background = 'var(--border-hi)';
                    if (innerDiv) {
                        innerDiv.style.left = '2px';
                        innerDiv.style.right = 'auto';
                    }
                }
                
                if (layer === 'territory') {
                    if (layerState.territory && getValidShopCoords().length > 0) {
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
            if (layerState.territory && getValidShopCoords().length > 0) {
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
                    if (shopMarkersLayer) {
                        shopMarkersLayer.eachLayer(function(layer) {
                            var ll = layer.getLatLng();
                            if (Math.abs(ll.lat - lat) < 0.00001 && Math.abs(ll.lng - lng) < 0.00001) {
                                layer.openPopup();
                            }
                        });
                    }
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            setTimeout(function() { map.invalidateSize(); }, 100);
        });

        // Capture runtime errors for quick diagnosis on-screen.
        window.addEventListener('error', function(e) {
            setDebug('JS error: ' + (e.message || 'unknown'));
        });
        } catch (initErr) {
            console.error('Map init failed:', initErr);
            var wrap = document.querySelector('.map-wrap');
            if (wrap) {
                var err = document.createElement('div');
                err.style.cssText = 'position:absolute;left:10px;bottom:10px;z-index:1300;background:rgba(120,20,20,0.9);border:1px solid rgba(255,255,255,0.2);border-radius:8px;padding:8px 10px;font-size:11px;font-family:monospace;color:#ffdada;';
                err.textContent = 'Map init failed: ' + (initErr.message || 'unknown');
                wrap.appendChild(err);
            }
        }
    });
</script>

<!-- Search Script for Map Page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('globalSearch');
    const resultsContainer = document.getElementById('searchResults');
    let searchTimeout;
    
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            resultsContainer.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetch(`/admin/search?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    let html = '';
                    
                    if (data.shops && data.shops.length > 0) {
                        html += `<div class="search-section-title">SHOPS (${data.shops.length})</div>`;
                        data.shops.forEach(shop => {
                            html += `<div class="search-result-item" onclick="window.location.href='/admin/shops/${shop.id}'">
                                <div class="search-result-icon shops"><i class="fas fa-store-alt"></i></div>
                                <div class="search-result-info">
                                    <div class="search-result-title">${escapeHtml(shop.name)}</div>
                                    <div class="search-result-subtitle">${escapeHtml(shop.email || shop.phone || 'No contact')}</div>
                                </div>
                                <div class="search-result-type">Shop</div>
                            </div>`;
                        });
                    }
                    
                    if (data.customers && data.customers.length > 0) {
                        html += `<div class="search-section-title">CUSTOMERS (${data.customers.length})</div>`;
                        data.customers.forEach(customer => {
                            html += `<div class="search-result-item" onclick="window.location.href='/admin/customers/${customer.id}'">
                                <div class="search-result-icon customers"><i class="fas fa-users"></i></div>
                                <div class="search-result-info">
                                    <div class="search-result-title">${escapeHtml(customer.name)}</div>
                                    <div class="search-result-subtitle">${escapeHtml(customer.phone || customer.address || 'No details')}</div>
                                </div>
                                <div class="search-result-type">Customer</div>
                            </div>`;
                        });
                    }
                    
                    if (data.rentals && data.rentals.length > 0) {
                        html += `<div class="search-section-title">RENTALS (${data.rentals.length})</div>`;
                        data.rentals.forEach(rental => {
                            html += `<div class="search-result-item" onclick="window.location.href='/admin/rentals/${rental.id}'">
                                <div class="search-result-icon rentals"><i class="fas fa-receipt"></i></div>
                                <div class="search-result-info">
                                    <div class="search-result-title">#${rental.id} - ${escapeHtml(rental.vehicle_name)}</div>
                                    <div class="search-result-subtitle">Customer: ${escapeHtml(rental.customer_name)}</div>
                                </div>
                                <div class="search-result-type">Rental</div>
                            </div>`;
                        });
                    }
                    
                    if (!html) {
                        html = `<div class="search-no-results">No results found for "${escapeHtml(query)}"</div>`;
                    }
                    
                    resultsContainer.innerHTML = html;
                    resultsContainer.style.display = 'block';
                })
                .catch(error => {
                    console.error('Search failed:', error);
                    resultsContainer.innerHTML = '<div class="search-no-results">Search error. Please try again.</div>';
                    resultsContainer.style.display = 'block';
                });
        }, 300);
    });
    
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
    
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
});
</script>
@endpush
