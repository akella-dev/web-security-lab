<?php

declare(strict_types=1);

use App\Application\Lab\LabService;
use App\Application\Shop\ShopService;
use App\Application\Student\StudentService;
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return static function (
    App $app,
    LabService $lab,
    ShopService $shop,
    StudentService $students,
    string $teacherToken,
): void {
    /** @return array<string, mixed> */
    $body = static function (Request $request): array {
        $decoded = json_decode((string) $request->getBody(), true);
        return is_array($decoded) ? $decoded : [];
    };

    /** @return array{id:string,name:string,group:string} */
    $requireStudent = static function (Request $request) use ($students): array {
        $cookies = $request->getCookieParams();
        return $students->authenticate((string) ($cookies['hp_session'] ?? ''));
    };

    $requireTeacher = static function (Request $request) use ($teacherToken): void {
        if (!hash_equals($teacherToken, $request->getHeaderLine('X-Teacher-Key'))) {
            throw new RuntimeException('Неверный ключ преподавателя', 403);
        }
    };

    $app->get('/healthz', static fn (Request $request, Response $response): Response =>
        JsonResponder::write($response, ['status' => 'ok', 'service' => 'web-security-lab-api']));

    $app->post('/api/session', static function (Request $request, Response $response) use ($students, $body): Response {
        $input = $body($request);
        $enrollment = $students->enroll(
            (string) ($input['name'] ?? ''),
            (string) ($input['group'] ?? ''),
        );

        return JsonResponder::write($response, ['data' => $enrollment['student']], 201)
            ->withAddedHeader(
                'Set-Cookie',
                'hp_session=' . $enrollment['session_token'] . '; Path=/; HttpOnly; SameSite=Lax; Max-Age=28800',
            );
    });

    $app->get('/api/session', static function (Request $request, Response $response) use ($requireStudent): Response {
        return JsonResponder::write($response, ['data' => $requireStudent($request)]);
    });

    $app->delete('/api/session', static fn (Request $request, Response $response): Response =>
        JsonResponder::write($response, ['data' => ['signed_out' => true]])
            ->withAddedHeader('Set-Cookie', 'hp_session=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0'));

    $app->get('/api/challenges', static function (Request $request, Response $response) use ($lab, $requireStudent): Response {
        $student = $requireStudent($request);
        return JsonResponder::write($response, ['data' => $lab->challenges($student['id'])]);
    });

    $app->get('/api/workbench/history', static function (Request $request, Response $response) use ($lab, $requireStudent): Response {
        $student = $requireStudent($request);
        return JsonResponder::write($response, ['data' => $lab->requestHistory($student['id'])]);
    });

    $app->post('/api/workbench/send', static function (Request $request, Response $response) use ($lab, $body, $requireStudent): Response {
        $student = $requireStudent($request);
        $input = $body($request);
        if (strlen((string) $request->getBody()) > 20000) {
            return JsonResponder::write($response, [
                'error' => ['code' => 'request_too_large', 'message' => 'Запрос учебной лаборатории слишком большой.'],
            ], 413);
        }
        return JsonResponder::write($response, ['data' => $lab->sendRequest($student['id'], $input)]);
    });

    $app->get('/api/orders/{id}', static function (Request $request, Response $response, array $args) use ($shop, $requireStudent): Response {
        $student = $requireStudent($request);
        return JsonResponder::write($response, [
            'data' => $shop->order((int) $args['id'], 1, $student['id']),
        ]);
    });

    $app->get('/api/reviews', static function (Request $request, Response $response) use ($shop, $requireStudent): Response {
        $student = $requireStudent($request);
        return JsonResponder::write($response, ['data' => $shop->reviews($student['id'])]);
    });

    $app->post('/api/reviews', static function (Request $request, Response $response) use ($shop, $body, $requireStudent): Response {
        $student = $requireStudent($request);
        $input = $body($request);
        $content = trim((string) ($input['content'] ?? ''));
        if ($content === '') {
            return JsonResponder::write($response, [
                'error' => ['code' => 'review_empty', 'message' => 'Введите текст отзыва.'],
            ], 422);
        }

        return JsonResponder::write($response, [
            'data' => $shop->addReview($student['name'], $content, $student['id']),
        ], 201);
    });

    $app->post('/api/coupons/apply', static function (Request $request, Response $response) use ($shop, $body, $requireStudent): Response {
        $student = $requireStudent($request);
        $input = $body($request);

        return JsonResponder::write($response, ['data' => $shop->applyCoupon(
            $student['id'],
            (string) ($input['code'] ?? ''),
            $student['id'],
        )]);
    });

    $app->post('/api/password-reset/request', static function (Request $request, Response $response) use ($shop, $body, $requireStudent): Response {
        $requireStudent($request);
        $input = $body($request);
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return JsonResponder::write($response, [
                'error' => ['code' => 'email_invalid', 'message' => 'Введите корректный email.'],
            ], 422);
        }

        return JsonResponder::write($response, ['data' => $shop->requestPasswordReset($email)]);
    });

    $app->post('/api/password-reset/verify', static function (Request $request, Response $response) use ($shop, $body, $requireStudent): Response {
        $student = $requireStudent($request);
        $input = $body($request);

        return JsonResponder::write($response, ['data' => $shop->verifyPasswordReset(
            strtolower(trim((string) ($input['email'] ?? ''))),
            trim((string) ($input['token'] ?? '')),
            $student['id'],
        )]);
    });

    $app->get('/api/teacher/overview', static function (Request $request, Response $response) use ($lab, $requireTeacher): Response {
        $requireTeacher($request);
        return JsonResponder::write($response, ['data' => $lab->overview()]);
    });

    $app->post('/api/teacher/reset', static function (Request $request, Response $response) use ($lab, $requireTeacher): Response {
        $requireTeacher($request);
        $lab->reset();
        return JsonResponder::write($response, ['data' => ['reset' => true]]);
    });
};
