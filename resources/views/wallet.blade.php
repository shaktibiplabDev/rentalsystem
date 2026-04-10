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
        window.location.href = 'yourapp://close';
        setTimeout(() => window.close(), 200);
    }

    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('order_id');

    if (!orderId) {
        showResult('⚠️ Missing Order ID', 'No order ID found.');
    } else {
        checkStatus(orderId);
    }

    let retryCount = 0;
    const MAX_RETRIES = 5;

    function checkStatus(orderId) {

        fetch(`${window.location.origin}/api/wallet/payment-status?order_id=${encodeURIComponent(orderId)}`)
            .then(async (response) => {

                const text = await response.text();

                // 🔥 DEBUG (remove later)
                console.log("RAW RESPONSE:", text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error("Invalid JSON response");
                }

                return data;
            })
            .then(data => {

                if (!data.success) {
                    throw new Error("API returned failure");
                }

                const status = data.data.status;

                // ✅ SUCCESS
                if (status === 'completed') {
                    showResult(
                        '✅ Payment Successful!',
                        `₹${data.data.amount} added to your wallet`
                    );

                    setTimeout(closeWindow, 2000);
                    return;
                }

                // ❌ FAILED
                if (status === 'failed') {
                    showResult(
                        '❌ Payment Failed',
                        'Your payment could not be processed.'
                    );
                    return;
                }

                // ⏳ PENDING
                if (status === 'pending') {

                    if (retryCount < MAX_RETRIES) {
                        retryCount++;
                        setTimeout(() => checkStatus(orderId), 3000);
                    } else {
                        showResult(
                            '⏳ Taking longer than expected',
                            'Please check your wallet manually.'
                        );
                    }
                }

            })
            .catch(error => {
                console.error("Fetch Error:", error);

                showResult(
                    '⚠️ Something went wrong',
                    'Unable to verify payment. Please check wallet.'
                );
            });
    }

    function showResult(title, message) {
        document.getElementById('loader').classList.add('hidden');
        document.getElementById('result').classList.remove('hidden');

        document.getElementById('title').innerHTML = title;
        document.getElementById('message').innerText = message;
    }
</script>
</body>
</html>