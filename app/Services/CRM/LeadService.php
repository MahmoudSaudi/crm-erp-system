<?php

namespace App\Services\CRM;

use App\Enums\ActivityType;
use App\Enums\LeadStatus;
use App\Models\CRM\Activity;
use App\Models\CRM\Customer;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\OpportunityStage;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class LeadService
{
    /**
     * Converts a lead into a customer and creates a fresh opportunity
     * linked to both. Atomic + audited.
     */
    public function convert(Lead $lead, ?string $opportunityTitle = null): Customer
    {
        return DB::transaction(function () use ($lead, $opportunityTitle) {
            $customer = Customer::create([
                'name' => $lead->full_name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'company' => $lead->company,
                'created_from_lead_id' => $lead->id,
                'created_by' => auth()->id(),
            ]);

            $defaultStage = OpportunityStage::where('slug', 'new')->first();

            if ($defaultStage) {
                $opportunity = Opportunity::create([
                    'title' => $opportunityTitle ?: "فرصة {$lead->full_name}",
                    'customer_id' => $customer->id,
                    'lead_id' => $lead->id,
                    'stage_id' => $defaultStage->id,
                    'amount' => $lead->value ?? 0,
                    'probability' => 10,
                    'assigned_to' => $lead->assigned_to,
                    'created_by' => auth()->id(),
                ]);

                Activity::create([
                    'type' => ActivityType::Note->value,
                    'subject' => 'تحويل عميل محتمل إلى عميل',
                    'description' => "تم تحويل {$lead->full_name} إلى عميل وإنشاء فرصة مرتبطة.",
                    'related_type' => Opportunity::class,
                    'related_id' => $opportunity->id,
                    'assigned_to' => $lead->assigned_to,
                    'created_by' => auth()->id(),
                ]);
            }

            $lead->update([
                'status' => LeadStatus::Converted->value,
                'converted_at' => now(),
            ]);

            AuditLogger::log('converted', $lead, [
                'status' => LeadStatus::New->value,
            ], [
                'status' => LeadStatus::Converted->value,
                'customer_id' => $customer->id,
            ]);

            return $customer;
        });
    }
}
