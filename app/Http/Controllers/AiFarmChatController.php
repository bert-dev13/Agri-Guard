<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FarmAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AiFarmChatController extends Controller
{
    public function show(FarmAssistantService $assistantService): View
    {
        /** @var User $user */
        $user = Auth::user();
        $context = $assistantService->buildAssistantContext($user);
        $starter = $assistantService->starterMessage($context);
        $sessionChat = (array) session('assistant_chat_history', []);
        if ($sessionChat === []) {
            $sessionChat[] = ['role' => 'assistant', 'payload' => $starter];
            session(['assistant_chat_history' => $sessionChat]);
        }

        return view('user.assistant.index', [
            'assistant_context' => $context,
            'assistant_chat_history' => $sessionChat,
        ]);
    }

    public function chat(Request $request, FarmAssistantService $assistantService): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json([
                'ok' => false,
                'message' => 'Please sign in again to continue.',
            ], 401);
        }

        $message = trim((string) $validated['message']);
        $context = $assistantService->buildAssistantContext($user);
        $sessionRows = (array) session('assistant_chat_history', []);
        $history = [];
        foreach ($sessionRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['role'] ?? '') !== 'user') {
                continue;
            }
            $text = trim((string) ($row['text'] ?? ''));
            if ($text !== '') {
                $history[] = ['role' => 'user', 'content' => $text];
            }
        }
        $history = array_slice($history, -10);

        try {
            $result = $assistantService->answer($user, $message, $history, $context);

            $sessionRows[] = ['role' => 'user', 'text' => $message];
            $sessionRows[] = ['role' => 'assistant', 'payload' => $result];
            $sessionRows = array_slice($sessionRows, -40);
            session(['assistant_chat_history' => $sessionRows]);

            return response()->json([
                'ok' => true,
                'message' => $result['message'],
                'meta' => $result['meta'],
            ]);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();

            return response()->json([
                'ok' => false,
                'message' => $msg !== '' ? $msg : 'AI advice is unavailable right now.',
                'error_code' => 'ai_context_unavailable',
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'I couldn’t reach the assistant right now. Please try again shortly.',
                'error_code' => 'ai_request_failed',
            ], 503);
        }
    }

    public function clear(Request $request, FarmAssistantService $assistantService): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['ok' => false, 'message' => 'Please sign in again.'], 401);
        }

        $context = $assistantService->buildAssistantContext($user);
        $starter = $assistantService->starterMessage($context);
        session(['assistant_chat_history' => [['role' => 'assistant', 'payload' => $starter]]]);

        return response()->json([
            'ok' => true,
            'history' => session('assistant_chat_history', []),
        ]);
    }
}
