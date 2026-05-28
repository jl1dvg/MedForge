<?php

namespace App\Listeners;

use App\Events\Crm\ExamenSolicitado;
use App\Events\Crm\SolicitudCreada;
use App\Events\Crm\WhatsappLeadQualified;
use App\Modules\CRM\Services\CrmContactResolverService;
use App\Modules\CRM\Services\CrmOpportunityService;

class CrmOpportunityListener
{
    public function __construct(
        private readonly CrmContactResolverService $contactResolver,
        private readonly CrmOpportunityService $opportunityService,
    ) {}

    public function handleWhatsappLeadQualified(WhatsappLeadQualified $event): void
    {
        $lead = $event->lead;

        $contact = $this->contactResolver->resolve(
            phone: $lead->wa_number,
            name: $lead->patient_full_name ?? $lead->display_name ?? $lead->wa_number,
            cedula: $lead->cedula,
            source: 'whatsapp',
        );

        $this->opportunityService->createFromEvent(
            contact: $contact,
            title: 'Lead WhatsApp: ' . ($lead->motivo_baja ?: 'sin motivo registrado'),
            source: 'whatsapp',
            sourceId: $lead->id,
            sourceType: 'whatsapp_lead',
            assignedTo: $event->actorUserId,
        );
    }

    public function handleSolicitudCreada(SolicitudCreada $event): void
    {
        $data = $event->solicitudData;

        $contact = $this->contactResolver->resolve(
            phone: (string) ($data['paciente_telefono'] ?? ''),
            name: (string) ($data['paciente_nombre'] ?? 'Paciente'),
            cedula: $data['paciente_cedula'] ?? null,
            source: 'solicitud',
        );

        $this->opportunityService->createFromEvent(
            contact: $contact,
            title: 'Solicitud: ' . (string) ($data['servicio'] ?? 'Servicio médico'),
            source: 'solicitud',
            sourceId: $event->solicitudId,
            sourceType: 'solicitud',
        );
    }

    public function handleExamenSolicitado(ExamenSolicitado $event): void
    {
        $data = $event->examenData;

        $contact = $this->contactResolver->resolve(
            phone: (string) ($data['paciente_telefono'] ?? ''),
            name: (string) ($data['paciente_nombre'] ?? 'Paciente'),
            cedula: $data['paciente_cedula'] ?? null,
            source: 'examen',
        );

        $this->opportunityService->createFromEvent(
            contact: $contact,
            title: 'Examen: ' . (string) ($data['descripcion_examen'] ?? 'Examen solicitado'),
            source: 'examen',
            sourceId: $event->examenId,
            sourceType: 'examen',
        );
    }
}
