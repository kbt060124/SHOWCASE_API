<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'origin_id',
        'itemtype_id',
        'filetype_id',
        'totalsize',
        'filename',
    ];

    public function origin(): BelongsTo
    {
        return $this->belongsTo(ItemOrigin::class, 'origin_id');
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'itemtype_id');
    }

    public function fileType(): BelongsTo
    {
        return $this->belongsTo(ItemFiletype::class, 'filetype_id');
    }

    public function marketplaces(): HasMany
    {
        return $this->hasMany(Marketplace::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
