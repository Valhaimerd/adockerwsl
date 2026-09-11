<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const projects = ref([]);
const sections = ref([]);
const checking = ref(true);
let timer;

const columnMap = {
    project1: [{ key: 'name', label: 'Name' }, { key: 'email', label: 'Email' }, { key: 'program', label: 'Program' }],
    project2: [{ key: 'name', label: 'Name' }, { key: 'email', label: 'Email' }, { key: 'department', label: 'Department' }],
    project3: [{ key: 'code', label: 'Code' }, { key: 'title', label: 'Title' }, { key: 'instructor', label: 'Instructor' }],
};

async function getJson(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Unable to load ${url}`);
    return response.json();
}

async function refresh() {
    const [statusResult, overviewResult] = await Promise.allSettled([
        getJson('/api/projects/status'),
        getJson('/api/school/overview'),
    ]);

    if (statusResult.status === 'fulfilled') projects.value = statusResult.value.projects;
    if (overviewResult.status === 'fulfilled') sections.value = overviewResult.value.sections;
    checking.value = false;
}

onMounted(async () => {
    await refresh();
    timer = window.setInterval(refresh, 5000);
});

onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <main class="min-h-screen bg-base-200 px-4 py-10 md:px-6">
        <section class="mx-auto max-w-6xl space-y-10">
            <header class="text-center">
                <div class="badge badge-primary badge-outline mb-3">Docker + WSL school demonstration</div>
                <h1 class="text-4xl font-bold text-base-content">Unify School Dashboard</h1>
                <p class="mt-3 text-base-content/65">Open a service to manage its own PostgreSQL database.</p>
            </header>

            <p v-if="checking" class="text-center text-base-content/60">Checking project containers…</p>
            <div v-else class="grid gap-5 md:grid-cols-3">
                <a v-for="project in projects" :key="project.id" :href="project.available ? project.url : undefined" :aria-disabled="String(!project.available)" class="card border bg-base-100 shadow-sm transition" :class="project.available ? 'border-primary/20 hover:-translate-y-1 hover:shadow-lg' : 'cursor-not-allowed border-base-300 bg-base-300 opacity-50 grayscale'" @click="!project.available && $event.preventDefault()">
                    <div class="card-body">
                        <div class="mb-2 grid size-11 place-items-center rounded-xl bg-primary text-lg font-bold text-primary-content">{{ project.name.slice(0, 1) }}</div>
                        <h2 class="card-title">{{ project.name }}</h2><p>{{ project.subtitle }}</p>
                        <div class="card-actions mt-3 justify-between"><span class="badge" :class="project.available ? 'badge-success' : 'badge-ghost'">{{ project.available ? 'Available' : 'Unavailable' }}</span><span v-if="project.available" class="text-sm font-semibold text-primary">Manage →</span></div>
                    </div>
                </a>
            </div>

            <section>
                <div class="mb-4"><h2 class="text-2xl font-bold">School data overview</h2><p class="text-sm text-base-content/60">Read-only data gathered from three separate application databases.</p></div>
                <div class="grid gap-5 xl:grid-cols-3">
                    <article v-for="section in sections" :key="section.id" class="card border border-base-300 bg-base-100 shadow-sm">
                        <div class="card-body p-5">
                            <div class="flex items-center justify-between"><h3 class="card-title text-lg">{{ section.title }}</h3><span class="badge badge-sm" :class="section.available ? 'badge-success badge-outline' : 'badge-error badge-outline'">{{ section.available ? section.records.length : 'Offline' }}</span></div>
                            <p v-if="!section.available" class="py-6 text-center text-sm text-error">This project container is unavailable.</p>
                            <p v-else-if="!section.records.length" class="py-6 text-center text-sm text-base-content/50">No records yet.</p>
                            <div v-else class="overflow-x-auto"><table class="table table-xs"><thead><tr><th v-for="column in columnMap[section.id]" :key="column.key">{{ column.label }}</th></tr></thead><tbody><tr v-for="record in section.records" :key="record.id"><td v-for="column in columnMap[section.id]" :key="column.key">{{ record[column.key] }}</td></tr></tbody></table></div>
                        </div>
                    </article>
                </div>
            </section>
        </section>
    </main>
</template>
