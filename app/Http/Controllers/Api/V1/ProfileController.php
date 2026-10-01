<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\UpdateLawyerProfileRequest;
use App\Http\Requests\Api\Profile\UpdatePhotoRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/** Self-service profile actions for the signed-in client or lawyer. */
class ProfileController extends Controller
{
    public function updatePhoto(UpdatePhotoRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $oldImage = $user->image;

        $path = $request->file('photo')->store(
            config('casehub.profile_photo.directory'),
            config('casehub.profile_photo.disk'),
        );

        $user->update(['image' => $path]);

        if ($oldImage) {
            Storage::disk(config('casehub.profile_photo.disk'))->delete($oldImage);
        }

        return response()->json([
            'message' => 'Profile photo updated.',
            'data' => ['image_url' => $user->imageUrl()],
        ]);
    }

    /** Location, years of experience, practice areas and bio - the lawyer-only part of the profile. */
    public function updateLawyerProfile(UpdateLawyerProfileRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user->isLawyer()) {
            return response()->json(['message' => 'Only lawyers have a practice profile to update.'], 403);
        }

        $user->lawyerProfile->update([
            'location' => $request->location(),
            'years_of_experience' => $request->integer('years_of_experience'),
            'bio' => $request->bio(),
        ]);

        $user->practiceAreas()->sync($request->input('practice_areas'));

        return response()->json([
            'message' => 'Profile updated.',
            'data' => ['user' => new UserResource($user->load('lawyerProfile', 'practiceAreas'))],
        ]);
    }
}
