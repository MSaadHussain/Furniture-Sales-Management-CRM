@extends('layouts.app')
@section('title', 'My Profile')

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    {{-- Avatar + identity --}}
    <x-card title="Profile Photo">
        <div class="flex flex-col items-center text-center">
            <x-user-avatar :user="$user" :px="112" text="text-3xl" />
            <p class="mt-4 text-lg font-semibold text-ink dark:text-white">{{ $user->name }}</p>
            <p class="text-sm text-muted">{{ $user->role->label() }}</p>

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-5 w-full">
                @csrf @method('PATCH')
                <input type="hidden" name="name" value="{{ $user->name }}">
                <input type="hidden" name="email" value="{{ $user->email }}">
                <label class="ta-label text-left">Upload a new photo</label>
                <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp"
                       class="ta-input mb-3 file:mr-3 file:rounded-md file:border-0 file:bg-brand file:px-3 file:py-1.5 file:text-white">
                @error('avatar')<p class="mb-2 text-xs text-danger">{{ $message }}</p>@enderror
                <button class="btn btn-primary w-full"><i class="fa-solid fa-upload"></i> Upload photo</button>
            </form>

            @if ($user->avatar_path)
                <form method="POST" action="{{ route('profile.avatar.remove') }}" class="mt-2 w-full">
                    @csrf @method('DELETE')
                    <button class="btn btn-light w-full"><i class="fa-solid fa-trash"></i> Remove photo</button>
                </form>
            @endif
        </div>
    </x-card>

    {{-- Profile info --}}
    <x-card title="Profile Information" class="lg:col-span-2">
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf @method('PATCH')
            <div>
                <label class="ta-label">Name</label>
                <input name="name" value="{{ old('name', $user->name) }}" class="ta-input @error('name') border-danger @enderror">
                @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ta-label">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="ta-input @error('email') border-danger @enderror">
                @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ta-label">Avatar color <span class="text-muted">(used when no photo is set)</span></label>
                <input type="color" name="avatar_color" value="{{ old('avatar_color', $user->avatar_color ?? '#465FFF') }}"
                       class="h-11 w-20 cursor-pointer rounded-lg border border-line dark:border-strokedark">
            </div>
            <div class="flex justify-end">
                <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
            </div>
        </form>
    </x-card>

    {{-- Password --}}
    <x-card title="Update Password" class="lg:col-span-3">
        <form method="POST" action="{{ route('profile.password') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @csrf @method('PUT')
            <div>
                <label class="ta-label">Current password</label>
                <input type="password" name="current_password" class="ta-input @error('current_password') border-danger @enderror">
                @error('current_password')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ta-label">New password</label>
                <input type="password" name="password" class="ta-input @error('password') border-danger @enderror">
                @error('password')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ta-label">Confirm new password</label>
                <input type="password" name="password_confirmation" class="ta-input">
            </div>
            <div class="sm:col-span-3 flex justify-end">
                <button class="btn btn-light"><i class="fa-solid fa-key"></i> Update password</button>
            </div>
        </form>
    </x-card>
</div>
@endsection
