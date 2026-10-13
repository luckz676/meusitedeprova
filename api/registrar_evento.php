<?php
/**
 * API: Registrar evento suspeito
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

requerAluno();

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validarTokenCSRF($csrfHeader)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

$avaliacaoId = (int)($input['avaliacao_id'] ?? 0);
$tipoEvento = $input['tipo_evento'] ?? '';
$descricao = $input['descricao'] ?? '';

if ($avaliacaoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da avaliação inválido']);
    exit;
}

$tiposValidos = ['mudanca_aba', 'mudanca_janela', 'tecla_bloqueada', 'click_fora', 'devtools_aberto', 'tela_cheia_saida', 'copiar_colar', 'botao_direito', 'nova_aba', 'volta_foco', 'tentativa_saida', 'tela_cheia_negada', 'tempo_esgotado'];
if (!in_array($tipoEvento, $tiposValidos)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo de evento inválido']);
    exit;
}

$avaliacao = obterAvaliacaoPorId($avaliacaoId);
if (!$avaliacao || $avaliacao['usuario_id'] != $_SESSION['usuario_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Avaliação não encontrada ou não autorizada']);
    exit;
}

if ($avaliacao['status'] !== 'em_andamento') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Avaliação não está em andamento']);
    exit;
}

$ok = registrarEventoSuspeito($avaliacaoId, $tipoEvento, $descricao);

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar evento']);
}