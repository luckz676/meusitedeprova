<?php
/**
 * TEMPORÁRIO — correção única dos horários gravados antes do ajuste de fuso.
 *
 * O site gravava inicio/fim/eventos com o relógio do MySQL, que roda em UTC
 * nas hospedagens. As linhas antigas ficaram adiantadas em relação ao horário
 * de Brasília. Este arquivo mede o desvio comparando `inicio` (DATETIME, veio
 * do NOW()) com `created_at` (TIMESTAMP, que o MySQL converte sozinho) da
 * mesma linha, mostra tudo e só altera com ?aplicar=1. Apagar depois de usar.
 */
require __DIR__ . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['token'] ?? '') !== 'HORARIOS2026') {
    http_response_code(403);
    exit("sem token\n");
}

$pdo = getDbConnection();

$phpAgora = date('Y-m-d H:i:s');
$phpFuso  = date('P');
$utc      = $pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn();
$tzGlobal = $pdo->query('SELECT @@global.time_zone')->fetchColumn();
$tzSessao = $pdo->query("SELECT @@session.time_zone")->fetchColumn();

echo "PHP agora      : {$phpAgora} ({$phpFuso})\n";
echo "MySQL UTC      : {$utc}\n";
echo "tz global      : {$tzGlobal}\n";
echo "tz da sessao   : {$tzSessao}\n\n";

$resumo = $pdo->query(
    "SELECT COUNT(*) AS total,
            MIN(TIMESTAMPDIFF(SECOND, inicio, created_at)) AS menor,
            MAX(TIMESTAMPDIFF(SECOND, inicio, created_at)) AS maior
     FROM avaliacoes"
)->fetch();

echo "avaliacoes: {$resumo['total']} linhas | desvio inicio->created_at: menor={$resumo['menor']}s maior={$resumo['maior']}s\n\n";

$linhas = $pdo->query(
    "SELECT id, inicio, fim, created_at,
            TIMESTAMPDIFF(SECOND, inicio, created_at) AS desvio
     FROM avaliacoes ORDER BY id DESC LIMIT 8"
)->fetchAll();

foreach ($linhas as $l) {
    printf(
        "#%-3s inicio=%s fim=%s created_at=%s desvio=%ss\n",
        $l['id'],
        $l['inicio'],
        $l['fim'] ?? 'NULL',
        $l['created_at'],
        $l['desvio']
    );
}

$eventos = $pdo->query(
    "SELECT COUNT(*) AS total, MIN(`timestamp`) AS primeiro, MAX(`timestamp`) AS ultimo FROM eventos_suspeitos"
)->fetch();
echo "\neventos_suspeitos: {$eventos['total']} linhas | primeiro={$eventos['primeiro']} ultimo={$eventos['ultimo']}\n";

$desvio = (int)$resumo['menor'];
$consistente = $resumo['total'] > 0 && (int)$resumo['menor'] === (int)$resumo['maior'];

echo "\ndesvio consistente em todas as linhas: " . ($consistente ? 'sim' : 'NAO') . " ({$desvio}s)\n";

if (($_GET['aplicar'] ?? '') !== '1') {
    exit("\n(previa: nada foi alterado; use &aplicar=1 para corrigir)\n");
}

if (!$consistente || $desvio === 0) {
    exit("\nnada a corrigir\n");
}

$segundos = (string)$desvio; // inteiro, vem do proprio banco

$pdo->exec(
    "UPDATE avaliacoes
        SET inicio = DATE_ADD(inicio, INTERVAL {$segundos} SECOND),
            fim    = DATE_ADD(fim,    INTERVAL {$segundos} SECOND)"
);
$avaliacoesAfetadas = $pdo->query('SELECT ROW_COUNT()')->fetchColumn();

$pdo->exec("UPDATE eventos_suspeitos SET `timestamp` = DATE_ADD(`timestamp`, INTERVAL {$segundos} SECOND)");
$eventosAfetados = $pdo->query('SELECT ROW_COUNT()')->fetchColumn();

echo "\naplicado {$segundos}s em {$avaliacoesAfetadas} avaliacoes e {$eventosAfetados} eventos\n";

$depois = $pdo->query(
    "SELECT COUNT(*) AS total,
            MIN(TIMESTAMPDIFF(SECOND, inicio, created_at)) AS menor,
            MAX(TIMESTAMPDIFF(SECOND, inicio, created_at)) AS maior
     FROM avaliacoes"
)->fetch();
echo "conferencia: desvio agora menor={$depois['menor']}s maior={$depois['maior']}s\n";

echo "\nultimas linhas:\n";
foreach ($pdo->query("SELECT id, inicio, fim FROM avaliacoes ORDER BY id DESC LIMIT 5")->fetchAll() as $l) {
    printf("  #%-3s %s -> %s\n", $l['id'], $l['inicio'], $l['fim'] ?? 'em andamento');
}
