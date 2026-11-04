class EventManager {
    constructor() {
        this.apiBaseUrl = window.EVENTS_API_BASE || '/events';
        this.currentFilter = 'all';
        this.searchKeyword = '';
        this.eventsById = new Map();
        this.currentEditingId = null;
    }

    async parseApiResponse(response) {
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            return response.json();
        }

        const rawText = await response.text();
        return {
            success: false,
            message: `Unexpected response from server (${response.status}).`,
            raw: rawText,
        };
    }

    async loadEvents() {
        const listEl = document.getElementById('eventsList');
        if (!listEl) return;

        listEl.innerHTML = this.createStateCard(
            'fa-spinner fa-spin',
            'Loading events',
            'Please wait while we prepare the latest event schedule.',
            'loading'
        );

        try {
            const params = new URLSearchParams();
            if (this.currentFilter && this.currentFilter !== 'all') {
                params.append('filter', this.currentFilter);
            }
            if (this.searchKeyword) {
                params.append('search', this.searchKeyword);
            }

            const query = params.toString();
            const response = await fetch(`${this.apiBaseUrl}/list${query ? `?${query}` : ''}`);
            const result = await this.parseApiResponse(response);

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to fetch events.');
            }

            const events = Array.isArray(result.data) ? result.data : [];
            this.eventsById = new Map(events.map((event) => [String(event.id), event]));
            if (events.length === 0) {
                listEl.innerHTML = this.createStateCard(
                    'fa-calendar-xmark',
                    'No events found',
                    'Try a different filter or search term, or create a new event to get started.'
                );
                return;
            }

            listEl.innerHTML = `
                <div class="events-display-grid">
                    ${events.map((event) => this.createEventCard(event)).join('')}
                </div>
            `;
        } catch (error) {
            console.error('Error loading events:', error);
            listEl.innerHTML = this.createStateCard(
                'fa-circle-exclamation',
                'Failed to load events',
                'Please try again in a moment.',
                'error'
            );
        }
    }

    createEventCard(eventData) {
        const combinedIso = eventData.datetime || (eventData.date && eventData.time ? `${eventData.date}T${eventData.time}` : eventData.date);
        const eventDate = new Date(combinedIso);
        const isValidDate = !Number.isNaN(eventDate.getTime());
        const formattedDate = Number.isNaN(eventDate.getTime())
            ? this.escapeHtml(combinedIso ?? '')
            : eventDate.toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });

        const statusText = eventData.status || 'upcoming';
        const statusClass = this.getStatusClass(statusText);
        const dateBadge = isValidDate
            ? eventDate.toLocaleDateString('en-US', {
                month: 'short',
                day: '2-digit',
            }).replace(' ', '\u00A0')
            : 'TBD';
        const timeLabel = isValidDate
            ? eventDate.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
            })
            : 'Time unavailable';
        const relativeLabel = isValidDate ? this.getRelativeDateLabel(eventDate) : 'Schedule to be announced';
        const accentClass = this.getTemporalAccentClass(eventDate, statusText);

        return `
            <article class="event-card ${accentClass}" data-event-id="${eventData.id}">
                <div class="event-card-banner">
                    <div class="event-date-badge">
                        <span class="event-date-month">${this.escapeHtml(dateBadge.split('\u00A0')[0] || 'TBD')}</span>
                        <strong class="event-date-day">${this.escapeHtml(dateBadge.split('\u00A0')[1] || '')}</strong>
                    </div>
                    <span class="event-status ${statusClass}">${this.escapeHtml(statusText)}</span>
                </div>
                <div class="event-header">
                    <h4 class="event-title">${this.escapeHtml(eventData.name || '')}</h4>
                    <p class="event-subtitle">${this.escapeHtml(relativeLabel)}</p>
                </div>
                <div class="event-details">
                    <div class="event-detail">
                        <i class="fas fa-calendar-day"></i>
                        <span>${formattedDate}</span>
                    </div>
                    <div class="event-detail">
                        <i class="fas fa-clock"></i>
                        <span>${this.escapeHtml(timeLabel)}</span>
                    </div>
                    <div class="event-detail">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${this.escapeHtml(eventData.location || '')}</span>
                    </div>
                </div>
                <div class="event-meta">
                    <span class="event-chip">
                        <i class="fas fa-location-dot"></i>
                        Venue Ready
                    </span>
                    <span class="event-chip">
                        <i class="fas fa-bell"></i>
                        ${this.escapeHtml(relativeLabel)}
                    </span>
                </div>
                <div class="event-actions">
                    <button class="btn-icon edit-btn" onclick="eventManager.openEditEventModal(${eventData.id})" title="Edit Event">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="btn-icon delete-btn" onclick="eventManager.deleteEvent(${eventData.id})" title="Delete Event">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </article>
        `;
    }

    createStateCard(iconClass, title, message, modifier = '') {
        const modifierClass = modifier ? ` event-state-${modifier}` : '';
        return `
            <div class="event-state-card${modifierClass}">
                <i class="fas ${iconClass}"></i>
                <h4>${this.escapeHtml(title)}</h4>
                <p>${this.escapeHtml(message)}</p>
            </div>
        `;
    }

    async createEvent(eventData) {
        try {
            const formData = new FormData();
            formData.append('name', eventData.name);
            formData.append('date', eventData.date);
            formData.append('time', eventData.time);
            formData.append('location', eventData.location);
            formData.append('status', eventData.status || 'upcoming');

            const response = await fetch(`${this.apiBaseUrl}/create`, {
                method: 'POST',
                body: formData,
            });

            const result = await this.parseApiResponse(response);
            if (!response.ok || !result.success) {
                alert(result.message || 'Failed to create event.');
                return;
            }

            closeCreateEventModal();
            await this.loadEvents();
        } catch (error) {
            console.error('Error creating event:', error);
            alert('Failed to create event. Please try again.');
        }
    }

    async updateEvent(id, eventData) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/update/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(eventData),
            });

            const result = await this.parseApiResponse(response);
            if (!response.ok || !result.success) {
                alert(result.message || 'Failed to update event.');
                return;
            }

            this.resetEventFormState();
            closeCreateEventModal();
            await this.loadEvents();
        } catch (error) {
            console.error('Error updating event:', error);
            alert('Failed to update event. Please try again.');
        }
    }

    async deleteEvent(id) {
        if (!confirm('Are you sure you want to delete this event?')) {
            return;
        }

        try {
            const response = await fetch(`${this.apiBaseUrl}/delete/${id}`, { method: 'DELETE' });
            const result = await this.parseApiResponse(response);
            if (!response.ok || !result.success) {
                alert(result.message || 'Failed to delete event.');
                return;
            }

            await this.loadEvents();
        } catch (error) {
            console.error('Error deleting event:', error);
            alert('Failed to delete event. Please try again.');
        }
    }

    openEditEventModal(id) {
        const eventData = this.eventsById.get(String(id));
        if (!eventData) {
            alert('Unable to load the selected event.');
            return;
        }

        this.currentEditingId = id;
        this.setEventFormValues(eventData);
        this.updateEventModalUi('Edit Event', 'Save Changes');
        openCreateEventModal();
    }

    setEventFormValues(eventData) {
        const nameInput = document.getElementById('eventName');
        const dateInput = document.getElementById('eventDate');
        const timeInput = document.getElementById('eventTime');
        const locationInput = document.getElementById('eventLocation');

        if (nameInput) nameInput.value = eventData.name || '';
        if (dateInput) dateInput.value = eventData.date || '';
        if (timeInput) timeInput.value = eventData.time || '';
        if (locationInput) locationInput.value = eventData.location || '';
    }

    clearEventForm() {
        this.setEventFormValues({
            name: '',
            date: '',
            time: '',
            location: '',
        });
    }

    updateEventModalUi(title, submitLabel) {
        const titleEl = document.getElementById('eventModalTitle');
        const submitBtn = document.getElementById('eventModalSubmitBtn');

        if (titleEl) titleEl.textContent = title;
        if (submitBtn) submitBtn.textContent = submitLabel;
    }

    resetEventFormState() {
        this.currentEditingId = null;
        this.clearEventForm();
        this.updateEventModalUi('Create New Event', 'Create Event');
    }

    getStatusClass(status) {
        const normalized = String(status || '').toLowerCase();
        if (normalized === 'active') return 'status-active';
        if (normalized === 'past') return 'status-past';
        return 'status-upcoming';
    }

    getTemporalAccentClass(eventDate, status) {
        if (Number.isNaN(eventDate.getTime())) return 'event-neutral';

        const normalized = String(status || '').toLowerCase();
        if (normalized === 'past') return 'event-past';

        const hoursUntilEvent = (eventDate.getTime() - Date.now()) / (1000 * 60 * 60);
        if (hoursUntilEvent <= 48 && hoursUntilEvent >= 0) {
            return 'event-soon';
        }

        return 'event-upcoming';
    }

    getRelativeDateLabel(eventDate) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const compareDate = new Date(eventDate);
        compareDate.setHours(0, 0, 0, 0);

        const diffDays = Math.round((compareDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

        if (diffDays === 0) return 'Happening today';
        if (diffDays === 1) return 'Coming tomorrow';
        if (diffDays > 1) return `In ${diffDays} days`;
        if (diffDays === -1) return 'Held yesterday';
        return `${Math.abs(diffDays)} days ago`;
    }

    escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
}

let eventManager;

window.openCreateEventModal = function openCreateEventModal() {
    const modal = document.getElementById('createEventModal');
    if (window.eventManager && window.eventManager.currentEditingId === null) {
        window.eventManager.clearEventForm();
        window.eventManager.updateEventModalUi('Create New Event', 'Create Event');
    }
    if (modal) modal.style.display = 'flex';
};

window.closeCreateEventModal = function closeCreateEventModal() {
    const modal = document.getElementById('createEventModal');
    if (modal) modal.style.display = 'none';
    if (window.eventManager) {
        window.eventManager.resetEventFormState();
    }
};

window.submitEventForm = function submitEventForm() {
    const eventData = {
        name: (document.getElementById('eventName')?.value || '').trim(),
        date: (document.getElementById('eventDate')?.value || '').trim(),
        time: (document.getElementById('eventTime')?.value || '').trim(),
        location: (document.getElementById('eventLocation')?.value || '').trim(),
        status: 'Active',
    };

    if (!eventData.name || !eventData.date || !eventData.time || !eventData.location) {
        alert('Please fill in all required fields.');
        return;
    }

    if (eventManager.currentEditingId !== null) {
        eventManager.updateEvent(eventManager.currentEditingId, eventData);
        return;
    }

    eventManager.createEvent(eventData);
};

document.addEventListener('DOMContentLoaded', () => {
    eventManager = new EventManager();
    window.eventManager = eventManager;

    const searchInput = document.getElementById('searchEvents');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            eventManager.searchKeyword = searchInput.value.trim();
            eventManager.loadEvents();
        });
    }

    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            filterButtons.forEach((btn) => btn.classList.remove('active'));
            button.classList.add('active');
            eventManager.currentFilter = button.dataset.filter || 'all';
            eventManager.loadEvents();
        });
    });

    window.addEventListener('click', (evt) => {
        const modal = document.getElementById('createEventModal');
        if (evt.target === modal) {
            closeCreateEventModal();
        }
    });

    eventManager.loadEvents();
});
