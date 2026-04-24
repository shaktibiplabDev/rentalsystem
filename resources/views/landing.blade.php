@extends('layouts.public')

@section('title', 'EKiraya | Rental Operations Platform')
@section('meta_description', 'Professional rental operations for vehicle businesses: verification workflows, digital agreements, and reliable records.')

@section('content')
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow">Rental Operations, Rebuilt</span>
            <h1>Run daily rentals with less risk and less paperwork.</h1>
            <p>
                EKiraya gives your team a practical operating layer for customer checks, agreement workflows,
                and handover-ready records so every rental is faster, cleaner, and easier to audit.
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="{{ route('contact') }}">Book a Demo</a>
                <a class="btn btn-secondary" href="#features">See Features</a>
            </div>
            <div class="hero-proof">
                <div class="proof-item">
                    <strong>500+</strong>
                    <span>Rental operators onboarded</span>
                </div>
                <div class="proof-item">
                    <strong>50k+</strong>
                    <span>Verification workflows executed</span>
                </div>
                <div class="proof-item">
                    <strong>24x7</strong>
                    <span>Cloud-hosted access for your team</span>
                </div>
            </div>
        </div>
        <figure class="hero-media">
            <img
                src="https://images.unsplash.com/photo-1550355291-bbee04a92027?auto=format&fit=crop&w=1200&q=80"
                alt="Premium car on city road"
                loading="eager"
                referrerpolicy="no-referrer"
            >
            <figcaption class="floating-card">
                <strong>Daily operations snapshot</strong>
                <span>Verification, agreement, and handover tracking in one place.</span>
            </figcaption>
        </figure>
    </div>
</section>

<section class="section" id="features">
    <div class="container">
        <span class="eyebrow">What You Get</span>
        <h2 style="margin-top:12px;">Built for real rental counters, not generic dashboards.</h2>
        <p style="margin-top:10px; max-width: 70ch;">
            Your workflows stay predictable from customer intake to return processing. The system is designed for long-term use by teams, not one-off demos.
        </p>
        <div class="cards" style="margin-top:20px;">
            <article class="card">
                <h3>Structured Verification Flow</h3>
                <p>Capture and process customer verification with clear statuses and fewer manual follow-ups.</p>
            </article>
            <article class="card">
                <h3>Agreement-First Rentals</h3>
                <p>Generate documents and keep rental events traceable with timestamped records.</p>
            </article>
            <article class="card">
                <h3>Operational Reporting</h3>
                <p>Track activity trends, performance indicators, and transaction movement from one place.</p>
            </article>
        </div>
    </div>
</section>

<section class="section" id="workflow">
    <div class="container workflow">
        <figure class="workflow-image">
            <img
                src="https://images.unsplash.com/photo-1549921296-3a6b79fdbb25?auto=format&fit=crop&w=1200&q=80"
                alt="Rental professional checking vehicle and documents"
                loading="lazy"
                referrerpolicy="no-referrer"
            >
        </figure>
        <div>
            <span class="eyebrow">How It Works</span>
            <h2 style="margin:12px 0;">A reliable workflow your staff can follow every day.</h2>
            <div class="steps">
                <article class="step">
                    <div class="step-index">1</div>
                    <div>
                        <h3>Start verification</h3>
                        <p>Begin the rental with customer checks and standardized intake data.</p>
                    </div>
                </article>
                <article class="step">
                    <div class="step-index">2</div>
                    <div>
                        <h3>Complete agreement and handover</h3>
                        <p>Generate and finalize records before keys leave the counter.</p>
                    </div>
                </article>
                <article class="step">
                    <div class="step-index">3</div>
                    <div>
                        <h3>Close with clear documentation</h3>
                        <p>Return processing and proof trail stay available for future reference.</p>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0;">
    <div class="container gallery-grid">
        <img src="https://images.unsplash.com/photo-1489515217757-5fd1be406fef?auto=format&fit=crop&w=1200&q=80" alt="Car fleet ready for rentals" loading="lazy" referrerpolicy="no-referrer">
        <img src="https://images.unsplash.com/photo-1511910849309-0dffb8785146?auto=format&fit=crop&w=900&q=80" alt="Vehicle key handover" loading="lazy" referrerpolicy="no-referrer">
    </div>
</section>
@endsection
