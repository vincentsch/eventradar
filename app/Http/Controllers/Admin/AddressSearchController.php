<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Events\MapboxGeocoder;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        } catch (HttpClientException $exception) {
            report($exception);

            return response()->json([
                'errors' => [
                    'q' => 'Address lookup is temporarily unavailable. Try again or open the manual fields.',
                ],
            ], 422);
        } catch (RuntimeException) {
            return response()->json([
                'errors' => [
                    'q' => 'Address lookup is not configured. Open the manual fields to enter the location.',
                ],
            ], 422);
        }

        return response()->json(['results' => $results]);
    }
}
