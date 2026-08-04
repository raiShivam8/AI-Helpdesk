<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">Users</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Manage agents and administrators</p>
            </div>
            <button
                x-data=""
                x-on:click.prevent="$dispatch('open-modal', 'create-user-modal')"
                class="btn-primary"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Add User
            </button>
        </div>
    </x-slot>

    <div
        x-data="{
            editUser: {
                id: '{{ old('user_id', '') }}',
                name: '{{ old('name', '') }}',
                email: '{{ old('email', '') }}',
                role: '{{ old('role', '') }}',
                password: ''
            },
            deleteUser: {
                id: '',
                name: '',
                email: ''
            }
        }"
    >

        {{-- Flash Messages --}}
        @if (session('success'))
            <div
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 4000)"
                class="mb-5 flex items-center justify-between p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-800 shadow-sm"
                role="alert"
            >
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-semibold">{{ session('success') }}</span>
                </div>
                <button @click="show = false" class="text-emerald-600 hover:text-emerald-900 ml-4 text-lg font-bold leading-none">&times;</button>
            </div>
        @endif

        @if (session('error'))
            <div
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 5000)"
                class="mb-5 flex items-center justify-between p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-800 shadow-sm"
                role="alert"
            >
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-semibold">{{ session('error') }}</span>
                </div>
                <button @click="show = false" class="text-red-600 hover:text-red-900 ml-4 text-lg font-bold leading-none">&times;</button>
            </div>
        @endif

        {{-- Users Table --}}
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            {{-- Name + Avatar --}}
                            <td class="whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="avatar avatar-md
                                        {{ $user->isAdmin() ? 'bg-violet-100 text-violet-700' : 'bg-sky-100 text-sky-700' }}
                                        font-semibold">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $user->name }}</span>
                                </div>
                            </td>

                            {{-- Email --}}
                            <td class="text-slate-500 dark:text-slate-400">{{ $user->email }}</td>

                            {{-- Role --}}
                            <td class="whitespace-nowrap">
                                @if ($user->isAdmin())
                                    <span class="badge badge-admin">Admin</span>
                                @elseif ($user->isAgent())
                                    <span class="badge badge-agent">Agent</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold text-sky-700 bg-sky-50 border border-sky-200 rounded-md">Customer</span>
                                @endif
                            </td>

                            {{-- Joined --}}
                            <td class="text-slate-500 dark:text-slate-400 whitespace-nowrap text-xs font-medium">
                                {{ $user->created_at->format('M j, Y') }}
                            </td>

                            {{-- Actions --}}
                            <td class="text-right whitespace-nowrap">
                                <div class="flex justify-end items-center gap-2">
                                    {{-- Edit --}}
                                    <button
                                        @click="editUser = @js([
                                            'id'       => $user->id,
                                            'name'     => $user->name,
                                            'email'    => $user->email,
                                            'role'     => $user->role->value,
                                            'password' => '',
                                        ]); $dispatch('open-modal', 'edit-user-modal');"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-200 transition-all duration-150"
                                        title="{{ __('Edit User') }}"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Edit
                                    </button>

                                    {{-- Delete --}}
                                    @if ($user->email === config('app.admin_email'))
                                        <button disabled
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-400 bg-slate-50 rounded-lg border border-slate-200 cursor-not-allowed"
                                            title="{{ __('Default Admin cannot be deleted') }}"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Delete
                                        </button>
                                    @else
                                        <button
                                            @click="deleteUser = @js([
                                                'id'    => $user->id,
                                                'name'  => $user->name,
                                                'email' => $user->email,
                                            ]); $dispatch('open-modal', 'delete-user-modal');"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 rounded-lg border border-red-200 transition-all duration-150"
                                            title="{{ __('Delete User') }}"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-12 h-12 bg-slate-100 dark:bg-slate-700/60 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <p class="text-slate-500 text-sm font-medium">No users found.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══ CREATE USER MODAL ═══ --}}
        <x-modal name="create-user-modal" :show="$errors->isNotEmpty() && !$errors->hasBag('updateUser')" focusable>
            <form method="post" action="{{ route('users.store') }}" class="p-6" autocomplete="off" novalidate>
                @csrf

                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Create New User</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Add a new agent or admin to the system</p>
                    </div>
                    <button type="button" x-on:click="$dispatch('close')"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="create_name" value="Name" />
                        <x-text-input id="create_name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" autofocus autocomplete="off" :is-error="$errors->has('name')" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="create_email" value="Email Address" />
                        <x-text-input id="create_email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email') }}" autocomplete="off" :is-error="$errors->has('email')" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="create_password" value="Password" />
                        <x-text-input id="create_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" :is-error="$errors->has('password')" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="create_role" value="Role" />
                        <select id="create_role" name="role"
                            class="mt-1 form-select {{ $errors->has('role') ? 'border-red-400 focus:border-red-500 focus:ring-red-500/30' : '' }}">
                            <option value="" disabled {{ !old('role') ? 'selected' : '' }}>Select a role…</option>
                            @foreach(\App\Enums\Role::cases() as $role)
                                <option value="{{ $role->value }}" {{ old('role') === $role->value ? 'selected' : '' }}>
                                    {{ ucfirst($role->value) }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-1.5" />
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-slate-100 dark:border-slate-700">
                    <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                    <x-primary-button>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Create User
                    </x-primary-button>
                </div>
            </form>
        </x-modal>

        {{-- ═══ EDIT USER MODAL ═══ --}}
        <x-modal name="edit-user-modal" :show="$errors->updateUser->isNotEmpty()" focusable>
            <form method="post" :action="'/users/' + editUser.id" class="p-6" autocomplete="off" novalidate>
                @csrf
                @method('PATCH')
                <input type="hidden" name="user_id" :value="editUser.id">

                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Edit User</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Update user details and permissions</p>
                    </div>
                    <button type="button" x-on:click="$dispatch('close')"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="edit_name" value="Name" />
                        <x-text-input id="edit_name" name="name" type="text" class="mt-1 block w-full" x-model="editUser.name" autofocus autocomplete="off" :is-error="$errors->updateUser->has('name')" />
                        <x-input-error :messages="$errors->updateUser->get('name')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="edit_email" value="Email Address" />
                        <x-text-input id="edit_email" name="email" type="email" class="mt-1 block w-full" x-model="editUser.email" autocomplete="off" :is-error="$errors->updateUser->has('email')" />
                        <x-input-error :messages="$errors->updateUser->get('email')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="edit_password" value="New Password (optional)" />
                        <x-text-input id="edit_password" name="password" type="password" class="mt-1 block w-full" x-model="editUser.password" autocomplete="new-password" placeholder="Leave empty to keep current" :is-error="$errors->updateUser->has('password')" />
                        <x-input-error :messages="$errors->updateUser->get('password')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="edit_role" value="Role" />
                        <select id="edit_role" name="role" x-model="editUser.role"
                            class="mt-1 form-select {{ $errors->updateUser->has('role') ? 'border-red-400 focus:border-red-500 focus:ring-red-500/30' : '' }}">
                            <option value="" disabled>Select a role…</option>
                            @foreach(\App\Enums\Role::cases() as $role)
                                <option value="{{ $role->value }}">{{ ucfirst($role->value) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->updateUser->get('role')" class="mt-1.5" />
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-slate-100 dark:border-slate-700">
                    <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                    <x-primary-button>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Save Changes
                    </x-primary-button>
                </div>
            </form>
        </x-modal>

        {{-- ═══ DELETE USER MODAL ═══ --}}
        <x-modal name="delete-user-modal" focusable>
            <form method="post" :action="'/users/' + deleteUser.id" class="p-6">
                @csrf
                @method('DELETE')

                <div class="flex items-start gap-4 mb-5">
                    <div class="w-11 h-11 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Delete User?</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            This will soft-delete the user and remove them from active lists. They can be recovered later if needed.
                        </p>
                    </div>
                </div>

                <div class="rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 px-4 py-3 mb-6">
                    <p class="text-sm text-slate-700 dark:text-slate-200">
                        <span class="font-semibold" x-text="deleteUser.name"></span>
                        &mdash;
                        <span class="text-slate-500 dark:text-slate-400" x-text="deleteUser.email"></span>
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                    <x-danger-button>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete User
                    </x-danger-button>
                </div>
            </form>
        </x-modal>

    </div>
</x-app-layout>
