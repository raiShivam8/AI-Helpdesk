<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{-- ── Form Heading ── --}}
    <div class="mb-6">
        <h2 style="font-size:20px;font-weight:800;color:#111827;line-height:1.25;">Welcome back 👋</h2>
        <p style="font-size:13.5px;color:#6B7280;margin-top:5px;">Sign in to manage your support tickets</p>
    </div>

    <form method="POST" action="{{ route('login') }}" novalidate autocomplete="off">
        @csrf

        {{-- Email --}}
        <div style="margin-bottom:18px;">
            <x-input-label for="email" :value="__('Email address')" />
            <x-text-input
                id="email"
                class="block mt-1 w-full"
                type="email"
                name="email"
                value=""
                required autofocus
                autocomplete="new-password"
                :is-error="$errors->has('email')"
                placeholder="you@example.com"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- Password --}}
        <div style="margin-bottom:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                       style="font-size:12.5px;color:#4F46E5;font-weight:500;text-decoration:none;"
                       onmouseover="this.style.textDecoration='underline'"
                       onmouseout="this.style.textDecoration='none'">
                        Forgot password?
                    </a>
                @endif
            </div>

            {{-- Password input + eye toggle wrapper --}}
            <div style="position:relative; margin-top:4px;">
                <x-text-input
                    id="password"
                    class="block w-full"
                    style="padding-right: 44px;"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    :is-error="$errors->has('password')"
                    placeholder="••••••••"
                />

                {{-- Eye toggle button --}}
                <button
                    type="button"
                    id="toggle-password"
                    onclick="
                        const inp = document.getElementById('password');
                        const isHidden = inp.type === 'password';
                        inp.type = isHidden ? 'text' : 'password';
                        document.getElementById('eye-icon-show').style.display = isHidden ? 'none'  : 'block';
                        document.getElementById('eye-icon-hide').style.display = isHidden ? 'block' : 'none';
                    "
                    style="
                        position: absolute;
                        right: 12px;
                        top: 50%;
                        transform: translateY(-50%);
                        background: none;
                        border: none;
                        cursor: pointer;
                        padding: 4px;
                        color: #94A3B8;
                        display: flex;
                        align-items: center;
                        transition: color 0.15s ease;
                    "
                    onmouseover="this.style.color='#4F46E5'"
                    onmouseout="this.style.color='#94A3B8'"
                    aria-label="Toggle password visibility"
                    title="Show / hide password"
                >
                    {{-- Eye (visible) icon — shown by default --}}
                    <svg id="eye-icon-show" xmlns="http://www.w3.org/2000/svg"
                         width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"
                         style="display:block;">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>

                    {{-- Eye-off (hidden) icon — hidden by default --}}
                    <svg id="eye-icon-hide" xmlns="http://www.w3.org/2000/svg"
                         width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"
                         style="display:none;">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>


        {{-- Remember Me --}}
        <div style="margin:16px 0 22px;">
            <label for="remember_me" style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                <input id="remember_me" type="checkbox"
                       style="width:15px;height:15px;accent-color:#4F46E5;border-radius:4px;"
                       name="remember">
                <span style="font-size:13.5px;color:#374151;font-weight:500;">Remember me for 30 days</span>
            </label>
        </div>

        {{-- Submit --}}
        <x-primary-button style="width:100%;justify-content:center;padding:11px 0;font-size:14.5px;font-weight:700;border-radius:10px;letter-spacing:0.01em;">
            Sign in to Dashboard →
        </x-primary-button>

    </form>

    {{-- Contact Support --}}
    <div style="margin-top:22px;padding-top:20px;border-top:1px solid #F3F4F6;">
        <x-contact-support variant="compact" class="text-center" />
    </div>

</x-guest-layout>
