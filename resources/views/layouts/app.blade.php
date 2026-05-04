<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta_description', 'EKiraya - Run your vehicle rental business from your phone. Manage bookings, track vehicles, and grow your rental business anytime, anywhere.')">
    <title>@yield('title', 'EKiraya - Vehicle Rental Business App')</title>
    
    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    {{-- Vite CSS/JS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }
        
        html, body {
            margin: 0;
            padding: 0;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #0B0F19;
            color: #E5E7EB;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
        }
        
        /* Glass morphism utilities */
        .glass {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .glass-card {
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
        }
        
        .glass-card:hover {
            background: rgba(17, 24, 39, 0.7);
            border-color: rgba(255, 255, 255, 0.12);
            transform: translateY(-2px);
        }
        
        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #4F46E5 0%, #22C55E 50%, #4F46E5 100%);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradient-shift 8s ease infinite;
        }
        
        @keyframes gradient-shift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        /* Gradient border effect */
        .gradient-border {
            position: relative;
            background: rgba(17, 24, 39, 0.6);
        }
        
        .gradient-border::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1px;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.6), rgba(34, 197, 94, 0.4));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }
        
        /* Glow effect */
        .glow {
            box-shadow: 0 0 60px rgba(79, 70, 229, 0.2), 0 0 100px rgba(34, 197, 94, 0.1);
        }
        
        .glow-sm {
            box-shadow: 0 0 30px rgba(79, 70, 229, 0.15);
        }
        
        /* Float animation */
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        
        .animate-float-delayed {
            animation: float 6s ease-in-out infinite;
            animation-delay: -3s;
        }
        
        .animate-float-slow {
            animation: float 8s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(1deg); }
        }
        
        /* Fade in animation */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s cubic-bezier(0.4, 0, 0.2, 1), 
                        transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Phone mockup */
        .phone-mockup {
            background: linear-gradient(145deg, #1F2937 0%, #0f172a 100%);
            border-radius: 40px;
            padding: 12px;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.6),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset,
                0 0 60px rgba(79, 70, 229, 0.1);
        }
        
        .phone-screen {
            background: #0B0F19;
            border-radius: 28px;
            overflow: hidden;
        }
        
        /* Button hover lift */
        .btn-lift {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .btn-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(79, 70, 229, 0.3);
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #0B0F19;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 4px;
            border: 2px solid #0B0F19;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #4B5563;
        }
        
        /* Selection color */
        ::selection {
            background: rgba(79, 70, 229, 0.3);
            color: #fff;
        }
        
        /* Focus visible */
        :focus-visible {
            outline: 2px solid #4F46E5;
            outline-offset: 2px;
        }
        
        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            
            .fade-in {
                opacity: 1;
                transform: none;
            }
        }
    </style>
    
    @stack('head')
</head>
<body class="antialiased min-h-screen">
    {{-- Navbar --}}
    @include('partials.navbar')
    
    {{-- Main Content --}}
    <main>
        @yield('content')
    </main>
    
    {{-- Footer --}}
    @include('partials.footer')
    
    {{-- Scroll Animation Script --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.fade-in').forEach(el => {
                observer.observe(el);
            });
        });
    </script>
    
    @stack('scripts')
</body>
</html>
