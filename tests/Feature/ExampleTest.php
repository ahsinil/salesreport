<?php

use Illuminate\Support\Facades\Route;

test('returns a successful response', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('Laporan Penjualan')
        ->assertSee('Catat penjualan, pantau stok, dan susun laporan bulanan dalam satu alur kerja.')
        ->assertSee('Masuk')
        ->assertSee('Daftar');
});

test('asset urls stay secure behind a trusted proxy', function () {
    Route::get('/proxy-aware-assets', function () {
        return response()->json([
            'asset_url' => asset('build/assets/app.css'),
            'secure' => request()->isSecure(),
        ]);
    });

    $response = $this->withServerVariables([
        'HTTP_HOST' => 'sr.ahsinil.com',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'REMOTE_ADDR' => '10.0.0.2',
    ])->get('/proxy-aware-assets');

    $response
        ->assertOk()
        ->assertJsonPath('secure', true);

    expect($response->json('asset_url'))
        ->toStartWith('https://')
        ->not->toStartWith('http://');
});
