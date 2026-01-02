<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PosLab Web Routes
|--------------------------------------------------------------------------
*/

// Гостевое меню - обрабатываем через SPA
Route::get('/menu/{code}', function ($code) {
    return view('guest-menu', ['code' => $code]);
});

// Или простой редирект на статический файл
Route::get('/menu/{code}', function ($code) {
    return redirect('/poslab-guest-menu.html#' . $code);
});

// Отзыв по номеру заказа
Route::get('/review/{orderNumber}', function ($orderNumber) {
    return redirect('/poslab-guest-menu.html?review=' . $orderNumber);
});

Route::get('/', function () {
    return view('welcome');
});