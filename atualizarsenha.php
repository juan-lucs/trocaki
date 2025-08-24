<?php
include("conexao.php");
session_start();

// Obtém email da sessão
$email = trim($_POST["email"] ?? '');
$novaSenha = trim($_POST["senha"] ?? '');
$confirmarSenha = trim($_POST["confirmar_senha"] ?? '');

if (empty($novaSenha) || empty($confirmarSenha)) {
    echo "Preencha todos os campos.";
    exit;
}

if ($novaSenha !== $confirmarSenha) {
    echo "As senhas não coincidem.";
    exit;
}

// Criptografa a nova senha
$criptografada = password_hash($novaSenha, PASSWORD_DEFAULT);

// Verifica se o usuário existe
$consulta = $mysqli->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$consulta->bind_param('s', $email);
$consulta->execute();
$resultado = $consulta->get_result();

if ($resultado->num_rows === 1) {
    // Usuário existe, atualiza a senha
    $salvar = $mysqli->prepare("UPDATE usuarios SET senha = ? WHERE usuario = ?");
    $salvar->bind_param('ss', $criptografada, $email);
    if ($salvar->execute()) {
        echo "Senha atualizada com sucesso.";
    } else {
        echo "Erro ao atualizar a senha.";
    }
} else {
    echo "Usuário não encontrado.";
}
?>