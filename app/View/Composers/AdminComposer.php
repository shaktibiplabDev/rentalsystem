<?php

namespace App\View\Composers;

use App\Models\User;
use Illuminate\View\View;

class AdminComposer
{
    public function compose(View $view)
    {
        $totalShops = User::where('role', 'user')->count();
        $view->with('totalShops', $totalShops);
    }
}