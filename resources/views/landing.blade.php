@extends('layouts.app')

@section('title', 'EKiraya | Run Your Vehicle Rental Business from Your Phone')
@section('meta_description', 'Download EKiraya mobile app to manage bookings, track vehicles, and grow your rental business anytime, anywhere. Available on Android and iOS.')

@section('content')

{{-- HERO SECTION --}}
<section class="relative overflow-hidden pt-8 pb-16 lg:pt-12 lg:pb-24">
    {{-- Background Effects --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-0 left-1/4 w-[500px] h-[500px] bg-primary/30 rounded-full blur-[150px] opacity-60"></div>
        <div class="absolute bottom-0 right-1/4 w-[600px] h-[600px] bg-accent/20 rounded-full blur-[150px] opacity-40"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-primary/10 rounded-full blur-[200px] opacity-30"></div>
    </div>

    {{-- Grid Pattern Overlay --}}
    <div class="absolute inset-0 opacity-[0.02]" style="background-image: linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 60px 60px;"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">
            {{-- Hero Content --}}
            <div class="fade-in text-center lg:text-left">
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-white/10 to-white/5 border border-white/10 text-sm text-gray-300 mb-6 shadow-lg shadow-primary/5">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-accent"></span>
                    </span>
                    Now available on Android & iOS
                </span>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-bold tracking-tight mb-6">
                    <span class="block text-white">Run Your Vehicle</span>
                    <span class="block text-white">Rental Business</span>
                    <span class="gradient-text">from Your Phone</span>
                </h1>

                <p class="text-lg sm:text-xl text-gray-400 mb-8 max-w-xl mx-auto lg:mx-0 leading-relaxed">
                    Manage bookings, track vehicles, and grow your rental business — anytime, anywhere.
                </p>

                {{-- CTA Buttons --}}
                <div class="flex flex-col sm:flex-row gap-4 mb-10 justify-center lg:justify-start" id="download">
                    {{-- Play Store --}}
                    <a 
                        href="#" 
                        class="group inline-flex items-center gap-3 px-6 py-4 bg-white text-gray-900 rounded-xl transition-all duration-200 hover:scale-105 hover:shadow-2xl hover:shadow-primary/20 shadow-lg"
                    >
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z"/>
                        </svg>
                        <div class="text-left">
                            <div class="text-xs text-gray-500 font-medium">Get it on</div>
                            <div class="text-lg font-bold leading-tight">Google Play</div>
                        </div>
                    </a>

                    {{-- App Store Coming Soon --}}
                    <div 
                        class="group inline-flex items-center gap-3 px-6 py-4 bg-white/5 text-gray-400 border border-white/10 rounded-xl cursor-not-allowed relative overflow-hidden"
                    >
                        <div class="absolute inset-0 bg-gradient-to-r from-primary/10 to-accent/10"></div>
                        <svg class="w-8 h-8 relative z-10" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.09,16.67C20.06,16.74 19.67,18.11 18.71,19.5M13,3.5C13.73,2.67 14.94,2.04 15.94,2C16.07,3.17 15.6,4.35 14.9,5.19C14.21,6.04 13.07,6.7 11.95,6.61C11.8,5.46 12.36,4.26 13,3.5Z"/>
                        </svg>
                        <div class="text-left relative z-10">
                            <div class="text-xs font-medium">Coming Soon</div>
                            <div class="text-lg font-bold leading-tight">iOS App</div>
                        </div>
                    </div>
                </div>

                {{-- Stats --}}
                <div class="flex flex-wrap gap-8 justify-center lg:justify-start">
                    <div class="text-center lg:text-left">
                        <div class="text-3xl sm:text-4xl font-bold text-white">100+</div>
                        <div class="text-sm text-gray-400 mt-1">Active Businesses</div>
                    </div>
                    <div class="text-center lg:text-left">
                        <div class="text-3xl sm:text-4xl font-bold text-white">50k+</div>
                        <div class="text-sm text-gray-400 mt-1">Bookings Managed</div>
                    </div>
                    <div class="text-center lg:text-left">
                        <div class="text-3xl sm:text-4xl font-bold text-amber-400">4.8★</div>
                        <div class="text-sm text-gray-400 mt-1">App Rating</div>
                    </div>
                </div>
            </div>

            {{-- Phone Mockup --}}
            <div class="relative flex justify-center lg:justify-end">
                {{-- Floating Badges --}}
                <div class="absolute top-8 -left-4 lg:left-0 glass-card rounded-xl p-3 animate-float z-20">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-accent/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Status</div>
                            <div class="text-sm font-semibold text-white">Booking Confirmed</div>
                        </div>
                    </div>
                </div>

                <div class="absolute top-1/3 -right-4 lg:-right-8 glass-card rounded-xl p-3 animate-float-delayed z-20">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Today</div>
                            <div class="text-sm font-semibold text-white">₹8,500 Earned</div>
                        </div>
                    </div>
                </div>

                <div class="absolute bottom-20 left-0 lg:-left-4 glass-card rounded-xl p-3 animate-float z-20">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Fleet</div>
                            <div class="text-sm font-semibold text-white">3 Vehicles Available</div>
                        </div>
                    </div>
                </div>

                {{-- Phone Frame with Real Screenshot --}}
                <div class="phone-mockup w-64 sm:w-72 lg:w-80 animate-float">
                    <div class="phone-screen aspect-[9/19] overflow-hidden">
                        {{-- TODO: Add your main app screenshot here (e.g., Dashboard) --}}
                        <img src="{{ asset('images/app/hero-dashboard.png') }}" 
                             alt="EKiraya App" 
                             class="w-full h-full object-cover"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        {{-- Fallback CSS mockup if image not found --}}
                        <div class="hidden h-full bg-gradient-to-b from-dark-800 to-dark-900 p-4">
                            <div class="flex justify-between items-center text-xs text-gray-400 mb-4">
                                <span>9:41</span>
                                <div class="flex gap-1">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>
                                </div>
                            </div>
                            <div class="text-xs text-gray-400">Good Morning</div>
                            <div class="text-lg font-bold text-white mb-4">John Updated</div>
                            <div class="bg-gradient-to-r from-primary/20 to-accent/20 rounded-2xl p-4 mb-4">
                                <div class="text-xs text-gray-300">Total Earnings</div>
                                <div class="text-2xl font-bold text-white">₹1,500</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- TRUST SECTION --}}
<section class="py-12 border-y border-white/5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8 fade-in">
            <p class="text-gray-400 text-sm uppercase tracking-wider">Trusted by 100+ rental businesses across India</p>
        </div>
        <div class="flex flex-wrap justify-center items-center gap-8 lg:gap-16 opacity-50">
            <div class="flex items-center gap-2 text-gray-400">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>
                </svg>
                <span class="font-semibold">Car Rentals</span>
            </div>
            <div class="flex items-center gap-2 text-gray-400">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                </svg>
                <span class="font-semibold">Bike Rentals</span>
            </div>
            <div class="flex items-center gap-2 text-gray-400">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 9h-2V7h-2v5H6v2h2v5h2v-5h2v-2z"/>
                </svg>
                <span class="font-semibold">Fleet Operators</span>
            </div>
            <div class="flex items-center gap-2 text-gray-400">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2l-5.5 9h11z M12 5.84L13.93 9h-3.87z M17.5 13c-2.49 0-4.5 2.01-4.5 4.5s2.01 4.5 4.5 4.5 4.5-2.01 4.5-4.5-2.01-4.5-4.5-4.5z M5 21.5h8v-8H5v8z"/>
                </svg>
                <span class="font-semibold">Tour Operators</span>
            </div>
        </div>
    </div>
</section>

{{-- FEATURES SECTION --}}
<section id="features" class="py-20 lg:py-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16 fade-in">
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary/10 border border-primary/20 text-sm text-primary mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
                Powerful Features
            </span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-4">
                Everything You Need to <span class="gradient-text">Run Your Business</span>
            </h2>
            <p class="text-gray-400 text-lg">
                Built specifically for vehicle rental businesses. No clutter, just what you need.
            </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {{-- Feature 1: Dashboard --}}
            <div class="glass-card rounded-2xl p-6 hover:bg-white/5 transition-all duration-300 hover:scale-105 fade-in">
                <div class="w-12 h-12 rounded-xl bg-primary/20 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">Dashboard</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Complete overview of your business at a glance. Track rentals, earnings, and active vehicles in real-time.
                </p>
            </div>

            {{-- Feature 2: Booking Management --}}
            <div class="glass-card rounded-2xl p-6 hover:bg-white/5 transition-all duration-300 hover:scale-105 fade-in">
                <div class="w-12 h-12 rounded-xl bg-accent/20 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">Booking Management</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Manage all your bookings in one place. Search by customer or vehicle, filter by status, and track completed rentals.
                </p>
            </div>

            {{-- Feature 3: Wallet System --}}
            <div class="glass-card rounded-2xl p-6 hover:bg-white/5 transition-all duration-300 hover:scale-105 fade-in">
                <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">Wallet System</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Pay-as-you-use model. Add money via UPI, track credits/debits, and view complete transaction history.
                </p>
            </div>

            {{-- Feature 4: Auto Agreements --}}
            <div class="glass-card rounded-2xl p-6 hover:bg-white/5 transition-all duration-300 hover:scale-105 fade-in">
                <div class="w-12 h-12 rounded-xl bg-primary/20 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">Auto Agreement Generation</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Generate rental agreements automatically with auto-filled details. Digital signatures and PDF downloads included.
                </p>
            </div>

            {{-- Feature 5: DL Verification --}}
            <div class="glass-card rounded-2xl p-6 hover:bg-white/5 transition-all duration-300 hover:scale-105 fade-in">
                <div class="w-12 h-12 rounded-xl bg-cyan-500/20 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3 3 0 01-3-3m5 3v-2m-5 0v-2m10 2v-2m-5 2h5"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">Driving License Verification</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Verify customer driving licenses instantly with RTO database check. Ensure valid licenses before every rental.
                </p>
            </div>

            {{-- Feature 6: Reports & Analytics --}}
            <div class="glass-card rounded-2xl p-6 hover:bg-white/5 transition-all duration-300 hover:scale-105 fade-in">
                <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">Reports & Analytics</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Track total earnings, rental performance, and monthly growth. Export data and get insights to optimize your business.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- APP SHOWCASE SECTION --}}
<section class="py-20 lg:py-32 overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16 fade-in">
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-accent/10 border border-accent/20 text-sm text-accent mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                App Preview
            </span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-4">
                Beautiful. Simple. <span class="gradient-text">Powerful.</span>
            </h2>
            <p class="text-gray-400 text-lg">
                A mobile-first experience designed for rental business owners on the move.
            </p>
        </div>

        {{-- Phone Screens - Replace with your actual app screenshots --}}
        <div class="flex flex-wrap justify-center gap-6 lg:gap-10">
            {{-- Screen 1: Dashboard --}}
            <div class="fade-in" style="animation-delay: 0.1s;">
                <div class="phone-mockup w-56 sm:w-64 overflow-hidden">
                    <div class="phone-screen aspect-[9/19]">
                        {{-- TODO: Add your Dashboard screenshot here --}}
                        <img src="{{ asset('images/app/dashboard.png') }}" 
                             alt="EKiraya Dashboard" 
                             class="w-full h-full object-cover"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        {{-- Fallback CSS mockup if image not found --}}
                        <div class="hidden w-full h-full bg-gradient-to-b from-dark-800 to-dark-900 p-3 flex-col">
                            <div class="text-xs text-gray-400 mb-2">Dashboard</div>
                            <div class="bg-gradient-to-r from-primary/20 to-accent/20 rounded-xl p-3 mb-3">
                                <div class="text-xs text-gray-300">Total Revenue</div>
                                <div class="text-xl font-bold text-white">₹2,45,000</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div class="bg-dark-700/50 rounded-lg p-2 text-center">
                                    <div class="text-lg font-bold text-white">48</div>
                                    <div class="text-xs text-gray-400">Bookings</div>
                                </div>
                                <div class="bg-dark-700/50 rounded-lg p-2 text-center">
                                    <div class="text-lg font-bold text-accent">12</div>
                                    <div class="text-xs text-gray-400">Available</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-center text-gray-400 text-sm mt-4">Dashboard</p>
            </div>

            {{-- Screen 2: Bookings --}}
            <div class="fade-in" style="animation-delay: 0.2s;">
                <div class="phone-mockup w-56 sm:w-64 overflow-hidden">
                    <div class="phone-screen aspect-[9/19]">
                        {{-- TODO: Add your Bookings screenshot here --}}
                        <img src="{{ asset('images/app/bookings.png') }}" 
                             alt="EKiraya Bookings" 
                             class="w-full h-full object-cover"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        {{-- Fallback CSS mockup if image not found --}}
                        <div class="hidden w-full h-full bg-gradient-to-b from-dark-800 to-dark-900 p-3 flex-col">
                            <div class="text-xs text-gray-400 mb-2">Bookings</div>
                            <div class="space-y-2">
                                <div class="bg-dark-700/50 rounded-xl p-3 border-l-2 border-accent">
                                    <div class="flex justify-between items-start mb-1">
                                        <span class="text-xs text-white font-medium">Honda City</span>
                                        <span class="text-xs text-accent">₹3,500</span>
                                    </div>
                                    <div class="text-xs text-gray-400">Rahul Verma • Today</div>
                                </div>
                                <div class="bg-dark-700/50 rounded-xl p-3 border-l-2 border-amber-500">
                                    <div class="flex justify-between items-start mb-1">
                                        <span class="text-xs text-white font-medium">Royal Enfield</span>
                                        <span class="text-xs text-amber-500">₹1,200</span>
                                    </div>
                                    <div class="text-xs text-gray-400">Vikram Singh • Tomorrow</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-center text-gray-400 text-sm mt-4">Bookings</p>
            </div>

            {{-- Screen 3: Wallet --}}
            <div class="fade-in hidden sm:block" style="animation-delay: 0.3s;">
                <div class="phone-mockup w-56 sm:w-64 overflow-hidden">
                    <div class="phone-screen aspect-[9/19]">
                        {{-- TODO: Add your Wallet screenshot here --}}
                        <img src="{{ asset('images/app/wallet.png') }}" 
                             alt="EKiraya Wallet" 
                             class="w-full h-full object-cover"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        {{-- Fallback CSS mockup if image not found --}}
                        <div class="hidden w-full h-full bg-gradient-to-b from-dark-800 to-dark-900 p-3 flex-col">
                            <div class="text-xs text-gray-400 mb-2">My Wallet</div>
                            <div class="bg-gradient-to-br from-dark-700 to-dark-800 rounded-xl p-4 mb-3">
                                <div class="text-xs text-gray-400 mb-1">Total Balance</div>
                                <div class="text-2xl font-bold text-white">₹2,495</div>
                                <div class="flex gap-2 mt-3">
                                    <div class="flex-1 bg-white/10 rounded-lg py-2 text-center">
                                        <div class="text-xs text-gray-300">Add Money</div>
                                    </div>
                                    <div class="flex-1 bg-white/10 rounded-lg py-2 text-center">
                                        <div class="text-xs text-gray-300">Send Money</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-center text-gray-400 text-sm mt-4">Wallet</p>
            </div>
        </div>
    </div>
</section>

{{-- HOW IT WORKS --}}
<section id="how-it-works" class="py-20 lg:py-32 bg-dark-800/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16 fade-in">
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-sm text-gray-300 mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Simple Setup
            </span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-4">
                Get Started in <span class="gradient-text">3 Easy Steps</span>
            </h2>
            <p class="text-gray-400 text-lg">
                From download to your first booking in minutes, not days.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            {{-- Step 1 --}}
            <div class="relative fade-in">
                <div class="glass-card rounded-2xl p-8 text-center h-full">
                    <div class="w-16 h-16 mx-auto mb-6 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-500/50 flex items-center justify-center text-2xl font-bold text-white">
                        1
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-3">Add Your Vehicles</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Enter vehicle details, upload photos, set pricing. Your fleet is ready in minutes.
                    </p>
                </div>
                {{-- Connector line --}}
                <div class="hidden md:block absolute top-1/2 -right-4 w-8 h-px bg-gradient-to-r from-white/20 to-transparent"></div>
            </div>

            {{-- Step 2 --}}
            <div class="relative fade-in">
                <div class="glass-card rounded-2xl p-8 text-center h-full">
                    <div class="w-16 h-16 mx-auto mb-6 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-500/50 flex items-center justify-center text-2xl font-bold text-white">
                        2
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-3">Accept Bookings</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Receive booking requests, manage availability, and confirm with a single tap.
                    </p>
                </div>
                {{-- Connector line --}}
                <div class="hidden md:block absolute top-1/2 -right-4 w-8 h-px bg-gradient-to-r from-white/20 to-transparent"></div>
            </div>

            {{-- Step 3 --}}
            <div class="fade-in">
                <div class="glass-card rounded-2xl p-8 text-center h-full">
                    <div class="w-16 h-16 mx-auto mb-6 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-500/50 flex items-center justify-center text-2xl font-bold text-white">
                        3
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-3">Track Earnings</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Watch your revenue grow in real-time. Get insights to optimize your business.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- WALLET PRICING SECTION --}}
<section id="pricing" class="py-20 lg:py-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16 fade-in">
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-accent/10 border border-accent/20 text-sm text-accent mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Pay As You Use
            </span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-4">
                Simple <span class="gradient-text">Wallet System</span>
            </h2>
            <p class="text-gray-400 text-lg">
                No monthly subscriptions. Add money to your wallet and pay only for what you use. Transparent pricing, no hidden fees.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-6 lg:gap-8 max-w-5xl mx-auto">
            {{-- Auto Agreements --}}
            <div class="glass-card rounded-2xl p-6 lg:p-8 fade-in hover:bg-white/5 transition-all duration-300">
                <div class="mb-6">
                    <div class="w-12 h-12 rounded-xl bg-primary/20 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Auto Agreements</h3>
                    <p class="text-gray-400 text-sm">Generate rental agreements automatically</p>
                </div>
                <div class="mb-6">
                    <span class="text-3xl font-bold text-white">Free</span>
                    <span class="text-gray-400">to use</span>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center gap-3 text-sm text-gray-300">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Unlimited agreements
                    </li>
                    <li class="flex items-center gap-3 text-sm text-gray-300">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Auto-filled details
                    </li>
                    <li class="flex items-center gap-3 text-sm text-gray-300">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        PDF download
                    </li>
                </ul>
            </div>

            {{-- DL Verification --}}
            <div class="glass-card rounded-2xl p-6 lg:p-8 relative fade-in gradient-border glow transform md:-translate-y-4">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                    <span class="px-4 py-1 bg-gradient-to-r from-primary to-accent text-white text-xs font-semibold rounded-full">
                        Required
                    </span>
                </div>
                <div class="mb-6">
                    <div class="w-12 h-12 rounded-xl bg-accent/20 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3 3 0 01-3-3m5 3v-2m-5 0v-2m10 2v-2m-5 2h5"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">DL Verification</h3>
                    <p class="text-gray-400 text-sm">Compulsory for every rental</p>
                </div>
                <div class="mb-6">
                    <span class="text-3xl font-bold text-white">₹3</span>
                    <span class="text-gray-400">/verification</span>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center gap-3 text-sm text-gray-300">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        RTO database check
                    </li>
                    <li class="flex items-center gap-3 text-sm text-gray-300">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Instant results
                    </li>
                    <li class="flex items-center gap-3 text-sm text-gray-300">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Validity & authenticity
                    </li>
                </ul>
            </div>

            {{-- Wallet Features --}}
            <div class="glass-card rounded-2xl p-6 lg:p-8 fade-in hover:bg-white/5 transition-all duration-300">
                <div class="mb-6">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Wallet Features</h3>
                    <p class="text-gray-400 text-sm">Manage your credits easily</p>
                </div>
                <div class="mb-6">
                    <span class="text-3xl font-bold text-white">Free</span>
                    <span class="text-gray-400">to use</span>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center gap-3 text-sm text-gray-300">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Add money via UPI
                    </li>
                    <li class="flex items-center gap-3 text-sm text-gray-300">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Transaction history
                    </li>
                    <li class="flex items-center gap-3 text-sm text-gray-300">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Auto-deduct on use
                    </li>
                </ul>
            </div>
        </div>

        {{-- Wallet CTA --}}
        <div class="mt-12 text-center fade-in">
            <div class="glass-card rounded-2xl p-8 max-w-2xl mx-auto">
                <h3 class="text-xl font-semibold text-white mb-3">Start with ₹0</h3>
                <p class="text-gray-400 mb-6">Download the app, create your account, and add money to your wallet when you're ready. No upfront costs.</p>
                <a href="#download" class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-primary to-accent text-white font-semibold rounded-xl hover:opacity-90 transition-opacity">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download App
                </a>
            </div>
        </div>
    </div>
</section>

{{-- TESTIMONIALS SECTION --}}
<section id="reviews" class="py-20 lg:py-32 bg-dark-800/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16 fade-in">
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-sm text-gray-300 mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                Loved by Rental Businesses
            </span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-4">
                What Our <span class="gradient-text">Customers</span> Say
            </h2>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            {{-- Testimonial 1 --}}
            <div class="glass-card rounded-2xl p-6 fade-in">
                <div class="flex items-center gap-1 mb-4">
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                </div>
                <p class="text-gray-300 text-sm leading-relaxed mb-6">
                    "This app replaced my entire manual system. I used to spend 3 hours every day managing bookings on WhatsApp and notebooks. Now everything is automated."
                </p>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-white font-semibold">
                        R
                    </div>
                    <div>
                        <div class="text-sm font-medium text-white">Rajesh Kumar</div>
                        <div class="text-xs text-gray-400">RK Car Rentals, Delhi</div>
                    </div>
                </div>
            </div>

            {{-- Testimonial 2 --}}
            <div class="glass-card rounded-2xl p-6 fade-in">
                <div class="flex items-center gap-1 mb-4">
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                </div>
                <p class="text-gray-300 text-sm leading-relaxed mb-6">
                    "The analytics feature is a game-changer. I can see which vehicles are performing best and adjust my pricing accordingly. Revenue is up 40% since I started using EKiraya."
                </p>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-accent to-cyan-500 flex items-center justify-center text-white font-semibold">
                        P
                    </div>
                    <div>
                        <div class="text-sm font-medium text-white">Priya Sharma</div>
                        <div class="text-xs text-gray-400">GoBikes, Bangalore</div>
                    </div>
                </div>
            </div>

            {{-- Testimonial 3 --}}
            <div class="glass-card rounded-2xl p-6 fade-in">
                <div class="flex items-center gap-1 mb-4">
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                </div>
                <p class="text-gray-300 text-sm leading-relaxed mb-6">
                    "Managing 3 locations was a nightmare before. Now I can see everything from my phone. My managers love it too — no more calling to check availability."
                </p>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-amber-500 to-pink-500 flex items-center justify-center text-white font-semibold">
                        A
                    </div>
                    <div>
                        <div class="text-sm font-medium text-white">Arun Nair</div>
                        <div class="text-xs text-gray-400">Kerala Travels, Kochi</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- FINAL CTA SECTION --}}
<section class="py-20 lg:py-32 relative overflow-hidden">
    {{-- Background Effects --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[800px] bg-primary/10 rounded-full blur-[200px]"></div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <div class="fade-in">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-6">
                Your Rental Business. <span class="gradient-text">Fully in Your Pocket.</span>
            </h2>
            <p class="text-xl text-gray-400 mb-10 max-w-2xl mx-auto">
                Join 100+ rental businesses already using EKiraya to grow their operations.
            </p>

            {{-- CTA Buttons --}}
            <div class="flex flex-col sm:flex-row gap-4 justify-center mb-8">
                {{-- Play Store --}}
                <a 
                    href="#" 
                    class="group inline-flex items-center gap-3 px-8 py-4 bg-white hover:bg-gray-100 text-dark-900 rounded-xl transition-all duration-200 hover:scale-105 shadow-lg hover:shadow-xl"
                >
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z"/>
                    </svg>
                    <div class="text-left">
                        <div class="text-xs text-gray-600">Get it on</div>
                        <div class="text-lg font-semibold leading-tight">Google Play</div>
                    </div>
                </a>

                {{-- App Store --}}
                <a 
                    href="#" 
                    class="group inline-flex items-center gap-3 px-8 py-4 bg-white/10 hover:bg-white/20 text-white border border-white/20 rounded-xl transition-all duration-200 hover:scale-105"
                >
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.09,16.67C20.06,16.74 19.67,18.11 18.71,19.5M13,3.5C13.73,2.67 14.94,2.04 15.94,2C16.07,3.17 15.6,4.35 14.9,5.19C14.21,6.04 13.07,6.7 11.95,6.61C11.8,5.46 12.36,4.26 13,3.5Z"/>
                    </svg>
                    <div class="text-left">
                        <div class="text-xs text-gray-400">Download on the</div>
                        <div class="text-lg font-semibold leading-tight">App Store</div>
                    </div>
                </a>
            </div>

            <p class="text-gray-500 text-sm">
                No credit card required.
            </p>
        </div>
    </div>
</section>

@endsection
