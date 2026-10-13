<?php
/**
 * Tela de conclusão da avaliação
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requerAluno();

$usuarioId = $_SESSION['usuario_id'];
$avaliacaoId = (int)($_GET['id'] ?? 0);

if ($avaliacaoId <= 0) {
    $ultima = obterUltimaAvaliacaoFinalizada($usuarioId);
    $avaliacaoId = $ultima ? (int)$ultima['id'] : 0;
}

if ($avaliacaoId <= 0) {
    flash('error', 'Nenhuma avaliação concluída encontrada.');
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

// Quantas questões de cada tipo a prova tinha e quantas foram respondidas
$totais = ['objetivas' => 0, 'dissertativas' => 0];
$respondidas = ['objetivas' => 0, 'dissertativas' => 0];

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        "SELECT q.tipo, COUNT(*) AS total
         FROM avaliacao_questoes aq
         JOIN questoes q ON q.id = aq.questao_id
         WHERE aq.avaliacao_id = ?
         GROUP BY q.tipo"
    );
    $stmt->execute([$avaliacaoId]);
    foreach ($stmt->fetchAll() as $linha) {
        if ($linha['tipo'] === 'dissertativa') {
            $totais['dissertativas'] += (int)$linha['total'];
        } else {
            $totais['objetivas'] += (int)$linha['total'];
        }
    }

    $stmt = $pdo->prepare(
        "SELECT q.tipo,
                SUM(CASE WHEN r.alternativa_id IS NOT NULL THEN 1 ELSE 0 END) AS objetivas,
                SUM(CASE WHEN r.resposta_texto IS NOT NULL AND r.resposta_texto <> '' THEN 1 ELSE 0 END) AS dissertativas
         FROM respostas r
         JOIN questoes q ON q.id = r.questao_id
         WHERE r.avaliacao_id = ?
         GROUP BY q.tipo"
    );
    $stmt->execute([$avaliacaoId]);
    foreach ($stmt->fetchAll() as $linha) {
        if ($linha['tipo'] === 'dissertativa') {
            $respondidas['dissertativas'] += (int)$linha['dissertativas'];
        } else {
            $respondidas['objetivas'] += (int)$linha['objetivas'];
        }
    }
} catch (Exception $e) {
    error_log('Erro ao montar resumo da conclusão: ' . $e->getMessage());
}

$totalRespondidas = $respondidas['objetivas'] + $respondidas['dissertativas'];
$flashes = obterFlashes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliação Concluída - <?= e(APP_NAME) ?></title>
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
        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= $flash['tipo'] === 'error' ? 'error' : ($flash['tipo'] === 'warning' ? 'warning' : 'success') ?>">
                <?= e($flash['mensagem']) ?>
            </div>
        <?php endforeach; ?>

        <div class="card conclusao-card">
            <div class="conclusao-icone">✅</div>
            <h2>Avaliação Concluída!</h2>
            <p class="conclusao-subtitulo">Suas respostas foram registradas com sucesso.</p>

            <div class="resumo-grid">
                <div class="resumo-card">
                    <span class="resumo-numero"><?= $totalRespondidas ?></span>
                    <span class="resumo-label">Respostas enviadas</span>
                </div>
                <div class="resumo-card">
                    <span class="resumo-numero"><?= $respondidas['objetivas'] ?>/<?= $totais['objetivas'] ?></span>
                    <span class="resumo-label">Objetivas respondidas</span>
                </div>
                <div class="resumo-card">
                    <span class="resumo-numero"><?= $respondidas['dissertativas'] ?>/<?= $totais['dissertativas'] ?></span>
                    <span class="resumo-label">Dissertativas enviadas</span>
                </div>
                <div class="resumo-card">
                    <span class="resumo-numero"><?= (int)$avaliacao['total_questoes'] ?></span>
                    <span class="resumo-label">Total da prova</span>
                </div>
            </div>

            <p class="conclusao-observacao">
                As questões objetivas são corrigidas automaticamente e as
                <strong>dissertativas</strong> ficam aguardando a correção do professor.
            </p>

            <div class="conclusao-actions">
                <a href="/avaliacao web/resultado.php?id=<?= $avaliacaoId ?>" class="btn btn-primary btn-lg">Ver Resultado Detalhado</a>
                <a href="/avaliacao web/dashboard.php" class="btn btn-secondary">Voltar ao Dashboard</a>
            </div>

            <div class="gif-banner">
                <img src="/avaliacao web/assets/gifs/suit-oguri-cap.gif" alt="Oguri de terno comemorando" class="gif-banner__img">
                <p class="gif-banner__texto">ebaaaa meu site tá muito bom né?</p>
            </div>
        </div>
    </main>

    <footer class="main-footer">
        <div class="container">
            <p>&copy; 2024 - Sistema de Avaliação Acadêmica</p>
        </div>
    </footer>
</body>
</html>
