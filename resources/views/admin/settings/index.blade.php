@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="settings-shell">
        <div class="settings-nav">
            <div class="snav-group">
                <div class="snav-group-title">General</div>
                <div class="snav-item active" data-section="general"><i class="fas fa-cog"></i>General</div>
                <div class="snav-item" data-section="verification"><i class="fas fa-shield-alt"></i>Verification</div>
                <div class="snav-item" data-section="legal"><i class="fas fa-gavel"></i>Legal Pages</div>
            </div>
        </div>
        <div class="settings-content" id="settingsContent">
            <!-- General Settings Section -->
            <div class="settings-section" id="section-general">
                <div class="ss-title">General Settings</div>
                <div class="ss-desc">Configure general platform settings</div>
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Platform Name</label>
                        <input type="text" name="platform_name" class="form-input" value="EKiraya">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Support Email</label>
                        <input type="email" name="support_email" class="form-input" value="support@ekiraya.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Support Phone</label>
                        <input type="text" name="support_phone" class="form-input" value="+91 98765 43210">
                    </div>
                    <button type="submit" class="btn btn-solid">Save Changes</button>
                </form>
            </div>

            <!-- Verification Settings Section -->
            <div class="settings-section" style="display: none;" id="section-verification">
                <div class="ss-title">Verification Settings</div>
                <div class="ss-desc">Manage verification pricing and thresholds</div>
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Verification Price (Shop Charge)</label>
                        <input type="number" name="verification_price" class="form-input" value="{{ $verificationPrice ?? 5 }}" step="0.5" min="0">
                        <small>Amount deducted from shop wallet per verification</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lease Threshold (Minutes)</label>
                        <input type="number" name="lease_threshold_minutes" class="form-input" value="{{ $leaseThreshold ?? 60 }}" step="1" min="1">
                        <small>Minimum rental duration before charging hourly rate</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Platform Profit (Fresh)</label>
                        <input type="number" class="form-input" value="₹1" disabled>
                        <small>Profit per fresh verification (₹3 charge - ₹2 cost)</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Platform Profit (Cached)</label>
                        <input type="number" class="form-input" value="₹3" disabled>
                        <small>Profit per cached verification (Pure profit)</small>
                    </div>
                    <button type="submit" class="btn btn-solid">Save Changes</button>
                </form>
            </div>

            <!-- Legal Pages Management Section -->
            <div class="settings-section" style="display: none;" id="section-legal">
                <div class="ss-title">Legal Pages Management</div>
                <div class="ss-desc">Manage privacy policy, terms of service, and other legal pages</div>
                
                <div style="margin-bottom: 20px;">
                    <a href="{{ route('admin.legal-pages.create') }}" class="btn btn-solid" style="display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-plus"></i> Add New Legal Page
                    </a>
                </div>
                
                <div class="table-responsive">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Icon</th>
                                <th>Page Title</th>
                                <th>Slug</th>
                                <th>Version</th>
                                <th>Footer</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $legalPages = \App\Models\LegalPage::orderBy('order')->get();
                            @endphp
                            @forelse($legalPages as $page)
                            <tr>
                                <td><i class="{{ $page->icon }}"></i></td>
                                <td>{{ $page->title }}</td>
                                <td class="tbl-mono">{{ $page->slug }}</td>
                                <td class="tbl-mono">v{{ $page->version }}</td>
                                <td>
                                    @if($page->show_in_footer)
                                    <span class="badge badge-green"><i class="fas fa-check"></i> Yes</span>
                                    @else
                                    <span class="badge badge-amber"><i class="fas fa-times"></i> No</span>
                                    @endif
                                </td>
                                <td class="tbl-mono">{{ $page->order }}</td>
                                <td>
                                    @if($page->is_active)
                                    <span class="badge badge-green">Active</span>
                                    @else
                                    <span class="badge badge-red">Inactive</span>
                                    @endif
                                </td>
                                <td class="tbl-mono">{{ $page->updated_at->format('d M Y') }}</td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="{{ route('admin.legal-pages.edit', $page->id) }}" class="row-btn" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="deleteLegalPage({{ $page->id }})" class="row-btn" title="Delete" style="color: var(--red);">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <a href="{{ route('legal.page', $page->slug) }}" target="_blank" class="row-btn" title="View">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-3);">
                                    <i class="fas fa-file-alt" style="font-size: 48px; margin-bottom: 12px; display: block;"></i>
                                    No legal pages created yet.
                                    <br>
                                    <a href="{{ route('admin.legal-pages.create') }}" style="color: var(--accent);">Create your first legal page</a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Section switching
    document.querySelectorAll('.snav-item').forEach(item => {
        item.addEventListener('click', function() {
            const section = this.dataset.section;
            
            // Update active state on nav items
            document.querySelectorAll('.snav-item').forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
            
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            document.getElementById(`section-${section}`).style.display = 'block';
        });
    });

    // Delete legal page function
    function deleteLegalPage(id) {
        if(confirm('Are you sure you want to delete this legal page? This action cannot be undone.')) {
            fetch(`/admin/legal-pages/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            }).then(response => response.json()).then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Failed to delete page');
                }
            }).catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }
    }
</script>

<style>
    .table-responsive {
        overflow-x: auto;
    }
    .tbl td, .tbl th {
        white-space: nowrap;
    }
    .row-btn {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--surface);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s;
    }
    .row-btn:hover {
        background: var(--surface-hover);
    }
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
</style>
@endsection
