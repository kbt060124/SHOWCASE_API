<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marketplace extends Model
{
    use HasFactory;

    // protected $fillable = [
    //     'user_id',
    //     'item_id',
    //     'name',
    //     'thumbnail',
    //     'tags',
    //     'views',
    //     'price',
    //     'status',
    // ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Mpcomment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Mptag::class);
    }

    public function liked(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function billingLogs(): HasMany
    {
        return $this->hasMany(BillingLog::class);
    }

    public function purchaseRecords(): HasMany
    {
        return $this->hasMany(PurchaseRecord::class);
    }
}
