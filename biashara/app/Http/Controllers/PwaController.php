<?php

namespace App\Http\Controllers;

use App\Support\Settings;

class PwaController extends Controller
{
    public function serviceWorker()
    {
        return response(file_get_contents(resource_path('pwa/sw.js')), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function manifest()
    {
        $base = rtrim(url('/'), '/');

        return response()->json([
            'name' => Settings::get('shop_name').' — Biashara',
            'short_name' => 'Biashara',
            'start_url' => $base.'/caisse-vente',
            'scope' => $base.'/',
            'display' => 'standalone',
            'background_color' => '#0B1F3A',
            'theme_color' => '#0B1F3A',
            'lang' => 'fr',
            'icons' => [
                ['src' => asset('images/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('images/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
