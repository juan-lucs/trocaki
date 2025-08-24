<?php
session_start();
include 'protect.php';
include 'conexao.php';

// 1) Verifica sessão e usuário autenticado
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int) $_SESSION['id'];

// 2) Verifica se o usuário existe no banco
$chk = $mysqli->prepare("SELECT 1 FROM usuarios WHERE id = ?");
$chk->bind_param('i', $userId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    die('Usuário inválido.');
}
$chk->close();

// 3) Processa cadastro de novo item (com quantidade)
if (isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $nome       = trim($_POST['nome_item']   ?? '');
    $descricao  = trim($_POST['descricao']   ?? '');
    $local      = trim($_POST['localizacao'] ?? '');
    $categoria  = trim($_POST['categoria']   ?? '');
    $quantidade = (int) ($_POST['quantidade'] ?? 1);

    if ($categoria === '') {
        die('Categoria é obrigatória.');
    }
    if ($quantidade < 1) {
        die('Quantidade deve ser no mínimo 1.');
    }

    // Lê BLOBs das imagens (até 3)
    $blobs = [];
    for ($i = 0; $i < 3; $i++) {
        $tmp = $_FILES['images']['tmp_name'][$i] ?? null;
        $blobs[$i] = ($tmp && is_uploaded_file($tmp))
            ? file_get_contents($tmp)
            : null;
    }

    // Prepara o INSERT incluindo a coluna quantidade
    $stmt = $mysqli->prepare(
        "INSERT INTO itens
         (usuario_id, nome_item, descricao, localizacao, categoria, quantidade, image1, image2, image3)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        die("Erro no prepare(): " . $mysqli->error);
    }
    // Tipos: i = userId, s = nome_item, s = descricao, s = localizacao, s = categoria,
    // i = quantidade, s = image1, s = image2, s = image3
    $stmt->bind_param(
        'issssisss',
        $userId,
        $nome,
        $descricao,
        $local,
        $categoria,
        $quantidade,
        $blobs[0],
        $blobs[1],
        $blobs[2]
    );
    if (!$stmt->execute()) {
        die("Erro ao cadastrar item: " . $stmt->error);
    }
    $stmt->close();

    header('Location: produtos.php');
    exit;
}

// 4) Busca itens do usuário (sem BLOBs, mas trazendo quantidade e nome_item)
$select = $mysqli->prepare(
    "SELECT id, nome_item, categoria, descricao, localizacao, quantidade
       FROM itens
      WHERE usuario_id = ?
   ORDER BY data_criacao DESC"
);
$select->bind_param('i', $userId);
$select->execute();
$result = $select->get_result();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meus Itens</title>
  <!-- Sua folha de estilos principal -->
  <link rel="stylesheet" href="estilos_produtos.css">

  <!-- Google Fonts ou outros assets -->
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Segoe+UI&display=swap" rel="stylesheet">

  <!--  Script para alternar paleta via JavaScript -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const temaCards = document.querySelectorAll('.tema-card');
      temaCards.forEach(card => {
        card.addEventListener('click', () => {
          const tema = card.dataset.theme;      
          document.documentElement.setAttribute('data-theme', tema);

          // sinaliza seleção visual
          temaCards.forEach(c => c.classList.remove('active'));
          card.classList.add('active');
        });
      });
    });
  </script>
</head>
<body>

<header>
  <a href="painel.php">← Voltar</a>
  <h1>Meus Itens</h1>
</header>

<div class="container">
  <!-- Botão para abrir modal -->
  <div class="add-item" id="openModal">+ Novo Item</div>

  <!-- Grid de itens -->
  <div class="items-grid">
    <?php while ($item = $result->fetch_assoc()): ?>
      <div class="item-card">
        <div class="carousel">
          <?php for ($k = 1; $k <= 3; $k++): ?>
            <img src="imagem_item.php?id=<?= $item['id'] ?>&k=<?= $k ?>"
                 class="<?= $k === 1 ? 'active' : '' ?>"
                 onerror="this.style.display='none'">
          <?php endfor; ?>
        </div>
        <div class="content">
          <div class="nome-item"><?= htmlspecialchars($item['nome_item']) ?></div>
          <div class="categoria"><?= htmlspecialchars($item['categoria']) ?></div>
          <div class="descricao"><?= nl2br(htmlspecialchars($item['descricao'])) ?></div>
          <div class="quantidade">Quantidade: <?= (int) $item['quantidade'] ?></div>
        </div>
        <div class="actions">
          <a href="editar_item.php?id=<?= $item['id'] ?>" class="edit">Editar</a>
          <a href="excluir_item.php?id=<?= $item['id'] ?>" class="delete"
             onclick="return confirm('Tem certeza que deseja excluir?')">Excluir</a>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
</div>

<!-- Modal Novo Item -->
<div class="modal" id="modal">
  <div class="modal-content">
    <button class="close" id="closeModal">&times;</button>
    <h2>Novo Item</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_item">

      <!-- Campo Quantidade -->
      <label>Quantidade de produtos</label>
      <input
        type="number"
        name="quantidade"
        id="quantidade"
        min="1"
        value="1"
        required
      >

      <label>Nome do item</label>
      <input type="text" name="nome_item" placeholder="Nome do item" required>

      <label>Descrição</label>
      <textarea name="descricao" placeholder="Descrição"></textarea>

      <label>Localização</label>
      <input type="text" name="localizacao" placeholder="Localização" required>

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

      <label>Imagens (até 3)</label>
      <input type="file" name="images[]" accept="image/*">
      <input type="file" name="images[]" accept="image/*">
      <input type="file" name="images[]" accept="image/*">

      <button type="submit">Cadastrar</button>
    </form>
  </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', function() {
    const modal   = document.getElementById('modal');
    const openBtn = document.getElementById('openModal');
    const closeBtn= document.getElementById('closeModal');

    // Abre modal
    openBtn.addEventListener('click', function() {
        modal.style.display = 'flex';
    });
    // Fecha modal ao clicar no X
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    // Fecha modal ao clicar fora do conteúdo
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Carousel automático para cada item-card
    document.querySelectorAll('.item-card').forEach(function(card) {
        let idx = 0;
        setInterval(function() {
            // Seleciona apenas as imagens visíveis (sem display='none')
            const imgs = Array.from(card.querySelectorAll('.carousel img'))
                              .filter(img => getComputedStyle(img).display !== 'none');
            if (imgs.length < 2) return;
            imgs.forEach(img => img.classList.remove('active'));
            idx = (idx + 1) % imgs.length;
            imgs[idx].classList.add('active');
        }, 3000);
    });
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
