<?php

namespace Tests\Unit;

use App\Modules\Core\Support\InstituicaoContext;
use PHPUnit\Framework\Attributes\After;
use Tests\TestCase;

class InstituicaoContextTest extends TestCase
{
    #[After]
    protected function tearDownContext(): void
    {
        InstituicaoContext::clear();
    }

    public function test_starts_without_context(): void
    {
        $this->assertFalse(InstituicaoContext::has());
        $this->assertNull(InstituicaoContext::id());
        $this->assertNull(InstituicaoContext::slug());
    }

    public function test_set_and_clear(): void
    {
        InstituicaoContext::set(1, 'escola-a');

        $this->assertTrue(InstituicaoContext::has());
        $this->assertSame(1, InstituicaoContext::id());
        $this->assertSame('escola-a', InstituicaoContext::slug());

        InstituicaoContext::clear();

        $this->assertFalse(InstituicaoContext::has());
    }

    public function test_run_with_restores_previous_context(): void
    {
        InstituicaoContext::set(1, 'escola-a');

        $result = InstituicaoContext::runWith(2, 'escola-b', function () {
            $this->assertSame(2, InstituicaoContext::id());
            $this->assertSame('escola-b', InstituicaoContext::slug());

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(1, InstituicaoContext::id());
        $this->assertSame('escola-a', InstituicaoContext::slug());
    }
}
