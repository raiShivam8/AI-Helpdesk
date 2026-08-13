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
#[Fillable(['ticket_id', 'user_id', 'body', 'body_html', 'sender_type', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_optimized_path', 'attachment_processing_status', 'transfer_reason'])]
class TicketReply extends Model
{
    /**
     * Check if reply has an attached file.
     */
    public function hasAttachment(): bool
    {
        return !empty($this->attachment_path);
    }

    /**
     * Check if attachment is an image file.
     */
    public function isImageAttachment(): bool
    {
        if (!$this->hasAttachment()) {
            return false;
        }

        $mime = strtolower($this->attachment_mime ?? '');
        $name = strtolower($this->attachment_name ?? '');
        $path = strtolower($this->attachment_path ?? '');

        $ext = strtolower(pathinfo(!empty($name) ? $name : $path, PATHINFO_EXTENSION));

        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'bmp', 'tiff'], true)) {
            return true;
        }

        // Fallback: check storage disk mime type if file exists
        try {
            if ($this->attachment_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->attachment_path)) {
                $diskMime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($this->attachment_path);
                if ($diskMime && str_starts_with(strtolower($diskMime), 'image/')) {
                    return true;
                }
            }
        } catch (\Throwable $e) {}

        return false;
    }

    /**
     * Check if image attachment optimization is currently in progress / pending.
     */
    public function isImageProcessing(): bool
    {
        return $this->isImageAttachment() && $this->attachment_processing_status === 'pending';
    }

    /**
     * Check if optimized version of the image is ready.
     */
    public function isImageOptimized(): bool
    {
        return $this->attachment_processing_status === 'completed' && !empty($this->attachment_optimized_path);
    }

    /**
     * Get original attachment public URL.
     */
    public function getAttachmentOriginalUrlAttribute(): ?string
    {
        return $this->attachment_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->attachment_path) : null;
    }

    /**
     * Get optimized attachment public URL.
     */
    public function getAttachmentOptimizedUrlAttribute(): ?string
    {
        return $this->attachment_optimized_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->attachment_optimized_path) : null;
    }

    /**
     * Get default attachment public URL for web display.
     * Fallback to original image URL if optimization is pending or failed.
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        if ($this->isImageOptimized()) {
            return $this->attachment_optimized_url;
        }

        return $this->attachment_original_url;
    }
    /**
     * Boot the model.
     * Automatically sanitize and store HTML content in body_html when body is saved.
     */
    protected static function booted(): void
    {
        static::saving(function (TicketReply $reply) {
            // Keep plain text body unchanged as requested.
            // Store the sanitized HTML version in body_html.
            if ($reply->isDirty('body') && empty($reply->body_html)) {
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
     * Get body HTML with resolved CID images and fallbacks.
     */
    public function getFormattedBodyHtmlAttribute(): ?string
    {
        if (empty($this->body_html)) {
            return null;
        }

        $html = $this->body_html;

        // If html contains unresolved cid: references
        if (str_contains($html, 'cid:')) {
            if ($this->hasAttachment()) {
                $html = preg_replace('/src=["\']cid:([^"\'\s>]+)["\']/i', 'src="' . $this->attachment_url . '"', $html);
            } else {
                // Replace broken cid: image tags with subtle badge when image payload was omitted by mail sender
                $html = preg_replace(
                    '/<img[^>]+src=["\']cid:([^"\'\s>]+)["\'][^>]*>/i',
                    '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 my-2 text-xs font-semibold rounded-xl bg-amber-100/80 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300/80 dark:border-amber-700/80 shadow-2xs"><svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> <span>Inline Image Unavailable (Omitted by mail client)</span></span>',
                    $html
                );
            }
        }

        return $html;
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
