<?php

namespace App\Modules\CRM\Services;

use App\Models\CrmContact;
use App\Models\CrmIntentLead;

class CrmIntentLeadService
{
    /**
     * Captures an inbound intent signal (e.g. WhatsApp message) as a CrmIntentLead.
     * Does NOT create a CrmOpportunity — that happens later, on conversion.
     *
     * Idempotent per (contact_id, source, source_id): if the same source record
     * already produced a lead, the existing lead is returned unchanged.
     */
    public function capture(
        CrmContact $contact,
        string     $source,
        ?int       $sourceId   = null,
        ?string    $sourceType = null,
        ?string    $motivo     = null,
        ?int       $assignedTo = null,
    ): CrmIntentLead {
        if ($sourceId !== null) {
            $existing = CrmIntentLead::query()
                ->where('contact_id',  $contact->id)
                ->where('source',      $source)
                ->where('source_id',   $sourceId)
                ->first();

            if ($existing instanceof CrmIntentLead) {
                return $existing;
            }
        }

        return CrmIntentLead::query()->create([
            'contact_id'  => $contact->id,
            'source'      => $source,
            'source_id'   => $sourceId,
            'source_type' => $sourceType,
            'motivo'      => $motivo,
            'assigned_to' => $assignedTo,
            'status'      => CrmIntentLead::STATUS_NUEVO,
        ]);
    }
}
