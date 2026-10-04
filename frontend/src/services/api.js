// Compatibility re-export: single source of truth is src/api/client.js
export { TOKEN_KEY, getToken, setToken, clearAuth } from '../api/client';
import client from '../api/client';
export default client;
