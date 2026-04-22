@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="cust-page-grid">
        <div class="cust-table-wrap">
            <div class="cust-filters">
                <div class="fstrip">
                    <button class="fbtn active" data-filter="all">All</button>
                    <button class="fbtn" data-filter="verified">Verified</button>
                    <button class="fbtn" data-filter="repeat">Repeat</button>
                </div>
            </div>
            <div class="panel cust-panel">
                <div class="ph">
                    <span class="ph-title">All Customers</span>
                    <span id="custCount">{{ $customers->total() }} records</span>
                </div>
                <div class="pb" style="padding:0;">
                    <table class="tbl">
                        <thead>
                            <tr><th>Customer</th><th>City</th><th>Shop</th><th>Trips</th><th>Verif type</th><th>Income</th>
                        </thead>
                        <tbody id="custTbody">
                            @foreach($customers as $c)
                            <tr>
                                <td><div class="av av-sm">{{ substr($c->name, 0, 2) }}</div> {{ $c->name }}</td>
                                <td>{{ $c->city ?? '—' }}</td>
                                <td>{{ $c->primary_shop ?? '—' }}</td>
                                <td>{{ $c->rentals_count }}</td>
                                <td><span class="badge badge-accent">{{ $c->rentals_count > 1 ? 'DB repeat' : 'Cashfree' }}</span></td>
                                <td>₹{{ $c->rentals_count > 0 ? 1 + ($c->rentals_count-1)*3 : 0 }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $customers->links() }}
                </div>
            </div>
        </div>
        <div class="cust-side">
            <div class="cust-detail-card" id="custDetailCard">Select a customer</div>
            <div class="cust-verif-log">
                <div class="ph"><span class="ph-title">Verification log</span></div>
                <div class="pb" id="custVerifLog"></div>
            </div>
        </div>
    </div>
</div>
@endsection