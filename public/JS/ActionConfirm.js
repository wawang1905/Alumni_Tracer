/**
 * GradVision — System-Wide Three-Level Action Confirmation Component
 *
 * Levels:
 *   - basic:  Low-risk actions (save profile, add ordinary record, save settings)
 *   - medium: Moderate-consequence actions (status/role/assignment change, publish/close, review)
 *   - high:   Destructive or irreversible actions (permanent delete, restore, clear) + typed phrase
 *
 * Usage:
 *   const ok = await window.ActionConfirm.basic({ title, message, confirmText });
 *   const ok = await window.ActionConfirm.medium({ title, message, record, consequence, confirmText });
 *   const ok = await window.ActionConfirm.high({ title, message, record, consequence, requiredPhrase: 'DELETE', confirmText });
 */
(function (global) {
    'use strict';

    if (global.ActionConfirm) {
        return;
    }

    const LEVEL_DEFAULTS = {
        basic: {
            badge: 'Confirm Action',
            icon: 'fa-circle-check',
            confirmText: 'Confirm',
            btnClass: 'ac-btn-confirm-basic'
        },
        medium: {
            badge: 'Important Action',
            icon: 'fa-triangle-exclamation',
            confirmText: 'Yes, Proceed',
            btnClass: 'ac-btn-confirm-medium'
        },
        high: {
            badge: 'High-Risk Action',
            icon: 'fa-skull-crossbones',
            confirmText: 'Delete Permanently',
            btnClass: 'ac-btn-confirm-high',
            requiredPhrase: 'DELETE'
        }
    };

    let activeBackdrop = null;
    let isSubmitting = false;
    let isModalOpen = false;

    function normalizeLevel(level, options) {
        const raw = String(level || '').toLowerCase().trim();
        if (raw === 'high' || raw === 'danger') return 'high';
        if (raw === 'medium' || raw === 'warning') return 'medium';
        if (raw === 'basic' || raw === 'low') return 'basic';
        if (options && options.danger) return 'high';
        return 'basic';
    }

    function escapeText(value) {
        return String(value ?? '');
    }

    function ensureBackdrop() {
        if (activeBackdrop && document.body.contains(activeBackdrop)) {
            return activeBackdrop;
        }

        const backdrop = document.createElement('div');
        backdrop.id = 'gvActionConfirmBackdrop';
        backdrop.className = 'ac-modal-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');
        backdrop.innerHTML = `
            <div class="ac-modal-dialog ac-level-basic" role="dialog" aria-modal="true" aria-labelledby="gvAcTitle">
                <div class="ac-modal-header">
                    <div class="ac-modal-icon" aria-hidden="true"><i id="gvAcIcon" class="fas fa-circle-check"></i></div>
                    <div class="ac-modal-heading-group">
                        <span class="ac-modal-badge" id="gvAcBadge">Confirm Action</span>
                        <h3 class="ac-modal-title" id="gvAcTitle">Confirm Action</h3>
                    </div>
                    <button type="button" class="ac-modal-close" id="gvAcCloseBtn" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="ac-modal-body">
                    <p class="ac-modal-message" id="gvAcMessage"></p>
                    <div class="ac-modal-record" id="gvAcRecordBox" hidden>
                        <strong id="gvAcRecordLabel">Affected Record</strong>
                        <span id="gvAcRecordValue"></span>
                    </div>
                    <div class="ac-modal-consequence" id="gvAcConsequenceBox" hidden>
                        <i class="fas fa-circle-info" aria-hidden="true"></i>
                        <span id="gvAcConsequenceText"></span>
                    </div>
                    <div class="ac-phrase-box" id="gvAcPhraseBox" hidden>
                        <label class="ac-phrase-label" for="gvAcPhraseInput">
                            Type <code id="gvAcPhraseCode">DELETE</code> to continue:
                        </label>
                        <input type="text" id="gvAcPhraseInput" class="ac-phrase-input" autocomplete="off" spellcheck="false">
                    </div>
                    <div class="ac-modal-error" id="gvAcErrorBox" hidden></div>
                </div>
                <div class="ac-modal-actions">
                    <button type="button" class="ac-btn ac-btn-cancel" id="gvAcCancelBtn">Cancel</button>
                    <button type="button" class="ac-btn ac-btn-confirm-basic" id="gvAcConfirmBtn">Confirm</button>
                </div>
            </div>
        `;

        document.body.appendChild(backdrop);
        activeBackdrop = backdrop;
        return backdrop;
    }

    function confirmAction(options = {}) {
        // Prevent duplicate / overlapping confirmation dialogs
        if (isModalOpen) {
            return Promise.resolve(false);
        }

        return new Promise((resolve) => {
            isModalOpen = true;
            const level = normalizeLevel(options.level, options);
            const defaults = LEVEL_DEFAULTS[level];
            const requiredPhrase = level === 'high'
                ? String(options.requiredPhrase !== undefined ? options.requiredPhrase : defaults.requiredPhrase)
                : '';

            const backdrop = ensureBackdrop();
            const dialog = backdrop.querySelector('.ac-modal-dialog');
            const iconEl = backdrop.querySelector('#gvAcIcon');
            const badgeEl = backdrop.querySelector('#gvAcBadge');
            const titleEl = backdrop.querySelector('#gvAcTitle');
            const messageEl = backdrop.querySelector('#gvAcMessage');
            const recordBox = backdrop.querySelector('#gvAcRecordBox');
            const recordLabel = backdrop.querySelector('#gvAcRecordLabel');
            const recordValue = backdrop.querySelector('#gvAcRecordValue');
            const consequenceBox = backdrop.querySelector('#gvAcConsequenceBox');
            const consequenceText = backdrop.querySelector('#gvAcConsequenceText');
            const phraseBox = backdrop.querySelector('#gvAcPhraseBox');
            const phraseCode = backdrop.querySelector('#gvAcPhraseCode');
            const phraseInput = backdrop.querySelector('#gvAcPhraseInput');
            const errorBox = backdrop.querySelector('#gvAcErrorBox');
            const closeBtn = backdrop.querySelector('#gvAcCloseBtn');
            const cancelBtn = backdrop.querySelector('#gvAcCancelBtn');
            const confirmBtn = backdrop.querySelector('#gvAcConfirmBtn');

            const setVisible = (el, visible, displayValue = '') => {
                if (!el) return;
                el.hidden = !visible;
                el.style.display = visible ? displayValue : 'none';
            };

            dialog.className = `ac-modal-dialog ac-level-${level}`;
            iconEl.className = `fas ${options.icon || defaults.icon}`;
            badgeEl.textContent = options.badge || defaults.badge;
            titleEl.textContent = escapeText(options.title || 'Confirm Action');
            messageEl.textContent = escapeText(options.message || 'Are you sure you want to proceed with this action?');

            if (options.record) {
                recordLabel.textContent = escapeText(options.recordLabel || 'Affected Record');
                recordValue.textContent = escapeText(options.record);
                setVisible(recordBox, true);
            } else {
                setVisible(recordBox, false);
            }

            if (options.consequence) {
                consequenceText.textContent = escapeText(options.consequence);
                setVisible(consequenceBox, true);
            } else {
                setVisible(consequenceBox, false);
            }

            setVisible(errorBox, false);
            errorBox.textContent = '';

            const confirmLabel = escapeText(options.confirmText || defaults.confirmText);
            cancelBtn.textContent = escapeText(options.cancelText || 'Cancel');
            confirmBtn.className = `ac-btn ${defaults.btnClass}`;
            confirmBtn.textContent = confirmLabel;

            isSubmitting = false;
            cancelBtn.disabled = false;
            closeBtn.disabled = false;

            const isPhraseStrictlyValid = () => {
                if (requiredPhrase === '') return true;
                // Strict, case-sensitive exact match — rejects empty, extra spaces, or wrong characters
                return phraseInput.value.length > 0 && phraseInput.value === requiredPhrase;
            };

            if (requiredPhrase !== '') {
                phraseCode.textContent = requiredPhrase;
                phraseInput.value = '';
                phraseInput.placeholder = `Type ${requiredPhrase}`;
                phraseInput.classList.remove('is-matched');
                setVisible(phraseBox, true);
                confirmBtn.disabled = true;
            } else {
                setVisible(phraseBox, false);
                phraseInput.value = '';
                phraseInput.classList.remove('is-matched');
                confirmBtn.disabled = false;
            }

            const onPhraseInput = () => {
                if (isSubmitting || requiredPhrase === '') return;
                const matched = isPhraseStrictlyValid();
                phraseInput.classList.toggle('is-matched', matched);
                confirmBtn.disabled = !matched;
                if (matched) {
                    setVisible(errorBox, false);
                    errorBox.textContent = '';
                }
            };

            const onPhraseKeydown = (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    if (isPhraseStrictlyValid()) {
                        onConfirmClick();
                    } else {
                        errorBox.textContent = `Please type "${requiredPhrase}" exactly (case-sensitive, no extra spaces) to continue.`;
                        setVisible(errorBox, true);
                    }
                }
            };

            const cleanup = (result) => {
                backdrop.classList.remove('is-open');
                backdrop.setAttribute('aria-hidden', 'true');
                phraseInput.removeEventListener('input', onPhraseInput);
                phraseInput.removeEventListener('keydown', onPhraseKeydown);
                closeBtn.removeEventListener('click', onCancel);
                cancelBtn.removeEventListener('click', onCancel);
                confirmBtn.removeEventListener('click', onConfirmClick);
                backdrop.removeEventListener('click', onBackdropClick);
                document.removeEventListener('keydown', onKeyDown);
                isSubmitting = false;
                isModalOpen = false;
                resolve(result);
            };

            const onCancel = () => {
                if (isSubmitting) return;
                cleanup(false);
            };

            const onBackdropClick = (event) => {
                if (event.target === backdrop && !isSubmitting) {
                    cleanup(false);
                }
            };

            const onKeyDown = (event) => {
                if (event.key === 'Escape' && !isSubmitting) {
                    event.preventDefault();
                    cleanup(false);
                }
            };

            const onConfirmClick = async () => {
                if (isSubmitting) return;
                if (!isPhraseStrictlyValid()) {
                    confirmBtn.disabled = true;
                    errorBox.textContent = `Please type "${requiredPhrase}" exactly (case-sensitive, no extra spaces) to continue.`;
                    setVisible(errorBox, true);
                    phraseInput.focus();
                    return;
                }

                if (typeof options.onConfirm !== 'function') {
                    isSubmitting = true;
                    confirmBtn.disabled = true;
                    cleanup(true);
                    return;
                }

                isSubmitting = true;
                confirmBtn.disabled = true;
                cancelBtn.disabled = true;
                closeBtn.disabled = true;
                setVisible(errorBox, false);
                confirmBtn.innerHTML = `<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> ${escapeText(options.loadingText || 'Processing...')}`;

                try {
                    const result = await options.onConfirm();
                    cleanup(result === undefined ? true : result);
                } catch (error) {
                    isSubmitting = false;
                    cancelBtn.disabled = false;
                    closeBtn.disabled = false;
                    confirmBtn.textContent = confirmLabel;
                    confirmBtn.disabled = !isPhraseStrictlyValid();
                    errorBox.textContent = (error && error.message) ? error.message : 'The action failed. Please try again.';
                    setVisible(errorBox, true);
                }
            };

            phraseInput.addEventListener('input', onPhraseInput);
            phraseInput.addEventListener('keydown', onPhraseKeydown);
            closeBtn.addEventListener('click', onCancel);
            cancelBtn.addEventListener('click', onCancel);
            confirmBtn.addEventListener('click', onConfirmClick);
            backdrop.addEventListener('click', onBackdropClick);
            document.addEventListener('keydown', onKeyDown);

            backdrop.classList.add('is-open');
            backdrop.setAttribute('aria-hidden', 'false');

            window.setTimeout(() => {
                if (requiredPhrase !== '') {
                    phraseInput.focus();
                } else {
                    confirmBtn.focus();
                }
            }, 40);
        });
    }

    global.ActionConfirm = {
        confirm: confirmAction,
        basic: (opts = {}) => confirmAction({ ...opts, level: 'basic' }),
        medium: (opts = {}) => confirmAction({ ...opts, level: 'medium' }),
        high: (opts = {}) => confirmAction({ ...opts, level: 'high' })
    };
})(window);
