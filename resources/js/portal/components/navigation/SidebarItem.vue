<script setup>
import { ref } from "vue";
import { useRoute } from "vue-router";

const props = defineProps({
    item: { type: Object, required: true },
});

const route = useRoute();
const open = ref(false);

if (
    props.item.children &&
    props.item.children.some(child => route.path.startsWith(child.to))
) {
    open.value = true;
}

const isActive = (to) => {
    return route.path === to || route.path.startsWith(to + '/');
};
</script>

<template>
    <div v-if="item.children">
        <button
            @click="open = !open"
            class="flex items-center w-full px-3 py-2 rounded hover:bg-gray-100"
        >
            <component :is="item.icon" class="w-5 h-5 mr-3" />
            <span>{{ item.label }}</span>
            <span class="ml-auto">
                {{ open ? "▾" : "▸" }}
            </span>
        </button>

        <div v-show="open" class="ml-8 mt-1 space-y-1">
            <router-link
                v-for="(child, i) in item.children"
                :key="i"
                :to="child.to"
                class="block px-3 py-2 text-sm rounded hover:bg-gray-100"
                :class="{ 'bg-gray-200 font-semibold': isActive(child.to) }"
            >
                {{ child.label }}
            </router-link>
        </div>
    </div>

    <router-link
        v-else
        :to="item.to"
        class="flex items-center px-3 py-2 rounded hover:bg-gray-100"
        :class="{ 'bg-gray-200 font-semibold': isActive(item.to) }"
    >
        <component :is="item.icon" class="w-5 h-5 mr-3" />
        <span>{{ item.label }}</span>
    </router-link>
</template>
