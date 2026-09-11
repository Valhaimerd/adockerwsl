<?php

return [
    [
        'id' => 'project1',
        'name' => 'Students',
        'subtitle' => 'Student Directory',
        'icon' => 'students',
        'health_url' => env('PROJECT1_HEALTH_URL', 'http://project1/health'),
        'data_url' => env('PROJECT1_DATA_URL', 'http://project1/api/students'),
        'public_url' => env('PROJECT1_PUBLIC_URL', 'http://localhost:8081'),
    ],
    [
        'id' => 'project2',
        'name' => 'Faculty',
        'subtitle' => 'Faculty Directory',
        'icon' => 'faculty',
        'health_url' => env('PROJECT2_HEALTH_URL', 'http://project2/health'),
        'data_url' => env('PROJECT2_DATA_URL', 'http://project2/api/faculty'),
        'public_url' => env('PROJECT2_PUBLIC_URL', 'http://localhost:8082'),
    ],
    [
        'id' => 'project3',
        'name' => 'Courses',
        'subtitle' => 'Course Catalog',
        'icon' => 'courses',
        'health_url' => env('PROJECT3_HEALTH_URL', 'http://project3/health'),
        'data_url' => env('PROJECT3_DATA_URL', 'http://project3/api/courses'),
        'public_url' => env('PROJECT3_PUBLIC_URL', 'http://localhost:8083'),
    ],
];
