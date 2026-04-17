/**
 * Farm Map — Leaflet + device GPS + farm context API
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

(function () {
    'use strict';

    const DEFAULT_CENTER = [17.65, 121.72];
    const EMPTY_ZOOM = 11;

    /** High-visibility device GPS pin (DivIcon). Tip aligns with exact lat/lng. */
    var cachedGpsPinIcon = null;
    function getGpsPinIcon() {
        if (!cachedGpsPinIcon) {
            cachedGpsPinIcon = L.divIcon({
                className: 'farm-map-gps-pin-icon',
                html:
                    '<div class="farm-map-gps-pin-wrap">' +
                    '<div class="farm-map-gps-pin" role="img" aria-label="Your farm location">' +
                    '<span class="farm-map-gps-pin__pulse" aria-hidden="true"></span>' +
                    '<div class="farm-map-gps-pin__head"></div>' +
                    '</div>' +
                    '<span class="farm-map-marker-label">Your Farm</span>' +
                    '</div>',
                iconSize: [88, 56],
                iconAnchor: [44, 48],
                popupAnchor: [0, -40],
            });
        }
        return cachedGpsPinIcon;
    }

    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: markerIcon2x,
        iconUrl: markerIcon,
        shadowUrl: markerShadow,
    });

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

        var contextUrl = root.dataset.contextUrl;
        var saveUrl = root.dataset.saveUrl;
        var csrf = root.dataset.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        var mapEl = document.getElementById('farm-map-container');
        var gpsLastEl = document.getElementById('farm-map-gps-last');
        var errEl = document.getElementById('farm-map-gps-error');
        var statusGpsWrap = document.getElementById('farm-map-status-gps-wrap');
        var statusGpsEl = document.getElementById('farm-map-status-gps');
        var statusFloodWrap = document.getElementById('farm-map-status-flood-wrap');
        var statusFloodEl = document.getElementById('farm-map-status-flood');
        var todaySummaryEl = document.getElementById('farm-map-today-summary');
        var lastAccuracyM = null;
        var summaryEl = document.getElementById('farm-map-summary-grid');
        var layerEl = document.getElementById('farm-map-layer-toggles');
        var noGpsHint = document.getElementById('farm-map-no-gps-hint');
        var weatherFloat = document.getElementById('farm-map-weather-float');
        var weatherFloatText = document.getElementById('farm-map-weather-float-text');
        var btnUse = document.getElementById('farm-map-btn-use-gps');
        var btnRefresh = document.getElementById('farm-map-btn-refresh-gps');
        var btnRetry = document.getElementById('farm-map-gps-retry');

        var map = null;
        var marker = null;
        var pendingGpsMarker = null;
        var pendingAccCircle = null;
        var rainCircle = null;
        var floodCircle = null;
        var weatherCircle = null;
        var accCircle = null;
        /** One layer at a time: farm | weather | rainfall | flood */
        var activeLayer = 'farm';
        var lastContext = null;
        var layerAdvisoryTimer = null;

        var emptyOverlayEl = document.getElementById('farm-map-empty-overlay');
        var mapControlsWired = false;
        var fullscreenResizeWired = false;
        var mapResizeGuardsSetup = false;

        function recenterMap() {
            if (!map) {
                return;
            }
            var c = lastContext;
            if (c && c.map_ready && c.latitude != null && c.longitude != null) {
                map.flyTo([Number(c.latitude), Number(c.longitude)], Math.max(map.getZoom(), 16), { duration: 0.45 });
            } else {
                map.setView(DEFAULT_CENTER, EMPTY_ZOOM);
            }
        }

        function wireMapControls() {
            if (mapControlsWired) {
                return;
            }
            var zi = document.getElementById('farm-map-zoom-in');
            var zo = document.getElementById('farm-map-zoom-out');
            var rc = document.getElementById('farm-map-recenter');
            var rg = document.getElementById('farm-map-gps-recenter');
            var fs = document.getElementById('farm-map-fullscreen');
            if (!zi || !zo || !rc) {
                return;
            }
            mapControlsWired = true;
            zi.addEventListener('click', function () {
                if (map) {
                    map.zoomIn();
                }
            });
            zo.addEventListener('click', function () {
                if (map) {
                    map.zoomOut();
                }
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

        function updateHero(ctx) {
            var farmEl = document.getElementById('farm-map-hero-farm');
            if (farmEl && ctx && ctx.farm_name) {
                farmEl.textContent = ctx.farm_name;
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

        function clearPendingGpsLayers() {
            if (pendingGpsMarker && map) {
                map.removeLayer(pendingGpsMarker);
            }
            if (pendingAccCircle && map) {
                map.removeLayer(pendingAccCircle);
            }
            pendingGpsMarker = null;
            pendingAccCircle = null;
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
            if (btnUse) {
                btnUse.disabled = isLoading;
            }
            if (btnRefresh) {
                btnRefresh.disabled = isLoading;
            }
        }

        function initMap() {
            if (!mapEl) {
                return;
            }
            measureMapCanvasHeightImmediate();
            if (!map) {
                map = L.map(mapEl, { zoomControl: false, attributionControl: true });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',
                }).addTo(map);
                map.setView(DEFAULT_CENTER, EMPTY_ZOOM);
                setupMapResizeGuards();
                scheduleMapResize();
                if (!fullscreenResizeWired) {
                    fullscreenResizeWired = true;
                    function onFsChange() {
                        measureMapCanvasHeightImmediate();
                        scheduleMapResize();
                        if (!document.fullscreenElement) {
                            refreshMapLayoutAfterFullscreenExit();
                            window.setTimeout(function () {
                                window.dispatchEvent(new Event('resize'));
                            }, 0);
                        }
                    }
                    document.addEventListener('fullscreenchange', onFsChange);
                    document.addEventListener('webkitfullscreenchange', onFsChange);
                }
            }
            wireMapControls();
        }

        function clearMapLayers() {
            clearPendingGpsLayers();
            if (marker && map) {
                map.removeLayer(marker);
            }
            if (rainCircle && map) {
                map.removeLayer(rainCircle);
            }
            if (floodCircle && map) {
                map.removeLayer(floodCircle);
            }
            if (weatherCircle && map) {
                map.removeLayer(weatherCircle);
            }
            if (accCircle && map) {
                map.removeLayer(accCircle);
            }
            marker = null;
            rainCircle = null;
            floodCircle = null;
            weatherCircle = null;
            accCircle = null;
        }

        function removeOverlayFromMap(layer) {
            if (layer && map && map.hasLayer(layer)) {
                map.removeLayer(layer);
            }
        }

        function ringStyleForRainfall(intensityLabel) {
            var s = String(intensityLabel || '').toLowerCase();
            if (s.indexOf('high') !== -1) {
                return { fillColor: '#1d4ed8', fillOpacity: 0.34, color: '#1e3a8a', weight: 2 };
            }
            if (s.indexOf('moder') !== -1) {
                return { fillColor: '#3b82f6', fillOpacity: 0.3, color: '#1d4ed8', weight: 2 };
            }
            if (s.indexOf('light') !== -1) {
                return { fillColor: '#93c5fd', fillOpacity: 0.26, color: '#3b82f6', weight: 2 };
            }
            return { fillColor: '#cbd5e1', fillOpacity: 0.18, color: '#64748b', weight: 1, dashArray: '6 5' };
        }

        function ringStyleForFlood(level) {
            if (level === 'CRITICAL') {
                return { fillColor: '#7f1d1d', fillOpacity: 0.34, color: '#450a0a', weight: 2 };
            }
            if (level === 'HIGH') {
                return { fillColor: '#ef4444', fillOpacity: 0.32, color: '#991b1b', weight: 2 };
            }
            if (level === 'MODERATE') {
                return { fillColor: '#f97316', fillOpacity: 0.34, color: '#c2410c', weight: 2 };
            }
            return { fillColor: '#22c55e', fillOpacity: 0.22, color: '#15803d', weight: 2 };
        }

        function updateLayerLegend() {
            var el = document.getElementById('farm-map-layer-legend');
            if (!el) {
                return;
            }
            if (!lastContext || !lastContext.map_ready) {
                el.textContent = '';
                return;
            }
            var rf = (lastContext.rainfall_context || {}).intensity_label || '—';
            var fl = floodLevelShort((lastContext.flood_risk || {}).level || 'LOW');
            var wx = lastContext.weather || {};
            var wxLine =
                wx.current_temperature != null
                    ? Math.round(Number(wx.current_temperature)) + '°C' + (wx.condition ? ' · ' + wx.condition : '')
                    : 'Forecast at your pin';
            var lines = {
                farm:
                    'Farm: your saved GPS pin. Shaded ring ≈ area used for map context (not a land survey).',
                weather:
                    'Weather: soft blue zone = forecast context around your farm. Card shows ' + wxLine + '.',
                rainfall:
                    'Rainfall: blue zone = forecast rainfall intensity at your pin — ' +
                    rf +
                    ' (not radar imagery).',
                flood:
                    'Flood: colored zone = advisory risk (' +
                    fl +
                    ') from weather — not official flood hazard mapping.',
            };
            el.textContent = lines[activeLayer] || '';
        }

        function applyLayerVisibility() {
            if (!map) {
                return;
            }
            removeOverlayFromMap(accCircle);
            removeOverlayFromMap(weatherCircle);
            removeOverlayFromMap(rainCircle);
            removeOverlayFromMap(floodCircle);

            if (marker) {
                if (!map.hasLayer(marker)) {
                    map.addLayer(marker);
                }
            }

            if (!lastContext || !lastContext.map_ready) {
                if (weatherFloat) {
                    weatherFloat.classList.add('hidden');
                    weatherFloat.setAttribute('aria-hidden', 'true');
                }
                updateLayerLegend();
                return;
            }

            switch (activeLayer) {
                case 'farm':
                    if (accCircle) {
                        map.addLayer(accCircle);
                    }
                    break;
                case 'weather':
                    if (weatherCircle) {
                        map.addLayer(weatherCircle);
                    }
                    break;
                case 'rainfall':
                    if (rainCircle) {
                        map.addLayer(rainCircle);
                    }
                    break;
                case 'flood':
                    if (floodCircle) {
                        map.addLayer(floodCircle);
                    }
                    break;
                default:
                    if (accCircle) {
                        map.addLayer(accCircle);
                    }
            }

            if (marker && typeof marker.bringToFront === 'function') {
                marker.bringToFront();
            }

            if (weatherFloat) {
                if (activeLayer === 'weather') {
                    weatherFloat.classList.remove('hidden');
                    weatherFloat.setAttribute('aria-hidden', 'false');
                } else {
                    weatherFloat.classList.add('hidden');
                    weatherFloat.setAttribute('aria-hidden', 'true');
                }
            }
            updateLayerLegend();
        }

        function fmtCoordExact(v) {
            if (v == null || v === '') {
                return '—';
            }
            return Number(v).toFixed(6);
        }

        function buildPopupHtml(ctx) {
            var w = ctx.weather || {};
            var rf = (ctx.rainfall_context || {}).intensity_label || '—';
            var fl = (ctx.flood_risk || {}).label || '—';
            var crop = ctx.crop_type ? String(ctx.crop_type) : 'Not set';
            var wxRaw =
                (w.current_temperature != null ? String(w.current_temperature) + '°C' : '—') +
                (w.condition ? ' · ' + String(w.condition) : '');
            return (
                '<div class="farm-map-popup">' +
                '<p class="farm-map-popup__eyebrow">Farm location</p>' +
                '<p class="farm-map-popup__title">' +
                escapeHtml(ctx.farm_name || 'Your farm') +
                '</p>' +
                '<p class="farm-map-popup__coords">' +
                fmtCoordExact(ctx.latitude) +
                ', ' +
                fmtCoordExact(ctx.longitude) +
                '</p>' +
                '<p class="farm-map-popup__meta"><span>Crop:</span> ' +
                escapeHtml(crop) +
                '</p>' +
                '<p class="farm-map-popup__meta"><span>Flood risk:</span> ' +
                escapeHtml(fl) +
                '</p>' +
                '<p class="farm-map-popup__wx">' +
                escapeHtml(wxRaw) +
                '</p>' +
                '<p class="farm-map-popup__meta"><span>Rainfall:</span> ' +
                escapeHtml(rf) +
                '</p>' +
                '</div>'
            );
        }

        var tooltipDefaults = {
            permanent: true,
            sticky: true,
            direction: 'top',
            offset: [0, -10],
            className: 'farm-map-layer-tooltip',
            opacity: 0.97,
        };

        function escapeHtml(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        /**
         * Leaflet measures the container at init. The map script loads asynchronously, fonts/icons
         * can reflow the page, and mobile viewports change — all of which require invalidateSize().
         */
        function scheduleMapResize() {
            if (!map) {
                return;
            }
            function inv() {
                if (map) {
                    map.invalidateSize({ animate: false });
                }
            }
            requestAnimationFrame(function () {
                inv();
                requestAnimationFrame(inv);
            });
            [0, 50, 150, 400, 800].forEach(function (ms) {
                window.setTimeout(inv, ms);
            });
        }

        /**
         * Fullscreen exit often fires before the document has finished reflowing; Leaflet can read a
         * stale clientWidth/height once and skip updates. Re-measure with pan:false to avoid drift.
         */
        function refreshMapLayoutAfterFullscreenExit() {
            if (!map) {
                return;
            }
            function inv() {
                if (map) {
                    map.invalidateSize({ animate: false, pan: false });
                }
            }
            inv();
            requestAnimationFrame(function () {
                inv();
                requestAnimationFrame(function () {
                    inv();
                });
            });
            [16, 50, 100, 200, 400, 600, 1000].forEach(function (ms) {
                window.setTimeout(inv, ms);
            });
        }

        /**
         * Map height: fill space below the map tile; enforce a minimum share of the visual viewport.
         * Debounced remeasure + hysteresis avoid feedback loops (ResizeObserver / row reflow / Leaflet).
         */
        var mapCanvasHeightDebounce = null;
        var MAP_CANVAS_DEBOUNCE_MS = 120;

        function scheduleMapCanvasHeight() {
            if (mapCanvasHeightDebounce != null) {
                clearTimeout(mapCanvasHeightDebounce);
            }
            mapCanvasHeightDebounce = window.setTimeout(function () {
                mapCanvasHeightDebounce = null;
                measureMapCanvasHeightImmediate();
            }, MAP_CANVAS_DEBOUNCE_MS);
        }

        function measureMapCanvasHeightImmediate() {
            var el = document.querySelector('.farm-map-main-canvas');
            if (!el) {
                return;
            }
            var pageRoot = document.getElementById('farm-map-page');
            var autoHeightOff =
                el.getAttribute('data-farm-map-auto-height') === 'false' ||
                (pageRoot && pageRoot.getAttribute('data-farm-map-auto-height') === 'false');

            if (document.fullscreenElement && document.fullscreenElement.contains(el)) {
                el.style.height = '';
                if (map) {
                    scheduleMapResize();
                }
                return;
            }

            /* Let CSS fully control height/min-height (Leaflet still needs a definite box — set height or min-height in CSS). */
            if (autoHeightOff) {
                el.style.height = '';
                if (map) {
                    scheduleMapResize();
                }
                return;
            }

            var cs = getComputedStyle(el);
            var cssMinH = parseFloat(cs.minHeight);
            if (isNaN(cssMinH) || cssMinH < 0) {
                cssMinH = 0;
            }

            var vv = window.visualViewport ? window.visualViewport.height : window.innerHeight;
            var top = el.getBoundingClientRect().top;
            var gap = 8;
            var remaining = Math.floor(vv - top - gap);
            /* At least ~62% of viewport height, floor 560px — keeps the map tall even with UI above */
            var minShare = Math.max(560, Math.floor(vv * 0.62));
            /* Respect stylesheet min-height / --farm-map-canvas-min-height (inline height would otherwise override CSS height). */
            var minH = Math.max(720, minShare, cssMinH);
            var h = Math.max(minH, remaining);
            var maxH = Math.floor(vv * 0.98);
            if (h > maxH) {
                h = maxH;
            }
            if (cssMinH > 0 && h < cssMinH && cssMinH <= maxH) {
                h = cssMinH;
            }

            var prev = parseFloat(String(el.style.height || '').replace(/px$/i, ''), 10);
            if (!isNaN(prev) && Math.abs(h - prev) < 8) {
                if (map) {
                    scheduleMapResize();
                }
                return;
            }

            el.style.height = h + 'px';
            if (map) {
                scheduleMapResize();
            }
        }

        var advisoryResizeObs = null;
        function setupAdvisoryResizeObserver() {
            if (advisoryResizeObs || typeof ResizeObserver === 'undefined') {
                return;
            }
            var adv = document.getElementById('farm-map-smart-advisory');
            if (!adv) {
                return;
            }
            /* Only the advisory card — do NOT observe the row that contains the map (resize loop). */
            advisoryResizeObs = new ResizeObserver(function () {
                scheduleMapCanvasHeight();
            });
            advisoryResizeObs.observe(adv);
        }

        function setupMapResizeGuards() {
            if (mapResizeGuardsSetup || !map || !mapEl) {
                return;
            }
            mapResizeGuardsSetup = true;

            function onViewportChange() {
                scheduleMapCanvasHeight();
            }

            measureMapCanvasHeightImmediate();
            setupAdvisoryResizeObserver();
            if (document.readyState !== 'complete') {
                window.addEventListener('load', measureMapCanvasHeightImmediate, { once: true, passive: true });
            }
            window.addEventListener('resize', onViewportChange, { passive: true });
            window.addEventListener('orientationchange', onViewportChange, { passive: true });
            window.addEventListener(
                'pageshow',
                function (ev) {
                    if (ev.persisted) {
                        measureMapCanvasHeightImmediate();
                    }
                },
                { passive: true }
            );
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', onViewportChange, { passive: true });
            }
            if (typeof ResizeObserver !== 'undefined') {
                var roFrame = null;
                var ro = new ResizeObserver(function () {
                    if (!map) {
                        return;
                    }
                    if (roFrame != null) {
                        cancelAnimationFrame(roFrame);
                    }
                    roFrame = requestAnimationFrame(function () {
                        roFrame = null;
                        if (map) {
                            map.invalidateSize({ animate: false });
                        }
                    });
                });
                ro.observe(mapEl);
                var inner = mapEl.closest('.farm-map-stack__map-inner');
                if (inner) {
                    ro.observe(inner);
                }
                var mapFrame = document.querySelector('.farm-map-stack__frame');
                if (mapFrame) {
                    ro.observe(mapFrame);
                }
            }
        }

        /**
         * Shows the browser’s latest GPS fix as a pin immediately (before save completes).
         */
        function showLiveGpsPin(pos) {
            initMap();
            clearMapLayers();
            setEmptyOverlayVisible(false);

            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            var ll = L.latLng(lat, lng);
            var acc = typeof pos.coords.accuracy === 'number' ? pos.coords.accuracy : null;
            lastAccuracyM = acc;

            if (acc != null && acc > 4 && acc < 800) {
                pendingAccCircle = L.circle(ll, {
                    radius: acc,
                    color: '#475569',
                    weight: 1,
                    dashArray: '5 6',
                    fillColor: '#64748b',
                    fillOpacity: 0.06,
                }).addTo(map);
            }

            pendingGpsMarker = L.marker([lat, lng], {
                icon: getGpsPinIcon(),
                zIndexOffset: 5000,
                riseOnHover: true,
                keyboard: true,
                title: 'Your device GPS position',
            }).addTo(map);

            pendingGpsMarker.bindPopup(
                '<div class="text-xs space-y-1" style="min-width:200px">' +
                    '<p class="farm-map-popup-gps-label">Live GPS</p>' +
                    '<p class="text-slate-700 font-semibold">Saving…</p>' +
                    '<p><span class="text-slate-500">Lat, lng:</span> ' +
                    fmtCoordExact(lat) +
                    ', ' +
                    fmtCoordExact(lng) +
                    '</p>' +
                    (acc != null ? '<p class="text-slate-500">~' + Math.round(acc) + ' m</p>' : '') +
                    '</div>',
                { maxWidth: 280 }
            );
            pendingGpsMarker.openPopup();

            map.setView(ll, 17);
            wireMapControls();
            scheduleMapResize();
        }

        function drawFarmMap(ctx) {
            initMap();
            clearMapLayers();
            if (!map || !ctx.map_ready || ctx.latitude == null || ctx.longitude == null) {
                map.setView(DEFAULT_CENTER, EMPTY_ZOOM);
                setEmptyOverlayVisible(true);
                scheduleMapResize();
                updateLayerLegend();
                return;
            }

            setEmptyOverlayVisible(false);

            var lat = Number(ctx.latitude);
            var lng = Number(ctx.longitude);
            var ll = L.latLng(lat, lng);

            var ov = ctx.overlays || {};
            var r = ov.rainfall || {};
            var f = ov.flood || {};
            var rfLabel = (ctx.rainfall_context || {}).intensity_label || '—';
            var flvl = (ctx.flood_risk || {}).level || 'LOW';

            accCircle = L.circle(ll, {
                radius: 95,
                color: '#64748b',
                weight: 1,
                dashArray: '5 6',
                fillColor: '#94a3b8',
                fillOpacity: 0.1,
            });
            accCircle.bindTooltip('Farm — saved pin & ring', tooltipDefaults);

            var rainSt = ringStyleForRainfall(rfLabel);
            var rainOpts = {
                radius: r.radius_m || 420,
                color: rainSt.color,
                weight: rainSt.weight,
                fillColor: rainSt.fillColor,
                fillOpacity: rainSt.fillOpacity,
            };
            if (rainSt.dashArray) {
                rainOpts.dashArray = rainSt.dashArray;
            }
            rainCircle = L.circle(ll, rainOpts);
            rainCircle.bindTooltip('Rainfall — ' + rfLabel + ' · forecast', tooltipDefaults);

            var floodSt = ringStyleForFlood(flvl);
            floodCircle = L.circle(ll, {
                radius: f.radius_m || 380,
                color: floodSt.color,
                weight: floodSt.weight,
                fillColor: floodSt.fillColor,
                fillOpacity: floodSt.fillOpacity,
            });
            floodCircle.bindTooltip(
                'Flood — ' + floodLevelShort(flvl) + ' risk (advisory)',
                tooltipDefaults
            );

            weatherCircle = L.circle(ll, {
                radius: 460,
                color: '#93c5fd',
                weight: 1,
                dashArray: '2 6',
                fillColor: '#3b82f6',
                fillOpacity: 0.08,
            });
            weatherCircle.bindTooltip('Weather — forecast zone', tooltipDefaults);

            marker = L.marker([lat, lng], {
                icon: getGpsPinIcon(),
                zIndexOffset: 5000,
                riseOnHover: true,
                keyboard: true,
                title: 'Your farm — tap for details',
            }).addTo(map);
            marker.bindPopup(buildPopupHtml(ctx), { maxWidth: 280 });

            map.fitBounds(L.latLngBounds(ll, ll).pad(0.35));
            map.setView(ll, Math.max(map.getZoom(), 16));

            var w = ctx.weather || {};
            if (weatherFloatText) {
                var t = w.current_temperature != null ? Math.round(Number(w.current_temperature)) + '°C' : '—';
                var c = w.condition ? String(w.condition) : '';
                weatherFloatText.textContent = (t !== '—' ? t + ' · ' : '') + c;
            }

            applyLayerVisibility();
            scheduleMapResize();
        }

        function floodChipTone(level) {
            if (level === 'HIGH') {
                return 'bad';
            }
            if (level === 'MODERATE') {
                return 'warn';
            }
            if (level === 'LOW') {
                return 'ok';
            }
            return 'muted';
        }

        function setStatusChip(wrapEl, tone) {
            if (!wrapEl) {
                return;
            }
            wrapEl.className = 'farm-map-status-chip farm-map-status-chip--' + tone;
        }

        function renderControlStripStatus(ctx) {
            if (!statusGpsEl || !statusFloodEl) {
                return;
            }
            if (!ctx || !ctx.map_ready) {
                setStatusChip(statusGpsWrap, 'muted');
                statusGpsEl.textContent = 'Not connected';
                setStatusChip(statusFloodWrap, 'muted');
                statusFloodEl.textContent = '—';
                return;
            }

            setStatusChip(statusGpsWrap, 'ok');
            statusGpsEl.textContent = 'Connected';

            var flvl = (ctx.flood_risk || {}).level || 'LOW';
            setStatusChip(statusFloodWrap, floodChipTone(flvl));
            statusFloodEl.textContent = floodLevelShort(flvl);
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

        function updateGpsPanel(ctx) {
            if (!gpsLastEl) {
                return;
            }
            if (ctx.map_ready && ctx.gps_captured_at) {
                gpsLastEl.textContent = 'Last updated: ' + formatGpsTime(ctx.gps_captured_at);
            } else if (ctx.map_ready) {
                gpsLastEl.textContent = 'Last updated: —';
            } else {
                gpsLastEl.textContent = 'Last updated —';
            }
        }

        function floodLevelShort(level) {
            if (level === 'CRITICAL') {
                return 'Critical';
            }
            if (level === 'HIGH') {
                return 'High';
            }
            if (level === 'MODERATE') {
                return 'Moderate';
            }
            return 'Low';
        }

        function rainPhraseFromCtx(ctx) {
            var rf = (ctx.rainfall_context || {}).intensity_label || '';
            var rl = String(rf).toLowerCase();
            if (rl.indexOf('high') !== -1) {
                return 'heavy rain';
            }
            if (rl.indexOf('moder') !== -1) {
                return 'moderate rain';
            }
            if (rl.indexOf('light') !== -1) {
                return 'light rain';
            }
            return 'limited rain in the forecast';
        }

        function renderTodaySummary(ctx) {
            if (!todaySummaryEl) {
                return;
            }
            if (!ctx.map_ready) {
                todaySummaryEl.textContent =
                    'Save GPS to see today’s snapshot: flood risk, rainfall, and weather at your pin.';
                return;
            }
            var flvl = (ctx.flood_risk || {}).level || 'LOW';
            var floodAdj = floodLevelShort(flvl);
            var rain = rainPhraseFromCtx(ctx);
            var snapshot = ctx.risk_snapshot || {};
            var snapshotEffect = String(snapshot.three_day_effect || '').trim();
            var tail = 'Normal farm activity is okay.';
            if (flvl === 'HIGH') {
                tail = 'Limit work in low spots and check drainage.';
            } else if (flvl === 'MODERATE') {
                tail = 'Watch fields if rain increases.';
            }
            if (snapshotEffect !== '' && snapshotEffect.toLowerCase() !== 'no forecast impact available') {
                tail = snapshotEffect + '. ' + tail;
            }
            todaySummaryEl.textContent =
                'Today: ' + floodAdj + ' flood risk and ' + rain + '. ' + tail;
        }

        function renderSummary(ctx) {
            if (!summaryEl) {
                return;
            }
            if (!ctx.map_ready) {
                summaryEl.innerHTML =
                    '<div class="farm-map-snap-card farm-map-snap-card--empty farm-map-snap-card--loc">' +
                    '<p class="farm-map-snap-card__label">Location</p>' +
                    '<p class="farm-map-snap-card__value">No GPS yet</p>' +
                    '<p class="farm-map-snap-card__sub">Use GPS to load your farm pin.</p>' +
                    '</div>' +
                    '<div class="farm-map-snap-card farm-map-snap-card--wx">' +
                    '<p class="farm-map-snap-card__label">Weather</p>' +
                    '<p class="farm-map-snap-card__value">—</p>' +
                    '<p class="farm-map-snap-card__sub">After GPS is saved.</p>' +
                    '</div>' +
                    '<div class="farm-map-snap-card farm-map-snap-card--rain">' +
                    '<p class="farm-map-snap-card__label">Rainfall</p>' +
                    '<p class="farm-map-snap-card__value">—</p>' +
                    '<p class="farm-map-snap-card__sub">Forecast at your pin.</p>' +
                    '</div>' +
                    '<div class="farm-map-snap-card farm-map-snap-card--flood">' +
                    '<p class="farm-map-snap-card__label">Flood risk</p>' +
                    '<p class="farm-map-snap-card__value">—</p>' +
                    '<p class="farm-map-snap-card__sub">Advisory from weather data.</p>' +
                    '</div>';
                return;
            }

            var w = ctx.weather || {};
            var addr = escapeHtml(ctx.location_display || '') || '—';

            var wxVal = '—';
            var wxSub = '—';
            var weatherUnavailable = w.current_temperature == null && !w.condition;
            if (!weatherUnavailable) {
                wxVal =
                    w.current_temperature != null ? String(Math.round(Number(w.current_temperature))) + '°C' : '—';
                wxSub = escapeHtml(w.condition || '');
                if (w.wind_speed != null) {
                    var ws = Number(w.wind_speed);
                    if (ws >= 30) {
                        wxSub += wxSub ? ' · Breezy' : 'Breezy';
                    } else if (ws >= 15) {
                        wxSub += wxSub ? ' · Light wind' : 'Light wind';
                    }
                }
            } else {
                wxSub = 'Unavailable';
            }

            var rainSub = 'Forecast trend near your farm';

            var fr = ctx.flood_risk || {};
            var flvl = fr.level || 'LOW';
            var snapshot = ctx.risk_snapshot || {};
            var snapshotFlood = String(snapshot.flood_risk_level || '').trim();
            var snapshotCropLoss = String(snapshot.estimated_crop_loss || 'N/A').trim() || 'N/A';
            var snapshotEffect = String(snapshot.three_day_effect || 'No forecast impact available').trim() || 'No forecast impact available';
            var floodVal = escapeHtml(snapshotFlood !== '' ? snapshotFlood : floodLevelShort(flvl));
            var floodLbl = fr.label != null && String(fr.label).trim() !== '' ? String(fr.label) : '';
            var floodSub =
                floodLbl !== ''
                    ? escapeHtml(floodLbl)
                    : fr.message != null && String(fr.message).trim() !== ''
                      ? escapeHtml(String(fr.message))
                      : 'Based on local advisory data';

            summaryEl.innerHTML =
                '<div class="farm-map-snap-card farm-map-snap-card--loc">' +
                '<p class="farm-map-snap-card__label">Location</p>' +
                '<p class="farm-map-snap-card__value">' +
                addr +
                '</p>' +
                '<p class="farm-map-snap-card__sub">Saved farm location</p>' +
                '</div>' +
                '<div class="farm-map-snap-card farm-map-snap-card--wx">' +
                '<p class="farm-map-snap-card__label">Weather</p>' +
                '<p class="farm-map-snap-card__value">' +
                escapeHtml(wxVal) +
                '</p>' +
                '<p class="farm-map-snap-card__sub">' +
                wxSub +
                '</p>' +
                '</div>' +
                '<div class="farm-map-snap-card farm-map-snap-card--rain">' +
                '<p class="farm-map-snap-card__label">Estimated Crop Loss</p>' +
                '<p class="farm-map-snap-card__value">' +
                escapeHtml(snapshotCropLoss) +
                '</p>' +
                '<p class="farm-map-snap-card__sub">' +
                'Potential crop damage from current weather risk' +
                '</p>' +
                '</div>' +
                '<div class="farm-map-snap-card farm-map-snap-card--flood">' +
                '<p class="farm-map-snap-card__label">Flood Risk Level</p>' +
                '<p class="farm-map-snap-card__value">' +
                floodVal +
                '</p>' +
                '<p class="farm-map-snap-card__sub">' +
                floodSub +
                '</p>' +
                '</div>' +
                '<div class="farm-map-snap-card farm-map-snap-card--rain">' +
                '<p class="farm-map-snap-card__label">3-Day Effect</p>' +
                '<p class="farm-map-snap-card__value">' +
                escapeHtml(snapshotEffect) +
                '</p>' +
                '<p class="farm-map-snap-card__sub">' +
                rainSub +
                '</p>' +
                '</div>';
        }

        function setAdvisoryLoading(busy) {
            var statusLine = document.getElementById('farm-map-advisory-status-line');
            var inner = document.getElementById('farm-map-advisory-inner');
            if (!statusLine || !inner) {
                return;
            }
            if (!busy) {
                return;
            }
            if (!lastContext || !lastContext.map_ready) {
                return;
            }
            statusLine.innerHTML = '<span class="text-slate-600">Updating advisory…</span>';
            inner.innerHTML = '<p class="text-sm text-slate-500" role="status">Loading advisory…</p>';
            lucideRefresh();
        }

        function scheduleAdvisoryRefetch() {
            if (!lastContext || !lastContext.map_ready) {
                return;
            }
            if (layerAdvisoryTimer != null) {
                clearTimeout(layerAdvisoryTimer);
            }
            setAdvisoryLoading(true);
            layerAdvisoryTimer = setTimeout(function () {
                layerAdvisoryTimer = null;
                fetchContext({ silent: true })
                    .then(function (ctx) {
                        if (ctx) {
                            applyContext(ctx);
                        } else if (lastContext) {
                            renderSmartAdvisory(lastContext);
                        }
                    });
            }, 380);
        }

        function mapClaySrc(key) {
            var c =
                typeof window !== 'undefined' && window.AGRI_MAP_ADV_CLAY ? window.AGRI_MAP_ADV_CLAY : {};
            return c[key] || '';
        }

        function mapClayImgHtml(key, cls, w, h) {
            var src = mapClaySrc(key);
            if (!src) {
                return '';
            }
            return (
                '<img src="' +
                escapeHtml(src) +
                '" alt="" class="' +
                cls +
                '" width="' +
                String(w) +
                '" height="' +
                String(h) +
                '" decoding="async">'
            );
        }

        function dashRiskBadgeClass(level) {
            var l = String(level || 'low').toLowerCase();
            if (l === 'high') {
                return 'dash-smart__badge dash-smart__badge--risk-high';
            }
            if (l === 'moderate') {
                return 'dash-smart__badge dash-smart__badge--risk-mid';
            }
            return 'dash-smart__badge dash-smart__badge--risk-low';
        }

        function riskBadgeLabel(level) {
            var l = String(level || 'low').toLowerCase();
            if (l === 'high') {
                return 'High';
            }
            if (l === 'moderate') {
                return 'Moderate';
            }
            return 'Low';
        }

        function renderFmAdvListHtml(items, emptyLine) {
            emptyLine = emptyLine || 'AI advisory temporarily unavailable.';
            if (!items || !items.length) {
                return '<ul class="cp-advice-list"><li>' + escapeHtml(emptyLine) + '</li></ul>';
            }
            var lis = items
                .map(function (line) {
                    return '<li>' + escapeHtml(String(line)) + '</li>';
                })
                .join('');
            return '<ul class="cp-advice-list">' + lis + '</ul>';
        }

        function lucideRefresh() {
            if (typeof window.lucide !== 'undefined') {
                window.lucide.createIcons();
            }
        }

        function renderSmartAdvisory(ctx) {
            var inner = document.getElementById('farm-map-advisory-inner');
            var statusLine = document.getElementById('farm-map-advisory-status-line');
            if (!inner || !statusLine) {
                return;
            }

            if (!ctx.map_ready || !ctx.map_smart_advisory) {
                statusLine.innerHTML =
                    '<span class="text-slate-600">AI Smart Advisory: Waiting for GPS</span>';
                inner.innerHTML =
                    '<div class="dash-smart__notice" role="status">' +
                    '<i data-lucide="map-pin" class="dash-smart__notice-icon" aria-hidden="true"></i>' +
                    '<p>Save your farm GPS pin to unlock map overlays, local weather, and this advisory for your field.</p>' +
                    '</div>';
                lucideRefresh();
                return;
            }

            var a = ctx.map_smart_advisory;
            var isActive = a.status === 'active';
            statusLine.innerHTML = isActive
                ? '<span class="text-emerald-700">AI Smart Advisory: Active</span>'
                : '<span class="text-rose-700">AI Smart Advisory: Unavailable</span>';

            var risk = a.risk_level || 'low';
            var riskBadgeHtml = isActive
                ? '<div class="cp-smart-badges">' +
                  '<span class="' +
                  dashRiskBadgeClass(risk) +
                  '">' +
                  escapeHtml(riskBadgeLabel(risk)) +
                  ' risk' +
                  '</span>' +
                  '</div>'
                : '';
            var action = escapeHtml(String(isActive ? a.smart_action || '' : 'AI advisory temporarily unavailable.'));
            var summary = escapeHtml(String(isActive ? a.advice_summary || '' : 'AI advisory temporarily unavailable.'));
            var why = escapeHtml(String(isActive ? a.why_this_matters || a.why_this_tip || '' : 'AI advisory temporarily unavailable.'));

            var doList = isActive && a.what_to_do ? a.what_to_do : [];
            if (!doList || !doList.length) {
                doList = [];
            }
            var watchList = isActive && a.what_to_watch ? a.what_to_watch : [];
            if (!watchList || !watchList.length) {
                watchList = [];
            }
            var avoidSrc = isActive ? a.what_to_avoid || a.avoid || [] : [];
            var avoidList = avoidSrc.slice(0, 3);
            doList = doList.slice(0, 4);
            watchList = watchList.slice(0, 4);

            inner.innerHTML =
                '<header class="cp-smart-head cp-smart-hero__head">' +
                '<div class="cp-smart-head__left">' +
                '<h2 class="cp-smart-title">' +
                mapClayImgHtml('brain', 'weather-clay-ic weather-clay-ic--title', 18, 18) +
                ' AI Smart Advisory' +
                '</h2>' +
                '<p class="cp-smart-sub">Tailored to your farm location and current map conditions</p>' +
                '</div>' +
                riskBadgeHtml +
                '</header>' +
                '<div class="cp-smart-action-callout">' +
                '<span class="cp-smart-action-callout__chip" aria-hidden="true">🔥 Smart action</span>' +
                '<p class="cp-smart-action-callout__text">' +
                action +
                '</p>' +
                '</div>' +
                '<div class="cp-smart-summary cp-smart-hero__summary">' +
                '<div class="cp-smart-summary__head">' +
                mapClayImgHtml('bulb', 'weather-clay-ic weather-clay-ic--plan', 22, 22) +
                '<h3 class="cp-smart-block-title">Advice summary</h3>' +
                '</div>' +
                '<p class="cp-smart-summary__text">' +
                summary +
                '</p>' +
                '</div>' +
                '<div class="cp-smart-grid">' +
                '<article class="cp-smart-block cp-smart-block--do">' +
                '<div class="cp-smart-block__head">' +
                mapClayImgHtml('sprout', 'weather-clay-ic weather-clay-ic--inline', 18, 18) +
                '<h3 class="cp-smart-block-title">What to do</h3>' +
                '</div>' +
                renderFmAdvListHtml(doList, '') +
                '</article>' +
                '<article class="cp-smart-block cp-smart-block--watch">' +
                '<div class="cp-smart-block__head">' +
                mapClayImgHtml('eye', 'weather-clay-ic weather-clay-ic--inline', 18, 18) +
                '<h3 class="cp-smart-block-title">What to watch</h3>' +
                '</div>' +
                renderFmAdvListHtml(watchList, '') +
                '</article>' +
                '<article class="cp-smart-block cp-smart-block--avoid">' +
                '<div class="cp-smart-block__head">' +
                mapClayImgHtml('alert', 'weather-clay-ic weather-clay-ic--inline', 18, 18) +
                '<h3 class="cp-smart-block-title">What to avoid</h3>' +
                '</div>' +
                renderFmAdvListHtml(avoidList, '') +
                '</article>' +
                '<article class="cp-smart-block cp-smart-block--why">' +
                '<div class="cp-smart-block__head">' +
                mapClayImgHtml('bulb', 'weather-clay-ic weather-clay-ic--inline', 18, 18) +
                '<h3 class="cp-smart-block-title">Why this matters</h3>' +
                '</div>' +
                '<p class="cp-smart-why-text">' +
                why +
                '</p>' +
                '</article>' +
                '</div>';

            lucideRefresh();
        }

        function renderLayerToggles() {
            if (!layerEl) {
                return;
            }
            var defs = [
                { id: 'farm', icon: '📍', label: 'Farm' },
                { id: 'weather', icon: '☁', label: 'Weather' },
                { id: 'rainfall', icon: '🌧', label: 'Rainfall' },
                { id: 'flood', icon: '⚠', label: 'Flood' },
            ];
            layerEl.innerHTML = '';
            defs.forEach(function (d) {
                var b = document.createElement('button');
                b.type = 'button';
                var isOn = activeLayer === d.id;
                b.className = 'farm-map-layer-btn' + (isOn ? ' farm-map-layer-btn--on' : '');
                b.setAttribute('aria-pressed', isOn ? 'true' : 'false');
                b.dataset.layer = d.id;
                b.title =
                    d.id === 'farm'
                        ? 'Farm: pin and saved area'
                        : d.id === 'weather'
                          ? 'Weather: forecast zone and conditions'
                          : d.id === 'rainfall'
                            ? 'Rainfall: forecast intensity zone'
                            : 'Flood: advisory risk zone';
                b.innerHTML =
                    '<span class="farm-map-layer-btn__ic" aria-hidden="true">' +
                    d.icon +
                    '</span><span class="farm-map-layer-btn__lbl">' +
                    d.label +
                    '</span>';
                b.addEventListener('click', function () {
                    activeLayer = d.id;
                    renderLayerToggles();
                    applyLayerVisibility();
                    scheduleMapResize();
                    scheduleAdvisoryRefetch();
                });
                layerEl.appendChild(b);
            });
        }

        function applyContext(ctx) {
            lastContext = ctx;
            updateHero(ctx);
            renderControlStripStatus(ctx);
            updateGpsPanel(ctx);
            renderTodaySummary(ctx);
            renderSummary(ctx);
            renderLayerToggles();
            renderSmartAdvisory(ctx);
            drawFarmMap(ctx);

            if (noGpsHint) {
                if (ctx.map_ready) {
                    noGpsHint.classList.add('hidden');
                } else {
                    noGpsHint.classList.remove('hidden');
                }
            }

            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    measureMapCanvasHeightImmediate();
                });
            });
        }

        function fetchContext(opts) {
            opts = opts || {};
            var params = {};
            if (activeLayer) {
                params.map_layer = activeLayer;
            }
            return window.axios
                .get(contextUrl, {
                    params: params,
                    headers: { Accept: 'application/json' },
                })
                .then(function (res) {
                    return res.data;
                })
                .catch(function () {
                    if (!opts.silent) {
                        showErr('<strong>Could not load map data.</strong> Check your connection and try again.');
                    }
                    return null;
                });
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
                    if (ctx) {
                        applyContext(ctx);
                    } else if (lastContext) {
                        applyContext(lastContext);
                    } else if (gpsLastEl) {
                        gpsLastEl.textContent = 'Last updated —';
                    }
                })
                .finally(function () {
                    if (btnRefresh) {
                        btnRefresh.disabled = false;
                    }
                    scheduleMapResize();
                    lucideRefresh();
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
                    showLiveGpsPin(pos);
                    if (gpsLastEl) {
                        gpsLastEl.textContent = 'Saving…';
                    }
                    var lat = pos.coords.latitude;
                    var lng = pos.coords.longitude;
                    if (typeof pos.coords.accuracy === 'number') {
                        lastAccuracyM = pos.coords.accuracy;
                    }
                    return saveGps(lat, lng).then(function (saveRes) {
                        if (!saveRes) {
                            clearPendingGpsLayers();
                            return fetchContext();
                        }
                        return fetchContext();
                    });
                })
                .then(function (ctx) {
                    if (ctx) {
                        applyContext(ctx);
                    } else if (lastContext) {
                        applyContext(lastContext);
                    } else {
                        drawFarmMap({ map_ready: false });
                    }
                })
                .catch(function (err) {
                    showErr(geoErrorMessage(err));
                    if (gpsLastEl) {
                        gpsLastEl.textContent = 'Last updated —';
                    }
                    clearPendingGpsLayers();
                    if (lastContext && lastContext.map_ready) {
                        applyContext(lastContext);
                    } else if (map) {
                        drawFarmMap({ map_ready: false });
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

        var advRefresh = document.getElementById('farm-map-advisory-refresh');
        if (advRefresh) {
            advRefresh.addEventListener('click', function () {
                advRefresh.disabled = true;
                fetchContext()
                    .then(function (ctx) {
                        if (ctx) {
                            applyContext(ctx);
                        }
                    })
                    .finally(function () {
                        advRefresh.disabled = false;
                        if (typeof window.lucide !== 'undefined') {
                            window.lucide.createIcons();
                        }
                    });
            });
        }

        if (gpsLastEl) {
            gpsLastEl.textContent = 'Loading…';
        }
        fetchContext().then(function (ctx) {
            if (ctx) {
                applyContext(ctx);
            } else {
                if (gpsLastEl) {
                    gpsLastEl.textContent = 'Last updated —';
                }
                if (todaySummaryEl) {
                    todaySummaryEl.textContent = 'Could not load today’s summary.';
                }
                var advInner = document.getElementById('farm-map-advisory-inner');
                var advStatus = document.getElementById('farm-map-advisory-status-line');
                if (advInner) {
                    advInner.innerHTML =
                        '<div class="dash-smart__notice" role="status">' +
                        '<i data-lucide="wifi-off" class="dash-smart__notice-icon" aria-hidden="true"></i>' +
                        '<p>Advisory unavailable — check your connection and refresh.</p>' +
                        '</div>';
                    lucideRefresh();
                }
                if (advStatus) {
                    advStatus.innerHTML =
                        '<span class="text-rose-700">AI Smart Advisory: Unavailable</span>';
                }
                var ts = document.getElementById('farm-map-advisory-updated');
                if (ts) {
                    ts.textContent = '';
                }
            }
        });
    });
})();
