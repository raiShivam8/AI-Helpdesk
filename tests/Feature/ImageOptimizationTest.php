<?php

namespace Tests\Feature;

use App\Jobs\OptimizeImageAttachmentJob;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_optimization_job_creates_optimized_copy_and_preserves_original(): void
    {
        Storage::fake('public');

        // Create a test image using GD (2500 x 2500 px to test downscaling)
        $origWidth = 2500;
        $origHeight = 2500;
        $img = imagecreatetruecolor($origWidth, $origHeight);
        $red = imagecolorallocate($img, 230, 50, 50);
        imagefill($img, 0, 0, $red);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_img_') . '.jpg';
        imagejpeg($img, $tmpFile, 95);
        imagedestroy($img);

        $originalPath = 'attachments/test_large_screenshot.jpg';
        Storage::disk('public')->put($originalPath, file_get_contents($tmpFile));
        @unlink($tmpFile);

        $ticket = Ticket::factory()->create();
        $reply = TicketReply::create([
            'ticket_id'                    => $ticket->id,
            'body'                         => 'Screenshot attached',
            'attachment_path'              => $originalPath,
            'attachment_name'              => 'test_large_screenshot.jpg',
            'attachment_mime'              => 'image/jpeg',
            'attachment_processing_status' => 'pending',
        ]);

        $this->assertTrue($reply->isImageProcessing());
        $this->assertFalse($reply->isImageOptimized());
        // Fallback to original before job runs
        $this->assertEquals(Storage::disk('public')->url($originalPath), $reply->attachment_url);

        // Execute optimization job
        $job = new OptimizeImageAttachmentJob($reply->id);
        $job->handle();

        $reply->refresh();

        $this->assertEquals('completed', $reply->attachment_processing_status);
        $this->assertTrue($reply->isImageOptimized());
        $this->assertNotNull($reply->attachment_optimized_path);

        // Verify original image still exists on disk (NEVER DELETED)
        Storage::disk('public')->assertExists($originalPath);

        // Verify optimized copy exists on disk
        Storage::disk('public')->assertExists($reply->attachment_optimized_path);

        // Check optimized image dimensions (Downscaled to max 2000x2000)
        $optFullPath = Storage::disk('public')->path($reply->attachment_optimized_path);
        [$optW, $optH] = getimagesize($optFullPath);
        $this->assertLessThanOrEqual(2000, $optW);
        $this->assertLessThanOrEqual(2000, $optH);

        // attachment_url now points to optimized copy
        $this->assertEquals(Storage::disk('public')->url($reply->attachment_optimized_path), $reply->attachment_url);
        // attachment_original_url still points to original file
        $this->assertEquals(Storage::disk('public')->url($originalPath), $reply->attachment_original_url);
    }

    public function test_image_optimization_fallback_when_file_missing_or_failed(): void
    {
        Storage::fake('public');

        $ticket = Ticket::factory()->create();
        $reply = TicketReply::create([
            'ticket_id'                    => $ticket->id,
            'body'                         => 'Broken image test',
            'attachment_path'              => 'attachments/missing_image.jpg',
            'attachment_name'              => 'missing_image.jpg',
            'attachment_mime'              => 'image/jpeg',
            'attachment_processing_status' => 'pending',
        ]);

        $job = new OptimizeImageAttachmentJob($reply->id);
        $job->handle();

        $reply->refresh();

        // Job handled failure gracefully
        $this->assertEquals('failed', $reply->attachment_processing_status);
        // Fallback URL still resolves to original path without crashing
        $this->assertEquals(Storage::disk('public')->url('attachments/missing_image.jpg'), $reply->attachment_url);
    }

    public function test_storage_route_serves_image_file_directly(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('attachments/test_serve_image.png', 'dummy-image-content');

        $user = \App\Models\User::factory()->create();
        $response = $this->actingAs($user)->get('/storage/attachments/test_serve_image.png');

        $response->assertStatus(200);
    }
}
