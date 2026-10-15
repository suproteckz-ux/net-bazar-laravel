<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportScriptsAndAssets\SupportScriptsAndAssets;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

use function Livewire\trigger;

/**
 * TEMPORARY DIAGNOSTIC — remove after root cause is identified.
 *
 * Extends Livewire's HandleRequests to log the PHP exception that Livewire
 * silently converts to HTTP 419 (abort(419)) when APP_DEBUG=false.
 * The original Livewire code catches \TypeError and calls abort(419) without
 * any logging, making it impossible to identify the root cause in production.
 *
 * Registered in AppServiceProvider::boot(). To remove:
 *   1. Delete this file.
 *   2. Remove the $this->app->instance() call from AppServiceProvider::boot().
 *   3. Delete DiagnosticTest::test_livewire_diagnostic_handle_requests_is_bound().
 */
class DiagnosticHandleRequests extends HandleRequests
{
    public function handleUpdate(): mixed
    {
        $requestPayload = request('components');

        if (! is_array($requestPayload) || empty($requestPayload)) {
            abort(404);
        }

        foreach ($requestPayload as $component) {
            if (! is_array($component)
                || ! is_string($component['snapshot'] ?? null)
                || ! is_array($component['updates'] ?? null)
                || ! is_array($component['calls'] ?? null)
            ) {
                abort(404);
            }
        }

        $finish = trigger('request', $requestPayload);

        $requestPayload = $finish($requestPayload);

        $componentResponses = [];

        foreach ($requestPayload as $componentPayload) {
            $snapshot = json_decode($componentPayload['snapshot'], associative: true);
            $updates  = $componentPayload['updates'];
            $calls    = $componentPayload['calls'];

            try {
                [$snapshot, $effects] = app('livewire')->update($snapshot, $updates, $calls);
            } catch (\TypeError $e) {
                // ── Diagnostic logging (no sensitive data logged) ─────────────
                // Intentionally excludes: $snapshot['data'] (form values),
                // $updates (user-submitted values), cookie/token values.
                Log::error('[diag] Livewire TypeError → 419', [
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                    'file'      => ltrim(str_replace(base_path(), '', $e->getFile()), '/\\'),
                    'line'      => $e->getLine(),
                    'trace'     => collect($e->getTrace())
                        ->take(10)
                        ->map(fn(array $f): string =>
                            ltrim(str_replace(base_path(), '', ($f['file'] ?? '[internal]')), '/\\')
                            . ':' . ($f['line'] ?? '?')
                            . '  ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '()')
                        )
                        ->values()
                        ->all(),
                    // Component class name — safe identifier, not user data.
                    'component' => $snapshot['memo']['name'] ?? '(unknown)',
                    // Call descriptors: for callSchemaComponentMethod we log the
                    // component key (e.g. "form.image_path") and target method name
                    // (e.g. "getUploadedFiles") — these are code identifiers, not user input.
                    // For all other methods only the method name and param count are logged.
                    'calls' => array_map(function (array $c): array {
                        $method = $c['method'] ?? '?';
                        $params = $c['params'] ?? [];

                        if ($method === 'callSchemaComponentMethod') {
                            return [
                                'method'         => $method,
                                'component_key'  => $params[0] ?? '?',
                                'target_method'  => $params[1] ?? '?',
                                'extra_args'     => count($params) > 2 ? count($params) - 2 : 0,
                            ];
                        }

                        return [
                            'method'      => $method,
                            'param_count' => count($params),
                        ];
                    }, $calls),
                ]);
                // ── End diagnostic logging ─────────────────────────────────────

                if (config('app.debug')) {
                    throw $e;
                }

                abort(419);
            }

            $componentResponses[] = [
                'snapshot' => json_encode($snapshot),
                'effects'  => $effects,
            ];
        }

        $responsePayload = [
            'components' => $componentResponses,
            'assets'     => SupportScriptsAndAssets::getAssets(),
        ];

        $finish = trigger('response', $responsePayload);

        return $finish($responsePayload);
    }
}
