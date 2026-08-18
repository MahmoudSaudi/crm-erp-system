<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'مفتوحة',
            self::InProgress => 'قيد المعالجة',
            self::Resolved => 'تم الحل',
            self::Closed => 'مغلقة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            self::InProgress => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
            self::Resolved => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
            self::Closed => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
