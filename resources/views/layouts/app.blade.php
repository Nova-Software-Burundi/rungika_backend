<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rungika — Send & Receive Money Across Africa</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-white text-gray-900">
{{-- Header --}}
@include('components.header')

{{-- Main Content --}}
<main class="max-w-5xl mx-auto px-4 py-8">
    @yield('content')
</main>

{{-- Footer --}}
@include('components.footer')
</body>
</html>
