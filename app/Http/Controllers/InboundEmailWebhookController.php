<?php

namespace App\Http\Controllers;

use App\Actions\CreateTicketFromInboundEmailAction;
use App\Http\Requests\StoreInboundEmailRequest;
use Illuminate\Http\JsonResponse;

class InboundEmailWebhookController extends Controller
{
    public function __construct(
        protected CreateTicketFromInboundEmailAction $createTicketAction
    ) {}

    /**
     * Handle the inbound email webhook.
     */
    public function handle(StoreInboundEmailRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $attachments = $validated['attachments'] ?? [];
            $bodyHtml = $validated['body_html'] ?? null;

            // Convert base64 inline images in body_html (pasted screenshots) to public storage files
            if (!empty($bodyHtml) && preg_match_all('/src=["\']data:image\/([a-zA-Z0-9\+\-]+);base64,([^"\'\s>]+)["\']/i', $bodyHtml, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    try {
                        $imgType = strtolower($match[1]);
                        $base64Data = base64_decode($match[2]);
                        if (!empty($base64Data)) {
                            $b64Filename = 'attachments/b64_' . uniqid() . '.' . ($imgType === 'jpeg' ? 'jpg' : $imgType);
                            \Illuminate\Support\Facades\Storage::disk('public')->put($b64Filename, $base64Data);
                            $b64Url = asset('storage/' . $b64Filename);

                            $bodyHtml = str_replace($match[0], 'src="' . $b64Url . '"', $bodyHtml);

                            $attachments[] = [
                                'name' => 'inline_screenshot.' . $imgType,
                                'mime' => 'image/' . $imgType,
                                'path' => $b64Filename,
                                'url'  => $b64Url,
                            ];
                        }
                    } catch (\Throwable $b64Ex) {
                        \Illuminate\Support\Facades\Log::warning('Failed to parse base64 image in webhook email body: ' . $b64Ex->getMessage());
                    }
                }
                $validated['body_html'] = $bodyHtml;
            }

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $path = $file->store('attachments', 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'path' => $path,
                    'url'  => asset('storage/' . $path),
                ];
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('attachments', 'public');
                    $attachments[] = [
                        'name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'path' => $path,
                        'url'  => asset('storage/' . $path),
                    ];
                }
            }

            $validated['attachments'] = $attachments;

            $ticket = $this->createTicketAction->execute($validated);

            return response()->json([
                'message' => 'Ticket created successfully.',
                'ticket'  => $ticket,
            ], 201);
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
