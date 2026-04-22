@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="profile-shell">
        <div class="profile-grid">
            <div>
                <div class="profile-card">
                    <div class="profile-av-wrap">
                        <div class="profile-av">{{ substr(auth()->user()->name, 0, 2) }}</div>
                    </div>
                    <div class="profile-name">{{ auth()->user()->name }}</div>
                    <div class="profile-role">Super Admin</div>
                    <div class="profile-stats">
                        <div class="ps-item"><div class="ps-v">{{ $totalShops }}</div><div class="ps-l">Shops</div></div>
                        <div class="ps-item"><div class="ps-v">{{ $totalCustomers }}</div><div class="ps-l">Customers</div></div>
                        <div class="ps-item"><div class="ps-v">{{ $totalVerifications }}</div><div class="ps-l">Verifs</div></div>
                    </div>
                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit" class="btn btn-ghost">Logout</button></form>
                </div>
            </div>
            <div class="profile-right">
                <div class="panel">
                    <div class="ph"><span class="ph-title">Recent activity</span></div>
                    <div class="pb" id="profileActivity">
                        @foreach($activities as $a)
                        <div class="activity-item">
                            <div class="act-dot" style="background:{{ $a['color'] }}"></div>
                            <div>
                                <div class="act-text">{{ $a['text'] }}</div>
                                <div class="act-time">{{ $a['time'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection