<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\LeadStatus;
use App\Models\CRM\Activity;
use App\Models\CRM\Customer;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\OpportunityStage;
use App\Models\User;
use Illuminate\Database\Seeder;

class CrmDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Lead::exists() || Customer::exists()) {
            $this->command->warn('تم تجاوز سيدر CRM Demo: توجد بيانات Leads/Customers سابقة.');

            return;
        }

        [$rep, $manager, $support] = $this->salesUsers();
        $salesUsers = collect([$rep, $manager]);

        $leadStatuses = [
            LeadStatus::New->value => 8,
            LeadStatus::Contacted->value => 8,
            LeadStatus::Qualified->value => 6,
            LeadStatus::Lost->value => 6,
        ];

        $leads = collect();

        $names = [
            ['أحمد', 'خالد'], ['سمر', 'يوسف'], ['محمد', 'عبد الرحمن'], ['نور', 'حسني'],
            ['خالد', 'صبري'], ['رنا', 'علي'], ['يوسف', 'نبيل'], ['ليلى', 'سمير'],
            ['عمر', 'فادي'], ['هبة', 'منير'], ['طارق', 'إبراهيم'], ['دينا', 'رشيد'],
            ['سامي', 'عوده'], ['رانيا', 'زكي'], ['فادي', 'مجدي'], ['آية', 'عماد'],
            ['باسم', 'عطا'], ['شهد', 'قدورة'], ['مازن', 'شحادة'], ['ريم', 'فؤاد'],
            ['وليد', 'حمدالله'], ['مياء', 'صلاح'], ['جمال', 'عوض'], ['ضحى', 'ناصر'],
            ['حسام', 'الخطيب'], ['عبير', 'شاهين'], ['زياد', 'أبو غزالة'], ['هند', 'العمد'],
        ];

        $sources = ['إعلان فيسبوك', 'جوجل', 'توصية', 'لينكدإن', 'معرض تجاري', 'موقع الويب', 'مكالمة واردة'];
        $companies = ['شركة الأمل للتجارة', 'مؤسسة الأنوار', 'شركة الراية', 'مخازن القدس', 'شركة النورس', 'زاوية الأفق', 'بصريات وطن', 'تدوير الشرق'];

        $i = 0;
        foreach ($leadStatuses as $status => $count) {
            for ($n = 0; $n < $count; $n++) {
                [$first, $last] = $names[$i % count($names)];
                $assignee = $salesUsers->random();
                $createdDaysAgo = rand(0, 20);

                $lead = Lead::create([
                    'first_name' => $first,
                    'last_name' => $last,
                    'phone' => '0599'.str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                    'email' => strtolower($this->toAscii($first).'.'.$this->toAscii($last)).rand(10, 99).'@example.com',
                    'company' => rand(0, 1) ? $companies[array_rand($companies)] : null,
                    'source' => $sources[array_rand($sources)],
                    'status' => $status,
                    'value' => $status === LeadStatus::Lost->value ? null : rand(1000, 50000) / 10 * 10,
                    'notes' => rand(0, 1) ? 'عميل مهتم بالمنتجات، يحتاج متابعة دورية.' : null,
                    'next_follow_up_at' => $status === LeadStatus::Contacted->value ? now()->addDays(rand(1, 7)) : null,
                    'assigned_to' => $assignee->id,
                    'created_by' => $assignee->id,
                    'created_at' => now()->subDays($createdDaysAgo),
                    'updated_at' => now()->subDays($createdDaysAgo),
                ]);

                $leads->push($lead);
                $i++;
            }
        }

        // تحويل جزء من المؤهلين إلى عملاء حقيقيين (مع فرص مرتبطة)
        $convertedLeads = $leads->where('status', LeadStatus::Qualified->value)->take(3);

        foreach ($convertedLeads as $lead) {
            $isCompany = rand(0, 1) === 1;
            $customer = Customer::create([
                'name' => $lead->full_name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'type' => $isCompany ? 'company' : 'individual',
                'company' => $isCompany ? ($lead->company ?: 'شركة '.$lead->full_name) : null,
                'tax_number' => $isCompany ? (string) rand(100000000, 999999999) : null,
                'address' => 'شارع النصر، رام الله',
                'city' => $this->cities()[array_rand($this->cities())],
                'credit_limit' => rand(5000, 50000) / 10 * 10,
                'balance' => rand(0, 30000) / 10 * 10,
                'status' => 'active',
                'created_from_lead_id' => $lead->id,
                'created_by' => $lead->assigned_to,
            ]);

            $lead->update([
                'status' => LeadStatus::Converted->value,
                'converted_at' => now()->subDays(rand(1, 6)),
            ]);
$stage = OpportunityStage::whereIn('slug', ['new', 'qualified', 'proposal'])->first();

            Opportunity::create([
                'title' => 'فرصة '.$customer->name,
                'customer_id' => $customer->id,
                'lead_id' => $lead->id,
                'stage_id' => $stage->id,
                'amount' => rand(8000, 60000) / 10 * 10,
                'probability' => rand(20, 70),
                'expected_close_date' => now()->addDays(rand(7, 45)),
                'description' => 'فرصة ناتجة عن تحويل عميل محتمل، بحاجة إلى عرض سعر ومتابعة.',
                'assigned_to' => $lead->assigned_to,
                'created_by' => $lead->assigned_to,
            ]);
        }

        // عملاء إضافيون مباشرون (ليسوا من Leads)
        $directCustomers = [
            ['نور الدين زيتون', 'individual', null, 25000, 0],
            ['مؤسسة البدر التجارية', 'company', 'شركة الإنشاءات الحديثة', 100000, 18000],
            ['سعدية عمران', 'individual', null, 8000, 1500],
            ['شركة المستقبل للتقنية', 'company', 'شركة المستقبل للتقنية', 75000, 45000],
            ['جميل القواس', 'individual', null, 15000, 0],
            ['مخزن روائع الشام', 'company', 'مخزن روائع الشام', 50000, 22000],
            ['هيثم البرغوثي', 'individual', null, 12000, 6000],
            ['جمعية الأمل الخيرية', 'company', 'جمعية الأمل الخيرية', 40000, 0],
            ['عصام عوض', 'individual', null, 9000, 0],
            ['شركة وطن للتوزيع', 'company', 'شركة وطن للتوزيع', 120000, 85000],
            ['روان حجازي', 'individual', null, 3000, 0],
            ['مصنع النخبة للألبان', 'company', 'مصنع النخبة للألبان', 60000, 30000],
        ];

        $direct = collect();
        foreach ($directCustomers as [$cName, $cType, $cCompany, $cLimit, $cBalance]) {
            $cCity = $this->cities()[array_rand($this->cities())];
            $assignee = $salesUsers->random();

            $direct->push(Customer::create([
                'name' => $cName,
                'phone' => '0598'.str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                'email' => strtolower($this->toAscii($cName)).'@company.ps',
                'type' => $cType,
                'company' => $cCompany,
                'tax_number' => $cType === 'company' ? (string) rand(100000000, 999999999) : null,
                'address' => 'شارع الجلاء، '.$cCity,
                'city' => $cCity,
                'credit_limit' => $cLimit,
                'balance' => $cBalance,
                'status' => rand(0, 10) === 0 ? 'inactive' : 'active',
                'created_by' => $assignee->id,
            ]));
        }

        // فرص إضافية للعملاء المباشرين عبر مراحل مختلفة
        $stages = OpportunityStage::ordered();
        $titles = ['توسعة خط إنتاج', 'توريد أجهزة مكتبية', 'عقد صيانة سنوي', 'تجهيز فرع جديد', 'نظام مراقبة مخزون', 'طباعة وتغليف'];
        $customersPool = $direct->merge(Customer::whereNotNull('created_from_lead_id')->with('createdFromLead')->get());

        foreach (range(1, 9) as $idx) {
            $customer = $customersPool->random();
            $stage = $stages->random();
            $assignee = $salesUsers->random();
            $amount = rand(5000, 80000) / 10 * 10;

            Opportunity::create([
                'title' => $titles[array_rand($titles)],
                'customer_id' => $customer->id,
                'lead_id' => $customer->created_from_lead_id,
                'stage_id' => $stage->id,
                'amount' => $amount,
                'probability' => $stage->is_won ? 100 : ($stage->is_lost ? 0 : rand(10, 90)),
                'expected_close_date' => ($stage->is_won || $stage->is_lost) ? now()->subDays(rand(1, 15)) : now()->addDays(rand(5, 60)),
                'description' => 'فرصة بيع قيد المتابعة مع مندوب '.(optional($assignee)->name ?? 'المبيعات').'.',
                'assigned_to' => $assignee->id,
                'created_by' => $assignee->id,
                'closed_at' => ($stage->is_won || $stage->is_lost) ? now()->subDays(rand(1, 15)) : null,
            ]);
        }

        // أنشطة موزعة على العملاء والفرص
        $this->seedActivities($leads, $salesUsers);

        // تذاكر دعم تجريبية
        $this->tickets();

        $this->command->info('تم إدخال بيانات CRM تجريبية: ');
        $this->command->line('  - Leads: '.Lead::count());
        $this->command->line('  - Customers: '.Customer::count());
        $this->command->line('  - Opportunities: '.Opportunity::count());
        $this->command->line('  - Activities: '.Activity::count());
        $this->command->line('  - Tickets: '.\App\Models\CRM\Ticket::count());
    }

    private function tickets(): void
    {
        if (\App\Models\CRM\Ticket::exists()) {
            return;
        }

        [$rep, $manager, $support] = $this->salesUsers();
        $customers = \App\Models\CRM\Customer::orderBy('id')->take(6)->get();
        $categories = ['فني', 'محاسبي', 'مبيعات', 'شحن', 'أخرى'];
        $statuses = ['open', 'in_progress', 'resolved', 'resolved', 'closed'];
        $priorities = ['low', 'medium', 'medium', 'high'];

        $sample = [
            'الموقع يظهر أخطاء عند فتح صفحة المنتجات',
            'تأخر في استلام الشحنة الأخيرة',
            'استفسار عن فاتورة شهر الماضي',
            'طلب تعديل بيانات العميل',
            'مشكلة في تسجيل الخروج من النظام',
            'طلب دعم فني لتركيب البرنامج',
            'ملاحظة على واجهة التقارير',
            'استفسار عن سياسة الاسترجاع',
        ];

        foreach ($sample as $i => $text) {
            $ticket = \App\Models\CRM\Ticket::create([
                'ticket_number' => 'TK-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                'customer_id' => $customers->get($i % 3)?->id,
                'subject' => $text,
                'message' => 'العميل أرسل هذه الشكوى ويطلب المتابعة السريعة.',
                'category' => $categories[$i % count($categories)],
                'priority' => $priorities[$i % count($priorities)],
                'status' => $statuses[$i % count($statuses)],
                'assigned_to' => $support?->id,
                'created_by' => $rep?->id,
                'resolved_at' => in_array($statuses[$i % count($statuses)], ['resolved', 'closed'], true) ? now()->subDays($i) : null,
                'created_at' => now()->subDays($i * 2 + 1),
                'updated_at' => now()->subDays($i * 2 + 1),
            ]);

            if ($ticket->status === 'in_progress') {
                \App\Models\CRM\TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $support?->id,
                    'body' => 'تم استلام الشكوى وجارٍ التحقيق مع الفريق الفني.',
                    'is_internal' => false,
                    'created_at' => now()->subDays($i * 2),
                    'updated_at' => now()->subDays($i * 2),
                ]);
            }
        }
    }

    private function seedActivities($leads, $salesUsers): void
    {
        $activityTypes = ActivityType::cases();
        $subjects = [
            ActivityType::Call->value => ['مكالمة متابعة', 'مكالمة تأكيد موعد', 'مكالمة عروض'],
            ActivityType::Email->value => ['إرسال عرض سعر', 'متابعة بريدية', 'رسالة ترحيب'],
            ActivityType::Meeting->value => ['اجتماع عرض منتج', 'اجتماع تفاوض نهائي'],
            ActivityType::Task->value => ['تجهيز عرض سعر', 'تحديث السجل', 'تحضير مواد العرض'],
            ActivityType::Note->value => ['ملاحظة بعد مكالمة', 'متابعة من العميل'],
        ];

        $entities = $leads
            ->map(fn (Lead $lead) => ['type' => Lead::class, 'item' => $lead])
            ->concat(Customer::all()->map(fn (Customer $c) => ['type' => Customer::class, 'item' => $c]))
            ->concat(Opportunity::all()->map(fn (Opportunity $o) => ['type' => Opportunity::class, 'item' => $o]));

        foreach (range(1, 30) as $i) {
            $entry = $entities->random();
            $type = $activityTypes[array_rand($activityTypes)];
            $assignee = $salesUsers->random();
            $completed = rand(0, 1) === 1;
            $daysAgo = rand(0, 14);

            Activity::create([
                'type' => $type->value,
                'subject' => $subjects[$type->value][array_rand($subjects[$type->value])],
                'description' => $this->sentence(),
                'related_type' => $entry['type'],
                'related_id' => $entry['item']->id,
                'scheduled_at' => $completed ? null : now()->addDays(rand(1, 6)),
                'completed_at' => $completed ? now()->subDays($daysAgo) : null,
                'assigned_to' => $assignee->id,
                'created_by' => $assignee->id,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]);
        }
    }

    private function salesUsers(): array
    {
        $users = User::whereIn('email', ['rep@crm.test', 'manager@crm.test', 'support@crm.test'])->get();

        return [
            $users->where('email', 'rep@crm.test')->first() ?? $users->first(),
            $users->where('email', 'manager@crm.test')->first() ?? $users->first(),
            $users->where('email', 'support@crm.test')->first() ?? $users->first(),
        ];
    }

    private function cities(): array
    {
        return ['رام الله', 'نابلس', 'الخليل', 'بيت لحم', 'جنين', 'طولكرم', 'قلقيلية', 'القدس', 'غزة', 'أريحا'];
    }

    private function toAscii(string $text): string
    {
        $map = ['أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ا' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h', 'و' => 'w', 'ي' => 'y', 'ة' => 'h'];

        return strtr($text, $map) ?: 'user';
    }

    private function sentence(): string
    {
        $sentences = [
            'تمت مناقشة احتياجات العميل وتوضيح العروض المتاحة.',
            'تم الاتفاق على موعد لتسليم العرض المبدئي.',
            'العميل طلب نسخة من الكتالوج وتحديث الأسعار.',
            'تمت المتابعة وجدولة اجتماع للمرحلة القادمة.',
            'يحتاج العميل توضيحًا حول شروط الدفع والضمان.',
            'تم إرسال تأكيد الموعد والتفاصيل المتعلقة بالطلب.',
        ];

        return $sentences[array_rand($sentences)];
    }
}