<?php

namespace App\Domains\Workflow\Services;

class TriggerRegistry
{
    /**
     * Complete catalog of CRM automation triggers grouped by domain.
     */
    public const TRIGGERS = [
        'leads' => [
            'name' => 'Leads & Enquiries',
            'triggers' => [
                'lead.created' => [
                    'label' => 'New Lead Received',
                    'description' => 'Triggers when a new lead is ingested or manually captured',
                    'entity_type' => 'lead',
                    'sample_payload' => ['entity_id' => 1, 'entity_type' => 'lead', 'lead' => ['id' => 1, 'title' => 'Enterprise Prospect', 'score' => 85, 'status' => 'new', 'source' => 'Meta Ads', 'branch_id' => 1]],
                ],
                'lead.updated' => [
                    'label' => 'Lead Updated',
                    'description' => 'Triggers when any standard or custom field on a lead is modified',
                    'entity_type' => 'lead',
                    'sample_payload' => ['entity_id' => 1, 'entity_type' => 'lead', 'lead' => ['id' => 1, 'status' => 'contacted', 'score' => 90]],
                ],
                'lead.assigned' => [
                    'label' => 'Lead Assigned',
                    'description' => 'Triggers when a lead is assigned or reassigned to a team member',
                    'entity_type' => 'lead',
                    'sample_payload' => ['entity_id' => 1, 'entity_type' => 'lead', 'assigned_user_id' => 2],
                ],
                'lead.score_changed' => [
                    'label' => 'Lead Score Changed',
                    'description' => 'Triggers when the AI or rule-based score of a lead updates',
                    'entity_type' => 'lead',
                    'sample_payload' => ['entity_id' => 1, 'entity_type' => 'lead', 'old_score' => 45, 'new_score' => 85],
                ],
                'lead.status_changed' => [
                    'label' => 'Lead Status Changed',
                    'description' => 'Triggers when a lead transitions through pipeline stages',
                    'entity_type' => 'lead',
                    'sample_payload' => ['entity_id' => 1, 'entity_type' => 'lead', 'old_status' => 'new', 'new_status' => 'qualified'],
                ],
                'lead.followup_overdue' => [
                    'label' => 'Lead Follow-up Overdue',
                    'description' => 'Triggers when scheduled lead follow-up passes without contact',
                    'entity_type' => 'lead',
                    'sample_payload' => ['entity_id' => 1, 'entity_type' => 'lead', 'overdue_minutes' => 60],
                ],
                'lead.converted' => [
                    'label' => 'Lead Converted',
                    'description' => 'Triggers when a lead is converted into a Customer / Deal',
                    'entity_type' => 'lead',
                    'sample_payload' => ['entity_id' => 1, 'customer_id' => 10, 'deal_id' => 5],
                ],
            ],
        ],

        'customers' => [
            'name' => 'Customers & Contacts',
            'triggers' => [
                'contact.created' => [
                    'label' => 'New Contact Added',
                    'description' => 'Triggers when a new contact record is created',
                    'entity_type' => 'contact',
                    'sample_payload' => ['entity_id' => 1, 'entity_type' => 'contact', 'contact' => ['name' => 'Rahul Sharma', 'email' => 'rahul@example.com']],
                ],
                'customer.updated' => [
                    'label' => 'Customer Profile Updated',
                    'description' => 'Triggers when customer demographic or business info changes',
                    'entity_type' => 'customer',
                    'sample_payload' => ['entity_id' => 1, 'customer' => ['name' => 'Acme Corp', 'tier' => 'Enterprise']],
                ],
                'customer.tagged' => [
                    'label' => 'Customer Tagged',
                    'description' => 'Triggers when a specific tag is attached to a customer profile',
                    'entity_type' => 'customer',
                    'sample_payload' => ['entity_id' => 1, 'tag' => 'VIP', 'customer_id' => 1],
                ],
                'customer.inactive' => [
                    'label' => 'Customer Inactive',
                    'description' => 'Triggers when customer has zero activity over defined threshold (e.g. 60 days)',
                    'entity_type' => 'customer',
                    'sample_payload' => ['entity_id' => 1, 'days_inactive' => 60],
                ],
                'customer.birthday' => [
                    'label' => 'Customer Birthday / Anniversary',
                    'description' => 'Triggers on customer birth date or company incorporation anniversary',
                    'entity_type' => 'customer',
                    'sample_payload' => ['entity_id' => 1, 'milestone_type' => 'birthday'],
                ],
            ],
        ],

        'sales' => [
            'name' => 'Sales & Deals',
            'triggers' => [
                'deal.created' => [
                    'label' => 'New Deal Created',
                    'description' => 'Triggers when a new sales deal is entered into a pipeline',
                    'entity_type' => 'deal',
                    'sample_payload' => ['entity_id' => 1, 'deal' => ['id' => 1, 'title' => 'Cloud Migration', 'deal_value' => 250000, 'stage' => 'proposal']],
                ],
                'deal.stage_changed' => [
                    'label' => 'Deal Stage Changed',
                    'description' => 'Triggers when a deal moves to a new pipeline stage',
                    'entity_type' => 'deal',
                    'sample_payload' => ['entity_id' => 1, 'deal_id' => 1, 'from_stage' => 'discovery', 'to_stage' => 'negotiation'],
                ],
                'deal.won' => [
                    'label' => 'Deal Won',
                    'description' => 'Triggers immediately when deal status is marked Won',
                    'entity_type' => 'deal',
                    'sample_payload' => ['entity_id' => 1, 'deal_id' => 1, 'deal_value' => 500000, 'customer_id' => 2],
                ],
                'deal.lost' => [
                    'label' => 'Deal Lost',
                    'description' => 'Triggers when a deal is closed lost with lost reason',
                    'entity_type' => 'deal',
                    'sample_payload' => ['entity_id' => 1, 'deal_id' => 1, 'lost_reason' => 'Competitor Pricing'],
                ],
                'quotation.approved' => [
                    'label' => 'Quotation Approved',
                    'description' => 'Triggers when client or manager approves a commercial quotation',
                    'entity_type' => 'quotation',
                    'sample_payload' => ['entity_id' => 1, 'quotation_id' => 1, 'total_amount' => 150000],
                ],
                'quotation.expired' => [
                    'label' => 'Quotation Expired',
                    'description' => 'Triggers when quote validity date lapses without acceptance',
                    'entity_type' => 'quotation',
                    'sample_payload' => ['entity_id' => 1, 'quotation_id' => 1, 'expired_at' => '2026-11-23'],
                ],
            ],
        ],

        'finance' => [
            'name' => 'Finance & Invoicing',
            'triggers' => [
                'payment.received' => [
                    'label' => 'Payment Received',
                    'description' => 'Triggers on successful gateway or manual payment confirmation',
                    'entity_type' => 'payment',
                    'sample_payload' => ['entity_id' => 1, 'amount' => 75000, 'currency' => 'INR', 'gateway' => 'razorpay', 'invoice_id' => 10],
                ],
                'payment.failed' => [
                    'label' => 'Payment Failed',
                    'description' => 'Triggers when an attempted customer payment transaction fails',
                    'entity_type' => 'payment',
                    'sample_payload' => ['entity_id' => 1, 'amount' => 75000, 'error_code' => 'BAD_REQUEST_PAYMENT_DECLINED'],
                ],
                'invoice.created' => [
                    'label' => 'Invoice Created',
                    'description' => 'Triggers upon generation and finalization of a tax invoice',
                    'entity_type' => 'invoice',
                    'sample_payload' => ['entity_id' => 1, 'invoice_number' => 'INV-2026-001', 'total_amount' => 88500],
                ],
                'invoice.overdue' => [
                    'label' => 'Invoice Overdue',
                    'description' => 'Triggers when invoice reaches or surpasses due date without full settlement',
                    'entity_type' => 'invoice',
                    'sample_payload' => ['entity_id' => 1, 'invoice_id' => 1, 'days_overdue' => 7, 'due_amount' => 88500],
                ],
                'refund.initiated' => [
                    'label' => 'Refund Initiated',
                    'description' => 'Triggers when a customer refund request is requested',
                    'entity_type' => 'refund',
                    'sample_payload' => ['entity_id' => 1, 'refund_amount' => 12000, 'customer_id' => 4],
                ],
                'refund.completed' => [
                    'label' => 'Refund Completed',
                    'description' => 'Triggers when refund disbursement settles and credit note issues',
                    'entity_type' => 'refund',
                    'sample_payload' => ['entity_id' => 1, 'refund_amount' => 12000, 'credit_note_id' => 3],
                ],
            ],
        ],

        'communication' => [
            'name' => 'Communication & Channels',
            'triggers' => [
                'whatsapp.message_received' => [
                    'label' => 'WhatsApp Message Received',
                    'description' => 'Triggers on inbound WhatsApp message from client',
                    'entity_type' => 'whatsapp_message',
                    'sample_payload' => ['entity_id' => 1, 'phone' => '+919876543210', 'message_body' => 'Need pricing info'],
                ],
                'email.received' => [
                    'label' => 'Email Received',
                    'description' => 'Triggers when an inbound email matches customer or deal thread',
                    'entity_type' => 'email',
                    'sample_payload' => ['entity_id' => 1, 'from' => 'client@enterprise.com', 'subject' => 'Project Kickoff'],
                ],
                'communication.customer_replied' => [
                    'label' => 'Customer Replied',
                    'description' => 'Triggers across any channel when customer responds to an agent or sequence',
                    'entity_type' => 'conversation',
                    'sample_payload' => ['entity_id' => 1, 'channel' => 'whatsapp', 'response_text' => 'Yes, proceed'],
                ],
                'telephony.missed_call' => [
                    'label' => 'Missed Call',
                    'description' => 'Triggers when an inbound call ends unhandled or after-hours',
                    'entity_type' => 'voice_call',
                    'sample_payload' => ['entity_id' => 1, 'caller_number' => '+919876543210', 'duration' => 0],
                ],
                'conversation.created' => [
                    'label' => 'Support Conversation Created',
                    'description' => 'Triggers when a new thread starts across WhatsApp, WebChat, or SMS',
                    'entity_type' => 'conversation',
                    'sample_payload' => ['entity_id' => 1, 'channel' => 'webchat', 'customer_id' => 8],
                ],
            ],
        ],

        'support' => [
            'name' => 'Support & Help Desk',
            'triggers' => [
                'ticket.created' => [
                    'label' => 'Ticket Created',
                    'description' => 'Triggers when a new helpdesk ticket is raised',
                    'entity_type' => 'ticket',
                    'sample_payload' => ['entity_id' => 1, 'ticket' => ['id' => 1, 'subject' => 'Payment Gateway Error', 'priority' => 'high']],
                ],
                'ticket.assigned' => [
                    'label' => 'Ticket Assigned',
                    'description' => 'Triggers when support ticket is routed to an agent or team',
                    'entity_type' => 'ticket',
                    'sample_payload' => ['entity_id' => 1, 'ticket_id' => 1, 'assigned_to_user_id' => 4],
                ],
                'ticket.overdue' => [
                    'label' => 'Ticket SLA Overdue',
                    'description' => 'Triggers when response or resolution target SLA is breached',
                    'entity_type' => 'ticket',
                    'sample_payload' => ['entity_id' => 1, 'ticket_id' => 1, 'sla_type' => 'resolution', 'breached_minutes' => 30],
                ],
                'ticket.escalated' => [
                    'label' => 'Ticket Escalated',
                    'description' => 'Triggers when ticket priority or escalation level is bumped to supervisor',
                    'entity_type' => 'ticket',
                    'sample_payload' => ['entity_id' => 1, 'ticket_id' => 1, 'escalation_level' => 2],
                ],
                'ticket.resolved' => [
                    'label' => 'Ticket Resolved',
                    'description' => 'Triggers when ticket status transitions to Resolved/Closed',
                    'entity_type' => 'ticket',
                    'sample_payload' => ['entity_id' => 1, 'ticket_id' => 1, 'resolution_code' => 'solved'],
                ],
            ],
        ],

        'projects' => [
            'name' => 'Projects & Delivery',
            'triggers' => [
                'project.created' => [
                    'label' => 'Project Created',
                    'description' => 'Triggers when a new delivery project is set up or converted from deal',
                    'entity_type' => 'project',
                    'sample_payload' => ['entity_id' => 1, 'project' => ['id' => 1, 'name' => 'ERP Rollout', 'budget' => 750000]],
                ],
                'project.milestone_completed' => [
                    'label' => 'Milestone Completed',
                    'description' => 'Triggers when a critical delivery milestone is marked completed',
                    'entity_type' => 'milestone',
                    'sample_payload' => ['entity_id' => 1, 'project_id' => 1, 'milestone_title' => 'Phase 1 Architecture'],
                ],
                'task.assigned' => [
                    'label' => 'Task Assigned',
                    'description' => 'Triggers when an employee is designated task owner',
                    'entity_type' => 'task',
                    'sample_payload' => ['entity_id' => 1, 'task_id' => 1, 'assigned_to' => 3],
                ],
                'task.overdue' => [
                    'label' => 'Task Overdue',
                    'description' => 'Triggers when a task deadline passes without completion',
                    'entity_type' => 'task',
                    'sample_payload' => ['entity_id' => 1, 'task_id' => 1, 'due_date' => '2026-11-22'],
                ],
                'project.completed' => [
                    'label' => 'Project Completed',
                    'description' => 'Triggers when final project deliverable is accepted',
                    'entity_type' => 'project',
                    'sample_payload' => ['entity_id' => 1, 'project_id' => 1, 'status' => 'completed'],
                ],
            ],
        ],

        'hr' => [
            'name' => 'HR, Shifts & Leaves',
            'triggers' => [
                'attendance.marked' => [
                    'label' => 'Attendance Marked',
                    'description' => 'Triggers on mobile or biometric clock-in timestamp',
                    'entity_type' => 'attendance',
                    'sample_payload' => ['entity_id' => 1, 'employee_id' => 5, 'clock_in_time' => '09:05:00'],
                ],
                'attendance.irregularity' => [
                    'label' => 'Attendance Irregularity',
                    'description' => 'Triggers on late arrival, geofence mismatch, or missing punch',
                    'entity_type' => 'attendance',
                    'sample_payload' => ['entity_id' => 1, 'employee_id' => 5, 'type' => 'late_arrival', 'minutes_late' => 45],
                ],
                'leave.requested' => [
                    'label' => 'Leave Requested',
                    'description' => 'Triggers when employee files a paid or unpaid leave application',
                    'entity_type' => 'leave',
                    'sample_payload' => ['entity_id' => 1, 'employee_id' => 5, 'leave_type' => 'Casual Leave', 'days' => 2],
                ],
                'leave.approved' => [
                    'label' => 'Leave Approved',
                    'description' => 'Triggers when reporting manager signs off on leave request',
                    'entity_type' => 'leave',
                    'sample_payload' => ['entity_id' => 1, 'employee_id' => 5, 'approved_by' => 2],
                ],
                'document.expiry_approaching' => [
                    'label' => 'Document Expiry Approaching',
                    'description' => 'Triggers 30 days before employee passport, visa, or contract expiry',
                    'entity_type' => 'employee_document',
                    'sample_payload' => ['entity_id' => 1, 'employee_id' => 5, 'document_type' => 'Passport', 'expires_at' => '2026-12-23'],
                ],
            ],
        ],

        'appointments' => [
            'name' => 'Appointments & Booking',
            'triggers' => [
                'appointment.booked' => [
                    'label' => 'Appointment Booked',
                    'description' => 'Triggers when a customer books a scheduled meeting or service slot',
                    'entity_type' => 'appointment',
                    'sample_payload' => ['entity_id' => 1, 'customer_id' => 3, 'service' => 'Product Demo', 'start_time' => '2026-11-25 14:00:00'],
                ],
                'appointment.rescheduled' => [
                    'label' => 'Appointment Rescheduled',
                    'description' => 'Triggers when meeting date/time is adjusted',
                    'entity_type' => 'appointment',
                    'sample_payload' => ['entity_id' => 1, 'appointment_id' => 1, 'new_start_time' => '2026-11-26 15:00:00'],
                ],
                'appointment.cancelled' => [
                    'label' => 'Appointment Cancelled',
                    'description' => 'Triggers when customer or staff cancels appointment',
                    'entity_type' => 'appointment',
                    'sample_payload' => ['entity_id' => 1, 'appointment_id' => 1, 'cancellation_reason' => 'Client emergency'],
                ],
                'appointment.reminder_due' => [
                    'label' => 'Appointment Reminder Due',
                    'description' => 'Triggers 24 hours / 1 hour before scheduled appointment start',
                    'entity_type' => 'appointment',
                    'sample_payload' => ['entity_id' => 1, 'appointment_id' => 1, 'hours_remaining' => 24],
                ],
            ],
        ],
    ];

    /**
     * Get flat list of all triggers.
     */
    public static function all(): array
    {
        $all = [];
        foreach (self::TRIGGERS as $category => $data) {
            foreach ($data['triggers'] as $key => $trigger) {
                $all[$key] = array_merge($trigger, ['category' => $category]);
            }
        }
        return $all;
    }

    /**
     * Check if a trigger type is registered.
     */
    public static function exists(string $triggerType): bool
    {
        return array_key_exists($triggerType, self::all());
    }

    /**
     * Find metadata for a trigger.
     */
    public static function get(string $triggerType): ?array
    {
        return self::all()[$triggerType] ?? null;
    }
}
