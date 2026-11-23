<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Tenancy\Services\TenantContext;

class Customer360Service
{
    /**
     * Retrieve complete Customer 360-degree profile
     */
    public function getProfile(int $customerId): array
    {
        $tenantId = TenantContext::getTenantId() ?? 1;

        $customer = Customer::where('tenant_id', $tenantId)
            ->with(['company', 'contact', 'lead'])
            ->findOrFail($customerId);

        $contactId = $customer->contact_id;
        $companyId = $customer->company_id;
        $leadId = $customer->lead_id;

        // 1. Core Profile Details
        $profile = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'status' => $customer->status,
            'tier' => $customer->tier,
            'company' => $customer->company ? [
                'id' => $customer->company->id,
                'name' => $customer->company->name,
                'industry' => $customer->company->industry,
                'website' => $customer->company->website,
                'phone' => $customer->company->phone,
                'address' => $customer->company->address,
                'account_status' => $customer->company->account_status,
            ] : null,
            'contact' => $customer->contact ? [
                'id' => $customer->contact->id,
                'name' => $customer->contact->name,
                'job_title' => $customer->contact->job_title,
                'email' => $customer->contact->email,
                'phone' => $customer->contact->phone,
                'consent_status' => $customer->contact->consent_status,
            ] : null,
        ];

        // 2. Lead Attribution & Origin
        $lead = $customer->lead;
        if (!$lead && $contactId) {
            $lead = Lead::where('converted_contact_id', $contactId)->first();
        }
        $profile['lead_origin'] = $lead ? [
            'id' => $lead->id,
            'source' => $lead->source,
            'campaign' => $lead->campaign,
            'utm_source' => $lead->utm_source,
            'utm_medium' => $lead->utm_medium,
            'utm_campaign' => $lead->utm_campaign,
            'first_touch' => $lead->first_touch_attribution,
            'last_touch' => $lead->last_touch_attribution,
            'score' => $lead->score,
            'converted_at' => $lead->converted_at?->toIso8601String(),
        ] : null;

        // 3. Sales Deals & Pipeline
        $deals = Deal::where('tenant_id', $tenantId)
            ->where(function ($q) use ($companyId, $contactId, $leadId) {
                if ($companyId) $q->orWhere('company_id', $companyId);
                if ($contactId) $q->orWhere('contact_id', $contactId);
                if ($leadId) $q->orWhere('lead_id', $leadId);
            })
            ->with('assignedUser:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        $profile['deals'] = [
            'items' => $deals,
            'total_count' => $deals->count(),
            'total_value' => $deals->sum('value'),
            'won_value' => $deals->where('stage', 'won')->sum('value'),
        ];

        // 4. Chronological Activity Timeline
        $activities = CrmActivity::where('tenant_id', $tenantId)
            ->where(function ($q) use ($customerId, $contactId, $companyId, $leadId) {
                $q->where('customer_id', $customerId);
                if ($contactId) $q->orWhere('contact_id', $contactId);
                if ($companyId) $q->orWhere('company_id', $companyId);
                if ($leadId) $q->orWhere('lead_id', $leadId);
            })
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $profile['timeline'] = $activities;

        // 5. Files and Attachments (Polymorphic MediaFiles)
        $mediaFiles = MediaFile::where('tenant_id', $tenantId)
            ->where(function ($q) use ($customerId, $leadId) {
                $q->where(function ($sub) use ($customerId) {
                    $sub->where('fileable_type', Customer::class)
                        ->where('fileable_id', $customerId);
                });
                if ($leadId) {
                    $q->orWhere(function ($sub) use ($leadId) {
                        $sub->where('fileable_type', Lead::class)
                            ->where('fileable_id', $leadId);
                    });
                }
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($f) {
                $item = $f->toArray();
                $item['download_url'] = $f->getSignedUrl(60);
                return $item;
            });

        $profile['attachments'] = $mediaFiles;

        return $profile;
    }
}
