<template>
    <aside class="w-64 shrink-0 bg-white shadow-lg border-r border-gray-200 flex flex-col">
        <div class="p-4 text-xl font-bold text-gray-700 flex items-center gap-2">
            <Banknote class="w-6 h-6 text-emerald-600" />
            <span>Remittance Portal</span>
        </div>

        <nav class="flex-1 overflow-y-auto px-2 py-4 space-y-5">
            <div v-for="group in menu" :key="group.section">
                <p class="px-3 mb-1 text-[10px] font-black uppercase tracking-widest text-slate-400">
                    {{ group.section }}
                </p>
                <SidebarItem
                    v-for="item in group.items"
                    :key="item.label"
                    :item="item"
                />
            </div>
        </nav>

        <div class="p-4 border-t border-gray-200 text-sm text-gray-500">
            (c) {{ new Date().getFullYear() }} Rungika
        </div>
    </aside>
</template>

<script setup>
import { menu } from "@/config/menu.js";
import SidebarItem from "./SidebarItem.vue";
import { Banknote } from "lucide-vue-next";
</script>
