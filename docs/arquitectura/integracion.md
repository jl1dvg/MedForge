# Integración del contexto de pacientes

Este documento describe el contrato de `Modules\Shared\Services\PatientContextService` y las reglas
para consumirlo desde los módulos funcionales.

## Servicio `PatientContextService`

```php
use Modules\Shared\Services\PatientContextService;

$service = new PatientContextService($pdo);
$context = $service->getContext('HC-001');
```

* El parámetro es siempre el número de historia clínica normalizado (`hc_number`).
* El retorno es un arreglo asociativo con la siguiente forma:

```php
[
    'hc_number' => 'HC-001',
    'clinic' => [
        'patient' => [
            'hc_number' => 'HC-001',
            'full_name' => 'Nombre completo',
            'fname' => 'Nombre',
            'mname' => 'Segundo nombre',
            'lname' => 'Apellido',
            'lname2' => 'Segundo apellido',
            'afiliacion' => 'IESS',
            'celular' => '+593...',
            'telefono' => '02...',
            'email' => 'correo@example.com',
            'cedula' => '1101...',
            'ciudad' => 'Quito',
            'fecha_nacimiento' => '1985-06-15',
        ]
    ],
    'crm' => [
        'customers' => [...],       // listado completo de coincidencias
        'primary_customer' => [...],// coincidencia prioritaria
        'leads' => [...],
        'primary_lead' => [...],
    ],
    'communications' => [
        'conversations' => [...],        // histórico de conversaciones de WhatsApp
        'primary_conversation' => [...], // conversación prioritaria
    ],
]
```

* El servicio maneja caches internas por `hc_number` para reducir viajes a BD.
* Ante tablas inexistentes (por ejemplo en un entorno de prueba), el servicio regresa
  estructuras vacías sin lanzar excepciones.

## Uso en Agenda

Los modelos `Models\Agenda\ProcedimientoProyectado` y `Models\Agenda\Visita` reciben el
servicio y exponen relaciones tipo `belongsTo` a paciente y cliente CRM. Se debe trabajar
con sus métodos en vez de acceder a columnas manualmente:

```php
$procedimiento->getPacienteNombre();
$procedimiento->patient();       // datos clínicos
$procedimiento->customer();      // cliente CRM principal
$procedimiento->communications();// resúmenes de conversaciones
```

Las vistas de Agenda consumen estas APIs y no deben realizar consultas adicionales sobre
`patient_data`.

## Uso en Cirugías

`Modules\Cirugias\Services\CirugiaService` adjunta el contexto al modelo `Cirugia`. El
modelo publica:

```php
$cirugia->getPatientContext();
$cirugia->patient();
$cirugia->customer();
```

Esto garantiza que toda la información clínica, de CRM y de comunicaciones provenga del
mismo contrato.

## Uso en comunicaciones (WhatsApp / CRM)

`Modules\Solicitudes\Services\SolicitudCrmService` utiliza `PatientContextService` para:

* Construir el listado de teléfonos destino (`patient`, `customer`, `wa_number`).
* Normalizar el encabezado de los mensajes con el nombre unificado del paciente.

Cualquier flujo de notificación adicional debe reutilizar este servicio antes de enviar
mensajes para asegurar consistencia.

## Buenas prácticas

* Nunca ejecutar consultas directas a `patient_data` cuando se disponga del servicio.
* Cachear el resultado por ciclo de petición cuando se generen múltiples consumos.
* Validar siempre que el `hc_number` esté presente antes de solicitar el contexto.
* Las pruebas deben ejercitar los tres orígenes (clínica, CRM y comunicaciones).
