/* MedForge — WhatsApp Chat v3 · Conversation list hook */

import { useState, useEffect, useCallback, useRef } from 'react';
import { fetchConversations } from '../api.js';
import { adaptConversation } from '../adapt.js';

export function useConversations({ filter, search, agentId }) {
  const [convos, setConvos] = useState([]);
  const [tabCounts, setTabCounts] = useState({});
  const [loading, setLoading] = useState(false);
  const abortRef = useRef(null);

  const reload = useCallback(async () => {
    abortRef.current?.abort();
    const ctrl = new AbortController();
    abortRef.current = ctrl;
    setLoading(true);
    try {
      const result = await fetchConversations({ filter, search, agentId });
      if (ctrl.signal.aborted) return;
      setConvos((result.data || []).map(adaptConversation));
      setTabCounts(result.meta?.tab_counts || {});
    } catch (err) {
      if (!ctrl.signal.aborted) console.error('[wa3] conversations fetch error', err);
    } finally {
      if (!ctrl.signal.aborted) setLoading(false);
    }
  }, [filter, search, agentId]);

  useEffect(() => { reload(); }, [reload]);

  // Patch a single conversation in the list without full refetch
  const patchConvo = useCallback((id, patch) => {
    setConvos(prev => prev.map(c => c.id === id ? { ...c, ...patch } : c));
  }, []);

  return { convos, setConvos, tabCounts, loading, reload, patchConvo };
}
