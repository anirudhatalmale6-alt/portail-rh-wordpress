<?php
/**
 * Coffre : chiffrement des donnees bancaires (section 4.3).
 *
 * ------------------------------------------------------------------------
 * CE QUE CE FICHIER PROTEGE, ET CE QU'IL NE PROTEGE PAS.
 *
 * Il protege contre UNE chose precise et frequente : la base de donnees
 * qui sort de l'entreprise. Une sauvegarde .sql telechargee, un export
 * envoye a un prestataire, un acces phpMyAdmin laisse ouvert. Dans ces
 * cas-la, `banque_chiffre` est illisible sans la cle, et la cle n'est pas
 * dans la base — elle est dans src/config.local.php, qui n'est ni dans le
 * depot ni dans un dump SQL.
 *
 * Il ne protege PAS contre quelqu'un qui a pris le serveur entier : celui
 * qui lit config.local.php lit la cle. C'est une limite reelle, elle est
 * ecrite ici pour qu'elle ne soit pas decouverte le mauvais jour. La
 * defense contre ce cas-la n'est pas cryptographique, elle est dans les
 * droits d'acces au serveur.
 *
 * AES-256-GCM et pas AES-256-CBC : GCM authentifie. Avec CBC, un octet
 * modifie en base donne un dechiffrement different sans erreur — donc un
 * numero de compte silencieusement faux sur un virement.
 * ------------------------------------------------------------------------
 */

declare(strict_types=1);

const COFFRE_ALGO = 'aes-256-gcm';

function coffre_cle(): ?string
{
    $b64 = (string) cfg('cle_coffre');
    if ($b64 === '') return null;
    $cle = base64_decode($b64, true);
    if ($cle === false || strlen($cle) !== 32) {
        journal('critique', 'cle_coffre invalide : longueur ' . ($cle === false ? 'non base64' : strlen($cle)));
        return null;
    }
    return $cle;
}

function coffre_disponible(): bool
{
    return coffre_cle() !== null && in_array(COFFRE_ALGO, openssl_get_cipher_methods(), true);
}

/**
 * Chiffre une valeur. Rend une chaine base64 de nonce|tag|chiffre, ou null
 * si le coffre n'est pas configure.
 *
 * On rend null au lieu de retomber sur du texte en clair. Le champ reste
 * vide et l'interface affiche « coffre non configure » — c'est visible et
 * ca se corrige. Un repli silencieux en clair, lui, ne se voit jamais.
 */
function coffre_chiffre(string $clair): ?string
{
    $cle = coffre_cle();
    if ($cle === null) return null;
    $nonce = random_bytes(12);
    $tag = '';
    $chiffre = openssl_encrypt($clair, COFFRE_ALGO, $cle, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($chiffre === false) {
        journal('critique', 'chiffrement impossible');
        return null;
    }
    return base64_encode($nonce . $tag . $chiffre);
}

function coffre_dechiffre(?string $paquet): ?string
{
    if ($paquet === null || $paquet === '') return null;
    $cle = coffre_cle();
    if ($cle === null) return null;
    $brut = base64_decode($paquet, true);
    if ($brut === false || strlen($brut) < 29) return null;
    $nonce = substr($brut, 0, 12);
    $tag = substr($brut, 12, 16);
    $chiffre = substr($brut, 28);
    $clair = openssl_decrypt($chiffre, COFFRE_ALGO, $cle, OPENSSL_RAW_DATA, $nonce, $tag);
    return $clair === false ? null : $clair;
}

/**
 * Le masque affiche partout dans l'interface.
 *
 * Quatre derniers caracteres, precedes de puces. Assez pour qu'un salarie
 * reconnaisse son compte, pas assez pour s'en servir. C'est cette valeur
 * qui est stockee en clair dans `banque_masque` et c'est la SEULE forme du
 * numero qu'une requete de liste peut ramener.
 */
function coffre_masque(string $clair): string
{
    $n = preg_replace('/[^0-9A-Za-z]/', '', $clair) ?? '';
    if ($n === '') return '';
    $fin = substr($n, -4);
    return '•••• ' . $fin;
}

/**
 * Verifie qu'un numero est plausible sans pretendre le valider.
 *
 * Je ne valide PAS un IBAN par sa cle de controle ni un numero de transit
 * canadien par sa longueur : les formats different par pays et par banque,
 * et un refus a tort empeche un salarie d'etre paye. On refuse seulement
 * ce qui est manifestement vide ou trop court.
 */
function banque_plausible(string $s): bool
{
    $n = preg_replace('/[^0-9A-Za-z]/', '', $s) ?? '';
    return strlen($n) >= 5 && strlen($n) <= 40;
}
