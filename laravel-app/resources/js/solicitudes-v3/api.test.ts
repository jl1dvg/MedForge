// @ts-nocheck
import test from 'node:test';
import assert from 'node:assert/strict';
import { mapDetalleResponse } from './api.ts';

test('maps prefactura detail using V2 field names', () => {
  const detalle = mapDetalleResponse({
    detalle: {
      hc_number: 'HC-1',
      crm_contacto_telefono: '0999999999',
      fecha_programada: '2026-06-10 09:00:00',
      duracion: 45,
    },
    notas: [],
    tareas: [],
    propuestas: [],
    adjuntos: [],
    checklist: [
      { slug: 'apto-oftalmologo', label: 'Apto oftalmologo', completed: true },
      { slug: 'apto-anestesia', label: 'Apto anestesia', completed: false },
    ],
    paciente: {
      hc_number: 'HC-1',
      fecha_nacimiento: '1980-01-01',
      sexo: 'F',
      direccion: 'Quito',
    },
    diagnostico: [{ dx_code: 'H25.1', descripcion: 'Catarata senil' }],
    consulta: { examen_fisico: 'Examen real', plan: 'Plan real' },
    derivacion: {
      cod_derivacion: 'DER-123',
      fecha_vigencia: '2026-07-01',
      archivo_derivacion_path: 'storage/derivaciones/DER-123.pdf',
      diagnostico: 'H25.1 - Catarata',
    },
    derivacion_tab: {
      actions: {
        authorization: { visible: false },
        download_pdf: { visible: true, href: '/derivaciones/archivo/55' },
      },
      vigencia: {
        text: '<strong>Dias para caducar:</strong> 28 dias',
        badge: { color: 'warning', texto: 'Por vencer' },
      },
    },
    bloqueos_agenda: [{
      sala: 'QX 2',
      fecha_inicio: '2026-06-10 08:00:00',
      fecha_fin: '2026-06-10 08:45:00',
      doctor: 'Dra. Ruiz',
    }],
  });

  assert.equal(detalle.derivacion.tiene, true);
  assert.equal(detalle.derivacion.cod, 'DER-123');
  assert.equal(detalle.derivacion.fecha_vigencia, '2026-07-01');
  assert.equal(detalle.derivacion.archivo_href, '/derivaciones/archivo/55');
  assert.equal(detalle.derivacion.vigencia_label, 'Por vencer');
  assert.equal(detalle.agenda.sala, 'QX 2');
  assert.equal(detalle.agenda.fecha, '2026-06-10 08:00:00');
  assert.equal(detalle.agenda.duracion, 45);
  assert.equal(detalle.agenda.doctor, 'Dra. Ruiz');
  assert.equal(detalle.preop.length, 2);
  assert.equal(detalle.preop[0].label, 'Apto oftalmologo');
});
