<?php

declare(strict_types=1);

namespace App\Domain\Lab;

final class InvestigationProgress
{
    /**
     * @param list<array<string, mixed>> $history
     * @return list<array<string, mixed>>
     */
    public function forChallenge(string $slug, array $history, bool $completed = false): array
    {
        $catalog = new InvestigationStages();
        $stages = [];
        $facts = [];
        $cursor = 0;
        $unlocked = true;
        foreach ($catalog->forChallenge($slug) as $index => $definition) {
            $stage = $catalog->forChallenge($slug, $facts)[$index];
            $match = null;
            if ($unlocked) {
                for ($offset = $cursor; $offset < count($history); $offset++) {
                    if ($this->matches($slug, $definition['id'], $history[$offset], $facts)) {
                        $match = $history[$offset];
                        $cursor = $offset + 1;
                        break;
                    }
                }
            }
            $done = $completed || $match !== null;
            $stage['status'] = $done ? 'completed' : ($unlocked ? 'current' : 'locked');
            if (!$done && !$unlocked) {
                $stage = ['id' => $stage['id'], 'title' => $stage['title'], 'status' => 'locked'];
            }
            if ($match !== null) {
                $response = is_array($match['response'] ?? null) ? $match['response'] : [];
                $facts = array_replace($facts, is_array($response['body'] ?? null) ? $response['body'] : []);
                $stage['evidence'] = [
                    'request_id' => is_int($match['id'] ?? null) ? $match['id'] : null,
                    'method' => $match['method'] ?? '', 'path' => $match['path'] ?? '',
                    'status' => $response['status'] ?? $match['response_status'] ?? null,
                ];
            }
            $stages[] = $stage;
            $unlocked = $unlocked && $done;
        }
        return $stages;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $facts
     */
    private function matches(string $slug, string $criterion, array $item, array $facts): bool
    {
        $response = is_array($item['response'] ?? null) ? $item['response'] : [];
        $body = is_array($item['body'] ?? null) ? $item['body'] : [];
        $result = is_array($response['body'] ?? null) ? $response['body'] : [];
        $path = is_string($item['path'] ?? null) ? (parse_url($item['path'], PHP_URL_PATH) ?: '') : '';
        $method = is_string($item['method'] ?? null) ? strtoupper($item['method']) : '';
        $status = $response['status'] ?? $item['response_status'] ?? null;
        $single = ($item['mode'] ?? 'single') === 'single';
        $route = static fn (string $verb, string $url, int $code): bool => $method === $verb && $path === $url && $status === $code;
        $email = is_string($body['email'] ?? null) ? strtolower($body['email']) : '';
        return match ($criterion) {
            'proof' => in_array($slug, is_array($response['earned_challenges'] ?? null) ? $response['earned_challenges'] : [], true),
            'ticket' => $route('GET', '/api/support/tickets/42', 200),
            'robots' => $route('GET', '/robots.txt', 200),
            'profile-admin' => $route('PATCH', '/api/store/profile', 200) && ($result['role'] ?? '') === 'admin',
            'comment' => $route('POST', '/api/comments', 201),
            'unknown-login' => $route('POST', '/api/auth/login', 401) && $email === 'missing@example.test' && ($result['code'] ?? '') === 'account_missing',
            'own-token' => $route('POST', '/api/reset/request', 200) && $email === 'student@example.test',
            'password' => $route('POST', '/api/auth/password', 200) && ($result['transaction'] ?? '') === 'tx-student',
            'coupon-first' => $route('POST', '/api/cart/coupon', 200) && $single && ($result['discounts_applied'] ?? 0) === 1,
            'coupon-repeat' => $route('POST', '/api/cart/coupon', 409) && $single && ($result['cart_id'] ?? 'demo') === ($facts['cart_id'] ?? 'demo'),
            'cart' => $route('POST', '/api/cart/new', 201),
            'preview' => $route('POST', '/api/tools/preview', 200) && ($body['url'] ?? '') === 'https://docs.example.test/help',
            'index' => $route('POST', '/api/tools/preview', 200) && ($body['url'] ?? '') === 'http://metadata.internal/latest/',
            'token' => $route('GET', '/api/auth/lab-token', 200),
            default => false,
        };
    }
}
