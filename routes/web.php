<?php

use App\Http\Controllers\TtsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TtsController::class, 'index'])->name('tts.index');
Route::post('/convert', [TtsController::class, 'convert'])->name('tts.convert');
// Route::get('/export/{filename}', [TtsController::class, 'export'])->name('tts.export');
Route::post('/export-pdf', [TtsController::class, 'exportPdf']);
