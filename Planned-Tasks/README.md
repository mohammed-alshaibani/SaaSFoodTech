# 🛠 ServiceHub — Service Marketplace Platform

A secure, scalable Service Marketplace built with **Laravel 11 (API)** and **Next.js 14 (App Router)**. This platform features robust RBAC, subscription gating, and AI-powered service description enhancement.

---

## 🏗 Architecture Overview

The system follows a decoupled **API-First** architecture:

- **Backend**: Laravel 11 acting as a stateless API using Sanctum for authentication.
- **Frontend**: Next.js 14 utilizing the App Router, Server Components where applicable, and a robust Middleware layer for route protection.
- **Database**: Supports **MySQL 8** (Production) and **SQLite** (Local Development) with automatic geolocation fallbacks.
- **State Management**: React Context API for Global Auth state and native `fetch/Axios` for data synchronization.

---

## 🔐 RBAC & Security Design

The project implements an **Advanced RBAC** system using `Spatie Laravel-Permission`:

1.  **Fine-Grained Policies**: Every model operation (`create`, `accept`, `complete`, `view`) is governed by a **Laravel Policy**. Authorization is NOT hardcoded in controllers.
2.  **Dynamic Permissions**: While users have roles (Admin, Provider, Customer), the Admin can assign **Direct Permission Overrides** via the Admin Dashboard.
3.  **httpOnly Cookie Session**:
    - Tokens are NOT stored in `localStorage` (XSS Vulnerable).
    - The Next.js frontend uses a Route Handler (`/api/session`) to store the token in an **httpOnly, Secure, SameSite=Lax cookie**.
4.  **Middleware Guards**: Next.js `middleware.ts` performs server-side role verification before rendering any protected routes.

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+ & Composer
- Node.js 18+ & npm
- SQLite (default) or MySQL

### Backend Setup
1. `cd backend`
2. `composer install`
3. `cp .env.example .env` (Set your `OPENAI_API_KEY` here)
4. `php artisan key:generate`
5. `touch database/database.sqlite`
6. `php artisan migrate:fresh --seed`
7. `php artisan serve` (Runs on http://localhost:8000)

### Frontend Setup
1. `cd frontend`
2. `npm install`
3. `npm run dev` (Runs on http://localhost:3000)

**Test Credentials:**
- **Admin**: `admin@test.com` / `password`
- **Customer**: `customer@test.com` / `password`

---

## 📍 Key Features & Trade-offs

### 🗺 Geolocation Fallback
Production uses MySQL's `ST_Distance_Sphere` for high-performance spatial queries. However, for local development efficiency, a **Haversine Formula fallback** is implemented automatically if the driver is detected as `sqlite`.

### 🤖 AI Description Enhancer
Integrated with OpenAI GPT-4o-mini. The system features:
- **Graceful Degradation**: If the AI service is down or quota is hit, the system logs the error and returns the original text, ensuring no user-facing service interruption.
- **Throttling**: The AI endpoint is rate-limited to 10 requests per minute per user to prevent abuse.

### 💳 Subscription Gating
Simulated using a `plan` attribute. Free users are restricted to **3 service requests**. The limit is enforced via atomic database checks in the `CheckRequestLimit` middleware and reflected in the UI via reactive upgrade banners.

---

## 📈 Future Improvements
1. **Real-time Notifications**: Integrate Laravel Reverb/Pusher for instant "Request Accepted" updates.
2. **MediaLibrary**: Use Spatie MediaLibrary for more robust S3-backed attachments.
3. **TypeScript Migration**: Full conversion of frontend components from `.jsx` to `.tsx` for better type safety.
4. **ElasticSearch**: If the marketplace scales, move geolocation and keyword search to a dedicated search engine.

---
