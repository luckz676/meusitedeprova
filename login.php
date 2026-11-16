<?php
/**
 * Página de Login - Sistema de Avaliação Acadêmica
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icones.php';

if (estaLogado()) {
    if (ehAdmin()) {
        header('Location: /avaliacao web/admin/dashboard.php');
    } else {
        header('Location: /avaliacao web/dashboard.php');
    }
    exit;
}

$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido. Tente novamente.';
    } else {
        $email = sanitizar($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            $erro = 'Preencha todos os campos.';
        } else {
            $usuario = autenticarUsuario($email, $senha);
            if ($usuario) {
                fazerLogin($usuario);
                if ($usuario['tipo'] === 'admin') {
                    header('Location: /avaliacao web/admin/dashboard.php');
                } else {
                    header('Location: /avaliacao web/dashboard.php');
                }
                exit;
            } else {
                $erro = 'Usuário/e-mail ou senha incorretos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="/avaliacao web/assets/css/style.css?v=<?= versaoEstatico('assets/css/style.css') ?>">
</head>
<body class="login-page">
    <div class="login-container">
        <section class="login-brief">
            <p class="login-brief__marca"><?= e(APP_NAME) ?></p>

            <div>
                <h2>A prova acontece <em>aqui</em> — com câmera e tela cheia ligadas.</h2>
                <p style="margin-top: 1rem;">
                    Entre com os dados entregues pelo professor. O tempo começa assim que a
                    prova for iniciada e cada saída da tela fica registrada no relatório.
                </p>
            </div>

            <ul class="login-brief__regras">
                <li><?= icone('camera', 16) ?> Câmera ligada durante toda a prova</li>
                <li><?= icone('escudo', 16) ?> Tela cheia obrigatória, sem trocar de aba</li>
                <li><?= icone('documento', 16) ?> Cada resposta é salva na hora</li>
            </ul>
        </section>

        <div class="login-card">
            <div class="login-header">
                <h1>Entrar na prova</h1>
                <p class="subtitle">Use o e-mail ou o login que o professor cadastrou.</p>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-error"><?= e($erro) ?></div>
            <?php endif; ?>

            <form method="POST" class="login-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(gerarTokenCSRF()) ?>">

                <div class="form-group">
                    <label for="email">Usuário ou e-mail</label>
                    <input type="text" id="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus placeholder="ex: joao ou joao@aluno.com">
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Entrar</button>
            </form>

            <div class="login-footer">
                <p><strong>Contas de demonstração</strong></p>
                <p>aluno: joao@aluno.com / aluno123</p>
                <p>admin: admin@sistema.com / admin123</p>
            </div>

            <?php if (!MODO_LEVE): ?>
                <div class="gif-banner login-gif">
                    <img src="/avaliacao web/assets/gifs/spinning-oguri.gif" alt="Oguri girando" class="gif-banner__img login-gif__img">
                    <p class="gif-banner__texto">espero que esteja bom</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        window.AVALIACAO_CONFIG = {
            baseUrl: '/avaliacao web',
            modoLeve: <?= MODO_LEVE ? 'true' : 'false' ?>
        };
    </script>
    <script src="/avaliacao web/assets/js/paste-guard.js?v=<?= versaoEstatico('assets/js/paste-guard.js') ?>"></script>
</body>
</html>