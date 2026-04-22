@extends('layouts.admin')

@section('content')
<div class="page active">
    <div style="padding:14px 16px 0;">
        <div class="fstrip" id="fleetFilter">
            <button class="fbtn active" data-status="all">All</button>
            <button class="fbtn" data-status="available">Available</button>
            <button class="fbtn" data-status="on_rent">On rent</button>
            <button class="fbtn" data-status="unavailable">Maintenance</button>
        </div>
    </div>
    <div class="fleet-grid" id="fleetGrid">
        @forelse($vehicles as $vehicle)
        <div class="fleet-card" data-status="{{ $vehicle->status }}">
            <div class="fc-top">
                <div>
                    <div class="fc-model">{{ $vehicle->name }}</div>
                    <div class="fc-plate">{{ $vehicle->number_plate }}</div>
                </div>
                <span class="badge badge-{{ $vehicle->status === 'available' ? 'green' : ($vehicle->status === 'on_rent' ? 'accent' : 'red') }}">
                    {{ ucfirst(str_replace('_', ' ', $vehicle->status)) }}
                </span>
            </div>
            <div class="fc-meta">
                <div class="fc-meta-item">
                    <div class="fc-meta-l">Hourly Rate</div>
                    <div class="fc-meta-v">₹{{ number_format($vehicle->hourly_rate ?? 0, 2) }}</div>
                </div>
                <div class="fc-meta-item">
                    <div class="fc-meta-l">Daily Rate</div>
                    <div class="fc-meta-v">₹{{ number_format($vehicle->daily_rate ?? 0, 2) }}</div>
                </div>
            </div>
            <div class="fc-shop">
                <div class="ibox ibox-sm" style="background:rgba(79,110,247,0.1);"><i class="fas fa-store-alt"></i></div>
                <div class="fc-shop-name">{{ $vehicle->user->name ?? 'Unknown Shop' }}</div>
            </div>
        </div>
        @empty
        <div class="panel" style="padding: 20px; text-align: center;">No vehicles found.</div>
        @endforelse
    </div>
    <div style="padding: 0 16px 16px;">
        {{ $vehicles->links() }}
    </div>
</div>

<script>
    document.querySelectorAll('#fleetFilter .fbtn').forEach(btn => {
        btn.addEventListener('click', function() {
            const status = this.dataset.status;
            document.querySelectorAll('#fleetFilter .fbtn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            document.querySelectorAll('.fleet-card').forEach(card => {
                if (status === 'all') {
                    card.style.display = '';
                } else {
                    card.style.display = card.dataset.status === status ? '' : 'none';
                }
            });
        });
    });
</script>
@endsection