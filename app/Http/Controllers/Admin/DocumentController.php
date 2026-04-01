<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function index()
    {
        $documents = Document::with(['rental.user', 'rental.vehicle', 'rental.customer'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($document) {
                return [
                    'id' => $document->id,
                    'rental_id' => $document->rental_id,
                    'user' => [
                        'id' => $document->rental->user->id,
                        'name' => $document->rental->user->name,
                        'email' => $document->rental->user->email
                    ],
                    'vehicle' => [
                        'id' => $document->rental->vehicle->id,
                        'name' => $document->rental->vehicle->name,
                        'number_plate' => $document->rental->vehicle->number_plate
                    ],
                    'customer' => [
                        'id' => $document->rental->customer->id,
                        'name' => $document->rental->customer->name,
                        'phone' => $document->rental->customer->phone
                    ],
                    'aadhaar_image' => $document->aadhaar_image,
                    'license_image' => $document->license_image,
                    'is_verified' => $document->is_verified,
                    'created_at' => $document->created_at
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $documents
        ]);
    }

    public function show($id)
    {
        $document = Document::with(['rental.user', 'rental.vehicle', 'rental.customer'])
            ->find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $document->id,
                'rental_id' => $document->rental_id,
                'user' => [
                    'id' => $document->rental->user->id,
                    'name' => $document->rental->user->name,
                    'email' => $document->rental->user->email
                ],
                'vehicle' => [
                    'id' => $document->rental->vehicle->id,
                    'name' => $document->rental->vehicle->name,
                    'number_plate' => $document->rental->vehicle->number_plate
                ],
                'customer' => [
                    'id' => $document->rental->customer->id,
                    'name' => $document->rental->customer->name,
                    'phone' => $document->rental->customer->phone
                ],
                'aadhaar_image' => $document->aadhaar_image,
                'license_image' => $document->license_image,
                'is_verified' => $document->is_verified,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at
            ]
        ]);
    }

    public function download($id, $type)
    {
        $document = Document::with('rental')->find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        if (!in_array($type, ['aadhaar', 'license'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid document type. Must be aadhaar or license'
            ], 400);
        }

        $path = $type === 'aadhaar' 
            ? $document->aadhaar_image 
            : $document->license_image;

        if (!$path || !Storage::disk('public')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Document file not found'
            ], 404);
        }

        return Storage::disk('public')->download($path);
    }
}