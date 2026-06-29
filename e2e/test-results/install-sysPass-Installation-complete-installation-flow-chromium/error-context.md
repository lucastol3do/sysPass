# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: install.spec.js >> sysPass Installation >> complete installation flow
- Location: install.spec.js:99:3

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: 0
Received: 1
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - generic [ref=e2]:
    - generic [ref=e4]:
      - banner [ref=e5]:
        - img "logo" [ref=e8] [cursor=pointer]
      - main [ref=e9]:
        - generic [ref=e11]:
          - heading "Installation 3.2" [level=1] [ref=e13]
          - generic [ref=e14]:
            - group "sysPass Admin" [ref=e15]:
              - generic [ref=e16]: sysPass Admin
              - generic [ref=e17]:
                - textbox "sysPass admin user Password" [ref=e18]: admin
                - generic: sysPass admin user
              - generic [ref=e19]: help_outline
              - generic: sysPass administrator's login
              - generic [ref=e20]:
                - textbox [ref=e21]: jRMSkI8G2zpJ+zZtoTn8zeFgLG1NrgZCYLrX+rTEmpFZ62GmxDRKzSVE2PfWslfGX7gG+TrnxR6yXeE6t7+AI07nLeJPgQ0vHKc1hXY74VjbpYLWvzWooxJsEoRf+o5Ssp/4WI6gFcHEbET12IpmUyttWrc5XvSKaZFLigjfCfee8hb6TDKWGQ96EjRZ3+e75b7yei3EDsgv7Rq1HbG1thETKSkIOc/1R7dlQmTCKeXLGDNwzvqPbcX+29Mtp/tvNU0yZl44ey93456DwYF3SrK5igYM1je8qq3FdnitxgLslZbUmFRourGujCEi4W7hyWIjOa9dpRvpixGv7YtYvA==
                - generic: Password
              - generic [ref=e22]:
                - button "more_vert" [ref=e23] [cursor=pointer]:
                  - generic [ref=e24]: more_vert
                - generic "Show Password" [ref=e25] [cursor=pointer]: remove_red_eye
            - group "Master Password" [ref=e26]:
              - generic [ref=e27]: Master Password
              - generic [ref=e28]:
                - textbox [ref=e29]: jrKNKEpv3P2thFWI3v9EUdrOY5fM+JI6Yfq5Cn/eeBf2OHn884+eF2eJOdm6qN7lqqscvvcFTKNY17z5R4G9kJdpb3+LJmQyNf0bFcF1/MvlmoRmSon3uYqzYoIROr9pOF8G/KqYi7SlVYvgqebhRm5QIvjB46ITH6f4ySvugFA9rLOvJJo8GtmB4gpO4os+x3wzBI7ccl74T6Sc9rlTZYjChYjnb76i80Jl2umX3evvgPH5hDsCbKr82y5Jig+n2yGQoiNZD6HG+m6lcZfgy7ukldaiz2iGubm7XVNJcwstOzkZa+dr3KnxA9aOIWslrVkgD7RjqWf3NyR8lV1fYw==
                - generic: Master Password
              - generic [ref=e30]:
                - button "more_vert" [ref=e31] [cursor=pointer]:
                  - generic [ref=e32]: more_vert
                - generic "Show Password" [ref=e33] [cursor=pointer]: remove_red_eye
              - generic [ref=e34]:
                - textbox "Password (repeat)" [ref=e35]: c119rd+fJD68b0lyST64444NBRP1KlU9GZGG1lAmNgAFzfea46llf1Y4Sn5AFZ+DG1wOyVaQvZ0wWL05oudcLultJ7bggMJ/NmuEk4vfG/wp4WdVuSpWW4PXSJwAPqaM4zeFhjboYbaxsF8c+VK0Bs/MdWdizvJ0AbBDIZ2AVShnQxn75CrwyqqK6bljWRYMYhK67PijGCwW8mUwpFyzxd4YoqE9kB0BS688iaimykMNxn1dVo1lB2VrLJquQyUHUcWRp0x99N5JnBRkp8AzeIciUp1VS3OcKuriwKETNr71P4j5AKtGAcdOXd9rOUcN4Iwor9QEveMDJdu6uoD/vQ==
                - generic: Password (repeat)
            - group "DB Configuration (MySQL)" [ref=e36]:
              - generic [ref=e37]: DB Configuration (MySQL)
              - generic [ref=e38]:
                - textbox "DB access user sysPass database name" [ref=e39]: root
                - generic: DB access user
              - generic [ref=e40]: help_outline
              - generic: An user with MySQL admin rights
              - generic [ref=e41]:
                - textbox "DB access password" [ref=e42]: BhPYinueBhZqrMuM6ULa5Row+4CwAvxp7WL4DMPizkE4AKZXc43xp/JLu0OmSlyslzUc/Afpsoy0pbhcT11hsSM9TJ69AaSHsbKT3C061xSvNIhztQE0r5u9xnZtn+VC6ayDt8B1RWztDONt0OAtyyE0HNauT6XrghHg4C4XqjaemdaKRwh7gvGmsvyf7b6TIohDM8pp6JJH3nZYT8NtZz0o+bBWu635ETnRaNHbAM2bb7kM+U6Gr5KPPJirD8CevbxckHxfm+acLnavalHpfPOIQ/oinQxS8HEszYbvLjd537gJa/QLp7Q93uNFIasRFePjCWWNQfGsrx86hSCfwg==
                - generic: DB access password
              - generic "Show Password" [ref=e43] [cursor=pointer]: remove_red_eye
              - generic [ref=e44]:
                - textbox [ref=e45]: syspass
                - generic: sysPass database name
              - generic [ref=e46]: help_outline
              - generic: sysPass database name
              - generic [ref=e47]:
                - textbox "sysPass database server" [ref=e48]: db
                - generic: sysPass database server
              - generic [ref=e49]: help_outline
              - generic: Server name to install sysPass database
            - group "General" [ref=e50]:
              - generic [ref=e51]: General
              - generic [ref=e52]:
                - generic [ref=e53]: Language
                - generic [ref=e55] [cursor=pointer]:
                  - generic [ref=e56]: English
                  - textbox "Language" [ref=e57]
              - generic [ref=e58]:
                - generic [ref=e59]:
                  - text: Hosting Mode
                  - generic [ref=e60]: help_outline
                  - generic: It does not create or verify the user's permissions on the DB
                - generic [ref=e62]:
                  - checkbox "Hosting Mode help_outline It does not create or verify the user's permissions on the DB"
            - button "Install play_circle_filled" [ref=e68] [cursor=pointer]:
              - text: Install
              - generic "Install" [ref=e69]: play_circle_filled
    - contentinfo [ref=e70]:
      - generic [ref=e72]:
        - generic [ref=e73]:
          - generic [ref=e75] [cursor=pointer]: http
          - generic:
            - text: Tells whether the connection uses HTTPS or not.
            - text: Passwords sent from forms are encrypted using PKI, the remain data don't.
        - generic [ref=e76]:
          - link "sysPass 3.2" [ref=e77] [cursor=pointer]:
            - /url: https://www.syspass.org
          - generic: "Help :: FAQ :: Changelog"
          - text: "::"
          - link "cygnux.org" [ref=e78] [cursor=pointer]:
            - /url: https://www.cygnux.org
          - generic: A cygnux.org project
  - generic [ref=e79]:
    - button "×" [ref=e80] [cursor=pointer]
    - generic [ref=e81]:
      - text: Unable to connect to DB
      - text: "Error 1045: SQLSTATE[HY000] [1045] Access denied for user 'root'@'172.18.0.3' (using password: YES)"
```

# Test source

```ts
  25  | const ADMIN_PASS = process.env.SYSPASS_ADMIN_PASS || 'Admin123!';
  26  | const MASTER_PASS = process.env.SYSPASS_MASTER_PASS || 'Master123!';
  27  | 
  28  | /**
  29  |  * Helper: fill the installation form
  30  |  */
  31  | async function fillInstallForm(page) {
  32  |   // Admin credentials
  33  |   await page.locator('#adminlogin').clear();
  34  |   await page.locator('#adminlogin').fill(ADMIN_LOGIN);
  35  | 
  36  |   // Password fields have obfuscated IDs — use name selector
  37  |   await page.locator('input[name="adminpass"]').fill(ADMIN_PASS);
  38  |   await page.locator('input[name="masterpassword"]').fill(MASTER_PASS);
  39  |   await page.locator('#masterpasswordR').fill(MASTER_PASS);
  40  | 
  41  |   // Database credentials
  42  |   await page.locator('#dbhost').clear();
  43  |   await page.locator('#dbhost').fill(DB_HOST);
  44  |   await page.locator('#dbuser').clear();
  45  |   await page.locator('#dbuser').fill(DB_USER);
  46  |   await page.locator('input[name="dbpass"]').fill(DB_PASS);
  47  |   await page.locator('#dbname').clear();
  48  |   await page.locator('#dbname').fill(DB_NAME);
  49  | 
  50  |   // Language — use the default (en_US), just ensure the hidden input is set
  51  |   // Selectize is hard to automate; skip language selection as default is fine
  52  | }
  53  | 
  54  | test.describe('sysPass Installation', () => {
  55  |   test.beforeEach(async ({ page }) => {
  56  |     await page.goto(`${BASE_URL}/index.php?r=install/index`, { waitUntil: 'networkidle' });
  57  |   });
  58  | 
  59  |   test('install page loads correctly', async ({ page }) => {
  60  |     // Verify page title
  61  |     await expect(page).toHaveTitle(/sysPass/);
  62  | 
  63  |     // Verify the install form is visible
  64  |     await expect(page.locator('#frmInstall')).toBeVisible();
  65  | 
  66  |     // Verify key form fields exist (using stable selectors)
  67  |     await expect(page.locator('#adminlogin')).toBeVisible();
  68  |     await expect(page.locator('input[name="adminpass"]')).toBeVisible();
  69  |     await expect(page.locator('input[name="masterpassword"]')).toBeVisible();
  70  |     await expect(page.locator('#masterpasswordR')).toBeVisible();
  71  |     await expect(page.locator('#dbhost')).toBeVisible();
  72  |     await expect(page.locator('#dbuser')).toBeVisible();
  73  |     await expect(page.locator('input[name="dbpass"]')).toBeVisible();
  74  |     await expect(page.locator('#dbname')).toBeVisible();
  75  | 
  76  |     // Verify default values from environment
  77  |     await expect(page.locator('#dbhost')).toHaveValue(DB_HOST);
  78  |     await expect(page.locator('#dbuser')).toHaveValue(DB_USER);
  79  |     await expect(page.locator('#dbname')).toHaveValue(DB_NAME);
  80  |   });
  81  | 
  82  |   test('CSS and JS resources load correctly', async ({ page }) => {
  83  |     // Check that the page has visible styling (not unstyled/broken)
  84  |     const bodyBg = await page.evaluate(() => {
  85  |       return window.getComputedStyle(document.body).backgroundColor;
  86  |     });
  87  |     expect(bodyBg).not.toBe('rgba(0, 0, 0, 0)');
  88  |     expect(bodyBg).not.toBe('transparent');
  89  | 
  90  |     // Check that MDL (Material Design Lite) is loaded
  91  |     const mdlLoaded = await page.evaluate(() => {
  92  |       return typeof window.componentHandler !== 'undefined'
  93  |         || typeof window.mdl !== 'undefined'
  94  |         || document.querySelector('.mdl-js-textfield') !== null;
  95  |     });
  96  |     expect(mdlLoaded).toBe(true);
  97  |   });
  98  | 
  99  |   test('complete installation flow', async ({ page }) => {
  100 |     await fillInstallForm(page);
  101 | 
  102 |     // Set up a listener for the AJAX response before clicking submit
  103 |     const installPromise = page.waitForResponse(
  104 |       resp => resp.url().includes('install/install') && resp.status() === 200,
  105 |       { timeout: 30000 }
  106 |     );
  107 | 
  108 |     // Click the install button
  109 |     await page.locator('#frmInstall button[type="submit"], #frmInstall input[type="submit"]')
  110 |       .first()
  111 |       .click();
  112 | 
  113 |     // Wait for the installation AJAX response
  114 |     const installResponse = await installPromise;
  115 |     // Verify installation result
  116 |     const responseBody = await installResponse.json();
  117 |     console.log('Install response:', JSON.stringify(responseBody, null, 2));
  118 | 
  119 |     if (responseBody.status !== 0) {
  120 |       // Log the error for debugging
  121 |       console.error('Installation failed:', responseBody.description || responseBody.message || JSON.stringify(responseBody));
  122 |     }
  123 | 
  124 |     // Verify installation succeeded
> 125 |     expect(responseBody.status).toBe(0);
      |                                 ^ Error: expect(received).toBe(expected) // Object.is equality
  126 |     expect(responseBody.description).toMatch(/Installation/i);
  127 | 
  128 |     // Wait for redirect to login page
  129 |     await page.waitForURL('**/login/index**', { timeout: 15000 });
  130 | 
  131 |     // Verify we're on the login page
  132 |     await expect(page).toHaveURL(/login\/index/);
  133 |   });
  134 | 
  135 |   test('login after installation', async ({ page }) => {
  136 |     // Complete the installation first
  137 |     await fillInstallForm(page);
  138 | 
  139 |     const installPromise = page.waitForResponse(
  140 |       resp => resp.url().includes('install/install') && resp.status() === 200,
  141 |       { timeout: 30000 }
  142 |     );
  143 | 
  144 |     await page.locator('#frmInstall button[type="submit"], #frmInstall input[type="submit"]')
  145 |       .first()
  146 |       .click();
  147 | 
  148 |     await installPromise;
  149 |     await page.waitForURL('**/login/index**', { timeout: 15000 });
  150 | 
  151 |     // Wait for login form to render
  152 |     await page.waitForLoadState('networkidle');
  153 | 
  154 |     // Fill login credentials
  155 |     await page.locator('#user').fill(ADMIN_LOGIN);
  156 |     await page.locator('input[name="pass"]').fill(ADMIN_PASS);
  157 | 
  158 |     // Submit login
  159 |     const loginPromise = page.waitForResponse(
  160 |       resp => resp.url().includes('login/login') && resp.status() === 200,
  161 |       { timeout: 15000 }
  162 |     );
  163 | 
  164 |     await page.locator('#frmLogin button[type="submit"], #frmLogin input[type="submit"]')
  165 |       .first()
  166 |       .click();
  167 | 
  168 |     const loginResponse = await loginPromise;
  169 |     const loginBody = await loginResponse.json();
  170 | 
  171 |     // Verify login succeeded
  172 |     expect(loginBody.status).toBe(0);
  173 |   });
  174 | 
  175 |   test('config.xml exists after installation', async ({ request }) => {
  176 |     // After installation, verify the app redirects to login (not install)
  177 |     const response = await request.get(`${BASE_URL}/index.php?r=login/index`);
  178 |     expect(response.status()).toBe(200);
  179 | 
  180 |     const body = await response.text();
  181 |     // Login page should contain a login form element
  182 |     expect(body).toMatch(/login/i);
  183 |   });
  184 | });
```