<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = ['user_id', 'name', 'key', 'permissions', 'allowed_ips', 'last_used_at', 'expires_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'allowed_ips' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
