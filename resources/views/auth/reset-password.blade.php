<x-guest-layout>
    {{-- ── Form Heading ── --}}
    <div class="mb-5">
        <h2 style="font-size:19px;font-weight:800;color:#111827;line-height:1.2;">Set new password 🔒</h2>
        <p style="font-size:13px;color:#6B7280;margin-top:3px;">Enter your new password below</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" novalidate autocomplete="off">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div style="margin-bottom:14px;">
            <x-input-label for="email" :value="__('Email Address')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" :is-error="$errors->has('email')" placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div style="margin-bottom:14px;">
            <x-input-label for="password" :value="__('New Password')" />
            <div style="position:relative; margin-top:4px;">
                <x-text-input id="password" class="block w-full" style="padding-right: 44px;" type="password" name="password" required autocomplete="new-password" :is-error="$errors->has('password')" placeholder="••••••••" />
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <!-- Confirm Password -->
        <div style="margin-bottom:18px;">
            <x-input-label for="password_confirmation" :value="__('Confirm New Password')" />
            <div style="position:relative; margin-top:4px;">
                <x-text-input id="password_confirmation" class="block w-full" style="padding-right: 44px;" type="password" name="password_confirmation" required autocomplete="new-password" :is-error="$errors->has('password_confirmation')" placeholder="••••••••" />
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>

        <div style="margin-bottom:16px;">
            <x-primary-button style="width:100%;justify-content:center;padding:10px 0;font-size:14px;font-weight:700;border-radius:10px;">
                Reset Password & Sign in →
            </x-primary-button>
        </div>
    </form>

    {{-- Contact Support --}}
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #F3F4F6;">
        <x-contact-support variant="compact" class="text-center" />
    </div>
</x-guest-layout>
