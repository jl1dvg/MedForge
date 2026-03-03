#!/usr/bin/env node

const args = process.argv.slice(2);
const options = {};
for (let i = 0; i < args.length; i += 1) {
  const arg = args[i];
  if (!arg.startsWith('--')) continue;
  const key = arg.slice(2);
  const value = (i + 1 < args.length && !args[i + 1].startsWith('--')) ? args[i + 1] : '';
  options[key] = value;
  if (value !== '') i += 1;
}

const title = String(options.title || '').trim();
if (!title) {
  console.error('Uso: create_task.mjs --title "..." [--project MedForge] [--status Hecho] [--priority Alta] [--module WhatsApp] [--date 2026-03-03] [--notes "..."] [--commit d10bbcb9] [--responsible Patricio]');
  process.exit(1);
}

const token = process.env.NOTION_TOKEN || '';
const databaseId = process.env.NOTION_DATABASE_ID || '';
if (!token || !databaseId) {
  console.error('Faltan variables de entorno NOTION_TOKEN y/o NOTION_DATABASE_ID.');
  process.exit(1);
}

const project = String(options.project || 'MedForge').trim();
const status = String(options.status || 'Pendiente').trim();
const priority = String(options.priority || 'Media').trim();
const moduleName = String(options.module || 'General').trim();
const date = String(options.date || new Date().toISOString().slice(0, 10)).trim();
const notes = String(options.notes || '').trim();
const commit = String(options.commit || '').trim();
const responsible = String(options.responsible || 'Patricio').trim();

const payload = {
  parent: { database_id: databaseId },
  properties: {
    Tarea: { title: [{ text: { content: title } }] },
    Proyecto: { select: { name: project } },
    Estado: { status: { name: status } },
    Prioridad: { select: { name: priority } },
    'Módulo': { rich_text: moduleName ? [{ text: { content: moduleName } }] : [] },
    Fecha: { date: { start: date } },
    'Notas técnicas': { rich_text: notes ? [{ text: { content: notes } }] : [] },
    Commit: { rich_text: commit ? [{ text: { content: commit } }] : [] },
    Responsable: { select: { name: responsible } },
  },
};

const res = await fetch('https://api.notion.com/v1/pages', {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${token}`,
    'Notion-Version': '2022-06-28',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify(payload),
});

const data = await res.json().catch(() => ({}));
if (!res.ok) {
  console.error(`Notion API error (${res.status}): ${data?.message || 'Error desconocido'}`);
  console.error(JSON.stringify(data, null, 2));
  process.exit(1);
}

console.log('NOTION_TASK_CREATED');
console.log(`id=${data.id || ''}`);
console.log(`url=${data.url || ''}`);
