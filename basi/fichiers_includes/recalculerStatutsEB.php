<?php
/**
 * recalculerStatutsEB.php — à exécuter UNE FOIS après phase3-ecarts-solde.sql
 * (ligne de commande), puis à volonté en cas de doute.
 *
 * Recalcule le statut de toutes les demandes du circuit (3 à 10) à partir de
 * leurs quantités, avec la même fonction que les contrôleurs. Utile à la
 * migration : une demande ancienne au statut 6 (« Terminée » selon l'ancienne
 * définition) dont tout a déjà été livré et reçu doit passer à 8 Clôturée,
 * alors qu'aucune action utilisateur ne viendra le déclencher.
 * Idempotent : sans changement de quantités, un second passage ne modifie rien.
 *
 *   php recalculerStatutsEB.php            → applique
 *   php recalculerStatutsEB.php --simuler  → liste les changements sans rien écrire
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Exécution en ligne de commande uniquement.'); }

include_once('../../bdBASI.php');      // ← ajuster selon l'emplacement réel
include_once('ebWorkflow.php');

date_default_timezone_set('Africa/Dakar');
$simuler = in_array('--simuler', $argv ?? [], true);
$bd = (new BDBASI())->connect();
if (!$bd) { fwrite(STDERR, "Connexion base de données impossible.\n"); exit(1); }

$date = date('Y-m-d H:i:s');
$motif = 'Recalcul des statuts après migration du circuit livraison/réception';
$ids = $bd->query("SELECT id, idStatut FROM expression_besoin WHERE idStatut IN (3,5,6,7,8,9,10) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$modifiees = 0;
foreach ($ids as $eb) {
    try {
        $bd->beginTransaction();
        $avant = (int) $eb['idStatut'];
        // En simulation le calcul est réellement exécuté, puis annulé (rollBack) :
        // rien n'est conservé.
        $apres = ebw_recalculerStatutEB($bd, (int) $eb['id'], $motif, $date);
        if ($apres !== null && $apres !== $avant) {
            $modifiees++;
            echo "Demande #{$eb['id']} : $avant → $apres" . ($simuler ? " (simulation)" : "") . "\n";
        }
        $simuler ? $bd->rollBack() : $bd->commit();
    } catch (\Throwable $e) {
        if ($bd->inTransaction()) $bd->rollBack();
        fwrite(STDERR, "Demande #{$eb['id']} : " . $e->getMessage() . "\n");
    }
}
echo count($ids) . " demande(s) examinée(s), $modifiees statut(s) " . ($simuler ? "à modifier" : "modifié(s)") . ".\n";