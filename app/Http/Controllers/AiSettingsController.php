<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class AiSettingsController extends Controller
{
    /**
     * Display AI Configuration page
     */
    public function index(GeminiService $geminiService): View
    {
        $hasKey = false;
        $maskedKey = null;
        $activeModel = $geminiService->getModel();
        $healthStatus = 'unknown';
        $healthMessage = null;

        try {
            $rawKey = $geminiService->getApiKey();
            $hasKey = true;
            $len = strlen($rawKey);
            $maskedKey = $len > 10 ? substr($rawKey, 0, 6) . '...' . substr($rawKey, -4) : '******';

            // Test health
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$activeModel}?key={$rawKey}";
            $resp = Http::timeout(6)->get($url);

            if ($resp->successful()) {
                $healthStatus = 'connected';
                $healthMessage = "Active and responding properly ({$activeModel}).";
            } else {
                $healthStatus = 'error';
                $healthMessage = $resp->json('error.message') ?? 'Google returned status ' . $resp->status();
            }
        } catch (\Throwable $e) {
            $healthStatus = 'unconfigured';
            $healthMessage = $e->getMessage();
        }

        return view('admin.ai-settings', compact(
            'hasKey',
            'maskedKey',
            'activeModel',
            'healthStatus',
            'healthMessage'
        ));
    }

    /**
     * Validate and save the Gemini API key and model
     */
    public function update(Request $request, GeminiService $geminiService): RedirectResponse|JsonResponse
    {
        $request->validate([
            'gemini_api_key' => ['required', 'string', 'min:10'],
            'gemini_model' => ['required', 'string', 'in:gemini-2.5-flash,gemini-2.0-flash,gemini-1.5-flash,gemini-flash-latest'],
        ]);

        try {
            $result = $geminiService->validateAndSaveKey(
                $request->input('gemini_api_key'),
                $request->input('gemini_model')
            );

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return redirect()->route('admin.ai-settings.index')
                ->with('success', 'Gemini API Key was verified by Google and saved successfully! Polish & Summarize are now ready.');
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Quick test endpoint to test a key before saving
     */
    public function test(Request $request): JsonResponse
    {
        $key = trim($request->input('key', ''));
        $model = trim($request->input('model', 'gemini-2.5-flash'));

        if (empty($key)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter an API key to test.',
            ], 422);
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
            $response = Http::timeout(8)->post($url, [
                'contents' => [
                    ['parts' => [['text' => 'ping']]]
                ]
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => "Google API verification passed! Model: {$model}",
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $response->json('error.message') ?? 'HTTP status ' . $response->status(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
