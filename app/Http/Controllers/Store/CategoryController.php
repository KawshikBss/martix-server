<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $search = $request->query('query', null);

        $storeIds = $user->stores->pluck('id')->toArray();
        $managerStoreIds = Store::where('manager_id', $user->id)->pluck('id')->toArray();
        $storeIds = array_merge($storeIds, $managerStoreIds);

        $categories = Category::where(function ($q) use ($storeIds) {
            foreach ($storeIds as $storeId) {
                $q->orWhereJsonContains('visible_stores', $storeId);
            }
        })->orWhere('owner_id', $user->id)->with('children');

        if ($search !== null && $search !== '') {
            $like = "%{$search}%";
            $categories = $categories->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        } else if ($request->get('only_parents', false)) {
            $categories = $categories->whereNull('parent_id');
        }

        $categories = $categories->get();
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
            'image' => 'nullable|image|max:2048',
            'parent_id' => 'nullable|exists:categories,id',
            'visible_stores' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('store/categories', 'public');
        } else {
            $imagePath = null;
        }

        $visibleStores = $request->input('visible_stores', []);

        $category = $user->categories()->create([
            'name' => $request->name,
            'slug' => $request->slug,
            'image' => $imagePath,
            'parent_id' => $request->parent_id,
            'visible_stores' => json_encode($visibleStores),
            'status' => $request->get('status', 'active'),
        ]);

        // $category['image'] = $category->getImageUrl();

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
            'image' => 'nullable|image|max:2048',
            'parent_id' => 'nullable|exists:categories,id',
            'visible_stores' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('profiles', 'public');
        } else {
            $imagePath = null;
        }

        $visibleStores = $request->input('visible_stores', $category->visible_stores);

        $category->update([
            'name' => $request->name,
            'slug' => $request->slug,
            'image' => $imagePath,
            'parent_id' => $request->parent_id,
            'visible_stores' => json_encode($visibleStores),
            'status' => $request->get('status', $category->status),
        ]);

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
