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
            path: "/",
            component: DashboardLayout,
            meta: { requiresAuth: true },
            children: [
                { path: "", redirect: "/transfers" },
                { path: "transfers",       name: "Transfers",       component: () => import("../pages/Transfers/Index.vue") },
                { path: "users",           name: "Users",           component: () => import("../pages/Users/Index.vue") },
                { path: "currencies",      name: "Currencies",      component: () => import("../pages/Currencies/Index.vue") },
                { path: "assets",           name: "Assets",          component: () => import("../pages/Assets/Index.vue") },
                { path: "payment-methods",  name: "PaymentMethods",  component: () => import("../pages/PaymentMethods/Index.vue") },
                { path: "ads",              name: "Ads",             component: () => import("../pages/Ads/Index.vue") },
                { path: "trades",           name: "Trades",          component: () => import("../pages/Trades/Index.vue") },
                { path: "platform-fees",    name: "PlatformFees",    component: () => import("../pages/PlatformFees/Index.vue") },
                { path: "reference-prices", name: "ReferencePrices", component: () => import("../pages/ReferencePrices/Index.vue") },
                { path: "revenue",          name: "Revenue",         component: () => import("../pages/Revenue/Index.vue") },
                { path: "support",         name: "Support",         component: () => import("../pages/Support/Index.vue") },
                { path: "settings",        name: "Settings",        component: () => import("../pages/Settings.vue") },
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
        next({ name: 'Transfers' });
    } else {
        next();
    }
});

export default router;
