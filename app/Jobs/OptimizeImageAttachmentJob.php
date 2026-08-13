<?php

namespace App\Jobs;

use App\Models\TicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OptimizeImageAttachmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param int $replyId
     */
    public function __construct(public int $replyId) {}

    /**
     * Execute the image optimization job.
     */
    public function handle(): void
    {
        @ini_set('memory_limit', '512M');

        $reply = TicketReply::find($this->replyId);

        if (!$reply || !$reply->isImageAttachment() || empty($reply->attachment_path)) {
            return;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($reply->attachment_path)) {
            Log::warning("OptimizeImageAttachmentJob: Attachment file not found on disk", [
                'reply_id' => $reply->id,
                'path'     => $reply->attachment_path,
            ]);
            $reply->update(['attachment_processing_status' => 'failed']);
            return;
        }

        $fullPath = $disk->path($reply->attachment_path);

        try {
            $imageInfo = @getimagesize($fullPath);
            if (!$imageInfo) {
                Log::warning("OptimizeImageAttachmentJob: Unable to get image dimensions", ['reply_id' => $reply->id]);
                $reply->update(['attachment_processing_status' => 'failed']);
                return;
            }

            [$origW, $origH, $imageType] = $imageInfo;

            // Load source image using GD based on type
            $srcImg = match ($imageType) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($fullPath),
                IMAGETYPE_PNG  => @imagecreatefrompng($fullPath),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($fullPath) : null,
                IMAGETYPE_GIF  => @imagecreatefromgif($fullPath),
                default        => null,
            };

            if (!$srcImg) {
                Log::warning("OptimizeImageAttachmentJob: Failed to create GD image resource", ['reply_id' => $reply->id]);
                $reply->update(['attachment_processing_status' => 'failed']);
                return;
            }

            // Calculate new dimensions (Max 2000x2000 px, maintaining aspect ratio)
            $maxDimension = 2000;
            $newW = $origW;
            $newH = $origH;

            if ($origW > $maxDimension || $origH > $maxDimension) {
                if ($origW >= $origH) {
                    $newW = $maxDimension;
                    $newH = (int) round(($origH * $maxDimension) / $origW);
                } else {
                    $newH = $maxDimension;
                    $newW = (int) round(($origW * $maxDimension) / $origH);
                }
            }

            // Create canvas for resized image
            $dstImg = imagecreatetruecolor($newW, $newH);

            // Handle transparency for PNG / WebP / GIF
            if (in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
                imagealphablending($dstImg, false);
                imagesavealpha($dstImg, true);
                $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
                imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $transparent);
            }

            // Resample with high quality interpolation
            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

            // Output filename setup
            $pathInfo = pathinfo($reply->attachment_path);
            $extension = strtolower($pathInfo['extension'] ?? 'jpg');
            $optFilename = 'attachments/opt_' . $reply->id . '_' . uniqid() . '.' . ($extension === 'png' ? 'png' : 'jpg');
            $tempPath = storage_path('app/temp_' . uniqid() . '.' . ($extension === 'png' ? 'png' : 'jpg'));

            // High quality target (Quality ~85-90 for high readability of screenshots/text)
            if ($extension === 'png') {
                imagepng($dstImg, $tempPath, 6); // Compression level 6 (0-9)
            } else {
                imagejpeg($dstImg, $tempPath, 88); // Quality 88 for crisp text & colors
            }

            imagedestroy($srcImg);
            imagedestroy($dstImg);

            // Check file size: target 500 KB - 1 MB, hard cap at 2 MB (2,097,152 bytes)
            $maxBytes = 2 * 1024 * 1024;
            if (file_exists($tempPath) && filesize($tempPath) > $maxBytes) {
                // If over 2MB limit, re-compress JPEG at slightly lower quality (78)
                $tempImg = @imagecreatefromstring(file_get_contents($tempPath));
                if ($tempImg) {
                    imagejpeg($tempImg, $tempPath, 78);
                    imagedestroy($tempImg);
                }
            }

            if (file_exists($tempPath)) {
                $optContent = file_get_contents($tempPath);
                $disk->put($optFilename, $optContent);
                @unlink($tempPath);

                $reply->update([
                    'attachment_optimized_path'     => $optFilename,
                    'attachment_processing_status' => 'completed',
                ]);

                Log::info("Successfully optimized image attachment for TicketReply #{$reply->id}", [
                    'reply_id'       => $reply->id,
                    'original_path'  => $reply->attachment_path,
                    'optimized_path' => $optFilename,
                    'orig_dimensions'=> "{$origW}x{$origH}",
                    'opt_dimensions' => "{$newW}x{$newH}",
                ]);
            } else {
                $reply->update(['attachment_processing_status' => 'failed']);
            }
        } catch (\Throwable $e) {
            report($e);
            Log::error("Failed to optimize image attachment for TicketReply #{$reply->id}: " . $e->getMessage(), [
                'reply_id'  => $reply->id,
                'exception' => $e,
            ]);

            $reply->update(['attachment_processing_status' => 'failed']);
        }
    }
}
