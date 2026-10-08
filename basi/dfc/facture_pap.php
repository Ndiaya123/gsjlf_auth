<?php
/**
 * bon_pap_pdf.php
 * Route : /bon_pap_pdf
 *
 * Script autonome (session → BDD → FPDF → sortie), générant le PDF du bon
 * de commande (achat) ou de la facture de paiement (paiement) pour une
 * opération donnée, disponible pour idStatut = 4 (Acceptée), 6 (En
 * paiement / Livrée) ou 7 (Terminée).
 *
 * Design : sobre et élégant, quasi monochrome (encre + un seul accent
 * discret pour le titre et les filets), sans aplats colorés — même style
 * que le rapport d'inventaire et la demande de facture pro forma.
 * Colonnes du tableau séparées par des filets verticaux fins.
 *
 * Paramètre GET : token (chiffré de passer_achat_et_paiement.id)
 *
 * ⚠️ À vérifier :
 *   - TEMPLATE_PREMIERE_PAGE / TEMPLATE_PAGES_SUIVANTES : mêmes fichiers
 *     que le rapport d'inventaire et la demande de facture pro forma
 *     (gsjlf_finance.pdf / gsjlf_template_finance.pdf), à copier dans
 *     includes/fpdf/template/.
 *   - Chemins de bdBASI.php / fpdf.php / PDF_MC_Table.php : 2 niveaux,
 *     comme dfc-demande-facture-proforma.php.
 */

// ─── Session ────────────────────────────────────────────────────────────────
ob_start();
session_start();

$sessionOk = !empty($_SESSION['tmpIdBASI']) && !empty($_SESSION['tmpMatricule']);
if (!$sessionOk) {
    header('Location: /signin'); // ← ajuster selon la vraie route de connexion
    die;
}

// ─── Connexion BDD ──────────────────────────────────────────────────────────
include_once('../../bdBASI.php'); // ← même profondeur que dfc-demande-facture-proforma.php

$BDBASI = new BDBASI();
$bdBASI = $BDBASI->connect();
if (!$bdBASI) {
    http_response_code(500);
    die('Connexion base de données impossible.');
}

// ─── Déchiffrement du token (même clé que drhController::tokendecrypt) ─────
function tokendecryptPdf($data) {
    $key = hash('sha256', 'U@hbENTDRI@TCRI@T2022');
    $iv  = substr(hash('sha256', 'www.ent.uahb.sn'), 0, 16);
    return openssl_decrypt(base64_decode(strtr($data, '-_.', '+/=')), "AES-256-CBC", $key, 0, $iv);
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if ($token === '') {
    http_response_code(400);
    die('Token manquant.');
}
$idPAP = (int) tokendecryptPdf($token);
if ($idPAP <= 0) {
    http_response_code(400);
    die('Token invalide.');
}

// ─── FPDF (+ FPDI via PDF_MC_Table) ─────────────────────────────────────────
require('../../includes/fpdf/fpdf.php');
require('../../includes/fpdf/PDF_MC_Table.php');

// Gabarit institutionnel (fond de page) — mêmes fichiers que le rapport
// d'inventaire et la demande de facture pro forma.
// define('TEMPLATE_PREMIERE_PAGE',   __DIR__ . '/../../includes/fpdf/template/gsjlf_finance.pdf');
// define('TEMPLATE_PAGES_SUIVANTES', __DIR__ . '/../../includes/fpdf/template/gsjlf_template_finance.pdf');


// define('TEMPLATE_PREMIERE_PAGE',   __DIR__ . '/../../includes/fpdf/template/gsjlf_template.pdf');
// define('TEMPLATE_PAGES_SUIVANTES', __DIR__ . '/../../includes/fpdf/template/gsjlf_template.pdf');

define('TEMPLATE_PREMIERE_PAGE',   __DIR__ . '/../../includes/fpdf/template/gsjlf_template_2026_1.pdf');
define('TEMPLATE_PAGES_SUIVANTES', __DIR__ . '/../../includes/fpdf/template/gsjlf_template_2026_1.pdf');



// ── Palette sobre : encre + un seul accent, pas d'aplats de couleur ────────
define('ENCRE',       [31, 41, 55]);     // texte principal, quasi noir
define('ENCRE_DOUCE',  [107, 114, 128]); // texte secondaire / labels
define('ACCENT',      [15, 76, 58]);     // vert institutionnel, usage minimal
define('FILET',       [223, 226, 230]);  // lignes fines
define('FILET_FONCE', [180, 186, 192]);  // filet double sous l'en-tête tableau
define('ARGENT',      [214, 219, 224]);  // fond argenté discret de l'en-tête tableau

function decodeFpdf($value): string
{
    $value = (string) $value;
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
    }
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);
        if ($converted !== false) {
            return $converted;
        }
    }
    return $value;
}

function formatMontantPdf($n): string {
    if ($n === null || $n === '') return '-';
    return number_format((float)$n, 0, ',', ' ') . ' FCFA';
}

/**
 * Récupère toutes les données nécessaires au document : en-tête de
 * l'opération, acheteur, fournisseur retenu (achat), lignes (prix réel pour
 * l'achat, montant réel pour le paiement), et conditions de règlement /
 * modalités de paiement (+ tranches si modalité = 3).
 */
function recupererDonneesBon(PDO $bdBASI, int $idPAP): ?array {
    $stmt = $bdBASI->prepare("
        SELECT
            p.id AS idPAP, p.nom_commande, p.montant_total, p.idTypePAP, p.idStatut,
            p.nb_tranche,
            mr.mode_reglement AS mode_reglement_nom,
            mp.mode_paiement  AS mode_paiement_nom,
            CONCAT(u.prenom, ' ', u.nom) AS acheteur
        FROM passer_achat_et_paiement p
        LEFT JOIN mode_reglement mr ON p.id_mode_reglement = mr.id
        LEFT JOIN mode_paiement  mp ON p.id_mode_paiement  = mp.id
        LEFT JOIN utilisateurs   u  ON p.idUtilisateur     = u.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$idPAP]);
    $entete = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entete) return null;

    $estAchat = ((int)$entete['idTypePAP'] === 1);

    // Fournisseur retenu (achat uniquement — le paiement n'a pas de notion
    // de fournisseur dans ce module).
    $nomFournisseur = '-';
    if ($estAchat) {
        $stmtF = $bdBASI->prepare("
            SELECT f.nomF, f.prenomF, f.entreprise
            FROM documents_pap dp
            JOIN fournisseur f ON dp.id_fournisseur = f.idF
            WHERE dp.idPAP = ? AND dp.choix = 1
            LIMIT 1
        ");
        $stmtF->execute([$idPAP]);
        $f = $stmtF->fetch(PDO::FETCH_ASSOC);
        if ($f) {
            // ⚠️ Le tiret cadratin (—, U+2014) n'existe pas en ISO-8859-1 —
            // FPDF/decodeFpdf() l'aurait silencieusement remplacé par "?".
            // Utiliser un tiret simple.
            $nomFournisseur = trim(($f['prenomF'] ?? '') . ' ' . ($f['nomF'] ?? ''))
                . ($f['entreprise'] ? ' - ' . $f['entreprise'] : '');
        }
    }

    // Lignes : prix réel (achat) ou montant réel (paiement)
    $stmtL = $bdBASI->prepare("
        SELECT lb.designation, papl.quantite_reelle, papl.prix_reel, papl.montant_total_ligne
        FROM passer_achat_et_paiement_ligne papl
        JOIN demandes_ligne dal ON papl.idDL = dal.idDL
        JOIN ligneBudget    lb  ON dal.idLB  = lb.id
        WHERE papl.idPAP = ? AND papl.id_statut_PAPL = 1
        ORDER BY papl.id ASC
    ");
    $stmtL->execute([$idPAP]);
    $lignes = $stmtL->fetchAll(PDO::FETCH_ASSOC);

    // Tranches (si modalité = 3)
    $tranches = [];
    if ((int)$entete['nb_tranche'] > 0) {
        $stmtT = $bdBASI->prepare("SELECT ordre, pourcentage FROM tranches WHERE idPAP = ? AND statut = 1 ORDER BY ordre ASC");
        $stmtT->execute([$idPAP]);
        $tranches = $stmtT->fetchAll(PDO::FETCH_ASSOC);
    }

    return [
        'entete'          => $entete,
        'estAchat'        => $estAchat,
        'nomFournisseur'  => $nomFournisseur,
        'lignes'          => $lignes,
        'tranches'        => $tranches,
    ];
}

/* ═══════════════════════════════════════════════════════════════════════════
   CLASSE PDF — sobre, sur gabarit institutionnel (page 1 vs suivantes)
═══════════════════════════════════════════════════════════════════════════ */

class PdfBonPap extends PDF_MC_Table
{
    public string $titre         = '';
    public string $numero        = '';
    public string $nomCommande   = '';
    public string $acheteur      = '';
    public string $fournisseur   = '';
    public string $dateDoc       = '';
    public bool   $estAchat      = true;

    /** Largeurs de colonnes du tableau, selon le type (achat/paiement). */
    function largeursColonnes(): array
    {
        return $this->estAchat
            ? ['designation' => 120, 'qte' => 30, 'montant' => 40] // 190
            : ['designation' => 120, 'montant' => 70];             // 190
    }

    function Header()
    {
        $template = ($this->PageNo() === 1) ? TEMPLATE_PREMIERE_PAGE : TEMPLATE_PAGES_SUIVANTES;
        $this->setSourceFile($template);
        $tplIdx = $this->importPage(1);
        $this->useTemplate($tplIdx, 0, 0, 210, 297);

        if ($this->PageNo() === 1) {
            // Titre — texte seul, pas d'aplat, filet fin en dessous.
            [$r, $g, $b] = ACCENT;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', 'B', 17);
            $this->SetXY(10, 50);
            $this->Cell(190, 9, decodeFpdf($this->titre), 0, 1, 'L');

            [$r, $g, $b] = FILET_FONCE;
            $this->SetDrawColor($r, $g, $b);
            $this->SetLineWidth(0.5);
            $this->Line(10, 61, 200, 61);

            // Bloc d'identification — grille sobre, labels petites capitales.
            $y = 68;
            $this->blocChampBon('N° / NOM', '#' . $this->numero . ' - ' . $this->nomCommande, 10, $y);
            $this->blocChampBon('DATE', $this->dateDoc, 105, $y);
            $y += 11;
            $this->blocChampBon('ACHETEUR', $this->acheteur, 10, $y);
            $this->blocChampBon('FOURNISSEUR', $this->fournisseur, 105, $y);

            $this->SetY($y + 12);
        } else {
            [$r, $g, $b] = ENCRE_DOUCE;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', 'I', 8.5);
            $this->SetXY(10, 44);
            $this->Cell(190, 5, decodeFpdf('Bon #' . $this->numero . ' - suite'), 0, 1, 'R');
            $this->SetY(50);
        }

        $this->enteteTableauBon();
    }

    /** Petit bloc "label / valeur" sobre, sans fond. */
    function blocChampBon(string $label, string $valeur, float $x, float $y): void
    {
        [$r, $g, $b] = ENCRE_DOUCE;
        $this->SetTextColor($r, $g, $b);
        $this->SetFont('Helvetica', 'B', 6.8);
        $this->SetXY($x, $y);
        $this->Cell(90, 3.5, decodeFpdf($label), 0, 1);
        [$r, $g, $b] = ENCRE;
        $this->SetTextColor($r, $g, $b);
        $this->SetFont('Helvetica', '', 9.5);
        $this->SetXY($x, $y + 3.8);
        $this->Cell(90, 5, decodeFpdf($valeur), 0, 0);
    }

    /** En-tête de tableau : fond argenté discret + double filet + séparateurs verticaux. */
    function enteteTableauBon(): void
    {
        $L = $this->largeursColonnes();

        [$r, $g, $b] = ARGENT;
        $this->SetFillColor($r, $g, $b);
        $this->Rect(10, $this->GetY(), 190, 8, 'F');

        $yHaut = $this->GetY();

        [$r, $g, $b] = ENCRE;
        $this->SetTextColor($r, $g, $b);
        $this->SetFont('Helvetica', 'B', 7.3);
        $this->SetX(10);
        $libelleMontant = $this->estAchat ? 'PRIX REEL' : 'MONTANT';

        $this->Cell($L['designation'], 8, decodeFpdf('DESIGNATION'), 0, 0, 'L');
        if ($this->estAchat) {
            $this->Cell($L['qte'], 8, decodeFpdf('QTE'), 0, 0, 'C');
            $this->Cell($L['montant'], 8, decodeFpdf($libelleMontant), 0, 1, 'C');
        } else {
            $this->Cell($L['montant'], 8, decodeFpdf($libelleMontant), 0, 1, 'C');
        }

        $yBas = $this->GetY();

        // Séparateurs verticaux entre colonnes.
        [$r, $g, $b] = FILET_FONCE;
        $this->SetDrawColor($r, $g, $b);
        $this->SetLineWidth(0.3);
        $xSep = 10 + $L['designation'];
        $this->Line($xSep, $yHaut, $xSep, $yBas);
        if ($this->estAchat) {
            $xSep2 = $xSep + $L['qte'];
            $this->Line($xSep2, $yHaut, $xSep2, $yBas);
        }

        $this->SetLineWidth(0.4);
        $this->Line(10, $yBas, 200, $yBas);
        $this->Ln(1);
    }

    function Footer()
    {
        // Le gabarit contient déjà le pied de page institutionnel — rien à
        // ajouter ici.
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   GÉNÉRATION
═══════════════════════════════════════════════════════════════════════════ */

$donnees = recupererDonneesBon($bdBASI, $idPAP);
if (!$donnees) {
    http_response_code(404);
    die('Opération introuvable.');
}
if (!in_array((int)$donnees['entete']['idStatut'], [4, 6, 7], true)) {
    http_response_code(403);
    die("Le document n'est disponible que pour une opération Acceptée, En paiement/Livrée ou Terminée.");
}
if (!file_exists(TEMPLATE_PREMIERE_PAGE) || !file_exists(TEMPLATE_PAGES_SUIVANTES)) {
    http_response_code(500);
    die('Gabarit(s) PDF introuvable(s) (TEMPLATE_PREMIERE_PAGE / TEMPLATE_PAGES_SUIVANTES à ajuster).');
}

date_default_timezone_set('Africa/Dakar');

$entete   = $donnees['entete'];
$estAchat = $donnees['estAchat'];

$pdf = new PdfBonPap();
$pdf->titre        = $estAchat ? 'Bon de commande' : 'Facture de paiement';
$pdf->numero       = (string)$entete['idPAP'];
$pdf->nomCommande  = $entete['nom_commande'] ?? '';
$pdf->acheteur     = $entete['acheteur'] ?? '-';
$pdf->fournisseur  = $donnees['nomFournisseur'];
$pdf->dateDoc      = (new DateTime())->format('d/m/Y');
$pdf->estAchat     = $estAchat;
$pdf->SetAutoPageBreak(true, 30);
$pdf->AliasNbPages();
$pdf->AddPage();

$L = $pdf->largeursColonnes();

$pdf->SetFont('Helvetica', '', 8);
foreach ($donnees['lignes'] as $l) {
    if ($pdf->GetY() > 250) { $pdf->AddPage(); }

    $yHaut = $pdf->GetY();

    [$r, $g, $b] = ENCRE;
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetX(10);
    $pdf->Cell($L['designation'], 7.5, decodeFpdf($l['designation'] ?? ''), 0, 0, 'L');
    if ($estAchat) {
        $pdf->Cell($L['qte'], 7.5, decodeFpdf((string)($l['quantite_reelle'] ?? '-')), 0, 0, 'C');
        $pdf->Cell($L['montant'], 7.5, decodeFpdf(formatMontantPdf($l['prix_reel'] ?? null)), 0, 1, 'C');
    } else {
        $pdf->Cell($L['montant'], 7.5, decodeFpdf(formatMontantPdf($l['montant_total_ligne'] ?? null)), 0, 1, 'C');
    }

    $yBas = $pdf->GetY();

    // Séparateurs verticaux (mêmes positions que l'en-tête).
    [$r, $g, $b] = FILET;
    $pdf->SetDrawColor($r, $g, $b);
    $pdf->SetLineWidth(0.15);
    $xSep = 10 + $L['designation'];
    $pdf->Line($xSep, $yHaut, $xSep, $yBas);
    if ($estAchat) {
        $xSep2 = $xSep + $L['qte'];
        $pdf->Line($xSep2, $yHaut, $xSep2, $yBas);
    }
    $pdf->Line(10, $yBas, 200, $yBas);
}

// ── Montant total ───────────────────────────────────────────────────────────
[$r, $g, $b] = FILET_FONCE;
$pdf->SetDrawColor($r, $g, $b);
$pdf->SetLineWidth(0.5);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(3);

[$r, $g, $b] = ACCENT;
$pdf->SetTextColor($r, $g, $b);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetX(10);
$pdf->Cell(150, 8, decodeFpdf('MONTANT TOTAL'), 0, 0, 'R');
$pdf->Cell(40, 8, decodeFpdf(formatMontantPdf($entete['montant_total'])), 0, 1, 'R');

// ── Conditions de règlement + modalités de paiement (bas de page) ──────────
$pdf->Ln(8);

[$r, $g, $b] = FILET;
$pdf->SetDrawColor($r, $g, $b);
$pdf->SetLineWidth(0.2);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(4);

$pdf->blocChampBon('CONDITIONS DE REGLEMENT', $entete['mode_reglement_nom'] ?? '-', 10, $pdf->GetY());
$pdf->blocChampBon('MODALITE DE PAIEMENT', $entete['mode_paiement_nom'] ?? '-', 105, $pdf->GetY());
$pdf->Ln(11);

if (!empty($donnees['tranches'])) {
    [$r, $g, $b] = ENCRE_DOUCE;
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetFont('Helvetica', 'B', 6.8);
    $pdf->SetX(10);
    $pdf->Cell(190, 3.5, decodeFpdf('REPARTITION DES TRANCHES'), 0, 1);

    [$r, $g, $b] = ENCRE;
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetFont('Helvetica', '', 9.5);
    foreach ($donnees['tranches'] as $t) {
        $pdf->SetX(10);
        $pdf->Cell(190, 5, decodeFpdf('Tranche ' . $t['ordre'] . ' : ' . number_format((float)$t['pourcentage'], 2, ',', ' ') . ' %'), 0, 1);
    }
}

// ── Sortie inline dans le navigateur ────────────────────────────────────────
ob_end_clean();
$pdf->Output('I', 'bon_' . ($estAchat ? 'commande' : 'paiement') . '_' . $entete['idPAP'] . '.pdf');
exit;