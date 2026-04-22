<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use App\Models\Rental;

class ProfileController extends Controller
{
    public function index()
    {
        $totalShops = User::where('role', 'owner')->count();
        $totalCustomers = Customer::count();
        $totalVerifications = Rental::whereNotNull('verification_completed_at')->count();
        $activities = [
            ['color' => 'var(--green)', 'text' => 'Logged in', 'time' => now()->format('d M Y, h:i A')],
            ['color' => 'var(--accent)', 'text' => 'Viewed dashboard', 'time' => now()->subMinutes(5)->format('d M Y, h:i A')],
        ];
        return view('admin.profile', compact('totalShops', 'totalCustomers', 'totalVerifications', 'activities'));
    }
}