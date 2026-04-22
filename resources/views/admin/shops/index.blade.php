@extends('layouts.admin')
@section('content')
<div class="page active">
    <div class="panel" style="margin:16px;">
        <div class="ph"><span class="ph-title">All Shops</span></div>
        <div class="pb">
            <table class="tbl">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Wallet</th><th>Rentals</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    @foreach($shops as $shop)
                    <tr>
                        <td>{{ $shop->name }}</td>
                        <td>{{ $shop->email }}</td>
                        <td>{{ $shop->phone }}</td>
                        <td>₹{{ number_format($shop->wallet_balance,2) }}</td>
                        <td>{{ $shop->rentals_count }}</td>
                        <td><span class="badge {{ $shop->status=='active'?'badge-green':'badge-red' }}">{{ $shop->status }}</span></td>
                        <td><a href="{{ route('admin.shops.show', $shop->id) }}" class="btn btn-ghost">View</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $shops->links() }}
        </div>
    </div>
</div>
@endsection