<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $stores = $user->stores;
        return response()->json($stores);
    }

    public function show($id)
    {
        $user = auth()->user();
        $store = $user->stores()->findOrFail($id);
        return response()->json($store);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'branch' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $data['owner_id'] = $user->id;

        $store = $user->stores()->create($data);
        return response()->json($store, 201);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $store = $user->stores()->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'branch' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $store->update($data);
        return response()->json($store);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $store = $user->stores()->findOrFail($id);
        $store->delete();
        return response()->json(null, 204);
    }
}
