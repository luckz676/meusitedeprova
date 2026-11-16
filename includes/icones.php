<?php
/**
 * Ícones do sistema (SVG desenhado, traço 1.6, 24x24).
 *
 * Emoji não é ícone: cada um é desenhado por uma fonte diferente, com peso e
 * alinhamento próprios, e no meio de uma tabela isso vira ruído. Estes herdam a
 * cor do texto (currentColor), então funcionam no papel claro e no fundo escuro.
 */

/**
 * Devolve o SVG de um ícone.
 *
 * @param string $nome    Chave do ícone (ex: 'dashboard')
 * @param int    $tamanho Lado em pixels
 * @param string $classe  Classe extra no elemento
 * @return string HTML do SVG (vazio se o nome não existir)
 */
function icone(string $nome, int $tamanho = 18, string $classe = ''): string
{
    $formas = [
        'dashboard' => '<rect x="3" y="3" width="7.5" height="7.5" rx="1"/><rect x="13.5" y="3" width="7.5" height="4.5" rx="1"/><rect x="13.5" y="10.5" width="7.5" height="10.5" rx="1"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1"/>',
        'questoes' => '<path d="M7 3.5h7l4 4v13H7z"/><path d="M14 3.5V8h4"/><path d="M9.5 12.5h6M9.5 15.5h4"/>',
        'alunos' => '<path d="M2.5 9 12 4.5 21.5 9 12 13.5z"/><path d="M6.5 11.2v4.6c0 1.6 2.5 2.9 5.5 2.9s5.5-1.3 5.5-2.9v-4.6"/>',
        'usuarios' => '<circle cx="9" cy="8.5" r="3.2"/><path d="M3 20c0-3.1 2.7-5 6-5s6 1.9 6 5"/><path d="M16 6.2a3 3 0 0 1 0 5.6"/><path d="M17.5 15.4c2 .6 3.5 2.2 3.5 4.6"/>',
        'avaliacoes' => '<path d="M8 4.5H6.5A1.5 1.5 0 0 0 5 6v14.5h14V6a1.5 1.5 0 0 0-1.5-1.5H16"/><rect x="8.5" y="2.5" width="7" height="4" rx="1"/><path d="M8.5 12h7M8.5 16h4.5"/>',
        'eventos' => '<path d="M12 3.5 21 20H3z"/><path d="M12 9.5v4.5"/><circle cx="12" cy="17" r="0.9" fill="currentColor" stroke="none"/>',
        'escudo' => '<path d="M12 3 20 6v6c0 4.4-3.2 7.7-8 9-4.8-1.3-8-4.6-8-9V6z"/>',
        'alerta' => '<path d="M12 3.5 21 20H3z"/><path d="M12 9.5v4.5"/><circle cx="12" cy="17" r="0.9" fill="currentColor" stroke="none"/>',
        'olho' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.8"/>',
        'editar' => '<path d="M4 20h4L20 8l-4-4L4 16z"/><path d="M14.5 5.5 18.5 9.5"/>',
        'excluir' => '<path d="M4.5 7h15"/><path d="M9.5 3.8h5"/><path d="M6.5 7l.9 13h9.2l.9-13"/><path d="M10.5 11v5.5M13.5 11v5.5"/>',
        'mais' => '<path d="M12 5v14M5 12h14"/>',
        'bloquear' => '<circle cx="12" cy="12" r="8.5"/><path d="M6 6l12 12"/>',
        'liberar' => '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 12.2l2.6 2.6 4.6-5"/>',
        'camera' => '<rect x="3" y="7" width="12.5" height="10" rx="1.5"/><path d="M15.5 11.5 21 8.5v7l-5.5-3z"/>',
        'relogio' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'documento' => '<path d="M6.5 3.5H14l4 4v13H6.5z"/><path d="M14 3.5V8h4"/><path d="M9.5 12.5h6M9.5 15.5h6"/>',
        'estrela' => '<path d="M12 3.8l2.5 5.2 5.6.7-4.1 3.9 1 5.6-5-2.7-5 2.7 1-5.6L3.9 9.7l5.6-.7z"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'seta' => '<path d="M6 9.5l6 6 6-6"/>',
        'sair' => '<path d="M15 5.5h3.5v13H15"/><path d="M12 12H4.5"/><path d="M7.5 8.5 4 12l3.5 3.5"/>',
        'pessoa' => '<circle cx="12" cy="8.5" r="3.4"/><path d="M5 20c0-3.4 3.1-5.6 7-5.6s7 2.2 7 5.6"/>',
    ];

    if (!isset($formas[$nome])) {
        return '';
    }

    $atributosClasse = $classe !== '' ? ' class="' . e($classe) . '"' : '';

    return '<svg' . $atributosClasse . ' width="' . $tamanho . '" height="' . $tamanho . '"'
        . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $formas[$nome] . '</svg>';
}
