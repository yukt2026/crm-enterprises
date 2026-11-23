# Omnichannel Customer Communication Hub Module

## 1. Overview & Objectives (Prompt 33)

The **Omnichannel Customer Communication Hub** unifies all customer communication channels into an enterprise-grade, tenant-isolated, 3-panel Unified Inbox. Employees manage customer conversations from a single hub without switching between fragmented third-party apps, while administrators configure automated routing rules, business hours schedules, branch assignments, and SLA policies.

### Core Capabilities Delivered:
1. **8 Connected Channels**: WhatsApp Business Platform, Facebook Messenger, Instagram Direct Messages, Telegram Bot, Email (SMTP/IMAP), SMS (Twilio/DLT), Website Live Chat, and Cloud Telephony (Twilio/Exotel).
2. **Official Telegram Bot API Integration**: Full bidirectional adapter handling outbound dispatch, inbound webhook ingestion, and connection testing.
3. **Channel Settings & Multi-Branch Business Hours**: Channel-specific business working hours (days of week, operating windows, timezone) mapped to branches and departments.
4. **Intelligent Routing Rules Engine**: Evaluates prioritized rules (`round_robin`, `least_active`, `branch`, `department`, `language`, `lead_source`, `working_hours`, `direct`).
5. **Business-Hours Aware SLA & Escalation Engine**: Calculates first response time (FRT) and resolution deadlines, automatically pausing during off-hours and weekends, tagging conversations as `on_track`, `warning`, or `breached`.
6. **Agent Collision Detection & Presence Heartbeat**: Real-time heartbeat tracking active viewing/typing agents, rendering a live collision warning banner in the center workspace.
7. **Conversation Locking & Exclusive Handling**: Allows agents to lock conversations to prevent duplicate replies, with audit history and take-over capability.
8. **Restricted Financial Overview in Conversation Context**: Right panel displays invoice summaries and balances strictly gated by RBAC permissions (`invoices.view` or authorized roles).

---

## 2. Architecture & Data Schema

### 2.1 Database Tables & Migration
Defined in `backend/database/migrations/2026_11_23_000027_create_omnichannel_customer_communication_hub_tables.php`:

| Table | Purpose | Key Columns |
| :--- | :--- | :--- |
| `branches` | Multi-branch organizational structure | `tenant_id`, `name`, `code`, `city`, `is_active` |
| `telegram_accounts` | Official Telegram Bot integrations | `tenant_id`, `bot_name`, `bot_username`, `bot_token`, `webhook_url`, `is_connected`, `branch_id`, `department_id` |
| `omnichannel_channel_settings` | Channel-specific configuration & business hours | `tenant_id`, `channel`, `branch_id`, `department_id`, `business_hours` (JSON: `timezone`, `days`, `start`, `end`), `is_active`, `health_status` |
| `omnichannel_routing_rules` | Automated assignment rules | `tenant_id`, `name`, `rule_type`, `channel`, `priority`, `conditions` (JSON), `target_user_id`, `target_team_id`, `is_active` |
| `omnichannel_sla_policies` | Service Level Agreement benchmarks | `tenant_id`, `name`, `priority`, `channel`, `first_response_target_minutes`, `resolution_target_minutes`, `business_hours_enabled`, `is_active` |
| `omnichannel_agent_presences` | Real-time presence heartbeat | `tenant_id`, `user_id`, `conversation_id`, `is_typing`, `last_seen_at` |
| `whatsapp_conversations` (Extended) | Unified thread schema | `locked_by_user_id`, `locked_at`, `sla_policy_id`, `sla_status`, `first_response_due_at`, `resolution_due_at`, `is_archived`, `branch_id`, `department_id`, `language` |

---

## 3. Communication Channel Adapters

### 3.1 Telegram Channel Adapter (`TelegramChannelAdapter.php`)
- **Adapter Interface**: Implements `ChannelAdapterInterface` (`send`, `sendTemplate`, `getChannelName`).
- **Outbound Dispatch**: Sends messages via Telegram Bot API `sendMessage` (`https://api.telegram.org/bot<TOKEN>/sendMessage`).
- **Webhook Ingestion**: Public route `/api/v1/webhooks/social/telegram/{tenantId}` receives Telegram update objects:
  - Resolves customer identity by Telegram User ID (`telegram:<USER_ID>`).
  - Creates or fetches conversation in `whatsapp_conversations` (`channel = 'telegram'`).
  - Stores inbound message in `whatsapp_messages` (`delivery_status = 'delivered'`).
  - Evaluates SLA policy and initiates automatic assignment rules.

### 3.2 Channel Health & Live Connection Testing
- Endpoint `POST /api/v1/crm/inbox/channels/test-connection`:
  - Validates API tokens, webhooks, and provider availability for any of the 8 channels.
  - Returns `{ channel, status: "connected"|"degraded"|"disconnected", message, tested_at }`.
- Endpoint `GET /api/v1/crm/inbox/channels/health`:
  - Aggregates operational health status across all configured channels for the active tenant.

---

## 4. Intelligent Routing & SLA Calculation Engines

### 4.1 Routing Engine (`OmnichannelRoutingService.php`)
Evaluates ordered rules by priority:
- `branch`: Routes to agents belonging to the specific branch.
- `department`: Routes to agents in the specified department.
- `language`: Routes to agents matching the customer's language.
- `working_hours`: Routes to after-hours on-call agents when outside configured operating hours.
- `least_active`: Selects the online agent with the lowest open conversation count.
- `round_robin`: Cycles evenly across qualified agents.

### 4.2 Business-Hours SLA Engine (`OmnichannelSlaService.php`)
- Reads configured business hours schedule: e.g. Monday–Friday, 09:00–18:00 in tenant timezone.
- Adds response/resolution minutes only during active working hours, rolling over remaining minutes into the next business day.
- Tags conversations dynamically:
  - `on_track`: Target deadline is in the future.
  - `warning`: Remaining time is less than 20% of target duration.
  - `breached`: Current time exceeds the due timestamp.
- Recalculation endpoint `POST /api/v1/crm/inbox/sla-policies/recalculate` evaluates all open conversations in batch.

---

## 5. Frontend 3-Panel Unified Inbox (`OmnichannelInboxView.tsx`)

### 5.1 Left Panel (Conversation Navigation & Filters)
- **Top Channel Ribbon**: Filter by All, WhatsApp, Facebook, Instagram, Telegram, Email, SMS, WebChat, or Voice.
- **Scope Filters**: `All`, `My`, `Queue` (Unassigned), `Priority` (High/Urgent), `Archived`.
- **Card Indicators**:
  - SLA Status Badges (`On Track`, `SLA Warning`, `Breached`).
  - Padlock icon if locked by an agent.
  - Assigned agent name, unread count badge, and branch pill.

### 5.2 Center Panel (Live Thread, Collision Alert & Lock)
- **Agent Collision Warning Banner**: Shows animated warning if other agents have active presence heartbeats within 30 seconds.
- **Lock / Unlock Button**: Prevents conflicting replies; displays "Locked by [Agent]" banner.
- **Archive / Unarchive Button**: Toggles archive status without losing conversation thread context.
- **SLA Badge**: Live indicator of current SLA deadline compliance.

### 5.3 Right Panel (Context & Permission-Gated Financials)
- **Customer Profile**: Name, email, phone, CRM Lead status, assigned agent/team.
- **Communication Consents**: Channel-specific opt-in/opt-out status with one-click toggles.
- **Financial Records (Strictly Gated)**:
  - If user possesses `invoices.view` or Admin/Accountant role: displays pending invoices count, total invoiced, balance due, and recent unpaid invoice list.
  - If user lacks financial permissions: displays a restricted shield badge with zero financial data leaked.

### 5.4 Channel Hub & Settings Modal
Tabs:
1. **Channel Hub**: Manage all 8 channels with health badges, business hours, and connection tests.
2. **Telegram Bot**: Connect bot token from @BotFather, review webhook URL, test connection.
3. **Routing Rules**: Create and prioritize multi-factor assignment rules.
4. **SLA Policies**: Configure response and resolution targets with business-hours pauses; trigger bulk recalculation.
5. **Gateways & Suppressions**: Manage SMTP/IMAP email accounts, Twilio SMS, WebChat embed snippets, and opt-out suppression registry.

---

## 6. Verification & Test Suite

### 6.1 Backend Feature Tests (`OmnichannelCustomerCommunicationHubTest.php`)
- `it_connects_telegram_bot_account_and_handles_webhook()`: Verified.
- `it_manages_channel_settings_with_branch_and_business_hours()`: Verified.
- `it_tests_channel_connection_endpoints()`: Verified.
- `it_locks_and_unlocks_conversations_to_prevent_collisions()`: Verified.
- `it_archives_and_filters_conversations()`: Verified.
- `it_records_agent_presence_heartbeat_and_detects_collision()`: Verified.
- `it_creates_routing_rules_and_executes_least_active_routing()`: Verified.
- `it_calculates_sla_due_dates_with_business_hours_pause()`: Verified.
- `it_strictly_gates_financial_data_in_unified_inbox_by_permission()`: Verified.
- `it_strictly_enforces_cross_tenant_omnichannel_isolation()`: Verified.

**Result: 10 / 10 passed (66 assertions). Total backend suite: 236 / 236 passed (1,611 assertions).**

### 6.2 Frontend Integration Suite (`apiIntegration.test.ts`)
- **Step 47**: Tests Channel Health, Channel Settings with business hours, Live Connection Test, Telegram Bot connect/disconnect, Routing Rules creation/deletion, SLA Policy recalculation, Conversation lock/unlock, Conversation archive/unarchive, Presence heartbeat & collision check, and Detailed BI Reports.

**Result: 48 / 48 passed, 0 failed.**

### 6.3 Production Build
- `npm.cmd run build`: Vite v6.4.3 production bundle built cleanly in 8.25s with 0 errors.
