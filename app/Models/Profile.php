<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nickname',
        'last_name',
        'first_name',
        'user_thumbnail',
        'attribute',
        'birthday',
        'gender',
        'introduction',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
