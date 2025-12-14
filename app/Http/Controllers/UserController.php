<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        $users = User::whereNot('id', $authUser->id)->where('status', 'active')->get();
        return response()->json($users);
    }
}
