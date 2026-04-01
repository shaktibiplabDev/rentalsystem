<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request; 

class DashboardController extends Controller
{
    /**
     * Get main dashboard statistics
     */
    public function stats()
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                Log::warning('Unauthenticated access to dashboard stats', [
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Fetch all stats in parallel using a single query where possible
            try {
                $totalVehicles = Vehicle::where('user_id', $userId)->count();
                $availableVehicles = Vehicle::where('user_id', $userId)
                    ->where('status', 'available')
                    ->count();
                $onRentVehicles = Vehicle::where('user_id', $userId)
                    ->where('status', 'on_rent')
                    ->count();
                $maintenanceVehicles = Vehicle::where('user_id', $userId)
                    ->where('status', 'maintenance')
                    ->count();
                $activeRentals = Rental::where('user_id', $userId)
                    ->where('status', 'active')
                    ->count();
                $completedRentals = Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->count();
                $cancelledRentals = Rental::where('user_id', $userId)
                    ->where('status', 'cancelled')
                    ->count();
                $totalEarnings = (float) Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->sum('total_price');
                    
                // Get pending earnings (active rentals that haven't been completed yet)
                $pendingEarnings = (float) Rental::where('user_id', $userId)
                    ->where('status', 'active')
                    ->sum('total_price');
                    
            } catch (QueryException $e) {
                Log::error('Database error fetching dashboard stats', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'sql' => method_exists($e, 'getSql') ? $e->getSql() : null
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch dashboard statistics',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            // Calculate utilization rate
            $utilizationRate = $totalVehicles > 0 
                ? round(($onRentVehicles / $totalVehicles) * 100, 2)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'vehicles' => [
                        'total' => $totalVehicles,
                        'available' => $availableVehicles,
                        'on_rent' => $onRentVehicles,
                        'maintenance' => $maintenanceVehicles,
                        'utilization_rate' => $utilizationRate
                    ],
                    'rentals' => [
                        'active' => $activeRentals,
                        'completed' => $completedRentals,
                        'cancelled' => $cancelledRentals,
                        'total' => $activeRentals + $completedRentals + $cancelledRentals
                    ],
                    'earnings' => [
                        'total' => $totalEarnings,
                        'pending' => $pendingEarnings,
                        'average_per_rental' => $completedRentals > 0 
                            ? round($totalEarnings / $completedRentals, 2) 
                            : 0
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching dashboard stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get recent activity (recent rentals)
     */
    public function recentActivity()
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                Log::warning('Unauthenticated access to recent activity', [
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $recentRentals = Rental::where('user_id', $userId)
                    ->with(['vehicle', 'customer'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($rental) {
                        try {
                            return [
                                'id' => $rental->id,
                                'vehicle' => [
                                    'name' => $rental->vehicle->name ?? 'N/A',
                                    'number_plate' => $rental->vehicle->number_plate ?? 'N/A',
                                    'type' => $rental->vehicle->type ?? 'N/A'
                                ],
                                'customer' => [
                                    'name' => $rental->customer->name ?? 'N/A',
                                    'phone' => $rental->customer->phone ?? 'N/A'
                                ],
                                'status' => $rental->status,
                                'total_price' => (float) ($rental->total_price ?? 0),
                                'duration_hours' => $rental->start_time && $rental->end_time 
                                    ? ceil($rental->start_time->diffInHours($rental->end_time)) 
                                    : null,
                                'created_at' => $rental->created_at,
                                'start_time' => $rental->start_time,
                                'end_time' => $rental->end_time
                            ];
                        } catch (Exception $e) {
                            Log::warning('Failed to format rental activity', [
                                'rental_id' => $rental->id,
                                'error' => $e->getMessage()
                            ]);
                            
                            return [
                                'id' => $rental->id,
                                'error' => 'Failed to load rental details',
                                'status' => $rental->status,
                                'created_at' => $rental->created_at
                            ];
                        }
                    });
            } catch (QueryException $e) {
                Log::error('Database error fetching recent activity', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch recent activity',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            // Get recent vehicles added
            try {
                $recentVehicles = Vehicle::where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($vehicle) {
                        return [
                            'id' => $vehicle->id,
                            'name' => $vehicle->name,
                            'number_plate' => $vehicle->number_plate,
                            'type' => $vehicle->type,
                            'status' => $vehicle->status,
                            'created_at' => $vehicle->created_at
                        ];
                    });
            } catch (QueryException $e) {
                Log::warning('Failed to fetch recent vehicles', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                $recentVehicles = collect();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'recent_rentals' => $recentRentals,
                    'recent_vehicles' => $recentVehicles,
                    'summary' => [
                        'total_rentals_today' => $this->getTodayRentalsCount($userId),
                        'total_earnings_today' => $this->getTodayEarnings($userId),
                        'active_rentals_count' => $recentRentals->where('status', 'active')->count()
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching recent activity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent activity',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get vehicle statistics (by type and status)
     */
    public function vehicleStats()
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                Log::warning('Unauthenticated access to vehicle stats', [
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $vehiclesByType = Vehicle::where('user_id', $userId)
                    ->select('type', DB::raw('count(*) as total'))
                    ->groupBy('type')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'type' => $item->type,
                            'total' => $item->total,
                            'percentage' => 0 // Will calculate after getting total
                        ];
                    });

                $vehiclesByStatus = Vehicle::where('user_id', $userId)
                    ->select('status', DB::raw('count(*) as total'))
                    ->groupBy('status')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'status' => $item->status,
                            'total' => $item->total,
                            'percentage' => 0 // Will calculate after getting total
                        ];
                    });

                $totalVehicles = $vehiclesByType->sum('total');

                // Calculate percentages
                $vehiclesByType = $vehiclesByType->map(function ($item) use ($totalVehicles) {
                    $item['percentage'] = $totalVehicles > 0 
                        ? round(($item['total'] / $totalVehicles) * 100, 2) 
                        : 0;
                    return $item;
                });

                $vehiclesByStatus = $vehiclesByStatus->map(function ($item) use ($totalVehicles) {
                    $item['percentage'] = $totalVehicles > 0 
                        ? round(($item['total'] / $totalVehicles) * 100, 2) 
                        : 0;
                    return $item;
                });

            } catch (QueryException $e) {
                Log::error('Database error fetching vehicle stats', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch vehicle statistics',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'by_type' => $vehiclesByType,
                    'by_status' => $vehiclesByStatus,
                    'total_vehicles' => $totalVehicles,
                    'available_vehicles' => $vehiclesByStatus->where('status', 'available')->first()['total'] ?? 0
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching vehicle stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vehicle statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get rental statistics with time-based analysis
     */
    public function rentalStats(Request $request)
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                Log::warning('Unauthenticated access to rental stats', [
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Optional date range filter
            $days = $request->get('days', 30);
            $days = min(max($days, 1), 365); // Limit between 1 and 365 days

            try {
                // Get daily stats for the last X days
                $dailyStats = Rental::where('user_id', $userId)
                    ->where('created_at', '>=', now()->subDays($days))
                    ->select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('count(*) as total_rentals'),
                        DB::raw('SUM(CASE WHEN status = "completed" THEN total_price ELSE 0 END) as daily_revenue'),
                        DB::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_rentals')
                    )
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'date' => $item->date,
                            'total_rentals' => (int) $item->total_rentals,
                            'completed_rentals' => (int) $item->completed_rentals,
                            'revenue' => (float) $item->daily_revenue
                        ];
                    });

                // Get monthly stats
                $monthlyStats = Rental::where('user_id', $userId)
                    ->where('created_at', '>=', now()->subMonths(12))
                    ->select(
                        DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                        DB::raw('count(*) as total_rentals'),
                        DB::raw('SUM(CASE WHEN status = "completed" THEN total_price ELSE 0 END) as monthly_revenue')
                    )
                    ->groupBy('month')
                    ->orderBy('month', 'desc')
                    ->limit(12)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'month' => $item->month,
                            'total_rentals' => (int) $item->total_rentals,
                            'revenue' => (float) $item->monthly_revenue
                        ];
                    });

                $totalRevenue = (float) Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->sum('total_price');

                $totalRentals = Rental::where('user_id', $userId)->count();
                $completedRentals = Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->count();

                $averageRentalValue = $completedRentals > 0 
                    ? round($totalRevenue / $completedRentals, 2)
                    : 0;

                $completionRate = $totalRentals > 0
                    ? round(($completedRentals / $totalRentals) * 100, 2)
                    : 0;

                // Get peak hours analysis
                $peakHours = $this->getPeakHours($userId);

            } catch (QueryException $e) {
                Log::error('Database error fetching rental stats', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch rental statistics',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'daily' => $dailyStats,
                    'monthly' => $monthlyStats,
                    'summary' => [
                        'total_rentals' => $totalRentals,
                        'completed_rentals' => $completedRentals,
                        'total_revenue' => $totalRevenue,
                        'average_rental_value' => $averageRentalValue,
                        'completion_rate' => $completionRate,
                        'peak_hours' => $peakHours
                    ],
                    'timeframe' => [
                        'days' => $days,
                        'start_date' => now()->subDays($days)->format('Y-m-d'),
                        'end_date' => now()->format('Y-m-d')
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching rental stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rental statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get top performing vehicles
     */
    public function topVehicles()
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $topVehicles = Vehicle::where('user_id', $userId)
                    ->withCount(['rentals as total_rentals' => function($query) {
                        $query->where('status', 'completed');
                    }])
                    ->withSum(['rentals as total_revenue' => function($query) {
                        $query->where('status', 'completed');
                    }], 'total_price')
                    ->orderBy('total_revenue', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($vehicle) {
                        return [
                            'id' => $vehicle->id,
                            'name' => $vehicle->name,
                            'number_plate' => $vehicle->number_plate,
                            'type' => $vehicle->type,
                            'total_rentals' => (int) $vehicle->total_rentals,
                            'total_revenue' => (float) ($vehicle->total_revenue ?? 0),
                        ];
                    });
            } catch (QueryException $e) {
                Log::error('Database error fetching top vehicles', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch top vehicles',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $topVehicles
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching top vehicles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top vehicles',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get dashboard summary for quick overview
     */
    public function summary()
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $stats = [
                    'today' => [
                        'rentals' => $this->getTodayRentalsCount($userId),
                        'earnings' => $this->getTodayEarnings($userId),
                        'new_customers' => $this->getNewCustomersToday($userId)
                    ],
                    'this_week' => [
                        'rentals' => $this->getWeekRentalsCount($userId),
                        'earnings' => $this->getWeekEarnings($userId)
                    ],
                    'this_month' => [
                        'rentals' => $this->getMonthRentalsCount($userId),
                        'earnings' => $this->getMonthEarnings($userId)
                    ],
                    'comparison' => [
                        'vs_last_week' => $this->getGrowthRate($userId, 'week'),
                        'vs_last_month' => $this->getGrowthRate($userId, 'month')
                    ]
                ];
            } catch (QueryException $e) {
                Log::error('Database error fetching dashboard summary', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch dashboard summary',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching dashboard summary', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard summary',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Helper: Calculate completion rate
     */
    protected function calculateCompletionRate($userId)
    {
        try {
            $totalRentals = Rental::where('user_id', $userId)->count();
            $completedRentals = Rental::where('user_id', $userId)
                ->where('status', 'completed')
                ->count();

            if ($totalRentals == 0) {
                return 0;
            }

            return round(($completedRentals / $totalRentals) * 100, 2);
        } catch (Exception $e) {
            Log::error('Calculate completion rate error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Helper: Get today's rentals count
     */
    protected function getTodayRentalsCount($userId)
    {
        try {
            return Rental::where('user_id', $userId)
                ->whereDate('created_at', Carbon::today())
                ->count();
        } catch (Exception $e) {
            Log::error('Get today rentals count error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Helper: Get today's earnings
     */
    protected function getTodayEarnings($userId)
    {
        try {
            return (float) Rental::where('user_id', $userId)
                ->where('status', 'completed')
                ->whereDate('created_at', Carbon::today())
                ->sum('total_price');
        } catch (Exception $e) {
            Log::error('Get today earnings error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Helper: Get new customers today
     */
    protected function getNewCustomersToday($userId)
    {
        try {
            return \App\Models\Customer::where('user_id', $userId)
                ->whereDate('created_at', Carbon::today())
                ->count();
        } catch (Exception $e) {
            Log::error('Get new customers today error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Helper: Get week rentals count
     */
    protected function getWeekRentalsCount($userId)
    {
        try {
            return Rental::where('user_id', $userId)
                ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->count();
        } catch (Exception $e) {
            Log::error('Get week rentals count error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Helper: Get week earnings
     */
    protected function getWeekEarnings($userId)
    {
        try {
            return (float) Rental::where('user_id', $userId)
                ->where('status', 'completed')
                ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->sum('total_price');
        } catch (Exception $e) {
            Log::error('Get week earnings error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Helper: Get month rentals count
     */
    protected function getMonthRentalsCount($userId)
    {
        try {
            return Rental::where('user_id', $userId)
                ->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->count();
        } catch (Exception $e) {
            Log::error('Get month rentals count error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Helper: Get month earnings
     */
    protected function getMonthEarnings($userId)
    {
        try {
            return (float) Rental::where('user_id', $userId)
                ->where('status', 'completed')
                ->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->sum('total_price');
        } catch (Exception $e) {
            Log::error('Get month earnings error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Helper: Get growth rate
     */
    protected function getGrowthRate($userId, $period = 'week')
    {
        try {
            $current = $period === 'week' 
                ? $this->getWeekEarnings($userId)
                : $this->getMonthEarnings($userId);
                
            $previous = $period === 'week'
                ? (float) Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()])
                    ->sum('total_price')
                : (float) Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->whereMonth('created_at', Carbon::now()->subMonth()->month)
                    ->whereYear('created_at', Carbon::now()->subMonth()->year)
                    ->sum('total_price');
            
            if ($previous == 0) {
                return $current > 0 ? 100 : 0;
            }
            
            return round((($current - $previous) / $previous) * 100, 2);
        } catch (Exception $e) {
            Log::error('Get growth rate error', [
                'user_id' => $userId,
                'period' => $period,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Helper: Get peak hours for rentals
     */
    protected function getPeakHours($userId)
    {
        try {
            $peakHours = Rental::where('user_id', $userId)
                ->select(DB::raw('HOUR(start_time) as hour'), DB::raw('count(*) as total'))
                ->whereNotNull('start_time')
                ->groupBy('hour')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'hour' => (int) $item->hour,
                        'total_rentals' => (int) $item->total,
                        'time_range' => date('g:i A', strtotime("{$item->hour}:00")) . ' - ' . date('g:i A', strtotime(($item->hour + 1) . ":00"))
                    ];
                });
                
            return $peakHours;
        } catch (Exception $e) {
            Log::error('Get peak hours error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }
}