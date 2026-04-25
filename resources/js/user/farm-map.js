import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import booleanPointInPolygon from '@turf/boolean-point-in-polygon';
import { point as turfPoint } from '@turf/helpers';

(function () {
    'use strict';

    const DEFAULT_CENTER = [17.65, 121.72];
    const DEFAULT_ZOOM = 11;
    const TRACK_ZOOM = 15;
    const FIXED_FLOOD_RISK_MAP = (function () {
        function canonical(name) {
            return String(name || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        }
        var high = [
            'Abolo', 'Alit Untung', 'Annafatan', 'Anquiray', 'Babayuan', 'Bauan', 'Baccuit', 'Baculud',
            'Balauini', 'Calamagui', 'Casingsingan Norte', 'Casingsingan Sur', 'Centro', 'Dafunganay',
            'Dugayung', 'Estefania', 'Gabut', 'Jurisdiccion', 'Logung', 'Marobbob',
            'Pacac-Grande', 'Pacac-Pequeño', 'Palacu', 'Palayag',
            'Unag', 'Concepcion', 'Tana', 'Annabuculan', 'Agguirit', 'Aggurit', 'Goran', 'Monte Alegre',
        ];
        var moderate = [
            'Dadda', 'Cordova', 'Calintaan', 'Caratacat', 'Gangauan', 'Magogod', 'Manalo', 'Masical', 'Nangalasauan',
        ];
        var low = [
            'Nabbialan', 'San Juan', 'La Suerte', 'Bacring', 'Backring', 'Nagsabaran', 'Bayabat',
            'Nanuccauan', 'Catarauan', 'Cataruan',
        ];
        var out = {};
        high.forEach(function (name) { out[canonical(name)] = 'high'; });
        moderate.forEach(function (name) { out[canonical(name)] = 'moderate'; });
        low.forEach(function (name) { out[canonical(name)] = 'low'; });
        return out;
    })();
    const RISK_STYLE = {
        high: { fill: '#ff0000', line: '#7f1d1d', label: 'High Risk', colorLabel: 'Red' },
        moderate: { fill: '#ffd700', line: '#854d0e', label: 'Moderate Risk', colorLabel: 'Yellow' },
        low: { fill: '#00aa00', line: '#14532d', label: 'Low Risk', colorLabel: 'Green' },
        unknown: { fill: '#94a3b8', line: '#334155', label: 'Unknown Risk' },
    };

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        var root = document.getElementById('farm-map-page');
        if (!root) {
            return;
        }

        var clayB64 = root.dataset.advClayB64;
        if (clayB64 && typeof atob !== 'undefined') {
            try {
                window.AGRI_MAP_ADV_CLAY = JSON.parse(atob(clayB64));
            } catch (e) {
                window.AGRI_MAP_ADV_CLAY = window.AGRI_MAP_ADV_CLAY || {};
            }
        }

        var contextUrl = root.dataset.contextUrl || '';
        var saveUrl = root.dataset.saveUrl || '';
        var geofenceUrl = root.dataset.geofenceUrl || '/amulung.json';
        var csrf = root.dataset.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        var mapEl = document.getElementById('farm-map-container');
        var gpsLastEl = document.getElementById('farm-map-gps-last');
        var errEl = document.getElementById('farm-map-gps-error');
        var statusGpsEl = document.getElementById('farm-map-status-gps');
        var statusRainEl = document.getElementById('farm-map-status-rain'); // reused for geofence state text
        var layerEl = document.getElementById('farm-map-layer-toggles');
        var todaySummaryEl = document.getElementById('farm-map-today-summary');
        var advisoryStatusEl = document.getElementById('farm-map-advisory-status-line');
        var advisoryMainActionEl = document.getElementById('farm-map-advisory-main-action');
        var advisoryPlanEarlyEl = document.getElementById('farm-map-plan-early');
        var advisoryPlanMiddayEl = document.getElementById('farm-map-plan-midday');
        var advisoryPlanLateEl = document.getElementById('farm-map-plan-late');
        var advisoryPlanWaterEl = document.getElementById('farm-map-plan-water');
        var advisoryPlanAvoidEl = document.getElementById('farm-map-plan-avoid');
        var snapshotGridEl = document.getElementById('farm-map-summary-grid');
        var btnUse = document.getElementById('farm-map-btn-use-gps');
        var btnRefresh = document.getElementById('farm-map-btn-refresh-gps');
        var btnRetry = document.getElementById('farm-map-gps-retry');
        var geofenceBadge = document.getElementById('farm-map-geofence-badge');
        var emptyOverlayEl = document.getElementById('farm-map-empty-overlay');

        var map = null;
        var geofenceLayer = null;
        var barangayLabelsLayer = null;
        var clickMarker = null;
        var gpsMarker = null;
        var gpsAccuracy = null;
        var geofenceFeatureCollection = null;
        var mapReady = false;
        var activeOverlay = 'none';
        var LABEL_MIN_ZOOM = 12;

        function recenterMap() {
            if (!map) {
                return;
            }
            if (gpsMarker) {
                map.flyTo(gpsMarker.getLatLng(), TRACK_ZOOM, { duration: 0.35 });
            } else {
                map.flyTo(DEFAULT_CENTER, DEFAULT_ZOOM, { duration: 0.35 });
            }
        }

        function wireMapControls() {
            var zi = document.getElementById('farm-map-zoom-in');
            var zo = document.getElementById('farm-map-zoom-out');
            var rc = document.getElementById('farm-map-recenter');
            var rg = document.getElementById('farm-map-gps-recenter');
            var fs = document.getElementById('farm-map-fullscreen');
            if (!zi || !zo || !rc) {
                return;
            }
            zi.addEventListener('click', function () {
                map && map.zoomIn();
            });
            zo.addEventListener('click', function () {
                map && map.zoomOut();
            });
            rc.addEventListener('click', recenterMap);
            if (rg) {
                rg.addEventListener('click', recenterMap);
            }
            var mapFrame = document.querySelector('.farm-map-stack__frame');
            if (fs && mapFrame && document.documentElement.requestFullscreen) {
                fs.addEventListener('click', function () {
                    if (!document.fullscreenElement) {
                        mapFrame.requestFullscreen().catch(function () {});
                    } else {
                        document.exitFullscreen();
                    }
                });
            }
        }

        function setEmptyOverlayVisible(visible) {
            if (!emptyOverlayEl) {
                return;
            }
            if (visible) {
                emptyOverlayEl.classList.remove('hidden');
                emptyOverlayEl.setAttribute('aria-hidden', 'false');
            } else {
                emptyOverlayEl.classList.add('hidden');
                emptyOverlayEl.setAttribute('aria-hidden', 'true');
            }
        }

        function showErr(html) {
            if (!errEl) {
                return;
            }
            errEl.innerHTML = html;
            errEl.classList.remove('hidden');
            if (btnRetry) {
                btnRetry.classList.remove('hidden');
            }
        }

        function clearErr() {
            if (!errEl) {
                return;
            }
            errEl.classList.add('hidden');
            errEl.textContent = '';
            if (btnRetry) {
                btnRetry.classList.add('hidden');
            }
        }

        function setLoading(isLoading) {
            if (btnUse) btnUse.disabled = isLoading;
            if (btnRefresh) btnRefresh.disabled = isLoading;
        }

        function setGeofenceBadge(state) {
            if (!geofenceBadge) return;
            var inside = state === 'inside_geofence';
            geofenceBadge.className = 'farm-map-geofence-badge ' + (inside ? 'farm-map-geofence-badge--inside' : 'farm-map-geofence-badge--outside');
            geofenceBadge.textContent = inside ? 'Inside Geofence' : 'Outside Geofence';
        }

        function setGeofenceStyleForState(state) {
            if (!geofenceLayer) return;
            var inside = state === 'inside_geofence';
            geofenceLayer.setStyle({
                opacity: inside ? 0.95 : 0.9,
            });
        }

        function getRiskStyle(riskLevel) {
            return RISK_STYLE[riskLevel] || RISK_STYLE.unknown;
        }

        function getFeatureName(feature) {
            var p = (feature && feature.properties) || {};
            return String(p.adm4_en || p.name || p.barangay || p.brgy_name || 'Unknown Barangay').trim();
        }

        function enrichBarangayRisk(feature) {
            var name = getFeatureName(feature);
            var canonical = String(name).toLowerCase().replace(/[^a-z0-9]/g, '');
            var riskLevel = FIXED_FLOOD_RISK_MAP[canonical] || 'unknown';
            var style = getRiskStyle(riskLevel);
            feature.properties = feature.properties || {};
            feature.properties.flood_risk_level = riskLevel;
            feature.properties.flood_risk_label = style.label;
            feature.properties.flood_risk_color_label = style.colorLabel || 'Unknown';
            feature.properties.flood_risk_fill = style.fill;
            feature.properties.risk_reason = 'Configured barangay flood classification';
            return feature;
        }

        function updateBarangayRiskData(featureCollection) {
            if (!featureCollection || !Array.isArray(featureCollection.features)) {
                return featureCollection;
            }
            featureCollection.features = featureCollection.features.map(function (feature) {
                return enrichBarangayRisk(feature);
            });
            return featureCollection;
        }

        function detectBarangayAtPoint(latlng) {
            if (!geofenceFeatureCollection || !Array.isArray(geofenceFeatureCollection.features)) {
                return null;
            }
            var p = turfPoint([latlng.lng, latlng.lat]);
            for (var i = 0; i < geofenceFeatureCollection.features.length; i += 1) {
                var feature = geofenceFeatureCollection.features[i];
                try {
                    if (booleanPointInPolygon(p, feature, { ignoreBoundary: false })) {
                        return feature;
                    }
                } catch (e) {
                    // Ignore malformed feature and continue.
                }
            }
            return null;
        }

        function detectGeofenceStatus(latlng) {
            if (!geofenceFeatureCollection || !geofenceFeatureCollection.features || !geofenceFeatureCollection.features.length) {
                return 'outside_geofence';
            }
            var p = turfPoint([latlng.lng, latlng.lat]);
            var isInside = geofenceFeatureCollection.features.some(function (feature) {
                try {
                    return booleanPointInPolygon(p, feature, { ignoreBoundary: false });
                } catch (e) {
                    return false;
                }
            });
            return isInside ? 'inside_geofence' : 'outside_geofence';
        }

        function applyGeofenceResult(latlng, sourceLabel) {
            var geofenceState = detectGeofenceStatus(latlng);
            setGeofenceBadge(geofenceState);
            setGeofenceStyleForState(geofenceState);
            var popupText = geofenceState === 'inside_geofence' ? 'Inside Geofence' : 'Outside Geofence';
            var details = (sourceLabel || 'Map point') + ': ' + latlng.lat.toFixed(6) + ', ' + latlng.lng.toFixed(6);
            var matchedBarangay = detectBarangayAtPoint(latlng);
            var barangayHtml = '';
            if (matchedBarangay) {
                var props = matchedBarangay.properties || {};
                barangayHtml =
                    '<br><strong>Barangay:</strong> ' + esc(getFeatureName(matchedBarangay)) +
                    '<br><strong>Flood risk:</strong> ' + esc(props.flood_risk_label || 'Unknown Risk') +
                    '<br><strong>Color class:</strong> ' + esc(props.flood_risk_color_label || 'Unknown');
            } else {
                barangayHtml = '<br><strong>Barangay:</strong> No polygon match';
            }
            if (clickMarker) {
                map.removeLayer(clickMarker);
            }
            clickMarker = L.circleMarker(latlng, {
                radius: 7,
                color: '#1d4ed8',
                weight: 2,
                fillColor: '#3b82f6',
                fillOpacity: 0.9,
            }).addTo(map);
            clickMarker.bindPopup('<strong>' + popupText + '</strong><br>' + details + barangayHtml).openPopup();
            return geofenceState;
        }

        function updateLayerLegend() {
            var el = document.getElementById('farm-map-layer-legend');
            if (!el) return;
            el.textContent = activeOverlay === 'none'
                ? 'Barangays are flood-risk colored by configured classification (red high, yellow moderate, green low).'
                : activeOverlay === 'flood'
                  ? 'Flood layer enabled (prototype overlay).'
                  : activeOverlay === 'crop'
                    ? 'Crop zone layer enabled (prototype overlay).'
                    : 'Barangay boundary layer enabled (prototype overlay).';
        }

        function applyOverlayVisibility() {
            updateLayerLegend();
        }

        function buildOverlayDefs(center) {
            return {
                flood: L.circle(center, {
                    radius: 480,
                    color: '#1d4ed8',
                    weight: 1,
                    fillColor: '#60a5fa',
                    fillOpacity: 0.18,
                }),
                crop: L.circle(center, {
                    radius: 320,
                    color: '#047857',
                    weight: 1,
                    fillColor: '#34d399',
                    fillOpacity: 0.17,
                }),
                barangay: L.circle(center, {
                    radius: 240,
                    color: '#6d28d9',
                    weight: 1,
                    fillColor: '#a78bfa',
                    fillOpacity: 0.15,
                }),
            };
        }

        var overlays = null;

        function renderOverlay(center) {
            if (!map) return;
            if (overlays) {
                Object.keys(overlays).forEach(function (k) {
                    map.removeLayer(overlays[k]);
                });
            }
            overlays = buildOverlayDefs(center);
            if (activeOverlay !== 'none' && overlays[activeOverlay]) {
                overlays[activeOverlay].addTo(map);
            }
            updateLayerLegend();
        }

        function initMap() {
            if (!mapEl || mapReady) return;
            map = L.map(mapEl, {
                zoomControl: false,
                attributionControl: true,
            });
            map.createPane('barangayLabelPane');
            map.getPane('barangayLabelPane').style.zIndex = '680';
            map.getPane('barangayLabelPane').style.pointerEvents = 'none';
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);
            map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
            map.on('click', function (e) {
                applyGeofenceResult(e.latlng, 'Clicked point');
            });
            map.on('zoomend', updateBarangayLabelVisibility);
            renderOverlay(DEFAULT_CENTER);
            wireMapControls();
            mapReady = true;
            loadGeofenceSource();

            var mapFrame = document.querySelector('.farm-map-stack__frame');
            if (mapFrame) {
                document.addEventListener('fullscreenchange', function () {
                    setTimeout(function () {
                        map.invalidateSize();
                    }, 120);
                });
            }
        }

        function labelTextForFeature(feature) {
            var p = (feature && feature.properties) || {};
            return String(
                p.adm4_en || p.name || p.barangay || p.brgy_name || ''
            ).trim();
        }

        function buildBarangayLabels(featureCollection) {
            if (!map || !featureCollection || !Array.isArray(featureCollection.features)) {
                return;
            }
            if (barangayLabelsLayer) {
                map.removeLayer(barangayLabelsLayer);
            }
            barangayLabelsLayer = L.layerGroup();
            featureCollection.features.forEach(function (feature) {
                var label = labelTextForFeature(feature);
                if (!label) {
                    return;
                }
                var featureLayer = L.geoJSON(feature);
                var bounds = featureLayer.getBounds();
                if (!bounds || !bounds.isValid()) {
                    return;
                }
                var center = bounds.getCenter();
                var marker = L.marker(center, {
                    pane: 'barangayLabelPane',
                    interactive: false,
                    keyboard: false,
                    opacity: 0,
                });
                marker.bindTooltip(label, {
                    permanent: true,
                    direction: 'center',
                    className: 'farm-map-barangay-label',
                    opacity: 1,
                    interactive: false,
                });
                barangayLabelsLayer.addLayer(marker);
            });
            barangayLabelsLayer.addTo(map);
            updateBarangayLabelVisibility();
        }

        function updateBarangayLabelVisibility() {
            if (!map || !barangayLabelsLayer) {
                return;
            }
            if (map.getZoom() >= LABEL_MIN_ZOOM) {
                if (!map.hasLayer(barangayLabelsLayer)) {
                    barangayLabelsLayer.addTo(map);
                }
            } else if (map.hasLayer(barangayLabelsLayer)) {
                map.removeLayer(barangayLabelsLayer);
            }
        }

        function loadGeofenceSource() {
            fetch(geofenceUrl, { headers: { Accept: 'application/json' } })
                .then(function (res) {
                    if (!res.ok) throw new Error('geofence fetch failed');
                    return res.json();
                })
                .then(function (geojson) {
                    geofenceFeatureCollection =
                        geojson.type === 'FeatureCollection' ? geojson : { type: 'FeatureCollection', features: [geojson] };
                    var featureBoundsLayer = L.geoJSON(geofenceFeatureCollection);
                    var bounds = featureBoundsLayer.getBounds && featureBoundsLayer.getBounds().isValid() ? featureBoundsLayer.getBounds() : null;
                    geofenceFeatureCollection = updateBarangayRiskData(geofenceFeatureCollection);
                    if (geofenceLayer) {
                        map.removeLayer(geofenceLayer);
                    }
                    geofenceLayer = L.geoJSON(geofenceFeatureCollection, {
                        style: function (feature) {
                            var riskLevel = (feature && feature.properties && feature.properties.flood_risk_level) || 'unknown';
                            var style = getRiskStyle(riskLevel);
                            return {
                                color: style.line,
                                weight: 1.9,
                                fillColor: style.fill,
                                fillOpacity: 0.24,
                            };
                        },
                        onEachFeature: function (feature, layer) {
                            var props = feature.properties || {};
                            var name = getFeatureName(feature);
                            var riskLabel = props.flood_risk_label || 'Unknown Risk';
                            var riskColor = props.flood_risk_color_label || 'Unknown';
                            var riskReason = props.risk_reason || 'Configured barangay flood classification';
                            layer.bindTooltip(name + ' • ' + riskLabel, { sticky: true });
                            layer.bindPopup(
                                '<strong>' + esc(name) + '</strong><br>' +
                                'Flood risk: <strong>' + esc(riskLabel) + '</strong><br>' +
                                'Color class: ' + esc(riskColor) + '<br>' +
                                'Risk reason: ' + esc(riskReason)
                            );
                            layer.on('mouseover', function () {
                                layer.setStyle({ weight: 3, fillOpacity: 0.35 });
                            });
                            layer.on('mouseout', function () {
                                geofenceLayer.resetStyle(layer);
                            });
                        },
                        interactive: true,
                    }).addTo(map);
                    geofenceLayer.bringToFront();
                    buildBarangayLabels(geofenceFeatureCollection);
                    if (bounds) {
                        map.fitBounds(bounds, { padding: [16, 16] });
                    } else if (geofenceLayer.getBounds && geofenceLayer.getBounds().isValid()) {
                        map.fitBounds(geofenceLayer.getBounds(), { padding: [16, 16] });
                    }
                    setEmptyOverlayVisible(false);
                    setGeofenceBadge('outside_geofence');
                    updateLayerLegend();
                })
                .catch(function () {
                    showErr('Could not load Amulung geofence boundary.');
                });
        }

        function renderLayerToggles() {
            if (!layerEl) return;
            var defs = [
                { id: 'none', icon: '🧭', label: 'Base' },
                { id: 'flood', icon: '🌊', label: 'Flood' },
                { id: 'crop', icon: '🌾', label: 'Crop' },
                { id: 'barangay', icon: '🧩', label: 'Barangay' },
            ];
            layerEl.innerHTML = '';
            defs.forEach(function (d) {
                var b = document.createElement('button');
                var on = activeOverlay === d.id;
                b.type = 'button';
                b.className = 'farm-map-layer-btn' + (on ? ' farm-map-layer-btn--on' : '');
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
                b.innerHTML =
                    '<span class="farm-map-layer-btn__ic" aria-hidden="true">' + d.icon + '</span>' +
                    '<span class="farm-map-layer-btn__lbl">' + d.label + '</span>';
                b.addEventListener('click', function () {
                    activeOverlay = d.id;
                    renderLayerToggles();
                    if (gpsMarker) {
                        renderOverlay(gpsMarker.getLatLng());
                    } else {
                        renderOverlay(map.getCenter());
                    }
                });
                layerEl.appendChild(b);
            });
        }

        function fetchContext() {
            if (!contextUrl) {
                return Promise.resolve(null);
            }
            return window.axios
                .get(contextUrl, { headers: { Accept: 'application/json' } })
                .then(function (res) {
                    return res.data || null;
                })
                .catch(function () {
                    return null;
                });
        }

        function esc(s) {
            var d = document.createElement('div');
            d.textContent = String(s == null ? '' : s);
            return d.innerHTML;
        }

        function renderSnapshotCards(ctx) {
            if (!snapshotGridEl) return;
            if (!ctx || !ctx.map_ready) {
                snapshotGridEl.innerHTML =
                    '<article class="farm-map-snap-card farm-map-snap-card--loc"><div class="farm-map-snap-card__row"><div class="farm-map-snap-card__main"><p class="farm-map-snap-card__label">Location</p><p class="farm-map-snap-card__value">GPS connected, syncing farm data…</p><p class="farm-map-snap-card__sub">Snapshot will appear once context is available.</p></div></div></article>' +
                    '<article class="farm-map-snap-card farm-map-snap-card--rain"><div class="farm-map-snap-card__row"><div class="farm-map-snap-card__main"><p class="farm-map-snap-card__label">Rain Chance</p><p class="farm-map-snap-card__value">Waiting for weather data</p><p class="farm-map-snap-card__sub">Forecast probability will populate automatically.</p></div></div></article>';
                return;
            }

            var weather = ctx.weather || {};
            var snapshot = ctx.risk_snapshot || {};
            var location = esc(ctx.location_display || 'Saved farm location');
            var rainChance = esc(snapshot.rain_chance_display || '—');
            var temp = weather.current_temperature != null ? Math.round(Number(weather.current_temperature)) + '°C' : '—';
            var cond = esc(weather.condition || 'No condition data');
            var effect = esc(snapshot.three_day_effect || 'No forecast impact available');
            snapshotGridEl.innerHTML =
                '<article class="farm-map-snap-card farm-map-snap-card--loc"><div class="farm-map-snap-card__row"><div class="farm-map-snap-card__main"><p class="farm-map-snap-card__label">Location</p><p class="farm-map-snap-card__value">' + location + '</p><p class="farm-map-snap-card__sub">Saved farm location</p></div></div></article>' +
                '<article class="farm-map-snap-card farm-map-snap-card--wx"><div class="farm-map-snap-card__row"><div class="farm-map-snap-card__main"><p class="farm-map-snap-card__label">Weather</p><p class="farm-map-snap-card__value">' + esc(temp) + '</p><p class="farm-map-snap-card__sub">' + cond + '</p></div></div></article>' +
                '<article class="farm-map-snap-card farm-map-snap-card--rain"><div class="farm-map-snap-card__row"><div class="farm-map-snap-card__main"><p class="farm-map-snap-card__label">Rain Chance</p><p class="farm-map-snap-card__value">' + rainChance + '</p><p class="farm-map-snap-card__sub">Forecast probability</p></div></div></article>' +
                '<article class="farm-map-snap-card farm-map-snap-card--effect"><div class="farm-map-snap-card__row"><div class="farm-map-snap-card__main"><p class="farm-map-snap-card__label">3-Day Effect</p><p class="farm-map-snap-card__value">' + effect + '</p><p class="farm-map-snap-card__sub">Forecast trend near your farm</p></div></div></article>';
        }

        function renderTodaySummary(ctx) {
            if (!todaySummaryEl) return;
            if (!ctx || !ctx.map_ready) {
                todaySummaryEl.textContent = 'GPS connected, syncing farm insights…';
                return;
            }
            var weather = ctx.weather || {};
            var rainProb = weather.today_rain_probability;
            var chance = Number.isFinite(Number(rainProb)) ? Math.round(Number(rainProb)) + '% rain chance' : 'rain chance unavailable';
            var effect = (ctx.risk_snapshot || {}).three_day_effect || 'No forecast impact available';
            todaySummaryEl.textContent = 'Today: ' + chance + '. ' + effect + '.';
        }

        function renderAdvisory(ctx) {
            if (!advisoryStatusEl || !advisoryMainActionEl) return;
            function setAdviceBodies(main, early, midday, late, water, avoid) {
                advisoryMainActionEl.textContent = main;
                if (advisoryPlanEarlyEl) advisoryPlanEarlyEl.textContent = early;
                if (advisoryPlanMiddayEl) advisoryPlanMiddayEl.textContent = midday;
                if (advisoryPlanLateEl) advisoryPlanLateEl.textContent = late;
                if (advisoryPlanWaterEl) advisoryPlanWaterEl.textContent = water;
                if (advisoryPlanAvoidEl) advisoryPlanAvoidEl.textContent = avoid;
            }

            if (!ctx || !ctx.map_ready) {
                advisoryStatusEl.innerHTML = '<span class="text-slate-600">AI Smart Advisory: Waiting for GPS</span>';
                setAdviceBodies(
                    'GPS connected, syncing advisory data…',
                    'Waiting for weather data.',
                    'Waiting for weather data.',
                    'Waiting for weather data.',
                    'Waiting for weather data.',
                    'Waiting for weather data.'
                );
                return;
            }

            var m = ctx.map_smart_advisory || null;
            if (!m || m.status !== 'active') {
                advisoryStatusEl.innerHTML = '<span class="text-slate-600">AI Smart Advisory: Ready</span>';
                setAdviceBodies(
                    'No advisory available yet.',
                    'No early-day guidance available yet.',
                    'No midday guidance available yet.',
                    'No late-day guidance available yet.',
                    'No water and drainage guidance available yet.',
                    'No avoid-today guidance available yet.'
                );
                return;
            }

            var doList = Array.isArray(m.what_to_do) ? m.what_to_do : [];
            var watchList = Array.isArray(m.what_to_watch) ? m.what_to_watch : [];
            var avoidList = Array.isArray(m.what_to_avoid) ? m.what_to_avoid : [];
            var smartAction = String(m.smart_action || '').trim() || 'No smart action provided.';
            var earlyDay = String(m.early_day || doList[0] || watchList[0] || 'No early-day guidance.').trim();
            var midday = String(m.midday || doList[1] || watchList[1] || doList[0] || 'No midday guidance.').trim();
            var lateDay = String(m.late_day || doList[2] || watchList[2] || watchList[0] || 'No late-day guidance.').trim();
            var waterDrainage = String(
                m.water_drainage || m.drainage_irrigation_advice || watchList[0] || doList[0] || 'No water and drainage guidance.'
            ).trim();
            var avoidToday = String(
                m.what_to_avoid_today || avoidList[0] || m.avoid || 'No avoid-today guidance.'
            ).trim();

            advisoryStatusEl.innerHTML = '<span class="text-emerald-700">AI Smart Advisory: Active</span>';
            setAdviceBodies(smartAction, earlyDay, midday, lateDay, waterDrainage, avoidToday);
        }

        function applyContextToUI(ctx) {
            renderTodaySummary(ctx);
            renderSnapshotCards(ctx);
            renderAdvisory(ctx);
            if (statusGpsEl) {
                statusGpsEl.textContent = ctx && ctx.map_ready ? 'Connected' : 'Syncing';
            }
            if (statusRainEl) {
                var rainProb = Number((ctx && ctx.weather ? ctx.weather.today_rain_probability : NaN));
                statusRainEl.textContent = Number.isFinite(rainProb) ? Math.round(rainProb) + '%' : '—';
            }
        }

        function formatGpsTime(iso) {
            if (!iso || String(iso).length < 10) {
                return '—';
            }
            try {
                var d = new Date(iso);
                if (isNaN(d.getTime())) {
                    return String(iso).slice(0, 16).replace('T', ' ');
                }
                return d.toLocaleString(undefined, {
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            } catch (e) {
                return '—';
            }
        }

        function saveGps(lat, lng) {
            if (!csrf) {
                showErr('Missing security token. Refresh the page and try again.');
                return Promise.resolve(null);
            }
            setLoading(true);
            clearErr();
            return window.axios
                .post(
                    saveUrl,
                    { latitude: lat, longitude: lng },
                    {
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }
                )
                .then(function (res) {
                    if (!res.data.success) {
                        showErr(escapeHtml(res.data.message || 'Save failed.'));
                        return null;
                    }
                    return res.data;
                })
                .catch(function (e) {
                    var msg =
                        (e.response && e.response.data && e.response.data.message) ||
                        'Could not save GPS. Try again.';
                    showErr(escapeHtml(String(msg)));
                    return null;
                })
                .finally(function () {
                    setLoading(false);
                });
        }

        function setGpsVisual(latlng, accuracy) {
            if (!map) return;
            if (!gpsMarker) {
                gpsMarker = L.circleMarker(latlng, {
                    radius: 8,
                    color: '#ffffff',
                    weight: 2,
                    fillColor: '#2563eb',
                    fillOpacity: 1,
                }).addTo(map);
            } else {
                gpsMarker.setLatLng(latlng);
            }
            if (gpsAccuracy) {
                map.removeLayer(gpsAccuracy);
            }
            gpsAccuracy = L.circle(latlng, {
                radius: Math.max(accuracy || 0, 8),
                color: '#2563eb',
                weight: 1,
                fillColor: '#60a5fa',
                fillOpacity: 0.12,
            }).addTo(map);
            renderOverlay(latlng);
        }

        function geoErrorMessage(err) {
            if (!navigator.geolocation || (err && err.code === 0)) {
                return 'This device or browser does not support GPS.';
            }
            if (!err) {
                return 'Location unavailable.';
            }
            if (err.code === 1) {
                return '<strong>Location blocked.</strong> Allow access in browser settings, then retry.';
            }
            if (err.code === 2) {
                return '<strong>Position unavailable.</strong> Try again with a clearer signal.';
            }
            if (err.code === 3) {
                return '<strong>Timed out.</strong> Try again in an open area.';
            }
            return 'Could not read GPS. Try again.';
        }

        function getPosition() {
            return new Promise(function (resolve, reject) {
                if (!navigator.geolocation) {
                    reject({ code: 0 });
                    return;
                }
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        resolve(pos);
                    },
                    function (err) {
                        reject(err);
                    },
                    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
                );
            });
        }

        function refreshMapData() {
            if (btnRefresh) {
                btnRefresh.disabled = true;
            }
            if (gpsLastEl) {
                gpsLastEl.textContent = 'Refreshing…';
            }
            fetchContext()
                .then(function (ctx) {
                    if (!ctx) return;
                    applyContextToUI(ctx);
                    if (ctx.latitude != null && ctx.longitude != null && map) {
                        var latlng = L.latLng(Number(ctx.latitude), Number(ctx.longitude));
                        setGpsVisual(latlng, 20);
                        applyGeofenceResult(latlng, 'Saved GPS');
                        map.flyTo(latlng, TRACK_ZOOM, { duration: 0.35 });
                    }
                    if (gpsLastEl) gpsLastEl.textContent = 'Last updated: ' + formatGpsTime(ctx.gps_captured_at);
                })
                .finally(function () {
                    if (btnRefresh) btnRefresh.disabled = false;
                });
        }

        function runGpsFlow() {
            clearErr();
            if (gpsLastEl) {
                gpsLastEl.textContent = 'Locating…';
            }
            setLoading(true);
            getPosition()
                .then(function (pos) {
                    var latlng = L.latLng(pos.coords.latitude, pos.coords.longitude);
                    setGpsVisual(latlng, pos.coords.accuracy || 0);
                    applyGeofenceResult(latlng, 'Live GPS');
                    map.flyTo(latlng, TRACK_ZOOM, { duration: 0.35 });
                    if (gpsLastEl) {
                        gpsLastEl.textContent = 'Saving…';
                    }
                    var lat = pos.coords.latitude;
                    var lng = pos.coords.longitude;
                    return saveGps(lat, lng);
                })
                .then(function () {
                    return fetchContext();
                })
                .then(function (ctx) {
                    if (ctx && gpsLastEl) {
                        applyContextToUI(ctx);
                        gpsLastEl.textContent = 'Last updated: ' + formatGpsTime(ctx.gps_captured_at);
                    }
                })
                .catch(function (err) {
                    showErr(geoErrorMessage(err));
                    if (gpsLastEl) {
                        gpsLastEl.textContent = 'Last updated —';
                    }
                })
                .finally(function () {
                    setLoading(false);
                });
        }

        if (btnUse) {
            btnUse.addEventListener('click', runGpsFlow);
        }
        if (btnRefresh) {
            btnRefresh.addEventListener('click', refreshMapData);
        }
        if (btnRetry) {
            btnRetry.addEventListener('click', runGpsFlow);
        }

        if (gpsLastEl) {
            gpsLastEl.textContent = 'Loading…';
        }
        if (statusGpsEl) {
            statusGpsEl.textContent = 'Ready';
        }
        if (statusRainEl) {
            statusRainEl.textContent = '—';
        }
        applyContextToUI(null);
        initMap();
        renderLayerToggles();

        fetchContext().then(function (ctx) {
            if (ctx && ctx.latitude != null && ctx.longitude != null && map) {
                applyContextToUI(ctx);
                var latlng = L.latLng(Number(ctx.latitude), Number(ctx.longitude));
                setGpsVisual(latlng, 20);
                applyGeofenceResult(latlng, 'Saved GPS');
                if (gpsLastEl) gpsLastEl.textContent = 'Last updated: ' + formatGpsTime(ctx.gps_captured_at);
            } else if (gpsLastEl) {
                gpsLastEl.textContent = 'Last updated —';
                applyContextToUI(ctx);
            }
        });
    });
})();
