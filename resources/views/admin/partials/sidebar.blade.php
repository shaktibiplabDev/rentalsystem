<nav class="sidenav">
    <div class="nav-logo"><i class="fas fa-layer-group"></i></div>
    <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="fas fa-th-large"></i></a>
    <a href="{{ route('admin.map') }}" class="nav-item {{ request()->routeIs('admin.map') ? 'active' : '' }}"><i class="fas fa-map-marked-alt"></i></a>
    <div class="nav-sep"></div>
    <a href="{{ route('admin.fleet') }}" class="nav-item {{ request()->routeIs('admin.fleet') ? 'active' : '' }}"><i class="fas fa-car"></i></a>
    <a href="{{ route('admin.customers.index') }}" class="nav-item {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}"><i class="fas fa-users"></i></a>
    <a href="{{ route('admin.wallet.index') }}" class="nav-item {{ request()->routeIs('admin.wallet.*') ? 'active' : '' }}"><i class="fas fa-wallet"></i></a>
    <div class="nav-spacer"></div>
    <a href="{{ route('admin.settings.index') }}" class="nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"><i class="fas fa-sliders-h"></i></a>
    <a href="{{ route('admin.profile') }}" class="nav-item {{ request()->routeIs('admin.profile') ? 'active' : '' }}"><i class="fas fa-user-circle"></i></a>
    <div class="nav-avatar">{{ substr(auth()->user()->name ?? 'AD', 0, 2) }}</div>
</nav>