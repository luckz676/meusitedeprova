<?php
/**
 * TEMPORÁRIO — teste do fuso: grava uma avaliação como o site grava, confere
 * se o horário bate com o do PHP e apaga. Não deixa nada no banco.
 */
require __DIR__ . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['token'] ?? '') !== 'HORARIOS2026') {
    http_response_code(403);
    exit("sem token\n");
}

$pdo = getDbConnection();

$antes = date('Y-m-d H:i:s');
$stmt = $pdo->prepare(
    "INSERT INTO avaliacoes (usuario_id, tempo_limite_segundos, inicio, total_questoes, status)
     VALUES ((SELECT id FROM usuarios ORDER BY id LIMIT 1), 3600, NOW(), 0, 'em_andamento')"
);
$stmt->execute();
$id = (int)$pdo->lastInsertId();

$linha = $pdo->query("SELECT inicio, created_at FROM avaliacoes WHERE id = {$id}")->fetch();
$dif = strtotime($linha['inicio']) - strtotime($antes);

printf("PHP antes do INSERT : %s\n", $antes);
printf("inicio gravado      : %s\n", $linha['inicio']);
printf("created_at          : %s\n", $linha['created_at']);
printf("diferenca PHP x inicio: %ss (esperado: 0 a 2)\n", $dif);

$ok = abs($dif) <= 2;

$pdo->exec("DELETE FROM avaliacoes WHERE id = {$id}");
$sobrou = $pdo->query("SELECT COUNT(*) FROM avaliacoes WHERE id = {$id}")->fetchColumn();
printf("linha de teste apagada: %s\n", $sobrou == 0 ? 'sim' : 'NAO');
printf("\nresultado: %s\n", $ok ? 'OK — o horario gravado e o do fuso do site' : 'FALHOU');
