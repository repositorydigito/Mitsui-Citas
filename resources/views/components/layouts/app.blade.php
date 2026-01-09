<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="application-name" content="{{ config('app.name') }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name') }}</title>

        <style>
            [x-cloak] { display: none !important; }
            
            /* Asegurar visibilidad de labels */
            .fi-fo-field-wrp-label {
                display: block !important;
                font-weight: 600 !important;
                color: #374151 !important;
                margin-bottom: 0.5rem !important;
                font-size: 0.875rem !important;
            }
            
            /* Asegurar visibilidad de inputs */
            .fi-input {
                display: block !important;
                width: 100% !important;
                background-color: white !important;
                color: #111827 !important;
                border: 1px solid #d1d5db !important;
                border-radius: 0.375rem !important;
                padding: 0.5rem 0.75rem !important;
                font-size: 1rem !important;
            }
            
            /* Focus states */
            .fi-input:focus {
                border-color: #0075BF !important;
                box-shadow: 0 0 0 1px #0075BF !important;
                outline: none !important;
            }
        </style>
        <script src="https://cdn.tailwindcss.com"></script>
        @livewireStyles
    </head>

    <body class="antialiased">
        {{ $slot }}

        @livewireScripts
    </body>
</html> 
