<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ZESCO Copilot — a Copilot-style chat over the knowledge base, backed by the
 * dart-rag agentic endpoint. Auth is provided by the host application; this
 * controller scopes data by an opaque user reference (header or session).
 */
class CopilotController extends Controller
{
    public function index(Request $request)
    {
        // Ensure a stable per-browser identity so saved conversations survive
        // reloads even when the host app does not supply X-User-Ref.
        if (! $request->cookie('copilot_uid')) {
            Cookie::queue(Cookie::forever('copilot_uid', (string) Str::uuid()));
        }

        return view('copilot');
    }

    // --- knowledge-base API plumbing -------------------------------------

    private function kbBase(): string
    {
        $base = rtrim(config('services.kb.url'), '/');
        if ($name = config('services.kb.name')) {
            $base .= '/kb/'.rawurlencode($name);
        }

        return $base;
    }

    private function kbClient()
    {
        $c = Http::acceptJson()->timeout(60);
        if ($key = config('services.kb.key')) {
            $c = $c->withToken($key);
        }

        return $c;
    }

    /** Opaque per-user key. The host app may set X-User-Ref; else a cookie. */
    private function userRef(Request $request): string
    {
        if ($ref = $request->header('X-User-Ref')) {
            return $ref;
        }

        return 'uid:'.($request->cookie('copilot_uid') ?? 'anon');
    }

    private function ownedOr404(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->user_ref === $this->userRef($request), 404);
    }

    // --- conversations ---------------------------------------------------

    public function conversations(Request $request): JsonResponse
    {
        $items = Conversation::where('user_ref', $this->userRef($request))
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at']);

        return response()->json($items);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $c = Conversation::create([
            'user_ref' => $this->userRef($request),
            'title' => null,
        ]);

        return response()->json(['id' => $c->id, 'title' => $c->title]);
    }

    public function deleteConversation(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ownedOr404($request, $conversation);
        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ownedOr404($request, $conversation);

        return response()->json(
            $conversation->messages()->get(['role', 'content', 'meta'])
        );
    }

    /**
     * Posts a user message and streams the agent's reply (SSE), persisting both
     * the user message and the final assistant message with its citations.
     */
    public function stream(Request $request, Conversation $conversation): StreamedResponse
    {
        $this->ownedOr404($request, $conversation);
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        $message = $data['message'];

        // Prior turns become the agent's conversation history.
        $history = $conversation->messages()
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        $conversation->messages()->create(['role' => 'user', 'content' => $message]);
        if (! $conversation->title) {
            $conversation->title = Str::limit($message, 60);
        }
        $conversation->save(); // bumps updated_at

        $url = $this->kbBase().'/chat/agent/stream';
        $key = config('services.kb.key');

        return response()->stream(function () use ($url, $key, $message, $history, $conversation) {
            @set_time_limit(0); // the agent may stream for well over 30s
            ignore_user_abort(true);
            $full = '';
            $sources = [];
            $passages = [];
            $steps = [];
            $buffer = '';

            $headers = ['Accept' => 'text/event-stream', 'Content-Type' => 'application/json'];
            if ($key) {
                $headers['Authorization'] = 'Bearer '.$key;
            }

            try {
                $resp = (new \GuzzleHttp\Client())->post($url, [
                    'headers' => $headers,
                    'json' => ['message' => $message, 'history' => $history],
                    'stream' => true,
                    'read_timeout' => 300,
                ]);
                $body = $resp->getBody();
                while (! $body->eof()) {
                    $chunk = $body->read(2048);
                    echo $chunk;
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    @flush();

                    $buffer .= $chunk;
                    while (($pos = strpos($buffer, "\n\n")) !== false) {
                        $block = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 2);
                        [$event, $json] = $this->parseSse($block);
                        $d = $json !== '' ? json_decode($json, true) : null;
                        if (! is_array($d)) {
                            continue;
                        }
                        if ($event === 'delta' && isset($d['text'])) {
                            $full .= $d['text'];
                        } elseif ($event === 'meta') {
                            $sources = $d['sources'] ?? [];
                            $passages = $d['passages'] ?? [];
                        } elseif ($event === 'tool') {
                            $steps[] = $d;
                        }
                    }
                }
            } catch (\Throwable $e) {
                echo 'event: error'."\n";
                echo 'data: '.json_encode(['error' => $e->getMessage()])."\n\n";
            }

            // Persist the assistant turn with its citations + tool trace.
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $full,
                'meta' => ['sources' => $sources, 'passages' => $passages, 'steps' => $steps],
            ]);
            $conversation->touch();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** @return array{0:string,1:string} [event, data] */
    private function parseSse(string $block): array
    {
        $event = 'message';
        $data = '';
        foreach (explode("\n", $block) as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data .= trim(substr($line, 5));
            }
        }

        return [$event, $data];
    }

    // --- document library (proxies to the KB API) ------------------------

    public function documents(): JsonResponse
    {
        try {
            $r = $this->kbClient()->get($this->kbBase().'/sources');

            return response()->json([
                'sources' => $r->json('sources', []),
                'documents' => $r->json('documents', 0),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:255'],
            'text' => ['required', 'string'],
        ]);
        try {
            $r = $this->kbClient()->timeout(120)
                ->post($this->kbBase().'/ingest/text', $data);

            return response()->json(['ok' => $r->successful()], $r->successful() ? 200 : 502);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function deleteDocument(Request $request): JsonResponse
    {
        $data = $request->validate(['source' => ['required', 'string']]);
        try {
            $this->kbClient()->post($this->kbBase().'/sources/delete', $data);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
