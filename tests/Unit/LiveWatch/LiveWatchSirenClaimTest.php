<?php

namespace Tests\Unit\LiveWatch;

use PHPUnit\Framework\TestCase;

/**
 * Mirrors the transition_id claim rules used by resources/js/utils/liveWatchSiren.js
 * so duplicate offline→online alerts cannot replay for the same transition.
 */
class LiveWatchSirenClaimTest extends TestCase
{
    public function test_transition_id_can_be_claimed_only_once(): void
    {
        $claims = [];
        $claim = function (string $transitionId) use (&$claims): bool {
            if ($transitionId === '' || isset($claims[$transitionId])) {
                return false;
            }
            $claims[$transitionId] = time();

            return true;
        };

        $this->assertTrue($claim('tid-abc'));
        $this->assertFalse($claim('tid-abc'));
        $this->assertTrue($claim('tid-def'));
        $this->assertFalse($claim(''));
    }
}
