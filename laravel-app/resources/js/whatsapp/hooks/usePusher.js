/* MedForge — WhatsApp Chat v3 · Pusher real-time hook */

import { useEffect, useRef } from 'react';
import Pusher from 'pusher-js';

/**
 * Subscribes to the WhatsApp Pusher channel and calls onEvent(eventName, payload).
 * Events: 'inbound-message', 'conversation-updated'
 * Config comes from the Blade-injected wa3-config JSON.
 */
export function usePusher(config, onEvent) {
  const cbRef = useRef(onEvent);
  cbRef.current = onEvent;

  useEffect(() => {
    if (!config?.enabled || !config?.key) return;

    const pusher = new Pusher(config.key, {
      cluster: config.cluster || 'us2',
    });

    const channel = pusher.subscribe(config.channel || 'whatsapp-ops');
    channel.bind('whatsapp.inbound-message',      (d) => cbRef.current('inbound-message', d));
    channel.bind('whatsapp.conversation-updated', (d) => cbRef.current('conversation-updated', d));

    return () => {
      channel.unbind_all();
      pusher.unsubscribe(config.channel || 'whatsapp-ops');
      pusher.disconnect();
    };
  }, [config?.key, config?.cluster, config?.channel, config?.enabled]);
}
