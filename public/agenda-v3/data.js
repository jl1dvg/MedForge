/* =========================================================================
   MedForge — Agendamiento · datos semilla
   Modelo normalizado: sedes · áreas · médicos · salas · tipos de cita ·
   horarios · citas · bloqueos. Expuesto en window.AG.
   Fecha de referencia del prototipo: jueves 4 de junio de 2026.
   ========================================================================= */
(function () {
  const HOY = "2026-06-04";

  /* ---------- Áreas clínicas ---------- */
  const AREAS = [
    { id: "consulta",   label: "Consulta",   icon: "mdi-stethoscope",            color: "#1f9d7a", bg: "#dff5ee", fg: "#17654f" },
    { id: "quirurgico", label: "Quirúrgico", icon: "mdi-hospital-box-outline",   color: "#d34b5b", bg: "#fde2e7", fg: "#9f2d3e" },
    { id: "imagenes",   label: "Imágenes",   icon: "mdi-radiology-box-outline",  color: "#3d7ac7", bg: "#e3edf9", fg: "#2e5e99" },
    { id: "comercial",  label: "Comercial",  icon: "mdi-tag-text-outline",       color: "#d59623", bg: "#fff0d1", fg: "#8a5d0a" },
  ];

  /* ---------- Sedes ---------- */
  const SEDES = [
    { id: "ceibos",    label: "Ceibos",     abrev: "CB", apertura: "08:00", cierre: "18:00" },
    { id: "villaclub", label: "Villa Club", abrev: "VC", apertura: "08:00", cierre: "17:00" },
  ];

  /* ---------- Médicos / profesionales ---------- */
  const MEDICOS = [
    { id: "m_ramirez",   nombre: "Dra. Carolina Ramírez",   esp: "Retina y catarata",  areas: ["consulta", "quirurgico"], sede: "ceibos",    color: "#5156be", iniciales: "CR" },
    { id: "m_salazar",   nombre: "Dr. Marco Salazar",       esp: "Vítreo-retina",      areas: ["quirurgico", "consulta"], sede: "ceibos",    color: "#d34b5b", iniciales: "MS" },
    { id: "m_veintim",   nombre: "Dra. Valeria Veintimilla",esp: "Glaucoma",           areas: ["consulta", "quirurgico"], sede: "ceibos",    color: "#0863be", iniciales: "VV" },
    { id: "m_vargas",    nombre: "Dr. Andrés Vargas",       esp: "Córnea y segmento ant.",areas: ["consulta", "imagenes"],sede: "ceibos",    color: "#7c4dff", iniciales: "AV" },
    { id: "m_encalada",  nombre: "Lic. Daniela Encalada",   esp: "Optometría",         areas: ["consulta"],               sede: "ceibos",    color: "#1f9d7a", iniciales: "DE" },
    { id: "m_andrade",   nombre: "Dr. Jorge Andrade",       esp: "Oculoplástica",      areas: ["consulta", "quirurgico"], sede: "villaclub", color: "#ffa800", iniciales: "JA" },
    { id: "m_mendoza",   nombre: "Dra. Paula Mendoza",      esp: "Oftalmología pediátrica",areas: ["consulta"],           sede: "villaclub", color: "#3d7ac7", iniciales: "PM" },
  ];

  /* ---------- Salas / consultorios / quirófanos ---------- */
  const SALAS = [
    // Ceibos
    { id: "s_cons1", label: "Consultorio 1",     tipo: "consultorio", area: "consulta",   sede: "ceibos",    cap: 1 },
    { id: "s_cons2", label: "Consultorio 2",     tipo: "consultorio", area: "consulta",   sede: "ceibos",    cap: 1 },
    { id: "s_cons3", label: "Consultorio 3",     tipo: "consultorio", area: "consulta",   sede: "ceibos",    cap: 1 },
    { id: "s_opto",  label: "Box optometría",    tipo: "box",         area: "consulta",   sede: "ceibos",    cap: 1 },
    { id: "s_qx1",   label: "Quirófano 1",       tipo: "quirofano",   area: "quirurgico", sede: "ceibos",    cap: 1 },
    { id: "s_qx2",   label: "Quirófano 2",       tipo: "quirofano",   area: "quirurgico", sede: "ceibos",    cap: 1 },
    { id: "s_proc",  label: "Sala procedimientos",tipo: "procedimiento",area:"quirurgico",sede: "ceibos",    cap: 1 },
    { id: "s_laser", label: "Sala láser",        tipo: "laser",       area: "quirurgico", sede: "ceibos",    cap: 1 },
    { id: "s_img1",  label: "Imágenes A (OCT)",  tipo: "imagen",      area: "imagenes",   sede: "ceibos",    cap: 1 },
    { id: "s_img2",  label: "Imágenes B (campo)",tipo: "imagen",      area: "imagenes",   sede: "ceibos",    cap: 1 },
    { id: "s_com",   label: "Asesoría comercial",tipo: "comercial",   area: "comercial",  sede: "ceibos",    cap: 1 },
    // Villa Club
    { id: "s_vcA",   label: "Consultorio A",     tipo: "consultorio", area: "consulta",   sede: "villaclub", cap: 1 },
    { id: "s_vcB",   label: "Consultorio B",     tipo: "consultorio", area: "consulta",   sede: "villaclub", cap: 1 },
    { id: "s_vcqx",  label: "Quirófano VC",      tipo: "quirofano",   area: "quirurgico", sede: "villaclub", cap: 1 },
    { id: "s_vcimg", label: "Imágenes VC",       tipo: "imagen",      area: "imagenes",   sede: "villaclub", cap: 1 },
  ];

  /* ---------- Tipos de cita ---------- */
  // requiereTipoSala: la cita solo puede ubicarse en salas de ese 'tipo'.
  const TIPOS = [
    { id: "t_cons",   label: "Consulta oftalmológica",  area: "consulta",   dur: 20, requiereTipoSala: ["consultorio"] },
    { id: "t_primera",label: "Consulta primera vez",    area: "consulta",   dur: 30, requiereTipoSala: ["consultorio"] },
    { id: "t_postop", label: "Control post-operatorio", area: "consulta",   dur: 15, requiereTipoSala: ["consultorio"] },
    { id: "t_opto",   label: "Optometría / refracción", area: "consulta",   dur: 30, requiereTipoSala: ["box", "consultorio"] },
    { id: "t_faco",   label: "Facoemulsificación + LIO",area: "quirurgico", dur: 45, requiereTipoSala: ["quirofano"] },
    { id: "t_vpp",    label: "Vitrectomía pars plana",  area: "quirurgico", dur: 90, requiereTipoSala: ["quirofano"] },
    { id: "t_antivegf",label:"Inyección intravítrea",   area: "quirurgico", dur: 20, requiereTipoSala: ["procedimiento"] },
    { id: "t_yag",    label: "Capsulotomía láser YAG",  area: "quirurgico", dur: 15, requiereTipoSala: ["laser"] },
    { id: "t_oct",    label: "OCT macular",             area: "imagenes",   dur: 15, requiereTipoSala: ["imagen"] },
    { id: "t_campo",  label: "Campimetría 24-2",        area: "imagenes",   dur: 20, requiereTipoSala: ["imagen"] },
    { id: "t_topo",   label: "Topografía corneal",      area: "imagenes",   dur: 15, requiereTipoSala: ["imagen"] },
    { id: "t_cotiza", label: "Cotización / afiliación", area: "comercial",  dur: 20, requiereTipoSala: ["comercial"] },
    { id: "t_preqx",  label: "Valoración pre-quirúrgica",area:"comercial",  dur: 15, requiereTipoSala: ["comercial"] },
  ];

  /* ---------- Horarios por médico (turnos por día de semana) ---------- */
  // dia: 1=lun … 6=sáb. excepciones puntuales aparte (ver BLOQUEOS).
  const HORARIOS = [
    { medico: "m_ramirez", dia: 1, ini: "08:00", fin: "13:00", sede: "ceibos" },
    { medico: "m_ramirez", dia: 4, ini: "08:00", fin: "14:00", sede: "ceibos" },
    { medico: "m_ramirez", dia: 4, ini: "15:00", fin: "18:00", sede: "ceibos" },
    { medico: "m_salazar", dia: 4, ini: "08:00", fin: "13:00", sede: "ceibos" },
    { medico: "m_veintim", dia: 4, ini: "09:00", fin: "17:00", sede: "ceibos" },
    { medico: "m_vargas",  dia: 4, ini: "08:00", fin: "16:00", sede: "ceibos" },
    { medico: "m_encalada",dia: 4, ini: "08:00", fin: "18:00", sede: "ceibos" },
    { medico: "m_andrade", dia: 4, ini: "08:00", fin: "13:00", sede: "villaclub" },
    { medico: "m_mendoza", dia: 4, ini: "13:00", fin: "17:00", sede: "villaclub" },
  ];

  /* ---------- helpers ---------- */
  const toMin = (t) => { const [h, m] = t.split(":").map(Number); return h * 60 + m; };
  const toHHMM = (m) => `${String(Math.floor(m / 60)).padStart(2, "0")}:${String(m % 60).padStart(2, "0")}`;

  /* ---------- Bloqueos manuales (no agendable) ---------- */
  const BLOQUEOS = [
    { id: "b1", scope: "medico",  ref: "m_ramirez", fecha: HOY, ini: "10:40", fin: "11:20", motivo: "Reunión comité quirúrgico", tipo: "reunion" },
    { id: "b2", scope: "sala",    ref: "s_qx2",     fecha: HOY, ini: "08:00", fin: "10:00", motivo: "Mantenimiento microscopio", tipo: "mantenimiento" },
    { id: "b3", scope: "medico",  ref: "m_salazar", fecha: HOY, ini: "13:00", fin: "18:00", motivo: "Cirugía externa / hospital", tipo: "ausencia" },
    { id: "b4", scope: "medico",  ref: "m_vargas",  fecha: HOY, ini: "12:30", fin: "13:30", motivo: "Almuerzo", tipo: "almuerzo" },
  ];

  /* ---------- Citas del día ---------- */
  // estado: agendado | confirmado | en_sala | en_consulta | completado | ausente | cancelado | reagendado
  // whatsapp: confirmado | enviado | sin_respuesta | cancelado_wa | na
  let _id = 100;
  const C = [];
  function cita(o) {
    const tipo = TIPOS.find((t) => t.id === o.tipo);
    const ini = toMin(o.ini);
    C.push(Object.assign({
      id: "C" + (++_id),
      fecha: HOY,
      sede: "ceibos",
      fin: toHHMM(ini + tipo.dur),
      sobreturno: false,
      whatsapp: "na",
      horaLlegada: null, horaSala: null, horaConsulta: null, horaFin: null,
      notas: "",
    }, o, { area: tipo.area, dur: tipo.dur }));
  }

  // ---- Dra. Ramírez (Consultorio 1, consulta) ----
  cita({ ini: "08:00", medico: "m_ramirez", sala: "s_cons1", tipo: "t_postop", paciente: "María Hernández Quito", hc: "HC-92418", edad: 54, afil: "IESS",       estado: "completado", whatsapp: "confirmado", tel: "099 812 4471", horaLlegada: "07:51", horaSala: "07:58", horaConsulta: "08:02", horaFin: "08:17" });
  cita({ ini: "08:20", medico: "m_ramirez", sala: "s_cons1", tipo: "t_cons",   paciente: "Esteban Larrea Jurado", hc: "HC-12077", edad: 58, afil: "ISSPOL",     estado: "completado", whatsapp: "confirmado", tel: "098 220 1190", horaLlegada: "08:10", horaSala: "08:21", horaConsulta: "08:24", horaFin: "08:46" });
  cita({ ini: "08:40", medico: "m_ramirez", sala: "s_cons1", tipo: "t_cons",   paciente: "Camila Andrade Espinosa", hc: "HC-66510", edad: 35, afil: "Particular", estado: "en_consulta", whatsapp: "confirmado", tel: "096 771 5532", horaLlegada: "08:33", horaSala: "08:44", horaConsulta: "08:49" });
  cita({ ini: "09:00", medico: "m_ramirez", sala: "s_cons1", tipo: "t_primera",paciente: "Joaquín Rivera Bermeo", hc: "HC-55918", edad: 41, afil: "Particular", estado: "en_sala", whatsapp: "confirmado", tel: "099 044 8821", horaLlegada: "08:48", horaSala: "09:02" });
  cita({ ini: "09:30", medico: "m_ramirez", sala: "s_cons1", tipo: "t_cons",   paciente: "Rosa Vinueza Calle", hc: "HC-77231", edad: 63, afil: "IESS",       estado: "confirmado", whatsapp: "confirmado", tel: "097 612 0098" });
  cita({ ini: "09:50", medico: "m_ramirez", sala: "s_cons1", tipo: "t_postop", paciente: "Gabriel Ortiz Muñoz", hc: "HC-30910", edad: 49, afil: "ISSFA",      estado: "agendado", whatsapp: "enviado", tel: "098 339 7711" });
  cita({ ini: "10:05", medico: "m_ramirez", sala: "s_cons1", tipo: "t_cons",   paciente: "Lucía Paredes Soto", hc: "HC-41882", edad: 71, afil: "IESS",       estado: "agendado", whatsapp: "sin_respuesta", tel: "099 511 7740" });
  cita({ ini: "11:20", medico: "m_ramirez", sala: "s_cons1", tipo: "t_cons",   paciente: "Fernando Cedeño Mora", hc: "HC-88120", edad: 57, afil: "Particular", estado: "agendado", whatsapp: "enviado", tel: "096 220 1845" });
  cita({ ini: "11:40", medico: "m_ramirez", sala: "s_cons1", tipo: "t_cons",   paciente: "Andrea Suárez Lima", hc: "HC-65010", edad: 44, afil: "IESS",       estado: "agendado", whatsapp: "confirmado", tel: "098 700 1212", sobreturno: true });

  // ---- Dr. Salazar (Quirófano 1, mañana quirúrgica) ----
  cita({ ini: "08:00", medico: "m_salazar", sala: "s_qx1", tipo: "t_faco", paciente: "Luis Andrés Pérez", hc: "HC-87123", edad: 68, afil: "ISSPOL", estado: "completado", whatsapp: "confirmado", tel: "099 120 4456", horaLlegada: "07:30", horaSala: "07:55", horaConsulta: "08:05", horaFin: "08:42" });
  cita({ ini: "08:50", medico: "m_salazar", sala: "s_qx1", tipo: "t_faco", paciente: "Patricia Calle Vega", hc: "HC-44012", edad: 71, afil: "Particular", estado: "en_consulta", whatsapp: "confirmado", tel: "097 880 3321", horaLlegada: "08:15", horaSala: "08:48", horaConsulta: "08:55" });
  cita({ ini: "09:45", medico: "m_salazar", sala: "s_qx1", tipo: "t_vpp",  paciente: "Roberto Salinas Mora", hc: "HC-38291", edad: 62, afil: "ISSFA", estado: "confirmado", whatsapp: "confirmado", tel: "098 442 9087" });
  cita({ ini: "11:30", medico: "m_salazar", sala: "s_qx1", tipo: "t_faco", paciente: "Marta Cevallos Ruiz", hc: "HC-29844", edad: 66, afil: "IESS", estado: "agendado", whatsapp: "sin_respuesta", tel: "099 330 1190" });

  // ---- Dra. Veintimilla (Consultorio 2 + láser) ----
  cita({ ini: "09:00", medico: "m_veintim", sala: "s_cons2", tipo: "t_cons",  paciente: "Hugo Maldonado Reyes", hc: "HC-50231", edad: 59, afil: "IESS", estado: "completado", whatsapp: "confirmado", tel: "098 110 2245", horaLlegada: "08:50", horaSala: "08:58", horaConsulta: "09:01", horaFin: "09:19" });
  cita({ ini: "09:20", medico: "m_veintim", sala: "s_cons2", tipo: "t_cons",  paciente: "Elena Pacheco Tobar", hc: "HC-71540", edad: 67, afil: "ISSPOL", estado: "en_consulta", whatsapp: "confirmado", tel: "096 554 7781", horaLlegada: "09:12", horaSala: "09:22", horaConsulta: "09:26" });
  cita({ ini: "09:40", medico: "m_veintim", sala: "s_cons2", tipo: "t_cons",  paciente: "Diego Romero Salas", hc: "HC-33218", edad: 52, afil: "Particular", estado: "confirmado", whatsapp: "confirmado", tel: "099 778 0021" });
  cita({ ini: "10:00", medico: "m_veintim", sala: "s_laser", tipo: "t_yag",   paciente: "Sandra Quiñónez Vera", hc: "HC-60127", edad: 73, afil: "IESS", estado: "confirmado", whatsapp: "enviado", tel: "098 220 5567" });
  cita({ ini: "10:30", medico: "m_veintim", sala: "s_cons2", tipo: "t_postop",paciente: "Iván Cabrera Núñez", hc: "HC-19022", edad: 61, afil: "ISSFA", estado: "agendado", whatsapp: "enviado", tel: "097 011 3389" });
  cita({ ini: "10:50", medico: "m_veintim", sala: "s_cons2", tipo: "t_cons",  paciente: "Verónica Espín Galarza", hc: "HC-82910", edad: 48, afil: "IESS", estado: "ausente", whatsapp: "sin_respuesta", tel: "099 660 7712" });

  // ---- Dr. Vargas (Consultorio 3 + imágenes) ----
  cita({ ini: "08:30", medico: "m_vargas", sala: "s_cons3", tipo: "t_primera",paciente: "Tomás Bustamante Ríos", hc: "HC-90011", edad: 39, afil: "Particular", estado: "completado", whatsapp: "confirmado", tel: "098 442 1100", horaLlegada: "08:20", horaSala: "08:31", horaConsulta: "08:33", horaFin: "09:00" });
  cita({ ini: "09:10", medico: "m_vargas", sala: "s_cons3", tipo: "t_cons",   paciente: "Ana Lucía Cárdenas", hc: "HC-71092", edad: 47, afil: "IESS", estado: "en_sala", whatsapp: "confirmado", tel: "099 553 8820", horaLlegada: "09:02", horaSala: "09:12" });
  cita({ ini: "09:30", medico: "m_vargas", sala: "s_cons3", tipo: "t_cons",   paciente: "Pedro Jaramillo Ortega", hc: "HC-40551", edad: 55, afil: "ISSPOL", estado: "confirmado", whatsapp: "confirmado", tel: "096 220 9981" });
  cita({ ini: "10:00", medico: "m_vargas", sala: "s_img1",  tipo: "t_oct",    paciente: "Carla Mejía Andrade", hc: "HC-22871", edad: 38, afil: "Particular", estado: "agendado", whatsapp: "enviado", tel: "098 700 4412" });
  cita({ ini: "10:20", medico: "m_vargas", sala: "s_cons3", tipo: "t_cons",   paciente: "José Tenorio Vaca", hc: "HC-58410", edad: 64, afil: "IESS", estado: "agendado", whatsapp: "sin_respuesta", tel: "099 118 2230" });

  // ---- Lic. Encalada (optometría) ----
  cita({ ini: "08:00", medico: "m_encalada", sala: "s_opto", tipo: "t_opto", paciente: "Daniela Torres Ávila", hc: "HC-30481", edad: 27, afil: "Particular", estado: "completado", whatsapp: "confirmado", tel: "096 330 7711", horaLlegada: "07:55", horaSala: "08:00", horaConsulta: "08:02", horaFin: "08:31" });
  cita({ ini: "08:40", medico: "m_encalada", sala: "s_opto", tipo: "t_opto", paciente: "Nicolás Vera Campos", hc: "HC-67120", edad: 19, afil: "Particular", estado: "en_consulta", whatsapp: "confirmado", tel: "098 551 0098", horaLlegada: "08:31", horaSala: "08:41", horaConsulta: "08:45" });
  cita({ ini: "09:20", medico: "m_encalada", sala: "s_opto", tipo: "t_opto", paciente: "Mónica Salcedo Peña", hc: "HC-71880", edad: 33, afil: "IESS", estado: "confirmado", whatsapp: "confirmado", tel: "099 442 7781" });
  cita({ ini: "10:00", medico: "m_encalada", sala: "s_opto", tipo: "t_opto", paciente: "Sofía Pinto Aguilar", hc: "HC-83001", edad: 22, afil: "Particular", estado: "agendado", whatsapp: "enviado", tel: "096 110 2234" });
  cita({ ini: "11:00", medico: "m_encalada", sala: "s_opto", tipo: "t_opto", paciente: "Ricardo Lema Quezada", hc: "HC-50012", edad: 45, afil: "ISSFA", estado: "agendado", whatsapp: "sin_respuesta", tel: "098 220 5512" });

  /* ---------- Estados (catálogo) ---------- */
  const ESTADOS = [
    { id: "agendado",    label: "Agendado",     icon: "mdi-calendar-blank-outline",  tone: "info",    desc: "Cita creada, sin confirmar" },
    { id: "confirmado",  label: "Confirmado",   icon: "mdi-calendar-check-outline",  tone: "primary", desc: "Paciente confirmó asistencia" },
    { id: "en_sala",     label: "En sala",      icon: "mdi-door-open",               tone: "warning", desc: "Paciente ingresó a sala/consultorio" },
    { id: "en_consulta", label: "En consulta",  icon: "mdi-stethoscope",             tone: "warning", desc: "Atención en curso" },
    { id: "completado",  label: "Completado",   icon: "mdi-check-circle-outline",    tone: "success", desc: "Atención finalizada" },
    { id: "ausente",     label: "Ausente",      icon: "mdi-account-cancel-outline",  tone: "danger",  desc: "No se presentó (no-show)" },
    { id: "cancelado",   label: "Cancelado",    icon: "mdi-close-circle-outline",    tone: "danger",  desc: "Cita anulada" },
    { id: "reagendado",  label: "Reagendado",   icon: "mdi-calendar-sync-outline",   tone: "light",   desc: "Movida a otra fecha/hora" },
  ];

  const AFILIACIONES = ["IESS", "ISSPOL", "ISSFA", "Particular", "MSP"];

  window.AG = {
    HOY, AREAS, SEDES, MEDICOS, SALAS, TIPOS, HORARIOS, BLOQUEOS,
    CITAS: C, ESTADOS, AFILIACIONES,
    util: { toMin, toHHMM },
  };
})();
