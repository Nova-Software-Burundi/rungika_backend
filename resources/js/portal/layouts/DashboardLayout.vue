<template>
    <div class="flex h-screen overflow-hidden bg-gray-100">
        <!-- Sidebar -->
        <SidebarMenu />

        <!-- Main content -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Topbar -->
            <Topbar />

            <!-- Main area -->
            <main class="flex-1 min-w-0 overflow-y-auto overflow-x-hidden p-6">
                <router-view />
            </main>
        </div>
    </div>
</template>

<script setup>
import SidebarMenu from "../components/navigation/SidebarMenu.vue";
import Topbar from "../components/navigation/Topbar.vue";
</script>
