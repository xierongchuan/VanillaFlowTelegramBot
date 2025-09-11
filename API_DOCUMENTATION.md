# TaskMate VanillaFlow Telegram Bot API Documentation

## Overview

This document provides comprehensive documentation for the TaskMate VanillaFlow Telegram Bot API. The API enables user management, authentication, and expense request handling for the application.

## API Specification Files

We provide two formats for the API specification:

1. **OpenAPI YAML**: [swagger.yaml](swagger.yaml)
2. **OpenAPI JSON**: [swagger.json](swagger.json)

Both files contain the complete API specification in OpenAPI 3.0 format.

## Key Features

### Authentication
- User registration with strong password requirements
- Session-based authentication using Laravel Sanctum
- Token-based authentication for API access

### User Management
- List users with pagination and phone number filtering
- Retrieve individual user details
- Check user status

### Expense Management
- Retrieve approved expense requests
- Retrieve declined expense requests
- Retrieve issued expense requests
- Retrieve pending expense requests (newly added)
- All endpoints support pagination

## Authentication

Most endpoints require authentication using Laravel Sanctum tokens. To authenticate, include the Authorization header with your Bearer token:

```
Authorization: Bearer YOUR_ACCESS_TOKEN
```

### Obtaining Authentication Tokens

1. **User Registration**: POST `/v1/register`
2. **User Login**: POST `/v1/session`

## Error Handling

The API uses standard HTTP status codes to indicate the success or failure of requests.

### Common Error Responses

**General Error** (500):
```json
{
  "success": false,
  "message": "An error occurred",
  "error": "Error details (in debug mode)"
}
```

**Validation Error** (422):
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

**Not Found** (404):
```json
{
  "success": false,
  "message": "Resource not found"
}
```

## Using the Documentation

### With Swagger UI

To view the documentation in a browser:

1. Install Swagger UI locally or use an online viewer
2. Load the [swagger.yaml](swagger.yaml) or [swagger.json](swagger.json) file
3. Explore the endpoints, parameters, and example responses

### With Postman

1. Open Postman
2. Click "Import" and select the [swagger.json](swagger.json) file
3. The collection will be automatically generated with all endpoints

### With VS Code

1. Install the "OpenAPI (Swagger) Editor" extension
2. Open the [swagger.yaml](swagger.yaml) file
3. Use the preview feature to view the documentation

## API Endpoints Summary

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/v1/session` | POST | User login |
| `/v1/session` | DELETE | User logout |
| `/v1/register` | POST | User registration |
| `/v1/up` | GET | API health check |
| `/v1/users` | GET | List users |
| `/v1/users/{id}` | GET | Get user by ID |
| `/v1/users/{id}/status` | GET | Check user status |
| `/v1/companies/{companyId}/expenses/approved` | GET | Get approved expenses |
| `/v1/companies/{companyId}/expenses/declined` | GET | Get declined expenses |
| `/v1/companies/{companyId}/expenses/issued` | GET | Get issued expenses |
| `/v1/companies/{companyId}/expenses/pending` | GET | Get pending expenses |

## Pagination

All list endpoints support pagination with the following query parameters:

- `per_page`: Number of items per page (1-100, default: 15)
- `page`: Page number (default: 1)

Responses include pagination metadata:
```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75,
    "from": 1,
    "to": 15
  }
}
```

## Data Models

### User
```json
{
  "id": 1,
  "login": "user123",
  "full_name": "John Doe",
  "role": "user",
  "telegram_id": 123456789,
  "phone_number": "+1234567890",
  "company_id": 1
}
```

### Expense Request
Different expense types have different response structures:

**Approved Expense**:
```json
{
  "id": 1,
  "date": "2023-01-01 12:00:00",
  "requester_name": "John Doe",
  "description": "Office supplies",
  "amount": 100.50,
  "status": "approved"
}
```

**Declined Expense**:
```json
{
  "id": 2,
  "date": "2023-01-02 14:30:00",
  "requester_name": "Jane Smith",
  "description": "Team lunch",
  "amount": 75.25
}
```

**Issued Expense**:
```json
{
  "id": 3,
  "date": "2023-01-03 10:15:00",
  "requester_name": "Bob Johnson",
  "description": "Travel expenses",
  "amount": 200.00,
  "issuer_name": "Alice Wilson",
  "issued_amount": 180.00
}
```

**Pending Expense**:
```json
{
  "id": 4,
  "date": "2023-01-04 09:00:00",
  "requester_name": "Charlie Brown",
  "description": "Equipment purchase",
  "amount": 500.00,
  "status": "pending"
}
```

## Support

For questions or issues with the API, please contact the development team.
