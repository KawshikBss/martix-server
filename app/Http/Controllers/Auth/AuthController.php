<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'image' => 'nullable|image|max:2048',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|unique:users,phone',
            'address' => 'nullable|string',
            'nid' => 'nullable|string',
            'password' => 'required|min:6',
        ]);

        $name = $request->name;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('profiles', 'public');
        } else {
            $imagePath = null;
        }

        $uniqueId = 'MTX-' . strtoupper(uniqid(substr($name, 0, 3)));

        $user = User::create([
            'name' => $request->name,
            'image' => $imagePath,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'nid' => $request->nid,
            'unique_id' => $uniqueId,
            'password' => Hash::make($request->password),
        ]);

        $user['image'] = $imagePath ? env('APP_URL') . '/storage/' . $imagePath : null;

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response(['message' => 'Invalid credentials'], 401);
        }

        $imagePath = $user->image;

        $user['image'] = $imagePath ? env('APP_URL') . '/storage/' . $imagePath : null;

        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function getUser(Request $request)
    {
        $user = $request->user();

        $imagePath = $user->image;

        $user['image'] = $imagePath ? env('APP_URL') . '/storage/' . $imagePath : null;

        return response()->json($user);
    }

    public function updateUser(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|required',
            'image' => 'sometimes|nullable|image|max:2048',
            'phone' => 'sometimes|nullable|unique:users,phone,' . $user->id,
            'address' => 'sometimes|nullable|string',
            'nid' => 'sometimes|nullable|string',
            'tfa_enabled' => 'sometimes|boolean',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('profiles', 'public');
            $user->image = $imagePath;
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        if ($request->has('address')) {
            $user->address = $request->address;
        }
        if ($request->has('nid')) {
            $user->nid = $request->nid;
        }
        if ($request->has('tfa_enabled')) {
            $user->tfa_enabled = $request->tfa_enabled ? 1 : 0;
        }

        $user->save();

        $imagePath = $user->image;

        $user['image'] = $imagePath ? env('APP_URL') . '/storage/' . $imagePath : null;

        return response()->json($user);
    }
}
