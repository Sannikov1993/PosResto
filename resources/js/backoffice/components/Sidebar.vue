<template>
    <aside :class="['sidebar bg-white border-r border-gray-200 flex flex-col fixed h-full z-40', store.sidebarCollapsed ? 'collapsed' : '']">
        <!-- Logo -->
        <div class="h-16 flex items-center px-4 border-b border-gray-200">
            <img src="/images/logo/menulab_icon.svg" alt="MenuLab" class="w-10 h-10" />
            <span v-if="!store.sidebarCollapsed" class="ml-3 font-bold text-gray-900 sidebar-text">MenuLab</span>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-4 overflow-y-auto">
            <div v-for="group in store.filteredMenuGroups" :key="group.name" class="mb-4">
                <div v-if="!store.sidebarCollapsed" class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">
                    {{ group.name }}
                </div>
                <a v-for="item in group.items" :key="item.id"
                   @click="store.navigateTo(item.id)"
                   :class="['nav-item flex items-center px-4 py-2.5 cursor-pointer', store.currentModule === item.id ? 'active' : 'text-gray-600']">
                    <span class="sidebar-icon text-lg mr-3">{{ item.icon }}</span>
                    <span class="sidebar-text text-sm font-medium">{{ item.name }}</span>
                    <span v-if="item.badge" class="sidebar-text ml-auto badge badge-danger">{{ item.badge }}</span>
                </a>
            </div>
        </nav>

        <!-- User & Collapse -->
        <div class="border-t border-gray-200 p-4">
            <div v-if="!store.sidebarCollapsed" class="flex items-center mb-3">
                <div class="w-9 h-9 bg-gray-200 rounded-full flex items-center justify-center">
                    <span class="text-sm font-medium text-gray-600">{{ userInitial }}</span>
                </div>
                <div class="ml-3 sidebar-text">
                    <div class="text-sm font-medium text-gray-900">{{ store.user?.name }}</div>
                    <div class="text-xs text-gray-500">{{ store.user?.role }}</div>
                </div>
            </div>
            <button @click="store.sidebarCollapsed = !store.sidebarCollapsed"
                    class="w-full flex items-center justify-center py-2 text-gray-500 hover:text-gray-700">
                <span v-if="store.sidebarCollapsed">→</span>
                <span v-else>←</span>
            </button>
        </div>
    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useBackofficeStore } from '../stores/backoffice';

const store = useBackofficeStore();

const userInitial = computed(() => {
    return store.user?.name?.charAt(0)?.toUpperCase() || 'U';
});
</script>

<style scoped>
.sidebar {
    width: 260px;
    transition: width 0.3s;
}
.sidebar.collapsed {
    width: 70px;
}
.sidebar.collapsed .sidebar-text {
    display: none;
}
.sidebar.collapsed .sidebar-icon {
    margin-right: 0;
}
.nav-item {
    transition: all 0.2s;
}
.nav-item:hover {
    background: rgba(249, 115, 22, 0.08);
}
.nav-item.active {
    background: rgba(249, 115, 22, 0.12);
    border-right: 3px solid #f97316;
    color: #f97316;
}
</style>
