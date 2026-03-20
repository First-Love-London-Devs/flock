const https = require('http');

const BASE_URL = process.env.FLOCK_URL || 'http://gochurch.poimen.co.uk';
let passed = 0;
let failed = 0;
let apiToken = null;

async function request(method, path, body = null, token = null) {
    const url = `${BASE_URL}${path}`;
    const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const res = await fetch(url, {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
    });

    const data = await res.json().catch(() => ({}));
    return { status: res.status, data };
}

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

async function run() {
    console.log('\n🐑 Flock API Test Suite');
    console.log(`   URL: ${BASE_URL}\n`);

    // ── CONNECTIVITY ──────────────────────────
    console.log('Phase 0: Connectivity');

    await test('Tenant root endpoint responds', async () => {
        const { status, data } = await request('GET', '/');
        if (data.app !== 'Flock') throw new Error(`Expected Flock app, got: ${JSON.stringify(data)}`);
        if (data.tenant !== 'Go Church') throw new Error(`Expected Go Church tenant, got: ${data.tenant}`);
    });

    // ── AUTH ───────────────────────────────────
    console.log('\nPhase 1: Authentication');

    await test('Login with leader credentials', async () => {
        const { status, data } = await request('POST', '/api/v1/auth/login', {
            username: 'jsmith',
            password: 'leader123',
        });
        if (!data.success) throw new Error(`Login failed: ${JSON.stringify(data)}`);
        if (!data.token) throw new Error('No token returned');
        apiToken = data.token;
    });

    // If login failed, try creating the leader data first
    if (!apiToken) {
        console.log('  ⚠ Login failed - skipping authenticated tests');
        console.log('  ⚠ Create a leader first via Filament admin');
    }

    // ── GROUP TYPES ───────────────────────────
    console.log('\nPhase 1: Group Types');

    await test('List group types', async () => {
        const { status, data } = await request('GET', '/api/v1/group-types', null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
        const types = data.data;
        if (!Array.isArray(types)) throw new Error('Expected array of group types');
        if (types.length < 3) throw new Error(`Expected at least 3 seeded types, got ${types.length}`);
        const names = types.map(t => t.name);
        if (!names.includes('Zone')) throw new Error('Zone type not found');
        if (!names.includes('District')) throw new Error('District type not found');
        if (!names.includes('Cell Group')) throw new Error('Cell Group type not found');
    });

    // ── GROUPS ─────────────────────────────────
    console.log('\nPhase 1: Groups');

    let zoneId, districtId, cellId;

    await test('Create Zone group', async () => {
        const { data } = await request('POST', '/api/v1/groups', {
            name: 'North Zone',
            group_type_id: 1, // Zone
        }, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
        zoneId = data.data.id;
    });

    await test('Create District under Zone', async () => {
        const { data } = await request('POST', '/api/v1/groups', {
            name: 'Camden District',
            group_type_id: 2, // District
            parent_id: zoneId,
        }, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
        districtId = data.data.id;
    });

    await test('Create Cell Group under District', async () => {
        const { data } = await request('POST', '/api/v1/groups', {
            name: 'Hope Cell',
            group_type_id: 3, // Cell Group
            parent_id: districtId,
        }, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
        cellId = data.data.id;
    });

    await test('List groups shows 3 groups', async () => {
        const { data } = await request('GET', '/api/v1/groups', null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
        const groups = data.data?.data || data.data;
        if (groups.length < 3) throw new Error(`Expected 3 groups, got ${groups.length}`);
    });

    await test('Get group children', async () => {
        const { data } = await request('GET', `/api/v1/groups/${zoneId}/children`, null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
        const children = data.data;
        if (!Array.isArray(children) || children.length < 1) throw new Error('Expected at least 1 child');
        if (children[0].name !== 'Camden District') throw new Error(`Expected Camden District, got ${children[0].name}`);
    });

    await test('Get group ancestors', async () => {
        const { data } = await request('GET', `/api/v1/groups/${cellId}/ancestors`, null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
    });

    await test('Get group hierarchy', async () => {
        const { data } = await request('GET', `/api/v1/groups/${zoneId}/hierarchy`, null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
    });

    // ── MEMBERS ────────────────────────────────
    console.log('\nPhase 1: Members');

    const memberIds = [];
    const members = [
        { first_name: 'John', last_name: 'Smith', email: 'john@test.com' },
        { first_name: 'Jane', last_name: 'Doe', email: 'jane@test.com' },
        { first_name: 'David', last_name: 'Wilson', email: 'david@test.com' },
        { first_name: 'Sarah', last_name: 'Brown', email: 'sarah@test.com' },
    ];

    for (const member of members) {
        await test(`Create member: ${member.first_name} ${member.last_name}`, async () => {
            const { data } = await request('POST', '/api/v1/members', member, apiToken);
            if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
            memberIds.push(data.data.id);
        });
    }

    await test('Search members', async () => {
        const { data } = await request('GET', '/api/v1/members/search?q=John', null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
    });

    await test('Assign member to group', async () => {
        if (memberIds.length === 0) throw new Error('No members created');
        const { data } = await request('POST', `/api/v1/members/${memberIds[0]}/assign-group`, {
            group_id: cellId,
            is_primary: true,
        }, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
    });

    await test('Assign more members to group', async () => {
        for (let i = 1; i < memberIds.length; i++) {
            const { data } = await request('POST', `/api/v1/members/${memberIds[i]}/assign-group`, {
                group_id: cellId,
            }, apiToken);
            if (!data.success) throw new Error(`Failed for member ${memberIds[i]}: ${JSON.stringify(data)}`);
        }
    });

    await test('Get group members', async () => {
        const { data } = await request('GET', `/api/v1/groups/${cellId}/members`, null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
    });

    // ── LEADERS ─────────────────────────────────
    console.log('\nPhase 1: Leaders');

    let leaderId;

    await test('List leaders', async () => {
        const { data } = await request('GET', '/api/v1/leaders', null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
        // jsmith should exist from tenant:create-user
        const leaders = data.data?.data || data.data;
        if (leaders.length > 0) leaderId = leaders[0].id;
    });

    // ── ATTENDANCE ──────────────────────────────
    console.log('\nPhase 2: Attendance');

    const today = new Date().toISOString().split('T')[0];

    await test('Submit attendance', async () => {
        if (memberIds.length < 2) throw new Error('Need at least 2 members');
        const { data } = await request('POST', '/api/v1/attendance/submit', {
            group_id: cellId,
            date: today,
            attendances: [
                { member_id: memberIds[0], attended: true, is_first_timer: false, is_visitor: false },
                { member_id: memberIds[1], attended: true, is_first_timer: false, is_visitor: false },
                { member_id: memberIds[2], attended: false, is_first_timer: false, is_visitor: false },
                { member_id: memberIds[3], attended: true, is_first_timer: true, is_visitor: false },
            ],
        }, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
        if (data.data.total_attendance !== 3) throw new Error(`Expected 3 attended, got ${data.data.total_attendance}`);
        if (data.data.first_timer_count !== 1) throw new Error(`Expected 1 first timer, got ${data.data.first_timer_count}`);
    });

    await test('Get attendance history', async () => {
        const { data } = await request('GET', `/api/v1/attendance/group/${cellId}`, null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
    });

    await test('Get defaulters (North Zone children)', async () => {
        const { data } = await request('GET', `/api/v1/attendance/defaulters/${districtId}/${today}`, null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
        // Hope Cell submitted, so defaulters should be empty
    });

    // ── DASHBOARD ───────────────────────────────
    console.log('\nPhase 2: Dashboard');

    await test('Dashboard stats', async () => {
        const { data } = await request('GET', '/api/v1/dashboard/stats', null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
        if (data.data.total_members < 4) throw new Error(`Expected at least 4 members, got ${data.data.total_members}`);
        if (data.data.total_groups < 3) throw new Error(`Expected at least 3 groups, got ${data.data.total_groups}`);
    });

    await test('Attendance trends', async () => {
        const { data } = await request('GET', '/api/v1/dashboard/attendance-trends?weeks=4', null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
    });

    // ── SETTINGS ────────────────────────────────
    console.log('\nPhase 1: Settings');

    await test('List settings', async () => {
        const { data } = await request('GET', '/api/v1/settings', null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
        const settings = data.data;
        if (!Array.isArray(settings)) throw new Error('Expected array of settings');
        const keys = settings.map(s => s.key);
        if (!keys.includes('church_name')) throw new Error('church_name setting not found');
    });

    await test('Update a setting', async () => {
        const { data } = await request('PUT', '/api/v1/settings/church_name', {
            value: 'Go Church London',
            type: 'string',
        }, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
    });

    // ── PUSH TOKENS ─────────────────────────────
    console.log('\nPhase 3: Push Notifications');

    await test('Register push token', async () => {
        const { data } = await request('POST', '/api/v1/push-token', {
            token: 'ExponentPushToken[test-token-12345]',
            device_type: 'ios',
        }, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
    });

    await test('Remove push token', async () => {
        const { data } = await request('DELETE', '/api/v1/push-token', {
            token: 'ExponentPushToken[test-token-12345]',
        }, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
    });

    // ── CLEANUP ─────────────────────────────────
    console.log('\nCleanup');

    await test('Logout', async () => {
        const { data } = await request('POST', '/api/v1/auth/logout', null, apiToken);
        if (!data.success) throw new Error(`Failed: ${JSON.stringify(data)}`);
    });

    // ── RESULTS ─────────────────────────────────
    console.log('\n' + '='.repeat(50));
    console.log(`Results: ${passed} passed, ${failed} failed out of ${passed + failed} tests`);
    console.log('='.repeat(50));

    if (failed > 0) {
        console.log('\n⚠️  Some tests failed. Review the errors above.');
    } else {
        console.log('\n🎉 All tests passed! Phases 1-3 verified.');
    }

    process.exit(failed > 0 ? 1 : 0);
}

run().catch(err => {
    console.error('Fatal error:', err);
    process.exit(1);
});
