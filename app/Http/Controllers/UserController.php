<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): View
    {
        $users = User::orderBy('name')->get();

        return view('users.index', compact('users'));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage (soft delete).
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('view-users');

        if ($user->email === config('app.admin_email')) {
            return back()->with('error', 'The default Admin account cannot be deleted.');
        }

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
