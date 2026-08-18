<?php

namespace App\Enums;

enum PayrollStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Approved => 'معتمد',
            self::Paid => 'مدفوع',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::Approved => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            self::Paid => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}