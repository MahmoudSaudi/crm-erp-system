<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Sent => 'مرسلة',
            self::Partial => 'مدفوعة جزئيًا',
            self::Paid => 'مدفوعة',
            self::Overdue => 'متأخرة',
            self::Cancelled => 'ملغاة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::Sent => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            self::Partial => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
            self::Paid => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
            self::Overdue => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
            self::Cancelled => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
