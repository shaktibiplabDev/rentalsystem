<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', 'EKiraya helps rental businesses operate faster with digital agreements, verification workflows, and operational visibility.')">
    <title>@yield('title', 'EKiraya')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/public-site.css') }}">
    @stack('head')
</head>
<body class="@yield('body_class')">
@php
    $footerPages = \App\Models\LegalPage::getFooterPages();
@endphp
<header class="site-header" id="siteHeader">
    <div class="utility-bar">
        <div class="utility-pill">
            <span class="pulse-dot" aria-hidden="true"></span>
            Built for high-volume Indian vehicle rental operations
        </div>
        <a href="{{ route('contact') }}" class="utility-link">Talk to Sales</a>
    </div>
    <nav class="main-nav">
        <a class="brand" href="{{ route('home') }}" aria-label="EKiraya Home">EKiraya</a>
        <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle menu">Menu</button>
        <ul class="nav-links" id="mainNavLinks">
            <li><a href="{{ route('home') }}">Home</a></li>
            <li><a href="{{ route('home') }}#features">Features</a></li>
            <li><a href="{{ route('home') }}#workflow">Workflow</a></li>
            <li><a href="{{ route('contact') }}">Contact</a></li>
            <li><a class="btn btn-nav" href="{{ route('contact') }}">Book Demo</a></li>
        </ul>
    </nav>
</header>

<main>
    @yield('content')
</main>

<footer class="site-footer">
    <div class="footer-grid">
        <section>
            <h4>EKiraya</h4>
            <p>Precision operations platform for rental teams that want reliable workflow control and long-term scalability.</p>
        </section>
        <section>
            <h4>Product</h4>
            <a href="{{ route('home') }}#features">Capabilities</a>
            <a href="{{ route('home') }}#workflow">How It Works</a>
            <a href="{{ route('contact') }}">Book a Live Demo</a>
        </section>
        <section>
            <h4>Legal</h4>
            @forelse($footerPages as $page)
                <a href="{{ route('legal.page', $page['slug']) }}">{{ $page['title'] }}</a>
            @empty
                <a href="{{ route('contact') }}">Contact Support</a>
            @endforelse
        </section>
        <section>
            <h4>Contact</h4>
            <a href="mailto:support@ekiraya.com">support@ekiraya.com</a>
            <a href="tel:+919876543210">+91 98765 43210</a>
            <a href="{{ route('contact') }}">Enterprise Assistance</a>
        </section>
    </div>
    <div class="footer-bottom">
        <span>{{ now()->year }} EKiraya. All rights reserved.</span>
    </div>
</footer>

<script src="{{ asset('js/public-site.js') }}" defer></script>
@stack('scripts')
</body>
</html>
