<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class ProjectStatusService
{
    public function all(): array
    {
        return collect(config('projects'))->map(function (array $project): array {
            try {
                $available = Http::acceptJson()
                    ->connectTimeout(1)
                    ->timeout(1)
                    ->get($project['health_url'])
                    ->successful();
            } catch (Throwable) {
                $available = false;
            }

            return [
                'id' => $project['id'],
                'name' => $project['name'],
                'subtitle' => $project['subtitle'],
                'icon' => $project['icon'],
                'url' => $project['public_url'],
                'available' => $available,
            ];
        })->all();
    }
}
