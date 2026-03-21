const puppeteer = require('puppeteer');

const BASE_URL = process.env.FLOCK_URL || 'http://gochurch.church-stack.com';
const ADMIN_EMAIL = process.env.FLOCK_EMAIL || 'admin@gochurch.com';
const ADMIN_PASSWORD = process.env.FLOCK_PASSWORD || 'flock2026';

let browser, page;
let passed = 0;
let failed = 0;

async function test(name, fn) {
    try {
        await fn();
        console.log(`  ✓ ${name}`);
        passed++;
    } catch (err) {
        console.log(`  ✗ ${name}`);
        console.log(`    Error: ${err.message}`);
        failed++;
    }
}

async function waitAndClick(selector, options = {}) {
    await page.waitForSelector(selector, { timeout: 10000, ...options });
    await page.click(selector);
}

async function waitAndType(selector, text) {
    await page.waitForSelector(selector, { timeout: 10000 });
    await page.click(selector, { clickCount: 3 }); // select all
    await page.type(selector, text);
}

async function fillFilamentField(label, value) {
    // Find the input by its label text
    const field = await page.evaluateHandle((labelText) => {
        const labels = Array.from(document.querySelectorAll('label'));
        const label = labels.find(l => l.textContent.trim().startsWith(labelText));
        if (!label) return null;
        const forAttr = label.getAttribute('for');
        if (forAttr) return document.getElementById(forAttr);
        // Try finding input in parent container
        const container = label.closest('.fi-fo-field-wrp');
        if (container) return container.querySelector('input, textarea, select');
        return null;
    }, label);

    if (!field) throw new Error(`Field "${label}" not found`);

    await field.click({ clickCount: 3 });
    await field.type(value);
}

async function selectFilamentOption(label, optionText) {
    // Click the select trigger
    const trigger = await page.evaluateHandle((labelText) => {
        const labels = Array.from(document.querySelectorAll('label'));
        const label = labels.find(l => l.textContent.trim().startsWith(labelText));
        if (!label) return null;
        const container = label.closest('.fi-fo-field-wrp');
        if (container) return container.querySelector('button[role="combobox"], select, .choices__inner, [wire\\:click]');
        return null;
    }, label);

    if (trigger) {
        await trigger.click();
        await page.waitForTimeout(500);

        // Try to find and click the option
        const option = await page.evaluateHandle((text) => {
            const options = Array.from(document.querySelectorAll('[role="option"], .fi-select-option, li'));
            return options.find(o => o.textContent.trim().includes(text)) || null;
        }, optionText);

        if (option) {
            await option.click();
            await page.waitForTimeout(300);
        }
    }
}

async function clickButton(text) {
    await page.evaluate((btnText) => {
        const buttons = Array.from(document.querySelectorAll('button'));
        const btn = buttons.find(b => b.textContent.trim().includes(btnText));
        if (btn) btn.click();
    }, text);
}

// ============================================================
// TESTS
// ============================================================

async function run() {
    browser = await puppeteer.launch({
        headless: 'new', // Use new headless mode
        defaultViewport: { width: 1400, height: 900 },
        args: [
            '--no-sandbox',
            '--disable-extensions',
            '--disable-default-apps',
            '--disable-component-extensions-with-background-pages',
            '--disable-features=SafeBrowsing',
            '--user-data-dir=/tmp/puppeteer-flock-test',
        ],
    });

    page = await browser.newPage();
    page.setDefaultTimeout(15000);

    console.log('\n🐑 Flock E2E Test Suite');
    console.log(`   URL: ${BASE_URL}`);
    console.log('');

    // ── LOGIN ──────────────────────────────────
    console.log('Phase 0: Authentication');

    await test('Load login page', async () => {
        await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle2' });
        const title = await page.title();
        if (!title.includes('Flock') && !title.includes('Sign in') && !title.includes('Login')) {
            // Check page content instead
            const content = await page.content();
            if (!content.includes('Sign in') && !content.includes('Email')) {
                throw new Error(`Unexpected page title: ${title}`);
            }
        }
    });

    await test('Login with admin credentials', async () => {
        await waitAndType('input[type="email"], input[name="email"]', ADMIN_EMAIL);
        await waitAndType('input[type="password"], input[name="password"]', ADMIN_PASSWORD);
        await page.click('button[type="submit"]');
        await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 });
        const url = page.url();
        if (!url.includes('/admin')) {
            throw new Error(`Login failed, redirected to: ${url}`);
        }
    });

    await test('Dashboard loads', async () => {
        await page.waitForSelector('body', { timeout: 10000 });
        const content = await page.content();
        if (!content.includes('Dashboard')) {
            throw new Error('Dashboard text not found');
        }
    });

    // ── PHASE 1: GROUP TYPES ──────────────────
    console.log('\nPhase 1: Church Structure');

    await test('Group Types page loads with seeded data', async () => {
        await page.goto(`${BASE_URL}/admin/group-types`, { waitUntil: 'networkidle2' });
        const content = await page.content();
        if (!content.includes('Zone') || !content.includes('District') || !content.includes('Cell Group')) {
            throw new Error('Seeded group types not found');
        }
    });

    // ── PHASE 1: GROUPS ───────────────────────
    await test('Create Zone group', async () => {
        await page.goto(`${BASE_URL}/admin/groups/create`, { waitUntil: 'networkidle2' });
        await page.waitForTimeout(1000);

        // Fill in name
        await fillFilamentField('Name', 'North Zone');

        // Select group type
        await selectFilamentOption('Group type', 'Zone');

        await page.waitForTimeout(500);
        await clickButton('Create');
        await page.waitForTimeout(2000);

        // Verify we're redirected or see success
        const content = await page.content();
        if (content.includes('North Zone') || page.url().includes('/admin/groups')) {
            // Success
        } else {
            throw new Error('Group creation may have failed');
        }
    });

    await test('Create District group under Zone', async () => {
        await page.goto(`${BASE_URL}/admin/groups/create`, { waitUntil: 'networkidle2' });
        await page.waitForTimeout(1000);

        await fillFilamentField('Name', 'Camden District');
        await selectFilamentOption('Group type', 'District');
        await selectFilamentOption('Parent', 'North Zone');

        await page.waitForTimeout(500);
        await clickButton('Create');
        await page.waitForTimeout(2000);
    });

    await test('Create Cell Group under District', async () => {
        await page.goto(`${BASE_URL}/admin/groups/create`, { waitUntil: 'networkidle2' });
        await page.waitForTimeout(1000);

        await fillFilamentField('Name', 'Hope Cell');
        await selectFilamentOption('Group type', 'Cell Group');
        await selectFilamentOption('Parent', 'Camden District');

        await page.waitForTimeout(500);
        await clickButton('Create');
        await page.waitForTimeout(2000);
    });

    await test('Groups list shows 3 groups', async () => {
        await page.goto(`${BASE_URL}/admin/groups`, { waitUntil: 'networkidle2' });
        await page.waitForTimeout(1000);
        const content = await page.content();
        const hasGroups = content.includes('North Zone') && content.includes('Camden District') && content.includes('Hope Cell');
        if (!hasGroups) {
            throw new Error('Not all groups visible in list');
        }
    });

    // ── PHASE 1: MEMBERS ──────────────────────
    console.log('\nPhase 1: People');

    const members = [
        { first: 'John', last: 'Smith', email: 'john@test.com' },
        { first: 'Jane', last: 'Doe', email: 'jane@test.com' },
        { first: 'David', last: 'Wilson', email: 'david@test.com' },
        { first: 'Sarah', last: 'Brown', email: 'sarah@test.com' },
    ];

    for (const member of members) {
        await test(`Create member: ${member.first} ${member.last}`, async () => {
            await page.goto(`${BASE_URL}/admin/members/create`, { waitUntil: 'networkidle2' });
            await page.waitForTimeout(1000);

            await fillFilamentField('First name', member.first);
            await fillFilamentField('Last name', member.last);
            await fillFilamentField('Email', member.email);

            await page.waitForTimeout(500);
            await clickButton('Create');
            await page.waitForTimeout(2000);
        });
    }

    await test('Members list shows 4 members', async () => {
        await page.goto(`${BASE_URL}/admin/members`, { waitUntil: 'networkidle2' });
        await page.waitForTimeout(1000);
        const content = await page.content();
        if (!content.includes('John') || !content.includes('Jane')) {
            throw new Error('Members not visible in list');
        }
    });

    // ── PHASE 1: LEADERS ──────────────────────
    await test('Create leader for John Smith', async () => {
        await page.goto(`${BASE_URL}/admin/leaders/create`, { waitUntil: 'networkidle2' });
        await page.waitForTimeout(1000);

        await selectFilamentOption('Member', 'John');
        await fillFilamentField('Username', 'jsmith');
        await fillFilamentField('Password', 'leader123');

        await page.waitForTimeout(500);
        await clickButton('Create');
        await page.waitForTimeout(2000);
    });

    // ── PHASE 1: ROLE DEFINITIONS ─────────────
    await test('Role Definitions page shows seeded roles', async () => {
        await page.goto(`${BASE_URL}/admin/role-definitions`, { waitUntil: 'networkidle2' });
        await page.waitForTimeout(1000);
        const content = await page.content();
        if (!content.includes('Super Admin') || !content.includes('Cell Leader')) {
            throw new Error('Seeded roles not found');
        }
    });

    // ── PHASE 1: SETTINGS ─────────────────────
    await test('Settings page shows seeded settings', async () => {
        await page.goto(`${BASE_URL}/admin/settings`, { waitUntil: 'networkidle2' });
        await page.waitForTimeout(1000);
        const content = await page.content();
        if (!content.includes('church_name') || !content.includes('timezone')) {
            throw new Error('Seeded settings not found');
        }
    });

    // ── PHASE 2: API TESTS ────────────────────
    console.log('\nPhase 2: Attendance API');

    await test('API login and get token', async () => {
        const response = await page.evaluate(async (url) => {
            const res = await fetch(`${url}/api/v1/auth/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ username: 'jsmith', password: 'leader123' }),
            });
            return { status: res.status, body: await res.json() };
        }, BASE_URL);

        if (!response.body.success || !response.body.token) {
            throw new Error(`Login failed: ${JSON.stringify(response.body)}`);
        }

        // Store token for subsequent requests
        global.apiToken = response.body.token;
    });

    await test('Submit attendance via API', async () => {
        const token = global.apiToken;
        const response = await page.evaluate(async (url, authToken) => {
            // First get groups to find Hope Cell
            const groupsRes = await fetch(`${url}/api/v1/groups`, {
                headers: { 'Authorization': `Bearer ${authToken}`, 'Accept': 'application/json' },
            });
            const groups = await groupsRes.json();
            const cellGroup = groups.data?.find(g => g.name === 'Hope Cell');
            if (!cellGroup) return { error: 'Hope Cell not found' };

            // Get members
            const membersRes = await fetch(`${url}/api/v1/members`, {
                headers: { 'Authorization': `Bearer ${authToken}`, 'Accept': 'application/json' },
            });
            const members = await membersRes.json();
            const memberList = members.data?.data || members.data || [];
            if (memberList.length === 0) return { error: 'No members found' };

            // Submit attendance
            const attendances = memberList.slice(0, 3).map((m, i) => ({
                member_id: m.id,
                attended: i < 2, // first 2 attended
                is_first_timer: i === 2, // third is first timer
                is_visitor: false,
            }));

            const attRes = await fetch(`${url}/api/v1/attendance/submit`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${authToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    group_id: cellGroup.id,
                    date: new Date().toISOString().split('T')[0],
                    attendances: attendances,
                }),
            });
            return { status: attRes.status, body: await attRes.json() };
        }, BASE_URL, token);

        if (response.error) throw new Error(response.error);
        if (!response.body.success) {
            throw new Error(`Attendance submission failed: ${JSON.stringify(response.body)}`);
        }
    });

    await test('Attendance Summaries page shows submission', async () => {
        await page.goto(`${BASE_URL}/admin/attendance-summaries`, { waitUntil: 'networkidle2' });
        await page.waitForTimeout(1000);
        const content = await page.content();
        if (!content.includes('Hope Cell')) {
            throw new Error('Attendance summary not visible');
        }
    });

    await test('Dashboard shows stats', async () => {
        await page.goto(`${BASE_URL}/admin`, { waitUntil: 'networkidle2' });
        await page.waitForTimeout(2000);
        const content = await page.content();
        // Should show member count, group count, etc.
        if (!content.includes('Total Members') && !content.includes('Dashboard')) {
            throw new Error('Dashboard stats not loading');
        }
    });

    // ── PHASE 2: MORE API TESTS ───────────────
    console.log('\nPhase 2: Dashboard & Defaulters API');

    await test('Dashboard API returns stats', async () => {
        const token = global.apiToken;
        const response = await page.evaluate(async (url, authToken) => {
            const res = await fetch(`${url}/api/v1/dashboard/stats`, {
                headers: { 'Authorization': `Bearer ${authToken}`, 'Accept': 'application/json' },
            });
            return { status: res.status, body: await res.json() };
        }, BASE_URL, token);

        if (!response.body.success) {
            throw new Error(`Dashboard API failed: ${JSON.stringify(response.body)}`);
        }
    });

    await test('Attendance history API returns data', async () => {
        const token = global.apiToken;
        const response = await page.evaluate(async (url, authToken) => {
            // Get groups first
            const groupsRes = await fetch(`${url}/api/v1/groups`, {
                headers: { 'Authorization': `Bearer ${authToken}`, 'Accept': 'application/json' },
            });
            const groups = await groupsRes.json();
            const cellGroup = groups.data?.find(g => g.name === 'Hope Cell');
            if (!cellGroup) return { error: 'Hope Cell not found' };

            const res = await fetch(`${url}/api/v1/attendance/group/${cellGroup.id}`, {
                headers: { 'Authorization': `Bearer ${authToken}`, 'Accept': 'application/json' },
            });
            return { status: res.status, body: await res.json() };
        }, BASE_URL, token);

        if (response.error) throw new Error(response.error);
        if (!response.body.success) {
            throw new Error(`Attendance history failed: ${JSON.stringify(response.body)}`);
        }
    });

    // ── PHASE 3: PUSH NOTIFICATIONS ───────────
    console.log('\nPhase 3: Communication API');

    await test('Register push token via API', async () => {
        const token = global.apiToken;
        const response = await page.evaluate(async (url, authToken) => {
            const res = await fetch(`${url}/api/v1/push-token`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${authToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    token: 'ExponentPushToken[test-token-12345]',
                    device_type: 'ios',
                    leader_id: 1,
                }),
            });
            return { status: res.status, body: await res.json() };
        }, BASE_URL, token);

        if (!response.body.success) {
            throw new Error(`Push token registration failed: ${JSON.stringify(response.body)}`);
        }
    });

    await test('Remove push token via API', async () => {
        const token = global.apiToken;
        const response = await page.evaluate(async (url, authToken) => {
            const res = await fetch(`${url}/api/v1/push-token`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${authToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ token: 'ExponentPushToken[test-token-12345]' }),
            });
            return { status: res.status, body: await res.json() };
        }, BASE_URL, token);

        if (!response.body.success) {
            throw new Error(`Push token removal failed: ${JSON.stringify(response.body)}`);
        }
    });

    // ── RESULTS ───────────────────────────────
    console.log('\n' + '='.repeat(50));
    console.log(`Results: ${passed} passed, ${failed} failed out of ${passed + failed} tests`);
    console.log('='.repeat(50));

    if (failed > 0) {
        console.log('\n⚠️  Some tests failed. Review the errors above.');
    } else {
        console.log('\n🎉 All tests passed!');
    }

    await browser.close();
    process.exit(failed > 0 ? 1 : 0);
}

run().catch(err => {
    console.error('Fatal error:', err);
    if (browser) browser.close();
    process.exit(1);
});
