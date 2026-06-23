/* MedForge — WhatsApp Chat v3 · Conversation list hook */

import { useState, useEffect, useCallback, useRef } from 'react';
import { fetchConversations } from '../api.js';
import { adaptConversation } from '../adapt.js';

export function useConversations({ filter, search, agentId }) {
  const [convos, setConvos] = useState([]);
  const [tabCounts, setTabCounts] = useState({});
  const [loading, setLoading] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const pageRef = useRef(1);
  const abortRef = useRef(null);

  const reload = useCallback(async () => {
    abortRef.current?.abort();
    const ctrl = new AbortController();
    abortRef.current = ctrl;
    setLoading(true);
    pageRef.current = 1;
    try {
      const result = await fetchConversations({ filter, search, agentId, page: 1 });
      if (ctrl.signal.aborted) return;
      setConvos((result.data || []).map(adaptConversation));
      setTabCounts(result.meta?.tab_counts || {});
      setHasMore((result.meta?.current_page ?? 1) < (result.meta?.last_page ?? 1));
    } catch (err) {
      if (!ctrl.signal.aborted) console.error('[wa3] conversations fetch error', err);
    } finally {
      if (!ctrl.signal.aborted) setLoading(false);
    }
  }, [filter, search, agentId]);

  const loadMore = useCallback(async () => {
    if (loadingMore) return;
    const nextPage = pageRef.current + 1;
    setLoadingMore(true);
    try {
      const result = await fetchConversations({ filter, search, agentId, page: nextPage });
      pageRef.current = nextPage;
      setConvos(prev => [...prev, ...(result.data || []).map(adaptConversation)]);
      setHasMore(nextPage < (result.meta?.last_page ?? nextPage));
    } catch (err) {
      console.error('[wa3] load more error', err);
    } finally {
      setLoadingMore(false);
    }
  }, [filter, search, agentId, loadingMore]);

  useEffect(() => { reload(); }, [reload]);

  // Polling fallback (30s) in case Pusher events don't arrive
  useEffect(() => {
    const id = setInterval(reload, 30000);
    return () => clearInterval(id);
  }, [reload]);

  const patchConvo = useCallback((id, patch) => {
    setConvos(prev => prev.map(c => c.id === id ? { ...c, ...patch } : c));
  }, []);

  return { convos, setConvos, tabCounts, loading, loadingMore, hasMore, reload, loadMore, patchConvo };
}

