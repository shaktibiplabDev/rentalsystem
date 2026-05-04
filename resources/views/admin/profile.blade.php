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
                        <button class="btn btn-accent" style="justify-content: center; width: 100%;" onclick="openModal('editProfileModal')">
                            <i class="fas fa-edit"></i> Edit profile
                        </button>
                        <button class="btn btn-ghost" style="justify-content: center; width: 100%;" onclick="openModal('changePasswordModal')">
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
                        <div class="ms">{{ $freshVerifications ?? 0 }} fresh · {{ $cachedVerifications ?? 0 }} cached</div>
                    </div>
                    <div class="mcard">
                        <div class="ml">Total Rentals</div>
                        <div class="mv mv-accent">{{ $totalRentals ?? 0 }}</div>
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

{{-- Edit Profile Modal --}}
<div id="editProfileModal" class="modal" style="display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(5, 9, 19, 0.9); backdrop-filter: blur(8px); align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg2); border: 1px solid var(--border); border-radius: var(--r); max-width: 420px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 14px; font-weight: 600; color: var(--text);"><i class="fas fa-edit" style="margin-right: 8px; color: var(--accent);"></i>Edit Profile</h3>
            <button onclick="closeModal('editProfileModal')" class="btn btn-ghost" style="padding: 4px 8px;"><i class="fas fa-times"></i></button>
        </div>
        <form action="{{ route('admin.profile.update') }}" method="POST">
            @csrf
            <div class="modal-body" style="padding: 20px;">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 11px; color: var(--text-3); margin-bottom: 6px; font-weight: 500;">Full Name</label>
                        <input type="text" name="name" value="{{ auth()->user()->name ?? '' }}" required
                            style="width: 100%; padding: 10px 12px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-sm); color: var(--text); font-size: 13px; font-family: var(--font);"
                            placeholder="Enter your full name">
                    </div>
                    <div>
                        <label style="display: block; font-size: 11px; color: var(--text-3); margin-bottom: 6px; font-weight: 500;">Email Address</label>
                        <input type="email" name="email" value="{{ auth()->user()->email ?? '' }}" required
                            style="width: 100%; padding: 10px 12px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-sm); color: var(--text); font-size: 13px; font-family: var(--font);"
                            placeholder="Enter your email">
                    </div>
                    <div>
                        <label style="display: block; font-size: 11px; color: var(--text-3); margin-bottom: 6px; font-weight: 500;">Phone Number (optional)</label>
                        <input type="tel" name="phone" value="{{ auth()->user()->phone ?? '' }}"
                            style="width: 100%; padding: 10px 12px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-sm); color: var(--text); font-size: 13px; font-family: var(--font);"
                            placeholder="Enter your phone number">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; padding: 16px 20px; border-top: 1px solid var(--border);">
                <button type="button" onclick="closeModal('editProfileModal')" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-accent"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Change Password Modal --}}
<div id="changePasswordModal" class="modal" style="display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(5, 9, 19, 0.9); backdrop-filter: blur(8px); align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg2); border: 1px solid var(--border); border-radius: var(--r); max-width: 420px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 14px; font-weight: 600; color: var(--text);"><i class="fas fa-key" style="margin-right: 8px; color: var(--amber);"></i>Change Password</h3>
            <button onclick="closeModal('changePasswordModal')" class="btn btn-ghost" style="padding: 4px 8px;"><i class="fas fa-times"></i></button>
        </div>
        <form action="{{ route('admin.profile.password') }}" method="POST">
            @csrf
            <div class="modal-body" style="padding: 20px;">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 11px; color: var(--text-3); margin-bottom: 6px; font-weight: 500;">Current Password</label>
                        <input type="password" name="current_password" required
                            style="width: 100%; padding: 10px 12px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-sm); color: var(--text); font-size: 13px; font-family: var(--font);"
                            placeholder="Enter current password">
                    </div>
                    <div>
                        <label style="display: block; font-size: 11px; color: var(--text-3); margin-bottom: 6px; font-weight: 500;">New Password</label>
                        <input type="password" name="password" required minlength="8"
                            style="width: 100%; padding: 10px 12px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-sm); color: var(--text); font-size: 13px; font-family: var(--font);"
                            placeholder="Enter new password (min 8 characters)">
                    </div>
                    <div>
                        <label style="display: block; font-size: 11px; color: var(--text-3); margin-bottom: 6px; font-weight: 500;">Confirm New Password</label>
                        <input type="password" name="password_confirmation" required
                            style="width: 100%; padding: 10px 12px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-sm); color: var(--text); font-size: 13px; font-family: var(--font);"
                            placeholder="Confirm new password">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; padding: 16px 20px; border-top: 1px solid var(--border);">
                <button type="button" onclick="closeModal('changePasswordModal')" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-accent"><i class="fas fa-save"></i> Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(function(modal) {
            modal.style.display = 'none';
        });
        document.body.style.overflow = '';
    }
});
</script>
@endsection
