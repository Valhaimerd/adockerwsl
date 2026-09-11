<script setup>
import { onMounted, reactive, ref } from 'vue';

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? '';
const project = { name: meta('project-name'), subtitle: meta('project-subtitle'), description: meta('project-description'), unifyUrl: meta('unify-url') };
const csrf = meta('csrf-token');
const records = ref([]);
const loading = ref(true);
const error = ref('');
const editingId = ref(null);
const form = reactive({ code: '', title: '', instructor: '' });

async function request(url, options = {}) {
    const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers ?? {}) } });
    if (response.status === 204) return null;
    const payload = await response.json();
    if (!response.ok) throw new Error(Object.values(payload.errors ?? {}).flat().join(' ') || 'Request failed.');
    return payload;
}

async function load() { try { records.value = (await request('/api/courses')).data; error.value = ''; } catch (exception) { error.value = exception.message; } finally { loading.value = false; } }
function reset() { Object.assign(form, { code: '', title: '', instructor: '' }); editingId.value = null; error.value = ''; }
function edit(record) { Object.assign(form, { code: record.code, title: record.title, instructor: record.instructor }); editingId.value = record.id; window.scrollTo({ top: 0, behavior: 'smooth' }); }
async function submit() { try { const url = editingId.value ? `/api/courses/${editingId.value}` : '/api/courses'; await request(url, { method: editingId.value ? 'PUT' : 'POST', body: JSON.stringify(form) }); reset(); await load(); } catch (exception) { error.value = exception.message; } }
async function remove(record) { if (!window.confirm(`Delete ${record.code}?`)) return; try { await request(`/api/courses/${record.id}`, { method: 'DELETE' }); await load(); } catch (exception) { error.value = exception.message; } }
onMounted(load);
</script>

<template>
    <main class="min-h-screen bg-base-200 px-4 py-8 md:px-6"><section class="mx-auto max-w-5xl space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold uppercase tracking-widest text-primary">{{ project.name }}</p><h1 class="text-3xl font-bold">{{ project.subtitle }}</h1><p class="mt-1 text-base-content/65">{{ project.description }}</p></div><a :href="project.unifyUrl" class="btn btn-outline btn-sm">← Back to Unify</a></header>
        <form class="card border border-base-300 bg-base-100 shadow-sm" @submit.prevent="submit"><div class="card-body"><h2 class="card-title text-lg">{{ editingId ? 'Edit course' : 'Add course' }}</h2><div class="grid gap-3 md:grid-cols-3"><input v-model="form.code" required maxlength="20" class="input input-bordered w-full" placeholder="Course code"><input v-model="form.title" required maxlength="150" class="input input-bordered w-full" placeholder="Course title"><input v-model="form.instructor" required maxlength="100" class="input input-bordered w-full" placeholder="Instructor"></div><p v-if="error" class="text-sm text-error">{{ error }}</p><div class="card-actions justify-end"><button v-if="editingId" type="button" class="btn btn-ghost btn-sm" @click="reset">Cancel</button><button class="btn btn-primary btn-sm">{{ editingId ? 'Save changes' : 'Add course' }}</button></div></div></form>
        <section class="card border border-base-300 bg-base-100 shadow-sm"><div class="card-body overflow-x-auto"><h2 class="card-title text-lg">Course records <span class="badge badge-ghost">{{ records.length }}</span></h2><p v-if="loading">Loading…</p><p v-else-if="!records.length" class="py-6 text-center text-base-content/55">No courses yet.</p><table v-else class="table"><thead><tr><th>Code</th><th>Title</th><th>Instructor</th><th></th></tr></thead><tbody><tr v-for="record in records" :key="record.id"><td class="font-mono font-medium">{{ record.code }}</td><td>{{ record.title }}</td><td>{{ record.instructor }}</td><td class="space-x-1 text-right"><button class="btn btn-ghost btn-xs" @click="edit(record)">Edit</button><button class="btn btn-ghost btn-xs text-error" @click="remove(record)">Delete</button></td></tr></tbody></table></div></section>
    </section></main>
</template>
