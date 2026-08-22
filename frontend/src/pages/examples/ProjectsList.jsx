import React, {useEffect, useMemo, useState} from 'react';
import {Link} from 'react-router-dom';
import {Edit3, FolderPlus, Search} from 'lucide-react';
import {api} from '@/lib/api';
import {Alert, Button, Card, EmptyState, Field, Input, Modal, PageHeader, Textarea} from '@/components/ui';

const blankForm = { name: '', description: '' };

export default function ProjectsList() {
  const [projects, setProjects] = useState([]);
  const [form, setForm] = useState(blankForm);
  const [editingProject, setEditingProject] = useState(null);
  const [query, setQuery] = useState('');
  const [error, setError] = useState('');
  const [formError, setFormError] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);

  const load = async () => {
    setError('');
    try {
      const data = await api.request('/project', { method: 'GET' });
      setProjects(Array.isArray(data) ? data : []);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const filteredProjects = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) return projects;

    return projects.filter((project) => {
      const name = project.name?.toLowerCase() || '';
      const description = project.description?.toLowerCase() || '';
      return name.includes(needle) || description.includes(needle);
    });
  }, [projects, query]);

  const openCreate = () => {
    setEditingProject(null);
    setForm(blankForm);
    setFormError('');
    setModalOpen(true);
  };

  const openEdit = (project) => {
    setEditingProject(project);
    setForm({
      name: project.name || '',
      description: project.description || '',
    });
    setFormError('');
    setModalOpen(true);
  };

  const closeModal = () => {
    if (saving) return;
    setModalOpen(false);
    setFormError('');
  };

  const updateForm = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const saveProject = async (e) => {
    e.preventDefault();
    setFormError('');
    setSaving(true);

    try {
      const payload = {
        name: form.name.trim(),
        description: form.description.trim(),
      };
      if (editingProject) {
        await api.putJson('/project', { id: editingProject.id, ...payload });
      } else {
        await api.postJson('/project', payload);
      }

      setModalOpen(false);
      setForm(blankForm);
      setEditingProject(null);
      await load();
    } catch (err) {
      setFormError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const hasProjects = projects.length > 0;
  const hasSearch = query.trim().length > 0;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Projects"
        description="Manage example CRUD projects and open each project to work with tasks and notes."
        actions={
          <Button type="button" onClick={openCreate}>
            <FolderPlus size={16} />
            New project
          </Button>
        }
      />

      <Card className="overflow-hidden">
        <div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative w-full sm:max-w-sm">
            <Search className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
            <Input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search projects"
              className="pl-9"
              aria-label="Search projects"
            />
          </div>
          <div className="text-sm text-slate-500">
            {loading ? 'Loading...' : `${filteredProjects.length} of ${projects.length} projects`}
          </div>
        </div>

        <div className="p-4">
          {error && <Alert className="mb-4">{error}</Alert>}

          {loading && <p className="py-8 text-center text-sm text-slate-500">Loading projects...</p>}

          {!loading && !error && !hasProjects && (
            <EmptyState
              title="No projects yet"
              description="Create the first project to start exercising the example CRUD flow."
              action={
                <Button type="button" onClick={openCreate}>
                  <FolderPlus size={16} />
                  New project
                </Button>
              }
            />
          )}

          {!loading && !error && hasProjects && filteredProjects.length === 0 && (
            <EmptyState
              title="No matching projects"
              description="Try a different search term or clear the search to see all projects."
              action={
                hasSearch && (
                  <Button type="button" variant="outline" onClick={() => setQuery('')}>
                    Clear search
                  </Button>
                )
              }
            />
          )}

          {!loading && !error && filteredProjects.length > 0 && (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th scope="col" className="px-4 py-3 font-semibold">
                      Name
                    </th>
                    <th scope="col" className="px-4 py-3 font-semibold">
                      Description
                    </th>
                    <th scope="col" className="px-4 py-3 text-right font-semibold">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                  {filteredProjects.map((project) => (
                    <tr key={project.id} className="transition hover:bg-slate-50">
                      <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                        <Link to={`/projects/${project.id}`} className="text-brand hover:underline">
                          {project.name || 'Untitled project'}
                        </Link>
                      </td>
                      <td className="max-w-xl px-4 py-3 text-slate-600">
                        <span className="line-clamp-2">{project.description || 'No description'}</span>
                      </td>
                      <td className="whitespace-nowrap px-4 py-3 text-right">
                        <Button type="button" variant="outline" className="px-3 py-1.5" onClick={() => openEdit(project)}>
                          <Edit3 size={14} />
                          Edit
                        </Button>
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
        open={modalOpen}
        onClose={closeModal}
        title={editingProject ? 'Edit project' : 'New project'}
        description={editingProject ? 'Update the project details shown in the table.' : 'Create a project for the example CRUD workflow.'}
        footer={
          <>
            <Button type="button" variant="outline" onClick={closeModal} disabled={saving}>
              Cancel
            </Button>
            <Button type="submit" form="project-form" disabled={saving || !form.name.trim()}>
              {saving ? 'Saving...' : editingProject ? 'Save changes' : 'Create project'}
            </Button>
          </>
        }
      >
        <form id="project-form" onSubmit={saveProject} className="space-y-4">
          {formError && <Alert>{formError}</Alert>}
          <Field label="Name">
            <Input value={form.name} onChange={(e) => updateForm('name', e.target.value)} required autoFocus />
          </Field>
          <Field label="Description">
            <Textarea value={form.description} onChange={(e) => updateForm('description', e.target.value)} />
          </Field>
        </form>
      </Modal>
    </div>
  );
}
