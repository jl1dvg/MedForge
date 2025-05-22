<?php

namespace Controllers;

use Models\UserModel;
use PDO;

class UserController
{
    private UserModel $userModel;

    public function __construct(PDO $pdo)
    {
        $this->userModel = new UserModel($pdo);
    }

    public function index()
    {
        return $this->userModel->getAllUsers();
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $this->userModel->createUser($data);
            header("Location: /users");
            exit();
        }
        require __DIR__ . '/../views/users/create.php';
    }

    public function edit($id)
    {
        $user = $this->userModel->getUserById($id);
        if (!$user) {
            die("Usuario no encontrado");
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $this->userModel->updateUser($id, $data);
            header("Location: /users");
            exit();
        }
        require __DIR__ . '/../views/users/edit.php';
    }

    public function getUserModel(): UserModel
    {
        return $this->userModel;
    }
}