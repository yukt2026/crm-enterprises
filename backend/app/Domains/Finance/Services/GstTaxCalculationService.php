<?php

namespace App\Domains\Finance\Services;

class GstTaxCalculationService
{
    /**
     * Map of 2-digit Indian GST State Codes to State Names
     */
    public const STATE_CODES = [
        '01' => 'Jammu and Kashmir',
        '02' => 'Himachal Pradesh',
        '03' => 'Punjab',
        '04' => 'Chandigarh',
        '05' => 'Uttarakhand',
        '06' => 'Haryana',
        '07' => 'Delhi',
        '08' => 'Rajasthan',
        '09' => 'Uttar Pradesh',
        '10' => 'Bihar',
        '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland',
        '14' => 'Manipur',
        '15' => 'Mizoram',
        '16' => 'Tripura',
        '17' => 'Meghalaya',
        '18' => 'Assam',
        '19' => 'West Bengal',
        '20' => 'Jharkhand',
        '21' => 'Odisha',
        '22' => 'Chhattisgarh',
        '23' => 'Madhya Pradesh',
        '24' => 'Gujarat',
        '26' => 'Dadra & Nagar Haveli and Daman & Diu',
        '27' => 'Maharashtra',
        '28' => 'Andhra Pradesh (Old)',
        '29' => 'Karnataka',
        '30' => 'Goa',
        '31' => 'Lakshadweep',
        '32' => 'Kerala',
        '33' => 'Tamil Nadu',
        '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana',
        '37' => 'Andhra Pradesh',
        '38' => 'Ladakh',
        '97' => 'Other Territory',
        '99' => 'Centre Jurisdiction',
    ];

    /**
     * Resolve state name from 2-digit GST state code.
     */
    public function getStateName(string $stateCode): string
    {
        $code = str_pad(trim($stateCode), 2, '0', STR_PAD_LEFT);
        return self::STATE_CODES[$code] ?? 'Unknown State';
    }

    /**
     * Determine supply type (intra_state, inter_state, export, sez).
     */
    public function determineSupplyType(
        string $sellerStateCode,
        string $placeOfSupplyStateCode,
        string $buyerGstTreatment = 'taxable',
        bool $isExport = false
    ): string {
        if ($isExport) {
            return 'export';
        }

        if (in_array($buyerGstTreatment, ['sez_with_payment', 'sez_without_payment'])) {
            return 'sez';
        }

        $sellerCode = str_pad(trim($sellerStateCode), 2, '0', STR_PAD_LEFT);
        $posCode = str_pad(trim($placeOfSupplyStateCode), 2, '0', STR_PAD_LEFT);

        return ($sellerCode === $posCode) ? 'intra_state' : 'inter_state';
    }

    /**
     * Calculate comprehensive GST breakdown for line items and invoice.
     *
     * @param array $items Array of item rows:
     *   [
     *     'quantity' => 2,
     *     'unit_price' => 1000,
     *     'discount_amount' => 50,
     *     'tax_rate' => 18,
     *     'cess_rate' => 0,
     *     'is_service' => false,
     *     'unit' => 'PCS',
     *     'hsn_sac_code' => '8471',
     *   ]
     * @param string $supplyType intra_state|inter_state|export|sez
     * @param bool $isTaxInclusive
     * @param bool $isReverseCharge
     * @param string $exportType with_payment|without_payment_lut
     * @return array
     */
    public function calculateInvoiceGst(
        array $items,
        string $supplyType,
        bool $isTaxInclusive = false,
        bool $isReverseCharge = false,
        string $exportType = 'with_payment'
    ): array {
        $calculatedItems = [];
        $subtotal = 0.0;
        $totalDiscount = 0.0;
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;
        $totalCess = 0.0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount_amount'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            $cessRate = (float) ($item['cess_rate'] ?? 0);

            $grossLine = round($qty * $unitPrice, 2);
            $lineDiscount = min($discount, $grossLine);
            $netLine = round($grossLine - $lineDiscount, 2);

            if ($isTaxInclusive && ($taxRate + $cessRate) > 0) {
                // NetLine includes tax: Taxable = NetLine / (1 + (Rate/100))
                $combinedRate = ($taxRate + $cessRate) / 100;
                $taxableAmount = round($netLine / (1 + $combinedRate), 2);
            } else {
                $taxableAmount = $netLine;
            }

            // Zero tax if Export without payment (under LUT/Bond)
            if ($supplyType === 'export' && $exportType === 'without_payment_lut') {
                $cgstRate = 0.0;
                $cgstAmount = 0.0;
                $sgstRate = 0.0;
                $sgstAmount = 0.0;
                $igstRate = 0.0;
                $igstAmount = 0.0;
                $cessAmount = 0.0;
            } elseif ($supplyType === 'intra_state') {
                $cgstRate = round($taxRate / 2, 2);
                $sgstRate = round($taxRate / 2, 2);
                $igstRate = 0.0;

                $cgstAmount = round($taxableAmount * ($cgstRate / 100), 2);
                $sgstAmount = round($taxableAmount * ($sgstRate / 100), 2);
                $igstAmount = 0.0;
                $cessAmount = round($taxableAmount * ($cessRate / 100), 2);
            } else {
                // Inter-state, SEZ, or Export with payment
                $cgstRate = 0.0;
                $sgstRate = 0.0;
                $igstRate = $taxRate;

                $cgstAmount = 0.0;
                $sgstAmount = 0.0;
                $igstAmount = round($taxableAmount * ($igstRate / 100), 2);
                $cessAmount = round($taxableAmount * ($cessRate / 100), 2);
            }

            $lineTaxAmount = round($cgstAmount + $sgstAmount + $igstAmount + $cessAmount, 2);
            $lineTotalPrice = round($taxableAmount + $lineTaxAmount, 2);

            $subtotal += $taxableAmount;
            $totalDiscount += $lineDiscount;
            $totalCgst += $cgstAmount;
            $totalSgst += $sgstAmount;
            $totalIgst += $igstAmount;
            $totalCess += $cessAmount;

            $calculatedItems[] = array_merge($item, [
                'discount_amount' => $lineDiscount,
                'taxable_amount' => $taxableAmount,
                'cgst_rate' => $cgstRate,
                'cgst_amount' => $cgstAmount,
                'sgst_rate' => $sgstRate,
                'sgst_amount' => $sgstAmount,
                'igst_rate' => $igstRate,
                'igst_amount' => $igstAmount,
                'cess_rate' => $cessRate,
                'cess_amount' => $cessAmount,
                'tax_amount' => $lineTaxAmount,
                'total_price' => $lineTotalPrice,
            ]);
        }

        $subtotal = round($subtotal, 2);
        $totalCgst = round($totalCgst, 2);
        $totalSgst = round($totalSgst, 2);
        $totalIgst = round($totalIgst, 2);
        $totalCess = round($totalCess, 2);
        $totalTax = round($totalCgst + $totalSgst + $totalIgst + $totalCess, 2);

        $unroundedGrandTotal = $subtotal + $totalTax;
        $roundedGrandTotal = round($unroundedGrandTotal);
        $roundOffAmount = round($roundedGrandTotal - $unroundedGrandTotal, 2);

        return [
            'items' => $calculatedItems,
            'subtotal' => $subtotal,
            'discount_amount' => round($totalDiscount, 2),
            'cgst_amount' => $totalCgst,
            'sgst_amount' => $totalSgst,
            'igst_amount' => $totalIgst,
            'cess_amount' => $totalCess,
            'total_tax_amount' => $totalTax,
            'unrounded_total' => $unroundedGrandTotal,
            'round_off_amount' => $roundOffAmount,
            'grand_total' => $roundedGrandTotal,
            'amount_in_words' => $this->convertAmountToIndianWords($roundedGrandTotal),
            'is_reverse_charge' => $isReverseCharge,
            'supply_type' => $supplyType,
        ];
    }

    /**
     * Converts numerical INR amount into official Indian English words representation
     * (Lakhs, Crores format per RBI / GST conventions).
     */
    public function convertAmountToIndianWords(float $amount): string
    {
        $amount = abs(round($amount, 2));
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        if ($rupees === 0 && $paise === 0) {
            return 'Rupees Zero Only';
        }

        $words = 'Rupees ' . $this->numberToIndianWords($rupees);

        if ($paise > 0) {
            $words .= ' and ' . $this->numberToIndianWords($paise) . ' Paise';
        }

        return trim($words) . ' Only';
    }

    private function numberToIndianWords(int $num): string
    {
        if ($num === 0) {
            return 'Zero';
        }

        $ones = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
        ];

        $tens = [
            2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
        ];

        $output = '';

        // Crores (>= 1,00,00,000)
        if ($num >= 10000000) {
            $crores = (int) floor($num / 10000000);
            $output .= $this->numberToIndianWords($crores) . ' Crore ';
            $num %= 10000000;
        }

        // Lakhs (>= 1,00,000)
        if ($num >= 100000) {
            $lakhs = (int) floor($num / 100000);
            $output .= $this->numberToIndianWords($lakhs) . ' Lakh ';
            $num %= 100000;
        }

        // Thousands (>= 1,000)
        if ($num >= 1000) {
            $thousands = (int) floor($num / 1000);
            $output .= $this->numberToIndianWords($thousands) . ' Thousand ';
            $num %= 1000;
        }

        // Hundreds (>= 100)
        if ($num >= 100) {
            $hundreds = (int) floor($num / 100);
            $output .= $ones[$hundreds] . ' Hundred ';
            $num %= 100;
        }

        if ($num > 0) {
            if ($num < 20) {
                $output .= $ones[$num] . ' ';
            } else {
                $t = (int) floor($num / 10);
                $o = $num % 10;
                $output .= $tens[$t] . ($o > 0 ? ' ' . $ones[$o] : '') . ' ';
            }
        }

        return trim($output);
    }
}
