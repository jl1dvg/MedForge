<?php

namespace App\Events\Crm;

use App\Models\WhatsappLead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsappLeadQualified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly WhatsappLead $lead,
        public readonly ?int $actorUserId = null,
    ) {}
}
