<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslationController extends Controller
{
    /**
     * Translate a short text (e.g. a lead/deal comment) into English.
     * Uses Google Cloud Translation, with OpenAI as an optional fallback when configured.
     * Results are cached so the same comment is only translated once.
     */
    public function translate(Request $request): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:10000',
            'target' => 'nullable|string|in:en,ar',
        ]);

        $text = trim($request->input('text'));
        $target = $request->input('target', 'en');
        $cacheKey = 'translation:' . $target . ':' . sha1($text);

        $translated = Cache::get($cacheKey);

        if ($translated === null) {
            $translated = $this->translateWithGoogle($text, $target)
                ?? $this->translateWithOpenAi($text, $target);

            if ($translated === null) {
                return response()->json(['message' => 'Translation service is unavailable. Please try again later.'], 503);
            }

            Cache::put($cacheKey, $translated, now()->addDays(30));
        }

        return response()->json(['translated' => $translated]);
    }

    private function translateWithOpenAi(string $text, string $target): ?string
    {
        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $language = $target === 'ar' ? 'Arabic' : 'English';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "You translate CRM comments written by real-estate agents into {$language}. "
                                . 'Reply with the translation only. Keep names, numbers, phone numbers, URLs, '
                                . '@mentions and any markup/BBCode tags unchanged.',
                        ],
                        ['role' => 'user', 'content' => $text],
                    ],
                    'temperature' => 0,
                ]);

            $result = trim((string) data_get($response->json(), 'choices.0.message.content'));

            return $response->successful() && $result !== '' ? $result : null;
        } catch (\Throwable $e) {
            Log::warning('OpenAI translation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function translateWithGoogle(string $text, string $target): ?string
    {
        $apiKey = config('services.google_translate.api_key');
        if (empty($apiKey)) {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->post('https://translation.googleapis.com/language/translate/v2?key=' . urlencode($apiKey), [
                    'q' => $text,
                    'target' => $target,
                    'format' => 'text',
                ]);

            if (!$response->successful()) {
                Log::warning('Google translation failed', [
                    'status' => $response->status(),
                    'error' => data_get($response->json(), 'error.message'),
                ]);
                return null;
            }

            $result = (string) data_get($response->json(), 'data.translations.0.translatedText', '');

            return trim($result) !== '' ? $result : null;
        } catch (\Throwable $e) {
            Log::warning('Google translation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
