<?php

declare(strict_types=1);

namespace App\Application\Shop;

use App\Application\Lab\LabService;
use App\Domain\Shop\VulnerableShop;
use RuntimeException;

final readonly class ShopService
{
    public function __construct(
        private ShopRepository $repository,
        private VulnerableShop $rules = new VulnerableShop(),
        private ?LabService $lab = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function order(int $orderId, int $actorId = 1, string $studentId = ''): array
    {
        $order = $this->repository->findOrder($orderId);
        if ($order === null) {
            throw new RuntimeException('Заказ не найден');
        }

        $ownerId = (int) $order['owner_id'];
        if (!$this->rules->mayReadOrder($actorId, $ownerId)) {
            throw new RuntimeException('Доступ запрещён');
        }

        $this->repository->logEvent('order.viewed', "Открыт заказ {$orderId}", [
            'student_id' => $studentId,
            'actor_id' => $actorId,
            'owner_id' => $ownerId,
            'foreign' => $actorId !== $ownerId,
        ]);

        return $order;
    }

    /** @return list<array<string, mixed>> */
    public function reviews(string $studentId): array
    {
        return $this->repository->reviews($studentId);
    }

    /** @return array<string, mixed> */
    public function addReview(string $author, string $content, string $studentId = ''): array
    {
        $unsafeContent = $this->rules->reviewForUnsafeRendering($content);
        $review = $this->repository->saveReview($studentId, $author, $unsafeContent);
        $this->repository->logEvent('review.created', 'Добавлен отзыв без экранирования', [
            'student_id' => $studentId,
            'contains_html' => $content !== strip_tags($content),
        ]);

        if ($studentId !== '' && preg_match('/<(script|img|svg)\b|onerror\s*=|onload\s*=/i', $content) === 1) {
            $this->lab?->complete($studentId, 'stored-xss', ['content' => $content]);
        }

        return $review;
    }

    /** @return array{code: string, applications: int, total: int, discount: int} */
    public function applyCoupon(string $cartId, string $code, string $studentId = ''): array
    {
        if (strtoupper($code) !== 'HONEY20') {
            throw new RuntimeException('Купон не найден');
        }

        $this->repository->saveCouponApplication($cartId, 'HONEY20');
        $applications = $this->repository->couponApplications($cartId, 'HONEY20');
        $baseTotal = 12490;
        $total = $this->rules->discountedTotal($baseTotal, $applications);
        $this->repository->logEvent('coupon.applied', 'Купон применён к корзине', [
            'student_id' => $studentId,
            'cart_id' => $cartId,
            'applications' => $applications,
        ]);

        return [
            'code' => 'HONEY20',
            'applications' => $applications,
            'total' => $total,
            'discount' => $baseTotal - $total,
        ];
    }

    /** @return array{email: string, message: string, delivery_preview?: string} */
    public function requestPasswordReset(string $email): array
    {
        $token = $this->rules->resetToken($email);
        $this->repository->logEvent('password_reset.requested', 'Запрошен сброс пароля', [
            'email' => $email,
            'token_disclosed' => $email === 'student@example.test',
        ]);

        $result = [
            'email' => $email,
            'message' => 'Если адрес существует, учебное письмо сформировано.',
        ];
        if ($email === 'student@example.test') {
            $result['delivery_preview'] = $token;
        }

        return $result;
    }

    /** @return array{verified: bool, message: string} */
    public function verifyPasswordReset(string $email, string $token, string $studentId = ''): array
    {
        $verified = hash_equals($this->rules->resetToken($email), $token);
        $this->repository->logEvent('password_reset.verified', 'Проверен токен восстановления', [
            'student_id' => $studentId,
            'email' => $email,
            'verified' => $verified,
        ]);

        return [
            'verified' => $verified,
            'message' => $verified
                ? 'Токен принят. Уязвимость подтверждена; пароль не изменялся.'
                : 'Токен не подходит.',
        ];
    }
}
