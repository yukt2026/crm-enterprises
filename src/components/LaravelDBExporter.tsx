import React, { useState } from 'react';
import { Database, FileCode, Check, Copy, Network, Cpu, ShieldCheck } from 'lucide-react';
import { useCRMStore } from '../store/crmStore';

export default function LaravelDBExporter() {
  const { theme } = useCRMStore();
  const [activeTab, setActiveTab] = useState<'erd' | 'migration' | 'seeder' | 'controller'>('erd');
  const [copied, setCopied] = useState<string | null>(null);

  const copyToClipboard = (text: string, id: string) => {
    navigator.clipboard.writeText(text);
    setCopied(id);
    setTimeout(() => setCopied(null), 2000);
  };

  const migrationCode = `<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

class CreateCrmSaaSModulesTables extends Migration
{
    public function up()
    {
        // 1. Companies Table
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('website')->nullable();
            $table->string('size')->nullable();
            $table->decimal('annual_revenue', 15, 2)->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Leads Table
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->decimal('estimated_value', 12, 2)->default(0);
            $table->enum('status', ['new', 'contacted', 'qualified', 'lost'])->default('new');
            $table->string('source')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Deals Table (Pipeline)
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->decimal('value', 15, 2);
            $table->enum('stage', ['qualification', 'proposal', 'negotiation', 'won', 'lost']);
            $table->integer('probability')->default(50);
            $table->date('close_date')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 4. Invoices & Billing Table
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(15.00);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue'])->default('draft');
            $table->date('due_date');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('companies');
    }
}`;

  const seederCode = `<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use App\\Models\\User;
use App\\Models\\Company;
use App\\Models\\Lead;
use Spatie\\Permission\\Models\\Role;
use Spatie\\Permission\\Models\\Permission;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Generate Roles using Spatie Laravel Permissions
        $roles = [
            'Super Admin', 'Admin', 'Manager', 'Sales Executive', 
            'Support Executive', 'HR', 'Employee', 'Accountant'
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
        }

        // Hydrate default Admin Account
        $superAdmin = User::create([
            'name' => 'John Doe',
            'email' => 'john@nexus.com',
            'password' => bcrypt('AdminSecurePass2026!'),
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('Super Admin');

        // Create Seed Companies
        $company = Company::create([
            'name' => 'Acme Corporation',
            'industry' => 'Manufacturing',
            'website' => 'https://acme.com',
            'size' => '200+',
            'annual_revenue' => 45000000,
        ]);

        // Create Seed Leads
        Lead::create([
            'name' => 'Acme Corp Upgrade Deal',
            'company_name' => 'Acme Corporation',
            'email' => 'procurement@acme.com',
            'estimated_value' => 45000,
            'status' => 'qualified',
            'assigned_user_id' => $superAdmin->id,
        ]);
    }
}`;

  const controllerCode = `<?php

namespace App\\Http\\Controllers\\Api;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Lead;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Validator;

class LeadApiController extends Controller
{
    /**
     * Display a paginated collection of Leads with filters.
     */
    public function index(Request $request)
    {
        $query = Lead::query()->with('assignedUser');

        // Robust Filtering and Search
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('company_name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $leads = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $leads,
        ], 200);
    }

    /**
     * Create a new Lead with strict validation.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'estimated_value' => 'nullable|numeric|min:0',
            'status' => 'required|in:new,contacted,qualified,lost',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $lead = Lead::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully',
            'data' => $lead
        ], 201);
    }
}`;

  return (
    <div className={`rounded-2xl border shadow-sm overflow-hidden ${theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'}`} id="laravel-db-exporter">
      <div className={`p-6 border-b flex flex-col md:flex-row md:items-center justify-between gap-4 ${theme === 'dark' ? 'bg-slate-950/40 border-slate-800/80' : 'bg-slate-50 border-slate-200'}`}>
        <div>
          <h2 className="text-lg font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Database className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
            <span>Laravel 12 / MySQL Backend Architecture</span>
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Real enterprise-ready architecture. Inspect standard schemas, migrations, seeders, and controllers.
          </p>
        </div>
        <div className={`flex flex-wrap border rounded-lg p-1 text-xs font-semibold shrink-0 gap-1 md:gap-0 ${theme === 'dark' ? 'bg-slate-950 border-slate-800' : 'bg-white border-slate-200'}`}>
          <button
            onClick={() => setActiveTab('erd')}
            className={`px-3 py-1.5 rounded-md flex items-center gap-1.5 transition-colors ${
              activeTab === 'erd'
                ? 'bg-indigo-600 text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
            }`}
          >
            <Network className="w-3.5 h-3.5" />
            <span>ER Diagram</span>
          </button>
          <button
            onClick={() => setActiveTab('migration')}
            className={`px-3 py-1.5 rounded-md flex items-center gap-1.5 transition-colors ${
              activeTab === 'migration'
                ? 'bg-indigo-600 text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
            }`}
          >
            <FileCode className="w-3.5 h-3.5" />
            <span>Migrations</span>
          </button>
          <button
            onClick={() => setActiveTab('seeder')}
            className={`px-3 py-1.5 rounded-md flex items-center gap-1.5 transition-colors ${
              activeTab === 'seeder'
                ? 'bg-indigo-600 text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
            }`}
          >
            <Cpu className="w-3.5 h-3.5" />
            <span>Seeders</span>
          </button>
          <button
            onClick={() => setActiveTab('controller')}
            className={`px-3 py-1.5 rounded-md flex items-center gap-1.5 transition-colors ${
              activeTab === 'controller'
                ? 'bg-indigo-600 text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
            }`}
          >
            <ShieldCheck className="w-3.5 h-3.5" />
            <span>Controllers</span>
          </button>
        </div>
      </div>

      <div className="p-6">
        {activeTab === 'erd' && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div className={`p-4 rounded-xl border ${theme === 'dark' ? 'bg-slate-950/40 border-slate-800' : 'bg-slate-50 border-slate-200'}`}>
                <div className="font-bold text-xs uppercase text-slate-400 dark:text-slate-500 mb-2">users table</div>
                <ul className="text-xs space-y-1 text-slate-600 dark:text-slate-300 font-mono">
                  <li className="text-indigo-600 dark:text-indigo-400 font-bold">id (PK)</li>
                  <li>name</li>
                  <li>email (Unique)</li>
                  <li>password</li>
                  <li>two_factor_secret</li>
                  <li>status</li>
                  <li>remember_token</li>
                </ul>
              </div>

              <div className={`p-4 rounded-xl border ${theme === 'dark' ? 'bg-slate-950/40 border-slate-800' : 'bg-slate-50 border-slate-200'}`}>
                <div className="font-bold text-xs uppercase text-slate-400 dark:text-slate-500 mb-2">companies table</div>
                <ul className="text-xs space-y-1 text-slate-600 dark:text-slate-300 font-mono">
                  <li className="text-indigo-600 dark:text-indigo-400 font-bold">id (PK)</li>
                  <li>name</li>
                  <li>industry</li>
                  <li>website</li>
                  <li>annual_revenue</li>
                  <li>address</li>
                </ul>
              </div>

              <div className={`p-4 rounded-xl border ${theme === 'dark' ? 'bg-slate-950/40 border-slate-800' : 'bg-slate-50 border-slate-200'}`}>
                <div className="font-bold text-xs uppercase text-slate-400 dark:text-slate-500 mb-2">deals table</div>
                <ul className="text-xs space-y-1 text-slate-600 dark:text-slate-300 font-mono">
                  <li className="text-indigo-600 dark:text-indigo-400 font-bold">id (PK)</li>
                  <li className="text-amber-600 dark:text-amber-500 font-semibold">company_id (FK)</li>
                  <li>name</li>
                  <li>value</li>
                  <li>stage</li>
                  <li>probability</li>
                  <li className="text-amber-600 dark:text-amber-500 font-semibold">assigned_user_id (FK)</li>
                </ul>
              </div>

              <div className={`p-4 rounded-xl border ${theme === 'dark' ? 'bg-slate-950/40 border-slate-800' : 'bg-slate-50 border-slate-200'}`}>
                <div className="font-bold text-xs uppercase text-slate-400 dark:text-slate-500 mb-2">leads table</div>
                <ul className="text-xs space-y-1 text-slate-600 dark:text-slate-300 font-mono">
                  <li className="text-indigo-600 dark:text-indigo-400 font-bold">id (PK)</li>
                  <li>name</li>
                  <li>company_name</li>
                  <li>email</li>
                  <li>value</li>
                  <li>status</li>
                  <li className="text-amber-600 dark:text-amber-500 font-semibold">assigned_user_id (FK)</li>
                </ul>
              </div>
            </div>

            <div className={`border rounded-xl p-4 flex gap-3 ${theme === 'dark' ? 'bg-amber-950/20 border-amber-900/30' : 'bg-amber-50 border-amber-200'}`}>
              <div className="text-amber-600 dark:text-amber-400 font-bold text-sm shrink-0">ℹ️ Relationship Context:</div>
              <p className="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                All entities map directly to Laravel standard Eloquent Models with fully optimized eager-loading configuration: 
                <span className={`font-mono px-1.5 py-0.5 rounded border ml-1 ${theme === 'dark' ? 'bg-slate-950 border-amber-900/30 text-amber-200' : 'bg-white border-amber-300'}`}>Company::hasMany(Deal::class)</span> and 
                <span className={`font-mono px-1.5 py-0.5 rounded border ml-1 ${theme === 'dark' ? 'bg-slate-950 border-amber-900/30 text-amber-200' : 'bg-white border-amber-300'}`}>User::hasMany(Lead::class)</span>.
              </p>
            </div>
          </div>
        )}

        {activeTab === 'migration' && (
          <div className="relative">
            <button
              onClick={() => copyToClipboard(migrationCode, 'migration')}
              className={`absolute top-3 right-3 p-2 border rounded-lg transition-colors shadow-sm ${theme === 'dark' ? 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-slate-300' : 'bg-white hover:bg-slate-50 border-slate-200 text-slate-500 hover:text-indigo-600'}`}
              title="Copy code"
            >
              {copied === 'migration' ? <Check className="w-4 h-4 text-emerald-500" /> : <Copy className="w-4 h-4" />}
            </button>
            <pre className="text-xs bg-slate-900 text-slate-100 p-4 rounded-xl overflow-x-auto font-mono max-h-96 leading-relaxed">
              {migrationCode}
            </pre>
          </div>
        )}

        {activeTab === 'seeder' && (
          <div className="relative">
            <button
              onClick={() => copyToClipboard(seederCode, 'seeder')}
              className={`absolute top-3 right-3 p-2 border rounded-lg transition-colors shadow-sm ${theme === 'dark' ? 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-slate-300' : 'bg-white hover:bg-slate-50 border-slate-200 text-slate-500 hover:text-indigo-600'}`}
              title="Copy code"
            >
              {copied === 'seeder' ? <Check className="w-4 h-4 text-emerald-500" /> : <Copy className="w-4 h-4" />}
            </button>
            <pre className="text-xs bg-slate-900 text-slate-100 p-4 rounded-xl overflow-x-auto font-mono max-h-96 leading-relaxed">
              {seederCode}
            </pre>
          </div>
        )}

        {activeTab === 'controller' && (
          <div className="relative">
            <button
              onClick={() => copyToClipboard(controllerCode, 'controller')}
              className={`absolute top-3 right-3 p-2 border rounded-lg transition-colors shadow-sm ${theme === 'dark' ? 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-slate-300' : 'bg-white hover:bg-slate-50 border-slate-200 text-slate-500 hover:text-indigo-600'}`}
              title="Copy code"
            >
              {copied === 'controller' ? <Check className="w-4 h-4 text-emerald-500" /> : <Copy className="w-4 h-4" />}
            </button>
            <pre className="text-xs bg-slate-900 text-slate-100 p-4 rounded-xl overflow-x-auto font-mono max-h-96 leading-relaxed">
              {controllerCode}
            </pre>
          </div>
        )}
      </div>
    </div>
  );
}
