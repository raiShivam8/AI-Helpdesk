<x-guest-layout>
    {{-- ── Form Heading ── --}}
    <div class="mb-4">
        <h2 style="font-size:19px;font-weight:800;color:#111827;line-height:1.2;">Security Check 🛡️</h2>
        <p style="font-size:13px;color:#6B7280;margin-top:3px;">
            This is a secure area of the application. Please confirm your password before continuing.
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" novalidate autocomplete="off">
        @csrf

        <!-- Password -->
        <div style="margin-bottom:18px;">
            <x-input-label for="password" :value="__('Password')" />
            <div style="position:relative; margin-top:4px;">
                <x-text-input id="password" class="block w-full" style="padding-right: 44px;" type="password" name="password" required autocomplete="current-password" :is-error="$errors->has('password')" placeholder="••••••••" />
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div style="margin-bottom:16px;">
            <x-primary-button style="width:100%;justify-content:center;padding:10px 0;font-size:14px;font-weight:700;border-radius:10px;">
                Confirm Password →
            </x-primary-button>
        </div>
    </form>

    {{-- Contact Support --}}
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #F3F4F6;">
        <x-contact-support variant="compact" class="text-center" />
    </div>
</x-guest-layout>
