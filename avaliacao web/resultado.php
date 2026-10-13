<?php
/**
 * Página de Resultado da Avaliação
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requerAluno();

$usuarioId = $_SESSION['usuario_id'];
$avaliacaoId = (int)($_GET['id'] ?? 0);

if ($avaliacaoId <= 0) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT * FROM avaliacoes
             WHERE usuario_id = ? AND status IN ('finalizada', 'tempo_esgotado', 'fraude')
             ORDER BY fim DESC LIMIT 1"
        );
        $stmt->execute([$usuarioId]);
        $avaliacao = $stmt->fetch();
        if ($avaliacao) {
            $avaliacaoId = $avaliacao['id'];
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar última avaliação: " . $e->getMessage());
    }
}

if ($avaliacaoId <= 0) {
    flash('error', 'Nenhuma avaliação encontrada.');
    header('Location: /avaliacao web/dashboard.php');
    exit;
}

$avaliacao = obterAvaliacaoPorId($avaliacaoId);

if (!$avaliacao || $avaliacao['usuario_id'] != $usuarioId) {
    flash('error', 'Avaliação não encontrada.');
    header('Location: /avaliacao web/dashboard.php');
    exit;
}

if ($avaliacao['status'] === 'em_andamento') {
    header('Location: /avaliacao web/avaliacao.php');
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        "SELECT r.*, q.enunciado, q.tipo, q.explicacao, q.categoria,
                a.texto as alternativa_texto, a.correta as alternativa_correta
         FROM respostas r
         JOIN questoes q ON r.questao_id = q.id
         LEFT JOIN alternativas a ON r.alternativa_id = a.id
         WHERE r.avaliacao_id = ?
         ORDER BY r.created_at"
    );
    $stmt->execute([$avaliacaoId]);
    $detalhes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erro ao buscar detalhes: " . $e->getMessage());
    $detalhes = [];
}

$statsCategoria = [];
$totalDissertativas = 0;
foreach ($detalhes as $d) {
    if ($d['correta'] === null) {
        // Dissertativa: aguardando correção do professor
        $totalDissertativas++;
        continue;
    }

    $cat = $d['categoria'];
    if (!isset($statsCategoria[$cat])) {
        $statsCategoria[$cat] = ['total' => 0, 'acertos' => 0];
    }
    $statsCategoria[$cat]['total']++;
    if ($d['correta']) $statsCategoria[$cat]['acertos']++;
}

$totalObjetivas = ($avaliacao['acertos'] ?? 0) + ($avaliacao['erros'] ?? 0);
$aproveitamento = $totalObjetivas > 0 ? round((($avaliacao['acertos'] ?? 0) / $totalObjetivas) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="/avaliacao web/assets/css/style.css?v=<?= versaoEstatico('assets/css/style.css') ?>">
    <link rel="stylesheet" href="/avaliacao web/assets/css/resultado.css?v=<?= versaoEstatico('assets/css/resultado.css') ?>">
</head>
<body class="resultado-page">
    <header class="main-header">
        <div class="container header-content">
            <h1><?= e(APP_NAME) ?></h1>
            <div class="user-info">
                <span class="user-name"><?= e($_SESSION['usuario_nome']) ?></span>
                <a href="/avaliacao web/logout.php" class="btn btn-secondary btn-sm">Sair</a>
            </div>
        </div>
    </header>

    <main class="container main-content">
        <div class="resultado-header">
            <h2>Resultado da Avaliação</h2>
            <div class="resultado-meta">
                <span><strong>Data:</strong> <?= formatarData($avaliacao['inicio'], 'd/m/Y') ?></span>
                <span><strong>Início:</strong> <?= formatarData($avaliacao['inicio'], 'H:i:s') ?></span>
                <span><strong>Término:</strong> <?= $avaliacao['fim'] ? formatarData($avaliacao['fim'], 'H:i:s') : '—' ?></span>
                <span><strong>Duração:</strong> <?= $avaliacao['fim'] ? gmdate('H:i:s', strtotime($avaliacao['fim']) - strtotime($avaliacao['inicio'])) : '—' ?></span>
                <span><strong>Status:</strong>
                    <span class="badge <?= match($avaliacao['status']) { 'finalizada' => 'success', 'tempo_esgotado' => 'warning', 'fraude' => 'danger', default => 'secondary' } ?>">
                        <?= $avaliacao['status'] === 'fraude' ? 'Encerrada por fraude' : ucfirst(str_replace('_', ' ', $avaliacao['status'])) ?>
                    </span>
                </span>
            </div>
        </div>

        <?php if ($avaliacao['status'] === 'fraude'): ?>
            <div class="alert alert-error">
                🚨 <strong>Prova encerrada por fraude.</strong>
                O sistema anti-cola registrou violações além do limite permitido e finalizou a avaliação
                automaticamente. O professor pode ver o relatório completo de eventos.
            </div>
        <?php endif; ?>

        <div class="nota-principal">
            <div class="nota-circulo <?= ($avaliacao['nota'] ?? 0) >= 6 ? 'aprovado' : 'reprovado' ?>" style="--p: <?= (($avaliacao['nota'] ?? 0) / 10) * 100 ?>">
                <span class="nota-valor"><?= number_format($avaliacao['nota'] ?? 0, 1) ?></span>
                <span class="nota-label">/ 10</span>
            </div>
            <div class="nota-status">
                <?php if ($avaliacao['status'] === 'fraude'): ?>
                    <h3>🚨 Encerrada por fraude</h3>
                    <p>A nota exibida é apenas informativa: a prova foi finalizada pelo sistema anti-cola.</p>
                <?php else: ?>
                    <h3><?= ($avaliacao['nota'] ?? 0) >= 6 ? '🎉 Aprovado!' : '📚 Reprovado' ?></h3>
                    <p>Nota mínima para aprovação: 6,0</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="resumo-grid">
            <div class="resumo-card">
                <span class="resumo-numero"><?= $avaliacao['acertos'] ?? 0 ?></span>
                <span class="resumo-label">Acertos</span>
            </div>
            <div class="resumo-card">
                <span class="resumo-numero"><?= $avaliacao['erros'] ?? 0 ?></span>
                <span class="resumo-label">Erros</span>
            </div>
            <div class="resumo-card">
                <span class="resumo-numero"><?= $avaliacao['total_questoes'] ?? 0 ?></span>
                <span class="resumo-label">Total de Questões</span>
            </div>
            <div class="resumo-card">
                <span class="resumo-numero"><?= $aproveitamento ?>%</span>
                <span class="resumo-label">Aproveitamento (objetivas)</span>
            </div>
            <div class="resumo-card">
                <span class="resumo-numero"><?= $totalDissertativas ?></span>
                <span class="resumo-label">Dissertativas aguardando correção</span>
            </div>
        </div>

        <?php if (!empty($statsCategoria)): ?>
        <section class="categorias-section">
            <h3>Desempenho por Categoria</h3>
            <div class="categorias-grid">
                <?php foreach ($statsCategoria as $cat => $stats): ?>
                    <?php $pct = $stats['total'] > 0 ? round(($stats['acertos'] / $stats['total']) * 100) : 0; ?>
                    <div class="categoria-card">
                        <div class="categoria-header">
                            <span class="categoria-nome"><?= e($cat) ?></span>
                            <span class="categoria-pct"><?= $pct ?>%</span>
                        </div>
                        <div class="categoria-barra">
                            <div class="categoria-fill" style="width: <?= $pct ?>%"></div>
                        </div>
                        <span class="categoria-detalhe"><?= $stats['acertos'] ?> de <?= $stats['total'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="detalhamento-section">
            <h3>Detalhamento das Questões</h3>
            <div class="accordion" id="accordion-questoes">
                <?php foreach ($detalhes as $index => $d): ?>
                    <?php
                    $pendente = ($d['correta'] === null);
                    $ehDissertativa = ($d['tipo'] === 'dissertativa');
                    $classeItem = $pendente ? 'pendente' : ($d['correta'] ? 'acertou' : 'errou');
                    $classeBadge = $pendente ? 'warning' : ($d['correta'] ? 'success' : 'danger');
                    $textoBadge = $pendente ? '⏳ Aguardando correção' : ($d['correta'] ? '✓ Correta' : '✗ Incorreta');
                    ?>
                    <div class="accordion-item <?= $classeItem ?>">
                        <button class="accordion-header" type="button" aria-expanded="false">
                            <span class="questao-numero">Q<?= $index + 1 ?></span>
                            <span class="questao-categoria"><?= e($d['categoria']) ?></span>
                            <span class="questao-resultado-badge <?= $classeBadge ?>">
                                <?= $textoBadge ?>
                            </span>
                            <span class="accordion-icon">▼</span>
                        </button>
                        <div class="accordion-content">
                            <div class="questao-detalhe">
                                <p class="enunciado"><strong>Pergunta:</strong> <?= e($d['enunciado']) ?></p>

                                <?php if ($ehDissertativa): ?>
                                    <?php if (!empty($d['resposta_texto'])): ?>
                                        <p class="resposta-aluno">
                                            <strong>Sua resposta:</strong>
                                            <span class="resposta-dissertativa"><?= nl2br(e($d['resposta_texto'])) ?></span>
                                        </p>
                                    <?php else: ?>
                                        <p class="resposta-aluno sem-resposta"><strong>Sua resposta:</strong> <em>Não respondida</em></p>
                                    <?php endif; ?>
                                <?php elseif ($d['alternativa_texto']): ?>
                                    <p class="resposta-aluno">
                                        <strong>Sua resposta:</strong>
                                        <span class="<?= $d['alternativa_correta'] ? 'correta' : 'incorreta' ?>">
                                            <?= e($d['alternativa_texto']) ?>
                                        </span>
                                    </p>
                                <?php else: ?>
                                    <p class="resposta-aluno sem-resposta"><strong>Sua resposta:</strong> <em>Não respondida</em></p>
                                <?php endif; ?>

                                <?php if (!$ehDissertativa): ?>
                                    <?php
                                    $stmtCorreta = $pdo->prepare("SELECT texto FROM alternativas WHERE questao_id = ? AND correta = 1");
                                    $stmtCorreta->execute([$d['questao_id']]);
                                    $correta = $stmtCorreta->fetch();
                                    if ($correta): ?>
                                        <p class="resposta-correta"><strong>Resposta correta:</strong> <span class="correta"><?= e($correta['texto']) ?></span></p>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($d['explicacao'])): ?>
                                    <p class="explicacao"><strong>Gabarito esperado:</strong> <?= e($d['explicacao']) ?></p>
                                <?php endif; ?>

                                <p class="tempo-resposta"><strong>Tempo de resposta:</strong> <?= gmdate('i:s', $d['tempo_resposta_segundos'] ?? 0) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="resultado-actions">
            <a href="/avaliacao web/dashboard.php" class="btn btn-primary">Voltar ao Dashboard</a>
        </div>

        <div class="gif-banner">
            <img src="/avaliacao web/assets/gifs/suit-oguri-cap.gif" alt="Oguri de terno comemorando" class="gif-banner__img">
            <p class="gif-banner__texto">ebaaaa meu site tá muito bom né?</p>
        </div>
    </main>

    <footer class="main-footer">
        <div class="container">
            <p>&copy; 2024 - Sistema de Avaliação Acadêmica</p>
        </div>
    </footer>

    <script>
        document.querySelectorAll('.accordion-header').forEach(btn => {
            btn.addEventListener('click', () => {
                const item = btn.closest('.accordion-item');
                const expanded = btn.getAttribute('aria-expanded') === 'true';
                item.classList.toggle('open');
                btn.setAttribute('aria-expanded', !expanded);
            });
        });
    </script>
</body>
</html>