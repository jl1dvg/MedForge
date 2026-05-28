import React, { useState } from 'react';

interface Props {
  onSave: (type: string, description: string) => Promise<void>;
}

export function NoteForm({ onSave }: Props) {
  const [type, setType] = useState('nota');
  const [text, setText] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSubmit = async () => {
    if (!text.trim()) return;
    setSaving(true);
    await onSave(type, text.trim());
    setText('');
    setSaving(false);
  };

  return (
    <div>
      <div className="flex gap-2 mb-2">
        {[{ v: 'nota', l: 'Nota' }, { v: 'llamada', l: 'Llamada' }, { v: 'email', l: 'Email' }].map(({ v, l }) => (
          <button
            key={v}
            onClick={() => setType(v)}
            className={`text-xs px-2.5 py-1 rounded-lg border font-semibold transition-all
              ${type === v ? 'bg-slate-700 text-white border-slate-700' : 'bg-white text-slate-500 border-slate-200'}`}
          >
            {l}
          </button>
        ))}
      </div>
      <textarea
        className="w-full border border-slate-200 rounded-lg p-2.5 text-sm resize-none outline-none focus:border-blue-400"
        rows={3}
        placeholder="Que paso? Ej: Llame al paciente, quedo de confirmar la proxima semana..."
        value={text}
        onChange={e => setText(e.target.value)}
      />
      <button
        onClick={() => { void handleSubmit(); }}
        disabled={saving || !text.trim()}
        className="mt-2 w-full bg-blue-500 text-white text-sm font-semibold py-2 rounded-lg hover:bg-blue-600 disabled:opacity-50"
      >
        {saving ? 'Guardando...' : 'Guardar nota'}
      </button>
    </div>
  );
}
