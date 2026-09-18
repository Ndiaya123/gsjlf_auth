<?php
/**
 * dfc-demande-facture-proforma.php
 * Route : /dfc_demande_facture_proforma/
 *
 * Script autonome (session → BDD → FPDF → génération → sortie, tout en un
 * seul fichier, sans passer par drh_controller.php).
 *
 * Design : sobre et élégant, quasi monochrome (encre + un seul accent
 * discret pour le titre et les filets), sans aplats colorés — même style
 * que le rapport d'inventaire. Seule l'en-tête du tableau porte un fond
 * argenté discret.
 *
 * Au chargement :
 *   1. Récupère les demandes de facture pro forma actives (statut = 1),
 *      groupées par fournisseur.
 *   2. Génère un PDF par fournisseur sur le gabarit institutionnel GSJLF
 *      (Désignation / Prix vide / Délai de validité vide).
 *   3. Regroupe les PDF (normalement 3) dans une archive ZIP.
 *   4. Propose le ZIP en téléchargement automatique.
 *
 * ⚠️ À vérifier :
 *   - TEMPLATE_PREMIERE_PAGE / TEMPLATE_PAGES_SUIVANTES : mêmes fichiers
 *     que le rapport d'inventaire (gsjlf_finance.pdf / gsjlf_template_finance.pdf),
 *     à copier dans includes/fpdf/template/.
 *   - Chemins de bdBASI.php / fpdf.php / PDF_MC_Table.php : repris à
 *     l'identique de votre exemple fonctionnel (2 niveaux).
 */

// ─── Session ────────────────────────────────────────────────────────────────
ob_start();
session_start();

$sessionOk = !empty($_SESSION['tmpIdBASI']) && !empty($_SESSION['tmpIdDirection']) && !empty($_SESSION['tmpMatricule']);
if (!$sessionOk) {
    header('Location: /signin'); // ← ajuster selon la vraie route de connexion
    die;
}

// ─── Connexion BDD ──────────────────────────────────────────────────────────
include_once('../../bdBASI.php'); // ← même profondeur que votre exemple fonctionnel

$BDBASI = new BDBASI();
$bdBASI = $BDBASI->connect();
if (!$bdBASI) {
    http_response_code(500);
    die('Connexion base de données impossible.');
}

// ─── FPDF (+ FPDI via PDF_MC_Table, comme dans votre exemple) ──────────────
require('../../includes/fpdf/fpdf.php');
require('../../includes/fpdf/PDF_MC_Table.php');

// Gabarit institutionnel (fond de page) — mêmes fichiers que le rapport
// d'inventaire : page 1 sur gsjlf_finance.pdf, pages suivantes sur
// gsjlf_template_finance.pdf.
// define('TEMPLATE_PREMIERE_PAGE',   __DIR__ . '/../../includes/fpdf/template/gsjlf_finance.pdf');
// define('TEMPLATE_PAGES_SUIVANTES', __DIR__ . '/../../includes/fpdf/template/gsjlf_template_finance.pdf');

define('TEMPLATE_PREMIERE_PAGE',   __DIR__ . '/../../includes/fpdf/template/gsjlf_template.pdf');
define('TEMPLATE_PAGES_SUIVANTES', __DIR__ . '/../../includes/fpdf/template/gsjlf_template.pdf');


// ── Palette sobre : encre + un seul accent, pas d'aplats de couleur ────────
// (identique au rapport d'inventaire, pour une cohérence visuelle totale)
define('ENCRE',       [31, 41, 55]);     // texte principal, quasi noir
define('ENCRE_DOUCE',  [107, 114, 128]); // texte secondaire / labels
define('ACCENT',      [15, 76, 58]);     // vert institutionnel, usage minimal
define('FILET',       [223, 226, 230]);  // lignes fines
define('FILET_FONCE', [180, 186, 192]);  // filet double sous l'en-tête tableau
define('ARGENT',      [214, 219, 224]);  // fond argenté discret de l'en-tête tableau

/**
 * Convertit une chaîne UTF-8 en ISO-8859-1 pour FPDF, qui ne supporte pas
 * nativement l'UTF-8 avec les polices standard (Helvetica, Times, Courier).
 */
function decodeFpdf($s) {
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding((string)$s, 'ISO-8859-1', 'UTF-8');
    }
    if (function_exists('iconv')) {
        return iconv('UTF-8', 'ISO-8859-1//IGNORE', (string)$s);
    }
    return (string)$s;
}

/**
 * Nettoie une chaîne pour l'utiliser comme nom de fichier (translittère les
 * accents, remplace tout caractère non alphanumérique par "_").
 */
function nomFichierSain(string $s): string {
    $translitteré = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = $translitteré !== false ? $translitteré : $s;
    $s = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $s);
    $s = trim($s, '_');
    return $s !== '' ? $s : 'fournisseur';
}

/**
 * Récupère les demandes de facture pro forma actives (statut = 1), groupées
 * par fournisseur. La référence (dp.reference) est déjà générée et stockée
 * par demanderFactureProforma() côté contrôleur — elle est lue ici telle
 * quelle, pas régénérée, pour rester cohérente entre l'enregistrement et
 * le PDF (une référence stable même si on régénère le PDF plusieurs fois).
 */
function recupererGroupesProformaActifs(PDO $bdBASI): array {
    $stmt = $bdBASI->query("
        SELECT
            dp.idFournisseur,
            dp.reference,
            f.nomF, f.prenomF, f.entreprise,
            dal.idDL,
            lb.designation
        FROM demande_proforma dp
        JOIN fournisseur     f   ON dp.idFournisseur = f.idF
        JOIN demandes_ligne  dal ON dp.idDL          = dal.idDL
        JOIN ligneBudget     lb  ON dal.idLB          = lb.id
        WHERE dp.statut = 1
        ORDER BY dp.idFournisseur, dal.idDL
    ");

    $groupes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idF = (int)$row['idFournisseur'];
        if (!isset($groupes[$idF])) {
            $groupes[$idF] = [
                    'idFournisseur' => $idF,
                    'reference'     => $row['reference'],
                    'nom'           => trim(($row['prenomF'] ?? '') . ' ' . ($row['nomF'] ?? ''))
                            . ($row['entreprise'] ? ' - ' . $row['entreprise'] : ''),
                    'lignes'        => [],
            ];
        }
        $groupes[$idF]['lignes'][] = [
                'idDL'        => (int)$row['idDL'],
                'designation' => $row['designation'],
        ];
    }
    return array_values($groupes);
}

/* ═══════════════════════════════════════════════════════════════════════════
   CLASSE PDF — sobre, sur gabarit institutionnel (page 1 vs suivantes)
═══════════════════════════════════════════════════════════════════════════ */

class PdfProforma extends PDF_MC_Table
{
    public string $nomFournisseur = '';
    public string $reference      = '';
    public string $dateDoc        = '';

    function Header()
    {
        // Fond de page : gabarit institutionnel GSJLF — page 1 vs suivantes.
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
            $this->Cell(190, 9, decodeFpdf('Demande de facture pro forma'), 0, 1, 'L');

            [$r, $g, $b] = FILET_FONCE;
            $this->SetDrawColor($r, $g, $b);
            $this->SetLineWidth(0.5);
            $this->Line(10, 61, 200, 61);

            // Bloc d'identification — grille sobre, labels petites capitales.
            $y = 68;
            $this->blocChampPF('FOURNISSEUR', $this->nomFournisseur, 10, $y);
            $this->blocChampPF('REFERENCE', $this->reference, 105, $y);
            $y += 11;
            $this->blocChampPF('DATE', $this->dateDoc, 10, $y);

            $this->SetY($y + 12);
        } else {
            [$r, $g, $b] = ENCRE_DOUCE;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', 'I', 8.5);
            $this->SetXY(10, 44);
            $this->Cell(190, 5, decodeFpdf('Référence : ' . $this->reference . ' - suite'), 0, 1, 'R');
            $this->SetY(50);
        }

        $this->enteteTableauPF();
    }

    /** Petit bloc "label / valeur" sobre, sans fond. */
    function blocChampPF(string $label, string $valeur, float $x, float $y): void
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

    /** En-tête de tableau : fond argenté discret + double filet, texte sombre. */
    function enteteTableauPF(): void
    {
        [$r, $g, $b] = ARGENT;
        $this->SetFillColor($r, $g, $b);
        $this->Rect(10, $this->GetY(), 190, 8, 'F');

        $yHautEntete = $this->GetY();

        [$r, $g, $b] = ENCRE;
        $this->SetTextColor($r, $g, $b);
        $this->SetFont('Helvetica', 'B', 7.3);
        $this->SetX(10);
        $this->Cell(100, 8, decodeFpdf('DESIGNATION'), 0, 0, 'L');
        $this->Cell(45, 8, decodeFpdf('PRIX'), 0, 0, 'C');
        $this->Cell(45, 8, decodeFpdf('DELAI DE VALIDITE'), 0, 1, 'C');

        $yBasEntete = $this->GetY();

        // Séparateurs verticaux entre les 3 colonnes (largeurs : 100 / 45 / 45).
        [$r, $g, $b] = FILET_FONCE;
        $this->SetDrawColor($r, $g, $b);
        $this->SetLineWidth(0.3);
        $this->Line(110, $yHautEntete, 110, $yBasEntete);
        $this->Line(155, $yHautEntete, 155, $yBasEntete);

        $this->SetLineWidth(0.4);
        $this->Line(10, $yBasEntete, 200, $yBasEntete);
        $this->Ln(1);
    }

    function Footer()
    {
        // Le gabarit contient déjà le pied de page institutionnel (adresse,
        // téléphone, site web) — rien à ajouter ici.
    }
}

/**
 * Génère le PDF de demande de facture pro forma pour un fournisseur et
 * l'enregistre sur disque (mode 'F' de FPDF::Output()).
 */
function genererPdfFournisseur(array $groupe, string $reference, string $cheminFichier): void {
    date_default_timezone_set('Africa/Dakar');
    $dateDoc = (new DateTime())->format('d/m/Y');

    $pdf = new PdfProforma();
    $pdf->nomFournisseur = $groupe['nom'];
    $pdf->reference      = $reference;
    $pdf->dateDoc        = $dateDoc;
    $pdf->SetAutoPageBreak(true, 22); // réserve l'espace du pied de page institutionnel
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->SetFont('Helvetica', '', 8);
    foreach ($groupe['lignes'] as $l) {
        if ($pdf->GetY() > 262) { $pdf->AddPage(); }

        $yHautLigne = $pdf->GetY();

        [$r, $g, $b] = ENCRE;
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetX(10);
        $pdf->Cell(100, 7.5, decodeFpdf($l['designation'] ?? ''), 0, 0, 'L');
        $pdf->Cell(45, 7.5, '', 0, 0, 'C');   // Prix — à renseigner par le fournisseur
        $pdf->Cell(45, 7.5, '', 0, 1, 'C');   // Délai de validité — à renseigner par le fournisseur

        $yBasLigne = $pdf->GetY();

        // Séparateurs verticaux (mêmes positions que l'en-tête : 100 / 45 / 45).
        [$r, $g, $b] = FILET;
        $pdf->SetDrawColor($r, $g, $b);
        $pdf->SetLineWidth(0.15);
        $pdf->Line(110, $yHautLigne, 110, $yBasLigne);
        $pdf->Line(155, $yHautLigne, 155, $yBasLigne);
        $pdf->Line(10, $yBasLigne, 200, $yBasLigne);
    }

    // Filet de clôture du tableau, plus marqué.
    [$r, $g, $b] = FILET_FONCE;
    $pdf->SetDrawColor($r, $g, $b);
    $pdf->SetLineWidth(0.4);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());

    // Note explicative
    $pdf->Ln(6);
    [$r, $g, $b] = ENCRE_DOUCE;
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->SetX(10);
    $pdf->MultiCell(190, 5, decodeFpdf(
            "Merci de bien vouloir compléter les colonnes « Prix » et « Délai de validité » ci-dessus, " .
            "puis de nous retourner ce document signé et cacheté."
    ), 0, 'L');

    $pdf->Output($cheminFichier, 'F');
}

/* ═══════════════════════════════════════════════════════════════════════════
   ZIP — création avec ZipArchive, ou repli PHP pur si l'extension est absente
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Écrit une archive ZIP valide en PHP pur (sans ext-zip), en ne s'appuyant
 * que sur zlib (gzdeflate).
 */
function creerZipPurPhp(array $fichiers, string $cheminZipDestination): bool {
    $localHeaders     = '';
    $centralDirectory = '';
    $offset           = 0;
    $nbFichiers       = 0;

    foreach ($fichiers as $f) {
        $donnees = @file_get_contents($f['chemin']);
        if ($donnees === false) continue;

        $nom                = $f['nomZip'];
        $crc                = crc32($donnees);
        $tailleOriginale    = strlen($donnees);
        $donneesCompressees = gzdeflate($donnees, 9);
        $tailleCompressee   = strlen($donneesCompressees);

        $enteteLocal = pack(
                'VvvvvvVVVvv',
                0x04034b50, 20, 0, 8, 0, 0,
                $crc, $tailleCompressee, $tailleOriginale, strlen($nom), 0
        );
        $localHeaders .= $enteteLocal . $nom . $donneesCompressees;

        $entreeCentrale = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, 20, 20, 0, 8, 0, 0,
                $crc, $tailleCompressee, $tailleOriginale,
                strlen($nom), 0, 0, 0, 0, 0, $offset
        );
        $centralDirectory .= $entreeCentrale . $nom;

        $offset += strlen($enteteLocal) + strlen($nom) + $tailleCompressee;
        $nbFichiers++;
    }

    if ($nbFichiers === 0) return false;

    $finRepertoireCentral = pack(
            'VvvvvVVv',
            0x06054b50, 0, 0, $nbFichiers, $nbFichiers,
            strlen($centralDirectory), strlen($localHeaders), 0
    );

    return file_put_contents($cheminZipDestination, $localHeaders . $centralDirectory . $finRepertoireCentral) !== false;
}

function creerZip(array $fichiers, string $cheminZipDestination): bool {
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($cheminZipDestination, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($fichiers as $f) {
                $zip->addFile($f['chemin'], $f['nomZip']);
            }
            $zip->close();
            return true;
        }
    }
    return creerZipPurPhp($fichiers, $cheminZipDestination);
}

/* ═══════════════════════════════════════════════════════════════════════════
   TRAITEMENT PRINCIPAL
═══════════════════════════════════════════════════════════════════════════ */

$groupes = recupererGroupesProformaActifs($bdBASI);

if (empty($groupes)) {
    http_response_code(404);
    die('Aucune demande de facture pro forma active pour le moment.');
}

if (!file_exists(TEMPLATE_PREMIERE_PAGE) || !file_exists(TEMPLATE_PAGES_SUIVANTES)) {
    http_response_code(500);
    die('Gabarit(s) PDF introuvable(s) (TEMPLATE_PREMIERE_PAGE / TEMPLATE_PAGES_SUIVANTES à ajuster).');
}

$dossierTmp  = sys_get_temp_dir();
$horodatage  = date('Ymd_His');
$fichiersPdf = []; // [['chemin' => ..., 'nomZip' => ...], ...]

try {
    // ── Génération d'un PDF par fournisseur ────────────────────────────────
    foreach ($groupes as $g) {
        $reference = $g['reference'];
        $nomBase   = 'proforma_' . nomFichierSain($g['nom']) . '_' . $g['idFournisseur'];
        $chemin    = $dossierTmp . '/' . $nomBase . '_' . uniqid() . '.pdf';
        genererPdfFournisseur($g, $reference, $chemin);
        $fichiersPdf[] = ['chemin' => $chemin, 'nomZip' => $nomBase . '.pdf'];
    }

    // ── Archivage en ZIP ────────────────────────────────────────────────────
    $nomZipTelecharge = 'demande_facture_proforma_' . $horodatage . '.zip';
    $cheminZip        = $dossierTmp . '/' . $nomZipTelecharge . '_' . uniqid();

    if (!creerZip($fichiersPdf, $cheminZip)) {
        throw new \RuntimeException("Impossible de créer l'archive ZIP.");
    }

    // ── Téléchargement automatique du ZIP ───────────────────────────────────
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nomZipTelecharge . '"');
    header('Content-Length: ' . filesize($cheminZip));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($cheminZip);

    // ── Nettoyage des fichiers temporaires ──────────────────────────────────
    foreach ($fichiersPdf as $f) {
        @unlink($f['chemin']);
    }
    @unlink($cheminZip);

    ob_end_flush();
    exit;

} catch (\Throwable $e) {
    foreach ($fichiersPdf as $f) {
        @unlink($f['chemin']);
    }
    error_log('[ProForma][dfc-demande-facture-proforma] ' . $e->getMessage());
    http_response_code(500);
    die('Impossible de générer les factures pro forma.');
}