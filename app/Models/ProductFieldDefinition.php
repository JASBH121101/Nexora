<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductFieldDefinition extends Model
{
    protected $fillable = [
        'group_id', 'field_key', 'label', 'field_type', 'options',
        'unit_dimension', 'unit_code', 'is_required', 'is_public',
        'is_searchable', 'width', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_public' => 'boolean',
            'is_searchable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CatalogGroup::class, 'group_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductFieldValue::class, 'field_definition_id');
    }
}
