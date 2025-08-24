<?php
// chat_conversas.php
session_start();
require_once 'conexao.php'; // Deve definir: $mysqli = new mysqli(...)

// 1) Verifica se o usuário está logado (usa a mesma chave de sessão em todo o sistema)
if (!isset($_SESSION['id'])) {
    die('Usuário não autenticado. <a href="login.php">Faça login</a>');
}

$me = (int) $_SESSION['id'];

// 2) Pega o contato selecionado via GET (ex: chat_conversas.php?contato=3)
$contatoSelecionado = isset($_GET['contato']) ? (int) $_GET['contato'] : 0;

// 3) Monta lista de contatos a partir das mensagens já trocadas
$sql = "
    SELECT DISTINCT u.id, u.nome
      FROM usuarios AS u
      JOIN (
        SELECT destinatario_id AS contato_id
          FROM mensagens
         WHERE remetente_id = ?
        UNION
        SELECT remetente_id AS contato_id
          FROM mensagens
         WHERE destinatario_id = ?
      ) AS conv ON conv.contato_id = u.id
    ORDER BY u.nome ASC
";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    die("Erro ao preparar consulta de contatos: " . $mysqli->error);
}
$stmt->bind_param('ii', $me, $me);
$stmt->execute();
$res = $stmt->get_result();

$contatos = [];
while ($row = $res->fetch_assoc()) {
    $contatos[$row['id']] = $row['nome'];
}
$stmt->close();

// 4) Se veio ?contato=ID e ele não está em $contatos (ou seja, ainda não trocou mensagens),
//    buscamos o nome do usuário diretamente em `usuarios` para exibir o card.
if ($contatoSelecionado > 0 && !array_key_exists($contatoSelecionado, $contatos)) {
    $stmt2 = $mysqli->prepare("SELECT nome FROM usuarios WHERE id = ?");
    if ($stmt2) {
        $stmt2->bind_param('i', $contatoSelecionado);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2->num_rows === 1) {
            $row2 = $res2->fetch_assoc();
            $contatos[$contatoSelecionado] = $row2['nome'];
        }
        $stmt2->close();
    }
}

// 5) Ordena o array de contatos pelo nome
if (!empty($contatos)) {
    asort($contatos);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Chat Privado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="estilos_chat.css">


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

  <!-- 1) Cabeçalho com link de volta ao painel principal -->
  <div id="header-chat">
    <a href="painel.php" class="btn btn-outline-primary">
      <i class="bi bi-arrow-left"></i> Voltar ao Painel
    </a>
    <span>Chat Privado</span>
    <div></div>
  </div>

  <div class="container-chat">
    <!-- 2) Coluna da esquerda: área de conversa -->
    <div class="left-panel">
      <div id="chat-header">
        Selecione um contato à direita
      </div>
      <div id="chat-area">
        <p class="text-muted">Nenhuma conversa selecionada.</p>
      </div>
      <!-- Área de digitação/esconder inicialmente -->
      <div id="input-area" style="display: none;">
        <textarea id="msg-input" placeholder="Digite sua mensagem..." class="form-control"></textarea>
        <button id="send-btn" class="btn btn-success" disabled>
          <i class="bi bi-send"></i> Enviar
        </button>
      </div>
    </div>

    <!-- 3) Coluna da direita: lista de contatos -->
    <div class="right-panel">
      <?php if (empty($contatos)): ?>
        <p class="text-muted p-3">Ainda não há conversas.</p>
      <?php else: ?>
        <?php foreach ($contatos as $idContato => $nomeContato): ?>
          <div class="card-user" data-id="<?= $idContato; ?>">
            <i class="bi bi-person-fill"></i>
            <span><?= htmlspecialchars($nomeContato, ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- JQuery + Bootstrap JS (no fim da página) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function() {
  const meuId         = <?= json_encode($_SESSION['id']); ?>;
  let contatoAtivo    = 0;
  let carregando      = false;
  let nomeContato     = '';
  const contatoInicial = <?= json_encode($contatoSelecionado); ?>;

  // Guarda qual mensagem está com o menu aberto
  let openMenuMsgId = null;

  function marcaContatoSelecionado(id) {
    $('.card-user').removeClass('selected');
    $(`.card-user[data-id='${id}']`).addClass('selected');
  }

  function carregaMensagens() {
    if (contatoAtivo <= 0 || carregando) return;
    carregando = true;

    // salva o id da mensagem cujo menu está aberto antes de limpar
    const lastOpen = openMenuMsgId;

    $('#chat-area').html('<p class="text-muted">Carregando mensagens...</p>');

    $.ajax({
      url: 'pegar_mensagem.php',
      method: 'GET',
      data: { with: contatoAtivo },
      dataType: 'json'
    })
    .done(function(data) {
      $('#chat-area').empty();
      openMenuMsgId = null;  // vamos reabrir só se lastOpen existir

      if (!Array.isArray(data) || data.length === 0) {
        $('#chat-area').html('<p class="text-muted">Ainda não há mensagens nesta conversa.</p>');
        carregando = false;
        return;
      }

      data.forEach(function(msg) {
        const box = $('<div>')
          .addClass('message-box')
          .attr('data-id', msg.id)
          .attr('data-sender', msg.remetente_id);

        const menuIcon = $('<i>')
          .addClass('bi bi-three-dots-vertical menu-icon')
          .attr('title','Opções')
          .css({ position:'absolute', top:'4px', right:'8px', cursor:'pointer' });

        const texto = $('<div>').addClass('texto').text(msg.mensagem);
        const hora  = $('<div>').addClass('hora').text(msg.created_at);

        box.addClass(msg.remetente_id == meuId ? 'mine' : 'theirs');
        box.append(menuIcon, texto, hora);
        $('#chat-area').append(box);
      });

      // scroll
      $('#chat-area').scrollTop($('#chat-area')[0].scrollHeight);
      carregando = false;

      // reabre menu no mesmo message-box, se havia
      if (lastOpen) {
        const icon = $(`.message-box[data-id='${lastOpen}'] .menu-icon`);
        if (icon.length) icon.trigger('click');
      }
    })
    .fail(function() {
      $('#chat-area').html('<p class="text-danger">Falha ao carregar mensagens.</p>');
      carregando = false;
    });
  }

  // Ao clicar num contato
  $('.card-user').on('click', function() {
    contatoAtivo = parseInt($(this).data('id'));
    nomeContato  = $(this).find('span').text().trim();
    marcaContatoSelecionado(contatoAtivo);
    $('#chat-header').text('Conversa com ' + nomeContato);
    $('#input-area').show();
    $('#msg-input').val('');
    $('#send-btn').prop('disabled', true);
    carregaMensagens();
  });

  // Habilita botão enviar
  $('#msg-input').on('input', function() {
    $('#send-btn').prop('disabled', $(this).val().trim() === '');
  });

  // Enviar mensagem
  $('#send-btn').on('click', function() {
    const txt = $('#msg-input').val().trim();
    if (!txt || contatoAtivo <= 0) return;
    $('#send-btn').prop('disabled', true);
    $.post('enviar_mensagem.php', {
      destinatario_id: contatoAtivo,
      mensagem: txt
    }, 'json')
    .done(res => {
      if (res.status === 'success') {
        $('#msg-input').val('');
        carregaMensagens();
      } else {
        alert('Erro ao enviar: ' + (res.msg || 'Desconhecido'));
      }
    })
    .fail(() => {
      alert('Falha ao enviar mensagem.');
    })
    .always(() => {
      $('#send-btn').prop('disabled', false);
    });
  });

  // Recarrega a cada 2s
  setInterval(() => {
    if (contatoAtivo > 0) carregaMensagens();
  }, 2000);

  // Abre menu de opções
  $('#chat-area').on('click', '.menu-icon', function(e) {
    e.stopPropagation();
    $('.dropdown-menu-custom').remove();

    const box    = $(this).closest('.message-box');
    const msgId  = box.data('id');
    const sender = box.data('sender');
    const menu   = $('<div class="dropdown-menu-custom"></div>');

    // marca qual está aberto
    openMenuMsgId = msgId;

    menu.append('<a href="#" class="delete-for-me">Excluir mensagem</a>');
    if (sender === meuId) {
      menu.append('<a href="#" class="delete-for-all">Excluir para todos</a>');
    }
    box.append(menu);
  });

  // Fecha menu clicando fora
  $(document).on('click', function() {
    $('.dropdown-menu-custom').remove();
    openMenuMsgId = null;
  });

  // Excluir só para mim
  $('#chat-area').on('click', '.delete-for-me', function(e) {
    e.preventDefault();
    const box   = $(this).closest('.message-box');
    const msgId = box.data('id');

    $.post('deletar_mensagem.php', {
      mensagem_id: msgId,
      tipo: 'so_para_mim'
    }, res => {
      if (res.status === 'success') {
        box.remove();
      } else {
        alert('Erro: ' + res.msg);
      }
    }, 'json')
    .fail(() => {
      alert('Falha ao processar exclusão.');
    });
  });

  // Excluir para todos
  $('#chat-area').on('click', '.delete-for-all', function(e) {
    e.preventDefault();
    const msgId = $(this).closest('.message-box').data('id');

    $.post('deletar_mensagem.php', {
      mensagem_id: msgId,
      tipo: 'para_todos'
    }, res => {
      if (res.status === 'success') {
        carregaMensagens();
      } else {
        alert('Erro: ' + res.msg);
      }
    }, 'json')
    .fail(() => {
      alert('Falha ao processar exclusão.');
    });
  });

  // Se veio contato por GET, dispara clique
  if (contatoInicial > 0) {
    setTimeout(() => {
      const card = $(`.card-user[data-id='${contatoInicial}']`);
      if (card.length) card.trigger('click');
    }, 100);
  }

  // Carrega tema salvo
  const temaSalvo = localStorage.getItem('paletaEscolhida');
  if (temaSalvo) {
    document.documentElement.setAttribute('data-theme', temaSalvo);
    document.querySelectorAll('.tema-card').forEach(c => {
      if (c.dataset.tema === temaSalvo) c.classList.add('active');
    });
  }
});
</script>

</body>
</html>
