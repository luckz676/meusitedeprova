<?php
/**
 * API: Obter respostas já salvas da avaliação
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

requerAluno();

$avaliacaoId = (int)($_GET['avaliacao_id'] ?? 0);

if ($avaliacaoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$avaliacao = obterAvaliacaoPorId($avaliacaoId);
if (!$avaliacao || $avaliacao['usuario_id'] != $_SESSION['usuario_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        "SELECT questao_id, alternativa_id, resposta_texto FROM respostas WHERE avaliacao_id = ?"
    );
    $stmt->execute([$avaliacaoId]);
    $respostas = $stmt->fetchAll();

    echo json_encode(['success' => true, 'respostas' => $respostas]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar respostas']);
}