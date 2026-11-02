<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shop;

use App\Domain\Shop\VulnerableShop;
use PHPUnit\Framework\TestCase;

final class VulnerableShopTest extends TestCase
{
    public function test_lab_profile_deliberately_allows_reading_another_owners_order(): void
    {
        self::assertTrue((new VulnerableShop())->mayReadOrder(actorId: 1, ownerId: 2));
    }

    public function test_coupon_is_deliberately_applied_more_than_once(): void
    {
        self::assertSame(6400, (new VulnerableShop())->discountedTotal(10000, applications: 2));
    }

    public function test_review_is_deliberately_returned_without_escaping(): void
    {
        $payload = '<img src=x onerror=alert(1)>';

        self::assertSame($payload, (new VulnerableShop())->reviewForUnsafeRendering($payload));
    }

    public function test_reset_token_is_deliberately_predictable(): void
    {
        self::assertSame('reset-alice-2026', (new VulnerableShop())->resetToken('alice@example.test'));
    }
}
