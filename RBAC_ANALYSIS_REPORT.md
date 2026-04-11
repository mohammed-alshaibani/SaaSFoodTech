# FoodTechSaaS - Roles, Permissions & Dashboards Analysis Report

## Executive Summary

This document provides a comprehensive breakdown of the **Simplified RBAC (Role-Based Access Control)** system implemented in the FoodTechSaaS project. The system uses Spatie Laravel Permission with custom hierarchy extensions, supporting 6 distinct roles with 38 granular permissions across 7 categories.

---

## 1. RBAC Architecture Overview

### 1.1 Role Hierarchy (Inheritance Chain)

```
Super Admin (Level 5)
    └── Admin (Level 4)
            └── Provider Admin (Level 3)
                    └── Provider (Level 2)
                            └── Customer (Level 1)
                                    └── Guest (Level 0)
```

**Key Design Principle**: Child roles inherit all permissions from their parent roles through the recursive `getAllPermissions()` method in the `Role` model.

### 1.2 Core Files

| Component | File Path |
|-----------|-----------|
| Role Model | `backend/app/Models/Role.php` |
| Permission Model | `backend/app/Models/Permission.php` |
| User Model | `backend/app/Models/User.php` |
| RBAC Seeder | `backend/database/seeders/AdvancedRbacSeeder.php` |
| Service Request Policy | `backend/app/Policies/ServiceRequestPolicy.php` |
| Auth Controller | `backend/app/Http/Controllers/AuthController.php` |
| Role Controller | `backend/app/Http/Controllers/RoleController.php` |

---

## 2. Detailed Role Breakdown

### 2.1 ADMIN ROLE

#### Permissions & Logic

**System-Wide Access**: Admins bypass ALL policy checks automatically via the `before()` method in `ServiceRequestPolicy`:

```php
public function before(User $user, string $ability): bool|null
{
    if ($user->hasRole('admin')) {
        return true;  // Admin bypass - no further checks
    }
    return null;
}
```

**Assigned Permissions** (from `AdvancedRbacSeeder.php`):

| Category | Permissions |
|----------|-------------|
| **Service Requests** | `request.view.all`, `request.view_nearby`, `request.accept`, `request.complete`, `request.update.any`, `request.delete.any` |
| **User Management** | `user.create`, `user.view.any`, `user.update.any`, `user.delete.any`, `user.assign.roles`, `user.grant.permissions` |
| **Role Management** | `role.create`, `role.view`, `role.update`, `role.delete`, `permission.create`, `permission.update`, `permission.delete` |
| **System Admin** | `system.monitor`, `system.logs`, `system.config`, `system.backup` |
| **AI Features** | `ai.enhance.description`, `ai.categorize.request`, `ai.suggest.pricing` |
| **Subscription** | `subscription.view`, `subscription.upgrade`, `subscription.manage.any` |
| **File Management** | `file.upload`, `file.view.any`, `file.delete.any` |
| **Analytics** | `analytics.view`, `analytics.export` |

**Admin-Only Restrictions**: Cannot assign `role.hierarchy.manage` (reserved for Super Admin)

#### Dashboard Capabilities

**Route**: `/dashboard/admin`

| Feature | Description |
|---------|-------------|
| **Platform Overview** | View system-wide statistics (paid accounts, total requests, pending queue, completed trips) |
| **User Management** | List all users with pagination, view user details, upgrade/downgrade user plans |
| **Permission Control Center** | Granular permission matrix - toggle individual permissions per user in real-time |
| **Pending Subscriptions** | Review and approve subscription upgrades |
| **Role Management** | Create, update, delete roles; manage role hierarchy; assign permissions to roles |
| **Request Monitoring** | View all service requests across the platform |

**API Endpoints**:
- `GET /api/admin/stats` - System statistics
- `GET /api/admin/users` - List all users
- `PATCH /api/admin/users/{user}/plan` - Update user plan
- `POST /api/admin/users/{user}/permissions` - Sync user permissions
- `GET /api/admin/subscriptions/pending` - Pending approvals
- `POST /api/admin/subscriptions/{subscription}/accept` - Approve subscription

#### Day-in-the-Life Scenario: Admin

**Morning (System Monitoring)**
1. Logs into `/dashboard/admin`
2. Reviews overnight stats: 3 new paid accounts, 47 total requests, 12 pending in queue
3. Checks system health via monitoring widgets

**Mid-Morning (User Management)**
4. Reviews pending subscription upgrades (3 users waiting)
5. Approves a Premium upgrade for customer "sarah@example.com"
6. Downgrades a churned user from Premium to Free

**Afternoon (Permission Override)**
7. Receives escalation: Provider needs temporary access to view ALL requests for training
8. Navigates to Permissions Control Center (`/dashboard/admin/permissions`)
9. Finds the provider user, toggles ON `request.view_all` permission
10. Provider gains immediate access (no re-login required)

**Evening (Analytics)**
11. Exports monthly analytics report showing:
   - Conversion rate: Free → Paid
   - Request completion times by provider
   - Geographic distribution of requests

---

### 2.2 PROVIDER ROLE (Provider Admin & Provider Employee)

#### Role Distinction

| Role | Use Case | Level |
|------|----------|-------|
| `provider_admin` | Company owner/manager who can view all company requests and manage team | 3 |
| `provider` (employee) | Individual worker who only sees their assigned tasks | 2 |

**Registration Logic**: When a user registers with `role=provider`, the system automatically assigns `provider_admin`:

```php
$role = $request->role;
if ($role === 'provider')
    $role = 'provider_admin';
```

#### Permissions & Logic

**Provider Admin Permissions**:

| Category | Permissions |
|----------|-------------|
| **Service Requests** | `request.view.all`, `request.view_nearby`, `request.accept`, `request.complete` |
| **User Management** | `user.view.any`, `user.assign.roles` |
| **Role & Permission** | `role.view`, `subscription.manage.any` |
| **File Management** | `file.view.any`, `file.delete.any` |
| **AI Features** | `ai.enhance.description`, `ai.categorize.request`, `ai.suggest.pricing` |
| **Analytics** | `analytics.view`, `analytics.export` |

**Provider (Employee) Permissions**:

| Category | Permissions |
|----------|-------------|
| **Service Requests** | `request.view_nearby`, `request.accept`, `request.complete`, `request.view.own`, `request.update.own` |
| **User Profile** | `user.view.own`, `user.update.own` |
| **File Management** | `file.upload`, `file.view.own`, `file.delete.own` |
| **AI Features** | `ai.enhance.description`, `ai.categorize.request`, `ai.suggest.pricing` |
| **Subscription** | `subscription.view`, `subscription.upgrade` |

#### Key Business Logic

**Request View Scoping** (`ServiceRequestController.php`):

```php
if ($user->hasRole(['provider_admin', 'provider_employee'])) {
    // Providers see pending requests + their own accepted/completed
    $query->where(function ($q) use ($user) {
        $q->where('status', 'pending')
            ->orWhere('provider_id', $user->id);
    });
}
```

**Accept Rules** (via `ServiceRequestPolicy.php`):
1. User must have `request.accept` permission
2. User CANNOT accept their own request (throws `CannotAcceptOwnOrderException`)
3. Request must be in `pending` status
4. Race-condition protected via database lock

**Complete Rules**:
1. Only the ASSIGNED provider can complete
2. Request must be in `accepted` status

#### Dashboard Capabilities

**Route**: `/dashboard/provider`

| Feature | Provider Admin | Provider Employee |
|---------|----------------|-------------------|
| **Request Views** | All pending + own assigned | All pending + own assigned |
| **Nearby Orders** | Geo-filtered by radius (5-100km) | Geo-filtered by radius |
| **Accept Requests** | Yes | Yes |
| **Complete Orders** | Yes (own only) | Yes (own only) |
| **Team Management** | View/manage employees | No |
| **Analytics** | Full company analytics | Personal metrics only |

**Real-time Features**:
- Live notifications via WebSocket (`useEcho` hook)
- Auto-refresh when new requests are created
- Distance calculation for nearby orders

**API Endpoints**:
- `GET /api/requests` - List requests (scoped)
- `GET /api/requests/nearby?lat={}&lng={}&radius={}` - Geo-filtered pending requests
- `PATCH /api/requests/{id}/accept` - Accept a request
- `PATCH /api/requests/{id}/complete` - Mark as completed

#### Day-in-the-Life Scenario: Provider

**Morning (Finding Work)**
1. Provider "Ahmed" logs into `/dashboard/provider`
2. Switches to "Nearby Orders" tab
3. Sets radius to 20km, clicks "Update Location"
4. System shows 8 pending requests within range, sorted by distance

**Accepting a Job**
5. Ahmed sees a "Kitchen Equipment Repair" request 3.2km away
6. Clicks "ACCEPT REQUEST" button
7. System verifies:
   - Ahmed has `request.accept` permission ✓
   - Request is not his own ✓
   - Request is still pending (DB lock prevents race conditions) ✓
8. Request status changes to "accepted", Ahmed becomes `provider_id`
9. Customer receives real-time notification: "Your request was accepted by Ahmed"

**Completing the Job**
10. Ahmed travels to customer location
11. Completes the repair
12. Clicks "MARK COMPLETED" button
13. System verifies:
    - Ahmed is the assigned provider ✓
    - Status is "accepted" ✓
14. Status changes to "completed"
15. Customer receives notification: "Your request was completed!"

**Admin Override Scenario**
16. Ahmed needs to see all requests in the system for training
17. Admin grants `request.view_all` permission via Permission Control Center
18. Ahmed immediately sees "All Requests" tab appear in his dashboard

---

### 2.3 CUSTOMER ROLE

#### Permissions & Logic

**Customer Permissions** (from `AdvancedRbacSeeder.php`):

| Category | Permissions |
|----------|-------------|
| **Service Requests** | `request.create`, `request.view.own`, `request.update.own`, `request.delete.own` |
| **User Profile** | `user.view.own`, `user.update.own` |
| **File Management** | `file.upload`, `file.view.own`, `file.delete.own` |
| **AI Features** | `ai.enhance.description`, `ai.suggest.pricing` |
| **Subscription** | `subscription.view`, `subscription.upgrade` |

**Subscription Limit Gate** (`ServiceRequestPolicy.php`):

```php
public function create(User $user): bool
{
    if (!$user->can('request.create')) {
        return false;
    }

    // Free users can only create up to 3 requests
    if ($user->plan === 'free') {
        $count = ServiceRequest::where('customer_id', $user->id)->count();
        if ($count >= 3) {
            return false;  // Limit reached
        }
    }
    return true;
}
```

**Request View Scoping**:
- Customers can ONLY see their own requests (`customer_id = user.id`)
- Cannot view pending requests from other customers
- Can view status and assigned provider for their requests

#### Dashboard Capabilities

**Route**: `/dashboard/customer`

| Feature | Description |
|---------|-------------|
| **Create Request** | Form with title, description, location (lat/lng), AI enhancement option |
| **My Requests List** | Filterable by status (all/pending/accepted/completed), searchable |
| **Usage Stats** | Visual stats: Total, Pending, In Progress, Completed |
| **Provider Info** | Shows assigned provider name when request is accepted |
| **Upgrade Banner** | Prominent upgrade prompt for free users (shows 3/3 limit) |
| **Real-time Updates** | WebSocket notifications when request status changes |

**Plan Limitations**:

| Plan | Request Limit | AI Features | Priority Support |
|------|---------------|-------------|------------------|
| Free | 3 requests/month | Basic | No |
| Premium | Unlimited | Full | Yes |
| Enterprise | Unlimited | Full + API Access | Yes |

**API Endpoints**:
- `POST /api/requests` - Create new request (with `check.limit` middleware)
- `GET /api/requests` - List own requests only
- `GET /api/subscription/usage` - Check current usage
- `POST /api/subscription/upgrade` - Upgrade plan

#### Day-in-the-Life Scenario: Customer

**Morning (Creating a Request)**
1. Customer "Fatima" logs into `/dashboard/customer`
2. Dashboard shows: 1 total request, 0 pending, 1 completed
3. Free plan banner shows: "1/3 requests used"
4. Clicks "NEW REQUEST" button

**Filling the Request**
5. Enters title: "Refrigerator not cooling"
6. Enters description: "My fridge stopped working yesterday"
7. Clicks "Enhance with AI" button (sends to `POST /api/ai/enhance`)
8. AI returns: "Professional Request: Refrigerator repair and restoration required. Unit fails to maintain temperature. Assistance needed as soon as possible."
9. Location auto-detected (Riyadh: 24.7136, 46.6753)
10. Submits request

**Tracking Progress**
11. Request appears in Fatima's list with "pending" status (amber badge)
12. Fatima receives real-time notification when a provider accepts
13. Dashboard updates: Provider "Ahmed" is now assigned
14. Status changes to "accepted" (indigo badge)

**Completion**
15. Fatima receives notification: "Your request was completed!"
16. Opens dashboard, sees "completed" status (emerald badge)
17. Can see Ahmed was the provider who completed the job

**Limit Scenario**
18. Fatima creates 2 more requests (total: 3)
19. "NEW REQUEST" button becomes disabled
20. Banner updates: "3/3 requests used - Upgrade to Pro"
21. Fatima clicks upgrade, subscribes to Premium
22. Button re-enables immediately, limit removed

---

## 3. Permission Categories & System

### 3.1 Permission Categories (7 Total)

| ID | Name | Icon | Description |
|----|------|------|-------------|
| 1 | Service Requests | clipboard-list | Request lifecycle management |
| 2 | User Management | users | User/role administration |
| 3 | System Administration | cog | System-level operations |
| 4 | AI Features | brain | AI-powered enhancements |
| 5 | Subscription Management | credit-card | Billing and plans |
| 6 | File Management | file | Uploads and attachments |
| 7 | Monitoring & Analytics | chart-bar | Reports and metrics |

### 3.2 Scoped Permissions

Location-based permissions with automatic scope enforcement:

| Permission | Scope | Values |
|------------|-------|--------|
| `request.view_nearby` | location | max_distance: 50km |
| `request.accept` | location | max_distance: 50km |

### 3.3 System vs Custom Permissions

```php
// System permissions (cannot be deleted)
'is_system' => true

// Custom permissions (created by admins)
'is_system' => false
```

---

## 4. Frontend Authorization

### 4.1 Middleware Protection (`middleware.ts`)

```typescript
// Role-based granular protection
if (pathname.startsWith('/dashboard/admin') && role !== 'admin') {
    return NextResponse.redirect(new URL(getDashboardPath(role), request.url));
}
if (pathname.startsWith('/dashboard/provider') && !['provider_admin', 'provider_employee'].includes(role || '')) {
    return NextResponse.redirect(new URL(getDashboardPath(role), request.url));
}
if (pathname.startsWith('/dashboard/customer') && role !== 'customer') {
    return NextResponse.redirect(new URL(getDashboardPath(role), request.url));
}
```

### 4.2 Client-Side Guards (Layout Components)

Each dashboard has a role-check in its layout:

```jsx
// Admin Layout
useEffect(() => {
    if (!loading && (!user || !user.roles?.includes('admin'))) {
        router.replace('/login');
    }
}, [user, loading, router]);

// Provider Layout
const PROVIDER_ROLES = ['provider_admin', 'provider_employee'];
const isProvider = user?.roles?.some(r => PROVIDER_ROLES.includes(r));

// Customer Layout
if (!loading && (!user || !user.roles?.includes('customer'))) {
    router.replace('/login');
}
```

### 4.3 Authorization Service (`AuthorizationContext.jsx`)

Provides permission checking methods:

| Method | Purpose |
|--------|---------|
| `hasRole(role)` | Check single role |
| `hasAnyRole(roles[])` | Check if user has any of the roles |
| `hasAllRoles(roles[])` | Check if user has all roles |
| `hasMinimumRole(role)` | Check hierarchy level |
| `hasPermission(permission)` | Check specific permission |
| `canAccessRoute(route)` | Route-based access control |
| `getDashboardPath()` | Get appropriate dashboard |

---

## 5. API Security Layer

### 5.1 Middleware Stack

```php
Route::middleware('api-security')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        // Protected routes
        Route::middleware('check.permission:request.view_nearby')->get('/requests/nearby');
        Route::middleware('check.limit')->post('/requests');
        Route::middleware('role:admin')->prefix('admin');
    });
});
```

### 5.2 Policy Enforcement Flow

1. **Middleware Check** - Route-level coarse filtering
2. **Policy Gate** - Controller authorization (`$this->authorize()`)
3. **Business Logic** - Model-level validation (ownership, status, etc.)
4. **Database Lock** - Race condition prevention for critical operations

---

## 6. Summary Matrix

### 6.1 Role Capabilities Comparison

| Capability | Admin | Provider Admin | Provider | Customer |
|------------|-------|----------------|----------|----------|
| **Create Requests** | ✓ (any) | ✓ | ✓ | ✓ (own, limited) |
| **View All Requests** | ✓ | ✓ (with perm) | ✗ | ✗ |
| **View Own Requests** | ✓ | ✓ | ✓ | ✓ |
| **View Nearby Requests** | ✓ | ✓ | ✓ | ✗ |
| **Accept Requests** | ✓ | ✓ | ✓ | ✗ |
| **Complete Requests** | ✓ | ✓ (own) | ✓ (own) | ✗ |
| **Manage Users** | ✓ | ✓ (limited) | ✗ | ✗ |
| **Manage Permissions** | ✓ (not hierarchy) | ✗ | ✗ | ✗ |
| **View Analytics** | ✓ (system) | ✓ (company) | ✓ (personal) | ✓ (personal) |
| **Manage Subscriptions** | ✓ (any) | ✓ (any) | ✓ (own) | ✓ (own) |
| **Access AI Features** | ✓ | ✓ | ✓ | ✓ (limited) |

### 6.2 Dashboard Routes

| Role | Route | Layout File |
|------|-------|-------------|
| Admin | `/dashboard/admin` | `frontend/app/dashboard/admin/layout.jsx` |
| Provider | `/dashboard/provider` | `frontend/app/dashboard/provider/layout.jsx` |
| Customer | `/dashboard/customer` | `frontend/app/dashboard/customer/layout.jsx` |

---

## 7. Real-Time Features

All dashboards use WebSocket connections via `useEcho` hook:

```javascript
useEcho(
    user ? `user.${user.id}` : null,
    'ServiceRequestUpdated',
    callback,
    [user?.id]
);
```

**Events Broadcast**:
- `ServiceRequestCreated` - New request available
- `ServiceRequestAccepted` - Provider assigned
- `ServiceRequestCompleted` - Job finished

---

## 8. Conclusion

The FoodTechSaaS RBAC system provides:

1. **Hierarchical Permission Inheritance** - Reduces permission duplication
2. **Granular Access Control** - 38 permissions across 7 categories
3. **Real-time Authorization** - Permission changes apply immediately
4. **Multi-layered Security** - Middleware + Policy + Business Logic
5. **Flexible Dashboards** - Role-appropriate UI for each user type
6. **Subscription Integration** - Plan limits enforced at policy level

The "Simplified RBAC" successfully balances flexibility with maintainability, allowing admins to override permissions per-user while maintaining a clear role hierarchy for default access patterns.
