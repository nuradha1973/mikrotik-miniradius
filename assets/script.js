/**
 * MINIRADIUS - CLIENT SIDE INTERACTIVE SCRIPTS
 */

// 1. Tailwind CSS Configuration
if (typeof tailwind !== 'undefined') {
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                },
                colors: {
                    brand: {
                        50: '#e8f5ee',
                        100: '#c6e6d3',
                        200: '#8ecead',
                        300: '#4daa7c',
                        400: '#058c4e',
                        500: '#046A38',
                        600: '#035a2f',
                        700: '#024a27',
                        800: '#013a1e',
                        900: '#012a16',
                    },
                    cream: {
                        50: '#FDFCFA',
                        100: '#F9F7F2',
                        200: '#F4F1EB',
                        300: '#E8E3D9',
                        400: '#D4CDBF',
                        500: '#B8AF9E',
                    }
                }
            }
        }
    };
}

// 2. DOMContentLoaded Events
document.addEventListener('DOMContentLoaded', () => {
    // --- Clock script ---
    const clockEl = document.getElementById('live-clock');
    function updateClock() {
        if (clockEl) {
            const now = new Date();
            clockEl.textContent = now.toLocaleTimeString('en-US', { hour12: false });
        }
    }
    if (clockEl) {
        setInterval(updateClock, 1000);
        updateClock();
    }

    // --- Responsive Drawer Toggle for Sidebar on mobile ---
    const menuBtn = document.getElementById('menu-btn');
    const sidebar = document.getElementById('sidebar');
    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('-translate-x-full');
        });
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
                sidebar.classList.add('-translate-x-full');
            }
        });
    }

    // --- User Management Form Reset ---
    const userResetBtn = document.getElementById('user-reset-btn');
    if (userResetBtn) {
        userResetBtn.addEventListener('click', () => {
            document.getElementById('user-form-title').textContent = 'Tambah User';
            document.getElementById('user-action').value = 'add_user';
            document.getElementById('user-old-username').value = '';
            document.getElementById('user-username').value = '';
            document.getElementById('user-password').value = '';
            document.getElementById('user-profile').value = '';
            userResetBtn.classList.add('hidden');
        });
    }

    // --- Profile Management Form Reset ---
    const profileResetBtn = document.getElementById('profile-reset-btn');
    if (profileResetBtn) {
        profileResetBtn.addEventListener('click', () => {
            document.getElementById('profile-form-title').textContent = 'Tambah Profile Kecepatan';
            document.getElementById('profile-action').value = 'add_profile';
            document.getElementById('profile-old-groupname').value = '';
            const serviceEl = document.getElementById('profile-service');
            if (serviceEl) serviceEl.value = 'PPPoE';
            document.getElementById('profile-groupname').value = '';
            document.getElementById('profile-rate-limit').value = '';
            profileResetBtn.classList.add('hidden');
        });
    }

    // --- Auto-scroll to bottom on System Log page load ---
    scrollToBottom();

    // --- Apply vertical scroll on all tables when row count exceeds 15 ---
    document.querySelectorAll('[data-scrollable-table]').forEach(wrapper => {
        const tableId = wrapper.dataset.scrollableTable;
        const table = document.getElementById(tableId);
        if (!table) return;

        const rowCount = table.querySelectorAll('tbody tr').length;
        if (rowCount > 15) {
            wrapper.style.maxHeight = '600px';
            wrapper.style.overflowY = 'auto';
        }
    });

    // --- Add scroll indicators to scrollable tables ---
    document.querySelectorAll('[data-scrollable-table]').forEach(wrapper => {
        const indicator = wrapper.querySelector('.scroll-indicator-down');
        if (!indicator) return;

        function updateScrollIndicator() {
            const isAtBottom = wrapper.scrollHeight - wrapper.scrollTop - wrapper.clientHeight < 5;
            if (isAtBottom) {
                indicator.style.opacity = '0';
            } else {
                indicator.style.opacity = '1';
            }
        }

        wrapper.addEventListener('scroll', updateScrollIndicator);
        // Initial check
        setTimeout(updateScrollIndicator, 100);
    });

    // --- Live Traffic Graph drawing (Dashboard Only) ---
    const canvas = document.getElementById('trafficChart');
    if (canvas && typeof Chart !== 'undefined') {
        const ctx = canvas.getContext('2d');
        const totalPoints = 15;
        const labels = Array(totalPoints).fill('');
        const dlData = Array(totalPoints).fill(0);
        const ulData = Array(totalPoints).fill(0);

        const dlGradient = ctx.createLinearGradient(0, 0, 0, 250);
        dlGradient.addColorStop(0, 'rgba(4, 106, 56, 0.25)');
        dlGradient.addColorStop(1, 'rgba(4, 106, 56, 0.0)');

        const ulGradient = ctx.createLinearGradient(0, 0, 0, 250);
        ulGradient.addColorStop(0, 'rgba(20, 184, 166, 0.25)');
        ulGradient.addColorStop(1, 'rgba(20, 184, 166, 0.0)');

        const trafficChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Download (Rx)',
                        data: dlData,
                        borderColor: '#046A38',
                        borderWidth: 2.5,
                        backgroundColor: dlGradient,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointBackgroundColor: '#046A38',
                        borderCapStyle: 'round'
                    },
                    {
                        label: 'Upload (Tx)',
                        data: ulData,
                        borderColor: '#14b8a6',
                        borderWidth: 2.5,
                        backgroundColor: ulGradient,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointBackgroundColor: '#14b8a6',
                        borderCapStyle: 'round'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: '#ffffff',
                        titleColor: '#046A38',
                        bodyColor: '#1e293b',
                        borderColor: '#e8e3d9',
                        borderWidth: 1,
                        padding: 10,
                        bodyFont: { family: 'Plus Jakarta Sans', size: 11 },
                        titleFont: { family: 'Plus Jakarta Sans', size: 10 },
                        callbacks: {
                            label: function(context) {
                                return ` ${context.dataset.label}: ${context.raw} Mbps`;
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { display: false } },
                    y: {
                        border: { dash: [5, 5] },
                        grid: { color: '#e8e3d9' },
                        ticks: {
                            color: '#64748b',
                            font: { family: 'Plus Jakarta Sans', size: 10 },
                            callback: function(value) { return value + ' Mb'; }
                        }
                    }
                }
            }
        });

        function fetchLiveStats() {
            fetch('?action=traffic')
                .then(r => r.json())
                .then(data => {
                    const dlSpeedEl = document.getElementById('speed-dl');
                    const ulSpeedEl = document.getElementById('speed-ul');
                    if (dlSpeedEl) dlSpeedEl.textContent = data.download + ' Mbps';
                    if (ulSpeedEl) ulSpeedEl.textContent = data.upload + ' Mbps';

                    const cpuTxtEl = document.getElementById('gauge-cpu-txt');
                    const cpuBarEl = document.getElementById('gauge-cpu-bar');
                    if (cpuTxtEl) cpuTxtEl.textContent = data.cpu + '%';
                    if (cpuBarEl) cpuBarEl.style.width = data.cpu + '%';

                    const tempTxtEl = document.getElementById('gauge-temp-txt');
                    if (tempTxtEl) tempTxtEl.textContent = data.temp;

                    // Memory Stats Update
                    const memTxtEl = document.getElementById('gauge-mem-txt');
                    const memBarEl = document.getElementById('gauge-mem-bar');
                    if (memTxtEl && data.free_mem && data.total_mem) {
                        memTxtEl.textContent = data.free_mem + ' MB / ' + data.total_mem + ' MB';
                    }
                    if (memBarEl && data.mem_pct) {
                        memBarEl.style.width = data.mem_pct + '%';
                    }

                    // Uptime Stats Update
                    const uptimeTxtEl = document.getElementById('gauge-uptime-txt');
                    if (uptimeTxtEl && data.uptime) {
                        uptimeTxtEl.textContent = data.uptime;
                    }

                    trafficChart.data.labels.push(data.timestamp);
                    trafficChart.data.labels.shift();
                    trafficChart.data.datasets[0].data.push(data.download);
                    trafficChart.data.datasets[0].data.shift();
                    trafficChart.data.datasets[1].data.push(data.upload);
                    trafficChart.data.datasets[1].data.shift();

                    trafficChart.update('none');
                })
                .catch(e => console.error('Traffic API error:', e));
        }
        setInterval(fetchLiveStats, 2000);
        fetchLiveStats();
    }
});

// 3. Global Callback functions for lists (used in inline event handlers)
function editUser(username, password, profile) {
    document.getElementById('user-form-title').textContent = 'Edit User: ' + username;
    document.getElementById('user-action').value = 'edit_user';
    document.getElementById('user-old-username').value = username;
    document.getElementById('user-username').value = username;
    document.getElementById('user-password').value = password;
    document.getElementById('user-profile').value = profile;
    
    document.getElementById('user-reset-btn').classList.remove('hidden');
}

function editProfile(groupname, rateLimit) {
    document.getElementById('profile-form-title').textContent = 'Edit Profile: ' + groupname;
    document.getElementById('profile-action').value = 'edit_profile';
    document.getElementById('profile-old-groupname').value = groupname;
    
    // Extract service and base name
    let service = 'PPPoE';
    let baseName = groupname;
    if (groupname.toUpperCase().startsWith('PPPOE_')) {
        service = 'PPPoE';
        baseName = groupname.substring(6);
    } else if (groupname.toUpperCase().startsWith('HOTSPOT_')) {
        service = 'Hotspot';
        baseName = groupname.substring(8);
    }
    
    const serviceEl = document.getElementById('profile-service');
    if (serviceEl) serviceEl.value = service;
    
    document.getElementById('profile-groupname').value = baseName;
    document.getElementById('profile-rate-limit').value = rateLimit;
    
    document.getElementById('profile-reset-btn').classList.remove('hidden');
    document.getElementById('profile-form-title').scrollIntoView({ behavior: 'smooth' });
}

function scrollToBottom() {
    const consoleContainer = document.getElementById('log-console');
    if (consoleContainer) {
        consoleContainer.scrollTop = consoleContainer.scrollHeight;
    }
}

function filterLogs(query) {
    const lines = document.querySelectorAll('.log-line');
    const term = query.toLowerCase().trim();
    let visible = 0;
    lines.forEach(line => {
        const text = line.getAttribute('data-text') || '';
        if (!term || text.includes(term)) {
            line.style.display = '';
            visible++;
        } else {
            line.style.display = 'none';
        }
    });
    const countEl = document.getElementById('log-visible-count');
    if (countEl) {
        countEl.textContent = visible + ' entries' + (term ? ' (filtered)' : '');
    }
}

function filterTable(query, tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const term = query.toLowerCase().trim();
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = !term || text.includes(term) ? '' : 'none';
    });
}

function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';

    if (!button) return;
    const eyeOpen = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>';
    const eyeClosed = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.269-2.943-9.543-7a9.967 9.967 0 012.55-4.374m1.66-1.63A9.93 9.93 0 0112 5c4.478 0 8.269 2.943 9.543 7a9.974 9.974 0 01-4.571 5.123M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18"></path></svg>';
    button.innerHTML = isHidden ? eyeClosed : eyeOpen;
    button.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
}

function togglePasswordRow(button) {
    if (!button) return;
    const container = button.closest('[data-password-row]');
    if (!container) return;

    const textEl = container.querySelector('.user-password-text');
    const password = container.dataset.password || '';
    const hidden = button.dataset.hidden === 'true';

    const eyeOpen = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>';
    const eyeClosed = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.269-2.943-9.543-7a9.967 9.967 0 012.55-4.374m1.66-1.63A9.93 9.93 0 0112 5c4.478 0 8.269 2.943 9.543 7a9.974 9.974 0 01-4.571 5.123M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18"></path></svg>';

    if (hidden) {
        textEl.textContent = password;
        button.dataset.hidden = 'false';
        button.innerHTML = eyeClosed;
        button.setAttribute('aria-label', 'Sembunyikan password');
    } else {
        textEl.textContent = '*'.repeat(password.length);
        button.dataset.hidden = 'true';
        button.innerHTML = eyeOpen;
        button.setAttribute('aria-label', 'Tampilkan password');
    }
}

/**
 * Show a floating notification at the top-right corner.
 * @param {string} message - The message text.
 * @param {string} type - 'success' or 'error'.
 */
window.showNotification = function(message, type = 'success') {
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.className = 'fixed top-5 right-5 z-[9999] flex flex-col gap-3 w-full max-w-sm pointer-events-none';
        document.body.appendChild(container);
    }
    
    // Create card element
    const card = document.createElement('div');
    card.className = `notification-card pointer-events-auto flex items-start gap-3 w-full bg-white/95 backdrop-blur border border-slate-100/90 rounded-2xl p-4 shadow-xl select-none`;
    
    let iconSvg = '';
    let borderAccent = '';
    
    if (type === 'success') {
        borderAccent = 'border-l-4 border-l-emerald-500';
        iconSvg = `
            <div class="bg-emerald-50 p-2 rounded-xl text-emerald-600 shrink-0 shadow-inner">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        `;
    } else {
        borderAccent = 'border-l-4 border-l-rose-500';
        iconSvg = `
            <div class="bg-rose-50 p-2 rounded-xl text-rose-600 shrink-0 shadow-inner">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        `;
    }
    
    card.className += ` ${borderAccent}`;
    
    card.innerHTML = `
        ${iconSvg}
        <div class="flex-1 pt-0.5">
            <p class="text-sm font-bold text-slate-800">${type === 'success' ? 'Berhasil' : 'Kesalahan'}</p>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">${message}</p>
        </div>
        <button class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-50 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    
    // Add close action
    const closeBtn = card.querySelector('button');
    let autoDismissTimeout;
    
    const dismiss = () => {
        card.classList.remove('show');
        card.classList.add('hide');
        setTimeout(() => {
            card.remove();
        }, 400);
    };
    
    closeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        clearTimeout(autoDismissTimeout);
        dismiss();
    });
    
    // Add to DOM
    container.appendChild(card);
    
    // Animate in
    setTimeout(() => {
        card.classList.add('show');
    }, 20);
    
    // Auto dismiss after 5 seconds
    autoDismissTimeout = setTimeout(dismiss, 5000);
    
    // Hover controls for auto-dismiss (pause on hover)
    card.addEventListener('mouseenter', () => {
        clearTimeout(autoDismissTimeout);
    });
    
    card.addEventListener('mouseleave', () => {
        autoDismissTimeout = setTimeout(dismiss, 3000);
    });
};

// Themed confirmation modal logic
let _confirmTargetForm = null;
function ensureConfirmModal() {
    if (document.getElementById('confirm-modal')) return;
    const modalHtml = `
    <div id="confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full mx-4">
            <div class="p-5">
                <h4 class="text-sm font-bold text-slate-800" id="confirm-modal-title">Konfirmasi</h4>
                <p class="text-xs text-slate-600 mt-2" id="confirm-modal-message">Apakah Anda yakin?</p>
            </div>
            <div class="flex justify-end gap-3 p-4 border-t border-cream-200">
                <button id="confirm-modal-cancel" class="px-4 py-2 rounded-xl bg-cream-100 text-slate-700 hover:bg-cream-200 transition-all">Batal</button>
                <button id="confirm-modal-confirm" class="px-4 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-500 transition-all">Ya, Hapus</button>
            </div>
        </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.getElementById('confirm-modal-cancel').addEventListener('click', hideConfirmModal);
    document.getElementById('confirm-modal-confirm').addEventListener('click', () => {
        if (_confirmTargetForm) {
            // submit the form programmatically
            _confirmTargetForm.submit();
            _confirmTargetForm = null;
        }
        hideConfirmModal();
    });
}

function showConfirm(formEl, message) {
    ensureConfirmModal();
    _confirmTargetForm = formEl;
    const modal = document.getElementById('confirm-modal');
    const msgEl = document.getElementById('confirm-modal-message');
    if (msgEl) msgEl.textContent = message || 'Apakah Anda yakin?';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function hideConfirmModal() {
    const modal = document.getElementById('confirm-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    _confirmTargetForm = null;
}

