<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class LoginController extends Controller
{
    public function index() {
        return view('login');
    }

    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user === null || !password_verify($request->password, $user->password)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json($user, 200);
        
    }
    
}
