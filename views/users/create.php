<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\UserController;

$controller = new UserController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $controller->create();
    exit();
}
?>
<h2>Nuevo Usuario</h2>
<form method="POST">
    Usuario: <input type="text" name="username" required><br>
    Contraseña: <input type="password" name="password" required><br>
    Email: <input type="email" name="email" required><br>
    Nombre: <input type="text" name="nombre" required><br>
    Cédula: <input type="text" name="cedula" required><br>
    Registro: <input type="text" name="registro" required><br>
    Sede: <input type="text" name="sede" required><br>
    Firma (ruta): <input type="text" name="firma"><br>
    Especialidad: <input type="text" name="especialidad" required><br>
    Subespecialidad: <input type="text" name="subespecialidad"><br>
    Suscrito: <input type="checkbox" name="is_subscribed" value="1"><br>
    Aprobado: <input type="checkbox" name="is_approved" value="1"><br>
    <button type="submit">Crear Usuario</button>
</form>