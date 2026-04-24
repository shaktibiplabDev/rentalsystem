@extends('layouts.public')

@section('title', 'Contact EKiraya')
@section('meta_description', 'Talk to EKiraya about onboarding your rental business operations team.')

@section('content')
<section class="section">
    <div class="container">
        <span class="eyebrow">Contact</span>
        <h1 style="margin-top:12px; font-size: clamp(1.8rem, 4.2vw, 3rem);">Let’s plan your rollout.</h1>
        <p style="margin-top:10px; max-width: 70ch;">
            Share your current setup and team size. We will help you map a practical onboarding path.
        </p>

        <div class="contact-layout" style="margin-top:22px;">
            <section class="panel">
                @if(session('success'))
                    <div class="alert alert-ok">{{ session('success') }}</div>
                @endif

                @if($errors->any())
                    <div class="alert alert-bad">Please review the highlighted fields and try again.</div>
                @endif

                <form method="POST" action="{{ route('contact.submit') }}" class="form-grid" novalidate>
                    @csrf
                    <div class="field">
                        <label for="name">Full Name</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="100" required>
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="email">Work Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="150" required>
                        @error('email') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="phone">Phone Number</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone') }}" maxlength="20">
                        @error('phone') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="subject">Subject</label>
                        <select id="subject" name="subject" required>
                            <option value="">Select a reason</option>
                            <option value="Demo Request" @selected(old('subject') === 'Demo Request')>Demo Request</option>
                            <option value="Pricing Query" @selected(old('subject') === 'Pricing Query')>Pricing Query</option>
                            <option value="Partnership" @selected(old('subject') === 'Partnership')>Partnership</option>
                            <option value="Support" @selected(old('subject') === 'Support')>Support</option>
                        </select>
                        @error('subject') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="field">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" maxlength="2000" required>{{ old('message') }}</textarea>
                        @error('message') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <button class="btn btn-primary" type="submit">Send Message</button>
                </form>
            </section>

            <section class="panel" style="display:grid; gap:14px; align-content:start;">
                <h2 style="font-size:1.4rem;">Support window</h2>
                <p>Monday to Saturday, 10:00 AM to 7:00 PM (IST)</p>
                <p><strong>Email:</strong> support@ekiraya.com</p>
                <p><strong>Phone:</strong> +91 98765 43210</p>
                <figure style="margin:6px 0 0; border-radius:14px; overflow:hidden;">
                    <img
                        src="https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=1200&q=80"
                        alt="Customer support team at work"
                        loading="lazy"
                        referrerpolicy="no-referrer"
                    >
                </figure>
                <p style="font-size:0.88rem;">For account-specific issues, mention your registered shop phone number for faster handling.</p>
            </section>
        </div>
    </div>
</section>
@endsection
