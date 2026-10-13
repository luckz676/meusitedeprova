<?php
/**
 * Gerenciamento de Usuários - Admin
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requerAdmin();

$acao = $_GET['acao'] ?? 'listar';
$usuarioId = (int)($_GET['id'] ?? 0);
$mensagem = '';
$erro = '';

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido.';
    } else {
        $nome = sanitizar($_POST['nome'] ?? '');
        $usuario = sanitizar($_POST['usuario'] ?? '');
        $email = sanitizar($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $tipo = $_POST['tipo'] ?? 'aluno';
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if (empty($nome) || empty($email)) {
            $erro = 'Nome e e-mail são obrigatórios.';
        } elseif ($usuario !== '' && !preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $usuario)) {
            $erro = 'Usuário deve ter de 3 a 60 caracteres (letras, números, ponto, hífen ou _).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } elseif ($acao === 'novo' && empty($senha)) {
            $erro = 'Senha é obrigatória para novo usuário.';
        } elseif ($acao === 'novo' && strlen($senha) < 6) {
            $erro = 'Senha deve ter pelo menos 6 caracteres.';
        } else {
            try {
                $pdo = getDbConnection();
                if ($acao === 'novo') {
                    $senhaHash = password_hash($senha, HASH_ALGO, ['cost' => HASH_COST]);
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, usuario, email, senha_hash, tipo, ativo) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nome, $usuario ?: null, $email, $senhaHash, $tipo, $ativo]);
                    $mensagem = 'Usuário cadastrado com sucesso!';
                    $acao = 'listar';
                } elseif ($acao === 'editar' && $usuarioId > 0) {
                    if (!empty($senha)) {
                        if (strlen($senha) < 6) {
                            $erro = 'Senha deve ter pelo menos 6 caracteres.';
                        } else {
                            $senhaHash = password_hash($senha, HASH_ALGO, ['cost' => HASH_COST]);
                            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, usuario = ?, email = ?, senha_hash = ?, tipo = ?, ativo = ? WHERE id = ?");
                            $stmt->execute([$nome, $usuario ?: null, $email, $senhaHash, $tipo, $ativo, $usuarioId]);
                        }
                    } else {
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, usuario = ?, email = ?, tipo = ?, ativo = ? WHERE id = ?");
                        $stmt->execute([$nome, $usuario ?: null, $email, $tipo, $ativo, $usuarioId]);
                    }
                    if (!$erro) {
                        $mensagem = 'Usuário atualizado com sucesso!';
                        $acao = 'listar';
                    }
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $erro = 'Usuário ou e-mail já cadastrado.';
                } else {
                    $erro = 'Erro ao salvar usuário.';
                }
            }
        }
    }
}

// Exclusão
if ($acao === 'excluir' && $usuarioId > 0 && ($_GET['confirm'] ?? '') === '1') {
    if ($usuarioId === $_SESSION['usuario_id']) {
        $erro = 'Não é possível excluir seu próprio usuário.';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$usuarioId]);
            $mensagem = 'Usuário excluído com sucesso!';
        } catch (Exception $e) {
            $erro = 'Erro ao excluir usuário.';
        }
    }
    $acao = 'listar';
}

// Busca usuário para edição
$usuarioEditar = null;
if (($acao === 'editar' || $acao === 'ver') && $usuarioId > 0) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        $usuarioEditar = $stmt->fetch();
        if (!$usuarioEditar) {
            $erro = 'Usuário não encontrado.';
            $acao = 'listar';
        }
    } catch (Exception $e) {
        $erro = 'Erro ao buscar usuário.';
        $acao = 'listar';
    }
}

// Lista usuários
$usuarios = [];
if ($acao === 'listar') {
    $usuarios = listarUsuarios();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Admin</title>
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
                    <li><a href="/avaliacao web/admin/usuarios.php" class="active">👥 Usuários</a></li>
                    <li><a href="/avaliacao web/admin/avaliacoes.php">📋 Avaliações</a></li>
                    <li><a href="/avaliacao web/admin/eventos.php">🚨 Eventos Suspeitos</a></li>
                </ul>
                <hr style="margin: 1rem 0; border-color: var(--border-color);">
                <li><a href="/avaliacao web/dashboard.php" class="btn btn-outline">👁️ Ver como Aluno</a></li>
            </nav>
        </aside>

        <main class="admin-main">
            <?php if ($acao === 'listar'): ?>
                <div class="admin-header-title">
                    <h2>Gerenciar Usuários</h2>
                    <p>Cadastre e gerencie alunos e administradores</p>
                </div>

                <?php if ($mensagem): ?><div class="alert alert-success"><?= e($mensagem) ?></div><?php endif; ?>
                <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

                <div class="admin-section">
                    <div class="section-header">
                        <h3>Usuários Cadastrados (<?= count($usuarios) ?>)</h3>
                        <a href="/avaliacao web/admin/usuarios.php?acao=novo" class="btn btn-primary">➕ Novo Usuário</a>
                    </div>

                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Usuário</th>
                                    <th>E-mail</th>
                                    <th>Tipo</th>
                                    <th>Status</th>
                                    <th>Cadastro</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <tr>
                                        <td><?= $u['id'] ?></td>
                                        <td><?= e($u['nome']) ?></td>
                                        <td><?= e($u['usuario'] ?? '—') ?></td>
                                        <td><?= e($u['email']) ?></td>
                                        <td>
                                            <span class="badge <?= $u['tipo'] === 'admin' ? 'badge-warning' : 'badge-info' ?>">
                                                <?= ucfirst($u['tipo']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $u['ativo'] ? 'badge-success' : 'badge-secondary' ?>">
                                                <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                        </td>
                                        <td><?= formatarData($u['created_at'], 'd/m/Y H:i') ?></td>
                                        <td>
                                            <a href="/avaliacao web/admin/usuarios.php?acao=ver&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline" title="Ver">👁️</a>
                                            <a href="/avaliacao web/admin/usuarios.php?acao=editar&id=<?= $u['id'] ?>" class="btn btn-sm btn-secondary" title="Editar">✏️</a>
                                            <?php if ($u['id'] !== $_SESSION['usuario_id']): ?>
                                                <a href="/avaliacao web/admin/usuarios.php?acao=excluir&id=<?= $u['id'] ?>&confirm=1"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Tem certeza que deseja excluir este usuário?')"
                                                   title="Excluir">🗑️</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($usuarios)): ?>
                                    <tr><td colspan="8" class="text-center">Nenhum usuário cadastrado</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($acao === 'novo' || $acao === 'editar'): ?>
                <div class="admin-header-title">
                    <h2><?= $acao === 'novo' ? 'Novo Usuário' : 'Editar Usuário' ?></h2>
                </div>

                <?php if ($mensagem): ?><div class="alert alert-success"><?= e($mensagem) ?></div><?php endif; ?>
                <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

                <form method="POST" class="admin-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(gerarTokenCSRF()) ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome *</label>
                            <input type="text" id="nome" name="nome" value="<?= e($usuarioEditar['nome'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">E-mail *</label>
                            <input type="email" id="email" name="email" value="<?= e($usuarioEditar['email'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="usuario">Usuário (login) — opcional</label>
                            <input type="text" id="usuario" name="usuario" value="<?= e($usuarioEditar['usuario'] ?? '') ?>" placeholder="ex: joao (pode logar com ele ou com o e-mail)">
                        </div>
                        <div class="form-group"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo">Tipo *</label>
                            <select id="tipo" name="tipo" required>
                                <option value="aluno" <?= ($usuarioEditar['tipo'] ?? '') === 'aluno' ? 'selected' : '' ?>>Aluno</option>
                                <option value="admin" <?= ($usuarioEditar['tipo'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="ativo" value="1" <?= (!isset($usuarioEditar['ativo']) || $usuarioEditar['ativo']) ? 'checked' : '' ?>> Ativo
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="senha">Senha <?= $acao === 'novo' ? '*' : '(deixe em branco para não alterar)' ?></label>
                        <input type="password" id="senha" name="senha" <?= $acao === 'novo' ? 'required' : '' ?> minlength="6" autocomplete="new-password">
                        <?php if ($acao === 'editar'): ?>
                            <small>Deixe em branco para manter a senha atual</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <a href="/avaliacao web/admin/usuarios.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><?= $acao === 'novo' ? 'Cadastrar' : 'Atualizar' ?></button>
                    </div>
                </form>

            <?php elseif ($acao === 'ver' && $usuarioEditar): ?>
                <div class="admin-header-title">
                    <h2>Visualizar Usuário #<?= $usuarioEditar['id'] ?></h2>
                </div>

                <div class="admin-section">
                    <div style="padding: 1.5rem;">
                        <dl style="display: grid; grid-template-columns: 150px 1fr; gap: 0.5rem 1.5rem;">
                            <dt>ID</dt><dd><?= $usuarioEditar['id'] ?></dd>
                            <dt>Nome</dt><dd><?= e($usuarioEditar['nome']) ?></dd>
                            <dt>Usuário</dt><dd><?= e($usuarioEditar['usuario'] ?? '—') ?></dd>
                            <dt>E-mail</dt><dd><?= e($usuarioEditar['email']) ?></dd>
                            <dt>Tipo</dt><dd><span class="badge <?= $usuarioEditar['tipo'] === 'admin' ? 'badge-warning' : 'badge-info' ?>"><?= ucfirst($usuarioEditar['tipo']) ?></span></dd>
                            <dt>Status</dt><dd><span class="badge <?= $usuarioEditar['ativo'] ? 'badge-success' : 'badge-secondary' ?>"><?= $usuarioEditar['ativo'] ? 'Ativo' : 'Inativo' ?></span></dd>
                            <dt>Cadastro</dt><dd><?= formatarData($usuarioEditar['created_at']) ?></dd>
                            <dt>Última Atualização</dt><dd><?= formatarData($usuarioEditar['updated_at']) ?></dd>
                        </dl>
                        <div style="margin-top: 1.5rem;">
                            <a href="/avaliacao web/admin/usuarios.php?acao=editar&id=<?= $usuarioEditar['id'] ?>" class="btn btn-secondary">Editar</a>
                            <a href="/avaliacao web/admin/usuarios.php" class="btn btn-outline">Voltar</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <footer class="main-footer">
        <div class="container">
            <p>&copy; 2024 - Sistema de Avaliação Acadêmica - Área Administrativa</p>
        </div>
    </footer>
</body>
</html>