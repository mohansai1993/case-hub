<?php

namespace App\Services\AppAuth;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\VerificationStatus;
use App\Models\LawyerProfile;
use App\Models\User;
use App\Models\UserOtp;
use App\Support\Identifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrationService
{
    public function __construct(private readonly UserOtpService $otps)
    {
    }

    /**
     * @param  array{name: string, email: string, mobile: string, password: string}  $data
     */
    public function registerClient(array $data): RegistrationResult
    {
        return $this->register(UserType::Client, $data);
    }

    /**
     * @param  array{name: string, email: string, mobile: string, password: string, location: string, years_of_experience: int, practice_areas: list<int>, bio?: ?string}  $data
     */
    public function registerLawyer(array $data, ?UploadedFile $photo = null): RegistrationResult
    {
        return $this->register(UserType::Lawyer, $data, $photo);
    }

    private function register(UserType $type, array $data, ?UploadedFile $photo = null): RegistrationResult
    {
        $email = mb_strtolower(trim($data['email']));
        $mobile = Identifier::normalizeMobile($data['mobile']) ?? $data['mobile'];
        $photoPath = null;

        try {
            $user = DB::transaction(function () use ($type, $data, $email, $mobile, $photo, &$photoPath) {
                $this->purgeStaleUnverified($email, $mobile);

                $user = new User([
                    'name' => trim($data['name']),
                    'email' => $email,
                    'mobile' => $mobile,
                    'password' => $data['password'],
                ]);

                if ($photo) {
                    $photoPath = $photo->store(
                        config('casehub.profile_photo.directory'),
                        config('casehub.profile_photo.disk'),
                    );
                    $user->image = $photoPath;
                }

                // Protected columns are set explicitly, never from request data.
                $user->forceFill([
                    'type' => $type,
                    'status' => UserStatus::Active,
                    'terms_accepted_at' => now(),
                ])->save();

                if ($type === UserType::Lawyer) {
                    $profile = new LawyerProfile([
                        'user_id' => $user->user_id,
                        'location' => trim($data['location']),
                        'years_of_experience' => (int) $data['years_of_experience'],
                        'bio' => isset($data['bio']) ? trim($data['bio']) : null,
                    ]);
                    $profile->verification_status = VerificationStatus::Pending; // admin reviews it
                    $profile->save();

                    $user->practiceAreas()->sync($data['practice_areas']);
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Lost a race with a concurrent registration of the same email/mobile.
            $this->deleteStoredPhoto($photoPath);

            throw ValidationException::withMessages([
                'email' => 'An account with these details already exists.',
            ]);
        } catch (Throwable $e) {
            $this->deleteStoredPhoto($photoPath);

            throw $e;
        }

        return new RegistrationResult($user, $this->sendVerificationOtp($user));
    }

    /**
     * Accounts that never verified their mobile hold no data and cannot log in,
     * so a new registration for the same email/mobile simply replaces them.
     * This stops a stranger from squatting on someone else's email or number
     * by registering it without ever verifying it.
     */
    private function purgeStaleUnverified(string $email, string $mobile): void
    {
        User::unverified()
            ->where(fn ($query) => $query->where('email', $email)->orWhere('mobile', $mobile))
            ->get()
            ->each(function (User $stale) {
                $this->deleteStoredPhoto($stale->image);
                $stale->delete();
            });
    }

    /** A failed SMS must not lose the account; the app offers "Resend OTP". */
    private function sendVerificationOtp(User $user): bool
    {
        try {
            return $this->otps->send($user, UserOtp::PURPOSE_VERIFY_MOBILE);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function deleteStoredPhoto(?string $path): void
    {
        if ($path) {
            Storage::disk(config('casehub.profile_photo.disk'))->delete($path);
        }
    }
}
