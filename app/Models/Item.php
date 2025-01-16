<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_id',
        'name',
        'thumbnail',
        'totalsize',
        'memo',
        'filename'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class)->withPivot([
            'position_x', 'position_y', 'position_z',
            'rotation_x', 'rotation_y', 'rotation_z', 'rotation_w',
            'scale_x', 'scale_y', 'scale_z',
            'parentindex'
        ])->withTimestamps();
    }
}
