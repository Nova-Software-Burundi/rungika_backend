<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = ['code', 'name', 'decimals', 'enabled'];

    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
            'enabled' => 'boolean',
        ];
    }
}
