<?php
// adicionar_video.php

// Inicia sessão
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include 'protect.php';
include 'conexao.php';  // fornece $mysqli

// Verifica sessão
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int) $_SESSION['id'];

// 1) Busca lista de itens do usuário para popular o <select>
$stmt = $mysqli->prepare(
    "SELECT id, nome_item
      FROM itens
     WHERE usuario_id = ?
     ORDER BY nome_item"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$userItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 2) Busca vídeos do usuário para exibição na grid
$stmt2 = $mysqli->prepare(
    "SELECT video_id, item_id, descricao, categoria, location, thumbnail, created_at
       FROM video
      WHERE usuario_id = ?
   ORDER BY created_at DESC"
);
$stmt2->bind_param('i', $userId);
$stmt2->execute();
$result = $stmt2->get_result();
$stmt2->close();

// 3) Define diretórios de upload
$uploadVideoDir = __DIR__ . '/uploads/videos/';
$uploadThumbDir = __DIR__ . '/uploads/videos/thumbs/';
if (!is_dir($uploadVideoDir)) mkdir($uploadVideoDir, 0755, true);
if (!is_dir($uploadThumbDir)) mkdir($uploadThumbDir, 0755, true);

// 4) Processa o envio de vídeo + capa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_video') {
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $itemId    = (int) ($_POST['item_id'] ?? 0);

    // Valida campos obrigatórios
    if ($itemId <= 0) {
        die('Selecione o item relacionado.');
    }
    if ($categoria === '') {
        die('Categoria é obrigatória.');
    }
    if (empty($_FILES['video']['tmp_name'])) {
        die('Selecione um arquivo de vídeo.');
    }

    // Verifica se o item pertence ao usuário
    $chk = $mysqli->prepare("SELECT COUNT(*) FROM itens WHERE id=? AND usuario_id=?");
    $chk->bind_param('ii', $itemId, $userId);
    $chk->execute();
    $chk->bind_result($count);
    $chk->fetch();
    $chk->close();
    if ($count === 0) {
        die('Item inválido.');
    }

    // Upload do vídeo
    $extVid  = pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION);
    $fileVid = "{$userId}_" . time() . ".{$extVid}";
    $destVid = $uploadVideoDir . $fileVid;
    if (!move_uploaded_file($_FILES['video']['tmp_name'], $destVid)) {
        die('Erro ao salvar vídeo.');
    }

    // Upload opcional da capa
    $destImg = null;
    if (!empty($_FILES['thumb']['tmp_name'])) {
        $extImg  = pathinfo($_FILES['thumb']['name'], PATHINFO_EXTENSION);
        $fileImg = "{$userId}_thumb_" . time() . ".{$extImg}";
        $destImg = $uploadThumbDir . $fileImg;
        if (!move_uploaded_file($_FILES['thumb']['tmp_name'], $destImg)) {
            die('Erro ao salvar imagem de capa.');
        }
    }

    // Insere no BD, incluindo o item_id
    $insert = $mysqli->prepare(
        "INSERT INTO video (usuario_id, item_id, descricao, categoria, location, thumbnail)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $insert->bind_param(
        'iissss',
        $userId,
        $itemId,
        $descricao,
        $categoria,
        $destVid,
        $destImg
    );
    if (!$insert->execute()) {
        die('Erro ao cadastrar vídeo: ' . $insert->error);
    }
    $insert->close();

    // Após inserir, recarrega página para atualizar grid
    header('Location: adicionar_video.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Meus Vídeos</title>
 <link rel="stylesheet" href="estilos_adicionar_video.css">
  
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

  <div class="top-bar">
    <h1>Meus Vídeos</h1>
    <a href="comunidade.php" class="btn-back">← Voltar</a>
  </div>

  <div class="container">
    <div class="add-video" id="openModal">+ Novo Vídeo</div>

    <div class="videos-grid">
      <?php while ($video = $result->fetch_assoc()): ?>
        <?php
          $pathVid = str_replace('\\','/',$video['location']);
          $posVid  = strpos($pathVid,'uploads/videos/');
          $urlVid  = $posVid !== false ? substr($pathVid, $posVid) : $pathVid;

          $urlThumb = '';
          if ($video['thumbnail']) {
            $pathImg = str_replace('\\','/',$video['thumbnail']);
            $posImg  = strpos($pathImg,'uploads/videos/thumbs/');
            $urlThumb= $posImg !== false ? substr($pathImg, $posImg) : $pathImg;
          }
        ?>
        <div class="video-card">
          <div class="video-preview">
            <video <?php if($urlThumb):?>poster="<?=htmlspecialchars($urlThumb)?>"<?php endif?> controls muted loop playsinline>
              <source src="<?=htmlspecialchars($urlVid)?>" type="video/mp4">
              Seu navegador não suporta o elemento de vídeo.
            </video>
          </div>
          <div class="content">
            <div class="categoria">
              <?=htmlspecialchars($video['categoria'])?> 
              <small style="font-size:0.8rem; color:#888;">
                (Item #<?=htmlspecialchars($video['item_id'])?>)
              </small>
            </div>
            <div class="descricao"><?=nl2br(htmlspecialchars($video['descricao']))?></div>
          </div>
          <div class="actions">
            <a href="editar_video.php?id=<?=$video['video_id']?>" class="edit">Editar</a>
            <a href="excluir_video.php?id=<?=$video['video_id']?>" class="delete"
               onclick="return confirm('Deseja excluir este vídeo?')">Excluir</a>
          </div>
        </div>
      <?php endwhile;?>
    </div>
  </div>

  <!-- Modal de cadastro -->
  <div class="modal" id="modal">
    <div class="modal-content">
      <button class="close" id="closeModal">&times;</button>
      <h2>Novo Vídeo</h2>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_video">

        <label>Item relacionado</label>
        <select name="item_id" required>
          <option value="">Selecione o item...</option>
          <?php foreach($userItems as $it): ?>
            <option value="<?= $it['id'] ?>"><?= htmlspecialchars($it['nome_item']) ?></option>
          <?php endforeach; ?>
        </select>

        <label>Descrição</label>
        <textarea name="descricao" placeholder="Descrição opcional"></textarea>

        <label>Categoria</label>
        <select name="categoria" required>
          <option value="">Selecione...</option>
          <option value="veículo">Veículo</option>
          <option value="eletrodomésticos">Eletrodomésticos</option>
          <option value="tecnologia">Tecnologia</option>
          <option value="moda">Moda</option>
          <option value="esportes">Esportes</option>
          <option value="brinquedos">Brinquedos</option>
          <option value="imóveis">Imóveis</option>
          <option value="pet shop">Pet Shop</option>
          <option value="ferramentas">Ferramentas</option>
          <option value="construção">Construção</option>
        </select>

        <label>Imagem de Capa (opcional)</label>
        <input type="file" name="thumb" accept="image/*">

        <label>Arquivo de Vídeo</label>
        <input type="file" name="video" accept="video/mp4,video/*" required>

        <button type="submit">Cadastrar Vídeo</button>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', ()=>{
      const modal = document.getElementById('modal');
      document.getElementById('openModal').onclick  = ()=> modal.style.display = 'flex';
      document.getElementById('closeModal').onclick = ()=> modal.style.display = 'none';
      window.onclick = e => { if(e.target===modal) modal.style.display='none'; };
 
    
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
