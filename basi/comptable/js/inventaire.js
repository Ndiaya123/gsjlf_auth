/**
 * liste-inventaire-scripts.bundle.js
 * Page "Inventaire" (profil Comptable) : création (bloquée si un inventaire
 * etat=1 existe déjà), liste, validation (idStatut 3→4), détail + rapport PDF.
 */

const INVENTAIRE_CONTROLLER_URL = '/personnel/cpt_caisse_basi_controller'; // ← ajuster selon le chemin réel

const LIBELLES_STATUT_INV = { 1: 'Créé', 2: 'Brouillon', 3: 'Soumis', 4: 'Terminé' };

let dga_table = null;
let dga_tokenCourant = null;

document.addEventListener('DOMContentLoaded', function () {
    dga_renderTable([]);
    chargerInventaires();

    document.getElementById('dga-btn-nouvel-inventaire')?.addEventListener('click', dga_creerInventaire);
    document.getElementById('dgaBtnConfirmerValiderInv')?.addEventListener('click', dga_confirmerValidation);
});

/* ────────────────────────── CHARGEMENT LISTE ───────────────────────── */
function chargerInventaires() {
    dga_showLoader('Chargement des inventaires…');

    $.ajax({
        url: INVENTAIRE_CONTROLLER_URL, method: 'POST', data: { option: 30 }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger les inventaires.', 'error');
            return;
        }

        const banniere = document.getElementById('dga-alerte-active');
        const btnNouvel = document.getElementById('dga-btn-nouvel-inventaire');
        if (res.inventaireActif) {
            banniere.style.display = 'flex';
            document.getElementById('dga-alerte-active-texte').textContent =
                `Un inventaire est déjà en cours (${res.inventaireActif.reference}) : impossible d'en créer un nouveau tant qu'il n'est pas terminé.`;
            btnNouvel.disabled = true;
        } else {
            banniere.style.display = 'none';
            btnNouvel.disabled = false;
        }

        dga_renderTable(res.data || []);
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

/* ────────────────────────── CRÉATION ───────────────────────────────── */
function dga_creerInventaire() {
    Swal.fire({
        title: 'Créer un nouvel inventaire',
        text: 'Toutes les fiches produits (type Consommable) seront générées automatiquement avec leur stock actuel. Confirmez-vous ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Créer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#1a7a5e',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        dga_showLoader("Création de l'inventaire…");
        $.ajax({
            url: INVENTAIRE_CONTROLLER_URL, method: 'POST', data: { option: 29 }, dataType: 'json'
        }).done(function (res) {
            dga_hideLoader();
            if (res.status === 'success') {
                Swal.fire({ title: 'Succès', text: res.message || 'Inventaire créé avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerInventaires();
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        }).fail(function (xhr) {
            dga_hideLoader();
            Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
        });
    });
}

/* ───────────────────────────── TABLE ────────────────────────────── */
function dga_renderTable(inventaires) {
    if (dga_table) { try { dga_table.destroy(); } catch (e) {} dga_table = null; }

    dga_table = $('#dga-table-inventaires').DataTable({
        data: inventaires,
        columns: [
            { data: 'reference' },
            { data: 'dateDebut' },
            { data: 'createur' },
            { data: 'nombre_produits' },
            { data: 'idStatut' },
            { data: 'tmp', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: 0, render: d => dga_escapeHtml(d) },
            { targets: 1, render: d => dga_fmtDateHeure(d) },
            { targets: 2, render: d => dga_escapeHtml(d) },
            { targets: 4, render: d => `<span class="dga-badge-statut dga-statut-${d}">${LIBELLES_STATUT_INV[d] || d}</span>` },
            {
                targets: 5,
                render: (d, t, row) => {
                    const statut = parseInt(row.idStatut);
                    let html = '';
                    if (statut === 1 || statut === 2) {
                        html += `<a href="/personnel/compta_basi_inventaire-pdf-vierge/${d}" target="_blank" class="dga-btn-pdf">PDF vierge</a>`;
                    }
                    if (statut === 3) {
                        html += `<button type="button" class="dga-btn-valider" onclick="dga_ouvrirValidation('${d}')">Valider</button>`;
                    }
                    if (statut === 4) {
                        html += `<button type="button" class="dga-btn-detail" onclick="dga_ouvrirDetail('${d}')">Détail</button>`;
                    }
                    return html || '<span style="color:#d1d5db;">—</span>';
                },
            },
        ],
        order: [[1, 'desc']],
        language: {
            emptyTable: 'Aucun inventaire à afficher.',
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

/* ────────────────────────── VALIDATION ─────────────────────────────── */
function dga_ouvrirValidation(token) {
    dga_showLoader('Chargement du détail…');
    $.ajax({
        url: INVENTAIRE_CONTROLLER_URL, method: 'POST', data: { option: 31, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        dga_tokenCourant = token;
        const inv = res.inventaire;
        document.getElementById('validerInvModalTitre').textContent = 'Valider — ' + inv.reference;
        document.getElementById('dgaErreurValiderInv').style.display = 'none';
        document.getElementById('dgaObservationComptable').value = '';

        document.getElementById('dgaCorpsValiderInv').innerHTML = (inv.lignes || []).map(function (l) {
            const defaut = l.quantite_operateur !== null ? l.quantite_operateur : l.quantite_systeme;
            return `
                <tr>
                    <td>${dga_escapeHtml(l.nom_categorie)}</td>
                    <td>${dga_escapeHtml(l.nom_sous_categorie)}</td>
                    <td>${dga_escapeHtml(l.nomproduit)}</td>
                    <td>${dga_escapeHtml(l.quantite_systeme)}</td>
                    <td>${l.quantite_operateur !== null ? dga_escapeHtml(l.quantite_operateur) : '—'}</td>
                    <td><input type="number" class="dga-inp-qte-valide" data-idip="${l.idIP}" min="0" step="0.01" value="${defaut}"/></td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="6" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit.</td></tr>';

        new bootstrap.Modal(document.getElementById('modalValiderInventaire')).show();
    }).fail(function (xhr) {
        dga_hideLoader();
        Swal.fire('Erreur', dga_ajaxErrorMessage(xhr), 'error');
    });
}

function dga_confirmerValidation() {
    const erreurBox = document.getElementById('dgaErreurValiderInv');
    erreurBox.style.display = 'none';

    const observation = document.getElementById('dgaObservationComptable').value.trim();
    if (!observation) {
        erreurBox.textContent = "L'observation est obligatoire pour valider l'inventaire.";
        erreurBox.style.display = 'block';
        return;
    }

    const inputsVides = Array.from(document.querySelectorAll('.dga-inp-qte-valide')).filter(input => input.value === '');
    if (inputsVides.length > 0) {
        erreurBox.textContent = `Impossible de valider : ${inputsVides.length} produit(s) n'ont pas de quantité validée renseignée.`;
        erreurBox.style.display = 'block';
        inputsVides.forEach(input => { input.style.borderColor = '#dc2626'; });
        return;
    }

    const lignes = [];
    document.querySelectorAll('.dga-inp-qte-valide').forEach(function (input) {
        lignes.push({ idIP: input.dataset.idip, quantite_valide: input.value });
    });

    Swal.fire({
        title: "Confirmer la validation",
        text: "Cette action met à jour le stock de chaque produit avec la quantité validée, et clôture l'inventaire. Confirmez-vous ?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Confirmer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#1a7a5e',
        cancelButtonColor: '#6b7280',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        const btn = document.getElementById('dgaBtnConfirmerValiderInv');
        btn.disabled = true;
        btn.querySelector('.dga-spinner').classList.remove('hidden');

        $.ajax({
            url: INVENTAIRE_CONTROLLER_URL,
            method: 'POST',
            data: JSON.stringify({
                option: 32,
                token: dga_tokenCourant,
                lignes: lignes,
                observation_comptable: document.getElementById('dgaObservationComptable').value.trim(),
            }),
            contentType: 'application/json',
            dataType: 'json',
        }).done(function (res) {
            btn.disabled = false;
            btn.querySelector('.dga-spinner').classList.add('hidden');

            if (res.status === 'success') {
                const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalValiderInventaire'));
                if (modalInstance) modalInstance.hide();
                Swal.fire({ title: 'Succès', text: res.message || 'Inventaire validé avec succès.', icon: 'success', confirmButtonColor: '#1a7a5e' });
                chargerInventaires();
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

/* ────────────────────────── DÉTAIL ─────────────────────────────────── */
function dga_ouvrirDetail(token) {
    dga_showLoader('Chargement du détail…');
    $.ajax({
        url: INVENTAIRE_CONTROLLER_URL, method: 'POST', data: { option: 31, token: token }, dataType: 'json'
    }).done(function (res) {
        dga_hideLoader();
        if (res.status !== 'success') {
            Swal.fire('Erreur', res.message || 'Impossible de charger le détail.', 'error');
            return;
        }

        const inv = res.inventaire;
        document.getElementById('detailInvModalTitre').textContent = 'Détail — ' + inv.reference;
        document.getElementById('dgaLienRapportPdf').href = '/personnel/compta_basi_inventaire_rapport_pdf/' + encodeURIComponent(token);

        const lignes = (inv.lignes || []).map(function (l) {
            let badge = '<span style="color:#d1d5db;">—</span>';
            if (l.constat === 'Conforme') badge = '<span class="dga-badge-conforme">Conforme</span>';
            else if (l.constat === 'Excédent') badge = '<span class="dga-badge-excedent">Excédent</span>';
            else if (l.constat === 'Déficitaire') badge = '<span class="dga-badge-deficit">Déficitaire</span>';
            return `
                <tr>
                    <td>${dga_escapeHtml(l.nom_categorie)}</td>
                    <td>${dga_escapeHtml(l.nom_sous_categorie)}</td>
                    <td>${dga_escapeHtml(l.nomproduit)}</td>
                    <td>${dga_escapeHtml(l.quantite_systeme)}</td>
                    <td>${l.quantite_operateur !== null ? dga_escapeHtml(l.quantite_operateur) : '—'}</td>
                    <td>${l.quantite_valide !== null ? dga_escapeHtml(l.quantite_valide) : '—'}</td>
                    <td>${badge}</td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="7" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun produit.</td></tr>';

        document.getElementById('dgaContenuDetailInv').innerHTML = `
            <div class="dga-info-grid">
                <div class="dga-info-item"><span class="lbl">Date de création</span><span class="val">${dga_fmtDateHeure(inv.dateEnregistrement)}</span></div>
                <div class="dga-info-item"><span class="lbl">Date de début</span><span class="val">${dga_fmtDateHeure(inv.dateDebut)}</span></div>
                <div class="dga-info-item"><span class="lbl">Date de soumission (opérateur)</span><span class="val">${inv.dateSoumission ? dga_fmtDateHeure(inv.dateSoumission) : '—'}</span></div>
                <div class="dga-info-item"><span class="lbl">Date de validation (comptable)</span><span class="val">${inv.dateFin ? dga_fmtDateHeure(inv.dateFin) : '—'}</span></div>
                <div class="dga-info-item"><span class="lbl">Date de fin</span><span class="val">${inv.dateFin ? dga_fmtDateHeure(inv.dateFin) : '—'}</span></div>
                <div class="dga-info-item"><span class="lbl">Créé par</span><span class="val">${dga_escapeHtml(inv.createur)}</span></div>
            </div>
            ${inv.observation_operateur ? `<p style="font-size:.82rem;color:#374151;margin-bottom:.5rem;"><strong>Observation opérateur :</strong> ${dga_escapeHtml(inv.observation_operateur)}</p>` : ''}
            ${inv.observation_comptable ? `<p style="font-size:.82rem;color:#374151;margin-bottom:1rem;"><strong>Observation comptable :</strong> ${dga_escapeHtml(inv.observation_comptable)}</p>` : ''}
            <table class="dga-table-produits">
                <thead><tr><th>Catégorie</th><th>Sous-catégorie</th><th>Produit</th><th>Qté système</th><th>Qté opérateur</th><th>Qté validée</th><th>Constat</th></tr></thead>
                <tbody>${lignes}</tbody>
            </table>
        `;

        new bootstrap.Modal(document.getElementById('modalDetailInventaire')).show();
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

function dga_fmtDateHeure(d) {
    if (!d) return '—';
    const dt = new Date(d.replace(' ', 'T'));
    return dt.toLocaleDateString('fr-FR') + ' à ' + dt.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
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