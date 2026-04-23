<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EKiraya – Smart Vehicle Rental Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #04060f;
            color: #e2e5f0;
            font-family: 'Syne', sans-serif;
            min-height: 100vh;
        }
        
        /* Navigation */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 5%;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            position: sticky;
            top: 0;
            background: rgba(4,6,15,0.95);
            backdrop-filter: blur(20px);
            z-index: 100;
        }
        .nav-logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f6ef7, #9b72f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .nav-links {
            display: flex;
            gap: 2rem;
        }
        .nav-links a {
            color: #8892a4;
            text-decoration: none;
            transition: color 0.2s;
            font-size: 0.9rem;
        }
        .nav-links a:hover {
            color: #4f6ef7;
        }
        .nav-buttons {
            display: flex;
            gap: 1rem;
        }
        .nav-btn {
            padding: 8px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .nav-btn-primary {
            background: #4f6ef7;
            color: white;
        }
        .nav-btn-primary:hover {
            background: #3d5ce8;
            transform: translateY(-2px);
        }
        .nav-btn-outline {
            border: 1px solid rgba(255,255,255,0.2);
            color: #e2e5f0;
        }
        .nav-btn-outline:hover {
            border-color: #4f6ef7;
            color: #4f6ef7;
        }
        
        /* Hero Section */
        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4rem 5%;
            gap: 4rem;
        }
        .hero-content {
            flex: 1;
        }
        .hero-badge {
            display: inline-block;
            background: rgba(79,110,247,0.15);
            border: 1px solid rgba(79,110,247,0.3);
            border-radius: 40px;
            padding: 4px 16px;
            font-size: 0.75rem;
            color: #4f6ef7;
            margin-bottom: 1.5rem;
        }
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
        }
        .hero-title span {
            background: linear-gradient(135deg, #4f6ef7, #9b72f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            color: #8892a4;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            max-width: 500px;
        }
        .hero-stats {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
        }
        .stat {
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #4f6ef7;
        }
        .stat-label {
            font-size: 0.75rem;
            color: #8892a4;
        }
        .hero-image {
            flex: 1;
            text-align: center;
        }
        .hero-image img {
            max-width: 100%;
            border-radius: 20px;
        }
        
        /* Features Section */
        .features {
            padding: 4rem 5%;
            background: rgba(7,11,24,0.5);
        }
        .section-title {
            text-align: center;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .section-subtitle {
            text-align: center;
            color: #8892a4;
            margin-bottom: 3rem;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        .feature-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
        }
        .feature-card:hover {
            border-color: rgba(79,110,247,0.3);
            transform: translateY(-5px);
        }
        .feature-icon {
            width: 60px;
            height: 60px;
            background: rgba(79,110,247,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .feature-icon i {
            font-size: 1.5rem;
            color: #4f6ef7;
        }
        .feature-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .feature-desc {
            color: #8892a4;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        /* Pricing Section */
        .pricing {
            padding: 4rem 5%;
        }
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        .pricing-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
        }
        .pricing-card.featured {
            border-color: #4f6ef7;
            transform: scale(1.05);
        }
        .pricing-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .pricing-price {
            font-size: 3rem;
            font-weight: 800;
            color: #4f6ef7;
            margin-bottom: 0.5rem;
        }
        .pricing-period {
            color: #8892a4;
            font-size: 0.8rem;
            margin-bottom: 1.5rem;
        }
        .pricing-features {
            list-style: none;
            margin-bottom: 2rem;
        }
        .pricing-features li {
            padding: 0.5rem 0;
            color: #8892a4;
            font-size: 0.9rem;
        }
        .pricing-features i {
            color: #1fcfaa;
            margin-right: 0.5rem;
        }
        
        /* CTA Section */
        .cta {
            background: linear-gradient(135deg, rgba(79,110,247,0.1), rgba(155,114,247,0.05));
            text-align: center;
            padding: 4rem 5%;
            border-top: 1px solid rgba(79,110,247,0.2);
            border-bottom: 1px solid rgba(79,110,247,0.2);
        }
        .cta h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .cta p {
            color: #8892a4;
            margin-bottom: 2rem;
        }
        
        /* Footer */
        .footer {
            padding: 3rem 5% 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .footer-col h4 {
            font-size: 1rem;
            margin-bottom: 1rem;
            color: #e2e5f0;
        }
        .footer-col a {
            display: block;
            color: #8892a4;
            text-decoration: none;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            transition: color 0.2s;
        }
        .footer-col a:hover {
            color: #4f6ef7;
        }
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            color: #50596a;
            font-size: 0.8rem;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background: #4f6ef7;
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #3d5ce8;
            transform: translateY(-2px);
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #4f6ef7;
            color: #4f6ef7;
        }
        .btn-outline:hover {
            background: rgba(79,110,247,0.1);
        }
        
        @media (max-width: 768px) {
            .hero { flex-direction: column; text-align: center; }
            .hero-title { font-size: 2rem; }
            .nav-links { display: none; }
            .pricing-card.featured { transform: none; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-logo">EKiraya</div>
        <div class="nav-links">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#pricing">Pricing</a>
            <a href="{{ route('contact') }}">Contact</a>
        </div>
        <div class="nav-buttons">
            <a href="{{ route('admin.login') }}" class="nav-btn nav-btn-primary">Admin Login</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <div class="hero-badge">🚀 No Subscriptions • Pay as You Go</div>
            <h1 class="hero-title">
                Smart Vehicle Rental<br>
                <span>Management Platform</span>
            </h1>
            <p class="hero-desc">
                Automate customer verification, create digital agreements, and manage your fleet 
                with EKiraya's pay-per-use wallet system. No monthly fees, just pay for what you use.
            </p>
            <a href="{{ route('admin.login') }}" class="btn">Get Started →</a>
            <div class="hero-stats">
                <div class="stat"><div class="stat-number">500+</div><div class="stat-label">Active Shops</div></div>
                <div class="stat"><div class="stat-number">50K+</div><div class="stat-label">Verifications</div></div>
                <div class="stat"><div class="stat-number">100%</div><div class="stat-label">Digital</div></div>
            </div>
        </div>
        <div class="hero-image">
            <div style="background: linear-gradient(135deg, #4f6ef7, #9b72f7); width: 300px; height: 300px; border-radius: 50px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                <i class="fas fa-car" style="font-size: 100px; color: white;"></i>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <h2 class="section-title">Why Choose EKiraya?</h2>
        <p class="section-subtitle">Everything you need to run your rental business efficiently</p>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-id-card"></i></div>
                <h3 class="feature-title">Instant DL Verification</h3>
                <p class="feature-desc">Verify driving licenses instantly via Cashfree API. No manual checks needed.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-file-signature"></i></div>
                <h3 class="feature-title">Digital Agreements</h3>
                <p class="feature-desc">Generate legally binding rental agreements automatically with e-signatures.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-wallet"></i></div>
                <h3 class="feature-title">Wallet System</h3>
                <p class="feature-desc">Pay as you go. Add money to wallet, pay only for verifications you use.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3 class="feature-title">Real-time Analytics</h3>
                <p class="feature-desc">Track your earnings, rentals, and customer history at a glance.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h3 class="feature-title">Fraud Detection</h3>
                <p class="feature-desc">AI-powered fraud detection to protect your business.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                <h3 class="feature-title">Mobile Ready</h3>
                <p class="feature-desc">Manage everything from your phone with our mobile app.</p>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing" id="pricing">
        <h2 class="section-title">Simple, Transparent Pricing</h2>
        <p class="section-subtitle">No hidden fees. No subscriptions. Just pay for what you use.</p>
        <div class="pricing-grid">
            <div class="pricing-card">
                <div class="pricing-name">Pay Per Verification</div>
                <div class="pricing-price">₹5</div>
                <div class="pricing-period">per verification</div>
                <ul class="pricing-features">
                    <li><i class="fas fa-check"></i> DL Verification</li>
                    <li><i class="fas fa-check"></i> Digital Agreement</li>
                    <li><i class="fas fa-check"></i> Customer Records</li>
                    <li><i class="fas fa-check"></i> Basic Analytics</li>
                </ul>
                <a href="{{ route('admin.login') }}" class="btn btn-outline">Get Started</a>
            </div>
            <div class="pricing-card featured">
                <div class="pricing-name">Wallet Top-up</div>
                <div class="pricing-price">₹500+</div>
                <div class="pricing-period">add anytime</div>
                <ul class="pricing-features">
                    <li><i class="fas fa-check"></i> All Features Included</li>
                    <li><i class="fas fa-check"></i> Priority Support</li>
                    <li><i class="fas fa-check"></i> Advanced Analytics</li>
                    <li><i class="fas fa-check"></i> API Access</li>
                </ul>
                <a href="{{ route('admin.login') }}" class="btn">Top Up Wallet</a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <h2>Ready to modernize your rental business?</h2>
        <p>Join hundreds of rental shops already using EKiraya</p>
        <a href="{{ route('admin.login') }}" class="btn">Get Started Now →</a>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>EKiraya</h4>
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#pricing">Pricing</a>
                <a href="#">Download App</a>
            </div>
            <div class="footer-col">
                <h4>Support</h4>
                <a href="{{ route('contact') }}">Contact Us</a>
                <a href="{{ route('privacy.policy') }}">Privacy Policy</a>
                <a href="{{ route('terms.service') }}">Terms of Service</a>
                <a href="#">FAQ</a>
            </div>
            <div class="footer-col">
                <h4>Contact</h4>
                <a href="mailto:support@ekiraya.com">support@ekiraya.com</a>
                <a href="tel:+919876543210">+91 98765 43210</a>
                <div style="margin-top: 1rem;">
                    <i class="fab fa-twitter" style="margin-right: 1rem; color: #8892a4;"></i>
                    <i class="fab fa-linkedin" style="margin-right: 1rem; color: #8892a4;"></i>
                    <i class="fab fa-instagram" style="color: #8892a4;"></i>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            © 2026 EKiraya – All rights reserved. Vehicle Rental SaaS Platform
        </div>
    </footer>
</body>
</html>