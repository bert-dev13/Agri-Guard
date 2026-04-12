/**
 * Populate a barangay <select> from /api/barangays, optionally filtered by a municipality <select>.
 */
export function initBarangaySelect(select) {
    if (!select || select.tagName !== 'SELECT') {
        return;
    }

    const apiBase = select.getAttribute('data-api-url') || '/api/barangays';
    const municipalitySelectId = select.getAttribute('data-municipality-select');
    const municipalitySelect = municipalitySelectId ? document.getElementById(municipalitySelectId) : null;
    const selectedValue = (select.getAttribute('data-old') || '').toString().trim();

    function buildUrl(municipality) {
        const url = new URL(apiBase, window.location.origin);
        if (municipality) {
            url.searchParams.set('municipality', municipality);
        }
        return url.toString();
    }

    function populate(municipality) {
        select.innerHTML = '<option value="">Loading…</option>';
        select.disabled = true;

        fetch(buildUrl(municipality), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => {
                if (!res.ok) {
                    throw new Error('Barangays fetch failed');
                }
                return res.json();
            })
            .then((data) => {
                const barangays = Array.isArray(data?.barangays) ? data.barangays : [];
                select.innerHTML = '<option value="">Select barangay</option>';
                barangays.forEach((b) => {
                    const opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.name;
                    if (String(b.id) === selectedValue) {
                        opt.selected = true;
                    }
                    select.appendChild(opt);
                });
                select.disabled = false;
            })
            .catch(() => {
                select.innerHTML = '<option value="">Could not load barangays</option>';
                select.disabled = false;
            });
    }

    function refresh() {
        if (municipalitySelect) {
            const mun = (municipalitySelect.value || '').trim();
            if (!mun) {
                select.innerHTML = '<option value="">Select municipality first</option>';
                select.disabled = true;
                return;
            }
        }

        const mun = municipalitySelect ? (municipalitySelect.value || '').trim() : '';
        populate(mun);
    }

    if (municipalitySelect) {
        municipalitySelect.addEventListener('change', () => {
            select.selectedIndex = 0;
            refresh();
        });
    }

    refresh();
}
