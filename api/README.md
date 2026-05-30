# Gunaso REST API – Complete Documentation
**बेसीशहर नगरपालिका | Besi Shahar Municipality**
**Version:** v1 | **Base URL:** `https://gunaso.besisharmun.gov.np/api`

---

## Table of Contents
1. [Overview](#overview)
2. [Installation Steps](#installation-steps)
3. [Authentication](#authentication)
4. [Rate Limiting](#rate-limiting)
5. [Endpoints](#endpoints)
6. [Request & Response Format](#request--response-format)
7. [Error Codes](#error-codes)
8. [API Key Management](#api-key-management)
9. [Postman Testing Guide](#postman-testing-guide)
10. [Sample Requests & Responses](#sample-requests--responses)
11. [Security Recommendations](#security-recommendations)
12. [File Structure](#file-structure)

---

## Overview

This REST API module exposes **read-only** complaint data from the Gunaso Management System to the Municipality's main dashboard (or any authorised consumer).

- **Protocol:** HTTPS only
- **Methods:** GET (read-only)
- **Format:** JSON (`application/json; charset=utf-8`)
- **Auth:** API Key (Bearer token or `X-API-KEY` header)
- **No caching:** Every response is fetched live from MySQL
- **Zero impact:** Completely isolated in `/api/` — no existing files modified


---

## Installation Steps

### Step 1 – Run the SQL Schema
Connect to your MySQL database and run the schema file:

```sql
USE techcraf_besigunaso;
SOURCE /path/to/api/schema_api.sql;
```

Or via cPanel → phpMyAdmin → Import `api/schema_api.sql`.

This creates three new tables:
- `api_keys` – stores authorised API clients
- `api_logs` – logs every request
- `api_rate_limit` – tracks per-key rate limit counters

### Step 2 – Verify File Permissions
```bash
chmod 640 api/config.php
chmod 640 api/database.php
chmod 640 api/auth.php
chmod 640 api/logger.php
chmod 750 api/logs/
```

### Step 3 – Configure CORS Origins
Open `api/config.php` and update `API_ALLOWED_ORIGINS`:

```php
define('API_ALLOWED_ORIGINS', [
    'https://gunaso.besisharmun.gov.np',
    'https://your-municipality-dashboard.gov.np',
]);
```

### Step 4 – Generate a Production API Key
Run this in phpMyAdmin or MySQL CLI:

```sql
-- Replace 'YourActualSecretKeyHere' with a strong random string
INSERT INTO api_keys (client_name, api_key, status, rate_limit)
VALUES (
    'Municipality Main Dashboard',
    SHA2('YourActualSecretKeyHere', 256),
    'active',
    100
);
```

> **Important:** The key stored in the DB is a SHA-256 hash.
> The consuming application must send the **plaintext** key in the header.

### Step 5 – Test the API
```bash
curl -s -H "X-API-KEY: YourActualSecretKeyHere" \
  https://gunaso.besisharmun.gov.np/api/dashboard.php
```

Expected response: HTTP 200 with dashboard statistics JSON.


---

## Authentication

Every request **must** include a valid API key in one of these two header formats:

**Option A – Bearer Token:**
```
Authorization: Bearer YOUR_SECRET_KEY
```

**Option B – Custom Header:**
```
X-API-KEY: YOUR_SECRET_KEY
```

The server:
1. Extracts the raw key from the header
2. Computes `SHA2(key, 256)` and looks it up in `api_keys`
3. Checks `status = 'active'`
4. Checks IP whitelist (if `allowed_ips` is set)
5. Enforces rate limit
6. Updates `last_used_at` timestamp

**Failure response (HTTP 401):**
```json
{
    "success": false,
    "message": "Unauthorized Access"
}
```

---

## Rate Limiting

- **Default:** 100 requests per minute per API key
- Configurable per key in the `api_keys.rate_limit` column
- Uses a **sliding 1-minute window** stored in `api_rate_limit` table

**Rate limit exceeded response (HTTP 429):**
```json
{
    "success": false,
    "message": "Rate limit exceeded. Maximum 100 requests per minute. Retry after 45 seconds.",
    "retry_after": 45
}
```
The `Retry-After` HTTP header is also set.

---

## Endpoints

| # | Endpoint | Method | Description |
|---|----------|--------|-------------|
| 1 | `/api/dashboard.php` | GET | Overall statistics |
| 2 | `/api/complaints.php` | GET | Paginated & filterable complaint list |
| 3 | `/api/complaint.php` | GET | Single complaint with full history |
| 4 | `/api/latest.php` | GET | Latest N complaints |
| 5 | `/api/status-summary.php` | GET | Status & priority breakdown |
| 6 | `/api/department-summary.php` | GET | Per-department & category breakdown |


---

## Request & Response Format

### Success Response
```json
{
    "success": true,
    "message": "Data Retrieved Successfully",
    "data": { ... }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Descriptive error message"
}
```

### Paginated Response (complaints.php)
```json
{
    "success": true,
    "message": "Data Retrieved Successfully",
    "page": 1,
    "limit": 50,
    "total_records": 1000,
    "total_pages": 20,
    "filters": {
        "status": "pending",
        "priority": null,
        "category": null,
        "department": null,
        "search": null,
        "from_date": "2026-01-01",
        "to_date": "2026-12-31"
    },
    "data": [ ... ]
}
```

---

## Error Codes

| HTTP Code | Meaning |
|-----------|---------|
| 200 | Success |
| 204 | CORS pre-flight OK (OPTIONS) |
| 400 | Bad Request – invalid parameter |
| 401 | Unauthorized – missing or invalid API key |
| 404 | Not Found – complaint does not exist |
| 405 | Method Not Allowed – use GET |
| 429 | Too Many Requests – rate limit exceeded |
| 500 | Internal Server Error |


---

## API Key Management

### Generate a new key
```sql
INSERT INTO api_keys (client_name, api_key, status, rate_limit)
VALUES (
    'New Consumer Name',
    SHA2('RandomSecretKey123!@#', 256),
    'active',
    100
);
-- Note the plaintext key; it cannot be recovered from the DB later.
```

### List all active keys
```sql
SELECT id, client_name, status, rate_limit, created_at, last_used_at
FROM api_keys
ORDER BY created_at DESC;
```

### Revoke a key immediately
```sql
UPDATE api_keys SET status = 'revoked' WHERE id = 3;
```

### Restrict a key to specific IPs
```sql
UPDATE api_keys
SET allowed_ips = '203.0.113.10,203.0.113.20'
WHERE id = 2;
```

### Increase rate limit for a trusted client
```sql
UPDATE api_keys SET rate_limit = 500 WHERE id = 2;
```

### View recent API usage
```sql
SELECT
    k.client_name,
    l.endpoint,
    l.ip_address,
    l.response_code,
    l.execution_ms,
    l.created_at
FROM api_logs l
LEFT JOIN api_keys k ON l.api_key_id = k.id
ORDER BY l.created_at DESC
LIMIT 100;
```

### View failed requests (last 24 hours)
```sql
SELECT endpoint, ip_address, response_code, created_at
FROM api_logs
WHERE response_code != 200
  AND created_at >= NOW() - INTERVAL 24 HOUR
ORDER BY created_at DESC;
```


---

## Sample Requests & Responses

### 1. Dashboard Statistics

**Request:**
```
GET /api/dashboard.php
X-API-KEY: YourActualSecretKeyHere
```

**Response:**
```json
{
    "success": true,
    "message": "Data Retrieved Successfully",
    "data": {
        "total": 1000,
        "pending": 100,
        "in_progress": 50,
        "resolved": 830,
        "rejected": 20,
        "today": 5,
        "this_month": 120,
        "resolution_rate": 83.00,
        "generated_at": "2026-05-30 10:00:00"
    }
}
```

---

### 2. Complaint List

**Request:**
```
GET /api/complaints.php?page=1&limit=2&status=pending&from_date=2026-01-01
X-API-KEY: YourActualSecretKeyHere
```

**Response:**
```json
{
    "success": true,
    "message": "Data Retrieved Successfully",
    "page": 1,
    "limit": 2,
    "total_records": 100,
    "total_pages": 50,
    "filters": {
        "status": "pending",
        "priority": null,
        "category": null,
        "department": null,
        "search": null,
        "from_date": "2026-01-01",
        "to_date": null
    },
    "data": [
        {
            "id": 205,
            "complaint_id": "GUN-26-0530-005",
            "name": "Ram Bahadur Thapa",
            "address": "Ward 3, Besi Shahar",
            "contact": "9856012345",
            "email": "ram@example.com",
            "subject": "Road repair needed near school",
            "description": "The road near ward 3 school has large potholes...",
            "priority": "high",
            "status": "pending",
            "has_document": false,
            "department": "पूर्वाधार शाखा",
            "category": "भौतिक पूर्वाधार",
            "assigned_to": "",
            "created_at": "2026-05-30 09:15:00",
            "updated_at": "2026-05-30 09:15:00",
            "resolved_at": null
        }
    ]
}
```

---

### 3. Single Complaint

**Request:**
```
GET /api/complaint.php?id=205
Authorization: Bearer YourActualSecretKeyHere
```

**Response:**
```json
{
    "success": true,
    "message": "Data Retrieved Successfully",
    "data": {
        "id": 205,
        "complaint_id": "GUN-26-0530-005",
        "name": "Ram Bahadur Thapa",
        "address": "Ward 3, Besi Shahar",
        "contact": "9856012345",
        "email": "ram@example.com",
        "subject": "Road repair needed near school",
        "description": "The road near ward 3 school has large potholes causing accidents...",
        "priority": "high",
        "status": "pending",
        "has_document": false,
        "document_url": null,
        "department": "पूर्वाधार शाखा",
        "category": "भौतिक पूर्वाधार",
        "assigned_to": "Sita Kumari Sharma",
        "created_at": "2026-05-30 09:15:00",
        "updated_at": "2026-05-30 09:15:00",
        "resolved_at": null,
        "history": [
            {
                "id": 301,
                "status": "pending",
                "remarks": "गुनासो दर्ता भयो",
                "reply": "",
                "updated_by": "Admin",
                "updated_at": "2026-05-30 09:15:00"
            }
        ]
    }
}
```


---

### 4. Latest Complaints

**Request:**
```
GET /api/latest.php?limit=3
X-API-KEY: YourActualSecretKeyHere
```

**Response:**
```json
{
    "success": true,
    "message": "Data Retrieved Successfully",
    "count": 3,
    "filters": { "status": null, "limit": 3 },
    "generated_at": "2026-05-30 10:00:00",
    "data": [ ... ]
}
```

---

### 5. Status Summary

**Request:**
```
GET /api/status-summary.php?from_date=2026-01-01&to_date=2026-05-30
X-API-KEY: YourActualSecretKeyHere
```

**Response:**
```json
{
    "success": true,
    "message": "Data Retrieved Successfully",
    "data": {
        "summary": [
            { "status": "pending",     "count": 100, "percentage": 10.00 },
            { "status": "in-progress", "count": 50,  "percentage": 5.00 },
            { "status": "resolved",    "count": 830, "percentage": 83.00 },
            { "status": "rejected",    "count": 20,  "percentage": 2.00 }
        ],
        "priority_summary": [
            { "priority": "urgent", "count": 15, "percentage": 1.50 },
            { "priority": "high",   "count": 85, "percentage": 8.50 },
            { "priority": "medium", "count": 750,"percentage": 75.00 },
            { "priority": "low",    "count": 150,"percentage": 15.00 }
        ],
        "total": 1000,
        "resolution_rate": 83.00,
        "period": { "from": "2026-01-01", "to": "2026-05-30" },
        "generated_at": "2026-05-30 10:00:00"
    }
}
```

---

### 6. Department Summary

**Request:**
```
GET /api/department-summary.php
X-API-KEY: YourActualSecretKeyHere
```

**Response:**
```json
{
    "success": true,
    "message": "Data Retrieved Successfully",
    "data": {
        "departments": [
            {
                "department_id": 5,
                "department": "पूर्वाधार शाखा",
                "total": 320,
                "pending": 80,
                "in_progress": 20,
                "resolved": 210,
                "rejected": 10,
                "resolution_rate": 65.63
            }
        ],
        "category_summary": [ ... ],
        "top_performing": [ ... ],
        "grand_total": 1000,
        "period": { "from": null, "to": null },
        "generated_at": "2026-05-30 10:00:00"
    }
}
```

### Complaint Not Found (404)
```
GET /api/complaint.php?id=99999
```
```json
{
    "success": false,
    "message": "Complaint not found."
}
```

### Invalid API Key (401)
```json
{
    "success": false,
    "message": "Unauthorized Access"
}
```


---

## Postman Testing Guide

### Setup Environment Variables in Postman
| Variable | Value |
|----------|-------|
| `base_url` | `https://gunaso.besisharmun.gov.np/api` |
| `api_key` | `YourActualSecretKeyHere` |

### Add Authentication Header (applies to all requests)
In Postman → **Headers** tab:
```
Key:   X-API-KEY
Value: {{api_key}}
```
Or use the **Authorization** tab → Type: **Bearer Token** → Token: `{{api_key}}`

### Collection Requests

**1. Dashboard**
```
GET {{base_url}}/dashboard.php
```

**2. All Complaints (paginated)**
```
GET {{base_url}}/complaints.php?page=1&limit=50
```

**3. Filter by status + date range**
```
GET {{base_url}}/complaints.php?status=pending&from_date=2026-01-01&to_date=2026-12-31
```

**4. Search complaints**
```
GET {{base_url}}/complaints.php?search=road&limit=20
```

**5. Filter by department**
```
GET {{base_url}}/complaints.php?department=पूर्वाधार&limit=10
```

**6. Single complaint by ID**
```
GET {{base_url}}/complaint.php?id=1
```

**7. Single complaint by tracking number**
```
GET {{base_url}}/complaint.php?tracking=GUN-26-0530-001
```

**8. Latest 10 complaints**
```
GET {{base_url}}/latest.php?limit=10
```

**9. Latest pending complaints**
```
GET {{base_url}}/latest.php?limit=5&status=pending
```

**10. Status summary (this year)**
```
GET {{base_url}}/status-summary.php?from_date=2026-01-01&to_date=2026-12-31
```

**11. Department summary**
```
GET {{base_url}}/department-summary.php
```

**12. Department summary with date filter**
```
GET {{base_url}}/department-summary.php?from_date=2026-01-01&to_date=2026-05-30
```

### Test 401 – Invalid key
Remove the `X-API-KEY` header and send any request. Expected: HTTP 401.

### Test 429 – Rate limit
Send more than 100 requests within 1 minute. Expected: HTTP 429 with `retry_after`.

### Test 400 – Bad parameter
```
GET {{base_url}}/complaint.php?id=abc
```
Expected: HTTP 400 with message `id must be a positive integer`.


---

## Security Recommendations

### 1. API Key Hygiene
- **Never commit** API keys to Git. They should live only in the database.
- Use **minimum 32 random characters** for key generation. Example:
  ```bash
  openssl rand -hex 32
  ```
- Issue **separate keys** for each consuming system (dashboard, mobile app, etc.)
- **Rotate keys** every 90 days or immediately after any suspected leak.

### 2. IP Whitelisting
Set `allowed_ips` on each key to restrict to known server IPs:
```sql
UPDATE api_keys SET allowed_ips = '203.0.113.50' WHERE id = 1;
```

### 3. HTTPS Enforcement
- `API_FORCE_HTTPS = true` is set in `config.php` — never disable this in production.
- Ensure your SSL certificate is valid and auto-renewed (Let's Encrypt recommended).

### 4. Database Security
- The API uses **PDO prepared statements exclusively** — no raw query interpolation.
- The API DB user (`techcraf_besigunaso`) should only have **SELECT** privileges on the complaint tables plus **SELECT/INSERT/UPDATE** on `api_keys`, `api_logs`, `api_rate_limit`.
  ```sql
  -- Minimal privilege DB user (recommended)
  GRANT SELECT ON techcraf_besigunaso.complaints         TO 'api_readonly'@'localhost';
  GRANT SELECT ON techcraf_besigunaso.branches           TO 'api_readonly'@'localhost';
  GRANT SELECT ON techcraf_besigunaso.complaint_types    TO 'api_readonly'@'localhost';
  GRANT SELECT ON techcraf_besigunaso.users              TO 'api_readonly'@'localhost';
  GRANT SELECT ON techcraf_besigunaso.complaint_logs     TO 'api_readonly'@'localhost';
  GRANT SELECT,INSERT,UPDATE ON techcraf_besigunaso.api_keys        TO 'api_readonly'@'localhost';
  GRANT SELECT,INSERT        ON techcraf_besigunaso.api_logs        TO 'api_readonly'@'localhost';
  GRANT SELECT,INSERT,UPDATE ON techcraf_besigunaso.api_rate_limit  TO 'api_readonly'@'localhost';
  ```

### 5. Log Monitoring
- The `api/logs/` directory is protected by `.htaccess` (Deny from all).
- Monitor `api_logs` weekly for unusual patterns (many 401s, high execution times).
- Set up a cron job to archive logs older than 90 days:
  ```sql
  DELETE FROM api_logs WHERE created_at < NOW() - INTERVAL 90 DAY;
  ```

### 6. CORS
- `API_ALLOWED_ORIGINS` in `config.php` lists the **exact** domains allowed to call the API from a browser. Keep this list minimal.
- Never use `*` (wildcard) in production.

### 7. Output Sanitization
- All string fields are passed through `htmlspecialchars()` with `ENT_QUOTES, 'UTF-8'` before being included in the JSON response.

### 8. Error Handling
- PHP errors are **never exposed** to the client. Real errors go to `api/logs/api_error.log`.
- Ensure `display_errors = Off` in your server's `php.ini`.

---

## File Structure

```
/api
├── .htaccess              Apache security rules
├── config.php             Configuration constants (API_ENTRY guarded)
├── database.php           Singleton PDO connection class
├── auth.php               Authentication middleware (ApiAuth)
├── logger.php             Request logging (ApiLogger)
├── schema_api.sql         MySQL schema for api_keys, api_logs, api_rate_limit
│
├── dashboard.php          GET  /api/dashboard.php
├── complaints.php         GET  /api/complaints.php
├── complaint.php          GET  /api/complaint.php?id=
├── latest.php             GET  /api/latest.php
├── status-summary.php     GET  /api/status-summary.php
├── department-summary.php GET  /api/department-summary.php
│
├── logs/                  Runtime logs (web-inaccessible)
│   ├── .htaccess          Deny from all
│   └── api_error.log      Application & DB error log
│
└── README.md              This file
```

---

## Query Parameter Reference

### complaints.php
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `limit` | int | 50 | Records per page (max 200) |
| `status` | string | — | `pending` \| `in-progress` \| `resolved` \| `rejected` |
| `priority` | string | — | `low` \| `medium` \| `high` \| `urgent` |
| `category` | string | — | Complaint type name (partial match) |
| `department` | string | — | Branch name (partial match) |
| `search` | string | — | Keyword (complaint_id, name, subject, address) |
| `from_date` | Y-m-d | — | Complaints created on/after this date |
| `to_date` | Y-m-d | — | Complaints created on/before this date |

### complaint.php
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | int | One of these | Internal DB id |
| `tracking` | string | One of these | Public tracking number |

### latest.php
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | 10 | Number of records (max 100) |
| `status` | string | — | Filter by status |

### status-summary.php / department-summary.php
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `from_date` | Y-m-d | — | Start of date range |
| `to_date` | Y-m-d | — | End of date range |

---

*Documentation last updated: May 30, 2026*
*System: Gunaso Management System – Besi Shahar Municipality*
