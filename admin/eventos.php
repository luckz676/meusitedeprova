<?php
/**
 * Eventos Suspeitos - Admin
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requerAdmin();

$filtroAvaliacao = (int)($_GET['avaliacao'] ?? 0);
$filtroTipo = $_GET['tipo'] ?? '';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 50;
$offset = ($pagina - 1) * $porPagina;

$mensagem = '';
$erro = '';

// Busca eventos com paginação
$eventos = [];
$totalEventos = 0;

try {
    $pdo = getDbConnection();

    $where = "WHERE 1=1";
    $params = [];

    if ($filtroAvaliacao > 0) {
        $where .= " AND e.avaliacao_id = ?";
        $params[] = $filtroAvaliacao;
    }
    if ($filtroTipo) {
        $where .= " AND e.tipo_evento = ?";
        $params[] = $filtroTipo;
    }

    // Total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM eventos_suspeitos e $where");
    $stmt->execute($params);
    $totalEventos = (int)$stmt->fetchColumn();

    // Dados
    $sql = "SELECT e.*, a.inicio as avaliacao_inicio, u.nome as aluno_nome, u.email as aluno_email
            FROM eventos_suspeitos e
            JOIN avaliacoes a ON e.avaliacao_id = a.id
            JOIN usuarios u ON a.usuario_id = u.id
            $where
            ORDER BY e.timestamp DESC
            LIMIT $porPagina OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $eventos = $stmt->fetchAll();
} catch (Exception $e) {
    $erro = 'Erro ao buscar eventos.';
}

// Busca avaliações para filtro
$avaliacoesFiltro = [];
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT a.id, u.nome FROM avaliacoes a JOIN usuarios u ON a.usuario_id = u.id ORDER BY a.inicio DESC LIMIT 100");
    $avaliacoesFiltro = $stmt->fetchAll();
} catch (Exception $e) {}

// Tipos de evento
$tiposEvento = [
    'mudanca_aba' => 'Mudança de Aba',
    'mudanca_janela' => 'Mudança de Janela',
    'tecla_bloqueada' => 'Tecla Bloqueada',
    'click_fora' => 'Click Fora',
    'devtools_aberto' => 'DevTools Aberto',
    'tela_cheia_saida' => 'Saída Tela Cheia',
    'copiar_colar' => 'Copiar/Colar',
    'botao_direito' => 'Botão Direito',
    'volta_foco' => 'Volta ao Foco',
    'tentativa_saida' => 'Tentativa de Saída',
    'tela_cheia_negada' => 'Tela Cheia Negada',
    'webcam_autorizada' => 'Webcam Liberada',
    'webcam_negada' => 'Webcam Negada',
    'webcam_desligada' => 'Webcam Desligada',
    'olhar_desviado' => 'Olhar Desviado',
    'rosto_fora' => 'Rosto Fora da Câmera'
];

$totalPaginas = ceil($totalEventos / $porPagina);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos Suspeitos - Admin</title>
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
                    <li><a href="/avaliacao web/admin/avaliacoes.php">📋 Avaliações</a></li>
                    <li><a href="/avaliacao web/admin/eventos.php" class="active">🚨 Eventos Suspeitos</a></li>
                </ul>
                <hr style="margin: 1rem 0; border-color: var(--border-color);">
                <li><a href="/avaliacao web/dashboard.php" class="btn btn-outline">👁️ Ver como Aluno</a></li>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header-title">
                <h2>Eventos Suspeitos</h2>
                <p>Monitoramento de comportamentos suspeitos durante avaliações</p>
            </div>

            <?php if ($mensagem): ?><div class="alert alert-success"><?= e($mensagem) ?></div><?php endif; ?>
            <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

            <!-- Filtros -->
            <div class="admin-section">
                <div class="filters">
                    <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                        <div class="filter-group">
                            <label>Avaliação</label>
                            <select name="avaliacao">
                                <option value="">Todas</option>
                                <?php foreach ($avaliacoesFiltro as $av): ?>
                                    <option value="<?= $av['id'] ?>" <?= $filtroAvaliacao === $av['id'] ? 'selected' : '' ?>>
                                        #<?= $av['id'] ?> - <?= e($av['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Tipo de Evento</label>
                            <select name="tipo">
                                <option value="">Todos</option>
                                <?php foreach ($tiposEvento as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $filtroTipo === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="/avaliacao web/admin/eventos.php" class="btn btn-secondary">Limpar</a>
                    </form>
                </div>
            </div>

            <!-- Tabela -->
            <div class="admin-section">
                <div class="section-header">
                    <h3>Eventos Registrados (<?= $totalEventos ?> total)</h3>
                </div>
                <div class="table-responsive" style="padding: 0 1.5rem 1.5rem;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Aluno</th>
                                <th>Avaliação</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th>Data/Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventos as $ev): ?>
                                <tr>
                                    <td><?= $ev['id'] ?></td>
                                    <td>
                                        <?= e($ev['aluno_nome']) ?><br>
                                        <small><?= e($ev['aluno_email']) ?></small>
                                    </td>
                                    <td>
                                        #<?= $ev['avaliacao_id'] ?><br>
                                        <small><?= formatarData($ev['avaliacao_inicio'], 'd/m H:i') ?></small>
                                    </td>
                                    <td>
                                        <span class="evento-tipo <?= $ev['tipo_evento'] ?>">
                                            <?= $tiposEvento[$ev['tipo_evento']] ?? $ev['tipo_evento'] ?>
                                        </span>
                                    </td>
                                    <td><?= e($ev['descricao'] ?? '—') ?></td>
                                    <td><span style="font-family: var(--font-mono);"><?= formatarData($ev['timestamp']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($eventos)): ?>
                                <tr><td colspan="6" class="text-center">Nenhum evento encontrado</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <?php if ($totalPaginas > 1): ?>
                    <div style="padding: 1rem 1.5rem; display: flex; justify-content: center; gap: 0.5rem; flex-wrap: wrap;">
                        <?php if ($pagina > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>" class="btn btn-sm btn-outline">« Anterior</a>
                        <?php endif; ?>

                        <?php
                        $inicio = max(1, $pagina - 2);
                        $fim = min($totalPaginas, $pagina + 2);
                        for ($i = $inicio; $i <= $fim; $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"
                               class="btn btn-sm <?= $i === $pagina ? 'btn-primary' : 'btn-outline' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagina < $totalPaginas): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>" class="btn btn-sm btn-outline">Próxima »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Estatísticas por tipo -->
            <?php if ($totalEventos > 0): ?>
            <div class="admin-section">
                <div class="section-header"><h3>Estatísticas por Tipo</h3></div>
                <div style="padding: 1.5rem;">
                    <?php
                    try {
                        $pdo = getDbConnection();
                        $stmt = $pdo->query(
                            "SELECT tipo_evento, COUNT(*) as total
                             FROM eventos_suspeitos
                             GROUP BY tipo_evento
                             ORDER BY total DESC"
                        );
                        $statsTipo = $stmt->fetchAll();
                    } catch (Exception $e) {
                        $statsTipo = [];
                    }
                    ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                        <?php foreach ($statsTipo as $st): ?>
                            <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 1rem; text-align: center;">
                                <div style="font-size: 2rem; font-weight: 700; font-family: var(--font-mono); color: var(--danger);"><?= $st['total'] ?></div>
                                <div style="color: var(--text-secondary); font-size: 0.875rem; text-transform: capitalize;"><?= str_replace('_', ' ', $st['tipo_evento']) ?></div>
                            </div>
                        <?php endforeach; ?>
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