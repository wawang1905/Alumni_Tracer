let __alumniActionConfirmPromise = null;
function ensureAlumniActionConfirm() {
    if (window.ActionConfirm) return Promise.resolve(window.ActionConfirm);
    if (!__alumniActionConfirmPromise) {
        __alumniActionConfirmPromise = new Promise((resolve) => {
            const s = document.createElement('script');
            s.src = '../JS/ActionConfirm.js';
            s.onload = () => resolve(window.ActionConfirm || null);
            s.onerror = () => resolve(null);
            document.head.appendChild(s);
        });
    }
    return __alumniActionConfirmPromise;
}
void ensureAlumniActionConfirm();

let currentAlumniUser = null;

function alumniUrl(path) {
    return new URL(path, window.location.href).toString();
}

function getRootUrl() {
    return alumniUrl('../');
}

function getSessionUrl() {
    return alumniUrl('../auth/session');
}

function getLogoutUrl() {
    return alumniUrl('../auth/logout');
}

function getEventsUrl() {
    return alumniUrl('../events/list');
}

function getNewsUrl() {
    return alumniUrl('../alumni/news');
}

function getStatisticsUrl() {
    return alumniUrl('../events/statistics');
}

function getSurveyListUrl() {
    return alumniUrl('../surveys/list');
}

function getSurveySubmitUrl(surveyId) {
    return alumniUrl(`../surveys/${encodeURIComponent(surveyId)}/responses`);
}

function getSurveyRespondUrl(surveyId) {
    return alumniUrl(`SurveyRespond.html?id=${encodeURIComponent(surveyId)}`);
}

function getProfileUrl() {
    return alumniUrl('../alumni/profile');
}

function getMyDigitalIdUrl() {
    return alumniUrl('../alumni/my-digital-id');
}

function getUpdateProfileUrl() {
    return alumniUrl('../alumni/update');
}

function getUploadProfilePictureUrl() {
    return alumniUrl('../alumni/profile-picture');
}

function getServeProfilePictureUrl(alumniId) {
    if (alumniId) {
        return alumniUrl(`../alumni/profile-picture?alumni_id=${encodeURIComponent(alumniId)}`);
    }
    return alumniUrl('../alumni/profile-picture');
}

function getStaffDashboardUrl() {
    return alumniUrl('../staff/Dashboard.html');
}

function getDeveloperDashboardUrl() {
    return alumniUrl('../developer/Dashboard.html');
}

function redirectToLogin() {
    window.location.replace(getRootUrl());
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        ...options,
    });

    if (!response.ok) {
        throw new Error(`Request failed (${response.status})`);
    }

    return response.json();
}

async function requireAlumni() {
    try {
        const session = await fetchJson(getSessionUrl());
        if (!session.loggedIn || !session.user) {
            redirectToLogin();
            return null;
        }

        const role = String(session.user.role || '').toLowerCase();
        if (role === 'developer' || role === 'system_admin') {
            window.location.replace(getDeveloperDashboardUrl());
            return null;
        }
        if (role === 'head_admin') {
            window.location.replace(getStaffDashboardUrl());
            return null;
        }

        currentAlumniUser = session.user;
        return session.user;
    } catch (error) {
        redirectToLogin();
        return null;
    }
}

async function loadAlumniProfile() {
    try {
        const response = await fetchJson(getProfileUrl());
        if (!response.success) {
            console.error('Failed to load profile:', response.message);
            return;
        }
        
        displayProfileData(response.profile);
    } catch (error) {
        console.error('Error loading profile:', error);
    }
}

function updateSessionUser(updatedUser) {
    const currentUser = getCurrentUser();
    if (currentUser) {
        // Update the session user data
        Object.assign(currentUser, updatedUser);
        
        // Update UI elements
        const userNameEl = document.getElementById('alumniUserName');
        if (userNameEl) {
            userNameEl.textContent = `${updatedUser.firstName} ${updatedUser.lastName}`.trim() || 'Alumni User';
        }
    }
}

function getCurrentUser() {
    // Try to get session from cookies first
    const cookies = document.cookie.split(';');
    let sessionCookie = null;
    
    for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'ci_session') {
            sessionCookie = value;
            break;
        }
    }
    
    if (sessionCookie) {
        try {
            // Decode session data from cookie
            const sessionData = atob(sessionCookie);
            const sessionObj = JSON.parse(sessionData);
            return sessionObj.auth_user || null;
        } catch (e) {
            console.error('Error parsing session cookie:', e);
            return null;
        }
    }
    
    return null;
}

function displayProfileData(profileData) {
    document.getElementById('profileAlumniId').value = profileData.alumni_id || '';
    document.getElementById('profileFirstName').value = profileData.firstName || '';
    document.getElementById('profileLastName').value = profileData.lastName || '';
    document.getElementById('profileEmail').value = profileData.email || '';
    document.getElementById('profileContactNumber').value = profileData.contactNumber || '';
    document.getElementById('profileProvince').value = profileData.province || '';
    document.getElementById('profileMunicipality').value = profileData.municipality || '';
    document.getElementById('profileBarangay').value = profileData.barangay || '';
    document.getElementById('profileGraduationYear').value = profileData.graduationYear || '';
    document.getElementById('profileCollege').value = profileData.college || '';
    document.getElementById('profileCourse').value = profileData.course || '';
}

async function logoutAlumni() {
    try {
        await fetch(getLogoutUrl(), {
            method: 'POST',
            credentials: 'same-origin',
        });
    } finally {
        redirectToLogin();
    }
}

function updateAlumniAvatarElements(imageUrl) {
    const avatarSpans = document.querySelectorAll('.alumni-avatar');
    avatarSpans.forEach((span) => {
        if (imageUrl) {
            let img = span.querySelector('img.alumni-avatar-img');
            if (!img) {
                span.innerHTML = '';
                img = document.createElement('img');
                img.className = 'alumni-avatar-img';
                img.alt = 'Avatar';
                span.appendChild(img);
            }
            img.src = imageUrl;
            img.onerror = () => {
                span.innerHTML = '<i class="fas fa-user"></i>';
            };
        } else {
            span.innerHTML = '<i class="fas fa-user"></i>';
        }
    });

    // Also sync the Profile page's main avatar card if present
    const profileImg = document.getElementById('profileAvatarImg');
    const profilePlaceholder = document.getElementById('profileAvatarPlaceholder');
    const profileCircle = document.getElementById('profileAvatarCircle');
    if (profileImg && profilePlaceholder) {
        if (imageUrl) {
            profileImg.src = imageUrl;
            profileImg.style.display = 'block';
            profilePlaceholder.style.display = 'none';
            if (profileCircle) {
                profileCircle.classList.add('has-image');
                profileCircle.title = 'Click to view full photo';
            }
            profileImg.onerror = () => {
                profileImg.style.display = 'none';
                profilePlaceholder.style.display = 'block';
                if (profileCircle) {
                    profileCircle.classList.remove('has-image');
                    profileCircle.title = 'Upload photo';
                }
            };
        } else {
            profileImg.style.display = 'none';
            profilePlaceholder.style.display = 'block';
            if (profileCircle) {
                profileCircle.classList.remove('has-image');
                profileCircle.title = 'Upload photo';
            }
        }
    }
}

async function loadAlumniAvatars() {
    const url = getServeProfilePictureUrl();
    try {
        const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
        if (res.ok && (res.headers.get('Content-Type') || '').startsWith('image/')) {
            const cacheBustUrl = url + (url.includes('?') ? '&' : '?') + '_t=' + Date.now();
            updateAlumniAvatarElements(cacheBustUrl);
        } else {
            updateAlumniAvatarElements(null);
        }
    } catch {
        updateAlumniAvatarElements(null);
    }
}

function injectAlumniUser(user) {
    const welcomeName = document.getElementById('welcomeName');
    const displayName = user?.name || 'Alumni User';
    const displayRole = (user?.role || 'alumni').replace('_', ' ');

    document.querySelectorAll('[data-alumni-user-name]').forEach((element) => {
        element.textContent = displayName;
    });

    document.querySelectorAll('[data-alumni-user-role]').forEach((element) => {
        element.textContent = displayRole;
    });

    if (welcomeName) welcomeName.textContent = user?.first_name || user?.name || 'Alumni';

    document.querySelectorAll('[data-logout-button]').forEach((button) => {
        if (button.dataset.bound === 'true') return;
        button.addEventListener('click', logoutAlumni);
        button.dataset.bound = 'true';
    });

    loadAlumniAvatars();
    setupSidebarMenu();
    setupProfileDropdown();
}

/* ==========================================================================
   Alumni Side - View My Alumni Digital ID Modal (Desktop Dual + Mobile Flip)
   ========================================================================== */
function ensureMyAlumniDigitalIdModal() {
    let modal = document.getElementById('myAlumniDigitalIdModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'myAlumniDigitalIdModal';
    modal.className = 'alumni-my-id-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'myAlumniDigitalIdTitle');
    modal.hidden = true;

    modal.innerHTML = `
        <div class="alumni-my-id-dialog">
            <div class="alumni-my-id-header">
                <div>
                    <h3 id="myAlumniDigitalIdTitle">My Alumni Digital ID</h3>
                    <p id="myAlumniDigitalIdSubtitle">Official USTP Alumni Association Digital Identification</p>
                </div>
                <button type="button" class="alumni-my-id-close-icon" onclick="closeMyAlumniDigitalIdModal()" aria-label="Close Digital ID">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="alumni-my-id-body">
                <div id="myAlumniIdLoading" class="alumni-my-id-state" hidden>
                    <i class="fas fa-circle-notch fa-spin"></i>
                    <span>Loading your Alumni Digital ID...</span>
                </div>
                <div id="myAlumniIdUnavailable" class="alumni-my-id-unavailable" hidden>
                    <i class="fas fa-id-card"></i>
                    <h4>Digital ID Not Yet Available</h4>
                    <p id="myAlumniIdUnavailableMsg">Your Digital ID is not yet available. Please contact the Alumni Office.</p>
                </div>

                <div id="myAlumniIdContent" class="alumni-my-id-content" hidden>
                    <div id="myAlumniIdStage" class="alumni-my-id-stage" tabindex="0" role="button" aria-label="Alumni Digital ID Card (tap to flip on mobile)">
                        <!-- FRONT SIDE -->
                        <div class="alumni-my-id-face alumni-my-id-face--front">
                            <div class="alumni-my-id-side-label">
                                <span class="alumni-my-id-side-pill">FRONT SIDE</span>
                            </div>
                            <div class="alumni-ustp-card alumni-ustp-front">
                                <div class="alumni-ustp-front-blob-bl" aria-hidden="true"></div>
                                <div class="alumni-ustp-front-dot-yellow" aria-hidden="true"></div>
                                <div class="alumni-ustp-front-dot-slate" aria-hidden="true"></div>
                                <div class="alumni-ustp-front-slate-shape" aria-hidden="true"></div>
                                <div class="alumni-ustp-front-corner-br" aria-hidden="true"></div>

                                <div class="alumni-ustp-front-photo-frame">
                                    <img id="myIdPhotoImg" class="alumni-ustp-front-photo-img" src="" alt="Alumni Photograph" hidden>
                                    <div id="myIdAvatarFallback" class="alumni-ustp-front-photo-fallback">GV</div>
                                </div>

                                <div class="alumni-ustp-front-logo-wrap">
                                    <img id="myIdOfficialLogo" src="../images/ustp-alumni-logo.jpg" alt="USTP Alumni Association Claveria Campus Logo">
                                </div>

                                <div class="alumni-ustp-front-text-block">
                                    <div class="alumni-ustp-front-name" id="myIdFullName">—</div>
                                    <div class="alumni-ustp-front-id-num" id="myIdNumber">—</div>
                                    <div class="alumni-ustp-front-member">LIFETIME MEMBER</div>
                                    <div class="alumni-ustp-front-batch" id="myIdBatch">BATCH —</div>
                                </div>
                            </div>
                        </div>

                        <!-- BACK SIDE -->
                        <div class="alumni-my-id-face alumni-my-id-face--back">
                            <div class="alumni-my-id-side-label">
                                <span class="alumni-my-id-side-pill is-back">BACK SIDE</span>
                            </div>
                            <div class="alumni-ustp-card alumni-ustp-back">
                                <div class="alumni-ustp-back-accent-tl" aria-hidden="true"></div>
                                <div class="alumni-ustp-back-accent-br" aria-hidden="true"></div>

                                <div class="alumni-ustp-back-header-left">PERSONAL DETAILS</div>
                                <div class="alumni-ustp-back-header-right">
                                    THE BEARER WHOSE PICTURE APPEARS HEREIN<br>
                                    IS A BONA FIDE MEMBER OF USTPAA-CLAVERIA
                                </div>

                                <div class="alumni-ustp-back-details">
                                    <div class="alumni-ustp-back-row"><span class="alumni-ustp-back-lbl">SEX:</span> <span id="myIdSex">—</span></div>
                                    <div class="alumni-ustp-back-row"><span class="alumni-ustp-back-lbl">BIRTHDATE:</span> <span id="myIdBirthdate">—</span></div>
                                    <div class="alumni-ustp-back-row"><span class="alumni-ustp-back-lbl">DEGREE/PROGRAM:</span> <span id="myIdCourse">—</span></div>
                                    <div class="alumni-ustp-back-row"><span class="alumni-ustp-back-lbl">CONTACT NUMBER:</span> <span id="myIdContact">—</span></div>
                                    <div class="alumni-ustp-back-row"><span class="alumni-ustp-back-lbl">HOME ADDRESS:</span></div>
                                    <div class="alumni-ustp-back-row alumni-ustp-back-indent" id="myIdAddress">—</div>
                                    <div class="alumni-ustp-back-row alumni-ustp-back-em-lbl">IN CASE OF EMERGENCY, PLEASE CONTACT:</div>
                                    <div class="alumni-ustp-back-row alumni-ustp-back-em-val" id="myIdEmergencyName">—</div>
                                    <div class="alumni-ustp-back-row alumni-ustp-back-em-val" id="myIdEmergencyNumber">—</div>
                                </div>

                                <div class="alumni-ustp-back-sig-block">
                                    <svg class="alumni-ustp-back-sig-svg" viewBox="0 0 140 52" aria-hidden="true">
                                        <path d="M22,38 C28,12 42,6 44,18 C46,28 32,36 30,26 C28,18 46,16 56,14 C54,28 50,42 54,44 C58,46 66,22 72,20 C76,18 70,36 78,34 C84,32 90,22 96,24 C100,25 94,35 104,33 C112,31 118,27 128,26" fill="none" stroke="#3f4650" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M40,42 C62,38 88,36 118,39" fill="none" stroke="#3f4650" stroke-width="1.3" stroke-linecap="round"/>
                                    </svg>
                                    <div class="alumni-ustp-back-sig-name">GERUM O. SALATAN</div>
                                    <div class="alumni-ustp-back-sig-title">USTPAA-CLAVERIA PRESIDENT</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alumni-my-id-mobile-hint">
                        <i class="fas fa-rotate" aria-hidden="true"></i>
                        <span id="myAlumniIdFlipHintText">Tap your ID to view the back.</span>
                    </div>
                </div>
            </div>
            <div class="alumni-my-id-footer">
                <button type="button" class="alumni-my-id-close-btn" onclick="closeMyAlumniDigitalIdModal()">Close</button>
            </div>
        </div>
    `;

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeMyAlumniDigitalIdModal();
        }
    });

    const stage = modal.querySelector('#myAlumniIdStage');
    if (stage) {
        stage.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 768px)').matches) {
                toggleMyAlumniIdFlip();
            }
        });
        stage.addEventListener('keydown', (event) => {
            if ((event.key === 'Enter' || event.key === ' ') && window.matchMedia('(max-width: 768px)').matches) {
                event.preventDefault();
                toggleMyAlumniIdFlip();
            }
        });
    }

    document.body.appendChild(modal);
    return modal;
}

function toggleMyAlumniIdFlip(forceFlipped) {
    const stage = document.getElementById('myAlumniIdStage');
    const hintText = document.getElementById('myAlumniIdFlipHintText');
    if (!stage) return;

    const isFlipped = typeof forceFlipped === 'boolean'
        ? forceFlipped
        : !stage.classList.contains('is-flipped');

    stage.classList.toggle('is-flipped', isFlipped);
    if (hintText) {
        hintText.textContent = isFlipped
            ? 'Tap your ID to view the front.'
            : 'Tap your ID to view the back.';
    }
}

async function openMyAlumniDigitalIdModal() {
    const modal = ensureMyAlumniDigitalIdModal();
    const loadingEl = document.getElementById('myAlumniIdLoading');
    const unavailableEl = document.getElementById('myAlumniIdUnavailable');
    const unavailableMsg = document.getElementById('myAlumniIdUnavailableMsg');
    const contentEl = document.getElementById('myAlumniIdContent');

    toggleMyAlumniIdFlip(false);
    if (unavailableEl) unavailableEl.hidden = true;
    if (contentEl) contentEl.hidden = true;
    if (loadingEl) loadingEl.hidden = false;

    modal.hidden = false;
    document.body.style.overflow = 'hidden';

    try {
        const res = await fetch(getMyDigitalIdUrl(), {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });
        const payload = await res.json().catch(() => ({}));

        if (loadingEl) loadingEl.hidden = true;

        if (!res.ok || !payload.success) {
            if (unavailableMsg) {
                unavailableMsg.textContent = payload.message || 'Unable to load your Digital ID right now. Please try again.';
            }
            if (unavailableEl) unavailableEl.hidden = false;
            return;
        }

        if (payload.available !== true || !payload.alumni) {
            if (unavailableMsg) {
                unavailableMsg.textContent = payload.message || 'Your Digital ID is not yet available. Please contact the Alumni Office.';
            }
            if (unavailableEl) unavailableEl.hidden = false;
            return;
        }

        populateMyAlumniDigitalId(payload.alumni);
        if (contentEl) contentEl.hidden = false;
    } catch (err) {
        if (loadingEl) loadingEl.hidden = true;
        if (unavailableMsg) {
            unavailableMsg.textContent = 'Unable to load your Digital ID right now. Please try again.';
        }
        if (unavailableEl) unavailableEl.hidden = false;
    }
}

function populateMyAlumniDigitalId(data) {
    const setTxt = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    };

    const fullName = String(data.full_name || '').trim() || 'ALUMNI MEMBER';
    const alumniId = String(data.alumni_id || '').trim() || '—';
    const batchYear = String(data.graduation_year || '').trim() || '—';
    const initials = String(data.initials || 'GV').trim() || 'GV';

    setTxt('myAlumniDigitalIdSubtitle', `${fullName} · ID: ${alumniId}`);
    setTxt('myIdFullName', fullName.toUpperCase());
    setTxt('myIdNumber', alumniId);
    setTxt('myIdBatch', `BATCH ${batchYear}`);
    setTxt('myIdAvatarFallback', initials);

    const logoEl = document.getElementById('myIdOfficialLogo');
    if (logoEl && data.logo_url) {
        logoEl.src = data.logo_url;
    }

    const photoImg = document.getElementById('myIdPhotoImg');
    const avatarFallback = document.getElementById('myIdAvatarFallback');
    if (photoImg && avatarFallback) {
        const photoUrl = String(data.profile_picture_url || '').trim();
        if (photoUrl) {
            photoImg.onload = () => {
                photoImg.hidden = false;
                avatarFallback.hidden = true;
            };
            photoImg.onerror = () => {
                photoImg.hidden = true;
                avatarFallback.hidden = false;
            };
            photoImg.src = photoUrl;
        } else {
            photoImg.removeAttribute('src');
            photoImg.hidden = true;
            avatarFallback.hidden = false;
        }
    }

    setTxt('myIdSex', String(data.sex || '').trim() || '—');
    setTxt('myIdBirthdate', String(data.birthdate || '').trim() || '—');
    setTxt('myIdCourse', String(data.course || '').trim().toUpperCase() || '—');
    setTxt('myIdContact', String(data.contact_number || '').trim() || '—');
    setTxt('myIdAddress', String(data.home_address || '').trim() || '—');
    setTxt('myIdEmergencyName', String(data.emergency_contact_name || '').trim() || '—');
    setTxt('myIdEmergencyNumber', String(data.emergency_contact_number || '').trim() || '—');
}

function closeMyAlumniDigitalIdModal() {
    const modal = document.getElementById('myAlumniDigitalIdModal');
    if (modal) {
        modal.hidden = true;
    }
    document.body.style.overflow = '';
}

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        const modal = document.getElementById('myAlumniDigitalIdModal');
        if (modal && !modal.hidden) {
            closeMyAlumniDigitalIdModal();
        }
    }
});

function closeSidebarMenu() {
    const shell = document.querySelector('.alumni-shell');
    const menuToggle = document.getElementById('menuToggleButton');
    if (!shell || !menuToggle) return;

    shell.classList.remove('menu-open');
    menuToggle.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('alumni-nav-open');
}

function setupSidebarMenu() {
    const shell = document.querySelector('.alumni-shell');
    const menuToggle = document.getElementById('menuToggleButton');
    const sidebar = document.getElementById('alumniSidebar');
    const overlay = document.getElementById('alumniSidebarOverlay');

    if (!shell || !menuToggle || !sidebar || !overlay) return;
    if (menuToggle.dataset.bound === 'true') return;

    const syncState = (isOpen) => {
        shell.classList.toggle('menu-open', isOpen);
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.body.classList.toggle('alumni-nav-open', isOpen);
    };

    menuToggle.addEventListener('click', (event) => {
        event.stopPropagation();
        syncState(!shell.classList.contains('menu-open'));
    });

    overlay.addEventListener('click', () => {
        syncState(false);
    });

    sidebar.querySelectorAll('.alumni-menu-item').forEach((link) => {
        link.addEventListener('click', () => {
            syncState(false);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            syncState(false);
        }
    });

    menuToggle.dataset.bound = 'true';
}

function setupProfileDropdown() {
    const menus = document.querySelectorAll('[data-profile-menu]');
    if (menus.length === 0) return;

    const closeMenus = () => {
        menus.forEach((menu) => {
            const trigger = menu.querySelector('[data-profile-trigger]');
            const dropdown = menu.querySelector('[data-profile-dropdown]');
            if (!trigger || !dropdown) return;

            dropdown.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        });
    };

    menus.forEach((menu) => {
        const trigger = menu.querySelector('[data-profile-trigger]');
        const dropdown = menu.querySelector('[data-profile-dropdown]');
        if (!trigger || !dropdown || trigger.dataset.bound === 'true') return;

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            closeSidebarMenu();

            const willOpen = !dropdown.classList.contains('open');
            closeMenus();
            dropdown.classList.toggle('open', willOpen);
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        dropdown.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        trigger.dataset.bound = 'true';
    });

    if (document.body.dataset.profileMenusBound !== 'true') {
        document.addEventListener('click', closeMenus);
        document.body.dataset.profileMenusBound = 'true';
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatDateTime(isoString) {
    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) {
        return escapeHtml(isoString);
    }

    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function formatRelativeLabel(isoString) {
    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) {
        return 'Schedule to be announced';
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const compare = new Date(date);
    compare.setHours(0, 0, 0, 0);

    const diffDays = Math.round((compare.getTime() - today.getTime()) / 86400000);
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Tomorrow';
    if (diffDays > 1) return `In ${diffDays} days`;
    if (diffDays === -1) return 'Yesterday';
    return `${Math.abs(diffDays)} days ago`;
}

function formatTimeAgo(isoString) {
    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) {
        return 'Just now';
    }

    const diffMs = Date.now() - date.getTime();
    const minute = 60000;
    const hour = 3600000;
    const day = 86400000;

    if (diffMs < minute) return 'Just now';
    if (diffMs < hour) return `${Math.max(1, Math.floor(diffMs / minute))}m`;
    if (diffMs < day) return `${Math.max(1, Math.floor(diffMs / hour))}h`;
    if (diffMs < day * 7) return `${Math.max(1, Math.floor(diffMs / day))}d`;
    return formatDateTime(isoString);
}

async function loadEventsData() {
    const result = await fetchJson(getEventsUrl());
    return Array.isArray(result.data) ? result.data : [];
}

async function loadEventStatistics() {
    const result = await fetchJson(getStatisticsUrl());
    return result.data || { total: 0, upcoming: 0, past: 0 };
}

async function loadNewsData() {
    const result = await fetchJson(getNewsUrl());
    return Array.isArray(result.news) ? result.news : [];
}

async function loadSurveyState() {
    const result = await fetchJson(getSurveyListUrl());
    return Array.isArray(result.data) ? result.data : [];
}

/* ==========================================================================
   Survey Notification Feature
   Tracks open surveys and keeps notification active on menu button until
   the survey is answered or closed.
   ========================================================================== */

function getPendingUnansweredSurveys(surveys) {
    if (!Array.isArray(surveys) || surveys.length === 0) return [];
    return surveys.filter((survey) => {
        // Exclude if already responded to by this alumni
        if (Boolean(survey.alreadyResponded)) return false;
        // Exclude if explicitly closed or not accepting responses
        if (survey.isAcceptingResponses === false) return false;
        if (String(survey.status || '').toLowerCase() === 'closed') return false;
        return true;
    });
}

function updateSurveyMenuNotification(hasPending, count = 0) {
    const menuToggle = document.getElementById('menuToggleButton');
    const surveyMenuItems = document.querySelectorAll('.alumni-menu-item[href*="Surveys.html"], [data-nav-survey]');

    const label = count > 0 ? `Survey awaiting your response (${count})` : 'Survey awaiting your response';

    // 1. Mobile topbar hamburger menu button
    if (menuToggle) {
        let dot = menuToggle.querySelector('.alumni-menu-dot');
        if (!dot) {
            dot = document.createElement('span');
            dot.className = 'alumni-menu-dot';
            dot.id = 'menuToggleNotificationDot';
            dot.setAttribute('aria-hidden', 'true');
            menuToggle.appendChild(dot);
        }
        if (hasPending) {
            dot.classList.add('is-active');
            dot.setAttribute('title', label);
            dot.setAttribute('aria-label', label);
        } else {
            dot.classList.remove('is-active');
            dot.removeAttribute('title');
            dot.removeAttribute('aria-label');
        }
    }

    // 2. Sidebar / Top navbar Survey menu item
    surveyMenuItems.forEach((item) => {
        let dot = item.querySelector('.alumni-menu-dot');
        if (!dot) {
            dot = document.createElement('span');
            dot.className = 'alumni-menu-dot';
            dot.setAttribute('aria-hidden', 'true');
            item.appendChild(dot);
        }
        if (hasPending) {
            dot.classList.add('is-active');
            dot.setAttribute('title', label);
            dot.setAttribute('aria-label', label);
        } else {
            dot.classList.remove('is-active');
            dot.removeAttribute('title');
            dot.removeAttribute('aria-label');
        }
    });
}

let isPollingSurveys = false;

async function pollSurveyNotifications() {
    if (!currentAlumniUser || isPollingSurveys) return;
    isPollingSurveys = true;

    try {
        const surveys = await loadSurveyState();
        const prevIds = Array.isArray(currentSurveys) ? currentSurveys.map((s) => Number(s.id)) : [];
        const currentIds = surveys.map((s) => Number(s.id));
        currentSurveys = surveys;

        const pending = getPendingUnansweredSurveys(surveys);
        updateSurveyMenuNotification(pending.length > 0, pending.length);

        const isSurveysPage = Boolean(document.getElementById('alumniSurveysGrid'));
        if (isSurveysPage) {
            const hasChanged = currentIds.some((id) => !prevIds.includes(id)) || currentIds.length !== prevIds.length;
            if (hasChanged && prevIds.length > 0) {
                renderSurveyCards('alumniSurveysGrid', surveys);
            }
        } else {
            const mainSurveyPreview = document.getElementById('mainSurveyPreview');
            const hasChanged = currentIds.some((id) => !prevIds.includes(id)) || currentIds.length !== prevIds.length;
            if (mainSurveyPreview && hasChanged) {
                renderSurveyCards('mainSurveyPreview', surveys, 2);
            }
        }

        if (document.getElementById('widgetSurveyReminder')) {
            renderSurveyReminderWidget(surveys);
        }
    } catch {
        // Ignore background polling errors
    } finally {
        isPollingSurveys = false;
    }
}

function createEmptyState(icon, title, description) {
    return `
        <div class="empty-state">
            <i class="fas ${icon}"></i>
            <h4>${escapeHtml(title)}</h4>
            <p>${escapeHtml(description)}</p>
        </div>
    `;
}

function renderEventCards(targetId, events, limit = null) {
    const container = document.getElementById(targetId);
    if (!container) return;

    const list = limit ? events.slice(0, limit) : events;
    if (list.length === 0) {
        container.innerHTML = createEmptyState('fa-calendar-xmark', 'No events yet', 'Staff has not posted any events at the moment.');
        return;
    }

    container.innerHTML = list.map((event) => {
        const iso = event.datetime || (event.date && event.time ? `${event.date}T${event.time}` : event.date);
        const date = new Date(iso);
        const month = Number.isNaN(date.getTime())
            ? 'TBD'
            : date.toLocaleDateString('en-PH', { month: 'short' });
        const day = Number.isNaN(date.getTime())
            ? '--'
            : date.toLocaleDateString('en-PH', { day: '2-digit' });

        return `
            <article class="event-card">
                <div class="event-head">
                    <div class="event-date-box">
                        <span>${escapeHtml(month)}</span>
                        <strong>${escapeHtml(day)}</strong>
                    </div>
                    <span class="event-pill">${escapeHtml(event.status || 'Upcoming')}</span>
                </div>
                <div>
                    <h4>${escapeHtml(event.name || 'Untitled Event')}</h4>
                    <p class="section-note">${escapeHtml(formatRelativeLabel(iso))}</p>
                </div>
                <div class="event-meta">
                    <div><i class="fas fa-calendar-day"></i><span>${escapeHtml(formatDateTime(iso))}</span></div>
                    <div><i class="fas fa-map-marker-alt"></i><span>${escapeHtml(event.location || 'Location to follow')}</span></div>
                </div>
            </article>
        `;
    }).join('');
}

/* ==========================================================================
   Smart Category Detection & Community Toast
   ========================================================================== */

function getPostCategory(item) {
    const text = ((item.title || '') + ' ' + (item.content || '') + ' ' + (item.description || '')).toLowerCase();
    if (text.includes('hiring') || text.includes('job') || text.includes('career') || text.includes('intern') || text.includes('vacancy') || text.includes('salary') || text.includes('developer') || text.includes('engineer')) {
        return { slug: 'career', label: 'Career & Jobs', icon: 'fa-briefcase' };
    }
    if (text.includes('spotlight') || text.includes('award') || text.includes('honor') || text.includes('achieve') || text.includes('featured') || text.includes('mentor') || text.includes('alumni story')) {
        return { slug: 'spotlight', label: 'Alumni Spotlight', icon: 'fa-star' };
    }
    if (text.includes('event') || text.includes('homecoming') || text.includes('reunion') || text.includes('gathering') || text.includes('notice') || text.includes('advisory') || text.includes('urgent') || text.includes('announcement') || text.includes('memo')) {
        return { slug: 'announcement', label: 'Announcement', icon: 'fa-bullhorn' };
    }
    if (item.category && item.category !== 'campus news' && item.category !== 'general') {
        const c = String(item.category).toLowerCase();
        if (c.includes('career') || c.includes('job') || c.includes('hiring')) return { slug: 'career', label: 'Career & Jobs', icon: 'fa-briefcase' };
        if (c.includes('spotlight')) return { slug: 'spotlight', label: 'Alumni Spotlight', icon: 'fa-star' };
        if (c.includes('announce')) return { slug: 'announcement', label: 'Announcement', icon: 'fa-bullhorn' };
    }
    return { slug: 'campus', label: 'Campus News', icon: 'fa-university' };
}

function showAlumniToast(message) {
    let toast = document.getElementById('alumniToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'alumniToast';
        toast.className = 'alumni-toast';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 3200);
}

function setupImageLightbox() {
    const modal = document.getElementById('feedLightboxModal');
    const img = document.getElementById('lightboxImg');
    const caption = document.getElementById('lightboxCaption');
    const closeBtn = document.getElementById('lightboxClose');
    const overlay = document.getElementById('lightboxOverlay');

    if (!modal || !img) return;

    window.openFeedLightbox = function(src, alt) {
        img.src = src;
        img.alt = alt || 'Enlarged photo';
        if (caption) caption.textContent = alt || '';
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    function closeLightbox() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        setTimeout(() => { if (!modal.classList.contains('open')) img.src = ''; }, 250);
    }

    if (closeBtn) closeBtn.onclick = closeLightbox;
    if (overlay) overlay.onclick = closeLightbox;
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('open')) {
            closeLightbox();
        }
    });
}

function renderNewsCards(targetId, newsItems, limit = null) {
    const container = document.getElementById(targetId);
    if (!container) return;

    const list = limit ? newsItems.slice(0, limit) : newsItems;

    if (allFeedPosts.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="padding: 48px 24px; text-align: center; background: #fff; border-radius: 24px; border: 1px dashed #cbd5e1;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 14px;">
                    <i class="fas fa-newspaper"></i>
                </div>
                <h3 style="color: #0a2240; font-size: 18px; margin-bottom: 6px;">No posts published yet</h3>
                <p style="color: #64748b; font-size: 14px; max-width: 420px; margin: 0 auto;">Campus news and official staff announcements will appear here once published.</p>
            </div>
        `;
        return;
    }

    if (list.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="padding: 48px 24px; text-align: center; background: #fff; border-radius: 24px; border: 1px dashed #cbd5e1;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: #fef3c7; color: #d97706; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 14px;">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <h3 style="color: #0a2240; font-size: 18px; margin-bottom: 6px;">No posts match your filter</h3>
                <p style="color: #64748b; font-size: 14px; max-width: 420px; margin: 0 auto;">No updates match your selected filter or search term. Try selecting "All Posts" or clearing the search box.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = list.map((item) => {
        const cat = getPostCategory(item);

        return `
        <article class="facebook-post" id="post-${item.id}">
            <div class="facebook-post-header">
                <div class="facebook-post-avatar">
                    <i class="fas fa-university"></i>
                </div>
                <div class="facebook-post-author">
                    <strong>${escapeHtml(item.author || 'Staff Office')}</strong>
                    <div class="facebook-post-meta">
                        <span>${escapeHtml((item.author_role || 'staff').replaceAll('_', ' '))}</span>
                        <span class="facebook-post-dot"></span>
                        <span>${escapeHtml(formatTimeAgo(item.created_at || ''))}</span>
                    </div>
                </div>
                <span class="post-category-tag ${cat.slug}"><i class="fas ${cat.icon}"></i> ${escapeHtml(cat.label)}</span>
            </div>
            <div class="facebook-post-body">
                <h2 class="facebook-post-title">${escapeHtml(item.title || 'Update')}</h2>
                <p class="facebook-post-copy">${escapeHtml(item.content || item.description || item.title || 'New campus update available.')}</p>
            </div>
            ${item.image_url ? `
                <div class="facebook-post-image-wrap">
                    <img class="facebook-post-image lightbox-trigger" src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.title || 'News image')}" onclick="window.openFeedLightbox && window.openFeedLightbox('${escapeHtml(item.image_url)}', '${escapeHtml(item.title || '')}')">
                </div>
            ` : ''}
            <div class="facebook-post-actions">
                <span><i class="fas fa-clock"></i> ${escapeHtml(formatDateTime(item.created_at || ''))}</span>
                <span><i class="fas fa-tag"></i> ${escapeHtml(cat.label)}</span>
                ${item.link_url ? `<a class="facebook-post-link" href="${escapeHtml(item.link_url)}" target="_blank" rel="noopener noreferrer"><i class="fas fa-arrow-up-right-from-square"></i><span>Open Link</span></a>` : ''}
            </div>
        </article>
        `;
    }).join('');
}

/* ==========================================================================
   News Feed Interactive Controls & Category Filtering
   ========================================================================== */

let allFeedPosts = [];
let activeFeedCategory = 'all';
let feedSearchQuery = '';

function initNewsFeed(serverNews) {
    allFeedPosts = Array.isArray(serverNews) ? serverNews : [];

    const countBadge = document.getElementById('feedCountAll');
    if (countBadge) {
        countBadge.textContent = String(allFeedPosts.length);
    }

    setupFeedControls();
    setupImageLightbox();
    renderFilteredFeed();
}

function setupFeedControls() {
    const filterTabs = document.getElementById('feedFilterTabs');
    if (filterTabs && !filterTabs.dataset.bound) {
        filterTabs.dataset.bound = 'true';
        filterTabs.addEventListener('click', (e) => {
            const btn = e.target.closest('.feed-tab-btn');
            if (!btn) return;
            const category = btn.dataset.category || 'all';
            activeFeedCategory = category;

            filterTabs.querySelectorAll('.feed-tab-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');

            renderFilteredFeed();
        });
    }

    const searchInput = document.getElementById('feedSearchInput');
    const searchClear = document.getElementById('feedSearchClear');
    if (searchInput && !searchInput.dataset.bound) {
        searchInput.dataset.bound = 'true';
        searchInput.addEventListener('input', () => {
            feedSearchQuery = searchInput.value.trim().toLowerCase();
            if (searchClear) {
                searchClear.style.display = feedSearchQuery ? 'inline-flex' : 'none';
            }
            renderFilteredFeed();
        });
    }

    if (searchClear && !searchClear.dataset.bound) {
        searchClear.dataset.bound = 'true';
        searchClear.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            feedSearchQuery = '';
            searchClear.style.display = 'none';
            renderFilteredFeed();
            if (searchInput) searchInput.focus();
        });
    }
}

function renderFilteredFeed() {
    let filtered = allFeedPosts;

    if (activeFeedCategory && activeFeedCategory !== 'all') {
        filtered = filtered.filter((post) => {
            const cat = getPostCategory(post);
            return cat.slug === activeFeedCategory;
        });
    }

    if (feedSearchQuery) {
        filtered = filtered.filter((post) => {
            const title = (post.title || '').toLowerCase();
            const content = (post.content || post.description || '').toLowerCase();
            const author = (post.author || '').toLowerCase();
            return title.includes(feedSearchQuery) || content.includes(feedSearchQuery) || author.includes(feedSearchQuery);
        });
    }

    renderNewsCards('alumniNewsGrid', filtered);
}

/* ==========================================================================
   Engaging Community Sidebar Widgets
   ========================================================================== */

function initFeedWidgets(user, events, surveys, news) {
    // 1. Identity snapshot details
    const userDept = document.getElementById('widgetUserDept');
    if (userDept) {
        const dept = user?.course || user?.department || user?.program || 'Bachelor of Science in Information Technology';
        userDept.textContent = dept;
    }

    // 2. Upcoming Event Spotlight
    const spotlightWidget = document.getElementById('widgetEventSpotlight');
    if (spotlightWidget && Array.isArray(events)) {
        const upcomingEvents = events.filter((e) => {
            const iso = e.datetime || (e.date && e.time ? `${e.date}T${e.time}` : e.date);
            const d = new Date(iso);
            return !Number.isNaN(d.getTime()) && d.getTime() >= Date.now();
        }).sort((a, b) => {
            const da = new Date(a.datetime || (a.date && a.time ? `${a.date}T${a.time}` : a.date));
            const db = new Date(b.datetime || (b.date && b.time ? `${b.date}T${b.time}` : b.date));
            return da - db;
        });

        const targetEvent = upcomingEvents[0] || events[0];
        if (targetEvent) {
            const titleEl = document.getElementById('widgetEventTitle');
            const timeEl = document.getElementById('widgetEventTime');
            const locEl = document.getElementById('widgetEventLocation');
            const monthEl = document.getElementById('widgetEventMonth');
            const dayEl = document.getElementById('widgetEventDay');
            const countdownEl = document.getElementById('widgetEventCountdown');

            if (titleEl) titleEl.textContent = targetEvent.name || targetEvent.title || 'Alumni Gathering';
            if (timeEl) timeEl.textContent = targetEvent.time || (targetEvent.datetime ? formatDateTime(targetEvent.datetime) : 'Check event schedule');
            if (locEl) locEl.textContent = targetEvent.location || 'USTP Campus / Online';

            const iso = targetEvent.datetime || (targetEvent.date && targetEvent.time ? `${targetEvent.date}T${targetEvent.time}` : targetEvent.date);
            const eventDate = new Date(iso);
            if (!Number.isNaN(eventDate.getTime())) {
                const monthNames = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
                if (monthEl) monthEl.textContent = monthNames[eventDate.getMonth()];
                if (dayEl) dayEl.textContent = String(eventDate.getDate()).padStart(2, '0');

                const diffDays = Math.ceil((eventDate.getTime() - Date.now()) / (1000 * 60 * 60 * 24));
                if (countdownEl) {
                    if (diffDays <= 0) countdownEl.textContent = 'Today!';
                    else if (diffDays === 1) countdownEl.textContent = 'Tomorrow';
                    else countdownEl.textContent = `In ${diffDays} days`;
                }
            }
        }
    }

    // 3. Community Pulse interactive poll
    initCommunityPoll();

    // 4. Survey reminder widget
    renderSurveyReminderWidget(surveys);
}

function initCommunityPoll() {
    const pollContainer = document.getElementById('widgetCommunityPoll');
    if (!pollContainer) return;

    const STORAGE_KEY = 'alumni_community_poll_state_v1';
    let pollData;
    try {
        pollData = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
    } catch (_) {
        pollData = null;
    }

    if (!pollData || typeof pollData !== 'object' || !pollData.votes) {
        pollData = {
            votes: { '1': 54, '2': 78, '3': 43, '4': 25 },
            userChoice: null,
        };
    }

    function renderPoll() {
        const total = Object.values(pollData.votes).reduce((sum, n) => sum + Number(n), 0);
        const voteCountEl = document.getElementById('pollVoteCount');
        const statusNote = document.getElementById('pollStatusNote');

        if (voteCountEl) {
            voteCountEl.innerHTML = `<i class="fas fa-users"></i> ${total} votes cast`;
        }
        if (statusNote) {
            if (pollData.userChoice) {
                statusNote.textContent = '✓ You voted! Thank you';
                statusNote.style.color = '#10b981';
            } else {
                statusNote.textContent = 'Click an option to vote';
                statusNote.style.color = '';
            }
        }

        const buttons = pollContainer.querySelectorAll('.poll-option-btn');
        buttons.forEach((btn) => {
            const choiceId = btn.dataset.pollChoice;
            const count = pollData.votes[choiceId] || 0;
            const pct = total > 0 ? Math.round((count / total) * 100) : 0;

            const pctEl = btn.querySelector('.poll-choice-percent');
            const barEl = btn.querySelector('.poll-choice-bar');

            if (pctEl) pctEl.textContent = `${pct}%`;
            if (barEl) barEl.style.width = `${pct}%`;

            if (pollData.userChoice === choiceId) {
                btn.classList.add('selected');
            } else {
                btn.classList.remove('selected');
            }
        });
    }

    renderPoll();

    if (!pollContainer.dataset.bound) {
        pollContainer.dataset.bound = 'true';
        pollContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('.poll-option-btn');
            if (!btn) return;
            const choiceId = btn.dataset.pollChoice;
            if (!choiceId) return;

            if (pollData.userChoice === choiceId) {
                showAlumniToast('You already voted for this option!');
                return;
            }

            // Adjust vote counts
            if (pollData.userChoice && pollData.votes[pollData.userChoice] > 0) {
                pollData.votes[pollData.userChoice]--;
            }

            pollData.votes[choiceId] = (pollData.votes[choiceId] || 0) + 1;
            pollData.userChoice = choiceId;

            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(pollData));
            } catch (_) {}

            renderPoll();
            showAlumniToast('Your vote has been counted! Thank you for participating.');
        });
    }
}

function renderSurveyReminderWidget(surveys) {
    const surveyReminder = document.getElementById('widgetSurveyReminder');
    if (!surveyReminder) return;

    const pending = getPendingUnansweredSurveys(surveys);
    const titleEl = document.getElementById('widgetSurveyTitle');
    const descEl = document.getElementById('widgetSurveyDesc');
    const badgeEl = document.getElementById('widgetSurveyBadge');
    const btnEl = document.getElementById('widgetSurveyBtn');

    if (pending.length > 0) {
        const topSurvey = pending[0];
        if (titleEl) titleEl.textContent = topSurvey.title || 'Alumni Survey';
        if (descEl) descEl.textContent = topSurvey.description || 'Your answers take ~2 minutes and help shape future university initiatives.';
        if (badgeEl) {
            badgeEl.textContent = `${pending.length} Action Needed`;
            badgeEl.className = 'widget-badge new-badge';
        }
        if (btnEl) {
            btnEl.href = getSurveyRespondUrl(topSurvey.id);
            btnEl.innerHTML = '<i class="fas fa-reply"></i> Answer Survey';
        }
    } else {
        if (titleEl) titleEl.textContent = 'All Caught Up!';
        if (descEl) descEl.textContent = 'Thank you for keeping your feedback updated. You will receive an alert when a new survey is available.';
        if (badgeEl) {
            badgeEl.textContent = 'Completed ✓';
            badgeEl.className = 'widget-badge done-badge';
        }
        if (btnEl) {
            btnEl.href = 'Surveys.html';
            btnEl.innerHTML = '<i class="fas fa-list-check"></i> View Surveys';
        }
    }
}

/**
 * Survey picker cards: title only, Respond opens SurveyRespond.html (max 3 columns — see CSS).
 */
function renderSurveyCards(targetId, surveys, limit = null) {
    const container = document.getElementById(targetId);
    if (!container) return;

    const list = limit ? surveys.slice(0, limit) : surveys;
    if (list.length === 0) {
        container.innerHTML = createEmptyState('fa-clipboard-question', 'No surveys available', 'When staff publishes surveys, you will see them here.');
        return;
    }

    container.innerHTML = list.map((survey) => {
        const responded = Boolean(survey.alreadyResponded);
        const closed = !Boolean(survey.isAcceptingResponses);
        const allowMulti = Boolean(survey.allowMultipleResponses);
        const responseCount = Number(survey.responseCount || 0);
        const respondHref = getSurveyRespondUrl(survey.id);
        const categories = getSurveyCategoryGroups(survey);
        const qNote = categories.reduce((total, category) => total + category.questions.length, 0);

        const cardMods = [responded ? 'survey-pick-card--done' : '', closed ? 'survey-pick-card--closed' : ''].filter(Boolean).join(' ');

        let footerHtml;
        if (responded) {
            footerHtml = `<span class="survey-pick-card-status"><i class="fas fa-circle-check"></i> Submitted</span>`;
        } else if (closed) {
            footerHtml = `
                <div class="survey-pick-card-footer-stack">
                    <span class="survey-pick-card-closed-label"><i class="fas fa-lock"></i> Unavailable</span>
                    <a class="button-secondary survey-view-btn" href="${escapeHtml(respondHref)}"><i class="fas fa-eye"></i><span>View survey</span></a>
                </div>`;
        } else {
            footerHtml = `<a class="button-primary survey-respond-btn" href="${escapeHtml(respondHref)}"><i class="fas fa-reply"></i><span>Respond</span></a>`;
        }

        const needsAnswer = !responded && !closed;
        const statusBadge = needsAnswer
            ? `<span class="survey-card-new-badge"><i class="fas fa-bell"></i> Open</span>`
            : '';

        const metaText = `${qNote} question${qNote === 1 ? '' : 's'} · ${getSurveyAvailabilityNote(survey)}${responseCount > 0 ? ` · ${responseCount} past submission${responseCount === 1 ? '' : 's'}` : ''}${allowMulti ? ' · Multiple responses allowed' : ''}`;
        const cardHtml = `
        <article class="survey-pick-card ${cardMods}">
            <div class="survey-pick-card-inner">
                <h4 class="survey-pick-card-title">${escapeHtml(survey.title || 'Survey')}${statusBadge}</h4>
                <p class="survey-pick-card-meta">${escapeHtml(metaText)}</p>
                <div class="survey-pick-card-footer">
                    ${footerHtml}
                </div>
            </div>
        </article>`;
        return cardHtml;
    }).join('');
}

let surveyRespondSession = {
    surveyId: null,
    currentCatIndex: 0,
    history: [0],
    answers: {},
};

function getSurveyCategoryGroups(survey) {
    if (Array.isArray(survey?.categories) && survey.categories.length > 0) {
        return survey.categories.map((category) => ({
            id: Number(category.id || 0) || category.id || null,
            name: category.name || category.category_name || '',
            description: category.description || '',
            questions: Array.isArray(category.questions) ? category.questions.map((question) => ({
                id: Number(question.id || 0) || question.id || null,
                text: question.text || '',
                type: question.type || 'multiple_choice',
                required: question.required === true || question.required === 1 || question.required === '1' || question.required === 'true',
                choices: Array.isArray(question.choices) ? [...question.choices] : [],
                options: Array.isArray(question.options) ? [...question.options] : [],
                logic: question.logic || null,
                ratingMin: question.ratingMin,
                ratingMax: question.ratingMax,
                ratingMinLabel: question.ratingMinLabel,
                ratingMaxLabel: question.ratingMaxLabel,
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
            id: Number(question.id || 0) || question.id || null,
            text: question.text || '',
            type: question.type || 'multiple_choice',
            required: question.required === true || question.required === 1 || question.required === '1' || question.required === 'true',
            choices: Array.isArray(question.choices) ? [...question.choices] : [],
            options: Array.isArray(question.options) ? [...question.options] : [],
            logic: question.logic || null,
            ratingMin: question.ratingMin,
            ratingMax: question.ratingMax,
            ratingMinLabel: question.ratingMinLabel,
            ratingMaxLabel: question.ratingMaxLabel,
        })),
    }];
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

function getSurveyAvailabilityNote(survey) {
    const openAt = survey?.openDateTime;
    const closeAt = survey?.closeDateTime;
    const accepting = Boolean(survey?.isAcceptingResponses);
    const now = Date.now();
    const openTime = openAt ? new Date(String(openAt).replace(' ', 'T')).getTime() : null;

    if (accepting) {
        if (openAt && closeAt) {
            return `Open from ${formatSurveyDatetimeLabel(openAt)} until ${formatSurveyDatetimeLabel(closeAt)}`;
        }
        if (closeAt) {
            return `Open until ${formatSurveyDatetimeLabel(closeAt)}`;
        }
        if (openAt) {
            return `Opened ${formatSurveyDatetimeLabel(openAt)}`;
        }
        return 'Open now';
    }

    if (openAt && !Number.isNaN(openTime) && openTime !== null && now < openTime) {
        return `Opens ${formatSurveyDatetimeLabel(openAt)}`;
    }
    if (closeAt) {
        return `Closed ${formatSurveyDatetimeLabel(closeAt)}`;
    }
    return 'Not accepting responses';
}

function renderSurveyDetailForm(mountId, survey) {
    const mount = document.getElementById(mountId);
    if (!mount || !survey) return;

    const responded = Boolean(survey.alreadyResponded);
    const closed = !Boolean(survey.isAcceptingResponses);
    const allowMulti = Boolean(survey.allowMultipleResponses);
    const responseCount = Number(survey.responseCount || 0);

    const formLocked = closed || responded;
    const sid = survey.id;
    const categories = getSurveyCategoryGroups(survey);
    const totalCategories = categories.length;
    const totalQuestions = categories.reduce((total, category) => total + category.questions.length, 0);

    // Initialize session if new survey
    if (surveyRespondSession.surveyId !== sid) {
        surveyRespondSession = {
            surveyId: sid,
            currentCatIndex: 0,
            history: [0],
            answers: {},
        };
    }

    if (surveyRespondSession.currentCatIndex >= totalCategories) {
        surveyRespondSession.currentCatIndex = 0;
        surveyRespondSession.history = [0];
    }

    const currentCatIndex = surveyRespondSession.currentCatIndex;
    const currentCategory = categories[currentCatIndex] || { name: 'General', questions: [] };
    const categoriesLeft = totalCategories - currentCatIndex - 1;
    const progressPercent = totalCategories > 0 ? Math.round(((currentCatIndex + 1) / totalCategories) * 100) : 100;

    const descHtml = survey.description
        ? `<p class="section-note survey-detail-desc">${escapeHtml(survey.description)}</p>`
        : '';

    const closedBanner = closed
        ? `<p class="survey-closed-banner"><i class="fas fa-lock"></i> This survey is not accepting responses right now. Questions are shown for reference only.</p>`
        : '';

    let respondedBannerHtml = '';
    if (responded && !closed) {
        respondedBannerHtml = `<p class="survey-responded-banner"><i class="fas fa-circle-check"></i> You already submitted this survey (${responseCount} total submission${responseCount === 1 ? '' : 's'} on file).</p>`;
    }

    const isFirstStep = surveyRespondSession.history.length <= 1;
    const isLastStep = currentCatIndex === totalCategories - 1;
    const showSubmit = !closed && !responded;

    // Calculate question offset
    let questionOffset = 0;
    for (let i = 0; i < currentCatIndex; i++) {
        questionOffset += (categories[i]?.questions?.length || 0);
    }

    mount.innerHTML = `
        <div class="survey-detail-inner${formLocked ? ' survey-detail-inner--locked' : ''}">
            ${closedBanner}
            ${respondedBannerHtml}
            ${descHtml}

            <!-- Category Stepper Progress Bar -->
            <div class="survey-respond-progress-card">
                <div class="survey-respond-progress-top">
                    <div class="survey-respond-step-badge">
                        <i class="fas fa-layer-group"></i> Section ${currentCatIndex + 1} of ${totalCategories}
                    </div>
                    <div class="survey-respond-remaining-badge">
                        ${categoriesLeft > 0 
                            ? `<i class="fas fa-hourglass-half"></i> ${categoriesLeft} section${categoriesLeft === 1 ? '' : 's'} left` 
                            : `<i class="fas fa-flag-checkered"></i> Final section`}
                    </div>
                </div>
                <div class="survey-respond-progress-track">
                    <div class="survey-respond-progress-fill" style="width: ${progressPercent}%;"></div>
                </div>
            </div>

            <!-- Active Category Card -->
            <div class="survey-respond-category-card">
                <div class="survey-respond-category-header">
                    <div class="survey-respond-category-title-row">
                        <span class="survey-respond-cat-num">${currentCatIndex + 1}</span>
                        <h2 class="survey-respond-category-title">${escapeHtml(currentCategory.name || 'General')}</h2>
                    </div>
                    ${currentCategory.description ? `<p class="survey-respond-category-description">${escapeHtml(currentCategory.description)}</p>` : ''}
                </div>

                <div class="survey-question-list">
                    ${(currentCategory.questions || []).length === 0 
                        ? `<div style="text-align:center;padding:30px;color:#94a3b8;"><i class="fas fa-inbox" style="font-size:24px;margin-bottom:8px;display:block;"></i>No questions in this section.</div>`
                        : currentCategory.questions.map((question, index) => {
                            const isReq = isAlumniSurveyQuestionRequired(question);
                            return `
                            <div class="survey-question-item">
                                <div class="survey-question-text">
                                    <span class="survey-question-num">${questionOffset + index + 1}.</span> 
                                    ${escapeHtml(question.text || '')}
                                    ${isReq 
                                        ? '<span class="survey-question-req-star" style="color:#ef4444;font-weight:700;margin-left:4px;" title="Required">*</span>' 
                                        : ' <span class="survey-question-optional-tag" style="font-size:12px;color:#94a3b8;font-weight:normal;margin-left:4px;">(Optional)</span>'}
                                </div>
                                ${renderSurveyAnswerInput(sid, question, formLocked)}
                            </div>
                            `;
                        }).join('')}
                </div>
            </div>

            <!-- Stepper Action Navigation -->
            <div class="survey-actions survey-respond-nav-actions">
                ${!isFirstStep
                    ? `<button class="button-secondary" type="button" onclick="surveyRespondGoBack(${sid})">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>`
                    : `<div></div>`}

                ${showSubmit
                    ? (isLastStep
                        ? `<button class="button-primary survey-btn-submit" type="button" onclick="surveyRespondNextOrSubmit(${sid})">
                            <i class="fas fa-paper-plane"></i> Submit answers
                        </button>`
                        : `<button class="button-primary" type="button" onclick="surveyRespondNextOrSubmit(${sid})">
                            Continue <i class="fas fa-arrow-right"></i>
                        </button>`)
                    : (!isLastStep
                        ? `<button class="button-secondary" type="button" onclick="surveyRespondNextOrSubmit(${sid})">
                            Next section <i class="fas fa-arrow-right"></i>
                        </button>`
                        : `<p class="section-note survey-actions-note">${closed ? 'This survey is closed.' : 'You have already submitted.'}</p>`)}
            </div>
        </div>
    `;

    // Restore any previously answered inputs
    restoreCategoryAnswers(sid, currentCategory);

    const pageTitle = document.getElementById('surveyRespondPageTitle');
    const pageSub = document.getElementById('surveyRespondPageSubtitle');
    if (pageTitle) pageTitle.textContent = survey.title || 'Survey';
    if (pageSub) {
        if (responded) {
            pageSub.textContent = 'Your submission for this survey is on file.';
        } else if (closed) {
            pageSub.textContent = `${getSurveyAvailabilityNote(survey)}. Questions below are read-only.`;
        } else {
            pageSub.textContent = totalQuestions
                ? `${getSurveyAvailabilityNote(survey)}. Section ${currentCatIndex + 1} of ${totalCategories}.`
                : 'No questions are available for this survey yet.';
        }
    }
}

function restoreCategoryAnswers(surveyId, category) {
    if (!category || !Array.isArray(category.questions)) return;
    category.questions.forEach((question) => {
        const qId = question.id;
        const saved = surveyRespondSession.answers[qId];
        if (!saved) return;

        const input = document.querySelector(`[data-survey-id="${surveyId}"][data-question-id="${qId}"]`);
        if (!input) return;

        if (question.type === 'multiple_choice') {
            if (saved.choice_id !== undefined && saved.choice_id !== null) {
                input.value = String(saved.choice_id);
            }
        } else if (question.type !== 'image') {
            if (saved.answer_text !== undefined) {
                input.value = saved.answer_text;
            }
        }
    });
}

function saveCurrentCategoryAnswers(surveyId, category) {
    if (!category || !Array.isArray(category.questions)) return true;

    category.questions.forEach((question) => {
        const qId = question.id;
        const input = document.querySelector(`[data-survey-id="${surveyId}"][data-question-id="${qId}"]`);
        if (!input) return;

        input.classList.remove('error');

        if (question.type === 'image') {
            const file = input.files ? input.files[0] : null;
            if (file) {
                surveyRespondSession.answers[qId] = {
                    question_id: qId,
                    file: file,
                    answer_text: '',
                };
            }
            return;
        }

        const rawValue = String(input.value || '').trim();
        if (rawValue) {
            if (question.type === 'multiple_choice') {
                surveyRespondSession.answers[qId] = {
                    question_id: qId,
                    choice_id: Number(rawValue),
                };
            } else {
                surveyRespondSession.answers[qId] = {
                    question_id: qId,
                    answer_text: rawValue,
                };
            }
        } else {
            delete surveyRespondSession.answers[qId];
        }
    });

    return true;
}

function pruneDivergentAnswers(categories) {
    const activeCatIndices = new Set(surveyRespondSession.history);
    const activeQuestionIds = new Set();
    categories.forEach((cat, idx) => {
        if (activeCatIndices.has(idx)) {
            (cat.questions || []).forEach(q => {
                activeQuestionIds.add(Number(q.id));
                activeQuestionIds.add(String(q.id));
            });
        }
    });

    Object.keys(surveyRespondSession.answers).forEach(qId => {
        if (!activeQuestionIds.has(Number(qId)) && !activeQuestionIds.has(String(qId))) {
            delete surveyRespondSession.answers[qId];
        }
    });
}

const ALUMNI_PREMADE_REQUIRED_QUESTIONS = new Set([
    'what is your employment status',
    'how long did it take you to find your first job after graduation',
    'is your job related to your degree',
    'name of employment business or company',
]);

function normalizeAlumniSurveyQuestionText(text) {
    return String(text || '')
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function isAlumniSurveyQuestionRequired(question) {
    if (!question) return false;
    if (
        question.required === true
        || question.required === 1
        || question.required === '1'
        || question.required === 'true'
        || question.isPreMade === true
    ) {
        return true;
    }
    const norm = normalizeAlumniSurveyQuestionText(question.text || question.question_text || '');
    return ALUMNI_PREMADE_REQUIRED_QUESTIONS.has(norm);
}

function showSurveySectionValidationError(message) {
    let banner = document.getElementById('surveySectionValidationError');
    const host = document.querySelector('.survey-question-list');
    if (!host) return;

    if (!message) {
        if (banner) banner.remove();
        return;
    }

    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'surveySectionValidationError';
        banner.setAttribute('role', 'alert');
        banner.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:14px;padding:10px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:13px;font-weight:600;';
        host.parentElement?.insertBefore(banner, host);
    }
    banner.innerHTML = `<i class="fas fa-circle-exclamation" aria-hidden="true"></i><span>${escapeHtml(message)}</span>`;
}

function validateCategoryAnswers(surveyId, category) {
    showSurveySectionValidationError('');
    if (!category || !Array.isArray(category.questions) || category.questions.length === 0) {
        return { valid: true };
    }

    let valid = true;
    let missingCount = 0;
    let firstUnansweredItem = null;
    let firstUnansweredInput = null;

    // Reset previous error classes
    document.querySelectorAll('.survey-question-item--error').forEach((el) => el.classList.remove('survey-question-item--error'));
    document.querySelectorAll('.survey-answer-input.input-error').forEach((el) => el.classList.remove('input-error'));

    category.questions.forEach((question) => {
        if (!isAlumniSurveyQuestionRequired(question)) {
            // Optional question: allow it to be blank or null
            return;
        }

        const qId = Number(question.id || 0) || question.id;
        const input = document.querySelector(`[data-survey-id="${surveyId}"][data-question-id="${qId}"]`);
        if (!input) return;

        let hasAnswer = false;

        if (question.type === 'image') {
            const hasFile = (input.files && input.files.length > 0) || Boolean(surveyRespondSession.answers[qId]?.file);
            hasAnswer = Boolean(hasFile);
        } else if (question.type === 'multiple_choice' || question.type === 'rating') {
            const val = String(input.value || '').trim();
            hasAnswer = val !== '';
        } else {
            // Text answer (reject whitespace-only)
            const val = String(input.value || '').trim();
            hasAnswer = val.length > 0;
        }

        if (!hasAnswer) {
            valid = false;
            missingCount++;

            input.classList.add('input-error');
            const questionItem = input.closest('.survey-question-item');
            if (questionItem) {
                questionItem.classList.add('survey-question-item--error');
            }

            if (!firstUnansweredItem && questionItem) {
                firstUnansweredItem = questionItem;
            }
            if (!firstUnansweredInput) {
                firstUnansweredInput = input;
            }

            const clearHandler = () => {
                input.classList.remove('input-error');
                if (questionItem) {
                    questionItem.classList.remove('survey-question-item--error');
                }
                if (document.querySelectorAll('.survey-question-item--error').length === 0) {
                    showSurveySectionValidationError('');
                }
                input.removeEventListener('input', clearHandler);
                input.removeEventListener('change', clearHandler);
            };
            input.addEventListener('input', clearHandler);
            input.addEventListener('change', clearHandler);
        }
    });

    if (!valid) {
        showSurveySectionValidationError(
            missingCount === 1
                ? 'Please answer the required question highlighted below before continuing.'
                : `Please answer all ${missingCount} required questions highlighted below before continuing.`
        );
        const targetToScroll = firstUnansweredItem || firstUnansweredInput;
        if (targetToScroll) {
            targetToScroll.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        if (firstUnansweredInput) {
            try {
                firstUnansweredInput.focus({ preventScroll: true });
            } catch (e) {
                firstUnansweredInput.focus();
            }
        }
    }

    return { valid };
}

function validateAllVisitedRequiredAnswers(categories) {
    const visitedIndices = Array.from(new Set(surveyRespondSession.history || [0]));
    for (const idx of visitedIndices) {
        const cat = categories[idx];
        if (!cat || !Array.isArray(cat.questions)) continue;
        for (const question of cat.questions) {
            if (!isAlumniSurveyQuestionRequired(question)) continue;
            const qId = Number(question.id || 0) || question.id;
            const ans = surveyRespondSession.answers[qId];
            let hasAnswer = false;
            if (question.type === 'image') {
                hasAnswer = Boolean(ans?.file);
            } else if (question.type === 'multiple_choice') {
                hasAnswer = Boolean(ans?.choice_id && String(ans.choice_id).trim() !== '');
            } else {
                hasAnswer = Boolean(ans?.answer_text && String(ans.answer_text).trim() !== '');
            }
            if (!hasAnswer) {
                return {
                    valid: false,
                    categoryIndex: idx,
                    questionText: question.text || 'Required question',
                };
            }
        }
    }
    return { valid: true };
}

function surveyRespondNextOrSubmit(surveyId) {
    const survey = currentSurveys.find((item) => Number(item.id) === Number(surveyId));
    if (!survey) return;

    const categories = getSurveyCategoryGroups(survey);
    const totalCategories = categories.length;
    const currentCatIndex = surveyRespondSession.currentCatIndex;
    const currentCategory = categories[currentCatIndex];

    const formLocked = Boolean(survey.alreadyResponded) || !Boolean(survey.isAcceptingResponses);
    if (!formLocked) {
        const validation = validateCategoryAnswers(surveyId, currentCategory);
        if (!validation.valid) {
            return;
        }
        saveCurrentCategoryAnswers(surveyId, currentCategory);
    }

    // Check branching logic from multiple choice questions in current category
    let nextTargetCatIndex = null;
    let shouldSubmitImmediately = false;

    if (currentCategory && Array.isArray(currentCategory.questions)) {
        for (const question of currentCategory.questions) {
            if (question.type === 'multiple_choice' && question.logic?.enabled && Array.isArray(question.logic.rules)) {
                const answer = surveyRespondSession.answers[question.id];
                if (answer && answer.choice_id) {
                    const choiceIdx = (question.choices || []).findIndex(c => Number(c.id) === Number(answer.choice_id));
                    const rule = question.logic.rules.find(r => Number(r.optionIndex) === Number(choiceIdx));
                    if (rule) {
                        if (rule.action === 'submit') {
                            shouldSubmitImmediately = true;
                            break;
                        } else if (rule.action === 'go_to_section') {
                            let targetIdx = -1;
                            if (rule.targetCatId) {
                                targetIdx = categories.findIndex(c => 
                                    String(c.id) === String(rule.targetCatId) || 
                                    (c.id && Number(c.id) === Number(rule.targetCatId))
                                );
                            }
                            if (targetIdx === -1 && rule.targetCatIndex !== null && rule.targetCatIndex !== undefined) {
                                if (rule.targetCatIndex >= 0 && rule.targetCatIndex < categories.length) {
                                    targetIdx = Number(rule.targetCatIndex);
                                }
                            }
                            if (targetIdx !== -1) {
                                nextTargetCatIndex = targetIdx;
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    if (shouldSubmitImmediately) {
        if (!formLocked) {
            pruneDivergentAnswers(categories);
            const visitedCheck = validateAllVisitedRequiredAnswers(categories);
            if (!visitedCheck.valid) {
                surveyRespondSession.currentCatIndex = visitedCheck.categoryIndex;
                renderSurveyDetailForm('alumniSurveyDetailMount', survey);
                validateCategoryAnswers(surveyId, categories[visitedCheck.categoryIndex]);
                return;
            }
            promptSubmitSurveyConfirmation(surveyId);
        }
        return;
    }

    if (nextTargetCatIndex !== null) {
        surveyRespondSession.currentCatIndex = nextTargetCatIndex;
        surveyRespondSession.history.push(nextTargetCatIndex);
        pruneDivergentAnswers(categories);
        renderSurveyDetailForm('alumniSurveyDetailMount', survey);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    if (currentCatIndex < totalCategories - 1) {
        const nextIdx = currentCatIndex + 1;
        surveyRespondSession.currentCatIndex = nextIdx;
        surveyRespondSession.history.push(nextIdx);
        pruneDivergentAnswers(categories);
        renderSurveyDetailForm('alumniSurveyDetailMount', survey);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    // Final step: submit
    if (!formLocked) {
        pruneDivergentAnswers(categories);
        const visitedCheck = validateAllVisitedRequiredAnswers(categories);
        if (!visitedCheck.valid) {
            surveyRespondSession.currentCatIndex = visitedCheck.categoryIndex;
            renderSurveyDetailForm('alumniSurveyDetailMount', survey);
            validateCategoryAnswers(surveyId, categories[visitedCheck.categoryIndex]);
            return;
        }
        promptSubmitSurveyConfirmation(surveyId);
    }
}

function surveyRespondGoBack(surveyId) {
    const survey = currentSurveys.find((item) => Number(item.id) === Number(surveyId));
    if (!survey) return;

    if (surveyRespondSession.history.length > 1) {
        const categories = getSurveyCategoryGroups(survey);
        const currentCategory = categories[surveyRespondSession.currentCatIndex];
        saveCurrentCategoryAnswers(surveyId, currentCategory);
        surveyRespondSession.history.pop();
        surveyRespondSession.currentCatIndex = surveyRespondSession.history[surveyRespondSession.history.length - 1];
        pruneDivergentAnswers(categories);
        renderSurveyDetailForm('alumniSurveyDetailMount', survey);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function renderSurveyAnswerInput(surveyId, question, disabled = false) {
    const questionId = Number(question.id || 0) || question.id;
    const type = String(question.type || 'text');
    const inputName = `survey_${surveyId}_question_${questionId}`;
    const disabledAttr = disabled ? ' disabled' : '';

    if (type === 'multiple_choice') {
        const choices = Array.isArray(question.choices) && question.choices.length > 0
            ? question.choices 
            : (Array.isArray(question.options) ? question.options.map((opt, idx) => ({ id: idx + 1, text: opt })) : []);
        return `
            <select data-survey-id="${surveyId}" data-question-id="${questionId}" data-question-type="${escapeHtml(type)}" class="survey-answer-input"${disabledAttr}>
                <option value="">Select an answer</option>
                ${choices.map((choice) => `
                    <option value="${choice.id}">${escapeHtml(choice.text || '')}</option>
                `).join('')}
            </select>
        `;
    }

    if (type === 'rating') {
        const rMin = question.ratingMin ?? 1;
        const rMax = question.ratingMax ?? 5;
        const rMinLabel = question.ratingMinLabel || '';
        const rMaxLabel = question.ratingMaxLabel || '';
        let optionsHtml = '<option value="">Select a rating</option>';
        for (let rv = rMin; rv <= rMax; rv++) {
            let label = String(rv);
            if (rv === rMin && rMinLabel) label += ` - ${rMinLabel}`;
            else if (rv === rMax && rMaxLabel) label += ` - ${rMaxLabel}`;
            optionsHtml += `<option value="${rv}">${label}</option>`;
        }
        return `
            <select data-survey-id="${surveyId}" data-question-id="${questionId}" data-question-type="${escapeHtml(type)}" class="survey-answer-input"${disabledAttr}>
                ${optionsHtml}
            </select>
        `;
    }

    if (type === 'image') {
        return `
            <input name="${inputName}" data-survey-id="${surveyId}" data-question-id="${questionId}" data-question-type="${escapeHtml(type)}" class="survey-answer-input" type="file" accept="image/jpeg,image/png"${disabledAttr}>
        `;
    }

    return `
        <textarea name="${inputName}" data-survey-id="${surveyId}" data-question-id="${questionId}" data-question-type="${escapeHtml(type)}" class="survey-answer-input survey-answer-text" placeholder="Type your answer here..."${disabledAttr}></textarea>
    `;
}

let pendingAlumniSurveySubmitId = null;

function promptSubmitSurveyConfirmation(surveyId) {
    const survey = currentSurveys.find((item) => Number(item.id) === Number(surveyId));
    if (!survey) return;

    const categories = getSurveyCategoryGroups(survey);
    const currentCategory = categories[surveyRespondSession.currentCatIndex];
    if (!survey.alreadyResponded && survey.isAcceptingResponses) {
        const validation = validateCategoryAnswers(surveyId, currentCategory);
        if (!validation.valid) {
            return;
        }
    }
    saveCurrentCategoryAnswers(surveyId, currentCategory);
    pruneDivergentAnswers(categories);

    pendingAlumniSurveySubmitId = surveyId;

    // Count how many answered questions
    let answeredCount = 0;
    let totalQuestions = 0;
    const visitedCatIndices = new Set(surveyRespondSession.history);
    categories.forEach((cat, idx) => {
        if (visitedCatIndices.has(idx)) {
            (cat.questions || []).forEach((q) => {
                totalQuestions++;
                const ans = surveyRespondSession.answers[q.id];
                if (ans && (ans.file || (ans.choice_id !== undefined && ans.choice_id !== null) || (ans.answer_text && String(ans.answer_text).trim() !== ''))) {
                    answeredCount++;
                }
            });
        }
    });

    const modal = document.getElementById('confirmSubmitSurveyModal');
    if (modal) {
        const titleEl = document.getElementById('confirmSubmitSurveyTitle');
        const countEl = document.getElementById('confirmSubmitAnswersCount');
        if (titleEl) titleEl.textContent = survey.title || 'Untitled Survey';
        if (countEl) countEl.textContent = `${answeredCount} of ${totalQuestions} question${totalQuestions === 1 ? '' : 's'} answered`;
        modal.style.display = 'flex';
        return;
    }

    // Centralized confirmation dialog fallback
    ensureAlumniActionConfirm().then(async (ac) => {
        if (!ac) return;
        const confirmed = await ac.medium({
            title: 'Submit Survey Response?',
            message: `Are you sure you want to submit your answers for "${survey.title || 'this survey'}"?`,
            record: survey.title || 'Alumni Survey',
            consequence: 'Once submitted, your answers will be officially recorded and cannot be edited.',
            confirmText: 'Yes, Submit Survey',
            icon: 'fa-paper-plane'
        });
        if (confirmed) {
            submitAlumniSurvey(surveyId);
        }
    });
}

function closeConfirmSubmitModal() {
    const modal = document.getElementById('confirmSubmitSurveyModal');
    if (modal) {
        modal.style.display = 'none';
    }
    pendingAlumniSurveySubmitId = null;
}

async function executeSubmitAlumniSurvey() {
    if (!pendingAlumniSurveySubmitId) return;
    const sid = pendingAlumniSurveySubmitId;

    const submitBtn = document.getElementById('confirmSubmitSurveyButton');
    const cancelBtn = document.getElementById('confirmSubmitCancelButton');
    const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
    }
    if (cancelBtn) {
        cancelBtn.disabled = true;
    }

    try {
        await submitAlumniSurvey(sid);
    } finally {
        closeConfirmSubmitModal();
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
        }
        if (cancelBtn) {
            cancelBtn.disabled = false;
        }
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('confirmSubmitSurveyModal');
        if (modal && modal.style.display !== 'none') {
            closeConfirmSubmitModal();
        }
    }
});

async function submitAlumniSurvey(surveyId) {
    const survey = currentSurveys.find((item) => Number(item.id) === Number(surveyId));
    if (!survey) {
        alert('Unable to find the selected survey.');
        return;
    }

    if (survey.alreadyResponded && !survey.allowMultipleResponses) {
        alert('You already submitted this survey. Multiple responses are not allowed.');
        return;
    }

    if (!survey.isAcceptingResponses) {
        alert('This survey is not accepting responses right now.');
        return;
    }

    const categories = getSurveyCategoryGroups(survey);
    const currentCategory = categories[surveyRespondSession.currentCatIndex];
    if (!survey.alreadyResponded && survey.isAcceptingResponses) {
        const validation = validateCategoryAnswers(surveyId, currentCategory);
        if (!validation.valid) {
            return;
        }
    }
    saveCurrentCategoryAnswers(surveyId, currentCategory);
    pruneDivergentAnswers(categories);

    // Collect active visited questions
    const visitedCatIndices = new Set(surveyRespondSession.history);
    const visitedQuestions = new Set();
    categories.forEach((cat, idx) => {
        if (visitedCatIndices.has(idx)) {
            (cat.questions || []).forEach(q => {
                visitedQuestions.add(Number(q.id));
                visitedQuestions.add(String(q.id));
            });
        }
    });

    const answers = [];
    const formData = new FormData();

    // Iterate through ALL questions in the entire survey so skipped questions are explicitly included with null values
    categories.forEach((cat) => {
        (cat.questions || []).forEach((question) => {
            const qId = Number(question.id || 0) || question.id;
            const isVisited = visitedQuestions.has(Number(qId)) || visitedQuestions.has(String(qId));
            const ans = isVisited ? surveyRespondSession.answers[qId] : null;

            if (ans && ans.file) {
                formData.append(`answer_image_${qId}`, ans.file);
                answers.push({
                    question_id: qId,
                    choice_id: null,
                    answer_text: '',
                });
            } else if (ans && ans.choice_id !== undefined && ans.choice_id !== null) {
                answers.push({
                    question_id: qId,
                    choice_id: ans.choice_id,
                    answer_text: null,
                });
            } else if (ans && ans.answer_text !== undefined && ans.answer_text !== '') {
                answers.push({
                    question_id: qId,
                    choice_id: null,
                    answer_text: ans.answer_text,
                });
            } else {
                // Stored as null for skipped questions
                answers.push({
                    question_id: qId,
                    choice_id: null,
                    answer_text: null,
                });
            }
        });
    });

    const hasAnyValidAnswer = answers.some(a => a.choice_id !== null || (a.answer_text !== null && a.answer_text !== '') || formData.has(`answer_image_${a.question_id}`));
    if (!hasAnyValidAnswer) {
        alert('Please answer at least one question before submitting.');
        return;
    }

    formData.append('answers', JSON.stringify(answers));

    try {
        const response = await fetch(getSurveySubmitUrl(surveyId), {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            alert(result.message || 'Unable to submit your answers right now.');
            return;
        }

        alert('Your survey response has been submitted. Thank you!');

        survey.alreadyResponded = true;
        survey.responseCount = Number(survey.responseCount || 0) + 1;
        const remainingPending = getPendingUnansweredSurveys(currentSurveys);
        updateSurveyMenuNotification(remainingPending.length > 0, remainingPending.length);

        const updatedSurvey = survey;

        const detailMount = document.getElementById('alumniSurveyDetailMount');
        if (detailMount) {
            renderSurveyDetailForm('alumniSurveyDetailMount', updatedSurvey);
            setTimeout(async () => {
                try {
                    currentSurveys = await loadSurveyState();
                } catch (err) {
                    console.error('Failed to reload survey state:', err);
                }
                window.location.href = 'Surveys.html';
            }, 1500);
            return;
        }

        renderSurveyCards('alumniSurveysGrid', currentSurveys);
        const mainSurveyPreview = document.getElementById('mainSurveyPreview');
        if (mainSurveyPreview) {
            renderSurveyCards('mainSurveyPreview', currentSurveys, 2);
        }
    } catch (error) {
        alert('Unable to submit your answers right now.');
    }
}

let currentSurveys = [];

function updateMainStats(stats, events, news, surveys) {
    const totalEvents = document.getElementById('statTotalEvents');
    const statUpcoming = document.getElementById('statUpcomingEvents');
    const statNews = document.getElementById('statNewsCount');
    const statSurveys = document.getElementById('statSurveyCount');

    if (totalEvents) totalEvents.textContent = String(stats.total ?? events.length ?? 0);
    if (statUpcoming) statUpcoming.textContent = String(stats.upcoming ?? 0);
    if (statNews) statNews.textContent = String(news.length);
    if (statSurveys) statSurveys.textContent = String(surveys.length);
}

function updateHeroHighlight(events, news) {
    const nextEventTitle = document.getElementById('nextEventTitle');
    const nextEventMeta = document.getElementById('nextEventMeta');
    const latestUpdateTitle = document.getElementById('latestUpdateTitle');
    const latestUpdateMeta = document.getElementById('latestUpdateMeta');

    const firstEvent = events[0];
    if (firstEvent && nextEventTitle && nextEventMeta) {
        const iso = firstEvent.datetime || (firstEvent.date && firstEvent.time ? `${firstEvent.date}T${firstEvent.time}` : firstEvent.date);
        nextEventTitle.textContent = firstEvent.name || 'Upcoming event';
        nextEventMeta.textContent = `${formatRelativeLabel(iso)} • ${firstEvent.location || 'Location to follow'}`;
    }

    const firstNews = news[0];
    if (firstNews && latestUpdateTitle && latestUpdateMeta) {
        latestUpdateTitle.textContent = firstNews.title || 'Latest staff update';
        latestUpdateMeta.textContent = `${firstNews.category || 'general'} • ${formatDateTime(firstNews.created_at || '')}`;
    }
}

async function bootstrapAlumniUi() {
    const user = await requireAlumni();
    if (!user) return;

    currentAlumniUser = user;
    injectAlumniUser(user);

    const [events, stats, news, surveys] = await Promise.all([
        loadEventsData().catch(() => []),
        loadEventStatistics().catch(() => ({ total: 0, upcoming: 0, past: 0 })),
        loadNewsData().catch(() => []),
        loadSurveyState().catch(() => []),
    ]);

    currentSurveys = surveys;

    // Check for open unanswered surveys: notification persists until answered or closed
    const pendingSurveys = getPendingUnansweredSurveys(surveys);
    updateSurveyMenuNotification(pendingSurveys.length > 0, pendingSurveys.length);

    updateMainStats(stats, events, news, surveys);
    updateHeroHighlight(events, news);
    renderEventCards('mainEventsPreview', events.filter((event) => {
        const iso = event.datetime || (event.date && event.time ? `${event.date}T${event.time}` : event.date);
        const date = new Date(iso);
        return !Number.isNaN(date.getTime()) && date.getTime() >= Date.now();
    }), 3);
    renderNewsCards('mainNewsPreview', news, 3);
    renderSurveyCards('mainSurveyPreview', surveys, 2);

    renderEventCards('alumniEventsGrid', events);
    if (document.getElementById('alumniNewsGrid')) {
        initNewsFeed(news);
        initFeedWidgets(user, events, surveys, news);
    } else {
        renderNewsCards('alumniNewsGrid', news);
    }
    renderSurveyCards('alumniSurveysGrid', surveys);
}

async function bootstrapSurveyRespondPage() {
    const user = await requireAlumni();
    if (!user) return;

    currentAlumniUser = user;
    injectAlumniUser(user);

    const params = new URLSearchParams(window.location.search);
    const rawId = params.get('id');
    const surveyId = rawId !== null && rawId !== '' ? Number(rawId) : NaN;

    const mount = document.getElementById('alumniSurveyDetailMount');
    const pageTitle = document.getElementById('surveyRespondPageTitle');
    const pageSub = document.getElementById('surveyRespondPageSubtitle');

    let surveys = [];
    try {
        surveys = await loadSurveyState();
    } catch (error) {
        surveys = [];
    }
    currentSurveys = surveys;

    const pendingSurveys = getPendingUnansweredSurveys(surveys);
    updateSurveyMenuNotification(pendingSurveys.length > 0, pendingSurveys.length);

    if (!Number.isFinite(surveyId) || surveyId <= 0) {
        if (mount) {
            mount.innerHTML = createEmptyState('fa-circle-exclamation', 'Invalid link', 'Open a survey from the Surveys page using Respond.');
        }
        if (pageTitle) pageTitle.textContent = 'Survey';
        if (pageSub) pageSub.textContent = 'Missing or invalid survey id.';
        return;
    }

    const survey = surveys.find((item) => Number(item.id) === surveyId);
    if (!survey) {
        if (mount) {
            mount.innerHTML = createEmptyState('fa-clipboard-question', 'Survey not found', 'It may have been removed. Return to the survey list and choose another.');
        }
        if (pageTitle) pageTitle.textContent = 'Survey not found';
        if (pageSub) pageSub.textContent = 'This survey is no longer available.';
        return;
    }

    renderSurveyDetailForm('alumniSurveyDetailMount', survey);
}

/* Fit [data-fit-viewport] panels to the remaining screen height (max-height only) */
function fitPanelsToViewport() {
    document.querySelectorAll('[data-fit-viewport]').forEach((panel) => {
        panel.style.maxHeight = '';
        if (getComputedStyle(panel).overflowY === 'visible') return;

        const container = panel.closest('.alumni-content');
        const bottomPad = container ? parseFloat(getComputedStyle(container).paddingBottom) || 0 : 0;
        const top = panel.getBoundingClientRect().top + window.scrollY;
        const available = window.innerHeight - top - bottomPad;

        panel.style.maxHeight = `${Math.max(240, Math.floor(available))}px`;
    });
}

window.addEventListener('resize', fitPanelsToViewport);
window.addEventListener('load', fitPanelsToViewport);

document.addEventListener('DOMContentLoaded', () => {
    fitPanelsToViewport();
    if (document.getElementById('alumniSurveyDetailMount')) {
        void bootstrapSurveyRespondPage();
        return;
    }
    void bootstrapAlumniUi();
});

// Periodic real-time background check every 2 seconds and on tab refocus/visibility change
setInterval(pollSurveyNotifications, 2000);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        void pollSurveyNotifications();
    }
});
