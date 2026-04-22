@extends('layouts.admin')

@section('content')
<div class="page active" id="page-dashboard">
    <div class="db-grid">
        <!-- Left: Shops list -->
        <div class="panel">
            <div class="ph">
                <span class="ph-title">Shops ({{ $shops->count() }})</span>
                <div class="fstrip">
                    <button class="fbtn active" data-filter="all">All</button>
                </div>
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
                    <span class="badge badge-{{ $shop->wallet_balance > 50000 ? 'green' : ($shop->wallet_balance > 10000 ? 'accent' : 'red') }}">
                        ₹{{ number_format($shop->wallet_balance, 2) }}
                    </span>
                </div>
                @empty
                <div class="sli" style="padding: 20px; text-align: center;">No shops found</div>
                @endforelse
            </div>
        </div>

        <!-- Middle: Metrics Grid -->
        <div class="db-mid">
            <!-- Row 1: Key Metrics -->
            <div class="db-metrics">
                <div class="mcard">
                    <div class="ml">Total Shops</div>
                    <div class="mv mv-accent">{{ $totalShops }}</div>
                    <div class="ms">Active businesses</div>
                </div>
                <div class="mcard">
                    <div class="ml">Total Wallet</div>
                    <div class="mv mv-green">₹{{ number_format($totalWallet, 2) }}</div>
                    <div class="ms">Across all shops</div>
                </div>
                <div class="mcard">
                    <div class="ml">Total Rentals</div>
                    <div class="mv">{{ $totalRentals }}</div>
                    <div class="ms">{{ $activeRentals }} active</div>
                </div>
                <div class="mcard">
                    <div class="ml">Total Revenue</div>
                    <div class="mv mv-amber">₹{{ number_format($totalRevenue, 2) }}</div>
                    <div class="ms">From completed rentals</div>
                </div>
            </div>

            <!-- Row 2: More Metrics -->
            <div class="db-metrics" style="margin-top: 12px;">
                <div class="mcard">
                    <div class="ml">Verifications</div>
                    <div class="mv">{{ $totalVerifications }}</div>
                    <div class="ms">{{ $freshVerifications ?? 0 }} fresh · {{ $cachedVerifications ?? 0 }} cached</div>
                </div>
                <div class="mcard">
                    <div class="ml">Platform Profit</div>
                    <div class="mv mv-green">₹{{ number_format($platformProfit, 2) }}</div>
                    <div class="ms">From verifications</div>
                </div>
                <div class="mcard">
                    <div class="ml">Vehicles</div>
                    <div class="mv">{{ $totalVehicles }}</div>
                    <div class="ms">{{ $availableVehicles }} available · {{ $onRentVehicles }} on rent</div>
                </div>
                <div class="mcard">
                    <div class="ml">Growth (30d)</div>
                    <div class="mv mv-amber">{{ $growth >= 0 ? '+' : '' }}{{ $growth }}%</div>
                    <div class="ms">vs previous period</div>
                </div>
            </div>

            <!-- Shop Detail Panel - Updates when shop is clicked -->
            <div class="db-detail" style="margin-top: 12px;" id="shopDetailPanel">
                <div class="dd-hero">
                    <div class="dd-name">Select a shop</div>
                    <div class="dd-meta">Click on any shop from the left panel to view details</div>
                </div>
            </div>
        </div>

        <!-- Right: Quick Stats & Income Model -->
        <div class="db-right">
            <div class="panel">
                <div class="ph"><span class="ph-title">Wallet Summary</span></div>
                <div class="pb">
                    <div style="margin-bottom: 12px;">
                        <div style="color: var(--text-3); font-size: 10px;">Total Credits</div>
                        <div class="mv mv-green" style="font-size: 20px;">₹{{ number_format($totalWalletCredits, 2) }}</div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <div style="color: var(--text-3); font-size: 10px;">Total Debits</div>
                        <div class="mv mv-red" style="font-size: 20px;">₹{{ number_format($totalWalletDebits, 2) }}</div>
                    </div>
                    <div>
                        <div style="color: var(--text-3); font-size: 10px;">Net Wallet Flow</div>
                        <div class="mv mv-accent" style="font-size: 20px;">₹{{ number_format($totalWalletCredits - $totalWalletDebits, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="panel">
                <div class="ph"><span class="ph-title">Top Customers</span></div>
                <div class="pb">
                    @forelse($topCustomers as $customer)
                    <div class="cli" style="margin-bottom: 10px;">
                        <div class="av av-sm">{{ substr($customer->name, 0, 2) }}</div>
                        <div class="cli-info">
                            <div class="cli-name">{{ $customer->name }}</div>
                            <div class="cli-sub">{{ $customer->city }} · {{ $customer->rentals_count }} trips</div>
                        </div>
                    </div>
                    @empty
                    <div style="text-align: center; padding: 20px; color: var(--text-3);">No customers yet</div>
                    @endforelse
                </div>
            </div>
            <div class="panel">
                <div class="ph"><span class="ph-title">Verification Model</span></div>
                <div class="pb">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Fresh verify cost:</span>
                        <span style="color: var(--red);">−₹2</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Shop charge:</span>
                        <span style="color: var(--green);">+₹3</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Fresh profit:</span>
                        <span style="color: var(--amber);">₹1</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Cached profit:</span>
                        <span style="color: var(--green);">₹3</span>
                    </div>
                    <div class="divider" style="margin: 12px 0;"></div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Total platform profit:</span>
                        <span style="color: var(--accent); font-weight: 700;">₹{{ number_format($platformProfit, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Shop click handler - load and display details
    document.querySelectorAll('.sli').forEach(el => {
        el.addEventListener('click', async function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const email = this.dataset.email;
            const gst = this.dataset.gst;
            const wallet = this.dataset.wallet;
            const address = this.dataset.address;
            
            // Fetch real-time rentals for this shop
            try {
                const response = await fetch(`/api/admin/rentals?user_id=${id}`);
                const data = await response.json();
                const rentals = data.data || [];
                const verifications = rentals.filter(r => r.verification_completed_at).length;
                const totalIncome = rentals.reduce((sum, r) => sum + (r.total_price || 0), 0);
                const activeRentals = rentals.filter(r => r.status === 'active').length;
                
                document.getElementById('shopDetailPanel').innerHTML = `
                    <div class="dd-hero">
                        <div class="dd-name">${escapeHtml(name)}</div>
                        <div class="dd-meta">${escapeHtml(email)} · GST: ${escapeHtml(gst)}</div>
                        <div class="dd-info" style="font-size: 10px; color: var(--text-3); margin-top: 4px;">📍 ${escapeHtml(address)}</div>
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
                            <div class="section-label">Recent Rentals (${activeRentals} active)</div>
                            <div id="recentRentals" style="font-size: 11px;">
                                ${rentals.slice(0, 5).map(r => `
                                    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border);">
                                        <div>
                                            <span style="color: var(--text-2);">${r.vehicle?.name || 'Vehicle'}</span>
                                            <div style="font-size: 9px; color: var(--text-3);">${r.status}</div>
                                        </div>
                                        <span style="color: var(--green);">₹${(r.total_price || 0).toLocaleString()}</span>
                                    </div>
                                `).join('') || '<div style="padding: 10px 0; color: var(--text-3); text-align: center;">No rentals yet</div>'}
                            </div>
                        </div>
                        <div class="dd-section">
                            <div class="section-label">Quick Stats</div>
                            <div style="margin-top: 8px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="font-size: 10px;">Total Rentals</span>
                                    <span style="font-size: 10px; font-weight: 700;">${rentals.length}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="font-size: 10px;">Avg Rental Value</span>
                                    <span style="font-size: 10px; font-weight: 700;">₹${rentals.length > 0 ? Math.round(totalIncome/rentals.length).toLocaleString() : 0}</span>
                                </div>
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
                        <div class="dd-info" style="font-size: 10px; color: var(--text-3); margin-top: 4px;">📍 ${escapeHtml(address)}</div>
                    </div>
                    <div class="dd-stats">
                        <div class="dd-stat"><div class="dd-stat-v mv-accent">₹${parseFloat(wallet).toLocaleString()}</div><div class="dd-stat-l">Wallet Balance</div></div>
                        <div class="dd-stat"><div class="dd-stat-v">0</div><div class="dd-stat-l">Verifications</div></div>
                        <div class="dd-stat"><div class="dd-stat-v mv-green">₹0</div><div class="dd-stat-l">Total Income</div></div>
                    </div>
                    <div class="dd-body">
                        <div class="dd-section"><div class="section-label">Rentals</div><div style="padding: 20px; text-align: center; color: var(--text-3);">No data available</div></div>
                        <div class="dd-section"><div class="section-label">Stats</div><div style="padding: 20px; text-align: center; color: var(--text-3);">Coming soon</div></div>
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