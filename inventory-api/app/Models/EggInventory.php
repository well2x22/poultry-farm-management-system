<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EggInventory extends Model
{
    protected $fillable = [
        'batch_code',
        'egg_size',
        'quantity',
        'received_date',
    ];
}
