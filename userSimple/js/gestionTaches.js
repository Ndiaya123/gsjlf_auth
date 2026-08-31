/* =============================================================================
 *  GESTION DES TÂCHES — écran d'affectation / retrait
 *  Remplace intégralement le bloc $(document).ready(...) actuel.
 *
 *  Colonne gauche  #tache3 : tâches détenues par la cible
 *  Colonne droite  #tache2 : tâches affectables (périmètre du chef connecté)
 *  Flèche gauche   .attribution : droite -> gauche  = AFFECTER
 *  Flèche droite   .restriction : gauche -> droite  = RETIRER
 * ========================================================================== */

(function ($) {
    'use strict';

    var URL_CTRL = '/personnel/user-controller';

    // Cible courante : { identifiant, libelle, source: 'agent' | 'directeur' }
    var cible = null;

    // Deux sélections distinctes : on n'affecte et on ne retire jamais
    // dans le même geste (bug de l'ancien selectedTachesIds partagé).
    var selDisponibles = new Set();
    var selDetenues    = new Set();

    // Données brutes des deux colonnes + filtre de sous-menu actif
    var lstDisponibles = [];
    var lstDetenues    = [];
    var filtreSousMenu = null;

    var enCours = false;   // verrou anti double-clic


    /* ---------------------------------------------------------------------
     *  Utilitaires
     * ------------------------------------------------------------------ */

    function alerte(icon, titre, texte) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: icon, title: titre, text: texte || '' });
        } else {
            window.alert(titre + (texte ? '\n' + texte : ''));
        }
    }

    function sessionPerdue() {
        alerte('warning', 'Session expirée', 'Veuillez vous reconnecter.');
        setTimeout(function () { window.location.href = '/personnel/signin'; }, 1800);
    }

    // Réponse texte des case 2 / 3 : soit du HTML <option>, soit un mot-clé.
    function estListeOptions(data) {
        return typeof data === 'string' && data.trim().substr(0, 7) === '<option';
    }

    function poster(payload) {
        return $.ajax({ type: 'POST', url: URL_CTRL, dataType: 'json', data: payload });
    }

    function normaliser(liste) {
        return (liste || []).map(function (t) {
            return {
                id:       parseInt(t.id, 10),
                tache:    t.tache,
                url:      t.url,
                sousMenu: t.sousMenu || null
            };
        });
    }


    /* ---------------------------------------------------------------------
     *  Chargement des deux listes déroulantes
     * ------------------------------------------------------------------ */

    function chargerListe(option, $select, libelleVide, libelleErreur) {
        $.ajax({
            type: 'POST',
            url: URL_CTRL,
            data: { option: option },
            success: function (data) {
                if (data === 'sessionExpired') { sessionPerdue(); return; }
                if (estListeOptions(data)) {
                    $select.html(data);
                } else {
                    $select.html('<option value="">' + libelleVide + '</option>');
                }
            },
            error: function () {
                $select.html('<option value="">' + libelleVide + '</option>');
                alerte('error', 'Erreur', libelleErreur);
            }
        });
    }


    /* ---------------------------------------------------------------------
     *  Sélection de la cible — les deux selects sont exclusifs
     * ------------------------------------------------------------------ */

    function definirCible(identifiant, libelle, source) {
        if (!identifiant) {
            cible = null;
            viderColonnes();
            return;
        }
        cible = { identifiant: identifiant, libelle: libelle, source: source };
        chargerColonnes();
    }

    function brancherSelects() {
        var $agent     = $('#agentSelectForTask');
        var $directeur = $('#listDirecteur');

        $agent.on('change', function () {
            $directeur.val('');                       // exclusivité
            definirCible(this.value, $(this).find('option:selected').text(), 'agent');
        });

        $directeur.on('change', function () {
            $agent.val('');                           // exclusivité
            definirCible(this.value, $(this).find('option:selected').text(), 'directeur');
        });
    }


    /* ---------------------------------------------------------------------
     *  Chargement et rendu des colonnes
     * ------------------------------------------------------------------ */

    function chargerColonnes() {
        if (!cible) { viderColonnes(); return; }

        selDisponibles.clear();
        selDetenues.clear();
        filtreSousMenu = null;

        $('#tache3').html(messageColonne('Chargement…'));
        $('#tache2').html(messageColonne('Chargement…'));
        majBoutons();

        poster({ option: 4, identifiant: cible.identifiant })
            .done(function (rep) {
                if (!rep || rep.status === 'sessionExpired') { sessionPerdue(); return; }
                if (rep.status !== 'ok') {
                    viderColonnes();
                    alerte('error', 'Erreur', rep.message || 'Chargement impossible.');
                    return;
                }
                // PDO renvoie les entiers en chaînes : on normalise une fois
                // pour que les comparaisons de Set restent fiables.
                lstDetenues    = normaliser(rep.detenues);
                lstDisponibles = normaliser(rep.disponibles);
                rendreDetenues();
                rendreDisponibles();
                rendreFiltres();
                majBoutons();
            })
            .fail(function (xhr) {
                viderColonnes();
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ||
                    'Impossible de charger les tâches.';
                alerte('error', 'Erreur', msg);
            });
    }

    function messageColonne(texte) {
        return $('<div>')
            .addClass('d-flex align-items-center bg-light-warning rounded p-5 mb-3 w-400px')
            .append($('<span>')
                .addClass('fw-bolder text-gray-800 fs-6')
                .text(texte));
    }

    function viderColonnes() {
        lstDisponibles = [];
        lstDetenues    = [];
        selDisponibles.clear();
        selDetenues.clear();
        filtreSousMenu = null;
        $('#tache3').html(messageColonne('Aucune tâche'));
        $('#tache2').html(messageColonne('Aucune tâche'));
        $('#services').addClass('d-none').empty();
        majBoutons();
    }

    // Construit une carte de tâche cliquable.
    function carteTache(tache, colonne) {
        var selection = (colonne === 'disponibles') ? selDisponibles : selDetenues;
        var actif     = selection.has(tache.id);

        return $('<div>')
            .addClass('d-flex align-items-center rounded p-5 mb-3 w-400px cursor-pointer')
            .addClass(actif ? 'bg-primary' : 'bg-light-warning')
            .attr('data-id', tache.id)
            .attr('data-colonne', colonne)
            .attr('title', tache.sousMenu || '')
            .append($('<div>')
                .addClass('flex-grow-1 me-2')
                .append($('<span>')
                    .addClass('fw-bolder fs-6')
                    .addClass(actif ? 'text-white' : 'text-gray-800')
                    .text(tache.tache)));
    }

    function rendreDetenues() {
        var $c = $('#tache3').empty();
        if (!lstDetenues.length) { $c.append(messageColonne('Aucune tâche')); return; }
        lstDetenues.forEach(function (t) { $c.append(carteTache(t, 'detenues')); });
    }

    function rendreDisponibles() {
        var $c = $('#tache2').empty();
        var liste = filtreSousMenu === null
            ? lstDisponibles
            : lstDisponibles.filter(function (t) { return (t.sousMenu || null) === filtreSousMenu; });

        if (!liste.length) { $c.append(messageColonne('Aucune tâche')); return; }
        liste.forEach(function (t) { $c.append(carteTache(t, 'disponibles')); });
    }

    // Filtres par sous-menu. L'état actif est conservé entre deux rendus
    // (l'ancien displayTaches le perdait à chaque appel).
    function rendreFiltres() {
        var $s = $('#services').empty();
        var sousMenus = [];

        lstDisponibles.forEach(function (t) {
            var sm = t.sousMenu || null;
            if (sousMenus.indexOf(sm) === -1) sousMenus.push(sm);
        });

        if (sousMenus.length < 2) { $s.addClass('d-none'); return; }
        $s.removeClass('d-none');

        $s.append(boutonFiltre(null, 'Tous'));
        sousMenus.forEach(function (sm) {
            $s.append(boutonFiltre(sm, sm === null ? '…' : sm));
        });
    }

    function boutonFiltre(valeur, libelle) {
        var actif = (filtreSousMenu === valeur);
        return $('<span>')
            .addClass('btn m-1')
            .addClass(actif ? 'btn-primary' : 'btn-outline-primary')
            .css('fontSize', '12px')
            .text(libelle)
            .on('click', function () {
                filtreSousMenu = valeur;
                // Les tâches masquées par le filtre sortent de la sélection.
                selDisponibles.forEach(function (id) {
                    var t = lstDisponibles.find(function (x) { return x.id === id; });
                    if (t && valeur !== null && (t.sousMenu || null) !== valeur) {
                        selDisponibles.delete(id);
                    }
                });
                rendreDisponibles();
                rendreFiltres();
                majBoutons();
            });
    }


    /* ---------------------------------------------------------------------
     *  Sélection des tâches
     * ------------------------------------------------------------------ */

    function brancherClicTaches() {
        $('#tache2, #tache3').on('click', '[data-id]', function () {
            var id      = parseInt($(this).attr('data-id'), 10);
            var colonne = $(this).attr('data-colonne');

            if (colonne === 'disponibles') {
                // Une seule direction à la fois : sélectionner à droite
                // annule la sélection de gauche.
                if (selDetenues.size) { selDetenues.clear(); rendreDetenues(); }
                selDisponibles.has(id) ? selDisponibles.delete(id) : selDisponibles.add(id);
                rendreDisponibles();
            } else {
                if (selDisponibles.size) { selDisponibles.clear(); rendreDisponibles(); }
                selDetenues.has(id) ? selDetenues.delete(id) : selDetenues.add(id);
                rendreDetenues();
            }
            majBoutons();
        });
    }

    function majBoutons() {
        basculerBouton($('.attribution'), selDisponibles.size > 0 && !enCours, 'btn-outline-success');
        basculerBouton($('.restriction'), selDetenues.size    > 0 && !enCours, 'btn-outline-warning');
    }

    function basculerBouton($btn, actif, classeActive) {
        if (actif) {
            $btn.removeClass('disabled btn-secondary').addClass(classeActive);
        } else {
            $btn.removeClass('btn-outline-success btn-outline-warning')
                .addClass('disabled btn-secondary');
        }
    }


    /* ---------------------------------------------------------------------
     *  Affectation / retrait
     * ------------------------------------------------------------------ */

    function confirmer(titre, texte, onOui) {
        if (typeof Swal === 'undefined') {
            if (window.confirm(titre + '\n' + texte)) onOui();
            return;
        }
        Swal.fire({
            title: titre,
            text: texte,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'OUI',
            cancelButtonText: 'NON'
        }).then(function (res) { if (res.isConfirmed) onOui(); });
    }

    // Un seul appel réseau pour toute la sélection, puis un seul rechargement
    // (l'ancien code lançait N requêtes qui se rechargeaient mutuellement).
    function envoyer(option, ids, libelleAction) {
        if (enCours || !cible || !ids.length) return;
        enCours = true;
        majBoutons();

        poster({ option: option, identifiant: cible.identifiant, taches: ids })
            .done(function (rep) {
                if (!rep || rep.status === 'sessionExpired') { sessionPerdue(); return; }

                if (rep.status === 'ok') {
                    alerte('success', libelleAction, rep.message);
                } else if (rep.status === 'partiel') {
                    var refus = (rep.resultats || [])
                        .filter(function (r) { return !r.ok; })
                        .map(function (r) { return '• ' + (r.tache || '#' + r.idTache) + ' : ' + r.message; })
                        .join('\n');
                    alerte('warning', libelleAction, rep.message + '\n' + refus);
                } else {
                    alerte('error', 'Erreur', rep.message || 'Opération refusée.');
                }
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Opération impossible.';
                alerte('error', 'Erreur', msg);
            })
            .always(function () {
                enCours = false;
                chargerColonnes();          // rechargement unique, état recalculé
            });
    }

    // Exposées en global : les <i> du HTML portent encore onclick="…"
    window.octroiementTache = function () {
        if (!cible) { alerte('info', 'Sélection requise', 'Choisissez un agent ou un directeur.'); return; }
        var ids = Array.from(selDisponibles);
        if (!ids.length) return;

        confirmer('Êtes-vous sûr ?',
            'Attribuer ' + ids.length + ' tâche(s) à ' + cible.libelle + ' ?',
            function () { envoyer(5, ids, 'Attribution'); });
    };

    window.restrictionTache = function () {
        if (!cible) { alerte('info', 'Sélection requise', 'Choisissez un agent ou un directeur.'); return; }
        var ids = Array.from(selDetenues);
        if (!ids.length) return;

        confirmer('Êtes-vous sûr ?',
            'Retirer ' + ids.length + ' tâche(s) à ' + cible.libelle + ' ?',
            function () { envoyer(6, ids, 'Retrait'); });
    };


    /* ---------------------------------------------------------------------
     *  Initialisation
     * ------------------------------------------------------------------ */

    $(function () {
        chargerListe(2, $('#agentSelectForTask'),
            'Sélectionner un agent', "Impossible de charger la liste des agents.");
        chargerListe(3, $('#listDirecteur'),
            'Sélectionner un directeur', "Impossible de charger la liste des directeurs.");

        brancherSelects();
        brancherClicTaches();
        viderColonnes();
    });

})(jQuery);