<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemRoom extends Model
{
    use HasFactory;

    protected $table = 'item_room';

    protected $fillable = [
        'room_id',
        'item_id',
        'position_x',
        'position_y',
        'position_z',
        'rotation_x',
        'rotation_y',
        'rotation_z',
        'rotation_w',
        'scale_x',
        'scale_y',
        'scale_z',
        'parentindex'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
