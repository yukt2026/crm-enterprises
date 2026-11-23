<?php

namespace App\Domains\Search\Services;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Quotation;
use App\Domains\HR\Models\Employee;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Tenancy\Services\TenantContext;

class GlobalSearchService
{
    /**
     * Search authorized tenant records across CRM entities
     */
    public function search(string $query, ?string $module = null, int $limit = 20): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        $tenantId = TenantContext::getTenantId();
        $results = [];

        // 1. Leads
        if (!$module || $module === 'leads') {
            $leads = Lead::where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })->limit(5)->get();

            foreach ($leads as $lead) {
                $results[] = [
                    'id' => (string) $lead->id,
                    'title' => $lead->name,
                    'subtitle' => $lead->company ?? 'Lead Record',
                    'module' => 'leads',
                    'url' => "/leads/{$lead->id}",
                    'snippet' => "Status: {$lead->status} • Value: \${$lead->value}",
                    'status' => $lead->status,
                    'created_at' => $lead->created_at?->toIso8601String(),
                ];
            }
        }

        // 2. Deals
        if (!$module || $module === 'deals') {
            $deals = Deal::where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('stage', 'like', "%{$q}%");
            })->limit(5)->get();

            foreach ($deals as $deal) {
                $results[] = [
                    'id' => (string) $deal->id,
                    'title' => $deal->name,
                    'subtitle' => "Stage: " . ucfirst($deal->stage),
                    'module' => 'deals',
                    'url' => "/deals/{$deal->id}",
                    'snippet' => "Deal Value: \${$deal->value} • Probability: {$deal->probability}%",
                    'status' => $deal->stage,
                    'created_at' => $deal->created_at?->toIso8601String(),
                ];
            }
        }

        // 3. Contacts
        if (!$module || $module === 'contacts') {
            $contacts = Contact::where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('job_title', 'like', "%{$q}%");
            })->limit(5)->get();

            foreach ($contacts as $contact) {
                $results[] = [
                    'id' => (string) $contact->id,
                    'title' => $contact->name,
                    'subtitle' => $contact->email ?? $contact->job_title ?? 'Contact',
                    'module' => 'contacts',
                    'url' => "/contacts/{$contact->id}",
                    'snippet' => $contact->phone ? "Phone: {$contact->phone}" : "Contact",
                    'status' => 'active',
                    'created_at' => $contact->created_at?->toIso8601String(),
                ];
            }
        }

        // 4. Companies
        if (!$module || $module === 'companies') {
            $companies = Company::where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('industry', 'like', "%{$q}%")
                    ->orWhere('website', 'like', "%{$q}%");
            })->limit(5)->get();

            foreach ($companies as $company) {
                $results[] = [
                    'id' => (string) $company->id,
                    'title' => $company->name,
                    'subtitle' => $company->industry ?? 'Company Entity',
                    'module' => 'companies',
                    'url' => "/companies/{$company->id}",
                    'snippet' => "Size: {$company->size} • Tier: {$company->tier}",
                    'status' => $company->status,
                    'created_at' => $company->created_at?->toIso8601String(),
                ];
            }
        }

        // 5. Tasks
        if (!$module || $module === 'tasks') {
            $tasks = Task::where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })->limit(5)->get();

            foreach ($tasks as $task) {
                $results[] = [
                    'id' => (string) $task->id,
                    'title' => $task->title,
                    'subtitle' => "Priority: {$task->priority}",
                    'module' => 'tasks',
                    'url' => "/tasks/{$task->id}",
                    'snippet' => "Status: {$task->status} • Due: {$task->due_date}",
                    'status' => $task->status,
                    'created_at' => $task->created_at?->toIso8601String(),
                ];
            }
        }

        // 6. Support Tickets
        if (!$module || $module === 'tickets') {
            $tickets = SupportTicket::where(function ($sub) use ($q) {
                $sub->where('subject', 'like', "%{$q}%")
                    ->orWhere('ticket_number', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })->limit(5)->get();

            foreach ($tickets as $ticket) {
                $results[] = [
                    'id' => (string) $ticket->id,
                    'title' => "#{$ticket->ticket_number} - {$ticket->subject}",
                    'subtitle' => "Ticket Priority: " . ucfirst($ticket->priority),
                    'module' => 'tickets',
                    'url' => "/tickets/{$ticket->id}",
                    'snippet' => "Status: {$ticket->status}",
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at?->toIso8601String(),
                ];
            }
        }

        // 7. Invoices
        if (!$module || $module === 'invoices') {
            $invoices = Invoice::where(function ($sub) use ($q) {
                $sub->where('invoice_number', 'like', "%{$q}%")
                    ->orWhere('status', 'like', "%{$q}%");
            })->limit(5)->get();

            foreach ($invoices as $inv) {
                $results[] = [
                    'id' => (string) $inv->id,
                    'title' => $inv->invoice_number,
                    'subtitle' => "Total: \${$inv->total_amount}",
                    'module' => 'invoices',
                    'url' => "/invoices/{$inv->id}",
                    'snippet' => "Due Date: {$inv->due_date} • Status: {$inv->status}",
                    'status' => $inv->status,
                    'created_at' => $inv->created_at?->toIso8601String(),
                ];
            }
        }

        // 8. Projects
        if (!$module || $module === 'projects') {
            $projects = Project::where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })->limit(5)->get();

            foreach ($projects as $proj) {
                $results[] = [
                    'id' => (string) $proj->id,
                    'title' => $proj->name,
                    'subtitle' => "Progress: {$proj->progress}%",
                    'module' => 'projects',
                    'url' => "/projects/{$proj->id}",
                    'snippet' => "Status: {$proj->status}",
                    'status' => $proj->status,
                    'created_at' => $proj->created_at?->toIso8601String(),
                ];
            }
        }

        // Limit total aggregated results
        return array_slice($results, 0, $limit);
    }
}
