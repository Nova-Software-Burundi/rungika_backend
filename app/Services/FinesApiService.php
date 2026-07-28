<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FinesApiService
{
    public function checkByPlate(string $plate): array
    {
        $url = config('services.fines.url');
        $headers = [
            'tin' => config('services.fines.tin'),
            'referer' => config('services.fines.referer'),
            'rpk' => config('services.fines.rpk'),
            'User-Agent' => config('services.fines.user_agent') ?: config('app-custom.fines_user_agent'),
            'PlateNumber' => $plate,
            'newrelic' => config('services.fines.newrelic'),
            'nls' => config('services.fines.nls'),
        ];

        try {
            $response = Http::withHeaders($headers)->get($url);

            if ($response->failed()) {
                Log::warning("Fines API failed for plate {$plate}", ['status' => $response->status()]);
                return ['status' => 'error', 'message' => 'api_error', 'raw' => $response->body()];
            }

            $json = $response->json();

            // API returns "status": false and data null when clear
            if (!isset($json['status']) || $json['status'] === false || empty($json['data'])) {
                return ['status' => 'clear', 'tickets' => [], 'total' => 0, 'raw' => $json];
            }

            $tickets = $json['data']['trafficFines'] ?? [];
            $total = array_sum(array_map(fn($t) => floatval($t['ticketAmount'] ?? 0), $tickets));

            return ['status' => 'fined', 'tickets' => $tickets, 'total' => $total, 'raw' => $json];

        } catch (\Throwable $e) {
            Log::error("FinesApiService exception for {$plate}: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
