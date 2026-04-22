@extends('layouts.admin')
@section('content')
<div class="page active">
    <div class="panel" style="margin:16px;">
        <div class="ph"><span class="ph-title">{{ $shop->name }} – Details</span></div>
        <div class="pb">
            <p><strong>Email:</strong> {{ $shop->email }}</p>
            <p><strong>Phone:</strong> {{ $shop->phone }}</p>
            <p><strong>Wallet:</strong> ₹{{ number_format($shop->wallet_balance,2) }}</p>
            <p><strong>Total Rentals:</strong> {{ $stats['total_rentals'] }}</p>
            <p><strong>Completed Rentals:</strong> {{ $stats['completed_rentals'] }}</p>
            <p><strong>Total Earnings:</strong> ₹{{ number_format($stats['total_earnings'],2) }}</p>
            <hr>
            <h4>Recent Rentals</h4>
            <table class="tbl">
                <thead><tr><th>ID</th><th>Vehicle</th><th>Customer</th><th>Status</th><th>Total</th></tr></thead>
                <tbody>
                    @foreach($rentals as $rental)
                    <tr>
                        <td>{{ $rental->id }}</td>
                        <td>{{ $rental->vehicle->name ?? 'N/A' }}</td>
                        <td>{{ $rental->customer->name ?? 'N/A' }}</td>
                        <td>{{ $rental->status }}</td>
                        <td>₹{{ number_format($rental->total_price,2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $rentals->links() }}
        </div>
    </div>
</div>
@endsection