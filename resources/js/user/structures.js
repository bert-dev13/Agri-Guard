import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

(function () {
    'use strict';

    // Ensure Leaflet default marker icons resolve correctly with Vite builds.
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

    function escapeHtml(input) {
        const div = document.createElement('div');
        div.textContent = String(input ?? '');
        return div.innerHTML;
    }

    ready(function () {
        const root = document.getElementById('structures-page');
        if (!root) {
            return;
        }

        const mapEl = document.getElementById('structures-map');
        const locationEl = document.getElementById('structures-location');
        const conditionsEl = document.getElementById('structures-conditions');
        const statusEl = document.getElementById('structures-analysis-status');
        const classificationEl = document.getElementById('structures-classification');
        const summaryEl = document.getElementById('structures-summary');
        const cardsEl = document.getElementById('structures-recommendations');
        const analysisUrl = root.dataset.analysisUrl || '';
        const locationUrl = root.dataset.locationUrl || '';
        const geofenceUrl = root.dataset.geofenceUrl || '/amulung.json';
        const csrfToken = root.dataset.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const analyzeBtn = document.getElementById('structures-analyze-btn');
        const analyzeTextEl = document.getElementById('structures-analyze-text');
        const analyzeSpinnerEl = document.getElementById('structures-analyze-spinner');

        const map = L.map(mapEl, { zoomControl: true, attributionControl: true }).setView([17.65, 121.72], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);

        const FIXED_FLOOD_RISK_MAP = (function () {
            function canonical(name) {
                return String(name || '')
                    .toLowerCase()
                    .replace(/[^a-z0-9]/g, '');
            }
            const high = [
                'Abolo', 'Alit Untung', 'Annafatan', 'Anquiray', 'Babayuan', 'Bauan', 'Baccuit', 'Baculud',
                'Balauini', 'Calamagui', 'Casingsingan Norte', 'Casingsingan Sur', 'Centro', 'Dafunganay',
                'Dugayung', 'Estefania', 'Gabut', 'Jurisdiccion', 'Logung', 'Marobbob',
                'Pacac-Grande', 'Pacac-Pequeño', 'Palacu', 'Palayag',
                'Unag', 'Concepcion', 'Tana', 'Annabuculan', 'Agguirit', 'Aggurit', 'Goran', 'Monte Alegre',
            ];
            const moderate = [
                'Dadda', 'Cordova', 'Calintaan', 'Caratacat', 'Gangauan', 'Magogod', 'Manalo', 'Masical', 'Nangalasauan',
            ];
            const low = [
                'Nabbialan', 'San Juan', 'La Suerte', 'Bacring', 'Backring', 'Nagsabaran', 'Bayabat',
                'Nanuccauan', 'Catarauan', 'Cataruan',
            ];

            const out = {};
            high.forEach((name) => { out[canonical(name)] = 'high'; });
            moderate.forEach((name) => { out[canonical(name)] = 'moderate'; });
            low.forEach((name) => { out[canonical(name)] = 'low'; });
            return out;
        })();

        const RISK_STYLE = {
            high: { fill: '#ff0000', line: '#7f1d1d', label: 'High Risk', colorLabel: 'Red' },
            moderate: { fill: '#ffd700', line: '#854d0e', label: 'Moderate Risk', colorLabel: 'Yellow' },
            low: { fill: '#00aa00', line: '#14532d', label: 'Low Risk', colorLabel: 'Green' },
            unknown: { fill: '#94a3b8', line: '#334155', label: 'Unknown Risk', colorLabel: 'Unknown' },
        };

        function getFeatureName(feature) {
            const p = (feature && feature.properties) || {};
            return String(p.adm4_en || p.name || p.barangay || p.brgy_name || 'Unknown Barangay').trim();
        }

        function getRiskStyle(riskLevel) {
            return RISK_STYLE[riskLevel] || RISK_STYLE.unknown;
        }

        function riskLevelFromBarangayName(barangay) {
            const canonical = String(barangay || '')
                .toLowerCase()
                .replace(/[^a-z0-9]/g, '');
            return FIXED_FLOOD_RISK_MAP[canonical] || 'unknown';
        }

        function normalizeRiskLevelFromText(value) {
            const normalized = String(value || '').trim().toLowerCase();
            if (normalized === 'high' || normalized === 'high risk') {
                return 'high';
            }
            if (normalized === 'moderate' || normalized === 'moderate risk' || normalized === 'medium') {
                return 'moderate';
            }
            if (normalized === 'low' || normalized === 'low risk') {
                return 'low';
            }
            return 'unknown';
        }

        function formatFloodRiskDisplay(value) {
            const text = String(value || '').trim();
            if (text.includes('outside mapped boundary')) {
                return text;
            }
            const riskLevel = normalizeRiskLevelFromText(text);
            const style = getRiskStyle(riskLevel);
            return style.colorLabel === 'Unknown' ? style.label : `${style.label} (${style.colorLabel})`;
        }

        let pin = null;
        let currentSelection = null;
        let isAnalyzing = false;

        const SOIL_OPTIONS = ['Clay', 'Clay Loam', 'Sandy Loam', 'Silty Loam', 'Sandy', 'Rocky'];
        const TERRAIN_OPTIONS = ['Flat', 'Gently Sloping', 'Undulating', 'Steep'];
        const WIND_OPTIONS = ['Low', 'Moderate', 'High'];

        function windBandFromValue(value) {
            const raw = String(value ?? '').trim().toLowerCase();
            const numeric = Number(raw.replace('%', '').trim());
            if (Number.isFinite(numeric)) {
                if (numeric >= 67) return 'high';
                if (numeric >= 34) return 'moderate';
                return 'low';
            }
            if (raw.includes('high')) return 'high';
            if (raw.includes('moderate') || raw.includes('medium')) return 'moderate';
            return 'low';
        }

        function normalizeTitleCase(value) {
            const text = String(value || '').trim().toLowerCase();
            if (text === '') return '';
            return text
                .split(/\s+/)
                .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
                .join(' ');
        }

        function clearResultsWithPrompt() {
            classificationEl.innerHTML = '';
            summaryEl.innerHTML = '';
            cardsEl.innerHTML = '';
            statusEl.innerHTML = `
                <div class="structures-decision structures-decision--idle">
                    <p class="structures-decision__hint">Adjust site conditions and click Analyze Site to generate recommendations.</p>
                </div>
            `;
        }

        function setAnalyzeLoading(loading) {
            isAnalyzing = loading;
            if (!analyzeBtn || !analyzeTextEl || !analyzeSpinnerEl) {
                return;
            }
            analyzeBtn.disabled = loading || !currentSelection;
            analyzeSpinnerEl.classList.toggle('hidden', !loading);
            analyzeTextEl.textContent = loading ? 'Analyzing site conditions…' : 'Analyze Site';
        }

        function buildWhatToDo(item, req) {
            const parts = [
                req.elevation_level,
                req.drainage_needs,
                req.wind_resistance_measures,
                req.material_suggestions,
                req.foundation_type,
            ]
                .map((v) => String(v || '').trim())
                .filter((v, i, arr) => v !== '' && arr.indexOf(v) === i);

            if (parts.length > 0) {
                return parts.slice(0, 2).join(' ');
            }

            const fallback = String(item.what_to_do || '').trim();
            if (fallback !== '') return fallback;
            return 'Keep this structure in safer, well-drained areas and protect it from strong wind.';
        }

        function buildSiteBuildabilitySummary(siteConditions) {
            const soil = String(siteConditions?.soil_type || '').toLowerCase();
            const terrain = String(siteConditions?.terrain || '').toLowerCase();
            const flood = String(siteConditions?.flood_risk || '').toLowerCase();
            const windBand = windBandFromValue(siteConditions?.wind_exposure || '');

            const suitableTypes = [
                'Lightweight agricultural structures (elevated design required)',
                'Livestock-related structures with drainage considerations',
                'Storage or utility buildings with reinforced base',
            ];

            const engineeringRisks = [];
            if (flood.includes('high') || flood.includes('moderate')) {
                engineeringRisks.push('Flood exposure may affect ground-level foundations');
            }
            if (soil.includes('clay') || soil.includes('silty') || soil.includes('sandy')) {
                engineeringRisks.push('Soil conditions may require foundation reinforcement');
            }
            if (windBand === 'high' || windBand === 'moderate') {
                engineeringRisks.push('Wind exposure may require lateral bracing');
            }
            if (terrain.includes('steep') || terrain.includes('undulating') || terrain.includes('sloping')) {
                engineeringRisks.push('Terrain slope may increase runoff and instability risk');
            }
            if (engineeringRisks.length === 0) {
                engineeringRisks.push('Baseline site constraints still require structural verification');
            }

            const designImplications = [
                'Elevate structures above expected flood level',
                'Use reinforced framing for wind resistance',
                'Improve drainage using gravel base or perimeter canals',
            ];

            return {
                suitableTypes: suitableTypes.slice(0, 3),
                engineeringRisks: engineeringRisks.slice(0, 4),
                designImplications,
            };
        }

        function getStructureProfile(structureName) {
            const name = String(structureName || '').toLowerCase();
            const profile = {
                groundLevel: true,
                lightweight: false,
                requiresStableFoundation: false,
                loadBearing: false,
            };

            if (name.includes('greenhouse') || name.includes('pump')) {
                profile.lightweight = true;
            }
            if (name.includes('barn') || name.includes('shed') || name.includes('storage') || name.includes('office')) {
                profile.requiresStableFoundation = true;
            }
            if (name.includes('barn') || name.includes('storage') || name.includes('office')) {
                profile.loadBearing = true;
            }
            if (name.includes('elevated')) {
                profile.groundLevel = false;
            }

            return profile;
        }

        function hasElevationMitigation(requirements) {
            const text = String(requirements?.elevation_level || '').toLowerCase();
            return text.includes('raise') || text.includes('elevat') || text.includes('above') || text.includes('feet') || text.includes('cm');
        }

        function hasWindMitigation(requirements) {
            const text = String(requirements?.wind_resistance_measures || '').toLowerCase();
            return text.includes('brace') || text.includes('anchor') || text.includes('reinforc') || text.includes('windbreak');
        }

        function computeStructureStatus(item, siteConditions) {
            const req = item?.design_requirements || {};
            const profile = getStructureProfile(item?.structure_name || '');
            const flood = String(siteConditions?.flood_risk || '').toLowerCase();
            const terrain = String(siteConditions?.terrain || '').toLowerCase();
            const windBand = windBandFromValue(siteConditions?.wind_exposure || '');
            const soil = String(siteConditions?.soil_type || '').toLowerCase();
            const elevated = hasElevationMitigation(req);
            const anchored = hasWindMitigation(req);

            const hardRejectReasons = [];
            if (flood.includes('high') && profile.groundLevel && !elevated) {
                hardRejectReasons.push('High flood risk with insufficient elevation');
            }
            if (terrain.includes('steep') && profile.requiresStableFoundation) {
                hardRejectReasons.push('Steep terrain for a structure needing stable foundation');
            }
            if (windBand === 'high' && profile.lightweight && !anchored) {
                hardRejectReasons.push('High wind exposure for lightweight structure without anchoring');
            }
            if ((soil.includes('sandy') || soil.includes('unstable')) && profile.loadBearing) {
                hardRejectReasons.push('Unstable soil for load-bearing structure');
            }

            // Weighted score (higher is safer): Flood 40%, Wind 30%, Soil 20%, Terrain 10%.
            let floodScore = 100;
            if (flood.includes('high')) floodScore = elevated ? 45 : 20;
            else if (flood.includes('moderate')) floodScore = elevated ? 75 : 60;
            else if (flood.includes('low')) floodScore = 90;

            let windScore = 100;
            if (windBand === 'high') windScore = anchored ? 55 : 30;
            else if (windBand === 'moderate') windScore = anchored ? 80 : 65;
            else windScore = 90;

            let soilScore = 80;
            if (soil.includes('rocky')) soilScore = 75;
            else if (soil.includes('clay loam')) soilScore = 80;
            else if (soil.includes('clay')) soilScore = 65;
            else if (soil.includes('silty')) soilScore = 50;
            else if (soil.includes('sandy loam')) soilScore = 60;
            else if (soil.includes('sandy')) soilScore = 35;
            if (profile.loadBearing && (soil.includes('sandy') || soil.includes('silty'))) {
                soilScore = Math.max(soilScore - 20, 10);
            }

            let terrainScore = 85;
            if (terrain.includes('flat')) terrainScore = 95;
            else if (terrain.includes('gently')) terrainScore = 80;
            else if (terrain.includes('undulating')) terrainScore = 55;
            else if (terrain.includes('steep')) terrainScore = 25;

            const weightedScore = Math.round(
                (floodScore * 0.4) + (windScore * 0.3) + (soilScore * 0.2) + (terrainScore * 0.1)
            );

            let label = 'Recommended';
            if (hardRejectReasons.length > 0) {
                label = 'Not Recommended';
            } else if (weightedScore >= 80) {
                label = 'Recommended';
            } else if (weightedScore >= 50) {
                label = 'Use with Caution';
            } else {
                label = 'Not Recommended';
            }

            return {
                label,
                weightedScore,
                hardRejectReasons,
            };
        }

        function statusMetaFromLabel(label) {
            if (label === 'Recommended') {
                return { label: 'Recommended', icon: '🟢', className: 'structures-rec-level--recommended' };
            }
            if (label === 'Use with Caution') {
                return { label: 'Use with Caution', icon: '🟡', className: 'structures-rec-level--conditional' };
            }
            return { label: 'Not Recommended', icon: '🔴', className: 'structures-rec-level--not' };
        }

        function enforceClassificationDistribution(results, siteConditions) {
            if (!Array.isArray(results) || results.length === 0) {
                return results;
            }
            // Mandatory fallback system:
            // Always classify all structures into a full 3-tier decision matrix.
            const rankedBySafety = [...results].sort((a, b) => b.status.weightedScore - a.status.weightedScore);
            const total = rankedBySafety.length;

            // Ensure one best and one worst option always exist.
            rankedBySafety[0].status.label = 'Recommended';
            if (total > 1) {
                rankedBySafety[total - 1].status.label = 'Not Recommended';
                if (rankedBySafety[total - 1].status.hardRejectReasons.length === 0) {
                    rankedBySafety[total - 1].status.hardRejectReasons = ['Lowest suitability based on combined site risk factors'];
                }
            }

            // Everything in between starts as caution.
            for (let i = 1; i < total - 1; i += 1) {
                rankedBySafety[i].status.label = 'Use with Caution';
            }

            // If there are exactly 2 structures, keep both categories and force one caution by rebalancing best.
            if (total === 2) {
                rankedBySafety[0].status.label = 'Use with Caution';
                rankedBySafety[1].status.label = 'Not Recommended';
                rankedBySafety[0].status.weightedScore = Math.max(rankedBySafety[0].status.weightedScore, 50);
            }

            // If a single structure somehow exists, classify it as caution to keep it visible.
            if (total === 1) {
                rankedBySafety[0].status.label = 'Use with Caution';
            }

            return results;
        }

        function groupByClassification(results) {
            const grouped = {
                recommended: [],
                caution: [],
                notRecommended: [],
            };
            results.forEach((entry) => {
                if (entry.status.label === 'Recommended') {
                    grouped.recommended.push(entry);
                } else if (entry.status.label === 'Use with Caution') {
                    grouped.caution.push(entry);
                } else {
                    grouped.notRecommended.push(entry);
                }
            });
            return grouped;
        }

        function forceThreeCategoryCompleteness(grouped, allResults) {
            const rankedBySafety = [...allResults].sort((a, b) => b.status.weightedScore - a.status.weightedScore);
            if (rankedBySafety.length === 0) {
                return grouped;
            }

            // Guaranteed category fill: best -> green, middle -> yellow, worst -> red.
            const best = rankedBySafety[0];
            const worst = rankedBySafety[rankedBySafety.length - 1];
            const middle = rankedBySafety[Math.floor(rankedBySafety.length / 2)];

            if (grouped.recommended.length === 0 && best) {
                best.status.label = 'Recommended';
            }
            if (grouped.caution.length === 0 && middle) {
                middle.status.label = 'Use with Caution';
            }
            if (grouped.notRecommended.length === 0 && worst) {
                worst.status.label = 'Not Recommended';
                if (worst.status.hardRejectReasons.length === 0) {
                    worst.status.hardRejectReasons = ['Lowest suitability based on combined site risk factors'];
                }
            }

            return groupByClassification(allResults);
        }

        function renderRecommendationCard(item, status, index) {
            const req = item.design_requirements || {};
            const statusMeta = statusMetaFromLabel(status.label);
            const riskReason = status.hardRejectReasons.length
                ? `${status.hardRejectReasons.join('; ')}. `
                : '';
            const explanation = `${riskReason}${String(item.engineering_explanation || '').trim()}`.trim();

            return `
                <details class="structures-rec-card" ${index === 0 ? 'open' : ''}>
                    <summary>
                        <span class="structures-rec-title">
                            <span class="structures-rec-title__icon" aria-hidden="true">🏠</span>
                            <span>${escapeHtml(item.structure_name || 'Structure')}</span>
                        </span>
                        <span class="structures-rec-level ${statusMeta.className}">${statusMeta.icon} ${escapeHtml(statusMeta.label)}</span>
                    </summary>
                    <div class="structures-rec-content">
                        <p>${escapeHtml(explanation)}</p>
                        <ul class="structures-rec-specs">
                            <li><strong>Foundation</strong><span>${escapeHtml(req.foundation_type || '')}</span></li>
                            <li><strong>Elevation</strong><span>${escapeHtml(req.elevation_level || '')}</span></li>
                            <li><strong>Drainage</strong><span>${escapeHtml(req.drainage_needs || '')}</span></li>
                            <li><strong>Wind Resistance</strong><span>${escapeHtml(req.wind_resistance_measures || '')}</span></li>
                            <li><strong>Materials</strong><span>${escapeHtml(req.material_suggestions || '')}</span></li>
                        </ul>
                    </div>
                </details>
            `;
        }

        function renderSiteConditionsForm(siteConditions) {
            const soilPrefill = normalizeTitleCase(siteConditions.soil_type || '');
            const terrainPrefill = normalizeTitleCase(siteConditions.terrain || '');
            const windPrefill = normalizeTitleCase(siteConditions.wind_exposure || '');
            const floodText = formatFloodRiskDisplay(siteConditions.flood_risk || 'Unknown');

            const buildOptions = (items, selectedValue) => {
                const list = [`<option value="">Select</option>`];
                items.forEach((item) => {
                    const selected = selectedValue.toLowerCase() === item.toLowerCase() ? 'selected' : '';
                    list.push(`<option value="${escapeHtml(item)}" ${selected}>${escapeHtml(item)}</option>`);
                });
                return list.join('');
            };

            conditionsEl.innerHTML = `
                <div class="structures-form">
                    <div class="structures-form__row">
                        <label class="structures-form__label" for="structures-flood-risk">Flood Risk</label>
                        <input id="structures-flood-risk" class="structures-form__input structures-form__input--readonly" type="text" value="${escapeHtml(floodText)}" readonly />
                    </div>
                    <div class="structures-form__row">
                        <label class="structures-form__label" for="structures-soil-type">Soil Type <span class="structures-form__required">*</span></label>
                        <select id="structures-soil-type" class="structures-form__select" required>
                            ${buildOptions(SOIL_OPTIONS, soilPrefill)}
                        </select>
                    </div>
                    <div class="structures-form__row">
                        <label class="structures-form__label" for="structures-terrain">Terrain <span class="structures-form__required">*</span></label>
                        <select id="structures-terrain" class="structures-form__select" required>
                            ${buildOptions(TERRAIN_OPTIONS, terrainPrefill)}
                        </select>
                    </div>
                    <div class="structures-form__row">
                        <label class="structures-form__label" for="structures-wind-exposure">Wind Exposure <span class="structures-form__required">*</span></label>
                        <select id="structures-wind-exposure" class="structures-form__select" required>
                            ${buildOptions(WIND_OPTIONS, windPrefill)}
                        </select>
                    </div>
                </div>
            `;

            ['structures-soil-type', 'structures-terrain', 'structures-wind-exposure'].forEach((id) => {
                const field = document.getElementById(id);
                if (!field) return;
                field.addEventListener('change', function () {
                    clearResultsWithPrompt();
                });
            });
        }

        function readSelectedConditions() {
            const soilField = document.getElementById('structures-soil-type');
            const terrainField = document.getElementById('structures-terrain');
            const windField = document.getElementById('structures-wind-exposure');
            const floodField = document.getElementById('structures-flood-risk');

            return {
                soilType: soilField ? String(soilField.value || '').trim() : '',
                terrain: terrainField ? String(terrainField.value || '').trim() : '',
                windExposure: windField ? String(windField.value || '').trim() : '',
                floodRisk: floodField ? String(floodField.value || '').trim() : '',
            };
        }

        function validateBeforeAnalyze() {
            if (!currentSelection || !currentSelection.location || !currentSelection.location.barangay) {
                return 'Click the map and detect a valid barangay before analysis.';
            }
            const selected = readSelectedConditions();
            if (!selected.floodRisk || selected.floodRisk.toLowerCase().includes('unknown')) {
                return 'Flood risk is required before analysis.';
            }
            if (!selected.soilType) {
                return 'Please select soil type.';
            }
            if (!selected.terrain) {
                return 'Please select terrain.';
            }
            if (!selected.windExposure) {
                return 'Please select wind exposure.';
            }
            return '';
        }

        function detectSelection(lat, lng) {
            if (pin) {
                map.removeLayer(pin);
            }

            pin = L.marker([lat, lng]).addTo(map);
            pin.bindPopup(`Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`).openPopup();

            clearResultsWithPrompt();
            statusEl.textContent = 'Detecting location and flood risk...';
            if (analyzeBtn) {
                analyzeBtn.disabled = true;
            }

            // Show immediate click feedback before API response arrives.
            locationEl.innerHTML = `
                <div class="structures-info-list structures-info-list--location">
                    <div class="structures-info-row structures-info-row--pulse">
                        <span class="structures-info-row__icon" aria-hidden="true">📍</span>
                        <span class="structures-info-row__label">Barangay</span>
                        <span class="structures-info-row__value">Detecting...</span>
                    </div>
                    <div class="structures-info-row structures-info-row--pulse">
                        <span class="structures-info-row__icon" aria-hidden="true">🏙️</span>
                        <span class="structures-info-row__label">City</span>
                        <span class="structures-info-row__value">Detecting...</span>
                    </div>
                    <div class="structures-info-row structures-info-row--pulse">
                        <span class="structures-info-row__icon" aria-hidden="true">🗺️</span>
                        <span class="structures-info-row__label">Province</span>
                        <span class="structures-info-row__value">Detecting...</span>
                    </div>
                    <div class="structures-info-row structures-info-row--pulse">
                        <span class="structures-info-row__icon" aria-hidden="true">🧭</span>
                        <span class="structures-info-row__label">Coordinates</span>
                        <span class="structures-info-row__value">${escapeHtml(lat.toFixed(7))}, ${escapeHtml(lng.toFixed(7))}</span>
                    </div>
                </div>
            `;

            window.axios
                .post(
                    locationUrl,
                    { latitude: lat, longitude: lng },
                    {
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }
                )
                .then((response) => {
                    const payload = response.data || {};
                    if (!payload.success || !payload.data) {
                        throw new Error(payload.message || 'Location detection failed.');
                    }

                    const data = payload.data;
                    const location = data.location || {};
                    const conditions = data.site_conditions || {};
                    const backendRiskLevel = normalizeRiskLevelFromText(conditions.flood_risk || '');
                    const mappedRiskLevel = riskLevelFromBarangayName(location.barangay || '');
                    const effectiveRiskText = backendRiskLevel === 'unknown' && mappedRiskLevel !== 'unknown'
                        ? getRiskStyle(mappedRiskLevel).label
                        : (conditions.flood_risk || 'Unknown');

                    locationEl.innerHTML = `
                        <div class="structures-info-list structures-info-list--location structures-info-list--ready">
                            <div class="structures-info-row">
                                <span class="structures-info-row__icon" aria-hidden="true">📍</span>
                                <span class="structures-info-row__label">Barangay</span>
                                <span class="structures-info-row__value">${escapeHtml(location.barangay || 'Unknown')}</span>
                            </div>
                            <div class="structures-info-row">
                                <span class="structures-info-row__icon" aria-hidden="true">🏙️</span>
                                <span class="structures-info-row__label">City</span>
                                <span class="structures-info-row__value">${escapeHtml(location.city || 'Unknown')}</span>
                            </div>
                            <div class="structures-info-row">
                                <span class="structures-info-row__icon" aria-hidden="true">🗺️</span>
                                <span class="structures-info-row__label">Province</span>
                                <span class="structures-info-row__value">${escapeHtml(location.province || 'Unknown')}</span>
                            </div>
                            <div class="structures-info-row">
                                <span class="structures-info-row__icon" aria-hidden="true">🧭</span>
                                <span class="structures-info-row__label">Coordinates</span>
                                <span class="structures-info-row__value">${escapeHtml(location.latitude)}, ${escapeHtml(location.longitude)}</span>
                            </div>
                        </div>
                    `;

                    renderSiteConditionsForm({
                        ...conditions,
                        flood_risk: effectiveRiskText,
                    });

                    currentSelection = {
                        location: {
                            ...location,
                            latitude: Number(location.latitude ?? lat),
                            longitude: Number(location.longitude ?? lng),
                        },
                    };
                    statusEl.textContent = 'Location detected. Configure site conditions, then click Analyze Site.';
                    if (analyzeBtn) {
                        analyzeBtn.disabled = false;
                    }
                })
                .catch((error) => {
                    const message = error?.response?.data?.message || error.message || 'Failed to analyze this location.';
                    statusEl.textContent = message;
                });
        }

        fetch(geofenceUrl, { headers: { Accept: 'application/json' } })
            .then((res) => (res.ok ? res.json() : null))
            .then((geojson) => {
                if (!geojson) {
                    return;
                }

                const collection = geojson.type === 'FeatureCollection' ? geojson : { type: 'FeatureCollection', features: [geojson] };
                const enriched = {
                    ...collection,
                    features: (collection.features || []).map((feature) => {
                        const name = getFeatureName(feature);
                        const canonical = name.toLowerCase().replace(/[^a-z0-9]/g, '');
                        const riskLevel = FIXED_FLOOD_RISK_MAP[canonical] || 'unknown';
                        const style = getRiskStyle(riskLevel);
                        const properties = {
                            ...(feature.properties || {}),
                            flood_risk_level: riskLevel,
                            flood_risk_label: style.label,
                            flood_risk_color_label: style.colorLabel,
                        };

                        return { ...feature, properties };
                    }),
                };

                const layer = L.geoJSON(enriched, {
                    style: function (feature) {
                        const riskLevel = (feature && feature.properties && feature.properties.flood_risk_level) || 'unknown';
                        const style = getRiskStyle(riskLevel);
                        return {
                            color: style.line,
                            weight: 1.6,
                            fillColor: style.fill,
                            fillOpacity: 0.24,
                        };
                    },
                    onEachFeature: function (feature, featureLayer) {
                        const props = (feature && feature.properties) || {};
                        const name = getFeatureName(feature);
                        featureLayer.bindTooltip(`${name} • ${props.flood_risk_label || 'Unknown Risk'}`, { sticky: true });
                        featureLayer.on('click', function (e) {
                            const clicked = e.latlng;
                            detectSelection(clicked.lat, clicked.lng);
                        });
                    },
                }).addTo(map);
                if (layer.getBounds && layer.getBounds().isValid()) {
                    map.fitBounds(layer.getBounds(), { padding: [20, 20] });
                }
            })
            .catch(() => {});

        map.on('click', function (event) {
            const { lat, lng } = event.latlng;
            detectSelection(lat, lng);
        });

        if (analyzeBtn) {
            analyzeBtn.addEventListener('click', function () {
                if (isAnalyzing) {
                    return;
                }
                const validationError = validateBeforeAnalyze();
                if (validationError !== '') {
                    statusEl.textContent = validationError;
                    return;
                }

                const selected = readSelectedConditions();
                const loc = currentSelection?.location || {};
                setAnalyzeLoading(true);
                classificationEl.innerHTML = '';
                summaryEl.innerHTML = '';
                cardsEl.innerHTML = '';
                statusEl.innerHTML = `<div class="structures-decision structures-decision--loading">Analyzing site conditions…</div>`;

                window.axios
                    .post(
                        analysisUrl,
                        {
                            latitude: loc.latitude,
                            longitude: loc.longitude,
                            soil_type: selected.soilType,
                            terrain: selected.terrain,
                            wind_exposure: selected.windExposure,
                        },
                        {
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        }
                    )
                    .then((response) => {
                        const payload = response.data || {};
                        if (!payload.success || !payload.data) {
                            throw new Error(payload.message || 'Analysis failed.');
                        }

                        const data = payload.data;
                        const analysis = data.analysis || {};
                        const summary = analysis.engineering_summary || {};
                        const recommendationList = Array.isArray(analysis.structure_recommendations)
                            ? analysis.structure_recommendations
                            : [];
                        const buildabilitySummary = buildSiteBuildabilitySummary(data.site_conditions || selected);

                        statusEl.innerHTML = `<div class="structures-decision structures-decision--done">Site analysis generated successfully.</div>`;

                        classificationEl.innerHTML = '';

                        summaryEl.innerHTML = `
                                <article class="structures-buildability">
                                    <h3>🧭 Site Buildability Summary</h3>
                                    <section class="structures-buildability__block structures-buildability__block--types">
                                        <h4><span class="structures-buildability__hicon" aria-hidden="true">🟢</span>Suitable Structure Types</h4>
                                        <ul>
                                            ${buildabilitySummary.suitableTypes.map((line) => `<li>${escapeHtml(line)}</li>`).join('')}
                                        </ul>
                                    </section>
                                    <section class="structures-buildability__block structures-buildability__block--risks">
                                        <h4><span class="structures-buildability__hicon" aria-hidden="true">⚠️</span>Key Engineering Risks</h4>
                                        <ul>
                                            ${buildabilitySummary.engineeringRisks.map((line) => `<li>${escapeHtml(line)}</li>`).join('')}
                                        </ul>
                                    </section>
                                    <section class="structures-buildability__block structures-buildability__block--implications">
                                        <h4><span class="structures-buildability__hicon" aria-hidden="true">🏗️</span>Design Implications</h4>
                                        <ul>
                                            ${buildabilitySummary.designImplications.map((line) => `<li>${escapeHtml(line)}</li>`).join('')}
                                        </ul>
                                    </section>
                                </article>
                            `;

                        const classificationResults = enforceClassificationDistribution(
                            recommendationList.map((item) => ({
                                item,
                                status: computeStructureStatus(item, data.site_conditions || selected),
                            })),
                            data.site_conditions || selected
                        );

                        const completeGroups = forceThreeCategoryCompleteness(
                            groupByClassification(classificationResults),
                            classificationResults
                        );
                        let cardIndex = 0;
                        cardsEl.innerHTML = `
                            <section class="structures-rec-group">
                                <h3 class="structures-rec-group__title">🟢 Recommended Structures</h3>
                                ${completeGroups.recommended.map(({ item, status }) => renderRecommendationCard(item, status, cardIndex++)).join('')}
                            </section>
                            <section class="structures-rec-group">
                                <h3 class="structures-rec-group__title">🟡 Use with Caution Structures</h3>
                                ${completeGroups.caution.map(({ item, status }) => renderRecommendationCard(item, status, cardIndex++)).join('')}
                            </section>
                            <section class="structures-rec-group">
                                <h3 class="structures-rec-group__title">🔴 Not Recommended Structures</h3>
                                ${completeGroups.notRecommended.map(({ item, status }) => renderRecommendationCard(item, status, cardIndex++)).join('')}
                            </section>
                        `;
                    })
                    .catch((error) => {
                        const message = error?.response?.data?.message || error.message || 'Failed to analyze site conditions.';
                        statusEl.innerHTML = `
                            <div class="structures-decision structures-decision--error">
                                <p class="structures-decision__title">⚠️ Analysis failed</p>
                                <p class="structures-decision__reason">${escapeHtml(message)}</p>
                            </div>
                        `;
                    })
                    .finally(() => {
                        setAnalyzeLoading(false);
                    });
            });
        }
    });
})();
