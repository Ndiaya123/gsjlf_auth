/**
 * listeFournisseur.js
 * Logique métier de la page de gestion des fournisseurs.
 * Dépend de jQuery, DataTables et SweetAlert2 (déjà chargés par la page).
 *
 * Le contrôleur (drh_basi_controller) route ses actions via le
 * paramètre "option" et répond toujours avec { status: 'success'|'error', ... }.
 */

const CONTROLLER_URL = '/personnel/drh_basi_controller';

let allFournisseurs = [];
let currentTab = 'tous';
let dataTable = null;

document.addEventListener('DOMContentLoaded', function () {
    initTable();
    initTabs();
    initFilters();
    initModal();
    loadFournisseurs();
});

/* ─────────────────────────── CHARGEMENT ────────────────────────── */
function loadFournisseurs() {
    $.ajax({
        url: CONTROLLER_URL,
        method: 'POST',
        data: { option: 1 }, // 1 = listerFournisseurs
        dataType: 'json'
    }).done(function (res) {
        try {
            if (res.status !== 'success') {
                showError(res.message || "Erreur lors du chargement des fournisseurs.");
                return;
            }
            allFournisseurs = res.data || [];
            updateStatsCards(res.stats);
            populateVilleFilter(allFournisseurs);
            renderTable();
        } catch (err) {
            console.error('Erreur lors du traitement de la liste des fournisseurs :', err);
            showError("Une erreur inattendue est survenue lors de l'affichage des fournisseurs.");
        }
    }).fail(function (xhr) {
        showError(ajaxErrorMessage(xhr));
    });
}

/* ──────────────────────── CARTES STATISTIQUES ──────────────────── */
function updateStatsCards(stats) {
    if (!stats) return;
    setText('stats-tous-count', stats.tous ?? 0);
    setText('stats-actif-count', stats.actif ?? 0);
    setText('stats-inactif-count', stats.inactif ?? 0);
    setText('count-tous', stats.tous ?? 0);
    setText('count-actif', stats.actif ?? 0);
    setText('count-inactif', stats.inactif ?? 0);
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

/* ────────────────────────────── ONGLETS ────────────────────────── */
function initTabs() {
    document.querySelectorAll('.four-tabs-bar button[data-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.four-tabs-bar button[data-tab]').forEach(function (b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            currentTab = btn.getAttribute('data-tab');
            renderTable();
        });
    });
}

/* ────────────────────────────── FILTRES ────────────────────────── */
function initFilters() {
    document.getElementById('resetFiltersF').addEventListener('click', function () {
        document.getElementById('filter_ville').value = '';
        renderTable();
    });
    document.getElementById('filter_ville').addEventListener('change', renderTable);
}

function populateVilleFilter(rows) {
    const select = document.getElementById('filter_ville');
    const current = select.value;
    const villes = Array.from(new Set(rows.map(r => (r.ville || '').trim()).filter(Boolean))).sort();

    select.innerHTML = '<option value="">Toutes les villes</option>';
    villes.forEach(function (v) {
        const opt = document.createElement('option');
        opt.value = v;
        opt.textContent = v;
        select.appendChild(opt);
    });
    if (villes.includes(current)) select.value = current;
}

/* ──────────────────────────── FILTRAGE ─────────────────────────── */
function getFilteredRows() {
    let rows = allFournisseurs;

    if (currentTab !== 'tous') {
        rows = rows.filter(r => r.statut === currentTab);
    }

    const ville = document.getElementById('filter_ville').value;
    if (ville) {
        rows = rows.filter(r => (r.ville || '') === ville);
    }

    return rows;
}

/* ─────────────────────────── DATATABLE ─────────────────────────── */
function initTable() {
    dataTable = $('#fournisseurTable').DataTable({
        data: [],
        columns: [
            { data: 'nomF' },
            { data: 'prenomF' },
            { data: 'entreprise' },
            { data: 'ville' },
            { data: 'telF' },
            { data: 'emailF' },
            { data: 'statut' },
            { data: null, orderable: false, searchable: false }
        ],
        columnDefs: [
            {
                targets: 0,
                render: function (data) {
                    return '<span class="four-cell-name">' + escapeHtml(data || '') + '</span>';
                }
            },
            {
                targets: [2, 3, 4],
                render: function (data) {
                    return data ? escapeHtml(data) : '<span class="four-cell-muted">—</span>';
                }
            },
            {
                targets: 5,
                render: function (data) {
                    return data ? escapeHtml(data) : '<span class="four-cell-muted">—</span>';
                }
            },
            {
                targets: 6,
                render: function (data) {
                    return data === 'actif'
                        ? '<span class="status-badge status-actif">Actif</span>'
                        : '<span class="status-badge status-inactif">Inactif</span>';
                }
            },
            {
                targets: 7,
                render: function (data, type, row) {
                    const toggleIcon = row.statut === 'actif'
                        ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
                        : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4"><polyline points="20 6 9 17 4 12"/></svg>';
                    const toggleClass = row.statut === 'actif' ? 'btn-action-warning' : 'btn-action-success';
                    const toggleTitle = row.statut === 'actif' ? 'Désactiver' : 'Activer';

                    return '' +
                        '<div class="flex justify-end gap-2">' +
                        '<button class="btn-action btn-action-primary" data-action="edit" data-id="' + row.idF + '" title="Modifier">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
                        '</button>' +
                        '<button class="btn-action ' + toggleClass + '" data-action="toggle" data-id="' + row.idF + '" title="' + toggleTitle + '">' +
                        toggleIcon +
                        '</button>' +
                        '<button class="btn-action btn-action-primary" data-action="historique" data-id="' + row.idF + '" title="Historique">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
                        '</button>' +
                        '<button class="btn-action btn-action-danger" data-action="delete" data-id="' + row.idF + '" title="Supprimer">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m5 0V4a2 2 0 012-2h0a2 2 0 012 2v2"/></svg>' +
                        '</button>' +
                        '</div>';
                }
            }
        ],
        language: {
            emptyTable: "Aucun fournisseur trouvé.",
            zeroRecords: "Aucun résultat ne correspond à votre recherche.",
            search: "Rechercher :",
            lengthMenu: "Afficher _MENU_ entrées",
            info: "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
            infoEmpty: "Aucune entrée",
            paginate: { previous: "Précédent", next: "Suivant" }
        }
    });

    $('#fournisseurTable tbody').on('click', 'button[data-action]', function () {
        const id = $(this).data('id');
        const action = $(this).data('action');
        if (action === 'edit') openEditModal(id);
        if (action === 'toggle') confirmToggleStatus(id);
        if (action === 'delete') confirmDelete(id);
        if (action === 'historique') openHistoriqueModal(id);
    });
}

function renderTable() {
    try {
        const rows = getFilteredRows();
        dataTable.clear();
        dataTable.rows.add(rows);
        dataTable.draw();
    } catch (err) {
        console.error('Erreur lors de l\'affichage du tableau des fournisseurs :', err);
        showError("Une erreur est survenue lors de l'affichage du tableau.");
    }
}

/* ────────────────────────────── MODALE ─────────────────────────── */
function initModal() {
    const modalEl = document.getElementById('fournisseurModal');
    const modal = new bootstrap.Modal(modalEl);

    document.getElementById('openModalBtnF').addEventListener('click', function () {
        resetForm();
        document.getElementById('modalLabelF').textContent = 'Créer un Fournisseur';
        document.getElementById('submitButtonF').textContent = 'Créer le Fournisseur';
        modal.show();
    });

    document.getElementById('cancelModalBtnF').addEventListener('click', function () {
        modal.hide();
    });

    document.getElementById('fournisseurForm').addEventListener('submit', function (e) {
        e.preventDefault();
        submitForm(modal);
    });

    // Exposé pour openEditModal()
    window.__fournisseurModal = modal;
}

function resetForm() {
    document.getElementById('fournisseurForm').reset();
    document.getElementById('idF').value = '';
    clearAllErrors();
}

function clearAllErrors() {
    ['nomF', 'prenomF', 'telF', 'emailF', 'entreprise', 'ville', 'adresseF'].forEach(function (field) {
        const errEl = document.getElementById(field + '-error');
        const inpEl = document.getElementById(field);
        if (errEl) { errEl.textContent = ''; errEl.classList.add('hidden'); }
        if (inpEl) inpEl.classList.remove('border-red-500');
    });
}

function showFieldErrors(errors) {
    clearAllErrors();
    Object.keys(errors || {}).forEach(function (field) {
        const errEl = document.getElementById(field + '-error');
        const inpEl = document.getElementById(field);
        if (errEl) { errEl.textContent = errors[field]; errEl.classList.remove('hidden'); }
        if (inpEl) inpEl.classList.add('border-red-500');
    });
}

function openEditModal(idF) {
    const row = allFournisseurs.find(r => String(r.idF) === String(idF));
    if (!row) return;

    resetForm();
    document.getElementById('idF').value = row.idF;
    document.getElementById('nomF').value = row.nomF || '';
    document.getElementById('prenomF').value = row.prenomF || '';
    document.getElementById('telF').value = row.telF || '';
    document.getElementById('emailF').value = row.emailF || '';
    document.getElementById('entreprise').value = row.entreprise || '';
    document.getElementById('ville').value = row.ville || '';
    document.getElementById('adresseF').value = row.adresseF || '';

    document.getElementById('modalLabelF').textContent = 'Modifier le Fournisseur';
    document.getElementById('submitButtonF').textContent = 'Enregistrer les modifications';

    window.__fournisseurModal.show();
}

function submitForm(modal) {
    const idF = document.getElementById('idF').value;
    const isEdit = !!idF;
    const optionCode = isEdit ? 3 : 2; // 2 = ajouterFournisseur, 3 = modifierFournisseur

    const formData = new FormData(document.getElementById('fournisseurForm'));
    formData.append('option', optionCode);

    toggleSubmitSpinner(true);

    $.ajax({
        url: CONTROLLER_URL,
        method: 'POST',
        data: Object.fromEntries(formData.entries()),
        dataType: 'json'
    }).done(function (res) {
        toggleSubmitSpinner(false);

        try {
            if (res.status !== 'success') {
                if (res.errors) {
                    showValidationErrors(res.errors);
                } else {
                    showError(res.message || "Une erreur est survenue.");
                }
                return;
            }

            modal.hide();
            showSuccess(res.message || (isEdit ? "Fournisseur modifié." : "Fournisseur créé."));
            loadFournisseurs();
        } catch (err) {
            console.error('Erreur lors du traitement de la réponse du formulaire :', err);
            showError("Une erreur inattendue est survenue.");
        }
    }).fail(function (xhr) {
        toggleSubmitSpinner(false);
        showError(ajaxErrorMessage(xhr));
    });
}

function toggleSubmitSpinner(loading) {
    const spinner = document.getElementById('submitSpinnerF');
    const btn = document.getElementById('submitFournisseurBtn');
    if (loading) {
        spinner.classList.remove('hidden');
        btn.disabled = true;
    } else {
        spinner.classList.add('hidden');
        btn.disabled = false;
    }
}

/* ──────────────────────── ACTIVER / DÉSACTIVER ─────────────────── */
function confirmToggleStatus(idF) {
    const row = allFournisseurs.find(r => String(r.idF) === String(idF));
    if (!row) return;

    const willActivate = row.statut !== 'actif';
    const label = (row.prenomF || '') + ' ' + (row.nomF || '');

    Swal.fire({
        title: willActivate ? 'Activer ce fournisseur ?' : 'Désactiver ce fournisseur ?',
        text: label.trim() + (willActivate
            ? ' redeviendra actif et apparaîtra dans les listes actives.'
            : ' sera désactivé et n\'apparaîtra plus comme actif.'),
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: willActivate ? 'Oui, activer' : 'Oui, désactiver',
        cancelButtonText: 'Annuler',
        confirmButtonColor: willActivate ? '#059669' : '#d97706'
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: CONTROLLER_URL,
            method: 'POST',
            data: { option: 5, idF: idF }, // 5 = activerDesactiverFournisseur
            dataType: 'json'
        }).done(function (res) {
            try {
                if (res.status !== 'success') {
                    showError(res.message || "Erreur lors du changement de statut.");
                    return;
                }
                showSuccess(res.message);
                loadFournisseurs();
            } catch (err) {
                console.error('Erreur lors du traitement du changement de statut :', err);
                showError("Une erreur inattendue est survenue.");
            }
        }).fail(function (xhr) {
            showError(ajaxErrorMessage(xhr));
        });
    });
}

/* ────────────────────────────── SUPPRESSION ────────────────────── */
function confirmDelete(idF) {
    const row = allFournisseurs.find(r => String(r.idF) === String(idF));
    if (!row) return;

    const label = ((row.prenomF || '') + ' ' + (row.nomF || '')).trim();

    Swal.fire({
        title: 'Supprimer ce fournisseur ?',
        text: label + ' ne sera plus visible sur la plateforme (listes, statistiques, recherche).',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#dc2626'
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: CONTROLLER_URL,
            method: 'POST',
            data: { option: 4, idF: idF }, // 4 = supprimerFournisseur
            dataType: 'json'
        }).done(function (res) {
            try {
                if (res.status !== 'success') {
                    showError(res.message || "Erreur lors de la suppression.");
                    return;
                }
                showSuccess(res.message);
                loadFournisseurs();
            } catch (err) {
                console.error('Erreur lors du traitement de la suppression :', err);
                showError("Une erreur inattendue est survenue.");
            }
        }).fail(function (xhr) {
            showError(ajaxErrorMessage(xhr));
        });
    });
}

/* ────────────────────────────── HISTORIQUE ─────────────────────── */
let historiqueModalInstance = null;

function getHistoriqueModal() {
    if (!historiqueModalInstance) {
        historiqueModalInstance = new bootstrap.Modal(document.getElementById('historiqueModal'));
    }
    return historiqueModalInstance;
}

function openHistoriqueModal(idF) {
    const row = allFournisseurs.find(r => String(r.idF) === String(idF));
    const label = row ? ((row.prenomF || '') + ' ' + (row.nomF || '')).trim() : '';

    document.getElementById('historiqueModalLabel').textContent = label
        ? 'Historique — ' + label
        : 'Historique du fournisseur';

    const content = document.getElementById('historiqueContent');
    content.innerHTML = '<p class="four-cell-muted">Chargement de l\'historique…</p>';

    const modal = getHistoriqueModal();
    modal.show();

    $.ajax({
        url: CONTROLLER_URL,
        method: 'POST',
        data: { option: 6, idF: idF }, // 6 = historiqueFournisseur
        dataType: 'json'
    }).done(function (res) {
        try {
            if (res.status !== 'success') {
                content.innerHTML = '<p class="four-cell-muted">Impossible de charger l\'historique.</p>';
                return;
            }
            renderHistorique(res.data || []);
        } catch (err) {
            console.error('Erreur lors du traitement de l\'historique :', err);
            content.innerHTML = '<p class="four-cell-muted">Une erreur inattendue est survenue lors de l\'affichage de l\'historique.</p>';
        }
    }).fail(function (xhr) {
        content.innerHTML = '<p class="four-cell-muted">' + escapeHtml(ajaxErrorMessage(xhr)) + '</p>';
    });
}

function renderHistorique(entries) {
    try {
        const content = document.getElementById('historiqueContent');

        if (!entries.length) {
            content.innerHTML = '<p class="four-cell-muted">Aucun historique disponible pour ce fournisseur.</p>';
            return;
        }

        let html = '';
        entries.forEach(function (e) {
            const statutBadge = e.statut === 'actif'
                ? '<span class="status-badge status-actif">Actif</span>'
                : '<span class="status-badge status-inactif">Inactif</span>';

            html += '' +
                '<div class="four-histo-item">' +
                '<div class="four-histo-dot"></div>' +
                '<div class="four-histo-body">' +
                '<div class="four-histo-motif">' + escapeHtml(e.motif || '') + '</div>' +
                '<div class="four-histo-date">' + formatDateTime(e.dateEnregistrement) + '</div>' +
                '<div class="four-histo-snapshot">' +
                '<span><strong>Nom :</strong> ' + escapeHtml((e.prenomF || '') + ' ' + (e.nomF || '')) + '</span>' +
                '<span><strong>Statut :</strong> ' + statutBadge + '</span>' +
                '<span><strong>Entreprise :</strong> ' + escapeHtml(e.entreprise || '—') + '</span>' +
                '<span><strong>Ville :</strong> ' + escapeHtml(e.ville || '—') + '</span>' +
                '<span><strong>Téléphone :</strong> ' + escapeHtml(e.telF || '—') + '</span>' +
                '<span><strong>Email :</strong> ' + escapeHtml(e.emailF || '—') + '</span>' +
                '</div>' +
                '</div>' +
                '</div>';
        });

        content.innerHTML = html;
    } catch (err) {
        console.error('Erreur lors du rendu de l\'historique :', err);
        const content = document.getElementById('historiqueContent');
        if (content) content.innerHTML = '<p class="four-cell-muted">Impossible d\'afficher l\'historique.</p>';
    }
}

function formatDateTime(value) {
    if (!value) return '—';
    const d = new Date(value.replace(' ', 'T'));
    if (isNaN(d.getTime())) return value;
    return d.toLocaleString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

/* ────────────────────────────── UTILITAIRES ────────────────────── */
function showSuccess(message) {
    Swal.fire({ title: 'Succès', text: message, icon: 'success', confirmButtonColor: '#1a7a5e' });
}

function showError(message) {
    Swal.fire({ title: 'Erreur', text: message, icon: 'error', confirmButtonColor: '#dc2626' });
}

/**
 * Construit un message d'erreur adapté au code HTTP renvoyé par le serveur.
 * Si le serveur a quand même renvoyé un message JSON (cas des erreurs
 * "métier" comme un doublon ou une session expirée), on le privilégie.
 */
function ajaxErrorMessage(xhr) {
    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
        return xhr.responseJSON.message;
    }
    if (!xhr || xhr.status === 0) {
        return "Impossible de contacter le serveur. Vérifiez votre connexion.";
    }
    switch (xhr.status) {
        case 400: return "Requête invalide. Merci de réessayer.";
        case 401: return "Votre session a expiré. Veuillez vous reconnecter.";
        case 403: return "Vous n'avez pas les droits nécessaires pour effectuer cette action.";
        case 404: return "Ressource introuvable.";
        case 409: return "Conflit détecté : cette information existe peut-être déjà.";
        case 500: return "Erreur interne du serveur. Merci de réessayer plus tard.";
        default:  return "Impossible de contacter le serveur (code " + xhr.status + ").";
    }
}

/**
 * Affiche les erreurs de validation/doublon sous chaque champ concerné,
 * ET un petit toast récapitulatif pour que l'utilisateur voie immédiatement
 * qu'il y a un problème (utile si le champ en erreur n'est pas visible
 * à l'écran, ex. modale déjà scrollée).
 */
function showValidationErrors(errors) {
    showFieldErrors(errors);

    const messages = Object.values(errors || {});
    if (!messages.length) return;

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'warning',
        title: messages.length === 1
            ? messages[0]
            : 'Merci de corriger les ' + messages.length + ' champs signalés.',
        showConfirmButton: false,
        timer: 4500,
        timerProgressBar: true
    });
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}