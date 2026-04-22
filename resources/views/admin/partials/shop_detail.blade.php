<div class="dd-hero">
    <div class="dd-name">{{ $shop->name }}</div>
    <div class="dd-meta">Wallet: ₹{{ number_format($shop->wallet_balance, 2) }} · {{ $shop->total_rentals ?? 0 }} total rentals</div>
    <div class="dd-info" style="font-size: 10px; color: var(--text-3); margin-top: 4px;">
        📍 {{ $shop->business_display_address ?? 'Address not set' }}
    </div>
</div>

<div class="dd-stats" style="display: grid; grid-template-columns: repeat(3, 1fr);">
    <div class="dd-stat">
        <div class="dd-stat-v mv-accent">₹{{ number_format($shop->wallet_balance, 2) }}</div>
        <div class="dd-stat-l">Wallet</div>
    </div>
    <div class="dd-stat">
        <div class="dd-stat-v">{{ $shop->verifications ?? 0 }}</div>
        <div class="dd-stat-l">Verifications</div>
    </div>
    <div class="dd-stat">
        <div class="dd-stat-v mv-green">₹{{ number_format($shop->total_income ?? 0, 2) }}</div>
        <div class="dd-stat-l">Income</div>
    </div>
</div>

<div class="dd-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0;">
    <div class="dd-section" style="padding: 12px;">
        <div class="section-label">Rental Stats</div>
        <div style="margin-top: 8px;">
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>Active Rentals</span>
                <span class="badge badge-accent">{{ $shop->active_rentals ?? 0 }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>Completed</span>
                <span class="badge badge-green">{{ $shop->completed_rentals ?? 0 }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>Cancelled</span>
                <span class="badge badge-red">{{ $shop->cancelled_rentals ?? 0 }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>Verifications</span>
                <span class="badge badge-accent">{{ $shop->verifications ?? 0 }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>Fresh/Cached</span>
                <span>{{ $shop->fresh_verifications ?? 0 }}/{{ $shop->cached_verifications ?? 0 }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>Verif Profit</span>
                <span class="badge badge-green">₹{{ number_format($shop->profit_from_verifications ?? 0, 2) }}</span>
            </div>
        </div>
    </div>
    <div class="dd-section" style="padding: 12px;">
        <div class="section-label">Recent Rentals</div>
        <div style="margin-top: 8px; font-size: 10px;">
            @forelse(($shop->recent_rentals ?? []) as $rental)
            <div style="padding: 6px 0; border-bottom: 1px solid var(--border);">
                <div><strong>{{ $rental->vehicle->name ?? 'Vehicle' }}</strong> - {{ ucfirst($rental->status) }}</div>
                <div style="color: var(--text-3);">₹{{ number_format($rental->total_price ?? 0, 2) }} · {{ \Carbon\Carbon::parse($rental->created_at)->format('d M Y') }}</div>
            </div>
            @empty
            <div style="padding: 6px 0; color: var(--text-3);">No recent rentals</div>
            @endforelse
        </div>
        <div class="section-label" style="margin-top: 12px;">Fleet Vehicles</div>
        <div style="margin-top: 8px;">
            @forelse(($shop->fleet_vehicles ?? []) as $vehicle)
            <span class="chip"><i class="fas fa-car"></i> {{ $vehicle->name }}</span>
            @empty
            <div style="padding: 6px 0; color: var(--text-3);">No vehicles</div>
            @endforelse
        </div>
    </div>
</div>

<div class="dd-actions" style="padding: 12px;">
    <a href="{{ url('/admin/shops/' . $shop->id) }}" class="btn btn-accent" style="width: 100%; text-align: center; text-decoration: none;">
        <i class="fas fa-eye"></i> View Full Details
    </a>
</div>