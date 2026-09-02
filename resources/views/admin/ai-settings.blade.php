<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full min-w-0">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white truncate">AI & Gemini Configuration</h1>
                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full bg-indigo-50 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                        Admin Only
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate hidden sm:block">Manage Google Gemini AI credentials for Summarize, Polish Reply, and Auto-Resolution</p>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-5 flex items-center justify-between p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-800 dark:text-emerald-300 shadow-sm" role="alert">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="font-semibold">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-5 flex items-center justify-between p-4 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-800 dark:text-red-300 shadow-sm" role="alert">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span class="font-semibold">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-7xl mx-auto">
        {{-- ── Left Column: Configuration Form ── --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Status Card --}}
            <div class="card p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21l-1.813-5.096L2.091 14.09 7.187 13.28 9 8.187l1.813 5.096 5.096 1.813-5.096 1.813z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 3v4m-2-2h4" />
                    </svg>
                    Live AI Status
                </h2>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-200/80 dark:border-slate-700/80 gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="relative flex h-3.5 w-3.5 shrink-0">
                            @if($healthStatus === 'connected')
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500"></span>
                            @else
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-red-500"></span>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">
                                @if($healthStatus === 'connected')
                                    Google Gemini Connected & Ready
                                @else
                                    Gemini Needs Attention
                                @endif
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ $healthMessage }}
                            </p>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <span class="text-xs font-mono px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                            {{ $activeModel }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Settings Form --}}
            <div
                class="card p-6 border border-slate-200 dark:border-slate-800 shadow-sm"
                x-data="{
                    apiKey: '{{ old('gemini_api_key', '') }}',
                    model: '{{ old('gemini_model', $activeModel) }}',
                    isTesting: false,
                    testResult: null,
                    testSuccess: false,
                    showKey: false,
                    async testCurrentKey() {
                        if (!this.apiKey.trim()) {
                            this.testResult = 'Please enter an API key to test.';
                            this.testSuccess = false;
                            return;
                        }
                        this.isTesting = true;
                        this.testResult = null;
                        try {
                            const res = await fetch('{{ route('admin.ai-settings.test') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ key: this.apiKey, model: this.model })
                            });
                            const data = await res.json();
                            this.testSuccess = res.ok && data.success;
                            this.testResult = data.message || (res.ok ? 'Connection successful!' : 'Verification failed.');
                        } catch (e) {
                            this.testSuccess = false;
                            this.testResult = 'Network error: ' + e.message;
                        } finally {
                            this.isTesting = false;
                        }
                    }
                }"
            >
                <div class="mb-5 border-b border-slate-200 dark:border-slate-800 pb-4">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Configure Gemini Credentials</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Saving your API key here securely stores it in the database cache. It will activate instantly across all services without needing to rebuild or redeploy containers.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.ai-settings.update') }}" class="space-y-5">
                    @csrf

                    {{-- API Key Field --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="gemini_api_key" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                Gemini API Key <span class="text-red-500">*</span>
                            </label>
                            @if($maskedKey)
                                <span class="text-[11px] text-slate-400 font-mono">Current: {{ $maskedKey }}</span>
                            @endif
                        </div>

                        <div class="relative">
                            <input
                                :type="showKey ? 'text' : 'password'"
                                name="gemini_api_key"
                                id="gemini_api_key"
                                x-model="apiKey"
                                required
                                placeholder="AIzaSy..."
                                class="w-full text-sm font-mono rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 pr-20 text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors shadow-2xs"
                            />
                            <button
                                type="button"
                                @click="showKey = !showKey"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 px-2 py-1 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300"
                            >
                                <span x-text="showKey ? 'Hide' : 'Show'"></span>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1.5">Starts with <code>AIzaSy...</code> (created from Google AI Studio).</p>
                    </div>

                    {{-- Model Selection --}}
                    <div>
                        <label for="gemini_model" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                            Primary Model
                        </label>
                        <select
                            name="gemini_model"
                            id="gemini_model"
                            x-model="model"
                            class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors shadow-2xs"
                        >
                            <option value="gemini-2.5-flash">gemini-2.5-flash (Recommended: Fast & High Quality)</option>
                            <option value="gemini-2.0-flash">gemini-2.0-flash (Fast & Reliable)</option>
                            <option value="gemini-1.5-flash">gemini-1.5-flash (Stable Legacy Fallback)</option>
                            <option value="gemini-flash-latest">gemini-flash-latest (Always Latest Flash Alias)</option>
                        </select>
                    </div>

                    {{-- Test Result Alert Banner --}}
                    <div
                        x-show="testResult"
                        x-cloak
                        :class="testSuccess ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300' : 'bg-red-50 border-red-200 text-red-800 dark:bg-red-950/40 dark:border-red-800 dark:text-red-300'"
                        class="p-3.5 rounded-xl border text-xs font-medium flex items-center justify-between"
                    >
                        <span x-text="testResult"></span>
                        <button type="button" @click="testResult = null" class="font-bold text-sm ml-2">&times;</button>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <button
                            type="button"
                            @click="testCurrentKey()"
                            :disabled="isTesting || !apiKey.trim()"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 disabled:opacity-50 disabled:cursor-not-allowed transition duration-150"
                        >
                            <svg x-show="isTesting" x-cloak class="animate-spin h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="isTesting ? 'Verifying with Google...' : 'Test Connection'"></span>
                        </button>

                        <button
                            type="submit"
                            :disabled="!apiKey.trim()"
                            class="btn-primary"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Verify & Save Key</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Right Column: Instructions & Help ── --}}
        <div class="space-y-6">
            <div class="card p-6 border border-slate-200 dark:border-slate-800 shadow-sm bg-gradient-to-br from-indigo-50/40 dark:from-indigo-950/20 to-purple-50/40 dark:to-purple-950/20">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    How to Get Your Key
                </h3>

                <ol class="list-decimal pl-4 space-y-2 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    <li>
                        Go to <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 font-bold underline">Google AI Studio</a>.
                    </li>
                    <li>Sign in with your standard Google account.</li>
                    <li>Click <strong>"Create API key"</strong>.</li>
                    <li>Copy the key (format begins with <code>AIzaSy...</code>).</li>
                    <li>Paste it into the form on the left and click <strong>Verify & Save Key</strong>.</li>
                </ol>

                <div class="mt-4 p-3 rounded-xl bg-indigo-100/60 dark:bg-indigo-900/40 border border-indigo-200 dark:border-indigo-800 text-[11px] text-indigo-900 dark:text-indigo-200">
                    💡 <strong>Instant Activation:</strong> Once saved here, all tickets on your live site will immediately support <strong>Summarize</strong> and <strong>Polish Reply</strong> without needing server restarts!
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
