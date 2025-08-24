<?php
session_start();
include 'protect.php';
include 'conexao.php';

// Pega ID do item da query string
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$userId = $_SESSION['id'];
if (!$id) {
    die('ID inválido.');
}

// Processa envio do formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_item') {
    $nome       = trim($_POST['nome_item']    ?? '');
    $descricao  = trim($_POST['descricao']    ?? '');
    $local      = trim($_POST['localizacao']  ?? '');
    $categoria  = trim($_POST['categoria']    ?? '');
    $quantidade = (int) ($_POST['quantidade'] ?? 1);

    if ($nome === '' || $categoria === '') {
        $error = 'Nome e categoria são obrigatórios.';
    } elseif ($quantidade < 1) {
        $error = 'Quantidade deve ser no mínimo 1.';
    } else {
        // Atualiza campos textuais e quantidade
        $stmt = $mysqli->prepare(
            "UPDATE itens
             SET nome_item   = ?,
                 descricao   = ?,
                 localizacao = ?,
                 categoria   = ?,
                 quantidade  = ?
             WHERE id = ? AND usuario_id = ?"
        );
        if (! $stmt) {
            die('Erro no prepare(): ' . $mysqli->error);
        }
        // Tipos: s(nome), s(desc), s(local), s(cat), i(quant), i(id), i(userId)
        $stmt->bind_param('ssssiii',
            $nome,
            $descricao,
            $local,
            $categoria,
            $quantidade,
            $id,
            $userId
        );
        if (! $stmt->execute()) {
            $error = 'Falha ao atualizar: ' . $mysqli->error;
        } else {
            // Processa atualização de imagens: substitui apenas se usuário enviou nova
            for ($i = 0; $i < 3; $i++) {
                if (!empty($_FILES['images']['tmp_name'][$i]) && is_uploaded_file($_FILES['images']['tmp_name'][$i])) {
                    $blob = file_get_contents($_FILES['images']['tmp_name'][$i]);
                    $col = 'image' . ($i + 1);
                    $sql = "UPDATE itens SET {$col} = ? WHERE id = ? AND usuario_id = ?";
                    $stmt2 = $mysqli->prepare($sql);
                    if (! $stmt2) {
                        continue; // pula se der erro no prepare
                    }
                    // bind as blob (b), depois id e userId
                    $stmt2->bind_param('bii', $blob, $id, $userId);
                    $stmt2->send_long_data(0, $blob);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
            header('Location: produtos.php');
            exit;
        }
        $stmt->close();
    }
}

// Busca dados atuais do item (incluindo quantidade)
$stmt = $mysqli->prepare(
    "SELECT nome_item, descricao, localizacao, categoria, quantidade
     FROM itens
     WHERE id = ? AND usuario_id = ?"
);
$stmt->bind_param('ii', $id, $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    die('Item não encontrado ou sem permissão para editar.');
}
$item = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Editar Item</title>

      <link rel="stylesheet" href="estilos_editar_item.css">


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
    <div class="form-container">
        <h2>Editar Item</h2>
        <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_item">

            <label for="nome_item">Nome do item</label>
            <input type="text" id="nome_item" name="nome_item"
                   value="<?= htmlspecialchars($item['nome_item']) ?>" required>

            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao"><?= htmlspecialchars($item['descricao']) ?></textarea>

            <label for="localizacao">Localização</label>
            <input type="text" id="localizacao" name="localizacao"
                   value="<?= htmlspecialchars($item['localizacao']) ?>">

            <label for="categoria">Categoria</label>
            <select id="categoria" name="categoria" required>
                <?php
                $cats = ['veículo','eletrodomésticos','tecnologia','moda','esportes','brinquedos','imóveis','pet shop','ferramentas','construção'];
                foreach ($cats as $cat): ?>
                <option value="<?= $cat ?>"<?= $cat === $item['categoria'] ? ' selected' : '' ?>>
                    <?= ucfirst($cat) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <label for="quantidade">Quantidade</label>
            <input type="number" id="quantidade" name="quantidade"
                   min="1" value="<?= (int) $item['quantidade'] ?>" required>

            <label>Substituir Imagens (opcional)</label>
            <input type="file" name="images[]" accept="image/*">
            <input type="file" name="images[]" accept="image/*">
            <input type="file" name="images[]" accept="image/*">

            <div class="actions">
                <button type="submit">Salvar Alterações</button>
                <a href="produtos.php">Cancelar</a>
            </div>
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
