/* MedForge — WhatsApp Chat v3 · Messages + notes + trail hook */

import { useState, useEffect, useCallback, useRef } from 'react';
import { fetchConversation, fetchNotes, fetchTrail, sendMessage as apiSend, sendMediaMessage as apiSendMedia, addNote as apiAddNote } from '../api.js';
import { adaptMessage, buildThread } from '../adapt.js';

const POLL_MS = 15000;

export function useMessages(conversationId) {
  const [thread, setThread] = useState([]);
  const [notes, setNotes] = useState([]);
  const [trail, setTrail] = useState([]);
  const [loading, setLoading] = useState(false);
  const abortRef = useRef(null);

  useEffect(() => {
    if (!conversationId) {
      setThread([]); setNotes([]); setTrail([]);
      return;
    }

    abortRef.current?.abort();
    const ctrl = new AbortController();
    abortRef.current = ctrl;
    setLoading(true);

    Promise.all([
      fetchConversation(conversationId),
      fetchNotes(conversationId),
      fetchTrail(conversationId),
    ]).then(([convResult, notesResult, trailResult]) => {
      if (ctrl.signal.aborted) return;
      const rawMessages = convResult.data?.messages || [];
      setThread(buildThread(rawMessages));
      setNotes(notesResult.data || []);
      setTrail(trailResult.data || []);
    }).catch(err => {
      if (!ctrl.signal.aborted) console.error('[wa3] messages fetch error', err);
    }).finally(() => {
      if (!ctrl.signal.aborted) setLoading(false);
    });

    return () => ctrl.abort();
  }, [conversationId]);

  // Polling fallback (15s): refresh messages if Pusher doesn't deliver
  useEffect(() => {
    if (!conversationId) return;
    const id = setInterval(async () => {
      try {
        const result = await fetchConversation(conversationId);
        const rawMessages = result.data?.messages || [];
        setThread(prev => {
          const pending = prev.filter(m => String(m.id).startsWith('temp-'));
          const fresh = buildThread(rawMessages);
          return pending.length > 0 ? [...fresh, ...pending] : fresh;
        });
      } catch {}
    }, POLL_MS);
    return () => clearInterval(id);
  }, [conversationId]);

  const sendMessage = useCallback(async (text) => {
    if (!conversationId || !text.trim()) return;
    const tempId = `temp-${Date.now()}`;
    const time = new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
    setThread(prev => [...prev, { kind: 'msg', id: tempId, dir: 'out', body: text, time, status: 'pending' }]);
    try {
      const result = await apiSend(conversationId, text);
      if (result.ok && result.data) {
        const msgData = result.data.message || result.data;
        const sent = adaptMessage(msgData);
        setThread(prev => prev.map(m => m.id === tempId ? { ...sent, body: sent.body || text, status: 'sent' } : m));
      }
    } catch {
      setThread(prev => prev.map(m => m.id === tempId ? { ...m, status: 'failed' } : m));
    }
  }, [conversationId]);

  const sendMedia = useCallback(async (type, uploadedData, caption = '') => {
    if (!conversationId) return;
    const tempId = `temp-${Date.now()}`;
    const time = new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
    const mediaIcon = type === 'audio' ? 'mdi-microphone-outline'
                    : type === 'image' ? 'mdi-image-outline'
                    : type === 'video' ? 'mdi-video-outline'
                    : 'mdi-file-document-outline';
    setThread(prev => [...prev, {
      kind: 'msg', id: tempId, dir: 'out', body: caption || null, time, status: 'pending',
      media: {
        type: type === 'audio' ? 'audio' : 'file',
        name: uploadedData.filename || (type === 'audio' ? 'Nota de voz' : 'Archivo adjunto'),
        size: uploadedData.mime_type || '',
        icon: mediaIcon,
        downloadUrl: null,
      },
    }]);
    try {
      const result = await apiSendMedia(conversationId, type, uploadedData, caption);
      if (result.ok && result.data) {
        const msgData = result.data.message || result.data;
        const sent = adaptMessage(msgData);
        setThread(prev => prev.map(m => m.id === tempId ? { ...sent, status: 'sent' } : m));
      }
    } catch {
      setThread(prev => prev.map(m => m.id === tempId ? { ...m, status: 'failed' } : m));
    }
  }, [conversationId]);

  // Optimistic status progression: sent → delivered → read
  const markDelivered = useCallback((waMessageId) => {
    setThread(prev => prev.map(m =>
      m.wa_message_id === waMessageId ? { ...m, status: 'delivered' } : m
    ));
  }, []);

  const markRead = useCallback((waMessageId) => {
    setThread(prev => prev.map(m =>
      m.wa_message_id === waMessageId ? { ...m, status: 'read' } : m
    ));
  }, []);

  const appendInbound = useCallback((rawMessage) => {
    const adapted = adaptMessage(rawMessage);
    const time = new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
    const dateStr = new Date().toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' });
    setThread(prev => {
      const lastDate = [...prev].reverse().find(m => m.kind === 'date');
      const items = lastDate?.text === dateStr ? [] : [{ kind: 'date', text: dateStr }];
      return [...prev, ...items, { ...adapted, time }];
    });
  }, []);

  const addNote = useCallback(async (body) => {
    if (!conversationId || !body.trim()) return;
    try {
      const result = await apiAddNote(conversationId, body);
      const newNote = result.data || { author: 'Tú', at: 'ahora', body };
      setNotes(prev => [newNote, ...prev]);
    } catch (err) {
      console.error('[wa3] add note error', err);
      throw err;
    }
  }, [conversationId]);

  return { thread, notes, trail, loading, sendMessage, sendMedia, appendInbound, markDelivered, markRead, addNote };
}
