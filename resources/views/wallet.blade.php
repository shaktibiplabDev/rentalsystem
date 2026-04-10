<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Payment Status</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f5f7fb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
            padding: 32px 24px;
            text-align: center;
        }
        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        .icon.success { background: #e6f7e6; color: #2e7d32; }
        .icon.error { background: #fee; color: #c62828; }
        .icon.pending { background: #fff3e0; color: #f57c00; }
        h2 { font-size: 24px; margin-bottom: 12px; color: #1e293b; }
        p { color: #475569; margin-bottom: 24px; line-height: 1.5; }
        .amount { font-size: 28px; font-weight: bold; color: #0f172a; margin: 16px 0; }
        .btn {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .loader {
            border: 3px solid #e2e8f0;
            border-top: 3px solid #4f46e5;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 0.8s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="card" id="app">
        <div id="loading" class="loader"></div>
        <div id="result" style="display: none;">
            <div id="icon" class="icon"></div>
            <h2 id="title"></h2>
            <div id="amount" class="amount"></div>
            <p id="message"></p>
            <button id="closeBtn" class="btn">Continue</button>
        </div>
    </div>

    <script>
        // Helper: get URL parameters
        function getQueryParam(param) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(param);
        }

        // Close WebView (custom scheme for Flutter)
        function closeWebView() {
            window.location.href = 'yourapp://close';
            setTimeout(() => window.close(), 100);
        }

        const orderId = getQueryParam('order_id');
        const paymentStatus = getQueryParam('payment_status');

        const loadingDiv = document.getElementById('loading');
        const resultDiv = document.getElementById('result');
        const iconDiv = document.getElementById('icon');
        const titleEl = document.getElementById('title');
        const amountEl = document.getElementById('amount');
        const messageEl = document.getElementById('message');
        const closeBtn = document.getElementById('closeBtn');

        closeBtn.addEventListener('click', closeWebView);

        if (!orderId) {
            loadingDiv.classList.add('hidden');
            resultDiv.style.display = 'block';
            iconDiv.className = 'icon error';
            iconDiv.innerHTML = '⚠️';
            titleEl.innerText = 'Invalid Order';
            messageEl.innerText = 'No order ID found. Please contact support.';
            return;
        }

        // Call your backend API: /wallet/payment-status?order_id=...
        const apiUrl = `https://rentos.versaero.top/wallet/payment-status?order_id=${encodeURIComponent(orderId)}`;

        fetch(apiUrl, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            loadingDiv.classList.add('hidden');
            resultDiv.style.display = 'block';

            if (data.success && data.data.status === 'completed') {
                const amount = data.data.amount;
                iconDiv.className = 'icon success';
                iconDiv.innerHTML = '✓';
                titleEl.innerText = 'Payment Successful!';
                amountEl.innerText = `+ ₹${amount.toFixed(2)}`;
                messageEl.innerText = `Your wallet has been recharged with ₹${amount.toFixed(2)}.`;
                // Auto-close after 3 seconds
                setTimeout(closeWebView, 3000);
            }
            else if (data.success && data.data.status === 'failed') {
                iconDiv.className = 'icon error';
                iconDiv.innerHTML = '✗';
                titleEl.innerText = 'Payment Failed';
                messageEl.innerText = 'Your payment could not be processed. Please try again.';
                amountEl.innerText = '';
            }
            else if (data.success && data.data.status === 'pending') {
                iconDiv.className = 'icon pending';
                iconDiv.innerHTML = '⏳';
                titleEl.innerText = 'Processing';
                messageEl.innerText = 'Your payment is being processed. You will receive a confirmation shortly.';
                amountEl.innerText = '';
                // Optional: poll again after a few seconds
                setTimeout(() => location.reload(), 5000);
            }
            else {
                iconDiv.className = 'icon error';
                iconDiv.innerHTML = '⚠️';
                titleEl.innerText = 'Verification Failed';
                messageEl.innerText = 'Unable to verify payment status. Please check your wallet later.';
                amountEl.innerText = '';
            }
        })
        .catch(err => {
            console.error('API error:', err);
            loadingDiv.classList.add('hidden');
            resultDiv.style.display = 'block';
            iconDiv.className = 'icon error';
            iconDiv.innerHTML = '⚠️';
            titleEl.innerText = 'Network Error';
            messageEl.innerText = 'Could not connect to server. Please check your wallet balance later.';
            amountEl.innerText = '';
        });
    </script>
</body>
</html>