<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Movie;

class IndexController extends Controller 
{
    public function index() {
        $movies = Movie::all();
        return view('welcome', compact('movies'));
    }
}