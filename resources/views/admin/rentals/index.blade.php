@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="panel" style="margin: 16px;">
        <div class="ph">
            <span class="ph-title">Rentals Management</span>
        </div>
        
        <!-- Summary Cards -->
        <div class="db-metrics" style="padding: 0 16px; margin-bottom: 20px;">
            <div class="mcard">
                <div class="ml">Total Rentals</div>
                <div class="mv mv-accent">{{ $summary['total'] }}</div>
            </div>
            <div class="mcard">
                <div class="ml">Active Rentals</div>
                <div class="mv mv-green">{{ $summary['active'] }}</div>
            </div>
            <div class="mcard">
                <div class="ml">Completed</div>
                <div class="mv">{{ $summary['completed'] }}</div>
            </div>
            <div class="mcard">
                <div class="ml">Cancelled</div>
                <div class="mv mv-red">{{ $summary['cancelled'] }}</div>
            </div>
            <div class="mcard">
                <div class="ml">Total Revenue</div>
                <div class="mv mv-amber">₹{{ number_format($summary['total_revenue'], 2) }}</div>
            </div>
            <div class="mcard">
                <div class="ml">Platform Profit</div>
                <div class="mv mv-green">₹{{ number_format($summary['platform_profit'], 2) }}</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div style="padding: 0 16px; margin-bottom: 16px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div class="fstrip">
                <a href="{{ route('admin.rentals.index') }}" class="fbtn {{ !request('status') ? 'active' : '' }}">All</a>
                <a href="{{ route('admin.rentals.index', ['status' => 'active']) }}" class="fbtn {{ request('status') == 'active' ? 'active' : '' }}">Active</a>
                <a href="{{ route('admin.rentals.index', ['status' => 'completed']) }}" class="fbtn {{ request('status') == 'completed' ? 'active' : '' }}">Completed</a>
                <a href="{{ route('admin.rentals.index', ['status' => 'cancelled']) }}" class="fbtn {{ request('status') == 'cancelled' ? 'active' : '' }}">Cancelled</a>
            </div>
            
            <div class="tb-spacer"></div>
            
            <form method="GET" action="{{ route('admin.rentals.index') }}" style="display: flex; gap: 8px;">
                @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="search-box" style="width: 250px;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search customer, vehicle, plate...">
                </div>
                <button type="submit" class="btn btn-ghost" style="padding: 6px 12px;">Search</button>
                @if(request('search'))
                <a href="{{ route('admin.rentals.index', request('status') ? ['status' => request('status')] : []) }}" class="btn btn-ghost">Clear</a>
                @endif
            </form>
        </div>
        
        <!-- Rentals Table -->
        <div class="pb" style="padding: 0;">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Shop</th>
                        <th>Customer</th>
                        <th>Vehicle</th>
                        <th>Number Plate</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Total Price</th>
                        <th>Platform Profit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rentals as $rental)
                    <tr style="{{ $rental->status === 'active' ? 'border-left: 2px solid var(--green);' : '' }}">
                        <td class="tbl-mono">#{{ $rental->id }}</td>
                        <td>{{ $rental->user->name ?? 'N/A' }}</td>
                        <td>{{ $rental->customer->name ?? 'N/A' }}</td>
                        <td>{{ $rental->vehicle->name ?? 'N/A' }}</td>
                        <td class="tbl-mono">{{ $rental->vehicle->number_plate ?? 'N/A' }}</td>
                        <td class="tbl-mono">{{ $rental->start_time ? $rental->start_time->format('d M Y, h:i A') : '-' }}</td>
                        <td class="tbl-mono">{{ $rental->end_time ? $rental->end_time->format('d M Y, h:i A') : '-' }}</td>
                        <td class="tbl-mono">
                            @if($rental->start_time && $rental->end_time)
                                {{ round($rental->start_time->diffInHours($rental->end_time)) }} hrs
                            @elseif($rental->start_time && $rental->status == 'active')
                                {{ round($rental->start_time->diffInHours(now())) }} hrs (ongoing)
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $rental->status === 'completed' ? 'badge-green' : ($rental->status === 'active' ? 'badge-accent' : 'badge-red') }}">
                                {{ ucfirst($rental->status) }}
                            </span>
                        </td>
                        <td class="tbl-mono">₹{{ number_format($rental->total_price ?? 0, 2) }}</td>
                        <td class="tbl-mono">
                            @php
                                $profit = $rental->verification_completed_at ? ($rental->is_verification_cached ? 3 : 1) : 0;
                            @endphp
                            @if($profit > 0)
                            <span class="badge badge-green">₹{{ $profit }}</span>
                            @else
                            <span class="badge badge-amber">-</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.rentals.show', $rental->id) }}" class="row-btn" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 40px; color: var(--text-3);">
                            <i class="fas fa-calendar-alt" style="font-size: 48px; margin-bottom: 12px; display: block;"></i>
                            No rentals found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div style="padding: 16px;">
                {{ $rentals->links() }}
            </div>
        </div>
    </div>
</div>
@endsection