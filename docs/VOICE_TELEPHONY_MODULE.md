# AI Voice & Telephony Automation Module Documentation

## 1. Overview & Architectural Principles

The **AI Voice & Telephony Automation Module** equips the Enterprise SaaS CRM with enterprise-grade voice intelligence and automated telephony capabilities. It connects directly with authorized cloud telephony carriers—featuring a specialized integration for **Exotel** (tailored for India domestic calling with DLT Entity ID and TRAI regulatory compliance) and **Twilio** (for global calling)—without utilizing fake browser audio or simulated browser wrappers.

### Key Architectural Tenets:
1. **Low-Latency Decoupling**: Voice session streaming and real-time audio chunk processing (STT, LLM streaming, TTS) are abstracted into dedicated pipeline services (`VoiceAiPipelineService`) decoupled from standard synchronous HTTP requests.
2. **Official Carrier Gateways**: Telephony providers (`Exotel`, `Twilio`, `SIP`) are interfaced through a polymorphic adapter architecture (`TelephonyProviderInterface`) generating compliant provider markup (ExoML `<Response><Say>...<Record>` for Exotel, TwiML for Twilio) and handling webhooks asynchronously.
3. **Regulatory & DND Compliance**:
   - Outbound calling to Indian phone numbers (`+91`) strictly validates TRAI / DoT time windows (**09:00 AM to 09:00 PM IST**).
   - Real-time checking against the tenant's `telephony_opt_outs` DND suppression list blocks unconsented or opted-out calls.
   - Mandatory call recording disclosures are automatically injected into agent call openings.
   - Verbal customer opt-out detection automatically registers the recipient in the suppression list.
4. **CRM Automation Without Hallucination**:
   - Post-call webhooks and transcripts trigger automated qualification scoring, lead status updates, timeline activity recording (`CrmActivity`), and callback `Task` creation.
   - Leads are only marked converted or qualified when concrete criteria (budget, confirmed appointments, need) are extracted.
5. **Usage Quotas & Metering**:
   - Every voice AI agent is metered with a monthly call duration budget (`monthly_call_minutes_budget`). When exhausted, calls gracefully fallback to standard human routing.
   - Real carrier duration (`duration_seconds`) is reconciled upon provider status callback.

---

## 2. Database Schema & Data Models

### A. `telephony_providers`
Stores verified cloud telephony account credentials:
- `id` (bigint, PK)
- `tenant_id` (bigint, FK)
- `provider` (enum: `exotel`, `twilio`, `sip`, `custom`)
- `name` (string)
- `account_sid`, `api_key`, `subdomain`, `caller_id`, `dlt_entity_id`
- `auth_token` (encrypted text)
- `supports_recordings`, `supports_realtime_streams`, `is_active` (boolean)
- `settings` (json)

### B. `telephony_phone_numbers`
Tracks virtual numbers assigned to tenants:
- `id` (bigint, PK)
- `tenant_id` (bigint, FK)
- `telephony_provider_id` (bigint, FK)
- `phone_number` (string, E.164 format)
- `display_name`, `country_code`
- `routing_type` (enum: `ai_agent`, `user`, `queue`, `ivr`)
- `default_agent_id`, `default_user_id` (nullable FKs)
- `is_default`, `is_active` (boolean)

### C. `voice_ai_agents`
Defines autonomous voice qualification agents:
- `id` (bigint, PK)
- `tenant_id` (bigint, FK)
- `name`, `description`, `status` (`active`, `paused`, `draft`)
- `language` (`hi`, `hinglish`, `en`, `multi`)
- `voice_engine` (`elevenlabs`, `openai`, `google`, `cartesia`)
- `voice_id` (string)
- `tone` (`consultative`, `professional`, `friendly`, `persuasive`)
- `call_opening` (text with recording disclosure)
- `system_instructions` (longtext)
- `qualification_script` (json)
- `max_call_duration_seconds` (integer, default 300)
- `human_transfer_number` (string, nullable)
- `allowed_crm_actions` (json)
- `monthly_call_minutes_budget` & `used_call_minutes_this_month` (integer)
- `version` (integer)

### D. `voice_calling_campaigns`
Orchestrates outbound qualification batches:
- `id` (bigint, PK)
- `tenant_id` (bigint, FK)
- `voice_agent_id` (bigint, FK)
- `name`, `description`, `status` (`draft`, `running`, `paused`, `completed`, `cancelled`)
- `target_filters` (json: `{ status: 'new', tags: ['q4'] }`)
- `max_retries`, `retry_interval_hours`, `calls_per_minute_limit`
- Counters: `total_contacts_queued`, `total_contacts_called`, `total_contacts_answered`, `total_contacts_qualified`
- `scheduled_start_at`, `completed_at` (timestamps)

### E. `voice_calls`
Permanent ledger of calls:
- `id` (bigint, PK)
- `tenant_id`, `voice_agent_id`, `campaign_id`, `lead_id`, `contact_id`, `assigned_user_id`
- `provider` (`exotel`, `twilio`, `sip`)
- `provider_call_id` (string, indexed)
- `direction` (`inbound`, `outbound`)
- `from_number`, `to_number` (string)
- `start_time`, `end_time`, `duration_seconds` (integer)
- `status` (`queued`, `ringing`, `in-progress`, `completed`, `busy`, `no-answer`, `failed`, `cancelled`)
- `disposition` (`interested`, `not_interested`, `callback_requested`, `wrong_number`, `converted`, `dnd_requested`, `unreached`, `completed`)
- `recording_url` & `recording_duration_seconds`
- `transcript` (longtext) & `ai_summary` (text)
- `extracted_data` (json: `{ budget, need, appointment_confirmed }`)
- `qualification_result` (`qualified`, `unqualified`, `follow_up_needed`)
- `next_follow_up_at` (timestamp)
- `cost_amount` (decimal 10,4) & `cost_currency` (string, default USD)
- `human_transferred` (boolean) & `transfer_target` (string)

### F. `telephony_opt_outs`
National and tenant-level DND suppression:
- `tenant_id`, `phone_number` (E.164)
- `opt_out_type` (`verbal`, `sms`, `portal`, `national_dnd`)
- `reason` (string)
- `opted_out_at` (timestamp)

---

## 3. Telephony Adapter Interfaces & Carrier Protocols

### Exotel Adapter (`ExotelProvider`)
- **Outbound Dispatch**: Sends HTTP POST to `https://{subdomain}/v1/Accounts/{account_sid}/Calls/connect.json` with `From`, `To`, `CallerId`, and status callback webhooks.
- **Inbound Markup**: Returns valid ExoML XML `<Response><Say voice="female" language="hi-IN">...<Record action="/webhook" playBeep="true"/>`.
- **Status Reconciliation**: Parses `CallSid`, `Status`, `RecordingUrl`, `RecordingDuration`, and maps telephony statuses (`completed`, `busy`, `no-answer`).

### Twilio Adapter (`TwilioProvider`)
- **Outbound Dispatch**: Dispatches call initiation via Twilio REST API `/2010-04-01/Accounts/{account_sid}/Calls.json`.
- **Inbound Markup**: Emits valid TwiML `<Response><Say voice="Polly.Aditi">...<Record>`.
- **Status Reconciliation**: Parses Twilio status webhook parameters (`CallDuration`, `CallStatus`, `RecordingUrl`).

---

## 4. API Endpoints

All tenant-scoped API routes require Bearer authentication under `/api/v1/crm/voice/`:

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/crm/voice/calls` | Call history ledger with filter & aggregate KPI stats |
| `GET` | `/crm/voice/calls/{id}` | Detailed call metadata, transcript, and audio recording |
| `POST` | `/crm/voice/calls/dial` | Initiate manual or CRM lead outbound call |
| `POST` | `/crm/voice/calls/{id}/simulate-turn` | Live playground dialogue turn with barge-in support |
| `GET` | `/crm/voice/agents` | List tenant's Voice AI qualification agents |
| `POST` | `/crm/voice/agents` | Create new voice agent with voice engine, budget, instructions |
| `GET` | `/crm/voice/agents/{id}` | Show voice agent configuration |
| `PUT` | `/crm/voice/agents/{id}` | Update voice agent prompts, script, and budget |
| `DELETE` | `/crm/voice/agents/{id}` | Delete voice agent |
| `GET` | `/crm/voice/campaigns` | List outbound calling campaigns |
| `POST` | `/crm/voice/campaigns` | Create calling campaign |
| `POST` | `/crm/voice/campaigns/{id}/launch` | Launch campaign dialer batch with pacing limits |
| `POST` | `/crm/voice/campaigns/{id}/pause` | Pause campaign execution |
| `DELETE` | `/crm/voice/campaigns/{id}` | Delete campaign |
| `GET` | `/crm/voice/telephony/providers` | List telephony providers (auth tokens masked) |
| `POST` | `/crm/voice/telephony/providers` | Configure/update Exotel or Twilio gateway |
| `GET` | `/crm/voice/telephony/numbers` | List virtual phone numbers |
| `POST` | `/crm/voice/telephony/numbers` | Register virtual phone number with routing |

### Public Telephony Webhook Routes
- `POST /api/v1/telephony/webhooks/exotel/connect`: Inbound call markup dispatch
- `POST /api/v1/telephony/webhooks/exotel/status`: Exotel status & duration callback
- `POST /api/v1/telephony/webhooks/exotel/record`: Call audio recording ingestion
- `POST /api/v1/telephony/webhooks/twilio/connect`: Twilio inbound TwiML dispatch
- `POST /api/v1/telephony/webhooks/twilio/status`: Twilio status callback

---

## 5. React Frontend Integration

1. **Navigation & Permissions**:
   - Registered under module ID `'voice'` (`AI Voice & Telephony`) in `Layout.tsx` and `ModulesView.tsx`.
   - Permissions configured for Platform Super Admin, Business Owner, Business Admin, Manager, and Sales Executive in `frontend/src/utils/permissions.ts`.
2. **Component Architecture (`VoiceTelephonyView.tsx`)**:
   - **KPI Summary Ribbon**: Displays Total Calls, Reconciled Minutes, Cumulative Cost ($/₹), and Qualified Leads.
   - **Compliance Notice Banner**: Emphasizes TRAI regulatory hours and real-time DND suppression.
   - **Voice Agents Tab**: Roster of voice agents with language badge, voice engine, opening script preview, and minute usage progress meter.
   - **Call History Tab**: Real-time ledger with direction badges, duration, status, disposition, and audio recording modal.
   - **Live Dialer & Simulator Tab**: Click-to-call direct lead dialer alongside an interactive dialogue simulator supporting simulated customer speech, barge-in / interruption toggles, and real-time qualification previews.
   - **Campaigns Tab**: Campaign manager with queue, answer, and qualification progress stats and batch dispatch controls.
   - **Telephony Gateways Tab**: Secure carrier credentials manager with masked tokens and virtual phone numbers routing roster.

---

## 6. Verification & Test Suite

- **Backend Feature Tests (`backend/tests/Feature/VoiceTelephonyModuleTest.php`)**:
  - 11 comprehensive automated tests covering:
    1. Voice agent configuration and budget creation.
    2. Inbound ExoML generation with mandatory recording disclosures.
    3. Inbound TwiML generation.
    4. Outbound call dispatch via provider adapter.
    5. DND suppression registry enforcement.
    6. Speech transcript and barge-in / interruption processing.
    7. Post-call CRM automation: lead status upgrade, estimated budget saving, activity timeline logging, and callback task creation.
    8. Verbal DND opt-out automatic suppression.
    9. Campaign batch pacing execution.
    10. Call duration reconciliation and usage metering.
    11. Strict multi-tenant isolation.
  - Test Result: **11 passed (57 assertions)**.
- **Frontend Integration Test Suite (`frontend/src/tests/apiIntegration.test.ts`)**:
  - Tests 16 and 17 validating `/crm/voice/agents` and `/crm/voice/calls`.
  - Result: **18 passed, 0 failed**.
- **Frontend Production Build (`vite build`)**:
  - Successfully transformed 2590 modules with **0 errors**.
- **Full Backend PHPUnit Suite**:
  - **83 tests passed (453 assertions), 0 failures**.
