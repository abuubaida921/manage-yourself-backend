# Reminder System API Documentation

## Base URL
```
/api/v1
```

## Authentication
All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer {token}
```

---

## Health Check

### GET /api/health
Check if the API is running.

**Response:**
```json
{
    "success": true,
    "message": "API is running",
    "version": "v1",
    "timestamp": "2026-01-05T10:00:00+00:00"
}
```

---

## Authentication Endpoints

### POST /api/v1/register
Register a new user.

**Rate Limit:** 5 requests per minute

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGci..."
    }
}
```

---

### POST /api/v1/login
Login and get access token.

**Rate Limit:** 10 requests per minute

**Request Body:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {...},
        "token": "eyJ0eXAiOiJKV1QiLCJhbGci..."
    }
}
```

**Response (401):**
```json
{
    "success": false,
    "message": "The provided credentials are incorrect."
}
```

---

### POST /api/v1/logout
Logout and revoke token.

**Headers:** Authorization: Bearer {token}

**Response (200):**
```json
{
    "success": true,
    "message": "Successfully logged out"
}
```

---

### GET /api/v1/profile
Get authenticated user profile.

**Headers:** Authorization: Bearer {token}

**Response (200):**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com"
        }
    }
}
```

---

## Task Endpoints

All task endpoints require authentication.

**Rate Limit:** 60 requests per minute

---

### GET /api/v1/tasks
Get all tasks for the authenticated user.

**Query Parameters:**
- `priority` (optional): Filter by priority (low, medium, high)
- `status` (optional): Filter by status (pending, completed)
- `page` (optional): Page number for pagination

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Complete project",
            "description": "Finish the reminder app",
            "due_date": "2026-01-10T14:00:00+00:00",
            "status": "pending",
            "priority": "high",
            "is_overdue": false,
            "created_at": "2026-01-05T10:00:00+00:00",
            "updated_at": "2026-01-05T10:00:00+00:00"
        }
    ],
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 3,
        "per_page": 15,
        "to": 15,
        "total": 35
    }
}
```

---

### POST /api/v1/tasks
Create a new task.

**Request Body:**
```json
{
    "title": "Complete project",
    "description": "Finish the reminder app",
    "due_date": "2026-01-10T14:00:00+00:00",
    "priority": "high"
}
```

**Validation Rules:**
- `title`: required, string, max 255 characters
- `description`: optional, string, max 1000 characters
- `due_date`: required, must be in the future
- `priority`: required, must be: low, medium, or high

**Response (201):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Complete project",
        ...
    }
}
```

---

### GET /api/v1/tasks/{id}
Get a specific task.

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Complete project",
        ...
    }
}
```

**Response (403):** Access denied (not your task)
**Response (404):** Task not found

---

### PUT /api/v1/tasks/{id}
Update a task.

**Request Body:**
```json
{
    "title": "Updated title",
    "status": "completed",
    "priority": "low"
}
```

**Response (200):**
```json
{
    "success": true,
    "data": {...}
}
```

---

### DELETE /api/v1/tasks/{id}
Delete a task.

**Response (200):**
```json
{
    "success": true,
    "message": "Task deleted successfully"
}
```

---

### GET /api/v1/tasks-summary
Get task statistics for the authenticated user.

**Response (200):**
```json
{
    "success": true,
    "data": {
        "total": 25,
        "pending": 18,
        "completed": 7,
        "overdue": 3,
        "due_soon": 5,
        "by_priority": {
            "high": 4,
            "medium": 8,
            "low": 6
        }
    }
}
```

---

## Error Responses

### 401 Unauthenticated
```json
{
    "success": false,
    "message": "Unauthenticated. Please login."
}
```

### 403 Forbidden
```json
{
    "success": false,
    "message": "Access denied. You do not have permission to perform this action."
}
```

### 404 Not Found
```json
{
    "success": false,
    "message": "Resource not found."
}
```

### 422 Validation Error
```json
{
    "success": false,
    "message": "Validation errors",
    "errors": {
        "title": ["Task title is required."],
        "due_date": ["Due date must be in the future."]
    }
}
```

### 429 Too Many Requests
```json
{
    "message": "Too Many Attempts."
}
```

---

## Notifications

The system automatically sends email reminders for tasks due within the next hour. This runs hourly via the scheduler.

To start the scheduler in production:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Testing

Run tests with:
```bash
php artisan test
```

Test user credentials:
- Email: test@example.com
- Password: password123

