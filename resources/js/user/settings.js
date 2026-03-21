/**
 * AGRIGUARD settings — barangay load, toggles, units, geolocation
 */
(function () {
    'use strict';

    function initLucide() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        initLucide();

        var select = document.getElementById('farm_barangay');
        if (select) {
            var apiUrl = select.getAttribute('data-api-url') || '/api/amulung-barangays';
            var selected = select.getAttribute('data-old') || '';
            fetch(apiUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    return res.json();
                })
                .then(function (data) {
                    var barangays = data.barangays || [];
                    select.innerHTML = '<option value="">Select barangay</option>';
                    barangays.forEach(function (b) {
                        var opt = document.createElement('option');
                        opt.value = b.code;
                        opt.textContent = b.name;
                        if (b.code === selected) {
                            opt.selected = true;
                        }
                        select.appendChild(opt);
                    });
                })
                .catch(function () {
                    select.innerHTML = '<option value="">Could not load</option>';
                });
        }

        document.querySelectorAll('.settings-page .ag-toggle').forEach(function (btn) {
            var key = 'ag_pref_' + (btn.getAttribute('data-pref') || '');
            var stored = localStorage.getItem(key);
            if (stored === 'false') {
                btn.classList.remove('active');
                btn.setAttribute('aria-pressed', 'false');
            }
            btn.addEventListener('click', function () {
                var isActive = !btn.classList.contains('active');
                btn.classList.toggle('active');
                btn.setAttribute('aria-pressed', isActive);
                localStorage.setItem(key, isActive);
            });
        });

        function updateOptionStyles(name) {
            document.querySelectorAll('input[name="' + name + '"]').forEach(function (r) {
                var label = r.closest('label');
                if (r.checked) {
                    label.classList.add('border-[#2E7D32]', 'bg-[#2E7D32]/5');
                    label.classList.remove('border-slate-200');
                } else {
                    label.classList.remove('border-[#2E7D32]', 'bg-[#2E7D32]/5');
                    label.classList.add('border-slate-200');
                }
            });
        }

        document.querySelectorAll('input[name="temp_unit"]').forEach(function (r) {
            var v = localStorage.getItem('ag_temp_unit') || 'C';
            if (r.value === v) {
                r.checked = true;
            }
            r.addEventListener('change', function () {
                localStorage.setItem('ag_temp_unit', r.value);
                updateOptionStyles('temp_unit');
            });
        });
        document.querySelectorAll('input[name="rain_unit"]').forEach(function (r) {
            var v = localStorage.getItem('ag_rain_unit') || 'mm';
            if (r.value === v) {
                r.checked = true;
            }
            r.addEventListener('change', function () {
                localStorage.setItem('ag_rain_unit', r.value);
                updateOptionStyles('rain_unit');
            });
        });
        updateOptionStyles('temp_unit');
        updateOptionStyles('rain_unit');

        var btnUseLocation = document.getElementById('btn-use-current-location');
        var farmLat = document.getElementById('farm_lat');
        var farmLng = document.getElementById('farm_lng');
        var coordsDisplay = document.getElementById('farm-coords-display');
        var coordsText = document.getElementById('farm-coords-text');
        if (btnUseLocation && farmLat && farmLng) {
            btnUseLocation.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported by your browser.');
                    return;
                }
                btnUseLocation.disabled = true;
                btnUseLocation.textContent = 'Getting location...';
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        var lat = pos.coords.latitude;
                        var lng = pos.coords.longitude;
                        farmLat.value = lat;
                        farmLng.value = lng;
                        if (coordsText) {
                            coordsText.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
                        }
                        if (coordsDisplay) {
                            coordsDisplay.classList.remove('hidden');
                        }
                        btnUseLocation.disabled = false;
                        btnUseLocation.innerHTML =
                            '<i data-lucide="map-pin" class="w-4 h-4"></i> Use current location';
                        initLucide();
                    },
                    function () {
                        alert('Could not get your location. Please allow location access or enter coordinates manually.');
                        btnUseLocation.disabled = false;
                        btnUseLocation.innerHTML =
                            '<i data-lucide="map-pin" class="w-4 h-4"></i> Use current location';
                        initLucide();
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            });
        }
    });
})();
