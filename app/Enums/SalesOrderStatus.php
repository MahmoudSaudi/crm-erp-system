<?php

namespace App\Enums;

enum SalesOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Confirmed => 'مؤكد',
            self::Fulfilled => 'منفذ',
            self::Cancelled => 'ملغي',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::Confirmed => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            self::Fulfilled => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
            self::Cancelled => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
