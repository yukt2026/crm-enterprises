# Customer Support, Help Desk, Ticket Management and Customer Success Module

## Executive Overview
The **Customer Support, Help Desk and Customer Success Module** upgrades the CRM's native ticketing capabilities into an enterprise-grade customer experience and retention engine. Designed for modern Indian and global B2B/B2C enterprises, it unifies multi-channel support intake (WhatsApp, Email, Web Chat, Phone, and Customer Portal) with strict SLA tracking, auditable ticket transitions, post-resolution feedback (CSAT & NPS), predictive Customer Health Scoring, Warranty/AMC contract lifecycle management, and an AI-augmented Knowledge Base.

---

## 1. Architectural Architecture & Core Components

```
                                  +-------------------------------------------------+
                                  |            Omnichannel Ingestion Hub            |
                                  |  (WhatsApp, Email, Live Chat, Webhook, Portal)  |
                                  +-------------------------------------------------+
                                                           |
                                                           v
+-------------------------------------------------------------------------------------------------------------------+
|                                            Enterprise Ticket Engine                                               |
|  - Auto-generation of Sequential Ticket Numbers (e.g., TICK-2026-0001)                                            |
|  - Category & Subcategory Taxonomy Classification                                                                 |
|  - SLA Target Evaluation (Response & Resolution deadlines, Business Hours, Urgency)                               |
|  - Relational Linkages: Customers, Contacts, Companies, Deals, Invoices, Projects, and Branches                    |
+-------------------------------------------------------------------------------------------------------------------+
               |                                           |                                     |
               v                                           v                                     v
+-----------------------------+             +-----------------------------+       +-----------------------------+
|    Audited Transitions      |             |    Team Collaboration       |       |  Customer Feedback Engine   |
| - Complete State Machine    |             | - Private Internal Notes    |       | - 1-5 Star CSAT Evaluation  |
| - Pre/Post Status Logging   |             | - Customer-Facing Replies   |       | - 0-10 Net Promoter Score   |
| - Auto-Reopening on Inbound |             | - Ownership Reassignment    |       | - Direct Feedback Capture   |
|   Customer Activity         |             | - Collision Avoidance       |       | - Immediate Health Scoring  |
+-----------------------------+             +-----------------------------+       +-----------------------------+
               |                                                                                 |
               +-------------------------------------------+-------------------------------------+
                                                           |
                                                           v
+-------------------------------------------------------------------------------------------------------------------+
|                                     Explainable Customer Health Scoring Engine                                    |
|  Score = 0.35 * TicketSupport + 0.30 * CSAT_NPS + 0.20 * FinancialHealth + 0.15 * OnboardingEngagement            |
|  - Status: Healthy (>=80), Neutral (60-79), At-Risk (40-59), Critical (<40)                                       |
|  - Explainable Breakdown: Sub-scores, weights, positive highlights, risk flags, and natural language summary       |
+-------------------------------------------------------------------------------------------------------------------+
               |                                                                                 |
               v                                                                                 v
+-----------------------------+                                                   +-----------------------------+
|    Warranty & AMC Engine    |                                                   |   Knowledge Base & AI Q&A   |
| - Warranty / AMC Contracts  |                                                   | - Public / Internal Articles|
| - Asset & Serial Tracking   |                                                   | - Tokenized Keyword Search  |
| - Preventive Maintenance    |                                                   | - Helpfulness Voting        |
| - Renewal Price & Overdue   |                                                   | - Grounded AI Suggested Q&A |
+-----------------------------+                                                   +-----------------------------+
```

---

## 2. Multi-Channel Ticket Intake & Lifecycle

### Supported Intake Channels
- **WhatsApp Business API**: Direct customer messages converted to tickets or appended to active threads.
- **Email Ingestion**: Incoming support emails converted into structured tickets with header attribution.
- **Web Chat**: Live chat conversations escalated into trackable support tickets.
- **Phone / Telephony**: Call logs and IVR transfers linked directly to customer ticket records.
- **Customer Portal**: Self-service ticket submission with attachment support.
- **Manual / Internal**: Employee-created tickets for customer escalations or internal team handoffs.

### Comprehensive Status State Machine
- `new`: Initial intake; awaiting triage or automated routing.
- `open`: Acknowledged by agent; SLA response timer active.
- `in_progress`: Actively being investigated or worked on by technical/engineering teams.
- `waiting_customer`: Pending information or verification from customer; pauses resolution countdown.
- `waiting_internal`: Awaiting cross-departmental dependency (finance, product, devops).
- `escalated`: Automatically or manually escalated due to severity or impending SLA breach.
- `resolved`: Solution provided; triggers automated CSAT/NPS survey request.
- `closed`: Verified and permanently archived.
- `reopened`: Automatically activated if the customer replies to a resolved/closed ticket.

### State Transition Audit Trail (`support_ticket_transitions`)
Every transition captures:
- `ticket_id`: Target ticket.
- `from_status` & `to_status`: Exact state change.
- `changed_by_user_id`: Authenticated user, customer, or system process.
- `reason`: Explanation or resolution notes.
- `metadata`: Contextual timestamps, SLA milestone snapshot, and reopen counter increments.

---

## 3. Team Management & Communication Security

### Separation of Concerns
1. **Public Customer Messages (`is_internal: false`)**:
   - Visible to customers in portal, WhatsApp, and email threads.
   - Triggers customer notifications.
2. **Private Internal Notes (`is_internal: true`)**:
   - Strictly restricted to authorized internal agents.
   - Never exposed over public APIs, webhooks, or customer portal endpoints.
   - Allows confidential cross-department discussion and diagnostic logs.

### Auto-Reopen Automation
When a customer sends an inbound message on a ticket in `resolved` or `closed` status:
- Status immediately transitions to `reopened`.
- `reopen_count` is incremented.
- An auditable transition log entry is written.
- The assigned agent and team are notified to resume investigation.

---

## 4. SLA Policies & Real-Time Tracking

- **Configurable Policies**: Define policies by priority (`urgent`, `high`, `medium`, `low`) and customer tier.
- **Response Target**: Minutes allowed before an agent sends the first public acknowledgment.
- **Resolution Target**: Minutes allowed before ticket resolution.
- **Breach Detection**: Background evaluation flagging tickets breaching SLA thresholds with status `breached`.
- **Business Hours Support**: SLA timers calculate against working shifts and business calendar configurations.

---

## 5. Customer Feedback Engine (CSAT & NPS)

- **CSAT (Customer Satisfaction)**: 1 to 5 stars evaluating resolution quality.
- **NPS (Net Promoter Score)**: 0 to 10 scale classifying customers into Promoters (9-10), Passives (7-8), and Detractors (0-6).
- **Automated Feedback Trigger**: Sent automatically upon ticket resolution.
- **Impact on Customer Success**: Direct inputs into the explainable Customer Health Score calculation.

---

## 6. Explainable Customer Health Scoring Engine

The Customer Success Service computes an objective, explainable health score ($0 - 100$) rather than an opaque number.

### Formula & Factor Breakdown
$$\text{Health Score} = 0.35 \times S_{\text{support}} + 0.30 \times S_{\text{satisfaction}} + 0.20 \times S_{\text{finance}} + 0.15 \times S_{\text{engagement}}$$

1. **Ticket & Support Weight (35%)**:
   - Based on volume of active tickets, unresolved escalations, and SLA breach frequency.
   - High breaches or repeat tickets reduce this score component.
2. **Customer Satisfaction Weight (30%)**:
   - Calculated from recent CSAT star ratings and NPS promoter/detractor ratios.
   - Rating averages $\ge 4.5$ score 100%; negative feedback systematically drops score.
3. **Financial Health Weight (20%)**:
   - Evaluates overdue invoices, accounts receivable ageing, and payment consistency.
   - Zero overdue balances yield maximum score.
4. **Onboarding & Engagement Weight (15%)**:
   - Tracks onboarding milestone progress (`not_started`, `in_progress`, `completed`, `stalled`) and cadence of success check-ins.

### Explainable Factor Payload
Every health score response includes:
- `health_score`: Numeric score from $0.00$ to $100.00$.
- `health_status`: `healthy`, `neutral`, `at_risk`, or `critical`.
- `churn_risk_level`: `low`, `medium`, or `high`.
- `factors`: Detailed object with individual component scores, weight distributions, list of positive highlights, active risk flags, and an auto-generated narrative summary.

---

## 7. Warranty & Annual Maintenance Contracts (AMC)

- **Contract Types**: Hardware Warranty, Annual Maintenance Contract (AMC), and Custom Service Agreements.
- **Asset Association**: Product name, serial numbers, customer account, and assigned service engineers.
- **Preventive Maintenance**: Interval scheduling (e.g. 90-day maintenance cycles) with automated `next_service_date` calculations.
- **Execution Tracking**: POST `/crm/customer-success/contracts/{id}/maintenance` updates `last_service_date`, sets the next cycle date, and records service engineer inspection notes.
- **Renewal Alerting**: Tracks contract expiration and renewal pricing for upsell and recurring service revenue.

---

## 8. Knowledge Base & Grounded AI Suggestions

- **Taxonomy**: Hierarchical categories (`knowledge_categories`) with active status and sorting.
- **Article Management**: Markdown-supported articles (`knowledge_articles`) with visibility controls (`public`, `internal`, `customer_portal`), tags, excerpts, and revision versions.
- **Helpfulness Feedback**: Incremental counters for helpful and unhelpful votes to rank article relevance.
- **Tokenized Search & Grounded AI Q&A**:
  - `KnowledgeBaseService::getArticles` executes whitespace-tokenized search across title, content, and tags.
  - `suggestAiAnswer`: Analyzes customer questions against approved published articles and returns grounded answers with confidence scores and source citations.

---

## 9. Verification & Quality Assurance

### Automated Testing Matrix
1. **PHPUnit Feature Tests**:
   - Test File: `backend/tests/Feature/EnterpriseSupportAndCustomerSuccessTest.php`
   - Test Results: **9 / 9 Passing (100%), 54 assertions**.
   - Coverage: Ticket creation with SLA, status transitions, auto-reopen on customer reply, internal note segregation, bulk updates, CSAT/NPS submission, health score computation, warranty maintenance, and KB search/AI grounding.
2. **Frontend-to-Backend Integration Suite**:
   - Test File: `frontend/src/tests/apiIntegration.test.ts` (Step 57)
   - Results: **57 / 57 Passing (100%, 0 failures)**.
3. **Production Build**:
   - Command: `npm run build`
   - Result: Clean Vite bundle compilation in 9.87s with zero errors.
