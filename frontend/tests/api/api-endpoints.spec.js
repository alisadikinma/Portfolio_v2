import { test, expect } from '@playwright/test';

const API_URL = 'http://localhost/Portfolio_v2/backend/public/api';

test.describe('API Endpoints', () => {
  test('GET /api/settings - should return settings', async ({ request }) => {
    const response = await request.get(`${API_URL}/settings`);
    expect(response.status()).toBe(200);

    const data = await response.json();
    expect(Array.isArray(data) || typeof data === 'object').toBe(true);
  });

  test('GET /api/awards - should return awards', async ({ request }) => {
    const response = await request.get(`${API_URL}/awards`);
    expect(response.status()).toBe(200);

    const data = await response.json();
    expect(data).toBeTruthy();
  });

  test('GET /api/testimonials - should return testimonials', async ({ request }) => {
    const response = await request.get(`${API_URL}/testimonials`);
    expect(response.status()).toBe(200);

    const data = await response.json();
    expect(data).toBeTruthy();
  });

  test('GET /api/projects - should return projects', async ({ request }) => {
    const response = await request.get(`${API_URL}/projects`);
    expect(response.status()).toBe(200);
  });

  test('GET /api/posts - should return posts', async ({ request }) => {
    const response = await request.get(`${API_URL}/posts`);
    expect(response.status()).toBe(200);
  });

  test('GET /api/categories - should return categories', async ({ request }) => {
    const response = await request.get(`${API_URL}/categories`);
    expect(response.status()).toBe(200);
  });
});
