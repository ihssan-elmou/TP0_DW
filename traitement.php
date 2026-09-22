<?php
require_once 'includes/functions.php';

$fichierEntree    = 'files/Emails.txt';
$fichierInvalides = 'generated/Emailinvalide.txt';
$fichierTrie      = 'generated/EmailsT.txt';
$dossierDomaines  = 'generated';

$valides      = filtrerEmailsValides($fichierEntree, $fichierInvalides);
$sansDoublons = supprimerDoublons($valides);
$trie         = trierEtSauvegarder($sansDoublons, $fichierTrie);
$parDomaine   = separerParDomaine($trie, $dossierDomaines);

echo "<h2>Traitement terminé</h2>";
echo "<p>Emails valides et uniques : " . count($trie) . "</p>";
echo "<p>Emails invalides : " . count(file($fichierInvalides)) . "</p>";
echo "<p>Domaines trouvés : " . implode(', ', array_keys($parDomaine)) . "</p>";