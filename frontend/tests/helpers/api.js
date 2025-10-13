/**
 * API Helper for testing backend endpoints
 */

export class APIHelper {
  constructor(request) {
    this.request = request;
    this.baseURL = 'http://localhost/Portfolio_v2/backend/public/api';
  }

  /**
   * Test GET endpoint
   */
  async get(endpoint) {
    const response = await this.request.get(`${this.baseURL}${endpoint}`);
    return {
      status: response.status(),
      data: await response.json(),
      headers: response.headers(),
    };
  }

  /**
   * Test POST endpoint with auth
   */
  async post(endpoint, data, token = null) {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const response = await this.request.post(`${this.baseURL}${endpoint}`, {
      data,
      headers,
    });

    return {
      status: response.status(),
      data: await response.json(),
      headers: response.headers(),
    };
  }

  /**
   * Test PUT endpoint with auth
   */
  async put(endpoint, data, token = null) {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const response = await this.request.put(`${this.baseURL}${endpoint}`, {
      data,
      headers,
    });

    return {
      status: response.status(),
      data: await response.json(),
      headers: response.headers(),
    };
  }

  /**
   * Test DELETE endpoint with auth
   */
  async delete(endpoint, token = null) {
    const headers = {
      'Accept': 'application/json',
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const response = await this.request.delete(`${this.baseURL}${endpoint}`, {
      headers,
    });

    return {
      status: response.status(),
      data: await response.json(),
      headers: response.headers(),
    };
  }

  /**
   * Login and get auth token
   */
  async login(email = 'admin@example.com', password = 'password') {
    const response = await this.post('/auth/login', { email, password });
    return response.data.token || null;
  }
}
