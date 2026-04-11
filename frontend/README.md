# SaaSFoodTech Frontend

A modern, responsive frontend application for the SaaSFoodTech service marketplace platform, built with Next.js 14 and featuring role-based dashboards, real-time updates, and comprehensive user management.

## **Features**

### **Core Functionality**
- **Role-based Dashboards**: Customer, Provider, and Admin interfaces
- **Service Request Management**: Create, view, and track service requests
- **Geolocation Features**: Find and filter nearby service requests
- **Real-time Updates**: WebSocket integration for live status updates
- **Subscription Management**: Plan upgrades and usage tracking

### **Advanced Features**
- **AI-Powered Enhancements**: Request description enhancement
- **File Attachments**: Support for request documentation
- **Multi-language Support**: RTL/LTR support with internationalization
- **Responsive Design**: Mobile-first approach with Tailwind CSS
- **Authentication Flow**: Secure JWT-based authentication

## **Technology Stack**

### **Core Framework**
- **Next.js 14**: React framework with App Router
- **React 18**: Modern React with hooks and context
- **TypeScript**: Type-safe development (optional)

### **Styling & UI**
- **Tailwind CSS**: Utility-first CSS framework
- **Lucide Icons**: Modern icon library
- **Framer Motion**: Smooth animations and transitions

### **State Management**
- **React Context**: Global state management
- **Custom Hooks**: Reusable logic extraction
- **Local Storage**: Client-side data persistence

### **HTTP & Real-time**
- **Axios**: HTTP client with interceptors
- **Laravel Echo**: WebSocket integration
- **Pusher.js**: Real-time event handling

## **Project Structure**

```
frontend/
app/
  (auth)/                 # Authentication routes
    login/
    register/
  dashboard/               # Role-based dashboards
    admin/
    customer/
    provider/
  subscription/           # Subscription management
  page.jsx               # Landing page
components/
  auth/                  # Authentication components
  landing/               # Landing page components
  subscription/          # Subscription components
context/                # React contexts
  AuthContext.jsx       # Authentication state
  AuthorizationContext.jsx # Role/permission state
  SubscriptionContext.jsx # Subscription state
  AppContext.jsx        # Combined context provider
hooks/                  # Custom React hooks
lib/                    # Utility libraries
middleware.ts           # Next.js middleware
public/                 # Static assets
```

## **Getting Started**

### **Prerequisites**
- Node.js 18+ 
- npm or yarn package manager
- Backend API running on localhost:8000

### **Installation**

1. **Clone the repository**
```bash
git clone <repository-url>
cd saas-foodtech-platform/frontend
```

2. **Install dependencies**
```bash
npm install
# or
yarn install
```

3. **Environment setup**
```bash
cp .env.example .env.local
# Configure your API URL and other settings
```

4. **Run development server**
```bash
npm run dev
# or
yarn dev
```

5. **Open browser**
Navigate to [http://localhost:3000](http://localhost:3000)

### **Environment Variables**

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
NEXT_PUBLIC_WS_URL=ws://localhost:6001
NEXT_PUBLIC_APP_NAME=SaaSFoodTech
```

## **Architecture Overview**

### **Component Architecture**
- **Atomic Design**: Components organized by size and reusability
- **Container/Presentation Pattern**: Logic separated from UI
- **Custom Hooks**: Business logic extracted into reusable hooks

### **State Management**
- **Context-based**: Global state using React Context API
- **Separated Concerns**: Auth, Authorization, and Subscription contexts
- **Optimization**: Context split to prevent unnecessary re-renders

### **Authentication Flow**
1. User logs in via LoginForm component
2. JWT token stored in HTTP-only cookie
3. AuthenticationContext manages user state
4. AuthorizationContext handles role-based routing
5. Automatic session refresh and logout handling

### **Role-Based Access Control (RBAC)**
The frontend uses a custom `AuthorizationContext` to manage permissions and roles provided by the backend.

- **Customer**: 
  - Views own service requests.
  - Creates new requests (limited by subscription quota).
  - Can upgrade plan to "Paid" for unlimited requests.
- **Provider**: 
  - Access to a global feed of "Pending" requests.
  - Filtering by location/radius.
  - Ability to "Accept" and "Complete" requests.
- **Admin**: 
  - System-wide statistics.
  - User management (view/edit plans).
  - Permission management (dynamic assignment).

### **Key Design Decisions & Trade-offs**
- **Simplicity over Map UI**: Decided to use a clean list/card view with radius filtering rather than a full map implementation (Google Maps/Leaflet) to keep the MVP focused on the core request lifecycle.
- **Context API for State**: Chose React Context over Redux or Zustand for its native integration and sufficient capabilities for an MVP of this scale.
- **Synchronous AI Feedback**: Opted for direct AI enhancement calls from the UI with loading states rather than an async polling mechanism to simplify the user experience.
- **Client-Side Permission Enforcement**: Permissions are validated on the client for UI visibility/hiding, but are strictly enforced at every API call on the backend.

### **What Would Be Improved With More Time**
- **Dynamic Map Integration**: A real-time map to visualize nearby requests.
- **Enhanced Form Validation**: More robust client-side validation using libraries like Formik or React Hook Form with Zod.
- **Notification Center**: A dedicated UI for tracking historical notifications and alerts.
- **Offline Support**: PWA capabilities for providers working in areas with poor connectivity.
- **Unit/E2E Test Coverage**: Expanding the suite with Cypress for critical user paths (e.g., complete registration to request fulfillment).

## **Key Features Implementation**

### **Real-time Updates**
```javascript
// WebSocket integration for live updates
import Echo from 'laravel-echo';

window.Echo = new Echo({
    broadcaster: 'reverb',
    wsHost: window.location.hostname,
    wsPort: 6001,
    forceTLS: false,
});
```

### **Geolocation Features**
- **Nearby Requests**: Filter requests by radius
- **Location Services**: Browser geolocation API integration
- **Distance Calculation**: Haversine formula for accurate distances

### **Subscription Management**
- **Plan Comparison**: Interactive plan comparison UI
- **Usage Tracking**: Real-time usage monitoring
- **Upgrade Flow**: Seamless plan upgrade process

### **AI Integration**
- **Request Enhancement**: AI-powered description improvement
- **Smart Suggestions**: Automatic categorization and pricing
- **Background Processing**: Async AI enhancement with status updates

## **API Integration**

### **HTTP Client Configuration**
```javascript
// Axios with interceptors for authentication
const api = axios.create({
    baseURL: process.env.NEXT_PUBLIC_API_URL,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});
```

### **Error Handling**
- **Global Error Handler**: Centralized error processing
- **User-friendly Messages**: Translated error messages
- **Retry Logic**: Automatic retry for failed requests

### **Request Interceptors**
- **Authentication**: Automatic token attachment
- **Response Processing**: Standardized response format
- **Error Logging**: Client-side error tracking

## **Performance Optimizations**

### **Code Splitting**
- **Route-based**: Automatic code splitting by routes
- **Component-based**: Dynamic imports for heavy components
- **Bundle Analysis**: Optimized bundle sizes

### **Caching Strategy**
- **API Response Caching**: Client-side caching for static data
- **Image Optimization**: Next.js Image component usage
- **Font Optimization**: Efficient font loading

### **Performance Monitoring**
- **Web Vitals**: Core Web Vitals tracking
- **Bundle Analysis**: Regular bundle size monitoring
- **Performance Budgets**: Performance budgets enforcement

## **Testing**

### **Unit Tests**
```bash
npm run test
# or
yarn test
```

### **Integration Tests**
```bash
npm run test:integration
# or
yarn test:integration
```

### **E2E Tests**
```bash
npm run test:e2e
# or
yarn test:e2e
```

### **Test Coverage**
- **Component Testing**: React component unit tests
- **Hook Testing**: Custom hook testing
- **API Testing**: Mock API responses
- **User Flow Testing**: Critical user journey tests

## **Deployment**

### **Build for Production**
```bash
npm run build
# or
yarn build
```

### **Start Production Server**
```bash
npm run start
# or
yarn start
```

### **Docker Deployment**
```bash
docker build -t saasfoodtech-frontend .
docker run -p 3000:3000 saasfoodtech-frontend
```

### **Environment Configuration**
- **Development**: Local development with hot reload
- **Staging**: Pre-production testing environment
- **Production**: Optimized build with security headers

## **Browser Support**

- **Chrome**: 90+
- **Firefox**: 88+
- **Safari**: 14+
- **Edge**: 90+
- **Mobile Safari**: 14+
- **Chrome Mobile**: 90+

## **Security Features**

### **Authentication Security**
- **HTTP-only Cookies**: Secure token storage
- **CSRF Protection**: Cross-site request forgery prevention
- **XSS Prevention**: Input sanitization and output encoding

### **Data Protection**
- **HTTPS Enforcement**: Secure communication
- **Content Security Policy**: Restrict resource loading
- **Privacy Controls**: User data protection measures

## **Accessibility**

### **WCAG 2.1 Compliance**
- **Keyboard Navigation**: Full keyboard accessibility
- **Screen Reader Support**: ARIA labels and semantic HTML
- **Color Contrast**: WCAG AA compliant color ratios
- **Focus Management**: Logical focus flow

### **Internationalization**
- **RTL/LTR Support**: Right-to-left language support
- **Multi-language**: Translation ready architecture
- **Cultural Adaptation**: Locale-specific formatting

## **Contributing**

1. **Fork the repository**
2. **Create feature branch**: `git checkout -b feature/amazing-feature`
3. **Commit changes**: `git commit -m 'Add amazing feature'`
4. **Push to branch**: `git push origin feature/amazing-feature`
5. **Open Pull Request**

### **Code Style**
- **ESLint**: JavaScript/React linting
- **Prettier**: Code formatting
- **TypeScript**: Type checking (if enabled)
- **Husky**: Pre-commit hooks

## **Troubleshooting**

### **Common Issues**
- **CORS Errors**: Check API CORS configuration
- **Authentication Issues**: Verify token storage and API URL
- **Build Failures**: Clear cache and reinstall dependencies
- **WebSocket Issues**: Check Reverb server status

### **Debug Mode**
```bash
# Enable debug logging
DEBUG=true npm run dev

# Clear all caches
npm run clean
```

## **Performance Metrics**

### **Core Web Vitals**
- **LCP**: < 2.5s (Largest Contentful Paint)
- **FID**: < 100ms (First Input Delay)
- **CLS**: < 0.1 (Cumulative Layout Shift)

### **Bundle Size**
- **Initial Load**: < 250KB gzipped
- **Route Chunks**: < 50KB gzipped each
- **Image Optimization**: WebP format with lazy loading

---
**Built with React, Next.js, and Tailwind CSS**
