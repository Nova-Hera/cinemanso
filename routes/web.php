<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\UserController;

Route::get('/', [ 'as' => 'index', 'uses' => 'App\Http\Controllers\IndexController@index' ]);
Route::get('/signup', [ 'as' => 'signup', 'uses' => 'App\Http\Controllers\SignupController@index' ]);
Route::get('/login', [ 'as' => 'login', 'uses' => 'App\Http\Controllers\LoginController@index' ]);
Route::get('/movie/{id}', [ 'as' => 'movie.show', 'uses' => 'App\Http\Controllers\MovieController@show' ]);
Route::get('/user/{id}', [ 'as' => 'user.show', 'uses' => 'App\Http\Controllers\UserController@show' ]);
Route::get('/users', [ 'as' => 'users', 'uses' => 'App\Http\Controllers\UserController@index' ]);
Route::post('/login', [ 'as' => 'login', 'uses' => 'App\Http\Controllers\LoginController@login' ]);
Route::post('/signup', [ 'as' => 'signup', 'uses' => 'App\Http\Controllers\SignupController@signup' ]);