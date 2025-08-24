<?php
session_start();
include 'protect.php';
include 'conexao.php';

// ID do usuário logado
$userId = (int)$_SESSION['id'];

// 1) Pega categorias de itens relacionados aos vídeos para filtro
$catRes = $mysqli->query("
  SELECT DISTINCT i.categoria
    FROM video v
    JOIN itens i ON v.item_id = i.id
   WHERE v.usuario_id <> $userId
   ORDER BY i.categoria
");
$categorias = [];
while ($r = $catRes->fetch_assoc()) {
    $categorias[] = $r['categoria'];
}

// 2) Busca vídeos (exceto do próprio usuário) e dados
$sql = "
  SELECT
    v.video_id,
    v.item_id,
    i.categoria AS item_categoria,
    v.location       AS mp4,
    v.thumbnail,
    u.id             AS usuario_id,
    u.nome           AS usuario,
    (SELECT COUNT(*) FROM video_likes l WHERE l.video_id=v.video_id) AS likes_count
  FROM video v
  JOIN itens i   ON v.item_id = i.id
  JOIN usuarios u ON u.id = v.usuario_id
  WHERE v.usuario_id <> $userId
  ORDER BY v.created_at DESC
";
$res    = $mysqli->query($sql);
$videos = $res->fetch_all(MYSQLI_ASSOC);

// Serializa para JS
$jsVideos = [];
foreach ($videos as $v) {
    $jsVideos[] = [
        'video_id'      => (int)$v['video_id'],
        'item_id'       => (int)$v['item_id'],
        'mp4'           => $v['mp4'],
        'thumbnail'     => $v['thumbnail'],
        'usuario'       => $v['usuario'],
        'usuario_id'    => (int)$v['usuario_id'],
        'likes_count'   => (int)$v['likes_count'],
        'item_categoria'=> $v['item_categoria'],
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Comunidade de Vídeos</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <link rel="stylesheet" href="estilos_comunidade.css">
  
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Segoe+UI&display=swap" rel="stylesheet">

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

  <!-- Cabeçalho + Filtro -->
  <div class="container-fluid bg-white py-3 mb-4 shadow-sm">
    <div class="d-flex align-items-center">
      <a href="adicionar_video.php" class="btn btn-primary me-3">Adicionar Vídeo</a>
      <h2 class="flex-fill text-center m-0">Comunidade de Vídeos</h2>
      <a href="painel.php" class="btn btn-primary ms-3">Voltar</a>
    </div>
    <div class="text-center mt-3">
      <label class="me-2">Filtrar por categoria de item:</label>
      <select id="catFilter" class="form-select d-inline-block w-auto">
        <option value="">Todas</option>
        <?php foreach($categorias as $c): ?>
          <option value="<?= htmlspecialchars(strtolower($c)) ?>">
            <?= htmlspecialchars($c) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Grid de Vídeos -->
  <div class="container mb-5">
    <div class="row g-4" id="videoGrid">
      <?php foreach($videos as $i=>$v):
        $path = str_replace('\\','/',$v['mp4']);
        $urlV = strpos($path,'uploads/')!==false ? substr($path,strpos($path,'uploads/')) : $path;
      ?>
      <div class="col-md-4">
        <div class="card-video"
             data-index="<?= $i ?>"
             data-item-cat="<?= htmlspecialchars(strtolower($v['item_categoria'])) ?>"
             data-user="<?= $v['usuario_id'] ?>">
          <div class="card-media">
            <?php if($v['thumbnail']): 
              $t = str_replace('\\','/',$v['thumbnail']);
              $t = strpos($t,'uploads/')!==false ? substr($t,strpos($t,'uploads/')):$t;
            ?>
              <img src="<?= $t ?>" alt="thumb">
            <?php else: ?>
              <video muted preload="metadata" onloadedmetadata="this.currentTime=0.1; this.pause();">
                <source src="<?= $urlV ?>" type="video/mp4">
              </video>
            <?php endif; ?>
          </div>
          <div class="p-2 bg-white">
            <div class="fw-bold text-capitalize"><?= htmlspecialchars($v['item_categoria']) ?></div>
            <small class="text-muted">Por <?= htmlspecialchars($v['usuario']) ?></small>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Modal Vídeo Fullscreen -->
  <div class="modal fade" id="videoModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
      <div class="modal-content bg-dark p-0">
        <video id="modalVideo" controls class="w-100 h-100" style="object-fit:contain;"></video>
        <button id="prevBtn" class="btn btn-outline-light position-absolute top-50 start-0 translate-middle-y ms-2">&lsaquo;</button>
        <button id="nextBtn" class="btn btn-outline-light position-absolute top-50 end-0 translate-middle-y me-2">&rsaquo;</button>
        <div class="position-absolute top-50 end-0 translate-middle-y me-5 d-flex flex-column gap-2">
          <button id="likeBtn" class="btn btn-light" title="Curtir">
            <i id="likeIcon" class="bi bi-hand-thumbs-up"></i>
            <span id="likeCountBadge" class="badge bg-danger position-absolute top-0 start-100 translate-middle"></span>
          </button>
          <button id="commentBtn" class="btn btn-light" title="Comentários"><i class="bi bi-chat"></i></button>
          <button id="itemDetailBtn" class="btn btn-light" title="Detalhes"><i class="bi bi-info-square"></i></button>
          <button id="itemAvalBtn" class="btn btn-light" title="Avaliações"><i class="bi bi-star"></i></button>
          <button id="tradeBtn" class="btn btn-light" title="Trocar"><i class="bi bi-arrow-left-right"></i></button>
          <button class="btn btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div id="overlayBox" class="position-absolute top-0 start-0 w-100 h-100"
             style="background:rgba(0,0,0,0.7);display:none;overflow:auto;color:#fff;padding:2rem;">
          <button id="closeOverlay" class="btn btn-close btn-close-white position-absolute top-2 end-2"></button>
          <div id="commSection" style="display:none;">
            <br>
            <h2>Comentários</h2>
            <div id="commentsList" class="mb-3"></div>
            <textarea id="commInput" class="form-control mb-2" rows="3" placeholder="Escreva..."></textarea>
            <button id="sendComm" class="btn btn-primary">Enviar</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Detalhes do Item -->
  <div id="modalItemDetail" class="modal-large">
    <div class="modal-content-large">
      <button class="btn-close-large" id="closeItemDetail">&times;</button>
      <div id="itemDetailContent"></div>
    </div>
  </div>

  <!-- Modal Avaliações -->
  <div id="modalItemAval" class="modal-large">
    <div class="modal-content-large">
      <button class="btn-close-large" id="closeItemAval">&times;</button>
      <div id="itemAvalContent"></div>
    </div>
  </div>

  <!-- Modal Troca -->
  <div id="modalTrade" class="modal-large">
    <div class="modal-content-large">
      <button class="btn-close-large" id="closeTrade">&times;</button>
      <h2>Escolha um item seu para oferecer</h2>
      <input type="text" id="searchMyItems" class="form-control mb-2" placeholder="Pesquisar...">
      <div id="myItemsToTrade" class="row g-2"></div>
      <button id="confirmTrade" class="btn btn-success mt-3">Enviar Pedido</button>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const videos      = <?= json_encode($jsVideos) ?>;
  let idx           = 0;
  const liked       = {};
  const modal       = new bootstrap.Modal('#videoModal');

  // Elementos
  const vid         = document.getElementById('modalVideo');
  const prevBtn     = document.getElementById('prevBtn');
  const nextBtn     = document.getElementById('nextBtn');
  const likeBtn     = document.getElementById('likeBtn');
  const commentBtn  = document.getElementById('commentBtn');
  const itemDBtn    = document.getElementById('itemDetailBtn');
  const itemABtn    = document.getElementById('itemAvalBtn');
  const tradeBtn    = document.getElementById('tradeBtn');
  const overlay     = document.getElementById('overlayBox');
  const closeOv     = document.getElementById('closeOverlay');
  const commSec     = document.getElementById('commSection');
  const commentsList= document.getElementById('commentsList');
  const sendComm    = document.getElementById('sendComm');
  const commInput   = document.getElementById('commInput');
  const modalItem   = document.getElementById('modalItemDetail');
  const modalItemC  = document.getElementById('itemDetailContent');
  const modalAval   = document.getElementById('modalItemAval');
  const modalAvalC  = document.getElementById('itemAvalContent');
  const modalTrade  = document.getElementById('modalTrade');
  const myItemsDiv  = document.getElementById('myItemsToTrade');
  const searchMy    = document.getElementById('searchMyItems');
  const confirmTr   = document.getElementById('confirmTrade');
  let targetVideo, targetItem, targetUser;

  function openVideo(i) {
    idx = i;
    const v = videos[i];
    targetVideo = v.video_id;
    targetItem  = v.item_id;
    targetUser  = v.usuario_id;
    let src = v.mp4.replace(/\\/g, '/');
    if (src.includes('uploads/')) src = 'uploads/' + src.split('uploads/').pop();
    vid.src = src;
    commSec.style.display = 'none';
    overlay.style.display = 'none';
    liked[v.video_id] = liked[v.video_id] || false;
    document.getElementById('likeIcon').className = liked[v.video_id]
      ? 'bi bi-hand-thumbs-up-fill text-danger'
      : 'bi bi-hand-thumbs-up';
    document.getElementById('likeCountBadge').textContent = v.likes_count + (liked[v.video_id] ? 1 : 0);
    modal.show();
    vid.play();
  }

  document.querySelectorAll('.card-video').forEach((c,i) => 
    c.addEventListener('click', () => openVideo(i))
  );

  prevBtn.onclick = () => idx>0 && openVideo(idx-1);
  nextBtn.onclick = () => idx<videos.length-1 && openVideo(idx+1);

  likeBtn.onclick = async () => {
    const r = await fetch('video_like.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ video_id: targetVideo })
    });
    const j = await r.json();
    liked[targetVideo] = j.liked;
    document.getElementById('likeIcon').className = j.liked
      ? 'bi bi-hand-thumbs-up-fill text-danger'
      : 'bi bi-hand-thumbs-up';
    document.getElementById('likeCountBadge').textContent = j.count;
  };

  commentBtn.onclick = async () => {
    commSec.style.display = commSec.style.display==='block' ? 'none' : 'block';
    overlay.style.display = commSec.style.display==='block' ? 'block' : 'none';
    if (commSec.style.display==='block') {
      const arr = await (await fetch(`video_comments_list.php?video_id=${targetVideo}`)).json();
      commentsList.innerHTML = arr.map(c => `${c.usuario}: ${c.comentario}`).join('<br>');
    }
  };
  closeOv.onclick = () => overlay.style.display='none';

  sendComm.onclick = async () => {
    const txt = commInput.value.trim();
    if (!txt) return;
    const c = await (await fetch('video_comment.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ video_id: targetVideo, comentario: txt })
    })).json();
    const div = document.createElement('div');
    div.textContent = `${c.usuario}: ${c.comentario}`;
    commentsList.prepend(div);
    commInput.value = '';
  };

  itemDBtn.onclick = async () => {
    modalItem.style.display='flex';
    const it = await (await fetch(`detalhes_item.php?id=${targetItem}`)).json();
    modalItemC.innerHTML = `
      <h2>${it.nome_item}</h2>
      <p><strong>Categoria:</strong> ${it.categoria}</p>
      <p><strong>Localização:</strong> ${it.localizacao}</p>
      <div style="display:flex;gap:8px;margin:10px 0;">
        ${it.images.map(u=>`<img src="${u}" style="width:80px;height:80px;object-fit:cover;border-radius:4px">`).join('')}
      </div>
      <p>${it.descricao}</p>
    `;
  };
  document.getElementById('closeItemDetail').onclick = () => modalItem.style.display='none';

  itemABtn.onclick = async () => {
    modalAval.style.display='flex';
    const resp = await fetch(`comentarios_detalhe.php?id=${targetItem}`);
    if (!resp.ok) {
      modalAvalC.innerHTML = '<p>Sem avaliações ainda.</p>';
    } else {
      const avals = await resp.json();
      modalAvalC.innerHTML = avals.map(a=>`
        <p><strong>${a.usuario}</strong> (${a.data}): ${'★'.repeat(a.nota)}${'☆'.repeat(5-a.nota)}</p>
        <p>${a.comentario}</p><hr>
      `).join('');
    }
  };
  document.getElementById('closeItemAval').onclick = () => modalAval.style.display='none';

  tradeBtn.onclick = async () => {
    modalTrade.style.display='flex';
    const mine = await (await fetch('lista_itens_proprios.php')).json();
    myItemsDiv.innerHTML = '';
    mine.forEach(it=>{
      const col = document.createElement('div');
      col.className='col-4 mb-2';
      col.innerHTML = `
        <div class="card p-2 h-100" data-id="${it.id}">
          <p>${it.nome_item}</p>
        </div>`;
      col.onclick = () => {
        myItemsDiv.querySelectorAll('.card').forEach(c=>c.classList.remove('border-primary'));
        col.querySelector('.card').classList.toggle('border-primary');
      };
      myItemsDiv.append(col);
    });
  };
  document.getElementById('closeTrade').onclick = () => modalTrade.style.display='none';

  searchMy.oninput = () => {
    const term = searchMy.value.toLowerCase();
    myItemsDiv.querySelectorAll('.col-4').forEach(c=>{
      c.style.display = c.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
  };

  confirmTr.onclick = async () => {
    const sel = myItemsDiv.querySelector('.border-primary');
    if (!sel) return alert('Selecione um item para oferecer.');
    const ofertado = sel.closest('.col-4').querySelector('.card').dataset.id;
    const resp = await fetch('solicitar_troca.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ item_solicitado: targetItem, item_ofertado: ofertado, owner: targetUser })
    });
    if (resp.status===201) {
      alert('Pedido de troca enviado!');
      modalTrade.style.display='none';
    } else {
      alert('Erro: '+await resp.text());
    }
  };

  document.getElementById('catFilter').addEventListener('change', function(){
    const cat = this.value.trim().toLowerCase();
    document.querySelectorAll('#videoGrid .card-video').forEach(c=>{
      c.parentElement.style.display = (!cat || c.dataset.itemCat === cat) ? '' : 'none';
    });
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
