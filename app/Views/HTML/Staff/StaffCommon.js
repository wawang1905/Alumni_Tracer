 function getLoginUrl() {
    return new URL('../', window.location.href).toString();
}

function getSessionUrl() {
    return new URL('../auth/session', window.location.href).toString();
}

let __staffActionConfirmPromise = null;
function ensureStaffActionConfirm() {
    if (window.ActionConfirm) return Promise.resolve(window.ActionConfirm);
    if (!__staffActionConfirmPromise) {
        __staffActionConfirmPromise = new Promise((resolve) => {
            const s = document.createElement('script');
            s.src = '../JS/ActionConfirm.js';
            s.onload = () => resolve(window.ActionConfirm || null);
            s.onerror = () => resolve(null);
            document.head.appendChild(s);
        });
    }
    return __staffActionConfirmPromise;
}
void ensureStaffActionConfirm();

function getLogoutUrl() {
    return new URL('../auth/logout', window.location.href).toString();
}

// ── Staff Logout ──
async function staffLogout() {
    const btn = document.getElementById('staffLogoutBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>Logging out…</span>';
    }
    try {
        const response = await fetch(getLogoutUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        });
        const data = await response.json().catch(() => ({}));
        window.location.href = data.redirect || '../';
    } catch {
        window.location.href = '../';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('staffLogoutBtn')?.addEventListener('click', staffLogout);
});

function getRecentActivitiesUrl(limit = 5) {
    return new URL(`../activity/recent?limit=${encodeURIComponent(limit)}`, window.location.href).toString();
}

function getLogActivityUrl() {
    return new URL('../activity/log', window.location.href).toString();
}

function getAlumniListUrl(sort = 'last_name_asc', filters = {}) {
    const url = new URL('../alumni/list', window.location.href);
    url.searchParams.set('sort', sort);
    if (filters.year) url.searchParams.set('year', String(filters.year));
    if (filters.college) url.searchParams.set('college', String(filters.college));
    if (filters.course) url.searchParams.set('course', String(filters.course));
    if (filters.status) url.searchParams.set('status', String(filters.status));
    return url.toString();
}

function getCreateAlumniUrl() {
    return new URL('../alumni/create', window.location.href).toString();
}

function getAlumniBatchesUrl() {
    return new URL('../alumni/batches', window.location.href).toString();
}

function getCreateBatchUrl() {
    return new URL('../alumni/batches/create', window.location.href).toString();
}

function getDeleteBatchUrl(year) {
    return new URL(`../alumni/batches/delete/${encodeURIComponent(year)}`, window.location.href).toString();
}

function getImportAlumniUrl() {
    return new URL('../alumni/import', window.location.href).toString();
}

function getAlumniBatchDetailsPageUrl(year = '') {
    const url = new URL('AlumniBatchDetails.html', window.location.href);
    if (year !== '') {
        url.searchParams.set('year', String(year));
    }
    return url.toString();
}

function getAlumniCollegeDetailsPageUrl(year = '', college = '') {
    const url = new URL('AlumniCollegeDetails.html', window.location.href);
    if (year !== '') {
        url.searchParams.set('year', String(year));
    }
    if (college !== '') {
        url.searchParams.set('college', String(college));
    }
    return url.toString();
}

function getAlumniCourseDetailsPageUrl(year = '', college = '', course = '') {
    const url = new URL('AlumniCourseDetails.html', window.location.href);
    if (year !== '') {
        url.searchParams.set('year', String(year));
    }
    if (college !== '') {
        url.searchParams.set('college', String(college));
    }
    if (course !== '') {
        url.searchParams.set('course', String(course));
    }
    return url.toString();
}

function getExportAlumniUrl(year = '', college = '', course = '') {
    const url = new URL('../alumni/export', window.location.href);
    if (year !== '') url.searchParams.set('year', String(year));
    if (college !== '') url.searchParams.set('college', String(college));
    if (course !== '') url.searchParams.set('course', String(course));
    return url.toString();
}

function resolveStaffAppUrl(path) {
    const cleanPath = String(path || '').replace(/^\/+/, '');
    return new URL('../' + cleanPath, window.location.href).toString();
}

function getGenerateReportUrl() {
    return new URL('../reports/generate', window.location.href).toString();
}

function getSurveyListUrl() {
    return new URL('../surveys/list', window.location.href).toString();
}

function getSurveyUpdateUrl(surveyId) {
    return new URL(`../surveys/${encodeURIComponent(surveyId)}/update`, window.location.href).toString();
}

function getSurveyUpdateStatusUrl(surveyId) {
    return new URL(`../surveys/${encodeURIComponent(surveyId)}/status`, window.location.href).toString();
}

function getSurveyUpdateStateUrl(surveyId) {
    return new URL(`../surveys/${encodeURIComponent(surveyId)}/state`, window.location.href).toString();
}

function getSurveyAllowMultipleUrl(surveyId) {
    return new URL(`../surveys/${encodeURIComponent(surveyId)}/allow-multiple`, window.location.href).toString();
}

function getSurveyNextBatchUrl(surveyId) {
    return new URL(`../surveys/${encodeURIComponent(surveyId)}/next-batch`, window.location.href).toString();
}

function getSurveyDeleteUrl(surveyId) {
    return new URL(`../surveys/${encodeURIComponent(surveyId)}`, window.location.href).toString();
}

function getCreateSurveyUrl() {
    return new URL('../surveys/create', window.location.href).toString();
}

function getSurveyCreateUrl() {
    return getCreateSurveyUrl();
}

function getAddSurveyQuestionUrl(surveyId) {
    return new URL(`../surveys/${encodeURIComponent(surveyId)}/questions`, window.location.href).toString();
}

function getUpdateSurveyQuestionUrl(questionId) {
    return new URL(`../surveys/questions/${encodeURIComponent(questionId)}/update`, window.location.href).toString();
}

function getDeleteSurveyQuestionUrl(questionId) {
    return new URL(`../surveys/questions/${encodeURIComponent(questionId)}`, window.location.href).toString();
}

function getSurveyResponsesUrl(surveyId) {
    return new URL(`../surveys/${encodeURIComponent(surveyId)}/responses`, window.location.href).toString();
}

function getSurveyStatisticsUrl(surveyId) {
    return new URL(`../surveys/${encodeURIComponent(surveyId)}/statistics`, window.location.href).toString();
}

function getSurveyBatchStatisticsUrl(surveyId) {
    return new URL(`../surveys/${encodeURIComponent(surveyId)}/batch-statistics`, window.location.href).toString();
}

function getSurveyResponseDetailUrl(responseId) {
    return new URL(`../surveys/responses/${encodeURIComponent(responseId)}`, window.location.href).toString();
}

function getSurveyResponsesPageUrl(surveyId = null) {
    const url = new URL('SurveyResponses.html', window.location.href);
    if (surveyId !== null && surveyId !== undefined && String(surveyId).trim() !== '') {
        url.searchParams.set('surveyId', String(surveyId));
    }
    return url.toString();
}

function getSurveyCreatePageUrl(surveyId = null) {
    const url = new URL('SurveyCreate.html', window.location.href);
    if (surveyId !== null && surveyId !== undefined && String(surveyId).trim() !== '') {
        url.searchParams.set('surveyId', String(surveyId));
    }
    return url.toString();
}

function getDashboardData() {
    return new URL('../staff/getDashboardData', window.location.href).toString();
}

function getNewsListUrl() {
    return new URL('../news/list', window.location.href).toString();
}

function getCreateNewsUrl() {
    return new URL('../news/create', window.location.href).toString();
}

function getUpdateNewsUrl(newsId) {
    return new URL(`../news/update/${encodeURIComponent(newsId)}`, window.location.href).toString();
}

function getDeleteNewsUrl(newsId) {
    return new URL(`../news/delete/${encodeURIComponent(newsId)}`, window.location.href).toString();
}

function redirectToLogin() {
    window.location.replace(getLoginUrl());
}

function initStaffSidebarCollapse() {
    const sidebar = document.querySelector('.sidebar');
    const sidebarHeader = document.querySelector('.sidebar-header');
    if (!sidebar || !sidebarHeader) return;

    const sidebarMenu = document.querySelector('.sidebar-menu');
    if (sidebarMenu) {
        // Tag existing items that might be missing data-page attribute
        Array.from(sidebarMenu.querySelectorAll('.menu-item')).forEach(item => {
            const onclick = (item.getAttribute('onclick') || '').toLowerCase();
            const text = item.textContent.trim().toLowerCase();
            if (!item.getAttribute('data-page')) {
                if (onclick.includes('dashboard.html') || text === 'dashboard') item.setAttribute('data-page', 'dashboard');
                else if (onclick.includes('alumnimanagement.html') || text.includes('alumni management')) item.setAttribute('data-page', 'alumni');
                else if (onclick.includes('colleges.html') || text === 'colleges') item.setAttribute('data-page', 'colleges');
                else if (onclick.includes('departments.html') || text === 'departments') item.setAttribute('data-page', 'departments');
                else if (onclick.includes('coordinators.html') || text === 'coordinators') item.setAttribute('data-page', 'coordinators');
                else if (onclick.includes('events.html') || text === 'events') item.setAttribute('data-page', 'events');
                else if (onclick.includes('news.html') || text === 'news') item.setAttribute('data-page', 'news');
                else if (onclick.includes('surveys.html') || text === 'surveys') item.setAttribute('data-page', 'surveys');
                else if (onclick.includes('payments.html') || text === 'payments') item.setAttribute('data-page', 'payments');
                else if (onclick.includes('reports.html') || text === 'reports') item.setAttribute('data-page', 'reports');
                else if (onclick.includes('settings.html') || text === 'settings') item.setAttribute('data-page', 'settings');
            }
        });

        // Deduplicate any repeated menu items
        const seenPages = new Set();
        Array.from(sidebarMenu.querySelectorAll('.menu-item')).forEach(item => {
            const page = item.getAttribute('data-page') || item.getAttribute('onclick') || item.textContent.trim();
            if (page) {
                if (seenPages.has(page)) {
                    item.remove();
                } else {
                    seenPages.add(page);
                }
            }
        });

        // If colleges or departments are still missing from a legacy page, insert them
        if (!sidebarMenu.querySelector('[data-page="colleges"]')) {
            const items = Array.from(sidebarMenu.querySelectorAll('.menu-item'));
            const coordinatorsItem = items.find(item => item.getAttribute('data-page') === 'coordinators');

            const collegesItem = document.createElement('div');
            collegesItem.className = 'menu-item';
            collegesItem.setAttribute('data-page', 'colleges');
            collegesItem.onclick = () => { window.location.href = 'Colleges.html'; };
            collegesItem.innerHTML = `
                <i class="fas fa-building-columns"></i>
                <span>Colleges</span>
            `;

            const departmentsItem = document.createElement('div');
            departmentsItem.className = 'menu-item';
            departmentsItem.setAttribute('data-page', 'departments');
            departmentsItem.onclick = () => { window.location.href = 'Departments.html'; };
            departmentsItem.innerHTML = `
                <i class="fas fa-network-wired"></i>
                <span>Departments</span>
            `;

            if (coordinatorsItem) {
                coordinatorsItem.insertAdjacentElement('afterend', collegesItem);
                collegesItem.insertAdjacentElement('afterend', departmentsItem);
            } else {
                sidebarMenu.appendChild(collegesItem);
                sidebarMenu.appendChild(departmentsItem);
            }
        }

        // If payments is still missing from a legacy page, insert it
        if (!sidebarMenu.querySelector('[data-page="payments"]')) {
            const allItems = Array.from(sidebarMenu.querySelectorAll('.menu-item'));
            const surveysItem = allItems.find(item => item.getAttribute('data-page') === 'surveys');

            const paymentsItem = document.createElement('div');
            paymentsItem.className = 'menu-item';
            paymentsItem.setAttribute('data-page', 'payments');
            paymentsItem.onclick = () => { window.location.href = 'Payments.html'; };
            paymentsItem.innerHTML = `
                <i class="fas fa-receipt"></i>
                <span>Payments</span>
            `;

            if (surveysItem) {
                surveysItem.insertAdjacentElement('afterend', paymentsItem);
            } else {
                sidebarMenu.appendChild(paymentsItem);
            }
        }

        // Hide Reports module from Head Admin sidebar
        Array.from(sidebarMenu.querySelectorAll('.menu-item')).forEach(item => {
            if (
                item.getAttribute('data-page') === 'reports' ||
                (item.getAttribute('onclick') && item.getAttribute('onclick').includes('Reports.html'))
            ) {
                item.style.display = 'none';
            }
        });

        // Sync active state based on current page URL
        const currentPath = window.location.pathname.toLowerCase();
        Array.from(sidebarMenu.querySelectorAll('.menu-item')).forEach(item => {
            const page = item.getAttribute('data-page');
            const isActive = (
                (currentPath.includes('dashboard.html') && page === 'dashboard') ||
                (currentPath.includes('alumnimanagement.html') && page === 'alumni') ||
                (currentPath.includes('colleges.html') && page === 'colleges') ||
                (currentPath.includes('departments.html') && page === 'departments') ||
                (currentPath.includes('coordinators.html') && page === 'coordinators') ||
                (currentPath.includes('events.html') && page === 'events') ||
                (currentPath.includes('news.html') && page === 'news') ||
                (currentPath.includes('surveys.html') && page === 'surveys') ||
                (currentPath.includes('payments.html') && page === 'payments') ||
                (currentPath.includes('settings.html') && page === 'settings')
            );
            if (isActive) {
                item.classList.add('active');
            } else if (page) {
                item.classList.remove('active');
            }
        });
    }

    if (sidebarHeader.querySelector('.sidebar-toggle')) return;

    const desktopQuery = window.matchMedia('(min-width: 769px)');
    const toggleButton = document.createElement('button');
    toggleButton.type = 'button';
    toggleButton.className = 'sidebar-toggle';
    toggleButton.setAttribute('aria-label', 'Collapse sidebar');
    toggleButton.setAttribute('aria-expanded', 'true');
    toggleButton.innerHTML = '<i class="fas fa-angle-left" aria-hidden="true"></i>';
    sidebarHeader.appendChild(toggleButton);

    document.querySelectorAll('.menu-item').forEach((item) => {
        const label = item.querySelector('span');
        if (label && !item.title) {
            item.title = label.textContent.trim();
        }
    });

    const applyState = () => {
        const shouldCollapse = desktopQuery.matches && localStorage.getItem('staffSidebarCollapsed') === 'true';
        document.body.classList.toggle('staff-sidebar-collapsed', shouldCollapse);
        toggleButton.setAttribute('aria-label', shouldCollapse ? 'Expand sidebar' : 'Collapse sidebar');
        toggleButton.setAttribute('aria-expanded', String(!shouldCollapse));
    };

    toggleButton.addEventListener('click', () => {
        if (!desktopQuery.matches) return;
        const nextValue = !document.body.classList.contains('staff-sidebar-collapsed');
        localStorage.setItem('staffSidebarCollapsed', String(nextValue));
        applyState();
    });

    if (typeof desktopQuery.addEventListener === 'function') {
        desktopQuery.addEventListener('change', applyState);
    } else {
        desktopQuery.addListener(applyState);
    }

    applyState();
}

document.addEventListener('DOMContentLoaded', initStaffSidebarCollapse);

let inFlightSessionPromise = null;

async function fetchSession() {
    if (inFlightSessionPromise) {
        return inFlightSessionPromise;
    }

    inFlightSessionPromise = (async () => {
        const response = await fetch(getSessionUrl(), {
            credentials: 'include',
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error('Unable to verify session.');
        }

        return response.json();
    })();

    try {
        return await inFlightSessionPromise;
    } finally {
        inFlightSessionPromise = null;
    }
}

async function requireAuth() {
    try {
        const result = await fetchSession();
        if (!result.loggedIn) {
            redirectToLogin();
            return null;
        }

        return result.user;
    } catch (error) {
        redirectToLogin();
        return null;
    }
}

function updateStaffHeaderInfo(currentUser) {
    const staffInfo = document.querySelector('.staff-info');
    if (!staffInfo) return;

    const staffName = staffInfo.querySelector('.staff-name');
    if (staffName && currentUser?.name) {
        staffName.textContent = currentUser.name;
    }
}

window.addEventListener('pageshow', async (event) => {
    if (event.persisted) {
        await requireAuth();
    }
});

// Sample Data
const STORAGE_KEY = 'staff_common_state_v1';

const sampleAlumniData = [
    { id: 1, name: "John Doe", email: "john@example.com", graduationYear: "2018", status: "active" },
    { id: 2, name: "Jane Smith", email: "jane@example.com", graduationYear: "2019", status: "active" },
    { id: 3, name: "Mike Johnson", email: "mike@example.com", graduationYear: "2020", status: "active" },
    { id: 4, name: "Sarah Williams", email: "sarah@example.com", graduationYear: "2018", status: "pending" },
    { id: 5, name: "David Brown", email: "david@example.com", graduationYear: "2021", status: "active" }
];

const sampleEventsData = [
    { id: 1, name: "Annual Alumni Reunion", date: "2025-06-15", location: "Main Hall", attendees: 45 },
    { id: 2, name: "Career Networking Night", date: "2025-07-20", location: "Virtual", attendees: 120 },
    { id: 3, name: "Mentorship Workshop", date: "2025-08-10", location: "Conference Room", attendees: 30 }
];

const sampleSurveyResponses = [];

function safeJsonParse(value) {
    try {
        return JSON.parse(value);
    } catch (e) {
        return null;
    }
}

function loadState() {
    if (typeof localStorage === 'undefined') return null;
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? safeJsonParse(raw) : null;
}

function saveState() {
    if (typeof localStorage === 'undefined') return;
    const state = {
        alumniData,
        eventsData,
        surveyResponses,
        nextAlumniId,
        nextEventId,
        nextSurveyResponseId
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

const stored = loadState();

function ensureArray(value, fallback) {
    return Array.isArray(value) ? value : fallback;
}

let alumniData = ensureArray(stored?.alumniData, sampleAlumniData);
let eventsData = ensureArray(stored?.eventsData, sampleEventsData);
let activities = [];
let surveysData = [];
let surveyResponses = ensureArray(stored?.surveyResponses, sampleSurveyResponses);
let currentSurveyResponses = [];
let currentSurveyResponsesPage = 1;
const SURVEY_RESPONSES_PER_PAGE = 15;
let currentSurveyStats = null;
let currentSurveyBatchStats = null;
let alumniBatches = [];
let alumniBatchesSummary = null;
const SURVEY_WIZARD_STORAGE_KEY = 'surveyWizardData';
let surveyWizardData = null;
let surveyWizardCurrentStep = 1;
let surveyWizardEditingQuestion = null;
let surveyWizardCollapsedCategories = new Set();
let surveyWizardPublishMethod = 'immediate';
let surveyWizardPublishing = false;
let surveyWizardPublishedSurveyId = null;
let surveyWizardHasChanges = false;

let nextAlumniId = stored?.nextAlumniId || ((alumniData.reduce((max, a) => Math.max(max, a.id), 0)) + 1);
let nextEventId = stored?.nextEventId || ((eventsData.reduce((max, e) => Math.max(max, e.id), 0)) + 1);
let nextSurveyResponseId = stored?.nextSurveyResponseId || ((surveyResponses.reduce((max, r) => Math.max(max, r.id), 0)) + 1);
let alumniSort = 'last_name_asc';
let selectedAlumniBatchYear = null;
let selectedAlumniCollege = null;
let selectedAlumniCourse = null;
let selectedDashboardBatchFilter = 'all';
let selectedCollegeBatchFilter = 'all';
let selectedCourseBatchFilter = 'all';
let selectedCourseCollegeFilter = 'all';

// Helper Functions
function updateStats() {
    const totalAlumniEl = document.getElementById('totalAlumni');
    const activeAlumniEl = document.getElementById('activeAlumni');
    const totalEventsEl = document.getElementById('totalEvents');
    const engagementRateEl = document.getElementById('engagementRate');

    // Stats cards only exist on the Dashboard page.
    if (!totalAlumniEl || !activeAlumniEl || !totalEventsEl || !engagementRateEl) return;

    totalAlumniEl.textContent = alumniData.length;
    const activeCount = alumniData.filter(a => a.status === 'active').length;
    activeAlumniEl.textContent = activeCount;
    totalEventsEl.textContent = eventsData.length;

    const engagementRate = alumniData.length > 0 ? Math.round((activeCount / alumniData.length) * 100) : 0;
    engagementRateEl.textContent = `${engagementRate || 0}%`;
}

function updateAlumniManagementStats() {
    const totalAlumniEl = document.getElementById('totalAlumniStat');
    const totalCollegesEl = document.getElementById('totalCollegesStat');
    const totalCoursesEl = document.getElementById('totalCoursesStat');
    const totalBatchYearsEl = document.getElementById('totalBatchYearsStat');
    // Only update on the AlumniManagement page
    if (!totalAlumniEl || !totalCollegesEl || !totalCoursesEl || !totalBatchYearsEl) return;

    if (alumniBatchesSummary && typeof alumniBatchesSummary === 'object') {
        totalAlumniEl.textContent = Number(alumniBatchesSummary.totalAlumni ?? 0);
        totalCollegesEl.textContent = Number(alumniBatchesSummary.totalColleges ?? 0);
        totalCoursesEl.textContent = Number(alumniBatchesSummary.totalCourses ?? 0);
        totalBatchYearsEl.textContent = alumniBatches.length > 0
            ? alumniBatches.length
            : Number(alumniBatchesSummary.totalBatches ?? 0);
        return;
    }

    totalAlumniEl.textContent = alumniData.length;

    // Get unique colleges from alumni data
    const uniqueColleges = new Set();
    alumniData.forEach((a) => {
        const c = String(a.college || '').trim();
        if (c) uniqueColleges.add(c);
    });
    totalCollegesEl.textContent = uniqueColleges.size;

    // Get unique courses from alumni data
    const uniqueCourses = new Set();
    alumniData.forEach((a) => {
        const c = String(a.course || '').trim();
        if (c) uniqueCourses.add(c);
    });
    totalCoursesEl.textContent = uniqueCourses.size;

    // Get total batch years from the alumni_batches or alumni data
    const batchYears = alumniBatches.length > 0
        ? alumniBatches.length
        : new Set(alumniData.map((a) => String(a.graduationYear || '').trim()).filter(Boolean)).size;
    totalBatchYearsEl.textContent = batchYears;
}

function renderAlumniTable() {
    const tbody = document.getElementById('alumniTableBody');
    if (!tbody) return;

    const records = getSelectedCourseAlumniRecords();

    let filtered = [...records];

    const search = (document.getElementById('alumniSearch')?.value || '').trim().toLowerCase();
    if (search) {
        filtered = filtered.filter((a) => {
            const hay = [
                a.alumniId,
                a.lastName,
                a.firstName,
                a.email,
                a.status,
            ].map((v) => String(v ?? '').toLowerCase()).join(' | ');
            return hay.includes(search);
        });
    }

    const showIncompleteOnly = Boolean(document.getElementById('showIncompleteToggle')?.checked);
    if (showIncompleteOnly) {
        filtered = filtered.filter((a) => String(a.status ?? '').toLowerCase() === 'incomplete');
    }

    const sortKey = document.getElementById('alumniTableSort')?.value || 'last_name_asc';

    const cmpStr = (x, y) => String(x ?? '').localeCompare(String(y ?? ''), undefined, { numeric: true, sensitivity: 'base' });
    filtered.sort((a, b) => {
        switch (sortKey) {
            case 'last_name_desc': return cmpStr(b.lastName, a.lastName);
            case 'first_name_asc': return cmpStr(a.firstName, b.firstName);
            case 'first_name_desc': return cmpStr(b.firstName, a.firstName);
            case 'college_asc': return cmpStr(a.college, b.college);
            case 'course_asc': return cmpStr(a.course, b.course);
            case 'email_asc':
                return cmpStr(a.email, b.email);
            default:
            case 'last_name_asc':
                return cmpStr(a.lastName, b.lastName);
        }
    });

    tbody.innerHTML = '';
    if (filtered.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="alumni-table-empty-cell">No matching alumni records found.</td>
            </tr>
        `;
        return;
    }

    filtered.forEach(alumni => {
        const row = tbody.insertRow();
        const st = String(alumni.status ?? '').toLowerCase();
        const badgeClass =
            st === 'active'
                ? 'active'
                : st === 'inactive'
                    ? 'inactive'
                    : st === 'incomplete'
                        ? 'incomplete'
                        : 'pending';
        const statusLabel = st ? st.charAt(0).toUpperCase() + st.slice(1) : 'Pending';
        row.innerHTML = `
            <td><span class="alumni-id-code">${escapeHtml(alumni.alumniId || '—')}</span></td>
            <td style="font-weight: 600; color: #0f172a;">${escapeHtml(alumni.lastName || '—')}</td>
            <td>${escapeHtml(alumni.firstName || '—')}</td>
            <td style="color: #64748b;">${escapeHtml(alumni.email || '—')}</td>
            <td>${escapeHtml(alumni.college || '—')}</td>
            <td>${escapeHtml(alumni.course || '—')}</td>
            <td><span class="alumni-status-badge ${badgeClass}">${escapeHtml(statusLabel)}</span></td>
        `;
    });
}

function getBatchYearLabel(year) {
    const value = String(year ?? '').trim();
    return value !== '' ? value : 'Unassigned';
}

function getSelectedCollegeFromUrl() {
    try {
        const url = new URL(window.location.href);
        return (url.searchParams.get('college') || '').trim();
    } catch (error) {
        return '';
    }
}

function getSelectedCourseFromUrl() {
    try {
        const url = new URL(window.location.href);
        return (url.searchParams.get('course') || '').trim();
    } catch (error) {
        return '';
    }
}

function isAlumniBatchPage() {
    return window.location.pathname.endsWith('/AlumniBatchDetails.html');
}

function isAlumniCollegePage() {
    return window.location.pathname.endsWith('/AlumniCollegeDetails.html');
}

function isAlumniCoursePage() {
    return window.location.pathname.endsWith('/AlumniCourseDetails.html');
}

function getBatchAlumniRecords(batchYear = selectedAlumniBatchYear) {
    if (batchYear === null) return [];
    return alumniData.filter((alumni) => String(alumni.graduationYear ?? '') === String(batchYear));
}

function getSelectedCollegeAlumniRecords() {
    const records = getBatchAlumniRecords();
    if (!selectedAlumniCollege) return [];
    return records.filter((alumni) => String(alumni.college ?? '').trim() === String(selectedAlumniCollege));
}

function getSelectedCourseAlumniRecords() {
    const records = getSelectedCollegeAlumniRecords();
    if (!selectedAlumniCourse) return [];
    return records.filter((alumni) => String(alumni.course ?? '').trim() === String(selectedAlumniCourse));
}

function getBatchCollegeSummaries() {
    const counts = {};
    getBatchAlumniRecords().forEach((alumni) => {
        const college = String(alumni.college ?? '').trim() || 'Unassigned';
        counts[college] = (counts[college] || 0) + 1;
    });

    return Object.entries(counts)
        .map(([college, alumniCount]) => ({ college, alumniCount }))
        .sort((a, b) => b.alumniCount - a.alumniCount || a.college.localeCompare(b.college));
}

function getCollegeCourseSummaries() {
    const counts = {};
    getSelectedCollegeAlumniRecords().forEach((alumni) => {
        const course = String(alumni.course ?? '').trim() || 'Unassigned';
        counts[course] = (counts[course] || 0) + 1;
    });

    return Object.entries(counts)
        .map(([course, alumniCount]) => ({ course, alumniCount }))
        .sort((a, b) => b.alumniCount - a.alumniCount || a.course.localeCompare(b.course));
}

function buildBreadcrumbChip(label, iconClass, href) {
    return `<a class="alumni-breadcrumb-chip" href="${escapeHtml(href)}"><span>${escapeHtml(label)}</span></a>`;
}

function getAlumniManagementPageUrl() {
    return new URL('AlumniManagement.html', window.location.href).toString();
}

function selectAlumniCollege(college) {
    const normalizedCollege = String(college || '').trim();
    if (!normalizedCollege || !selectedAlumniBatchYear) return;

    selectedAlumniCollege = normalizedCollege;
    selectedAlumniCourse = null;
    window.location.href = getAlumniCollegeDetailsPageUrl(selectedAlumniBatchYear, normalizedCollege);
}

function selectAlumniCourse(course) {
    const normalizedCourse = String(course || '').trim();
    if (!normalizedCourse || !selectedAlumniBatchYear || !selectedAlumniCollege) return;

    selectedAlumniCourse = normalizedCourse;
    window.location.href = getAlumniCourseDetailsPageUrl(selectedAlumniBatchYear, selectedAlumniCollege, normalizedCourse);
}

async function loadAlumniBatches() {
    const container = document.getElementById('alumniBatchGrid');
    const dashboardBatchFilter = document.getElementById('dashboardBatchFilter');
    const collegeFilter = document.getElementById('collegeBatchFilter');
    const courseFilter = document.getElementById('courseBatchFilter');
    if (!container && !dashboardBatchFilter && !collegeFilter && !courseFilter) return;

    const requestUrl = getAlumniBatchesUrl();
    try {
        const response = await fetch(requestUrl, {
            credentials: 'include',
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error(`Unable to load alumni batches (HTTP ${response.status}).`);
        }

        const result = await response.json();
        alumniBatches = Array.isArray(result.batches)
            ? result.batches
            : Array.isArray(result.data)
                ? result.data
                : Array.isArray(result)
                    ? result
                    : [];
        alumniBatchesSummary = result && typeof result.summary === 'object' ? result.summary : null;
    } catch (error) {
        console.error('Failed to load alumni batches.', {
            url: requestUrl,
            error: error instanceof Error ? error.message : error
        });
        alumniBatches = [];
        alumniBatchesSummary = null;
    }

    populateDashboardBatchFilters();
    renderAlumniBatchCards();
    updateAlumniManagementStats();
}

function renderAlumniBatchCards() {
    const container = document.getElementById('alumniBatchGrid');
    if (!container) return;

    if (!Array.isArray(alumniBatches) || alumniBatches.length === 0) {
        container.innerHTML = '<div class="alumni-batch-empty">No graduation batches yet. Click "New Batch" to create a batch year, then add or import alumni into it.</div>';
        return;
    }

    container.innerHTML = alumniBatches.map((batch) => {
        const year = getBatchYearLabel(batch.graduationYear);
        const activeClass = String(selectedAlumniBatchYear) === String(year) ? 'is-active' : '';

        return `
            <article class="alumni-card ${activeClass}" onclick='openAlumniBatch(${JSON.stringify(String(year))})'>
                <div class="alumni-card-header">
                    <div class="alumni-card-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="alumni-card-title">
                        <h4>Class of ${escapeHtml(year)}</h4>
                        <p>Graduation Batch</p>
                    </div>
                    <i class="fas fa-arrow-right alumni-card-arrow" aria-hidden="true"></i>
                </div>
                <div class="alumni-card-metadata">
                    <span class="alumni-card-tag">${Number(batch.alumniCount || 0)} alumni</span>
                </div>
            </article>
        `;
    }).join('');
}

function renderBatchCollegePage() {
    const state = document.getElementById('alumniBatchDetailsState');
    const title = document.getElementById('alumniBatchDetailsTitle');
    const copy = document.getElementById('alumniBatchDetailsCopy');
    const breadcrumb = document.getElementById('alumniBatchBreadcrumb');
    const collegeSection = document.getElementById('alumniCollegeSection');
    const collegeGrid = document.getElementById('alumniCollegeGrid');
    const addBtn = document.getElementById('addBatchAlumniBtn');
    const importBtn = document.getElementById('importBatchBtn');
    const exportBtn = document.getElementById('exportBatchBtn');
    if (!state || !title || !copy || !collegeSection || !collegeGrid || !addBtn || !importBtn) return;

    if (selectedAlumniBatchYear === null) {
        title.textContent = 'Batch Colleges';
        copy.textContent = 'Select a batch first.';
        if (breadcrumb) {
            breadcrumb.hidden = true;
            breadcrumb.innerHTML = '';
        }
        state.innerHTML = '<div class="alumni-batch-empty">Choose a graduation year first, then open its colleges.</div>';
        collegeSection.hidden = true;
        addBtn.hidden = true;
        importBtn.hidden = true;
        if (exportBtn) exportBtn.hidden = true;
        return;
    }

    const members = getBatchAlumniRecords();
    const colleges = getBatchCollegeSummaries();

    title.textContent = `Batch ${getBatchYearLabel(selectedAlumniBatchYear)} — Colleges`;
    copy.textContent = `${members.length} alumni record(s) in this batch. Select a college to view its departments.`;
    if (breadcrumb) {
        breadcrumb.hidden = false;
        breadcrumb.innerHTML = [
            buildBreadcrumbChip(
                'Batches',
                'fas fa-users',
                getAlumniManagementPageUrl()
            ),
            buildBreadcrumbChip(
                `Batch ${getBatchYearLabel(selectedAlumniBatchYear)}`,
                'fas fa-layer-group',
                getAlumniBatchDetailsPageUrl(selectedAlumniBatchYear)
            )
        ].join('<i class="fas fa-chevron-right alumni-bc-sep" aria-hidden="true"></i>');
    }

    state.innerHTML = members.length === 0
        ? '<div class="alumni-batch-empty">This batch exists, but it does not have alumni records yet. You can add or import alumni into it.</div>'
        : '';

    collegeSection.hidden = members.length === 0;
    collegeGrid.innerHTML = colleges.map((entry) => `
        <article class="alumni-card alumni-nested-card" onclick='selectAlumniCollege(${JSON.stringify(entry.college)})'>
            <div class="alumni-card-header">
                <div class="alumni-card-icon">
                    <i class="fas fa-building-columns"></i>
                </div>
                <div class="alumni-card-title">
                    <h4>${escapeHtml(entry.college)}</h4>
                    <p>College</p>
                </div>
                <i class="fas fa-arrow-right alumni-card-arrow" aria-hidden="true"></i>
            </div>
            <div class="alumni-card-metadata">
                <span class="alumni-card-tag">${Number(entry.alumniCount || 0)} alumni</span>
            </div>
        </article>
    `).join('');

    addBtn.hidden = true;
    importBtn.hidden = false;
    if (exportBtn) exportBtn.hidden = false;

    updateBatchDetailPageStats();
}

function updateBatchDetailPageStats() {
    const totalEl = document.getElementById('batchTotalAlumniStat');
    const collegeCountEl = document.getElementById('batchCollegeCountStat');
    const employedEl = document.getElementById('batchEmployedCountStat');
    const furtherStudiesEl = document.getElementById('batchFurtherStudiesCountStat');
    if (!totalEl || !collegeCountEl) return;

    const members = getBatchAlumniRecords();
    const colleges = getBatchCollegeSummaries();
    totalEl.textContent = members.length;
    collegeCountEl.textContent = colleges.length;

    // "Employed" and "Further Studies" are placeholder stats — alumni data doesn't have employment fields.
    // We show counts for status=active (employed proxy) and status=pending (further studies proxy).
    if (employedEl) {
        employedEl.textContent = members.filter((a) => String(a.status || '').toLowerCase() === 'active').length;
    }
    if (furtherStudiesEl) {
        furtherStudiesEl.textContent = members.filter((a) => String(a.status || '').toLowerCase() === 'pending').length;
    }
}

function updateCollegeDetailPageStats() {
    const courseCountEl = document.getElementById('collegeCourseCountStat');
    const totalAlumniEl = document.getElementById('collegeTotalAlumniStat');
    const batchYearEl = document.getElementById('collegeBatchYearStat');
    if (!courseCountEl || !totalAlumniEl || !batchYearEl) return;

    const members = getSelectedCollegeAlumniRecords();
    const courses = getCollegeCourseSummaries();
    courseCountEl.textContent = courses.length;
    totalAlumniEl.textContent = members.length;
    batchYearEl.textContent = selectedAlumniBatchYear ? getBatchYearLabel(selectedAlumniBatchYear) : '—';
}

function updateCourseDetailPageStats() {
    const totalEl = document.getElementById('courseTotalAlumniStat');
    const activeEl = document.getElementById('courseActiveCountStat');
    const pendingEl = document.getElementById('coursePendingCountStat');
    if (!totalEl || !pendingEl) return;

    const members = getSelectedCourseAlumniRecords();
    totalEl.textContent = members.length;
    if (activeEl) {
        activeEl.textContent = members.filter((a) => String(a.status || '').toLowerCase() === 'active').length;
    }
    pendingEl.textContent = members.filter((a) => String(a.status || '').toLowerCase() === 'pending').length;
}

function renderCollegeCoursePage() {
    const state = document.getElementById('alumniCollegeDetailsState');
    const title = document.getElementById('alumniCollegeDetailsTitle');
    const copy = document.getElementById('alumniCollegeDetailsCopy');
    const breadcrumb = document.getElementById('alumniCollegeBreadcrumb');
    const courseSection = document.getElementById('alumniCourseSection');
    const courseGrid = document.getElementById('alumniCourseGrid');
    const backBtn = document.getElementById('backToBatchBtn');
    const addBtn = document.getElementById('addBatchAlumniBtn');
    const importBtn = document.getElementById('importBatchBtn');
    const exportBtn = document.getElementById('exportBatchBtn');
    if (!state || !title || !copy || !courseSection || !courseGrid || !backBtn || !addBtn || !importBtn) return;

    backBtn.onclick = () => {
        window.location.href = getAlumniBatchDetailsPageUrl(selectedAlumniBatchYear || '');
    };

    if (selectedAlumniBatchYear === null || !selectedAlumniCollege) {
        title.textContent = 'College Departments';
        copy.textContent = 'Select a batch and college first.';
        if (breadcrumb) {
            breadcrumb.hidden = true;
            breadcrumb.innerHTML = '';
        }
        state.innerHTML = '<div class="alumni-batch-empty">Open a batch, then choose a college to view its departments.</div>';
        courseSection.hidden = true;
        addBtn.hidden = true;
        importBtn.hidden = true;
        if (exportBtn) exportBtn.hidden = true;
        return;
    }

    const members = getSelectedCollegeAlumniRecords();
    const courses = getCollegeCourseSummaries();
    if (members.length === 0) {
        selectedAlumniCourse = null;
    }

    title.textContent = `${selectedAlumniCollege} — Departments`;
    copy.textContent = `${members.length} alumni record(s) under this college for Batch ${getBatchYearLabel(selectedAlumniBatchYear)}. Select a department to view alumni.`;
    if (breadcrumb) {
        breadcrumb.hidden = false;
        breadcrumb.innerHTML = [
            buildBreadcrumbChip(
                'Batches',
                'fas fa-users',
                getAlumniManagementPageUrl()
            ),
            buildBreadcrumbChip(
                `Batch ${getBatchYearLabel(selectedAlumniBatchYear)}`,
                'fas fa-layer-group',
                getAlumniBatchDetailsPageUrl(selectedAlumniBatchYear)
            ),
            buildBreadcrumbChip(
                selectedAlumniCollege,
                'fas fa-building-columns',
                getAlumniCollegeDetailsPageUrl(selectedAlumniBatchYear, selectedAlumniCollege)
            )
        ].join('<i class="fas fa-chevron-right alumni-bc-sep" aria-hidden="true"></i>');
    }

    state.innerHTML = members.length === 0
        ? '<div class="alumni-batch-empty">No alumni were found under this college for the selected batch.</div>'
        : '';

    courseSection.hidden = members.length === 0;
    courseGrid.innerHTML = courses.map((entry) => `
        <article class="alumni-card alumni-nested-card" onclick='selectAlumniCourse(${JSON.stringify(entry.course)})'>
            <div class="alumni-card-header">
                <div class="alumni-card-icon">
                    <i class="fas fa-network-wired"></i>
                </div>
                <div class="alumni-card-title">
                    <h4>${escapeHtml(entry.course)}</h4>
                    <p>Department</p>
                </div>
                <i class="fas fa-arrow-right alumni-card-arrow" aria-hidden="true"></i>
            </div>
            <div class="alumni-card-metadata">
                <span class="alumni-card-tag">${Number(entry.alumniCount || 0)} alumni</span>
            </div>
        </article>
    `).join('');

    addBtn.hidden = true;
    importBtn.hidden = false;
    if (exportBtn) exportBtn.hidden = false;

    updateCollegeDetailPageStats();
}

function renderCourseAlumniPage() {
    const state = document.getElementById('alumniCourseDetailsState');
    const wrap = document.getElementById('alumniBatchTableWrap');
    const title = document.getElementById('alumniCourseDetailsTitle');
    const copy = document.getElementById('alumniCourseDetailsCopy');
    const breadcrumb = document.getElementById('alumniCourseBreadcrumb');
    const backBtn = document.getElementById('backToCollegeBtn');
    const addBtn = document.getElementById('addBatchAlumniBtn');
    const importBtn = document.getElementById('importBatchBtn');
    const exportBtn = document.getElementById('exportBatchBtn');
    if (!state || !wrap || !title || !copy || !backBtn || !addBtn || !importBtn) return;

    backBtn.onclick = () => {
        window.location.href = getAlumniCollegeDetailsPageUrl(selectedAlumniBatchYear || '', selectedAlumniCollege || '');
    };

    if (selectedAlumniBatchYear === null || !selectedAlumniCollege || !selectedAlumniCourse) {
        title.textContent = 'Department Alumni';
        copy.textContent = 'Select a batch, college, and department first.';
        if (breadcrumb) {
            breadcrumb.hidden = true;
            breadcrumb.innerHTML = '';
        }
        state.innerHTML = '<div class="alumni-batch-empty">Open a batch, choose a college, then choose a department to view the alumni list.</div>';
        wrap.hidden = true;
        addBtn.hidden = true;
        importBtn.hidden = true;
        if (exportBtn) exportBtn.hidden = true;
        return;
    }

    const members = getSelectedCourseAlumniRecords();
    title.textContent = selectedAlumniCourse;
    copy.textContent = `${members.length} alumni record(s) in ${selectedAlumniCollege} • Batch ${getBatchYearLabel(selectedAlumniBatchYear)}`;
    if (breadcrumb) {
        breadcrumb.hidden = false;
        breadcrumb.innerHTML = [
            buildBreadcrumbChip(
                'Batches',
                'fas fa-users',
                getAlumniManagementPageUrl()
            ),
            buildBreadcrumbChip(
                `Batch ${getBatchYearLabel(selectedAlumniBatchYear)}`,
                'fas fa-layer-group',
                getAlumniBatchDetailsPageUrl(selectedAlumniBatchYear)
            ),
            buildBreadcrumbChip(
                selectedAlumniCollege,
                'fas fa-building-columns',
                getAlumniCollegeDetailsPageUrl(selectedAlumniBatchYear, selectedAlumniCollege)
            ),
            buildBreadcrumbChip(
                selectedAlumniCourse,
                'fas fa-network-wired',
                getAlumniCourseDetailsPageUrl(selectedAlumniBatchYear, selectedAlumniCollege, selectedAlumniCourse)
            )
        ].join('<i class="fas fa-chevron-right alumni-bc-sep" aria-hidden="true"></i>');
    }

    state.innerHTML = members.length === 0
        ? '<div class="alumni-batch-empty">No alumni were found for the selected department.</div>'
        : '';
    wrap.hidden = members.length === 0;
    addBtn.hidden = false;
    importBtn.hidden = false;
    if (exportBtn) exportBtn.hidden = false;

    if (!wrap.hidden) {
        renderAlumniTable();
    }

    updateCourseDetailPageStats();
}

function renderSelectedBatchDetails() {
    if (isAlumniBatchPage()) {
        renderBatchCollegePage();
        return;
    }

    if (isAlumniCollegePage()) {
        renderCollegeCoursePage();
        return;
    }

    if (isAlumniCoursePage()) {
        renderCourseAlumniPage();
    }
}

function openAlumniBatch(year) {
    window.location.href = getAlumniBatchDetailsPageUrl(year);
}

function clearSelectedBatch() {
    if (isAlumniBatchPage() || isAlumniCollegePage() || isAlumniCoursePage()) {
        window.location.href = 'AlumniManagement.html';
        return;
    }

    selectedAlumniBatchYear = null;
    selectedAlumniCollege = null;
    selectedAlumniCourse = null;
    renderAlumniBatchCards();
    renderSelectedBatchDetails();
}

async function deleteBatch(year) {
    const yearStr = String(year ?? '').trim();
    if (!yearStr) {
        alert('Invalid batch year.');
        return;
    }

    const batch = alumniBatches.find(b => String(b.graduationYear ?? '') === yearStr);
    const alumniCount = batch?.alumniCount || 0;

    // Prevent deletion if alumni records exist
    if (alumniCount > 0) {
        alert(`Cannot delete Batch ${yearStr}.\n\nThis batch contains ${alumniCount} alumni record(s).\n\nPlease remove or reassign all alumni from this batch before deleting.`);
        return;
    }

    const ac = await ensureStaffActionConfirm();
    const confirmed = ac
        ? await ac.high({
            title: 'Permanently Delete Batch?',
            message: `You are about to permanently delete Batch ${yearStr} from the batch registry.`,
            record: `Batch ${yearStr}`,
            consequence: 'This action cannot be undone.',
            requiredPhrase: 'DELETE',
            confirmText: 'Delete Batch'
        })
        : false;
    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch(getDeleteBatchUrl(yearStr), {
            method: 'DELETE',
            credentials: 'include',
            cache: 'no-store'
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `Failed to delete batch (HTTP ${response.status})`);
        }

        const result = await response.json();

        if (result.success) {
            // Remove deleted batch from local array
            alumniBatches = alumniBatches.filter(b => String(b.graduationYear ?? '') !== yearStr);

            // Clear selection if this was the selected batch
            if (String(selectedAlumniBatchYear) === yearStr) {
                selectedAlumniBatchYear = null;
                renderSelectedBatchDetails();
            }

            // Re-render the batch cards
            renderAlumniBatchCards();

            // Reload alumni data to reflect deletions
            await loadAlumni();

            // Log the activity
            await logRecentActivity(
                `Deleted batch ${yearStr}`,
                'alumni',
                { graduationYear: yearStr }
            );

            alert('Batch deleted successfully.');
        } else {
            throw new Error(result.message || 'Failed to delete batch.');
        }
    } catch (error) {
        console.error('Delete batch error:', error);
        alert('Error deleting batch: ' + (error.message || 'Please try again.'));
    }
}

async function loadAlumni(sort = alumniSort) {
    const hasAlumniUi = Boolean(
        document.getElementById('totalAlumni') ||
        document.getElementById('alumniTableBody') ||
        document.getElementById('alumniBatchDetailsState') ||
        document.getElementById('alumniCollegeDetailsState') ||
        document.getElementById('alumniCourseDetailsState') ||
        document.getElementById('yearDistribution') ||
        document.getElementById('alumniStatusChart') ||
        document.getElementById('collegeDistributionChart') ||
        document.getElementById('courseDistributionChart')
    );
    if (!hasAlumniUi) return;

    alumniSort = sort;

    const filters = {};
    if (isAlumniBatchPage() || isAlumniCollegePage() || isAlumniCoursePage()) {
        const yearParam = selectedAlumniBatchYear || getSelectedBatchYearFromUrl();
        if (yearParam) filters.year = yearParam;
    }
    if (isAlumniCollegePage() || isAlumniCoursePage()) {
        const collegeParam = selectedAlumniCollege || getSelectedCollegeFromUrl();
        if (collegeParam) filters.college = collegeParam;
    }
    if (isAlumniCoursePage()) {
        const courseParam = selectedAlumniCourse || getSelectedCourseFromUrl();
        if (courseParam) filters.course = courseParam;
    }

    try {
        const response = await fetch(getAlumniListUrl(sort, filters), {
            credentials: 'include',
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error('Unable to load alumni.');
        }

        const result = await response.json();
        alumniData = Array.isArray(result.alumni) ? result.alumni : [];
    } catch (error) {
        alumniData = [];
    }

    const groupedYears = alumniBatches.map((batch) => String(batch.graduationYear ?? ''));
    if (selectedAlumniBatchYear !== null && groupedYears.length > 0 && !groupedYears.includes(String(selectedAlumniBatchYear))) {
        const yearFromUrl = getSelectedBatchYearFromUrl();
        if (!yearFromUrl || String(selectedAlumniBatchYear) !== yearFromUrl) {
            selectedAlumniBatchYear = null;
            selectedAlumniCollege = null;
            selectedAlumniCourse = null;
        }
    }

    const colleges = getBatchCollegeSummaries();
    if (selectedAlumniCollege && !colleges.some((entry) => entry.college === selectedAlumniCollege)) {
        selectedAlumniCollege = null;
        selectedAlumniCourse = null;
    }

    const courses = getCollegeCourseSummaries();
    if (selectedAlumniCourse && !courses.some((entry) => entry.course === selectedAlumniCourse)) {
        selectedAlumniCourse = null;
    }

    renderAlumniBatchCards();
    renderSelectedBatchDetails();
    updateStats();
    updateAlumniManagementStats();
    populateDashboardBatchFilters();
    populateCourseCollegeFilter();
    renderAlumniStatusChart();
    renderYearDistribution();
    renderCollegeDistributionChart();
    renderCourseDistributionChart();
}

function handleAlumniSortChange() {
    const sortSelect = document.getElementById('alumniSort');
    const nextSort = sortSelect ? sortSelect.value : 'last_name_asc';
    void loadAlumni(nextSort);
}

function getAlumniDisplayName(alumni) {
    if (!alumni || typeof alumni !== 'object') return 'Unknown Alumni';
    const fullName = `${alumni.firstName ?? ''} ${alumni.lastName ?? ''}`.trim();
    return fullName || alumni.name || alumni.email || 'Unknown Alumni';
}

function getSelectedBatchYearFromUrl() {
    try {
        const url = new URL(window.location.href);
        return (url.searchParams.get('year') || '').trim();
    } catch (error) {
        return '';
    }
}

function renderEvents() {
    const eventsContainer = document.getElementById('eventsList');
    if (!eventsContainer) return;

    if (eventsData.length === 0) {
        eventsContainer.innerHTML = '<p style="text-align: center; color: #7f8c8d; padding: 40px;">No events scheduled</p>';
        return;
    }

    eventsContainer.innerHTML = `
        <table>
            <thead><tr><th>Event Name</th><th>Date</th><th>Location</th><th>Attendees</th><th>Actions</th></tr></thead>
            <tbody>
                ${eventsData.map(event => `
                    <tr>
                        <td>${event.name}</td>
                        <td>${event.date}</td>
                        <td>${event.location}</td>
                        <td>${event.attendees}</td>
                        <td><button class="btn-danger" onclick="deleteEvent(${event.id})" style="padding: 4px 10px;">Delete</button></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderActivities() {
    const activitiesContainer = document.getElementById('recentActivities');
    if (!activitiesContainer) return;

    if (!Array.isArray(activities) || activities.length === 0) {
        activitiesContainer.innerHTML = '<p style="text-align: center; color: #7f8c8d; padding: 20px;">No recent activities yet.</p>';
        return;
    }

    activitiesContainer.innerHTML = `
        <table>
            <tbody>
                ${activities.slice(0, 5).map(activity => `
                    <tr><td><i class="fas fa-circle" style="font-size: 8px; color: #1e4a76; margin-right: 10px;"></i> ${activity.action}</td><td style="text-align: right; color: #7f8c8d;">${formatActivityReference(activity)}</td></tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function formatActivityReference(activity) {
    if (!activity || typeof activity !== 'object') return '';

    const datePart = activity.activity_date || '';
    const timePart = activity.activity_time || '';
    const relativePart = activity.readable_time || activity.time || '';

    if (datePart && timePart) {
        // Combine date + time, treat as UTC, then convert to PH time
        const utcString = `${datePart}T${timePart}Z`;
        const localDate = new Date(utcString);

        if (!isNaN(localDate)) {
            return localDate.toLocaleString('en-PH', {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }
        return `${datePart} ${timePart}`; // fallback
    }

    if (datePart) return datePart;
    if (timePart) return timePart;
    return relativePart;
}

async function loadRecentActivities(limit = 5) {
    if (!document.getElementById('recentActivities')) return;
    try {
        const response = await fetch(getRecentActivitiesUrl(limit), {
            credentials: 'include',
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error('Unable to load recent activities.');
        }

        const result = await response.json();
        activities = Array.isArray(result.activities) ? result.activities : [];
    } catch (error) {
        activities = [];
    }

    renderActivities();
}

function hasSurveyPageUi() {
    return Boolean(
        document.getElementById('surveyWizard') ||
        document.getElementById('staffSurveyList') ||
        document.getElementById('surveySelector') ||
        document.getElementById('surveyResponsesBody') ||
        document.getElementById('surveyStats') ||
        document.getElementById('surveyDetails') ||
        document.getElementById('surveyQuestionsList') ||
        document.getElementById('surveyResponseFormFields') ||
        document.getElementById('surveyStatusPanel')
    );
}

async function loadSurveys() {
    if (!hasSurveyPageUi()) return;

    try {
        const response = await fetch(getSurveyListUrl(), {
            credentials: 'include',
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error('Unable to load surveys.');
        }

        const result = await response.json();
        surveysData = Array.isArray(result.data) ? result.data : [];
    } catch (error) {
        surveysData = [];
    }

    renderSurveySelector();
    const selector = document.getElementById('surveySelector');
    if (selector) {
        const urlSurveyId = new URL(window.location.href).searchParams.get('surveyId');
        if (urlSurveyId && Array.isArray(surveysData) && surveysData.some((survey) => String(survey.id) === String(urlSurveyId))) {
            selector.value = String(urlSurveyId);
        }
    }
    initializeSurveyEditorPage();
    await onSurveySelectionChange();
    renderStaffSurveyFeed();
}

async function loadSurveyResponsesAndStats(surveyId) {
    currentSurveyResponsesPage = 1;
    const needsResponsesUi = Boolean(
        document.getElementById('surveyResponsesBody') ||
        document.getElementById('surveyStats') ||
        document.getElementById('surveyResponseFormFields')
    );
    if (!surveyId || !needsResponsesUi) {
        currentSurveyResponses = [];
        currentSurveyStats = null;
        currentSurveyBatchStats = null;
        return;
    }

    try {
        const responsesResponse = await fetch(getSurveyResponsesUrl(surveyId), {
            credentials: 'include',
            cache: 'no-store'
        });

        const responsesResult = await responsesResponse.json();

        currentSurveyResponses = responsesResponse.ok && responsesResult.success && Array.isArray(responsesResult.responses)
            ? responsesResult.responses
            : [];
        currentSurveyStats = {
            totalResponses: currentSurveyResponses.length
        };
        currentSurveyBatchStats = null;
    } catch (error) {
        currentSurveyResponses = [];
        currentSurveyStats = null;
        currentSurveyBatchStats = null;
    }
}

async function logRecentActivity(action, category = 'general', details = {}) {
    try {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('category', category);
        formData.append('user_type', 'staff');
        formData.append('details', JSON.stringify(details));

        const response = await fetch(getLogActivityUrl(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Unable to log activity.');
        }
    } catch (error) {
        return;
    }

    await loadRecentActivities();
}

function renderYearDistribution() {
    const container = document.getElementById('yearDistribution');
    if (!container) return;

    const heroMetaEl = document.getElementById('yearDistributionHeroMeta');

    const distribution = {};
    alumniBatches.forEach((batch) => {
        const year = getBatchYearLabel(batch.graduationYear);
        if (year && !(year in distribution)) {
            distribution[year] = 0;
        }
    });
    alumniData.forEach((alumni) => {
        const year = String(alumni.graduationYear || 'Unassigned').trim() || 'Unassigned';
        distribution[year] = (distribution[year] || 0) + 1;
    });

    const entries = Object.entries(distribution)
        .sort(([yearA], [yearB]) => String(yearA).localeCompare(String(yearB), undefined, { numeric: true }))
        .slice(-10);

    if (entries.length === 0) {
        if (heroMetaEl) heroMetaEl.innerHTML = '';
        container.innerHTML = '<div class="chart-empty-state">No alumni records yet.</div>';
        return;
    }

    const totalAcrossBatches = entries.reduce((sum, [, count]) => sum + count, 0);
    const peakEntry = entries.reduce((best, curr) => (curr[1] > best[1] ? curr : best), entries[0]);
    const avgPerBatch = Math.round(totalAcrossBatches / entries.length);

    if (heroMetaEl) {
        heroMetaEl.innerHTML = `
            <div class="hero-chart-stat">
                <span class="hero-chart-stat-label">Batches</span>
                <strong class="hero-chart-stat-value">${entries.length}</strong>
            </div>
            <div class="hero-chart-stat">
                <span class="hero-chart-stat-label">Peak Class</span>
                <strong class="hero-chart-stat-value">${escapeHtml(peakEntry[0])} <em>(${peakEntry[1]})</em></strong>
            </div>
            <div class="hero-chart-stat">
                <span class="hero-chart-stat-label">Avg / Batch</span>
                <strong class="hero-chart-stat-value">${avgPerBatch}</strong>
            </div>
        `;
    }

    const maxCount = Math.max(...entries.map(([, count]) => count), 1);
    let majorStep = 25;
    if (maxCount <= 10) majorStep = 2;
    else if (maxCount <= 25) majorStep = 5;
    else if (maxCount <= 50) majorStep = 10;
    else if (maxCount <= 100) majorStep = 25;
    else if (maxCount <= 250) majorStep = 50;
    else majorStep = Math.ceil(maxCount / 400) * 100;

    const yAxisMax = Math.max(Math.ceil((maxCount * 1.15) / majorStep) * majorStep, majorStep * 4);

    let gridLinesHtml = '';
    for (let step = 0; step <= yAxisMax; step += majorStep) {
        const bottomPct = (step / yAxisMax) * 100;
        gridLinesHtml += `<div class="vbar-grid-line vbar-grid-major" style="bottom: ${bottomPct}%;"></div>`;
    }

    let yLabelsHtml = '';
    for (let step = yAxisMax; step >= 0; step -= majorStep) {
        yLabelsHtml += `<div class="vbar-y-label">${step}</div>`;
    }

    const padX = entries.length === 1 ? 50 : 6;
    const points = entries.map(([year, count], index) => {
        const xPct = entries.length === 1
            ? 50
            : padX + (index / (entries.length - 1)) * (100 - padX * 2);
        const yPct = Math.max(Math.min((count / yAxisMax) * 100, 98), 0);
        const svgX = Number(xPct.toFixed(2));
        const svgY = Number((100 - yPct).toFixed(2));
        return { year, count, xPct, yPct, svgX, svgY };
    });

    const polylinePoints = points.length === 1
        ? `0,${points[0].svgY} 100,${points[0].svgY}`
        : points.map((pt) => `${pt.svgX},${pt.svgY}`).join(' ');

    const firstX = points.length === 1 ? 0 : points[0].svgX;
    const lastX = points.length === 1 ? 100 : points[points.length - 1].svgX;
    const areaPoints = `${firstX},100 ${polylinePoints} ${lastX},100`;

    let nodesHtml = '';
    points.forEach((pt) => {
        const isSelectedBatch = selectedDashboardBatchFilter !== 'all' && String(selectedDashboardBatchFilter) === String(pt.year);
        const isDimmedBatch = selectedDashboardBatchFilter !== 'all' && !isSelectedBatch;
        nodesHtml += `
            <div class="line-chart-node ${isSelectedBatch ? 'is-selected' : ''} ${isDimmedBatch ? 'is-dimmed' : ''}"
                 style="left: ${pt.xPct}%;"
                 title="Class of ${escapeHtml(pt.year)}: ${pt.count} alumni (click to filter by this batch)"
                 onclick='toggleDashboardBatchFromBar(${JSON.stringify(String(pt.year))})'>
                <div class="line-chart-guide"></div>
                <div class="line-chart-point-anchor" style="bottom: ${pt.yPct}%;">
                    <span class="line-chart-val">${pt.count}</span>
                    <span class="line-chart-dot"></span>
                </div>
            </div>
        `;
    });

    let xLabelsHtml = '';
    points.forEach((pt) => {
        const isSelectedBatch = selectedDashboardBatchFilter !== 'all' && String(selectedDashboardBatchFilter) === String(pt.year);
        xLabelsHtml += `
            <button type="button"
                    class="line-chart-x-label ${isSelectedBatch ? 'is-selected' : ''}"
                    style="left: ${pt.xPct}%;"
                    onclick='toggleDashboardBatchFromBar(${JSON.stringify(String(pt.year))})'>
                ${escapeHtml(pt.year)}
            </button>
        `;
    });

    container.innerHTML = `
        <div class="hero-line-chart">
            <div class="vbar-y-axis">
                <div class="vbar-y-labels">${yLabelsHtml}</div>
                <div class="vbar-y-spacer"></div>
            </div>
            <div class="line-chart-plot-wrap">
                <div class="line-chart-plot">
                    ${gridLinesHtml}
                    <svg class="line-chart-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="heroLineAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#2563eb" stop-opacity="0.18"/>
                                <stop offset="100%" stop-color="#2563eb" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        <polygon class="line-chart-area" points="${areaPoints}" fill="url(#heroLineAreaGrad)"></polygon>
                        <polyline class="line-chart-path" points="${polylinePoints}" fill="none" vector-effect="non-scaling-stroke"></polyline>
                    </svg>
                    <div class="line-chart-nodes">${nodesHtml}</div>
                </div>
                <div class="line-chart-x-axis">${xLabelsHtml}</div>
            </div>
        </div>
    `;
}

function getDashboardBatchOptions() {
    const years = Array.from(new Set([
        ...alumniBatches.map((batch) => getBatchYearLabel(batch.graduationYear)),
        ...alumniData.map((alumni) => getBatchYearLabel(alumni.graduationYear))
    ]))
        .filter(Boolean)
        .sort((a, b) => String(b).localeCompare(String(a), undefined, { numeric: true }));

    return years;
}

function updateDashboardBatchFilterMeta() {
    const isFiltered = selectedDashboardBatchFilter !== 'all';
    const filteredRecords = getFilteredDashboardAlumni(selectedDashboardBatchFilter);
    const badgeLabel = isFiltered ? `Batch ${selectedDashboardBatchFilter}` : 'All Batches';

    document.querySelectorAll('[data-dashboard-batch-badge]').forEach((badge) => {
        badge.textContent = badgeLabel;
        badge.classList.toggle('is-filtered', isFiltered);
    });

    const summaryEl = document.getElementById('dashboardBatchScopeSummary');
    if (summaryEl) {
        summaryEl.textContent = isFiltered
            ? `${filteredRecords.length} of ${alumniData.length} alumni`
            : `${alumniData.length} total alumni`;
    }

    const resetBtn = document.getElementById('dashboardResetBatchBtn');
    if (resetBtn) {
        resetBtn.hidden = !isFiltered;
    }
}

function populateDashboardBatchFilters() {
    const options = getDashboardBatchOptions();

    if (selectedDashboardBatchFilter !== 'all' && !options.includes(selectedDashboardBatchFilter)) {
        selectedDashboardBatchFilter = 'all';
    }
    selectedCollegeBatchFilter = selectedDashboardBatchFilter;
    selectedCourseBatchFilter = selectedDashboardBatchFilter;

    const dropdowns = [
        { element: document.getElementById('dashboardBatchFilter'), value: selectedDashboardBatchFilter },
        { element: document.getElementById('collegeBatchFilter'), value: selectedCollegeBatchFilter },
        { element: document.getElementById('courseBatchFilter'), value: selectedCourseBatchFilter }
    ];

    dropdowns.forEach(({ element, value }) => {
        if (!element) return;

        element.innerHTML = '<option value="all">All Batches</option>' +
            options.map((year) => `<option value="${escapeHtml(year)}">Batch ${escapeHtml(year)}</option>`).join('');
        element.value = options.includes(value) || value === 'all' ? value : 'all';
    });

    updateDashboardBatchFilterMeta();
}

function populateCourseCollegeFilter() {
    const select = document.getElementById('courseCollegeFilter');
    if (!select) return;

    const batchScopedRecords = getFilteredDashboardAlumni(selectedDashboardBatchFilter);
    const colleges = Array.from(new Set(
        batchScopedRecords
            .map((alumni) => String(alumni?.college || '').trim())
            .filter(Boolean)
    )).sort((a, b) => a.localeCompare(b));

    select.innerHTML = '<option value="all">All Colleges</option>' +
        colleges.map((college) => `<option value="${escapeHtml(college)}">${escapeHtml(college)}</option>`).join('');

    if (selectedCourseCollegeFilter !== 'all' && !colleges.includes(selectedCourseCollegeFilter)) {
        selectedCourseCollegeFilter = 'all';
    }

    select.value = selectedCourseCollegeFilter;
}

function getFilteredDashboardAlumni(batchFilter = selectedDashboardBatchFilter) {
    if (!Array.isArray(alumniData)) return [];
    if (!batchFilter || batchFilter === 'all') return alumniData;

    return alumniData.filter((alumni) => getBatchYearLabel(alumni.graduationYear) === String(batchFilter));
}

function getFilteredCourseDashboardAlumni() {
    let records = getFilteredDashboardAlumni(selectedDashboardBatchFilter);

    if (selectedCourseCollegeFilter !== 'all') {
        records = records.filter((alumni) => String(alumni?.college || '').trim() === selectedCourseCollegeFilter);
    }

    return records;
}

function buildDistributionEntries(records, key) {
    const distribution = {};

    records.forEach((record) => {
        const label = String(record?.[key] || '').trim() || 'Unassigned';
        distribution[label] = (distribution[label] || 0) + 1;
    });

    return Object.entries(distribution)
        .sort(([, countA], [, countB],) => countB - countA || 0);
}

function getChartPalette() {
    return ['#0f172a', '#2563eb', '#0284c7', '#0d9488', '#4f46e5', '#d97706', '#64748b', '#94a3b8'];
}

function renderDistributionChart(containerId, records, titleLabel) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!Array.isArray(records) || records.length === 0) {
        container.innerHTML = `<div class="chart-empty-state">No alumni records found for ${escapeHtml(titleLabel)}.</div>`;
        return;
    }

    const entries = titleLabel === 'college'
        ? buildDistributionEntries(records, 'college')
        : buildDistributionEntries(records, 'course');

    if (entries.length === 0) {
        container.innerHTML = `<div class="chart-empty-state">No ${escapeHtml(titleLabel)} data found for this batch.</div>`;
        return;
    }

    const maxCount = Math.max(...entries.map(([, count]) => count), 1);
    container.innerHTML = `
        <div class="distribution-chart">
            <div class="distribution-chart-summary">Showing ${records.length} alumni record(s).</div>
            ${entries.map(([label, count]) => `
                <div class="distribution-row">
                    <div class="distribution-label-line">
                        <strong>${escapeHtml(label)}</strong>
                        <span>${count} alumni</span>
                    </div>
                    <div class="distribution-bar-track">
                        <div class="distribution-bar-fill" style="width: ${(count / maxCount) * 100}%;"></div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderPieDistributionChart(containerId, records, titleLabel) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!Array.isArray(records) || records.length === 0) {
        container.innerHTML = `<div class="chart-empty-state">No alumni records found for ${escapeHtml(titleLabel)}.</div>`;
        return;
    }

    const entries = titleLabel === 'college'
        ? buildDistributionEntries(records, 'college')
        : buildDistributionEntries(records, 'course');

    if (entries.length === 0) {
        container.innerHTML = `<div class="chart-empty-state">No ${escapeHtml(titleLabel)} data found for this batch.</div>`;
        return;
    }

    const total = records.length;
    const palette = getChartPalette();
    let start = 0;
    const segments = entries.map(([label, count], index) => {
        const size = total > 0 ? (count / total) * 100 : 0;
        const end = start + size;
        const segment = {
            label,
            count,
            color: palette[index % palette.length],
            percentage: total > 0 ? Math.round((count / total) * 100) : 0,
            gradient: `${palette[index % palette.length]} ${start}% ${end}%`
        };
        start = end;
        return segment;
    });

    container.innerHTML = `
        <div class="pie-chart-layout">
            <div class="pie-chart-donut" style="background: conic-gradient(${segments.map((segment) => segment.gradient).join(', ')});">
                <div class="pie-chart-center">
                    <span class="pie-chart-total">${total}</span>
                    <span class="pie-chart-label">Alumni</span>
                </div>
            </div>
            <div class="status-legend chart-legend-scroll">
                <div class="distribution-chart-summary">Showing ${records.length} record${records.length === 1 ? '' : 's'}</div>
                ${segments.map((segment) => `
                    <div class="status-legend-item">
                        <span class="status-legend-swatch" style="background:${segment.color};"></span>
                        <div class="status-legend-copy">
                            <div class="status-legend-line">
                                <strong>${escapeHtml(segment.label)}</strong>
                                <div class="status-legend-metrics">
                                    <span class="status-legend-value">${segment.count}</span>
                                    <span class="status-legend-pct">${segment.percentage}%</span>
                                </div>
                            </div>
                            <div class="status-legend-bar">
                                <div class="status-legend-bar-fill" style="width:${Math.max(segment.percentage, segment.count > 0 ? 3 : 0)}%;background:${segment.color};"></div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function renderCollegeDistributionChart() {
    renderPieDistributionChart(
        'collegeDistributionChart',
        getFilteredDashboardAlumni(selectedDashboardBatchFilter),
        'college'
    );
}

function renderCourseDistributionChart() {
    renderPieDistributionChart(
        'courseDistributionChart',
        getFilteredCourseDashboardAlumni(),
        'course'
    );
}

function applyCentralizedDashboardBatchFilter(nextBatch = 'all') {
    selectedDashboardBatchFilter = nextBatch || 'all';
    selectedCollegeBatchFilter = selectedDashboardBatchFilter;
    selectedCourseBatchFilter = selectedDashboardBatchFilter;

    const batchSelect = document.getElementById('dashboardBatchFilter');
    if (batchSelect && batchSelect.value !== selectedDashboardBatchFilter) {
        batchSelect.value = selectedDashboardBatchFilter;
    }

    populateCourseCollegeFilter();
    updateDashboardBatchFilterMeta();
    renderYearDistribution();
    renderAlumniStatusChart();
    renderCollegeDistributionChart();
    renderCourseDistributionChart();
}

function handleDashboardBatchFilterChange() {
    const select = document.getElementById('dashboardBatchFilter');
    applyCentralizedDashboardBatchFilter(select ? select.value : 'all');
}

function resetDashboardBatchFilter() {
    applyCentralizedDashboardBatchFilter('all');
}

function toggleDashboardBatchFromBar(year) {
    const normalized = String(year || '').trim();
    if (!normalized) return;
    const nextBatch = selectedDashboardBatchFilter === normalized ? 'all' : normalized;
    applyCentralizedDashboardBatchFilter(nextBatch);
}

function handleDashboardCollegeBatchChange() {
    const select = document.getElementById('collegeBatchFilter');
    applyCentralizedDashboardBatchFilter(select ? select.value : 'all');
}

function handleDashboardCourseBatchChange() {
    const select = document.getElementById('courseBatchFilter');
    applyCentralizedDashboardBatchFilter(select ? select.value : 'all');
}

function handleDashboardCourseCollegeChange() {
    const select = document.getElementById('courseCollegeFilter');
    selectedCourseCollegeFilter = select ? select.value : 'all';
    renderCourseDistributionChart();
}

function renderAlumniStatusChart() {
    const container = document.getElementById('alumniStatusChart');
    if (!container) return;

    const records = getFilteredDashboardAlumni(selectedDashboardBatchFilter);

    if (!Array.isArray(records) || records.length === 0) {
        container.innerHTML = `<div class="chart-empty-state">${selectedDashboardBatchFilter === 'all' ? 'No alumni records yet.' : `No alumni records found for Batch ${escapeHtml(selectedDashboardBatchFilter)}.`}</div>`;
        return;
    }

    const segments = [
        { key: 'active', label: 'Active', color: '#0f172a' },
        { key: 'pending', label: 'Pending', color: '#d97706' },
        { key: 'inactive', label: 'Inactive', color: '#94a3b8' },
        { key: 'incomplete', label: 'Incomplete', color: '#2563eb' }
    ].map((segment) => ({
        ...segment,
        count: records.filter((alumni) => String(alumni.status || '').toLowerCase() === segment.key).length
    }));

    const total = records.length;
    let start = 0;
    const gradientParts = segments.map((segment) => {
        const size = total > 0 ? (segment.count / total) * 100 : 0;
        const end = start + size;
        const part = `${segment.color} ${start}% ${end}%`;
        start = end;
        return part;
    });

    container.innerHTML = `
        <div class="status-chart-layout">
            <div class="status-donut" style="background: conic-gradient(${gradientParts.join(', ')});">
                <div class="status-donut-center">
                    <span class="status-donut-total">${total}</span>
                    <span class="status-donut-label">Total</span>
                </div>
            </div>
            <div class="status-legend chart-legend-scroll">
                ${segments.map((segment) => {
                    const percentage = total > 0 ? Math.round((segment.count / total) * 100) : 0;
                    return `
                        <div class="status-legend-item">
                            <span class="status-legend-swatch" style="background:${segment.color};"></span>
                            <div class="status-legend-copy">
                                <div class="status-legend-line">
                                    <strong>${segment.label}</strong>
                                    <div class="status-legend-metrics">
                                        <span class="status-legend-value">${segment.count}</span>
                                        <span class="status-legend-pct">${percentage}%</span>
                                    </div>
                                </div>
                                <div class="status-legend-bar">
                                    <div class="status-legend-bar-fill" style="width:${Math.max(percentage, segment.count > 0 ? 3 : 0)}%;background:${segment.color};"></div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatReadableDateTime(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value || '');
    }

    const monthLabels = ['Jan.', 'Feb.', 'Mar.', 'Apr.', 'May', 'Jun.', 'Jul.', 'Aug.', 'Sept.', 'Oct.', 'Nov.', 'Dec.'];
    const month = monthLabels[date.getMonth()] || '';
    const day = date.getDate();
    const year = date.getFullYear();
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const hours24 = date.getHours();
    const suffix = hours24 >= 12 ? 'PM' : 'AM';
    const hours12 = hours24 % 12 || 12;

    return `${month} ${day}, ${year} ${hours12}:${minutes}${suffix}`;
}

let newsPosts = [];
let editingNewsId = null;

async function loadNewsPosts() {
    const list = document.getElementById('staffNewsList');
    if (!list && !document.getElementById('alumniNewsGrid')) return;

    try {
        const response = await fetch(getNewsListUrl(), {
            credentials: 'include',
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error('Unable to load news.');
        }

        const result = await response.json();
        newsPosts = Array.isArray(result.news) ? result.news : [];
    } catch (error) {
        newsPosts = [];
    }

    renderStaffNewsList();
}

function renderStaffNewsList() {
    const container = document.getElementById('staffNewsList');
    if (!container) return;

    if (!Array.isArray(newsPosts) || newsPosts.length === 0) {
        container.innerHTML = '<div class="event-state-card"><i class="fas fa-newspaper"></i><h4>No news posts yet</h4><p>Create your first update on the right side. It will instantly appear on the alumni news page.</p></div>';
        return;
    }

    container.innerHTML = newsPosts.map((post, index) => `
        <article class="staff-news-card ${index === 0 ? '' : 'is-collapsed'}" data-news-id="${Number(post.id || 0)}">
            <div class="staff-news-card-header">
                <div class="staff-news-header-main">
                    <h4 class="staff-news-card-title">${escapeHtml(post.title || 'Untitled post')}</h4>
                    <div class="staff-news-card-meta">
                        <div>${escapeHtml(post.author || 'Staff Office')}</div>
                        <div>${escapeHtml(formatReadableDateTime(post.created_at || ''))}</div>
                    </div>
                </div>
                <div class="staff-news-toolbar">
                    <button class="staff-news-toggle" type="button" onclick="toggleNewsCard(${Number(post.id || 0)})" aria-label="Expand or collapse news post">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <button class="staff-news-edit" type="button" onclick="startEditNewsPost(${Number(post.id || 0)})" aria-label="Edit news post">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="staff-news-delete" type="button" onclick="deleteNewsPost(${Number(post.id || 0)})" aria-label="Delete news post">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="staff-news-card-content">
                <div class="staff-news-card-copy">${escapeHtml(post.content || '')}</div>
                ${post.link_url ? `<a class="staff-news-card-link" href="${escapeHtml(post.link_url)}" target="_blank" rel="noopener noreferrer"><i class="fas fa-link"></i><span>Open attached link</span></a>` : ''}
                ${post.image_url ? `<img class="staff-news-card-image" src="${escapeHtml(post.image_url)}" alt="${escapeHtml(post.title || 'News image')}">` : ''}
            </div>
        </article>
    `).join('');
}

function resetNewsForm() {
    const form = document.getElementById('staffNewsForm');
    const idInput = document.getElementById('staffNewsId');
    const submitLabel = document.getElementById('staffNewsSubmitLabel');
    const cancelButton = document.getElementById('staffNewsCancelEdit');
    const preview = document.getElementById('staffNewsImagePreview');
    const removeImage = document.getElementById('staffNewsRemoveImage');

    editingNewsId = null;
    if (form) form.reset();
    if (idInput) idInput.value = '';
    if (submitLabel) submitLabel.textContent = 'Post News';
    if (cancelButton) cancelButton.hidden = true;
    if (preview) {
        preview.hidden = true;
        preview.innerHTML = '';
    }
    if (removeImage) removeImage.checked = false;
}

function startEditNewsPost(newsId) {
    const post = newsPosts.find((item) => Number(item.id) === Number(newsId));
    if (!post) return;

    const idInput = document.getElementById('staffNewsId');
    const title = document.getElementById('staffNewsTitle');
    const link = document.getElementById('staffNewsLink');
    const content = document.getElementById('staffNewsContent');
    const submitLabel = document.getElementById('staffNewsSubmitLabel');
    const cancelButton = document.getElementById('staffNewsCancelEdit');
    const preview = document.getElementById('staffNewsImagePreview');
    const removeImage = document.getElementById('staffNewsRemoveImage');
    const imageInput = document.getElementById('staffNewsImage');

    editingNewsId = Number(newsId);
    if (idInput) idInput.value = String(newsId);
    if (title) title.value = post.title || '';
    if (link) link.value = post.link_url || '';
    if (content) content.value = post.content || '';
    if (submitLabel) submitLabel.textContent = 'Save Changes';
    if (cancelButton) cancelButton.hidden = false;
    if (removeImage) removeImage.checked = false;
    if (imageInput) imageInput.value = '';

    if (preview) {
        if (post.image_url) {
            preview.hidden = false;
            preview.innerHTML = `<img src="${escapeHtml(post.image_url)}" alt="Current news image">`;
        } else {
            preview.hidden = true;
            preview.innerHTML = '';
        }
    }

    const composerCard = document.querySelector('.news-composer-card');
    if (composerCard) {
        composerCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function toggleNewsCard(newsId) {
    const card = document.querySelector(`.staff-news-card[data-news-id="${Number(newsId)}"]`);
    if (!card) return;
    card.classList.toggle('is-collapsed');
}

function setupNewsImagePreview() {
    const fileInput = document.getElementById('staffNewsImage');
    const preview = document.getElementById('staffNewsImagePreview');
    if (!fileInput || !preview || fileInput.dataset.bound === 'true') return;

    fileInput.dataset.bound = 'true';
    fileInput.addEventListener('change', () => {
        const [file] = fileInput.files || [];
        if (!file) {
            preview.hidden = true;
            preview.innerHTML = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            preview.hidden = false;
            preview.innerHTML = `<img src="${reader.result}" alt="Selected news image preview">`;
        };
        reader.readAsDataURL(file);
    });
}

function setupNewsForm() {
    const form = document.getElementById('staffNewsForm');
    if (!form || form.dataset.bound === 'true') return;

    form.dataset.bound = 'true';
    setupNewsImagePreview();
    const cancelButton = document.getElementById('staffNewsCancelEdit');
    if (cancelButton) {
        cancelButton.addEventListener('click', () => {
            resetNewsForm();
        });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';
        if (submitBtn) submitBtn.disabled = true;

        const idInput = document.getElementById('staffNewsId');
        const title = document.getElementById('staffNewsTitle');
        const link = document.getElementById('staffNewsLink');
        const content = document.getElementById('staffNewsContent');
        const image = document.getElementById('staffNewsImage');
        const removeImage = document.getElementById('staffNewsRemoveImage');
        if (!title || !link || !content || !image || !idInput || !removeImage) {
            form.dataset.submitting = 'false';
            if (submitBtn) submitBtn.disabled = false;
            return;
        }
        const titleValue = title.value.trim();
        const isEditing = idInput.value.trim() !== '';

        const ac = await ensureStaffActionConfirm();
        if (ac) {
            const confirmed = await ac.basic({
                title: isEditing ? 'Update News Post?' : 'Publish News Post?',
                message: isEditing
                    ? 'Are you sure you want to save changes to this news announcement?'
                    : 'Are you sure you want to publish this news announcement to alumni?',
                record: titleValue || 'News Announcement',
                confirmText: isEditing ? 'Save Changes' : 'Publish News',
                icon: 'fa-newspaper'
            });
            if (!confirmed) {
                form.dataset.submitting = 'false';
                if (submitBtn) submitBtn.disabled = false;
                return;
            }
        }

        const formData = new FormData();
        formData.append('title', titleValue);
        formData.append('link_url', link.value.trim());
        formData.append('content', content.value.trim());
        formData.append('remove_image', removeImage.checked ? '1' : '0');
        if (image.files && image.files[0]) {
            formData.append('image', image.files[0]);
        }

        try {
            const response = await fetch(isEditing ? getUpdateNewsUrl(idInput.value.trim()) : getCreateNewsUrl(), {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                alert(result.message || 'Unable to post news right now.');
                form.dataset.submitting = 'false';
                if (submitBtn) submitBtn.disabled = false;
                return;
            }

            resetNewsForm();
            await loadNewsPosts();
            void logRecentActivity(isEditing ? `News updated: ${titleValue}` : `News posted: ${titleValue}`, 'news', { title: titleValue });
        } catch (error) {
            alert(isEditing ? 'Unable to update news right now.' : 'Unable to post news right now.');
        } finally {
            form.dataset.submitting = 'false';
            if (submitBtn) submitBtn.disabled = false;
        }
    });
}

async function deleteNewsPost(newsId) {
    if (!newsId) return;
    const ac = await ensureStaffActionConfirm();
    const confirmed = ac
        ? await ac.high({
            title: 'Permanently Delete News Post?',
            message: 'You are about to permanently delete this news announcement and any attached photo.',
            record: `News Post #${newsId}`,
            consequence: 'Alumni will no longer see this update and this action cannot be undone.',
            requiredPhrase: 'DELETE',
            confirmText: 'Delete Permanently'
        })
        : false;
    if (!confirmed) return;

    try {
        const response = await fetch(getDeleteNewsUrl(newsId), {
            method: 'DELETE',
            credentials: 'same-origin'
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to delete this news post right now.');
            return;
        }

        if (Number(editingNewsId) === Number(newsId)) {
            resetNewsForm();
        }
        await loadNewsPosts();
        void logRecentActivity(`News post deleted`, 'news', { id: newsId });
    } catch (error) {
        alert('Unable to delete this news post right now.');
    }
}

// CRUD Operations
async function addAlumni() {
    if (!selectedAlumniBatchYear) {
        alert('Please open a batch first.');
        return;
    }
    if (!selectedAlumniCollege || !selectedAlumniCourse) {
        alert('Please open a college and course before adding alumni.');
        return;
    }

    const lastName = document.getElementById('alumniLastName').value.trim();
    const firstName = document.getElementById('alumniFirstName').value.trim();
    const graduationYear = String(selectedAlumniBatchYear);
    const status = document.getElementById('alumniStatus').value;
    const college = String(selectedAlumniCollege).trim();
    const course = String(selectedAlumniCourse).trim();

    if (!lastName || !firstName || !graduationYear || !status || !college || !course) {
        alert('Please fill all alumni fields');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('lastName', lastName);
        formData.append('firstName', firstName);
        formData.append('graduationYear', graduationYear);
        formData.append('status', status);
        formData.append('college', college);
        formData.append('course', course);

        const response = await fetch(getCreateAlumniUrl(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to add alumni right now.');
            return;
        }
    } catch (error) {
        alert('Unable to add alumni right now.');
        return;
    }

    closeAddAlumniModal();

    document.getElementById('alumniLastName').value = '';
    document.getElementById('alumniFirstName').value = '';
    document.getElementById('alumniGraduationYear').value = graduationYear;
    document.getElementById('alumniStatus').value = 'active';
    document.getElementById('alumniCollege').value = college;
    document.getElementById('alumniCourse').value = course;

    await loadAlumni(alumniSort);
    await loadAlumniBatches();
    renderAlumniBatchCards();
    renderSelectedBatchDetails();
    void logRecentActivity(`${firstName} ${lastName} was added to alumni records`, 'alumni', { name: `${firstName} ${lastName}`, status, college, course });
}

async function addBatch() {
    const input = document.getElementById('batchGraduationYear');
    if (!input) return;

    const graduationYear = input.value.trim();
    if (!/^\d{4}$/.test(graduationYear)) {
        alert('Please enter a valid 4-digit batch year.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('graduationYear', graduationYear);

        const response = await fetch(getCreateBatchUrl(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to create batch right now.');
            return;
        }
    } catch (error) {
        alert('Unable to create batch right now.');
        return;
    }

    closeAddBatchModal();
    input.value = '';
    await loadAlumniBatches();
    renderAlumniBatchCards();
    window.location.href = getAlumniBatchDetailsPageUrl(graduationYear);
}

function exportSelectedBatchExcel() {
    if (!selectedAlumniBatchYear) {
        alert('Please open a batch first.');
        return;
    }

    const modal = document.getElementById('exportAlumniModal');
    const collegeSelect = document.getElementById('exportCollege');
    const courseSelect = document.getElementById('exportCourse');
    if (!modal || !collegeSelect || !courseSelect) {
        window.location.href = getExportAlumniUrl(selectedAlumniBatchYear);
        return;
    }

    populateExportCollegeOptions();
    populateExportCourseOptions('all');
    modal.style.display = 'flex';
}

function closeExportAlumniModal() {
    const modal = document.getElementById('exportAlumniModal');
    if (!modal) return;
    modal.style.display = 'none';
}

function getBatchAlumniRecordsForExport() {
    return alumniData.filter((alumni) => String(alumni.graduationYear ?? '') === String(selectedAlumniBatchYear));
}

function populateExportCollegeOptions() {
    const collegeSelect = document.getElementById('exportCollege');
    if (!collegeSelect) return;

    const colleges = Array.from(new Set(
        getBatchAlumniRecordsForExport()
            .map((alumni) => String(alumni.college ?? '').trim())
            .filter(Boolean)
    )).sort((a, b) => a.localeCompare(b));

    collegeSelect.innerHTML = '<option value="all">All</option>' +
        colleges.map((college) => `<option value="${escapeHtml(college)}">${escapeHtml(college)}</option>`).join('');
    collegeSelect.value = 'all';
}

function populateExportCourseOptions(selectedCollege = 'all') {
    const courseSelect = document.getElementById('exportCourse');
    if (!courseSelect) return;

    const normalizedCollege = String(selectedCollege || 'all');
    const courses = Array.from(new Set(
        getBatchAlumniRecordsForExport()
            .filter((alumni) => normalizedCollege === 'all' || String(alumni.college ?? '').trim() === normalizedCollege)
            .map((alumni) => String(alumni.course ?? '').trim())
            .filter(Boolean)
    )).sort((a, b) => a.localeCompare(b));

    courseSelect.innerHTML = '<option value="all">All</option>' +
        courses.map((course) => `<option value="${escapeHtml(course)}">${escapeHtml(course)}</option>`).join('');
    courseSelect.value = 'all';
}

function handleExportCollegeChange() {
    const collegeSelect = document.getElementById('exportCollege');
    if (!collegeSelect) return;
    populateExportCourseOptions(collegeSelect.value);
}

function confirmExportAlumniExcel() {
    if (!selectedAlumniBatchYear) {
        alert('Please open a batch first.');
        return;
    }

    const collegeSelect = document.getElementById('exportCollege');
    const courseSelect = document.getElementById('exportCourse');
    if (!collegeSelect || !courseSelect) {
        window.location.href = getExportAlumniUrl(selectedAlumniBatchYear);
        return;
    }

    const college = (collegeSelect.value || 'all').trim();
    const course = (courseSelect.value || 'all').trim();

    closeExportAlumniModal();
    window.location.href = getExportAlumniUrl(selectedAlumniBatchYear, college, course);
}

async function importAlumniExcel() {
    if (!selectedAlumniBatchYear) {
        alert('Please select a batch first.');
        return;
    }

    const fileInput = document.getElementById('alumniImportFile');
    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
        alert('Please choose an Excel file to import.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('graduationYear', String(selectedAlumniBatchYear));
        formData.append('alumniFile', fileInput.files[0]);

        const response = await fetch(getImportAlumniUrl(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            const details = Array.isArray(result.errors) ? `\n\n${result.errors.join('\n')}` : '';
            alert((result.message || 'Unable to import alumni right now.') + details);
            return;
        }
    } catch (error) {
        alert('Unable to import alumni right now.');
        return;
    }

    closeImportAlumniModal();
    if (fileInput) fileInput.value = '';
    await loadAlumni(alumniSort);
    await loadAlumniBatches();
    renderAlumniBatchCards();
    renderSelectedBatchDetails();
}

function createEvent() {
    const name = document.getElementById('eventName').value;
    const date = document.getElementById('eventDate').value;
    const location = document.getElementById('eventLocation').value;

    if (!name || !date || !location) {
        alert('Please fill all fields');
        return;
    }

    const newEvent = {
        id: nextEventId++,
        name: name,
        date: date,
        location: location,
        attendees: 0
    };

    eventsData.push(newEvent);
    updateStats();
    renderEvents();
    closeCreateEventModal();

    document.getElementById('eventName').value = '';
    document.getElementById('eventDate').value = '';
    document.getElementById('eventLocation').value = '';

    saveState();
    void logRecentActivity(`New event created: ${name}`, 'event', { name, date, location });
}

async function deleteEvent(id) {
    const event = eventsData.find(e => e.id === id);
    const ac = await ensureStaffActionConfirm();
    const confirmed = ac
        ? await ac.high({
            title: 'Permanently Delete Event?',
            message: 'You are about to permanently remove this event from the schedule.',
            record: event ? `${event.name}${event.date ? ' (' + event.date + ')' : ''}` : `Event #${id}`,
            consequence: 'This action cannot be undone.',
            requiredPhrase: 'DELETE',
            confirmText: 'Delete Event'
        })
        : false;
    if (!confirmed) return;

    eventsData = eventsData.filter(e => e.id !== id);

    if (event) {
        void logRecentActivity(`Event cancelled: ${event.name}`, 'event', { name: event.name, date: event.date, location: event.location });
    }

    updateStats();
    renderEvents();

    saveState();
}

function sendNewsletter() {
    alert(`📧 Newsletter sent to ${alumniData.length} alumni!`);
    saveState();
    void logRecentActivity(`Newsletter sent to ${alumniData.length} alumni`, 'communication', { count: alumniData.length });
}

function sendAlumniSurvey() {
    const linkInput = document.getElementById('surveyLink');
    const messageInput = document.getElementById('surveyMessage');
    if (!linkInput || !messageInput) return;

    const surveyLink = linkInput.value.trim();
    const surveyMessage = messageInput.value.trim();

    if (!surveyLink) {
        alert('Please add a Google Form link first.');
        return;
    }

    const hasValidUrl = /^https?:\/\/.+/i.test(surveyLink);
    if (!hasValidUrl) {
        alert('Please enter a valid survey link (must start with http:// or https://).');
        return;
    }

    const recipientEmails = alumniData
        .filter(alumni => alumni.status === 'active' && alumni.email)
        .map(alumni => alumni.email);

    if (recipientEmails.length === 0) {
        alert('No active alumni email addresses found.');
        return;
    }

    const emailSubject = encodeURIComponent('Alumni Survey Invitation');
    const defaultMessage = `Hello Alumni,\n\nPlease answer this short survey:\n${surveyLink}\n\nThank you!`;
    const emailBody = encodeURIComponent(surveyMessage || defaultMessage);
    const bcc = encodeURIComponent(recipientEmails.join(','));

    window.location.href = `mailto:?subject=${emailSubject}&bcc=${bcc}&body=${emailBody}`;

    saveState();
    void logRecentActivity(`Survey invite prepared for ${recipientEmails.length} alumni`, 'survey', { count: recipientEmails.length, link: surveyLink });
}

function getSelectedSurveyId() {
    const selector = document.getElementById('surveySelector');
    if (selector && selector.value) {
        return Number(selector.value);
    }

    const urlSurveyId = new URL(window.location.href).searchParams.get('surveyId');
    if (!urlSurveyId) return null;

    return Number(urlSurveyId);
}

function getSelectedSurvey() {
    const surveyId = getSelectedSurveyId();
    if (!surveyId) return null;
    return surveysData.find(s => s.id === surveyId) || null;
}

function getSurveyEditorSurveyId() {
    const surveyId = new URL(window.location.href).searchParams.get('surveyId');
    if (!surveyId) return null;
    return Number(surveyId);
}

function isSurveyEditorPage() {
    return document.body.classList.contains('staff-survey-create-page');
}

function getSurveyBeingEdited() {
    if (!isSurveyEditorPage()) return null;
    const surveyId = getSurveyEditorSurveyId();
    if (!surveyId) return null;
    return surveysData.find((survey) => Number(survey.id) === Number(surveyId)) || null;
}

function renderSurveySelector() {
    const selector = document.getElementById('surveySelector');
    if (!selector) return;

    selector.innerHTML = surveysData.map((survey) => {
        const badge = getSurveyAvailabilityBadge(survey);
        const tag = `[${badge.text}]`;
        return `<option value="${survey.id}">${tag} ${survey.title}</option>`;
    }).join('');
    if (surveysData.length === 0) {
        selector.innerHTML = '<option value="">No surveys available</option>';
    }
}

function renderStaffSurveyFeed() {
    const container = document.getElementById('staffSurveyList');
    if (!container) return;

    if (!Array.isArray(surveysData) || surveysData.length === 0) {
        container.innerHTML = '<div class="event-state-card"><i class="fas fa-file-alt"></i><h4>No surveys yet</h4><p>Create your first survey from the panel on the right.</p></div>';
        return;
    }

    const selectedId = getSelectedSurveyId();
    container.innerHTML = surveysData.map((survey) => {
        const badge = getSurveyAvailabilityBadge(survey);
        const isClosed = survey.status === 'closed';
        const isSelected = Number(selectedId) === Number(survey.id);
        const cardClass = isSelected ? 'is-selected' : '';
        const statusClass = badge.className;
        const isPosted = survey.surveyState === 'posted';
        const stateLabel = isPosted ? 'Posted' : 'Not Posted';
        const canPost = isSurveyReadyToPost(survey);
        return `
            <article class="staff-survey-card ${cardClass}" onclick="selectSurveyFromFeed(${Number(survey.id)})">
                <div class="staff-survey-card-top">
                    <h4>${escapeHtml(survey.title || 'Untitled Survey')}</h4>
                    <span class="staff-survey-status ${statusClass}">${badge.text}</span>
                </div>
                <div class="staff-survey-meta">
                    <span><i class="fas fa-clock"></i> ${escapeHtml(getSurveyScheduleSummary(survey))}</span>
                    <span><i class="fas fa-bullhorn"></i> ${escapeHtml(stateLabel)}</span>
                    <span><i class="fas fa-question-circle"></i> ${Array.isArray(survey.questions) ? survey.questions.length : 0} question(s)</span>
                </div>
                <div class="staff-survey-actions">
                    <button type="button" class="btn-secondary" title="Edit Form" aria-label="Edit Form" onclick="event.stopPropagation(); openSurveyEditorPage(${Number(survey.id)})"><i class="fas fa-pen-to-square"></i></button>
                    <button type="button" class="btn-secondary" title="View Responses" aria-label="View Responses" onclick="event.stopPropagation(); openSurveyResponsesPage(${Number(survey.id)})"><i class="fas fa-eye"></i></button>
                    ${isPosted
                        ? `<button type="button" class="btn-secondary" title="Unpost Survey" aria-label="Unpost Survey" onclick="event.stopPropagation(); updateSurveyStateById(${Number(survey.id)}, 'not posted')"><i class="fas fa-eye-slash"></i></button>`
                        : `<button type="button" class="${canPost ? 'btn-success' : 'btn-secondary'}" title="${canPost ? 'Post Survey' : 'Set Schedule First'}" aria-label="${canPost ? 'Post Survey' : 'Set Schedule First'}" onclick="event.stopPropagation(); updateSurveyStateById(${Number(survey.id)}, 'posted')"><i class="fas fa-bullhorn"></i></button>`
                    }
                    ${isClosed
                        ? `<button type="button" class="btn-success" title="Reopen Survey" aria-label="Reopen Survey" onclick="event.stopPropagation(); setSurveyStatusById(${Number(survey.id)}, 'open')"><i class="fas fa-lock-open"></i></button>`
                        : `<button type="button" class="btn-secondary" title="Close Survey" aria-label="Close Survey" onclick="event.stopPropagation(); setSurveyStatusById(${Number(survey.id)}, 'closed')"><i class="fas fa-lock"></i></button>`
                    }
                    <button type="button" class="btn-danger" title="Delete Survey" aria-label="Delete Survey" onclick="event.stopPropagation(); deleteSurveyById(${Number(survey.id)})"><i class="fas fa-trash-can"></i></button>
                </div>
            </article>
        `;
    }).join('');
}

async function setSurveyStatusById(surveyId, nextStatus) {
    const survey = surveysData.find((item) => Number(item.id) === Number(surveyId));
    if (!survey) return;

    try {
        const response = await fetch(getSurveyUpdateStatusUrl(survey.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ status: nextStatus })
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to update survey status.');
            return;
        }

        await loadSurveys();
        const selector = document.getElementById('surveySelector');
        if (selector) selector.value = String(survey.id);
        await onSurveySelectionChange();
    } catch (error) {
        alert('Unable to update survey status.');
    }
}

async function updateSurveyStateById(surveyId, nextState) {
    const survey = surveysData.find((item) => Number(item.id) === Number(surveyId));
    if (!survey) return;

    if (nextState === 'posted' && !isSurveyReadyToPost(survey)) {
        alert('Set both open time and close time before posting this survey.');
        return;
    }

    const ac = await ensureStaffActionConfirm();
    if (ac) {
        const isPosting = nextState === 'posted';
        const confirmed = await ac.medium({
            title: isPosting ? 'Publish Survey?' : 'Unpublish Survey?',
            message: isPosting
                ? `You are about to publish "${survey.title || 'Untitled Survey'}" so alumni can access it.`
                : `You are about to unpublish "${survey.title || 'Untitled Survey'}" and hide it from alumni.`,
            record: survey.title || `Survey #${survey.id}`,
            consequence: isPosting
                ? 'Alumni within your campus will be able to submit responses.'
                : 'Alumni will no longer be able to view or submit responses while unpublished.',
            confirmText: isPosting ? 'Yes, Publish' : 'Yes, Unpublish',
            icon: isPosting ? 'fa-bullhorn' : 'fa-eye-slash'
        });
        if (!confirmed) return;
    }

    try {
        const response = await fetch(getSurveyUpdateStateUrl(survey.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ survey_state: nextState })
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to update survey post state.');
            return;
        }

        await loadSurveys();
        const selector = document.getElementById('surveySelector');
        if (selector) selector.value = String(survey.id);
        await onSurveySelectionChange();
    } catch (error) {
        alert('Unable to update survey post state.');
    }
}

async function openNextSurveyBatchById(surveyId) {
    const survey = surveysData.find((item) => Number(item.id) === Number(surveyId));
    if (!survey || survey.status === 'closed') return;

    const ac = await ensureStaffActionConfirm();
    if (ac) {
        const confirmed = await ac.medium({
            title: 'Advance Survey to Next Batch?',
            message: `You are about to open a new response batch for "${survey.title || 'Untitled Survey'}".`,
            record: survey.title || `Survey #${survey.id}`,
            consequence: 'This increments the active survey batch number for subsequent alumni responses.',
            confirmText: 'Yes, Open Next Batch',
            icon: 'fa-forward'
        });
        if (!confirmed) return;
    }

    try {
        const response = await fetch(getSurveyNextBatchUrl(survey.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({})
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to open a new cycle.');
            return;
        }

        await loadSurveys();
        const selector = document.getElementById('surveySelector');
        if (selector) selector.value = String(survey.id);
        await onSurveySelectionChange();
    } catch (error) {
        alert('Unable to open a new cycle.');
    }
}

async function deleteSurveyById(surveyId) {
    const survey = surveysData.find((item) => Number(item.id) === Number(surveyId));
    if (!survey) return;

    const ac = await ensureStaffActionConfirm();
    const confirmed = ac
        ? await ac.high({
            title: 'Permanently Delete Survey?',
            message: `You are about to permanently delete "${survey.title || 'Untitled Survey'}" along with all of its questions, responses, and answers.`,
            record: survey.title || `Survey #${survey.id}`,
            consequence: 'All collected survey responses and statistics for this survey will be permanently lost. This action cannot be undone.',
            requiredPhrase: 'DELETE',
            confirmText: 'Delete Survey Permanently'
        })
        : false;
    if (!confirmed) return;

    try {
        const response = await fetch(getSurveyDeleteUrl(survey.id), {
            method: 'DELETE',
            credentials: 'same-origin'
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to delete survey.');
            return;
        }

        await loadSurveys();
        const selector = document.getElementById('surveySelector');
        if (selector) selector.value = '';
        await onSurveySelectionChange();
    } catch (error) {
        alert('Unable to delete survey.');
    }
}

let currentSelectedSurveyFeedId = null;

function selectSurveyFromFeed(surveyId) {
    currentSelectedSurveyFeedId = Number(surveyId);
    const selector = document.getElementById('surveySelector');
    if (selector) {
        selector.value = String(surveyId);
        void onSurveySelectionChange();
    }
    document.querySelectorAll('.staff-survey-card').forEach((card) => {
        card.classList.remove('is-selected');
    });
    if (window.event && window.event.currentTarget) {
        window.event.currentTarget.classList.add('is-selected');
    }
}

let selectedSurveyCreationMode = 'new'; // 'new' | 'copy'

function openSurveyComposer() {
    const modal = document.getElementById('chooseSurveyModal');
    if (!modal) {
        localStorage.removeItem('surveyWizardData');
        window.location.href = getSurveyCreatePageUrl();
        return;
    }

    populateSurveyTemplateDropdown();
    selectSurveyCreationMode('new');
    modal.style.display = 'flex';
}

function closeChooseSurveyModal() {
    const modal = document.getElementById('chooseSurveyModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function selectSurveyCreationMode(mode) {
    selectedSurveyCreationMode = mode;
    const cardNew = document.getElementById('choiceCardNew');
    const cardCopy = document.getElementById('choiceCardCopy');
    const templateSection = document.getElementById('surveyTemplateSelectSection');
    const btnText = document.getElementById('confirmSurveyChoiceBtnText');

    const hasSurveys = Array.isArray(surveysData) && surveysData.length > 0;

    if (mode === 'new') {
        if (cardNew) cardNew.classList.add('active');
        if (cardCopy) cardCopy.classList.remove('active');
        if (templateSection) templateSection.style.display = 'none';
        if (btnText) btnText.textContent = 'Create Blank Form';
    } else {
        if (!hasSurveys) {
            alert('No previous surveys found to copy from. Starting a new blank form instead.');
            selectSurveyCreationMode('new');
            return;
        }
        if (cardNew) cardNew.classList.remove('active');
        if (cardCopy) cardCopy.classList.add('active');
        if (templateSection) templateSection.style.display = 'block';
        if (btnText) btnText.textContent = 'Copy & Continue to Builder';
        updateSurveyTemplatePreview();
    }
}

function populateSurveyTemplateDropdown() {
    const select = document.getElementById('modalSourceSurveySelect');
    const emptyNotice = document.getElementById('surveyTemplateEmptyNotice');
    const previewCard = document.getElementById('surveyTemplatePreviewCard');
    const cardCopy = document.getElementById('choiceCardCopy');
    if (!select) return;

    select.innerHTML = '';
    const hasSurveys = Array.isArray(surveysData) && surveysData.length > 0;

    if (!hasSurveys) {
        if (emptyNotice) emptyNotice.style.display = 'block';
        if (previewCard) previewCard.style.display = 'none';
        select.style.display = 'none';
        if (cardCopy) {
            cardCopy.style.opacity = '0.6';
            cardCopy.style.cursor = 'not-allowed';
            cardCopy.title = 'No previous surveys available to copy from';
        }
        return;
    }

    if (emptyNotice) emptyNotice.style.display = 'none';
    if (previewCard) previewCard.style.display = 'block';
    select.style.display = 'block';
    if (cardCopy) {
        cardCopy.style.opacity = '1';
        cardCopy.style.cursor = 'pointer';
        cardCopy.title = '';
    }

    surveysData.forEach((survey) => {
        const groups = getSurveyCategoryGroups(survey);
        const catCount = groups.length;
        const qCount = groups.reduce((sum, g) => sum + (g.questions?.length || 0), 0);
        const opt = document.createElement('option');
        opt.value = String(survey.id);
        opt.textContent = `${survey.title || 'Untitled Survey'} (${qCount} Q • ${catCount} Cat)`;
        select.appendChild(opt);
    });

    const selected = getSelectedSurvey();
    if (selected && surveysData.some((s) => Number(s.id) === Number(selected.id))) {
        select.value = String(selected.id);
    }
}

function updateSurveyTemplatePreview() {
    const select = document.getElementById('modalSourceSurveySelect');
    if (!select) return;

    const surveyId = select.value;
    const survey = surveysData.find((s) => String(s.id) === String(surveyId));
    const titleEl = document.getElementById('templatePreviewTitle');
    const descEl = document.getElementById('templatePreviewDesc');
    const qBadge = document.getElementById('templatePreviewQuestionBadge');
    const cBadge = document.getElementById('templatePreviewCategoryBadge');

    if (!survey) {
        if (titleEl) titleEl.textContent = 'No survey selected';
        if (descEl) descEl.textContent = '';
        return;
    }

    const groups = getSurveyCategoryGroups(survey);
    const catCount = groups.length;
    const qCount = groups.reduce((sum, g) => sum + (g.questions?.length || 0), 0);

    if (titleEl) titleEl.textContent = survey.title || 'Untitled Survey';
    if (descEl) descEl.textContent = survey.description || 'No description provided.';
    if (qBadge) qBadge.innerHTML = `<i class="fas fa-question-circle"></i> ${qCount} Question${qCount === 1 ? '' : 's'}`;
    if (cBadge) cBadge.innerHTML = `<i class="fas fa-folder"></i> ${catCount} Categor${catCount === 1 ? 'y' : 'ies'}`;
}

function wizardCloneFromExistingSurvey(survey) {
    const groups = getSurveyCategoryGroups(survey);

    // Map old category IDs to fresh IDs so logic rules don't break
    const catIdMap = {};
    groups.forEach((category, catIdx) => {
        const oldId = category.id || `old_${catIdx}`;
        const newId = `cat_${Date.now()}_${catIdx}_${Math.floor(Math.random() * 10000)}`;
        catIdMap[oldId] = newId;
    });

    const clonedCategories = groups.map((category, catIdx) => {
        const oldId = category.id || `old_${catIdx}`;
        const newCatId = catIdMap[oldId];

        return {
            id: newCatId,
            name: category.name || `Category ${catIdx + 1}`,
            description: category.description || '',
            questions: (category.questions || []).map((question, qIdx) => {
                let logicCopy = null;
                if (question.logic && question.logic.enabled && Array.isArray(question.logic.rules)) {
                    logicCopy = {
                        enabled: true,
                        rules: question.logic.rules.map((rule) => {
                            const clonedRule = { ...rule };
                            if (clonedRule.action === 'go_to_section' && clonedRule.targetCatId) {
                                clonedRule.targetCatId = catIdMap[clonedRule.targetCatId] || clonedRule.targetCatId;
                            }
                            return clonedRule;
                        }),
                    };
                }

                return {
                    id: `q_${Date.now()}_${catIdx}_${qIdx}_${Math.floor(Math.random() * 10000)}`,
                    text: question.text || '',
                    type: question.type || 'multiple_choice',
                    required: question.required !== false && question.required !== 0 && question.required !== '0',
                    options: Array.isArray(question.options)
                        ? [...question.options]
                        : (Array.isArray(question.choices) ? question.choices.map((c) => (typeof c === 'object' ? (c.text || '') : c)) : []),
                    ratingMin: question.ratingMin ?? 1,
                    ratingMax: question.ratingMax ?? 5,
                    ratingMinLabel: question.ratingMinLabel || '',
                    ratingMaxLabel: question.ratingMaxLabel || '',
                    logic: logicCopy,
                };
            }),
        };
    });

    return {
        surveyId: null, // Critical: this is a NEW survey, not editing the old one!
        formName: survey.title ? `${survey.title} (New Form)` : '',
        formDetails: survey.description || '',
        dateOpen: '',
        dateClose: '',
        categories: clonedCategories.length > 0 ? clonedCategories : wizardDefaultData().categories,
    };
}

function confirmSurveyCreationChoice() {
    if (selectedSurveyCreationMode === 'new') {
        localStorage.removeItem('surveyWizardData');
        window.location.href = getSurveyCreatePageUrl();
        return;
    }

    const select = document.getElementById('modalSourceSurveySelect');
    const surveyId = select ? select.value : null;
    const sourceSurvey = surveysData.find((s) => String(s.id) === String(surveyId));

    if (!sourceSurvey) {
        alert('Please select an existing survey form to copy.');
        return;
    }

    const clonedData = wizardCloneFromExistingSurvey(sourceSurvey);
    wizardStorageSet(SURVEY_WIZARD_STORAGE_KEY, clonedData);
    window.location.href = getSurveyCreatePageUrl();
}

function openSurveyEditorPage(surveyId = null) {
    let targetSurveyId = surveyId;
    if (targetSurveyId === null || targetSurveyId === undefined) {
        const selected = getSelectedSurvey();
        if (selected) {
            targetSurveyId = selected.id;
        }
    }

    if (!targetSurveyId) {
        alert('Survey not found.');
        return;
    }

    const targetSurvey = Array.isArray(surveysData)
        ? surveysData.find((s) => Number(s.id) === Number(targetSurveyId))
        : null;
    if (targetSurvey) {
        wizardLoadDataFromSurvey(targetSurvey);
        wizardPersistData();
    }

    window.location.href = getSurveyCreatePageUrl(targetSurveyId);
}

function openSurveyResponsesPage(surveyId = null) {
    let targetSurveyId = surveyId;
    if (targetSurveyId === null || targetSurveyId === undefined) {
        const selected = getSelectedSurvey();
        if (selected) {
            targetSurveyId = selected.id;
        } else if (Array.isArray(surveysData) && surveysData.length > 0) {
            targetSurveyId = surveysData[0].id;
        } else {
            targetSurveyId = null;
        }
    }
    window.location.href = getSurveyResponsesPageUrl(targetSurveyId);
}

function renderSurveyStatusPanel() {
    const panel = document.getElementById('surveyStatusPanel');
    if (!panel) return;

    const survey = getSelectedSurvey();
    const label = document.getElementById('surveyStatusText');
    const closeBtn = document.getElementById('surveyCloseBtn');
    const reopenBtn = document.getElementById('surveyReopenBtn');

    if (!survey || surveysData.length === 0) {
        panel.style.display = 'none';
        return;
    }

    panel.style.display = '';
    const closed = survey.status === 'closed';

    if (label) {
        label.innerHTML = closed
            ? '<strong>Closed</strong> — alumni cannot submit new responses from their portal. You can still view analytics and record responses on behalf of alumni below.'
            : '<strong>Open</strong> — alumni can view and submit responses from their portal.';
    }
    if (closeBtn) closeBtn.style.display = closed ? 'none' : 'inline-block';
    if (reopenBtn) reopenBtn.style.display = closed ? 'inline-block' : 'none';

    const batchLabel = document.getElementById('surveyCurrentBatchLabel');
    const nextBatchBtn = document.getElementById('surveyNextBatchBtn');
    if (batchLabel) {
        batchLabel.textContent = getSurveyScheduleSummary(survey);
    }
    if (nextBatchBtn) {
        nextBatchBtn.style.display = 'none';
    }
}

async function openNextSurveyBatch() {
    const survey = getSelectedSurvey();
    if (!survey || survey.status === 'closed') return;

    try {
        const response = await fetch(getSurveyNextBatchUrl(survey.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({})
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to open a new cycle.');
            return;
        }

        await loadSurveys();
        const selector = document.getElementById('surveySelector');
        if (selector) selector.value = String(survey.id);
        await onSurveySelectionChange();

        void logRecentActivity(`Survey new cycle opened: ${survey.title}`, 'survey', { surveyId: survey.id });
    } catch (error) {
        alert('Unable to open a new cycle.');
    }
}

async function onSurveyAllowMultipleChange() {
    const survey = getSelectedSurvey();
    const cb = document.getElementById('surveyAllowMultipleCheckbox');
    if (!survey || !cb || survey.status === 'closed') return;

    const want = cb.checked;
    const current = Boolean(survey.allowMultipleResponses);
    if (want === current) return;

    cb.disabled = true;
    try {
        await setSurveyAllowMultipleAllowed(want);
    } finally {
        const s = getSelectedSurvey();
        if (cb) {
            cb.checked = Boolean(s?.allowMultipleResponses);
            cb.disabled = !s || s.status === 'closed';
        }
    }
}

async function setSurveyAllowMultipleAllowed(allowed) {
    const survey = getSelectedSurvey();
    if (!survey) return;

    try {
        const response = await fetch(getSurveyAllowMultipleUrl(survey.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ allowed })
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to update setting.');
            return;
        }

        await loadSurveys();
        const selector = document.getElementById('surveySelector');
        if (selector) selector.value = String(survey.id);
        await onSurveySelectionChange();

        void logRecentActivity(
            allowed ? `Multiple responses enabled: ${survey.title}` : `Multiple responses disabled: ${survey.title}`,
            'survey',
            { surveyId: survey.id, allowMultiple: allowed }
        );
    } catch (error) {
        alert('Unable to update setting.');
    }
}

async function setSelectedSurveyStatus(nextStatus) {
    const survey = getSelectedSurvey();
    if (!survey) return;

    try {
        const response = await fetch(getSurveyUpdateStatusUrl(survey.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ status: nextStatus })
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to update survey status.');
            return;
        }

        await loadSurveys();
        const selector = document.getElementById('surveySelector');
        if (selector) selector.value = String(survey.id);
        await onSurveySelectionChange();

        void logRecentActivity(
            nextStatus === 'closed' ? `Survey closed: ${survey.title}` : `Survey reopened: ${survey.title}`,
            'survey',
            { surveyId: survey.id, status: nextStatus }
        );
    } catch (error) {
        alert('Unable to update survey status.');
    }
}

function renderSurveyQuestionsList() {
    const list = document.getElementById('surveyQuestionsList');
    if (!list) return;

    const survey = getSelectedSurvey();
    if (!survey) {
        list.innerHTML = '<p style="color: #7f8c8d;">Create a survey first.</p>';
        return;
    }

    const categories = getSurveyCategoryGroups(survey);
    if (categories.length === 0) {
        list.innerHTML = '<p style="color: #7f8c8d;">No questions yet.</p>';
        return;
    }

    list.innerHTML = categories.map((category) => `
        <div style="border: 1px solid #dbeafe; border-radius: 10px; padding: 14px; margin-bottom: 12px; background: #fff;">
            <strong style="display: block; color: #1e3a8a; margin-bottom: 10px;">${escapeHtml(category.name || 'Uncategorized')}</strong>
            ${category.questions.map((question, index) => `
                <div style="border: 1px solid #eef2f6; border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                        <strong>Q${index + 1}: ${escapeHtml(question.text || '')}</strong>
                        <button class="btn-danger" onclick="deleteSurveyQuestion(${question.id})">Remove</button>
                    </div>
                    <p style="margin-top: 6px; color: #7f8c8d;">Type: ${escapeHtml(String(question.type || 'text').replace('_', ' '))}</p>
                    ${question.type === 'multiple_choice' ? `<p style="margin-top: 4px; color: #7f8c8d;">Options: ${escapeHtml((question.options || []).join(', '))}</p>` : ''}
                </div>
            `).join('')}
        </div>
    `).join('');
}

function renderSurveyResponseForm() {
    const fieldsContainer = document.getElementById('surveyResponseFormFields');
    const alumniSelector = document.getElementById('responseAlumniId');
    if (!fieldsContainer || !alumniSelector) return;

    alumniSelector.innerHTML = '<option value="">Select alumni</option>' + alumniData
        .filter(alumni => !!alumni.email)
        .map(alumni => `<option value="${alumni.id}">${getAlumniDisplayName(alumni)} (${alumni.email})</option>`)
        .join('');

    const survey = getSelectedSurvey();
    const categories = getSurveyCategoryGroups(survey);
    if (!survey || categories.length === 0) {
        fieldsContainer.innerHTML = '<p style="color: #7f8c8d;">Choose a survey with questions to record responses.</p>';
        return;
    }

    const closedNote = survey.status === 'closed'
        ? `<p style="margin-bottom: 14px; padding: 10px 12px; background: #fff8e6; border: 1px solid #fcd34d; border-radius: 8px; color: #92400e; font-size: 14px;"><strong>Survey is closed</strong> to alumni self-service. You can still enter a response here for records.</p>`
        : '';

    fieldsContainer.innerHTML = closedNote + categories.map((category) => `
        <div style="margin-bottom: 14px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff;">
            <div style="font-weight: 700; color: #1e4a76; margin-bottom: 10px;">${escapeHtml(category.name || 'Uncategorized')}</div>
            ${category.questions.map((question) => {
                if (question.type === 'multiple_choice') {
                    return `
                        <label style="display:block; margin-bottom: 6px; font-weight: 600;">${escapeHtml(question.text || '')}</label>
                        <select id="response_q_${question.id}" data-question-type="${question.type}" style="width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #ddd; border-radius: 6px;">
                            <option value="">Select answer</option>
                            ${(question.choices || []).map((choice) => `<option value="${choice.id}">${escapeHtml(choice.text)}</option>`).join('')}
                        </select>
                    `;
                }

                if (question.type === 'rating') {
                    const rMin = question.ratingMin ?? 1;
                    const rMax = question.ratingMax ?? 5;
                    const rMinLabel = question.ratingMinLabel || '';
                    const rMaxLabel = question.ratingMaxLabel || '';
                    let ratingOptions = '<option value="">Select rating</option>';
                    for (let rv = rMin; rv <= rMax; rv++) {
                        let label = String(rv);
                        if (rv === rMin && rMinLabel) label += ` - ${rMinLabel}`;
                        else if (rv === rMax && rMaxLabel) label += ` - ${rMaxLabel}`;
                        ratingOptions += `<option value="${rv}">${label}</option>`;
                    }
                    return `
                        <label style="display:block; margin-bottom: 6px; font-weight: 600;">${escapeHtml(question.text || '')}</label>
                        <select id="response_q_${question.id}" data-question-type="${question.type}" style="width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #ddd; border-radius: 6px;">
                            ${ratingOptions}
                        </select>
                    `;
                }

                if (question.type === 'image') {
                    return `
                        <label style="display:block; margin-bottom: 6px; font-weight: 600;">${escapeHtml(question.text || '')}</label>
                        <input id="response_q_${question.id}" data-question-type="${question.type}" type="file" accept="image/jpeg,image/png" style="width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #ddd; border-radius: 6px; background: #fff;">
                    `;
                }

                return `
                    <label style="display:block; margin-bottom: 6px; font-weight: 600;">${escapeHtml(question.text || '')}</label>
                    <input id="response_q_${question.id}" data-question-type="${question.type}" type="text" placeholder="Type answer..." style="width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #ddd; border-radius: 6px;">
                `;
            }).join('')}
        </div>
    `).join('');
}

function renderSurveyResponsesTable() {
    const tableBody = document.getElementById('surveyResponsesBody');
    if (!tableBody) return;

    const resultCount = document.getElementById('surveyResponseResultCount');
    const searchInput = document.getElementById('surveyResponseSearch');
    const collegeFilter = document.getElementById('surveyResponseCollegeFilter');
    const statusFilter = document.getElementById('surveyResponseStatusFilter');
    const sortSelect = document.getElementById('surveyResponseSort');

    const survey = getSelectedSurvey();
    if (!survey) {
        if (resultCount) resultCount.textContent = '0 responses';
        tableBody.innerHTML = `
            <tr><td colspan="6" class="response-table-state">
                <span class="response-empty-icon"><i class="fas fa-file-circle-question" aria-hidden="true"></i></span>
                <strong>Survey unavailable</strong>
                <span>Return to the survey builder and choose a survey to review.</span>
            </td></tr>`;
        renderSurveyResponsesPagination(0, 1);
        return;
    }

    const responses = currentSurveyResponses;

    if (collegeFilter) {
        const currentCollege = collegeFilter.value;
        const colleges = [...new Set(responses
            .map((response) => String(response.college || '').trim())
            .filter(Boolean))]
            .sort((a, b) => a.localeCompare(b));
        const optionSignature = colleges.join('|');
        if (collegeFilter.dataset.options !== optionSignature) {
            collegeFilter.innerHTML = '<option value="">All Colleges</option>' + colleges
                .map((college) => `<option value="${escapeHtml(college)}">${escapeHtml(college)}</option>`)
                .join('');
            collegeFilter.dataset.options = optionSignature;
            collegeFilter.value = colleges.includes(currentCollege) ? currentCollege : '';
        }
    }

    if (responses.length === 0) {
        if (resultCount) resultCount.textContent = '0 responses';
        tableBody.innerHTML = `
            <tr><td colspan="6" class="response-table-state">
                <span class="response-empty-icon"><i class="fas fa-inbox" aria-hidden="true"></i></span>
                <strong>No responses yet</strong>
                <span>New submissions will appear here as soon as alumni complete this survey.</span>
            </td></tr>`;
        renderSurveyResponsesPagination(0, 1);
        return;
    }

    const query = String(searchInput?.value || '').trim().toLocaleLowerCase();
    const selectedCollege = String(collegeFilter?.value || '');
    const selectedStatus = String(statusFilter?.value || '').trim().toLowerCase();
    const sortMode = String(sortSelect?.value || 'newest');
    const searchableFields = (response) => [
        response.alumniName,
        response.userName,
        response.alumniRecordId,
        response.college,
        response.course,
        response.reviewStatus,
    ].map((value) => String(value || '').toLocaleLowerCase()).join(' ');

    const visibleResponses = responses
        .filter((response) => !query || searchableFields(response).includes(query))
        .filter((response) => !selectedCollege || String(response.college || '') === selectedCollege)
        .filter((response) => !selectedStatus || String(response.reviewStatus || 'pending').toLowerCase() === selectedStatus)
        .sort((a, b) => {
            if (sortMode === 'name') {
                return String(a.alumniName || '').localeCompare(String(b.alumniName || ''));
            }
            const aTime = Date.parse(String(a.submittedAt || '')) || 0;
            const bTime = Date.parse(String(b.submittedAt || '')) || 0;
            return sortMode === 'oldest' ? aTime - bTime : bTime - aTime;
        });

    const totalRecords = visibleResponses.length;
    const totalPages = Math.max(1, Math.ceil(totalRecords / SURVEY_RESPONSES_PER_PAGE));
    if (currentSurveyResponsesPage > totalPages) {
        currentSurveyResponsesPage = totalPages;
    }
    if (currentSurveyResponsesPage < 1) {
        currentSurveyResponsesPage = 1;
    }

    if (resultCount) {
        resultCount.textContent = visibleResponses.length === responses.length
            ? `${responses.length} ${responses.length === 1 ? 'response' : 'responses'}`
            : `${visibleResponses.length} of ${responses.length} responses`;
    }

    if (visibleResponses.length === 0) {
        tableBody.innerHTML = `
            <tr><td colspan="6" class="response-table-state">
                <span class="response-empty-icon"><i class="fas fa-magnifying-glass" aria-hidden="true"></i></span>
                <strong>No matching responses</strong>
                <span>Try a different search term or college filter.</span>
                <button type="button" class="response-clear-button" onclick="clearSurveyResponseFilters()">Clear filters</button>
            </td></tr>`;
        renderSurveyResponsesPagination(0, 1);
        return;
    }

    const startIdx = (currentSurveyResponsesPage - 1) * SURVEY_RESPONSES_PER_PAGE;
    const paginatedResponses = visibleResponses.slice(startIdx, startIdx + SURVEY_RESPONSES_PER_PAGE);

    tableBody.innerHTML = paginatedResponses.map((response, index) => {
        const name = String(response.alumniName || response.userName || 'Alumni respondent');
        const initials = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase() || 'AR';
        const answerCount = Array.isArray(response.answers) ? response.answers.length : 0;
        const recordId = String(response.alumniRecordId || '').trim();
        const college = String(response.college || '').trim();
        const course = String(response.course || '').trim();
        const submittedAt = formatReadableDateTime(response.submittedAt || '') || 'Date unavailable';
        const reviewStatus = String(response.reviewStatus || 'pending').toLowerCase();

        let statusBadge = '';
        if (reviewStatus === 'reviewed') {
            statusBadge = `<span class="response-status-badge is-reviewed"><i class="fas fa-circle-check" aria-hidden="true"></i> Reviewed</span>`;
        } else if (reviewStatus === 'needs_revision') {
            statusBadge = `<span class="response-status-badge is-needs-revision"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Needs Revision</span>`;
        } else {
            statusBadge = `<span class="response-status-badge is-pending"><i class="fas fa-clock" aria-hidden="true"></i> Pending</span>`;
        }

        return `
        <tr style="--response-row-index: ${Math.min(index, 10)}">
            <td data-label="Respondent">
                <div class="response-person">
                    <span class="response-person-avatar" aria-hidden="true">${escapeHtml(initials)}</span>
                    <span class="response-person-copy">
                        <strong>${escapeHtml(name)}</strong>
                        <small>${recordId ? escapeHtml(recordId) : 'No ID'}</small>
                    </span>
                </div>
            </td>
            <td data-label="College & course">
                <div class="response-academic-info">
                    <strong>${escapeHtml(college || '—')}</strong>
                    <span>${escapeHtml(course || '—')}</span>
                </div>
            </td>
            <td data-label="Answers">
                <span class="response-answer-count">${answerCount} ${answerCount === 1 ? 'item' : 'items'}</span>
            </td>
            <td data-label="Status">
                ${statusBadge}
            </td>
            <td data-label="Submitted">
                <div class="response-submitted"><span>${escapeHtml(submittedAt)}</span></div>
            </td>
            <td class="response-action-cell">
                <button class="response-view-button" type="button" onclick="viewSurveyResponseDetails(${Number(response.id)})" title="View complete response" aria-label="View complete response from ${escapeHtml(name)}">
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </td>
        </tr>
    `;
    }).join('');

    renderSurveyResponsesPagination(totalRecords, totalPages);
}

function handleSurveyResponseFilterChange() {
    currentSurveyResponsesPage = 1;
    renderSurveyResponsesTable();
}

function renderSurveyResponsesPagination(totalRecords, totalPages) {
    const infoEl = document.getElementById('surveyResponsesPaginationInfo');
    const controlsEl = document.getElementById('surveyResponsesPaginationControls');
    if (!infoEl || !controlsEl) return;

    if (totalRecords === 0) {
        infoEl.textContent = 'Showing 0 responses';
        controlsEl.innerHTML = '';
        return;
    }

    const startRecord = (currentSurveyResponsesPage - 1) * SURVEY_RESPONSES_PER_PAGE + 1;
    const endRecord = Math.min(currentSurveyResponsesPage * SURVEY_RESPONSES_PER_PAGE, totalRecords);
    infoEl.textContent = `Showing ${startRecord}–${endRecord} of ${totalRecords} responses`;

    let buttonsHtml = `
        <button type="button" class="response-page-btn" onclick="goToSurveyResponsesPage(${currentSurveyResponsesPage - 1})" ${currentSurveyResponsesPage <= 1 ? 'disabled' : ''} title="Previous Page" aria-label="Previous Page">
            <i class="fas fa-chevron-left" style="font-size: 10px;"></i>
        </button>
    `;

    const pages = [];
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentSurveyResponsesPage - 1 && i <= currentSurveyResponsesPage + 1)) {
            pages.push(i);
        } else if (pages[pages.length - 1] !== '...') {
            pages.push('...');
        }
    }

    pages.forEach((p) => {
        if (p === '...') {
            buttonsHtml += `<span class="response-page-ellipsis">…</span>`;
        } else {
            buttonsHtml += `
                <button type="button" class="response-page-btn ${p === currentSurveyResponsesPage ? 'active' : ''}" onclick="goToSurveyResponsesPage(${p})" title="Page ${p}">
                    ${p}
                </button>
            `;
        }
    });

    buttonsHtml += `
        <button type="button" class="response-page-btn" onclick="goToSurveyResponsesPage(${currentSurveyResponsesPage + 1})" ${currentSurveyResponsesPage >= totalPages ? 'disabled' : ''} title="Next Page" aria-label="Next Page">
            <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
        </button>
    `;

    controlsEl.innerHTML = buttonsHtml;
}

function goToSurveyResponsesPage(page) {
    if (page < 1 || page === currentSurveyResponsesPage) return;
    currentSurveyResponsesPage = page;
    renderSurveyResponsesTable();
    const tableWrap = document.querySelector('.response-table-wrap');
    if (tableWrap) tableWrap.scrollTop = 0;
}

function clearSurveyResponseFilters() {
    const searchInput = document.getElementById('surveyResponseSearch');
    const collegeFilter = document.getElementById('surveyResponseCollegeFilter');
    const statusFilter = document.getElementById('surveyResponseStatusFilter');
    const sortSelect = document.getElementById('surveyResponseSort');
    if (searchInput) searchInput.value = '';
    if (collegeFilter) collegeFilter.value = '';
    if (statusFilter) statusFilter.value = '';
    if (sortSelect) sortSelect.value = 'newest';
    currentSurveyResponsesPage = 1;
    renderSurveyResponsesTable();
}

function viewSurveyResponseDetails(responseId) {
    window.location.href = `SurveyResponseDetails.html?responseId=${encodeURIComponent(responseId)}`;
}

function viewAllSurveyResponseDetails() {
    const survey = getSelectedSurvey();
    if (!survey) {
        alert('Please select a survey first.');
        return;
    }

    openSurveyResponsesPage(survey.id);
}

function renderSurveyAnalytics() {
    const container = document.getElementById('surveyStats');
    if (!container) return;

    const survey = getSelectedSurvey();
    if (!survey) {
        container.innerHTML = '<p style="color: #7f8c8d;">Select a survey to view analytics.</p>';
        return;
    }

    const stats = currentSurveyStats;
    const batchStats = currentSurveyBatchStats;

    const totalResponses = Number(stats?.totalResponses || 0);
    const responseLabel = totalResponses === 1 ? 'response recorded' : 'responses recorded';

    let html = `
        <div class="response-stat-overview">
            <span class="response-stat-icon"><i class="fas fa-users" aria-hidden="true"></i></span>
            <div class="response-stat-value">${totalResponses}</div>
            <div class="response-stat-copy">
                <strong>${responseLabel}</strong>
                <span>Submitted by alumni for the selected survey.</span>
            </div>
        </div>
    `;

    if (Array.isArray(batchStats) && batchStats.length > 0) {
        const maxResponseRate = Math.max(...batchStats.map(b => Number(b.responseRate || 0)), 1);
        const chartHeight = 250;
        const yAxisWidth = 50;
        const chartPadding = 20;

        // Generate Y-axis labels (0, 25%, 50%, 75%, 100%)
        const yTicks = [0, 25, 50, 75, 100].filter(tick => tick <= maxResponseRate + 10);

        html += `
            <details class="response-analytics-details">
                <summary>
                    <span><i class="fas fa-chart-column" aria-hidden="true"></i> Response-rate breakdown by batch</span>
                    <span class="response-analytics-toggle"><span>View breakdown</span><i class="fas fa-chevron-down" aria-hidden="true"></i></span>
                </summary>
                <div class="response-analytics-expanded">
            <div style="margin-top: 24px;">
                <div style="font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 16px;">
                    <i class="fas fa-chart-bar"></i> Response Rate by Batch
                </div>
                <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; background: #fff;">
                    <div style="display: flex; height: ${chartHeight}px;">
                        <!-- Y-axis -->
                        <div style="width: ${yAxisWidth}px; display: flex; flex-direction: column; justify-content: space-between; padding-right: 10px; border-right: 2px solid #e2e8f0;">
                            ${yTicks.map(tick => `
                                <div style="font-size: 11px; color: #64748b; text-align: right;">${tick}%</div>
                            `).join('')}
                        </div>
                        <!-- Chart area -->
                        <div style="flex: 1; display: flex; align-items: flex-end; justify-content: space-around; padding-left: ${chartPadding}px; padding-bottom: 30px; border-bottom: 2px solid #e2e8f0; position: relative;">
                            <!-- Grid lines -->
                            ${yTicks.map(tick => {
                                const topPosition = ((maxResponseRate - tick) / maxResponseRate) * (chartHeight - 30);
                                return `
                                    <div style="position: absolute; left: 0; right: 0; top: ${topPosition}px; border-top: 1px dashed #e2e8f0; z-index: 0;"></div>
                                `;
                            }).join('')}
                            <!-- Bars -->
                            ${batchStats.map(batch => {
                                const responseRate = Number(batch.responseRate || 0);
                                const respondentCount = Number(batch.respondentCount || 0);
                                const totalAlumni = Number(batch.totalAlumni || 0);
                                const barHeight = (responseRate / maxResponseRate) * (chartHeight - 30);
                                const barColor = responseRate >= 70 ? '#10b981' : responseRate >= 40 ? '#f59e0b' : '#ef4444';

                                return `
                                    <div style="display: flex; flex-direction: column; align-items: center; width: 50px; z-index: 1;">
                                        <div style="width: 35px; height: ${barHeight}px; background: ${barColor}; border-radius: 4px 4px 0 0; transition: height 0.3s ease; position: relative;">
                                            <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 11px; font-weight: 600; color: #64748b;">${responseRate}%</div>
                                        </div>
                                        <div style="margin-top: 8px; font-size: 11px; font-weight: 600; color: #1e293b; text-align: center;">${escapeHtml(String(batch.batch))}</div>
                                        <div style="font-size: 9px; color: #64748b; text-align: center;">${respondentCount}/${totalAlumni}</div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top: 24px;">
                <div style="font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 16px;">
                    <i class="fas fa-list"></i> Batch Details
                </div>
                <div style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #fff;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #1e293b;">Batch</th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #1e293b;">Total Alumni</th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #1e293b;">Responded</th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #1e293b;">Response Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${batchStats.map(batch => {
                                const responseRate = Number(batch.responseRate || 0);
                                const rateColor = responseRate >= 70 ? '#10b981' : responseRate >= 40 ? '#f59e0b' : '#ef4444';
                                return `
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 12px 16px; font-weight: 500; color: #1e293b;">${escapeHtml(String(batch.batch))}</td>
                                        <td style="padding: 12px 16px; color: #64748b;">${batch.totalAlumni}</td>
                                        <td style="padding: 12px 16px; color: #64748b;">${batch.respondentCount}</td>
                                        <td style="padding: 12px 16px; font-weight: 600; color: ${rateColor};">${responseRate}%</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
                </div>
            </details>
        `;
    }

    container.innerHTML = html;
}

function renderSurveyDetails() {
    const container = document.getElementById('surveyDetails');
    const contextTitle = document.getElementById('responseSurveyContextTitle');
    const editButton = document.getElementById('responseEditSurveyButton');
    if (!container && !contextTitle && !editButton) return;

    const survey = getSelectedSurvey();
    if (!survey) {
        if (container) {
            container.innerHTML = '<p style="margin: 0; color: #7f8c8d;">Survey details are unavailable right now.</p>';
        }
        if (contextTitle) contextTitle.textContent = 'Survey unavailable';
        if (editButton) editButton.hidden = true;
        return;
    }

    if (contextTitle) contextTitle.textContent = survey.title || 'Untitled Survey';
    if (editButton) {
        editButton.hidden = false;
        editButton.onclick = () => openSurveyEditorPage(Number(survey.id));
    }
    if (!container) return;

    const surveyTitle = escapeHtml(survey.title || 'Untitled Survey');
    const surveyDescription = escapeHtml(survey.description || '');
    const categories = getSurveyCategoryGroups(survey);
    const categoryItems = categories.length > 0
        ? categories.map((category) => `
            <div style="padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 10px; background: #fff;">
                <div style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 8px;">
                    ${escapeHtml(category.name || 'Uncategorized')}
                </div>
                <ol style="margin: 0; padding-left: 18px; color: #475569;">
                    ${category.questions.map((question) => `
                        <li style="margin-bottom: 8px;">
                            ${escapeHtml(question.text || '')}
                        </li>
                    `).join('')}
                </ol>
            </div>
        `).join('')
        : '<p style="color: #64748b; margin: 0;">No categories or questions were added to this survey yet.</p>';

    container.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
            <div>
                <div style="font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px;">
                    Form Name
                </div>
                <div style="font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">
                    ${surveyTitle}
                </div>
                ${surveyDescription ? `<p style="margin: 0; color: #64748b;">${surveyDescription}</p>` : ''}
            </div>
            <button type="button" class="btn-secondary" onclick="openSurveyEditorPage(${Number(survey.id)})">Edit Form</button>
        </div>
        <div style="font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 8px;">
            Categories In This Form
        </div>
        ${categoryItems}
    `;
}

function getSurveyCategoryGroups(survey) {
    if (Array.isArray(survey?.categories) && survey.categories.length > 0) {
        return survey.categories.map((category) => ({
            id: Number(category.id || 0) || null,
            name: category.name || category.category_name || '',
            description: category.description || '',
            questions: Array.isArray(category.questions) ? category.questions.map((question) => ({
                id: Number(question.id || 0) || null,
                text: question.text || '',
                type: question.type || 'multiple_choice',
                required: question.required !== false && question.required !== 0 && question.required !== '0',
                options: Array.isArray(question.options) ? [...question.options] : [],
                choices: Array.isArray(question.choices) ? [...question.choices] : [],
                ratingMin: question.ratingMin ?? 1,
                ratingMax: question.ratingMax ?? 5,
                ratingMinLabel: question.ratingMinLabel || '',
                ratingMaxLabel: question.ratingMaxLabel || '',
                logic: question.logic || null,
            })) : [],
        }));
    }

    const fallbackQuestions = Array.isArray(survey?.questions) ? survey.questions : [];
    if (fallbackQuestions.length === 0) {
        return [];
    }

    return [{
        id: null,
        name: 'General',
        description: '',
        questions: fallbackQuestions.map((question) => ({
            id: Number(question.id || 0) || null,
            text: question.text || '',
            type: question.type || 'multiple_choice',
            required: question.required !== false && question.required !== 0 && question.required !== '0',
            options: Array.isArray(question.options) ? [...question.options] : [],
            choices: Array.isArray(question.choices) ? [...question.choices] : [],
            ratingMin: question.ratingMin ?? 1,
            ratingMax: question.ratingMax ?? 5,
            ratingMinLabel: question.ratingMinLabel || '',
            ratingMaxLabel: question.ratingMaxLabel || '',
            logic: question.logic || null,
        })),
    }];
}

function getWizardQuestionCount() {
    if (!surveyWizardData || !Array.isArray(surveyWizardData.categories)) return 0;
    return surveyWizardData.categories.reduce((total, category) => {
        const questions = Array.isArray(category.questions) ? category.questions.length : 0;
        return total + questions;
    }, 0);
}

function buildSurveyCategoriesPayload() {
    const categories = surveyWizardData?.categories || [];
    return categories.map((category) => ({
        ...(category.id && Number.isInteger(Number(category.id)) ? { id: Number(category.id) } : {}),
        name: category.name,
        description: category.description || '',
        questions: (Array.isArray(category.questions) ? category.questions : []).map((question) => {
            const q = {
                ...(question.id && Number.isInteger(Number(question.id)) ? { id: Number(question.id) } : {}),
                text: question.text,
                type: question.type,
                required: question.required !== false && question.required !== 0 && question.required !== '0',
                options: question.type === 'multiple_choice' ? (question.options || []) : [],
            };
            if (question.type === 'multiple_choice') {
                q.logic = question.logic || { enabled: false, rules: [] };
            }
            if (question.type === 'rating') {
                q.ratingMin = question.ratingMin ?? 1;
                q.ratingMax = question.ratingMax ?? 5;
                q.ratingMinLabel = question.ratingMinLabel ?? '';
                q.ratingMaxLabel = question.ratingMaxLabel ?? '';
            }
            return q;
        }),
    }));
}

function initializeSurveyTimeDropdowns() {
    return;
}

function splitSurveyDatetimeForInputs(value) {
    if (!value) return { date: '', hour: '', minute: '', meridiem: '' };
    const text = String(value).trim().replace(' ', 'T');
    const hour24 = Number(text.slice(11, 13));
    const minute = text.slice(14, 16);
    const meridiem = hour24 >= 12 ? 'PM' : 'AM';
    const hour12 = ((hour24 + 11) % 12) + 1;
    return {
        date: text.slice(0, 10),
        hour: String(hour12),
        minute,
        meridiem,
    };
}

function combineSurveyDatetimeFromInputs(dateValue, hourValue, minuteValue, meridiemValue) {
    if (!dateValue && !hourValue && !minuteValue && !meridiemValue) return null;
    if (!dateValue || !hourValue || !minuteValue || !meridiemValue) return null;

    const hourNumber = Number(hourValue);
    const minuteNumber = Number(minuteValue);
    if (!Number.isInteger(hourNumber) || hourNumber < 1 || hourNumber > 12) return null;
    if (!Number.isInteger(minuteNumber) || minuteNumber < 0 || minuteNumber > 59) return null;
    if (!['AM', 'PM'].includes(meridiemValue)) return null;

    let hour24 = hourNumber % 12;
    if (meridiemValue === 'PM') {
        hour24 += 12;
    }

    return `${dateValue} ${String(hour24).padStart(2, '0')}:${String(minuteNumber).padStart(2, '0')}:00`;
}

function formatSurveyDatetimeForInput(value) {
    return splitSurveyDatetimeForInputs(value);
}

function formatSurveyDatetimeLabel(value) {
    if (!value) return 'Not set';

    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) {
        return String(value);
    }

    return parsed.toLocaleString([], {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function getSurveyScheduleSummary(survey) {
    const openAt = survey?.openDateTime;
    const closeAt = survey?.closeDateTime;

    if (openAt && closeAt) {
        return `Open: ${formatSurveyDatetimeLabel(openAt)} · Close: ${formatSurveyDatetimeLabel(closeAt)}`;
    }
    if (openAt) {
        return `Opens: ${formatSurveyDatetimeLabel(openAt)}`;
    }
    if (closeAt) {
        return `Closes: ${formatSurveyDatetimeLabel(closeAt)}`;
    }
    return 'No schedule set';
}

function getSurveyAvailabilityBadge(survey) {
    if (survey?.status === 'closed') {
        return { text: 'Closed', className: 'is-closed' };
    }

    if (survey?.isAcceptingResponses) {
        return { text: 'Open', className: 'is-open' };
    }

    const openAt = survey?.openDateTime ? new Date(String(survey.openDateTime).replace(' ', 'T')).getTime() : null;
    if (openAt && !Number.isNaN(openAt) && Date.now() < openAt) {
        return { text: 'Scheduled', className: 'is-open' };
    }

    return { text: 'Unavailable', className: 'is-closed' };
}

function isSurveyReadyToPost(survey) {
    return Boolean(survey?.openDateTime) && Boolean(survey?.closeDateTime);
}

async function onSurveySelectionChange() {
    const survey = getSelectedSurvey();
    await loadSurveyResponsesAndStats(survey ? survey.id : null);
    renderSurveyDetails();
    renderSurveyQuestionsList();
    renderSurveyResponseForm();
    renderSurveyResponsesTable();
    renderSurveyAnalytics();
    renderSurveyStatusPanel();
    renderStaffSurveyFeed();
}

// Survey wizard utilities and step handlers (spliced into StaffCommon.js)

const WIZARD_TYPE_LABELS = {
    multiple_choice: 'Multiple choice',
    rating: 'Rating scale',
    text: 'Short text answer',
    image: 'Image upload',
};

const WIZARD_TYPE_DESCRIPTIONS = {
    multiple_choice: 'Respondents select from predefined options',
    rating: 'Respondents rate on a numeric scale',
    text: 'Respondents provide a short written response',
    image: 'Respondents upload an image file (JPG, PNG, WEBP, GIF)',
};

function wizardShowToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toastContainer');
    if (!container) return null;

    const icons = {
        success: 'fa-check',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle',
    };

    const toast = document.createElement('div');
    toast.className = `survey-wizard-toast survey-wizard-toast-${type}`;
    toast.innerHTML = `
        <div class="survey-wizard-toast-content">
            <i class="fas ${icons[type] || icons.info}" aria-hidden="true"></i>
            <span>${escapeHtml(message)}</span>
        </div>
        <button type="button" class="survey-wizard-toast-close" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    `;
    toast.querySelector('.survey-wizard-toast-close').addEventListener('click', () => toast.remove());
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    if (duration > 0) {
        window.setTimeout(() => {
            toast.classList.remove('show');
            window.setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    return toast;
}

function wizardStorageGet(key, defaultValue = null) {
    try {
        const item = localStorage.getItem(key);
        return item ? JSON.parse(item) : defaultValue;
    } catch (error) {
        return defaultValue;
    }
}

function wizardStorageSet(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch (error) {
        wizardShowToast('Unable to save data. Storage may be full.', 'error');
        return false;
    }
}

function wizardStorageRemove(key) {
    try {
        localStorage.removeItem(key);
        return true;
    } catch (error) {
        return false;
    }
}

function wizardMarkChanged() {
    surveyWizardHasChanges = true;
}

function wizardClearChanged() {
    surveyWizardHasChanges = false;
}

function wizardInitChangeTracker() {
    window.addEventListener('beforeunload', (event) => {
        if (surveyWizardHasChanges) {
            event.preventDefault();
            event.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
}

const WIZARD_PREMADE_CATEGORIES = [
    {
        id: 'cat_premade_employment_status',
        name: 'Employment Status',
        isPreMade: true,
        questions: [
            {
                id: 'q_premade_employment_status',
                text: 'What is your employment status?',
                type: 'multiple_choice',
                required: true,
                isPreMade: true,
                options: ['Employed', 'Unemployed'],
            },
        ],
    },
    {
        id: 'cat_premade_employment_info',
        name: 'Employment Information',
        isPreMade: true,
        questions: [
            {
                id: 'q_premade_time_to_first_job',
                text: 'How long did it take you to find your first job after graduation?',
                type: 'multiple_choice',
                required: true,
                isPreMade: true,
                options: [
                    'Less than 1 month',
                    '1 to 3 months',
                    '3 to 6 months',
                    '6 to 12 months',
                    'More than a year',
                ],
            },
            {
                id: 'q_premade_job_related_to_degree',
                text: 'Is your job related to your degree?',
                type: 'multiple_choice',
                required: true,
                isPreMade: true,
                options: [
                    'Related',
                    'Moderately Related',
                    'Unrelated',
                ],
            },
        ],
    },
];

function wizardNormalizeLookupText(value) {
    return String(value || '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

function wizardGetPreMadeCategorySpec(categoryOrName) {
    const rawName = typeof categoryOrName === 'string'
        ? categoryOrName
        : (categoryOrName?.name || '');
    const norm = wizardNormalizeLookupText(rawName);
    if (!norm) return null;
    if (norm === 'employement information') {
        return WIZARD_PREMADE_CATEGORIES[1];
    }
    return WIZARD_PREMADE_CATEGORIES.find(
        (spec) => wizardNormalizeLookupText(spec.name) === norm
    ) || null;
}

function wizardGetPreMadeQuestionSpec(questionOrText) {
    const rawText = typeof questionOrText === 'string'
        ? questionOrText
        : (questionOrText?.text || questionOrText?.question_text || '');
    const norm = wizardNormalizeLookupText(rawText);
    if (!norm) return null;
    for (const catSpec of WIZARD_PREMADE_CATEGORIES) {
        for (const qSpec of catSpec.questions) {
            if (wizardNormalizeLookupText(qSpec.text) === norm) {
                return qSpec;
            }
        }
    }
    return null;
}

function wizardIsPreMadeQuestion(question) {
    if (!question) return false;
    return Boolean(question.isPreMade || wizardGetPreMadeQuestionSpec(question));
}

function wizardIsPreMadeCategory(category) {
    if (!category) return false;
    if (category.isPreMade || wizardGetPreMadeCategorySpec(category)) return true;
    return Array.isArray(category.questions) && category.questions.some((q) => wizardIsPreMadeQuestion(q));
}

function wizardClonePreMadeCategories() {
    return WIZARD_PREMADE_CATEGORIES.map((cat) => ({
        id: cat.id,
        name: cat.name,
        isPreMade: true,
        questions: cat.questions.map((q) => ({
            id: q.id,
            text: q.text,
            type: q.type,
            required: true,
            isPreMade: true,
            options: [...q.options],
        })),
    }));
}

function wizardEnsurePreMadeStructure(data) {
    if (!data || typeof data !== 'object') return data;
    if (!Array.isArray(data.categories)) {
        data.categories = [];
    }

    // Remove legacy empty default "General" placeholder if it has no questions
    data.categories = data.categories.filter((cat) => {
        const isEmptyGeneral = wizardNormalizeLookupText(cat?.name) === 'general'
            && (!Array.isArray(cat?.questions) || cat.questions.length === 0);
        return !isEmptyGeneral;
    });

    const prependCategories = [];

    WIZARD_PREMADE_CATEGORIES.forEach((catSpec) => {
        let targetCat = data.categories.find(
            (c) => wizardGetPreMadeCategorySpec(c)?.id === catSpec.id || String(c?.id) === catSpec.id
        );

        if (!targetCat) {
            targetCat = {
                id: catSpec.id,
                name: catSpec.name,
                isPreMade: true,
                questions: [],
            };
            prependCategories.push(targetCat);
        } else {
            targetCat.name = catSpec.name;
            targetCat.isPreMade = true;
            if (!Array.isArray(targetCat.questions)) {
                targetCat.questions = [];
            }
        }

        const missingPreMadeQuestions = [];
        catSpec.questions.forEach((qSpec) => {
            const existingQ = targetCat.questions.find(
                (q) => wizardGetPreMadeQuestionSpec(q)?.id === qSpec.id || String(q?.id) === qSpec.id
            );
            if (existingQ) {
                existingQ.text = qSpec.text;
                existingQ.type = qSpec.type;
                existingQ.required = true;
                existingQ.isPreMade = true;
                existingQ.options = [...qSpec.options];
            } else {
                missingPreMadeQuestions.push({
                    id: qSpec.id,
                    text: qSpec.text,
                    type: qSpec.type,
                    required: true,
                    isPreMade: true,
                    options: [...qSpec.options],
                });
            }
        });

        if (missingPreMadeQuestions.length > 0) {
            targetCat.questions = [...missingPreMadeQuestions, ...targetCat.questions];
        }
    });

    if (prependCategories.length > 0) {
        data.categories = [...prependCategories, ...data.categories];
    }

    return data;
}

function wizardDefaultData() {
    return {
        surveyId: null,
        formName: '',
        formDetails: '',
        dateOpen: '',
        dateClose: '',
        categories: wizardClonePreMadeCategories(),
    };
}

function wizardDateToOpenDatetime(dateValue) {
    if (!dateValue) return null;
    return `${dateValue} 00:00:00`;
}

function wizardDateToCloseDatetime(dateValue) {
    if (!dateValue) return null;
    return `${dateValue} 23:59:59`;
}

function wizardDatetimeToDate(value) {
    if (!value) return '';
    return String(value).trim().slice(0, 10);
}

function wizardValidateDetails(data) {
    const errors = [];
    const fieldErrors = {};
    const isEditingExisting = Boolean(data && data.surveyId);
    const existingSurvey = isEditingExisting
        ? (surveysData.find((s) => Number(s.id) === Number(data.surveyId)) || null)
        : null;

    const formName = String(data.formName || existingSurvey?.title || '').trim();
    const formDetails = String(data.formDetails ?? existingSurvey?.description ?? '');
    const dateOpen = data.dateOpen
        || data.originalDateOpen
        || wizardDatetimeToDate(existingSurvey?.openDateTime || existingSurvey?.open_datetime || existingSurvey?.createdAt || existingSurvey?.created_at)
        || '';
    const dateClose = data.dateClose
        || data.originalDateClose
        || wizardDatetimeToDate(existingSurvey?.closeDateTime || existingSurvey?.close_datetime || existingSurvey?.openDateTime || existingSurvey?.open_datetime || existingSurvey?.createdAt || existingSurvey?.created_at)
        || '';

    if (data) {
        data.formName = formName;
        data.formDetails = formDetails;
        if (dateOpen) data.dateOpen = dateOpen;
        if (dateClose) data.dateClose = dateClose;
    }

    if (!formName) {
        const msg = 'Survey name is required';
        errors.push(msg);
        fieldErrors.wizardFormName = msg;
    } else if (formName.length > 200) {
        const msg = 'Survey name must be under 200 characters';
        errors.push(msg);
        fieldErrors.wizardFormName = msg;
    }

    if (formDetails.length > 2000) {
        const msg = 'Description must be under 2000 characters';
        errors.push(msg);
        fieldErrors.wizardFormDetails = msg;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const openDate = dateOpen ? new Date(`${dateOpen}T00:00:00`) : null;
    const closeDate = dateClose ? new Date(`${dateClose}T00:00:00`) : null;

    if (!dateOpen) {
        const msg = 'Opening date is required';
        errors.push(msg);
        fieldErrors.wizardDateOpen = msg;
    } else if (!isEditingExisting && openDate < today) {
        const msg = 'Opening date cannot be in the past';
        errors.push(msg);
        fieldErrors.wizardDateOpen = msg;
    }

    if (!dateClose) {
        const msg = 'Closing date is required';
        errors.push(msg);
        fieldErrors.wizardDateClose = msg;
    } else if (!isEditingExisting && closeDate < today) {
        const msg = 'Closing date cannot be in the past';
        errors.push(msg);
        fieldErrors.wizardDateClose = msg;
    }

    if (dateOpen && dateClose && openDate > closeDate) {
        const msg = 'Closing date must be after opening date';
        errors.push(msg);
        fieldErrors.wizardDateClose = msg;
    }

    return { valid: errors.length === 0, errors, fieldErrors };
}

function wizardValidateQuestions(categories) {
    const errors = [];
    let totalQuestions = 0;

    (categories || []).forEach((category, catIndex) => {
        const categoryName = String(category.name || '').trim() || `Category ${catIndex + 1}`;
        if (!String(category.name || '').trim()) {
            errors.push(`Category ${catIndex + 1}: name is required`);
        }

        (category.questions || []).forEach((question, qIndex) => {
            totalQuestions += 1;
            const label = `Category "${categoryName}", Q${qIndex + 1}`;

            if (!String(question.text || '').trim()) {
                errors.push(`${label}: question text is required`);
            }
            if (String(question.text || '').length > 500) {
                errors.push(`${label}: question too long (max 500 chars)`);
            }
            if (!question.type) {
                errors.push(`${label}: question type is required`);
            }
            if (question.type === 'multiple_choice') {
                const options = Array.isArray(question.options) ? question.options : [];
                if (options.length < 2) {
                    errors.push(`${label}: multiple choice needs at least 2 options`);
                }
                options.forEach((option, oIndex) => {
                    if (!String(option || '').trim()) {
                        errors.push(`${label}, Option ${oIndex + 1}: cannot be empty`);
                    }
                    if (String(option || '').length > 200) {
                        errors.push(`${label}, Option ${oIndex + 1}: too long (max 200 chars)`);
                    }
                });
            }
        });
    });

    if (totalQuestions === 0) {
        errors.push('At least one question is required');
    }

    return { valid: errors.length === 0, errors, totalQuestions };
}

function wizardValidateForPublish(data) {
    const detailsValidation = wizardValidateDetails(data);
    if (!detailsValidation.valid) {
        return { valid: false, errors: detailsValidation.errors, fieldErrors: detailsValidation.fieldErrors };
    }
    const questionsValidation = wizardValidateQuestions(data.categories || []);
    if (!questionsValidation.valid) {
        return { valid: false, errors: questionsValidation.errors };
    }
    return { valid: true, errors: [], totalQuestions: questionsValidation.totalQuestions };
}

function wizardShowErrorList(containerId, itemsId, errors) {
    const list = document.getElementById(containerId);
    const items = document.getElementById(itemsId);
    if (!list || !items || errors.length === 0) return;
    items.innerHTML = errors.map((error) => `<div class="survey-wizard-error-list-item">${escapeHtml(error)}</div>`).join('');
    list.classList.add('show');
}

function wizardClearStep1Errors() {
    const list = document.getElementById('wizardStep1ErrorList');
    const items = document.getElementById('wizardStep1ErrorItems');
    if (list) list.classList.remove('show');
    if (items) items.innerHTML = '';
    ['wizardFormName', 'wizardFormDetails', 'wizardDateOpen', 'wizardDateClose'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('error');
        const errEl = document.getElementById(`${id}Error`);
        if (errEl) {
            errEl.textContent = '';
            errEl.classList.remove('show');
        }
    });
}

function wizardApplyStep1FieldErrors(fieldErrors = {}) {
    wizardClearStep1Errors();
    let firstInvalidElement = null;

    Object.entries(fieldErrors).forEach(([fieldId, message]) => {
        const inputEl = document.getElementById(fieldId);
        const errorEl = document.getElementById(`${fieldId}Error`);
        if (inputEl) {
            inputEl.classList.add('error');
            if (!firstInvalidElement) {
                firstInvalidElement = inputEl;
            }

            const clearHandler = () => {
                inputEl.classList.remove('error');
                if (errorEl) {
                    errorEl.textContent = '';
                    errorEl.classList.remove('show');
                }
                inputEl.removeEventListener('input', clearHandler);
                inputEl.removeEventListener('change', clearHandler);
            };
            inputEl.addEventListener('input', clearHandler);
            inputEl.addEventListener('change', clearHandler);
        }
        if (errorEl && message) {
            errorEl.innerHTML = `<i class="fas fa-exclamation-circle"></i> <span>${escapeHtml(message)}</span>`;
            errorEl.classList.add('show');
        }
    });

    if (firstInvalidElement) {
        firstInvalidElement.focus();
        firstInvalidElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function wizardApplyQuestionErrors(validation) {
    if (!surveyWizardData || !surveyWizardData.categories) return;

    if (validation.totalQuestions === 0) {
        const firstCat = surveyWizardData.categories[0];
        if (firstCat) {
            surveyWizardCollapsedCategories.delete(firstCat.id);
            wizardRenderCategories();
            const qInput = document.querySelector(`.wizard-question-input[data-cat-id="${firstCat.id}"]`);
            if (qInput) {
                qInput.classList.add('error');
                qInput.focus();
                qInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                const clearQHandler = () => {
                    qInput.classList.remove('error');
                    qInput.removeEventListener('input', clearQHandler);
                };
                qInput.addEventListener('input', clearQHandler);
            }
        } else {
            const addCatBtn = document.querySelector('.survey-wizard-add-category-btn');
            if (addCatBtn) {
                addCatBtn.classList.add('error');
                addCatBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        return;
    }

    let firstInvalid = null;
    surveyWizardData.categories.forEach((category) => {
        if (!String(category.name || '').trim()) {
            surveyWizardCollapsedCategories.delete(category.id);
            wizardRenderCategories();
            const catNameInput = document.querySelector(`.survey-wizard-category-name-input[data-cat-id="${category.id}"]`);
            if (catNameInput) {
                catNameInput.classList.add('error');
                if (!firstInvalid) firstInvalid = catNameInput;
                const clearNameHandler = () => {
                    catNameInput.classList.remove('error');
                    catNameInput.removeEventListener('input', clearNameHandler);
                };
                catNameInput.addEventListener('input', clearNameHandler);
            }
        }
    });

    if (firstInvalid) {
        firstInvalid.focus();
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function wizardReadStep1Fields() {
    return {
        formName: document.getElementById('wizardFormName')?.value || '',
        formDetails: document.getElementById('wizardFormDetails')?.value || '',
        dateOpen: document.getElementById('wizardDateOpen')?.value || '',
        dateClose: document.getElementById('wizardDateClose')?.value || '',
    };
}

function wizardPersistData() {
    if (!surveyWizardData) return;
    wizardStorageSet(SURVEY_WIZARD_STORAGE_KEY, surveyWizardData);
}

function wizardSyncStep1ToData() {
    if (!surveyWizardData) surveyWizardData = wizardDefaultData();
    const fields = wizardReadStep1Fields();
    if (surveyWizardData.surveyId) {
        const existingSurvey = surveysData.find((s) => Number(s.id) === Number(surveyWizardData.surveyId)) || null;
        if (!fields.formName) {
            fields.formName = surveyWizardData.formName || existingSurvey?.title || '';
        }
        if (!fields.formDetails && surveyWizardData.formDetails) {
            fields.formDetails = surveyWizardData.formDetails;
        }
        if (!fields.dateOpen) {
            fields.dateOpen = surveyWizardData.dateOpen
                || surveyWizardData.originalDateOpen
                || wizardDatetimeToDate(existingSurvey?.openDateTime || existingSurvey?.open_datetime || existingSurvey?.createdAt || existingSurvey?.created_at)
                || '';
        }
        if (!fields.dateClose) {
            fields.dateClose = surveyWizardData.dateClose
                || surveyWizardData.originalDateClose
                || wizardDatetimeToDate(existingSurvey?.closeDateTime || existingSurvey?.close_datetime || existingSurvey?.openDateTime || existingSurvey?.open_datetime || existingSurvey?.createdAt || existingSurvey?.created_at)
                || '';
        }
    }
    Object.assign(surveyWizardData, fields);
    surveyWizardData.categories = surveyWizardData.categories || [];
    wizardEnsurePreMadeStructure(surveyWizardData);
    wizardPersistData();
}

function wizardHandleProgressStepClick(targetStep) {
    const numStep = Number(targetStep);
    if (!numStep || numStep === Number(surveyWizardCurrentStep)) return;
    if (numStep === 1) {
        wizardGoToStep(1);
    } else if (numStep === 2) {
        wizardStep1Next();
    } else if (numStep === 3) {
        wizardSyncStep1ToData();
        const dVal = wizardValidateDetails(surveyWizardData);
        if (!dVal.valid) {
            wizardGoToStep(1);
            wizardShowErrorList('wizardStep1ErrorList', 'wizardStep1ErrorItems', dVal.errors);
            return;
        }
        wizardStep2Next();
    } else if (numStep === 4) {
        wizardStep3Next();
    }
}

function wizardRenderProgress(activeStep) {
    const steps = [
        { num: 1, label: 'Details' },
        { num: 2, label: 'Questions' },
        { num: 3, label: 'Preview' },
        { num: 4, label: 'Publish' },
    ];
    const renderBar = (current) => steps.map((step, index) => {
        const done = current === 'success' || step.num < current;
        const active = current !== 'success' && step.num === current;
        const dotClass = done ? 'done' : (active ? 'active' : 'idle');
        const labelClass = done ? 'done' : (active ? 'active' : 'idle');
        const line = index < steps.length - 1
            ? `<div class="survey-wizard-prog-line${done ? ' done' : ''}"></div>`
            : '';
        const dotContent = done ? '<i class="fas fa-check" style="font-size:11px;"></i>' : String(step.num);
        return `
            <div class="survey-wizard-prog-step" style="cursor:pointer;" onclick="wizardHandleProgressStepClick(${step.num})" title="Go to ${step.label}">
                <div class="survey-wizard-prog-dot ${dotClass}">${dotContent}</div>
                <span class="survey-wizard-prog-label ${labelClass}">${step.label}</span>
            </div>
            ${line}
        `;
    }).join('');

    const progress = document.getElementById('surveyWizardProgress');
    const successProgress = document.getElementById('surveyWizardSuccessProgress');
    if (progress) progress.innerHTML = renderBar(activeStep);
    if (successProgress) successProgress.innerHTML = renderBar('success');
}

function wizardGoToStep(step) {
    surveyWizardCurrentStep = step;
    wizardRenderProgress(step);

    const stepTitles = {
        1: { title: 'Survey details', sub: 'Start by giving your survey a name and setting the dates.' },
        2: { title: 'Add questions', sub: 'Create categories and add your survey questions.' },
        3: { title: 'Preview survey', sub: 'Check how your survey will appear to alumni respondents.' },
        4: { title: 'Publish survey', sub: 'Choose when and how to publish your survey.' }
    };

    const nav = document.getElementById('surveyWizardNav');
    const titleEl = document.getElementById('wizardSharedStepTitle');
    const subEl = document.getElementById('wizardSharedStepSub');
    const footerEl = document.getElementById('wizardSharedFooter');

    if (step === 'success' || step === 5) {
        if (nav) nav.hidden = true;
    } else {
        if (nav) nav.hidden = false;
        const numStep = Number(step);
        if (stepTitles[numStep]) {
            if (titleEl) titleEl.textContent = stepTitles[numStep].title;
            if (subEl) subEl.textContent = stepTitles[numStep].sub;
        }
        if (footerEl) {
            let footerHtml = '';
            if (numStep === 1) {
                footerHtml = `
                    <button class="btn-secondary" type="button" onclick="wizardSaveDraft()">Save draft</button>
                    <button class="btn-primary" type="button" onclick="wizardStep1Next()">Next &rarr;</button>`;
            } else if (numStep === 2) {
                footerHtml = `
                    <button class="btn-secondary" type="button" onclick="wizardGoToStep(1)">&larr; Back</button>
                    <button class="btn-primary" type="button" onclick="wizardStep2Next()">Preview &rarr;</button>`;
            } else if (numStep === 3) {
                footerHtml = `
                    <button class="btn-secondary" type="button" onclick="wizardGoToStep(2)">&larr; Edit questions</button>
                    <button class="btn-primary" type="button" id="wizardPreviewNextBtn" onclick="wizardStep3Next()">Next &rarr;</button>`;
            } else if (numStep === 4) {
                footerHtml = `
                    <button class="btn-secondary" type="button" onclick="wizardGoToStep(3)">&larr; Back to preview</button>
                    <button class="btn-primary" type="button" id="wizardPublishBtn" onclick="wizardPublish()">Publish survey</button>`;
            }
            footerEl.innerHTML = footerHtml;
        }
    }

    document.querySelectorAll('.survey-wizard-step').forEach((panel) => {
        const panelStep = panel.getAttribute('data-step');
        const visible = panelStep === 'success'
            ? (step === 'success' || step === 5)
            : Number(panelStep) === Number(step);
        panel.hidden = !visible;
    });

    if (Number(step) === 2) wizardRenderCategories();
    if (Number(step) === 3) wizardRenderPreview();
    if (Number(step) === 4) wizardInitPublishStep();

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function wizardUpdateProgress(step) {
    wizardRenderProgress(step);
}

function wizardGetPillClass(type) {
    if (type === 'multiple_choice') return 'mc';
    if (type === 'rating') return 'rt';
    if (type === 'image') return 'img';
    return 'tx';
}

function wizardCollectAnswerOptions(catId) {
    return Array.from(document.querySelectorAll(`.wizard-option-input[data-cat-id="${catId}"]`))
        .map((input) => input.value.trim())
        .filter(Boolean);
}

function wizardRenderCategories() {
    const container = document.getElementById('wizardCategoriesContainer');
    if (!container || !surveyWizardData) return;

    const categories = surveyWizardData.categories || [];
    const totalCategories = categories.length;
    let totalQuestions = 0;

    // Update Draft Canvas Header Stats Badge
    const totalQEl = document.getElementById('wizardDraftTotalQuestions');
    const totalCEl = document.getElementById('wizardDraftTotalCategories');
    categories.forEach(c => { totalQuestions += (c.questions || []).length; });
    if (totalQEl) totalQEl.textContent = `${totalQuestions} Question${totalQuestions === 1 ? '' : 's'}`;
    if (totalCEl) totalCEl.textContent = `${totalCategories} Categor${totalCategories === 1 ? 'y' : 'ies'}`;

    // Update Left Builder Outline Summary
    const summaryContainer = document.getElementById('wizardDraftCategorySummary');
    if (summaryContainer) {
        if (totalCategories === 0) {
            summaryContainer.innerHTML = '<div class="survey-wizard-draft-summary-empty"><i class="fas fa-inbox" style="margin-bottom:4px;display:block;"></i>No categories in draft yet.</div>';
        } else {
            summaryContainer.innerHTML = categories.map((cat, idx) => {
                const count = (cat.questions || []).length;
                return `
                    <div class="survey-wizard-draft-summary-item" onclick="wizardScrollToCategory('${cat.id}')" title="Jump to ${escapeHtml(cat.name || 'Category')}">
                        <span><i class="fas fa-folder" style="color:#173d73;margin-right:6px;"></i>${idx + 1}. ${escapeHtml(cat.name || 'Untitled Category')}</span>
                        <span class="survey-wizard-draft-summary-badge">${count} Q</span>
                    </div>
                `;
            }).join('');
        }
    }

    container.innerHTML = '';
    if (totalCategories === 0) {
        container.innerHTML = `
            <div class="survey-wizard-empty-state" style="padding:48px 24px;background:#fff;border:2px dashed #cbd5e1;border-radius:16px;">
                <i class="fas fa-folder-open" style="font-size:38px;color:#94a3b8;margin-bottom:12px;display:block;"></i>
                <h4 style="margin:0 0 6px;font-size:16px;font-weight:700;color:#334155;">No categories in draft yet</h4>
                <p style="margin:0;font-size:13.5px;color:#64748b;">Use the builder panel on the left or the button below to add your first survey category.</p>
            </div>
        `;
        return;
    }

    categories.forEach((category, catIndex) => {
        const isCollapsed = surveyWizardCollapsedCategories.has(category.id);
        const isLockedCategory = wizardIsPreMadeCategory(category);
        const qCount = (category.questions || []).length;
        let questionsHtml = '';

        if (qCount === 0) {
            questionsHtml = '<div class="survey-wizard-empty-state"><i class="fas fa-inbox"></i><div>No questions in this category yet. Add one below!</div></div>';
        } else {
            let counter = surveyWizardData.categories.slice(0, catIndex).reduce((sum, c) => sum + (c.questions?.length || 0), 0);
            questionsHtml = (category.questions || []).map((question) => {
                counter += 1;
                const isLockedQuestion = wizardIsPreMadeQuestion(question);
                const optionsNote = question.type === 'multiple_choice' && question.options?.length
                    ? `<div style="margin-top:6px;font-size:12px;color:#64748b;">Options: ${question.options.map((o) => escapeHtml(o)).join(' • ')}</div>`
                    : '';
                const ratingNote = question.type === 'rating'
                    ? `<div style="margin-top:6px;font-size:12px;color:#94a3b8;">Scale: ${question.ratingMin ?? 1}–${question.ratingMax ?? 5}</div>`
                    : '';
                const logicBadge = question.type === 'multiple_choice' && question.logic?.enabled
                    ? `<span class="wizard-logic-badge"><i class="fas fa-code-branch"></i> Section logic</span>`
                    : '';
                const preMadeBadge = isLockedQuestion
                    ? `<span class="survey-wizard-premade-badge" title="Pre-made question — cannot be deleted"><i class="fas fa-lock"></i> Pre-made</span>`
                    : '';
                const deleteBtnHtml = isLockedQuestion
                    ? `<button type="button" class="survey-wizard-icon-btn is-locked" title="Pre-made question cannot be deleted" disabled aria-disabled="true"><i class="fas fa-lock"></i></button>`
                    : `<button type="button" class="survey-wizard-icon-btn del" title="Delete" onclick="wizardDeleteQuestion('${category.id}', '${question.id}')"><i class="fas fa-trash"></i></button>`;
                return `
                    <div class="survey-wizard-q-item">
                        <div class="survey-wizard-q-num">${counter}</div>
                        <div style="flex:1;">
                            <div class="survey-wizard-q-text">${escapeHtml(question.text)}</div>
                            <span class="survey-wizard-type-pill ${wizardGetPillClass(question.type)}">${WIZARD_TYPE_LABELS[question.type] || question.type}</span>
                            ${(question.required !== false && question.required !== 0 && question.required !== '0')
                                ? `<span class="survey-wizard-required-badge is-required" title="Mandatory"><i class="fas fa-asterisk"></i> Required</span>`
                                : `<span class="survey-wizard-required-badge is-optional" title="Optional">Optional</span>`}
                            ${preMadeBadge}
                            ${optionsNote}${ratingNote}${logicBadge}
                        </div>
                        <div class="survey-wizard-q-actions">
                            <button type="button" class="survey-wizard-icon-btn" title="Edit" onclick="wizardEditQuestion('${category.id}', '${question.id}')"><i class="fas fa-edit"></i></button>
                            ${deleteBtnHtml}
                        </div>
                    </div>
                `;
            }).join('');
        }

        const card = document.createElement('div');
        card.className = `section-card survey-wizard-category-card${isCollapsed ? ' collapsed' : ''}`;
        card.dataset.catId = category.id;
        card.innerHTML = `
            <div class="survey-wizard-category-bar">
                <button type="button" class="survey-wizard-collapse-btn" title="${isCollapsed ? 'Expand category' : 'Collapse category'}" onclick="wizardToggleCategoryCollapse('${category.id}')">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <i class="fas fa-tag" style="color:#94a3b8;"></i>
                <input type="text" class="survey-wizard-category-name-input" value="${escapeHtml(category.name)}" data-cat-id="${category.id}" ${isLockedCategory ? 'readonly title="Pre-made category name"' : ''}>
                <span class="survey-wizard-category-summary-badge">${qCount} question${qCount === 1 ? '' : 's'}</span>
                ${isLockedCategory
                    ? `<span class="survey-wizard-premade-badge" title="Pre-made category — cannot be deleted"><i class="fas fa-lock"></i> Pre-made</span>`
                    : `<span class="survey-wizard-category-badge"><i class="fas fa-folder"></i> Category</span>`}
                ${isLockedCategory
                    ? `<button type="button" class="survey-wizard-icon-btn is-locked" style="margin-left:4px;" title="Pre-made category cannot be deleted" disabled aria-disabled="true">
                        <i class="fas fa-lock"></i>
                    </button>`
                    : `<button type="button" class="survey-wizard-icon-btn del" style="margin-left:4px;" title="Delete category" onclick="wizardDeleteCategory('${category.id}')">
                        <i class="fas fa-trash"></i>
                    </button>`}
            </div>
            <div class="survey-wizard-category-body">
                <div class="survey-wizard-q-editor">
                    <div class="survey-wizard-section-title" style="margin-bottom:12px;"><i class="fas fa-pen"></i> Question editor</div>
                    <div class="survey-wizard-q-fields">
                        <div class="survey-wizard-field" style="margin-bottom:0;">
                            <label class="survey-wizard-label">Question <span class="required">*</span></label>
                            <textarea class="wizard-question-input" data-cat-id="${category.id}" maxlength="500" placeholder="Type your question here…"></textarea>
                        </div>
                        <div class="survey-wizard-field" style="margin-bottom:0;">
                            <label class="survey-wizard-label">Type <span class="required">*</span></label>
                            <select class="wizard-type-select" data-cat-id="${category.id}" onchange="wizardToggleAnswerOptions(this, '${category.id}')">
                                <option value="">Select…</option>
                                <option value="multiple_choice">Multiple choice</option>
                                <option value="rating">Rating scale</option>
                                <option value="text">Short text</option>
                                <option value="image">Image upload</option>
                            </select>
                            <div class="wizard-required-checkbox-wrap" style="margin-top:8px;">
                                <label class="wizard-required-label" style="display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:#334155;cursor:pointer;user-select:none;">
                                    <input type="checkbox" class="wizard-required-checkbox" data-cat-id="${category.id}" checked style="width:16px;height:16px;cursor:pointer;accent-color:#1e4a76;">
                                    <span>Required question</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-answer-options-section" data-cat-id="${category.id}" data-logic-enabled="false" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid #e2e8f0;">
                        <label class="survey-wizard-label">Answer options <span class="required">*</span></label>
                        <div class="survey-wizard-options-list" data-cat-id="${category.id}"></div>
                        <div class="wizard-mc-actions-row">
                            <button type="button" class="survey-wizard-icon-btn survey-wizard-add-option-btn" onclick="wizardAddAnswerOption('${category.id}')" title="Add option"><i class="fas fa-plus"></i></button>
                            <button type="button" class="wizard-logic-toggle-btn" data-cat-id="${category.id}" onclick="wizardToggleQuestionLogic('${category.id}')" title="Go to section based on answer">
                                <i class="fas fa-code-branch"></i> Go to section based on answer
                            </button>
                        </div>
                    </div>
                    <div class="wizard-rating-range-section" data-cat-id="${category.id}" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid #e2e8f0;">
                        <label class="survey-wizard-label">Rating scale range</label>
                        <div class="wizard-rating-range-row">
                            <div class="wizard-rating-range-item">
                                <span class="wizard-rating-range-from-label">From</span>
                                <select class="wizard-rating-min-select" data-cat-id="${category.id}" onchange="wizardUpdateRatingRange('${category.id}')">
                                    ${[0,1,2,3,4,5,6,7,8,9].map(v => `<option value="${v}"${v===1?' selected':''}>${v}</option>`).join('')}
                                </select>
                            </div>
                            <span class="wizard-rating-range-to">to</span>
                            <div class="wizard-rating-range-item">
                                <select class="wizard-rating-max-select" data-cat-id="${category.id}" onchange="wizardUpdateRatingRange('${category.id}')">
                                    ${[2,3,4,5,6,7,8,9,10].map(v => `<option value="${v}"${v===5?' selected':''}>${v}</option>`).join('')}
                                </select>
                                <span class="wizard-rating-range-to-label">Label (optional)</span>
                            </div>
                        </div>
                        <div class="wizard-rating-label-row">
                            <div class="wizard-rating-label-item">
                                <input type="text" class="wizard-rating-min-label" data-cat-id="${category.id}" maxlength="40" placeholder="Label (optional)">
                            </div>
                            <div class="wizard-rating-label-spacer"></div>
                            <div class="wizard-rating-label-item">
                                <input type="text" class="wizard-rating-max-label" data-cat-id="${category.id}" maxlength="40" placeholder="Label (optional)">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-primary survey-wizard-add-question-btn" style="margin-top:12px;" onclick="wizardAddQuestion('${category.id}')">
                        <i class="fas fa-plus"></i> Add question
                    </button>
                </div>
                <div class="survey-wizard-q-list">${questionsHtml}</div>
            </div>
        `;
        container.appendChild(card);

        const nameInput = card.querySelector('.survey-wizard-category-name-input');
        if (!isLockedCategory) {
            nameInput.addEventListener('change', () => {
                category.name = nameInput.value.trim() || 'Untitled Category';
                wizardPersistData();
                wizardMarkChanged();
                wizardRenderCategories();
                wizardShowToast('Category updated', 'success');
            });
        }
    });
}

function wizardToggleCategoryCollapse(catId) {
    if (surveyWizardCollapsedCategories.has(catId)) {
        surveyWizardCollapsedCategories.delete(catId);
    } else {
        surveyWizardCollapsedCategories.add(catId);
    }
    wizardRenderCategories();
}

function wizardScrollToCategory(catId) {
    surveyWizardCollapsedCategories.delete(catId);
    wizardRenderCategories();
    requestAnimationFrame(() => {
        const el = document.querySelector(`.survey-wizard-category-card[data-cat-id="${catId}"]`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.style.transition = 'box-shadow 0.3s';
            el.style.boxShadow = '0 0 0 4px rgba(23, 61, 115, 0.2)';
            setTimeout(() => { el.style.boxShadow = ''; }, 1200);
        }
    });
}

function wizardAddCategoryFromBuilder() {
    const input = document.getElementById('wizardNewCategoryName');
    const catName = input?.value.trim() || 'New Category';
    if (!surveyWizardData) surveyWizardData = wizardDefaultData();
    surveyWizardData.categories.forEach((category) => surveyWizardCollapsedCategories.add(category.id));
    const newCatId = `cat_${Date.now()}`;
    surveyWizardData.categories.push({
        id: newCatId,
        name: catName,
        questions: [],
    });
    if (input) input.value = '';
    wizardPersistData();
    wizardMarkChanged();
    wizardRenderCategories();
    wizardShowToast(`Category "${catName}" added to draft`, 'success');

    requestAnimationFrame(() => {
        const newEl = document.querySelector(`.survey-wizard-category-card[data-cat-id="${newCatId}"]`);
        if (newEl) {
            newEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            newEl.style.transition = 'box-shadow 0.3s';
            newEl.style.boxShadow = '0 0 0 4px rgba(23, 61, 115, 0.25)';
            setTimeout(() => { newEl.style.boxShadow = ''; }, 1400);
        }
    });
}

function wizardDeleteCategory(catId) {
    if (!surveyWizardData?.categories) return;
    const catIndex = surveyWizardData.categories.findIndex(c => c.id === catId);
    if (catIndex === -1) return;
    if (wizardIsPreMadeCategory(surveyWizardData.categories[catIndex])) {
        wizardShowToast('Pre-made categories cannot be deleted.', 'warning');
        return;
    }
    const catName = surveyWizardData.categories[catIndex].name || 'Category';
    surveyWizardData.categories.splice(catIndex, 1);
    surveyWizardCollapsedCategories.delete(catId);
    wizardPersistData();
    wizardMarkChanged();
    wizardRenderCategories();
    wizardShowToast(`Deleted "${catName}" from draft`, 'info');
}

function wizardAddCategory() {
    if (!surveyWizardData) surveyWizardData = wizardDefaultData();
    surveyWizardData.categories.forEach((category) => surveyWizardCollapsedCategories.add(category.id));
    const newCatId = `cat_${Date.now()}`;
    surveyWizardData.categories.push({
        id: newCatId,
        name: 'New Category',
        questions: [],
    });
    wizardPersistData();
    wizardMarkChanged();
    wizardRenderCategories();
    wizardShowToast('Category added to draft', 'success');

    requestAnimationFrame(() => {
        const newEl = document.querySelector(`.survey-wizard-category-card[data-cat-id="${newCatId}"]`);
        if (newEl) {
            newEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
}

function wizardToggleAnswerOptions(selectElement, catId) {
    const mcSection = document.querySelector(`.wizard-answer-options-section[data-cat-id="${catId}"]`);
    const ratingSection = document.querySelector(`.wizard-rating-range-section[data-cat-id="${catId}"]`);
    if (mcSection) mcSection.style.display = 'none';
    if (ratingSection) ratingSection.style.display = 'none';

    if (selectElement.value === 'multiple_choice') {
        if (mcSection) {
            mcSection.style.display = 'block';
            const list = mcSection.querySelector('.survey-wizard-options-list');
            if (list && list.innerHTML === '') {
                wizardAddAnswerOption(catId);
                wizardAddAnswerOption(catId);
            }
        }
    } else if (selectElement.value === 'rating') {
        if (ratingSection) ratingSection.style.display = 'block';
        wizardUpdateRatingRange(catId);
    }
}

function wizardUpdateRatingRange(catId) {
    const minSel = document.querySelector(`.wizard-rating-min-select[data-cat-id="${catId}"]`);
    const maxSel = document.querySelector(`.wizard-rating-max-select[data-cat-id="${catId}"]`);
    if (!minSel || !maxSel) return;
    const minVal = parseInt(minSel.value, 10);
    const maxVal = parseInt(maxSel.value, 10);
    // Ensure max is always > min
    if (maxVal <= minVal) {
        // Rebuild max options to only show values > minVal
        const currentMax = Math.max(minVal + 1, maxVal);
        maxSel.innerHTML = '';
        for (let v = minVal + 1; v <= 10; v++) {
            const opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            if (v === currentMax) opt.selected = true;
            maxSel.appendChild(opt);
        }
    }
}

function wizardAddAnswerOption(catId) {
    if (surveyWizardEditingQuestion && surveyWizardEditingQuestion.catId === catId) {
        const cat = surveyWizardData?.categories?.find(c => c.id === catId);
        const editQ = cat?.questions?.find(q => q.id === surveyWizardEditingQuestion.qId);
        if (editQ && wizardIsPreMadeQuestion(editQ)) {
            wizardShowToast('Options for pre-made questions cannot be modified.', 'warning');
            return;
        }
    }
    const list = document.querySelector(`.survey-wizard-options-list[data-cat-id="${catId}"]`);
    if (!list) return;
    const section = document.querySelector(`.wizard-answer-options-section[data-cat-id="${catId}"]`);
    const logicEnabled = section?.dataset.logicEnabled === 'true';
    const optionIndex = list.querySelectorAll('.survey-wizard-option-group').length + 1;
    const group = document.createElement('div');
    group.className = 'survey-wizard-option-group';
    const logicSelect = logicEnabled ? wizardBuildLogicSelectHtml(catId) : '';
    group.innerHTML = `
        <div class="survey-wizard-option-index">${optionIndex}</div>
        <input type="text" class="wizard-option-input option-input" maxlength="200" placeholder="Enter answer option" data-cat-id="${catId}">
        <button type="button" class="survey-wizard-icon-btn del" onclick="wizardRemoveAnswerOption(this, '${catId}')"><i class="fas fa-times"></i></button>
        ${logicSelect}
    `;
    list.appendChild(group);
}

function wizardRemoveAnswerOption(button, catId) {
    if (surveyWizardEditingQuestion && surveyWizardEditingQuestion.catId === catId) {
        const cat = surveyWizardData?.categories?.find(c => c.id === catId);
        const editQ = cat?.questions?.find(q => q.id === surveyWizardEditingQuestion.qId);
        if (editQ && wizardIsPreMadeQuestion(editQ)) {
            wizardShowToast('Options for pre-made questions cannot be removed.', 'warning');
            return;
        }
    }
    const group = button.closest('.survey-wizard-option-group');
    if (group) group.remove();
    const list = document.querySelector(`.survey-wizard-options-list[data-cat-id="${catId}"]`);
    if (!list) return;
    list.querySelectorAll('.survey-wizard-option-group').forEach((item, index) => {
        const num = index + 1;
        item.querySelector('.survey-wizard-option-index').textContent = String(num);
    });
}

function wizardToggleQuestionLogic(catId) {
    const section = document.querySelector(`.wizard-answer-options-section[data-cat-id="${catId}"]`);
    if (!section) return;
    const isEnabled = section.dataset.logicEnabled === 'true';
    const nextEnabled = !isEnabled;
    section.dataset.logicEnabled = String(nextEnabled);
    const btn = section.querySelector('.wizard-logic-toggle-btn');
    if (btn) btn.classList.toggle('active', nextEnabled);
    wizardUpdateLogicDropdowns(catId, nextEnabled);
}

function wizardBuildLogicSelectHtml(catId) {
    const categories = surveyWizardData?.categories || [];
    const sectionOptions = categories.map((cat, idx) => {
        const stableId = cat.id || `cat_${idx + 1}`;
        cat.id = stableId;
        return `<option value="go_to_section:${stableId}" data-cat-index="${idx}">${idx + 1}. ${escapeHtml(cat.name || 'Category ' + (idx + 1))}</option>`;
    }).join('');
    return `
        <select class="wizard-logic-select" data-cat-id="${catId}">
            <option value="next_section">Continue to next section</option>
            ${sectionOptions}
            <option value="submit">Submit form</option>
        </select>
    `;
}

function wizardUpdateLogicDropdowns(catId, show) {
    const list = document.querySelector(`.survey-wizard-options-list[data-cat-id="${catId}"]`);
    if (!list) return;
    list.querySelectorAll('.survey-wizard-option-group').forEach(group => {
        if (show) {
            if (!group.querySelector('.wizard-logic-select')) {
                group.insertAdjacentHTML('beforeend', wizardBuildLogicSelectHtml(catId));
            }
        } else {
            group.querySelector('.wizard-logic-select')?.remove();
        }
    });
}

function wizardCollectLogicRules(catId) {
    const list = document.querySelector(`.survey-wizard-options-list[data-cat-id="${catId}"]`);
    if (!list) return [];
    const categories = surveyWizardData?.categories || [];
    return Array.from(list.querySelectorAll('.survey-wizard-option-group')).map((group, index) => {
        const sel = group.querySelector('.wizard-logic-select');
        const value = sel?.value || 'next_section';
        if (value.startsWith('go_to_section:')) {
            const targetId = value.substring('go_to_section:'.length);
            const targetIndex = categories.findIndex(c => String(c.id) === String(targetId));
            return {
                optionIndex: index,
                action: 'go_to_section',
                targetCatId: targetId,
                targetCatIndex: targetIndex !== -1 ? targetIndex : null
            };
        }
        return { optionIndex: index, action: value, targetCatId: null, targetCatIndex: null };
    });
}

function wizardCollectLogicData(catId) {
    const mcSection = document.querySelector(`.wizard-answer-options-section[data-cat-id="${catId}"]`);
    if (mcSection?.dataset.logicEnabled === 'true') {
        return { enabled: true, rules: wizardCollectLogicRules(catId) };
    }
    return { enabled: false, rules: [] };
}

function wizardBuildNewQuestion(catId, questionInput, typeSelect) {
    const reqCheckbox = document.querySelector(`.wizard-required-checkbox[data-cat-id="${catId}"]`);
    const isRequired = reqCheckbox ? reqCheckbox.checked : true;
    const question = {
        id: `q_${Date.now()}`,
        text: questionInput.value.trim(),
        type: typeSelect.value,
        required: isRequired,
    };
    if (typeSelect.value === 'multiple_choice') {
        question.options = wizardCollectAnswerOptions(catId);
        question.logic = wizardCollectLogicData(catId);
    }
    if (typeSelect.value === 'rating') {
        const minSel = document.querySelector(`.wizard-rating-min-select[data-cat-id="${catId}"]`);
        const maxSel = document.querySelector(`.wizard-rating-max-select[data-cat-id="${catId}"]`);
        const minLabel = document.querySelector(`.wizard-rating-min-label[data-cat-id="${catId}"]`);
        const maxLabel = document.querySelector(`.wizard-rating-max-label[data-cat-id="${catId}"]`);
        question.ratingMin = minSel ? parseInt(minSel.value, 10) : 1;
        question.ratingMax = maxSel ? parseInt(maxSel.value, 10) : 5;
        question.ratingMinLabel = minLabel ? minLabel.value.trim() : '';
        question.ratingMaxLabel = maxLabel ? maxLabel.value.trim() : '';
    }
    return question;
}

function wizardAddQuestion(catId) {
    const category = surveyWizardData?.categories?.find((item) => item.id === catId);
    const questionInput = document.querySelector(`.wizard-question-input[data-cat-id="${catId}"]`);
    const typeSelect = document.querySelector(`.wizard-type-select[data-cat-id="${catId}"]`);
    if (!category || !questionInput || !typeSelect) return;

    questionInput.classList.remove('error');
    typeSelect.classList.remove('error');
    document.querySelectorAll(`.wizard-option-input[data-cat-id="${catId}"]`).forEach(input => input.classList.remove('error'));

    if (!questionInput.value.trim()) {
        questionInput.classList.add('error');
        questionInput.focus();
        questionInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const clearQ = () => {
            questionInput.classList.remove('error');
            questionInput.removeEventListener('input', clearQ);
        };
        questionInput.addEventListener('input', clearQ);
        wizardShowToast('Question text is required', 'error');
        return;
    }
    if (!typeSelect.value) {
        typeSelect.classList.add('error');
        typeSelect.focus();
        typeSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const clearType = () => {
            typeSelect.classList.remove('error');
            typeSelect.removeEventListener('change', clearType);
        };
        typeSelect.addEventListener('change', clearType);
        wizardShowToast('Please select a question type', 'error');
        return;
    }

    if (typeSelect.value === 'multiple_choice') {
        const optionInputs = Array.from(document.querySelectorAll(`.wizard-option-input[data-cat-id="${catId}"]`));
        if (optionInputs.length < 2) {
            wizardShowToast('Multiple choice questions need at least 2 options', 'error');
            return;
        }
        let firstEmpty = null;
        optionInputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('error');
                if (!firstEmpty) firstEmpty = input;
                const clearOpt = () => {
                    input.classList.remove('error');
                    input.removeEventListener('input', clearOpt);
                };
                input.addEventListener('input', clearOpt);
            }
        });
        if (firstEmpty) {
            firstEmpty.focus();
            firstEmpty.scrollIntoView({ behavior: 'smooth', block: 'center' });
            wizardShowToast('Answer option cannot be empty', 'error');
            return;
        }
    }

    const wasEditing = Boolean(surveyWizardEditingQuestion && surveyWizardEditingQuestion.catId === catId);
    const reqCheckbox = document.querySelector(`.wizard-required-checkbox[data-cat-id="${catId}"]`);
    const isRequired = reqCheckbox ? reqCheckbox.checked : true;

    if (wasEditing) {
        const existing = category.questions.find((q) => q.id === surveyWizardEditingQuestion.qId);
        if (existing) {
            const preMadeSpec = wizardGetPreMadeQuestionSpec(existing);
            if (preMadeSpec) {
                existing.isPreMade = true;
                existing.preMadeKey = preMadeSpec.preMadeKey;
                existing.text = preMadeSpec.text;
                existing.type = preMadeSpec.type;
                existing.required = true;
                existing.options = [...preMadeSpec.options];
                const mcSec = document.querySelector(`.wizard-answer-options-section[data-cat-id="${catId}"]`);
                if (mcSec?.dataset.logicEnabled === 'true') {
                    existing.logic = { enabled: true, rules: wizardCollectLogicRules(catId) };
                } else {
                    existing.logic = { enabled: false, rules: [] };
                }
            } else {
                existing.text = questionInput.value.trim();
                existing.type = typeSelect.value;
                existing.required = isRequired;
                if (typeSelect.value === 'multiple_choice') {
                    existing.options = wizardCollectAnswerOptions(catId);
                    const mcSec = document.querySelector(`.wizard-answer-options-section[data-cat-id="${catId}"]`);
                    if (mcSec?.dataset.logicEnabled === 'true') {
                        existing.logic = { enabled: true, rules: wizardCollectLogicRules(catId) };
                    } else {
                        existing.logic = { enabled: false, rules: [] };
                    }
                    delete existing.ratingMin; delete existing.ratingMax;
                    delete existing.ratingMinLabel; delete existing.ratingMaxLabel;
                } else if (typeSelect.value === 'rating') {
                    delete existing.options;
                    const minSel = document.querySelector(`.wizard-rating-min-select[data-cat-id="${catId}"]`);
                    const maxSel = document.querySelector(`.wizard-rating-max-select[data-cat-id="${catId}"]`);
                    const minLbl = document.querySelector(`.wizard-rating-min-label[data-cat-id="${catId}"]`);
                    const maxLbl = document.querySelector(`.wizard-rating-max-label[data-cat-id="${catId}"]`);
                    existing.ratingMin = minSel ? parseInt(minSel.value, 10) : 1;
                    existing.ratingMax = maxSel ? parseInt(maxSel.value, 10) : 5;
                    existing.ratingMinLabel = minLbl ? minLbl.value.trim() : '';
                    existing.ratingMaxLabel = maxLbl ? maxLbl.value.trim() : '';
                } else {
                    delete existing.options;
                    delete existing.ratingMin; delete existing.ratingMax;
                    delete existing.ratingMinLabel; delete existing.ratingMaxLabel;
                }
            }
        } else {
            category.questions.push(wizardBuildNewQuestion(catId, questionInput, typeSelect));
        }
    } else {
        category.questions.push(wizardBuildNewQuestion(catId, questionInput, typeSelect));
    }

    surveyWizardEditingQuestion = null;
    questionInput.readOnly = false;
    typeSelect.disabled = false;
    questionInput.value = '';
    typeSelect.value = '';
    if (reqCheckbox) {
        reqCheckbox.disabled = false;
        reqCheckbox.checked = true;
    }
    const section = document.querySelector(`.wizard-answer-options-section[data-cat-id="${catId}"]`);
    if (section) {
        section.style.display = 'none';
        section.dataset.logicEnabled = 'false';
        const logicBtn = section.querySelector('.wizard-logic-toggle-btn');
        if (logicBtn) logicBtn.classList.remove('active');
        const addOptBtn = section.querySelector('.survey-wizard-add-option-btn');
        if (addOptBtn) addOptBtn.style.display = '';
        section.querySelectorAll('.wizard-logic-select').forEach(s => s.remove());
    }
    const ratingSection = document.querySelector(`.wizard-rating-range-section[data-cat-id="${catId}"]`);
    if (ratingSection) ratingSection.style.display = 'none';

    wizardPersistData();
    wizardMarkChanged();
    wizardRenderCategories();
    wizardShowToast(wasEditing ? 'Question updated' : 'Question added', 'success');
}

function wizardDeleteQuestion(catId, qId) {
    const category = surveyWizardData?.categories?.find((item) => item.id === catId);
    if (!category) return;
    const targetQuestion = (category.questions || []).find((question) => question.id === qId);
    if (targetQuestion && wizardIsPreMadeQuestion(targetQuestion)) {
        wizardShowToast('Pre-made questions cannot be deleted.', 'warning');
        return;
    }
    category.questions = (category.questions || []).filter((question) => question.id !== qId);
    if (surveyWizardEditingQuestion && surveyWizardEditingQuestion.catId === catId && surveyWizardEditingQuestion.qId === qId) {
        surveyWizardEditingQuestion = null;
    }
    wizardPersistData();
    wizardMarkChanged();
    wizardRenderCategories();
    wizardShowToast('Question deleted', 'success');
}

function wizardEditQuestion(catId, qId) {
    const category = surveyWizardData?.categories?.find((item) => item.id === catId);
    const question = category?.questions?.find((item) => item.id === qId);
    if (!category || !question) return;

    surveyWizardCollapsedCategories.delete(catId);
    wizardRenderCategories();

    const questionInput = document.querySelector(`.wizard-question-input[data-cat-id="${catId}"]`);
    const typeSelect = document.querySelector(`.wizard-type-select[data-cat-id="${catId}"]`);
    if (!questionInput || !typeSelect) return;

    const isPreMadeQ = wizardIsPreMadeQuestion(question);
    questionInput.value = question.text;
    questionInput.readOnly = isPreMadeQ;
    typeSelect.value = question.type;
    typeSelect.disabled = isPreMadeQ;
    surveyWizardEditingQuestion = { catId, qId };

    if (question.type === 'multiple_choice') {
        wizardToggleAnswerOptions(typeSelect, catId);
        const list = document.querySelector(`.survey-wizard-options-list[data-cat-id="${catId}"]`);
        const mcSec = document.querySelector(`.wizard-answer-options-section[data-cat-id="${catId}"]`);
        const logicEnabled = question.logic?.enabled === true;
        if (mcSec) {
            mcSec.dataset.logicEnabled = String(logicEnabled);
            const logicBtn = mcSec.querySelector('.wizard-logic-toggle-btn');
            if (logicBtn) logicBtn.classList.toggle('active', logicEnabled);
            const addOptBtn = mcSec.querySelector('.survey-wizard-add-option-btn');
            if (addOptBtn) addOptBtn.style.display = isPreMadeQ ? 'none' : '';
        }
        if (list) {
            list.innerHTML = (question.options || []).map((option, index) => {
                const logicSel = logicEnabled ? wizardBuildLogicSelectHtml(catId) : '';
                const removeBtnHtml = isPreMadeQ
                    ? `<button type="button" class="survey-wizard-icon-btn is-locked" disabled title="Pre-made option cannot be removed"><i class="fas fa-lock"></i></button>`
                    : `<button type="button" class="survey-wizard-icon-btn del" onclick="wizardRemoveAnswerOption(this, '${catId}')"><i class="fas fa-times"></i></button>`;
                return `
                    <div class="survey-wizard-option-group">
                        <div class="survey-wizard-option-index">${index + 1}</div>
                        <input type="text" class="wizard-option-input option-input" maxlength="200" value="${escapeHtml(option)}" data-cat-id="${catId}" ${isPreMadeQ ? 'readonly' : ''}>
                        ${removeBtnHtml}
                        ${logicSel}
                    </div>
                `;
            }).join('');
            // Restore logic select values
            if (logicEnabled) {
                list.querySelectorAll('.survey-wizard-option-group').forEach((grp, idx) => {
                    const rule = question.logic.rules?.[idx];
                    const sel = grp.querySelector('.wizard-logic-select');
                    if (sel && rule) {
                        sel.value = rule.action === 'go_to_section' ? `go_to_section:${rule.targetCatId}` : rule.action;
                    }
                });
            }
        }
    } else if (question.type === 'rating') {
        wizardToggleAnswerOptions(typeSelect, catId);
        const minSel = document.querySelector(`.wizard-rating-min-select[data-cat-id="${catId}"]`);
        const maxSel = document.querySelector(`.wizard-rating-max-select[data-cat-id="${catId}"]`);
        const minLbl = document.querySelector(`.wizard-rating-min-label[data-cat-id="${catId}"]`);
        const maxLbl = document.querySelector(`.wizard-rating-max-label[data-cat-id="${catId}"]`);
        if (minSel) minSel.value = question.ratingMin ?? 1;
        if (maxSel) maxSel.value = question.ratingMax ?? 5;
        if (minLbl) minLbl.value = question.ratingMinLabel ?? '';
        if (maxLbl) maxLbl.value = question.ratingMaxLabel ?? '';
    }

    questionInput.focus();
    const reqCheckbox = document.querySelector(`.wizard-required-checkbox[data-cat-id="${catId}"]`);
    if (reqCheckbox) {
        reqCheckbox.checked = isPreMadeQ ? true : (question.required !== false && question.required !== 0 && question.required !== '0');
        reqCheckbox.disabled = isPreMadeQ;
    }
    const addBtn = document.querySelector(`.survey-wizard-category-card[data-cat-id="${catId}"] .survey-wizard-add-question-btn`);
    if (addBtn) {
        addBtn.innerHTML = '<i class="fas fa-check"></i> Save question';
    }
    wizardShowToast(
        isPreMadeQ
            ? 'Pre-made question is locked from deletion, but you can configure section routing logic.'
            : 'Viewing question details. You can continue without making changes.',
        'info',
        3500
    );
}

function wizardRenderPreview() {
    if (!surveyWizardData) return;
    const totalQuestions = getWizardQuestionCount();
    document.getElementById('wizardPreviewQuestionCount').textContent = String(totalQuestions);
    document.getElementById('wizardPreviewCategoryCount').textContent = String(surveyWizardData.categories.length);
    document.getElementById('wizardPreviewTitle').textContent = surveyWizardData.formName || 'Untitled Survey';
    document.getElementById('wizardPreviewDesc').textContent = surveyWizardData.formDetails || 'No description provided';

    const openDate = surveyWizardData.dateOpen ? new Date(`${surveyWizardData.dateOpen}T00:00:00`) : null;
    const closeDate = surveyWizardData.dateClose ? new Date(`${surveyWizardData.dateClose}T00:00:00`) : null;
    document.getElementById('wizardPreviewDateOpen').textContent = openDate
        ? openDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
        : '-';
    document.getElementById('wizardPreviewDateClose').textContent = closeDate
        ? closeDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
        : '-';

    const container = document.getElementById('wizardPreviewQuestions');
    if (!container) return;
    container.innerHTML = '';
    let questionCounter = 0;

    surveyWizardData.categories.forEach((category) => {
        if (!category.questions?.length) return;
        container.innerHTML += `<div class="survey-wizard-preview-category"><i class="fas fa-folder"></i> ${escapeHtml(category.name)}</div>`;
        category.questions.forEach((question) => {
            questionCounter += 1;
            let optionsHtml = '';
            if (question.type === 'multiple_choice' && question.options?.length) {
                optionsHtml = `
                    <div style="margin-top:8px;padding:10px;background:#f8fafc;border-radius:8px;">
                        <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px;">Answer options:</div>
                        ${question.options.map((option) => `<div style="font-size:13px;padding:4px 0 4px 16px;">○ ${escapeHtml(option)}</div>`).join('')}
                    </div>
                `;
            } else if (question.type === 'image') {
                optionsHtml = `
                    <div class="wizard-preview-image-box">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <div>
                            <strong style="display:block;color:#334155;margin-bottom:2px;font-size:13px;">Image Attachment / Upload</strong>
                            <span style="font-size:12px;color:#64748b;">Respondents will be asked to upload an image file (JPG, PNG, WEBP, GIF).</span>
                        </div>
                    </div>
                `;
            }
            let typeLabel = WIZARD_TYPE_LABELS[question.type] || question.type;
            let typeDesc = WIZARD_TYPE_DESCRIPTIONS[question.type] || '';
            if (question.type === 'rating') {
                const mn = question.ratingMin ?? 1;
                const mx = question.ratingMax ?? 5;
                typeLabel = `Rating scale (${mn}–${mx})`;
                typeDesc = `Respondents rate on a scale of ${mn} to ${mx}`;
            }
            const isReq = question.required !== false && question.required !== 0 && question.required !== '0';
            const reqMarker = isReq
                ? ' <span style="color:#e11d48;font-weight:700;" title="Required">*</span>'
                : ' <span style="font-size:12px;color:#94a3b8;font-weight:normal;">(Optional)</span>';
            container.innerHTML += `
                <div class="survey-wizard-preview-question">
                    <div class="survey-wizard-preview-question-text">${questionCounter}. ${escapeHtml(question.text)}${reqMarker}</div>
                    <span class="survey-wizard-preview-question-type">${typeLabel}</span>
                    <div style="margin-top:8px;font-size:13px;color:#94a3b8;"><i class="fas fa-info-circle"></i> ${typeDesc}</div>
                    ${optionsHtml}
                </div>
            `;
        });
    });
}

function wizardInitPublishStep() {
    const totalQuestions = getWizardQuestionCount();
    const countEl = document.getElementById('wizardPublishQuestionCount');
    if (countEl) countEl.textContent = `${totalQuestions} question${totalQuestions === 1 ? '' : 's'} ready`;
    const today = new Date().toISOString().split('T')[0];
    const scheduleDate = document.getElementById('wizardScheduleDate');
    if (scheduleDate) scheduleDate.min = today;
}

function wizardSelectPublishMethod(method, cardElement) {
    surveyWizardPublishMethod = method;
    document.querySelectorAll('.survey-wizard-option-card').forEach((card) => card.classList.remove('selected'));
    cardElement.classList.add('selected');
    const scheduleInput = document.getElementById('wizardScheduleInput');
    if (scheduleInput) {
        scheduleInput.classList.toggle('show', method === 'scheduled');
    }
}

function wizardValidateScheduleInput() {
    if (surveyWizardPublishMethod !== 'scheduled') return true;
    const date = document.getElementById('wizardScheduleDate')?.value || '';
    const time = document.getElementById('wizardScheduleTime')?.value || '';
    if (!date) {
        wizardShowToast('Please select a publish date', 'error');
        return false;
    }
    if (!time) {
        wizardShowToast('Please select a publish time', 'error');
        return false;
    }
    const scheduleDateTime = new Date(`${date}T${time}`);
    if (scheduleDateTime <= new Date()) {
        wizardShowToast('Scheduled time must be in the future', 'error');
        return false;
    }
    return true;
}

function buildSurveyCategoriesPayload() {
    const categories = surveyWizardData?.categories || [];
    return categories.map((category, catIndex) => {
        const stableCatId = category.id || `cat_${catIndex + 1}`;
        category.id = stableCatId;
        return {
            id: stableCatId,
            name: category.name || `Category ${catIndex + 1}`,
            description: category.description || '',
            order_number: catIndex + 1,
            questions: (Array.isArray(category.questions) ? category.questions : []).map((question, qIdx) => {
                const stableQId = question.id || `q_${catIndex + 1}_${qIdx + 1}`;
                question.id = stableQId;
                return {
                    id: stableQId,
                    text: String(question.text || '').trim(),
                    type: question.type,
                    required: question.required !== false && question.required !== 0 && question.required !== '0',
                    order_index: qIdx + 1,
                    ratingMin: question.type === 'rating' ? (question.ratingMin ?? 1) : null,
                    ratingMax: question.type === 'rating' ? (question.ratingMax ?? 5) : null,
                    ratingMinLabel: question.type === 'rating' ? (question.ratingMinLabel || null) : null,
                    ratingMaxLabel: question.type === 'rating' ? (question.ratingMaxLabel || null) : null,
                    options: question.type === 'multiple_choice' ? (question.options || []) : [],
                    logic: question.type === 'multiple_choice' && question.logic?.enabled ? question.logic : null,
                };
            }),
        };
    });
}

async function wizardSaveSurveyToBackend(options = {}) {
    const { publishMethod = null, scheduledFor = null } = options;
    wizardSyncStep1ToData();

    let openDatetime = wizardDateToOpenDatetime(surveyWizardData.dateOpen);
    let closeDatetime = wizardDateToCloseDatetime(surveyWizardData.dateClose);

    if (surveyWizardData.surveyId) {
        if (surveyWizardData.dateOpen && surveyWizardData.dateOpen === surveyWizardData.originalDateOpen && surveyWizardData.originalOpenDateTime) {
            openDatetime = surveyWizardData.originalOpenDateTime;
        }
        if (surveyWizardData.dateClose && surveyWizardData.dateClose === surveyWizardData.originalDateClose && surveyWizardData.originalCloseDateTime) {
            closeDatetime = surveyWizardData.originalCloseDateTime;
        }
    }

    if (publishMethod === 'scheduled' && scheduledFor) {
        const scheduled = new Date(scheduledFor);
        if (!Number.isNaN(scheduled.getTime())) {
            const pad = (value) => String(value).padStart(2, '0');
            openDatetime = `${scheduled.getFullYear()}-${pad(scheduled.getMonth() + 1)}-${pad(scheduled.getDate())} ${pad(scheduled.getHours())}:${pad(scheduled.getMinutes())}:00`;
        }
    }

    const payload = {
        title: String(surveyWizardData.formName || '').trim(),
        description: String(surveyWizardData.formDetails || '').trim(),
        open_datetime: openDatetime,
        close_datetime: closeDatetime,
    };

    if (getWizardQuestionCount() > 0) {
        payload.categories = buildSurveyCategoriesPayload();
    }

    if (publishMethod === 'scheduled' && scheduledFor) {
        payload.scheduled_for = scheduledFor;
    }

    const isEdit = Boolean(surveyWizardData.surveyId);
    const targetUrl = isEdit
        ? getSurveyUpdateUrl(surveyWizardData.surveyId)
        : getCreateSurveyUrl();

    const response = await fetch(targetUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    const result = await response.json();
    if (!response.ok || !result.success) {
        const errors = Array.isArray(result.errors) ? result.errors : [];
        const message = errors.length > 0
            ? errors.join('\n')
            : (result.message || 'Unable to save survey right now.');
        throw new Error(message);
    }

    if (result.data?.id) {
        surveyWizardData.surveyId = Number(result.data.id);
        wizardPersistData();
    }

    return result;
}

async function wizardPublishSurveyState(surveyId, publishMethod, scheduledFor = null) {
    const body = { survey_state: 'posted' };
    if (publishMethod === 'scheduled' && scheduledFor) {
        body.publish_method = 'scheduled';
        body.scheduled_for = scheduledFor;
    } else {
        body.publish_method = 'immediate';
    }

    const response = await fetch(getSurveyUpdateStateUrl(surveyId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
        const errors = Array.isArray(result.errors) ? result.errors : [];
        throw new Error(errors.length > 0 ? errors.join('\n') : (result.message || 'Unable to publish survey.'));
    }
    return result;
}

async function wizardSaveDraft() {
    wizardClearStep1Errors();
    wizardSyncStep1ToData();
    const validation = wizardValidateDetails(surveyWizardData);
    if (!validation.valid) {
        wizardShowErrorList('wizardStep1ErrorList', 'wizardStep1ErrorItems', validation.errors);
        return;
    }

    wizardPersistData();
    wizardClearChanged();

    const canPersistToServer = Boolean(surveyWizardData.surveyId) || getWizardQuestionCount() > 0;
    if (!canPersistToServer) {
        wizardShowToast('Draft saved!', 'success');
        return;
    }

    try {
        await wizardSaveSurveyToBackend();
        wizardShowToast('Draft saved!', 'success');
    } catch (error) {
        wizardShowToast(error.message || 'Unable to save draft.', 'error', 5000);
    }
}

function wizardStep1Next() {
    wizardClearStep1Errors();
    wizardSyncStep1ToData();
    const validation = wizardValidateDetails(surveyWizardData);
    if (!validation.valid) {
        wizardShowErrorList('wizardStep1ErrorList', 'wizardStep1ErrorItems', validation.errors);
        wizardShowToast(`${validation.errors.length} error(s) found. Please review.`, 'error');
        return;
    }
    wizardPopulateStep1Fields();
    wizardGoToStep(2);
}

function wizardStep2Next() {
    wizardSyncStep1ToData();
    if (surveyWizardEditingQuestion) {
        const { catId, qId } = surveyWizardEditingQuestion;
        const category = surveyWizardData?.categories?.find((item) => item.id === catId);
        const existing = category?.questions?.find((q) => q.id === qId);
        const questionInput = document.querySelector(`.wizard-question-input[data-cat-id="${catId}"]`);
        const typeSelect = document.querySelector(`.wizard-type-select[data-cat-id="${catId}"]`);
        if (existing && questionInput && typeSelect && questionInput.value.trim() && typeSelect.value) {
            const preMadeSpec = wizardGetPreMadeQuestionSpec(existing);
            if (preMadeSpec) {
                existing.isPreMade = true;
                existing.preMadeKey = preMadeSpec.preMadeKey;
                existing.text = preMadeSpec.text;
                existing.type = preMadeSpec.type;
                existing.required = true;
                existing.options = [...preMadeSpec.options];
                const mcSec = document.querySelector(`.wizard-answer-options-section[data-cat-id="${catId}"]`);
                if (mcSec?.dataset.logicEnabled === 'true') {
                    existing.logic = { enabled: true, rules: wizardCollectLogicRules(catId) };
                } else {
                    existing.logic = { enabled: false, rules: [] };
                }
            } else {
                const reqCheckbox = document.querySelector(`.wizard-required-checkbox[data-cat-id="${catId}"]`);
                existing.text = questionInput.value.trim();
                existing.type = typeSelect.value;
                existing.required = reqCheckbox ? reqCheckbox.checked : true;
                if (typeSelect.value === 'multiple_choice') {
                    const opts = wizardCollectAnswerOptions(catId);
                    if (opts.length >= 2) {
                        existing.options = opts;
                    }
                    const mcSec = document.querySelector(`.wizard-answer-options-section[data-cat-id="${catId}"]`);
                    if (mcSec?.dataset.logicEnabled === 'true') {
                        existing.logic = { enabled: true, rules: wizardCollectLogicRules(catId) };
                    } else {
                        existing.logic = { enabled: false, rules: [] };
                    }
                } else if (typeSelect.value === 'rating') {
                    const minSel = document.querySelector(`.wizard-rating-min-select[data-cat-id="${catId}"]`);
                    const maxSel = document.querySelector(`.wizard-rating-max-select[data-cat-id="${catId}"]`);
                    const minLbl = document.querySelector(`.wizard-rating-min-label[data-cat-id="${catId}"]`);
                    const maxLbl = document.querySelector(`.wizard-rating-max-label[data-cat-id="${catId}"]`);
                    existing.ratingMin = minSel ? parseInt(minSel.value, 10) : (existing.ratingMin ?? 1);
                    existing.ratingMax = maxSel ? parseInt(maxSel.value, 10) : (existing.ratingMax ?? 5);
                    existing.ratingMinLabel = minLbl ? minLbl.value.trim() : (existing.ratingMinLabel ?? '');
                    existing.ratingMaxLabel = maxLbl ? maxLbl.value.trim() : (existing.ratingMaxLabel ?? '');
                }
            }
            wizardPersistData();
        }
        surveyWizardEditingQuestion = null;
    }
    const validation = wizardValidateQuestions(surveyWizardData.categories || []);
    if (!validation.valid) {
        wizardShowToast(`Fix ${validation.errors.length} error(s) before continuing`, 'error', 5000);
        return;
    }
    wizardGoToStep(3);
}

function wizardStep3Next() {
    wizardSyncStep1ToData();
    const validation = wizardValidateForPublish(surveyWizardData);
    const btn = document.getElementById('wizardPreviewNextBtn');
    if (!validation.valid) {
        wizardShowToast(`Survey is incomplete. Fix ${validation.errors.length} issue(s) first.`, 'error', 5000);
        return;
    }
    if (btn) btn.disabled = false;
    wizardGoToStep(4);
}

async function wizardPublish() {
    if (surveyWizardPublishing) return;
    if (!wizardValidateScheduleInput()) return;

    wizardSyncStep1ToData();
    const validation = wizardValidateForPublish(surveyWizardData);
    if (!validation.valid) {
        wizardShowToast(`Survey is incomplete. Fix ${validation.errors.length} issue(s) first.`, 'error', 5000);
        return;
    }

    const modal = document.getElementById('confirmPublishSurveyModal');
    if (modal) {
        const nameEl = document.getElementById('confirmPublishSurveyName');
        const qCountEl = document.getElementById('confirmPublishQuestionCount');
        const cCountEl = document.getElementById('confirmPublishCategoryCount');
        const methodEl = document.getElementById('confirmPublishMethodText');
        const scheduleEl = document.getElementById('confirmPublishScheduleText');

        const totalQ = getWizardQuestionCount();
        const totalC = Array.isArray(surveyWizardData.categories) ? surveyWizardData.categories.length : 0;

        if (nameEl) nameEl.textContent = surveyWizardData.formName || 'Untitled Survey';
        if (qCountEl) qCountEl.textContent = `${totalQ} question${totalQ === 1 ? '' : 's'}`;
        if (cCountEl) cCountEl.textContent = `${totalC} categor${totalC === 1 ? 'y' : 'ies'}`;

        if (surveyWizardPublishMethod === 'scheduled') {
            const date = document.getElementById('wizardScheduleDate')?.value || '';
            const time = document.getElementById('wizardScheduleTime')?.value || '';
            if (methodEl) methodEl.textContent = 'Scheduled';
            if (scheduleEl) scheduleEl.textContent = date && time ? `${date} at ${time}` : 'Scheduled date/time';
        } else {
            if (methodEl) methodEl.textContent = 'Immediate (Live now)';
            const openD = surveyWizardData.dateOpen || '-';
            const closeD = surveyWizardData.dateClose || '-';
            if (scheduleEl) scheduleEl.textContent = `${openD} to ${closeD}`;
        }

        modal.style.display = 'flex';
        return;
    }

    // Fallback if modal is not in DOM
    const surveyTitle = surveyWizardData.formName || 'this survey';
    const isScheduled = surveyWizardPublishMethod === 'scheduled';
    const msg = isScheduled
        ? `Are you sure you want to schedule publication for "${surveyTitle}"?`
        : `Are you sure you want to publish "${surveyTitle}" immediately? It will become active for alumni respondents.`;

    const ac = await ensureStaffActionConfirm();
    const confirmed = ac
        ? await ac.medium({
            title: isScheduled ? 'Schedule Survey Publication?' : 'Publish Survey Immediately?',
            message: msg,
            record: surveyTitle,
            consequence: isScheduled
                ? 'The survey will automatically open to alumni at the scheduled date and time.'
                : 'Alumni in your campus will immediately be able to view and submit responses.',
            confirmText: isScheduled ? 'Yes, Schedule' : 'Yes, Publish Now',
            icon: 'fa-rocket'
        })
        : false;
    if (!confirmed) {
        return;
    }

    await wizardExecutePublish();
}

function closeConfirmPublishModal() {
    const modal = document.getElementById('confirmPublishSurveyModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

async function wizardExecutePublish() {
    closeConfirmPublishModal();
    if (surveyWizardPublishing) return;
    if (!wizardValidateScheduleInput()) return;

    wizardSyncStep1ToData();
    const validation = wizardValidateForPublish(surveyWizardData);
    if (!validation.valid) {
        wizardShowToast(`Survey is incomplete. Fix ${validation.errors.length} issue(s) first.`, 'error', 5000);
        return;
    }

    let scheduledFor = null;
    if (surveyWizardPublishMethod === 'scheduled') {
        const date = document.getElementById('wizardScheduleDate').value;
        const time = document.getElementById('wizardScheduleTime').value;
        scheduledFor = `${date}T${time}`;
    }

    surveyWizardPublishing = true;
    const publishBtn = document.getElementById('wizardPublishBtn');
    const modalBtn = document.getElementById('modalConfirmPublishBtn');
    const originalHtml = publishBtn ? publishBtn.innerHTML : '';
    if (publishBtn) {
        publishBtn.disabled = true;
        publishBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';
    }
    if (modalBtn) {
        modalBtn.disabled = true;
        modalBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';
    }

    try {
        const saveResult = await wizardSaveSurveyToBackend({
            publishMethod: surveyWizardPublishMethod,
            scheduledFor,
        });
        const surveyId = saveResult.data?.id || surveyWizardData.surveyId;
        await wizardPublishSurveyState(surveyId, surveyWizardPublishMethod, scheduledFor);

        surveyWizardPublishedSurveyId = surveyId;
        wizardStorageRemove(SURVEY_WIZARD_STORAGE_KEY);
        wizardClearChanged();

        const successName = document.getElementById('wizardSuccessSurveyName');
        if (successName) successName.textContent = surveyWizardData.formName || 'Your survey';

        await loadSurveys();
        void logRecentActivity(`Survey published: ${surveyWizardData.formName}`, 'survey', { surveyId, title: surveyWizardData.formName });
        wizardGoToStep('success');
    } catch (error) {
        wizardShowToast(error.message || 'Unable to publish survey. Please try again.', 'error', 5000);
        if (publishBtn) {
            publishBtn.disabled = false;
            publishBtn.innerHTML = originalHtml;
        }
        if (modalBtn) {
            modalBtn.disabled = false;
            modalBtn.innerHTML = '<i class="fas fa-check-circle"></i> Yes, Publish Survey';
        }
    } finally {
        surveyWizardPublishing = false;
    }
}

function wizardViewResponses() {
    if (surveyWizardPublishedSurveyId) {
        window.location.href = getSurveyResponsesPageUrl(surveyWizardPublishedSurveyId);
        return;
    }
    wizardShowToast('Responses will be available once the survey is saved.', 'info');
}

function wizardCreateAnother() {
    wizardStorageRemove(SURVEY_WIZARD_STORAGE_KEY);
    surveyWizardData = wizardDefaultData();
    surveyWizardEditingQuestion = null;
    surveyWizardCollapsedCategories = new Set();
    surveyWizardPublishMethod = 'immediate';
    surveyWizardPublishedSurveyId = null;
    surveyWizardHasChanges = false;
    wizardPopulateStep1Fields();
    wizardGoToStep(1);
}

function wizardPopulateStep1Fields() {
    if (!surveyWizardData) return;
    const formName = document.getElementById('wizardFormName');
    const formDetails = document.getElementById('wizardFormDetails');
    const dateOpen = document.getElementById('wizardDateOpen');
    const dateClose = document.getElementById('wizardDateClose');
    if (formName) formName.value = surveyWizardData.formName || '';
    if (formDetails) formDetails.value = surveyWizardData.formDetails || '';
    if (dateOpen) dateOpen.value = surveyWizardData.dateOpen || '';
    if (dateClose) dateClose.value = surveyWizardData.dateClose || '';
}

function wizardLoadDataFromSurvey(survey) {
    const groups = getSurveyCategoryGroups(survey);
    const rawOpen = survey.openDateTime || survey.open_datetime || survey.createdAt || survey.created_at || '';
    const rawClose = survey.closeDateTime || survey.close_datetime || rawOpen || '';
    const resolvedDateOpen = wizardDatetimeToDate(rawOpen);
    const resolvedDateClose = wizardDatetimeToDate(rawClose);

    surveyWizardData = {
        surveyId: Number(survey.id),
        formName: survey.title || '',
        formDetails: survey.description || '',
        dateOpen: resolvedDateOpen,
        dateClose: resolvedDateClose,
        originalDateOpen: resolvedDateOpen,
        originalDateClose: resolvedDateClose,
        originalOpenDateTime: survey.openDateTime || survey.open_datetime || null,
        originalCloseDateTime: survey.closeDateTime || survey.close_datetime || null,
        surveyState: survey.surveyState || survey.survey_state || 'not posted',
        status: survey.status || 'open',
        categories: groups.length > 0
            ? groups.map((category) => ({
                id: category.id || `cat_${Date.now()}_${Math.random()}`,
                name: category.name || 'General',
                questions: (category.questions || []).map((question) => ({
                    id: question.id || `q_${Date.now()}_${Math.random()}`,
                    text: question.text || '',
                    type: question.type || 'text',
                    required: question.required !== false && question.required !== 0 && question.required !== '0',
                    options: Array.isArray(question.options)
                        ? [...question.options]
                        : (Array.isArray(question.choices) ? question.choices.map((c) => (typeof c === 'object' ? (c.text || '') : c)) : []),
                    ratingMin: question.ratingMin ?? 1,
                    ratingMax: question.ratingMax ?? 5,
                    ratingMinLabel: question.ratingMinLabel || '',
                    ratingMaxLabel: question.ratingMaxLabel || '',
                    logic: question.logic ? JSON.parse(JSON.stringify(question.logic)) : null,
                })),
            }))
            : wizardDefaultData().categories,
    };
    wizardEnsurePreMadeStructure(surveyWizardData);
    surveyWizardHasChanges = false;
}

let surveyEditorInitializedForKey = null;

function initializeSurveyEditorPage(forceResetStep = false) {
    const wizardEl = document.getElementById('surveyWizard');
    if (!wizardEl) return;

    const editingSurveyId = getSurveyEditorSurveyId();
    const surveyBeingEdited = getSurveyBeingEdited();

    // If URL has ?surveyId=... but surveysData hasn't finished loading yet, wait for loadSurveys()
    if (editingSurveyId && !surveyBeingEdited && (!Array.isArray(surveysData) || surveysData.length === 0)) {
        return;
    }

    const currentKey = surveyBeingEdited ? `edit_${surveyBeingEdited.id}` : 'create';
    if (!forceResetStep && surveyEditorInitializedForKey === currentKey && surveyWizardPublishing) {
        return;
    }

    if (!wizardEl.dataset.changeTrackerInit) {
        wizardEl.dataset.changeTrackerInit = 'true';
        wizardInitChangeTracker();
    }

    const pageTitle = document.getElementById('surveyEditorPageTitle');

    if (surveyBeingEdited) {
        if (surveyEditorInitializedForKey !== currentKey || forceResetStep) {
            wizardLoadDataFromSurvey(surveyBeingEdited);
        }
        if (pageTitle) pageTitle.textContent = 'Edit Survey';
    } else if (surveyEditorInitializedForKey !== currentKey || forceResetStep) {
        const stored = wizardStorageGet(SURVEY_WIZARD_STORAGE_KEY);
        surveyWizardData = stored && typeof stored === 'object' && !stored.surveyId ? stored : wizardDefaultData();
        if (!Array.isArray(surveyWizardData.categories) || surveyWizardData.categories.length === 0) {
            surveyWizardData.categories = wizardDefaultData().categories;
        }
        wizardEnsurePreMadeStructure(surveyWizardData);
        wizardPersistData();
        if (pageTitle) pageTitle.textContent = 'Create New Survey';
    }

    const isFirstInitForKey = surveyEditorInitializedForKey !== currentKey;
    surveyEditorInitializedForKey = currentKey;

    if (isFirstInitForKey || forceResetStep) {
        wizardPopulateStep1Fields();
    }

    const today = new Date().toISOString().split('T')[0];
    const dateOpen = document.getElementById('wizardDateOpen');
    const dateClose = document.getElementById('wizardDateClose');
    if (dateOpen) {
        if (surveyBeingEdited) {
            dateOpen.removeAttribute('min');
        } else {
            dateOpen.min = today;
        }
        if (!dateOpen.dataset.boundEditor) {
            dateOpen.dataset.boundEditor = 'true';
            dateOpen.addEventListener('change', function onOpenDateChange() {
                if (dateClose) {
                    if (getSurveyBeingEdited()) {
                        dateClose.min = this.value || '';
                    } else {
                        dateClose.min = this.value || today;
                    }
                }
                wizardMarkChanged();
            });
        }
    }
    if (dateClose) {
        if (surveyBeingEdited) {
            dateClose.min = surveyWizardData?.dateOpen || '';
        } else {
            dateClose.min = today;
        }
    }

    ['wizardFormName', 'wizardFormDetails', 'wizardDateOpen', 'wizardDateClose'].forEach((id) => {
        const el = document.getElementById(id);
        if (el && !el.dataset.boundMarkChanged) {
            el.dataset.boundMarkChanged = 'true';
            el.addEventListener('input', wizardMarkChanged);
        }
    });

    if (isFirstInitForKey || forceResetStep) {
        wizardGoToStep(1);
    }
}
async function addSurveyQuestion() {
    const survey = getSelectedSurvey();
    if (!survey) {
        alert('Please create/select a survey first.');
        return;
    }

    const textInput = document.getElementById('newQuestionText');
    const typeInput = document.getElementById('newQuestionType');
    const optionsInput = document.getElementById('newQuestionOptions');
    if (!textInput || !typeInput || !optionsInput) return;

    const text = textInput.value.trim();
    const type = typeInput.value;
    const options = optionsInput.value
        .split(',')
        .map(option => option.trim())
        .filter(Boolean);

    if (!text) {
        alert('Please add question text.');
        return;
    }

    if (type === 'multiple_choice' && options.length < 2) {
        alert('Multiple choice questions need at least 2 options.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('text', text);
        formData.append('type', type);
        formData.append('options', JSON.stringify(type === 'multiple_choice' ? options : []));

        const response = await fetch(getAddSurveyQuestionUrl(survey.id), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to add question right now.');
            return;
        }

        textInput.value = '';
        optionsInput.value = '';

        await loadSurveys();
        const selector = document.getElementById('surveySelector');
        if (selector) selector.value = String(survey.id);
        await onSurveySelectionChange();
    } catch (error) {
        alert('Unable to add question right now.');
    }
}

async function deleteSurveyQuestion(questionId) {
    const survey = getSelectedSurvey();
    if (!survey) return;
    const targetQ = (survey.questions || []).find(q => Number(q.id) === Number(questionId));
    if (targetQ && wizardIsPreMadeQuestion(targetQ)) {
        wizardShowToast('Pre-made questions cannot be deleted.', 'warning');
        return;
    }

    const ac = await ensureStaffActionConfirm();
    const confirmed = ac
        ? await ac.high({
            title: 'Permanently Delete Survey Question?',
            message: 'You are about to permanently remove this question and any submitted answers linked to it.',
            record: targetQ?.text || `Question #${questionId}`,
            consequence: 'This action cannot be undone.',
            requiredPhrase: 'DELETE',
            confirmText: 'Delete Question'
        })
        : false;
    if (!confirmed) return;

    surveyResponses = surveyResponses.map(response => ({
        ...response,
        answers: response.answers.filter(answer => answer.questionId !== questionId)
    }));

    try {
        const response = await fetch(getDeleteSurveyQuestionUrl(questionId), {
            method: 'DELETE',
            credentials: 'same-origin'
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to delete question right now.');
            return;
        }
    } catch (error) {
        alert('Unable to delete question right now.');
        return;
    }

    saveState();
    await loadSurveys();
    const selector = document.getElementById('surveySelector');
    if (selector) selector.value = String(survey.id);
    await onSurveySelectionChange();
}

function sendSurveyInvite() {
    const survey = getSelectedSurvey();
    const messageInput = document.getElementById('surveyInviteMessage');
    if (!survey || !messageInput) {
        alert('Please select a survey first.');
        return;
    }

    if (survey.questions.length === 0) {
        alert('Add at least one question before sending.');
        return;
    }

    const recipientEmails = alumniData
        .filter(alumni => alumni.status === 'active' && alumni.email)
        .map(alumni => alumni.email);

    if (recipientEmails.length === 0) {
        alert('No active alumni email addresses found.');
        return;
    }

    const customMessage = messageInput.value.trim();
    const questionList = survey.questions.map((question, index) => `${index + 1}. ${question.text}`).join('\n');
    const emailBody = encodeURIComponent(
        `${customMessage || 'Hello Alumni,\n\nPlease answer our survey below.'}\n\nSurvey: ${survey.title}\n\nQuestions:\n${questionList}\n\nReply to this email with your answers.`
    );
    const subject = encodeURIComponent(`Alumni Survey: ${survey.title}`);
    const bcc = encodeURIComponent(recipientEmails.join(','));

    window.location.href = `mailto:?subject=${subject}&bcc=${bcc}&body=${emailBody}`;

    saveState();
    void logRecentActivity(`Survey invite sent: ${survey.title}`, 'survey', { name: survey.title });
}

async function submitSurveyResponse() {
    const survey = getSelectedSurvey();
    const alumniSelector = document.getElementById('responseAlumniId');
    if (!survey || !alumniSelector) {
        alert('Please select a survey.');
        return;
    }

    const alumniId = Number(alumniSelector.value);
    const alumni = alumniData.find(item => item.id === alumniId);
    if (!alumni) {
        alert('Please select alumni for this response.');
        return;
    }

    if (survey.questions.length === 0) {
        alert('Survey has no questions.');
        return;
    }

    const answers = [];
    const formData = new FormData();
    for (const question of survey.questions) {
        const input = document.getElementById(`response_q_${question.id}`);
        const questionType = String(question.type || 'text');
        if (questionType === 'image') {
            const file = input && input.files ? input.files[0] : null;
            if (!file) {
                alert('Please answer all questions before saving.');
                return;
            }

            answers.push({
                question_id: question.id,
                answer_text: ''
            });
            formData.append(`answer_image_${question.id}`, file);
            continue;
        }

        const value = input ? String(input.value).trim() : '';
        if (!value) {
            alert('Please answer all questions before saving.');
            return;
        }
        if (question.type === 'multiple_choice') {
            answers.push({
                question_id: question.id,
                choice_id: Number(value)
            });
        } else {
            answers.push({
                question_id: question.id,
                answer_text: value
            });
        }
    }

    formData.append('user_email', alumni.email);
    formData.append('answers', JSON.stringify(answers));

    try {
        const response = await fetch(getSurveyResponsesUrl(survey.id), {
            method: 'POST',
            credentials: 'include',
            body: formData
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to save survey response right now.');
            return;
        }
    } catch (error) {
        alert('Unable to save survey response right now.');
        return;
    }

    await onSurveySelectionChange();
    const alumniName = getAlumniDisplayName(alumni);
    void logRecentActivity(`Survey response recorded for ${alumniName}`, 'survey', { name: alumniName, survey: survey.title });
}

function viewAllActivities() {
    if (!Array.isArray(activities) || activities.length === 0) {
        alert('No recent activities yet.');
        return;
    }

    alert('All activities:\n' + activities.map(a => `${a.action} (${formatActivityReference(a)})`).join('\n'));
}

// Modal Controls
function openAddBatchModal() {
    const modal = document.getElementById('addBatchModal');
    if (!modal) return;
    modal.style.display = 'flex';
}

function closeAddBatchModal() {
    const modal = document.getElementById('addBatchModal');
    if (!modal) return;
    modal.style.display = 'none';
}

function openAddAlumniModal() {
    if (!selectedAlumniBatchYear) {
        alert('Please open a batch first.');
        return;
    }
    if (!selectedAlumniCollege || !selectedAlumniCourse) {
        alert('Please open a college and course before adding alumni.');
        return;
    }
    const modal = document.getElementById('addAlumniModal');
    const batchYearInput = document.getElementById('alumniGraduationYear');
    const collegeInput = document.getElementById('alumniCollege');
    const courseInput = document.getElementById('alumniCourse');
    if (!modal) return;
    if (batchYearInput) batchYearInput.value = String(selectedAlumniBatchYear);
    if (collegeInput) collegeInput.value = String(selectedAlumniCollege);
    if (courseInput) courseInput.value = String(selectedAlumniCourse);
    modal.style.display = 'flex';
}

function closeAddAlumniModal() {
    const modal = document.getElementById('addAlumniModal');
    if (!modal) return;
    modal.style.display = 'none';
}

function openImportAlumniModal() {
    if (!selectedAlumniBatchYear) {
        alert('Please open a batch first.');
        return;
    }

    const modal = document.getElementById('importAlumniModal');
    const batchYearInput = document.getElementById('importBatchYear');
    if (!modal) return;
    if (batchYearInput) batchYearInput.value = String(selectedAlumniBatchYear);
    modal.style.display = 'flex';
}

function closeImportAlumniModal() {
    const modal = document.getElementById('importAlumniModal');
    const fileInput = document.getElementById('alumniImportFile');
    if (!modal) return;
    if (fileInput) fileInput.value = '';
    modal.style.display = 'none';
}

function openCreateEventModal() {
    const modal = document.getElementById('createEventModal');
    if (!modal) return;
    modal.style.display = 'flex';
}

function closeCreateEventModal() {
    const modal = document.getElementById('createEventModal');
    if (!modal) return;
    modal.style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function (event) {
    if (event && event.target && event.target.classList && event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
};

// Initial Render (page-specific; guards prevent errors on other pages)
async function initStaffPage() {
    const currentUser = await requireAuth();
    if (!currentUser) return;

    updateStaffHeaderInfo(currentUser);

    const yearFromUrl = getSelectedBatchYearFromUrl();
    const collegeFromUrl = getSelectedCollegeFromUrl();
    const courseFromUrl = getSelectedCourseFromUrl();
    if (yearFromUrl) {
        selectedAlumniBatchYear = yearFromUrl;
    }
    if (collegeFromUrl) {
        selectedAlumniCollege = collegeFromUrl;
    }
    if (courseFromUrl) {
        selectedAlumniCourse = courseFromUrl;
    }

    renderEvents();
    setupNewsForm();

    await Promise.all([
        loadAlumniBatches(),
        loadAlumni(),
        loadRecentActivities(),
        loadSurveys(),
        loadNewsPosts(),
    ]);

    updateStats();
    renderYearDistribution();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initStaffPage();
    });
} else {
    initStaffPage();
}


// Load dashboard data
// Load dashboard data
async function loadDashboardData() 
{
    try {
        const response = await fetch(getDashboardData(), {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error: ${response.status}`);
        }
        
        const data = await response.json();
        
        // Only update elements that exist in Dashboard.html
        const totalAlumniEl = document.getElementById('totalAlumni');
        const activeAlumniEl = document.getElementById('activeAlumni');
        const totalEventsEl = document.getElementById('totalEvents');
        const engagementRateEl = document.getElementById('engagementRate');
        
        // Update only if elements exist
        if (totalAlumniEl) totalAlumniEl.textContent = data.totalAlumni || 0;
        if (activeAlumniEl) activeAlumniEl.textContent = data.activeAlumni || 0;
        if (totalEventsEl) totalEventsEl.textContent = data.upcomingEvents || 0;
        if (engagementRateEl) {
            // Calculate engagement rate if not provided
            const engagementRate = data.engagementRate || 
                (data.totalAlumni > 0 ? Math.round((data.activeAlumni / data.totalAlumni) * 100) : 0);
            engagementRateEl.textContent = `${engagementRate}%`;
        }
        
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        
        // Set default values only for elements that exist
        const totalAlumniEl = document.getElementById('totalAlumni');
        const activeAlumniEl = document.getElementById('activeAlumni');
        const totalEventsEl = document.getElementById('totalEvents');
        const engagementRateEl = document.getElementById('engagementRate');
        
        if (totalAlumniEl) totalAlumniEl.textContent = '0';
        if (activeAlumniEl) activeAlumniEl.textContent = '0';
        if (totalEventsEl) totalEventsEl.textContent = '0';
        if (engagementRateEl) engagementRateEl.textContent = '0%';
    }
}

function getSelectedReportOptions() {
    return {
        reportType: document.getElementById('reportType').value,
        format: document.getElementById('reportFormat').value,
        includeSummary: document.getElementById('includeSummary').checked,
        includeCharts: document.getElementById('includeCharts').checked,
        includeDetails: document.getElementById('includeDetails').checked,
        includeContactInfo: document.getElementById('includeContactInfo').checked,
    };
}

function resolveReportFileExtension(format) {
    switch (format) {
        case 'excel':
        case 'csv':
        case 'xlsx':
            return 'xlsx';
        case 'pdf':
            return 'pdf';
        case 'word':
        case 'docx':
            return 'docx';
        default:
            return 'dat';
    }
}

function resolveReportFilename(contentDisposition, format) {
    const extension = resolveReportFileExtension(format);
    let filename = `alumni_report_${new Date().toISOString().split('T')[0]}.${extension}`;

    if (!contentDisposition) {
        return filename;
    }

    const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
    if (filenameMatch && filenameMatch[1]) {
        filename = filenameMatch[1].replace(/['"]/g, '');
    }

    return filename;
}

/* ================================================================
   5-STEP SURVEY REPORT WIZARD LOGIC
   ================================================================ */

const reportWizardState = {
    currentStep: 1,
    surveys: [],
    selectedSurveyId: null,
    selectedSurvey: null,
    selectedQuestionIds: new Set(),
    collapsedCategories: new Set(),
    filters: {
        graduation_year: '',
        college: '',
        course: '',
        review_status: 'all',
        date_from: '',
        date_to: '',
        search_query: '',
        answer_filters: {} // questionId => [choice values]
    },
    colleges: [],
    departments: [],
    reportType: 'alumni_tracer',
    format: 'pdf',
    liveMatchCount: null,
    isGenerating: false,
    previewDebounceTimer: null
};

function openReportWizardModal(preferredSurveyId = null) {
    const modal = document.getElementById('reportWizardModal');
    if (!modal) return;

    modal.hidden = false;
    document.body.style.overflow = 'hidden';

    // Reset or initialize state
    reportWizardState.currentStep = 1;
    reportWizardState.reportType = 'alumni_tracer';
    reportWizardState.format = 'pdf';
    wizardOnReportTypeSelect('alumni_tracer');
    wizardOnFormatSelect('pdf');
    wizardUpdateStepperUI();
    wizardShowPane(1);

    const targetSurveyId = preferredSurveyId || currentSelectedSurveyFeedId || (typeof getSelectedSurveyId === 'function' ? getSelectedSurveyId() : null);

    if (reportWizardState.surveys && reportWizardState.surveys.length > 0) {
        renderWizardSurveys(reportWizardState.surveys);
        if (targetSurveyId) {
            const found = reportWizardState.surveys.find((s) => Number(s.id) === Number(targetSurveyId));
            if (found) {
                wizardSelectSurvey(found.id);
            }
        }
    }

    // Initial data fetch
    wizardInitData(targetSurveyId);
}

function closeReportWizardModal() {
    const modal = document.getElementById('reportWizardModal');
    if (!modal) return;

    modal.hidden = true;
    document.body.style.overflow = '';
}

async function wizardInitData(targetSurveyId = null) {
    const container = document.getElementById('wizardSurveyListContainer');
    if (container && reportWizardState.surveys.length === 0) {
        container.innerHTML = '<div class="wizard-loading-state"><i class="fas fa-spinner fa-spin"></i> Loading available surveys...</div>';
    }

    try {
        const [surveysRes, batchesRes, collegesRes, deptRes] = await Promise.all([
            fetch(getSurveyListUrl(), { credentials: 'include', cache: 'no-store' }),
            fetch(resolveStaffAppUrl('alumni/batches'), { credentials: 'include', cache: 'no-store' }).catch(() => null),
            fetch(resolveStaffAppUrl('staff/colleges-data'), { credentials: 'include', cache: 'no-store' }).catch(() => null),
            fetch(resolveStaffAppUrl('staff/departments-data'), { credentials: 'include', cache: 'no-store' }).catch(() => null),
        ]);

        if (surveysRes && surveysRes.ok) {
            const result = await surveysRes.json();
            reportWizardState.surveys = Array.isArray(result.data) ? result.data : [];
            renderWizardSurveys(reportWizardState.surveys);

            if (targetSurveyId) {
                const found = reportWizardState.surveys.find((s) => Number(s.id) === Number(targetSurveyId));
                if (found) {
                    wizardSelectSurvey(found.id);
                }
            }
        }

        if (batchesRes && batchesRes.ok) {
            const bData = await batchesRes.json();
            const rawBatches = Array.isArray(bData.batches) ? bData.batches : (Array.isArray(bData.data) ? bData.data : []);
            reportWizardState.batches = rawBatches;
        }

        if (collegesRes && collegesRes.ok) {
            const cData = await collegesRes.json();
            if (cData && cData.success && Array.isArray(cData.colleges)) {
                reportWizardState.colleges = cData.colleges;
            }
        }

        if (deptRes && deptRes.ok) {
            const dData = await deptRes.json();
            if (dData && dData.success && Array.isArray(dData.departments)) {
                reportWizardState.departments = dData.departments;
            }
        }

        wizardPopulateFilterDropdowns();
    } catch (err) {
        if (container) {
            container.innerHTML = `<div class="wizard-empty-state"><i class="fas fa-exclamation-circle" style="color:#ef4444; font-size:24px; margin-bottom:8px; display:block;"></i> Failed to load surveys: ${escapeHtml(err.message || String(err))}</div>`;
        }
    }
}

function wizardUpdateStepperUI() {
    const step = reportWizardState.currentStep;
    const subtitles = [
        'Step 1 of 5: Select Survey / Form',
        'Step 2 of 5: Choose Categories & Questions',
        'Step 3 of 5: Filter Responses',
        'Step 4 of 5: Select Export Document Format',
        'Step 5 of 5: Review Configuration & Generate'
    ];

    const subEl = document.getElementById('wizardModalSubtitle');
    if (subEl) subEl.textContent = subtitles[step - 1] || `Step ${step} of 5`;

    // Update Stepper Nodes
    const nodes = document.querySelectorAll('.wizard-step-node');
    nodes.forEach((node) => {
        const nodeStep = parseInt(node.dataset.step, 10);
        node.classList.remove('is-active', 'is-complete');
        if (nodeStep === step) {
            node.classList.add('is-active');
        } else if (nodeStep < step) {
            node.classList.add('is-complete');
        }
    });

    // Update Footer Buttons
    const prevBtn = document.getElementById('wizardPrevBtn');
    const nextBtn = document.getElementById('wizardNextBtn');
    const genBtn = document.getElementById('wizardGenerateBtn');

    if (prevBtn) prevBtn.style.display = step > 1 ? 'inline-flex' : 'none';
    if (nextBtn) nextBtn.style.display = step < 5 ? 'inline-flex' : 'none';
    if (genBtn) genBtn.style.display = step === 5 ? 'inline-flex' : 'none';

    // Next button disabled validation
    if (nextBtn) {
        if (step === 1) {
            nextBtn.disabled = !reportWizardState.selectedSurveyId;
        } else {
            nextBtn.disabled = false;
        }
    }
}

function wizardShowPane(step) {
    for (let i = 1; i <= 5; i++) {
        const pane = document.getElementById(`wizardPane${i}`);
        if (pane) pane.hidden = i !== step;
    }

    const scrollBody = document.querySelector('.report-wizard-body');
    if (scrollBody) scrollBody.scrollTop = 0;
}

function wizardJumpToStep(targetStep) {
    if (targetStep < reportWizardState.currentStep) {
        reportWizardState.currentStep = targetStep;
        wizardUpdateStepperUI();
        wizardShowPane(targetStep);
    } else if (targetStep > reportWizardState.currentStep) {
        // Can only jump forward if validation passes
        if (reportWizardState.currentStep === 1 && !reportWizardState.selectedSurveyId) return;
        
        reportWizardState.currentStep = targetStep;
        wizardUpdateStepperUI();
        wizardShowPane(targetStep);
        if (targetStep === 3) {
            renderWizardAnswerFilters();
            wizardFetchLivePreviewCount();
        } else if (targetStep === 5) {
            renderWizardReviewSummary();
        }
    }
}

function wizardNextStep() {
    const step = reportWizardState.currentStep;
    if (step === 1) {
        if (!reportWizardState.selectedSurveyId) {
            alert('Please select a survey to continue.');
            return;
        }
        renderWizardCategories();
    } else if (step === 2) {
        renderWizardAnswerFilters();
        wizardFetchLivePreviewCount();
    } else if (step === 3) {
        // Filters step complete
    } else if (step === 4) {
        renderWizardReviewSummary();
    }

    if (step < 5) {
        reportWizardState.currentStep = step + 1;
        wizardUpdateStepperUI();
        wizardShowPane(reportWizardState.currentStep);
    }
}

function wizardPrevStep() {
    if (reportWizardState.currentStep > 1) {
        reportWizardState.currentStep--;
        wizardUpdateStepperUI();
        wizardShowPane(reportWizardState.currentStep);
    }
}

/* Step 1: Render Surveys */
function renderWizardSurveys(surveys) {
    const container = document.getElementById('wizardSurveyListContainer');
    if (!container) return;

    if (!Array.isArray(surveys) || surveys.length === 0) {
        container.innerHTML = '<div class="wizard-empty-state"><i class="fas fa-inbox" style="font-size:28px; opacity:0.4; margin-bottom:8px; display:block;"></i>No surveys found.</div>';
        return;
    }

    container.innerHTML = surveys.map((s) => {
        const isSelected = reportWizardState.selectedSurveyId === s.id;
        const totalQuestions = (s.categories || []).reduce((acc, cat) => acc + (cat.questions ? cat.questions.length : 0), 0);
        const state = (s.surveyState || s.status || 'open').toLowerCase();

        return `
            <div class="wizard-survey-card ${isSelected ? 'is-selected' : ''}" onclick="wizardSelectSurvey(${s.id})">
                <input type="radio" name="wizardSurveyRadio" value="${s.id}" ${isSelected ? 'checked' : ''} onclick="event.stopPropagation(); wizardSelectSurvey(${s.id});">
                <div class="wizard-survey-card-body">
                    <div class="wizard-survey-card-header">
                        <h5>${escapeHtml(s.title || 'Untitled Survey')}</h5>
                        <span class="wizard-status-pill is-${state}">${state}</span>
                    </div>
                    <p class="wizard-survey-card-desc">${escapeHtml(s.description || 'No description provided.')}</p>
                    <div class="wizard-survey-card-footer">
                        <span><i class="fas fa-layer-group"></i> ${(s.categories || []).length} Categories</span>
                        <span><i class="fas fa-circle-question"></i> ${totalQuestions} Questions</span>
                        ${s.batch ? `<span><i class="fas fa-graduation-cap"></i> Batch ${escapeHtml(String(s.batch))}</span>` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function filterWizardSurveys() {
    const searchVal = (document.getElementById('wizardSurveySearch')?.value || '').toLowerCase().trim();
    if (!searchVal) {
        renderWizardSurveys(reportWizardState.surveys);
        return;
    }

    const filtered = reportWizardState.surveys.filter((s) => {
        return (s.title && s.title.toLowerCase().includes(searchVal)) ||
               (s.description && s.description.toLowerCase().includes(searchVal));
    });

    renderWizardSurveys(filtered);
}

function isImageQuestion(q) {
    if (!q) return false;
    const type = String(q.type || q.question_type || q.questionType || '').toLowerCase().trim();
    return type === 'image';
}

function isTextQuestion(q) {
    if (!q) return false;
    const type = String(q.type || q.question_type || q.questionType || 'text').toLowerCase().trim();
    return type === 'text' || type === 'short_text' || type === 'long_text' || type === 'paragraph';
}

function getSurveyCreatedDateString(survey) {
    if (!survey) return '';
    const rawDate = String(survey.createdAt || survey.created_at || survey.openDateTime || survey.open_datetime || '').trim();
    if (!rawDate) return '';

    const isoMatch = rawDate.match(/^(\d{4}-\d{2}-\d{2})/);
    if (isoMatch) {
        return isoMatch[1];
    }

    const parsed = new Date(rawDate);
    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    const yyyy = parsed.getFullYear();
    const mm = String(parsed.getMonth() + 1).padStart(2, '0');
    const dd = String(parsed.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function wizardSelectSurvey(surveyId) {
    const survey = reportWizardState.surveys.find((s) => s.id === surveyId);
    if (!survey) return;

    reportWizardState.selectedSurveyId = surveyId;
    reportWizardState.selectedSurvey = survey;

    // By default, unselect all text and image questions immediately so the user does not need to unselect them one by one
    reportWizardState.selectedQuestionIds.clear();
    (survey.categories || []).forEach((cat) => {
        (cat.questions || []).forEach((q) => {
            if (!isImageQuestion(q) && !isTextQuestion(q)) {
                reportWizardState.selectedQuestionIds.add(q.id);
            }
        });
    });

    // Reset dynamic answer filters and default "Submitted From Date" to the survey creation date
    reportWizardState.filters.answer_filters = {};
    const createdDateStr = getSurveyCreatedDateString(survey);
    reportWizardState.filters.date_from = createdDateStr;
    const fromEl = document.getElementById('wizardFilterDateFrom');
    if (fromEl) {
        fromEl.value = createdDateStr;
    }

    renderWizardSurveys(reportWizardState.surveys);
    wizardUpdateStepperUI();
}

/* Step 2: Render Categories & Questions */
function renderWizardCategories() {
    const container = document.getElementById('wizardCategoriesContainer');
    if (!container || !reportWizardState.selectedSurvey) return;

    const survey = reportWizardState.selectedSurvey;
    const categories = survey.categories || [];

    if (categories.length === 0) {
        container.innerHTML = '<div class="wizard-empty-state">No categories or questions in this survey.</div>';
        return;
    }

    container.innerHTML = categories.map((cat) => {
        const catQuestions = cat.questions || [];
        const exportableQuestions = catQuestions.filter((q) => !isImageQuestion(q));
        const isCollapsed = reportWizardState.collapsedCategories.has(cat.id);
        const selectedCount = catQuestions.filter((q) => reportWizardState.selectedQuestionIds.has(q.id)).length;
        const isAllSelected = exportableQuestions.length > 0 && selectedCount === exportableQuestions.length;
        const isIndeterminate = selectedCount > 0 && selectedCount < exportableQuestions.length;

        return `
            <div class="wizard-category-card ${isCollapsed ? 'is-collapsed' : ''}" data-cat-id="${cat.id}">
                <div class="wizard-category-header" onclick="wizardToggleCategoryCollapse(${cat.id})">
                    <div class="wizard-category-header-left">
                        <input type="checkbox" 
                               id="cat_cb_${cat.id}"
                               class="wizard-cat-cb" 
                               ${isAllSelected ? 'checked' : ''} 
                               ${isIndeterminate ? 'data-indeterminate="true"' : ''}
                               ${exportableQuestions.length === 0 ? 'disabled' : ''}
                               onclick="event.stopPropagation(); wizardToggleCategory(${cat.id}, this.checked);">
                        <div class="wizard-category-title-group">
                            <h5>${escapeHtml(cat.name || 'General Information')}</h5>
                            <p>${catQuestions.length} question${catQuestions.length === 1 ? '' : 's'}${catQuestions.length !== exportableQuestions.length ? ` (${exportableQuestions.length} exportable)` : ''}</p>
                        </div>
                    </div>
                    <div class="wizard-category-header-right">
                        <span class="wizard-category-badge">${selectedCount} of ${exportableQuestions.length} selected</span>
                        <i class="fas fa-chevron-down wizard-category-toggle-icon"></i>
                    </div>
                </div>

                <div class="wizard-questions-list">
                    ${catQuestions.map((q) => {
                        const isImage = isImageQuestion(q);
                        const isQSelected = !isImage && reportWizardState.selectedQuestionIds.has(q.id);
                        const qType = (q.type || 'text').toLowerCase();
                        return `
                            <label class="wizard-question-item ${isQSelected ? 'is-selected' : ''} ${isImage ? 'is-disabled' : ''}" 
                                   ${isImage ? 'title="Image questions cannot be included in export reports"' : ''}
                                   onclick="event.stopPropagation();">
                                <input type="checkbox" 
                                       data-cat-id="${cat.id}" 
                                       data-q-id="${q.id}" 
                                       ${isQSelected ? 'checked' : ''} 
                                       ${isImage ? 'disabled' : ''}
                                       onchange="wizardToggleQuestion(${q.id}, this.checked);">
                                <span class="wizard-question-text">${escapeHtml(q.text || 'Untitled question')}</span>
                                ${isImage 
                                    ? `<span class="wizard-qtype-badge is-image" title="Image questions cannot be exported"><i class="fas fa-image"></i> image (not included in export)</span>`
                                    : `<span class="wizard-qtype-badge is-${qType}">${qType.replace('_', ' ')}</span>`
                                }
                            </label>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }).join('');

    // Apply indeterminate state to checkboxes
    categories.forEach((cat) => {
        const cb = document.getElementById(`cat_cb_${cat.id}`);
        if (cb && cb.dataset.indeterminate === 'true') {
            cb.indeterminate = true;
        }
    });

    wizardUpdateSelectionCountBadge();
}

function wizardToggleCategoryCollapse(catId) {
    if (reportWizardState.collapsedCategories.has(catId)) {
        reportWizardState.collapsedCategories.delete(catId);
    } else {
        reportWizardState.collapsedCategories.add(catId);
    }
    renderWizardCategories();
}

function wizardToggleCategory(catId, isChecked) {
    const survey = reportWizardState.selectedSurvey;
    if (!survey) return;

    const cat = (survey.categories || []).find((c) => c.id === catId);
    if (!cat) return;

    (cat.questions || []).forEach((q) => {
        if (isChecked) {
            if (!isImageQuestion(q)) {
                reportWizardState.selectedQuestionIds.add(q.id);
            }
        } else {
            reportWizardState.selectedQuestionIds.delete(q.id);
        }
    });

    renderWizardCategories();
    wizardUpdateStepperUI();
}

function wizardToggleQuestion(qId, isChecked) {
    if (isChecked) {
        let isImage = false;
        const survey = reportWizardState.selectedSurvey;
        if (survey) {
            for (const cat of (survey.categories || [])) {
                const found = (cat.questions || []).find((q) => q.id === qId);
                if (found && isImageQuestion(found)) {
                    isImage = true;
                    break;
                }
            }
        }
        if (!isImage) {
            reportWizardState.selectedQuestionIds.add(qId);
        }
    } else {
        reportWizardState.selectedQuestionIds.delete(qId);
    }

    renderWizardCategories();
    wizardUpdateStepperUI();
}

function wizardToggleAllQuestions(select) {
    const survey = reportWizardState.selectedSurvey;
    if (!survey) return;

    if (select) {
        (survey.categories || []).forEach((cat) => {
            (cat.questions || []).forEach((q) => {
                if (!isImageQuestion(q)) {
                    reportWizardState.selectedQuestionIds.add(q.id);
                }
            });
        });
    } else {
        reportWizardState.selectedQuestionIds.clear();
    }

    renderWizardCategories();
    wizardUpdateStepperUI();
}

function wizardUpdateSelectionCountBadge() {
    const badge = document.getElementById('wizardQuestionSelectionBadge');
    if (!badge) return;

    const count = reportWizardState.selectedQuestionIds.size;
    badge.textContent = `${count} question${count === 1 ? '' : 's'} selected`;
}

function filterWizardQuestions() {
    const searchVal = (document.getElementById('wizardQuestionSearch')?.value || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.wizard-category-card');

    cards.forEach((card) => {
        let hasVisibleQuestion = false;
        const qItems = card.querySelectorAll('.wizard-question-item');

        qItems.forEach((qItem) => {
            const text = (qItem.querySelector('.wizard-question-text')?.textContent || '').toLowerCase();
            if (!searchVal || text.includes(searchVal)) {
                qItem.style.display = 'flex';
                hasVisibleQuestion = true;
            } else {
                qItem.style.display = 'none';
            }
        });

        card.style.display = hasVisibleQuestion ? 'block' : 'none';
    });
}

/* Step 3: Populate Filter Dropdowns & Dynamic Choices */
function wizardPopulateFilterDropdowns() {
    // 1. Graduation Years from alumni_batches table
    const yearSelect = document.getElementById('wizardFilterYear');
    if (yearSelect) {
        let years = [];
        if (Array.isArray(reportWizardState.batches) && reportWizardState.batches.length > 0) {
            years = reportWizardState.batches.map((b) => String(b.graduationYear || b.batch_year || b.year || '')).filter((y) => y.trim() !== '');
            // Deduplicate and sort descending
            years = [...new Set(years)].sort((a, b) => b.localeCompare(a));
        }

        if (years.length > 0) {
            yearSelect.innerHTML = '<option value="">All graduation years</option>' +
                years.map((y) => `<option value="${escapeHtml(y)}">Class of ${escapeHtml(y)}</option>`).join('');
        } else if (yearSelect.options.length <= 1) {
            const currentYear = new Date().getFullYear();
            for (let y = currentYear + 1; y >= 2010; y--) {
                const opt = document.createElement('option');
                opt.value = String(y);
                opt.textContent = `Class of ${y}`;
                yearSelect.appendChild(opt);
            }
        }
    }

    // 2. Colleges from colleges table
    const collegeSelect = document.getElementById('wizardFilterCollege');
    if (collegeSelect && Array.isArray(reportWizardState.colleges) && reportWizardState.colleges.length > 0) {
        collegeSelect.innerHTML = '<option value="">All colleges</option>' +
            reportWizardState.colleges.map((c) => {
                const cName = c.college || c.College_Name || c.name || '';
                return `<option value="${escapeHtml(cName)}">${escapeHtml(cName)}</option>`;
            }).join('');
    }

    // 3. Departments/Programs from departments table
    wizardPopulateDepartments();
}

function wizardPopulateDepartments(selectedCollegeName = '') {
    const deptSelect = document.getElementById('wizardFilterCourse');
    if (!deptSelect) return;

    let depts = Array.isArray(reportWizardState.departments) ? reportWizardState.departments : [];
    if (selectedCollegeName) {
        const matchedCollege = (reportWizardState.colleges || []).find((c) => (c.college || c.College_Name || c.name) === selectedCollegeName);
        const matchedCollegeId = matchedCollege ? (matchedCollege.college_id || matchedCollege.College_ID) : null;

        depts = depts.filter((d) => {
            if (matchedCollegeId && (d.college_id || d.College_ID)) {
                return String(d.college_id || d.College_ID) === String(matchedCollegeId);
            }
            if (d.college || d.College_Name) {
                return (d.college || d.College_Name) === selectedCollegeName;
            }
            return true;
        });
    }

    deptSelect.innerHTML = '<option value="">All departments & programs</option>' +
        depts.map((d) => {
            const dName = d.department || d.Department_Name || d.name || '';
            return `<option value="${escapeHtml(dName)}">${escapeHtml(dName)}</option>`;
        }).join('');
}

function wizardOnCollegeChange() {
    const collegeVal = document.getElementById('wizardFilterCollege')?.value || '';
    wizardPopulateDepartments(collegeVal);
    wizardOnFilterChange();
}

/* Dynamic Survey Answer Filter Cards */
function renderWizardAnswerFilters() {
    const container = document.getElementById('wizardAnswerFiltersContainer');
    const section = document.getElementById('wizardAnswerFilterSection');
    if (!container || !reportWizardState.selectedSurvey) return;

    const choiceQuestions = [];
    (reportWizardState.selectedSurvey.categories || []).forEach((cat) => {
        (cat.questions || []).forEach((q) => {
            const qType = (q.type || '').toLowerCase();
            const options = Array.isArray(q.options) ? q.options : (Array.isArray(q.choices) ? q.choices.map((c) => c.text || c.choice_text) : []);
            if ((qType === 'multiple_choice' || qType === 'rating') && options.length > 0) {
                choiceQuestions.push({
                    id: q.id,
                    text: q.text,
                    categoryName: cat.name,
                    options: options
                });
            }
        });
    });

    if (choiceQuestions.length === 0) {
        if (section) section.style.display = 'none';
        return;
    }

    if (section) section.style.display = 'block';
    container.innerHTML = choiceQuestions.map((q) => {
        const activeChoices = reportWizardState.filters.answer_filters[q.id] || [];

        return `
            <div class="wizard-ans-filter-card" data-q-id="${q.id}">
                <h6><i class="fas fa-question-circle" style="color:#1e4a76; margin-right:4px;"></i> ${escapeHtml(q.text || 'Question')}</h6>
                <div class="wizard-ans-choices-grid">
                    ${q.options.map((opt) => {
                        const optStr = String(opt);
                        const isChecked = activeChoices.includes(optStr);
                        return `
                            <label class="wizard-ans-choice-chip ${isChecked ? 'is-selected' : ''}">
                                <input type="checkbox" 
                                       value="${escapeHtml(optStr)}" 
                                       ${isChecked ? 'checked' : ''} 
                                       onchange="wizardOnAnswerChoiceToggle(${q.id}, '${escapeHtml(optStr)}', this.checked)">
                                <span>${escapeHtml(optStr)}</span>
                            </label>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }).join('');
}

function wizardOnAnswerChoiceToggle(qId, choiceVal, isChecked) {
    if (!reportWizardState.filters.answer_filters[qId]) {
        reportWizardState.filters.answer_filters[qId] = [];
    }

    const currentArr = reportWizardState.filters.answer_filters[qId];
    if (isChecked) {
        if (!currentArr.includes(choiceVal)) currentArr.push(choiceVal);
    } else {
        const idx = currentArr.indexOf(choiceVal);
        if (idx !== -1) currentArr.splice(idx, 1);
    }

    if (currentArr.length === 0) {
        delete reportWizardState.filters.answer_filters[qId];
    }

    renderWizardAnswerFilters();
    wizardOnFilterChange();
}

function wizardOnFilterChange() {
    reportWizardState.filters.graduation_year = document.getElementById('wizardFilterYear')?.value || '';
    reportWizardState.filters.college = document.getElementById('wizardFilterCollege')?.value || '';
    reportWizardState.filters.course = document.getElementById('wizardFilterCourse')?.value || '';
    reportWizardState.filters.review_status = document.getElementById('wizardFilterReviewStatus')?.value || 'all';
    reportWizardState.filters.date_from = document.getElementById('wizardFilterDateFrom')?.value || '';
    reportWizardState.filters.date_to = document.getElementById('wizardFilterDateTo')?.value || '';
    reportWizardState.filters.search_query = document.getElementById('wizardFilterSearch')?.value || '';

    // Debounced count calculation
    clearTimeout(reportWizardState.previewDebounceTimer);
    const countEl = document.getElementById('wizardLiveMatchCount');
    if (countEl) countEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculating...';

    reportWizardState.previewDebounceTimer = setTimeout(() => {
        wizardFetchLivePreviewCount();
    }, 350);
}

function wizardResetFilters() {
    const yearEl = document.getElementById('wizardFilterYear');
    const collegeEl = document.getElementById('wizardFilterCollege');
    const courseEl = document.getElementById('wizardFilterCourse');
    const statusEl = document.getElementById('wizardFilterReviewStatus');
    const fromEl = document.getElementById('wizardFilterDateFrom');
    const toEl = document.getElementById('wizardFilterDateTo');
    const searchEl = document.getElementById('wizardFilterSearch');

    const defaultFromDate = getSurveyCreatedDateString(reportWizardState.selectedSurvey);

    if (yearEl) yearEl.value = '';
    if (collegeEl) collegeEl.value = '';
    if (courseEl) courseEl.value = '';
    if (statusEl) statusEl.value = 'all';
    if (fromEl) fromEl.value = defaultFromDate;
    if (toEl) toEl.value = '';
    if (searchEl) searchEl.value = '';

    reportWizardState.filters = {
        graduation_year: '',
        college: '',
        course: '',
        review_status: 'all',
        date_from: defaultFromDate,
        date_to: '',
        search_query: '',
        answer_filters: {}
    };

    renderWizardAnswerFilters();
    wizardFetchLivePreviewCount();
}

async function wizardFetchLivePreviewCount() {
    if (!reportWizardState.selectedSurveyId) return;

    try {
        const response = await fetch(resolveStaffAppUrl('reports/preview-count'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                survey_id: reportWizardState.selectedSurveyId,
                filters: reportWizardState.filters
            }),
            credentials: 'include'
        });

        if (response.ok) {
            const data = await response.json();
            reportWizardState.liveMatchCount = Number(data.count || 0);
            
            const countEl = document.getElementById('wizardLiveMatchCount');
            if (countEl) {
                countEl.textContent = `${reportWizardState.liveMatchCount} alumni respondent${reportWizardState.liveMatchCount === 1 ? '' : 's'}`;
            }
        }
    } catch (err) {
        const countEl = document.getElementById('wizardLiveMatchCount');
        if (countEl) countEl.textContent = 'All submitted responses';
    }
}

/* Step 4: Report Type & Format Selection */
function wizardOnReportTypeSelect(type) {
    reportWizardState.reportType = type;

    const cards = {
        alumni_tracer: document.getElementById('wizardReportTypeCardAlumniTracer'),
        graphs: document.getElementById('wizardReportTypeCardGraphs'),
        raw_numbers: document.getElementById('wizardReportTypeCardRawNumbers')
    };

    Object.keys(cards).forEach((k) => {
        if (cards[k]) {
            if (k === type) {
                cards[k].classList.add('is-selected');
                const radio = cards[k].querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            } else {
                cards[k].classList.remove('is-selected');
            }
        }
    });
}

function wizardOnFormatSelect(format) {
    reportWizardState.format = format;

    const cards = {
        pdf: document.getElementById('wizardFormatCardPdf'),
        docx: document.getElementById('wizardFormatCardDocx'),
        csv: document.getElementById('wizardFormatCardCsv')
    };

    Object.keys(cards).forEach((k) => {
        if (cards[k]) {
            if (k === format) {
                cards[k].classList.add('is-selected');
                const radio = cards[k].querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            } else {
                cards[k].classList.remove('is-selected');
            }
        }
    });
}

/* Step 5: Review Summary */
function renderWizardReviewSummary() {
    const surveyTitleEl = document.getElementById('wizardReviewSurveyTitle');
    const reportTypeEl = document.getElementById('wizardReviewReportType');
    const dataSummaryEl = document.getElementById('wizardReviewDataSummary');
    const formatEl = document.getElementById('wizardReviewFormat');
    const filtersListEl = document.getElementById('wizardReviewFiltersList');
    const matchCountEl = document.getElementById('wizardReviewMatchCount');
    const matchNoteEl = document.getElementById('wizardReviewMatchNote');

    if (!reportWizardState.selectedSurvey) return;

    if (surveyTitleEl) surveyTitleEl.textContent = reportWizardState.selectedSurvey.title || 'Untitled Survey';

    const reportTypeNames = {
        alumni_tracer: 'Alumni Tracer',
        graphs: 'Graphs',
        raw_numbers: 'Raw number of every total number answer gathered'
    };
    if (reportTypeEl) reportTypeEl.textContent = reportTypeNames[reportWizardState.reportType] || 'Alumni Tracer';

    const selectedQCount = reportWizardState.selectedQuestionIds.size;
    const catCount = (reportWizardState.selectedSurvey.categories || []).filter((c) => {
        return (c.questions || []).some((q) => reportWizardState.selectedQuestionIds.has(q.id));
    }).length;

    if (dataSummaryEl) {
        dataSummaryEl.textContent = `${catCount} Categor${catCount === 1 ? 'y' : 'ies'}, ${selectedQCount} Question${selectedQCount === 1 ? '' : 's'} included`;
    }

    const formatNames = {
        pdf: 'PDF Document (.pdf)',
        docx: 'Word Document (.docx)',
        csv: 'Excel / CSV Spreadsheet (.xlsx)'
    };
    if (formatEl) formatEl.textContent = formatNames[reportWizardState.format] || 'PDF';

    // Filter pills
    if (filtersListEl) {
        const filterPills = [];
        const f = reportWizardState.filters;
        if (f.graduation_year) filterPills.push(`Graduation Year: Class of ${escapeHtml(f.graduation_year)}`);
        if (f.college) filterPills.push(`College: ${escapeHtml(f.college)}`);
        if (f.course) filterPills.push(`Program: ${escapeHtml(f.course)}`);
        if (f.review_status && f.review_status !== 'all') filterPills.push(`Status: ${escapeHtml(f.review_status)}`);
        if (f.date_from) filterPills.push(`From: ${escapeHtml(f.date_from)}`);
        if (f.date_to) filterPills.push(`To: ${escapeHtml(f.date_to)}`);
        if (f.search_query) filterPills.push(`Search: "${escapeHtml(f.search_query)}"`);

        const answerFilterKeys = Object.keys(f.answer_filters || {});
        if (answerFilterKeys.length > 0) {
            filterPills.push(`${answerFilterKeys.length} Dynamic Answer Filter${answerFilterKeys.length === 1 ? '' : 's'}`);
        }

        if (filterPills.length === 0) {
            filtersListEl.innerHTML = '<span class="wizard-filter-pill" style="background:#f1f5f9; color:#64748b;">No filters applied (All survey respondents)</span>';
        } else {
            filtersListEl.innerHTML = filterPills.map((pill) => `<span class="wizard-filter-pill"><i class="fas fa-check"></i> ${pill}</span>`).join('');
        }
    }

    const count = reportWizardState.liveMatchCount !== null ? reportWizardState.liveMatchCount : 0;
    if (matchCountEl) matchCountEl.textContent = `${count} Respondent${count === 1 ? '' : 's'}`;
    if (matchNoteEl) {
        matchNoteEl.textContent = count > 0 
            ? 'Report data compiled and ready for instant generation.' 
            : 'No matching responses found for these filter criteria.';
    }
}

/* Report Generation Action */
async function wizardExecuteGeneration() {
    if (!reportWizardState.selectedSurveyId) {
        alert('Please select a survey.');
        return;
    }

    const genBtn = document.getElementById('wizardGenerateBtn');
    if (genBtn) {
        genBtn.disabled = true;
        genBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Report...';
    }

    try {
        const sanitizedQuestionIds = Array.from(reportWizardState.selectedQuestionIds).filter((qId) => {
            const survey = reportWizardState.selectedSurvey;
            if (!survey) return true;
            for (const cat of (survey.categories || [])) {
                const found = (cat.questions || []).find((q) => q.id === qId);
                if (found && isImageQuestion(found)) return false;
            }
            return true;
        });

        const payload = {
            survey_id: reportWizardState.selectedSurveyId,
            report_type: reportWizardState.reportType,
            selected_questions: sanitizedQuestionIds,
            filters: reportWizardState.filters,
            format: reportWizardState.format
        };

        const response = await fetch(getGenerateReportUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'include'
        });

        if (!response.ok) {
            let errMsg = `HTTP Error ${response.status}`;
            try {
                const errData = await response.json();
                if (errData && errData.message) errMsg = errData.message;
            } catch (e) { /* non-json error */ }
            throw new Error(errMsg);
        }

        const contentDisposition = response.headers.get('Content-Disposition');
        const filename = resolveReportFilename(contentDisposition, reportWizardState.format);

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = filename;
        document.body.appendChild(anchor);
        anchor.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(anchor);

        closeReportWizardModal();
        alert('Survey Report generated and downloaded successfully!');
    } catch (err) {
        console.error('Report Generation Error:', err);
        alert(err instanceof Error ? err.message : 'Failed to generate report. Please try again.');
    } finally {
        if (genBtn) {
            genBtn.disabled = false;
            genBtn.innerHTML = '<i class="fas fa-file-export"></i> Generate &amp; Download Report';
        }
    }
}

// Survey Batch Statistics for Reports Page
let currentReportBatchStats = null;
let currentReportSurveyStats = null;

async function loadReportSurveySelector() {
    const selector = document.getElementById('reportSurveySelector');
    if (!selector) return;

    try {
        const response = await fetch(getSurveyListUrl(), { credentials: 'include', cache: 'no-store' });
        if (!response.ok) throw new Error('Failed to load surveys');
        const result = await response.json();
        const surveys = Array.isArray(result.data) ? result.data : [];

        selector.innerHTML = '<option value="">Select a survey...</option>' +
            surveys.map((s) => `<option value="${escapeHtml(String(s.id ?? ''))}">${escapeHtml(String(s.title ?? 'Untitled'))}</option>`).join('');
    } catch (error) {
        selector.innerHTML = '<option value="">Unable to load surveys</option>';
    }
}

async function onReportSurveyChange() {
    const selector = document.getElementById('reportSurveySelector');
    const container = document.getElementById('surveyBatchStatsContainer');
    const meanContainer = document.getElementById('surveyQuestionMeanContainer');
    if (!selector || !container) return;

    const surveyId = selector.value;
    if (!surveyId) {
        container.innerHTML = '<div class="chart-empty-state"><i class="fas fa-chart-bar" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i><p>Select a survey above to view batch-separated response analytics</p></div>';
        if (meanContainer) {
            meanContainer.innerHTML = '<div class="chart-empty-state"><i class="fas fa-table" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i><p>Select a survey above to view the question and answer mean table</p></div>';
        }
        currentReportBatchStats = null;
        currentReportSurveyStats = null;
        surveyBatchStatsPage = 0; // Reset pagination
        return;
    }

    // Reset pagination when selecting new survey
    surveyBatchStatsPage = 0;

    container.innerHTML = '<div class="chart-empty-state"><i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px;"></i><p>Loading analytics...</p></div>';
    if (meanContainer) {
        meanContainer.innerHTML = '<div class="chart-empty-state"><i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px;"></i><p>Loading question mean table...</p></div>';
    }

    try {
        const [batchResponse, statsResponse] = await Promise.all([
            fetch(getSurveyBatchStatisticsUrl(surveyId), { credentials: 'include', cache: 'no-store' }),
            fetch(getSurveyStatisticsUrl(surveyId), { credentials: 'include', cache: 'no-store' }),
        ]);

        if (!batchResponse.ok) throw new Error('Failed to load batch statistics');
        if (!statsResponse.ok) throw new Error('Failed to load survey statistics');

        const batchResult = await batchResponse.json();
        const statsResult = await statsResponse.json();

        if (!batchResult.success || !batchResult.data) throw new Error(batchResult.message || 'No batch data available');
        if (!statsResult.success || !statsResult.data) throw new Error(statsResult.message || 'No survey data available');

        currentReportBatchStats = batchResult.data;
        currentReportSurveyStats = statsResult.data;
        renderSurveyBatchStats(currentReportBatchStats, currentReportSurveyStats);
    } catch (error) {
        container.innerHTML = `<div class="chart-empty-state" style="color: #dc3545;"><i class="fas fa-exclamation-circle" style="font-size: 48px; margin-bottom: 16px;"></i><p>Error loading analytics: ${escapeHtml(error instanceof Error ? error.message : String(error))}</p></div>`;
        if (meanContainer) {
            meanContainer.innerHTML = `<div class="chart-empty-state" style="color: #dc3545;"><i class="fas fa-exclamation-circle" style="font-size: 48px; margin-bottom: 16px;"></i><p>Error loading question mean table: ${escapeHtml(error instanceof Error ? error.message : String(error))}</p></div>`;
        }
    }
}

// Pagination state for survey batch stats
let surveyBatchStatsPage = 0;
const BATCHES_PER_PAGE = 5;

function navigateBatchStats(direction) {
    surveyBatchStatsPage += direction;
    if (surveyBatchStatsPage < 0) surveyBatchStatsPage = 0;
    // Re-render with current data
    if (currentReportBatchStats) {
        renderSurveyBatchStats(currentReportBatchStats, currentReportSurveyStats);
    }
}

function formatSurveyMean(value) {
    const numericValue = Number(value || 0);
    if (!Number.isFinite(numericValue)) return '0.00%';
    return `${numericValue.toFixed(2)}%`;
}

function buildSurveyAnswerPieChart(answerRows, totalResponses) {
    const rows = Array.isArray(answerRows) ? answerRows.filter((row) => Number(row?.responseCount || 0) > 0) : [];
    if (rows.length === 0) {
        return `
            <div class="chart-empty-state" style="min-height: 220px;">
                <p>No answer breakdown available for this question yet.</p>
            </div>
        `;
    }

    const palette = getChartPalette();
    let start = 0;
    const segments = rows.map((row, index) => {
        const responseCount = Number(row?.responseCount || 0);
        const size = totalResponses > 0 ? (responseCount / totalResponses) * 100 : 0;
        const end = start + size;
        const color = palette[index % palette.length];
        const segment = {
            answerText: String(row?.answerText || 'No label'),
            responseCount,
            mean: Number(row?.mean || 0),
            color,
            gradient: `${color} ${start}% ${end}%`
        };
        start = end;
        return segment;
    });

    const totalAnswered = segments.reduce((sum, segment) => sum + segment.responseCount, 0);

    return `
        <div class="pie-chart-layout survey-question-chart-layout">
            <div class="pie-chart-donut" style="background: conic-gradient(${segments.map((segment) => segment.gradient).join(', ')});">
                <div class="pie-chart-center">
                    <span class="pie-chart-total">${totalAnswered}</span>
                    <span class="pie-chart-label">Answers</span>
                </div>
            </div>
            <div class="status-legend chart-legend-scroll">
                <div class="distribution-chart-summary">Based on ${totalResponses} submitted response${totalResponses === 1 ? '' : 's'}.</div>
                ${segments.map((segment) => `
                    <div class="status-legend-item">
                        <span class="status-legend-swatch" style="background:${segment.color};"></span>
                        <div class="status-legend-copy">
                            <strong>${escapeHtml(segment.answerText)}</strong>
                            <span>${formatSurveyMean(segment.mean)} of all submissions</span>
                        </div>
                        <div class="status-legend-value">${segment.responseCount}</div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function buildSurveyQuestionMeanTableHtml(stats) {
    const totalResponses = Number(stats?.totalResponses || 0);
    const questions = Array.isArray(stats?.questions) ? stats.questions : [];

    if (questions.length === 0) {
        return `
            <div class="chart-empty-state" style="padding: 32px 16px;">
                <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 12px; opacity: 0.5;"></i>
                <p>No question analytics are available for this survey yet.</p>
            </div>
        `;
    }

    const categories = [];
    questions.forEach((question) => {
        const categoryName = String(question?.categoryName || 'Uncategorized');
        const existingCategory = categories.find((category) => category.name === categoryName);
        if (existingCategory) {
            existingCategory.questions.push(question);
            return;
        }

        categories.push({
            name: categoryName,
            questions: [question],
        });
    });

    const categoryHtml = categories.map((category) => `
        <div class="survey-category-block">
            <div class="survey-category-header">
                <div>
                    <div class="survey-category-label">Category</div>
                    <h4>${escapeHtml(category.name)}</h4>
                </div>
                <span class="survey-category-count">${category.questions.length} question${category.questions.length === 1 ? '' : 's'}</span>
            </div>
            <div class="survey-question-grid">
                ${category.questions.map((question) => {
                    const answerRows = Array.isArray(question?.answerRows) && question.answerRows.length > 0
                        ? question.answerRows
                        : [{
                            answerText: ['text', 'image'].includes(String(question?.questionType || '')) ? `${String(question?.questionType || '') === 'image' ? 'Image' : 'Text'} responses submitted` : 'No recorded answers',
                            responseCount: Number(question?.totalAnswered || question?.textCount || 0),
                            mean: Number(question?.mean || 0),
                        }];

                    return `
                        <article class="survey-question-card">
                            <div class="survey-question-card-header">
                                <div class="survey-question-number">Question</div>
                                <h5>${escapeHtml(String(question?.questionText || 'Untitled question'))}</h5>
                                <p>${Number(question?.totalAnswered || question?.textCount || 0)} answer${Number(question?.totalAnswered || question?.textCount || 0) === 1 ? '' : 's'} recorded</p>
                            </div>
                            ${buildSurveyAnswerPieChart(answerRows, totalResponses)}
                        </article>
                    `;
                }).join('')}
            </div>
        </div>
    `).join('');

    return `
        <div class="survey-question-analytics">
            <div class="survey-question-analytics-summary">
                <div class="section-card-filter-group">
                    <button type="button" class="section-card-filter-btn">All</button>
                    <button type="button" class="section-card-filter-btn">Member</button>
                    <button type="button" class="section-card-filter-btn">Not Member</button>
                </div>
                <span>Based on ${totalResponses} submitted response${totalResponses === 1 ? '' : 's'}</span>
            </div>
            ${categoryHtml}
        </div>
    `;
}

function renderSurveyQuestionMeanTable(stats) {
    const container = document.getElementById('surveyQuestionMeanContainer');
    if (!container) return;

    container.innerHTML = buildSurveyQuestionMeanTableHtml(stats);
}

function renderSurveyBatchStats(data, surveyStats = null) {
    const container = document.getElementById('surveyBatchStatsContainer');
    if (!container) return;

    const batches = Array.isArray(data?.batches) ? data.batches : [];
    if (batches.length === 0) {
        container.innerHTML = '<div class="chart-empty-state"><i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i><p>No batch data available for this survey</p></div>';
        renderSurveyQuestionMeanTable(surveyStats);
        return;
    }

    // Reset page if out of bounds
    const totalPages = Math.ceil(batches.length / BATCHES_PER_PAGE);
    if (surveyBatchStatsPage >= totalPages) surveyBatchStatsPage = 0;

    const palette = getChartPalette();

    // Calculate total responses across all batches
    const totalResponses = batches.reduce((sum, batch) => sum + Number(batch.respondentCount || 0), 0);

    // Get current page batches
    const startIndex = surveyBatchStatsPage * BATCHES_PER_PAGE;
    const currentBatches = batches.slice(startIndex, startIndex + BATCHES_PER_PAGE);

    // Prepare chart data
    const maxResponseRate = Math.max(...batches.map(b => Number(b.responseRate || 0)), 100);
    const yAxisMax = Math.max(Math.ceil(maxResponseRate / 25) * 25, 100);

    // Generate grid lines HTML
    let gridLinesHtml = '';
    for (let step = 0; step <= yAxisMax; step += 25) {
        const bottomPct = (step / yAxisMax) * 100;
        const isMajor = step % 50 === 0;
        const cls = isMajor ? 'vbar-grid-line vbar-grid-major' : 'vbar-grid-line vbar-grid-minor';
        gridLinesHtml += `<div class="${cls}" style="bottom: ${bottomPct}%;"></div>`;
    }

    // Generate Y-axis labels
    let yLabelsHtml = '';
    for (let step = yAxisMax; step >= 0; step -= 50) {
        yLabelsHtml += `<div class="vbar-y-label">${step}%</div>`;
    }

    // Generate bars HTML with color coding for current page
    let barsHtml = '';
    currentBatches.forEach(batch => {
        const responseRate = Number(batch.responseRate || 0);
        const heightPct = (responseRate / yAxisMax) * 100;
        const barGradient = responseRate >= 70
            ? 'linear-gradient(180deg, #1e4a76 0%, #4f89c2 100%)'
            : responseRate >= 40
                ? 'linear-gradient(180deg, #ffc107 0%, #ffdb5c 100%)'
                : 'linear-gradient(180deg, #dc3545 0%, #e85d75 100%)';

        barsHtml += `<div class="vbar-bar" style="height: ${heightPct}%; background: ${barGradient};" title="Batch ${escapeHtml(String(batch.batch))}: ${responseRate}% (${batch.respondentCount || 0}/${batch.totalAlumni || 0})"></div>`;
    });

    // Generate X-axis labels for current page with batch year, respondent/total count, and percentage
    let xLabelsHtml = '';
    currentBatches.forEach(batch => {
        const respondentCount = Number(batch.respondentCount || 0);
        const totalAlumni = Number(batch.totalAlumni || 0);
        const responseRate = Number(batch.responseRate || 0);
        const pctColor = responseRate >= 70 ? '#1e4a76' : responseRate >= 40 ? '#ffc107' : '#dc3545';
        xLabelsHtml += `
            <div class="vbar-x-label" style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                <span>${escapeHtml(String(batch.batch))}</span>
                <span style="font-size: 10px; color: #6a7b8c;">${respondentCount}/${totalAlumni}</span>
                <span style="font-size: 10px; color: ${pctColor}; font-weight: 600;">${responseRate}%</span>
            </div>
        `;
    });

    // Pagination controls
    const showPrev = surveyBatchStatsPage > 0;
    const showNext = surveyBatchStatsPage < totalPages - 1;
    const paginationHtml = totalPages > 1 ? `
        <div class="section-card" style="margin-top: 0; margin-bottom: 25px; padding: 16px;">
            <div style="display: flex; justify-content: center; align-items: center; gap: 16px;">
                <button class="btn-secondary" onclick="navigateBatchStats(-1)" ${!showPrev ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                    <i class="fas fa-chevron-left"></i> Back
                </button>
                <span style="font-size: 14px; color: #6a7b8c; font-weight: 500;">
                    Page ${surveyBatchStatsPage + 1} of ${totalPages}
                </span>
                <button class="btn-secondary" onclick="navigateBatchStats(1)" ${!showNext ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    ` : '';

    const barGraphHtml = `
        <div class="stats-grid" style="margin-bottom: 25px;">
            <div class="stat-card" style="background: linear-gradient(135deg, #1e4a76 0%, #0f3b5c 100%);">
                <div class="stat-info" style="color: white;">
                    <h3 style="color: white; font-size: 32px;">${totalResponses}</h3>
                    <p style="color: rgba(255,255,255,0.8);">Total Responses</p>
                </div>
                <div class="stat-icon" style="background: rgba(255,255,255,0.2); color: white;">
                    <i class="fas fa-reply"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>${batches.length}</h3>
                    <p>Graduation Batches</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>${Math.round(totalResponses / batches.length || 0)}</h3>
                    <p>Avg per Batch</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-calculator"></i>
                </div>
            </div>
        </div>

        <div class="section-card chart-card" style="margin-bottom: 0;">
            <div class="section-header">
                <h3><i class="fas fa-chart-bar"></i> Response Rate by Batch</h3>
                <div style="display: flex; gap: 16px; font-size: 12px;">
                    <span style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 12px; height: 12px; background: #1e4a76; border-radius: 2px;"></span>
                        <span style="color: #6a7b8c;">Excellent (≥70%)</span>
                    </span>
                    <span style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 12px; height: 12px; background: #ffc107; border-radius: 2px;"></span>
                        <span style="color: #6a7b8c;">Good (40-69%)</span>
                    </span>
                    <span style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 12px; height: 12px; background: #dc3545; border-radius: 2px;"></span>
                        <span style="color: #6a7b8c;">Low (<40%)</span>
                    </span>
                </div>
            </div>
            <div class="chart-shell">
                <div class="vertical-bar-chart">
                    <div class="vbar-y-axis">
                        <div class="vbar-y-labels">${yLabelsHtml}</div>
                        <div class="vbar-y-spacer"></div>
                    </div>
                    <div class="vbar-plot-wrap">
                        <div class="vbar-plot">
                            ${gridLinesHtml}
                            <div class="vbar-bars">${barsHtml}</div>
                        </div>
                        <div class="vbar-x-axis">${xLabelsHtml}</div>
                    </div>
                </div>
            </div>
        </div>
        ${paginationHtml}
    `;

    container.innerHTML = barGraphHtml;
    renderSurveyQuestionMeanTable(surveyStats);
}

// Load data when page loads - Only run on dashboard page
if (document.getElementById('totalAlumni')) {
    document.addEventListener('DOMContentLoaded', () => {
        loadDashboardData();
        setInterval(loadDashboardData, 30000);
    });
}

// Load report survey selector on Reports page
if (document.getElementById('reportSurveySelector')) {
    document.addEventListener('DOMContentLoaded', () => {
        loadReportSurveySelector();
    });
}

// Colleges Page Logic
let localCollegesData = [];

function getCollegesDataUrl() {
    return new URL('../staff/colleges-data', window.location.href).toString();
}

function getCreateCollegeUrl() {
    return new URL('../staff/colleges/create', window.location.href).toString();
}

function generateCollegeAbbreviation(name) {
    if (!name) return 'COL';
    const clean = String(name).trim();
    const words = clean.split(/\s+/).filter(w => !['of', 'and', 'the', 'for', 'in'].includes(w.toLowerCase()));
    if (words.length > 1) {
        return words.map(w => w[0].toUpperCase()).slice(0, 4).join('');
    }
    return clean.slice(0, 4).toUpperCase();
}

async function loadCollegesPage() {
    const tbody = document.getElementById('collegesTableBody');
    const statsContainer = document.getElementById('collegesStatsContainer');

    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="colleges-loading-cell">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Loading colleges directory...</span>
                </td>
            </tr>
        `;
    }
    if (statsContainer) {
        statsContainer.innerHTML = `
            <div class="colleges-loading-cell">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading statistics...</span>
            </div>
        `;
    }

    try {
        let response = await fetch(getCollegesDataUrl(), {
            credentials: 'include',
            cache: 'no-store'
        }).catch(() => null);

        if (!response || !response.ok) {
            response = await fetch(resolveStaffAppUrl('staff/colleges-data'), {
                credentials: 'include',
                cache: 'no-store'
            }).catch(() => null);
        }

        if (!response || !response.ok) {
            throw new Error(`Unable to load colleges (HTTP ${response ? response.status : 'error'}).`);
        }

        const data = await response.json();
        if (data.success && Array.isArray(data.colleges)) {
            localCollegesData = data.colleges;
            renderCollegesData(localCollegesData);
            setupCollegesSearch();
        } else {
            throw new Error(data.message || 'Unable to load colleges.');
        }
    } catch (error) {
        console.error('Error loading colleges data:', error);
        if (tbody) {
            tbody.innerHTML = `
                <tr><td colspan="5" class="colleges-loading-cell" style="color: #b91c1c;">
                    <i class="fas fa-circle-exclamation"></i>
                    <span>Failed to load colleges: ${escapeHtml(error.message || 'Network error')}</span>
                </td></tr>
            `;
        }
        if (statsContainer) {
            statsContainer.innerHTML = '<div class="colleges-loading-cell">No statistics available</div>';
        }
    }
}

function renderCollegesData(colleges) {
    const tbody = document.getElementById('collegesTableBody');
    const statsContainer = document.getElementById('collegesStatsContainer');
    if (!tbody) return;

    if (!Array.isArray(colleges) || colleges.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="colleges-loading-cell">
                    <i class="fas fa-building-columns"></i>
                    <span>No colleges found in the database.</span>
                </td>
            </tr>
        `;
        if (statsContainer) {
            statsContainer.innerHTML = '<div class="colleges-loading-cell">No colleges registered yet.</div>';
        }
        const badgeEl = document.getElementById('collegesCountBadge');
        if (badgeEl) badgeEl.textContent = '0 Colleges';
        return;
    }

    // Render Stats cards
    let totalColleges = colleges.length;
    let totalCourses = 0;
    let totalAlumni = 0;
    let totalActive = 0;

    colleges.forEach(col => {
        totalCourses += Number(col.totalCourses || col.totalDepartments || 0);
        totalAlumni += Number(col.totalAlumni || 0);
        totalActive += Number(col.activeAlumni || 0);
    });

    const totalCountEl = document.getElementById('totalCollegesCountStat');
    const totalCoursesEl = document.getElementById('totalCollegesCoursesStat');
    const totalAlumniEl = document.getElementById('totalCollegesAlumniStat');
    const totalActiveEl = document.getElementById('totalCollegesActiveStat');
    const activePercentBadge = document.getElementById('totalCollegesActivePercentBadge');
    const badgeEl = document.getElementById('collegesCountBadge');

    if (totalCountEl) totalCountEl.textContent = totalColleges.toLocaleString();
    if (totalCoursesEl) totalCoursesEl.textContent = totalCourses.toLocaleString();
    if (totalAlumniEl) totalAlumniEl.textContent = totalAlumni.toLocaleString();
    if (totalActiveEl) totalActiveEl.textContent = totalActive.toLocaleString();

    const overallActiveRate = totalAlumni > 0 ? Math.round((totalActive / totalAlumni) * 100) : 0;
    if (activePercentBadge) {
        activePercentBadge.textContent = `${overallActiveRate}% overall rate`;
    }
    if (badgeEl) {
        badgeEl.textContent = `${totalColleges} College${totalColleges === 1 ? '' : 's'}`;
    }

    // Render Table Body
    tbody.innerHTML = colleges.map(col => {
        const abbr = (col.abbreviation || generateCollegeAbbreviation(col.college)).toUpperCase();
        const deptsCount = Number(col.totalCourses || col.totalDepartments || 0);
        const alumniCount = Number(col.totalAlumni || 0);
        const activeCount = Number(col.activeAlumni || 0);
        const ratio = alumniCount > 0 ? Math.round((activeCount / alumniCount) * 100) : 0;

        let ratioClass = 'ratio-neutral';
        if (ratio >= 70) ratioClass = 'ratio-high';
        else if (ratio >= 40) ratioClass = 'ratio-medium';
        else if (alumniCount > 0) ratioClass = 'ratio-low';

        const coordCount = Array.isArray(col.coordinators) ? col.coordinators.length : 0;
        const coordTooltip = coordCount > 0 
            ? col.coordinators.map(c => c.name ? `${c.name} (${c.email || 'no email'})` : (c.email || 'Coordinator')).join(', ')
            : 'No coordinators assigned';

        const coordinatorsHtml = coordCount > 0
            ? `<span class="college-badge-coord" title="${escapeHtml(coordTooltip)}"><i class="fas fa-user-tie" aria-hidden="true"></i> <span>${coordCount} ${coordCount === 1 ? 'Coordinator' : 'Coordinators'}</span></span>`
            : `<span class="coord-unassigned-pill" title="No coordinators assigned"><i class="fas fa-user-xmark" aria-hidden="true"></i> <span>0 Coordinators</span></span>`;

        return `
            <tr class="college-row-item">
                <td>
                    <div class="college-cell-title">
                        <span class="college-avatar-badge">${escapeHtml(abbr.slice(0, 4))}</span>
                        <div>
                            <strong class="college-name">${escapeHtml(col.college)}</strong>
                            ${col.abbreviation ? `<span class="college-sub-code">${escapeHtml(col.abbreviation)}</span>` : ''}
                        </div>
                    </div>
                </td>
                <td>
                    <span class="college-badge-dept" title="${deptsCount} department(s) under this college">
                        <i class="fas fa-network-wired" aria-hidden="true"></i>
                        <span>${deptsCount} ${deptsCount === 1 ? 'dept' : 'depts'}</span>
                    </span>
                </td>
                <td>
                    <strong class="college-metric-num">${alumniCount.toLocaleString()}</strong>
                </td>
                <td>
                    <div class="college-ratio-stack" title="${activeCount} active of ${alumniCount} alumni (${ratio}%)">
                        <span class="college-ratio-pill ${ratioClass}">${ratio}%</span>
                        <div class="college-mini-bar">
                            <div class="college-mini-fill ${ratioClass}" style="width: ${ratio}%;"></div>
                        </div>
                    </div>
                </td>
                <td>${coordinatorsHtml}</td>
            </tr>
        `;
    }).join('');

    // Render Stats Module Progress Cards
    if (statsContainer) {
        statsContainer.innerHTML = colleges.map(col => {
            const abbr = (col.abbreviation || generateCollegeAbbreviation(col.college)).toUpperCase();
            const deptsCount = Number(col.totalCourses || col.totalDepartments || 0);
            const alumniCount = Number(col.totalAlumni || 0);
            const activeCount = Number(col.activeAlumni || 0);
            const ratio = alumniCount > 0 ? Math.round((activeCount / alumniCount) * 100) : 0;

            let ratioClass = 'ratio-neutral';
            if (ratio >= 70) ratioClass = 'ratio-high';
            else if (ratio >= 40) ratioClass = 'ratio-medium';
            else if (alumniCount > 0) ratioClass = 'ratio-low';

            return `
                <div class="college-stat-panel">
                    <div class="college-stat-panel-top">
                        <div class="college-stat-panel-info">
                            <span class="college-stat-code">${escapeHtml(abbr.slice(0, 5))}</span>
                            <strong class="college-stat-name" title="${escapeHtml(col.college)}">${escapeHtml(col.college)}</strong>
                        </div>
                        <span class="college-ratio-pill ${ratioClass}">${ratio}% Active</span>
                    </div>
                    <div class="college-stat-track" title="${ratio}% active rate">
                        <div class="college-stat-fill ${ratioClass}" style="width: ${ratio}%;"></div>
                    </div>
                    <div class="college-stat-meta">
                        <span class="college-stat-pill meta-active" title="${activeCount.toLocaleString()} active alumni">
                            <i class="fas fa-user-check" aria-hidden="true"></i>
                            <span><strong>${activeCount.toLocaleString()}</strong> active</span>
                        </span>
                        <span class="college-stat-pill meta-alumni" title="${alumniCount.toLocaleString()} total alumni">
                            <i class="fas fa-user-graduate" aria-hidden="true"></i>
                            <span><strong>${alumniCount.toLocaleString()}</strong> alumni</span>
                        </span>
                        <span class="college-stat-pill meta-depts" title="${deptsCount} department(s)">
                            <i class="fas fa-network-wired" aria-hidden="true"></i>
                            <span><strong>${deptsCount}</strong> ${deptsCount === 1 ? 'dept' : 'depts'}</span>
                        </span>
                    </div>
                </div>
            `;
        }).join('');
    }
}

function setupCollegesSearch() {
    const searchInput = document.getElementById('collegeSearch');
    if (!searchInput || searchInput.dataset.bound === 'true') return;
    searchInput.dataset.bound = 'true';

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.college-row-item');
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const coordTooltip = (row.querySelector('.college-badge-coord')?.getAttribute('title') || '').toLowerCase();
            if (!query || text.includes(query) || coordTooltip.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const tbody = document.getElementById('collegesTableBody');
        let emptySearchRow = document.getElementById('collegeSearchEmptyRow');
        if (visibleCount === 0 && rows.length > 0) {
            if (!emptySearchRow && tbody) {
                emptySearchRow = document.createElement('tr');
                emptySearchRow.id = 'collegeSearchEmptyRow';
                emptySearchRow.innerHTML = `
                    <td colspan="5" class="colleges-loading-cell">
                        <i class="fas fa-magnifying-glass"></i>
                        <span>No colleges match "${escapeHtml(query)}".</span>
                    </td>
                `;
                tbody.appendChild(emptySearchRow);
            }
        } else if (emptySearchRow) {
            emptySearchRow.remove();
        }

        // Also filter stats cards on the right
        const statPanels = document.querySelectorAll('.college-stat-panel');
        statPanels.forEach(panel => {
            const text = panel.textContent.toLowerCase();
            panel.style.display = (!query || text.includes(query)) ? '' : 'none';
        });
    });
}

function openAddCollegeModal() {
    const modal = document.getElementById('addCollegeModal');
    if (modal) {
        modal.style.display = 'flex';
        const nameInput = document.getElementById('collegeName');
        if (nameInput) setTimeout(() => nameInput.focus(), 60);
    }
}

function closeAddCollegeModal() {
    const modal = document.getElementById('addCollegeModal');
    const form = document.getElementById('addCollegeForm');
    if (modal) modal.style.display = 'none';
    if (form) form.reset();
}

async function submitCollegeForm() {
    const collegeNameInput = document.getElementById('collegeName');
    const abbreviationInput = document.getElementById('collegeAbbreviation');
    const submitBtn = document.getElementById('addCollegeSubmitBtn');

    const collegeName = collegeNameInput ? collegeNameInput.value.trim() : '';
    const abbreviation = abbreviationInput ? abbreviationInput.value.trim() : '';

    if (!collegeName) {
        alert('Please enter a college name.');
        if (collegeNameInput) collegeNameInput.focus();
        return;
    }

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Creating...</span>';
    }

    const formData = new FormData();
    formData.append('college_name', collegeName);
    formData.append('abbreviation', abbreviation);

    try {
        let response;
        try {
            response = await fetch(getCreateCollegeUrl(), {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
        } catch (e) {
            response = await fetch(resolveStaffAppUrl('staff/colleges/create'), {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
        }

        const result = await response.json();

        if (result.success) {
            closeAddCollegeModal();
            await loadCollegesPage();
        } else {
            alert('Error: ' + (result.message || 'Failed to create college.'));
        }
    } catch (error) {
        console.error('Error creating college:', error);
        alert('Error: Failed to create college. Please try again.');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-plus"></i><span>Create College</span>';
        }
    }
}

function openAddDepartmentModal() {
    const modal = document.getElementById('addDepartmentModal');
    if (modal) {
        modal.style.display = 'flex';
        loadCollegesForDepartmentModal();
    }
}

function closeAddDepartmentModal() {
    const modal = document.getElementById('addDepartmentModal');
    const form = document.getElementById('addDepartmentForm');
    if (modal) modal.style.display = 'none';
    if (form) form.reset();
}

async function loadCollegesForDepartmentModal() {
    const select = document.getElementById('departmentCollege');
    if (!select) return;

    try {
        const response = await fetch(resolveStaffAppUrl('staff/colleges-data'));
        const result = await response.json();

        if (result.success && result.colleges) {
            select.innerHTML = '<option value="">Select a college...</option>';
            result.colleges.forEach(college => {
                const option = document.createElement('option');
                option.value = college.college_id;
                option.textContent = college.college;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading colleges:', error);
    }
}

async function submitDepartmentForm() {
    const departmentName = document.getElementById('departmentName').value.trim();
    const collegeId = document.getElementById('departmentCollege').value;
    const abbreviation = document.getElementById('departmentAbbreviation').value.trim();

    if (!departmentName) {
        alert('Please enter a department name.');
        return;
    }

    if (!collegeId) {
        alert('Please select a college.');
        return;
    }

    const formData = new FormData();
    formData.append('department_name', departmentName);
    formData.append('college_id', collegeId);
    formData.append('abbreviation', abbreviation);

    try {
        const response = await fetch(resolveStaffAppUrl('staff/departments/create'), {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert('Department created successfully!');
            closeAddDepartmentModal();
            // Reload the departments data
            if (typeof loadDepartmentsPage === 'function') {
                loadDepartmentsPage();
            }
        } else {
            alert('Error: ' + (result.message || 'Failed to create department'));
        }
    } catch (error) {
        console.error('Error creating department:', error);
        alert('Error: Failed to create department. Please try again.');
    }
}

// Departments Page Logic
let localDepartmentsData = [];
async function loadDepartmentsPage() {
    try {
        const departmentsUrl = resolveStaffAppUrl('staff/departments-data');
        console.log('[DEBUG] Attempting to fetch from:', departmentsUrl);
        console.log('[DEBUG] Current page URL:', window.location.href);
        const response = await fetch(departmentsUrl);
        console.log('[DEBUG] Response status:', response.status);
        console.log('[DEBUG] Response ok:', response.ok);
        console.log('[DEBUG] Response URL:', response.url);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json();
        console.log('[DEBUG] Response data:', data);
        if (data.success) {
            localDepartmentsData = Array.isArray(data.departments) ? data.departments : [];
            populateDepartmentCollegeFilter(localDepartmentsData);
            renderDepartmentsData(localDepartmentsData);
            setupDepartmentsSearch();
        } else {
            const tbody = document.getElementById('departmentsTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr><td colspan="2" style="text-align: center; padding: 25px; color: #dc3545;">Error: ${escapeHtml(data.message || 'Failed to load departments')}</td></tr>
                `;
            }
        }
    } catch (error) {
        console.error('[DEBUG] Error loading departments data:', error);
        console.error('[DEBUG] Error details:', error.message, error.stack);
        const tbody = document.getElementById('departmentsTableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr><td colspan="2" style="text-align: center; padding: 25px; color: #dc3545;">Failed to load departments data: ${escapeHtml(error.message)}</td></tr>
            `;
        }
    }
}

function populateDepartmentCollegeFilter(departments) {
    const select = document.getElementById('deptCollegeFilter');
    if (!select || !Array.isArray(departments)) return;

    const currentVal = select.value;
    const colleges = Array.from(new Set(departments.map(d => d.college).filter(Boolean))).sort();
    select.innerHTML = '<option value="all">All Colleges</option>';
    colleges.forEach(col => {
        const opt = document.createElement('option');
        opt.value = col;
        opt.textContent = col;
        select.appendChild(opt);
    });
    if (colleges.includes(currentVal)) {
        select.value = currentVal;
    }
}

function renderDepartmentsData(departments) {
    const tbody = document.getElementById('departmentsTableBody');
    if (!tbody) return;

    const countBadge = document.getElementById('departmentsCountBadge');
    const totalDepts = Array.isArray(departments) ? departments.length : 0;
    if (countBadge) {
        countBadge.textContent = `${totalDepts} Department${totalDepts === 1 ? '' : 's'}`;
    }

    if (!Array.isArray(departments) || departments.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="2" class="colleges-loading-cell">
                    <i class="fas fa-network-wired"></i>
                    <span>No departments found.</span>
                </td>
            </tr>
        `;
        return;
    }

    // Render Stats if elements exist in DOM
    let totalCoordinators = 0;
    let totalUsers = 0;
    departments.forEach(dept => {
        totalCoordinators += Number(dept.totalCoordinators !== undefined ? dept.totalCoordinators : (Array.isArray(dept.coordinators) ? dept.coordinators.length : 0));
        totalUsers += Number(dept.totalUsers || 0);
    });

    const totalDeptsEl = document.getElementById('totalDeptsCountStat');
    const totalCoordinatorsEl = document.getElementById('totalDeptsCoordinatorsStat');
    const totalUsersEl = document.getElementById('totalDeptsUsersStat');

    if (totalDeptsEl) totalDeptsEl.textContent = totalDepts;
    if (totalCoordinatorsEl) totalCoordinatorsEl.textContent = totalCoordinators;
    if (totalUsersEl) totalUsersEl.textContent = totalUsers;

    // Render Table Body: Only Department and Coordinator columns
    tbody.innerHTML = departments.map((dept, index) => {
        const coordCount = Number(dept.totalCoordinators !== undefined ? dept.totalCoordinators : (Array.isArray(dept.coordinators) ? dept.coordinators.length : 0));
        const coordTooltip = coordCount > 0 && Array.isArray(dept.coordinators)
            ? dept.coordinators.map(c => c.name ? `${c.name} (${c.email || 'no email'})` : (c.email || 'Coordinator')).join(', ')
            : '0 coordinators assigned';

        const coordDisplay = coordCount > 0
            ? `<span class="college-badge-coord" title="${escapeHtml(coordTooltip)}"><i class="fas fa-user-tie" aria-hidden="true"></i> <span>${coordCount}</span></span>`
            : `<span class="coord-unassigned-pill" title="0 coordinators assigned"><i class="fas fa-user-xmark" aria-hidden="true"></i> <span>0</span></span>`;

        return `
            <tr class="dept-row-item" id="dept-row-${index}">
                <td>
                    <div class="college-cell-title">
                        <span class="college-avatar-badge"><i class="fas fa-network-wired" style="font-size: 13px;"></i></span>
                        <div>
                            <strong class="college-name">${escapeHtml(dept.department || 'Unnamed Department')}</strong>
                            ${dept.college ? `<span class="college-sub-code">${escapeHtml(dept.college)}</span>` : ''}
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    ${coordDisplay}
                </td>
            </tr>
        `;
    }).join('');
}

function filterDepartmentRows() {
    const searchInput = document.getElementById('deptSearch');
    const filterSelect = document.getElementById('deptCollegeFilter');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const selectedCollege = filterSelect ? filterSelect.value : 'all';

    const rows = document.querySelectorAll('.dept-row-item');
    let visibleCount = 0;

    rows.forEach((row, index) => {
        const dept = localDepartmentsData[index];
        const deptName = (dept && dept.department ? dept.department : (row.cells[0]?.textContent || '')).toLowerCase();
        const deptCollege = dept && dept.college ? dept.college : '';
        const matchesCollege = (selectedCollege === 'all') || (deptCollege === selectedCollege);

        let matchesQuery = !query || deptName.includes(query) || deptCollege.toLowerCase().includes(query);
        if (!matchesQuery && dept && Array.isArray(dept.coordinators)) {
            matchesQuery = dept.coordinators.some(c => 
                (c.name && c.name.toLowerCase().includes(query)) || 
                (c.email && c.email.toLowerCase().includes(query))
            );
        }

        if (matchesCollege && matchesQuery) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const countBadge = document.getElementById('departmentsCountBadge');
    if (countBadge) {
        countBadge.textContent = `${visibleCount} Department${visibleCount === 1 ? '' : 's'}`;
    }
}

function setupDepartmentsSearch() {
    const searchInput = document.getElementById('deptSearch');
    const filterSelect = document.getElementById('deptCollegeFilter');

    if (searchInput && !searchInput.dataset.bound) {
        searchInput.dataset.bound = 'true';
        searchInput.addEventListener('input', filterDepartmentRows);
    }
    if (filterSelect && !filterSelect.dataset.bound) {
        filterSelect.dataset.bound = 'true';
        filterSelect.addEventListener('change', filterDepartmentRows);
    }
}

function toggleDeptMembers(index) {
    // Deprecated: members detail row removed in simplified directory view
}
