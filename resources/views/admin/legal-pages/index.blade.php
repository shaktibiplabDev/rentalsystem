@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="panel" style="margin: 16px;">
        <div class="ph">
            <span class="ph-title">Legal Pages Management</span>
            <a href="{{ route('admin.legal-pages.create') }}" class="btn btn-solid" style="padding: 6px 12px;">
                <i class="fas fa-plus"></i> Add New Page
            </a>
        </div>
        <div class="pb" style="padding: 0;">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Icon</th>
                        <th>Page</th>
                        <th>Slug</th>
                        <th>Version</th>
                        <th>Footer</th>
                        <th>Status</th>
                        <th>Order</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pages as $page)
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
                        <td>
                            <span class="badge {{ $page->is_active ? 'badge-green' : 'badge-red' }}">
                                {{ $page->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="tbl-mono">{{ $page->order }}</td>
                        <td class="tbl-mono">{{ $page->updated_at->format('d M Y') }}</td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="{{ route('admin.legal-pages.edit', $page->id) }}" class="row-btn" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deletePage({{ $page->id }})" class="row-btn" title="Delete" style="color: var(--red);">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function deletePage(id) {
    if(confirm('Are you sure you want to delete this legal page?')) {
        fetch(`/admin/legal-pages/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        }).then(response => response.json()).then(data => {
            if(data.success) location.reload();
        });
    }
}
</script>
@endsection