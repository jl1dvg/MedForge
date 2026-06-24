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
  { key: 'OCT_MACULA',      label: 'OCT de mácula',                short: 'OCT mácula',       icon: 'mdi-circle-double',             equipo: 'Cirrus HD-OCT 5000' },
  { key: 'OCT_NERVIO',      label: 'OCT de nervio óptico (RNFL)',  short: 'OCT nervio',        icon: 'mdi-eye-circle-outline',        equipo: 'Cirrus HD-OCT 5000' },
  { key: 'TOPOGRAFIA',      label: 'Topografía corneal',           short: 'Topografía',        icon: 'mdi-terrain',                   equipo: 'Pentacam AXL' },
  { key: 'PAQUIMETRIA',     label: 'Paquimetría',                  short: 'Paquimetría',       icon: 'mdi-arrow-collapse-horizontal', equipo: 'Pentacam AXL' },
  { key: 'MICROESPECULAR',  label: 'Microscopía especular',        short: 'Microsc. especular', icon: 'mdi-grid',                    equipo: 'Tomey EM-4000' },
  { key: 'BIOMETRIA',       label: 'Biometría / cálculo LIO',      short: 'Biometría',         icon: 'mdi-ruler',                     equipo: 'IOLMaster 700' },
  { key: 'CAMPO_VISUAL',    label: 'Campo visual computarizado',   short: 'Campo visual',      icon: 'mdi-radar',                     equipo: 'Humphrey HFA3' },
  { key: 'RETINOGRAFIA',    label: 'Retinografía a color',         short: 'Retinografía',      icon: 'mdi-camera-iris',               equipo: 'Canon CR-2 AF' },
  { key: 'ANGIOGRAFIA',     label: 'Angiografía con fluoresceína', short: 'Angiografía',       icon: 'mdi-blood-bag',                 equipo: 'Heidelberg Spectralis' },
  { key: 'ECOGRAFIA',       label: 'Ecografía ocular (modo B)',    short: 'Ecografía B',       icon: 'mdi-pulse',                     equipo: 'Aviso Quantel' },
  { key: 'OTRO',            label: 'Otro / sin clasificar',        short: 'Otro',              icon: 'mdi-file-outline',              equipo: '' },
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

// legacyMap: { reactCampoKey: legacyKeyBase } — handleSave appends 'OD'/'OI' suffix for bilateral.
// checksTextKey: campo key whose textarea receives the check text when chip is toggled ON.
// checks: { id, label, text, flag? } — flag:true chips save as boolean payload fields, no text append.

export const TEMPLATES = {
  OCT_MACULA: {
    titulo: 'OCT de mácula',
    bilateral: true,
    campos: [
      { k: 'grosor_foveal', label: 'Grosor foveal (µm)', type: 'num', ph: '268' },
      { k: 'hallazgos',     label: 'Descripción / hallazgos', type: 'text', ph: 'Líquido subretiniano, drusas, membrana epirretiniana…' },
    ],
    legacyMap: { grosor_foveal: 'input', hallazgos: 'text' },
    checksTextKey: 'hallazgos',
    checks: [
      { id: 'dln',   label: 'DLN',        text: 'Arquitectura retiniana bien definida, fóvea con depresión central bien delineada, epitelio pigmentario continuo y uniforme, membrana limitante interna es hiporreflectiva y continua, células de Müller están bien alineadas sin signos de edema o tracción.' },
      { id: 'emq',   label: 'EMQ',        text: 'Se muestran áreas de hiporreflectividad intrarretiniana que sugieren quistes, exudados lipídicos dispersos, engrosamiento retiniano significativo en la región foveal y alteración en la capa del epitelio pigmentario de la retina, indicando edema macular quístico y depósitos lipídicos intrarretinianos.' },
      { id: 'dtr',   label: 'DTR',        text: 'El examen revela elevación de la retina, membranas fibrovasculares adheridas, aumento del grosor retiniano, distorsión de las capas retinianas y un espacio subretiniano hipoecogénico, sugerente de desprendimiento traccional de retina.' },
      { id: 'mnv',   label: 'MNV',        text: 'Se observa membrana neovascular subretinal con un área hiperreflectiva irregular y fluido subretinal, causando elevación de la retina y cambios estructurales en las capas retinianas adyacentes, indicando actividad neovascular y exudación subretinal significativa.' },
      { id: 'drusen',label: 'Drusen',     text: 'Muestra drusas cuticulares no exudativas como depósitos hiperreflectivos entre la membrana de Bruch y el epitelio pigmentario de la retina, sin elevación significativa de la retina ni acumulación de fluido subretinal, y con capas retinianas adyacentes bien estructuradas.' },
      { id: 'aepr',  label: 'A-EPR',      text: 'Áreas de hiporreflectividad, hiperreflectividad y adelgazamiento significativo en el epitelio pigmentario, disrupción de las capas retinianas y mayor visibilidad de la coroides debido a la atrofia del epitelio pigmentario.' },
      { id: 'mer',   label: 'MER',        text: 'Muestra una membrana epirretinal que distorsiona la arquitectura de la retina, formando pseudoquistes intrarretinianos, sin desprendimiento significativo de la retina ni acumulación de fluido subretinal.' },
      { id: 'am',    label: 'AM',         text: 'El examen muestra un agujero macular con discontinuidad completa en las capas retinianas centrales, creando una cavidad definida. Los bordes elevados en forma de cúpula se deben a la tracción vitreomacular, con cambios en las capas retinianas adyacentes, pero sin fluido subretinal ni edema significativo.' },
      { id: 'pseudo',label: 'Pseudohole', text: 'AGUJERO PSEUDOMACULAR' },
      { id: 'csc',   label: 'CSC',        text: 'Revela coroidopatía serosa central con desprendimiento seroso de la retina neurosensorial y acumulación de fluido subretinal. No se detecta tracción vitreorretiniana ni neovascularización, y las capas retinianas adyacentes mantienen su estructura.' },
      { id: 'vmt',   label: 'VMT',        text: 'TRACCION VITREOMACULAR' },
    ],
  },
  OCT_NERVIO: {
    titulo: 'OCT de nervio óptico (RNFL)',
    bilateral: true,
    campos: [
      { k: 'rnfl_promedio', label: 'RNFL promedio (µm)',  type: 'num', ph: 'p. ej. 92' },
      { k: 'cd_ratio',      label: 'Relación C/D',        type: 'num', ph: 'p. ej. 0.45' },
      { k: 'hallazgos',     label: 'Descripción',          type: 'text', ph: 'Adelgazamiento sectorial, defecto haz…' },
    ],
    legacyMap: { rnfl_promedio: 'input', hallazgos: 'text' },
    checksTextKey: 'hallazgos',
    checks: [
      { id: 'dln',      label: 'DLN',           text: 'Capa de fibras nerviosas retinianas dentro de los parámetros normales para la edad. Excavación fisiológica. Sin defectos sectoriales significativos.' },
      { id: 'excav',    label: 'Excavación ↑',  text: 'Aumento de la excavación papilar con adelgazamiento del anillo neural, relación copa/disco elevada.' },
      { id: 'asim',     label: 'Asimétrico',    text: 'Asimetría significativa del RNFL entre ambos ojos.' },
      { id: 'rnfl_sup', label: 'RNFL red. sup', text: 'Reducción del espesor de la CFNR en el cuadrante superior.' },
      { id: 'rnfl_inf', label: 'RNFL red. inf', text: 'Reducción del espesor de la CFNR en el cuadrante inferior.' },
      { id: 'glauc',    label: 'Sosp. glaucoma',text: 'Patrón de adelgazamiento sectorial compatible con daño glaucomatoso. Se recomienda correlación clínica y perimetría.' },
    ],
  },
  TOPOGRAFIA: {
    titulo: 'Topografía corneal',
    bilateral: true,
    campos: [
      { k: 'k1',     label: 'K1 / K Flat (D)',  type: 'num', ph: '43.2' },
      { k: 'k2',     label: 'K2 / K Steep (D)', type: 'num', ph: '44.1' },
      { k: 'astig',  label: 'Astigmatismo (D)',  type: 'num', ph: '0.9' },
      { k: 'patron', label: 'Patrón', type: 'select', opts: ['Regular', 'Astigmatismo a favor', 'En contra', 'Queratocono sospecha', 'Irregular'] },
    ],
    legacyMap: { k1: 'kFlat', k2: 'kSteep', astig: 'cilindro' },
    checksTextKey: null,
    checks: [
      { id: 'normal',   label: 'Normal',               text: 'Topografía corneal dentro de los parámetros normales.' },
      { id: 'qc',       label: 'Queratocono',          text: 'Patrón topográfico compatible con queratocono.' },
      { id: 'deg_mar',  label: 'Degeneración marginal',text: 'Hallazgos compatibles con degeneración marginal pelúcida.' },
      { id: 'ectasia',  label: 'Ectasia',              text: 'Ectasia corneal post-refractiva.' },
      { id: 'astig_irr',label: 'Astig. irregular',     text: 'Astigmatismo irregular que puede limitar la corrección óptica.' },
    ],
  },
  PAQUIMETRIA: {
    titulo: 'Paquimetría',
    bilateral: true,
    campos: [
      { k: 'pcc',       label: 'Punto más delgado (µm)', type: 'num', ph: '545' },
      { k: 'centro',    label: 'Espesor central (µm)',   type: 'num', ph: '552' },
      { k: 'hallazgos', label: 'Observaciones',          type: 'text', ph: 'Adecuado para cirugía refractiva / control…' },
    ],
    legacyMap: { centro: 'input' },
    checksTextKey: null,
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
    legacyMap: { celulas: 'densidad', cv: 'coefVar' },
    checksTextKey: null,
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
    legacyMap: { al: 'axial', acd: 'camara', lio: 'cristalino' },
    checksTextKey: null,
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
    legacyMap: { hallazgos: 'input' },
    checksTextKey: 'hallazgos',
    checks: [
      { id: 'dln',      label: 'DLN',             text: '', flag: true },
      { id: 'amaurosis',label: 'Amaurosis',        text: '', flag: true },
      { id: 'sin',      label: 'Sin Patrón',       text: 'NO ESPECIFICADO' },
      { id: 'isla',     label: 'Isla',             text: 'DE CONTRACCIÓN DEL CAMPO VISUAL PERIFÉRICO CON ISLA REMANENTE CENTRAL' },
      { id: 'arc_sup',  label: 'Arcuato Sup',      text: 'ARCUATO SUPERIOR' },
      { id: 'arc_inf',  label: 'Arcuato Inf',      text: 'ARCUATO INFERIOR' },
      { id: 'arc_doble',label: 'Arcuato Sup+Inf',  text: 'ARCUATO SUPERIOR E INFERIOR' },
      { id: 'esc_sup',  label: 'Escalón N. Sup',   text: 'ESCALÓN NASAL SUPERIOR' },
      { id: 'esc_inf',  label: 'Escalón N. Inf',   text: 'ESCALÓN NASAL INFERIOR' },
      { id: 'par_sup',  label: 'Paracentral Sup',  text: 'PARACENTRAL SUPERIOR' },
      { id: 'par_inf',  label: 'Paracentral Inf',  text: 'PARACENTRAL INFERIOR' },
      { id: 'alt_sup',  label: 'Altitudinal Sup',  text: 'ALTITUDINAL SUPERIOR' },
      { id: 'alt_inf',  label: 'Altitudinal Inf',  text: 'ALTITUDINAL INFERIOR' },
      { id: 'central',  label: '10 grados',        text: 'QUE INVOLUCRA LOS 10 GRADOS CENTRALES' },
    ],
  },
  RETINOGRAFIA: {
    titulo: 'Retinografía a color',
    bilateral: true,
    campos: [
      { k: 'hallazgos', label: 'Descripción', type: 'text', ph: 'Papila, mácula, vasos, periferia…' },
    ],
    legacyMap: { hallazgos: 'input' },
    checksTextKey: 'hallazgos',
    checks: [
      { id: 'dln',    label: 'DLN',           text: 'El nervio óptico presenta bordes definidos, coloración normal y una excavación dentro de parámetros fisiológicos. Los vasos retinianos muestran calibre y trayecto regulares sin signos de estrechamientos ni cruces patológicos. La mácula mantiene su brillo foveal habitual sin edema ni exudados. La periferia retiniana se observa íntegra, sin lesiones, desgarros ni alteraciones pigmentarias.' },
      { id: 'excav',  label: 'Excavación ↑',  text: 'El nervio óptico presenta aumento de la excavación, aproximándose a una relación copa/disco de 0.7, con adelgazamiento del anillo neural en el cuadrante temporal.' },
      { id: 'ma',     label: 'Microaneurismas',text: 'En la retina posterior se identifican pequeñas dilataciones capilares puntiformes compatibles con microaneurismas, sin hemorragias asociadas.' },
      { id: 'ocl_v',  label: 'Oclusión venosa',text: 'Los vasos venosos retinianos muestran dilatación y tortuosidad marcada. Se observan hemorragias en llama en varios cuadrantes y exudados duros en la región macular.' },
      { id: 'exud',   label: 'Exudados duros', text: 'En la mácula y algunos cuadrantes periféricos se observan depósitos lipídicos amarillentos bien delimitados compatibles con exudados duros.' },
      { id: 'neovas', label: 'Neovasos',       text: 'En la retina posterior se observa proliferación de vasos finos irregulares, compatibles con neovascularización.' },
      { id: 'edema_m',label: 'Edema macular',  text: 'En la región macular se aprecia engrosamiento retiniano difuso con pérdida del brillo foveal, indicando edema macular.' },
      { id: 'drusas', label: 'Drusas',         text: 'En la región macular y en zonas periféricas se observan múltiples drusas amarillentas bien delimitadas.' },
      { id: 'at_mac', label: 'Atrofia macular',text: 'La mácula presenta áreas extensas de atrofia del epitelio pigmentario con pérdida de la arquitectura retiniana.' },
      { id: 'rdd',    label: 'RD diabética',   text: 'En la retina posterior se identifican microaneurismas, hemorragias puntiformes y algunos exudados compatibles con retinopatía diabética.' },
      { id: 'dr',     label: 'Desprendimiento',text: 'Se observan pliegues elevados en la retina central y periférica, compatibles con desprendimiento de retina.' },
      { id: 'ed_pap', label: 'Edema papila',   text: 'La papila óptica se observa elevada, con bordes poco definidos e hiperemia generalizada. Se identifican pequeñas hemorragias peripapilares.' },
      { id: 'trac_vr',label: 'Tracción VR',    text: 'En la región macular se observa deformación del contorno foveal con elevación focal de las capas internas, compatible con tracción vitreorretiniana.' },
    ],
  },
  ANGIOGRAFIA: {
    titulo: 'Angiografía con fluoresceína',
    bilateral: true,
    campos: [
      { k: 'hallazgos', label: 'Hallazgos angiográficos', type: 'text', ph: 'Áreas de no perfusión, neovascularización, fugas…' },
    ],
    legacyMap: { hallazgos: 'input' },
    checksTextKey: 'hallazgos',
    checks: [
      { id: 'dln',    label: 'DLN',              text: 'Se muestra perfusión arterial rápida y uniforme en las arteriolas retinianas, con patrón de hiperfluorescencia indicativo de llenado vascular normal. Durante la fase venosa, se observa flujo sanguíneo uniforme y relleno venoso completo, sin anomalías como extravasación de fluoresceína, obstrucción vascular o neovascularización patológica.' },
      { id: 'rdnp',   label: 'RDNP',             text: 'Durante la fase arterial temprana se observa perfusión arterial normal y aumento de fluorescencia en la región macular, indicando edema macular difuso y microaneurismas. En la fase arterial tardía, persiste la hiperfluorescencia y se observan áreas de hipoperfusión periférica.' },
      { id: 'neovas', label: 'Neovascularización',text: 'Áreas de hiperfluorescencia en la periferia retiniana con fuga progresiva de fluoresceína, indicando permeabilidad anormal de neovasos.' },
      { id: 'ma',     label: 'Microaneurismas',  text: 'Microaneurismas dispersos como pequeñas áreas de hiperfluorescencia en la retina, especialmente en la periferia, que se ven como focos de fuga de fluoresceína en las fases tardías del estudio.' },
      { id: 'ovri',   label: 'OVRI',             text: 'El examen revela una oclusión venosa inferior con hipofluorescencia marcada, llenado tardío y deficiente de fluoresceína, congestión y dilatación venosa en la retina inferior.' },
      { id: 'ovrs',   label: 'OVRS',             text: 'El examen revela una oclusión venosa superior con hipofluorescencia marcada, llenado tardío y deficiente de fluoresceína, congestión y dilatación venosa en la retina superior.' },
      { id: 'mnv',    label: 'MNV',              text: 'El examen revela una membrana neovascular exudativa con hiperfluorescencia irregular en la región yuxtafoveal, indicando actividad exudativa y permeabilidad vascular anormal.' },
      { id: 'isq',    label: 'Isquemia',         text: 'FALTA DE PERFUSIÓN SANGUÍNEA' },
      { id: 'emq',    label: 'EMQ',              text: 'ACUMULACIÓN DE LÍQUIDO EN LA MÁCULA' },
      { id: 'fugas',  label: 'Fugas',            text: 'ESCAPES DE FLUORESCEÍNA DE LOS VASOS' },
      { id: 'epr',    label: 'Atrofia EPR',      text: 'El examen muestra atrofia del epitelio pigmentario de la retina con defecto en ventana yuxtafoveal, manifestado como área de hiperfluorescencia bien delimitada.' },
    ],
  },
  ECOGRAFIA: {
    titulo: 'Ecografía ocular (modo B)',
    bilateral: false,
    campos: [
      { k: 'hallazgos', label: 'Conclusión', type: 'text', ph: 'Membranas, masa, longitud axial…' },
    ],
    legacyMap: { hallazgos: 'input' },
    checksTextKey: 'hallazgos',
    checks: [
      { id: 'dln',    label: 'DLN',               text: 'Globo ocular con volumen conservado, retina adherida en los cuatro cuadrantes y con grosor uniforme, sin evidencia de desprendimientos. Vítreo muestra ausencia de opacidades y tracciones anormales. Nervio óptico exhibe una cabeza bien definida, sin signos de neuropatía óptica.' },
      { id: 'desp_r', label: 'Desp. Retina',      text: 'Ecogenicidad anormal en la interfaz retiniana, sugestiva de desprendimiento de retina.' },
      { id: 'hem_v',  label: 'Hemorragia vítrea', text: 'Presencia de hiperecogenicidad en la cavidad vítrea, sugerente de hemorragia vítrea.' },
      { id: 'mer',    label: 'MER',               text: 'PRESENCIA DE MEMBRANA SOBRE LA RETINA' },
      { id: 'tumor',  label: 'Tumor intraocular', text: 'MASA O LESIÓN DENTRO DEL OJO' },
      { id: 'ce',     label: 'Cuerpo extraño',    text: 'OBJETO EXTRAÑO DENTRO DEL OJO' },
      { id: 'ed_cor', label: 'Edema coroidal',    text: 'ACUMULACIÓN DE LÍQUIDO EN LA COROIDES' },
      { id: 'pvd',    label: 'PVD',               text: 'Línea ecogénica móvil dentro del vítreo sugestiva de desprendimiento del humor vítreo.' },
    ],
  },
  OTRO: {
    titulo: 'Resultado del examen',
    bilateral: false,
    campos: [
      { k: 'hallazgos', label: 'Hallazgos y conclusión', type: 'text', ph: 'Describa los hallazgos del estudio…' },
    ],
    legacyMap: { hallazgos: 'input' },
    checksTextKey: null,
    checks: null,
  },
};

export const MOTIVOS_URGENTE = [
  'Cirugía programada en 48 h, falta informe para protocolo',
  'Paciente externo — viene de otra institución solo a realizarse exámenes, necesita resultados hoy',
  'Paciente requiere informe para trámite de carnet de discapacidad',
  'Pre-quirúrgico de catarata, junta médica el viernes',
  'Control post-inyección intravítrea, decisión de retratamiento',
  'Derivación externa con plazo de autorización por vencer',
];
