<?php
// salvar_video.php
// Ajuste do fuso horário (opcional)
date_default_timezone_set('Asia/Manila');

// Inclui a conexão com o banco
require_once 'conexao.php';

if (isset($_POST['save'])) {
    // Dados do upload
    $file_name = $_FILES['video']['name'];
    $file_temp = $_FILES['video']['tmp_name'];
    $file_size = $_FILES['video']['size'];

    // Define o diretório de upload usando caminho absoluto
    $upload_dir = __DIR__ . '/video/';

    // Se não existir, tenta criar (0755 e true para criar recursivamente)
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            die("Não foi possível criar a pasta de upload: $upload_dir");
        }
    }

    // Verifica tamanho (menos de 50 MB)
    if ($file_size < 50000000) {
        // Extrai a extensão em minúsculo
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['avi', 'flv', 'wmv', 'mov', 'mp4'];

        if (in_array($ext, $allowed_ext)) {
            // Gera um nome único e monta o novo caminho
            $name     = date("Ymd") . time();
            $new_path = $upload_dir . $name . "." . $ext;

            // Move o arquivo do tmp para a pasta 'video'
            if (move_uploaded_file($file_temp, $new_path)) {
                // Insere no banco — armazenamos apenas o caminho relativo
                $relative_path = 'video/' . $name . "." . $ext;
                $sql = "INSERT INTO `video` (video_name, location)
                        VALUES ('$name', '$relative_path')";
                mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli));

                echo "<script>alert('Vídeo enviado com sucesso!');</script>";
                echo "<script>window.location = 'index.php';</script>";
                exit;
            } else {
                die("Falha ao mover o arquivo para: $new_path");
            }
        } else {
            echo "<script>alert('Formato de vídeo não permitido');</script>";
            echo "<script>window.location = 'index.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Arquivo muito grande para upload');</script>";
        echo "<script>window.location = 'index.php';</script>";
        exit;
    }
}
?>