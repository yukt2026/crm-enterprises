<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Organization GST Registrations
        if (!Schema::hasTable('gst_registrations')) {
            Schema::create('gst_registrations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('business_legal_name', 200);
                $table->string('trade_name', 200)->nullable();
                $table->string('gstin', 15)->index();
                $table->string('pan', 10)->nullable();
                $table->text('registered_address');
                $table->string('city', 100);
                $table->string('state', 100);
                $table->string('state_code', 2); // e.g. 27, 29, 07
                $table->string('pincode', 10);
                $table->string('business_type', 50)->default('private_limited');
                $table->string('gst_registration_status', 30)->default('active'); // active, suspended, cancelled
                $table->string('tax_calculation_preference', 20)->default('exclusive'); // exclusive, inclusive
                $table->string('default_place_of_supply', 2)->default('27');
                $table->string('invoice_prefix', 20)->default('INV-');
                $table->string('financial_year', 10)->default('2026-27');
                $table->string('invoice_numbering_series', 30)->default('sequential');
                $table->boolean('is_default')->default(true);
                $table->timestamps();
            });
        }

        // 2. Add GST & E-Invoice & E-Way Bill fields to Invoices
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'gst_registration_id')) {
                $table->unsignedBigInteger('gst_registration_id')->nullable()->after('tenant_id');
            }
            if (!Schema::hasColumn('invoices', 'document_type')) {
                $table->string('document_type', 30)->default('tax_invoice')->after('invoice_number'); // tax_invoice, bill_of_supply, proforma, credit_note, debit_note, export_invoice, delivery_challan, purchase_voucher
            }
            if (!Schema::hasColumn('invoices', 'seller_gstin')) {
                $table->string('seller_gstin', 15)->nullable()->after('document_type');
                $table->string('seller_legal_name', 200)->nullable()->after('seller_gstin');
                $table->string('seller_state_code', 2)->nullable()->after('seller_legal_name');
            }
            if (!Schema::hasColumn('invoices', 'buyer_gstin')) {
                $table->string('buyer_gstin', 15)->nullable()->after('seller_state_code');
                $table->string('buyer_legal_name', 200)->nullable()->after('buyer_gstin');
                $table->string('buyer_trade_name', 200)->nullable()->after('buyer_legal_name');
                $table->string('buyer_state_code', 2)->nullable()->after('buyer_trade_name');
                $table->string('buyer_gst_treatment', 30)->default('taxable')->after('buyer_state_code'); // taxable, exempt, nil_rated, non_gst, reverse_charge
            }
            if (!Schema::hasColumn('invoices', 'place_of_supply_state_code')) {
                $table->string('place_of_supply_state_code', 2)->nullable()->after('buyer_gst_treatment');
                $table->string('place_of_supply_state_name', 100)->nullable()->after('place_of_supply_state_code');
                $table->string('supply_type', 20)->default('intra_state')->after('place_of_supply_state_name'); // intra_state, inter_state, export, sez
            }
            if (!Schema::hasColumn('invoices', 'is_reverse_charge')) {
                $table->boolean('is_reverse_charge')->default(false)->after('supply_type');
            }
            if (!Schema::hasColumn('invoices', 'cgst_amount')) {
                $table->decimal('cgst_amount', 15, 2)->default(0)->after('tax_amount');
                $table->decimal('sgst_amount', 15, 2)->default(0)->after('cgst_amount');
                $table->decimal('igst_amount', 15, 2)->default(0)->after('sgst_amount');
                $table->decimal('cess_amount', 15, 2)->default(0)->after('igst_amount');
                $table->decimal('round_off_amount', 15, 2)->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('invoices', 'amount_in_words')) {
                $table->string('amount_in_words', 500)->nullable()->after('round_off_amount');
            }
            if (!Schema::hasColumn('invoices', 'original_invoice_id')) {
                $table->unsignedBigInteger('original_invoice_id')->nullable()->after('amount_in_words');
                $table->string('original_invoice_number', 50)->nullable()->after('original_invoice_id');
                $table->date('original_invoice_date')->nullable()->after('original_invoice_number');
                $table->string('reason_for_issuance', 255)->nullable()->after('original_invoice_date');
            }
            if (!Schema::hasColumn('invoices', 'export_type')) {
                $table->string('export_type', 30)->nullable()->after('reason_for_issuance'); // with_payment, without_payment_lut
                $table->string('shipping_bill_number', 50)->nullable()->after('export_type');
                $table->date('shipping_bill_date')->nullable()->after('shipping_bill_number');
                $table->string('port_code', 10)->nullable()->after('shipping_bill_date');
            }
            if (!Schema::hasColumn('invoices', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->after('billing_tax_id');
                $table->string('bank_account_number', 50)->nullable()->after('bank_name');
                $table->string('bank_ifsc', 20)->nullable()->after('bank_account_number');
                $table->string('bank_branch', 100)->nullable()->after('bank_ifsc');
                $table->text('terms_and_conditions')->nullable()->after('bank_branch');
            }
            // E-Invoice columns
            if (!Schema::hasColumn('invoices', 'irn')) {
                $table->string('irn', 64)->nullable()->index()->after('terms_and_conditions');
                $table->string('ack_number', 64)->nullable()->after('irn');
                $table->timestamp('ack_date')->nullable()->after('ack_number');
                $table->longText('signed_qr_code')->nullable()->after('ack_date');
                $table->longText('signed_invoice')->nullable()->after('signed_qr_code');
                $table->string('einvoice_status', 30)->default('not_applicable')->after('signed_invoice'); // not_applicable, pending, generated, cancelled, failed, sandbox_simulated
                $table->text('einvoice_error_logs')->nullable()->after('einvoice_status');
                $table->timestamp('einvoice_cancelled_at')->nullable()->after('einvoice_error_logs');
                $table->string('einvoice_cancel_reason', 255)->nullable()->after('einvoice_cancelled_at');
            }
            // E-Way Bill columns
            if (!Schema::hasColumn('invoices', 'eway_bill_number')) {
                $table->string('eway_bill_number', 32)->nullable()->index()->after('einvoice_cancel_reason');
                $table->timestamp('eway_bill_date')->nullable()->after('eway_bill_number');
                $table->timestamp('eway_bill_valid_until')->nullable()->after('eway_bill_date');
                $table->integer('distance_km')->nullable()->after('eway_bill_valid_until');
                $table->string('transporter_id', 64)->nullable()->after('distance_km');
                $table->string('transporter_name', 128)->nullable()->after('transporter_id');
                $table->string('transporter_doc_no', 64)->nullable()->after('transporter_name');
                $table->string('vehicle_number', 32)->nullable()->after('transporter_doc_no');
                $table->string('vehicle_type', 30)->default('regular')->after('vehicle_number'); // regular, over_dimensional_cargo
                $table->string('transport_mode', 20)->default('road')->after('vehicle_type'); // road, rail, air, ship
                $table->string('eway_bill_status', 30)->default('not_applicable')->after('transport_mode'); // not_applicable, generated, updated, cancelled, expired, sandbox_simulated
                $table->string('eway_bill_cancel_reason', 255)->nullable()->after('eway_bill_status');
            }
        });

        // 3. Add GST fields to InvoiceItems
        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'is_service')) {
                $table->boolean('is_service')->default(false)->after('item_name');
            }
            if (!Schema::hasColumn('invoice_items', 'unit')) {
                $table->string('unit', 20)->default('PCS')->after('quantity');
            }
            if (!Schema::hasColumn('invoice_items', 'taxable_amount')) {
                $table->decimal('taxable_amount', 15, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('invoice_items', 'cgst_rate')) {
                $table->decimal('cgst_rate', 5, 2)->default(0)->after('tax_amount');
                $table->decimal('cgst_amount', 15, 2)->default(0)->after('cgst_rate');
                $table->decimal('sgst_rate', 5, 2)->default(0)->after('cgst_amount');
                $table->decimal('sgst_amount', 15, 2)->default(0)->after('sgst_rate');
                $table->decimal('igst_rate', 5, 2)->default(0)->after('sgst_amount');
                $table->decimal('igst_amount', 15, 2)->default(0)->after('igst_rate');
                $table->decimal('cess_rate', 5, 2)->default(0)->after('igst_amount');
                $table->decimal('cess_amount', 15, 2)->default(0)->after('cess_rate');
            }
        });

        // 4. GSTIN Entity Verifications
        if (!Schema::hasTable('gst_entity_verifications')) {
            Schema::create('gst_entity_verifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('gstin', 15)->index();
                $table->string('legal_name', 200)->nullable();
                $table->string('trade_name', 200)->nullable();
                $table->string('state_code', 2)->nullable();
                $table->string('state_name', 100)->nullable();
                $table->text('registered_address')->nullable();
                $table->string('registration_status', 50)->default('active'); // active, cancelled, suspended
                $table->string('registration_type', 50)->default('regular'); // regular, composition, etc.
                $table->boolean('is_verified')->default(false);
                $table->string('validation_source', 50)->default('authorized_api'); // authorized_api, gstn_sandbox, mock_verifier
                $table->json('raw_response')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }

        // 5. Compliance Calendar Reminders
        if (!Schema::hasTable('compliance_reminders')) {
            Schema::create('compliance_reminders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('title', 200);
                $table->string('compliance_type', 50); // gstr1, gstr3b, gstr9, tds_payment, tds_return, advance_tax, license_renewal, contract_expiry, document_expiry
                $table->date('due_date');
                $table->string('financial_period', 50)->nullable(); // e.g. "November 2026", "Q3 2026-27"
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->string('responsible_accountant_name', 100)->nullable();
                $table->string('frequency', 30)->default('monthly'); // monthly, quarterly, annual, one_time
                $table->string('status', 30)->default('pending'); // pending, in_progress, completed, overdue
                $table->string('priority', 20)->default('medium'); // low, medium, high, critical
                $table->integer('escalation_level')->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // 6. Compliance Document Vault
        if (!Schema::hasTable('compliance_documents')) {
            Schema::create('compliance_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('title', 200);
                $table->string('document_type', 50); // gst_certificate, pan_card, trade_license, msme_certificate, incorporation_certificate, contract, tax_audit_report, annual_return
                $table->string('document_number', 100)->nullable();
                $table->string('file_name', 255);
                $table->string('file_path', 500);
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('mime_type', 100)->default('application/pdf');
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->boolean('is_expired')->default(false);
                $table->string('version', 20)->default('1.0');
                $table->json('tags')->nullable();
                $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
                $table->json('access_restricted_roles')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_documents');
        Schema::dropIfExists('compliance_reminders');
        Schema::dropIfExists('gst_entity_verifications');
        Schema::dropIfExists('gst_registrations');
    }
};
