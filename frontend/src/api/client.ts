/**
 * Enterprise AI CRM SaaS - Strongly Typed API Client
 * Seamlessly interfaces React 19 Frontend with Laravel 12 Backend
 */

import {
  ApiResponse,
  ApiErrorResponse,
  HealthCheckData,
  AuthLoginResponse,
  NotificationItem,
  NotificationPreference,
  MediaFileItem,
  CustomFieldDefinition,
  CustomFieldValue,
  SearchResultItem,
  AuditLogEntry,
  LeadItem,
  CrmActivityItem,
  Customer360Profile,
  DuplicateMatch,
  LeadConversionPayload,
  DealItem,
  PipelineItem,
  PipelineStageItem,
  DealForecastData,
  StaleDealItem,
  TaskItem,
  SalesTeamItem,
  SalesQuotaItem,
  SalesLeaderboardData,
  ProductCatalogItem,
  ProductCategoryItem,
  ProductVariantItem,
  WarehouseItem,
  ProductStockItem,
  StockMovementItem,
  SupplierItem,
  PurchaseOrderRecord,
  InventoryValuationData,
  LowStockAlertItem,
  QuotationRecord,
  QuotationItemRecord,
  InvoiceRecord,
  InvoiceItemRecord,
  PaymentRecord,
  RefundRecord,
  FinanceSummaryData,
  WhatsAppAccountItem,
  WhatsAppPhoneNumberItem,
  WhatsAppConversationItem,
  WhatsAppMessageItem,
  WhatsAppConversationNoteItem,
  WhatsAppTemplateItem,
  WhatsAppCampaignItem,
  WhatsAppCampaignRecipientItem,
  WhatsAppAutomationItem,
  WhatsAppAutomationLogItem,
  WhatsAppUsageRecordItem,
  WhatsAppUsageReportsData,
  WhatsAppIntegrationLogItem,
  WhatsAppAiAgentItem,
  WhatsAppAiAgentVersionItem,
  WhatsAppKnowledgeBaseItem,
  WhatsAppKnowledgeChunkItem,
  WhatsAppAiProviderItem,
  WhatsAppAiExecutionLogItem,
  VoiceAiAgentItem,
  VoiceCallItem,
  VoiceCallingCampaignItem,
  TelephonyProviderItem,
  TelephonyPhoneNumberItem,
  CallIvrFlowItem,
  CallQueueItem,
  CallQualityReviewItem,
  CallerProfileLookupData,
  CallReportingAnalyticsData,
  TelephonyProviderLogItem,
  OmnichannelConversationItem,
  OmnichannelMessageItem,
  ConversationAssignmentLogItem,
  OmnichannelQuickReplyItem,
  CustomerCommunicationConsentItem,
  OmnichannelTimelineItem,
  OmnichannelAnalyticsData,
  TelegramAccountItem,
  OmnichannelChannelSettingItem,
  OmnichannelRoutingRuleItem,
  OmnichannelSlaPolicyItem,
  OmnichannelAgentPresenceItem,
  OmnichannelDetailedReportsData,
  EmailAccountItem,
  SmsConfigurationItem,
  WebsiteChatWidgetItem,
  CommunicationSuppressionItem,
  IdentityMergePendingItem,
  UnifiedTimelineItem,
  WorkflowItem,
  WorkflowVersionItem,
  WorkflowRunItem,
  WorkflowStepLogItem,
  WorkflowTemplateItem,
  WorkflowSimulationResult,
  WorkflowDefinition,
  WorkflowApprovalItem,
  WorkflowAutomationLimitItem,
  WorkflowMetricsItem,
  OwnerDashboardMetrics,
  DashboardWidgetLayoutItem,
  CustomReportModuleCatalog,
  CustomReportPreviewResult,
  CustomAnalyticsAlertItem,
  FieldSalesVisitItem,
  FieldSalesDailyPlan,
  FieldSalesAnalytics,
  AiBusinessInsightsItem,
  IndiaSettingsItem,
  IndustryTemplateItem,
  TenantAppliedTemplateItem,
  FeatureEntitlementCatalog,
  FeatureEntitlementVerification,
  PlatformConsoleOverview,
  PlatformTenantItem,
  PlatformPlanItem,
  PlatformAnnouncementItem,
  PlatformBackupRecordItem,
  PlatformRevenueReport,
  SaasOnboardingRegisterPayload,
  SaasOnboardingStepPayload,
  CustomDomainItem,
  LandingPageItem,
  LandingPageRevisionItem,
  LandingPageTemplateItem,
  LandingPageAnalyticsData,
  FormItem,
  FormSubmissionItem,
  MarketingSegmentItem,
  MarketingCampaignItem,
  MarketingTemplateItem,
  MarketingConsentItem,
  MarketingOverviewSummary,
  CampaignApprovalItem,
  CampaignAnalyticsData,
  DripSequenceItem,
  DripEnrollmentItem,
  DepartmentItem,
  ShiftItem,
  CompanyHolidayItem,
  LeaveTypeItem,
  LeaveBalanceItem,
  LeaveItem,
  AttendanceItem,
  AttendanceCorrectionItem,
  EmployeeItem,
  SalaryStructureItem,
  EmployeeSalaryItem,
  PayrollBatchItem,
  PayslipItem,
  EmployeeDocumentItem,
  EmployeePerformanceReviewItem,
  EmployeeProductivityGoalItem,
  HrReminderItem,
  MobileCheckInData,
  FullAndFinalSettlementResult,
  HrDashboardMetrics,
  ProjectItem,
  ProjectMilestoneItem,
  ProjectCommentItem,
  ProjectTeamMember,
  ProjectMetricsData,
  TaskDependencyItem,
  TaskTimeLogItem,
  TaskMetricsData,
  MeetingItem,
  MeetingAttendeeItem,
  SupportTicketItem,
  SupportTicketMessageItem,
  SupportTicketCategoryItem,
  SupportSlaPolicyItem,
  SupportAnalyticsData,
  SupportAiAssistanceData,
  ExecutiveAnalyticsData,
  LeadAnalyticsData,
  SalesAnalyticsData,
  AttributionComparisonData,
  SavedReportItem,
  ReportExportResult,
  IntegrationDirectoryItem,
  IntegrationConnectionItem,
  IntegrationSyncLogItem,
  TenantApiKeyItem,
  TenantApiRequestLogItem,
  OutboundWebhookItem,
  WebhookDeliveryLogItem,
  SubscriptionPlanItem,
  TenantSubscriptionItem,
  UsageDashboardData,
  UsageReservationItem,
  UsageRecordItem,
  CreditWalletData,
  CreditLedgerEntryItem,
  CostProtectionRuleItem,
  SubscriptionInvoiceItem,
  PlatformOverviewData,
  PlatformTenantItem,
  PlatformTenantDetailData,
  PlatformSupportSessionItem,
  PlatformProviderItem,
  PlatformFeatureFlagItem,
  PlatformMaintenanceModeData,
  PlatformAuditLogItem,
  PlatformQueueStatsData,
  TrackingSettingData,
  TrackingEventItem,
  TrackingDiagnosticsData,
  GA4ReportData,
  SocialChannelAccountItem,
  CustomerSocialIdentityItem,
  SocialWebhookEventItem,
  SocialDiagnosticsData,
  LeadRoutingRuleItem,
  LeadSlaPolicyItem,
  LeadEscalationLogItem,
  LeadFollowupItem,
  LeadStageHistoryItem,
  LeadMergeRequestItem,
  LeadPortalIntegrationItem,
  LeadScoringConfigurationItem,
  LeadPerformanceDashboardData,
  ContractTemplateItem,
  ContractItem,
  DiscountRuleItem,
  DiscountApprovalItem,
  CommissionPlanItem,
  SalesCommissionItem,
  AdvancedSalesForecastData,
  GstRegistrationItem,
  GstVerificationResult,
  GstTaxCalculationResult,
  GstTaxSummaryReport,
  Gstr1SalesRegister,
  HsnSacSummaryItem,
  ComplianceReminderItem,
  ComplianceDocumentItem,
  SupportTicketTransitionItem,
  CustomerFeedbackItem,
  CustomerHealthScoreItem,
  WarrantyContractItem,
  KnowledgeCategoryItem,
  KnowledgeArticleItem,
  AiAnswerSuggestion,
  BranchItem,
  BranchTransferItem,
  BranchSalesTargetItem,
  BranchDashboardMetrics,
  ConsolidatedBranchAnalytics,
  CustomerAccountItem,
  CustomerLoginHistoryItem,
  PortalSettingItem,
  BookableServiceItem,
  CustomerDashboardData,
  PublicBookingConfig,
  PublicBookingSlot,
} from './types';

const memoryStore: Record<string, string> = {};

const storage = {
  getItem: (key: string): string | null => {
    try {
      if (typeof window !== 'undefined' && window.localStorage) {
        return window.localStorage.getItem(key);
      }
    } catch {}
    return memoryStore[key] ?? null;
  },
  setItem: (key: string, value: string): void => {
    try {
      if (typeof window !== 'undefined' && window.localStorage) {
        window.localStorage.setItem(key, value);
      }
    } catch {}
    memoryStore[key] = value;
  },
  removeItem: (key: string): void => {
    try {
      if (typeof window !== 'undefined' && window.localStorage) {
        window.localStorage.removeItem(key);
      }
    } catch {}
    delete memoryStore[key];
  },
};

class ApiClientError extends Error {
  statusCode: number;
  errors?: Record<string, string[]>;

  constructor(message: string, statusCode: number, errors?: Record<string, string[]>) {
    super(message);
    this.name = 'ApiClientError';
    this.statusCode = statusCode;
    this.errors = errors;
  }
}

class ApiClient {
  private baseURL: string;

  constructor() {
    const envUrl = (import.meta as any).env?.VITE_API_URL;
    if (envUrl) {
      this.baseURL = `${envUrl}/api/v1`;
    } else if (typeof window !== 'undefined') {
      this.baseURL = '/api/v1';
    } else {
      this.baseURL = 'http://127.0.0.1:8000/api/v1';
    }
  }

  private async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const token = storage.getItem('crm_token');
    const activeWorkspaceId = storage.getItem('crm_workspace_id');

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      ...((options.headers as Record<string, string>) || {}),
    };

    // Only set Content-Type to application/json when not using FormData
    if (!(options.body instanceof FormData) && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    if (activeWorkspaceId) {
      headers['X-Workspace-Id'] = activeWorkspaceId;
    }

    const url = endpoint.startsWith('http') ? endpoint : `${this.baseURL}${endpoint}`;

    const res = await fetch(url, {
      ...options,
      headers,
    });

    const body = await res.json().catch(() => ({}));

    if (!res.ok) {
      const errRes = body as ApiErrorResponse;
      throw new ApiClientError(
        errRes.message || `Request failed with status ${res.status}`,
        res.status,
        errRes.errors
      );
    }

    return body as T;
  }

  // --- HEALTH CHECK ---
  async getHealth(): Promise<ApiResponse<HealthCheckData>> {
    return this.request<ApiResponse<HealthCheckData>>('/health');
  }

  // --- AUTHENTICATION ---
  async login(email: string, password: string): Promise<AuthLoginResponse> {
    const res = await this.request<any>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });

    if (res.data?.token) {
      storage.setItem('crm_token', res.data.token);
      storage.setItem('crm_user', JSON.stringify(res.data.user));
      if (res.data.activeWorkspace?.id) {
        storage.setItem('crm_workspace_id', String(res.data.activeWorkspace.id));
      }
    }

    return res;
  }

  async verify2FA(userId: string, code: string): Promise<AuthLoginResponse> {
    const res = await this.request<ApiResponse<{ token: string; user: any; activeWorkspace?: any }>>('/auth/verify-2fa', {
      method: 'POST',
      body: JSON.stringify({ user_id: userId, code }),
    });

    if (res.data?.token) {
      storage.setItem('crm_token', res.data.token);
      storage.setItem('crm_user', JSON.stringify(res.data.user));
      if (res.data.activeWorkspace?.id) {
        storage.setItem('crm_workspace_id', String(res.data.activeWorkspace.id));
      }
    }

    return res as any;
  }

  async register(name: string, email: string, password: string, companyName: string): Promise<any> {
    return this.request('/auth/register', {
      method: 'POST',
      body: JSON.stringify({ name, email, password, company_name: companyName }),
    });
  }

  async getProfile(): Promise<any> {
    return this.request('/auth/profile');
  }

  async logout(): Promise<void> {
    try {
      await this.request('/auth/logout', { method: 'POST' });
    } finally {
      storage.removeItem('crm_token');
      storage.removeItem('crm_user');
      storage.removeItem('crm_workspace_id');
    }
  }

  // --- MULTI-TENANT WORKSPACES ---
  async getWorkspaces(): Promise<ApiResponse<any[]>> {
    return this.request<ApiResponse<any[]>>('/workspaces');
  }

  async getCurrentWorkspace(): Promise<ApiResponse<any>> {
    return this.request<ApiResponse<any>>('/workspaces/current');
  }

  async switchWorkspace(workspaceId: string | number): Promise<ApiResponse<any>> {
    const res = await this.request<ApiResponse<any>>('/workspaces/switch', {
      method: 'POST',
      body: JSON.stringify({ workspace_id: workspaceId }),
    });
    storage.setItem('crm_workspace_id', String(workspaceId));
    return res;
  }

  async createWorkspace(name: string, subscriptionTier = 'starter'): Promise<ApiResponse<any>> {
    const res = await this.request<ApiResponse<any>>('/workspaces', {
      method: 'POST',
      body: JSON.stringify({ name, subscription_tier: subscriptionTier }),
    });
    if (res.data?.id) {
      storage.setItem('crm_workspace_id', String(res.data.id));
    }
    return res;
  }

  async updateWorkspaceSettings(settings: Record<string, any>): Promise<ApiResponse<any>> {
    return this.request<ApiResponse<any>>('/workspaces/settings', {
      method: 'PUT',
      body: JSON.stringify(settings),
    });
  }

  async getWorkspaceMembers(): Promise<ApiResponse<any[]>> {
    return this.request<ApiResponse<any[]>>('/workspaces/members');
  }

  async inviteMember(email: string, role: string): Promise<ApiResponse<any>> {
    return this.request<ApiResponse<any>>('/workspaces/invitations', {
      method: 'POST',
      body: JSON.stringify({ email, role }),
    });
  }

  async getLoginHistory(): Promise<ApiResponse<any[]>> {
    return this.request<ApiResponse<any[]>>('/auth/login-history');
  }

  // --- CRM MODULES ---
  async getRecords<T = any>(module: string, params: Record<string, any> = {}): Promise<ApiResponse<T[]>> {
    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, val]) => {
      if (val !== undefined && val !== null && val !== '') {
        searchParams.append(key, String(val));
      }
    });
    const queryString = searchParams.toString() ? `?${searchParams.toString()}` : '';
    return this.request<ApiResponse<T[]>>(`/crm/${module}${queryString}`);
  }

  async createRecord<T = any>(module: string, data: any, idempotencyKey?: string): Promise<ApiResponse<T>> {
    const headers: Record<string, string> = {};
    if (idempotencyKey) {
      headers['Idempotency-Key'] = idempotencyKey;
    }
    return this.request<ApiResponse<T>>(`/crm/${module}`, {
      method: 'POST',
      headers,
      body: JSON.stringify(data),
    });
  }

  async updateRecord<T = any>(module: string, id: string | number, data: any): Promise<ApiResponse<T>> {
    return this.request<ApiResponse<T>>(`/crm/${module}/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteRecord(module: string, id: string | number): Promise<ApiResponse<null>> {
    return this.request<ApiResponse<null>>(`/crm/${module}/${id}`, {
      method: 'DELETE',
    });
  }

  async bulkDeleteRecords(module: string, ids: (string | number)[]): Promise<ApiResponse<null>> {
    return this.request<ApiResponse<null>>(`/crm/${module}/bulk-delete`, {
      method: 'POST',
      body: JSON.stringify({ ids }),
    });
  }

  async getReports(): Promise<any> {
    return this.request('/crm/reports');
  }

  // --- NOTIFICATION CENTER ---
  async getNotifications(unreadOnly = false, page = 1, perPage = 20): Promise<ApiResponse<NotificationItem[]>> {
    const q = new URLSearchParams({
      page: String(page),
      per_page: String(perPage),
      ...(unreadOnly ? { unread_only: '1' } : {}),
    });
    return this.request<ApiResponse<NotificationItem[]>>(`/notifications?${q.toString()}`);
  }

  async getUnreadNotificationCount(): Promise<ApiResponse<{ unread_count: number }>> {
    return this.request<ApiResponse<{ unread_count: number }>>('/notifications/unread-count');
  }

  async markNotificationRead(id: string | number): Promise<ApiResponse<any>> {
    return this.request<ApiResponse<any>>(`/notifications/${id}/read`, {
      method: 'POST',
    });
  }

  async markAllNotificationsRead(): Promise<ApiResponse<{ marked_count: number }>> {
    return this.request<ApiResponse<{ marked_count: number }>>('/notifications/mark-all-read', {
      method: 'POST',
    });
  }

  async getNotificationPreferences(): Promise<ApiResponse<NotificationPreference[]>> {
    return this.request<ApiResponse<NotificationPreference[]>>('/notifications/preferences');
  }

  async updateNotificationPreferences(preferences: Array<{
    notification_type: string;
    in_app_enabled?: boolean;
    email_enabled?: boolean;
    push_enabled?: boolean;
  }>): Promise<ApiResponse<NotificationPreference[]>> {
    return this.request<ApiResponse<NotificationPreference[]>>('/notifications/preferences', {
      method: 'PUT',
      body: JSON.stringify({ preferences }),
    });
  }

  async dispatchTestNotification(type = 'system.announcement'): Promise<ApiResponse<any>> {
    return this.request<ApiResponse<any>>('/notifications/test', {
      method: 'POST',
      body: JSON.stringify({ type }),
    });
  }

  // --- FILE & MEDIA MANAGER ---
  async getMediaFiles(params: {
    mime_type?: string;
    fileable_type?: string;
    fileable_id?: string | number;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<MediaFileItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request<ApiResponse<MediaFileItem[]>>(`/media${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async uploadMediaFile(formData: FormData): Promise<ApiResponse<MediaFileItem>> {
    return this.request<ApiResponse<MediaFileItem>>('/media', {
      method: 'POST',
      body: formData,
    });
  }

  async getMediaFile(uuid: string): Promise<ApiResponse<MediaFileItem>> {
    return this.request<ApiResponse<MediaFileItem>>(`/media/${uuid}`);
  }

  async deleteMediaFile(uuid: string): Promise<ApiResponse<null>> {
    return this.request<ApiResponse<null>>(`/media/${uuid}`, {
      method: 'DELETE',
    });
  }

  // --- CUSTOM FIELDS & METADATA ---
  async getCustomFieldDefinitions(entityType?: string): Promise<ApiResponse<CustomFieldDefinition[]>> {
    const q = entityType ? `?entity_type=${entityType}` : '';
    return this.request<ApiResponse<CustomFieldDefinition[]>>(`/custom-fields${q}`);
  }

  async createCustomFieldDefinition(data: Partial<CustomFieldDefinition>): Promise<ApiResponse<CustomFieldDefinition>> {
    return this.request<ApiResponse<CustomFieldDefinition>>('/custom-fields', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateCustomFieldDefinition(id: number | string, data: Partial<CustomFieldDefinition>): Promise<ApiResponse<CustomFieldDefinition>> {
    return this.request<ApiResponse<CustomFieldDefinition>>(`/custom-fields/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteCustomFieldDefinition(id: number | string): Promise<ApiResponse<null>> {
    return this.request<ApiResponse<null>>(`/custom-fields/${id}`, {
      method: 'DELETE',
    });
  }

  async getEntityCustomFieldValues(entityType: string, entityId: number | string): Promise<ApiResponse<{ fields: CustomFieldDefinition[]; values: Record<string, any> }>> {
    return this.request<ApiResponse<{ fields: CustomFieldDefinition[]; values: Record<string, any> }>>(`/custom-fields/values/${entityType}/${entityId}`);
  }

  async saveEntityCustomFieldValues(entityType: string, entityId: number | string, fields: Record<string, any>): Promise<ApiResponse<any>> {
    return this.request<ApiResponse<any>>(`/custom-fields/values/${entityType}/${entityId}`, {
      method: 'POST',
      body: JSON.stringify({ fields }),
    });
  }

  // --- GLOBAL UNIFIED SEARCH ---
  async globalSearch(query: string, module?: string, limit = 20): Promise<ApiResponse<SearchResultItem[]>> {
    const q = new URLSearchParams({
      q: query,
      limit: String(limit),
      ...(module ? { module } : {}),
    });
    return this.request<ApiResponse<SearchResultItem[]>>(`/search?${q.toString()}`);
  }

  // --- AUDIT LOGS & DIFFS ---
  async getAuditLogs(params: {
    action?: string;
    user_id?: string | number;
    date_from?: string;
    date_to?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<AuditLogEntry[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request<ApiResponse<AuditLogEntry[]>>(`/audit-logs${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getAuditLog(id: number | string): Promise<ApiResponse<AuditLogEntry>> {
    return this.request<ApiResponse<AuditLogEntry>>(`/audit-logs/${id}`);
  }

  // --- CRM CORE: LEADS LIFECYCLE & OPERATIONS ---

  async checkLeadDuplicates(params: { email?: string; phone?: string; company_name?: string; name?: string }, ignoreId?: number | string): Promise<ApiResponse<{ has_duplicates: boolean; count: number; duplicates: DuplicateMatch[] }>> {
    return this.request('/crm/leads/check-duplicates', {
      method: 'POST',
      body: JSON.stringify({ ...params, ignore_id: ignoreId }),
    });
  }

  async mergeLead(masterId: number | string, duplicateId: number | string, fieldOverrides: Record<string, any> = {}): Promise<ApiResponse<LeadItem>> {
    return this.request(`/crm/leads/${masterId}/merge`, {
      method: 'POST',
      body: JSON.stringify({ duplicate_id: duplicateId, field_overrides: fieldOverrides }),
    });
  }

  async assignLead(leadId: number | string, userId: number | string, reason = 'Direct assignment'): Promise<ApiResponse<LeadItem>> {
    return this.request(`/crm/leads/${leadId}/assign`, {
      method: 'POST',
      body: JSON.stringify({ assigned_user_id: userId, reason }),
    });
  }

  async autoDistributeLeadsRoundRobin(): Promise<ApiResponse<{ distributed_count: number; assignments?: any[] }>> {
    return this.request('/crm/leads/auto-distribute-round-robin', {
      method: 'POST',
    });
  }

  async convertLead(leadId: number | string, payload: LeadConversionPayload): Promise<ApiResponse<{ lead: LeadItem; contact: any; company: any; deal: any; customer: any }>> {
    return this.request(`/crm/leads/${leadId}/convert`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async archiveLead(leadId: number | string): Promise<ApiResponse<LeadItem>> {
    return this.request(`/crm/leads/${leadId}/archive`, {
      method: 'POST',
    });
  }

  async unarchiveLead(leadId: number | string): Promise<ApiResponse<LeadItem>> {
    return this.request(`/crm/leads/${leadId}/unarchive`, {
      method: 'POST',
    });
  }

  async recalculateLeadScore(leadId: number | string): Promise<ApiResponse<{ score: number; score_breakdown: any; qualification_status: string }>> {
    return this.request(`/crm/leads/${leadId}/recalculate-score`, {
      method: 'POST',
    });
  }

  async exportLeadsCsv(): Promise<string> {
    const token = storage.getItem('crm_token');
    const workspaceId = storage.getItem('crm_workspace_id');
    const headers: Record<string, string> = {};
    if (token) headers['Authorization'] = `Bearer ${token}`;
    if (workspaceId) headers['X-Workspace-Id'] = workspaceId;

    const res = await fetch(`${this.baseURL}/crm/leads/export`, { headers });
    return res.text();
  }

  async importLeadsCsv(csvContent: string): Promise<ApiResponse<{ imported_count: number; skipped_count: number; errors: string[] }>> {
    return this.request('/crm/leads/import', {
      method: 'POST',
      body: JSON.stringify({ csv_content: csvContent }),
    });
  }

  // --- CRM CORE: CUSTOMER 360 & CHRONOLOGICAL ACTIVITIES ---

  async getCustomer360(customerId: number | string): Promise<ApiResponse<Customer360Profile>> {
    return this.request(`/crm/customers/${customerId}/360`);
  }

  async getActivities(params: {
    lead_id?: number | string;
    contact_id?: number | string;
    company_id?: number | string;
    deal_id?: number | string;
    customer_id?: number | string;
    activity_type?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<CrmActivityItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/activities${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async logActivity(data: {
    activity_type: string;
    title: string;
    description?: string;
    metadata?: Record<string, any>;
    lead_id?: number | string;
    contact_id?: number | string;
    company_id?: number | string;
    deal_id?: number | string;
    customer_id?: number | string;
    scheduled_at?: string;
    completed_at?: string;
  }): Promise<ApiResponse<CrmActivityItem>> {
    return this.request('/crm/activities', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async deleteActivity(id: number | string): Promise<ApiResponse<null>> {
    return this.request(`/crm/activities/${id}`, {
      method: 'DELETE',
    });
  }

  // --- LEAD ACQUISITION HUB METHODS ---

  async getLeadSources(): Promise<ApiResponse<{ sources: any[]; supported_providers: any[] }>> {
    return this.request('/crm/lead-sources');
  }

  async createLeadSource(data: {
    provider: string;
    name: string;
    account_id?: string;
    credentials?: Record<string, any>;
    config?: Record<string, any>;
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/lead-sources', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateLeadSource(id: number | string, data: Record<string, any>): Promise<ApiResponse<any>> {
    return this.request(`/crm/lead-sources/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteLeadSource(id: number | string): Promise<ApiResponse<any>> {
    return this.request(`/crm/lead-sources/${id}`, {
      method: 'DELETE',
    });
  }

  async testLeadSource(id: number | string): Promise<ApiResponse<any>> {
    return this.request(`/crm/lead-sources/${id}/test`, {
      method: 'POST',
    });
  }

  async syncLeadSourceMetrics(id: number | string): Promise<ApiResponse<any>> {
    return this.request(`/crm/lead-sources/${id}/sync-metrics`, {
      method: 'POST',
    });
  }

  async getLeadAcquisitionStats(): Promise<ApiResponse<any>> {
    return this.request('/crm/lead-sources/stats');
  }

  async getLeadForms(): Promise<ApiResponse<any[]>> {
    return this.request('/crm/lead-forms');
  }

  async getLeadForm(id: number | string): Promise<ApiResponse<any>> {
    return this.request(`/crm/lead-forms/${id}`);
  }

  async createLeadForm(data: {
    name: string;
    provider?: string;
    lead_source_id?: number | null;
    fields_config?: any[];
    campaign_name?: string;
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/lead-forms', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateLeadForm(id: number | string, data: Record<string, any>): Promise<ApiResponse<any>> {
    return this.request(`/crm/lead-forms/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteLeadForm(id: number | string): Promise<ApiResponse<any>> {
    return this.request(`/crm/lead-forms/${id}`, {
      method: 'DELETE',
    });
  }

  async getLeadFieldMappings(provider?: string): Promise<ApiResponse<{ mappings: any[]; available_crm_fields: any[] }>> {
    const q = provider ? `?provider=${encodeURIComponent(provider)}` : '';
    return this.request(`/crm/lead-field-mappings${q}`);
  }

  async saveLeadFieldMapping(data: {
    provider: string;
    source_field: string;
    crm_field: string;
    transform_rule?: string;
    default_value?: string;
    is_required?: boolean;
    lead_source_id?: number | null;
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/lead-field-mappings', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async deleteLeadFieldMapping(id: number | string): Promise<ApiResponse<any>> {
    return this.request(`/crm/lead-field-mappings/${id}`, {
      method: 'DELETE',
    });
  }

  async getLeadIngestionEvents(params: {
    status?: string;
    provider?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<{ data: any[]; total: number; current_page: number; last_page: number }>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/lead-ingestion-events${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async retryLeadIngestionEvent(id: number | string): Promise<ApiResponse<any>> {
    return this.request(`/crm/lead-ingestion-events/${id}/retry`, {
      method: 'POST',
    });
  }

  async getAdCampaignMetrics(params: {
    provider?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<{ metrics: { data: any[] }; summary: any }>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/ad-campaign-metrics${q.toString() ? `?${q.toString()}` : ''}`);
  }

  // --- SALES PIPELINE, DEALS & FORECAST ---
  async getPipelines(): Promise<ApiResponse<PipelineItem[]>> {
    return this.request('/crm/pipelines');
  }

  async getPipeline(id: number): Promise<ApiResponse<PipelineItem>> {
    return this.request(`/crm/pipelines/${id}`);
  }

  async createPipeline(data: { name: string; description?: string; is_default?: boolean }): Promise<ApiResponse<PipelineItem>> {
    return this.request('/crm/pipelines', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updatePipeline(id: number, data: Partial<PipelineItem>): Promise<ApiResponse<PipelineItem>> {
    return this.request(`/crm/pipelines/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deletePipeline(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/pipelines/${id}`, {
      method: 'DELETE',
    });
  }

  async createPipelineStage(pipelineId: number, data: {
    name: string;
    probability: number;
    color?: string;
    order_column?: number;
    is_won?: boolean;
    is_lost?: boolean;
    required_fields?: string[];
    stale_days_threshold?: number;
  }): Promise<ApiResponse<PipelineStageItem>> {
    return this.request(`/crm/pipelines/${pipelineId}/stages`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updatePipelineStage(stageId: number, data: Partial<PipelineStageItem>): Promise<ApiResponse<PipelineStageItem>> {
    return this.request(`/crm/pipelines/stages/${stageId}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deletePipelineStage(stageId: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/pipelines/stages/${stageId}`, {
      method: 'DELETE',
    });
  }

  async getDeals(params: {
    pipeline_id?: number;
    stage_id?: number;
    stage?: string;
    customer_id?: number;
    assigned_user_id?: number;
    search?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<DealItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/deals${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getDeal(dealId: number): Promise<ApiResponse<DealItem>> {
    return this.request(`/crm/deals/${dealId}`);
  }

  async createDeal(data: Partial<DealItem>): Promise<ApiResponse<DealItem>> {
    return this.request('/crm/deals', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateDeal(dealId: number, data: Partial<DealItem>): Promise<ApiResponse<DealItem>> {
    return this.request(`/crm/deals/${dealId}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteDeal(dealId: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/deals/${dealId}`, {
      method: 'DELETE',
    });
  }

  async moveDealToStage(dealId: number, data: {
    pipeline_stage_id?: number;
    stage?: string;
    notes?: string;
    competitor?: string;
    lost_reason?: string;
    value?: number;
    expected_close_date?: string;
  }): Promise<ApiResponse<DealItem>> {
    return this.request(`/crm/deals/${dealId}/move-stage`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async addDealProduct(dealId: number, data: {
    product_id?: number;
    name: string;
    quantity: number;
    unit_price: number;
    discount?: number;
  }): Promise<ApiResponse<DealItem>> {
    return this.request(`/crm/deals/${dealId}/products`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async removeDealProduct(dealId: number, productId: number): Promise<ApiResponse<DealItem>> {
    return this.request(`/crm/deals/${dealId}/products/${productId}`, {
      method: 'DELETE',
    });
  }

  async getDealForecast(params: { pipeline_id?: number; start_date?: string; end_date?: string } = {}): Promise<ApiResponse<DealForecastData>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/deals/forecast${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getStaleDeals(days?: number): Promise<ApiResponse<StaleDealItem[]>> {
    const q = days ? `?days=${days}` : '';
    return this.request(`/crm/deals/stale${q}`);
  }

  async archiveDeal(dealId: number): Promise<ApiResponse<DealItem>> {
    return this.request(`/crm/deals/${dealId}/archive`, { method: 'POST' });
  }

  async unarchiveDeal(dealId: number): Promise<ApiResponse<DealItem>> {
    return this.request(`/crm/deals/${dealId}/unarchive`, { method: 'POST' });
  }

  // --- TASKS & FOLLOW-UPS ---
  async getTasks(params: {
    view?: 'my_tasks' | 'team_tasks' | 'today' | 'upcoming' | 'overdue' | 'completed';
    status?: string;
    priority?: string;
    deal_id?: number;
    lead_id?: number;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<TaskItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/tasks${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createTask(data: Partial<TaskItem>): Promise<ApiResponse<TaskItem>> {
    return this.request('/crm/tasks', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateTask(id: number, data: Partial<TaskItem>): Promise<ApiResponse<TaskItem>> {
    return this.request(`/crm/tasks/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteTask(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/tasks/${id}`, { method: 'DELETE' });
  }

  async completeTask(id: number): Promise<ApiResponse<TaskItem>> {
    return this.request(`/crm/tasks/${id}/complete`, { method: 'POST' });
  }

  async toggleTaskChecklist(taskId: number, itemId: string, completed: boolean): Promise<ApiResponse<TaskItem>> {
    return this.request(`/crm/tasks/${taskId}/checklist/${itemId}/toggle`, {
      method: 'POST',
      body: JSON.stringify({ completed }),
    });
  }

  // --- SALES TEAMS, QUOTAS & LEADERBOARD ---
  async getSalesTeams(): Promise<ApiResponse<SalesTeamItem[]>> {
    return this.request('/crm/sales-teams');
  }

  async createSalesTeam(data: { name: string; leader_id?: number; member_ids?: number[]; description?: string }): Promise<ApiResponse<SalesTeamItem>> {
    return this.request('/crm/sales-teams', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateSalesTeam(id: number, data: Partial<SalesTeamItem>): Promise<ApiResponse<SalesTeamItem>> {
    return this.request(`/crm/sales-teams/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteSalesTeam(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/sales-teams/${id}`, { method: 'DELETE' });
  }

  async getSalesLeaderboard(params: { period?: 'monthly' | 'quarterly' | 'yearly'; team_id?: number } = {}): Promise<ApiResponse<SalesLeaderboardData>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/sales-teams/leaderboard${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getSalesQuotas(params: { team_id?: number; period?: string } = {}): Promise<ApiResponse<SalesQuotaItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/sales-teams/quotas${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async saveSalesQuota(data: {
    sales_team_id?: number;
    user_id?: number;
    period: string;
    start_date: string;
    end_date: string;
    target_revenue: number;
    target_deals_count?: number;
    target_leads_count?: number;
  }): Promise<ApiResponse<SalesQuotaItem>> {
    return this.request('/crm/sales-teams/quotas', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // --- PRODUCT & SERVICE CATALOG ---
  async getCatalog(params: {
    type?: 'product' | 'service';
    category_id?: number;
    status?: string;
    search?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<ProductCatalogItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/catalog${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createCatalogItem(data: Partial<ProductCatalogItem> & { opening_stock?: number; warehouse_id?: number }): Promise<ApiResponse<ProductCatalogItem>> {
    return this.request('/crm/catalog', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateCatalogItem(id: number, data: Partial<ProductCatalogItem>): Promise<ApiResponse<ProductCatalogItem>> {
    return this.request(`/crm/catalog/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteCatalogItem(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/catalog/${id}`, { method: 'DELETE' });
  }

  async getCatalogCategories(): Promise<ApiResponse<ProductCategoryItem[]>> {
    return this.request('/crm/catalog/categories');
  }

  async createCatalogCategory(data: { name: string; parent_id?: number; description?: string }): Promise<ApiResponse<ProductCategoryItem>> {
    return this.request('/crm/catalog/categories', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async createProductVariant(productId: number, data: Partial<ProductVariantItem>): Promise<ApiResponse<ProductVariantItem>> {
    return this.request(`/crm/catalog/${productId}/variants`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // --- INVENTORY MANAGEMENT ---
  async getWarehouses(): Promise<ApiResponse<WarehouseItem[]>> {
    return this.request('/crm/inventory/warehouses');
  }

  async createWarehouse(data: Partial<WarehouseItem>): Promise<ApiResponse<WarehouseItem>> {
    return this.request('/crm/inventory/warehouses', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getProductStocks(params: {
    warehouse_id?: number;
    product_id?: number;
    low_stock?: boolean;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<ProductStockItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/inventory/stocks${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async adjustStock(data: {
    warehouse_id: number;
    reason: string;
    items: Array<{
      product_id: number;
      product_variant_id?: number;
      adjustment_type: 'increase' | 'decrease' | 'damage';
      quantity: number;
      unit_cost?: number;
      notes?: string;
    }>;
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/inventory/adjust', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async transferStock(data: {
    source_warehouse_id: number;
    destination_warehouse_id: number;
    notes?: string;
    items: Array<{
      product_id: number;
      product_variant_id?: number;
      quantity: number;
      notes?: string;
    }>;
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/inventory/transfer', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getStockLedger(params: {
    product_id?: number;
    warehouse_id?: number;
    movement_type?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<StockMovementItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/inventory/ledger${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getInventoryValuation(warehouseId?: number): Promise<ApiResponse<InventoryValuationData>> {
    const q = warehouseId ? `?warehouse_id=${warehouseId}` : '';
    return this.request(`/crm/inventory/valuation${q}`);
  }

  async getLowStockAlerts(warehouseId?: number): Promise<ApiResponse<LowStockAlertItem[]>> {
    const q = warehouseId ? `?warehouse_id=${warehouseId}` : '';
    return this.request(`/crm/inventory/low-stock${q}`);
  }

  // --- PROCUREMENT & SUPPLIERS ---
  async getSuppliers(): Promise<ApiResponse<SupplierItem[]>> {
    return this.request('/crm/procurement/suppliers');
  }

  async createSupplier(data: Partial<SupplierItem>): Promise<ApiResponse<SupplierItem>> {
    return this.request('/crm/procurement/suppliers', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getPurchaseOrders(params: {
    status?: string;
    supplier_id?: number;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<PurchaseOrderRecord[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/procurement/purchase-orders${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createPurchaseOrder(data: {
    supplier_id: number;
    warehouse_id: number;
    order_date?: string;
    expected_delivery_date?: string;
    status?: string;
    currency?: string;
    discount_amount?: number;
    notes?: string;
    terms_conditions?: string;
    items: Array<{
      product_id: number;
      product_variant_id?: number;
      quantity: number;
      unit_cost: number;
      tax_percent?: number;
    }>;
  }): Promise<ApiResponse<PurchaseOrderRecord>> {
    return this.request('/crm/procurement/purchase-orders', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async receivePurchaseOrderGoods(id: number, items: Array<{ item_id: number; quantity: number }>): Promise<ApiResponse<PurchaseOrderRecord>> {
    return this.request(`/crm/procurement/purchase-orders/${id}/receive`, {
      method: 'POST',
      body: JSON.stringify({ items }),
    });
  }

  // --- Advanced Inventory & Procurement Enhancements ---

  async archiveProduct(id: number): Promise<ApiResponse<ProductCatalogItem>> {
    return this.request(`/crm/catalog/${id}/archive`, { method: 'POST' });
  }

  async unarchiveProduct(id: number): Promise<ApiResponse<ProductCatalogItem>> {
    return this.request(`/crm/catalog/${id}/unarchive`, { method: 'POST' });
  }

  async checkProductAvailability(items: Array<{ product_id: number; quantity: number; warehouse_id?: number }>, preferredWarehouseId?: number): Promise<ApiResponse<any>> {
    return this.request('/crm/catalog/check-availability', {
      method: 'POST',
      body: JSON.stringify({ items, preferred_warehouse_id: preferredWarehouseId }),
    });
  }

  async importProductsCsv(products: any[]): Promise<ApiResponse<any>> {
    return this.request('/crm/catalog/import', {
      method: 'POST',
      body: JSON.stringify({ products }),
    });
  }

  async getInventorySettings(): Promise<ApiResponse<InventorySettingItem>> {
    return this.request('/crm/inventory/settings');
  }

  async updateInventorySettings(data: Partial<InventorySettingItem>): Promise<ApiResponse<InventorySettingItem>> {
    return this.request('/crm/inventory/settings', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async getWarehouse(id: number): Promise<ApiResponse<WarehouseItem>> {
    return this.request(`/crm/inventory/warehouses/${id}`);
  }

  async updateWarehouse(id: number, data: Partial<WarehouseItem>): Promise<ApiResponse<WarehouseItem>> {
    return this.request(`/crm/inventory/warehouses/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async getTransfers(params: { status?: string; page?: number; per_page?: number } = {}): Promise<ApiResponse<StockTransferRecord[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/inventory/transfers${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createTransferRequest(data: {
    source_warehouse_id: number;
    destination_warehouse_id: number;
    items: Array<{ product_id: number; product_variant_id?: number; quantity: number; notes?: string }>;
    notes?: string;
  }): Promise<ApiResponse<StockTransferRecord>> {
    return this.request('/crm/inventory/transfers/request', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async approveTransfer(id: number): Promise<ApiResponse<StockTransferRecord>> {
    return this.request(`/crm/inventory/transfers/${id}/approve`, { method: 'POST' });
  }

  async dispatchTransfer(id: number): Promise<ApiResponse<StockTransferRecord>> {
    return this.request(`/crm/inventory/transfers/${id}/dispatch`, { method: 'POST' });
  }

  async receiveTransfer(id: number, data: {
    items: Array<{ item_id: number; accepted_quantity: number; damaged_quantity?: number }>;
    damage_notes?: string;
  }): Promise<ApiResponse<StockTransferRecord>> {
    return this.request(`/crm/inventory/transfers/${id}/receive`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async reserveStock(data: { product_id: number; warehouse_id: number; quantity: number; reference_type?: string; reference_id?: number }): Promise<ApiResponse<any>> {
    return this.request('/crm/inventory/reserve', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async releaseStock(data: { product_id: number; warehouse_id: number; quantity: number; reference_type?: string; reference_id?: number }): Promise<ApiResponse<any>> {
    return this.request('/crm/inventory/release', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async issueStock(data: { product_id: number; warehouse_id: number; quantity: number; reason: string }): Promise<ApiResponse<any>> {
    return this.request('/crm/inventory/issue', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getReconciliations(): Promise<ApiResponse<StockReconciliationRecord[]>> {
    return this.request('/crm/inventory/reconciliations');
  }

  async createReconciliation(data: {
    warehouse_id: number;
    items: Array<{ product_id: number; physical_quantity: number; reconciliation_action?: string }>;
    notes?: string;
  }): Promise<ApiResponse<StockReconciliationRecord>> {
    return this.request('/crm/inventory/reconciliations', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async applyReconciliation(id: number): Promise<ApiResponse<StockReconciliationRecord>> {
    return this.request(`/crm/inventory/reconciliations/${id}/apply`, { method: 'POST' });
  }

  async getReorderReport(warehouseId?: number): Promise<ApiResponse<any>> {
    const q = warehouseId ? `?warehouse_id=${warehouseId}` : '';
    return this.request(`/crm/inventory/reorder-report${q}`);
  }

  async getProductProfitabilityReport(): Promise<ApiResponse<any>> {
    return this.request('/crm/inventory/profitability');
  }

  async getSupplier(id: number): Promise<ApiResponse<SupplierItem>> {
    return this.request(`/crm/procurement/suppliers/${id}`);
  }

  async updateSupplier(id: number, data: Partial<SupplierItem>): Promise<ApiResponse<SupplierItem>> {
    return this.request(`/crm/procurement/suppliers/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async getSupplierBankDetails(id: number): Promise<ApiResponse<any>> {
    return this.request(`/crm/procurement/suppliers/${id}/bank-details`);
  }

  async getPurchaseRequests(params: { status?: string; page?: number; per_page?: number } = {}): Promise<ApiResponse<PurchaseRequestRecord[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/procurement/purchase-requests${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createPurchaseRequest(data: {
    warehouse_id: number;
    items: Array<{ product_id: number; quantity: number; estimated_unit_cost?: number; notes?: string }>;
    department_id?: number;
    notes?: string;
  }): Promise<ApiResponse<PurchaseRequestRecord>> {
    return this.request('/crm/procurement/purchase-requests', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async approvePurchaseRequest(id: number): Promise<ApiResponse<PurchaseRequestRecord>> {
    return this.request(`/crm/procurement/purchase-requests/${id}/approve`, { method: 'POST' });
  }

  async rejectPurchaseRequest(id: number, reason: string): Promise<ApiResponse<PurchaseRequestRecord>> {
    return this.request(`/crm/procurement/purchase-requests/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ rejection_reason: reason }),
    });
  }

  async getPurchaseOrder(id: number): Promise<ApiResponse<PurchaseOrderRecord>> {
    return this.request(`/crm/procurement/purchase-orders/${id}`);
  }

  async approvePurchaseOrder(id: number): Promise<ApiResponse<PurchaseOrderRecord>> {
    return this.request(`/crm/procurement/purchase-orders/${id}/approve`, { method: 'POST' });
  }

  async revisePurchaseOrder(id: number, data: any): Promise<ApiResponse<PurchaseOrderRecord>> {
    return this.request(`/crm/procurement/purchase-orders/${id}/revise`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async receivePurchaseOrderWithQC(id: number, data: {
    received_date?: string;
    challan_number?: string;
    invoice_reference?: string;
    qc_notes?: string;
    items: Array<{ item_id: number; quantity: number; accepted_quantity?: number; rejected_damaged_quantity?: number; rejection_reason?: string }>;
  }): Promise<ApiResponse<GoodsReceiptNoteRecord>> {
    return this.request(`/crm/procurement/purchase-orders/${id}/receive`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async createBillFromPO(id: number, data: { bill_number?: string; bill_date?: string; due_date?: string; notes?: string } = {}): Promise<ApiResponse<any>> {
    return this.request(`/crm/procurement/purchase-orders/${id}/create-bill`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getGoodsReceiptNotes(): Promise<ApiResponse<GoodsReceiptNoteRecord[]>> {
    return this.request('/crm/procurement/goods-receipt-notes');
  }

  // --- Customer Returns (RMA) ---

  async getCustomerReturns(params: { status?: string; customer_id?: number; page?: number; per_page?: number } = {}): Promise<ApiResponse<CustomerReturnRecord[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/returns${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createCustomerReturn(data: {
    customer_id: number;
    return_reason: string;
    invoice_id?: number;
    order_reference?: string;
    warehouse_id?: number;
    product_condition?: string;
    restock_action?: string;
    refund_type?: string;
    items: Array<{ product_id: number; quantity: number; unit_price?: number; tax_rate?: number; condition_notes?: string }>;
  }): Promise<ApiResponse<CustomerReturnRecord>> {
    return this.request('/crm/returns', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getCustomerReturn(id: number): Promise<ApiResponse<CustomerReturnRecord>> {
    return this.request(`/crm/returns/${id}`);
  }

  async inspectCustomerReturn(id: number, data: { product_condition: string; restock_action: string; inspection_notes?: string }): Promise<ApiResponse<CustomerReturnRecord>> {
    return this.request(`/crm/returns/${id}/inspect`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async approveCustomerReturn(id: number): Promise<ApiResponse<CustomerReturnRecord>> {
    return this.request(`/crm/returns/${id}/approve`, { method: 'POST' });
  }

  async getCustomerReturnsReport(): Promise<ApiResponse<any>> {
    return this.request('/crm/returns/reports');
  }

  // --------------------------------------------------------------------------
  // Finance, Quotations, Invoices, Payments & Refunds
  // --------------------------------------------------------------------------

  async getQuotations(params: {
    status?: string;
    customer_id?: number;
    deal_id?: number;
    search?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<QuotationRecord[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/quotations${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getQuotation(id: number): Promise<ApiResponse<QuotationRecord>> {
    return this.request(`/crm/quotations/${id}`);
  }

  async createQuotation(data: Partial<QuotationRecord> & { items: QuotationItemRecord[] }): Promise<ApiResponse<QuotationRecord>> {
    return this.request('/crm/quotations', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async reviseQuotation(id: number, data: Partial<QuotationRecord> & { items?: QuotationItemRecord[] }): Promise<ApiResponse<QuotationRecord>> {
    return this.request(`/crm/quotations/${id}/revise`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateQuotationStatus(id: number, status: string): Promise<ApiResponse<QuotationRecord>> {
    return this.request(`/crm/quotations/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status }),
    });
  }

  async convertQuotationToInvoice(id: number): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/quotations/${id}/convert-to-invoice`, {
      method: 'POST',
    });
  }

  async deleteQuotation(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/quotations/${id}`, {
      method: 'DELETE',
    });
  }

  getQuotationPdfUrl(id: number): string {
    return `${this.baseUrl}/crm/quotations/${id}/pdf`;
  }

  async getInvoices(params: {
    status?: string;
    customer_id?: number;
    deal_id?: number;
    search?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<InvoiceRecord[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/invoices${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getInvoice(id: number): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/invoices/${id}`);
  }

  async createInvoice(data: Partial<InvoiceRecord> & { items: InvoiceItemRecord[] }): Promise<ApiResponse<InvoiceRecord>> {
    return this.request('/crm/invoices', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async issueInvoice(id: number): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/invoices/${id}/issue`, {
      method: 'POST',
    });
  }

  async voidInvoice(id: number, reason: string): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/invoices/${id}/void`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async recordInvoicePayment(id: number, data: {
    amount: number;
    payment_method?: string;
    payment_date?: string;
    reference_number?: string;
    notes?: string;
  }): Promise<ApiResponse<PaymentRecord>> {
    return this.request(`/crm/invoices/${id}/payments`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async createInvoicePaymentLink(id: number): Promise<ApiResponse<{
    order: any;
    invoice_id: number;
    invoice_number: string;
    outstanding_amount: number;
    currency: string;
    payment_link: string;
    key_id: string;
  }>> {
    return this.request(`/crm/invoices/${id}/payment-link`, {
      method: 'POST',
    });
  }

  async verifyInvoicePayment(id: number, verifyData: {
    razorpay_order_id: string;
    razorpay_payment_id: string;
    razorpay_signature: string;
  }): Promise<ApiResponse<PaymentRecord>> {
    return this.request(`/crm/invoices/${id}/verify-payment`, {
      method: 'POST',
      body: JSON.stringify(verifyData),
    });
  }

  async deleteInvoice(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/invoices/${id}`, {
      method: 'DELETE',
    });
  }

  getInvoicePdfUrl(id: number): string {
    return `${this.baseUrl}/crm/invoices/${id}/pdf`;
  }

  async getPayments(params: {
    invoice_id?: number;
    customer_id?: number;
    search?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<PaymentRecord[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/payments${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getPayment(id: number): Promise<ApiResponse<PaymentRecord>> {
    return this.request(`/crm/payments/${id}`);
  }

  async refundPayment(id: number, data: { amount: number; reason: string }): Promise<ApiResponse<RefundRecord>> {
    return this.request(`/crm/payments/${id}/refund`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getFinanceSummary(): Promise<ApiResponse<FinanceSummaryData>> {
    return this.request('/crm/finance/summary');
  }

  // --------------------------------------------------------------------------
  // Advanced Payments & Gateway Architecture
  // --------------------------------------------------------------------------

  async getPaymentGateways(): Promise<ApiResponse<PaymentGatewayConfigItem[]>> {
    return this.request('/crm/payments/gateways');
  }

  async savePaymentGateway(data: Partial<PaymentGatewayConfigItem>): Promise<ApiResponse<PaymentGatewayConfigItem>> {
    return this.request('/crm/payments/gateways', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async generateUpiQr(data: {
    amount: number;
    vpa?: string;
    merchant_name?: string;
    reference_id?: string;
    note?: string;
  }): Promise<ApiResponse<UpiQrPayloadData>> {
    return this.request('/crm/payments/upi-qr', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async createPaymentLink(data: Partial<PaymentLinkItem>): Promise<ApiResponse<PaymentLinkItem>> {
    return this.request('/crm/payment-links', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getPaymentLinks(page = 1): Promise<ApiResponse<PaginatedResponse<PaymentLinkItem>>> {
    return this.request(`/crm/payment-links?page=${page}`);
  }

  async getPaymentLinkByToken(token: string): Promise<ApiResponse<PaymentLinkItem>> {
    return this.request(`/public/payment-links/${token}`);
  }

  async allocatePayment(paymentId: number, data: { invoice_id: number; amount: number }): Promise<ApiResponse<PaymentAllocationItem>> {
    return this.request(`/crm/payments/${paymentId}/allocate`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getFinanceTransactions(params: {
    reconciliation_status?: string;
    internal_status?: string;
    page?: number;
  } = {}): Promise<ApiResponse<PaginatedResponse<FinanceTransactionItem>>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/finance/transactions${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async reconcileFinanceTransaction(id: number, data: { status: string; notes?: string }): Promise<ApiResponse<FinanceTransactionItem>> {
    return this.request(`/crm/finance/transactions/${id}/reconcile`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async requestPaymentRefund(paymentId: number, data: { amount: number; reason: string }): Promise<ApiResponse<RefundRecord>> {
    return this.request(`/crm/payments/${paymentId}/refund-request`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async approveRefund(refundId: number, notes?: string): Promise<ApiResponse<RefundRecord>> {
    return this.request(`/crm/refunds/${refundId}/approve`, {
      method: 'POST',
      body: JSON.stringify({ notes }),
    });
  }

  async getRefunds(page = 1): Promise<ApiResponse<PaginatedResponse<RefundRecord>>> {
    return this.request(`/crm/refunds?page=${page}`);
  }

  // --------------------------------------------------------------------------
  // Accounts Receivable (AR) & Debt Recovery
  // --------------------------------------------------------------------------

  async getReceivablesAgeing(customerId?: number): Promise<ApiResponse<AccountsReceivableAgeingReport>> {
    const q = customerId ? `?customer_id=${customerId}` : '';
    return this.request(`/crm/receivables/ageing${q}`);
  }

  async updatePromiseToPay(invoiceId: number, data: { promise_to_pay_date: string; notes?: string }): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/receivables/invoices/${invoiceId}/promise-to-pay`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async setInvoiceDispute(invoiceId: number, data: { is_disputed: boolean; reason?: string; status?: string }): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/receivables/invoices/${invoiceId}/dispute`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async assignInvoiceCollection(invoiceId: number, data: { user_id?: number; recovery_status?: string }): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/receivables/invoices/${invoiceId}/assign`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // --------------------------------------------------------------------------
  // Accounts Payable (AP) & Vendor Bills
  // --------------------------------------------------------------------------

  async getVendorBills(params: { supplier_id?: number; status?: string; page?: number } = {}): Promise<ApiResponse<PaginatedResponse<VendorBillItem>>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/payables/bills${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createVendorBill(data: Partial<VendorBillItem>): Promise<ApiResponse<VendorBillItem>> {
    return this.request('/crm/payables/bills', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async approveVendorBill(billId: number): Promise<ApiResponse<VendorBillItem>> {
    return this.request(`/crm/payables/bills/${billId}/approve`, {
      method: 'POST',
    });
  }

  async getVendorPayments(page = 1): Promise<ApiResponse<PaginatedResponse<VendorPaymentItem>>> {
    return this.request(`/crm/payables/payments?page=${page}`);
  }

  async recordVendorPayment(data: Partial<VendorPaymentItem>): Promise<ApiResponse<VendorPaymentItem>> {
    return this.request('/crm/payables/payments', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getPayablesAgeing(supplierId?: number): Promise<ApiResponse<AccountsPayableAgeingReport>> {
    const q = supplierId ? `?supplier_id=${supplierId}` : '';
    return this.request(`/crm/payables/ageing${q}`);
  }

  // --------------------------------------------------------------------------
  // Expenses & Reimbursements
  // --------------------------------------------------------------------------

  async getExpenses(params: { category?: string; status?: string; is_reimbursement?: boolean; page?: number } = {}): Promise<ApiResponse<PaginatedResponse<ExpenseItem>>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/expenses${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createExpense(data: Partial<ExpenseItem>): Promise<ApiResponse<ExpenseItem>> {
    return this.request('/crm/expenses', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async approveExpense(id: number): Promise<ApiResponse<ExpenseItem>> {
    return this.request(`/crm/expenses/${id}/approve`, {
      method: 'POST',
    });
  }

  async rejectExpense(id: number, reason: string): Promise<ApiResponse<ExpenseItem>> {
    return this.request(`/crm/expenses/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async payExpense(id: number, data: { payment_method?: string; payment_reference?: string } = {}): Promise<ApiResponse<ExpenseItem>> {
    return this.request(`/crm/expenses/${id}/pay`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getExpenseReport(): Promise<ApiResponse<any>> {
    return this.request('/crm/expenses/report');
  }

  // --------------------------------------------------------------------------
  // Comprehensive Financial BI & Cash-Flow Reporting
  // --------------------------------------------------------------------------

  async getComprehensiveFinanceReport(): Promise<ApiResponse<ComprehensiveFinanceReport>> {
    return this.request('/crm/finance/comprehensive-report');
  }

  // --------------------------------------------------------------------------
  // Indian GST Invoicing, Tax Management & Business Compliance
  // --------------------------------------------------------------------------

  async getGstRegistrations(): Promise<ApiResponse<GstRegistrationItem[]>> {
    return this.request('/crm/gst/config');
  }

  async saveGstRegistration(data: Partial<GstRegistrationItem>): Promise<ApiResponse<GstRegistrationItem>> {
    return this.request('/crm/gst/config', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async verifyGstin(gstin: string): Promise<ApiResponse<GstVerificationResult>> {
    return this.request('/crm/gst/verify-gstin', {
      method: 'POST',
      body: JSON.stringify({ gstin }),
    });
  }

  async calculateGstTax(data: {
    items: any[];
    place_of_supply_state_code?: string;
    buyer_gst_treatment?: string;
    is_tax_inclusive?: boolean;
    is_reverse_charge?: boolean;
    is_export?: boolean;
    export_type?: string;
  }): Promise<ApiResponse<GstTaxCalculationResult>> {
    return this.request('/crm/gst/calculate-tax', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getGstInvoices(params: { document_type?: string; einvoice_status?: string; status?: string; page?: number } = {}): Promise<ApiResponse<PaginatedResponse<InvoiceRecord>>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/gst/invoices${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createGstInvoice(data: any): Promise<ApiResponse<InvoiceRecord>> {
    return this.request('/crm/gst/invoices', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async finalizeGstInvoice(id: number): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/gst/invoices/${id}/finalize`, {
      method: 'POST',
    });
  }

  async createGstCreditNote(id: number, data: { amount?: number; reason: string }): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/gst/invoices/${id}/credit-note`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async generateEInvoice(id: number): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/gst/invoices/${id}/einvoice`, {
      method: 'POST',
    });
  }

  async cancelEInvoice(id: number, reason: string): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/gst/invoices/${id}/einvoice/cancel`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async generateEWayBill(id: number, data: {
    distance_km: number;
    vehicle_number: string;
    transporter_name?: string;
    transporter_id?: string;
    transport_mode?: string;
  }): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/gst/invoices/${id}/eway-bill`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateEWayBillVehicle(id: number, vehicleNumber: string): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/gst/invoices/${id}/eway-bill/vehicle`, {
      method: 'POST',
      body: JSON.stringify({ vehicle_number: vehicleNumber }),
    });
  }

  async cancelEWayBill(id: number, reason: string): Promise<ApiResponse<InvoiceRecord>> {
    return this.request(`/crm/gst/invoices/${id}/eway-bill/cancel`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async getGstTaxSummary(params: { start_date?: string; end_date?: string } = {}): Promise<ApiResponse<GstTaxSummaryReport>> {
    const q = new URLSearchParams();
    if (params.start_date) q.append('start_date', params.start_date);
    if (params.end_date) q.append('end_date', params.end_date);
    return this.request(`/crm/gst/reports/summary${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getGstr1SalesRegister(params: { start_date?: string; end_date?: string } = {}): Promise<ApiResponse<Gstr1SalesRegister>> {
    const q = new URLSearchParams();
    if (params.start_date) q.append('start_date', params.start_date);
    if (params.end_date) q.append('end_date', params.end_date);
    return this.request(`/crm/gst/reports/sales-register${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getHsnSummary(): Promise<ApiResponse<HsnSacSummaryItem[]>> {
    return this.request('/crm/gst/reports/hsn-summary');
  }

  async getComplianceReminders(params: { type?: string; status?: string } = {}): Promise<ApiResponse<ComplianceReminderItem[]>> {
    const q = new URLSearchParams();
    if (params.type) q.append('type', params.type);
    if (params.status) q.append('status', params.status);
    return this.request(`/crm/gst/compliance/reminders${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createComplianceReminder(data: Partial<ComplianceReminderItem>): Promise<ApiResponse<ComplianceReminderItem>> {
    return this.request('/crm/gst/compliance/reminders', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async completeComplianceReminder(id: number): Promise<ApiResponse<ComplianceReminderItem>> {
    return this.request(`/crm/gst/compliance/reminders/${id}/complete`, {
      method: 'POST',
    });
  }

  async getComplianceDocuments(params: { type?: string } = {}): Promise<ApiResponse<ComplianceDocumentItem[]>> {
    const q = params.type ? `?type=${params.type}` : '';
    return this.request(`/crm/gst/compliance/documents${q}`);
  }

  async storeComplianceDocument(data: Partial<ComplianceDocumentItem>): Promise<ApiResponse<ComplianceDocumentItem>> {
    return this.request('/crm/gst/compliance/documents', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // --------------------------------------------------------------------------
  // Official WhatsApp CRM & Shared Team Inbox
  // --------------------------------------------------------------------------

  async getWhatsAppAccounts(): Promise<ApiResponse<WhatsAppAccountItem[]>> {
    return this.request('/crm/whatsapp/accounts');
  }

  async createWhatsAppAccount(data: {
    name: string;
    waba_id: string;
    app_id?: string;
    system_user_token: string;
    webhook_verify_token?: string;
  }): Promise<ApiResponse<WhatsAppAccountItem>> {
    return this.request('/crm/whatsapp/accounts', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getWhatsAppPhoneNumbers(): Promise<ApiResponse<WhatsAppPhoneNumberItem[]>> {
    return this.request('/crm/whatsapp/phone-numbers');
  }

  async connectWhatsAppPhoneNumber(data: {
    whatsapp_account_id: number;
    phone_number_id: string;
    display_phone_number: string;
    verified_name?: string;
    is_default?: boolean;
    default_assigned_user_id?: number;
  }): Promise<ApiResponse<WhatsAppPhoneNumberItem>> {
    return this.request('/crm/whatsapp/phone-numbers', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async testWhatsAppConnection(id: number): Promise<ApiResponse<any>> {
    return this.request(`/crm/whatsapp/phone-numbers/${id}/test`, {
      method: 'POST',
    });
  }

  async getWhatsAppTemplates(): Promise<ApiResponse<WhatsAppTemplateItem[]>> {
    return this.request('/crm/whatsapp/templates');
  }

  async createWhatsAppTemplate(data: {
    whatsapp_account_id: number;
    template_name: string;
    category?: string;
    language?: string;
    body_text: string;
    footer_text?: string;
    header_type?: string;
    buttons?: any[];
  }): Promise<ApiResponse<WhatsAppTemplateItem>> {
    return this.request('/crm/whatsapp/templates', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getWhatsAppConversations(params: {
    scope?: 'all' | 'assigned_to_me' | 'unassigned';
    status?: string;
    priority?: string;
    search?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<WhatsAppConversationItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/whatsapp/conversations${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getWhatsAppConversation(id: number): Promise<ApiResponse<WhatsAppConversationItem>> {
    return this.request(`/crm/whatsapp/conversations/${id}`);
  }

  async assignWhatsAppConversation(id: number, assignedUserId: number | null): Promise<ApiResponse<WhatsAppConversationItem>> {
    return this.request(`/crm/whatsapp/conversations/${id}/assign`, {
      method: 'POST',
      body: JSON.stringify({ assigned_user_id: assignedUserId }),
    });
  }

  async updateWhatsAppConversationStatus(id: number, status: 'open' | 'pending' | 'resolved'): Promise<ApiResponse<WhatsAppConversationItem>> {
    return this.request(`/crm/whatsapp/conversations/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status }),
    });
  }

  async addWhatsAppConversationNote(id: number, content: string): Promise<ApiResponse<WhatsAppConversationNoteItem>> {
    return this.request(`/crm/whatsapp/conversations/${id}/notes`, {
      method: 'POST',
      body: JSON.stringify({ content }),
    });
  }

  async toggleWhatsAppAiHandover(id: number, isAiHandled: boolean): Promise<ApiResponse<WhatsAppConversationItem>> {
    return this.request(`/crm/whatsapp/conversations/${id}/toggle-ai`, {
      method: 'POST',
      body: JSON.stringify({ is_ai_handled: isAiHandled }),
    });
  }

  async createLeadFromWhatsAppConversation(id: number, data: { name?: string; email?: string; company?: string }): Promise<ApiResponse<LeadItem>> {
    return this.request(`/crm/whatsapp/conversations/${id}/create-lead`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async sendWhatsAppTextMessage(conversationId: number, text: string): Promise<ApiResponse<WhatsAppMessageItem>> {
    return this.request(`/crm/whatsapp/conversations/${conversationId}/messages/text`, {
      method: 'POST',
      body: JSON.stringify({ text }),
    });
  }

  async sendWhatsAppTemplateMessage(
    conversationId: number,
    templateName: string,
    language: string = 'en_US',
    parameters: any[] = []
  ): Promise<ApiResponse<WhatsAppMessageItem>> {
    return this.request(`/crm/whatsapp/conversations/${conversationId}/messages/template`, {
      method: 'POST',
      body: JSON.stringify({
        template_name: templateName,
        language,
        parameters,
      }),
    });
  }

  async sendWhatsAppMediaMessage(
    conversationId: number,
    mediaType: 'image' | 'document' | 'audio' | 'video',
    mediaUrl: string,
    caption?: string
  ): Promise<ApiResponse<WhatsAppMessageItem>> {
    return this.request(`/crm/whatsapp/conversations/${conversationId}/messages/media`, {
      method: 'POST',
      body: JSON.stringify({
        media_type: mediaType,
        media_url: mediaUrl,
        caption,
      }),
    });
  }

  async sendWhatsAppInteractiveMessage(
    conversationId: number,
    interactive: any
  ): Promise<ApiResponse<WhatsAppMessageItem>> {
    return this.request(`/crm/whatsapp/conversations/${conversationId}/messages/interactive`, {
      method: 'POST',
      body: JSON.stringify({ interactive }),
    });
  }

  async sendWhatsAppLocationMessage(
    conversationId: number,
    data: { latitude: number; longitude: number; name?: string; address?: string }
  ): Promise<ApiResponse<WhatsAppMessageItem>> {
    return this.request(`/crm/whatsapp/conversations/${conversationId}/messages/location`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateWhatsAppPhoneNumber(
    id: number,
    data: Partial<WhatsAppPhoneNumberItem>
  ): Promise<ApiResponse<WhatsAppPhoneNumberItem>> {
    return this.request(`/crm/whatsapp/phone-numbers/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async disconnectWhatsAppPhoneNumber(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/whatsapp/phone-numbers/${id}`, {
      method: 'DELETE',
    });
  }

  async disconnectWhatsAppAccount(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/whatsapp/accounts/${id}/disconnect`, {
      method: 'POST',
    });
  }

  async getWhatsAppIntegrationLogs(page: number = 1, perPage: number = 25): Promise<ApiResponse<any>> {
    return this.request(`/crm/whatsapp/logs?page=${page}&per_page=${perPage}`);
  }

  async syncWhatsAppTemplates(accountId?: number): Promise<ApiResponse<WhatsAppTemplateItem[]>> {
    return this.request('/crm/whatsapp/templates/sync', {
      method: 'POST',
      body: JSON.stringify({ whatsapp_account_id: accountId }),
    });
  }

  async testWhatsAppTemplate(
    id: number,
    data: { phone_number: string; parameters?: any[] }
  ): Promise<ApiResponse<WhatsAppMessageItem>> {
    return this.request(`/crm/whatsapp/templates/${id}/test`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getWhatsAppTemplateAnalytics(): Promise<ApiResponse<any>> {
    return this.request('/crm/whatsapp/templates/analytics');
  }

  // WhatsApp Campaigns
  async getWhatsAppCampaigns(params?: { status?: string; page?: number; per_page?: number }): Promise<ApiResponse<any>> {
    const qs = new URLSearchParams();
    if (params?.status) qs.append('status', params.status);
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/whatsapp/campaigns${queryStr}`);
  }

  async createWhatsAppCampaign(
    data: Partial<WhatsAppCampaignItem> & { csv_rows?: any[]; selected_contact_ids?: number[] }
  ): Promise<ApiResponse<WhatsAppCampaignItem>> {
    return this.request('/crm/whatsapp/campaigns', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getWhatsAppCampaign(id: number): Promise<ApiResponse<{ campaign: WhatsAppCampaignItem; analytics: any }>> {
    return this.request(`/crm/whatsapp/campaigns/${id}`);
  }

  async getWhatsAppCampaignRecipients(
    id: number,
    params?: { status?: string; page?: number; per_page?: number }
  ): Promise<ApiResponse<any>> {
    const qs = new URLSearchParams();
    if (params?.status) qs.append('status', params.status);
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/whatsapp/campaigns/${id}/recipients${queryStr}`);
  }

  async testWhatsAppCampaign(
    id: number,
    data: { phone_number: string; sample_parameters?: any[] }
  ): Promise<ApiResponse<any>> {
    return this.request(`/crm/whatsapp/campaigns/${id}/test`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async submitWhatsAppCampaignForApproval(id: number): Promise<ApiResponse<WhatsAppCampaignItem>> {
    return this.request(`/crm/whatsapp/campaigns/${id}/submit`, {
      method: 'POST',
    });
  }

  async approveWhatsAppCampaign(id: number): Promise<ApiResponse<WhatsAppCampaignItem>> {
    return this.request(`/crm/whatsapp/campaigns/${id}/approve`, {
      method: 'POST',
    });
  }

  async startWhatsAppCampaign(id: number): Promise<ApiResponse<WhatsAppCampaignItem>> {
    return this.request(`/crm/whatsapp/campaigns/${id}/start`, {
      method: 'POST',
    });
  }

  async pauseWhatsAppCampaign(id: number): Promise<ApiResponse<WhatsAppCampaignItem>> {
    return this.request(`/crm/whatsapp/campaigns/${id}/pause`, {
      method: 'POST',
    });
  }

  async cancelWhatsAppCampaign(id: number): Promise<ApiResponse<WhatsAppCampaignItem>> {
    return this.request(`/crm/whatsapp/campaigns/${id}/cancel`, {
      method: 'POST',
    });
  }

  async processWhatsAppCampaignBatch(
    id: number,
    batchSize: number = 50
  ): Promise<ApiResponse<{ processed_count: number }>> {
    return this.request(`/crm/whatsapp/campaigns/${id}/process-batch`, {
      method: 'POST',
      body: JSON.stringify({ batch_size: batchSize }),
    });
  }

  // WhatsApp Customer Journeys & Automations
  async getWhatsAppAutomations(): Promise<ApiResponse<WhatsAppAutomationItem[]>> {
    return this.request('/crm/whatsapp/automations');
  }

  async createWhatsAppAutomation(data: Partial<WhatsAppAutomationItem>): Promise<ApiResponse<WhatsAppAutomationItem>> {
    return this.request('/crm/whatsapp/automations', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getWhatsAppAutomation(id: number): Promise<ApiResponse<WhatsAppAutomationItem>> {
    return this.request(`/crm/whatsapp/automations/${id}`);
  }

  async updateWhatsAppAutomation(
    id: number,
    data: Partial<WhatsAppAutomationItem>
  ): Promise<ApiResponse<WhatsAppAutomationItem>> {
    return this.request(`/crm/whatsapp/automations/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async toggleWhatsAppAutomation(id: number): Promise<ApiResponse<WhatsAppAutomationItem>> {
    return this.request(`/crm/whatsapp/automations/${id}/toggle`, {
      method: 'POST',
    });
  }

  async deleteWhatsAppAutomation(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/whatsapp/automations/${id}`, {
      method: 'DELETE',
    });
  }

  async testWhatsAppAutomationTrigger(data: {
    event: string;
    phone_number: string;
    payload?: any;
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/whatsapp/automations/trigger-test', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getWhatsAppAutomationLogs(params?: {
    automation_id?: number;
    page?: number;
    per_page?: number;
  }): Promise<ApiResponse<any>> {
    const qs = new URLSearchParams();
    if (params?.automation_id) qs.append('automation_id', String(params.automation_id));
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/whatsapp/automations/logs${queryStr}`);
  }

  // WhatsApp Usage & Billing
  async getWhatsAppUsageSummary(params?: {
    start_date?: string;
    end_date?: string;
  }): Promise<ApiResponse<WhatsAppUsageReportsData>> {
    const qs = new URLSearchParams();
    if (params?.start_date) qs.append('start_date', params.start_date);
    if (params?.end_date) qs.append('end_date', params.end_date);
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/whatsapp/usage/summary${queryStr}`);
  }

  async getWhatsAppUsageRecords(params?: {
    phone_number_id?: number;
    category?: string;
    page?: number;
    per_page?: number;
  }): Promise<ApiResponse<any>> {
    const qs = new URLSearchParams();
    if (params?.phone_number_id) qs.append('phone_number_id', String(params.phone_number_id));
    if (params?.category) qs.append('category', params.category);
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/whatsapp/usage/records${queryStr}`);
  }

  // =========================================================================
  // Complete AI-Powered CRM Assistant & Business Intelligence Engine (Prompt 35)
  // =========================================================================

  // Providers & Settings
  async getAiProviders(): Promise<ApiResponse<TenantAiConfigurationItem[]>> {
    return this.request('/crm/ai/providers');
  }

  async updateAiProvider(data: Partial<TenantAiConfigurationItem> & { api_key?: string }): Promise<ApiResponse<TenantAiConfigurationItem>> {
    return this.request('/crm/ai/providers', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async pingAiProvider(configId?: number): Promise<ApiResponse<{ success: boolean; provider: string; model: string; latency_ms: number; message: string }>> {
    return this.request('/crm/ai/providers/ping', {
      method: 'POST',
      body: JSON.stringify({ config_id: configId }),
    });
  }

  // Chat Assistant & Sessions
  async getAiSessions(): Promise<ApiResponse<AiAssistantSessionItem[]>> {
    return this.request('/crm/ai/sessions');
  }

  async getAiSessionMessages(sessionId: number): Promise<ApiResponse<AiAssistantSessionItem>> {
    return this.request(`/crm/ai/sessions/${sessionId}/messages`);
  }

  async askAiAssistant(params: { prompt: string; session_id?: number; context_filters?: any }): Promise<ApiResponse<{
    session_id: number;
    message_id: number;
    role: string;
    content: string;
    suggested_actions?: any[];
    citations?: any;
    tokens: number;
    cost: number;
  }>> {
    return this.request('/crm/ai/chat', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  }

  // Sales Intelligence & Intent Scoring
  async analyzeSalesEntity(params: { entity_type: 'lead' | 'deal'; entity_id: number }): Promise<ApiResponse<AiSalesInsightItem>> {
    return this.request('/crm/ai/sales/analyze', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  }

  async getSalesInsight(entityType: string, entityId: number): Promise<ApiResponse<AiSalesInsightItem | null>> {
    return this.request(`/crm/ai/sales/insights/${entityType}/${entityId}`);
  }

  // Proposed Action Execution Gate
  async getAiProposedActions(params?: { per_page?: number }): Promise<ApiResponse<{ data: AiProposedActionItem[]; total: number }>> {
    const qs = params?.per_page ? `?per_page=${params.per_page}` : '';
    return this.request(`/crm/ai/actions${qs}`);
  }

  async confirmAiAction(id: number): Promise<ApiResponse<AiProposedActionItem>> {
    return this.request(`/crm/ai/actions/${id}/confirm`, {
      method: 'POST',
    });
  }

  async rejectAiAction(id: number, reason?: string): Promise<ApiResponse<AiProposedActionItem>> {
    return this.request(`/crm/ai/actions/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  // Meeting Intelligence
  async getAiMeetings(params?: { per_page?: number }): Promise<ApiResponse<{ data: AiMeetingRecordingItem[]; total: number }>> {
    const qs = params?.per_page ? `?per_page=${params.per_page}` : '';
    return this.request(`/crm/ai/meetings${qs}`);
  }

  async processAiMeeting(formData: FormData): Promise<ApiResponse<AiMeetingRecordingItem>> {
    return this.request('/crm/ai/meetings/process', {
      method: 'POST',
      body: formData,
      headers: {}, // let browser set multipart/form-data boundary
    });
  }

  async processAiMeetingMock(params: { title: string; mock_transcript: string; language?: string }): Promise<ApiResponse<AiMeetingRecordingItem>> {
    return this.request('/crm/ai/meetings/process', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  }

  // Document Intelligence
  async getAiDocuments(params?: { per_page?: number }): Promise<ApiResponse<{ data: AiDocumentExtractionItem[]; total: number }>> {
    const qs = params?.per_page ? `?per_page=${params.per_page}` : '';
    return this.request(`/crm/ai/documents${qs}`);
  }

  async uploadAiDocument(file: File, classification?: string): Promise<ApiResponse<AiDocumentExtractionItem>> {
    const formData = new FormData();
    formData.append('file', file);
    if (classification) formData.append('document_classification', classification);

    return this.request('/crm/ai/documents/upload', {
      method: 'POST',
      body: formData,
      headers: {},
    });
  }

  async updateAiDocumentCorrections(id: number, corrections: Record<string, any>): Promise<ApiResponse<AiDocumentExtractionItem>> {
    return this.request(`/crm/ai/documents/${id}/corrections`, {
      method: 'PUT',
      body: JSON.stringify({ corrections }),
    });
  }

  async commitAiDocument(id: number, target: { target_entity_type: string; target_entity_id: number }): Promise<ApiResponse<AiDocumentExtractionItem>> {
    return this.request(`/crm/ai/documents/${id}/commit`, {
      method: 'POST',
      body: JSON.stringify(target),
    });
  }

  // Content Generator
  async generateAiContent(params: {
    content_type: string;
    prompt_or_context: string;
    tone?: string;
    language?: string;
    template_variables?: any;
  }): Promise<ApiResponse<AiGeneratedContentItem>> {
    return this.request('/crm/ai/content/generate', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  }

  // Usage & Admin Reports
  async getAiUsageReports(params?: { start_date?: string; end_date?: string }): Promise<ApiResponse<AiUsageReportsData>> {
    const qs = new URLSearchParams();
    if (params?.start_date) qs.append('start_date', params.start_date);
    if (params?.end_date) qs.append('end_date', params.end_date);
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/ai/usage/reports${queryStr}`);
  }

  async getAiRequestLogs(params?: { per_page?: number }): Promise<ApiResponse<{ data: any[]; total: number }>> {
    const qs = params?.per_page ? `?per_page=${params.per_page}` : '';
    return this.request(`/crm/ai/usage/logs${qs}`);
  }

  // ----------------------------------------------------
  // WhatsApp AI Agents & Knowledge Base
  // ----------------------------------------------------

  async getWhatsAppAiAgents(): Promise<ApiResponse<WhatsAppAiAgentItem[]>> {
    return this.request('/crm/whatsapp/ai/agents');
  }

  async getWhatsAppAiAgent(id: number): Promise<ApiResponse<WhatsAppAiAgentItem>> {
    return this.request(`/crm/whatsapp/ai/agents/${id}`);
  }

  async createWhatsAppAiAgent(data: Partial<WhatsAppAiAgentItem>): Promise<ApiResponse<WhatsAppAiAgentItem>> {
    return this.request('/crm/whatsapp/ai/agents', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateWhatsAppAiAgent(id: number, data: Partial<WhatsAppAiAgentItem> & { change_summary?: string }): Promise<ApiResponse<WhatsAppAiAgentItem>> {
    return this.request(`/crm/whatsapp/ai/agents/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteWhatsAppAiAgent(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/whatsapp/ai/agents/${id}`, {
      method: 'DELETE',
    });
  }

  async testWhatsAppAiPlayground(id: number, message: string, history?: any[]): Promise<ApiResponse<any>> {
    return this.request(`/crm/whatsapp/ai/agents/${id}/test`, {
      method: 'POST',
      body: JSON.stringify({ message, history }),
    });
  }

  async getWhatsAppKnowledgeBases(): Promise<ApiResponse<WhatsAppKnowledgeBaseItem[]>> {
    return this.request('/crm/whatsapp/ai/knowledge');
  }

  async uploadWhatsAppKnowledge(formData: FormData): Promise<ApiResponse<WhatsAppKnowledgeBaseItem>> {
    return this.request('/crm/whatsapp/ai/knowledge/upload', {
      method: 'POST',
      body: formData,
    });
  }

  async ingestWhatsAppKnowledgeText(data: {
    title: string;
    category: string;
    description?: string;
    content: string;
  }): Promise<ApiResponse<WhatsAppKnowledgeBaseItem>> {
    return this.request('/crm/whatsapp/ai/knowledge/upload', {
      method: 'POST',
      body: JSON.stringify({
        ...data,
        source_type: 'manual_text',
      }),
    });
  }

  async searchWhatsAppKnowledge(query: string, topK: number = 3): Promise<ApiResponse<WhatsAppKnowledgeChunkItem[]>> {
    return this.request('/crm/whatsapp/ai/knowledge/search', {
      method: 'POST',
      body: JSON.stringify({ query, top_k: topK }),
    });
  }

  async deleteWhatsAppKnowledgeBase(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/whatsapp/ai/knowledge/${id}`, {
      method: 'DELETE',
    });
  }

  async getWhatsAppAiProviders(): Promise<ApiResponse<WhatsAppAiProviderItem[]>> {
    return this.request('/crm/whatsapp/ai/providers');
  }

  async updateWhatsAppAiProvider(data: Partial<WhatsAppAiProviderItem> & { api_key?: string }): Promise<ApiResponse<void>> {
    return this.request('/crm/whatsapp/ai/providers', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getWhatsAppAiLogs(params?: { agent_id?: number; status?: string; page?: number; per_page?: number }): Promise<ApiResponse<WhatsAppAiExecutionLogItem[]> & { stats?: any }> {
    const qs = new URLSearchParams();
    if (params?.agent_id) qs.append('agent_id', String(params.agent_id));
    if (params?.status) qs.append('status', params.status);
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));

    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/whatsapp/ai/logs${queryStr}`);
  }

  // ----------------------------------------------------
  // Voice AI & Telephony Automation API Methods
  // ----------------------------------------------------

  async getVoiceCalls(params?: {
    agent_id?: number;
    campaign_id?: number;
    lead_id?: number;
    status?: string;
    direction?: string;
    page?: number;
    per_page?: number;
  }): Promise<ApiResponse<VoiceCallItem[]> & { stats?: { total_calls: number; total_minutes: number; total_cost: number; qualified_count: number } }> {
    const qs = new URLSearchParams();
    if (params?.agent_id) qs.append('agent_id', String(params.agent_id));
    if (params?.campaign_id) qs.append('campaign_id', String(params.campaign_id));
    if (params?.lead_id) qs.append('lead_id', String(params.lead_id));
    if (params?.status) qs.append('status', params.status);
    if (params?.direction) qs.append('direction', params.direction);
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));

    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/voice/calls${queryStr}`);
  }

  async getVoiceCall(id: number): Promise<ApiResponse<VoiceCallItem>> {
    return this.request(`/crm/voice/calls/${id}`);
  }

  async initiateVoiceCall(data: {
    lead_id?: number;
    contact_id?: number;
    to_number: string;
    voice_agent_id?: number;
    campaign_id?: number;
  }): Promise<ApiResponse<VoiceCallItem>> {
    return this.request('/crm/voice/calls/dial', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async simulateVoiceCallTurn(
    callId: number,
    data: { user_speech?: string; user_speech_text?: string; is_barge_in?: boolean }
  ): Promise<ApiResponse<{ agent_reply_text?: string; response_text?: string; audio_duration_ms?: number; is_interrupted?: boolean; call: VoiceCallItem }>> {
    const payload = {
      user_speech_text: data.user_speech_text || data.user_speech || '',
      is_barge_in: data.is_barge_in || false,
    };
    return this.request(`/crm/voice/calls/${callId}/turn`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async getVoiceAgents(): Promise<ApiResponse<VoiceAiAgentItem[]>> {
    return this.request('/crm/voice/agents');
  }

  async getVoiceAgent(id: number): Promise<ApiResponse<VoiceAiAgentItem>> {
    return this.request(`/crm/voice/agents/${id}`);
  }

  async createVoiceAgent(data: Partial<VoiceAiAgentItem>): Promise<ApiResponse<VoiceAiAgentItem>> {
    return this.request('/crm/voice/agents', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateVoiceAgent(id: number, data: Partial<VoiceAiAgentItem>): Promise<ApiResponse<VoiceAiAgentItem>> {
    return this.request(`/crm/voice/agents/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteVoiceAgent(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/voice/agents/${id}`, {
      method: 'DELETE',
    });
  }

  async getVoiceCampaigns(): Promise<ApiResponse<VoiceCallingCampaignItem[]>> {
    return this.request('/crm/voice/campaigns');
  }

  async getVoiceCampaign(id: number): Promise<ApiResponse<VoiceCallingCampaignItem>> {
    return this.request(`/crm/voice/campaigns/${id}`);
  }

  async createVoiceCampaign(data: Partial<VoiceCallingCampaignItem>): Promise<ApiResponse<VoiceCallingCampaignItem>> {
    return this.request('/crm/voice/campaigns', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async launchVoiceCampaign(id: number, batchLimit: number = 20): Promise<ApiResponse<{ message: string; campaign: VoiceCallingCampaignItem }>> {
    return this.request(`/crm/voice/campaigns/${id}/launch`, {
      method: 'POST',
      body: JSON.stringify({ batch_limit: batchLimit }),
    });
  }

  async pauseVoiceCampaign(id: number): Promise<ApiResponse<{ message: string; campaign: VoiceCallingCampaignItem }>> {
    return this.request(`/crm/voice/campaigns/${id}/pause`, {
      method: 'POST',
    });
  }

  async deleteVoiceCampaign(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/voice/campaigns/${id}`, {
      method: 'DELETE',
    });
  }

  async getTelephonyProviders(): Promise<ApiResponse<TelephonyProviderItem[]>> {
    return this.request('/crm/voice/providers');
  }

  async updateTelephonyProvider(data: Partial<TelephonyProviderItem> & { auth_token?: string }): Promise<ApiResponse<TelephonyProviderItem>> {
    return this.request('/crm/voice/providers', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async testTelephonyProvider(id: number): Promise<ApiResponse<{ status: string; provider: string; message: string; latency_ms: number }>> {
    return this.request(`/crm/voice/providers/${id}/test`, {
      method: 'POST',
    });
  }

  async getTelephonyProviderLogs(params?: { provider?: string; status?: string }): Promise<ApiResponse<TelephonyProviderLogItem[]>> {
    const q = new URLSearchParams();
    if (params?.provider) q.append('provider', params.provider);
    if (params?.status) q.append('status', params.status);
    return this.request(`/crm/voice/providers/logs${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getTelephonyPhoneNumbers(): Promise<ApiResponse<TelephonyPhoneNumberItem[]>> {
    return this.request('/crm/voice/phone-numbers');
  }

  async registerTelephonyPhoneNumber(data: Partial<TelephonyPhoneNumberItem>): Promise<ApiResponse<TelephonyPhoneNumberItem>> {
    return this.request('/crm/voice/phone-numbers', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async clickToCall(data: {
    to_number: string;
    from_number?: string;
    lead_id?: number;
    contact_id?: number;
    deal_id?: number;
    company_id?: number;
    provider?: string;
  }): Promise<ApiResponse<VoiceCallItem>> {
    return this.request('/crm/voice/calls/click-to-call', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async lookupCaller(phoneNumber: string): Promise<ApiResponse<CallerProfileLookupData>> {
    return this.request('/crm/voice/calls/lookup', {
      method: 'POST',
      body: JSON.stringify({ phone_number: phoneNumber }),
    });
  }

  async simulateInboundCall(data: {
    from_number: string;
    to_number: string;
    provider?: string;
    provider_call_id?: string;
    idempotency_key?: string;
  }): Promise<ApiResponse<VoiceCallItem & { is_duplicate?: boolean; caller_profile?: CallerProfileLookupData; routing?: any }>> {
    return this.request('/crm/voice/calls/inbound', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateCallDisposition(callId: number, data: {
    disposition: string;
    status?: string;
    qualification_result?: string;
    duration_seconds?: number;
    follow_up_required?: boolean;
    next_follow_up_at?: string;
    follow_up_notes?: string;
    ai_summary?: string;
  }): Promise<ApiResponse<VoiceCallItem>> {
    return this.request(`/crm/voice/calls/${callId}/disposition`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getCallRecordingPlayback(callId: number): Promise<ApiResponse<{ call_id: number; recording_url: string; recording_duration_seconds: number; notice_played: boolean }>> {
    return this.request(`/crm/voice/calls/${callId}/recording/playback`);
  }

  async getCallRecordingDownload(callId: number): Promise<ApiResponse<{ call_id: number; download_url: string; file_name: string }>> {
    return this.request(`/crm/voice/calls/${callId}/recording/download`);
  }

  async submitCallQualityReview(callId: number, data: {
    score: number;
    criteria_scores?: Record<string, number>;
    feedback?: string;
    tags?: string[];
  }): Promise<ApiResponse<CallQualityReviewItem>> {
    return this.request(`/crm/voice/calls/${callId}/reviews`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async transferVoiceCall(callId: number, data: {
    transfer_target: string;
    transfer_to_user_id?: number;
  }): Promise<ApiResponse<VoiceCallItem>> {
    return this.request(`/crm/voice/calls/${callId}/transfer`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getVoiceCallReports(params?: { start_date?: string; end_date?: string }): Promise<ApiResponse<CallReportingAnalyticsData>> {
    const q = new URLSearchParams();
    if (params?.start_date) q.append('start_date', params.start_date);
    if (params?.end_date) q.append('end_date', params.end_date);
    return this.request(`/crm/voice/calls/reports${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getIvrFlows(): Promise<ApiResponse<CallIvrFlowItem[]>> {
    return this.request('/crm/voice/ivr-flows');
  }

  async createIvrFlow(data: Partial<CallIvrFlowItem>): Promise<ApiResponse<CallIvrFlowItem>> {
    return this.request('/crm/voice/ivr-flows', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateIvrFlow(id: number, data: Partial<CallIvrFlowItem>): Promise<ApiResponse<CallIvrFlowItem>> {
    return this.request(`/crm/voice/ivr-flows/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteIvrFlow(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/voice/ivr-flows/${id}`, {
      method: 'DELETE',
    });
  }

  async getCallQueues(): Promise<ApiResponse<CallQueueItem[]>> {
    return this.request('/crm/voice/queues');
  }

  async createCallQueue(data: Partial<CallQueueItem>): Promise<ApiResponse<CallQueueItem>> {
    return this.request('/crm/voice/queues', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateCallQueue(id: number, data: Partial<CallQueueItem>): Promise<ApiResponse<CallQueueItem>> {
    return this.request(`/crm/voice/queues/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteCallQueue(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/voice/queues/${id}`, {
      method: 'DELETE',
    });
  }

  // ----------------------------------------------------
  // Unified Omnichannel Inbox API Methods
  // ----------------------------------------------------

  async getOmnichannelConversations(params?: {
    channel?: string;
    filter?: string;
    status?: string;
    priority?: string;
    is_ai_handled?: boolean;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<ApiResponse<OmnichannelConversationItem[]> & { stats?: Record<string, number> }> {
    const qs = new URLSearchParams();
    if (params?.channel) qs.append('channel', params.channel);
    if (params?.filter) qs.append('filter', params.filter);
    if (params?.status) qs.append('status', params.status);
    if (params?.priority) qs.append('priority', params.priority);
    if (params?.is_ai_handled !== undefined) qs.append('is_ai_handled', String(params.is_ai_handled));
    if (params?.search) qs.append('search', params.search);
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));

    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/inbox/conversations${queryStr}`);
  }

  async getOmnichannelConversation(id: number): Promise<ApiResponse<OmnichannelConversationItem>> {
    return this.request(`/crm/inbox/conversations/${id}`);
  }

  async sendOmnichannelMessage(
    id: number,
    data: {
      content: string;
      subject?: string;
      to?: string;
      attachments?: any[];
      media_url?: string;
      dlt_template_id?: string;
    }
  ): Promise<ApiResponse<OmnichannelMessageItem> & { conversation?: OmnichannelConversationItem }> {
    return this.request(`/crm/inbox/conversations/${id}/messages`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async assignOmnichannelConversation(
    id: number,
    data: { assigned_user_id?: number | null; assigned_team_id?: number | null }
  ): Promise<ApiResponse<OmnichannelConversationItem>> {
    return this.request(`/crm/inbox/conversations/${id}/assign`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateOmnichannelStatus(
    id: number,
    status: 'open' | 'pending' | 'resolved'
  ): Promise<ApiResponse<OmnichannelConversationItem>> {
    return this.request(`/crm/inbox/conversations/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status }),
    });
  }

  async toggleOmnichannelAi(
    id: number,
    isAiHandled: boolean
  ): Promise<ApiResponse<OmnichannelConversationItem>> {
    return this.request(`/crm/inbox/conversations/${id}/toggle-ai`, {
      method: 'POST',
      body: JSON.stringify({ is_ai_handled: isAiHandled }),
    });
  }

  async markOmnichannelRead(id: number): Promise<ApiResponse<OmnichannelConversationItem>> {
    return this.request(`/crm/inbox/conversations/${id}/read`, {
      method: 'POST',
    });
  }

  async addOmnichannelNote(
    id: number,
    content: string,
    mentions?: number[]
  ): Promise<ApiResponse<any>> {
    return this.request(`/crm/inbox/conversations/${id}/notes`, {
      method: 'POST',
      body: JSON.stringify({ content, mentions }),
    });
  }

  async getUnifiedTimeline(params: {
    customer_id?: number;
    lead_id?: number;
    contact_id?: number;
  }): Promise<ApiResponse<UnifiedTimelineItem[]>> {
    const qs = new URLSearchParams();
    if (params.customer_id) qs.append('customer_id', String(params.customer_id));
    if (params.lead_id) qs.append('lead_id', String(params.lead_id));
    if (params.contact_id) qs.append('contact_id', String(params.contact_id));

    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/inbox/timeline${queryStr}`);
  }

  async getEmailAccounts(): Promise<ApiResponse<EmailAccountItem[]>> {
    return this.request('/crm/inbox/channels/email');
  }

  async saveEmailAccount(data: Partial<EmailAccountItem> & { smtp_password?: string; imap_password?: string }): Promise<ApiResponse<EmailAccountItem>> {
    return this.request('/crm/inbox/channels/email', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getSmsConfigs(): Promise<ApiResponse<SmsConfigurationItem[]>> {
    return this.request('/crm/inbox/channels/sms');
  }

  async saveSmsConfig(data: Partial<SmsConfigurationItem> & { auth_token?: string }): Promise<ApiResponse<SmsConfigurationItem>> {
    return this.request('/crm/inbox/channels/sms', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getWebChatWidgets(): Promise<ApiResponse<WebsiteChatWidgetItem[]>> {
    return this.request('/crm/inbox/channels/webchat');
  }

  async saveWebChatWidget(data: Partial<WebsiteChatWidgetItem>): Promise<ApiResponse<WebsiteChatWidgetItem>> {
    return this.request('/crm/inbox/channels/webchat', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getCommunicationSuppressions(): Promise<ApiResponse<CommunicationSuppressionItem[]>> {
    return this.request('/crm/inbox/suppressions');
  }

  async addCommunicationSuppression(data: {
    channel: string;
    identifier: string;
    reason: string;
  }): Promise<ApiResponse<CommunicationSuppressionItem>> {
    return this.request('/crm/inbox/suppressions', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async removeCommunicationSuppression(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/inbox/suppressions/${id}`, {
      method: 'DELETE',
    });
  }

  async getPendingIdentityMerges(): Promise<ApiResponse<IdentityMergePendingItem[]>> {
    return this.request('/crm/inbox/identity/pending');
  }

  async reviewIdentityMerge(
    id: number,
    data: { decision: 'merged' | 'rejected'; notes?: string }
  ): Promise<ApiResponse<IdentityMergePendingItem>> {
    return this.request(`/crm/inbox/identity/pending/${id}/review`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async autoAssignOmnichannelConversation(
    id: number,
    teamId?: number
  ): Promise<ApiResponse<OmnichannelConversationItem>> {
    return this.request(`/crm/inbox/conversations/${id}/auto-assign`, {
      method: 'POST',
      body: JSON.stringify({ team_id: teamId }),
    });
  }

  async getOmnichannelAssignmentHistory(
    id: number
  ): Promise<ApiResponse<ConversationAssignmentLogItem[]>> {
    return this.request(`/crm/inbox/conversations/${id}/assignment-history`);
  }

  async getOmnichannelAnalytics(params?: {
    from?: string;
    to?: string;
  }): Promise<ApiResponse<OmnichannelAnalyticsData>> {
    const qs = new URLSearchParams();
    if (params?.from) qs.append('from', params.from);
    if (params?.to) qs.append('to', params.to);
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/inbox/analytics${queryStr}`);
  }

  async getOmnichannelConsents(params?: {
    channel?: string;
    destination?: string;
  }): Promise<ApiResponse<CustomerCommunicationConsentItem[]>> {
    const qs = new URLSearchParams();
    if (params?.channel) qs.append('channel', params.channel);
    if (params?.destination) qs.append('destination', params.destination);
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/inbox/consents${queryStr}`);
  }

  async updateOmnichannelConsent(
    data: Partial<CustomerCommunicationConsentItem>
  ): Promise<ApiResponse<CustomerCommunicationConsentItem>> {
    return this.request('/crm/inbox/consents', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getOmnichannelQuickReplies(
    channel?: string
  ): Promise<ApiResponse<OmnichannelQuickReplyItem[]>> {
    const queryStr = channel ? `?channel=${channel}` : '';
    return this.request(`/crm/inbox/quick-replies${queryStr}`);
  }

  async createOmnichannelQuickReply(
    data: Partial<OmnichannelQuickReplyItem>
  ): Promise<ApiResponse<OmnichannelQuickReplyItem>> {
    return this.request('/crm/inbox/quick-replies', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async deleteOmnichannelQuickReply(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/inbox/quick-replies/${id}`, {
      method: 'DELETE',
    });
  }

  async sendOmnichannelTypingSignal(
    id: number,
    isTyping: boolean
  ): Promise<ApiResponse<void>> {
    return this.request(`/crm/inbox/conversations/${id}/typing`, {
      method: 'POST',
      body: JSON.stringify({ is_typing: isTyping }),
    });
  }

  async lockOmnichannelConversation(id: number): Promise<ApiResponse<OmnichannelConversationItem>> {
    return this.request(`/crm/inbox/conversations/${id}/lock`, { method: 'POST' });
  }

  async unlockOmnichannelConversation(id: number): Promise<ApiResponse<OmnichannelConversationItem>> {
    return this.request(`/crm/inbox/conversations/${id}/unlock`, { method: 'POST' });
  }

  async archiveOmnichannelConversation(id: number, archive: boolean = true): Promise<ApiResponse<OmnichannelConversationItem>> {
    return this.request(`/crm/inbox/conversations/${id}/archive`, {
      method: 'POST',
      body: JSON.stringify({ archive }),
    });
  }

  async sendOmnichannelPresence(data: { conversation_id?: number; is_typing?: boolean }): Promise<ApiResponse<{ presence: OmnichannelAgentPresenceItem; active_agents: OmnichannelAgentPresenceItem[]; collision_detected: boolean }>> {
    return this.request('/crm/inbox/presence', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async checkOmnichannelCollision(id: number): Promise<ApiResponse<{ collision_detected: boolean; active_agents: OmnichannelAgentPresenceItem[] }>> {
    return this.request(`/crm/inbox/conversations/${id}/collision`);
  }

  async getOmnichannelRoutingRules(): Promise<ApiResponse<OmnichannelRoutingRuleItem[]>> {
    return this.request('/crm/inbox/routing-rules');
  }

  async saveOmnichannelRoutingRule(data: Partial<OmnichannelRoutingRuleItem>): Promise<ApiResponse<OmnichannelRoutingRuleItem>> {
    return this.request('/crm/inbox/routing-rules', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async deleteOmnichannelRoutingRule(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/inbox/routing-rules/${id}`, { method: 'DELETE' });
  }

  async getOmnichannelSlaPolicies(): Promise<ApiResponse<OmnichannelSlaPolicyItem[]>> {
    return this.request('/crm/inbox/sla-policies');
  }

  async saveOmnichannelSlaPolicy(data: Partial<OmnichannelSlaPolicyItem>): Promise<ApiResponse<OmnichannelSlaPolicyItem>> {
    return this.request('/crm/inbox/sla-policies', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async deleteOmnichannelSlaPolicy(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/inbox/sla-policies/${id}`, { method: 'DELETE' });
  }

  async recalculateOmnichannelSla(): Promise<ApiResponse<{ summary: { total: number; on_track: number; warning: number; breached: number } }>> {
    return this.request('/crm/inbox/sla-policies/recalculate', { method: 'POST' });
  }

  async getOmnichannelDetailedReports(): Promise<ApiResponse<OmnichannelDetailedReportsData>> {
    return this.request('/crm/inbox/detailed-reports');
  }

  async getTelegramAccounts(): Promise<ApiResponse<TelegramAccountItem[]>> {
    return this.request('/crm/inbox/channels/telegram');
  }

  async saveTelegramAccount(data: Partial<TelegramAccountItem>): Promise<ApiResponse<TelegramAccountItem>> {
    return this.request('/crm/inbox/channels/telegram', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async disconnectTelegramAccount(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/inbox/channels/telegram/${id}/disconnect`, { method: 'POST' });
  }

  async getOmnichannelChannelSettings(): Promise<ApiResponse<OmnichannelChannelSettingItem[]>> {
    return this.request('/crm/inbox/channels/settings');
  }

  async saveOmnichannelChannelSettings(data: Partial<OmnichannelChannelSettingItem>): Promise<ApiResponse<OmnichannelChannelSettingItem>> {
    return this.request('/crm/inbox/channels/settings', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async testOmnichannelConnection(data: { channel: string; channel_account_id?: string }): Promise<ApiResponse<{ channel: string; status: string; message: string; tested_at: string }>> {
    return this.request('/crm/inbox/channels/test-connection', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getOmnichannelChannelHealth(): Promise<ApiResponse<Record<string, { channel: string; status: string; message: string; last_checked_at: string }>>> {
    return this.request('/crm/inbox/channels/health');
  }

  // ----------------------------------------------------
  // Visual Workflow Automation API Methods
  // ----------------------------------------------------

  async getWorkflows(params?: { category?: string; status?: string; search?: string }): Promise<ApiResponse<WorkflowItem[]>> {
    const qs = new URLSearchParams();
    if (params?.category) qs.append('category', params.category);
    if (params?.status) qs.append('status', params.status);
    if (params?.search) qs.append('search', params.search);
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/workflows${queryStr}`);
  }

  async getWorkflow(id: number): Promise<ApiResponse<WorkflowItem>> {
    return this.request(`/crm/workflows/${id}`);
  }

  async createWorkflow(data: {
    name: string;
    description?: string;
    category: string;
    trigger_type: string;
    definition: WorkflowDefinition;
  }): Promise<ApiResponse<WorkflowItem>> {
    return this.request('/crm/workflows', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateWorkflow(
    id: number,
    data: {
      name?: string;
      description?: string;
      category?: string;
      trigger_type?: string;
      definition?: WorkflowDefinition;
      changelog?: string;
    }
  ): Promise<ApiResponse<WorkflowItem>> {
    return this.request(`/crm/workflows/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async publishWorkflow(id: number): Promise<ApiResponse<WorkflowItem>> {
    return this.request(`/crm/workflows/${id}/publish`, {
      method: 'POST',
    });
  }

  async pauseWorkflow(id: number): Promise<ApiResponse<WorkflowItem>> {
    return this.request(`/crm/workflows/${id}/pause`, {
      method: 'POST',
    });
  }

  async deleteWorkflow(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/workflows/${id}`, {
      method: 'DELETE',
    });
  }

  async simulateWorkflow(
    id: number,
    samplePayload: Record<string, any>,
    definition?: WorkflowDefinition
  ): Promise<ApiResponse<WorkflowSimulationResult>> {
    return this.request(`/crm/workflows/${id}/simulate`, {
      method: 'POST',
      body: JSON.stringify({
        sample_payload: samplePayload,
        definition,
      }),
    });
  }

  async getWorkflowTemplates(): Promise<ApiResponse<WorkflowTemplateItem[]>> {
    return this.request('/crm/workflows/templates');
  }

  async importWorkflowTemplate(templateId: number, name?: string): Promise<ApiResponse<WorkflowItem>> {
    return this.request('/crm/workflows/import-template', {
      method: 'POST',
      body: JSON.stringify({ template_id: templateId, name }),
    });
  }

  async getWorkflowRuns(params?: {
    workflow_id?: number;
    status?: string;
    page?: number;
    per_page?: number;
  }): Promise<ApiResponse<WorkflowRunItem[]>> {
    const qs = new URLSearchParams();
    if (params?.workflow_id) qs.append('workflow_id', String(params.workflow_id));
    if (params?.status) qs.append('status', params.status);
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/workflow-runs${queryStr}`);
  }

  async getWorkflowRun(id: number): Promise<ApiResponse<WorkflowRunItem>> {
    return this.request(`/crm/workflow-runs/${id}`);
  }

  async retryWorkflowRun(id: number): Promise<ApiResponse<WorkflowRunItem>> {
    return this.request(`/crm/workflow-runs/${id}/retry`, {
      method: 'POST',
    });
  }

  async cancelWorkflowRun(id: number): Promise<ApiResponse<WorkflowRunItem>> {
    return this.request(`/crm/workflow-runs/${id}/cancel`, {
      method: 'POST',
    });
  }

  async triggerManualWorkflow(
    workflowId: number,
    entityType?: string,
    entityId?: number,
    payload?: Record<string, any>
  ): Promise<ApiResponse<WorkflowRunItem>> {
    return this.request('/crm/workflows/trigger', {
      method: 'POST',
      body: JSON.stringify({
        workflow_id: workflowId,
        entity_type: entityType,
        entity_id: entityId,
        payload: payload ?? {},
      }),
    });
  }

  async getWorkflowApprovals(params?: {
    status?: string;
    category?: string;
    approver_role?: string;
    page?: number;
    per_page?: number;
  }): Promise<ApiResponse<WorkflowApprovalItem[]>> {
    const qs = new URLSearchParams();
    if (params?.status) qs.append('status', params.status);
    if (params?.category) qs.append('category', params.category);
    if (params?.approver_role) qs.append('approver_role', params.approver_role);
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/workflows/approvals${queryStr}`);
  }

  async getWorkflowApproval(id: number): Promise<ApiResponse<WorkflowApprovalItem>> {
    return this.request(`/crm/workflows/approvals/${id}`);
  }

  async decideWorkflowApproval(
    id: number,
    data: { decision: 'approve' | 'reject'; comments?: string }
  ): Promise<ApiResponse<{ approval: WorkflowApprovalItem; run: WorkflowRunItem }>> {
    return this.request(`/crm/workflows/approvals/${id}/decide`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getWorkflowLimits(): Promise<ApiResponse<{
    limits: WorkflowAutomationLimitItem;
    active_workflows_count: number;
    monthly_usage_percentage: number;
  }>> {
    return this.request('/crm/workflows/limits');
  }

  async updateWorkflowLimits(
    data: Partial<WorkflowAutomationLimitItem>
  ): Promise<ApiResponse<WorkflowAutomationLimitItem>> {
    return this.request('/crm/workflows/limits', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async getWorkflowMetrics(): Promise<ApiResponse<WorkflowMetricsItem>> {
    return this.request('/crm/workflows/dashboard/metrics');
  }

  async getWorkflowTriggersCatalog(): Promise<ApiResponse<Record<string, any>>> {
    return this.request('/crm/workflows/triggers/catalog');
  }

  // ----------------------------------------------------
  // Landing Pages & Form Builder API Methods
  // ----------------------------------------------------

  async getLandingPages(params?: { status?: string; search?: string }): Promise<ApiResponse<LandingPageItem[]>> {
    const qs = new URLSearchParams();
    if (params?.status) qs.append('status', params.status);
    if (params?.search) qs.append('search', params.search);
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/landing-pages${queryStr}`);
  }

  async getLandingPage(id: number): Promise<ApiResponse<LandingPageItem>> {
    return this.request(`/crm/landing-pages/${id}`);
  }

  async createLandingPage(data: Partial<LandingPageItem>): Promise<ApiResponse<LandingPageItem>> {
    return this.request('/crm/landing-pages', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateLandingPage(id: number, data: Partial<LandingPageItem>): Promise<ApiResponse<LandingPageItem>> {
    return this.request(`/crm/landing-pages/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteLandingPage(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/landing-pages/${id}`, {
      method: 'DELETE',
    });
  }

  async publishLandingPage(id: number, changelog?: string): Promise<ApiResponse<LandingPageItem>> {
    return this.request(`/crm/landing-pages/${id}/publish`, {
      method: 'POST',
      body: JSON.stringify({ changelog }),
    });
  }

  async unpublishLandingPage(id: number): Promise<ApiResponse<LandingPageItem>> {
    return this.request(`/crm/landing-pages/${id}/unpublish`, {
      method: 'POST',
    });
  }

  async rollbackLandingPage(id: number, version: number): Promise<ApiResponse<LandingPageItem>> {
    return this.request(`/crm/landing-pages/${id}/rollback`, {
      method: 'POST',
      body: JSON.stringify({ version }),
    });
  }

  async getLandingPageTemplates(): Promise<ApiResponse<LandingPageTemplateItem[]>> {
    return this.request('/crm/landing-pages/templates');
  }

  async createLandingPageFromTemplate(templateId: number, name: string, slug?: string): Promise<ApiResponse<LandingPageItem>> {
    return this.request('/crm/landing-pages/from-template', {
      method: 'POST',
      body: JSON.stringify({ template_id: templateId, name, slug }),
    });
  }

  async getLandingPageAnalytics(id: number, params?: { start_date?: string; end_date?: string }): Promise<ApiResponse<{ landing_page_id: number; name: string; slug: string; metrics: LandingPageAnalyticsData }>> {
    const qs = new URLSearchParams();
    if (params?.start_date) qs.append('start_date', params.start_date);
    if (params?.end_date) qs.append('end_date', params.end_date);
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/landing-pages/${id}/analytics${queryStr}`);
  }

  async getForms(): Promise<ApiResponse<FormItem[]>> {
    return this.request('/crm/forms');
  }

  async getForm(id: number): Promise<ApiResponse<FormItem>> {
    return this.request(`/crm/forms/${id}`);
  }

  async createForm(data: Partial<FormItem>): Promise<ApiResponse<FormItem>> {
    return this.request('/crm/forms', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateForm(id: number, data: Partial<FormItem>): Promise<ApiResponse<FormItem>> {
    return this.request(`/crm/forms/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteForm(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/forms/${id}`, {
      method: 'DELETE',
    });
  }

  async getFormSubmissions(id: number, page?: number): Promise<ApiResponse<FormSubmissionItem[]> & { pagination?: any }> {
    const qs = new URLSearchParams();
    if (page) qs.append('page', String(page));
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/forms/${id}/submissions${queryStr}`);
  }

  async getCustomDomains(): Promise<ApiResponse<CustomDomainItem[]>> {
    return this.request('/crm/custom-domains');
  }

  async addCustomDomain(domain: string): Promise<ApiResponse<CustomDomainItem>> {
    return this.request('/crm/custom-domains', {
      method: 'POST',
      body: JSON.stringify({ domain }),
    });
  }

  async verifyCustomDomain(id: number): Promise<ApiResponse<CustomDomainItem>> {
    return this.request(`/crm/custom-domains/${id}/verify`, {
      method: 'POST',
    });
  }

  async deleteCustomDomain(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/custom-domains/${id}`, {
      method: 'DELETE',
    });
  }

  // ----------------------------------------------------
  // Marketing Automation & Campaigns API Methods
  // ----------------------------------------------------

  async getSegments(): Promise<ApiResponse<MarketingSegmentItem[]>> {
    return this.request('/crm/segments');
  }

  async getSegment(id: number): Promise<ApiResponse<MarketingSegmentItem>> {
    return this.request(`/crm/segments/${id}`);
  }

  async createSegment(data: Partial<MarketingSegmentItem>): Promise<ApiResponse<MarketingSegmentItem>> {
    return this.request('/crm/segments', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateSegment(id: number, data: Partial<MarketingSegmentItem>): Promise<ApiResponse<MarketingSegmentItem>> {
    return this.request(`/crm/segments/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteSegment(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/segments/${id}`, {
      method: 'DELETE',
    });
  }

  async previewSegment(
    rules: any,
    inclusionIds?: number[],
    exclusionIds?: number[],
    targetEntity = 'lead'
  ): Promise<ApiResponse<{ matching_count: number; target_entity: string }>> {
    return this.request('/crm/segments/preview', {
      method: 'POST',
      body: JSON.stringify({
        rules,
        inclusion_ids: inclusionIds,
        exclusion_ids: exclusionIds,
        target_entity: targetEntity,
      }),
    });
  }

  async getSegmentMembers(id: number, channel?: string): Promise<ApiResponse<{ segment_id: number; name: string; total_members: number; sample_members: any[] }>> {
    const qs = channel ? `?channel=${channel}` : '';
    return this.request(`/crm/segments/${id}/members${qs}`);
  }

  async getCampaigns(params?: { status?: string; channel?: string; search?: string }): Promise<ApiResponse<MarketingCampaignItem[]>> {
    const qs = new URLSearchParams();
    if (params?.status) qs.append('status', params.status);
    if (params?.channel) qs.append('channel', params.channel);
    if (params?.search) qs.append('search', params.search);
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/campaigns${queryStr}`);
  }

  async getCampaign(id: number): Promise<ApiResponse<MarketingCampaignItem>> {
    return this.request(`/crm/campaigns/${id}`);
  }

  async createCampaign(data: Partial<MarketingCampaignItem>): Promise<ApiResponse<MarketingCampaignItem>> {
    return this.request('/crm/campaigns', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateCampaign(id: number, data: Partial<MarketingCampaignItem>): Promise<ApiResponse<MarketingCampaignItem>> {
    return this.request(`/crm/campaigns/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteCampaign(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/campaigns/${id}`, {
      method: 'DELETE',
    });
  }

  async dispatchCampaign(id: number): Promise<ApiResponse<{ campaign: MarketingCampaignItem; dispatch_result: any }>> {
    return this.request(`/crm/campaigns/${id}/dispatch`, {
      method: 'POST',
    });
  }

  async testSendCampaign(id: number, recipient: string): Promise<ApiResponse<any>> {
    return this.request(`/crm/campaigns/${id}/test-send`, {
      method: 'POST',
      body: JSON.stringify({ recipient }),
    });
  }

  async pauseCampaign(id: number): Promise<ApiResponse<MarketingCampaignItem>> {
    return this.request(`/crm/campaigns/${id}/pause`, {
      method: 'POST',
    });
  }

  async resumeCampaign(id: number): Promise<ApiResponse<MarketingCampaignItem>> {
    return this.request(`/crm/campaigns/${id}/resume`, {
      method: 'POST',
    });
  }

  async getCampaignAnalytics(id: number): Promise<ApiResponse<CampaignAnalyticsData>> {
    return this.request(`/crm/campaigns/${id}/analytics`);
  }

  async submitCampaignForApproval(id: number, comments?: string): Promise<ApiResponse<MarketingCampaignItem>> {
    return this.request(`/crm/campaigns/${id}/submit-approval`, {
      method: 'POST',
      body: JSON.stringify({ comments }),
    });
  }

  async approveCampaign(id: number, comments?: string): Promise<ApiResponse<MarketingCampaignItem>> {
    return this.request(`/crm/campaigns/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify({ comments }),
    });
  }

  async rejectCampaign(id: number, reason: string): Promise<ApiResponse<MarketingCampaignItem>> {
    return this.request(`/crm/campaigns/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async duplicateCampaign(id: number): Promise<ApiResponse<MarketingCampaignItem>> {
    return this.request(`/crm/campaigns/${id}/duplicate`, {
      method: 'POST',
    });
  }

  async archiveCampaign(id: number): Promise<ApiResponse<MarketingCampaignItem>> {
    return this.request(`/crm/campaigns/${id}/archive`, {
      method: 'POST',
    });
  }

  async getCampaignsSummary(): Promise<ApiResponse<MarketingOverviewSummary>> {
    return this.request('/crm/campaigns/summary');
  }

  // Multi-channel Marketing Templates
  async getMarketingTemplates(params?: { channel?: string; category?: string }): Promise<ApiResponse<MarketingTemplateItem[]>> {
    const qs = new URLSearchParams();
    if (params?.channel) qs.set('channel', params.channel);
    if (params?.category) qs.set('category', params.category);
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/marketing/templates${queryStr}`);
  }

  async getMarketingTemplate(id: number): Promise<ApiResponse<MarketingTemplateItem>> {
    return this.request(`/crm/marketing/templates/${id}`);
  }

  async createMarketingTemplate(data: Partial<MarketingTemplateItem>): Promise<ApiResponse<MarketingTemplateItem>> {
    return this.request('/crm/marketing/templates', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateMarketingTemplate(id: number, data: Partial<MarketingTemplateItem>): Promise<ApiResponse<MarketingTemplateItem>> {
    return this.request(`/crm/marketing/templates/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteMarketingTemplate(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/marketing/templates/${id}`, {
      method: 'DELETE',
    });
  }

  async previewMarketingTemplate(id: number): Promise<ApiResponse<any>> {
    return this.request(`/crm/marketing/templates/${id}/preview`);
  }

  async generateMarketingTemplateAi(params: {
    channel: string;
    objective: string;
    product_name?: string;
    tone?: string;
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/marketing/templates/ai-generate', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  }

  // Compliance & Consent Registry
  async getMarketingConsents(params?: { channel?: string; entity_type?: string }): Promise<ApiResponse<any>> {
    const qs = new URLSearchParams();
    if (params?.channel) qs.set('channel', params.channel);
    if (params?.entity_type) qs.set('entity_type', params.entity_type);
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/marketing/consents${queryStr}`);
  }

  async recordMarketingConsent(data: {
    entity_type: string;
    entity_id: number;
    channel: string;
    consent_status: string;
    source?: string;
    reason?: string;
  }): Promise<ApiResponse<MarketingConsentItem>> {
    return this.request('/crm/marketing/consents', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async checkMarketingConsent(channel: string, address: string): Promise<ApiResponse<any>> {
    return this.request('/crm/marketing/consents/check', {
      method: 'POST',
      body: JSON.stringify({ channel, address }),
    });
  }

  async getMarketingSuppressions(search?: string): Promise<ApiResponse<any>> {
    const qs = new URLSearchParams();
    if (search) qs.set('search', search);
    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/marketing/consents/suppressions${queryStr}`);
  }

  async unsubscribeMarketingRecipient(channel: string, address: string): Promise<ApiResponse<any>> {
    return this.request('/crm/marketing/consents/unsubscribe', {
      method: 'POST',
      body: JSON.stringify({ channel, address }),
    });
  }

  async getDripSequences(): Promise<ApiResponse<DripSequenceItem[]>> {
    return this.request('/crm/drip-sequences');
  }

  async getDripSequence(id: number): Promise<ApiResponse<{ sequence: DripSequenceItem; recent_enrollments: DripEnrollmentItem[] }>> {
    return this.request(`/crm/drip-sequences/${id}`);
  }

  async createDripSequence(data: Partial<DripSequenceItem>): Promise<ApiResponse<DripSequenceItem>> {
    return this.request('/crm/drip-sequences', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateDripSequence(id: number, data: Partial<DripSequenceItem>): Promise<ApiResponse<DripSequenceItem>> {
    return this.request(`/crm/drip-sequences/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteDripSequence(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/drip-sequences/${id}`, {
      method: 'DELETE',
    });
  }

  async enrollInDripSequence(
    id: number,
    entityType: string,
    entityId: number,
    context?: Record<string, any>
  ): Promise<ApiResponse<DripEnrollmentItem>> {
    return this.request(`/crm/drip-sequences/${id}/enroll`, {
      method: 'POST',
      body: JSON.stringify({
        entity_type: entityType,
        entity_id: entityId,
        context,
      }),
    });
  }

  async processDueDripEnrollments(): Promise<ApiResponse<any>> {
    return this.request('/crm/drip-sequences/process-due', {
      method: 'POST',
    });
  }

  // ==========================================
  // HRM & EMPLOYEE MANAGEMENT APIS
  // ==========================================

  async getEmployees(params?: { status?: string; department_id?: number; search?: string }): Promise<ApiResponse<EmployeeItem[]>> {
    const query = new URLSearchParams();
    if (params?.status) query.append('status', params.status);
    if (params?.department_id) query.append('department_id', String(params.department_id));
    if (params?.search) query.append('search', params.search);
    const qs = query.toString();
    return this.request(`/crm/employees${qs ? `?${qs}` : ''}`);
  }

  async getEmployee(id: number): Promise<ApiResponse<EmployeeItem>> {
    return this.request(`/crm/employees/${id}`);
  }

  async createEmployee(data: Partial<EmployeeItem>): Promise<ApiResponse<EmployeeItem>> {
    return this.request('/crm/employees', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateEmployee(id: number, data: Partial<EmployeeItem>): Promise<ApiResponse<EmployeeItem>> {
    return this.request(`/crm/employees/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteEmployee(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/employees/${id}`, {
      method: 'DELETE',
    });
  }

  async transferEmployee(id: number, data: {
    to_department_id?: number;
    to_designation?: string;
    to_manager_id?: number;
    transfer_date?: string;
    reason?: string;
  }): Promise<ApiResponse<any>> {
    return this.request(`/crm/employees/${id}/transfer`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getOrgHierarchy(): Promise<ApiResponse<{ departments: DepartmentItem[] }>> {
    return this.request('/crm/hr/hierarchy');
  }

  async getHrDashboardMetrics(): Promise<ApiResponse<HrDashboardMetrics>> {
    return this.request('/crm/hr/dashboard');
  }

  // Departments
  async getDepartments(): Promise<ApiResponse<DepartmentItem[]>> {
    return this.request('/crm/departments');
  }

  async getDepartment(id: number): Promise<ApiResponse<DepartmentItem>> {
    return this.request(`/crm/departments/${id}`);
  }

  async createDepartment(data: Partial<DepartmentItem>): Promise<ApiResponse<DepartmentItem>> {
    return this.request('/crm/departments', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateDepartment(id: number, data: Partial<DepartmentItem>): Promise<ApiResponse<DepartmentItem>> {
    return this.request(`/crm/departments/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteDepartment(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/departments/${id}`, {
      method: 'DELETE',
    });
  }

  // Shifts & Holidays
  async getShifts(): Promise<ApiResponse<ShiftItem[]>> {
    return this.request('/crm/shifts');
  }

  async getShift(id: number): Promise<ApiResponse<ShiftItem>> {
    return this.request(`/crm/shifts/${id}`);
  }

  async createShift(data: Partial<ShiftItem>): Promise<ApiResponse<ShiftItem>> {
    return this.request('/crm/shifts', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateShift(id: number, data: Partial<ShiftItem>): Promise<ApiResponse<ShiftItem>> {
    return this.request(`/crm/shifts/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteShift(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/shifts/${id}`, {
      method: 'DELETE',
    });
  }

  async getCompanyHolidays(year?: number): Promise<ApiResponse<CompanyHolidayItem[]>> {
    return this.request(`/crm/shifts/holidays${year ? `?year=${year}` : ''}`);
  }

  async createCompanyHoliday(data: Partial<CompanyHolidayItem>): Promise<ApiResponse<CompanyHolidayItem>> {
    return this.request('/crm/shifts/holidays', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async deleteCompanyHoliday(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/shifts/holidays/${id}`, {
      method: 'DELETE',
    });
  }

  // Attendance
  async getAttendance(params?: { date?: string; start_date?: string; end_date?: string; employee_id?: number; status?: string }): Promise<ApiResponse<AttendanceItem[]>> {
    const query = new URLSearchParams();
    if (params?.date) query.append('date', params.date);
    if (params?.start_date && params?.end_date) {
      query.append('start_date', params.start_date);
      query.append('end_date', params.end_date);
    }
    if (params?.employee_id) query.append('employee_id', String(params.employee_id));
    if (params?.status) query.append('status', params.status);
    const qs = query.toString();
    return this.request(`/crm/attendance${qs ? `?${qs}` : ''}`);
  }

  async getAttendanceCalendar(params?: { year?: number; month?: number; employee_id?: number; department_id?: number }): Promise<ApiResponse<any>> {
    const query = new URLSearchParams();
    if (params?.year) query.append('year', String(params.year));
    if (params?.month) query.append('month', String(params.month));
    if (params?.employee_id) query.append('employee_id', String(params.employee_id));
    if (params?.department_id) query.append('department_id', String(params.department_id));
    const qs = query.toString();
    return this.request(`/crm/attendance/calendar${qs ? `?${qs}` : ''}`);
  }

  async checkInAttendance(data?: {
    date?: string;
    check_in?: string;
    location?: string;
    notes?: string;
    latitude?: number;
    longitude?: number;
    accuracy_meters?: number;
    verification_method?: string;
    selfie_url?: string;
    is_field_checkin?: boolean;
    branch_id?: number;
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/attendance/checkin', {
      method: 'POST',
      body: JSON.stringify(data || {}),
    });
  }

  async checkOutAttendance(data?: {
    date?: string;
    check_out?: string;
    latitude?: number;
    longitude?: number;
    accuracy_meters?: number;
    verification_method?: string;
    selfie_url?: string;
    is_field_checkin?: boolean;
    branch_id?: number;
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/attendance/checkout', {
      method: 'POST',
      body: JSON.stringify(data || {}),
    });
  }

  async getAttendanceCorrections(status?: string): Promise<ApiResponse<AttendanceCorrectionItem[]>> {
    return this.request(`/crm/attendance/corrections${status ? `?status=${status}` : ''}`);
  }

  async requestAttendanceCorrection(data: { date: string; requested_check_in?: string; requested_check_out?: string; reason: string }): Promise<ApiResponse<AttendanceCorrectionItem>> {
    return this.request('/crm/attendance/corrections', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async reviewAttendanceCorrection(id: number, status: 'approved' | 'rejected', reviewer_notes?: string): Promise<ApiResponse<AttendanceCorrectionItem>> {
    return this.request(`/crm/attendance/corrections/${id}/review`, {
      method: 'POST',
      body: JSON.stringify({ status, reviewer_notes }),
    });
  }

  // Leaves & Balances
  async getLeaves(params?: { status?: string; employee_id?: number; year?: number }): Promise<ApiResponse<LeaveItem[]>> {
    const query = new URLSearchParams();
    if (params?.status) query.append('status', params.status);
    if (params?.employee_id) query.append('employee_id', String(params.employee_id));
    if (params?.year) query.append('year', String(params.year));
    const qs = query.toString();
    return this.request(`/crm/leaves${qs ? `?${qs}` : ''}`);
  }

  async getLeave(id: number): Promise<ApiResponse<LeaveItem>> {
    return this.request(`/crm/leaves/${id}`);
  }

  async createLeave(data: Partial<LeaveItem>): Promise<ApiResponse<LeaveItem>> {
    return this.request('/crm/leaves', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async approveLeave(id: number, manager_notes?: string): Promise<ApiResponse<LeaveItem>> {
    return this.request(`/crm/leaves/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify({ manager_notes }),
    });
  }

  async rejectLeave(id: number, manager_notes?: string): Promise<ApiResponse<LeaveItem>> {
    return this.request(`/crm/leaves/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ manager_notes }),
    });
  }

  async cancelLeave(id: number): Promise<ApiResponse<LeaveItem>> {
    return this.request(`/crm/leaves/${id}/cancel`, {
      method: 'POST',
    });
  }

  async getLeaveTypes(): Promise<ApiResponse<LeaveTypeItem[]>> {
    return this.request('/crm/leaves/types');
  }

  async createLeaveType(data: Partial<LeaveTypeItem>): Promise<ApiResponse<LeaveTypeItem>> {
    return this.request('/crm/leaves/types', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getLeaveBalances(employeeId?: number, year?: number): Promise<ApiResponse<LeaveBalanceItem[]>> {
    const query = new URLSearchParams();
    if (year) query.append('year', String(year));
    const qs = query.toString();
    return this.request(`/crm/leaves/balances${employeeId ? `/${employeeId}` : ''}${qs ? `?${qs}` : ''}`);
  }

  async adjustLeaveBalance(data: { employee_id: number; leave_type_id: number; adjustment_days: number; reason: string }): Promise<ApiResponse<LeaveBalanceItem>> {
    return this.request('/crm/leaves/balances/adjust', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // --- HRM PAYROLL, DOCUMENTS, PRODUCTIVITY & REMINDERS ---
  async getSalaryStructures(): Promise<ApiResponse<SalaryStructureItem[]>> {
    return this.request('/crm/payroll/structures');
  }

  async createSalaryStructure(data: Partial<SalaryStructureItem>): Promise<ApiResponse<SalaryStructureItem>> {
    return this.request('/crm/payroll/structures', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getEmployeeSalary(employeeId: number): Promise<ApiResponse<EmployeeSalaryItem>> {
    return this.request(`/crm/payroll/employees/${employeeId}/salary`);
  }

  async assignEmployeeSalary(employeeId: number, data: {
    salary_structure_id?: number;
    ctc_monthly?: number;
    ctc_annual?: number;
    effective_from: string;
    notes?: string;
  }): Promise<ApiResponse<EmployeeSalaryItem>> {
    return this.request(`/crm/payroll/employees/${employeeId}/salary`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getPayrollBatches(params?: { year?: number; status?: string }): Promise<ApiResponse<PayrollBatchItem[]>> {
    const query = new URLSearchParams();
    if (params?.year) query.append('year', String(params.year));
    if (params?.status) query.append('status', params.status);
    const qs = query.toString();
    return this.request(`/crm/payroll/batches${qs ? `?${qs}` : ''}`);
  }

  async generatePayrollBatch(data: { month: number; year: number; branch_id?: number }): Promise<ApiResponse<PayrollBatchItem>> {
    return this.request('/crm/payroll/batches/generate', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getPayrollBatch(batchId: number): Promise<ApiResponse<PayrollBatchItem>> {
    return this.request(`/crm/payroll/batches/${batchId}`);
  }

  async approvePayrollBatch(batchId: number): Promise<ApiResponse<PayrollBatchItem>> {
    return this.request(`/crm/payroll/batches/${batchId}/approve`, {
      method: 'POST',
    });
  }

  async disbursePayrollBatch(batchId: number): Promise<ApiResponse<PayrollBatchItem>> {
    return this.request(`/crm/payroll/batches/${batchId}/disburse`, {
      method: 'POST',
    });
  }

  async getPayslips(params?: { payroll_batch_id?: number; employee_id?: number; month?: number; year?: number }): Promise<ApiResponse<PayslipItem[]>> {
    const query = new URLSearchParams();
    if (params?.payroll_batch_id) query.append('payroll_batch_id', String(params.payroll_batch_id));
    if (params?.employee_id) query.append('employee_id', String(params.employee_id));
    if (params?.month) query.append('month', String(params.month));
    if (params?.year) query.append('year', String(params.year));
    const qs = query.toString();
    return this.request(`/crm/payroll/payslips${qs ? `?${qs}` : ''}`);
  }

  async getPayslip(payslipId: number): Promise<ApiResponse<PayslipItem>> {
    return this.request(`/crm/payroll/payslips/${payslipId}`);
  }

  async calculateFullAndFinal(data: {
    employee_id: number;
    resignation_date?: string;
    last_working_date?: string;
    notice_required_days?: number;
    notice_served_days?: number;
    gratuity_eligible?: boolean;
    years_of_service?: number;
    other_recoveries?: number;
    remarks?: string;
  }): Promise<ApiResponse<FullAndFinalSettlementResult>> {
    return this.request('/crm/payroll/full-and-final', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getEmployeeDocuments(employeeId: number): Promise<ApiResponse<EmployeeDocumentItem[]>> {
    return this.request(`/crm/employees/${employeeId}/documents`);
  }

  async createEmployeeDocument(employeeId: number, data: Partial<EmployeeDocumentItem>): Promise<ApiResponse<EmployeeDocumentItem>> {
    return this.request(`/crm/employees/${employeeId}/documents`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async verifyEmployeeDocument(employeeId: number, docId: number, data?: { notes?: string }): Promise<ApiResponse<EmployeeDocumentItem>> {
    return this.request(`/crm/employees/${employeeId}/documents/${docId}/verify`, {
      method: 'POST',
      body: JSON.stringify(data || {}),
    });
  }

  async deleteEmployeeDocument(employeeId: number, docId: number): Promise<ApiResponse<any>> {
    return this.request(`/crm/employees/${employeeId}/documents/${docId}`, {
      method: 'DELETE',
    });
  }

  async getEmployeeProductivity(employeeId: number): Promise<ApiResponse<any>> {
    return this.request(`/crm/employees/${employeeId}/productivity`);
  }

  async getEmployeeReviews(employeeId: number): Promise<ApiResponse<EmployeePerformanceReviewItem[]>> {
    return this.request(`/crm/employees/${employeeId}/reviews`);
  }

  async createEmployeeReview(employeeId: number, data: Partial<EmployeePerformanceReviewItem>): Promise<ApiResponse<EmployeePerformanceReviewItem>> {
    return this.request(`/crm/employees/${employeeId}/reviews`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getEmployeeGoals(employeeId: number): Promise<ApiResponse<EmployeeProductivityGoalItem[]>> {
    return this.request(`/crm/employees/${employeeId}/goals`);
  }

  async createEmployeeGoal(employeeId: number, data: Partial<EmployeeProductivityGoalItem>): Promise<ApiResponse<EmployeeProductivityGoalItem>> {
    return this.request(`/crm/employees/${employeeId}/goals`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getHrReminders(): Promise<ApiResponse<{ reminders: HrReminderItem[]; summary: { total: number; urgent: number; high: number } }>> {
    return this.request('/crm/hr/reminders');
  }

  // --- PROJECTS MANAGEMENT ---
  async getProjects(params: {
    status?: string;
    priority?: string;
    archived?: boolean;
    customer_id?: number;
    company_id?: number;
    owner_id?: number;
    q?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<ProjectItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/projects${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getProject(id: number): Promise<ApiResponse<ProjectItem>> {
    return this.request(`/crm/projects/${id}`);
  }

  async createProject(data: Partial<ProjectItem> & { team_members?: any[]; milestones?: any[] }): Promise<ApiResponse<ProjectItem>> {
    return this.request('/crm/projects', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateProject(id: number, data: Partial<ProjectItem> & { team_members?: any[] }): Promise<ApiResponse<ProjectItem>> {
    return this.request(`/crm/projects/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteProject(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/projects/${id}`, { method: 'DELETE' });
  }

  async archiveProject(id: number): Promise<ApiResponse<ProjectItem>> {
    return this.request(`/crm/projects/${id}/archive`, { method: 'POST' });
  }

  async restoreProject(id: number): Promise<ApiResponse<ProjectItem>> {
    return this.request(`/crm/projects/${id}/restore`, { method: 'POST' });
  }

  async getProjectMilestones(projectId: number): Promise<ApiResponse<ProjectMilestoneItem[]>> {
    return this.request(`/crm/projects/${projectId}/milestones`);
  }

  async createProjectMilestone(projectId: number, data: Partial<ProjectMilestoneItem>): Promise<ApiResponse<ProjectMilestoneItem>> {
    return this.request(`/crm/projects/${projectId}/milestones`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateProjectMilestone(projectId: number, milestoneId: number, data: Partial<ProjectMilestoneItem>): Promise<ApiResponse<ProjectMilestoneItem>> {
    return this.request(`/crm/projects/${projectId}/milestones/${milestoneId}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteProjectMilestone(projectId: number, milestoneId: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/projects/${projectId}/milestones/${milestoneId}`, { method: 'DELETE' });
  }

  async addProjectMember(projectId: number, data: { user_id: number; role?: string }): Promise<ApiResponse<ProjectTeamMember[]>> {
    return this.request(`/crm/projects/${projectId}/members`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async removeProjectMember(projectId: number, userId: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/projects/${projectId}/members/${userId}`, { method: 'DELETE' });
  }

  async getProjectComments(projectId: number): Promise<ApiResponse<ProjectCommentItem[]>> {
    return this.request(`/crm/projects/${projectId}/comments`);
  }

  async createProjectComment(projectId: number, data: { comment: string; attachments?: any[] }): Promise<ApiResponse<ProjectCommentItem>> {
    return this.request(`/crm/projects/${projectId}/comments`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getProjectMetrics(): Promise<ApiResponse<ProjectMetricsData>> {
    return this.request('/crm/projects/metrics');
  }

  async createProjectFromDeal(dealId: number, overrides: any = {}): Promise<ApiResponse<ProjectItem>> {
    return this.request('/crm/projects/create-from-deal', {
      method: 'POST',
      body: JSON.stringify({ deal_id: dealId, ...overrides }),
    });
  }

  async createProjectFromQuotation(quotationId: number, overrides: any = {}): Promise<ApiResponse<ProjectItem>> {
    return this.request('/crm/projects/create-from-quotation', {
      method: 'POST',
      body: JSON.stringify({ quotation_id: quotationId, ...overrides }),
    });
  }

  async transitionProjectStatus(projectId: number, status: string, reason?: string): Promise<ApiResponse<ProjectItem>> {
    return this.request(`/crm/projects/${projectId}/transition-status`, {
      method: 'POST',
      body: JSON.stringify({ status, reason }),
    });
  }

  async getProjectFinancials(projectId: number): Promise<ApiResponse<ProjectFinancialMetrics>> {
    return this.request(`/crm/projects/${projectId}/financials`);
  }

  async getProjectGantt(projectId: number): Promise<ApiResponse<GanttChartData>> {
    return this.request(`/crm/projects/${projectId}/gantt`);
  }

  async getProjectWorkload(branchId?: number): Promise<ApiResponse<TeamWorkloadItem[]>> {
    const q = branchId ? `?branch_id=${branchId}` : '';
    return this.request(`/crm/projects/workload${q}`);
  }

  async getProjectDocuments(projectId: number, clientOnly: boolean = false): Promise<ApiResponse<ProjectDocumentItem[]>> {
    const q = clientOnly ? '?client_only=true' : '';
    return this.request(`/crm/projects/${projectId}/documents${q}`);
  }

  async uploadProjectDocument(projectId: number, data: Partial<ProjectDocumentItem>): Promise<ApiResponse<ProjectDocumentItem>> {
    return this.request(`/crm/projects/${projectId}/documents`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async toggleProjectDocumentVisibility(documentId: number, isClientVisible: boolean): Promise<ApiResponse<ProjectDocumentItem>> {
    return this.request(`/crm/project-documents/${documentId}/visibility`, {
      method: 'PATCH',
      body: JSON.stringify({ is_client_visible: isClientVisible }),
    });
  }

  async deleteProjectDocument(documentId: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/project-documents/${documentId}`, { method: 'DELETE' });
  }

  async submitMilestoneForReview(milestoneId: number, summary?: string): Promise<ApiResponse<ProjectMilestoneItem>> {
    return this.request(`/crm/milestones/${milestoneId}/submit-review`, {
      method: 'POST',
      body: JSON.stringify({ deliverables_summary: summary }),
    });
  }

  async approveMilestoneInternal(milestoneId: number, notes?: string): Promise<ApiResponse<ProjectMilestoneItem>> {
    return this.request(`/crm/milestones/${milestoneId}/approve-internal`, {
      method: 'POST',
      body: JSON.stringify({ notes }),
    });
  }

  async sendMilestoneToClient(milestoneId: number, notes?: string): Promise<ApiResponse<ProjectMilestoneItem>> {
    return this.request(`/crm/milestones/${milestoneId}/send-to-client`, {
      method: 'POST',
      body: JSON.stringify({ notes }),
    });
  }

  async getPortalMilestone(token: string): Promise<ApiResponse<ProjectMilestoneItem>> {
    return this.request(`/portal/milestones/${token}`);
  }

  async clientApproveMilestone(token: string, clientName: string, feedback?: string): Promise<ApiResponse<ProjectMilestoneItem>> {
    return this.request(`/portal/milestones/${token}/approve`, {
      method: 'POST',
      body: JSON.stringify({ client_name: clientName, feedback }),
    });
  }

  async clientRevisionMilestone(token: string, clientName: string, feedback: string): Promise<ApiResponse<ProjectMilestoneItem>> {
    return this.request(`/portal/milestones/${token}/revision`, {
      method: 'POST',
      body: JSON.stringify({ client_name: clientName, feedback }),
    });
  }

  // --- ENHANCED TASK TIME LOGS & LIVE TIMER ---
  async startTaskTimer(taskId: number): Promise<ApiResponse<TaskTimeLogItem>> {
    return this.request(`/crm/tasks/${taskId}/start-timer`, {
      method: 'POST',
    });
  }

  async stopTaskTimer(logId: number, description?: string): Promise<ApiResponse<TaskTimeLogItem>> {
    return this.request(`/crm/tasks/stop-timer/${logId}`, {
      method: 'POST',
      body: JSON.stringify({ description }),
    });
  }

  async approveTimeLog(logId: number): Promise<ApiResponse<TaskTimeLogItem>> {
    return this.request(`/crm/tasks/timesheets/${logId}/approve`, {
      method: 'POST',
    });
  }

  async rejectTimeLog(logId: number, reason: string): Promise<ApiResponse<TaskTimeLogItem>> {
    return this.request(`/crm/tasks/timesheets/${logId}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async getTimesheets(params: {
    user_id?: number;
    project_id?: number;
    status?: string;
    is_running?: boolean;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<TaskTimeLogItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/tasks/timesheets${q.toString() ? `?${q.toString()}` : ''}`);
  }

  // --- ENHANCED TASK TIME LOGS & DEPENDENCIES ---
  async logTaskTime(taskId: number, data: { duration_minutes?: number; start_time?: string; end_time?: string; description?: string; is_billable?: boolean }): Promise<ApiResponse<TaskTimeLogItem>> {
    return this.request(`/crm/tasks/${taskId}/log-time`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getTaskTimeLogs(taskId: number): Promise<ApiResponse<TaskTimeLogItem[]>> {
    return this.request(`/crm/tasks/${taskId}/time-logs`);
  }

  async getTaskDependencies(taskId: number): Promise<ApiResponse<{ depends_on: TaskItem[]; blocking: TaskItem[] }>> {
    return this.request(`/crm/tasks/${taskId}/dependencies`);
  }

  async addTaskDependency(taskId: number, data: { depends_on_task_id: number; dependency_type?: string }): Promise<ApiResponse<TaskDependencyItem>> {
    return this.request(`/crm/tasks/${taskId}/dependencies`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async removeTaskDependency(taskId: number, dependsOnTaskId: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/tasks/${taskId}/dependencies/${dependsOnTaskId}`, { method: 'DELETE' });
  }

  async getTaskMetrics(params?: { my_metrics?: boolean }): Promise<ApiResponse<TaskMetricsData>> {
    const q = params?.my_metrics ? '?my_metrics=true' : '';
    return this.request(`/crm/tasks/metrics${q}`);
  }

  // --- MEETINGS & CALENDAR ---
  async getMeetings(params: {
    status?: string;
    date_from?: string;
    date_to?: string;
    project_id?: number;
    lead_id?: number;
    deal_id?: number;
    q?: string;
    view?: 'all' | 'upcoming';
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<MeetingItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/meetings${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getMeeting(id: number): Promise<ApiResponse<MeetingItem>> {
    return this.request(`/crm/meetings/${id}`);
  }

  async scheduleMeeting(data: Partial<MeetingItem> & { attendees?: any[] }): Promise<ApiResponse<MeetingItem>> {
    return this.request('/crm/meetings', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateMeeting(id: number, data: Partial<MeetingItem> & { attendees?: any[] }): Promise<ApiResponse<MeetingItem>> {
    return this.request(`/crm/meetings/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteMeeting(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/meetings/${id}`, { method: 'DELETE' });
  }

  async rescheduleMeeting(id: number, data: { meeting_date: string; start_time: string; end_time?: string; agenda?: string; meet_link?: string; location_type?: string; location_address?: string }): Promise<ApiResponse<MeetingItem>> {
    return this.request(`/crm/meetings/${id}/reschedule`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateMeetingRsvp(id: number, data: { attendee_identifier: number | string; status: string; notes?: string }): Promise<ApiResponse<MeetingAttendeeItem>> {
    return this.request(`/crm/meetings/${id}/rsvp`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async createMeetingFollowUpTask(id: number, data: { title?: string; description?: string; due_date?: string; assigned_user_id?: number; priority?: string }): Promise<ApiResponse<TaskItem>> {
    return this.request(`/crm/meetings/${id}/follow-up-task`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getMeetingCalendarEvents(params?: { start_date?: string; end_date?: string; my_calendar?: boolean }): Promise<ApiResponse<any[]>> {
    const q = new URLSearchParams();
    if (params?.start_date) q.append('start_date', params.start_date);
    if (params?.end_date) q.append('end_date', params.end_date);
    if (params?.my_calendar) q.append('my_calendar', 'true');
    return this.request(`/crm/meetings/calendar${q.toString() ? `?${q.toString()}` : ''}`);
  }

  // --- CUSTOMER SUPPORT, HELPDESK & AI ASSISTANCE ---
  async getTickets(params: {
    status?: string;
    priority?: string;
    source_channel?: string;
    category_id?: number;
    assigned_user_id?: number;
    customer_id?: number;
    sla_status?: string;
    q?: string;
    view?: 'all' | 'my_tickets' | 'open' | 'resolved' | 'closed' | 'breached';
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<SupportTicketItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/tickets${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getTicket(id: number): Promise<ApiResponse<SupportTicketItem>> {
    return this.request(`/crm/tickets/${id}`);
  }

  async createTicket(data: Partial<SupportTicketItem>): Promise<ApiResponse<SupportTicketItem>> {
    return this.request('/crm/tickets', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateTicket(id: number, data: Partial<SupportTicketItem>): Promise<ApiResponse<SupportTicketItem>> {
    return this.request(`/crm/tickets/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteTicket(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/tickets/${id}`, { method: 'DELETE' });
  }

  async assignTicket(id: number, data: { assigned_user_id: number; assigned_team_id?: number }): Promise<ApiResponse<SupportTicketItem>> {
    return this.request(`/crm/tickets/${id}/assign`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getTicketMessages(id: number): Promise<ApiResponse<SupportTicketMessageItem[]>> {
    return this.request(`/crm/tickets/${id}/messages`);
  }

  async sendTicketMessage(id: number, data: { message: string; is_internal?: boolean; is_draft?: boolean; channel?: string; attachments?: any[]; status?: string; sender_type?: string }): Promise<ApiResponse<SupportTicketMessageItem>> {
    return this.request(`/crm/tickets/${id}/messages`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async resolveTicket(id: number, data: { resolution_notes: string }): Promise<ApiResponse<SupportTicketItem>> {
    return this.request(`/crm/tickets/${id}/resolve`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async closeTicket(id: number, data?: { closure_reason?: string }): Promise<ApiResponse<SupportTicketItem>> {
    return this.request(`/crm/tickets/${id}/close`, {
      method: 'POST',
      body: JSON.stringify(data || {}),
    });
  }

  async reopenTicket(id: number, data: { reopen_reason: string }): Promise<ApiResponse<SupportTicketItem>> {
    return this.request(`/crm/tickets/${id}/reopen`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async submitTicketCsat(id: number, data: { rating: number; comment?: string }): Promise<ApiResponse<SupportTicketItem>> {
    return this.request(`/crm/tickets/${id}/csat`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getTicketAiAssistance(id: number, context?: string): Promise<ApiResponse<SupportAiAssistanceData>> {
    return this.request(`/crm/tickets/${id}/ai-assistance`, {
      method: 'POST',
      body: JSON.stringify({ context }),
    });
  }

  async getTicketAnalytics(): Promise<ApiResponse<SupportAnalyticsData>> {
    return this.request('/crm/tickets/analytics');
  }

  async getTicketCategories(): Promise<ApiResponse<SupportTicketCategoryItem[]>> {
    return this.request('/crm/tickets/categories');
  }

  async createTicketCategory(data: Partial<SupportTicketCategoryItem>): Promise<ApiResponse<SupportTicketCategoryItem>> {
    return this.request('/crm/tickets/categories', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateTicketCategory(id: number, data: Partial<SupportTicketCategoryItem>): Promise<ApiResponse<SupportTicketCategoryItem>> {
    return this.request(`/crm/tickets/categories/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteTicketCategory(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/tickets/categories/${id}`, { method: 'DELETE' });
  }

  async getTicketSlaPolicies(): Promise<ApiResponse<SupportSlaPolicyItem[]>> {
    return this.request('/crm/tickets/sla-policies');
  }

  async createTicketSlaPolicy(data: Partial<SupportSlaPolicyItem>): Promise<ApiResponse<SupportSlaPolicyItem>> {
    return this.request('/crm/tickets/sla-policies', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateTicketSlaPolicy(id: number, data: Partial<SupportSlaPolicyItem>): Promise<ApiResponse<SupportSlaPolicyItem>> {
    return this.request(`/crm/tickets/sla-policies/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteTicketSlaPolicy(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/tickets/sla-policies/${id}`, { method: 'DELETE' });
  }

  // --------------------------------------------------------------------------
  // Prompt 42: Customer Support, Transitions, Customer Success & Knowledge Base
  // --------------------------------------------------------------------------

  async getTicketTransitions(id: number): Promise<ApiResponse<SupportTicketTransitionItem[]>> {
    return this.request(`/crm/tickets/${id}/transitions`);
  }

  async bulkUpdateTickets(data: { ticket_ids: number[]; status?: string; assigned_user_id?: number; priority?: string }): Promise<ApiResponse<{ updated_count: number }>> {
    return this.request('/crm/tickets/bulk-update', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getCustomerHealthScores(params: { status?: string; churn_risk?: string } = {}): Promise<ApiResponse<CustomerHealthScoreItem[]>> {
    const q = new URLSearchParams();
    if (params.status) q.append('status', params.status);
    if (params.churn_risk) q.append('churn_risk', params.churn_risk);
    return this.request(`/crm/customer-success/health-scores${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getCustomerHealthScore(customerId: number): Promise<ApiResponse<CustomerHealthScoreItem>> {
    return this.request(`/crm/customer-success/health-scores/${customerId}`);
  }

  async recordCustomerCheckIn(customerId: number, data: { notes: string; next_check_in_date?: string; onboarding_status?: string }): Promise<ApiResponse<CustomerHealthScoreItem>> {
    return this.request(`/crm/customer-success/check-ins/${customerId}`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getWarrantyContracts(params: { customer_id?: number; status?: string; contract_type?: string } = {}): Promise<ApiResponse<WarrantyContractItem[]>> {
    const q = new URLSearchParams();
    if (params.customer_id) q.append('customer_id', String(params.customer_id));
    if (params.status) q.append('status', params.status);
    if (params.contract_type) q.append('contract_type', params.contract_type);
    return this.request(`/crm/customer-success/contracts${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createWarrantyContract(data: Partial<WarrantyContractItem>): Promise<ApiResponse<WarrantyContractItem>> {
    return this.request('/crm/customer-success/contracts', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async recordContractMaintenance(id: number, data: { notes?: string }): Promise<ApiResponse<WarrantyContractItem>> {
    return this.request(`/crm/customer-success/contracts/${id}/maintenance`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getCustomerFeedback(params: { customer_id?: number } = {}): Promise<ApiResponse<CustomerFeedbackItem[]>> {
    const q = params.customer_id ? `?customer_id=${params.customer_id}` : '';
    return this.request(`/crm/customer-success/feedback${q}`);
  }

  async submitCustomerFeedback(data: Partial<CustomerFeedbackItem>): Promise<ApiResponse<CustomerFeedbackItem>> {
    return this.request('/crm/customer-success/feedback', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getKnowledgeCategories(): Promise<ApiResponse<KnowledgeCategoryItem[]>> {
    return this.request('/crm/knowledge-base/categories');
  }

  async getKnowledgeArticles(params: { search?: string; category_id?: number; visibility?: string; only_published?: boolean } = {}): Promise<ApiResponse<KnowledgeArticleItem[]>> {
    const q = new URLSearchParams();
    if (params.search) q.append('search', params.search);
    if (params.category_id) q.append('category_id', String(params.category_id));
    if (params.visibility) q.append('visibility', params.visibility);
    if (params.only_published !== undefined) q.append('only_published', String(params.only_published));
    return this.request(`/crm/knowledge-base/articles${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getKnowledgeArticle(id: number): Promise<ApiResponse<KnowledgeArticleItem>> {
    return this.request(`/crm/knowledge-base/articles/${id}`);
  }

  async createKnowledgeArticle(data: Partial<KnowledgeArticleItem>): Promise<ApiResponse<KnowledgeArticleItem>> {
    return this.request('/crm/knowledge-base/articles', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async voteKnowledgeArticle(id: number, helpful: boolean): Promise<ApiResponse<KnowledgeArticleItem>> {
    return this.request(`/crm/knowledge-base/articles/${id}/vote`, {
      method: 'POST',
      body: JSON.stringify({ helpful }),
    });
  }

  async suggestAiKnowledgeAnswer(query: string): Promise<ApiResponse<AiAnswerSuggestion>> {
    return this.request('/crm/knowledge-base/ai-suggest', {
      method: 'POST',
      body: JSON.stringify({ query }),
    });
  }

  // ==========================================
  // Analytics, Attribution & Custom Reports
  // ==========================================

  async getExecutiveAnalytics(params?: {
    from_date?: string;
    to_date?: string;
    employee_id?: number;
  }): Promise<ApiResponse<ExecutiveAnalyticsData>> {
    const qs = new URLSearchParams();
    if (params?.from_date) qs.append('from_date', params.from_date);
    if (params?.to_date) qs.append('to_date', params.to_date);
    if (params?.employee_id) qs.append('employee_id', String(params.employee_id));
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/analytics/executive${query}`);
  }

  async getLeadAnalytics(params?: {
    from_date?: string;
    to_date?: string;
    employee_id?: number;
  }): Promise<ApiResponse<LeadAnalyticsData>> {
    const qs = new URLSearchParams();
    if (params?.from_date) qs.append('from_date', params.from_date);
    if (params?.to_date) qs.append('to_date', params.to_date);
    if (params?.employee_id) qs.append('employee_id', String(params.employee_id));
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/analytics/leads${query}`);
  }

  async getSalesAnalytics(params?: {
    from_date?: string;
    to_date?: string;
    employee_id?: number;
  }): Promise<ApiResponse<SalesAnalyticsData>> {
    const qs = new URLSearchParams();
    if (params?.from_date) qs.append('from_date', params.from_date);
    if (params?.to_date) qs.append('to_date', params.to_date);
    if (params?.employee_id) qs.append('employee_id', String(params.employee_id));
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/analytics/sales${query}`);
  }

  async getAttributionAnalytics(params?: {
    from_date?: string;
    to_date?: string;
  }): Promise<ApiResponse<AttributionComparisonData>> {
    const qs = new URLSearchParams();
    if (params?.from_date) qs.append('from_date', params.from_date);
    if (params?.to_date) qs.append('to_date', params.to_date);
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/analytics/attribution${query}`);
  }

  async recordTouchpoint(data: Record<string, any>): Promise<ApiResponse<any>> {
    return this.request('/crm/analytics/attribution/touchpoint', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async exportAnalyticsReport(params: {
    report_type: string;
    from_date?: string;
    to_date?: string;
    columns?: string;
    format?: string;
  }): Promise<ApiResponse<ReportExportResult>> {
    const qs = new URLSearchParams();
    qs.append('report_type', params.report_type);
    if (params.from_date) qs.append('from_date', params.from_date);
    if (params.to_date) qs.append('to_date', params.to_date);
    if (params.columns) qs.append('columns', params.columns);
    if (params.format) qs.append('format', params.format);
    return this.request(`/crm/analytics/export?${qs.toString()}`);
  }

  async getSavedReports(): Promise<ApiResponse<SavedReportItem[]>> {
    return this.request('/crm/analytics/saved-reports');
  }

  async createSavedReport(data: Partial<SavedReportItem>): Promise<ApiResponse<SavedReportItem>> {
    return this.request('/crm/analytics/saved-reports', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async deleteSavedReport(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/analytics/saved-reports/${id}`, { method: 'DELETE' });
  }

  // ----------------------------------------------------
  // Prompt 49: Advanced BI Dashboard, Report Builder & Field Sales
  // ----------------------------------------------------

  async getOwnerDashboardMetrics(params?: {
    from_date?: string;
    to_date?: string;
    branch_id?: number;
    employee_id?: number;
    department_id?: number;
  }): Promise<ApiResponse<OwnerDashboardMetrics>> {
    const qs = new URLSearchParams();
    if (params?.from_date) qs.append('from_date', params.from_date);
    if (params?.to_date) qs.append('to_date', params.to_date);
    if (params?.branch_id) qs.append('branch_id', String(params.branch_id));
    if (params?.employee_id) qs.append('employee_id', String(params.employee_id));
    if (params?.department_id) qs.append('department_id', String(params.department_id));
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/analytics/owner-dashboard${query}`);
  }

  async getDashboardLayouts(): Promise<ApiResponse<DashboardWidgetLayoutItem[]>> {
    return this.request('/crm/analytics/dashboard-layouts');
  }

  async saveDashboardLayout(data: {
    name: string;
    role?: string;
    is_default?: boolean;
    widgets: any[];
    filters?: Record<string, any>;
  }): Promise<ApiResponse<DashboardWidgetLayoutItem>> {
    return this.request('/crm/analytics/dashboard-layouts', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateDashboardLayout(
    id: number,
    data: Partial<DashboardWidgetLayoutItem>
  ): Promise<ApiResponse<DashboardWidgetLayoutItem>> {
    return this.request(`/crm/analytics/dashboard-layouts/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteDashboardLayout(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/analytics/dashboard-layouts/${id}`, { method: 'DELETE' });
  }

  async getCustomReportCatalog(): Promise<ApiResponse<CustomReportModuleCatalog>> {
    return this.request('/crm/analytics/report-builder/catalog');
  }

  async previewCustomReport(config: {
    module: string;
    from_date?: string;
    to_date?: string;
    filters?: any[];
    selected_columns?: string[];
    aggregations?: Record<string, any>;
    group_by?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
    chart_type?: string;
    relations?: string[];
    limit?: number;
  }): Promise<ApiResponse<CustomReportPreviewResult>> {
    return this.request('/crm/analytics/report-builder/preview', {
      method: 'POST',
      body: JSON.stringify(config),
    });
  }

  async saveCustomReport(data: {
    title: string;
    description?: string;
    module: string;
    report_type?: string;
    group_by?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
    chart_type?: string;
    filters?: any[];
    selected_columns?: string[];
    aggregations?: Record<string, any>;
    is_scheduled?: boolean;
    schedule_frequency?: 'daily' | 'weekly' | 'monthly';
    schedule_recipients?: string[];
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/analytics/report-builder/save', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async scheduleCustomReport(
    id: number,
    data: {
      is_scheduled: boolean;
      schedule_frequency?: 'daily' | 'weekly' | 'monthly';
      schedule_recipients?: string[];
    }
  ): Promise<ApiResponse<any>> {
    return this.request(`/crm/analytics/report-builder/${id}/schedule`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getSalesDeepDive(params?: { from_date?: string; to_date?: string }): Promise<ApiResponse<any>> {
    const qs = new URLSearchParams();
    if (params?.from_date) qs.append('from_date', params.from_date);
    if (params?.to_date) qs.append('to_date', params.to_date);
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/analytics/sales-deep-dive${query}`);
  }

  async getMarketingDeepDive(params?: { from_date?: string; to_date?: string }): Promise<ApiResponse<any>> {
    const qs = new URLSearchParams();
    if (params?.from_date) qs.append('from_date', params.from_date);
    if (params?.to_date) qs.append('to_date', params.to_date);
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/analytics/marketing-deep-dive${query}`);
  }

  async getFinanceDeepDive(params?: { from_date?: string; to_date?: string }): Promise<ApiResponse<any>> {
    const qs = new URLSearchParams();
    if (params?.from_date) qs.append('from_date', params.from_date);
    if (params?.to_date) qs.append('to_date', params.to_date);
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/analytics/finance-deep-dive${query}`);
  }

  async getAiBusinessInsights(params?: { from_date?: string; to_date?: string }): Promise<ApiResponse<{
    total_insights: number;
    generated_at: string;
    generated_at_ist: string;
    insights: AiBusinessInsightsItem[];
  }>> {
    const qs = new URLSearchParams();
    if (params?.from_date) qs.append('from_date', params.from_date);
    if (params?.to_date) qs.append('to_date', params.to_date);
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/analytics/ai-insights${query}`);
  }

  async getCustomAnalyticsAlerts(): Promise<ApiResponse<CustomAnalyticsAlertItem[]>> {
    return this.request('/crm/analytics/alerts');
  }

  async createCustomAnalyticsAlert(data: {
    name: string;
    metric_type: string;
    condition_operator: string;
    threshold_value: number;
    notification_channels: string[];
    branch_id?: number;
    is_active?: boolean;
  }): Promise<ApiResponse<CustomAnalyticsAlertItem>> {
    return this.request('/crm/analytics/alerts', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateCustomAnalyticsAlert(
    id: number,
    data: Partial<CustomAnalyticsAlertItem>
  ): Promise<ApiResponse<CustomAnalyticsAlertItem>> {
    return this.request(`/crm/analytics/alerts/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteCustomAnalyticsAlert(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/analytics/alerts/${id}`, { method: 'DELETE' });
  }

  async evaluateCustomAnalyticsAlerts(): Promise<ApiResponse<{
    total_alerts_checked: number;
    triggered_count: number;
    triggered_alerts: any[];
  }>> {
    return this.request('/crm/analytics/alerts/evaluate', { method: 'POST' });
  }

  async getFieldSalesVisits(params?: {
    employee_id?: number;
    branch_id?: number;
    status?: string;
    date?: string;
    page?: number;
  }): Promise<ApiResponse<FieldSalesVisitItem[]>> {
    const qs = new URLSearchParams();
    if (params?.employee_id) qs.append('employee_id', String(params.employee_id));
    if (params?.branch_id) qs.append('branch_id', String(params.branch_id));
    if (params?.status) qs.append('status', params.status);
    if (params?.date) qs.append('date', params.date);
    if (params?.page) qs.append('page', String(params.page));
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/field-sales/visits${query}`);
  }

  async scheduleFieldSalesVisit(data: {
    employee_id: number;
    customer_id?: number;
    lead_id?: number;
    branch_id?: number;
    title: string;
    scheduled_at: string;
    route_waypoints?: any[];
    offline_client_uuid?: string;
  }): Promise<ApiResponse<FieldSalesVisitItem>> {
    return this.request('/crm/field-sales/visits', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async checkInFieldSalesVisit(
    id: number,
    data: { latitude: number; longitude: number; address?: string }
  ): Promise<ApiResponse<FieldSalesVisitItem>> {
    return this.request(`/crm/field-sales/visits/${id}/check-in`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async checkOutFieldSalesVisit(
    id: number,
    data: {
      check_out_lat?: number;
      check_out_lng?: number;
      check_out_address?: string;
      distance_travelled_km?: number;
      visit_notes: string;
      outcome: string;
      follow_up_date?: string;
      travel_expense_amount?: number;
    }
  ): Promise<ApiResponse<FieldSalesVisitItem>> {
    return this.request(`/crm/field-sales/visits/${id}/check-out`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getFieldSalesDailyPlan(employeeId?: number, date?: string): Promise<ApiResponse<FieldSalesDailyPlan>> {
    const qs = new URLSearchParams();
    if (employeeId) qs.append('employee_id', String(employeeId));
    if (date) qs.append('date', date);
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/field-sales/daily-plan${query}`);
  }

  async getFieldSalesAnalytics(params?: {
    from_date?: string;
    to_date?: string;
    branch_id?: number;
    employee_id?: number;
  }): Promise<ApiResponse<FieldSalesAnalytics>> {
    const qs = new URLSearchParams();
    if (params?.from_date) qs.append('from_date', params.from_date);
    if (params?.to_date) qs.append('to_date', params.to_date);
    if (params?.branch_id) qs.append('branch_id', String(params.branch_id));
    if (params?.employee_id) qs.append('employee_id', String(params.employee_id));
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/field-sales/analytics${query}`);
  }

  async syncOfflineFieldSalesDrafts(drafts: any[]): Promise<ApiResponse<{
    synced_count: number;
    duplicates_ignored: number;
    synced_visit_ids: number[];
    disclaimer: string;
  }>> {
    return this.request('/crm/field-sales/sync-offline', {
      method: 'POST',
      body: JSON.stringify({ drafts }),
    });
  }

  async syncOfflineDrafts(drafts: any[]): Promise<ApiResponse<{
    synced_count: number;
    duplicates_ignored: number;
    synced_visit_ids: number[];
    disclaimer: string;
    processed?: number;
  }>> {
    return this.syncOfflineFieldSalesDrafts(drafts);
  }

  async getIndiaSettings(): Promise<ApiResponse<IndiaSettingsItem>> {
    return this.request('/crm/india/settings');
  }

  async formatInrAmount(amount: number): Promise<ApiResponse<{ raw_amount: number; formatted_inr: string; formatted_short: string }>> {
    return this.request('/crm/india/format-inr', {
      method: 'POST',
      body: JSON.stringify({ amount }),
    });
  }

  async getIndiaTranslations(lang: string = 'en'): Promise<ApiResponse<{ language: string; translations: Record<string, string> }>> {
    return this.request(`/crm/india/translations/${lang}`);
  }

  // ==========================================
  // Integrations, API Keys & Webhook Platform
  // ==========================================

  async getIntegrations(): Promise<ApiResponse<IntegrationDirectoryItem[]>> {
    return this.request('/crm/integrations');
  }

  async generateIntegrationOAuthUrl(
    provider: string,
    redirectUri: string
  ): Promise<ApiResponse<{ provider: string; auth_url: string; state_token: string; expires_at: string }>> {
    return this.request('/crm/integrations/oauth-url', {
      method: 'POST',
      body: JSON.stringify({ provider, redirect_uri: redirectUri }),
    });
  }

  async handleIntegrationOAuthCallback(data: {
    provider: string;
    code: string;
    state: string;
    redirect_uri: string;
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/integrations/oauth-callback', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async connectIntegrationCredentials(data: {
    provider: string;
    name?: string;
    credentials: Record<string, any>;
    metadata?: Record<string, any>;
  }): Promise<ApiResponse<any>> {
    return this.request('/crm/integrations/connect-credentials', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async disconnectIntegration(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/integrations/${id}/disconnect`, { method: 'POST' });
  }

  async testIntegrationConnection(id: number): Promise<ApiResponse<any>> {
    return this.request(`/crm/integrations/${id}/test`, { method: 'POST' });
  }

  async syncIntegration(id: number): Promise<ApiResponse<any>> {
    return this.request(`/crm/integrations/${id}/sync`, { method: 'POST' });
  }

  async getIntegrationSyncLogs(id: number): Promise<ApiResponse<IntegrationSyncLogItem[]>> {
    return this.request(`/crm/integrations/${id}/sync-logs`);
  }

  async getApiKeys(): Promise<ApiResponse<TenantApiKeyItem[]>> {
    return this.request('/crm/api-keys');
  }

  async createApiKey(data: {
    name: string;
    scopes?: string[];
    allowed_ips?: string[];
    rate_limit_per_minute?: number;
    expires_at?: string;
  }): Promise<ApiResponse<{ api_key: TenantApiKeyItem; raw_secret: string; warning: string }>> {
    return this.request('/crm/api-keys', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async revokeApiKey(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/api-keys/${id}`, { method: 'DELETE' });
  }

  async rotateApiKey(
    id: number
  ): Promise<ApiResponse<{ api_key: TenantApiKeyItem; raw_secret: string; warning: string }>> {
    return this.request(`/crm/api-keys/${id}/rotate`, { method: 'POST' });
  }

  async getApiKeyLogs(apiKeyId?: number): Promise<ApiResponse<TenantApiRequestLogItem[]>> {
    const query = apiKeyId ? `?api_key_id=${apiKeyId}` : '';
    return this.request(`/crm/api-keys/logs${query}`);
  }

  async getWebhooks(): Promise<ApiResponse<OutboundWebhookItem[]>> {
    return this.request('/crm/webhooks');
  }

  async createWebhook(data: {
    name: string;
    url: string;
    events: string[];
    is_active?: boolean;
    retry_limit?: number;
    timeout_seconds?: number;
    headers?: Record<string, string>;
  }): Promise<ApiResponse<OutboundWebhookItem>> {
    return this.request('/crm/webhooks', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateWebhook(id: number, data: Partial<OutboundWebhookItem>): Promise<ApiResponse<OutboundWebhookItem>> {
    return this.request(`/crm/webhooks/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteWebhook(id: number): Promise<ApiResponse<null>> {
    return this.request(`/crm/webhooks/${id}`, { method: 'DELETE' });
  }

  async testPingWebhook(id: number): Promise<ApiResponse<WebhookDeliveryLogItem>> {
    return this.request(`/crm/webhooks/${id}/test-ping`, { method: 'POST' });
  }

  async getWebhookLogs(webhookId?: number): Promise<ApiResponse<WebhookDeliveryLogItem[]>> {
    const query = webhookId ? `?webhook_id=${webhookId}` : '';
    return this.request(`/crm/webhooks/logs${query}`);
  }

  async redeliverWebhook(id: number): Promise<ApiResponse<WebhookDeliveryLogItem>> {
    return this.request(`/crm/webhooks/logs/${id}/redeliver`, { method: 'POST' });
  }

  async getOpenApiSpec(): Promise<any> {
    return this.request('/crm/docs/openapi.json');
  }

  // ----------------------------------------------------
  // PROMPT 22: SaaS Subscription, Hybrid Billing & Credit Ledger
  // ----------------------------------------------------

  async getBillingPlans(): Promise<ApiResponse<SubscriptionPlanItem[]>> {
    return this.request('/crm/billing/plans');
  }

  async getBillingSubscription(): Promise<ApiResponse<TenantSubscriptionItem | null>> {
    return this.request('/crm/billing/subscription');
  }

  async subscribeBillingPlan(data: {
    plan_id: number;
    billing_cycle?: 'monthly' | 'annual';
    prorate?: boolean;
    billing_contact_name?: string;
    billing_contact_email?: string;
    billing_tax_id?: string;
    billing_address?: string;
  }): Promise<ApiResponse<TenantSubscriptionItem>> {
    return this.request('/crm/billing/subscription/subscribe', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async cancelBillingSubscription(reason?: string): Promise<ApiResponse<TenantSubscriptionItem>> {
    return this.request('/crm/billing/subscription/cancel', {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async updateBillingContact(data: {
    billing_contact_name?: string;
    billing_contact_email?: string;
    billing_tax_id?: string;
    billing_address?: string;
  }): Promise<ApiResponse<TenantSubscriptionItem>> {
    return this.request('/crm/billing/subscription/billing-contact', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async getBillingInvoices(): Promise<ApiResponse<SubscriptionInvoiceItem[]>> {
    return this.request('/crm/billing/invoices');
  }

  async getUsageDashboard(): Promise<ApiResponse<UsageDashboardData>> {
    return this.request('/crm/billing/usage/dashboard');
  }

  async getUsageRecords(params?: {
    meter_type?: string;
    start_date?: string;
    end_date?: string;
    per_page?: number;
  }): Promise<ApiResponse<UsageRecordItem[]>> {
    const qs = new URLSearchParams();
    if (params?.meter_type) qs.append('meter_type', params.meter_type);
    if (params?.start_date) qs.append('start_date', params.start_date);
    if (params?.end_date) qs.append('end_date', params.end_date);
    if (params?.per_page) qs.append('per_page', String(params.per_page));

    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/billing/usage/records${queryStr}`);
  }

  async reserveUsage(data: {
    meter_type: string;
    estimated_units: number;
    metadata?: Record<string, any>;
  }): Promise<ApiResponse<UsageReservationItem>> {
    return this.request('/crm/billing/usage/reserve', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async settleUsage(data: {
    reservation_id: string;
    actual_units: number;
    provider_cost?: number;
    metadata?: Record<string, any>;
  }): Promise<ApiResponse<UsageRecordItem>> {
    return this.request('/crm/billing/usage/settle', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async releaseUsageReservation(data: {
    reservation_id: string;
    reason?: string;
  }): Promise<ApiResponse<{ released: boolean }>> {
    return this.request('/crm/billing/usage/release', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getCreditWallet(): Promise<ApiResponse<CreditWalletData>> {
    return this.request('/crm/billing/wallet');
  }

  async rechargeCreditWallet(data: {
    amount: number;
    reference_id?: string;
    description?: string;
    metadata?: Record<string, any>;
  }): Promise<ApiResponse<CreditLedgerEntryItem>> {
    return this.request('/crm/billing/wallet/recharge', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateWalletAutoRecharge(data: {
    auto_recharge_enabled: boolean;
    auto_recharge_threshold?: number;
    auto_recharge_amount?: number;
  }): Promise<ApiResponse<CreditWalletData>> {
    return this.request('/crm/billing/wallet/auto-recharge', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async getCreditLedger(perPage: number = 50): Promise<ApiResponse<CreditLedgerEntryItem[]>> {
    return this.request(`/crm/billing/wallet/ledger?per_page=${perPage}`);
  }

  async verifyCreditLedgerIntegrity(): Promise<ApiResponse<{
    is_valid: boolean;
    current_wallet_balance: number;
    calculated_ledger_sum: number;
    difference: number;
    records_count: number;
    verified_at: string;
  }>> {
    return this.request('/crm/billing/wallet/verify-integrity');
  }

  async getCostProtection(): Promise<ApiResponse<CostProtectionRuleItem>> {
    return this.request('/crm/billing/cost-protection');
  }

  async updateCostProtection(data: Partial<CostProtectionRuleItem>): Promise<ApiResponse<CostProtectionRuleItem>> {
    return this.request('/crm/billing/cost-protection', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  // ----------------------------------------------------
  // PROMPT 23: Separate Platform Super Admin Console API
  // ----------------------------------------------------

  async getPlatformOverview(): Promise<ApiResponse<PlatformOverviewData>> {
    return this.request('/platform/overview');
  }

  async getPlatformTenants(params?: {
    search?: string;
    status?: string;
    tier?: string;
    page?: number;
    per_page?: number;
  }): Promise<ApiResponse<PlatformTenantItem[]> & { meta?: any }> {
    const qs = new URLSearchParams();
    if (params?.search) qs.append('search', params.search);
    if (params?.status) qs.append('status', params.status);
    if (params?.tier) qs.append('tier', params.tier);
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));

    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/platform/tenants${queryStr}`);
  }

  async getPlatformTenantDetails(id: number): Promise<ApiResponse<PlatformTenantDetailData>> {
    return this.request(`/platform/tenants/${id}`);
  }

  async updatePlatformTenantStatus(id: number, status: string, reason: string): Promise<ApiResponse<PlatformTenantItem>> {
    return this.request(`/platform/tenants/${id}/status`, {
      method: 'POST',
      body: JSON.stringify({ status, reason }),
    });
  }

  async changePlatformTenantPlan(id: number, planId: number, prorate: boolean = true): Promise<ApiResponse<TenantSubscriptionItem>> {
    return this.request(`/platform/tenants/${id}/plan`, {
      method: 'POST',
      body: JSON.stringify({ plan_id: planId, prorate }),
    });
  }

  async updatePlatformTenantLimits(id: number, limits: Record<string, any>): Promise<ApiResponse<PlatformTenantItem>> {
    return this.request(`/platform/tenants/${id}/limits`, {
      method: 'PUT',
      body: JSON.stringify({ limits }),
    });
  }

  async createBreakglassSupportSession(
    tenantId: number,
    justificationReason: string,
    durationMinutes: number = 60
  ): Promise<ApiResponse<{
    session_id: number;
    tenant_id: number;
    tenant_name: string;
    access_token: string;
    expires_at: string;
    justification_reason: string;
  }>> {
    return this.request(`/platform/tenants/${tenantId}/support-access`, {
      method: 'POST',
      body: JSON.stringify({ justification_reason: justificationReason, duration_minutes: durationMinutes }),
    });
  }

  async revokeBreakglassSupportSession(sessionId: number): Promise<ApiResponse<void>> {
    return this.request(`/platform/support-access/${sessionId}/revoke`, {
      method: 'POST',
    });
  }

  async getPlatformBillingPlans(): Promise<ApiResponse<SubscriptionPlanItem[]>> {
    return this.request('/platform/billing/plans');
  }

  async createPlatformBillingPlan(data: Partial<SubscriptionPlanItem>): Promise<ApiResponse<SubscriptionPlanItem>> {
    return this.request('/platform/billing/plans', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updatePlatformBillingPlan(id: number, data: Partial<SubscriptionPlanItem>): Promise<ApiResponse<SubscriptionPlanItem>> {
    return this.request(`/platform/billing/plans/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async getPlatformSubscriptions(status?: string): Promise<ApiResponse<TenantSubscriptionItem[]> & { meta?: any }> {
    const qs = status ? `?status=${status}` : '';
    return this.request(`/platform/billing/subscriptions${qs}`);
  }

  async getPlatformInvoices(status?: string): Promise<ApiResponse<SubscriptionInvoiceItem[]> & { meta?: any }> {
    const qs = status ? `?status=${status}` : '';
    return this.request(`/platform/billing/invoices${qs}`);
  }

  async adjustTenantCredits(tenantId: number, amount: number, reason: string): Promise<ApiResponse<CreditLedgerEntryItem>> {
    return this.request('/platform/billing/adjust-credits', {
      method: 'POST',
      body: JSON.stringify({ tenant_id: tenantId, amount, reason }),
    });
  }

  async getPlatformProviders(): Promise<ApiResponse<PlatformProviderItem[]>> {
    return this.request('/platform/providers');
  }

  async updatePlatformProvider(
    key: string,
    data: {
      name?: string;
      is_active?: boolean;
      credentials?: string | Record<string, any>;
      available_models?: string[];
      default_model?: string;
      routing_policy?: Record<string, any>;
      rate_limit_rpm?: number;
      rate_limit_tpm?: number;
    }
  ): Promise<ApiResponse<PlatformProviderItem>> {
    return this.request(`/platform/providers/${key}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async testPlatformProviderHealth(key: string): Promise<ApiResponse<{
    provider_key: string;
    status: string;
    message: string;
    checked_at: string;
  }>> {
    return this.request(`/platform/providers/${key}/test-health`, {
      method: 'POST',
    });
  }

  async getPlatformQueueStats(): Promise<ApiResponse<PlatformQueueStatsData>> {
    return this.request('/platform/system/queues');
  }

  async retryPlatformQueueJob(id: string | number): Promise<ApiResponse<void>> {
    return this.request(`/platform/system/queues/${id}/retry`, {
      method: 'POST',
    });
  }

  async purgePlatformQueueJobs(): Promise<ApiResponse<{ purged_count: number }>> {
    return this.request('/platform/system/queues/purge', {
      method: 'POST',
    });
  }

  async getPlatformFeatureFlags(): Promise<ApiResponse<PlatformFeatureFlagItem[]>> {
    return this.request('/platform/system/feature-flags');
  }

  async updatePlatformFeatureFlag(
    key: string,
    data: { is_enabled?: boolean; name?: string; description?: string; rules?: Record<string, any> }
  ): Promise<ApiResponse<PlatformFeatureFlagItem>> {
    return this.request(`/platform/system/feature-flags/${key}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async getPlatformMaintenanceMode(): Promise<ApiResponse<PlatformMaintenanceModeData>> {
    return this.request('/platform/system/maintenance');
  }

  async setPlatformMaintenanceMode(data: {
    is_enabled: boolean;
    message?: string;
    bypass_secret?: string;
  }): Promise<ApiResponse<PlatformMaintenanceModeData>> {
    return this.request('/platform/system/maintenance', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getPlatformAuditLogs(params?: {
    action?: string;
    admin_id?: number;
    target_type?: string;
    page?: number;
    per_page?: number;
  }): Promise<ApiResponse<PlatformAuditLogItem[]> & { meta?: any }> {
    const qs = new URLSearchParams();
    if (params?.action) qs.append('action', params.action);
    if (params?.admin_id) qs.append('admin_id', String(params.admin_id));
    if (params?.target_type) qs.append('target_type', params.target_type);
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));

    const queryStr = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/platform/system/audit-logs${queryStr}`);
  }

  // ==========================================
  // Analytics Tracking SDK, GA4 & Attribution
  // ==========================================

  async getTrackingSettings(): Promise<ApiResponse<TrackingSettingData>> {
    return this.request('/crm/tracking/settings');
  }

  async updateTrackingSettings(data: Partial<TrackingSettingData>): Promise<ApiResponse<TrackingSettingData>> {
    return this.request('/crm/tracking/settings', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async getTrackingEvents(params?: {
    event_name?: string;
    utm_source?: string;
    session_id?: string;
    with_leads_only?: boolean;
    page?: number;
    per_page?: number;
  }): Promise<ApiResponse<any>> {
    const qs = new URLSearchParams();
    if (params?.event_name) qs.append('event_name', params.event_name);
    if (params?.utm_source) qs.append('utm_source', params.utm_source);
    if (params?.session_id) qs.append('session_id', params.session_id);
    if (params?.with_leads_only) qs.append('with_leads_only', '1');
    if (params?.page) qs.append('page', String(params.page));
    if (params?.per_page) qs.append('per_page', String(params.per_page));

    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/tracking/events${query}`);
  }

  async getTrackingAttribution(params?: {
    from_date?: string;
    to_date?: string;
  }): Promise<ApiResponse<any>> {
    const qs = new URLSearchParams();
    if (params?.from_date) qs.append('from_date', params.from_date);
    if (params?.to_date) qs.append('to_date', params.to_date);

    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/tracking/attribution${query}`);
  }

  async getTrackingDiagnostics(): Promise<ApiResponse<TrackingDiagnosticsData>> {
    return this.request('/crm/tracking/diagnostics');
  }

  async connectGA4(data: {
    property_id: string;
    measurement_id?: string;
    api_secret?: string;
    display_name?: string;
  }): Promise<ApiResponse<TrackingSettingData>> {
    return this.request('/crm/tracking/ga4/connect', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async disconnectGA4(): Promise<ApiResponse<TrackingSettingData>> {
    return this.request('/crm/tracking/ga4/disconnect', {
      method: 'POST',
    });
  }

  async getGA4Properties(): Promise<ApiResponse<any>> {
    return this.request('/crm/tracking/ga4/properties');
  }

  async getGA4Report(params?: {
    start_date?: string;
    end_date?: string;
  }): Promise<ApiResponse<GA4ReportData>> {
    const qs = new URLSearchParams();
    if (params?.start_date) qs.append('start_date', params.start_date);
    if (params?.end_date) qs.append('end_date', params.end_date);

    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/tracking/ga4/report${query}`);
  }

  // -------------------------------------------------------------
  // PROMPT 28: Facebook Messenger & Instagram Direct Messages
  // -------------------------------------------------------------
  async getSocialAccounts(params?: { provider?: string }): Promise<ApiResponse<SocialChannelAccountItem[]>> {
    const qs = new URLSearchParams();
    if (params?.provider) qs.append('provider', params.provider);
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/social/accounts${query}`);
  }

  async connectSocialAccount(data: {
    provider: 'facebook' | 'instagram';
    page_id: string;
    page_name?: string;
    page_access_token: string;
    instagram_business_id?: string;
    instagram_username?: string;
    permissions?: string[];
    ai_enabled?: boolean;
    ai_agent_id?: number;
    ai_instructions?: string;
  }): Promise<ApiResponse<SocialChannelAccountItem>> {
    return this.request('/crm/social/accounts', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async disconnectSocialAccount(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/social/accounts/${id}/disconnect`, {
      method: 'POST',
    });
  }

  async sendSocialMessage(
    conversationId: number,
    data: {
      content?: string;
      media_url?: string;
      message_type?: string;
      tag?: string;
    }
  ): Promise<ApiResponse<any>> {
    return this.request(`/crm/social/conversations/${conversationId}/messages`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getSocialIdentities(params?: { provider?: string }): Promise<ApiResponse<CustomerSocialIdentityItem[]>> {
    const qs = new URLSearchParams();
    if (params?.provider) qs.append('provider', params.provider);
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/social/identities${query}`);
  }

  async linkSocialIdentity(
    id: number,
    data: { lead_id?: number; customer_id?: number }
  ): Promise<ApiResponse<CustomerSocialIdentityItem>> {
    return this.request(`/crm/social/identities/${id}/link`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async mergeSocialIdentities(data: {
    primary_id: number;
    secondary_id: number;
  }): Promise<ApiResponse<CustomerSocialIdentityItem>> {
    return this.request('/crm/social/identities/merge', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getSocialLogs(params?: { limit?: number }): Promise<ApiResponse<SocialWebhookEventItem[]>> {
    const qs = new URLSearchParams();
    if (params?.limit) qs.append('limit', params.limit.toString());
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.request(`/crm/social/logs${query}`);
  }

  async getSocialDiagnostics(): Promise<ApiResponse<SocialDiagnosticsData>> {
    return this.request('/crm/social/diagnostics');
  }

  // ==========================================
  // PROMPT 32: Enterprise RBAC, Custom Roles & Granular Data Permissions
  // ==========================================

  async getCrmRoles(): Promise<ApiResponse<CrmRoleItem[]> & { meta?: any }> {
    return this.request('/crm/roles');
  }

  async getCrmRoleMatrixMetadata(): Promise<ApiResponse<RoleMatrixMetadata>> {
    return this.request('/crm/roles/metadata');
  }

  async getCrmRole(id: number): Promise<ApiResponse<CrmRoleItem>> {
    return this.request(`/crm/roles/${id}`);
  }

  async createCrmRole(data: {
    name: string;
    description?: string;
    color?: string;
    is_active?: boolean;
    parent_role_id?: number;
    permissions?: CrmRolePermissionItem[];
  }): Promise<ApiResponse<CrmRoleItem>> {
    return this.request('/crm/roles', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateCrmRole(
    id: number,
    data: {
      name?: string;
      description?: string;
      color?: string;
      is_active?: boolean;
      permissions?: CrmRolePermissionItem[];
    }
  ): Promise<ApiResponse<CrmRoleItem>> {
    return this.request(`/crm/roles/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteCrmRole(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/roles/${id}`, {
      method: 'DELETE',
    });
  }

  async duplicateCrmRole(
    id: number,
    data: { name: string; description?: string }
  ): Promise<ApiResponse<CrmRoleItem>> {
    return this.request(`/crm/roles/${id}/duplicate`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async copyCrmRolePermissions(
    id: number,
    sourceRoleId: number
  ): Promise<ApiResponse<CrmRoleItem>> {
    return this.request(`/crm/roles/${id}/copy-from`, {
      method: 'POST',
      body: JSON.stringify({ source_role_id: sourceRoleId }),
    });
  }

  async compareCrmRoles(
    roleAId: number,
    roleBId: number
  ): Promise<ApiResponse<{ role_a: CrmRoleItem; role_b: CrmRoleItem; comparison: RoleComparisonItem[] }>> {
    return this.request('/crm/roles/compare', {
      method: 'POST',
      body: JSON.stringify({ role_a_id: roleAId, role_b_id: roleBId }),
    });
  }

  async getCrmRoleUsers(id: number): Promise<ApiResponse<{ role: CrmRoleItem; users: any[] }>> {
    return this.request(`/crm/roles/${id}/users`);
  }

  async assignCrmUserRole(data: {
    user_id: number;
    role_id: number;
    is_primary?: boolean;
  }): Promise<ApiResponse<void>> {
    return this.request('/crm/roles/assign', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async removeCrmUserRole(data: {
    user_id: number;
    role_id: number;
  }): Promise<ApiResponse<void>> {
    return this.request('/crm/roles/remove-user', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getUserEffectivePermissions(userId?: number): Promise<ApiResponse<any>> {
    const query = userId ? `?user_id=${userId}` : '';
    return this.request(`/crm/roles/effective-permissions${query}`);
  }

  // ----------------------------------------------------
  // ADVANCED LEAD MANAGEMENT & LEAKAGE (PROMPT 36)
  // ----------------------------------------------------

  async captureLead(data: Record<string, any>): Promise<ApiResponse<{ lead: LeadItem; is_duplicate: boolean; duplicate_lead_id?: number | null; similar_leads: any[] }>> {
    return this.request('/crm/leads/capture', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async capturePortalLead(portal: string, payload: Record<string, any>): Promise<ApiResponse<{ lead: LeadItem; is_duplicate: boolean; duplicate_lead_id?: number | null }>> {
    return this.request(`/crm/leads/portal/${portal}`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async getLeadPortals(): Promise<ApiResponse<LeadPortalIntegrationItem[]>> {
    return this.request('/crm/leads/portals');
  }

  async saveLeadPortal(data: Partial<LeadPortalIntegrationItem>): Promise<ApiResponse<LeadPortalIntegrationItem>> {
    return this.request('/crm/leads/portals', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getLeadRoutingRules(): Promise<ApiResponse<LeadRoutingRuleItem[]>> {
    return this.request('/crm/leads/routing/rules');
  }

  async saveLeadRoutingRule(data: Partial<LeadRoutingRuleItem>): Promise<ApiResponse<LeadRoutingRuleItem>> {
    return this.request('/crm/leads/routing/rules', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async deleteLeadRoutingRule(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/leads/routing/rules/${id}`, {
      method: 'DELETE',
    });
  }

  async autoRouteLead(leadId: number): Promise<ApiResponse<LeadItem>> {
    return this.request(`/crm/leads/${leadId}/auto-route`, {
      method: 'POST',
    });
  }

  async getLeadSlaPolicies(): Promise<ApiResponse<LeadSlaPolicyItem[]>> {
    return this.request('/crm/leads/sla/policies');
  }

  async saveLeadSlaPolicy(data: Partial<LeadSlaPolicyItem>): Promise<ApiResponse<LeadSlaPolicyItem>> {
    return this.request('/crm/leads/sla/policies', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async evaluateLeadSlaEscalations(): Promise<ApiResponse<{ evaluated_leads_count: number; escalated_count: number }>> {
    return this.request('/crm/leads/sla/evaluate', {
      method: 'POST',
    });
  }

  async getLeadEscalationLogs(): Promise<ApiResponse<{ data: LeadEscalationLogItem[]; total: number }>> {
    return this.request('/crm/leads/sla/escalations');
  }

  async recordLeadResponse(leadId: number, channel: string = 'call'): Promise<ApiResponse<LeadItem>> {
    return this.request(`/crm/leads/${leadId}/record-response`, {
      method: 'POST',
      body: JSON.stringify({ channel }),
    });
  }

  async getOverdueFollowups(): Promise<ApiResponse<LeadFollowupItem[]>> {
    return this.request('/crm/leads/followups/overdue');
  }

  async getLeadFollowups(leadId: number): Promise<ApiResponse<LeadFollowupItem[]>> {
    return this.request(`/crm/leads/${leadId}/followups`);
  }

  async scheduleLeadFollowup(leadId: number, data: Partial<LeadFollowupItem>): Promise<ApiResponse<LeadFollowupItem>> {
    return this.request(`/crm/leads/${leadId}/followups`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async completeLeadFollowup(id: number, data: { outcome: string; notes?: string }): Promise<ApiResponse<LeadFollowupItem>> {
    return this.request(`/crm/leads/followups/${id}/complete`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async rescheduleLeadFollowup(id: number, data: { new_scheduled_at: string; reason?: string }): Promise<ApiResponse<LeadFollowupItem>> {
    return this.request(`/crm/leads/followups/${id}/reschedule`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async transitionLeadStage(leadId: number, data: { to_stage: string; reason?: string; notes?: string }): Promise<ApiResponse<LeadItem>> {
    return this.request(`/crm/leads/${leadId}/transition-stage`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getLeadStageHistory(leadId: number): Promise<ApiResponse<LeadStageHistoryItem[]>> {
    return this.request(`/crm/leads/${leadId}/stage-history`);
  }

  async getLeadMergeRequests(): Promise<ApiResponse<LeadMergeRequestItem[]>> {
    return this.request('/crm/leads/merge-requests');
  }

  async createLeadMergeRequest(data: { master_lead_id: number; duplicate_lead_id: number; field_overrides?: any; similarity_reasons?: string[] }): Promise<ApiResponse<LeadMergeRequestItem>> {
    return this.request('/crm/leads/merge-requests', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async approveLeadMergeRequest(id: number): Promise<ApiResponse<LeadItem>> {
    return this.request(`/crm/leads/merge-requests/${id}/approve`, {
      method: 'POST',
    });
  }

  async rejectLeadMergeRequest(id: number, reason?: string): Promise<ApiResponse<LeadMergeRequestItem>> {
    return this.request(`/crm/leads/merge-requests/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async getLeadScoringConfig(): Promise<ApiResponse<LeadScoringConfigurationItem>> {
    return this.request('/crm/leads/scoring/config');
  }

  async saveLeadScoringConfig(data: Partial<LeadScoringConfigurationItem>): Promise<ApiResponse<LeadScoringConfigurationItem>> {
    return this.request('/crm/leads/scoring/config', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getLeadReportsDashboard(): Promise<ApiResponse<LeadPerformanceDashboardData>> {
    return this.request('/crm/leads/reports/dashboard');
  }

  // --- PROMPT 39: ADVANCED SALES MANAGEMENT, PROPOSALS, CONTRACTS & COMMISSIONS ---

  async transferDealOwnership(dealId: number, data: { user_id?: number; new_assigned_user_id?: number; reason?: string }): Promise<ApiResponse<DealItem>> {
    const payload = {
      new_assigned_user_id: data.new_assigned_user_id ?? data.user_id,
      user_id: data.user_id ?? data.new_assigned_user_id,
      reason: data.reason,
    };
    return this.request(`/crm/deals/${dealId}/transfer-ownership`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async reopenDeal(dealId: number, data: { stage_id?: number; reopen_stage_id?: number; reason?: string }): Promise<ApiResponse<DealItem>> {
    const payload = {
      reopen_stage_id: data.reopen_stage_id ?? data.stage_id,
      stage_id: data.stage_id ?? data.reopen_stage_id,
      reason: data.reason,
    };
    return this.request(`/crm/deals/${dealId}/reopen`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async convertDealToProject(dealId: number, data: { name?: string; description?: string } = {}): Promise<ApiResponse<any>> {
    return this.request(`/crm/deals/${dealId}/convert-to-project`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getAdvancedSalesForecast(params: {
    pipeline_id?: number;
    branch_id?: number;
    start_date?: string;
    end_date?: string;
  } = {}): Promise<ApiResponse<AdvancedSalesForecastData>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/deals/forecast${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async submitQuotationApproval(quotationId: number): Promise<ApiResponse<QuotationRecord>> {
    return this.request(`/crm/quotations/${quotationId}/submit-approval`, {
      method: 'POST',
    });
  }

  async approveQuotation(quotationId: number, notes?: string): Promise<ApiResponse<QuotationRecord>> {
    return this.request(`/crm/quotations/${quotationId}/approve`, {
      method: 'POST',
      body: JSON.stringify({ notes }),
    });
  }

  async rejectQuotation(quotationId: number, reason: string): Promise<ApiResponse<QuotationRecord>> {
    return this.request(`/crm/quotations/${quotationId}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async checkQuotationDiscount(quotationId: number, discountPercentage: number): Promise<ApiResponse<{
    requires_approval: boolean;
    rule: DiscountRuleItem | null;
    approval: DiscountApprovalItem | null;
  }>> {
    return this.request(`/crm/quotations/${quotationId}/check-discount`, {
      method: 'POST',
      body: JSON.stringify({ discount_percentage: discountPercentage }),
    });
  }

  async recordQuotationCustomerAction(quotationId: number, data: {
    action: 'accept' | 'reject';
    notes?: string;
  }): Promise<ApiResponse<QuotationRecord>> {
    return this.request(`/crm/quotations/${quotationId}/customer-action`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async sendQuotationVia(quotationId: number, data: {
    channel: 'email' | 'whatsapp';
    recipient?: string;
  }): Promise<ApiResponse<{ status: string; sent_via: string; sent_at: string }>> {
    return this.request(`/crm/quotations/${quotationId}/send-via`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getContracts(params: {
    deal_id?: number;
    customer_id?: number;
    status?: string;
    contract_type?: string;
    search?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<ContractItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/contracts${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getContract(id: number): Promise<ApiResponse<ContractItem>> {
    return this.request(`/crm/contracts/${id}`);
  }

  async createContract(data: Partial<ContractItem>): Promise<ApiResponse<ContractItem>> {
    return this.request('/crm/contracts', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateContract(id: number, data: Partial<ContractItem>): Promise<ApiResponse<ContractItem>> {
    return this.request(`/crm/contracts/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteContract(id: number): Promise<ApiResponse<void>> {
    return this.request(`/crm/contracts/${id}`, {
      method: 'DELETE',
    });
  }

  async sendContractForSignature(id: number, data: {
    signer_name: string;
    signer_email: string;
    signer_phone?: string;
    provider?: string;
  }): Promise<ApiResponse<ContractItem>> {
    return this.request(`/crm/contracts/${id}/send-for-signature`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async signContract(id: number, data: {
    signature_data: string;
    otp_code?: string;
    ip_address?: string;
  }): Promise<ApiResponse<ContractItem>> {
    return this.request(`/crm/contracts/${id}/sign`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async declineContract(id: number, reason: string): Promise<ApiResponse<ContractItem>> {
    return this.request(`/crm/contracts/${id}/decline`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async getContractTemplates(params: { category?: string } = {}): Promise<ApiResponse<ContractTemplateItem[]>> {
    const q = new URLSearchParams();
    if (params.category) q.append('category', params.category);
    return this.request(`/crm/contracts/templates${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async createContractTemplate(data: Partial<ContractTemplateItem>): Promise<ApiResponse<ContractTemplateItem>> {
    return this.request('/crm/contracts/templates', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getSalesCommissions(params: {
    user_id?: number;
    deal_id?: number;
    status?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<ApiResponse<SalesCommissionItem[]>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/sales-commissions${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getCommissionPlans(): Promise<ApiResponse<CommissionPlanItem[]>> {
    return this.request('/crm/sales-commissions/plans');
  }

  async createCommissionPlan(data: Partial<CommissionPlanItem>): Promise<ApiResponse<CommissionPlanItem>> {
    return this.request('/crm/sales-commissions/plans', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async approveCommission(id: number, notes?: string): Promise<ApiResponse<SalesCommissionItem>> {
    return this.request(`/crm/sales-commissions/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify({ notes }),
    });
  }

  async markCommissionPaid(id: number, payoutReference?: string): Promise<ApiResponse<SalesCommissionItem>> {
    return this.request(`/crm/sales-commissions/${id}/mark-paid`, {
      method: 'POST',
      body: JSON.stringify({ payout_reference: payoutReference }),
    });
  }

  async reverseCommission(id: number, reason: string): Promise<ApiResponse<SalesCommissionItem>> {
    return this.request(`/crm/sales-commissions/${id}/reverse`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async getCommissionReport(params: {
    user_id?: number;
    start_date?: string;
    end_date?: string;
  } = {}): Promise<ApiResponse<any>> {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, String(v));
    });
    return this.request(`/crm/sales-commissions/report${q.toString() ? `?${q.toString()}` : ''}`);
  }

  // -------------------------------------------------------------------------
  // PROMPT 45: Advanced Multi-Branch and Multi-Location Business Management
  // -------------------------------------------------------------------------

  async getBranches(params: { search?: string; is_active?: boolean; city?: string } = {}): Promise<ApiResponse<BranchItem[]> & { meta?: any }> {
    const q = new URLSearchParams();
    if (params.search) q.append('search', params.search);
    if (params.is_active !== undefined) q.append('is_active', String(params.is_active));
    if (params.city) q.append('city', params.city);
    return this.request(`/crm/branches${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getBranch(id: number): Promise<ApiResponse<BranchItem>> {
    return this.request(`/crm/branches/${id}`);
  }

  async createBranch(data: Partial<BranchItem> & { department_ids?: number[] }): Promise<ApiResponse<BranchItem>> {
    return this.request('/crm/branches', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateBranch(id: number, data: Partial<BranchItem> & { department_ids?: number[] }): Promise<ApiResponse<BranchItem>> {
    return this.request(`/crm/branches/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteBranch(id: number): Promise<ApiResponse<{ message: string }>> {
    return this.request(`/crm/branches/${id}`, {
      method: 'DELETE',
    });
  }

  async setBranchStatus(id: number, is_active: boolean): Promise<ApiResponse<BranchItem>> {
    return this.request(`/crm/branches/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ is_active }),
    });
  }

  async assignEmployeesToBranch(id: number, user_ids: number[], department_id?: number): Promise<ApiResponse<any>> {
    return this.request(`/crm/branches/${id}/employees`, {
      method: 'POST',
      body: JSON.stringify({ user_ids, department_id }),
    });
  }

  async transferBranchRecord(data: {
    transferable_type: string;
    transferable_id: number;
    to_branch_id: number;
    reason?: string;
    approval_notes?: string;
    metadata?: Record<string, any>;
  }): Promise<ApiResponse<BranchTransferItem>> {
    return this.request('/crm/branches/transfers', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getBranchTransferHistory(params: {
    transferable_type?: string;
    branch_id?: number;
    per_page?: number;
    page?: number;
  } = {}): Promise<ApiResponse<BranchTransferItem[]> & { meta?: any }> {
    const q = new URLSearchParams();
    if (params.transferable_type) q.append('transferable_type', params.transferable_type);
    if (params.branch_id) q.append('branch_id', String(params.branch_id));
    if (params.per_page) q.append('per_page', String(params.per_page));
    if (params.page) q.append('page', String(params.page));
    return this.request(`/crm/branches/transfers/history${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getBranchDashboard(id: number): Promise<ApiResponse<BranchDashboardMetrics>> {
    return this.request(`/crm/branches/${id}/dashboard`);
  }

  async getConsolidatedBranchAnalytics(params: {
    start_date?: string;
    end_date?: string;
    department_id?: number;
  } = {}): Promise<ApiResponse<ConsolidatedBranchAnalytics>> {
    const q = new URLSearchParams();
    if (params.start_date) q.append('start_date', params.start_date);
    if (params.end_date) q.append('end_date', params.end_date);
    if (params.department_id) q.append('department_id', String(params.department_id));
    return this.request(`/crm/branches/analytics/consolidated${q.toString() ? `?${q.toString()}` : ''}`);
  }

  async getBranchSettings(id: number): Promise<ApiResponse<any>> {
    return this.request(`/crm/branches/${id}/settings`);
  }

  async updateBranchSettings(id: number, data: any): Promise<ApiResponse<any>> {
    return this.request(`/crm/branches/${id}/settings`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async getBranchSalesTargets(id: number): Promise<ApiResponse<BranchSalesTargetItem[]>> {
    return this.request(`/crm/branches/${id}/sales-targets`);
  }

  async saveBranchSalesTarget(id: number, data: Partial<BranchSalesTargetItem>): Promise<ApiResponse<BranchSalesTargetItem>> {
    return this.request(`/crm/branches/${id}/sales-targets`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  setAuthToken(token: string | null) {
    if (token) {
      storage.setItem('crm_token', token);
    } else {
      storage.removeItem('crm_token');
    }
  }

  getAuthToken(): string | null {
    return storage.getItem('crm_token');
  }

  // ---------------------------------------------------------------------------
  // PROMPT 46: Customer Self-Service Portal & Online Appointment Booking
  // ---------------------------------------------------------------------------

  // Portal Authentication
  async portalRegister(data: { name: string; email: string; password: string; password_confirmation: string; phone?: string; branch_id?: number }): Promise<ApiResponse<{ token: string; account: CustomerAccountItem }>> {
    return this.request('/portal/auth/register', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async portalLogin(data: { email: string; password: string }): Promise<ApiResponse<{ token: string; account: CustomerAccountItem }>> {
    const res = await this.request<{ token: string; account: CustomerAccountItem }>('/portal/auth/login', {
      method: 'POST',
      body: JSON.stringify(data),
    });
    if (res.data?.token) {
      this.setAuthToken(res.data.token);
    }
    return res;
  }

  async portalLogout(): Promise<ApiResponse<{ success: boolean }>> {
    const res = await this.request<{ success: boolean }>('/portal/auth/logout', { method: 'POST' });
    this.setAuthToken(null);
    return res;
  }

  // Customer Dashboard & Profile
  async getPortalDashboard(): Promise<ApiResponse<CustomerDashboardData>> {
    return this.request('/portal/dashboard');
  }

  async getPortalProfile(): Promise<ApiResponse<{ account: CustomerAccountItem; customer: any }>> {
    return this.request('/portal/profile');
  }

  async updatePortalProfile(data: { name?: string; phone?: string; billing_address?: string; shipping_address?: string; gstin?: string }): Promise<ApiResponse<any>> {
    return this.request('/portal/profile', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async changePortalPassword(data: { current_password: string; password: string; password_confirmation: string }): Promise<ApiResponse<{ success: boolean }>> {
    return this.request('/portal/change-password', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // Quotation Management
  async getPortalQuotations(params: { page?: number; per_page?: number } = {}): Promise<ApiResponse<any>> {
    const query = new URLSearchParams();
    if (params.page) query.set('page', String(params.page));
    if (params.per_page) query.set('per_page', String(params.per_page));
    return this.request(`/portal/quotations?${query.toString()}`);
  }

  async getPortalQuotation(id: number): Promise<ApiResponse<any>> {
    return this.request(`/portal/quotations/${id}`);
  }

  async acceptPortalQuotation(id: number, notes?: string): Promise<ApiResponse<any>> {
    return this.request(`/portal/quotations/${id}/accept`, {
      method: 'POST',
      body: JSON.stringify({ notes }),
    });
  }

  async rejectPortalQuotation(id: number, reason: string): Promise<ApiResponse<any>> {
    return this.request(`/portal/quotations/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async requestPortalQuotationRevision(id: number, notes: string): Promise<ApiResponse<any>> {
    return this.request(`/portal/quotations/${id}/request-revision`, {
      method: 'POST',
      body: JSON.stringify({ notes }),
    });
  }

  // Invoices & Payments
  async getPortalInvoices(params: { page?: number; per_page?: number } = {}): Promise<ApiResponse<any>> {
    const query = new URLSearchParams();
    if (params.page) query.set('page', String(params.page));
    if (params.per_page) query.set('per_page', String(params.per_page));
    return this.request(`/portal/invoices?${query.toString()}`);
  }

  async getPortalInvoice(id: number): Promise<ApiResponse<any>> {
    return this.request(`/portal/invoices/${id}`);
  }

  async payPortalInvoice(id: number, data: { amount: number; payment_method: string; transaction_reference?: string }): Promise<ApiResponse<any>> {
    return this.request(`/portal/invoices/${id}/pay`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // Projects & Documents
  async getPortalProjects(params: { page?: number; per_page?: number } = {}): Promise<ApiResponse<any>> {
    const query = new URLSearchParams();
    if (params.page) query.set('page', String(params.page));
    if (params.per_page) query.set('per_page', String(params.per_page));
    return this.request(`/portal/projects?${query.toString()}`);
  }

  async getPortalProject(id: number): Promise<ApiResponse<any>> {
    return this.request(`/portal/projects/${id}`);
  }

  async getPortalDocuments(): Promise<ApiResponse<any[]>> {
    return this.request('/portal/documents');
  }

  // Support Tickets
  async getPortalTickets(params: { page?: number; per_page?: number } = {}): Promise<ApiResponse<any>> {
    const query = new URLSearchParams();
    if (params.page) query.set('page', String(params.page));
    if (params.per_page) query.set('per_page', String(params.per_page));
    return this.request(`/portal/tickets?${query.toString()}`);
  }

  async createPortalTicket(data: { subject: string; description: string; priority?: string; category_id?: number }): Promise<ApiResponse<any>> {
    return this.request('/portal/tickets', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getPortalTicket(id: number): Promise<ApiResponse<any>> {
    return this.request(`/portal/tickets/${id}`);
  }

  async replyPortalTicket(id: number, message: string, attachments?: any[]): Promise<ApiResponse<any>> {
    return this.request(`/portal/tickets/${id}/reply`, {
      method: 'POST',
      body: JSON.stringify({ message, attachments }),
    });
  }

  async submitPortalTicketFeedback(id: number, csat_score: number, csat_feedback?: string): Promise<ApiResponse<any>> {
    return this.request(`/portal/tickets/${id}/feedback`, {
      method: 'POST',
      body: JSON.stringify({ csat_score, csat_feedback }),
    });
  }

  // Appointments
  async getPortalAppointments(params: { page?: number; per_page?: number } = {}): Promise<ApiResponse<any>> {
    const query = new URLSearchParams();
    if (params.page) query.set('page', String(params.page));
    if (params.per_page) query.set('per_page', String(params.per_page));
    return this.request(`/portal/appointments?${query.toString()}`);
  }

  async bookPortalAppointment(data: {
    service_id: number;
    branch_id?: number;
    meeting_date: string;
    start_time: string;
    end_time?: string;
    organizer_id?: number;
    title?: string;
    notes?: string;
    timezone?: string;
    location_type?: string;
    location_address?: string;
  }): Promise<ApiResponse<any>> {
    return this.request('/portal/appointments', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async reschedulePortalAppointment(id: number, data: { meeting_date: string; start_time: string }): Promise<ApiResponse<any>> {
    return this.request(`/portal/appointments/${id}/reschedule`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async cancelPortalAppointment(id: number, reason: string): Promise<ApiResponse<any>> {
    return this.request(`/portal/appointments/${id}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async getPortalServices(): Promise<ApiResponse<BookableServiceItem[]>> {
    return this.request('/portal/services');
  }

  // Public Online Booking Page
  async getPublicBookingConfig(slug: string): Promise<ApiResponse<PublicBookingConfig>> {
    return this.request(`/public/booking/${slug}`);
  }

  async getPublicBookingSlots(slug: string, params: { service_id: number; date: string; branch_id?: number; staff_id?: number }): Promise<ApiResponse<{ date: string; service: BookableServiceItem; slots: PublicBookingSlot[] }>> {
    const query = new URLSearchParams();
    query.set('service_id', String(params.service_id));
    query.set('date', params.date);
    if (params.branch_id) query.set('branch_id', String(params.branch_id));
    if (params.staff_id) query.set('staff_id', String(params.staff_id));
    return this.request(`/public/booking/${slug}/slots?${query.toString()}`);
  }

  async submitPublicBooking(slug: string, data: {
    service_id: number;
    branch_id?: number;
    meeting_date: string;
    start_time: string;
    customer_name: string;
    customer_email: string;
    customer_phone?: string;
    notes?: string;
  }): Promise<ApiResponse<{ meeting: any; booking_reference: string }>> {
    return this.request(`/public/booking/${slug}/book`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // Admin CRM Portal & Bookable Services
  async getPortalSettings(): Promise<ApiResponse<PortalSettingItem>> {
    return this.request('/crm/portal-settings');
  }

  async updatePortalSettings(data: Partial<PortalSettingItem>): Promise<ApiResponse<PortalSettingItem>> {
    return this.request('/crm/portal-settings', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async inviteCustomerToPortal(customerId: number): Promise<ApiResponse<{ customer: any; account: CustomerAccountItem; invite_token: string; invite_url: string }>> {
    return this.request(`/crm/customers/${customerId}/invite-portal`, {
      method: 'POST',
    });
  }

  async getBookableServices(params: { branch_id?: number; is_active?: boolean } = {}): Promise<ApiResponse<BookableServiceItem[]>> {
    const query = new URLSearchParams();
    if (params.branch_id) query.set('branch_id', String(params.branch_id));
    if (params.is_active !== undefined) query.set('is_active', String(params.is_active));
    return this.request(`/crm/bookable-services?${query.toString()}`);
  }

  async createBookableService(data: Partial<BookableServiceItem>): Promise<ApiResponse<BookableServiceItem>> {
    return this.request('/crm/bookable-services', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateBookableService(id: number, data: Partial<BookableServiceItem>): Promise<ApiResponse<BookableServiceItem>> {
    return this.request(`/crm/bookable-services/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteBookableService(id: number): Promise<ApiResponse<{ success: boolean }>> {
    return this.request(`/crm/bookable-services/${id}`, {
      method: 'DELETE',
    });
  }

  // --------------------------------------------------------------------
  // PROMPT 50: Industry Templates, Entitlements & SaaS Super Admin
  // --------------------------------------------------------------------

  // Industry Templates
  async getIndustryTemplates(): Promise<ApiResponse<IndustryTemplateItem[]>> {
    return this.request('/saas/templates');
  }

  async getCurrentIndustryTemplate(): Promise<ApiResponse<TenantAppliedTemplateItem | null>> {
    return this.request('/saas/templates/current');
  }

  async getIndustryTemplate(code: string): Promise<ApiResponse<IndustryTemplateItem>> {
    return this.request(`/saas/templates/${code}`);
  }

  async applyIndustryTemplate(
    templateCode: string,
    replaceExisting: boolean = false
  ): Promise<ApiResponse<{ applied_template: TenantAppliedTemplateItem; template: IndustryTemplateItem; created_stages_count: number; created_fields_count: number }>> {
    return this.request('/saas/templates/apply', {
      method: 'POST',
      body: JSON.stringify({ template_code: templateCode, replace_existing: replaceExisting }),
    });
  }

  // SaaS Onboarding
  async registerSaasOrganization(
    payload: SaasOnboardingRegisterPayload
  ): Promise<ApiResponse<{ tenant: any; user: any; token: string; template_result?: any }>> {
    return this.request('/saas/onboarding/register', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async getSaasOnboardingStatus(): Promise<ApiResponse<{ onboarding_status: string; onboarding_step: number; is_completed: boolean; current_step: number; completed_steps: number[] }>> {
    return this.request('/saas/onboarding/status');
  }

  async saveSaasOnboardingStep(
    payload: SaasOnboardingStepPayload
  ): Promise<ApiResponse<{ onboarding_step: number; onboarding_status: string; is_completed: boolean }>> {
    return this.request('/saas/onboarding/step', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // Feature Entitlements Engine
  async getFeatureEntitlements(): Promise<ApiResponse<FeatureEntitlementCatalog>> {
    return this.request('/saas/entitlements');
  }

  async verifyFeatureEntitlement(
    featureKey: string
  ): Promise<ApiResponse<FeatureEntitlementVerification>> {
    return this.request('/saas/entitlements/verify', {
      method: 'POST',
      body: JSON.stringify({ feature_key: featureKey }),
    });
  }

  // Platform Super Admin Console
  async getPlatformConsoleOverview(): Promise<ApiResponse<PlatformConsoleOverview>> {
    return this.request('/platform/console/overview');
  }

  async suspendTenant(
    id: number,
    reason: string
  ): Promise<ApiResponse<{ tenant: PlatformTenantItem; message: string }>> {
    return this.request(`/platform/console/tenants/${id}/suspend`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }

  async reactivateTenant(
    id: number
  ): Promise<ApiResponse<{ tenant: PlatformTenantItem; message: string }>> {
    return this.request(`/platform/console/tenants/${id}/reactivate`, {
      method: 'POST',
    });
  }

  async changeTenantPlan(
    id: number,
    planCode: string,
    billingCycle: string = 'monthly'
  ): Promise<ApiResponse<{ subscription: any; message: string }>> {
    return this.request(`/platform/console/tenants/${id}/change-plan`, {
      method: 'POST',
      body: JSON.stringify({ plan_code: planCode, billing_cycle: billingCycle }),
    });
  }

  async getPlatformPlans(): Promise<ApiResponse<PlatformPlanItem[]>> {
    return this.request('/platform/console/plans');
  }

  async savePlatformPlan(
    data: Partial<PlatformPlanItem>
  ): Promise<ApiResponse<PlatformPlanItem>> {
    return this.request('/platform/console/plans', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getPlatformRevenueReport(
    year?: number
  ): Promise<ApiResponse<PlatformRevenueReport>> {
    const query = new URLSearchParams();
    if (year) query.set('year', String(year));
    return this.request(`/platform/console/revenue?${query.toString()}`);
  }

  async getPlatformAnnouncements(): Promise<ApiResponse<PlatformAnnouncementItem[]>> {
    return this.request('/platform/console/announcements');
  }

  async createPlatformAnnouncement(
    data: Partial<PlatformAnnouncementItem>
  ): Promise<ApiResponse<PlatformAnnouncementItem>> {
    return this.request('/platform/console/announcements', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getPlatformBackups(): Promise<ApiResponse<PlatformBackupRecordItem[]>> {
    return this.request('/platform/console/backups');
  }

  async triggerPlatformBackup(
    data: { backup_type?: string; notes?: string } = {}
  ): Promise<ApiResponse<PlatformBackupRecordItem>> {
    return this.request('/platform/console/backups', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }
}

export interface CrmRoleItem {
  id: number;
  tenant_id: number | null;
  name: string;
  slug: string;
  description: string | null;
  is_system: boolean;
  is_active: boolean;
  color: string;
  parent_role_id: number | null;
  permissions_count?: number;
  users_count?: number;
  permissions?: CrmRolePermissionItem[];
  created_at?: string;
  updated_at?: string;
}

export interface CrmRolePermissionItem {
  id?: number;
  role_id?: number;
  module: string;
  action: string;
  data_scope: string;
  scope_constraints?: Record<string, any> | null;
}

export interface RoleMatrixMetadata {
  modules: Record<string, string>;
  actions: Record<string, string>;
  data_scopes: Record<string, string>;
}

export interface RoleComparisonItem {
  module: string;
  action: string;
  role_a_has: boolean;
  role_a_scope: string | null;
  role_b_has: boolean;
  role_b_scope: string | null;
  is_different: boolean;
}

export const api = new ApiClient();
export const apiClient = api;
export { ApiClientError };
