<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_id',
        'name',
        'thumbnail',
        'favorite',
        'memo',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Whtag::class);
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
