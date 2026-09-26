<?php

namespace Database\Seeders;

use App\Models\PracticeArea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PracticeAreaSeeder extends Seeder
{
    /** The specialization chips on the lawyer registration screen. Idempotent. */
    public function run(): void
    {
        foreach (['Severance', 'Wrongful Termination', 'Compliance', 'Employee Rights', 'Arbitration', 'Contracts'] as $name) {
            PracticeArea::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
