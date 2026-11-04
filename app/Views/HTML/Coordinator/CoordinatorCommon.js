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
    const collegeSelect = document.getElementById('collegeSelect');
    const title = document.querySelector('.topbar h2');

    navItems.forEach((item) => {
        item.addEventListener('click', () => {
            const target = item.dataset.section;

            navItems.forEach((navItem) => navItem.classList.remove('active'));
            sections.forEach((section) => section.classList.remove('active'));

            item.classList.add('active');
            document.getElementById(target)?.classList.add('active');
        });
    });

    collegeSelect?.addEventListener('change', () => {
        title.textContent = collegeSelect.value;
    });

    void initializeCoordinatorSurveyResponses();
    void initializeCoordinatorResponseDetails();
});

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

async function submitCoordinatorReview() {
    if (!coordinatorActiveResponseId) return;

    const selected = document.querySelector('input[name="reviewStatus"]:checked');
    if (!selected) {
        alert('Please select a review decision before submitting.');
        return;
    }
    const reviewStatus = selected.value;
    const comment = (document.getElementById('reviewCommentTextarea')?.value || '').trim();

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
   Coordinator Overview Dashboard - Charts & Interactive Controls
   ============================================================ */

let coordGradChart = null;
let coordStatusChart = null;
let coordTimeToJobChart = null;
let coordRelevanceChart = null;

const coordMockData = {
    all: {
        totalGraduates: '1,284',
        gradTrend: '▲ 3.1%',
        gradNote: '98.2% traced & verified',
        employmentRate: '81.4%',
        empTrend: '▲ 2.5%',
        empNote: '1,045 employed / 1,284 total',
        alignmentRate: '87.2%',
        alignTrend: '▲ 1.8%',
        alignNote: 'Degree-relevant employment',
        timeToJob: '2.8 Mos',
        timeTrend: '▼ 0.4 mos',
        timeNote: '66.9% placed in ≤ 90 days',
        yearCohorts: {
            labels: ['2019', '2020', '2021', '2022', '2023', '2024'],
            employed: [165, 198, 212, 228, 245, 197],
            unemployed: [55, 112, 48, 72, 85, 32]
        },
        status: {
            counts: [931, 114, 177, 62],
            pcts: ['72.5%', '8.9%', '13.8%', '4.8%']
        },
        timeToJobDistribution: [358, 501, 269, 103, 53]
    },
    '2024': {
        totalGraduates: '229',
        gradTrend: 'Newest',
        gradNote: 'Current tracer cycle',
        employmentRate: '86.0%',
        empTrend: '▲ 4.6%',
        empNote: '197 employed / 229 total',
        alignmentRate: '91.2%',
        alignTrend: '▲ 4.0%',
        alignNote: 'High curriculum relevance',
        timeToJob: '2.1 Mos',
        timeTrend: '▼ 0.7 mos',
        timeNote: '74.2% placed in ≤ 90 days',
        yearCohorts: {
            labels: ['2024 (Target)', '2024 (Actual)'],
            employed: [180, 197],
            unemployed: [49, 32]
        },
        status: {
            counts: [175, 22, 24, 8],
            pcts: ['76.4%', '9.6%', '10.5%', '3.5%']
        },
        timeToJobDistribution: [82, 88, 38, 14, 7]
    },
    '2023': {
        totalGraduates: '330',
        gradTrend: 'Peak Cohort',
        gradNote: 'Highest class size',
        employmentRate: '74.2%',
        empTrend: '▼ 1.8%',
        empNote: '245 employed / 330 total',
        alignmentRate: '84.5%',
        alignTrend: '▲ 0.9%',
        alignNote: 'Solid industry match',
        timeToJob: '3.1 Mos',
        timeTrend: '▲ 0.2 mos',
        timeNote: '63.0% placed in ≤ 90 days',
        yearCohorts: {
            labels: ['Q1', 'Q2', 'Q3', 'Q4'],
            employed: [60, 68, 62, 55],
            unemployed: [22, 20, 23, 20]
        },
        status: {
            counts: [215, 30, 65, 20],
            pcts: ['65.2%', '9.1%', '19.7%', '6.0%']
        },
        timeToJobDistribution: [85, 123, 72, 34, 16]
    },
    '2022': {
        totalGraduates: '300',
        gradTrend: 'Stable',
        gradNote: 'Established cohort',
        employmentRate: '76.0%',
        empTrend: '▲ 1.2%',
        empNote: '228 employed / 300 total',
        alignmentRate: '86.0%',
        alignTrend: '▲ 1.5%',
        alignNote: 'Accreditation compliant',
        timeToJob: '2.9 Mos',
        timeTrend: '▼ 0.1 mos',
        timeNote: '67.4% placed in ≤ 90 days',
        yearCohorts: {
            labels: ['Sem 1', 'Sem 2', 'Midyear'],
            employed: [110, 98, 20],
            unemployed: [35, 30, 7]
        },
        status: {
            counts: [205, 23, 54, 18],
            pcts: ['68.3%', '7.7%', '18.0%', '6.0%']
        },
        timeToJobDistribution: [78, 124, 61, 25, 12]
    },
    '2021': {
        totalGraduates: '260',
        gradTrend: 'Post-Pandemic',
        gradNote: 'Strong recovery cohort',
        employmentRate: '81.5%',
        empTrend: '▲ 17.6%',
        empNote: '212 employed / 260 total',
        alignmentRate: '88.1%',
        alignTrend: '▲ 3.2%',
        alignNote: 'Strong technical roles',
        timeToJob: '2.6 Mos',
        timeTrend: '▼ 0.5 mos',
        timeNote: '71.5% placed in ≤ 90 days',
        yearCohorts: {
            labels: ['Cohort A', 'Cohort B'],
            employed: [115, 97],
            unemployed: [25, 23]
        },
        status: {
            counts: [190, 22, 38, 10],
            pcts: ['73.1%', '8.5%', '14.6%', '3.8%']
        },
        timeToJobDistribution: [75, 111, 48, 18, 8]
    },
    '2020': {
        totalGraduates: '310',
        gradTrend: 'Pandemic Year',
        gradNote: 'Resilient cohort',
        employmentRate: '63.9%',
        empTrend: 'Stabilized',
        empNote: '198 employed / 310 total',
        alignmentRate: '83.2%',
        alignTrend: '▲ 1.1%',
        alignNote: 'Diversified career paths',
        timeToJob: '3.6 Mos',
        timeTrend: '▲ 0.8 mos',
        timeNote: '54.2% placed in ≤ 90 days',
        yearCohorts: {
            labels: ['Graduation', '6-Mo Track', '1-Yr Track', '2-Yr Track'],
            employed: [80, 120, 160, 198],
            unemployed: [230, 190, 150, 112]
        },
        status: {
            counts: [180, 18, 92, 20],
            pcts: ['58.1%', '5.8%', '29.7%', '6.4%']
        },
        timeToJobDistribution: [52, 98, 85, 48, 27]
    }
};

let currentBatchFilter = 'all';
let currentBarMode = 'grouped'; // 'grouped' | 'stacked'
let currentRelevanceMode = 'relevance'; // 'relevance' | 'sectors'

function initCoordinatorDashboardCharts() {
    const canvasGrads = document.getElementById('graduatesYearChart');
    if (!canvasGrads) return;

    if (typeof Chart === 'undefined') {
        console.warn('Chart.js library is not loaded.');
        return;
    }

    // Chart.js global defaults for clean modern aesthetic
    Chart.defaults.font.family = "'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.color = '#64748b';

    // 1. Graduates by Year & Employment Cohorts Chart
    renderGraduatesYearChart();

    // 2. Current Employment Status Donut Chart
    renderEmploymentStatusChart();

    // 3. Time to First Job Horizontal Bar Chart
    renderTimeToJobChart();

    // 4. Curriculum Relevance & Industry Chart
    renderRelevanceIndustryChart();

    // Setup interactive controls
    setupCoordinatorControls();
}

function renderGraduatesYearChart() {
    const ctx = document.getElementById('graduatesYearChart')?.getContext('2d');
    if (!ctx) return;

    if (coordGradChart) {
        coordGradChart.destroy();
    }

    const data = coordMockData[currentBatchFilter] || coordMockData.all;
    const isStacked = currentBarMode === 'stacked';

    coordGradChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.yearCohorts.labels,
            datasets: [
                {
                    label: 'Employed',
                    data: data.yearCohorts.employed,
                    backgroundColor: '#10b981',
                    hoverBackgroundColor: '#059669',
                    borderRadius: 6,
                    borderSkipped: false,
                    barPercentage: isStacked ? 0.6 : 0.7,
                    categoryPercentage: 0.8
                },
                {
                    label: 'Unemployed / Seeking',
                    data: data.yearCohorts.unemployed,
                    backgroundColor: '#f43f5e',
                    hoverBackgroundColor: '#e11d48',
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
            animation: { duration: 600 },
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
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: { size: 13, weight: '700' },
                    bodyFont: { size: 12 },
                    callbacks: {
                        footer: (items) => {
                            const total = items.reduce((acc, curr) => acc + curr.raw, 0);
                            const emp = items.find(i => i.dataset.label === 'Employed')?.raw || 0;
                            const rate = total > 0 ? Math.round((emp / total) * 100) : 0;
                            return `Total Graduates: ${total} (Employment: ${rate}%)`;
                        }
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

function renderEmploymentStatusChart() {
    const ctx = document.getElementById('employmentStatusChart')?.getContext('2d');
    if (!ctx) return;

    if (coordStatusChart) {
        coordStatusChart.destroy();
    }

    const data = coordMockData[currentBatchFilter] || coordMockData.all;

    coordStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [
                'Employed Full-Time',
                'Self-Employed / Freelance',
                'Unemployed Seeking',
                'Further Studies / Board Prep'
            ],
            datasets: [{
                data: data.status.counts,
                backgroundColor: ['#10b981', '#0284c7', '#f43f5e', '#f59e0b'],
                hoverBackgroundColor: ['#059669', '#0369a1', '#e11d48', '#d97706'],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            animation: { animateScale: true, animateRotate: true, duration: 650 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: (item) => {
                            const total = item.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((item.raw / total) * 100).toFixed(1) : 0;
                            return ` ${item.label}: ${item.raw} alumni (${pct}%)`;
                        }
                    }
                }
            }
        }
    });

    // Update legend values & center metric
    updateEmploymentLegendAndCenter(data);
}

function updateEmploymentLegendAndCenter(data) {
    const centerEl = document.getElementById('donutCenterPct');
    if (centerEl) {
        centerEl.textContent = data.employmentRate;
    }

    const countEls = document.querySelectorAll('[data-status-count]');
    const pctEls = document.querySelectorAll('[data-status-pct]');

    data.status.counts.forEach((count, i) => {
        if (countEls[i]) countEls[i].textContent = count;
        if (pctEls[i]) pctEls[i].textContent = data.status.pcts[i];
    });
}

function renderTimeToJobChart() {
    const ctx = document.getElementById('timeToJobChart')?.getContext('2d');
    if (!ctx) return;

    if (coordTimeToJobChart) {
        coordTimeToJobChart.destroy();
    }

    const data = coordMockData[currentBatchFilter] || coordMockData.all;

    coordTimeToJobChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['< 1 Month', '1 - 3 Months', '3 - 6 Months', '6 - 12 Months', '> 1 Year'],
            datasets: [{
                label: 'Graduates',
                data: data.timeToJobDistribution,
                backgroundColor: [
                    '#10b981',
                    '#0284c7',
                    '#38bdf8',
                    '#f59e0b',
                    '#94a3b8'
                ],
                borderRadius: 6,
                borderSkipped: false,
                barPercentage: 0.65
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 600 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: (item) => {
                            const total = item.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((item.raw / total) * 100).toFixed(1) : 0;
                            return ` ${item.raw} alumni (${pct}% of traced cohort)`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { precision: 0 }
                },
                y: {
                    grid: { display: false },
                    ticks: { font: { weight: '600' } }
                }
            }
        }
    });
}

function renderRelevanceIndustryChart() {
    const ctx = document.getElementById('relevanceIndustryChart')?.getContext('2d');
    if (!ctx) return;

    if (coordRelevanceChart) {
        coordRelevanceChart.destroy();
    }

    if (currentRelevanceMode === 'relevance') {
        coordRelevanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Directly Related', 'Moderately Related', 'Unrelated / Career Shift'],
                datasets: [{
                    label: 'Degree Relevance (%)',
                    data: [70.8, 16.4, 12.8],
                    backgroundColor: ['#10b981', '#6366f1', '#94a3b8'],
                    hoverBackgroundColor: ['#059669', '#4f46e5', '#64748b'],
                    borderRadius: 8,
                    barPercentage: 0.55
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: (item) => ` ${item.raw}% of employed graduates`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { weight: '600' } }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            callback: (val) => `${val}%`
                        }
                    }
                }
            }
        });
    } else {
        coordRelevanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [
                    'Software & Tech',
                    'Civil & Infrastructure',
                    'Energy & Manufacturing',
                    'Government & Public',
                    'Academia & Research'
                ],
                datasets: [{
                    label: 'Industry Absorption (%)',
                    data: [36.2, 21.4, 17.5, 13.1, 11.8],
                    backgroundColor: [
                        '#0284c7',
                        '#0ea5e9',
                        '#6366f1',
                        '#8b5cf6',
                        '#94a3b8'
                    ],
                    borderRadius: 6,
                    barPercentage: 0.6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: (item) => ` ${item.raw}% of employed alumni in this sector`
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 45,
                        grid: { color: '#f1f5f9' },
                        ticks: { callback: (val) => `${val}%` }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { weight: '600' } }
                    }
                }
            }
        });
    }
}

function setupCoordinatorControls() {
    // 1. Batch Filter Pills
    document.querySelectorAll('.coord-batch-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.coord-batch-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const batch = btn.dataset.batch || 'all';
            currentBatchFilter = batch;
            applyBatchFilter(batch);
        });
    });

    // 2. Bar Chart Mode Toggle (Grouped vs Stacked)
    document.querySelectorAll('[data-bar-mode]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-bar-mode]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentBarMode = btn.dataset.barMode;
            renderGraduatesYearChart();
        });
    });

    // 3. Relevance vs Sectors Mode Toggle
    document.querySelectorAll('[data-relevance-mode]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-relevance-mode]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentRelevanceMode = btn.dataset.relevanceMode;
            renderRelevanceIndustryChart();
        });
    });

    // 4. Program Dropdown Filter
    const programSelect = document.getElementById('coordProgramFilter');
    programSelect?.addEventListener('change', () => {
        const program = programSelect.value;
        const bannerSub = document.getElementById('coordHeaderSubtitle');
        if (bannerSub) {
            bannerSub.textContent = program === 'all'
                ? 'College-wide cohort insights, graduate career trajectory, employability metrics, and alumni tracking overview.'
                : `Focused analytics and graduate employment trajectory for ${programSelect.options[programSelect.selectedIndex].text}.`;
        }
        // Refresh charts with animation
        renderGraduatesYearChart();
        renderEmploymentStatusChart();
        renderTimeToJobChart();
        renderRelevanceIndustryChart();
    });

    // 5. Export Report Button
    document.getElementById('btnExportOverview')?.addEventListener('click', () => {
        alert('Exporting College Alumni Tracer Overview Report (PDF/Excel)...');
    });
}

function applyBatchFilter(batch) {
    const data = coordMockData[batch] || coordMockData.all;

    // Update KPI Card Numbers
    const gradNum = document.getElementById('kpiTotalGraduates');
    const gradTrend = document.getElementById('kpiGradTrend');
    const gradNote = document.getElementById('kpiGradNote');

    const empNum = document.getElementById('kpiEmploymentRate');
    const empTrend = document.getElementById('kpiEmpTrend');
    const empNote = document.getElementById('kpiEmpNote');

    const alignNum = document.getElementById('kpiAlignmentRate');
    const alignTrend = document.getElementById('kpiAlignTrend');
    const alignNote = document.getElementById('kpiAlignNote');

    const timeNum = document.getElementById('kpiTimeToJob');
    const timeTrend = document.getElementById('kpiTimeTrend');
    const timeNote = document.getElementById('kpiTimeNote');

    if (gradNum) gradNum.textContent = data.totalGraduates;
    if (gradTrend) gradTrend.innerHTML = `<i class="fas fa-arrow-up"></i> ${data.gradTrend}`;
    if (gradNote) gradNote.textContent = data.gradNote;

    if (empNum) empNum.textContent = data.employmentRate;
    if (empTrend) empTrend.innerHTML = `<i class="fas fa-arrow-up"></i> ${data.empTrend}`;
    if (empNote) empNote.textContent = data.empNote;

    if (alignNum) alignNum.textContent = data.alignmentRate;
    if (alignTrend) alignTrend.innerHTML = `<i class="fas fa-arrow-up"></i> ${data.alignTrend}`;
    if (alignNote) alignNote.textContent = data.alignNote;

    if (timeNum) timeNum.textContent = data.timeToJob;
    if (timeTrend) timeTrend.innerHTML = `<i class="fas fa-arrow-down"></i> ${data.timeTrend}`;
    if (timeNote) timeNote.textContent = data.timeNote;

    // Re-render charts for selected cohort
    renderGraduatesYearChart();
    renderEmploymentStatusChart();
    renderTimeToJobChart();
}

// Auto-initialize charts on DOM load
document.addEventListener('DOMContentLoaded', () => {
    initCoordinatorDashboardCharts();
});

