<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class WhatsappLead
 *
 * @property int         $id
 * @property int         $conversation_id
 * @property int|null    $crm_lead_id
 * @property string      $wa_number
 * @property string|null $display_name
 * @property string|null $hc_number
 * @property string|null $cedula
 * @property string|null $patient_full_name
 * @property string      $motivo_baja
 * @property string      $status
 * @property int|null    $created_by_user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class WhatsappLead extends Model
{
    protected $table = 'whatsapp_leads';

    protected $casts = [
        'conversation_id'    => 'int',
        'crm_lead_id'        => 'int',
        'created_by_user_id' => 'int',
    ];

    protected $fillable = [
        'conversation_id',
        'crm_lead_id',
        'wa_number',
        'display_name',
        'hc_number',
        'cedula',
        'patient_full_name',
        'motivo_baja',
        'status',
        'created_by_user_id',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }
}
