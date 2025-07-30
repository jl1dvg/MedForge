<?php
require_once __DIR__ . '/../../../bootstrap.php';

use Helpers\PacientesHelper;

?>
<div class="box">
    <?php
    // Determinar la imagen de fondo en función del seguro
    $insurance = strtolower($patientData['afiliacion']);
    $backgroundImage = '/public/assets/logos_seguros/5.png'; // Imagen predeterminada

    $generalInsurances = [
        'contribuyente voluntario', 'conyuge', 'conyuge pensionista', 'seguro campesino', 'seguro campesino jubilado',
        'seguro general', 'seguro general jubilado', 'seguro general por montepío', 'seguro general tiempo parcial'
    ];

    foreach ($generalInsurances as $generalInsurance) {
        if (strpos($insurance, $generalInsurance) !== false) {
            $backgroundImage = '/public/assets/logos_seguros/1.png';
            break;
        }
    }

    if (strpos($insurance, 'issfa') !== false) {
        $backgroundImage = '/public/assets/logos_seguros/2.png';
    } elseif (strpos($insurance, 'isspol') !== false) {
        $backgroundImage = '/public/assets/logos_seguros/3.png';
    } elseif (strpos($insurance, 'msp') !== false) {
        $backgroundImage = '/public/assets/logos_seguros/4.png';
    }

    // Determinar la imagen del avatar en función del sexo
    $gender = strtolower($patientData['sexo']);
    $avatarImage = '/public/images/avatar/female.png'; // Imagen predeterminada

    if (strpos($gender, 'masculino') !== false) {
        $avatarImage = '/public/images/avatar/male.png';
    }
    ?>

    <div class="box-body text-end min-h-150"
         style="background-image:url(<?php echo $backgroundImage; ?>); background-repeat: no-repeat; background-position: center; background-size: cover;">
    </div>
    <div class="box-body wed-up position-relative">
        <button class="btn btn-warning mb-3" data-bs-toggle="modal"
                data-bs-target="#modalEditarPaciente">Editar Datos
        </button>
        <div class="d-md-flex align-items-center">
            <div class=" me-20 text-center text-md-start">
                <img src="<?php echo $avatarImage; ?>" style="height: 150px"
                     class="bg-success-light rounded10"
                     alt=""/>
                <div class="text-center my-10">
                    <p class="mb-0">Afiliación</p>
                    <h4><?php echo $patientData['afiliacion']; ?></h4>
                </div>
            </div>
            <div class="mt-40">
                <h4 class="fw-600 mb-5"><?php
                    echo PacientesHelper::safe($patientData['fname']) . " " . PacientesHelper::safe($patientData['mname']) . " " . PacientesHelper::safe($patientData['lname']) . " " . PacientesHelper::safe($patientData['lname2']);
                    ?></h4>
                <h5 class="fw-500 mb-5"><?php echo "C. I.: " . PacientesHelper::safe($patientData['hc_number']); ?></h5>
                <p><i class="fa fa-clock-o"></i> Edad: <?
                    echo $pacienteController->calcularEdad($patientData['fecha_nacimiento']) . " años";
                    ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <?php include __DIR__ . '/timeline_eventos.php'; ?>
    </div>
</div>
