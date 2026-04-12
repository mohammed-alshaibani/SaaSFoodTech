# SaaSFoodTech Service Marketplace Platform

A robust, fullstack MVP for a service marketplace where customers create service requests and providers fulfill them based on location and real-time availability. Built on Laravel 11 and Next.js 14, this platform demonstrates advanced RBAC, AI-powered automation, and specialized geolocation handling.

## Architecture Overview

The system is designed as a modular monolith focused on scalability and clarity.

- **Backend (Laravel 11)**: Operates as a RESTful API with stateless JWT authentication. Business logic is organized into Controllers and Middleware to ensure strict separation of concerns.
- **Frontend (Next.js 14)**: A high-performance SPA (Single Page Application) utilizing the App Router and React Context for responsive state management.
- **Data Layer**: Relational schema (MySQL) optimized for geolocation queries and role-based permissions.
- **Real-time Layer**: Laravel Echo and Reverb handle WebSocket broadcasts for instant status updates.

## Folder Structure

### Detailed Folder Structure

#### 🛡️ Backend (`/backend`)
- `app/Http/Controllers`: Unified logic for **Authentication**, **Subscription**, **Service Requests**, and **Admin** operations.
- `app/Http/Middleware`: Specialized gates for **RBAC Enforcement**, **Subscription Gating**, and **API Security**.
- `app/Models`: Database entities incorporating Laravel's Eloquent ORM with custom business logic (e.g., `getCurrentMonthUsage`).
- `app/Events & app/Observers`: Automated notification system and real-time WebSocket broadcasting triggers.
- `app/Exceptions`: Standardized API error responses for better frontend consumption.
- `app/Providers`: Core service registration for **Authentication** and **Architecture Strategies**.
- `database/migrations`: Relational schema for Users, Roles, Requests, and Subscriptions.
- `routes/api.php`: Centralized, clean, and version-ready API routes.

#### 🎨 Frontend (`/frontend`)
- `app/dashboard`: Modular views for **Admin**, **Provider**, and **Customer** tailored to role-specific requirements.
- `app/(auth)`: Secure flows for login and multi-role registration.
- `components/`: UI library categorized into **Layouts**, **Auth**, **Subscription**, and **Animations**.
- `context/`: Deep-state management for **Auth**, **Permissions**, and **Language** (RTL support).
- `hooks/`: Reusable React logic, such as `useEcho` for real-time request status listening.
- `lib/api.js`: Centrally managed Axios instance featuring automatic JWT attachment and error handling.

## Architecture & Design Patterns

The project follows **SOLID principles** and clear architectural patterns to ensure high quality and maintainability.

### 📐 Design Patterns
- **MVC (Model-View-Controller)**: Strict separation of concerns between data (Models), business flow (Controllers), and interface (Next.js components).
- **Middleware Pattern**: A "Pipeline" approach for cross-cutting concerns like security and role validation.
- **Observer Pattern**: Laravel Observers automatically trigger events when database states change (e.g., a Request is marked "Completed").
- **Dependency Injection (DI)**: Extensively used via Laravel's service container to decouple services and increase testability.
- **Context API (State Management)**: Chose over Redux for its simplicity and native performance in handling global Auth/Language states.

### 🛡️ SOLID Implementation
- **Single Responsibility (SRP)**: Each controller is focused on one feature set (e.g., `SubscriptionController` only handles plans and upgrades).
- **Open-Closed Principle (OCP)**: The permission system is designed to be extensible; adding new features doesn't require modifying the core auth middleware.
- **Interface Segregation**: Used in service layers to ensure components only depend on the methods they actually use.
- **Dependency Inversion**: High-level modules don't depend on low-level UI details; they communicate via standardized API DTOs and Interfaces.

## Key Features & Implementation

### 1. 🔐 Advanced RBAC Design
The platform implements an **Advanced RBAC** system using a combination of `Spatie/Laravel-Permission` and custom middleware.

- **Storage**: Permissions are stored in specific tables (`permissions`, `role_has_permissions`) allowing for dynamic changes without code redeployments.
- **Hierarchy**:
    - **Admin**: Complete system visibility and user management.
    - **Provider Admin**: Can manage permissions for their own team/employees.
    - **Provider Employee**: Can accept/complete requests but has limited management access.
    - **Customer**: Strictly limited to their own request lifecycle.
- **Dynamic Permissions**: Permissions are not hardcoded. Administrators can revoke/assign permissions (`request.create`, `request.accept`, etc.) via the Admin Dashboard.
- **Enforcement**:
    - **API Level**: Handled via `CheckPermission` middleware.
    - **UI Level**: Context-aware rendering based on user permission sets.

### 2. Geolocation & Proximity
- **Storage**: Coordinates (Lat/Long) are stored with every Service Request and Provider profile.
- **Search**: Uses the **Haversine Formula** with MySQL's `ST_Distance_Sphere` for millisecond-latency nearby request filtering (default 50km radius).
- **MVP Optimization**: Includes a bounding-box fallback logic for non-spatial databases (SQLite) used in testing.

### 3. AI-Powered Automation
Included an AI enhancement feature to improve service quality:

- **Gemini AI Integration**: Automatically "professionalizes" service descriptions during creation using Google's Gemini 1.5 Flash API.
- **Reliability**: Implements a **Local Fallback Engine** that uses rule-based text processing if the external AI API is unavailable or has limited quota.

### 4. Subscription & Feature Gating
- **Logic**: enforced via `MainService` and `User` model scopes.
- **Limits**:
    - **Free Users**: Capped at 3 active requests per month.
    - **Pro Users**: Unlimited requests and access to AI enhancement features.
- **Mock Workflow**: Includes a simulated payment/upgrade flow to demonstrate system extensibility for Stripe/PayPal.

## Key Design Decisions & Trade-offs
- **Pattern Simplicity**: Chose to collapse complex Service/Repository layers into straightforward Controllers for the MVP. This increases readability and simplifies the onboarding process for new developers (Line 115 of requirements).
- **Geolocation Strategy**: Implemented a mathematical approach (Haversine formula) for "Nearby" filtering directly in the database logic to avoid external API costs while maintaining high accuracy.
- **Mock Payment**: Implemented a mock credit card simulation for plan upgrades to demonstrate the workflow without requiring actual Stripe/PayPal credentials.
- **AI Enhancement**: Integrated Google Gemini API to automatically improve service request descriptions, adding professional value to customer postings.

## Default Credentials

Admin: admin@example.com / password
Provider: provider@example.com / password
Customer: john@example.com / password

## Setup Instructions

### Using Docker (Recommended)
1. **Start the environment**:
   ```bash
   docker-compose up -d
   ```
2. **Initialize Backend**:
   ```bash
   docker-compose exec backend composer install
   docker-compose exec backend php artisan key:generate
   docker-compose exec backend php artisan migrate --seed
   ```

### 🚀 Manual Setup
1. **Backend**:
   ```bash
   cd backend && composer install
   cp .env.example .env
   php artisan migrate --seed
   php artisan serve
   ```
2. **Frontend**:
   ```bash
   cd frontend && npm install
   npm run dev
   ```

## API Documentation & Tests
- **API Docs**: A full Postman collection is located at the root provided as `SaaSFoodTech.postman_collection.json`. It includes 50+ endpoints covering all lifecycles.
- **Tests**: Run `php artisan test` in the `/backend` directory to verify core functionality and RBAC enforcement.

## Future Improvements
- **Mobile Application**: Porting the Next.js logic to React Native for field-ready providers.
- **Advanced Maps**: Integrating Google Maps/Leaflet for visual request clustering.
- **Payment Gateway**: Moving from mock payment to Stripe/PayPal production integration.
- **ElasticSearch**: Implementing full-text search and advanced AI-driven matching engines.

