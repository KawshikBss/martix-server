<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $categories = $user->categories()->with('children')->get();
        return response()->json($categories);
    }

    public function show($id)
    {
        $user = auth()->user();
        $category = $user->categories()->with('children')->findOrFail($id);
        return response()->json($category);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string',
            'slug' => 'nullable|string|unique:categories,slug',
            'image' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'visible_stores' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('store/categories', 'public');
        } else {
            $imagePath = null;
        }

        $category = $user->categories()->create([
            'name' => $request->name,
            'slug' => $request->slug,
            'image' => $imagePath,
            'parent_id' => $request->parent_id,
            'visible_stores' => $request->visible_stores,
            'status' => $request->get('status', 'active'),
        ]);

        $category['image'] = $category->getImageUrl();

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $user = auth()->user();

        if ($category->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string',
            'slug' => 'nullable|string|unique:categories,slug,' . $category->id,
            'image' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'visible_stores' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('profiles', 'public');
        } else {
            $imagePath = null;
        }

        $category->update([
            'name' => $request->name,
            'slug' => $request->slug,
            'image' => $imagePath,
            'parent_id' => $request->parent_id,
            'visible_stores' => $request->visible_stores,
            'status' => $request->get('status', $category->status),
        ]);

        $category['image'] = $category->getImageUrl();

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $user = auth()->user();

        if ($category->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
