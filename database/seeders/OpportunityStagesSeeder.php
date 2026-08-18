<?php

namespace Database\Seeders;

use App\Models\CRM\OpportunityStage;
use Illuminate\Database\Seeder;

class OpportunityStagesSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['name' => 'جديد', 'slug' => 'new', 'color' => '#0ea5e9', 'position' => 1, 'is_won' => false, 'is_lost' => false],
            ['name' => 'مؤهل', 'slug' => 'qualified', 'color' => '#8b5cf6', 'position' => 2, 'is_won' => false, 'is_lost' => false],
            ['name' => 'عرض سعر', 'slug' => 'proposal', 'color' => '#f59e0b', 'position' => 3, 'is_won' => false, 'is_lost' => false],
            ['name' => 'تفاوض', 'slug' => 'negotiation', 'color' => '#f97316', 'position' => 4, 'is_won' => false, 'is_lost' => false],
            ['name' => 'فوز', 'slug' => 'won', 'color' => '#10b981', 'position' => 5, 'is_won' => true, 'is_lost' => false],
            ['name' => 'خسارة', 'slug' => 'lost', 'color' => '#f43f5e', 'position' => 6, 'is_won' => false, 'is_lost' => true],
        ];

        foreach ($stages as $stage) {
            OpportunityStage::updateOrCreate(
                ['slug' => $stage['slug']],
                $stage
            );
        }
    }
}
