@extends('layouts.admin')

@section('content')
<div class="page active" id="page-dashboard">
    <div class="db-grid">
        <!-- Left: Shops list -->
        <div class="panel">
            <div class="ph">
                <span class="ph-title">Shops ({{ $shops->count() }})</span>
            </div>
            <div class="pb" style="padding: 0;">
                @foreach($shops as $shop)
                <div class="sli shop-item" data-shop-id="{{ $shop->id }}" onclick="selectShop({{ $shop->id }})" style="cursor: pointer;">
                    <div class="ibox ibox-sm"><i class="fas fa-store-alt"></i></div>
                    <div class="sli-info">
                        <div class="sli-name">{{ $shop->name }}</div>
                        <div class="sli-sub">{{ $shop->business_display_address ? substr($shop->business_display_address, 0, 35) : 'Address not set' }}</div>
                    </div>
                    <span class="badge {{ $shop->wallet_balance > 50000 ? 'badge-green' : ($shop->wallet_balance > 10000 ? 'badge-accent' : 'badge-red') }}">
                        ₹{{ number_format($shop->wallet_balance, 2) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Middle: Metrics & Shop Detail -->
        <div class="db-mid">
            <!-- Row 1: Key Metrics -->
            <div class="db-metrics">
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
            <div class="db-metrics" style="margin-top: 12px;">
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

            <!-- Shop Detail Panel -->
            <div class="db-detail" style="margin-top: 12px;" id="shopDetailPanel">
                @include('admin.partials.shop_detail', ['shop' => $selectedShop])
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
                        <span style="font-weight: 600;">Total profit:</span>
                        <span style="color: var(--accent); font-weight: 700;">₹{{ number_format($platformProfit, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectShop(shopId) {
    // Show loading state
    document.getElementById('shopDetailPanel').innerHTML = `
        <div class="dd-hero">
            <div class="dd-name">Loading...</div>
            <div class="dd-meta">Fetching shop details</div>
        </div>
    `;
    
    fetch(`/admin/shops/${shopId}/details`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('shopDetailPanel').innerHTML = html;
            // Update active state on shop items
            document.querySelectorAll('.shop-item').forEach(el => {
                el.classList.remove('active');
                if(parseInt(el.dataset.shopId) === shopId) {
                    el.classList.add('active');
                }
            });
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('shopDetailPanel').innerHTML = `
                <div class="dd-hero">
                    <div class="dd-name">Error</div>
                    <div class="dd-meta">Could not load shop details. Please try again.</div>
                </div>
            `;
        });
}

// Optional: Auto-select first shop on page load
document.addEventListener('DOMContentLoaded', function() {
    const firstShop = document.querySelector('.shop-item');
    if (firstShop && firstShop.dataset.shopId) {
        // Uncomment to auto-select first shop
        // selectShop(parseInt(firstShop.dataset.shopId));
    }
});
</script>
@endsection