<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EggInventoryController;

Route::get('/egg-inventory', [EggInventoryController::class, 'index']);
Route::post('/egg-inventory', [EggInventoryController::class, 'store']);
Route::get('/egg-inventory-summary', [EggInventoryController::class, 'summary']);
