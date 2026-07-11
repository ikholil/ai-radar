<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    /** @use HasFactory<\Database\Factories\SourceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'last_polled_at',
        'poll_interval_minutes',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'last_polled_at' => 'datetime',
            'poll_interval_minutes' => 'integer',
            'enabled' => 'boolean',
        ];
    }
}
