<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const projects = ref([]);
const checking = ref(true);
let timer;

async function refreshStatuses() {
    try {
        const response = await fetch('/api/projects/status', {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        projects.value = payload.projects;
    } finally {
        checking.value = false;
    }
}

onMounted(async () => {
    await refreshStatuses();
    timer = window.setInterval(refreshStatuses, 5000);
});

onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <main class="min-h-screen bg-base-200 px-6 py-16">
        <section class="mx-auto max-w-5xl">
            <div class="mb-10 text-center">
                <div class="badge badge-primary badge-outline mb-4">Docker + WSL demonstration</div>
                <h1 class="text-5xl font-bold text-base-content">Unify</h1>
                <p class="mt-4 text-base-content/70">Choose an available project container.</p>
            </div>

            <p v-if="checking" class="text-center text-base-content/60">Checking project containers…</p>

            <div v-else class="grid gap-6 md:grid-cols-3">
                <a
                    v-for="project in projects"
                    :key="project.id"
                    :href="project.available ? project.url : undefined"
                    :aria-disabled="String(!project.available)"
                    class="card border bg-base-100 shadow-xl transition"
                    :class="project.available
                        ? 'border-primary/20 hover:-translate-y-1 hover:shadow-2xl'
                        : 'cursor-not-allowed border-base-300 bg-base-300 opacity-50 grayscale'"
                    @click="!project.available && $event.preventDefault()"
                >
                    <div class="card-body">
                        <div class="mb-3 grid size-12 place-items-center rounded-xl bg-primary text-primary-content">
                            <span aria-hidden="true" class="text-xl">◆</span>
                        </div>
                        <h2 class="card-title">{{ project.name }}</h2>
                        <p>{{ project.subtitle }}</p>
                        <div class="card-actions mt-4 justify-between">
                            <span
                                class="badge"
                                :class="project.available ? 'badge-success' : 'badge-ghost'"
                            >
                                {{ project.available ? 'Available' : 'Unavailable' }}
                            </span>
                            <span v-if="project.available" class="text-sm font-semibold text-primary">Open →</span>
                        </div>
                    </div>
                </a>
            </div>
        </section>
    </main>
</template>
