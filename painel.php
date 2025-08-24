<?php
//painel.php
session_start();
include 'protect.php';
include 'conexao.php';

// 1) Cria a tabela de trocas se não existir
$mysqli->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS trocas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  solicitante_id INT NOT NULL,
  destinatario_id INT NOT NULL,
  item_solicitado_id INT NOT NULL,
  item_ofertado_id INT NOT NULL,
  status ENUM('pendente','aceita','recusada') DEFAULT 'pendente',
  data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unq_solic (solicitante_id,item_solicitado_id,item_ofertado_id),
  FOREIGN KEY (solicitante_id)     REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (destinatario_id)    REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (item_solicitado_id) REFERENCES itens(id) ON DELETE CASCADE,
  FOREIGN KEY (item_ofertado_id)   REFERENCES itens(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4;
SQL
);

// 2) Conta trocas pendentes
$stmt = $mysqli->prepare("SELECT COUNT(*) FROM trocas WHERE destinatario_id=? AND status='pendente'");
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($pendentes);
$stmt->fetch();
$stmt->close();

// 3) Itens de outros usuários com estoque > 0
$stmt = $mysqli->prepare("
  SELECT id, nome_item, descricao, localizacao, categoria, usuario_id, quantidade
    FROM itens
   WHERE usuario_id <> ?
     AND quantidade > 0
   ORDER BY data_criacao DESC
");
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$others = $stmt->get_result();

// 4) Categorias distintas
$catStmt = $mysqli->prepare("
  SELECT DISTINCT categoria
    FROM itens
   WHERE usuario_id<>?
   ORDER BY categoria
");
$catStmt->bind_param('i', $_SESSION['id']);
$catStmt->execute();
$resCats = $catStmt->get_result();
$categorias = [];
while ($r = $resCats->fetch_assoc()) {
    $categorias[] = $r['categoria'];
}

// 5) Média geral de avaliações dos seus produtos
$mStmt = $mysqli->prepare("
  SELECT AVG(a.nota)
    FROM avaliacoes a
    JOIN itens i ON i.id = a.produto_id
   WHERE i.usuario_id = ?
");
$mStmt->bind_param('i', $_SESSION['id']);
$mStmt->execute();
$mStmt->bind_result($mediaGeral);
$mStmt->fetch();
$mStmt->close();
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="paleta1">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Painel - Trocaki</title>

  <!-- Sua folha de estilos principal -->
  <link rel="stylesheet" href="estilos_painel.css?v=20250722">   

  <!-- (Opcional) Google Fonts ou outros assets -->
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Segoe+UI&display=swap" rel="stylesheet">

  <!-- (Opcional) Script para alternar paleta via JavaScript -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const temaCards = document.querySelectorAll('.tema-card');
      temaCards.forEach(card => {
        card.addEventListener('click', () => {
          // ao clicar, ativa paleta1 ou paleta2
          const tema = card.dataset.theme;        // <div class="tema-card" data-theme="paleta1">
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

  <!-- Top Bar -->
  <div class="top-bar">
    <div class="profile-btn" id="openSidebar">
      <?php
        $img = file_exists("uploads/{$_SESSION['id']}.jpg")
             ? "uploads/{$_SESSION['id']}.jpg"
             : "https://i.imgur.com/6VBx3io.png";
      ?>
      <img src="<?= $img ?>" alt="Perfil">
    </div>
    <h1>Olá, <?= htmlspecialchars($_SESSION['nome']) ?>!</h1>
    <a href="logout.php" class="btn sair">Sair</a>
  </div>

  <div id="dragonWrapper" class="dragon-container">
<svg id="dragon" viewBox="0 0 100 100">
        <defs>
          <!-- Cabeza -->
          <g id="Cabeza" transform="matrix(1, 0, 0, 1, 0, 0)">
            <path
              style="fill:#FFFFFF;fill-opacity:1"
              d="M-28.9,-1.1L-28.55 -1.95Q-28.1 -3.1 -27.25 -2.95L-26.7 -2.95Q-27.7 -1.65 -28.9 -1.1M-18.35,-1.8Q-15.1 -10.3 -9.6 -6.05Q-15.1 -6.2 -18.35 -1.8M-18.35,1.1Q-15.1 5.45 -9.6 5.35Q-15.1 9.55 -18.35 1.1M-26.7,2.2L-27.25 2.25Q-28.1 2.4 -28.55 1.2L-28.9 0.35Q-27.7 0.9 -26.7 2.2"
            />
            <path
              style="fill:#000000;fill-opacity:1"
              d="M-21.05,-8.25Q-13.6 -15.95 -1.3 -12.1Q-7.85 -8.5 -5.85 -4.35Q-2.3 -4.85 10.5 0.15Q0 4.35 -5.85 3.65Q-7.85 7.75 -1.25 12.45Q-13.6 15.2 -21.05 7.5Q-29.55 4.05 -30.2 -0.35Q-29.55 -4.8 -21.05 -8.25M-26.7,-2.95L-27.25 -2.95Q-28.1 -3.1 -28.55 -1.95L-28.9 -1.1Q-27.7 -1.65 -26.7 -2.95M-9.6,-6.05Q-15.1 -10.3 -18.35 -1.8M-18.35 1.1Q-15.1 5.45 -9.6 5.35Q-15.1 9.55 -18.35 1.1"
            />
          </g>
  
          <!-- Aletas -->
          <g id="Aletas" transform="matrix(1, 0, 0, 1, 0, 0)">
            <linearGradient
              id="LinearGradID_1"
              gradientUnits="userSpaceOnUse"
              gradientTransform="matrix(0.0935974, 0, 0, 0.188782, -20.55, 0)"
              spreadMethod="pad"
              x1="-819.2"
              y1="0"
              x2="819.2"
              y2="0"
            >
              <stop offset="0" style="stop-color:#CCCCCC;stop-opacity:1" />
              <stop offset="1" style="stop-color:#000000;stop-opacity:1" />
            </linearGradient>
            <path
              style="fill:url(#LinearGradID_1)"
              d="M29.75,-36.85Q-17.75 -61.45 -42.05 -40.95L-45.35 -38.35L-53.7 -41.15L-51.15 -44.85Q-34.85 -68.4 21 -57.8Q-32.2 -72.1 -50.25 -50.25Q-53.85 -45.65 -56.05 -41.95L-64.7 -43.35L-60.6 -50.3Q-45.9 -75.55 5.1 -79.35Q-2.2 -79.8 -9.45 -79.15Q-16.2 -78.55 -22.85 -77.15Q-29.85 -75.65 -36.5 -73Q-43.05 -70.4 -48.8 -66.85Q-54.55 -63.35 -56.8 -60.3L-60.5 -55.4Q-62.95 -52.1 -67 -43.55L-70.55 -43.55L-76.35 -42.95Q-74.6 -49.1 -71.85 -54.85Q-68.9 -61.25 -64.8 -67.1Q-60.8 -73 -55.45 -77.55Q-49.9 -82.35 -43.65 -85.85L-30.6 -92.7Q-24.05 -95.95 -17 -98.25Q-63.75 -86.35 -73.65 -57.1Q-75.75 -50.75 -77.45 -42.75Q-82.9 -41.75 -88 -39.65Q-87.65 -46.65 -86.3 -53.05Q-79.8 -89.8 -36.65 -117.2Q-80.65 -94.5 -87.55 -59.55Q-88.65 -54.15 -88.95 -39.4L-89.8 -38.85L-92.7 -37.6Q-93.75 -44.35 -94.1 -51.15L-94.1 -51.15L-90.05 -79.65Q-79.05 -86.55 -88.05 -93.6Q-88.05 -88.95 -90.05 -79.7Q-92.15 -72.5 -90.05 -79.65Q-88.05 -86.55 -78.8 -105.15Q-74.6 -111.35 -70.25 -117.25Q-66.1 -125 -61.1 -128.55Q-70.3 -119.35 -77.9 -108.7Q-86 -97.3 -90.8 -84.05Q-95.8 -70.5 -96 -56.15Q-96.1 -46 -94.05 -36.05L-93.25 -31.55Q-93.5 -35.65 -92.35 -36Q-79.85 -42 -66.6 -40.45Q-52.45 -38.85 -39.2 -33.25Q-28.3 -29.9 -21.25 -24.15Q-17.8 -23.3 -8.6 -15.6Q-12.1 -20.75 -16.75 -24.5Q-24.5 -30.7 -34.25 -34.05L-42.55 -37Q-38.9 -41.25 -31.5 -43.25Q-24.05 -45.3 -16.2 -46.3Q-8.35 -47.35 -1 -46Q5.95 -44.75 12.75 -42.85Q19.85 -40.9 29.75 -36.85M-92.45,-27.35L-94.95 -36.25Q-109.7 -105 -27.95 -154.65Q-98.65 -103.1Q-101.3 -78.45 -83.15 -42.95L-87.45 -78.95L-83.15 -42.95"
            />
          </g>
  
          <!-- Espina -->
          <g id="Espina" transform="matrix(1, 0, 0, 1, 0, 0)">
            <linearGradient
              id="LinearGradID_2"
              gradientUnits="userSpaceOnUse"
              gradientTransform="matrix(0.0229492, 0, 0, -0.0152893, 0, 0.05)"
              spreadMethod="pad"
              x1="-819.2"
              y1="0"
              x2="819.2"
              y2="0"
            >
              <stop offset="0" style="stop-color:#CCCCCC;stop-opacity:1" />
              <stop offset="1" style="stop-color:#333333;stop-opacity:1" />
            </linearGradient>
            <path
              style="fill:url(#LinearGradID_2)"
              d="M-18.8,0Q-17.85 -5.7 -12.3 -9.6Q-11.2 -5.35 -6.5 -8.25L-6.45 -8.2L-6.2 -8.3Q1.25 -16.25 6.65 -12.4Q0.05 -12.55 0 -5.95Q2.7 -2.4 7.75 -4.1Q18 -1.45 18.8 0L-18.8 0"
            />
            <linearGradient
              id="LinearGradID_3"
              gradientUnits="userSpaceOnUse"
              gradientTransform="matrix(0.0229492, 0, 0, 0.0152893, 0, -0.05)"
              spreadMethod="pad"
              x1="-819.2"
              y1="0"
              x2="819.2"
              y2="0"
            >
              <stop offset="0" style="stop-color:#CCCCCC;stop-opacity:1" />
              <stop offset="1" style="stop-color:#333333;stop-opacity:1" />
            </linearGradient>
            <path
              style="fill:url(#LinearGradID_3)"
              d="M18.8,0Q18 1.45 7.75 4.1Q2.7 2.4 0 5.95Q0.05 12.55 6.65 12.25L6.2 8.35Q-6.35 8.25L-6.5 8.25Q-11.2 5.35 -12.3 9.6Q-17.85 5.7 -18.8 0"
            />
          </g>
        </defs>
  
  
          
        <!-- Tela principal -->
        <g id="screen" />
      </svg>
  </div>


  <!-- Sidebar Perfil -->
  <div id="sidebar">
    <button class="close-sidebar" id="closeSidebar">&times;</button>
    <h2>Meu Perfil</h2>
    <form method="POST" action="editar_perfil.php" enctype="multipart/form-data">
      <label>Foto de Perfil</label>
      <input type="file" name="foto_perfil" accept="image/*">
      <label>Nome Completo</label>
      <input type="text" name="nome" value="<?= htmlspecialchars($_SESSION['nome']) ?>" required>
      <label>Localização</label>
      <input type="text" name="localizacao" placeholder="Cidade, Estado"
             value="<?= htmlspecialchars($_SESSION['localizacao'] ?? '') ?>">
      <button type="submit">Salvar</button>
    </form>
    <h2>Meu Perfil</h2>
<hr>
<!-- Início da seção de escolha de paleta -->
<div class="tema-seletor">
  <p><strong>Escolha sua paleta:</strong></p>
  <div class="tema-cards">
    <div class="tema-card" data-tema="paleta1">
      <!-- dois swatches como preview -->
      <div class="swatch" style="background:#13505b"></div>
      <div class="swatch" style="background:#FFF9B1"></div>
      <p>Paleta 1</p>
    </div>
    <div class="tema-card" data-tema="paleta2">
      <div class="swatch" style="background:#614051"></div>
      <div class="swatch" style="background:#cfa8bb"></div>
      <p>Paleta 2</p>
    </div>
    <div class="tema-card" data-tema="paleta3">
      <div class="swatch" style="background:#B88B50"></div>
      <div class="swatch" style="background:#2D2926"></div>
      <p>Paleta 3</p>
    </div>
    <div class="tema-card" data-tema="paleta4">
      <div class="swatch" style="background:#3B2311"></div>
      <div class="swatch" style="background:#B69A8C"></div>
      <p>Paleta 4</p>
    </div>
  </div>
</div>
    <hr>
    <p>
      <strong>Média Geral:</strong>
      <?= $mediaGeral ? number_format($mediaGeral,1) : 'sem avaliações' ?> ★
      <button id="verPorProduto" class="btn-act-ion">Ver por produto</button>
    </p>
  </div>

  <!-- Nav Cards -->
  <div class="nav-cards">
    <a href="produtos.php">Meus Itens</a>
    <a href="pedidos.php">Trocas (<?= $pendentes ?>)</a>
    <a href="comunidade.php">Comunidade</a>
    <a href="chat_conversas.php">Mensagens</a>
  </div>

  <!-- Busca + Filtro -->
  <div class="search-bar">
    <input type="text" id="searchProduct" placeholder="Pesquisar produtos...">
    <select id="categoryFilter">
      <option value="">Todas Categorias</option>
      <?php foreach($categorias as $cat): ?>
        <option value="<?= strtolower($cat) ?>"><?= htmlspecialchars($cat) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

<div class="items-grid" id="itemsGrid">
 <?php while($it = $others->fetch_assoc()): ?>
  <div class="item-card"
       data-id="<?= $it['id'] ?>"
       data-owner="<?= $it['usuario_id'] ?>"
       data-name="<?= strtolower(htmlspecialchars($it['nome_item'])) ?>"
       data-category="<?= strtolower(htmlspecialchars($it['categoria'])) ?>">
 
      <div class="carousel">
        <?php for($k=1; $k<=3; $k++): ?>
          <img src="imagem_item.php?id=<?= $it['id'] ?>&k=<?= $k ?>"
               class="<?= $k===1?'active':'' ?>"
               onerror="this.style.display='none'">
        <?php endfor; ?>
      </div>
      <div class="content">
        <div class="nome-item"><?= htmlspecialchars($it['nome_item']) ?></div>
        <div class="categoria"><?= htmlspecialchars($it['categoria']) ?></div>
        <div class="quantidade">Disponível: <?= (int)$it['quantidade'] ?></div>
      </div>
    </div>
  <?php endwhile; ?>
</div>

  <!-- Fim do loop de itens -->

  <!-- Modais -->

  <div id="modalTroca" class="modal" style="z-index: 99999;">
    <div class="modal-content" style="z-index: 99999;">
      <button class="close" id="closeTroca">&times;</button>
      <h2>Escolha um dos seus itens</h2>
      <div class="search-bar">
        <input type="text" id="searchMyProduct" placeholder="Pesquisar meus itens...">
      </div>
      <div class="modal-grid" id="myItemsGrid"></div>
      <div class="modal-footer">
        <button class="btn-action confirm" id="confirmarTroca">Confirmar</button>
        <button class="btn-action cancel" id="cancelTroca">Cancelar</button>
      </div>
    </div>
  </div>

  <div id="modalDetalhes" class="modal">
    <div class="modal-content">
      <button class="close" id="closeDetalhes">&times;</button>
      <div id="detalhesContent"></div>
    </div>
  </div>

  <div id="modalDetalhesComentarios" class="modal">
    <div class="modal-content">
      <button class="close" id="closeDetalhesComentarios">&times;</button>
      <div id="detalhesContentComentarios"></div>
    </div>
  </div>

  <div id="modalMediaProdutos" class="modal">
    <div class="modal-content">
      <button class="close" id="closeMediaProd">&times;</button>
      <h2>Média por Produto</h2>
      <ul id="listaMedias" style="list-style:none;padding:0"></ul>
    </div>
  </div>
  
<!-- Modal Fullscreen -->
<div id="fullScreenDetalhes" class="fullscreen-modal">
  <button class="close-full" id="closeFull">&times;</button>
  <div class="fs-carousel" id="fsCarousel"></div>
  <div class="fs-header">
    <h2 id="fsNome"></h2>
    <button id="fsBtnTroca" class="btn-action btn-trocar">Trocar</button>
  </div>
  <div class="fs-description" id="fsDescricao"></div>
  <div class="fs-avaliacoes" id="fsAvaliacoes"></div>
  <div id="fsImageOverlay" class="image-overlay">
  <button id="overlayClose" class="overlay-btn close-btn">&times;</button>
  <button id="overlayPrev"  class="overlay-btn nav-btn">&#10094;</button>
  <img    id="overlayImg"   class="overlay-img">
  <button id="overlayNext"  class="overlay-btn nav-btn">&#10095;</button>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // --- Variáveis globais para confirmar troca ---
  let targetId, targetOwner;

  // --- Função que popula o modal de troca e abre ele ---
  async function populaGridDeTroca(itemId, ownerId) {
    targetId    = itemId;
    targetOwner = ownerId;

    const res  = await fetch('lista_itens_proprios.php');
    const data = await res.json();
    const grid = document.getElementById('myItemsGrid');
    grid.innerHTML = '';

    data.forEach(it => {
      const d = document.createElement('div');
      d.className    = 'modal-card';
      d.dataset.id   = it.id;
      d.dataset.name = it.nome_item.toLowerCase();
      d.innerHTML    = `
        <img src="imagem_item.php?id=${it.id}&k=1" onerror="this.style.display='none'">
        <div class="nome">${it.nome_item}</div>
      `;
      d.onclick = () => {
        grid.querySelectorAll('.modal-card').forEach(x => x.classList.remove('active'));
        d.classList.add('active');
      };
      grid.appendChild(d);
    });

     const searchMyProduct = document.getElementById('searchMyProduct');
  searchMyProduct.value = '';    // opcional: limpa busca de sessões anteriores
  searchMyProduct.focus();       // opcional: já deixa o cursor no campo
  searchMyProduct.oninput = e => {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('#myItemsGrid .modal-card')
      .forEach(c => {
        c.style.display = c.dataset.name.includes(term) ? '' : 'none';
      });
  };
    document.getElementById('modalTroca').style.display = 'flex';
  }

  // ————————————————————————————————————
  // 1) Fullscreen-detail (abrir e fechar)
  // ————————————————————————————————————
  const fullModal    = document.getElementById('fullScreenDetalhes');
  const closeFull    = document.getElementById('closeFull');
  const fsCarousel   = document.getElementById('fsCarousel');
  const fsNome       = document.getElementById('fsNome');
  const fsBtnTroca   = document.getElementById('fsBtnTroca');
  const fsDescricao  = document.getElementById('fsDescricao');
  const fsAvaliacoes = document.getElementById('fsAvaliacoes');
  const grid         = document.getElementById('itemsGrid');

  async function abrirFullscreen(card) {
    const id = card.dataset.id;

    // Busca dados e avaliações
    const [r1, r2] = await Promise.all([
      fetch(`detalhes_item.php?id=${id}`),
      fetch(`comentarios_detalhe.php?id=${id}`)
    ]);
    if (!r1.ok) return alert('Não foi possível carregar o item.');

    const item  = await r1.json();
    const avals = r2.ok ? await r2.json() : [];

    // 1) Popula carrossel do fullscreen
    fsCarousel.innerHTML = '';
    item.images.forEach((src, idx) => {
      const img = document.createElement('img');
      img.src           = src;
      img.dataset.index = idx;
      fsCarousel.appendChild(img);
      img.addEventListener('click', () => openOverlay(idx));
    });

    // 2) Popula nome e configura botão Trocar
    fsNome.textContent = item.nome_item;
    fsBtnTroca.onclick = () => populaGridDeTroca(card.dataset.id, card.dataset.owner);

    // 3) Popula descrição
    fsDescricao.textContent = item.descricao;

    // 4) Popula avaliações
    if (!avals.length) {
      fsAvaliacoes.innerHTML = '<p style="opacity:0.7;font-style:italic">Sem avaliações.</p>';
    } else {
      fsAvaliacoes.innerHTML = avals.map(a => `
        <p><strong>${a.usuario}</strong> – ${'★'.repeat(a.nota)}${'☆'.repeat(5 - a.nota)}</p>
        <p>${a.comentario}</p>
        <hr>
      `).join('');
    }

    // Lightbox interno
    const overlay      = document.getElementById('fsImageOverlay');
    const overlayImg   = document.getElementById('overlayImg');
    let currentOverlay = 0;

    function openOverlay(i) {
      currentOverlay = i;
      overlayImg.src = item.images[i];
      overlay.classList.add('active');
    }
    function closeOverlay() {
      overlay.classList.remove('active');
    }
    function showOverlay(dir) {
      currentOverlay = (currentOverlay + dir + item.images.length) % item.images.length;
      overlayImg.src = item.images[currentOverlay];
    }

    document.getElementById('overlayClose').onclick = closeOverlay;
    document.getElementById('overlayPrev').onclick  = () => showOverlay(-1);
    document.getElementById('overlayNext').onclick  = () => showOverlay(+1);

    // Exibe o fullscreen
    fullModal.classList.add('ativo');
  }

  grid.addEventListener('click', e => {
    const card = e.target.closest('.item-card');
    if (card) abrirFullscreen(card);
  });
  closeFull.addEventListener('click', () => fullModal.classList.remove('ativo'));

  // ————————————————————————————————————
  // 2) Sidebar
  // ————————————————————————————————————
  document.getElementById('openSidebar').onclick = () =>
    document.getElementById('sidebar').classList.add('open');
  document.getElementById('closeSidebar').onclick = () =>
    document.getElementById('sidebar').classList.remove('open');

  // ————————————————————————————————————
  // 3) Carousel automático dos cards
  // ————————————————————————————————————
  document.querySelectorAll('.item-card').forEach(card => {
    let idx = 0;
    setInterval(() => {
      const imgs = Array.from(card.querySelectorAll('.carousel img'))
                        .filter(i => i.style.display !== 'none');
      if (imgs.length < 2) return;
      imgs.forEach(i => i.classList.remove('active'));
      idx = (idx + 1) % imgs.length;
      imgs[idx].classList.add('active');
    }, 3000);
  });

  // ————————————————————————————————————
  // 4) Filtros de busca e categoria
  // ————————————————————————————————————
 const searchProd = document.getElementById('searchProduct'),
        catFilter  = document.getElementById('categoryFilter');

  function applyFilter() {
    const term = searchProd.value.toLowerCase(),
          cat  = catFilter.value;
    document.querySelectorAll('.item-card').forEach(card => {
      const nome     = card.dataset.name,
            categoria = card.dataset.category,
            matchNome = nome.includes(term),
            matchCat  = (!cat || categoria === cat);
      card.style.display = (matchNome && matchCat) ? '' : 'none';
    });
  }

  searchProd.addEventListener('input', applyFilter);
  catFilter.addEventListener('change', applyFilter);


  // ————————————————————————————————————
  // 5) Troca de itens (modo “genérico” para cards)
  // ————————————————————————————————————
  document.querySelectorAll('.btn-trocar').forEach(btn => {
    btn.onclick = () => {
      const card = btn.closest('.item-card');
      if (!card) return;
      populaGridDeTroca(card.dataset.id, card.dataset.owner);
    };
  });

  document.getElementById('searchMyProduct').oninput = e => {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('#myItemsGrid .modal-card').forEach(c => {
      c.style.display = c.dataset.name.includes(term) ? '' : 'none';
    });
  };

  document.getElementById('confirmarTroca').onclick = async () => {
    const sel = document.querySelector('#myItemsGrid .modal-card.active');
    if (!sel) return alert('Selecione um item');
    try {
      const r = await fetch('solicitar_troca.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          item_solicitado: targetId,
          item_ofertado:   sel.dataset.id,
          owner:           targetOwner
        })
      });
      if (r.status === 201) {
        document.getElementById('modalTroca').style.display = 'none';
        return alert('Solicitação de troca enviada com sucesso!');
      }
      const resJson = await r.json().catch(() => ({}));
      if (r.status === 409)      alert(resJson.error || 'Já fez essa solicitação.');
      else if (r.status === 400) alert(resJson.error || 'Dados inválidos.');
      else                        alert(resJson.error || 'Erro ao solicitar troca.');
    } catch {
      alert('Falha de conexão. Tente novamente mais tarde.');
    }
  };
  document.getElementById('closeTroca').onclick = () =>
    document.getElementById('modalTroca').style.display = 'none';

  // ————————————————————————————————————
  // 6) Modal de detalhes simples
  // ————————————————————————————————————
  document.querySelectorAll('.btn-detalhes').forEach(btn => {
    btn.onclick = async () => {
      const card = btn.closest('.item-card');
      const id   = card.dataset.id;
      const qty  = card.dataset.quantity;
      const r    = await fetch(`detalhes_item.php?id=${id}`);
      if (!r.ok) return alert('Item não encontrado');
      const it = await r.json();
      const d  = document.getElementById('detalhesContent');
      d.innerHTML = `
        <h2>${it.nome_item}</h2>
        <p><strong>Categoria:</strong> ${it.categoria}</p>
        <p><strong>Localização:</strong> ${it.localizacao}</p>
        <p><strong>Quantidade disponível:</strong> ${qty}</p>
        <div style="display:flex;gap:10px;margin:10px 0;">
          ${it.images.map(s=>`<img src="${s}" style="width:80px;height:80px;object-fit:cover;border-radius:4px">`).join('')}
        </div>
        <p>${it.descricao}</p>`;
      document.getElementById('modalDetalhes').style.display = 'flex';
    };
  });
  document.getElementById('closeDetalhes').onclick = () =>
    document.getElementById('modalDetalhes').style.display = 'none';

  // ————————————————————————————————————
  // 7) Modal de avaliações
  // ————————————————————————————————————
  document.querySelectorAll('.btn-avaliacoes').forEach(btn => {
    btn.onclick = async () => {
      const card = btn.closest('.item-card');
      const id   = card.dataset.id;
      const resp = await fetch(`comentarios_detalhe.php?id=${id}`);
      const data = resp.ok ? await resp.json() : [];
      const div  = document.getElementById('detalhesContentComentarios');
      if (!data.length) {
        div.innerHTML = `<p style="text-align:center;font-style:italic;color:#555;">
                           Ainda não há avaliações para este item.
                         </p>`;
      } else {
        div.innerHTML = data.map(a => `
          <p><strong>${a.usuario}</strong> (${a.data}):
            ${'★'.repeat(a.nota)}${'☆'.repeat(5-a.nota)}
          </p>
          <p>${a.comentario}</p><hr>`).join('');
      }
      document.getElementById('modalDetalhesComentarios').style.display = 'flex';
    };
  });
  document.getElementById('closeDetalhesComentarios').onclick = () =>
    document.getElementById('modalDetalhesComentarios').style.display = 'none';

  // ————————————————————————————————————
  // 8) Média por produto
  // ————————————————————————————————————
  document.getElementById('verPorProduto').onclick = async () => {
    const r    = await fetch('medias_por_produto.php');
    const list = await r.json();
    const ul   = document.getElementById('listaMedias');
    ul.innerHTML = list.map(p => `
      <li style="margin-bottom:8px">
        <strong>${p.nome_item}</strong>: ${p.media.toFixed(1)} ★
      </li>`).join('');
    document.getElementById('modalMediaProdutos').style.display = 'flex';
  };
  document.getElementById('closeMediaProd').onclick = () =>
    document.getElementById('modalMediaProdutos').style.display = 'none';

  // ————————————————————————————————————
  // 9) Restauração de tema salvo
  // ————————————————————————————————————
  const temaSalvo = localStorage.getItem('paletaEscolhida');
  if (temaSalvo) {
    document.documentElement.setAttribute('data-theme', temaSalvo);
    document.querySelectorAll('.tema-card').forEach(c => {
      if (c.dataset.tema === temaSalvo) c.classList.add('active');
    });
  }
  document.querySelectorAll('.tema-card').forEach(card => {
    card.onclick = () => {
      document.querySelectorAll('.tema-card.active').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
      document.documentElement.setAttribute('data-theme', card.dataset.tema);
      localStorage.setItem('paletaEscolhida', card.dataset.tema);
    };
  });

 const dragonWrapper = document.getElementById('dragonWrapper');

  function atualizarDragaoComTema() {
  const dragonWrapper = document.getElementById('dragonWrapper');
  dragonWrapper.style.display =
    document.documentElement.getAttribute('data-theme') === 'paleta1'
      ? 'block'
      : 'none';
}


  // Atualiza logo que a página carrega
  atualizarDragaoComTema();

  // Observa mudanças no atributo data‑theme do <html>
  new MutationObserver(() => atualizarDragaoComTema())
    .observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['data-theme']
    });

}); // fim DOMContentLoaded

 
</script>

<script>
// Script do dragao 

 "use strict";
      
        const screen   = document.getElementById("screen");
        const xmlns    = "http://www.w3.org/2000/svg";
        const xlinkns  = "http://www.w3.org/1999/xlink";
      
        const N        = 10;      // segmentos por rabo
        const maxSize  = 0.1;     // escala no início
        const minSize  = 0.05;     // escala no fim

        const SPACING = 15;       
        const SMOOTHING = 1.2;     
        const DAMPING   = 2.5;      
      
        const HEAD_SMOOTHING = 1.0;

        let width, height;
        function resize() {
          width  = innerWidth;
          height = innerHeight;
        }
        window.addEventListener("resize", resize);
        resize();
      
        const pointer = { x: width/2, y: height/2 };
        window.addEventListener("pointermove", e => {
          pointer.x = e.clientX;
          pointer.y = e.clientY;
          rad = 0;
        });
      
        let frm = Math.random(), rad = 0;
      
        // criamos duas “throngs”: direita e esquerda
        const tails = [ [], [] ];  // [0]=direita, [1]=esquerda
      
        for (let side = 0; side < 2; side++) {
          for (let i = 0; i < N; i++) {
            const useEl = document.createElementNS(xmlns, "use");
            // a cabeça (i=0)
            if (i === 0) {
              useEl.setAttributeNS(xlinkns, "xlink:href", "#Cabeza");
            } else {
              useEl.setAttributeNS(xlinkns, "xlink:href", "#Aletas");
            }
            screen.appendChild(useEl);
            tails[side].push({ use: useEl, x: width/2, y: height/2 });
          }
        }
      
        function run() {
          requestAnimationFrame(run);
      
          // aumenta wobble radial
          const maxRad = Math.min(pointer.x, pointer.y) - 20;
          if (rad < maxRad) rad++;
          frm += 0.003;
      
          for (let side = 0; side < 2; side++) {
            const sign = side === 0 ? 1 : -1;  // normal vs espelhado
            const tail = tails[side];
      
            // — Cabeça
            const e0 = tail[0];
const e1 = tail[1]; 


// 1) Normaliza mouse (0…innerWidth → 0…100 do viewBox)
const nx = pointer.x / window.innerWidth  * 100;
const ny = pointer.y / window.innerHeight * 100;

// 2) Suaviza movendo e0 em direção ao ponto normalizado
e0.x += (nx - e0.x) / HEAD_SMOOTHING;
e0.y += (ny - e0.y) / HEAD_SMOOTHING;

// 3) Calcula ângulo e escala
const angle = Math.atan2((tail[1].y - e0.y), (tail[1].x - e0.x));
const scale = 0.5;

// 4) Aplica transform no espaço 0–100 do SVG
e0.use.setAttributeNS(null, "transform",
  `translate(${e0.x}, ${e0.y}) 
   rotate(${angle * 180/Math.PI}) 
   scale(${scale})`
);




      
            // — Corpo
            for (let i = 1; i < N; i++) {
              const e  = tail[i];
              const ep = tail[i-1];
              let a   = Math.atan2(e.y - ep.y, e.x - ep.x);
      
              // segue o elo anterior
              e.x += (ep.x - e.x + Math.cos(a)*(SPACING-i)/SMOOTHING)/DAMPING;
              e.y += (ep.y - e.y + Math.sin(a)*(SPACING-i)/SMOOTHING)/DAMPING;
      
              // tapering suave
              const t    = (i - 1)/(N - 2);
              const ramp = Math.pow(t, 0.5);
              const s    = maxSize*(1 - ramp) + minSize*ramp;
      
              // no espelho, inverte o ângulo
              if (side === 1) a += Math.PI;
      
              e.use.setAttributeNS(null, "transform",
                `translate(${(ep.x + e.x)/2},${(ep.y + e.y)/2})
                 rotate(${a * 180/Math.PI})
                 scale(${s})`
              );
            }
          }
        }
      
        run();
</script>

</body>
</html>


