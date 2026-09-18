/**
 * caissier-paiement-scripts.bundle.js
 * Page "Paiement des commandes" (profil Caissier) : liste des commandes
 * d'achat/paiement à régler, écran de paiement (complet ou par tranche),
 * vérification du solde journalier (Liquide/Wave/Orange Money), et
 * statistiques de la journée.
 */

const PAIEMENT_CONTROLLER_URL = '/personnel/caisse_basi_controller'; // ← ajuster selon le chemin réel

let dga_table = null;
let dga_commandeCourante = null; // token de la commande en cours de paiement
let dga_soldeSuffisant = true;

/* ────────────────────── LIEN "ARRÊT DE CAISSE" (confirmation) ──────── */
function dga_confirmerArreteCaisseLien(event, url, dejaEffectue) {
    event.preventDefault();

    Swal.fire({
        title: "Arrêt de caisse",
        text: dejaEffectue
            ? "Consulter le rapport d'arrêt de caisse déjà effectué aujourd'hui ?"
            : "Confirmez-vous vouloir accéder à l'arrêt de caisse ? Cette action clôturera définitivement la caisse pour aujourd'hui une fois validée.",
        icon: dejaEffectue ? 'info' : 'warning',
        showCancelButton: true,
        confirmButtonText: 'Continuer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: dejaEffectue ? '#1a7a5e' : '#dc2626',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (result.isConfirmed) {
            window.open(url, '_blank');
        }
    });

    return false;
}

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]);
    chargerCommandes();
    chargerStats();

    document.getElementById('dgaSubmitPaiement')?.addEventListener('click', soumettrePaiement);
});

let dga_arreteEffectue = false;

/* ────────────────────────── CHARGEMENT LISTE ───────────────────────── */
function chargerCommandes() {
    dga_showLoader('Chargement des commandes…');

    $.ajax({
        url: PAIEMENT_CONTROLLER_URL, method: 'POST', data: { option: 5 }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les commandes.', 'error');
            return;
        }
        dga_arreteEffectue = !!res.arreteEffectue;

        const banniere = document.getElementById('dga-alerte-arrete');
        if (banniere) {
            banniere.innerHTML = dga_arreteEffectue
                ? `<div class="dga-alerte-arrete-caisse">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        L'arrêt de caisse a déjà été effectué aujourd'hui — aucun nouveau paiement ne peut être enregistré.
                   </div>`
                : '';
        }

        dga_renderTable(res.data || []);
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── CHARGEMENT STATS ───────────────────────── */
function chargerStats() {
    $.ajax({
        url: PAIEMENT_CONTROLLER_URL, method: 'POST', data: { option: 8 }, dataType: 'json'
    }).done(function (res) {
        if (res.status !== 'success') return;

        const s = res.stats || {};
        const c = res.compteurs || {};

        ['liquide', 'wave', 'om'].forEach(function (cle) {
            const donnees = s[cle] || {};
            const prefixe = cle === 'liquide' ? 'liq' : cle;
            document.getElementById(`dga-${prefixe}-alloue`).textContent  = dga_formatMontant(donnees.alloue || 0);
            document.getElementById(`dga-${prefixe}-utilise`).textContent = dga_formatMontant(donnees.utilise || 0);
            document.getElementById(`dga-${prefixe}-solde`).textContent   = dga_formatMontant(donnees.solde || 0);
        });

        document.getElementById('dga-cnt-traitees').textContent   = c.traitees_jour ?? 0;
        document.getElementById('dga-cnt-partielles').textContent = c.partielles ?? 0;
        document.getElementById('dga-cnt-attente').textContent    = c.en_attente ?? 0;
    });
}

/* ───────────────────────────── TABLE ────────────────────────────── */
function dga_renderTable(commandes) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-commandes').DataTable({
        data: commandes,
        columns: [
            { data: 'numero' },
            { data: 'nom_commande' },
            { data: 'idTypePAP' },
            { data: 'montant_total' },
            { data: 'montant_paye' },
            { data: 'montant_restant' },
            { data: 'mode_reglement_nom' },
            { data: 'idStatut' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => dga_escapeHtml(d) },
            {
                targets: 2,
                render: d => parseInt(d) === 1
                    ? '<span class="dga-badge-type dga-type-1">Achat</span>'
                    : '<span class="dga-badge-type dga-type-2">Paiement</span>',
            },
            { targets: 3, render: d => '<span class="dga-cell-amount">' + dga_formatMontant(d) + '</span>' },
            { targets: 4, render: d => dga_formatMontant(d) },
            { targets: 5, render: d => '<span class="dga-cell-amount">' + dga_formatMontant(d) + '</span>' },
            { targets: 6, render: d => d ? dga_escapeHtml(d) : '—' },
            {
                targets: 7,
                render: d => parseInt(d) === 4
                    ? '<span class="dga-badge-statut dga-statut-4">Acceptée</span>'
                    : '<span class="dga-badge-statut dga-statut-6">En paiement</span>',
            },
            {
                targets: 8,
                render: d => dga_arreteEffectue
                    ? `<button type="button" class="dga-btn-payer" disabled style="opacity:.5;cursor:not-allowed;" title="Arrêt de caisse déjà effectué aujourd'hui">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            Payer
                       </button>`
                    : `<button type="button" class="dga-btn-payer" onclick="dga_ouvrirPaiement('${d}')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            Payer
                       </button>`,
            },
        ],
        order: [[0, 'desc']],
        language: {
            emptyTable: 'Aucune commande à payer.',
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

/* ────────────────────────── OUVERTURE MODALE PAIEMENT ──────────────── */
function dga_ouvrirPaiement(token) {
    dga_showLoader('Chargement du détail…');

    $.ajax({
        url: PAIEMENT_CONTROLLER_URL, method: 'POST', data: { option: 6, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        dga_commandeCourante = token;
        const c = res.commande;
        const estAchat = parseInt(c.idTypePAP) === 1;

        document.getElementById('dgaModalTitre').textContent = 'Paiement — ' + c.numero;
        document.getElementById('dgaErreurGenerale').style.display = 'none';

        document.getElementById('dgaPaiementInfo').innerHTML = `
            <div class="dga-paiement-item"><span class="lbl">Type</span><span class="val">${estAchat ? 'Achat' : 'Paiement'}</span></div>
            <div class="dga-paiement-item"><span class="lbl">Mode de règlement</span><span class="val">${dga_escapeHtml(c.mode_reglement_nom || '—')}</span></div>
            <div class="dga-paiement-item"><span class="lbl">Modalité de paiement</span><span class="val">${dga_escapeHtml(c.mode_paiement_nom || '—')}</span></div>
            <div class="dga-paiement-item"><span class="lbl">Montant restant dû</span><span class="val">${dga_formatMontant(res.montant_restant)}</span></div>
        `;

        document.getElementById('dgaMontantLabel').textContent = res.tranche
            ? `Montant à régler — Tranche n°${res.tranche.ordre} (${res.tranche.pourcentage}%)`
            : 'Montant à régler';
        document.getElementById('dgaMontantValeur').textContent = dga_formatMontant(res.montant_a_payer);

        // Bloc solde journalier (Liquide/Wave/OM) OU bloc Banque (Chèque/Virement)
        const blocBanque = document.getElementById('dgaBlocBanque');
        const soldeInfo = document.getElementById('dgaSoldeInfo');
        if (res.solde_requis) {
            blocBanque.style.display = 'none';
            dga_soldeSuffisant = res.solde_disponible >= res.montant_a_payer;
            soldeInfo.innerHTML = `
                <div class="dga-solde-info ${dga_soldeSuffisant ? 'dga-solde-ok' : 'dga-solde-insuffisant'}">
                    ${dga_soldeSuffisant
                ? `Solde disponible aujourd'hui : ${dga_formatMontant(res.solde_disponible)}`
                : `Solde insuffisant (${dga_formatMontant(res.solde_disponible)} disponible) — paiement impossible.`}
                </div>
            `;
        } else {
            dga_soldeSuffisant = true;
            soldeInfo.innerHTML = '';
            blocBanque.style.display = 'block';
            remplirSelectBanques(res.banques || []);
            document.getElementById('dgaNumeroChequeVirement').value = '';
        }

        document.getElementById('dgaRecu').value = '';

        new bootstrap.Modal(document.getElementById('modalPaiement')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function remplirSelectBanques(banques) {
    const sel = document.getElementById('dgaBanque');
    sel.innerHTML = '<option value="">Sélectionner…</option>' +
        banques.map(b => `<option value="${dga_escapeHtml(b.nom)}">${dga_escapeHtml(b.nom)}</option>`).join('');
}

/* ────────────────────────── SOUMISSION ─────────────────────────────── */
function soumettrePaiement() {
    const erreurBox = document.getElementById('dgaErreurGenerale');
    erreurBox.style.display = 'none';

    if (!dga_soldeSuffisant) {
        erreurBox.textContent = 'Solde disponible insuffisant pour ce mode de règlement aujourd\'hui.';
        erreurBox.style.display = 'block';
        return;
    }

    const blocBanqueVisible = document.getElementById('dgaBlocBanque').style.display !== 'none';
    let banque = '', numeroChequeVirement = '';
    if (blocBanqueVisible) {
        banque = document.getElementById('dgaBanque').value;
        numeroChequeVirement = document.getElementById('dgaNumeroChequeVirement').value.trim();
        if (!banque || !numeroChequeVirement) {
            erreurBox.textContent = 'La banque et le numéro du chèque/virement sont obligatoires.';
            erreurBox.style.display = 'block';
            return;
        }
    }

    const fd = new FormData();
    fd.append('option', 7);
    fd.append('token', dga_commandeCourante);
    fd.append('banque', banque);
    fd.append('numero_cheque_virement', numeroChequeVirement);
    const fichierRecu = document.getElementById('dgaRecu').files[0];
    if (fichierRecu) fd.append('recu', fichierRecu);

    const btn = document.getElementById('dgaSubmitPaiement');
    btn.disabled = true;
    btn.querySelector('.dga-spinner').classList.remove('hidden');

    $.ajax({
        url: PAIEMENT_CONTROLLER_URL,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
    }).done(function (res) {
        btn.disabled = false;
        btn.querySelector('.dga-spinner').classList.add('hidden');

        if (res.status === 'success') {
            const modalEl = document.getElementById('modalPaiement');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            Swal.fire({ title: 'Succès', text: res.message || 'Paiement enregistré avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
            chargerCommandes();
            chargerStats();
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