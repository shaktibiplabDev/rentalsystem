<header class="topbar">
    <span class="brand">RENT·AI</span>
    <div class="tb-div"></div>
    <span class="page-title" id="pageTitle">Dashboard</span>
    <div class="tb-spacer"></div>
    
    <!-- Smart Search Box -->
    <div class="search-box" style="position: relative;">
        <i class="fas fa-search"></i>
        <input type="text" id="globalSearch" placeholder="Search shops, customers, rentals..." autocomplete="off">
        <div id="searchResults" class="search-results-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; margin-top: 4px; z-index: 1000; max-height: 400px; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.5);"></div>
    </div>
    
    <div class="tb-pill">
        <div class="pulse"></div>
        <span id="shopCount">{{ $totalShops ?? 0 }}</span> shops live
    </div>
    
    <form method="POST" action="{{ route('logout') }}" id="logout-form" style="display:none;">@csrf</form>
    <div class="nav-avatar" onclick="document.getElementById('logout-form').submit();" style="cursor:pointer; margin-left:8px;">
        {{ substr(auth()->user()->name ?? 'AD', 0, 2) }}
    </div>
</header>

<style>
.search-results-dropdown::-webkit-scrollbar {
    width: 4px;
}
.search-results-dropdown::-webkit-scrollbar-track {
    background: var(--surface);
}
.search-results-dropdown::-webkit-scrollbar-thumb {
    background: var(--border-hi);
    border-radius: 4px;
}
.search-result-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    cursor: pointer;
    transition: background 0.15s;
    border-bottom: 1px solid var(--border);
}
.search-result-item:hover {
    background: var(--surface-hover);
}
.search-result-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    flex-shrink: 0;
}
.search-result-icon.shops { background: rgba(79,110,247,0.15); color: #4f6ef7; }
.search-result-icon.customers { background: rgba(240,180,41,0.15); color: #f0b429; }
.search-result-icon.rentals { background: rgba(31,207,170,0.15); color: #1fcfaa; }
.search-result-info {
    flex: 1;
}
.search-result-title {
    font-size: 13px;
    font-weight: 500;
    color: var(--text);
}
.search-result-subtitle {
    font-size: 10px;
    color: var(--text-3);
    margin-top: 2px;
}
.search-result-type {
    font-size: 9px;
    font-family: var(--mono);
    padding: 2px 6px;
    border-radius: 4px;
    background: var(--surface);
    color: var(--text-2);
}
.search-section-title {
    padding: 8px 12px;
    font-size: 9px;
    font-family: var(--mono);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--accent);
    background: var(--surface);
    border-bottom: 1px solid var(--border);
}
.search-no-results {
    padding: 20px;
    text-align: center;
    color: var(--text-3);
    font-size: 12px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('globalSearch');
    const resultsContainer = document.getElementById('searchResults');
    let searchTimeout;
    
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            resultsContainer.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });
    
    // Close results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
    
    async function performSearch(query) {
        try {
            const response = await fetch(`/admin/search?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            displayResults(data, query);
        } catch (error) {
            console.error('Search failed:', error);
            resultsContainer.innerHTML = '<div class="search-no-results">Search error. Please try again.</div>';
            resultsContainer.style.display = 'block';
        }
    }
    
    function displayResults(data, query) {
        let html = '';
        
        // Shops Section
        if (data.shops && data.shops.length > 0) {
            html += `<div class="search-section-title">SHOPS (${data.shops.length})</div>`;
            data.shops.forEach(shop => {
                html += `
                    <div class="search-result-item" onclick="window.location.href='/admin/shops/${shop.id}'">
                        <div class="search-result-icon shops">
                            <i class="fas fa-store-alt"></i>
                        </div>
                        <div class="search-result-info">
                            <div class="search-result-title">${escapeHtml(shop.name)}</div>
                            <div class="search-result-subtitle">${escapeHtml(shop.email || shop.phone || 'No contact')}</div>
                        </div>
                        <div class="search-result-type">Shop</div>
                    </div>
                `;
            });
        }
        
        // Customers Section
        if (data.customers && data.customers.length > 0) {
            html += `<div class="search-section-title">CUSTOMERS (${data.customers.length})</div>`;
            data.customers.forEach(customer => {
                html += `
                    <div class="search-result-item" onclick="window.location.href='/admin/customers/${customer.id}'">
                        <div class="search-result-icon customers">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="search-result-info">
                            <div class="search-result-title">${escapeHtml(customer.name)}</div>
                            <div class="search-result-subtitle">${escapeHtml(customer.phone || customer.address || 'No details')}</div>
                        </div>
                        <div class="search-result-type">Customer</div>
                    </div>
                `;
            });
        }
        
        // Rentals Section
        if (data.rentals && data.rentals.length > 0) {
            html += `<div class="search-section-title">RENTALS (${data.rentals.length})</div>`;
            data.rentals.forEach(rental => {
                html += `
                    <div class="search-result-item" onclick="window.location.href='/admin/rentals/${rental.id}'">
                        <div class="search-result-icon rentals">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="search-result-info">
                            <div class="search-result-title">#${rental.id} - ${escapeHtml(rental.vehicle_name)}</div>
                            <div class="search-result-subtitle">Customer: ${escapeHtml(rental.customer_name)}</div>
                        </div>
                        <div class="search-result-type">Rental</div>
                    </div>
                `;
            });
        }
        
        if (!html) {
            html = `<div class="search-no-results">No results found for "${escapeHtml(query)}"</div>`;
        }
        
        resultsContainer.innerHTML = html;
        resultsContainer.style.display = 'block';
    }
    
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
});
</script>