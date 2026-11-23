<?php

namespace App\Domains\PlatformAdmin\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\CRM\Models\Pipeline;
use App\Domains\CRM\Models\PipelineStage;
use App\Domains\PlatformAdmin\Models\IndustryTemplate;
use App\Domains\PlatformAdmin\Models\TenantAppliedTemplate;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IndustryTemplateService
{
    /**
     * Get 12 Standard Industry Starter Templates Definition
     */
    public static function getStandardTemplatesDefinitions(): array
    {
        return [
            [
                'code' => 'real_estate',
                'name' => 'Real Estate & Property Advisory',
                'category' => 'Property & Construction',
                'icon' => 'Home',
                'description' => 'Tailored for real estate brokers, developers, and property consultants with site visit tracking, unit inventory, and buyer qualification stages.',
                'module_presets' => ['crm_core', 'advanced_sales', 'omnichannel', 'whatsapp', 'field_sales', 'finance', 'documents'],
                'pipeline_stages' => [
                    ['name' => 'Inquiry Received', 'probability' => 10, 'order' => 1],
                    ['name' => 'Budget & Requirement Match', 'probability' => 25, 'order' => 2],
                    ['name' => 'Site Visit Scheduled', 'probability' => 40, 'order' => 3],
                    ['name' => 'Site Visit Completed', 'probability' => 60, 'order' => 4],
                    ['name' => 'Booking Proposal & Token', 'probability' => 80, 'order' => 5],
                    ['name' => 'Sale Deed / Agreement Registered', 'probability' => 100, 'order' => 6],
                ],
                'custom_fields' => [
                    ['entity' => 'deal', 'name' => 'property_type', 'label' => 'Property Type', 'type' => 'select', 'options' => ['Residential Apartment', 'Commercial Office', 'Plot / Land', 'Villa', 'Penthouse']],
                    ['entity' => 'deal', 'name' => 'carpet_area_sqft', 'label' => 'Carpet Area (sq.ft)', 'type' => 'number'],
                    ['entity' => 'lead', 'name' => 'preferred_location', 'label' => 'Preferred Locality', 'type' => 'string'],
                    ['entity' => 'lead', 'name' => 'budget_max_lakhs', 'label' => 'Max Budget (Lakhs INR)', 'type' => 'number'],
                ],
                'dashboard_widgets' => ['kpi_actual_sales', 'kpi_site_visits', 'chart_pipeline', 'table_recent_leads'],
                'automation_recipes' => [
                    ['name' => 'Auto-Schedule Site Visit Follow-up', 'trigger' => 'deal.stage_changed', 'action' => 'create_field_sales_visit'],
                    ['name' => 'Send WhatsApp Brochure on Lead Capture', 'trigger' => 'lead.created', 'action' => 'send_whatsapp_brochure'],
                ],
                'report_definitions' => ['site_visit_conversion_rate', 'broker_commission_summary', 'inventory_absorption_rate'],
                'notification_templates' => ['site_visit_reminder_sms', 'token_payment_receipt_whatsapp'],
            ],
            [
                'code' => 'education',
                'name' => 'Higher Education & University Admissions',
                'category' => 'Education & Training',
                'icon' => 'GraduationCap',
                'description' => 'Comprehensive applicant funnel for universities and colleges with counseling workflows, entrance scores, and fee installments.',
                'module_presets' => ['crm_core', 'marketing_automation', 'omnichannel', 'telephony', 'finance', 'documents'],
                'pipeline_stages' => [
                    ['name' => 'Prospect Inquired', 'probability' => 15, 'order' => 1],
                    ['name' => 'Eligibility Verified', 'probability' => 35, 'order' => 2],
                    ['name' => 'Campus Visit / Counseling', 'probability' => 55, 'order' => 3],
                    ['name' => 'Application Submitted', 'probability' => 75, 'order' => 4],
                    ['name' => 'Seat Fee Paid', 'probability' => 100, 'order' => 5],
                ],
                'custom_fields' => [
                    ['entity' => 'lead', 'name' => 'target_course', 'label' => 'Target Course / Degree', 'type' => 'string'],
                    ['entity' => 'lead', 'name' => 'entrance_score', 'label' => 'Entrance Exam Percentile', 'type' => 'number'],
                    ['entity' => 'contact', 'name' => 'guardian_phone', 'label' => 'Parent / Guardian Contact', 'type' => 'string'],
                ],
                'dashboard_widgets' => ['kpi_admissions_count', 'kpi_fee_collections', 'chart_counselor_performance'],
                'automation_recipes' => [
                    ['name' => 'Entrance Exam Result WhatsApp Broadcast', 'trigger' => 'lead.score_updated', 'action' => 'send_whatsapp_result'],
                ],
                'report_definitions' => ['counselor_admissions_ratio', 'course_wise_lead_distribution'],
                'notification_templates' => ['admission_interview_schedule', 'seat_allotment_letter'],
            ],
            [
                'code' => 'healthcare',
                'name' => 'Healthcare, Clinics & Hospitals',
                'category' => 'Medical & Wellness',
                'icon' => 'Activity',
                'description' => 'Patient consultation scheduling, doctor availability calendars, treatment packages, and post-op follow-up workflows.',
                'module_presets' => ['crm_core', 'customer_portal', 'support', 'whatsapp', 'omnichannel'],
                'pipeline_stages' => [
                    ['name' => 'Patient Inquiry', 'probability' => 20, 'order' => 1],
                    ['name' => 'Doctor Consult Scheduled', 'probability' => 50, 'order' => 2],
                    ['name' => 'Investigation & Diagnosis', 'probability' => 70, 'order' => 3],
                    ['name' => 'Treatment Package Accepted', 'probability' => 100, 'order' => 4],
                ],
                'custom_fields' => [
                    ['entity' => 'lead', 'name' => 'specialty_needed', 'label' => 'Medical Specialty', 'type' => 'select', 'options' => ['Cardiology', 'Orthopedics', 'Dermatology', 'Pediatrics', 'Dental', 'General Surgery']],
                    ['entity' => 'contact', 'name' => 'blood_group', 'label' => 'Blood Group', 'type' => 'string'],
                ],
                'dashboard_widgets' => ['kpi_daily_appointments', 'kpi_package_revenue', 'table_upcoming_surgeries'],
                'automation_recipes' => [
                    ['name' => 'Appointment Reminder 24h Prior', 'trigger' => 'appointment.approaching', 'action' => 'send_whatsapp_reminder'],
                ],
                'report_definitions' => ['doctor_utilization_rate', 'specialty_inquiry_trends'],
                'notification_templates' => ['doctor_prescription_link', 'consultation_zoom_link'],
            ],
            [
                'code' => 'it_agencies',
                'name' => 'IT Services & Software Development Agencies',
                'category' => 'Technology',
                'icon' => 'Code',
                'description' => 'Agile sprint delivery, tech-stack scoping, RFP quotation proposals, milestone escrow approvals, and SLA bug tracking.',
                'module_presets' => ['crm_core', 'projects', 'support', 'gst_compliance', 'workflow_builder', 'custom_portal'],
                'pipeline_stages' => [
                    ['name' => 'RFP / Discovery Inbound', 'probability' => 10, 'order' => 1],
                    ['name' => 'Technical Scoping & Architecture', 'probability' => 30, 'order' => 2],
                    ['name' => 'Commercial Proposal Sent', 'probability' => 60, 'order' => 3],
                    ['name' => 'Contract & MSA Signed', 'probability' => 85, 'order' => 4],
                    ['name' => 'Sprint Kickoff & Advance Received', 'probability' => 100, 'order' => 5],
                ],
                'custom_fields' => [
                    ['entity' => 'deal', 'name' => 'tech_stack', 'label' => 'Primary Tech Stack', 'type' => 'select', 'options' => ['Full-Stack Laravel/React', 'Mobile Flutter/React Native', 'Cloud & DevOps AWS/GCP', 'AI/ML Python']],
                    ['entity' => 'deal', 'name' => 'billing_model', 'label' => 'Contract Model', 'type' => 'select', 'options' => ['Fixed Price', 'Time & Materials', 'Dedicated Team Retainer']],
                ],
                'dashboard_widgets' => ['kpi_project_profitability', 'kpi_unbilled_hours', 'chart_pipeline_forecast'],
                'automation_recipes' => [
                    ['name' => 'Create Jira / Project on Deal Won', 'trigger' => 'deal.won', 'action' => 'initialize_project_board'],
                ],
                'report_definitions' => ['developer_utilization_margin', 'sprint_completion_burnup'],
                'notification_templates' => ['sprint_demo_invite', 'milestone_acceptance_portal_link'],
            ],
            [
                'code' => 'digital_marketing',
                'name' => 'Digital Marketing & Performance Media Agencies',
                'category' => 'Marketing & Advertising',
                'icon' => 'Share2',
                'description' => 'Client campaign performance monitoring, ad spend ROAS attribution, monthly retainers, and content creative approval queues.',
                'module_presets' => ['crm_core', 'marketing_automation', 'projects', 'advanced_analytics', 'finance'],
                'pipeline_stages' => [
                    ['name' => 'Audit Pitch Request', 'probability' => 15, 'order' => 1],
                    ['name' => 'Account Growth Audit Presented', 'probability' => 40, 'order' => 2],
                    ['name' => 'Retainer Contract Dispatched', 'probability' => 70, 'order' => 3],
                    ['name' => 'Client Onboarded & Pixel Access', 'probability' => 100, 'order' => 4],
                ],
                'custom_fields' => [
                    ['entity' => 'deal', 'name' => 'monthly_ad_spend_budget', 'label' => 'Planned Client Ad Spend (INR)', 'type' => 'number'],
                    ['entity' => 'deal', 'name' => 'primary_channel', 'label' => 'Target Growth Channel', 'type' => 'select', 'options' => ['Meta Ads', 'Google Ads Performance Max', 'SEO Organic', 'LinkedIn B2B']],
                ],
                'dashboard_widgets' => ['kpi_client_roas', 'kpi_monthly_recurring_retainer', 'chart_attribution'],
                'automation_recipes' => [
                    ['name' => 'Generate Monthly ROAS Summary Report', 'trigger' => 'schedule.monthly', 'action' => 'generate_client_bi_pdf'],
                ],
                'report_definitions' => ['channel_wise_cpa_benchmark', 'retainer_margin_contribution'],
                'notification_templates' => ['monthly_growth_report_email', 'creative_approval_request'],
            ],
            [
                'code' => 'automobile',
                'name' => 'Automobile Dealerships & Service Centers',
                'category' => 'Automotive & Logistics',
                'icon' => 'Car',
                'description' => 'Showroom test drive bookings, vehicle inventory by chassis/VIN, trade-in valuation, and periodic maintenance reminders.',
                'module_presets' => ['crm_core', 'inventory_procurement', 'whatsapp', 'field_sales', 'gst_compliance'],
                'pipeline_stages' => [
                    ['name' => 'Walk-in / Online Lead', 'probability' => 20, 'order' => 1],
                    ['name' => 'Test Drive Completed', 'probability' => 45, 'order' => 2],
                    ['name' => 'Finance / Loan Pre-Approved', 'probability' => 70, 'order' => 3],
                    ['name' => 'Booking Down Payment Received', 'probability' => 90, 'order' => 4],
                    ['name' => 'Vehicle Delivered & RTO Registered', 'probability' => 100, 'order' => 5],
                ],
                'custom_fields' => [
                    ['entity' => 'deal', 'name' => 'vehicle_model', 'label' => 'Vehicle Model & Trim', 'type' => 'string'],
                    ['entity' => 'deal', 'name' => 'fuel_type', 'label' => 'Fuel Type', 'type' => 'select', 'options' => ['EV / Electric', 'Petrol', 'Diesel', 'CNG Hybrid']],
                    ['entity' => 'lead', 'name' => 'has_exchange_car', 'label' => 'Trade-in Exchange Vehicle?', 'type' => 'boolean'],
                ],
                'dashboard_widgets' => ['kpi_vehicles_delivered', 'kpi_test_drives_today', 'chart_model_demand'],
                'automation_recipes' => [
                    ['name' => 'Follow up on Test Drive Experience', 'trigger' => 'field_visit.completed', 'action' => 'send_feedback_whatsapp'],
                ],
                'report_definitions' => ['test_drive_to_booking_ratio', 'insurance_payout_reconciliation'],
                'notification_templates' => ['test_drive_confirmation_sms', 'chassis_allotment_notice'],
            ],
            [
                'code' => 'insurance',
                'name' => 'Insurance Brokers & Corporate Advisors',
                'category' => 'Financial Services',
                'icon' => 'ShieldCheck',
                'description' => 'Policy lifecycle tracking (Life, Health, Motor, Marine), annual premium renewals, claim assistance, and commission clawback audits.',
                'module_presets' => ['crm_core', 'finance', 'advanced_sales', 'telephony', 'whatsapp'],
                'pipeline_stages' => [
                    ['name' => 'Prospect Coverage Request', 'probability' => 15, 'order' => 1],
                    ['name' => 'Comparative Quote Comparison', 'probability' => 40, 'order' => 2],
                    ['name' => 'Underwriting & Medicals', 'probability' => 70, 'order' => 3],
                    ['name' => 'Policy Bound & First Premium Logged', 'probability' => 100, 'order' => 4],
                ],
                'custom_fields' => [
                    ['entity' => 'deal', 'name' => 'policy_type', 'label' => 'Policy Category', 'type' => 'select', 'options' => ['Health Super Top-up', 'Term Life', 'Commercial Fleet', 'Directors & Officers (D&O)']],
                    ['entity' => 'deal', 'name' => 'sum_insured', 'label' => 'Sum Insured (INR)', 'type' => 'number'],
                    ['entity' => 'deal', 'name' => 'policy_renewal_date', 'label' => 'Annual Renewal Date', 'type' => 'date'],
                ],
                'dashboard_widgets' => ['kpi_gross_written_premium', 'kpi_renewals_due_30d', 'chart_agent_commissions'],
                'automation_recipes' => [
                    ['name' => 'Renewal Countdown 30 Days Prior', 'trigger' => 'date.renewal_30d', 'action' => 'dispatch_renewal_quote'],
                ],
                'report_definitions' => ['policy_retention_ratio', 'claim_settlement_turnaround_time'],
                'notification_templates' => ['policy_renewal_link_whatsapp', 'claim_status_update_sms'],
            ],
            [
                'code' => 'travel_tourism',
                'name' => 'Travel, Tourism & Destination Managers',
                'category' => 'Hospitality & Leisure',
                'icon' => 'Plane',
                'description' => 'Custom holiday package itineraries, flight/hotel booking vouchers, visa document coordination, and group tour bookings.',
                'module_presets' => ['crm_core', 'quotations', 'whatsapp', 'customer_portal', 'finance'],
                'pipeline_stages' => [
                    ['name' => 'Destination Enquiry', 'probability' => 20, 'order' => 1],
                    ['name' => 'Custom Itinerary Proposed', 'probability' => 50, 'order' => 2],
                    ['name' => 'Flights & Hotels Confirmed', 'probability' => 80, 'order' => 3],
                    ['name' => 'Vouchers Issued & Tour Departed', 'probability' => 100, 'order' => 4],
                ],
                'custom_fields' => [
                    ['entity' => 'deal', 'name' => 'destination_country', 'label' => 'Destination Country', 'type' => 'string'],
                    ['entity' => 'deal', 'name' => 'travel_passengers_count', 'label' => 'Number of Travelers', 'type' => 'number'],
                    ['entity' => 'deal', 'name' => 'departure_date', 'label' => 'Departure Date', 'type' => 'date'],
                ],
                'dashboard_widgets' => ['kpi_itineraries_sent', 'kpi_total_tour_revenue', 'chart_top_destinations'],
                'automation_recipes' => [
                    ['name' => 'Send E-Tickets on Balance Clearance', 'trigger' => 'invoice.paid', 'action' => 'send_travel_vouchers'],
                ],
                'report_definitions' => ['destination_margin_analysis', 'visa_processing_sla'],
                'notification_templates' => ['itinerary_brochure_whatsapp', 'flight_schedule_alert'],
            ],
            [
                'code' => 'manufacturing',
                'name' => 'Manufacturing, B2B Industrial & OEMs',
                'category' => 'Industrial & Production',
                'icon' => 'Factory',
                'description' => 'Raw material batch production, multi-location warehouse stock, MOQ quotations, vendor purchase orders, and GST E-Way bills.',
                'module_presets' => ['crm_core', 'inventory_procurement', 'gst_compliance', 'finance', 'multi_branch'],
                'pipeline_stages' => [
                    ['name' => 'Technical Drawings / RFQ Received', 'probability' => 10, 'order' => 1],
                    ['name' => 'BOM Costing & Feasibility', 'probability' => 30, 'order' => 2],
                    ['name' => 'Sample Batch Approved by Client', 'probability' => 60, 'order' => 3],
                    ['name' => 'Purchase Order (PO) Accepted', 'probability' => 85, 'order' => 4],
                    ['name' => 'Batch Dispatched with E-Way Bill', 'probability' => 100, 'order' => 5],
                ],
                'custom_fields' => [
                    ['entity' => 'deal', 'name' => 'minimum_order_qty', 'label' => 'Batch Quantity (MOQ)', 'type' => 'number'],
                    ['entity' => 'deal', 'name' => 'delivery_lead_time_days', 'label' => 'Production Lead Time (Days)', 'type' => 'number'],
                ],
                'dashboard_widgets' => ['kpi_warehouse_valuation', 'kpi_production_orders', 'chart_inventory_aging'],
                'automation_recipes' => [
                    ['name' => 'Trigger Procurement PR on Low Raw Material', 'trigger' => 'inventory.reorder_level', 'action' => 'create_purchase_requisition'],
                ],
                'report_definitions' => ['bom_variance_report', 'scrap_damage_quarantine_metrics'],
                'notification_templates' => ['grn_inspection_report', 'dispatch_eway_bill_notification'],
            ],
            [
                'code' => 'retail',
                'name' => 'Omnichannel Retail & D2C Brands',
                'category' => 'Consumer Goods',
                'icon' => 'ShoppingBag',
                'description' => 'Multi-channel POS/online sales integration, barcode scanning, loyalty point tiers, return (RMA) replacements, and catalog promos.',
                'module_presets' => ['crm_core', 'inventory_procurement', 'marketing_automation', 'whatsapp', 'gst_compliance'],
                'pipeline_stages' => [
                    ['name' => 'Cart Initiated / In-Store Lead', 'probability' => 25, 'order' => 1],
                    ['name' => 'Billing & Payment Processed', 'probability' => 75, 'order' => 2],
                    ['name' => 'Delivered & Loyalty Enrolled', 'probability' => 100, 'order' => 3],
                ],
                'custom_fields' => [
                    ['entity' => 'customer', 'name' => 'loyalty_tier', 'label' => 'Loyalty Club Tier', 'type' => 'select', 'options' => ['Silver', 'Gold', 'Platinum VIP']],
                    ['entity' => 'customer', 'name' => 'lifetime_orders_count', 'label' => 'Total Orders Count', 'type' => 'number'],
                ],
                'dashboard_widgets' => ['kpi_daily_retail_sales', 'kpi_average_order_value', 'chart_category_sales'],
                'automation_recipes' => [
                    ['name' => 'Send WhatsApp Digital Receipt & Feedback', 'trigger' => 'invoice.paid', 'action' => 'send_digital_invoice_whatsapp'],
                ],
                'report_definitions' => ['sku_velocity_turnover', 'loyalty_repeat_purchase_rate'],
                'notification_templates' => ['festive_offer_broadcast', 'order_dispatch_tracking'],
            ],
            [
                'code' => 'consultancy',
                'name' => 'Legal, Tax & Management Consultancies',
                'category' => 'Professional Services',
                'icon' => 'Briefcase',
                'description' => 'Hourly billable logging, confidential retainer agreements, regulatory compliance checklists, and secure client document vaults.',
                'module_presets' => ['crm_core', 'projects', 'finance', 'customer_portal', 'support'],
                'pipeline_stages' => [
                    ['name' => 'Advisory Request Received', 'probability' => 20, 'order' => 1],
                    ['name' => 'Conflict Check & Scope Review', 'probability' => 45, 'order' => 2],
                    ['name' => 'Engagement Letter Dispatched', 'probability' => 75, 'order' => 3],
                    ['name' => 'Retainer Engaged & Counsel Onboarded', 'probability' => 100, 'order' => 4],
                ],
                'custom_fields' => [
                    ['entity' => 'deal', 'name' => 'practice_area', 'label' => 'Practice Area', 'type' => 'select', 'options' => ['Corporate M&A', 'GST & Direct Tax', 'Litigation / Arbitration', 'Intellectual Property (IP)']],
                    ['entity' => 'deal', 'name' => 'lead_partner', 'label' => 'Managing Partner', 'type' => 'string'],
                ],
                'dashboard_widgets' => ['kpi_billable_hours', 'kpi_retainer_cash_collections', 'chart_practice_area_revenue'],
                'automation_recipes' => [
                    ['name' => 'Client Portal Onboarding on Engagement Signed', 'trigger' => 'deal.won', 'action' => 'send_portal_invitation'],
                ],
                'report_definitions' => ['partner_realization_rate', 'matter_profitability_report'],
                'notification_templates' => ['engagement_letter_esign', 'compliance_deadline_reminder'],
            ],
            [
                'code' => 'coaching_centres',
                'name' => 'Coaching Centres & Test Prep Institutes',
                'category' => 'Education & Test Prep',
                'icon' => 'BookOpen',
                'description' => 'Batch management, student attendance tracking, scholarship tests, installment fee schedules, and parent SMS alerts.',
                'module_presets' => ['crm_core', 'marketing_automation', 'whatsapp', 'hrm_payroll', 'finance'],
                'pipeline_stages' => [
                    ['name' => 'Diagnostic Inquiry', 'probability' => 20, 'order' => 1],
                    ['name' => 'Scholarship Test Taken', 'probability' => 50, 'order' => 2],
                    ['name' => 'Batch Allotment & Fee Plan', 'probability' => 80, 'order' => 3],
                    ['name' => 'Enrolled & Student ID Generated', 'probability' => 100, 'order' => 4],
                ],
                'custom_fields' => [
                    ['entity' => 'lead', 'name' => 'target_exam', 'label' => 'Target Examination', 'type' => 'select', 'options' => ['IIT-JEE', 'NEET Medical', 'UPSC Civil Services', 'CAT / MBA', 'CA Foundation']],
                    ['entity' => 'contact', 'name' => 'school_name', 'label' => 'Current School / College', 'type' => 'string'],
                ],
                'dashboard_widgets' => ['kpi_student_enrollments', 'kpi_scholarship_discount_given', 'chart_batch_capacity'],
                'automation_recipes' => [
                    ['name' => 'Send WhatsApp Attendance Notification to Parents', 'trigger' => 'attendance.absent', 'action' => 'send_parent_whatsapp_alert'],
                ],
                'report_definitions' => ['batch_fill_rate', 'scholarship_roi_report'],
                'notification_templates' => ['batch_commencement_notice', 'mock_test_rank_card'],
            ],
        ];
    }

    /**
     * Seed or Refresh 12 Standard Industry Starter Templates
     */
    public function seedStandardTemplates(): void
    {
        $templates = self::getStandardTemplatesDefinitions();

        foreach ($templates as $tpl) {
            IndustryTemplate::updateOrCreate(
                ['code' => $tpl['code']],
                [
                    'name' => $tpl['name'],
                    'category' => $tpl['category'],
                    'icon' => $tpl['icon'],
                    'description' => $tpl['description'],
                    'module_presets' => $tpl['module_presets'],
                    'pipeline_stages' => $tpl['pipeline_stages'],
                    'custom_fields' => $tpl['custom_fields'],
                    'dashboard_widgets' => $tpl['dashboard_widgets'],
                    'automation_recipes' => $tpl['automation_recipes'],
                    'report_definitions' => $tpl['report_definitions'],
                    'notification_templates' => $tpl['notification_templates'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Get All Available Active Industry Templates
     */
    public function getAvailableTemplates(): array
    {
        // Auto-seed if empty
        if (IndustryTemplate::count() < 12) {
            $this->seedStandardTemplates();
        }

        return IndustryTemplate::where('is_active', true)->get()->toArray();
    }

    /**
     * Get a Single Template by Code
     */
    public function getTemplateByCode(string $code): ?IndustryTemplate
    {
        $tpl = IndustryTemplate::where('code', $code)->first();
        if (!$tpl) {
            $this->seedStandardTemplates();
            $tpl = IndustryTemplate::where('code', $code)->first();
        }
        return $tpl;
    }

    /**
     * Apply Industry Template to Tenant Without Breaking Existing Records
     */
    public function applyTemplateToTenant(Tenant $tenant, string $templateCode, array $customizations = []): TenantAppliedTemplate
    {
        $template = $this->getTemplateByCode($templateCode);
        if (!$template) {
            throw new \InvalidArgumentException("Industry template [{$templateCode}] not found.");
        }

        return DB::transaction(function () use ($tenant, $template, $customizations) {
            // 1. Mark any previously applied template as customized/archived
            TenantAppliedTemplate::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->update(['status' => 'customized']);

            // 2. Register applied template record
            $applied = TenantAppliedTemplate::create([
                'tenant_id' => $tenant->id,
                'template_id' => $template->id,
                'template_code' => $template->code,
                'customizations' => $customizations,
                'applied_at' => now(),
                'status' => 'active',
            ]);

            // 3. Update tenant industry code
            $tenant->update([
                'industry_template_code' => $template->code,
            ]);

            // 4. Configure Industry Pipeline if not exists or create industry-specific pipeline
            $pipelineName = "{$template->name} Pipeline";
            $existingPipeline = Pipeline::where('tenant_id', $tenant->id)
                ->where('name', $pipelineName)
                ->first();

            if (!$existingPipeline && !empty($template->pipeline_stages)) {
                $newPipeline = Pipeline::create([
                    'tenant_id' => $tenant->id,
                    'name' => $pipelineName,
                    'is_default' => false,
                    'is_active' => true,
                ]);

                foreach ($template->pipeline_stages as $idx => $stage) {
                    PipelineStage::create([
                        'tenant_id' => $tenant->id,
                        'pipeline_id' => $newPipeline->id,
                        'name' => $stage['name'],
                        'code' => Str::slug($stage['name'], '_'),
                        'probability' => $stage['probability'] ?? 50,
                        'order_column' => $stage['order'] ?? ($idx + 1),
                    ]);
                }
            }

            return $applied->load('template');
        });
    }

    /**
     * Get Current Active Template for Tenant
     */
    public function getTenantTemplate(Tenant $tenant): ?array
    {
        $applied = TenantAppliedTemplate::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->with('template')
            ->first();

        if ($applied) {
            return [
                'has_template' => true,
                'applied_template' => $applied,
                'template' => $applied->template,
                'template_details' => $applied->template,
            ];
        }

        return [
            'has_template' => false,
            'applied_template' => null,
            'template_details' => null,
        ];
    }
}
