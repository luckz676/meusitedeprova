<?php
/**
 * Gerenciamento de Avaliações - Admin
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requerAdmin();

$avaliacaoId = (int)($_GET['id'] ?? 0);
$acao = $_GET['acao'] ?? 'listar';
$filtroStatus = $_GET['status'] ?? '';
$filtroAluno = (int)($_GET['aluno'] ?? 0);

$mensagem = '';
$erro = '';

// Lista avaliações com filtros
$avaliacoes = [];
try {
    $pdo = getDbConnection();
    $sql = "SELECT a.*, u.nome as aluno_nome, u.email as aluno_email
            FROM avaliacoes a
            JOIN usuarios u ON a.usuario_id = u.id
            WHERE 1=1";
    $params = [];

    if ($filtroStatus) {
        $sql .= " AND a.status = ?";
        $params[] = $filtroStatus;
    }
    if ($filtroAluno) {
        $sql .= " AND a.usuario_id = ?";
        $params[] = $filtroAluno;
    }

    $sql .= " ORDER BY a.inicio DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $avaliacoes = $stmt->fetchAll();
} catch (Exception $e) {
    $erro = 'Erro ao listar avaliações.';
}

// Busca alunos para filtro
$alunos = [];
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT id, nome FROM usuarios WHERE tipo = 'aluno' ORDER BY nome");
    $alunos = $stmt->fetchAll();
} catch (Exception $e) {}

// Ver detalhes
$avaliacaoDetalhe = null;
$respostasDetalhe = [];
$eventosDetalhe = [];
if ($acao === 'ver' && $avaliacaoId > 0) {
    $avaliacaoDetalhe = obterAvaliacaoPorId($avaliacaoId);
    if ($avaliacaoDetalhe) {
        try {
            $pdo = getDbConnection();
            // Respostas
            $stmt = $pdo->prepare(
                "SELECT r.*, q.enunciado, q.tipo, q.categoria, q.explicacao,
                        a.texto as alternativa_texto, a.correta as alternativa_correta
                 FROM respostas r
                 JOIN questoes q ON r.questao_id = q.id
                 LEFT JOIN alternativas a ON r.alternativa_id = a.id
                 WHERE r.avaliacao_id = ?
                 ORDER BY r.created_at"
            );
            $stmt->execute([$avaliacaoId]);
            $respostasDetalhe = $stmt->fetchAll();

            // Eventos suspeitos
            $eventosDetalhe = listarEventosSuspeitos($avaliacaoId);
        } catch (Exception $e) {
            $erro = 'Erro ao carregar detalhes.';
        }
    } else {
        $erro = 'Avaliação não encontrada.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliações - Admin</title>
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
                    <li><a href="/avaliacao web/admin/usuarios.php">👥 Usuários</a></li>
                    <li><a href="/avaliacao web/admin/avaliacoes.php" class="active">📋 Avaliações</a></li>
                    <li><a href="/avaliacao web/admin/eventos.php">🚨 Eventos Suspeitos</a></li>
                </ul>
                <hr style="margin: 1rem 0; border-color: var(--border-color);">
                <li><a href="/avaliacao web/dashboard.php" class="btn btn-outline">👁️ Ver como Aluno</a></li>
            </nav>
        </aside>

        <main class="admin-main">
            <?php if ($acao === 'listar'): ?>
                <div class="admin-header-title">
                    <h2>Avaliações Realizadas</h2>
                    <p>Visualize e gerencie todas as avaliações do sistema</p>
                </div>

                <?php if ($mensagem): ?><div class="alert alert-success"><?= e($mensagem) ?></div><?php endif; ?>
                <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

                <!-- Filtros -->
                <div class="admin-section">
                    <div class="filters">
                        <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="">Todos</option>
                                    <option value="finalizada" <?= $filtroStatus === 'finalizada' ? 'selected' : '' ?>>Finalizada</option>
                                    <option value="fraude" <?= $filtroStatus === 'fraude' ? 'selected' : '' ?>>🚨 Encerrada por Fraude</option>
                                    <option value="tempo_esgotado" <?= $filtroStatus === 'tempo_esgotado' ? 'selected' : '' ?>>Tempo Esgotado</option>
                                    <option value="em_andamento" <?= $filtroStatus === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                                    <option value="cancelada" <?= $filtroStatus === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Aluno</label>
                                <select name="aluno">
                                    <option value="">Todos</option>
                                    <?php foreach ($alunos as $aluno): ?>
                                        <option value="<?= $aluno['id'] ?>" <?= $filtroAluno === $aluno['id'] ? 'selected' : '' ?>>
                                            <?= e($aluno['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <a href="/avaliacao web/admin/avaliacoes.php" class="btn btn-secondary">Limpar</a>
                        </form>
                    </div>
                </div>

                <!-- Tabela -->
                <div class="admin-section">
                    <div class="section-header">
                        <h3>Avaliações (<?= count($avaliacoes) ?>)</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Aluno</th>
                                    <th>E-mail</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th>Duração</th>
                                    <th>Status</th>
                                    <th>Nota</th>
                                    <th>Acertos/Erros</th>
                                    <th>Eventos</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($avaliacoes as $av): ?>
                                    <?php
                                        $duracao = '—';
                                        if ($av['fim']) {
                                            $diff = strtotime($av['fim']) - strtotime($av['inicio']);
                                            $duracao = gmdate('H:i:s', $diff);
                                        }
                                        $eventosCount = 0;
                                        try {
                                            $pdo = getDbConnection();
                                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM eventos_suspeitos WHERE avaliacao_id = ?");
                                            $stmt->execute([$av['id']]);
                                            $eventosCount = $stmt->fetchColumn();
                                        } catch (Exception $e) {}
                                    ?>
                                    <tr>
                                        <td><?= $av['id'] ?></td>
                                        <td><?= e($av['aluno_nome']) ?></td>
                                        <td><?= e($av['aluno_email']) ?></td>
                                        <td><?= formatarData($av['inicio']) ?></td>
                                        <td><?= $av['fim'] ? formatarData($av['fim']) : '—' ?></td>
                                        <td><span style="font-family: var(--font-mono);"><?= $duracao ?></span></td>
                                        <td>
                                            <span class="badge <?= match($av['status']) {
                                                'finalizada' => 'badge-success',
                                                'tempo_esgotado' => 'badge-warning',
                                                'fraude' => 'badge-danger',
                                                'em_andamento' => 'badge-info',
                                                default => 'badge-secondary'
                                            } ?>">
                                                <?= $av['status'] === 'fraude' ? 'Fraude' : ucfirst(str_replace('_', ' ', $av['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= $av['nota'] !== null ? '<strong>' . number_format($av['nota'], 1) . '</strong>' : '—' ?></td>
                                        <td><?= $av['acertos'] ?? 0 ?> / <?= $av['erros'] ?? 0 ?></td>
                                        <td>
                                            <span class="badge <?= $eventosCount > 0 ? 'badge-danger' : 'badge-success' ?>">
                                                <?= $eventosCount ?> evento<?= $eventosCount !== 1 ? 's' : '' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="/avaliacao web/admin/avaliacoes.php?acao=ver&id=<?= $av['id'] ?>" class="btn btn-sm btn-outline">Ver Detalhes</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($avaliacoes)): ?>
                                    <tr><td colspan="11" class="text-center">Nenhuma avaliação encontrada</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($acao === 'ver' && $avaliacaoDetalhe): ?>
                <div class="admin-header-title">
                    <div>
                        <h2>Avaliação #<?= $avaliacaoDetalhe['id'] ?></h2>
                        <p><?= e($avaliacaoDetalhe['aluno_nome']) ?> - <?= formatarData($avaliacaoDetalhe['inicio']) ?></p>
                    </div>
                    <a href="/avaliacao web/admin/avaliacoes.php" class="btn btn-outline">← Voltar</a>
                </div>

                <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

                <!-- Info Principal -->
                <div class="admin-section">
                    <div class="section-header"><h3>Informações Gerais</h3></div>
                    <div style="padding: 1.5rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div>
                            <strong>Aluno:</strong> <?= e($avaliacaoDetalhe['aluno_nome']) ?><br>
                            <small><?= e($avaliacaoDetalhe['aluno_email']) ?></small>
                        </div>
                        <div>
                            <strong>Início:</strong> <?= formatarData($avaliacaoDetalhe['inicio']) ?>
                        </div>
                        <div>
                            <strong>Fim:</strong> <?= $avaliacaoDetalhe['fim'] ? formatarData($avaliacaoDetalhe['fim']) : '—' ?>
                        </div>
                        <div>
                            <strong>Tempo Limite:</strong> <?= gmdate('H:i:s', $avaliacaoDetalhe['tempo_limite_segundos']) ?>
                        </div>
                        <div>
                            <strong>Status:</strong>
                            <span class="badge <?= match($avaliacaoDetalhe['status']) {
                                'finalizada' => 'badge-success',
                                'tempo_esgotado' => 'badge-warning',
                                'fraude' => 'badge-danger',
                                'em_andamento' => 'badge-info',
                                default => 'badge-secondary'
                            } ?>">
                                <?= $avaliacaoDetalhe['status'] === 'fraude' ? 'Fraude' : ucfirst(str_replace('_', ' ', $avaliacaoDetalhe['status'])) ?>
                            </span>
                        </div>
                        <div>
                            <strong>Nota Final:</strong> <?= $avaliacaoDetalhe['nota'] !== null ? '<span style="font-size: 1.25rem; font-weight: 700; color: var(--primary);">' . number_format($avaliacaoDetalhe['nota'], 1) . '</span>' : '—' ?>
                        </div>
                        <div>
                            <strong>Acertos:</strong> <?= $avaliacaoDetalhe['acertos'] ?? 0 ?>
                        </div>
                        <div>
                            <strong>Erros:</strong> <?= $avaliacaoDetalhe['erros'] ?? 0 ?>
                        </div>
                        <div>
                            <strong>Total Questões:</strong> <?= $avaliacaoDetalhe['total_questoes'] ?? 0 ?>
                        </div>
                    </div>
                </div>

                <!-- Respostas -->
                <div class="admin-section">
                    <div class="section-header"><h3>Respostas (<?= count($respostasDetalhe) ?>)</h3></div>
                    <div class="table-responsive" style="padding: 0 1.5rem 1.5rem;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Questão</th>
                                    <th>Categoria</th>
                                    <th>Resposta do Aluno</th>
                                    <th>Correta</th>
                                    <th>Resultado</th>
                                    <th>Tempo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($respostasDetalhe as $i => $r): ?>
                                    <?php
                                        $correta = false;
                                        $altCorretaTexto = '';
                                        try {
                                            $pdo = getDbConnection();
                                            $stmt = $pdo->prepare("SELECT texto FROM alternativas WHERE questao_id = ? AND correta = 1");
                                            $stmt->execute([$r['questao_id']]);
                                            $altCorreta = $stmt->fetch();
                                            if ($altCorreta) {
                                                $altCorretaTexto = $altCorreta['texto'];
                                                $correta = ($r['alternativa_id'] && $r['alternativa_correta']) ? true : false;
                                            }
                                        } catch (Exception $e) {}
                                    ?>
                                    <tr class="<?= $correta ? 'table-success' : 'table-danger' ?>">
                                        <td><?= $i + 1 ?></td>
                                        <td style="max-width: 250px;">
                                            <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                <?= e($r['enunciado']) ?>
                                            </div>
                                        </td>
                                        <td><span class="badge badge-info"><?= e($r['categoria']) ?></span></td>
                                        <td>
                                            <?= $r['alternativa_texto'] ? e($r['alternativa_texto']) : '<em class="text-muted">Não respondida</em>' ?>
                                        </td>
                                        <td>
                                            <?= $altCorretaTexto ? e($altCorretaTexto) : '—' ?>
                                        </td>
                                        <td>
                                            <?php if (!$r['alternativa_texto']): ?>
                                                <span class="badge badge-secondary">Não respondida</span>
                                            <?php elseif ($correta): ?>
                                                <span class="badge badge-success">✓ Correta</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">✗ Incorreta</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span style="font-family: var(--font-mono);"><?= gmdate('i:s', $r['tempo_resposta_segundos'] ?? 0) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($respostasDetalhe)): ?>
                                    <tr><td colspan="7" class="text-center">Nenhuma resposta registrada</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Eventos Suspeitos -->
                <div class="admin-section">
                    <div class="section-header"><h3>Eventos Suspeitos (<?= count($eventosDetalhe) ?>)</h3></div>
                    <div class="table-responsive" style="padding: 0 1.5rem 1.5rem;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Descrição</th>
                                    <th>Data/Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eventosDetalhe as $ev): ?>
                                    <tr>
                                        <td>
                                            <span class="evento-tipo <?= $ev['tipo_evento'] ?>">
                                                <?= ucfirst(str_replace('_', ' ', $ev['tipo_evento'])) ?>
                                            </span>
                                        </td>
                                        <td><?= e($ev['descricao'] ?? '—') ?></td>
                                        <td><span style="font-family: var(--font-mono);"><?= formatarData($ev['timestamp']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($eventosDetalhe)): ?>
                                    <tr><td colspan="3" class="text-center">Nenhum evento suspeito registrado</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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

    <style>
        .table-success { background: var(--success-light) !important; }
        .table-danger { background: var(--danger-light) !important; }
    </style>
</body>
</html>