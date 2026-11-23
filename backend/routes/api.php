<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AdCampaignMetricController;
use App\Http\Controllers\Api\V1\AdvancedLeadController;
use App\Http\Controllers\Api\V1\AdvancedPaymentController;
use App\Http\Controllers\Api\V1\AiAssistantController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\ApiKeyController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BookableServiceController;
use App\Http\Controllers\Api\V1\CustomerPortalController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\CrmActivityController;
use App\Http\Controllers\Api\V1\CustomDomainController;
use App\Http\Controllers\Api\V1\CustomFieldController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerReturnController;
use App\Http\Controllers\Api\V1\CustomerSuccessController;
use App\Http\Controllers\Api\V1\DealController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DripSequenceController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FinanceReportController;
use App\Http\Controllers\Api\V1\FormBuilderController;
use App\Http\Controllers\Api\V1\GlobalSearchController;
use App\Http\Controllers\Api\V1\GstComplianceController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\KnowledgeBaseController;
use App\Http\Controllers\Api\V1\LandingPageAnalyticsController;
use App\Http\Controllers\Api\V1\LandingPageController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\LeadFieldMappingController;
use App\Http\Controllers\Api\V1\LeadFormController;
use App\Http\Controllers\Api\V1\LeadIngestionEventController;
use App\Http\Controllers\Api\V1\LeadSourceController;
use App\Http\Controllers\Api\V1\LeaveManagementController;
use App\Http\Controllers\Api\V1\MarketingCampaignController;
use App\Http\Controllers\Api\V1\MarketingConsentController;
use App\Http\Controllers\Api\V1\MarketingSegmentController;
use App\Http\Controllers\Api\V1\MarketingTemplateController;
use App\Http\Controllers\Api\V1\MediaFileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PortalSettingController;
use App\Http\Controllers\Api\V1\PublicBookingController;
use App\Http\Controllers\Api\V1\OmnichannelChannelConfigController;
use App\Http\Controllers\Api\V1\OmnichannelInboxController;
use App\Http\Controllers\Api\V1\OpenApiController;
use App\Http\Controllers\Api\V1\OutboundWebhookController;
use App\Http\Controllers\Api\V1\PayableController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\EmployeeDocumentController;
use App\Http\Controllers\Api\V1\EmployeeProductivityController;
use App\Http\Controllers\Api\V1\HrReminderController;
use App\Http\Controllers\Api\V1\PipelineController;
use App\Http\Controllers\Api\V1\ProcurementController;
use App\Http\Controllers\Api\V1\ProductCatalogController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectMilestoneController;
use App\Http\Controllers\Api\V1\ProjectDocumentController;
use App\Http\Controllers\Api\V1\PublicLandingPageController;
use App\Http\Controllers\Api\V1\PublicLeadCaptureController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\RazorpayBillingWebhookController;
use App\Http\Controllers\Api\V1\ReceivableController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\OwnerDashboardController;
use App\Http\Controllers\Api\V1\CustomReportBuilderController;
use App\Http\Controllers\Api\V1\SalesMarketingFinanceAnalyticsController;
use App\Http\Controllers\Api\V1\AiInsightsAlertsController;
use App\Http\Controllers\Api\V1\FieldSalesController;
use App\Http\Controllers\Api\V1\IndiaFirstController;
use App\Http\Controllers\Api\V1\IndustryTemplateController;
use App\Http\Controllers\Api\V1\SaasOnboardingController;
use App\Http\Controllers\Api\V1\FeatureEntitlementController;
use App\Http\Controllers\Api\V1\PlatformSuperAdminConsoleController;
use App\Http\Controllers\Api\V1\RolePermissionController;
use App\Http\Controllers\Api\V1\SalesCommissionController;
use App\Http\Controllers\Api\V1\SalesTeamController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\SocialChannelController;
use App\Http\Controllers\Api\V1\Platform\PlatformOverviewController;
use App\Http\Controllers\Api\V1\Platform\PlatformTenantController;
use App\Http\Controllers\Api\V1\Platform\PlatformBillingController;
use App\Http\Controllers\Api\V1\Platform\PlatformProviderController;
use App\Http\Controllers\Api\V1\Platform\PlatformSystemController;
use App\Http\Controllers\Api\V1\SubscriptionBillingController;
use App\Http\Controllers\Api\V1\SupportCategoryController;
use App\Http\Controllers\Api\V1\SupportSlaPolicyController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\SystemSettingController;
use App\Http\Controllers\Api\V1\TrackingAdminController;
use App\Http\Controllers\Api\V1\TrackingPublicController;
use App\Http\Controllers\Api\V1\UsageMeteringController;
use App\Http\Controllers\Api\V1\CallIvrQueueController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TelephonyProviderController;
use App\Http\Controllers\Api\V1\VoiceAiAgentController;
use App\Http\Controllers\Api\V1\VoiceCallController;
use App\Http\Controllers\Api\V1\VoiceCallingCampaignController;
use App\Http\Controllers\Api\V1\Webhook\LeadSourceWebhookController;
use App\Http\Controllers\Api\V1\Webhook\RazorpayWebhookController;
use App\Http\Controllers\Api\V1\Webhook\SocialWebhookController;
use App\Http\Controllers\Api\V1\Webhook\TelephonyWebhookController;
use App\Http\Controllers\Api\V1\Webhook\WhatsAppWebhookController;
use App\Http\Controllers\Api\V1\WhatsAppAccountController;
use App\Http\Controllers\Api\V1\WhatsAppAiAgentController;
use App\Http\Controllers\Api\V1\WhatsAppAiKnowledgeController;
use App\Http\Controllers\Api\V1\WhatsAppAiLogsController;
use App\Http\Controllers\Api\V1\WhatsAppAiProviderController;
use App\Http\Controllers\Api\V1\WhatsAppAutomationController;
use App\Http\Controllers\Api\V1\WhatsAppCampaignController;
use App\Http\Controllers\Api\V1\WhatsAppConversationController;
use App\Http\Controllers\Api\V1\WhatsAppMessageController;
use App\Http\Controllers\Api\V1\WhatsAppTemplateController;
use App\Http\Controllers\Api\V1\WhatsAppUsageController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WebChatPublicController;
use App\Http\Controllers\Api\V1\WorkflowController;
use App\Http\Controllers\Api\V1\WorkflowRunController;
use App\Http\Controllers\Api\V1\WorkflowApprovalController;
use App\Http\Controllers\Api\V1\WorkflowLimitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Enterprise AI CRM SaaS
|--------------------------------------------------------------------------
*/

// Public Health Check Diagnostics
Route::get('/health', [HealthController::class, 'check'])->name('api.health');
Route::get('/v1/health', [HealthController::class, 'check'])->name('api.v1.health');

// Route Definition Macro for V1 & Direct Compatibility
$registerCrmRoutes = function () {
    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-2fa', [AuthController::class, 'verify2fa']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/forgot-password', fn() => response()->json([
            'success' => true,
            'message' => 'Password reset instructions have been dispatched to your corporate email.'
        ]));

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/login-history', [WorkspaceController::class, 'loginHistory']);
        });
    });

    // Workspace & Multi-Tenancy Management Routes
    Route::middleware('auth:sanctum')->prefix('workspaces')->group(function () {
        Route::get('/', [WorkspaceController::class, 'index']);
        Route::post('/', [WorkspaceController::class, 'store']);
        Route::get('/current', [WorkspaceController::class, 'current']);
        Route::post('/switch', [WorkspaceController::class, 'switchWorkspace']);
        Route::put('/settings', [WorkspaceController::class, 'updateSettings']);
        Route::get('/members', [WorkspaceController::class, 'members']);
        Route::post('/invitations', [WorkspaceController::class, 'inviteMember']);
        Route::post('/invitations/{token}/accept', [WorkspaceController::class, 'acceptInvitation']);
    });

    // Shared Platform Services: Notifications
    Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::get('/preferences', [NotificationController::class, 'preferences']);
        Route::put('/preferences', [NotificationController::class, 'updatePreferences']);
        Route::post('/dispatch-test', [NotificationController::class, 'dispatchTest']);
    });

    // Shared Platform Services: Media & File Manager
    Route::middleware('auth:sanctum')->prefix('media')->group(function () {
        Route::get('/', [MediaFileController::class, 'index']);
        Route::post('/', [MediaFileController::class, 'store']);
        Route::get('/{uuid}', [MediaFileController::class, 'show']);
        Route::delete('/{uuid}', [MediaFileController::class, 'destroy']);
    });
    // Secure Download with Signed URL or Sanctum Auth
    Route::get('/media/{uuid}/download', [MediaFileController::class, 'download'])->name('api.v1.media.download');

    // Shared Platform Services: Custom Fields & Layouts
    Route::middleware('auth:sanctum')->prefix('custom-fields')->group(function () {
        Route::get('/', [CustomFieldController::class, 'index']);
        Route::post('/', [CustomFieldController::class, 'store']);
        Route::put('/{id}', [CustomFieldController::class, 'update']);
        Route::delete('/{id}', [CustomFieldController::class, 'destroy']);
        Route::get('/values/{entity_type}/{entity_id}', [CustomFieldController::class, 'getEntityValues']);
        Route::post('/values/{entity_type}/{entity_id}', [CustomFieldController::class, 'saveEntityValues']);
    });

    // Shared Platform Services: Global Unified Search
    Route::middleware('auth:sanctum')->get('/search', [GlobalSearchController::class, 'search']);

    // Shared Platform Services: Immutable Audit Trail
    Route::middleware('auth:sanctum')->prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/{id}', [AuditLogController::class, 'show']);
    });

    // Public Lead Capture & Hosted Forms (Public / Embedded)
    Route::prefix('public/forms')->group(function () {
        Route::get('/{uuid}', [PublicLeadCaptureController::class, 'show']);
        Route::post('/{uuid}/submit', [PublicLeadCaptureController::class, 'submit']);
        Route::get('/{uuid}/embed.js', [PublicLeadCaptureController::class, 'embedScript']);
        Route::post('/{slug}/submit-custom', [PublicLandingPageController::class, 'submitForm']);
    });

    // Public Landing Pages
    Route::prefix('public/landing-pages')->group(function () {
        Route::get('/{slug}', [PublicLandingPageController::class, 'getPage']);
        Route::post('/{slug}/cta-click', [PublicLandingPageController::class, 'trackCtaClick']);
    });

    // Public / Client Portal Milestone Approvals
    Route::prefix('portal/milestones')->group(function () {
        Route::get('/{token}', [ProjectMilestoneController::class, 'getMilestoneByToken']);
        Route::post('/{token}/approve', [ProjectMilestoneController::class, 'clientApprove']);
        Route::post('/{token}/revision', [ProjectMilestoneController::class, 'clientRevision']);
    });

    // Customer Portal Authentication (Public)
    Route::prefix('portal/auth')->group(function () {
        Route::post('/register', [CustomerPortalController::class, 'register']);
        Route::post('/login', [CustomerPortalController::class, 'login']);
    });

    // Public Online Appointment Booking Page
    Route::prefix('public/booking')->group(function () {
        Route::get('/{slug}', [PublicBookingController::class, 'getBookingConfig']);
        Route::get('/{slug}/slots', [PublicBookingController::class, 'getSlots']);
        Route::post('/{slug}/book', [PublicBookingController::class, 'book']);
    });

    // Customer Self-Service Portal (Scoped for Customer Accounts)
    Route::middleware('auth:sanctum')->prefix('portal')->group(function () {
        Route::post('/auth/logout', [CustomerPortalController::class, 'logout']);
        Route::get('/dashboard', [CustomerPortalController::class, 'dashboard']);
        Route::get('/profile', [CustomerPortalController::class, 'profile']);
        Route::put('/profile', [CustomerPortalController::class, 'updateProfile']);
        Route::post('/change-password', [CustomerPortalController::class, 'changePassword']);

        // Quotations
        Route::get('/quotations', [CustomerPortalController::class, 'quotations']);
        Route::get('/quotations/{id}', [CustomerPortalController::class, 'showQuotation']);
        Route::post('/quotations/{id}/accept', [CustomerPortalController::class, 'acceptQuotation']);
        Route::post('/quotations/{id}/reject', [CustomerPortalController::class, 'rejectQuotation']);
        Route::post('/quotations/{id}/request-revision', [CustomerPortalController::class, 'requestQuotationChange']);

        // Invoices & Payments
        Route::get('/invoices', [CustomerPortalController::class, 'invoices']);
        Route::get('/invoices/{id}', [CustomerPortalController::class, 'showInvoice']);
        Route::post('/invoices/{id}/pay', [CustomerPortalController::class, 'payInvoice']);

        // Projects & Documents (Strictly Client Visible)
        Route::get('/projects', [CustomerPortalController::class, 'projects']);
        Route::get('/projects/{id}', [CustomerPortalController::class, 'showProject']);
        Route::get('/documents', [CustomerPortalController::class, 'documents']);

        // Support Tickets
        Route::get('/tickets', [CustomerPortalController::class, 'tickets']);
        Route::post('/tickets', [CustomerPortalController::class, 'createTicket']);
        Route::get('/tickets/{id}', [CustomerPortalController::class, 'showTicket']);
        Route::post('/tickets/{id}/reply', [CustomerPortalController::class, 'replyTicket']);
        Route::post('/tickets/{id}/feedback', [CustomerPortalController::class, 'feedbackTicket']);

        // Appointments & Services
        Route::get('/services', [CustomerPortalController::class, 'bookableServices']);
        Route::get('/appointments', [CustomerPortalController::class, 'appointments']);
        Route::post('/appointments', [CustomerPortalController::class, 'bookAppointment']);
        Route::post('/appointments/{id}/reschedule', [CustomerPortalController::class, 'rescheduleAppointment']);
        Route::post('/appointments/{id}/cancel', [CustomerPortalController::class, 'cancelAppointment']);
    });

    // Lead Acquisition Webhooks (Unauthenticated with signature verification)
    Route::prefix('webhooks')->group(function () {
        Route::get('/meta-ads', [LeadSourceWebhookController::class, 'verifyMetaWebhook']);
        Route::post('/meta-ads', [LeadSourceWebhookController::class, 'handleMetaWebhook']);
        Route::post('/google-ads/{sourceId}', [LeadSourceWebhookController::class, 'handleGoogleAdsWebhook']);
        Route::post('/whatsapp/{sourceId}', [LeadSourceWebhookController::class, 'handleWhatsAppWebhook']);
        Route::post('/generic/{sourceId}', [LeadSourceWebhookController::class, 'handleGenericWebhook']);
        Route::post('/razorpay', [RazorpayWebhookController::class, 'handle']);
        Route::get('/whatsapp', [WhatsAppWebhookController::class, 'verify']);
        Route::post('/whatsapp', [WhatsAppWebhookController::class, 'handle']);

        // Meta Messenger & Instagram Direct Message Webhooks
        Route::get('/meta/social', [SocialWebhookController::class, 'verify']);
        Route::post('/meta/social', [SocialWebhookController::class, 'handle']);

        // Telephony Webhooks (Exotel & Twilio)
        Route::match(['get', 'post'], '/telephony/exotel/connect', [TelephonyWebhookController::class, 'exotelConnect']);
        Route::post('/telephony/exotel/status', [TelephonyWebhookController::class, 'exotelStatus']);
        Route::post('/telephony/exotel/record', [TelephonyWebhookController::class, 'exotelStatus']);
        Route::match(['get', 'post'], '/telephony/twilio/voice', [TelephonyWebhookController::class, 'twilioVoice']);
        Route::post('/telephony/twilio/status', [TelephonyWebhookController::class, 'twilioStatus']);
        Route::post('/telephony/twilio/record', [TelephonyWebhookController::class, 'twilioStatus']);
    });

    // Support direct provider URL formats: /telephony/webhooks/...
    Route::prefix('telephony/webhooks')->group(function () {
        Route::match(['get', 'post'], '/exotel/connect', [TelephonyWebhookController::class, 'exotelConnect']);
        Route::post('/exotel/status', [TelephonyWebhookController::class, 'exotelStatus']);
        Route::post('/exotel/record', [TelephonyWebhookController::class, 'exotelStatus']);
        Route::match(['get', 'post'], '/twilio/voice', [TelephonyWebhookController::class, 'twilioVoice']);
        Route::post('/twilio/status', [TelephonyWebhookController::class, 'twilioStatus']);
        Route::post('/twilio/record', [TelephonyWebhookController::class, 'twilioStatus']);
    });

    // Public Website Chat Widget Endpoints
    Route::prefix('public/webchat')->group(function () {
        Route::get('/init', [WebChatPublicController::class, 'init']);
        Route::post('/send', [WebChatPublicController::class, 'send']);
        Route::get('/messages', [WebChatPublicController::class, 'getMessages']);
    });

    // Public Razorpay Commercial Billing Webhooks
    Route::post('/public/webhooks/razorpay/billing', [RazorpayBillingWebhookController::class, 'handle']);

    // Public Meta Messenger & Instagram Direct Message Webhook
    Route::get('/public/webhooks/meta/social', [SocialWebhookController::class, 'verify']);
    Route::post('/public/webhooks/meta/social', [SocialWebhookController::class, 'handle']);

    // Public Telegram Bot Webhook
    Route::post('/webhooks/telegram/{tenant_id}', [SocialWebhookController::class, 'handleTelegram']);
    Route::post('/public/webhooks/telegram/{tenant_id}', [SocialWebhookController::class, 'handleTelegram']);

    // Public Website & Landing Page Tracking SDK
    Route::prefix('tracking')->group(function () {
        Route::post('/events', [TrackingPublicController::class, 'ingestEvent']);
        Route::get('/script/{key}.js', [TrackingPublicController::class, 'serveScript']);
    });

    // Public Lead & Indian B2B Portal Ingestion
    Route::post('/public/leads/capture', [AdvancedLeadController::class, 'capture']);
    Route::post('/public/leads/portal/{portal}', [AdvancedLeadController::class, 'capturePortal']);

    // Public Payment Links & Webhooks
    Route::get('/public/payment-links/{token}', [AdvancedPaymentController::class, 'getPaymentLinkByToken']);
    Route::post('/public/payments/webhook/{provider}', [AdvancedPaymentController::class, 'handleWebhook']);

    // Protected CRM Endpoints
    Route::middleware(['auth:sanctum', 'tenant', 'audit'])->prefix('crm')->group(function () {
        // Lead Acquisition Hub Management
        Route::get('/lead-sources/stats', [LeadSourceController::class, 'stats']);
        Route::post('/lead-sources/{id}/test', [LeadSourceController::class, 'testConnection']);
        Route::post('/lead-sources/{id}/sync-metrics', [LeadSourceController::class, 'syncMetrics']);
        Route::apiResource('lead-sources', LeadSourceController::class);

        Route::apiResource('lead-forms', LeadFormController::class);

        Route::get('/lead-field-mappings', [LeadFieldMappingController::class, 'index']);
        Route::post('/lead-field-mappings', [LeadFieldMappingController::class, 'store']);
        Route::delete('/lead-field-mappings/{id}', [LeadFieldMappingController::class, 'destroy']);

        Route::get('/lead-ingestion-events', [LeadIngestionEventController::class, 'index']);
        Route::get('/lead-ingestion-events/{id}', [LeadIngestionEventController::class, 'show']);
        Route::post('/lead-ingestion-events/{id}/retry', [LeadIngestionEventController::class, 'retry']);

        Route::get('/ad-campaign-metrics', [AdCampaignMetricController::class, 'index']);
        // Core CRM Resources: Leads Advanced Operations
        Route::post('/leads/capture', [AdvancedLeadController::class, 'capture']);
        Route::post('/leads/portal/{portal}', [AdvancedLeadController::class, 'capturePortal']);
        Route::get('/leads/portals', [AdvancedLeadController::class, 'listPortals']);
        Route::post('/leads/portals', [AdvancedLeadController::class, 'savePortal']);

        Route::get('/leads/routing/rules', [AdvancedLeadController::class, 'listRoutingRules']);
        Route::post('/leads/routing/rules', [AdvancedLeadController::class, 'saveRoutingRule']);
        Route::delete('/leads/routing/rules/{id}', [AdvancedLeadController::class, 'deleteRoutingRule']);
        Route::post('/leads/{lead}/auto-route', [AdvancedLeadController::class, 'autoRouteLead']);

        Route::get('/leads/sla/policies', [AdvancedLeadController::class, 'listSlaPolicies']);
        Route::post('/leads/sla/policies', [AdvancedLeadController::class, 'saveSlaPolicy']);
        Route::post('/leads/sla/evaluate', [AdvancedLeadController::class, 'evaluateSlaEscalations']);
        Route::get('/leads/sla/escalations', [AdvancedLeadController::class, 'listEscalationLogs']);
        Route::post('/leads/{lead}/record-response', [AdvancedLeadController::class, 'recordResponse']);

        Route::get('/leads/followups/overdue', [AdvancedLeadController::class, 'listOverdueFollowups']);
        Route::get('/leads/{lead}/followups', [AdvancedLeadController::class, 'listFollowups']);
        Route::post('/leads/{lead}/followups', [AdvancedLeadController::class, 'scheduleFollowup']);
        Route::post('/leads/followups/{id}/complete', [AdvancedLeadController::class, 'completeFollowup']);
        Route::post('/leads/followups/{id}/reschedule', [AdvancedLeadController::class, 'rescheduleFollowup']);

        Route::post('/leads/{lead}/transition-stage', [AdvancedLeadController::class, 'transitionStage']);
        Route::get('/leads/{lead}/stage-history', [AdvancedLeadController::class, 'listStageHistory']);

        Route::get('/leads/merge-requests', [AdvancedLeadController::class, 'listMergeRequests']);
        Route::post('/leads/merge-requests', [AdvancedLeadController::class, 'createMergeRequest']);
        Route::post('/leads/merge-requests/{id}/approve', [AdvancedLeadController::class, 'approveMergeRequest']);
        Route::post('/leads/merge-requests/{id}/reject', [AdvancedLeadController::class, 'rejectMergeRequest']);

        Route::get('/leads/scoring/config', [AdvancedLeadController::class, 'getScoringConfig']);
        Route::post('/leads/scoring/config', [AdvancedLeadController::class, 'saveScoringConfig']);

        Route::get('/leads/reports/dashboard', [AdvancedLeadController::class, 'reportsDashboard']);

        Route::post('/leads/check-duplicates', [LeadController::class, 'checkDuplicates']);
        Route::post('/leads/auto-distribute-round-robin', [LeadController::class, 'autoDistributeRoundRobin']);
        Route::get('/leads/export', [LeadController::class, 'export']);
        Route::post('/leads/import', [LeadController::class, 'import']);
        Route::post('/leads/bulk-delete', [LeadController::class, 'bulkDelete']);
        Route::post('/leads/{lead}/assign', [LeadController::class, 'assign']);
        Route::post('/leads/{lead}/convert', [LeadController::class, 'convert']);
        Route::post('/leads/{lead}/merge', [LeadController::class, 'merge']);
        Route::post('/leads/{lead}/archive', [LeadController::class, 'archive']);
        Route::post('/leads/{lead}/unarchive', [LeadController::class, 'unarchive']);
        Route::post('/leads/{lead}/recalculate-score', [LeadController::class, 'recalculateScore']);
        Route::apiResource('leads', LeadController::class);

        // Deals & Sales Pipelines
        Route::get('/deals/forecast', [DealController::class, 'forecast']);
        Route::get('/deals/stale', [DealController::class, 'staleDeals']);
        Route::post('/deals/bulk-delete', [DealController::class, 'bulkDelete']);
        Route::post('/deals/{id}/transfer-ownership', [DealController::class, 'transferOwnership']);
        Route::post('/deals/{id}/reopen', [DealController::class, 'reopen']);
        Route::post('/deals/{id}/convert-to-project', [DealController::class, 'convertToProject']);
        Route::post('/deals/{id}/move-to-stage', [DealController::class, 'moveToStage']);
        Route::post('/deals/{id}/products', [DealController::class, 'addProduct']);
        Route::delete('/deals/{id}/products/{productId}', [DealController::class, 'removeProduct']);
        Route::post('/deals/{id}/archive', [DealController::class, 'archive']);
        Route::post('/deals/{id}/unarchive', [DealController::class, 'unarchive']);
        Route::apiResource('deals', DealController::class);

        Route::post('/pipelines/{id}/stages', [PipelineController::class, 'addStage']);
        Route::put('/pipelines/{id}/stages/{stageId}', [PipelineController::class, 'updateStage']);
        Route::delete('/pipelines/{id}/stages/{stageId}', [PipelineController::class, 'deleteStage']);
        Route::apiResource('pipelines', PipelineController::class);

        // Projects & Milestones
        Route::get('/projects/metrics', [ProjectController::class, 'metrics']);
        Route::get('/projects/workload', [ProjectController::class, 'workload']);
        Route::post('/projects/create-from-deal', [ProjectController::class, 'createFromDeal']);
        Route::post('/projects/create-from-quotation', [ProjectController::class, 'createFromQuotation']);
        Route::post('/projects/{id}/archive', [ProjectController::class, 'archive']);
        Route::post('/projects/{id}/restore', [ProjectController::class, 'restore']);
        Route::post('/projects/{id}/transition-status', [ProjectController::class, 'transitionStatus']);
        Route::get('/projects/{id}/financials', [ProjectController::class, 'financials']);
        Route::get('/projects/{id}/gantt', [ProjectController::class, 'gantt']);
        Route::get('/projects/{id}/milestones', [ProjectController::class, 'milestones']);
        Route::post('/projects/{id}/milestones', [ProjectController::class, 'storeMilestone']);
        Route::put('/projects/{id}/milestones/{milestoneId}', [ProjectController::class, 'updateMilestone']);
        Route::delete('/projects/{id}/milestones/{milestoneId}', [ProjectController::class, 'destroyMilestone']);
        Route::get('/projects/{projectId}/documents', [ProjectDocumentController::class, 'index']);
        Route::post('/projects/{projectId}/documents', [ProjectDocumentController::class, 'store']);
        Route::patch('/project-documents/{id}/visibility', [ProjectDocumentController::class, 'toggleVisibility']);
        Route::delete('/project-documents/{id}', [ProjectDocumentController::class, 'destroy']);
        Route::post('/milestones/{id}/submit-review', [ProjectMilestoneController::class, 'submitReview']);
        Route::post('/milestones/{id}/approve-internal', [ProjectMilestoneController::class, 'approveInternal']);
        Route::post('/milestones/{id}/send-to-client', [ProjectMilestoneController::class, 'sendToClient']);
        Route::post('/projects/{id}/members', [ProjectController::class, 'addMember']);
        Route::delete('/projects/{id}/members/{userId}', [ProjectController::class, 'removeMember']);
        Route::get('/projects/{id}/comments', [ProjectController::class, 'comments']);
        Route::post('/projects/{id}/comments', [ProjectController::class, 'storeComment']);
        Route::apiResource('projects', ProjectController::class);

        // Tasks, Dependencies & Time Tracking
        Route::get('/tasks/metrics', [TaskController::class, 'metrics']);
        Route::get('/tasks/timesheets', [TaskController::class, 'allTimesheets']);
        Route::post('/tasks/bulk-delete', [TaskController::class, 'bulkDelete']);
        Route::post('/tasks/{id}/toggle-checklist', [TaskController::class, 'toggleChecklistItem']);
        Route::post('/tasks/{id}/complete', [TaskController::class, 'complete']);
        Route::post('/tasks/{id}/start-timer', [TaskController::class, 'startTimer']);
        Route::post('/tasks/stop-timer/{logId}', [TaskController::class, 'stopTimer']);
        Route::post('/tasks/timesheets/{logId}/approve', [TaskController::class, 'approveTimeLog']);
        Route::post('/tasks/timesheets/{logId}/reject', [TaskController::class, 'rejectTimeLog']);
        Route::get('/tasks/{id}/time-logs', [TaskController::class, 'timeLogs']);
        Route::post('/tasks/{id}/log-time', [TaskController::class, 'logTime']);
        Route::get('/tasks/{id}/dependencies', [TaskController::class, 'dependencies']);
        Route::post('/tasks/{id}/dependencies', [TaskController::class, 'addDependency']);
        Route::delete('/tasks/{id}/dependencies/{dependsOnTaskId}', [TaskController::class, 'removeDependency']);
        Route::apiResource('tasks', TaskController::class);

        // Meetings & Calendar
        Route::get('/meetings/calendar', [MeetingController::class, 'calendar']);
        Route::post('/meetings/{id}/reschedule', [MeetingController::class, 'reschedule']);
        Route::post('/meetings/{id}/rsvp', [MeetingController::class, 'updateRsvp']);
        Route::post('/meetings/{id}/follow-up-task', [MeetingController::class, 'createFollowUpTask']);
        Route::apiResource('meetings', MeetingController::class);

        // Support Tickets & Helpdesk
        Route::get('/tickets/analytics', [SupportTicketController::class, 'analytics']);
        Route::get('/tickets/categories', [SupportCategoryController::class, 'index']);
        Route::post('/tickets/categories', [SupportCategoryController::class, 'store']);
        Route::put('/tickets/categories/{id}', [SupportCategoryController::class, 'update']);
        Route::delete('/tickets/categories/{id}', [SupportCategoryController::class, 'destroy']);

        Route::get('/tickets/sla-policies', [SupportSlaPolicyController::class, 'index']);
        Route::post('/tickets/sla-policies', [SupportSlaPolicyController::class, 'store']);
        Route::put('/tickets/sla-policies/{id}', [SupportSlaPolicyController::class, 'update']);
        Route::delete('/tickets/sla-policies/{id}', [SupportSlaPolicyController::class, 'destroy']);

        Route::post('/tickets/{id}/assign', [SupportTicketController::class, 'assign']);
        Route::get('/tickets/{id}/messages', [SupportTicketController::class, 'messages']);
        Route::post('/tickets/{id}/messages', [SupportTicketController::class, 'addMessage']);
        Route::post('/tickets/{id}/resolve', [SupportTicketController::class, 'resolve']);
        Route::post('/tickets/{id}/close', [SupportTicketController::class, 'close']);
        Route::post('/tickets/{id}/reopen', [SupportTicketController::class, 'reopen']);
        Route::post('/tickets/{id}/csat', [SupportTicketController::class, 'csat']);
        Route::post('/tickets/{id}/ai-assistance', [SupportTicketController::class, 'aiAssistance']);
        Route::get('/tickets/{id}/transitions', [SupportTicketController::class, 'transitions']);
        Route::post('/tickets/bulk-update', [SupportTicketController::class, 'bulkUpdate']);
        Route::apiResource('tickets', SupportTicketController::class);

        // Customer Success Management & Health Scores
        Route::get('/customer-success/health-scores', [CustomerSuccessController::class, 'getHealthScores']);
        Route::get('/customer-success/health-scores/{customerId}', [CustomerSuccessController::class, 'getCustomerHealthScore']);
        Route::post('/customer-success/check-ins/{customerId}', [CustomerSuccessController::class, 'recordCheckIn']);
        Route::get('/customer-success/contracts', [CustomerSuccessController::class, 'getContracts']);
        Route::post('/customer-success/contracts', [CustomerSuccessController::class, 'createContract']);
        Route::post('/customer-success/contracts/{id}/maintenance', [CustomerSuccessController::class, 'recordMaintenance']);
        Route::get('/customer-success/feedback', [CustomerSuccessController::class, 'getFeedback']);
        Route::post('/customer-success/feedback', [CustomerSuccessController::class, 'submitFeedback']);

        // Knowledge Base & AI-Assisted Answers
        Route::get('/knowledge-base/categories', [KnowledgeBaseController::class, 'getCategories']);
        Route::get('/knowledge-base/articles', [KnowledgeBaseController::class, 'getArticles']);
        Route::get('/knowledge-base/articles/{id}', [KnowledgeBaseController::class, 'getArticle']);
        Route::post('/knowledge-base/articles', [KnowledgeBaseController::class, 'createArticle']);
        Route::post('/knowledge-base/articles/{id}/vote', [KnowledgeBaseController::class, 'voteHelpfulness']);
        Route::post('/knowledge-base/ai-suggest', [KnowledgeBaseController::class, 'suggestAiAnswer']);

        // Sales Teams & Quotas
        Route::get('/sales-teams/leaderboard', [SalesTeamController::class, 'leaderboard']);
        Route::get('/sales-teams/quotas', [SalesTeamController::class, 'quotas']);
        Route::post('/sales-teams/quotas', [SalesTeamController::class, 'saveQuota']);
        Route::apiResource('sales-teams', SalesTeamController::class);

        // Product & Service Catalog
        Route::get('/catalog/export', [ProductCatalogController::class, 'exportCsv']);
        Route::post('/catalog/import', [ProductCatalogController::class, 'importCsv']);
        Route::post('/catalog/check-availability', [ProductCatalogController::class, 'checkAvailability']);
        Route::post('/catalog/{id}/archive', [ProductCatalogController::class, 'archive']);
        Route::post('/catalog/{id}/unarchive', [ProductCatalogController::class, 'unarchive']);
        Route::get('/catalog/categories', [ProductCatalogController::class, 'categories']);
        Route::post('/catalog/categories', [ProductCatalogController::class, 'createCategory']);
        Route::post('/catalog/{id}/variants', [ProductCatalogController::class, 'createVariant']);
        Route::apiResource('catalog', ProductCatalogController::class);
        Route::apiResource('products', ProductCatalogController::class);

        // Inventory Management & Warehouses
        Route::get('/inventory/settings', [InventoryController::class, 'getSettings']);
        Route::put('/inventory/settings', [InventoryController::class, 'updateSettings']);
        Route::get('/inventory/warehouses', [InventoryController::class, 'warehouses']);
        Route::post('/inventory/warehouses', [InventoryController::class, 'createWarehouse']);
        Route::get('/inventory/warehouses/{id}', [InventoryController::class, 'showWarehouse']);
        Route::put('/inventory/warehouses/{id}', [InventoryController::class, 'updateWarehouse']);
        Route::get('/inventory/stocks', [InventoryController::class, 'stocks']);
        Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
        Route::post('/inventory/transfer', [InventoryController::class, 'transfer']);
        Route::get('/inventory/transfers', [InventoryController::class, 'transfers']);
        Route::post('/inventory/transfers/request', [InventoryController::class, 'createTransferRequest']);
        Route::post('/inventory/transfers/{id}/approve', [InventoryController::class, 'approveTransfer']);
        Route::post('/inventory/transfers/{id}/dispatch', [InventoryController::class, 'dispatchTransfer']);
        Route::post('/inventory/transfers/{id}/receive', [InventoryController::class, 'receiveTransfer']);
        Route::post('/inventory/reserve', [InventoryController::class, 'reserve']);
        Route::post('/inventory/release', [InventoryController::class, 'release']);
        Route::post('/inventory/issue', [InventoryController::class, 'issue']);
        Route::get('/inventory/reconciliations', [InventoryController::class, 'reconciliations']);
        Route::post('/inventory/reconciliations', [InventoryController::class, 'createReconciliation']);
        Route::post('/inventory/reconciliations/{id}/apply', [InventoryController::class, 'applyReconciliation']);
        Route::get('/inventory/ledger', [InventoryController::class, 'ledger']);
        Route::get('/inventory/valuation', [InventoryController::class, 'valuation']);
        Route::get('/inventory/low-stock', [InventoryController::class, 'lowStockAlerts']);
        Route::get('/inventory/reorder-report', [InventoryController::class, 'reorderReport']);
        Route::get('/inventory/profitability', [InventoryController::class, 'profitabilityReport']);

        // Procurement & Vendors
        Route::get('/procurement/suppliers', [ProcurementController::class, 'suppliers']);
        Route::post('/procurement/suppliers', [ProcurementController::class, 'createSupplier']);
        Route::get('/procurement/suppliers/{id}', [ProcurementController::class, 'showSupplier']);
        Route::put('/procurement/suppliers/{id}', [ProcurementController::class, 'updateSupplier']);
        Route::get('/procurement/suppliers/{id}/bank-details', [ProcurementController::class, 'supplierBankDetails']);
        Route::get('/procurement/purchase-requests', [ProcurementController::class, 'purchaseRequests']);
        Route::post('/procurement/purchase-requests', [ProcurementController::class, 'createPurchaseRequest']);
        Route::post('/procurement/purchase-requests/{id}/approve', [ProcurementController::class, 'approvePurchaseRequest']);
        Route::post('/procurement/purchase-requests/{id}/reject', [ProcurementController::class, 'rejectPurchaseRequest']);
        Route::get('/procurement/purchase-orders', [ProcurementController::class, 'purchaseOrders']);
        Route::post('/procurement/purchase-orders', [ProcurementController::class, 'createPurchaseOrder']);
        Route::get('/procurement/purchase-orders/{id}', [ProcurementController::class, 'showPurchaseOrder']);
        Route::post('/procurement/purchase-orders/{id}/approve', [ProcurementController::class, 'approvePurchaseOrder']);
        Route::post('/procurement/purchase-orders/{id}/revise', [ProcurementController::class, 'revisePurchaseOrder']);
        Route::post('/procurement/purchase-orders/{id}/receive', [ProcurementController::class, 'receiveGoods']);
        Route::post('/procurement/purchase-orders/{id}/create-bill', [ProcurementController::class, 'createBill']);
        Route::get('/procurement/goods-receipt-notes', [ProcurementController::class, 'goodsReceiptNotes']);

        // Customer Returns (RMA) & Refunds
        Route::get('/returns/reports', [CustomerReturnController::class, 'reports']);
        Route::get('/returns', [CustomerReturnController::class, 'index']);
        Route::post('/returns', [CustomerReturnController::class, 'store']);
        Route::get('/returns/{id}', [CustomerReturnController::class, 'show']);
        Route::post('/returns/{id}/inspect', [CustomerReturnController::class, 'inspect']);
        Route::post('/returns/{id}/approve', [CustomerReturnController::class, 'approve']);

        // Finance & Billing: Quotations
        Route::post('/quotations/{id}/submit-approval', [QuotationController::class, 'submitForApproval']);
        Route::post('/quotations/{id}/approve', [QuotationController::class, 'approve']);
        Route::post('/quotations/{id}/reject', [QuotationController::class, 'reject']);
        Route::post('/quotations/{id}/check-discount', [QuotationController::class, 'checkDiscount']);
        Route::post('/quotations/{id}/customer-action', [QuotationController::class, 'customerAction']);
        Route::post('/quotations/{id}/send-via', [QuotationController::class, 'sendVia']);
        Route::post('/quotations/{id}/revise', [QuotationController::class, 'revise']);
        Route::patch('/quotations/{id}/status', [QuotationController::class, 'markStatus']);
        Route::post('/quotations/{id}/convert-to-invoice', [QuotationController::class, 'convertToInvoice']);
        Route::get('/quotations/{id}/pdf', [QuotationController::class, 'downloadPdf']);
        Route::apiResource('quotations', QuotationController::class);

        // Proposals & Contract Management
        Route::get('/contracts/templates', [ContractController::class, 'templates']);
        Route::post('/contracts/templates', [ContractController::class, 'storeTemplate']);
        Route::post('/contracts/{id}/send-signature', [ContractController::class, 'sendForSignature']);
        Route::post('/contracts/{id}/send-for-signature', [ContractController::class, 'sendForSignature']);
        Route::post('/contracts/{id}/sign', [ContractController::class, 'sign']);
        Route::post('/contracts/{id}/decline', [ContractController::class, 'decline']);
        Route::apiResource('contracts', ContractController::class);

        // Sales Commissions & Incentives
        Route::get('/sales/commissions/report', [SalesCommissionController::class, 'report']);
        Route::get('/sales/commissions/plans', [SalesCommissionController::class, 'plans']);
        Route::post('/sales/commissions/plans', [SalesCommissionController::class, 'storePlan']);
        Route::post('/sales/commissions/{id}/approve', [SalesCommissionController::class, 'approve']);
        Route::post('/sales/commissions/{id}/paid', [SalesCommissionController::class, 'markPaid']);
        Route::post('/sales/commissions/{id}/mark-paid', [SalesCommissionController::class, 'markPaid']);
        Route::post('/sales/commissions/{id}/reverse', [SalesCommissionController::class, 'reverse']);
        Route::get('/sales/commissions', [SalesCommissionController::class, 'index']);

        // Aliases for /sales-commissions
        Route::get('/sales-commissions/report', [SalesCommissionController::class, 'report']);
        Route::get('/sales-commissions/plans', [SalesCommissionController::class, 'plans']);
        Route::post('/sales-commissions/plans', [SalesCommissionController::class, 'storePlan']);
        Route::post('/sales-commissions/{id}/approve', [SalesCommissionController::class, 'approve']);
        Route::post('/sales-commissions/{id}/paid', [SalesCommissionController::class, 'markPaid']);
        Route::post('/sales-commissions/{id}/mark-paid', [SalesCommissionController::class, 'markPaid']);
        Route::post('/sales-commissions/{id}/reverse', [SalesCommissionController::class, 'reverse']);
        Route::get('/sales-commissions', [SalesCommissionController::class, 'index']);

        // Finance & Billing: Invoices
        Route::post('/invoices/{id}/issue', [InvoiceController::class, 'issue']);
        Route::post('/invoices/{id}/void', [InvoiceController::class, 'void']);
        Route::post('/invoices/{id}/payments', [InvoiceController::class, 'recordPayment']);
        Route::post('/invoices/{id}/payment-link', [InvoiceController::class, 'createPaymentLink']);
        Route::post('/invoices/{id}/verify-payment', [InvoiceController::class, 'verifyPayment']);
        Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf']);
        Route::apiResource('invoices', InvoiceController::class);

        // Finance & Billing: Payments, Allocations & Refunds
        Route::post('/payments/{id}/refund-request', [AdvancedPaymentController::class, 'requestRefund']);
        Route::post('/payments/{id}/refund', [PaymentController::class, 'refund']);
        Route::post('/refunds/{id}/approve', [AdvancedPaymentController::class, 'approveRefund']);
        Route::get('/refunds', [AdvancedPaymentController::class, 'getRefunds']);
        Route::post('/payments/{id}/allocate', [AdvancedPaymentController::class, 'allocatePayment']);
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{id}', [PaymentController::class, 'show']);

        // Advanced Payments: Gateways, Payment Links, UPI QR & Webhooks
        Route::get('/payments/gateways', [AdvancedPaymentController::class, 'getGateways']);
        Route::post('/payments/gateways', [AdvancedPaymentController::class, 'saveGateway']);
        Route::post('/payments/upi-qr', [AdvancedPaymentController::class, 'generateUpiQr']);
        Route::get('/payment-links', [AdvancedPaymentController::class, 'getPaymentLinks']);
        Route::post('/payment-links', [AdvancedPaymentController::class, 'createPaymentLink']);
        Route::get('/payment-links/{token}', [AdvancedPaymentController::class, 'getPaymentLinkByToken']);
        Route::post('/payments/webhook/{provider}', [AdvancedPaymentController::class, 'handleWebhook']);

        // Centralized Transaction Register & Reconciliation Ledger
        Route::get('/finance/transactions', [AdvancedPaymentController::class, 'getTransactions']);
        Route::post('/finance/transactions/{id}/reconcile', [AdvancedPaymentController::class, 'reconcileTransaction']);

        // Accounts Receivable (AR) Management
        Route::get('/receivables/ageing', [ReceivableController::class, 'getAgeingReport']);
        Route::post('/receivables/invoices/{id}/promise-to-pay', [ReceivableController::class, 'updatePromiseToPay']);
        Route::post('/receivables/invoices/{id}/dispute', [ReceivableController::class, 'setDispute']);
        Route::post('/receivables/invoices/{id}/assign', [ReceivableController::class, 'assignCollectionAgent']);

        // Accounts Payable (AP) & Vendor Management
        Route::get('/payables/bills', [PayableController::class, 'getBills']);
        Route::post('/payables/bills', [PayableController::class, 'createBill']);
        Route::post('/payables/bills/{id}/approve', [PayableController::class, 'approveBill']);
        Route::get('/payables/payments', [PayableController::class, 'getPayments']);
        Route::post('/payables/payments', [PayableController::class, 'recordPayment']);
        Route::get('/payables/ageing', [PayableController::class, 'getAgeingReport']);

        // Expenses & Reimbursement Management
        Route::get('/expenses/report', [ExpenseController::class, 'report']);
        Route::post('/expenses/{id}/approve', [ExpenseController::class, 'approve']);
        Route::post('/expenses/{id}/reject', [ExpenseController::class, 'reject']);
        Route::post('/expenses/{id}/pay', [ExpenseController::class, 'pay']);
        Route::apiResource('expenses', ExpenseController::class);

        // Finance & Billing: Overview & Reports
        Route::get('/finance/summary', [FinanceReportController::class, 'summary']);
        Route::get('/finance/comprehensive-report', [FinanceReportController::class, 'comprehensive']);

        // Indian GST Invoicing, Tax Management & Compliance
        Route::get('/gst/config', [GstComplianceController::class, 'getGstRegistrations']);
        Route::post('/gst/config', [GstComplianceController::class, 'saveGstRegistration']);
        Route::post('/gst/verify-gstin', [GstComplianceController::class, 'verifyGstin']);
        Route::post('/gst/calculate-tax', [GstComplianceController::class, 'calculateTax']);
        Route::get('/gst/invoices', [GstComplianceController::class, 'getInvoices']);
        Route::post('/gst/invoices', [GstComplianceController::class, 'createInvoice']);
        Route::post('/gst/invoices/{id}/finalize', [GstComplianceController::class, 'finalizeInvoice']);
        Route::post('/gst/invoices/{id}/credit-note', [GstComplianceController::class, 'createCreditNote']);
        Route::post('/gst/invoices/{id}/einvoice', [GstComplianceController::class, 'generateEInvoice']);
        Route::post('/gst/invoices/{id}/einvoice/cancel', [GstComplianceController::class, 'cancelEInvoice']);
        Route::post('/gst/invoices/{id}/eway-bill', [GstComplianceController::class, 'generateEWayBill']);
        Route::post('/gst/invoices/{id}/eway-bill/vehicle', [GstComplianceController::class, 'updateEWayBillVehicle']);
        Route::post('/gst/invoices/{id}/eway-bill/cancel', [GstComplianceController::class, 'cancelEWayBill']);
        Route::get('/gst/reports/summary', [GstComplianceController::class, 'getTaxSummary']);
        Route::get('/gst/reports/sales-register', [GstComplianceController::class, 'getSalesRegister']);
        Route::get('/gst/reports/hsn-summary', [GstComplianceController::class, 'getHsnSummary']);
        Route::get('/gst/reports/export-csv', [GstComplianceController::class, 'exportSalesRegisterCsv']);
        Route::get('/gst/compliance/reminders', [GstComplianceController::class, 'getComplianceReminders']);
        Route::post('/gst/compliance/reminders', [GstComplianceController::class, 'createComplianceReminder']);
        Route::post('/gst/compliance/reminders/{id}/complete', [GstComplianceController::class, 'completeComplianceReminder']);
        Route::get('/gst/compliance/documents', [GstComplianceController::class, 'getComplianceDocuments']);
        Route::post('/gst/compliance/documents', [GstComplianceController::class, 'storeComplianceDocument']);

        // Official WhatsApp CRM & Shared Team Inbox
        Route::get('/whatsapp/accounts', [WhatsAppAccountController::class, 'accounts']);
        Route::post('/whatsapp/accounts', [WhatsAppAccountController::class, 'storeAccount']);
        Route::post('/whatsapp/accounts/{id}/disconnect', [WhatsAppAccountController::class, 'disconnectAccount']);
        Route::get('/whatsapp/phone-numbers', [WhatsAppAccountController::class, 'phoneNumbers']);
        Route::post('/whatsapp/phone-numbers', [WhatsAppAccountController::class, 'connectPhoneNumber']);
        Route::put('/whatsapp/phone-numbers/{id}', [WhatsAppAccountController::class, 'updatePhoneNumber']);
        Route::delete('/whatsapp/phone-numbers/{id}', [WhatsAppAccountController::class, 'disconnectPhoneNumber']);
        Route::post('/whatsapp/phone-numbers/{id}/test', [WhatsAppAccountController::class, 'testConnection']);
        Route::get('/whatsapp/logs', [WhatsAppAccountController::class, 'integrationLogs']);

        Route::get('/whatsapp/templates', [WhatsAppTemplateController::class, 'index']);
        Route::post('/whatsapp/templates', [WhatsAppTemplateController::class, 'store']);
        Route::post('/whatsapp/templates/sync', [WhatsAppTemplateController::class, 'sync']);
        Route::post('/whatsapp/templates/{id}/test', [WhatsAppTemplateController::class, 'testMessage']);
        Route::get('/whatsapp/templates/analytics', [WhatsAppTemplateController::class, 'analytics']);

        Route::get('/whatsapp/conversations', [WhatsAppConversationController::class, 'index']);
        Route::get('/whatsapp/conversations/{id}', [WhatsAppConversationController::class, 'show']);
        Route::post('/whatsapp/conversations/{id}/assign', [WhatsAppConversationController::class, 'assign']);
        Route::patch('/whatsapp/conversations/{id}/status', [WhatsAppConversationController::class, 'updateStatus']);
        Route::post('/whatsapp/conversations/{id}/notes', [WhatsAppConversationController::class, 'addNote']);
        Route::post('/whatsapp/conversations/{id}/toggle-ai', [WhatsAppConversationController::class, 'toggleAi']);
        Route::post('/whatsapp/conversations/{id}/create-lead', [WhatsAppConversationController::class, 'createLead']);

        Route::post('/whatsapp/conversations/{id}/messages/text', [WhatsAppMessageController::class, 'sendText']);
        Route::post('/whatsapp/conversations/{id}/messages/template', [WhatsAppMessageController::class, 'sendTemplate']);
        Route::post('/whatsapp/conversations/{id}/messages/media', [WhatsAppMessageController::class, 'sendMedia']);
        Route::post('/whatsapp/conversations/{id}/messages/interactive', [WhatsAppMessageController::class, 'sendInteractive']);
        Route::post('/whatsapp/conversations/{id}/messages/location', [WhatsAppMessageController::class, 'sendLocation']);

        // WhatsApp Bulk Campaigns
        Route::get('/whatsapp/campaigns', [WhatsAppCampaignController::class, 'index']);
        Route::post('/whatsapp/campaigns', [WhatsAppCampaignController::class, 'store']);
        Route::get('/whatsapp/campaigns/{id}', [WhatsAppCampaignController::class, 'show']);
        Route::get('/whatsapp/campaigns/{id}/recipients', [WhatsAppCampaignController::class, 'recipients']);
        Route::post('/whatsapp/campaigns/{id}/test', [WhatsAppCampaignController::class, 'testMessage']);
        Route::post('/whatsapp/campaigns/{id}/submit', [WhatsAppCampaignController::class, 'submitForApproval']);
        Route::post('/whatsapp/campaigns/{id}/approve', [WhatsAppCampaignController::class, 'approve']);
        Route::post('/whatsapp/campaigns/{id}/start', [WhatsAppCampaignController::class, 'start']);
        Route::post('/whatsapp/campaigns/{id}/pause', [WhatsAppCampaignController::class, 'pause']);
        Route::post('/whatsapp/campaigns/{id}/cancel', [WhatsAppCampaignController::class, 'cancel']);
        Route::post('/whatsapp/campaigns/{id}/process-batch', [WhatsAppCampaignController::class, 'processBatch']);

        // WhatsApp Automations & Customer Journeys
        Route::get('/whatsapp/automations', [WhatsAppAutomationController::class, 'index']);
        Route::post('/whatsapp/automations', [WhatsAppAutomationController::class, 'store']);
        Route::get('/whatsapp/automations/logs', [WhatsAppAutomationController::class, 'logs']);
        Route::get('/whatsapp/automations/{id}', [WhatsAppAutomationController::class, 'show']);
        Route::put('/whatsapp/automations/{id}', [WhatsAppAutomationController::class, 'update']);
        Route::post('/whatsapp/automations/{id}/toggle', [WhatsAppAutomationController::class, 'toggleStatus']);
        Route::delete('/whatsapp/automations/{id}', [WhatsAppAutomationController::class, 'destroy']);
        Route::post('/whatsapp/automations/trigger-test', [WhatsAppAutomationController::class, 'triggerTest']);

        // WhatsApp Billing & Usage
        Route::get('/whatsapp/usage/summary', [WhatsAppUsageController::class, 'summary']);
        Route::get('/whatsapp/usage/records', [WhatsAppUsageController::class, 'records']);

        // WhatsApp AI Agents, Knowledge Base, Providers & Logs
        Route::get('/whatsapp/ai/agents', [WhatsAppAiAgentController::class, 'index']);
        Route::post('/whatsapp/ai/agents', [WhatsAppAiAgentController::class, 'store']);
        Route::get('/whatsapp/ai/agents/{id}', [WhatsAppAiAgentController::class, 'show']);
        Route::put('/whatsapp/ai/agents/{id}', [WhatsAppAiAgentController::class, 'update']);
        Route::delete('/whatsapp/ai/agents/{id}', [WhatsAppAiAgentController::class, 'destroy']);
        Route::post('/whatsapp/ai/agents/{id}/test', [WhatsAppAiAgentController::class, 'testPlayground']);

        Route::get('/whatsapp/ai/knowledge', [WhatsAppAiKnowledgeController::class, 'index']);
        Route::post('/whatsapp/ai/knowledge/upload', [WhatsAppAiKnowledgeController::class, 'upload']);
        Route::post('/whatsapp/ai/knowledge/search', [WhatsAppAiKnowledgeController::class, 'search']);
        Route::delete('/whatsapp/ai/knowledge/{id}', [WhatsAppAiKnowledgeController::class, 'destroy']);

        Route::get('/whatsapp/ai/providers', [WhatsAppAiProviderController::class, 'index']);
        Route::post('/whatsapp/ai/providers', [WhatsAppAiProviderController::class, 'store']);

        Route::get('/whatsapp/ai/logs', [WhatsAppAiLogsController::class, 'index']);

        // AI Voice & Telephony Routes
        Route::get('/voice/calls', [VoiceCallController::class, 'index']);
        Route::get('/voice/calls/reports', [TelephonyProviderController::class, 'getReports']);
        Route::post('/voice/calls/lookup', [VoiceCallController::class, 'lookupCaller']);
        Route::post('/voice/calls/inbound', [VoiceCallController::class, 'handleInbound']);
        Route::get('/voice/calls/{id}', [VoiceCallController::class, 'show']);
        Route::post('/voice/calls/dial', [VoiceCallController::class, 'initiateCall']);
        Route::post('/voice/calls/click-to-call', [VoiceCallController::class, 'clickToCall']);
        Route::post('/voice/calls/{id}/turn', [VoiceCallController::class, 'simulatePlaygroundTurn']);
        Route::post('/voice/calls/{id}/disposition', [VoiceCallController::class, 'updateDisposition']);
        Route::get('/voice/calls/{id}/recording/playback', [VoiceCallController::class, 'recordingPlayback']);
        Route::get('/voice/calls/{id}/recording/download', [VoiceCallController::class, 'recordingDownload']);
        Route::post('/voice/calls/{id}/reviews', [VoiceCallController::class, 'submitQualityReview']);
        Route::post('/voice/calls/{id}/transfer', [VoiceCallController::class, 'transferCall']);

        Route::get('/voice/agents', [VoiceAiAgentController::class, 'index']);
        Route::post('/voice/agents', [VoiceAiAgentController::class, 'store']);
        Route::get('/voice/agents/{id}', [VoiceAiAgentController::class, 'show']);
        Route::put('/voice/agents/{id}', [VoiceAiAgentController::class, 'update']);
        Route::delete('/voice/agents/{id}', [VoiceAiAgentController::class, 'destroy']);

        Route::get('/voice/ivr-flows', [CallIvrQueueController::class, 'getIvrFlows']);
        Route::post('/voice/ivr-flows', [CallIvrQueueController::class, 'storeIvrFlow']);
        Route::put('/voice/ivr-flows/{id}', [CallIvrQueueController::class, 'updateIvrFlow']);
        Route::delete('/voice/ivr-flows/{id}', [CallIvrQueueController::class, 'deleteIvrFlow']);

        Route::get('/voice/queues', [CallIvrQueueController::class, 'getQueues']);
        Route::post('/voice/queues', [CallIvrQueueController::class, 'storeQueue']);
        Route::put('/voice/queues/{id}', [CallIvrQueueController::class, 'updateQueue']);
        Route::delete('/voice/queues/{id}', [CallIvrQueueController::class, 'deleteQueue']);

        Route::get('/voice/campaigns', [VoiceCallingCampaignController::class, 'index']);
        Route::post('/voice/campaigns', [VoiceCallingCampaignController::class, 'store']);
        Route::post('/voice/campaigns/{id}/launch', [VoiceCallingCampaignController::class, 'launch']);
        Route::post('/voice/campaigns/{id}/pause', [VoiceCallingCampaignController::class, 'pause']);
        Route::delete('/voice/campaigns/{id}', [VoiceCallingCampaignController::class, 'destroy']);

        Route::get('/voice/providers', [TelephonyProviderController::class, 'index']);
        Route::post('/voice/providers', [TelephonyProviderController::class, 'store']);
        Route::post('/voice/providers/{id}/test', [TelephonyProviderController::class, 'testConnection']);
        Route::get('/voice/providers/logs', [TelephonyProviderController::class, 'getLogs']);
        Route::get('/voice/phone-numbers', [TelephonyProviderController::class, 'phoneNumbers']);
        Route::post('/voice/phone-numbers', [TelephonyProviderController::class, 'storePhoneNumber']);

        // Unified Omnichannel Inbox (WhatsApp, Facebook, Instagram, Email, SMS, Calls, Website Chat)
        Route::get('/inbox/conversations', [OmnichannelInboxController::class, 'index']);
        Route::get('/inbox/conversations/{id}', [OmnichannelInboxController::class, 'show']);
        Route::post('/inbox/conversations/{id}/messages', [OmnichannelInboxController::class, 'sendMessage']);
        Route::post('/inbox/conversations/{id}/assign', [OmnichannelInboxController::class, 'assign']);
        Route::post('/inbox/conversations/{id}/auto-assign', [OmnichannelInboxController::class, 'autoAssign']);
        Route::get('/inbox/conversations/{id}/assignment-history', [OmnichannelInboxController::class, 'assignmentHistory']);
        Route::post('/inbox/conversations/{id}/typing', [OmnichannelInboxController::class, 'typing']);
        Route::patch('/inbox/conversations/{id}/status', [OmnichannelInboxController::class, 'updateStatus']);
        Route::post('/inbox/conversations/{id}/toggle-ai', [OmnichannelInboxController::class, 'toggleAi']);
        Route::post('/inbox/conversations/{id}/read', [OmnichannelInboxController::class, 'markRead']);
        Route::post('/inbox/conversations/{id}/notes', [OmnichannelInboxController::class, 'addNote']);
        Route::get('/inbox/timeline', [OmnichannelInboxController::class, 'timeline']);
        Route::get('/inbox/analytics', [OmnichannelInboxController::class, 'analytics']);
        Route::get('/inbox/consents', [OmnichannelInboxController::class, 'getConsents']);
        Route::post('/inbox/consents', [OmnichannelInboxController::class, 'updateConsent']);
        Route::get('/inbox/quick-replies', [OmnichannelInboxController::class, 'getQuickReplies']);
        Route::post('/inbox/quick-replies', [OmnichannelInboxController::class, 'createQuickReply']);
        Route::delete('/inbox/quick-replies/{id}', [OmnichannelInboxController::class, 'deleteQuickReply']);

        // Conversation Locking, Archiving, Presence & Collision
        Route::post('/inbox/conversations/{id}/lock', [OmnichannelInboxController::class, 'lock']);
        Route::post('/inbox/conversations/{id}/unlock', [OmnichannelInboxController::class, 'unlock']);
        Route::post('/inbox/conversations/{id}/archive', [OmnichannelInboxController::class, 'archive']);
        Route::post('/inbox/presence', [OmnichannelInboxController::class, 'presence']);
        Route::get('/inbox/conversations/{id}/collision', [OmnichannelInboxController::class, 'collisionCheck']);

        // Routing Rules & SLA Policies
        Route::get('/inbox/routing-rules', [OmnichannelInboxController::class, 'getRoutingRules']);
        Route::post('/inbox/routing-rules', [OmnichannelInboxController::class, 'saveRoutingRule']);
        Route::delete('/inbox/routing-rules/{id}', [OmnichannelInboxController::class, 'deleteRoutingRule']);

        Route::get('/inbox/sla-policies', [OmnichannelInboxController::class, 'getSlaPolicies']);
        Route::post('/inbox/sla-policies', [OmnichannelInboxController::class, 'saveSlaPolicy']);
        Route::delete('/inbox/sla-policies/{id}', [OmnichannelInboxController::class, 'deleteSlaPolicy']);
        Route::post('/inbox/sla-policies/recalculate', [OmnichannelInboxController::class, 'recalculateSla']);
        Route::get('/inbox/detailed-reports', [OmnichannelInboxController::class, 'detailedReports']);

        // Channel Integrations & Configurations
        Route::get('/inbox/channels/email', [OmnichannelChannelConfigController::class, 'getEmailAccounts']);
        Route::post('/inbox/channels/email', [OmnichannelChannelConfigController::class, 'saveEmailAccount']);
        Route::get('/inbox/channels/sms', [OmnichannelChannelConfigController::class, 'getSmsConfigs']);
        Route::post('/inbox/channels/sms', [OmnichannelChannelConfigController::class, 'saveSmsConfig']);
        Route::get('/inbox/channels/webchat', [OmnichannelChannelConfigController::class, 'getWebChatWidgets']);
        Route::post('/inbox/channels/webchat', [OmnichannelChannelConfigController::class, 'saveWebChatWidget']);

        // Telegram Channel Integration & Settings
        Route::get('/inbox/channels/telegram', [OmnichannelChannelConfigController::class, 'getTelegramAccounts']);
        Route::post('/inbox/channels/telegram', [OmnichannelChannelConfigController::class, 'saveTelegramAccount']);
        Route::post('/inbox/channels/telegram/{id}/disconnect', [OmnichannelChannelConfigController::class, 'disconnectTelegramAccount']);

        // Channel Settings & Connection Testing
        Route::get('/inbox/channels/settings', [OmnichannelChannelConfigController::class, 'getChannelSettings']);
        Route::post('/inbox/channels/settings', [OmnichannelChannelConfigController::class, 'saveChannelSettings']);
        Route::post('/inbox/channels/test-connection', [OmnichannelChannelConfigController::class, 'testConnection']);
        Route::get('/inbox/channels/health', [OmnichannelChannelConfigController::class, 'getChannelHealth']);

        // Suppression List & Identity Resolution
        Route::get('/inbox/suppressions', [OmnichannelChannelConfigController::class, 'getSuppressions']);
        Route::post('/inbox/suppressions', [OmnichannelChannelConfigController::class, 'addSuppression']);
        Route::delete('/inbox/suppressions/{id}', [OmnichannelChannelConfigController::class, 'removeSuppression']);
        Route::get('/inbox/identity/pending', [OmnichannelChannelConfigController::class, 'getPendingMerges']);
        Route::post('/inbox/identity/pending/{id}/review', [OmnichannelChannelConfigController::class, 'reviewMerge']);

        // Visual Workflow Automation Builder
        Route::get('/workflows/dashboard/metrics', [WorkflowLimitController::class, 'getMetrics']);
        Route::get('/workflows/triggers/catalog', [WorkflowLimitController::class, 'getTriggersCatalog']);
        Route::get('/workflows/limits', [WorkflowLimitController::class, 'getLimits']);
        Route::put('/workflows/limits', [WorkflowLimitController::class, 'updateLimits']);
        Route::get('/workflows/approvals', [WorkflowApprovalController::class, 'index']);
        Route::get('/workflows/approvals/{id}', [WorkflowApprovalController::class, 'show']);
        Route::post('/workflows/approvals/{id}/decide', [WorkflowApprovalController::class, 'decide']);

        Route::get('/workflows/templates', [WorkflowController::class, 'templates']);
        Route::post('/workflows/import-template', [WorkflowController::class, 'importTemplate']);
        Route::post('/workflows/{id}/publish', [WorkflowController::class, 'publish']);
        Route::post('/workflows/{id}/pause', [WorkflowController::class, 'pause']);
        Route::post('/workflows/{id}/simulate', [WorkflowController::class, 'testSimulation']);
        Route::apiResource('workflows', WorkflowController::class);

        Route::get('/workflow-runs', [WorkflowRunController::class, 'index']);
        Route::get('/workflow-runs/{id}', [WorkflowRunController::class, 'show']);
        Route::post('/workflow-runs/{id}/retry', [WorkflowRunController::class, 'retry']);
        Route::post('/workflow-runs/{id}/cancel', [WorkflowRunController::class, 'cancel']);
        Route::post('/workflows/trigger', [WorkflowRunController::class, 'triggerManual']);

        // Landing Pages & Form Builder
        Route::get('/landing-pages/templates', [LandingPageController::class, 'templates']);
        Route::post('/landing-pages/from-template', [LandingPageController::class, 'createFromTemplate']);
        Route::post('/landing-pages/{id}/publish', [LandingPageController::class, 'publish']);
        Route::post('/landing-pages/{id}/unpublish', [LandingPageController::class, 'unpublish']);
        Route::post('/landing-pages/{id}/rollback', [LandingPageController::class, 'rollback']);
        Route::get('/landing-pages/{id}/analytics', [LandingPageAnalyticsController::class, 'show']);
        Route::apiResource('landing-pages', LandingPageController::class);

        Route::get('/forms/{id}/submissions', [FormBuilderController::class, 'submissions']);
        Route::apiResource('forms', FormBuilderController::class);

        Route::post('/custom-domains/{id}/verify', [CustomDomainController::class, 'verify']);
        Route::apiResource('custom-domains', CustomDomainController::class);

        // Marketing Automation & Campaign Management
        Route::post('/segments/preview', [MarketingSegmentController::class, 'preview']);
        Route::get('/segments/{id}/members', [MarketingSegmentController::class, 'members']);
        Route::apiResource('segments', MarketingSegmentController::class);

        Route::get('/campaigns/summary', [MarketingCampaignController::class, 'overviewSummary']);
        Route::post('/campaigns/{id}/submit-approval', [MarketingCampaignController::class, 'submitForApproval']);
        Route::post('/campaigns/{id}/approve', [MarketingCampaignController::class, 'approve']);
        Route::post('/campaigns/{id}/reject', [MarketingCampaignController::class, 'reject']);
        Route::post('/campaigns/{id}/duplicate', [MarketingCampaignController::class, 'duplicate']);
        Route::post('/campaigns/{id}/archive', [MarketingCampaignController::class, 'archive']);
        Route::post('/campaigns/{id}/dispatch', [MarketingCampaignController::class, 'dispatch']);
        Route::post('/campaigns/{id}/test-send', [MarketingCampaignController::class, 'testSend']);
        Route::post('/campaigns/{id}/pause', [MarketingCampaignController::class, 'pause']);
        Route::post('/campaigns/{id}/resume', [MarketingCampaignController::class, 'resume']);
        Route::get('/campaigns/{id}/analytics', [MarketingCampaignController::class, 'analytics']);
        Route::apiResource('campaigns', MarketingCampaignController::class);

        // Reusable Multi-channel Templates (WhatsApp, Email, SMS)
        Route::post('/marketing/templates/ai-generate', [MarketingTemplateController::class, 'generateWithAi']);
        Route::get('/marketing/templates/{id}/preview', [MarketingTemplateController::class, 'preview']);
        Route::apiResource('/marketing/templates', MarketingTemplateController::class);

        // Compliance & Consent Registry
        Route::get('/marketing/consents', [MarketingConsentController::class, 'index']);
        Route::post('/marketing/consents', [MarketingConsentController::class, 'store']);
        Route::post('/marketing/consents/check', [MarketingConsentController::class, 'check']);
        Route::get('/marketing/consents/suppressions', [MarketingConsentController::class, 'suppressions']);
        Route::post('/marketing/consents/unsubscribe', [MarketingConsentController::class, 'unsubscribe']);

        Route::post('/drip-sequences/process-due', [DripSequenceController::class, 'processDue']);
        Route::post('/drip-sequences/{id}/enroll', [DripSequenceController::class, 'enroll']);
        Route::apiResource('drip-sequences', DripSequenceController::class);

        Route::post('/companies/bulk-delete', [CompanyController::class, 'bulkDelete']);
        Route::apiResource('companies', CompanyController::class);

        Route::get('/customers/{id}/360', [CustomerController::class, 'profile360']);
        Route::post('/customers/bulk-delete', [CustomerController::class, 'bulkDelete']);
        Route::apiResource('customers', CustomerController::class);

        Route::post('/contacts/bulk-delete', [ContactController::class, 'bulkDelete']);
        Route::apiResource('contacts', ContactController::class);

        // Timeline Activities
        Route::apiResource('activities', CrmActivityController::class)->only(['index', 'store', 'destroy']);

        // Reports & Analytics
        Route::get('/reports', [ReportController::class, 'index']);

        // Centralized Analytics, Attribution & Custom Reporting
        Route::prefix('analytics')->group(function () {
            Route::get('/executive', [AnalyticsController::class, 'executive']);
            Route::get('/leads', [AnalyticsController::class, 'leads']);
            Route::get('/sales', [AnalyticsController::class, 'sales']);
            Route::get('/attribution', [AnalyticsController::class, 'attribution']);
            Route::post('/attribution/touchpoint', [AnalyticsController::class, 'recordTouchpoint']);
            Route::get('/export', [AnalyticsController::class, 'export']);
            Route::get('/saved-reports', [AnalyticsController::class, 'savedReports']);
            Route::post('/saved-reports', [AnalyticsController::class, 'saveReport']);
            Route::delete('/saved-reports/{id}', [AnalyticsController::class, 'deleteSavedReport']);

            // Prompt 49: Advanced Executive Dashboard, BI Widgets & Reports
            Route::get('/owner-dashboard', [OwnerDashboardController::class, 'getMetrics']);
            Route::get('/dashboard-layouts', [OwnerDashboardController::class, 'getLayouts']);
            Route::post('/dashboard-layouts', [OwnerDashboardController::class, 'saveLayout']);
            Route::put('/dashboard-layouts/{id}', [OwnerDashboardController::class, 'updateLayout']);
            Route::delete('/dashboard-layouts/{id}', [OwnerDashboardController::class, 'deleteLayout']);

            // Custom Report Builder
            Route::get('/report-builder/catalog', [CustomReportBuilderController::class, 'getCatalog']);
            Route::post('/report-builder/preview', [CustomReportBuilderController::class, 'previewReport']);
            Route::post('/report-builder/save', [CustomReportBuilderController::class, 'saveReport']);
            Route::post('/report-builder/export', [CustomReportBuilderController::class, 'exportReport']);
            Route::post('/report-builder/{id}/schedule', [CustomReportBuilderController::class, 'scheduleReport']);

            // Domain Analytics Deep Dives
            Route::get('/sales-deep-dive', [SalesMarketingFinanceAnalyticsController::class, 'salesDeepDive']);
            Route::get('/marketing-deep-dive', [SalesMarketingFinanceAnalyticsController::class, 'marketingDeepDive']);
            Route::get('/finance-deep-dive', [SalesMarketingFinanceAnalyticsController::class, 'financeDeepDive']);

            // AI Insights & Custom Alerts
            Route::get('/ai-insights', [AiInsightsAlertsController::class, 'getAiInsights']);
            Route::get('/alerts', [AiInsightsAlertsController::class, 'getAlerts']);
            Route::post('/alerts', [AiInsightsAlertsController::class, 'createAlert']);
            Route::put('/alerts/{id}', [AiInsightsAlertsController::class, 'updateAlert']);
            Route::delete('/alerts/{id}', [AiInsightsAlertsController::class, 'deleteAlert']);
            Route::post('/alerts/evaluate', [AiInsightsAlertsController::class, 'evaluateAlerts']);
        });

        // Field Sales & Visit Route Management
        Route::prefix('field-sales')->group(function () {
            Route::get('/visits', [FieldSalesController::class, 'index']);
            Route::post('/visits', [FieldSalesController::class, 'store']);
            Route::post('/visits/{id}/check-in', [FieldSalesController::class, 'checkIn']);
            Route::post('/visits/{id}/check-out', [FieldSalesController::class, 'checkOut']);
            Route::get('/daily-plan', [FieldSalesController::class, 'dailyPlan']);
            Route::get('/analytics', [FieldSalesController::class, 'analytics']);
            Route::post('/sync-offline', [FieldSalesController::class, 'syncOffline']);
        });

        // India-First Capabilities & Settings
        Route::get('/india/settings', [IndiaFirstController::class, 'getSettings']);
        Route::match(['get', 'post'], '/india/format-inr', [IndiaFirstController::class, 'formatAmount']);
        Route::get('/india/translations/{lang}', [IndiaFirstController::class, 'getTranslations']);

        // Analytics Tracking SDK, GA4 & Attribution Diagnostics
        Route::prefix('tracking')->group(function () {
            Route::get('/settings', [TrackingAdminController::class, 'getSettings']);
            Route::put('/settings', [TrackingAdminController::class, 'updateSettings']);
            Route::get('/events', [TrackingAdminController::class, 'getEvents']);
            Route::get('/attribution', [TrackingAdminController::class, 'getAttribution']);
            Route::get('/diagnostics', [TrackingAdminController::class, 'getDiagnostics']);
            Route::post('/ga4/connect', [TrackingAdminController::class, 'connectGa4']);
            Route::post('/ga4/disconnect', [TrackingAdminController::class, 'disconnectGa4']);
            Route::get('/ga4/properties', [TrackingAdminController::class, 'getGa4Properties']);
            Route::get('/ga4/report', [TrackingAdminController::class, 'getGa4Report']);
        });

        // Facebook Messenger & Instagram DM Management
        Route::prefix('social')->group(function () {
            Route::get('/accounts', [SocialChannelController::class, 'getAccounts']);
            Route::post('/accounts', [SocialChannelController::class, 'connectAccount']);
            Route::post('/accounts/{id}/disconnect', [SocialChannelController::class, 'disconnectAccount']);
            Route::post('/conversations/{id}/messages', [SocialChannelController::class, 'sendMessage']);
            Route::get('/identities', [SocialChannelController::class, 'getIdentities']);
            Route::post('/identities/{id}/link', [SocialChannelController::class, 'linkIdentity']);
            Route::post('/identities/merge', [SocialChannelController::class, 'mergeIdentities']);
            Route::get('/logs', [SocialChannelController::class, 'getLogs']);
            Route::get('/diagnostics', [SocialChannelController::class, 'getDiagnostics']);
        });

        // Integrations Directory, OAuth & Sync Platform
        Route::get('/integrations', [IntegrationController::class, 'index']);
        Route::post('/integrations/oauth-url', [IntegrationController::class, 'oauthUrl']);
        Route::post('/integrations/oauth-callback', [IntegrationController::class, 'oauthCallback']);
        Route::post('/integrations/connect-credentials', [IntegrationController::class, 'connectCredentials']);
        Route::post('/integrations/{id}/disconnect', [IntegrationController::class, 'disconnect']);
        Route::post('/integrations/{id}/test', [IntegrationController::class, 'test']);
        Route::post('/integrations/{id}/sync', [IntegrationController::class, 'sync']);
        Route::get('/integrations/{id}/sync-logs', [IntegrationController::class, 'syncLogs']);

        // Public Tenant API Keys Management
        Route::get('/api-keys/logs', [ApiKeyController::class, 'logs']);
        Route::post('/api-keys/{id}/rotate', [ApiKeyController::class, 'rotate']);
        Route::apiResource('api-keys', ApiKeyController::class)->only(['index', 'store', 'destroy']);

        // Outbound Webhooks & Delivery Management
        Route::get('/webhooks/logs', [OutboundWebhookController::class, 'logs']);
        Route::post('/webhooks/logs/{id}/redeliver', [OutboundWebhookController::class, 'redeliver']);
        Route::post('/webhooks/{id}/test-ping', [OutboundWebhookController::class, 'testPing']);
        Route::apiResource('webhooks', OutboundWebhookController::class);

        // OpenAPI Documentation Specification
        Route::get('/docs/openapi.json', [OpenApiController::class, 'spec']);

        // SaaS Commercial Subscriptions, Usage Metering & Credit Ledger
        Route::prefix('billing')->group(function () {
            Route::get('/plans', [SubscriptionBillingController::class, 'plans']);
            Route::get('/subscription', [SubscriptionBillingController::class, 'subscription']);
            Route::post('/subscribe', [SubscriptionBillingController::class, 'subscribe']);
            Route::post('/subscription/subscribe', [SubscriptionBillingController::class, 'subscribe']);
            Route::post('/cancel', [SubscriptionBillingController::class, 'cancel']);
            Route::post('/subscription/cancel', [SubscriptionBillingController::class, 'cancel']);
            Route::put('/contact', [SubscriptionBillingController::class, 'updateBillingContact']);
            Route::put('/subscription/billing-contact', [SubscriptionBillingController::class, 'updateBillingContact']);
            Route::get('/invoices', [SubscriptionBillingController::class, 'invoices']);

            // Hybrid Usage Metering & Credit Ledger
            Route::get('/usage', [UsageMeteringController::class, 'dashboard']);
            Route::get('/usage/dashboard', [UsageMeteringController::class, 'dashboard']);
            Route::post('/usage/reserve', [UsageMeteringController::class, 'reserve']);
            Route::post('/usage/settle', [UsageMeteringController::class, 'settle']);
            Route::post('/usage/release', [UsageMeteringController::class, 'release']);
            Route::get('/wallet', [UsageMeteringController::class, 'wallet']);
            Route::post('/wallet/recharge', [UsageMeteringController::class, 'recharge']);
            Route::get('/wallet/ledger', [UsageMeteringController::class, 'ledger']);
            Route::get('/wallet/verify-integrity', [UsageMeteringController::class, 'verifyIntegrity']);
            Route::get('/cost-protection', [UsageMeteringController::class, 'costProtection']);
            Route::put('/cost-protection', [UsageMeteringController::class, 'updateCostProtection']);
        });

        // HRM: Employees & Organization
        Route::get('/hr/dashboard', [EmployeeController::class, 'dashboard']);
        Route::get('/hr/hierarchy', [EmployeeController::class, 'hierarchy']);
        Route::get('/hr/reminders', [HrReminderController::class, 'getReminders']);
        Route::get('/hrm/reminders', [HrReminderController::class, 'getReminders']);
        Route::post('/employees/{id}/transfer', [EmployeeController::class, 'transfer']);
        Route::apiResource('employees', EmployeeController::class);

        // HRM: Employee Documents
        Route::get('/employees/{id}/documents', [EmployeeDocumentController::class, 'getDocuments']);
        Route::post('/employees/{id}/documents', [EmployeeDocumentController::class, 'storeDocument']);
        Route::post('/employees/{id}/documents/{docId}/verify', [EmployeeDocumentController::class, 'verifyDocument']);
        Route::delete('/employees/{id}/documents/{docId}', [EmployeeDocumentController::class, 'deleteDocument']);

        // HRM: Employee Productivity & Goals
        Route::get('/employees/{id}/productivity', [EmployeeProductivityController::class, 'getProductivity']);
        Route::get('/employees/{id}/reviews', [EmployeeProductivityController::class, 'getReviews']);
        Route::post('/employees/{id}/reviews', [EmployeeProductivityController::class, 'storeReview']);
        Route::get('/employees/{id}/goals', [EmployeeProductivityController::class, 'getGoals']);
        Route::post('/employees/{id}/goals', [EmployeeProductivityController::class, 'storeGoal']);

        // HRM: Payroll & Salary Structures
        Route::get('/payroll/structures', [PayrollController::class, 'getStructures']);
        Route::post('/payroll/structures', [PayrollController::class, 'storeStructure']);
        Route::get('/payroll/employees/{id}/salary', [PayrollController::class, 'getEmployeeSalary']);
        Route::post('/payroll/employees/{id}/salary', [PayrollController::class, 'assignSalary']);
        Route::get('/payroll/batches', [PayrollController::class, 'getBatches']);
        Route::post('/payroll/batches/generate', [PayrollController::class, 'generateBatch']);
        Route::get('/payroll/batches/{id}', [PayrollController::class, 'getBatch']);
        Route::post('/payroll/batches/{id}/approve', [PayrollController::class, 'approveBatch']);
        Route::post('/payroll/batches/{id}/disburse', [PayrollController::class, 'disburseBatch']);
        Route::get('/payroll/payslips', [PayrollController::class, 'getPayslips']);
        Route::get('/payroll/payslips/{id}', [PayrollController::class, 'getPayslip']);
        Route::post('/payroll/full-and-final/{employeeId}', [PayrollController::class, 'calculateFullAndFinal']);
        Route::post('/payroll/full-and-final', [PayrollController::class, 'calculateFullAndFinal']);

        // HRM: Departments
        Route::apiResource('departments', DepartmentController::class);

        // HRM: Shifts & Holidays
        Route::get('/shifts/holidays', [ShiftController::class, 'holidays']);
        Route::post('/shifts/holidays', [ShiftController::class, 'storeHoliday']);
        Route::delete('/shifts/holidays/{id}', [ShiftController::class, 'destroyHoliday']);
        Route::apiResource('shifts', ShiftController::class);

        // HRM: Attendance & Corrections
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::get('/attendance/calendar', [AttendanceController::class, 'calendar']);
        Route::post('/attendance/checkin', [AttendanceController::class, 'checkIn']);
        Route::post('/attendance/checkout', [AttendanceController::class, 'checkOut']);
        Route::get('/attendance/corrections', [AttendanceController::class, 'corrections']);
        Route::post('/attendance/corrections', [AttendanceController::class, 'requestCorrection']);
        Route::post('/attendance/corrections/{id}/review', [AttendanceController::class, 'reviewCorrection']);

        // HRM: Leaves & Balances
        Route::get('/leaves/types', [LeaveManagementController::class, 'types']);
        Route::post('/leaves/types', [LeaveManagementController::class, 'storeType']);
        Route::get('/leaves/balances/{employeeId?}', [LeaveManagementController::class, 'balances']);
        Route::post('/leaves/balances/adjust', [LeaveManagementController::class, 'adjustBalance']);
        Route::post('/leaves/{id}/approve', [LeaveManagementController::class, 'approve']);
        Route::post('/leaves/{id}/reject', [LeaveManagementController::class, 'reject']);
        Route::post('/leaves/{id}/cancel', [LeaveManagementController::class, 'cancel']);
        Route::apiResource('leaves', LeaveManagementController::class);

        // System Settings
        Route::get('/settings', [SystemSettingController::class, 'show']);
        Route::put('/settings', [SystemSettingController::class, 'update']);

        // Activity Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);

        // Advanced Enterprise RBAC, Custom Roles & Granular Data Permissions (Prompt 32)
        Route::prefix('roles')->group(function () {
            Route::get('/', [RolePermissionController::class, 'index']);
            Route::get('/metadata', [RolePermissionController::class, 'matrixMetadata']);
            Route::post('/', [RolePermissionController::class, 'store']);
            Route::post('/compare', [RolePermissionController::class, 'compare']);
            Route::post('/assign', [RolePermissionController::class, 'assignUser']);
            Route::post('/remove-user', [RolePermissionController::class, 'removeUser']);
            Route::get('/effective-permissions', [RolePermissionController::class, 'effectivePermissions']);
            Route::get('/{id}', [RolePermissionController::class, 'show']);
            Route::put('/{id}', [RolePermissionController::class, 'update']);
            Route::delete('/{id}', [RolePermissionController::class, 'destroy']);
            Route::post('/{id}/duplicate', [RolePermissionController::class, 'duplicate']);
            Route::post('/{id}/copy-from', [RolePermissionController::class, 'copyFrom']);
            Route::get('/{id}/users', [RolePermissionController::class, 'roleUsers']);
        });
        Route::get('/roles-permissions', [RolePermissionController::class, 'index']);

        // Complete AI-Powered CRM Assistant & Business Intelligence Engine (Prompt 35)
        Route::prefix('ai')->group(function () {
            // Providers & Settings
            Route::get('/providers', [AiAssistantController::class, 'getProviders']);
            Route::post('/providers', [AiAssistantController::class, 'updateProvider']);
            Route::post('/providers/ping', [AiAssistantController::class, 'pingProvider']);

            // Chat Assistant & Sessions
            Route::get('/sessions', [AiAssistantController::class, 'getSessions']);
            Route::get('/sessions/{id}/messages', [AiAssistantController::class, 'getSessionMessages']);
            Route::post('/chat', [AiAssistantController::class, 'askAssistant']);

            // Sales Intelligence & Intent Scoring
            Route::post('/sales/analyze', [AiAssistantController::class, 'analyzeSales']);
            Route::get('/sales/insights/{entityType}/{entityId}', [AiAssistantController::class, 'getSalesInsight']);

            // Action Execution Gate
            Route::get('/actions', [AiAssistantController::class, 'getProposedActions']);
            Route::post('/actions/{id}/confirm', [AiAssistantController::class, 'confirmAction']);
            Route::post('/actions/{id}/reject', [AiAssistantController::class, 'rejectAction']);

            // Meeting Intelligence
            Route::get('/meetings', [AiAssistantController::class, 'getMeetings']);
            Route::post('/meetings/process', [AiAssistantController::class, 'processMeeting']);

            // Document Intelligence
            Route::get('/documents', [AiAssistantController::class, 'getDocuments']);
            Route::post('/documents/upload', [AiAssistantController::class, 'uploadDocument']);
            Route::put('/documents/{id}/corrections', [AiAssistantController::class, 'updateDocumentCorrection']);
            Route::post('/documents/{id}/commit', [AiAssistantController::class, 'commitDocument']);

            // Content Generation
            Route::post('/content/generate', [AiAssistantController::class, 'generateContent']);

            // Usage & Admin Reports
            Route::get('/usage/reports', [AiAssistantController::class, 'getUsageReports']);
            Route::get('/usage/logs', [AiAssistantController::class, 'getRequestLogs']);
        });

        // Advanced Multi-Branch and Multi-Location Business Management (Prompt 45)
        Route::prefix('branches')->group(function () {
            Route::get('/', [BranchController::class, 'index']);
            Route::post('/', [BranchController::class, 'store']);
            Route::get('/analytics/consolidated', [BranchController::class, 'consolidated']);
            Route::post('/transfers', [BranchController::class, 'transfer']);
            Route::get('/transfers/history', [BranchController::class, 'transferHistory']);
            Route::get('/{id}', [BranchController::class, 'show']);
            Route::put('/{id}', [BranchController::class, 'update']);
            Route::delete('/{id}', [BranchController::class, 'destroy']);
            Route::patch('/{id}/status', [BranchController::class, 'setStatus']);
            Route::post('/{id}/employees', [BranchController::class, 'assignEmployees']);
            Route::get('/{id}/dashboard', [BranchController::class, 'dashboard']);
            Route::get('/{id}/settings', [BranchController::class, 'getSettings']);
            Route::put('/{id}/settings', [BranchController::class, 'updateSettings']);
            Route::match(['get', 'post'], '/{id}/sales-targets', [BranchController::class, 'salesTargets']);
        });

        // Customer Portal Administration & Bookable Services (Prompt 46)
        Route::prefix('portal-settings')->group(function () {
            Route::get('/', [PortalSettingController::class, 'show']);
            Route::put('/', [PortalSettingController::class, 'update']);
        });
        Route::post('/customers/{customer}/invite-portal', [PortalSettingController::class, 'inviteCustomer']);
        Route::apiResource('/bookable-services', BookableServiceController::class);
    });

    // --------------------------------------------------------------------
    // PROMPT 23: Dedicated Platform Super Admin Console Routes
    // --------------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'platform.admin'])->prefix('platform')->group(function () {
        // Platform Overview & KPIs
        Route::get('/overview', [PlatformOverviewController::class, 'index']);

        // Tenant Management & Breakglass Access
        Route::get('/tenants', [PlatformTenantController::class, 'index']);
        Route::get('/tenants/{id}', [PlatformTenantController::class, 'show']);
        Route::post('/tenants/{id}/status', [PlatformTenantController::class, 'updateStatus']);
        Route::post('/tenants/{id}/plan', [PlatformTenantController::class, 'changePlan']);
        Route::put('/tenants/{id}/limits', [PlatformTenantController::class, 'updateLimits']);
        Route::post('/tenants/{id}/support-access', [PlatformTenantController::class, 'breakglassSupportAccess']);
        Route::post('/support-access/{sessionId}/revoke', [PlatformTenantController::class, 'revokeSupportAccess']);

        // Plan & Commercial Billing Administration
        Route::get('/billing/plans', [PlatformBillingController::class, 'plans']);
        Route::post('/billing/plans', [PlatformBillingController::class, 'storePlan'])->middleware('platform.admin:billing');
        Route::put('/billing/plans/{id}', [PlatformBillingController::class, 'updatePlan'])->middleware('platform.admin:billing');
        Route::get('/billing/subscriptions', [PlatformBillingController::class, 'subscriptions']);
        Route::get('/billing/invoices', [PlatformBillingController::class, 'invoices']);
        Route::post('/billing/adjust-credits', [PlatformBillingController::class, 'adjustCredits'])->middleware('platform.admin:billing');

        // AI & Infrastructure Provider Administration
        Route::get('/providers', [PlatformProviderController::class, 'index']);
        Route::put('/providers/{key}', [PlatformProviderController::class, 'update'])->middleware('platform.admin:provider');
        Route::post('/providers/{key}/test-health', [PlatformProviderController::class, 'testHealth'])->middleware('platform.admin:provider');

        // System Management & Monitoring
        Route::get('/system/queues', [PlatformSystemController::class, 'queues']);
        Route::post('/system/queues/{id}/retry', [PlatformSystemController::class, 'retryQueueJob'])->middleware('platform.admin:ops');
        Route::post('/system/queues/purge', [PlatformSystemController::class, 'purgeQueueJobs'])->middleware('platform.admin:ops');
        Route::get('/system/feature-flags', [PlatformSystemController::class, 'featureFlags']);
        Route::put('/system/feature-flags/{key}', [PlatformSystemController::class, 'updateFeatureFlag'])->middleware('platform.admin:ops');
        Route::get('/system/maintenance', [PlatformSystemController::class, 'maintenanceMode']);
        Route::post('/system/maintenance', [PlatformSystemController::class, 'setMaintenanceMode'])->middleware('platform.admin:ops');
        Route::get('/system/audit-logs', [PlatformSystemController::class, 'auditLogs']);
    });

    // --------------------------------------------------------------------
    // PROMPT 50: Enterprise Multi-Tenant SaaS, Onboarding & Industry Templates
    // --------------------------------------------------------------------
    // Public Onboarding & Catalog
    Route::post('/saas/onboarding/register', [SaasOnboardingController::class, 'register']);
    Route::get('/saas/templates/catalog', [IndustryTemplateController::class, 'index']);

    // Tenant-Scoped SaaS Operations
    Route::middleware('auth:sanctum')->prefix('saas')->group(function () {
        Route::get('/templates', [IndustryTemplateController::class, 'index']);
        Route::get('/templates/current', [IndustryTemplateController::class, 'current']);
        Route::get('/templates/{code}', [IndustryTemplateController::class, 'show']);
        Route::post('/templates/apply', [IndustryTemplateController::class, 'apply']);

        Route::post('/onboarding/step', [SaasOnboardingController::class, 'saveStep']);
        Route::get('/onboarding/status', [SaasOnboardingController::class, 'status']);

        Route::get('/entitlements', [FeatureEntitlementController::class, 'index']);
        Route::post('/entitlements/verify', [FeatureEntitlementController::class, 'verify']);
    });

    // Platform Super Admin Console (Enterprise SaaS Management)
    Route::middleware(['auth:sanctum', 'platform.admin'])->prefix('platform/console')->group(function () {
        Route::get('/overview', [PlatformSuperAdminConsoleController::class, 'overview']);
        Route::get('/tenants', [PlatformSuperAdminConsoleController::class, 'tenants']);
        Route::post('/tenants/{id}/suspend', [PlatformSuperAdminConsoleController::class, 'suspendTenant']);
        Route::post('/tenants/{id}/reactivate', [PlatformSuperAdminConsoleController::class, 'reactivateTenant']);
        Route::post('/tenants/{id}/change-plan', [PlatformSuperAdminConsoleController::class, 'changeTenantPlan']);
        Route::get('/plans', [PlatformSuperAdminConsoleController::class, 'plans']);
        Route::post('/plans', [PlatformSuperAdminConsoleController::class, 'savePlan']);
        Route::get('/revenue', [PlatformSuperAdminConsoleController::class, 'revenueReport']);
        Route::get('/announcements', [PlatformSuperAdminConsoleController::class, 'announcements']);
        Route::post('/announcements', [PlatformSuperAdminConsoleController::class, 'createAnnouncement']);
        Route::get('/backups', [PlatformSuperAdminConsoleController::class, 'backups']);
        Route::post('/backups', [PlatformSuperAdminConsoleController::class, 'triggerBackup']);
        Route::get('/audit-logs', [PlatformSuperAdminConsoleController::class, 'auditLogs']);
    });

    // Public / Tenant broadcast announcements
    Route::middleware('auth:sanctum')->get('/platform/announcements', [PlatformSuperAdminConsoleController::class, 'announcements']);
};

// Register under /api/v1/... (Standard Versioned Path)
Route::prefix('v1')->group($registerCrmRoutes);

// Register direct aliases under /api/... for backward compatibility with frontend
$registerCrmRoutes();
