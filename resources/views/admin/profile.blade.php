@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="profile-shell">
        <div class="profile-grid">
            <!-- Left Column - Profile Card -->
            <div>
                <div class="profile-card">
                    <div class="profile-av-wrap">
                        <div class="profile-av">{{ substr(auth()->user()->name ?? 'AD', 0, 2) }}</div>
                        <div class="profile-av-badge">
                            <i class="fas fa-check" style="font-size: 7px; color: #fff;"></i>
                        </div>
                    </div>
                    <div class="profile-name">{{ auth()->user()->name ?? 'Admin' }}</div>
                    <div class="profile-role">Super Admin · EKiraya Platform</div>
                    <div class="profile-stats">
                        <div class="ps-item">
                            <div class="ps-v" style="color: var(--accent);">{{ $totalShops }}</div>
                            <div class="ps-l">Shops</div>
                        </div>
                        <div class="ps-item">
                            <div class="ps-v" style="color: var(--amber);">{{ $totalCustomers }}</div>
                            <div class="ps-l">Customers</div>
                        </div>
                        <div class="ps-item">
                            <div class="ps-v" style="color: var(--green);">{{ $totalVerifications }}</div>
                            <div class="ps-l">Verifications</div>
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 7px;">
                        <button class="btn btn-accent" style="justify-content: center; width: 100%;" onclick="alert('Edit profile coming soon')">
                            <i class="fas fa-edit"></i> Edit profile
                        </button>
                        <button class="btn btn-ghost" style="justify-content: center; width: 100%;" onclick="alert('Change password coming soon')">
                            <i class="fas fa-key"></i> Change password
                        </button>
                    </div>
                </div>

                <div class="panel" style="margin-top: 16px;">
                    <div class="ph"><span class="ph-title">Account Info</span></div>
                    <div class="pb">
                        <table style="width: 100%; font-size: 10px; font-family: var(--mono); border-collapse: collapse;">
                            <tr><td style="color: var(--text-3); padding: 5px 0;">Email</td><td style="text-align: right; color: var(--text-2);">{{ auth()->user()->email ?? 'N/A' }}</td></tr>
                            <tr><td style="color: var(--text-3); padding: 5px 0;">Phone</td><td style="text-align: right; color: var(--text-2);">{{ auth()->user()->phone ?? 'N/A' }}</td></tr>
                            <tr><td style="color: var(--text-3); padding: 5px 0;">Role</td><td style="text-align: right;"><span class="badge badge-purple">Super Admin</span></td></tr>
                            <tr><td style="color: var(--text-3); padding: 5px 0;">Member since</td><td style="text-align: right; color: var(--text-2);">{{ auth()->user()->created_at->format('d M Y') }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column - Activity -->
            <div class="profile-right">
                <div class="panel">
                    <div class="ph"><span class="ph-title">Recent Activity</span><span class="badge badge-accent">Live</span></div>
                    <div class="pb" id="profileActivity">
                        @foreach($activities as $activity)
                        <div class="activity-item">
                            <div class="act-dot" style="background: {{ $activity['color'] }}"></div>
                            <div>
                                <div class="act-text">{{ $activity['text'] }}</div>
                                <div class="act-time">{{ $activity['time'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Stats Cards -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="mcard">
                        <div class="ml">Platform Profit</div>
                        <div class="mv mv-green">₹{{ number_format($platformProfit ?? 0, 2) }}</div>
                        <div class="ms">From verifications</div>
                    </div>
                    <div class="mcard">
                        <div class="ml">Total Rentals</div>
                        <div class="mv mv-accent">{{ \App\Models\Rental::count() }}</div>
                        <div class="ms">All time</div>
                    </div>
                </div>

                <div class="panel">
                    <div class="ph"><span class="ph-title">Active Sessions</span></div>
                    <div class="pb" id="sessionList">
                        <div style="display: flex; align-items: center; gap: 10px; padding: 8px 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px;">
                            <i class="fas fa-laptop" style="font-size: 12px; color: var(--text-3); width: 16px; text-align: center;"></i>
                            <div style="flex: 1;">
                                <div style="font-size: 11px; font-weight: 600;">Current Session</div>
                                <div style="font-size: 9px; font-family: var(--mono); color: var(--text-3);">{{ request()->ip() }} · Active now</div>
                            </div>
                            <span class="badge badge-green">Current</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection