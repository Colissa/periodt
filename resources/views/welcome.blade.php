<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Periodt.</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-pink-50 min-h-screen flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-6xl font-bold text-pink-600">Periodt.</h1>
            <p class="text-gray-600 mt-4 text-lg">Track your cycle. Know your body.</p>

            <div class="mt-8 flex gap-4 justify-center">
                <a href="{{ route('login') }}"
                   class="px-6 py-3 bg-pink-600 text-white rounded-lg hover:bg-pink-700 transition font-semibold">
                    Log In
                </a>
                <a href="{{ route('register') }}"
                   class="px-6 py-3 bg-white text-pink-600 border border-pink-300 rounded-lg hover:bg-pink-50 transition font-semibold">
                    Sign Up
                </a>
            </div>
        </div>
    </body>
</html>
