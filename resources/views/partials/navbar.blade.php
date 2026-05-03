<nav 
    x-data="{ mobileMenuOpen: false, scrolled: false }"
    x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 20)"
    :class="scrolled ? 'glass border-b border-white/10' : 'bg-transparent'"
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 lg:h-20">
            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex items-center gap-2 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-accent flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                </div>
                <span class="text-xl font-bold text-white">EKiraya</span>
            </a>

            {{-- Desktop Navigation --}}
            <div class="hidden lg:flex items-center gap-8">
                <a href="#features" class="text-gray-300 hover:text-white transition-colors text-sm font-medium">Features</a>
                <a href="#pricing" class="text-gray-300 hover:text-white transition-colors text-sm font-medium">Pricing</a>
                <a href="#reviews" class="text-gray-300 hover:text-white transition-colors text-sm font-medium">Reviews</a>
                <a href="{{ route('contact') }}" class="text-gray-300 hover:text-white transition-colors text-sm font-medium">Contact</a>
            </div>

            {{-- CTA Button --}}
            <div class="hidden lg:block">
                <a 
                    href="#download" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary hover:bg-primary/90 text-white text-sm font-semibold rounded-full transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-primary/25"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download App
                </a>
            </div>

            {{-- Mobile Menu Button --}}
            <button 
                @click="mobileMenuOpen = !mobileMenuOpen"
                class="lg:hidden p-2 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition-colors"
            >
                <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile Menu --}}
    <div 
        x-show="mobileMenuOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-4"
        class="lg:hidden glass border-t border-white/10"
    >
        <div class="px-4 py-4 space-y-3">
            <a @click="mobileMenuOpen = false" href="#features" class="block px-4 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition-colors text-sm font-medium">Features</a>
            <a @click="mobileMenuOpen = false" href="#pricing" class="block px-4 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition-colors text-sm font-medium">Pricing</a>
            <a @click="mobileMenuOpen = false" href="#reviews" class="block px-4 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition-colors text-sm font-medium">Reviews</a>
            <a @click="mobileMenuOpen = false" href="{{ route('contact') }}" class="block px-4 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition-colors text-sm font-medium">Contact</a>
            <a @click="mobileMenuOpen = false" href="#download" class="block px-4 py-2 mt-3 bg-primary text-white text-center rounded-lg text-sm font-semibold hover:bg-primary/90 transition-colors">
                Download App
            </a>
        </div>
    </div>
</nav>

{{-- Spacer for fixed navbar --}}
<div class="h-16 lg:h-20"></div>
