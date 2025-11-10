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

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('whatsapp-chat-root');
        if (!root) {
            return;
        }

        var state = {
            selectedId: null,
            conversations: [],
            search: '',
            loadingConversation: false,
            sending: false
        };

        var endpoints = {
            list: root.getAttribute('data-endpoint-list') || '',
            conversation: root.getAttribute('data-endpoint-conversation') || '',
            send: root.getAttribute('data-endpoint-send') || ''
        };

        var enabled = root.getAttribute('data-enabled') === '1';

        var listContainer = root.querySelector('[data-conversation-list]');
        var emptyListState = root.querySelector('[data-empty-state]');
        var messageContainer = root.querySelector('[data-chat-messages]');
        var emptyChatState = root.querySelector('[data-chat-empty]');
        var header = root.querySelector('[data-chat-header]');
        var subtitle = header ? header.querySelector('[data-chat-subtitle]') : null;
        var titleElement = root.querySelector('[data-chat-title]');
        var lastSeenElement = root.querySelector('[data-chat-last-seen]');
        var unreadIndicator = root.querySelector('[data-unread-indicator]');
        var composer = root.querySelector('[data-chat-composer]');
        var messageForm = root.querySelector('[data-message-form]');
        var messageInput = root.querySelector('#chatMessage');
        var previewCheckbox = root.querySelector('#chatPreview');
        var errorAlert = root.querySelector('[data-chat-error]');
        var searchInput = root.querySelector('[data-conversation-search]');
        var newConversationForm = root.querySelector('[data-new-conversation-form]');
        var newConversationFeedback = root.querySelector('[data-new-conversation-feedback]');
        var detailName = root.querySelector('[data-detail-name]');
        var detailNumber = root.querySelector('[data-detail-number]');
        var detailPatient = root.querySelector('[data-detail-patient]');
        var detailHc = root.querySelector('[data-detail-hc]');
        var detailLast = root.querySelector('[data-detail-last]');
        var detailUnread = root.querySelector('[data-detail-unread]');

        function getConversationEndpoint(id) {
            return endpoints.conversation.replace('{id}', String(id));
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
                    var icon = createElement('i', 'mdi mdi-account text-primary');
                    avatarEl.appendChild(icon);
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

            resetContainer(messageContainer, emptyChatState);

            if (!data || !data.messages || !data.messages.length) {
                if (emptyChatState) {
                    emptyChatState.classList.remove('d-none');
                }
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

                // Card container with left/right float and background per sample
                var cardClass = isOutbound
                    ? 'card d-inline-block mb-3 float-end me-2 bg-primary max-w-p80'
                    : 'card d-inline-block mb-3 float-start me-2 no-shadow bg-lighter max-w-p80';
                var card = createElement('div', cardClass);

                // Absolute timestamp at top-right
                var stampWrap = createElement('div', 'position-absolute pt-1 pe-2 r-0');
                var stamp = createElement('span', 'text-extra-small' + (isOutbound ? '' : ' text-muted'), formatTime(message.timestamp));
                stampWrap.appendChild(stamp);
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
                } else {
                    // Fallback avatar as a circle with icon
                    avatarEl = createElement('span', 'avatar me-10 bg-primary-light d-inline-flex align-items-center justify-content-center');
                    var ic = createElement('i', 'mdi mdi-account text-primary');
                    avatarEl.appendChild(ic);
                }
                avatarLink.appendChild(avatarEl);
                headerRow.appendChild(avatarLink);

                var flexGrow = createElement('div', 'd-flex flex-grow-1 min-width-zero');
                var nameWrap = createElement('div', 'm-2 ps-0 align-self-center d-flex flex-column flex-lg-row justify-content-between');
                var inner = createElement('div', 'min-width-zero');
                var nameP = createElement('p', 'mb-0 fs-16' + (isOutbound ? '' : ' text-dark'));
                var senderName = message.sender_name || (isOutbound ? 'Tú' : (data.patient_full_name || data.display_name || data.wa_number || 'Contacto'));
                nameP.textContent = senderName;
                inner.appendChild(nameP);
                nameWrap.appendChild(inner);
                flexGrow.appendChild(nameWrap);
                headerRow.appendChild(flexGrow);

                body.appendChild(headerRow);

                // Message text block with left padding (ps-55)
                var textWrap = createElement('div', 'chat-text-start ps-55');
                var paragraph = createElement('p', 'mb-0 text-semi-muted');
                if (message.body) {
                    paragraph.textContent = message.body;
                } else {
                    paragraph.textContent = '[Contenido sin vista previa]';
                }
                textWrap.appendChild(paragraph);
                body.appendChild(textWrap);

                card.appendChild(body);
                messageContainer.appendChild(card);
                messageContainer.appendChild(createElement('div', 'clearfix'));
            });

            // Scroll to bottom after rendering
            messageContainer.scrollTop = messageContainer.scrollHeight;
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

            if (lastSeenElement) {
                if (conversation.last_message_at) {
                    lastSeenElement.textContent = 'Último mensaje: ' + formatDate(conversation.last_message_at);
                } else {
                    lastSeenElement.textContent = '';
                }
            }

            var summary = state.conversations.find(function (item) {
                return item.id === conversation.id;
            });

            if (unreadIndicator) {
                var unreadCount = summary && summary.unread_count ? summary.unread_count : 0;
                if (unreadCount > 0) {
                    unreadIndicator.textContent = unreadCount + ' sin leer';
                    unreadIndicator.classList.remove('d-none');
                } else {
                    unreadIndicator.classList.add('d-none');
                }
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
                if (payload && payload.ok && Array.isArray(payload.data)) {
                    state.conversations = payload.data;
                    renderConversations();
                } else {
                    state.conversations = [];
                    renderConversations();
                }
            }).catch(function (error) {
                console.error('No fue posible cargar las conversaciones', error);
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

            state.selectedId = id;

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

                updateHeader(payload.data);
                renderMessages(payload.data);
            }).catch(function (error) {
                if (!silent) {
                    state.loadingConversation = false;
                    state.selectedId = null;
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
                var preview = formData.get('preview_url') ? true : false;

                if (!waNumber || !message) {
                    if (newConversationFeedback) {
                        newConversationFeedback.textContent = 'Debes indicar un número y un mensaje inicial.';
                        newConversationFeedback.classList.remove('text-success');
                        newConversationFeedback.classList.add('text-danger');
                    }
                    return;
                }

                state.sending = true;
                if (newConversationFeedback) {
                    newConversationFeedback.textContent = 'Enviando mensaje...';
                    newConversationFeedback.classList.remove('text-danger');
                    newConversationFeedback.classList.add('text-muted');
                }

                sendMessage({
                    wa_number: waNumber,
                    display_name: displayName,
                    message: message,
                    preview_url: preview
                }).then(function (result) {
                    if (newConversationFeedback) {
                        newConversationFeedback.textContent = 'Mensaje enviado correctamente.';
                        newConversationFeedback.classList.remove('text-danger');
                        newConversationFeedback.classList.add('text-success');
                    }

                    newConversationForm.reset();
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
                var preview = previewCheckbox ? previewCheckbox.checked : false;

                if (!text) {
                    if (errorAlert) {
                        errorAlert.textContent = 'El mensaje no puede estar vacío.';
                        errorAlert.classList.remove('d-none');
                    }
                    return;
                }

                state.sending = true;
                if (errorAlert) {
                    errorAlert.classList.add('d-none');
                }

                sendMessage({
                    conversation_id: state.selectedId,
                    message: text,
                    preview_url: preview
                }).then(function () {
                    if (messageInput) {
                        messageInput.value = '';
                    }
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

        var autoRefreshId = null;
        var isRefreshing = false;

        function startAutoRefresh() {
            if (autoRefreshId !== null) {
                return;
            }

            autoRefreshId = window.setInterval(function () {
                if (isRefreshing) {
                    return;
                }

                isRefreshing = true;

                var promises = [loadConversations()];
                if (state.selectedId) {
                    promises.push(openConversation(state.selectedId, {silent: true}));
                }

                Promise.all(promises).catch(function (error) {
                    console.error('Error durante la actualización automática del chat', error);
                }).finally(function () {
                    isRefreshing = false;
                });
            }, 5000);
        }

        function stopAutoRefresh() {
            if (autoRefreshId !== null) {
                window.clearInterval(autoRefreshId);
                autoRefreshId = null;
            }
        }

        window.addEventListener('beforeunload', stopAutoRefresh);

        toggleComposer(true);
        loadConversations();
        startAutoRefresh();
    });
})();
