<?php

namespace Tests\Feature;

use App\Livewire\DiagnosticHandleRequests;
use Livewire\Mechanisms\HandleRequests\HandleRequests as LivewireHandleRequests;
use Tests\TestCase;

/**
 * TEMPORARY — verifies the diagnostic logging is wired correctly.
 * Delete this file together with DiagnosticHandleRequests.php and the
 * AppServiceProvider::boot() instance() call once the 419 root cause is found.
 */
class DiagnosticTest extends TestCase
{
    // Verifies that AppServiceProvider replaces the Livewire HandleRequests
    // singleton with our DiagnosticHandleRequests subclass. If this test
    // passes, TypeError exceptions during Livewire updates are logged before
    // being converted to HTTP 419 (rather than silently swallowed).
    public function test_livewire_diagnostic_handle_requests_is_bound(): void
    {
        $instance = app(LivewireHandleRequests::class);

        $this->assertInstanceOf(
            DiagnosticHandleRequests::class,
            $instance,
            'DiagnosticHandleRequests must be bound as the Livewire HandleRequests singleton.'
        );
    }

    // Verifies that DiagnosticHandleRequests actually overrides handleUpdate()
    // (not relying on parent — we need the logging catch block to be present).
    public function test_diagnostic_handle_requests_declares_own_handle_update(): void
    {
        $ref    = new \ReflectionClass(DiagnosticHandleRequests::class);
        $method = $ref->getMethod('handleUpdate');

        $this->assertEquals(
            DiagnosticHandleRequests::class,
            $method->getDeclaringClass()->getName(),
            'handleUpdate() must be declared on DiagnosticHandleRequests itself, not inherited.'
        );
    }
}
