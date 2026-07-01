<?php

/*
 * Catálogos y reglas de sugerencia del módulo /v2/protocolos.
 * Única fuente de verdad para el listado de categorías quirúrgicas, funciones
 * de staff, vías/responsables de kardex y las reglas deterministas de
 * "sugerencia asistida" por categoría (sin LLM: son mapeos fijos que el
 * frontend intenta calzar contra los catálogos reales de insumos/medicamentos).
 */

return [

    // Idéntico al select "categoriaQX" del formulario legacy (resources/views/protocolos/secciones/requerido.blade.php).
    'categorias' => [
        ['id' => 'Catarata',               'icon' => 'mdi-eye-outline',          'color' => '#5156be'],
        ['id' => 'Conjuntiva',             'icon' => 'mdi-eye-check-outline',    'color' => '#1f9d7a'],
        ['id' => 'Estrabismo',             'icon' => 'mdi-eye-settings-outline', 'color' => '#6f67d8'],
        ['id' => 'Glaucoma',               'icon' => 'mdi-water-outline',        'color' => '#3596f7'],
        ['id' => 'Implantes secundarios',  'icon' => 'mdi-circle-double',        'color' => '#0863be'],
        ['id' => 'Inyecciones',            'icon' => 'mdi-needle',               'color' => '#d59623'],
        ['id' => 'Oculoplastica',          'icon' => 'mdi-face-woman-outline',   'color' => '#d34b5b'],
        ['id' => 'Refractiva',             'icon' => 'mdi-glasses',              'color' => '#7c4dff'],
        ['id' => 'Retina',                 'icon' => 'mdi-eye-circle-outline',   'color' => '#05825f'],
        ['id' => 'Traumatismo Ocular',     'icon' => 'mdi-bandage',              'color' => '#ee3158'],
    ],

    // Idéntico al select "funcion" del legacy (resources/views/protocolos/secciones/staff.blade.php).
    'funciones_staff' => [
        'CIRUJANO 1', 'CIRUJANO 2', 'PRIMER AYUDANTE', 'INSTRUMENTISTA',
        'CIRCULANTE', 'ANESTESIOLOGO', 'AYUDANTE ANESTESIOLOGO',
    ],

    // Qué grupo de /v2/cirugias/staff-options (cirujanos|anestesiologos|asistentes) autocompleta cada función.
    // INSTRUMENTISTA y CIRCULANTE no tienen especialidad equivalente en users.especialidad, quedan en texto libre.
    'funcion_especialidad' => [
        'CIRUJANO 1'              => 'cirujanos',
        'CIRUJANO 2'              => 'cirujanos',
        'PRIMER AYUDANTE'         => 'asistentes',
        'ANESTESIOLOGO'           => 'anestesiologos',
        'AYUDANTE ANESTESIOLOGO'  => 'anestesiologos',
    ],

    'vias' => ['Tópica', 'Subconjuntival', 'Intravítrea', 'Intravenosa', 'Intramuscular', 'Oral', 'Peribulbar'],

    'responsables' => ['Enfermería', 'Anestesiología', 'Cirujano', 'Circulante'],

    // Plantillas base del paso "Inicio". El código se resuelve en vivo contra tarifario_2014
    // (GET /v2/cirugias/search-procedimientos) para no repetir la descripción aquí.
    'plantillas_base' => [
        [
            'id' => 'faco', 'nombre' => 'Facoemulsificación con LIO', 'categoria' => 'Catarata',
            'descripcion' => 'Catarata senil no complicada. Incluye técnica, kardex e insumos estándar.',
            'icon' => 'mdi-eye-outline', 'codigo' => '66984',
            'data' => [
                'cirugia' => 'faco', 'membrete' => 'Facoemulsificación de catarata + LIO', 'categoria' => 'Catarata', 'horas' => '0.75',
                'dieresis' => 'Córnea clara, incisión de 2.75 mm', 'exposicion' => 'Blefarostato', 'hallazgo' => 'Catarata nuclear',
            ],
        ],
        [
            'id' => 'pterigion', 'nombre' => 'Pterigión con autoinjerto', 'categoria' => 'Conjuntiva',
            'descripcion' => 'Escisión de pterigión primario con autoinjerto conjuntival libre.',
            'icon' => 'mdi-eye-check-outline', 'codigo' => '65426',
            'data' => [
                'cirugia' => 'pterigion', 'membrete' => 'Escisión de pterigión + autoinjerto conjuntival', 'categoria' => 'Conjuntiva', 'horas' => '0.5',
                'dieresis' => 'Disección lamelar limbo-corneal', 'exposicion' => 'Blefarostato', 'hallazgo' => 'Pterigión nasal grado II',
            ],
        ],
        [
            'id' => 'avastin', 'nombre' => 'Inyección intravítrea', 'categoria' => 'Inyecciones',
            'descripcion' => 'Aplicación de antiangiogénico intravítreo en quirófano.',
            'icon' => 'mdi-needle', 'codigo' => '67028',
            'data' => [
                'cirugia' => 'avastin', 'membrete' => 'Inyección intravítrea de antiangiogénico', 'categoria' => 'Inyecciones', 'horas' => '0.25',
                'dieresis' => 'No aplica', 'exposicion' => 'Blefarostato', 'hallazgo' => 'Edema macular',
            ],
        ],
        [
            'id' => 'vpp', 'nombre' => 'Vitrectomía pars plana', 'categoria' => 'Retina',
            'descripcion' => 'VPP 25 G con endoláser y tamponamiento según hallazgos.',
            'icon' => 'mdi-eye-circle-outline', 'codigo' => '67036',
            'data' => [
                'cirugia' => 'vpp', 'membrete' => 'Vitrectomía vía pars plana 25 G', 'categoria' => 'Retina', 'horas' => '1.5',
                'dieresis' => 'Esclerotomías pars plana 25 G', 'exposicion' => 'Blefarostato', 'hallazgo' => 'Desprendimiento de retina',
            ],
        ],
    ],

    // "IA" = reglas deterministas, sin LLM. El equipo típico se resuelve contra /v2/cirugias/staff-options
    // (primer nombre disponible de cada grupo); no depende de nombres hardcodeados.
    'sugerencias_staff' => [
        'default' => [
            ['funcion' => 'CIRUJANO 1', 'grupo' => 'cirujanos'],
            ['funcion' => 'INSTRUMENTISTA'],
            ['funcion' => 'CIRCULANTE'],
            ['funcion' => 'ANESTESIOLOGO', 'grupo' => 'anestesiologos'],
        ],
    ],

    // "match" se busca (contains, case-insensitive) contra insumosDisponibles[*].nombre reales.
    // Si no hay coincidencia para una categoría, no se agrega esa fila (no se inventan insumos).
    'sugerencias_insumos' => [
        'Catarata' => [
            ['match' => 'LIO monofocal', 'cantidad' => 1],
            ['match' => 'Hialuronato de sodio', 'cantidad' => 1],
            ['match' => 'Cuchillete 2.75', 'cantidad' => 1],
            ['match' => 'Cuchillete 15', 'cantidad' => 1],
            ['match' => 'Solución BSS', 'cantidad' => 1],
            ['match' => 'Esponja de celulosa', 'cantidad' => 4],
        ],
        'Conjuntiva' => [
            ['match' => 'Vicryl 8-0', 'cantidad' => 1],
            ['match' => 'Esponja de celulosa', 'cantidad' => 4],
            ['match' => 'Bisturí crescente', 'cantidad' => 1],
        ],
        'Retina' => [
            ['match' => 'Trócares valvulados', 'cantidad' => 1],
            ['match' => 'Gas SF6', 'cantidad' => 1],
            ['match' => 'Solución BSS', 'cantidad' => 2],
        ],
        'Inyecciones' => [
            ['match' => 'Jeringa 1 ml', 'cantidad' => 1],
            ['match' => 'Aguja 30 G', 'cantidad' => 1],
            ['match' => 'Blefarostato', 'cantidad' => 1],
        ],
        'Glaucoma' => [
            ['match' => 'Nylon 10-0', 'cantidad' => 1],
            ['match' => 'Viscoelástico cohesivo', 'cantidad' => 1],
            ['match' => 'Esponja de celulosa', 'cantidad' => 3],
        ],
        'default' => [
            ['match' => 'Gasa estéril', 'cantidad' => 4],
            ['match' => 'Campo quirúrgico', 'cantidad' => 1],
        ],
    ],

    // "match" se busca contra opcionesMedicamentos[*].medicamento reales.
    'sugerencias_medicamentos' => [
        'Catarata' => [
            ['match' => 'Moxifloxacino', 'dosis' => '1 gota', 'frecuencia' => 'c/6 h', 'via' => 'Tópica', 'responsable' => 'Enfermería'],
            ['match' => 'Prednisolona', 'dosis' => '1 gota', 'frecuencia' => 'c/6 h', 'via' => 'Tópica', 'responsable' => 'Enfermería'],
            ['match' => 'Ketorolaco 0.5', 'dosis' => '1 gota', 'frecuencia' => 'c/8 h', 'via' => 'Tópica', 'responsable' => 'Enfermería'],
        ],
        'Inyecciones' => [
            ['match' => 'Moxifloxacino', 'dosis' => '1 gota', 'frecuencia' => 'c/6 h', 'via' => 'Tópica', 'responsable' => 'Enfermería'],
        ],
        'default' => [
            ['match' => 'Moxifloxacino', 'dosis' => '1 gota', 'frecuencia' => 'c/6 h', 'via' => 'Tópica', 'responsable' => 'Enfermería'],
            ['match' => 'Ketorolaco 30 mg', 'dosis' => '1 ampolla', 'frecuencia' => 'Dosis única', 'via' => 'Intramuscular', 'responsable' => 'Anestesiología'],
        ],
    ],

    'operatorio_sugerido' => [
        'Catarata' => 'Bajo anestesia tópica y sedación, previa asepsia y antisepsia del campo quirúrgico, se coloca blefarostato. Se realiza paracentesis de servicio e incisión principal en córnea clara de 2.75 mm. Se inyecta viscoelástico en cámara anterior. Capsulorrexis circular continua. Hidrodisección e hidrodelaminación. Facoemulsificación del núcleo mediante técnica de división. Aspiración de masas corticales con sistema de irrigación/aspiración. Se implanta lente intraocular plegable en saco capsular. Se retira viscoelástico. Se hidrata la incisión y se comprueba su hermeticidad. Se aplica antibiótico intracameral.',
        'Conjuntiva' => 'Bajo anestesia tópica y subconjuntival, previa asepsia y antisepsia, se coloca blefarostato. Se delimita la cabeza del pterigión y se diseca del limbo hacia la córnea. Escisión del cuerpo del pterigión y tejido de Tenon subyacente. Se obtiene autoinjerto conjuntival del cuadrante superior. Se posiciona el injerto sobre el lecho escleral respetando la orientación limbo-limbo y se fija con suturas / adhesivo de fibrina. Revisión de hemostasia.',
        'Retina' => 'Bajo anestesia peribulbar, previa asepsia y antisepsia, se colocan trócares valvulados de 25 G en pars plana a 3.5–4 mm del limbo. Se instala línea de infusión. Vitrectomía central y periférica. Inducción de desprendimiento de vítreo posterior. Se completa la disección de membranas. Endoláser sobre desgarros/áreas isquémicas. Intercambio fluido-aire y tamponamiento según hallazgos. Retiro de trócares y verificación de hermeticidad de esclerotomías.',
        'default' => 'Bajo anestesia, previa asepsia y antisepsia del campo quirúrgico, se procede al abordaje planificado. Se ejecutan los tiempos quirúrgicos según técnica. Se verifica hemostasia y cierre por planos. Se aplica apósito estéril.',
    ],

];
