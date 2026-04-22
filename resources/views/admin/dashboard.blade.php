@extends('layouts.admin')

@section('content')
<div class="page active" id="page-dashboard">
    <div class="db-grid">
        <!-- Left: Shops list -->
        <div class="panel">
            <div class="ph">
                <span class="ph-title">Shops ({{ $shops->count() }})</span>
            </div>
            <div class="pb" id="shopList">
                @forelse($shops as $shop)
                <div class="sli" data-id="{{ $shop->id }}" data-name="{{ $shop->name }}" data-email="{{ $shop->email }}" data-gst="{{ $shop->gst_number ?? 'Not added' }}" data-wallet="{{ $shop->wallet_balance }}" data-address="{{ $shop->business_display_address ?? 'Address not set' }}">
                    <div class="ibox ibox-sm"><i class="fas fa-store-alt"></i></div>
                    <div class="sli-info">
                        <div class="sli-name">{{ $shop->name }}</div>
                        <div class="sli-sub">
                            {{ $shop->business_display_address ? explode(',', $shop->business_display_address)[0] : 'Address not set' }}
                        </div>
                    </div>
                    <span class="badge badge-green">₹{{ number_format($shop->wallet_balance, 2) }}</span>
                </div>
                @empty
                <div class="sli" style="padding: 20px; text-align: center;">No shops found</div>
                @endforelse
            </div>
        </div>

        <!-- Middle: Metrics -->
        <div class="db-mid">
            <div class="db-metrics">
                <div class="mcard">
                    <div class="ml">Total Shops</div>
                    <div class="mv mv-accent">{{ $shops->count() }}</div>
                </div>
                <div class="mcard">
                    <div class="ml">Total Wallet Balance</div>
                    <div class="mv mv-green">₹{{ number_format($totalWallet, 2) }}</div>
                </div>
                <div class="mcard">
                    <div class="ml">Total Rentals</div>
                    <div class="mv">{{ number_format($totalRentals) }}</div>
                </div>
                <div class="mcard">
                    <div class="ml">Total Revenue</div>
                    <div class="mv mv-amber">₹{{ number_format($totalRevenue, 2) }}</div>
                </div>
            </div>

            <!-- Shop Detail Panel - Updates when shop is clicked -->
            <div class="db-detail" id="shopDetailPanel">
                <div class="dd-hero">
                    <div class="dd-name">Select a shop</div>
                    <div class="dd-meta">Click on any shop from the left panel to view details</div>
                </div>
            </div>
        </div>

        <!-- Right: Quick Stats & Income Model -->
        <div class="db-right">
            <div class="panel">
                <div class="ph"><span class="ph-title">Quick Stats</span></div>
                <div class="pb">
                    <div style="margin-bottom: 12px;">
                        <div style="color: var(--text-3); font-size: 10px;">Active Shops</div>
                        <div class="mv" style="font-size: 24px;">{{ $shops->count() }}</div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <div style="color: var(--text-3); font-size: 10px;">Average Wallet Balance</div>
                        <div class="mv mv-accent" style="font-size: 24px;">₹{{ number_format($shops->avg('wallet_balance') ?? 0, 2) }}</div>
                    </div>
                    <div>
                        <div style="color: var(--text-3); font-size: 10px;">Highest Wallet</div>
                        <div class="mv mv-green" style="font-size: 24px;">₹{{ number_format($shops->max('wallet_balance') ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="panel">
                <div class="ph"><span class="ph-title">Verification Income Model</span></div>
                <div class="pb">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>1st verify (Cashfree):</span>
                        <span style="color: var(--red);">−₹2/cust</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Shop charge:</span>
                        <span style="color: var(--green);">+₹3/verif</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>1st time profit:</span>
                        <span style="color: var(--amber);">₹1 net</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Repeat (DB lookup):</span>
                        <span style="color: var(--green);">₹3 pure profit</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Shop click handler - load and display details in the panel
    document.querySelectorAll('.sli').forEach(el => {
        el.addEventListener('click', async function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const email = this.dataset.email;
            const gst = this.dataset.gst;
            const wallet = this.dataset.wallet;
            const address = this.dataset.address;
            
            // Fetch real-time stats from API
            try {
                const response = await fetch(`/api/admin/rentals?user_id=${id}`);
                const data = await response.json();
                
                const rentals = data.data || [];
                const verifications = rentals.filter(r => r.verification_completed_at).length;
                const totalIncome = rentals.reduce((sum, r) => sum + (r.total_price || 0), 0);
                
                document.getElementById('shopDetailPanel').innerHTML = `
                    <div class="dd-hero">
                        <div class="dd-name">${escapeHtml(name)}</div>
                        <div class="dd-meta">${escapeHtml(email)} · GST: ${escapeHtml(gst)}</div>
                        <div class="dd-info" style="font-size: 10px; color: var(--text-3); margin-top: 4px;">${escapeHtml(address)}</div>
                    </div>
                    <div class="dd-stats">
                        <div class="dd-stat">
                            <div class="dd-stat-v mv-accent">₹${parseFloat(wallet).toLocaleString()}</div>
                            <div class="dd-stat-l">Wallet Balance</div>
                        </div>
                        <div class="dd-stat">
                            <div class="dd-stat-v">${verifications}</div>
                            <div class="dd-stat-l">Verifications</div>
                        </div>
                        <div class="dd-stat">
                            <div class="dd-stat-v mv-green">₹${totalIncome.toLocaleString()}</div>
                            <div class="dd-stat-l">Total Income</div>
                        </div>
                    </div>
                    <div class="dd-body">
                        <div class="dd-section">
                            <div class="section-label">Recent Rentals</div>
                            <div id="recentRentals" style="font-size: 11px;">
                                ${rentals.slice(0, 5).map(r => `
                                    <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border);">
                                        <span>${r.vehicle?.name || 'Vehicle'}</span>
                                        <span style="color: var(--green);">₹${(r.total_price || 0).toLocaleString()}</span>
                                    </div>
                                `).join('') || '<div style="padding: 10px 0; color: var(--text-3);">No rentals yet</div>'}
                            </div>
                        </div>
                        <div class="dd-section">
                            <div class="section-label">Performance</div>
                            <div style="margin-top: 8px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span style="font-size: 10px;">Completion Rate</span>
                                    <span style="font-size: 10px; color: var(--green);">0%</span>
                                </div>
                                <div class="prog-track"><div class="prog-fill" style="width: 0%; background: var(--green);"></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="dd-actions">
                        <a href="/admin/shops/${id}" class="btn btn-accent" style="flex: 1; text-align: center; text-decoration: none;"><i class="fas fa-eye"></i> View Full Details</a>
                    </div>
                `;
            } catch(e) {
                console.error(e);
                // Fallback with basic info
                document.getElementById('shopDetailPanel').innerHTML = `
                    <div class="dd-hero">
                        <div class="dd-name">${escapeHtml(name)}</div>
                        <div class="dd-meta">${escapeHtml(email)} · GST: ${escapeHtml(gst)}</div>
                        <div class="dd-info" style="font-size: 10px; color: var(--text-3); margin-top: 4px;">${escapeHtml(address)}</div>
                    </div>
                    <div class="dd-stats">
                        <div class="dd-stat">
                            <div class="dd-stat-v mv-accent">₹${parseFloat(wallet).toLocaleString()}</div>
                            <div class="dd-stat-l">Wallet Balance</div>
                        </div>
                        <div class="dd-stat">
                            <div class="dd-stat-v">0</div>
                            <div class="dd-stat-l">Verifications</div>
                        </div>
                        <div class="dd-stat">
                            <div class="dd-stat-v mv-green">₹0</div>
                            <div class="dd-stat-l">Total Income</div>
                        </div>
                    </div>
                    <div class="dd-body">
                        <div class="dd-section">
                            <div class="section-label">Recent Rentals</div>
                            <div style="padding: 10px 0; color: var(--text-3); text-align: center;">No data available</div>
                        </div>
                        <div class="dd-section">
                            <div class="section-label">Performance</div>
                            <div style="padding: 10px 0; color: var(--text-3); text-align: center;">Coming soon</div>
                        </div>
                    </div>
                    <div class="dd-actions">
                        <a href="/admin/shops/${id}" class="btn btn-accent" style="flex: 1; text-align: center; text-decoration: none;"><i class="fas fa-eye"></i> View Full Details</a>
                    </div>
                `;
            }
        });
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
</script>
@endsection