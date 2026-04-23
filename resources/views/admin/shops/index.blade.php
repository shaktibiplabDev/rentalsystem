@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="panel" style="margin: 16px;">
        <div class="ph">
            <span class="ph-title">All Shops ({{ $shops->total() }})</span>
            <div class="tb-spacer"></div>
            <form method="GET" action="{{ route('admin.shops.index') }}" style="display: flex; gap: 8px;">
                <div class="search-box" style="width: 250px;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email, phone..." style="width: 100%; background: transparent; border: none; outline: none; padding: 6px 10px 6px 28px; color: var(--text);">
                </div>
                <button type="submit" class="btn btn-ghost" style="padding: 4px 12px;">Search</button>
                @if(request('search'))
                <a href="{{ route('admin.shops.index') }}" class="btn btn-ghost" style="padding: 4px 12px;">Clear</a>
                @endif
            </form>
        </div>
        <div class="pb" style="padding: 0;">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Wallet</th>
                        <th>Rentals</th>
                        <th>Vehicles</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shops as $shop)
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="av av-sm" style="background: rgba(79,110,247,0.15); color: var(--accent);">
                                    {{ substr($shop->name, 0, 2) }}
                                </div>
                                <div>
                                    <div style="font-weight: 600;">{{ $shop->name }}</div>
                                    <div style="font-size: 9px; color: var(--text-3);">ID: #{{ $shop->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="tbl-mono">{{ $shop->email ?? '—' }}</td>
                        <td class="tbl-mono">{{ $shop->phone ?? '—' }}</td>
                        <td class="tbl-mono">
                            <span class="badge {{ $shop->wallet_balance > 50000 ? 'badge-green' : ($shop->wallet_balance > 10000 ? 'badge-accent' : 'badge-red') }}">
                                ₹{{ number_format($shop->wallet_balance, 2) }}
                            </span>
                        </td>
                        <td class="tbl-mono">{{ $shop->rentals_count ?? 0 }}</td>
                        <td class="tbl-mono">{{ $shop->vehicles_count ?? 0 }}</td>
                        <td>
                            @php
                                $status = $shop->status ?? 'active';
                            @endphp
                            <span class="badge {{ $status === 'active' ? 'badge-green' : 'badge-red' }}">
                                {{ ucfirst($status) }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('admin.shops.show', $shop->id) }}" class="row-btn" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-3);">
                            <i class="fas fa-store-alt" style="font-size: 48px; margin-bottom: 12px; display: block;"></i>
                            No shops found
                            @if(request('search'))
                            <br><small>Try a different search term</small>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            
            <!-- Pagination -->
            @if($shops->hasPages())
            <div style="padding: 16px;">
                {{ $shops->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection