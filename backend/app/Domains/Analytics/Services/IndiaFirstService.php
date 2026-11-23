<?php

namespace App\Domains\Analytics\Services;

use Carbon\Carbon;

class IndiaFirstService
{
    /**
     * Format a number according to the Indian Numbering System (Lakhs & Crores).
     * e.g., 1234567.89 -> 12,34,567.89
     */
    public static function formatIndianNumber(float|int|null $amount, int $decimals = 2): string
    {
        if ($amount === null) {
            return '0.00';
        }

        $isNegative = $amount < 0;
        $amount = abs($amount);

        $parts = explode('.', number_format($amount, $decimals, '.', ''));
        $intPart = $parts[0];
        $decPart = isset($parts[1]) ? '.' . $parts[1] : '';

        if (strlen($intPart) <= 3) {
            $formattedInt = $intPart;
        } else {
            $lastThree = substr($intPart, -3);
            $rest = substr($intPart, 0, -3);
            $restFormatted = '';
            while (strlen($rest) > 2) {
                $restFormatted = ',' . substr($rest, -2) . $restFormatted;
                $rest = substr($rest, 0, -2);
            }
            $formattedInt = $rest . $restFormatted . ',' . $lastThree;
        }

        return ($isNegative ? '-' : '') . $formattedInt . $decPart;
    }

    /**
     * Format currency in Indian Rupees (INR) with symbol.
     * e.g. 1550000 -> ₹15,50,000.00
     */
    public static function formatInr(float|int|null $amount, int $decimals = 2): string
    {
        return '₹' . self::formatIndianNumber($amount, $decimals);
    }

    /**
     * Format short Indian notation (Lakhs / Crores).
     * e.g. 150000 -> ₹1.50 L
     * e.g. 25000000 -> ₹2.50 Cr
     */
    public static function formatShortInr(float|int|null $amount): string
    {
        if ($amount === null || $amount == 0) {
            return '₹0';
        }

        $abs = abs($amount);
        $prefix = $amount < 0 ? '-₹' : '₹';

        if ($abs >= 10000000) { // 1 Crore
            return $prefix . number_format($abs / 10000000, 2) . ' Cr';
        }

        if ($abs >= 100000) { // 1 Lakh
            return $prefix . number_format($abs / 100000, 2) . ' L';
        }

        if ($abs >= 1000) { // 1 Thousand
            return $prefix . number_format($abs / 1000, 1) . ' K';
        }

        return $prefix . number_format($abs, 2);
    }

    /**
     * Convert timestamp to Asia/Kolkata timezone and Indian presentation format (DD/MM/YYYY hh:mm A).
     */
    public static function formatIstDateTime($timestamp, string $format = 'd/m/Y h:i A'): ?string
    {
        if (!$timestamp) {
            return null;
        }

        return Carbon::parse($timestamp)->setTimezone('Asia/Kolkata')->format($format);
    }

    /**
     * Validate and normalize Indian Phone Numbers to +91 E.164 standard.
     */
    public static function normalizeIndianPhoneNumber(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10 && in_array($digits[0], ['6', '7', '8', '9'])) {
            return '+91' . $digits;
        }

        return null;
    }

    /**
     * Check if current time is within Indian Business Hours (09:00 - 18:00 IST, Mon-Sat).
     */
    public static function isWithinIndianBusinessHours(?Carbon $time = null): bool
    {
        $time = $time ? $time->copy()->setTimezone('Asia/Kolkata') : Carbon::now('Asia/Kolkata');

        if ($time->isSunday()) {
            return false;
        }

        $hour = $time->hour;
        return $hour >= 9 && $hour < 18;
    }

    /**
     * Indian Gazetted Holidays 2026/2027.
     */
    public static function getIndianHolidays(): array
    {
        return [
            ['name' => 'Republic Day', 'date' => '2026-01-26', 'type' => 'Gazetted'],
            ['name' => 'Maha Shivratri', 'date' => '2026-02-15', 'type' => 'Restricted'],
            ['name' => 'Holi', 'date' => '2026-03-04', 'type' => 'Gazetted'],
            ['name' => 'Eid-ul-Fitr', 'date' => '2026-03-21', 'type' => 'Gazetted'],
            ['name' => 'Mahavir Jayanti', 'date' => '2026-03-31', 'type' => 'Gazetted'],
            ['name' => 'Good Friday', 'date' => '2026-04-03', 'type' => 'Gazetted'],
            ['name' => 'Buddha Purnima', 'date' => '2026-05-01', 'type' => 'Gazetted'],
            ['name' => 'Independence Day', 'date' => '2026-08-15', 'type' => 'National'],
            ['name' => 'Raksha Bandhan', 'date' => '2026-08-28', 'type' => 'Restricted'],
            ['name' => 'Janmashtami', 'date' => '2026-09-04', 'type' => 'Gazetted'],
            ['name' => 'Gandhi Jayanti', 'date' => '2026-10-02', 'type' => 'National'],
            ['name' => 'Dussehra (Vijay Dashami)', 'date' => '2026-10-20', 'type' => 'Gazetted'],
            ['name' => 'Diwali (Deepavali)', 'date' => '2026-11-08', 'type' => 'Gazetted'],
            ['name' => 'Guru Nanak Jayanti', 'date' => '2026-11-24', 'type' => 'Gazetted'],
            ['name' => 'Christmas Day', 'date' => '2026-12-25', 'type' => 'Gazetted'],
        ];
    }

    /**
     * Localization dictionaries for supported regional languages.
     */
    public static function getLocalizationDictionaries(): array
    {
        return [
            'en' => [
                'dashboard' => 'Dashboard',
                'leads' => 'Leads',
                'deals' => 'Deals',
                'revenue' => 'Revenue',
                'total_revenue' => 'Total Revenue',
                'expenses' => 'Expenses',
                'field_sales' => 'Field Sales',
                'check_in' => 'Check In',
                'check_out' => 'Check Out',
                'analytics' => 'Analytics',
                'reports' => 'Reports',
                'insights' => 'AI Insights',
                'alerts' => 'Custom Alerts',
            ],
            'hi' => [
                'dashboard' => 'डैशबोर्ड (Dashboard)',
                'leads' => 'लीड्स / ग्राहक पूछताछ (Leads)',
                'deals' => 'डील / व्यापार अवसर (Deals)',
                'revenue' => 'कुल राजस्व / आमदनी (Revenue)',
                'total_revenue' => 'कुल राजस्व / आमदनी (Total Revenue)',
                'expenses' => 'खर्चे / व्यय (Expenses)',
                'field_sales' => 'फील्ड सेल्स / दौरा प्रबंधन (Field Sales)',
                'check_in' => 'चेक-इन करें (Check In)',
                'check_out' => 'चेक-आउट करें (Check Out)',
                'analytics' => 'व्यावसायिक विश्लेषण (Analytics)',
                'reports' => 'रिपोर्ट्स (Reports)',
                'insights' => 'एआई अंतर्दृष्टि (AI Insights)',
                'alerts' => 'अलर्ट्स व सूचनाएं (Alerts)',
            ],
            'hinglish' => [
                'dashboard' => 'Business Dashboard',
                'leads' => 'Client Leads',
                'deals' => 'Sales Deals',
                'revenue' => 'Total Kamai / Revenue',
                'expenses' => 'Business Kharcha',
                'field_sales' => 'Field Visit Planner',
                'check_in' => 'Client Site Check-In',
                'check_out' => 'Check-Out & Outcome Save',
                'analytics' => 'Business Ka Report Card',
                'reports' => 'Detailed Reports Export',
                'insights' => 'Smart AI Salah / Insights',
                'alerts' => 'Important Alerts',
            ],
            'ta' => ['dashboard' => 'டாஷ்போர்டு', 'leads' => 'வாடிக்கையாளர் வாய்ப்புகள்', 'revenue' => 'வருவாய்', 'field_sales' => 'கள விற்பனை'],
            'te' => ['dashboard' => 'డాష్‌బోర్డ్', 'leads' => 'కొత్త లీడ్లు', 'revenue' => 'ఆదాయం', 'field_sales' => 'ఫీల్డ్ సేల్స్'],
            'mr' => ['dashboard' => 'डॅशबोर्ड', 'leads' => 'नवीन ग्राहक चौकशी', 'revenue' => 'एकूण महसूल', 'field_sales' => 'फील्ड विक्री नियोजन'],
            'gu' => ['dashboard' => 'ડેશબોર્ડ', 'leads' => 'ગ્રાહક પૂછપરછ', 'revenue' => 'આવક', 'field_sales' => 'ફીલ્ડ વેચાણ'],
            'kn' => ['dashboard' => 'ಡ್ಯಾಶ್‌ಬೋರ್ಡ್', 'leads' => 'ಗ್ರಾಹಕರ ವಿಚಾರಣೆ', 'revenue' => 'ಆದಾಯ', 'field_sales' => 'ಕ್ಷೇತ್ರ ಮಾರಾಟ'],
            'bn' => ['dashboard' => 'ড্যাশবোর্ড', 'leads' => 'সম্ভাব্য গ্রাহক', 'revenue' => 'মোট রাজস্ব', 'field_sales' => 'ফিল্ড সেলস'],
        ];
    }
}
