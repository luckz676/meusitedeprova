<?php
/**
 * Visualizar Avaliação Individual - Admin
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requerAdmin();

$avaliacaoId = (int)($_GET['id'] ?? 0);

if ($avaliacaoId <= 0) {
    header('Location: /avaliacao web/admin/avaliacoes.php');
    exit;
}

$avaliacao = obterAvaliacaoPorId($avaliacaoId);

if (!$avaliacao) {
    flash('error', 'Avaliação não encontrada.');
    header('Location: /avaliacao web/admin/avaliacoes.php');
    exit;
}

// Busca respostas
$respostas = [];
try {
    $pdo = getDbConnection();
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
    $respostas = $stmt->fetchAll();
} catch (Exception $e) {
    $respostas = [];
}

// Busca eventos
$eventos = listarEventosSuspeitos($avaliacaoId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliação #<?= $avaliacaoId ?> - Admin</title>
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
                    <li><a href="/avaliacao web/admin/eventos.php">🚨 Eventos Suspeitos</a></li>
                </ul>
                <hr style="margin: 1rem 0; border-color: var(--border-color);">
                <li><a href="/avaliacao web/dashboard.php" class="btn btn-outline">👁️ Ver como Aluno</a></li>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header-title">
                <div>
                    <h2>Avaliação #<?= $avaliacao['id'] ?></h2>
                    <p><?= e($avaliacao['aluno_nome'] ?? 'Aluno') ?> - <?= formatarData($avaliacao['inicio']) ?></p>
                </div>
                <a href="/avaliacao web/admin/avaliacoes.php" class="btn btn-outline">← Voltar</a>
            </div>

            <?php if ($avaliacao['status'] === 'fraude'): ?>
                <div class="alert alert-error" style="margin-bottom:1.25rem;">
                    🚨 <strong>Prova encerrada por fraude.</strong>
                    O aluno ultrapassou o limite de violações do sistema anti-cola e a avaliação foi
                    finalizada automaticamente. Veja abaixo os eventos registrados.
                </div>
            <?php endif; ?>

            <!-- Info Principal -->
            <div class="admin-section">
                <div class="section-header"><h3>Informações Gerais</h3></div>
                <div style="padding: 1.5rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div><strong>Aluno:</strong> <?= e($avaliacao['aluno_nome'] ?? '—') ?><br><small><?= e($avaliacao['aluno_email'] ?? '—') ?></small></div>
                    <div><strong>Início:</strong> <?= formatarData($avaliacao['inicio']) ?></div>
                    <div><strong>Fim:</strong> <?= $avaliacao['fim'] ? formatarData($avaliacao['fim']) : '—' ?></div>
                    <div><strong>Tempo Limite:</strong> <?= gmdate('H:i:s', $avaliacao['tempo_limite_segundos']) ?></div>
                    <div><strong>Status:</strong> <span class="badge <?= match($avaliacao['status']) { 'finalizada' => 'badge-success', 'tempo_esgotado' => 'badge-warning', 'fraude' => 'badge-danger', 'em_andamento' => 'badge-info', default => 'badge-secondary' } ?>"><?= $avaliacao['status'] === 'fraude' ? 'Fraude' : ucfirst(str_replace('_', ' ', $avaliacao['status'])) ?></span></div>
                    <div><strong>Nota:</strong> <?= $avaliacao['nota'] !== null ? '<span style="font-size:1.25rem;font-weight:700;color:var(--primary)">' . number_format($avaliacao['nota'],1) . '</span>' : '—' ?></div>
                    <div><strong>Acertos:</strong> <?= $avaliacao['acertos'] ?? 0 ?></div>
                    <div><strong>Erros:</strong> <?= $avaliacao['erros'] ?? 0 ?></div>
                    <div><strong>Total:</strong> <?= $avaliacao['total_questoes'] ?? 0 ?></div>
                </div>
            </div>

            <!-- Respostas -->
            <div class="admin-section">
                <div class="section-header"><h3>Respostas (<?= count($respostas) ?>)</h3></div>
                <div class="table-responsive" style="padding: 0 1.5rem 1.5rem;">
                    <table class="admin-table">
                        <thead>
                            <tr><th>#</th><th>Questão</th><th>Categoria</th><th>Resposta do Aluno</th><th>Correta</th><th>Resultado</th><th>Tempo</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($respostas as $i => $r):
                                $correta = false; $altCorretaTexto = '';
                                try {
                                    $pdo = getDbConnection();
                                    $stmt = $pdo->prepare("SELECT texto FROM alternativas WHERE questao_id = ? AND correta = 1");
                                    $stmt->execute([$r['questao_id']]);
                                    $ac = $stmt->fetch();
                                    if ($ac) { $altCorretaTexto = $ac['texto']; $correta = ($r['alternativa_id'] && $r['alternativa_correta']); }
                                } catch (Exception $e) {}
                            ?>
                            <tr class="<?= !$r['alternativa_texto'] ? '' : ($correta ? 'table-success' : 'table-danger') ?>">
                                <td><?= $i+1 ?></td>
                                <td style="max-width:250px"><div style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= e($r['enunciado']) ?></div></td>
                                <td><span class="badge badge-info"><?= e($r['categoria']) ?></span></td>
                                <td><?= $r['alternativa_texto'] ? e($r['alternativa_texto']) : '<em class="text-muted">Não respondida</em>' ?></td>
                                <td><?= $altCorretaTexto ? e($altCorretaTexto) : '—' ?></td>
                                <td>
                                    <?php if (!$r['alternativa_texto']): ?><span class="badge badge-secondary">Não respondida</span>
                                    <?php elseif ($correta): ?><span class="badge badge-success">✓ Correta</span>
                                    <?php else: ?><span class="badge badge-danger">✗ Incorreta</span><?php endif; ?>
                                </td>
                                <td><span style="font-family:var(--font-mono)"><?= gmdate('i:s',$r['tempo_resposta_segundos']??0) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($respostas)): ?><tr><td colspan="7" class="text-center">Nenhuma resposta</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Eventos -->
            <div class="admin-section">
                <div class="section-header"><h3>Eventos Suspeitos (<?= count($eventos) ?>)</h3></div>
                <div class="table-responsive" style="padding: 0 1.5rem 1.5rem;">
                    <table class="admin-table">
                        <thead><tr><th>Tipo</th><th>Descrição</th><th>Data/Hora</th></tr></thead>
                        <tbody>
                            <?php foreach ($eventos as $ev): ?>
                            <tr>
                                <td><span class="evento-tipo <?= $ev['tipo_evento'] ?>"><?= ucfirst(str_replace('_',' ',$ev['tipo_evento'])) ?></span></td>
                                <td><?= e($ev['descricao']??'—') ?></td>
                                <td><span style="font-family:var(--font-mono)"><?= formatarData($ev['timestamp']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($eventos)): ?><tr><td colspan="3" class="text-center">Nenhum evento</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <footer class="main-footer"><div class="container"><p>&copy; 2024 - Sistema de Avaliação Acadêmica - Área Administrativa</p></div></footer>
    <style>.table-success{background:var(--success-light)!important}.table-danger{background:var(--danger-light)!important}</style>
</body>
</html>