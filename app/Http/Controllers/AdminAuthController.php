<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'profile_picture' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required' => 'Please enter your name.',
            'profile_picture.image' => 'Choose a valid image file.',
            'profile_picture.mimes' => 'Profile pictures must be JPG, JPEG, PNG, or WebP.',
            'profile_picture.extensions' => 'Profile pictures must use a JPG, JPEG, PNG, or WebP file extension.',
            'profile_picture.max' => 'Profile pictures must be 2MB or smaller.',
        ]);

        $user = $request->user();
        $previousPicture = $user->profile_picture;
        $newPicture = null;

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }

        if ($request->hasFile('profile_picture')) {
            $newPicture = $request->file('profile_picture')->storePublicly('profile-pictures', 'public');

            if (! is_string($newPicture)) {
                return response()->json([
                    'message' => 'The profile picture could not be saved. Please try again.',
                ], 500);
            }

            $user->profile_picture = $newPicture;
        }

        $user->save();

        if ($newPicture !== null && $previousPicture !== null) {
            Storage::disk('public')->delete($previousPicture);
        }

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([], 204);
    }

    /**
     * @return array{id: int, name: string, email: string, profile_picture_url: string|null}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_picture_url' => $user->profile_picture
                ? Storage::disk('public')->url($user->profile_picture)
                : null,
        ];
    }
}
