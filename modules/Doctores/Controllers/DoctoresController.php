<?php

namespace Modules\Doctores\Controllers;

use Core\BaseController;
use Modules\Doctores\Models\DoctorModel;
use PDO;

class DoctoresController extends BaseController
{
    private DoctorModel $doctors;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->doctors = new DoctorModel($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $doctors = $this->doctors->all();

        $this->render(BASE_PATH . '/modules/Doctores/views/index.php', [
            'pageTitle' => 'Doctores',
            'doctors' => $doctors,
            'totalDoctors' => count($doctors),
        ]);
    }

    public function show(int $doctorId): void
    {
        $this->requireAuth();

        $doctor = $this->doctors->find($doctorId);
        if ($doctor === null) {
            header('Location: /doctores');
            exit;
        }

        $this->render(BASE_PATH . '/modules/Doctores/views/show.php', [
            'pageTitle' => $doctor['display_name'] ?? $doctor['name'] ?? 'Doctor',
            'doctor' => $doctor,
        ]);
    }
}
