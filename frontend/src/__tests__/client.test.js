import { beforeEach, describe, expect, it } from 'vitest';
import client, { TOKEN_KEY, clearAuth, getToken, setToken } from '../api/client';

describe('api client', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('attaches the Bearer token to outgoing requests', async () => {
    setToken('test-jwt-token');
    const handler = client.interceptors.request.handlers[0];
    const config = await handler.fulfilled({ headers: {} });
    expect(config.headers.Authorization).toBe('Bearer test-jwt-token');
  });

  it('sends no Authorization header when there is no token', async () => {
    clearAuth();
    const handler = client.interceptors.request.handlers[0];
    const config = await handler.fulfilled({ headers: {} });
    expect(config.headers.Authorization).toBeUndefined();
  });

  it('round-trips token storage helpers', () => {
    setToken('abc123');
    expect(getToken()).toBe('abc123');
    expect(localStorage.getItem(TOKEN_KEY)).toBe('abc123');
    clearAuth();
    expect(getToken()).toBeNull();
  });

  it('clears auth state on 401 responses', async () => {
    setToken('stale-token');
    const handler = client.interceptors.response.handlers[0];
    await expect(
      handler.rejected({ response: { status: 401 } })
    ).rejects.toBeDefined();
    expect(getToken()).toBeNull();
  });

  it('keeps auth state on non-401 errors', async () => {
    setToken('good-token');
    const handler = client.interceptors.response.handlers[0];
    await expect(
      handler.rejected({ response: { status: 500 } })
    ).rejects.toBeDefined();
    expect(getToken()).toBe('good-token');
  });
});
