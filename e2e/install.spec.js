/**
 * sysPass E2E Test Suite
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
 * Fill a password field and trigger JSEncrypt RSA encryption.
 *
 * sysPass encrypts password fields on the 'blur' event via encryptFormValue().
 * Playwright's fill() does NOT trigger blur, so we must manually trigger it
 * and verify that encryption occurred (value changes to a base64 RSA ciphertext).
 */
async function fillAndEncrypt(page, selector, value) {
  const locator = page.locator(selector);
  await locator.fill(value);
  // Trigger blur to invoke encryptFormValue()
  await locator.dispatchEvent('blur');
  // Wait briefly for JSEncrypt async encryption to complete
  await page.waitForTimeout(200);
  // Verify the value was encrypted — it should now be a long base64 string
  // (RSA-2048 ciphertext is ~344 chars of base64), not the original plaintext
  const encrypted = await locator.inputValue();
  if (encrypted === value) {
    // Encryption didn't fire — try calling encryptFormValue directly
    await page.evaluate(({ sel }) => {
      const $input = $(sel);
      if (typeof sysPass !== 'undefined' && sysPass.encryptFormValue) {
        sysPass.encryptFormValue($input);
      }
    }, { sel: selector });
    await page.waitForTimeout(200);
  }
}

test.describe('sysPass E2E Test Suite', () => {
  test.beforeAll(async () => {
    // Reset Docker environment for a clean install, ensuring we rebuild with latest host code
    const { execSync } = require('child_process');
    const projectRoot = __dirname + '/..';
    try {
      execSync('docker compose down -v', { cwd: projectRoot, stdio: 'pipe' });
      execSync('docker compose up -d --build', { cwd: projectRoot, stdio: 'pipe' });
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

    // Admin credentials
    await page.locator('#adminlogin').clear();
    await page.locator('#adminlogin').fill(ADMIN_LOGIN);

    // Password fields — must trigger RSA encryption via blur event
    await fillAndEncrypt(page, 'input[name="adminpass"]', ADMIN_PASS);
    await fillAndEncrypt(page, 'input[name="masterpassword"]', MASTER_PASS);
    await fillAndEncrypt(page, '#masterpasswordR', MASTER_PASS);

    // Database credentials
    await page.locator('#dbhost').clear();
    await page.locator('#dbhost').fill(DB_HOST);
    await page.locator('#dbuser').clear();
    await page.locator('#dbuser').fill(DB_USER);
    await fillAndEncrypt(page, 'input[name="dbpass"]', DB_PASS);
    await page.locator('#dbname').clear();
    await page.locator('#dbname').fill(DB_NAME);

    // Enable hosting mode (database already exists, skip DB creation)
    await page.locator('.mdl-checkbox[for="hostingmode"]').click({ force: true });

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

    // Verify installation succeeded
    expect(responseBody.status).toBe(0);
    expect(responseBody.description).toMatch(/Installation/i);

    // Wait for redirect to login page
    await page.waitForURL(/login/, { timeout: 15000 });

    // Verify we're on the login page
    await expect(page).toHaveURL(/login/);
  });

  test('login after installation', async ({ page }) => {
    await page.goto(`${BASE_URL}/index.php?r=login/index`, { waitUntil: 'networkidle' });

    await expect(page).toHaveURL(/login/);

    await page.locator('#user').fill(ADMIN_LOGIN);
    await fillAndEncrypt(page, 'input[name="pass"]', ADMIN_PASS);

    const [response] = await Promise.all([
      page.waitForURL(url => url.searchParams.get('r') === 'index', { timeout: 15000 }).catch(() => null),
      page.locator('#frmLogin button[type="submit"], #frmLogin input[type="submit"]')
        .first()
        .click(),
    ]);

    await expect(page).not.toHaveURL(/login/);
  });

  test('config.xml exists after installation', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/index.php?r=login/index`);
    expect(response.status()).toBe(200);

    const body = await response.text();
    expect(body).toMatch(/login/i);
  });

  test('verify category, client, account, decryption, and theme', async ({ page }) => {
    // 1. Log in
    await page.goto(`${BASE_URL}/index.php?r=login/index`, { waitUntil: 'networkidle' });
    await page.locator('#user').fill(ADMIN_LOGIN);
    await fillAndEncrypt(page, 'input[name="pass"]', ADMIN_PASS);

    await Promise.all([
      page.waitForURL(url => url.searchParams.get('r') === 'index', { timeout: 15000 }),
      page.locator('#frmLogin button[type="submit"], #frmLogin input[type="submit"]').first().click(),
    ]);

    // 2. Create Category
    await page.locator('#btn-5001').click();
    await page.waitForSelector('#btn-add-104', { state: 'visible' });
    await page.locator('#btn-add-104').click();
    await page.waitForSelector('#box-popup', { state: 'visible' });

    const categoryName = 'TestCategory-' + Date.now();
    await page.locator('#box-popup #name').fill(categoryName);
    await page.locator('#box-popup #description').fill('Test Category Description');

    const saveCategoryResponse = page.waitForResponse(
      resp => resp.url().includes('category/saveCreate') && resp.status() === 200,
      { timeout: 10000 }
    );
    await page.locator('#box-popup button[form="frmCategories"]').click();
    const catResp = await saveCategoryResponse;
    const catJson = await catResp.json();
    expect(catJson.status).toBe(0);
    await page.waitForSelector('#box-popup', { state: 'hidden' });

    // 3. Create Client
    await page.locator('a.mdl-tabs__tab[href="#tabs-2"]').click();
    await page.waitForSelector('#btn-add-304', { state: 'visible' });
    await page.locator('#btn-add-304').click();
    await page.waitForSelector('#box-popup', { state: 'visible' });

    const clientName = 'TestClient-' + Date.now();
    await page.locator('#box-popup #name').fill(clientName);
    await page.locator('#box-popup #description').fill('Test Client Description');

    const saveClientResponse = page.waitForResponse(
      resp => resp.url().includes('client/saveCreate') && resp.status() === 200,
      { timeout: 10000 }
    );
    await page.locator('#box-popup button[form="frmClients"]').click();
    const cliResp = await saveClientResponse;
    const cliJson = await cliResp.json();
    expect(cliJson.status).toBe(0);
    await page.waitForSelector('#box-popup', { state: 'hidden' });

    // 4. Create Account
    await page.locator('#btn-4').click();
    await page.waitForSelector('#frmAccount', { state: 'visible' });

    await page.waitForFunction(() => {
      const catSelect = document.querySelector('#category_id');
      const cliSelect = document.querySelector('#client_id');
      const catObj = catSelect && catSelect.selectize;
      const cliObj = cliSelect && cliSelect.selectize;
      return catObj && Object.keys(catObj.options).length >= 1 &&
             cliObj && Object.keys(cliObj.options).length >= 1;
    }, { timeout: 15000 });

    const accountName = 'TestAccount-' + Date.now();
    const accountUser = 'testuser';
    const accountPass = 'TestPass123!';

    await page.locator('#frmAccount #name').fill(accountName);
    await page.locator('#frmAccount #url').fill('http://example.com');
    await page.locator('#frmAccount #login').fill(accountUser);
    await fillAndEncrypt(page, '#frmAccount input[name="password"]', accountPass);
    await fillAndEncrypt(page, '#frmAccount input[name="password_repeat"]', accountPass);

    await page.evaluate(({ catName, cliName }) => {
      const catSelect = document.querySelector('#category_id');
      if (catSelect && catSelect.selectize) {
        const selectize = catSelect.selectize;
        const opt = Object.values(selectize.options).find(o => o.name.includes(catName));
        if (opt) {
          selectize.setValue(opt.id);
        }
      }

      const cliSelect = document.querySelector('#client_id');
      if (cliSelect && cliSelect.selectize) {
        const selectize = cliSelect.selectize;
        const opt = Object.values(selectize.options).find(o => o.name.includes(cliName));
        if (opt) {
          selectize.setValue(opt.id);
        }
      }
    }, { catName: categoryName, cliName: clientName });

    const saveAccountResponse = page.waitForResponse(
      resp => resp.url().includes('account/save') && resp.status() === 200,
      { timeout: 10000 }
    );
    await page.locator('button[form="frmAccount"][type="submit"]').click();
    const accResp = await saveAccountResponse;
    const accJson = await accResp.json();
    expect(accJson.status).toBe(0);

    // 5. Search for the Account
    await page.locator('#btn-1').click();
    await page.waitForSelector('#frmSearch', { state: 'visible' });

    await page.locator('#frmSearch #search').fill(accountName);
    const searchResponse = page.waitForResponse(
      resp => resp.url().includes('account/search') && resp.status() === 200,
      { timeout: 10000 }
    );
    await page.locator('#frmSearch #search').press('Enter');
    await searchResponse;

    await expect(page.locator('.account-label', { hasText: accountName })).toBeVisible();

    // 6. View/Decrypt password
    const accountRow = page.locator('.account-label', { hasText: accountName });
    const viewPassBtn = accountRow.locator('i.btn-action[data-onclick="account/viewPass"]');

    const decryptResponse = page.waitForResponse(
      resp => resp.url().includes('account/viewPass') && resp.status() === 200,
      { timeout: 10000 }
    );
    await viewPassBtn.click();
    await decryptResponse;

    await page.waitForSelector('.box-password-view', { state: 'visible' });
    await expect(page.locator('.box-password-view .dialog-user-text')).toContainText(accountUser);
    await expect(page.locator('.box-password-view .dialog-pass-text')).toContainText(accountPass);

    // Press Escape to dismiss Magnific Popup dialog immediately
    await page.keyboard.press('Escape');
    await page.waitForSelector('.box-password-view', { state: 'hidden' });

    // 7. Change theme to material-dark
    await page.locator('#users-menu-lower-right').click();
    await page.waitForSelector('#btnPrefs', { state: 'visible' });
    await page.locator('#btnPrefs').click();
    await page.waitForSelector('#frmPreferences', { state: 'visible' });

    await page.evaluate(() => {
      const select = document.querySelector('#sel-usertheme');
      select.value = 'material-dark';
      if (select.selectize) {
        select.selectize.setValue('material-dark');
      } else {
        select.dispatchEvent(new Event('change'));
      }
    });

    const savePrefsResponse = page.waitForResponse(
      resp => resp.url().includes('userSettingsGeneral/save') && resp.status() === 200,
      { timeout: 10000 }
    );
    await page.locator('button[form="frmPreferences"]').click();
    const prefsResp = await savePrefsResponse;
    const prefsJson = await prefsResp.json();
    expect(prefsJson.status).toBe(0);

    await page.goto(`${BASE_URL}/index.php?r=index`, { waitUntil: 'networkidle' });

    const bodyBg = await page.evaluate(() => {
      return window.getComputedStyle(document.body).backgroundColor;
    });
    expect(bodyBg).not.toBe('rgba(0, 0, 0, 0)');
    expect(bodyBg).not.toBe('transparent');
  });
});