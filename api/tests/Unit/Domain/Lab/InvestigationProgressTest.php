<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Lab;

use App\Domain\Lab\InvestigationProgress;
use App\Domain\Lab\ExerciseEngine;
use App\Domain\Lab\ExerciseRequest;
use PHPUnit\Framework\TestCase;

final class InvestigationProgressTest extends TestCase
{
    public function test_all_challenges_complete_with_real_responses_in_teaching_order(): void
    {
        foreach (ExerciseEngineTest::completionCases() as [$finalRequest, $prerequisites, $slug]) {
            $history = [];
            if ($slug === 'ssrf-preview') {
                $this->send($history, 'POST', '/api/tools/preview', ['url' => 'https://docs.example.test/help']);
            }
            if ($slug === 'coupon-race') {
                $this->send($history, 'POST', '/api/cart/coupon', ['code' => 'HONEY20']);
            }
            foreach ($prerequisites as $request) {
                $this->send($history, $request['method'], $request['path'], $request['body'] ?? []);
            }
            if ($slug === 'coupon-race') {
                $this->send($history, 'POST', '/api/cart/new');
            }
            $result = (new ExerciseEngine())->handle($finalRequest, $history);
            $history[] = $finalRequest->toArray() + ['response' => $result->toArray()];
            $stages = (new InvestigationProgress())->forChallenge($slug, $history);
            self::assertSame(array_fill(0, count($stages), 'completed'), array_column($stages, 'status'), $slug);
        }
    }

    public function test_next_stage_materials_are_hidden_until_a_verified_request(): void
    {
        $progress = new InvestigationProgress();
        $initial = $progress->forChallenge('idor-chain', []);
        self::assertSame(['current', 'locked'], array_column($initial, 'status'));
        self::assertArrayNotHasKey('resources', $initial[1]);
        self::assertArrayNotHasKey('request', $initial[1]);
        self::assertStringNotContainsString('/api/store/invoices', json_encode($initial, JSON_THROW_ON_ERROR));
        $history = [];
        $this->send($history, 'GET', '/api/support/tickets/42');
        $next = $progress->forChallenge('idor-chain', $history);
        self::assertSame(['completed', 'current'], array_column($next, 'status'));
        self::assertSame('/api/store/invoices/ORD-b7f3', $next[1]['request']['path']);
        self::assertSame(1, $next[0]['evidence']['request_id']);
        self::assertEquals($next, (new InvestigationProgress())->forChallenge('idor-chain', $history));
    }

    public function test_failed_and_out_of_order_requests_do_not_unlock_stages(): void
    {
        $progress = new InvestigationProgress();
        $history = [];
        $this->send($history, 'GET', '/api/store/invoices/ORD-b7f3');
        $this->send($history, 'GET', '/api/support/tickets/42');
        self::assertSame(['completed', 'current'], array_column($progress->forChallenge('idor-chain', $history), 'status'));
        $this->send($history, 'GET', '/api/store/invoices/ORD-b7f3');
        self::assertSame(['completed', 'completed'], array_column($progress->forChallenge('idor-chain', $history), 'status'));
        $failed = [];
        $this->send($failed, 'POST', '/api/auth/password', ['email' => 'student@example.test', 'password' => 'wrong']);
        self::assertSame(['current', 'locked'], array_column($progress->forChallenge('mfa-bypass', $failed), 'status'));
    }

    public function test_ssrf_discloses_each_address_only_after_its_previous_stage(): void
    {
        $progress = new InvestigationProgress();
        $history = [];
        self::assertStringNotContainsString('metadata.internal', json_encode($progress->forChallenge('ssrf-preview', []), JSON_THROW_ON_ERROR));
        $this->send($history, 'POST', '/api/tools/preview', ['url' => 'https://docs.example.test/help']);
        $stages = $progress->forChallenge('ssrf-preview', $history);
        self::assertSame(['completed', 'current', 'locked'], array_column($stages, 'status'));
        self::assertSame('http://metadata.internal/latest/', $stages[1]['request']['body']['url']);
        self::assertStringNotContainsString('/latest/secret', json_encode($stages, JSON_THROW_ON_ERROR));
        $this->send($history, 'POST', '/api/tools/preview', ['url' => 'http://metadata.internal/latest/']);
        self::assertSame('http://metadata.internal/latest/secret', $progress->forChallenge('ssrf-preview', $history)[2]['request']['body']['url']);
    }

    public function test_coupon_has_a_control_repeat_fresh_cart_and_final_experiment(): void
    {
        $progress = new InvestigationProgress();
        $history = [];
        $this->send($history, 'POST', '/api/cart/coupon', ['code' => 'WRONG']);
        self::assertSame('current', $progress->forChallenge('coupon-race', $history)[0]['status']);
        $this->send($history, 'POST', '/api/cart/coupon', ['code' => 'HONEY20']);
        $this->send($history, 'POST', '/api/cart/coupon', ['code' => 'HONEY20']);
        self::assertSame(['completed', 'completed', 'current', 'locked'], array_column($progress->forChallenge('coupon-race', $history), 'status'));
        $this->send($history, 'POST', '/api/cart/new');
        $stages = $progress->forChallenge('coupon-race', $history);
        self::assertSame('cart-1', $stages[3]['request']['body']['cart_id']);
        $this->send($history, 'POST', '/api/cart/coupon', ['code' => 'HONEY20', 'cart_id' => 'cart-1'], 'parallel');
        self::assertSame(['completed', 'completed', 'completed', 'completed'], array_column($progress->forChallenge('coupon-race', $history), 'status'));
    }

    public function test_existing_completions_remain_complete_without_replaying_old_steps(): void
    {
        $stages = (new InvestigationProgress())->forChallenge('coupon-race', [], true);
        self::assertSame(['completed', 'completed', 'completed', 'completed'], array_column($stages, 'status'));
    }

    /** @param list<array<string, mixed>> $history @param array<string, mixed> $body */
    private function send(array &$history, string $method, string $path, array $body = [], string $mode = 'single'): void
    {
        $request = new ExerciseRequest($method, $path, [], $body, $mode);
        $result = (new ExerciseEngine())->handle($request, $history);
        $history[] = $request->toArray() + ['id' => count($history) + 1, 'response' => $result->toArray()];
    }
}
