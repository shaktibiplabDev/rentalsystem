<header class="topbar">
    <span class="brand">RENT·AI</span>
    <div class="tb-div"></div>
    <span class="page-title" id="pageTitle">Dashboard</span>
    <div class="tb-spacer"></div>
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="globalSearch" placeholder="Search shops, customers, GST...">
    </div>
    <div class="tb-pill"><div class="pulse"></div><span id="shopCount">0</span> shops live</div>
    <div class="notif-btn"><i class="fas fa-bell"></i><div class="notif-dot"></div></div>
    <form method="POST" action="{{ route('logout') }}" id="logout-form" style="display:none;">@csrf</form>
    <div class="nav-avatar" onclick="document.getElementById('logout-form').submit();" style="cursor:pointer; margin-left:8px;">Logout</div>
</header>