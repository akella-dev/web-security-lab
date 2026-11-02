<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Lab;

use App\Domain\Lab\ChallengeCatalog;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ChallengeCatalogTest extends TestCase
{
    public function test_it_exposes_the_course_in_teaching_order(): void
    {
        $challenges = (new ChallengeCatalog())->all();

        self::assertCount(16, $challenges);
        self::assertSame(range(1, 16), array_map(static fn ($challenge): int => $challenge->number, $challenges));
        self::assertCount(16, array_unique(array_map(static fn ($challenge): string => $challenge->slug, $challenges)));
        self::assertSame(
            ['HTTP и доверие', 'Контроль доступа', 'Инъекции', 'Аутентификация', 'Бизнес-логика'],
            array_values(array_unique(array_map(static fn ($challenge): string => $challenge->module, $challenges))),
        );
        self::assertSame(
            ['Лёгкое', 'Среднее', 'Сложное', 'Экспертное'],
            array_values(array_unique(array_map(static fn ($challenge): string => $challenge->difficulty, $challenges))),
        );
        foreach ($challenges as $challenge) {
            self::assertNotSame('', $challenge->mission);
            self::assertNotEmpty($challenge->hints);
            self::assertArrayHasKey('method', $challenge->starterRequest);
            self::assertArrayHasKey('path', $challenge->starterRequest);
            self::assertSame('{}', json_encode($challenge->toArray()['starter_request']['headers']));
            if ($challenge->starterRequest['body'] === []) {
                self::assertSame('{}', json_encode($challenge->toArray()['starter_request']['body']));
            }
        }
    }

    public function test_it_rejects_an_unknown_challenge(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Неизвестное задание');

        (new ChallengeCatalog())->find('unknown');
    }

    public function test_multi_step_challenges_point_to_stage_materials_instead_of_response_navigation(): void
    {
        $catalog = new ChallengeCatalog();

        foreach (['idor-chain', 'role-escalation', 'reset-token', 'mfa-bypass', 'ssrf-preview', 'jwt-tampering'] as $slug) {
            $hints = implode(' ', $catalog->find($slug)->hints);
            self::assertStringContainsString('этап', $hints, $slug);
            foreach (['_links', 'workflow', 'client_context', 'service_index'] as $obsolete) {
                self::assertStringNotContainsString($obsolete, $hints, $slug);
            }
        }
    }
}
