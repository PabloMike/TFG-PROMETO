<?php
// auth.php — helper de autorización, incluir DESPUÉS de session_start()

function requiere_login() {
    if (!isset($_SESSION['id_usuario'])) {
        header("Location: login.php"); exit;
    }
}

function requiere_rol(string ...$roles) {
    requiere_login();
    if (!in_array($_SESSION['rol'] ?? '', $roles)) {
        header("Location: inicio.php"); exit;
    }
}

function es_admin():     bool { return ($_SESSION['rol'] ?? '') === 'admin'; }
function es_conductor(): bool { return ($_SESSION['rol'] ?? '') === 'conductor'; }
function es_cliente():   bool { return ($_SESSION['rol'] ?? '') === 'cliente'; }