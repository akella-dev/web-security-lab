<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Lab\LabRepository;
use App\Application\Shop\ShopRepository;
use App\Application\Student\StudentRepository;
use PDO;
use RuntimeException;

final readonly class PostgresRepository implements LabRepository, ShopRepository, StudentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function saveSubmission(string $studentId, string $challenge, array $proof): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO submissions (student_id, challenge, proof) VALUES (:student, :challenge, CAST(:proof AS jsonb)) '
            . 'ON CONFLICT (student_id, challenge) DO NOTHING RETURNING id',
        );
        $statement->execute([
            'student' => $studentId,
            'challenge' => $challenge,
            'proof' => json_encode($proof, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
        if ($statement->fetchColumn() !== false) {
            $this->logEvent('mission.completed', "Миссия {$challenge} выполнена", ['student_id' => $studentId]);
        }
    }

    public function completedChallenges(): array
    {
        $statement = $this->pdo->prepare('SELECT DISTINCT challenge FROM submissions ORDER BY challenge');
        $statement->execute();

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function completedChallengesForStudent(string $studentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT challenge FROM submissions WHERE student_id = :student ORDER BY challenge',
        );
        $statement->execute(['student' => $studentId]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function recentEvents(int $limit = 20): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, type, summary, metadata, created_at FROM lab_events ORDER BY id DESC LIMIT :limit',
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_values(array_map(static function (array $row): array {
            $row['metadata'] = json_decode((string) $row['metadata'], true, flags: JSON_THROW_ON_ERROR);
            return $row;
        }, $statement->fetchAll(PDO::FETCH_ASSOC)));
    }

    public function studentOverview(): array
    {
        $statement = $this->pdo->query(
            "SELECT s.id, s.name, s.group_name AS \"group\", s.last_seen_at, "
            . "COALESCE(jsonb_agg(sub.challenge ORDER BY sub.submitted_at) "
            . "FILTER (WHERE sub.challenge IS NOT NULL), '[]'::jsonb) AS completed_challenges "
            . 'FROM students s LEFT JOIN submissions sub ON sub.student_id = s.id '
            . 'GROUP BY s.id, s.name, s.group_name, s.last_seen_at '
            . 'ORDER BY s.last_seen_at DESC',
        );
        if ($statement === false) {
            throw new RuntimeException('Не удалось получить сводку по студентам.');
        }

        return array_values(array_map(static function (array $row): array {
            $row['completed_challenges'] = json_decode(
                (string) $row['completed_challenges'],
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            return $row;
        }, $statement->fetchAll(PDO::FETCH_ASSOC)));
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $response
     */
    public function saveRequest(string $studentId, array $request, array $response): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO lab_requests (student_id, method, path, headers, body, mode, response_status, response_body) '
            . 'VALUES (:student, :method, :path, CAST(:headers AS jsonb), CAST(:body AS jsonb), :mode, :status, CAST(:response AS jsonb))',
        );
        $statement->execute([
            'student' => $studentId,
            'method' => (string) ($request['method'] ?? 'GET'),
            'path' => (string) ($request['path'] ?? '/'),
            'headers' => json_encode($request['headers'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'body' => json_encode($request['body'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'mode' => (string) ($request['mode'] ?? 'single'),
            'status' => (int) ($response['status'] ?? 0),
            'response' => json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
        $this->logEvent('request.sent', 'Отправлен запрос из HTTP-лаборатории', [
            'student_id' => $studentId,
            'method' => $request['method'] ?? 'GET',
            'path' => $request['path'] ?? '/',
            'status' => $response['status'] ?? 0,
        ]);
    }

    public function requestHistory(string $studentId, int $limit = 50): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, method, path, headers, body, mode, response_status, response_body AS response, created_at '
            . 'FROM lab_requests WHERE student_id = :student ORDER BY id DESC'
            . ($limit > 0 ? ' LIMIT :limit' : ''),
        );
        $statement->bindValue('student', $studentId);
        if ($limit > 0) {
            $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        }
        $statement->execute();
        $rows = array_reverse($statement->fetchAll(PDO::FETCH_ASSOC));

        return array_values(array_map(static function (array $row): array {
            foreach (['headers', 'body', 'response'] as $field) {
                $row[$field] = json_decode((string) $row[$field], true, flags: JSON_THROW_ON_ERROR);
            }
            $row['response_status'] = (int) $row['response_status'];
            return $row;
        }, $rows));
    }

    public function reset(): void
    {
        $this->pdo->exec(
            'TRUNCATE submissions, lab_requests, lab_events, reviews, coupon_applications, students RESTART IDENTITY',
        );
        $this->pdo->exec(
            "INSERT INTO reviews (student_id, author, content) "
            . "VALUES (NULL, 'Марина', 'Мёд отличный, доставка вовремя!')",
        );
        $this->logEvent('lab.reset', 'Состояние полигона сброшено');
    }

    public function findOrder(int $orderId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.owner_id, u.name AS owner, o.status, o.total, o.address, o.created_at '
            . 'FROM orders o JOIN users u ON u.id = o.owner_id WHERE o.id = :id',
        );
        $statement->execute(['id' => $orderId]);
        $order = $statement->fetch(PDO::FETCH_ASSOC);

        return $order === false ? null : $order;
    }

    public function reviews(string $studentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, author, content, created_at FROM reviews '
            . 'WHERE student_id IS NULL OR student_id = :student ORDER BY id DESC',
        );
        $statement->execute(['student' => $studentId]);

        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function saveReview(string $studentId, string $author, string $content): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO reviews (student_id, author, content) VALUES (:student, :author, :content) '
            . 'RETURNING id, author, content, created_at',
        );
        $statement->execute(['student' => $studentId, 'author' => $author, 'content' => $content]);

        $review = $statement->fetch(PDO::FETCH_ASSOC);
        if ($review === false) {
            throw new RuntimeException('Не удалось сохранить отзыв');
        }

        return $review;
    }

    public function couponApplications(string $cartId, string $code): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM coupon_applications WHERE cart_id = :cart AND code = :code',
        );
        $statement->execute(['cart' => $cartId, 'code' => $code]);

        return (int) $statement->fetchColumn();
    }

    public function saveCouponApplication(string $cartId, string $code): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO coupon_applications (cart_id, code) VALUES (:cart, :code)',
        );
        $statement->execute(['cart' => $cartId, 'code' => $code]);
    }

    /** @param array<string, mixed> $metadata */
    public function logEvent(string $type, string $summary, array $metadata = []): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO lab_events (type, summary, metadata) VALUES (:type, :summary, CAST(:metadata AS jsonb))',
        );
        $statement->execute([
            'type' => $type,
            'summary' => $summary,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function createStudent(string $id, string $name, string $group, string $sessionToken): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO students (id, name, group_name, session_token_hash) '
            . 'VALUES (:id, :name, :group_name, :token_hash) '
            . 'RETURNING id, name, group_name AS "group"',
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'group_name' => $group,
            'token_hash' => hash('sha256', $sessionToken),
        ]);
        $student = $statement->fetch(PDO::FETCH_ASSOC);
        if ($student === false) {
            throw new RuntimeException('Не удалось зарегистрировать студента.');
        }
        $this->logEvent('student.joined', 'Студент подключился к стенду', [
            'student_id' => $id,
            'group' => $group,
        ]);

        /** @var array{id:string,name:string,group:string} $student */
        return $student;
    }

    public function findStudentBySession(string $sessionToken): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, group_name AS "group" FROM students WHERE session_token_hash = :token_hash',
        );
        $statement->execute(['token_hash' => hash('sha256', $sessionToken)]);
        $student = $statement->fetch(PDO::FETCH_ASSOC);

        if ($student === false) {
            return null;
        }

        /** @var array{id:string,name:string,group:string} $student */
        return $student;
    }

    public function touchStudent(string $studentId): void
    {
        $statement = $this->pdo->prepare('UPDATE students SET last_seen_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $studentId]);
    }
}
