# SigCenter — Mapa de Selectores DOM

> Actualizar este archivo cada vez que se mapee un nuevo módulo de SigCenter.
> Columna "Estado": ✅ verificado | ⚠️ frágil (puede cambiar) | ❌ no funciona

## Módulo: Ficha de Paciente

| Campo | Selector | Estado | Notas |
|-------|----------|--------|-------|
| Cédula / ID | `#lblCedula` | ⚠️ | Verificar en contexto de admisión |
| Nombre completo | `#lblNombre` | ⚠️ | |
| Historia clínica # | `#lblHistoria` | ⚠️ | |
| Fecha de atención | `#lblFechaAtencion` | ⚠️ | |
| Médico tratante | `#lblMedico` | ⚠️ | |

## Módulo: Protocolos Quirúrgicos

| Campo | Selector | Estado | Notas |
|-------|----------|--------|-------|
| Tipo de cirugía | `#ddlTipoCirugia` | ⚠️ | Select — usar `change` event |
| Ojo | `#ddlOjo` | ⚠️ | Valores: OD, OI, AO |
| Diagnóstico | `#txtDiagnostico` | ⚠️ | textarea |
| Tratamiento | `#txtTratamiento` | ⚠️ | textarea |

## Módulo: Exámenes

| Campo | Selector | Estado | Notas |
|-------|----------|--------|-------|
| Agudeza Visual OD | `#txtAVOD` | ⚠️ | |
| Agudeza Visual OI | `#txtAVOI` | ⚠️ | |
| PIO OD | `#txtPIOOD` | ⚠️ | |
| PIO OI | `#txtPIOOI` | ⚠️ | |

## Módulo: Citas / Agenda

| Campo | Selector | Estado | Notas |
|-------|----------|--------|-------|
| Fecha cita | `#txtFechaCita` | ⚠️ | Datepicker — puede necesitar trigger especial |
| Médico | `#ddlMedicoCita` | ⚠️ | Select |
| Motivo | `#txtMotivoCita` | ⚠️ | |

---

## Notas de mapeo

- SigCenter aparentemente usa WebForms ASP.NET — los IDs pueden tener prefijos de UpdatePanel (`ctl00_ContentPlaceHolder1_`)
- Confirmar si la versión de SigCenter en CIVE tiene los IDs limpios o con prefijo
- Si hay iframes, el content script necesita permisos adicionales o scripts separados por frame
