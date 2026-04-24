<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\Request;

class LegalPageController extends Controller
{
    /**
     * Get list of all active legal pages
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $pages = LegalPage::where('is_active', true)
            ->orderBy('order')
            ->get(['id', 'slug', 'title', 'icon', 'excerpt', 'updated_at']);

        return response()->json([
            'success' => true,
            'data' => $pages,
            'total' => $pages->count()
        ]);
    }

    /**
     * Get a single legal page by slug
     * 
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($slug)
    {
        $page = LegalPage::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Legal page not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'content' => $page->content, // HTML content
                'excerpt' => $page->excerpt,
                'icon' => $page->icon,
                'updated_at' => $page->updated_at->toIso8601String(),
                'version' => $page->version
            ]
        ]);
    }
}