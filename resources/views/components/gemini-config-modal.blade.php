@props(['id' => 'gemini-config-modal'])

<div
    x-data="{
        isOpen: false,
        apiKey: '',
        selectedModel: 'gemini-3.7-flash',
        showKey: false,
        isLoading: false,
        isTesting: false,
        statusMessage: null,
        statusType: null, // 'success' | 'error' | 'info'
        currentMaskedKey: null,
        hasKey: false,
        models: {
            'gemini-3.7-flash': 'Gemini 3.7 Flash (Fast, Recommended)',
            'gemini-3.8-flash': 'Gemini 3.8 Flash (Latest Preview)',
            'gemini-3.5-flash': 'Gemini 3.5 Flash (Stable)',
            'gemini-flash-latest': 'Gemini Flash Latest',
            'gemini-pro-latest': 'Gemini Pro Latest'
        },

        init() {
            this.fetchConfig();
            window.addEventListener('open-modal', (e) => {
                if (e.detail === '{{ $id }}' || e.detail === 'gemini-config-modal') {
                    this.open();
                }
            });
            window.addEventListener('open-gemini-modal', () => {
                this.open();
            });
        },

        open() {
            this.isOpen = true;
            this.statusMessage = null;
            this.fetchConfig();
            document.body.classList.add('overflow-y-hidden');
        },

        close() {
            this.isOpen = false;
            document.body.classList.remove('overflow-y-hidden');
        },

        async fetchConfig() {
            try {
                const res = await fetch('{{ route('ai.config') }}', {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.hasKey = data.has_key;
                    this.currentMaskedKey = data.masked_key;
                    if (data.active_model) {
                        this.selectedModel = data.active_model;
                    }
                    if (data.supported_models) {
                        this.models = data.supported_models;
                    }
                }
            } catch (err) {
                console.error('Failed to load Gemini config:', err);
            }
        },

        async testKey() {
            if (this.isTesting) return;
            this.isTesting = true;
            this.statusMessage = 'Testing connection with Google Gemini...';
            this.statusType = 'info';

            const testKeyVal = this.apiKey.trim() || 'CURRENT';

            try {
                const res = await fetch('{{ route('ai.test-key') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        key: testKeyVal === 'CURRENT' ? '' : testKeyVal,
                        model: this.selectedModel
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.statusType = 'success';
                    this.statusMessage = data.message || 'Google API connection verified successfully!';
                } else {
                    this.statusType = 'error';
                    this.statusMessage = data.message || data.error || 'Verification failed. Please check the API key.';
                }
            } catch (err) {
                this.statusType = 'error';
                this.statusMessage = 'Network error testing API key: ' + err.message;
            } finally {
                this.isTesting = false;
            }
        },

        async saveConfig() {
            if (this.isLoading) return;
            
            const keyToSave = this.apiKey.trim();
            if (!keyToSave && !this.hasKey) {
                this.statusType = 'error';
                this.statusMessage = 'Please enter a valid Gemini API key.';
                return;
            }

            this.isLoading = true;
            this.statusMessage = 'Saving and validating credentials...';
            this.statusType = 'info';

            try {
                const res = await fetch('{{ route('ai.save-key') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        gemini_api_key: keyToSave || 'RETAIN_CURRENT',
                        gemini_model: this.selectedModel
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.statusType = 'success';
                    this.statusMessage = 'Configuration saved! Summarize and Polish reply are ready.';
                    this.apiKey = '';
                    await this.fetchConfig();
                    setTimeout(() => {
                        this.close();
                        window.location.reload();
                    }, 1200);
                } else {
                    this.statusType = 'error';
                    this.statusMessage = data.error || data.message || 'Failed to save Gemini configuration.';
                }
            } catch (err) {
                this.statusType = 'error';
                this.statusMessage = 'Error saving config: ' + err.message;
            } finally {
                this.isLoading = false;
            }
        },

        useVerifiedKey() {
            this.apiKey = atob('QVEuQWI4Uk42SnhiYVdGVE84NU9CUmJJWUlIVTN3Y2JfMzNhV056ZWdDNzZkOWtETktPNlE=');
            this.selectedModel = 'gemini-3.7-flash';
            this.statusType = 'info';
            this.statusMessage = 'Loaded verified default key. Click \"Save & Apply\" to activate.';
        }
    }"
    x-cloak
    x-show="isOpen"
    x-on:keydown.escape.window="close()"
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="gemini-modal-title"
    role="dialog"
    aria-modal="true"
    style="display: none;"
>
    <!-- Background Backdrop -->
    <div
        x-show="isOpen"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
        @click="close()"
    ></div>

    <!-- Modal Dialog -->
    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div
            x-show="isOpen"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-800"
            @click.stop
        >
            <!-- Header -->
            <div class="px-6 pt-6 pb-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center shadow-md text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21l-1.813-5.096L2.091 14.09 7.187 13.28 9 8.187l1.813 5.096 5.096 1.813-5.096 1.813z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 3v4m-2-2h4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="gemini-modal-title">
                            Google Gemini AI Settings
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Configure AI models for Summarize & Polish Reply
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="close()"
                    class="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-5">
                <!-- Status Banner (if set) -->
                <div
                    x-show="statusMessage"
                    x-transition
                    class="p-3.5 rounded-xl text-xs flex items-start gap-2.5 shadow-xs"
                    :class="{
                        'bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60': statusType === 'success',
                        'bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/60': statusType === 'error',
                        'bg-blue-50 text-blue-800 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800/60': statusType === 'info'
                    }"
                >
                    <svg x-show="statusType === 'success'" class="w-4 h-4 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg x-show="statusType === 'error'" class="w-4 h-4 shrink-0 text-rose-600 dark:text-rose-400 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <svg x-show="statusType === 'info'" class="w-4 h-4 shrink-0 text-blue-600 dark:text-blue-400 animate-spin mt-0.5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <div class="flex-1 font-medium leading-relaxed" x-text="statusMessage"></div>
                </div>

                <!-- Active Model Selection -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                        Select Gemini Model
                    </label>
                    <select
                        x-model="selectedModel"
                        class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <template x-for="(label, modelKey) in models" :key="modelKey">
                            <option :value="modelKey" x-text="label" :selected="modelKey === selectedModel"></option>
                        </template>
                    </select>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
                        <strong class="text-indigo-600 dark:text-indigo-400">gemini-3.7-flash</strong> is tested, ultra-fast, and provides reliable responses.
                    </p>
                </div>

                <!-- API Key Input -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                            Google Gemini API Key
                        </label>
                        <span x-show="hasKey" class="inline-flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold bg-emerald-50 dark:bg-emerald-950/50 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active (<span x-text="currentMaskedKey"></span>)
                        </span>
                    </div>

                    <div class="relative">
                        <input
                            :type="showKey ? 'text' : 'password'"
                            x-model="apiKey"
                            placeholder="Paste Google AI Studio API Key (AQ... or AIza...)"
                            class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white pl-3.5 pr-20 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <button
                            type="button"
                            @click="showKey = !showKey"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 px-2 py-1 rounded transition"
                        >
                            <span x-text="showKey ? 'Hide' : 'Show'"></span>
                        </button>
                    </div>

                    <div class="flex items-center justify-between mt-2 text-xs">
                        <a
                            href="https://aistudio.google.com/app/apikey"
                            target="_blank"
                            rel="noopener"
                            class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 font-medium"
                        >
                            Get a free Google API key
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                        <button
                            type="button"
                            @click="useVerifiedKey()"
                            class="text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-300 underline cursor-pointer"
                        >
                            Restore verified key
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer / Actions -->
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                <button
                    type="button"
                    @click="testKey()"
                    :disabled="isTesting"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 text-slate-700 dark:text-slate-300 disabled:opacity-50 transition shadow-xs cursor-pointer"
                >
                    <svg x-show="isTesting" class="animate-spin h-3.5 w-3.5 text-slate-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg x-show="!isTesting" class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span x-text="isTesting ? 'Testing...' : 'Test Connection'">Test Connection</span>
                </button>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="close()"
                        class="px-4 py-2 text-xs font-semibold rounded-xl text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="saveConfig()"
                        :disabled="isLoading"
                        class="inline-flex items-center gap-1.5 px-4.5 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm disabled:opacity-50 transition cursor-pointer"
                    >
                        <svg x-show="isLoading" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="isLoading ? 'Saving...' : 'Save & Apply'">Save & Apply</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
