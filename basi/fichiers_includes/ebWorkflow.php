<?php
/**
 * ebWorkflow.php — logique PARTAGÉE du circuit "Expression de besoin"
 * (Fonctionnement) : bons de sortie, écarts, clôture avec solde et calcul
 * central des statuts.
 *
 * Inclus par les acteurs du circuit :
 *   - caisseController.php        (comptable : bon à chaque sortie, écarts, clôture du solde)
 *   - magasinierController.php    (magasinier : livre bon par bon)
 *   - personnelController.php     (demandeur : confirme / signale un écart bon par bon)
 *   - cronReceptionPresumee.php   (tâche planifiée : réception présumée)
 *
 * ⚠️ À placer À CÔTÉ de bdBASI.php (même dossier) : les contrôleurs l'incluent
 * avec le même préfixe relatif que bdBASI.php — include_once('../../../ebWorkflow.php').
 *
 * ── Principe ───────────────────────────────────────────────────────────────
 * Les QUANTITÉS sont la source de vérité ; les statuts en sont DÉDUITS par
 * ebw_recalculerStatutBon() / ebw_recalculerStatutEB(), au lieu de
 * transitions codées séparément dans chaque contrôleur. Les compteurs
 * cumulés de expression_besoin_produit (quantite_sortie / quantite_livree /
 * quantite_recue) restent maintenus en parallèle des lignes de bon (mêmes
 * transactions) : ils alimentent les écrans existants.
 *
 * ── Quantités restantes ────────────────────────────────────────────────────
 *   reste à sortir   = validée − annulée − Σ sorties            (par ligne de demande)
 *   reste à livrer   = sortie − livrée                          (par ligne de bon)
 *   reste à recevoir = livrée − reçue − écart − perdue          (par ligne de bon)
 *
 * ── Statuts de la demande (expression_besoin.idStatut) ─────────────────────
 *   1 Brouillon | 2 Soumise | 3 Validée | 4 Rejetée
 *   5 Sortie partielle : au moins une sortie, il reste des quantités à sortir
 *   6 Sortie totale    : plus rien à sortir, il reste à livrer
 *   7 Livrée           : tout ce qui est sorti est remis, reste à confirmer
 *                        (ou un écart est en attente de régularisation)
 *   8 Clôturée         : tout est reçu
 *   9 Clôturée avec solde : comme 8, mais une partie n'a jamais été sortie
 *                        (solde annulé par le comptable, avec motif)
 *  10 Annulée          : solde annulé avant toute sortie
 *
 * ── Statuts d'un bon (bon_sortie_eb.idStatut) ──────────────────────────────
 *   1 Sortie enregistrée | 2 Livraison partielle | 3 Livré
 *   4 Réception partielle | 5 Reçu
 *   6 Écart signalé (le demandeur déclare avoir reçu moins que "livré")
 *   7 Clos avec écart (écart régularisé : correction, retour en stock ou perte)
 */

const EBW_EPS = 0.001;

/** Statuts d'en-tête qui relèvent du circuit calculé (jamais 1, 2, 4). */
const EBW_STATUTS_CALCULES = [3, 5, 6, 7, 8, 9, 10];

/** Résolutions possibles d'un écart de réception. */
const EBW_RESOLUTIONS_ECART = ['correction_livraison', 'retour_stock', 'perte'];

/** Erreur métier : son message est destiné à l'utilisateur. */
class EbwException extends RuntimeException {}

/**
 * Crée l'en-tête d'un bon de sortie (une action de sortie du comptable) et
 * lui attribue son numéro BS-000123. Retourne l'id du bon.
 */
function ebw_creerBonSortie(PDO $bd, int $idEB, int $idUtilisateur, string $dateSortie, string $motif): int {
    $bd->prepare("
        INSERT INTO bon_sortie_eb (idEB, numero_bon, idStatut, idUtilisateurSortie, dateSortie)
        VALUES (?, '', 1, ?, ?)
    ")->execute([$idEB, $idUtilisateur, $dateSortie]);
    $idBS = (int) $bd->lastInsertId();

    $numero = 'BS-' . str_pad((string) $idBS, 6, '0', STR_PAD_LEFT);
    $bd->prepare("UPDATE bon_sortie_eb SET numero_bon = ? WHERE id = ?")->execute([$numero, $idBS]);

    ebw_historiserBon($bd, $idBS, $motif, $idUtilisateur, $dateSortie);
    return $idBS;
}

/** Ajoute une ligne au bon (quantité sortie du stock pour une ligne de demande). */
function ebw_ajouterLigneBon(PDO $bd, int $idBS, int $idEBP, int $idP, float $quantite, int $idUtilisateur, string $motif, string $date): int {
    $bd->prepare("
        INSERT INTO bon_sortie_eb_ligne (idBS, idEBP, idP, quantite_sortie, quantite_livree, quantite_recue)
        VALUES (?, ?, ?, ?, 0, 0)
    ")->execute([$idBS, $idEBP, $idP, $quantite]);
    $idBSL = (int) $bd->lastInsertId();
    ebw_historiserLigneBon($bd, $idBSL, $motif, $idUtilisateur, $date);
    return $idBSL;
}

/** Instantané de l'en-tête du bon (à chaque création / changement de statut). */
function ebw_historiserBon(PDO $bd, int $idBS, string $motif, int $idUtilisateur, string $date): void {
    $bd->prepare("
        INSERT INTO historique_bon_sortie_eb (idBS, idEB, numero_bon, idStatut, motif, idUtilisateur, dateEnregistrement)
        SELECT id, idEB, numero_bon, idStatut, ?, ?, ?
        FROM bon_sortie_eb
        WHERE id = ?
    ")->execute([$motif, $idUtilisateur, $date, $idBS]);
}

/** Instantané d'une ligne de bon (à chaque création / livraison / réception / écart). */
function ebw_historiserLigneBon(PDO $bd, int $idBSL, string $motif, int $idUtilisateur, string $date): void {
    $bd->prepare("
        INSERT INTO historique_bon_sortie_eb_ligne
            (idBSL, idBS, idEBP, idP, quantite_sortie, quantite_livree, quantite_recue, quantite_ecart, quantite_perdue, motif, idUtilisateur, dateEnregistrement)
        SELECT id, idBS, idEBP, idP, quantite_sortie, quantite_livree, quantite_recue, quantite_ecart, quantite_perdue, ?, ?, ?
        FROM bon_sortie_eb_ligne
        WHERE id = ?
    ")->execute([$motif, $idUtilisateur, $date, $idBSL]);
}

/** Instantané d'une ligne de demande (compteurs cumulés + quantité annulée). */
function ebw_historiserEBP(PDO $bd, int $idEBP, string $motif, int $idUtilisateur, string $date): void {
    $bd->prepare("
        INSERT INTO historique_expression_besoin_produit
            (idEBP, idEB, idP, quantite, quantite_reelle, quantite_sortie, quantite_livree, quantite_recue, quantite_annulee, statut, motif, dateEnregistrement, idUtilisateur)
        SELECT id, idEB, idP, quantite, quantite_reelle, quantite_sortie, quantite_livree, quantite_recue, quantite_annulee, statut, ?, ?, ?
        FROM expression_besoin_produit
        WHERE id = ?
    ")->execute([$motif, $date, $idUtilisateur, $idEBP]);
}

/**
 * Déduit le statut d'un bon de ses lignes et le met à jour s'il change.
 * Retourne le statut résultant.
 */
function ebw_recalculerStatutBon(PDO $bd, int $idBS, int $idUtilisateur, string $motif, string $date): int {
    $stmt = $bd->prepare("
        SELECT COALESCE(SUM(quantite_sortie), 0)  AS s,
               COALESCE(SUM(quantite_livree), 0)  AS l,
               COALESCE(SUM(quantite_recue), 0)   AS r,
               COALESCE(SUM(quantite_ecart), 0)   AS e,
               COALESCE(SUM(quantite_perdue), 0)  AS p
        FROM bon_sortie_eb_ligne WHERE idBS = ?
    ");
    $stmt->execute([$idBS]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    $s = (float) $t['s']; $l = (float) $t['l']; $r = (float) $t['r']; $e = (float) $t['e']; $p = (float) $t['p'];

    // Un écart a-t-il déjà été régularisé sur ce bon ? (détermine 5 Reçu / 7 Clos avec écart)
    $stmtR = $bd->prepare("SELECT COUNT(*) FROM ecart_bon_sortie_eb WHERE idBS = ? AND statut = 2");
    $stmtR->execute([$idBS]);
    $aRegularisation = ((int) $stmtR->fetchColumn() > 0) || $p > EBW_EPS;

    if     ($e > EBW_EPS)                                     $nouveau = 6; // Écart signalé
    elseif ($s <= EBW_EPS)                                    $nouveau = $aRegularisation ? 7 : 1; // bon vidé par un retour en stock
    elseif ($r + $p >= $s - EBW_EPS)                          $nouveau = $aRegularisation ? 7 : 5; // Reçu / Clos avec écart
    elseif ($r + $p > EBW_EPS)                                $nouveau = 4; // Réception partielle
    elseif ($l >= $s - EBW_EPS)                               $nouveau = 3; // Livré
    elseif ($l > EBW_EPS)                                     $nouveau = 2; // Livraison partielle
    else                                                      $nouveau = 1; // Sortie enregistrée

    $stmtC = $bd->prepare("SELECT idStatut FROM bon_sortie_eb WHERE id = ?");
    $stmtC->execute([$idBS]);
    $actuel = (int) $stmtC->fetchColumn();

    if ($nouveau !== $actuel) {
        $bd->prepare("UPDATE bon_sortie_eb SET idStatut = ? WHERE id = ?")->execute([$nouveau, $idBS]);
        ebw_historiserBon($bd, $idBS, $motif, $idUtilisateur, $date);
    }
    return $nouveau;
}

/**
 * Déduit le statut de la DEMANDE des quantités de ses lignes actives et le
 * met à jour (avec historique) s'il change. Ne touche jamais aux statuts
 * hors circuit (1 Brouillon, 2 Soumise, 4 Rejetée). Retourne le statut
 * résultant, ou null si la demande n'est pas dans le circuit calculé.
 */
function ebw_recalculerStatutEB(PDO $bd, int $idEB, string $motif, string $date): ?int {
    $stmtE = $bd->prepare("SELECT idStatut FROM expression_besoin WHERE id = ? LIMIT 1");
    $stmtE->execute([$idEB]);
    $actuel = $stmtE->fetchColumn();
    if ($actuel === false || !in_array((int) $actuel, EBW_STATUTS_CALCULES, true)) return null;
    $actuel = (int) $actuel;

    // Niveau demande : reste à sortir net du solde annulé.
    $stmt = $bd->prepare("
        SELECT COALESCE(SUM(quantite_sortie), 0)  AS total_sortie,
               COALESCE(SUM(quantite_annulee), 0) AS total_annulee,
               COALESCE(SUM(GREATEST(COALESCE(quantite_reelle, 0) - quantite_annulee, 0)), 0) AS net_valide,
               COALESCE(SUM(GREATEST(COALESCE(quantite_reelle, 0) - quantite_annulee - quantite_sortie, 0)), 0) AS reste_a_sortir
        FROM expression_besoin_produit
        WHERE idEB = ? AND statut = 1
    ");
    $stmt->execute([$idEB]);
    $q = $stmt->fetch(PDO::FETCH_ASSOC);

    // Niveau bons : reste à livrer / à recevoir / écarts ouverts.
    $stmtB = $bd->prepare("
        SELECT COALESCE(SUM(GREATEST(bl.quantite_sortie - bl.quantite_livree, 0)), 0) AS reste_a_livrer,
               COALESCE(SUM(GREATEST(bl.quantite_livree - bl.quantite_recue - bl.quantite_ecart - bl.quantite_perdue, 0)), 0) AS reste_a_recevoir,
               COALESCE(SUM(bl.quantite_ecart), 0) AS ecart_ouvert
        FROM bon_sortie_eb_ligne bl
        JOIN bon_sortie_eb bs ON bs.id = bl.idBS
        JOIN expression_besoin_produit ebp ON ebp.id = bl.idEBP AND ebp.statut = 1
        WHERE bs.idEB = ?
    ");
    $stmtB->execute([$idEB]);
    $b = $stmtB->fetch(PDO::FETCH_ASSOC);

    if ((float) $q['total_sortie'] <= EBW_EPS) {
        // Rien de sorti : Validée, ou Annulée si tout le solde a été renoncé.
        $nouveau = ((float) $q['net_valide'] <= EBW_EPS && (float) $q['total_annulee'] > EBW_EPS) ? 10 : 3;
    }
    elseif ((float) $q['reste_a_sortir']   > EBW_EPS) $nouveau = 5; // Sortie partielle
    elseif ((float) $b['reste_a_livrer']   > EBW_EPS) $nouveau = 6; // Sortie totale
    elseif ((float) $b['reste_a_recevoir'] > EBW_EPS || (float) $b['ecart_ouvert'] > EBW_EPS) $nouveau = 7; // Livrée
    else $nouveau = ((float) $q['total_annulee'] > EBW_EPS) ? 9 : 8; // Clôturée (avec solde ?)

    if ($nouveau !== $actuel) {
        $bd->prepare("UPDATE expression_besoin SET idStatut = ? WHERE id = ?")->execute([$nouveau, $idEB]);
        $bd->prepare("
            INSERT INTO historique_expression_besoin
                (idEB, nom_expression, idUtilisateur, idDirection, date_creation, idStatut, motif, dateEnregistrement)
            SELECT id, nom_expression, idUtilisateur, idDirection, date_creation, idStatut, ?, ?
            FROM expression_besoin
            WHERE id = ?
        ")->execute([$motif, $date, $idEB]);
    }
    return $nouveau;
}

/**
 * Le demandeur déclare n'avoir PAS reçu $quantite alors que le bon la marque
 * "livrée". Le plafond est le reste à recevoir de la ligne de bon. La
 * quantité passe en écart ouvert (le bon passe à 6 Écart signalé) jusqu'à
 * régularisation par le comptable. Retourne l'id de l'écart.
 */
function ebw_signalerEcart(PDO $bd, int $idBSL, float $quantite, string $commentaire, int $idUtilisateur, string $motif, string $date): int {
    $commentaire = trim($commentaire);
    if ($quantite <= EBW_EPS) throw new EbwException("La quantité d'écart doit être supérieure à zéro.");
    if ($commentaire === '')  throw new EbwException("Un commentaire est obligatoire pour signaler un écart de réception.");

    $stmt = $bd->prepare("
        SELECT bl.id, bl.idBS, bl.idEBP, bl.quantite_livree, bl.quantite_recue, bl.quantite_ecart, bl.quantite_perdue, bs.idEB
        FROM bon_sortie_eb_ligne bl JOIN bon_sortie_eb bs ON bs.id = bl.idBS
        WHERE bl.id = ? FOR UPDATE
    ");
    $stmt->execute([$idBSL]);
    $l = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$l) throw new EbwException("Ligne de bon introuvable.");

    $reste = (float) $l['quantite_livree'] - (float) $l['quantite_recue'] - (float) $l['quantite_ecart'] - (float) $l['quantite_perdue'];
    if ($quantite > $reste + EBW_EPS) throw new EbwException("L'écart déclaré dépasse ce qui restait à confirmer pour cette ligne.");

    $bd->prepare("UPDATE bon_sortie_eb_ligne SET quantite_ecart = quantite_ecart + ? WHERE id = ?")->execute([$quantite, $idBSL]);
    $bd->prepare("
        INSERT INTO ecart_bon_sortie_eb (idBSL, idBS, idEB, idEBP, quantite_ecart, commentaire, idUtilisateurSignale, dateSignalement, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ")->execute([$idBSL, $l['idBS'], $l['idEB'], $l['idEBP'], $quantite, $commentaire, $idUtilisateur, $date]);
    $idEcart = (int) $bd->lastInsertId();

    ebw_historiserLigneBon($bd, $idBSL, $motif, $idUtilisateur, $date);
    return $idEcart;
}

/**
 * Régularise un écart ouvert (comptable, garant du stock).
 *  - correction_livraison : la livraison avait été enregistrée en trop ; la
 *    quantité redevient "à livrer" (le magasinier doit la remettre).
 *  - retour_stock : les produits n'ont jamais quitté le magasin ; ils sont
 *    remis en stock et redeviennent "à sortir" (le comptable peut ressortir
 *    ou annuler le solde).
 *  - perte : les produits sont perdus ; l'écart est clos, le stock reste
 *    décrémenté.
 */
function ebw_regulariserEcart(PDO $bd, int $idEcart, string $resolution, string $commentaire, int $idUtilisateur, string $matricule, string $date): void {
    $commentaire = trim($commentaire);
    if (!in_array($resolution, EBW_RESOLUTIONS_ECART, true)) throw new EbwException("Résolution invalide.");
    if ($commentaire === '') throw new EbwException("Un commentaire est obligatoire pour régulariser un écart.");

    $stmt = $bd->prepare("SELECT * FROM ecart_bon_sortie_eb WHERE id = ? FOR UPDATE");
    $stmt->execute([$idEcart]);
    $ecart = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ecart || (int) $ecart['statut'] !== 1) throw new EbwException("Écart introuvable ou déjà régularisé.");

    $q = (float) $ecart['quantite_ecart'];
    $stmtL = $bd->prepare("SELECT * FROM bon_sortie_eb_ligne WHERE id = ? FOR UPDATE");
    $stmtL->execute([$ecart['idBSL']]);
    $bl = $stmtL->fetch(PDO::FETCH_ASSOC);
    if (!$bl || (float) $bl['quantite_ecart'] < $q - EBW_EPS) throw new EbwException("Incohérence : la ligne de bon ne porte plus cet écart.");

    $motif = "Écart de réception régularisé — {$resolution} (par $matricule)";

    if ($resolution === 'correction_livraison') {
        $bd->prepare("UPDATE bon_sortie_eb_ligne SET quantite_livree = quantite_livree - ?, quantite_ecart = quantite_ecart - ? WHERE id = ?")
            ->execute([$q, $q, $bl['id']]);
        $bd->prepare("UPDATE expression_besoin_produit SET quantite_livree = quantite_livree - ?, dateEnregistrement = ? WHERE id = ?")
            ->execute([$q, $date, $bl['idEBP']]);
    }
    elseif ($resolution === 'retour_stock') {
        $bd->prepare("UPDATE bon_sortie_eb_ligne SET quantite_sortie = quantite_sortie - ?, quantite_livree = quantite_livree - ?, quantite_ecart = quantite_ecart - ? WHERE id = ?")
            ->execute([$q, $q, $q, $bl['id']]);
        $bd->prepare("UPDATE expression_besoin_produit SET quantite_sortie = quantite_sortie - ?, quantite_livree = quantite_livree - ?, dateEnregistrement = ? WHERE id = ?")
            ->execute([$q, $q, $date, $bl['idEBP']]);
        $bd->prepare("UPDATE product SET Stock_actuel = Stock_actuel + ?, retrait = GREATEST(retrait - ?, 0) WHERE idP = ?")
            ->execute([$q, $q, $bl['idP']]);
        $bd->prepare("
            INSERT INTO historique_product
                (product_id, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, motif, dateEnregistrement)
            SELECT idP, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, ?, ?
            FROM product WHERE idP = ?
        ")->execute([$motif, $date, $bl['idP']]);
    }
    else { // perte
        $bd->prepare("UPDATE bon_sortie_eb_ligne SET quantite_ecart = quantite_ecart - ?, quantite_perdue = quantite_perdue + ? WHERE id = ?")
            ->execute([$q, $q, $bl['id']]);
    }

    $bd->prepare("
        UPDATE ecart_bon_sortie_eb
        SET statut = 2, resolution = ?, commentaireResolution = ?, idUtilisateurResolution = ?, dateResolution = ?
        WHERE id = ?
    ")->execute([$resolution, $commentaire, $idUtilisateur, $date, $idEcart]);

    ebw_historiserLigneBon($bd, (int) $bl['id'], $motif, $idUtilisateur, $date);
    ebw_historiserEBP($bd, (int) $bl['idEBP'], $motif, $idUtilisateur, $date);
    ebw_recalculerStatutBon($bd, (int) $bl['idBS'], $idUtilisateur, $motif, $date);
    ebw_recalculerStatutEB($bd, (int) $ecart['idEB'], $motif, $date);
}

/**
 * Clôture avec solde : le comptable renonce à sortir le reste (rupture
 * durable, besoin disparu). Le reste à sortir de chaque ligne passe en
 * quantite_annulee ; la demande évolue ensuite selon ce qui a déjà été
 * sorti (9 Clôturée avec solde une fois tout reçu, 10 Annulée si rien
 * n'avait été sorti). Motif obligatoire. Retourne [nouveauStatut, soldeTotal].
 */
function ebw_cloturerSolde(PDO $bd, int $idEB, string $commentaire, int $idUtilisateur, string $matricule, string $date): array {
    $commentaire = trim($commentaire);
    if ($commentaire === '') throw new EbwException("Un motif est obligatoire pour clôturer le solde d'une demande.");

    $stmtE = $bd->prepare("SELECT idStatut FROM expression_besoin WHERE id = ? FOR UPDATE");
    $stmtE->execute([$idEB]);
    $statut = $stmtE->fetchColumn();
    if ($statut === false || !in_array((int) $statut, [3, 5], true)) {
        throw new EbwException("Seule une demande Validée ou en Sortie partielle peut voir son solde clôturé.");
    }

    $stmtL = $bd->prepare("
        SELECT id, COALESCE(quantite_reelle, 0) AS reelle, quantite_sortie, quantite_annulee
        FROM expression_besoin_produit WHERE idEB = ? AND statut = 1 FOR UPDATE
    ");
    $stmtL->execute([$idEB]);

    $motif = "Solde annulé par le comptable : {$commentaire} (par $matricule)";
    $total = 0.0;
    foreach ($stmtL->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $solde = max(0.0, (float) $l['reelle'] - (float) $l['quantite_annulee'] - (float) $l['quantite_sortie']);
        if ($solde <= EBW_EPS) continue;
        $bd->prepare("UPDATE expression_besoin_produit SET quantite_annulee = quantite_annulee + ?, dateEnregistrement = ? WHERE id = ?")
            ->execute([$solde, $date, $l['id']]);
        ebw_historiserEBP($bd, (int) $l['id'], $motif, $idUtilisateur, $date);
        $total += $solde;
    }
    if ($total <= EBW_EPS) throw new EbwException("Il n'y a aucun solde à clôturer sur cette demande.");

    $nouveau = ebw_recalculerStatutEB($bd, $idEB, $motif, $date);
    return [$nouveau, $total];
}

/**
 * Réception présumée : toute quantité livrée depuis plus de $delaiJours
 * jours et jamais confirmée ni contestée est réputée reçue. Traitée par
 * demande, chacune dans sa propre transaction (une erreur n'en bloque pas
 * les autres). Le demandeur est l'acteur tracé, le motif précise
 * l'automatisme. Retourne le nombre de lignes de bon régularisées.
 */
function ebw_receptionPresumee(PDO $bd, int $delaiJours, string $date): int {
    $stmt = $bd->prepare("
        SELECT bl.id AS idBSL, bl.idBS, bl.idEBP, bs.idEB, eb.idUtilisateur AS idDemandeur,
               (bl.quantite_livree - bl.quantite_recue - bl.quantite_ecart - bl.quantite_perdue) AS reste
        FROM bon_sortie_eb_ligne bl
        JOIN bon_sortie_eb bs ON bs.id = bl.idBS
        JOIN expression_besoin eb ON eb.id = bs.idEB
        WHERE bl.date_derniere_livraison IS NOT NULL
          AND bl.date_derniere_livraison <= DATE_SUB(?, INTERVAL ? DAY)
          AND (bl.quantite_livree - bl.quantite_recue - bl.quantite_ecart - bl.quantite_perdue) > ?
        ORDER BY bs.idEB, bl.id
    ");
    $stmt->execute([$date, $delaiJours, EBW_EPS]);
    $parDemande = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $parDemande[(int) $r['idEB']][] = $r;

    $motif = "Réception présumée automatiquement (aucune confirmation ni contestation sous $delaiJours jours)";
    $nb = 0;
    foreach ($parDemande as $idEB => $lignes) {
        try {
            $bd->beginTransaction();
            $bons = [];
            foreach ($lignes as $r) {
                $reste = (float) $r['reste'];
                $acteur = (int) $r['idDemandeur'];
                $bd->prepare("UPDATE bon_sortie_eb_ligne SET quantite_recue = quantite_recue + ? WHERE id = ?")->execute([$reste, $r['idBSL']]);
                $bd->prepare("UPDATE expression_besoin_produit SET quantite_recue = quantite_recue + ?, dateEnregistrement = ? WHERE id = ?")->execute([$reste, $date, $r['idEBP']]);
                ebw_historiserLigneBon($bd, (int) $r['idBSL'], $motif, $acteur, $date);
                ebw_historiserEBP($bd, (int) $r['idEBP'], $motif, $acteur, $date);
                $bons[(int) $r['idBS']] = $acteur;
                $nb++;
            }
            foreach ($bons as $idBS => $acteur) ebw_recalculerStatutBon($bd, $idBS, $acteur, $motif, $date);
            ebw_recalculerStatutEB($bd, $idEB, $motif, $date);
            $bd->commit();
        } catch (\Throwable $e) {
            if ($bd->inTransaction()) $bd->rollBack();
            error_log("[ebWorkflow][receptionPresumee] demande $idEB : " . $e->getMessage());
        }
    }
    return $nb;
}