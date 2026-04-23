<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    public function showLoginForm()
    {
        // If already logged in as admin, redirect to dashboard
        if (auth()->check() && auth()->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if ($user->role === 'admin') {
                $request->session()->regenerate();
                
                // Redirect to intended URL or dashboard
                $intendedUrl = session()->pull('url.intended');
                if ($intendedUrl && !str_contains($intendedUrl, '/login')) {
                    return redirect($intendedUrl);
                }
                
                return redirect()->intended(route('admin.dashboard'));
            }
            Auth::logout();
            return back()->with('error', 'You do not have admin access.')->withInput($request->only('email'));
        }

        return back()->with('error', 'Invalid credentials. Please try again.')->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/admin/login')->with('success', 'Logged out successfully');
    }
}