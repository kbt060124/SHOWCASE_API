<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function mp_objects()
    {
        return $this->hasMany(Mp_object::class);
    }
}
