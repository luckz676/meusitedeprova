<?php
/**
 * Página de Login - Sistema de Avaliação Acadêmica
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

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
        <div class="login-card">
            <div class="login-header">
                <h1><?= e(APP_NAME) ?></h1>
                <p class="subtitle">Área do Aluno</p>
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
                <p><strong>Credenciais de teste:</strong></p>
                <p>Aluno: joao@aluno.com ou "joao" / aluno123</p>
                <p>Admin: admin@sistema.com ou "admin" / admin123</p>
            </div>
        </div>

        <div class="gif-banner login-gif">
            <img src="/avaliacao web/assets/gifs/spinning-oguri.gif" alt="Oguri girando" class="gif-banner__img login-gif__img">
            <p class="gif-banner__texto">espero que esteja bom</p>
        </div>
    </div>

    <script src="/avaliacao web/assets/js/paste-guard.js?v=<?= versaoEstatico('assets/js/paste-guard.js') ?>"></script>
</body>
</html>