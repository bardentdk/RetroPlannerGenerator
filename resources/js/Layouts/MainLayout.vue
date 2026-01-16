<script setup>
import { Link } from '@inertiajs/vue3';
import { PhCalendarCheck, PhTrash, PhGithubLogo } from '@phosphor-icons/vue';
</script>

<template>
    <div class="min-h-screen flex flex-col bg-[#f8fafc] text-slate-800 font-sans">
        <header class="bg-white/80 backdrop-blur-md sticky top-0 z-50 border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-indigo-600 p-2 rounded-lg text-white shadow-lg shadow-indigo-200">
                        <PhCalendarCheck :size="28" weight="fill" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-slate-900">PlanningGen</h1>
                        <p class="text-xs text-slate-500 font-medium">Powered by Laravel 12 & Groq AI</p>
                    </div>
                </div>
                
                <div class="flex gap-4">
                     <Link href="/reset" method="post" as="button" class="text-sm font-semibold text-slate-500 hover:text-red-600 transition-colors flex items-center gap-2 px-4 py-2 hover:bg-red-50 rounded-lg">
                        <PhTrash weight="bold"/> Reset Data
                     </Link>
                </div>
            </div>
        </header>

        <main class="flex-1 w-full">
            <slot />
        </main>
    </div>
</template>