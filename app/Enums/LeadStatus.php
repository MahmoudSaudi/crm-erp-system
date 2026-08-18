<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Converted = 'converted';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::Contacted => 'تم التواصل',
            self::Qualified => 'مؤهل',
            self::Converted => 'تحول لعميل',
            self::Lost => 'خاسر',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            self::Contacted => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
            self::Qualified => 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
            self::Converted => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
            self::Lost => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
        };
    }

    public function dot(): string
    {
        return match ($this) {
            self::New => '#0ea5e9',
            self::Contacted => '#f59e0b',
            self::Qualified => '#8b5cf6',
            self::Converted => '#10b981',
            self::Lost => '#f43f5e',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
