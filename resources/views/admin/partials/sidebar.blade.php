<nav class="sidenav">
    <div class="nav-logo"><i class="fas fa-layer-group"></i></div>
    
    <!-- Dashboard -->
    <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" title="Dashboard">
        <i class="fas fa-th-large"></i>
    </a>
    
    <!-- Map -->
    <a href="{{ route('admin.map') }}" class="nav-item {{ request()->routeIs('admin.map') ? 'active' : '' }}" title="Territory Map">
        <i class="fas fa-map-marked-alt"></i>
    </a>
    
    <!-- Separator -->
    <div class="nav-sep"></div>
    
    <!-- Fleet Management -->
    <a href="{{ route('admin.fleet') }}" class="nav-item {{ request()->routeIs('admin.fleet') ? 'active' : '' }}" title="Fleet Management">
        <i class="fas fa-car"></i>
    </a>
    
    <!-- Shops Management -->
    <a href="{{ route('admin.shops.index') }}" class="nav-item {{ request()->routeIs('admin.shops.*') ? 'active' : '' }}" title="Shops Management">
        <i class="fas fa-store-alt"></i>
    </a>
    
    <!-- Customers Management -->
    <a href="{{ route('admin.customers.index') }}" class="nav-item {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" title="Customers Management">
        <i class="fas fa-users"></i>
    </a>
    
    <!-- Rentals Management -->
    <a href="{{ route('admin.rentals.index') }}" class="nav-item {{ request()->routeIs('admin.rentals.*') ? 'active' : '' }}" title="Rentals Management">
        <i class="fas fa-receipt"></i>
    </a>
    
    <!-- Wallet Management -->
    <a href="{{ route('admin.wallet.index') }}" class="nav-item {{ request()->routeIs('admin.wallet.*') ? 'active' : '' }}" title="Wallet Management">
        <i class="fas fa-wallet"></i>
    </a>
    
    <!-- Spacer -->
    <div class="nav-spacer"></div>
    
    <!-- Settings -->
    <a href="{{ route('admin.settings.index') }}" class="nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" title="Settings">
        <i class="fas fa-sliders-h"></i>
    </a>
    
    <!-- Profile -->
    <a href="{{ route('admin.profile') }}" class="nav-item {{ request()->routeIs('admin.profile') ? 'active' : '' }}" title="Profile">
        <i class="fas fa-user-circle"></i>
    </a>
    
    <!-- Avatar / Logout -->
    <div class="nav-avatar" title="{{ auth()->user()->name ?? 'Admin' }}">
        {{ substr(auth()->user()->name ?? 'AD', 0, 2) }}
    </div>
</nav>