<?php

namespace Tests\Feature\Api;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\PracticeArea;
use App\Models\User;
use App\Notifications\VerificationCode;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class RegistrationTest extends ApiTestCase
{
    private const CLIENT = '/api/v1/auth/register/client';
    private const LAWYER = '/api/v1/auth/register/lawyer';

    private function lawyerPayload(array $override = []): array
    {
        return array_merge($this->clientPayload([
            'name' => 'Adv. Sarah Jenkins',
            'email' => 'sarah@lawfirm.com',
            'mobile' => '98765 43210',
        ]), [
            'location' => 'New Delhi, India',
            'years_of_experience' => 8,
            'practice_areas' => PracticeArea::whereIn('slug', ['severance', 'compliance'])->pluck('id')->all(),
            'bio' => 'Specializing in corporate governance.',
        ], $override);
    }

    public function test_client_can_register_and_is_asked_to_verify_the_email(): void
    {
        Notification::fake();

        $this->postJson(self::CLIENT, $this->clientPayload())
            ->assertCreated()
            ->assertJsonPath('data.user.type', 'client')
            ->assertJsonPath('data.user.mobile', '9876543210')
            ->assertJsonPath('data.user.email_verified', false)
            ->assertJsonPath('data.otp.sent', true)
            ->assertJsonPath('data.otp.length', 4)
            ->assertJsonPath('data.otp.resend_in', 59)
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.user.password');

        $user = User::firstWhere('email', 'rahul@example.com');
        $this->assertSame(UserType::Client, $user->type);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));

        Notification::assertSentTo($user, VerificationCode::class, fn ($n, $channels) => $channels === ['mail']);
    }

    public function test_mobile_and_email_are_normalised(): void
    {
        Notification::fake();

        $this->postJson(self::CLIENT, $this->clientPayload(['email' => '  Rahul@Example.COM ', 'mobile' => '+91 98765-43210']))
            ->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'rahul@example.com', 'mobile' => '9876543210']);
    }

    public function test_lawyer_registers_with_photo_profile_and_practice_areas(): void
    {
        Notification::fake();
        Storage::fake('public');

        $response = $this->post(self::LAWYER, $this->lawyerPayload([
            'photo' => UploadedFile::fake()->image('headshot.jpg', 400, 400)->size(800),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $response->assertJsonPath('data.user.type', 'lawyer')
            ->assertJsonPath('data.user.lawyer.location', 'New Delhi, India')
            ->assertJsonPath('data.user.lawyer.years_of_experience', 8)
            ->assertJsonPath('data.user.lawyer.verification_status', 'pending')
            ->assertJsonCount(2, 'data.user.lawyer.practice_areas');

        $user = User::firstWhere('email', 'sarah@lawfirm.com');
        Storage::disk('public')->assertExists($user->image);
        $this->assertStringStartsWith('profile-photos/', $user->image);
        $this->assertNotNull($response->json('data.user.image_url'));
        $this->assertSame(2, $user->practiceAreas()->count());
    }

    public function test_lawyer_photo_is_optional(): void
    {
        Notification::fake();

        $this->postJson(self::LAWYER, $this->lawyerPayload())->assertCreated()->assertJsonPath('data.user.image_url', null);
    }

    public function test_lawyer_cannot_self_verify(): void
    {
        Notification::fake();

        $this->postJson(self::LAWYER, $this->lawyerPayload(['verification_status' => 'verified']))->assertCreated();

        $this->assertSame('pending', User::firstWhere('email', 'sarah@lawfirm.com')->lawyerProfile->verification_status->value);
    }

    public function test_protected_fields_cannot_be_set_from_the_request(): void
    {
        Notification::fake();

        $this->postJson(self::CLIENT, $this->clientPayload([
            'type' => 'lawyer',
            'status' => 'suspended',
            'mobile_verified_at' => now()->toDateTimeString(),
            'email_verified_at' => now()->toDateTimeString(),
            'role' => 1,
            'is_verified' => true,
        ]))->assertCreated();

        $user = User::firstWhere('email', 'rahul@example.com');
        $this->assertSame(UserType::Client, $user->type);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNull($user->mobile_verified_at);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(0, $user->role);
        $this->assertFalse($user->is_verified);
    }

    #[DataProvider('invalidClientInput')]
    public function test_client_validation(array $override, string $field): void
    {
        $this->postJson(self::CLIENT, $this->clientPayload($override))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidClientInput(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'bad email' => [['email' => 'not-an-email'], 'email'],
            'bad mobile' => [['mobile' => '12345'], 'mobile'],
            'mobile not starting 6-9' => [['mobile' => '5876543210'], 'mobile'],
            'short password' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'], 'password'],
            'no digit' => [['password' => 'NoDigitsHere', 'password_confirmation' => 'NoDigitsHere'], 'password'],
            'no upper case' => [['password' => 'lowercase123', 'password_confirmation' => 'lowercase123'], 'password'],
            'password mismatch' => [['password_confirmation' => 'Different@123'], 'password'],
            'terms not accepted' => [['terms_accepted' => false], 'terms_accepted'],
            'terms missing' => [['terms_accepted' => null], 'terms_accepted'],
        ];
    }

    #[DataProvider('invalidLawyerInput')]
    public function test_lawyer_validation(callable $override, string $field): void
    {
        $this->postJson(self::LAWYER, $this->lawyerPayload($override()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidLawyerInput(): array
    {
        return [
            'no location' => [fn () => ['location' => ''], 'location'],
            'no experience' => [fn () => ['years_of_experience' => null], 'years_of_experience'],
            'negative experience' => [fn () => ['years_of_experience' => -1], 'years_of_experience'],
            'absurd experience' => [fn () => ['years_of_experience' => 99], 'years_of_experience'],
            'no practice areas' => [fn () => ['practice_areas' => []], 'practice_areas'],
            'unknown practice area' => [fn () => ['practice_areas' => [9999]], 'practice_areas.0'],
            'duplicate practice area' => [fn () => ['practice_areas' => [1, 1]], 'practice_areas.0'],
            'bio too long' => [fn () => ['bio' => str_repeat('a', 501)], 'bio'],
        ];
    }

    public function test_photo_must_be_a_jpg_or_png_up_to_5mb(): void
    {
        Storage::fake('public');

        $this->post(self::LAWYER, $this->lawyerPayload(['photo' => UploadedFile::fake()->image('big.jpg')->size(5121)]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->post(self::LAWYER, $this->lawyerPayload(['photo' => UploadedFile::fake()->image('anim.gif')]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->post(self::LAWYER, $this->lawyerPayload(['photo' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_verified_email_or_mobile_cannot_be_registered_again(): void
    {
        User::factory()->create(['email' => 'rahul@example.com', 'mobile' => '9876543210']);

        $this->postJson(self::CLIENT, $this->clientPayload(['email' => 'RAHUL@example.com', 'mobile' => '9123456780']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson(self::CLIENT, $this->clientPayload(['email' => 'other@example.com', 'mobile' => '+91 98765 43210']))
            ->assertUnprocessable()->assertJsonValidationErrors('mobile');
    }

    public function test_an_unverified_registration_is_replaced_not_blocking(): void
    {
        Notification::fake();
        Storage::fake('public');

        $this->post(self::LAWYER, $this->lawyerPayload(['photo' => UploadedFile::fake()->image('a.jpg')]), ['Accept' => 'application/json'])->assertCreated();
        $stale = User::firstWhere('email', 'sarah@lawfirm.com');
        $stalePhoto = $stale->image;

        // Someone (the real owner) registers the same number and email later.
        $this->postJson(self::CLIENT, $this->clientPayload(['email' => 'sarah@lawfirm.com', 'mobile' => '9876543210']))->assertCreated();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['user_id' => $stale->user_id]);
        $this->assertDatabaseCount('lawyer_profiles', 0);
        Storage::disk('public')->assertMissing($stalePhoto);
    }

    public function test_the_account_survives_a_failed_email(): void
    {
        $brokenMailer = new class implements Mailer {
            public function to($users) { return $this; }
            public function bcc($users) { return $this; }
            public function raw($text, $callback) { throw new RuntimeException('mail down'); }
            public function send($view, array $data = [], $callback = null) { throw new RuntimeException('mail down'); }
            public function sendNow($mailable, array $data = [], $callback = null) { throw new RuntimeException('mail down'); }
        };
        $this->app->bind(MailFactory::class, fn () => new class($brokenMailer) implements MailFactory {
            public function __construct(private readonly Mailer $mailer) {}
            public function mailer($name = null) { return $this->mailer; }
        });

        $this->postJson(self::CLIENT, $this->clientPayload())
            ->assertCreated()
            ->assertJsonPath('data.otp.sent', false);

        $this->assertDatabaseHas('users', ['email' => 'rahul@example.com']);
    }

    public function test_practice_areas_are_listed_for_the_form(): void
    {
        PracticeArea::where('slug', 'contracts')->update(['is_active' => false]);

        $names = collect($this->getJson('/api/v1/practice-areas')->assertOk()->json('data'))->pluck('name');

        $this->assertContains('Severance', $names);
        $this->assertNotContains('Contracts', $names);
    }

    public function test_api_errors_are_json_even_without_an_accept_header(): void
    {
        $this->post(self::CLIENT, [])->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->get('/api/v1/nope')->assertStatus(404)->assertJsonStructure(['message']);
        $this->get('/api/v1/auth/me')->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }
}
