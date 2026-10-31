<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\DocumentLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentLookupController extends Controller
{
    public function show(Request $request, DocumentLookupService $documents): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:DNI,RUC',
            'number' => 'required|digits_between:8,11',
        ]);

        return response()->json($documents->lookup($validated['type'], $validated['number']));
    }
}
