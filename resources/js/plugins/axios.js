import axios from "axios";

const getApiBaseUrl = () =>
  (typeof window !== 'undefined' && window.__API_BASE_URL__) ||
  import.meta.env.VITE_API_BASE_URL ||
  'http://127.0.0.1:8001/api';

const api = axios.create({
  baseURL: getApiBaseUrl(),
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;
