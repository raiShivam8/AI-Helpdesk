<?php

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['message_id', 'sender_email', 'sender_name', 'subject', 'body', 'status', 'priority', 'category', 'assigned_agent_id', 'ai_summary', 'category_confidence', 'classified_at', 'ai_resolved_at', 'resolved_at'])]
class Ticket extends Model
{
    /** @use HasFactory<\Database\Factories\TicketFactory> */
    use HasFactory;

    /**
     * Get the agent assigned to this ticket.
     */
    public function assignedAgent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id')->withTrashed();
    }

    /**
     * Get all replies for this ticket, ordered oldest-first for a
     * chronological conversation thread.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->orderBy('created_at', 'asc');
    }

    /**
     * Safe accessor for category attribute.
     */
    protected function category(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (empty($value)) return null;
                if ($value instanceof TicketCategory) return $value;
                $clean = strtolower(trim((string) $value));
                $cleanNoUnderscore = str_replace(['_', '-'], ' ', $clean);
                return TicketCategory::tryFrom($clean) 
                    ?? TicketCategory::tryFrom($cleanNoUnderscore)
                    ?? null;
            }
        );
    }

    /**
     * Safe accessor for status attribute.
     */
    protected function status(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (empty($value)) return TicketStatus::Open;
                if ($value instanceof TicketStatus) return $value;
                $clean = strtolower(trim((string) $value));
                return TicketStatus::tryFrom($clean) ?? TicketStatus::Open;
            }
        );
    }

    /**
     * Safe accessor for priority attribute.
     */
    protected function priority(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (empty($value)) return TicketPriority::Medium;
                if ($value instanceof TicketPriority) return $value;
                $clean = strtolower(trim((string) $value));
                return TicketPriority::tryFrom($clean) ?? TicketPriority::Medium;
            }
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
            'classified_at'  => 'datetime',
            'ai_resolved_at' => 'datetime',
            'resolved_at'    => 'datetime',
        ];
    }
}
