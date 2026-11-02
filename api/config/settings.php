<?php

declare(strict_types=1);

$required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'TEACHER_TOKEN'];
foreach ($required as $name) {
    if (getenv($name) === false || getenv($name) === '') {
        throw new RuntimeException("Не задана обязательная переменная {$name}");
    }
}

return [
    'database' => [
        'dsn' => sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            getenv('DB_HOST'),
            getenv('DB_PORT'),
            getenv('DB_NAME'),
        ),
        'user' => (string) getenv('DB_USER'),
        'password' => (string) getenv('DB_PASSWORD'),
    ],
    'teacher_token' => (string) getenv('TEACHER_TOKEN'),
];
