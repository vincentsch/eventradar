<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Events\MapboxGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AddressSearchController extends Controller
{
    public function __invoke(Request $request, MapboxGeocoder $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:200'],
        ]);
        try {
            $results = $geocoder->forward($validated['q']);
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'q' => 'Address lookup is not configured. Enter the address and coordinates manually.',
            ]);
        }

        return response()->json(['results' => $results]);
    }
}
