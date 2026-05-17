<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InventoryWebController;

Route::get('/', function () {
    return redirect('/inventory');
});

Route::get('/inventory', [InventoryWebController::class, 'index']);
