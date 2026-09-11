<script setup>
import { onMounted, reactive, ref } from 'vue';

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? '';
const project = { name: meta('project-name'), subtitle: meta('project-subtitle'), description: meta('project-description'), unifyUrl: meta('unify-url') };
const csrf = meta('csrf-token');
const records = ref([]);
const loading = ref(true);
const error = ref('');
const editingId = ref(null);
const form = reactive({ name: '', email: '', department: '' });

async function request(url, options = {}) {
    const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers ?? {}) } });
    if (response.status === 204) return null;
    const payload = await response.json();
    if (!response.ok) throw new Error(Object.values(payload.errors ?? {}).flat().join(' ') || 'Request failed.');
    return payload;
}

async function load() { try { records.value = (await request('/api/faculty')).data; error.value = ''; } catch (exception) { error.value = exception.message; } finally { loading.value = false; } }
function reset() { Object.assign(form, { name: '', email: '', department: '' }); editingId.value = null; error.value = ''; }
function edit(record) { Object.assign(form, { name: record.name, email: record.email, department: record.department }); editingId.value = record.id; window.scrollTo({ top: 0, behavior: 'smooth' }); }
async function submit() { try { const url = editingId.value ? `/api/faculty/${editingId.value}` : '/api/faculty'; await request(url, { method: editingId.value ? 'PUT' : 'POST', body: JSON.stringify(form) }); reset(); await load(); } catch (exception) { error.value = exception.message; } }
async function remove(record) { if (!window.confirm(`Delete ${record.name}?`)) return; try { await request(`/api/faculty/${record.id}`, { method: 'DELETE' }); await load(); } catch (exception) { error.value = exception.message; } }
onMounted(load);
</script>

<template>
    <main class="min-h-screen bg-base-200 px-4 py-8 md:px-6"><section class="mx-auto max-w-5xl space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold uppercase tracking-widest text-primary">{{ project.name }}</p><h1 class="text-3xl font-bold">{{ project.subtitle }}</h1><p class="mt-1 text-base-content/65">{{ project.description }}</p></div><a :href="project.unifyUrl" class="btn btn-outline btn-sm">← Back to Unify</a></header>
        <form class="card border border-base-300 bg-base-100 shadow-sm" @submit.prevent="submit"><div class="card-body"><h2 class="card-title text-lg">{{ editingId ? 'Edit faculty member' : 'Add faculty member' }}</h2><div class="grid gap-3 md:grid-cols-3"><input v-model="form.name" required maxlength="100" class="input input-bordered w-full" placeholder="Full name"><input v-model="form.email" required type="email" maxlength="150" class="input input-bordered w-full" placeholder="Email"><input v-model="form.department" required maxlength="100" class="input input-bordered w-full" placeholder="Department"></div><p v-if="error" class="text-sm text-error">{{ error }}</p><div class="card-actions justify-end"><button v-if="editingId" type="button" class="btn btn-ghost btn-sm" @click="reset">Cancel</button><button class="btn btn-primary btn-sm">{{ editingId ? 'Save changes' : 'Add faculty' }}</button></div></div></form>
        <section class="card border border-base-300 bg-base-100 shadow-sm"><div class="card-body overflow-x-auto"><h2 class="card-title text-lg">Faculty records <span class="badge badge-ghost">{{ records.length }}</span></h2><p v-if="loading">Loading…</p><p v-else-if="!records.length" class="py-6 text-center text-base-content/55">No faculty yet.</p><table v-else class="table"><thead><tr><th>Name</th><th>Email</th><th>Department</th><th></th></tr></thead><tbody><tr v-for="record in records" :key="record.id"><td class="font-medium">{{ record.name }}</td><td>{{ record.email }}</td><td>{{ record.department }}</td><td class="space-x-1 text-right"><button class="btn btn-ghost btn-xs" @click="edit(record)">Edit</button><button class="btn btn-ghost btn-xs text-error" @click="remove(record)">Delete</button></td></tr></tbody></table></div></section>
    </section></main>
</template>
