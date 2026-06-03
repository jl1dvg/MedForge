// @ts-nocheck
import test from 'node:test';
import assert from 'node:assert/strict';
import { buildSolicitudFromApi, mapDetalleResponse } from './api.ts';

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
      domicilio: 'Quito',
    },
    diagnostico: { dx_code: 'H25.1', diagnostico: 'Catarata senil' },
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
  assert.equal(detalle.paciente.direccion, 'Quito');
  assert.equal(detalle.diagnosticos[0].desc, 'Catarata senil');
  assert.equal(detalle.agenda.sala, 'QX 2');
  assert.equal(detalle.agenda.fecha, '2026-06-10 08:00:00');
  assert.equal(detalle.agenda.duracion, 45);
  assert.equal(detalle.agenda.doctor, 'Dra. Ruiz');
  assert.equal(detalle.preop.length, 2);
  assert.equal(detalle.preop[0].label, 'Apto oftalmologo');
});

test('maps assigned diagnoses list from detalle completo', () => {
  const detalle = mapDetalleResponse({
    diagnosticos: [{
      id: 70030,
      form_id: 275872,
      fuente: 'consulta',
      dx_code: 'H00',
      descripcion: 'ORZUELO Y CALACIO',
      definitivo: 1,
      lateralidad: 'DERECHO',
    }],
  });

  assert.equal(detalle.diagnosticos.length, 1);
  assert.equal(detalle.diagnosticos[0].cie, 'H00');
  assert.equal(detalle.diagnosticos[0].desc, 'ORZUELO Y CALACIO · DERECHO');
});

test('normalizes card and filter insurer labels to company only', () => {
  const salud = buildSolicitudFromApi({
    id: 1,
    afiliacion: 'SN4 - SALUD S.A. - SALUD (REEMBOLSO) NIVEL 4',
    empresa_seguro: 'SN4 - SALUD S.A. - SALUD (REEMBOLSO) NIVEL 4',
    procedimiento: 'Chalazion',
  }, 'revision-codigos');
  const particular = buildSolicitudFromApi({
    id: 2,
    afiliacion: 'PAR - PARTICULAR',
    empresa_seguro: 'PAR - PARTICULAR',
    procedimiento: 'Capsulotomia',
  }, 'recibida');

  assert.equal(salud.empresa_seguro, 'SALUD S.A.');
  assert.equal(salud.plan_seguro, 'SALUD (REEMBOLSO) NIVEL 4');
  assert.equal(particular.empresa_seguro, 'PARTICULAR');
  assert.equal(particular.plan_seguro, 'PARTICULAR');
});
