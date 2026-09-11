<!doctype html>
<html lang="en" data-theme="corporate">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="project-name" content="{{ $project['name'] }}">
    <meta name="project-subtitle" content="{{ $project['subtitle'] }}">
    <meta name="project-description" content="{{ $project['description'] }}">
    <meta name="unify-url" content="{{ $project['unify_url'] }}">
    <title>{{ $project['name'] }} — {{ $project['subtitle'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
