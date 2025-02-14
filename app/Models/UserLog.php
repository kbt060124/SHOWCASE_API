<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    protected $fillable = [
        'user_id',
        'route_name',
        'method',
        'url',
        'parameters',
        'status_code',
        'response',
        'started_at',
        'ended_at',
        'duration_ms'
    ];

    protected $casts = [
        'parameters' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime'
    ];

    // ステータスコードによる検索用スコープ
    public function scopeErrors($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status_code', '<', 400);
    }

}

