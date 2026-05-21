import axios from "axios";

const getApiBaseUrl = () =>
  (typeof window !== 'undefined' && window.__API_BASE_URL__) ||
  import.meta.env.VITE_API_BASE_URL ||
  'http://127.0.0.1:8000/api';

const resolveAuthToken = () => {
  let token =
    localStorage.getItem('token') ||
    localStorage.getItem('access_token') ||
    sessionStorage.getItem('token');

  if (!token && typeof document !== 'undefined') {
    const cookies = document.cookie.split('; ');
    const tokenCookie = cookies.find((row) => row.startsWith('token='));
    const accessTokenCookie = cookies.find((row) => row.startsWith('access_token='));
    token = tokenCookie?.split('=')[1] || accessTokenCookie?.split('=')[1];
  }

  return token || null;
};

const api = axios.create({
  baseURL: getApiBaseUrl(),
});

api.interceptors.request.use((config) => {
  const token = resolveAuthToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;
