<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full min-w-0">
            <div class="min-w-0">
                <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white truncate">Profile Settings</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate hidden sm:block">Manage your account credentials, password, and security</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6 max-w-4xl">
        {{-- Profile Info Card --}}
        <div class="card p-6 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        {{-- Password Card --}}
        <div class="card p-6 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        {{-- Delete Account Card --}}
        <div class="card p-6 sm:p-8 border-red-200 dark:border-red-900/40">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
