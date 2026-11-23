<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Enhance Projects Table
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('projects', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('customer_id')->constrained('companies')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'priority')) {
                $table->string('priority', 20)->default('medium')->after('status');
            }
            if (!Schema::hasColumn('projects', 'budget')) {
                $table->decimal('budget', 15, 2)->default(0)->after('priority');
            }
            if (!Schema::hasColumn('projects', 'estimated_hours')) {
                $table->unsignedInteger('estimated_hours')->default(0)->after('budget');
            }
            if (!Schema::hasColumn('projects', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('progress');
            }
            if (!Schema::hasColumn('projects', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('is_archived');
            }
            if (!Schema::hasColumn('projects', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('archived_at');
            }
        });

        // Widen status and priority on projects
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status', 50)->default('not_started')->change();
        });

        // 2. Enhance Project Members Table
        Schema::table('project_members', function (Blueprint $table) {
            if (!Schema::hasColumn('project_members', 'role')) {
                $table->string('role', 50)->default('member')->after('user_id');
            }
        });

        // 3. Project Milestones Table
        if (!Schema::hasTable('project_milestones')) {
            Schema::create('project_milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status', 50)->default('pending'); // pending, in_progress, completed
                $table->unsignedTinyInteger('progress')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'status']);
            });
        }

        // 4. Project Comments Table
        if (!Schema::hasTable('project_comments')) {
            Schema::create('project_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('comment');
                $table->json('attachments')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
            });
        }

        // 5. Enhance Tasks Table
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'estimated_hours')) {
                $table->decimal('estimated_hours', 8, 2)->default(0)->after('due_date');
            }
            $table->string('status', 50)->default('todo')->change();
            $table->string('priority', 20)->default('medium')->change();
        });

        // 6. Task Dependencies Table
        if (!Schema::hasTable('task_dependencies')) {
            Schema::create('task_dependencies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
                $table->string('dependency_type', 50)->default('finish_to_start'); // finish_to_start, start_to_start
                $table->timestamps();

                $table->unique(['task_id', 'depends_on_task_id']);
                $table->index(['tenant_id', 'task_id']);
                $table->index(['tenant_id', 'depends_on_task_id']);
            });
        }

        // 7. Task Time Logs Table
        if (!Schema::hasTable('task_time_logs')) {
            Schema::create('task_time_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('start_time')->nullable();
                $table->timestamp('end_time')->nullable();
                $table->unsignedInteger('duration_minutes')->default(0);
                $table->text('description')->nullable();
                $table->boolean('is_billable')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'task_id']);
                $table->index(['tenant_id', 'user_id']);
            });
        }

        // 8. Enhance Meetings Table
        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'agenda')) {
                $table->text('agenda')->nullable()->after('description');
            }
            if (!Schema::hasColumn('meetings', 'organizer_id')) {
                $table->foreignId('organizer_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('meetings', 'lead_id')) {
                $table->foreignId('lead_id')->nullable()->after('organizer_id')->constrained('leads')->nullOnDelete();
            }
            if (!Schema::hasColumn('meetings', 'contact_id')) {
                $table->foreignId('contact_id')->nullable()->after('lead_id')->constrained('contacts')->nullOnDelete();
            }
            if (!Schema::hasColumn('meetings', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('contact_id')->constrained('companies')->nullOnDelete();
            }
            if (!Schema::hasColumn('meetings', 'deal_id')) {
                $table->foreignId('deal_id')->nullable()->after('company_id')->constrained('deals')->nullOnDelete();
            }
            if (!Schema::hasColumn('meetings', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('deal_id')->constrained('projects')->nullOnDelete();
            }
            if (!Schema::hasColumn('meetings', 'timezone')) {
                $table->string('timezone', 60)->default('UTC')->after('end_time');
            }
            if (!Schema::hasColumn('meetings', 'location_type')) {
                $table->string('location_type', 30)->default('online')->after('timezone'); // online, physical
            }
            if (!Schema::hasColumn('meetings', 'location_address')) {
                $table->string('location_address', 255)->nullable()->after('location_type');
            }
            if (!Schema::hasColumn('meetings', 'outcome')) {
                $table->text('outcome')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('meetings', 'status')) {
                $table->string('status', 40)->default('scheduled')->after('outcome'); // scheduled, in_progress, completed, cancelled, rescheduled
            }
            if (!Schema::hasColumn('meetings', 'rescheduled_from_meeting_id')) {
                $table->foreignId('rescheduled_from_meeting_id')->nullable()->after('status')->constrained('meetings')->nullOnDelete();
            }

            $table->index(['tenant_id', 'meeting_date']);
            $table->index(['tenant_id', 'status']);
        });

        // 9. Enhance Meeting Attendees Table
        Schema::table('meeting_attendees', function (Blueprint $table) {
            if (!Schema::hasColumn('meeting_attendees', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('meeting_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('meeting_attendees', 'contact_id')) {
                $table->foreignId('contact_id')->nullable()->after('user_id')->constrained('contacts')->nullOnDelete();
            }
            if (!Schema::hasColumn('meeting_attendees', 'status')) {
                $table->string('status', 30)->default('pending')->after('attendee_email'); // pending, accepted, declined, tentative
            }
            if (!Schema::hasColumn('meeting_attendees', 'notes')) {
                $table->string('notes', 255)->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_time_logs');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('project_comments');
        Schema::dropIfExists('project_milestones');

        Schema::table('meeting_attendees', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'contact_id', 'status', 'notes']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn([
                'agenda', 'organizer_id', 'lead_id', 'contact_id', 'company_id',
                'deal_id', 'project_id', 'timezone', 'location_type', 'location_address',
                'outcome', 'status', 'rescheduled_from_meeting_id'
            ]);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['estimated_hours']);
        });

        Schema::table('project_members', function (Blueprint $table) {
            $table->dropColumn(['role']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'company_id', 'owner_id', 'priority',
                'budget', 'estimated_hours', 'is_archived', 'archived_at', 'completed_at'
            ]);
        });
    }
};
