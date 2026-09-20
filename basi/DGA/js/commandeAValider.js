/**
 * dga-liste-commandes-scripts.bundle.js
 * Page "Validation des commandes" (DGA) : liste les commandes en attente
 * (passer_achat_et_paiement.idStatut = 1), et permet de les valider via une
 * modale à 2 blocs (choix du fournisseur + prix réels des lignes).
 */

const DGA_CONTROLLER_URL = '/personnel/dga_basi_controller'; // ← ajuster selon le chemin réel

let dga_table          = null;
let dga_commandeActive = null; // token de la commande en cours de validation
let dga_idPAPCourant   = null; // idPAP réel de la commande en cours de validation
let dga_fournisseurs   = [];
let dga_lignes         = [];

document.addEventListener('DOMContentLoaded', function () {
    // Initialise le DataTable vide immédiatement : sans ça, le <table> brut
    // (sans barre de recherche, pagination, ni style d'en-tête) reste visible
    // tel quel tant que les données n'ont pas fini de charger — d'où le
    // "flash" de mise en forme incorrecte pendant le chargement.
    dga_renderTable([]);

    chargerCommandes();
    document.getElementById('dgaSubmitValider')?.addEventListener('click', soumettreValidation);
});

/* ────────────────────────── CHARGEMENT LISTE ─────────────────────── */
function chargerCommandes() {
    dga_showLoader('Chargement des commandes…');

    $.ajax({
        url: DGA_CONTROLLER_URL,
        method: 'POST',
        data: { option: 1 },
        dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') {
            dga_hideLoader();
            Swal.fire('Erreur', res.message || 'Impossible de charger les commandes.', 'error');
            return;
        }
        // Le tableau est repeuplé AVANT de cacher le loader : le changement de
        // hauteur de page (et donc le déplacement du footer) reste masqué
        // derrière l'overlay au lieu d'être visible à l'écran.
        dga_renderTable(res.data || []);
        dga_hideLoader();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_renderTable(commandes) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-commandes').DataTable({
        data: commandes,
        columns: [
            { data: 'idPAP' },
            { data: 'nom_commande' },
            { data: 'demandeur' },
            { data: 'dateCreation' },
            { data: 'mode_reglement_nom' },
            { data: 'mode_paiement_nom' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => `#${d}` },
            { targets: 3, render: d => dga_fmtDate(d) },
            { targets: [4, 5], render: d => d || '<span style="color:#d1d5db;">—</span>' },
            {
                targets: 6,
                render: (d) => `
                    <button type="button" class="dga-btn-valider" onclick="dga_ouvrirModale('${d}')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M20 6L9 17l-5-5"/>
                        </svg>
                        Valider
                    </button>
                `,
            },
        ],
        order: [[0, 'desc']],
        language: {
            emptyTable: 'Aucune commande à valider.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
        initComplete: function () {

            document.documentElement.classList.remove('ld-booting');
            document.getElementById('lb-table')?.classList.add('lb-ready');

        }
    });
}

/* ────────────────────────── OUVERTURE MODALE ─────────────────────── */
function dga_ouvrirModale(token) {
    dga_commandeActive = token;
    dga_showLoader('Chargement du détail…');

    $.ajax({
        url: DGA_CONTROLLER_URL,
        method: 'POST',
        data: { option: 2, token: token },
        dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail de la commande.', 'error');
            return;
        }

        dga_fournisseurs  = res.fournisseurs || [];
        dga_lignes        = res.lignes || [];
        dga_idPAPCourant  = res.commande?.idPAP || null;

        document.getElementById('dgaModalTitre').textContent = 'Validation — ' + (res.commande?.nom_commande || '');
        dga_clearErreur();
        dga_afficherFournisseurs();
        dga_afficherLignes();

        new bootstrap.Modal(document.getElementById('modalValiderCommande')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ── Bloc 1 : fournisseurs proposés (pro forma actifs) ─────────────────── */
function dga_afficherFournisseurs() {
    const conteneur = document.getElementById('dgaListeFournisseurs');

    if (!dga_fournisseurs.length) {
        conteneur.innerHTML = '<p style="color:#9ca3af;font-style:italic;">Aucun fournisseur pro forma disponible pour cette commande.</p>';
        return;
    }

    conteneur.innerHTML = dga_fournisseurs.map(function (f, idx) {
        const nom = `${f.prenomF || ''} ${f.nomF || ''}`.trim() + (f.entreprise ? ' — ' + f.entreprise : '');
        const coordonnees = [f.telF, f.emailF, f.ville].filter(Boolean).join(' · ');
        return `
            <label class="dga-fournisseur-item" data-doc-id="${f.id}">
                <input type="radio" name="dgaFournisseurChoisi" value="${f.id}"/>
                <div class="dga-fournisseur-info">
                    <div class="dga-fournisseur-nom">${dga_escapeHtml(nom)}</div>
                    <div class="dga-fournisseur-details">${dga_escapeHtml(coordonnees)}</div>
                    ${f.doc ? `<a href="${f.doc}" target="_blank" class="dga-fournisseur-doc">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Voir le pro forma
                    </a>` : ''}
                </div>
            </label>
        `;
    }).join('');

    conteneur.querySelectorAll('input[name="dgaFournisseurChoisi"]').forEach(function (radio) {
        radio.addEventListener('change', dga_majSelectionFournisseur);
    });
}

function dga_majSelectionFournisseur() {
    document.querySelectorAll('.dga-fournisseur-item').forEach(function (item) {
        const radio = item.querySelector('input[type="radio"]');
        item.classList.toggle('dga-fournisseur-selected', radio.checked);
    });
}

/* ── Bloc 2 : lignes de la commande (prix réel) ─────────────────────────── */
function dga_afficherLignes() {
    const body = document.getElementById('dgaBodyLignes');
    body.innerHTML = dga_lignes.map(function (l) {
        return `
            <tr data-idpapl="${l.idPAPL}">
                <td>${dga_escapeHtml(l.designation || '')}</td>
                <td>${dga_formatNumber(l.quantite_reelle)} ${dga_escapeHtml(l.unite || '')}</td>
                <td><input type="number" class="dga-inp dga-prix-reel" min="0.01" step="0.01" placeholder="0.00" value="${l.prix_reel ?? ''}"/></td>
            </tr>
        `;
    }).join('');

    body.querySelectorAll('.dga-prix-reel').forEach(inp => {
        inp.addEventListener('input', dga_recalculerTotal);
    });
    dga_recalculerTotal();
}

function dga_recalculerTotal() {
    let total = 0;
    document.querySelectorAll('#dgaBodyLignes tr').forEach(function (tr) {
        const idPAPL = tr.dataset.idpapl;
        const prix   = parseFloat(tr.querySelector('.dga-prix-reel')?.value) || 0;
        const ligne  = dga_lignes.find(l => String(l.idPAPL) === String(idPAPL));
        const qte    = ligne ? (parseFloat(ligne.quantite_reelle) || 0) : 0;
        total += prix * qte;
    });
    document.getElementById('dgaMontantTotal').textContent = dga_formatMontant(total);
}

/* ────────────────────────── SOUMISSION ───────────────────────────── */
function soumettreValidation() {
    dga_clearErreur();

    // ── Bloc 1 : fournisseur choisi ──────────────────────────────────────
    const radioChoisi = document.querySelector('input[name="dgaFournisseurChoisi"]:checked');
    if (!radioChoisi) {
        dga_afficherErreur('Sélectionnez le fournisseur retenu.');
        return;
    }
    const idDocumentChoisi = radioChoisi.value;

    // ── Bloc 2 : prix réels ──────────────────────────────────────────────
    const lignes = [];
    const champsInvalides = [];

    document.querySelectorAll('#dgaBodyLignes tr').forEach(function (tr) {
        const inp  = tr.querySelector('.dga-prix-reel');
        const prix = inp.value;
        if (!prix || parseFloat(prix) <= 0) champsInvalides.push(inp);

        lignes.push({ idPAPL: tr.dataset.idpapl, prix_reel: prix });
    });

    if (!lignes.length) {
        dga_afficherErreur('Aucune ligne à mettre à jour.');
        return;
    }
    if (champsInvalides.length) {
        dga_afficherErreur('Renseignez un prix réel valide (> 0) pour chaque ligne.', champsInvalides);
        return;
    }

    // ── Envoi (JSON classique — plus d'upload de fichier à cette étape) ──
    dga_toggleSpinner(true);

    $.ajax({
        url: DGA_CONTROLLER_URL,
        method: 'POST',
        data: {
            option: 3,
            idPAP: dga_idPAPCourant,
            id_document_choisi: idDocumentChoisi,
            lignes: JSON.stringify(lignes),
        },
        dataType: 'json',
    }).done(function (res) {
        dga_toggleSpinner(false);
        if (res.status === 'success') {
            const modalEl = document.getElementById('modalValiderCommande');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            Swal.fire({ title: 'Succès', text: res.message || 'Commande validée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
            chargerCommandes();
        } else {
            dga_afficherErreur(res.message || 'Une erreur est survenue.');
        }
    }).fail(function (xhr) {
        dga_toggleSpinner(false);
        dga_afficherErreur(dga_ajaxErrorMessage(xhr));
    });
}

/* ────────────────────────────── ERREURS ──────────────────────────── */
function dga_afficherErreur(message, champsInvalides) {
    const banniere = document.getElementById('dgaErreurGenerale');
    banniere.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span>${dga_escapeHtml(message)}</span>
    `;
    banniere.style.display = 'flex';
    banniere.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    document.querySelectorAll('.dga-invalid').forEach(el => el.classList.remove('dga-invalid'));
    (champsInvalides || []).forEach(el => { if (el) el.classList.add('dga-invalid'); });
}

function dga_clearErreur() {
    const banniere = document.getElementById('dgaErreurGenerale');
    banniere.style.display = 'none';
    banniere.innerHTML = '';
    document.querySelectorAll('.dga-invalid').forEach(el => el.classList.remove('dga-invalid'));
}

/* ────────────────────────────── UTILITAIRES ──────────────────────── */
function dga_showLoader(msg = 'Chargement…') {
    $('#dga-loader').remove();
    $('body').append(`
        <div id="dga-loader">
            <div class="dga-loader-bg"></div>
            <div class="dga-loader-box">
                <svg class="dga-loader-spin" viewBox="0 0 50 50">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/>
                </svg>
                <p>${msg}</p>
            </div>
        </div>`);
}
function dga_hideLoader() { $('#dga-loader').remove(); }

function dga_toggleSpinner(loading) {
    const btn = document.getElementById('dgaSubmitValider');
    const spinner = btn.querySelector('.dga-spinner');
    if (spinner) spinner.classList.toggle('hidden', !loading);
    btn.disabled = loading;
}

function dga_fmtDate(d) { return d ? new Date(d.replace(' ', 'T')).toLocaleDateString('fr-FR') : '—'; }

function dga_ajaxErrorMessage(xhr) {
    if (xhr && xhr.responseJSON && xhr.responseJSON.message) return xhr.responseJSON.message;
    if (!xhr || xhr.status === 0) return 'Impossible de contacter le serveur. Vérifiez votre connexion.';
    switch (xhr.status) {
        case 400: return 'Requête invalide. Merci de réessayer.';
        case 401: return 'Votre session a expiré. Veuillez vous reconnecter.';
        case 403: return "Vous n'avez pas les droits nécessaires pour effectuer cette action.";
        case 404: return 'Ressource introuvable.';
        case 500: return 'Erreur interne du serveur. Merci de réessayer plus tard.';
        default:  return 'Impossible de contacter le serveur (code ' + xhr.status + ').';
    }
}

function dga_formatNumber(value) {
    const n = Number(value);
    if (isNaN(n)) return dga_escapeHtml(String(value ?? ''));
    return n.toLocaleString('fr-FR');
}

function dga_formatMontant(value) {
    const n = Number(value);
    if (isNaN(n)) return dga_escapeHtml(String(value ?? ''));
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' FCFA';
}

function dga_escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}