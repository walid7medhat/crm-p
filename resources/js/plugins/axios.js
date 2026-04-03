import axios from "axios";

/**
 * Same-origin /api when the SPA is served by Laravel (any port). Avoids hard-coded :8001
 * when you run `php artisan serve` on :8000 only.
 */
const getApiBaseUrl = () => {
  if (typeof window !== "undefined") {
    if (window.__API_BASE_URL__) {
      return window.__API_BASE_URL__;
    }
    if (window.location?.origin) {
      return `${window.location.origin}/api`;
    }
  }
  return (
    import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8001/api"
  );
};

const api = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: 120000,
});

api.interceptors.request.use((config) => {
  config.baseURL = getApiBaseUrl();
  const token = localStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;
