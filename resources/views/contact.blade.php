<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | EKiraya</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @php
        $footerPages = \App\Models\LegalPage::where('is_active', true)
            ->where('show_in_footer', true)
            ->orderBy('order')
            ->get();
    @endphp
    <style>
        :root {
            --bg-root: #080b12;
            --bg-surface: #0d1117;
            --bg-card: #111720;
            --bg-card-hover: #161d28;
            --border-default: rgba(255, 255, 255, 0.06);
            --border-hover: rgba(255, 255, 255, 0.12);
            --border-accent: rgba(99, 114, 242, 0.35);
            --text-primary: #edf0f7;
            --text-secondary: #8b93a5;
            --text-tertiary: #5c6378;
            --accent: #6372f2;
            --accent-soft: rgba(99, 114, 242, 0.12);
            --accent-glow: rgba(99, 114, 242, 0.25);
            --green: #3dd68c;
            --green-soft: rgba(61, 214, 140, 0.12);
            --radius-sm: 8px;
            --radius: 14px;
            --radius-lg: 20px;
            --radius-xl: 24px;
            --shadow-card: 0 1px 2px rgba(0, 0, 0, 0.3), 0 4px 16px rgba(0, 0, 0, 0.2);
            --shadow-card-hover: 0 1px 3px rgba(0, 0, 0, 0.4), 0 8px 32px rgba(0, 0, 0, 0.35);
            --transition-fast: 0.15s ease;
            --transition-smooth: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: var(--bg-root);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            letter-spacing: -0.01em;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(99, 114, 242, 0.03) 0%, transparent 70%),
                radial-gradient(ellipse 50% 40% at 20% 80%, rgba(99, 114, 242, 0.02) 0%, transparent 70%);
            background-size: 100% 100%;
        }

        /* ─── NAVIGATION ─────────────────────────── */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 6%;
            position: sticky;
            top: 16px;
            z-index: 100;
            margin: 16px 3%;
            background: rgba(13, 17, 23, 0.75);
            backdrop-filter: blur(24px) saturate(140%);
            -webkit-backdrop-filter: blur(24px) saturate(140%);
            border: 1px solid var(--border-default);
            border-radius: 60px;
            transition: all var(--transition-smooth);
        }

        .navbar.scrolled {
            background: rgba(11, 14, 20, 0.9);
            border-color: var(--border-hover);
            box-shadow: 0 4px 32px rgba(0, 0, 0, 0.4);
        }

        .nav-logo {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #a8b4ff 0%, #6372f2 40%, #4f5ed8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            gap: 0.25rem;
            align-items: center;
            list-style: none;
        }

        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: all var(--transition-fast);
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.5rem 1.1rem;
            border-radius: 50px;
            letter-spacing: -0.01em;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.03);
        }

        .nav-buttons {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .nav-btn {
            padding: 9px 22px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all var(--transition-smooth);
            letter-spacing: -0.01em;
            cursor: pointer;
            border: none;
            white-space: nowrap;
        }

        .nav-btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 2px 12px rgba(99, 114, 242, 0.25);
            position: relative;
            overflow: hidden;
        }

        .nav-btn-primary::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.1) 0%, transparent 60%);
            pointer-events: none;
            border-radius: 50px;
        }

        .nav-btn-primary:hover {
            background: #7885f5;
            box-shadow: 0 4px 20px rgba(99, 114, 242, 0.4);
            transform: translateY(-1px);
        }

        /* Mobile toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.4rem;
            cursor: pointer;
            padding: 0.4rem;
        }

        .mobile-nav {
            display: none;
            position: fixed;
            top: 80px;
            left: 3%;
            right: 3%;
            background: rgba(13, 17, 23, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            padding: 1rem;
            z-index: 99;
            flex-direction: column;
            gap: 0.25rem;
        }
        .mobile-nav.active {
            display: flex;
        }
        .mobile-nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 0.7rem 1rem;
            border-radius: var(--radius-sm);
            transition: all var(--transition-fast);
        }
        .mobile-nav a:hover {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-primary);
        }

        /* ─── PAGE HEADER ────────────────────────── */
        .page-header {
            padding: 5rem 6% 2rem;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .page-header .section-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--accent);
            margin-bottom: 0.75rem;
        }

        .page-header h1 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #f5f7fc;
            margin-bottom: 0.5rem;
        }

        .page-header .subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            max-width: 500px;
            margin: 0 auto;
            letter-spacing: -0.01em;
            line-height: 1.5;
        }

        /* ─── CONTACT GRID ───────────────────────── */
        .container {
            max-width: 1150px;
            margin: 0 auto;
            padding: 2rem 6% 5rem;
            position: relative;
            z-index: 1;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: start;
        }

        /* Contact info cards */
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .contact-card {
            background: var(--bg-card);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: all var(--transition-smooth);
            box-shadow: var(--shadow-card);
        }

        .contact-card:hover {
            border-color: var(--border-hover);
            background: var(--bg-card-hover);
            box-shadow: var(--shadow-card-hover);
            transform: translateY(-2px);
        }

        .contact-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--accent-soft);
            color: #a8b4ff;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .contact-detail h3 {
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #edf0f7;
            margin-bottom: 0.3rem;
        }

        .contact-detail p {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.5;
            letter-spacing: -0.01em;
        }

        /* Form */
        .contact-form {
            background: var(--bg-card);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-xl);
            padding: 2rem 2.25rem;
            box-shadow: var(--shadow-card);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-default);
            border-radius: var(--radius);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            outline: none;
            transition: all var(--transition-fast);
            letter-spacing: -0.01em;
        }

        input:focus,
        textarea:focus {
            border-color: var(--accent);
            background: rgba(99, 114, 242, 0.05);
            box-shadow: 0 0 0 3px rgba(99, 114, 242, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 13px 28px;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all var(--transition-smooth);
            border: none;
            cursor: pointer;
            letter-spacing: -0.01em;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 16px rgba(99, 114, 242, 0.2);
            width: 100%;
        }

        .btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.08) 0%, transparent 50%);
            pointer-events: none;
            border-radius: 50px;
        }

        .btn:hover {
            background: #7885f5;
            box-shadow: 0 6px 28px rgba(99, 114, 242, 0.35);
            transform: translateY(-2px);
        }

        .success-message {
            background: rgba(61, 214, 140, 0.08);
            border: 1px solid rgba(61, 214, 140, 0.25);
            border-radius: var(--radius);
            padding: 14px 18px;
            margin-bottom: 1.5rem;
            color: #5fe8a8;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            backdrop-filter: blur(10px);
            letter-spacing: -0.01em;
        }

        /* ─── FOOTER ─────────────────────────────── */
        .footer {
            padding: 4rem 6% 2rem;
            border-top: 1px solid var(--border-default);
            position: relative;
            z-index: 1;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 2.5rem;
            max-width: 1100px;
            margin: 0 auto 2.5rem;
        }

        .footer-col h4 {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #f5f7fc;
            margin-bottom: 1.1rem;
        }

        .footer-col a {
            display: block;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            margin-bottom: 0.55rem;
            transition: color var(--transition-fast);
            letter-spacing: -0.01em;
            font-weight: 400;
        }

        .footer-col a:hover {
            color: #c5cdf5;
        }

        .footer-col .social-icons {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .footer-col .social-icons a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 1px solid var(--border-default);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-fast);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .footer-col .social-icons a:hover {
            border-color: var(--accent);
            color: #a8b4ff;
            background: var(--accent-soft);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--border-default);
            color: var(--text-tertiary);
            font-size: 0.78rem;
            letter-spacing: 0.01em;
            max-width: 1100px;
            margin: 0 auto;
        }

        /* ─── RESPONSIVE ─────────────────────────── */
        @media (max-width: 900px) {
            .navbar {
                padding: 0.75rem 5%;
                margin: 10px 2%;
                border-radius: 40px;
            }
            .nav-links {
                display: none;
            }
            .mobile-toggle {
                display: block;
            }
            .contact-grid {
                grid-template-columns: 1fr;
            }
            .page-header {
                padding: 3rem 5% 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .contact-form {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-logo">EKiraya</div>
        <div class="nav-links">
            <a href="/">Home</a>
            <a href="/#features">Features</a>
            <a href="/#pricing">Pricing</a>
            <a href="{{ route('contact') }}" class="active">Contact</a>
        </div>
        <div class="nav-buttons">
            <a href="{{ route('admin.login') }}" class="nav-btn nav-btn-primary">Admin Login</a>
        </div>
        <button class="mobile-toggle" id="mobileToggle" aria-label="Menu">
            <i class="fas fa-bars"></i>
        </button>
    </nav>
    <div class="mobile-nav" id="mobileNav">
        <a href="/">Home</a>
        <a href="/#features">Features</a>
        <a href="/#pricing">Pricing</a>
        <a href="{{ route('contact') }}">Contact</a>
        <a href="{{ route('admin.login') }}">Admin Login</a>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="section-label">Get in touch</div>
        <h1>Contact Us</h1>
        <p class="subtitle">Have questions about EKiraya? We're here to help you modernize your rental business.</p>
    </div>

    <!-- Contact Content -->
    <div class="container">
        @if(session('success'))
        <div class="success-message">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
        @endif

        <div class="contact-grid">
            <!-- Contact Information Cards -->
            <div class="contact-info">
                <div class="contact-card">
                    <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                    <div class="contact-detail">
                        <h3>Email</h3>
                        <p>support@ekiraya.com</p>
                        <p style="margin-top: 0.25rem; font-size:0.8rem; color: var(--text-tertiary);">sales@ekiraya.com</p>
                    </div>
                </div>
                <div class="contact-card">
                    <div class="contact-icon"><i class="fas fa-phone"></i></div>
                    <div class="contact-detail">
                        <h3>Phone</h3>
                        <p>+91 98765 43210</p>
                        <p style="margin-top: 0.25rem; font-size:0.8rem; color: var(--text-tertiary);">Mon-Fri, 10AM - 6PM IST</p>
                    </div>
                </div>
                <div class="contact-card">
                    <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="contact-detail">
                        <h3>Office</h3>
                        <p>Bhubaneswar, Odisha, India</p>
                    </div>
                </div>
                <div class="contact-card">
                    <div class="contact-icon"><i class="fab fa-whatsapp"></i></div>
                    <div class="contact-detail">
                        <h3>WhatsApp</h3>
                        <p>+91 98765 43210</p>
                    </div>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="contact-form">
                <form method="POST" action="{{ route('contact.submit') }}">
                    @csrf
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" name="name" id="name" required placeholder="John Doe">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" name="email" id="email" required placeholder="john@example.com">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" name="phone" id="phone" placeholder="+91 98765 43210">
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <input type="text" name="subject" id="subject" required placeholder="How can we help?">
                    </div>
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea name="message" id="message" rows="5" required placeholder="Tell us about your rental business needs..."></textarea>
                    </div>
                    <button type="submit" class="btn">
                        Send Message <i class="fas fa-arrow-right" style="font-size:0.8rem;"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>Platform</h4>
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
                <div class="social-icons">
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; 2026 EKiraya — All rights reserved. Vehicle Rental SaaS Platform.
        </div>
    </footer>

    <script>
        // Mobile nav toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const mobileNav = document.getElementById('mobileNav');
        const navbar = document.getElementById('navbar');

        if (mobileToggle && mobileNav) {
            mobileToggle.addEventListener('click', () => {
                mobileNav.classList.toggle('active');
            });

            mobileNav.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    mobileNav.classList.remove('active');
                });
            });

            document.addEventListener('click', (e) => {
                if (!mobileNav.contains(e.target) && !mobileToggle.contains(e.target)) {
                    mobileNav.classList.remove('active');
                }
            });
        }

        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            if (navbar && window.scrollY > 20) {
                navbar.classList.add('scrolled');
            } else if (navbar) {
                navbar.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>