<?php
/**
 * Mise en forme des textes libres (actualites RH, offres, demandes).
 *
 * PRINCIPE, et il n'y a pas de variante : on N'ACCEPTE JAMAIS de HTML.
 * Le texte est d'abord echappe EN ENTIER, puis on re-injecte un petit
 * nombre de balises que l'on ecrit soi-meme. On ne nettoie donc jamais un
 * HTML hostile — on n'en recoit pas. C'est la seule facon de ne pas
 * dependre d'une liste noire toujours incomplete.
 *
 * Consequence a connaitre : un texte contenant « <b>gras</b> » ressort en
 * clair, avec ses chevrons visibles. C'est le comportement voulu, et un
 * test de la suite le verifie explicitement — il verifie que la chaine
 * apparait ECHAPPEE, pas qu'elle a disparu.
 *
 * Syntaxe : **gras**  *italique*  `code`  > citation  - liste
 *           [texte](https://…)   [texte](/chemin/interne)
 */

declare(strict_types=1);

function rendre_texte(string $corps): string
{
    $s = h($corps);
    $s = str_replace("\r\n", "\n", $s);

    // Liens externes : http/https uniquement. javascript: et data: ne
    // franchissent pas ce filtre parce qu'il est une liste BLANCHE.
    $s = preg_replace_callback('/\[([^\]\n]{1,120})\]\((https?:\/\/[^\s)]{1,500})\)/i',
        fn($m) => lien_html($m[2], $m[1]), $s) ?? $s;

    // Liens internes en chemin absolu. Le motif exige une seule barre suivie
    // d'autre chose qu'une barre : « //evil.example » est une URL absolue de
    // protocole relatif, pas un chemin interne.
    $s = preg_replace_callback('#\[([^\]\n]{1,120})\]\((/[^/\s)][^\s)]{0,200})\)#',
        fn($m) => '<a href="' . h(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8')) . '">'
                . $m[1] . '</a>', $s) ?? $s;

    $s = preg_replace_callback('#(?<![">=])\b(https?://[^\s<]{4,500})#i',
        fn($m) => lien_html($m[1], $m[1]), $s) ?? $s;

    $s = preg_replace('/`([^`\n]{1,200})`/', '<code>$1</code>', $s) ?? $s;
    $s = preg_replace('/\*\*([^*\n]{1,300})\*\*/', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace('/(?<![\*\w])\*([^*\n]{1,300})\*(?!\w)/', '<em>$1</em>', $s) ?? $s;

    return blocs($s);
}

function lien_html(string $url, string $texte): string
{
    $u = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    if (!preg_match('#^https?://#i', $u)) return h($texte);
    $interne = false;
    $dom = cfg('domaine');
    if ($dom) $interne = str_starts_with($u, rtrim($dom, '/'));
    $rel = $interne ? '' : ' rel="nofollow noopener" target="_blank"';
    return '<a href="' . h($u) . '"' . $rel . '>' . $texte . '</a>';
}

function blocs(string $s): string
{
    $out = []; $liste = false; $cit = false; $para = [];

    $ferme_para = function () use (&$para, &$out) {
        if ($para) { $out[] = '<p>' . implode('<br>', $para) . '</p>'; $para = []; }
    };
    $ferme_liste = function () use (&$liste, &$out) {
        if ($liste) { $out[] = '</ul>'; $liste = false; }
    };
    $ferme_cit = function () use (&$cit, &$out) {
        if ($cit) { $out[] = '</blockquote>'; $cit = false; }
    };

    foreach (explode("\n", $s) as $ligne) {
        $l = rtrim($ligne);
        if (trim($l) === '') { $ferme_para(); $ferme_liste(); $ferme_cit(); continue; }

        if (preg_match('/^\s*&gt;\s?(.*)$/', $l, $m)) {
            $ferme_para(); $ferme_liste();
            if (!$cit) { $out[] = '<blockquote>'; $cit = true; }
            $out[] = '<p>' . $m[1] . '</p>';
            continue;
        }
        $ferme_cit();

        if (preg_match('/^\s*[-*]\s+(.*)$/', $l, $m)) {
            $ferme_para();
            if (!$liste) { $out[] = '<ul>'; $liste = true; }
            $out[] = '<li>' . $m[1] . '</li>';
            continue;
        }
        $ferme_liste();
        $para[] = $l;
    }
    $ferme_para(); $ferme_liste(); $ferme_cit();
    return implode("\n", $out);
}

/** Extrait sans balise, pour les listes et la recherche. */
function extrait(string $corps, int $n = 180): string
{
    $t = preg_replace('/https?:\/\/\S+/u', ' ', $corps) ?? $corps;
    $t = preg_replace('/[*`>#\-\[\]()]/u', ' ', $t) ?? $t;
    $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
    return mb_strlen($t) > $n ? mb_substr($t, 0, $n - 1) . '…' : $t;
}
