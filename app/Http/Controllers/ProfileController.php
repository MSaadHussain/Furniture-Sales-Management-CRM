<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'avatar_color' => ['nullable', 'string', 'max:7'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            // Remove the old file if present.
            if ($user->avatar_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->fill(collect($validated)->except('avatar')->toArray());
        $user->save();

        return back()->with('toast', 'Profile updated.');
    }

    /** Remove the uploaded avatar (revert to initials). */
    public function removeAvatar(Request $request)
    {
        $user = $request->user();
        if ($user->avatar_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return back()->with('toast', 'Profile photo removed.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', 'min:8'],
        ]);

        $request->user()->update(['password' => Hash::make($validated['password'])]);

        return back()->with('toast', 'Password updated.');
    }
}
