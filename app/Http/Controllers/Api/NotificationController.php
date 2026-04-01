<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class NotificationController extends Controller
{
    /**
     * Get all notifications for authenticated user
     */
    public function index(Request $request)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                Log::warning('Unauthenticated access to notifications', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $notifications = Notification::where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->paginate($request->get('per_page', 20))
                    ->through(function ($notification) {
                        try {
                            return [
                                'id' => $notification->id,
                                'title' => $notification->title,
                                'message' => $notification->message,
                                'type' => $notification->type,
                                'data' => $notification->data,
                                'is_read' => (bool) $notification->is_read,
                                'read_at' => $notification->read_at,
                                'created_at' => $notification->created_at,
                                'created_at_human' => $notification->created_at->diffForHumans(),
                                'updated_at' => $notification->updated_at
                            ];
                        } catch (Exception $e) {
                            Log::warning('Failed to format notification', [
                                'notification_id' => $notification->id,
                                'error' => $e->getMessage()
                            ]);
                            
                            return [
                                'id' => $notification->id,
                                'title' => $notification->title,
                                'message' => $notification->message,
                                'is_read' => (bool) $notification->is_read,
                                'created_at' => $notification->created_at,
                                'error' => 'Failed to load full details'
                            ];
                        }
                    });

                $unreadCount = Notification::where('user_id', $userId)
                    ->where('is_read', false)
                    ->count();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'notifications' => $notifications->items(),
                        'pagination' => [
                            'current_page' => $notifications->currentPage(),
                            'last_page' => $notifications->lastPage(),
                            'per_page' => $notifications->perPage(),
                            'total' => $notifications->total(),
                            'from' => $notifications->firstItem(),
                            'to' => $notifications->lastItem()
                        ],
                        'unread_count' => $unreadCount,
                        'total_count' => $notifications->total()
                    ]
                ], 200);

            } catch (QueryException $e) {
                Log::error('Database error fetching notifications', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'sql' => method_exists($e, 'getSql') ? $e->getSql() : null
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch notifications',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Unexpected error fetching notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get a specific notification
     */
    public function show($id)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                Log::warning('Unauthenticated access to notification', [
                    'notification_id' => $id,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $notification = $this->findUserNotification($id, $userId);
            } catch (ModelNotFoundException $e) {
                Log::warning('Notification not found', [
                    'notification_id' => $id,
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                    'errors' => [
                        'notification' => ['Notification not found or access denied.']
                    ]
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'is_read' => (bool) $notification->is_read,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'created_at_human' => $notification->created_at->diffForHumans(),
                    'updated_at' => $notification->updated_at
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching notification', [
                'notification_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead($id)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                Log::warning('Unauthenticated notification read attempt', [
                    'notification_id' => $id,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $notification = $this->findUserNotification($id, $userId);
            } catch (ModelNotFoundException $e) {
                Log::warning('Notification not found for read marking', [
                    'notification_id' => $id,
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                    'errors' => [
                        'notification' => ['Notification not found or access denied.']
                    ]
                ], 404);
            }

            if ($notification->is_read) {
                Log::info('Notification already read', [
                    'notification_id' => $id,
                    'user_id' => $userId
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Notification already marked as read',
                    'data' => [
                        'id' => $notification->id,
                        'is_read' => true,
                        'read_at' => $notification->read_at
                    ]
                ], 400);
            }

            try {
                $notification->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);
            } catch (QueryException $e) {
                Log::error('Database error marking notification as read', [
                    'notification_id' => $id,
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to mark notification as read',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            Log::info('Notification marked as read', [
                'notification_id' => $id,
                'user_id' => $userId,
                'ip' => request()->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => [
                    'id' => $notification->id,
                    'is_read' => true,
                    'read_at' => $notification->read_at
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error marking notification as read', [
                'notification_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                Log::warning('Unauthenticated mark all read attempt', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $count = Notification::where('user_id', $userId)
                    ->where('is_read', false)
                    ->count();

                if ($count === 0) {
                    Log::info('No unread notifications found for user', [
                        'user_id' => $userId
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'No unread notifications found',
                        'data' => [
                            'marked_count' => 0
                        ]
                    ], 400);
                }

                DB::beginTransaction();
                
                $updated = Notification::where('user_id', $userId)
                    ->where('is_read', false)
                    ->update([
                        'is_read' => true,
                        'read_at' => now()
                    ]);
                    
                DB::commit();
                
            } catch (QueryException $e) {
                DB::rollBack();
                
                Log::error('Database error marking all notifications as read', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to mark notifications as read',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            Log::info('All notifications marked as read', [
                'user_id' => $userId,
                'marked_count' => $updated,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'data' => [
                    'marked_count' => $updated
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error marking all notifications as read', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notifications as read',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy($id)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                Log::warning('Unauthenticated notification deletion attempt', [
                    'notification_id' => $id,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $notification = $this->findUserNotification($id, $userId);
            } catch (ModelNotFoundException $e) {
                Log::warning('Notification not found for deletion', [
                    'notification_id' => $id,
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                    'errors' => [
                        'notification' => ['Notification not found or access denied.']
                    ]
                ], 404);
            }

            try {
                $notification->delete();
            } catch (QueryException $e) {
                Log::error('Database error deleting notification', [
                    'notification_id' => $id,
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete notification',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            Log::info('Notification deleted', [
                'notification_id' => $id,
                'user_id' => $userId,
                'ip' => request()->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error deleting notification', [
                'notification_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Delete all notifications for authenticated user
     */
    public function destroyAll(Request $request)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                Log::warning('Unauthenticated delete all notifications attempt', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $count = Notification::where('user_id', $userId)->count();

                if ($count === 0) {
                    Log::info('No notifications found for deletion', [
                        'user_id' => $userId
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'No notifications found to delete',
                        'data' => [
                            'deleted_count' => 0
                        ]
                    ], 400);
                }

                DB::beginTransaction();
                
                $deleted = Notification::where('user_id', $userId)->delete();
                
                DB::commit();
                
            } catch (QueryException $e) {
                DB::rollBack();
                
                Log::error('Database error deleting all notifications', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete notifications',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            Log::info('All notifications deleted', [
                'user_id' => $userId,
                'deleted_count' => $deleted,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications deleted successfully',
                'data' => [
                    'deleted_count' => $deleted
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error deleting all notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get unread notifications count
     */
    public function unreadCount(Request $request)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                Log::warning('Unauthenticated access to unread count', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $unreadCount = Notification::where('user_id', $userId)
                    ->where('is_read', false)
                    ->count();
            } catch (QueryException $e) {
                Log::error('Database error fetching unread count', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch unread count',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $unreadCount
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching unread count', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unread count',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get notifications by type
     */
    public function getByType(Request $request, $type)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validTypes = ['info', 'warning', 'success', 'error', 'rental', 'payment', 'system'];
            
            if (!in_array($type, $validTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid notification type',
                    'errors' => [
                        'type' => ['Type must be one of: ' . implode(', ', $validTypes)]
                    ]
                ], 400);
            }

            try {
                $notifications = Notification::where('user_id', $userId)
                    ->where('type', $type)
                    ->orderBy('created_at', 'desc')
                    ->paginate($request->get('per_page', 20))
                    ->through(function ($notification) {
                        return [
                            'id' => $notification->id,
                            'title' => $notification->title,
                            'message' => $notification->message,
                            'is_read' => (bool) $notification->is_read,
                            'created_at' => $notification->created_at,
                            'created_at_human' => $notification->created_at->diffForHumans()
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'data' => [
                        'notifications' => $notifications->items(),
                        'pagination' => [
                            'current_page' => $notifications->currentPage(),
                            'last_page' => $notifications->lastPage(),
                            'per_page' => $notifications->perPage(),
                            'total' => $notifications->total()
                        ],
                        'type' => $type
                    ]
                ], 200);

            } catch (QueryException $e) {
                Log::error('Database error fetching notifications by type', [
                    'user_id' => $userId,
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch notifications',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Unexpected error fetching notifications by type', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Delete read notifications (older than specified days)
     */
    public function deleteRead(Request $request)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $days = $request->get('days', 30);
            $days = min(max($days, 1), 365); // Limit between 1 and 365 days

            try {
                $deleted = Notification::where('user_id', $userId)
                    ->where('is_read', true)
                    ->where('created_at', '<', now()->subDays($days))
                    ->delete();

                Log::info('Deleted old read notifications', [
                    'user_id' => $userId,
                    'days' => $days,
                    'deleted_count' => $deleted
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Deleted {$deleted} old read notifications",
                    'data' => [
                        'deleted_count' => $deleted,
                        'older_than_days' => $days
                    ]
                ], 200);

            } catch (QueryException $e) {
                Log::error('Database error deleting old read notifications', [
                    'user_id' => $userId,
                    'days' => $days,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete old notifications',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Unexpected error deleting old read notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete old notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function statistics()
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
                    'total' => Notification::where('user_id', $userId)->count(),
                    'unread' => Notification::where('user_id', $userId)->where('is_read', false)->count(),
                    'read' => Notification::where('user_id', $userId)->where('is_read', true)->count(),
                    'by_type' => Notification::where('user_id', $userId)
                        ->select('type', DB::raw('count(*) as total'))
                        ->groupBy('type')
                        ->get()
                        ->mapWithKeys(function ($item) {
                            return [$item->type => $item->total];
                        }),
                    'last_7_days' => Notification::where('user_id', $userId)
                        ->where('created_at', '>=', now()->subDays(7))
                        ->count(),
                    'last_30_days' => Notification::where('user_id', $userId)
                        ->where('created_at', '>=', now()->subDays(30))
                        ->count()
                ];
            } catch (QueryException $e) {
                Log::error('Database error fetching notification statistics', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch statistics',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching notification statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Find a notification belonging to the authenticated user
     */
    protected function findUserNotification($id, $userId = null)
    {
        $userId = $userId ?? auth()->id();
        
        $notification = Notification::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            throw new ModelNotFoundException('Notification not found or access denied');
        }

        return $notification;
    }
}