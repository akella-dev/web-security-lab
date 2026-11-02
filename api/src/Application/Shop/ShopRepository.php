<?php

declare(strict_types=1);

namespace App\Application\Shop;

interface ShopRepository
{
    /** @return array<string, mixed>|null */
    public function findOrder(int $orderId): ?array;

    /** @return list<array<string, mixed>> */
    public function reviews(string $studentId): array;

    /** @return array<string, mixed> */
    public function saveReview(string $studentId, string $author, string $content): array;

    public function couponApplications(string $cartId, string $code): int;

    public function saveCouponApplication(string $cartId, string $code): void;

    /** @param array<string, mixed> $metadata */
    public function logEvent(string $type, string $summary, array $metadata = []): void;
}
