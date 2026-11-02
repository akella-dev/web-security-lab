<?php

declare(strict_types=1);

namespace App\Domain\Lab;

final class ExerciseEngine
{
    /** @param list<array<string, mixed>> $history */
    public function handle(ExerciseRequest $request, array $history): ExerciseResult
    {
        $method = strtoupper($request->method);
        $path = $request->path;
        $route = (string) (parse_url($path, PHP_URL_PATH) ?? '');
        $query = rawurldecode((string) (parse_url($path, PHP_URL_QUERY) ?? ''));
        $body = is_array($request->body) ? $request->body : [];
        $json = ['Content-Type' => 'application/json; charset=utf-8', 'X-Virtual-Lab' => 'true'];

        if ($route === '' || str_contains($route, '..') || !str_starts_with($route, '/')) {
            return new ExerciseResult(404, $json, ['error' => 'Виртуальный маршрут не найден.']);
        }

        if ($method === 'POST' && $route === '/api/store/orders') {
            $price = (int) ($body['unit_price'] ?? 4990);
            $earned = ($body['product_id'] ?? '') === 'honey-set' && (int) ($body['quantity'] ?? 0) > 0 && $price <= 1 ? ['price-tampering'] : [];
            return new ExerciseResult(201, $json, ['order_id' => 'LAB-2048', 'charged' => $price, 'status' => 'created'], $earned);
        }
        if ($method === 'PATCH' && $route === '/api/store/profile') {
            $role = (string) ($body['role'] ?? 'student');
            return new ExerciseResult(200, $json, [
                'display_name' => $body['display_name'] ?? 'Студент',
                'role' => $role,
            ], in_array($role, ['instructor', 'admin'], true) ? ['mass-assignment'] : []);
        }
        if ($method === 'GET' && $route === '/api/store/shipments') {
            if (str_contains($query, 'format[]=json')) {
                return new ExerciseResult(500, $json, ['error' => 'TypeError', 'service' => 'shipping-worker.internal', 'trace' => ['ShipmentController.php:74', 'Kernel.php:211']], ['debug-disclosure']);
            }
            return new ExerciseResult(200, $json, ['shipments' => [['id' => 'SHP-10', 'status' => 'packing']]]);
        }
        if ($method === 'GET' && $route === '/api/support/tickets/42') {
            return new ExerciseResult(200, $json, [
                'id' => 42,
                'subject' => 'Не пришёл счёт',
                'message' => 'Проверьте заказ',
                'order_ref' => 'ORD-b7f3',
            ]);
        }
        if ($method === 'GET' && $route === '/api/store/invoices/ORD-b7f3') {
            $earned = $this->historyHas($history, 'GET', '/api/support/tickets/42') ? ['idor-chain'] : [];
            return new ExerciseResult(200, $json, ['invoice' => 'INV-882', 'customer' => 'Алиса Воронова', 'total' => 27990], $earned);
        }
        if ($method === 'GET' && $route === '/robots.txt') {
            return new ExerciseResult(200, ['Content-Type' => 'text/plain', 'X-Virtual-Lab' => 'true'], "User-agent: *\nDisallow: /internal/export", preview: 'text');
        }
        if ($method === 'GET' && $route === '/internal/export') {
            $earned = $this->historyHas($history, 'GET', '/robots.txt') ? ['forced-browsing'] : [];
            return new ExerciseResult(200, $json, ['customers' => [['email' => 'alice@example.test'], ['email' => 'boris@example.test']]], $earned);
        }
        if ($method === 'GET' && $route === '/api/admin/audit') {
            $role = strtolower($this->header($request->headers, 'x-lab-role'));
            $escalated = $this->historyBodyHas($history, 'PATCH', '/api/store/profile', 'role', 'admin');
            if ($role !== 'admin') {
                return new ExerciseResult(403, $json, ['error' => 'Требуется роль admin.']);
            }
            return new ExerciseResult(200, $json, ['events' => [['actor' => 'system', 'action' => 'export.created']]], $escalated ? ['role-escalation'] : []);
        }
        if ($method === 'GET' && $route === '/api/search') {
            $value = $this->queryValue($query, 'q');
            $xss = $this->hasSafeXss($value);
            return new ExerciseResult(200, ['Content-Type' => 'text/html; charset=utf-8', 'X-Virtual-Lab' => 'true'], '<h2>Результаты для: ' . $value . '</h2>', $xss ? ['reflected-xss'] : [], 'html');
        }
        if ($method === 'POST' && $route === '/api/comments') {
            $content = (string) ($body['content'] ?? '');
            return new ExerciseResult(201, $json, ['message' => 'Отзыв опубликован. Откройте ленту, чтобы увидеть его глазами следующего посетителя.']);
        }
        if ($method === 'GET' && $route === '/api/comments') {
            $html = '';
            $xss = false;
            foreach ($history as $item) {
                $saved = $item['body'] ?? [];
                if (($item['method'] ?? '') !== 'POST' || ($item['path'] ?? '') !== '/api/comments' || !is_array($saved)) {
                    continue;
                }
                $content = (string) ($saved['content'] ?? '');
                $html .= '<article><strong>Покупатель</strong><p>' . $content . '</p></article>';
                $xss = $xss || $this->hasSafeXss($content);
            }
            return new ExerciseResult(200, ['Content-Type' => 'text/html; charset=utf-8', 'X-Virtual-Lab' => 'true'], $html ?: '<p>Отзывов пока нет.</p>', $xss ? ['stored-xss'] : [], 'html');
        }
        if ($method === 'GET' && $route === '/api/catalog') {
            if (preg_match('/;|\b(drop|delete|update|insert|alter)\b/i', $query) === 1) {
                return new ExerciseResult(400, $json, ['error' => 'Учебный фильтр допускает только безопасный SELECT-сценарий.']);
            }
            $injected = preg_match("/'\\s*or\\s+1\\s*=\\s*1/i", $query) === 1;
            $products = [['sku' => 'honey-500', 'name' => 'Липовый мёд']];
            if ($injected) {
                $products[] = ['sku' => 'hidden-queen', 'name' => 'Маточное молочко', 'hidden' => true];
            }
            return new ExerciseResult(200, $json, ['items' => $products, 'query' => "SELECT * FROM catalog WHERE name LIKE '%{$query}%'"], $injected ? ['sql-injection'] : []);
        }
        if ($method === 'POST' && $route === '/api/auth/login') {
            $email = strtolower((string) ($body['email'] ?? ''));
            $known = in_array($email, ['alice@example.test', 'student@example.test'], true);
            $compared = $email === 'alice@example.test' && $this->historyBodyHas($history, 'POST', '/api/auth/login', 'email', 'missing@example.test');
            return new ExerciseResult(401, $json, $known ? ['code' => 'password_invalid', 'message' => 'Неверный пароль.'] : ['code' => 'account_missing', 'message' => 'Пользователь не найден.'], $compared ? ['user-enumeration'] : []);
        }
        if ($method === 'POST' && $route === '/api/reset/request') {
            $email = strtolower((string) ($body['email'] ?? ''));
            $local = explode('@', $email)[0] ?: 'unknown';
            return new ExerciseResult(200, $json, [
                'message' => 'Учебное письмо доставлено.',
                'delivery_preview' => "reset-{$local}-2609",
            ]);
        }
        if ($method === 'POST' && $route === '/api/reset/verify') {
            $valid = strtolower((string) ($body['email'] ?? '')) === 'alice@example.test' && ($body['token'] ?? '') === 'reset-alice-2609';
            $ownRequested = $this->historyBodyHas($history, 'POST', '/api/reset/request', 'email', 'student@example.test');
            return new ExerciseResult($valid ? 200 : 422, $json, ['verified' => $valid, 'password_changed' => false], $valid && $ownRequested ? ['reset-token'] : []);
        }
        if ($method === 'POST' && $route === '/api/auth/password') {
            $valid = ($body['email'] ?? '') === 'student@example.test' && ($body['password'] ?? '') === 'honey123';
            return new ExerciseResult($valid ? 200 : 401, $json, $valid ? [
                'transaction' => 'tx-student',
            ] : ['error' => 'Неверные данные.']);
        }
        if ($method === 'POST' && $route === '/api/auth/otp') {
            return new ExerciseResult(422, $json, ['error' => 'Одноразовый код неверен.']);
        }
        if ($method === 'POST' && $route === '/api/auth/session') {
            $passwordAccepted = false;
            foreach ($history as $item) {
                $credentials = $item['body'] ?? [];
                if (($item['method'] ?? '') === 'POST' && ($item['path'] ?? '') === '/api/auth/password' && is_array($credentials) && ($credentials['email'] ?? '') === 'student@example.test' && ($credentials['password'] ?? '') === 'honey123') {
                    $passwordAccepted = true;
                }
            }
            $valid = ($body['transaction'] ?? '') === 'tx-student' && $passwordAccepted;
            return new ExerciseResult($valid ? 201 : 401, $json, $valid ? ['session' => 'lab-session-created', 'mfa_verified' => false] : ['error' => 'Транзакция не найдена.'], $valid ? ['mfa-bypass'] : []);
        }
        if ($method === 'POST' && $route === '/api/cart/items') {
            $quantity = (int) ($body['quantity'] ?? 0);
            $total = $quantity * 890;
            return new ExerciseResult(200, $json, ['quantity' => $quantity, 'cart_total' => $total], $quantity < 0 && $total < 0 ? ['negative-quantity'] : []);
        }
        if ($method === 'POST' && $route === '/api/cart/new') {
            $number = count(array_filter($history, static fn (array $item): bool => ($item['method'] ?? '') === 'POST' && ($item['path'] ?? '') === '/api/cart/new')) + 1;
            return new ExerciseResult(201, $json, ['cart_id' => 'cart-' . $number, 'message' => 'Новая корзина готова. Купон ещё не использован.']);
        }
        if ($method === 'POST' && $route === '/api/cart/coupon') {
            if (strtoupper((string) ($body['code'] ?? '')) !== 'HONEY20') {
                return new ExerciseResult(422, $json, ['error' => 'Купон не найден.']);
            }
            $cartId = (string) ($body['cart_id'] ?? 'demo');
            $used = false;
            $baseline = false;
            foreach ($history as $item) {
                $previous = $item['body'] ?? [];
                if (($item['method'] ?? '') !== 'POST' || ($item['path'] ?? '') !== '/api/cart/coupon' || !is_array($previous)) {
                    continue;
                }
                $response = is_array($item['response'] ?? null) ? $item['response'] : [];
                $status = $response['status'] ?? $item['response_status'] ?? null;
                if ($status === 409 && ($item['mode'] ?? 'single') === 'single') {
                    $baseline = true;
                }
                if ((string) ($previous['cart_id'] ?? 'demo') === $cartId && strtoupper((string) ($previous['code'] ?? '')) === 'HONEY20') {
                    $used = true;
                }
            }
            if ($used) {
                return new ExerciseResult(409, $json, ['error' => 'Купон уже использован в этой корзине.', 'cart_id' => $cartId, 'discounts_applied' => 0]);
            }
            $parallel = $request->mode === 'parallel';
            return new ExerciseResult(200, $json, [
                'cart_id' => $cartId,
                'accepted_requests' => $parallel ? 2 : 1,
                'discounts_applied' => $parallel ? 2 : 1,
                'timeline' => $parallel
                    ? ['A: прочитан лимит — купон свободен', 'B: прочитан лимит — купон свободен', 'A: скидка записана', 'B: вторая скидка записана']
                    : ['A: прочитан лимит — купон свободен', 'A: скидка записана, купон использован'],
                'comparison_recorded' => $baseline,
            ], $parallel && $baseline ? ['coupon-race'] : []);
        }
        if ($method === 'POST' && $route === '/api/tools/preview') {
            $url = (string) ($body['url'] ?? '');
            if ($url === 'http://metadata.internal/latest/secret') {
                $discovered = $this->historyBodyHas($history, 'POST', '/api/tools/preview', 'url', 'http://metadata.internal/latest/');
                return new ExerciseResult(200, $json, ['upstream' => 'metadata.internal', 'content' => 'LAB-METADATA-KEY=bee-7f91'], $discovered ? ['ssrf-preview'] : []);
            }
            if ($url === 'http://metadata.internal/latest/') {
                return new ExerciseResult(200, $json, [
                    'service' => 'metadata.internal',
                ]);
            }
            if ($url === 'https://docs.example.test/help') {
                return new ExerciseResult(200, $json, [
                    'title' => 'Справка магазина',
                    'content' => 'Учебная страница предпросмотра.',
                ]);
            }
            return new ExerciseResult(400, $json, ['error' => 'Внешняя сеть отключена; доступен только виртуальный allowlist стенда.']);
        }
        if ($method === 'GET' && $route === '/api/auth/lab-token') {
            return new ExerciseResult(200, $json, [
                'token' => 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJzdHVkZW50Iiwicm9sZSI6InN0dWRlbnQifQ.c2lnbmF0dXJl',
            ]);
        }
        if ($method === 'GET' && $route === '/api/admin/reports') {
            $token = preg_replace('/^Bearer\s+/i', '', $this->header($request->headers, 'authorization')) ?? '';
            $valid = $this->isUnsignedAdminToken($token);
            $recon = $this->historyHas($history, 'GET', '/api/auth/lab-token');
            return new ExerciseResult($valid ? 200 : 403, $json, $valid ? ['report' => 'Выручка: 1 240 000 ₽', 'role' => 'admin'] : ['error' => 'Требуется admin JWT.'], $valid && $recon ? ['jwt-tampering'] : []);
        }

        return new ExerciseResult(404, $json, ['error' => 'Виртуальный маршрут не найден.', 'path' => $route]);
    }

    /** @param list<array<string, mixed>> $history */
    private function historyHas(array $history, string $method, string $path): bool
    {
        foreach ($history as $item) {
            if (strtoupper((string) ($item['method'] ?? '')) === $method && (string) (parse_url((string) ($item['path'] ?? ''), PHP_URL_PATH) ?? '') === $path) {
                return true;
            }
        }
        return false;
    }

    /** @param list<array<string, mixed>> $history */
    private function historyBodyHas(array $history, string $method, string $path, string $key, string $value): bool
    {
        foreach ($history as $item) {
            $body = $item['body'] ?? [];
            if (strtoupper((string) ($item['method'] ?? '')) === $method && (string) (parse_url((string) ($item['path'] ?? ''), PHP_URL_PATH) ?? '') === $path && is_array($body) && strtolower((string) ($body[$key] ?? '')) === strtolower($value)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, string> $headers */
    private function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $name) {
                return $value;
            }
        }
        return '';
    }

    private function queryValue(string $query, string $key): string
    {
        parse_str($query, $values);
        return is_string($values[$key] ?? null) ? $values[$key] : '';
    }

    private function hasSafeXss(string $value): bool
    {
        return preg_match('/(<svg[^>]+onload\s*=|<img[^>]+onerror\s*=).*alert\s*\(\s*1\s*\)/i', $value) === 1;
    }

    private function isUnsignedAdminToken(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[2] !== '') {
            return false;
        }
        $header = $this->decodeBase64Url($parts[0]);
        $payload = $this->decodeBase64Url($parts[1]);
        return is_array($header) && strtolower((string) ($header['alg'] ?? '')) === 'none' && is_array($payload) && ($payload['role'] ?? '') === 'admin';
    }

    /** @return array<string, mixed>|null */
    private function decodeBase64Url(string $value): ?array
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }
        $json = json_decode($decoded, true);
        return is_array($json) ? $json : null;
    }
}
