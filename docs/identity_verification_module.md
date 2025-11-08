# Guía del módulo de Certificación Biométrica de Pacientes

Esta guía describe cómo habilitar y utilizar el módulo **IdentityVerification** incluido en MedForge para certificar la identidad de los pacientes mediante firmas manuscritas y verificación facial. También se detallan los motores biométricos utilizados y la forma en que el módulo se vincula con la tabla `patient_data`.

## 1. Requisitos previos

1. **Migraciones ejecutadas**: Aplique el script [`database/migrations/20241201_patient_identity_verification.sql`](../database/migrations/20241201_patient_identity_verification.sql) en su base de datos MySQL/MariaDB. Esto crea las tablas:
   - `patient_identity_certifications` (registros maestros de cada paciente)
   - `patient_identity_checkins` (historial de verificaciones)

   Además, se establecen las llaves foráneas necesarias y se registran los permisos `pacientes.verification.view` y `pacientes.verification.manage`.

2. **Permisos de usuario**: Asigne los permisos anteriores a los roles que deban administrar certificaciones. El script de migración añade automáticamente los permisos a roles administrativos (`administrativo` y `superuser`) si existen.

3. **Extensión GD de PHP**: Para generar plantillas biométricas vectorizadas, el módulo requiere la extensión `gd` habilitada. Si no está disponible, el sistema utilizará un modo de respaldo basado en `SHA-256`.

4. **Estructura de archivos**: Verifique que el directorio `storage/identity_verification/` sea escribible. El controlador creará subcarpetas para firmas, documentos y rostros cuando sea necesario.

## 2. Vinculación con `patient_data`

La columna `patient_identity_certifications.patient_id` mantiene una relación directa con `patient_data.hc_number` mediante la restricción `FOREIGN KEY fk_patient_identity_certifications_patient`. Esto garantiza que solo puedan certificarse pacientes que existan en la tabla maestra.

En tiempo de ejecución, el controlador [`VerificationController`](../modules/IdentityVerification/Controllers/VerificationController.php) realiza los siguientes pasos:

1. **Normalización del identificador**: El método `normalizePatientId()` elimina espacios, convierte a mayúsculas y valida el formato del número de historia clínica antes de cualquier operación.
2. **Consulta del paciente**: `VerificationModel::findPatientSummary()` consulta `patient_data` para mostrar en la interfaz datos clave (nombres, cédula, afiliación). Si la cédula no se proporciona en el formulario, se toma automáticamente de `patient_data`.
3. **Restricción de escritura**: Al crear o actualizar certificaciones, `VerificationModel::create()` y `VerificationModel::update()` envían `patient_id` al motor SQL; si el paciente no existe, la transacción falla por la llave foránea.
4. **Actualización en cascada**: Cambios sobre `patient_data.hc_number` se propagan a `patient_identity_certifications` gracias a la opción `ON UPDATE CASCADE`. Al eliminar un paciente, la certificación se elimina (`ON DELETE CASCADE`).

## 3. Motores de reconocimiento biométrico

El módulo implementa motores ligeros escritos en PHP orientados a la comparación vectorial de firmas y rostros. Ambos servicios viven en `modules/IdentityVerification/Services/`.

### 3.1 Reconocimiento facial (`FaceRecognitionService`)

- **Vectorización**: Convierte la captura facial en una matriz de 32×32 píxeles, calcula luminancia en escala de grises y normaliza el vector resultante.
- **Comparación**: Utiliza similitud coseno (producto punto normalizado) para obtener un puntaje de 0 a 100.
- **Modo de respaldo**: Si la extensión `gd` no está disponible o la imagen es inválida, se almacena únicamente el hash `SHA-256` del binario. En ese caso, las comparaciones devuelven 100 solo si los hashes coinciden exactamente.

### 3.2 Reconocimiento de firmas (`SignatureAnalysisService`)

- **Vectorización**: Reescala la imagen a 64×32 px preservando transparencia, calcula luminancia ponderada y multiplica por la opacidad para enfatizar trazos.
- **Comparación**: Implementa distancia absoluta promedio (Manhattan) y la transforma en un puntaje de coincidencia de 0 a 100.
- **Modo de respaldo**: Igual que el motor facial, se recurre a un hash `SHA-256` cuando `gd` no está disponible o el recurso es inválido.

Los vectores resultantes se almacenan en formato JSON dentro de `signature_template` y `face_template`. Esto permite re-calcular puntajes en verificaciones posteriores sin necesidad de conservar únicamente la imagen original.

## 4. Flujo de trabajo en la interfaz

La vista principal del módulo se encuentra en [`modules/IdentityVerification/views/index.php`](../modules/IdentityVerification/views/index.php) y el comportamiento cliente en [`public/js/modules/patient-verification.js`](../public/js/modules/patient-verification.js).

1. **Búsqueda del paciente**: Ingrese el número de historia clínica (`patient_id`). El sistema trae automáticamente los datos de `patient_data` si existe.
2. **Captura de firmas**:
   - Firma manuscrita directa mediante lienzo HTML5.
   - Firma extraída de la cédula (subida de imagen o archivo PDF convertido).
3. **Captura facial**: Puede tomarse una fotografía desde la cámara o subir una imagen existente.
4. **Documentos de identidad**: Opcionalmente se sube el anverso y reverso de la cédula para auditoría.
5. **Almacenamiento**: Al guardar, el módulo crea o actualiza el registro en `patient_identity_certifications`, genera las plantillas biométricas y marca la certificación como `verified`.
6. **Historial**: Cada verificación posterior puede registrarse mediante `VerificationModel::logCheckin()` para conservar puntajes y resultados (`approved`, `rejected`, `manual_review`).

## 5. Buenas prácticas operativas

- Valide que los pacientes actualicen su cédula en `patient_data` antes de iniciar la captura biométrica para evitar rechazos por inconsistencia documental.
- Mantenga políticas de retención acordes a la legislación local sobre datos biométricos; el directorio `storage/identity_verification/` puede configurarse con rotaciones o cifrado.
- Genere procedimientos de revisión manual cuando los puntajes se sitúen por debajo de un umbral aceptable (por ejemplo, 75/100).
- Registre capacitaciones al personal administrativo para garantizar capturas limpias (buena iluminación, documentos legibles, etc.).

## 6. Solución de problemas

| Problema | Posible causa | Acción recomendada |
| --- | --- | --- |
| No aparece el paciente al ingresar la historia clínica | El registro no existe en `patient_data` | Crear el paciente en el módulo maestro antes de certificar |
| Error de base de datos al guardar | La llave foránea detectó un `patient_id` inexistente | Revise que la historia clínica esté escrita correctamente y exista |
| Puntajes muy bajos en verificaciones posteriores | Imágenes borrosas o capturas con mala iluminación | Repetir la certificación con mejores condiciones de captura |
| Plantillas quedan en blanco | Extensión `gd` deshabilitada | Instalar/activar `php-gd` para habilitar los motores vectoriales |

Con esta documentación podrá operar el módulo de certificación biométrica y comprender sus componentes técnicos principales.
