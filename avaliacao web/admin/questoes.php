<?php
/**
 * Gerenciamento de Questões - Admin
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requerAdmin();

$acao = $_GET['acao'] ?? 'listar';
$questaoId = (int)($_GET['id'] ?? 0);
$mensagem = '';
$erro = '';

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido.';
    } else {
        $enunciado = sanitizar($_POST['enunciado'] ?? '');
        $tipo = $_POST['tipo'] ?? 'multipla_escolha';
        $categoria = sanitizar($_POST['categoria'] ?? '');
        $explicacao = sanitizar($_POST['explicacao'] ?? '');
        $alternativas = $_POST['alternativas'] ?? [];

        $tiposValidos = ['multipla_escolha', 'verdadeiro_falso', 'dissertativa'];
        $ehDissertativa = ($tipo === 'dissertativa');

        // Validações
        if (!in_array($tipo, $tiposValidos, true)) {
            $erro = 'Tipo de questão inválido.';
        } elseif (empty($enunciado) || empty($categoria)) {
            $erro = 'Preencha todos os campos obrigatórios.';
        } elseif (!$ehDissertativa && count($alternativas) < 2) {
            $erro = 'Adicione pelo menos 2 alternativas.';
        } elseif (!$ehDissertativa) {
            $temCorreta = false;
            foreach ($alternativas as $alt) {
                if (!empty($alt['texto']) && !empty($alt['correta'])) {
                    $temCorreta = true;
                    break;
                }
            }
            if (!$temCorreta) {
                $erro = 'Marque pelo menos uma alternativa como correta.';
            }
        }

        if (!$erro) {
            // Questões dissertativas não usam alternativas
            $alts = [];
            if (!$ehDissertativa) {
                foreach ($alternativas as $i => $alt) {
                    if (!empty($alt['texto'])) {
                        $alts[] = [
                            'texto' => $alt['texto'],
                            'correta' => !empty($alt['correta']) ? 1 : 0,
                            'ordem' => $i + 1
                        ];
                    }
                }
            }

            if ($acao === 'editar' && $questaoId > 0) {
                if (atualizarQuestao($questaoId, $enunciado, $tipo, $categoria, $explicacao, $alts)) {
                    $mensagem = 'Questão atualizada com sucesso!';
                    $acao = 'listar';
                } else {
                    $erro = 'Erro ao atualizar questão.';
                }
            } elseif ($acao === 'novo') {
                $id = cadastrarQuestao($enunciado, $tipo, $categoria, $explicacao, $alts);
                if ($id) {
                    $mensagem = 'Questão cadastrada com sucesso!';
                    $acao = 'listar';
                } else {
                    $erro = 'Erro ao cadastrar questão.';
                }
            }
        }
    }
}

// Exclusão
if ($acao === 'excluir' && $questaoId > 0 && ($_GET['confirm'] ?? '') === '1') {
    if (excluirQuestao($questaoId)) {
        $mensagem = 'Questão excluída com sucesso!';
    } else {
        $erro = 'Erro ao excluir questão.';
    }
    $acao = 'listar';
}

// Busca dados para edição
$questaoEditar = null;
if (($acao === 'editar' || $acao === 'ver') && $questaoId > 0) {
    $questaoEditar = obterQuestaoComAlternativas($questaoId);
    if (!$questaoEditar) {
        $erro = 'Questão não encontrada.';
        $acao = 'listar';
    }
}

// Lista questões
$questoes = [];
if ($acao === 'listar') {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query(
            "SELECT q.*, (SELECT COUNT(*) FROM alternativas WHERE questao_id = q.id) as total_alts,
                    (SELECT COUNT(*) FROM alternativas WHERE questao_id = q.id AND correta = 1) as corretas
             FROM questoes q ORDER BY q.id DESC"
        );
        $questoes = $stmt->fetchAll();
    } catch (Exception $e) {
        $erro = 'Erro ao listar questões.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questões - Admin</title>
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
                    <li><a href="/avaliacao web/admin/questoes.php" class="active">📝 Questões</a></li>
                    <li><a href="/avaliacao web/admin/usuarios.php">👥 Usuários</a></li>
                    <li><a href="/avaliacao web/admin/avaliacoes.php">📋 Avaliações</a></li>
                    <li><a href="/avaliacao web/admin/eventos.php">🚨 Eventos Suspeitos</a></li>
                </ul>
                <hr style="margin: 1rem 0; border-color: var(--border-color);">
                <li><a href="/avaliacao web/dashboard.php" class="btn btn-outline">👁️ Ver como Aluno</a></li>
            </nav>
        </aside>

        <main class="admin-main">
            <?php if ($acao === 'listar'): ?>
                <!-- LISTAGEM -->
                <div class="admin-header-title">
                    <h2>Gerenciar Questões</h2>
                    <p>Cadastre, edite e exclua questões da avaliação</p>
                </div>

                <?php if ($mensagem): ?><div class="alert alert-success"><?= e($mensagem) ?></div><?php endif; ?>
                <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

                <div class="admin-section">
                    <div class="section-header">
                        <h3>Questões Cadastradas (<?= count($questoes) ?>)</h3>
                        <a href="/avaliacao web/admin/questoes.php?acao=novo" class="btn btn-primary">➕ Nova Questão</a>
                    </div>

                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Enunciado</th>
                                    <th>Tipo</th>
                                    <th>Categoria</th>
                                    <th>Alternativas</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($questoes as $q): ?>
                                    <tr>
                                        <td><?= $q['id'] ?></td>
                                        <td style="max-width: 300px;">
                                            <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                <?= e(mb_substr($q['enunciado'], 0, 120)) ?><?= mb_strlen($q['enunciado']) > 120 ? '...' : '' ?>
                                            </div>
                                        </td>
                                        <?php [$rotuloTipo, $classeTipo] = rotuloTipoQuestao($q['tipo']); ?>
                                        <td>
                                            <span class="badge <?= $classeTipo ?>"><?= e($rotuloTipo) ?></span>
                                        </td>
                                        <td><?= e($q['categoria']) ?></td>
                                        <td>
                                            <?php if ($q['tipo'] === 'dissertativa'): ?>
                                                —
                                            <?php else: ?>
                                                <?= $q['total_alts'] ?> (<?= $q['corretas'] ?> correta<?= $q['corretas'] !== 1 ? 's' : '' ?>)
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $q['ativa'] ? 'badge-success' : 'badge-secondary' ?>">
                                                <?= $q['ativa'] ? 'Ativa' : 'Inativa' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="/avaliacao web/admin/questoes.php?acao=ver&id=<?= $q['id'] ?>" class="btn btn-sm btn-outline" title="Ver">👁️</a>
                                            <a href="/avaliacao web/admin/questoes.php?acao=editar&id=<?= $q['id'] ?>" class="btn btn-sm btn-secondary" title="Editar">✏️</a>
                                            <a href="/avaliacao web/admin/questoes.php?acao=excluir&id=<?= $q['id'] ?>&confirm=1"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Tem certeza que deseja excluir esta questão?')"
                                               title="Excluir">🗑️</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($questoes)): ?>
                                    <tr><td colspan="7" class="text-center">Nenhuma questão cadastrada</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($acao === 'novo' || $acao === 'editar'): ?>
                <!-- FORMULÁRIO NOVO/EDITAR -->
                <div class="admin-header-title">
                    <h2><?= $acao === 'novo' ? 'Nova Questão' : 'Editar Questão' ?></h2>
                    <p><?= $acao === 'novo' ? 'Cadastre uma nova questão para a avaliação' : 'Atualize os dados da questão' ?></p>
                </div>

                <?php if ($mensagem): ?><div class="alert alert-success"><?= e($mensagem) ?></div><?php endif; ?>
                <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

                <form method="POST" class="admin-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(gerarTokenCSRF()) ?>">

                    <div class="form-group">
                        <label for="enunciado">Enunciado *</label>
                        <textarea id="enunciado" name="enunciado" rows="4" required><?= e($questaoEditar['enunciado'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo">Tipo *</label>
                            <select id="tipo" name="tipo" required>
                                <option value="multipla_escolha" <?= ($questaoEditar['tipo'] ?? '') === 'multipla_escolha' ? 'selected' : '' ?>>Múltipla Escolha</option>
                                <option value="verdadeiro_falso" <?= ($questaoEditar['tipo'] ?? '') === 'verdadeiro_falso' ? 'selected' : '' ?>>Verdadeiro ou Falso</option>
                                <option value="dissertativa" <?= ($questaoEditar['tipo'] ?? '') === 'dissertativa' ? 'selected' : '' ?>>Dissertativa</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="categoria">Categoria *</label>
                            <input type="text" id="categoria" name="categoria" value="<?= e($questaoEditar['categoria'] ?? '') ?>" required placeholder="Ex: PHP, SQL, Segurança">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="explicacao">Explicação / gabarito esperado (opcional)</label>
                        <textarea id="explicacao" name="explicacao" rows="3"><?= e($questaoEditar['explicacao'] ?? '') ?></textarea>
                        <small>Explicação exibida ao aluno após a avaliação (nas dissertativas, serve de gabarito para a correção)</small>
                    </div>

                    <fieldset id="fieldset-alternativas" style="border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 1rem; margin-bottom: 1.5rem;">
                        <legend style="font-weight: 600; padding: 0 0.5rem;">Alternativas</legend>
                        <p style="margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.875rem;">
                            Adicione pelo menos 2 alternativas. Marque a(s) correta(s) com o checkbox.
                            Este bloco é ignorado em questões dissertativas.
                        </p>

                        <div id="alternativas-container">
                            <?php
                            $alts = $questaoEditar['alternativas'] ?? [];
                            if (empty($alts)) {
                                $alts = [['texto' => '', 'correta' => false], ['texto' => '', 'correta' => false]];
                            }
                            foreach ($alts as $i => $alt): ?>
                                <div class="alternativa-row" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <span style="width: 30px; text-align: center; font-weight: 600; color: var(--text-secondary);"><?= chr(65 + $i) ?></span>
                                    <input type="text" name="alternativas[<?= $i ?>][texto]" value="<?= e($alt['texto'] ?? '') ?>" placeholder="Texto da alternativa" style="flex: 1;" required>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; white-space: nowrap;">
                                        <input type="checkbox" name="alternativas[<?= $i ?>][correta]" value="1" <?= !empty($alt['correta']) ? 'checked' : '' ?>>
                                        Correta
                                    </label>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removerAlternativa(this)" title="Remover">🗑️</button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="btn btn-secondary btn-sm" onclick="adicionarAlternativa()">➕ Adicionar Alternativa</button>
                    </fieldset>

                    <div class="form-actions">
                        <a href="/avaliacao web/admin/questoes.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><?= $acao === 'novo' ? 'Cadastrar' : 'Atualizar' ?></button>
                    </div>
                </form>

            <?php elseif ($acao === 'ver' && $questaoEditar): ?>
                <!-- VISUALIZAR -->
                <div class="admin-header-title">
                    <h2>Visualizar Questão #<?= $questaoEditar['id'] ?></h2>
                </div>

                <div class="admin-section">
                    <div class="questao-item">
                        <div class="questao-header">
                            <div class="questao-info">
                                <p class="questao-enunciado"><?= e($questaoEditar['enunciado']) ?></p>
                                <div class="questao-meta">
                                    <span>Tipo: <?= e(rotuloTipoQuestao($questaoEditar['tipo'])[0]) ?></span>
                                    <span>Categoria: <?= e($questaoEditar['categoria']) ?></span>
                                    <span>Status: <?= $questaoEditar['ativa'] ? 'Ativa' : 'Inativa' ?></span>
                                </div>
                            </div>
                            <div class="questao-actions">
                                <a href="/avaliacao web/admin/questoes.php?acao=editar&id=<?= $questaoEditar['id'] ?>" class="btn btn-secondary">Editar</a>
                                <a href="/avaliacao web/admin/questoes.php" class="btn btn-outline">Voltar</a>
                            </div>
                        </div>
                        <div class="questao-body">
                            <?php if (!empty($questaoEditar['explicacao'])): ?>
                                <p><strong>Explicação:</strong> <?= e($questaoEditar['explicacao']) ?></p>
                            <?php endif; ?>
                            <?php if ($questaoEditar['tipo'] === 'dissertativa'): ?>
                                <p style="color: var(--text-secondary); font-size: 0.9rem;">
                                    Questão dissertativa — o aluno responde por texto e a correção é manual.
                                </p>
                            <?php else: ?>
                                <div class="alternativas-list">
                                    <?php foreach ($questaoEditar['alternativas'] as $i => $alt): ?>
                                        <div class="alternativa-item <?= $alt['correta'] ? 'correta' : '' ?>">
                                            <span class="alternativa-letra-admin"><?= chr(65 + $i) ?></span>
                                            <span class="alternativa-texto-admin"><?= e($alt['texto']) ?></span>
                                            <?php if ($alt['correta']): ?>
                                                <span class="alternativa-badge correta">Correta</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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

    <script>
        // Mostra o bloco de alternativas apenas para questões objetivas
        const tipoSelect = document.getElementById('tipo');
        const fieldsetAlternativas = document.getElementById('fieldset-alternativas');

        function atualizarTipoQuestao() {
            if (!tipoSelect || !fieldsetAlternativas) return;

            const ehDissertativa = tipoSelect.value === 'dissertativa';
            fieldsetAlternativas.style.display = ehDissertativa ? 'none' : '';

            fieldsetAlternativas.querySelectorAll('input[type="text"]').forEach((input) => {
                input.required = !ehDissertativa;
            });
        }

        if (tipoSelect) {
            tipoSelect.addEventListener('change', atualizarTipoQuestao);
            atualizarTipoQuestao();
        }

        let altIndex = <?= count($questaoEditar['alternativas'] ?? []) ?: 2 ?>;

        function adicionarAlternativa() {
            const container = document.getElementById('alternativas-container');
            const div = document.createElement('div');
            div.className = 'alternativa-row';
            div.style.cssText = 'display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;';
            div.innerHTML = `
                <span style="width: 30px; text-align: center; font-weight: 600; color: var(--text-secondary);">${String.fromCharCode(65 + altIndex)}</span>
                <input type="text" name="alternativas[${altIndex}][texto]" placeholder="Texto da alternativa" style="flex: 1;" required>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; white-space: nowrap;">
                    <input type="checkbox" name="alternativas[${altIndex}][correta]" value="1"> Correta
                </label>
                <button type="button" class="btn btn-sm btn-danger" onclick="removerAlternativa(this)" title="Remover">🗑️</button>
            `;
            container.appendChild(div);
            altIndex++;
        }

        function removerAlternativa(btn) {
            const rows = document.querySelectorAll('.alternativa-row');
            if (rows.length <= 2) {
                alert('Mínimo de 2 alternativas');
                return;
            }
            btn.closest('.alternativa-row').remove();
            // Reordena letras
            document.querySelectorAll('.alternativa-row').forEach((row, i) => {
                row.querySelector('span').textContent = String.fromCharCode(65 + i);
                const input = row.querySelector('input[type="text"]');
                const checkbox = row.querySelector('input[type="checkbox"]');
                if (input) input.name = `alternativas[${i}][texto]`;
                if (checkbox) checkbox.name = `alternativas[${i}][correta]`;
            });
            altIndex = rows.length - 1;
        }
    </script>
</body>
</html>