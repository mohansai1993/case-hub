<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\VerificationStatus;
use App\Models\LawyerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factories bypass mass-assignment guards, so the protected columns
 * (type, status, mobile_verified_at) can be set here directly.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public const PASSWORD = 'Password@123';

    /**
     * Default: an active client whose email is already verified.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::PASSWORD,
            'role' => 0,
            'type' => UserType::Client,
            'status' => UserStatus::Active,
            'mobile' => fake()->unique()->numerify('9#########'),
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function lawyer(VerificationStatus $verification = VerificationStatus::Pending): static
    {
        return $this->state(['type' => UserType::Lawyer])
            ->afterCreating(function (User $user) use ($verification) {
                LawyerProfile::unguarded(fn () => LawyerProfile::create([
                    'user_id' => $user->user_id,
                    'location' => 'New Delhi, India',
                    'years_of_experience' => 8,
                    'bio' => 'Corporate governance and labour disputes.',
                    'verification_status' => $verification,
                ]));
            });
    }

    public function unverifiedEmail(): static
    {
        return $this->state(['email_verified_at' => null, 'mobile_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => UserStatus::Suspended]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => UserStatus::Inactive]);
    }
}
