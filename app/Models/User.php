<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function billingLogs()
    {
        return $this->hasMany(BillingLog::class);
    }

    public function logs()
    {
        return $this->hasMany(Log::class);
    }

    public function likeMaketplaces()
    {
        return $this->belongsToMany(Marketplace::class)->withTimestamps();
    }

    public function likeWarehouses()
    {
        return $this->belongsToMany(Warehouse::class)->withTimestamps();
    }

    public function marketplaces()
    {
        return $this->hasMany(Marketplace::class);
    }

    public function mpcomments()
    {
        return $this->hasMany(Mpcomment::class);
    }

    public function warehouseComments()
    {
        return $this->hasMany(WarehouseComment::class);
    }

    /**
     * ユーザーが受け取った通知
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * ユーザーが発生させた通知
     */
    public function actionNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'action_user_id');
    }

    public function purchasePoint(): BelongsTo
    {
        return $this->belongsTo(PurchasePoint::class);
    }

    public function purchaseRecords(): HasMany
    {
        return $this->hasMany(PurchaseRecord::class);
    }
}
