// ─── URLs ─────────────────────────────────────────────────────────────────────
// Toutes les requêtes utilisent POST + body JSON.
// L'option est dans l'URL (query string), le payload dans le body.
const API_BASE = '/personnel/chef_service_basi_controller';
const apiUrl = {
    liste:        `${API_BASE}?option=10`, // POST  { annee? }             → liste budgets direction
    getById:      `${API_BASE}?option=11`, // POST  { id }                 → un budget
    create:       `${API_BASE}?option=12`, // POST  { annee, plafond }     → créer
    update:       `${API_BASE}?option=13`, // POST  { id, plafond?, statut?, motif? } → maj
    budgetLines:  `${API_BASE}?option=14`, // POST  { action:"lines",  budgetId }     → lignes
    deleteBudget: `${API_BASE}?option=14`, // POST  { action:"delete", id }           → Supprimer
    directions:   `${API_BASE}?option=15`, // POST  { action:"directions" }
    typesList:    `${API_BASE}?option=16`, // POST  {}                    → types budget
    typeById:     `${API_BASE}?option=17`, // POST  { id }               → un type budget
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

// Session expirée → alerte + redirect
function handleSessionExpired() {
    hideLoader();
    Swal.fire({
        icon: 'warning',
        title: 'Session expirée',
        text: 'Votre session a expiré. Vous allez être redirigé vers la page de connexion.',
        timer: 2500,
        showConfirmButton: false,
        didClose: () => { window.location.href = '/signin'; }
    });
}

// Vérification centralisée des réponses API
function checkApiResponse(data) {
    if (data && data.code === 'sessionExpired') {
        handleSessionExpired();
        return false;
    }
    return true;
}

// Fetch POST JSON centralisé
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
        '<div class="loader-spinner">' +
        '<svg viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/></svg>' +
        '</div>' +
        '<p class="loader-msg">' + message + '</p>' +
        '</div>' +
        '</div>'
    );
    if (!$('#loader-style').length) {
        $('head').append(
            '<style id="loader-style">' +
            '#global-loader{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center}' +
            '.loader-backdrop{position:absolute;inset:0;background:rgba(4,20,11,.55);backdrop-filter:blur(4px);animation:loaderFadeIn .2s ease}' +
            '.loader-box{position:relative;z-index:1;background:#fff;border-radius:20px;padding:36px 48px;display:flex;flex-direction:column;align-items:center;gap:18px;box-shadow:0 0 0 1px rgba(17,59,38,.12),0 24px 60px rgba(0,0,0,.20);animation:loaderSlideUp .25s cubic-bezier(.34,1.56,.64,1);min-width:220px}' +
            '.loader-spinner svg{width:48px;height:48px;animation:loaderRotate .9s linear infinite}' +
            '.loader-spinner circle{stroke:#113B26;stroke-linecap:round;stroke-dasharray:80;stroke-dashoffset:60;animation:loaderDash 1.4s ease-in-out infinite}' +
            '.loader-msg{margin:0;font-size:.85rem;font-weight:600;color:#113B26;letter-spacing:.02em;text-align:center;opacity:.85}' +
            '@keyframes loaderFadeIn{from{opacity:0}to{opacity:1}}' +
            '@keyframes loaderSlideUp{from{opacity:0;transform:translateY(16px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}' +
            '@keyframes loaderRotate{to{transform:rotate(360deg)}}' +
            '@keyframes loaderDash{0%{stroke-dashoffset:80}50%{stroke-dashoffset:20}100%{stroke-dashoffset:80}}' +
            '</style>'
        );
    }
}

function hideLoader() {
    $('#global-loader').remove();
}

// ─── DataTable ────────────────────────────────────────────────────────────────
function initializeDataTable() {
    if (dataTableInstance !== null) {
        try { dataTableInstance.destroy(); } catch (e) { console.warn('Erreur destruction DataTable:', e); }
        dataTableInstance = null;
    }
    if (!document.getElementById('budgetTable')) {
       // console.error('La table #budgetTable n\'existe pas dans le DOM');
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
            search:            '',
            searchPlaceholder: 'Rechercher…',
            zeroRecords:       'Aucun budget correspondant',
            paginate:          { first: '«', last: '»', next: '›', previous: '‹' }
        },
        dom: '<"bud-dt-top"lf>rt<"bud-dt-bottom"ip>',
        data: [],
        columns: [
            { data: 'annee', width: '70px' },
            { data: 'type_budget' },
            { data: 'direction' },
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

// ─── Dropdown type budget — option=16, POST ───────────────────────────────────
async function populateTypeBudgetDropdown() {
    try {
        const response = await postJson(apiUrl.typesList, {});
        if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
        const data = await response.json();
        if (!checkApiResponse(data)) return;
        if (data.status !== 'success' || !Array.isArray(data.data)) throw new Error("Réponse invalide.");

        const typeSelect = document.getElementById('type_budget_id');
        if (!typeSelect) return;
        typeSelect.innerHTML = '<option value="" disabled selected>Investissement</option>';
        data.data.forEach(type => {
            if (window.COMPTABLE_CONFIG?.defaultType) {
                if (parseInt(type.id) !== parseInt(window.COMPTABLE_CONFIG.defaultType)) return;
            }
            const option = document.createElement('option');
            option.value = type.id; option.textContent = type.nom;
            typeSelect.appendChild(option);
        });
        if (window.COMPTABLE_CONFIG?.defaultType) {
            typeSelect.value    = window.COMPTABLE_CONFIG.defaultType;
            typeSelect.disabled = true;
        }
    } catch (error) {
      //  console.error('Erreur populateTypeBudgetDropdown:', error);
    }
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

// option=17, POST { id }
async function getTypeBudgetById(typeBudgetId) {
    try {
        if (!typeBudgetId || isNaN(typeBudgetId) || typeBudgetId <= 0) return null;
        const response = await postJson(apiUrl.typeById, { id: typeBudgetId });
        if (!response.ok) return null;
        const result = await response.json();
        if (!checkApiResponse(result)) return null;
        if (result.status === 'error' || !result.data) return null;
        return { id: result.data.id, nom: result.data.nom };
    } catch (error) {
        //console.error(`Erreur getTypeBudgetById ${typeBudgetId}:`, error);
        return null;
    }
}

// option=14, POST { action:"lines", budgetId }
async function getBudgetLineCount(budgetId, budgetTmp) {
    try {
        // option=14 attend le token chiffré (tmp), pas l'id en clair
        const tokenOrId = budgetTmp || budgetId;
        const response = await postJson(apiUrl.budgetLines, { action: 'lines', budgetId: tokenOrId });
        if (!response.ok) return 0;
        const data = await response.json();
        if (!checkApiResponse(data)) return 0;
        return data.status === 'success' ? (data.lineCount || 0) : 0;
    } catch (error) {
       // console.error('Erreur getBudgetLineCount:', error);
        return 0;
    }
}

// ─── Créer / Mettre à jour un budget ─────────────────────────────────────────
// option=12 create : POST { annee, plafond }
// option=13 update : POST { id, plafond }
// type_budget_id, direction_id, matricule → gérés côté serveur (session)
async function addBudget(annee, type_budget_id, plafond, statut = 'En cours', id = null) {
    const isUpdate = !!id;
    const url      = isUpdate ? apiUrl.update : apiUrl.create;

    // Pour la création : annee + plafond (type_budget_id ignoré, fixé côté serveur)
    // Pour la mise à jour (plafond seulement) : id + plafond
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
            if (response.status === 404) message = "Budget non trouvé ou accès non autorisé.";
            if (response.status === 500) message = "Erreur serveur.";
            throw new Error(message);
        }
        const data = await response.json();
        if (!checkApiResponse(data)) return;
        if (data.status === 'error') throw new Error(data.message || "Erreur lors de l'enregistrement.");
        hideLoader();
        loadBudgetTableData();
        Swal.fire({
            icon: 'success', title: 'Succès', text: data.message, timer: 1500,
            didClose: () => { if (!isUpdate) resetForm(); closeModal(); }
        });
        return data;
    } catch (error) {
        hideLoader();
      //  console.error('Erreur addBudget:', error);
        Swal.fire({ icon: 'error', title: 'Erreur', text: error.message });
        throw error;
    }
}

// ─── Charger les données de la table ─────────────────────────────────────────
// option=10, POST { annee? }
// Direction et type imposés côté serveur — aucun paramètre envoyé pour ces valeurs.
async function loadBudgetTableData() {
    showLoader('Chargement des budgets…');
    try {
        const filterAnneeSelect = document.getElementById('filter_annee');
        const filterAnnee = filterAnneeSelect?.value || '';

        // Body minimal : uniquement le filtre année si renseigné
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

        const tableDataPromises = budgets.data.map(async budget => {
            const statut  = budget.statut || '';
            const plafond = parseFloat(budget.plafond) || 0;

            // Statistiques globales
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

            // Filtre onglet actif
            let shouldInclude = false;
            if      (currentTab === 'tous')     shouldInclude = true;
            else if (currentTab === 'encours')  shouldInclude = statut === 'En cours' || statut === 'Sauvegarder';
            else if (currentTab === 'valider')  shouldInclude = statut === 'Valider';
            else if (currentTab === 'accepter') shouldInclude = statut === 'Accepter';
            else if (currentTab === 'rejeter')  shouldInclude = statut === 'Rejeter';
            else if (currentTab === 'reajuster') shouldInclude = statut === 'Réajuster';
            if (!shouldInclude) return null;

            // Nom du type via option=17 POST
            const typeBudgetData = await getTypeBudgetById(budget.type_budget_id);
            // Nb lignes via option=14 POST action=lines
            const lineCount      = await getBudgetLineCount(budget.id, budget.tmp);
            // Direction retournée directement par la requête SQL (nom_direction)
            const directionName  = budget.nom_direction || budget.direction || '';

            return {
                annee:        budget.annee       || '—',
                type_budget:  typeBudgetData ? typeBudgetData.nom : budget.type_budget_nom || 'Investissement',
                direction:    directionName,
                plafond:      plafond,
                date_creation:budget.date_creation || '',
                statut:       statut,
                idStatut:     budget.idStatut    || null,
                lineCount:    lineCount,
                id:           budget.id,
                tmp:           budget.tmp
            };
        });

        const tableData = (await Promise.all(tableDataPromises)).filter(item => item !== null);

        updateTabCounters();
        updateAllStatsCards();

        // Remplir filtre années (une seule fois)
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
      //  console.error('Erreur loadBudgetTableData:', error);
        Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur chargement budgets: ' + error.message });
    }
}

// ─── Supprimer un budget — option=14 POST { action:"delete", id } ─────────────
async function deleteBudget(id) {
    try {
        const result = await Swal.fire({
            icon: 'warning', title: 'Confirmer la suppression',
            text: 'Voulez-vous vraiment Supprimer ce budget ?',
            showCancelButton: true, confirmButtonText: 'Oui, Supprimer', cancelButtonText: 'Annuler'
        });
        if (!result.isConfirmed) return;

        showLoader('Suppression du budget…');
        const response = await postJson(apiUrl.deleteBudget, { action: 'delete', id: parseInt(id) });
        if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
        const data = await response.json();
        if (!checkApiResponse(data)) return;
        if (data.status === 'error') throw new Error(data.message || "Erreur suppression.");
        hideLoader();
        Swal.fire({
            icon: 'success', title: 'Succès', text: data.message || "Budget supprimé.",
            timer: 2000, didClose: () => loadBudgetTableData()
        });
    } catch (error) {
        hideLoader();
       // console.error('Erreur deleteBudget:', error);
        Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur suppression: ' + error.message });
    }
}

// ─── Éditer un budget — option=11 POST { id } ─────────────────────────────────
async function editBudget(id, mode = 'plafond') {
    showLoader('Chargement du budget…');
    try {
        await populateTypeBudgetDropdown();

        const response = await postJson(apiUrl.getById, { id: parseInt(id) });
        if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
        const result = await response.json();
        if (!checkApiResponse(result)) return;
        if (result.status === 'error' || !result.data) throw new Error(result.message || 'Budget non trouvé');

        const budget = result.data;
        const nonEditableStatuts = ['Terminer', 'Valider', 'Accepter'];
        if (nonEditableStatuts.includes(budget.statut))
            throw new Error(`Impossible de modifier un budget au statut "${budget.statut}".`);

        // Pour Réajuster : vérifier que l'année est en cours ou future
        if (budget.statut === 'Réajuster') {
            const anneeInt = parseInt(budget.annee);
            const currentYear = new Date().getFullYear();
            if (anneeInt < currentYear)
                throw new Error(`Le statut "Réajuster" n'est applicable que pour des budgets de l'année ${currentYear} ou plus. Ce budget concerne ${budget.annee}.`);
        }

        const form = document.getElementById('budgetForm');
        populateYearDropdown(budget.annee);
        if (form.annee)          form.annee.value          = budget.annee;
        if (form.type_budget_id) form.type_budget_id.value = budget.type_budget_id;

        const plafondActuel = budget.plafond ? parseFloat(budget.plafond) : 0;
        if (form.plafond) form.plafond.value = plafondActuel > 0 ? plafondActuel.toFixed(2) : '';

        // Stocker le plafond actuel et la somme lignes pour validation côté client
        form.dataset.plafondActuel  = plafondActuel;
        form.dataset.sommeLignes    = parseFloat(budget.somme_lignes || 0);

        // Afficher le minimum dans l'aide du champ
        const plafondHelp = document.getElementById('plafond-help');
        if (plafondHelp && plafondActuel > 0) {
            plafondHelp.textContent = `Plafond actuel : ${plafondActuel.toLocaleString('fr-FR')} FCFA — ne peut pas être diminué si des lignes actives/verrouillées existent.`;
            plafondHelp.classList.remove('hidden');
        }

        // Seul le plafond est modifiable sur cette page
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
      //  console.error('Erreur editBudget:', error);
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
    document.getElementById('modalLabel').textContent   = 'Créer un Budget Investissement';
    delete form.dataset.id;
    delete form.dataset.plafondActuel;
    delete form.dataset.sommeLignes;
    const plafondHelp = document.getElementById('plafond-help');
    if (plafondHelp) plafondHelp.classList.add('hidden');

    // Forcer le type investissement
    if (window.COMPTABLE_CONFIG?.defaultType && form.type_budget_id) {
        form.type_budget_id.value    = window.COMPTABLE_CONFIG.defaultType;
        form.type_budget_id.disabled = true;
    }
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
function getBudgetActions(budget, lineCount) {

   // console.log("ndiaya",budget);
    const { id, tmp, statut, annee, plafond } = budget;

    let actions = '<div class="flex justify-end gap-2">';

    const anneeInt = parseInt(annee);
    const currentYear = new Date().getFullYear();
    const isEditableYear = anneeInt >= currentYear; // Réajuster : uniquement années en cours ou futures

    if (statut === 'En cours' || statut === 'Sauvegarder' || statut === 'Rejeter') {
        // Ajouter une ligne + modifier plafond
        actions += `
            <button onclick="poursuivreBudget('${tmp}')" class="btn-action btn-action-primary" title="Ajouter / voir les lignes">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
            <button onclick="editBudget(${id}, 'plafond')" class="btn-action btn-action-warning" title="Modifier le plafond">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>`;
        // Valider : uniquement si des lignes et un plafond existent
        if (lineCount > 0 && parseFloat(plafond) > 0) {
            actions += `
                <button onclick="valider_budget('${tmp}')" class="btn-action btn-action-success" title="Valider le budget">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </button>`;
        }

    } else if (statut === 'Réajuster') {
        // Réajuster : ajouter une ligne ET modifier plafond autorisés
        // mais uniquement si l'année est en cours ou future
        if (isEditableYear) {
            actions += `
                <button onclick="poursuivreBudget('${tmp}')" class="btn-action btn-action-primary" title="Ajouter / voir les lignes">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </button>
                <button onclick="editBudget(${id}, 'plafond')" class="btn-action btn-action-warning" title="Modifier le plafond">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>`;

            // Valider : uniquement si des lignes et un plafond existent
            if (lineCount > 0 && parseFloat(plafond) > 0) {
                actions += `
                <button onclick="valider_budget('${tmp}')" class="btn-action btn-action-success" title="Valider le budget">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </button>`;
            }
        } else {
            // Année passée → lecture seule pour Réajuster
            actions += `
                <button onclick="voirBudget('${tmp}')" class="btn-action btn-action-primary" title="Voir (année passée)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>`;
        }
        // Pas de bouton Supprimer pour Réajuster

    } else if (statut === 'Terminer') {
        actions += `
            <button onclick="redirectionVersPageGraphics('${tmp}')" class="btn-action btn-action-primary" title="Rapport">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </button>`;

    } else {
        // Valider / Accepter → lecture seule
        actions += `
            <button onclick="voirBudget('${tmp}')" class="btn-action btn-action-primary" title="Voir">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>`;
    }

    // Suppression : interdite pour Valider, Accepter, Réajuster, Terminer
    const nonDeletable = ['Valider', 'Accepter', 'Réajuster', 'Terminer'];
    if (!nonDeletable.includes(statut)) {
        actions += `
            <button onclick="deleteBudget('${tmp}')" class="btn-action btn-action-danger" title="Supprimer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>`;
    }

    actions += '</div>';
    return actions;
}

// ─── Redirections ─────────────────────────────────────────────────────────────
function redirectionVersPageGraphics(id) { window.location.href = `../../Gestion_example2/budget/Consulter_Budget.php?id=${id}`; }
function poursuivreBudget(id)            { window.location.href = `/chef_service_ligne_budget_investissement/${id}`; }
function voirBudget(id)                  { window.location.href = `/chef_service_ligne_budget_investissement/${id}`; }

// ─── Valider un budget ────────────────────────────────────────────────────────
function valider_budget(id) {
    Swal.fire({
        title: 'Confirmer la validation', text: 'Voulez-vous vraiment valider ce budget ?',
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Oui, valider', cancelButtonText: 'Annuler'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                showLoader('Validation du budget…');
                // chef_service_basi_controller attend un body form-urlencoded, pas du JSON

                const body =  {budgetId: `${encodeURIComponent(id)}`};
                const controller = new AbortController();
                const timer = setTimeout(() => controller.abort(), 10000);
                const response = await fetch('/chef_service_basi_controller?option=32', {
                    method: 'POST',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify(body),
                    signal: controller.signal
                });
                clearTimeout(timer);
                if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
                const data = await response.json();
                if (!checkApiResponse(data)) return;
                if (data.status === 'error' || !data.success)
                    throw new Error(data.message || data.error || "Erreur validation.");
                hideLoader();
                Swal.fire({
                    icon: 'success', title: 'Succès', text: 'Budget validé avec succès.',
                    timer: 1500, didClose: () => { loadBudgetTableData(); closeModal(); }
                });
            } catch (error) {
                hideLoader();
               // console.error('Erreur valider_budget:', error);
                Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur validation: ' + error.message });
            }
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

    document.getElementById('applyFilters')?.addEventListener('click', () => {
        // Réinitialiser le select années pour qu'il se recharge
        const fa = document.getElementById('filter_annee');
        if (fa) {
            // Garder la valeur sélectionnée mais forcer le rechargement du tableau
        }
        loadBudgetTableData();
    });
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

        // Validation année (uniquement si nouveau budget, pas en mode update)
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

        // Validation plafond
        if (!plafondValue) {
            showError(form.plafond, 'plafond-error', 'Le plafond est requis.'); isValid = false;
        } else if (isNaN(plafond) || plafond <= 0 || plafond > 1000000000) {
            showError(form.plafond, 'plafond-error', 'Le plafond doit être entre 0 et 1 000 000 000.'); isValid = false;
        } else if (id) {
            // En mode modification : vérifier que le plafond ne diminue pas s'il y a des lignes protégées
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
        document.getElementById('submitSpinner').classList.remove('hidden');
        try {
            await addBudget(annee, type_budget_id, plafond, 'En cours', id);
        } catch (error) {
          //  console.error('Erreur soumission:', error);
        } finally {
            submitBtn.disabled = false;
            document.getElementById('submitButton').textContent = originalText;
            document.getElementById('submitSpinner').classList.add('hidden');
        }
    });
});