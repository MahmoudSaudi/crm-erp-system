<?php

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpportunityStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'position',
        'is_won',
        'is_lost',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'stage_id');
    }

    public static function ordered()
    {
        return self::orderBy('position')->get();
    }
}
