<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <title>Text to Speech - Gemini AI</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-2xl shadow-xl p-8">
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Text to Speech</h1>
                    <p class="text-gray-600 mb-6">Powered by Gemini AI</p>

                    <div class="space-y-6">
                        <div>
                            <label for="text" class="block text-sm font-medium text-gray-700 mb-2">
                                Enter your text
                            </label>
                            <textarea
                                id="text"
                                rows="8"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                                placeholder="Type your text here..."
                            ></textarea>
                            <p class="text-sm text-gray-500 mt-1">Max 5000 characters</p>
                        </div>

                        <div>
                            <label for="voice" class="block text-sm font-medium text-gray-700 mb-2">
                                Voice Selection
                            </label>
                            <select
                                id="voice"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            >
                                <option value="Kore" selected>Kore</option>
                                <option value="Charon">Charon</option>
                                <option value="Aoede">Aoede</option>
                                <option value="Fenrir">Fenrir</option>
                                <option value="Puck">Puck</option>
                            </select>
                        </div>

                        <div class="flex gap-4">
                            <button
                                id="convertBtn"
                                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span id="convertText">Convert</span>
                                <span id="convertLoading" class="hidden">Converting...</span>
                            </button>
                            <!-- <button
                                id="exportBtn"
                                class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled
                            >
                                Export Audio
                            </button> -->

                            <button
                                id="exportBtn"
                                class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                
                                >
                                Export PDF
                            </button>
                        </div>

                        <div id="alertBox" class="hidden"></div>

                        <div id="audioPlayer" class="hidden">
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <p class="text-sm font-medium text-gray-700 mb-2">Preview:</p>
                                <audio id="audio" controls class="w-full"></audio>
                            </div>
                        </div>

                        <div id="processedText" class="hidden">
                            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200 relative overflow-visible">
                                <p class="text-sm font-medium text-blue-900 mb-2">Processed Text:</p>
                                <p id="processedTextContent" class="text-sm text-blue-800"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            let currentFilename = null;
            let processedTextValue = null;

            const convertBtn = document.getElementById('convertBtn');
            const exportBtn = document.getElementById('exportBtn');
            const textArea = document.getElementById('text');
            const audioPlayer = document.getElementById('audioPlayer');
            const audio = document.getElementById('audio');
            const alertBox = document.getElementById('alertBox');
            const processedTextDiv = document.getElementById('processedText');
            const processedTextContent = document.getElementById('processedTextContent');

            convertBtn.addEventListener('click', async () => {
                const text = textArea.value.trim();
                const voice = document.getElementById('voice').value;

                if (!text) {
                    showAlert('Please enter some text', 'error');
                    return;
                }

                setLoading(true);
                hideAlert();
                audioPlayer.classList.add('hidden');
                processedTextDiv.classList.add('hidden');

                try {
                    const response = await fetch('{{ route("tts.convert") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ text, voice })
                    });

                    // Debug: log the raw response
                    const responseText = await response.text();
                    console.log('Raw response:', responseText);
                    
                    let data;
                    try {
                        data = JSON.parse(responseText);
                    } catch (e) {
                        showAlert('Server returned HTML instead of JSON. Check console for details.', 'error');
                        console.error('Response was:', responseText);
                        return;
                    }

                    if (data.success) {
                        console.log('Raw response:', 'data success');

                        processedTextValue = data.processed_text;
                        currentFilename = data.filename;
                        audio.src = data.audio_url;
                        audioPlayer.classList.remove('hidden');
                        // const box = document.getElementById('processedText');
                        // const content = document.getElementById('processedTextContent');

                        // content.textContent = data.processed_text;
                        exportBtn.disabled = false;
                        
                        processedTextContent.innerHTML = data.processed_text;
                        processedTextDiv.classList.remove('hidden');
                        
                        showAlert('Audio generated successfully!', 'success');
                    } else {
                        showAlert(data.error || 'Failed to generate audio', 'error');
                    }
                } catch (error) {
                    showAlert('An error occurred: ' + error.message, 'error');
                } finally {
                    setLoading(false);
                }
            });
            // exportBtn.disabled = false;
            exportBtn.addEventListener('click', async () => {
                console.log('Export PDF clicked');
                if (!processedTextValue) return;

                // processedTextValue = 'halo g';
                // processedTextValue = textArea.value.trim();
                // console.log(text);

                const response = await fetch('/export-pdf', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        text: processedTextValue
                    })
                });

                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);

                const a = document.createElement('a');
                a.href = url;
                a.download = 'processed-text.pdf';
                a.click();

                window.URL.revokeObjectURL(url);
            });



            function setLoading(loading) {
                convertBtn.disabled = loading;
                document.getElementById('convertText').classList.toggle('hidden', loading);
                document.getElementById('convertLoading').classList.toggle('hidden', !loading);
            }

            function showAlert(message, type) {
                const bgColor = type === 'success' ? 'bg-green-100 border-green-400 text-green-800' : 'bg-red-100 border-red-400 text-red-800';
                alertBox.className = `border-l-4 p-4 rounded ${bgColor}`;
                alertBox.textContent = message;
                alertBox.classList.remove('hidden');
            }

            function hideAlert() {
                alertBox.classList.add('hidden');
            }
        </script>
    </body>
</html>