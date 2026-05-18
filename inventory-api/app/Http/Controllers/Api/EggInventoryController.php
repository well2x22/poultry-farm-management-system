<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EggInventory;
use Illuminate\Http\Request;

class EggInventoryController extends Controller
{
    public function index()
    {
        $inventory = EggInventory::orderBy('id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Egg inventory records retrieved successfully.',
            'data' => $inventory
        ]);
    }

public function store(Request $request)
{
    $validated = $request->validate([
        'batch_code' => 'required|string|max:255',
        'egg_size' => 'required|in:Extra Large,Large,Medium,Small',
        'quantity' => 'required|integer|min:1',
        'received_date' => 'required|date',
    ]);

    $inventory = EggInventory::where('batch_code', $validated['batch_code'])
        ->where('egg_size', $validated['egg_size'])
        ->first();

    if ($inventory) {
        $inventory->quantity = $validated['quantity'];
        $inventory->received_date = $validated['received_date'];
        $inventory->save();

        return response()->json([
            'status' => true,
            'message' => 'Existing egg inventory updated without duplicating stock.',
            'data' => $inventory
        ], 200);
    }

    $inventory = EggInventory::create($validated);

    return response()->json([
        'status' => true,
        'message' => 'New egg inventory saved successfully.',
        'data' => $inventory
    ], 201);
}

    public function summary()
    {
        $summary = EggInventory::selectRaw('egg_size, SUM(quantity) as total_quantity')
            ->groupBy('egg_size')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Egg inventory summary retrieved successfully.',
            'data' => $summary
        ]);
    }
}
