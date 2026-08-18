<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'منخفضة',
            self::Medium => 'متوسطة',
            self::High => 'عالية',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::Medium => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            self::High => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
