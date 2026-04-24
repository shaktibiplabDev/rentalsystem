<?php

namespace App\Http\Controllers;

use App\Models\LegalPage;

class LegalPageController extends Controller
{
    public function show($slug)
    {
        $page = LegalPage::getBySlug($slug);
        
        if (!$page) {
            abort(404);
        }
        
        return view('legal.page', compact('page'));
    }
}