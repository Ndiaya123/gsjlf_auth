/**
 * liste-livraisons-scripts.bundle.js
 * Page "Gestion des livraisons" (profil Comptable) : liste filtrable par
 * intervalle d'années, création d'une livraison (opération irréversible),
 * consultation et accès au bon de livraison.
 */

const CAISSE_CONTROLLER_URL = '/personnel/cpt_caisse_basi_controller'; // ← ajuster selon le chemin réel
const ANNEE_MIN_LIVRAISON = 2026;

let dga_table = null;
let dga_lignesCourantes = []; // lignes de la commande sélectionnée (avec quantité restante)

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]);
    initSelectsAnnees();
    chargerLivraisons();

    document.getElementById('dga-btn-appliquer-filtres')?.addEventListener('click', chargerLivraisons);
    document.getElementById('dga-btn-reset-filtres')?.addEventListener('click', function () {
        const anneeCourante = new Date().getFullYear();
        document.getElementById('dga-annee-debut').value = '';
        document.getElementById('dga-annee-fin').value = anneeCourante;
        chargerLivraisons();
    });

    document.getElementById('dga-btn-nouvelle')?.addEventListener('click', dga_ouvrirNouvelleLivraison);
    document.getElementById('dgaCommande')?.addEventListener('change', dga_chargerLignesCommande);
    document.getElementById('dgaSubmitLivraison')?.addEventListener('click', dga_soumettreLivraison);
});

/* ────────────────────────── ANNÉES (filtres) ───────────────────────── */
function initSelectsAnnees() {
    const anneeCourante = new Date().getFullYear();
    const debutSel = document.getElementById('dga-annee-debut');
    const finSel   = document.getElementById('dga-annee-fin');
    if (!debutSel || !finSel) return;

    const anneeMax = Math.max(anneeCourante, ANNEE_MIN_LIVRAISON);
    let options = '';
    for (let a = ANNEE_MIN_LIVRAISON; a <= anneeMax; a++) {
        options += `<option value="${a}">${a}</option>`;
    }

    debutSel.innerHTML = '<option value="">Toutes</option>' + options;
    finSel.innerHTML   = options;

    debutSel.value = '';
    finSel.value   = anneeCourante;
}

/* ────────────────────────── CHARGEMENT LISTE ───────────────────────── */
function chargerLivraisons() {
    const anneeDebutVal = document.getElementById('dga-annee-debut').value;
    const anneeFinVal   = document.getElementById('dga-annee-fin').value;

    if (anneeDebutVal !== '' && anneeFinVal !== '' && parseInt(anneeDebutVal) > parseInt(anneeFinVal)) {
        Swal.fire('Intervalle invalide', '« Début » ne peut pas être supérieur à « Fin ».', 'warning');
        return;
    }

    dga_showLoader('Chargement des livraisons…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL,
        method: 'POST',
        data: { option: 20, anneeDebut: anneeDebutVal, anneeFin: anneeFinVal },
        dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les livraisons.', 'error');
            return;
        }
        dga_renderTable(res.data || []);
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ───────────────────────────── TABLE ────────────────────────────── */
function dga_renderTable(livraisons) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-livraisons').DataTable({
        data: livraisons,
        columns: [
            { data: 'numeroPAP' },
            { data: 'nom_commande' },
            { data: 'numero_livraison' },
            { data: 'date_livraison' },
            { data: 'idStatut' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => dga_escapeHtml(d) },
            { targets: 2, render: d => d ? dga_escapeHtml(d) : '<span style="color:#d1d5db;">—</span>' },
            { targets: 3, render: d => dga_fmtDate(d) },
            { targets: 4, render: d => '<span class="dga-badge-statut dga-statut-1">Enregistrée</span>' },
            {
                targets: 5,
                render: (d, t, row) => `
                    ${row.fichier_bon_livraison ? `
                        <a href="${row.fichier_bon_livraison}" target="_blank" class="dga-btn-fichier">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Bon de livraison
                        </a>
                    ` : ''}
                    <button type="button" class="dga-btn-consulter" onclick="dga_ouvrirConsulter('${d}')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/></svg>
                        Consulter
                    </button>
                `,
            },
        ],
        order: [[3, 'desc']],
        language: {
            emptyTable: 'Aucune livraison à afficher.',
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

/* ────────────────────────── NOUVELLE LIVRAISON ─────────────────────── */
function dga_ouvrirNouvelleLivraison() {
    document.getElementById('dgaErreurGenerale').style.display = 'none';
    document.getElementById('dgaBlocLignes').style.display = 'none';
    document.getElementById('dgaSubmitLivraison').style.display = 'none';
    document.getElementById('dgaNumeroLivraison').value = '';
    document.getElementById('dgaDateLivraison').value = new Date().toISOString().slice(0, 10);
    document.getElementById('dgaFichierBL').value = '';
    document.getElementById('dgaCorpsLignes').innerHTML = '';
    dga_lignesCourantes = [];

    const sel = document.getElementById('dgaCommande');
    sel.innerHTML = '<option value="">Chargement…</option>';
    sel.value = '';

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 21 }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') {
            sel.innerHTML = '<option value="">Erreur de chargement</option>';
            return;
        }
        const commandes = res.data || [];
        sel.innerHTML = '<option value="">Sélectionner une commande…</option>' +
            commandes.map(c => `<option value="${c.tmp}">${dga_escapeHtml(c.numero || c.id)} — ${dga_escapeHtml(c.nom_commande)}</option>`).join('');

        if (!commandes.length) {
            sel.innerHTML = '<option value="">Aucune commande livrable</option>';
        }
    });

    new bootstrap.Modal(document.getElementById('modalNouvelleLivraison')).show();
}

function dga_plafonnerQuantite(input) {
    const max = parseFloat(input.dataset.max);
    const val = parseFloat(input.value);
    if (!isNaN(val) && !isNaN(max) && val > max) {
        input.value = max;
        input.style.borderColor = '#dc2626';
        setTimeout(() => { input.style.borderColor = ''; }, 800);
    }
}

function dga_chargerLignesCommande() {
    const token = document.getElementById('dgaCommande').value;
    const bloc = document.getElementById('dgaBlocLignes');
    const submitBtn = document.getElementById('dgaSubmitLivraison');

    if (!token) {
        bloc.style.display = 'none';
        submitBtn.style.display = 'none';
        return;
    }

    dga_showLoader('Chargement des lignes…');
    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 22, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les lignes de la commande.', 'error');
            return;
        }

        dga_lignesCourantes = res.lignes || [];
        document.getElementById('dgaCorpsLignes').innerHTML = dga_lignesCourantes.map(function (l) {
            return `
                <tr>
                    <td>${dga_escapeHtml(l.designation || '')}</td>
                    <td>${dga_escapeHtml(l.quantite_restante)}</td>
                    <td><input type="number" class="dga-inp-qte" data-idpapl="${l.idPAPL}" data-max="${l.quantite_restante}" min="0" max="${l.quantite_restante}" step="0.01" value="0" oninput="dga_plafonnerQuantite(this)"/></td>
                </tr>
            `;
        }).join('');

        document.getElementById('dgaBlocLignes').style.display = 'block';
        document.getElementById('dgaSubmitLivraison').style.display = 'inline-flex';
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_soumettreLivraison() {
    const erreurBox = document.getElementById('dgaErreurGenerale');
    erreurBox.style.display = 'none';

    const token = document.getElementById('dgaCommande').value;
    if (!token) {
        erreurBox.textContent = 'Veuillez sélectionner une commande.';
        erreurBox.style.display = 'block';
        return;
    }

    if (!document.getElementById('dgaNumeroLivraison').value.trim()) {
        erreurBox.textContent = 'Le numéro de livraison est obligatoire.';
        erreurBox.style.display = 'block';
        return;
    }

    if (!document.getElementById('dgaFichierBL').files[0]) {
        erreurBox.textContent = 'Le bon de livraison est obligatoire.';
        erreurBox.style.display = 'block';
        return;
    }

    const quantites = {};
    let auMoinsUne = false;
    let depassement = false;
    document.querySelectorAll('.dga-inp-qte').forEach(function (input) {
        const val = parseFloat(input.value) || 0;
        const max = parseFloat(input.dataset.max) || 0;
        if (val > max + 0.001) depassement = true;
        if (val > 0) auMoinsUne = true;
        quantites[input.dataset.idpapl] = val;
    });

    if (depassement) {
        erreurBox.textContent = 'La quantité reçue ne peut pas dépasser la quantité restant à livrer.';
        erreurBox.style.display = 'block';
        return;
    }

    if (!auMoinsUne) {
        erreurBox.textContent = 'Veuillez saisir au moins une quantité reçue.';
        erreurBox.style.display = 'block';
        return;
    }

    Swal.fire({
        title: 'Confirmer la livraison',
        text: 'Cette opération est irréversible. Confirmez-vous vouloir enregistrer cette livraison ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Confirmer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#1a7a5e',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('option', 23);
        fd.append('token', token);
        fd.append('numero_livraison', document.getElementById('dgaNumeroLivraison').value.trim());
        fd.append('date_livraison', document.getElementById('dgaDateLivraison').value);
        fd.append('quantites', JSON.stringify(quantites));
        const fichier = document.getElementById('dgaFichierBL').files[0];
        if (fichier) fd.append('fichier_bon_livraison', fichier);

        const btn = document.getElementById('dgaSubmitLivraison');
        btn.disabled = true;
        btn.querySelector('.dga-spinner').classList.remove('hidden');

        $.ajax({
            url: CAISSE_CONTROLLER_URL, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
        }).done(function (res) {
            btn.disabled = false;
            btn.querySelector('.dga-spinner').classList.add('hidden');

            if (res.status === 'success') {
                const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalNouvelleLivraison'));
                if (modalInstance) modalInstance.hide();
                Swal.fire({ title: 'Succès', text: res.message || 'Livraison enregistrée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerLivraisons();
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
    });
}

/* ────────────────────────── CONSULTER ──────────────────────────────── */
function dga_ouvrirConsulter(token) {
    dga_showLoader('Chargement du détail…');

    $.ajax({
        url: CAISSE_CONTROLLER_URL, method: 'POST', data: { option: 24, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        const l = res.livraison;
        const produitsHtml = (l.produits || []).map(function (p) {
            return `
                <tr>
                    <td>${dga_escapeHtml(p.designation || '')}</td>
                    <td>${dga_escapeHtml(p.quantite)}</td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="2" style="text-align:center;color:#9ca3af;font-style:italic;">Aucune ligne.</td></tr>';

        document.getElementById('dgaContenuConsulter').innerHTML = `
            <div class="dga-detail-grid">
                <div class="dga-detail-item"><span class="lbl">N° commande</span><span class="val">${dga_escapeHtml(l.numeroPAP)}</span></div>
                <div class="dga-detail-item"><span class="lbl">N° livraison</span><span class="val">${l.numero_livraison ? dga_escapeHtml(l.numero_livraison) : '—'}</span></div>
                <div class="dga-detail-item"><span class="lbl">Date de livraison</span><span class="val">${dga_fmtDate(l.date_livraison)}</span></div>
                <div class="dga-detail-item"><span class="lbl">Nom commande</span><span class="val">${dga_escapeHtml(l.nom_commande)}</span></div>
            </div>
            ${l.fichier_bon_livraison ? `
                <a href="${l.fichier_bon_livraison}" target="_blank" class="dga-btn-fichier" style="margin-bottom:1rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Voir le bon de livraison
                </a>
            ` : ''}
            <table class="dga-table-lignes">
                <thead><tr><th>Désignation</th><th>Quantité reçue</th></tr></thead>
                <tbody>${produitsHtml}</tbody>
            </table>
        `;

        new bootstrap.Modal(document.getElementById('modalConsulterLivraison')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
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

function dga_escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}