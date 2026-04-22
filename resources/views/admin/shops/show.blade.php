@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="panel" style="margin: 16px;">
        <div class="ph">
            <span class="ph-title">Shop Details</span>
            <a href="{{ route('admin.shops.index') }}" class="btn btn-ghost" style="padding: 4px 12px;">
                <i class="fas fa-arrow-left"></i> Back to Shops
            </a>
        </div>
        <div class="pb">
            <!-- Shop Header -->
            <div class="dd-hero" style="margin-bottom: 20px;">
                <div class="dd-name">{{ $shop->name }}</div>
                <div class="dd-meta">{{ $shop->email ?? 'No email' }} · Phone: {{ $shop->phone }}</div>
                <div class="dd-info">
                    <i class="fas fa-id-card"></i> GST: {{ $shop->gst_number ?? 'Not added' }}
                    <span style="margin: 0 8px;">·</span>
                    <i class="fas fa-map-marker-alt"></i> {{ $shop->business_display_address ?? 'Address not set' }}
                </div>
                <div class="dd-info" style="margin-top: 8px;">
                    <span class="badge {{ $shop->wallet_balance > 50000 ? 'badge-green' : 'badge-accent' }}">
                        Wallet: ₹{{ number_format($shop->wallet_balance, 2) }}
                    </span>
                    <span class="badge badge-accent">Role: {{ ucfirst($shop->role) }}</span>
                    <span class="badge {{ $shop->business_verification_status === 'verified' ? 'badge-green' : 'badge-amber' }}">
                        {{ ucfirst($shop->business_verification_status) }}
                    </span>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="db-metrics" style="margin-bottom: 20px;">
                <div class="mcard">
                    <div class="ml">Total Rentals</div>
                    <div class="mv mv-accent">{{ $stats['total_rentals'] ?? 0 }}</div>
                </div>
                <div class="mcard">
                    <div class="ml">Completed Rentals</div>
                    <div class="mv mv-green">{{ $stats['completed_rentals'] ?? 0 }}</div>
                </div>
                <div class="mcard">
                    <div class="ml">Total Earnings</div>
                    <div class="mv mv-amber">₹{{ number_format($stats['total_earnings'] ?? 0, 2) }}</div>
                </div>
                <div class="mcard">
                    <div class="ml">Verification Fees</div>
                    <div class="mv">₹{{ number_format($stats['verification_fees'] ?? 0, 2) }}</div>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Left Column: Fleet Vehicles -->
                <div class="panel">
                    <div class="ph">
                        <span class="ph-title">Fleet Vehicles ({{ $vehicles->count() }})</span>
                        <button class="btn btn-accent btn-sm" style="padding: 4px 12px;" onclick="alert('Add vehicle feature coming soon')">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    <div class="pb">
                        @forelse($vehicles as $vehicle)
                        <div class="sli" style="margin-bottom: 8px;">
                            <div class="ibox ibox-sm"><i class="fas fa-car"></i></div>
                            <div class="sli-info">
                                <div class="sli-name">{{ $vehicle->name }}</div>
                                <div class="sli-sub">{{ $vehicle->number_plate }} · {{ ucfirst($vehicle->type) }}</div>
                            </div>
                            <span class="badge {{ $vehicle->status === 'available' ? 'badge-green' : ($vehicle->status === 'on_rent' ? 'badge-accent' : 'badge-red') }}">
                                {{ ucfirst($vehicle->status) }}
                            </span>
                        </div>
                        @empty
                        <div style="text-align: center; padding: 40px; color: var(--text-3);">
                            <i class="fas fa-car" style="font-size: 48px; margin-bottom: 12px; display: block;"></i>
                            No vehicles added yet
                        </div>
                        @endforelse
                    </div>
                </div>

                <!-- Right Column: Recent Rentals -->
                <div class="panel">
                    <div class="ph">
                        <span class="ph-title">Recent Rentals</span>
                        <a href="{{ route('admin.rentals.index') }}?user_id={{ $shop->id }}" class="btn btn-ghost btn-sm" style="padding: 4px 12px;">
                            View All
                        </a>
                    </div>
                    <div class="pb">
                        @forelse($rentals as $rental)
                        <div class="sli" style="margin-bottom: 8px;">
                            <div class="ibox ibox-sm"><i class="fas fa-receipt"></i></div>
                            <div class="sli-info">
                                <div class="sli-name">{{ $rental->vehicle->name ?? 'N/A' }}</div>
                                <div class="sli-sub">
                                    {{ $rental->customer->name ?? 'N/A' }} · 
                                    {{ $rental->created_at->format('d M Y') }}
                                </div>
                            </div>
                            <span class="badge {{ $rental->status === 'completed' ? 'badge-green' : ($rental->status === 'active' ? 'badge-accent' : 'badge-red') }}">
                                {{ ucfirst($rental->status) }}
                            </span>
                        </div>
                        @empty
                        <div style="text-align: center; padding: 40px; color: var(--text-3);">
                            <i class="fas fa-calendar-alt" style="font-size: 48px; margin-bottom: 12px; display: block;"></i>
                            No rentals yet
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            @if($rentals->hasPages())
            <div style="margin-top: 20px; padding: 10px 0;">
                {{ $rentals->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection