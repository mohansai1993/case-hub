<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\LawyerProfile;
use App\Models\PracticeArea;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample clients and lawyers so the admin panel has something to manage.
 * Local development only, and deliberately NOT part of DatabaseSeeder:
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Idempotent: everything uses @demo.casehub.test emails and is skipped if present.
 * All demo accounts log in with the password "Password@123".
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            $this->command?->error('DemoDataSeeder only runs in the local environment.');

            return;
        }

        $this->call(PracticeAreaSeeder::class);

        if (User::where('email', 'like', '%@demo.casehub.test')->exists()) {
            $this->command?->info('Demo data already present - skipped.');

            return;
        }

        $clients = [
            ['Rahul Sharma', '9810000001', UserStatus::Active],
            ['Priya Verma', '9810000002', UserStatus::Active],
            ['David Chen', '9810000003', UserStatus::Inactive],
            ['Michael Chang', '9810000004', UserStatus::Suspended],
            ['Sophia Martinez', '9810000005', UserStatus::Active],
            ['Jonathan Cole', '9810000006', UserStatus::Active],
            ['Ananya Patel', '9810000007', UserStatus::Active],
            ['Elena Rostova', '9810000008', UserStatus::Suspended],
        ];

        foreach ($clients as $i => [$name, $mobile, $status]) {
            User::factory()->create([
                'name' => $name,
                'email' => 'client' . ($i + 1) . '@demo.casehub.test',
                'mobile' => $mobile,
                'status' => $status,
                'created_at' => now()->subDays(3 * ($i + 1)),
            ]);
        }

        // One client who registered but never verified their mobile.
        User::factory()->unverifiedMobile()->create([
            'name' => 'Arjun Nair',
            'email' => 'client9@demo.casehub.test',
            'mobile' => '9810000009',
        ]);

        $areas = PracticeArea::pluck('id', 'slug');

        $lawyers = [
            ['Adv. Sarah Williams', '9820000001', 'New Delhi, India', 12, UserStatus::Active, VerificationStatus::Verified, ['compliance', 'contracts']],
            ['Adv. John Smith', '9820000002', 'Mumbai, India', 9, UserStatus::Active, VerificationStatus::Verified, ['employee-rights']],
            ['Adv. Marcus Vance', '9820000003', 'Bengaluru, India', 4, UserStatus::Active, VerificationStatus::Pending, ['severance', 'wrongful-termination']],
            ['Adv. Elena Rostova', '9820000004', 'Chennai, India', 7, UserStatus::Active, VerificationStatus::Pending, ['arbitration']],
            ['Adv. David Chen', '9820000005', 'Pune, India', 15, UserStatus::Suspended, VerificationStatus::Verified, ['contracts', 'arbitration']],
            ['Adv. Priya Verma', '9820000006', 'Jaipur, India', 6, UserStatus::Active, VerificationStatus::Rejected, ['compliance']],
        ];

        foreach ($lawyers as $i => [$name, $mobile, $location, $years, $status, $verification, $slugs]) {
            $lawyer = User::factory()->create([
                'name' => $name,
                'email' => 'lawyer' . ($i + 1) . '@demo.casehub.test',
                'mobile' => $mobile,
                'status' => $status,
                'type' => \App\Enums\UserType::Lawyer,
                'created_at' => now()->subDays(2 * ($i + 1)),
            ]);

            LawyerProfile::unguarded(fn () => LawyerProfile::create([
                'user_id' => $lawyer->user_id,
                'location' => $location,
                'years_of_experience' => $years,
                'bio' => 'Experienced in ' . implode(', ', array_map(fn ($s) => str_replace('-', ' ', $s), $slugs)) . '.',
                'verification_status' => $verification,
                'verified_at' => $verification === VerificationStatus::Verified ? now()->subDay() : null,
            ]));

            $lawyer->practiceAreas()->sync(collect($slugs)->map(fn ($s) => $areas[$s])->all());
        }

        $this->command?->info('Demo clients and lawyers created (password: Password@123).');
    }
}
