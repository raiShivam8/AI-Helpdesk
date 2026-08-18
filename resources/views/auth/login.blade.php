<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-3" :status="session('status')" />

    {{-- ── Form Heading ── --}}
    <div class="mb-4">
        <h2 style="font-size:19px;font-weight:800;color:#111827;line-height:1.2;">Welcome back 👋</h2>
        <p style="font-size:13px;color:#6B7280;margin-top:3px;">Sign in to manage your support tickets</p>
    </div>

    {{-- ── Quick Access Buttons (Admin & Agent) ── --}}
    <div style="margin-bottom:14px;padding:10px 12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748B;margin-bottom:6px;display:flex;align-items:center;gap:5px;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Quick Access Demo Login
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <button type="button"
                    onclick="quickLogin('{{ config('app.admin_email', 'admin@gmail.com') }}', '{{ config('app.admin_password', 'password123') }}')"
                    style="display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 10px;background:linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);border:1px solid #C7D2FE;border-radius:9px;color:#3730A3;font-weight:700;font-size:13px;cursor:pointer;transition:all 0.2s ease;box-shadow:0 1px 2px rgba(0,0,0,0.04);"
                    onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(79, 70, 229, 0.15)';"
                    onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 1px 2px rgba(0,0,0,0.04)';">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Admin
            </button>

            <button type="button"
                    onclick="quickLogin('agent@gmail.com', 'password123')"
                    style="display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 10px;background:linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);border:1px solid #A7F3D0;border-radius:9px;color:#065F46;font-weight:700;font-size:13px;cursor:pointer;transition:all 0.2s ease;box-shadow:0 1px 2px rgba(0,0,0,0.04);"
                    onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.15)';"
                    onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 1px 2px rgba(0,0,0,0.04)';">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Agent
            </button>
        </div>
    </div>

    <script>
        function quickLogin(email, password) {
            const emailEl = document.getElementById('email');
            const passEl = document.getElementById('password');
            if (emailEl && passEl) {
                emailEl.value = email;
                passEl.value = password;
                const form = emailEl.closest('form');
                if (form) {
                    form.submit();
                }
            }
        }
    </script>

    <form method="POST" action="{{ route('login') }}" novalidate autocomplete="off">
        @csrf

        {{-- Email --}}
        <div style="margin-bottom:14px;">
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
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        {{-- Password --}}
        <div style="margin-bottom:6px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                       style="font-size:12px;color:#4F46E5;font-weight:500;text-decoration:none;"
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

            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>


        {{-- Remember Me --}}
        <div style="margin:12px 0 16px;">
            <label for="remember_me" style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                <input id="remember_me" type="checkbox"
                       style="width:15px;height:15px;accent-color:#4F46E5;border-radius:4px;"
                       name="remember">
                <span style="font-size:13px;color:#374151;font-weight:500;">Remember me for 30 days</span>
            </label>
        </div>

        {{-- Submit --}}
        <x-primary-button style="width:100%;justify-content:center;padding:10px 0;font-size:14px;font-weight:700;border-radius:10px;letter-spacing:0.01em;">
            Sign in to Dashboard →
        </x-primary-button>

    </form>

    {{-- Contact Support --}}
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid #F3F4F6;">
        <x-contact-support variant="compact" class="text-center" />
    </div>

</x-guest-layout>
