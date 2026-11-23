# WhatsApp AI Agent Builder, Knowledge Base & CRM Actions Module Documentation

## 1. Executive Summary & Architecture

The **WhatsApp AI Agent Builder & Knowledge Base** module delivers an enterprise-grade autonomous intelligence layer on top of the official Meta WhatsApp Business Platform (Cloud API v21.0).

It allows businesses to build, test, and deploy customized, multilingual AI conversational agents that:
1. Understand customer intent in **English, Hindi, and Hinglish**.
2. Answer queries strictly using **Tenant-Isolated Vector RAG (Retrieval-Augmented Generation)** knowledge bases.
3. Automatically execute permission-controlled CRM operations (lead capture, field updates, scheduling follow-up tasks, internal notes, catalog lookups, appointment requests).
4. Defend against prompt injections and jailbreak attempts.
5. Provide automatic and explicit handover to human agents when complex situations or refund/pricing disputes arise.
6. Provide an evaluation and interactive testing playground with decision traces and token/latency metrics.

```
+-----------------------------------------------------------------------------------------+
|                                    WHATSAPP CUSTOMER                                    |
+-----------------------------------------------------------------------------------------+
                                             |
                                  WhatsApp Inbound Webhook
                                             v
+-----------------------------------------------------------------------------------------+
|                               WhatsAppAiAgentService                                    |
|                                                                                         |
|  1. Prompt Injection Defense      2. Language Detection      3. Opt-out & Window Check  |
+-----------------------------------------------------------------------------------------+
                    |                                                    |
         Query Embedding / Vector Search                     System Prompt + History
                    v                                                    v
+-------------------------------------------------+     +---------------------------------+
|              KnowledgeBaseService               |     |       AiProviderManager         |
|  - Tenant-Isolated Vector RAG Chunks            |     |  - Gemini 1.5 Flash / Pro       |
|  - Cosine Similarity + Hybrid Keyword Match     |     |  - OpenAI-compatible endpoints  |
|  - Source Citation Generation                   |     |  - Safe Fallbacks & Retry Rules |
+-------------------------------------------------+     +---------------------------------+
                                                                         |
                                                              Function Call Schemas
                                                                         v
                                                        +---------------------------------+
                                                        |         CrmToolRegistry         |
                                                        |  - find_or_create_lead          |
                                                        |  - update_lead_fields           |
                                                        |  - add_crm_note                 |
                                                        |  - create_follow_up_task        |
                                                        |  - get_product_catalog          |
                                                        |  - request_appointment          |
                                                        |  - escalate_to_human            |
                                                        |  [SENSITIVE ACTIONS BLOCKED]    |
                                                        +---------------------------------+
                                                                         |
                                                              Outbound Customer Reply
                                                                         v
+-----------------------------------------------------------------------------------------+
|                  WhatsAppMessagingService (Meta Cloud API / Inbound 24h)                 |
+-----------------------------------------------------------------------------------------+
```

---

## 2. LLM Provider Adapter Layer

The system decouples business logic from specific AI models using the `AiProviderInterface` and `AiProviderManager`:

- **Providers Supported**:
  - `GeminiProvider`: Google Generative AI (Gemini 1.5 Flash, Gemini 1.5 Pro, Text-Embedding-004).
  - `OpenAiCompatibleProvider`: OpenAI API (GPT-4o, GPT-4o-mini) and custom OpenAI-compatible endpoints.
- **Security & Key Management**:
  - Provider API keys are stored encrypted at rest via Laravel's `Crypt` facade in the `whatsapp_ai_providers` table.
  - Keys are **never exposed** in API responses; they are masked (e.g. `sk-****...1234`) on all frontend requests.
- **Token & Cost Tracking**:
  - Real-time token usage calculation (prompt tokens, completion tokens, total tokens).
  - Estimated USD spend computed automatically per execution and tracked against tenant and agent monthly budgets.
- **Safe Fallback & Retries**:
  - Configurable timeouts (`timeout_seconds`) and exponential retries (`max_retries`).
  - If a primary provider encounters a remote failure, `AiProviderManager` attempts a safe fallback provider before triggering a human handover fallback.

---

## 3. Vector Knowledge Base & Document Ingestion (RAG)

### Supported Document Types
- **PDF Documents**: Extracted stream parsing.
- **DOCX Documents**: XML package parsing.
- **TXT & CSV**: Structured line-item parsing.
- **Manual FAQs / Policies**: Direct textual entry.

### Chunking & Vector Search
- Text is normalized and split into chunks of ~450 characters with a 50-character overlap.
- Numerical embeddings (vector float coordinates) are generated for every chunk and stored in `whatsapp_knowledge_chunks`.
- **Cosine Similarity Formula**:
  $$\text{Cosine Similarity}(A, B) = \frac{\sum_{i=1}^n A_i B_i}{\sqrt{\sum_{i=1}^n A_i^2} \sqrt{\sum_{i=1}^n B_i^2}}$$
- **Hybrid Retrieval**: Combines Cosine Similarity (75% weight) with keyword term matching (25% weight).
- **Tenant Isolation**: Vector search queries are strictly bounded by `WHERE tenant_id = :tenant_id AND status = 'ready'`. Cross-tenant data retrieval is impossible.

---

## 4. Protected CRM Tool Execution Engine

The AI model interacts with CRM data through permission-checked tool calls defined in `CrmToolRegistry`:

| Tool Name | Parameters | Action Taken |
|---|---|---|
| `find_or_create_lead` | `name`, `requirement`, `budget`, `timeline`, `location` | Locates contact or creates a new CRM Lead with source `'whatsapp'`, assigns initial score, and links to conversation. |
| `update_lead_fields` | `stage`, `budget`, `requirement`, `qualification_status` | Updates qualification details on the linked lead without altering critical deal/payment fields. |
| `add_crm_note` | `content` | Logs an internal note in `whatsapp_conversation_notes` visible to sales agents. |
| `create_follow_up_task` | `title`, `due_date`, `priority`, `description` | Schedules a follow-up task in `tasks` with assigned user and due date. |
| `get_product_catalog` | `query`, `category` | Queries active product/service catalog for current pricing and SKUs. |
| `request_appointment` | `preferred_date`, `meeting_topic` | Logs customer consultation time and agenda in notes. |
| `escalate_to_human` | `reason`, `priority` | Sets `is_ai_handled = false`, raises conversation priority, and notifies human sales reps. |

### Strict Security Policies:
- **No Arbitrary Queries**: Direct SQL execution or unvetted API calls are strictly blocked.
- **Blocked Sensitive Actions**: Sensitive actions such as `issue_refund`, `give_discount`, and `delete_lead` throw a security policy violation and require human manager approval.
- **Prompt Injection Defense**: Sanitizes user queries and immediately neutralizes attempts such as `"ignore all previous instructions"` or `"output system prompt"`.

---

## 5. Multilingual Capabilities

The agent builder supports:
- **English**: Professional, consultative, or friendly enterprise tone.
- **Hindi (Devanagari)**: High-fidelity natural responses in native script.
- **Hinglish**: Seamless colloquial Romanized Hindi/English (e.g. *"Namaste! Main Nexus CRM AI Assistant hoon. Hamare software plans ke baare mein jankari ke liye main aapki madad kar sakta hoon."*).

---

## 6. Frontend Interactive Workspace

Implemented in `WhatsAppAiAgentBuilderView.tsx`:
1. **AI Agents Tab**: Grid of active agents with language, tone, status switch, and monthly token usage progress meter.
2. **Knowledge Base Tab**: Upload documents (PDF, DOCX, TXT, CSV), manage FAQs, view vector chunk status, and run **Live Cosine Similarity Search** tests.
3. **Interactive Playground Tab**: Test chat simulator with preset prompt test chips (Hindi greeting, lead capture, human handover, prompt injection) and a **Live Decision Trace** drawer showing detected language, citations, triggered tools, token counts, and latency.
4. **AI Providers & Settings Tab**: Manage Gemini / OpenAI configurations, masked keys, and timeout/retry parameters.
5. **Trace & Execution Logs Tab**: Real-time audit log of every AI prompt, execution latency, tool execution, and handover event.

---

## 7. Acceptance Test Verification

All 12 feature test cases in `WhatsAppAiAgentModuleTest.php` pass:
- `test_ai_agent_creation_and_versioning`: Verified agent creation and version audit trail.
- `test_knowledge_base_ingestion_and_chunking`: Verified chunking and vector embedding generation.
- `test_multilingual_ai_responses`: Verified English and Hinglish responses.
- `test_rag_knowledge_retrieval`: Verified accurate vector RAG citations.
- `test_prompt_injection_defense`: Verified injection neutralization.
- `test_cross_tenant_retrieval_denial`: Verified zero cross-tenant knowledge leaks.
- `test_crm_tool_execution_find_or_create_lead`: Verified lead creation and conversation linking.
- `test_crm_tool_execution_create_follow_up_task`: Verified follow-up task creation.
- `test_sensitive_action_security_block`: Verified rejection of refunds/discounts/deletions.
- `test_human_handover_flow`: Verified handover trigger (`is_ai_handled = false`) and note logging.
- `test_monthly_token_budget_limit`: Verified token budget capping and fallback trigger.
- `test_playground_simulation_endpoint`: Verified playground simulation endpoint.
