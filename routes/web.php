<?php

use App\Http\Controllers\CopilotController;
use Illuminate\Support\Facades\Route;

// ZESCO Copilot — general-usage agentic knowledge-base chat.
Route::get('/', [CopilotController::class, 'index']);

Route::prefix('api')->group(function () {
    Route::get('conversations', [CopilotController::class, 'conversations']);
    Route::post('conversations', [CopilotController::class, 'createConversation']);
    Route::delete('conversations/{conversation}', [CopilotController::class, 'deleteConversation']);
    Route::get('conversations/{conversation}/messages', [CopilotController::class, 'messages']);
    Route::post('conversations/{conversation}/stream', [CopilotController::class, 'stream']);

    Route::get('documents', [CopilotController::class, 'documents']);
    Route::post('documents', [CopilotController::class, 'uploadDocument']);
    Route::delete('documents', [CopilotController::class, 'deleteDocument']);
});
