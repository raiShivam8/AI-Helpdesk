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
        $primaryModel = config('services.gemini.model', 'gemini-flash-latest');
        $fallbackModels = array_values(array_unique([$primaryModel, 'gemini-flash-latest', 'gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-flash-lite-latest']));

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

        try {
            $classificationText = $this->generateContent($prompt, [
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ]
            ]);

            $decoded = json_decode($classificationText, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['category']) && isset($decoded['confidence'])) {
                return [
                    'category' => trim($decoded['category']),
                    'confidence' => (float) $decoded['confidence'],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini API classification failed or rate-limited. Using local fallback classification.', [
                'error' => $e->getMessage(),
                'subject' => $subject,
            ]);
        }

        return $this->fallbackClassifyTicket($subject, $message);
    }

    /**
     * Local fallback classification based on keyword matching
     */
    public function fallbackClassifyTicket(string $subject, string $message): array
    {
        $text = mb_strtolower($subject . ' ' . $message, 'UTF-8');

        if (str_contains($text, 'refund') || str_contains($text, 'money back')) {
            return ['category' => 'Refund', 'confidence' => 0.90];
        }
        if (str_contains($text, 'error') || str_contains($text, 'not loading') || str_contains($text, 'issue') || str_contains($text, 'bug') || str_contains($text, 'technical')) {
            return ['category' => 'Technical Issue', 'confidence' => 0.88];
        }
        if (str_contains($text, 'pay') || str_contains($text, 'bill') || str_contains($text, 'card') || str_contains($text, 'invoice') || str_contains($text, 'subscription')) {
            return ['category' => 'Billing', 'confidence' => 0.85];
        }
        if (str_contains($text, 'password') || str_contains($text, 'account') || str_contains($text, 'login') || str_contains($text, 'sign up')) {
            return ['category' => 'Account', 'confidence' => 0.88];
        }

        return ['category' => 'General Question', 'confidence' => 0.75];
    }

    /**
     * Auto-resolve a ticket using the knowledge base.
     */
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

        $prompt = "You are a customer support automation agent. You will be given a customer support ticket and a knowledge base markdown document.\n\n" .
                  "Your tasks:\n" .
                  "1. Read the knowledge base carefully.\n" .
                  "2. Determine if the customer's question or issue matches any topic, Q&A, or instructions in the knowledge base (including technical issues, error messages, website loading problems, account issues, refund requests, password reset, course activation, etc.).\n" .
                  "   - If the knowledge base contains relevant information, troubleshooting steps, or requests for required details for their issue, set \"can_resolve\": true.\n" .
                  "3. If can_resolve is true, generate a professional, friendly, and helpful customer support reply based on the knowledge base content:\n" .
                  "   - Address the customer by their first name: '{$firstName}' (e.g. 'Hi {$firstName},' or 'Dear {$firstName},').\n" .
                  "   - Answer the question or provide the relevant steps/instructions from the knowledge base.\n" .
                  "   - Sign the reply as:\n" .
                  "     'Support Team'\n" .
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

        // 1. Try Gemini API first if configured and available
        try {
            $resolveText = $this->generateContent($prompt, [
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ]
            ]);

            $decoded = json_decode($resolveText, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['can_resolve'])) {
                $canResolve = filter_var($decoded['can_resolve'], FILTER_VALIDATE_BOOLEAN);
                $reply = isset($decoded['reply']) ? trim($decoded['reply']) : null;

                if ($canResolve && !empty($reply)) {
                    Log::info('Gemini API auto-resolution succeeded', [
                        'subject' => $subject,
                    ]);
                    return [
                        'can_resolve' => true,
                        'reply'       => $reply,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini API auto-resolution call failed or rate-limited. Falling back to dynamic Knowledge Base matching.', [
                'error'   => $e->getMessage(),
                'subject' => $subject,
            ]);
        }

        // 2. Dynamic Knowledge Base matching directly on knowledgeBase content
        $dynamicMatch = $this->matchKnowledgeBaseDynamic($subject, $message, $knowledgeBase, $firstName);
        if ($dynamicMatch['can_resolve'] ?? false) {
            Log::info('Dynamic Knowledge Base match found for auto-resolution', [
                'subject' => $subject,
            ]);
            return $dynamicMatch;
        }

        // 3. Fallback Knowledge Base Pattern Matching as backstop
        return $this->fallbackKnowledgeBaseMatch($subject, $message, $knowledgeBase, $firstName);
    }

    /**
     * Parse knowledge-base.md content into Q&A entries and dynamically match ticket query.
     */
    public function matchKnowledgeBaseDynamic(string $subject, string $message, string $knowledgeBase, string $firstName): array
    {
        if (empty($knowledgeBase)) {
            return ['can_resolve' => false, 'reply' => null];
        }

        $queryText = mb_strtolower($subject . ' ' . $message, 'UTF-8');
        $sections  = $this->parseKnowledgeBaseMarkdown($knowledgeBase);

        $bestMatchScore = 0;
        $bestMatchAnswer = null;

        foreach ($sections as $section) {
            $question = mb_strtolower($section['question'], 'UTF-8');
            $answer   = $section['answer'];

            $score = 0;

            // Check key phrase matches in question
            $qKeywords = preg_split('/\W+/u', $question, -1, PREG_SPLIT_NO_EMPTY);
            $qKeywords = array_filter($qKeywords, fn($w) => mb_strlen($w) > 3 && !in_array($w, ['how', 'what', 'does', 'have', 'from', 'with', 'your', 'this', 'that', 'please']));

            $matchedCount = 0;
            foreach ($qKeywords as $kw) {
                if (str_contains($queryText, $kw)) {
                    $matchedCount++;
                }
            }

            if (count($qKeywords) > 0) {
                $score = $matchedCount / count($qKeywords);
            }

            // Bonus for specific topic phrases
            if (str_contains($question, 'password') && (str_contains($queryText, 'password') || str_contains($queryText, 'forgot') || str_contains($queryText, 'reset'))) {
                $score += 0.6;
            }
            if (str_contains($question, 'account') && (str_contains($queryText, 'account') || str_contains($queryText, 'sign up') || str_contains($queryText, 'register'))) {
                $score += 0.6;
            }
            if (str_contains($question, 'error') && (str_contains($queryText, 'error') || str_contains($queryText, 'issue') || str_contains($queryText, 'bug'))) {
                $score += 0.6;
            }
            if (str_contains($question, 'loading') && (str_contains($queryText, 'loading') || str_contains($queryText, 'not opening') || str_contains($queryText, 'site down'))) {
                $score += 0.6;
            }
            if (str_contains($question, 'refund') && str_contains($queryText, 'refund')) {
                $score += 0.6;
            }
            if (str_contains($question, 'activated') && (str_contains($queryText, 'paid') || str_contains($queryText, 'activated') || str_contains($queryText, 'course') || str_contains($queryText, 'payment'))) {
                $score += 0.6;
            }

            if ($score > $bestMatchScore && $score >= 0.5) {
                $bestMatchScore  = $score;
                $bestMatchAnswer = $answer;
            }
        }

        if ($bestMatchAnswer !== null) {
            $formattedReply = "Hi {$firstName},\n\n{$bestMatchAnswer}\n\nSupport Team";
            return [
                'can_resolve' => true,
                'reply'       => $formattedReply,
            ];
        }

        return ['can_resolve' => false, 'reply' => null];
    }

    /**
     * Parse markdown knowledge base into structured Q&A array.
     */
    protected function parseKnowledgeBaseMarkdown(string $content): array
    {
        $entries = [];
        $lines   = explode("\n", $content);

        $currentQuestion = null;
        $currentAnswerLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '### Q:') || str_starts_with($trimmed, '### ')) {
                if ($currentQuestion !== null && !empty($currentAnswerLines)) {
                    $entries[] = [
                        'question' => $currentQuestion,
                        'answer'   => trim(implode("\n", $currentAnswerLines)),
                    ];
                }
                $currentQuestion = trim(preg_replace('/^###\s*(Q:\s*)?/i', '', $trimmed));
                $currentAnswerLines = [];
            } elseif ($currentQuestion !== null) {
                if (str_starts_with($trimmed, '# ') || str_starts_with($trimmed, '#2 ') || str_starts_with($trimmed, '#3 ') || str_starts_with($trimmed, '---')) {
                    if (!empty($currentAnswerLines)) {
                        $entries[] = [
                            'question' => $currentQuestion,
                            'answer'   => trim(implode("\n", $currentAnswerLines)),
                        ];
                        $currentQuestion = null;
                        $currentAnswerLines = [];
                    }
                } else {
                    $currentAnswerLines[] = $line;
                }
            }
        }

        if ($currentQuestion !== null && !empty($currentAnswerLines)) {
            $entries[] = [
                'question' => $currentQuestion,
                'answer'   => trim(implode("\n", $currentAnswerLines)),
            ];
        }

        return $entries;
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
                'reply'       => "Hi {$firstName},\n\nTo reset your password, please follow these steps:\n\n1. Go to the login page.\n2. Click **Forgot Password**.\n3. Enter your registered email address.\n4. Follow the instructions in the reset email.\n\nIf you do not receive the email, please check your spam folder.\n\nSupport Team",
            ];
        }

        // 2. Account Creation
        if (str_contains($queryText, 'create account') || str_contains($queryText, 'sign up') || str_contains($queryText, 'register') || str_contains($queryText, 'new account')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nTo create an account, please follow these steps:\n\n1. Click the **Sign Up** button on our website.\n2. Enter your name, email address, and password.\n3. Verify your email address.\n4. Log in to access your account.\n\nSupport Team",
            ];
        }

        // 3. Profile Update
        if (str_contains($queryText, 'update profile') || str_contains($queryText, 'profile settings') || str_contains($queryText, 'change profile')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nTo update your profile information, please follow these steps:\n\n1. Log in to your account.\n2. Open **Profile Settings**.\n3. Update your required information.\n4. Save the changes.\n\nSupport Team",
            ];
        }

        // 4. Contact Support
        if (str_contains($queryText, 'contact support') || str_contains($queryText, 'reach support')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nTo help our support team assist you faster, please provide:\n\n* Your registered email address\n* A detailed description of the issue\n* Relevant screenshots if available\n\nOur support team will assist you as soon as possible.\n\nSupport Team",
            ];
        }

        // 5. Technical Error / Error Message (Matches Tickets #553 and #555!)
        if (str_contains($queryText, 'error') || str_contains($queryText, 'error message') || str_contains($queryText, 'getting a error') || str_contains($queryText, 'getting an error')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nIf you are experiencing an error message, please reply with the following details so we can investigate:\n\n* Screenshot of the error\n* Browser name and version\n* Steps to reproduce the issue\n\nThis information helps us investigate and resolve the issue faster.\n\nSupport Team",
            ];
        }

        // 6. Website Not Loading
        if (str_contains($queryText, 'not loading') || str_contains($queryText, 'website is not loading') || str_contains($queryText, 'site not opening')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nIf the website is not loading, please try the following troubleshooting steps:\n\n1. Refresh the page.\n2. Clear your browser cache.\n3. Check your internet connection.\n4. Try another browser or device.\n\nSupport Team",
            ];
        }

        // 7. Features Not Working
        if (str_contains($queryText, 'not working') || str_contains($queryText, 'feature not working')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nIf features are not working correctly, please try these possible solutions:\n\n1. Log out and log back in.\n2. Clear your browser cache and cookies.\n3. Disable browser extensions.\n4. Try another browser.\n\nSupport Team",
            ];
        }

        // 8. Application Running Slowly
        if (str_contains($queryText, 'slow') || str_contains($queryText, 'running slowly') || str_contains($queryText, 'lagging')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nIf the application is running slowly, please try:\n\n* Refreshing the page\n* Closing unnecessary browser tabs\n* Using a stable internet connection\n* Clearing browser cache\n\nSupport Team",
            ];
        }

        // 9. Subscription Cancellation
        if (str_contains($queryText, 'cancel subscription') || str_contains($queryText, 'cancel membership')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nTo cancel your subscription, please follow these steps:\n\n1. Go to Account Settings > Subscription.\n2. Click **Cancel Subscription**.\n3. Confirm the cancellation.\n\nSupport Team",
            ];
        }

        // 10. Refund Request / Eligibility / Process
        if (str_contains($queryText, 'refund')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nTo request or check on a refund, please provide:\n\n* Registered email address\n* Order ID / Invoice Number\n* Reason for the refund request\n\nRefund requests are typically reviewed within 3–7 business days.\n\nSupport Team",
            ];
        }

        // 11. Payment / Course Activation Issues
        if (str_contains($queryText, 'paid') || str_contains($queryText, 'course is not activated') || str_contains($queryText, 'payment issue') || str_contains($queryText, 'activation')) {
            return [
                'can_resolve' => true,
                'reply'       => "Hi {$firstName},\n\nIf you paid for a course but it is not activated, please try the following:\n\n1. Log out and log back in to refresh your account session.\n2. Check your email receipt to verify the purchase was completed successfully.\n3. Verify that you logged in using the exact same email address used during checkout.\n\nIf the course is still inactive, please reply with your payment receipt or Transaction ID and our support team will activate it for you.\n\nSupport Team",
            ];
        }

        return [
            'can_resolve' => false,
            'reply'       => null,
        ];
    }
}