<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bucket extends Model
{
    use HasFactory;

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function mp_objects()
    {
        return $this->hasMany(Mp_object::class);
    }
}
