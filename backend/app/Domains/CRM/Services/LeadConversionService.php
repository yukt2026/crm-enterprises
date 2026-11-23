<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\DB;

class LeadConversionService
{
    /**
     * Convert lead into Contact, Company, and Deal without data loss
     */
    public function convertLead(Lead $lead, array $options = [], ?int $userId = null): array
    {
        $tenantId = $lead->tenant_id ?? TenantContext::getTenantId() ?? 1;

        return DB::transaction(function () use ($lead, $options, $tenantId, $userId) {
            // 1. Company Resolution (Create or Link Existing)
            $company = null;
            if (!empty($options['company_id'])) {
                $company = Company::where('tenant_id', $tenantId)->find($options['company_id']);
            }

            if (!$company) {
                $companyName = !empty($options['company_name']) 
                    ? $options['company_name'] 
                    : (!empty($lead->company_name) ? $lead->company_name : "{$lead->name}'s Company");

                $company = Company::create([
                    'tenant_id' => $tenantId,
                    'lead_id' => $lead->id,
                    'name' => $companyName,
                    'phone' => $lead->phone,
                    'address' => $lead->address,
                    'billing_city' => $lead->city,
                    'billing_state' => $lead->state,
                    'billing_country' => $lead->country ?? 'US',
                    'billing_postal_code' => $lead->postal_code,
                    'account_owner_id' => $lead->assigned_user_id,
                    'account_status' => 'prospect',
                    'tags' => $lead->tags,
                ]);
            }

            // 2. Contact Resolution (Prevent duplicate contact creation)
            $contact = null;
            if (!empty($options['contact_id'])) {
                $contact = Contact::where('tenant_id', $tenantId)->find($options['contact_id']);
            }
            if (!$contact && !empty($lead->phone)) {
                $contact = Contact::where('tenant_id', $tenantId)->where('phone', $lead->phone)->first();
            }
            if (!$contact && !empty($lead->email)) {
                $contact = Contact::where('tenant_id', $tenantId)->where('email', $lead->email)->first();
            }

            if (!$contact) {
                $contact = Contact::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $company->id,
                    'lead_id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'job_title' => $lead->designation,
                    'address' => $lead->address,
                    'city' => $lead->city,
                    'state' => $lead->state,
                    'country' => $lead->country ?? 'India',
                    'postal_code' => $lead->postal_code,
                    'is_primary' => true,
                    'tags' => $lead->tags,
                ]);
            }

            // 3. Customer 360 Account Link
            $customer = Customer::where('tenant_id', $tenantId)
                ->where(function ($q) use ($contact, $company) {
                    $q->where('contact_id', $contact->id)->orWhere('company_id', $company->id);
                })
                ->first();

            if (!$customer) {
                $customer = Customer::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $company->id,
                    'contact_id' => $contact->id,
                    'lead_id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'status' => 'active',
                    'tier' => 'standard',
                ]);
            }

            // 4. Deal Creation (Optional, enabled by default)
            $deal = null;
            if ($options['create_deal'] ?? true) {
                $dealName = !empty($options['deal_name'])
                    ? $options['deal_name']
                    : ($lead->product_interest ? "{$lead->product_interest} - {$company->name}" : "Deal for {$lead->name}");

                $deal = Deal::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $company->id,
                    'contact_id' => $contact->id,
                    'lead_id' => $lead->id,
                    'pipeline_id' => $options['pipeline_id'] ?? 1,
                    'name' => $dealName,
                    'value' => (float) ($options['deal_value'] ?? $lead->estimated_value ?? 10000),
                    'stage' => $options['deal_stage'] ?? 'qualification',
                    'probability' => 20,
                    'close_date' => $options['close_date'] ?? now()->addDays(30),
                    'assigned_user_id' => $lead->assigned_user_id,
                    'tags' => $lead->tags,
                ]);
            }

            // 4.5. Project Creation (Optional)
            $project = null;
            if (!empty($options['create_project'])) {
                $project = \App\Domains\Projects\Models\Project::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'company_id' => $company->id,
                    'owner_id' => $userId ?? $lead->assigned_user_id,
                    'name' => $options['project_name'] ?? "Project: {$company->name}",
                    'description' => "Created from converted lead: {$lead->name}",
                    'status' => 'planning',
                    'priority' => 'medium',
                    'budget' => (float) ($options['deal_value'] ?? $lead->estimated_value ?? 0),
                ]);
            }

            // 5. Update Lead Record to Converted
            $lead->converted_at = now();
            $lead->converted_contact_id = $contact->id;
            $lead->converted_company_id = $company->id;
            $lead->converted_deal_id = $deal?->id;
            $lead->status = 'converted';
            $lead->lifecycle_stage = 'opportunity';
            $lead->qualification_status = 'qualified';
            $lead->save();

            // 6. Record Conversion Activity on Timeline
            CrmActivity::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId ?? $lead->assigned_user_id,
                'lead_id' => $lead->id,
                'contact_id' => $contact->id,
                'company_id' => $company->id,
                'deal_id' => $deal?->id,
                'customer_id' => $customer->id,
                'activity_type' => 'conversion',
                'title' => "Lead Successfully Converted",
                'description' => "Converted into Contact '{$contact->name}', Company '{$company->name}'" . ($deal ? " and Deal '{$deal->name}' (\${$deal->value})" : "."),
                'metadata' => [
                    'contact_id' => $contact->id,
                    'company_id' => $company->id,
                    'deal_id' => $deal?->id,
                    'customer_id' => $customer->id,
                ],
            ]);

            return [
                'lead' => $lead->fresh(['convertedContact', 'convertedCompany', 'convertedDeal']),
                'contact' => $contact,
                'company' => $company,
                'deal' => $deal,
                'customer' => $customer,
                'project' => $project,
            ];
        });
    }
}
