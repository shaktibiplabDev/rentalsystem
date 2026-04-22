@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="settings-shell">
        <div class="settings-nav">
            <div class="snav-group">
                <div class="snav-group-title">General</div>
                <div class="snav-item active" data-section="general"><i class="fas fa-cog"></i>General</div>
                <div class="snav-item" data-section="verification"><i class="fas fa-shield-alt"></i>Verification</div>
            </div>
        </div>
        <div class="settings-content" id="settingsContent">
            <div class="settings-section">
                <div class="ss-title">Verification Settings</div>
                <div class="ss-desc">Manage verification pricing and thresholds</div>
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Verification price (shop charge)</label>
                        <input type="number" name="verification_price" class="form-input" value="{{ $verificationPrice }}" step="0.5" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lease threshold (minutes)</label>
                        <input type="number" name="lease_threshold_minutes" class="form-input" value="{{ $leaseThreshold }}" step="1" min="1">
                    </div>
                    <button type="submit" class="btn btn-solid">Save changes</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection