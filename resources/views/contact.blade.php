<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | EKiraya</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @php
        $footerPages = \App\Models\LegalPage::where('is_active', true)
            ->where('show_in_footer', true)
            ->orderBy('order')
            ->get();
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #04060f;
            color: #e2e5f0;
            font-family: 'Syne', sans-serif;
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 5%;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            background: rgba(4,6,15,0.95);
            backdrop-filter: blur(20px);
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
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .subtitle {
            color: #8892a4;
            margin-bottom: 3rem;
            font-size: 1.1rem;
        }
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }
        .contact-info {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 2rem;
        }
        .contact-method {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
        }
        .contact-icon {
            width: 50px;
            height: 50px;
            background: rgba(79,110,247,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .contact-icon i {
            font-size: 1.2rem;
            color: #4f6ef7;
        }
        .contact-detail h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        .contact-detail p {
            color: #8892a4;
            font-size: 0.9rem;
        }
        .contact-form {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            color: #8892a4;
        }
        input, textarea {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #e2e5f0;
            font-family: 'Syne', sans-serif;
            outline: none;
        }
        input:focus, textarea:focus {
            border-color: #4f6ef7;
            background: rgba(79,110,247,0.05);
        }
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background: #4f6ef7;
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
        }
        .btn:hover {
            background: #3d5ce8;
            transform: translateY(-2px);
        }
        .footer {
            padding: 3rem 5% 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            margin-top: 4rem;
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
        }
        .footer-col a {
            display: block;
            color: #8892a4;
            text-decoration: none;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
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
        .success {
            background: rgba(31,207,170,0.15);
            border: 1px solid rgba(31,207,170,0.3);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 1rem;
            color: #1fcfaa;
            text-align: center;
        }
        @media (max-width: 768px) {
            .contact-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-logo">EKiraya</div>
        <div class="nav-links">
            <a href="/">Home</a>
            <a href="/#features">Features</a>
            <a href="/#pricing">Pricing</a>
            <a href="{{ route('contact') }}">Contact</a>
        </div>
        <div class="nav-buttons">
            <a href="{{ route('admin.login') }}" class="nav-btn nav-btn-primary">Admin Login</a>
        </div>
    </nav>

    <div class="container">
        <h1>Contact Us</h1>
        <div class="subtitle">We'd love to hear from you. Reach out with any questions or feedback.</div>

        @if(session('success'))
        <div class="success">{{ session('success') }}</div>
        @endif

        <div class="contact-grid">
            <div class="contact-info">
                <div class="contact-method">
                    <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                    <div class="contact-detail">
                        <h3>Email</h3>
                        <p>support@ekiraya.com</p>
                        <p>sales@ekiraya.com</p>
                    </div>
                </div>
                <div class="contact-method">
                    <div class="contact-icon"><i class="fas fa-phone"></i></div>
                    <div class="contact-detail">
                        <h3>Phone</h3>
                        <p>+91 98765 43210</p>
                        <p>Mon-Fri, 10AM - 6PM IST</p>
                    </div>
                </div>
                <div class="contact-method">
                    <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="contact-detail">
                        <h3>Office</h3>
                        <p>Bhubaneswar, Odisha, India</p>
                    </div>
                </div>
                <div class="contact-method">
                    <div class="contact-icon"><i class="fab fa-whatsapp"></i></div>
                    <div class="contact-detail">
                        <h3>WhatsApp</h3>
                        <p>+91 98765 43210</p>
                    </div>
                </div>
            </div>

            <div class="contact-form">
                <form method="POST" action="{{ route('contact.submit') }}">
                    @csrf
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone">
                    </div>
                    <div class="form-group">
                        <label>Subject *</label>
                        <input type="text" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label>Message *</label>
                        <textarea name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn">Send Message →</button>
                </form>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>EKiraya</h4>
                <a href="/">Home</a>
                <a href="/#features">Features</a>
                <a href="/#pricing">Pricing</a>
                <a href="#">Download App</a>
            </div>
            <div class="footer-col">
                <h4>Support</h4>
                <a href="{{ route('contact') }}">Contact Us</a>
                @foreach($footerPages as $page)
                <a href="{{ route('legal.page', $page->slug) }}">{{ $page->title }}</a>
                @endforeach
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