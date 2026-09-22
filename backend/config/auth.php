<?php
session_start();

function requireLogin(): int {
    if (!isset($_SESSION['usuario_id'])) {
        jsonResponse(['erro' => 'Não autenticado.'], 401);
    }
    return (int) $_SESSION['usuario_id'];
}

function currentUser(): ?array {
    if (!isset($_SESSION['usuario_id'])) return null;
    return [
        'id' => (int)$_SESSION['usuario_id'],
        'nome' => $_SESSION['usuario_nome'] ?? '',
        'tipo' => $_SESSION['usuario_tipo'] ?? 'aluno'
    ];
}

function requireProfessor(): int {
    $id = requireLogin();
    if (($_SESSION['usuario_tipo'] ?? '') !== 'professor') {
        jsonResponse(['erro' => 'Acesso permitido apenas para professores.'], 403);
    }
    return $id;
}
?>
