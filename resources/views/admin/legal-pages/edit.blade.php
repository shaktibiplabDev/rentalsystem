@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="panel" style="margin: 16px;">
        <div class="ph">
            <span class="ph-title">Edit: {{ $page->title }}</span>
            <a href="{{ route('admin.legal-pages.index') }}" class="btn btn-ghost">← Back</a>
        </div>
        <div class="pb">
            <form method="POST" action="{{ route('admin.legal-pages.update', $page->id) }}">
                @csrf
                @method('PUT')
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Slug</label>
                        <input type="text" class="form-input" value="{{ $page->slug }}" disabled>
                        <small>Slug cannot be changed after creation</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Icon (FontAwesome)</label>
                        <input type="text" name="icon" class="form-input" value="{{ old('icon', $page->icon) }}">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" value="{{ old('title', $page->title) }}" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Content (HTML) *</label>
                    <textarea name="content" rows="20" class="form-input" required>{{ old('content', $page->content) }}</textarea>
                    <small>Current version: v{{ $page->version }} → Next: v{{ $page->version + 1 }}</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Excerpt</label>
                    <textarea name="excerpt" rows="3" class="form-input">{{ old('excerpt', $page->excerpt) }}</textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Meta Title</label>
                        <input type="text" name="meta_title" class="form-input" value="{{ old('meta_title', $page->meta_title) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description" rows="2" class="form-input">{{ old('meta_description', $page->meta_description) }}</textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="order" class="form-input" value="{{ old('order', $page->order) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Show in Footer</label>
                        <select name="show_in_footer" class="form-select">
                            <option value="1" {{ $page->show_in_footer ? 'selected' : '' }}>Yes - Show in footer</option>
                            <option value="0" {{ !$page->show_in_footer ? 'selected' : '' }}>No - Hide from footer</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1" {{ $page->is_active ? 'selected' : '' }}>Active - Visible on website</option>
                        <option value="0" {{ !$page->is_active ? 'selected' : '' }}>Inactive - Hidden</option>
                    </select>
                </div>
                
                <div class="save-bar">
                    <button type="submit" class="btn btn-solid">Save Changes</button>
                    <span class="form-text">Updated: {{ $page->updated_at->format('d M Y, h:i A') }}</span>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection