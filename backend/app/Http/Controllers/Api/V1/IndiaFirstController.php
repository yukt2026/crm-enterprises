<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Analytics\Services\IndiaFirstService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IndiaFirstController extends Controller
{
    /**
     * Get India-First CRM Configuration & Capabilities
     */
    public function getSettings(Request $request): JsonResponse
    {
        $now = Carbon::now('Asia/Kolkata');

        return response()->json([
            'success' => true,
            'data' => [
                'currency' => [
                    'code' => 'INR',
                    'symbol' => '₹',
                    'numbering_system' => 'Lakhs and Crores (e.g. 12,34,567.89)',
                    'sample_formatted' => IndiaFirstService::formatInr(1234567.89),
                    'sample_short' => IndiaFirstService::formatShortInr(1234567.89),
                ],
                'timezone' => [
                    'default' => 'Asia/Kolkata',
                    'offset' => '+05:30',
                    'current_time_ist' => $now->format('d/m/Y h:i:s A'),
                    'is_business_hours' => IndiaFirstService::isWithinIndianBusinessHours(),
                    'business_hours_schedule' => '09:00 AM - 06:00 PM IST (Mon - Sat)',
                ],
                'phone_validation' => [
                    'country_code' => '+91',
                    'format' => 'E.164 10-digit mobile number prefixed with +91',
                ],
                'holidays' => IndiaFirstService::getIndianHolidays(),
                'languages' => [
                    'available' => ['en', 'hi', 'hinglish', 'ta', 'te', 'mr', 'gu', 'kn', 'bn'],
                    'dictionaries' => IndiaFirstService::getLocalizationDictionaries(),
                ],
                'upi_qr_standard' => 'NPCI BharatQR / UPI Deep-link standard (upi://pay)',
            ],
        ]);
    }

    /**
     * Format INR Amount in Lakhs & Crores
     */
    public function formatAmount(Request $request): JsonResponse
    {
        $amount = (float) ($request->input('amount') ?? $request->query('amount', 0));

        return response()->json([
            'success' => true,
            'data' => [
                'raw_amount' => $amount,
                'formatted_inr' => IndiaFirstService::formatInr($amount),
                'formatted_short' => IndiaFirstService::formatShortInr($amount),
            ],
        ]);
    }

    /**
     * Get Localization Dictionary for Language
     */
    public function getTranslations(Request $request, string $lang = 'en'): JsonResponse
    {
        $dictionaries = IndiaFirstService::getLocalizationDictionaries();
        $translations = $dictionaries[$lang] ?? $dictionaries['en'];

        return response()->json([
            'success' => true,
            'data' => [
                'language' => $lang,
                'translations' => $translations,
            ],
        ]);
    }
}
