<?php
//verificar_email_existente.php
include('conexao.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo "E-mail não informado";
    }
    $consulta = $mysqli-> prepare("SELECT id FROM usuarios WHERE usuario = ?");
    if ($consulta) {
        $consulta->bind_param('s',$email);
        $consulta->execute();
        $teste = $consulta->get_result();
        if ($teste->num_rows === 0) {
            echo "E-mail não encontrado, crie uma conta!";
        } else {
            include 'enviar_codigo.php';
            echo "Código de verificação enviado ao seu e-mail.";
        }
    }
}
?>