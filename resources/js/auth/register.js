// AGRIGUARD registration interactions
// Barangay dropdown: load all Amulung barangays (PSGC) into native <select>

document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('farm_barangay');
    if (!select || select.tagName !== 'SELECT') return;

    const apiUrl = select.getAttribute('data-api-url') || '/api/amulung-barangays';
    const selected = (select.getAttribute('data-old') || '').toString().trim();

    fetch(apiUrl, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then((res) => {
            if (!res.ok) throw new Error(`Barangays fetch failed: ${res.status}`);
            return res.json();
        })
        .then((data) => {
            const barangays = Array.isArray(data?.barangays) ? data.barangays : [];
            select.innerHTML = '<option value="">Select barangay</option>';
            barangays.forEach((b) => {
                const opt = document.createElement('option');
                opt.value = b.code;
                opt.textContent = b.name;
                if (String(b.code) === selected) opt.selected = true;
                select.appendChild(opt);
            });
        })
        .catch(() => {
            select.innerHTML = '<option value="">Could not load barangays</option>';
        });
});
