<?php

namespace App\Models;

use App\Enums\PlanStorageUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A storage capacity tier clients subscribe to - billed monthly, auto-renewing. */
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

    public function storageLimitBytes(): int
    {
        $multiplier = $this->storage_unit === PlanStorageUnit::GB ? 1024 ** 3 : 1024 ** 2;

        return $this->storage_amount * $multiplier;
    }
}
