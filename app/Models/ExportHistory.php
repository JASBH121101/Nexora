<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExportHistory extends Model
{
    protected $fillable = [
        'user_id',
        'format',
        'status',
        'file_name',
        'file_size_bytes',
        'record_count',
        'filters',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'record_count' => 'integer',
            'filters' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
