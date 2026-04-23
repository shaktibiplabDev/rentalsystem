<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index()
    {
        $totalShops = User::where('role', 'user')->count();
        $shops = User::where('role', 'owner')
            ->select('id', 'name', 'wallet_balance')
            ->orderBy('wallet_balance', 'desc')
            ->get();
        
        $transactions = WalletTransaction::with('user')
            ->latest()
            ->paginate(30);
        
        $totalCredits = WalletTransaction::where('type', 'credit')
            ->where('status', 'completed')
            ->sum('amount');
        
        $totalDebits = WalletTransaction::where('type', 'debit')
            ->where('status', 'completed')
            ->sum('amount');
        
        $platformRevenue = WalletTransaction::whereIn('type', ['debit', 'credit'])
            ->where('status', 'completed')
            ->sum('amount'); // This needs adjustment – platform revenue from verifications
        
        // Platform revenue from verifications (profit)
        $platformRevenue = \App\Models\Rental::whereNotNull('verification_completed_at')
            ->get()
            ->sum(fn($r) => $r->is_verification_cached ? 3 : 1);
        
        return view('admin.wallet.index', compact('shops', 'transactions', 'totalCredits', 'totalDebits', 'platformRevenue', 'totalShops'));
    }
    
    public function addBalance(Request $request, $shopId)
    {
        $request->validate(['amount' => 'required|numeric|min:1|max:100000']);
        
        $shop = User::findOrFail($shopId);
        $shop->increment('wallet_balance', $request->amount);
        
        WalletTransaction::create([
            'user_id' => $shop->id,
            'amount' => $request->amount,
            'type' => 'credit',
            'reason' => 'Admin manual top-up',
            'status' => 'completed',
            'reference_id' => 'ADMIN_' . uniqid(),
        ]);
        
        return redirect()->back()->with('success', '₹' . number_format($request->amount, 2) . ' added to ' . $shop->name . '\'s wallet.');
    }
}