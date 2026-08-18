<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Leave = 'leave';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'حاضر',
            self::Absent => 'غائب',
            self::Late => 'متأخر',
            self::Leave => 'إجازة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
            self::Absent => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
            self::Late => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
            self::Leave => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}