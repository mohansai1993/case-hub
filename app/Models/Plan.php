<?php

namespace App\Models;

use App\Enums\PlanStorageUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A storage capacity tier clients subscribe to (one-time payment, non-expiring). */
class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'storage_amount', 'storage_unit', 'price', 'description', 'is_popular', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'storage_unit' => PlanStorageUnit::class,
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function storageLabel(): string
    {
        return "{$this->storage_amount} {$this->storage_unit->value}";
    }
}
