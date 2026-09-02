<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/tickets', [\App\Http\Controllers\TicketController::class, 'index'])->name('tickets.index');
    Route::post('/tickets/sync-emails', \App\Http\Controllers\SyncEmailsController::class)->name('tickets.sync-emails');
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\TicketController::class, 'show'])->name('tickets.show');
    Route::patch('/tickets/{ticket}', [\App\Http\Controllers\TicketController::class, 'update'])->name('tickets.update');
    Route::delete('/tickets/{ticket}', [\App\Http\Controllers\TicketController::class, 'destroy'])->name('tickets.destroy');
    Route::patch('/tickets/{ticket}/assign', [\App\Http\Controllers\TicketController::class, 'assign'])->name('tickets.assign');

    // Ticket Replies – nested under a ticket (POST only; replies are immutable)
    Route::post('/tickets/{ticket}/replies', [\App\Http\Controllers\TicketReplyController::class, 'store'])->name('tickets.replies.store');
    Route::post('/tickets/{ticket}/polish-reply', [\App\Http\Controllers\TicketReplyController::class, 'polish'])->name('tickets.polish-reply');
    Route::post('/tickets/{ticket}/summarize', [\App\Http\Controllers\TicketController::class, 'summarize'])->name('tickets.summarize');
    Route::post('/tickets/{ticket}/try-ai-resolve', [\App\Http\Controllers\TicketController::class, 'tryAiResolve'])->name('tickets.try-ai-resolve');
    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/debug-gemini', function () {
        if (!auth()->user()?->isAdmin()) {
            abort(403);
        }
        $gemini = app(\App\Services\GeminiService::class);
        $hasDbKey = !empty(\Illuminate\Support\Facades\Cache::get('system_gemini_api_key'));
        $rawKey = null;
        try {
            $rawKey = $gemini->getApiKey();
        } catch (\Throwable $e) {}

        $model = $gemini->getModel();
        $length = strlen($rawKey ?? '');
        $preview = $length > 10 ? substr($rawKey, 0, 6) . '...' . substr($rawKey, -4) : ($length > 0 ? 'TOO_SHORT' : 'NOT_SET');

        $response = null;
        if (!empty($rawKey)) {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get("https://generativelanguage.googleapis.com/v1beta/models?key={$rawKey}");
        }

        return response()->json([
            'key_configured' => !empty($rawKey),
            'key_source' => $hasDbKey ? 'database_cache' : 'environment',
            'key_length' => $length,
            'key_preview' => $preview,
            'model' => $model,
            'google_http_status' => $response?->status() ?? 500,
            'google_response' => $response?->json('error') ?? ($response?->successful() ? 'OK (Google accepted key)' : 'No key provided'),
            'admin_settings_url' => route('admin.ai-settings.index'),
        ]);
    })->name('debug.gemini');
});

Route::middleware(['auth', 'can:view-users'])->group(function () {
    Route::get('/admin/ai-settings', [\App\Http\Controllers\AiSettingsController::class, 'index'])->name('admin.ai-settings.index');
    Route::post('/admin/ai-settings', [\App\Http\Controllers\AiSettingsController::class, 'update'])->name('admin.ai-settings.update');
    Route::post('/admin/ai-settings/test', [\App\Http\Controllers\AiSettingsController::class, 'test'])->name('admin.ai-settings.test');

    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');
});

Route::post('/api/webhooks/inbound-email', [\App\Http\Controllers\InboundEmailWebhookController::class, 'handle'])
    ->middleware(\App\Http\Middleware\VerifyWebhookSecret::class)
    ->name('webhooks.inbound-email');

// Fallback route to serve files directly from storage/app/public/ on any environment/server
Route::get('/storage/{path}', function ($path) {
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    if (!$disk->exists($path)) {
        abort(404);
    }
    $filePath = $disk->path($path);
    $mimeType = \Illuminate\Support\Facades\File::mimeType($filePath) ?: 'application/octet-stream';
    return response(file_get_contents($filePath), 200, [
        'Content-Type'        => $mimeType,
        'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        'Cache-Control'       => 'public, max-age=86400',
    ]);
})->where('path', '.*')->name('storage.serve');

require __DIR__.'/auth.php';
