// Static catalogs for Exámenes Realizados
export const SEDES = ['MATRIZ', 'CEIBOS'];

export const AFILIACIONES = [
  { value: 'IESS',       label: 'IESS',            cat: 'publico' },
  { value: 'ISSPOL',     label: 'ISSPOL',           cat: 'publico' },
  { value: 'ISSFA',      label: 'ISSFA',            cat: 'publico' },
  { value: 'MSP',        label: 'Red Pública MSP',  cat: 'publico' },
  { value: 'SALUD S.A.', label: 'Salud S.A.',       cat: 'privado' },
  { value: 'BMI',        label: 'BMI Seguros',       cat: 'privado' },
  { value: 'PARTICULAR', label: 'Particular',        cat: 'particular' },
  { value: 'FUNDACIÓN',  label: 'Fundación Ver',     cat: 'fundacional' },
];

export const TIPOS = [
  { key: 'OCT_MACULA',      label: 'OCT de mácula',                short: 'OCT mácula',       icon: 'mdi-circle-double',          equipo: 'Cirrus HD-OCT 5000' },
  { key: 'OCT_NERVIO',      label: 'OCT de nervio óptico (RNFL)',  short: 'OCT nervio',        icon: 'mdi-eye-circle-outline',     equipo: 'Cirrus HD-OCT 5000' },
  { key: 'TOPOGRAFIA',      label: 'Topografía corneal',           short: 'Topografía',        icon: 'mdi-terrain',                equipo: 'Pentacam AXL' },
  { key: 'PAQUIMETRIA',     label: 'Paquimetría',                  short: 'Paquimetría',       icon: 'mdi-arrow-collapse-horizontal', equipo: 'Pentacam AXL' },
  { key: 'MICROESPECULAR',  label: 'Microscopía especular',        short: 'Microsc. especular', icon: 'mdi-grid',                  equipo: 'Tomey EM-4000' },
  { key: 'BIOMETRIA',       label: 'Biometría / cálculo LIO',      short: 'Biometría',         icon: 'mdi-ruler',                  equipo: 'IOLMaster 700' },
  { key: 'CAMPO_VISUAL',    label: 'Campo visual computarizado',   short: 'Campo visual',      icon: 'mdi-radar',                  equipo: 'Humphrey HFA3' },
  { key: 'RETINOGRAFIA',    label: 'Retinografía a color',         short: 'Retinografía',      icon: 'mdi-camera-iris',            equipo: 'Canon CR-2 AF' },
  { key: 'ANGIOGRAFIA',     label: 'Angiografía con fluoresceína', short: 'Angiografía',       icon: 'mdi-blood-bag',              equipo: 'Heidelberg Spectralis' },
  { key: 'ECOGRAFIA',       label: 'Ecografía ocular (modo B)',     short: 'Ecografía B',       icon: 'mdi-pulse',                  equipo: 'Aviso Quantel' },
  { key: 'OTRO',            label: 'Otro / sin clasificar',        short: 'Otro',              icon: 'mdi-file-outline',           equipo: '' },
];

export const TIPO_BY_KEY = Object.fromEntries(TIPOS.map((t) => [t.key, t]));

export const TABS = [
  {
    key: 'no-informados',
    label: 'Por informar',
    icon: 'mdi-file-document-edit-outline',
    tone: 'primary',
    desc: 'Exámenes con imágenes listas en el NAS que aún no tienen informe. Es tu cola de trabajo principal.',
    help: 'Aparecen aquí los procedimientos atendidos cuyos archivos ya se escanearon pero todavía no se ha redactado el informe. Desde cada fila puedes abrir el visor, redactar el informe o marcarlo como urgente para enviarlo a la bandeja prioritaria.',
  },
  {
    key: 'bandeja',
    label: 'Bandeja prioritaria',
    icon: 'mdi-bell-alert-outline',
    tone: 'danger',
    desc: 'Exámenes que tú marcaste como Urgente o Pronto porque necesitan informe cuanto antes.',
    help: 'Tu bandeja de entrada de pendientes críticos. Agrega aquí los exámenes no informados que requieren lectura prioritaria (cirugía próxima, paciente foráneo, sospecha grave…). Cada caso lleva prioridad, fecha límite, médico responsable y motivo. Los vencidos se resaltan en rojo.',
  },
  {
    key: 'informados',
    label: 'Informados',
    icon: 'mdi-file-check-outline',
    tone: 'success',
    desc: 'Exámenes con informe firmado. Puedes imprimirlos, descargarlos y ver el estado del aviso al paciente.',
    help: 'Histórico de informes completados. Muestra quién informó, cuándo, y el estado del mensaje de WhatsApp con que se avisa al paciente que su resultado está disponible. Selecciona varios para imprimir o exportar en lote.',
  },
  {
    key: 'sin-nas',
    label: 'Sin archivos',
    icon: 'mdi-folder-alert-outline',
    tone: 'warning',
    desc: 'Procedimientos agendados sin imágenes en el NAS: faltan por escanear o hay un problema de mapeo.',
    help: 'El examen está en agenda pero el escaneo no encontró archivos en el servidor de imágenes (carpeta vacía, sin mapeo o ruta inexistente). No se puede informar hasta resolver el origen. Útil para reclamar al área técnica los estudios que faltan por subir.',
  },
];

export const TEMPLATES = {
  OCT_MACULA: {
    titulo: 'OCT de mácula',
    bilateral: true,
    campos: [
      { k: 'grosor_foveal', label: 'Grosor foveal (µm)', type: 'num', ph: '268' },
      { k: 'hallazgos',     label: 'Descripción / hallazgos', type: 'text', ph: 'Líquido subretiniano, drusas, membrana epirretiniana…' },
    ],
    checks: ['DLN', 'EMQ', 'DTR', 'MNV', 'Drusen', 'A-EPR', 'MER', 'AM', 'Pseudohole', 'CSC', 'VMT'],
  },
  OCT_NERVIO: {
    titulo: 'OCT de nervio óptico (RNFL)',
    bilateral: true,
    campos: [
      { k: 'rnfl_promedio', label: 'RNFL promedio (µm)',  type: 'num', ph: 'p. ej. 92' },
      { k: 'cd_ratio',      label: 'Relación C/D',        type: 'num', ph: 'p. ej. 0.45' },
      { k: 'hallazgos',     label: 'Descripción',          type: 'text', ph: 'Adelgazamiento sectorial, defecto haz…' },
    ],
    checks: ['DLN', 'Excavación ↑', 'Asimétrico', 'RNFL reducido sup', 'RNFL reducido inf', 'Sospecha glaucoma'],
  },
  TOPOGRAFIA: {
    titulo: 'Topografía corneal',
    bilateral: true,
    campos: [
      { k: 'k1',     label: 'K1 (D)',           type: 'num', ph: '43.2' },
      { k: 'k2',     label: 'K2 (D)',           type: 'num', ph: '44.1' },
      { k: 'astig',  label: 'Astigmatismo (D)', type: 'num', ph: '0.9' },
      { k: 'patron', label: 'Patrón', type: 'select', opts: ['Regular', 'Astigmatismo a favor', 'En contra', 'Queratocono sospecha', 'Irregular'] },
    ],
    checks: ['Normal', 'Queratocono', 'Degeneración marginal', 'Ectasia', 'Astigmatismo irregular'],
  },
  PAQUIMETRIA: {
    titulo: 'Paquimetría',
    bilateral: true,
    campos: [
      { k: 'pcc',       label: 'Punto más delgado (µm)', type: 'num', ph: '545' },
      { k: 'centro',    label: 'Espesor central (µm)',   type: 'num', ph: '552' },
      { k: 'hallazgos', label: 'Observaciones',          type: 'text', ph: 'Adecuado para cirugía refractiva / control…' },
    ],
    checks: null,
  },
  MICROESPECULAR: {
    titulo: 'Microscopía especular',
    bilateral: true,
    campos: [
      { k: 'celulas',       label: 'Densidad celular (cél/mm²)', type: 'num', ph: '2480' },
      { k: 'cv',            label: 'Coef. variación (%)',        type: 'num', ph: '32' },
      { k: 'hexagonalidad', label: 'Hexagonalidad (%)',          type: 'num', ph: '58' },
      { k: 'hallazgos',     label: 'Conclusión',                 type: 'text', ph: 'Endotelio apto para facoemulsificación…' },
    ],
    checks: null,
  },
  BIOMETRIA: {
    titulo: 'Biometría / cálculo de LIO',
    bilateral: true,
    campos: [
      { k: 'al',      label: 'Longitud axial (mm)',          type: 'num', ph: '23.45' },
      { k: 'acd',     label: 'Profundidad cámara ant. (mm)', type: 'num', ph: '3.1' },
      { k: 'lio',     label: 'LIO sugerida (D)',             type: 'num', ph: '21.0' },
      { k: 'formula', label: 'Fórmula', type: 'select', opts: ['SRK/T', 'Barrett Universal II', 'Hoffer Q', 'Holladay'] },
    ],
    checks: null,
  },
  CAMPO_VISUAL: {
    titulo: 'Campo visual computarizado',
    bilateral: true,
    campos: [
      { k: 'md',  label: 'MD (dB)',  type: 'num', ph: '-3.4' },
      { k: 'psd', label: 'PSD (dB)', type: 'num', ph: '2.1' },
      { k: 'fiabilidad', label: 'Fiabilidad', type: 'select', opts: ['Buena', 'Aceptable', 'Baja'] },
      { k: 'hallazgos',  label: 'Defectos', type: 'text', ph: 'Escotoma arciforme superior…' },
    ],
    checks: ['DLN', 'Sin Patrón', 'Isla', 'Arcuato Sup', 'Arcuato Inf', 'Arcuato Sup+Inf', 'Escalón Nasal Sup', 'Escalón Nasal Inf', 'Paracentral Sup', 'Paracentral Inf', 'Altitudinal Sup', 'Altitudinal Inf', '10 grados', 'Amaurosis'],
  },
  RETINOGRAFIA: {
    titulo: 'Retinografía a color',
    bilateral: true,
    campos: [
      { k: 'hallazgos', label: 'Descripción', type: 'text', ph: 'Papila, mácula, vasos, periferia…' },
    ],
    checks: ['DLN', 'Excavación ↑', 'Microaneurismas', 'Oclusión venosa', 'Exudados duros', 'Neovasos', 'Edema macular', 'Drusas', 'Atrofia macular', 'RD diabética', 'Desprendimiento', 'Edema papila', 'Tracción VR'],
  },
  ANGIOGRAFIA: {
    titulo: 'Angiografía con fluoresceína',
    bilateral: false,
    campos: [
      { k: 'hallazgos', label: 'Conclusión', type: 'text', ph: 'Áreas de no perfusión, neovascularización…' },
    ],
    checks: ['DLN', 'RDNP', 'Neovascularización', 'Microaneurismas', 'OVRI', 'OVRS', 'MNV', 'Isquemia', 'EMQ', 'Fugas', 'Atrofia EPR'],
  },
  ECOGRAFIA: {
    titulo: 'Ecografía ocular (modo B)',
    bilateral: false,
    campos: [
      { k: 'hallazgos', label: 'Conclusión', type: 'text', ph: 'Membranas, masa, longitud axial…' },
    ],
    checks: ['DLN', 'Desp. Retina', 'Hemorragia vítrea', 'MER', 'Tumor intraocular', 'Cuerpo extraño', 'Edema coroidal', 'PVD'],
  },
  OTRO: {
    titulo: 'Resultado del examen',
    bilateral: false,
    campos: [
      { k: 'hallazgos', label: 'Hallazgos y conclusión', type: 'text', ph: 'Describa los hallazgos del estudio…' },
    ],
    checks: null,
  },
};

export const MOTIVOS_URGENTE = [
  'Cirugía programada en 48 h, falta informe para protocolo',
  'Paciente foráneo, viaja mañana — necesita informe hoy',
  'Sospecha de glaucoma avanzado, requiere lectura prioritaria',
  'Pre-quirúrgico de catarata, junta médica el viernes',
  'Control post-inyección intravítrea, decisión de retratamiento',
  'Derivación externa con plazo de autorización por vencer',
];
