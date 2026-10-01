<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\UpdatePhotoRequest;
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
}
