<?php
include('conexao.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senha2 = $_POST['senha2'] ?? '';

    if (empty($nome) || empty($email) || empty($senha)) {
        echo "Dados incompletos.";
        exit;
    } else if ($senha !== $senha2){
        echo "Senhas não coincidem.";
        exit;
    }

    $consulta = $mysqli->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $consulta->bind_param('s', $email);
    $consulta->execute();
    $consulta->store_result();

    if ($consulta->num_rows > 0) {
        echo "E-mail já cadastrado.";
    } else {
        $criptografada = password_hash($senha, PASSWORD_DEFAULT);
        $salvar = $mysqli->prepare("INSERT INTO usuarios (nome, usuario, senha) VALUES (?, ?, ?)");
        $salvar->bind_param('sss', $nome, $email, $criptografada);

        if ($salvar->execute()) {
            echo "Cadastro concluído com sucesso!";
        } else {
            echo "Erro ao salvar: " . $mysqli->error;
        }
    }

    $consulta->close();
}
// Normaliza a resposta para evitar problemas com acento, espaços, etc.
//respostaNormalizada = texto.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
?>



