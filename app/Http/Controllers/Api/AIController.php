<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    /**
     * Securely expand a simple name into a detailed professional prompt using Gemini.
     */
    private function expandPrompt(string $name, string $type = 'product'): string
    {
        $apiKey = config('services.ai.gemini');
        if (!$apiKey) return $name;

        $typeContext = [
            'category'    => 'a premium 3D clay icon, isometric, minimalist white background, modern app aesthetic',
            'product'     => 'high-end commercial photography, cinematic lighting, macro detail, professional presentation',
            'merchant'    => 'modern professional outlet storefront, elegant architecture, vibrant signage',
            'offer'       => 'dynamic promotional banner, high-contrast, professional graphic design',
            'grocery'     => 'fresh organic product display, vibrant colors, clean studio lighting, high resolution',
            'electronics' => 'sleek modern technology device, minimalist luxury aesthetic, studio product photography',
            'electrical'  => 'professional industrial equipment, sharp detail, technical lighting, clean background'
        ];

        $style = $typeContext[$type] ?? $typeContext['product'];
        $prompt = "You are a master prompt engineer for a global multi-merchant marketplace. Create a hyper-detailed, professional image generation prompt for the following category name: \"{$name}\". 
        Requirements:
        1. Style: High-end commercial photography or premium 3D claymorphism.
        2. Composition: Centered, minimalist, professional studio lighting, macro detail.
        3. Global Focus: Ensure the item is iconic and universally recognizable across all global sectors (Food, Tech, Fashion, Services).
        4. Quality: No text, no distorted faces, clean aesthetic.
        Keep the response under 60 words and return ONLY the expanded prompt text.";

        try {
            Log::info("Expanding prompt for: {$name}");
            $response = Http::timeout(5)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]]
            ]);

            if ($response->successful()) {
                $expandedText = $response->json('candidates.0.content.parts.0.text') ?? $name;
                Log::info("Prompt expanded successfully.");
                return $expandedText;
            }
        } catch (\Exception $e) {
            Log::error("Gemini failed: " . $e->getMessage());
        }

        return $name;
    }

    /**
     * Primary endpoint for AI image generation (Categories, Products, etc.)
     */
    public function generateImage(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'nullable|string|in:category,product,merchant,offer'
        ]);

        $name = $request->name;
        $type = $request->type ?? 'product';

        Log::info("Starting AI Image Generation - Name: {$name}, Type: {$type}");

        // 1. Expand Prompt Securely
        $detailedPrompt = $this->expandPrompt($name, $type);

        // 2. PRIMARY: OpenAI DALL-E 3
        $openaiKey = config('services.ai.openai');
        if ($openaiKey) {
            try {
                Log::info("Attempting OpenAI DALL-E 3...");
                $response = Http::timeout(7)->withHeaders([
                    'Authorization' => "Bearer {$openaiKey}",
                    'Content-Type'  => 'application/json',
                ])->post('https://api.openai.com/v1/images/generations', [
                    'model'  => 'dall-e-3',
                    'prompt' => $detailedPrompt,
                    'n'      => 1,
                    'size'   => '1024x1024'
                ]);

                if ($response->successful()) {
                    Log::info("OpenAI Success!");
                    return response()->json([
                        'success'   => true,
                        'image_url' => $response->json('data.0.url'),
                        'metadata'  => ['engine' => 'OpenAI DALL-E 3', 'prompt' => $detailedPrompt]
                    ]);
                } else {
                    Log::warning("OpenAI Failed: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::warning("OpenAI Exception: " . $e->getMessage());
            }
        }

        // 3. SECONDARY FALLBACK: Pollinations AI (AI Generated)
        // Note: Sometimes Pollinations can be unstable (500 errors).
        Log::info("Attempting Pollinations fallback...");
        $seed = rand(1000, 9999);
        $pollinationsUrl = "https://image.pollinations.ai/prompt/" . urlencode($detailedPrompt) . "?width=1024&height=1024&seed={$seed}&nologo=true";

        // 4. TERTIARY FALLBACK: Dynamic Image Discovery (Commercial Focus)
        // We append 'product,photography' to force a professional studio look.
        $searchKeyword = urlencode($name . " product photography commercial");
        $unsplashUrl = "https://loremflickr.com/1024/1024/{$searchKeyword}/all?lock=" . rand(1, 1000);

        // We return the Pollinations URL but with a frontend fallback mechanism would be complex, 
        // so we return the most stable one first if current tests show Pollinations is down.
        // For now, I'll return a smart combination.
        
        Log::info("Final image URL generated.");
        return response()->json([
            'success'   => true,
            'image_url' => $pollinationsUrl, 
            'fallback_url' => $unsplashUrl, // Provide a backup for the frontend
            'metadata'  => [
                'engine' => 'Pollinations AI (with Unsplash failsafe)',
                'prompt' => $detailedPrompt
            ]
        ]);
    }

    /**
     * Legacy/Simplified method for specific banners
     */
    public function generateOfferImage(Request $request)
    {
        return $this->generateImage($request->merge(['type' => 'offer']));
    }
}
