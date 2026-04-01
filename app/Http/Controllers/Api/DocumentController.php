<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Exception;

class DocumentController extends Controller
{
    /**
     * Get all documents based on user role
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated access to documents list', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            try {
                // If admin, get all documents
                if ($user->role === 'admin') {
                    $documents = Document::with(['rental.user', 'rental.vehicle', 'rental.customer'])
                        ->orderBy('created_at', 'desc')
                        ->get()
                        ->map(function ($document) {
                            try {
                                return [
                                    'id' => $document->id,
                                    'rental_id' => $document->rental_id,
                                    'user' => [
                                        'id' => $document->rental->user->id ?? null,
                                        'name' => $document->rental->user->name ?? 'N/A',
                                        'phone' => $document->rental->user->phone ?? 'N/A',
                                        'email' => $document->rental->user->email ?? 'N/A'
                                    ],
                                    'vehicle' => [
                                        'id' => $document->rental->vehicle->id ?? null,
                                        'name' => $document->rental->vehicle->name ?? 'N/A',
                                        'number_plate' => $document->rental->vehicle->number_plate ?? 'N/A',
                                        'type' => $document->rental->vehicle->type ?? 'N/A'
                                    ],
                                    'customer' => [
                                        'id' => $document->rental->customer->id ?? null,
                                        'name' => $document->rental->customer->name ?? 'N/A',
                                        'phone' => $document->rental->customer->phone ?? 'N/A',
                                        'address' => $document->rental->customer->address ?? 'N/A'
                                    ],
                                    'aadhaar_image' => $document->aadhaar_image,
                                    'has_aadhaar' => !is_null($document->aadhaar_image),
                                    'license_image' => $document->license_image,
                                    'is_verified' => (bool) $document->is_verified,
                                    'verification_status' => $document->verification_status,
                                    'verified_at' => $document->verified_at,
                                    'created_at' => $document->created_at,
                                    'updated_at' => $document->updated_at,
                                    'extracted_name' => $document->extracted_name,
                                    'extracted_license' => $document->extracted_license,
                                    'extracted_aadhaar' => $document->extracted_aadhaar
                                ];
                            } catch (Exception $e) {
                                Log::warning('Failed to format document data', [
                                    'document_id' => $document->id,
                                    'error' => $e->getMessage()
                                ]);
                                
                                return [
                                    'id' => $document->id,
                                    'rental_id' => $document->rental_id,
                                    'error' => 'Failed to load document details',
                                    'is_verified' => (bool) $document->is_verified,
                                    'has_aadhaar' => !is_null($document->aadhaar_image),
                                    'created_at' => $document->created_at
                                ];
                            }
                        });
                } else {
                    // For regular users, get only their documents
                    $documents = Document::whereHas('rental', function ($query) use ($user) {
                            $query->where('user_id', $user->id);
                        })
                        ->with('rental.vehicle')
                        ->orderBy('created_at', 'desc')
                        ->get()
                        ->map(function ($document) {
                            try {
                                return [
                                    'id' => $document->id,
                                    'rental_id' => $document->rental_id,
                                    'vehicle' => [
                                        'id' => $document->rental->vehicle->id ?? null,
                                        'name' => $document->rental->vehicle->name ?? 'N/A',
                                        'number_plate' => $document->rental->vehicle->number_plate ?? 'N/A',
                                        'type' => $document->rental->vehicle->type ?? 'N/A'
                                    ],
                                    'aadhaar_image' => $document->aadhaar_image,
                                    'has_aadhaar' => !is_null($document->aadhaar_image),
                                    'license_image' => $document->license_image,
                                    'is_verified' => (bool) $document->is_verified,
                                    'verification_status' => $document->verification_status,
                                    'verified_at' => $document->verified_at,
                                    'created_at' => $document->created_at,
                                    'extracted_name' => $document->extracted_name,
                                    'extracted_license' => $document->extracted_license
                                ];
                            } catch (Exception $e) {
                                Log::warning('Failed to format user document data', [
                                    'document_id' => $document->id,
                                    'error' => $e->getMessage()
                                ]);
                                
                                return [
                                    'id' => $document->id,
                                    'rental_id' => $document->rental_id,
                                    'error' => 'Failed to load document details',
                                    'is_verified' => (bool) $document->is_verified,
                                    'has_aadhaar' => !is_null($document->aadhaar_image),
                                    'created_at' => $document->created_at
                                ];
                            }
                        });
                }
            } catch (QueryException $e) {
                Log::error('Database error fetching documents', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'error' => $e->getMessage(),
                    'sql' => method_exists($e, 'getSql') ? $e->getSql() : null
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch documents',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            if ($documents->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => 'No documents found'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $documents,
                'total' => $documents->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching documents', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch documents',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get a specific document
     */
    public function show($id)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated access to document', [
                    'document_id' => $id,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            try {
                $document = $this->findDocument($id);
            } catch (ModelNotFoundException $e) {
                Log::warning('Document not found', [
                    'document_id' => $id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found',
                    'errors' => [
                        'document' => ['Document not found.']
                    ]
                ], 404);
            }
            
            // For regular users, verify ownership
            if ($user->role !== 'admin' && $document->rental->user_id !== $user->id) {
                Log::warning('Unauthorized document access attempt', [
                    'document_id' => $id,
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'owner_id' => $document->rental->user_id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                    'errors' => [
                        'document' => ['Document not found or access denied']
                    ]
                ], 403);
            }

            $responseData = [
                'id' => $document->id,
                'rental_id' => $document->rental_id,
                'aadhaar_image' => $document->aadhaar_image,
                'has_aadhaar' => !is_null($document->aadhaar_image),
                'license_image' => $document->license_image,
                'is_verified' => (bool) $document->is_verified,
                'verification_status' => $document->verification_status,
                'verified_at' => $document->verified_at,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at,
                'extracted_name' => $document->extracted_name,
                'extracted_license' => $document->extracted_license,
                'extracted_aadhaar' => $document->extracted_aadhaar,
                'rental_details' => [
                    'vehicle_name' => $document->rental->vehicle->name ?? 'N/A',
                    'customer_name' => $document->rental->customer->name ?? 'N/A',
                    'start_time' => $document->rental->start_time,
                    'end_time' => $document->rental->end_time
                ]
            ];

            // Add OCR data if available
            if ($document->license_ocr_data) {
                $responseData['license_ocr_data'] = json_decode($document->license_ocr_data, true);
            }
            
            if ($document->aadhaar_ocr_data) {
                $responseData['aadhaar_ocr_data'] = json_decode($document->aadhaar_ocr_data, true);
            }

            return response()->json([
                'success' => true,
                'data' => $responseData
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching document', [
                'document_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch document',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Download a document file
     */
    public function download($id, $type)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated document download attempt', [
                    'document_id' => $id,
                    'type' => $type,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            try {
                $document = $this->findDocument($id);
            } catch (ModelNotFoundException $e) {
                Log::warning('Document not found for download', [
                    'document_id' => $id,
                    'type' => $type,
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found',
                    'errors' => [
                        'document' => ['Document not found.']
                    ]
                ], 404);
            }
            
            // For regular users, verify ownership
            if ($user->role !== 'admin' && $document->rental->user_id !== $user->id) {
                Log::warning('Unauthorized document download attempt', [
                    'document_id' => $id,
                    'type' => $type,
                    'user_id' => $user->id,
                    'owner_id' => $document->rental->user_id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                    'errors' => [
                        'document' => ['You do not have permission to download this document']
                    ]
                ], 403);
            }

            if (!in_array($type, ['aadhaar', 'license'])) {
                Log::warning('Invalid document type requested', [
                    'document_id' => $id,
                    'type' => $type,
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid document type',
                    'errors' => [
                        'type' => ['Invalid document type. Must be aadhaar or license']
                    ]
                ], 400);
            }

            $path = $type === 'aadhaar' 
                ? $document->aadhaar_image 
                : $document->license_image;

            if (!$path) {
                $errorMessage = $type === 'aadhaar' 
                    ? 'Aadhaar document not provided for this rental' 
                    : 'License document not found';
                    
                Log::warning('Document path is null', [
                    'document_id' => $id,
                    'type' => $type,
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => [
                        'document' => [$errorMessage]
                    ]
                ], 404);
            }
            
            try {
                if (!Storage::disk('public')->exists($path)) {
                    Log::error('Document file does not exist in storage', [
                        'document_id' => $id,
                        'type' => $type,
                        'path' => $path,
                        'user_id' => $user->id
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Document file not found',
                        'errors' => [
                            'document' => ['The document file does not exist on the server.']
                        ]
                    ], 404);
                }
            } catch (Exception $e) {
                Log::error('Error checking file existence', [
                    'document_id' => $id,
                    'path' => $path,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to access document file',
                    'error' => config('app.debug') ? $e->getMessage() : 'Storage error occurred'
                ], 500);
            }

            // Get original filename
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $originalName = $type . '_' . $document->rental_id . '_' . date('Y-m-d') . '.' . ($extension ?: 'jpg');
            
            Log::info('Document downloaded', [
                'document_id' => $id,
                'type' => $type,
                'user_id' => $user->id,
                'user_role' => $user->role
            ]);
            
            try {
                return Storage::disk('public')->download($path, $originalName);
            } catch (Exception $e) {
                Log::error('Failed to download file', [
                    'document_id' => $id,
                    'path' => $path,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to download document',
                    'error' => config('app.debug') ? $e->getMessage() : 'Download failed'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Unexpected error downloading document', [
                'document_id' => $id,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to download document',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Verify a document (admin only)
     */
    public function verify($id)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated document verification attempt', [
                    'document_id' => $id,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            // Only admin can verify documents
            if ($user->role !== 'admin') {
                Log::warning('Non-admin user attempted to verify document', [
                    'document_id' => $id,
                    'user_id' => $user->id,
                    'user_role' => $user->role
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                    'errors' => [
                        'role' => ['Only administrators can verify documents']
                    ]
                ], 403);
            }
            
            try {
                $document = $this->findDocument($id);
            } catch (ModelNotFoundException $e) {
                Log::warning('Document not found for verification', [
                    'document_id' => $id,
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found',
                    'errors' => [
                        'document' => ['Document not found.']
                    ]
                ], 404);
            }
            
            if ($document->is_verified) {
                Log::info('Document already verified', [
                    'document_id' => $id,
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Document already verified',
                    'data' => [
                        'is_verified' => true,
                        'verified_at' => $document->verified_at
                    ]
                ], 400);
            }
            
            try {
                $document->update([
                    'is_verified' => true,
                    'verification_status' => 'verified',
                    'verified_at' => now()
                ]);
            } catch (QueryException $e) {
                Log::error('Database error verifying document', [
                    'document_id' => $id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to verify document',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }
            
            Log::info('Document verified successfully', [
                'document_id' => $id,
                'admin_id' => $user->id,
                'rental_id' => $document->rental_id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Document verified successfully',
                'data' => [
                    'id' => $document->id,
                    'rental_id' => $document->rental_id,
                    'is_verified' => true,
                    'verification_status' => 'verified',
                    'verified_at' => $document->verified_at
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error verifying document', [
                'document_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify document',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Delete a document
     */
    public function destroy($id)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated document deletion attempt', [
                    'document_id' => $id,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            try {
                $document = $this->findDocument($id);
            } catch (ModelNotFoundException $e) {
                Log::warning('Document not found for deletion', [
                    'document_id' => $id,
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found',
                    'errors' => [
                        'document' => ['Document not found.']
                    ]
                ], 404);
            }
            
            // Only admin or document owner can delete
            if ($user->role !== 'admin' && $document->rental->user_id !== $user->id) {
                Log::warning('Unauthorized document deletion attempt', [
                    'document_id' => $id,
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'owner_id' => $document->rental->user_id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                    'errors' => [
                        'document' => ['You do not have permission to delete this document']
                    ]
                ], 403);
            }
            
            // Delete files from storage
            $deletedFiles = [];
            
            if ($document->aadhaar_image) {
                try {
                    if (Storage::disk('public')->exists($document->aadhaar_image)) {
                        Storage::disk('public')->delete($document->aadhaar_image);
                        $deletedFiles[] = 'aadhaar';
                    }
                } catch (Exception $e) {
                    Log::error('Failed to delete aadhaar image', [
                        'document_id' => $id,
                        'path' => $document->aadhaar_image,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            if ($document->license_image) {
                try {
                    if (Storage::disk('public')->exists($document->license_image)) {
                        Storage::disk('public')->delete($document->license_image);
                        $deletedFiles[] = 'license';
                    }
                } catch (Exception $e) {
                    Log::error('Failed to delete license image', [
                        'document_id' => $id,
                        'path' => $document->license_image,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            try {
                $document->delete();
            } catch (QueryException $e) {
                Log::error('Database error deleting document', [
                    'document_id' => $id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete document',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }
            
            Log::info('Document deleted successfully', [
                'document_id' => $id,
                'user_id' => $user->id,
                'user_role' => $user->role,
                'deleted_files' => $deletedFiles
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
                'data' => [
                    'deleted_files' => $deletedFiles
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error deleting document', [
                'document_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get unverified documents (admin only)
     */
    public function unverified(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                    'errors' => [
                        'role' => ['Only administrators can view unverified documents']
                    ]
                ], 403);
            }
            
            try {
                $documents = Document::with(['rental.user', 'rental.vehicle', 'rental.customer'])
                    ->where('is_verified', false)
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(function ($document) {
                        return [
                            'id' => $document->id,
                            'rental_id' => $document->rental_id,
                            'user' => [
                                'id' => $document->rental->user->id ?? null,
                                'name' => $document->rental->user->name ?? 'N/A',
                                'phone' => $document->rental->user->phone ?? 'N/A'
                            ],
                            'vehicle' => [
                                'id' => $document->rental->vehicle->id ?? null,
                                'name' => $document->rental->vehicle->name ?? 'N/A',
                                'number_plate' => $document->rental->vehicle->number_plate ?? 'N/A'
                            ],
                            'customer' => [
                                'id' => $document->rental->customer->id ?? null,
                                'name' => $document->rental->customer->name ?? 'N/A',
                                'phone' => $document->rental->customer->phone ?? 'N/A'
                            ],
                            'has_aadhaar' => !is_null($document->aadhaar_image),
                            'has_license' => !is_null($document->license_image),
                            'created_at' => $document->created_at,
                            'waiting_days' => $document->created_at->diffInDays(now())
                        ];
                    });
            } catch (QueryException $e) {
                Log::error('Database error fetching unverified documents', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch unverified documents',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'data' => $documents,
                'total' => $documents->count()
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Unexpected error fetching unverified documents', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unverified documents',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Bulk verify documents (admin only)
     */
    public function bulkVerify(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }
            
            $validated = $request->validate([
                'document_ids' => 'required|array',
                'document_ids.*' => 'exists:documents,id'
            ]);
            
            DB::beginTransaction();
            
            try {
                $updatedCount = Document::whereIn('id', $validated['document_ids'])
                    ->where('is_verified', false)
                    ->update([
                        'is_verified' => true,
                        'verification_status' => 'verified',
                        'verified_at' => now()
                    ]);
                    
                DB::commit();
            } catch (QueryException $e) {
                DB::rollBack();
                
                Log::error('Database error bulk verifying documents', [
                    'user_id' => $user->id,
                    'document_ids' => $validated['document_ids'],
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to verify documents',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }
            
            Log::info('Bulk document verification completed', [
                'admin_id' => $user->id,
                'verified_count' => $updatedCount,
                'total_requested' => count($validated['document_ids'])
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} documents verified successfully",
                'data' => [
                    'verified_count' => $updatedCount,
                    'total_requested' => count($validated['document_ids'])
                ]
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Unexpected error bulk verifying documents', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify documents',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Find a document by ID
     */
    protected function findDocument($id)
    {
        $document = Document::with(['rental.user', 'rental.vehicle', 'rental.customer'])->find($id);

        if (!$document) {
            throw new ModelNotFoundException('Document not found');
        }

        return $document;
    }
}