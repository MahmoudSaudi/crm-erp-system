<?php

namespace App\Models\ERP;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category',
        'amount',
        'description',
        'date',
        'receipt',
        'is_reimbursable',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'is_reimbursable' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where('description', 'like', "%{$term}%")
            ->orWhere('category', 'like', "%{$term}%");
    }

    public static function categories(): array
    {
        return ['إيجار', 'رواتب', 'نقل', 'مصاريف تشغيلية', 'تسويق', 'صيانة', 'اتصالات', 'أخرى'];
    }
}
