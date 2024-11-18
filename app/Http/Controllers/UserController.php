<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    function index() {
        $users = User::all();
        return view('users', compact('users'));
    }
    
    function show($id) {
        $user = User::findOrFail($id);
        return view('user', compact('user'));
    }
}
