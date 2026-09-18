<?php
/**
 * arrete-caisse-pdf.php
 * Script autonome (comme bon_pap_pdf.php / dfc-demande-facture-proforma.php) :
 * aucune requête AJAX, aucun JS. Ce script est directement appelé en
 * navigation (lien <a> ou <iframe src>) et produit le PDF en réponse HTTP.
 *
 * Comportement :
 *   - Si l'arrêt de caisse du jour existe déjà pour ce caissier : affiche le
 *     PDF (régénéré à l'identique à partir des mêmes données, AUCUNE
 *     nouvelle insertion en base — cf. point 9 de la demande).
 *   - Sinon, s'il existe au moins un paiement Liquide/Wave/Orange Money
 *     aujourd'hui pour ce caissier : enregistre la ligne `arrete_caisse`,
 *     PUIS enregistre une copie du PDF sur le serveur, PUIS affiche le PDF.
 *   - Sinon (rien à clôturer) : redirige vers une page d'erreur.
 *
 * ⚠️ À vérifier / ajuster :
 *   - Chemins de bdBASI.php / fpdf.php / PDF_MC_Table.php (2 niveaux, comme
 *     bon_pap_pdf.php).
 *   - Dossier de sauvegarde des copies PDF (RAPPORT_DIR ci-dessous).
 *   - Le PDF est construit sans gabarit externe (pas de finance.pdf connu
 *     dans ce projet) — à remplacer par un import de template si un fichier
 *     équivalent existe dans /includes/fpdf/template/.
 */

ob_start();
session_start();
if (empty($_SESSION['tmpIdBASI']) || empty($_SESSION['tmpMatricule'])) {
    header('Location:/personnel/page-de-connexion');
    exit;
}
include_once('../../bdBASI.php'); // ← ajuster selon la profondeur réelle du fichier
ob_end_clean();

require('../../includes/fpdf/fpdf.php');
require('../../includes/fpdf/PDF_MC_Table.php');

define('RAPPORT_DIR', __DIR__ . '/../../documents/commandes/rapports_arrete_caisse'); // ← ajuster

$idCaissier   = (int) $_SESSION['tmpIdBASI'];
$matricule    = trim($_SESSION['tmpMatricule']);

date_default_timezone_set('Africa/Dakar');
$dateJour = date('Y-m-d');

try {
    $BDBASI = new BDBASI();
    $bdBASI = $BDBASI->connect();
} catch (\Throwable $e) {
    error_log('[ArreteCaissePdf][Connexion] ' . $e->getMessage());
    header('Location:/personnel/erreur');
    exit;
}

/**
 * Vrai si un arrêt de caisse existe déjà pour ce caissier aujourd'hui.
 */
function arreteCaisseDejaEffectue(PDO $bdBASI, int $idCaissier, string $dateJour): bool {
    $stmt = $bdBASI->prepare("SELECT id FROM alimentation_arrete_caisse WHERE idCaissier = ? AND date_alimentation = ? LIMIT 1");
    $stmt->execute([$idCaissier, $dateJour]);
    return (bool) $stmt->fetch();
}

/**
 * Liste des paiements Liquide/Wave/Orange Money du jour pour ce caissier.
 */
function listerPaiementsJour(PDO $bdBASI, int $idCaissier, string $dateJour): array {
    $stmt = $bdBASI->prepare("
        SELECT pp.id, pp.montant, pp.mode_reglement, pp.date_paiement,
               p.nom_commande as numero, p.nom_commande
        FROM paiement_pap pp
        JOIN passer_achat_et_paiement p ON pp.idPAP = p.id
        WHERE pp.idCaissier = ? AND DATE(pp.date_paiement) = ? AND pp.mode_reglement IN (1, 4, 5)
        ORDER BY pp.date_paiement ASC
    ");
    $stmt->execute([$idCaissier, $dateJour]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sommePaiements(array $paiements): float {
    $somme = 0.0;
    foreach ($paiements as $p) $somme += (float) $p['montant'];
    return $somme;
}

function nomCaissier(PDO $bdBASI, int $idCaissier): array {
    $stmt = $bdBASI->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ? LIMIT 1");
    $stmt->execute([$idCaissier]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['prenom' => '', 'nom' => ''];
    return [$u['prenom'] ?? '', $u['nom'] ?? ''];
}

/* ═══════════════════════════════════════════════════════════════════════════
   GÉNÉRATION DU PDF (FPDF — sans gabarit externe)
═══════════════════════════════════════════════════════════════════════════ */

const LIBELLES_MODE_PDF = [1 => 'Liquide', 4 => 'Wave', 5 => 'Orange Money'];
// Couleurs par mode (RGB), pour les pastilles dans le tableau.
const COULEURS_MODE_PDF = [
    1 => [5, 150, 105],   // Liquide  — vert émeraude
    4 => [37, 99, 235],   // Wave     — bleu
    5 => [234, 88, 12],   // Orange Money — orange
];

// Palette de marque (cohérente avec le reste de l'application).
const VERT_FONCE  = [6, 78, 59];
const VERT_MOYEN  = [26, 122, 94];
const VERT_CLAIR  = [236, 253, 245];
const GRIS_TEXTE  = [55, 65, 81];
const GRIS_CLAIR  = [249, 250, 251];

class PdfArreteCaisse extends PDF_MC_Table
{
    function Footer() {
        $this->SetY(-18);
        $this->SetDrawColor(...VERT_MOYEN);
        $this->SetLineWidth(0.4);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->SetY(-15);
        $this->SetX(10);
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 8, "CRIAT — Centre des Ressources Informatiques et d'Appui Technique", 0, 0, 'L');
        $this->SetX(180);
        $this->Cell(0, 8, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
        $this->AliasNbPages();
    }

    // Rectangle aux coins arrondis (recette FPDF classique, domaine public —
    // même implémentation que bon_pap_pdf.php).
    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k; $hp = $this->h;
        $op = ($style === 'F') ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 'S');
        $arc = 4 / 3 * (sqrt(2) - 1);

        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc + $r * $arc, $yc - $r, $xc + $r, $yc - $r * $arc, $xc + $r, $yc);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $arc, $xc + $r * $arc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $arc, $yc + $r, $xc - $r, $yc + $r * $arc, $xc - $r, $yc);
        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $arc, $xc - $r * $arc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1 * $this->k, ($h - $y1) * $this->k, $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
    }
}

/**
 * Construit le PDF d'arrêt de caisse à partir des données fournies, et le
 * renvoie sans l'envoyer (l'appelant choisit 'I' = affichage ou 'F' =
 * enregistrement fichier, via FPDF::Output()).
 */
function construirePdfArreteCaisse(array $paiements, float $somme, string $prenom, string $nom, string $matricule, string $dateJour): FPDF {
    setlocale(LC_ALL, 'fr_FR.UTF8', 'fr_FR', 'fr', 'fra');

    $pdf = new PdfArreteCaisse();
    $pdf->AddPage();
    $pageWidth = 190; // zone utile (210mm A4 - marges 10mm x2)

    // ── Bandeau d'en-tête coloré ─────────────────────────────────────────
    $pdf->SetFillColor(...VERT_FONCE);
    $pdf->Rect(0, 0, 210, 34, 'F');
    $pdf->SetXY(10, 8);
    $pdf->SetFont('Helvetica', 'B', 18);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($pageWidth, 9, utf8_decode("Arrêt de caisse"), 0, 1, 'C');
    $pdf->SetX(10);
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetTextColor(220, 240, 230);
    $pdf->Cell($pageWidth, 7, utf8_decode('Journée du ' . mb_strtoupper(strftime('%d %B %Y', strtotime($dateJour)))), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetY(40);

    // ── Carte "Caissier" ─────────────────────────────────────────────────
    $pdf->SetFillColor(...VERT_CLAIR);
    $pdf->SetDrawColor(209, 250, 229);
    $pdf->RoundedRect(10, $pdf->GetY(), $pageWidth, 22, 3, 'DF');
    $pdf->SetXY(15, $pdf->GetY() + 4);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(...VERT_FONCE);
    $pdf->Cell(60, 5, utf8_decode('CAISSIER'), 0, 0);
    $pdf->Cell(60, 5, utf8_decode('MATRICULE'), 0, 0);
    $pdf->Cell(60, 5, utf8_decode('GÉNÉRÉ LE'), 0, 1);
    $pdf->SetX(15);
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetTextColor(...GRIS_TEXTE);
    $pdf->Cell(60, 6, utf8_decode(ucwords(strtolower($prenom)) . ' ' . strtoupper($nom)), 0, 0);
    $pdf->Cell(60, 6, utf8_decode($matricule), 0, 0);
    $pdf->Cell(60, 6, utf8_decode(date('d/m/Y à H:i')), 0, 1);
    $pdf->Ln(10);

    // ── Tableau des paiements ────────────────────────────────────────────
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(...VERT_MOYEN);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(...VERT_MOYEN);
    $pdf->Cell(10, 9, utf8_decode('N°'), 1, 0, 'C', true);
    $pdf->Cell(35, 9, utf8_decode('N° commande'), 1, 0, 'C', true);
    $pdf->Cell(65, 9, utf8_decode('Nom'), 1, 0, 'C', true);
    $pdf->Cell(45, 9, utf8_decode('Moyen de paiement'), 1, 0, 'C', true);
    $pdf->Cell(35, 9, utf8_decode('Montant'), 1, 1, 'C', true);

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetDrawColor(230, 230, 230);
    if (count($paiements)) {
        $i = 1;
        foreach ($paiements as $p) {
            $fond = ($i % 2 === 0) ? GRIS_CLAIR : [255, 255, 255];
            $pdf->SetFillColor(...$fond);
            $pdf->SetTextColor(...GRIS_TEXTE);

            $pdf->Cell(10, 8, (string) $i, 1, 0, 'C', true);
            $pdf->Cell(35, 8, utf8_decode((string) ($p['numero'] ?? $p['id'])), 1, 0, 'L', true);
            $pdf->Cell(65, 8, utf8_decode(substr((string) ($p['nom_commande'] ?? ''), 0, 38)), 1, 0, 'L', true);

            // Pastille colorée pour le mode de règlement (petit carré, Rect
            // natif FPDF — Ellipse() n'existe pas dans FPDF standard).
            $couleurMode = COULEURS_MODE_PDF[(int) $p['mode_reglement']] ?? [107, 114, 128];
            $xModeDebut = $pdf->GetX();
            $yModeDebut = $pdf->GetY();
            $pdf->Cell(45, 8, '', 1, 0, 'L', true);
            $pdf->SetFillColor(...$couleurMode);
            $pdf->Rect($xModeDebut + 3, $yModeDebut + 3.2, 2.4, 2.4, 'F');
            $pdf->SetXY($xModeDebut + 7, $yModeDebut + 1.5);
            $pdf->SetTextColor(...GRIS_TEXTE);
            $pdf->Cell(36, 5, utf8_decode(LIBELLES_MODE_PDF[(int) $p['mode_reglement']] ?? ''), 0, 0, 'L');
            $pdf->SetXY($xModeDebut + 45, $yModeDebut);

            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(...VERT_MOYEN);
            $pdf->Cell(35, 8, number_format((float) $p['montant'], 0, ',', ' ') . ' F', 1, 1, 'R', true);
            $pdf->SetFont('Helvetica', '', 9);
            $i++;
        }
    } else {
        $pdf->SetFillColor(...GRIS_CLAIR);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetFont('Helvetica', 'I', 11);
        $pdf->Cell($pageWidth, 14, utf8_decode('Aucun paiement enregistré pour cette journée'), 1, 1, 'C', true);
    }

    // ── Bandeau total ────────────────────────────────────────────────────
    $pdf->Ln(3);
    $pdf->SetFillColor(...VERT_FONCE);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->RoundedRect(10, $pdf->GetY(), $pageWidth, 14, 2, 'F');
    $pdf->SetXY(15, $pdf->GetY() + 4);
    $pdf->Cell(90, 6, utf8_decode('MONTANT TOTAL'), 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->Cell(85, 6, number_format($somme, 0, ',', ' ') . ' F CFA', 0, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(12);

    // ── Pied : nombre de paiements + signature ──────────────────────────
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(...GRIS_TEXTE);
    $pdf->Cell(95, 6.5, utf8_decode('Nombre de paiements : ' . count($paiements)), 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(95, 6.5, utf8_decode('Fait, le ' . date('d-m-Y à H:i')), 0, 1, 'R');

    $pdf->Ln(14);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line(140, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(2);
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(130, 6, '', 0, 0);
    $pdf->Cell(60, 6, utf8_decode('Signature du caissier'), 0, 1, 'C');

    return $pdf;
}

/* ═══════════════════════════════════════════════════════════════════════════
   LOGIQUE PRINCIPALE (identique au modèle fourni)
═══════════════════════════════════════════════════════════════════════════ */

[$prenom, $nom] = nomCaissier($bdBASI, $idCaissier);
$nomFichierRapport = 'arrete_caisse_du_' . $dateJour . '_' . strtolower($prenom) . '_' . strtoupper($nom) . '.pdf';

if (arreteCaisseDejaEffectue($bdBASI, $idCaissier, $dateJour)) {
    // Déjà effectué aujourd'hui : consultation uniquement, aucune nouvelle
    // insertion — on régénère le même PDF à partir des mêmes données pour
    // l'afficher (rien n'est réécrit en base ni sur le disque).
    $paiements = listerPaiementsJour($bdBASI, $idCaissier, $dateJour);
    $somme = sommePaiements($paiements);
    $pdf = construirePdfArreteCaisse($paiements, $somme, $prenom, $nom, $matricule, $dateJour);
    $pdf->Output('I', $nomFichierRapport);
    exit;
}

$paiements = listerPaiementsJour($bdBASI, $idCaissier, $dateJour);

if (count($paiements) === 0) {
    // Rien à clôturer aujourd'hui.
    header('Location:/personnel/erreur');
    exit;
}

$somme = sommePaiements($paiements);
$dateEnregistrement = date('Y-m-d H:i:s');

try {
    $bdBASI->beginTransaction();

    // Champs conformes à la demande : Surplus/Perte/IdComptable/
    // DateConsolidation/IdBrouillardCaisse restent NULL, Statut = 0.
    $bdBASI->prepare("
        INSERT INTO alimentation_arrete_caisse
            (montant, surplus, perte, date_alimentation, idCaissier, statut, idComptable, dateConsolidation, idBrouillardCaisse, dateEnregistrement)
        VALUES (?, NULL, NULL, ?, ?, 0, NULL, NULL, NULL, ?)
    ")->execute([$somme, $dateJour, $idCaissier, $dateEnregistrement]);

    $bdBASI->commit();
} catch (\Throwable $e) {
    if ($bdBASI->inTransaction()) $bdBASI->rollBack();
    error_log('[ArreteCaissePdf] ' . $e->getMessage());
    header('Location:/personnel/erreur');
    exit;
}

// Une fois l'enregistrement réussi : copie sauvegardée sur le serveur, PUIS
// affichage du PDF (point 8 et exigence "enregistrer avant d'afficher").
if (!is_dir(RAPPORT_DIR)) mkdir(RAPPORT_DIR, 0755, true);
$pdfCopie = construirePdfArreteCaisse($paiements, $somme, $prenom, $nom, $matricule, $dateJour);
$pdfCopie->Output('F', RAPPORT_DIR . '/' . $nomFichierRapport);

$pdfAffiche = construirePdfArreteCaisse($paiements, $somme, $prenom, $nom, $matricule, $dateJour);
$pdfAffiche->Output('I', $nomFichierRapport);