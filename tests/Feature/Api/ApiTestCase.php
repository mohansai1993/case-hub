<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerificationCode;
use Database\Seeders\PracticeAreaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected const PASSWORD = 'Password@123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PracticeAreaSeeder::class);
    }

    /** Payload of the client registration form. */
    protected function clientPayload(array $override = []): array
    {
        return array_merge([
            'name' => 'Rahul Sharma',
            'email' => 'rahul@example.com',
            'mobile' => '9876543210',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terms_accepted' => true,
        ], $override);
    }

    /** The code the (faked) SMS would have carried. */
    protected function sentCode(User $user, string $notification = VerificationCode::class): string
    {
        $sent = Notification::sent($user, $notification);
        $this->assertNotEmpty($sent, 'no SMS was sent to this user');

        preg_match('/\b(\d{' . config('otp.app.length') . '})\b/', $sent->last()->toSms($user), $m);

        return $m[1];
    }

    protected function wrongCode(string $code): string
    {
        return $code === '0000' ? '1111' : '0000';
    }

    protected function bearer(User $user, array $abilities = null): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('test', $abilities ?? [$user->type->value])->plainTextToken];
    }
}
