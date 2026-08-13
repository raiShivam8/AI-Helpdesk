<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use App\Models\Ticket;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            // Automatically unassign all tickets assigned to this agent when deleted
            Ticket::where('assigned_agent_id', $user->id)
                ->update(['assigned_agent_id' => null]);
        });
    }

    /**
     * Determine if the user has the admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Determine if the user has the agent role.
     */
    public function isAgent(): bool
    {
        return $this->role === Role::Agent;
    }

    public function appNotifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    /**
     * Determine if the user has the customer role.
     */
    public function isCustomer(): bool
    {
        return $this->role === Role::Customer;
    }

    /**
     * Interact with the user's name (automatic title case and trimming).
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::title(trim($value)),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }
}
