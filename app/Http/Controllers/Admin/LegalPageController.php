<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LegalPageController extends Controller
{
    public function index()
    {
        try {
            $pages = LegalPage::orderBy('order')->get();
            return view('admin.legal-pages.index', compact('pages'));
        } catch (\Exception $e) {
            Log::error('Legal page index error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to load legal pages. Please try again.');
        }
    }

    public function create()
    {
        return view('admin.legal-pages.create');
    }

    public function store(Request $request)
    {
        try {
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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Legal page store error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to create legal page. Please try again.')
                ->withInput();
        }
    }

    public function edit($id)
    {
        try {
            $page = LegalPage::findOrFail($id);
            return view('admin.legal-pages.edit', compact('page'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('admin.legal-pages.index')
                ->with('error', 'Legal page not found.');
        } catch (\Exception $e) {
            Log::error('Legal page edit error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to load legal page. Please try again.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('admin.legal-pages.index')
                ->with('error', 'Legal page not found.');
        } catch (\Exception $e) {
            Log::error('Legal page update error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to update legal page. Please try again.')
                ->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $page = LegalPage::findOrFail($id);
            $page->delete();

            return redirect()->route('admin.legal-pages.index')
                ->with('success', 'Legal page deleted successfully!');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('admin.legal-pages.index')
                ->with('error', 'Legal page not found.');
        } catch (\Exception $e) {
            Log::error('Legal page destroy error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to delete legal page. Please try again.');
        }
    }

    public function toggleStatus($id)
    {
        try {
            $page = LegalPage::findOrFail($id);
            $page->update(['is_active' => !$page->is_active]);

            return response()->json(['success' => true]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Page not found'], 404);
        } catch (\Exception $e) {
            Log::error('Legal page toggle error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to toggle status'], 500);
        }
    }
}