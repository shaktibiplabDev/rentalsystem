@extends('layouts.app')

@section('title', ($page['meta_title'] ?? $page['title'] ?? 'Legal') . ' | EKiraya')
@section('meta_description', $page['meta_description'] ?? 'Legal information and usage terms for EKiraya services.')

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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Legal
            </span>

            <h1 class="text-4xl sm:text-5xl font-extrabold mb-4">
                {{ $page['title'] ?? 'Legal Page' }}
            </h1>

            @if(!empty($page['published_at']))
                <p class="text-gray-400">
                    Last updated: {{ \Carbon\Carbon::parse($page['published_at'])->format('F d, Y') }}
                </p>
            @endif
        </div>
    </div>
</section>

{{-- CONTENT SECTION --}}
<section class="pb-20 lg:pb-32">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="fade-in">
            {{-- Content Card --}}
            <div class="glass-card rounded-2xl p-6 lg:p-10">
                <article class="prose prose-invert prose-lg max-w-none">
                    {!! $page['content'] ?? '<p class="text-gray-400">No content available.</p>' !!}
                </article>
            </div>

            {{-- Back to Home --}}
            <div class="mt-8 text-center">
                <a 
                    href="{{ route('home') }}" 
                    class="inline-flex items-center gap-2 px-6 py-3 bg-white/10 hover:bg-white/20 text-white border border-white/20 rounded-xl transition-all duration-200"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Home
                </a>
            </div>

            {{-- Other Legal Pages --}}
            @php
                $footerPages = \App\Models\LegalPage::getFooterPages();
            @endphp
            
            @if(count($footerPages) > 1)
                <div class="mt-12 pt-8 border-t border-white/10">
                    <h3 class="text-lg font-semibold text-white mb-4 text-center">Other Legal Documents</h3>
                    <div class="flex flex-wrap justify-center gap-3">
                        @foreach($footerPages as $footerPage)
                            @if($footerPage['slug'] !== ($page['slug'] ?? ''))
                                <a 
                                    href="{{ route('legal.page', $footerPage['slug']) }}" 
                                    class="px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white text-sm transition-colors"
                                >
                                    {{ $footerPage['title'] }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>

@endsection

@push('head')
<style>
    .prose-invert h1 { color: #fff; font-size: 1.875rem; font-weight: 700; margin-top: 2rem; margin-bottom: 1rem; }
    .prose-invert h2 { color: #fff; font-size: 1.5rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.75rem; }
    .prose-invert h3 { color: #fff; font-size: 1.25rem; font-weight: 600; margin-top: 1.25rem; margin-bottom: 0.5rem; }
    .prose-invert p { color: #9CA3AF; margin-bottom: 1rem; line-height: 1.75; }
    .prose-invert ul { color: #9CA3AF; list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
    .prose-invert ol { color: #9CA3AF; list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 1rem; }
    .prose-invert li { margin-bottom: 0.5rem; }
    .prose-invert a { color: #4F46E5; text-decoration: none; }
    .prose-invert a:hover { color: #22C55E; }
    .prose-invert strong { color: #E5E7EB; font-weight: 600; }
    .prose-invert hr { border-color: rgba(255,255,255,0.1); margin: 2rem 0; }
    .prose-invert blockquote { border-left: 4px solid #4F46E5; padding-left: 1rem; color: #9CA3AF; font-style: italic; }
</style>
@endpush
