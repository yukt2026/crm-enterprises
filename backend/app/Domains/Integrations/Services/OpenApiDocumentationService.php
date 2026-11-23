<?php

namespace App\Domains\Integrations\Services;

class OpenApiDocumentationService
{
    /**
     * Generate versioned OpenAPI 3.0.3 specification documentation
     */
    public function generateSpecification(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'CRM Enterprises RESTful API & Webhook Platform',
                'description' => 'Complete enterprise RESTful API suite for CRM leads, contacts, deals, tasks, omnichannel conversations, voice telephony, billing invoices, payments, and outbound webhooks.',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'API Support Team',
                    'email' => 'api-support@crm-enterprises.test',
                ],
            ],
            'servers' => [
                [
                    'url' => config('app.url', 'http://127.0.0.1:8000') . '/api/v1',
                    'description' => 'Primary Tenant API Gateway',
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum Token',
                        'description' => 'Standard user session token acquired from `/api/v1/auth/login`.',
                    ],
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-KEY',
                        'description' => 'Tenant Service API Key created in Settings -> Integrations -> API Keys.',
                    ],
                ],
                'schemas' => [
                    'ApiResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => ['type' => 'boolean', 'example' => true],
                            'message' => ['type' => 'string', 'example' => 'Operation completed successfully.'],
                            'data' => ['type' => 'object'],
                        ],
                    ],
                    'Lead' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'example' => 101],
                            'name' => ['type' => 'string', 'example' => 'Sophia Taylor'],
                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'sophia@enterprise.test'],
                            'phone' => ['type' => 'string', 'example' => '+14155552671'],
                            'source' => ['type' => 'string', 'example' => 'Meta Ads'],
                            'status' => ['type' => 'string', 'enum' => ['new', 'contacted', 'qualified', 'converted', 'lost']],
                            'score' => ['type' => 'integer', 'example' => 85],
                        ],
                    ],
                    'Deal' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'example' => 501],
                            'name' => ['type' => 'string', 'example' => 'Annual SaaS Enterprise License'],
                            'value' => ['type' => 'number', 'format' => 'float', 'example' => 75000.00],
                            'currency' => ['type' => 'string', 'example' => 'USD'],
                            'stage' => ['type' => 'string', 'example' => 'proposal'],
                            'probability' => ['type' => 'integer', 'example' => 60],
                        ],
                    ],
                    'Invoice' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'example' => 801],
                            'invoice_number' => ['type' => 'string', 'example' => 'INV-2026-0042'],
                            'total_amount' => ['type' => 'number', 'example' => 12500.00],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'sent', 'paid', 'overdue', 'void']],
                        ],
                    ],
                    'OutboundWebhook' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'example' => 12],
                            'name' => ['type' => 'string', 'example' => 'Production Webhook Subscriber'],
                            'url' => ['type' => 'string', 'format' => 'uri', 'example' => 'https://api.partner.test/webhooks'],
                            'events' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'example' => ['lead.created', 'deal.won', 'invoice.paid'],
                            ],
                            'is_active' => ['type' => 'boolean', 'example' => true],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/auth/login' => [
                    'post' => [
                        'summary' => 'Authenticate user and obtain API access token',
                        'tags' => ['Authentication'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email', 'password'],
                                        'properties' => [
                                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'admin@company.test'],
                                            'password' => ['type' => 'string', 'format' => 'password', 'example' => 'secret123'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Authentication successful, bearer token issued.'],
                            '401' => ['description' => 'Invalid credentials.'],
                        ],
                    ],
                ],
                '/crm/leads' => [
                    'get' => [
                        'summary' => 'List leads with pagination and filters',
                        'tags' => ['Leads'],
                        'security' => [['BearerAuth' => []], ['ApiKeyAuth' => []]],
                        'parameters' => [
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20]],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Leads list retrieved successfully.'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Create a new CRM lead',
                        'tags' => ['Leads'],
                        'security' => [['BearerAuth' => []], ['ApiKeyAuth' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name'],
                                        'properties' => [
                                            'name' => ['type' => 'string', 'example' => 'Alexander Vance'],
                                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'alex@acme.test'],
                                            'phone' => ['type' => 'string', 'example' => '+14155551234'],
                                            'source' => ['type' => 'string', 'example' => 'API Ingestion'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Lead created successfully.'],
                        ],
                    ],
                ],
                '/crm/deals' => [
                    'get' => [
                        'summary' => 'List sales deals',
                        'tags' => ['Deals'],
                        'security' => [['BearerAuth' => []], ['ApiKeyAuth' => []]],
                        'responses' => ['200' => ['description' => 'Deals list retrieved.']],
                    ],
                ],
                '/crm/invoices' => [
                    'get' => [
                        'summary' => 'List invoices & payment receipts',
                        'tags' => ['Invoices'],
                        'security' => [['BearerAuth' => []], ['ApiKeyAuth' => []]],
                        'responses' => ['200' => ['description' => 'Invoices list retrieved.']],
                    ],
                ],
                '/crm/webhooks' => [
                    'get' => [
                        'summary' => 'List outbound webhook subscriptions',
                        'tags' => ['Webhooks'],
                        'security' => [['BearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Webhooks retrieved.']],
                    ],
                    'post' => [
                        'summary' => 'Register a new outbound webhook endpoint',
                        'tags' => ['Webhooks'],
                        'security' => [['BearerAuth' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'url', 'events'],
                                        'properties' => [
                                            'name' => ['type' => 'string', 'example' => 'ERP Real-time Sync'],
                                            'url' => ['type' => 'string', 'format' => 'uri', 'example' => 'https://erp.company.test/webhook/crm'],
                                            'events' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['lead.created', 'deal.won']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['201' => ['description' => 'Webhook registered with HMAC signing secret.']],
                    ],
                ],
            ],
        ];
    }
}
