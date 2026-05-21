// Modern messaging UI
let _activeUserId = null;
let _activePartnerName = null;
let _activeCourseTitles = null;
let _messagesPollTimer = null;
let _conversationsCache = [];
let _searchQuery = '';

const AVATAR_COLORS = ['#4F46E5', '#6366F1', '#818CF8', '#7C3AED', '#2563EB', '#0891B2', '#059669'];

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function getInitials(name) {
    const n = (name || 'U').trim();
    const parts = n.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return n.substring(0, 2).toUpperCase();
}

function avatarColorForUserId(userId) {
    return AVATAR_COLORS[Math.abs(Number(userId) || 0) % AVATAR_COLORS.length];
}

function avatarHtml(userId, name, sizeClass = 'chat-avatar--sm') {
    const initials = getInitials(name);
    const color = avatarColorForUserId(userId);
    return `<span class="chat-avatar ${sizeClass}" style="background:${color}" aria-hidden="true">${escapeHtml(initials)}</span>`;
}

function formatMessageTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) {
        return 'Yesterday';
    }
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function formatSidebarTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function partnerName(conv) {
    return conv.other_full_name || conv.other_username || 'User';
}

function openMessagesWithUser(userId, name) {
    sessionStorage.setItem('messages_target', JSON.stringify({
        userId: Number(userId),
        name: name || 'User'
    }));
    showPage('messages');
}

function openMessagesWithEnrollment(_enrollmentId, userId, name) {
    openMessagesWithUser(userId, name);
}

function getFilteredConversations() {
    const q = _searchQuery.trim().toLowerCase();
    if (!q) return _conversationsCache;
    return _conversationsCache.filter(c => {
        const name = partnerName(c).toLowerCase();
        const course = (c.course_titles || '').toLowerCase();
        const preview = (c.last_message || '').toLowerCase();
        return name.includes(q) || course.includes(q) || preview.includes(q);
    });
}

function renderConversationCard(c) {
    const unread = parseInt(c.unread_count, 10) || 0;
    const active = _activeUserId === Number(c.other_user_id);
    const hasUnread = unread > 0;
    const name = partnerName(c);
    const nameSafe = escapeHtml(name);
    const course = c.course_titles ? escapeHtml(c.course_titles) : '';
    const hasPreview = Boolean(c.last_message);
    const preview = hasPreview
        ? escapeHtml(String(c.last_message).substring(0, 72))
        : '';
    const time = c.message_time ? formatSidebarTime(c.message_time) : '';

    return `
        <button type="button"
            class="conversation-card${active ? ' active' : ''}${hasUnread ? ' has-unread' : ''}"
            data-user-id="${c.other_user_id}"
            data-user-name="${nameSafe}"
            data-course-titles="${course}">
            ${avatarHtml(c.other_user_id, name)}
            <span class="conversation-card-body">
                <span class="conversation-card-top">
                    <span class="conversation-card-name">${nameSafe}</span>
                    ${time ? `<span class="conversation-card-time">${time}</span>` : ''}
                </span>
                ${course ? `<span class="conversation-card-course">${course}</span>` : ''}
                <span class="conversation-card-preview${hasPreview ? '' : ' is-empty'}">${hasPreview ? preview : 'No messages yet'}</span>
            </span>
            ${hasUnread ? `<span class="conversation-unread">${unread > 99 ? '99+' : unread}</span>` : ''}
        </button>`;
}

function bindConversationCards(container) {
    container.querySelectorAll('.conversation-card').forEach(btn => {
        btn.addEventListener('click', () => {
            selectConversation(
                Number(btn.dataset.userId),
                btn.dataset.userName,
                btn.dataset.courseTitles || ''
            );
        });
    });
}

function paintConversationsList() {
    const listEl = document.getElementById('conversations-list');
    if (!listEl) return;

    const filtered = getFilteredConversations();

    if (_conversationsCache.length === 0) {
        listEl.innerHTML = '<p class="messages-state-text">No contacts yet. Enroll in a course to start messaging.</p>';
        return;
    }

    if (filtered.length === 0) {
        listEl.innerHTML = '<p class="messages-state-text">No conversations match your search.</p>';
        return;
    }

    listEl.innerHTML = filtered.map(renderConversationCard).join('');
    bindConversationCards(listEl);
}

async function loadMessagesPage() {
    stopMessagesPolling();
    showEmptyThread();

    const targetRaw = sessionStorage.getItem('messages_target');
    sessionStorage.removeItem('messages_target');
    let target = null;
    if (targetRaw) {
        try { target = JSON.parse(targetRaw); } catch (_) { /* ignore */ }
    }

    await loadConversationsList();

    if (target?.userId) {
        const item = _conversationsCache.find(c => Number(c.other_user_id) === target.userId);
        if (item) {
            await selectConversation(target.userId, partnerName(item), item.course_titles || '');
        } else {
            ensureConversationForUser(target.userId, target.name);
        }
    }
}

function ensureConversationForUser(userId, name) {
    const listEl = document.getElementById('conversations-list');
    if (!listEl) return;

    const existing = _conversationsCache.find(c => Number(c.other_user_id) === userId);
    if (!existing) {
        _conversationsCache.unshift({
            other_user_id: userId,
            other_full_name: name,
            other_username: name,
            course_titles: '',
            last_message: null,
            message_time: null,
            unread_count: 0
        });
    }
    paintConversationsList();
}

async function loadConversationsList() {
    const listEl = document.getElementById('conversations-list');
    if (!listEl) return;

    listEl.innerHTML = '<p class="messages-state-text">Loading conversations...</p>';

    const result = await apiInstance.getConversations();
    if (!result.success) {
        listEl.innerHTML = '<p class="messages-state-text is-error">Could not load conversations.</p>';
        _conversationsCache = [];
        return;
    }

    _conversationsCache = result.data.conversations || [];
    paintConversationsList();
    refreshUnreadBadge();
}

function showEmptyThread() {
    _activeUserId = null;
    _activePartnerName = null;
    _activeCourseTitles = null;

    const app = document.getElementById('messages-app');
    const panel = document.getElementById('messages-panel');
    app?.classList.remove('is-chat-open');
    panel?.classList.remove('chat-active');

    document.getElementById('thread-header')?.classList.add('hidden');
    document.getElementById('messages-thread')?.classList.add('hidden');

    const compose = document.getElementById('message-compose');
    compose?.classList.add('hidden');
    compose?.setAttribute('hidden', '');

    const input = document.getElementById('message-input');
    if (input) {
        input.value = '';
        input.disabled = true;
    }
    document.getElementById('message-send-btn')?.setAttribute('disabled', '');

    const placeholder = document.getElementById('thread-placeholder');
    if (placeholder) placeholder.style.display = 'flex';

    paintConversationsList();
}

function updateChatHeader(userId, name, courseTitles) {
    const titleEl = document.getElementById('thread-partner-name');
    if (titleEl) titleEl.textContent = name || 'User';

    const courseEl = document.getElementById('thread-course-title');
    if (courseEl) courseEl.textContent = courseTitles || '';

    const avatarEl = document.getElementById('chat-header-avatar');
    if (avatarEl) {
        avatarEl.textContent = getInitials(name);
        avatarEl.style.background = avatarColorForUserId(userId);
        avatarEl.className = 'chat-avatar chat-avatar--md';
    }
}

async function selectConversation(userId, name, courseTitles) {
    _activeUserId = userId;
    _activePartnerName = name || 'User';
    _activeCourseTitles = courseTitles || '';

    document.getElementById('messages-app')?.classList.add('is-chat-open');
    document.getElementById('messages-panel')?.classList.add('chat-active');

    const placeholder = document.getElementById('thread-placeholder');
    if (placeholder) placeholder.style.display = 'none';

    document.getElementById('thread-header')?.classList.remove('hidden');
    document.getElementById('messages-thread')?.classList.remove('hidden');

    const compose = document.getElementById('message-compose');
    compose?.classList.remove('hidden');
    compose?.removeAttribute('hidden');

    updateChatHeader(userId, _activePartnerName, _activeCourseTitles);
    paintConversationsList();

    const input = document.getElementById('message-input');
    const sendBtn = document.getElementById('message-send-btn');
    if (input) {
        input.disabled = false;
        input.focus();
    }
    sendBtn?.removeAttribute('disabled');

    await loadThread(userId);
    startMessagesPolling();
}

async function loadThread(userId, silent = false) {
    const threadEl = document.getElementById('messages-thread');
    if (!threadEl) return;

    if (!silent) {
        threadEl.innerHTML = '<p class="messages-state-text">Loading messages...</p>';
    }

    const result = await apiInstance.getConversation(userId);
    if (!result.success) {
        threadEl.innerHTML = '<p class="messages-state-text is-error">' +
            escapeHtml(result.data?.error || 'Failed to load messages') + '</p>';
        return;
    }

    if (result.data.partner) {
        _activePartnerName = result.data.partner.full_name ||
            result.data.partner.username || _activePartnerName;
        _activeCourseTitles = result.data.partner.course_titles || _activeCourseTitles;
        updateChatHeader(userId, _activePartnerName, _activeCourseTitles);
    }

    const me = getUser();
    const myId = me?.id;
    const messages = result.data.messages || [];

    if (messages.length === 0) {
        threadEl.innerHTML = '<p class="messages-state-text">No messages yet. Say hello!</p>';
    } else {
        threadEl.innerHTML = messages.map(m => {
            const isMine = Number(m.sender_id) === Number(myId);
            const senderLabel = escapeHtml(m.sender_full_name || m.sender_username || 'User');
            return `
                <div class="message-row ${isMine ? 'mine' : 'theirs'}">
                    <div class="message-bubble ${isMine ? 'mine' : 'theirs'}">
                        ${!isMine ? `<span class="message-sender">${senderLabel}</span>` : ''}
                        <p class="message-text">${escapeHtml(m.message)}</p>
                        <span class="message-time">${formatMessageTime(m.created_at)}</span>
                    </div>
                </div>`;
        }).join('');
        threadEl.scrollTop = threadEl.scrollHeight;
    }

    await loadConversationsList();
}

async function sendCurrentMessage() {
    if (!_activeUserId) {
        return;
    }

    const input = document.getElementById('message-input');
    const text = (input?.value || '').trim();
    if (!text) return;

    const btn = document.getElementById('message-send-btn');
    if (btn) btn.disabled = true;

    const result = await apiInstance.sendMessage(_activeUserId, text);
    if (btn) btn.disabled = false;

    if (!result.success) {
        alert(result.data?.error || 'Failed to send message');
        return;
    }

    if (input) input.value = '';
    await loadThread(_activeUserId);
}

function startMessagesPolling() {
    stopMessagesPolling();
    _messagesPollTimer = setInterval(() => {
        const messagesPage = document.getElementById('page-messages');
        if (!messagesPage?.classList.contains('active')) {
            stopMessagesPolling();
            return;
        }
        if (_activeUserId) {
            loadThread(_activeUserId, true);
        } else {
            loadConversationsList();
        }
        refreshUnreadBadge();
    }, 5000);
}

function stopMessagesPolling() {
    if (_messagesPollTimer) {
        clearInterval(_messagesPollTimer);
        _messagesPollTimer = null;
    }
}

async function refreshUnreadBadge() {
    const badge = document.getElementById('messages-unread-badge');
    if (!badge || !getUser()) return;

    const result = await apiInstance.getUnreadMessageCount();
    const count = result.success ? (result.data.unread_count || 0) : 0;
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'inline-flex';
    } else {
        badge.style.display = 'none';
    }
}

function initMessagesUI() {
    document.getElementById('message-send-btn')?.addEventListener('click', sendCurrentMessage);
    document.getElementById('message-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendCurrentMessage();
        }
    });

    document.getElementById('chat-back-btn')?.addEventListener('click', showEmptyThread);

    document.getElementById('conversation-search')?.addEventListener('input', (e) => {
        _searchQuery = e.target.value;
        paintConversationsList();
    });

    const input = document.getElementById('message-input');
    if (input) {
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
        });
    }
}

document.addEventListener('DOMContentLoaded', initMessagesUI);
