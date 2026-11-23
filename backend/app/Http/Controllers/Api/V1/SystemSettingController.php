<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Tenancy\Models\SystemSetting;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $settings = SystemSetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'company_name' => 'Acme Enterprise Inc.',
                'company_email' => 'ops@acme.com',
                'company_phone' => '+1 (555) 019-2834',
                'tax_rate' => 15.00,
                'currency' => 'USD',
                'theme' => 'dark',
            ]
        );

        return response()->json([
            'companyName' => $settings->company_name,
            'companyEmail' => $settings->company_email,
            'companyPhone' => $settings->company_phone,
            'taxRate' => (float) $settings->tax_rate,
            'currency' => $settings->currency,
            'theme' => $settings->theme,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'companyName' => 'nullable|string|max:255',
            'companyEmail' => 'nullable|email|max:255',
            'companyPhone' => 'nullable|string|max:50',
            'taxRate' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'theme' => 'nullable|in:light,dark',
        ]);

        $tenantId = $request->user()?->tenant_id;
        $settings = SystemSetting::firstOrCreate(['tenant_id' => $tenantId]);

        $updateData = [];
        if (isset($validated['companyName'])) $updateData['company_name'] = $validated['companyName'];
        if (isset($validated['companyEmail'])) $updateData['company_email'] = $validated['companyEmail'];
        if (isset($validated['companyPhone'])) $updateData['company_phone'] = $validated['companyPhone'];
        if (isset($validated['taxRate'])) $updateData['tax_rate'] = $validated['taxRate'];
        if (isset($validated['currency'])) $updateData['currency'] = $validated['currency'];
        if (isset($validated['theme'])) $updateData['theme'] = $validated['theme'];

        $settings->update($updateData);

        return response()->json([
            'companyName' => $settings->company_name,
            'companyEmail' => $settings->company_email,
            'companyPhone' => $settings->company_phone,
            'taxRate' => (float) $settings->tax_rate,
            'currency' => $settings->currency,
            'theme' => $settings->theme,
        ]);
    }
}
