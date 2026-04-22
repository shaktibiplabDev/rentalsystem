@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="wallet-page">
        <div class="wallet-left">
            <div class="wallet-metrics">
                <div class="mcard"><div class="ml">Total Credits</div><div class="mv mv-green">₹{{ number_format($totalCredits,2) }}</div></div>
                <div class="mcard"><div class="ml">Total Debits</div><div class="mv mv-amber">₹{{ number_format($totalDebits,2) }}</div></div>
                <div class="mcard"><div class="ml">Platform Revenue</div><div class="mv mv-accent">₹{{ number_format($platformRevenue,2) }}</div></div>
            </div>
            <div class="panel">
                <div class="ph"><span class="ph-title">Transaction Log</span></div>
                <div class="pb">
                    @foreach($transactions as $t)
                    <div class="wlog-item">
                        <div class="wlog-icon {{ $t->type === 'credit' ? 'wl-credit' : 'wl-debit' }}">
                            <i class="fas {{ $t->type === 'credit' ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                        </div>
                        <div class="wlog-info">
                            <div class="wlog-desc">{{ $t->reason }}</div>
                            <div class="wlog-meta">{{ $t->user->name ?? 'System' }} · {{ $t->created_at->format('d M Y, h:i A') }}</div>
                        </div>
                        <div class="wlog-amt">{{ $t->type === 'credit' ? '+' : '-' }}₹{{ number_format($t->amount,2) }}</div>
                    </div>
                    @endforeach
                    {{ $transactions->links() }}
                </div>
            </div>
        </div>
        <div class="wallet-right">
            <div class="panel">
                <div class="ph"><span class="ph-title">Shop Balances</span></div>
                <div class="pb">
                    @foreach($shops as $s)
                    <div>
                        <div style="display:flex;justify-content:space-between;">
                            <span>{{ $s->name }}</span>
                            <span>₹{{ number_format($s->wallet_balance,2) }}</span>
                        </div>
                        <div class="prog-track">
                            <div class="prog-fill" style="width:{{ min(100, $s->wallet_balance/300000*100) }}%;background:var(--accent);"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="mcard">
                <div class="ml">Low balance alerts</div>
                <div id="lowBalanceAlerts">
                    @foreach($shops->where('wallet_balance', '<', 50000) as $s)
                    <div>⚠️ {{ $s->name }}: ₹{{ number_format($s->wallet_balance,2) }}</div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection