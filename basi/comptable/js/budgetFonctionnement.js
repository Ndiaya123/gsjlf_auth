// ═══════════════════════════════════════════════════════════════════════════
// MODULE BUDGET FONCTIONNEMENT — vue Comptable
// Le comptable voit TOUS les budgets de fonctionnement (direction_id = NULL,
// type transversal), avec les pleins pouvoirs : créer, modifier, valider,
// supprimer. Toutes les requêtes utilisent POST + body JSON.
// ═══════════════════════════════════════════════════════════════════════════

const API_BASE = '/personnel/compta_basi_controller';
const apiUrl = {
    liste:        `${API_BASE}?option=16`, // POST { annee? }                          → liste tous les budgets fonctionnement
    getById:      `${API_BASE}?option=17`, // POST { id }                              → un budget
    create:       `${API_BASE}?option=18`, // POST { annee, plafond }                  → créer
    update:       `${API_BASE}?option=19`, // POST { id, plafond?, statut?, motif? }   → maj
    budgetLines:  `${API_BASE}?option=20`, // POST { action:"lines",  budgetId }       → lignes
    deleteBudget: `${API_BASE}?option=20`, // POST { action:"delete", id }             → supprimer
    services:     `${API_BASE}?option=24`, // POST {}                                  → services (toutes directions)
    valider:      `${API_BASE}?option=25`, // POST { budgetId }                        → valider
};

const currentYear = new Date().getFullYear();

let currentTab = 'tous';
let globalStats = {
    tous:      { count: 0, plafond: 0 },
    encours:   { count: 0, plafond: 0 },
    valider:   { count: 0, plafond: 0 },
    accepter:  { count: 0, plafond: 0 },
    rejeter:   { count: 0, plafond: 0 },
    reajuster: { count: 0, plafond: 0 }
};
let dataTableInstance = null;

// ─── Helpers de base ──────────────────────────────────────────────────────────

function handleSessionExpired() {
    hideLoader();
    Swal.fire({
        icon: 'warning',
        title: 'Session expirée',
        text: 'Votre session a expiré. Vous allez être redirigé vers la page de connexion.',
        timer: 2500,
        showConfirmButton: false,
        didClose: () => { window.location.href = '/personnel/signin'; }
    });
}

function checkApiResponse(data) {
    if (data && data.code === 'sessionExpired') {
        handleSessionExpired();
        return false;
    }
    return true;
}

async function postJson(url, body = {}, timeout = 10000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeout);
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            signal: controller.signal
        });
        clearTimeout(timer);
        return response;
    } catch (error) {
        clearTimeout(timer);
        if (error.name === 'AbortError') throw new Error('Délai d\'attente dépassé. Veuillez réessayer.');
        throw error;
    }
}

// ─── LOADER ───────────────────────────────────────────────────────────────────
function showLoader(message) {
    message = message || 'Chargement en cours…';
    $('#global-loader').remove();
    $('body').append(
        '<div id="global-loader">' +
        '<div class="loader-backdrop"></div>' +
        '<div class="loader-box">' +
        '<svg class="loader-spinner" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/></svg>' +
        '<p>' + message + '</p>' +
        '</div></div>'
    );
}
function hideLoader() { $('#global-loader').remove(); }

// ─── DataTable ──────────────────────────────────────────────────────────────
function initializeDataTable() {
    if (dataTableInstance !== null) {
        try { dataTableInstance.destroy(); } catch (e) { console.warn('Erreur destruction DataTable:', e); }
        dataTableInstance = null;
    }
    if (!document.getElementById('budgetTable')) {
        console.error('La table #budgetTable n\'existe pas dans le DOM');
        return null;
    }

    dataTableInstance = $('#budgetTable').DataTable({
        responsive: true,
        paging: true,
        pageLength: 25,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,
        language: {
            emptyTable:        'Aucun budget disponible',
            info:              'Affichage de _START_ à _END_ sur _TOTAL_ budgets',
            infoEmpty:         'Aucun budget',
            infoFiltered:      '(filtré sur _MAX_)',
            lengthMenu:        'Afficher _MENU_ lignes',
            loadingRecords:    'Chargement…',
            processing:        'Traitement…',
            search:            '_INPUT_',
            searchPlaceholder: 'Rechercher...',
            zeroRecords:       'Aucun budget correspondant',
            paginate:          { first: '«', last: '»', next: '›', previous: '‹' }
        },
        dom: '<"bud-dt-top d-flex align-items-center justify-content-between"lf>rt<"bud-dt-bottom d-flex align-items-center justify-content-between"ip>',
        data: [],
        columns: [
            { data: 'annee', width: '70px' },
            { data: 'type_budget' },
            {
                data: 'plafond',
                render: function(data) {
                    if (!data) return '<span class="bud-cell-muted">N/A</span>';
                    return `<span class="bud-cell-amount">${new Intl.NumberFormat('fr-FR').format(data)}&nbsp;FCFA</span>`;
                }
            },
            {
                data: 'date_creation',
                render: function(data) {
                    return data ? new Date(data).toLocaleDateString('fr-FR') : '';
                },
                width: '90px'
            },
            {
                data: 'statut',
                render: function(data) {
                    let cls = 'status-badge ';
                    let label = data;
                    switch (data) {
                        case 'En cours':
                        case 'Sauvegarder':
                            cls += 'status-pending'; label = 'En cours'; break;
                        case 'Valider':   cls += 'status-validated'; break;
                        case 'Accepter':  cls += 'status-accepted';  break;
                        case 'Rejeter':   cls += 'status-rejected';  break;
                        case 'Réajuster': cls += 'status-reajuster'; label = 'Réajuster'; break;
                        case 'Terminer':  cls += 'status-validated'; label = 'Terminé'; break;
                        default:          cls += 'status-pending';
                    }
                    return `<span class="${cls}">${label}</span>`;
                },
                width: '100px'
            },
            {
                data: null,
                render: function(data, type, row) {
                    return getBudgetActions(row, row.lineCount || 0);
                },
                orderable: false,
                searchable: false,
                width: '110px'
            }
        ],
        initComplete: function () {

            document.documentElement.classList.remove('ld-booting');
            document.getElementById('lb-table')?.classList.add('lb-ready');

        }
    });

    return dataTableInstance;
}

// ─── Onglets ──────────────────────────────────────────────────────────────────
function initTabs() {
    const tabButtons = document.querySelectorAll('button[data-tab]');
    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            const tabName = this.getAttribute('data-tab');
            currentTab = tabName;
            tabButtons.forEach(btn => {
                btn.classList.remove('active', 'bg-white', 'border', 'border-b-0', 'border-gray-200', 'text-emerald-700');
                btn.classList.add('text-gray-600');
            });
            this.classList.remove('text-gray-600');
            this.classList.add('active', 'bg-white', 'border', 'border-b-0', 'border-gray-200', 'text-emerald-700');
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            const activeContent = document.querySelector(`.tab-content[data-tab="${tabName}"]`);
            if (activeContent) activeContent.classList.add('active');
            loadBudgetTableData();
        });
    });
}

// ─── Compteurs & statistiques ─────────────────────────────────────────────────
function updateTabCounters() {
    document.getElementById('count-tous').textContent      = globalStats.tous.count;
    document.getElementById('count-encours').textContent   = globalStats.encours.count;
    document.getElementById('count-valider').textContent   = globalStats.valider.count;
    document.getElementById('count-accepter').textContent  = globalStats.accepter.count;
    document.getElementById('count-rejeter').textContent   = globalStats.rejeter.count;
    const reajEl = document.getElementById('count-reajuster');
    if (reajEl) reajEl.textContent = globalStats.reajuster.count;
}

function updateAllStatsCards() {
    document.querySelectorAll('.tab-content').forEach(card => card.classList.remove('active'));
    const activeCard = document.querySelector(`.tab-content[data-tab="${currentTab}"]`);
    if (activeCard) activeCard.classList.add('active');

    document.getElementById('stats-tous-count').textContent      = globalStats.tous.count;
    document.getElementById('stats-tous-plafond').textContent    = globalStats.tous.plafond.toLocaleString('fr-FR') + ' FCFA';
    document.getElementById('stats-encours-count').textContent   = globalStats.encours.count;
    document.getElementById('stats-encours-plafond').textContent = globalStats.encours.plafond.toLocaleString('fr-FR') + ' FCFA';
    document.getElementById('stats-valider-count').textContent   = globalStats.valider.count;
    document.getElementById('stats-valider-plafond').textContent = globalStats.valider.plafond.toLocaleString('fr-FR') + ' FCFA';
    document.getElementById('stats-accepter-count').textContent  = globalStats.accepter.count;
    document.getElementById('stats-accepter-plafond').textContent= globalStats.accepter.plafond.toLocaleString('fr-FR') + ' FCFA';
    document.getElementById('stats-rejeter-count').textContent   = globalStats.rejeter.count;
    document.getElementById('stats-rejeter-plafond').textContent = globalStats.rejeter.plafond.toLocaleString('fr-FR') + ' FCFA';
    const srCount = document.getElementById('stats-reajuster-count');
    const srPlaf  = document.getElementById('stats-reajuster-plafond');
    if (srCount) srCount.textContent = globalStats.reajuster.count;
    if (srPlaf)  srPlaf.textContent  = globalStats.reajuster.plafond.toLocaleString('fr-FR') + ' FCFA';
}

// ─── Modal ────────────────────────────────────────────────────────────────────
function closeModal() {
    const modal = document.getElementById('budgetModal');
    if (!modal) return;
    if (typeof bootstrap !== 'undefined') {
        const inst = bootstrap.Modal.getInstance(modal);
        if (inst) { inst.hide(); return; }
    }
    modal.classList.add('hidden');
    resetForm();
}

function openModal() {
    const modal = document.getElementById('budgetModal');
    if (!modal) return;
    if (typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modal).show();
        return;
    }
    modal.classList.remove('hidden');
}

// ─── Dropdown type budget — fixé côté client (Fonctionnement uniquement) ──────
function populateTypeBudgetDropdown() {
    const typeSelect = document.getElementById('type_budget_id');
    if (!typeSelect) return;
    const defaultType = window.COMPTABLE_CONFIG?.defaultType || 1;
    typeSelect.innerHTML = `<option value="${defaultType}">Fonctionnement</option>`;
    typeSelect.value    = defaultType;
    typeSelect.disabled = true;
}

function populateYearDropdown(selectedYear = null) {
    const anneeSelect = document.getElementById('annee');
    if (!anneeSelect) return;
    let options = `<option value="" disabled ${!selectedYear ? 'selected' : ''}>Sélectionner une année</option>`;
    const years = [currentYear, currentYear + 1];
    if (selectedYear && !years.includes(parseInt(selectedYear))) years.push(parseInt(selectedYear));
    years.sort((a, b) => a - b);
    years.forEach(y => { options += `<option value="${y}" ${selectedYear == y ? 'selected' : ''}>${y}</option>`; });
    anneeSelect.innerHTML = options;
}

// ─── Nombre de lignes d'un budget — option=20 POST { action:"lines", budgetId } ──
async function getBudgetLineCount(budgetTmp) {
    try {
        const response = await postJson(apiUrl.budgetLines, { action: 'lines', budgetId: budgetTmp });
        if (!response.ok) return 0;
        const data = await response.json();
        if (!checkApiResponse(data)) return 0;
        return data.status === 'success' ? (data.lineCount || 0) : 0;
    } catch (error) {
        console.error('Erreur getBudgetLineCount:', error);
        return 0;
    }
}

// ─── Créer / Mettre à jour un budget ─────────────────────────────────────────
// option=18 create : POST { annee, plafond }
// option=19 update : POST { id, plafond }
// type_budget_id fixé côté serveur (Fonctionnement). direction_id toujours NULL.
async function addBudget(annee, type_budget_id, plafond, statut = 'En cours', id = null) {
    const isUpdate = !!id;
    const url      = isUpdate ? apiUrl.update : apiUrl.create;

    const payload = isUpdate
        ? { id: parseInt(id), plafond: plafond !== null ? parseFloat(plafond) : null }
        : { annee: parseInt(annee), plafond: plafond !== null ? parseFloat(plafond) : null };

    showLoader(isUpdate ? 'Mise à jour du budget…' : 'Création du budget…');
    try {
        const response = await postJson(url, payload);
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            if (!checkApiResponse(errorData)) return;
            let message = errorData.message || `Erreur HTTP: ${response.status}`;
            if (response.status === 404) message = "Budget non trouvé.";
            if (response.status === 500) message = "Erreur serveur.";
            throw new Error(message);
        }
        const data = await response.json();
        if (!checkApiResponse(data)) return;
        if (data.status === 'error') throw new Error(data.message || "Erreur lors de l'enregistrement.");
        hideLoader();
        await loadBudgetTableData();
        await Swal.fire({
            icon: 'success', title: 'Succès', text: data.message, timer: 1500, showConfirmButton: false
        });
        if (!isUpdate) resetForm();
        closeModal();
        return data;
    } catch (error) {
        hideLoader();
        console.error('Erreur addBudget:', error);
        Swal.fire({ icon: 'error', title: 'Erreur', text: error.message });
        throw error;
    }
}

// ─── Charger les données de la table ─────────────────────────────────────────
// option=16, POST { annee? } — comptable : tous les budgets fonctionnement,
// sans filtre direction (direction_id est NULL pour ce type de budget).
// Chargement des compteurs de lignes en PARALLÈLE (Promise.all) pour éviter
// les requêtes séquentielles qui ralentissent le rendu.
async function loadBudgetTableData() {
    showLoader('Chargement des budgets…');
    try {
        const filterAnneeSelect = document.getElementById('filter_annee');
        const filterAnnee = filterAnneeSelect?.value || '';

        const body = {};
        if (filterAnnee) body.annee = parseInt(filterAnnee);

        const response = await postJson(apiUrl.liste, body);
        if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
        const budgets = await response.json();
        if (!checkApiResponse(budgets)) return;
        if (budgets.status !== 'success' || !Array.isArray(budgets.data)) throw new Error('Réponse non valide');

        // Reset stats
        globalStats = {
            tous:      { count: 0, plafond: 0 }, encours:   { count: 0, plafond: 0 },
            valider:   { count: 0, plafond: 0 }, accepter:  { count: 0, plafond: 0 },
            rejeter:   { count: 0, plafond: 0 }, reajuster: { count: 0, plafond: 0 }
        };

        // Calcul des stats globales (sur TOUS les budgets, indépendamment de l'onglet actif)
        budgets.data.forEach(budget => {
            const statut  = budget.statut || '';
            const plafond = parseFloat(budget.plafond) || 0;
            globalStats.tous.count++; globalStats.tous.plafond += plafond;
            if (statut === 'En cours' || statut === 'Sauvegarder') {
                globalStats.encours.count++;  globalStats.encours.plafond  += plafond;
            } else if (statut === 'Valider') {
                globalStats.valider.count++;  globalStats.valider.plafond  += plafond;
            } else if (statut === 'Accepter') {
                globalStats.accepter.count++; globalStats.accepter.plafond += plafond;
            } else if (statut === 'Rejeter') {
                globalStats.rejeter.count++;  globalStats.rejeter.plafond  += plafond;
            } else if (statut === 'Réajuster') {
                globalStats.reajuster.count++; globalStats.reajuster.plafond += plafond;
            }
        });

        // Filtrer selon l'onglet actif
        const filtered = budgets.data.filter(budget => {
            const statut = budget.statut || '';
            if (currentTab === 'tous')      return true;
            if (currentTab === 'encours')   return statut === 'En cours' || statut === 'Sauvegarder';
            if (currentTab === 'valider')   return statut === 'Valider';
            if (currentTab === 'accepter')  return statut === 'Accepter';
            if (currentTab === 'rejeter')   return statut === 'Rejeter';
            if (currentTab === 'reajuster') return statut === 'Réajuster';
            return true;
        });

        // Compteurs de lignes en parallèle (un seul aller-retour groupé, pas séquentiel)
        const lineCounts = await Promise.all(filtered.map(b => getBudgetLineCount(b.tmp)));

        const tableData = filtered.map((budget, i) => ({
            annee:         budget.annee || '—',
            type_budget:   budget.type_budget_nom || 'Fonctionnement',
            plafond:       parseFloat(budget.plafond) || 0,
            date_creation: budget.date_creation || '',
            statut:        budget.statut || '',
            idStatut:      budget.idStatut || null,
            lineCount:     lineCounts[i],
            id:            budget.id,
            tmp:           budget.tmp,
        }));

        updateTabCounters();
        updateAllStatsCards();

        if (filterAnneeSelect && filterAnneeSelect.children.length <= 1) {
            const annees = [...new Set(budgets.data.map(b => b.annee))].sort((a, b) => a - b);
            filterAnneeSelect.innerHTML = '<option value="">Toutes les années</option>';
            annees.forEach(a => {
                const o = document.createElement('option'); o.value = a; o.textContent = a;
                filterAnneeSelect.appendChild(o);
            });
            if (filterAnnee) filterAnneeSelect.value = filterAnnee;
        }

        if (!dataTableInstance) initializeDataTable();
        if (dataTableInstance) {
            dataTableInstance.clear();
            if (tableData.length > 0) dataTableInstance.rows.add(tableData);
            dataTableInstance.draw();
        }

        hideLoader();

    } catch (error) {
        hideLoader();
        console.error('Erreur loadBudgetTableData:', error);
        Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur chargement budgets: ' + error.message });
    }
}

// ─── Supprimer un budget — option=20 POST { action:"delete", id } ────────────
async function deleteBudget(id) {
    try {
        const result = await Swal.fire({
            icon: 'warning', title: 'Confirmer la suppression',
            text: 'Voulez-vous vraiment supprimer ce budget ?',
            showCancelButton: true, confirmButtonText: 'Oui, supprimer', cancelButtonText: 'Annuler'
        });
        if (!result.isConfirmed) return;

        showLoader('Suppression du budget…');
        const response = await postJson(apiUrl.deleteBudget, { action: 'delete', id: parseInt(id) });
        if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
        const data = await response.json();
        if (!checkApiResponse(data)) return;
        if (data.status === 'error') throw new Error(data.message || "Erreur suppression.");
        hideLoader();
        await Swal.fire({
            icon: 'success', title: 'Succès', text: data.message || "Budget supprimé.",
            timer: 2000, showConfirmButton: false
        });
        await loadBudgetTableData();
    } catch (error) {
        hideLoader();
        console.error('Erreur deleteBudget:', error);
        Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur suppression: ' + error.message });
    }
}

// ─── Éditer un budget — option=17 POST { id } ─────────────────────────────────
async function editBudget(id, mode = 'plafond') {
    showLoader('Chargement du budget…');
    try {
        populateTypeBudgetDropdown();

        const response = await postJson(apiUrl.getById, { id: parseInt(id) });
        if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
        const result = await response.json();
        if (!checkApiResponse(result)) return;
        if (result.status === 'error' || !result.data) throw new Error(result.message || 'Budget non trouvé');

        const budget = result.data;
        const nonEditableStatuts = ['Terminer', 'Valider', 'Accepter'];
        if (nonEditableStatuts.includes(budget.statut))
            throw new Error(`Impossible de modifier un budget au statut "${budget.statut}".`);

        if (budget.statut === 'Réajuster') {
            const anneeInt = parseInt(budget.annee);
            const cy = new Date().getFullYear();
            if (anneeInt < cy)
                throw new Error(`Le statut "Réajuster" n'est applicable que pour des budgets de l'année ${cy} ou plus. Ce budget concerne ${budget.annee}.`);
        }

        const form = document.getElementById('budgetForm');
        populateYearDropdown(budget.annee);
        if (form.annee)          form.annee.value          = budget.annee;
        if (form.type_budget_id) form.type_budget_id.value = budget.type_budget_id;

        const plafondActuel = budget.plafond ? parseFloat(budget.plafond) : 0;
        if (form.plafond) form.plafond.value = plafondActuel > 0 ? plafondActuel.toFixed(2) : '';

        form.dataset.plafondActuel = plafondActuel;
        form.dataset.sommeLignes   = parseFloat(budget.somme_lignes || 0);

        const plafondHelp = document.getElementById('plafond-help');
        if (plafondHelp && plafondActuel > 0) {
            plafondHelp.textContent = `Plafond actuel : ${plafondActuel.toLocaleString('fr-FR')} FCFA — ne peut pas être diminué si des lignes actives/verrouillées existent.`;
            plafondHelp.classList.remove('hidden');
        }

        if (form.annee)          form.annee.disabled          = true;
        if (form.type_budget_id) form.type_budget_id.disabled = true;

        document.getElementById('submitButton').textContent = 'Mettre à jour le plafond';
        form.dataset.id = id;
        document.getElementById('modalLabel').textContent   = budget.statut === 'Réajuster'
            ? 'Réajuster le Plafond du Budget'
            : 'Modifier le Plafond du Budget';

        hideLoader();
        openModal();
    } catch (error) {
        hideLoader();
        console.error('Erreur editBudget:', error);
        Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur chargement budget: ' + error.message });
    }
}

// ─── Reset formulaire ─────────────────────────────────────────────────────────
function resetForm() {
    const form = document.getElementById('budgetForm');
    if (!form) return;
    form.reset();
    form.querySelectorAll('.border-red-500').forEach(el => el.classList.remove('border-red-500'));
    form.querySelectorAll('[id$="-error"]').forEach(el => { el.textContent = ''; el.classList.add('hidden'); });

    if (form.annee)          form.annee.disabled          = false;
    if (form.type_budget_id) form.type_budget_id.disabled = false;

    document.getElementById('submitButton').textContent = 'Créer le Budget';
    document.getElementById('modalLabel').textContent   = 'Créer un Budget de Fonctionnement';
    delete form.dataset.id;
    delete form.dataset.plafondActuel;
    delete form.dataset.sommeLignes;
    const plafondHelp = document.getElementById('plafond-help');
    if (plafondHelp) plafondHelp.classList.add('hidden');

    populateTypeBudgetDropdown();
}

// ─── Helpers erreurs ──────────────────────────────────────────────────────────
function showError(inputElement, errorElementId, message) {
    inputElement.classList.add('border-red-500');
    const errorElement = document.getElementById(errorElementId);
    if (errorElement) { errorElement.textContent = message; errorElement.classList.remove('hidden'); }
}

function resetFormErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(el => { el.textContent = ''; el.classList.add('hidden'); });
}

// ─── Boutons d'action dans la table ──────────────────────────────────────────
// Le comptable a les pleins pouvoirs : pas de restriction par direction.
function getBudgetActions(budget, lineCount) {
    const { id, tmp, statut, annee, plafond } = budget;
    let actions = '<div class="flex justify-end gap-2">';

    const anneeInt = parseInt(annee);
    const cy = new Date().getFullYear();
    const isEditableYear = anneeInt >= cy;

    if (statut === 'En cours' || statut === 'Sauvegarder' || statut === 'Rejeter') {
        actions += `
            <button onclick="poursuivreBudget('${tmp}')" class="btn-action btn-action-primary" title="Ajouter / voir les lignes">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
            <button onclick="editBudget(${id}, 'plafond')" class="btn-action btn-action-warning" title="Modifier le plafond">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>`;
        if (lineCount > 0 && parseFloat(plafond) > 0) {
            actions += `
                <button onclick="valider_budget(${id})" class="btn-action btn-action-success" title="Valider le budget">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </button>`;
        }

    } else if (statut === 'Réajuster') {
        if (isEditableYear) {
            actions += `
                <button onclick="poursuivreBudget('${tmp}')" class="btn-action btn-action-primary" title="Ajouter / voir les lignes">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </button>
                <button onclick="editBudget(${id}, 'plafond')" class="btn-action btn-action-warning" title="Modifier le plafond">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>`;

            if (lineCount > 0 && parseFloat(plafond) > 0) {
                actions += `
                <button onclick="valider_budget(${id})" class="btn-action btn-action-success" title="Valider le budget">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </button>`;
            }
        } else {
            actions += `
                <button onclick="voirBudget('${tmp}')" class="btn-action btn-action-primary" title="Voir (année passée)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>`;
        }

    } else if (statut === 'Terminer') {
        actions += `
            <button onclick="redirectionVersPageGraphics('${tmp}')" class="btn-action btn-action-primary" title="Rapport">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </button>`;

    } else {
        actions += `
            <button onclick="voirBudget('${tmp}')" class="btn-action btn-action-primary" title="Voir">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>`;
    }

    const nonDeletable = ['Valider', 'Accepter', 'Réajuster', 'Terminer'];
    if (!nonDeletable.includes(statut)) {
        actions += `
            <button onclick="deleteBudget(${id})" class="btn-action btn-action-danger" title="Supprimer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>`;
    }

    actions += '</div>';
    return actions;
}

// ─── Redirections ─────────────────────────────────────────────────────────────
// Page lignes propre au module fonctionnement comptable (route, pas .php direct)
//function redirectionVersPageGraphics(id) { window.location.href = `Consulter_Budget_Fonctionnement.php?id=${id}`; }
function redirectionVersPageGraphics(id) { window.location.href = `/personnel/compta_ligne_budget_fonctionnement/${id}`; }
function poursuivreBudget(id)            { window.location.href = `/personnel/compta_ligne_budget_fonctionnement/${id}`; }
function voirBudget(id)                  { window.location.href = `/personnel/compta_ligne_budget_fonctionnement/${id}`; }

// ─── Valider un budget — option=25 POST { budgetId } ──────────────────────────
// Utilise le même endpoint JSON que le reste du module (cohérent, plus de
// form-urlencoded séparé vers un autre contrôleur).
function valider_budget(id) {
    const row = dataTableInstance?.rows().data().toArray().find(r => String(r.id) === String(id));
    const tmp = row?.tmp;
    if (!tmp) { Swal.fire('Erreur', 'Budget introuvable.', 'error'); return; }

    Swal.fire({
        title: 'Confirmer la validation', text: 'Voulez-vous vraiment valider ce budget ?',
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Oui, valider', cancelButtonText: 'Annuler'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        try {
            showLoader('Validation du budget…');
            const response = await postJson(apiUrl.valider, { budgetId: tmp });
            if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
            const data = await response.json();
            if (!checkApiResponse(data)) return;
            if (!data.success) throw new Error(data.message || "Erreur validation.");
            hideLoader();
            await Swal.fire({
                icon: 'success', title: 'Succès', text: data.message || 'Budget validé avec succès.',
                timer: 1500, showConfirmButton: false
            });
            await loadBudgetTableData();
            closeModal();
        } catch (error) {
            hideLoader();
            console.error('Erreur valider_budget:', error);
            Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur validation: ' + error.message });
        }
    });
}

// ─── Initialisation ───────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initializeDataTable();
    populateTypeBudgetDropdown();
    loadBudgetTableData();

    document.getElementById('openModalBtn')?.addEventListener('click', () => {
        resetForm();
        populateYearDropdown();
        openModal();
    });
    document.getElementById('cancelModalBtn')?.addEventListener('click', closeModal);

    const modalEl = document.getElementById('budgetModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        modalEl.addEventListener('hidden.bs.modal', () => resetForm());
    }

    document.getElementById('applyFilters')?.addEventListener('click', () => loadBudgetTableData());
    document.getElementById('resetFilters')?.addEventListener('click', () => {
        const fa = document.getElementById('filter_annee');
        if (fa) { fa.value = ''; fa.innerHTML = '<option value="">Toutes les années</option>'; }
        loadBudgetTableData();
    });

    document.getElementById('budgetForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;

        const annee          = parseInt(form.annee?.value);
        const type_budget_id = parseInt(form.type_budget_id?.value);
        const plafondValue   = form.plafond?.value.trim() || '';
        const plafond        = plafondValue ? parseFloat(plafondValue) : null;
        const id             = form.dataset.id || null;

        resetFormErrors();
        let isValid = true;

        if (!id) {
            if (!form.annee?.value) {
                showError(form.annee, 'annee-error', 'L\'année est requise.'); isValid = false;
            } else if (isNaN(annee)) {
                showError(form.annee, 'annee-error', 'L\'année doit être un nombre valide.'); isValid = false;
            } else if (annee < currentYear) {
                showError(form.annee, 'annee-error', `L'année doit être ${currentYear} ou plus.`); isValid = false;
            } else if (annee > currentYear + 10) {
                showError(form.annee, 'annee-error', 'L\'année ne peut pas excéder 10 ans dans le futur.'); isValid = false;
            }
        }

        if (!plafondValue) {
            showError(form.plafond, 'plafond-error', 'Le plafond est requis.'); isValid = false;
        } else if (isNaN(plafond) || plafond <= 0 || plafond > 1000000000) {
            showError(form.plafond, 'plafond-error', 'Le plafond doit être entre 0 et 1 000 000 000.'); isValid = false;
        } else if (id) {
            const plafondActuel = parseFloat(form.dataset.plafondActuel || 0);
            const sommeLignes   = parseFloat(form.dataset.sommeLignes   || 0);
            if (sommeLignes > 0 && plafond < plafondActuel) {
                const fmt = n => n.toLocaleString('fr-FR', {minimumFractionDigits:0});
                showError(form.plafond, 'plafond-error',
                    `Impossible de diminuer le plafond (${fmt(plafondActuel)} FCFA) : des lignes actives/verrouillées sont rattachées. Minimum : ${fmt(plafondActuel)} FCFA.`
                );
                isValid = false;
            } else if (sommeLignes > 0 && plafond < sommeLignes) {
                const fmt = n => n.toLocaleString('fr-FR', {minimumFractionDigits:0});
                showError(form.plafond, 'plafond-error',
                    `Le plafond ne peut pas être inférieur au total des lignes (${fmt(sommeLignes)} FCFA).`
                );
                isValid = false;
            }
        }

        if (!isValid) return;

        const submitBtn = document.getElementById('submitBudgetBtn');
        submitBtn.disabled = true;
        const originalText = document.getElementById('submitButton').textContent;
        document.getElementById('submitButton').textContent = 'Enregistrement…';
        document.getElementById('submitSpinner')?.classList.remove('hidden');
        try {
            await addBudget(annee, type_budget_id, plafond, 'En cours', id);
        } catch (error) {
            console.error('Erreur soumission:', error);
        } finally {
            submitBtn.disabled = false;
            document.getElementById('submitButton').textContent = originalText;
            document.getElementById('submitSpinner')?.classList.add('hidden');
        }
    });
});