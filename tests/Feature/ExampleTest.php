<?php

test('returns a successful response', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('Laporan Penjualan')
        ->assertSee('Catat penjualan, pantau stok, dan susun laporan bulanan dalam satu alur kerja.')
        ->assertSee('Masuk')
        ->assertSee('Daftar');
});
