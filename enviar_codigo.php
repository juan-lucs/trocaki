<?php
session_start();

require __DIR__ . '/src/PHPMailer.php';
require __DIR__ . '/src/SMTP.php';
require __DIR__ . '/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = $_POST['email'];
$codigo = rand(100000, 999999);
$_SESSION['codigo_verificacao'] = $codigo;
$_SESSION['email_verificado'] = $email;


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "E-mail inválido.";
    exit;
} else {
    $mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'danilocombosrapidos@gmail.com';
    $mail->Password   = 'wfmaptgvnezlavtf';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('danilocombosrapidos@gmail.com', 'Equipe Trocaki');
    $mail->addAddress($email);
    $mail->Subject = 'Código de verificação';
    $mail->Body    = "Seu código é: $codigo";

    $mail->send();
    echo "Código enviado!";
} catch (Exception $e) {
    echo "Erro ao enviar: {$mail->ErrorInfo}";
}
}