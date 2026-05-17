import axios from "axios";

// 1. Export the 'api' instance
export const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || "/api",
    withCredentials: true,
    withXSRFToken: true, // <--- This is the crucial modern Axios setting
    xsrfCookieName: "XSRF-TOKEN",
    xsrfHeaderName: "X-XSRF-TOKEN",
    headers: {
        "Accept": "application/json",
        "X-Requested-With": "XMLHttpRequest",
    },
});

// 2. Export the CSRF function
export async function ensureCsrfCookie() {
    const baseUrl = (import.meta.env.VITE_API_BASE_URL || "").replace('/api', '');
    return axios.get(`${baseUrl}/sanctum/csrf-cookie`, { withCredentials: true });
}

// 3. Export the missing setAuthToken function
export function setAuthToken(token) {
    if (token) {
        api.defaults.headers.common["Authorization"] = `Bearer ${token}`;
    } else {
        delete api.defaults.headers.common["Authorization"];
    }
}

// Interceptor logic
api.interceptors.response.use(
    (response) => {
        // CATCH HTML RESPONSES: If Laravel redirects to a login page
        // and returns HTML instead of JSON, treat it as a 401.
        const contentType = response.headers['content-type'];
        if (contentType && contentType.includes('text/html')) {
            console.warn("Received HTML instead of JSON. Session likely expired.");
            window.location.href = '/portal/login';
            return Promise.reject(new Error("Session expired"));
        }
        return response;
    },
    async (error) => {
        const status = error.response?.status;
        const configUrl = error.config?.url || '';
        const isLoginPage = window.location.pathname.includes('portal/login');

        // Rule 1: Silent fail for boot-up check
        if (status === 401 && configUrl.includes('user')) {
            return Promise.reject(error);
        }

        // Rule 2: Redirect for expired sessions on data routes
        if ((status === 401 || status === 419) && !isLoginPage) {
            console.warn("Session expired (401/419). Redirecting...");
            window.location.href = '/portal/login';
        }

        return Promise.reject(error);
    }
);

export default api;
