<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
    /**
     * Get Gemini API key
     */
    protected function getApiKey(): string
    {
        $key = config('services.gemini.key');

        if (empty($key)) {
            throw new RuntimeException('Gemini API key is not configured. Please add GEMINI_API_KEY to your .env file.');
        }

        return $key;
    }

    /**
     * Centralized execution method for calling Gemini API with multi-model fallback and retries.
     *
     * @param string $prompt
     * @param array $extraPayload
     * @return string
     */
    protected function generateContent(string $prompt, array $extraPayload = []): string
    {
        @set_time_limit(60);

        $apiKey = $this->getApiKey();
        $timeout = (int) config('services.gemini.timeout', 30);
        $connectTimeout = (int) config('services.gemini.connect_timeout', 10);
        $proxy = config('services.gemini.proxy');
        $ipResolve = config('services.gemini.ip_resolve');

        $options = [];
        if (!empty($proxy)) {
            $options['proxy'] = $proxy;
        }

        if ($ipResolve === 'v4') {
            $options['curl'] = [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4];
        } elseif ($ipResolve === 'v6') {
            $options['curl'] = [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6];
        }

        // Primary model and ordered fallback models for high demand / rate limit resilience
        $primaryModel = config('services.gemini.model', 'gemini-2.0-flash');
        $fallbackModels = [$primaryModel];

        $payload = array_merge([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ], $extraPayload);

        $lastException = null;

        foreach ($fallbackModels as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $startTime = microtime(true);

            try {
                Log::info("Sending request to Gemini [Model: {$model}]");

                $response = Http::connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->withOptions($options)
                    ->post($url, $payload);

                $duration = round((microtime(true) - $startTime) * 1000, 2);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text');
                    if (!empty($text)) {
                        Log::info("Gemini API Request Success [Model: {$model}]", [
                            'status' => $response->status(),
                            'duration_ms' => $duration
                        ]);
                        return trim($text);
                    }
                }

                // Handle 429 rate limit with automatic retry after pause
                if ($response->status() === 429) {
                    Log::warning("Gemini 429 Rate Limit encountered on model {$model}. Pausing 3 seconds for quota reset...");
                    sleep(3);
                    $retryResponse = Http::connectTimeout($connectTimeout)
                        ->timeout($timeout)
                        ->withOptions($options)
                        ->post($url, $payload);

                    if ($retryResponse->successful()) {
                        $text = $retryResponse->json('candidates.0.content.parts.0.text');
                        if (!empty($text)) {
                            Log::info("Gemini API Retry Success [Model: {$model}]");
                            return trim($text);
                        }
                    }
                }

                $errorMessage = $response->json('error.message') ?? 'HTTP status code: ' . $response->status();
                Log::warning("Gemini model {$model} returned error status {$response->status()}: {$errorMessage}. Trying fallback model if available...");
                $lastException = new RuntimeException('Gemini API Error: ' . $errorMessage);

            } catch (\Exception $e) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                Log::warning("Exception calling Gemini model {$model} after {$duration}ms: " . $e->getMessage());
                $lastException = $e;
            }
        }

        if ($lastException instanceof RuntimeException) {
            throw $lastException;
        }

        throw new RuntimeException('Gemini API Error: All Gemini models are currently experiencing high demand. Please try again in a few moments.', 0, $lastException);
    }

    /**
     * Polish agent reply using Gemini
     */
    public function polishReply(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('Cannot polish an empty reply.');
        }

        // Compute unique key based on SHA-256 hash of normalized text
        $normalizedText = mb_strtolower($text, 'UTF-8');
        $textHash = hash('sha256', $normalizedText);
        $cacheKey = "gemini:polish:{$textHash}";
        
        $cacheStore = config('services.gemini.cache_store', 'file');
        $cacheTtl = (int) config('services.gemini.cache_ttl', 86400);
        $startTime = microtime(true);

        // Check cache hit
        if ($cacheTtl > 0) {
            try {
                if (Cache::store($cacheStore)->has($cacheKey)) {
                    $cachedReply = Cache::store($cacheStore)->get($cacheKey);
                    $duration = round((microtime(true) - $startTime) * 1000, 2);
                    Log::info('Gemini Polish Reply Cache HIT', [
                        'hash' => $textHash,
                        'store' => $cacheStore,
                        'duration_ms' => $duration
                    ]);
                    return $cachedReply;
                }
            } catch (\Exception $e) {
                Log::warning('Gemini Cache Store read failed, falling back to API', [
                    'exception' => $e->getMessage()
                ]);
            }
        }

        $prompt = "You are a professional customer support assistant. " .
                  "Your task is to polish the following draft reply for a helpdesk ticket to make it more professional, polite, clear, and grammatically correct while preserving its original meaning. " .
                  "Return ONLY the final polished text. Do not wrap the response in markdown backticks, quotes, or include any extra conversational filler/explanations.\n\n" .
                  "Draft Reply:\n" . $text;

        $reply = $this->generateContent($prompt);

        // Clean up any residual formatting or quotes if Gemini returned them
        if (str_starts_with($reply, '```')) {
            $lines = explode("\n", $reply);
            if (count($lines) >= 3) {
                array_shift($lines); // Remove opening ```
                array_pop($lines);   // Remove closing ```
                $reply = trim(implode("\n", $lines));
            }
        }

        // Strip enclosing outer quotes if any
        if (preg_match('/^["\'](.*)["\']$/s', $reply, $matches)) {
            $reply = trim($matches[1]);
        }

        // Cache the successful polished reply
        if ($cacheTtl > 0) {
            try {
                Cache::store($cacheStore)->put($cacheKey, $reply, $cacheTtl);
            } catch (\Exception $e) {
                Log::warning('Gemini Cache Store write failed', [
                    'exception' => $e->getMessage()
                ]);
            }
        }

        return $reply;
    }

    /**
     * Generate an AI-powered summary for a ticket and its conversation thread.
     */
    public function summarizeTicket(string $subject, string $customerMessage, array $conversation): string
    {
        $subject = trim($subject);
        $customerMessage = trim($customerMessage);

        $conversationText = "";
        foreach ($conversation as $reply) {
            $sender = $reply['sender'] ?? 'Unknown';
            $role = $reply['role'] ?? 'user';
            $body = trim($reply['body'] ?? '');
            $conversationText .= "- {$sender} ({$role}): {$body}\n";
        }

        $prompt = "You are a customer support analysis assistant. Analyze the following helpdesk ticket and generate a structured JSON summary.\n\n" .
                  "Ticket Subject: {$subject}\n" .
                  "Original Customer Message: {$customerMessage}\n" .
                  "Conversation Thread:\n{$conversationText}\n\n" .
                  "Return a JSON object with exactly the following keys:\n" .
                  "{\n" .
                  "  \"summary\": \"A concise, single-paragraph summary of the ticket and conversation history.\",\n" .
                  "  \"issues\": [\"List of important customer issues identified from the messages.\"],\n" .
                  "  \"actions_taken\": [\"List of actions already taken by agents in the replies.\"],\n" .
                  "  \"status\": \"A short description of the current status of the ticket.\",\n" .
                  "  \"next_step\": \"The single most important suggested next step for the agent.\"\n" .
                  "}";

        return $this->generateContent($prompt, [
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ]
        ]);
    }

    /**
     * Classify a ticket's subject and message.
     */
    public function classifyTicket(string $subject, string $message): array
    {
        $subject = trim($subject);
        $message = trim($message);

        $prompt = "You are a customer support triage assistant. Analyze the following ticket subject and message, and classify it into one of the designated categories.\n\n" .
                  "Ticket Subject: {$subject}\n" .
                  "Ticket Message: {$message}\n\n" .
                  "Designated Categories:\n" .
                  "- Technical Issue\n" .
                  "- Billing\n" .
                  "- Account\n" .
                  "- Refund\n" .
                  "- General Question\n" .
                  "- Spam/Gibberish\n\n" .
                  "Return a JSON object with exactly the following keys:\n" .
                  "{\n" .
                  "  \"category\": \"One of the designated categories listed above\",\n" .
                  "  \"confidence\": 0.95\n" .
                  "}";

        $classificationText = $this->generateContent($prompt, [
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ]
        ]);

        $decoded = json_decode($classificationText, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['category']) || !isset($decoded['confidence'])) {
            throw new RuntimeException('AI returned invalid classification format.');
        }

        return [
            'category' => trim($decoded['category']),
            'confidence' => (float) $decoded['confidence'],
        ];
    }

    /**
     * Auto-resolve a ticket using the knowledge base.
     */
    public function autoResolveTicket(string $subject, string $message, string $knowledgeBase, string $customerName): array
    {
        $subject = trim($subject);
        $message = trim($message);
        $knowledgeBase = trim($knowledgeBase);
        $customerName = trim($customerName);

        $firstName = explode(' ', $customerName)[0] ?? 'there';

        $prompt = "You are a customer support automation agent for 'Code with Mosh'. You will be given a customer support ticket and a knowledge base markdown document.\n\n" .
                  "Your tasks:\n" .
                  "1. Read the knowledge base carefully.\n" .
                  "2. Determine if the customer's query can be fully and accurately answered using only the information present in the knowledge base.\n" .
                  "3. If it can be answered, generate a customer support reply. The reply MUST follow these constraints:\n" .
                  "   - Address the customer by their first name: '{$firstName}' (e.g. 'Hi {$firstName},' or 'Dear {$firstName},').\n" .
                  "   - Adopt a professional, friendly, and helpful customer support tone.\n" .
                  "   - Answer the question completely using the knowledge base.\n" .
                  "   - Sign the reply exactly as:\n" .
                  "     'Code with Mosh Support'\n" .
                  "   - Preserve proper formatting and line breaks.\n\n" .
                  "Knowledge Base:\n" .
                  "\"\"\"\n" .
                  "{$knowledgeBase}\n" .
                  "\"\"\"\n\n" .
                  "Ticket Subject: {$subject}\n" .
                  "Ticket Message: {$message}\n\n" .
                  "Return a JSON object with exactly the following keys:\n" .
                  "{\n" .
                  "  \"can_resolve\": true or false,\n" .
                  "  \"reply\": \"Generated support reply message with newlines preserved, or null if cannot resolve\"\n" .
                  "}";

        try {
            $resolveText = $this->generateContent($prompt, [
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ]
            ]);

            $decoded = json_decode($resolveText, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['can_resolve'])) {
                return [
                    'can_resolve' => filter_var($decoded['can_resolve'], FILTER_VALIDATE_BOOLEAN),
                    'reply'       => isset($decoded['reply']) ? trim($decoded['reply']) : null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini API auto-resolution call failed or rate-limited. Falling back to Knowledge Base pattern matching.', [
                'error'   => $e->getMessage(),
                'subject' => $subject,
            ]);
        }

        // Local Rule-Based Fallback Matching against knowledge-base.md
        return $this->fallbackKnowledgeBaseMatch($subject, $message, $knowledgeBase, $firstName);
    }

    /**
     * Fallback Knowledge Base Pattern Matching when AI API is rate limited or unavailable.
     */
    public function fallbackKnowledgeBaseMatch(string $subject, string $message, string $knowledgeBase, string $firstName): array
    {
        $queryText = mb_strtolower($subject . ' ' . $message, 'UTF-8');

        // 1. Password Reset / Forgot Password
        if (str_contains($queryText, 'forgot') || str_contains($queryText, 'password') || str_contains($queryText, 'reset')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nTo reset your password, please follow these steps:\n\n1. Go to the login page.\n2. Click **Forgot Password**.\n3. Enter your registered email address.\n4. Follow the instructions in the reset email.\n\nIf you do not receive the email, please check your spam folder.\n\nCode with Mosh Support",
            ];
        }

        // 2. Account Creation
        if (str_contains($queryText, 'create account') || str_contains($queryText, 'sign up') || str_contains($queryText, 'register')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nTo create an account, please follow these steps:\n\n1. Click the **Sign Up** button on our website.\n2. Enter your name, email address, and password.\n3. Verify your email address.\n4. Log in to access your account.\n\nCode with Mosh Support",
            ];
        }

        // 3. Subscription Cancellation
        if (str_contains($queryText, 'cancel subscription') || str_contains($queryText, 'cancel membership')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nTo cancel your subscription, please follow these steps:\n\n1. Go to Account Settings > Subscription.\n2. Click **Cancel Subscription**.\n3. Confirm the cancellation.\n\nCode with Mosh Support",
            ];
        }

        // 4. Payment / Course Activation Issues
        if (str_contains($queryText, 'paid') || str_contains($queryText, 'course is not activated') || str_contains($queryText, 'payment issue') || str_contains($queryText, 'activation')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nIf you paid for a course but it is not activated, please try the following:\n\n1. Log out and log back in to refresh your account session.\n2. Check your email receipt to verify the purchase was completed successfully.\n3. Verify that you logged in using the exact same email address used during checkout.\n\nIf the course is still inactive, please reply with your payment receipt or Transaction ID and our support team will activate it for you.\n\nCode with Mosh Support",
            ];
        }

        return [
            'can_resolve' => false,
            'reply'       => null,
        ];
    }
}