<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Lab;

use App\Domain\Lab\ExerciseEngine;
use App\Domain\Lab\ExerciseRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExerciseEngineTest extends TestCase
{
    /** @return iterable<string, array{ExerciseRequest, list<array<string, mixed>>, string}> */
    public static function completionCases(): iterable
    {
        yield 'price tampering' => [self::request('POST', '/api/store/orders', ['product_id' => 'honey-set', 'quantity' => 1, 'unit_price' => 1]), [], 'price-tampering'];
        yield 'mass assignment' => [self::request('PATCH', '/api/store/profile', ['display_name' => 'Студент', 'role' => 'instructor']), [], 'mass-assignment'];
        yield 'debug disclosure' => [self::request('GET', '/api/store/shipments?format[]=json'), [], 'debug-disclosure'];
        yield 'idor chain' => [self::request('GET', '/api/store/invoices/ORD-b7f3'), [self::history('GET', '/api/support/tickets/42')], 'idor-chain'];
        yield 'forced browsing' => [self::request('GET', '/internal/export'), [self::history('GET', '/robots.txt')], 'forced-browsing'];
        yield 'role escalation' => [self::request('GET', '/api/admin/audit', headers: ['X-Lab-Role' => 'admin']), [self::history('PATCH', '/api/store/profile', ['role' => 'admin'])], 'role-escalation'];
        yield 'reflected xss' => [self::request('GET', '/api/search?q=%3Csvg%20onload%3Dalert(1)%3E'), [], 'reflected-xss'];
        yield 'stored xss' => [self::request('GET', '/api/comments'), [self::history('POST', '/api/comments', ['content' => '<img src=x onerror=alert(1)>'])], 'stored-xss'];
        yield 'sql injection' => [self::request('GET', '/api/catalog?filter=%27%20OR%201%3D1--'), [], 'sql-injection'];
        yield 'user enumeration' => [self::request('POST', '/api/auth/login', ['email' => 'alice@example.test', 'password' => 'wrong']), [self::history('POST', '/api/auth/login', ['email' => 'missing@example.test'])], 'user-enumeration'];
        yield 'reset chain' => [self::request('POST', '/api/reset/verify', ['email' => 'alice@example.test', 'token' => 'reset-alice-2609']), [self::history('POST', '/api/reset/request', ['email' => 'student@example.test'])], 'reset-token'];
        yield 'mfa bypass' => [self::request('POST', '/api/auth/session', ['transaction' => 'tx-student']), [self::history('POST', '/api/auth/password', ['email' => 'student@example.test', 'password' => 'honey123'])], 'mfa-bypass'];
        yield 'negative quantity' => [self::request('POST', '/api/cart/items', ['sku' => 'honey-500', 'quantity' => -3]), [], 'negative-quantity'];
        yield 'coupon race' => [self::request('POST', '/api/cart/coupon', ['code' => 'HONEY20', 'cart_id' => 'new-cart'], mode: 'parallel'), [self::history('POST', '/api/cart/coupon', ['code' => 'HONEY20']) + ['response_status' => 409]], 'coupon-race'];
        yield 'virtual ssrf' => [self::request('POST', '/api/tools/preview', ['url' => 'http://metadata.internal/latest/secret']), [self::history('POST', '/api/tools/preview', ['url' => 'http://metadata.internal/latest/'])], 'ssrf-preview'];
        yield 'jwt tampering' => [self::request('GET', '/api/admin/reports', headers: ['Authorization' => 'Bearer eyJhbGciOiJub25lIn0.eyJzdWIiOiJzdHVkZW50Iiwicm9sZSI6ImFkbWluIn0.']), [self::history('GET', '/api/auth/lab-token')], 'jwt-tampering'];
    }

    #[DataProvider('completionCases')]
    public function test_it_awards_exercises_from_observed_http_outcomes(ExerciseRequest $request, array $history, string $slug): void
    {
        $result = (new ExerciseEngine())->handle($request, $history);

        self::assertContains($slug, $result->earnedChallenges);
        self::assertGreaterThanOrEqual(200, $result->status);
        self::assertLessThanOrEqual(500, $result->status);
    }

    public function test_sequence_tasks_are_not_awarded_without_reconnaissance(): void
    {
        $engine = new ExerciseEngine();

        self::assertNotContains('idor-chain', $engine->handle(self::request('GET', '/api/store/invoices/ORD-b7f3'), [])->earnedChallenges);
        self::assertNotContains('forced-browsing', $engine->handle(self::request('GET', '/internal/export'), [])->earnedChallenges);
        self::assertNotContains('jwt-tampering', $engine->handle(self::request('GET', '/api/admin/reports', headers: ['Authorization' => 'Bearer eyJhbGciOiJub25lIn0.eyJyb2xlIjoiYWRtaW4ifQ.']), [])->earnedChallenges);
    }

    public function test_stored_xss_requires_a_subsequent_visit_and_is_isolated_by_history(): void
    {
        $engine = new ExerciseEngine();
        $payload = ['content' => '<svg onload=alert(1)>'];
        self::assertSame([], $engine->handle(self::request('POST', '/api/comments', $payload), [])->earnedChallenges);
        $read = self::request('GET', '/api/comments');
        self::assertNotContains('stored-xss', $engine->handle($read, [])->earnedChallenges);
        $result = $engine->handle($read, [self::history('POST', '/api/comments', $payload)]);
        self::assertContains('stored-xss', $result->earnedChallenges);
        self::assertStringContainsString('<svg onload=alert(1)>', (string) $result->body);
    }

    public function test_coupon_experiment_requires_sequential_control_and_a_fresh_cart(): void
    {
        $engine = new ExerciseEngine();
        $body = ['code' => 'HONEY20'];
        $single = self::request('POST', '/api/cart/coupon', $body);
        self::assertSame(200, $engine->handle($single, [])->status);
        $history = [self::history('POST', '/api/cart/coupon', $body) + ['response_status' => 200]];
        self::assertSame(409, $engine->handle($single, $history)->status);
        $history[] = self::history('POST', '/api/cart/coupon', $body) + ['response_status' => 409];
        self::assertSame(409, $engine->handle(self::request('POST', '/api/cart/coupon', $body, mode: 'parallel'), $history)->status);
        $parallel = self::request('POST', '/api/cart/coupon', ['code' => 'HONEY20', 'cart_id' => 'cart-1'], mode: 'parallel');
        self::assertSame([], $engine->handle($parallel, [])->earnedChallenges);
        $result = $engine->handle($parallel, $history);
        self::assertContains('coupon-race', $result->earnedChallenges);
        self::assertSame(2, $result->body['discounts_applied']);
        self::assertCount(4, $result->body['timeline']);
        self::assertSame(422, $engine->handle(self::request('POST', '/api/cart/coupon', ['code' => 'WRONG']), [])->status);
    }

    public function test_failed_password_does_not_allow_mfa_session_creation(): void
    {
        $history = [self::history('POST', '/api/auth/password', ['email' => 'student@example.test', 'password' => 'wrong'])];
        $result = (new ExerciseEngine())->handle(self::request('POST', '/api/auth/session', ['transaction' => 'tx-student']), $history);
        self::assertSame(401, $result->status);
        self::assertSame([], $result->earnedChallenges);
    }

    public function test_router_never_performs_real_network_filesystem_or_sql_operations(): void
    {
        $engine = new ExerciseEngine();

        self::assertSame(400, $engine->handle(self::request('POST', '/api/tools/preview', ['url' => 'https://example.com']), [])->status);
        self::assertSame(404, $engine->handle(self::request('GET', '/../../etc/passwd'), [])->status);
        self::assertSame(400, $engine->handle(self::request('GET', '/api/catalog?filter=%27%3B%20DROP%20TABLE%20users--'), [])->status);
    }

    public function test_responses_contain_business_data_without_teaching_navigation(): void
    {
        $engine = new ExerciseEngine();
        $requests = [
            self::request('GET', '/api/support/tickets/42'),
            self::request('PATCH', '/api/store/profile', ['role' => 'admin']),
            self::request('POST', '/api/reset/request', ['email' => 'student@example.test']),
            self::request('POST', '/api/auth/password', ['email' => 'student@example.test', 'password' => 'honey123']),
            self::request('GET', '/api/auth/lab-token'),
            self::request('POST', '/api/tools/preview', ['url' => 'https://docs.example.test/help']),
            self::request('POST', '/api/tools/preview', ['url' => 'http://metadata.internal/latest/']),
        ];
        foreach ($requests as $request) {
            $result = $engine->handle($request, []);
            foreach (['_links', 'workflow', 'client_context', 'service_index', 'hint', 'next'] as $field) {
                self::assertArrayNotHasKey($field, $result->body, $request->path);
            }
        }
        self::assertSame('ORD-b7f3', $engine->handle($requests[0], [])->body['order_ref']);
        self::assertSame('admin', $engine->handle($requests[1], [])->body['role']);
        self::assertSame('reset-student-2609', $engine->handle($requests[2], [])->body['delivery_preview']);
        self::assertSame('tx-student', $engine->handle($requests[3], [])->body['transaction']);
    }

    /** @param array<string, mixed>|string $body @param array<string, string> $headers */
    private static function request(string $method, string $path, array|string $body = [], array $headers = [], string $mode = 'single'): ExerciseRequest
    {
        return new ExerciseRequest($method, $path, $headers, $body, $mode);
    }

    /** @param array<string, mixed>|string $body @return array<string, mixed> */
    private static function history(string $method, string $path, array|string $body = []): array
    {
        return ['method' => $method, 'path' => $path, 'headers' => [], 'body' => $body, 'mode' => 'single'];
    }
}
