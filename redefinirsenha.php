<?php
//redefinirsenha.php
session_start();
require_once "conexao.php"; 

$novaSenha      = trim($_POST['senha'] ?? '');
$confirmarSenha = trim($_POST['confirmar_senha'] ?? '');
$email          = $_SESSION['email_verificado'] ?? '';

if ($novaSenha === '' || $confirmarSenha === '') {
    echo "Preencha todos os campos.";
    exit;
}
if ($novaSenha !== $confirmarSenha) {
    echo "As senhas não coincidem.";
    exit;
}
if (!$email) {
    echo "E-mail não encontrado.";
    exit;
}

$senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

// **USE $mysqli e não $conexao**
$stmt = $mysqli->prepare("UPDATE usuarios SET senha = ? WHERE usuario = ?");
$stmt->bind_param("ss", $senhaHash, $email);
if ($stmt->execute()) {
    echo "Senha redefinida com sucesso!";
    session_destroy();
} else {
    echo "Erro ao atualizar senha.";
}
