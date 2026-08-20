<x-guest-layout>
    {{-- ── Form Heading ── --}}
    <div class="mb-4">
        <h2 style="font-size:19px;font-weight:800;color:#111827;line-height:1.2;">Verify your email ✉️</h2>
        <p style="font-size:13px;color:#6B7280;margin-top:3px;">
            Thanks for signing up! Before getting started, please verify your email address by clicking on the link we just emailed to you.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs font-semibold text-emerald-800">
            A new verification link has been sent to your email address.
        </div>
    @endif

    <div class="mt-5 flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button style="width:100%;justify-content:center;padding:10px 0;font-size:14px;font-weight:700;border-radius:10px;">
                Resend Verification Email →
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-xs text-slate-500 hover:text-slate-900 underline font-medium">
                Log Out
            </button>
        </form>
    </div>

    {{-- Contact Support --}}
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #F3F4F6;">
        <x-contact-support variant="compact" class="text-center" />
    </div>
</x-guest-layout>
