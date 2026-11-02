<?php

declare(strict_types=1);

namespace App\Domain\Shop;

/**
 * Небезопасные правила существуют только как учебная мишень.
 * Их тесты документируют ожидаемое уязвимое поведение стенда.
 */
final class VulnerableShop
{
    public function mayReadOrder(int $actorId, int $ownerId): bool
    {
        return true;
    }

    public function discountedTotal(int $total, int $applications): int
    {
        for ($index = 0; $index < $applications; $index++) {
            $total = (int) round($total * 0.8);
        }

        return $total;
    }

    public function reviewForUnsafeRendering(string $content): string
    {
        return $content;
    }

    public function resetToken(string $email): string
    {
        $localPart = explode('@', strtolower($email), 2)[0];

        return 'reset-' . preg_replace('/[^a-z0-9]/', '', $localPart) . '-2026';
    }
}
