<template>
    <aside class="w-64 shrink-0 bg-white shadow-lg border-r border-gray-200 flex flex-col">
        <div class="p-4 text-xl font-bold text-gray-700 flex items-center gap-2">
            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
            <span>Transfer Desk</span>
        </div>

        <nav class="flex-1 overflow-y-auto px-2 py-4 space-y-2">
            <SidebarItem
                v-for="item in menu"
                :key="item.label"
                :item="item"
            />
        </nav>

        <div class="p-4 border-t border-gray-200 text-sm text-gray-500">
            (c) {{ new Date().getFullYear() }} Transfer Desk
        </div>
    </aside>
</template>

<script setup>
import { menu } from "@/config/menu.js";
import SidebarItem from "./SidebarItem.vue";
</script>
