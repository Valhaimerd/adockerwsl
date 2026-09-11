<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SchoolOverviewService
{
    public function all(): array
    {
        return collect(config('projects'))->map(function (array $project): array {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout(1)
                    ->timeout(2)
                    ->get($project['data_url']);

                $available = $response->successful();
                $records = $available ? $response->json('data', []) : [];
            } catch (Throwable) {
                $available = false;
                $records = [];
            }

            return [
                'id' => $project['id'],
                'title' => $project['name'],
                'available' => $available,
                'records' => $records,
            ];
        })->all();
    }
}
