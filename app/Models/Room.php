<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'thumbnail'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RoomComment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class)->withPivot([
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
        ])->withTimestamps();
    }

    public function liked()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
