<?php
/**
 * Fichiers et documents (sections 7 et 32).
 *
 * ------------------------------------------------------------------------
 * C'EST ICI QU'UN PORTAIL RH FUIT.
 *
 * Trois regles, et aucune n'est negociable :
 *
 * 1. Le fichier n'est JAMAIS dans la racine web. Il vit dans
 *    donnees/documents/, servi par /document/<id> apres verification du
 *    droit d'acces. Un repertoire d'upload accessible en direct rend un
 *    bulletin de paie lisible par quiconque devine son adresse — et les
 *    adresses se devinent : bulletin-2026-08-1045.pdf.
 *
 * 2. Le nom du fichier sur le disque est TIRE AU HASARD. Le nom d'origine
 *    est garde en base pour l'affichage et le telechargement, jamais dans
 *    le chemin. « arret-travail-depression.pdf » ne doit pas exister comme
 *    nom de fichier sur un serveur partage.
 *
 * 3. Le droit d'acces est verifie AVANT d'ouvrir le fichier, dans une seule
 *    fonction, et cette fonction est la seule porte. Une vue qui cache un
 *    lien n'a rien protege.
 * ------------------------------------------------------------------------
 */

declare(strict_types=1);

function repertoire_documents(): string
{
    $d = (string) cfg('chemin_documents');
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}

/**
 * Enregistre un fichier televerse. Rend l'identifiant, ou un message
 * d'erreur traduit.
 */
function deposer_fichier(array $f, int $depose_par): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['erreur' => t('fic_aucun')];
    }
    if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
        return ['erreur' => t('fic_echec_transfert')];
    }
    if ((int) $f['size'] > (int) cfg('taille_max_doc')) {
        return ['erreur' => t('fic_trop_gros', ['mo' => (int) (cfg('taille_max_doc') / 1048576)])];
    }

    // Le type est lu DANS le fichier, pas dans l'en-tete envoye par le
    // navigateur. Le champ $_FILES['type'] est controle par le client :
    // il annonce ce qu'il veut.
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $fi->file($f['tmp_name']);
    if (!in_array($mime, cfg('types_doc'), true)) {
        return ['erreur' => t('fic_type_refuse', ['type' => $mime])];
    }

    $ext = match ($mime) {
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        default => 'bin',
    };
    $chemin = gmdate('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    $abs = repertoire_documents() . '/' . $chemin;
    if (!is_dir(dirname($abs))) @mkdir(dirname($abs), 0700, true);

    if (!move_uploaded_file($f['tmp_name'], $abs)
        && !rename($f['tmp_name'], $abs)) {     // le second cas sert aux tests CLI
        journal('erreur', 'deplacement impossible', ['vers' => $chemin]);
        return ['erreur' => t('fic_echec_transfert')];
    }
    @chmod($abs, 0600);

    $id = insere('fichiers', [
        'chemin' => $chemin,
        'nom_origine' => mb_substr((string) $f['name'], 0, 255),
        'mime' => $mime, 'taille' => (int) $f['size'],
        'sha256' => hash_file('sha256', $abs),
        'depose_par' => $depose_par, 'cree_le' => maintenant(),
    ]);
    return ['id' => $id];
}

/**
 * A-t-on le droit de lire ce document ? Rend true / false, et c'est la
 * SEULE fonction qui repond a cette question.
 *
 * L'ordre des tests dit la politique :
 *   - le document d'entreprise (pas d'utilisateur_id) est lisible de tous ;
 *   - son propre document est lisible par soi ;
 *   - la RH lit tout ;
 *   - le MANAGER ne lit que le non-confidentiel de son equipe. Un bulletin
 *     de paie, un contrat et un justificatif medical sont marques
 *     confidentiels : son propre chef ne les ouvre pas.
 */
function peut_lire_document(array $doc): bool
{
    $u = utilisateur();
    if (!$u) return false;

    if (empty($doc['utilisateur_id'])) return peut('documents.siens') || peut('documents.gerer');
    if ((int) $doc['utilisateur_id'] === (int) $u['id']) return true;
    if (peut('documents.gerer')) return true;

    if ((int) ($doc['confidentiel'] ?? 0) === 1) return false;
    if ($doc['categorie'] === 'justificatif') return peut('absences.justificatif');
    return peut('equipe.voir') && est_mon_subordonne((int) $doc['utilisateur_id']);
}

/** Categories toujours confidentielles, quel que soit le depot. */
const CATEGORIES_CONFIDENTIELLES = ['bulletin', 'fiscal', 'justificatif', 'contrat', 'avenant'];

function categorie_confidentielle(string $cat): bool
{
    return in_array($cat, CATEGORIES_CONFIDENTIELLES, true);
}

function creer_document(array $d): int
{
    $d['confidentiel'] = (int) (($d['confidentiel'] ?? 0) || categorie_confidentielle((string) ($d['categorie'] ?? '')));
    $d['cree_le'] = maintenant();
    $id = insere('documents', $d);
    audit('document.depot', 'document', $id, [], ['categorie' => $d['categorie'] ?? '']);
    return $id;
}

/** Sert le fichier. Ne rend jamais le chemin disque au client. */
function servir_document(int $id): never
{
    $doc = qun('SELECT * FROM documents WHERE id = ?', [$id]);
    if (!$doc) reponse_refus(404, t('refus_introuvable'));
    if (!peut_lire_document($doc)) {
        // On journalise le refus : une tentative repetee sur des documents
        // qui ne sont pas les siens est exactement ce qu'on veut voir.
        audit('document.refus', 'document', $id);
        reponse_refus(403, t('refus_droit'));
    }
    $f = qun('SELECT * FROM fichiers WHERE id = ?', [(int) $doc['fichier_id']]);
    if (!$f) reponse_refus(404, t('refus_introuvable'));

    $abs = repertoire_documents() . '/' . $f['chemin'];
    if (!is_file($abs)) {
        journal('erreur', 'fichier absent du disque', ['document' => $id]);
        reponse_refus(404, t('refus_introuvable'));
    }

    audit('document.lecture', 'document', $id);

    header('Content-Type: ' . $f['mime']);
    header('Content-Length: ' . filesize($abs));
    // inline pour un PDF, attachment sinon. Et toujours un nom lisible :
    // le salarie recoit « bulletin-2026-08.pdf », pas « a3f9…bin ».
    $disp = $f['mime'] === 'application/pdf' ? 'inline' : 'attachment';
    header('Content-Disposition: ' . $disp . '; filename="'
         . str_replace(['"', "\r", "\n"], '', (string) $f['nom_origine']) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($abs);
    exit;
}

/** Les documents visibles par $lecteur sur le dossier de $cible. */
function documents_de(int $cible_id, ?string $categorie = null): array
{
    $params = [$cible_id];
    $sql = 'SELECT d.*, f.nom_origine, f.mime, f.taille
            FROM documents d LEFT JOIN fichiers f ON f.id = d.fichier_id
            WHERE d.utilisateur_id = ?';
    if ($categorie) { $sql .= ' AND d.categorie = ?'; $params[] = $categorie; }
    $sql .= ' ORDER BY d.cree_le DESC';
    // Le filtre de droit s'applique LIGNE PAR LIGNE, apres la requete. Il
    // serait plus rapide de le mettre dans le WHERE ; il serait aussi
    // duplique a chaque appel, et c'est comme cela qu'une copie oublie une
    // condition.
    return array_values(array_filter(qtous($sql, $params), 'peut_lire_document'));
}

function documents_entreprise(): array
{
    return qtous('SELECT d.*, f.nom_origine, f.mime, f.taille
                  FROM documents d LEFT JOIN fichiers f ON f.id = d.fichier_id
                  WHERE d.utilisateur_id IS NULL ORDER BY d.categorie, d.titre');
}

function taille_lisible(?int $o): string
{
    $o = (int) $o;
    if ($o < 1024) return $o . ' o';
    if ($o < 1048576) return round($o / 1024) . ' Ko';
    return round($o / 1048576, 1) . ' Mo';
}
