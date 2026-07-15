
    const inStockDevices = [];
    const techniciansList = [];
    const availableSimcards = [];
    const warehouseAccessories = [];
    const warehouseSelect = document.getElementById('warehouse_select');

    // Active rack tracking
    let activeRack = '';
    const rackScanInput = document.getElementById('rack_scan_input');
    const activeRackBadge = document.getElementById('activeRackBadge');

    rackScanInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const rack = this.value.trim();
            if (rack) {
                activeRack = rack;

                // Fix: properly change class from warning → success
                activeRackBadge.className = 'badge badge-success';
                activeRackBadge.style.display = 'inline-flex';
                activeRackBadge.style.alignItems = 'center';
                activeRackBadge.style.gap = '5px';
                activeRackBadge.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size:10px;"></i> RAK: ' + rack;

                // Update card border & header to green
                const rackCard = document.getElementById('rackScanCard');
                if (rackCard) {
                    rackCard.style.borderColor = 'rgba(16,185,129,0.5)';
                    const rackHeader = rackCard.querySelector('.card-header');
                    if (rackHeader) rackHeader.style.background = 'rgba(16,185,129,0.06)';
                    const rackLabel = rackCard.querySelector('label');
                    if (rackLabel) rackLabel.style.color = 'var(--accent-emerald)';
                    const rackIcon = rackCard.querySelector('.card-title i');
                    if (rackIcon) rackIcon.style.color = 'var(--accent-emerald)';
                }

                // Show green status message
                const rackStatus = document.getElementById('rackScanStatus');
                if (rackStatus) {
                    rackStatus.style.display = 'block';
                    rackStatus.innerHTML = '<i class="fa-solid fa-circle-check" style="margin-right:8px;"></i>Rak <strong>' + rack + '</strong> aktif — semua device berikutnya diambil dari rak ini.';
                }

                if (window.playBeep) window.playBeep('success');
                // Auto-focus to SN scan input
                document.getElementById('issue_scan_input').focus();
            }
            this.value = '';
        }
    });

    // ==========================================
    // MULTI-SCAN (BATCH) UI & LOGIC
    // ==========================================
    function setScanMode(mode) {
        const singleBtn = document.getElementById('modeSingleBtn');
        const batchBtn = document.getElementById('modeBatchBtn');
        const singleView = document.getElementById('scanSingleMode');
        const batchView = document.getElementById('scanBatchMode');
        const batchInput = document.getElementById('batch_sn_input');

        if (mode === 'single') {
            singleBtn.style.background = 'var(--accent-blue)';
            singleBtn.style.color = '#fff';
            singleBtn.style.borderColor = 'var(--accent-blue)';
            batchBtn.style.background = 'var(--bg-secondary)';
            batchBtn.style.color = 'var(--text-secondary)';
            batchBtn.style.borderColor = 'var(--border-color)';
            singleView.style.display = 'block';
            batchView.style.display = 'none';
            document.getElementById('issue_scan_input').focus();
        } else {
            batchBtn.style.background = 'var(--accent-blue)';
            batchBtn.style.color = '#fff';
            batchBtn.style.borderColor = 'var(--accent-blue)';
            singleBtn.style.background = 'var(--bg-secondary)';
            singleBtn.style.color = 'var(--text-secondary)';
            singleBtn.style.borderColor = 'var(--border-color)';
            singleView.style.display = 'none';
            batchView.style.display = 'block';
            batchInput.focus();
        }
    }

    async function processBatchScan() {
        const sourceType = document.getElementById('source_type_input')?.value || 'warehouse';
        if (sourceType === 'warehouse' && !activeRack) {
            triggerAlert('Silakan scan barcode rak terlebih dahulu.');
            return;
        }

        const input = document.getElementById('batch_sn_input');
        const text = input.value;
        if (!text.trim()) {
            triggerAlert('Kolom input masih kosong.');
            return;
        }

        const rawSns = text.split('\n').map(s => s.trim()).filter(s => s.length > 0);
        const sns = [...new Set(rawSns)];
        let successCount = 0;
        let failCount = 0;
        let failedSns = [];

        for (const sn of sns) {
            let resolvedSn = sn;
            if (typeof inStockDevices !== 'undefined' && Array.isArray(inStockDevices)) {
                const localDev = inStockDevices.find(d => 
                    (d.serial_number && String(d.serial_number).trim().toUpperCase() === String(sn).trim().toUpperCase()) ||
                    (d.imei && String(d.imei).trim().toUpperCase() === String(sn).trim().toUpperCase())
                );
                if (localDev) {
                    resolvedSn = localDev.serial_number;
                }
            }

            if (issueDraftSns.has(resolvedSn)) continue; // already in list
            const res = await processIssueScan(sn, true); // true = silent (no alert)
            if (res && res.success) {
                successCount++;
            } else {
                failCount++;
                failedSns.push(sn);
            }
        }

        if (successCount > 0 && window.playBeep) window.playBeep('success');
        
        let msg = `Berhasil memproses ${successCount} perangkat.`;
        if (failCount > 0) {
            msg += ` (Gagal: ${failCount} SN tidak ditemukan / invalid)`;
            triggerAlert(msg);
            input.value = failedSns.join('\n');
        } else {
            input.value = '';
            issueAlert.style.display = 'none';
        }
        
        const badge = document.getElementById('scanQueueBadge');
        if (badge && successCount > 0) {
            badge.style.display = 'inline-block';
            badge.textContent = `+${successCount} ditambahkan`;
            setTimeout(() => { badge.style.display = 'none'; }, 3000);
        }
    }


    const tabAdminBtn = document.getElementById('tabAdminBtn');
    const tabTechBtn = document.getElementById('tabTechBtn');
    const panelAdminIssue = document.getElementById('panelAdminIssue');
    const panelTechIssue = document.getElementById('panelTechIssue');
    const issueScanInput = document.getElementById('issue_scan_input');
    const emulatorTarget = document.getElementById('emulatorTarget');

    let activeTab = 'admin';

    // ==========================================
    // SN SUGGESTION / AUTOCOMPLETE LOGIC (AJAX)
    // ==========================================
    const snSuggestionBox = document.getElementById('snSuggestionBox');
    let suggestTimeout = null;

    function renderSuggestions(query) {
        if (!query || query.length < 2) {
            snSuggestionBox.style.display = 'none';
            snSuggestionBox.innerHTML = '';
            return;
        }
        clearTimeout(suggestTimeout);
        suggestTimeout = setTimeout(() => {
            const warehouse = document.getElementById('warehouse_select')?.value || '';
            const sourceType = document.getElementById('source_type_input')?.value || 'warehouse';
            const sourceTech = document.getElementById('source_tech_input')?.value || '';

            const params = new URLSearchParams({ q: query, warehouse, source_type: sourceType });
            if (sourceType === 'technician' && sourceTech) params.append('source_tech', sourceTech);

            fetch(`1?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(matches => {
                if (!matches || matches.length === 0) {
                    snSuggestionBox.style.display = 'none';
                    snSuggestionBox.innerHTML = '';
                    return;
                }
                snSuggestionBox.innerHTML = matches.map((d, i) => `
                    <div class="sn-suggest-item" data-sn="${d.serial_number}" data-idx="${i}"
                        style="display:flex; align-items:center; gap:12px; padding:10px 16px; cursor:pointer; border-bottom:1px solid var(--border-color); transition:background .15s;">
                        <i class="fa-solid fa-microchip" style="color:var(--accent-blue); font-size:14px; flex-shrink:0;"></i>
                        <div>
                            <div style="font-weight:700; font-size:14px; color:var(--text-primary);">${d.serial_number}</div>
                            <div style="font-size:11px; color:var(--text-muted);">${d.type ?? ''} &middot; <span style="color: var(--accent-emerald);">${d.status ?? ''}</span> ${d.warehouse_code ? '&middot; ' + d.warehouse_code : ''} ${d.current_holder ? '&middot; ' + d.current_holder : ''}</div>
                        </div>
                    </div>
                `).join('');

                snSuggestionBox.querySelectorAll('.sn-suggest-item').forEach(item => {
                    item.addEventListener('mouseenter', () => item.style.background = 'var(--bg-primary)');
                    item.addEventListener('mouseleave', () => item.style.background = '');
                    item.addEventListener('mousedown', e => {
                        e.preventDefault();
                        const sn = item.dataset.sn;
                        issueScanInput.value = '';
                        snSuggestionBox.style.display = 'none';
                        snSuggestionBox.innerHTML = '';
                        processIssueScan(sn);
                        issueScanInput.focus();
                    });
                });

                snSuggestionBox.style.display = 'block';
            })
            .catch(() => {
                snSuggestionBox.style.display = 'none';
            });
        }, 300);
    }

    issueScanInput.addEventListener('input', function () {
        renderSuggestions(this.value.trim());
    });

    issueScanInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            snSuggestionBox.style.display = 'none';
            snSuggestionBox.innerHTML = '';
        }
    });

    document.addEventListener('click', function (e) {
        if (!snSuggestionBox.contains(e.target) && e.target !== issueScanInput) {
            snSuggestionBox.style.display = 'none';
        }
    });

    function toggleTargetType(type) {
        const techSelect = document.getElementById('techSelectWrapper');
        const custSelect = document.getElementById('custSelectWrapper');
        if (type === 'technician') {
            techSelect.style.display = 'block';
            document.getElementById('technician_select').setAttribute('required', 'required');
            custSelect.style.display = 'none';
            document.getElementById('customer_select').removeAttribute('required');
        } else {
            techSelect.style.display = 'none';
            document.getElementById('technician_select').removeAttribute('required');
            custSelect.style.display = 'block';
            document.getElementById('customer_select').setAttribute('required', 'required');
        }
    }


    // Tabs navigation
    const tabHistoryBtn = document.getElementById('tabHistoryBtn');
    const panelHistoryIssue = document.getElementById('panelHistoryIssue');

    tabAdminBtn.addEventListener('click', () => {
        activeTab = 'admin';
        tabAdminBtn.style.borderBottomColor = 'var(--accent-blue)';
        tabAdminBtn.style.color = 'var(--text-primary)';
        tabTechBtn.style.borderBottomColor = 'transparent';
        tabTechBtn.style.color = 'var(--text-secondary)';
        if (tabHistoryBtn) {
            tabHistoryBtn.style.borderBottomColor = 'transparent';
            tabHistoryBtn.style.color = 'var(--text-secondary)';
        }
        panelAdminIssue.style.display = 'block';
        panelTechIssue.style.display = 'none';
        if (panelHistoryIssue) panelHistoryIssue.style.display = 'none';
        if (emulatorTarget) {
            emulatorTarget.value = '.scan-target-input';
        }
        issueScanInput.focus();
    });

    tabTechBtn.addEventListener('click', () => {
        activeTab = 'tech';
        tabTechBtn.style.borderBottomColor = 'var(--accent-indigo)';
        tabTechBtn.style.color = 'var(--text-primary)';
        tabAdminBtn.style.borderBottomColor = 'transparent';
        tabAdminBtn.style.color = 'var(--text-secondary)';
        if (tabHistoryBtn) {
            tabHistoryBtn.style.borderBottomColor = 'transparent';
            tabHistoryBtn.style.color = 'var(--text-secondary)';
        }
        panelAdminIssue.style.display = 'none';
        if (panelHistoryIssue) panelHistoryIssue.style.display = 'none';
        panelTechIssue.style.display = 'block';
        if (emulatorTarget) {
            emulatorTarget.value = '#manual_sn_input'; // fallback
        }
        loadMobileAcceptance();
    });

    if (tabHistoryBtn) {
        tabHistoryBtn.addEventListener('click', () => {
            activeTab = 'history';
            tabHistoryBtn.style.borderBottomColor = 'var(--accent-blue)';
            tabHistoryBtn.style.color = 'var(--text-primary)';
            tabAdminBtn.style.borderBottomColor = 'transparent';
            tabAdminBtn.style.color = 'var(--text-secondary)';
            tabTechBtn.style.borderBottomColor = 'transparent';
            tabTechBtn.style.color = 'var(--text-secondary)';
            panelAdminIssue.style.display = 'none';
            panelTechIssue.style.display = 'none';
            panelHistoryIssue.style.display = 'block';
            if (emulatorTarget) {
                emulatorTarget.value = '';
            }
            loadHistoryIssue();
        });
    }

    function loadHistoryIssue() {
        const tbody = document.getElementById('historyIssueBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Memuat data...</td></tr>';
        
        // Ambil riwayat dari 3 bulan terakhir misalnya, atau all tanpa end_date
        fetch(`1?start_date=2024-01-01&end_date=1`)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">Belum ada riwayat serah terima.</td></tr>';
                    return;
                }
                tbody.innerHTML = '';
                data.forEach(item => {
                    const dateObj = new Date(item.created_at);
                    const formattedDate = dateObj.toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'});
                    
                    const statusAccept = item.is_accepted 
                        ? `<span class="badge badge-success"><i class="fa-solid fa-check"></i> Diterima</span><div style="font-size:10px; color:var(--text-muted); margin-top:4px;">${new Date(item.accepted_at).toLocaleString('id-ID', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'})}</div>` 
                        : `<span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Menunggu Konfirmasi Teknisi</span>`;
                    
                    const targetStr = item.target_type === 'TECHNICIAN' 
                        ? `<i class="fa-solid fa-wrench" style="color:var(--accent-blue);"></i> Teknisi: ${item.target_name}`
                        : `<i class="fa-solid fa-user-tie" style="color:var(--accent-emerald);"></i> Pelanggan: ${item.target_name}`;
                    
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${formattedDate}</td>
                        <td style="font-weight:600; font-family:monospace; color:var(--accent-blue);">${item.receipt_no}</td>
                        <td>${targetStr}</td>
                        <td>${item.issuer_name || '-'}</td>
                        <td>${statusAccept}</td>
                        <td>
                            <a href="/receipt/${item.receipt_no}" target="_blank" class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i> Download
                            </a>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#ef4444;">Gagal memuat riwayat.</td></tr>';
                console.error(err);
            });
    }

    // Enforce focus — only refocus scan input if user is NOT interacting with batch textarea, buttons, or other interactive elements
    document.addEventListener('click', (e) => {
        const tag = e.target.tagName;
        if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA' || tag === 'OPTION' || tag === 'BUTTON') return;
        if (e.target.closest('.scanner-emulator') || e.target.closest('#panelTechIssue') || e.target.closest('#scanBatchMode')) return;
        if (activeTab === 'admin' && document.getElementById('scanSingleMode')?.style.display !== 'none') {
            issueScanInput.focus();
        }
    });

    // ==========================================
    // ADMIN ISSUE TO TECHNICIAN LOGIC
    // ==========================================
    const issueDraftSns = new Set();
    const issueTableBody = document.getElementById('issueTableBody');
    const issueEmptyPlaceholder = document.getElementById('issueEmptyPlaceholder');
    const issueCountSpan = document.getElementById('issueCount');
    const btnAssignTech = document.getElementById('btnAssignTech');
    const issueAlert = document.getElementById('issueAlert');
    const issueAlertText = document.getElementById('issueAlertText');

    issueScanInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const sn = this.value.trim();
            // Tutup suggestion box
            if (snSuggestionBox) { snSuggestionBox.style.display = 'none'; snSuggestionBox.innerHTML = ''; }
            if (sn) {
                processIssueScan(sn);
            }
            this.value = '';
            issueScanInput.focus();
        }
    });

    function processIssueScan(sn, silent = false) {
        if (!silent) issueAlert.style.display = 'none';

        // Pre-resolve SN from local devices list to check for duplicates correctly (especially if scanned via IMEI)
        let resolvedSn = sn;
        if (typeof inStockDevices !== 'undefined' && Array.isArray(inStockDevices)) {
            const localDev = inStockDevices.find(d => 
                (d.serial_number && String(d.serial_number).trim().toUpperCase() === String(sn).trim().toUpperCase()) ||
                (d.imei && String(d.imei).trim().toUpperCase() === String(sn).trim().toUpperCase())
            );
            if (localDev) {
                resolvedSn = localDev.serial_number;
            }
        }

        if (issueDraftSns.has(resolvedSn)) {
            if (!silent) triggerAlert("Device SN " + resolvedSn + " sudah dimasukkan ke daftar issue.");
            return Promise.resolve({ success: false, error: "Sudah dimasukkan ke daftar issue." });
        }

        const warehouse = document.getElementById('warehouse_select')?.value || '';
        const sourceType = document.getElementById('source_type_input')?.value || 'warehouse';
        const sourceTech = document.getElementById('source_tech_input')?.value || '';

        if (sourceType === 'warehouse' && !warehouse) {
            if (!silent) triggerAlert("Pilih Gudang Asal terlebih dahulu sebelum scan device.");
            return Promise.resolve({ success: false, error: "Pilih Gudang Asal terlebih dahulu." });
        }
        if (sourceType === 'technician' && !sourceTech) {
            if (!silent) triggerAlert("Pilih Teknisi Asal terlebih dahulu sebelum scan device.");
            return Promise.resolve({ success: false, error: "Pilih Teknisi Asal terlebih dahulu." });
        }

        const params = new URLSearchParams({ q: sn, warehouse, source_type: sourceType });
        if (sourceType === 'technician' && sourceTech) params.append('source_tech', sourceTech);

        // Fetch device details from DB
        return fetch(`1?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(matches => {
            const matchDev = matches.find(d => 
                (d.serial_number && String(d.serial_number).trim().toUpperCase() === String(sn).trim().toUpperCase()) ||
                (d.imei && String(d.imei).trim().toUpperCase() === String(sn).trim().toUpperCase())
            );
            if (!matchDev) {
                if (!silent) {
                    const errMsg = (sourceType === 'technician') 
                        ? `Device SN ${sn} tidak ditemukan pada penguasaan teknisi asal.`
                        : `Device SN ${sn} tidak ditemukan di stock gudang asal terpilih.`;
                    triggerAlert(errMsg);
                }
                return { success: false, error: "Tidak ditemukan di database/stok." };
            }

            if (issueDraftSns.has(matchDev.serial_number)) {
                return { success: false, error: "Sudah dimasukkan ke daftar issue." };
            }

            issueDraftSns.add(matchDev.serial_number);
            if (issueEmptyPlaceholder) issueEmptyPlaceholder.style.display = 'none';
            if (!silent && window.playBeep) window.playBeep('success');

            const cond = (matchDev.unit_condition === 'BEKAS') ? 'BEKAS' : 'BARU';
            const condCls = cond === 'BEKAS' ? 'badge-warning' : 'badge-success';

            const newRow = document.createElement('tr');
            newRow.id = `issue-row-${matchDev.serial_number}`;
            newRow.className = 'animate-fade-in row-added';
            const rackDisplay = activeRack
                ? `<span class="badge badge-info" style="font-size:11px;">${activeRack}</span>`
                : `<span style="color:var(--text-muted); font-size:11px;">-</span>`;
            newRow.innerHTML = `
                <td>${issueDraftSns.size}</td>
                <td style="font-weight:600; color:var(--accent-blue);">
                    <i class="fa-solid fa-circle-check" style="color:var(--accent-emerald); margin-right:6px;" title="Sudah masuk daftar serah terima"></i>${matchDev.serial_number}
                    <input type="hidden" name="sns[]" value="${matchDev.serial_number}">
                </td>
                <td><span class="badge badge-info">${matchDev.type ?? '-'}</span> <span class="badge ${condCls}">${cond}</span></td>
                <td><span class="badge badge-success">${matchDev.status}</span></td>
                <td>${rackDisplay}<input type="hidden" name="rack_origins[${matchDev.serial_number}]" value="${activeRack}"></td>
                <td>
                    <input type="text" name="vehicle_plates[${matchDev.serial_number}]" class="form-control" placeholder="B 1234 CD" style="font-size: 12px; height: 30px; width: 120px;">
                </td>
                <td style="text-align: right;">
                    <button type="button" class="btn btn-danger btn-icon-sm" onclick="removeIssueRow('${matchDev.serial_number}')" style="padding:4px 8px; font-size:11px;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            `;
            issueTableBody.appendChild(newRow);
            issueCountSpan.innerText = issueDraftSns.size;
            updateSubmitState();
            buildSimPairing();
            return { success: true };
        })
        .catch(err => {
            console.error('processIssueScan fetch error:', err);
            if (!silent) triggerAlert('Terjadi kesalahan koneksi. Coba scan ulang.');
            return { success: false, error: 'Kesalahan koneksi.' };
        });
    }

    function removeIssueRow(sn) {
        const row = document.getElementById(`issue-row-${sn}`);
        if (row) row.remove();
        issueDraftSns.delete(sn);
        issueCountSpan.innerText = issueDraftSns.size;
        if (issueDraftSns.size === 0) {
            if (issueEmptyPlaceholder) issueEmptyPlaceholder.style.display = 'table-row';
        }
        // Re-index remaining rows
        Array.from(issueTableBody.querySelectorAll('tr:not(#issueEmptyPlaceholder)')).forEach((tr, i) => {
            tr.cells[0].innerText = i + 1;
        });
        updateSubmitState();
        buildSimPairing();
    }

    // Check if any accessory has qty > 0
    function hasAccessoryInput() {
        const qtyInputs = document.querySelectorAll('input[name="acc_qtys[]"]');
        for (const input of qtyInputs) {
            if (parseInt(input.value) > 0) return true;
        }
        return false;
    }

    // Unified submit button state: enable if device OR accessory OR GSM exists
    function updateSubmitState() {
        const hasDevices = issueDraftSns.size > 0;
        const hasAcc = hasAccessoryInput();
        const simCount = document.querySelectorAll('.issue-sim-check:checked').length;
        const hasSim = simCount > 0;
        btnAssignTech.disabled = !(hasDevices || hasAcc || hasSim);

        // Ringkasan serah terima di panel sticky
        let accUnits = 0;
        document.querySelectorAll('input[name="acc_qtys[]"]').forEach(i => { accUnits += parseInt(i.value || 0) || 0; });
        const sumD = document.getElementById('sumDevices');
        const sumA = document.getElementById('sumAcc');
        const sumS = document.getElementById('sumSim');
        if (sumD) sumD.innerText = issueDraftSns.size;
        if (sumA) sumA.innerText = accUnits;
        if (sumS) sumS.innerText = simCount;
    }

    // Listen for accessory qty changes
    document.querySelectorAll('input[name="acc_qtys[]"]').forEach(input => {
        input.addEventListener('input', updateSubmitState);
        input.addEventListener('change', updateSubmitState);
    });

    // Validate before submit: if only accessories, warehouse must be selected
    document.getElementById('issueForm').addEventListener('submit', function(e) {
        const hasDevices = issueDraftSns.size > 0;
        const hasAcc = hasAccessoryInput();
        const hasSim = document.querySelectorAll('.issue-sim-check:checked').length > 0;
        const warehouseSelect = document.getElementById('warehouse_select');

        if (!warehouseSelect || !warehouseSelect.value) {
            e.preventDefault();
            triggerAlert('Gudang Asal wajib dipilih.');
            if (warehouseSelect) warehouseSelect.focus();
            return false;
        }

        if (!hasDevices && !hasAcc && !hasSim) {
            e.preventDefault();
            triggerAlert('Harus ada minimal 1 perangkat, aksesoris, atau kartu GSM yang diserahkan.');
            return false;
        }
    });

    // Quick set accessory qty from AI suggestion pills
    function quickSetAccQty(accCode, qty) {
        const input = document.getElementById('issue_acc_' + accCode);
        if (input) {
            input.value = parseInt(input.value || 0) + qty;
            if (window.playBeep) window.playBeep('success');
            updateSubmitState();
        }
    }

    function triggerAlert(msg) {
        if (window.playBeep) window.playBeep('error');
        issueAlertText.innerText = msg;
        issueAlert.style.display = 'flex';
        issueAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ==========================================
    // SIM PAIRING + STOK AKSESORIS PER GUDANG
    // ==========================================
    function currentWarehouse() {
        return warehouseSelect ? warehouseSelect.value : '';
    }

    // Bangun baris pairing SIM per device (SIM difilter sesuai gudang asal).
    function buildSimPairing() {
        const card = document.getElementById('simPairingCard');
        const container = document.getElementById('simPairingRows');
        if (!card || !container) return;

        if (issueDraftSns.size === 0) {
            card.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        const wh = currentWarehouse();
        const sims = availableSimcards.filter(s => s.warehouse_code === wh);

        card.style.display = 'block';
        container.innerHTML = '';

        issueDraftSns.forEach(sn => {
            const dev = inStockDevices.find(d => d.serial_number === sn);
            let options = '<option value="">-- Tanpa SIM --</option>';
            sims.forEach(s => {
                options += `<option value="${s.msisdn}">${s.msisdn} (${s.provider})</option>`;
            });
            const row = document.createElement('div');
            row.style.cssText = 'display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid var(--border-color);';
            row.innerHTML = `
                <div style="flex:1;">
                    <span style="font-size:12px; font-weight:600; color:var(--accent-blue);">${sn}</span>
                    <span style="font-size:11px; color:var(--text-secondary); display:block;">${dev ? dev.type : 'Device'}</span>
                </div>
                <select name="sim_pairings[${sn}]" class="form-control" style="max-width:240px; font-size:12px;">${options}</select>
            `;
            container.appendChild(row);
        });

        if (sims.length === 0) {
            const note = document.createElement('div');
            note.style.cssText = 'font-size:11px; color:var(--text-muted); padding-top:8px;';
            note.innerText = 'Tidak ada kartu SIM IN_STOCK di gudang ini.';
            container.appendChild(note);
        }
    }

    // Perbarui tampilan stok + batas qty aksesoris sesuai gudang asal.
    function updateAccessoryStocks() {
        const stockMap = warehouseAccessories[currentWarehouse()] || {};
        document.querySelectorAll('.acc-stock-val').forEach(el => {
            const qty = stockMap[el.dataset.acc] || 0;
            el.innerText = qty;
            // Warna badge sesuai stok
            el.className = qty > 0 ? 'badge badge-success acc-stock-val' : 'badge badge-warning acc-stock-val';
        });
        document.querySelectorAll('.acc-qty-issue').forEach(input => {
            const max = parseInt(stockMap[input.dataset.acc] || 0);
            input.max = max;
            if (parseInt(input.value || 0) > max) input.value = max;
            input.disabled = max <= 0; // nonaktifkan jika stok 0
        });
        updateSubmitState();
    }

    // Bangun baris tabel aksesori dari data warehouseAccessories.
    function buildAccessoryRows() {
        const tbody = document.getElementById('accIssueTableBody');
        const emptyRow = document.getElementById('accIssueEmpty');
        if (!tbody) return;

        // Kumpulkan semua kode aksesori unik dari semua gudang
        const allAccCodes = new Set();
        Object.values(warehouseAccessories).forEach(wh => {
            Object.keys(wh).forEach(code => allAccCodes.add(code));
        });

        // Peta nama dari PHP data (accessories keyed by code)
        const accNames = {};
        

        // Hapus baris lama kecuali empty placeholder
        tbody.querySelectorAll('tr:not(#accIssueEmpty)').forEach(r => r.remove());

        if (allAccCodes.size === 0) {
            if (emptyRow) emptyRow.style.display = 'table-row';
            return;
        }
        if (emptyRow) emptyRow.style.display = 'none';

        allAccCodes.forEach(code => {
            const name = accNames[code] || code;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div style="font-weight:600; font-size:13px;">${name}</div>
                    <div style="font-size:11px; color:var(--text-muted); font-family:monospace;">${code}</div>
                    <input type="hidden" name="acc_types[]" value="${code}">
                    <input type="hidden" name="acc_names[]" value="${name}">
                </td>
                <td style="text-align:center;">
                    <span class="badge badge-info acc-stock-val" data-acc="${code}">0</span>
                </td>
                <td style="text-align:center;">
                    <input type="number" name="acc_qtys[]" id="issue_acc_${code}"
                        class="form-control acc-qty-issue"
                        data-acc="${code}"
                        min="0" max="0" value="0"
                        style="width:90px; text-align:center; margin:0 auto; height:34px; font-size:13px; font-weight:600;">
                </td>
            `;
            tbody.appendChild(tr);

            // Bind event listeners untuk qty changes
            const qtyInput = tr.querySelector('input[name="acc_qtys[]"]');
            if (qtyInput) {
                qtyInput.addEventListener('input', () => { updateSubmitState(); updateAccIssueBadge(); });
                qtyInput.addEventListener('change', () => { updateSubmitState(); updateAccIssueBadge(); });
            }
        });

        // Update stok sesuai gudang aktif
        updateAccessoryStocks();
    }

    // Update badge jumlah aksesori yang dipilih
    function updateAccIssueBadge() {
        let totalQty = 0;
        document.querySelectorAll('input[name="acc_qtys[]"]').forEach(i => { totalQty += parseInt(i.value || 0) || 0; });
        const badge = document.getElementById('accIssueBadge');
        if (badge) {
            badge.textContent = totalQty > 0 ? totalQty + ' dipilih' : '0 dipilih';
            if (totalQty > 0) {
                badge.style.background = 'rgba(249,115,22,0.85)';
                badge.style.color = '#fff';
                badge.style.border = '1px solid var(--accent-orange)';
            } else {
                badge.style.background = 'rgba(249,115,22,0.15)';
                badge.style.color = 'var(--accent-orange)';
                badge.style.border = '1px solid rgba(249,115,22,0.3)';
            }
        }
    }

    // Filter daftar "Serahkan Kartu GSM" (standalone) sesuai gudang asal.
    function filterIssueSim() {
        const wh = currentWarehouse();
        const search = (document.getElementById('issue_sim_search')?.value || '').trim().toLowerCase();
        let avail = 0;
        document.querySelectorAll('.issue-sim-row').forEach(row => {
            const matchWh = row.dataset.warehouse === wh;
            const matchSearch = !search || (row.dataset.search || '').includes(search);
            const show = matchWh && matchSearch;
            row.style.display = show ? '' : 'none';
            if (!show) {
                const cb = row.querySelector('.issue-sim-check');
                if (cb) cb.checked = false;
            }
            if (matchWh) avail++;
        });
        const noneRow = document.getElementById('issueSimNone');
        if (noneRow) noneRow.style.display = (avail === 0) ? 'table-row' : 'none';
        const availEl = document.getElementById('issueSimAvail');
        if (availEl) availEl.innerText = avail;
        updateIssueSimBadge();
    }

    function updateIssueSimBadge() {
        const badge = document.getElementById('issueSimSelectedBadge');
        if (badge) badge.innerText = document.querySelectorAll('.issue-sim-check:checked').length + ' dipilih';
        updateSubmitState();
    }

    const issueSimSearch = document.getElementById('issue_sim_search');
    const issueSimAll = document.getElementById('issue_sim_all');
    if (issueSimSearch) issueSimSearch.addEventListener('input', filterIssueSim);
    if (issueSimAll) {
        issueSimAll.addEventListener('change', function () {
            document.querySelectorAll('.issue-sim-row').forEach(row => {
                if (row.style.display === 'none') return;
                const cb = row.querySelector('.issue-sim-check');
                if (cb) cb.checked = this.checked;
            });
            updateIssueSimBadge();
        });
    }
    // Saat checkbox SIM manual diubah: jika dicentang → pindah ke atas tabel
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('issue-sim-check')) {
            if (e.target.checked) {
                const row = e.target.closest('tr');
                const tbody = document.getElementById('issueSimTableBody');
                if (row && tbody) tbody.prepend(row);
            }
            updateIssueSimBadge();
        }
    });

    // ==========================================
    // BARCODE SCAN UNTUK GSM (SIM CARD) & BATCH GSM
    // Parse MSISDN dari URL Telkomsel:
    // https://digipos.telkomsel.com/simcardchecking/S238X065000010795493200001085285129483
    // Ambil 12 digit terakhir (atau 11) = MSISDN
    // ==========================================
    function setSimScanMode(mode) {
        const singleBtn = document.getElementById('modeSimSingleBtn');
        const batchBtn = document.getElementById('modeSimBatchBtn');
        const singleView = document.getElementById('simSingleMode');
        const batchView = document.getElementById('simBatchMode');
        const batchInput = document.getElementById('batch_gsm_input');

        if (mode === 'single') {
            singleBtn.style.background = 'var(--accent-indigo)';
            singleBtn.style.color = '#fff';
            singleBtn.style.borderColor = 'var(--accent-indigo)';
            batchBtn.style.background = 'var(--bg-secondary)';
            batchBtn.style.color = 'var(--text-secondary)';
            batchBtn.style.borderColor = 'var(--border-color)';
            singleView.style.display = 'block';
            batchView.style.display = 'none';
            document.getElementById('issue_sim_search').focus();
        } else {
            batchBtn.style.background = 'var(--accent-indigo)';
            batchBtn.style.color = '#fff';
            batchBtn.style.borderColor = 'var(--accent-indigo)';
            singleBtn.style.background = 'var(--bg-secondary)';
            singleBtn.style.color = 'var(--text-secondary)';
            singleBtn.style.borderColor = 'var(--border-color)';
            singleView.style.display = 'none';
            batchView.style.display = 'block';
            batchInput.focus();
        }
    }

    function processBatchGsmScan() {
        const input = document.getElementById('batch_gsm_input');
        const text = input.value;
        if (!text.trim()) {
            triggerAlert('Kolom input GSM masih kosong.');
            return;
        }

        const lines = text.split('\n').map(s => s.trim()).filter(s => s.length > 0);
        let successCount = 0;
        let failCount = 0;
        let failedLines = [];

        lines.forEach(line => {
            let msisdn = line;
            if (line.includes('digipos.telkomsel.com') || line.includes('/simcardchecking/')) {
                const parts = line.split('/');
                const code = parts[parts.length - 1];
                msisdn = code.slice(-12);
            } else if (/^\d{20,}$/.test(line)) {
                msisdn = line.slice(-12);
            }

            let found = false;
            document.querySelectorAll('.issue-sim-row').forEach(row => {
                if (row.style.display === 'none') return; // only match currently visible GSMs for the active warehouse
                const td = row.querySelector('td:nth-child(2)');
                const rowMsisdn = (td ? td.textContent.trim() : '');
                if (rowMsisdn === msisdn) {
                    const cb = row.querySelector('.issue-sim-check');
                    if (cb) {
                        if (!cb.checked) {
                            cb.checked = true;
                            const tbody = document.getElementById('issueSimTableBody');
                            if (tbody) tbody.prepend(row);
                        }
                        // Highlight
                        row.style.backgroundColor = '#dcfce7';
                        row.style.transition = 'background-color 0.6s';
                        setTimeout(() => { row.style.backgroundColor = ''; }, 1200);
                        found = true;
                    }
                }
            });

            if (found) {
                successCount++;
            } else {
                failCount++;
                failedLines.push(line);
            }
        });

        updateIssueSimBadge();

        if (successCount > 0 && window.playBeep) window.playBeep('success');

        let msg = `Berhasil memproses ${successCount} GSM.`;
        if (failCount > 0) {
            msg += ` (Gagal: ${failCount} GSM tidak ditemukan / tidak sesuai gudang asal)`;
            triggerAlert(msg);
            input.value = failedLines.join('\n');
        } else {
            input.value = '';
            issueAlert.style.display = 'none';
        }
    }

    const simBarcodeInput = document.getElementById('simBarcodeInput');
    if (simBarcodeInput) {
        let simScanTimeout;
        const processSimScan = function () {
            let raw = simBarcodeInput.value.trim();
            if (!raw) return;

            // Jika berupa URL Telkomsel, ambil 12 digit terakhir
            let msisdn = raw;
            if (raw.includes('digipos.telkomsel.com') || raw.includes('/simcardchecking/')) {
                // Ambil path terakhir
                const parts = raw.split('/');
                const code = parts[parts.length - 1];
                // MSISDN = 12 digit terakhir (0812xxxxxxxx)
                msisdn = code.slice(-12);
            } else if (/^\d{20,}$/.test(raw)) {
                // Jika scan barcode fisik panjang (kode SIM), ambil 12 digit terakhir
                msisdn = raw.slice(-12);
            }

            // Cari row yang cocok di tabel GSM
            let found = false;
            document.querySelectorAll('.issue-sim-row').forEach(row => {
                if (found) return;
                const td = row.querySelector('td:nth-child(2)');
                const rowMsisdn = (td ? td.textContent.trim() : '');
                if (rowMsisdn === msisdn) {
                    const cb = row.querySelector('.issue-sim-check');
                    if (cb && !cb.checked) {
                        cb.checked = true;
                        // Pindahkan ke atas
                        const tbody = document.getElementById('issueSimTableBody');
                        if (tbody) tbody.prepend(row);
                        updateIssueSimBadge();
                    }
                    // Highlight
                    row.style.backgroundColor = '#dcfce7';
                    row.style.transition = 'background-color 0.6s';
                    setTimeout(() => { row.style.backgroundColor = ''; }, 1200);
                    found = true;
                }
            });

            if (!found) {
                simBarcodeInput.style.backgroundColor = '#fee2e2';
                setTimeout(() => { simBarcodeInput.style.backgroundColor = ''; }, 500);
            }

            simBarcodeInput.value = '';
            simBarcodeInput.focus();
        };

        simBarcodeInput.addEventListener('input', function () {
            clearTimeout(simScanTimeout);
            simScanTimeout = setTimeout(processSimScan, 300);
        });
        simBarcodeInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(simScanTimeout);
                processSimScan();
            }
        });
    }

    if (warehouseSelect) {
        warehouseSelect.addEventListener('change', () => {
            // Gudang berganti: bersihkan draft device karena stok berbeda antar gudang.
            Array.from(issueDraftSns).forEach(sn => removeIssueRow(sn));
            updateAccessoryStocks();
            buildSimPairing();
            filterIssueSim();
        });
    }

    // Inisialisasi sesuai gudang aktif saat halaman dimuat.
    buildAccessoryRows();   // Bangun baris aksesori dari data JS
    filterIssueSim();       // Filter SIM sesuai gudang

    // ==========================================
    // MOBILE VIEW EMULATION LOGIC
    // ==========================================
    const mobileTechName = document.getElementById('mobileTechName');
    const mobilePendingItems = document.getElementById('mobilePendingItems');
    const btnMobileAccept = document.getElementById('btnMobileAccept');

    function loadMobileAcceptance() {
        const pendingCountBadge = document.getElementById('pendingCountBadge');
        const acceptVerifyForm  = document.getElementById('acceptVerifyForm');
        const acceptNoPending   = document.getElementById('acceptNoPending');
        const acceptSummaryText = document.getElementById('acceptSummaryText');

        mobilePendingItems.innerHTML = `
            <div style="text-align:center; color:var(--text-muted); font-size:13px; padding:20px;">
                <i class="fa-solid fa-spinner fa-spin"></i> Memuat daftar barang...
            </div>
        `;
        if (acceptVerifyForm) acceptVerifyForm.style.display = 'none';
        if (acceptNoPending) acceptNoPending.style.display = 'none';
        if (btnMobileAccept) btnMobileAccept.disabled = true;
        if (pendingCountBadge) pendingCountBadge.textContent = 'Memuat...';

        fetch('1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(devices => {
            mobilePendingItems.innerHTML = '';

            if (!devices || devices.length === 0) {
                if (pendingCountBadge) { pendingCountBadge.textContent = '0 Pending'; pendingCountBadge.className = 'badge badge-success'; }
                if (acceptNoPending) acceptNoPending.style.display = 'block';
                if (acceptVerifyForm) acceptVerifyForm.style.display = 'none';
                return;
            }

            if (pendingCountBadge) { pendingCountBadge.textContent = devices.length + ' Menunggu Konfirmasi'; pendingCountBadge.className = 'badge badge-warning'; }

            devices.forEach(d => {
                const card = document.createElement('div');
                card.style.cssText = 'background:var(--bg-primary); border:1px solid var(--border-color); border-left:4px solid var(--accent-amber); border-radius:8px; padding:12px 14px; display:flex; justify-content:space-between; align-items:center;';
                card.innerHTML = `
                    <div>
                        <div style="font-size:13px; font-weight:700; color:var(--accent-blue);">${d.serial_number}</div>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                            <span class="badge badge-info" style="font-size:10px;">${d.type || 'Device'}</span>
                            ${d.model ? '<span style="margin-left:6px;">' + d.model + '</span>' : ''}
                        </div>
                    </div>
                    <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Pending</span>
                `;
                mobilePendingItems.appendChild(card);
            });

            // Tampilkan form verifikasi ringkasan
            if (acceptSummaryText) {
                acceptSummaryText.innerHTML = `
                    Anda akan mengkonfirmasi penerimaan <strong>${devices.length} perangkat</strong>
                    yang telah diserahkan kepada Anda.
                    Pastikan Anda sudah memeriksa seluruh perangkat secara fisik sebelum klik tombol konfirmasi.
                `;
            }
            if (acceptVerifyForm) acceptVerifyForm.style.display = 'block';
            if (btnMobileAccept) btnMobileAccept.disabled = false;
        })
        .catch(() => {
            mobilePendingItems.innerHTML = `<div style="color:#ef4444; font-size:13px; padding:10px;"><i class="fa-solid fa-triangle-exclamation"></i> Gagal memuat data. Refresh halaman.</div>`;
        });
    }

    if (btnMobileAccept) {
        btnMobileAccept.addEventListener('click', () => {
            btnMobileAccept.disabled = true;
            btnMobileAccept.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '1';
            fetch('1', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                }
            })
            .then(r => r.json())
            .then(data => {
                btnMobileAccept.innerHTML = '<i class="fa-solid fa-circle-check"></i> Berhasil Dikonfirmasi!';
                btnMobileAccept.style.background = '#22c55e';
                mobilePendingItems.innerHTML = `
                    <div style="text-align: center; padding: 20px;">
                        <i class="fa-solid fa-circle-check" style="font-size:32px; color: #22c55e; display:block; margin-bottom:8px;"></i>
                        <span style="color: #22c55e; font-size: 12px; font-weight:700;">${data.message || 'Berhasil!'}</span>
                    </div>
                `;
            })
            .catch(err => {
                btnMobileAccept.disabled = false;
                btnMobileAccept.innerHTML = '<i class="fa-solid fa-signature"></i> Tanda Tangan & Terima Fisik';
                alert('Gagal mengkonfirmasi. Silakan coba lagi.');
            });
        });
    }

    // ==========================================
    // UI/UX: collapsible optional sections + shortcut
    // ==========================================
    function makeSectionToggle(btnId, bodyId, addLabel, hideLabel) {
        const btn = document.getElementById(btnId);
        const body = document.getElementById(bodyId);
        if (!btn || !body) return;
        btn.addEventListener('click', () => {
            const show = body.style.display === 'none';
            body.style.display = show ? 'block' : 'none';
            btn.classList.toggle('open', show);
            btn.innerHTML = show
                ? '<i class="fa-solid fa-chevron-up"></i> ' + hideLabel
                : '<i class="fa-solid fa-plus"></i> ' + addLabel;
        });
    }
    // Section GSM sudah selalu tampil (tidak pakai toggle lagi)
    // makeSectionToggle('toggleSimSection', 'simSectionBody', 'Tambah Kartu GSM', 'Sembunyikan');


    // Keyboard shortcut: Ctrl/Cmd + Enter untuk Serahkan Perangkat (tanpa lepas scanner)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && activeTab === 'admin') {
            e.preventDefault();
            if (!btnAssignTech.disabled) {
                document.getElementById('issueForm').requestSubmit();
            } else {
                triggerAlert('Belum ada barang untuk diserahkan. Scan perangkat, isi aksesoris, atau pilih kartu GSM dahulu.');
            }
        }
    });

    // ==========================================
    // E-SEAL OWNERSHIP / WARRANTY PANEL LOGIC
    // ==========================================
    function hasEsealInDraft() {
        for (const sn of issueDraftSns) {
            const dev = inStockDevices.find(d => d.serial_number === sn);
            if (dev && dev.type && dev.type.toLowerCase().replace(/[-_\s]/g, '').includes('eseal')) {
                return true;
            }
        }
        return false;
    }

    function updateEsealPanel() {
        const panel = document.getElementById('esealOwnershipPanel');
        if (!panel) return;

        const isCustomer = document.querySelector('input[name="target_type"]:checked')?.value === 'customer';
        const isInstalled = document.querySelector('input[name="customer_device_status"]:checked')?.value === 'INSTALLED';
        const hasEseal = hasEsealInDraft();

        panel.style.display = (isCustomer && isInstalled && hasEseal) ? 'block' : 'none';
    }

    // Re-check panel visibility whenever target_type, customer_device_status, or device list changes
    document.querySelectorAll('input[name="target_type"]').forEach(r => r.addEventListener('change', updateEsealPanel));
    document.querySelectorAll('input[name="customer_device_status"]').forEach(r => r.addEventListener('change', updateEsealPanel));

    // Hook into processIssueScan and removeIssueRow to trigger panel update
    // IMPORTANT: processIssueScan returns a Promise — wrapper MUST return the same Promise
    const _origProcessIssueScan = processIssueScan;
    processIssueScan = function(sn, silent = false) { 
        const promise = _origProcessIssueScan(sn, silent); 
        // updateEsealPanel after the promise resolves (non-blocking)
        if (promise && typeof promise.then === 'function') {
            promise.then(() => updateEsealPanel()).catch(() => {});
        } else {
            updateEsealPanel();
        }
        return promise; 
    };
    const _origRemoveIssueRow = removeIssueRow;
    removeIssueRow = function(sn) { _origRemoveIssueRow(sn); updateEsealPanel(); };

    // ==========================================
    // EXCEL BULK IMPORT LOGIC
    // ==========================================
    function toggleExcelSection() {
        const body = document.getElementById('excelImportBody');
        const chevron = document.getElementById('excelChevron');
        if (!body || !chevron) return;
        const isHidden = body.style.display === 'none';
        body.style.display = isHidden ? 'block' : 'none';
        chevron.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
    }

    function handleExcelDrop(e) {
        e.preventDefault();
        e.currentTarget.style.borderColor = 'rgba(99,102,241,0.4)';
        e.currentTarget.style.background = '';
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleExcelFile(e.dataTransfer.files[0]);
            e.dataTransfer.clearData();
        }
    }

    function handleExcelFile(file) {
        if (!file) return;
        const resultBox = document.getElementById('excelResultBox');
        const resultContent = document.getElementById('excelResultContent');
        if (!resultBox || !resultContent) return;

        resultBox.style.display = 'block';
        resultContent.innerHTML = '<span style="color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Membaca file...</span>';

        const reader = new FileReader();
        reader.onload = async function(e) {
            try {
                const data = e.target.result;
                const workbook = XLSX.read(data, { type: 'binary' });
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                // Read as array of arrays, skipping empty rows
                const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, blankrows: false });
                
                if (!jsonData || jsonData.length === 0) {
                    resultContent.innerHTML = '<span style="color:var(--accent-rose);">File kosong atau format tidak valid.</span>';
                    return;
                }

                let successCount = 0;
                let failCount = 0;
                let logs = [];

                // Start from index 1 to skip header row
                for (let i = 1; i < jsonData.length; i++) {
                    const row = jsonData[i];
                    if (row && row.length > 0) {
                        const sn = String(row[0]).trim();
                        if (sn) {
                            const res = await processIssueScan(sn, true); // silent = true
                            if (res && res.success) {
                                successCount++;
                            } else {
                                failCount++;
                                logs.push(`<div style="color:var(--accent-rose);">[Gagal] SN ${sn} - ${res ? res.error : 'Unknown error'}</div>`);
                            }
                        }
                    }
                }

                // Render result
                let html = `<div style="margin-bottom:8px;">Berhasil: <strong style="color:#22c55e;">${successCount}</strong>, Gagal: <strong style="color:var(--accent-rose);">${failCount}</strong></div>`;
                if (logs.length > 0) {
                    html += `<div style="max-height:100px; overflow-y:auto; background:var(--bg-secondary); padding:8px; border-radius:6px; font-family:monospace; font-size:11px;">${logs.join('')}</div>`;
                }
                resultContent.innerHTML = html;

            } catch (err) {
                console.error(err);
                resultContent.innerHTML = '<span style="color:var(--accent-rose);">Gagal membaca file Excel. Pastikan format valid.</span>';
            }
        };
        reader.readAsBinaryString(file);
        
        // Reset file input
        const fileInput = document.getElementById('excelFileInput');
        if (fileInput) fileInput.value = '';
    }


