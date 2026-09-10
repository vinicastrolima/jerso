<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app', ['initialCode' => null, 'initialTab' => 'table']);
});

Route::get('/mesa/{code}', function ($code) {
    return view('app', ['initialCode' => strtoupper($code), 'initialTab' => 'table']);
});

Route::get('/ranking', function () {
    return view('app', ['initialCode' => null, 'initialTab' => 'ranking']);
});

Route::get('/jogadores', function () {
    return view('app', ['initialCode' => null, 'initialTab' => 'players']);
});

Route::get('/admin', function () {
    return view('app', ['initialCode' => null, 'initialTab' => 'admin']);
});
