@extends('layouts.public')

@section('title', ($page['meta_title'] ?? $page['title'] ?? 'Legal') . ' | EKiraya')
@section('meta_description', $page['meta_description'] ?? 'Legal information and usage terms for EKiraya services.')

@section('content')
<section class="section">
    <div class="container" data-reveal>
        <div class="legal-shell" data-reveal>
            <span class="eyebrow">Legal</span>
            <h1 style="margin-top:12px; font-size: clamp(1.6rem, 3.5vw, 2.45rem);">{{ $page['title'] ?? 'Legal Page' }}</h1>
            @if(!empty($page['published_at']))
                <p style="margin-top:8px; font-size:0.88rem;">Last updated: {{ \Carbon\Carbon::parse($page['published_at'])->format('F d, Y') }}</p>
            @endif
            <article class="legal-content">
                {!! $page['content'] ?? '' !!}
            </article>
            <div style="margin-top:24px;">
                <a class="btn btn-secondary" href="{{ route('home') }}">Back to Home</a>
            </div>
        </div>
    </div>
</section>
@endsection
