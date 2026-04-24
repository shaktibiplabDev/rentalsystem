@extends('layouts.public')

@section('title', 'Payment Status | EKiraya')
@section('meta_description', 'Check wallet payment status for EKiraya recharge transactions.')

@section('content')
<section class="section">
    <div class="container status-wrap">
        <div class="status-card">
            <div id="walletLoader" class="spinner" aria-hidden="true"></div>
            <div id="walletResult" class="hidden">
                <span id="walletPill" class="pill">Status</span>
                <h1 id="walletTitle" style="font-size:1.8rem; margin-bottom:8px;">Checking payment</h1>
                <p id="walletMessage" style="max-width:52ch; margin:0 auto;"></p>
                <button id="walletContinue" class="btn btn-primary" style="margin-top:18px;" type="button">Continue</button>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    (() => {
        const loader = document.getElementById('walletLoader');
        const result = document.getElementById('walletResult');
        const title = document.getElementById('walletTitle');
        const message = document.getElementById('walletMessage');
        const pill = document.getElementById('walletPill');
        const button = document.getElementById('walletContinue');

        const closeWindow = () => {
            window.location.href = 'yourapp://close';
            setTimeout(() => window.close(), 250);
        };

        button.addEventListener('click', closeWindow);

        const showResult = (state, heading, bodyText) => {
            loader.classList.add('hidden');
            result.classList.remove('hidden');

            title.textContent = heading;
            message.textContent = bodyText;

            pill.textContent = state === 'ok' ? 'Payment Success' : 'Payment Update';
            pill.classList.remove('ok', 'bad');
            pill.classList.add(state === 'ok' ? 'ok' : 'bad');
        };

        const params = new URLSearchParams(window.location.search);
        const orderId = params.get('order_id');

        if (!orderId) {
            showResult('bad', 'Missing order reference', 'Order ID is not present in this URL.');
            return;
        }

        let retries = 0;
        const maxRetries = 5;

        const checkStatus = async () => {
            try {
                const endpoint = `${window.location.origin}/api/wallet/payment-status?order_id=${encodeURIComponent(orderId)}`;
                const response = await fetch(endpoint, { headers: { 'Accept': 'application/json' } });
                const data = await response.json();

                if (!data || !data.success || !data.data || !data.data.status) {
                    throw new Error('Unexpected payment response.');
                }

                const status = String(data.data.status).toLowerCase();
                if (status === 'completed') {
                    const amount = data.data.amount ?? '';
                    showResult('ok', 'Payment successful', `Amount ₹${amount} has been added to your wallet.`);
                    setTimeout(closeWindow, 1800);
                    return;
                }

                if (status === 'failed') {
                    showResult('bad', 'Payment failed', 'The transaction did not complete. Please retry from the app.');
                    return;
                }

                if (status === 'pending' && retries < maxRetries) {
                    retries += 1;
                    setTimeout(checkStatus, 2500);
                    return;
                }

                showResult('bad', 'Payment pending', 'The transaction is still pending. Please recheck from the app wallet shortly.');
            } catch (error) {
                showResult('bad', 'Status unavailable', 'We could not verify your payment right now. Please check wallet balance in the app.');
            }
        };

        checkStatus();
    })();
</script>
@endpush
