<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;
use RuntimeException;

final class SchemaMigrator
{
    public static function migrate(PDO $pdo, string $directory): void
    {
        $files = glob(rtrim($directory, '/') . '/*.sql');
        if ($files === false || $files === []) {
            throw new RuntimeException('Файлы миграций не найдены.');
        }

        sort($files);
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Не удалось прочитать миграцию: ' . basename($file));
            }
            $pdo->exec($sql);
        }
    }
}
