/**
 * receptionProduits.js
 * Page "Réception des produits" (profil Personnel/demandeur) : liste, en un
 * seul endroit, tous les bons — Fonctionnement et Investissement confondus —
 * ayant des lignes remises par le magasinier mais pas encore confirmées.
 *
 * Volontairement simple pour l'instant : la quantité confirmée est TOUJOURS
 * la totalité de ce qui reste sur le bon (aucune saisie, aucune option
 * "Non reçu" — masquée temporairement, à la demande). Pour signaler un
 * écart, revenir pour l'instant à la modale "Voir" habituelle.
 */

const EB_CONTROLLER_URL = '/personnel/personnel_basi_controller';

let dga_table = null;
let dga_tokenCourant = null;

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]);
    chargerBons();
    document.getElementById('dgaBtnConfirmerReceptionBon')?.addEventListener('click', dga_confirmerReceptionBon);
});

/* ────────────────────────── CHARGEMENT LISTE ─────────────────────── */
function chargerBons() {
    dga_showLoader('Chargement…');
    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 20 }, dataType: 'json'
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

function dga_renderTable(bons) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-reception').DataTable({
        data: bons,
        columns: [
            { data: 'numero_bon' },
            { data: 'type' },
            { data: 'nom_expression' },
            { data: 'dateSortie' },
            { data: 'nombre_lignes_a_confirmer' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => `<strong>${dga_escapeHtml(d)}</strong>` },
            {
                targets: 1,
                render: d => d === 'investissement'
                    ? '<span class="dga-badge-type dga-badge-type-i">Investissement</span>'
                    : '<span class="dga-badge-type dga-badge-type-f">Fonctionnement</span>',
            },
            { targets: 2, render: d => dga_escapeHtml(d) },
            { targets: 3, render: d => dga_fmtDate(d) },
            {
                targets: 5,
                render: (d) => `
                    <button type="button" class="dga-btn-avis" onclick="dga_ouvrirReception('${d}')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                        Confirmer réception
                    </button>
                `,
            },
        ],
        order: [[3, 'asc']],
        language: {
            emptyTable: 'Aucune réception en attente pour le moment.',
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

/* ────────────────────────── DÉTAIL / CONFIRMATION ────────────────── */
function dga_ouvrirReception(token) {
    dga_showLoader('Chargement du détail…');

    $.ajax({
        url: EB_CONTROLLER_URL, method: 'POST', data: { option: 21, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        dga_tokenCourant = token;
        const b = res.bon;
        document.getElementById('receptionModalTitre').textContent = 'Confirmer — ' + b.numero_bon;
        document.getElementById('dgaErreurReception').style.display = 'none';

        // Quantité BLOQUÉE : simple affichage, jamais un champ modifiable —
        // c'est toujours l'intégralité de ce qui a été remis qui est confirmée.
        document.getElementById('dgaCorpsReception').innerHTML = (b.lignes || []).length
            ? b.lignes.map(l => `
                <tr>
                    <td>${dga_escapeHtml(l.designation)}</td>
                    <td><span class="dga-qte-fixe">${dga_escapeHtml(l.quantite_restante_a_recevoir)}</span></td>
                </tr>
            `).join('')
            : '<tr><td colspan="2" style="text-align:center;color:#9ca3af;font-style:italic;">Rien à confirmer sur ce bon.</td></tr>';

        new bootstrap.Modal(document.getElementById('modalReception')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_confirmerReceptionBon() {
    const erreurBox = document.getElementById('dgaErreurReception');
    erreurBox.style.display = 'none';

    Swal.fire({
        title: 'Confirmer la réception',
        text: 'Confirmez-vous avoir bien reçu l\'ensemble de ces produits ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, reçu',
        confirmButtonColor: '#1a7a5e',
        cancelButtonText: 'Annuler',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        const btn = document.getElementById('dgaBtnConfirmerReceptionBon');
        btn.disabled = true;
        btn.querySelector('.dga-spinner').classList.remove('hidden');

        $.ajax({
            url: EB_CONTROLLER_URL, method: 'POST', data: { option: 22, token: dga_tokenCourant }, dataType: 'json'
        }).done(function (res) {
            btn.disabled = false;
            btn.querySelector('.dga-spinner').classList.add('hidden');

            if (res.status === 'success') {
                const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalReception'));
                if (modalInstance) modalInstance.hide();
                Swal.fire({ title: 'Succès', text: res.message || 'Réception confirmée avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e', timer: 1500, showConfirmButton: false });
                chargerBons();
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