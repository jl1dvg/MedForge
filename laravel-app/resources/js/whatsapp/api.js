/* MedForge — WhatsApp Chat v3 · API client
   Thin axios wrappers for /v2/whatsapp/api/* endpoints. */

import axios from 'axios';

const wa = axios.create({
  baseURL: '/v2/whatsapp/api',
  headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrfToken) wa.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;

// ── Conversations ────────────────────────────────────────────────────────────

export async function fetchConversations({ filter = 'all', search = '', page = 1, agentId, roleId } = {}) {
  const params = { filter, per_page: 25, page };
  if (search) params.search = search;
  if (agentId) params.agent_id = agentId;
  if (roleId) params.role_id = roleId;
  const { data } = await wa.get('/conversations', { params });
  return data; // { ok, data: [...], meta: { tab_counts, ... } }
}

export async function fetchConversation(id) {
  const { data } = await wa.get(`/conversations/${id}`);
  return data; // { ok, data: { ...conv, messages: [...] } }
}

export async function fetchTrail(conversationId) {
  const { data } = await wa.get(`/conversations/${conversationId}/trail`);
  return data; // { ok, data: [...] }
}

export async function fetchNotes(conversationId) {
  const { data } = await wa.get(`/conversations/${conversationId}/notes`);
  return data; // { ok, data: [...] }
}

// ── Messaging ────────────────────────────────────────────────────────────────

export async function sendMessage(conversationId, message, messageType = 'text') {
  const { data } = await wa.post(`/conversations/${conversationId}/messages`, {
    message,
    message_type: messageType,
  });
  return data; // { ok, data: serializedMessage }
}

export async function startWithTemplate({ waNumber, templateId, variables, contactName, patientHcNumber, patientFullName }) {
  const { data } = await wa.post('/conversations/start-template', {
    wa_number: waNumber,
    template_id: templateId,
    template_variables: variables || [],
    contact_name: contactName,
    patient_hc_number: patientHcNumber,
    patient_full_name: patientFullName,
  });
  return data;
}

export async function addNote(conversationId, body) {
  const { data } = await wa.post(`/conversations/${conversationId}/notes`, { body });
  return data;
}

// ── Operations ───────────────────────────────────────────────────────────────

export async function assignConversation(conversationId, userId) {
  const { data } = await wa.post(`/conversations/${conversationId}/assign`, { user_id: userId });
  return data;
}

export async function transferConversation(conversationId, userId, note = '') {
  const { data } = await wa.post(`/conversations/${conversationId}/transfer`, { user_id: userId, note });
  return data;
}

export async function queueByRole(conversationId, roleId, note = '') {
  const { data } = await wa.post(`/conversations/${conversationId}/queue-by-role`, { role_id: roleId, note });
  return data;
}

export async function closeConversation(conversationId) {
  const { data } = await wa.post(`/conversations/${conversationId}/close`);
  return data;
}

export async function requeueExpired() {
  const { data } = await wa.post('/handoffs/requeue-expired');
  return data;
}

// ── Agents & presence ────────────────────────────────────────────────────────

export async function fetchAgents() {
  const { data } = await wa.get('/agents');
  return data; // { ok, data: [{ id, name, role_id, role_name, presence_status }] }
}

export async function fetchAgentSummary() {
  const { data } = await wa.get('/agents/summary');
  return data;
}

export async function getPresence() {
  const { data } = await wa.get('/presence');
  return data;
}

export async function updatePresence(status) {
  const { data } = await wa.post('/presence', { status });
  return data;
}

// ── Productivity ─────────────────────────────────────────────────────────────

export async function fetchQuickReplies() {
  const { data } = await wa.get('/quick-replies');
  return data; // { ok, data: [{ id, title, body }] }
}

export async function fetchTemplates() {
  const { data } = await wa.get('/templates');
  return data; // { ok, data: [...] }
}

// ── Contacts ─────────────────────────────────────────────────────────────────

export async function searchContacts(q, limit = 15) {
  const { data } = await wa.get('/contacts/search', { params: { q, limit } });
  return data; // { ok, data: [{ name, wa_number, patient_hc_number }] }
}

// ── Media ────────────────────────────────────────────────────────────────────

export async function uploadMedia(file) {
  const form = new FormData();
  form.append('file', file);
  const { data } = await wa.post('/media/upload', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data; // { ok, data: { media_url, filename, mime_type, media_disk, media_path } }
}
