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

            listContainer.innerHTML = '';

            if (!state.conversations.length) {
                if (emptyListState) {
                    emptyListState.classList.remove('d-none');
                }
                return;
            }

            if (emptyListState) {
                emptyListState.classList.add('d-none');
            }

            var list = createElement('div', 'list-group list-group-flush');

            state.conversations.forEach(function (conversation) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.setAttribute('data-id', conversation.id);

                var title = conversation.display_name || conversation.patient_full_name || conversation.wa_number;
                var subtitleText = conversation.wa_number;
                if (conversation.patient_full_name && conversation.patient_full_name !== title) {
                    subtitleText = conversation.patient_full_name + ' · ' + conversation.wa_number;
                }

                var content = createElement('div', 'd-flex w-100 justify-content-between align-items-start');
                var body = createElement('div', 'me-2');
                var heading = createElement('h6', 'mb-1 fw-600 text-start', title);
                body.appendChild(heading);
                body.appendChild(createElement('div', 'text-muted small text-start', subtitleText));

                if (conversation.last_message && conversation.last_message.preview) {
                    body.appendChild(createElement('div', 'small text-truncate text-start mt-1', conversation.last_message.preview));
                }

                content.appendChild(body);

                var meta = createElement('div', 'text-end');
                if (conversation.last_message && conversation.last_message.at) {
                    meta.appendChild(createElement('div', 'small text-muted', formatDate(conversation.last_message.at)));
                }

                if (conversation.unread_count > 0) {
                    var badge = createElement('span', 'badge bg-primary');
                    badge.textContent = conversation.unread_count;
                    meta.appendChild(badge);
                }

                content.appendChild(meta);
                item.appendChild(content);

                if (state.selectedId === conversation.id) {
                    item.classList.add('active');
                }

                item.addEventListener('click', function () {
                    if (state.loadingConversation) {
                        return;
                    }
                    openConversation(conversation.id);
                });

                list.appendChild(item);
            });

            listContainer.appendChild(list);
        }

        function renderMessages(data) {
            if (!messageContainer) {
                return;
            }

            messageContainer.innerHTML = '';

            if (!data || !data.messages || !data.messages.length) {
                if (emptyChatState) {
                    emptyChatState.classList.remove('d-none');
                }
                return;
            }

            if (emptyChatState) {
                emptyChatState.classList.add('d-none');
            }

            data.messages.forEach(function (message) {
                var isOutbound = message.direction === 'outbound';
                var cardClass = isOutbound
                    ? 'card d-inline-block mb-3 float-end me-2 bg-primary text-white max-w-p80'
                    : 'card d-inline-block mb-3 float-start me-2 no-shadow bg-lighter max-w-p80';

                var card = createElement('div', cardClass);
                var body = createElement('div', 'card-body');

                var headerRow = createElement('div', 'd-flex justify-content-between align-items-center mb-2');
                var senderName = isOutbound ? 'Tú' : 'Contacto';
                if (message.sender_name) {
                    senderName = message.sender_name;
                }
                headerRow.appendChild(createElement('span', 'fw-600' + (isOutbound ? ' text-white' : ''), senderName));
                headerRow.appendChild(createElement('span', 'small ' + (isOutbound ? 'text-white-50' : 'text-muted'), formatDate(message.timestamp)));
                body.appendChild(headerRow);

                if (message.body) {
                    var paragraph = createElement('p', 'mb-2' + (isOutbound ? ' text-white' : ' text-dark'));
                    paragraph.textContent = message.body;
                    body.appendChild(paragraph);
                } else {
                    var placeholderClass = 'mb-2 fst-italic' + (isOutbound ? ' text-white-50' : ' text-muted');
                    body.appendChild(createElement('p', placeholderClass, '[Contenido sin vista previa]'));
                }

                if (message.status) {
                    var footerRow = createElement('div', 'd-flex justify-content-end small ' + (isOutbound ? 'text-white-50' : 'text-muted'));
                    footerRow.appendChild(createElement('span', '', 'Estado: ' + message.status));
                    body.appendChild(footerRow);
                }

                card.appendChild(body);
                messageContainer.appendChild(card);
                messageContainer.appendChild(createElement('div', 'clearfix'));
            });

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
                return;
            }

            var requestUrl = url;
            if (state.search) {
                var separator = url.indexOf('?') === -1 ? '?' : '&';
                requestUrl = url + separator + 'search=' + encodeURIComponent(state.search);
            }

            fetch(requestUrl, {
                headers: {
                    'Accept': 'application/json'
                }
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
                return;
            }

            var opts = options || {};
            var silent = !!opts.silent;

            if (state.loadingConversation && !silent) {
                return;
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

            fetch(getConversationEndpoint(id), {
                headers: {
                    'Accept': 'application/json'
                }
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
                    loadConversations();

                    if (result.conversation && result.conversation.id) {
                        openConversation(result.conversation.id);
                    }
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
                    loadConversations();
                    openConversation(state.selectedId, { silent: true });
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

        function startAutoRefresh() {
            if (autoRefreshId !== null) {
                return;
            }

            autoRefreshId = window.setInterval(function () {
                loadConversations();
                if (state.selectedId) {
                    openConversation(state.selectedId, { silent: true });
                }
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
