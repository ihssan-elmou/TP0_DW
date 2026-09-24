<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =========================================================
   CONFIGURATION SMTP
   ========================================================= */
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'douaeelmoudni235@gmail.com');
define('SMTP_PASS', 'jxsz cdxd sdks toge');
define('SMTP_PORT', 587);
define('SMTP_FROM', 'douaeelmoudni235@gmail.com');
define('SMTP_FROM_NAME', 'TP0 - Gestion des emails');


/* =========================================================
   PARTIE 1
   ========================================================= */

function filtrerEmailsValides($fichierEntree, $fichierInvalides) {
    $lignes = file($fichierEntree, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $valides = [];
    $invalides = [];

    foreach ($lignes as $email) {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $valides[] = $email;
        } else {
            $invalides[] = $email;
        }
    }

    file_put_contents($fichierInvalides, implode(PHP_EOL, $invalides));

    return $valides;
}

function supprimerDoublons($emails) {
    return array_values(array_unique($emails));
}

function trierEtSauvegarder($emails, $fichierSortie) {
    sort($emails, SORT_STRING | SORT_FLAG_CASE);
    file_put_contents($fichierSortie, implode(PHP_EOL, $emails));
    return $emails;
}

function separerParDomaine($emails, $dossierSortie) {
    $parDomaine = [];

    foreach ($emails as $email) {
        $domaine = strtolower(substr(strrchr($email, "@"), 1));
        $parDomaine[$domaine][] = $email;
    }

    foreach ($parDomaine as $domaine => $liste) {
        file_put_contents("$dossierSortie/$domaine.txt", implode(PHP_EOL, $liste));
    }

    return $parDomaine;
}


/* =========================================================
   PARTIE 2 : fonctions pour l'interface web
   ========================================================= */

/**
 * Liste les fichiers texte présents dans un dossier donné
 * (utilisé pour afficher les fichiers générés à télécharger/envoyer).
 */
function listerFichiersGeneres($dossier) {
    $fichiers = [];
    if (!is_dir($dossier)) {
        return $fichiers;
    }
    foreach (scandir($dossier) as $f) {
        if ($f === '.' || $f === '..') continue;
        if (is_file("$dossier/$f") && str_ends_with($f, '.txt')) {
            $fichiers[] = $f;
        }
    }
    sort($fichiers);
    return $fichiers;
}

/**
 * Lit une liste d'adresses email valides depuis un fichier (une par ligne).
 */
function lireAdressesValides($fichier) {
    if (!file_exists($fichier)) {
        return [];
    }
    return file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

/**
 * Envoie un email (avec pièces jointes optionnelles) à un ou plusieurs destinataires,
 * via PHPMailer + SMTP.
 *
 * @param array  $destinataires  liste d'adresses email
 * @param string $objet          objet du message
 * @param string $contenu        corps du message (texte brut)
 * @param array  $piecesJointes  chemins des fichiers à joindre (facultatif)
 * @return bool  true si l'envoi a réussi pour TOUS les destinataires
 */
function envoyerEmail($destinataires, $objet, $contenu, $piecesJointes = []) {
    $succes = true;

    foreach ((array) $destinataires as $dest) {
        $dest = trim($dest);
        if ($dest === '' || !filter_var($dest, FILTER_VALIDATE_EMAIL)) {
            $succes = false;
            continue;
        }

        $mail = new PHPMailer(true);

        try {
            // Configuration serveur SMTP
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            // Expéditeur / destinataire
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($dest);

            // Pièces jointes
            foreach ($piecesJointes as $chemin) {
                if (file_exists($chemin)) {
                    $mail->addAttachment($chemin);
                }
            }

            // Contenu
            $mail->isHTML(false);
            $mail->Subject = $objet;
            $mail->Body    = $contenu;

            $mail->send();
        } catch (Exception $e) {
            $succes = false;
            error_log("Échec de l'envoi à $dest : " . $mail->ErrorInfo);
        }
    }

    return $succes;
}
/**
 * Vérifie si une adresse existe déjà dans le fichier (insensible à la casse)
 */
function adresseExisteDeja($email, $fichier) {
    if (!file_exists($fichier)) {
        return false;
    }
    $adresses = file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $email = strtolower(trim($email));

    foreach ($adresses as $a) {
        if (strtolower(trim($a)) === $email) {
            return true;
        }
    }
    return false;
}

/**
 * Ajoute une adresse au fichier, puis retrie le fichier entier
 */
function ajouterAdresse($email, $fichier) {
    $adresses = file_exists($fichier)
        ? file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        : [];

    $adresses[] = trim($email);
    sort($adresses, SORT_STRING | SORT_FLAG_CASE);

    file_put_contents($fichier, implode(PHP_EOL, $adresses));
}

function domaineExiste($email) {

    $domaine = substr(strrchr($email, "@"), 1);

    return checkdnsrr($domaine, "A") ||
           checkdnsrr($domaine, "AAAA") ;
}


function domainePossedeMX($email) {

    $domaine = substr(strrchr($email, "@"), 1);

    return checkdnsrr($domaine, "MX");
}

/**
 * Ajoute une adresse à EmailsT.txt et régénère les fichiers de domaine,
 * en réutilisant les fonctions de la Partie 1. Emails.txt (source) n'est pas modifié.
 */
function ajouterAuxFichiersCorrespondants($email, $fichierTrie, $dossierGenerated) {
    $listeActuelle = file_exists($fichierTrie)
        ? file($fichierTrie, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        : [];

    $listeActuelle[] = trim($email);

    $sansDoublons = supprimerDoublons($listeActuelle);
    $trie         = trierEtSauvegarder($sansDoublons, $fichierTrie);
    separerParDomaine($trie, $dossierGenerated);
}



