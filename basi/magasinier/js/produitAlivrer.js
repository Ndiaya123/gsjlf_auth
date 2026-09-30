/**
 * listeProduitsALivrer.js
 * Page "Produits à livrer" (profil Magasinier) : liste des BONS DE SORTIE
 * (un bon = une sortie du comptable) ayant des lignes sorties du stock mais
 * pas encore remises au demandeur. Confirmation "Livré" plafonnée strictement
 * à ce qui a été sorti dans le bon, jamais plus.
 */

const MAGASINIER_CONTROLLER_URL = '/personnel/magasinier_basi_controller'; // ← ajuster selon le chemin réel

let dga_table = null;
let dga_tableInvest = null;
let dga_tokenCourant = null;
let dga_typeLivraisonActif = 'fonctionnement'; // 'fonctionnement' | 'investissement' — piloté par la modale active

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]);
    chargerExpressions();
    dga_initCommutateurLivraison();
    document.getElementById('dgaBtnConfirmerLivraison')?.addEventListener('click', dga_confirmerLivraison);
});

/* ────────────────────────── COMMUTATEUR TYPE ────────────────────────── */
function dga_initCommutateurLivraison() {
    document.querySelectorAll('#dga-switch-type-livraison .dga-switch-btn').forEach(function (btn) {
        btn.addEventListener('click', () => dga_activerPanneauLivraison(btn.dataset.cible));
    });
    chargerExpressionsInvest();
}

function dga_activerPanneauLivraison(nom) {
    const estFonctionnement = nom === 'fonctionnement';
    document.getElementById('dga-panel-livraison-fonctionnement').style.display = estFonctionnement ? '' : 'none';
    document.getElementById('dga-panel-livraison-investissement').style.display = estFonctionnement ? 'none' : '';
    document.querySelectorAll('#dga-switch-type-livraison .dga-switch-btn').forEach(function (btn) {
        btn.classList.toggle('dga-switch-active', btn.dataset.cible === nom);
    });
}

/* ────────────────────────── CHARGEMENT LISTE ─────────────────────── */
function chargerExpressions() {
    dga_showLoader('Chargement…');
    $.ajax({
        url: MAGASINIER_CONTROLLER_URL, method: 'POST', data: { option: 1 }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger la liste.', 'error');
            return;
        }
        dga_renderTable(res.data || []);
        document.getElementById('dga-stat-nombre').textContent = res.nombre_total ?? 0;
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_renderTable(expressions) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-livraison').DataTable({
        data: expressions,
        columns: [
            { data: 'numero_bon' },
            { data: 'nom_expression' },
            { data: 'demandeur' },
            { data: 'dateSortie' },
            { data: 'nombre_lignes_a_livrer' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => `<strong>${dga_escapeHtml(d)}</strong>` },
            { targets: 1, render: d => dga_escapeHtml(d) },
            { targets: 2, render: d => dga_escapeHtml(d || '—') },
            { targets: 3, render: d => dga_fmtDate(d) },
            {
                targets: 5,
                render: (d) => `
                    <button type="button" class="dga-btn-avis" onclick="dga_ouvrirLivraisonFonctionnement('${d}')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                        Préparer / Livrer
                    </button>
                `,
            },
        ],
        order: [[3, 'asc']],
        language: {
            emptyTable: 'Aucun produit à livrer pour le moment.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
        initComplete: function () {
            document.documentElement.classList.remove('ld-booting');
        },
    });
}

/* ────────────────────────── LISTE — INVESTISSEMENT ───────────────── */
function chargerExpressionsInvest() {
    $.ajax({
        url: MAGASINIER_CONTROLLER_URL, method: 'POST', data: { option: 4 }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') return;
        dga_renderTableInvest(res.data || []);
        document.getElementById('dga-stat-nombre-invest').textContent = res.nombre_total ?? 0;
    });
}

function dga_renderTableInvest(bons) {
    if (dga_tableInvest) { try { dga_tableInvest.destroy(); } catch (e) {} dga_tableInvest = null; }

    dga_tableInvest = $('#dga-table-livraison-invest').DataTable({
        data: bons,
        columns: [
            { data: 'numero_bon' },
            { data: 'nom_expression' },
            { data: null },
            { data: 'dateSortie' },
            { data: 'nombre_lignes_a_livrer' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => `<strong>${dga_escapeHtml(d)}</strong>` },
            { targets: 1, render: d => dga_escapeHtml(d) },
            { targets: 2, render: (d, t, row) => dga_escapeHtml(row.code_direction || row.nom_direction || '—') },
            { targets: 3, render: d => dga_fmtDate(d) },
            {
                targets: 5,
                render: (d) => `
                    <button type="button" class="dga-btn-avis" onclick="dga_ouvrirLivraisonInvest('${d}')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                        Préparer / Livrer
                    </button>
                `,
            },
        ],
        order: [[3, 'asc']],
        language: {
            emptyTable: 'Aucun produit Investissement à livrer pour le moment.',
            zeroRecords: 'Aucun résultat.',
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { previous: 'Précédent', next: 'Suivant' },
        },
    });
}

function dga_ouvrirLivraisonInvest(token) {
    dga_typeLivraisonActif = 'investissement';
    dga_ouvrirLivraison(token);
}

/* ────────────────────────── DÉTAIL / CONFIRMATION ────────────────── */
function dga_ouvrirLivraisonFonctionnement(token) {
    dga_typeLivraisonActif = 'fonctionnement';
    dga_ouvrirLivraison(token);
}

function dga_ouvrirLivraison(token) {
    dga_showLoader('Chargement du détail…');
    const optionDetail = dga_typeLivraisonActif === 'investissement' ? 5 : 2;

    $.ajax({
        url: MAGASINIER_CONTROLLER_URL, method: 'POST', data: { option: optionDetail, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        dga_tokenCourant = token;
        const e = res.bon;
        document.getElementById('livraisonModalTitre').textContent = 'Livraison — ' + e.numero_bon;
        const infoActeur = dga_typeLivraisonActif === 'investissement'
            ? `<strong>Direction :</strong> ${dga_escapeHtml(e.code_direction || e.nom_direction || '—')}`
            : `<strong>Demandeur :</strong> ${dga_escapeHtml(e.demandeur || '—')}`;
        document.getElementById('dgaInfoDemandeur').innerHTML = `<strong>Expression de besoin :</strong> ${dga_escapeHtml(e.nom_expression)}<br/>${infoActeur}`;
        document.getElementById('dgaErreurLivraison').style.display = 'none';

        const lignes = (e.lignes || []).filter(l => !l.entierement_livree);
        document.getElementById('dgaCorpsLivraison').innerHTML = lignes.length
            ? lignes.map(function (l) {
                return `
                    <tr>
                        <td>${dga_escapeHtml(l.designation)}</td>
                        <td>${dga_fmtNombre(l.quantite_livree)}</td>
                        <td>${dga_fmtNombre(l.quantite_restante_a_livrer)}</td>
                        <td>
                            <input type="number" class="dga-inp-qte-livraison" data-id="${l.idBSL}" data-max="${l.quantite_restante_a_livrer}"
                                   value="${l.quantite_restante_a_livrer}" readonly disabled
                                   title="Quantité fixée par la sortie du comptable — non modifiable"/>
                        </td>
                    </tr>
                `;
            }).join('')
            : '<tr><td colspan="4" style="text-align:center;color:#9ca3af;font-style:italic;">Toutes les lignes sont déjà entièrement livrées.</td></tr>';

        new bootstrap.Modal(document.getElementById('modalLivraison')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_confirmerLivraison() {
    const erreurBox = document.getElementById('dgaErreurLivraison');
    erreurBox.style.display = 'none';

    const quantites = [];
    let auMoinsUne = false;
    let depassement = false;
    document.querySelectorAll('.dga-inp-qte-livraison').forEach(function (input) {
        const val = parseFloat(input.value) || 0;
        const max = parseFloat(input.dataset.max) || 0;
        if (val > max + 0.001) depassement = true;
        if (val > 0) auMoinsUne = true;
        quantites.push({ idBSL: input.dataset.id, quantite: val });
    });

    if (depassement) {
        erreurBox.textContent = 'La quantité saisie ne peut jamais dépasser ce qui a été sorti dans ce bon pour une ligne.';
        erreurBox.style.display = 'block';
        return;
    }
    if (!auMoinsUne) {
        erreurBox.textContent = 'Veuillez saisir au moins une quantité à remettre.';
        erreurBox.style.display = 'block';
        return;
    }

    Swal.fire({
        title: 'Confirmer la remise au demandeur',
        text: 'Confirmez-vous avoir physiquement remis ces produits au demandeur ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, remis',
        confirmButtonColor: '#1a7a5e',
        cancelButtonText: 'Annuler',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        const btn = document.getElementById('dgaBtnConfirmerLivraison');
        btn.disabled = true;
        btn.querySelector('.dga-spinner').classList.remove('hidden');

        const optionConfirmer = dga_typeLivraisonActif === 'investissement' ? 6 : 3;

        $.ajax({
            url: MAGASINIER_CONTROLLER_URL,
            method: 'POST',
            data: JSON.stringify({ option: optionConfirmer, token: dga_tokenCourant, quantites: quantites }),
            contentType: 'application/json',
            dataType: 'json',
        }).done(function (res) {
            btn.disabled = false;
            btn.querySelector('.dga-spinner').classList.add('hidden');

            if (res.status === 'success') {
                const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalLivraison'));
                if (modalInstance) modalInstance.hide();
                Swal.fire({ title: 'Succès', text: res.message || 'Livraison enregistrée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                if (dga_typeLivraisonActif === 'investissement') chargerExpressionsInvest(); else chargerExpressions();
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

/* ────────────────────────────── UTILITAIRES ────────────────────── */
function dga_showLoader(msg = 'Chargement…') {
    $('#dga-loader').remove();
    $('body').append(`
        <div id="dga-loader">
            <div class="dga-loader-bg"></div>
            <div class="dga-loader-box">
                <svg class="dga-loader-spin" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/></svg>
                <p>${msg}</p>
            </div>
        </div>`);
}
function dga_hideLoader() { $('#dga-loader').remove(); }

function dga_fmtDate(d) { return d ? new Date(d.replace(' ', 'T')).toLocaleDateString('fr-FR') : '—'; }

function dga_fmtNombre(v) {
    const n = parseFloat(v);
    return isNaN(n) ? dga_escapeHtml(v) : String(n);
}

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