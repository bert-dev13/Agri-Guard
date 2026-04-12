/**
 * Admin user management: export menu and modals.
 */
function refreshIcons() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function openModal(prefix) {
    const modal = document.getElementById(`admin-users-modal-${prefix}`);
    const backdrop = document.getElementById(`admin-users-modal-${prefix}-backdrop`);
    if (!modal || !backdrop) {
        return;
    }
    modal.hidden = false;
    backdrop.hidden = false;
    document.body.classList.add('admin-users-modal-open');
    refreshIcons();
}

function closeModal(prefix) {
    const modal = document.getElementById(`admin-users-modal-${prefix}`);
    const backdrop = document.getElementById(`admin-users-modal-${prefix}-backdrop`);
    if (!modal || !backdrop) {
        return;
    }
    modal.hidden = true;
    backdrop.hidden = true;
    document.body.classList.remove('admin-users-modal-open');
}

function closeAllModals() {
    ['view', 'edit', 'add'].forEach(closeModal);
}

function detailRows(d) {
    return [
        ['Full name', d.name],
        ['Email', d.email],
        ['Role', d.role_label],
        ['Municipality', d.farm_municipality || '—'],
        ['Barangay', d.farm_barangay || '—'],
        ['Crop type', d.crop_type || '—'],
        ['Farming stage', d.farming_stage_label || (d.farming_stage ? d.farming_stage.replaceAll('_', ' ') : '—')],
        ['Planting date', d.planting_date || '—'],
        ['Farm area (ha)', d.farm_area || '—'],
        ['Account status', d.status?.label || '—'],
    ];
}

function renderDetails(dl, d) {
    dl.innerHTML = '';
    for (const [dt, dd] of detailRows(d)) {
        const t = document.createElement('dt');
        t.className = 'admin-users-detail__dt';
        t.textContent = dt;
        const v = document.createElement('dd');
        v.className = 'admin-users-detail__dd';
        v.textContent = dd;
        dl.appendChild(t);
        dl.appendChild(v);
    }
}

function readBarangaysCatalog() {
    const el = document.getElementById('admin-barangays-catalog');
    if (!el) {
        return [];
    }
    try {
        const parsed = JSON.parse(el.textContent || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function fillBarangaySelect(select, municipality, selectedId, catalog) {
    if (!select) {
        return;
    }
    const mun = (municipality || '').trim();
    select.innerHTML = '<option value="">—</option>';
    catalog
        .filter((b) => !mun || b.municipality === mun)
        .forEach((b) => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.name;
            if (String(b.id) === String(selectedId || '').trim()) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
}

function fillEditForm(d, catalog) {
    const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) {
            el.value = val ?? '';
        }
    };
    set('edit-name', d.name);
    set('edit-email', d.email);
    set('edit-role', d.role);
    set('edit-farm_municipality', d.farm_municipality);
    fillBarangaySelect(document.getElementById('edit-farm_barangay_code'), d.farm_municipality, d.farm_barangay_code, catalog);
    set('edit-crop_type', d.crop_type);
    set('edit-farming_stage', d.farming_stage);
    set('edit-planting_date', d.planting_date);
    set('edit-farm_area', d.farm_area);
}

function setFieldGroupVisibility(roleSelectId, groupSelector, options = {}) {
    const roleEl = document.getElementById(roleSelectId);
    if (!roleEl) {
        return;
    }
    const isAdmin = String(roleEl.value || '').toLowerCase() === 'admin';
    const groups = Array.from(document.querySelectorAll(groupSelector));
    groups.forEach((group) => {
        group.classList.toggle('admin-users-edit-field--hidden', isAdmin);
        const controls = group.querySelectorAll('input, select, textarea');
        controls.forEach((control) => {
            control.disabled = isAdmin;
            if (isAdmin && options.clearValues) {
                control.value = '';
            }
        });
    });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

const root = document.getElementById('admin-users-root');
if (root) {
    const base = root.dataset.usersBase;
    const printUrl = root.dataset.usersPrintUrl;
    const barangaysCatalog = readBarangaysCatalog();
    function syncFarmerProfileFields(roleSelectId, cropSelectId, stageSelectId) {
        const roleEl = document.getElementById(roleSelectId);
        const cropEl = document.getElementById(cropSelectId);
        const stageEl = document.getElementById(stageSelectId);
        if (!roleEl || !cropEl || !stageEl) {
            return;
        }
        const isAdmin = String(roleEl.value || '').toLowerCase() === 'admin';
        cropEl.disabled = isAdmin;
        cropEl.required = !isAdmin;
        stageEl.disabled = isAdmin;
        stageEl.required = !isAdmin;
        if (isAdmin) {
            cropEl.value = '';
            stageEl.value = '';
        }
    }

    const filterMunicipality = document.getElementById('admin-users-filter-municipality');
    const filterBarangay = document.getElementById('admin-users-filter-barangay');
    function syncFilterBarangayOptions() {
        if (!filterBarangay) {
            return;
        }
        const mun = filterMunicipality ? filterMunicipality.value.trim() : '';
        for (const opt of filterBarangay.options) {
            if (opt.value === '') {
                opt.disabled = false;
                continue;
            }
            const om = opt.getAttribute('data-municipality') || '';
            opt.disabled = mun !== '' && om !== mun;
        }
        const sel = filterBarangay.selectedOptions[0];
        if (sel && sel.disabled) {
            filterBarangay.value = '';
        }
    }
    if (filterBarangay) {
        if (filterMunicipality) {
            filterMunicipality.addEventListener('change', syncFilterBarangayOptions);
        }
        syncFilterBarangayOptions();
    }

    const editMunicipality = document.getElementById('edit-farm_municipality');
    const editBarangay = document.getElementById('edit-farm_barangay_code');
    if (editMunicipality && editBarangay) {
        editMunicipality.addEventListener('change', () => {
            fillBarangaySelect(editBarangay, editMunicipality.value, '', barangaysCatalog);
        });
    }

    const editRole = document.getElementById('edit-role');
    if (editRole) {
        editRole.addEventListener('change', () => {
            syncFarmerProfileFields('edit-role', 'edit-crop_type', 'edit-farming_stage');
            setFieldGroupVisibility('edit-role', '.admin-users-edit-field--farmer-only', { clearValues: true });
        });
        syncFarmerProfileFields('edit-role', 'edit-crop_type', 'edit-farming_stage');
        setFieldGroupVisibility('edit-role', '.admin-users-edit-field--farmer-only', { clearValues: false });
    }

    const addMunicipality = document.getElementById('add-farm_municipality');
    const addBarangay = document.getElementById('add-farm_barangay_code');
    if (addMunicipality && addBarangay) {
        addMunicipality.addEventListener('change', () => {
            fillBarangaySelect(addBarangay, addMunicipality.value, '', barangaysCatalog);
        });
        const oldBrgy = root.dataset.oldAddBarangay || '';
        if (addMunicipality.value) {
            fillBarangaySelect(addBarangay, addMunicipality.value, oldBrgy, barangaysCatalog);
        }
    }
    const addRole = document.getElementById('add-role');
    if (addRole) {
        addRole.addEventListener('change', () => {
            syncFarmerProfileFields('add-role', 'add-crop_type', 'add-farming_stage');
            setFieldGroupVisibility('add-role', '.admin-users-add-field--farmer-only', { clearValues: true });
        });
        syncFarmerProfileFields('add-role', 'add-crop_type', 'add-farming_stage');
        setFieldGroupVisibility('add-role', '.admin-users-add-field--farmer-only', { clearValues: false });
    }

    const exportToggle = document.getElementById('admin-users-export-toggle');
    const exportMenu = document.getElementById('admin-users-export-menu');
    const printButton = document.getElementById('admin-users-print-btn');
    const printTbody = document.getElementById('admin-users-print-tbody');
    const printGeneratedAt = document.getElementById('admin-users-print-generated-at');
    const printTotal = document.getElementById('admin-users-print-total');
    if (exportToggle && exportMenu) {
        exportToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = exportMenu.hidden;
            exportMenu.hidden = !open;
            exportToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', () => {
            exportMenu.hidden = true;
            exportToggle.setAttribute('aria-expanded', 'false');
        });
        exportMenu.addEventListener('click', (e) => e.stopPropagation());
    }
    if (printButton) {
        printButton.addEventListener('click', async () => {
            if (exportMenu) {
                exportMenu.hidden = true;
            }
            if (exportToggle) {
                exportToggle.setAttribute('aria-expanded', 'false');
            }
            const previousText = printButton.textContent;
            printButton.disabled = true;
            printButton.textContent = 'Preparing...';
            try {
                if (!printUrl || !printTbody) {
                    throw new Error('Print endpoint is unavailable.');
                }

                const params = new URLSearchParams(window.location.search);
                params.delete('page');
                const url = `${printUrl}${params.toString() ? `?${params.toString()}` : ''}`;
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('Failed to load print dataset.');
                }

                const payload = await response.json();
                const rows = Array.isArray(payload.rows) ? payload.rows : [];
                if (printGeneratedAt) {
                    const generatedAt = payload.generated_at || new Date().toLocaleString();
                    printGeneratedAt.textContent = `Generated on ${generatedAt}`;
                }
                if (printTotal) {
                    printTotal.textContent = `Total records: ${Number(payload.total || rows.length)}`;
                }

                if (rows.length === 0) {
                    printTbody.innerHTML = '<tr><td colspan="7" class="admin-users-table__empty">No users found for the selected filters.</td></tr>';
                } else {
                    printTbody.innerHTML = rows
                        .map((row) => {
                            const municipality = row.location_municipality || '—';
                            const barangay = row.location_barangay || '—';
                            const location = row.location_na ? 'N/A' : `${municipality}, ${barangay}`;
                            return `<tr>
                                <td>${escapeHtml(row.name || '—')}</td>
                                <td>${escapeHtml(row.email || '—')}</td>
                                <td>${escapeHtml(location)}</td>
                                <td>${escapeHtml(row.role_label || '—')}</td>
                                <td>${escapeHtml(row.crop_type || '—')}</td>
                                <td>${escapeHtml(row.farming_stage || '—')}</td>
                                <td>${escapeHtml(row.status?.label || '—')}</td>
                            </tr>`;
                        })
                        .join('');
                }

                window.print();
            } catch (error) {
                window.alert('Unable to prepare full print report. Please try again.');
                console.error(error);
            } finally {
                printButton.disabled = false;
                printButton.textContent = previousText || 'Print Report';
            }
        });
    }

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => {
            closeModal(btn.getAttribute('data-close-modal'));
        });
    });

    ['view', 'edit', 'add'].forEach((prefix) => {
        const backdrop = document.getElementById(`admin-users-modal-${prefix}-backdrop`);
        if (backdrop) {
            backdrop.addEventListener('click', () => closeModal(prefix));
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });

    const openAdd = document.getElementById('admin-users-open-add');
    if (openAdd) {
        openAdd.addEventListener('click', () => {
            syncFarmerProfileFields('add-role', 'add-crop_type', 'add-farming_stage');
            setFieldGroupVisibility('add-role', '.admin-users-add-field--farmer-only', { clearValues: false });
            openModal('add');
        });
    }

    const viewBody = document.getElementById('admin-users-view-body');
    const editForm = document.getElementById('admin-users-edit-form');

    async function loadUser(id) {
        const res = await fetch(`${base}/${encodeURIComponent(id)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            throw new Error('Could not load user');
        }
        return res.json();
    }

    if (root.dataset.openAdd === '1') {
        syncFarmerProfileFields('add-role', 'add-crop_type', 'add-farming_stage');
        setFieldGroupVisibility('add-role', '.admin-users-add-field--farmer-only', { clearValues: false });
        openModal('add');
    }

    root.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action][data-user-id]');
        if (!btn) {
            return;
        }
        const id = btn.getAttribute('data-user-id');
        const action = btn.getAttribute('data-action');
        if (!id || !action) {
            return;
        }
        e.preventDefault();
        try {
            const data = await loadUser(id);
            if (action === 'view' && viewBody) {
                renderDetails(viewBody, data);
                openModal('view');
            }
            if (action === 'edit' && editForm) {
                editForm.action = `${base}/${encodeURIComponent(id)}`;
                fillEditForm(data, barangaysCatalog);
                syncFarmerProfileFields('edit-role', 'edit-crop_type', 'edit-farming_stage');
                setFieldGroupVisibility('edit-role', '.admin-users-edit-field--farmer-only', { clearValues: false });
                openModal('edit');
            }
        } catch {
            window.alert('Unable to load user details. Please try again.');
        }
    });

    refreshIcons();
}
