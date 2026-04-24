@extends('layouts.public')

@section('title', $title . ' | EKiraya')
@section('meta_description', 'Email verification status for your EKiraya account.')

@section('content')
<section class="section">
    <div class="container status-wrap">
        <article class="status-card">
            <span class="pill {{ $success ? 'ok' : 'bad' }}">{{ $success ? 'Verification Complete' : 'Verification Failed' }}</span>
            <h1 style="font-size: clamp(1.5rem, 3vw, 2.2rem); margin-bottom:10px;">{{ $title }}</h1>
            <p style="max-width:52ch; margin:0 auto;">{{ $message }}</p>

            @if($showLoginButton)
                <div style="margin-top:18px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                    <button class="btn btn-primary" type="button" onclick="openApp()">Open App</button>
                    <a class="btn btn-secondary" href="{{ route('home') }}">Go Home</a>
                </div>
            @else
                <div style="margin-top:18px;">
                    <a class="btn btn-secondary" href="{{ route('home') }}">Go Home</a>
                </div>
            @endif
        </article>
    </div>
</section>
@endsection

@push('scripts')
@if($showLoginButton)
<script>
    function openApp() {
        window.location.href = 'yourapp://login';
        setTimeout(() => {
            window.location.href = 'https://play.google.com/store/apps/details?id=com.yourapp.package';
        }, 2000);
    }

    setTimeout(() => {
        openApp();
    }, 3000);
</script>
@endif
@endpush
