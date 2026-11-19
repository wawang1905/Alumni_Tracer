const DEVELOPER_STATE_KEY = 'developer_console_state_v1';

const defaultDeveloperState = {
    admins: [
        {
            id: 1,
            firstName: 'Maria',
            lastName: 'Santos',
            email: 'maria.santos@ustp.edu.ph',
            department: 'Alumni Affairs',
            role: 'Head Admin',
            status: 'Active',
            lastActive: 'Today, 9:42 AM'
        },
        {
            id: 2,
            firstName: 'Rafael',
            lastName: 'Cruz',
            email: 'rafael.cruz@ustp.edu.ph',
            department: 'Registrar',
            role: 'Head Admin',
            status: 'Locked',
            lastActive: 'Jul 10, 2026'
        }
    ],
    settings: {
        appName: 'GradVision',
        supportEmail: 'support@ustp.edu.ph',
        sessionTimeout: '30',
        failedLoginLimit: '5',
        forceResetNewAdmins: true,
        notifyOnLock: true,
        restrictStaffSettings: true
    },
    school: {
        schoolName: 'University of Science and Technology of Southern Philippines',
        schoolCampus: 'Cagayan de Oro',
        registrarEmail: 'registrar@ustp.edu.ph',
        schoolAddress: 'Lapasan, Cagayan de Oro City',
        schoolPhone: '(088) 856-1738'
    },
    messaging: {
        emailProvider: 'SMTP',
        smtpHost: 'smtp.ustp.edu.ph',
        senderEmail: 'alumni-tracer@ustp.edu.ph',
        senderName: 'GradVision',
        smsProvider: 'Disabled',
        smsSenderId: 'USTP',
        smsEnabled: false
    },
    backups: [
        {
            name: 'alumni_tracer_backup_2026_07_10.sql',
            date: 'Jul 10, 2026 8:00 PM',
            size: '42 MB',
            status: 'Completed',
            createdBy: 'System'
        }
    ],
    logs: [
        {
            level: 'info',
            title: 'Developer console opened',
            detail: 'System Administrator reviewed application controls.',
            time: 'Just now'
        },
        {
            level: 'warning',
            title: 'Account locked',
            detail: 'rafael.cruz@ustp.edu.ph exceeded the login limit.',
            time: 'Jul 10, 2026 4:18 PM'
        },
        {
            level: 'info',
            title: 'Survey template updated',
            detail: 'Graduate Employment template was revised.',
            time: 'Jul 09, 2026 2:11 PM'
        }
    ],
    announcements: [
        {
            title: 'Scheduled database backup',
            body: 'Administrators should avoid importing alumni records during the Friday backup window.',
            date: 'Jul 11, 2026'
        }
    ],
    surveyTemplates: [
        {
            name: 'Graduate Employment',
            description: 'Core employability questions for alumni tracking.',
            questions: 18,
            status: 'Active'
        },
        {
            name: 'Further Studies',
            description: 'Tracks postgraduate enrollment and certifications.',
            questions: 9,
            status: 'Draft'
        },
        {
            name: 'Training Needs',
            description: 'Collects feedback for seminars and alumni services.',
            questions: 12,
            status: 'Active'
        }
    ],
    maintenance: {
        enabled: false,
        message: 'The Alumni Tracer is temporarily unavailable while maintenance is in progress.',
        end: ''
    },
    nextAdminId: 4
};

let developerState = loadDeveloperState();
let editingAdminId = null;
let toastTimer = null;
let developerUsers = [];
let developerCampuses = [];
let developerOverviewTelemetry = null;
let editingCampusId = null;
const campusAddressApiUrls = {
    provinces: 'https://psgc.gitlab.io/api/provinces.json',
    municipalities: 'https://psgc.gitlab.io/api/municipalities.json',
    cities: 'https://psgc.gitlab.io/api/cities.json',
    barangays: 'https://psgc.gitlab.io/api/barangays.json',
};
const campusAddressData = {
    provinces: [],
    municipalities: [],
    cities: [],
    barangays: [],
};
let campusAddressLoaded = false;
let campusAddressLoading = false;
let campusAddressState = {
    provinceCode: '',
    municipalityCode: '',
    barangayCode: '',
    province: '',
    municipality: '',
    barangay: '',
};

function cloneDefaultState() {
    return JSON.parse(JSON.stringify(defaultDeveloperState));
}

function loadDeveloperState() {
    try {
        const stored = localStorage.getItem(DEVELOPER_STATE_KEY);
        if (!stored) return cloneDefaultState();
        return { ...cloneDefaultState(), ...JSON.parse(stored) };
    } catch (error) {
        return cloneDefaultState();
    }
}

function saveDeveloperState() {
    localStorage.setItem(DEVELOPER_STATE_KEY, JSON.stringify(developerState));
}

function showToast(message) {
    const toast = document.getElementById('developerToast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}

function ensureConfirmationModal() {
    let modal = document.getElementById('developerConfirmModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'developerConfirmModal';
    modal.className = 'modal confirm-modal';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
        <div class="modal-content confirm-modal-content" role="dialog" aria-modal="true" aria-labelledby="developerConfirmTitle">
            <div class="modal-header">
                <h3 id="developerConfirmTitle">Confirm Action</h3>
                <button class="icon-button" type="button" id="closeDeveloperConfirmModal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="confirm-modal-body">
                <div class="confirm-modal-icon" id="developerConfirmIcon">
                    <i class="fas fa-circle-question"></i>
                </div>
                <div class="confirm-modal-text">
                    <p id="developerConfirmMessage">Are you sure you want to proceed with this action?</p>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" type="button" id="cancelDeveloperConfirm">Cancel</button>
                <button class="btn-primary" type="button" id="acceptDeveloperConfirm">Confirm</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    return modal;
}

function askConfirmation(options = {}) {
    const config = typeof options === 'string'
        ? { message: options }
        : (options || {});

    const title = config.title || 'Confirm Action';
    const message = config.message || 'Are you sure you want to proceed with this action?';
    const confirmText = config.confirmText || 'Confirm';
    const cancelText = config.cancelText || 'Cancel';
    const isDanger = Boolean(config.danger);
    const iconClass = config.icon || (isDanger ? 'fa-triangle-exclamation' : 'fa-circle-question');

    const modal = ensureConfirmationModal();
    const titleEl = document.getElementById('developerConfirmTitle');
    const messageEl = document.getElementById('developerConfirmMessage');
    const iconWrapEl = document.getElementById('developerConfirmIcon');
    const acceptBtn = document.getElementById('acceptDeveloperConfirm');
    const cancelBtn = document.getElementById('cancelDeveloperConfirm');
    const closeBtn = document.getElementById('closeDeveloperConfirmModal');

    if (titleEl) titleEl.textContent = title;
    if (messageEl) messageEl.textContent = message;
    if (iconWrapEl) {
        iconWrapEl.classList.toggle('is-danger', isDanger);
        iconWrapEl.innerHTML = `<i class="fas ${escapeHtml(iconClass)}"></i>`;
    }
    if (acceptBtn) {
        acceptBtn.textContent = confirmText;
        acceptBtn.className = isDanger ? 'btn-danger' : 'btn-primary';
    }
    if (cancelBtn) {
        cancelBtn.textContent = cancelText;
    }

    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');

    return new Promise((resolve) => {
        const cleanup = (confirmed) => {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            acceptBtn?.removeEventListener('click', onAccept);
            cancelBtn?.removeEventListener('click', onCancel);
            closeBtn?.removeEventListener('click', onCancel);
            modal.removeEventListener('click', onBackdrop);
            document.removeEventListener('keydown', onKeydown);
            resolve(confirmed);
        };

        const onAccept = () => cleanup(true);
        const onCancel = () => cleanup(false);
        const onBackdrop = (event) => {
            if (event.target === modal) cleanup(false);
        };
        const onKeydown = (event) => {
            if (event.key === 'Escape') cleanup(false);
        };

        acceptBtn?.addEventListener('click', onAccept);
        cancelBtn?.addEventListener('click', onCancel);
        closeBtn?.addEventListener('click', onCancel);
        modal.addEventListener('click', onBackdrop);
        document.addEventListener('keydown', onKeydown);
    });
}

function getLoginUrl() {
    return new URL('../', window.location.href).toString();
}

function getSessionUrl() {
    return new URL('../auth/session', window.location.href).toString();
}

function getLogoutUrl() {
    return new URL('../auth/logout', window.location.href).toString();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function sortCampusAddressItems(items) {
    return [...items].sort((first, second) => String(first.name || '').localeCompare(String(second.name || '')));
}

function buildCampusAddress(parts) {
    return parts.filter((part) => String(part || '').trim() !== '').join(', ');
}

function toCampusAddressOption(item) {
    return {
        value: item.code,
        label: item.name,
    };
}

function findCampusAddressItem(collectionName, name) {
    const normalizedName = String(name || '').trim().toLowerCase();
    return campusAddressData[collectionName].find((item) => String(item.name || '').trim().toLowerCase() === normalizedName) || null;
}

function buildCampusAddressMarkup(items, selectedValue, placeholder) {
    const options = [`<option value="">${escapeHtml(placeholder)}</option>`];
    items.forEach((item) => {
        const selected = item.value === selectedValue ? ' selected' : '';
        options.push(`<option value="${escapeHtml(item.value)}"${selected}>${escapeHtml(item.label)}</option>`);
    });
    return options.join('');
}

function renderCampusAddressSelects(selectedAddress = '') {
    const provinceSelect = document.getElementById('campusProvince');
    const municipalitySelect = document.getElementById('campusMunicipality');
    const barangaySelect = document.getElementById('campusBarangay');
    if (!provinceSelect || !municipalitySelect || !barangaySelect) return;

    if (selectedAddress !== '') {
        const addressParts = String(selectedAddress || '').split(',').map((part) => part.trim()).filter(Boolean);
        const [provinceName, municipalityName, barangayName] = addressParts;

        if (provinceName) {
            const provinceItem = findCampusAddressItem('provinces', provinceName);
            if (provinceItem) {
                campusAddressState.provinceCode = provinceItem.code;
                campusAddressState.province = provinceItem.name;
            } else {
                campusAddressState.provinceCode = '';
                campusAddressState.province = provinceName;
            }
        }

        if (municipalityName && campusAddressState.provinceCode) {
            const municipalityItem = [...campusAddressData.municipalities, ...campusAddressData.cities].find((item) => item.provinceCode === campusAddressState.provinceCode && String(item.name || '').trim().toLowerCase() === municipalityName.toLowerCase()) || null;
            if (municipalityItem) {
                campusAddressState.municipalityCode = municipalityItem.code;
                campusAddressState.municipality = municipalityItem.name;
            } else {
                campusAddressState.municipalityCode = '';
                campusAddressState.municipality = municipalityName;
            }
        }

        if (barangayName && campusAddressState.municipalityCode) {
            const barangayItem = campusAddressData.barangays.find((item) => (item.cityCode || item.municipalityCode) === campusAddressState.municipalityCode && String(item.name || '').trim().toLowerCase() === barangayName.toLowerCase()) || null;
            if (barangayItem) {
                campusAddressState.barangayCode = barangayItem.code;
                campusAddressState.barangay = barangayItem.name;
            } else {
                campusAddressState.barangayCode = '';
                campusAddressState.barangay = barangayName;
            }
        }
    }

    const provinceOptions = sortCampusAddressItems(campusAddressData.provinces).map(toCampusAddressOption);
    provinceSelect.innerHTML = buildCampusAddressMarkup(provinceOptions, campusAddressState.provinceCode, 'Select province');

    const localityOptions = [...campusAddressData.municipalities, ...campusAddressData.cities]
        .filter((item) => item.provinceCode === campusAddressState.provinceCode)
        .map(toCampusAddressOption);
    municipalitySelect.innerHTML = buildCampusAddressMarkup(localityOptions, campusAddressState.municipalityCode, 'Select municipality or city');

    const barangayOptions = campusAddressData.barangays
        .filter((item) => (item.cityCode || item.municipalityCode) === campusAddressState.municipalityCode)
        .map(toCampusAddressOption);
    barangaySelect.innerHTML = buildCampusAddressMarkup(barangayOptions, campusAddressState.barangayCode, 'Select barangay');

    const hiddenAddressField = document.getElementById('campusAddress');
    if (hiddenAddressField) {
        hiddenAddressField.value = buildCampusAddress([campusAddressState.province, campusAddressState.municipality, campusAddressState.barangay]);
    }
}

async function loadCampusAddressData() {
    if (campusAddressLoaded || campusAddressLoading) return;

    campusAddressLoading = true;
    try {
        const [provinces, municipalities, cities, barangays] = await Promise.all([
            fetch(campusAddressApiUrls.provinces, { cache: 'force-cache' }).then((response) => {
                if (!response.ok) throw new Error('Unable to load provinces');
                return response.json();
            }),
            fetch(campusAddressApiUrls.municipalities, { cache: 'force-cache' }).then((response) => {
                if (!response.ok) throw new Error('Unable to load municipalities');
                return response.json();
            }),
            fetch(campusAddressApiUrls.cities, { cache: 'force-cache' }).then((response) => {
                if (!response.ok) throw new Error('Unable to load cities');
                return response.json();
            }),
            fetch(campusAddressApiUrls.barangays, { cache: 'force-cache' }).then((response) => {
                if (!response.ok) throw new Error('Unable to load barangays');
                return response.json();
            }),
        ]);

        campusAddressData.provinces = provinces;
        campusAddressData.municipalities = municipalities;
        campusAddressData.cities = cities;
        campusAddressData.barangays = barangays;
        campusAddressLoaded = true;
        renderCampusAddressSelects();
    } catch (error) {
        showToast('Unable to load address options.');
    } finally {
        campusAddressLoading = false;
    }
}

async function loadCurrentUser() {
    try {
        const response = await fetch(getSessionUrl(), {
            credentials: 'include',
            cache: 'no-store'
        });
        if (!response.ok) throw new Error('Session unavailable');
        const session = await response.json();
        if (!session.loggedIn) {
            window.location.replace(getLoginUrl());
            return;
        }
        const user = session.user || {};
        const role = String(user.role || '').toLowerCase();
        if (!DEVELOPER_ROLES.includes(role)) {
            window.location.replace(role === 'head_admin' ? '../staff/Dashboard.html' : '../alumni/Main.html');
            return;
        }
        document.querySelector('[data-developer-name]').textContent = user.name || 'System Administrator';
        document.querySelector('[data-developer-role]').textContent = String(user.role || 'developer').replaceAll('_', ' ');
    } catch (error) {
        window.location.replace(getLoginUrl());
    }
}

async function logoutDeveloper() {
    const confirmed = await askConfirmation({
        title: 'Confirm Logout',
        message: 'Are you sure you want to log out of the Developer Console?',
        confirmText: 'Log Out',
        danger: true,
        icon: 'fa-right-from-bracket'
    });
    if (!confirmed) return;

    try {
        await fetch(getLogoutUrl(), {
            method: 'POST',
            credentials: 'same-origin'
        });
    } finally {
        window.location.replace(getLoginUrl());
    }
}

function switchPanel(sectionName, clickedItem = null) {
    if (!sectionName) {
        if (clickedItem) {
            document.querySelectorAll('.developer-nav-item').forEach((item) => {
                item.classList.toggle('active', item === clickedItem);
            });
        }
        return;
    }

    document.querySelectorAll('.developer-nav-item').forEach((item) => {
        item.classList.toggle('active', item.dataset.section === sectionName);
    });
    document.querySelectorAll('.developer-panel').forEach((panel) => {
        panel.classList.toggle('active', panel.dataset.panel === sectionName);
    });

    const activeLabel = document.querySelector(`.developer-nav-item[data-section="${sectionName}"] span`)?.textContent || 'Developer Overview';
    const titleEl = document.getElementById('developerPageTitle');
    if (titleEl) {
        titleEl.textContent = sectionName === 'overview' ? 'Developer Overview' : activeLabel;
    }
}

function isHeadAdminUser(admin) {
    const roleValue = String(admin?.roleValue || '').trim().toLowerCase().replaceAll(' ', '_');
    const roleLabel = String(admin?.role || '').trim().toLowerCase().replaceAll('_', ' ');
    return roleValue === 'head_admin' || roleLabel === 'head admin';
}

function renderMetrics() {
    const headAdmins = developerUsers.filter(isHeadAdminUser);
    const locked = headAdmins.filter((admin) => admin.status === 'Locked');
    const telemetry = developerOverviewTelemetry || {};
    const accounts = telemetry.accounts || {};
    const alumniDir = telemetry.alumniDirectory || {};
    const academic = telemetry.academicUnits || {};
    const modules = telemetry.modules || {};
    const system = telemetry.system || {};

    const totalHeadAdmins = typeof accounts.headAdmins === 'number' ? accounts.headAdmins : headAdmins.length;
    const assignedHeadAdmins = typeof accounts.assignedHeadAdmins === 'number'
        ? accounts.assignedHeadAdmins
        : headAdmins.filter((admin) => String(admin.campus || '').trim() !== '').length;
    const unassignedHeadAdmins = Math.max(0, totalHeadAdmins - assignedHeadAdmins);

    const headAdminCount = document.getElementById('headAdminCount');
    if (headAdminCount) headAdminCount.textContent = totalHeadAdmins;

    const headAdminSub = document.getElementById('headAdminSub');
    if (headAdminSub) headAdminSub.textContent = `${assignedHeadAdmins} assigned · ${unassignedHeadAdmins} unassigned`;

    const campusUnitCount = document.getElementById('campusUnitCount');
    if (campusUnitCount) {
        const campusTotal = typeof academic.totalCampuses === 'number' ? academic.totalCampuses : developerCampuses.length;
        campusUnitCount.textContent = campusTotal;
    }

    const campusUnitSub = document.getElementById('campusUnitSub');
    if (campusUnitSub) {
        campusUnitSub.textContent = `${academic.totalColleges ?? 0} colleges · ${academic.totalDepartments ?? 0} departments`;
    }

    const totalPlatformUsers = document.getElementById('totalPlatformUsers');
    if (totalPlatformUsers) totalPlatformUsers.textContent = alumniDir.total ?? 0;

    const totalPlatformUsersSub = document.getElementById('totalPlatformUsersSub');
    if (totalPlatformUsersSub) {
        totalPlatformUsersSub.textContent = `${alumniDir.active ?? 0} active · ${alumniDir.pending ?? 0} pending`;
    }

    const surveyResponseCount = document.getElementById('surveyResponseCount');
    if (surveyResponseCount) surveyResponseCount.textContent = modules.totalResponses ?? 0;

    const surveyResponseSub = document.getElementById('surveyResponseSub');
    if (surveyResponseSub) {
        surveyResponseSub.textContent = `${modules.activeSurveys ?? 0} active of ${modules.totalSurveys ?? 0} surveys`;
    }

    const dbHealthStatus = document.getElementById('dbHealthStatus');
    if (dbHealthStatus) {
        dbHealthStatus.textContent = system.dbConnected === false ? 'Degraded' : 'Online';
    }

    const dbHealthSub = document.getElementById('dbHealthSub');
    if (dbHealthSub) {
        const pingText = system.dbPingMs !== undefined ? `${system.dbPingMs} ms` : 'Connected';
        const phpText = system.phpVersion ? `PHP ${system.phpVersion}` : 'Runtime OK';
        dbHealthSub.textContent = `${pingText} · ${phpText}`;
    }

    const platformContentCount = document.getElementById('platformContentCount');
    if (platformContentCount) {
        const totalItems = (modules.totalEvents ?? 0) + (modules.totalNews ?? 0) + (modules.totalPayments ?? 0);
        platformContentCount.textContent = totalItems;
    }

    const platformContentSub = document.getElementById('platformContentSub');
    if (platformContentSub) {
        platformContentSub.textContent = `${modules.totalEvents ?? 0} events · ${modules.totalNews ?? 0} news · ${modules.totalPayments ?? 0} payments`;
    }

    const lockedAccountCount = document.getElementById('lockedAccountCount');
    if (lockedAccountCount) lockedAccountCount.textContent = locked.length;

    const maintenanceStatus = document.getElementById('maintenanceStatus');
    if (maintenanceStatus) maintenanceStatus.textContent = developerState.maintenance.enabled ? 'On' : 'Off';

    const maintenanceSub = document.getElementById('maintenanceSub');
    if (maintenanceSub) {
        maintenanceSub.textContent = developerState.maintenance.enabled ? 'Public access restricted' : 'Normal user access';
    }

    const maintenanceMetricIcon = document.getElementById('maintenanceMetricIcon');
    if (maintenanceMetricIcon) {
        maintenanceMetricIcon.className = developerState.maintenance.enabled ? 'fas fa-toggle-on' : 'fas fa-toggle-off';
    }

    const quickMaintenanceLabel = document.getElementById('quickMaintenanceLabel');
    if (quickMaintenanceLabel) {
        quickMaintenanceLabel.textContent = developerState.maintenance.enabled ? 'Disable Maintenance' : 'Enable Maintenance';
    }

    const lastBackupDate = document.getElementById('lastBackupDate');
    if (lastBackupDate) lastBackupDate.textContent = developerState.backups[0]?.date || 'None';

    const overviewLockPolicy = document.getElementById('overviewLockPolicy');
    if (overviewLockPolicy) overviewLockPolicy.textContent = `${developerState.settings.failedLoginLimit} failed attempts`;

    const overviewEmailProvider = document.getElementById('overviewEmailProvider');
    if (overviewEmailProvider) overviewEmailProvider.textContent = developerState.messaging.emailProvider || 'Not set';

    const overviewSmsProvider = document.getElementById('overviewSmsProvider');
    if (overviewSmsProvider) overviewSmsProvider.textContent = developerState.messaging.smsEnabled ? developerState.messaging.smsProvider : 'Disabled';

    const overviewTemplateCount = document.getElementById('overviewTemplateCount');
    if (overviewTemplateCount) overviewTemplateCount.textContent = developerState.surveyTemplates.filter((template) => template.status === 'Active').length;

    renderOverviewDashboard();
}

function renderOverviewDashboard() {
    const matrixBody = document.getElementById('overviewCampusMatrixBody');
    if (matrixBody) {
        if (developerCampuses.length === 0) {
            matrixBody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--muted);">No campuses registered yet. Add one in School Info.</td></tr>';
        } else {
            matrixBody.innerHTML = developerCampuses.map((campus) => {
                const headEmail = String(campus.alumniHeadEmail || '').trim();
                const matchedAdmin = developerUsers.find((u) => String(u.email || '').toLowerCase() === headEmail.toLowerCase());
                const adminDisplay = matchedAdmin
                    ? `${matchedAdmin.firstName} ${matchedAdmin.lastName} (${matchedAdmin.email})`
                    : (headEmail || 'Unassigned');
                const isAssigned = headEmail !== '';
                const pillClass = isAssigned ? 'status-active' : 'status-queued';
                const pillLabel = isAssigned ? 'Assigned' : 'Needs Head Admin';

                return `
                    <tr>
                        <td><strong>${escapeHtml(campus.campus)}</strong></td>
                        <td>${escapeHtml(campus.abbreviation)}</td>
                        <td>${escapeHtml(adminDisplay)}</td>
                        <td><span class="status-pill ${pillClass}">${pillLabel}</span></td>
                    </tr>
                `;
            }).join('');
        }
    }

    const telemetry = developerOverviewTelemetry || {};
    const accounts = telemetry.accounts || {};
    const academic = telemetry.academicUnits || {};
    const modules = telemetry.modules || {};
    const system = telemetry.system || {};

    const checklistEl = document.getElementById('overviewHealthChecklist');
    if (checklistEl) {
        const totalCampuses = academic.totalCampuses ?? developerCampuses.length;
        const assignedCampuses = academic.assignedCampuses ?? developerCampuses.filter((c) => String(c.alumniHeadEmail || '').trim() !== '').length;
        const campusHealthy = totalCampuses > 0 && assignedCampuses === totalCampuses;

        const totalDepts = academic.totalDepartments ?? 0;
        const assignedDepts = academic.assignedDepartments ?? 0;
        const deptHealthy = totalDepts > 0 && assignedDepts === totalDepts;

        const totalAlumniAccts = accounts.alumniAccounts ?? 0;
        const linkedAlumniAccts = accounts.linkedAlumniAccounts ?? 0;
        const linkageHealthy = totalAlumniAccts === 0 || linkedAlumniAccts === totalAlumniAccts;

        const activeSurveys = modules.activeSurveys ?? 0;
        const pendingReviews = modules.pendingResponses ?? 0;

        const checks = [
            {
                icon: 'fa-building-user',
                label: `Campus Head Admin coverage (${assignedCampuses}/${totalCampuses})`,
                status: campusHealthy ? 'Healthy' : 'Attention',
                pill: campusHealthy ? 'status-active' : 'status-queued',
            },
            {
                icon: 'fa-sitemap',
                label: `Department coordinators (${assignedDepts}/${totalDepts})`,
                status: deptHealthy ? 'Complete' : 'Partial',
                pill: deptHealthy ? 'status-active' : 'status-queued',
            },
            {
                icon: 'fa-id-card-clip',
                label: `Alumni account ID linkage (${linkedAlumniAccts}/${totalAlumniAccts})`,
                status: linkageHealthy ? 'Synced' : 'Review',
                pill: linkageHealthy ? 'status-active' : 'status-queued',
            },
            {
                icon: 'fa-poll-h',
                label: `Active tracer surveys (${activeSurveys}) · Pending reviews (${pendingReviews})`,
                status: activeSurveys > 0 ? 'Collecting' : 'Idle',
                pill: activeSurveys > 0 ? 'status-active' : 'status-queued',
            },
            {
                icon: 'fa-hard-drive',
                label: `Storage & DB (${system.writableStatus !== false ? 'Writable OK' : 'Read-Only'})`,
                status: system.writableStatus !== false ? 'Ready' : 'Warning',
                pill: system.writableStatus !== false ? 'status-active' : 'status-locked',
            },
        ];

        checklistEl.innerHTML = checks.map((item) => `
            <div class="control-row">
                <span><i class="fas ${escapeHtml(item.icon)}"></i> ${escapeHtml(item.label)}</span>
                <span class="status-pill ${escapeHtml(item.pill)}">${escapeHtml(item.status)}</span>
            </div>
        `).join('');
    }

    const roleDistEl = document.getElementById('overviewRoleDistribution');
    if (roleDistEl) {
        const totalAccts = Math.max(1, accounts.total || 1);
        const roles = [
            { label: 'Head Admins', count: accounts.headAdmins ?? developerUsers.length, sub: `${accounts.assignedHeadAdmins ?? 0} assigned to campus` },
            { label: 'Coordinators', count: accounts.coordinators ?? 0, sub: `${academic.assignedDepartments ?? 0} dept assignments` },
            { label: 'Alumni Accounts', count: accounts.alumniAccounts ?? 0, sub: `${accounts.linkedAlumniAccounts ?? 0} ID-linked` },
            { label: 'Developers', count: accounts.developers ?? 1, sub: 'System administrators' },
        ];

        roleDistEl.innerHTML = roles.map((item) => {
            const pct = Math.min(100, Math.round((item.count / totalAccts) * 100));
            return `
                <div class="role-dist-card">
                    <div class="role-dist-head">
                        <span>${escapeHtml(item.label)}</span>
                        <strong>${escapeHtml(item.count)}</strong>
                    </div>
                    <div class="role-dist-bar"><span style="width: ${pct}%"></span></div>
                    <div class="role-dist-meta">${escapeHtml(item.sub)}</div>
                </div>
            `;
        }).join('');
    }
}

async function loadOverviewTelemetry(showFeedback = false) {
    try {
        const response = await fetch('../developer/overview-data', {
            credentials: 'include',
            cache: 'no-store'
        });
        if (!response.ok) throw new Error('Unable to load overview telemetry');
        const data = await response.json();
        if (data && data.success && data.telemetry) {
            developerOverviewTelemetry = data.telemetry;
            const lastSyncedEl = document.getElementById('overviewLastSynced');
            if (lastSyncedEl) {
                lastSyncedEl.textContent = `Last synced: ${data.telemetry.system?.serverTime || new Date().toLocaleTimeString()} · DB ping ${data.telemetry.system?.dbPingMs ?? 0} ms`;
            }
            renderMetrics();
            renderLogs();
            if (showFeedback) {
                showToast('Live platform telemetry refreshed.');
            }
        }
    } catch (error) {
        const lastSyncedEl = document.getElementById('overviewLastSynced');
        if (lastSyncedEl) {
            lastSyncedEl.textContent = 'Using cached console state (unable to reach telemetry endpoint).';
        }
    }
}

function renderAdminTable() {
    const tbody = document.getElementById('adminTableBody');
    if (!tbody) return;

    const searchInput = document.getElementById('adminSearch');
    const search = searchInput ? searchInput.value.trim().toLowerCase() : '';

    const visibleUsers = developerUsers.filter((admin) => {
        if (!isHeadAdminUser(admin)) {
            return false;
        }

        const haystack = `${admin.firstName} ${admin.lastName} ${admin.email} ${admin.department}`.toLowerCase();
        return haystack.includes(search);
    });

    if (visibleUsers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--muted);">No Head Admin accounts found.</td></tr>';
        return;
    }

    tbody.innerHTML = visibleUsers.map((admin) => {
        const campusValue = (admin.campus && String(admin.campus).trim() !== '') ? String(admin.campus) : 'Not Assigned';
        return `
            <tr>
                <td><strong>${escapeHtml(admin.firstName)} ${escapeHtml(admin.lastName)}</strong></td>
                <td>${escapeHtml(admin.email)}</td>
                <td>${escapeHtml(admin.department)}</td>
                <td>${escapeHtml(admin.role)}</td>
                <td>${escapeHtml(campusValue)}</td>
                <td>
                    <div class="action-set">
                        <button class="icon-button" type="button" title="Edit account" aria-label="Edit account" onclick="editAdmin('${escapeHtml(admin.email)}')"><i class="fas fa-pen"></i></button>
                        <button class="icon-button" type="button" title="Reset password" aria-label="Reset password" onclick="resetAdminPassword('${escapeHtml(admin.email)}')"><i class="fas fa-key"></i></button>
                        <button class="icon-button" type="button" title="Lock or unlock" aria-label="Lock or unlock" onclick="toggleAdminLock('${escapeHtml(admin.email)}')"><i class="fas fa-lock"></i></button>
                        <button class="icon-button danger" type="button" title="Delete account" aria-label="Delete account" onclick="deleteAdmin('${escapeHtml(admin.email)}')"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function populateRoleDropdown() {
    const select = document.getElementById('adminRole');
    const filterSelect = document.getElementById('roleFilter');

    const options = (typeof DEVELOPER_ROLE_OPTIONS !== 'undefined' && Array.isArray(DEVELOPER_ROLE_OPTIONS) && DEVELOPER_ROLE_OPTIONS.length > 0)
        ? DEVELOPER_ROLE_OPTIONS
        : [{ value: 'head_admin', label: 'Head Admin' }];

    if (select) {
        select.innerHTML = options.map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`).join('');
    }
    if (filterSelect) {
        filterSelect.innerHTML = options.map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`).join('');
    }
}

function openAdminModal(admin = null) {
    editingAdminId = admin?.email || null;
    document.getElementById('adminModalTitle').textContent = admin ? 'Edit Head Admin' : 'Add Head Admin';
    document.getElementById('adminFirstName').value = admin?.firstName || '';
    document.getElementById('adminLastName').value = admin?.lastName || '';
    document.getElementById('adminEmail').value = admin?.email || '';
    document.getElementById('adminDepartment').value = admin?.department || '';
    document.getElementById('adminRole').value = 'head_admin';
    document.getElementById('adminStatus').value = admin?.status || 'Active';
    const modal = document.getElementById('adminModal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function closeAdminModal() {
    const modal = document.getElementById('adminModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

async function saveAdminAccount() {
    const account = {
        firstName: document.getElementById('adminFirstName').value.trim(),
        lastName: document.getElementById('adminLastName').value.trim(),
        email: document.getElementById('adminEmail').value.trim(),
        department: document.getElementById('adminDepartment').value.trim(),
        role: document.getElementById('adminRole').value,
        status: document.getElementById('adminStatus').value,
        existingEmail: editingAdminId || ''
    };

    if (!account.firstName || !account.lastName || !account.email) {
        showToast('Please complete the required account fields.');
        return;
    }

    const isEdit = Boolean(editingAdminId);
    const confirmed = await askConfirmation({
        title: isEdit ? 'Confirm Update Account' : 'Confirm Add Head Admin',
        message: isEdit
            ? `Are you sure you want to save changes to the Head Admin account for ${account.firstName} ${account.lastName} (${account.email})?`
            : `Are you sure you want to create a new Head Admin account for ${account.firstName} ${account.lastName} (${account.email})?`,
        confirmText: isEdit ? 'Save Changes' : 'Create Account',
        icon: 'fa-user-shield'
    });
    if (!confirmed) return;

    try {
        const response = await fetch('../developer/users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(account)
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Could not save the account.');
        }

        closeAdminModal();
        await loadDeveloperUsers();
        showToast('Account saved.');
    } catch (error) {
        showToast(error.message || 'Account save failed.');
    }
}

function editAdmin(email) {
    const admin = developerUsers.find((item) => item.email === email);
    if (admin) openAdminModal(admin);
}

async function resetAdminPassword(email) {
    const admin = developerUsers.find((item) => item.email === email);
    if (!admin) return;

    const confirmed = await askConfirmation({
        title: 'Confirm Password Reset',
        message: `Are you sure you want to reset the password for ${admin.firstName} ${admin.lastName} (${admin.email})?`,
        confirmText: 'Reset Password',
        icon: 'fa-key'
    });
    if (!confirmed) return;

    try {
        const response = await fetch('../developer/users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ ...admin, resetPassword: true, existingEmail: admin.email })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Password reset failed.');
        }
        showToast('Password reset flag created.');
    } catch (error) {
        showToast(error.message || 'Password reset failed.');
    }
}

async function toggleAdminLock(email) {
    const admin = developerUsers.find((item) => item.email === email);
    if (!admin) return;

    const nextStatus = admin.status === 'Locked' ? 'Active' : 'Locked';
    const actionLabel = nextStatus === 'Locked' ? 'Lock' : 'Unlock';
    const confirmed = await askConfirmation({
        title: `Confirm ${actionLabel} Account`,
        message: `Are you sure you want to ${actionLabel.toLowerCase()} the Head Admin account for ${admin.firstName} ${admin.lastName} (${admin.email})?`,
        confirmText: `${actionLabel} Account`,
        danger: nextStatus === 'Locked',
        icon: 'fa-lock'
    });
    if (!confirmed) return;

    try {
        const response = await fetch('../developer/users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ ...admin, status: nextStatus, existingEmail: admin.email })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Status update failed.');
        }
        await loadDeveloperUsers();
    } catch (error) {
        showToast(error.message || 'Status update failed.');
    }
}

async function deleteAdmin(email) {
    const admin = developerUsers.find((item) => item.email === email);
    if (!admin) return;

    const confirmed = await askConfirmation({
        title: 'Confirm Delete Account',
        message: `Are you sure you want to permanently delete the Head Admin account for ${admin.firstName} ${admin.lastName} (${admin.email})? This action cannot be undone.`,
        confirmText: 'Delete Account',
        danger: true,
        icon: 'fa-trash'
    });
    if (!confirmed) return;

    try {
        const response = await fetch(`../developer/users/${encodeURIComponent(admin.email)}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Delete failed.');
        }
        await loadDeveloperUsers();
        showToast('Account deleted.');
    } catch (error) {
        showToast(error.message || 'Account deletion failed.');
    }
}

function addLog(level, title, detail) {
    developerState.logs.unshift({
        level,
        title,
        detail,
        time: 'Just now'
    });
}

async function loadDeveloperUsers() {
    try {
        const response = await fetch('../developer/users', {
            credentials: 'include',
            cache: 'no-store'
        });
        if (!response.ok) throw new Error('Unable to load users');
        const data = await response.json();
        developerUsers = (Array.isArray(data.users) ? data.users : []).filter(isHeadAdminUser);
        populateCampusAlumniHeadOptions();
        if (Array.isArray(data.roles)) {
            const options = data.roles.filter((option) => String(option.value || '').toLowerCase() === 'head_admin');
            const select = document.getElementById('adminRole');
            const filterSelect = document.getElementById('roleFilter');
            if (select && options.length > 0) {
                select.innerHTML = options.map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`).join('');
            }
            if (filterSelect && options.length > 0) {
                filterSelect.innerHTML = options.map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`).join('');
            }
        }
        renderMetrics();
        renderAdminTable();
    } catch (error) {
        showToast('Unable to sync user list.');
    }
}

function renderSettingsForms() {
    Object.entries(developerState.settings).forEach(([key, value]) => {
        const field = document.getElementById(key);
        if (!field) return;
        if (field.type === 'checkbox') field.checked = Boolean(value);
        else field.value = value;
    });

    Object.entries(developerState.school).forEach(([key, value]) => {
        const field = document.getElementById(key);
        if (field) field.value = value;
    });

    Object.entries(developerState.messaging).forEach(([key, value]) => {
        const field = document.getElementById(key);
        if (!field) return;
        if (field.type === 'checkbox') field.checked = Boolean(value);
        else field.value = value;
    });

    const maintenanceToggle = document.getElementById('maintenanceToggle');
    if (maintenanceToggle) maintenanceToggle.checked = developerState.maintenance.enabled;

    const maintenanceMessage = document.getElementById('maintenanceMessage');
    if (maintenanceMessage) maintenanceMessage.value = developerState.maintenance.message;

    const maintenanceEnd = document.getElementById('maintenanceEnd');
    if (maintenanceEnd) maintenanceEnd.value = developerState.maintenance.end;
}

async function saveSystemSettings() {
    const confirmed = await askConfirmation({
        title: 'Confirm System Settings',
        message: 'Are you sure you want to save and apply these system settings?',
        confirmText: 'Save Settings',
        icon: 'fa-sliders-h'
    });
    if (!confirmed) return;

    developerState.settings = {
        appName: document.getElementById('appName').value.trim(),
        supportEmail: document.getElementById('supportEmail').value.trim(),
        sessionTimeout: document.getElementById('sessionTimeout').value,
        failedLoginLimit: document.getElementById('failedLoginLimit').value,
        forceResetNewAdmins: document.getElementById('forceResetNewAdmins').checked,
        notifyOnLock: document.getElementById('notifyOnLock').checked,
        restrictStaffSettings: document.getElementById('restrictStaffSettings').checked
    };
    addLog('info', 'System settings saved', 'Application and security settings were updated.');
    saveDeveloperState();
    renderAll();
    showToast('System settings saved.');
}

async function saveSchoolInfo() {
    const confirmed = await askConfirmation({
        title: 'Confirm School Information',
        message: 'Are you sure you want to update the institution profile details?',
        confirmText: 'Save Changes',
        icon: 'fa-school'
    });
    if (!confirmed) return;

    developerState.school = {
        schoolName: document.getElementById('schoolName').value.trim(),
        schoolCampus: document.getElementById('schoolCampus').value.trim(),
        registrarEmail: document.getElementById('registrarEmail').value.trim(),
        schoolAddress: document.getElementById('schoolAddress').value.trim(),
        schoolPhone: document.getElementById('schoolPhone').value.trim()
    };
    addLog('info', 'School information saved', 'Institution profile details were updated.');
    saveDeveloperState();
    renderAll();
    showToast('School information saved.');
}

async function saveMessagingSettings() {
    const confirmed = await askConfirmation({
        title: 'Confirm Messaging Settings',
        message: 'Are you sure you want to save the Email and SMS configuration?',
        confirmText: 'Save Settings',
        icon: 'fa-envelope-open-text'
    });
    if (!confirmed) return;

    developerState.messaging = {
        emailProvider: document.getElementById('emailProvider').value.trim(),
        smtpHost: document.getElementById('smtpHost').value.trim(),
        senderEmail: document.getElementById('senderEmail').value.trim(),
        senderName: document.getElementById('senderName').value.trim(),
        smsProvider: document.getElementById('smsProvider').value.trim(),
        smsSenderId: document.getElementById('smsSenderId').value.trim(),
        smsEnabled: document.getElementById('smsEnabled').checked
    };
    addLog('info', 'Email and SMS settings saved', 'Messaging configuration was updated.');
    saveDeveloperState();
    renderAll();
    showToast('Messaging settings saved.');
}

function renderBackups() {
    const tbody = document.getElementById('backupTableBody');
    if (tbody) {
        if (!Array.isArray(developerState.backups) || developerState.backups.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--muted);">No Supabase SQL backups found yet. Click "Create Supabase Backup (.sql)" to generate one.</td></tr>';
        } else {
            tbody.innerHTML = developerState.backups.map((backup) => `
                <tr>
                    <td><strong>${escapeHtml(backup.name)}</strong></td>
                    <td>${escapeHtml(backup.format || 'Supabase PostgreSQL (.sql)')}</td>
                    <td>${escapeHtml(backup.date)}</td>
                    <td>${escapeHtml(backup.size)}</td>
                    <td><span class="status-pill status-active">${escapeHtml(backup.status)}</span></td>
                    <td>${escapeHtml(backup.createdBy)}</td>
                    <td>
                        <div class="action-set">
                            <button class="icon-button" type="button" title="Download Supabase .sql file" aria-label="Download Supabase .sql file" onclick="downloadBackupFile('${escapeHtml(backup.name)}')"><i class="fas fa-download"></i></button>
                            <button class="icon-button" type="button" title="Restore snapshot to Supabase" aria-label="Restore snapshot to Supabase" onclick="restoreBackupFile('${escapeHtml(backup.name)}')"><i class="fas fa-rotate-left"></i></button>
                            <button class="icon-button danger" type="button" title="Delete snapshot" aria-label="Delete snapshot" onclick="deleteBackupFile('${escapeHtml(backup.name)}')"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }
    }

    const select = document.getElementById('restorePointSelect');
    if (select) {
        if (!Array.isArray(developerState.backups) || developerState.backups.length === 0) {
            select.innerHTML = '<option value="">No backups available</option>';
        } else {
            select.innerHTML = developerState.backups.map((backup) => (
                `<option value="${escapeHtml(backup.name)}">${escapeHtml(backup.name)} (${escapeHtml(backup.date)})</option>`
            )).join('');
        }
    }
}

async function loadDeveloperBackups() {
    try {
        const response = await fetch('../developer/backups', {
            credentials: 'include',
            cache: 'no-store'
        });
        if (!response.ok) return;
        const data = await response.json();
        if (data && data.success && Array.isArray(data.backups)) {
            developerState.backups = data.backups;
            saveDeveloperState();
            renderBackups();
            renderMetrics();
        }
    } catch (error) {
        // Fallback to cached state
    }
}

function downloadBackupFile(filename) {
    if (!filename) return;
    const downloadUrl = `../developer/backups/download/${encodeURIComponent(filename)}`;
    const anchor = document.createElement('a');
    anchor.href = downloadUrl;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
}

async function createBackup() {
    const confirmed = await askConfirmation({
        title: 'Create Supabase PostgreSQL Backup',
        message: 'Are you sure you want to export all public schema tables, rows, sequences, and RLS policies into a Supabase-compatible .sql backup?',
        confirmText: 'Create Backup (.sql)',
        icon: 'fa-database'
    });
    if (!confirmed) return;

    try {
        showToast('Generating Supabase PostgreSQL (.sql) dump...');
        const response = await fetch('../developer/backups/create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include'
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Could not generate Supabase backup.');
        }

        if (Array.isArray(data.backups)) {
            developerState.backups = data.backups;
        } else if (data.backup) {
            developerState.backups.unshift(data.backup);
        }

        const created = data.backup || developerState.backups[0];
        addLog(
            'info',
            'Supabase SQL backup created',
            `${created?.name || 'supabase_backup.sql'} (${created?.tablesCount ?? 0} tables, ${created?.rowsCount ?? 0} rows) exported.`
        );
        saveDeveloperState();
        renderAll();

        const autoDownloadToggle = document.getElementById('autoDownloadBackupSql');
        const shouldAutoDownload = !autoDownloadToggle || autoDownloadToggle.checked;
        if (shouldAutoDownload && created?.name) {
            downloadBackupFile(created.name);
        }

        showToast('Supabase .sql backup created and ready for import.');
    } catch (error) {
        showToast(error.message || 'Supabase backup generation failed.');
    }
}

async function restoreBackupFile(filename) {
    if (!filename) {
        showToast('No backup snapshot selected.');
        return;
    }

    const confirmed = await askConfirmation({
        title: 'Confirm Restore to Supabase',
        message: `Are you sure you want to execute and restore "${filename}" into the connected Supabase PostgreSQL database? Existing rows with matching primary keys will be updated.`,
        confirmText: 'Restore to Supabase',
        danger: true,
        icon: 'fa-rotate-left'
    });
    if (!confirmed) return;

    try {
        showToast(`Restoring ${filename} to Supabase...`);
        const response = await fetch('../developer/backups/restore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ filename })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Restore execution failed.');
        }

        addLog('critical', 'Supabase database restored', `${filename} was executed and restored onto the database.`);
        saveDeveloperState();
        await Promise.all([
            loadOverviewTelemetry(),
            loadDeveloperUsers(),
            loadCampuses()
        ]);
        renderAll();
        showToast(data.message || 'Snapshot restored to Supabase.');
    } catch (error) {
        showToast(error.message || 'Database restore failed.');
    }
}

async function deleteBackupFile(filename) {
    if (!filename) return;

    const confirmed = await askConfirmation({
        title: 'Confirm Delete Backup',
        message: `Are you sure you want to permanently delete the backup file "${filename}"?`,
        confirmText: 'Delete Backup',
        danger: true,
        icon: 'fa-trash'
    });
    if (!confirmed) return;

    try {
        const response = await fetch(`../developer/backups/${encodeURIComponent(filename)}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Could not delete backup.');
        }

        developerState.backups = Array.isArray(data.backups)
            ? data.backups
            : developerState.backups.filter((b) => b.name !== filename);
        addLog('warning', 'Backup deleted', `${filename} was removed.`);
        saveDeveloperState();
        renderAll();
        showToast('Backup snapshot deleted.');
    } catch (error) {
        showToast(error.message || 'Backup deletion failed.');
    }
}

async function queueRestore() {
    const selected = document.getElementById('restorePointSelect')?.value || '';
    if (!selected) {
        showToast('No backup is available to restore.');
        return;
    }
    await restoreBackupFile(selected);
}

function renderLogs() {
    const dbActivities = Array.isArray(developerOverviewTelemetry?.recentActivities)
        ? developerOverviewTelemetry.recentActivities.map((act) => ({
            level: act.category === 'auth' ? 'info' : 'info',
            title: act.action || 'System Activity',
            detail: `${act.userType ? `[${act.userType.toUpperCase()}] ` : ''}${act.description || act.category || 'Database event'}${act.ipAddress ? ` · IP ${act.ipAddress}` : ''}`,
            time: act.createdAt || 'Recent'
        }))
        : [];

    const combinedLogs = [...dbActivities, ...developerState.logs];
    const logLevelFilter = document.getElementById('logLevelFilter');
    const logLevel = logLevelFilter ? logLevelFilter.value : 'all';
    const logs = combinedLogs.filter((log) => logLevel === 'all' || log.level === logLevel);
    const html = logs.map((log) => `
        <article class="log-item">
            <span class="level-pill level-${escapeHtml(log.level)}">${escapeHtml(log.level)}</span>
            <div class="log-message">
                <strong>${escapeHtml(log.title)}</strong>
                <span>${escapeHtml(log.detail)}</span>
            </div>
            <time class="log-time">${escapeHtml(log.time)}</time>
        </article>
    `).join('');

    const systemLogFeed = document.getElementById('systemLogFeed');
    if (systemLogFeed) {
        systemLogFeed.innerHTML = html || '<p class="empty-state">No logs match this filter.</p>';
    }

    const overviewLogs = document.getElementById('overviewLogs');
    if (overviewLogs) {
        overviewLogs.innerHTML = combinedLogs.slice(0, 6).map((log) => `
            <article class="log-item">
                <span class="level-pill level-${escapeHtml(log.level)}">${escapeHtml(log.level)}</span>
                <div class="log-message">
                    <strong>${escapeHtml(log.title)}</strong>
                    <span>${escapeHtml(log.detail)}</span>
                </div>
                <time class="log-time">${escapeHtml(log.time)}</time>
            </article>
        `).join('');
    }
}

async function publishAnnouncement() {
    const title = document.getElementById('announcementTitle').value.trim();
    const body = document.getElementById('announcementBody').value.trim();
    if (!title || !body) {
        showToast('Enter an announcement title and details.');
        return;
    }

    const confirmed = await askConfirmation({
        title: 'Confirm Publish Announcement',
        message: `Are you sure you want to publish the announcement "${title}"?`,
        confirmText: 'Publish',
        icon: 'fa-bullhorn'
    });
    if (!confirmed) return;

    developerState.announcements.unshift({
        title,
        body,
        date: new Date().toLocaleDateString([], { dateStyle: 'medium' })
    });
    document.getElementById('announcementTitle').value = '';
    document.getElementById('announcementBody').value = '';
    addLog('info', 'Announcement published', title);
    saveDeveloperState();
    renderAll();
    showToast('Announcement published.');
}

function renderAnnouncements() {
    const announcementList = document.getElementById('announcementList');
    if (announcementList) {
        announcementList.innerHTML = developerState.announcements.map((announcement, index) => `
            <article class="announcement-item">
                <h4>${escapeHtml(announcement.title)}</h4>
                <p>${escapeHtml(announcement.body)}</p>
                <div class="template-meta">
                    <span>${escapeHtml(announcement.date)}</span>
                    <button class="icon-button danger" type="button" title="Remove announcement" aria-label="Remove announcement" onclick="removeAnnouncement(${index})"><i class="fas fa-trash"></i></button>
                </div>
            </article>
        `).join('');
    }
}

async function removeAnnouncement(index) {
    const target = developerState.announcements[index];
    if (!target) return;

    const confirmed = await askConfirmation({
        title: 'Confirm Remove Announcement',
        message: `Are you sure you want to remove the announcement "${target.title}"?`,
        confirmText: 'Remove',
        danger: true,
        icon: 'fa-trash'
    });
    if (!confirmed) return;

    const removed = developerState.announcements.splice(index, 1)[0];
    addLog('warning', 'Announcement removed', removed?.title || 'Announcement removed.');
    saveDeveloperState();
    renderAll();
    showToast('Announcement removed.');
}

function renderSurveyTemplates() {
    const surveyTemplateGrid = document.getElementById('surveyTemplateGrid');
    if (surveyTemplateGrid) {
        surveyTemplateGrid.innerHTML = developerState.surveyTemplates.map((template, index) => `
            <article class="template-card">
                <h4>${escapeHtml(template.name)}</h4>
                <p>${escapeHtml(template.description)}</p>
                <div class="template-meta">
                    <span>${escapeHtml(template.questions)} questions</span>
                    <span class="status-pill ${template.status === 'Active' ? 'status-active' : 'status-queued'}">${escapeHtml(template.status)}</span>
                </div>
                <div class="template-meta">
                    <button class="btn-secondary" type="button" onclick="toggleTemplateStatus(${index})">Toggle Status</button>
                    <button class="icon-button danger" type="button" title="Delete template" aria-label="Delete template" onclick="deleteTemplate(${index})"><i class="fas fa-trash"></i></button>
                </div>
            </article>
        `).join('');
    }
}

async function addSurveyTemplate() {
    const nextNumber = developerState.surveyTemplates.length + 1;
    const confirmed = await askConfirmation({
        title: 'Confirm Add Survey Template',
        message: `Are you sure you want to create "Custom Template ${nextNumber}"?`,
        confirmText: 'Create Template',
        icon: 'fa-file-signature'
    });
    if (!confirmed) return;

    developerState.surveyTemplates.unshift({
        name: `Custom Template ${nextNumber}`,
        description: 'New reusable survey template for administrators.',
        questions: 0,
        status: 'Draft'
    });
    addLog('info', 'Survey template added', `Custom Template ${nextNumber} was created.`);
    saveDeveloperState();
    renderAll();
    showToast('Survey template added.');
}

async function toggleTemplateStatus(index) {
    const template = developerState.surveyTemplates[index];
    if (!template) return;
    const nextStatus = template.status === 'Active' ? 'Draft' : 'Active';

    const confirmed = await askConfirmation({
        title: 'Confirm Template Status Change',
        message: `Are you sure you want to change "${template.name}" status to ${nextStatus}?`,
        confirmText: `Set to ${nextStatus}`
    });
    if (!confirmed) return;

    template.status = nextStatus;
    addLog('info', 'Survey template status changed', `${template.name} is now ${template.status}.`);
    saveDeveloperState();
    renderAll();
}

async function deleteTemplate(index) {
    const target = developerState.surveyTemplates[index];
    if (!target) return;

    const confirmed = await askConfirmation({
        title: 'Confirm Delete Template',
        message: `Are you sure you want to delete the survey template "${target.name}"?`,
        confirmText: 'Delete Template',
        danger: true,
        icon: 'fa-trash'
    });
    if (!confirmed) return;

    const removed = developerState.surveyTemplates.splice(index, 1)[0];
    addLog('warning', 'Survey template deleted', removed?.name || 'Template removed.');
    saveDeveloperState();
    renderAll();
    showToast('Survey template deleted.');
}

async function saveMaintenance() {
    const enabled = document.getElementById('maintenanceToggle').checked;
    const confirmed = await askConfirmation({
        title: 'Confirm Maintenance Settings',
        message: enabled
            ? 'Are you sure you want to enable Maintenance Mode and save these settings?'
            : 'Are you sure you want to save Maintenance Mode settings?',
        confirmText: 'Save Settings',
        danger: enabled,
        icon: 'fa-power-off'
    });
    if (!confirmed) return;

    developerState.maintenance = {
        enabled,
        message: document.getElementById('maintenanceMessage').value.trim(),
        end: document.getElementById('maintenanceEnd').value
    };
    addLog(developerState.maintenance.enabled ? 'critical' : 'info', 'Maintenance mode updated', developerState.maintenance.enabled ? 'Maintenance mode is enabled.' : 'Maintenance mode is disabled.');
    saveDeveloperState();
    renderAll();
    showToast('Maintenance settings saved.');
}

function renderMaintenance() {
    const state = document.getElementById('maintenanceState');
    if (state) {
        state.classList.toggle('is-on', developerState.maintenance.enabled);
        state.innerHTML = developerState.maintenance.enabled
            ? '<i class="fas fa-triangle-exclamation"></i><div><strong>Application is in maintenance mode</strong><p>Access should be limited while maintenance is active.</p></div>'
            : '<i class="fas fa-circle-check"></i><div><strong>Application is available</strong><p>Administrators and alumni can access the system normally.</p></div>';
    }

    const maintenanceToggle = document.getElementById('maintenanceToggle');
    if (maintenanceToggle) maintenanceToggle.checked = developerState.maintenance.enabled;
}

function populateCampusAlumniHeadOptions() {
    const select = document.getElementById('campusAlumniHeadEmail');
    if (!select) return;

    const headAdmins = developerUsers.filter((user) => {
        const roleValue = String(user.roleValue || '').toLowerCase();
        const roleLabel = String(user.role || '').toLowerCase();
        return roleValue === 'head_admin' || roleLabel === 'head admin';
    });

    const options = headAdmins.map((user) => {
        const name = `${user.firstName || ''} ${user.lastName || ''}`.trim();
        const label = name ? `${name} (${user.email})` : user.email;
        return `<option value="${escapeHtml(user.email)}">${escapeHtml(label)}</option>`;
    });

    select.innerHTML = ['<option value="">Select alumni head</option>', ...options].join('');
}

function renderCampusTable() {
    const tbody = document.getElementById('campusTableBody');
    if (!tbody) return;

    if (developerCampuses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--muted);">No campuses found. Click "Add Campus" to create one.</td></tr>';
        return;
    }

    tbody.innerHTML = developerCampuses.map((campus) => `
        <tr>
            <td><strong>${escapeHtml(campus.schoolName)}</strong></td>
            <td>${escapeHtml(campus.abbreviation)}</td>
            <td>${escapeHtml(campus.campus)}</td>
            <td>${escapeHtml(campus.address)}</td>
            <td>${escapeHtml(campus.alumniHeadEmail) || '-'}</td>
            <td>
                <div class="action-set">
                    <button class="icon-button" type="button" title="Edit campus" aria-label="Edit campus" onclick="editCampus(${campus.id})"><i class="fas fa-pen"></i></button>
                    <button class="icon-button danger" type="button" title="Delete campus" aria-label="Delete campus" onclick="deleteCampus(${campus.id})"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function loadCampuses() {
    try {
        const response = await fetch('../developer/campuses', {
            credentials: 'include',
            cache: 'no-store'
        });
        if (!response.ok) throw new Error('Unable to load campuses');
        const data = await response.json();
        developerCampuses = Array.isArray(data.campuses) ? data.campuses : [];
        renderCampusTable();
        renderMetrics();
    } catch (error) {
        showToast('Unable to load campuses.');
        const tbody = document.getElementById('campusTableBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--danger);">Failed to load campuses.</td></tr>';
        }
    }
}

function openCampusModal(campus = null) {
    editingCampusId = campus?.id || null;
    populateCampusAlumniHeadOptions();
    campusAddressState = {
        provinceCode: '',
        municipalityCode: '',
        barangayCode: '',
        province: '',
        municipality: '',
        barangay: '',
    };
    loadCampusAddressData();
    document.getElementById('campusModalTitle').textContent = campus ? 'Edit Campus' : 'Add Campus';
    document.getElementById('campusSchoolName').value = campus?.schoolName || '';
    document.getElementById('campusAbbreviation').value = campus?.abbreviation || '';
    document.getElementById('campusName').value = campus?.campus || '';
    const alumniHeadSelect = document.getElementById('campusAlumniHeadEmail');
    if (alumniHeadSelect) {
        alumniHeadSelect.value = campus?.alumniHeadEmail || '';
    }
    renderCampusAddressSelects(campus?.address || '');
    document.getElementById('campusExistingId').value = campus?.id || 0;
    const modal = document.getElementById('campusModal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function closeCampusModal() {
    const modal = document.getElementById('campusModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    editingCampusId = null;
    campusAddressState = {
        provinceCode: '',
        municipalityCode: '',
        barangayCode: '',
        province: '',
        municipality: '',
        barangay: '',
    };
}

async function saveCampusRecord() {
    const campus = {
        schoolName: document.getElementById('campusSchoolName').value.trim(),
        abbreviation: document.getElementById('campusAbbreviation').value.trim(),
        campus: document.getElementById('campusName').value.trim(),
        alumniHeadEmail: document.getElementById('campusAlumniHeadEmail').value.trim(),
        address: buildCampusAddress([campusAddressState.province, campusAddressState.municipality, campusAddressState.barangay]),
        existingId: parseInt(document.getElementById('campusExistingId').value) || 0
    };

    if (!campus.schoolName || !campus.abbreviation || !campus.campus || !campus.address) {
        showToast('Please complete the required fields.');
        return;
    }

    const isEdit = campus.existingId > 0;
    const confirmed = await askConfirmation({
        title: isEdit ? 'Confirm Update Campus' : 'Confirm Add Campus',
        message: isEdit
            ? `Are you sure you want to save changes to campus "${campus.campus}" (${campus.abbreviation})?`
            : `Are you sure you want to add campus "${campus.campus}" (${campus.abbreviation})?`,
        confirmText: isEdit ? 'Save Changes' : 'Add Campus',
        icon: 'fa-school'
    });
    if (!confirmed) return;

    try {
        const response = await fetch('../developer/campuses', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(campus)
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Could not save the campus.');
        }

        closeCampusModal();
        await loadCampuses();
        showToast('Campus saved.');
    } catch (error) {
        showToast(error.message || 'Campus save failed.');
    }
}

function editCampus(id) {
    const campus = developerCampuses.find((item) => item.id === id);
    if (campus) openCampusModal(campus);
}

async function deleteCampus(id) {
    const targetCampus = developerCampuses.find((item) => item.id === id);
    const campusLabel = targetCampus ? `"${targetCampus.campus}" (${targetCampus.abbreviation})` : 'this campus';
    const confirmed = await askConfirmation({
        title: 'Confirm Delete Campus',
        message: `Are you sure you want to permanently delete ${campusLabel}? This action cannot be undone.`,
        confirmText: 'Delete Campus',
        danger: true,
        icon: 'fa-trash'
    });
    if (!confirmed) return;

    try {
        const response = await fetch(`../developer/campuses/${id}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Delete failed.');
        }
        await loadCampuses();
        showToast('Campus deleted.');
    } catch (error) {
        showToast(error.message || 'Campus deletion failed.');
    }
}

function exportLogs() {
    const text = developerState.logs.map((log) => `[${log.level.toUpperCase()}] ${log.time} - ${log.title}: ${log.detail}`).join('\n');
    navigator.clipboard?.writeText(text);
    showToast('Logs prepared for export.');
}

function bindEvents() {
    document.querySelectorAll('.developer-nav-item').forEach((item) => {
        item.addEventListener('click', () => switchPanel(item.dataset.section, item));
    });

    const developerLogout = document.getElementById('developerLogout');
    if (developerLogout) developerLogout.addEventListener('click', logoutDeveloper);

    const adminSearch = document.getElementById('adminSearch');
    if (adminSearch) adminSearch.addEventListener('input', renderAdminTable);

    const roleFilter = document.getElementById('roleFilter');
    if (roleFilter) roleFilter.addEventListener('change', renderAdminTable);

    const openAdminModalButton = document.getElementById('openAdminModal');
    if (openAdminModalButton) openAdminModalButton.addEventListener('click', () => openAdminModal());

    const closeAdminModalButton = document.getElementById('closeAdminModal');
    if (closeAdminModalButton) closeAdminModalButton.addEventListener('click', closeAdminModal);

    const cancelAdminModalButton = document.getElementById('cancelAdminModal');
    if (cancelAdminModalButton) cancelAdminModalButton.addEventListener('click', closeAdminModal);

    const saveAdminAccountButton = document.getElementById('saveAdminAccount');
    if (saveAdminAccountButton) saveAdminAccountButton.addEventListener('click', saveAdminAccount);

    const saveSystemSettingsButton = document.getElementById('saveSystemSettings');
    if (saveSystemSettingsButton) saveSystemSettingsButton.addEventListener('click', saveSystemSettings);

    const saveSchoolInfoButton = document.getElementById('saveSchoolInfo');
    if (saveSchoolInfoButton) saveSchoolInfoButton.addEventListener('click', saveSchoolInfo);

    const createBackupButton = document.getElementById('createBackup');
    if (createBackupButton) createBackupButton.addEventListener('click', createBackup);

    const queueRestoreButton = document.getElementById('queueRestore');
    if (queueRestoreButton) queueRestoreButton.addEventListener('click', queueRestore);

    const logLevelFilter = document.getElementById('logLevelFilter');
    if (logLevelFilter) logLevelFilter.addEventListener('change', renderLogs);

    const exportLogsButton = document.getElementById('exportLogs');
    if (exportLogsButton) exportLogsButton.addEventListener('click', exportLogs);

    const publishAnnouncementButton = document.getElementById('publishAnnouncement');
    if (publishAnnouncementButton) publishAnnouncementButton.addEventListener('click', publishAnnouncement);

    const saveMessagingSettingsButton = document.getElementById('saveMessagingSettings');
    if (saveMessagingSettingsButton) saveMessagingSettingsButton.addEventListener('click', saveMessagingSettings);

    const addSurveyTemplateButton = document.getElementById('addSurveyTemplate');
    if (addSurveyTemplateButton) addSurveyTemplateButton.addEventListener('click', addSurveyTemplate);

    const saveMaintenanceButton = document.getElementById('saveMaintenance');
    if (saveMaintenanceButton) saveMaintenanceButton.addEventListener('click', saveMaintenance);

    const maintenanceToggle = document.getElementById('maintenanceToggle');
    if (maintenanceToggle) maintenanceToggle.addEventListener('change', () => {
        developerState.maintenance.enabled = document.getElementById('maintenanceToggle').checked;
        renderMaintenance();
        renderMetrics();
    });

    const openCampusModalButton = document.getElementById('openCampusModal');
    if (openCampusModalButton) openCampusModalButton.addEventListener('click', () => openCampusModal());

    const closeCampusModalButton = document.getElementById('closeCampusModal');
    if (closeCampusModalButton) closeCampusModalButton.addEventListener('click', closeCampusModal);

    const cancelCampusModalButton = document.getElementById('cancelCampusModal');
    if (cancelCampusModalButton) cancelCampusModalButton.addEventListener('click', closeCampusModal);

    const campusProvinceSelect = document.getElementById('campusProvince');
    if (campusProvinceSelect) campusProvinceSelect.addEventListener('change', (event) => {
        const selectedOption = event.target.selectedOptions[0];
        campusAddressState.provinceCode = event.target.value;
        campusAddressState.province = selectedOption ? selectedOption.textContent.trim() : '';
        campusAddressState.municipalityCode = '';
        campusAddressState.municipality = '';
        campusAddressState.barangayCode = '';
        campusAddressState.barangay = '';
        renderCampusAddressSelects();
    });

    const campusMunicipalitySelect = document.getElementById('campusMunicipality');
    if (campusMunicipalitySelect) campusMunicipalitySelect.addEventListener('change', (event) => {
        const selectedOption = event.target.selectedOptions[0];
        campusAddressState.municipalityCode = event.target.value;
        campusAddressState.municipality = selectedOption ? selectedOption.textContent.trim() : '';
        campusAddressState.barangayCode = '';
        campusAddressState.barangay = '';
        renderCampusAddressSelects();
    });

    const campusBarangaySelect = document.getElementById('campusBarangay');
    if (campusBarangaySelect) campusBarangaySelect.addEventListener('change', (event) => {
        const selectedOption = event.target.selectedOptions[0];
        campusAddressState.barangayCode = event.target.value;
        campusAddressState.barangay = selectedOption ? selectedOption.textContent.trim() : '';
        renderCampusAddressSelects();
    });

    const saveCampusBtn = document.getElementById('saveCampus');
    if (saveCampusBtn) saveCampusBtn.addEventListener('click', saveCampusRecord);

    const quickBackupBtn = document.getElementById('quickBackupBtn');
    if (quickBackupBtn) quickBackupBtn.addEventListener('click', createBackup);

    const quickMaintenanceBtn = document.getElementById('quickMaintenanceBtn');
    if (quickMaintenanceBtn) {
        quickMaintenanceBtn.addEventListener('click', async () => {
            const nextState = !developerState.maintenance.enabled;
            const confirmed = await askConfirmation({
                title: nextState ? 'Enable Maintenance Mode' : 'Disable Maintenance Mode',
                message: nextState
                    ? 'Are you sure you want to enable Maintenance Mode across the web app?'
                    : 'Are you sure you want to disable Maintenance Mode and restore normal user access?',
                confirmText: nextState ? 'Enable Maintenance' : 'Disable Maintenance',
                danger: nextState,
                icon: 'fa-power-off'
            });
            if (!confirmed) return;

            developerState.maintenance.enabled = nextState;
            addLog(
                nextState ? 'critical' : 'info',
                'Maintenance mode updated',
                nextState ? 'Maintenance mode was enabled from the Overview command bar.' : 'Maintenance mode was disabled from the Overview command bar.'
            );
            saveDeveloperState();
            renderAll();
            showToast(nextState ? 'Maintenance mode enabled.' : 'Maintenance mode disabled.');
        });
    }

    const refreshOverviewBtn = document.getElementById('refreshOverviewBtn');
    if (refreshOverviewBtn) {
        refreshOverviewBtn.addEventListener('click', async () => {
            await Promise.all([
                loadOverviewTelemetry(true),
                loadDeveloperUsers(),
                loadCampuses()
            ]);
        });
    }
}

function initDeveloperSidebar() {
    const shell = document.querySelector('.developer-shell');
    const sidebar = document.querySelector('.developer-sidebar');
    const brand = document.querySelector('.developer-brand');
    const nav = document.querySelector('.developer-nav');
    if (!shell || !sidebar || !brand || !nav) return;

    const SIDEBAR_STORAGE_KEY = 'developer_sidebar_collapsed_v1';

    try {
        if (localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1') {
            shell.classList.add('sidebar-collapsed');
        }
    } catch (error) {
        // Ignore storage read errors
    }

    if (!document.getElementById('developerSidebarToggle')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.id = 'developerSidebarToggle';
        toggleBtn.type = 'button';
        toggleBtn.className = 'sidebar-collapse-btn';
        toggleBtn.title = 'Toggle sidebar (Ctrl+B)';
        toggleBtn.setAttribute('aria-label', 'Toggle sidebar');
        toggleBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        toggleBtn.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 780px)').matches) {
                shell.classList.toggle('mobile-nav-open');
                return;
            }
            const isCollapsed = shell.classList.toggle('sidebar-collapsed');
            try {
                localStorage.setItem(SIDEBAR_STORAGE_KEY, isCollapsed ? '1' : '0');
            } catch (error) {
                // Ignore storage write errors
            }
        });
        brand.appendChild(toggleBtn);
    }

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'b') {
            event.preventDefault();
            document.getElementById('developerSidebarToggle')?.click();
        }
    });

    const sectionMarkers = {
        'Overview.html': 'Core Management',
        'BackupRestore.html': 'System & Logs',
    };

    const currentPage = (window.location.pathname.split('/').pop() || 'Overview.html').toLowerCase();
    const targetPage = currentPage === 'dashboard.html' ? 'overview.html' : currentPage;

    nav.querySelectorAll('.developer-nav-item').forEach((item) => {
        const labelText = item.querySelector('span')?.textContent?.trim() || '';
        if (labelText && !item.getAttribute('title')) {
            item.setAttribute('title', labelText);
        }

        const href = item.getAttribute('href') || '';
        if (href) {
            const hrefPage = href.split('/').pop().toLowerCase();
            item.classList.toggle('active', hrefPage === targetPage);
        }

        const groupTitle = sectionMarkers[href];
        if (groupTitle && !item.previousElementSibling?.classList.contains('developer-nav-label')) {
            const labelEl = document.createElement('div');
            labelEl.className = 'developer-nav-label';
            labelEl.textContent = groupTitle;
            nav.insertBefore(labelEl, item);
        }
    });

    if (!sidebar.querySelector('.developer-sidebar-footer')) {
        const footer = document.createElement('div');
        footer.className = 'developer-sidebar-footer';
        footer.innerHTML = `
            <button type="button" class="developer-sidebar-logout" id="developerSidebarLogoutBtn" title="Sign Out">
                <i class="fas fa-right-from-bracket"></i>
                <span>Sign Out</span>
            </button>
        `;
        footer.querySelector('#developerSidebarLogoutBtn')?.addEventListener('click', logoutDeveloper);
        sidebar.appendChild(footer);
    }
}

function renderAll() {
    renderMetrics();
    renderAdminTable();
    renderSettingsForms();
    renderBackups();
    renderLogs();
    renderAnnouncements();
    renderSurveyTemplates();
    renderMaintenance();
    renderCampusTable();
}

document.addEventListener('DOMContentLoaded', () => {
    initDeveloperSidebar();
    populateRoleDropdown();
    bindEvents();
    renderAll();
    loadCurrentUser();
    loadOverviewTelemetry();
    loadDeveloperUsers();
    loadCampuses();
    loadDeveloperBackups();
});

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        buildCampusAddress,
    };
}
