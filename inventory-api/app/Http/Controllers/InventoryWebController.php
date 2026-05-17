<?php

namespace App\Http\Controllers;

use App\Models\EggInventory;

class InventoryWebController extends Controller
{
    public function index()
    {
        $inventories = EggInventory::orderBy('id', 'desc')->get();

        $summary = EggInventory::selectRaw('egg_size, SUM(quantity) as total_quantity')
            ->groupBy('egg_size')
            ->get();

        $totalEggs = EggInventory::sum('quantity');

        return view('inventory.index', compact(
            'inventories',
            'summary',
            'totalEggs'
        ));
    }
}
