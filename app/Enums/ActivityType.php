<?php

namespace App\Enums;

enum ActivityType: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Task = 'task';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'مكالمة',
            self::Email => 'بريد إلكتروني',
            self::Meeting => 'اجتماع',
            self::Task => 'مهمة',
            self::Note => 'ملاحظة',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
