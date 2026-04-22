<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RENT·AI – Vehicle Rental Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #04060f;
            color: #e2e5f0;
            font-family: 'Syne', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        .logo {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f6ef7, #9b72f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        .tagline {
            font-size: 1.2rem;
            color: #8892a4;
            margin-bottom: 2rem;
            font-family: 'DM Mono', monospace;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            background: #4f6ef7;
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.2s;
            margin: 0.5rem;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #4f6ef7;
            color: #4f6ef7;
        }
        .btn:hover {
            transform: translateY(-2px);
            background: #3d5ce8;
        }
        .btn-outline:hover {
            background: rgba(79,110,247,0.1);
        }
        .footer {
            text-align: center;
            padding: 1.5rem;
            color: #50596a;
            font-size: 0.8rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .features {
            display: flex;
            gap: 2rem;
            justify-content: center;
            margin-top: 3rem;
            flex-wrap: wrap;
        }
        .feature {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 1.5rem;
            width: 220px;
        }
        .feature i { font-size: 2rem; color: #4f6ef7; margin-bottom: 1rem; }
        .feature h3 { font-size: 1rem; margin-bottom: 0.5rem; }
        .feature p { font-size: 0.8rem; color: #8892a4; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="hero">
        <div class="logo">RENT·AI</div>
        <div class="tagline">Intelligent vehicle rental management</div>
        <div>
            <a href="{{ route('admin.login') }}" class="btn">Admin Login</a>
            <a href="#" class="btn btn-outline">Download App</a>
        </div>
        <div class="features">
            <div class="feature"><i class="fas fa-id-card"></i><h3>DL Verification</h3><p>Instant driving license verification via Cashfree</p></div>
            <div class="feature"><i class="fas fa-wallet"></i><h3>Wallet System</h3><p>Secure wallet for verification fees</p></div>
            <div class="feature"><i class="fas fa-chart-line"></i><h3>Analytics</h3><p>Real-time revenue & fraud detection</p></div>
        </div>
    </div>
    <div class="footer">
        © 2026 RENT·AI – All rights reserved.
    </div>
</body>
</html>