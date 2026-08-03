<?php

namespace App\Models;

use App\Enums\SenderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Stevebauman\Purify\Facades\Purify;

/**
 * Model: TicketReply
 *
 * Represents a single reply posted in a ticket's conversation thread.
 * A reply can originate from an internal agent/admin (SenderType::Agent)
 * or from the customer themselves (SenderType::Customer).
 *
 * Relationships:
 *   - ticket()     : BelongsTo Ticket  — the parent ticket
 *   - user()       : BelongsTo User    — the authenticated user who posted
 *                                        this reply (null for customer replies)
 *
 * Casts:
 *   - sender_type  → SenderType enum   — ensures the column is always a
 *                                        typed enum, never a raw string
 *
 * Mass-assignable columns follow the project-wide #[Fillable] attribute
 * pattern (identical to Ticket and User models).
 */
#[Fillable(['ticket_id', 'user_id', 'body', 'body_html', 'sender_type'])]
class TicketReply extends Model
{
    /**
     * Boot the model.
     * Automatically sanitize and store HTML content in body_html when body is saved.
     */
    protected static function booted(): void
    {
        static::saving(function (TicketReply $reply) {
            // Keep plain text body unchanged as requested.
            // Store the sanitized HTML version in body_html.
            if ($reply->isDirty('body')) {
                $reply->body_html = Purify::clean(nl2br($reply->body));
            }
        });
    }

    /**
     * The ticket that this reply belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The authenticated user (agent or admin) who authored this reply.
     * Will be null for customer-originated replies (no portal account).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Attribute casts.
     *
     * Casting sender_type to the SenderType enum means Eloquent will
     * automatically convert the raw DB string → SenderType instance on
     * read and SenderType instance → string on write.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sender_type' => SenderType::class,
        ];
    }
}
