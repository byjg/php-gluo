import React, {useCallback, useEffect, useMemo, useState} from 'react';
import {Link, useParams} from 'react-router-dom';
import {ArrowLeft, Edit3, ListPlus, Search, StickyNote} from 'lucide-react';
import {api} from '@/lib/api';
import {Alert, Button, Card, EmptyState, Field, Input, Modal, PageHeader} from '@/components/ui';
import TaskNotes from '@/pages/examples/TaskNotes';

const STATUSES = ['open', 'in-progress', 'done'];
const blankTaskForm = { title: '', status: 'open' };

function statusLabel(status) {
  return status === 'in-progress' ? 'In progress' : status.charAt(0).toUpperCase() + status.slice(1);
}

function StatusBadge({ status }) {
  const normalized = STATUSES.includes(status) ? status : 'open';
  const classes = {
    open: 'bg-slate-100 text-slate-700',
    'in-progress': 'bg-amber-100 text-amber-800',
    done: 'bg-green-100 text-green-700',
  };

  return (
    <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${classes[normalized]}`}>
      {statusLabel(normalized)}
    </span>
  );
}

export default function ProjectDetail() {
  const { id } = useParams();
  const [project, setProject] = useState(null);
  const [tasks, setTasks] = useState([]);
  const [notes, setNotes] = useState([]);
  const [taskForm, setTaskForm] = useState(blankTaskForm);
  const [editingTask, setEditingTask] = useState(null);
  const [notesTask, setNotesTask] = useState(null);
  const [query, setQuery] = useState('');
  const [error, setError] = useState('');
  const [taskError, setTaskError] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [taskModalOpen, setTaskModalOpen] = useState(false);

  const loadProject = useCallback(async () => {
    const data = await api.request(`/project/${id}`, { method: 'GET' });
    setProject(data);
  }, [id]);

  const loadTasks = useCallback(async () => {
    const data = await api.request('/task', { method: 'GET' });
    setTasks(Array.isArray(data) ? data.filter((t) => String(t.projectId) === String(id)) : []);
  }, [id]);

  // Fetch every note in this project in one request. A note only carries a task_id,
  // so the API resolves note -> task -> project server-side (Note::getByProjectId via
  // joinWith(Task::class, Project::class)); we no longer pull all notes and filter here.
  const loadNotes = useCallback(async () => {
    try {
      const data = await api.request(`/project/${id}/note`, { method: 'GET' });
      setNotes(Array.isArray(data) ? data : []);
    } catch {
      setNotes([]);
    }
  }, [id]);

  const load = useCallback(async () => {
    setError('');
    setLoading(true);
    try {
      await Promise.all([loadProject(), loadTasks(), loadNotes()]);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [loadNotes, loadProject, loadTasks]);

  // task id -> that task's notes
  const notesByTask = useMemo(() => {
    const map = {};
    notes.forEach((note) => {
      if (!note.taskId) return;
      (map[note.taskId] ||= []).push(note);
    });
    return map;
  }, [notes]);

  useEffect(() => {
    load();
  }, [load]);

  const filteredTasks = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) return tasks;

    return tasks.filter((task) => {
      const title = task.title?.toLowerCase() || '';
      const status = task.status?.toLowerCase() || '';
      const id = task.id?.toLowerCase() || '';
      return title.includes(needle) || status.includes(needle) || id.includes(needle);
    });
  }, [tasks, query]);

  const openCreateTask = () => {
    setEditingTask(null);
    setTaskForm(blankTaskForm);
    setTaskError('');
    setTaskModalOpen(true);
  };

  const openEditTask = (task) => {
    setEditingTask(task);
    setTaskForm({
      title: task.title || '',
      status: STATUSES.includes(task.status) ? task.status : 'open',
    });
    setTaskError('');
    setTaskModalOpen(true);
  };

  const closeTaskModal = () => {
    if (saving) return;
    setTaskModalOpen(false);
    setTaskError('');
  };

  const updateTaskForm = (field, value) => {
    setTaskForm((current) => ({ ...current, [field]: value }));
  };

  const saveTask = async (e) => {
    e.preventDefault();
    setTaskError('');
    setSaving(true);

    try {
      const payload = {
        projectId: Number(id),
        title: taskForm.title.trim(),
        status: taskForm.status,
      };
      if (editingTask) {
        await api.putJson('/task', { id: editingTask.id, ...payload });
      } else {
        await api.postJson('/task', payload);
      }

      setTaskModalOpen(false);
      setTaskForm(blankTaskForm);
      setEditingTask(null);
      await loadTasks();
    } catch (err) {
      setTaskError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const hasTasks = tasks.length > 0;
  const hasSearch = query.trim().length > 0;

  return (
    <div className="space-y-6">
      <Link to="/projects" className="inline-flex items-center gap-1 text-sm text-brand hover:underline">
        <ArrowLeft size={14} />
        Back to projects
      </Link>

      <PageHeader
        title={project?.name || 'Project'}
        description={project?.description || 'Manage project tasks and related notes.'}
      />

      {error && <Alert>{error}</Alert>}

      <Card className="overflow-hidden">
        <div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="text-lg font-semibold text-slate-900">Tasks</h2>
            <p className="text-sm text-slate-500">Track work for this project by title and status.</p>
          </div>
          <Button type="button" onClick={openCreateTask}>
            <ListPlus size={16} />
            New task
          </Button>
        </div>

        <div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative w-full sm:max-w-sm">
            <Search className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
            <Input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search tasks"
              className="pl-9"
              aria-label="Search tasks"
            />
          </div>
          <div className="text-sm text-slate-500">
            {loading ? 'Loading...' : `${filteredTasks.length} of ${tasks.length} tasks`}
          </div>
        </div>

        <div className="p-4">
          {loading && <p className="py-8 text-center text-sm text-slate-500">Loading tasks...</p>}

          {!loading && !error && !hasTasks && (
            <EmptyState
              title="No tasks yet"
              description="Create the first task for this project. Notes can be attached after tasks exist."
              action={
                <Button type="button" onClick={openCreateTask}>
                  <ListPlus size={16} />
                  New task
                </Button>
              }
            />
          )}

          {!loading && !error && hasTasks && filteredTasks.length === 0 && (
            <EmptyState
              title="No matching tasks"
              description="Try a different search term or clear the search to see all tasks."
              action={
                hasSearch && (
                  <Button type="button" variant="outline" onClick={() => setQuery('')}>
                    Clear search
                  </Button>
                )
              }
            />
          )}

          {!loading && !error && filteredTasks.length > 0 && (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th scope="col" className="px-4 py-3 font-semibold">
                      Title
                    </th>
                    <th scope="col" className="px-4 py-3 font-semibold">
                      Status
                    </th>
                    <th scope="col" className="px-4 py-3 font-semibold">
                      UUID
                    </th>
                    <th scope="col" className="px-4 py-3 text-right font-semibold">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                  {filteredTasks.map((task) => (
                    <tr key={task.id} className="transition hover:bg-slate-50">
                      <td className="max-w-sm px-4 py-3 font-medium text-slate-900">
                        <span className="line-clamp-2">{task.title || 'Untitled task'}</span>
                      </td>
                      <td className="whitespace-nowrap px-4 py-3">
                        <StatusBadge status={task.status} />
                      </td>
                      <td className="max-w-48 truncate whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">
                        {task.id}
                      </td>
                      <td className="whitespace-nowrap px-4 py-3 text-right">
                        <div className="inline-flex gap-2">
                          <Button type="button" variant="outline" className="px-3 py-1.5" onClick={() => setNotesTask(task)}>
                            <StickyNote size={14} />
                            Notes
                            {(notesByTask[task.id]?.length ?? 0) > 0 && (
                              <span className="ml-1 rounded-full bg-brand/10 px-1.5 text-xs font-semibold text-brand">
                                {notesByTask[task.id].length}
                              </span>
                            )}
                          </Button>
                          <Button type="button" variant="outline" className="px-3 py-1.5" onClick={() => openEditTask(task)}>
                            <Edit3 size={14} />
                            Edit
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </Card>

      <Modal
        open={Boolean(notesTask)}
        onClose={() => setNotesTask(null)}
        title={notesTask ? `Notes · ${notesTask.title || 'Task'}` : 'Notes'}
        description="Notes attached to this task (ActiveRecord pattern)."
        footer={
          <Button type="button" variant="outline" onClick={() => setNotesTask(null)}>
            Close
          </Button>
        }
      >
        {notesTask && (
          <TaskNotes task={notesTask} notes={notesByTask[notesTask.id] || []} onChanged={loadNotes} />
        )}
      </Modal>

      <Modal
        open={taskModalOpen}
        onClose={closeTaskModal}
        title={editingTask ? 'Edit task' : 'New task'}
        description={editingTask ? 'Update the task title and status.' : 'Create a task for this project.'}
        footer={
          <>
            <Button type="button" variant="outline" onClick={closeTaskModal} disabled={saving}>
              Cancel
            </Button>
            <Button type="submit" form="task-form" disabled={saving || !taskForm.title.trim()}>
              {saving ? 'Saving...' : editingTask ? 'Save changes' : 'Create task'}
            </Button>
          </>
        }
      >
        <form id="task-form" onSubmit={saveTask} className="space-y-4">
          {taskError && <Alert>{taskError}</Alert>}
          <Field label="Title">
            <Input value={taskForm.title} onChange={(e) => updateTaskForm('title', e.target.value)} required autoFocus />
          </Field>
          <Field label="Status">
            <select
              value={taskForm.status}
              onChange={(e) => updateTaskForm('status', e.target.value)}
              className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-1 focus:ring-brand"
            >
              {STATUSES.map((status) => (
                <option key={status} value={status}>
                  {statusLabel(status)}
                </option>
              ))}
            </select>
          </Field>
        </form>
      </Modal>
    </div>
  );
}
