@extends('layouts.admin')

@section('content')
<div class="page active">
    <div class="panel" style="margin: 16px;">
        <div class="ph">
            <span class="ph-title">Create New Legal Page</span>
            <a href="{{ route('admin.legal-pages.index') }}" class="btn btn-ghost">← Back</a>
        </div>
        <div class="pb">
            <form method="POST" action="{{ route('admin.legal-pages.store') }}">
                @csrf
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Slug (URL) *</label>
                        <input type="text" name="slug" class="form-input" required placeholder="privacy-policy">
                        <small>Use lowercase letters, numbers, and hyphens only</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Icon (FontAwesome)</label>
                        <input type="text" name="icon" class="form-input" value="fas fa-file-alt">
                        <small>Example: fas fa-shield-alt, fas fa-file-contract</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Content (HTML) *</label>
                    <textarea name="content" rows="20" class="form-input" required placeholder="<h2>Section Title</h2><p>Your content here...</p>"></textarea>
                    <small>You can use HTML tags: &lt;h2&gt;, &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;em&gt;</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Excerpt (Short description for SEO)</label>
                    <textarea name="excerpt" rows="3" class="form-input"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Meta Title (SEO)</label>
                        <input type="text" name="meta_title" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Meta Description (SEO)</label>
                        <textarea name="meta_description" rows="2" class="form-input"></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="order" class="form-input" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Show in Footer</label>
                        <select name="show_in_footer" class="form-select">
                            <option value="1">Yes - Show in footer</option>
                            <option value="0">No - Hide from footer</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1">Active - Visible on website</option>
                        <option value="0">Inactive - Hidden</option>
                    </select>
                </div>
                
                <div class="save-bar">
                    <button type="submit" class="btn btn-solid">Create Legal Page</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection