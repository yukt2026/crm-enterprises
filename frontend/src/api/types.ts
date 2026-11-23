/**
 * Enterprise AI CRM SaaS - Typed API Response Contracts
 */

export interface ApiResponse<T = any> {
  success: boolean;
  status_code: number;
  message: string;
  data: T;
  meta?: PaginationMeta;
}

export interface ApiErrorResponse {
  success: false;
  status_code: number;
  message: string;
  errors?: Record<string, string[]>;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  has_more_pages?: boolean;
  unread_count?: number;
}

export interface HealthCheckData {
  status: 'healthy' | 'degraded';
  version: string;
  laravel_version: string;
  php_version: string;
  environment: string;
  timestamp: string;
  services: {
    database: {
      connection: string;
      status: 'connected' | 'error' | string;
      latency_ms: number | null;
    };
    cache: {
      driver: string;
      status: string;
    };
    queue: {
      driver: string;
      status: string;
    };
    storage: {
      disk: string;
      status: string;
    };
  };
}

export interface AuthLoginResponse {
  token?: string;
  requires2FA?: boolean;
  userId?: string;
  user?: {
    id: string;
    name: string;
    email: string;
    role: string;
    avatar?: string | null;
    status: 'active' | 'inactive';
    department?: string;
    twoFactorEnabled: boolean;
    lastActive?: string;
    createdAt?: string;
  };
}

// --- SHARED PLATFORM SERVICE CONTRACTS ---

export interface NotificationItem {
  id: number;
  user_id: number;
  type: string;
  title: string;
  message: string;
  action_url?: string | null;
  read_at?: string | null;
  channel: string;
  data?: Record<string, any> | null;
  created_at: string;
}

export interface NotificationPreference {
  id?: number;
  user_id: number;
  notification_type: string;
  in_app_enabled: boolean;
  email_enabled: boolean;
  push_enabled: boolean;
}

export interface MediaFileItem {
  id: number;
  uuid: string;
  user_id: number;
  fileable_type?: string | null;
  fileable_id?: number | null;
  file_name: string;
  original_name: string;
  mime_type: string;
  file_size: number;
  disk: string;
  storage_path: string;
  is_private: boolean;
  retention_days?: number | null;
  metadata?: Record<string, any> | null;
  download_url?: string;
  created_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface CustomFieldDefinition {
  id: number;
  tenant_id: number;
  entity_type: 'lead' | 'deal' | 'contact' | 'company' | 'customer' | 'project' | 'task' | 'ticket' | 'employee' | 'invoice';
  name: string;
  field_key: string;
  type: 'text' | 'long_text' | 'number' | 'date' | 'select' | 'multi_select' | 'checkbox' | 'email' | 'phone' | 'url' | 'currency' | 'file';
  options?: string[] | null;
  is_required: boolean;
  default_value?: string | null;
  display_order: number;
  validation_rules?: Record<string, any> | null;
  visibility_roles?: string[] | null;
  created_at?: string;
}

export interface CustomFieldValue {
  id: number;
  custom_field_definition_id: number;
  entity_type: string;
  entity_id: number;
  value: any;
  definition?: CustomFieldDefinition;
}

export interface SearchResultItem {
  id: string;
  title: string;
  subtitle?: string;
  module: string;
  url: string;
  snippet?: string;
  status?: string;
  created_at?: string;
}

export interface AuditLogEntry {
  id: number;
  uuid: string;
  user_id?: number | null;
  user_name?: string | null;
  role?: string | null;
  action: string;
  auditable_type?: string | null;
  auditable_id?: string | null;
  old_values?: Record<string, any> | null;
  new_values?: Record<string, any> | null;
  ip_address?: string | null;
  user_agent?: string | null;
  url?: string | null;
  method?: string | null;
  created_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
    role: string;
  };
}

// --- CRM CORE CONTRACTS ---

export interface LeadItem {
  id: number;
  tenant_id: number;
  company_id?: number | null;
  company_name?: string | null;
  name: string;
  email?: string | null;
  phone?: string | null;
  alternate_phone?: string | null;
  designation?: string | null;
  address?: string | null;
  city?: string | null;
  state?: string | null;
  country?: string | null;
  postal_code?: string | null;
  requirement?: string | null;
  product_interest?: string | null;
  estimated_value: number;
  expected_purchase_date?: string | null;
  status: 'new' | 'attempted' | 'contacted' | 'qualified' | 'nurturing' | 'converted' | 'lost' | 'disqualified' | string;
  lifecycle_stage?: string;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  qualification_status: 'unqualified' | 'in_qualification' | 'qualified' | 'disqualified';
  score: number;
  score_breakdown?: Record<string, { points: number; max: number; details: string }> | null;
  assigned_user_id?: number | null;
  source?: string | null;
  campaign?: string | null;
  utm_source?: string | null;
  utm_medium?: string | null;
  utm_campaign?: string | null;
  tags?: string[] | null;
  notes?: string | null;
  is_archived: boolean;
  converted_at?: string | null;
  converted_contact_id?: number | null;
  converted_company_id?: number | null;
  converted_deal_id?: number | null;
  created_at: string;
  updated_at: string;
  company?: {
    id: number;
    name: string;
    industry?: string | null;
  };
  assignedUser?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface CrmActivityItem {
  id: number;
  tenant_id: number;
  user_id?: number | null;
  lead_id?: number | null;
  contact_id?: number | null;
  company_id?: number | null;
  deal_id?: number | null;
  customer_id?: number | null;
  activity_type: 'note' | 'call' | 'email' | 'sms' | 'whatsapp' | 'meeting' | 'task' | 'status_change' | 'assignment' | 'conversion' | 'audit' | string;
  title: string;
  description?: string | null;
  metadata?: Record<string, any> | null;
  scheduled_at?: string | null;
  completed_at?: string | null;
  created_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface Customer360Profile {
  id: number;
  name: string;
  email?: string | null;
  phone?: string | null;
  status: string;
  tier?: string;
  company?: {
    id: number;
    name: string;
    industry?: string | null;
    website?: string | null;
    phone?: string | null;
    address?: string | null;
    account_status?: string | null;
  } | null;
  contact?: {
    id: number;
    name: string;
    job_title?: string | null;
    email?: string | null;
    phone?: string | null;
    consent_status?: string | null;
  } | null;
  lead_origin?: {
    id: number;
    source?: string | null;
    campaign?: string | null;
    utm_source?: string | null;
    utm_medium?: string | null;
    utm_campaign?: string | null;
    first_touch?: Record<string, any> | null;
    last_touch?: Record<string, any> | null;
    score: number;
    converted_at?: string | null;
  } | null;
  deals: {
    items: any[];
    total_count: number;
    total_value: number;
    won_value: number;
  };
  timeline: CrmActivityItem[];
  attachments: MediaFileItem[];
}

export interface DuplicateMatch {
  entity_type: string;
  id: number;
  name: string;
  company?: string | null;
  email?: string | null;
  phone?: string | null;
  status?: string | null;
  confidence: 'exact' | 'high' | 'medium';
  match_reasons: string[];
  created_at?: string | null;
}

export interface LeadConversionPayload {
  company_id?: number;
  company_name?: string;
  create_deal?: boolean;
  deal_name?: string;
  deal_value?: number;
  deal_stage?: string;
  close_date?: string;
}

// ==========================================
// LEAD ACQUISITION HUB TYPES
// ==========================================

export interface LeadSourceItem {
  id: number;
  workspace_id: number;
  provider: string;
  name: string;
  account_id?: string | null;
  status: 'connected' | 'disconnected' | 'error' | 'pending_auth';
  credentials?: Record<string, any>;
  config?: Record<string, any>;
  sync_status: 'idle' | 'syncing' | 'failed' | 'success';
  last_synced_at?: string | null;
  last_error?: string | null;
  forms_count?: number;
  events_count?: number;
  created_at: string;
  updated_at: string;
}

export interface LeadFormItem {
  id: number;
  workspace_id: number;
  lead_source_id?: number | null;
  provider: string;
  name: string;
  external_form_id?: string | null;
  public_uuid: string;
  fields_config: Array<{ name: string; label: string; type: string; required: boolean; options?: string[] }>;
  is_active: boolean;
  campaign_id?: string | null;
  campaign_name?: string | null;
  submissions_count: number;
  last_submission_at?: string | null;
  source?: LeadSourceItem | null;
  created_at: string;
  updated_at: string;
  embed_snippet?: string;
  hosted_url?: string;
}

export interface LeadFieldMappingItem {
  id: number;
  workspace_id: number;
  lead_source_id?: number | null;
  provider: string;
  source_field: string;
  crm_field: string;
  transform_rule?: string | null;
  default_value?: string | null;
  is_required: boolean;
  source?: LeadSourceItem | null;
  created_at: string;
}

export interface LeadIngestionEventItem {
  id: number;
  workspace_id: number;
  lead_source_id?: number | null;
  provider: string;
  event_id: string;
  status: 'pending' | 'processing' | 'processed' | 'failed' | 'duplicate' | 'skipped';
  raw_payload?: string | null;
  normalized_payload?: Record<string, any> | null;
  lead_id?: number | null;
  error_message?: string | null;
  retry_count: number;
  max_retries: number;
  ip_address?: string | null;
  user_agent?: string | null;
  processed_at?: string | null;
  created_at: string;
  source?: LeadSourceItem | null;
  lead?: {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
  } | null;
}

export interface AdCampaignMetricItem {
  id: number;
  workspace_id: number;
  lead_source_id?: number | null;
  provider: string;
  campaign_id: string;
  campaign_name: string;
  ad_group_id?: string | null;
  ad_group_name?: string | null;
  ad_id?: string | null;
  ad_name?: string | null;
  date: string;
  spend: number;
  currency: string;
  impressions: number;
  clicks: number;
  leads_count: number;
  cost_per_lead: number;
  source?: LeadSourceItem | null;
}

export interface LeadAcquisitionStats {
  total_leads: number;
  leads_by_source: Record<string, number>;
  total_spend: number;
  total_impressions: number;
  total_clicks: number;
  total_ad_leads: number;
  cost_per_lead: number;
  total_events: number;
  failed_events: number;
}

// --- SALES PIPELINE, DEALS, TASKS & SALES TEAMS CONTRACTS ---

export interface PipelineStageItem {
  id: number;
  pipeline_id: number;
  name: string;
  code: string;
  probability: number;
  order_column: number;
  color: string;
  is_won: boolean;
  is_lost: boolean;
  is_default?: boolean;
  requires_approval?: boolean;
  allowed_transitions?: number[] | null;
  required_fields?: string[] | null;
  stale_days_threshold: number;
  created_at?: string;
  updated_at?: string;
}

export interface PipelineItem {
  id: number;
  name: string;
  description?: string | null;
  business_model?: 'b2b_enterprise' | 'b2b_smb' | 'b2c_high_touch' | 'channel_partner' | 'general' | null;
  department?: string | null;
  branch_id?: number | null;
  owner_user_id?: number | null;
  is_default: boolean;
  is_active: boolean;
  stages_count?: number;
  stages?: PipelineStageItem[];
  owner_user?: { id: number; name: string; email?: string } | null;
  created_at?: string;
  updated_at?: string;
}

export interface DealProductItem {
  id: number;
  deal_id: number;
  product_id?: number | null;
  name: string;
  quantity: number;
  unit_price: number;
  total_price: number;
  discount: number;
}

export interface DealStageHistoryItem {
  id: number;
  deal_id: number;
  stage_id?: number | null;
  stage: string;
  duration_days?: number | null;
  changed_by?: number | null;
  notes?: string | null;
  created_at: string;
  changed_user?: { id: number; name: string } | null;
}

export interface DealItem {
  id: number;
  pipeline_id?: number | null;
  pipeline_stage_id?: number | null;
  name: string;
  value: number;
  currency: string;
  probability: number;
  weighted_value: number;
  stage: string;
  stage_updated_at?: string | null;
  expected_close_date?: string | null;
  customer_id?: number | null;
  company_id?: number | null;
  contact_id?: number | null;
  lead_id?: number | null;
  branch_id?: number | null;
  sales_team?: string | null;
  notes?: string | null;
  next_follow_up_at?: string | null;
  reopened_at?: string | null;
  reopened_by_user_id?: number | null;
  project_id?: number | null;
  assigned_user_id?: number | null;
  collaborator_ids?: number[] | null;
  competitor?: string | null;
  lost_reason?: string | null;
  source?: string | null;
  campaign?: string | null;
  utm_source?: string | null;
  utm_medium?: string | null;
  utm_campaign?: string | null;
  is_archived: boolean;
  won_at?: string | null;
  lost_at?: string | null;
  created_at: string;
  pipeline?: PipelineItem | null;
  pipeline_stage?: PipelineStageItem | null;
  customer?: { id: number; name: string; email?: string; phone?: string } | null;
  company?: { id: number; name: string; email?: string } | null;
  contact?: { id: number; name: string; email?: string; phone?: string } | null;
  lead?: { id: number; name: string; email?: string; phone?: string } | null;
  assigned_user?: { id: number; name: string; email?: string } | null;
  reopened_by_user?: { id: number; name: string; email?: string } | null;
  products?: DealProductItem[];
  stage_histories?: DealStageHistoryItem[];
  activities?: any[];
  tasks?: any[];
}

export interface DealForecastData {
  total_open_pipeline_value: number;
  total_weighted_forecast: number;
  total_won_value: number;
  total_lost_value: number;
  win_rate_percentage: number;
  total_deals_count: number;
  stage_breakdown: Array<{
    stage: string;
    count: number;
    total_value: number;
    weighted_value: number;
  }>;
}

export interface StaleDealItem {
  deal: DealItem;
  days_in_stage: number;
  threshold_days: number;
  stale_level: 'warning' | 'critical';
}

export interface TaskChecklistItem {
  id: string;
  text: string;
  completed: boolean;
}

export interface TaskItem {
  id: number;
  title: string;
  description?: string | null;
  assigned_user_id?: number | null;
  created_by?: number | null;
  lead_id?: number | null;
  contact_id?: number | null;
  company_id?: number | null;
  deal_id?: number | null;
  ticket_id?: number | null;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  status: 'todo' | 'in_progress' | 'done' | 'cancelled';
  start_date?: string | null;
  due_date?: string | null;
  reminder_at?: string | null;
  is_recurring: boolean;
  recurring_rule?: 'daily' | 'weekly' | 'monthly' | null;
  checklist_items?: TaskChecklistItem[] | null;
  completed_at?: string | null;
  cancelled_at?: string | null;
  created_at: string;
  assigned_user?: { id: number; name: string; email?: string } | null;
  creator?: { id: number; name: string; email?: string } | null;
  deal?: { id: number; name: string; value: number } | null;
  lead?: { id: number; name: string } | null;
  contact?: { id: number; name: string } | null;
  company?: { id: number; name: string } | null;
}

export interface SalesTeamItem {
  id: number;
  name: string;
  leader_id?: number | null;
  member_ids?: number[] | null;
  description?: string | null;
  leader?: { id: number; name: string; email: string } | null;
  members?: Array<{ id: number; name: string; email: string }>;
  created_at: string;
}

export interface SalesQuotaItem {
  id: number;
  sales_team_id?: number | null;
  user_id?: number | null;
  period: 'monthly' | 'quarterly' | 'yearly';
  start_date: string;
  end_date: string;
  target_revenue: number;
  target_deals_count: number;
  target_leads_count: number;
  sales_team?: SalesTeamItem | null;
  user?: { id: number; name: string; email: string } | null;
}

export interface SalesLeaderboardEntry {
  user_id: number;
  name: string;
  email: string;
  role: string;
  rank: number;
  revenue_won: number;
  deals_won_count: number;
  deals_lost_count: number;
  win_rate: number;
  target_revenue: number;
  attainment_percent: number;
  leads_handled: number;
  tasks_completed: number;
}

export interface SalesLeaderboardData {
  period: string;
  start_date: string;
  end_date: string;
  leaderboard: SalesLeaderboardEntry[];
}

// --- PRODUCT CATALOG, INVENTORY & PROCUREMENT CONTRACTS ---

export interface ProductCategoryItem {
  id: number;
  name: string;
  slug?: string;
  parent_id?: number | null;
  description?: string | null;
  products_count?: number;
}

export interface ProductVariantItem {
  id: number;
  product_id: number;
  name: string;
  sku: string;
  attribute_values?: Record<string, string> | null;
  sale_price?: number | null;
  cost_price?: number | null;
  barcode?: string | null;
  status: 'active' | 'inactive';
}

export interface WarehouseItem {
  id: number;
  name: string;
  code: string;
  contact_name?: string | null;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  city?: string | null;
  state?: string | null;
  country?: string | null;
  is_primary: boolean;
  is_active: boolean;
  stocks_count?: number;
}

export interface ProductStockItem {
  id: number;
  product_id: number;
  product_variant_id?: number | null;
  warehouse_id: number;
  current_stock: number;
  reserved_stock: number;
  available_stock: number;
  minimum_stock: number;
  damaged_stock: number;
  product?: ProductCatalogItem | null;
  warehouse?: WarehouseItem | null;
  variant?: ProductVariantItem | null;
}

export interface ProductCatalogItem {
  id: number;
  type: 'product' | 'service';
  name: string;
  sku: string;
  category_id?: number | null;
  category?: string | null;
  brand?: string | null;
  unit: string;
  price: number;
  cost_price: number;
  tax_rate: number;
  hsn_sac_code?: string | null;
  barcode?: string | null;
  track_inventory: boolean;
  allow_negative_stock: boolean;
  service_duration?: string | null;
  service_delivery_type?: string | null;
  image_url?: string | null;
  status: 'active' | 'inactive';
  description?: string | null;
  total_stock: number;
  available_stock: number;
  category_model?: ProductCategoryItem | null;
  stocks?: ProductStockItem[];
  variants?: ProductVariantItem[];
  created_at?: string;
}

export interface StockMovementItem {
  id: number;
  product_id: number;
  product_variant_id?: number | null;
  warehouse_id: number;
  destination_warehouse_id?: number | null;
  movement_type: string;
  quantity: number;
  unit_cost: number;
  total_cost: number;
  before_stock: number;
  after_stock: number;
  reference_type?: string | null;
  reference_id?: number | null;
  notes?: string | null;
  created_at: string;
  product?: { id: number; name: string; sku: string; unit: string } | null;
  variant?: { id: number; name: string; sku: string } | null;
  warehouse?: { id: number; name: string; code: string } | null;
  user?: { id: number; name: string; email: string } | null;
}

export interface SupplierItem {
  id: number;
  name: string;
  code: string;
  contact_name?: string | null;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  city?: string | null;
  country?: string | null;
  tax_id?: string | null;
  payment_terms: string;
  currency: string;
  status: 'active' | 'inactive';
  notes?: string | null;
  purchase_orders_count?: number;
}

export interface PurchaseOrderItem {
  id: number;
  purchase_order_id: number;
  product_id: number;
  product_variant_id?: number | null;
  quantity: number;
  received_quantity: number;
  unit_cost: number;
  tax_percent: number;
  total_cost: number;
  product?: ProductCatalogItem | null;
}

export interface PurchaseOrderRecord {
  id: number;
  po_number: string;
  supplier_id: number;
  warehouse_id: number;
  order_date: string;
  expected_delivery_date?: string | null;
  status: 'draft' | 'ordered' | 'received' | 'partially_received' | 'cancelled';
  currency: string;
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total_amount: number;
  received_at?: string | null;
  notes?: string | null;
  supplier?: SupplierItem | null;
  warehouse?: WarehouseItem | null;
  items?: PurchaseOrderItem[];
}

export interface InventoryValuationData {
  total_skus: number;
  total_units: number;
  total_cost_valuation: number;
  total_retail_valuation: number;
  unrealized_margin: number;
  warehouses: Array<{
    warehouse_id: number;
    name: string;
    code: string;
    is_primary: boolean;
    total_units: number;
    total_valuation: number;
  }>;
}

export interface LowStockAlertItem {
  stock_id: number;
  product_id: number;
  product_name: string;
  sku: string;
  warehouse_id: number;
  warehouse_name: string;
  current_stock: number;
  minimum_stock: number;
  deficit: number;
}

// ----------------------------------------------------
// Finance, Quotations, Invoices, Payments & Refunds
// ----------------------------------------------------

export interface QuotationItemRecord {
  id?: number;
  product_id?: number | null;
  product_variant_id?: number | null;
  item_name: string;
  description?: string | null;
  hsn_sac_code?: string | null;
  quantity: number;
  unit_price: number;
  discount_type?: 'fixed' | 'percentage';
  discount_rate?: number;
  discount_amount?: number;
  tax_rate?: number;
  tax_amount?: number;
  total_amount?: number;
  product?: ProductCatalogItem | null;
}

export interface QuotationRecord {
  id: number;
  tenant_id: number;
  quote_number?: string;
  quotation_number: string;
  title?: string;
  version: number;
  parent_quotation_id?: number | null;
  customer_id?: number | null;
  lead_id?: number | null;
  contact_id?: number | null;
  company_id?: number | null;
  deal_id?: number | null;
  status: 'draft' | 'sent' | 'viewed' | 'accepted' | 'rejected' | 'expired';
  currency: string;
  subtotal: number;
  discount_type: 'fixed' | 'percentage';
  discount_rate: number;
  discount_amount: number;
  tax_rate: number;
  tax_amount: number;
  total_amount: number;
  is_tax_inclusive: boolean;
  template_name?: string | null;
  approval_status?: 'not_required' | 'pending_approval' | 'approved' | 'rejected';
  approved_by_user_id?: number | null;
  approved_at?: string | null;
  rejection_reason?: string | null;
  converted_to_invoice_id?: number | null;
  converted_to_invoice_at?: string | null;
  sent_via?: 'email' | 'whatsapp' | 'manual' | 'direct' | null;
  sent_at?: string | null;
  customer_action_ip?: string | null;
  customer_action_notes?: string | null;
  valid_until?: string | null;
  terms_conditions?: string | null;
  notes?: string | null;
  accepted_at?: string | null;
  rejected_at?: string | null;
  viewed_at?: string | null;
  created_at: string;
  customer?: any;
  deal?: any;
  contact?: any;
  company?: any;
  creator?: any;
  approved_by_user?: { id: number; name: string; email?: string } | null;
  converted_invoice?: any;
  items?: QuotationItemRecord[];
  revisions?: QuotationRecord[];
}

export interface InvoiceItemRecord {
  id?: number;
  product_id?: number | null;
  product_variant_id?: number | null;
  item_name: string;
  description?: string | null;
  hsn_sac_code?: string | null;
  quantity: number;
  unit_price: number;
  discount_type?: 'fixed' | 'percentage';
  discount_rate?: number;
  discount_amount?: number;
  tax_rate?: number;
  tax_amount?: number;
  total_amount?: number;
  product?: ProductCatalogItem | null;
}

export interface InvoiceRecord {
  id: number;
  tenant_id: number;
  invoice_number: string;
  quotation_id?: number | null;
  deal_id?: number | null;
  customer_id?: number | null;
  contact_id?: number | null;
  company_id?: number | null;
  currency: string;
  subtotal: number;
  discount_type: 'fixed' | 'percentage';
  discount_rate: number;
  discount_amount: number;
  tax_rate: number;
  tax_amount: number;
  shipping_amount: number;
  is_tax_inclusive: boolean;
  total_amount: number;
  paid_amount: number;
  outstanding_amount: number;
  status: 'draft' | 'issued' | 'partially_paid' | 'paid' | 'overdue' | 'void' | 'cancelled';
  due_date?: string | null;
  payment_terms?: string | null;
  billing_name?: string | null;
  billing_email?: string | null;
  billing_phone?: string | null;
  billing_address?: string | null;
  billing_tax_id?: string | null;
  notes?: string | null;
  issued_at?: string | null;
  paid_at?: string | null;
  voided_at?: string | null;
  razorpay_order_id?: string | null;
  razorpay_payment_link?: string | null;
  created_at: string;
  customer?: any;
  deal?: any;
  quotation?: any;
  items?: InvoiceItemRecord[];
  payments?: PaymentRecord[];
  refunds?: RefundRecord[];
}

export interface PaymentRecord {
  id: number;
  tenant_id: number;
  invoice_id: number;
  customer_id?: number | null;
  receipt_number: string;
  amount: number;
  currency: string;
  payment_date: string;
  payment_method: string;
  payment_channel: string;
  reference_number?: string | null;
  gateway_order_id?: string | null;
  gateway_payment_id?: string | null;
  refunded_amount: number;
  status: string;
  notes?: string | null;
  created_at: string;
  invoice?: InvoiceRecord | null;
  customer?: any;
  user?: any;
  refunds?: RefundRecord[];
}

export interface RefundRecord {
  id: number;
  tenant_id: number;
  invoice_id: number;
  payment_id: number;
  refund_number: string;
  amount: number;
  currency: string;
  reason: string;
  status: string;
  notes?: string | null;
  created_at: string;
}

export interface FinanceSummaryData {
  total_invoiced: number;
  total_collected: number;
  total_receivables: number;
  overdue_amount: number;
  overdue_count: number;
  status_breakdown: Record<string, { status: string; count: number; total: number }>;
  recent_payments: PaymentRecord[];
}

export interface PaymentGatewayConfigItem {
  id: number;
  tenant_id: number;
  provider: 'razorpay' | 'cashfree' | 'upi_qr' | 'bank_transfer';
  display_name: string;
  is_active: boolean;
  is_test_mode: boolean;
  credentials?: Record<string, any> | null;
  upi_vpa?: string | null;
  merchant_name?: string | null;
  bank_account_details?: Record<string, any> | null;
  webhook_secret?: string | null;
}

export interface UpiQrPayloadData {
  upi_string: string;
  vpa: string;
  merchant_name: string;
  amount: number;
  currency: string;
  transaction_ref: string;
  qr_svg_url: string;
}

export interface PaymentLinkItem {
  id: number;
  tenant_id: number;
  link_token: string;
  title: string;
  description?: string | null;
  payable_type?: string | null;
  payable_id?: number | null;
  customer_id?: number | null;
  amount_type: 'fixed' | 'partial' | 'advance' | 'due_amount';
  amount: number;
  min_partial_amount?: number | null;
  currency: string;
  expires_at?: string | null;
  gateway_provider: string;
  gateway_link_id?: string | null;
  gateway_link_url?: string | null;
  upi_qr_payload?: string | null;
  status: 'active' | 'paid' | 'partially_paid' | 'expired' | 'cancelled';
  paid_amount: number;
  payment_id?: number | null;
  customer_notified_at?: string | null;
  created_by_user_id?: number | null;
  customer?: any;
  payable?: any;
  created_at: string;
}

export interface FinanceTransactionItem {
  id: number;
  tenant_id: number;
  transaction_number: string;
  internal_transaction_id: string;
  provider_transaction_id?: string | null;
  payment_id?: number | null;
  invoice_id?: number | null;
  customer_id?: number | null;
  gateway: string;
  payment_method: string;
  amount: number;
  currency: string;
  fee_amount: number;
  tax_on_fee: number;
  net_amount: number;
  transaction_date: string;
  provider_status: string;
  internal_status: 'posted' | 'pending' | 'failed' | 'reversed';
  reconciliation_status: 'matched' | 'unreconciled' | 'discrepancy' | 'manual_override';
  reconciled_at?: string | null;
  reconciled_by_user_id?: number | null;
  refund_reference?: string | null;
  webhook_event_id?: string | null;
  is_posted: boolean;
  notes?: string | null;
  customer?: any;
  invoice?: any;
  created_at: string;
}

export interface PaymentAllocationItem {
  id: number;
  tenant_id: number;
  payment_id: number;
  invoice_id: number;
  allocated_amount: number;
  allocated_at: string;
  allocated_by_user_id?: number | null;
  notes?: string | null;
  payment?: PaymentRecord;
  invoice?: InvoiceRecord;
}

export interface AccountsReceivableAgeingReport {
  summary: {
    total_receivables: number;
    not_due_current: number;
    total_overdue: number;
    bucket_0_30: number;
    bucket_31_45: number;
    bucket_46_90: number;
    bucket_90_plus: number;
    disputed_amount: number;
    total_invoices_count: number;
  };
  customer_breakdown: Array<{
    customer_id: number;
    customer_name: string;
    total_outstanding: number;
    current: number;
    bucket_0_30: number;
    bucket_31_45: number;
    bucket_46_90: number;
    bucket_90_plus: number;
    invoices_count: number;
  }>;
  invoices: Array<{
    id: number;
    invoice_number: string;
    customer_name: string;
    total_amount: number;
    paid_amount: number;
    outstanding_amount: number;
    due_date?: string | null;
    days_overdue: number;
    bucket: string;
    promise_to_pay_date?: string | null;
    recovery_status: string;
    is_disputed: boolean;
    dispute_reason?: string | null;
  }>;
}

export interface VendorBillItem {
  id: number;
  tenant_id: number;
  bill_number: string;
  supplier_id: number;
  purchase_order_id?: number | null;
  bill_date: string;
  due_date: string;
  subtotal: number;
  tax_amount: number;
  total_amount: number;
  paid_amount: number;
  outstanding_amount: number;
  currency: string;
  status: 'draft' | 'pending_approval' | 'approved' | 'partially_paid' | 'paid' | 'overdue' | 'disputed' | 'cancelled';
  approved_by_user_id?: number | null;
  approved_at?: string | null;
  notes?: string | null;
  file_attachment?: string | null;
  supplier?: any;
  approved_by_user?: any;
  created_at: string;
}

export interface VendorPaymentItem {
  id: number;
  tenant_id: number;
  payment_number: string;
  supplier_id: number;
  vendor_bill_id?: number | null;
  amount: number;
  currency: string;
  payment_method: string;
  payment_date: string;
  reference_number?: string | null;
  status: 'pending_approval' | 'approved' | 'disbursed' | 'failed' | 'cancelled';
  approved_by_user_id?: number | null;
  disbursed_by_user_id?: number | null;
  notes?: string | null;
  supplier?: any;
  vendor_bill?: VendorBillItem | null;
  created_at: string;
}

export interface AccountsPayableAgeingReport {
  summary: {
    total_payables: number;
    not_due_current: number;
    total_overdue: number;
    bucket_0_30: number;
    bucket_31_45: number;
    bucket_46_90: number;
    bucket_90_plus: number;
    bills_count: number;
  };
  vendor_breakdown: Array<{
    supplier_id: number;
    vendor_name: string;
    total_outstanding: number;
    bills_count: number;
  }>;
  bills: VendorBillItem[];
}

export interface ExpenseItem {
  id: number;
  tenant_id: number;
  expense_number: string;
  title: string;
  description?: string | null;
  category: 'travel' | 'office_supplies' | 'software_saas' | 'marketing' | 'utilities' | 'professional_services' | 'meals_entertainment' | 'other';
  supplier_id?: number | null;
  expense_date: string;
  amount: number;
  tax_amount: number;
  is_tax_inclusive: boolean;
  currency: string;
  receipt_url?: string | null;
  is_reimbursement: boolean;
  employee_user_id?: number | null;
  status: 'draft' | 'pending_approval' | 'approved' | 'rejected' | 'reimbursed' | 'paid';
  approved_by_user_id?: number | null;
  approved_at?: string | null;
  rejection_reason?: string | null;
  branch_id?: number | null;
  department_id?: number | null;
  payment_method?: string | null;
  payment_reference?: string | null;
  paid_at?: string | null;
  supplier?: any;
  employee?: any;
  approved_by_user?: any;
  created_at: string;
}

export interface ComprehensiveFinanceReport {
  revenue_vs_cash_metrics: {
    cash_collected: number;
    net_cash_collected: number;
    invoiced_revenue: number;
    forecast_pipeline_revenue: number;
    variance_invoiced_vs_collected: number;
    accounting_notice: string;
  };
  receivables_ar: {
    total_receivables: number;
    overdue_receivables: number;
    overdue_count: number;
  };
  payables_ap: {
    total_billed: number;
    total_disbursed: number;
    total_outstanding: number;
  };
  expenses: {
    paid_expenses: number;
    pending_expenses: number;
  };
  refunds: {
    total_refunded: number;
  };
  cash_flow: {
    total_inflow: number;
    total_outflow: number;
    net_cash_flow: number;
  };
  payment_methods_breakdown: Record<string, number>;
  ledger_integrity: {
    posted_transactions_count: number;
    reconciled_transactions_count: number;
    unposted_deletions_prohibited: boolean;
  };
}

// ----------------------------------------------------
// Official WhatsApp CRM & Shared Team Inbox
// ----------------------------------------------------

export interface WhatsAppAccountItem {
  id: number;
  tenant_id: number;
  name: string;
  waba_id: string;
  app_id?: string | null;
  webhook_verify_token: string;
  status: 'connected' | 'disconnected' | 'restricted';
  business_verification_status: 'verified' | 'pending' | 'failed';
  settings?: any;
  phone_numbers?: WhatsAppPhoneNumberItem[];
  created_at: string;
}

export interface WhatsAppPhoneNumberItem {
  id: number;
  tenant_id: number;
  whatsapp_account_id: number;
  phone_number_id: string;
  display_phone_number: string;
  normalized_number: string;
  verified_name?: string | null;
  quality_rating: 'GREEN' | 'YELLOW' | 'RED' | 'UNKNOWN';
  status: 'CONNECTED' | 'PENDING' | 'DISCONNECTED' | 'FLAGGED';
  is_default: boolean;
  branch_id?: number | null;
  department_id?: number | null;
  messaging_limit_tier?: string | null;
  channel_status?: 'active' | 'inactive' | 'warning' | 'restricted' | string;
  working_hours?: Record<string, any> | null;
  default_reply_rules?: Record<string, any> | null;
  assigned_team_ids?: number[] | null;
  messages_sent_today?: number;
  conversations_today?: number;
  default_assigned_user_id?: number | null;
  account?: WhatsAppAccountItem;
  default_user?: any;
}

export interface WhatsAppConversationItem {
  id: number;
  tenant_id: number;
  whatsapp_phone_number_id: number;
  customer_id?: number | null;
  contact_id?: number | null;
  lead_id?: number | null;
  phone_number: string;
  customer_name?: string | null;
  status: 'open' | 'pending' | 'resolved';
  is_ai_handled: boolean;
  assigned_user_id?: number | null;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  unread_count: number;
  window_expires_at?: string | null;
  last_message_at?: string | null;
  last_message_preview?: string | null;
  last_message_direction?: 'inbound' | 'outbound';
  tags?: string[];
  phone_number_model?: WhatsAppPhoneNumberItem;
  contact?: any;
  lead?: any;
  customer?: any;
  assigned_user?: any;
  messages?: WhatsAppMessageItem[];
  notes?: WhatsAppConversationNoteItem[];
}

export interface WhatsAppMessageItem {
  id: number;
  tenant_id: number;
  conversation_id: number;
  provider_message_id?: string | null;
  direction: 'inbound' | 'outbound';
  message_type: 'text' | 'image' | 'document' | 'audio' | 'video' | 'template' | 'interactive';
  sender_actor: 'customer' | 'human' | 'ai' | 'automation';
  user_id?: number | null;
  content?: string | null;
  media_url?: string | null;
  media_id?: string | null;
  mime_type?: string | null;
  template_name?: string | null;
  template_params?: any;
  delivery_status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
  error_code?: string | null;
  error_message?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  created_at: string;
  sender_user?: any;
}

export interface WhatsAppConversationNoteItem {
  id: number;
  tenant_id: number;
  conversation_id: number;
  user_id: number;
  content: string;
  mentions?: number[];
  created_at: string;
  author?: any;
}

export interface WhatsAppTemplateItem {
  id: number;
  tenant_id: number;
  whatsapp_account_id: number;
  template_name: string;
  category: 'MARKETING' | 'UTILITY' | 'AUTHENTICATION';
  language: string;
  status: 'APPROVED' | 'PENDING' | 'REJECTED' | 'PAUSED';
  header_type: 'NONE' | 'TEXT' | 'IMAGE' | 'DOCUMENT' | 'VIDEO';
  header_content?: string | null;
  body_text: string;
  footer_text?: string | null;
  buttons?: any[];
  variables_schema?: any[];
  meta_template_id?: string | null;
  rejected_reason?: string | null;
  sent_count?: number;
  last_used_at?: string | null;
  created_at: string;
}

export interface WhatsAppCampaignItem {
  id: number;
  tenant_id: number;
  name: string;
  whatsapp_phone_number_id: number;
  whatsapp_template_id: number;
  audience_type: 'segment' | 'contacts' | 'csv' | 'csv_import';
  segment_id?: number | null;
  csv_file_path?: string | null;
  total_recipients: number;
  queued_count: number;
  sent_count: number;
  delivered_count: number;
  read_count: number;
  failed_count: number;
  replied_count: number;
  opted_out_count: number;
  estimated_cost: number;
  variable_mappings?: Record<string, string> | null;
  scheduled_at?: string | null;
  status: 'draft' | 'pending_approval' | 'approved' | 'queued' | 'processing' | 'paused' | 'completed' | 'cancelled';
  approved_by_user_id?: number | null;
  approved_at?: string | null;
  created_by_user_id?: number | null;
  template?: WhatsAppTemplateItem;
  phoneNumber?: WhatsAppPhoneNumberItem;
  creator?: any;
  approver?: any;
  created_at: string;
  updated_at: string;
}

export interface WhatsAppCampaignRecipientItem {
  id: number;
  tenant_id: number;
  campaign_id: number;
  phone_number: string;
  recipient_name?: string | null;
  contact_id?: number | null;
  lead_id?: number | null;
  variables?: Record<string, any> | null;
  status: 'queued' | 'sent' | 'delivered' | 'read' | 'failed' | 'suppressed_opt_out' | 'skipped_opt_out';
  whatsapp_message_id?: number | null;
  error_message?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  created_at: string;
}

export interface WhatsAppAutomationItem {
  id: number;
  tenant_id: number;
  name: string;
  journey_type: 'new_enquiry_welcome' | 'lead_qualification' | 'appointment_confirmation' | 'payment_reminder' | 'invoice_reminder' | 'renewal_reminder' | 'customer_onboarding' | 'service_update' | 'sales_followup' | 'abandoned_enquiry';
  trigger_event: string;
  delay_minutes: number;
  conditions?: Record<string, any> | null;
  whatsapp_phone_number_id?: number | null;
  whatsapp_template_id?: number | null;
  message_payload?: Record<string, any> | null;
  stop_condition: string;
  is_active: boolean;
  execution_count: number;
  template?: WhatsAppTemplateItem;
  phoneNumber?: WhatsAppPhoneNumberItem;
  logs?: WhatsAppAutomationLogItem[];
  created_at: string;
  updated_at: string;
}

export interface WhatsAppAutomationLogItem {
  id: number;
  tenant_id: number;
  automation_id: number;
  contact_id?: number | null;
  lead_id?: number | null;
  invoice_id?: number | null;
  phone_number: string;
  trigger_context?: Record<string, any> | null;
  status: 'triggered' | 'delayed' | 'sent' | 'stopped' | 'failed';
  stopped_reason?: string | null;
  executed_at?: string | null;
  created_at: string;
  automation?: WhatsAppAutomationItem;
}

export interface WhatsAppUsageRecordItem {
  id: number;
  tenant_id: number;
  whatsapp_phone_number_id?: number | null;
  campaign_id?: number | null;
  conversation_id?: number | null;
  conversation_category: 'marketing' | 'utility' | 'authentication' | 'service';
  provider_reported_charge: number;
  rate_currency: string;
  billable_conversations_count: number;
  recorded_date: string;
  created_at: string;
  phoneNumber?: WhatsAppPhoneNumberItem;
  campaign?: WhatsAppCampaignItem;
}

export interface WhatsAppUsageReportsData {
  period: {
    start_date: string;
    end_date: string;
  };
  total_cost: number;
  total_conversations: number;
  by_category: Array<{
    conversation_category: string;
    count: number;
    cost: number;
  }>;
  by_phone_number: Array<{
    phone_number_id: number;
    display_phone_number: string;
    verified_name?: string | null;
    tier?: string | null;
    messages_sent_today: number;
    conversations_count: number;
    cost: number;
  }>;
  daily_trend: Array<{
    date: string;
    count: number;
    cost: number;
  }>;
  currency: string;
}

export interface WhatsAppIntegrationLogItem {
  id: number;
  tenant_id: number;
  whatsapp_account_id?: number | null;
  whatsapp_phone_number_id?: number | null;
  event_type: string;
  status: 'success' | 'warning' | 'error';
  payload?: any;
  error_message?: string | null;
  created_at: string;
}

// ----------------------------------------------------
// WhatsApp AI Agent Builder & Knowledge Base
// ----------------------------------------------------

export interface WhatsAppAiAgentItem {
  id: number;
  tenant_id: number;
  whatsapp_phone_number_id?: number | null;
  name: string;
  description?: string | null;
  status: 'active' | 'paused' | 'draft';
  primary_language: 'en' | 'hi' | 'hinglish' | 'multi' | string;
  supported_languages?: string[];
  tone: 'professional' | 'friendly' | 'consultative' | 'assertive';
  system_prompt: string;
  business_hours?: any;
  welcome_message?: string | null;
  fallback_message?: string | null;
  human_handover_conditions?: string[];
  conversation_rules?: string[];
  allowed_tools?: string[];
  model_provider: 'gemini' | 'openai' | 'anthropic' | 'custom' | string;
  model_name: string;
  temperature: number;
  max_tokens: number;
  monthly_token_budget: number;
  used_tokens_this_month: number;
  version: number;
  created_at: string;
  updated_at: string;
  phone_number?: {
    id: number;
    display_phone_number: string;
    verified_name?: string | null;
  } | null;
  versions?: WhatsAppAiAgentVersionItem[];
}

export interface WhatsAppAiAgentVersionItem {
  id: number;
  tenant_id: number;
  agent_id: number;
  version: number;
  system_prompt: string;
  welcome_message?: string | null;
  fallback_message?: string | null;
  allowed_tools?: string[];
  settings_snapshot?: any;
  changed_by_user_id?: number | null;
  change_summary?: string | null;
  created_at: string;
  changed_by_user?: {
    id: number;
    name: string;
  } | null;
}

export interface WhatsAppKnowledgeBaseItem {
  id: number;
  tenant_id: number;
  title: string;
  description?: string | null;
  category: 'faqs' | 'catalog' | 'policies' | 'manuals' | 'web_crawl' | string;
  source_type: 'file' | 'url' | 'manual_text';
  file_path?: string | null;
  file_type?: string | null;
  file_size_bytes?: number | null;
  status: 'pending' | 'indexing' | 'ready' | 'failed';
  error_message?: string | null;
  total_chunks: number;
  version: number;
  metadata?: any;
  chunks_count?: number;
  created_at: string;
  updated_at: string;
}

export interface WhatsAppKnowledgeChunkItem {
  id: number;
  tenant_id: number;
  knowledge_base_id: number;
  chunk_index: number;
  content: string;
  source_citation?: string | null;
  score?: number;
  cosine_score?: number;
  token_count: number;
}

export interface WhatsAppAiProviderItem {
  id: number;
  provider: 'gemini' | 'openai' | 'anthropic' | 'custom' | string;
  name: string;
  api_endpoint?: string | null;
  default_model: string;
  available_models?: string[];
  is_active: boolean;
  priority: number;
  monthly_budget_usd?: number | null;
  spent_usd_this_month: number;
  timeout_seconds: number;
  max_retries: number;
  has_configured_key: boolean;
  masked_key?: string | null;
}

export interface WhatsAppAiExecutionLogItem {
  id: number;
  tenant_id: number;
  agent_id?: number | null;
  conversation_id?: number | null;
  provider: string;
  model: string;
  prompt_tokens: number;
  completion_tokens: number;
  total_tokens: number;
  estimated_cost_usd: string | number;
  latency_ms: number;
  tool_calls?: Array<{
    tool: string;
    arguments?: any;
    result?: any;
  }> | null;
  knowledge_citations?: Array<{
    source: string;
    score: number;
    content: string;
  }> | null;
  decision_trace?: {
    sanitized_query?: string;
    language_detected?: string;
    prompt_injection_flag?: boolean;
    handover_triggered?: boolean;
  } | null;
  user_query: string;
  ai_response?: string | null;
  status: 'success' | 'failed' | 'fallback_used' | 'handover_triggered';
  error_message?: string | null;
  created_at: string;
  agent?: {
    id: number;
    name: string;
    primary_language: string;
  } | null;
  conversation?: {
    id: number;
    phone_number: string;
    customer_name?: string | null;
  } | null;
}

// ----------------------------------------------------
// AI Voice & Telephony Automation Module
// ----------------------------------------------------

export interface VoiceAiAgentItem {
  id: number;
  tenant_id: number;
  telephony_phone_number_id?: number | null;
  name: string;
  description?: string | null;
  status: 'active' | 'paused' | 'draft';
  language: 'en' | 'hi' | 'hinglish' | 'multi' | string;
  voice_engine: 'elevenlabs' | 'openai' | 'google' | 'cartesia' | string;
  voice_id: string;
  tone: 'consultative' | 'professional' | 'friendly' | 'persuasive';
  system_instructions: string;
  call_opening: string;
  qualification_script?: Array<{
    question: string;
    target_field?: string;
  }> | null;
  max_call_duration_seconds: number;
  human_transfer_number?: string | null;
  allowed_crm_actions?: string[];
  monthly_call_minutes_budget: number;
  used_call_minutes_this_month: number;
  version: number;
  created_at: string;
  updated_at: string;
  phone_number?: {
    id: number;
    phone_number: string;
    display_name?: string | null;
  } | null;
  calls_count?: number;
  campaigns_count?: number;
}

export interface VoiceAiAgentItem {
  id: number;
  tenant_id: number;
  telephony_phone_number_id?: number | null;
  name: string;
  description?: string | null;
  business_purpose?: 'lead_qualification' | 'customer_follow_up' | 'appointment_confirmation' | 'renewal_reminder' | 'service_enquiry' | 'payment_reminder' | 'feedback_collection' | 'incoming_enquiry' | string | null;
  status: 'active' | 'paused' | 'draft';
  language: string;
  regional_dialects?: string[] | null;
  voice_engine: 'elevenlabs' | 'openai' | 'google' | 'cartesia' | string;
  voice_id: string;
  tone: 'consultative' | 'professional' | 'friendly' | 'persuasive';
  system_instructions: string;
  call_opening: string;
  knowledge_base?: string | null;
  confidence_threshold?: number | null;
  qualification_script?: Array<{
    id: string;
    question: string;
    expected_entity: string;
    required: boolean;
  }> | null;
  max_call_duration_seconds: number;
  human_transfer_number?: string | null;
  allowed_crm_actions?: string[] | null;
  escalation_rules?: any | null;
  business_hours?: any | null;
  monthly_call_minutes_budget: number;
  used_call_minutes_this_month: number;
  version: number;
  created_at: string;
  updated_at: string;
  phone_number?: {
    id: number;
    phone_number: string;
    display_name?: string | null;
  } | null;
  calls_count?: number;
  campaigns_count?: number;
}

export interface VoiceCallItem {
  id: number;
  tenant_id: number;
  voice_agent_id?: number | null;
  campaign_id?: number | null;
  lead_id?: number | null;
  contact_id?: number | null;
  deal_id?: number | null;
  company_id?: number | null;
  assigned_user_id?: number | null;
  transfer_to_user_id?: number | null;
  provider: 'exotel' | 'twilio' | 'sip' | string;
  provider_call_id?: string | null;
  idempotency_key?: string | null;
  direction: 'inbound' | 'outbound';
  call_type?: 'human' | 'ai_voice' | 'ivr';
  from_number: string;
  to_number: string;
  start_time?: string | null;
  end_time?: string | null;
  duration_seconds: number;
  status: 'queued' | 'ringing' | 'in-progress' | 'completed' | 'busy' | 'no-answer' | 'failed' | 'cancelled';
  disposition?: 'interested' | 'not_interested' | 'callback_requested' | 'wrong_number' | 'converted' | 'dnd_requested' | 'unreached' | 'completed' | null;
  hangup_cause?: string | null;
  recording_url?: string | null;
  recording_duration_seconds: number;
  recording_notice_played?: boolean;
  transcript?: string | null;
  ai_summary?: string | null;
  extracted_data?: {
    budget?: number;
    appointment_confirmed?: boolean;
    objections?: string[];
    need?: string;
    timeline?: string;
  } | null;
  qualification_result?: 'qualified' | 'unqualified' | 'follow_up_needed' | null;
  quality_score?: number | null;
  manager_feedback?: string | null;
  next_follow_up_at?: string | null;
  follow_up_required?: boolean;
  follow_up_notes?: string | null;
  cost_amount: number | string;
  cost_currency: string;
  human_transferred: boolean;
  transfer_target?: string | null;
  created_at: string;
  agent?: {
    id: number;
    name: string;
    language: string;
    voice_engine: string;
  } | null;
  lead?: {
    id: number;
    name: string;
    phone: string;
    status: string;
    company_name?: string | null;
  } | null;
  contact?: {
    id: number;
    name: string;
    phone?: string;
    email?: string;
  } | null;
  deal?: {
    id: number;
    name: string;
    value: number;
    stage: string;
  } | null;
  company?: {
    id: number;
    name: string;
  } | null;
  assigned_user?: {
    id: number;
    name: string;
    email?: string;
  } | null;
  transferred_to_user?: {
    id: number;
    name: string;
  } | null;
}

export interface VoiceCallingCampaignItem {
  id: number;
  tenant_id: number;
  voice_agent_id: number;
  name: string;
  description?: string | null;
  status: 'draft' | 'running' | 'paused' | 'completed' | 'cancelled';
  target_filters?: {
    status?: string;
    source?: string;
    tags?: string[];
  } | null;
  max_retries: number;
  retry_interval_hours: number;
  calls_per_minute_limit: number;
  total_contacts_queued: number;
  total_contacts_called: number;
  total_contacts_answered: number;
  total_contacts_qualified: number;
  scheduled_start_at?: string | null;
  completed_at?: string | null;
  created_at: string;
  agent?: {
    id: number;
    name: string;
    language: string;
    voice_engine: string;
  } | null;
  calls_count?: number;
}

export interface TelephonyProviderItem {
  id: number;
  provider: 'exotel' | 'twilio' | 'sip' | 'custom' | string;
  name: string;
  account_sid?: string | null;
  api_key?: string | null;
  subdomain?: string | null;
  caller_id?: string | null;
  dlt_entity_id?: string | null;
  is_active: boolean;
  supports_recordings: boolean;
  supports_realtime_streams: boolean;
  has_configured_token: boolean;
  masked_token?: string | null;
  incoming_webhook_url?: string;
  status_callback_url?: string;
  recording_callback_url?: string;
}

export interface CallIvrFlowItem {
  id: number;
  tenant_id: number;
  phone_number_id?: number | null;
  name: string;
  description?: string | null;
  welcome_audio_text: string;
  language: string;
  business_hours?: {
    open?: string;
    close?: string;
    days?: number[];
    timezone?: string;
  } | null;
  holiday_rules?: string[] | null;
  menu_options?: Array<{
    key: string;
    action: string;
    label: string;
    target_queue_id?: number;
    target_agent_id?: number;
  }> | null;
  unanswered_action: 'voicemail' | 'callback' | 'transfer_phone' | 'hangup';
  unanswered_target?: string | null;
  voicemail_box?: string | null;
  is_active: boolean;
  created_at: string;
  phone_number?: {
    id: number;
    phone_number: string;
    display_name?: string | null;
  } | null;
}

export interface CallQueueItem {
  id: number;
  tenant_id: number;
  name: string;
  department?: string | null;
  branch_id?: number | null;
  strategy: 'round_robin' | 'simultaneous_ring' | 'least_recent' | 'linear';
  max_wait_time_seconds: number;
  music_on_hold?: string | null;
  assigned_agent_ids?: number[] | null;
  is_active: boolean;
  created_at: string;
}

export interface CallQualityReviewItem {
  id: number;
  tenant_id: number;
  voice_call_id: number;
  reviewer_user_id: number;
  score: number;
  criteria_scores?: Record<string, number> | null;
  feedback?: string | null;
  tags?: string[] | null;
  created_at: string;
  reviewer?: {
    id: number;
    name: string;
  } | null;
}

export interface CallerProfileLookupData {
  is_known_customer: boolean;
  caller_number: string;
  normalized_phone: string;
  customer_name: string;
  customer_email?: string | null;
  company_name?: string | null;
  contact?: any | null;
  lead?: any | null;
  assigned_user?: {
    id: number;
    name: string;
    email?: string;
  } | null;
  open_deals?: Array<{
    id: number;
    name: string;
    value: number;
    stage: string;
  }>;
  pending_followups?: Array<{
    id: number;
    followup_type: string;
    scheduled_at: string;
    title: string;
  }>;
  open_tickets?: Array<{
    id: number;
    ticket_number: string;
    subject: string;
    priority: string;
    status: string;
  }>;
  recent_calls?: Array<{
    id: number;
    direction: string;
    duration_seconds: number;
    status: string;
    disposition?: string | null;
    created_at: string;
  }>;
  quick_create_suggestion?: {
    phone: string;
    suggested_name: string;
    source: string;
  } | null;
}

export interface CallReportingAnalyticsData {
  summary: {
    total_calls: number;
    inbound_calls: number;
    outbound_calls: number;
    answered_calls: number;
    missed_calls: number;
    answer_rate_pct: number;
    total_duration_minutes: number;
    avg_call_duration_seconds: number;
    total_cost_inr: number;
    conversion_rate_pct: number;
    qualified_leads: number;
    callback_requests: number;
    dnd_suppressions: number;
  };
  ai_performance: {
    total_ai_calls: number;
    ai_qualified_leads: number;
    ai_qualification_rate_pct: number;
    ai_human_transfers: number;
    ai_handoff_rate_pct: number;
  };
  dispositions: Record<string, number>;
  agent_performance: Array<{
    user_id: number;
    name: string;
    email?: string;
    total_calls: number;
    answered_calls: number;
    total_talk_minutes: number;
    average_quality_score: number;
    qualified_leads: number;
  }>;
  provider_usage: Array<{
    provider: string;
    call_count: number;
    duration_minutes: number;
    cost: number;
  }>;
}

export interface TelephonyProviderLogItem {
  id: number;
  tenant_id: number;
  provider: string;
  provider_call_id?: string | null;
  event_type: string;
  status: 'success' | 'error' | 'warning';
  payload?: any | null;
  error_message?: string | null;
  created_at: string;
}

export interface TelephonyPhoneNumberItem {
  id: number;
  tenant_id: number;
  telephony_provider_id: number;
  phone_number: string;
  display_name?: string | null;
  country_code: string;
  is_default: boolean;
  routing_type: 'ai_agent' | 'user' | 'queue' | 'ivr';
  default_agent_id?: number | null;
  is_active: boolean;
  created_at: string;
  provider?: {
    id: number;
    name: string;
    provider: string;
  } | null;
  default_agent?: {
    id: number;
    name: string;
  } | null;
}






// ----------------------------------------------------
// Unified Omnichannel Inbox Module Types
// ----------------------------------------------------

export interface OmnichannelConversationItem {
  id: number;
  tenant_id: number;
  channel: 'whatsapp' | 'facebook' | 'instagram' | 'email' | 'sms' | 'call' | 'webchat' | string;
  customer_name?: string | null;
  customer_email?: string | null;
  phone_number: string;
  subject?: string | null;
  channel_account_id?: string | null;
  status: 'open' | 'pending' | 'resolved';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  is_ai_handled: boolean;
  assigned_user_id?: number | null;
  assigned_team_id?: number | null;
  unread_count: number;
  first_response_at?: string | null;
  first_response_time_seconds?: number | null;
  resolved_at?: string | null;
  resolution_time_seconds?: number | null;
  last_actor_type?: 'customer' | 'human' | 'ai' | 'automation' | 'system' | string;
  last_message_at?: string | null;
  last_message_preview?: string | null;
  last_message_direction?: 'inbound' | 'outbound';
  tags?: string[];
  metadata?: any;
  locked_by_user_id?: number | null;
  locked_at?: string | null;
  locked_by?: { id: number; name: string; email: string } | null;
  sla_policy_id?: number | null;
  sla_status?: 'on_track' | 'warning' | 'breached';
  first_response_due_at?: string | null;
  resolution_due_at?: string | null;
  is_archived?: boolean;
  branch_id?: number | null;
  branch?: { id: number; name: string } | null;
  department_id?: number | null;
  department?: { id: number; name: string } | null;
  language?: string | null;
  lead_id?: number | null;
  contact_id?: number | null;
  customer_id?: number | null;
  window_expires_at?: string | null;
  financials?: {
    has_permission: boolean;
    message?: string;
    summary?: {
      total_invoiced: number;
      total_paid: number;
      balance_due: number;
      pending_count: number;
    };
    invoices?: Array<{
      id: number;
      invoice_number: string;
      total_amount: number;
      status: string;
      due_date: string;
    }>;
  };
  created_at: string;
  updated_at: string;
  assigned_user?: {
    id: number;
    name: string;
    email: string;
  } | null;
  assigned_team?: {
    id: number;
    name: string;
  } | null;
  lead?: {
    id: number;
    name: string;
    company_name?: string | null;
    status: string;
    lead_score?: number;
  } | null;
  contact?: {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
  } | null;
  customer?: {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
  } | null;
  messages?: OmnichannelMessageItem[];
  notes?: OmnichannelConversationNoteItem[];
  assignment_logs?: ConversationAssignmentLogItem[];
}

export interface OmnichannelMessageItem {
  id: number;
  conversation_id: number;
  channel: 'whatsapp' | 'email' | 'sms' | 'call' | 'webchat' | string;
  provider_message_id?: string | null;
  direction: 'inbound' | 'outbound';
  message_type: 'text' | 'email' | 'sms' | 'image' | 'document' | 'audio' | 'video' | 'template' | string;
  subject?: string | null;
  sender_actor: 'customer' | 'human' | 'ai' | 'automation';
  user_id?: number | null;
  sender_identity?: string | null;
  recipient_identity?: string | null;
  in_reply_to?: string | null;
  content?: string | null;
  media_url?: string | null;
  attachments?: Array<{
    name: string;
    url: string;
    size?: number;
    mime_type?: string;
  }>;
  delivery_status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
  error_code?: string | null;
  error_message?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  created_at: string;
  sender_user?: {
    id: number;
    name: string;
  } | null;
}

export interface OmnichannelConversationNoteItem {
  id: number;
  conversation_id: number;
  user_id: number;
  content: string;
  mentions?: number[];
  created_at: string;
  author?: {
    id: number;
    name: string;
  } | null;
}

export interface ConversationAssignmentLogItem {
  id: number;
  tenant_id: number;
  conversation_id: number;
  assigned_by_user_id?: number | null;
  assigned_to_user_id?: number | null;
  assigned_to_team_id?: number | null;
  routing_method: 'manual' | 'round_robin' | 'workload_aware' | 'rule_based' | 'handover' | string;
  reason?: string | null;
  created_at: string;
  assigned_by?: {
    id: number;
    name: string;
  } | null;
  assigned_to?: {
    id: number;
    name: string;
  } | null;
}

export interface OmnichannelQuickReplyItem {
  id: number;
  tenant_id: number;
  channel: string;
  shortcut: string;
  title: string;
  content: string;
  tags?: string[] | null;
  is_shared: boolean;
  created_by_user_id?: number | null;
  created_at: string;
}

export interface CustomerCommunicationConsentItem {
  id: number;
  tenant_id: number;
  customer_id?: number | null;
  lead_id?: number | null;
  contact_id?: number | null;
  channel: 'whatsapp' | 'facebook' | 'instagram' | 'email' | 'sms' | 'call' | 'webchat' | string;
  destination: string;
  consent_status: 'opted_in' | 'opted_out' | 'pending' | 'revoked';
  consent_source: 'form' | 'verbal' | 'direct_message' | 'admin' | 'unsubscribe_link' | string;
  consent_timestamp?: string | null;
  opt_out_timestamp?: string | null;
  is_marketing_allowed: boolean;
  is_transactional_allowed: boolean;
  metadata?: any;
  created_at: string;
}

export interface OmnichannelTimelineItem {
  id: string;
  type: 'message' | 'call' | 'activity';
  channel: 'whatsapp' | 'facebook' | 'instagram' | 'email' | 'sms' | 'call' | 'webchat' | 'system' | string;
  title: string;
  content: string;
  direction?: 'inbound' | 'outbound';
  recording_url?: string | null;
  disposition?: string | null;
  qualification?: string | null;
  actor_type?: 'customer' | 'human' | 'ai' | 'automation' | 'system' | string;
  timestamp: string;
}

export interface OmnichannelAnalyticsData {
  total_conversations: number;
  conversations_by_channel: Record<string, number>;
  messages_volume: {
    inbound: number;
    outbound: number;
    total: number;
  };
  first_response_time: {
    average_seconds: number;
    average_minutes: number;
  };
  resolution_time: {
    average_seconds: number;
    average_minutes: number;
  };
  agent_workload: Array<{
    user_id: number;
    name: string;
    open_conversations: number;
    resolved_conversations: number;
  }>;
  ai_metrics: {
    ai_handled_conversations: number;
    human_handovers: number;
  };
  voice_calls_overview: {
    total_calls: number;
    completed: number;
    missed_or_failed: number;
    average_duration_seconds: number;
  };
  delivery_rates: {
    sent: number;
    delivered: number;
    read: number;
    failed: number;
  };
  provider_costs: {
    voice_minutes: number;
    sms_segments: number;
    estimated_total_usd: number;
  };
}

export interface TelegramAccountItem {
  id: number;
  tenant_id: number;
  bot_name: string;
  bot_username?: string | null;
  bot_token?: string;
  is_connected: boolean;
  webhook_url?: string | null;
  branch_id?: number | null;
  branch?: { id: number; name: string } | null;
  department_id?: number | null;
  department?: { id: number; name: string } | null;
  business_hours?: any;
  ai_enabled: boolean;
  ai_agent_id?: number | null;
  created_at: string;
}

export interface OmnichannelChannelSettingItem {
  id: number;
  tenant_id: number;
  channel: string;
  channel_account_id?: string | null;
  name?: string | null;
  branch_id?: number | null;
  branch?: { id: number; name: string } | null;
  department_id?: number | null;
  department?: { id: number; name: string } | null;
  business_hours?: {
    timezone: string;
    days: number[];
    start: string;
    end: string;
  } | null;
  timezone?: string;
  working_hours_start?: string;
  working_hours_end?: string;
  working_days?: number[];
  is_active: boolean;
  health_status: 'healthy' | 'degraded' | 'failed' | 'disconnected';
  last_health_check_at?: string | null;
  last_error?: any;
}

export interface OmnichannelRoutingRuleItem {
  id: number;
  tenant_id: number;
  name: string;
  rule_type: 'round_robin' | 'least_active' | 'branch' | 'department' | 'language' | 'lead_source' | 'product' | 'working_hours' | 'direct' | string;
  routing_type?: string;
  channel: string;
  conditions?: any;
  target_user_id?: number | null;
  target_user?: { id: number; name: string; email: string } | null;
  target_team_id?: number | null;
  target_team?: { id: number; name: string } | null;
  priority: number;
  is_active: boolean;
  created_at: string;
}

export interface OmnichannelSlaPolicyItem {
  id: number;
  tenant_id: number;
  name: string;
  priority: string;
  channel: string;
  first_response_target_minutes: number;
  resolution_target_minutes: number;
  first_response_time_minutes?: number;
  resolution_time_minutes?: number;
  pause_outside_business_hours?: boolean;
  business_hours_enabled: boolean;
  business_hours?: any;
  escalation_target_minutes?: number | null;
  escalate_to_user_id?: number | null;
  escalate_to_user?: { id: number; name: string; email: string } | null;
  is_active: boolean;
  created_at: string;
}

export interface OmnichannelAgentPresenceItem {
  id?: number;
  user_id: number;
  user?: { id: number; name: string; email: string };
  conversation_id?: number | null;
  is_typing: boolean;
  is_online: boolean;
  last_seen_at: string;
}

export interface OmnichannelDetailedReportsData extends OmnichannelAnalyticsData {
  sla_compliance: {
    compliance_rate: number;
    on_track: number;
    warning: number;
    breached: number;
    total_open: number;
  };
}

export interface EmailAccountItem {
  id: number;
  name: string;
  email_address: string;
  provider: 'smtp_imap' | 'sendgrid' | 'mailgun' | 'ses' | 'gmail_oauth';
  smtp_host?: string | null;
  smtp_port: number;
  smtp_encryption: string;
  smtp_username?: string | null;
  imap_host?: string | null;
  imap_port: number;
  imap_encryption: string;
  imap_username?: string | null;
  signature_html?: string | null;
  is_active: boolean;
  is_default: boolean;
  created_at: string;
}

export interface SmsConfigurationItem {
  id: number;
  name: string;
  provider: 'twilio' | 'exotel' | 'fast2sms' | 'custom_http';
  account_sid?: string | null;
  sender_id?: string | null;
  dlt_entity_id?: string | null;
  is_active: boolean;
  is_default: boolean;
  created_at: string;
}

export interface WebsiteChatWidgetItem {
  id: number;
  name: string;
  widget_key: string;
  brand_color: string;
  brand_title: string;
  brand_logo_url?: string | null;
  welcome_message?: string | null;
  pre_chat_fields?: string[];
  agent_availability_mode: 'always_ai' | 'hybrid' | 'business_hours';
  is_active: boolean;
  allowed_domains?: string[];
  created_at: string;
}

export interface CommunicationSuppressionItem {
  id: number;
  channel: 'email' | 'sms' | 'whatsapp' | 'all';
  identifier: string;
  reason: 'unsubscribe' | 'bounce' | 'spam_complaint' | 'dnd';
  suppressed_at: string;
  created_at: string;
}

export interface IdentityMergePendingItem {
  id: number;
  primary_type: 'lead' | 'contact' | 'customer';
  primary_id: number;
  secondary_type: 'lead' | 'contact' | 'customer';
  secondary_id: number;
  match_criteria: string;
  confidence_score: number;
  status: 'pending_review' | 'merged' | 'rejected';
  reviewed_by_user_id?: number | null;
  reviewed_at?: string | null;
  notes?: string | null;
  created_at: string;
  reviewed_by_user?: {
    id: number;
    name: string;
  } | null;
}

export interface UnifiedTimelineItem {
  id: string;
  type: 'message' | 'call' | 'activity';
  channel: string;
  title: string;
  content: string;
  subject?: string | null;
  sender?: string | null;
  recipient?: string | null;
  delivery_status?: string | null;
  attachments?: any[];
  recording_url?: string | null;
  disposition?: string | null;
  qualification?: string | null;
  timestamp: string;
}

export interface WorkflowNodeData {
  label: string;
  action_type?: string;
  params?: Record<string, any>;
  conditions?: {
    logical_operator?: 'and' | 'or';
    rules?: Array<{
      field: string;
      operator: 'equals' | 'not_equals' | 'contains' | 'greater_than' | 'less_than' | 'in';
      value: any;
    }>;
  };
  duration?: number;
  unit?: 'minutes' | 'hours' | 'days';
  event?: string;
  timeout_hours?: number;
  [key: string]: any;
}

export interface WorkflowNode {
  id: string;
  type: 'trigger' | 'condition' | 'action' | 'delay' | 'wait' | 'stop';
  position: { x: number; y: number };
  data: WorkflowNodeData;
}

export interface WorkflowEdge {
  id: string;
  source: string;
  target: string;
  sourceHandle?: string;
  label?: string;
}

export interface WorkflowDefinition {
  nodes: WorkflowNode[];
  edges: WorkflowEdge[];
}

export interface WorkflowVersionItem {
  id: number;
  tenant_id: number;
  workflow_id: number;
  version: number;
  definition: WorkflowDefinition;
  changelog?: string | null;
  is_published: boolean;
  created_at: string;
}

export interface WorkflowItem {
  id: number;
  tenant_id: number;
  name: string;
  description?: string | null;
  category: 'crm' | 'sales' | 'marketing' | 'support' | 'billing' | 'custom';
  status: 'draft' | 'published' | 'paused';
  trigger_type: string;
  current_version: number;
  is_active: boolean;
  total_runs_count: number;
  last_run_at?: string | null;
  created_by_user_id?: number | null;
  current_version_rel?: WorkflowVersionItem | null;
  created_at: string;
  updated_at: string;
}

export interface WorkflowStepLogItem {
  id: number;
  workflow_run_id: number;
  node_id: string;
  node_type: string;
  node_name: string;
  status: 'completed' | 'failed' | 'waiting' | 'skipped';
  input_state?: any;
  output_state?: any;
  branch_taken?: string | null;
  error_message?: string | null;
  execution_duration_ms: number;
  created_at: string;
}

export interface WorkflowRunItem {
  id: number;
  tenant_id: number;
  workflow_id: number;
  workflow_version_id?: number | null;
  trigger_event: string;
  idempotency_key?: string | null;
  entity_type?: string | null;
  entity_id?: number | null;
  status: 'pending' | 'running' | 'waiting' | 'completed' | 'failed' | 'cancelled';
  current_node_id?: string | null;
  resume_at?: string | null;
  wait_for_event?: string | null;
  wait_timeout_at?: string | null;
  trigger_payload?: any;
  context_data?: any;
  retry_count: number;
  max_retries: number;
  error_message?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  created_at: string;
  workflow?: WorkflowItem | null;
  step_logs?: WorkflowStepLogItem[];
}

export interface WorkflowTemplateItem {
  id: number;
  name: string;
  category: string;
  description: string;
  trigger_type: string;
  definition: WorkflowDefinition;
  is_system: boolean;
}

export interface WorkflowSimulationTraceItem {
  node_id: string;
  node_type: string;
  node_name: string;
  status: string;
  branch_taken?: string | null;
  output?: any;
  error?: string | null;
  duration_ms: number;
}

export interface WorkflowSimulationResult {
  status: string;
  steps_executed: number;
  trace: WorkflowSimulationTraceItem[];
}

export interface CustomDomainItem {
  id: number;
  tenant_id: number;
  domain: string;
  verification_token: string;
  verification_type: 'txt' | 'cname';
  verification_status: 'pending' | 'verified' | 'failed';
  ssl_status: 'pending' | 'active' | 'failed';
  verified_at?: string | null;
  last_verification_error?: string | null;
  landing_pages_count?: number;
  created_at: string;
}

export interface LandingPageSection {
  id: string;
  type: 'hero' | 'features' | 'testimonials' | 'form_embed' | 'pricing' | 'faq' | 'cta' | 'footer' | 'custom_html';
  headline?: string;
  subheadline?: string;
  badge?: string;
  cta_text?: string;
  cta_target?: string;
  form_slug?: string;
  features?: Array<{ title: string; desc: string; icon?: string }>;
  testimonials?: Array<{ name: string; role: string; quote: string; avatar?: string }>;
  faqs?: Array<{ q: string; a: string }>;
  copyright?: string;
  is_visible: boolean;
  [key: string]: any;
}

export interface LandingPageBranding {
  primary_color: string;
  secondary_color?: string;
  font_family: string;
  logo_url?: string;
  favicon_url?: string;
}

export interface LandingPageSeo {
  meta_title: string;
  meta_description: string;
  keywords?: string;
  og_title?: string;
  og_description?: string;
  og_image?: string;
}

export interface LandingPageRevisionItem {
  id: number;
  tenant_id: number;
  landing_page_id: number;
  version: number;
  snapshot: any;
  changelog?: string | null;
  created_at: string;
  created_by_user?: { id: number; name: string } | null;
}

export interface LandingPageItem {
  id: number;
  tenant_id: number;
  name: string;
  slug: string;
  title?: string | null;
  description?: string | null;
  status: 'draft' | 'published' | 'unpublished' | 'archived';
  custom_domain_id?: number | null;
  current_version: number;
  branding?: LandingPageBranding;
  seo_metadata?: LandingPageSeo;
  sections: LandingPageSection[];
  settings?: Record<string, any>;
  view_count: number;
  submission_count: number;
  published_at?: string | null;
  custom_domain?: CustomDomainItem | null;
  revisions?: LandingPageRevisionItem[];
  created_at: string;
  updated_at: string;
}

export interface FormField {
  id?: string;
  name: string;
  label: string;
  type: 'text' | 'textarea' | 'email' | 'phone' | 'number' | 'dropdown' | 'radio' | 'checkbox' | 'date';
  required: boolean;
  placeholder?: string;
  options?: string[];
  help_text?: string;
}

export interface FormItem {
  id: number;
  tenant_id: number;
  name: string;
  slug: string;
  title?: string | null;
  description?: string | null;
  status: 'active' | 'inactive';
  schema: {
    fields: FormField[];
    conditional_rules?: Array<{
      show_field: string;
      when_field: string;
      operator: string;
      value: any;
    }>;
  };
  settings?: {
    submit_button_text?: string;
    success_type?: 'message' | 'redirect';
    success_message?: string;
    redirect_url?: string;
    require_consent?: boolean;
    consent_text?: string;
    spam_protection?: {
      honeypot_enabled?: boolean;
      min_time_seconds?: number;
      rate_limit_per_minute?: number;
    };
  };
  submissions_count?: number;
  created_at: string;
  updated_at: string;
}

export interface FormSubmissionItem {
  id: number;
  tenant_id: number;
  form_id: number;
  landing_page_id?: number | null;
  lead_id?: number | null;
  data: Record<string, any>;
  utm_data?: Record<string, any>;
  referrer?: string | null;
  consent_given: boolean;
  consent_text?: string | null;
  ip_address?: string | null;
  spam_score: number;
  created_at: string;
  form?: { id: number; name: string; slug: string };
  landing_page?: { id: number; name: string; slug: string } | null;
  lead?: { id: number; name: string; email?: string; phone?: string; status: string } | null;
}

export interface LandingPageTemplateItem {
  id: number;
  name: string;
  category: string;
  description?: string;
  preview_image?: string | null;
  branding?: LandingPageBranding;
  sections: LandingPageSection[];
  is_system: boolean;
}

export interface LandingPageAnalyticsData {
  views: number;
  cta_clicks: number;
  form_views: number;
  submissions: number;
  conversion_rate: number;
  device_breakdown: { desktop: number; mobile: number; tablet: number };
  top_utm_sources: Record<string, number>;
}

export interface MarketingSegmentItem {
  id: number;
  tenant_id: number;
  name: string;
  description?: string | null;
  type: 'dynamic' | 'static';
  target_entity: 'lead' | 'contact' | 'customer';
  rules?: {
    logical_operator?: 'and' | 'or';
    rules?: Array<{ field: string; operator: string; value: any }>;
  };
  inclusion_ids?: number[];
  exclusion_ids?: number[];
  cached_count: number;
  last_calculated_at?: string | null;
  campaigns_count?: number;
  created_at: string;
  updated_at: string;
}

export interface CampaignRecipientItem {
  id: number;
  campaign_id: number;
  entity_type: string;
  entity_id: number;
  recipient_address: string;
  status: 'queued' | 'sent' | 'delivered' | 'read' | 'failed' | 'skipped';
  skip_reason?: string | null;
  error_message?: string | null;
  message_id?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  created_at: string;
}

export interface CampaignApprovalItem {
  id: number;
  tenant_id: number;
  campaign_id: number;
  approver_user_id: number;
  role_level: string;
  action: 'requested' | 'approved' | 'rejected';
  comments?: string | null;
  approver?: { id: number; name: string; email: string } | null;
  created_at: string;
}

export interface MarketingConsentItem {
  id: number;
  tenant_id: number;
  entity_type: 'lead' | 'contact' | 'customer';
  entity_id: number;
  channel: 'whatsapp' | 'email' | 'sms' | 'voice' | 'all';
  consent_status: 'opted_in' | 'opted_out' | 'pending';
  source: string;
  reason?: string | null;
  opted_out_at?: string | null;
  created_at: string;
  updated_at: string;
}

export interface MarketingTemplateItem {
  id: number;
  tenant_id: number;
  name: string;
  channel: 'whatsapp' | 'email' | 'sms';
  category: string;
  subject?: string | null;
  content: string;
  variables?: string[] | null;
  dlt_template_id?: string | null;
  status: 'draft' | 'approved' | 'rejected';
  version: number;
  created_by_user_id?: number | null;
  created_by_user?: { id: number; name: string; email: string } | null;
  created_at: string;
  updated_at: string;
}

export interface MarketingOverviewSummary {
  total_campaigns: number;
  active_campaigns: number;
  total_budget: number;
  total_spend: number;
  total_attributed_revenue: number;
  overall_roi_percent: number;
  total_sent: number;
  total_delivered: number;
  total_conversions: number;
}

export interface MarketingCampaignItem {
  id: number;
  tenant_id: number;
  name: string;
  description?: string | null;
  channel: 'whatsapp' | 'email' | 'sms';
  type: 'broadcast' | 'drip' | 'triggered';
  objective?: string | null;
  status: 'draft' | 'in_review' | 'scheduled' | 'running' | 'paused' | 'completed' | 'cancelled' | 'archived';
  approval_status?: 'not_submitted' | 'pending_approval' | 'approved' | 'rejected';
  approved_by_user_id?: number | null;
  approved_by_user?: { id: number; name: string; email: string } | null;
  approved_at?: string | null;
  rejection_reason?: string | null;
  assigned_owner_id?: number | null;
  assigned_owner?: { id: number; name: string; email: string } | null;
  branch_id?: number | null;
  segment_id?: number | null;
  whatsapp_template_id?: number | null;
  whatsapp_account_id?: number | null;
  email_account_id?: number | null;
  sms_config_id?: number | null;
  subject?: string | null;
  content?: string | null;
  variable_mappings?: Record<string, string>;
  scheduled_at?: string | null;
  start_date?: string | null;
  end_date?: string | null;
  timezone: string;
  business_hours_only?: boolean;
  frequency_cap_days?: number;
  rate_limit_per_minute: number;
  budget?: number;
  spend_amount: number;
  total_recipients_count: number;
  sent_count: number;
  delivered_count: number;
  read_count: number;
  opens_count?: number;
  clicks_count?: number;
  replied_count: number;
  conversions_count?: number;
  failed_count: number;
  unsubscribed_count: number;
  attributed_revenue: number;
  cost_per_lead?: number;
  cost_per_acquisition?: number;
  campaign_notes?: string | null;
  ai_drafted?: boolean;
  started_at?: string | null;
  completed_at?: string | null;
  segment?: MarketingSegmentItem | null;
  whatsapp_template?: WhatsAppTemplateItem | null;
  approvals?: CampaignApprovalItem[];
  created_at: string;
  updated_at: string;
}

export interface CampaignAnalyticsData {
  campaign_id: number;
  name: string;
  channel: string;
  status: string;
  funnel: {
    audience_size: number;
    sent: number;
    delivered: number;
    read: number;
    failed: number;
    replied: number;
    unsubscribed: number;
  };
  rates: {
    delivery_rate: number;
    read_rate: number;
    reply_rate: number;
  };
  revenue_attribution: {
    spend_amount: number;
    attributed_revenue: number;
    roi_multiplier: number;
  };
  recent_logs: CampaignRecipientItem[];
}

export interface DripStepItem {
  id: number;
  drip_sequence_id: number;
  step_order: number;
  step_type?: 'send_message' | 'wait_delay' | 'condition_check' | 'assign_rep' | 'schedule_followup';
  channel: 'whatsapp' | 'email' | 'sms';
  delay_interval: number;
  delay_unit: 'minutes' | 'hours' | 'days';
  template_id?: number | null;
  subject?: string | null;
  content?: string | null;
  variable_mappings?: Record<string, string>;
  condition_rules?: Record<string, any> | null;
}

export interface DripEnrollmentItem {
  id: number;
  drip_sequence_id: number;
  entity_type: string;
  entity_id: number;
  status: 'active' | 'paused' | 'completed' | 'exited_conversion' | 'exited_reply' | 'exited_unsubscribed';
  current_step_id?: number | null;
  next_execution_at?: string | null;
  last_message_sent_at?: string | null;
  exit_reason?: string | null;
  current_step?: DripStepItem | null;
  created_at: string;
}

export interface DripSequenceItem {
  id: number;
  tenant_id: number;
  name: string;
  journey_type?: string;
  branch_id?: number | null;
  target_segment_id?: number | null;
  description?: string | null;
  trigger_event: string;
  status: 'draft' | 'active' | 'paused';
  exit_on_conversion: boolean;
  exit_on_reply: boolean;
  exit_on_unsubscribe: boolean;
  respect_quiet_hours: boolean;
  quiet_hours_start: string;
  quiet_hours_end: string;
  max_messages_per_week: number;
  active_enrollments_count: number;
  completed_enrollments_count: number;
  steps?: DripStepItem[];
  enrollments_count?: number;
  created_at: string;
  updated_at: string;
}

// ==========================================
// 17. HRM & EMPLOYEE MANAGEMENT TYPES
// ==========================================

export interface DepartmentItem {
  id: number;
  tenant_id: number;
  name: string;
  code?: string | null;
  description?: string | null;
  department_head_id?: number | null;
  parent_department_id?: number | null;
  status: 'active' | 'archived';
  head?: { id: number; name: string; designation?: string; email?: string } | null;
  parent_department?: { id: number; name: string } | null;
  employees_count?: number;
  created_at: string;
  updated_at: string;
}

export interface ShiftItem {
  id: number;
  tenant_id: number;
  name: string;
  code?: string | null;
  start_time: string;
  end_time: string;
  grace_period_minutes: number;
  half_day_minutes: number;
  full_day_minutes: number;
  break_duration_minutes: number;
  working_days?: string[] | null;
  is_default: boolean;
  status: 'active' | 'inactive';
  employees_count?: number;
  created_at: string;
  updated_at: string;
}

export interface CompanyHolidayItem {
  id: number;
  tenant_id: number;
  name: string;
  date: string;
  description?: string | null;
  is_recurring: boolean;
  created_at: string;
}

export interface LeaveTypeItem {
  id: number;
  tenant_id: number;
  name: string;
  code?: string | null;
  days_allowed_per_year: number;
  is_paid: boolean;
  requires_attachment: boolean;
  carry_forward_days: number;
  status: 'active' | 'inactive';
  created_at: string;
}

export interface LeaveBalanceItem {
  id: number;
  tenant_id: number;
  employee_id: number;
  leave_type_id: number;
  year: number;
  total_entitled: number;
  taken_days: number;
  pending_days: number;
  balance_days: number;
  leave_type?: LeaveTypeItem;
}

export interface LeaveItem {
  id: number;
  tenant_id: number;
  employee_id: number;
  leave_type_id?: number | null;
  type: string;
  start_date: string;
  end_date: string;
  is_half_day: boolean;
  half_day_session?: 'morning' | 'afternoon' | null;
  total_days: number;
  status: 'pending' | 'approved' | 'rejected' | 'cancelled';
  reason?: string | null;
  attachment_url?: string | null;
  approved_by_user_id?: number | null;
  manager_notes?: string | null;
  approved_at?: string | null;
  cancelled_at?: string | null;
  employee?: { id: number; employee_id?: string; name: string; department?: string };
  leave_type?: LeaveTypeItem;
  approver?: { id: number; name: string };
  created_at: string;
}

export interface AttendanceItem {
  id: number;
  tenant_id: number;
  employee_id: number;
  shift_id?: number | null;
  branch_id?: number | null;
  date: string;
  check_in?: string | null;
  check_out?: string | null;
  break_start?: string | null;
  break_end?: string | null;
  total_work_minutes: number;
  total_break_minutes: number;
  status: 'present' | 'absent' | 'late' | 'half_day' | 'on_leave';
  is_late: boolean;
  late_minutes: number;
  is_early_departure?: boolean;
  early_minutes?: number;
  overtime_minutes?: number;
  is_half_day: boolean;
  latitude?: number | null;
  longitude?: number | null;
  accuracy_meters?: number | null;
  is_within_geofence?: boolean;
  selfie_url?: string | null;
  verification_method?: string | null;
  is_field_checkin?: boolean;
  ip_address?: string | null;
  location?: string | null;
  notes?: string | null;
  is_locked: boolean;
  approved_by_user_id?: number | null;
  employee?: { id: number; employee_id?: string; name: string; department?: string; designation?: string };
  shift?: { id: number; name: string };
  created_at: string;
}

export interface AttendanceCorrectionItem {
  id: number;
  tenant_id: number;
  employee_id: number;
  attendance_id?: number | null;
  date: string;
  requested_check_in?: string | null;
  requested_check_out?: string | null;
  reason: string;
  status: 'pending' | 'approved' | 'rejected';
  reviewed_by_user_id?: number | null;
  reviewer_notes?: string | null;
  reviewed_at?: string | null;
  employee?: { id: number; employee_id?: string; name: string; department?: string };
  created_at: string;
}

export interface EmployeeItem {
  id: number;
  tenant_id: number;
  branch_id?: number | null;
  employee_id?: string | null;
  user_id?: number | null;
  name: string;
  profile_image?: string | null;
  email?: string | null;
  phone?: string | null;
  department?: string | null;
  department_id?: number | null;
  designation?: string | null;
  reporting_manager_id?: number | null;
  shift_id?: number | null;
  date_of_joining?: string | null;
  probation_end_date?: string | null;
  contract_end_date?: string | null;
  notice_period_days?: number | null;
  salary?: number | null;
  salary_structure_id?: number | null;
  pan_number?: string | null;
  aadhaar_number?: string | null;
  uan_number?: string | null;
  esi_number?: string | null;
  bank_account_number?: string | null;
  bank_name?: string | null;
  bank_ifsc?: string | null;
  status: 'active' | 'probation' | 'suspended' | 'terminated' | 'resigned';
  employment_type: 'full_time' | 'part_time' | 'contract' | 'intern';
  work_location: string;
  emergency_contact?: { name?: string; relationship?: string; phone?: string } | null;
  skills?: string[] | null;
  documents?: Array<{ name: string; url: string; uploaded_at: string }> | null;
  notes?: string | null;
  department_rel?: DepartmentItem | null;
  manager?: { id: number; name: string; designation?: string; email?: string } | null;
  shift?: ShiftItem | null;
  created_at: string;
  updated_at: string;
}

export interface SalaryStructureItem {
  id: number;
  tenant_id: number;
  name: string;
  code: string;
  description?: string | null;
  basic_percentage: number;
  hra_percentage: number;
  conveyance_fixed: number;
  medical_fixed: number;
  special_allowance_is_balancing: boolean;
  pf_employer_rate: number;
  pf_employee_rate: number;
  pf_wage_ceiling: number;
  esi_employer_rate: number;
  esi_employee_rate: number;
  esi_gross_ceiling: number;
  pt_state: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface EmployeeSalaryItem {
  id: number;
  tenant_id: number;
  employee_id: number;
  salary_structure_id: number;
  ctc_monthly: number;
  ctc_annual: number;
  basic_salary: number;
  hra: number;
  conveyance_allowance: number;
  medical_allowance: number;
  special_allowance: number;
  pf_employee: number;
  pf_employer: number;
  esi_employee: number;
  esi_employer: number;
  professional_tax: number;
  monthly_gross: number;
  monthly_net_take_home: number;
  effective_from: string;
  effective_to?: string | null;
  is_active: boolean;
  notes?: string | null;
  salary_structure?: SalaryStructureItem;
  created_at: string;
  updated_at: string;
}

export interface PayrollBatchItem {
  id: number;
  tenant_id: number;
  branch_id?: number | null;
  month: number;
  year: number;
  batch_number: string;
  status: 'draft' | 'calculated' | 'approved' | 'disbursed' | 'locked';
  total_employees: number;
  total_gross_pay: number;
  total_deductions: number;
  total_net_pay: number;
  total_employer_pf: number;
  total_employer_esi: number;
  approved_by?: number | null;
  approved_at?: string | null;
  disbursed_at?: string | null;
  created_at: string;
  updated_at: string;
  payslips?: PayslipItem[];
}

export interface PayslipItem {
  id: number;
  tenant_id: number;
  payroll_batch_id: number;
  employee_id: number;
  payslip_number: string;
  month: number;
  year: number;
  working_days: number;
  present_days: number;
  paid_leaves_days: number;
  holidays_days: number;
  unpaid_leaves_days: number;
  overtime_hours: number;
  overtime_amount: number;
  basic_salary: number;
  hra: number;
  conveyance_allowance: number;
  medical_allowance: number;
  special_allowance: number;
  gross_earnings: number;
  loss_of_pay: number;
  pf_employee: number;
  esi_employee: number;
  professional_tax: number;
  tds_amount: number;
  other_deductions: number;
  total_deductions: number;
  net_pay: number;
  pf_employer: number;
  esi_employer: number;
  ctc: number;
  payment_status: 'unpaid' | 'paid' | 'on_hold';
  payment_reference?: string | null;
  calculation_trace?: any;
  employee?: { id: number; name: string; employee_id?: string; department?: string; designation?: string };
  created_at: string;
  updated_at: string;
}

export interface EmployeeDocumentItem {
  id: number;
  tenant_id: number;
  employee_id: number;
  document_type: 'appointment_letter' | 'contract' | 'id_proof' | 'pan_card' | 'aadhaar_card' | 'qualification' | 'certificate' | 'other';
  title: string;
  file_url: string;
  file_name?: string | null;
  mime_type?: string | null;
  file_size_kb?: number | null;
  issue_date?: string | null;
  expiry_date?: string | null;
  is_verified: boolean;
  verified_by?: number | null;
  verified_at?: string | null;
  notes?: string | null;
  created_at: string;
  updated_at: string;
}

export interface EmployeePerformanceReviewItem {
  id: number;
  tenant_id: number;
  employee_id: number;
  reviewer_user_id: number;
  review_period: string;
  rating_productivity: number;
  rating_quality: number;
  rating_teamwork: number;
  rating_overall: number;
  strengths?: string | null;
  areas_for_improvement?: string | null;
  action_plan?: string | null;
  status: 'draft' | 'submitted' | 'acknowledged';
  reviewer?: { id: number; name: string; email?: string };
  created_at: string;
  updated_at: string;
}

export interface EmployeeProductivityGoalItem {
  id: number;
  tenant_id: number;
  employee_id: number;
  title: string;
  metric_name: string;
  target_value: number;
  current_value: number;
  start_date: string;
  target_date: string;
  status: 'in_progress' | 'achieved' | 'missed';
  created_at: string;
  updated_at: string;
}

export interface HrReminderItem {
  type: 'probation_ending' | 'contract_expiring' | 'document_expiring' | 'work_anniversary' | 'pending_approval';
  title: string;
  description: string;
  due_date: string;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  entity_id: number;
  employee?: { id: number; name: string; department?: string };
}

export interface MobileCheckInData {
  latitude: number;
  longitude: number;
  accuracy_meters?: number;
  verification_method?: 'gps' | 'selfie_gps' | 'manual';
  selfie_url?: string;
  is_field_checkin?: boolean;
  notes?: string;
}

export interface FullAndFinalSettlementResult {
  employee_id: number;
  unpaid_days: number;
  unpaid_salary_amount: number;
  leave_encashment_days: number;
  leave_encashment_amount: number;
  gratuity_eligible: boolean;
  gratuity_amount: number;
  notice_shortfall_days: number;
  notice_recovery_amount: number;
  gross_earnings: number;
  gross_deductions: number;
  net_payable: number;
  settlement_notes: string;
}

export interface HrDashboardMetrics {
  headcount: {
    total: number;
    active: number;
    probation: number;
    by_employment_type: Record<string, number>;
  };
  department_distribution: Record<string, number>;
  attendance_today: {
    present: number;
    late: number;
    half_day: number;
    on_leave: number;
    absent: number;
  };
  pending_actions: {
    leave_approvals: number;
    attendance_corrections: number;
  };
  upcoming_holidays: CompanyHolidayItem[];
}

// --- PROJECTS, TASKS & MEETINGS MODULE TYPES ---

export interface ProjectMilestoneItem {
  id: number;
  project_id: number;
  title: string;
  description?: string | null;
  due_date?: string | null;
  status: 'pending' | 'in_progress' | 'completed';
  progress: number;
  completed_at?: string | null;
  created_at: string;
  updated_at: string;
}

export interface ProjectCommentItem {
  id: number;
  project_id: number;
  user_id?: number | null;
  comment: string;
  attachments?: Array<{ name: string; url: string }> | null;
  user?: { id: number; name: string; email?: string } | null;
  created_at: string;
  updated_at: string;
}

export interface ProjectTeamMember {
  id: number;
  name: string;
  email?: string;
  pivot?: {
    role: 'manager' | 'member' | 'viewer';
  };
}

export interface ProjectItem {
  id: number;
  tenant_id?: number;
  customer_id?: number | null;
  company_id?: number | null;
  owner_id?: number | null;
  name: string;
  description?: string | null;
  status: 'not_started' | 'in_progress' | 'on_hold' | 'completed' | 'cancelled';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  budget: number;
  estimated_hours: number;
  start_date?: string | null;
  end_date?: string | null;
  progress: number;
  is_archived: boolean;
  archived_at?: string | null;
  completed_at?: string | null;
  customer?: { id: number; name: string } | null;
  company?: { id: number; name: string } | null;
  owner?: { id: number; name: string; email?: string } | null;
  team_members?: ProjectTeamMember[];
  milestones?: ProjectMilestoneItem[];
  tasks_count?: number;
  milestones_count?: number;
  created_at: string;
  updated_at: string;
}

export interface TaskDependencyItem {
  id: number;
  task_id: number;
  depends_on_task_id: number;
  dependency_type: 'finish_to_start' | 'start_to_start';
  depends_on_task?: TaskItem;
  created_at: string;
}

export interface TaskTimeLogItem {
  id: number;
  task_id: number;
  user_id?: number | null;
  start_time?: string | null;
  end_time?: string | null;
  duration_minutes: number;
  description?: string | null;
  is_billable: boolean;
  user?: { id: number; name: string; email?: string } | null;
  created_at: string;
}

export interface MeetingAttendeeItem {
  id: number;
  meeting_id: number;
  user_id?: number | null;
  contact_id?: number | null;
  attendee_name: string;
  attendee_email?: string | null;
  status: 'pending' | 'accepted' | 'declined' | 'tentative';
  notes?: string | null;
  user?: { id: number; name: string; email?: string } | null;
  contact?: { id: number; name: string; email?: string } | null;
}

export interface MeetingItem {
  id: number;
  tenant_id?: number;
  organizer_id?: number | null;
  lead_id?: number | null;
  contact_id?: number | null;
  company_id?: number | null;
  deal_id?: number | null;
  project_id?: number | null;
  title: string;
  description?: string | null;
  agenda?: string | null;
  meeting_date: string;
  start_time: string;
  end_time?: string | null;
  timezone: string;
  location_type: 'online' | 'physical';
  location_address?: string | null;
  meet_link?: string | null;
  notes?: string | null;
  outcome?: string | null;
  status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled' | 'rescheduled';
  rescheduled_from_meeting_id?: number | null;
  organizer?: { id: number; name: string; email?: string } | null;
  attendees?: MeetingAttendeeItem[];
  lead?: { id: number; name: string } | null;
  contact?: { id: number; name: string } | null;
  company?: { id: number; name: string } | null;
  deal?: { id: number; name: string } | null;
  project?: { id: number; name: string } | null;
  rescheduled_from?: MeetingItem | null;
  created_at: string;
  updated_at: string;
}

export interface ProjectMetricsData {
  total_projects: number;
  active_projects: number;
  completed_projects: number;
  on_hold_projects: number;
  total_budget: number;
  average_progress: number;
}

export interface TaskMetricsData {
  total_tasks: number;
  completed_tasks: number;
  pending_tasks: number;
  overdue_tasks: number;
  by_priority: {
    urgent: number;
    high: number;
    medium: number;
    low: number;
  };
  total_hours_logged: number;
}

// --- CUSTOMER SUPPORT, HELPDESK & AI ASSISTANCE TYPES ---

export interface SupportTicketCategoryItem {
  id: number;
  tenant_id?: number;
  name: string;
  description?: string | null;
  parent_id?: number | null;
  is_active: boolean;
  subcategories?: SupportTicketCategoryItem[];
  tickets_count?: number;
  created_at: string;
  updated_at: string;
}

export interface SupportSlaPolicyItem {
  id: number;
  tenant_id?: number;
  name: string;
  description?: string | null;
  priority: 'all' | 'urgent' | 'high' | 'medium' | 'low';
  response_target_minutes: number;
  resolution_target_minutes: number;
  business_hours_only: boolean;
  escalation_threshold_percent: number;
  escalate_to_user_id?: number | null;
  is_default: boolean;
  escalate_to_user?: { id: number; name: string; email?: string } | null;
  tickets_count?: number;
  created_at: string;
  updated_at: string;
}

export interface SupportTicketMessageItem {
  id: number;
  tenant_id?: number;
  ticket_id: number;
  sender_type: 'user' | 'customer' | 'system' | 'ai';
  sender_id?: number | null;
  message: string;
  is_internal: boolean;
  channel: 'email' | 'whatsapp' | 'web_chat' | 'internal';
  attachments?: Array<{ name: string; url: string; size?: number }> | null;
  is_ai_generated: boolean;
  is_draft: boolean;
  user?: { id: number; name: string; email?: string } | null;
  customer?: { id: number; name: string; email?: string } | null;
  created_at: string;
  updated_at: string;
}

export interface SupportTicketItem {
  id: number;
  tenant_id?: number;
  ticket_number: string;
  customer_id?: number | null;
  contact_id?: number | null;
  company_id?: number | null;
  source_channel: 'email' | 'whatsapp' | 'web_chat' | 'phone' | 'portal';
  category_id?: number | null;
  subcategory?: string | null;
  subject: string;
  description?: string | null;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  status: 'new' | 'open' | 'in_progress' | 'waiting_customer' | 'waiting_internal' | 'resolved' | 'closed';
  assigned_user_id?: number | null;
  assigned_team_id?: number | null;
  lead_id?: number | null;
  deal_id?: number | null;
  invoice_id?: number | null;
  tags?: string[] | null;
  attachments?: Array<{ name: string; url: string }> | null;
  sla_policy_id?: number | null;
  first_response_due_at?: string | null;
  resolution_due_at?: string | null;
  first_responded_at?: string | null;
  sla_status: 'within_sla' | 'warning' | 'breached';
  escalated_to_user_id?: number | null;
  escalated_at?: string | null;
  escalation_reason?: string | null;
  resolution_notes?: string | null;
  closure_reason?: string | null;
  resolved_at?: string | null;
  closed_at?: string | null;
  reopen_count: number;
  reopened_at?: string | null;
  reopen_reason?: string | null;
  csat_rating?: number | null;
  csat_comment?: string | null;
  ai_reply_draft?: string | null;
  ai_summary?: string | null;
  ai_troubleshooting_steps?: string | null;
  ai_suggested_category?: string | null;
  ai_confidence_score?: number | null;
  ai_auto_reply_enabled: boolean;
  customer?: { id: number; name: string; email?: string; phone?: string } | null;
  contact?: { id: number; name: string; email?: string } | null;
  company?: { id: number; name: string } | null;
  assigned_user?: { id: number; name: string; email?: string } | null;
  escalated_to?: { id: number; name: string; email?: string } | null;
  category?: SupportTicketCategoryItem | null;
  sla_policy?: SupportSlaPolicyItem | null;
  messages?: SupportTicketMessageItem[];
  messages_count?: number;
  created_at: string;
  updated_at: string;
}

export interface SupportAnalyticsData {
  total_tickets: number;
  open_tickets: number;
  resolved_tickets: number;
  closed_tickets: number;
  breached_tickets: number;
  warning_tickets: number;
  avg_response_minutes: number;
  avg_resolution_minutes: number;
  reopen_rate: number;
  csat_average: number;
  sla_compliance_rate: number;
  by_priority: {
    urgent: number;
    high: number;
    medium: number;
    low: number;
  };
  by_channel: {
    web_chat: number;
    whatsapp: number;
    email: number;
    phone: number;
    portal: number;
  };
}

export interface SupportAiAssistanceData {
  ticket_id: number;
  summary: string;
  suggested_category: string;
  troubleshooting_steps: string[];
  draft_reply: string;
  confidence_score: number;
  relevant_docs: Array<{ title: string; snippet: string }>;
}

export interface ExecutiveKpis {
  total_leads: number;
  new_leads: number;
  qualified_leads: number;
  converted_leads: number;
  lost_leads: number;
  lead_conversion_rate: number;
  open_deals: number;
  won_deals_count: number;
  won_deal_value: number;
  pipeline_value: number;
  win_rate: number;
  average_deal_size: number;
  invoiced_amount: number;
  received_revenue: number;
  overdue_invoices_value: number;
  total_marketing_spend: number;
  provider_reported_spend: number;
  manual_estimated_spend: number;
  cost_per_lead: number;
  roas: number;
  whatsapp_conversations: number;
  ai_conversations: number;
  total_voice_calls: number;
  completed_voice_calls: number;
  tasks_overdue: number;
  open_support_tickets: number;
  breached_support_tickets: number;
  active_projects: number;
  average_project_progress: number;
}

export interface MetricTrendItem {
  current: number;
  previous: number;
  change: number;
  change_percent: number;
}

export interface ExecutiveAnalyticsData {
  period: {
    from: string;
    to: string;
    previous_from: string;
    previous_to: string;
  };
  kpis: ExecutiveKpis;
  trends: Record<string, MetricTrendItem>;
}

export interface LeadAgingBuckets {
  '0_7_days': number;
  '8_14_days': number;
  '15_30_days': number;
  '30_plus_days': number;
}

export interface LeadAnalyticsData {
  total_leads: number;
  new_leads: number;
  qualified_leads: number;
  converted_leads: number;
  lost_leads: number;
  qualification_rate: number;
  conversion_rate: number;
  duplicate_rate: number;
  avg_response_hours: number;
  aging_buckets: LeadAgingBuckets;
  by_source: Array<{
    source: string;
    count: number;
    percentage: number;
    converted: number;
  }>;
  by_status: Array<{
    status: string;
    count: number;
  }>;
  by_location: Array<{
    location: string;
    count: number;
    converted: number;
  }>;
  by_employee: Array<{
    representative: string;
    assigned_count: number;
    converted_count: number;
    conversion_rate: number;
  }>;
  lost_reasons: Array<{
    reason: string;
    count: number;
  }>;
}

export interface SalesAnalyticsData {
  total_deals: number;
  open_deals_count: number;
  won_deals_count: number;
  lost_deals_count: number;
  pipeline_value: number;
  won_value: number;
  lost_value: number;
  weighted_forecast: number;
  win_rate: number;
  loss_rate: number;
  avg_deal_size: number;
  avg_sales_cycle_days: number;
  stage_breakdown: Array<{
    stage: string;
    count: number;
    total_value: number;
    weighted_value: number;
  }>;
  rep_performance: Array<{
    representative: string;
    total_deals: number;
    won_count: number;
    won_value: number;
    quota_target: number;
    achievement_percent: number;
    pipeline_value: number;
  }>;
  revenue_by_source: Array<{
    source: string;
    deals_count: number;
    revenue: number;
  }>;
}

export interface AttributionModelChannelItem {
  channel: string;
  first_touch_revenue: number;
  last_touch_revenue: number;
  linear_revenue: number;
  u_shaped_revenue: number;
}

export interface AttributionComparisonData {
  total_won_deals: number;
  total_won_revenue: number;
  attribution_model_comparison: AttributionModelChannelItem[];
  models_available: Record<string, string>;
  attribution_notice: string;
}

export interface SavedReportItem {
  id: number;
  tenant_id: number;
  user_id: number;
  title: string;
  description?: string | null;
  report_type: string;
  filters?: Record<string, any> | null;
  selected_columns?: string[] | null;
  chart_type?: string | null;
  is_scheduled: boolean;
  schedule_frequency?: string | null;
  schedule_recipients?: string[] | null;
  last_run_at?: string | null;
  created_at: string;
}

export interface ReportExportResult {
  report_type: string;
  headers: string[];
  rows_count: number;
  rows: any[][];
  csv_content: string;
  filename: string;
}

// ==========================================
// Integrations, API Keys & Webhook Platform
// ==========================================

export interface IntegrationConnectionItem {
  id: number;
  name: string;
  status: 'connected' | 'disconnected' | 'expired' | 'error';
  auth_type: string;
  masked_credentials: Record<string, string>;
  scopes?: string[] | null;
  metadata?: Record<string, any> | null;
  connected_at: string;
  last_sync_at?: string | null;
  next_sync_at?: string | null;
  last_error?: string | null;
  is_sync_enabled: boolean;
}

export interface IntegrationDirectoryItem {
  provider: string;
  name: string;
  category: string;
  description: string;
  supported_features: string[];
  required_scopes: string[];
  setup_instructions: string;
  limitations: string[];
  is_connected: boolean;
  connection?: IntegrationConnectionItem | null;
}

export interface IntegrationSyncLogItem {
  id: number;
  tenant_id: number;
  integration_id: number;
  job_id?: string | null;
  sync_type: string;
  status: 'pending' | 'running' | 'success' | 'failed' | 'partial';
  items_processed: number;
  items_failed: number;
  error_details?: string | null;
  duration_ms?: number | null;
  started_at: string;
  completed_at?: string | null;
}

export interface TenantApiKeyItem {
  id: number;
  tenant_id: number;
  user_id?: number | null;
  name: string;
  key_prefix: string;
  scopes: string[];
  allowed_ips?: string[] | null;
  rate_limit_per_minute: number;
  expires_at?: string | null;
  last_used_at?: string | null;
  last_used_ip?: string | null;
  is_revoked: boolean;
  revoked_at?: string | null;
  created_at: string;
  user?: { id: number; name: string; email: string } | null;
}

export interface TenantApiRequestLogItem {
  id: number;
  tenant_id: number;
  api_key_id?: number | null;
  method: string;
  endpoint: string;
  status_code: number;
  ip_address?: string | null;
  user_agent?: string | null;
  response_time_ms: number;
  request_payload_summary?: string | null;
  error_message?: string | null;
  created_at: string;
  api_key?: { id: number; name: string; key_prefix: string } | null;
}

export interface OutboundWebhookItem {
  id: number;
  tenant_id: number;
  name: string;
  url: string;
  masked_signing_secret?: string;
  events: string[];
  is_active: boolean;
  retry_limit: number;
  timeout_seconds: number;
  headers?: Record<string, string> | null;
  last_delivery_at?: string | null;
  last_delivery_status?: string | null;
  delivery_logs_count?: number;
  created_at: string;
}

export interface WebhookDeliveryLogItem {
  id: number;
  tenant_id: number;
  outbound_webhook_id: number;
  event_id: string;
  event_type: string;
  payload: Record<string, any>;
  destination_url: string;
  attempt_number: number;
  status: 'pending' | 'success' | 'failed' | 'retrying';
  response_status_code?: number | null;
  response_body?: string | null;
  error_message?: string | null;
  delivered_at?: string | null;
  next_retry_at?: string | null;
  created_at: string;
  webhook?: { id: number; name: string; url: string } | null;
}

// -------------------------------------------------------------
// PROMPT 22: SaaS Subscription, Hybrid Billing & Credit Ledger
// -------------------------------------------------------------

export interface SubscriptionPlanItem {
  id: number;
  plan_code: string;
  name: string;
  description?: string | null;
  billing_cycle: 'monthly' | 'annual' | 'custom';
  price_monthly: number;
  price_annual: number;
  currency: string;
  trial_days: number;
  grace_period_days: number;
  seat_limit: number;
  lead_limit?: number | null;
  whatsapp_number_limit: number;
  ai_agent_limit: number;
  workflow_limit: number;
  storage_bytes_limit: number;
  api_rpm_limit: number;
  features: string[];
  allowances: {
    llm_tokens: number;
    ai_call_minutes: number;
    telephony_minutes: number;
    whatsapp_messages: number;
    sms_segments: number;
    emails: number;
    storage_mb: number;
  };
  overage_rates: {
    llm_tokens_per_1k: number;
    ai_call_per_minute: number;
    telephony_per_minute: number;
    whatsapp_per_message: number;
    sms_per_segment: number;
    email_per_1k: number;
    storage_per_gb_month: number;
  };
  is_active: boolean;
  is_public: boolean;
  created_at?: string;
}

export interface TenantSubscriptionItem {
  id: number;
  tenant_id: number;
  plan_id: number;
  plan?: SubscriptionPlanItem | null;
  status: 'trialing' | 'active' | 'past_due' | 'grace_period' | 'cancelled' | 'paused';
  billing_cycle: 'monthly' | 'annual' | 'custom';
  current_period_start?: string | null;
  current_period_end?: string | null;
  trial_ends_at?: string | null;
  grace_period_ends_at?: string | null;
  cancelled_at?: string | null;
  cancellation_reason?: string | null;
  billing_contact_name?: string | null;
  billing_contact_email?: string | null;
  billing_tax_id?: string | null;
  billing_address?: string | null;
  current_price?: number;
  currency: string;
  in_grace_period?: boolean;
}

export interface UsageCategoryItem {
  key: string;
  label: string;
  unit: string;
  included_allowance: number;
  used_units: number;
  remaining_allowance: number;
  overage_units: number;
  overage_rate: number;
  overage_charge: number;
  percent_used: number;
}

export interface UsageDashboardData {
  subscription: TenantSubscriptionItem | null;
  plan: SubscriptionPlanItem | null;
  current_period_start: string | null;
  current_period_end: string | null;
  days_remaining_in_cycle: number;
  categories: Record<string, UsageCategoryItem>;
  total_overage_charge: number;
  cost_protection: CostProtectionRuleItem | null;
  total_provider_cost?: number;
}

export interface UsageReservationItem {
  id: number;
  reservation_id: string;
  meter_type: string;
  estimated_units: number;
  reserved_amount: number;
  status: 'held' | 'settled' | 'released' | 'expired';
  expires_at: string;
}

export interface UsageRecordItem {
  id: number;
  record_id: string;
  meter_type: string;
  units_consumed: number;
  unit_rate: number;
  tenant_charge_amount: number;
  provider_cost_amount: number;
  settled_at: string;
  reservation_id?: string | null;
}

export interface CreditWalletData {
  id: number;
  tenant_id: number;
  balance: number;
  reserved_balance: number;
  available_balance: number;
  currency: string;
  auto_recharge_enabled: boolean;
  auto_recharge_threshold: number;
  auto_recharge_amount: number;
  last_recharged_at?: string | null;
}

export interface CreditLedgerEntryItem {
  id: number;
  entry_id: string;
  tenant_id: number;
  entry_type: 'recharge' | 'usage_hold' | 'usage_settle' | 'hold_release' | 'adjustment' | 'refund';
  amount: number;
  balance_after: number;
  reference_id?: string | null;
  description: string;
  metadata?: Record<string, any> | null;
  created_at: string;
}

export interface CostProtectionRuleItem {
  id: number;
  tenant_id: number;
  daily_spend_limit: number;
  monthly_spend_limit: number;
  ai_provider_spend_limit: number;
  is_ai_calling_unlimited_allowed: boolean;
  is_auto_pause_enabled: boolean;
  low_balance_alert_threshold: number;
  alert_emails: string[];
}

export interface SubscriptionInvoiceItem {
  id: number;
  tenant_id: number;
  subscription_id?: number | null;
  invoice_number: string;
  invoice_type: 'subscription' | 'usage_overage' | 'credit_purchase';
  billing_period_start?: string | null;
  billing_period_end?: string | null;
  subtotal: number;
  tax_amount: number;
  total_amount: number;
  currency: string;
  status: 'draft' | 'issued' | 'paid' | 'past_due' | 'void';
  payment_method?: string | null;
  razorpay_payment_id?: string | null;
  paid_at?: string | null;
  due_date?: string | null;
  invoice_pdf_url?: string | null;
  line_items?: Array<{
    description: string;
    quantity: number;
    unit_price: number;
    amount: number;
  }>;
  created_at: string;
}

// -------------------------------------------------------------
// PROMPT 23: Separate Platform Super Admin Console
// -------------------------------------------------------------

export interface PlatformOverviewData {
  tenants: {
    total: number;
    active: number;
    trialing: number;
    suspended: number;
    cancelled: number;
    total_users: number;
  };
  financials: {
    active_subscriptions: number;
    mrr: number;
    arr: number;
    total_collections: number;
    retail_usage_billed: number;
    wholesale_provider_cost: number;
    gross_profit: number;
    gross_margin_percent: number;
  };
  usage_volumes: {
    ai_tokens: number;
    calling_minutes: number;
    whatsapp_messages: number;
    sms_segments: number;
    email_volume: number;
  };
  infrastructure: {
    failed_integrations: number;
    failed_jobs: number;
    system_health: {
      database: string;
      cache: string;
      storage: string;
      overall: string;
    };
  };
}

export interface PlatformTenantItem {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  domain?: string | null;
  subscription_tier: string;
  status: 'active' | 'trialing' | 'past_due' | 'suspended' | 'cancelled';
  settings?: Record<string, any> | null;
  users_count?: number;
  subscription?: TenantSubscriptionItem | null;
  wallet?: CreditWalletData | null;
  created_at: string;
}

export interface PlatformTenantDetailData {
  tenant: PlatformTenantItem;
  recent_ledger: CreditLedgerEntryItem[];
  recent_invoices: SubscriptionInvoiceItem[];
  support_sessions: PlatformSupportSessionItem[];
}

export interface PlatformSupportSessionItem {
  id: number;
  user_id: number;
  tenant_id: number;
  justification_reason: string;
  status: 'active' | 'expired' | 'revoked';
  expires_at: string;
  revoked_at?: string | null;
  user?: { id: number; name: string; email: string } | null;
  created_at: string;
}

export interface PlatformProviderItem {
  id: number;
  provider_key: string;
  name: string;
  category: 'llm' | 'voice' | 'telephony' | 'messaging';
  is_active: boolean;
  health_status: 'healthy' | 'degraded' | 'unhealthy' | 'unconfigured';
  is_configured: boolean;
  available_models: string[];
  default_model?: string | null;
  routing_policy?: Record<string, any> | null;
  rate_limit_rpm: number;
  rate_limit_tpm: number;
  last_health_check_at?: string | null;
  health_check_message?: string | null;
}

export interface PlatformFeatureFlagItem {
  id: number;
  flag_key: string;
  name: string;
  description?: string | null;
  is_enabled: boolean;
  rules?: Record<string, any> | null;
  created_at: string;
  updated_at: string;
}

export interface PlatformMaintenanceModeData {
  is_enabled: boolean;
  message: string;
  bypass_secret?: string | null;
  updated_at?: string | null;
}

export interface PlatformAuditLogItem {
  id: number;
  user_id?: number | null;
  admin_name: string;
  admin_email: string;
  action: string;
  target_type?: string | null;
  target_id?: string | null;
  ip_address?: string | null;
  user_agent?: string | null;
  details?: Record<string, any> | null;
  created_at: string;
}

export interface PlatformQueueStatsData {
  pending_jobs_count: number;
  failed_jobs_count: number;
  failed_jobs: Array<{
    id: number | string;
    connection: string;
    queue: string;
    payload: string;
    exception: string;
    failed_at: string;
  }>;
}

export interface TrackingSettingData {
  id: number;
  tenant_id: number;
  tracking_key: string;
  is_enabled: boolean;
  require_consent: boolean;
  allowed_domains: string[] | null;
  data_retention_days: number;
  enabled_events: string[] | null;
  anonymize_ip: boolean;
  bot_filtering: boolean;
  ga4_property_id?: string | null;
  ga4_measurement_id?: string | null;
  ga4_connected_at?: string | null;
  ga4_cached_properties?: any[] | null;
  created_at: string;
  updated_at: string;
}

export interface TrackingEventItem {
  id: number;
  session_id: string;
  event_name: string;
  event_id?: string | null;
  page_url?: string | null;
  page_title?: string | null;
  referrer?: string | null;
  utm_source?: string | null;
  utm_medium?: string | null;
  utm_campaign?: string | null;
  consent_given: boolean;
  lead_id?: number | null;
  lead?: {
    id: number;
    name: string;
    email: string;
    company_name?: string;
  } | null;
  metadata?: Record<string, any> | null;
  ip_anonymized?: string | null;
  is_bot: boolean;
  created_at: string;
}

export interface TrackingDiagnosticsData {
  tracking_key: string;
  is_enabled: boolean;
  require_consent: boolean;
  allowed_domains: string[] | null;
  data_retention_days: number;
  events_past_24h: number;
  active_sessions_now: number;
  last_event_at?: string | null;
  last_event_type?: string | null;
  events_by_type: Record<string, number>;
  top_utm_sources: Array<{ utm_source: string; count: number }>;
  ga4_connected: boolean;
  ga4_property_id?: string | null;
  ga4_measurement_id?: string | null;
}

export interface GA4ReportData {
  property_id: string;
  measurement_id: string;
  date_range: { start_date: string; end_date: string };
  summary: {
    sessions: number;
    users: number;
    page_views: number;
    conversions: number;
    bounce_rate: number;
    avg_session_duration: string;
  };
  traffic_sources: Array<{
    source_medium: string;
    sessions: number;
    users: number;
    conversions: number;
  }>;
  campaigns: Array<{
    campaign: string;
    sessions: number;
    conversions: number;
  }>;
  landing_pages: Array<{
    page_path: string;
    sessions: number;
    bounce_rate: string;
  }>;
  trend: Array<{
    date: string;
    sessions: number;
    users: number;
    conversions: number;
  }>;
  privacy_notice: string;
}

// -------------------------------------------------------------
// PROMPT 28: Facebook Messenger & Instagram Direct Messages
// -------------------------------------------------------------
export interface SocialChannelAccountItem {
  id: number;
  tenant_id: number;
  provider: 'facebook' | 'instagram';
  page_id: string;
  page_name: string;
  has_access_token?: boolean;
  instagram_business_id?: string | null;
  instagram_username?: string | null;
  is_connected: boolean;
  connected_at?: string | null;
  token_expires_at?: string | null;
  webhook_subscribed: boolean;
  webhook_verify_token?: string | null;
  permissions?: string[] | null;
  metadata?: {
    category?: string;
    picture_url?: string;
    is_instagram_eligible?: boolean;
    [key: string]: any;
  } | null;
  ai_enabled: boolean;
  ai_agent_id?: number | null;
  ai_instructions?: string | null;
  ai_handover_keywords?: string[] | null;
  ai_agent?: {
    id: number;
    name: string;
  } | null;
  created_at: string;
  updated_at: string;
}

export interface CustomerSocialIdentityItem {
  id: number;
  tenant_id: number;
  customer_id?: number | null;
  lead_id?: number | null;
  contact_id?: number | null;
  provider: 'facebook' | 'instagram';
  scoped_id: string;
  username?: string | null;
  display_name?: string | null;
  profile_pic_url?: string | null;
  is_verified: boolean;
  merged_with_id?: number | null;
  metadata?: Record<string, any> | null;
  created_at: string;
  updated_at: string;
  customer?: { id: number; name: string; email?: string } | null;
  lead?: { id: number; name: string; status?: string } | null;
  contact?: { id: number; name: string; phone?: string } | null;
  merged_into?: CustomerSocialIdentityItem | null;
}

export interface SocialWebhookEventItem {
  id: number;
  tenant_id?: number | null;
  provider: 'facebook' | 'instagram';
  page_id?: string | null;
  event_id: string;
  event_type: string;
  payload: Record<string, any>;
  status: 'received' | 'processed' | 'ignored' | 'failed';
  error_message?: string | null;
  created_at: string;
}

export interface SocialDiagnosticsData {
  connected_accounts_count: number;
  facebook_pages_count: number;
  instagram_accounts_count: number;
  total_identities: number;
  linked_identities: number;
  total_social_conversations: number;
  active_24h_windows: number;
  webhook_events_last_24h: number;
  webhook_endpoint: string;
  webhook_verify_token: string;
}

// =========================================================================
// AI CRM ASSISTANT & INTELLIGENCE ENGINE TYPES (Prompt 35)
// =========================================================================

export interface TenantAiConfigurationItem {
  id: number;
  tenant_id: number;
  provider_name: string;
  display_name?: string | null;
  base_url?: string | null;
  default_model: string;
  fallback_model?: string | null;
  available_models?: string[] | null;
  is_active: boolean;
  is_default: boolean;
  temperature: number;
  max_tokens: number;
  rate_limit_rpm: number;
  rate_limit_tpm: number;
  cost_per_1k_input_tokens: number;
  cost_per_1k_output_tokens: number;
  health_status: 'healthy' | 'degraded' | 'unavailable';
  last_health_checked_at?: string | null;
  last_error_message?: string | null;
  has_api_key?: boolean;
}

export interface AiAssistantSessionItem {
  id: number;
  tenant_id: number;
  user_id: number;
  title: string;
  category: string;
  context_parameters?: Record<string, any> | null;
  is_pinned: boolean;
  messages_count?: number;
  messages?: AiAssistantMessageItem[];
  created_at: string;
  updated_at: string;
}

export interface AiAssistantMessageItem {
  id: number;
  tenant_id: number;
  session_id: number;
  role: 'user' | 'assistant' | 'system' | 'tool';
  content: string;
  suggested_actions?: Array<{
    id: number;
    action_type: string;
    summary: string;
    status: string;
    payload?: any;
  }> | null;
  executed_actions?: any[] | null;
  citations_or_records?: Record<string, any> | null;
  prompt_tokens: number;
  completion_tokens: number;
  estimated_cost: number;
  created_at: string;
}

export interface AiProposedActionItem {
  id: number;
  tenant_id: number;
  user_id: number;
  session_id?: number | null;
  action_type: string;
  target_entity_type?: string | null;
  target_entity_id?: number | null;
  summary: string;
  payload: Record<string, any>;
  requires_confirmation: boolean;
  status: 'pending_confirmation' | 'confirmed' | 'executed' | 'rejected' | 'expired';
  confirmed_by_user_id?: number | null;
  confirmed_at?: string | null;
  execution_result?: any;
  rejection_reason?: string | null;
  user?: { id: number; name: string; email: string } | null;
  confirmed_by?: { id: number; name: string; email: string } | null;
  created_at: string;
}

export interface AiSalesInsightItem {
  id: number;
  tenant_id: number;
  entity_type: 'lead' | 'deal';
  entity_id: number;
  summary: string;
  extracted_requirements?: string[] | null;
  qualification_status: 'qualified' | 'unqualified' | 'under_review';
  temperature: 'hot' | 'warm' | 'cold';
  intent_score: number;
  confidence_score: number;
  confidence_rationale?: string | null;
  suggested_next_action?: string | null;
  objections_detected?: string[] | null;
  deal_risk_factors?: string[] | null;
  sales_pitch?: string | null;
  suggested_follow_up_message?: string | null;
  suggested_duplicate_lead_id?: number | null;
  analyzed_at: string;
}

export interface AiMeetingRecordingItem {
  id: number;
  tenant_id: number;
  meeting_id?: number | null;
  created_by_user_id: number;
  title: string;
  audio_file_path?: string | null;
  duration_seconds: number;
  language: string;
  processing_status: 'uploaded' | 'transcribing' | 'analyzed' | 'completed' | 'failed';
  raw_transcript?: string | null;
  meeting_summary?: string | null;
  key_discussion_points?: string[] | null;
  customer_requirements?: string[] | null;
  objections_raised?: string[] | null;
  action_items?: Array<{ title: string; due_date?: string; assignee?: string }> | null;
  linked_contact_id?: number | null;
  linked_deal_id?: number | null;
  error_message?: string | null;
  creator?: { id: number; name: string; email: string } | null;
  created_at: string;
}

export interface AiDocumentExtractionItem {
  id: number;
  tenant_id: number;
  uploaded_by_user_id: number;
  original_filename: string;
  file_path: string;
  file_type: string;
  document_classification: 'invoice' | 'purchase_order' | 'contract_agreement' | 'quotation' | 'identity' | 'general';
  processing_status: 'uploaded' | 'processing' | 'extracted' | 'verified' | 'committed' | 'failed';
  overall_confidence: number;
  extracted_fields?: {
    customer_name?: string;
    invoice_no?: string;
    gstin?: string;
    amount?: number;
    currency?: string;
    invoice_date?: string;
    due_date?: string;
    po_number?: string;
    payment_terms?: string;
    [key: string]: any;
  } | null;
  field_confidences?: Record<string, number> | null;
  user_corrections?: Record<string, any> | null;
  committed_to_entity_id?: number | null;
  committed_to_entity_type?: string | null;
  committed_at?: string | null;
  uploader?: { id: number; name: string; email: string } | null;
  created_at: string;
}

export interface AiGeneratedContentItem {
  id: number;
  tenant_id: number;
  user_id: number;
  content_type: string;
  tone: string;
  language: string;
  prompt_or_context: string;
  generated_text: string;
  is_used: boolean;
  created_at: string;
}

export interface AiUsageReportsData {
  summary: {
    total_requests: number;
    total_tokens: number;
    total_cost: number;
    failed_requests: number;
    success_rate: number;
  };
  by_model: Array<{
    model: string;
    count: number;
    tokens: number;
    cost: number;
  }>;
  by_feature: Array<{
    feature_code: string;
    count: number;
    cost: number;
  }>;
  by_user: Array<{
    user_id: number;
    count: number;
    cost: number;
    user?: { id: number; name: string; email: string };
  }>;
}

// ==========================================
// ADVANCED LEAD MANAGEMENT & LEAKAGE (PROMPT 36)
// ==========================================

export interface LeadRoutingRuleItem {
  id: number;
  tenant_id: number;
  name: string;
  priority: number;
  is_active: boolean;
  conditions?: {
    sources?: string[];
    cities?: string[];
    products?: string[];
    min_budget?: number;
    branch_id?: number;
    [key: string]: any;
  } | null;
  assignment_method: 'round_robin' | 'least_active' | 'availability' | 'branch_based' | 'location_based' | 'department_based' | 'product_based' | 'source_based' | 'campaign_based' | 'manual';
  target_user_ids?: number[] | null;
  target_team_id?: number | null;
  target_branch_id?: number | null;
  fallback_user_id?: number | null;
  fallback_user?: { id: number; name: string; email?: string } | null;
  last_assigned_index: number;
  created_at: string;
  updated_at: string;
}

export interface LeadSlaPolicyItem {
  id: number;
  tenant_id: number;
  name: string;
  is_default: boolean;
  first_response_time_minutes: number;
  followup_deadline_hours: number;
  max_idle_hours: number;
  working_hours_only: boolean;
  business_hours_start: string;
  business_hours_end: string;
  working_days?: number[] | null;
  holiday_calendar?: string[] | null;
  escalation_level_1_minutes: number;
  escalation_level_2_minutes: number;
  auto_reassign_on_escalation: boolean;
  manager_user_id?: number | null;
  manager_user?: { id: number; name: string; email?: string } | null;
  created_at: string;
  updated_at: string;
}

export interface LeadEscalationLogItem {
  id: number;
  tenant_id: number;
  lead_id: number;
  sla_policy_id?: number | null;
  escalation_level: number;
  reason: string;
  action_taken: string;
  previous_user_id?: number | null;
  previous_user?: { id: number; name: string } | null;
  new_user_id?: number | null;
  new_user?: { id: number; name: string } | null;
  triggered_at: string;
  lead?: { id: number; name: string; phone?: string; source?: string; status: string } | null;
}

export interface LeadFollowupItem {
  id: number;
  tenant_id: number;
  lead_id: number;
  assigned_user_id?: number | null;
  assigned_user?: { id: number; name: string } | null;
  followup_type: 'call' | 'whatsapp' | 'email' | 'meeting' | 'task_reminder';
  scheduled_at: string;
  status: 'pending' | 'completed' | 'missed' | 'rescheduled';
  outcome?: 'connected' | 'busy' | 'no_answer' | 'interested' | 'callback_requested' | 'not_interested' | 'meeting_scheduled' | null;
  outcome_notes?: string | null;
  completed_at?: string | null;
  rescheduled_to_id?: number | null;
  created_by?: number | null;
  lead?: { id: number; name: string; phone?: string; email?: string; company_name?: string; status: string } | null;
  created_at: string;
}

export interface LeadStageHistoryItem {
  id: number;
  tenant_id: number;
  lead_id: number;
  user_id?: number | null;
  user?: { id: number; name: string } | null;
  from_stage?: string | null;
  to_stage: string;
  time_in_previous_stage_seconds: number;
  reason?: string | null;
  notes?: string | null;
  created_at: string;
}

export interface LeadMergeRequestItem {
  id: number;
  tenant_id: number;
  master_lead_id: number;
  master_lead?: { id: number; name: string; phone?: string; email?: string; company_name?: string } | null;
  duplicate_lead_id: number;
  duplicate_lead?: { id: number; name: string; phone?: string; email?: string; company_name?: string } | null;
  status: 'pending' | 'approved' | 'rejected';
  requested_by_user_id: number;
  requested_by?: { id: number; name: string } | null;
  approved_by_user_id?: number | null;
  approved_by?: { id: number; name: string } | null;
  field_overrides?: Record<string, any> | null;
  similarity_reasons?: string[] | null;
  rejection_reason?: string | null;
  merged_at?: string | null;
  created_at: string;
}

export interface LeadPortalIntegrationItem {
  id: number;
  tenant_id: number;
  portal: 'indiamart' | 'justdial' | 'tradeindia' | 'generic_webhook' | 'referral';
  name: string;
  api_key?: string | null;
  webhook_secret?: string | null;
  is_active: boolean;
  auto_assign: boolean;
  auto_score: boolean;
  default_campaign?: string | null;
  total_captured_count: number;
  last_sync_at?: string | null;
  created_at: string;
}

export interface LeadScoringConfigurationItem {
  id: number;
  tenant_id: number;
  hot_threshold: number;
  warm_threshold: number;
  weights?: Record<string, number> | null;
  is_active: boolean;
  created_at: string;
}

export interface LeadPerformanceDashboardData {
  summary: {
    total_leads: number;
    active_leads: number;
    converted_leads: number;
    lost_leads: number;
    conversion_rate_percent: number;
    total_pipeline_value: number;
  };
  source_performance: Array<{
    source: string;
    total_leads: number;
    converted_count: number;
    conversion_rate: number;
    pipeline_value: number;
    avg_score: number;
  }>;
  campaign_performance: Array<{
    campaign: string;
    leads_count: number;
    converted_count: number;
    pipeline_value: number;
  }>;
  employee_performance: Array<{
    user_id: number;
    name: string;
    assigned_leads: number;
    contacted_leads: number;
    converted_leads: number;
    conversion_rate: number;
    avg_first_response_minutes: number;
    sla_breaches: number;
  }>;
  lead_ageing: {
    under_24_hours: number;
    '1_to_3_days': number;
    '4_to_7_days': number;
    '8_to_14_days': number;
    '15_to_30_days': number;
    over_30_days: number;
  };
  followup_metrics: {
    total_followups: number;
    completed: number;
    pending: number;
    overdue: number;
    rescheduled: number;
    completion_rate_percent: number;
  };
  leakage_and_sla: {
    total_sla_tracked_leads: number;
    within_sla: number;
    breached_sla: number;
    escalated_leads: number;
    compliance_rate_percent: number;
    total_escalation_events: number;
    level_1_warnings: number;
    level_2_reassignments: number;
  };
  lost_and_recycled: {
    lost_reasons_breakdown: Record<string, number>;
    recycled_leads_count: number;
    qualification_breakdown: Record<string, number>;
  };
}

// --- PROMPT 39: ADVANCED SALES, CONTRACTS, COMMISSIONS & FORECASTING ---

export interface ContractTemplateItem {
  id: number;
  tenant_id: number;
  name: string;
  code: string;
  category: 'nda' | 'msa' | 'sla' | 'sow' | 'quotation_terms' | 'custom';
  content_markdown: string;
  variable_keys?: string[];
  is_active: boolean;
  version: number;
  created_at?: string;
  updated_at?: string;
}

export interface ContractItem {
  id: number;
  tenant_id: number;
  deal_id?: number | null;
  customer_id?: number | null;
  contract_template_id?: number | null;
  contract_number: string;
  title: string;
  contract_type: 'proposal' | 'contract' | 'nda' | 'sla' | 'custom';
  version: number;
  content_markdown: string;
  contract_value?: number | null;
  currency: string;
  status: 'draft' | 'pending_approval' | 'approved' | 'sent_for_signature' | 'partially_signed' | 'signed' | 'declined' | 'expired';
  e_signature_provider: 'aadhaar_esign' | 'docusign' | 'internal_otp' | 'clickwrap';
  signer_name?: string | null;
  signer_email?: string | null;
  signer_phone?: string | null;
  sent_at?: string | null;
  signed_at?: string | null;
  declined_at?: string | null;
  decline_reason?: string | null;
  audit_hash?: string | null;
  audit_metadata?: Record<string, any> | null;
  created_by_user_id?: number | null;
  approved_by_user_id?: number | null;
  created_at?: string;
  updated_at?: string;
  deal?: DealItem | null;
  customer?: any;
  created_by_user?: { id: number; name: string; email?: string } | null;
  approved_by_user?: { id: number; name: string; email?: string } | null;
}

export interface DiscountRuleItem {
  id: number;
  tenant_id: number;
  name: string;
  role_name?: string | null;
  min_deal_value: number;
  max_discount_percentage: number;
  max_discount_amount?: number | null;
  requires_approval_above_percent: number;
  approver_role?: string | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface DiscountApprovalItem {
  id: number;
  tenant_id: number;
  quotation_id?: number | null;
  deal_id?: number | null;
  requested_by_user_id: number;
  requested_discount_percent: number;
  requested_discount_amount: number;
  rule_id?: number | null;
  status: 'pending' | 'approved' | 'rejected';
  action_by_user_id?: number | null;
  action_notes?: string | null;
  action_at?: string | null;
  created_at?: string;
  updated_at?: string;
  requested_by_user?: { id: number; name: string; email?: string } | null;
  action_by_user?: { id: number; name: string; email?: string } | null;
}

export interface CommissionPlanItem {
  id: number;
  tenant_id: number;
  name: string;
  code: string;
  plan_type: 'percentage' | 'fixed' | 'tiered';
  calculation_basis: 'deal_won' | 'payment_received';
  base_rate_percentage?: number | null;
  fixed_amount_per_deal?: number | null;
  tier_rules?: Array<{
    min_value: number;
    max_value?: number | null;
    rate_percentage: number;
  }> | null;
  is_active: boolean;
  effective_from?: string | null;
  effective_until?: string | null;
  description?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface SalesCommissionItem {
  id: number;
  tenant_id: number;
  commission_plan_id: number;
  deal_id?: number | null;
  payment_id?: number | null;
  invoice_id?: number | null;
  user_id: number;
  deal_value: number;
  commission_basis_amount: number;
  calculated_commission_amount: number;
  rate_applied_percentage?: number | null;
  status: 'pending_approval' | 'approved' | 'paid' | 'clawback_reversed' | 'cancelled';
  approved_by_user_id?: number | null;
  approved_at?: string | null;
  paid_at?: string | null;
  payout_reference?: string | null;
  reversed_at?: string | null;
  reversal_reason?: string | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
  user?: { id: number; name: string; email?: string } | null;
  deal?: DealItem | null;
  commission_plan?: CommissionPlanItem | null;
  approved_by_user?: { id: number; name: string; email?: string } | null;
}

export interface AdvancedSalesForecastData {
  unweighted_pipeline: number;
  weighted_forecast: number;
  committed_forecast: number;
  best_case_forecast: number;
  closed_won_value: number;
  closed_lost_value: number;
  total_open_deals_count: number;
  monthly_buckets: Array<{
    month: string;
    unweighted_value: number;
    weighted_value: number;
    deals_count: number;
  }>;
  stage_breakdown: Array<{
    stage: string;
    probability: number;
    deals_count: number;
    total_value: number;
    weighted_value: number;
  }>;
  employee_performance: Array<{
    user_id: number;
    user_name: string;
    open_deals_count: number;
    open_pipeline_value: number;
    weighted_value: number;
    won_deals_count: number;
    won_value: number;
  }>;
  branch_performance: Array<{
    branch_id: number | null;
    open_deals_count: number;
    open_pipeline_value: number;
    weighted_value: number;
  }>;
  assumptions_notice: string;
}

// Indian GST Invoicing, Tax Management & Compliance
export interface GstRegistrationItem {
  id: number;
  tenant_id: number;
  branch_id?: number | null;
  business_legal_name: string;
  trade_name?: string | null;
  gstin: string;
  pan?: string | null;
  registered_address: string;
  city: string;
  state: string;
  state_code: string;
  pincode: string;
  business_type: string;
  gst_registration_status: string;
  tax_calculation_preference: 'exclusive' | 'inclusive';
  default_place_of_supply: string;
  invoice_prefix: string;
  financial_year: string;
  invoice_numbering_series: string;
  is_default: boolean;
  created_at: string;
}

export interface GstVerificationResult {
  id: number;
  tenant_id: number;
  gstin: string;
  legal_name?: string | null;
  trade_name?: string | null;
  state_code?: string | null;
  state_name?: string | null;
  registered_address?: string | null;
  registration_status: string;
  registration_type: string;
  is_verified: boolean;
  validation_source: string;
  raw_response?: any;
  verified_at?: string | null;
}

export interface GstTaxCalculationResult {
  items: Array<{
    item_name: string;
    quantity: number;
    unit_price: number;
    discount_amount: number;
    taxable_amount: number;
    tax_rate: number;
    tax_amount: number;
    cgst_rate: number;
    cgst_amount: number;
    sgst_rate: number;
    sgst_amount: number;
    igst_rate: number;
    igst_amount: number;
    cess_rate: number;
    cess_amount: number;
    total_price: number;
    hsn_sac_code?: string;
  }>;
  subtotal: number;
  discount_amount: number;
  cgst_amount: number;
  sgst_amount: number;
  igst_amount: number;
  cess_amount: number;
  total_tax_amount: number;
  unrounded_total: number;
  round_off_amount: number;
  grand_total: number;
  amount_in_words: string;
  is_reverse_charge: boolean;
  supply_type: string;
}

export interface GstTaxSummaryReport {
  period: {
    start_date: string;
    end_date: string;
  };
  outward_supplies: {
    taxable_value: number;
    cgst: number;
    sgst: number;
    igst: number;
    cess: number;
    total_output_tax: number;
    total_invoiced_value: number;
  };
  inward_supplies_itc: {
    total_itc_available: number;
    cgst_itc: number;
    sgst_itc: number;
    igst_itc: number;
    total_bills_value: number;
  };
  net_tax_liability: {
    cgst_payable: number;
    sgst_payable: number;
    igst_payable: number;
    total_net_payable: number;
  };
  compliance_disclaimer: string;
}

export interface Gstr1SalesRegister {
  b2b_invoices: Array<{
    invoice_number: string;
    invoice_date: string;
    buyer_name: string;
    buyer_gstin?: string | null;
    place_of_supply: string;
    supply_type: string;
    taxable_value: number;
    cgst: number;
    sgst: number;
    igst: number;
    cess: number;
    total_invoice_value: number;
    irn?: string | null;
    eway_bill_number?: string | null;
  }>;
  b2c_large: any[];
  b2c_small: any[];
  credit_debit_notes: Array<{
    invoice_number: string;
    original_invoice?: string | null;
    reason?: string | null;
    taxable_value: number;
    cgst: number;
    sgst: number;
    igst: number;
    total_invoice_value: number;
  }>;
  exports: any[];
  total_invoices_count: number;
  total_taxable_value: number;
  total_tax_value: number;
  total_invoice_value: number;
  disclaimer: string;
}

export interface HsnSacSummaryItem {
  hsn_sac: string;
  description: string;
  is_service: boolean;
  unit: string;
  total_quantity: number;
  total_taxable: number;
  cgst: number;
  sgst: number;
  igst: number;
  cess: number;
  total_tax: number;
  total_value: number;
}

export interface ComplianceReminderItem {
  id: number;
  tenant_id: number;
  title: string;
  compliance_type: string;
  due_date: string;
  financial_period?: string | null;
  assigned_user_id?: number | null;
  responsible_accountant_name?: string | null;
  frequency: string;
  status: 'pending' | 'in_progress' | 'completed' | 'overdue';
  priority: 'low' | 'medium' | 'high' | 'critical';
  escalation_level: number;
  notes?: string | null;
  completed_at?: string | null;
}

export interface ComplianceDocumentItem {
  id: number;
  tenant_id: number;
  title: string;
  document_type: string;
  document_number?: string | null;
  file_name: string;
  file_path: string;
  file_size: number;
  mime_type: string;
  issue_date?: string | null;
  expiry_date?: string | null;
  is_expired: boolean;
  version: string;
  tags?: string[] | null;
  created_at: string;
}

// Complete Customer Support, Help Desk & Customer Success (Prompt 42)
export interface SupportTicketTransitionItem {
  id: number;
  tenant_id: number;
  ticket_id: number;
  from_status?: string | null;
  to_status: string;
  user_id?: number | null;
  reason?: string | null;
  created_at: string;
  user?: { id: number; name: string; email?: string } | null;
}

export interface CustomerFeedbackItem {
  id: number;
  tenant_id: number;
  ticket_id?: number | null;
  customer_id: number;
  csat_score: number;
  nps_score?: number | null;
  rating_category: string;
  feedback_text?: string | null;
  channel: string;
  submitted_at: string;
  ticket?: any;
  customer?: any;
}

export interface CustomerHealthScoreItem {
  id: number;
  tenant_id: number;
  customer_id: number;
  health_score: number;
  health_status: 'healthy' | 'neutral' | 'at_risk' | 'critical';
  churn_risk_level: 'low' | 'medium' | 'high';
  onboarding_status: 'not_started' | 'in_progress' | 'completed' | 'stalled';
  renewal_date?: string | null;
  contract_value: number;
  factors: {
    ticket_support: { score: number; weight_percent: number; explanation: string };
    customer_satisfaction: { score: number; weight_percent: number; explanation: string };
    financial_health: { score: number; weight_percent: number; explanation: string };
    onboarding_engagement: { score: number; weight_percent: number; explanation: string };
    summary_narrative: string;
  };
  success_owner_id?: number | null;
  last_check_in_date?: string | null;
  next_check_in_date?: string | null;
  check_in_notes?: string | null;
  customer?: any;
  success_owner?: any;
}

export interface WarrantyContractItem {
  id: number;
  tenant_id: number;
  customer_id: number;
  contract_type: 'warranty' | 'amc' | 'service_agreement';
  contract_number: string;
  product_id?: number | null;
  product_name?: string | null;
  serial_number?: string | null;
  start_date: string;
  end_date: string;
  coverage_details?: string | null;
  renewal_date?: string | null;
  renewal_price: number;
  status: 'active' | 'expired' | 'pending_renewal' | 'cancelled';
  service_team_id?: number | null;
  assigned_engineer_id?: number | null;
  preventive_maintenance_interval_days: number;
  last_service_date?: string | null;
  next_service_date?: string | null;
  notes?: string | null;
  customer?: any;
  assigned_engineer?: any;
}

export interface KnowledgeCategoryItem {
  id: number;
  tenant_id: number;
  name: string;
  slug: string;
  description?: string | null;
  icon?: string | null;
  is_active: boolean;
  sort_order: number;
  articles_count?: number;
}

export interface KnowledgeArticleItem {
  id: number;
  tenant_id: number;
  category_id?: number | null;
  title: string;
  slug: string;
  content: string;
  excerpt?: string | null;
  tags?: string[] | null;
  is_published: boolean;
  visibility: 'public' | 'internal' | 'customer_portal';
  view_count: number;
  helpful_count: number;
  not_helpful_count: number;
  version: string;
  author_user_id?: number | null;
  approved_by_user_id?: number | null;
  approved_at?: string | null;
  category?: KnowledgeCategoryItem | null;
}

export interface AiAnswerSuggestion {
  suggested_answer: string;
  confidence_score: number;
  referenced_articles: Array<{
    id: number;
    title: string;
    slug: string;
  }>;
}









