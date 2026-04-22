// Admin dashboard JavaScript – uses session auth, fetches from Laravel API

const API_BASE = window.Laravel.apiBase;
const csrfToken = window.Laravel.csrfToken;

async function apiFetch(endpoint, options = {}) {
    const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        ...options.headers
    };
    const res = await fetch(API_BASE + endpoint, {
        ...options,
        headers,
        credentials: 'include'
    });
    if (res.status === 401 || res.status === 403) {
        window.location.href = '/login';
        throw new Error('Unauthenticated');
    }
    return res.json();
}

// Global state
let shopsData = [], allRentals = [], customersData = [], vehiclesData = [];
let currentShopId = null;
let map, shopMarkersLayer;

// Load all data
async function loadDashboardData() {
    try {
        const usersRes = await apiFetch('/admin/users');
        if (usersRes.success) shopsData = usersRes.data.filter(u => u.role === 'user');
        const rentalsRes = await apiFetch('/admin/rentals');
        if (rentalsRes.success) allRentals = rentalsRes.data;
        const custRes = await apiFetch('/customers?per_page=200');
        if (custRes.success) customersData = custRes.data.data || custRes.data;
        const vehRes = await apiFetch('/admin/vehicles').catch(() => ({ success: false, data: [] }));
        if (vehRes.success) vehiclesData = vehRes.data;
        renderAll();
        document.getElementById('shopCount').innerText = shopsData.length;
    } catch(e) { console.error(e); }
}

function computeShopStats(shopId) {
    const shopRentals = allRentals.filter(r => r.user && r.user.id == shopId && r.verification_completed_at);
    const newCust = shopRentals.filter(r => !r.is_verification_cached).length;
    const repeatVerifs = shopRentals.filter(r => r.is_verification_cached).length;
    let feb=0, mar=0;
    shopRentals.forEach(r => {
        const d = new Date(r.created_at);
        if (d.getFullYear()===2026 && d.getMonth()===1) feb++;
        if (d.getFullYear()===2026 && d.getMonth()===2) mar++;
    });
    return { newCust, repeatVerifs, feb, mar, income: newCust*1 + repeatVerifs*3 };
}

function renderShopList() {
    const container = document.getElementById('shopListEl');
    container.innerHTML = shopsData.map(shop => {
        const stats = computeShopStats(shop.id);
        return `<div class="sli" data-shop-id="${shop.id}" style="display:flex;align-items:center;gap:9px;padding:8px;cursor:pointer;">
            <div class="ibox ibox-sm"><i class="fas fa-store-alt"></i></div>
            <div class="sli-info"><div class="sli-name">${escapeHtml(shop.name)}</div><div class="sli-sub">${shop.city || 'Odisha'} · verif: ${stats.newCust+stats.repeatVerifs}</div></div>
            <span class="badge badge-green">₹${stats.income}</span>
        </div>`;
    }).join('');
    document.querySelectorAll('.sli').forEach(el => el.addEventListener('click', () => { currentShopId = parseInt(el.dataset.shopId); renderShopDetail(currentShopId); renderShopList(); }));
}

function renderShopDetail(shopId) {
    const shop = shopsData.find(s=>s.id==shopId);
    if(!shop) return;
    const stats = computeShopStats(shop.id);
    document.getElementById('shopDetailPanel').innerHTML = `
        <div class="dd-hero"><div class="dd-name">${escapeHtml(shop.name)}</div><div class="dd-meta">${shop.email || ''} · GST: ${shop.gst_number || 'Not added'}</div></div>
        <div class="dd-stats"><div class="dd-stat"><div class="dd-stat-v mv-accent">₹${shop.wallet_balance}</div><div class="dd-stat-l">Wallet</div></div>
        <div class="dd-stat"><div class="dd-stat-v">${stats.newCust+stats.repeatVerifs}</div><div class="dd-stat-l">Verifications</div></div>
        <div class="dd-stat"><div class="dd-stat-v mv-green">₹${stats.income}</div><div class="dd-stat-l">Verif Income</div></div></div>
        <div class="dd-body"><div class="dd-section"><div class="section-label">Fleet</div><div id="shopFleet"></div></div>
        <div class="dd-section"><div class="section-label">Monthly trend</div><div>Feb: ${stats.feb} verifs, Mar: ${stats.mar} verifs</div><div class="prog-track"><div class="prog-fill" style="width:${(stats.mar/(stats.feb+1))*100}%;background:var(--green);"></div></div></div></div>
        <div class="dd-actions"><button class="btn btn-red" id="banShopBtn">Ban</button><button class="btn btn-green" id="unbanShopBtn">Unban</button></div>`;
    const fleetVehicles = vehiclesData.filter(v=> v.user_id == shopId);
    document.getElementById('shopFleet').innerHTML = fleetVehicles.map(v=>`<span class="chip"><i class="fas fa-car"></i>${escapeHtml(v.name)}</span>`).join('');
}

function updateMetrics() {
    const totalWallet = shopsData.reduce((s,shop)=> s+shop.wallet_balance,0);
    const totalVerifs = allRentals.filter(r=>r.verification_completed_at).length;
    const totalProfit = shopsData.reduce((s,shop)=> s+computeShopStats(shop.id).income,0);
    document.getElementById('metricCards').innerHTML = `
        <div class="mcard"><div class="ml">Platform Wallet</div><div class="mv mv-accent">₹${totalWallet.toLocaleString()}</div></div>
        <div class="mcard"><div class="ml">Verifications</div><div class="mv">${totalVerifs}</div></div>
        <div class="mcard"><div class="ml">Net Profit</div><div class="mv mv-green">₹${totalProfit.toLocaleString()}</div></div>
        <div class="mcard"><div class="ml">Avg Growth</div><div class="mv mv-amber">+12%</div></div>`;
}

function renderTopCustomers() {
    const top = [...customersData].sort((a,b)=> (b.total_rentals||0) - (a.total_rentals||0)).slice(0,5);
    document.getElementById('dbCustList').innerHTML = top.map(c=> `<div class="cli" style="display:flex;align-items:center;gap:9px;padding:8px;"><div class="av av-sm" style="background:rgba(79,110,247,0.15);">${(c.name||'?').slice(0,2)}</div><div class="cli-info"><div class="cli-name">${escapeHtml(c.name)}</div><div class="cli-sub">${c.city||''} · trips: ${c.total_rentals||0}</div></div></div>`).join('');
}

function renderIncomeBreakdown() {
    document.getElementById('incomeBreakdown').innerHTML = `<div class="pb"><div>1st verify cost: ₹2</div><div>Shop charge: ₹3</div><div class="mv mv-accent">Net per 1st: ₹1</div><div>Repeat (DB): ₹3 pure</div></div>`;
}

function renderFleet() {
    const filter = document.querySelector('#fleetFilter .fbtn.active')?.dataset.f || 'all';
    const filtered = vehiclesData.filter(v => filter==='all' || v.status === filter);
    document.getElementById('fleetGrid').innerHTML = filtered.map(v => `<div class="fleet-card" style="background:rgba(7,11,24,0.72);border:1px solid var(--border);border-radius:16px;padding:16px;"><div class="fc-top" style="display:flex;justify-content:space-between;"><div><div class="fc-model" style="font-weight:700;">${escapeHtml(v.name)}</div><div class="fc-plate" style="font-size:10px;color:var(--text-3);">${v.number_plate}</div></div><span class="badge badge-green">${v.status}</span></div><div class="fc-meta" style="margin-top:10px;"><div class="fc-meta-item" style="background:var(--surface);border-radius:6px;padding:7px;"><div class="fc-meta-l" style="font-size:9px;">Daily</div><div class="fc-meta-v">₹${v.daily_rate||0}</div></div></div><div class="fc-shop" style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);"><div class="fc-shop-name" style="font-size:10px;">${shopsData.find(s=>s.id==v.user_id)?.name || ''}</div></div></div>`).join('');
}

function renderCustomersTable() {
    const filter = document.querySelector('#custFilter .fbtn.active')?.dataset.f || 'all';
    let filtered = customersData;
    if(filter === 'verified') filtered = filtered.filter(c=>c.verified);
    if(filter === 'repeat') filtered = filtered.filter(c=>(c.total_rentals||0)>1);
    document.getElementById('custCount').innerText = filtered.length+' records';
    document.getElementById('custTbody').innerHTML = filtered.map(c => {
        const rentals = c.total_rentals || 0;
        const income = 1 + (rentals-1)*3;
        const verifType = rentals>1 ? 'DB repeat' : 'Cashfree';
        return `<tr><td><div class="av av-sm" style="display:inline-flex;">${(c.name||'U').slice(0,2)}</div> ${escapeHtml(c.name)}</td><td>${c.city||'--'}</td><td>${c.shop_name||'--'}</td><td>${rentals}</td><td><span class="badge badge-accent">${verifType}</span></td><td>₹${income}</td></tr>`;
    }).join('');
}

function renderWalletPage() {
    const totalCredits = shopsData.reduce((a,s)=>a+s.wallet_balance,0);
    document.getElementById('walletMetrics').innerHTML = `<div class="mcard"><div class="ml">Total Credits</div><div class="mv mv-green">₹${totalCredits.toLocaleString()}</div></div><div class="mcard"><div class="ml">Total Debits</div><div class="mv mv-amber">₹0</div></div><div class="mcard"><div class="ml">Platform Revenue</div><div class="mv mv-accent">₹${shopsData.reduce((a,s)=>a+computeShopStats(s.id).income,0)}</div></div>`;
    document.getElementById('shopBalanceList').innerHTML = shopsData.map(s=> `<div style="margin-bottom:8px;"><div style="display:flex;justify-content:space-between;"><span>${escapeHtml(s.name)}</span><span>₹${s.wallet_balance}</span></div><div class="prog-track"><div class="prog-fill" style="width:${(s.wallet_balance/300000)*100}%;background:var(--accent);"></div></div></div>`).join('');
    document.getElementById('walletLogList').innerHTML = `<div class="wlog-item"><div class="wlog-icon wl-credit"><i class="fas fa-arrow-down"></i></div><div class="wlog-info">Live transactions will appear here</div></div>`;
}

function initMap() {
    if(map) return;
    map = L.map('leafletMap').setView([20.5,84.5],7);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',{subdomains:'abcd'}).addTo(map);
    shopMarkersLayer = L.layerGroup().addTo(map);
    shopsData.forEach(shop => {
        if(shop.lat && shop.lng) {
            const marker = L.marker([shop.lat, shop.lng], { icon: L.divIcon({ html: `<div style="background:#4f6ef7;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid white;"><i class="fas fa-store" style="color:white;font-size:10px;"></i></div>`, iconSize:[24,24] }) }).bindPopup(`<b>${escapeHtml(shop.name)}</b><br>Wallet: ₹${shop.wallet_balance}`);
            shopMarkersLayer.addLayer(marker);
        }
    });
    document.getElementById('fitIndiaBtn').onclick = () => map.setView([22,82],5);
    document.getElementById('fitOdishaBtn').onclick = () => map.fitBounds([[17.8,81.4],[22.6,87.5]]);
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
        document.getElementById(`page-${el.dataset.page}`).classList.add('active');
        document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('pageTitle').innerText = el.dataset.page.charAt(0).toUpperCase() + el.dataset.page.slice(1);
        if(el.dataset.page === 'map') setTimeout(()=> map?.invalidateSize(), 100);
        if(el.dataset.page === 'fleet') renderFleet();
        if(el.dataset.page === 'customers') renderCustomersTable();
    });
});

document.querySelectorAll('#fleetFilter .fbtn').forEach(btn=> btn.addEventListener('click', function(){ document.querySelectorAll('#fleetFilter .fbtn').forEach(b=>b.classList.remove('active')); this.classList.add('active'); renderFleet(); }));
document.querySelectorAll('#custFilter .fbtn').forEach(btn=> btn.addEventListener('click', function(){ document.querySelectorAll('#custFilter .fbtn').forEach(b=>b.classList.remove('active')); this.classList.add('active'); renderCustomersTable(); }));

function escapeHtml(str) {
    if(!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if(m === '&') return '&amp;';
        if(m === '<') return '&lt;';
        if(m === '>') return '&gt;';
        return m;
    });
}

loadDashboardData();