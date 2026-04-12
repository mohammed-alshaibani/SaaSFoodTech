# SaaSFoodTech: Service Marketplace Platform (MVP)

A premium, fullstack Service Marketplace designed as a Senior Engineering Take-Home Assignment. This platform enables customers to request services and providers to fulfill them based on location, while enforcing complex role-based access and subscription-based feature gating.

---

## 🏗️ Architecture Overview

The system follows a **Modular Monolith** architecture designed for scalability, maintainability, and visual excellence.

-   **Backend**: [Laravel 11](https://laravel.com/) (PHP 8.2+) serving a stateless RESTful API.
    -   Uses **JWT (Sanctum)** for secure, stateless authentication.
    -   Implements **Service Layer** pattern (`MainService`) to consolidate business logic.
    -   Leverages **MySQL 8 Spatial Indexes** for high-performance proximity searches.
-   **Frontend**: [Next.js 14](https://nextjs.org/) (React) with App Router.
    -   **Tailwind CSS** for a premium, responsive UI.
    -   **React Context API** for global state (Auth, Permissions).
    -   **Zustand** or Context for lightweight, performant state management.
-   **Real-time**: [Laravel Reverb](https://laravel.com/docs/11.x/reverb) for WebSocket-driven status updates.
-   **Containerization**: Fully Dockerized environment via Docker Compose.

---

## 📂 Key Features & Implementation

### 1. 🔐 Advanced RBAC Design
The system implements a dynamic, hierarchical RBAC system using `Spatie/Laravel-Permission`.

-   **Roles**: `Super Admin`, `Admin`, `Provider Admin`, `Provider`, `Customer`, and `Guest`.
-   **Hierarchy**: Roles inherit permissions (e.g., `Admin` inherits `Provider` capabilities).
-   **Dynamic Permissions**: Permissions are not hardcoded. Administrators can revoke/assign permissions (`request.create`, `request.accept`, etc.) via the Admin Dashboard.
-   **Enforcement**: 
    -   **API Level**: Handled via `CheckPermission` middleware.
    -   **UI Level**: Context-aware rendering based on user permission sets.

### 2. 📍 Geolocation & Proximity
-   **Storage**: Coordinates (Lat/Long) are stored with every Service Request and Provider profile.
-   **Search**: Uses the **Haversine Formula** with MySQL's `ST_Distance_Sphere` for millisecond-latency nearby request filtering (default 50km radius).
-   **MVP Optimization**: Includes a bounding-box fallback logic for non-spatial databases (SQLite) used in testing.

### 3. 🤖 AI-Powered Automation
Included an AI enhancement feature to improve service quality:
-   **Gemini AI Integration**: Automatically "professionalizes" service descriptions during creation using Google's Gemini 1.5 Flash API.
-   **Reliability**: Implements a **Local Fallback Engine** that uses rule-based text processing if the external AI API is unavailable or has limited quota.

### 4. 💳 Subscription & Feature Gating
-   **Logic**: enforced via `MainService` and `User` model scopes.
-   **Limits**: 
    -   **Free Users**: Capped at 3 active requests per month.
    -   **Pro Users**: Unlimited requests and access to AI enhancement features.
-   **Mock Workflow**: Includes a simulated payment/upgrade flow to demonstrate system extensibility for Stripe/PayPal.

---

## 🛠️ Setup & Running

### 🐳 Docker (Recommended)
1.  **Clone & Environment**:
    ```bash
    cp backend/.env.example backend/.env
    ```
2.  **Start Services**:
    ```bash
    docker-compose up -d
    ```
3.  **Initialize Database**:
    ```bash
    docker-compose exec backend php artisan key:generate
    docker-compose exec backend php artisan migrate --seed
    ```

### 🚀 Manual Setup
-   **Backend**: `cd backend && composer install && php artisan serve`
-   **Frontend**: `cd frontend && npm install && npm run dev`

---

##  API Documentation
A comprehensive **Postman Collection** is provided in the root:
👉 [SaaSFoodTech.postman_collection.json](./SaaSFoodTech.postman_collection.json)

It includes 50+ documented endpoints with example payloads for:
- Authentication & Multi-Role Registration
- Service Request Lifecycle (Pending → Accepted → Completed)
- Subscription Management
- Admin CRUD Operations

---

##  Design Decisions & Trade-offs
-   **Trade-off: Mock Payments**: Opted for a mock subscription state instead of real Stripe integration to allow the reviewer to test the upgrade flow without config overhead.
-   **Strategy: Spatial Queries**: Used native MySQL spatial functions instead of PHP-side filtering to demonstrate "Senior" database optimization skills.

---

## 🔧 Future Improvements
-   **Mobile App**: PWA or React Native companion for field workers.
-   **Advanced Logging**: ELK stack integration for request auditing.

---
**Author**: Mohammed Al-Shaibani
