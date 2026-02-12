<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;


class TtsController extends Controller
{
    public function index()
    {
        return view('tts.index');
    }
    
    public function exportPdf(Request $request)
    {
        $request->validate([
            'text' => 'required|string'
        ]);

        $text = $request->text;

        $pdf = Pdf::loadView('tts.pdf', [
            'text' => $text
        ]);

        return $pdf->download('processed-text.pdf');
    }

    public function convert(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:5000',
            'voice' => 'nullable|string'
        ]);

        $text = $request->text;
        $voice = $request->voice ?? 'Kore';

        $processedText = $this->applyRegexReplacements($text);

        \Log::info('Processed TTS text', [
            'text' => $processedText
        ]);

        $response = Http::withHeaders(headers: [
            'verify' => false, 
        ])->withHeaders([
            'x-goog-api-key' => config(key: 'services.gemini.api_key'),
            'Content-Type'  => 'application/json',
        ])->post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent',
            [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "say: {$processedText}"
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseModalities' => ['AUDIO'],
                    'speechConfig' => [
                        'voiceConfig' => [
                            'prebuiltVoiceConfig' => [
                                'voiceName' => $voice
                            ]
                        ]
                    ]
                ],
                'model' => 'gemini-2.5-flash-preview-tts'
            ]
        );

       
        // \Log::info('My Gemini raw response', config(key: 'services.gemini.key'));
        // \Log::info('Gemini API key loaded', [
        //     'key' => substr(config('services.gemini.api_key') ?? '', 0, 6) . '...'
        // ]);
        // \Log::info('My Gemini raw response', $response->json());

        if (!$response->successful()) {
            Log::error('Gemini TTS failed', $response->json());
            return response()->json(['success' => false], 500);
        }

        $audioBase64 = data_get(
            $response->json(),
            'candidates.0.content.parts.0.inlineData.data'
        );

        if (!$audioBase64) {
            return response()->json(['success' => false, 'error' => 'No audio returned'], 500);
        }

        // Decode PCM
        $pcmData = base64_decode($audioBase64);

        $filename = 'tts_' . time() . '.wav';
        $wavPath  = storage_path("app/public/audio/$filename");

        if (!file_exists(dirname($wavPath))) {
            mkdir(dirname($wavPath), 0755, true);
        }

        $this->pcmToWav($pcmData, $wavPath);

        return response()->json([
            'success'   => true,
            'filename'  => $filename,
            'audio_url' => asset("storage/audio/$filename"),
            'processed_text' => $processedText
        ]);
    }

    /**
     * Convert raw PCM to WAV (s16le, 24kHz, mono)
     */
    private function pcmToWav(string $pcmData, string $wavPath)
    {
        $sampleRate = 24000;
        $channels   = 1;
        $bits       = 16;

        $byteRate   = $sampleRate * $channels * ($bits / 8);
        $blockAlign = $channels * ($bits / 8);
        $dataSize   = strlen($pcmData);
        $chunkSize  = 36 + $dataSize;

        $header = pack(
            'A4VA4A4VvvVVvvA4V',
            'RIFF',            // ChunkID
            $chunkSize,        // ChunkSize
            'WAVE',            // Format
            'fmt ',            // Subchunk1ID
            16,                // Subchunk1Size
            1,                 // AudioFormat (PCM)
            $channels,         // NumChannels
            $sampleRate,       // SampleRate
            $byteRate,         // ByteRate
            $blockAlign,       // BlockAlign
            $bits,             // BitsPerSample
            'data',            // Subchunk2ID
            $dataSize          // Subchunk2Size
        );

        file_put_contents($wavPath, $header . $pcmData);
    }

    // private function applyRegexReplacements(string $text): string
    // {
    //     $path = storage_path('app/regex_replacements.json');

    //     if (!file_exists($path)) {
    //         return $text;
    //     }

    //     $rules = json_decode(file_get_contents($path), true);

    //     if (!is_array($rules)) {
    //         return $text;
    //     }

    //     foreach ($rules as $rule) {
    //         if (!isset($rule['pattern'], $rule['replace'])) {
    //             continue;
    //         }

    //         $text = preg_replace(
    //             '/' . $rule['pattern'] . '/i',
    //             $rule['replace'],
    //             $text
    //         );
    //     }

    //     return $text;
    // }

    private function applyRegexReplacements(string $text): string
    {
        $path = storage_path('app/regex_replacements.json');

        if (!file_exists($path)) {
            return $text;
        }

        $rules = json_decode(file_get_contents($path), true);

        if (!is_array($rules)) {
            return $text;
        }

        // =========================
        // 1️⃣ Replace biasa
        // =========================
        if (isset($rules['replace']) && is_array($rules['replace'])) {
            foreach ($rules['replace'] as $rule) {
                if (!isset($rule['pattern'], $rule['replacement'])) {
                    continue;
                }

                $text = preg_replace(
                    '/' . $rule['pattern'] . '/i',
                    $rule['replacement'],
                    $text
                );
            }
        }

        // =========================
        // 2️⃣ Tooltip istilah ilmiah
        // =========================
        if (isset($rules['tooltip']) && is_array($rules['tooltip'])) {
            foreach ($rules['tooltip'] as $rule) {
                if (!isset($rule['pattern'], $rule['explanation'])) {
                    continue;
                }

                $text = preg_replace_callback(
                    '/' . $rule['pattern'] . '/i',
                    function ($matches) use ($rule) {
                        return '<span class="tooltip-word" data-tooltip="' 
                            . htmlspecialchars($rule['explanation'], ENT_QUOTES, 'UTF-8') 
                            . '">' 
                            . $matches[0] 
                            . '</span>';
                    },
                    $text
                );
            }
        }

        return $text;
    }


    

}