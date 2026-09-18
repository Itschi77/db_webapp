<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminConnectionProfile extends Model
{
    protected $fillable = [
        'name', 'key', 'type', 'host', 'port', 'database', 'username',
        'secret', 'options', 'active', 'last_test_at', 'last_test_status',
        'last_test_message', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'options' => 'array',
            'active' => 'boolean',
            'last_test_at' => 'datetime',
        ];
    }
}
