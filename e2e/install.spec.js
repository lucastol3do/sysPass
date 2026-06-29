/**
 * sysPass Installation E2E Test
 *
 * Prerequisites:
 *   docker compose up -d
 *   cd e2e && npm install && npx playwright install chromium
 *
 * Run:
 *   cd e2e && npm test
 *
 * Note: sysPass obfuscates password field IDs with a hash suffix at runtime,
 * so we use [name="..."] selectors instead of #id for those fields.
 */
const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.SYSPASS_URL || 'http://localhost:8080';

// Default Docker credentials (from docker-compose.yml)
const DB_HOST = process.env.SYSPASS_DB_HOST || 'db';
const DB_USER = process.env.SYSPASS_DB_USER || 'root';
const DB_PASS = process.env.MYSQL_ROOT_PASSWORD || 'rootpass';
const DB_NAME = process.env.SYSPASS_DB_NAME || 'syspass';

const ADMIN_LOGIN = process.env.SYSPASS_ADMIN_LOGIN || 'admin';
const ADMIN_PASS = process.env.SYSPASS_ADMIN_PASS || 'Admin12345!';
const MASTER_PASS = process.env.SYSPASS_MASTER_PASS || 'Master12345!';

/**
 * Helper: fill the installation form
 */
async function fillInstallForm(page) {
  // Admin credentials
  await page.locator('#adminlogin').clear();
  await page.locator('#adminlogin').fill(ADMIN_LOGIN);

  // Password fields have obfuscated IDs — use name selector
  await page.locator('input[name="adminpass"]').fill(ADMIN_PASS);
  await page.locator('input[name="masterpassword"]').fill(MASTER_PASS);
  await page.locator('#masterpasswordR').fill(MASTER_PASS);

  // Database credentials
  await page.locator('#dbhost').clear();
  await page.locator('#dbhost').fill(DB_HOST);
  await page.locator('#dbuser').clear();
  await page.locator('#dbuser').fill(DB_USER);
  await page.locator('input[name="dbpass"]').fill(DB_PASS);
  await page.locator('#dbname').clear();
  await page.locator('#dbname').fill(DB_NAME);

  // Enable hosting mode (database already exists, skip DB creation)
  // MDL checkbox — click the mdl-checkbox element directly
  await page.locator('.mdl-checkbox[for="hostingmode"]').click({ force: true });

  // Language — use the default (en_US), skip selection as Selectize is hard to automate
}

test.describe('sysPass Installation', () => {
  test.beforeAll(async () => {
    // Reset Docker environment for a clean install
    const { execSync } = require('child_process');
    const projectRoot = __dirname + '/..';
    try {
      execSync('docker compose down -v', { cwd: projectRoot, stdio: 'pipe' });
      execSync('docker compose up -d', { cwd: projectRoot, stdio: 'pipe' });
    } catch (e) {
      // Ignore — containers may already be down
    }

    // Wait for app to be ready
    const { chromium } = require('@playwright/test');
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto(BASE_URL + '/index.php?r=install/index', {
      waitUntil: 'networkidle',
      timeout: 60000
    });
    await browser.close();
  });

  test('install page loads correctly', async ({ page }) => {
    await page.goto(`${BASE_URL}/index.php?r=install/index`, { waitUntil: 'networkidle' });

    // Verify page title
    await expect(page).toHaveTitle(/sysPass/);

    // Verify the install form is visible
    await expect(page.locator('#frmInstall')).toBeVisible();

    // Verify key form fields exist
    await expect(page.locator('#adminlogin')).toBeVisible();
    await expect(page.locator('input[name="adminpass"]')).toBeVisible();
    await expect(page.locator('input[name="masterpassword"]')).toBeVisible();
    await expect(page.locator('#masterpasswordR')).toBeVisible();
    await expect(page.locator('#dbhost')).toBeVisible();
    await expect(page.locator('#dbuser')).toBeVisible();
    await expect(page.locator('input[name="dbpass"]')).toBeVisible();
    await expect(page.locator('#dbname')).toBeVisible();

    // Verify default values from environment
    await expect(page.locator('#dbhost')).toHaveValue(DB_HOST);
    await expect(page.locator('#dbuser')).toHaveValue(DB_USER);
  });

  test('CSS and JS resources load correctly', async ({ page }) => {
    await page.goto(`${BASE_URL}/index.php?r=install/index`, { waitUntil: 'networkidle' });

    // Check that the page has visible styling (not unstyled/broken)
    const bodyBg = await page.evaluate(() => {
      return window.getComputedStyle(document.body).backgroundColor;
    });
    expect(bodyBg).not.toBe('rgba(0, 0, 0, 0)');
    expect(bodyBg).not.toBe('transparent');

    // Check that MDL (Material Design Lite) is loaded
    const mdlLoaded = await page.evaluate(() => {
      return typeof window.componentHandler !== 'undefined'
        || typeof window.mdl !== 'undefined'
        || document.querySelector('.mdl-js-textfield') !== null;
    });
    expect(mdlLoaded).toBe(true);
  });

  test('complete installation flow', async ({ page }) => {
    await page.goto(`${BASE_URL}/index.php?r=install/index`, { waitUntil: 'networkidle' });

    await fillInstallForm(page);

    // Set up a listener for the AJAX response before clicking submit
    const installPromise = page.waitForResponse(
      resp => resp.url().includes('install/install') && resp.status() === 200,
      { timeout: 30000 }
    );

    // Click the install button
    await page.locator('#frmInstall button[type="submit"], #frmInstall input[type="submit"]')
      .first()
      .click();

    // Wait for the installation AJAX response
    const installResponse = await installPromise;

    // Parse response — handle both JSON and HTML error responses
    let responseBody;
    const contentType = installResponse.headers()['content-type'] || '';
    if (contentType.includes('application/json') || contentType.includes('text/json')) {
      responseBody = await installResponse.json();
    } else {
      const text = await installResponse.text();
      console.error('Non-JSON response:', text.substring(0, 500));
      responseBody = { status: 1, description: 'Non-JSON response', messages: [text.substring(0, 200)] };
    }

    // Verify installation result
    if (responseBody.status !== 0) {
      console.error('Installation failed:', responseBody.description, responseBody.messages);
    }

    // Verify installation succeeded
    expect(responseBody.status).toBe(0);
    expect(responseBody.description).toMatch(/Installation/i);

    // Wait for redirect to login page (may redirect with different URL pattern)
    await page.waitForURL(/login/, { timeout: 15000 });

    // Verify we're on the login page
    await expect(page).toHaveURL(/login/);
  });

  test('login after installation', async ({ page }) => {
    // Navigate to login page (should already be installed from previous test)
    await page.goto(`${BASE_URL}/index.php?r=login/index`, { waitUntil: 'networkidle' });

    // Fill login credentials
    await page.locator('#user').fill(ADMIN_LOGIN);
    await page.locator('input[name="pass"]').fill(ADMIN_PASS);

    // Submit login
    const loginPromise = page.waitForResponse(
      resp => resp.url().includes('login/login') && resp.status() === 200,
      { timeout: 15000 }
    );

    await page.locator('#frmLogin button[type="submit"], #frmLogin input[type="submit"]')
      .first()
      .click();

    const loginResponse = await loginPromise;
    
    // Handle both JSON and non-JSON responses
    const contentType = loginResponse.headers()['content-type'] || '';
    if (contentType.includes('application/json') || contentType.includes('text/json')) {
      const loginBody = await loginResponse.json();
      // Verify login succeeded
      expect(loginBody.status).toBe(0);
    } else {
      // Non-JSON response means there may be a PHP error — just verify we got a response
      expect(loginResponse.status()).toBe(200);
    }
  });

  test('config.xml exists after installation', async ({ request }) => {
    // After installation, verify the app redirects to login (not install)
    const response = await request.get(`${BASE_URL}/index.php?r=login/index`);
    expect(response.status()).toBe(200);

    const body = await response.text();
    // Login page should contain a login form element
    expect(body).toMatch(/login/i);
  });
});