function refreshIcons() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

const root = document.getElementById('admin-farms-root');
if (root) {
    const base = root.dataset.farmsBase;
    const printUrl = root.dataset.farmsPrintUrl;
    const viewModal = document.getElementById('admin-farms-modal-view');
    const viewBackdrop = document.getElementById('admin-farms-modal-view-backdrop');
    const editModal = document.getElementById('admin-farms-modal-edit');
    const editBackdrop = document.getElementById('admin-farms-modal-edit-backdrop');
    const viewBody = document.getElementById('admin-farms-view-body');
    const editForm = document.getElementById('admin-farms-edit-form');
    const cropInput = document.getElementById('edit-farm-crop-type');
    const stageInput = document.getElementById('edit-farm-stage');
    const plantingDateInput = document.getElementById('edit-farm-planting-date');
    const farmAreaInput = document.getElementById('edit-farm-area');
    const farmerNameInput = document.getElementById('edit-farm-name');
    const barangayInput = document.getElementById('edit-farm-barangay');
    const toast = document.getElementById('admin-farms-toast');
    const exportToggle = document.getElementById('admin-farms-export-toggle');
    const exportMenu = document.getElementById('admin-farms-export-menu');
    const printButton = document.getElementById('admin-farms-print-btn');
    const printTbody = document.getElementById('admin-farms-print-tbody');
    const printGeneratedAt = document.getElementById('admin-farms-print-generated-at');
    const printTotal = document.getElementById('admin-farms-print-total');

    const escapeHtml = (value) =>
        String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');

    const openModal = (modal, backdrop) => {
        if (!modal || !backdrop) {
            return;
        }
        modal.hidden = false;
        backdrop.hidden = false;
        document.body.classList.add('admin-users-modal-open');
        refreshIcons();
    };

    const closeModal = (modal, backdrop) => {
        if (!modal || !backdrop) {
            return;
        }
        modal.hidden = true;
        backdrop.hidden = true;
        document.body.classList.remove('admin-users-modal-open');
    };

    const renderDetails = (data) => {
        if (!viewBody) {
            return;
        }
        const rows = [
            ['Farmer Name', data.name || '—'],
            ['Location (Barangay)', data.location || '—'],
            ['Crop Type', data.crop_type || '—'],
            ['Farming Stage', data.farming_stage_label || '—'],
            ['Planting Date', data.planting_date || '—'],
            ['Farm Size (ha)', data.farm_area ? `${data.farm_area} ha` : '—'],
        ];
        viewBody.innerHTML = '';
        rows.forEach(([dt, dd]) => {
            const term = document.createElement('dt');
            term.className = 'admin-users-detail__dt';
            term.textContent = dt;
            const value = document.createElement('dd');
            value.className = 'admin-users-detail__dd';
            value.textContent = dd;
            viewBody.appendChild(term);
            viewBody.appendChild(value);
        });
    };

    const fillEditForm = (id, data) => {
        if (!editForm) {
            return;
        }
        editForm.action = `${base}/${encodeURIComponent(id)}`;
        if (farmerNameInput) {
            farmerNameInput.value = data.name || '—';
        }
        if (barangayInput) {
            barangayInput.value = data.location || '—';
        }
        if (cropInput) {
            const cropVal = data.crop_type || '';
            if (cropInput.tagName === 'SELECT') {
                cropInput.querySelectorAll('option[data-dynamic-crop="1"]').forEach((opt) => opt.remove());
                const hasOption = Array.from(cropInput.options).some((opt) => opt.value === cropVal);
                if (cropVal && !hasOption) {
                    const opt = document.createElement('option');
                    opt.value = cropVal;
                    opt.textContent = cropVal;
                    opt.dataset.dynamicCrop = '1';
                    cropInput.appendChild(opt);
                }
                cropInput.value = cropVal;
            } else {
                cropInput.value = cropVal;
            }
        }
        if (stageInput) {
            stageInput.value = data.farming_stage || '';
        }
        if (plantingDateInput) {
            plantingDateInput.value = data.planting_date || '';
        }
        if (farmAreaInput) {
            farmAreaInput.value = data.farm_area || '';
        }
    };

    const loadFarm = async (id) => {
        const response = await fetch(`${base}/${encodeURIComponent(id)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            throw new Error('Failed to load farm details.');
        }
        return response.json();
    };

    if (exportToggle && exportMenu) {
        exportToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = exportMenu.hidden;
            exportMenu.hidden = !open;
            exportToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', () => {
            exportMenu.hidden = true;
            exportToggle.setAttribute('aria-expanded', 'false');
        });
        exportMenu.addEventListener('click', (event) => event.stopPropagation());
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
                    throw new Error('Print endpoint unavailable.');
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
                    printTbody.innerHTML = '<tr><td colspan="6" class="admin-users-table__empty">No farm records found for the selected filters.</td></tr>';
                } else {
                    printTbody.innerHTML = rows
                        .map((row) => `<tr>
                                <td>${escapeHtml(row.name || '—')}</td>
                                <td>${escapeHtml(row.location || '—')}</td>
                                <td>${escapeHtml(row.crop_type || '—')}</td>
                                <td>${escapeHtml(row.farming_stage || '—')}</td>
                                <td>${escapeHtml(row.planting_date || '—')}</td>
                                <td>${escapeHtml(row.farm_area || '—')}</td>
                            </tr>`)
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

    root.addEventListener('click', async (event) => {
        const actionBtn = event.target.closest('[data-action][data-user-id]');
        if (!actionBtn) {
            return;
        }

        const action = actionBtn.getAttribute('data-action');
        const userId = actionBtn.getAttribute('data-user-id');
        if (!action || !userId) {
            return;
        }

        try {
            const data = await loadFarm(userId);
            if (action === 'view') {
                renderDetails(data);
                openModal(viewModal, viewBackdrop);
                return;
            }
            if (action === 'edit') {
                fillEditForm(userId, data);
                openModal(editModal, editBackdrop);
            }
        } catch {
            window.alert('Unable to load farm record. Please try again.');
        }
    });

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-close-modal');
            if (key === 'view') {
                closeModal(viewModal, viewBackdrop);
            }
            if (key === 'edit') {
                closeModal(editModal, editBackdrop);
            }
        });
    });

    viewBackdrop?.addEventListener('click', () => closeModal(viewModal, viewBackdrop));
    editBackdrop?.addEventListener('click', () => closeModal(editModal, editBackdrop));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal(viewModal, viewBackdrop);
            closeModal(editModal, editBackdrop);
        }
    });

    if (toast) {
        setTimeout(() => {
            toast.dataset.visible = 'false';
            setTimeout(() => toast.remove(), 220);
        }, 3200);
    }

    refreshIcons();
}

