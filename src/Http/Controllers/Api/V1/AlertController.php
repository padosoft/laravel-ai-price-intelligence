<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\Alert;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AlertController
{
    public function index(Request $request): JsonResponse
    {
        $alerts = Alert::query()
            ->when($request->boolean('unacknowledged'), fn ($q) => $q->whereNull('acknowledged_at'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($alerts);
    }

    /**
     * Server-Sent Events stream for the admin's live alert feed. Emits any alerts newer
     * than the client's Last-Event-ID (or ?after=) as `alert` events, then a `heartbeat`,
     * and closes. The browser EventSource reconnects automatically (sending Last-Event-ID),
     * so this stays FPM/proxy-friendly without holding a long-lived connection.
     */
    public function stream(Request $request): StreamedResponse
    {
        $after = (int) ($request->header('Last-Event-ID') ?? $request->integer('after', 0));

        $alerts = Alert::query()
            ->where('id', '>', $after)
            ->whereNull('acknowledged_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $response = new StreamedResponse(function () use ($alerts): void {
            foreach ($alerts as $alert) {
                echo 'id: '.$alert->id."\n";
                echo "event: alert\n";
                echo 'data: '.json_encode($alert->toArray())."\n\n";
            }

            echo "event: heartbeat\n";
            echo 'data: '.json_encode(['at' => now()->toIso8601String()])."\n\n";

            if (function_exists('ob_get_level') && ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    public function acknowledge(int $id): JsonResponse
    {
        $alert = Alert::query()->findOrFail($id);
        $alert->acknowledge();

        return response()->json(['data' => $alert]);
    }
}
