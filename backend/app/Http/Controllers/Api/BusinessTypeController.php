<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BusinessTypeController extends Controller
{
    /**
     * The set of modes HABB POS ships with. Kept as simple config rather
     * than a table since label/icon rarely change; add more here (and to
     * the seeder) to support a new kind of business.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['key' => 'retail', 'label' => 'Retail', 'icon' => '🛍️'],
                ['key' => 'cafe', 'label' => 'Café', 'icon' => '☕'],
                ['key' => 'service', 'label' => 'Service', 'icon' => '🛠️'],
            ],
        ]);
    }
}
