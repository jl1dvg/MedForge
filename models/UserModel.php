<?php

namespace Models;

use PDO;

class UserModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAllUsers()
    {
        $stmt = $this->db->query("SELECT * FROM users");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createUser($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (username, password, email, is_subscribed, is_approved, nombre, cedula, registro, sede, firma, especialidad, subespecialidad)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['email'],
            $data['is_subscribed'],
            $data['is_approved'],
            $data['nombre'],
            $data['cedula'],
            $data['registro'],
            $data['sede'],
            $data['firma'],
            $data['especialidad'],
            $data['subespecialidad']
        ]);
    }

    public function updateUser($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE users SET username=?, email=?, is_subscribed=?, is_approved=?, nombre=?, cedula=?, registro=?, sede=?, firma=?, especialidad=?, subespecialidad=?
            WHERE id=?
        ");
        return $stmt->execute([
            $data['username'],
            $data['email'],
            $data['is_subscribed'],
            $data['is_approved'],
            $data['nombre'],
            $data['cedula'],
            $data['registro'],
            $data['sede'],
            $data['firma'],
            $data['especialidad'],
            $data['subespecialidad'],
            $id
        ]);
    }
}