<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailAccount extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'email_accounts';

    protected $fillable = [
        'tenant_id',
        'name',
        'email_address',
        'provider', // smtp_imap, sendgrid, mailgun, ses, gmail_oauth
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'signature_html',
        'is_active',
        'is_default',
        'settings',
    ];

    protected $casts = [
        'smtp_password' => 'encrypted',
        'imap_password' => 'encrypted',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'array',
        'smtp_port' => 'integer',
        'imap_port' => 'integer',
    ];
}
