<?php
/**
 * Inicia nova avaliação
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requerAluno();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /avaliacao web/dashboard.php');
    exit;
}

if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token de segurança inválido.');
    header('Location: /avaliacao web/dashboard.php');
    exit;
}

$usuarioId = $_SESSION['usuario_id'];

// Se já existe uma prova em andamento, retoma ela em vez de criar outra
$avaliacaoAtiva = obterAvaliacaoAtiva($usuarioId);
if ($avaliacaoAtiva) {
    header('Location: /avaliacao web/avaliacao.php');
    exit;
}

// Sorteia as questões (intercalando objetivas e dissertativas)
$questoes = montarQuestoesProva();

if (empty($questoes)) {
    flash('error', 'Nenhuma questão cadastrada no sistema.');
    header('Location: /avaliacao web/dashboard.php');
    exit;
}

$avaliacaoId = criarAvaliacao($usuarioId, TEMPO_LIMITE_PADRAO, $questoes);

if (!$avaliacaoId) {
    flash('error', 'Erro ao iniciar avaliação. Tente novamente.');
    header('Location: /avaliacao web/dashboard.php');
    exit;
}

flash('success', 'Avaliação iniciada! Boa prova!');
header('Location: /avaliacao web/avaliacao.php');
exit;
