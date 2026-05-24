import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../store/authStore"; // Adjust path as needed
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
                { path: "dashboard", name: "Dashboard", component: () => import("../pages/Dashboard.vue") },
                { path: "transfers", name: "Transfers", component: () => import("../pages/Transfers/Index.vue") },
                { path: "users", name: "Users", component: () => import("../pages/Users/Index.vue") },
                { path: "drivers", component: () => import("../pages/Drivers/Index.vue") },
                { path: "trips", component: () => import("../pages/Trips/Index.vue") },
                { path: "vehicles", component: () => import("../pages/Vehicles/Index.vue") },
                { path: "routes", component: () => import("../pages/Routes/Index.vue") },
                { path: "reports", component: () => import("../pages/Reports/Index.vue") },
                { path: "support", component: () => import("../pages/Support/Index.vue") },
                { path: "settings", component: () => import("../pages/Settings.vue") },
                { path: "fines", component: () => import("../pages/Fines/Index.vue") },
                { path: "fines/analytics", component: () => import("../pages/Fines/Analytics.vue") },
                { path: "control-tower", component: () => import("../pages/ControlTower/Index.vue") },
                { path: "dispatch", component: () => import("../pages/Dispatch/Index.vue") },
                { path: "insurances", component: () => import("../pages/Compliance/Insurance.vue") },
                { path: "inspections", component: () => import("../pages/Compliance/Inspection.vue") },
                { path: "compliance-summary", component: () => import("../pages/Compliance/Index.vue") },
                {
                    path: "drivers/:id",
                    name: "drivers.show",
                    component: () => import("../pages/Drivers/Show.vue"),
                    props: true // Allows the :id to be passed as a prop to the component
                },
                {
                    path: "vehicles/:id",
                    name: "vehicles.show",
                    component: () => import("../pages/Vehicles/Show.vue"),
                    props: true
                },
                {
                    path: "orders/:id",
                    name: "orders.show",
                    component: () => import("../pages/Orders/Show.vue"),
                    props: true
                },
            ],
        },
        { path: "/:pathMatch(.*)*", redirect: "/login" },
    ],
});

// The Gatekeeper
router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    // 1. If we haven't checked the session yet, check it now
    if (!authStore.isInitialized) {
        await authStore.checkAuth();
    }

    // 2. Check if the route requires authentication
    // Assuming you add "meta: { requiresAuth: true }" to your protected routes
    const requiresAuth = to.matched.some(record => record.meta.requiresAuth);

    if (requiresAuth && !authStore.user) {
        // Redirect to login if trying to access a protected page while logged out
        next({ name: 'Login' }); // Make sure this matches your login route name
    } else if (to.name === 'Login' && authStore.user) {
        // Redirect to the transfer desk if trying to access login while already logged in
        next({ name: 'Transfers' });
    } else {
        next(); // Carry on!
    }
});

export default router;
