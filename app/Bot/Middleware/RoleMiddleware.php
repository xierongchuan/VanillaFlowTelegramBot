<?php

declare(strict_types=1);

namespace App\Bot\Middleware;

use SergiX44\Nutgram\Nutgram;
use App\Models\User;

class RoleMiddleware
{
    /** @var array<string> */
    protected array $allowed;

    /**
     * @param array<string> $allowed allowed role names, e.g. ['user','manager','cashier']
     */
    public function __construct(array $allowed = [])
    {
        $this->allowed = $allowed;
    }

    /**
     * Nutgram middleware must be callable: function(Nutgram $bot, $next)
     * Using __invoke makes the class instance invokable as middleware.
     */
    public function __invoke(Nutgram $bot, $next)
    {
        $user = auth()->user();

        // Если роли переданы пустые — разрешаем всем залогиненным пользователям
        if (!empty($this->allowed)) {
            $roleName = $user->role ?? 'guest';

            if (!in_array($roleName, $this->allowed, true)) {
                $bot->sendMessage('У вас недостаточно прав для выполнения этой команды.');
                return;
            }
        }

        // Сохраняем модель пользователя в контексте бота для следующих middleware/handler'ов.
        // На самом деле нету такой переменной в Nutgram $bot как user но мы пока что насильно так засовываем изменения
        $bot->user = $user;

        // Пропускаем дальше
        return $next($bot);
    }
}
