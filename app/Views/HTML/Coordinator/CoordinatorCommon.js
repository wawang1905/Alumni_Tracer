// ── Coordinator Logout ──
function getCoordinatorLogoutUrl() {
    return new URL('../auth/logout', window.location.href).toString();
}

async function coordinatorLogout() {
    const btn = document.getElementById('coordinatorLogoutBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>Logging out…</span>';
    }
    try {
        const response = await fetch(getCoordinatorLogoutUrl(), {
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
    document.getElementById('coordinatorLogoutBtn')?.addEventListener('click', coordinatorLogout);
});

// ── Coordinator Sidebar Collapse (mirrors Staff sidebar behavior) ──
function initCoordinatorSidebarCollapse() {
    const sidebar = document.querySelector('.coordinator-sidebar');
    const brandBlock = document.querySelector('.brand-block');
    if (!sidebar || !brandBlock) return;

    // Add title attributes to nav items for collapsed tooltips
    document.querySelectorAll('.nav-item').forEach((item) => {
        const label = item.querySelector('span');
        if (label && !item.title) {
            item.title = label.textContent.trim();
        }
    });

    const desktopQuery = window.matchMedia('(min-width: 981px)');

    const applyState = () => {
        const shouldCollapse = desktopQuery.matches && localStorage.getItem('coordinatorSidebarCollapsed') === 'true';
        document.documentElement.classList.toggle('coordinator-sidebar-collapsed', shouldCollapse);
        const btn = document.querySelector('.coordinator-sidebar-toggle');
        if (btn) {
            btn.setAttribute('aria-label', shouldCollapse ? 'Expand sidebar' : 'Collapse sidebar');
            btn.setAttribute('aria-expanded', String(!shouldCollapse));
        }
    };

    if (brandBlock.querySelector('.coordinator-sidebar-toggle')) {
        applyState();
        requestAnimationFrame(() => requestAnimationFrame(() => {
            document.documentElement.classList.remove('coordinator-sidebar-preload');
        }));
        return;
    }

    const toggleButton = document.createElement('button');
    toggleButton.type = 'button';
    toggleButton.className = 'coordinator-sidebar-toggle';
    toggleButton.setAttribute('aria-label', 'Collapse sidebar');
    toggleButton.setAttribute('aria-expanded', 'true');
    toggleButton.innerHTML = '<i class="fas fa-angle-left" aria-hidden="true"></i>';
    brandBlock.appendChild(toggleButton);

    toggleButton.addEventListener('click', () => {
        if (!desktopQuery.matches) return;
        document.documentElement.classList.remove('coordinator-sidebar-preload');
        const nextValue = !document.documentElement.classList.contains('coordinator-sidebar-collapsed');
        localStorage.setItem('coordinatorSidebarCollapsed', String(nextValue));
        applyState();
    });

    if (typeof desktopQuery.addEventListener === 'function') {
        desktopQuery.addEventListener('change', applyState);
    } else {
        desktopQuery.addListener(applyState);
    }

    applyState();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            document.documentElement.classList.remove('coordinator-sidebar-preload');
        });
    });
}

document.addEventListener('DOMContentLoaded', initCoordinatorSidebarCollapse);
document.addEventListener('DOMContentLoaded', () => {
    const navItems = document.querySelectorAll('.nav-item[data-section]');
    const sections = document.querySelectorAll('.workspace-section');

    navItems.forEach((item) => {
        item.addEventListener('click', () => {
            const target = item.dataset.section;

            navItems.forEach((navItem) => navItem.classList.remove('active'));
            sections.forEach((section) => section.classList.remove('active'));

            item.classList.add('active');
            document.getElementById(target)?.classList.add('active');
        });
    });

    void loadCoordinatorSessionProfile();
    void initializeCoordinatorSurveyResponses();
    void initializeCoordinatorResponseDetails();
});

async function loadCoordinatorSessionProfile() {
    try {
        const res = await fetch(coordinatorApiUrl('coordinator/session'), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) return null;
        const data = await res.json();
        if (!data?.success) return null;

        const name = String(data.name || '').trim() || 'Coordinator';
        const dept = String(data.department || '').trim();
        const college = String(data.college || '').trim();

        document.querySelectorAll('.sidebar-user-name').forEach((el) => {
            el.textContent = name;
        });
        document.querySelectorAll('.sidebar-user-role').forEach((el) => {
            el.textContent = college || 'Unassigned College';
            if (college) el.title = college;
        });

        const lockedDeptEl = document.getElementById('coordLockedDeptLabel');
        if (lockedDeptEl) {
            lockedDeptEl.textContent = college || 'Unassigned College';
        }

        const topbarTitleEl = document.getElementById('topbarTitle');
        const topbarCollegeEyebrow = document.getElementById('topbarCollegeEyebrow');
        if (topbarCollegeEyebrow) {
            topbarCollegeEyebrow.textContent = college ? `${college} · Assigned College` : 'Assigned College';
        }
        if (topbarTitleEl && college && document.getElementById('graduatesYearChart')) {
            topbarTitleEl.textContent = college;
        }

        const topbarDeptEl = document.getElementById('topbarDept');
        if (topbarDeptEl && college) {
            topbarDeptEl.textContent = college;
        }

        const topbarEyebrow = document.querySelector('.coordinator-main > .topbar .eyebrow');
        if (topbarEyebrow && college && !topbarEyebrow.id) {
            topbarEyebrow.textContent = college;
        }

        return data;
    } catch {
        return null;
    }
}

let coordinatorSurveys = [];
let coordinatorResponses = [];
let coordinatorSelectedSurvey = null;
let coordinatorResponsePage = 1;
const coordinatorResponsesPerPage = 7;

function responseInitials(name) {
    return String(name).split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

function coordinatorApiUrl(path) {
    return new URL(`../${String(path).replace(/^\/+/, '')}`, window.location.href).toString();
}

function escapeCoordinatorHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatCoordinatorDateTime(value) {
    const date = new Date(String(value || '').replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value || 'Date unavailable');
    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
    }).format(date);
}

function formatCoordinatorDate(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(date);
}

function getCoordinatorResponseRate(response) {
    const suppliedRate = Number(response?.completionRate);
    return Number.isFinite(suppliedRate) ? Math.max(0, Math.min(100, Math.round(suppliedRate))) : 100;
}

function getCoordinatorResponseStatus(response) {
    return getCoordinatorResponseRate(response) >= 100 ? 'Completed' : 'In Progress';
}

async function fetchCoordinatorJson(path) {
    const response = await fetch(coordinatorApiUrl(path), {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
    });
    let result = null;
    try {
        result = await response.json();
    } catch (error) {
        throw new Error('The server returned an invalid response.');
    }
    if (!response.ok || !result?.success) {
        throw new Error(result?.message || 'Unable to load survey data.');
    }
    return result;
}

async function initializeCoordinatorSurveyResponses() {
    const tbody = document.getElementById('responsesTableBody');
    if (!tbody) return;

    const surveySelect = document.getElementById('coordinatorSurveySelect');
    document.getElementById('coordinatorResponseSearch')?.addEventListener('input', () => {
        coordinatorResponsePage = 1;
        renderCoordinatorSurveyResponses();
    });
    document.getElementById('applyResponseFilters')?.addEventListener('click', () => {
        coordinatorResponsePage = 1;
        renderCoordinatorSurveyResponses();
    });
    document.getElementById('resetResponseFilters')?.addEventListener('click', resetCoordinatorResponseFilters);
    document.getElementById('exportResponsesButton')?.addEventListener('click', exportCoordinatorResponses);
    document.getElementById('coordinatorResponsePagination')?.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-page]');
        if (!button) return;
        coordinatorResponsePage = Number(button.dataset.page) || 1;
        renderCoordinatorSurveyResponses();
    });
    surveySelect?.addEventListener('change', () => void loadCoordinatorSurveyResponses(Number(surveySelect.value)));

    try {
        const result = await fetchCoordinatorJson('surveys/list');
        coordinatorSurveys = Array.isArray(result.data) ? result.data : [];
        if (coordinatorSurveys.length === 0) {
            surveySelect.innerHTML = '<option value="">No surveys available</option>';
            renderCoordinatorResponseState('No surveys available', 'Create and publish a survey before reviewing responses.');
            updateCoordinatorResponseStats([]);
            return;
        }

        surveySelect.innerHTML = coordinatorSurveys.map((survey) =>
            `<option value="${Number(survey.id)}">${escapeCoordinatorHtml(survey.title || `Survey ${survey.id}`)}</option>`
        ).join('');
        surveySelect.disabled = false;
        const requestedSurveyId = Number(new URLSearchParams(window.location.search).get('surveyId'));
        const initialSurvey = coordinatorSurveys.find((survey) => Number(survey.id) === requestedSurveyId) || coordinatorSurveys[0];
        surveySelect.value = String(initialSurvey.id);
        await loadCoordinatorSurveyResponses(Number(initialSurvey.id));
    } catch (error) {
        surveySelect.innerHTML = '<option value="">Unable to load surveys</option>';
        renderCoordinatorResponseState('Unable to load responses', error.message);
        updateCoordinatorResponseStats([]);
    }
}

async function loadCoordinatorSurveyResponses(surveyId) {
    const tbody = document.getElementById('responsesTableBody');
    if (!tbody || !surveyId) return;
    tbody.innerHTML = '<tr><td colspan="10" class="coordinator-table-state">Loading survey responses…</td></tr>';
    coordinatorSelectedSurvey = coordinatorSurveys.find((survey) => Number(survey.id) === Number(surveyId)) || null;
    coordinatorResponsePage = 1;

    try {
        const result = await fetchCoordinatorJson(`surveys/${encodeURIComponent(surveyId)}/responses`);
        coordinatorSelectedSurvey = result.survey || coordinatorSelectedSurvey;
        coordinatorResponses = Array.isArray(result.responses) ? result.responses : [];
        const url = new URL(window.location.href);
        url.searchParams.set('surveyId', String(surveyId));
        window.history.replaceState({}, '', url);
        renderCoordinatorSurveyResponses();
    } catch (error) {
        coordinatorResponses = [];
        renderCoordinatorResponseState('Unable to load responses', error.message);
        updateCoordinatorResponseStats([]);
    }
}

function getFilteredCoordinatorResponses() {
    const query = String(document.getElementById('coordinatorResponseSearch')?.value || '').trim().toLowerCase();
    const status = String(document.getElementById('coordinatorStatusFilter')?.value || 'all');
    const dateFrom = document.getElementById('coordinatorDateFrom')?.value || '';
    const dateTo = document.getElementById('coordinatorDateTo')?.value || '';
    const start = dateFrom ? new Date(`${dateFrom}T00:00:00`).getTime() : null;
    const end = dateTo ? new Date(`${dateTo}T23:59:59.999`).getTime() : null;

    return coordinatorResponses.filter((response) => {
        const searchable = [response.firstName, response.lastName, response.alumniName, response.userName, response.respondentEmail, response.alumniRecordId, response.college, response.course]
            .map((value) => String(value || '').toLowerCase()).join(' ');
        const responseStatus = getCoordinatorResponseStatus(response) === 'Completed' ? 'completed' : 'progress';
        const submittedTime = new Date(String(response.submittedAt || '').replace(' ', 'T')).getTime();
        return (!query || searchable.includes(query))
            && (status === 'all' || status === responseStatus)
            && (start === null || (!Number.isNaN(submittedTime) && submittedTime >= start))
            && (end === null || (!Number.isNaN(submittedTime) && submittedTime <= end));
    });
}

function renderCoordinatorSurveyResponses() {
    const tbody = document.getElementById('responsesTableBody');
    if (!tbody) return;
    const filtered = getFilteredCoordinatorResponses();
    updateCoordinatorResponseStats(filtered);

    if (filtered.length === 0) {
        const hasResponses = coordinatorResponses.length > 0;
        renderCoordinatorResponseState(
            hasResponses ? 'No matching responses' : 'No responses yet',
            hasResponses ? 'Try changing or resetting the filters.' : 'Submitted alumni responses for this survey will appear here.'
        );
        return;
    }

    const totalPages = Math.max(1, Math.ceil(filtered.length / coordinatorResponsesPerPage));
    coordinatorResponsePage = Math.min(coordinatorResponsePage, totalPages);
    const startIndex = (coordinatorResponsePage - 1) * coordinatorResponsesPerPage;
    const visible = filtered.slice(startIndex, startIndex + coordinatorResponsesPerPage);

    tbody.innerHTML = visible.map((response, index) => {
        const name = response.alumniName || response.userName || 'Alumni respondent';
        const firstName = response.firstName || String(name).split(/\s+/)[0] || '—';
        const lastName = response.lastName || String(name).split(/\s+/).slice(1).join(' ') || '—';
        const email = response.respondentEmail || 'Email unavailable';
        const status = getCoordinatorResponseStatus(response);
        const rate = getCoordinatorResponseRate(response);
        const statusClass = status === 'Completed' ? 'completed' : 'progress';
        return `
            <tr>
                <td>${startIndex + index + 1}</td>
                <td>${escapeCoordinatorHtml(firstName)}</td>
                <td>${escapeCoordinatorHtml(lastName)}</td>
                <td>${escapeCoordinatorHtml(email)}</td>
                <td>${escapeCoordinatorHtml(response.graduationYear || '—')}</td>
                <td><span class="response-badge ${statusClass}">${escapeCoordinatorHtml(status)}</span></td>
                <td>${escapeCoordinatorHtml(formatCoordinatorDateTime(response.submittedAt))}</td>
                <td><div class="rate-cell"><span><i style="width:${rate}%"></i></span>${rate}%</div></td>
                <td><a class="view-btn" href="SurveyResponseDetails.html?responseId=${Number(response.id)}&surveyId=${Number(coordinatorSelectedSurvey?.id || response.surveyId)}"><i class="far fa-eye"></i> View</a></td>
                <td><button class="dots-btn" type="button"><i class="fas fa-ellipsis-vertical"></i></button></td>
            </tr>
        `;
    }).join('');

    renderCoordinatorPagination(filtered.length, startIndex, visible.length);
}

function renderCoordinatorResponseState(title, message) {
    const tbody = document.getElementById('responsesTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="10" class="coordinator-table-state"><strong>${escapeCoordinatorHtml(title)}</strong><span>${escapeCoordinatorHtml(message)}</span></td></tr>`;
    }
    const count = document.getElementById('coordinatorResponseCount');
    if (count) count.textContent = 'Showing 0 entries';
    const pagination = document.getElementById('coordinatorResponsePagination');
    if (pagination) pagination.innerHTML = '';
}

function renderCoordinatorPagination(total, startIndex, visibleCount) {
    const count = document.getElementById('coordinatorResponseCount');
    if (count) count.textContent = `Showing ${startIndex + 1} to ${startIndex + visibleCount} of ${total} entries`;
    const pagination = document.getElementById('coordinatorResponsePagination');
    if (!pagination) return;
    const totalPages = Math.ceil(total / coordinatorResponsesPerPage);
    const pages = [];
    for (let page = 1; page <= totalPages; page += 1) {
        if (page === 1 || page === totalPages || Math.abs(page - coordinatorResponsePage) <= 1) pages.push(page);
    }
    let previous = 0;
    const pageButtons = pages.map((page) => {
        const gap = previous && page - previous > 1 ? '<span aria-hidden="true">…</span>' : '';
        previous = page;
        return `${gap}<button type="button" data-page="${page}" class="${page === coordinatorResponsePage ? 'active' : ''}" aria-current="${page === coordinatorResponsePage ? 'page' : 'false'}">${page}</button>`;
    }).join('');
    pagination.innerHTML = `<button type="button" data-page="${coordinatorResponsePage - 1}" ${coordinatorResponsePage === 1 ? 'disabled' : ''}>Previous</button>${pageButtons}<button type="button" data-page="${coordinatorResponsePage + 1}" ${coordinatorResponsePage === totalPages ? 'disabled' : ''}>Next</button>`;
}

function updateCoordinatorResponseStats(responses) {
    const total = responses.length;
    const complete = responses.filter((response) => getCoordinatorResponseStatus(response) === 'Completed').length;
    const progress = total - complete;
    const average = total ? Math.round(responses.reduce((sum, response) => sum + getCoordinatorResponseRate(response), 0) / total) : 0;
    const setText = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = value; };
    setText('totalResponsesStat', String(total));
    setText('completeResponsesStat', String(complete));
    setText('progressResponsesStat', String(progress));
    setText('completeResponsesRate', `${total ? Math.round((complete / total) * 100) : 0}%`);
    setText('progressResponsesRate', `${total ? Math.round((progress / total) * 100) : 0}%`);
    setText('averageCompletionStat', `${average}%`);
}

function resetCoordinatorResponseFilters() {
    ['coordinatorResponseSearch', 'coordinatorDateFrom', 'coordinatorDateTo'].forEach((id) => {
        const element = document.getElementById(id);
        if (element) element.value = '';
    });
    const status = document.getElementById('coordinatorStatusFilter');
    if (status) status.value = 'all';
    coordinatorResponsePage = 1;
    renderCoordinatorSurveyResponses();
}

function downloadCoordinatorCsv(filename, rows) {
    const csv = rows.map((row) => row.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function exportCoordinatorResponses() {
    const responses = getFilteredCoordinatorResponses();
    if (responses.length === 0) return;
    const rows = [['First Name', 'Last Name', 'Email', 'Alumni ID', 'College', 'Department', 'Graduation Year', 'Status', 'Submitted At']];
    responses.forEach((response) => rows.push([
        response.firstName || '', response.lastName || '', response.respondentEmail || '', response.alumniRecordId || '',
        response.college || '', response.course || '', response.graduationYear || '', getCoordinatorResponseStatus(response), response.submittedAt || ''
    ]));
    const title = String(coordinatorSelectedSurvey?.title || 'survey-responses').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase();
    downloadCoordinatorCsv(`${title || 'survey-responses'}.csv`, rows);
}

async function initializeCoordinatorResponseDetails() {
    const list = document.getElementById('responseListItems');
    if (!list) return;

    const params = new URLSearchParams(window.location.search);
    const responseId = Number(params.get('responseId'));
    if (!responseId) {
        renderCoordinatorDetailError('No survey response was selected.');
        return;
    }

    try {
        const detailResult = await fetchCoordinatorJson(`surveys/responses/${encodeURIComponent(responseId)}`);
        const active = detailResult.response;
        const survey = detailResult.survey;
        const listResult = await fetchCoordinatorJson(`surveys/${encodeURIComponent(active.surveyId)}/responses`);
        coordinatorSelectedSurvey = survey;
        coordinatorResponses = Array.isArray(listResult.responses) ? listResult.responses : [];
        renderCoordinatorResponseList(active.id);
        renderCoordinatorResponseDetail(active, survey);
        document.getElementById('detailResponseSearch')?.addEventListener('input', () => renderCoordinatorResponseList(active.id));
        document.getElementById('exportSingleResponse')?.addEventListener('click', () => exportCoordinatorSingleResponse(active, survey));
        const activeIndex = coordinatorResponses.findIndex((response) => Number(response.id) === Number(active.id));
        configureCoordinatorDetailNavigation(activeIndex);
    } catch (error) {
        renderCoordinatorDetailError(error.message);
    }
}

function renderCoordinatorResponseList(activeId) {
    const list = document.getElementById('responseListItems');
    if (!list) return;
    const query = String(document.getElementById('detailResponseSearch')?.value || '').trim().toLowerCase();
    const visible = coordinatorResponses.filter((response) => [response.alumniName, response.userName, response.respondentEmail]
        .map((value) => String(value || '').toLowerCase()).join(' ').includes(query));

    list.innerHTML = visible.length ? visible.map((response) => {
        const name = response.alumniName || response.userName || 'Alumni respondent';
        const status = getCoordinatorResponseStatus(response);
        const rate = getCoordinatorResponseRate(response);
        const statusClass = status === 'Completed' ? 'completed' : 'progress';
        return `
            <a class="response-list-row ${Number(response.id) === Number(activeId) ? 'active' : ''}" href="SurveyResponseDetails.html?responseId=${Number(response.id)}&surveyId=${Number(response.surveyId)}">
                <span class="mini-initials">${escapeCoordinatorHtml(responseInitials(name) || 'AR')}</span>
                <span><strong>${escapeCoordinatorHtml(name)}</strong><small>${escapeCoordinatorHtml(response.respondentEmail || 'Email unavailable')}</small><small>${escapeCoordinatorHtml(formatCoordinatorDateTime(response.submittedAt))}</small></span>
                <em class="${statusClass}">${status}<b>${rate}%</b></em>
            </a>
        `;
    }).join('') : '<div class="coordinator-table-state">No matching responses.</div>';
    const count = document.getElementById('detailResponseCount');
    if (count) count.textContent = `${visible.length} ${visible.length === 1 ? 'response' : 'responses'}`;
}

function renderCoordinatorResponseDetail(active, survey) {
    const name = active.alumniName || active.userName || 'Alumni respondent';
    const rate = getCoordinatorResponseRate(active);
    const complete = coordinatorResponses.filter((response) => getCoordinatorResponseStatus(response) === 'Completed').length;
    const total = coordinatorResponses.length;
    const average = total ? Math.round(coordinatorResponses.reduce((sum, response) => sum + getCoordinatorResponseRate(response), 0) / total) : 0;
    const setText = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = value; };

    setText('detailSurveyTitle', survey?.title || 'Survey');
    setText('detailSurveyStatus', survey?.status === 'closed' ? 'Closed' : 'Open');
    setText('detailSurveyDates', [formatCoordinatorDate(survey?.openDateTime), formatCoordinatorDate(survey?.closeDateTime)].filter(Boolean).join(' – ') || 'Survey dates unavailable');
    setText('detailTotalResponses', String(total));
    setText('detailCompleteResponses', `${complete} (${total ? Math.round((complete / total) * 100) : 0}%)`);
    setText('detailProgressResponses', `${total - complete} (${total ? Math.round(((total - complete) / total) * 100) : 0}%)`);
    setText('detailAverageRate', `${average}%`);
    setText('detailResponseStatus', getCoordinatorResponseStatus(active));
    setText('detailName', name);
    setText('detailEmail', active.respondentEmail || 'Email unavailable');
    setText('detailCourse', active.course || active.college || 'Program unavailable');
    setText('detailYear', active.graduationYear || '—');
    setText('detailSubmitted', formatCoordinatorDateTime(active.submittedAt));
    setText('detailAlumniId', active.alumniRecordId || 'Unavailable');
    const backLink = document.getElementById('detailBackLink');
    if (backLink) backLink.href = `SurveyResponses.html?surveyId=${Number(active.surveyId)}`;
    const initials = document.querySelector('.initials');
    if (initials) initials.textContent = responseInitials(name) || 'AR';

    const questionList = document.getElementById('detailQuestionList');
    const answers = Array.isArray(active.answers) ? active.answers : [];
    if (questionList) {
        questionList.innerHTML = answers.length ? answers.map((answer, index) => `
            <article><b>${index + 1}</b><div><h4>${escapeCoordinatorHtml(answer.questionText || `Question ${index + 1}`)}</h4><p><span>Answer</span> ${answer.imageUrl ? `<a href="${escapeCoordinatorHtml(answer.imageUrl)}" target="_blank" rel="noopener">View uploaded image</a>` : escapeCoordinatorHtml(answer.value || 'No answer')}</p></div></article>
        `).join('') : '<article><div><h4>No saved answers were found for this response.</h4></div></article>';
    }
    const statusElement = document.getElementById('detailResponseStatus');
    if (statusElement) statusElement.className = rate >= 100 ? 'completed' : 'progress';

    // Store active ID for the review modal
    coordinatorActiveResponseId = active.id;

    // Render review state
    renderCoordinatorReviewBanner(active.reviewStatus || 'pending', active.latestReview || null);
}

function configureCoordinatorDetailNavigation(activeIndex) {
    const previous = document.getElementById('previousResponseButton');
    const next = document.getElementById('nextResponseButton');
    const navigate = (response) => {
        if (response) window.location.href = `SurveyResponseDetails.html?responseId=${Number(response.id)}&surveyId=${Number(response.surveyId)}`;
    };
    if (previous) {
        previous.disabled = activeIndex <= 0;
        previous.addEventListener('click', () => navigate(coordinatorResponses[activeIndex - 1]));
    }
    if (next) {
        next.disabled = activeIndex < 0 || activeIndex >= coordinatorResponses.length - 1;
        next.addEventListener('click', () => navigate(coordinatorResponses[activeIndex + 1]));
    }
}

function exportCoordinatorSingleResponse(response, survey) {
    const rows = [['Survey', survey?.title || ''], ['First Name', response.firstName || ''], ['Last Name', response.lastName || ''], ['Email', response.respondentEmail || ''], ['Alumni ID', response.alumniRecordId || ''], ['College', response.college || ''], ['Department', response.course || ''], ['Graduation Year', response.graduationYear || ''], ['Submitted At', response.submittedAt || ''], [], ['Question', 'Answer']];
    (response.answers || []).forEach((answer) => rows.push([answer.questionText || '', answer.value || answer.imageUrl || '']));
    downloadCoordinatorCsv(`survey-response-${Number(response.id)}.csv`, rows);
}

function renderCoordinatorDetailError(message) {
    const list = document.getElementById('responseListItems');
    if (list) list.innerHTML = `<div class="coordinator-table-state"><strong>Unable to load response</strong><span>${escapeCoordinatorHtml(message)}</span></div>`;
    const questions = document.getElementById('detailQuestionList');
    if (questions) questions.innerHTML = `<article><div><h4>${escapeCoordinatorHtml(message)}</h4></div></article>`;
}

// ── Review Modal ──────────────────────────────────────────────
let coordinatorActiveResponseId = null;

function renderCoordinatorReviewBanner(reviewStatus, latestReview) {
    const banner = document.getElementById('detailReviewBanner');
    const icon = document.getElementById('reviewBannerIcon');
    const statusEl = document.getElementById('reviewBannerStatus');
    const metaEl = document.getElementById('reviewBannerMeta');
    const btn = document.getElementById('openReviewModalBtn');
    const btnLabel = document.getElementById('reviewBtnLabel');

    if (!reviewStatus || reviewStatus === 'pending') {
        if (banner) banner.hidden = true;
        if (btnLabel) btnLabel.textContent = 'Review';
        if (btn) { btn.classList.remove('review-btn--reviewed', 'review-btn--revision'); }
        return;
    }

    if (banner) {
        banner.hidden = false;
        banner.className = `review-banner review-banner--${reviewStatus === 'reviewed' ? 'reviewed' : 'revision'}`;
    }
    if (icon) {
        icon.className = reviewStatus === 'reviewed'
            ? 'fas fa-circle-check' : 'fas fa-triangle-exclamation';
    }
    if (statusEl) {
        statusEl.textContent = reviewStatus === 'reviewed' ? 'Reviewed' : 'Needs Revision';
    }
    if (metaEl && latestReview) {
        const when = latestReview.reviewedAt ? formatCoordinatorDateTime(latestReview.reviewedAt) : '';
        const who = latestReview.reviewerName || 'Coordinator';
        const comment = latestReview.reviewComment ? ` — "${latestReview.reviewComment}"` : '';
        metaEl.textContent = `By ${who}${when ? `, ${when}` : ''}${comment}`;
    } else if (metaEl) {
        metaEl.textContent = '';
    }
    if (btnLabel) {
        btnLabel.textContent = reviewStatus === 'reviewed' ? 'Re-review' : 'Re-review';
    }
    if (btn) {
        btn.classList.remove('review-btn--reviewed', 'review-btn--revision');
        btn.classList.add(reviewStatus === 'reviewed' ? 'review-btn--reviewed' : 'review-btn--revision');
    }
}

function openCoordinatorReviewModal(currentStatus) {
    const overlay = document.getElementById('reviewModalOverlay');
    if (!overlay) return;
    // Pre-select current status if already reviewed
    const radioReviewed = document.getElementById('reviewRadioReviewed');
    const radioRevision = document.getElementById('reviewRadioRevision');
    const textarea = document.getElementById('reviewCommentTextarea');
    if (radioReviewed) radioReviewed.checked = currentStatus === 'reviewed';
    if (radioRevision) radioRevision.checked = currentStatus === 'needs_revision';
    if (textarea) textarea.value = '';
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
}

function closeCoordinatorReviewModal() {
    const overlay = document.getElementById('reviewModalOverlay');
    if (overlay) overlay.hidden = true;
    document.body.style.overflow = '';
}

let __coordActionConfirmPromise = null;
function ensureCoordinatorActionConfirm() {
    if (window.ActionConfirm) return Promise.resolve(window.ActionConfirm);
    if (!__coordActionConfirmPromise) {
        __coordActionConfirmPromise = new Promise((resolve) => {
            const s = document.createElement('script');
            s.src = '../JS/ActionConfirm.js';
            s.onload = () => resolve(window.ActionConfirm || null);
            s.onerror = () => resolve(null);
            document.head.appendChild(s);
        });
    }
    return __coordActionConfirmPromise;
}
void ensureCoordinatorActionConfirm();

async function submitCoordinatorReview() {
    if (!coordinatorActiveResponseId) return;

    const selected = document.querySelector('input[name="reviewStatus"]:checked');
    if (!selected) {
        alert('Please select a review decision before submitting.');
        return;
    }
    const reviewStatus = selected.value;
    const comment = (document.getElementById('reviewCommentTextarea')?.value || '').trim();
    const decisionLabel = reviewStatus === 'reviewed' ? 'Approved / Reviewed' : 'Needs Revision';

    const ac = await ensureCoordinatorActionConfirm();
    if (ac) {
        const confirmed = await ac.medium({
            title: 'Submit Survey Review?',
            message: `You are about to mark this alumni survey response as "${decisionLabel}".`,
            record: `Response #${coordinatorActiveResponseId}`,
            consequence: 'This updates the official review status visible to the alumni and in department reports.',
            confirmText: 'Yes, Submit Review',
            icon: 'fa-stamp'
        });
        if (!confirmed) return;
    }

    const submitBtn = document.getElementById('submitReviewBtn');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…'; }

    try {
        const res = await fetch(coordinatorApiUrl(`surveys/responses/${coordinatorActiveResponseId}/review`), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ review_status: reviewStatus, review_comment: comment }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) throw new Error(data.message || 'Failed to save review.');

        closeCoordinatorReviewModal();
        const updated = data.response;
        renderCoordinatorReviewBanner(updated?.reviewStatus || reviewStatus, updated?.latestReview || null);
    } catch (err) {
        alert(`Error: ${err.message}`);
    } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-stamp"></i> Submit Review'; }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('openReviewModalBtn')?.addEventListener('click', () => {
        const currentStatus = document.getElementById('detailReviewBanner')?.hidden === false
            ? (document.getElementById('reviewBannerStatus')?.textContent === 'Reviewed' ? 'reviewed' : 'needs_revision')
            : 'pending';
        openCoordinatorReviewModal(currentStatus);
    });
    document.getElementById('closeReviewModalBtn')?.addEventListener('click', closeCoordinatorReviewModal);
    document.getElementById('cancelReviewBtn')?.addEventListener('click', closeCoordinatorReviewModal);
    document.getElementById('submitReviewBtn')?.addEventListener('click', submitCoordinatorReview);
    document.getElementById('reviewModalOverlay')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeCoordinatorReviewModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeCoordinatorReviewModal();
    });
});

/* ============================================================
   Coordinator Overview Dashboard - Department-Scoped Analytics
   ============================================================ */

let coordGradChart = null;
let coordStatusChart = null;
let coordDashboardPayload = null;
let currentBatchFilter = 'all';
let currentBarMode = 'line'; // 'grouped' | 'stacked'

function initCoordinatorDashboardCharts() {
    const canvasGrads = document.getElementById('graduatesYearChart');
    if (!canvasGrads) return;

    setupCoordinatorControls();
    void loadCoordinatorDashboardData('all');
}

async function loadCoordinatorDashboardData(batch = 'all') {
    currentBatchFilter = batch || 'all';
    try {
        const query = currentBatchFilter !== 'all' ? `?batch=${encodeURIComponent(currentBatchFilter)}` : '';
        const res = await fetch(coordinatorApiUrl(`coordinator/dashboard-data${query}`), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) throw new Error('Unable to load department dashboard data.');
        const data = await res.json();
        if (!data?.success) throw new Error(data?.message || 'Unable to load department dashboard data.');

        coordDashboardPayload = data;
        renderCoordinatorBatchPills(Array.isArray(data.batches) ? data.batches : []);
        applyDepartmentDashboardData(data);
    } catch (err) {
        const bannerSub = document.getElementById('coordHeaderSubtitle');
        if (bannerSub) bannerSub.textContent = 'Unable to load department records right now.';
    }
}

function renderCoordinatorBatchPills(batches) {
    const pillList = document.getElementById('coordBatchPills');
    if (!pillList) return;

    const buttons = [
        `<button class="coord-batch-pill ${currentBatchFilter === 'all' ? 'active' : ''}" data-batch="all" type="button">All Batches</button>`,
        ...batches.map((yr) => {
            const safeYr = escapeCoordinatorHtml(yr);
            return `<button class="coord-batch-pill ${currentBatchFilter === String(yr) ? 'active' : ''}" data-batch="${safeYr}" type="button">${safeYr}</button>`;
        }),
    ];
    pillList.innerHTML = buttons.join('');

    pillList.querySelectorAll('.coord-batch-pill').forEach((btn) => {
        btn.addEventListener('click', () => {
            const nextBatch = btn.dataset.batch || 'all';
            if (nextBatch === currentBatchFilter) return;
            void loadCoordinatorDashboardData(nextBatch);
        });
    });
}

function applyDepartmentDashboardData(data) {
    const collegeLabel = data.college || 'Unassigned College';
    const setText = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    };

    setText('coordLockedDeptLabel', collegeLabel);
    setText('coordHeaderSubtitle', data.college
        ? `Showing alumni, employment, survey, and payment records across all departments of ${data.college}.`
        : 'No college is assigned to this coordinator account.');

    const totalGrad = Number(data.totalGraduates || 0);
    const tracedCount = Number(data.tracedCount || 0);
    const tracedRate = Number(data.tracedRate || 0);
    const employedCount = Number(data.employedCount || 0);
    const unemployedCount = Number(data.unemployedCount || 0);
    const notTrackedCount = Number(data.notTrackedCount || 0);
    const employmentRate = Number(data.employmentRate || 0);
    const survey = data.surveySummary || {};
    const payment = data.paymentSummary || {};

    setText('kpiTotalGraduates', totalGrad.toLocaleString());
    setText('kpiGradTrend', `${tracedRate.toFixed(1)}% traced`);
    setText('kpiGradNote', `${tracedCount.toLocaleString()} traced of ${totalGrad.toLocaleString()} in college`);

    setText('kpiEmploymentRate', `${employmentRate.toFixed(1)}%`);
    setText('kpiEmpTrend', `${employedCount.toLocaleString()} employed`);
    setText('kpiEmpNote', `${employedCount.toLocaleString()} employed / ${tracedCount.toLocaleString()} traced`);

    setText('kpiSurveyResponses', Number(survey.totalResponses || 0).toLocaleString());
    setText('kpiSurveyTrend', `${Number(survey.pendingCount || 0).toLocaleString()} pending`);
    setText('kpiSurveyNote', `${Number(survey.reviewedCount || 0).toLocaleString()} reviewed · ${Number(survey.needsRevisionCount || 0).toLocaleString()} needs revision`);

    setText('kpiDeptPayments', Number(payment.totalTransactions || 0).toLocaleString());
    setText('kpiPayTrend', `${Number(payment.pendingCount || 0).toLocaleString()} pending`);
    setText('kpiPayNote', `${Number(payment.verifiedCount || 0).toLocaleString()} verified transactions`);

    setText('chartTotalRecordsBadge', `${totalGrad.toLocaleString()} College Alumni`);

    // Survey snapshot progress bars
    const respRate = Number(survey.responseRate || 0);
    const reviewDenom = Number(survey.totalResponses || 0);
    const reviewPct = reviewDenom > 0 ? Math.round((Number(survey.reviewedCount || 0) / reviewDenom) * 100) : 0;
    const payDenom = Number(payment.totalTransactions || 0);
    const payPct = payDenom > 0 ? Math.round((Number(payment.verifiedCount || 0) / payDenom) * 100) : 0;

    setText('snapResponseRate', `${respRate.toFixed(1)}%`);
    setText('snapReviewRate', `${reviewPct}%`);
    setText('snapPaymentRate', `${Number(payment.verifiedCount || 0)} / ${payDenom}`);

    const barResp = document.getElementById('snapResponseBar');
    const barRev = document.getElementById('snapReviewBar');
    const barPay = document.getElementById('snapPaymentBar');
    if (barResp) barResp.style.width = `${Math.min(100, Math.max(0, respRate))}%`;
    if (barRev) barRev.style.width = `${Math.min(100, Math.max(0, reviewPct))}%`;
    if (barPay) barPay.style.width = `${Math.min(100, Math.max(0, payPct))}%`;

    // Task queue badges
    setText('taskPendingSurveys', `${Number(survey.pendingCount || 0)} Pending`);
    setText('taskRevisionSurveys', `${Number(survey.needsRevisionCount || 0)} Revision`);
    setText('taskNotTrackedAlumni', `${notTrackedCount.toLocaleString()} Untraced`);
    setText('taskPendingPayments', `${Number(payment.pendingCount || 0)} Pending`);

    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = "'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.color = '#64748b';
        renderGraduatesYearChart(data);
        renderEmploymentStatusChart(data);
    }
}

function renderGraduatesYearChart(data = coordDashboardPayload) {
    const canvas = document.getElementById('graduatesYearChart');
    const ctx = canvas?.getContext('2d');
    if (!ctx || !canvas || !data) return;

    if (coordGradChart) {
        coordGradChart.destroy();
        coordGradChart = null;
    }

    const cohorts = data.yearCohorts || { labels: [], employed: [], unemployed: [], notTracked: [] };
    const labels = cohorts.labels.length ? cohorts.labels : ['No Cohorts'];
    const employed = cohorts.labels.length ? cohorts.employed : [0];
    const unemployed = cohorts.labels.length ? cohorts.unemployed : [0];
    const notTracked = cohorts.labels.length ? cohorts.notTracked : [0];

    // Professional Line Graph Modes (Count Trend & Employment Rate %)
    if ((currentBarMode === 'line' || currentBarMode === 'rate') && window.GradVisionLineChart) {
        if (currentBarMode === 'rate') {
            const empRates = labels.map((_, i) => {
                const emp = Number(employed[i] || 0);
                const unemp = Number(unemployed[i] || 0);
                const tracked = emp + unemp;
                return tracked > 0 ? Number(((emp / tracked) * 100).toFixed(1)) : 0;
            });
            const tracedRates = labels.map((_, i) => {
                const emp = Number(employed[i] || 0);
                const unemp = Number(unemployed[i] || 0);
                const nt = Number(notTracked[i] || 0);
                const total = emp + unemp + nt;
                return total > 0 ? Number((((emp + unemp) / total) * 100).toFixed(1)) : 0;
            });
            coordGradChart = window.GradVisionLineChart.createLineChart(canvas, {
                labels,
                unit: '%',
                xTitle: 'Graduation Batch',
                yTitle: 'Rate (%)',
                showLegend: true,
                series: [
                    { label: 'Employment Rate', data: empRates, color: 'emerald' },
                    { label: 'Tracer Response Rate', data: tracedRates, color: 'blue' }
                ]
            });
            return;
        }

        coordGradChart = window.GradVisionLineChart.createLineChart(canvas, {
            labels,
            unit: 'alumni',
            xTitle: 'Graduation Batch',
            yTitle: 'Alumni Count',
            showLegend: true,
            series: [
                { label: 'Employed', data: employed, color: 'emerald' },
                { label: 'Unemployed', data: unemployed, color: 'rose' },
                { label: 'Not Tracked', data: notTracked, color: 'slate' }
            ]
        });
        return;
    }

    const isStacked = currentBarMode === 'stacked';

    coordGradChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Employed',
                    data: employed,
                    backgroundColor: '#10b981',
                    hoverBackgroundColor: '#059669',
                    borderRadius: 6,
                    borderSkipped: false,
                    barPercentage: isStacked ? 0.6 : 0.7,
                    categoryPercentage: 0.8
                },
                {
                    label: 'Unemployed',
                    data: unemployed,
                    backgroundColor: '#f43f5e',
                    hoverBackgroundColor: '#e11d48',
                    borderRadius: 6,
                    borderSkipped: false,
                    barPercentage: isStacked ? 0.6 : 0.7,
                    categoryPercentage: 0.8
                },
                {
                    label: 'Not Tracked',
                    data: notTracked,
                    backgroundColor: '#94a3b8',
                    hoverBackgroundColor: '#64748b',
                    borderRadius: 6,
                    borderSkipped: false,
                    barPercentage: isStacked ? 0.6 : 0.7,
                    categoryPercentage: 0.8
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 500 },
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12,
                        boxHeight: 12,
                        borderRadius: 3,
                        useBorderRadius: true,
                        font: { size: 12, weight: '600' }
                    }
                }
            },
            scales: {
                x: {
                    stacked: isStacked,
                    grid: { display: false },
                    ticks: { font: { weight: '600' } }
                },
                y: {
                    stacked: isStacked,
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { precision: 0 }
                }
            }
        }
    });
}

function renderEmploymentStatusChart(data = coordDashboardPayload) {
    const ctx = document.getElementById('employmentStatusChart')?.getContext('2d');
    if (!ctx || !data) return;

    if (coordStatusChart) {
        coordStatusChart.destroy();
    }

    const status = data.statusBreakdown || { counts: [0, 0, 0], pcts: ['0.0%', '0.0%', '0.0%'] };
    const total = status.counts.reduce((acc, n) => acc + Number(n || 0), 0);

    coordStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Employed', 'Unemployed', 'Not Tracked'],
            datasets: [{
                data: total > 0 ? status.counts : [0, 0, 1],
                backgroundColor: total > 0 ? ['#10b981', '#f43f5e', '#94a3b8'] : ['#e2e8f0', '#e2e8f0', '#e2e8f0'],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            animation: { duration: 550 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: total > 0,
                    backgroundColor: '#0f172a',
                    padding: 12,
                    cornerRadius: 8
                }
            }
        }
    });

    const centerEl = document.getElementById('donutCenterPct');
    if (centerEl) {
        centerEl.textContent = `${Number(data.employmentRate || 0).toFixed(1)}%`;
    }

    const countEls = document.querySelectorAll('[data-status-count]');
    const pctEls = document.querySelectorAll('[data-status-pct]');
    status.counts.forEach((count, i) => {
        if (countEls[i]) countEls[i].textContent = Number(count || 0).toLocaleString();
        if (pctEls[i]) pctEls[i].textContent = status.pcts[i] || '0.0%';
    });
}

function setupCoordinatorControls() {
    document.querySelectorAll('[data-bar-mode]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-bar-mode]').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            currentBarMode = btn.dataset.barMode || 'grouped';
            renderGraduatesYearChart();
        });
    });

    document.getElementById('btnExportOverview')?.addEventListener('click', () => {
        if (!coordDashboardPayload) return;
        const rows = [
            ['Department', coordDashboardPayload.department || 'Unassigned'],
            ['Batch Filter', currentBatchFilter],
            ['Total Graduates', coordDashboardPayload.totalGraduates || 0],
            ['Traced Alumni', coordDashboardPayload.tracedCount || 0],
            ['Employed', coordDashboardPayload.employedCount || 0],
            ['Unemployed', coordDashboardPayload.unemployedCount || 0],
            ['Not Tracked', coordDashboardPayload.notTrackedCount || 0],
            ['Employment Rate (%)', coordDashboardPayload.employmentRate || 0],
        ];
        downloadCoordinatorCsv('department-overview-summary.csv', rows);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initCoordinatorDashboardCharts();
});

