<?php

return [
    [
        'id' => 'project1',
        'name' => 'Project 1',
        'subtitle' => 'Future Work',
        'icon' => 'briefcase',
        'health_url' => env('PROJECT1_HEALTH_URL', 'http://project1/health'),
        'public_url' => env('PROJECT1_PUBLIC_URL', 'http://localhost:8081'),
    ],
    [
        'id' => 'project2',
        'name' => 'Project 2',
        'subtitle' => 'Future Work',
        'icon' => 'layers',
        'health_url' => env('PROJECT2_HEALTH_URL', 'http://project2/health'),
        'public_url' => env('PROJECT2_PUBLIC_URL', 'http://localhost:8082'),
    ],
    [
        'id' => 'project3',
        'name' => 'Project 3',
        'subtitle' => 'Future Work',
        'icon' => 'rocket',
        'health_url' => env('PROJECT3_HEALTH_URL', 'http://project3/health'),
        'public_url' => env('PROJECT3_PUBLIC_URL', 'http://localhost:8083'),
    ],
];
