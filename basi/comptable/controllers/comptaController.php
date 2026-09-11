<?php

include_once('../../../bdBASI.php');
session_start();





if (empty($_SESSION['tmpIdBASI'])) {
    echo "pasConnexion";
    die;
    // header("Location:/page-de-connexion");
    // die;
}

class comptaController extends BDBASI
{

    function tokenencrypt($data)
    {
        $secretKey = 'U@hbENTDRI@TCRI@T2022';
        $secretIv = 'www.ent.uahb.sn';
        $encryptMethod = "AES-256-CBC";
        $key = hash('sha256', $secretKey);
        $iv = substr(hash('sha256', $secretIv), 0, 16);
        $result = openssl_encrypt($data, $encryptMethod, $key, 0, $iv);
        return $result = base64_encode($result);
    }

    function tokendecrypt($data)
    {
        $secretKey = 'U@hbENTDRI@TCRI@T2022';
        $secretIv = 'www.ent.uahb.sn';
        $encryptMethod = "AES-256-CBC";
        $key = hash('sha256', $secretKey);
        $iv = substr(hash('sha256', $secretIv), 0, 16);
        $result = openssl_decrypt(base64_decode($data), $encryptMethod, $key, 0, $iv);
        return $result;
    }

    function fctRetirerAccents($varMaChaine)
    {
        $search = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'à', 'á', 'â', 'ã', 'ä', 'å', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ');
        $replace = array('A', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 'a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y');

        $varMaChaine = str_replace($search, $replace, $varMaChaine);
        return $varMaChaine;
    }

    public function numberFormat($number, $tmp1, $tmp2, $tmp3)
    {


        if ($number != NULL && $number != "") {
            return number_format($number, 0, ',', ' ');
        } else {
            return $number;
        }
    }








}


$BDBASI = new BDBASI();
$bdBASI = $BDBASI->connect();

$basiController = new comptaController();


function valid_donnees($donnees)
{
    $donnees = trim($donnees);
    $donnees = stripslashes($donnees);
    $donnees = htmlspecialchars($donnees);
    return $donnees;
}

$option = (!empty($_POST['option'])) ? $_POST['option'] : '';


switch ($option) {


// ─── CASE 1 : Lister les categories ───────────────────────────────────────────

    case 1:

        try {
            date_default_timezone_set('Africa/Dakar');
            $dateNow = new DateTime();
            $dateNow = $dateNow->format('Y-m-d');

            $stmt = $bdBASI->prepare("
            SELECT 
                categorie.id,
                categorie.nom_categorie,
                categorie.date_creation,
                utilisateurs.prenom,
                utilisateurs.nom,
                (
                    SELECT COUNT(*) 
                    FROM souscategorie sr 
                    WHERE sr.categorie_id = categorie.id 
                    AND sr.statut != 4
                ) AS subrub_count
            FROM categorie
            INNER JOIN utilisateurs 
                ON categorie.idUtilisateur = utilisateurs.id
            WHERE categorie.statut != 4
            ORDER BY categorie.id ASC
        ");
            $stmt->execute();
            $listes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $datacategorie = array();

            if (count($listes) != 0) {
                $numero = 0;
                foreach ($listes as $tmp) {
                    ++$numero;
                    $datacategorie[] = array(
                        'tmp' => $basiController->tokenencrypt($tmp["id"]),
                        'numero' => $numero,
                        'nom_categorie' => $tmp["nom_categorie"],
                        'date_creation' => date_format(date_create($tmp["date_creation"]), 'd/m/Y H:i:s'),
                        'createur' => ucfirst(mb_strtolower($tmp["prenom"])) . ' dfcController.php' . $basiController->fctRetirerAccents(mb_strtoupper($tmp["nom"])),
                        // ✅ CORRIGÉ : subrub_count était récupéré en SQL mais jamais inclus dans la réponse
                        'subrub_count' => (int)$tmp["subrub_count"]
                    );
                }
            }

            echo json_encode($datacategorie);
            die;

        } catch (\Throwable $th) {
            // ✅ CORRIGÉ : on ne renvoie pas les détails de l'exception au client en production
            error_log("Erreur case 1 : " . $th->getMessage());
            echo json_encode(array());
            die;
        }
        break;


// ─── CASE 2 : Ajouter une categorie ───────────────────────────────────────────

    case 2:

        date_default_timezone_set('Africa/Dakar');

        if (empty(trim($_POST["nom_categorie"] ?? ''))) {
            echo "obligatoire";
            die;
        }

        try {
            $bdBASI->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $bdBASI->beginTransaction();

            $nomr = trim($_POST["nom_categorie"]);

            if (!preg_match('/^[\p{L}\p{N}\s\'&-]+$/u', $nomr)) {
                $bdBASI->rollBack();
                echo "caratereSpeciaux";
                die;
            }

            // Vérifier si une categorie avec le même nom existe déjà
            $checkStmt = $bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE nom_categorie = :nomR AND statut != 4");
            $checkStmt->bindParam(':nomR', $nomr, PDO::PARAM_STR);
            $checkStmt->execute();
            $count = $checkStmt->fetchColumn();

            if ($count > 0) {
                $bdBASI->rollBack();
                echo "categorieExiste";
                die;
            }

            if (empty($_SESSION['tmpIdBASI'])) {
                $bdBASI->rollBack();
                echo "sessionExpired";
                die;
            }

            $idUtilisateur = $_SESSION['tmpIdBASI'];
            $statut = 1;

            $date_creation = (new DateTime())->format('Y-m-d H:i:s');

            $stmt = $bdBASI->prepare("INSERT INTO categorie (nom_categorie, statut, date_creation, idUtilisateur) VALUES (:nomR, :statut, :date_creation, :idUtilisateur)");
            $tmp_stmt = $stmt->execute([
                'nomR' => $nomr,
                'statut' => $statut,
                'date_creation' => $date_creation,
                'idUtilisateur' => $idUtilisateur
            ]);

            if ($tmp_stmt) {
                $id_categorie = $bdBASI->lastInsertId();

                $stmt_his = $bdBASI->prepare("INSERT INTO historique_categorie (id_categorie, nom_categorie, action, dateEnregistrement, idUtilisateur) VALUES (:id_categorie, :nomR, :action, :dateEnregistrement, :idUtilisateur)");
                $tmp_stmt_his = $stmt_his->execute([
                    'id_categorie' => $id_categorie,
                    'nomR' => $nomr,
                    'action' => 'Insertion',
                    'dateEnregistrement' => $date_creation,
                    'idUtilisateur' => $idUtilisateur
                ]);

                if ($tmp_stmt_his) {
                    $bdBASI->commit();
                    echo "succès";
                    die;
                } else {
                    $bdBASI->rollBack();
                    echo "erreur";
                    die;
                }
            } else {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

        } catch (\Throwable $th) {
            $bdBASI->rollBack();
            error_log("Erreur case 2 : " . $th->getMessage());
            echo "erreur";
            die;
        }
        break;


// ─── CASE 3 : Modifier une categorie ──────────────────────────────────────────

    case 3:

        date_default_timezone_set('Africa/Dakar');

        $id = $basiController->tokendecrypt($_POST["tmp"] ?? null);
        $nom = trim($_POST['nom_categorie_up'] ?? '');
        $original_nom = trim($_POST['original_nom'] ?? '');  // ✅ transmis depuis le JS

        $date_modification = (new DateTime())->format('Y-m-d H:i:s');

        try {
            $bdBASI->beginTransaction();

            if (empty($nom)) {
                $bdBASI->rollBack();
                echo "obligatoire";
                die;
            }

            if (!preg_match('/^[\p{L}\p{N}\s\'&-]+$/u', $nom)) {
                $bdBASI->rollBack();
                echo "caratereSpeciaux";
                die;
            }

            // ✅ CORRIGÉ : utilisation de fetchColumn() pour lire le COUNT, pas execute()
            $check = $bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE nom_categorie = ? AND id != ? AND statut != 4");
            $check->execute([$nom, $id ?? 0]);
            if ($check->fetchColumn() > 0) {
                $bdBASI->rollBack();
                echo "categorieExiste";
                die;
            }

            if (empty($id)) {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

            // Vérifier que la categorie existe
            $rub_check = $bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE id = ? AND statut != 4");
            $rub_check->execute([$id]);
            if ($rub_check->fetchColumn() != 1) {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

            // Vérifier les sous-categories liées si le nom change
            $subrub_check = $bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE categorie_id = ? AND statut != 4");
            $subrub_check->execute([$id]);
            $has_subcategories = $subrub_check->fetchColumn() > 0;

            if ($has_subcategories && $nom !== $original_nom) {
                $bdBASI->rollBack();
                echo "souscategorieExiste";
                die;
            }

            $stmt = $bdBASI->prepare("UPDATE categorie SET nom_categorie = ?, date_derniere_modification = ? WHERE id = ?");
            $result = $stmt->execute([$nom, $date_modification, $id]);

            if (!$result) {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

            if (empty($_SESSION['tmpIdBASI'])) {
                $bdBASI->rollBack();
                echo "sessionExpired";
                die;
            }

            $idUtilisateur = $_SESSION['tmpIdBASI'];

            $log_stmt = $bdBASI->prepare("INSERT INTO historique_categorie (id_categorie, nom_categorie, action, dateEnregistrement, idUtilisateur) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute([$id, $nom, 'Modification', $date_modification, $idUtilisateur]);

            $bdBASI->commit();
            echo "succès";
            die;

        } catch (Exception $e) {
            $bdBASI->rollBack();
            error_log("Erreur case 3 : " . $e->getMessage());
            echo "erreur";
            die;
        }
        break;


// ─── CASE 4 : Supprimer une categorie ─────────────────────────────────────────

    case 4:

        date_default_timezone_set('Africa/Dakar');

        $id = $basiController->tokendecrypt($_POST["e1"] ?? null);
        $nom = trim($_POST['e2'] ?? '');

        $date_suppression = (new DateTime())->format('Y-m-d H:i:s');

        try {
            $bdBASI->beginTransaction();

            if (empty($id)) {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

            // ✅ CORRIGÉ : vérifier que la categorie ciblée existe (et non les autres)
            $rub_check = $bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE id = ? AND statut != 4");
            $rub_check->execute([$id]);
            if ($rub_check->fetchColumn() != 1) {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

            // Vérifier les sous-categories liées
            $subrub_check = $bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE categorie_id = ? AND statut != 4");
            $subrub_check->execute([$id]);
            $has_subcategories = $subrub_check->fetchColumn() > 0;

            if ($has_subcategories) {
                $bdBASI->rollBack();
                echo "souscategorieExiste";
                die;
            }

            // Suppression logique : on passe le statut à 4
            $stmt = $bdBASI->prepare("UPDATE categorie SET statut = ?, date_derniere_modification = ? WHERE id = ?");
            $result = $stmt->execute([4, $date_suppression, $id]);

            if (!$result) {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

            if (empty($_SESSION['tmpIdBASI'])) {
                $bdBASI->rollBack();
                echo "sessionExpired";
                die;
            }

            $idUtilisateur = $_SESSION['tmpIdBASI'];

            $log_stmt = $bdBASI->prepare("INSERT INTO historique_categorie (id_categorie, nom_categorie, action, dateEnregistrement, idUtilisateur) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute([$id, $nom, 'Suppression', $date_suppression, $idUtilisateur]);

            $bdBASI->commit();
            echo "succès";
            die;

        } catch (Exception $e) {
            $bdBASI->rollBack();
            error_log("Erreur case 4 : " . $e->getMessage());
            echo "erreur";
            die;
        }
        break;

    case 5 :

        try {
            date_default_timezone_set('Africa/Dakar');
            $dateNow = new DateTime();
            $dateNow = $dateNow->format('Y-m-d');

            $stmt = $bdBASI->prepare("
   SELECT
    sr.id,
    sr.nom_sous_categorie,
    sr.date_creation,
    sr.categorie_id AS categorie,
    u.prenom,
    u.nom,
    ru.nom_categorie,
    COUNT(p.idP) AS subrub_count
FROM souscategorie sr
INNER JOIN utilisateurs u
    ON sr.idUtilisateur = u.id
INNER JOIN categorie ru
    ON sr.categorie_id = ru.id
LEFT JOIN product p
    ON p.id_Sous_categorie = sr.id
WHERE sr.statut <> 4
GROUP BY
    sr.id,
    sr.nom_sous_categorie,
    sr.date_creation,
    sr.categorie_id,
    u.prenom,
    u.nom,
    ru.nom_categorie
ORDER BY sr.id ASC;
        ");
            $stmt->execute();
            $listes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $datacategorie = array();

            if (count($listes) != 0) {
                $numero = 0;
                foreach ($listes as $tmp) {
                    ++$numero;
                    $datacategorie[] = array(
                        'tmp' => $basiController->tokenencrypt($tmp["id"]),
                        'numero' => $numero,
                        'nom_sous_categorie' => $tmp["nom_sous_categorie"],
                        'nom_categorie' => $tmp["nom_categorie"],
                        'categorie' => $tmp["categorie"],
                        'date_creation' => date_format(date_create($tmp["date_creation"]), 'd/m/Y H:i:s'),
                        'createur' => ucfirst(mb_strtolower($tmp["prenom"])) . ' dfcController.php' . $basiController->fctRetirerAccents(mb_strtoupper($tmp["nom"])),
                        // ✅ CORRIGÉ : subrub_count était récupéré en SQL mais jamais inclus dans la réponse
                        'subrub_count' => (int)$tmp["subrub_count"]
                    );
                }
            }

            echo json_encode($datacategorie);
            die;

        } catch (\Throwable $th) {
            // ✅ CORRIGÉ : on ne renvoie pas les détails de l'exception au client en production
            error_log("Erreur case 1 : " . $th->getMessage());
            echo json_encode(array());
            die;
        }
        break;

    case 6 :

        try {


            $data =
                [
                    'statut' => 4
                ];
            $stmt = $bdBASI->prepare("SELECT * FROM categorie WHERE  statut <> :statut");
            $stmt->execute($data);
            $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

            echo '<option></option><option value="" >Choisir...</option>';
//            echo '<option value="vide" hidden="">VIDE</option>';

            if (count($listes) > 0) {
                foreach ($listes as $tmp) {
                    echo '<option value="' . $tmp->id . '">' . $tmp->nom_categorie . '</option>';
                }
            } else {
                echo '<option value="">--categorie--</option>';
            }

            die;
        } catch (\Throwable $th) {
            echo "erreur";
            die;
        }
        break;

    case 7 :



        date_default_timezone_set('Africa/Dakar');

        if (empty(trim($_POST["nom_sous_categorie"] ?? '')) && empty(trim($_POST["categorie"] ?? ''))) {
            echo "obligatoire";
            die;
        }

        try {
            $bdBASI->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $bdBASI->beginTransaction();

            $nomr = trim($_POST["nom_sous_categorie"]);
            $categorie_id = trim($_POST["categorie"]);
            if (!preg_match('/^[\p{L}\p{N}\s\'&-]+$/u', $nomr)) {
                $bdBASI->rollBack();
                echo "caratereSpeciaux";
                die;
            }

            // Vérifier si une categorie avec le même nom existe déjà
            $checkStmt = $bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE nom_sous_categorie = :nomR AND categorie_id = :categorie_id  AND statut != 4");
            $checkStmt->bindParam(':nomR', $nomr, PDO::PARAM_STR);
            $checkStmt->bindParam(':categorie_id', $categorie_id, PDO::PARAM_INT);
            $checkStmt->execute();
            $count = $checkStmt->fetchColumn();

            if ($count > 0) {
                $bdBASI->rollBack();
                echo "categorieExiste";
                die;
            }

            // Vérifie que la categorie parente existe
            $rub_check = $bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE id = ? AND statut != 4");
            $rub_check->execute([$categorie_id]);
            if ($rub_check->fetchColumn() == 0) {
                $bdBASI->rollBack();

                echo "categorieParentNo";
                die;
//                echo json_encode([
//                    "success" => false,
//                    "message" => "categorie parente non trouvée ou inactive."
//                ]);
//                exit;
            }




            if (empty($_SESSION['tmpIdBASI'])) {
                $bdBASI->rollBack();
                echo "sessionExpired";
                die;
            }

            $idUtilisateur = $_SESSION['tmpIdBASI'];
            $statut = 1;

            $date_creation = (new DateTime())->format('Y-m-d H:i:s');

            $stmt = $bdBASI->prepare("INSERT INTO souscategorie (categorie_id ,nom_sous_categorie, statut, date_creation, idUtilisateur) VALUES (:categorie_id,:nomR, :statut, :date_creation, :idUtilisateur)");
            $tmp_stmt = $stmt->execute([
                'categorie_id' => $categorie_id,
                'nomR' => $nomr,
                'statut' => $statut,
                'date_creation' => $date_creation,
                'idUtilisateur' => $idUtilisateur
            ]);

            if ($tmp_stmt) {
                $id_sous_categorie = $bdBASI->lastInsertId();

                $stmt_his = $bdBASI->prepare("INSERT INTO historique_sous_categorie (id_sous_categorie,categorie_id, nom_sous_categorie, action, dateEnregistrement, idUtilisateur) VALUES (:id_sous_categorie,:categorie_id, :nomR, :action, :dateEnregistrement, :idUtilisateur)");
                $tmp_stmt_his = $stmt_his->execute([
                    'id_sous_categorie' => $id_sous_categorie,
                    'categorie_id' => $categorie_id,
                    'nomR' => $nomr,
                    'action' => 'Insertion',
                    'dateEnregistrement' => $date_creation,
                    'idUtilisateur' => $idUtilisateur
                ]);

                if ($tmp_stmt_his) {
                    $bdBASI->commit();
                    echo "succès";
                    die;
                } else {
                    $bdBASI->rollBack();
                    echo "erreur";
                    die;
                }
            } else {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

        } catch (\Throwable $th) {
            $bdBASI->rollBack();
            error_log("Erreur case 2 : " . $th->getMessage());
            echo "erreur".$th;
            die;
        }
        break;


    case 8 :


        date_default_timezone_set('Africa/Dakar');

        $id = $basiController->tokendecrypt($_POST["tmp"] ?? null);
        $categorie_id = trim($_POST['categorie_up'] ?? '');
        $nom = trim($_POST['nom_sous_categorie_up'] ?? '');
        $original_nom = trim($_POST['original_nom'] ?? '');  // ✅ transmis depuis le JS

        $date_modification = (new DateTime())->format('Y-m-d H:i:s');

        try {
            $bdBASI->beginTransaction();

            if (empty($nom)) {
                $bdBASI->rollBack();
                echo "obligatoire";
                die;
            }

            if (!preg_match('/^[\p{L}\p{N}\s\'&-]+$/u', $nom)) {
                $bdBASI->rollBack();
                echo "caratereSpeciaux";
                die;
            }

            // ✅ CORRIGÉ : utilisation de fetchColumn() pour lire le COUNT, pas execute()
            $check = $bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE nom_sous_categorie = ? AND categorie_id = ? AND  id != ? AND statut != 4");
            $check->execute([$nom,$categorie_id ?? 0, $id ?? 0]);
            if ($check->fetchColumn() > 0) {
                $bdBASI->rollBack();
                echo "categorieExiste";
                die;
            }

            if (empty($id)) {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

            // Vérifier que la categorie existe
            $rub_check = $bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE id = ? AND statut != 4");
            $rub_check->execute([$id]);
            if ($rub_check->fetchColumn() != 1) {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

            // Vérifier les sous-categories liées si le nom change
            $subrub_check = $bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE categorie_id = ? AND statut != 4");
            $subrub_check->execute([$id]);
            $has_subcategories = $subrub_check->fetchColumn() > 0;

            if ($has_subcategories && $nom !== $original_nom) {
                $bdBASI->rollBack();
                echo "souscategorieExiste";
                die;
            }


            // Vérifie que la categorie parente existe
            $rub_check = $bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE id = ? AND statut != 4");
            $rub_check->execute([$categorie_id]);
            if ($rub_check->fetchColumn() == 0) {
                $bdBASI->rollBack();

                echo "categorieParentNo";
                die;
            }


            // Vérifier les budget liées
            $subrub_check = $bdBASI->prepare("SELECT * FROM product WHERE product.id_Sous_categorie = ?");
            $subrub_check->execute([$id]);
            $has_subcategories = $subrub_check->fetchColumn() > 0;

            if ($has_subcategories) {
                $bdBASI->rollBack();
                echo "produitExiste";
                die;
            }

            $stmt = $bdBASI->prepare("UPDATE souscategorie SET nom_sous_categorie = ?,categorie_id =?, date_derniere_modification = ? WHERE id = ?");
            $result = $stmt->execute([$nom, $categorie_id,$date_modification, $id]);

            if (!$result) {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

            if (empty($_SESSION['tmpIdBASI'])) {
                $bdBASI->rollBack();
                echo "sessionExpired";
                die;
            }

            $idUtilisateur = $_SESSION['tmpIdBASI'];

            $log_stmt = $bdBASI->prepare("INSERT INTO historique_sous_categorie (id_sous_categorie,categorie_id, nom_sous_categorie, action, dateEnregistrement, idUtilisateur) VALUES (?,?, ?, ?, ?, ?)");
            $log_stmt->execute([$id,$categorie_id, $nom, 'Modification', $date_modification, $idUtilisateur]);

            $bdBASI->commit();
            echo "succès";
            die;

        } catch (Exception $e) {
            $bdBASI->rollBack();
            error_log("Erreur case 3 : " . $e->getMessage());
            echo "erreur".$e;
            die;
        }
        break;


    case 9:

        date_default_timezone_set('Africa/Dakar');

        $id = $basiController->tokendecrypt($_POST["e1"] ?? null);
        $nom = trim($_POST['e2'] ?? '');

        $date_suppression = (new DateTime())->format('Y-m-d H:i:s');

        try {
            $bdBASI->beginTransaction();

            if (empty($id)) {
                $bdBASI->rollBack();
                echo "erreur3";
                die;
            }



            // ✅ CORRIGÉ : vérifier que la categorie ciblée existe (et non les autres)
            $rub_check = $bdBASI->prepare("SELECT * FROM souscategorie WHERE id = ? AND statut != 4");
            $rub_check->execute([$id]);
            $result =  $rub_check->fetch(PDO::FETCH_OBJ);


            if (!$result) {
                $bdBASI->rollBack();
                echo "erreur2";
                die;
            }

            // Vérifier les budget liées
            $subrub_check = $bdBASI->prepare("SELECT * FROM product WHERE product.id_Sous_categorie = ?");
            $subrub_check->execute([$id]);
            $has_subcategories = $subrub_check->fetchColumn() > 0;

            if ($has_subcategories) {
                $bdBASI->rollBack();
                echo "souscategorieExiste";
                die;
            }

            // Suppression logique : on passe le statut à 4
            $stmt = $bdBASI->prepare("
    UPDATE souscategorie
    SET statut = ?, date_derniere_modification = ?
    WHERE id = ?
");

            $stmt->execute([4, $date_suppression, $id]);

            if ($stmt->rowCount() != 1) {
                $bdBASI->rollBack();
                echo "erreur";
                die;
            }

            if (empty($_SESSION['tmpIdBASI'])) {
                $bdBASI->rollBack();
                echo "sessionExpired";
                die;
            }

            $idUtilisateur = $_SESSION['tmpIdBASI'];

            $log_stmt = $bdBASI->prepare("INSERT INTO historique_sous_categorie (id_sous_categorie,categorie_id, nom_sous_categorie, action, dateEnregistrement, idUtilisateur) VALUES (?,?, ?, ?, ?, ?)");
            $log_stmt->execute([$id,$result->categorie_id, $nom, 'Suppression', $date_suppression, $idUtilisateur]);

            $bdBASI->commit();
            echo "succès";
            die;

        } catch (Exception $e) {
            $bdBASI->rollBack();
            error_log("Erreur case 4 : " . $e->getMessage());
            echo "erreur".$e;
            die;
        }
        break;




    default :

        echo "erreur";
        die;

}