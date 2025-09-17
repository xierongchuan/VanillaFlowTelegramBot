<?php

declare(strict_types=1);

namespace App\Traits;

use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;

trait KeyboardTrait
{
    /**
     * Клавиатура пользователя (reply keyboard)
     */
    public static function userMenu(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true, one_time_keyboard: false)
            ->addRow(
                KeyboardButton::make('📝 Создать заявку'),
                KeyboardButton::make('📄 Мои заявки')
            );
        // ->addRow(
        //     KeyboardButton::make('📞 Поделиться номером', request_contact: true),
        //     KeyboardButton::make('❓ Помощь')
        // );
    }

    /**
     * Клавиатура директора
     */
    public static function directorMenu(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make('🔃 Ожидающие заявки'),
                KeyboardButton::make('📋 История заявок')
            );
        // ->addRow(
        //     KeyboardButton::make('🧾 Отчёты'),
        //     KeyboardButton::make('◀️ Назад')
        // );
    }

    /**
     * Клавиатура кассира
     */
    public static function cashierMenu(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(
                KeyboardButton::make('📝 Создать заявку'),
                KeyboardButton::make('📄 Мои заявки')
            )
            ->addRow(
                KeyboardButton::make('💰 Ожидающие выдачи'),
                KeyboardButton::make('💼 История операций')
            )
            ->addRow(
                KeyboardButton::make('⚡ Прямая выдача')
            );
    }

    /**
     * Кнопка запроса контакта (на одну кнопку) — удобно если нужен только контакт
     */
    public static function contactRequestKeyboard(string $label = 'Отправить номер'): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true, one_time_keyboard: true)
            ->addRow(KeyboardButton::make($label, request_contact: true));
    }

    /**
     * Inline клавиатура: Подтвердить выдачу (callback_data задаются)
     */
    public static function inlineConfirmIssued(
        string $confirmData = 'confirm',
    ): InlineKeyboardMarkup {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(text: '✅ Выдано', callback_data: $confirmData),
            );
    }

    /**
     * Inline клавиатура: Подтвердить выдачу полной суммы / Выдать иную сумму
     */
    public static function inlineConfirmIssuedWithAmount(
        string $confirmFullData = 'confirm_full',
        string $confirmDifferentData = 'confirm_different_amount'
    ): InlineKeyboardMarkup {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(text: '✅ Выдать полную сумму', callback_data: $confirmFullData),
            )
            ->addRow(
                InlineKeyboardButton::make(text: '💰 Выдать иную сумму', callback_data: $confirmDifferentData),
            );
    }

    /**
     * Inline клавиатура: Подтвердить / Отменить (callback_data задаются)
     */
    public static function inlineConfirmDecline(
        string $confirmData = 'confirm',
        string $declineData = 'decline'
    ): InlineKeyboardMarkup {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(text: '✅ Подтвердить', callback_data: $confirmData),
                InlineKeyboardButton::make(text: '❌ Отменить', callback_data: $declineData),
            );
    }

    /**
     * Inline клавиатура: Подтвердить / Подтвердить с комментом / Отменить (callback_data задаются)
     */
    public static function inlineConfirmCommentDecline(
        string $confirmData = 'confirm',
        string $confirmWithCommentData = 'confirm_with_comment',
        string $declineData = 'decline'
    ): InlineKeyboardMarkup {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(text: '✅ Подтвердить', callback_data: $confirmData),
                InlineKeyboardButton::make(text: '❌ Отменить', callback_data: $declineData),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💬 Подтвердить с комментарием',
                    callback_data: $confirmWithCommentData
                ),
            );
    }

    /**
     * ReplyKeyboardRemove — убрать reply keyboard
     */
    public static function removeKeyboard(): ReplyKeyboardRemove
    {
        return ReplyKeyboardRemove::make(true, selective: false);
    }

    /**
     * Сгенерировать inline-клавиатуру из массива:
     * $buttons = [
     *   [ ['text'=>'A','data'=>'a'], ['text'=>'B','data'=>'b'] ],
     *   [ ['text'=>'C','data'=>'c'] ]
     * ];
     */
    public static function inlineFromArray(array $buttons): InlineKeyboardMarkup
    {
        $ik = InlineKeyboardMarkup::make();
        foreach ($buttons as $row) {
            $ikRow = [];
            foreach ($row as $btn) {
                $ikRow[] = InlineKeyboardButton::make(text: $btn['text'], callback_data: $btn['data']);
            }
            $ik->row($ikRow);
        }
        return $ik;
    }

    /**
     * Быстрая reply клавиатура с Yes/No (удобно для простых вопросов)
     */
    public static function yesNoKeyboard(string $yes = 'Да', string $no = 'Нет'): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true, one_time_keyboard: true)
            ->addRow(
                KeyboardButton::make($yes),
                KeyboardButton::make($no)
            );
    }
}
