<?php

namespace App\Models;

use App\Enums\SubjectKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    /** @use HasFactory<\Database\Factories\SubjectFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'kind',
        'external_url',
        'metric_value',
        'metric_history',
        'first_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SubjectKind::class,
            'metric_value' => 'integer',
            'metric_history' => 'array',
            'first_seen_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
