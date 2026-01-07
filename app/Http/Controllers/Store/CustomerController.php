<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('query', null);
        $storeId = $request->query('store', null);
        $customers = Customer::where('store_id', $storeId)
            ->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%");
            })
            ->paginate(10);
        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'store_id' => 'required',
            'name' => 'required',
            'phone' => 'required|unique:customers,phone',
            'email' => 'required|email|unique:customers,email',
            'address' => 'nullable|string',
        ]);

        $data['created_by'] = $user->id;

        $customer = Customer::create($data);

        return response()->json($customer, 201);
    }
}
