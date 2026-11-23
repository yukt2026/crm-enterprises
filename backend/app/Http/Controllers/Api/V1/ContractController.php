<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Sales\Models\Contract;
use App\Domains\Sales\Models\ContractTemplate;
use App\Domains\Sales\Services\ContractService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function __construct(
        protected ContractService $contractService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $query = Contract::where('tenant_id', $tenantId)
            ->with(['deal:id,name', 'customer:id,name', 'company:id,name', 'contact:id,name', 'quotation:id,quote_number']);

        if ($request->filled('contract_type')) {
            $query->where('contract_type', $request->contract_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('signature_status')) {
            $query->where('signature_status', $request->signature_status);
        }
        if ($request->filled('deal_id')) {
            $query->where('deal_id', $request->deal_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('contract_number', 'like', "%{$search}%");
            });
        }

        $contracts = $query->orderByDesc('updated_at')->get();

        return response()->json([
            'success' => true,
            'data' => $contracts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'contract_type' => 'required|in:proposal,contract,nda,sla',
            'deal_id' => 'nullable|exists:deals,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'customer_id' => 'nullable|exists:customers,id',
            'company_id' => 'nullable|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'contract_value' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'auto_renew' => 'nullable|boolean',
            'renewal_notice_days' => 'nullable|integer',
            'terms_conditions' => 'nullable|string',
            'e_signature_provider' => 'nullable|in:aadhaar_esign,docusign,internal_otp,clickwrap',
            'signer_name' => 'nullable|string|max:191',
            'signer_email' => 'nullable|email|max:191',
        ]);

        $contract = $this->contractService->createContract($tenantId, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Contract created successfully.',
            'data' => $contract->load(['deal', 'customer']),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $contract = Contract::where('tenant_id', $tenantId)
            ->with(['deal', 'customer', 'company', 'contact', 'quotation', 'createdByUser:id,name'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $contract,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $contract = Contract::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'contract_type' => 'sometimes|in:proposal,contract,nda,sla',
            'contract_value' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'auto_renew' => 'nullable|boolean',
            'renewal_notice_days' => 'nullable|integer',
            'terms_conditions' => 'nullable|string',
            'status' => 'nullable|string',
            'e_signature_provider' => 'nullable|in:aadhaar_esign,docusign,internal_otp,clickwrap',
        ]);

        $contract->update($validated);
        $contract->increment('document_version');

        return response()->json([
            'success' => true,
            'message' => 'Contract updated successfully.',
            'data' => $contract->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $contract = Contract::where('tenant_id', $tenantId)->findOrFail($id);
        $contract->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contract deleted.',
        ]);
    }

    public function sendForSignature(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $contract = Contract::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'signer_name' => 'required|string|max:191',
            'signer_email' => 'required|email|max:191',
            'provider' => 'nullable|in:aadhaar_esign,docusign,internal_otp,clickwrap',
        ]);

        $updated = $this->contractService->sendForSignature(
            $contract,
            $validated['signer_name'],
            $validated['signer_email'],
            $validated['provider'] ?? 'clickwrap'
        );

        return response()->json([
            'success' => true,
            'message' => "Contract sent for e-signature to {$validated['signer_email']}.",
            'data' => $updated,
        ]);
    }

    public function sign(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $contract = Contract::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'signer_name' => 'nullable|string',
            'signer_email' => 'nullable|email',
            'otp_code' => 'nullable|string',
            'signature_data' => 'nullable|string',
            'ip_address' => 'nullable|string',
        ]);

        $signerName = $validated['signer_name'] ?? $contract->signer_name ?? 'Authorized Signer';
        $signerEmail = $validated['signer_email'] ?? $contract->signer_email ?? 'signer@enterprise.com';
        $ip = $validated['ip_address'] ?? $request->ip() ?? '127.0.0.1';

        $updated = $this->contractService->recordSignature(
            $contract,
            $signerName,
            $signerEmail,
            $ip,
            $validated['otp_code'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Digital signature recorded successfully with cryptographic audit hash.',
            'data' => $updated,
        ]);
    }

    public function decline(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $contract = Contract::where('tenant_id', $tenantId)->findOrFail($id);

        $reason = $request->input('reason', 'Customer declined agreement');
        $updated = $this->contractService->declineSignature($contract, $reason);

        return response()->json([
            'success' => true,
            'message' => 'Contract signature declined.',
            'data' => $updated,
        ]);
    }

    public function templates(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $templates = ContractTemplate::where('tenant_id', $tenantId)->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|in:proposal,contract,nda,sla',
            'content' => 'required|string',
            'variables' => 'nullable|array',
        ]);

        $template = ContractTemplate::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'created_by_user_id' => $request->user()->id,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Contract template created.',
            'data' => $template,
        ], 201);
    }
}
