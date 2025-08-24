<?php
// index.php
include('conexao.php');
session_start();

// Apenas o login
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='login') {
  $email = trim($_POST['email'] ?? '');
  $senha = $_POST['senha'] ?? '';
  if ($email === '') {
    $erro_login = 'E-mail não informado.';
  } elseif ($senha === '') {
    $erro_login = 'Preencha sua senha.';
  } else {
        $consulta = $mysqli->prepare("SELECT id, nome, usuario, senha FROM usuarios WHERE usuario = ?");
        if ($consulta) {
            $consulta->bind_param('s', $email);
            $consulta->execute();
            $resultado_busca = $consulta->get_result();

            if ($resultado_busca->num_rows === 1) {
                $dados_usuario = $resultado_busca->fetch_assoc();
                if (password_verify($senha, $dados_usuario['senha'])) {
                    $_SESSION['id']   = $dados_usuario['id'];
                    $_SESSION['nome'] = $dados_usuario['nome'];
                    header("Location: painel.php");
                    exit;
                } else {
                    $erro_login = 'Senha incorreta.';
                }
            } else {
                $erro_login = 'E-mail não encontrado, crie uma conta!';
            }
            $consulta->close();
        } else {
            $erro_login = 'Erro interno ao preparar consulta.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Cadastro com Verificação</title>
  <!-- Sua folha de estilos principal -->
    <style>
    /* ─── RESET ─────────────────────────────────── */
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    html, body {
      width: 100%; height: 100%;
    }

    /* ─── VARIÁVEIS DE COR ───────────────────────── */
    :root {
      --p1-light: #F2CEAB;
      --p1-teal:  #13505B;
      --p1-coral: #FF6F61;
      --p1-purple:#72B5A4;
    }
    [data-theme="paleta1"] {
      --bg-body:   var(--p1-light);
      --bg-main:   var(--p1-coral);
      --bg-card:   var(--p1-light);
      --txt:       var(--p1-teal);
      --acc-1:     var(--p1-purple);
      --acc-2:     var(--p1-teal);
    }

    /* ─── BODY ──────────────────────────────────── */
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(to right, var(--bg-main), var(--bg-body));
      font-family: sans-serif;
      color: var(--txt);
    }

    /* ─── CONTAINER PRINCIPAL ───────────────────── */
    .container {
      position: relative;
      width: 768px; max-width: 90%;
      min-height: 480px;
      background: var(--bg-card);
      border-radius: 30px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
      overflow: hidden;
    }

    /* ─── FORM- CONTAINERS (login + signup) ────── */
    .form-container {
      position: absolute;
      top: 0; left: 0;
      width: 50%; height: 100%;
      padding: 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      background: var(--bg-card);
      transition: transform 0.6s ease, opacity 0.6s ease;
    }
    .sign-in { z-index: 2; }
    .sign-up { opacity: 0; z-index: 1; }

    .container.active .sign-in {
      transform: translateX(100%);
    }
    .container.active .sign-up {
      transform: translateX(100%);
      opacity: 1;
      z-index: 5;
    }

    .form-container h1 {
      margin-bottom: 10px;
    }
    .form-container input {
      margin: 8px 0;
      padding: 10px;
      border: none;
      border-radius: 8px;
    }
    .form-container button {
      margin-top: 10px;
      padding: 10px 45px;
      border: none;
      border-radius: 8px;
      background: var(--acc-2);
      color: #fff;
      cursor: pointer;
      text-transform: uppercase;
    }
    .form-container a {
      margin-top: 8px;
      color: var(--acc-1);
      text-decoration: none;
      font-size: 0.9em;
    }

    /* ─── TOGGLE PANEL ─────────────────────────── */
    .toggle-container {
      position: absolute;
      top: 0; left: 50%;
      width: 50%; height: 100%;
      overflow: hidden;
      transition: transform 0.6s ease;
      border-radius: 150px 0 0 150px;
      z-index: 1000;
    }
    .container.active .toggle-container {
      transform: translateX(-100%);
      border-radius: 0 150px 150px 0;
    }

    .toggle {
      position: relative;
      width: 200%; height: 100%;
      left: -100%;
      background: linear-gradient(to right, var(--acc-1), var(--acc-2));
      transition: transform 0.6s ease;
    }
    .container.active .toggle {
      transform: translateX(50%);
    }

    .toggle-panel {
      position: absolute;
      top: 0;
      width: 50%; height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      color: #fff;
      text-align: center;
      padding: 0 30px;
    }
    .toggle-left  { transform: translateX(-200%); }
    .toggle-right { right: 0; }
    .container.active .toggle-left  { transform: translateX(0); }
    .container.active .toggle-right { transform: translateX(200%); }
  
  /* ─── MODAL OVERLAY ─────────────────────────── */

.modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  padding: 1rem;
  overflow-y: auto;
}
.modal.active {
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Caixa interna do modal */
.modal .box {
  background: var(--bg-card);
  color: var(--txt);
  width: 100%;
  max-width: 400px;
  border-radius: 1rem;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  padding: 2rem;
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  font-family: sans-serif;
}

/* Botão fechar (x) */
.modal .close {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  font-size: 1.25rem;
  color: var(--txt);
  cursor: pointer;
}

/* Cabeçalhos dentro do modal */
.modal .box h3 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: bold;
  color: var(--acc-2);
}

/* Inputs do modal */
.modal .box input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--acc-1);
  border-radius: 0.5rem;
  font-size: 1rem;
  color: var(--txt);
}

/* Botões principais */
.modal .box .btn {
  align-self: flex-end;
  padding: 0.75rem 1.5rem;
  background: var(--acc-2);
  color: #fff;
  border: none;
  border-radius: 0.5rem;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.2s;
}

.modal .box .btn:hover {
  background: var(--acc-1);
}

/* Mensagens de erro / aviso */
.modal .box .msg-error {
  font-size: 0.9rem;
  color: #E63946;
  min-height: 1.2em;
}

</style>

  <!-- (Opcional) Google Fonts ou outros assets -->
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Segoe+UI&display=swap" rel="stylesheet">


</head>
<body data-theme="paleta1">
<div class="container" id="container">
  <!-- Tela de Login -->
  <div class="form-container sign-in">
    <h1>Login</h1>
    <?php if (!empty($erro_login)): ?>
      <p class="error"><?= htmlspecialchars($erro_login) ?></p>
    <?php endif; ?>
    <form method="POST" id="loginForm">
  <input type="hidden" name="action" value="login">
  <input id="loginEmail" type="email" name="email" placeholder="E‑mail" required>
  <input id="loginSenha" type="password" name="senha" placeholder="Senha" required>
  <button type="submit">Entrar</button>
</form>
<button id="btnCodigo" type="button" style="display:none">Enviar Código</button>
<a id="linkEsqueci" href="#">Esqueci minha senha</a>

</div>

  <!-- Modal Cadastro -->

<!-- Formulário de Cadastro -->
<div class="form-container sign-up">
  <span class="close" id="closeModal">&times;</span>
  <h1>Cadastro</h1>
  <form id="cadastroForm">
    <input id="cadNome"     type="text"     name="nome"     placeholder="Nome completo" required>
    <input id="cadEmail"    type="email"    name="email"    placeholder="E‑mail"          required>
    <input id="cadSenha"    type="password" name="senha"    placeholder="Senha"           required>
    <input id="cadSenha2"   type="password" name="senha2"   placeholder="Repita a senha"  required>
    <button id="btnCadEnviar" type="submit">Enviar Código</button>
  </form>
</div>

    <div class="toggle-container">
      <div class="toggle">
        <div class="toggle-panel toggle-left">
          <h1>Bem‑vindo de volta!</h1>
          <p>Já tem uma conta? Faça login.</p>
          <button id="signIn">Entrar</button>
        </div>
        <div class="toggle-panel toggle-right">
          <h1>Olá, amigo!</h1>
          <p>Ainda não tem conta? Cadastre‑se.</p>
          <button id="signUp">Inscrever‑se</button>
        </div>
      </div>
    </div>



<!-- Modal 1: Digitar e‑mail -->
<div class="modal" id="modalEmail">
  <div class="box">
    <span class="close" data-target="modalEmail">&times;</span>
    <h3>Esqueci Minha Senha</h3>
    <input type="email" id="inputEmail" placeholder="Digite seu e‑mail cadastrado">
    <button class="btn" id="btnEnviarCodigo">Enviar Código</button>
    <div id="msgEmail" class="msg-error"></div>
  </div>
</div>

<!-- Modal 2: Digitar código -->
<div class="modal" id="modalCodigo">
  <div class="box">
    <span class="close" data-target="modalCodigo">&times;</span>
    <h3>Verificar Código</h3>
    <input type="text" id="inputCodigo" placeholder="Digite o código recebido">
    <button class="btn" id="btnVerificarCodigo">Verificar Código</button>
    <div id="msgCodigo" class="msg-error"></div>
  </div>
</div>

<!-- Modal 3: Definir nova senha -->
<div class="modal" id="modalNovaSenha">
  <div class="box">
    <span class="close" data-target="modalNovaSenha">&times;</span>
    <h3>Definir Nova Senha</h3>
    <input type="password" id="inputSenha1" placeholder="Nova senha">
    <input type="password" id="inputSenha2" placeholder="Confirmar senha">
    <button class="btn" id="btnRedefinir">Redefinir Senha</button>
    <div id="msgNovaSenha" class="msg-error"></div>
  </div>
</div>

<script>
// Gerencia Login, Esqueci Senha e Cadastro com envio/verificação de código
document.addEventListener('DOMContentLoaded', () => {
  // --- LOGIN / ESQUECI SENHA ---
  const formL     = document.getElementById('loginForm');
  const inEmail   = document.getElementById('loginEmail');
  const inSenha   = document.getElementById('loginSenha');
  const btnLogin  = document.getElementById('btnLogin');
  const btnCod    = document.getElementById('btnCodigo');
  const linkEsqueci    = document.getElementById('linkEsqueci');
  const modalEmail     = document.getElementById('modalEmail');
  const modalCodigo    = document.getElementById('modalCodigo');
  const modalNovaSenha = document.getElementById('modalNovaSenha');

  let emailGlobal = '';

  // Abre Modal 1
  linkEsqueci.addEventListener('click', e => {
    e.preventDefault();
    modalEmail.classList.add('active');
  });

  // Fecha qualquer modal
  document.querySelectorAll('.modal .close').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById(btn.dataset.target).classList.remove('active');
    });
  });

  // Etapa 1: Enviar código
  document.getElementById('btnEnviarCodigo').addEventListener('click', async () => {
    const email = document.getElementById('inputEmail').value.trim();
    const msg   = document.getElementById('msgEmail');
    msg.textContent = '';
    if (!email) {
      msg.textContent = 'Informe um e‑mail válido.';
      return;
    }

    const data = new FormData();
    data.append('email', email);

    const resp = await fetch('verificar_email_existente.php', {
      method: 'POST', body: data
    });
    const text = (await resp.text()).toLowerCase();

    if (text.includes('enviado')) {
      emailGlobal = email;
      modalEmail.classList.remove('active');
      modalCodigo.classList.add('active');
    } else {
      msg.textContent = text;
    }
  });

  // Etapa 2: Verificar código
  document.getElementById('btnVerificarCodigo').addEventListener('click', async () => {
    const codigo = document.getElementById('inputCodigo').value.trim();
    const msg    = document.getElementById('msgCodigo');
    msg.textContent = '';
    if (!codigo) {
      msg.textContent = 'Digite o código recebido.';
      return;
    }

    const data = new FormData();
    data.append('email', emailGlobal);
    data.append('codigo', codigo);

    const resp = await fetch('confirmarcodigo.php', {
      method: 'POST', body: data
    });
    const text = (await resp.text()).trim();

    if (text === 'codigo_ok') {
      modalCodigo.classList.remove('active');
      modalNovaSenha.classList.add('active');
    } else {
      msg.textContent = 'Código incorreto.';
    }
  });

  // Etapa 3: Redefinir senha
  document.getElementById('btnRedefinir').addEventListener('click', async () => {
  const s1  = document.getElementById('inputSenha1').value.trim();
  const s2  = document.getElementById('inputSenha2').value.trim();
  const msg = document.getElementById('msgNovaSenha');
  msg.textContent = '';

  if (!s1 || !s2) {
    msg.textContent = 'Preencha todos os campos.';
    return;
  }
  if (s1 !== s2) {
    msg.textContent = 'As senhas não coincidem.';
    return;
  }

  // monta o FormData com os dois campos
  const data = new FormData();
  data.append('email', emailGlobal);
  data.append('senha', s1);
  data.append('confirmar_senha', s2);

  // envia para o seu redefinirsenha.php
  const resp = await fetch('redefinirsenha.php', {
    method: 'POST',
    body: data
  });

  const texto = await resp.text();
  alert(texto);
  if (texto.toLowerCase().includes('sucesso')) {
    modalNovaSenha.classList.remove('active');
  }
});


  // --- CADASTRO (fluxo de envio/verificação) ---
  const formC    = document.getElementById('cadastroForm');
  const btnCad   = document.getElementById('btnCadEnviar');
  let etapaC = 'cadastro'; // cadastro → verificarCadastro

  btnCad.addEventListener('click', async e => {
    e.preventDefault();
    if (etapaC === 'cadastro') {
      const resp = await fetch('enviar_codigo.php', {
        method: 'POST',
        body: new FormData(formC)
      });
      const txt  = (await resp.text()).toLowerCase();
      if (txt.includes('enviado')) {
        etapaC = 'verificarCadastro';
        ['cadNome','cadEmail','cadSenha','cadSenha2'].forEach(id => {
          document.getElementById(id).style.display = 'none';
        });
        btnCad.textContent = 'Verificar Código';
        const campo = document.createElement('input');
        campo.type        = 'text';
        campo.id          = 'cadCodigo';
        campo.placeholder = 'Digite o código recebido';
        formC.insertBefore(campo, btnCad);
      } else {
        alert(txt);
      }
    }
    else if (etapaC === 'verificarCadastro') {
      const data = new FormData();
      data.append('email', formC.email.value);
      data.append('codigo', document.getElementById('cadCodigo').value);
      const resp = await fetch('verificar_codigo.php', {
        method: 'POST',
        body: data
      });
      const txt  = (await resp.text()).toLowerCase();
      if (txt.includes('sucesso')) {
        const d2 = new FormData();
        d2.append('nome',  formC.nome.value);
        d2.append('email', formC.email.value);
        d2.append('senha', formC.senha.value);
        d2.append('senha2', formC.senha2.value);
        const r2 = await fetch('cadastrar_usuario.php', {
          method: 'POST',
          body: d2
        });
        alert(await r2.text());
        window.location.reload();
      } else {
        alert('Código inválido');
      }
    }
  });
});
</script>

  <script>
    const container = document.getElementById('container');
    document.getElementById('signUp').addEventListener('click', () => {
      container.classList.add('active');
    });
    document.getElementById('signIn').addEventListener('click', () => {
      container.classList.remove('active');
    });
  </script>
</body>
</html>