import { createNotificationPanel } from '../solicitudes/notifications/panel.js';

const realtimeConfig = window.MEDF_PusherConfig || {};
const runtimeConfig = window.__WHATSAPP_V2_NOTIFICATIONS__ || {};

const getNotificationPanel = () => {
    window.MEDF = window.MEDF || {};

    if (window.MEDF.notificationPanel) {
        return window.MEDF.notificationPanel;
    }

    const retentionDays = Number(realtimeConfig.panel_retention_days || 7);
    const panel = createNotificationPanel({
        panelId: 'kanbanNotificationPanel',
        backdropId: 'notificationPanelBackdrop',
        toggleSelector: '[data-notification-panel-toggle]',
        storageKey: `medf:notification-panel:whatsapp-v2:${String(runtimeConfig.scope || 'general')}`,
        retentionDays: Number.isFinite(retentionDays) && retentionDays >= 0 ? retentionDays : 7,
    });

    panel.setChannelPreferences({
        ...(window.MEDF.defaultNotificationChannels || {}),
        ...(realtimeConfig.channels || {}),
    });

    window.MEDF.notificationPanel = panel;

    return panel;
};

const getToastContainer = () => {
    let container = document.getElementById('whatsappV2ToastContainer');
    if (container) {
        return container;
    }

    container = document.createElement('div');
    container.id = 'whatsappV2ToastContainer';
    container.style.position = 'fixed';
    container.style.top = '1rem';
    container.style.right = '1rem';
    container.style.zIndex = '2055';
    container.style.display = 'flex';
    container.style.flexDirection = 'column';
    container.style.gap = '10px';
    container.style.pointerEvents = 'none';
    document.body.appendChild(container);

    return container;
};

const showToast = (message, tone = 'info') => {
    const text = String(message || '').trim();
    if (text === '') {
        return;
    }

    const container = getToastContainer();
    const toast = document.createElement('div');
    const palette = tone === 'danger'
        ? { bg: '#7f1d1d', border: '#ef4444' }
        : tone === 'warning'
            ? { bg: '#78350f', border: '#f59e0b' }
            : tone === 'success'
                ? { bg: '#064e3b', border: '#10b981' }
                : { bg: '#0f172a', border: '#38bdf8' };

    toast.setAttribute('role', 'status');
    toast.style.pointerEvents = 'auto';
    toast.style.minWidth = '280px';
    toast.style.maxWidth = '420px';
    toast.style.padding = '12px 14px';
    toast.style.borderRadius = '14px';
    toast.style.background = palette.bg;
    toast.style.border = `1px solid ${palette.border}`;
    toast.style.color = '#fff';
    toast.style.boxShadow = '0 16px 40px rgba(15, 23, 42, .18)';
    toast.style.fontSize = '13px';
    toast.style.lineHeight = '1.4';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-6px)';
    toast.style.transition = 'opacity .18s ease, transform .18s ease';
    toast.textContent = text;

    container.appendChild(toast);
    requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    });

    const dismissAfter = Number(realtimeConfig.toast_auto_dismiss_seconds || 4);
    const timeoutMs = Number.isFinite(dismissAfter) && dismissAfter > 0 ? dismissAfter * 1000 : 4000;
    window.setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-6px)';
        window.setTimeout(() => toast.remove(), 220);
    }, timeoutMs);
};

const maybeShowDesktopNotification = (title, body) => {
    if (!realtimeConfig.desktop_notifications || typeof window === 'undefined' || !('Notification' in window)) {
        return;
    }

    if (Notification.permission === 'default') {
        Notification.requestPermission().catch(() => {});
    }

    if (Notification.permission !== 'granted') {
        return;
    }

    const notification = new Notification(title, { body });
    const dismissAfter = Number(realtimeConfig.auto_dismiss_seconds || 0);
    if (Number.isFinite(dismissAfter) && dismissAfter > 0) {
        window.setTimeout(() => notification.close(), dismissAfter * 1000);
    }
};

const shouldNotifyForPayload = (payload) => {
    if (!payload || typeof payload !== 'object' || !payload.conversation) {
        return false;
    }

    const currentUserId = Number(window.MEDF?.currentUser?.id || 0);
    const currentConversationId = Number(runtimeConfig.currentConversationId || 0);
    const payloadConversationId = Number(payload.conversation.id || 0);
    const assignedUserId = Number(payload.conversation.assigned_user_id || 0);
    const canSupervise = Boolean(runtimeConfig.canSupervise);

    if (payloadConversationId > 0 && payloadConversationId === currentConversationId) {
        return false;
    }

    if (canSupervise) {
        return true;
    }

    if (assignedUserId <= 0) {
        return true;
    }

    return currentUserId > 0 && assignedUserId === currentUserId;
};

document.addEventListener('DOMContentLoaded', () => {
    const notificationPanel = getNotificationPanel();

    if (!realtimeConfig.enabled) {
        notificationPanel.setIntegrationWarning('Las notificaciones en tiempo real están desactivadas en Configuración → Notificaciones.');
        return;
    }

    if (typeof Pusher === 'undefined') {
        notificationPanel.setIntegrationWarning('Pusher no está disponible. Verifica que el script se haya cargado correctamente.');
        return;
    }

    if (!realtimeConfig.key) {
        notificationPanel.setIntegrationWarning('No se configuró la APP Key de Pusher en los ajustes.');
        return;
    }

    notificationPanel.setIntegrationWarning('');

    const options = { forceTLS: true };
    if (realtimeConfig.cluster) {
        options.cluster = realtimeConfig.cluster;
    }

    const pusher = new Pusher(realtimeConfig.key, options);
    const channel = pusher.subscribe(realtimeConfig.channel || 'whatsapp-ops');
    const events = realtimeConfig.events || {};
    const inboundEventName = events.inbound_message || realtimeConfig.event || 'whatsapp.inbound-message';
    const updateEventName = events.conversation_updated || 'whatsapp.conversation-updated';

    const handlePayload = (payload) => {
        if (!payload || typeof payload !== 'object') {
            return;
        }

        if (payload.notification) {
            notificationPanel.pushRealtime(payload.notification);
        }

        if (payload.pending_notification) {
            notificationPanel.pushPending(payload.pending_notification);
        }

        if (typeof window.MEDF?.whatsappChat?.handleRealtimeEvent === 'function') {
            window.MEDF.whatsappChat.handleRealtimeEvent(payload);
        }

        if (shouldNotifyForPayload(payload) && payload.notification) {
            const title = payload.notification.title || 'WhatsApp';
            const message = payload.notification.message || 'Tienes actividad nueva en WhatsApp.';
            showToast(`${title}: ${message}`, payload.notification.tone || 'info');
            maybeShowDesktopNotification(title, message);
        }
    };

    channel.bind(inboundEventName, handlePayload);
    channel.bind(updateEventName, handlePayload);
});
