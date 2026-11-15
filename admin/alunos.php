<?php
/**
 * Alunos - Cadastro e gerenciamento de alunos (Admin)
 *
 * Página focada em aluno: o administrador cadastra nome, e-mail (e,
 * opcionalmente, login e senha) e o aluno já fica gravado na tabela
 * `usuarios` com tipo 'aluno', podendo entrar no site na hora.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requerAdmin();

/**
 * Senha legível para o professor entregar ao aluno (sem letras/números que
 * se confundem, como l/1 e o/0).
 * @return string
 */
function gerarSenhaAluno(int $tamanho = 8): string
{
    $alfabeto = 'abcdefghijkmnpqrstuvwxyz23456789';
    $senha = '';

    for ($i = 0; $i < $tamanho; $i++) {
        $senha .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }

    return $senha;
}

$erro = '';
$busca = sanitizar((string)($_GET['busca'] ?? ''));
$acao = $_GET['acao'] ?? 'listar';
$alunoId = (int)($_GET['id'] ?? 0);

// Cadastro de aluno
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido. Tente novamente.';
    } else {
        $nome = sanitizar($_POST['nome'] ?? '');
        $email = strtolower(sanitizar($_POST['email'] ?? ''));
        $usuario = strtolower(sanitizar($_POST['usuario'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');
        $senhaGerada = false;

        if ($nome === '' || $email === '') {
            $erro = 'Nome e e-mail são obrigatórios.';
        } elseif (mb_strlen($nome) < 3) {
            $erro = 'O nome precisa ter pelo menos 3 letras.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } elseif ($usuario !== '' && !preg_match('/^[a-z0-9._-]{3,60}$/', $usuario)) {
            $erro = 'O login deve ter de 3 a 60 caracteres (letras, números, ponto, hífen ou _).';
        } else {
            if ($senha === '') {
                $senha = gerarSenhaAluno();
                $senhaGerada = true;
            }

            if (strlen($senha) < 6) {
                $erro = 'A senha precisa ter pelo menos 6 caracteres.';
            } elseif (usuarioJaCadastrado($email, $usuario)) {
                $erro = 'Já existe um usuário com este e-mail ou este login.';
            } else {
                $novoId = criarUsuario($nome, $email, $senha, 'aluno', $usuario !== '' ? $usuario : null);

                if ($novoId) {
                    $login = $usuario !== '' ? $usuario : $email;
                    $avisoSenha = $senhaGerada ? ' (senha gerada pelo sistema)' : '';
                    flash('success', "Aluno \"{$nome}\" cadastrado com o ID {$novoId}. "
                        . "Login: {$login} — Senha: {$senha}{$avisoSenha}. "
                        . 'Anote e entregue estes dados ao aluno: a senha não é exibida de novo.');
                    header('Location: /avaliacao web/admin/alunos.php');
                    exit;
                }

                $erro = 'Não foi possível cadastrar o aluno. Tente novamente.';
            }
        }
    }
}

// Ativar / desativar acesso do aluno
if ($acao === 'status' && $alunoId > 0) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT nome, ativo FROM usuarios WHERE id = ? AND tipo = 'aluno'");
        $stmt->execute([$alunoId]);
        $aluno = $stmt->fetch();

        if ($aluno) {
            $novoStatus = $aluno['ativo'] ? 0 : 1;
            $pdo->prepare("UPDATE usuarios SET ativo = ? WHERE id = ?")->execute([$novoStatus, $alunoId]);
            flash('success', $novoStatus
                ? "Acesso de \"{$aluno['nome']}\" liberado."
                : "Acesso de \"{$aluno['nome']}\" bloqueado (o aluno não consegue mais entrar).");
        } else {
            flash('error', 'Aluno não encontrado.');
        }
    } catch (Exception $e) {
        error_log('Erro ao alterar status do aluno: ' . $e->getMessage());
        flash('error', 'Erro ao alterar o status do aluno.');
    }

    header('Location: /avaliacao web/admin/alunos.php');
    exit;
}

// Excluir aluno (as provas dele saem junto)
if ($acao === 'excluir' && $alunoId > 0 && ($_GET['confirm'] ?? '') === '1') {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? AND tipo = 'aluno'");
        $stmt->execute([$alunoId]);
        $aluno = $stmt->fetch();

        if ($aluno) {
            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$alunoId]);
            flash('success', "Aluno \"{$aluno['nome']}\" excluído.");
        } else {
            flash('error', 'Aluno não encontrado.');
        }
    } catch (Exception $e) {
        error_log('Erro ao excluir aluno: ' . $e->getMessage());
        flash('error', 'Erro ao excluir o aluno.');
    }

    header('Location: /avaliacao web/admin/alunos.php');
    exit;
}

$alunos = listarAlunos($busca);
$flashes = obterFlashes();
$totalAtivos = 0;
foreach ($alunos as $a) {
    if ($a['ativo']) {
        $totalAtivos++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alunos - Admin</title>
    <link rel="stylesheet" href="/avaliacao web/assets/css/style.css?v=<?= versaoEstatico('assets/css/style.css') ?>">
    <link rel="stylesheet" href="/avaliacao web/assets/css/admin.css?v=<?= versaoEstatico('assets/css/admin.css') ?>">
</head>
<body class="admin-page">
    <header class="admin-header">
        <div class="container header-content">
            <h1><?= e(APP_NAME) ?></h1>
            <span class="badge admin-badge">Administrador</span>
            <div class="user-info">
                <span class="user-name"><?= e($_SESSION['usuario_nome']) ?></span>
                <a href="/avaliacao web/logout.php" class="btn btn-secondary btn-sm">Sair</a>
            </div>
        </div>
    </header>

    <div class="admin-container container">
        <aside class="admin-sidebar">
            <nav class="admin-nav">
                <h3>Menu</h3>
                <ul>
                    <li><a href="/avaliacao web/admin/dashboard.php">📊 Dashboard</a></li>
                    <li><a href="/avaliacao web/admin/questoes.php">📝 Questões</a></li>
                    <li><a href="/avaliacao web/admin/alunos.php" class="active">🎓 Alunos</a></li>
                    <li><a href="/avaliacao web/admin/usuarios.php">👥 Usuários</a></li>
                    <li><a href="/avaliacao web/admin/avaliacoes.php">📋 Avaliações</a></li>
                    <li><a href="/avaliacao web/admin/eventos.php">🚨 Eventos Suspeitos</a></li>
                </ul>
                <hr style="margin: 1rem 0; border-color: var(--border-color);">
                <li><a href="/avaliacao web/dashboard.php" class="btn btn-outline">👁️ Ver como Aluno</a></li>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header-title">
                <h2>Alunos</h2>
                <p>Cadastre os alunos que vão fazer a prova. Cada aluno cadastrado aqui já pode entrar no site.</p>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= $flash['tipo'] === 'error' ? 'error' : ($flash['tipo'] === 'warning' ? 'warning' : 'success') ?>">
                    <?= e($flash['mensagem']) ?>
                </div>
            <?php endforeach; ?>

            <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

            <section class="admin-section">
                <div class="section-header">
                    <h3>Cadastrar aluno</h3>
                </div>

                <form method="POST" action="/avaliacao web/admin/alunos.php" class="admin-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(gerarTokenCSRF()) ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome completo *</label>
                            <input type="text" id="nome" name="nome" required value="<?= e($_POST['nome'] ?? '') ?>" placeholder="ex: Maria Santos">
                        </div>
                        <div class="form-group">
                            <label for="email">E-mail *</label>
                            <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="ex: maria@aluno.com">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="usuario">Login (opcional)</label>
                            <input type="text" id="usuario" name="usuario" value="<?= e($_POST['usuario'] ?? '') ?>" placeholder="ex: maria — o aluno também pode entrar com o e-mail">
                        </div>
                        <div class="form-group">
                            <label for="senha">Senha</label>
                            <input type="text" id="senha" name="senha" minlength="6" autocomplete="off" placeholder="deixe em branco para o sistema gerar">
                            <small>Em branco: o sistema cria uma senha e mostra aqui depois de cadastrar.</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">➕ Cadastrar aluno</button>
                        <a href="/avaliacao web/admin/usuarios.php" class="btn btn-outline">Gerenciar todos os usuários</a>
                    </div>
                </form>
            </section>

            <section class="admin-section">
                <div class="section-header">
                    <h3>Alunos cadastrados (<?= count($alunos) ?><?= $busca !== '' ? ' encontrados' : '' ?>)</h3>
                    <form method="GET" action="/avaliacao web/admin/alunos.php" class="admin-search">
                        <input type="search" name="busca" value="<?= e($busca) ?>" placeholder="Buscar por nome, e-mail ou login">
                        <button type="submit" class="btn btn-secondary btn-sm">Buscar</button>
                        <?php if ($busca !== ''): ?>
                            <a href="/avaliacao web/admin/alunos.php" class="btn btn-outline btn-sm">Limpar</a>
                        <?php endif; ?>
                    </form>
                </div>

                <p class="admin-hint">
                    <?= $totalAtivos ?> de <?= count($alunos) ?> alunos com acesso liberado.
                </p>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Aluno</th>
                                <th>Login</th>
                                <th>E-mail</th>
                                <th>Status</th>
                                <th>Cadastro</th>
                                <th>Provas</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunos as $aluno): ?>
                                <tr>
                                    <td><?= (int)$aluno['id'] ?></td>
                                    <td><?= e($aluno['nome']) ?></td>
                                    <td><?= e($aluno['usuario'] ?? '—') ?></td>
                                    <td><?= e($aluno['email']) ?></td>
                                    <td>
                                        <span class="badge <?= $aluno['ativo'] ? 'badge-success' : 'badge-secondary' ?>">
                                            <?= $aluno['ativo'] ? 'Ativo' : 'Bloqueado' ?>
                                        </span>
                                    </td>
                                    <td><?= formatarData($aluno['created_at'], 'd/m/Y H:i') ?></td>
                                    <td>
                                        <?= (int)$aluno['provas_entregues'] ?> entregues
                                        <?php if ((int)$aluno['total_provas'] > 0): ?>
                                            <small>(<?= (int)$aluno['total_provas'] ?> no total)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/avaliacao web/admin/usuarios.php?acao=editar&id=<?= (int)$aluno['id'] ?>"
                                           class="btn btn-sm btn-secondary" title="Editar">✏️</a>
                                        <a href="/avaliacao web/admin/alunos.php?acao=status&id=<?= (int)$aluno['id'] ?>"
                                           class="btn btn-sm btn-outline"
                                           title="<?= $aluno['ativo'] ? 'Bloquear acesso' : 'Liberar acesso' ?>">
                                            <?= $aluno['ativo'] ? '🚫' : '✅' ?>
                                        </a>
                                        <a href="/avaliacao web/admin/alunos.php?acao=excluir&id=<?= (int)$aluno['id'] ?>&confirm=1"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Excluir o aluno <?= e(addslashes($aluno['nome'])) ?>? As provas dele também serão apagadas.')"
                                           title="Excluir">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($alunos)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <?= $busca !== '' ? 'Nenhum aluno encontrado para esta busca.' : 'Nenhum aluno cadastrado ainda. Use o formulário acima.' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <footer class="main-footer">
        <div class="container">
            <p>&copy; 2024 - Sistema de Avaliação Acadêmica - Área Administrativa</p>
        </div>
    </footer>
</body>
</html>
