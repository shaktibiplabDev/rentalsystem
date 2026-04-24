@extends('layouts.public')

@section('title', 'EKiraya | Rental Operations Platform')
@section('meta_description', 'Professional rental operations for vehicle businesses: verification workflows, digital agreements, and reliable records.')

@section('content')
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy" data-reveal>
            <span class="eyebrow">Rental Operations, Reimagined</span>
            <h1>Move faster from booking to handover, with audit-ready control.</h1>
            <p>
                EKiraya gives your team a serious operations cockpit for verification, agreements, delivery tracking,
                and return events so rentals stay clean, quick, and defensible.
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="{{ route('contact') }}">Schedule Live Demo</a>
                <a class="btn btn-secondary" href="#features">Explore Platform</a>
            </div>
            <div class="hero-proof">
                <div class="proof-item">
                    <strong>500+</strong>
                    <span>Shops operational on EKiraya</span>
                </div>
                <div class="proof-item">
                    <strong>50k+</strong>
                    <span>Verification workflows completed</span>
                </div>
                <div class="proof-item">
                    <strong>99.9%</strong>
                    <span>Cloud availability architecture</span>
                </div>
            </div>
        </div>

        <figure class="hero-media" data-reveal>
            <img
                src="https://images.unsplash.com/photo-1550355291-bbee04a92027?auto=format&fit=crop&w=1400&q=85"
                alt="Luxury rental car moving through city"
                loading="eager"
                referrerpolicy="no-referrer"
            >
            <figcaption class="floating-card">
                <strong>Operations timeline in one view</strong>
                <span>Verification, agreement, key handover, and return trail unified for your team.</span>
            </figcaption>
        </figure>
    </div>
</section>

<section class="section" id="features">
    <div class="container" data-reveal>
        <span class="eyebrow">Capabilities</span>
        <h2 style="margin-top:12px; font-size: clamp(1.7rem, 3.5vw, 2.7rem);">Built for long-term operations, not temporary dashboards.</h2>
        <p style="margin-top:10px; max-width: 72ch;">
            Everything is structured around real shop workflows so your team can onboard quickly and execute consistently even during peak demand windows.
        </p>
        <div class="cards">
            <article class="card" data-reveal>
                <h3>Verified Customer Flow</h3>
                <p>Run predictable verification steps with clear statuses and less back-and-forth at the counter.</p>
            </article>
            <article class="card" data-reveal>
                <h3>Agreement + Event Log</h3>
                <p>Generate rental paperwork and maintain a timeline of key operational actions automatically.</p>
            </article>
            <article class="card" data-reveal>
                <h3>Business Visibility</h3>
                <p>Track operational throughput, issues, and money movement with decision-ready reporting views.</p>
            </article>
        </div>
    </div>
</section>

<section class="section" id="workflow">
    <div class="container workflow">
        <figure class="workflow-image" data-reveal>
            <img
                src="https://images.unsplash.com/photo-1517524008697-84bbe3c3fd98?auto=format&fit=crop&w=1300&q=85"
                alt="Rental associate handing over vehicle key"
                loading="lazy"
                referrerpolicy="no-referrer"
            >
        </figure>
        <div data-reveal>
            <span class="eyebrow">Workflow</span>
            <h2 style="margin:12px 0; font-size: clamp(1.6rem, 3vw, 2.4rem);">A clean operational sequence your staff can trust daily.</h2>
            <div class="steps">
                <article class="step" data-reveal>
                    <div class="step-index">1</div>
                    <div>
                        <h3>Collect and verify</h3>
                        <p>Begin each rental with consistent intake data and verification checkpoints.</p>
                    </div>
                </article>
                <article class="step" data-reveal>
                    <div class="step-index">2</div>
                    <div>
                        <h3>Finalize and hand over</h3>
                        <p>Complete agreement workflows before key handover with clear accountability.</p>
                    </div>
                </article>
                <article class="step" data-reveal>
                    <div class="step-index">3</div>
                    <div>
                        <h3>Close with proof trail</h3>
                        <p>Handle returns with a complete action history available for support and compliance.</p>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0;">
    <div class="container gallery-grid">
        <img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1400&q=85" alt="High-end car fleet lineup" loading="lazy" referrerpolicy="no-referrer" data-reveal>
        <img src="https://images.unsplash.com/photo-1565043589221-1a6fd9ae45c7?auto=format&fit=crop&w=900&q=85" alt="Driver starting a vehicle" loading="lazy" referrerpolicy="no-referrer" data-reveal>
    </div>
</section>
@endsection
