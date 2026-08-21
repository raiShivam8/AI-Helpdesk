<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'AI Helpdesk') }} — Support Portal</title>
        <meta name="description" content="AI-powered helpdesk ticket management system">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Chart.js CDN for Dashboard Charts -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <!-- ⚡ Dark mode: apply class BEFORE paint to prevent flash -->
        <script>
            (function () {
                const stored = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (stored === 'dark' || (!stored && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <!-- Scripts & Styles (Relative Build Assets) -->
        @php
            $manifestPath = public_path('build/manifest.json');
            $cssFile = 'build/assets/app.css';
            $jsFile = 'build/assets/app.js';
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                if (isset($manifest['resources/css/app.css']['file'])) {
                    $cssFile = 'build/' . $manifest['resources/css/app.css']['file'];
                }
                if (isset($manifest['resources/js/app.js']['file'])) {
                    $jsFile = 'build/' . $manifest['resources/js/app.js']['file'];
                }
            }
        @endphp
        <link rel="stylesheet" href="{{ asset($cssFile) }}">
        <script type="module" src="{{ asset($jsFile) }}"></script>
        <style>
            .notif-scrollbar::-webkit-scrollbar {
                width: 6px;
            }
            .notif-scrollbar::-webkit-scrollbar-track {
                background: rgba(0, 0, 0, 0.04);
            }
            .notif-scrollbar::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 9999px;
            }
            .dark .notif-scrollbar::-webkit-scrollbar-thumb {
                background: #475569;
            }
            .notif-scrollbar::-webkit-scrollbar-thumb:hover {
                background: #6366f1;
            }

            .notif-card {
                background-color: #ffffff !important;
                color: #0f172a !important;
            }
            .dark .notif-card {
                background-color: #111827 !important;
                color: #f8fafc !important;
            }
            .notif-header {
                background-color: #f8fafc !important;
            }
            .dark .notif-header {
                background-color: #1f2937 !important;
            }
            .notif-item-read {
                background-color: #ffffff !important;
            }
            .dark .notif-item-read {
                background-color: #111827 !important;
            }
            .notif-item-unread {
                background-color: #f0fdf4 !important;
            }
            .dark .notif-item-unread {
                background-color: #14243b !important;
            }
            .notif-title-text {
                color: #0f172a !important;
            }
            .dark .notif-title-text {
                color: #f8fafc !important;
            }
            .notif-body-text {
                color: #334155 !important;
            }
            .dark .notif-body-text {
                color: #cbd5e1 !important;
            }
            .notif-time-text {
                color: #64748b !important;
            }
            .dark .notif-time-text {
                color: #94a3b8 !important;
            }
            .notif-feed-container {
                height: 86px !important;
                max-height: 86px !important;
                overflow-y: auto !important;
            }
        </style>
    </head>
    <body class="font-sans antialiased transition-colors duration-300 min-h-screen overflow-y-auto"
          style="background-color: var(--bg-page); color: var(--text-primary);"
          x-data="{
              sidebarOpen: false,
              open: false,
              tab: 'all',
              notifications: [],
              unreadCount: 0,
              loading: false,
              soundEnabled: true,
              lastUnreadCount: 0,
              toast: { show: false, title: '', message: '', link: '' },
              showToast(title, message, link) {
                  this.toast.title = title;
                  this.toast.message = message;
                  this.toast.link = link;
                  this.toast.show = true;
                  this.playChime();
                  setTimeout(() => { this.toast.show = false; }, 8000);
              },
              playChime() {
                  if (!this.soundEnabled) return;
                  try {
                      const ctx = new (window.AudioContext || window.webkitAudioContext)();
                      const osc = ctx.createOscillator();
                      const gain = ctx.createGain();
                      osc.type = 'sine';
                      osc.frequency.setValueAtTime(587.33, ctx.currentTime);
                      osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15);
                      gain.gain.setValueAtTime(0.08, ctx.currentTime);
                      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
                      osc.connect(gain);
                      gain.connect(ctx.destination);
                      osc.start();
                      osc.stop(ctx.currentTime + 0.3);
                  } catch (e) {}
              },
              fetchNotifications() {
                  this.loading = true;
                  const controller = new AbortController();
                  const timeoutId = setTimeout(() => controller.abort(), 8000);
                  fetch('{{ route('notifications.index') }}', { signal: controller.signal })
                      .then(res => {
                          clearTimeout(timeoutId);
                          if (!res.ok) throw new Error('Network error');
                          return res.json();
                      })
                      .then(data => {
                          const newCount = data.unread_count || 0;
                          if (this.lastUnreadCount > 0 && newCount > this.lastUnreadCount) {
                              const latestNotif = (data.notifications || []).find(n => !n.read_at) || (data.notifications || [])[0];
                              if (latestNotif) {
                                  this.showToast(latestNotif.title, latestNotif.message, latestNotif.link);
                              } else {
                                  this.playChime();
                              }
                          }
                          this.lastUnreadCount = newCount;
                          this.notifications = data.notifications || [];
                          this.unreadCount = newCount;
                          this.loading = false;
                      })
                      .catch(err => {
                          clearTimeout(timeoutId);
                          this.loading = false;
                      });
              },
              autoSyncImap() {
                  const controller = new AbortController();
                  const timeoutId = setTimeout(() => controller.abort(), 12000);
                  fetch('{{ route('tickets.sync-emails') }}', {
                      method: 'POST',
                      headers: {
                          'X-CSRF-TOKEN': '{{ csrf_token() }}',
                          'Accept': 'application/json',
                          'X-Requested-With': 'XMLHttpRequest'
                      },
                      signal: controller.signal
                  })
                  .then(res => {
                      clearTimeout(timeoutId);
                      if (!res.ok) throw new Error('Sync error');
                      return res.json();
                  })
                  .then(data => {
                      if (data.success && data.count > 0) {
                          this.fetchNotifications();
                          this.showToast('📩 New Ticket Received!', `Imported ${data.count} new customer support ticket(s).`, '{{ route('dashboard') }}');
                      }
                  })
                  .catch(err => {
                      clearTimeout(timeoutId);
                  });
              },
              get filteredNotifications() {
                  if (this.tab === 'unread') {
                      return this.notifications.filter(n => !n.read_at);
                  }
                  return this.notifications;
              },
              markRead(id, link, event) {
                  if (event) event.stopPropagation();
                  fetch('/notifications/' + id + '/read', {
                      method: 'POST',
                      headers: {
                          'X-CSRF-TOKEN': '{{ csrf_token() }}',
                          'Content-Type': 'application/json',
                          'Accept': 'application/json'
                      }
                  }).then(() => {
                      this.fetchNotifications();
                      if (link) window.location.href = link;
                  }).catch(() => {});
              },
              markAllRead() {
                  fetch('{{ route('notifications.read-all') }}', {
                      method: 'POST',
                      headers: {
                          'X-CSRF-TOKEN': '{{ csrf_token() }}',
                          'Content-Type': 'application/json',
                          'Accept': 'application/json'
                      }
                  }).then(() => {
                      this.fetchNotifications();
                  }).catch(() => {});
              }
          }"
          x-init="fetchNotifications(); setInterval(() => fetchNotifications(), 30000);">

        <div class="flex min-h-screen relative overflow-x-hidden overflow-y-auto">

            {{-- ════════ SIDEBAR ════════ --}}
            @include('layouts.navigation')

            {{-- ════════ MAIN AREA ════════ --}}
            <div class="flex-1 flex flex-col min-w-0 w-full lg:ml-[260px] transition-all duration-300">

                {{-- Fixed/Sticky top header bar --}}
                <header class="sticky top-0 z-30 backdrop-blur-md border-b h-16 flex items-center px-4 sm:px-6 gap-3 shrink-0 bg-white/95 dark:bg-[#161B27]/95 border-slate-200/80 dark:border-slate-800 shadow-xs">

                    {{-- Mobile Hamburger Menu Button --}}
                    <button
                        @click="sidebarOpen = !sidebarOpen"
                        type="button"
                        class="lg:hidden p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shrink-0"
                        aria-label="Toggle navigation menu"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    @if (isset($header))
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 flex-1 min-w-0">
                            {{ $header }}
                        </div>
                    @else
                        {{-- Breadcrumb matching Image 1: headset icon > Dashboard --}}
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 flex-1 min-w-0">
                            <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z" />
                            </svg>
                            <svg class="w-3.5 h-3.5 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                            <span class="text-slate-800 dark:text-slate-200 font-bold truncate">Dashboard</span>
                        </div>
                    @endif

                    {{-- Right Controls matching Image 1 --}}
                    <div class="flex items-center gap-2 sm:gap-3">
                        @auth
                        {{-- User Pill --}}
                        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-slate-50 dark:bg-slate-800/80 rounded-xl border border-slate-200/70 dark:border-slate-700/60">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ Auth::user()?->name }}</span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300">
                                {{ Auth::user()?->role?->value ?? 'User' }}
                            </span>
                        </div>
                        @endauth

                        @if (Auth::user()?->isAdmin())
                        <a href="{{ route('dashboard', ['agent_id' => Auth::id()]) }}"
                           class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-xs font-bold rounded-xl border border-indigo-200 dark:border-indigo-700/60 shadow-2xs transition-colors shrink-0"
                           title="View Agent Dashboard">
                            <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="hidden sm:inline">Agent Dashboard</span>
                        </a>
                        <a href="{{ route('users.index') }}"
                           class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xs transition-colors shrink-0"
                           title="Admin Panel">
                            <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="hidden sm:inline">Admin Panel</span>
                        </a>
                        @endif

                        {{-- ═══ Production Level Real-Time Notification Dropdown ═══ --}}
                        @auth
                        <div class="relative">

                            {{-- Bell Trigger Button --}}
                            <button @click="open = !open" type="button" class="relative p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all shrink-0" aria-label="View notifications">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <template x-if="unreadCount > 0">
                                    <span class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                        <span class="relative inline-flex h-4 w-4 items-center justify-center rounded-full bg-rose-600 text-[9px] font-extrabold text-white shadow-xs" x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
                                    </span>
                                </template>
                            </button>

                            {{-- Dropdown Container --}}
                            <div x-show="open"
                                 @click.outside="open = false"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95 translate-y-[-8px]"
                                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 scale-95 translate-y-[-8px]"
                                 class="notif-card absolute right-0 top-full mt-2 w-[calc(100vw-2rem)] max-w-sm sm:w-96 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl z-50 overflow-hidden"
                                 x-cloak>

                                {{-- Header & Action Controls --}}
                                <div class="notif-header px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between shrink-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-xs notif-title-text uppercase tracking-wider">Notifications</span>
                                        <template x-if="unreadCount > 0">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500 text-white shadow-2xs" x-text="unreadCount + ' unread'"></span>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <template x-if="unreadCount > 0">
                                            <button @click="markAllRead()" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline transition-colors">Mark all read</button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Filter Tabs (All vs Unread) --}}
                                <div class="px-4 py-2 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2">
                                    <button @click="tab = 'all'" :class="tab === 'all' ? 'bg-indigo-600 text-white shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-300 font-semibold hover:bg-slate-200 dark:hover:bg-slate-800'" class="px-3 py-1 text-xs rounded-xl transition-all">
                                        All (<span x-text="notifications.length"></span>)
                                    </button>
                                    <button @click="tab = 'unread'" :class="tab === 'unread' ? 'bg-indigo-600 text-white shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-300 font-semibold hover:bg-slate-200 dark:hover:bg-slate-800'" class="px-3 py-1 text-xs rounded-xl transition-all">
                                        Unread (<span x-text="unreadCount"></span>)
                                    </button>
                                </div>

                                {{-- Notification Scrollable List Container (Fixed Single Ticket Height 86px + Vertical Scrollbar) --}}
                                <div class="notif-feed-container divide-y divide-slate-100 dark:divide-slate-800 notif-scrollbar" style="height: 86px !important; max-height: 86px !important; overflow-y: auto !important;">
                                    <template x-if="filteredNotifications.length === 0">
                                        <div class="p-8 text-center text-xs font-medium notif-body-text">
                                            <svg class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                            </svg>
                                            No notifications found
                                        </div>
                                    </template>
                                    <template x-for="n in filteredNotifications" :key="n.id">
                                        <div @click="markRead(n.id, n.link, $event)"
                                             class="p-3.5 cursor-pointer transition-all border-b border-slate-100 dark:border-slate-800/80 flex items-start gap-3 group"
                                             :class="!n.read_at ? 'notif-item-unread hover:opacity-90' : 'notif-item-read hover:bg-slate-50 dark:hover:bg-slate-800/60'">

                                            {{-- Type-based Badge Icon --}}
                                            <div class="p-2 rounded-xl shrink-0 mt-0.5 shadow-2xs"
                                                 :class="{
                                                     'bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300': n.type === 'ticket_created',
                                                     'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300': n.type === 'ticket_reply',
                                                     'bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300': n.type === 'ticket_transfer',
                                                     'bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300': n.type === 'ai_resolved'
                                                 }">
                                                <template x-if="n.type === 'ticket_created'">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5h14a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-3a2 2 0 000-4V7a2 2 0 012-2z" /></svg>
                                                </template>
                                                <template x-if="n.type === 'ticket_reply'">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                                </template>
                                                <template x-if="n.type === 'ticket_transfer'">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                                </template>
                                                <template x-if="n.type === 'ai_resolved' || (n.type !== 'ticket_created' && n.type !== 'ticket_reply' && n.type !== 'ticket_transfer')">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                                </template>
                                            </div>

                                            {{-- Content Body --}}
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-start justify-between gap-2">
                                                    <span class="font-bold text-xs leading-snug notif-title-text group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors"
                                                          x-text="n.title"></span>
                                                    <template x-if="!n.read_at">
                                                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0 mt-0.5 shadow-2xs animate-pulse"></span>
                                                    </template>
                                                </div>
                                                <p class="text-xs mt-1 leading-relaxed notif-body-text"
                                                   x-text="n.message"></p>
                                                <div class="flex items-center justify-between mt-2">
                                                    <span class="text-[10px] font-semibold notif-time-text"
                                                          x-text="n.created_at_human || new Date(n.created_at).toLocaleString()"></span>
                                                    <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300"
                                                          x-text="n.type_label || 'Info'"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        @endauth

                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xs transition-colors shrink-0"
                                    title="Sign out">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                <span class="hidden sm:inline">Sign out</span>
                            </button>
                        </form>

                        {{-- Dark / Light Mode Toggle --}}
                        <button
                            id="theme-toggle-btn"
                            class="theme-toggle"
                            title="Toggle dark / light mode"
                            aria-label="Toggle dark mode"
                            onclick="
                                const html = document.documentElement;
                                if (html.classList.contains('dark')) {
                                    html.classList.remove('dark');
                                    localStorage.setItem('theme', 'light');
                                } else {
                                    html.classList.add('dark');
                                    localStorage.setItem('theme', 'dark');
                                }
                            "
                        >
                            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5"/>
                                <line x1="12" y1="1" x2="12" y2="3"/>
                                <line x1="12" y1="21" x2="12" y2="23"/>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                                <line x1="1" y1="12" x2="3" y2="12"/>
                                <line x1="21" y1="12" x2="23" y2="12"/>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                            </svg>
                            <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                            </svg>
                        </button>
                    </div>

                </header>

                {{-- Page Content --}}
                <main class="flex-1 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>

            </div>
        </div>

        {{-- ═══ Floating Real-Time Ticket Notification Toast Popup ═══ --}}
        <div x-show="toast.show"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:translate-x-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0 scale-100"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="opacity-100 translate-y-0 sm:translate-x-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:translate-x-4 scale-95"
             class="fixed bottom-4 right-4 left-4 sm:left-auto sm:right-5 sm:bottom-5 z-50 max-w-md w-auto bg-white dark:bg-slate-900 border-2 border-indigo-500 dark:border-indigo-400 rounded-2xl shadow-2xl p-4 backdrop-blur-xl flex items-start gap-3.5 ring-4 ring-indigo-500/10"
             x-cloak
             style="display: none;">
            <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-md animate-bounce">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">📩 New Ticket Received!</span>
                    <button @click="toast.show = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white mt-1 truncate" x-text="toast.title"></h4>
                <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5 line-clamp-2" x-text="toast.message"></p>
                <div class="mt-2.5 flex items-center gap-2">
                    <a :href="toast.link || '#'" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-xs transition-colors">
                        View Ticket →
                    </a>
                    <button @click="toast.show = false" class="px-2.5 py-1 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                        Dismiss
                    </button>
                </div>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
