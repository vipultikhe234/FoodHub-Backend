<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AIController extends Controller
{
    /**
     * Generate an AI-powered image for offers/products.
     * This provides a curated, high-impact visual based on a simple prompt.
     */
    public function generateOfferImage(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
        ]);

        $promptText = trim($request->prompt);
        $cleanPrompt = strtolower($promptText);
        
        // 1. Intelligent Contextual Prompt Engineering
        $isFestival = str_contains($cleanPrompt, 'holi') || str_contains($cleanPrompt, 'festival') || str_contains($cleanPrompt, 'celebration') || str_contains($cleanPrompt, 'harvest');
        $isFood = str_contains($cleanPrompt, 'burger') || str_contains($cleanPrompt, 'pizza') || str_contains($cleanPrompt, 'sushi') || str_contains($cleanPrompt, 'biryani') || str_contains($cleanPrompt, 'thali');
        
        // Base context for offer banners
        $context = "Professional commercial food photography, cinematic lighting, 8k resolution, appetizing presentation, advertising banner style, vibrant colors.";
        
        if ($isFestival) {
            $context = "Vivid festive celebration banner, colorful background, professional photography, cinematic lighting, 8k resolution, high energy, commercial advertising style.";
        }
        
        // Generate a deterministic but unique-ish seed based on the prompt
        $seed = crc32($cleanPrompt) % 10000;
        
        // Construct the AI Generation Prompt
        $aiPrompt = "{$context} featuring '{$promptText}', highly detailed, studio quality, centered composition";
        
        // Using Pollinations AI (Flux Model) for REAL AI generation as requested
        $imageUrl = "https://image.pollinations.ai/prompt/" . urlencode($aiPrompt) . "?width=1200&height=600&seed={$seed}&nologo=true&model=flux";

        return response()->json([
            'success'   => true,
            'image_url' => $imageUrl,
            'metadata'  => [
                'engine' => 'Pollinations Flux AI',
                'prompt_engineered' => $aiPrompt,
                'seed' => $seed
            ]
        ]);
    }
}
