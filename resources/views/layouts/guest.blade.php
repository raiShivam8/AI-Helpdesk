<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'AI Helpdesk') }} — Agent Portal</title>
        <meta name="description" content="AI-powered helpdesk support portal. Sign in to manage tickets and customer support.">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800&display=swap" rel="stylesheet">

        <!-- Scripts & Styles (Relative Build Assets) -->
        @php
            $manifestPath = public_path('build/manifest.json');
            $cssFile = '/build/assets/app.css';
            $jsFile = '/build/assets/app.js';
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                if (isset($manifest['resources/css/app.css']['file'])) {
                    $cssFile = '/build/' . $manifest['resources/css/app.css']['file'];
                }
                if (isset($manifest['resources/js/app.js']['file'])) {
                    $jsFile = '/build/' . $manifest['resources/js/app.js']['file'];
                }
            }
        @endphp
        <link rel="stylesheet" href="{{ asset($cssFile) }}">
        <script type="module" src="{{ asset($jsFile) }}"></script>

        <style>
            *, *::before, *::after { box-sizing: border-box; }
            html, body { height: 100vh; max-height: 100vh; margin: 0; padding: 0; font-family: 'Inter', sans-serif; overflow: hidden; }

            /* ── Full-screen two-column layout ── */
            .auth-wrapper {
                display: flex;
                height: 100vh;
                width: 100%;
                overflow: hidden;
            }

            /* ── LEFT BRANDING PANEL ── */
            .auth-left {
                flex: 0 0 48%;
                background: linear-gradient(145deg, #3730A3 0%, #4F46E5 35%, #7C3AED 70%, #6D28D9 100%);
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: flex-start;
                padding: 40px 52px;
                position: relative;
                overflow: hidden;
                height: 100vh;
            }

            /* Decorative blobs on left panel */
            .auth-left::before {
                content: '';
                position: absolute;
                top: -120px; right: -120px;
                width: 380px; height: 380px;
                background: rgba(255,255,255,0.06);
                border-radius: 50%;
                pointer-events: none;
            }
            .auth-left::after {
                content: '';
                position: absolute;
                bottom: -100px; left: -80px;
                width: 300px; height: 300px;
                background: rgba(255,255,255,0.05);
                border-radius: 50%;
                pointer-events: none;
            }
            .auth-left-inner { position: relative; z-index: 1; width: 100%; }

            /* ── RIGHT FORM PANEL ── */
            .auth-right {
                flex: 1;
                background: #F8F9FF;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 24px 20px;
                overflow: hidden;
                height: 100vh;
            }

            .auth-card {
                width: 100%;
                max-width: 420px;
                background: #FFFFFF;
                border-radius: 20px;
                padding: 28px 32px 24px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 10px 40px rgba(99,102,241,0.10);
                border: 1px solid rgba(99,102,241,0.10);
            }

            /* ── Badge ── */
            .ai-badge {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                background: rgba(255,255,255,0.15);
                border: 1px solid rgba(255,255,255,0.25);
                color: #fff;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.07em;
                text-transform: uppercase;
                padding: 5px 13px;
                border-radius: 100px;
                margin-bottom: 32px;
            }
            .ai-badge-dot {
                width: 7px; height: 7px;
                background: #34D399;
                border-radius: 50%;
                animation: blink 1.6s ease-in-out infinite;
            }
            @keyframes blink {
                0%, 100% { opacity: 1; } 50% { opacity: 0.4; }
            }

            /* ── Feature rows ── */
            .feature-row {
                display: flex;
                align-items: center;
                gap: 13px;
                padding: 14px 0;
                border-bottom: 1px solid rgba(255,255,255,0.10);
            }
            .feature-row:last-child { border-bottom: none; }
            .feature-icon {
                width: 38px; height: 38px; flex-shrink: 0;
                background: rgba(255,255,255,0.12);
                border-radius: 10px;
                display: flex; align-items: center; justify-content: center;
            }
            .feature-icon svg { width: 17px; height: 17px; stroke: #fff; }
            .feature-text-title { font-size: 13.5px; font-weight: 600; color: #fff; line-height: 1.3; }
            .feature-text-desc  { font-size: 12px; color: rgba(255,255,255,0.60); margin-top: 2px; }

            /* ── Stats row ── */
            .stats-row {
                display: flex;
                gap: 24px;
                margin-top: 40px;
                padding-top: 28px;
                border-top: 1px solid rgba(255,255,255,0.12);
            }
            .stat-item { display: flex; flex-direction: column; gap: 3px; }
            .stat-num  { font-size: 22px; font-weight: 800; color: #fff; }
            .stat-lbl  { font-size: 11.5px; color: rgba(255,255,255,0.55); font-weight: 500; }

            /* ── Right panel heading ── */
            .form-logo-wrap {
                display: flex; align-items: center; gap: 11px;
                margin-bottom: 14px;
            }
            .form-logo-wrap img, .form-logo-wrap svg { width: 36px; height: 36px; border-radius: 10px; }
            .form-brand-name { font-size: 16px; font-weight: 700; color: #1E1B4B; }
            .form-brand-sub  { font-size: 11.5px; color: #6B7280; font-weight: 500; }

            .form-heading { font-size: 20px; font-weight: 800; color: #111827; line-height: 1.25; }
            .form-sub     { font-size: 13px; color: #6B7280; margin-top: 4px; line-height: 1.4; }

            .divider { height: 1px; background: #F3F4F6; margin: 14px 0; }

            /* Responsive: stack on small screens */
            @media (max-width: 800px) {
                .auth-left { display: none; }
                .auth-right { padding: 32px 20px; background: #EEF2FF; }
                .auth-card { padding: 32px 28px; }
            }
        </style>
    </head>
    <body>
        <div class="auth-wrapper">

            {{-- ══════════════════════════════════════
                 LEFT — Branding Panel
            ══════════════════════════════════════ --}}
            <div class="auth-left">
                <div class="auth-left-inner">

                    {{-- Badge --}}
                    <div class="ai-badge">
                        <span class="ai-badge-dot"></span>
                        AI Support Active
                    </div>

                    {{-- Logo + Name --}}
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;">
                        <a href="/" style="display:flex;align-items:center;gap:14px;text-decoration:none;">
                            <div style="background:rgba(255,255,255,0.15);padding:8px;border-radius:16px;border:1px solid rgba(255,255,255,0.2);">
                                <x-application-logo class="w-10 h-10 rounded-xl" />
                            </div>
                        </a>
                        <div>
                            <div style="font-size:20px;font-weight:800;color:#fff;">AI Helpdesk</div>
                            <div style="font-size:12px;color:rgba(255,255,255,0.60);font-weight:500;margin-top:2px;">Intelligent Support Portal</div>
                        </div>
                    </div>

                    {{-- Tagline --}}
                    <p style="font-size:27px;font-weight:800;color:#fff;line-height:1.30;max-width:340px;margin:0 0 10px;">
                        Resolve support tickets&nbsp;10× faster with AI
                    </p>
                    <p style="font-size:14px;color:rgba(255,255,255,0.65);line-height:1.65;max-width:340px;margin:0 0 36px;">
                        Every customer email becomes a ticket automatically. Gemini AI reads, understands, and resolves common issues — before your team even sees them.
                    </p>

                    {{-- Features --}}
                    <div style="width:100%;">
                        <div class="feature-row">
                            <div class="feature-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            </div>
                            <div>
                                <div class="feature-text-title">AI Auto-Resolution</div>
                                <div class="feature-text-desc">Gemini AI resolves common tickets instantly from your Knowledge Base</div>
                            </div>
                        </div>
                        <div class="feature-row">
                            <div class="feature-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </div>
                            <div>
                                <div class="feature-text-title">Email → Ticket in &lt;10 Seconds</div>
                                <div class="feature-text-desc">IMAP sync converts customer emails into dashboard tickets instantly</div>
                            </div>
                        </div>
                        <div class="feature-row">
                            <div class="feature-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            </div>
                            <div>
                                <div class="feature-text-title">Real-time Dashboard</div>
                                <div class="feature-text-desc">Monitor open, resolved, and AI-handled tickets from one place</div>
                            </div>
                        </div>
                    </div>

                    {{-- Stats --}}
                    <div class="stats-row">
                        <div class="stat-item">
                            <span class="stat-num">10s</span>
                            <span class="stat-lbl">Email → Ticket</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-num">AI</span>
                            <span class="stat-lbl">Auto-Resolve</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-num">24/7</span>
                            <span class="stat-lbl">Always On</span>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ══════════════════════════════════════
                 RIGHT — Auth Form Panel
            ══════════════════════════════════════ --}}
            <div class="auth-right">

                <div class="auth-card">

                    {{-- Mini logo for right panel --}}
                    <div class="form-logo-wrap">
                        <x-application-logo class="w-9 h-9 rounded-xl" />
                        <div>
                            <div class="form-brand-name">AI Helpdesk</div>
                            <div class="form-brand-sub">Agent Portal</div>
                        </div>
                    </div>

                    <div class="divider"></div>

                    {{ $slot }}

                </div>

                <p style="margin-top:20px;font-size:12px;color:#9CA3AF;text-align:center;">
                    © {{ date('Y') }} AI Helpdesk &nbsp;·&nbsp; Powered by Google Gemini AI
                </p>
            </div>

        </div>
    </body>
</html>
