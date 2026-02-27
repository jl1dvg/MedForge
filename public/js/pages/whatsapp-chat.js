(function () {
    'use strict';

    function formatDate(value) {
        if (!value) {
            return '';
        }

        try {
            var date = new Date(value);
            if (!isNaN(date.getTime())) {
                return date.toLocaleString();
            }
        } catch (error) {
            console.error('No se pudo formatear la fecha', error);
        }

        return value;
    }

    function createElement(tag, className, text) {
        var el = document.createElement(tag);
        if (className) {
            el.className = className;
        }
        if (typeof text === 'string') {
            el.textContent = text;
        }
        return el;
    }

    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(null, args);
            }, delay);
        };
    }

    function appendCacheBuster(url) {
        if (!url) {
            return url;
        }

        var separator = url.indexOf('?') === -1 ? '?' : '&';

        return url + separator + '_=' + Date.now();
    }

    function containsUrl(text) {
        if (!text) {
            return false;
        }

        return /(https?:\/\/|www\.)\S+/i.test(text);
    }

    function resetContainer(container, placeholder) {
        if (!container) {
            return;
        }

        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }

        if (placeholder) {
            placeholder.classList.add('d-none');
            container.appendChild(placeholder);
        }
    }

    function isNearBottom(container, threshold) {
        if (!container) {
            return true;
        }

        var limit = typeof threshold === 'number' ? threshold : 80;
        return (container.scrollHeight - container.scrollTop - container.clientHeight) <= limit;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('whatsapp-chat-root');
        if (!root) {
            return;
        }

        var state = {
            selectedId: null,
            conversations: [],
            agents: [],
            search: '',
            loadingConversation: false,
            sending: false,
            selectedHasInbound: true,
            forceScroll: false,
            lastInboundByConversation: {},
            selectedConversationSignature: '',
            conversationListSignature: '',
            unreadByConversation: {},
            unreadInitialized: false
        };

        var endpoints = {
            list: root.getAttribute('data-endpoint-list') || '',
            conversation: root.getAttribute('data-endpoint-conversation') || '',
            send: root.getAttribute('data-endpoint-send') || '',
            media: root.getAttribute('data-endpoint-media') || '',
            patients: root.getAttribute('data-endpoint-patients') || '',
            templates: root.getAttribute('data-endpoint-templates') || '',
            agents: root.getAttribute('data-endpoint-agents') || '',
            agentPresence: root.getAttribute('data-endpoint-agent-presence') || '',
            assign: root.getAttribute('data-endpoint-assign') || '',
            transfer: root.getAttribute('data-endpoint-transfer') || '',
            close: root.getAttribute('data-endpoint-close') || '',
            remove: root.getAttribute('data-endpoint-delete') || ''
        };

        var enabled = root.getAttribute('data-enabled') === '1';
        var currentUserId = parseInt(root.getAttribute('data-current-user-id'), 10) || 0;
        var currentRoleId = parseInt(root.getAttribute('data-current-role-id'), 10) || 0;
        var canAssign = root.getAttribute('data-can-assign') === '1';

        var listContainer = root.querySelector('[data-conversation-list]');
        var emptyListState = root.querySelector('[data-empty-state]');
        var messageContainer = root.querySelector('[data-chat-messages]');
        var emptyChatState = root.querySelector('[data-chat-empty]');
        var header = root.querySelector('[data-chat-header]');
        var subtitle = header ? header.querySelector('[data-chat-subtitle]') : null;
        var titleElement = root.querySelector('[data-chat-title]');
        var lastSeenElement = root.querySelector('[data-chat-last-seen]');
        var assignedCompact = root.querySelector('[data-chat-assigned-compact]');
        var teamCompact = root.querySelector('[data-chat-team]');
        var botStatusCompact = root.querySelector('[data-chat-bot-status]');
        var unreadIndicator = root.querySelector('[data-unread-indicator]');
        var composer = root.querySelector('[data-chat-composer]');
        var messageForm = root.querySelector('[data-message-form]');
        var messageInput = root.querySelector('#chatMessage');
        var errorAlert = root.querySelector('[data-chat-error]');
        var searchInput = root.querySelector('[data-conversation-search]');
        var newConversationForm = root.querySelector('[data-new-conversation-form]');
        var newConversationFeedback = root.querySelector('[data-new-conversation-feedback]');
        var patientSearchInput = root.querySelector('[data-patient-search]');
        var patientResults = root.querySelector('[data-patient-results]');
        var templateToggle = root.querySelector('[data-template-toggle]');
        var templatePanel = root.querySelector('[data-template-panel]');
        var templateSelect = root.querySelector('[data-template-select]');
        var templateFieldsContainer = root.querySelector('[data-template-fields]');
        var templatePreview = root.querySelector('[data-template-preview]');
        var detailName = root.querySelector('[data-detail-name]');
        var detailNumber = root.querySelector('[data-detail-number]');
        var detailPatient = root.querySelector('[data-detail-patient]');
        var detailHc = root.querySelector('[data-detail-hc]');
        var detailLast = root.querySelector('[data-detail-last]');
        var detailUnread = root.querySelector('[data-detail-unread]');
        var detailHandoff = root.querySelector('[data-detail-handoff]');
        var detailNotes = root.querySelector('[data-detail-notes]');
        var needsHumanBadge = root.querySelector('[data-chat-needs-human]');
        var assignedBadge = root.querySelector('[data-chat-assigned]');
        var firstContactBadge = root.querySelector('[data-chat-first-contact]');
        var copyNumberButton = root.querySelector('[data-action-copy-number]');
        var openChatLink = root.querySelector('[data-action-open-chat]');
        var closeConversationButton = root.querySelector('[data-action-close-conversation]');
        var deleteConversationButton = root.querySelector('[data-action-delete-conversation]');
        var headerAvatar = root.querySelector('[data-chat-avatar]');
        var headerAvatarImg = root.querySelector('[data-chat-avatar-img]');
        var headerAvatarInitials = root.querySelector('[data-chat-avatar-initials]');
        var attachmentTrigger = root.querySelector('[data-attachment-trigger]');
        var attachmentInput = root.querySelector('[data-attachment-input]');
        var attachmentPreview = root.querySelector('[data-attachment-preview]');
        var openTemplateButton = root.querySelector('[data-action-open-template]');
        var templateWarning = root.querySelector('[data-chat-template-warning]');
        var handoffPanel = root.querySelector('[data-handoff-panel]');
        var handoffBadge = root.querySelector('[data-handoff-badge]');
        var handoffQueue = root.querySelector('[data-handoff-queue]');
        var takeConversationButton = root.querySelector('[data-action="take-conversation"]');
        var transferSelect = root.querySelector('[data-transfer-agent]');
        var transferButton = root.querySelector('[data-action="transfer-conversation"]');
        var transferNoteInput = root.querySelector('[data-transfer-note]');
        var agentPresenceSelect = root.querySelector('[data-agent-presence]');
        var agentPresenceFeedback = root.querySelector('[data-agent-presence-feedback]');

        if (handoffPanel && !canAssign) {
            handoffPanel.classList.add('d-none');
        }
        var selectedNumber = '';
        var pendingAttachment = null;
        var attachmentObjectUrl = null;
        var typingIndicator = null;
        var typingTimerId = null;
        var toastStack = null;
        var templatesLoaded = false;
        var templateState = null;
        var templateFieldInputs = {};
        var templateCache = {};

        function getConversationEndpoint(id) {
            return endpoints.conversation.replace('{id}', String(id));
        }

        function getAssignEndpoint(id) {
            return endpoints.assign.replace('{id}', String(id));
        }

        function getTransferEndpoint(id) {
            return endpoints.transfer.replace('{id}', String(id));
        }

        function getCloseEndpoint(id) {
            return endpoints.close.replace('{id}', String(id));
        }

        function getDeleteEndpoint(id) {
            return endpoints.remove.replace('{id}', String(id));
        }

        function getMediaEndpoint(id) {
            return endpoints.media.replace('{id}', encodeURIComponent(String(id)));
        }

        function computeConversationListSignature(conversations) {
            if (!Array.isArray(conversations) || !conversations.length) {
                return 'empty';
            }

            return conversations.map(function (conversation) {
                var lastAt = conversation && conversation.last_message ? (conversation.last_message.at || '') : '';
                var unread = conversation ? Number(conversation.unread_count || 0) : 0;
                var needsHuman = conversation && conversation.needs_human ? 1 : 0;
                var assignedId = conversation ? Number(conversation.assigned_user_id || 0) : 0;
                return [conversation.id || 0, lastAt, unread, needsHuman, assignedId].join(':');
            }).join('|');
        }

        function computeConversationSignature(conversation) {
            if (!conversation || !conversation.id) {
                return '';
            }

            var messages = Array.isArray(conversation.messages) ? conversation.messages : [];
            var first = messages.length ? messages[0] : null;
            var last = messages.length ? messages[messages.length - 1] : null;

            return [
                conversation.id,
                conversation.last_message_at || '',
                conversation.needs_human ? 1 : 0,
                Number(conversation.assigned_user_id || 0),
                Number(conversation.handoff_role_id || 0),
                messages.length,
                first ? (first.id || first.timestamp || '') : '',
                last ? (last.id || last.timestamp || '') : '',
                last ? (last.status || '') : ''
            ].join('|');
        }

        function notifyIncomingFromList(conversations) {
            if (!Array.isArray(conversations)) {
                return;
            }

            var nextUnread = {};
            conversations.forEach(function (conversation) {
                if (!conversation || !conversation.id) {
                    return;
                }
                nextUnread[String(conversation.id)] = Number(conversation.unread_count || 0);
            });

            if (!state.unreadInitialized) {
                state.unreadByConversation = nextUnread;
                state.unreadInitialized = true;
                return;
            }

            conversations.forEach(function (conversation) {
                if (!conversation || !conversation.id) {
                    return;
                }

                var key = String(conversation.id);
                var previous = Number(state.unreadByConversation[key] || 0);
                var current = Number(conversation.unread_count || 0);
                if (current <= previous) {
                    return;
                }

                if (state.selectedId && Number(state.selectedId) === Number(conversation.id)) {
                    return;
                }

                var name = conversation.display_name || conversation.patient_full_name || conversation.wa_number || 'Contacto';
                showHandoffToast('Nuevo mensaje de ' + name);
            });

            state.unreadByConversation = nextUnread;
        }

        function toggleComposer(disabled) {
            if (!composer) {
                return;
            }

            var shouldDisable = disabled || !enabled;
            composer.querySelectorAll('textarea, input, button').forEach(function (element) {
                element.disabled = shouldDisable;
            });
        }

        function resetConversationView() {
            state.selectedId = null;
            state.selectedHasInbound = true;
            state.selectedConversationSignature = '';
            selectedNumber = '';

            if (titleElement) {
                titleElement.textContent = 'Selecciona una conversación';
            }
            if (subtitle) {
                subtitle.textContent = 'El historial aparecerá cuando elijas un contacto.';
            }
            if (lastSeenElement) {
                lastSeenElement.textContent = '';
            }
            if (assignedCompact) {
                assignedCompact.textContent = '';
                assignedCompact.classList.add('d-none');
            }
            if (teamCompact) {
                teamCompact.textContent = '';
                teamCompact.classList.add('d-none');
            }
            if (botStatusCompact) {
                botStatusCompact.textContent = '';
                botStatusCompact.classList.remove('meta-warning', 'meta-active');
                botStatusCompact.classList.add('d-none');
            }
            if (detailName) {
                detailName.textContent = 'Selecciona una conversación';
            }
            if (detailNumber) {
                detailNumber.textContent = 'El número aparecerá aquí.';
            }
            if (detailPatient) {
                detailPatient.textContent = '—';
            }
            if (detailHc) {
                detailHc.textContent = '—';
            }
            if (detailLast) {
                detailLast.textContent = '—';
            }
            if (detailUnread) {
                detailUnread.textContent = '—';
            }
            if (detailHandoff) {
                detailHandoff.textContent = '—';
            }
            if (detailNotes) {
                detailNotes.textContent = '—';
            }
            if (openChatLink) {
                openChatLink.href = '#';
                openChatLink.classList.add('disabled');
            }
            if (copyNumberButton) {
                copyNumberButton.disabled = true;
            }
            if (closeConversationButton) {
                closeConversationButton.disabled = true;
            }
            if (deleteConversationButton) {
                deleteConversationButton.disabled = true;
            }
            if (needsHumanBadge) {
                needsHumanBadge.classList.add('d-none');
            }
            if (assignedBadge) {
                assignedBadge.classList.add('d-none');
                assignedBadge.textContent = '';
            }
            if (firstContactBadge) {
                firstContactBadge.classList.add('d-none');
            }
            if (templateWarning) {
                templateWarning.classList.add('d-none');
            }
            if (unreadIndicator) {
                unreadIndicator.classList.add('d-none');
            }
            if (headerAvatarImg) {
                headerAvatarImg.classList.add('d-none');
            }
            if (headerAvatarInitials) {
                headerAvatarInitials.textContent = 'WA';
                headerAvatarInitials.classList.remove('d-none');
            }

            renderMessages({ messages: [] });
            toggleComposer(true);
            renderConversations();
            updateHandoffPanel(null);
            renderAgentOptions(null);
        }

        function updateHandoffPanel(conversation) {
            if (!handoffPanel) {
                return;
            }

            var needsHuman = Boolean(conversation && conversation.needs_human);
            var assignedId = conversation && conversation.assigned_user_id ? Number(conversation.assigned_user_id) : 0;
            var assignedName = conversation && conversation.assigned_user_name ? conversation.assigned_user_name : '';
            var roleName = conversation && conversation.handoff_role_name ? conversation.handoff_role_name : '';

            if (handoffBadge) {
                handoffBadge.classList.remove('bg-secondary-light', 'text-secondary', 'bg-warning-light', 'text-warning', 'bg-success-light', 'text-success');
                if (assignedId) {
                    handoffBadge.textContent = 'Asignado';
                    handoffBadge.classList.add('bg-success-light', 'text-success');
                } else if (needsHuman) {
                    handoffBadge.textContent = 'Pendiente';
                    handoffBadge.classList.add('bg-warning-light', 'text-warning');
                } else {
                    handoffBadge.textContent = 'Automático';
                    handoffBadge.classList.add('bg-secondary-light', 'text-secondary');
                }
            }

            if (handoffQueue) {
                var queueText = roleName ? ('Equipo: ' + roleName) : 'Equipo: General';
                if (assignedId && assignedName) {
                    queueText += ' · ' + assignedName;
                }
                handoffQueue.textContent = queueText;
            }

            if (takeConversationButton) {
                var canTake = canAssign && needsHuman && !assignedId;
                if (conversation && conversation.handoff_role_id && currentRoleId && Number(conversation.handoff_role_id) !== currentRoleId) {
                    canTake = false;
                }
                takeConversationButton.classList.toggle('d-none', !canTake);
            }

            if (transferSelect && transferButton) {
                var canTransfer = canAssign && (assignedId === currentUserId || !assignedId);
                transferSelect.disabled = !canTransfer;
                transferButton.disabled = !canTransfer;
            }
        }

        function renderAgentOptions(conversation) {
            if (!transferSelect) {
                return;
            }

            transferSelect.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecciona un agente';
            transferSelect.appendChild(placeholder);

            var roleFilter = conversation && conversation.handoff_role_id ? Number(conversation.handoff_role_id) : 0;

            state.agents.forEach(function (agent) {
                if (!agent || !agent.id) {
                    return;
                }
                if (roleFilter && agent.role_id && Number(agent.role_id) !== roleFilter) {
                    return;
                }
                var presence = String(agent.presence_status || 'available');
                if (presence !== 'available') {
                    return;
                }
                var option = document.createElement('option');
                option.value = String(agent.id);
                var label = agent.name || agent.username || 'Agente';
                if (agent.role_name) {
                    label += ' · ' + agent.role_name;
                }
                option.textContent = label;
                transferSelect.appendChild(option);
            });
        }

        function fetchAgents() {
            if (!endpoints.agents) {
                return Promise.resolve();
            }

            return fetch(endpoints.agents, { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('No fue posible cargar agentes');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (payload && payload.ok && Array.isArray(payload.data)) {
                        state.agents = payload.data;
                        if (state.selectedId) {
                            var summary = state.conversations.find(function (item) {
                                return item.id === state.selectedId;
                            });
                            renderAgentOptions(summary || null);
                        }
                    }
                })
                .catch(function () {});
        }

        function applyConversationUpdate(conversation) {
            if (!conversation || !conversation.id) {
                return;
            }

            var summary = state.conversations.find(function (item) {
                return item.id === conversation.id;
            });

            if (summary) {
                summary.needs_human = conversation.needs_human;
                summary.handoff_notes = conversation.handoff_notes;
                summary.handoff_role_id = conversation.handoff_role_id;
                summary.handoff_role_name = conversation.handoff_role_name;
                summary.assigned_user_id = conversation.assigned_user_id;
                summary.assigned_user_name = conversation.assigned_user_name;
                summary.assigned_at = conversation.assigned_at;
                summary.handoff_requested_at = conversation.handoff_requested_at;
            }

            renderConversations();
            updateHeader(conversation);
        }

        function assignConversation(conversationId, userId) {
            return fetch(getAssignEndpoint(conversationId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ user_id: userId }),
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload || !payload.ok) {
                        var error = payload && payload.error ? payload.error : 'No fue posible asignar la conversación.';
                        throw new Error(error);
                    }
                    return payload.data;
                });
            }).then(function (conversation) {
                applyConversationUpdate(conversation);
            }).catch(function (error) {
                if (errorAlert) {
                    errorAlert.textContent = error.message || 'No fue posible asignar la conversación.';
                    errorAlert.classList.remove('d-none');
                }
            });
        }

        function transferConversation(conversationId, userId, note) {
            return fetch(getTransferEndpoint(conversationId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ user_id: userId, note: note || '' }),
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload || !payload.ok) {
                        var error = payload && payload.error ? payload.error : 'No fue posible transferir la conversación.';
                        throw new Error(error);
                    }
                    return payload.data;
                });
            }).then(function (conversation) {
                if (transferNoteInput) {
                    transferNoteInput.value = '';
                }
                applyConversationUpdate(conversation);
            }).catch(function (error) {
                if (errorAlert) {
                    errorAlert.textContent = error.message || 'No fue posible transferir la conversación.';
                    errorAlert.classList.remove('d-none');
                }
            });
        }

        function closeConversation(conversationId) {
            return fetch(getCloseEndpoint(conversationId), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload || !payload.ok) {
                        var error = payload && payload.error ? payload.error : 'No fue posible cerrar la conversación.';
                        throw new Error(error);
                    }
                    return payload.data;
                });
            }).then(function (conversation) {
                applyConversationUpdate(conversation);
                return openConversation(conversationId, { silent: true });
            });
        }

        function deleteConversation(conversationId) {
            return fetch(getDeleteEndpoint(conversationId), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload || !payload.ok) {
                        var error = payload && payload.error ? payload.error : 'No fue posible eliminar la conversación.';
                        throw new Error(error);
                    }
                    return payload.data;
                });
            });
        }

        function maybeShowDesktopNotification(title, body) {
            var realtimeConfig = window.MEDF_PusherConfig || {};
            if (!realtimeConfig.desktop_notifications || typeof window === 'undefined' || !('Notification' in window)) {
                return;
            }

            if (Notification.permission === 'default') {
                Notification.requestPermission().catch(function () {});
            }

            if (Notification.permission !== 'granted') {
                return;
            }

            var notification = new Notification(title, { body: body });
            var dismissSeconds = realtimeConfig.auto_dismiss_seconds || 0;
            if (dismissSeconds && dismissSeconds > 0) {
                setTimeout(function () { notification.close(); }, dismissSeconds * 1000);
            }
        }

        function getToastStack() {
            if (toastStack && toastStack.parentNode) {
                return toastStack;
            }

            toastStack = document.querySelector('.wa-inline-toast-stack');
            if (toastStack) {
                return toastStack;
            }

            toastStack = document.createElement('div');
            toastStack.className = 'wa-inline-toast-stack';
            document.body.appendChild(toastStack);

            return toastStack;
        }

        function showHandoffToast(message) {
            if (!message) {
                return;
            }

            var stack = getToastStack();
            if (!stack) {
                return;
            }

            if (stack.childElementCount >= 3) {
                stack.removeChild(stack.firstElementChild);
            }

            var toast = document.createElement('div');
            toast.className = 'wa-inline-toast';

            var icon = document.createElement('i');
            icon.className = 'mdi mdi-whatsapp';
            toast.appendChild(icon);

            var text = document.createElement('span');
            text.className = 'wa-inline-toast__text';
            text.textContent = message;
            toast.appendChild(text);

            var closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'btn-close';
            closeButton.setAttribute('aria-label', 'Cerrar');
            closeButton.addEventListener('click', function () {
                toast.remove();
            });
            toast.appendChild(closeButton);

            stack.appendChild(toast);

            setTimeout(function () {
                if (toast && toast.parentNode) {
                    toast.remove();
                }
            }, 4200);
        }

        function setupRealtime() {
            var realtimeConfig = window.MEDF_PusherConfig || {};
            if (!realtimeConfig.enabled) {
                return;
            }
            if (typeof Pusher === 'undefined' || !realtimeConfig.key) {
                console.warn('Pusher no está disponible para WhatsApp.');
                return;
            }

            var options = { forceTLS: true };
            if (realtimeConfig.cluster) {
                options.cluster = realtimeConfig.cluster;
            }

            var pusher = new Pusher(realtimeConfig.key, options);
            var channelName = realtimeConfig.channel || 'solicitudes-kanban';
            var events = realtimeConfig.events || {};
            var handoffEvent = events.whatsapp_handoff || 'whatsapp.handoff';

            var channel = pusher.subscribe(channelName);
            channel.bind(handoffEvent, function (data) {
                if (!data) {
                    return;
                }

                if (data.handoff_role_id && currentRoleId && Number(data.handoff_role_id) !== currentRoleId) {
                    return;
                }

                var name = data.display_name || data.patient_full_name || data.wa_number || 'Contacto';
                showHandoffToast('Nueva solicitud de agente: ' + name);
                maybeShowDesktopNotification('WhatsApp · Nuevo handoff', name);

                loadConversations();
            });
        }

        function renderConversations() {
            if (!listContainer) {
                return;
            }

            resetContainer(listContainer, emptyListState);

            if (!state.conversations.length) {
                if (emptyListState) {
                    emptyListState.classList.remove('d-none');
                }
                return;
            }

            // Ensure empty state is hidden when there is data
            if (emptyListState) {
                emptyListState.classList.add('d-none');
            }

            // Append each conversation as a .media item (demo look & feel)
            state.conversations.forEach(function (conversation) {
                var media = createElement('div', 'media');
                media.setAttribute('data-id', conversation.id);

                // Avatar/link
                var a = document.createElement('a');
                a.className = 'align-self-center me-0';
                a.href = '#';

                // Prefer avatar_url if provided; otherwise use an icon avatar
                var avatarEl;
                if (conversation.avatar_url) {
                    avatarEl = document.createElement('img');
                    avatarEl.className = 'avatar avatar-lg';
                    avatarEl.src = conversation.avatar_url;
                    avatarEl.alt = '...';
                } else {
                    avatarEl = createElement('span', 'avatar avatar-lg bg-primary-light d-inline-flex align-items-center justify-content-center');
                    var initials = computeInitials(conversation.display_name || conversation.patient_full_name || conversation.wa_number);
                    if (initials) {
                        avatarEl.textContent = initials;
                        avatarEl.classList.add('fw-600', 'text-primary');
                    } else {
                        var icon = createElement('i', 'mdi mdi-account text-primary');
                        avatarEl.appendChild(icon);
                    }
                }
                a.appendChild(avatarEl);
                media.appendChild(a);

                // Body
                var body = createElement('div', 'media-body');

                var title = conversation.display_name || conversation.patient_full_name || conversation.wa_number || 'Contacto';
                var lastAt = (conversation.last_message && conversation.last_message.at) ? new Date(conversation.last_message.at) : null;
                var timeText = lastAt && !isNaN(lastAt.getTime()) ? lastAt.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                }) : '';

                var pTop = document.createElement('p');
                var nameLink = document.createElement('a');
                nameLink.className = 'hover-primary';
                nameLink.href = '#';
                var strong = document.createElement('strong');
                strong.textContent = title;
                nameLink.appendChild(strong);
                pTop.appendChild(nameLink);

                var badgeWrap = createElement('span', 'ms-2');
                if (conversation.assigned_user_name) {
                    var assignedLabel = conversation.assigned_user_name.length > 16
                        ? conversation.assigned_user_name.slice(0, 15) + '…'
                        : conversation.assigned_user_name;
                    var assignedBadge = createElement('span', 'badge bg-success-light text-success me-1', assignedLabel);
                    badgeWrap.appendChild(assignedBadge);
                } else if (conversation.needs_human) {
                    var handoffBadge = createElement('span', 'badge bg-warning-light text-warning me-1', 'Pendiente');
                    badgeWrap.appendChild(handoffBadge);
                }
                if (conversation.unread_count && conversation.unread_count > 0) {
                    var unreadBadge = createElement('span', 'badge bg-primary-light text-primary', String(conversation.unread_count));
                    badgeWrap.appendChild(unreadBadge);
                }
                if (badgeWrap.childNodes.length) {
                    pTop.appendChild(badgeWrap);
                }

                var timeSpan = createElement('span', 'float-end fs-10', timeText);
                pTop.appendChild(timeSpan);
                body.appendChild(pTop);

                var previewText = '';
                if (conversation.last_message && conversation.last_message.preview) {
                    previewText = conversation.last_message.preview;
                } else if (conversation.last_message && conversation.last_message.body) {
                    previewText = conversation.last_message.body;
                } else {
                    previewText = '';
                }

                var pPreview = document.createElement('p');
                pPreview.textContent = previewText;
                body.appendChild(pPreview);

                media.appendChild(body);

                // Active state styling
                if (state.selectedId === conversation.id) {
                    media.classList.add('active');
                    media.classList.add('bg-light');
                    media.classList.add('rounded');
                }

                // Click handler
                media.addEventListener('click', function (evt) {
                    evt.preventDefault();
                    if (state.loadingConversation) {
                        return;
                    }
                    openConversation(conversation.id);
                });

                listContainer.appendChild(media);
            });
        }

        function renderMessages(data) {
            if (!messageContainer) {
                return;
            }

            var previousScrollTop = messageContainer.scrollTop;
            var wasNearBottom = isNearBottom(messageContainer, 120);

            resetContainer(messageContainer, emptyChatState);

            if (!data || !data.messages || !data.messages.length) {
                if (emptyChatState) {
                    emptyChatState.classList.remove('d-none');
                }
                removeTypingIndicator();
                state.forceScroll = false;
                return;
            }

            if (emptyChatState) {
                emptyChatState.classList.add('d-none');
            }

            var formatTime = function (value) {
                try {
                    var d = new Date(value);
                    if (!isNaN(d.getTime())) {
                        return d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                    }
                } catch (e) {
                }
                return '';
            };

            data.messages.forEach(function (message) {
                var isOutbound = message.direction === 'outbound';
                var senderName = message.sender_name || (isOutbound ? 'Tú' : (data.patient_full_name || data.display_name || data.wa_number || 'Contacto'));

                // Card container with left/right float and background per sample
                var cardClass = isOutbound
                    ? 'card d-inline-block mb-3 float-end me-2 bg-primary max-w-p80 whatsapp-chat-bubble'
                    : 'card d-inline-block mb-3 float-start me-2 no-shadow bg-lighter max-w-p80 whatsapp-chat-bubble';
                var card = createElement('div', cardClass);

                // Absolute timestamp at top-right
                var stampWrap = createElement('div', 'position-absolute pt-1 pe-2 r-0');
                var stamp = createElement('span', 'text-extra-small' + (isOutbound ? '' : ' text-muted'), formatTime(message.timestamp));
                stampWrap.appendChild(stamp);
                if (isOutbound && message.status) {
                    var statusEl = buildStatusIndicator(message.status);
                    if (statusEl) {
                        stampWrap.appendChild(statusEl);
                    }
                }
                card.appendChild(stampWrap);

                var body = createElement('div', 'card-body');

                // Header row: avatar + sender name
                var headerRow = createElement('div', 'd-flex flex-row pb-2');

                var avatarLink = createElement('a', 'd-flex');
                avatarLink.href = '#';

                // Choose avatar (prefer message.sender_avatar if present)
                var avatarEl;
                if (message.sender_avatar) {
                    avatarEl = document.createElement('img');
                    avatarEl.alt = 'Profile';
                    avatarEl.src = appendCacheBuster(message.sender_avatar);
                    avatarEl.className = 'avatar me-10';
                } else if (!isOutbound && data.avatar_url) {
                    avatarEl = document.createElement('img');
                    avatarEl.alt = 'Profile';
                    avatarEl.src = appendCacheBuster(data.avatar_url);
                    avatarEl.className = 'avatar me-10';
                } else {
                    // Fallback avatar with initials + color
                    avatarEl = createElement('span', 'avatar me-10 d-inline-flex align-items-center justify-content-center');
                    var initials = computeInitials(senderName || 'WA');
                    var initialsSpan = createElement('span', 'fw-600', initials || 'WA');
                    avatarEl.appendChild(initialsSpan);
                    applyAvatarTheme(avatarEl, senderName || data.wa_number || 'WA');
                }
                avatarLink.appendChild(avatarEl);
                headerRow.appendChild(avatarLink);

                var flexGrow = createElement('div', 'd-flex flex-grow-1 min-width-zero');
                var nameWrap = createElement('div', 'm-2 ps-0 align-self-center d-flex flex-column flex-lg-row justify-content-between');
                var inner = createElement('div', 'min-width-zero');
                var nameP = createElement('p', 'mb-0 fs-16' + (isOutbound ? '' : ' text-dark'));
                nameP.textContent = senderName;
                inner.appendChild(nameP);
                nameWrap.appendChild(inner);
                flexGrow.appendChild(nameWrap);
                headerRow.appendChild(flexGrow);

                body.appendChild(headerRow);

                // Message text block with left padding (ps-55)
                var textWrap = createElement('div', 'chat-text-start ps-55');
                var paragraph = createElement('p', 'mb-0 text-semi-muted');
                var hasBody = message.body && String(message.body).trim() !== '';
                if (hasBody) {
                    paragraph.textContent = message.body;
                    textWrap.appendChild(paragraph);
                } else if (message.media_caption) {
                    paragraph.textContent = message.media_caption;
                    textWrap.appendChild(paragraph);
                    hasBody = true;
                }

                var mediaPreview = buildMediaPreview(message);
                if (mediaPreview) {
                    textWrap.appendChild(mediaPreview);
                } else if (!hasBody) {
                    paragraph.textContent = '[Contenido sin vista previa]';
                    textWrap.appendChild(paragraph);
                }
                body.appendChild(textWrap);

                card.appendChild(body);
                messageContainer.appendChild(card);
                messageContainer.appendChild(createElement('div', 'clearfix'));
            });

            maybeShowTypingIndicator(data);

            var shouldStickToBottom = state.forceScroll || wasNearBottom;
            if (shouldStickToBottom) {
                messageContainer.scrollTop = messageContainer.scrollHeight;
            } else {
                var maxScroll = Math.max(0, messageContainer.scrollHeight - messageContainer.clientHeight);
                messageContainer.scrollTop = Math.min(previousScrollTop, maxScroll);
            }

            state.forceScroll = false;
        }

        function buildMediaPreview(message) {
            if (!message || !message.type) {
                return null;
            }

            var type = String(message.type);
            var url = resolveMediaUrl(message);

            if (type === 'image') {
                if (url) {
                    var imgWrap = createElement('div', 'whatsapp-chat-media');
                    var img = document.createElement('img');
                    img.src = appendCacheBuster(url);
                    img.alt = message.media_caption || 'Imagen';
                    imgWrap.appendChild(img);
                    return imgWrap;
                }
                return createMediaPlaceholder('Imagen recibida');
            }

            if (type === 'document') {
                var docWrap = createElement('div', 'whatsapp-chat-media');
                var doc = createElement('div', 'whatsapp-chat-doc');
                var icon = createElement('i', 'mdi mdi-file-document-outline text-primary');
                doc.appendChild(icon);
                var meta = createElement('div', 'd-flex flex-column');
                var name = message.media_filename || 'Documento';
                var nameEl = createElement('div', 'doc-name', name);
                var metaText = message.media_mime ? message.media_mime : '';
                var metaEl = createElement('div', 'doc-meta', metaText);
                meta.appendChild(nameEl);
                meta.appendChild(metaEl);
                doc.appendChild(meta);

                if (url) {
                    var link = document.createElement('a');
                    link.href = url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.className = 'text-decoration-none text-reset';
                    link.appendChild(doc);
                    docWrap.appendChild(link);
                } else {
                    docWrap.appendChild(doc);
                }

                return docWrap;
            }

            if (type === 'audio') {
                if (url) {
                    var audioWrap = createElement('div', 'whatsapp-chat-media');
                    var audio = document.createElement('audio');
                    audio.controls = true;
                    audio.src = url;
                    audioWrap.appendChild(audio);
                    return audioWrap;
                }
                return createMediaPlaceholder('Audio recibido');
            }

            return null;
        }

        function createMediaPlaceholder(label) {
            var wrap = createElement('div', 'whatsapp-chat-media');
            var badge = createElement('span', 'badge bg-secondary-light text-secondary', label);
            wrap.appendChild(badge);
            return wrap;
        }

        function resolveMediaUrl(message) {
            if (!message) {
                return '';
            }

            var mediaId = '';
            if (message.media_id) {
                mediaId = String(message.media_id).trim();
            }

            if (!mediaId && message.media_url) {
                mediaId = extractMediaIdFromUrl(String(message.media_url));
            }

            if (mediaId && endpoints.media) {
                return getMediaEndpoint(mediaId);
            }

            if (message.media_url) {
                return String(message.media_url);
            }

            return '';
        }

        function extractMediaIdFromUrl(url) {
            if (!url) {
                return '';
            }

            try {
                var parsed = new URL(url, window.location.origin);
                var mid = parsed.searchParams.get('mid');
                return mid ? String(mid).trim() : '';
            } catch (error) {
                return '';
            }
        }

        function buildStatusIndicator(status) {
            if (!status) {
                return null;
            }

            var normalized = String(status).toLowerCase();
            var label = '';
            var className = 'whatsapp-status';

            if (normalized === 'sent') {
                label = '✓';
            } else if (normalized === 'delivered') {
                label = '✓✓';
            } else if (normalized === 'read') {
                label = '✓✓';
                className += ' read';
            } else if (normalized === 'failed') {
                label = '!';
                className += ' failed';
            } else {
                return null;
            }

            return createElement('span', className, label);
        }

        function showTypingIndicator() {
            if (!messageContainer) {
                return;
            }

            removeTypingIndicator();

            var bubble = createElement('div', 'whatsapp-typing');
            bubble.appendChild(createElement('span', 'dot'));
            bubble.appendChild(createElement('span', 'dot'));
            bubble.appendChild(createElement('span', 'dot'));
            typingIndicator = bubble;
            messageContainer.appendChild(bubble);
            messageContainer.appendChild(createElement('div', 'clearfix'));

            typingTimerId = window.setTimeout(function () {
                removeTypingIndicator();
            }, 1400);
        }

        function removeTypingIndicator() {
            if (typingTimerId) {
                window.clearTimeout(typingTimerId);
                typingTimerId = null;
            }
            if (typingIndicator && typingIndicator.parentNode) {
                typingIndicator.parentNode.removeChild(typingIndicator);
            }
            typingIndicator = null;
        }

        function maybeShowTypingIndicator(conversation) {
            if (!conversation || !conversation.messages || !conversation.messages.length) {
                removeTypingIndicator();
                return;
            }

            if (conversation.needs_human || conversation.assigned_user_id) {
                removeTypingIndicator();
                return;
            }

            var convId = conversation.id || conversation.conversation_id;
            if (!convId) {
                return;
            }

            var lastInbound = null;
            for (var i = conversation.messages.length - 1; i >= 0; i--) {
                var msg = conversation.messages[i];
                if (msg && msg.direction === 'inbound') {
                    lastInbound = msg;
                    break;
                }
            }

            if (!lastInbound) {
                return;
            }

            var marker = lastInbound.id ? String(lastInbound.id) : (lastInbound.timestamp || '');
            if (!marker) {
                return;
            }

            if (state.lastInboundByConversation[convId] === undefined) {
                state.lastInboundByConversation[convId] = marker;
                return;
            }

            if (state.lastInboundByConversation[convId] === marker) {
                return;
            }

            state.lastInboundByConversation[convId] = marker;
            showTypingIndicator();
        }

        function updateHeader(conversation) {
            if (!header || !subtitle) {
                return;
            }

            var title = conversation.display_name || conversation.patient_full_name || conversation.wa_number;
            if (titleElement) {
                titleElement.textContent = title;
            }

            subtitle.textContent = conversation.wa_number || '';
            selectedNumber = conversation.wa_number || '';

            updateHeaderAvatar(conversation);

            if (lastSeenElement) {
                if (conversation.last_message_at) {
                    lastSeenElement.textContent = 'Último: ' + formatDate(conversation.last_message_at);
                    lastSeenElement.classList.remove('d-none');
                } else {
                    lastSeenElement.textContent = '';
                    lastSeenElement.classList.add('d-none');
                }
            }

            var summary = state.conversations.find(function (item) {
                return item.id === conversation.id;
            });
            var messages = Array.isArray(conversation.messages) ? conversation.messages : [];
            var inboundCount = 0;
            var outboundCount = 0;
            messages.forEach(function (item) {
                if (!item || !item.direction) {
                    return;
                }
                if (item.direction === 'inbound') {
                    inboundCount += 1;
                } else if (item.direction === 'outbound') {
                    outboundCount += 1;
                }
            });
            var hasInbound = Boolean(conversation.has_inbound) || inboundCount > 0;
            var isInitialContact = hasInbound && outboundCount === 0;

            if (unreadIndicator) {
                var unreadCount = summary && summary.unread_count ? summary.unread_count : 0;
                if (unreadCount > 0) {
                    unreadIndicator.textContent = unreadCount + ' sin leer';
                    unreadIndicator.classList.remove('d-none');
                } else {
                    unreadIndicator.classList.add('d-none');
                }
            }

            if (firstContactBadge) {
                if (isInitialContact) {
                    firstContactBadge.classList.remove('d-none');
                } else {
                    firstContactBadge.classList.add('d-none');
                }
            }

            if (needsHumanBadge) {
                if (conversation.needs_human) {
                    needsHumanBadge.classList.remove('d-none');
                } else {
                    needsHumanBadge.classList.add('d-none');
                }
            }

            if (assignedBadge) {
                if (conversation.assigned_user_name) {
                    assignedBadge.textContent = 'Asignado a ' + conversation.assigned_user_name;
                    assignedBadge.classList.remove('d-none');
                } else {
                    assignedBadge.classList.add('d-none');
                    assignedBadge.textContent = '';
                }
            }

            if (assignedCompact) {
                if (conversation.assigned_user_name) {
                    assignedCompact.textContent = 'Asignado a ' + conversation.assigned_user_name;
                    assignedCompact.classList.remove('d-none');
                } else {
                    assignedCompact.textContent = '';
                    assignedCompact.classList.add('d-none');
                }
            }

            if (teamCompact) {
                if (conversation.handoff_role_name) {
                    teamCompact.textContent = 'Equipo: ' + conversation.handoff_role_name;
                    teamCompact.classList.remove('d-none');
                } else {
                    teamCompact.textContent = '';
                    teamCompact.classList.add('d-none');
                }
            }

            if (botStatusCompact) {
                botStatusCompact.classList.remove('meta-warning', 'meta-active');
                var botStatusText = '';
                if (conversation.assigned_user_id) {
                    botStatusText = 'Bot en pausa (chat asignado)';
                    botStatusCompact.classList.add('meta-warning');
                } else if (conversation.needs_human) {
                    botStatusText = 'Bot en pausa (handoff)';
                    botStatusCompact.classList.add('meta-warning');
                } else if (isInitialContact) {
                    botStatusText = 'Bot activo (primer contacto)';
                    botStatusCompact.classList.add('meta-active');
                } else {
                    botStatusText = 'Bot activo';
                    botStatusCompact.classList.add('meta-active');
                }

                botStatusCompact.textContent = botStatusText;
                botStatusCompact.classList.remove('d-none');
            }

            if (summary && summary.unread_count) {
                summary.unread_count = 0;
                renderConversations();
            }

            if (detailName) {
                detailName.textContent = title;
            }

            if (detailNumber) {
                detailNumber.textContent = conversation.wa_number || '—';
            }

            if (detailPatient) {
                detailPatient.textContent = conversation.patient_full_name || '—';
            }

            if (detailHc) {
                detailHc.textContent = conversation.patient_hc_number || '—';
            }

            if (detailLast) {
                detailLast.textContent = conversation.last_message_at ? formatDate(conversation.last_message_at) : '—';
            }

            if (detailUnread) {
                var detailUnreadCount = summary && summary.unread_count ? summary.unread_count : 0;
                detailUnread.textContent = detailUnreadCount > 0 ? String(detailUnreadCount) : '0';
            }

            if (detailHandoff) {
                if (conversation.assigned_user_name) {
                    detailHandoff.textContent = 'Asignado a ' + conversation.assigned_user_name;
                } else if (conversation.needs_human) {
                    var roleLabel = conversation.handoff_role_name ? (' · ' + conversation.handoff_role_name) : '';
                    detailHandoff.textContent = 'Pendiente de agente' + roleLabel;
                } else {
                    detailHandoff.textContent = 'Automático';
                }
            }

            if (detailNotes) {
                detailNotes.textContent = conversation.handoff_notes || '—';
            }

            if (openChatLink) {
                if (selectedNumber) {
                    openChatLink.href = buildWaMe(selectedNumber);
                    openChatLink.classList.remove('disabled');
                } else {
                    openChatLink.href = '#';
                    openChatLink.classList.add('disabled');
                }
            }

            if (copyNumberButton) {
                copyNumberButton.disabled = !selectedNumber;
            }

            if (closeConversationButton) {
                closeConversationButton.disabled = !state.selectedId;
            }

            if (deleteConversationButton) {
                deleteConversationButton.disabled = !state.selectedId;
            }

            updateHandoffPanel(conversation);
            renderAgentOptions(conversation);
            updateTemplateWarning(conversation);
        }

        function updateHeaderAvatar(conversation) {
            if (!headerAvatar) {
                return;
            }

            var avatarUrl = conversation.avatar_url || '';
            if (avatarUrl && headerAvatarImg) {
                headerAvatarImg.src = appendCacheBuster(avatarUrl);
                headerAvatarImg.classList.remove('d-none');
                if (headerAvatarInitials) {
                    headerAvatarInitials.classList.add('d-none');
                }
                return;
            }

            if (headerAvatarImg) {
                headerAvatarImg.classList.add('d-none');
            }

            if (headerAvatarInitials) {
                var initials = computeInitials(conversation.display_name || conversation.patient_full_name || conversation.wa_number);
                headerAvatarInitials.textContent = initials || 'WA';
                headerAvatarInitials.classList.remove('d-none');
            }

            applyAvatarTheme(headerAvatar, conversation.display_name || conversation.patient_full_name || conversation.wa_number || 'WA');
        }

        function resolveHasInbound(conversation) {
            if (conversation && typeof conversation.has_inbound === 'boolean') {
                return conversation.has_inbound;
            }

            if (conversation && Array.isArray(conversation.messages)) {
                for (var i = 0; i < conversation.messages.length; i++) {
                    if (conversation.messages[i] && conversation.messages[i].direction === 'inbound') {
                        return true;
                    }
                }
            }

            return true;
        }

        function updateTemplateWarning(conversation) {
            var hasInbound = resolveHasInbound(conversation);
            state.selectedHasInbound = hasInbound;

            if (!templateWarning) {
                return;
            }

            if (hasInbound) {
                templateWarning.classList.add('d-none');
            } else {
                templateWarning.classList.remove('d-none');
            }
        }

        function openTemplateTab() {
            var tabLink = root.querySelector('a[data-bs-toggle=\"tab\"][href=\"#contacts\"]');
            if (tabLink) {
                tabLink.click();
            }

            if (templateToggle && !templateToggle.checked) {
                templateToggle.checked = true;
                templateToggle.dispatchEvent(new Event('change', {bubbles: true}));
            }

            if (templateSelect) {
                templateSelect.focus();
            }
        }

        function computeInitials(value) {
            if (!value) {
                return '';
            }

            var clean = String(value).trim();
            if (!clean) {
                return '';
            }

            var parts = clean.split(/\s+/).filter(Boolean);
            if (!parts.length) {
                return '';
            }

            if (parts.length === 1) {
                return parts[0].substring(0, 2).toUpperCase();
            }

            return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
        }

        function colorFromString(value) {
            var palette = [
                {bg: '#e0f2fe', text: '#0369a1'},
                {bg: '#fee2e2', text: '#b91c1c'},
                {bg: '#dcfce7', text: '#15803d'},
                {bg: '#fef9c3', text: '#a16207'},
                {bg: '#ede9fe', text: '#6d28d9'},
                {bg: '#fce7f3', text: '#be185d'},
                {bg: '#cffafe', text: '#0e7490'},
                {bg: '#ffedd5', text: '#c2410c'}
            ];

            var str = String(value || 'chat');
            var hash = 0;
            for (var i = 0; i < str.length; i++) {
                hash = ((hash << 5) - hash) + str.charCodeAt(i);
                hash |= 0;
            }
            var index = Math.abs(hash) % palette.length;
            return palette[index];
        }

        function applyAvatarTheme(element, seed) {
            if (!element) {
                return;
            }

            var theme = colorFromString(seed || '');
            element.style.backgroundColor = theme.bg;
            element.style.color = theme.text;
        }

        function buildWaMe(number) {
            var digits = (number || '').replace(/\D+/g, '');
            if (!digits) {
                return '#';
            }

            return 'https://wa.me/' + digits;
        }

        function copyToClipboard(text) {
            if (!text) {
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).catch(function () {});
                return;
            }

            var temp = document.createElement('textarea');
            temp.value = text;
            temp.setAttribute('readonly', '');
            temp.style.position = 'absolute';
            temp.style.left = '-9999px';
            document.body.appendChild(temp);
            temp.select();
            try {
                document.execCommand('copy');
            } catch (e) {
            }
            document.body.removeChild(temp);
        }

        function loadConversations() {
            var url = endpoints.list;
            if (!url) {
                return Promise.resolve();
            }

            var requestUrl = url;
            if (state.search) {
                var separator = url.indexOf('?') === -1 ? '?' : '&';
                requestUrl = url + separator + 'search=' + encodeURIComponent(state.search);
            }

            requestUrl = appendCacheBuster(requestUrl);

            return fetch(requestUrl, {
                headers: {
                    'Accept': 'application/json'
                },
                cache: 'no-store',
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                var nextConversations = [];
                if (payload && payload.ok && Array.isArray(payload.data)) {
                    nextConversations = payload.data;
                }

                notifyIncomingFromList(nextConversations);

                var nextSignature = computeConversationListSignature(nextConversations);
                if (state.conversationListSignature !== nextSignature) {
                    state.conversationListSignature = nextSignature;
                    state.conversations = nextConversations;
                    renderConversations();
                    return;
                }

                state.conversations = nextConversations;
            }).catch(function (error) {
                console.error('No fue posible cargar las conversaciones', error);
            });
        }

        function mapPresenceLabel(status) {
            if (status === 'available') {
                return 'Disponible';
            }
            if (status === 'away') {
                return 'Ausente';
            }
            if (status === 'offline') {
                return 'Desconectado';
            }
            return 'Disponible';
        }

        function loadMyPresence() {
            if (!agentPresenceSelect || !endpoints.agentPresence) {
                return Promise.resolve();
            }

            return fetch(appendCacheBuster(endpoints.agentPresence), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                cache: 'no-store',
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                if (!payload || !payload.ok || !payload.data) {
                    throw new Error(payload && payload.error ? payload.error : 'No se pudo cargar el estado.');
                }

                var status = String(payload.data.status || 'available');
                agentPresenceSelect.value = status;
                if (agentPresenceFeedback) {
                    agentPresenceFeedback.textContent = 'Estado actual: ' + mapPresenceLabel(status);
                }
            }).catch(function (error) {
                console.error('No fue posible cargar el estado del agente', error);
                if (agentPresenceFeedback) {
                    agentPresenceFeedback.textContent = 'No se pudo cargar el estado.';
                }
            });
        }

        function updateMyPresence(status) {
            if (!agentPresenceSelect || !endpoints.agentPresence) {
                return Promise.resolve();
            }

            agentPresenceSelect.disabled = true;
            if (agentPresenceFeedback) {
                agentPresenceFeedback.textContent = 'Actualizando estado...';
            }

            return fetch(endpoints.agentPresence, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({status: status})
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload || !payload.ok || !payload.data) {
                        var error = payload && payload.error ? payload.error : 'No se pudo actualizar el estado.';
                        throw new Error(error);
                    }
                    return payload.data;
                });
            }).then(function (data) {
                var updatedStatus = String(data.status || status || 'available');
                agentPresenceSelect.value = updatedStatus;
                if (agentPresenceFeedback) {
                    agentPresenceFeedback.textContent = 'Estado actual: ' + mapPresenceLabel(updatedStatus);
                }
            }).catch(function (error) {
                console.error('No se pudo actualizar el estado del agente', error);
                if (agentPresenceFeedback) {
                    agentPresenceFeedback.textContent = error.message || 'No se pudo actualizar el estado.';
                }
                return loadMyPresence();
            }).finally(function () {
                agentPresenceSelect.disabled = false;
            });
        }

        function openConversation(id, options) {
            if (!endpoints.conversation) {
                return Promise.resolve();
            }

            var opts = options || {};
            var silent = !!opts.silent;

            if (state.loadingConversation && !silent) {
                return Promise.resolve();
            }

            if (state.selectedId !== id) {
                state.selectedConversationSignature = '';
            }
            state.selectedId = id;
            state.forceScroll = !silent;

            if (!silent) {
                state.loadingConversation = true;
                toggleComposer(false);
                if (errorAlert) {
                    errorAlert.classList.add('d-none');
                }
                renderConversations();
            }

            var requestUrl = appendCacheBuster(getConversationEndpoint(id));

            return fetch(requestUrl, {
                headers: {
                    'Accept': 'application/json'
                },
                cache: 'no-store',
                credentials: 'same-origin'
            }).then(function (response) {
                if (!silent) {
                    state.loadingConversation = false;
                }
                return response.json();
            }).then(function (payload) {
                if (!payload || !payload.ok) {
                    throw new Error(payload && payload.error ? payload.error : 'Error desconocido');
                }

                var conversation = payload.data || {};
                var nextSignature = computeConversationSignature(conversation);
                if (silent && state.selectedConversationSignature === nextSignature) {
                    return;
                }

                state.selectedConversationSignature = nextSignature;
                updateHeader(payload.data);
                renderMessages(payload.data);
            }).catch(function (error) {
                if (!silent) {
                    state.loadingConversation = false;
                    state.selectedId = null;
                    state.selectedConversationSignature = '';
                    console.error('No fue posible cargar la conversación', error);
                    toggleComposer(true);
                    renderConversations();
                    if (errorAlert) {
                        errorAlert.textContent = error.message || 'No fue posible cargar la conversación seleccionada.';
                        errorAlert.classList.remove('d-none');
                    }
                } else {
                    console.error('No fue posible actualizar la conversación en segundo plano', error);
                }
            });
        }

        function sendMessage(payload) {
            if (!endpoints.send) {
                return Promise.reject(new Error('No hay un endpoint configurado para enviar mensajes.'));
            }

            if (payload instanceof FormData) {
                return fetch(endpoints.send, {
                    method: 'POST',
                    cache: 'no-store',
                    credentials: 'same-origin',
                    body: payload
                }).then(function (response) {
                    return response.json();
                }).then(function (data) {
                    if (!data.ok) {
                        throw new Error(data.error || 'No fue posible enviar el mensaje.');
                    }

                    return data.data || {};
                });
            }

            return fetch(endpoints.send, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                cache: 'no-store',
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (!data.ok) {
                    throw new Error(data.error || 'No fue posible enviar el mensaje.');
                }

                return data.data || {};
            });
        }

        if (newConversationForm) {
            newConversationForm.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!enabled || state.sending) {
                    return;
                }

                var formData = new FormData(newConversationForm);
                var waNumber = (formData.get('wa_number') || '').toString().trim();
                var displayName = (formData.get('display_name') || '').toString().trim();
                var message = (formData.get('message') || '').toString().trim();
                var preview = containsUrl(message);
                var useTemplate = templateToggle ? templateToggle.checked : false;
                var templatePayload = null;

                if (useTemplate) {
                    var templateId = templateSelect ? templateSelect.value : '';
                    if (!templateId || !templateState || !templateState.template) {
                        if (newConversationFeedback) {
                            newConversationFeedback.textContent = 'Selecciona una plantilla para continuar.';
                            newConversationFeedback.classList.remove('text-success');
                            newConversationFeedback.classList.add('text-danger');
                        }
                        return;
                    }

                    var templateBuild = buildTemplatePayload();
                    if (!templateBuild || templateBuild.error) {
                        if (newConversationFeedback) {
                            newConversationFeedback.textContent = templateBuild && templateBuild.error
                                ? templateBuild.error
                                : 'No fue posible preparar la plantilla.';
                            newConversationFeedback.classList.remove('text-success');
                            newConversationFeedback.classList.add('text-danger');
                        }
                        return;
                    }

                    templatePayload = templateBuild.payload;
                }

                if (!waNumber || (!message && !templatePayload)) {
                    if (newConversationFeedback) {
                        newConversationFeedback.textContent = 'Debes indicar un número y un mensaje o plantilla.';
                        newConversationFeedback.classList.remove('text-success');
                        newConversationFeedback.classList.add('text-danger');
                    }
                    return;
                }

                state.sending = true;
                state.forceScroll = true;
                if (newConversationFeedback) {
                    newConversationFeedback.textContent = 'Enviando mensaje...';
                    newConversationFeedback.classList.remove('text-danger');
                    newConversationFeedback.classList.add('text-muted');
                }

                var payload = {
                    wa_number: waNumber,
                    display_name: displayName,
                    message: message,
                    preview_url: preview
                };

                if (templatePayload) {
                    payload.template = templatePayload;
                }

                sendMessage(payload).then(function (result) {
                    if (newConversationFeedback) {
                        newConversationFeedback.textContent = 'Mensaje enviado correctamente.';
                        newConversationFeedback.classList.remove('text-danger');
                        newConversationFeedback.classList.add('text-success');
                    }

                    newConversationForm.reset();
                    if (templatePanel) {
                        templatePanel.classList.add('d-none');
                    }
                    if (templateToggle) {
                        templateToggle.checked = false;
                    }
                    templateState = null;
                    renderTemplateFields();
                    renderTemplatePreview();
                    loadConversations().then(function () {
                        if (result.conversation && result.conversation.id) {
                            openConversation(result.conversation.id);
                        }
                    });
                }).catch(function (error) {
                    console.error('No se pudo enviar el mensaje inicial', error);
                    if (newConversationFeedback) {
                        newConversationFeedback.textContent = error.message || 'No fue posible enviar el mensaje.';
                        newConversationFeedback.classList.remove('text-success');
                        newConversationFeedback.classList.add('text-danger');
                    }
                }).finally(function () {
                    state.sending = false;
                });
            });
        }

        if (messageForm) {
            messageForm.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!state.selectedId || state.sending) {
                    return;
                }

                var text = messageInput ? messageInput.value.trim() : '';
                var preview = containsUrl(text);

                if (!text && !pendingAttachment) {
                    if (errorAlert) {
                        errorAlert.textContent = 'El mensaje no puede estar vacío.';
                        errorAlert.classList.remove('d-none');
                    }
                    return;
                }

                if (!state.selectedHasInbound) {
                    if (errorAlert) {
                        errorAlert.textContent = 'Este contacto no ha iniciado conversación. Envía una plantilla aprobada desde la pestaña Nuevo.';
                        errorAlert.classList.remove('d-none');
                    }
                    return;
                }

                state.sending = true;
                state.forceScroll = true;
                if (errorAlert) {
                    errorAlert.classList.add('d-none');
                }

                var payload;
                if (pendingAttachment && attachmentInput && attachmentInput.files && attachmentInput.files[0]) {
                    payload = new FormData();
                    payload.append('conversation_id', String(state.selectedId));
                    payload.append('message', text);
                    payload.append('preview_url', preview ? '1' : '');
                    payload.append('attachment', attachmentInput.files[0]);
                } else {
                    payload = {
                        conversation_id: state.selectedId,
                        message: text,
                        preview_url: preview
                    };
                }

                sendMessage(payload).then(function () {
                    if (messageInput) {
                        messageInput.value = '';
                    }
                    clearAttachment();
                    loadConversations().then(function () {
                        openConversation(state.selectedId, {silent: true});
                    });
                }).catch(function (error) {
                    console.error('No fue posible enviar el mensaje', error);
                    if (errorAlert) {
                        errorAlert.textContent = error.message || 'Ocurrió un error al enviar el mensaje.';
                        errorAlert.classList.remove('d-none');
                    }
                }).finally(function () {
                    state.sending = false;
                });
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', debounce(function (event) {
                state.search = event.target.value.trim();
                loadConversations();
            }, 300));
        }

        if (agentPresenceSelect) {
            agentPresenceSelect.addEventListener('change', function () {
                var status = String(agentPresenceSelect.value || 'available');
                updateMyPresence(status);
            });
        }

        if (messageInput) {
            messageInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    if (messageForm) {
                        messageForm.dispatchEvent(new Event('submit', {cancelable: true}));
                    }
                }
            });
        }

        if (attachmentTrigger && attachmentInput) {
            attachmentTrigger.addEventListener('click', function () {
                if (attachmentInput.disabled) {
                    return;
                }
                attachmentInput.click();
            });

            attachmentInput.addEventListener('change', function () {
                if (attachmentInput.files && attachmentInput.files[0]) {
                    pendingAttachment = attachmentInput.files[0];
                    showAttachmentPreview(pendingAttachment);
                } else {
                    clearAttachment();
                }
            });
        }

        if (attachmentPreview) {
            attachmentPreview.addEventListener('click', function () {
                clearAttachment();
            });
        }

        if (patientSearchInput && patientResults) {
            patientSearchInput.addEventListener('input', debounce(function (event) {
                var query = event.target.value.trim();
                if (!query) {
                    patientResults.innerHTML = '';
                    patientResults.classList.add('d-none');
                    return;
                }
                loadPatients(query);
            }, 350));
        }

        if (templateSelect) {
            templateSelect.addEventListener('change', function () {
                var templateId = templateSelect.value;
                if (!templateId || !templateCache[templateId]) {
                    templateState = null;
                    renderTemplateFields();
                    renderTemplatePreview();
                    return;
                }

                renderTemplateEditor(templateCache[templateId]);
            });
        }

        if (templateToggle) {
            templateToggle.addEventListener('change', function () {
                if (!templatePanel) {
                    return;
                }
                if (templateToggle.checked) {
                    templatePanel.classList.remove('d-none');
                    if (!templatesLoaded) {
                        loadTemplates();
                    }
                    if (templateSelect && templateSelect.value && templateCache[templateSelect.value]) {
                        renderTemplateEditor(templateCache[templateSelect.value]);
                    }
                    var messageField = newConversationForm ? newConversationForm.querySelector('[name="message"]') : null;
                    if (messageField) {
                        messageField.removeAttribute('required');
                    }
                } else {
                    templatePanel.classList.add('d-none');
                    var messageFieldOff = newConversationForm ? newConversationForm.querySelector('[name="message"]') : null;
                    if (messageFieldOff) {
                        messageFieldOff.setAttribute('required', 'required');
                    }
                }
            });
        }

        if (openTemplateButton) {
            openTemplateButton.addEventListener('click', function () {
                openTemplateTab();
            });
        }

        function showAttachmentPreview(file) {
            if (!attachmentPreview) {
                return;
            }

            if (attachmentObjectUrl) {
                URL.revokeObjectURL(attachmentObjectUrl);
                attachmentObjectUrl = null;
            }

            attachmentPreview.innerHTML = '';
            if (!file) {
                attachmentPreview.classList.add('d-none');
                return;
            }

            var wrapper = createElement('div', 'd-flex align-items-center gap-2');
            var type = file.type || '';

            if (type.indexOf('image/') === 0) {
                attachmentObjectUrl = URL.createObjectURL(file);
                var img = document.createElement('img');
                img.src = attachmentObjectUrl;
                img.alt = file.name;
                img.className = 'whatsapp-attachment-thumb';
                wrapper.appendChild(img);
            } else {
                var iconClass = 'mdi mdi-paperclip text-primary';
                if (type.indexOf('pdf') !== -1) {
                    iconClass = 'mdi mdi-file-pdf-box text-danger';
                } else if (type.indexOf('word') !== -1) {
                    iconClass = 'mdi mdi-file-word-box text-primary';
                } else if (type.indexOf('audio') !== -1) {
                    iconClass = 'mdi mdi-microphone text-success';
                }
                var icon = createElement('i', iconClass);
                wrapper.appendChild(icon);
            }

            var info = createElement('div', 'd-flex flex-column');
            info.appendChild(createElement('div', 'fw-600', file.name));
            info.appendChild(createElement('div', 'text-muted small', formatFileSize(file.size)));
            wrapper.appendChild(info);

            var hint = createElement('div', 'ms-auto text-muted small', 'clic para quitar');
            wrapper.appendChild(hint);

            attachmentPreview.appendChild(wrapper);
            attachmentPreview.classList.remove('d-none');
        }

        function clearAttachment() {
            pendingAttachment = null;
            if (attachmentInput) {
                attachmentInput.value = '';
            }
            if (attachmentObjectUrl) {
                URL.revokeObjectURL(attachmentObjectUrl);
                attachmentObjectUrl = null;
            }
            showAttachmentPreview(null);
        }

        function formatFileSize(bytes) {
            var size = Number(bytes) || 0;
            if (!size) {
                return '0 B';
            }
            var units = ['B', 'KB', 'MB', 'GB'];
            var index = Math.min(Math.floor(Math.log(size) / Math.log(1024)), units.length - 1);
            var value = size / Math.pow(1024, index);
            return value.toFixed(value >= 10 || index === 0 ? 0 : 1) + ' ' + units[index];
        }

        function loadPatients(query) {
            if (!endpoints.patients) {
                return;
            }

            var url = endpoints.patients;
            var separator = url.indexOf('?') === -1 ? '?' : '&';
            var requestUrl = url + separator + 'search=' + encodeURIComponent(query);

            fetch(requestUrl, {
                headers: {
                    'Accept': 'application/json'
                },
                cache: 'no-store',
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                if (!patientResults) {
                    return;
                }

                if (!payload || !payload.ok || !Array.isArray(payload.data)) {
                    patientResults.innerHTML = '';
                    patientResults.classList.add('d-none');
                    return;
                }

                renderPatientResults(payload.data);
            }).catch(function () {
                if (patientResults) {
                    patientResults.innerHTML = '';
                    patientResults.classList.add('d-none');
                }
            });
        }

        function renderPatientResults(items) {
            if (!patientResults) {
                return;
            }

            patientResults.innerHTML = '';

            if (!items.length) {
                patientResults.classList.add('d-none');
                return;
            }

            items.forEach(function (item) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';

                var row = document.createElement('div');
                row.className = 'd-flex align-items-center justify-content-between gap-2';

                var left = document.createElement('div');
                left.className = 'd-flex align-items-center gap-2';

                var icon = document.createElement('i');
                icon.className = 'mdi mdi-account-circle text-primary fs-18';
                left.appendChild(icon);

                var info = document.createElement('div');
                var name = document.createElement('div');
                name.className = 'patient-name';
                name.textContent = item.full_name || 'Paciente';
                info.appendChild(name);

                var meta = document.createElement('div');
                meta.className = 'patient-meta';

                if (item.hc_number) {
                    var hcBadge = document.createElement('span');
                    hcBadge.className = 'badge badge-soft';
                    hcBadge.textContent = 'HC ' + item.hc_number;
                    meta.appendChild(hcBadge);
                }

                if (item.phone) {
                    var phoneBadge = document.createElement('span');
                    phoneBadge.className = 'badge badge-soft-success';
                    var phoneIcon = document.createElement('i');
                    phoneIcon.className = 'mdi mdi-phone me-1';
                    phoneBadge.appendChild(phoneIcon);
                    phoneBadge.appendChild(document.createTextNode(item.phone));
                    meta.appendChild(phoneBadge);
                } else {
                    var noPhoneBadge = document.createElement('span');
                    noPhoneBadge.className = 'badge badge-soft-muted';
                    noPhoneBadge.textContent = 'Sin teléfono';
                    meta.appendChild(noPhoneBadge);
                }

                info.appendChild(meta);
                left.appendChild(info);
                row.appendChild(left);

                var action = document.createElement('span');
                action.className = 'text-muted small';
                var actionIcon = document.createElement('i');
                actionIcon.className = 'mdi mdi-arrow-right';
                action.appendChild(actionIcon);
                row.appendChild(action);

                button.appendChild(row);

                button.addEventListener('click', function () {
                    var waNumberField = newConversationForm ? newConversationForm.querySelector('[name="wa_number"]') : null;
                    var nameField = newConversationForm ? newConversationForm.querySelector('[name="display_name"]') : null;
                    if (waNumberField && item.phone) {
                        waNumberField.value = item.phone;
                    }
                    if (nameField && item.full_name) {
                        nameField.value = item.full_name;
                    }
                    patientResults.innerHTML = '';
                    patientResults.classList.add('d-none');
                });

                patientResults.appendChild(button);
            });

            patientResults.classList.remove('d-none');
        }

        function buildFieldKey(type, index, placeholder) {
            return type + ':' + String(index) + ':' + String(placeholder);
        }

        function extractPlaceholders(text) {
            if (!text) {
                return [];
            }

            var matches = text.match(/\{\{\s*(\d+)\s*\}\}/g);
            if (!matches) {
                return [];
            }

            var values = {};
            matches.forEach(function (match) {
                var num = match.replace(/[^\d]/g, '');
                if (num) {
                    values[num] = true;
                }
            });

            return Object.keys(values).map(function (value) {
                return parseInt(value, 10);
            }).sort(function (a, b) {
                return a - b;
            });
        }

        function buildTemplateState(template) {
            var components = Array.isArray(template.components) ? template.components : [];
            var fields = [];
            var requirements = [];

            components.forEach(function (component, componentIndex) {
                if (!component || !component.type) {
                    return;
                }

                var type = String(component.type).toUpperCase();

                if (type === 'BODY' || type === 'HEADER' || type === 'FOOTER') {
                    var text = component.text || '';
                    var placeholders = extractPlaceholders(text);
                    if (placeholders.length) {
                        requirements.push({
                            type: type,
                            index: componentIndex,
                            subType: null,
                            placeholders: placeholders,
                            sourceText: text
                        });

                        placeholders.forEach(function (placeholder) {
                            var labelPrefix = type === 'BODY' ? 'Cuerpo' : (type === 'HEADER' ? 'Encabezado' : 'Pie');
                            fields.push({
                                key: buildFieldKey(type, componentIndex, placeholder),
                                label: labelPrefix + ' · Variable ' + placeholder,
                                type: type,
                                index: componentIndex,
                                placeholder: placeholder
                            });
                        });
                    }
                }

                if (type === 'BUTTONS') {
                    var buttons = Array.isArray(component.buttons) ? component.buttons : [];
                    buttons.forEach(function (button, buttonIndex) {
                        if (!button || !button.type) {
                            return;
                        }

                        var buttonType = String(button.type).toUpperCase();
                        if (buttonType !== 'URL') {
                            return;
                        }

                        var url = button.url || '';
                        var urlPlaceholders = extractPlaceholders(url);
                        if (!urlPlaceholders.length) {
                            return;
                        }

                        var buttonLabel = button.text || 'Botón ' + (buttonIndex + 1);
                        requirements.push({
                            type: 'BUTTON',
                            index: buttonIndex,
                            subType: 'URL',
                            placeholders: urlPlaceholders,
                            sourceText: url,
                            label: buttonLabel
                        });

                        urlPlaceholders.forEach(function (placeholder) {
                            fields.push({
                                key: buildFieldKey('BUTTON', buttonIndex, placeholder),
                                label: buttonLabel + ' · Variable ' + placeholder,
                                type: 'BUTTON',
                                index: buttonIndex,
                                placeholder: placeholder,
                                subType: 'URL'
                            });
                        });
                    });
                }
            });

            return {
                template: template,
                components: components,
                fields: fields,
                requirements: requirements
            };
        }

        function renderTemplateEditor(template) {
            templateState = buildTemplateState(template);
            renderTemplateFields();
            renderTemplatePreview();
        }

        function renderTemplateFields() {
            if (!templateFieldsContainer) {
                return;
            }

            templateFieldsContainer.innerHTML = '';
            templateFieldInputs = {};

            if (!templateState || !templateState.fields.length) {
                var empty = document.createElement('div');
                empty.className = 'small text-muted';
                empty.textContent = templateState ? 'Esta plantilla no requiere variables.' : 'Selecciona una plantilla.';
                templateFieldsContainer.appendChild(empty);
                return;
            }

            templateState.fields.forEach(function (field) {
                var wrapper = document.createElement('div');
                wrapper.className = 'mb-2';

                var label = document.createElement('label');
                label.className = 'form-label small';
                label.textContent = field.label;
                wrapper.appendChild(label);

                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm';
                input.setAttribute('data-template-field', field.key);
                input.addEventListener('input', renderTemplatePreview);
                wrapper.appendChild(input);

                templateFieldInputs[field.key] = input;
                templateFieldsContainer.appendChild(wrapper);
            });
        }

        function getTemplateFieldValue(type, index, placeholder) {
            var key = buildFieldKey(type, index, placeholder);
            var input = templateFieldInputs[key];
            if (!input) {
                return '';
            }
            return input.value.trim();
        }

        function applyTemplateText(text, type, index) {
            return String(text || '').replace(/\{\{\s*(\d+)\s*\}\}/g, function (match, num) {
                var value = getTemplateFieldValue(type, index, num);
                return value !== '' ? value : '[' + num + ']';
            });
        }

        function renderTemplatePreview() {
            if (!templatePreview) {
                return;
            }

            if (!templateState || !templateState.components) {
                templatePreview.textContent = 'Selecciona una plantilla para ver la vista previa.';
                return;
            }

            var lines = [];

            templateState.components.forEach(function (component, componentIndex) {
                if (!component || !component.type) {
                    return;
                }

                var type = String(component.type).toUpperCase();
                if (type === 'HEADER' && component.text) {
                    lines.push(applyTemplateText(component.text, 'HEADER', componentIndex));
                }
                if (type === 'BODY' && component.text) {
                    lines.push(applyTemplateText(component.text, 'BODY', componentIndex));
                }
                if (type === 'FOOTER' && component.text) {
                    lines.push(applyTemplateText(component.text, 'FOOTER', componentIndex));
                }
                if (type === 'BUTTONS' && Array.isArray(component.buttons)) {
                    var buttonLabels = component.buttons.map(function (button, buttonIndex) {
                        if (!button || !button.text) {
                            return 'Botón ' + (buttonIndex + 1);
                        }
                        return button.text;
                    });
                    if (buttonLabels.length) {
                        lines.push('Botones: ' + buttonLabels.join(' · '));
                    }
                }
            });

            if (!lines.length) {
                templatePreview.textContent = 'Vista previa no disponible para esta plantilla.';
                return;
            }

            templatePreview.textContent = lines.join('\n');
        }

        function buildTemplatePayload() {
            if (!templateState || !templateState.template) {
                return null;
            }

            var components = [];
            var errors = [];

            templateState.requirements.forEach(function (requirement) {
                var params = [];
                requirement.placeholders.forEach(function (placeholder) {
                    var value = getTemplateFieldValue(requirement.type, requirement.index, placeholder);
                    if (!value) {
                        errors.push('Completa la variable ' + placeholder + ' de ' + (requirement.label || requirement.type));
                    } else {
                        params.push({ type: 'text', text: value });
                    }
                });

                if (params.length) {
                    var entry = { type: requirement.type };
                    if (requirement.subType) {
                        entry.sub_type = requirement.subType;
                    }
                    if (requirement.type === 'BUTTON' && requirement.index !== null && typeof requirement.index !== 'undefined') {
                        entry.index = requirement.index;
                    }
                    entry.parameters = params;
                    components.push(entry);
                }
            });

            if (errors.length) {
                return { error: errors[0] };
            }

            var template = templateState.template;
            var payload = {
                name: template.name,
                language: template.language
            };

            if (components.length) {
                payload.components = components;
            }

            return { payload: payload };
        }

        function loadTemplates() {
            if (!endpoints.templates || !templateSelect) {
                return;
            }

            var url = new URL(endpoints.templates, window.location.origin);
            url.searchParams.set('limit', '250');

            fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json'
                },
                cache: 'no-store',
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                if (!payload || !payload.ok || !Array.isArray(payload.data)) {
                    return;
                }

                templateCache = {};
                templateSelect.innerHTML = '<option value="">Selecciona una plantilla</option>';
                payload.data.forEach(function (template) {
                    if (!template || !template.id) {
                        return;
                    }
                    templateCache[template.id] = template;
                    var option = document.createElement('option');
                    option.value = template.id;
                    option.textContent = template.name + ' (' + template.language + ')';
                    templateSelect.appendChild(option);
                });
                templatesLoaded = true;
            }).catch(function () {});
        }

        if (copyNumberButton) {
            copyNumberButton.addEventListener('click', function () {
                if (!selectedNumber) {
                    return;
                }
                copyToClipboard(selectedNumber);
            });
        }

        if (takeConversationButton) {
            takeConversationButton.addEventListener('click', function () {
                if (!state.selectedId || !endpoints.assign) {
                    return;
                }

                assignConversation(state.selectedId, currentUserId);
            });
        }

        if (transferButton) {
            transferButton.addEventListener('click', function () {
                if (!state.selectedId || !endpoints.transfer || !transferSelect) {
                    return;
                }

                var targetId = parseInt(transferSelect.value, 10) || 0;
                if (!targetId) {
                    return;
                }

                var note = transferNoteInput ? transferNoteInput.value.trim() : '';
                transferConversation(state.selectedId, targetId, note);
            });
        }

        if (closeConversationButton) {
            closeConversationButton.addEventListener('click', function () {
                if (!state.selectedId || !endpoints.close) {
                    return;
                }

                var shouldClose = window.confirm('¿Deseas cerrar esta conversación? Se limpiará la asignación y se marcará como atendida.');
                if (!shouldClose) {
                    return;
                }

                closeConversation(state.selectedId).catch(function (error) {
                    if (errorAlert) {
                        errorAlert.textContent = error.message || 'No fue posible cerrar la conversación.';
                        errorAlert.classList.remove('d-none');
                    }
                });
            });
        }

        if (deleteConversationButton) {
            deleteConversationButton.addEventListener('click', function () {
                if (!state.selectedId || !endpoints.remove) {
                    return;
                }

                var conversationId = state.selectedId;
                var shouldDelete = window.confirm('¿Eliminar la conversación y su historial? Esta acción no se puede deshacer.');
                if (!shouldDelete) {
                    return;
                }

                deleteConversation(conversationId).then(function () {
                    state.conversations = state.conversations.filter(function (item) {
                        return item.id !== conversationId;
                    });
                    resetConversationView();
                }).catch(function (error) {
                    if (errorAlert) {
                        errorAlert.textContent = error.message || 'No fue posible eliminar la conversación.';
                        errorAlert.classList.remove('d-none');
                    }
                });
            });
        }

        var refreshTimerId = null;
        var isRefreshing = false;
        var refreshBaseMs = 5000;
        var refreshMaxMs = 30000;
        var refreshIntervalMs = refreshBaseMs;

        function scheduleRefresh(delay) {
            if (refreshTimerId !== null) {
                window.clearTimeout(refreshTimerId);
            }

            refreshTimerId = window.setTimeout(runAutoRefresh, delay);
        }

        function runAutoRefresh() {
            if (document.hidden) {
                scheduleRefresh(refreshIntervalMs);
                return;
            }

            if (isRefreshing) {
                scheduleRefresh(refreshIntervalMs);
                return;
            }

            isRefreshing = true;

            var promises = [loadConversations()];
            var isPlayingAudio = isAudioPlaybackActive();
            if (state.selectedId && !isPlayingAudio) {
                promises.push(openConversation(state.selectedId, {silent: true}));
            }

            Promise.all(promises).then(function () {
                refreshIntervalMs = refreshBaseMs;
            }).catch(function (error) {
                console.error('Error durante la actualización automática del chat', error);
                refreshIntervalMs = Math.min(refreshMaxMs, Math.round(refreshIntervalMs * 1.7));
            }).finally(function () {
                isRefreshing = false;
                scheduleRefresh(refreshIntervalMs);
            });
        }

        function isAudioPlaybackActive() {
            if (!messageContainer) {
                return false;
            }

            var audios = messageContainer.querySelectorAll('audio');
            for (var i = 0; i < audios.length; i++) {
                var audio = audios[i];
                if (audio && !audio.paused && !audio.ended) {
                    return true;
                }
            }

            return false;
        }

        function startAutoRefresh() {
            if (refreshTimerId !== null) {
                return;
            }

            refreshIntervalMs = refreshBaseMs;
            scheduleRefresh(refreshIntervalMs);
        }

        function stopAutoRefresh() {
            if (refreshTimerId !== null) {
                window.clearTimeout(refreshTimerId);
                refreshTimerId = null;
            }
        }

        window.addEventListener('beforeunload', stopAutoRefresh);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });

        setupRealtime();
        toggleComposer(true);
        if (canAssign) {
            fetchAgents();
        }
        loadMyPresence();
        loadConversations();
        startAutoRefresh();
    });
})();
