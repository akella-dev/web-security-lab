<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Lab\LabRepository;
use App\Application\Lab\LabService;
use App\Application\Shop\ShopRepository;
use App\Application\Shop\ShopService;
use App\Domain\Shop\VulnerableShop;
use PHPUnit\Framework\TestCase;

final class ShopServiceTest extends TestCase
{
    public function test_legacy_order_screen_does_not_bypass_the_new_idor_chain(): void
    {
        [$shop, $missions] = $this->shop();

        $shop->order(1002, 1, 'student-a');

        self::assertFalse($missions->has('student-a', 'idor-chain'));
    }

    public function test_saving_an_executable_review_completes_only_the_xss_mission(): void
    {
        [$shop, $missions] = $this->shop();

        $shop->addReview('Иван', '<img src=x onerror=alert(1)>', 'student-a');

        self::assertTrue($missions->has('student-a', 'stored-xss'));
        self::assertFalse($missions->has('student-a', 'idor-chain'));
    }

    public function test_sequential_legacy_coupon_calls_do_not_complete_the_race_mission(): void
    {
        [$shop, $missions] = $this->shop();

        $shop->applyCoupon('student-a', 'HONEY20', 'student-a');
        $shop->applyCoupon('student-a', 'HONEY20', 'student-a');

        self::assertFalse($missions->has('student-a', 'coupon-race'));
    }

    public function test_legacy_reset_check_does_not_skip_the_new_reconnaissance_step(): void
    {
        [$shop, $missions] = $this->shop();

        $result = $shop->verifyPasswordReset('alice@example.test', 'reset-alice-2026', 'student-a');

        self::assertTrue($result['verified']);
        self::assertFalse($missions->has('student-a', 'reset-token'));
    }

    public function test_reviews_are_isolated_between_student_sessions(): void
    {
        [$shop] = $this->shop();

        $shop->addReview('Иван', '<img src=x onerror=alert(1)>', 'student-a');

        self::assertCount(1, $shop->reviews('student-a'));
        self::assertCount(0, $shop->reviews('student-b'));
    }

    /** @return array{ShopService, MissionSpyRepository} */
    private function shop(): array
    {
        $repository = new MissionSpyRepository();
        $lab = new LabService($repository);

        return [new ShopService($repository, new VulnerableShop(), $lab), $repository];
    }
}

final class MissionSpyRepository implements LabRepository, ShopRepository
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $submissions = [];

    /** @var array<string, int> */
    private array $coupons = [];

    /** @var list<array<string, mixed>> */
    private array $savedReviews = [];

    public function has(string $studentId, string $challenge): bool
    {
        return isset($this->submissions[$studentId][$challenge]);
    }

    public function saveSubmission(string $studentId, string $challenge, array $proof): void
    {
        $this->submissions[$studentId][$challenge] = $proof;
    }

    public function completedChallenges(): array
    {
        return [];
    }

    public function completedChallengesForStudent(string $studentId): array
    {
        return array_keys($this->submissions[$studentId] ?? []);
    }

    public function recentEvents(int $limit = 20): array
    {
        return [];
    }

    public function studentOverview(): array
    {
        return [];
    }

    public function saveRequest(string $studentId, array $request, array $response): void
    {
    }

    public function requestHistory(string $studentId, int $limit = 50): array
    {
        return [];
    }

    public function reset(): void
    {
        $this->submissions = [];
        $this->coupons = [];
    }

    public function findOrder(int $orderId): ?array
    {
        return [
            'id' => $orderId,
            'owner_id' => $orderId === 1001 ? 1 : 2,
            'owner' => $orderId === 1001 ? 'Иван' : 'Алиса',
            'status' => 'processing',
            'total' => 12490,
            'address' => 'Учебный адрес',
        ];
    }

    public function reviews(string $studentId): array
    {
        return array_values(array_filter(
            $this->savedReviews,
            static fn (array $review): bool => $review['student_id'] === $studentId,
        ));
    }

    public function saveReview(string $studentId, string $author, string $content): array
    {
        $review = [
            'id' => count($this->savedReviews) + 1,
            'student_id' => $studentId,
            'author' => $author,
            'content' => $content,
        ];
        $this->savedReviews[] = $review;

        return $review;
    }

    public function couponApplications(string $cartId, string $code): int
    {
        return $this->coupons[$cartId . ':' . $code] ?? 0;
    }

    public function saveCouponApplication(string $cartId, string $code): void
    {
        $key = $cartId . ':' . $code;
        $this->coupons[$key] = ($this->coupons[$key] ?? 0) + 1;
    }

    public function logEvent(string $type, string $summary, array $metadata = []): void
    {
    }
}
