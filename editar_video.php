<?php
// editar_video.php

// Inicia sessão apenas se não houver uma em andamento
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include 'protect.php';
include 'conexao.php';  // fornece $mysqli

// Verifica se usuário está logado
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}
$userId  = (int) $_SESSION['id'];
$videoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Busca dados do vídeo (incluindo thumbnail)
$stmt = $mysqli->prepare("
    SELECT descricao, categoria, location, thumbnail
      FROM video
     WHERE video_id = ? AND usuario_id = ?
");
$stmt->bind_param('ii', $videoId, $userId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    die('Vídeo não encontrado ou acesso negado.');
}
$stmt->bind_result($descricao, $categoria, $location, $thumbnail);
$stmt->fetch();
$stmt->close();

// Diretório de thumbs
$uploadThumbDir = __DIR__ . '/uploads/videos/thumbs/';
if (!is_dir($uploadThumbDir)) {
    mkdir($uploadThumbDir, 0755, true);
}

// Processa submissão do formulário
if (isset($_POST['action']) && $_POST['action'] === 'edit_video') {
    $novaDesc = trim($_POST['descricao'] ?? '');
    $novaCat  = trim($_POST['categoria'] ?? '');

    if ($novaCat === '') {
        die('Categoria é obrigatória.');
    }

    // Se houver upload de nova capa
    if (!empty($_FILES['thumb']['tmp_name'])) {
        $extImg   = pathinfo($_FILES['thumb']['name'], PATHINFO_EXTENSION);
        $fileImg  = "{$userId}_thumb_" . time() . ".{$extImg}";
        $destImg  = $uploadThumbDir . $fileImg;
        if (!move_uploaded_file($_FILES['thumb']['tmp_name'], $destImg)) {
            die('Erro ao salvar nova imagem de capa.');
        }
        // Remove capa antiga
        if ($thumbnail && file_exists($thumbnail)) {
            unlink($thumbnail);
        }
        $thumbnail = $destImg;
    }

    // Atualiza registro no banco
    $up = $mysqli->prepare("
        UPDATE video
           SET descricao = ?, 
               categoria = ?, 
               thumbnail = ?
         WHERE video_id = ? AND usuario_id = ?
    ");
    $up->bind_param('sssii', $novaDesc, $novaCat, $thumbnail, $videoId, $userId);
    if (!$up->execute()) {
        die('Erro ao atualizar vídeo: ' . $up->error);
    }

    header('Location: adicionar_video.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar Vídeo</title>
  <link rel="stylesheet" href="estilos_editar_video.css">


  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Segoe+UI&display=swap" rel="stylesheet">

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const temaCards = document.querySelectorAll('.tema-card');
      temaCards.forEach(card => {
        card.addEventListener('click', () => {
          const tema = card.dataset.theme;       
          document.documentElement.setAttribute('data-theme', tema);

        
          temaCards.forEach(c => c.classList.remove('active'));
          card.classList.add('active');
        });
      });
    });
  </script>
</head>
<body>

<header>
  <a href="adicionar_video.php" class="back">← Voltar</a>
  <h1>Editar Vídeo</h1>
</header>

<div class="container">
  <h2>Atualize as informações</h2>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="edit_video">

    <label>Descrição</label>
    <textarea name="descricao" rows="4"><?= htmlspecialchars($descricao) ?></textarea>

    <label>Categoria</label>
    <select name="categoria" required>
      <option value="">Selecione...</option>
      <?php
      $cats = [
        'veículo','eletrodomésticos','tecnologia','moda',
        'esportes','brinquedos','imóveis','pet shop',
        'ferramentas','construção'
      ];
      foreach ($cats as $cat): ?>
        <option value="<?= $cat ?>" <?= $categoria === $cat ? 'selected' : '' ?>>
          <?= ucfirst($cat) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Imagem de Capa (opcional)</label>
    <?php if ($thumbnail && file_exists($thumbnail)): 
        $urlThumb = substr(str_replace('\\','/',$thumbnail), strpos($thumbnail,'uploads/'));
    ?>
      <img src="<?= htmlspecialchars($urlThumb) ?>" alt="Capa Atual" class="thumb-preview">
    <?php endif; ?>
    <input type="file" name="thumb" accept="image/*">

    <button type="submit">Salvar Alterações</button>
  </form>
</div>


  <script>
    document.addEventListener('DOMContentLoaded', ()=>{
          // Carrega tema salvo
const temaSalvo = localStorage.getItem('paletaEscolhida');
if (temaSalvo) {
  document.documentElement.setAttribute('data-theme', temaSalvo);
  document.querySelectorAll('.tema-card').forEach(c => {
    if (c.dataset.tema === temaSalvo) c.classList.add('active');
  });
}
document.querySelectorAll('.tema-card').forEach(card => {
  card.addEventListener('click', () => {
    document.querySelectorAll('.tema-card.active')
            .forEach(c => c.classList.remove('active'));
    card.classList.add('active');
    const nome = card.dataset.tema;
    document.documentElement.setAttribute('data-theme', nome);
    localStorage.setItem('paletaEscolhida', nome);
  });
});
   });
  </script>
</body>
</html>
