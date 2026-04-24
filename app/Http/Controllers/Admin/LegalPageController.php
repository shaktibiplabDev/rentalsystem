<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\Request;

class LegalPageController extends Controller
{
    public function index()
    {
        $pages = LegalPage::orderBy('order')->get();
        return view('admin.legal-pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.legal-pages.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'slug' => 'required|string|unique:legal_pages|regex:/^[a-z0-9-]+$/',
            'title' => 'required|string|max:255',
            'icon' => 'nullable|string',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'order' => 'integer',
            'show_in_footer' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['published_at'] = now();
        $validated['version'] = 1;

        LegalPage::create($validated);

        return redirect()->route('admin.legal-pages.index')
            ->with('success', 'Legal page created successfully!');
    }

    public function edit($id)
    {
        $page = LegalPage::findOrFail($id);
        return view('admin.legal-pages.edit', compact('page'));
    }

    public function update(Request $request, $id)
    {
        $page = LegalPage::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'icon' => 'nullable|string',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'order' => 'integer',
            'show_in_footer' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['version'] = $page->version + 1;
        
        if ($request->is_active && !$page->published_at) {
            $validated['published_at'] = now();
        }

        $page->update($validated);

        return redirect()->route('admin.legal-pages.index')
            ->with('success', 'Legal page updated successfully!');
    }

    public function destroy($id)
    {
        $page = LegalPage::findOrFail($id);
        $page->delete();

        return redirect()->route('admin.legal-pages.index')
            ->with('success', 'Legal page deleted successfully!');
    }

    public function toggleStatus($id)
    {
        $page = LegalPage::findOrFail($id);
        $page->update(['is_active' => !$page->is_active]);

        return response()->json(['success' => true]);
    }
}