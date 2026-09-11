/**
 * inventaire-operateur-scripts.bundle.js
 * Page "Saisie d'inventaire" (profil Opérateur) : affiche uniquement
 * l'inventaire en cours (etat=1, idStatut ∈ {1,2}), saisie des quantités,
 * Sauvegarder en brouillon / Soumettre le traitement.
 */

const INVENTAIRE_OP_CONTROLLER_URL = '/personnel/operateur_basi_controller'; // ← ajuster selon le chemin réel

let dga_inventaireCourant = null;

document.addEventListener('DOMContentLoaded', function () {
    chargerInventaireEnCours();
    document.getElementById('dgaBtnConfirmerSoumission')?.addEventListener('click', dga_confirmerSoumission);
});

/* ────────────────────────── CHARGEMENT ─────────────────────────────── */
function chargerInventaireEnCours() {
    dga_showLoader('Chargement de l\'inventaire en cours…');

    $.ajax({
        url: INVENTAIRE_OP_CONTROLLER_URL, method: 'POST', data: { option: 1 }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || "Impossible de charger l'inventaire.", 'error');
            return;
        }

        dga_inventaireCourant = res.inventaire;
        dga_renderZone();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── RENDU DE LA ZONE ───────────────────────── */
function dga_renderZone() {
    const zone = document.getElementById('dga-zone-inventaire');

    if (!dga_inventaireCourant) {
        zone.innerHTML = `
            <div class="dga-card">
                <div class="dga-vide">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:.75rem;"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                    <p>Aucun inventaire en cours pour le moment.</p>
                </div>
            </div>
        `;
        return;
    }

    const inv = dga_inventaireCourant;
    const lignesHtml = (inv.lignes || []).map(function (l) {
        return `
            <tr>
                <td>${dga_escapeHtml(l.nom_categorie)}</td>
                <td>${dga_escapeHtml(l.nom_sous_categorie)}</td>
                <td>${dga_escapeHtml(l.nomproduit)}</td>
                <td>${l.produit_actif ? '<span class="dga-badge-actif">Actif</span>' : '<span class="dga-badge-inactif">Inactif</span>'}</td>
                <td><input type="number" class="dga-inp-qte-op" data-idip="${l.idIP}" min="0" step="0.01" value="${l.quantite_operateur !== null ? l.quantite_operateur : ''}" placeholder="Quantité"/></td>
            </tr>
        `;
    }).join('') || '<tr><td colspan="5" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit dans cet inventaire.</td></tr>';

    zone.innerHTML = `
        <div class="dga-card">
            <div class="dga-card-head">
                <span class="dga-card-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/></svg>
                    ${dga_escapeHtml(inv.reference)}
                </span>
            </div>
            <table id="dga-table-inv-op" class="display" style="width:100%">
                <thead><tr><th>Catégorie</th><th>Sous-catégorie</th><th>Produit</th><th>Statut</th><th>Quantité</th></tr></thead>
                <tbody>${lignesHtml}</tbody>
            </table>
            <div class="dga-actions-bar">
                <a href="/personnel/operateur_basi_inventaire-pdf-vierge/${inv.tmp}" target="_blank" class="dga-btn-pdf-op">PDF vierge</a>
                <button type="button" class="dga-btn-brouillon" id="dga-btn-brouillon">Sauvegarder en brouillon</button>
                <button type="button" class="dga-btn-soumettre" id="dga-btn-soumettre">Soumettre le traitement</button>
            </div>
        </div>
    `;

    $('#dga-table-inv-op').DataTable({
        paging: true, pageLength: 25, order: [],
        language: {
            emptyTable: 'Aucun produit dans cet inventaire.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
    });

    document.getElementById('dga-btn-brouillon')?.addEventListener('click', dga_sauvegarderBrouillon);
    document.getElementById('dga-btn-soumettre')?.addEventListener('click', dga_ouvrirSoumission);
}

/* ────────────────────────── COLLECTE DES QUANTITÉS ─────────────────── */
function dga_collecterLignes() {
    const lignes = [];
    document.querySelectorAll('.dga-inp-qte-op').forEach(function (input) {
        if (input.value !== '') {
            lignes.push({ idIP: input.dataset.idip, quantite_operateur: input.value });
        }
    });
    return lignes;
}

/* ────────────────────────── BROUILLON ──────────────────────────────── */
function dga_sauvegarderBrouillon() {
    const lignes = dga_collecterLignes();
    const btn = document.getElementById('dga-btn-brouillon');
    btn.disabled = true;

    dga_showLoader('Enregistrement du brouillon…');
    $.ajax({
        url: INVENTAIRE_OP_CONTROLLER_URL,
        method: 'POST',
        data: JSON.stringify({ option: 2, token: dga_inventaireCourant.tmp, lignes: lignes }),
        contentType: 'application/json',
        dataType: 'json',
    }).done(function (res) {
        dga_hideLoader();
        btn.disabled = false;
        if (res.status === 'success') {
            Swal.fire({ title: 'Succès', text: res.message || 'Brouillon enregistré avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
            chargerInventaireEnCours();
        } else {
            Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
        }
    }).fail(function (xhr) {
        dga_hideLoader();
        btn.disabled = false;
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── SOUMISSION ─────────────────────────────── */
function dga_ouvrirSoumission() {
    const inputsVides = Array.from(document.querySelectorAll('.dga-inp-qte-op')).filter(input => input.value === '');
    if (inputsVides.length > 0) {
        Swal.fire('Quantités manquantes', `${inputsVides.length} produit(s) n'ont pas de quantité renseignée (0 est accepté, mais le champ ne peut pas être vide).`, 'warning');
        inputsVides.forEach(input => { input.style.borderColor = '#dc2626'; });
        return;
    }

    document.getElementById('dgaErreurSoumettre').style.display = 'none';
    document.getElementById('dgaObservationOperateur').value = '';
    new bootstrap.Modal(document.getElementById('modalSoumettre')).show();
}

function dga_confirmerSoumission() {
    const erreurBox = document.getElementById('dgaErreurSoumettre');
    erreurBox.style.display = 'none';

    const observation = document.getElementById('dgaObservationOperateur').value.trim();
    if (!observation) {
        erreurBox.textContent = "L'observation est obligatoire pour soumettre le traitement.";
        erreurBox.style.display = 'block';
        return;
    }

    const inputsVides = Array.from(document.querySelectorAll('.dga-inp-qte-op')).filter(input => input.value === '');
    if (inputsVides.length > 0) {
        erreurBox.textContent = `Impossible de soumettre : ${inputsVides.length} produit(s) n'ont pas de quantité renseignée (0 est accepté, mais le champ ne peut pas être vide).`;
        erreurBox.style.display = 'block';
        inputsVides.forEach(input => { input.style.borderColor = '#dc2626'; });
        return;
    }

    const lignes = dga_collecterLignes();
    const btn = document.getElementById('dgaBtnConfirmerSoumission');
    btn.disabled = true;
    btn.querySelector('.dga-spinner').classList.remove('hidden');

    $.ajax({
        url: INVENTAIRE_OP_CONTROLLER_URL,
        method: 'POST',
        data: JSON.stringify({ option: 3, token: dga_inventaireCourant.tmp, lignes: lignes, observation: observation }),
        contentType: 'application/json',
        dataType: 'json',
    }).done(function (res) {
        btn.disabled = false;
        btn.querySelector('.dga-spinner').classList.add('hidden');

        if (res.status === 'success') {
            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalSoumettre'));
            if (modalInstance) modalInstance.hide();
            Swal.fire({ title: 'Succès', text: res.message || 'Traitement soumis avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
            chargerInventaireEnCours();
        } else {
            erreurBox.textContent = res.message || 'Une erreur est survenue.';
            erreurBox.style.display = 'block';
        }
    }).fail(function (xhr) {
        btn.disabled = false;
        btn.querySelector('.dga-spinner').classList.add('hidden');
        erreurBox.textContent = dga_ajaxErrorMessage(xhr);
        erreurBox.style.display = 'block';
    });
}

/* ────────────────────────────── UTILITAIRES ────────────────────── */
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

function dga_escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}