/* MedForge — WhatsApp Chat v3 · API shape → component shape adapters */

const TONES = ['violet', 'green', 'amber', 'rose', 'blue', 'cyan'];

function pickTone(seed) {
  if (!seed) return 'violet';
  let h = 0;
  for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) & 0x7fffffff;
  return TONES[h % TONES.length];
}

function initials(name) {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

function fmtTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const now = new Date();
  const diffDays = Math.floor((now - d) / 86400000);
  if (diffDays === 0) return d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
  if (diffDays === 1) return 'Ayer';
  if (diffDays <= 6) return ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][d.getDay()];
  return d.toLocaleDateString('es', { day: '2-digit', month: 'short' });
}

function deriveBuckets(c) {
  const b = [];
  if (c.closed_at) { b.push('closed'); return b; }
  if (!c.needs_human) return b;
  if (!c.assigned_user_id) b.push('requires_attention');
  if (c.ownership_state === 'owned') b.push('mine');
  if (c.assigned_user_id) {
    if (c.last_message_direction === 'inbound') b.push('in_progress');
    else b.push('waiting_patient');
  }
  if (c.unread_count > 0) b.push('unread');
  if (c.messaging_window_state === 'window_open') b.push('window_open');
  else b.push('needs_template');
  if (c.queue_bucket) b.push(c.queue_bucket);
  return b;
}

/* Conversation API response → component shape */
export function adaptConversation(c) {
  return {
    id: c.id,
    name: c.display_name || c.wa_number || '—',
    wa: c.wa_number || '',
    hc: c.patient_hc_number || null,
    preview: c.last_message_preview || '',
    time: fmtTime(c.last_message_at),
    unread: c.unread_count || 0,
    unreadFlag: (c.unread_count || 0) > 0,
    status: c.closed_at ? null : (c.unread_count > 0 ? 'urgent' : 'open'),
    tone: pickTone(c.wa_number),
    initials: initials(c.display_name || c.wa_number),
    bucket: c.operational_status || 'in_progress',
    buckets: deriveBuckets(c),
    window: c.messaging_window_state === 'window_open' ? 'open' : 'template',
    canSend: c.can_send_freeform || false,
    priority: c.priority_level_label || 'Media',
    opStatus: c.operational_status_label || '—',
    lastActor: c.last_message_actor_label || 'Paciente',
    queue: c.queue_bucket_label || null,
    attribution: c.attribution_headline || null,
    assignedTo: c.assigned_user_name || null,
    assignedRole: c.assigned_role_name || null,
    isMine: c.ownership_state === 'owned',
    patient: {
      name: c.patient_full_name || c.display_name || '',
      age: null,
      dx: null,
      nextAppt: null,
    },
    // raw IDs needed for API calls
    assignedUserId: c.assigned_user_id || null,
    handoffRoleId: c.handoff_role_id || null,
  };
}

/* Message API response → component thread item */
export function adaptMessage(m) {
  const ts = m.message_timestamp || m.sent_at || null;
  const time = ts
    ? new Date(ts).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })
    : '';

  let media = null;
  if (m.media) {
    const isAudio = m.message_type === 'audio' || m.media.voice;
    const isImage = m.message_type === 'image';
    media = {
      type: isAudio ? 'audio' : 'file',
      name: m.media.filename || (isAudio ? 'Nota de voz' : 'Archivo adjunto'),
      size: m.media.mime_type || '',
      icon: isImage ? 'mdi-image-outline' : 'mdi-file-document-outline',
      downloadUrl: m.media.download_url || null,
    };
  }

  return {
    kind: 'msg',
    id: m.id,
    dir: m.direction === 'outbound' ? 'out' : 'in',
    body: m.body || null,
    time,
    status: m.status || null,
    media,
  };
}

/* Build date-separated thread from adapted messages */
export function buildThread(messages) {
  const items = [];
  let lastDate = null;
  for (const m of messages) {
    const ts = m.message_timestamp || m.sent_at;
    const d = ts ? new Date(ts) : null;
    const dateStr = d ? d.toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' }) : 'Hoy';
    if (dateStr !== lastDate) {
      items.push({ kind: 'date', text: dateStr });
      lastDate = dateStr;
    }
    items.push(adaptMessage(m));
  }
  return items;
}

/* Agent API response → component shape */
export function adaptAgent(a) {
  return {
    id: a.id,
    name: a.name || '—',
    role: a.role_name || '—',
    role_id: a.role_id || null,
    status: a.presence_status === 'available' ? 'online' : a.presence_status === 'busy' ? 'busy' : 'away',
    active: a.open_conversations || 0,
    resolved: a.resolved_today || 0,
    avgResp: a.avg_response_time || '—',
    tone: pickTone(a.name),
    initials: initials(a.name),
    isMe: a.is_me || false,
  };
}

/* Derive role list from agents array */
export function rolesFromAgents(agents) {
  const seen = new Map();
  agents.forEach(a => {
    if (a.role_id && a.role && !seen.has(a.role_id)) {
      seen.set(a.role_id, { id: a.role_id, name: a.role, open: 0, icon: 'mdi-account-group-outline' });
    }
  });
  return Array.from(seen.values());
}
