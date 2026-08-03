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
        return $this->belongsTo(User::class, 'assigned_agent_id');
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status'         => TicketStatus::class,
            'category'       => TicketCategory::class,
            'priority'       => TicketPriority::class,
            'classified_at'  => 'datetime',
            'ai_resolved_at' => 'datetime',
            'resolved_at'    => 'datetime',
        ];
    }
}
