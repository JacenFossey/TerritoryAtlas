<?php

namespace App\Http\Controllers;

use App\Services\Geography\AreaSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaSearchController extends Controller
{
    public function __invoke(Request $request, AreaSearch $areaSearch): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json([
            'data' => $areaSearch->search($validated['q'] ?? ''),
        ]);
    }
}
