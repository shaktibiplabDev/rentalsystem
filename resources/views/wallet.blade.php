<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status</title>
    <style>
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f7f9fc;
        }
        .card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        .loader {
            border: 3px solid #e2e8f0;
            border-top-color: #4f46e5;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 24px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .pending { color: #f59e0b; }
        button {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 24px;
        }
        .hidden { display: none; }
    </style>
</head>
<body>
<div class="card">
    <div id="loader" class="loader"></div>
    <div id="result" class="hidden">
        <h1 id="title"></h1>
        <p id="message" style="margin: 16px 0;"></p>
        <button onclick="closeWindow()">Continue</button>
    </div>
</div>

<script>
    function closeWindow() {
        // For Flutter WebView: use a custom scheme
        window.location.href = 'yourapp://close';
        // Fallback: try to close the tab
        setTimeout(() => window.close(), 200);
    }

    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('order_id');

    if (!orderId) {
        document.getElementById('loader').classList.add('hidden');
        document.getElementById('result').classList.remove('hidden');
        document.getElementById('title').innerHTML = '⚠️ Missing Order ID';
        document.getElementById('message').innerText = 'No order ID found in the URL.';
        return;
    }

    // Call your public status endpoint (no token required after modification)
    fetch(`/wallet/payment-status?order_id=${encodeURIComponent(orderId)}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('loader').classList.add('hidden');
            document.getElementById('result').classList.remove('hidden');

            if (data.success && data.data.status === 'completed') {
                document.getElementById('title').innerHTML = '✅ Payment Successful!';
                document.getElementById('message').innerText = `₹${data.data.amount} has been added to your wallet.`;
                // Auto close after 2 seconds
                setTimeout(closeWindow, 2000);
            } else if (data.success && data.data.status === 'failed') {
                document.getElementById('title').innerHTML = '❌ Payment Failed';
                document.getElementById('message').innerText = 'Your payment could not be processed. Please try again.';
            } else if (data.success && data.data.status === 'pending') {
                document.getElementById('title').innerHTML = '⏳ Payment Processing';
                document.getElementById('message').innerText = 'Your payment is being processed. You will receive a confirmation shortly.';
                // Optionally reload after 5 seconds to check again
                setTimeout(() => location.reload(), 5000);
            } else {
                document.getElementById('title').innerHTML = '⚠️ Unknown Status';
                document.getElementById('message').innerText = 'We could not determine the payment status. Please check your wallet later.';
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            document.getElementById('loader').classList.add('hidden');
            document.getElementById('result').classList.remove('hidden');
            document.getElementById('title').innerHTML = '⚠️ Network Error';
            document.getElementById('message').innerText = 'Could not connect to the server. Please check your internet connection.';
        });
</script>
</body>
</html>