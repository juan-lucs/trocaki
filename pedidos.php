<?php
session_start();
include 'protect.php';
include 'conexao.php';

$usuarioLogado = $_SESSION['id'];

// ─── 2) Ações de aceitar / recusar / excluir ────────────────────────────────
if (isset($_GET['aceitar'])) {
    $trocaId = (int) $_GET['aceitar'];

    // 2.1) Buscamos os IDs dos itens para então decrementar a quantidade
    $stmtFetch = $mysqli->prepare("
      SELECT item_solicitado_id, item_ofertado_id
        FROM trocas
       WHERE id = ? 
         AND destinatario_id = ?
         AND status = 'pendente'
    ");
    $stmtFetch->bind_param('ii', $trocaId, $usuarioLogado);
    $stmtFetch->execute();
    $resultFetch = $stmtFetch->get_result();

    if ($resultFetch->num_rows === 1) {
        $row = $resultFetch->fetch_assoc();
        $itemSolicitadoId = (int) $row['item_solicitado_id'];
        $itemOfertadoId   = (int) $row['item_ofertado_id'];

        // 2.2) Atualiza o status da troca para 'aceita'
        $stmtUpdate = $mysqli->prepare(
            "UPDATE trocas 
                SET status = 'aceita' 
              WHERE id = ? 
                AND destinatario_id = ?"
        );
        $stmtUpdate->bind_param('ii', $trocaId, $usuarioLogado);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        // 2.3) Decrementa a quantidade de ambos os itens (não vai abaixo de 0)
        $stmtDec = $mysqli->prepare("
          UPDATE itens 
             SET quantidade = GREATEST(quantidade - 1, 0) 
           WHERE id = ?
        ");

        // Decrementa quantidade do item solicitado
        $stmtDec->bind_param('i', $itemSolicitadoId);
        $stmtDec->execute();
        $stmtDec->reset();

        // Decrementa quantidade do item ofertado
        $stmtDec->bind_param('i', $itemOfertadoId);
        $stmtDec->execute();
        $stmtDec->close();
    }
    $stmtFetch->close();

    header('Location: minhas_trocas.php');
    exit;
}

if (isset($_GET['recusar'])) {
    $trocaId = (int) $_GET['recusar'];

    $stmt = $mysqli->prepare(
      "UPDATE trocas 
          SET status = 'recusada'
        WHERE id = ? 
          AND (destinatario_id = ? OR solicitante_id = ?)"
    );
    $stmt->bind_param('iii', $trocaId, $usuarioLogado, $usuarioLogado);
    $stmt->execute();
    $stmt->close();

    header('Location: minhas_trocas.php');
    exit;
}

if (isset($_GET['excluir'])) {
    $trocaId = (int) $_GET['excluir'];

    $stmt = $mysqli->prepare(
      "DELETE FROM trocas
        WHERE id = ? 
          AND solicitante_id = ? 
          AND status = 'recusada'"
    );
    $stmt->bind_param('ii', $trocaId, $usuarioLogado);
    $stmt->execute();
    $stmt->close();

    header('Location: minhas_trocas.php');
    exit;
}

// ─── 3) Busca todas as trocas envolvendo o usuário ──────────────────────────
$stmt = $mysqli->prepare("
  SELECT
    t.*,
    u1.nome AS sol_nome, u2.nome AS dest_nome,
    i1.nome_item AS sol_item, i2.nome_item AS off_item,
    i1.categoria  AS sol_cat,  i2.categoria  AS off_cat,
    t.solicitante_id, t.destinatario_id,
    DATE_FORMAT(t.data_criacao,'%d/%m %H:%i') AS criado_em
  FROM trocas t
    JOIN usuarios u1 ON t.solicitante_id = u1.id
    JOIN usuarios u2 ON t.destinatario_id  = u2.id
    JOIN itens    i1 ON t.item_solicitado_id = i1.id
    JOIN itens    i2 ON t.item_ofertado_id   = i2.id
  WHERE t.solicitante_id = ? 
     OR t.destinatario_id = ?
  ORDER BY t.data_criacao DESC
");
$stmt->bind_param('ii', $usuarioLogado, $usuarioLogado);
$stmt->execute();
$resultado = $stmt->get_result();

// ─── 3.1) Separa em “recebidas” (destinatário é você) e “enviadas” (solicitante é você)
$recebidas = [];
$enviadas  = [];
while ($row = $resultado->fetch_assoc()) {
    if ((int)$row['destinatario_id'] === $usuarioLogado) {
        $recebidas[] = $row;
    }
    if ((int)$row['solicitante_id'] === $usuarioLogado) {
        $enviadas[] = $row;
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Minhas Trocas</title>
  <!-- Sua folha de estilos principal -->
  <link rel="stylesheet" href="estilos_pedidos.css">

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

  <div class="back">
    <a href="painel.php">&larr; Voltar ao Painel</a>
  </div>
  <h2>Minhas Trocas</h2>

  <div class="container">
    <!-- Seção de Solicitações Recebidas -->
    <div class="section">
      <h3>Solicitações Recebidas</h3>
      <?php if (empty($recebidas)): ?>
        <p>Nenhuma solicitação recebida.</p>
      <?php else: ?>
        <div class="grid">
          <?php foreach ($recebidas as $t): 
            // Usuário logado é destinatário, então “seu item” = ofertado (off_item)
            $itemRecebidoId      = $t['item_ofertado_id'];
            $itemRecebidoName    = $t['off_item'];
            $itemRecebidoCat     = $t['off_cat'];
            $itemSolicitadoId    = $t['item_solicitado_id'];
            $itemSolicitadoName  = $t['sol_item'];
            $itemSolicitadoCat   = $t['sol_cat'];
            $outroUsuarioId      = (int)$t['solicitante_id']; // quem fez a solicitação
            $princDest = 'Item do solicitante'; // solicitante mandou este
            $princOwn  = 'Seu Item';
          ?>
          <div class="card">
            <div class="row">
              <div class="block">
                <img src="imagem_item.php?id=<?= $itemSolicitadoId ?>&k=1" onerror="this.style.display='none'">
                <div class="owner-label"><?= $princDest ?></div>
                <div class="label"><?= htmlspecialchars($itemSolicitadoName, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="sub"><?= htmlspecialchars($itemSolicitadoCat, ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div class="arrow">→</div>
              <div class="block">
                <img src="imagem_item.php?id=<?= $itemRecebidoId ?>&k=1" onerror="this.style.display='none'">
                <div class="owner-label"><?= $princOwn ?></div>
                <div class="label"><?= htmlspecialchars($itemRecebidoName, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="sub"><?= htmlspecialchars($itemRecebidoCat, ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>

            <div class="status <?= $t['status'] ?>">
              <?= ucfirst($t['status']) ?> em <?= $t['criado_em'] ?>
            </div>

            <div class="actions">
              <?php if ($t['status'] === 'pendente'): ?>
                <button class="aceitar" onclick="location='?aceitar=<?= $t['id'] ?>'">
                  Aceitar
                </button>
                <button class="recusar" onclick="location='?recusar=<?= $t['id'] ?>'">
                  Recusar
                </button>
              <?php elseif ($t['status'] === 'aceita'): 
                // Depois de aceita, destinatário não avalia— quem avalia é o solicitante quando receber o item?
                // Mas aqui já adicionamos botão “Enviar Mensagem” para falar com o solicitante
              ?>
                <button class="avaliar" onclick="abrirModalAvaliacao(<?= $itemRecebidoId ?>)">
                  Avaliar Item Recebido
                </button>
                <!-- NOVO: botão de enviar mensagem -->
                <a class="mensagem" href="chat_conversas.php?contato=<?= $outroUsuarioId ?>">
                  Enviar Mensagem
                </a>
              <?php elseif ($t['status'] === 'recusada'): ?>
                <button class="excluir" onclick="location='?excluir=<?= $t['id'] ?>'">
                  ✕ Excluir
                </button>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Linha divisória visual -->
    <div style="width:2px; background:#ccc;"></div>

    <!-- Seção de Solicitações Enviadas -->
    <div class="section">
      <h3>Solicitações Enviadas</h3>
      <?php if (empty($enviadas)): ?>
        <p>Nenhuma solicitação enviada.</p>
      <?php else: ?>
        <div class="grid">
          <?php foreach ($enviadas as $t):
            // Usuário logado é solicitante, então “seu item” = item_solicitado
            $itemSolicitadoId    = $t['item_solicitado_id'];
            $itemSolicitadoName  = $t['sol_item'];
            $itemSolicitadoCat   = $t['sol_cat'];
            $itemOfertadoId      = $t['item_ofertado_id'];
            $itemOfertadoName    = $t['off_item'];
            $itemOfertadoCat     = $t['off_cat'];
            $outroUsuarioId      = (int)$t['destinatario_id']; // quem recebeu a solicitação
            $princOwn  = 'Seu Item';
            $princDest = 'Item do outro';
          ?>
          <div class="card">
            <div class="row">
              <div class="block">
                <img src="imagem_item.php?id=<?= $itemSolicitadoId ?>&k=1" onerror="this.style.display='none'">
                <div class="owner-label"><?= $princOwn ?></div>
                <div class="label"><?= htmlspecialchars($itemSolicitadoName, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="sub"><?= htmlspecialchars($itemSolicitadoCat, ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div class="arrow">→</div>
              <div class="block">
                <img src="imagem_item.php?id=<?= $itemOfertadoId ?>&k=1" onerror="this.style.display='none'">
                <div class="owner-label"><?= $princDest ?></div>
                <div class="label"><?= htmlspecialchars($itemOfertadoName, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="sub"><?= htmlspecialchars($itemOfertadoCat, ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>

            <div class="status <?= $t['status'] ?>">
              <?= ucfirst($t['status']) ?> em <?= $t['criado_em'] ?>
            </div>

            <div class="actions">
              <?php if ($t['status'] === 'pendente'): ?>
                <button class="recusar" onclick="location='?recusar=<?= $t['id'] ?>'">
                  Recusar
                </button>
              <?php elseif ($t['status'] === 'aceita'): 
                // Depois de aceita, quem avalia é o solicitante, avalia o item recebido (off_item)
              ?>
                <button class="avaliar" onclick="abrirModalAvaliacao(<?= $itemOfertadoId ?>)">
                  Avaliar Item Recebido
                </button>
                <!-- NOVO: botão de enviar mensagem -->
                <a class="mensagem" href="chat_conversas.php?contato=<?= $outroUsuarioId ?>">
                  Enviar Mensagem
                </a>
              <?php elseif ($t['status'] === 'recusada'): ?>
                <button class="excluir" onclick="location='?excluir=<?= $t['id'] ?>'">
                  ✕ Excluir
                </button>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Modal de Avaliação -->
  <div id="modalAvaliar" style="
       display:none;
       position:fixed;
       inset:0;
       background:rgba(0,0,0,.6);
       justify-content:center;
       align-items:center;
       z-index:1000">
    <div style="
         background:#fff;
         padding:20px;
         border-radius:10px;
         width:90%;
         max-width:400px;
         position:relative">
      <button style="
             position:absolute;
             top:10px;
             right:10px;
             border:none;
             background:none;
             font-size:20px;
             cursor:pointer"
              onclick="fecharModalAvaliacao()">&times;</button>
      <h3 style="margin-top:0;color:#007bff">Avaliar Produto</h3>
      <div id="starContainer" style="display:flex;gap:5px;font-size:2rem;cursor:pointer">
        <span class="star" data-value="1">☆</span>
        <span class="star" data-value="2">☆</span>
        <span class="star" data-value="3">☆</span>
        <span class="star" data-value="4">☆</span>
        <span class="star" data-value="5">☆</span>
      </div>
      <textarea id="comentario" rows="4" placeholder="Comentário..."
                style="
                  width:100%;
                  margin-top:10px;
                  padding:8px;
                  border:1px solid #ccc;
                  border-radius:4px"></textarea>
      <div style="text-align:right;margin-top:10px">
        <button onclick="salvarAvaliacao()"
                style="
                  padding:8px 16px;
                  background:#28a745;
                  color:#fff;
                  border:none;
                  border-radius:4px;
                  cursor:pointer">
          Enviar
        </button>
      </div>
    </div>
  </div>

  <script>
    let produtoAvaliado = null;

    function abrirModalAvaliacao(itemId) {
      produtoAvaliado = itemId;
      document.querySelectorAll('#starContainer .star').forEach(s=> s.textContent='☆');
      document.getElementById('comentario').value = '';
      document.getElementById('modalAvaliar').style.display = 'flex';
    }
    function fecharModalAvaliacao() {
      document.getElementById('modalAvaliar').style.display = 'none';
    }

    // Seleção de estrelas
    document.querySelectorAll('#starContainer .star').forEach(star=>{
      star.addEventListener('click', ()=>{
        const v = +star.dataset.value;
        document.querySelectorAll('#starContainer .star').forEach(s=>{
          s.textContent = (+s.dataset.value <= v) ? '★' : '☆';
        });
      });
    });

    // Salva via AJAX e envia cookies de sessão
    async function salvarAvaliacao() {
      const nota    = Array.from(document.querySelectorAll('#starContainer .star'))
                             .filter(s=> s.textContent === '★').length;
      const coment  = document.getElementById('comentario').value;
      if (!nota) return alert('Selecione ao menos 1 estrela');

      const resp = await fetch('salvar_avaliacao.php', {
        method: 'POST',
        credentials: 'same-origin',      // envia cookie de sessão
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          produto_id: produtoAvaliado,
          nota,
          comentario: coment
        })
      });

      let data = {};
      try { data = await resp.json(); } catch(e){}

      if (resp.ok && data.success) {
        alert('Avaliação enviada com sucesso!');
        fecharModalAvaliacao();
      } else {
        alert('Erro ao salvar avaliação: ' + (data.error || resp.status));
      }
    }
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
  </script>

</body>
</html>
