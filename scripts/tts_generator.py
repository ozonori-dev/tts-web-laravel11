#!/usr/bin/env python3

import sys
import wave
import os
from google import genai
from google.genai import types

def wave_file(filename, pcm, channels=1, rate=24000, sample_width=2):
    with wave.open(filename, "wb") as wf:
        wf.setnchannels(channels)
        wf.setsampwidth(sample_width)
        wf.setframerate(rate)
        wf.writeframes(pcm)

def generate_tts(text, output_path, api_key, voice_name='Kore'):
    try:
        os.environ['GEMINI_API_KEY'] = api_key
        
        client = genai.Client()
        
        response = client.models.generate_content(
            model="gemini-2.5-flash-preview-tts",
            contents=text,
            config=types.GenerateContentConfig(
                response_modalities=["AUDIO"],
                speech_config=types.SpeechConfig(
                    voice_config=types.VoiceConfig(
                        prebuilt_voice_config=types.PrebuiltVoiceConfig(
                            voice_name=voice_name,
                        )
                    )
                ),
            )
        )
        
        data = response.candidates[0].content.parts[0].inline_data.data
        wave_file(output_path, data)
        
        print(f"SUCCESS: Audio saved to {output_path}")
        return 0
        
    except Exception as e:
        print(f"ERROR: {str(e)}", file=sys.stderr)
        return 1

if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("Usage: python tts_generator.py <text> <output_path> <api_key> [voice_name]", file=sys.stderr)
        sys.exit(1)
    
    text = sys.argv[1]
    output_path = sys.argv[2]
    api_key = sys.argv[3]
    voice_name = sys.argv[4] if len(sys.argv) > 4 else 'Kore'
    
    sys.exit(generate_tts(text, output_path, api_key, voice_name))