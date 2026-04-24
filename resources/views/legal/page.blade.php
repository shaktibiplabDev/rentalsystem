<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page['meta_title'] ?? $page['title'] ?? 'Legal' }} | EKiraya</title>
    <meta name="description" content="{{ $page['meta_description'] ?? '' }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @php
        // Fetch footer pages as an array (cached)
        $footerPages = \App\Models\LegalPage::getFooterPages();
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

        * { margin: 0; padding: 0; box-sizing: border-box; }

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

        /* Navigation */
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
            text-decoration: none;
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

        .nav-links a:hover {
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

        /* Page Content */
        .page-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 3rem 6% 4rem;
            position: relative;
            z-index: 1;
        }

        .content-card {
            background: var(--bg-card);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-xl);
            padding: 2.5rem;
            box-shadow: var(--shadow-card);
            backdrop-filter: blur(10px);
        }

        .page-title {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #f5f7fc;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }

        .last-updated {
            color: var(--text-tertiary);
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .last-updated i {
            font-size: 0.7rem;
            color: var(--accent);
        }

        .legal-content {
            color: var(--text-secondary);
            line-height: 1.7;
            font-size: 0.95rem;
        }

        .legal-content h2 {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 2.25rem 0 1rem;
            color: #f5f7fc;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-default);
        }

        .legal-content h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 1.75rem 0 0.75rem;
            color: #e0e4f2;
            letter-spacing: -0.02em;
        }

        .legal-content p {
            margin-bottom: 1.1rem;
            line-height: 1.65;
        }

        .legal-content ul,
        .legal-content ol {
            margin-left: 1.75rem;
            margin-bottom: 1.1rem;
            padding-left: 0.5rem;
        }

        .legal-content li {
            margin-bottom: 0.55rem;
        }

        .legal-content a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px solid rgba(99, 114, 242, 0.3);
            transition: border-color var(--transition-fast);
        }

        .legal-content a:hover {
            border-bottom-color: var(--accent);
        }

        .back-link-wrapper {
            margin-top: 2.5rem;
            padding-top: 1.75rem;
            border-top: 1px solid var(--border-default);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color var(--transition-fast);
            letter-spacing: -0.01em;
        }

        .back-link:hover {
            color: #bcc5f0;
        }

        .back-link i {
            font-size: 0.8rem;
            transition: transform var(--transition-fast);
        }

        .back-link:hover i {
            transform: translateX(-3px);
        }

        /* Footer */
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

        @media (max-width: 900px) {
            .navbar {
                padding: 0.75rem 5%;
                margin: 10px 2%;
                border-radius: 40px;
            }
            .nav-links { display: none; }
            .mobile-toggle { display: block; }
            .content-card { padding: 1.75rem; }
        }

        @media (max-width: 480px) {
            .page-container { padding: 2rem 4% 3rem; }
            .content-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <a href="/" class="nav-logo">EKiraya</a>
        <div class="nav-links">
            <a href="/">Home</a>
            <a href="/#features">Features</a>
            <a href="/#pricing">Pricing</a>
            <a href="{{ route('contact') }}">Contact</a>
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

    <!-- Page Content -->
    <div class="page-container">
        <div class="content-card">
            <h1 class="page-title">{{ $page['title'] ?? 'Legal Page' }}</h1>
            @if(!empty($page['published_at']))
                <div class="last-updated">
                    <i class="fas fa-clock"></i> Last Updated: {{ \Carbon\Carbon::parse($page['published_at'])->format('F d, Y') }}
                </div>
            @endif
            <div class="legal-content">
                {!! $page['content'] ?? '' !!}
            </div>
            <div class="back-link-wrapper">
                <a href="/" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
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
                @foreach($footerPages as $footerPage)
                    <a href="{{ route('legal.page', $footerPage['slug']) }}">{{ $footerPage['title'] }}</a>
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