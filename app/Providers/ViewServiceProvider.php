<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\View\Composers\AdminComposer;

class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Apply to all admin views
        View::composer('admin.*', AdminComposer::class);
    }

    public function register(): void
    {
        //
    }
}