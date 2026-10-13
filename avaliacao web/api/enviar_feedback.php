<?php
/**
 * API: Registrar avaliação do site (estrelas + comentário)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

iniciarSessaoSegura();

if (!estaLogado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
if (!validarTokenCSRF($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$estrelas = (int)($_POST['estrelas'] ?? 0);
$comentario = sanitizar((string)($_POST['comentario'] ?? ''));

if ($estrelas < 1 || $estrelas > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Escolha de 1 a 5 estrelas']);
    exit;
}

$ok = salvarFeedback((int)$_SESSION['usuario_id'], $estrelas, $comentario);

if ($ok) {
    echo json_encode(['success' => true, 'estrelas' => $estrelas]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar a avaliação']);
}
