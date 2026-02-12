<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiTtsService
{
    private string $apiKey;
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    /**
     * Generate speech from text using Gemini AI
     */
    public function generateSpeech(string $text, string $outputPath, string $voiceName = 'Kore'): bool
    {
        try {
            $url = $this->apiUrl . '/gemini-2.0-flash-exp:generateContent?key=' . $this->apiKey;

            Log::info('Calling Gemini API', ['url' => $url]);

            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $text]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseModalities' => ['AUDIO'],
                        'speechConfig' => [
                            'voiceConfig' => [
                                'prebuiltVoiceConfig' => [
                                    'voiceName' => $voiceName
                                ]
                            ]
                        ]
                    ]
                ]);

            Log::info('API Response Status', ['status' => $response->status()]);

            if (!$response->successful()) {
                Log::error('Gemini API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }

            $data = $response->json();

            Log::info('API Response Structure', ['data' => json_encode($data)]);

            // Extract audio data from response
            if (!isset($data['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
                Log::error('Invalid Gemini API response structure', ['data' => $data]);
                return false;
            }

            $audioBase64 = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'];
            $audioData = base64_decode($audioBase64);

            // Create WAV file
            $this->createWavFile($outputPath, $audioData);

            return true;

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('TTS HTTP Error', [
                'message' => $e->getMessage(),
                'response' => $e->response ? $e->response->body() : 'No response'
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('TTS Generation Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Create WAV file with proper headers
     */
    private function createWavFile(string $filename, string $pcmData, int $channels = 1, int $rate = 24000, int $sampleWidth = 2): void
    {
        $dataSize = strlen($pcmData);
        $fileSize = $dataSize + 36;

        // WAV header
        $header = pack(
            'a4Va4a4VvvVVvva4V',
            'RIFF',           // ChunkID
            $fileSize,        // ChunkSize
            'WAVE',           // Format
            'fmt ',           // Subchunk1ID
            16,               // Subchunk1Size (PCM)
            1,                // AudioFormat (PCM)
            $channels,        // NumChannels
            $rate,            // SampleRate
            $rate * $channels * $sampleWidth, // ByteRate
            $channels * $sampleWidth,         // BlockAlign
            $sampleWidth * 8, // BitsPerSample
            'data',           // Subchunk2ID
            $dataSize         // Subchunk2Size
        );

        file_put_contents($filename, $header . $pcmData);
    }

    /**
     * Get available voice names
     */
    public function getAvailableVoices(): array
    {
        return [
            'Kore',
            'Charon',
            'Aoede',
            'Fenrir',
            'Puck'
        ];
    }
}