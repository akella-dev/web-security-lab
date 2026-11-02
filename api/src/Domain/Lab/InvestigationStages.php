<?php

declare(strict_types=1);

namespace App\Domain\Lab;

/**
 * Teaching material is separate from the simulated application's responses.
 * @phpstan-type Stage array{id:string,title:string,instruction:string,resources:list<array{label:string,value:string}>,request?:array{method:string,path:string,headers:array<string,string>|object,body:array<string,string>|object,mode:string}}
 */
final class InvestigationStages
{
    /**
     * @param array<string, mixed> $facts
     * @return list<Stage>
     */
    public function forChallenge(string $slug, array $facts = []): array
    {
        $value = static fn (string $key, string $fallback = ''): string => is_string($facts[$key] ?? null) ? $facts[$key] : $fallback;
        return match ($slug) {
            'idor-chain' => [
                $this->step('ticket', 'Изучить обращение', 'В разделе «Поддержка» откройте обращение №42. Установите, кто его автор и какой заказ упоминается.'),
                $this->step('proof', 'Проверить доступ к счёту', 'Обращение прочитано. Получен адрес документа к заказу. Проверьте, выдаёт ли магазин счёт другого покупателя вашей сессии.', 'GET', '/api/store/invoices/ORD-b7f3', resources: [
                    ['label' => 'Заказ из обращения', 'value' => $value('order_ref', 'ORD-b7f3')],
                    ['label' => 'Владелец обращения', 'value' => 'Алиса Воронова; ваша сессия — student@example.test'],
                ]),
            ],
            'forced-browsing' => [
                $this->step('robots', 'Изучить правила индексации', 'Откройте robots.txt в разделе «Сервисы» → «Индексация сайта». Найдите путь, исключённый из индексации.'),
                $this->step('proof', 'Проверить скрытую выгрузку', 'Путь найден. Проверьте, защищён ли экспорт правами доступа, а не только исключением из индексации.', 'GET', '/internal/export'),
            ],
            'role-escalation' => [
                $this->step('profile-admin', 'Изменить полномочия профиля', 'Исследуйте сохранение профиля в «Моём кабинете». Добейтесь административной роли через изменённый запрос, а не через интерфейс.'),
                $this->step('proof', 'Проверить служебный журнал', 'Профиль вернул административную роль. Журнал использует отдельный клиентский контекст. Сравните обычное обращение и запрос с изменённым контекстом.', 'GET', '/api/admin/audit', resources: [
                    ['label' => 'Контекст журнала', 'value' => 'Роль передаётся заголовком X-Lab-Role. Значение определите по результату первого этапа.'],
                ]),
            ],
            'stored-xss' => [
                $this->step('comment', 'Сохранить отзыв', 'Отправьте отзыв в разделе «Отзывы и поиск». Этот этап подтверждает запись, а не выполнение кода.'),
                $this->step('proof', 'Проверить последующее чтение', 'Исследуйте сохранённый ввод при открытии ленты. Для зачёта нужен безвредный alert(1) при чтении; обычного опубликованного текста недостаточно.', 'GET', '/api/comments', resources: [
                    ['label' => 'Где наблюдать', 'value' => '«Открыть ленту как следующий посетитель» в магазине. Отзывы изолированы по вашей учебной сессии.'],
                ]),
            ],
            'user-enumeration' => [
                $this->step('unknown-login', 'Зафиксировать отказ неизвестному', 'Войдите с missing@example.test и произвольным неверным паролем. Сохраните код и содержание ответа.'),
                $this->step('proof', 'Сравнить с известным аккаунтом', 'Получен контрольный отказ. Повторите вход для адреса Алисы с тем же неверным паролем. Сравните структуру ответа, а не только HTTP-статус.', resources: [
                    ['label' => 'Адрес для сравнения', 'value' => 'alice@example.test'],
                    ['label' => 'Исходный запрос', 'value' => 'Возьмите предыдущий запрос входа из журнала; измените только email.'],
                ]),
            ],
            'reset-token' => [
                $this->step('own-token', 'Получить своё письмо', 'В «Вход» → «Восстановление» запросите письмо для student@example.test. Исследуйте собственный токен.'),
                $this->step('proof', 'Проверить предсказуемость', 'Собственное письмо получено. Определите закономерность токена и проверьте восстановление для Алисы, не запрашивая её письмо.', 'POST', '/api/reset/verify', ['email' => 'alice@example.test', 'token' => ''], [
                    ['label' => 'Токен из вашего письма', 'value' => $value('delivery_preview', 'Посмотрите ответ запроса собственного письма в журнале.')],
                    ['label' => 'Поля подтверждения', 'value' => 'email — адрес аккаунта; token — проверяемое значение. Пароль в стенде не меняется.'],
                ]),
            ],
            'mfa-bypass' => [
                $this->step('password', 'Пройти первый фактор', 'Откройте «Вход» → «Два этапа» и используйте выданный аккаунт student@example.test / honey123. Неверный пароль не завершает этап.'),
                $this->step('proof', 'Исследовать завершение входа', 'Пароль принят, второй фактор ещё не подтверждён. Проверьте, контролирует ли сервер порядок переходов при создании сессии.', 'POST', '/api/auth/session', ['transaction' => $value('transaction', 'tx-student')], [
                    ['label' => 'Транзакция входа', 'value' => $value('transaction', 'tx-student')],
                    ['label' => 'Проверка одноразового кода', 'value' => 'POST /api/auth/otp · поля transaction и code'],
                    ['label' => 'Завершение входа', 'value' => 'POST /api/auth/session · поле transaction'],
                ]),
            ],
            'coupon-race' => [
                $this->step('coupon-first', 'Применить купон один раз', 'Примените HONEY20 к свежей корзине обычным одиночным запросом. Если корзина уже использована, создайте новую на витрине.'),
                $this->step('coupon-repeat', 'Зафиксировать отказ при повторе', 'Повторите применение к той же корзине в одиночном режиме. Нужен отказ 409: контроль того, что ограничение действительно существует.', 'POST', '/api/cart/coupon', ['code' => 'HONEY20', 'cart_id' => $value('cart_id', 'demo')]),
                $this->step('cart', 'Подготовить свежую корзину', 'Контрольный повтор отклонён. Создайте новую корзину, чтобы следующий опыт начался с неиспользованного купона.', 'POST', '/api/cart/new'),
                $this->step('proof', 'Сравнить с конкурентным применением', 'Используйте новую корзину. Исследуйте режим параллельной отправки и сравните число применённых скидок с контрольным опытом.', 'POST', '/api/cart/coupon', ['code' => 'HONEY20', 'cart_id' => $value('cart_id', 'demo')], [
                    ['label' => 'Новая корзина', 'value' => $value('cart_id', 'Создайте новую корзину на витрине.')],
                    ['label' => 'Доказательство', 'value' => 'Два успешных ответа недостаточны: проверьте discounts_applied и порядок событий.'],
                ]),
            ],
            'ssrf-preview' => [
                $this->step('preview', 'Проверить обычный предпросмотр', 'В «Сервисы» → «Предпросмотр ссылки» загрузите штатную страницу https://docs.example.test/help. Зафиксируйте нормальный ответ.'),
                $this->step('index', 'Исследовать внутренний сервис', 'Обычный предпросмотр работает. Для следующего опыта выдан адрес внутреннего сервиса. Проверьте его доступность через тот же механизм.', 'POST', '/api/tools/preview', ['url' => 'http://metadata.internal/latest/'], [
                    ['label' => 'Внутренний адрес', 'value' => 'http://metadata.internal/latest/'],
                    ['label' => 'Граница проверки', 'value' => 'Адрес передаётся сервису предпросмотра, а не открывается браузером.'],
                ]),
                $this->step('proof', 'Проверить доступ к закрытым данным', 'Внутренний сервис ответил. Доступен следующий материал — адрес его закрытого ресурса. Проверьте, может ли предпросмотр прочитать содержимое.', 'POST', '/api/tools/preview', ['url' => 'http://metadata.internal/latest/secret']),
            ],
            'jwt-tampering' => [
                $this->step('token', 'Получить собственный пропуск', 'Откройте «Сервисы» → «Пропуск интеграции». Получите токен текущего пользователя и разберите его структуру.'),
                $this->step('proof', 'Проверить целостность пропуска', 'Токен получен. Подготовленный запрос использует ваш исходный пропуск, а не решение. Исследуйте, можно ли изменить полномочия без действительной подписи.', 'GET', '/api/admin/reports', resources: [
                    ['label' => 'Схема авторизации', 'value' => 'Authorization: Bearer <ваш токен>'],
                    ['label' => 'Формат пропуска', 'value' => 'base64url(header).base64url(payload).signature'],
                ], headers: ['Authorization' => 'Bearer ' . $value('token')]),
            ],
            default => [$this->step('proof', 'Подтвердить результат', 'Проверьте гипотезу из цели расследования. Этап завершится, когда сервер зафиксирует требуемый результат.')],
        };
    }

    /**
     * @param array<string,string> $body
     * @param list<array{label:string,value:string}> $resources
     * @param array<string,string> $headers
     * @return Stage
     */
    private function step(string $id, string $title, string $instruction, ?string $method = null, string $path = '', array $body = [], array $resources = [], array $headers = []): array
    {
        $step = ['id' => $id, 'title' => $title, 'instruction' => $instruction, 'resources' => $resources];
        if ($method !== null) {
            $step['request'] = ['method' => $method, 'path' => $path, 'body' => $body ?: (object) [], 'headers' => $headers ?: (object) [], 'mode' => 'single'];
        }
        return $step;
    }
}
