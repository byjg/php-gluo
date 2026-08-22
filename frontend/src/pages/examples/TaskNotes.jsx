import React, {useState} from 'react';
import {api} from '@/lib/api';
import {Alert, Button, Input} from '@/components/ui';

/**
 * ActiveRecord example, scoped to a single task: lists that task's notes
 * (note.taskId === task.id) and adds new ones. Rendered inside a modal
 * so notes are managed in the context of the task they belong to.
 */
export default function TaskNotes({ task, notes, onChanged }) {
  const [body, setBody] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  const add = async (e) => {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      await api.postJson('/note', { taskId: task.id, body: body.trim() });
      setBody('');
      await onChanged();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-4">
      {error && <Alert>{error}</Alert>}

      <ul className="max-h-64 space-y-2 overflow-y-auto">
        {notes.length === 0 && <li className="text-sm text-slate-500">No notes yet for this task.</li>}
        {notes.map((note) => (
          <li key={note.id} className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
            <p className="whitespace-pre-wrap">{note.body}</p>
            {note.createdAt && <p className="mt-1 text-xs text-slate-400">{note.createdAt}</p>}
          </li>
        ))}
      </ul>

      <form onSubmit={add} className="flex items-center gap-2">
        <Input value={body} onChange={(e) => setBody(e.target.value)} placeholder="Add a note…" required />
        <Button type="submit" disabled={saving || !body.trim()}>
          {saving ? 'Adding…' : 'Add'}
        </Button>
      </form>
    </div>
  );
}
