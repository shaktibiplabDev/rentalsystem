@extends('layouts.admin')

@section('content')
<div class="page active" id="page-dashboard">
    <div class="db-grid">
        <!-- Left: Shops list -->
        <div class="panel">
            <div class="ph">
                <span class="ph-title">Shops ({{ $shops->count() }})</span>
            </div>
            <div class="pb" id="shopList" style="padding: 0;">
                @forelse($shops as $shop)
                <div class="sli" data-id="{{ $shop->id }}" onclick="loadShopDetails({{ $shop->id }})">
                    <div class="ibox ibox-sm"><i class="fas fa-store-alt"></i></div>
                    <div class="sli-info">
                        <div class="sli-name">{{ $shop->name }}</div>
                        <div class="sli-sub">{{ $shop->business_display_address ? substr($shop->business_display_address, 0, 30) : 'Address not set' }}</div>
                    </div>
                    <span class="badge badge-{{ $shop->wallet_balance > 50000 ? 'green' : ($shop->wallet_balance > 10000 ? 'accent' : 'red') }}">
                        ₹{{ number_format($shop->wallet_balance, 2) }}
                    </span>
                </div>
                @empty
                <div style="padding: 20px; text-align: center;">No shops found</div>
                @endforelse
            </div>
        </div>

        <!-- Middle: Metrics & Shop Detail -->
        <div class="db-mid">
            <!-- Row 1: Key Metrics -->
            <div class="db-metrics" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                <div class="mcard">
                    <div class="ml">Total Shops</div>
                    <div class="mv mv-accent" style="font-size: 24px;">{{ $totalShops }}</div>
                </div>
                <div class="mcard">
                    <div class="ml">Total Wallet</div>
                    <div class="mv mv-green" style="font-size: 24px;">₹{{ number_format($totalWallet, 2) }}</div>
                </div>
                <div class="mcard">
                    <div class="ml">Total Rentals</div>
                    <div class="mv" style="font-size: 24px;">{{ $totalRentals }}</div>
                    <div class="ms">{{ $activeRentals }} active</div>
                </div>
                <div class="mcard">
                    <div class="ml">Total Revenue</div>
                    <div class="mv mv-amber" style="font-size: 24px;">₹{{ number_format($totalRevenue, 2) }}</div>
                </div>
            </div>

            <!-- Row 2: More Metrics -->
            <div class="db-metrics" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 12px;">
                <div class="mcard">
                    <div class="ml">Verifications</div>
                    <div class="mv" style="font-size: 24px;">{{ $totalVerifications }}</div>
                    <div class="ms">{{ $freshVerifications }} fresh · {{ $cachedVerifications }} cached</div>
                </div>
                <div class="mcard">
                    <div class="ml">Platform Profit</div>
                    <div class="mv mv-green" style="font-size: 24px;">₹{{ number_format($platformProfit, 2) }}</div>
                    <div class="ms">From verifications</div>
                </div>
                <div class="mcard">
                    <div class="ml">Vehicles</div>
                    <div class="mv" style="font-size: 24px;">{{ $totalVehicles }}</div>
                    <div class="ms">{{ $availableVehicles }} avail · {{ $onRentVehicles }} rent</div>
                </div>
                <div class="mcard">
                    <div class="ml">Growth (30d)</div>
                    <div class="mv mv-amber" style="font-size: 24px;">{{ $growth >= 0 ? '+' : '' }}{{ $growth }}%</div>
                </div>
            </div>

            <!-- Shop Detail Panel - Shows when shop clicked -->
            <div class="db-detail" style="margin-top: 12px;" id="shopDetailPanel">
                <div class="dd-hero">
                    <div class="dd-name">Select a shop</div>
                    <div class="dd-meta">Click on any shop from the left panel to view details</div>
                </div>
            </div>
        </div>

        <!-- Right: Quick Stats -->
        <div class="db-right">
            <div class="panel">
                <div class="ph"><span class="ph-title">Top Customers</span></div>
                <div class="pb">
                    @forelse($topCustomers as $customer)
                    <div class="cli" style="margin-bottom: 10px; padding: 8px;">
                        <div class="av av-sm">{{ substr($customer->name, 0, 2) }}</div>
                        <div class="cli-info">
                            <div class="cli-name">{{ $customer->name }}</div>
                            <div class="cli-sub">{{ $customer->city ?? '—' }} · {{ $customer->rentals_count }} trips</div>
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
                        <span>Total profit:</span>
                        <span style="color: var(--accent); font-weight: 700;">₹{{ number_format($platformProfit, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadShopDetails(shopId) {
    // Show loading state
    document.getElementById('shopDetailPanel').innerHTML = `
        <div class="dd-hero">
            <div class="dd-name">Loading...</div>
            <div class="dd-meta">Fetching shop details</div>
        </div>
    `;
    
    // Fetch shop rentals directly from your API
    fetch(`/api/admin/rentals`)
        .then(res => res.json())
        .then(data => {
            const rentals = data.data || [];
            const shopRentals = rentals.filter(r => r.user && r.user.id == shopId);
            
            // Get shop info from the clicked element
            const shopEl = document.querySelector(`.sli[data-id="${shopId}"]`);
            const shopName = shopEl?.querySelector('.sli-name')?.innerText || 'Shop';
            
            // Calculate stats
            const verifications = shopRentals.filter(r => r.verification_completed_at).length;
            const totalIncome = shopRentals.reduce((sum, r) => sum + (r.total_price || 0), 0);
            const activeRentals = shopRentals.filter(r => r.status === 'active').length;
            const completedRentals = shopRentals.filter(r => r.status === 'completed').length;
            
            // Get wallet from displayed element
            const walletText = shopEl?.querySelector('.badge')?.innerText || '₹0';
            const wallet = parseFloat(walletText.replace('₹', '').replace(/,/g, '')) || 0;
            
            document.getElementById('shopDetailPanel').innerHTML = `
                <div class="dd-hero">
                    <div class="dd-name">${escapeHtml(shopName)}</div>
                    <div class="dd-meta">Wallet: ₹${wallet.toLocaleString()} · ${shopRentals.length} total rentals</div>
                </div>
                <div class="dd-stats" style="display: grid; grid-template-columns: repeat(3, 1fr);">
                    <div class="dd-stat"><div class="dd-stat-v mv-accent">₹${wallet.toLocaleString()}</div><div class="dd-stat-l">Wallet</div></div>
                    <div class="dd-stat"><div class="dd-stat-v">${verifications}</div><div class="dd-stat-l">Verifications</div></div>
                    <div class="dd-stat"><div class="dd-stat-v mv-green">₹${totalIncome.toLocaleString()}</div><div class="dd-stat-l">Income</div></div>
                </div>
                <div class="dd-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0;">
                    <div class="dd-section" style="padding: 12px;">
                        <div class="section-label">Rental Stats</div>
                        <div style="margin-top: 8px;">
                            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                                <span>Active Rentals</span>
                                <span class="badge badge-accent">${activeRentals}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                                <span>Completed</span>
                                <span class="badge badge-green">${completedRentals}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                                <span>Cancelled</span>
                                <span class="badge badge-red">${shopRentals.filter(r => r.status === 'cancelled').length}</span>
                            </div>
                        </div>
                    </div>
                    <div class="dd-section" style="padding: 12px;">
                        <div class="section-label">Recent Activity</div>
                        <div style="margin-top: 8px; font-size: 10px;">
                            ${shopRentals.slice(0, 3).map(r => `
                                <div style="padding: 6px 0; border-bottom: 1px solid var(--border);">
                                    <div>${r.vehicle?.name || 'Vehicle'} - ${r.status}</div>
                                    <div style="color: var(--text-3);">${new Date(r.created_at).toLocaleDateString()}</div>
                                </div>
                            `).join('') || '<div style="padding: 6px 0;">No recent activity</div>'}
                        </div>
                    </div>
                </div>
                <div class="dd-actions" style="padding: 12px;">
                    <a href="/admin/shops/${shopId}" class="btn btn-accent" style="width: 100%; text-align: center;"><i class="fas fa-eye"></i> View Full Details</a>
                </div>
            `;
        })
        .catch(error => {
            console.error('Error loading shop details:', error);
            document.getElementById('shopDetailPanel').innerHTML = `
                <div class="dd-hero">
                    <div class="dd-name">Error</div>
                    <div class="dd-meta">Could not load shop details</div>
                </div>
            `;
        });
}

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