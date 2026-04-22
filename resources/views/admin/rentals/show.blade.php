@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="panel" style="margin: 16px;">
        <div class="ph">
            <span class="ph-title">Rental Details #{{ $rental->id }}</span>
            <a href="{{ route('admin.rentals.index') }}" class="btn btn-ghost" style="padding: 4px 12px;">
                <i class="fas fa-arrow-left"></i> Back to Rentals
            </a>
        </div>
        <div class="pb">
            <!-- Rental Header -->
            <div class="dd-hero" style="margin-bottom: 20px;">
                <div class="dd-name">Rental Information</div>
                <div class="dd-meta">Created: {{ $rental->created_at->format('d M Y, h:i A') }}</div>
                <div class="dd-info">
                    <span class="badge {{ $rental->status === 'completed' ? 'badge-green' : ($rental->status === 'active' ? 'badge-accent' : 'badge-red') }}">
                        Status: {{ ucfirst($rental->status) }}
                    </span>
                    <span class="badge badge-accent">Phase: {{ ucfirst($rental->phase) }}</span>
                    @if($rental->is_verification_cached)
                    <span class="badge badge-green">Cached Verification</span>
                    @else
                    <span class="badge badge-amber">Fresh Verification</span>
                    @endif
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="db-metrics" style="margin-bottom: 20px;">
                <div class="mcard">
                    <div class="ml">Total Price</div>
                    <div class="mv mv-amber">₹{{ number_format($rental->total_price ?? 0, 2) }}</div>
                </div>
                <div class="mcard">
                    <div class="ml">Duration</div>
                    <div class="mv">{{ $durationHours ?? 0 }} hrs</div>
                </div>
                <div class="mcard">
                    <div class="ml">Verification Fee</div>
                    <div class="mv">₹{{ number_format($rental->verification_fee_deducted ?? 0, 2) }}</div>
                </div>
                <div class="mcard">
                    <div class="ml">Platform Profit</div>
                    <div class="mv mv-green">₹{{ number_format($platformProfit ?? 0, 2) }}</div>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Left Column: Shop & Customer Info -->
                <div>
                    <!-- Shop Info -->
                    <div class="panel" style="margin-bottom: 20px;">
                        <div class="ph"><span class="ph-title">Shop Information</span></div>
                        <div class="pb">
                            <div style="margin-bottom: 12px;">
                                <div style="color: var(--text-3); font-size: 10px;">Shop Name</div>
                                <div style="font-weight: 600;">{{ $rental->user->name ?? 'N/A' }}</div>
                            </div>
                            <div style="margin-bottom: 12px;">
                                <div style="color: var(--text-3); font-size: 10px;">Email</div>
                                <div>{{ $rental->user->email ?? 'N/A' }}</div>
                            </div>
                            <div>
                                <div style="color: var(--text-3); font-size: 10px;">Phone</div>
                                <div>{{ $rental->user->phone ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Info -->
                    <div class="panel">
                        <div class="ph"><span class="ph-title">Customer Information</span></div>
                        <div class="pb">
                            <div style="margin-bottom: 12px;">
                                <div style="color: var(--text-3); font-size: 10px;">Customer Name</div>
                                <div style="font-weight: 600;">{{ $rental->customer->name ?? 'N/A' }}</div>
                            </div>
                            <div style="margin-bottom: 12px;">
                                <div style="color: var(--text-3); font-size: 10px;">Phone</div>
                                <div>{{ $rental->customer->phone ?? 'N/A' }}</div>
                            </div>
                            <div>
                                <div style="color: var(--text-3); font-size: 10px;">License Number</div>
                                <div class="tbl-mono">{{ $rental->customer->license_number ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Vehicle Info & Timeline -->
                <div>
                    <!-- Vehicle Info -->
                    <div class="panel" style="margin-bottom: 20px;">
                        <div class="ph"><span class="ph-title">Vehicle Information</span></div>
                        <div class="pb">
                            <div style="margin-bottom: 12px;">
                                <div style="color: var(--text-3); font-size: 10px;">Vehicle Name</div>
                                <div style="font-weight: 600;">{{ $rental->vehicle->name ?? 'N/A' }}</div>
                            </div>
                            <div style="margin-bottom: 12px;">
                                <div style="color: var(--text-3); font-size: 10px;">Number Plate</div>
                                <div class="tbl-mono">{{ $rental->vehicle->number_plate ?? 'N/A' }}</div>
                            </div>
                            <div>
                                <div style="color: var(--text-3); font-size: 10px;">Vehicle Type</div>
                                <div>{{ ucfirst($rental->vehicle->type ?? 'N/A') }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Timeline -->
                    <div class="panel">
                        <div class="ph"><span class="ph-title">Rental Timeline</span></div>
                        <div class="pb">
                            <div style="margin-bottom: 12px;">
                                <div style="color: var(--text-3); font-size: 10px;">Start Time</div>
                                <div class="tbl-mono">{{ $rental->start_time ? $rental->start_time->format('d M Y, h:i A') : 'Not started' }}</div>
                            </div>
                            <div style="margin-bottom: 12px;">
                                <div style="color: var(--text-3); font-size: 10px;">End Time</div>
                                <div class="tbl-mono">{{ $rental->end_time ? $rental->end_time->format('d M Y, h:i A') : 'Not ended' }}</div>
                            </div>
                            <div>
                                <div style="color: var(--text-3); font-size: 10px;">Duration</div>
                                <div>{{ $durationHours ?? 0 }} hours</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection