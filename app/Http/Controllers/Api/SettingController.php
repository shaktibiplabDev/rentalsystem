<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class SettingController extends Controller
{
    /**
     * Get all settings for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated access to settings', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $settings = $user->settings()
                    ->orderBy('key', 'asc')
                    ->get()
                    ->mapWithKeys(function ($setting) {
                        try {
                            return [$setting->key => $this->castValue($setting->value, $setting->type)];
                        } catch (Exception $e) {
                            Log::warning('Failed to cast setting value', [
                                'setting_id' => $setting->id,
                                'key' => $setting->key,
                                'error' => $e->getMessage()
                            ]);
                            return [$setting->key => $setting->value];
                        }
                    });
            } catch (QueryException $e) {
                Log::error('Database error fetching settings', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'sql' => method_exists($e, 'getSql') ? $e->getSql() : null
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch settings',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            if ($settings->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => 'No settings found'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $settings,
                'total' => $settings->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Update multiple settings
     */
    public function update(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated settings update attempt', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'settings' => 'required|array|min:1',
                'settings.*.key' => 'required|string|max:255',
                'settings.*.value' => 'nullable',
                'settings.*.type' => 'sometimes|in:string,integer,boolean,float,json'
            ]);

            $updatedSettings = [];
            $errors = [];
            $skipped = [];

            DB::beginTransaction();
            
            try {
                foreach ($validated['settings'] as $index => $settingData) {
                    try {
                        // Validate key format
                        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $settingData['key'])) {
                            throw new Exception('Invalid key format. Only alphanumeric, underscore, hyphen, and dot allowed.');
                        }
                        
                        $type = $settingData['type'] ?? $this->detectType($settingData['value']);
                        
                        // Validate value based on type
                        $this->validateValueByType($settingData['value'], $type);
                        
                        $value = $this->prepareValue($settingData['value'], $type);
                        
                        $setting = $user->settings()->updateOrCreate(
                            ['key' => $settingData['key']],
                            [
                                'value' => $value,
                                'type' => $type
                            ]
                        );
                        
                        $updatedSettings[$setting->key] = $this->castValue($setting->value, $setting->type);
                        
                    } catch (ValidationException $e) {
                        $errors[] = [
                            'index' => $index,
                            'key' => $settingData['key'],
                            'error' => $e->errors()
                        ];
                        Log::warning('Validation error for setting', [
                            'user_id' => $user->id,
                            'key' => $settingData['key'],
                            'error' => $e->getMessage()
                        ]);
                    } catch (Exception $e) {
                        $errors[] = [
                            'index' => $index,
                            'key' => $settingData['key'],
                            'error' => $e->getMessage()
                        ];
                        Log::error('Update setting error for key ' . $settingData['key'], [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                if (empty($errors)) {
                    DB::commit();
                } else {
                    DB::rollBack();
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to update settings due to validation errors',
                        'errors' => $errors,
                        'updated_count' => 0,
                        'failed_count' => count($errors)
                    ], 422);
                }
                
            } catch (QueryException $e) {
                DB::rollBack();
                
                Log::error('Database error updating settings', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update settings',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            Log::info('Settings updated successfully', [
                'user_id' => $user->id,
                'updated_count' => count($updatedSettings),
                'keys' => array_keys($updatedSettings)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $updatedSettings,
                'updated_count' => count($updatedSettings)
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Settings update validation failed', [
                'errors' => $e->errors(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (Exception $e) {
            Log::error('Unexpected error updating settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get a specific setting by key
     */
    public function show($key)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated access to setting', [
                    'key' => $key,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $setting = $user->settings()
                    ->where('key', $key)
                    ->first();

                if (!$setting) {
                    Log::warning('Setting not found', [
                        'user_id' => $user->id,
                        'key' => $key
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Setting not found',
                        'errors' => [
                            'key' => ['Setting with key "' . $key . '" not found']
                        ]
                    ], 404);
                }
            } catch (QueryException $e) {
                Log::error('Database error fetching setting', [
                    'user_id' => $user->id,
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch setting',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'key' => $setting->key,
                    'value' => $this->castValue($setting->value, $setting->type),
                    'type' => $setting->type,
                    'created_at' => $setting->created_at,
                    'updated_at' => $setting->updated_at
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching setting', [
                'key' => $key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch setting',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Update a single setting
     */
    public function updateSingle(Request $request, $key)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'value' => 'nullable',
                'type' => 'sometimes|in:string,integer,boolean,float,json'
            ]);

            $type = $validated['type'] ?? $this->detectType($validated['value']);
            
            // Validate value based on type
            $this->validateValueByType($validated['value'], $type);
            
            $value = $this->prepareValue($validated['value'], $type);
            
            try {
                $setting = $user->settings()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'type' => $type
                    ]
                );
            } catch (QueryException $e) {
                Log::error('Database error updating single setting', [
                    'user_id' => $user->id,
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update setting',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            Log::info('Single setting updated', [
                'user_id' => $user->id,
                'key' => $key,
                'type' => $type
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully',
                'data' => [
                    'key' => $setting->key,
                    'value' => $this->castValue($setting->value, $setting->type),
                    'type' => $setting->type
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Unexpected error updating single setting', [
                'key' => $key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Delete a setting
     */
    public function destroy($key)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated setting deletion attempt', [
                    'key' => $key,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $setting = $user->settings()
                    ->where('key', $key)
                    ->first();

                if (!$setting) {
                    Log::warning('Setting not found for deletion', [
                        'user_id' => $user->id,
                        'key' => $key
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Setting not found',
                        'errors' => [
                            'key' => ['Setting with key "' . $key . '" not found']
                        ]
                    ], 404);
                }

                $setting->delete();
            } catch (QueryException $e) {
                Log::error('Database error deleting setting', [
                    'user_id' => $user->id,
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete setting',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            Log::info('Setting deleted', [
                'user_id' => $user->id,
                'key' => $key
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting deleted successfully'
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error deleting setting', [
                'key' => $key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete setting',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Delete multiple settings
     */
    public function bulkDelete(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated bulk delete attempt', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'keys' => 'required|array|min:1',
                'keys.*' => 'required|string|max:255'
            ]);

            try {
                $deleted = $user->settings()
                    ->whereIn('key', $validated['keys'])
                    ->delete();

                if ($deleted === 0) {
                    Log::warning('No settings found for bulk deletion', [
                        'user_id' => $user->id,
                        'keys' => $validated['keys']
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'No settings found to delete',
                        'data' => [
                            'deleted_count' => 0,
                            'requested_keys' => $validated['keys']
                        ]
                    ], 404);
                }
            } catch (QueryException $e) {
                Log::error('Database error in bulk delete', [
                    'user_id' => $user->id,
                    'keys' => $validated['keys'],
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete settings',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            Log::info('Bulk settings deletion completed', [
                'user_id' => $user->id,
                'deleted_count' => $deleted,
                'keys' => $validated['keys']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Settings deleted successfully',
                'data' => [
                    'deleted_count' => $deleted,
                    'requested_count' => count($validated['keys'])
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Bulk delete validation failed', [
                'errors' => $e->errors(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (Exception $e) {
            Log::error('Unexpected error in bulk delete', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get settings by type
     */
    public function getByType($type)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validTypes = ['string', 'integer', 'boolean', 'float', 'json'];
            
            if (!in_array($type, $validTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid type',
                    'errors' => [
                        'type' => ['Type must be one of: ' . implode(', ', $validTypes)]
                    ]
                ], 400);
            }

            try {
                $settings = $user->settings()
                    ->where('type', $type)
                    ->orderBy('key', 'asc')
                    ->get()
                    ->mapWithKeys(function ($setting) {
                        return [$setting->key => $this->castValue($setting->value, $setting->type)];
                    });
            } catch (QueryException $e) {
                Log::error('Database error fetching settings by type', [
                    'user_id' => $user->id,
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch settings',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $settings,
                'type' => $type,
                'count' => $settings->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching settings by type', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get default settings (templates)
     */
    public function getDefaults()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Define default settings for different user roles
            $defaultSettings = [
                'user' => [
                    'notifications_enabled' => ['value' => true, 'type' => 'boolean'],
                    'email_notifications' => ['value' => true, 'type' => 'boolean'],
                    'sms_notifications' => ['value' => false, 'type' => 'boolean'],
                    'language' => ['value' => 'en', 'type' => 'string'],
                    'timezone' => ['value' => 'UTC', 'type' => 'string'],
                    'items_per_page' => ['value' => 15, 'type' => 'integer'],
                    'date_format' => ['value' => 'Y-m-d', 'type' => 'string'],
                    'time_format' => ['value' => 'H:i', 'type' => 'string']
                ],
                'admin' => [
                    'admin_notifications' => ['value' => true, 'type' => 'boolean'],
                    'auto_verify_documents' => ['value' => false, 'type' => 'boolean'],
                    'maintenance_mode' => ['value' => false, 'type' => 'boolean'],
                    'default_verification_price' => ['value' => 50, 'type' => 'float'],
                    'default_lease_threshold' => ['value' => 60, 'type' => 'integer']
                ]
            ];

            $role = $user->role ?? 'user';
            $settings = $defaultSettings[$role] ?? $defaultSettings['user'];

            return response()->json([
                'success' => true,
                'data' => $settings,
                'role' => $role,
                'message' => 'Default settings templates'
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching default settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch default settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Reset settings to defaults
     */
    public function reset(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'keys' => 'sometimes|array',
                'keys.*' => 'string|max:255',
                'all' => 'sometimes|boolean'
            ]);

            try {
                if (isset($validated['all']) && $validated['all']) {
                    // Delete all settings
                    $deleted = $user->settings()->delete();
                    $message = "All settings have been reset to defaults";
                } elseif (isset($validated['keys']) && !empty($validated['keys'])) {
                    // Delete specific settings
                    $deleted = $user->settings()
                        ->whereIn('key', $validated['keys'])
                        ->delete();
                    $message = count($validated['keys']) . " settings have been reset to defaults";
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please specify keys to reset or use all=true'
                    ], 422);
                }
            } catch (QueryException $e) {
                Log::error('Database error resetting settings', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reset settings',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            Log::info('Settings reset', [
                'user_id' => $user->id,
                'deleted_count' => $deleted
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'reset_count' => $deleted
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Unexpected error resetting settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Validate value based on type
     */
    protected function validateValueByType($value, string $type): void
    {
        switch ($type) {
            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false', true, false], true)) {
                    throw new ValidationException(
                        validator([], ['Invalid boolean value'])
                    );
                }
                break;
                
            case 'integer':
                if (!is_numeric($value) || (int) $value != $value) {
                    throw new ValidationException(
                        validator([], ['Invalid integer value'])
                    );
                }
                break;
                
            case 'float':
                if (!is_numeric($value)) {
                    throw new ValidationException(
                        validator([], ['Invalid float value'])
                    );
                }
                break;
                
            case 'json':
                if (is_string($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new ValidationException(
                            validator([], ['Invalid JSON string'])
                        );
                    }
                } elseif (!is_array($value)) {
                    throw new ValidationException(
                        validator([], ['Value must be array or valid JSON string'])
                    );
                }
                break;
        }
    }

    /**
     * Detect the type of a value
     */
    protected function detectType($value): string
    {
        if (is_bool($value)) return 'boolean';
        if (is_int($value)) return 'integer';
        if (is_float($value)) return 'float';
        if (is_array($value)) return 'json';
        
        // Check if string is JSON
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return 'json';
            }
        }
        
        return 'string';
    }

    /**
     * Prepare value for storage
     */
    protected function prepareValue($value, string $type): string
    {
        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }
        
        if ($type === 'json') {
            if (is_array($value)) {
                return json_encode($value);
            }
            if (is_string($value)) {
                // Validate it's valid JSON
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $value;
                }
            }
            return json_encode(['value' => $value]);
        }
        
        if ($type === 'integer') {
            return (string) (int) $value;
        }
        
        if ($type === 'float') {
            return (string) (float) $value;
        }
        
        return (string) $value;
    }

    /**
     * Cast stored value to appropriate type
     */
    protected function castValue(string $value, string $type)
    {
        try {
            return match ($type) {
                'boolean' => (bool) $value,
                'integer' => (int) $value,
                'float' => (float) $value,
                'json' => json_decode($value, true) ?? $value,
                default => $value,
            };
        } catch (Exception $e) {
            Log::error('Failed to cast value', [
                'value' => $value,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return $value;
        }
    }
}