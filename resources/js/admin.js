// Admin dashboard JavaScript – uses session auth, fetches from Laravel API
// Enhanced with comprehensive error handling and user-friendly notifications

const API_BASE = window.Laravel?.apiBase || '/api';
const csrfToken = window.Laravel?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;
const IS_ADMIN_MAP_PAGE = window.location.pathname === '/admin/map';

// Global error state tracker
let hasErrorOccurred = false;

/**
 * Enhanced API fetch with comprehensive error handling
 * @param {string} endpoint - API endpoint
 * @param {Object} options - Fetch options
 * @returns {Promise<Object>} JSON response
 */
async function apiFetch(endpoint, options = {}) {
    const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        ...options.headers
    };

    try {
        const res = await fetch(API_BASE + endpoint, {
            ...options,
            headers,
            credentials: 'include'
        });

        // Handle authentication errors
        if (res.status === 401 || res.status === 403) {
            if (window.showErrorModal) {
                window.showErrorModal('Session Expired', 'Your session has expired. Please login again.');
            } else {
                alert('Your session has expired. Please login again.');
            }
            setTimeout(() => {
                window.location.href = '/admin/login';
            }, 2000);
            throw new Error('Unauthenticated');
        }

        // Handle not found
        if (res.status === 404) {
            const error = new Error('The requested resource was not found');
            error.status = 404;
            throw error;
        }

        // Handle rate limiting
        if (res.status === 429) {
            const error = new Error('Too many requests. Please slow down and try again later.');
            error.status = 429;
            throw error;
        }

        // Handle server errors (500, 502, 503, etc.)
        if (res.status >= 500) {
            const error = new Error('A server error occurred. Please try again later.');
            error.status = res.status;
            throw error;
        }

        // Parse JSON response
        let data;
        try {
            data = await res.json();
        } catch (e) {
            const error = new Error('Invalid response from server');
            error.status = res.status;
            throw error;
        }

        // Check if response indicates failure
        if (!res.ok) {
            const error = new Error(data.message || data.error || `Request failed with status ${res.status}`);
            error.status = res.status;
            error.data = data;
            throw error;
        }

        return data;

    } catch (error) {
        // Network errors
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            error.message = 'Network error. Please check your internet connection.';
        }

        // Log error for debugging
        console.error(`API Error (${endpoint}):`, error);

        // Show user-friendly error if not already shown
        if (!hasErrorOccurred && window.showErrorModal) {
            hasErrorOccurred = true;
            window.showErrorModal('Error', error.message || 'An unexpected error occurred');
            // Reset error flag after 5 seconds to allow future errors
            setTimeout(() => { hasErrorOccurred = false; }, 5000);
        }

        throw error;
    }
}

/**
 * Safe API fetch that returns default value on error instead of throwing
 * @param {string} endpoint - API endpoint
 * @param {Object} defaultValue - Default value to return on error
 * @param {Object} options - Fetch options
 * @returns {Promise<Object>} JSON response or default value
 */
async function safeApiFetch(endpoint, defaultValue = { success: false, data: [] }, options = {}) {
    try {
        return await apiFetch(endpoint, options);
    } catch (error) {
        console.warn(`Safe fetch failed for ${endpoint}, returning default:`, error.message);
        return defaultValue;
    }
}

// Global state
let shopsData = [], allRentals = [], customersData = [], vehiclesData = [];
let currentShopId = null;
let map, shopMarkersLayer;

/**
 * Load all dashboard data with error handling for each request
 */
async function loadDashboardData() {
    try {
        // Load users (shops)
        const usersRes = await safeApiFetch('/admin/users', { success: false, data: [] });
        if (usersRes.success) {
            shopsData = usersRes.data.filter(u => u.role === 'user');
        } else {
            console.warn('Failed to load shops data');
            if (window.showToast) {
                window.showToast('warning', 'Could not load shops data');
            }
        }

        // Load rentals
        const rentalsRes = await safeApiFetch('/admin/rentals', { success: false, data: [] });
        if (rentalsRes.success) {
            allRentals = rentalsRes.data;
        } else {
            console.warn('Failed to load rentals data');
        }

        // Load customers
        const custRes = await safeApiFetch('/customers?per_page=200', { success: false, data: { data: [] } });
        if (custRes.success) {
            customersData = custRes.data.data || custRes.data || [];
        } else {
            console.warn('Failed to load customers data');
        }

        // Load vehicles
        const vehRes = await safeApiFetch('/admin/vehicles', { success: false, data: [] });
        if (vehRes.success) {
            vehiclesData = vehRes.data;
        } else {
            console.warn('Failed to load vehicles data');
        }

        // Render all components
        renderAll();

        // Update shop count if element exists
        const shopCountEl = document.getElementById('shopCount');
        if (shopCountEl) {
            shopCountEl.innerText = shopsData.length;
        }

        // Show success toast
        if (window.showToast) {
            window.showToast('success', 'Dashboard data loaded successfully');
        }

    } catch (e) {
        console.error('Dashboard data loading error:', e);
        if (window.showErrorModal) {
            window.showErrorModal('Error', 'Failed to load dashboard data. Please refresh the page.');
        }
    }
}

function computeShopStats(shopId) {
    const shopRentals = allRentals.filter(r => r.user && r.user.id == shopId && r.verification_completed_at);
    const newCust = shopRentals.filter(r => !r.is_verification_cached).length;
    const repeatVerifs = shopRentals.filter(r => r.is_verification_cached).length;
    let feb=0, mar=0;
    shopRentals.forEach(r => {
        const d = new Date(r.created_at);
        if (d.getFullYear()===2026 && d.getMonth()===1) feb++;
        if (d.getFullYear()===6 && d.getMonth()===2) mar++;
    });
    return { newCust, repeatVerifs, feb, mar, income: newCust*1 + repeatVerifs*3 };
}

function renderShopList() {
    const container = document.getElementById('shopListEl');
    if (!container) return;

    if (shopsData.length === 0) {
        container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-3);">No shops found</div>';
        return;
    }

    container.innerHTML = shopsData.map(shop => {
        const stats = computeShopStats(shop.id);
        return `<div class="sli" data-shop-id="${shop.id}" style="display:flex;align-items:center;gap:9px;padding:8px;cursor:pointer;">
            <div class="ibox ibox-sm"><i class="fas fa-store-alt"></i></div>
            <div class="sli-info"><div class="sli-name">${escapeHtml(shop.name)}</div><div class="sli-sub">${shop.city || 'Odisha'} · verif: ${stats.newCust+stats.repeatVerifs}</div></div>
            <span class="badge badge-green">₹${stats.income}</span>
        </div>`;
    }).join('');

    document.querySelectorAll('.sli').forEach(el => {
        el.addEventListener('click', () => {
            currentShopId = parseInt(el.dataset.shopId);
            renderShopDetail(currentShopId);
            renderShopList();
        });
    });
}

function renderShopDetail(shopId) {
    const container = document.getElementById('shopDetailPanel');
    if (!container) return;

    const shop = shopsData.find(s=>s.id==shopId);
    if(!shop) {
        container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-3);">Select a shop to view details</div>';
        return;
    }

    const stats = computeShopStats(shop.id);
    container.innerHTML = `
        <div class="dd-hero"><div class="dd-name">${escapeHtml(shop.name)}</div><div class="dd-meta">${shop.email || ''} · GST: ${shop.gst_number || 'Not added'}</div></div>
        <div class="dd-stats"><div class="dd-stat"><div class="dd-stat-v mv-accent">₹${shop.wallet_balance || 0}</div><div class="dd-stat-l">Wallet</div></div>
        <div class="dd-stat"><div class="dd-stat-v">${stats.newCust+stats.repeatVerifs}</div><div class="dd-stat-l">Verifications</div></div>
        <div class="dd-stat"><div class="dd-stat-v mv-green">₹${stats.income}</div><div class="dd-stat-l">Verif Income</div></div></div>
        <div class="dd-body"><div class="dd-section"><div class="section-label">Fleet</div><div id="shopFleet"></div></div>
        <div class="dd-section"><div class="section-label">Monthly trend</div><div>Feb: ${stats.feb} verifs, Mar: ${stats.mar} verifs</div><div class="prog-track"><div class="prog-fill" style="width:${(stats.mar/(stats.feb+1))*100}%;background:var(--green);"></div></div></div></div>
        <div class="dd-actions"><button class="btn btn-red" id="banShopBtn">Ban</button><button class="btn btn-green" id="unbanShopBtn">Unban</button></div>`;

    const fleetVehicles = vehiclesData.filter(v=> v.user_id == shopId);
    const fleetEl = document.getElementById('shopFleet');
    if (fleetEl) {
        fleetEl.innerHTML = fleetVehicles.length > 0
            ? fleetVehicles.map(v=>`<span class="chip"><i class="fas fa-car"></i>${escapeHtml(v.name)}</span>`).join('')
            : '<span style="color:var(--text-3);">No vehicles in fleet</span>';
    }
}

function updateMetrics() {
    const container = document.getElementById('metricCards');
    if (!container) return;

    const totalWallet = shopsData.reduce((s,shop)=> s+(shop.wallet_balance||0),0);
    const totalVerifs = allRentals.filter(r=>r.verification_completed_at).length;
    const totalProfit = shopsData.reduce((s,shop)=> s+computeShopStats(shop.id).income,0);

    container.innerHTML = `
        <div class="mcard"><div class="ml">Platform Wallet</div><div class="mv mv-accent">₹${totalWallet.toLocaleString()}</div></div>
        <div class="mcard"><div class="ml">Verifications</div><div class="mv">${totalVerifs}</div></div>
        <div class="mcard"><div class="ml">Net Profit</div><div class="mv mv-green">₹${totalProfit.toLocaleString()}</div></div>
        <div class="mcard"><div class="ml">Avg Growth</div><div class="mv mv-amber">+12%</div></div>`;
}

function renderTopCustomers() {
    const container = document.getElementById('dbCustList');
    if (!container) return;

    if (customersData.length === 0) {
        container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-3);">No customers found</div>';
        return;
    }

    const top = [...customersData].sort((a,b)=> (b.total_rentals||0) - (a.total_rentals||0)).slice(0,5);
    container.innerHTML = top.map(c=> `<div class="cli" style="display:flex;align-items:center;gap:9px;padding:8px;"><div class="av av-sm" style="background:rgba(79,110,247,0.15);">${(c.name||'?').slice(0,2)}</div><div class="cli-info"><div class="cli-name">${escapeHtml(c.name)}</div><div class="cli-sub">${c.city||''} · trips: ${c.total_rentals||0}</div></div></div>`).join('');
}

function renderIncomeBreakdown() {
    const container = document.getElementById('incomeBreakdown');
    if (!container) return;
    container.innerHTML = `<div class="pb"><div>1st verify cost: ₹2</div><div>Shop charge: ₹3</div><div class="mv mv-accent">Net per 1st: ₹1</div><div>Repeat (DB): ₹3 pure</div></div>`;
}

function renderFleet() {
    const container = document.getElementById('fleetGrid');
    if (!container) return;

    const filter = document.querySelector('#fleetFilter .fbtn.active')?.dataset.f || 'all';
    const filtered = vehiclesData.filter(v => filter==='all' || v.status === filter);

    if (filtered.length === 0) {
        container.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-3);">No vehicles found</div>';
        return;
    }

    container.innerHTML = filtered.map(v => `<div class="fleet-card" style="background:rgba(7,11,24,0.72);border:1px solid var(--border);border-radius:16px;padding:16px;"><div class="fc-top" style="display:flex;justify-content:space-between;"><div><div class="fc-model" style="font-weight:700;">${escapeHtml(v.name)}</div><div class="fc-plate" style="font-size:10px;color:var(--text-3);">${v.number_plate}</div></div><span class="badge badge-green">${v.status}</span></div><div class="fc-meta" style="margin-top:10px;"><div class="fc-meta-item" style="background:var(--surface);border-radius:6px;padding:7px;"><div class="fc-meta-l" style="font-size:9px;">Daily</div><div class="fc-meta-v">₹${v.daily_rate||0}</div></div></div><div class="fc-shop" style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);"><div class="fc-shop-name" style="font-size:10px;">${shopsData.find(s=>s.id==v.user_id)?.name || ''}</div></div></div>`).join('');
}

function renderCustomersTable() {
    const container = document.getElementById('custTbody');
    const countEl = document.getElementById('custCount');
    if (!container) return;

    const filter = document.querySelector('#custFilter .fbtn.active')?.dataset.f || 'all';
    let filtered = customersData;
    if(filter === 'verified') filtered = filtered.filter(c=>c.verified);
    if(filter === 'repeat') filtered = filtered.filter(c=>(c.total_rentals||0)>1);

    if (countEl) {
        countEl.innerText = filtered.length+' records';
    }

    if (filtered.length === 0) {
        container.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-3);">No customers found</td></tr>';
        return;
    }

    container.innerHTML = filtered.map(c => {
        const rentals = c.total_rentals || 0;
        const income = 1 + (rentals-1)*3;
        const verifType = rentals>1 ? 'DB repeat' : 'Cashfree';
        return `<tr><td><div class="av av-sm" style="display:inline-flex;">${(c.name||'U').slice(0,2)}</div> ${escapeHtml(c.name)}</td><td>${c.city||'--'}</td><td>${c.shop_name||'--'}</td><td>${rentals}</td><td><span class="badge badge-accent">${verifType}</span></td><td>₹${income}</td></tr>`;
    }).join('');
}

function renderWalletPage() {
    const metricsContainer = document.getElementById('walletMetrics');
    const balanceContainer = document.getElementById('shopBalanceList');
    const logContainer = document.getElementById('walletLogList');

    if (metricsContainer) {
        const totalCredits = shopsData.reduce((a,s)=>a+(s.wallet_balance||0),0);
        metricsContainer.innerHTML = `<div class="mcard"><div class="ml">Total Credits</div><div class="mv mv-green">₹${totalCredits.toLocaleString()}</div></div><div class="mcard"><div class="ml">Total Debits</div><div class="mv mv-amber">₹0</div></div><div class="mcard"><div class="ml">Platform Revenue</div><div class="mv mv-accent">₹${shopsData.reduce((a,s)=>a+computeShopStats(s.id).income,0)}</div></div>`;
    }

    if (balanceContainer) {
        if (shopsData.length === 0) {
            balanceContainer.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-3);">No shops found</div>';
        } else {
            balanceContainer.innerHTML = shopsData.map(s=> `<div style="margin-bottom:8px;"><div style="display:flex;justify-content:space-between;"><span>${escapeHtml(s.name)}</span><span>₹${s.wallet_balance || 0}</span></div><div class="prog-track"><div class="prog-fill" style="width:${Math.min((s.wallet_balance||0)/300000*100, 100)}%;background:var(--accent);"></div></div></div>`).join('');
        }
    }

    if (logContainer) {
        logContainer.innerHTML = `<div class="wlog-item"><div class="wlog-icon wl-credit"><i class="fas fa-arrow-down"></i></div><div class="wlog-info">Live transactions will appear here</div></div>`;
    }
}

function initMap() {
    if(map || !document.getElementById('leafletMap')) return;

    try {
        map = L.map('leafletMap').setView([20.5,84.5],7);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',{subdomains:'abcd'}).addTo(map);
        shopMarkersLayer = L.layerGroup().addTo(map);

        if (shopsData.length > 0) {
            shopsData.forEach(shop => {
                if(shop.lat && shop.lng) {
                    const marker = L.marker([shop.lat, shop.lng], { icon: L.divIcon({ html: `<div style="background:#4f6ef7;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid white;"><i class="fas fa-store" style="color:white;font-size:10px;"></i></div>`, iconSize:[24,24] }) }).bindPopup(`<b>${escapeHtml(shop.name)}</b><br>Wallet: ₹${shop.wallet_balance || 0}`);
                    shopMarkersLayer.addLayer(marker);
                }
            });
        }

        const fitIndiaBtn = document.getElementById('fitIndiaBtn');
        const fitOdishaBtn = document.getElementById('fitOdishaBtn');

        if (fitIndiaBtn) {
            fitIndiaBtn.onclick = () => map.setView([22,82],5);
        }
        if (fitOdishaBtn) {
            fitOdishaBtn.onclick = () => map.fitBounds([[17.8,81.4],[22.6,87.5]]);
        }
    } catch (error) {
        console.error('Map initialization error:', error);
        if (window.showToast) {
            window.showToast('error', 'Failed to initialize map');
        }
    }
}

function renderAll() {
    renderShopList();
    updateMetrics();
    if(currentShopId) renderShopDetail(currentShopId);
    renderTopCustomers();
    renderIncomeBreakdown();
    renderFleet();
    renderCustomersTable();
    renderWalletPage();
    initMap();
}

// Page switching
document.querySelectorAll('.nav-item[data-page]').forEach(el => {
    el.addEventListener('click', () => {
        document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
        const pageEl = document.getElementById(`page-${el.dataset.page}`);
        if (pageEl) pageEl.classList.add('active');

        document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
        el.classList.add('active');

        const pageTitleEl = document.getElementById('pageTitle');
        if (pageTitleEl) {
            pageTitleEl.innerText = el.dataset.page.charAt(0).toUpperCase() + el.dataset.page.slice(1);
        }

        if(el.dataset.page === 'map') setTimeout(()=> map?.invalidateSize(), 100);
        if(el.dataset.page === 'fleet') renderFleet();
        if(el.dataset.page === 'customers') renderCustomersTable();
    });
});

document.querySelectorAll('#fleetFilter .fbtn').forEach(btn=> {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#fleetFilter .fbtn').forEach(b=>b.classList.remove('active'));
        this.classList.add('active');
        renderFleet();
    });
});

document.querySelectorAll('#custFilter .fbtn').forEach(btn=> {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#custFilter .fbtn').forEach(b=>b.classList.remove('active'));
        this.classList.add('active');
        renderCustomersTable();
    });
});

function escapeHtml(str) {
    if(!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if(m === '&') return '&amp;';
        if(m === '<') return '&lt;';
        if(m === '>') return '&gt;';
        return m;
    });
}

// Global error handler for uncaught errors
window.addEventListener('error', function(event) {
    console.error('Global error caught:', event.error);
    if (window.showErrorModal && !hasErrorOccurred) {
        hasErrorOccurred = true;
        window.showErrorModal('Error', 'An unexpected error occurred. Please refresh the page if the issue persists.');
        setTimeout(() => { hasErrorOccurred = false; }, 5000);
    }
});

// Handle unhandled promise rejections
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    if (window.showErrorModal && !hasErrorOccurred) {
        hasErrorOccurred = true;
        window.showErrorModal('Error', 'A network or processing error occurred. Please try again.');
        setTimeout(() => { hasErrorOccurred = false; }, 5000);
    }
});

// The dedicated admin map page has its own Blade-driven map script and controls.
// Skip dashboard bootstrap there to avoid map/container/script conflicts.
if (!IS_ADMIN_MAP_PAGE) {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadDashboardData);
    } else {
        loadDashboardData();
    }
}

// Export functions for global access
window.apiFetch = apiFetch;
window.safeApiFetch = safeApiFetch;
window.showToast = window.showToast || function(type, message) {
    console.log(`[${type}] ${message}`);
};
