<?php
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

// 3) Itens de outros usuários
$stmt = $mysqli->prepare("
  SELECT id, nome_item, descricao, localizacao, categoria, usuario_id
    FROM itens
   WHERE usuario_id<>?
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
    JOIN itens i ON i.id=a.produto_id
   WHERE i.usuario_id=?
");
$mStmt->bind_param('i', $_SESSION['id']);
$mStmt->execute();
$mStmt->bind_result($mediaGeral);
$mStmt->fetch();
$mStmt->close();
?>

<!DOCTYPE html>

<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel Trocaki</title>
  <style>
    body { margin:0; font-family:'Segoe UI',sans-serif; background:#e6f4ff; color:#333; }
    .top-bar { display:flex; align-items:center; justify-content:space-between; padding:20px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,.1); position:relative; }
    .profile-btn { width:40px; height:40px; border-radius:50%; overflow:hidden; cursor:pointer; border:2px solid #008cba; }
    .profile-btn img { width:100%; height:100%; object-fit:cover; }
    .top-bar h1 { margin:0; color:#008cba; font-size:1.5rem; }
    .btn { padding:8px 16px; border:none; border-radius:20px; color:#fff; cursor:pointer; text-decoration:none; }
    .sair { background:#008cba; }
    #sidebar { position:fixed; top:0; left:-100%; width:20%; height:100%; background:#fff; box-shadow:2px 0 6px rgba(0,0,0,.1); transition:left .3s; overflow:auto; padding:20px; z-index:1001; }
    #sidebar.open { left:0; }
    #sidebar h2 { margin-top:0; color:#008cba; }
    #sidebar label { display:block; margin:10px 0 5px; }
    #sidebar input[type="text"], #sidebar input[type="file"] { width:100%; padding:8px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px; }
    #sidebar button { padding:8px 16px; background:#28a745; color:#fff; border:none; border-radius:4px; cursor:pointer; }
    .close-sidebar { position:absolute; top:10px; right:10px; background:none; border:none; font-size:20px; cursor:pointer; }
    .nav-cards { display:flex; justify-content:center; gap:20px; padding:20px; }
    .nav-cards a { background:#fff; border:2px solid #cce8ff; border-radius:15px; padding:20px; width:180px; text-align:center; color:#008cba; text-decoration:none; font-weight:bold; transition:.3s; }
    .nav-cards a:hover { transform:translateY(-5px); box-shadow:0 8px 16px rgba(0,140,186,.2); }
    .search-bar { max-width:1000px; margin:10px auto; display:flex; justify-content:center; gap:10px; }
    .search-bar input, .search-bar select { padding:8px; border-radius:4px; border:1px solid #ccc; font-size:1rem; }
    .items-grid { max-width:1000px; margin:20px auto; display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:20px; padding:0 20px; }
    .item-card { background:#fff; border:1px solid #e0e0e0; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
    .carousel { position:relative; width:100%; padding-top:60%; overflow:hidden; }
    .carousel img { position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; opacity:0; transition:.5s; }
    .carousel img.active { opacity:1; }
    .content { padding:15px; flex:1; display:flex; flex-direction:column; }
    .nome-item { font-size:1.1rem; font-weight:bold; color:#333; margin-bottom:6px; }
    .categoria { font-size:0.9rem; color:#555; margin-bottom:auto; }
    .actions { padding:10px; text-align:center; background:#f9f9f9; }
    .actions button { margin:0 5px; padding:6px 12px; border:none; border-radius:4px; color:#fff; cursor:pointer; }
    .btn-trocar { background:#17a2b8; } .btn-trocar:hover { background:#138496; }
    .btn-detalhes { background:#007bff; } .btn-detalhes:hover { background:#0056b3; }
    .btn-avaliar { background:#ffc107; } .btn-avaliar:hover { background:#e0a800; }
    .btn-avaliacoes { background:#333; } .btn-avaliacoes:hover { background:#111; }
    .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); justify-content:center; align-items:center; z-index:1000; }
    .modal-content { background:#fff; padding:20px; border-radius:10px; width:90%; max-width:500px; position:relative; max-height:90%; overflow:auto; }
    .modal-content h2 { color:#17a2b8; margin-bottom:10px; }
    .close { position:absolute; top:10px; right:10px; background:none; border:none; font-size:20px; cursor:pointer; }
    #starContainer { display:flex; gap:5px; font-size:2rem; cursor:pointer; margin-bottom:10px; }
    #starContainer .star { color:#ccc; transition:color .2s; }
    #starContainer .star.selected { color:gold; }
    .modal-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:10px; margin:10px 0; }
    .modal-card { background:#f5f5f5; padding:10px; border-radius:8px; text-align:center; cursor:pointer; transition:.3s; }
    .modal-card.active { border:2px solid #008cba; }
    .modal-card img { width:100%; height:80px; object-fit:cover; border-radius:4px; }
    .modal-card .nome { font-size:.9rem; margin-top:6px; }
    .modal-footer { text-align:right; margin-top:10px; }
    .btn-action { padding:8px 16px; border:none; border-radius:4px; color:#fff; cursor:pointer; }
    .confirm { background:#28a745; margin-right:8px; } .cancel { background:#dc3545; }
  </style>
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
    <a href="clientes.php">Comunidade</a>
    <a href="relatorios.php">Relatórios</a>
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

  <!-- Itens Grid -->

  <div class="items-grid" id="itemsGrid">
    <?php while($it = $others->fetch_assoc()): ?>
      <div class="item-card"
           data-name="<?= strtolower($it['nome_item']) ?>"
           data-category="<?= strtolower($it['categoria']) ?>"
           data-id="<?= $it['id'] ?>"
           data-owner="<?= $it['usuario_id'] ?>">
        <div class="carousel">
          <?php for($k=1;$k<=3;$k++): ?>
            <img src="imagem_item.php?id=<?= $it['id'] ?>&k=<?= $k ?>"
                 class="<?= $k===1?'active':'' ?>"
                 onerror="this.style.display='none'">
          <?php endfor; ?>
        </div>
        <div class="content">
          <div class="nome-item"><?= htmlspecialchars($it['nome_item']) ?></div>
          <div class="categoria"><?= htmlspecialchars($it['categoria']) ?></div>
        </div>
        <div class="actions">
          <button class="btn-trocar">Trocar</button>
          <button class="btn-detalhes" data-id="<?= $it['id'] ?>">Detalhes</button>
           <!--<button class="btn-avaliar" data-id="<?= $it['id'] ?>">Avaliar</button>-->
          <button class="btn-avaliacoes" data-id="<?= $it['id'] ?>">Avaliações</button>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

  <!-- Modais -->

  <div id="modalTroca" class="modal">
    <div class="modal-content">
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

  <!--<div id="modalAvaliar" class="modal">
    <div class="modal-content">
      <button class="close" id="closeAvaliar">&times;</button>
      <h2>Avaliar Produto</h2>
      <div id="starContainer">
        <span class="star" data-value="1">☆</span>
        <span class="star" data-value="2">☆</span>
        <span class="star" data-value="3">☆</span>
        <span class="star" data-value="4">☆</span>
        <span class="star" data-value="5">☆</span>
      </div>
      <textarea id="comentario" placeholder="Deixe seu comentário..." rows="4"
                style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px"></textarea>
      <div class="modal-footer">
        <button class="btn-action confirm" id="confirmarAvaliacao">Enviar Avaliação</button>
      </div>
    </div>
  </div>-->

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

<script>
document.addEventListener('DOMContentLoaded', ()=>{

  // Sidebar
  document.getElementById('openSidebar').onclick = ()=> document.getElementById('sidebar').classList.add('open');
  document.getElementById('closeSidebar').onclick = ()=> document.getElementById('sidebar').classList.remove('open');

  // Carousel
  document.querySelectorAll('.item-card').forEach(card=>{
    let idx=0;
    setInterval(()=>{
      const imgs = Array.from(card.querySelectorAll('.carousel img')).filter(i=>i.style.display!=='none');
      if(imgs.length<2) return;
      imgs.forEach(i=>i.classList.remove('active'));
      idx=(idx+1)%imgs.length; imgs[idx].classList.add('active');
    },3000);
  });

  // Filtros
  const searchProd = document.getElementById('searchProduct'),
        catFilter  = document.getElementById('categoryFilter');
  function applyFilter(){
    const term=searchProd.value.toLowerCase(), cat=catFilter.value;
    document.querySelectorAll('.item-card').forEach(c=>{
      c.style.display = (c.dataset.name.includes(term) && (!cat||c.dataset.category===cat)) ? '' : 'none';
    });
  }
  searchProd.oninput = applyFilter;
  catFilter.onchange = applyFilter;

  // Trocar
  let targetId, targetOwner;
  document.querySelectorAll('.btn-trocar').forEach(btn=>{
    btn.onclick = async ()=>{
      const c = btn.closest('.item-card');
      targetId    = c.dataset.id;
      targetOwner = c.dataset.owner;
      const res  = await fetch('lista_itens_proprios.php');
      const data = await res.json();
      const grid = document.getElementById('myItemsGrid');
      grid.innerHTML = '';
      data.forEach(it=>{
        const d = document.createElement('div');
        d.className       = 'modal-card';
        d.dataset.id      = it.id;
        d.dataset.name    = it.nome_item.toLowerCase();
        d.innerHTML       = `<img src="imagem_item.php?id=${it.id}&k=1" onerror="this.style.display='none'">
                             <div class="nome">${it.nome_item}</div>`;
        d.onclick         = ()=>{ grid.querySelectorAll('.modal-card').forEach(x=>x.classList.remove('active')); d.classList.add('active') };
        grid.appendChild(d);
      });
      document.getElementById('modalTroca').style.display='flex';
    };
  });
  document.getElementById('searchMyProduct').oninput = e=>{
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('#myItemsGrid .modal-card').forEach(c=>{
      c.style.display = c.dataset.name.includes(term)?'':'none';
    });
  };
  document.getElementById('confirmarTroca').onclick = async ()=>{
    const sel = document.querySelector('#myItemsGrid .modal-card.active');
    if(!sel) return alert('Selecione um item');
    const r = await fetch('solicitar_troca.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        item_solicitado: targetId,
        item_ofertado:   sel.dataset.id,
        owner:           targetOwner
      })
    });
    if(r.status===201){
      document.getElementById('modalTroca').style.display='none';
      document.getElementById('modalConfirm').style.display='flex';
    } else alert('Erro: '+await r.text());
  };
  document.getElementById('closeTroca').onclick = ()=> document.getElementById('modalTroca').style.display='none';

  // Detalhes
  document.querySelectorAll('.btn-detalhes').forEach(btn=>{
    btn.onclick = async ()=>{
      const id = btn.dataset.id;
      const r  = await fetch(`detalhes_item.php?id=${id}`);
      if(!r.ok) return alert('Item não encontrado');
      const it = await r.json();
      const d  = document.getElementById('detalhesContent');
      d.innerHTML = `
        <h2>${it.nome_item}</h2>
        <p><strong>Categoria:</strong> ${it.categoria}</p>
        <p><strong>Localização:</strong> ${it.localizacao}</p>
        <p><strong>Criado em:</strong> ${it.criado_em}</p>
        <div style="display:flex;gap:10px;margin:10px 0;">
          ${it.images.map(s=>`<img src="${s}" style="width:80px;height:80px;object-fit:cover;border-radius:4px">`).join('')}
        </div>
        <p>${it.descricao}</p>`;
      document.getElementById('modalDetalhes').style.display='flex';
    };
  });
  document.getElementById('closeDetalhes').onclick = ()=> document.getElementById('modalDetalhes').style.display='none';

  // Avaliar
  //let avaliarId = null;
  //document.querySelectorAll('.btn-avaliar').forEach(btn=>{
   // btn.onclick = ()=>{
   //   avaliarId = btn.dataset.id;
   //   document.getElementById('modalAvaliar').style.display='flex';
   // };
  //});
 // document.querySelectorAll('#starContainer .star').forEach(s=>{
 //   s.onclick = ()=>{
  //    const v = +s.dataset.value;
 //     document.querySelectorAll('#starContainer .star').forEach(x=>
  //      x.classList.toggle('selected', +x.dataset.value <= v)
  //    );
  //  };
 // });
  //document.getElementById('confirmarAvaliacao').onclick = async ()=>{
  //  const nota = document.querySelectorAll('#starContainer .star.selected').length;
  //  const com  = document.getElementById('comentario').value;
  //  if(nota===0) return alert('Selecione ao menos 1 estrela');
  //  const r = await fetch('salvar_avaliacao.php',{
   //   method:'POST',
   //   headers:{'Content-Type':'application/json'},
   //   body:JSON.stringify({ produto_id:avaliarId, nota, comentario:com })
   // });
   // if(r.ok){
   //   alert('Avaliação enviada!');
  //    document.getElementById('modalAvaliar').style.display='none';
   // } else {
  //    alert('Erro ao salvar avaliação');
   // }
//  };
//  document.getElementById('closeAvaliar').onclick = ()=> document.getElementById('modalAvaliar').style.display='none';

  // Abre modal de Avaliações
  document.querySelectorAll('.btn-avaliacoes').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        // pega o product_id do card pai
        const card = btn.closest('.item-card');
        const id   = card.dataset.id;
        const resp = await fetch(`comentarios_detalhe.php?id=${id}`);
        if (!resp.ok) {
          // se retornar 400/404/204
          return alert('Ainda não recebeu avaliações.');
        }
        const data = await resp.json();
        const div  = document.getElementById('detalhesContentComentarios');
        // popula o modal
        div.innerHTML = data.map(a=>`
          <p><strong>${a.usuario}</strong> (${a.data}): 
             ${'★'.repeat(a.nota)}${'☆'.repeat(5-a.nota)}</p>
          <p>${a.comentario}</p>
          <hr>
        `).join('');
        // mostra o modal
        document.getElementById('modalDetalhesComentarios').style.display = 'flex';
      });
    });

    // Fecha modal de Avaliações
    document.getElementById('closeDetalhesComentarios')
      .addEventListener('click', ()=>{
        document.getElementById('modalDetalhesComentarios').style.display='none';
      });

  // Média por Produto
  document.getElementById('verPorProduto').onclick = async ()=>{
    const r    = await fetch('medias_por_produto.php');
    const list = await r.json();
    const ul   = document.getElementById('listaMedias');
    ul.innerHTML = list.map(p=>`
      <li style="margin-bottom:8px">
        <strong>${p.nome_item}</strong>: ${p.media.toFixed(1)} ★
      </li>
    `).join('');
    document.getElementById('modalMediaProdutos').style.display='flex';
  };
  document.getElementById('closeMediaProd').onclick = ()=> document.getElementById('modalMediaProdutos').style.display='none';

});
</script>

</body>
</html>        









<?php
session_start();
include 'protect.php';
include 'conexao.php';

// Filtragem por categoria, se houver
$categoriaFiltro = '';
if(isset($_GET['categoria']) && $_GET['categoria'] != '' && $_GET['categoria'] != 'all') {
    $categoriaFiltro = $_GET['categoria'];
}

// Consulta os vídeos no banco
$sql = "SELECT * FROM video";
if ($categoriaFiltro !== '') {
    $categoriaFiltroEscaped = $mysqli->real_escape_string($categoriaFiltro);
    $sql .= " WHERE categoria = '$categoriaFiltroEscaped'";
}
$sql .= " ORDER BY created_at DESC";
$result = $mysqli->query($sql);

$videos = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $videos[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Comunidade - Vídeos</title>
    <!-- CSS do Bootstrap e ícones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Deixa todos os cards como ponteiros e imagens/vídeos ajustados */
        .video-card {
            cursor: pointer;
        }
        .card-img-top, .card-video {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <!-- Filtro por Categoria -->
    <form class="mb-4" method="GET" action="comunidade.php">
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <label for="categoriaSelect" class="col-form-label">Categoria:</label>
            </div>
            <div class="col-auto">
                <select id="categoriaSelect" name="categoria" class="form-select">
                    <option value="all">Todas</option>
                    <?php
                    // Opções de categorias dinâmicas
                    $catResult = $mysqli->query("SELECT DISTINCT categoria FROM video");
                    if ($catResult->num_rows > 0) {
                        while ($catRow = $catResult->fetch_assoc()) {
                            $cat = $catRow['categoria'];
                            $selected = ($cat == $categoriaFiltro ? 'selected' : '');
                            echo "<option value=\"" . htmlspecialchars($cat) . "\" $selected>" . htmlspecialchars($cat) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </div>
    </form>

    <!-- Grid de Vídeos -->
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <?php foreach ($videos as $video): ?>
            <div class="col">
                <div class="card h-100 video-card" 
                     data-video-id="<?php echo $video['video_id']; ?>" 
                     data-video-src="<?php echo htmlspecialchars($video['location'], ENT_QUOTES); ?>"
                     data-description="<?php echo htmlspecialchars($video['descricao'], ENT_QUOTES); ?>"
                     data-category="<?php echo htmlspecialchars($video['categoria'], ENT_QUOTES); ?>">
                    <?php if (!empty($video['thumbnail'])): ?>
                        <img src="<?php echo $video['thumbnail']; ?>" class="card-img-top" alt="Thumbnail">
                    <?php else: ?>
                        <video class="card-img-top card-video" muted>
                            <source src="<?php echo $video['location']; ?>" type="video/mp4">
                            Seu navegador não suporta o elemento de vídeo.
                        </video>
                    <?php endif; ?>
                    <div class="card-body">
                        <p class="card-text text-truncate"><?php echo htmlspecialchars($video['descricao']); ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal de Vídeo em Tela Cheia -->
<div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Vídeo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body text-center">
        <video id="modalVideoPlayer" controls style="width: 100%; height: 80vh;">
            <source src="" type="video/mp4">
            Seu navegador não suporta o elemento de vídeo.
        </video>
        <div class="mt-3">
            <button id="likeBtn" class="btn btn-outline-primary me-2">
                <i class="bi bi-hand-thumbs-up"></i> <span id="likeCount">0</span>
            </button>
            <button id="commentsBtn" class="btn btn-outline-secondary me-2">
                <i class="bi bi-chat"></i> Comentários
            </button>
            <button id="descriptionBtn" class="btn btn-outline-secondary me-2">
                <i class="bi bi-info-circle"></i> Descrição
            </button>
            <button id="prevVideoBtn" class="btn btn-outline-secondary me-2">&lt; Anterior</button>
            <button id="nextVideoBtn" class="btn btn-outline-secondary">Próximo &gt;</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Offcanvas para Comentários e Descrição -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="videoSidePanel" aria-labelledby="videoSidePanelLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="videoSidePanelLabel">Detalhes do Vídeo</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
  </div>
  <div class="offcanvas-body">
    <div id="commentsContainer" style="display: none;">
      <!-- Comentários carregados via AJAX -->
    </div>
    <div id="descriptionContainer" style="display: none;">
      <!-- Descrição do vídeo -->
    </div>
  </div>
</div>

<!-- Scripts do Bootstrap e comportamentos -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Monta lista de vídeos para navegação
var videos = <?php echo json_encode($videos); ?>;
var currentVideoIndex = -1;

// Abre modal com o vídeo selecionado
function openVideoModal(index) {
    currentVideoIndex = index;
    var videoData = videos[index];
    var videoSrc = videoData['location'];
    var videoDesc = videoData['descricao'];
    var modalVideo = document.getElementById('modalVideoPlayer');
    modalVideo.querySelector('source').src = videoSrc;
    modalVideo.load();

    // Reset de curtidas e oculta painel lateral
    document.getElementById('likeCount').textContent = 0;
    document.getElementById('commentsContainer').style.display = 'none';
    document.getElementById('descriptionContainer').style.display = 'none';
    var offcanvasEl = document.getElementById('videoSidePanel');
    bootstrap.Offcanvas.getInstance(offcanvasEl)?.hide();

    // Exibe o modal
    new bootstrap.Modal(document.getElementById('videoModal')).show();
}

// Eventos de clique nos cards
document.querySelectorAll('.video-card').forEach(function(card, index) {
    card.addEventListener('click', function() {
        openVideoModal(index);
    });
});

// Navegação Próximo/Anterior
document.getElementById('nextVideoBtn').addEventListener('click', function() {
    if (currentVideoIndex < videos.length - 1) {
        openVideoModal(currentVideoIndex + 1);
    }
});
document.getElementById('prevVideoBtn').addEventListener('click', function() {
    if (currentVideoIndex > 0) {
        openVideoModal(currentVideoIndex - 1);
    }
});

// Botão Curtir (incrementa contagem local)
document.getElementById('likeBtn').addEventListener('click', function() {
    var countElem = document.getElementById('likeCount');
    countElem.textContent = parseInt(countElem.textContent) + 1;
});

// Botão Comentários (abre offcanvas e carrega via AJAX)
document.getElementById('commentsBtn').addEventListener('click', function() {
    var videoId = videos[currentVideoIndex]['video_id'];
    var commentsContainer = document.getElementById('commentsContainer');
    var descContainer = document.getElementById('descriptionContainer');
    descContainer.style.display = 'none';
    commentsContainer.style.display = 'block';
    commentsContainer.innerHTML = 'Carregando comentários...';
    fetch('fetch_comments.php?video_id=' + videoId)
        .then(response => response.text())
        .then(data => {
            commentsContainer.innerHTML = data;
        });
    new bootstrap.Offcanvas(document.getElementById('videoSidePanel')).show();
});

// Botão Descrição (abre offcanvas com descrição)
document.getElementById('descriptionBtn').addEventListener('click', function() {
    var desc = videos[currentVideoIndex]['descricao'];
    document.getElementById('commentsContainer').style.display = 'none';
    var descContainer = document.getElementById('descriptionContainer');
    descContainer.style.display = 'block';
    descContainer.textContent = desc;
    new bootstrap.Offcanvas(document.getElementById('videoSidePanel')).show();
});
</script>
</body>
</html>
