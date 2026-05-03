@extends('layouts.app')

@section('title', 'Contact Us | EKiraya')
@section('meta_description', 'Get in touch with the EKiraya team. We are here to help you grow your rental business.')

@section('content')

{{-- HERO SECTION --}}
<section class="relative overflow-hidden pt-12 pb-12">
    {{-- Background Effects --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-1/4 w-96 h-96 bg-primary/20 rounded-full blur-[128px]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center max-w-2xl mx-auto fade-in">
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-sm text-gray-300 mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                </svg>
                Contact Us
            </span>

            <h1 class="text-4xl sm:text-5xl font-extrabold mb-4">
                Get in <span class="gradient-text">Touch</span>
            </h1>

            <p class="text-lg text-gray-400">
                Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.
            </p>
        </div>
    </div>
</section>

{{-- CONTACT SECTION --}}
<section class="pb-20 lg:pb-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-5 gap-8 lg:gap-12">
            {{-- Contact Form --}}
            <div class="lg:col-span-3 fade-in">
                <div class="glass-card rounded-2xl p-6 lg:p-8">
                    @if(session('success'))
                        <div class="mb-6 p-4 rounded-xl bg-accent/20 border border-accent/30 text-accent flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-6 p-4 rounded-xl bg-red-500/20 border border-red-500/30 text-red-400 flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Please review the highlighted fields and try again.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('contact.submit') }}" class="space-y-6" novalidate>
                        @csrf
                        
                        <div class="grid sm:grid-cols-2 gap-6">
                            {{-- Name --}}
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Full Name</label>
                                <input 
                                    id="name" 
                                    name="name" 
                                    type="text" 
                                    value="{{ old('name') }}" 
                                    maxlength="100" 
                                    required
                                    class="w-full px-4 py-3 bg-dark-800/50 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                    placeholder="John Doe"
                                >
                                @error('name') 
                                    <span class="mt-1 text-sm text-red-400">{{ $message }}</span> 
                                @enderror
                            </div>

                            {{-- Email --}}
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                                <input 
                                    id="email" 
                                    name="email" 
                                    type="email" 
                                    value="{{ old('email') }}" 
                                    maxlength="150" 
                                    required
                                    class="w-full px-4 py-3 bg-dark-800/50 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                    placeholder="john@example.com"
                                >
                                @error('email') 
                                    <span class="mt-1 text-sm text-red-400">{{ $message }}</span> 
                                @enderror
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-6">
                            {{-- Phone --}}
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-300 mb-2">Phone Number</label>
                                <input 
                                    id="phone" 
                                    name="phone" 
                                    type="tel" 
                                    value="{{ old('phone') }}" 
                                    maxlength="20"
                                    class="w-full px-4 py-3 bg-dark-800/50 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                    placeholder="+91 98765 43210"
                                >
                                @error('phone') 
                                    <span class="mt-1 text-sm text-red-400">{{ $message }}</span> 
                                @enderror
                            </div>

                            {{-- Subject --}}
                            <div>
                                <label for="subject" class="block text-sm font-medium text-gray-300 mb-2">Subject</label>
                                <select 
                                    id="subject" 
                                    name="subject" 
                                    required
                                    class="w-full px-4 py-3 bg-dark-800/50 border border-white/10 rounded-xl text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors appearance-none cursor-pointer"
                                >
                                    <option value="" class="bg-dark-800">Select a reason</option>
                                    <option value="Demo Request" @selected(old('subject') === 'Demo Request') class="bg-dark-800">Demo Request</option>
                                    <option value="Pricing Query" @selected(old('subject') === 'Pricing Query') class="bg-dark-800">Pricing Query</option>
                                    <option value="Partnership" @selected(old('subject') === 'Partnership') class="bg-dark-800">Partnership</option>
                                    <option value="Support" @selected(old('subject') === 'Support') class="bg-dark-800">Support</option>
                                </select>
                                @error('subject') 
                                    <span class="mt-1 text-sm text-red-400">{{ $message }}</span> 
                                @enderror
                            </div>
                        </div>

                        {{-- Honeypot --}}
                        <div class="hidden" aria-hidden="true">
                            <label for="website">Website</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>

                        {{-- Message --}}
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-300 mb-2">Message</label>
                            <textarea 
                                id="message" 
                                name="message" 
                                maxlength="2000" 
                                required
                                rows="5"
                                class="w-full px-4 py-3 bg-dark-800/50 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors resize-none"
                                placeholder="Tell us how we can help you..."
                            >{{ old('message') }}</textarea>
                            @error('message') 
                                <span class="mt-1 text-sm text-red-400">{{ $message }}</span> 
                            @enderror
                        </div>

                        {{-- Submit Button --}}
                        <button 
                            type="submit" 
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 bg-gradient-to-r from-primary to-accent text-white font-semibold rounded-xl hover:opacity-90 transition-opacity"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            Send Message
                        </button>
                    </form>
                </div>
            </div>

            {{-- Contact Info --}}
            <div class="lg:col-span-2 fade-in">
                <div class="space-y-6">
                    {{-- Support Card --}}
                    <div class="glass-card rounded-2xl p-6">
                        <div class="w-12 h-12 rounded-xl bg-primary/20 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">Support Hours</h3>
                        <p class="text-gray-400 text-sm leading-relaxed">
                            Monday to Saturday<br>
                            10:00 AM - 7:00 PM IST
                        </p>
                    </div>

                    {{-- Email Card --}}
                    <div class="glass-card rounded-2xl p-6">
                        <div class="w-12 h-12 rounded-xl bg-accent/20 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">Email Us</h3>
                        <a href="mailto:support@ekiraya.com" class="text-primary hover:text-accent transition-colors text-sm">
                            support@ekiraya.com
                        </a>
                    </div>

                    {{-- Phone Card --}}
                    <div class="glass-card rounded-2xl p-6">
                        <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">Call Us</h3>
                        <a href="tel:+919876543210" class="text-primary hover:text-accent transition-colors text-sm">
                            +91 98765 43210
                        </a>
                    </div>

                    {{-- Address Card --}}
                    <div class="glass-card rounded-2xl p-6">
                        <div class="w-12 h-12 rounded-xl bg-rose-500/20 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">Office Address</h3>
                        <p class="text-gray-400 text-sm leading-relaxed">
                            EKiraya Technologies Pvt. Ltd.<br>
                            123, Tech Park, 4th Floor<br>
                            Koramangala, Bangalore - 560034<br>
                            Karnataka, India
                        </p>
                    </div>

                    {{-- Quick Help --}}
                    <div class="glass-card rounded-2xl p-6 bg-gradient-to-br from-primary/10 to-accent/10">
                        <h3 class="text-lg font-semibold text-white mb-2">Need Quick Help?</h3>
                        <p class="text-gray-400 text-sm mb-4">
                            Download our app for instant support and FAQs.
                        </p>
                        <a href="#download" class="inline-flex items-center gap-2 text-primary hover:text-accent transition-colors text-sm font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download App
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
