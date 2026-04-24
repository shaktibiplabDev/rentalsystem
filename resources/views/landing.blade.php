<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EKiraya – Smart Vehicle Rental Management</title>
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
            --shadow-button: 0 2px 8px rgba(99, 114, 242, 0.3);
            --transition-fast: 0.15s ease;
            --transition-smooth: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-spring: 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
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
        }

        /* Subtle background grain/dot pattern */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(99, 114, 242, 0.03) 0%, transparent 70%),
                radial-gradient(ellipse 40% 50% at 90% 50%, rgba(61, 214, 140, 0.02) 0%, transparent 70%),
                radial-gradient(ellipse 50% 40% at 10% 80%, rgba(99, 114, 242, 0.02) 0%, transparent 70%);
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
            position: relative;
        }

        .nav-logo::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(99, 114, 242, 0.4), transparent);
            border-radius: 1px;
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
            position: relative;
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

        .nav-btn-outline {
            border: 1px solid var(--border-hover);
            color: var(--text-primary);
            background: transparent;
        }

        .nav-btn-outline:hover {
            border-color: var(--accent);
            color: #fff;
            background: var(--accent-soft);
        }

        /* ─── HERO ────────────────────────────────── */
        .hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            padding: 3rem 6% 5rem;
            gap: 3rem;
            position: relative;
            z-index: 1;
            max-width: 1300px;
            margin: 0 auto;
        }

        .hero-content {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--accent-soft);
            border: 1px solid var(--border-accent);
            border-radius: 50px;
            padding: 6px 18px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #a8b4ff;
            width: fit-content;
            letter-spacing: -0.01em;
        }

        .hero-badge .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #3dd68c;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(61, 214, 140, 0.6);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(61, 214, 140, 0);
            }
        }

        .hero-title {
            font-size: clamp(2.2rem, 4.5vw, 3.6rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.03em;
            color: #f5f7fc;
        }

        .hero-title .highlight {
            background: linear-gradient(135deg, #a8b4ff 0%, #6372f2 55%, #9b8bff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }

        .hero-desc {
            color: var(--text-secondary);
            font-size: 1.05rem;
            line-height: 1.65;
            max-width: 480px;
            font-weight: 400;
            letter-spacing: -0.01em;
        }

        .hero-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 0.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
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

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-hover);
            color: var(--text-primary);
            box-shadow: none;
        }

        .btn-outline:hover {
            border-color: var(--accent);
            background: var(--accent-soft);
            box-shadow: 0 2px 16px rgba(99, 114, 242, 0.1);
            transform: translateY(-2px);
        }

        .hero-stats {
            display: flex;
            gap: 2.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-default);
            margin-top: 1rem;
        }

        .stat {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .stat-number {
            font-size: 1.7rem;
            font-weight: 800;
            color: #f5f7fc;
            letter-spacing: -0.02em;
            font-family: 'JetBrains Mono', 'Inter', monospace;
        }

        .stat-label {
            font-size: 0.78rem;
            color: var(--text-tertiary);
            font-weight: 500;
            letter-spacing: -0.01em;
        }

        .hero-visual {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .hero-card-stack {
            position: relative;
            width: 320px;
            height: 380px;
        }

        .hero-card {
            position: absolute;
            background: var(--bg-card);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
            transition: all var(--transition-spring);
        }

        .hero-card.main {
            width: 280px;
            height: 340px;
            top: 20px;
            left: 20px;
            z-index: 3;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            border-color: var(--border-accent);
            box-shadow: 0 8px 40px rgba(99, 114, 242, 0.15);
        }

        .hero-card.main .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(99, 114, 242, 0.2), rgba(155, 139, 255, 0.15));
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-accent);
        }

        .hero-card.main .icon-circle i {
            font-size: 2.2rem;
            color: #a8b4ff;
        }

        .hero-card.main .card-label {
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: -0.02em;
            color: #f5f7fc;
        }

        .hero-card.main .card-sub {
            font-size: 0.8rem;
            color: var(--text-tertiary);
        }

        .hero-card.secondary {
            width: 240px;
            height: 140px;
            bottom: 0;
            right: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-radius: var(--radius);
        }

        .hero-card.tertiary {
            width: 200px;
            height: 100px;
            top: 0;
            right: 10px;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-radius: var(--radius);
        }

        /* ─── SECTION COMMONS ────────────────────── */
        section {
            position: relative;
            z-index: 1;
        }

        .section-label {
            text-align: center;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--accent);
            margin-bottom: 0.75rem;
        }

        .section-title {
            text-align: center;
            font-size: clamp(1.6rem, 3vw, 2.4rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 0.75rem;
            color: #f5f7fc;
        }

        .section-subtitle {
            text-align: center;
            color: var(--text-secondary);
            font-size: 1rem;
            max-width: 550px;
            margin: 0 auto 3.5rem;
            letter-spacing: -0.01em;
            line-height: 1.6;
        }

        /* ─── FEATURES ───────────────────────────── */
        .features {
            padding: 5rem 6% 6rem;
            background: rgba(13, 17, 23, 0.5);
            border-top: 1px solid var(--border-default);
            border-bottom: 1px solid var(--border-default);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.25rem;
            max-width: 1150px;
            margin: 0 auto;
        }

        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            padding: 1.75rem;
            transition: all var(--transition-smooth);
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
        }

        .feature-card:hover {
            border-color: var(--border-hover);
            background: var(--bg-card-hover);
            box-shadow: var(--shadow-card-hover);
            transform: translateY(-3px);
        }

        .feature-card .fc-icon {
            width: 46px;
            height: 46px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .fc-icon.blue {
            background: rgba(99, 114, 242, 0.12);
            color: #a8b4ff;
        }
        .fc-icon.green {
            background: var(--green-soft);
            color: #5fe8a8;
        }
        .fc-icon.amber {
            background: rgba(245, 158, 11, 0.1);
            color: #fbbf24;
        }
        .fc-icon.rose {
            background: rgba(244, 114, 182, 0.1);
            color: #f9a8d4;
        }
        .fc-icon.cyan {
            background: rgba(34, 211, 238, 0.1);
            color: #67e8f9;
        }
        .fc-icon.violet {
            background: rgba(167, 139, 250, 0.1);
            color: #c4b5fd;
        }

        .feature-card h3 {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #edf0f7;
        }

        .feature-card p {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.55;
            letter-spacing: -0.01em;
        }

        /* ─── PRICING ────────────────────────────── */
        .pricing {
            padding: 5rem 6% 6rem;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            max-width: 900px;
            margin: 0 auto;
        }

        .pricing-card {
            background: var(--bg-card);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-xl);
            padding: 2.25rem 2rem;
            text-align: center;
            transition: all var(--transition-smooth);
            position: relative;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .pricing-card.featured {
            border-color: var(--border-accent);
            background: linear-gradient(180deg, rgba(99, 114, 242, 0.06) 0%, var(--bg-card) 40%);
            box-shadow: 0 8px 48px rgba(99, 114, 242, 0.12);
        }

        .pricing-card.featured::before {
            content: 'Most Popular';
            position: absolute;
            top: -13px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--accent);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 5px 16px;
            border-radius: 50px;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .pricing-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-secondary);
            letter-spacing: -0.01em;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.04em;
        }

        .pricing-price {
            font-size: 3.2rem;
            font-weight: 900;
            letter-spacing: -0.04em;
            color: #f5f7fc;
            font-family: 'JetBrains Mono', 'Inter', monospace;
            line-height: 1;
        }

        .pricing-price small {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-tertiary);
            letter-spacing: -0.01em;
            font-family: 'Inter', sans-serif;
        }

        .pricing-period {
            color: var(--text-tertiary);
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: -0.01em;
        }

        .pricing-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            text-align: left;
            padding: 0 0.5rem;
        }

        .pricing-features li {
            color: var(--text-secondary);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            letter-spacing: -0.01em;
        }

        .pricing-features li i {
            color: var(--green);
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .pricing-card .btn {
            margin-top: auto;
            justify-content: center;
            width: 100%;
        }

        .pricing-card.featured .btn {
            background: var(--accent);
            box-shadow: 0 4px 24px rgba(99, 114, 242, 0.3);
        }

        /* ─── CTA ────────────────────────────────── */
        .cta {
            background: linear-gradient(180deg, rgba(99, 114, 242, 0.04) 0%, rgba(99, 114, 242, 0.01) 100%);
            text-align: center;
            padding: 5rem 6%;
            border-top: 1px solid var(--border-default);
            border-bottom: 1px solid var(--border-default);
        }

        .cta-inner {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .cta h2 {
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #f5f7fc;
        }

        .cta p {
            color: var(--text-secondary);
            font-size: 1rem;
            letter-spacing: -0.01em;
        }

        .cta .btn {
            margin-top: 0.75rem;
            font-size: 1rem;
            padding: 15px 34px;
        }

        /* ─── FOOTER ─────────────────────────────── */
        .footer {
            padding: 4rem 6% 2rem;
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

        /* ─── MOBILE NAV TOGGLE ──────────────────── */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.4rem;
            cursor: pointer;
            padding: 0.4rem;
        }

        /* ─── RESPONSIVE ─────────────────────────── */
        @media (max-width: 900px) {
            .hero {
                grid-template-columns: 1fr;
                text-align: center;
                padding: 2rem 5% 3rem;
                gap: 2rem;
            }
            .hero-content {
                align-items: center;
            }
            .hero-desc {
                max-width: 100%;
            }
            .hero-stats {
                justify-content: center;
                flex-wrap: wrap;
                gap: 1.5rem;
            }
            .hero-card-stack {
                width: 260px;
                height: 300px;
            }
            .hero-card.main {
                width: 220px;
                height: 270px;
                top: 10px;
                left: 20px;
            }
            .hero-card.main .icon-circle {
                width: 60px;
                height: 60px;
            }
            .hero-card.main .icon-circle i {
                font-size: 1.6rem;
            }
            .hero-card.secondary {
                width: 180px;
                height: 110px;
            }
            .hero-card.tertiary {
                width: 150px;
                height: 80px;
                right: 0;
            }
            .nav-links {
                display: none;
            }
            .navbar {
                padding: 0.75rem 5%;
                margin: 10px 2%;
                border-radius: 40px;
            }
            .mobile-toggle {
                display: block;
            }
            .pricing-card.featured {
                transform: none;
            }
            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .hero-actions {
                flex-direction: column;
                width: 100%;
            }
            .hero-actions .btn {
                width: 100%;
                justify-content: center;
            }
            .nav-buttons .nav-btn-outline {
                display: none;
            }
            .hero-card-stack {
                width: 200px;
                height: 240px;
            }
            .hero-card.main {
                width: 170px;
                height: 210px;
                top: 10px;
                left: 15px;
                padding: 1rem;
            }
            .hero-card.main .icon-circle {
                width: 48px;
                height: 48px;
            }
            .hero-card.main .icon-circle i {
                font-size: 1.2rem;
            }
            .hero-card.main .card-label {
                font-size: 0.9rem;
            }
            .hero-card.secondary {
                width: 140px;
                height: 85px;
                padding: 0.75rem;
                font-size: 0.75rem;
            }
            .hero-card.tertiary {
                width: 110px;
                height: 60px;
                padding: 0.6rem;
                font-size: 0.7rem;
                right: -5px;
            }
            .pricing-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ─── MOBILE NAV DRAWER ──────────────────── */
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-logo">EKiraya</div>
        <div class="nav-links">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#pricing">Pricing</a>
            <a href="{{ route('contact') }}">Contact</a>
        </div>
        <div class="nav-buttons">
            <a href="{{ route('contact') }}" class="nav-btn nav-btn-outline">Support</a>
            <a href="{{ route('admin.login') }}" class="nav-btn nav-btn-primary">Admin Login</a>
        </div>
        <button class="mobile-toggle" id="mobileToggle" aria-label="Menu">
            <i class="fas fa-bars"></i>
        </button>
    </nav>
    <div class="mobile-nav" id="mobileNav">
        <a href="#home">Home</a>
        <a href="#features">Features</a>
        <a href="#pricing">Pricing</a>
        <a href="{{ route('contact') }}">Contact</a>
        <a href="{{ route('admin.login') }}">Admin Login</a>
    </div>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <div class="hero-badge">
                <span class="dot"></span> No Subscriptions — Pay as You Go
            </div>
            <h1 class="hero-title">
                Run your rental business<br>
                <span class="highlight">smarter & faster</span>
            </h1>
            <p class="hero-desc">
                Automate driving license verifications, generate digital rental agreements,
                and manage your fleet — all from a single dashboard. Top up your wallet and
                pay only for what you use.
            </p>
            <div class="hero-actions">
                <a href="{{ route('admin.login') }}" class="btn">
                    Get Started <i class="fas fa-arrow-right" style="font-size:0.8rem;"></i>
                </a>
                <a href="#features" class="btn btn-outline">
                    Explore Features
                </a>
            </div>
            <div class="hero-stats">
                <div class="stat">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Active Rental Shops</div>
                </div>
                <div class="stat">
                    <div class="stat-number">50K+</div>
                    <div class="stat-label">Verifications Done</div>
                </div>
                <div class="stat">
                    <div class="stat-number">₹5</div>
                    <div class="stat-label">Per Verification</div>
                </div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="hero-card-stack">
                <div class="hero-card main">
                    <div class="icon-circle">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="card-label">Smart Rental</div>
                    <div class="card-sub">Digital Agreements</div>
                </div>
                <div class="hero-card secondary">
                    <i class="fas fa-shield-alt" style="font-size:1.5rem;color:#5fe8a8;"></i>
                    <div>
                        <div style="font-weight:700;font-size:0.85rem;color:#edf0f7;">Instant DL Verify</div>
                        <div style="font-size:0.7rem;color:var(--text-tertiary);">Cashfree API</div>
                    </div>
                </div>
                <div class="hero-card tertiary">
                    <i class="fas fa-wallet" style="font-size:1.2rem;color:#fbbf24;"></i>
                    <div>
                        <div style="font-weight:700;font-size:0.8rem;color:#edf0f7;">Wallet System</div>
                        <div style="font-size:0.68rem;color:var(--text-tertiary);">Pay Per Use</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-label">Core Features</div>
        <h2 class="section-title">Everything your rental shop needs</h2>
        <p class="section-subtitle">
            Purpose-built tools that replace paperwork, reduce fraud, and accelerate your workflow.
        </p>
        <div class="features-grid">
            <div class="feature-card">
                <div class="fc-icon blue"><i class="fas fa-id-card"></i></div>
                <h3>Instant DL Verification</h3>
                <p>Verify any Indian driving license in seconds via Cashfree API integration. No manual cross-checking needed.</p>
            </div>
            <div class="feature-card">
                <div class="fc-icon green"><i class="fas fa-file-signature"></i></div>
                <h3>Digital Agreements</h3>
                <p>Auto-generate legally binding rental contracts with e-signature support. Store them securely in the cloud.</p>
            </div>
            <div class="feature-card">
                <div class="fc-icon amber"><i class="fas fa-wallet"></i></div>
                <h3>Pay-Per-Use Wallet</h3>
                <p>Top up your wallet anytime. Pay only ₹5 per verification. No recurring subscriptions or hidden fees.</p>
            </div>
            <div class="feature-card">
                <div class="fc-icon rose"><i class="fas fa-chart-pie"></i></div>
                <h3>Real-Time Analytics</h3>
                <p>Track earnings, rental frequency, customer patterns, and wallet usage from a clean analytics dashboard.</p>
            </div>
            <div class="feature-card">
                <div class="fc-icon violet"><i class="fas fa-shield-haltered"></i></div>
                <h3>Fraud Detection</h3>
                <p>AI-powered checks flag suspicious licenses and duplicate customer profiles before you hand over the keys.</p>
            </div>
            <div class="feature-card">
                <div class="fc-icon cyan"><i class="fas fa-mobile-screen"></i></div>
                <h3>Mobile Ready</h3>
                <p>Manage your shop from any device. The responsive dashboard works seamlessly on phones and tablets.</p>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing" id="pricing">
        <div class="section-label">Pricing</div>
        <h2 class="section-title">Transparent & simple</h2>
        <p class="section-subtitle">
            No tiers, no commitments. Just add funds and pay for exactly what you use.
        </p>
        <div class="pricing-grid">
            <div class="pricing-card">
                <div class="pricing-name">Standard Rate</div>
                <div class="pricing-price">₹5 <small>/ verify</small></div>
                <div class="pricing-period">Deducted from wallet balance</div>
                <ul class="pricing-features">
                    <li><i class="fas fa-check-circle"></i> DL Verification</li>
                    <li><i class="fas fa-check-circle"></i> Digital Agreement Generation</li>
                    <li><i class="fas fa-check-circle"></i> Customer Record Keeping</li>
                    <li><i class="fas fa-check-circle"></i> Basic Analytics Dashboard</li>
                    <li><i class="fas fa-check-circle"></i> Email Support</li>
                </ul>
                <a href="{{ route('admin.login') }}" class="btn btn-outline">Start Free Trial</a>
            </div>
            <div class="pricing-card featured">
                <div class="pricing-name">Wallet Top-Up</div>
                <div class="pricing-price">₹500+ <small>top-up</small></div>
                <div class="pricing-period">Add funds anytime — no expiry</div>
                <ul class="pricing-features">
                    <li><i class="fas fa-check-circle"></i> Everything in Standard</li>
                    <li><i class="fas fa-check-circle"></i> Priority Phone Support</li>
                    <li><i class="fas fa-check-circle"></i> Advanced Analytics & Reports</li>
                    <li><i class="fas fa-check-circle"></i> API Access for Integrations</li>
                    <li><i class="fas fa-check-circle"></i> Bulk Verification Discounts</li>
                </ul>
                <a href="{{ route('admin.login') }}" class="btn">Top Up & Get Started</a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="cta-inner">
            <h2>Ready to simplify your rental operations?</h2>
            <p>Join 500+ rental businesses already saving time with EKiraya's digital platform.</p>
            <a href="{{ route('admin.login') }}" class="btn">
                Get Started Now <i class="fas fa-arrow-right" style="font-size:0.85rem;"></i>
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>Platform</h4>
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#pricing">Pricing</a>
                <a href="#">Download App</a>
            </div>
            <div class="footer-col">
                <h4>Resources</h4>
                <a href="{{ route('contact') }}">Contact Us</a>
                @foreach($footerPages as $page)
                    <a href="{{ route('legal.page', $page->slug) }}">{{ $page->title }}</a>
                @endforeach
                <a href="#">Help Center</a>
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

        mobileToggle.addEventListener('click', () => {
            mobileNav.classList.toggle('active');
        });

        // Close mobile nav when clicking a link
        mobileNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileNav.classList.remove('active');
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            if (window.scrollY > 20) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Close mobile nav on outside click
        document.addEventListener('click', (e) => {
            if (!mobileNav.contains(e.target) && !mobileToggle.contains(e.target)) {
                mobileNav.classList.remove('active');
            }
        });
    </script>
</body>
</html>