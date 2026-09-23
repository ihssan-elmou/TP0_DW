<?php
session_start();
require_once 'includes/functions.php';

$dossierFiles     = 'files'; 
$dossierGenerated = 'generated';
$fichierEntree    = "$dossierFiles/Emails.txt";
$fichierInvalides = "$dossierGenerated/Emailinvalide.txt";
$fichierTrie      = "$dossierGenerated/EmailsT.txt";

if (!is_dir($dossierFiles))     mkdir($dossierFiles, 0777, true);
if (!is_dir($dossierGenerated)) mkdir($dossierGenerated, 0777, true);

$messages = [];
$action   = $_POST['action'] ?? null;

/* -----------------------------------------------------------
   1. Upload du fichier + lancement du traitement (Partie 1)
   ----------------------------------------------------------- */
if ($action === 'uploader') {
    if (isset($_FILES['fichierEmails']) && $_FILES['fichierEmails']['error'] === UPLOAD_ERR_OK) {

        move_uploaded_file($_FILES['fichierEmails']['tmp_name'], $fichierEntree);

        // On repart d'un dossier "generated" propre à chaque nouveau traitement
        foreach (glob("$dossierGenerated/*.txt") as $f) {
            unlink($f);
        }

        $valides      = filtrerEmailsValides($fichierEntree, $fichierInvalides);
        $sansDoublons = supprimerDoublons($valides);
        $trie         = trierEtSauvegarder($sansDoublons, $fichierTrie);
        $parDomaine   = separerParDomaine($trie, $dossierGenerated);

        $nbInvalides = file_exists($fichierInvalides) ? count(file($fichierInvalides, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;

        $messages[] = [
            'type' => 'succes',
            'texte' => "Traitement terminé : " . count($trie) . " email(s) valide(s)/unique(s), "
                     . "$nbInvalides invalide(s), " . count($parDomaine) . " domaine(s) trouvé(s)."
        ];
    } else {
        $messages[] = ['type' => 'erreur', 'texte' => "Erreur lors de l'envoi du fichier."];
    }
}

/* -----------------------------------------------------------
   2. Envoi des fichiers générés sélectionnés vers une adresse
   ----------------------------------------------------------- */
if ($action === 'envoyer_fichiers') {
    $destinataire     = trim($_POST['destinataire'] ?? '');
    $fichiersChoisis  = $_POST['fichiers'] ?? [];

    if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
        $messages[] = ['type' => 'erreur', 'texte' => "Adresse destinataire invalide."];
    } elseif (empty($fichiersChoisis)) {
        $messages[] = ['type' => 'erreur', 'texte' => "Veuillez sélectionner au moins un fichier à envoyer."];
    } else {
        $chemins = array_map(
            fn($f) => "$dossierGenerated/" . basename($f), // basename() évite toute injection de chemin
            $fichiersChoisis
        );
        $ok = envoyerEmail(
            [$destinataire],
            "Fichiers générés - TP0",
            "Bonjour,\n\nVeuillez trouver ci-joint le(s) fichier(s) demandé(s).\n\nCordialement.",
            $chemins
        );
        $messages[] = $ok
            ? ['type' => 'succes', 'texte' => "Fichier(s) envoyé(s) à $destinataire."]
            : ['type' => 'erreur', 'texte' => "Échec de l'envoi des fichiers."];
    }
}

/* -----------------------------------------------------------
   3. Envoi d'un message aux adresses email sélectionnées
   ----------------------------------------------------------- */
if ($action === 'envoyer_message') {
    $destinataires = $_POST['adresses'] ?? [];
    $objet         = trim($_POST['objet'] ?? '');
    $contenu       = trim($_POST['contenu'] ?? '');
    $piecesJointes = [];

    if (empty($destinataires)) {
        $messages[] = ['type' => 'erreur', 'texte' => "Veuillez sélectionner au moins un destinataire."];
    } elseif ($objet === '' || $contenu === '') {
        $messages[] = ['type' => 'erreur', 'texte' => "L'objet et le contenu du message sont obligatoires."];
    } else {
        if (isset($_FILES['pieceJointe']) && $_FILES['pieceJointe']['error'] === UPLOAD_ERR_OK) {
            $dossierTmp = "$dossierGenerated/tmp";
            if (!is_dir($dossierTmp)) mkdir($dossierTmp, 0777, true);
            $chemin = $dossierTmp . '/' . basename($_FILES['pieceJointe']['name']);
            move_uploaded_file($_FILES['pieceJointe']['tmp_name'], $chemin);
            $piecesJointes[] = $chemin;
        }

        $ok = envoyerEmail($destinataires, $objet, $contenu, $piecesJointes);
        $messages[] = $ok
            ? ['type' => 'succes', 'texte' => "Message envoyé à " . count($destinataires) . " destinataire(s)."]
            : ['type' => 'erreur', 'texte' => "Échec de l'envoi du message."];
    }
}


/* -----------------------------------------------------------
   4. Ajout d'une nouvelle adresse email
   ----------------------------------------------------------- */
if ($action === 'ajouter_adresse') {

    $nouvelleAdresse = trim($_POST['nouvelleAdresse'] ?? '');

    // 1. Syntaxe
    if (!filter_var($nouvelleAdresse, FILTER_VALIDATE_EMAIL)) {

        $messages[] = [
            'type' => 'erreur',
            'texte' => "Format d'adresse invalide."
        ];

    // 2. Doublon
    } elseif (adresseExisteDeja($nouvelleAdresse, $fichierTrie)) {

        $messages[] = [
            'type' => 'erreur',
            'texte' => "Cette adresse existe déjà."
        ];

    // 3. Domaine
    } elseif (!domaineExiste($nouvelleAdresse)) {

        $messages[] = [
            'type' => 'erreur',
            'texte' => "Le domaine de cette adresse n'existe pas."
        ];

    // 4. Serveur MX
    } elseif (!domainePossedeMX($nouvelleAdresse)) {

        $messages[] = [
            'type' => 'erreur',
            'texte' => "Le domaine ne possède pas de serveur de messagerie MX."
        ];

        // 5. Ajout
    } else {

        ajouterAuxFichiersCorrespondants($nouvelleAdresse, $fichierTrie, $dossierGenerated);

        $messages[] = [
            'type' => 'succes',
            'texte' => "Adresse « $nouvelleAdresse » ajoutée avec succès."
        ];
    }
}



/* -----------------------------------------------------------
   Données pour l'affichage
   ----------------------------------------------------------- */
$fichiersGeneres = listerFichiersGeneres($dossierGenerated);
$adressesValides = lireAdressesValides($fichierTrie);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>TP0 - Gestion des adresses email</title>
   <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Application de gestion des adresses email</h1>

<?php foreach ($messages as $m): ?>
    <div class="message <?= htmlspecialchars($m['type']) ?>"><?= htmlspecialchars($m['texte']) ?></div>
<?php endforeach; ?>

<!-- 1. Téléchargement du fichier + lancement du traitement -->
<section>
    <h2>1. Télécharger le fichier des emails à traiter</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="fichierEmails" accept=".txt" required>
        <input type="hidden" name="action" value="uploader">
        <button type="submit">Lancer le traitement</button>
    </form>
</section>

<!-- 2. Fichiers générés : téléchargement + envoi -->
<?php if (!empty($fichiersGeneres)): ?>
<section>
    <h2>2. Fichiers générés</h2>
    <form method="post">
        <ul class="fichiers">
        <?php foreach ($fichiersGeneres as $f): ?>
            <li>
                <label style="display:inline">
                    <input type="checkbox" name="fichiers[]" value="<?= htmlspecialchars($f) ?>">
                    <span class="fichier-nom"><?= htmlspecialchars($f) ?></span>
                </label>
                — <a href="<?= $dossierGenerated ?>/<?= urlencode($f) ?>" download>télécharger</a>
            </li>
        <?php endforeach; ?>
        </ul>
        <input type="hidden" name="action" value="envoyer_fichiers">
        <label>Envoyer les fichiers sélectionnés à :
            <input type="email" name="destinataire" placeholder="destinataire@exemple.com" required>
        </label>
        <button type="submit">Envoyer les fichiers sélectionnés</button>
    </form>
</section>
<?php endif; ?>

<!-- 3. Envoi d'un message aux adresses sélectionnées -->
<?php if (!empty($adressesValides)): ?>
<section>
    <h2>3. Envoyer un message</h2>
    <form method="post" enctype="multipart/form-data">
        <h3 style="margin-bottom:4px;">Destinataires</h3>
        <div class="liste-adresses">
        <?php foreach ($adressesValides as $email): ?>
            <label style="display:inline-block; width:100%;">
                <input type="checkbox" name="adresses[]" value="<?= htmlspecialchars($email) ?>">
                <?= htmlspecialchars($email) ?>
            </label>
        <?php endforeach; ?>
        </div>

        <label>Objet :
            <input type="text" name="objet" required>
        </label>
        <label>Contenu :
            <textarea name="contenu" rows="5" required></textarea>
        </label>
        <label>Pièce jointe (facultatif) :
            <input type="file" name="pieceJointe">
        </label>

        <input type="hidden" name="action" value="envoyer_message">
        <button type="submit">Envoyer le message</button>
    </form>
</section>
<?php endif; ?>

<!-- 4. Ajouter une nouvelle adresse -->
<section>
    <h2>4. Ajouter une adresse email</h2>
    <form method="post" id="formAjout" novalidate>
        <label for="nouvelleAdresse">Adresse email :</label>
        <input type="email" id="nouvelleAdresse" name="nouvelleAdresse" placeholder="exemple@domaine.com" required>
        <p id="erreurClient" class="message erreur" style="display:none;"></p>
        <input type="hidden" name="action" value="ajouter_adresse">
        <button type="submit">Ajouter l'adresse</button>
    </form>
</section>

<script>
document.getElementById('formAjout').addEventListener('submit', function (e) {
    const champEmail = document.getElementById('nouvelleAdresse');
    const erreurBox = document.getElementById('erreurClient');
    const regex = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;

    if (!regex.test(champEmail.value.trim())) {
        e.preventDefault();
        erreurBox.textContent = "Format d'adresse email invalide.";
        erreurBox.style.display = 'block';
    } else {
        erreurBox.style.display = 'none';
    }
});
</script>

</body>
</html>