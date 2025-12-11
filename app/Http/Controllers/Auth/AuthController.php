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

        $user['image'] = $user->getUserImageUrl();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response(['error' => 'Invalid credentials'], 401);
        }

        $user['image'] = $user->getUserImageUrl();

        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function getUser(Request $request)
    {
        $user = $request->user();

        $user['image'] = $user->getUserImageUrl();

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

        $user['image'] = $user->getUserImageUrl();
        $user->load('role');

        return response()->json($user);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password updated successfully']);
    }
}
