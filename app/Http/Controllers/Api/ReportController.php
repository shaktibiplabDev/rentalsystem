<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerAccessLog;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    /**
     * Generate earnings report
     */
    public function earnings(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                Log::warning('Unauthenticated access to earnings report', [
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $validated = [];

            try {
                $query = Rental::where('user_id', $userId)
                    ->where('status', 'completed');

                // Date filtering
                if ($request->has('start_date') && $request->has('end_date')) {
                    $validated = $request->validate([
                        'start_date' => 'required|date',
                        'end_date' => 'required|date|after_or_equal:start_date',
                    ]);

                    $startDate = Carbon::parse($validated['start_date'])->startOfDay();
                    $endDate = Carbon::parse($validated['end_date'])->endOfDay();

                    $query->whereBetween('end_time', [$startDate, $endDate]);
                } elseif ($request->has('start_date') || $request->has('end_date')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Both start_date and end_date are required for date range filtering',
                    ], 422);
                }

                // Optional: Filter by month/year
                if ($request->has('month') && $request->has('year')) {
                    $validated = $request->validate([
                        'month' => 'required|integer|between:1,12',
                        'year' => 'required|integer|min:2000|max:'.date('Y'),
                    ]);

                    $startDate = Carbon::createFromDate($validated['year'], $validated['month'], 1)->startOfMonth();
                    $endDate = $startDate->copy()->endOfMonth();

                    $query->whereBetween('end_time', [$startDate, $endDate]);
                }

                $totalEarnings = (float) $query->sum('total_price');

                // Get daily earnings breakdown
                $dailyEarnings = $query->select(
                    DB::raw('DATE(end_time) as date'),
                    DB::raw('COUNT(*) as rental_count'),
                    DB::raw('SUM(total_price) as total')
                )
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'date' => $item->date,
                            'rental_count' => (int) $item->rental_count,
                            'total' => (float) $item->total,
                        ];
                    });

                // Get summary statistics
                $summary = [
                    'total_earnings' => $totalEarnings,
                    'average_daily_earnings' => $dailyEarnings->count() > 0
                        ? round($totalEarnings / $dailyEarnings->count(), 2)
                        : 0,
                    'highest_earning_day' => $dailyEarnings->first() ? [
                        'date' => $dailyEarnings->first()['date'],
                        'amount' => $dailyEarnings->first()['total'],
                    ] : null,
                    'total_rentals' => $dailyEarnings->sum('rental_count'),
                    'average_rental_value' => $dailyEarnings->sum('rental_count') > 0
                        ? round($totalEarnings / $dailyEarnings->sum('rental_count'), 2)
                        : 0,
                ];
            } catch (ValidationException $e) {
                Log::warning('Earnings report validation failed', [
                    'errors' => $e->errors(),
                    'user_id' => $userId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            } catch (QueryException $e) {
                Log::error('Database error generating earnings report', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'sql' => method_exists($e, 'getSql') ? $e->getSql() : null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate earnings report',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'daily_earnings' => $dailyEarnings,
                    'filters' => [
                        'start_date' => $validated['start_date'] ?? null,
                        'end_date' => $validated['end_date'] ?? null,
                        'month' => $request->month ?? null,
                        'year' => $request->year ?? null,
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Unexpected error generating earnings report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate earnings report',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Generate rentals report with filtering
     */
    public function rentals(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                Log::warning('Unauthenticated access to rentals report', [
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            try {
                $query = Rental::where('user_id', $userId)
                    ->with(['vehicle', 'customer']);

                // Date filtering
                if ($request->has('start_date') && $request->has('end_date')) {
                    $validated = $request->validate([
                        'start_date' => 'required|date',
                        'end_date' => 'required|date|after_or_equal:start_date',
                    ]);

                    $startDate = Carbon::parse($validated['start_date'])->startOfDay();
                    $endDate = Carbon::parse($validated['end_date'])->endOfDay();

                    $query->whereBetween('created_at', [$startDate, $endDate]);
                } elseif ($request->has('start_date') || $request->has('end_date')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Both start_date and end_date are required for date range filtering',
                    ], 422);
                }

                // Single date filtering
                if ($request->has('date')) {
                    $validated = $request->validate([
                        'date' => 'required|date',
                    ]);

                    $date = Carbon::parse($validated['date']);
                    $query->whereDate('created_at', $date);
                }

                // Status filtering
                if ($request->has('status')) {
                    $validated = $request->validate([
                        'status' => 'required|in:active,completed,cancelled',
                    ]);

                    $query->where('status', $validated['status']);
                }

                // Vehicle filtering
                if ($request->has('vehicle_id')) {
                    $validated = $request->validate([
                        'vehicle_id' => 'required|exists:vehicles,id',
                    ]);

                    $query->where('vehicle_id', $validated['vehicle_id']);
                }

                // Customer filtering
                if ($request->has('customer_id')) {
                    $validated = $request->validate([
                        'customer_id' => 'required|exists:customers,id',
                    ]);

                    $query->where('customer_id', $validated['customer_id']);
                }

                // Sorting
                $sortBy = $request->get('sort_by', 'created_at');
                $sortOrder = $request->get('sort_order', 'desc');
                $allowedSortFields = ['id', 'created_at', 'start_time', 'end_time', 'total_price', 'status'];

                if (! in_array($sortBy, $allowedSortFields)) {
                    $sortBy = 'created_at';
                }

                $query->orderBy($sortBy, $sortOrder);

                // Pagination
                $perPage = $request->get('per_page', 15);
                $perPage = min(max($perPage, 1), 100); // Limit between 1 and 100
                $page = $request->get('page', 1);

                $rentals = $query->paginate($perPage, ['*'], 'page', $page);

                $formattedRentals = $rentals->getCollection()->map(function ($rental) {
                    try {
                        $durationHours = null;
                        if ($rental->start_time && $rental->end_time) {
                            $durationHours = ceil($rental->start_time->diffInHours($rental->end_time));
                        } elseif ($rental->start_time && $rental->status === 'active') {
                            $durationHours = ceil($rental->start_time->diffInHours(now()));
                        }

                        return [
                            'id' => $rental->id,
                            'vehicle' => [
                                'id' => $rental->vehicle->id ?? null,
                                'name' => $rental->vehicle->name ?? 'N/A',
                                'number_plate' => $rental->vehicle->number_plate ?? 'N/A',
                                'type' => $rental->vehicle->type ?? 'N/A',
                            ],
                            'customer' => [
                                'id' => $rental->customer->id ?? null,
                                'name' => $rental->customer->name ?? 'N/A',
                                'phone' => $rental->customer->phone ?? 'N/A',
                                'address' => $rental->customer->address ?? 'N/A',
                            ],
                            'start_time' => $rental->start_time,
                            'end_time' => $rental->end_time,
                            'duration_hours' => $durationHours,
                            'status' => $rental->status,
                            'total_price' => (float) ($rental->total_price ?? 0),
                            'created_at' => $rental->created_at,
                            'updated_at' => $rental->updated_at,
                        ];
                    } catch (Exception $e) {
                        Log::warning('Failed to format rental for report', [
                            'rental_id' => $rental->id,
                            'error' => $e->getMessage(),
                        ]);

                        return [
                            'id' => $rental->id,
                            'error' => 'Failed to load rental details',
                            'status' => $rental->status,
                            'created_at' => $rental->created_at,
                        ];
                    }
                });

                // Add summary statistics
                $summary = [
                    'total_rentals' => $rentals->total(),
                    'total_earnings' => (float) $query->sum('total_price'),
                    'average_rental_value' => $rentals->total() > 0
                        ? round($query->sum('total_price') / $rentals->total(), 2)
                        : 0,
                    'status_breakdown' => $this->getStatusBreakdown($userId, $request),
                ];
            } catch (ValidationException $e) {
                Log::warning('Rentals report validation failed', [
                    'errors' => $e->errors(),
                    'user_id' => $userId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            } catch (QueryException $e) {
                Log::error('Database error generating rentals report', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate rentals report',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'rentals' => $formattedRentals,
                    'summary' => $summary,
                    'pagination' => [
                        'current_page' => $rentals->currentPage(),
                        'last_page' => $rentals->lastPage(),
                        'per_page' => $rentals->perPage(),
                        'total' => $rentals->total(),
                        'from' => $rentals->firstItem(),
                        'to' => $rentals->lastItem(),
                    ],
                    'filters' => [
                        'start_date' => $request->start_date ?? null,
                        'end_date' => $request->end_date ?? null,
                        'date' => $request->date ?? null,
                        'status' => $request->status ?? null,
                        'vehicle_id' => $request->vehicle_id ?? null,
                        'customer_id' => $request->customer_id ?? null,
                        'sort_by' => $sortBy ?? 'created_at',
                        'sort_order' => $sortOrder ?? 'desc',
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Unexpected error generating rentals report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate rentals report',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Generate monthly summary report
     */
    public function summary(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                Log::warning('Unauthenticated access to summary report', [
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            try {
                // Allow custom month/year selection
                $year = $request->get('year', now()->year);
                $month = $request->get('month', now()->month);

                if ($year && $month) {
                    $currentMonthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                    $currentMonthEnd = $currentMonthStart->copy()->endOfMonth();
                } else {
                    $currentMonthStart = now()->startOfMonth();
                    $currentMonthEnd = now()->endOfMonth();
                }

                $previousMonthStart = $currentMonthStart->copy()->subMonth();
                $previousMonthEnd = $previousMonthStart->copy()->endOfMonth();

                // Current month stats
                $currentMonthEarnings = (float) Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->whereBetween('end_time', [$currentMonthStart, $currentMonthEnd])
                    ->sum('total_price');

                $currentMonthRentals = Rental::where('user_id', $userId)
                    ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                    ->count();

                $currentMonthActiveRentals = Rental::where('user_id', $userId)
                    ->where('status', 'active')
                    ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                    ->count();

                // Previous month stats
                $previousMonthEarnings = (float) Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->whereBetween('end_time', [$previousMonthStart, $previousMonthEnd])
                    ->sum('total_price');

                $previousMonthRentals = Rental::where('user_id', $userId)
                    ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
                    ->count();

                // Calculate growth percentages
                $earningsGrowth = $previousMonthEarnings > 0
                    ? (($currentMonthEarnings - $previousMonthEarnings) / $previousMonthEarnings) * 100
                    : ($currentMonthEarnings > 0 ? 100 : 0);

                $rentalsGrowth = $previousMonthRentals > 0
                    ? (($currentMonthRentals - $previousMonthRentals) / $previousMonthRentals) * 100
                    : ($currentMonthRentals > 0 ? 100 : 0);

                // Year to date stats
                $ytdStart = Carbon::createFromDate($year, 1, 1)->startOfDay();
                $ytdEnd = Carbon::createFromDate($year, 12, 31)->endOfDay();

                $ytdEarnings = (float) Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->whereBetween('end_time', [$ytdStart, $ytdEnd])
                    ->sum('total_price');

                $ytdRentals = Rental::where('user_id', $userId)
                    ->whereBetween('created_at', [$ytdStart, $ytdEnd])
                    ->count();
            } catch (QueryException $e) {
                Log::error('Database error generating summary report', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate summary report',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'current_month' => [
                        'earnings' => $currentMonthEarnings,
                        'rentals' => $currentMonthRentals,
                        'active_rentals' => $currentMonthActiveRentals,
                        'period' => [
                            'start' => $currentMonthStart->toDateString(),
                            'end' => $currentMonthEnd->toDateString(),
                            'month' => $currentMonthStart->format('F Y'),
                            'month_number' => $currentMonthStart->month,
                            'year' => $currentMonthStart->year,
                        ],
                    ],
                    'previous_month' => [
                        'earnings' => $previousMonthEarnings,
                        'rentals' => $previousMonthRentals,
                        'period' => [
                            'start' => $previousMonthStart->toDateString(),
                            'end' => $previousMonthEnd->toDateString(),
                            'month' => $previousMonthStart->format('F Y'),
                        ],
                    ],
                    'year_to_date' => [
                        'earnings' => $ytdEarnings,
                        'rentals' => $ytdRentals,
                        'period' => [
                            'year' => $year,
                            'start' => $ytdStart->toDateString(),
                            'end' => $ytdEnd->toDateString(),
                        ],
                    ],
                    'growth' => [
                        'earnings' => round($earningsGrowth, 2),
                        'rentals' => round($rentalsGrowth, 2),
                        'trend' => $earningsGrowth > 0 ? 'up' : ($earningsGrowth < 0 ? 'down' : 'stable'),
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Unexpected error generating summary report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate summary report',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Generate top vehicles report
     */
    public function topVehicles(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                Log::warning('Unauthenticated access to top vehicles report', [
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            try {
                $limit = $request->get('limit', 10);
                $limit = min(max($limit, 1), 50); // Limit between 1 and 50

                $period = $request->get('period', 'all_time'); // all_time, this_month, this_year

                $query = Rental::where('user_id', $userId)
                    ->where('status', 'completed');

                // Apply period filter
                switch ($period) {
                    case 'this_month':
                        $query->whereMonth('end_time', now()->month)
                            ->whereYear('end_time', now()->year);
                        break;
                    case 'this_year':
                        $query->whereYear('end_time', now()->year);
                        break;
                    case 'last_30_days':
                        $query->where('end_time', '>=', now()->subDays(30));
                        break;
                }

                $topVehicles = $query->select(
                    'vehicle_id',
                    DB::raw('COUNT(*) as rental_count'),
                    DB::raw('SUM(total_price) as total_revenue'),
                    DB::raw('AVG(total_price) as average_revenue'),
                    DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count')
                )
                    ->with('vehicle')
                    ->groupBy('vehicle_id')
                    ->orderByRaw('SUM(total_price) DESC')  // ✅ Fixed line
                    ->limit($limit)
                    ->get()
                    ->map(function ($item) {
                        try {
                            return [
                                'vehicle_id' => $item->vehicle_id,
                                'vehicle_name' => $item->vehicle->name ?? 'N/A',
                                'number_plate' => $item->vehicle->number_plate ?? 'N/A',
                                'type' => $item->vehicle->type ?? 'N/A',
                                'hourly_rate' => (float) ($item->vehicle->hourly_rate ?? 0),
                                'daily_rate' => (float) ($item->vehicle->daily_rate ?? 0),
                                'rental_count' => (int) $item->rental_count,
                                'completed_count' => (int) $item->completed_count,
                                'total_revenue' => (float) $item->total_revenue,
                                'average_revenue' => (float) $item->average_revenue,
                                'utilization_rate' => $this->calculateUtilizationRate($item->vehicle, $item->rental_count),
                            ];
                        } catch (Exception $e) {
                            Log::warning('Failed to format top vehicle', [
                                'vehicle_id' => $item->vehicle_id,
                                'error' => $e->getMessage(),
                            ]);

                            return [
                                'vehicle_id' => $item->vehicle_id,
                                'error' => 'Failed to load vehicle details',
                            ];
                        }
                    });

                // Add summary statistics
                $summary = [
                    'total_vehicles' => Vehicle::where('user_id', $userId)->count(),
                    'total_revenue' => (float) $query->sum('total_price'),
                    'total_rentals' => $query->count(),
                    'average_revenue_per_vehicle' => $topVehicles->count() > 0
                        ? round($topVehicles->avg('total_revenue'), 2)
                        : 0,
                ];
            } catch (QueryException $e) {
                Log::error('Database error generating top vehicles report', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate top vehicles report',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'top_vehicles' => $topVehicles,
                    'summary' => $summary,
                    'filters' => [
                        'limit' => $limit,
                        'period' => $period,
                        'period_label' => $this->getPeriodLabel($period),
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Unexpected error generating top vehicles report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate top vehicles report',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Generate customer report
     */
    public function topCustomers(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            try {
                $limit = $request->get('limit', 10);
                $limit = min(max($limit, 1), 50);

                $topCustomers = Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->select(
                        'customer_id',
                        DB::raw('COUNT(*) as rental_count'),
                        DB::raw('SUM(total_price) as total_spent'),
                        DB::raw('AVG(total_price) as average_spent'),
                        DB::raw('MAX(created_at) as last_rental_date')
                    )
                    ->with('customer')
                    ->groupBy('customer_id')
                    ->orderBy('total_spent', 'desc')
                    ->limit($limit)
                    ->get()
                    ->map(function ($item) {
                        try {
                            return [
                                'customer_id' => $item->customer_id,
                                'customer_name' => $item->customer->name ?? 'N/A',
                                'customer_phone' => $item->customer->phone ?? 'N/A',
                                'customer_address' => $item->customer->address ?? 'N/A',
                                'rental_count' => (int) $item->rental_count,
                                'total_spent' => (float) $item->total_spent,
                                'average_spent' => (float) $item->average_spent,
                                'last_rental_date' => $item->last_rental_date,
                                'days_since_last_rental' => Carbon::parse($item->last_rental_date)->diffInDays(now()),
                            ];
                        } catch (Exception $e) {
                            Log::warning('Failed to format top customer', [
                                'customer_id' => $item->customer_id,
                                'error' => $e->getMessage(),
                            ]);

                            return [
                                'customer_id' => $item->customer_id,
                                'error' => 'Failed to load customer details',
                            ];
                        }
                    });

                $summary = [
                    'total_customers' => \App\Models\Customer::count(),
                    'total_revenue' => (float) Rental::where('user_id', $userId)
                        ->where('status', 'completed')
                        ->sum('total_price'),
                    'average_customer_value' => $topCustomers->count() > 0
                        ? round($topCustomers->avg('total_spent'), 2)
                        : 0,
                ];
            } catch (QueryException $e) {
                Log::error('Database error generating top customers report', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate top customers report',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'top_customers' => $topCustomers,
                    'summary' => $summary,
                    'filters' => [
                        'limit' => $limit,
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Unexpected error generating top customers report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate top customers report',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Get document statistics
     */
    /**
     * Get document statistics
     */
    public function documentStatistics(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                Log::warning('Unauthenticated access to document statistics', [
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            try {
                // Get rental document statistics (agreements and receipts)
                $totalRentals = Rental::where('user_id', $userId)->count();

                $rentalsWithAgreement = Rental::where('user_id', $userId)
                    ->whereNotNull('agreement_path')
                    ->where('agreement_path', '!=', '')
                    ->count();

                $rentalsWithReceipt = Rental::where('user_id', $userId)
                    ->whereNotNull('receipt_path')
                    ->where('receipt_path', '!=', '')
                    ->count();

                $rentalsWithBothDocuments = Rental::where('user_id', $userId)
                    ->whereNotNull('agreement_path')
                    ->where('agreement_path', '!=', '')
                    ->whereNotNull('receipt_path')
                    ->where('receipt_path', '!=', '')
                    ->count();

                $agreementRate = $totalRentals > 0
                    ? round(($rentalsWithAgreement / $totalRentals) * 100, 2)
                    : 0;

                $receiptRate = $totalRentals > 0
                    ? round(($rentalsWithReceipt / $totalRentals) * 100, 2)
                    : 0;

                $bothDocumentsRate = $totalRentals > 0
                    ? round(($rentalsWithBothDocuments / $totalRentals) * 100, 2)
                    : 0;

                // Get customer document statistics
                $totalCustomers = \App\Models\Customer::count();

                $customersWithAadhaar = \App\Models\Customer::whereNotNull('aadhaar_number')
                    ->where('aadhaar_number', '!=', '')
                    ->count();

                $customersWithLicense = \App\Models\Customer::whereNotNull('license_number')
                    ->where('license_number', '!=', '')
                    ->count();

                $aadhaarAdoptionRate = $totalCustomers > 0
                    ? round(($customersWithAadhaar / $totalCustomers) * 100, 2)
                    : 0;

                $licenseAdoptionRate = $totalCustomers > 0
                    ? round(($customersWithLicense / $totalCustomers) * 100, 2)
                    : 0;

                return response()->json([
                    'success' => true,
                    'data' => [
                        'rentals' => [
                            'total' => $totalRentals,
                            'with_agreement' => $rentalsWithAgreement,
                            'with_receipt' => $rentalsWithReceipt,
                            'with_both' => $rentalsWithBothDocuments,
                            'without_documents' => $totalRentals - $rentalsWithBothDocuments,
                            'agreement_rate' => $agreementRate,
                            'receipt_rate' => $receiptRate,
                            'both_documents_rate' => $bothDocumentsRate,
                        ],
                        'customers' => [
                            'total' => $totalCustomers,
                            'with_aadhaar' => $customersWithAadhaar,
                            'with_license' => $customersWithLicense,
                            'aadhaar_adoption_rate' => $aadhaarAdoptionRate,
                            'license_adoption_rate' => $licenseAdoptionRate,
                            'both_documents_rate' => $totalCustomers > 0
                                ? round((min($customersWithAadhaar, $customersWithLicense) / $totalCustomers) * 100, 2)
                                : 0,
                        ],
                    ],
                ], 200);
            } catch (QueryException $e) {
                Log::error('Database error generating document statistics', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate document statistics',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred',
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('Unexpected error generating document statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate document statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Export report as CSV
     */
    public function export(Request $request, $type)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $validTypes = ['rentals', 'earnings', 'vehicles', 'customers'];

            if (! in_array($type, $validTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid export type',
                    'errors' => [
                        'type' => ['Type must be one of: '.implode(', ', $validTypes)],
                    ],
                ], 400);
            }

            $data = [];
            $headers = [];
            $filename = "report_{$type}_";

            switch ($type) {
                case 'rentals':
                    $query = Rental::where('user_id', $userId)
                        ->with(['vehicle', 'customer']);

                    // Add status filtering if provided
                    if ($request->has('status') && in_array($request->status, ['active', 'completed', 'cancelled'])) {
                        $query->where('status', $request->status);
                        $filename .= $request->status.'_';
                    }

                    // Add date filtering if provided
                    if ($request->has('start_date') && $request->has('end_date')) {
                        $validated = $request->validate([
                            'start_date' => 'required|date',
                            'end_date' => 'required|date|after_or_equal:start_date',
                        ]);

                        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
                        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

                        $query->whereBetween('created_at', [$startDate, $endDate]);
                        $filename .= $validated['start_date'].'_to_'.$validated['end_date'];
                    } else {
                        $filename .= date('Y-m-d');
                    }

                    $rentals = $query->orderBy('created_at', 'desc')->get();

                    $headers = ['ID', 'Vehicle', 'Vehicle Number', 'Customer', 'Customer Phone', 'Start Time', 'End Time', 'Duration (Hours)', 'Status', 'Total Price', 'Payment Method', 'Created At'];
                    $data = $rentals->map(function ($rental) {
                        // Calculate duration in hours
                        $durationHours = null;
                        if ($rental->start_time && $rental->end_time) {
                            $durationHours = round($rental->start_time->diffInHours($rental->end_time), 2);
                        } elseif ($rental->start_time && $rental->status === 'active') {
                            $durationHours = round($rental->start_time->diffInHours(now()), 2);
                        }

                        return [
                            $rental->id,
                            $rental->vehicle->name ?? 'N/A',
                            $rental->vehicle->number_plate ?? 'N/A',
                            $rental->customer->name ?? 'N/A',
                            $rental->customer->phone ?? 'N/A',
                            $rental->start_time ? Carbon::parse($rental->start_time)->format('Y-m-d H:i:s') : 'N/A',
                            $rental->end_time ? Carbon::parse($rental->end_time)->format('Y-m-d H:i:s') : 'N/A',
                            $durationHours ?? 'N/A',
                            ucfirst($rental->status),
                            number_format((float) ($rental->total_price ?? 0), 2),
                            $rental->payment_method ?? 'N/A',
                            $rental->created_at ? Carbon::parse($rental->created_at)->format('Y-m-d H:i:s') : 'N/A',
                        ];
                    });
                    break;

                case 'earnings':
                    $query = Rental::where('user_id', $userId)
                        ->where('status', 'completed');

                    // Add date filtering if provided
                    if ($request->has('start_date') && $request->has('end_date')) {
                        $validated = $request->validate([
                            'start_date' => 'required|date',
                            'end_date' => 'required|date|after_or_equal:start_date',
                        ]);

                        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
                        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

                        $query->whereBetween('end_time', [$startDate, $endDate]);
                        $filename .= $validated['start_date'].'_to_'.$validated['end_date'];
                    } else {
                        $filename .= date('Y-m-d');
                    }

                    $earnings = $query->select(
                        DB::raw('DATE(end_time) as date'),
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(total_price) as total')
                    )
                        ->groupBy('date')
                        ->orderBy('date', 'desc')
                        ->get();

                    $headers = ['Date', 'Rental Count', 'Total Earnings'];
                    $data = $earnings->map(function ($item) {
                        return [
                            Carbon::parse($item->date)->format('Y-m-d'),
                            (int) $item->count,
                            number_format((float) $item->total, 2),
                        ];
                    });
                    break;

                case 'vehicles':
                    $vehicles = Vehicle::where('user_id', $userId)->get();

                    $headers = ['ID', 'Name', 'Number Plate', 'Type', 'Status', 'Hourly Rate', 'Daily Rate', 'Total Rentals', 'Total Revenue', 'Created At'];
                    $data = $vehicles->map(function ($vehicle) {
                        $totalRentals = $vehicle->rentals()->where('status', 'completed')->count();
                        $totalRevenue = (float) $vehicle->rentals()->where('status', 'completed')->sum('total_price');

                        return [
                            $vehicle->id,
                            $vehicle->name,
                            $vehicle->number_plate,
                            $vehicle->type,
                            ucfirst($vehicle->status),
                            number_format((float) ($vehicle->hourly_rate ?? 0), 2),
                            number_format((float) ($vehicle->daily_rate ?? 0), 2),
                            $totalRentals,
                            number_format($totalRevenue, 2),
                            $vehicle->created_at ? Carbon::parse($vehicle->created_at)->format('Y-m-d H:i:s') : 'N/A',
                        ];
                    });

                    $filename .= date('Y-m-d');
                    break;

                case 'customers':
                    // Note: customers table might not have user_id, so we get customers who have rented from this shop
                    $customers = \App\Models\Customer::whereHas('rentals', function ($query) use ($userId) {
                        $query->where('user_id', $userId);
                    })->with(['rentals' => function ($query) use ($userId) {
                        $query->where('user_id', $userId);
                    }])->get();

                    $headers = ['ID', 'Name', 'Phone', 'Email', 'Address', 'Aadhaar Number', 'License Number', 'Total Rentals', 'Total Spent', 'Last Rental Date', 'First Rental Date', 'Customer Since'];
                    $data = $customers->map(function ($customer) use ($userId) {
                        $customerRentals = $customer->rentals()->where('user_id', $userId);
                        $completedRentals = $customerRentals->where('status', 'completed');
                        $totalSpent = (float) $completedRentals->sum('total_price');
                        $totalRentals = $customerRentals->count();
                        $lastRental = $customerRentals->orderBy('created_at', 'desc')->first();
                        $firstRental = $customerRentals->orderBy('created_at', 'asc')->first();

                        return [
                            $customer->id,
                            $customer->name ?? 'N/A',
                            $customer->phone ?? 'N/A',
                            $customer->email ?? 'N/A',
                            $customer->address ?? 'N/A',
                            $customer->aadhaar_number ?? 'N/A',
                            $customer->license_number ?? 'N/A',
                            $totalRentals,
                            number_format($totalSpent, 2),
                            $lastRental ? Carbon::parse($lastRental->created_at)->format('Y-m-d') : 'N/A',
                            $firstRental ? Carbon::parse($firstRental->created_at)->format('Y-m-d') : 'N/A',
                            $customer->created_at ? Carbon::parse($customer->created_at)->format('Y-m-d') : 'N/A',
                        ];
                    });

                    $filename .= date('Y-m-d');
                    break;
            }

            // Generate CSV with UTF-8 BOM for Excel compatibility
            $csvContent = "\xEF\xBB\xBF".implode(',', $headers)."\n";

            foreach ($data as $row) {
                $escapedRow = array_map(function ($field) {
                    // Convert null to empty string
                    if ($field === null) {
                        return '';
                    }

                    // Convert to string
                    $field = (string) $field;

                    // Escape fields that contain commas, quotes, newlines, or carriage returns
                    if (preg_match('/[,"\n\r]/', $field)) {
                        $field = str_replace('"', '""', $field);

                        return '"'.$field.'"';
                    }

                    return $field;
                }, $row);

                $csvContent .= implode(',', $escapedRow)."\n";
            }

            // Add .csv extension if not present
            if (! str_ends_with($filename, '.csv')) {
                $filename .= '.csv';
            }

            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=UTF-8')
                ->header('Content-Disposition', "attachment; filename={$filename}")
                ->header('Cache-Control', 'private, max-age=0, must-revalidate')
                ->header('Pragma', 'public');
        } catch (ValidationException $e) {
            Log::warning('Export validation failed', [
                'type' => $type,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to export report', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export report',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Get status breakdown for rentals
     */
    protected function getStatusBreakdown($userId, Request $request)
    {
        try {
            $query = Rental::where('user_id', $userId);

            // Apply date filters if present
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay(),
                ]);
            }

            $breakdown = $query->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->status => $item->total];
                });

            return [
                'active' => $breakdown->get('active', 0),
                'completed' => $breakdown->get('completed', 0),
                'cancelled' => $breakdown->get('cancelled', 0),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get status breakdown', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'active' => 0,
                'completed' => 0,
                'cancelled' => 0,
            ];
        }
    }

    /**
     * Calculate vehicle utilization rate
     */
    protected function calculateUtilizationRate($vehicle, $rentalCount)
    {
        try {
            if (! $vehicle || $rentalCount == 0) {
                return 0;
            }

            // Assume 30 days in a month for utilization calculation
            $daysInMonth = 30;
            $maxRentalsPerDay = 2; // Maximum rentals per day (can be configured)
            $maxPossibleRentals = $daysInMonth * $maxRentalsPerDay;

            $utilization = ($rentalCount / $maxPossibleRentals) * 100;

            return round(min($utilization, 100), 2);
        } catch (Exception $e) {
            Log::error('Failed to calculate utilization rate', [
                'vehicle_id' => $vehicle->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get period label for display
     */
    protected function getPeriodLabel($period)
    {
        switch ($period) {
            case 'this_month':
                return 'Current Month';
            case 'this_year':
                return 'Current Year';
            case 'last_30_days':
                return 'Last 30 Days';
            default:
                return 'All Time';
        }
    }

    /**
     * Get verification metrics (admin only)
     */
    public function verificationMetrics(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $metrics = [
                'total_verifications' => Rental::where('user_id', $userId)->whereNotNull('verification_completed_at')->count(),
                'cached_verifications' => Rental::where('user_id', $userId)->where('is_verification_cached', true)->count(),
                'fresh_verifications' => Rental::where('user_id', $userId)->where('is_verification_cached', false)->count(),
                'total_fees_collected' => (float) Rental::where('user_id', $userId)->sum('verification_fee_deducted'),
                'average_verification_time' => $this->getAverageVerificationTimeForUser($userId),
                'verifications_by_day' => $this->getVerificationsByDay($userId),
            ];

            return response()->json(['success' => true, 'data' => $metrics], 200);

        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch verification metrics'], 500);
        }
    }

    /**
     * Get fraud detection report (admin only)
     */
    public function fraudDetectionReport(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $report = [
                'suspicious_rentals' => Rental::where('user_id', $userId)
                    ->where(function ($q) {
                        $q->where('damage_amount', '>', 5000)
                            ->orWhere('status', 'cancelled');
                    })
                    ->with(['vehicle', 'customer'])
                    ->get()
                    ->map(function ($rental) {
                        return [
                            'id' => $rental->id,
                            'type' => $rental->damage_amount > 5000 ? 'high_damage' : 'cancelled',
                            'amount' => $rental->damage_amount,
                            'created_at' => $rental->created_at,
                        ];
                    }),
                'unverified_customers' => Customer::whereDoesntHave('rentals', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })->count(),
                'fraud_score' => $this->calculateFraudScore($userId),
            ];

            return response()->json(['success' => true, 'data' => $report], 200);

        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch fraud report'], 500);
        }
    }

    /**
     * Get customer analytics (admin only)
     */
    public function customerAnalytics(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $analytics = [
                'total_customers' => Customer::count(),
                'new_customers_today' => Customer::whereDate('created_at', today())->count(),
                'new_customers_this_week' => Customer::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'customers_with_rentals' => Customer::has('rentals')->count(),
                'average_rentals_per_customer' => $this->getAverageRentalsPerCustomer(),
                'top_customers_by_rentals' => $this->getTopCustomersByRentals(10),
                'customer_retention_rate' => $this->getRetentionRate(),
            ];

            return response()->json(['success' => true, 'data' => $analytics], 200);

        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch customer analytics'], 500);
        }
    }

    /**
     * Get access logs (admin only)
     */
    public function accessLogs(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $logs = CustomerAccessLog::with(['user', 'customer'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            return response()->json(['success' => true, 'data' => $logs], 200);

        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch access logs'], 500);
        }
    }

    // Helper methods for ReportController

    private function getAverageVerificationTimeForUser($userId): ?float
    {
        $verifications = Rental::where('user_id', $userId)
            ->whereNotNull('verification_completed_at')
            ->get();

        if ($verifications->isEmpty()) {
            return null;
        }

        $totalMinutes = $verifications->sum(function ($rental) {
            return $rental->created_at->diffInMinutes($rental->verification_completed_at);
        });

        return round($totalMinutes / $verifications->count(), 2);
    }

    private function getVerificationsByDay($userId, $days = 30)
    {
        return Rental::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();
    }

    private function calculateFraudScore($userId): float
    {
        $totalRentals = Rental::where('user_id', $userId)->count();
        $suspiciousRentals = Rental::where('user_id', $userId)
            ->where(function ($q) {
                $q->where('damage_amount', '>', 5000)
                    ->orWhere('status', 'cancelled');
            })->count();

        if ($totalRentals === 0) {
            return 0;
        }

        return min(100, round(($suspiciousRentals / $totalRentals) * 100, 2));
    }

    private function getAverageRentalsPerCustomer(): float
    {
        $totalCustomers = Customer::count();
        $totalRentals = Rental::count();

        if ($totalCustomers === 0) {
            return 0;
        }

        return round($totalRentals / $totalCustomers, 2);
    }

    private function getTopCustomersByRentals($limit = 10)
    {
        return Customer::withCount('rentals')
            ->orderBy('rentals_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'rental_count' => $customer->rentals_count,
                ];
            });
    }
}
