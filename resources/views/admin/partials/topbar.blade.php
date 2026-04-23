<header class="topbar">
    <span class="brand">RENT·AI</span>
    <div class="tb-div"></div>
    <span class="page-title" id="pageTitle">Dashboard</span>
    <div class="tb-spacer"></div>
    
    <form method="POST" action="{{ route('logout') }}" id="logout-form" style="display:none;">@csrf</form>
    <div class="nav-avatar" onclick="document.getElementById('logout-form').submit();" style="cursor:pointer;">
        {{ substr(auth()->user()->name ?? 'AD', 0, 2) }}
    </div>
</header>