import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../store/authStore";
import DashboardLayout from "../layouts/DashboardLayout.vue";

const router = createRouter({
    history: createWebHistory("/portal/"),
    routes: [
        {
            path: "/login",
            name: "Login",
            component: () => import("../pages/Auth/Login.vue"),
            meta: { guestOnly: true }
        },
        {
            path: "/2fa/setup",
            name: "TwoFactorSetup",
            component: () => import("../pages/Auth/TwoFactorSetup.vue"),
            meta: { guestOnly: true }
        },
        {
            path: "/",
            component: DashboardLayout,
            meta: { requiresAuth: true },
            children: [
                { path: "", redirect: "/dashboard" },
                { path: "dashboard",     name: "Dashboard",      component: () => import("../pages/Dashboard/Index.vue") },
                { path: "remittances",   name: "Remittances",    component: () => import("../pages/Remittances/Index.vue") },
                { path: "transfers",     redirect: "/remittances" },
                { path: "support",       name: "Support",        component: () => import("../pages/Support/Index.vue") },
                { path: "users",         name: "Users",          component: () => import("../pages/Users/Index.vue") },
                { path: "ratings",       name: "Ratings",        component: () => import("../pages/Ratings/Index.vue") },
                { path: "reports",       name: "Reports",        component: () => import("../pages/Reports/Index.vue") },
                { path: "revenue",       name: "Revenue",        component: () => import("../pages/Revenue/Index.vue") },
                { path: "countries",     name: "Countries",      component: () => import("../pages/Countries/Index.vue") },
                { path: "currencies",    name: "Currencies",     component: () => import("../pages/Currencies/Index.vue") },
                { path: "payment-methods", name: "PaymentMethods", component: () => import("../pages/PaymentMethods/Index.vue") },
                { path: "announcements", name: "Announcements",  component: () => import("../pages/Announcements/Index.vue") },
                { path: "ads",           name: "Ads",            component: () => import("../pages/Ads/Index.vue") },
                { path: "trades",        name: "Trades",         component: () => import("../pages/Trades/Index.vue") },
                { path: "assets",        name: "Assets",         component: () => import("../pages/Assets/Index.vue") },
                { path: "platform-fees", name: "PlatformFees",   component: () => import("../pages/PlatformFees/Index.vue") },
                { path: "reference-prices", name: "ReferencePrices", component: () => import("../pages/ReferencePrices/Index.vue") },
                { path: "settings",      name: "Settings",       component: () => import("../pages/Settings.vue") },
            ],
        },
        { path: "/:pathMatch(.*)*", redirect: "/login" },
    ],
});

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();
    if (!authStore.isInitialized) {
        await authStore.checkAuth();
    }
    const requiresAuth = to.matched.some(record => record.meta.requiresAuth);
    if (requiresAuth && !authStore.user) {
        next({ name: 'Login' });
    } else if (to.name === 'Login' && authStore.user) {
        next({ name: 'Dashboard' });
    } else {
        next();
    }
});

export default router;
