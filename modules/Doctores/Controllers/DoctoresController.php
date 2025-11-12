<?php

namespace Modules\Doctores\Controllers;

use Core\BaseController;
use PDO;

class DoctoresController extends BaseController
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $patientsToday = [
            [
                'time' => '10:30 AM',
                'name' => 'Sarah Hostemn',
                'diagnosis' => 'Bronquitis',
                'avatar' => 'images/avatar/1.jpg',
            ],
            [
                'time' => '11:00 AM',
                'name' => 'Dakota Smith',
                'diagnosis' => 'Accidente cerebrovascular',
                'avatar' => 'images/avatar/2.jpg',
            ],
            [
                'time' => '11:30 AM',
                'name' => 'John Lane',
                'diagnosis' => 'Cirrosis hepática',
                'avatar' => 'images/avatar/3.jpg',
            ],
        ];

        $appointmentsCalendar = [
            ['type' => 'nav', 'direction' => 'prev'],
            ['type' => 'day', 'date' => '2025-11-07', 'weekday' => 'Fri', 'day_label' => '7th'],
            ['type' => 'day', 'date' => '2025-11-08', 'weekday' => 'Sat', 'day_label' => '8th', 'disabled' => true],
            ['type' => 'day', 'date' => '2025-11-09', 'weekday' => 'Sun', 'day_label' => '9th', 'disabled' => true],
            ['type' => 'day', 'date' => '2025-11-10', 'weekday' => 'Mon', 'day_label' => '10th', 'divider' => true],
            [
                'type' => 'day',
                'date' => '2025-11-11',
                'weekday' => 'Tue',
                'weekday_full' => 'Tuesday',
                'day_label' => '11th',
                'month_label' => 'November 11th 2025',
                'wide' => true,
                'active' => true,
                'is_today' => true,
            ],
            ['type' => 'day', 'date' => '2025-11-12', 'weekday' => 'Wed', 'day_label' => '12th'],
            ['type' => 'day', 'date' => '2025-11-13', 'weekday' => 'Thu', 'day_label' => '13th'],
            ['type' => 'day', 'date' => '2025-11-14', 'weekday' => 'Fri', 'day_label' => '14th'],
            ['type' => 'nav', 'direction' => 'next'],
        ];

        $appointments = [
            [
                'name' => 'Shawn Hampton',
                'note' => 'Cita de emergencia',
                'time' => '10:00',
                'price' => 30,
                'avatar' => 'images/avatar/avatar-1.png',
            ],
            [
                'name' => 'Polly Paul',
                'note' => 'USG + Consulta',
                'time' => '10:30',
                'price' => 50,
                'avatar' => 'images/avatar/avatar-2.png',
            ],
            [
                'name' => 'Johen Doe',
                'note' => 'Exámenes de laboratorio',
                'time' => '11:00',
                'price' => 70,
                'avatar' => 'images/avatar/avatar-3.png',
            ],
            [
                'name' => 'Harmani Doe',
                'note' => 'Control de embarazo',
                'time' => '11:30',
                'price' => null,
                'avatar' => 'images/avatar/avatar-4.png',
            ],
            [
                'name' => 'Mark Wood',
                'note' => 'Consulta general',
                'time' => '12:00',
                'price' => 30,
                'avatar' => 'images/avatar/avatar-5.png',
            ],
            [
                'name' => 'Shawn Marsh',
                'note' => 'Cita de emergencia',
                'time' => '13:00',
                'price' => 90,
                'avatar' => 'images/avatar/avatar-6.png',
            ],
        ];

        $abilities = [
            ['label' => 'Operaciones', 'value' => 44, 'color' => '#3246d3'],
            ['label' => 'Terapia', 'value' => 55, 'color' => '#00d0ff'],
            ['label' => 'Medicamentos', 'value' => 41, 'color' => '#ee3158'],
            ['label' => 'Colesterol', 'value' => 17, 'color' => '#ffa800'],
            ['label' => 'Frecuencia cardiaca', 'value' => 15, 'color' => '#05825f'],
        ];

        $recoveryRates = [
            ['label' => 'Resfriado', 'percentage' => 80, 'class' => 'primary'],
            ['label' => 'Fractura', 'percentage' => 24, 'class' => 'success'],
            ['label' => 'Dolor', 'percentage' => 91, 'class' => 'info'],
            ['label' => 'Hematoma', 'percentage' => 50, 'class' => 'danger'],
            ['label' => 'Caries', 'percentage' => 72, 'class' => 'warning'],
        ];

        $doctorProfile = [
            'name' => 'Dr. Johen Doe',
            'specialty' => 'Otorrinolaringólogo',
            'cover_image' => 'images/gallery/landscape14.jpg',
            'photo' => 'images/avatar/avatar-1.png',
            'joined_at' => '15 de mayo de 2019, 10:00 AM',
            'biography' => [
                'Vestibulum tincidunt sit amet sapien et eleifend. Fusce pretium libero enim, nec lacinia est ultrices id. Duis nibh sapien, ultrices in hendrerit ac, pulvinar ut mauris. Quisque eu condimentum justo. In consectetur dapibus justo, et dapibus augue pellentesque sed.',
                'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.',
            ],
        ];

        $assignedPatient = [
            'name' => 'Loky Doe',
            'condition' => 'Resfriado y gripe',
            'photo' => 'images/avatar/1.jpg',
            'trend' => [69, 70, 60, 60, 61, 0, 46, 42, 61, 49, 72, 64, 52],
            'trend_labels' => ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00'],
            'improvement' => 10,
        ];

        $reviews = [
            [
                'name' => 'Theron Trump',
                'avatar' => 'images/avatar/1.jpg',
                'since' => 'Hace 2 días',
                'rating' => 4,
                'comment' => 'Vestibulum tincidunt sit amet sapien et eleifend. Fusce pretium libero enim, nec lacinia est ultrices id. Duis nibh sapien, ultrices en hendrerit ac, pulvinar ut mauris. Quisque eu condimentum justo.',
            ],
            [
                'name' => 'Johen Doe',
                'avatar' => 'images/avatar/3.jpg',
                'since' => 'Hace 5 días',
                'rating' => 4.5,
                'comment' => 'Praesent venenatis viverra turpis quis varius. Nullam ullamcorper congue urna, in sodales eros placerat non.',
            ],
            [
                'name' => 'Tyler Mark',
                'avatar' => 'images/avatar/4.jpg',
                'since' => 'Hace 7 días',
                'rating' => 5,
                'comment' => 'Pellentesque a pretium orci. In hac habitasse platea dictumst. Nulla mattis odio enim, id euismod neque bibendum non.',
            ],
            [
                'name' => 'Theron Trump',
                'avatar' => 'images/avatar/5.jpg',
                'since' => 'Hace 9 días',
                'rating' => 4.5,
                'comment' => 'Curabitur condimentum molestie ligula iaculis euismod. Fusce nulla lectus, tincidunt eu consequat.',
            ],
            [
                'name' => 'Johen Doe',
                'avatar' => 'images/avatar/6.jpg',
                'since' => 'Hace 12 días',
                'rating' => 4,
                'comment' => 'Proin lacinia eleifend nulla eu ornare. Integer commodo elit purus. Suspendisse mattis gravida interdum.',
            ],
        ];

        $questions = [
            [
                'date' => '14 Jun 2021',
                'time' => '01:05 PM',
                'title' => '¿Adicción al banco de sangre y desinfectantes contagiosos?',
            ],
            [
                'date' => '17 Jun 2021',
                'time' => '02:05 PM',
                'title' => '¿Asma desencadenada y compatibilidad sanguínea para anestesia?',
            ],
            [
                'date' => '18 Jun 2021',
                'time' => '09:15 AM',
                'title' => '¿Recomendaciones para rehabilitación post operatoria?',
            ],
            [
                'date' => '19 Jun 2021',
                'time' => '11:45 AM',
                'title' => '¿Indicaciones para pacientes con alergias múltiples?',
            ],
        ];

        $labTests = [
            [
                'patient' => 'Johen Doe',
                'title' => 'Control prenatal',
                'test' => 'Prueba PRGA',
                'avatar' => 'images/avatar/avatar-1.png',
            ],
            [
                'patient' => 'Polly Paul',
                'title' => 'USG + Consulta',
                'test' => 'Marcadores',
                'avatar' => 'images/avatar/avatar-2.png',
            ],
            [
                'patient' => 'Shawn Hampton',
                'title' => 'Beta 2 Microglobulina',
                'test' => 'Marcadores',
                'avatar' => 'images/avatar/avatar-3.png',
            ],
        ];

        $this->render(
            __DIR__ . '/../views/index.php',
            [
                'pageTitle' => 'Doctores',
                'patientsToday' => $patientsToday,
                'appointmentsCalendar' => $appointmentsCalendar,
                'appointments' => $appointments,
                'abilities' => $abilities,
                'recoveryRates' => $recoveryRates,
                'doctorProfile' => $doctorProfile,
                'assignedPatient' => $assignedPatient,
                'reviews' => $reviews,
                'questions' => $questions,
                'labTests' => $labTests,
            ]
        );
    }
}
