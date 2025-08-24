<?php
session_start();
include 'protect.php';
include 'conexao.php';

// ID do usuário logado
$id = $_SESSION['id'];

// Se veio via POST, processa os dados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Trata upload da foto de perfil
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        // Garante que a pasta uploads/ existe
        if (!is_dir(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0755, true);
        }
        // Salva sempre como {id}.jpg
        $dest = __DIR__ . "/uploads/{$id}.jpg";
        move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $dest);
    }

    // 2) Atualiza nome e localização no banco
    $nome = trim($_POST['nome'] ?? '');
    $loc  = trim($_POST['localizacao'] ?? '');

    // Garante que exista a coluna localizacao na tabela usuarios
    $mysqli->query("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS localizacao VARCHAR(100) NULL") or null;

    $stmt = $mysqli->prepare("UPDATE usuarios SET nome = ?, localizacao = ? WHERE id = ?");
    $stmt->bind_param('ssi', $nome, $loc, $id);
    $stmt->execute();
    $stmt->close();

    // 3) Atualiza sessão para refletir mudanças imediatamente
    $_SESSION['nome'] = $nome;
    $_SESSION['localizacao'] = $loc;
}

// Redireciona de volta para o painel
header('Location: painel.php');
exit;
