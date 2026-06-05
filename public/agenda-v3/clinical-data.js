/* MedForge — catálogos clínicos para la historia clínica oftalmológica.
   Expuesto en window.CLIN. */
(function () {
  // Vías de administración
  const VIAS = ["Tópica oftálmica OD", "Tópica oftálmica OS", "Tópica oftálmica AO", "Oral", "Subconjuntival", "Intravítrea"];
  const FRECS = ["Cada 2 h", "Cada 4 h", "Cada 6 h", "Cada 8 h", "Cada 12 h", "1 vez al día", "Antes de dormir", "PRN (a demanda)"];
  const DURS = ["3 días", "5 días", "7 días", "10 días", "14 días", "21 días", "1 mes", "Permanente"];

  // Medicamentos / gotas oftálmicas frecuentes
  const MEDS = [
    { id: "tobradex", nm: "Tobramicina + Dexametasona", clase: "Antibiótico + corticoide", via: "Tópica oftálmica OD", frec: "Cada 6 h", dur: "7 días" },
    { id: "moxiflox", nm: "Moxifloxacino 0.5%", clase: "Antibiótico", via: "Tópica oftálmica OD", frec: "Cada 6 h", dur: "7 días" },
    { id: "prednis", nm: "Prednisolona acetato 1%", clase: "Corticoide", via: "Tópica oftálmica OD", frec: "Cada 6 h", dur: "14 días" },
    { id: "ketorol", nm: "Ketorolaco 0.5%", clase: "AINE", via: "Tópica oftálmica OD", frec: "Cada 8 h", dur: "14 días" },
    { id: "latanop", nm: "Latanoprost 0.005%", clase: "Análogo de prostaglandina", via: "Tópica oftálmica AO", frec: "Antes de dormir", dur: "Permanente" },
    { id: "timolol", nm: "Timolol 0.5%", clase: "Betabloqueante", via: "Tópica oftálmica AO", frec: "Cada 12 h", dur: "Permanente" },
    { id: "brimon", nm: "Brimonidina 0.2%", clase: "Agonista alfa-2", via: "Tópica oftálmica AO", frec: "Cada 12 h", dur: "Permanente" },
    { id: "dorzo", nm: "Dorzolamida + Timolol", clase: "Inhibidor AC + betabloq.", via: "Tópica oftálmica AO", frec: "Cada 12 h", dur: "Permanente" },
    { id: "lagrimas", nm: "Carboximetilcelulosa 0.5%", clase: "Lágrima artificial", via: "Tópica oftálmica AO", frec: "Cada 4 h", dur: "1 mes" },
    { id: "olopat", nm: "Olopatadina 0.1%", clase: "Antihistamínico", via: "Tópica oftálmica AO", frec: "Cada 12 h", dur: "14 días" },
    { id: "tropic", nm: "Tropicamida 1%", clase: "Ciclopléjico / midriático", via: "Tópica oftálmica OD", frec: "PRN (a demanda)", dur: "3 días" },
    { id: "acetazol", nm: "Acetazolamida 250 mg", clase: "Inhibidor AC (oral)", via: "Oral", frec: "Cada 8 h", dur: "5 días" },
  ];

  // Plantillas de receta (rellenan varias filas)
  const PLANTILLAS_RX = [
    { id: "postfaco", nm: "Post-facoemulsificación estándar", items: [
      { med: "moxiflox", frec: "Cada 6 h", dur: "7 días" },
      { med: "prednis", frec: "Cada 6 h", dur: "21 días" },
      { med: "ketorol", frec: "Cada 8 h", dur: "14 días" },
    ] },
    { id: "ojoseco", nm: "Ojo seco / superficie ocular", items: [
      { med: "lagrimas", frec: "Cada 4 h", dur: "1 mes" },
    ] },
    { id: "conjun", nm: "Conjuntivitis bacteriana", items: [
      { med: "moxiflox", frec: "Cada 4 h", dur: "7 días" },
    ] },
    { id: "glauinicio", nm: "Inicio de tratamiento de glaucoma", items: [
      { med: "latanop", frec: "Antes de dormir", dur: "Permanente" },
    ] },
    { id: "alergica", nm: "Conjuntivitis alérgica", items: [
      { med: "olopat", frec: "Cada 12 h", dur: "14 días" },
      { med: "lagrimas", frec: "Cada 6 h", dur: "1 mes" },
    ] },
  ];

  // Exámenes complementarios
  const EXAMENES = [
    { id: "oct_mac", nm: "OCT macular", grupo: "Imagen", icon: "mdi-grain" },
    { id: "oct_nervio", nm: "OCT de nervio óptico (RNFL)", grupo: "Imagen", icon: "mdi-grain" },
    { id: "paqui", nm: "Paquimetría corneal", grupo: "Córnea", icon: "mdi-ruler" },
    { id: "topo", nm: "Topografía corneal", grupo: "Córnea", icon: "mdi-map-outline" },
    { id: "angio", nm: "Angiografía fluoresceínica", grupo: "Imagen", icon: "mdi-water-outline" },
    { id: "eco", nm: "Ecografía ocular modo B", grupo: "Imagen", icon: "mdi-pulse" },
    { id: "campo", nm: "Campo visual (campimetría)", grupo: "Funcional", icon: "mdi-eye-settings-outline" },
    { id: "biometria", nm: "Biometría (cálculo de LIO)", grupo: "Quirúrgico", icon: "mdi-calculator-variant-outline" },
    { id: "espec", nm: "Microscopía especular", grupo: "Córnea", icon: "mdi-hexagon-multiple-outline" },
    { id: "schirmer", nm: "Test de Schirmer", grupo: "Funcional", icon: "mdi-water-percent" },
  ];

  // Procedimientos y cirugías solicitables
  const PROCEDIMIENTOS = [
    { id: "faco", nm: "Facoemulsificación + LIO", clase: "Cirugía", area: "quirurgico" },
    { id: "vpp", nm: "Vitrectomía pars plana", clase: "Cirugía", area: "quirurgico" },
    { id: "antivegf", nm: "Inyección intravítrea (anti-VEGF)", clase: "Procedimiento", area: "quirurgico" },
    { id: "yag", nm: "Capsulotomía láser YAG", clase: "Procedimiento", area: "quirurgico" },
    { id: "pterigion", nm: "Exéresis de pterigión + injerto", clase: "Cirugía", area: "quirurgico" },
    { id: "trabec", nm: "Trabeculectomía", clase: "Cirugía", area: "quirurgico" },
    { id: "iridotomia", nm: "Iridotomía láser (LPI)", clase: "Procedimiento", area: "quirurgico" },
    { id: "crosslink", nm: "Crosslinking corneal", clase: "Procedimiento", area: "quirurgico" },
  ];

  const OJOS = ["OD (derecho)", "OS (izquierdo)", "AO (ambos)"];
  const PRIORIDADES = ["Normal", "Preferente", "Urgente"];

  // Antecedentes por paciente (semilla) — clave: hc. Persistente.
  const ANTEC_SEED = {
    "HC-66510": { patologicos: ["Diabetes mellitus tipo 2", "Hipertensión arterial"], quirurgicos: ["Apendicectomía (2009)"], medicamentos: ["Metformina 850 mg", "Losartán 50 mg"], alergicos: ["Penicilina"], familiares: ["Madre: glaucoma"] },
    "HC-92418": { patologicos: ["Hipertensión arterial"], quirurgicos: ["Facoemulsificación OD (2026)"], medicamentos: ["Enalapril 10 mg"], alergicos: [], familiares: ["Padre: catarata"] },
    "HC-44012": { patologicos: ["Glaucoma primario de ángulo abierto"], quirurgicos: [], medicamentos: ["Latanoprost AO"], alergicos: ["Sulfas"], familiares: ["Hermana: glaucoma"] },
  };
  const ANTEC_VACIO = { patologicos: [], quirurgicos: [], medicamentos: [], alergicos: [], familiares: [] };

  // Persistencia de antecedentes (localStorage → "para siempre")
  const LS_KEY = "mf_antecedentes_v1";
  function loadAntec() {
    try { return Object.assign({}, ANTEC_SEED, JSON.parse(localStorage.getItem(LS_KEY) || "{}")); }
    catch (e) { return Object.assign({}, ANTEC_SEED); }
  }
  let _store = loadAntec();
  function getAntec(hc) {
    return Object.assign({}, ANTEC_VACIO, _store[hc] || {});
  }
  function saveAntec(hc, antec) {
    _store[hc] = antec;
    try {
      const persisted = JSON.parse(localStorage.getItem(LS_KEY) || "{}");
      persisted[hc] = antec;
      localStorage.setItem(LS_KEY, JSON.stringify(persisted));
    } catch (e) {}
  }

  window.CLIN = {
    VIAS, FRECS, DURS, MEDS, PLANTILLAS_RX, EXAMENES, PROCEDIMIENTOS, OJOS, PRIORIDADES,
    ANTEC_CATS: [
      { key: "patologicos", label: "Patológicos", icon: "mdi-heart-pulse" },
      { key: "quirurgicos", label: "Quirúrgicos", icon: "mdi-hospital-box-outline" },
      { key: "medicamentos", label: "Medicación habitual", icon: "mdi-pill" },
      { key: "alergicos", label: "Alérgicos", icon: "mdi-alert-rhombus-outline" },
      { key: "familiares", label: "Familiares", icon: "mdi-account-group-outline" },
    ],
    getAntec, saveAntec,
  };
})();
