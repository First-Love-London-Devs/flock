# Flock — Mobile Developer Guide

## What is Flock?

Flock is a **multi-tenant church management system** where each church gets its own subdomain, database, and admin panel. The mobile app connects to a church's API via their unique subdomain.

**Example:**
- `gochurch.poimen.co.uk` — Go Church's instance
- `grace.poimen.co.uk` — Grace Community's instance
- Each church has completely isolated data

---

## Architecture Overview

```
Mobile App (React Native / Flutter)
    │
    ├── Login: POST https://{church}.poimen.co.uk/api/v1/auth/login
    ├── Get token → store securely
    └── All requests: Authorization: Bearer {token}
         │
         ├── Church Structure (Groups, Group Types)
         ├── People (Members, Leaders)
         ├── Attendance (Submit, History, Defaulters)
         ├── Dashboard (Stats, Trends)
         ├── Push Notifications (Register token)
         └── Settings (Church config)
```

### Tech Stack (Backend)
- **Framework:** Laravel 10 + PHP 8.2
- **Auth:** Laravel Sanctum (Bearer tokens)
- **Database:** MySQL 8.0 (separate DB per church)
- **Push Notifications:** Expo SDK
- **Admin Panel:** Filament 3 (web only)

---

## Getting Started

### 1. Base URL

Each church has its own subdomain:
```
https://{church-subdomain}.poimen.co.uk/api/v1
```

The app should let users enter their church subdomain on first launch (e.g., "gochurch"), then construct the base URL.

### 2. Authentication Flow

```
1. User enters username + password
2. POST /auth/login → returns Bearer token + leader profile
3. Store token securely (Keychain/SecureStorage)
4. Include token in all subsequent requests
5. On 401 response → redirect to login
```

### 3. Required Headers

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

---

## Core Concepts

### Church Hierarchy (Flexible)

Unlike traditional church software, Flock uses a **flexible hierarchy** system. Each church defines their own structure:

| Concept | Description |
|---------|-------------|
| **GroupType** | A level in the hierarchy (e.g., "Zone", "District", "Cell Group") |
| **Group** | An instance of a GroupType, forms a tree via `parent_id` |
| **Member** | A person in the church, can belong to multiple groups |
| **Leader** | A member with login credentials and a role |
| **RoleDefinition** | An admin-defined role (e.g., "Zone Overseer", "Cell Leader") |
| **LeaderRole** | Assigns a role to a leader, scoped to a specific group |

**Example structure for Go Church:**
```
Zone: "North Zone"                    (GroupType: Zone, level 0)
  └── District: "Camden District"     (GroupType: District, level 1)
       └── Cell: "Hope Cell"          (GroupType: Cell Group, level 2)
            ├── Member: John Smith
            ├── Member: Jane Doe
            └── Member: Peter Jones
```

### Attendance Model

- **AttendanceSummary** — one record per group per date (totals)
- **Attendance** — individual member attendance within a summary
- Only groups whose GroupType has `tracks_attendance = true` submit attendance
- **Defaulters** = groups that haven't submitted for a given date

### Push Notifications

- Uses **Expo Push Notifications** (works with React Native out of the box)
- Register device token via `POST /push-token`
- Server sends notifications for:
  - Birthday reminders (leader gets notified about member birthdays)
  - Attendance completion (parent leader notified when all child groups submit)
  - Manual broadcasts from admin

---

## API Reference

**Base URL:** `https://{church}.poimen.co.uk/api/v1`

### Response Format

All endpoints return consistent JSON:

**Success:**
```json
{
  "success": true,
  "data": { ... }
}
```

**Error:**
```json
{
  "success": false,
  "message": "Error description"
}
```

**Validation Error (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

**Unauthenticated (401):**
```json
{
  "message": "Unauthenticated."
}
```

### Pagination

Paginated endpoints return:
```json
{
  "success": true,
  "data": {
    "data": [ ... ],
    "current_page": 1,
    "per_page": 25,
    "total": 100,
    "last_page": 4,
    "from": 1,
    "to": 25,
    "next_page_url": "...?page=2",
    "prev_page_url": null
  }
}
```

---

## Authentication

### POST /auth/login
> No auth required

**Request:**
```json
{
  "username": "jsmith",
  "password": "secret123"
}
```

**Response (200):**
```json
{
  "success": true,
  "leader": {
    "id": 1,
    "member_id": 5,
    "username": "jsmith",
    "is_active": true,
    "notification_token": null,
    "member": {
      "id": 5,
      "first_name": "John",
      "last_name": "Smith",
      "email": "john@example.com",
      "phone_number": "+44123456789",
      "date_of_birth": "1990-05-15",
      "gender": "male",
      "picture": null,
      "is_active": true
    },
    "leaderRoles": [
      {
        "id": 1,
        "role_definition_id": 4,
        "group_id": 3,
        "is_active": true,
        "roleDefinition": {
          "name": "Cell Leader",
          "slug": "cell-leader",
          "permission_level": 40
        },
        "group": {
          "id": 3,
          "name": "Hope Cell",
          "group_type_id": 3
        }
      }
    ]
  },
  "token": "1|abc123def456..."
}
```

### POST /auth/logout
> Requires auth

**Response:**
```json
{
  "success": true,
  "message": "Logged out"
}
```

---

## Group Types

### GET /group-types
> List all hierarchy levels

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Zone",
      "slug": "zone",
      "description": null,
      "level": 0,
      "color": "#6366f1",
      "icon": "heroicon-o-globe-alt",
      "tracks_attendance": false,
      "is_active": true
    },
    {
      "id": 2,
      "name": "District",
      "slug": "district",
      "level": 1,
      "tracks_attendance": false
    },
    {
      "id": 3,
      "name": "Cell Group",
      "slug": "cell-group",
      "level": 2,
      "tracks_attendance": true
    }
  ]
}
```

### POST /group-types
> Create a new hierarchy level

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| name | string | Yes | max 255 |
| slug | string | Yes | unique, max 255 |
| description | string | No | |
| level | integer | Yes | 0 = top level |
| tracks_attendance | boolean | No | default false |
| color | string | No | hex color, max 7 |
| icon | string | No | icon identifier, max 50 |

### GET /group-types/{id}
### PUT /group-types/{id}
### DELETE /group-types/{id}
> Standard CRUD. Delete fails if groups exist for this type.

---

## Groups

### GET /groups
> List groups with optional filters

**Query Params:**
| Param | Type | Description |
|-------|------|-------------|
| group_type_id | integer | Filter by type |
| parent_id | integer | Filter by parent |

**Response includes:** groupType, leader.member, parent, members_count

### POST /groups
> Create a group

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| name | string | Yes | max 255 |
| group_type_id | integer | Yes | must exist |
| parent_id | integer | No | parent group ID |
| leader_id | integer | No | leader ID |
| description | string | No | |
| meeting_day | integer | No | 0=Sun, 1=Mon, ..., 6=Sat |
| meeting_time | string | No | HH:mm format |
| address | string | No | |
| latitude | decimal | No | |
| longitude | decimal | No | |

### GET /groups/{id}
> Returns group with groupType, leader.member, parent, children, members_count

### PUT /groups/{id}
> Update group fields

### DELETE /groups/{id}
> Soft deletes the group

### GET /groups/{id}/children
> Direct child groups

### GET /groups/{id}/ancestors
> All parent groups up the tree

### GET /groups/{id}/members
> Paginated members (25/page)

### GET /groups/{id}/hierarchy
> Full tree structure from this group's type

---

## Members

### GET /members
> Paginated list (25/page)

**Query Params:**
| Param | Type | Description |
|-------|------|-------------|
| group_id | integer | Filter by group |
| is_active | boolean | Filter active/inactive |
| search | string | Search name, email, phone |

### POST /members
> Create a member

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| first_name | string | Yes | max 255 |
| last_name | string | Yes | max 255 |
| email | string | No | unique |
| phone_number | string | No | max 50 |
| date_of_birth | date | No | YYYY-MM-DD |
| gender | string | No | male, female, other |
| address | string | No | |
| picture | string | No | file path/URL |
| marital_status | string | No | max 50 |
| occupation | string | No | max 255 |
| member_since | date | No | YYYY-MM-DD |
| is_active | boolean | No | default true |
| notes | string | No | |

### GET /members/{id}
> Returns member with groups (pivot: joined_at, is_primary) and leader

### PUT /members/{id}
> Update member

### DELETE /members/{id}
> Soft delete

### GET /members/search?q={query}
> Quick search, returns max 20 results

### POST /members/{id}/assign-group
> Assign member to a group

```json
{
  "group_id": 3,
  "is_primary": true
}
```

### DELETE /members/{id}/remove-group/{groupId}
> Remove member from a group

---

## Leaders

### GET /leaders
> List all leaders

**Query Params:**
| Param | Type | Description |
|-------|------|-------------|
| is_active | boolean | Filter active/inactive |

**Response includes:** member, leaderRoles.roleDefinition, leaderRoles.group, ledGroup

### POST /leaders
> Create a leader (links to existing member)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| member_id | integer | Yes | must exist, unique per leader |
| username | string | Yes | unique, max 255 |
| password | string | Yes | min 8 chars |

### GET /leaders/{id}
### PUT /leaders/{id}
### DELETE /leaders/{id}
> Standard CRUD

### POST /leaders/{id}/assign-role
> Assign a role scoped to a group

```json
{
  "role_definition_id": 4,
  "group_id": 3
}
```

**Response:** LeaderRole with roleDefinition and group

### DELETE /leaders/{id}/remove-role/{roleId}
> Remove a role assignment

---

## Attendance

### POST /attendance/submit
> Submit attendance for a group on a date

```json
{
  "group_id": 3,
  "date": "2026-03-20",
  "attendances": [
    {
      "member_id": 1,
      "attended": true,
      "is_first_timer": false,
      "is_visitor": false
    },
    {
      "member_id": 2,
      "attended": false,
      "is_first_timer": false,
      "is_visitor": false
    },
    {
      "member_id": 3,
      "attended": true,
      "is_first_timer": true,
      "is_visitor": false
    }
  ]
}
```

**Validation:**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| group_id | integer | Yes | must exist |
| date | date | Yes | YYYY-MM-DD |
| attendances | array | Yes | min 1 item |
| attendances.*.member_id | integer | Yes | must exist |
| attendances.*.attended | boolean | Yes | |
| attendances.*.is_first_timer | boolean | No | default false |
| attendances.*.is_visitor | boolean | No | default false |

**Response:** AttendanceSummary with auto-calculated totals + individual attendances

**Note:** Submitting twice for the same group+date will fail (unique constraint).

### GET /attendance/{summaryId}
> Get attendance details with individual records, submittedBy leader, and group

### PUT /attendance/{summaryId}
> Update attendance (replaces all individual records)

```json
{
  "attendances": [
    { "member_id": 1, "attended": true },
    { "member_id": 2, "attended": true }
  ]
}
```

### DELETE /attendance/{summaryId}
> Delete attendance record (cascades to individual records)

### GET /attendance/group/{groupId}
> Attendance history for a group (paginated, 15/page)

**Query Params:**
| Param | Type | Description |
|-------|------|-------------|
| start_date | date | Filter from date |
| end_date | date | Filter to date |

### GET /attendance/defaulters/{parentGroupId}/{date}
> Groups under a parent that haven't submitted attendance for a date

Only checks groups whose GroupType has `tracks_attendance = true`.

**Example:** `GET /attendance/defaulters/1/2026-03-20` returns cell groups under group 1 that haven't submitted for March 20.

---

## Dashboard

### GET /dashboard
> Overview stats, optionally scoped to a group

**Query Params:**
| Param | Type | Description |
|-------|------|-------------|
| group_id | integer | Scope stats to group + descendants |

**Response:**
```json
{
  "success": true,
  "data": {
    "total_members": 150,
    "total_groups": 12,
    "active_leaders": 8,
    "attendance_trends": [
      {
        "week": "2026-03-02",
        "total_attendance": 120,
        "visitor_count": 5,
        "first_timer_count": 3,
        "submissions": 8
      }
    ]
  }
}
```

### GET /dashboard/stats
> Alias for /dashboard

### GET /dashboard/attendance-trends
> Weekly attendance trends

**Query Params:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| group_id | integer | all | Scope to group |
| weeks | integer | 8 | Number of weeks |

### GET /dashboard/defaulters
> Groups that haven't submitted attendance

**Query Params (required):**
| Param | Type | Description |
|-------|------|-------------|
| group_id | integer | Parent group ID |
| date | date | Date to check (YYYY-MM-DD) |

---

## Settings

### GET /settings
> All church settings as key-value pairs

**Default settings seeded per church:**
| Key | Type | Default | Description |
|-----|------|---------|-------------|
| church_name | string | My Church | Display name |
| church_tagline | string | | Tagline |
| color_primary | string | #4f46e5 | Brand color |
| color_secondary | string | #7c3aed | Secondary color |
| font | string | Inter | UI font |
| dark_mode | boolean | true | Enable dark mode |
| attendance_day | integer | 0 | Default service day (0=Sunday) |
| timezone | string | Europe/London | Church timezone |
| modules.children | boolean | true | Children ministry enabled |
| modules.training | boolean | true | Training courses enabled |
| modules.equipment | boolean | false | Equipment booking enabled |
| modules.follow_up | boolean | true | First-timer follow-up enabled |
| modules.ai_assistant | boolean | false | AI assistant enabled |

### PUT /settings/{key}
> Update a setting

```json
{
  "value": "New Church Name",
  "type": "string"
}
```

---

## Push Notifications

### POST /push-token
> Register an Expo push token for the current device

```json
{
  "token": "ExponentPushToken[xxxxxxxxxxxxxx]",
  "device_type": "ios",
  "leader_id": 5
}
```

If the token already exists, it updates the record. Call this on every app launch.

### DELETE /push-token
> Unregister a push token (e.g., on logout)

```json
{
  "token": "ExponentPushToken[xxxxxxxxxxxxxx]"
}
```

### POST /notifications/send
> Send a push notification (admin use)

Must specify exactly one target:

**To a specific leader:**
```json
{
  "title": "Meeting Reminder",
  "body": "Your group meeting is tomorrow",
  "leader_id": 5
}
```

**To all leaders in a group tree:**
```json
{
  "title": "Submit Attendance",
  "body": "Please submit attendance for today",
  "group_id": 1
}
```

**To all holders of a role:**
```json
{
  "title": "Zone Meeting",
  "body": "Monthly zone meeting this Saturday",
  "role_slug": "zone-overseer"
}
```

---

## Automated Notifications (Server-Side)

These run automatically via cron — no API calls needed:

### Birthday Reminders
- **Schedule:** Daily at 7:00 AM
- **Logic:** Finds members with birthdays today, tomorrow, or in 1 week. Notifies their group leaders via push notification.
- **Deduplication:** Each notification only sent once per member/leader/date/type.

### Attendance Completion
- **Schedule:** Every 30 minutes
- **Logic:** For each parent group, checks if all child groups (with `tracks_attendance=true`) have submitted attendance. When all submit, notifies the parent group's leader.
- **Deduplication:** Completion notification only sent once per parent group per date.

---

## Data Types Reference

| Type | Format | Example |
|------|--------|---------|
| date | YYYY-MM-DD | 2026-03-20 |
| datetime | ISO 8601 | 2026-03-20T14:30:00.000000Z |
| time | HH:mm | 10:30 |
| meeting_day | 0-6 | 0 (Sunday) |
| gender | enum | male, female, other |
| boolean | true/false | true |

---

## Suggested Mobile App Screens

Based on the API, here's a recommended screen structure:

### Auth
1. **Church Selector** — enter church subdomain
2. **Login** — username + password

### Home
3. **Dashboard** — stats cards + attendance trend chart
4. **Quick Actions** — submit attendance, view defaulters

### Church Structure
5. **Group Tree** — expandable hierarchy view
6. **Group Detail** — info, members list, attendance history

### People
7. **Member List** — searchable, filterable
8. **Member Detail** — profile, group memberships, attendance record
9. **Leader List** — with roles and assigned groups

### Attendance
10. **Submit Attendance** — select group, date, mark members present/absent
11. **Attendance History** — per group, with date range filter
12. **Defaulters** — groups that haven't submitted today

### Profile
13. **My Profile** — leader info, role assignments
14. **Settings** — notification preferences, logout

---

## Error Handling Best Practices

```
401 → Token expired → redirect to login
403 → No permission → show "Access Denied"
404 → Resource not found → show "Not Found"
422 → Validation error → show field-level errors
429 → Rate limited → retry after delay
500 → Server error → show "Something went wrong"
```

---

## Environment Setup

For development, point the app at:
```
http://gochurch.poimen.co.uk/api/v1
```

The API is currently HTTP (SSL pending). When SSL is configured:
```
https://gochurch.poimen.co.uk/api/v1
```

---

## Questions?

Contact the backend team or check the Filament admin panel at `{church}.poimen.co.uk/admin` to see the data structures in action.
