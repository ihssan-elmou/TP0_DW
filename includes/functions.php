<?php

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

?>