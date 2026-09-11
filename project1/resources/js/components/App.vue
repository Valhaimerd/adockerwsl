<script setup>
const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? '';

const project = {
    name: meta('project-name'),
    subtitle: meta('project-subtitle'),
    description: meta('project-description'),
    unifyUrl: meta('unify-url'),
};
</script>

<template>
    <main class="hero min-h-screen bg-base-200 px-6">
        <section class="hero-content text-center">
            <div class="max-w-xl">
                <div class="mx-auto mb-6 grid size-20 place-items-center rounded-2xl bg-primary text-primary-content shadow-lg">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-10 fill-none stroke-current" stroke-width="1.8">
                        <path d="M4 19V8a2 2 0 0 1 2-2h3l2-2h7a2 2 0 0 1 2 2v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Z" />
                        <path d="M8 11h8M8 15h5" />
                    </svg>
                </div>
                <p class="mb-2 text-sm font-semibold uppercase tracking-[0.25em] text-primary">{{ project.name }}</p>
                <h1 class="text-5xl font-bold text-base-content">{{ project.subtitle }}</h1>
                <p class="py-6 text-lg text-base-content/70">{{ project.description }}</p>
                <a :href="project.unifyUrl" class="btn btn-primary">Back to Unify</a>
            </div>
        </section>
    </main>
</template>
