# Swagger/OpenAPI Documentation Access Guide

## **Swagger Documentation is Ready!** 

Your Otex Service Platform API documentation is now available through Swagger UI.

---

## **Access Swagger Documentation**

### **Method 1: Browser Access (Recommended)**
```bash
# Open Swagger UI in your browser
http://localhost:8000/api/documentation
```

### **Method 2: Command Line**
```bash
# Open in default browser
cd "c:\Users\User\Desktop\e-store\Otex App2\OtexApp-v2\FullAppApiOtex\SAAS FullStack\backend"
start http://localhost:8000/api/documentation
```

### **Method 3: Direct File Access**
```bash
# View raw OpenAPI specification
http://localhost:8000/api/docs/api-docs.yaml
```

---

## **What's Available in Swagger UI**

### **Interactive API Documentation**
- **Visual Interface**: Clean, interactive documentation
- **Try It Out**: Test API endpoints directly from browser
- **Authentication**: Built-in JWT token support
- **Request/Response Examples**: Complete examples for all endpoints

### **API Endpoints Documented**

#### **Authentication**
- **POST** `/register` - User registration
- **POST** `/login` - User login
- **GET** `/me` - Get current user profile
- **POST** `/logout` - User logout

#### **Service Requests**
- **GET** `/requests` - List service requests
- **POST** `/requests` - Create new service request
- **GET** `/requests/{id}` - Get specific request
- **PATCH** `/requests/{id}/accept` - Accept request
- **PATCH** `/requests/{id}/complete` - Complete request

#### **File Attachments**
- **POST** `/attachments/upload` - Upload files
- **GET** `/attachments/{id}/download` - Download files
- **DELETE** `/attachments/{id}` - Delete files

#### **Monitoring & Health**
- **GET** `/monitoring/health` - System health check
- **GET** `/monitoring/dashboard` - Performance metrics
- **GET** `/monitoring/metrics` - System metrics

---

## **Using Swagger UI**

### **1. Authentication Setup**
1. Click **Authorize** button (top right)
2. Enter your JWT token in the format: `Bearer your_token_here`
3. Click **Authorize**
4. All endpoints will now be authenticated

### **2. Testing Endpoints**
1. Click on any endpoint in the list
2. Click **Try it out** button
3. Fill in required parameters
4. Click **Execute** to test
5. View response below

### **3. Getting JWT Token**
1. First, register a user via `/register` endpoint
2. Then login via `/login` endpoint
3. Copy the `access_token` from response
4. Use this token in the Authorize dialog

---

## **Swagger UI Features**

### **Interactive Testing**
- **Live API Testing**: Test endpoints directly from browser
- **Parameter Validation**: Automatic validation of required fields
- **Response Formatting**: Pretty-printed JSON responses
- **Error Handling**: Clear error messages and status codes

### **Documentation Features**
- **Schema Definitions**: Complete data models
- **Request Examples**: Sample request bodies
- **Response Examples**: Sample response formats
- **Authentication Info**: JWT authentication details

### **Navigation**
- **Search**: Find endpoints quickly
- **Tagging**: Organized by feature areas
- **Expandable Sections**: Collapsible endpoint details
- **Copy URLs**: Easy endpoint URL copying

---

## **Quick Start Example**

### **Step 1: Register a User**
```json
POST /api/register
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "role": "customer"
}
```

### **Step 2: Login**
```json
POST /api/login
{
  "email": "john@example.com",
  "password": "Password123!"
}
```

### **Step 3: Use Token**
Copy the `access_token` from login response and use it in the Authorize dialog.

### **Step 4: Create Service Request**
```json
POST /api/requests
{
  "title": "Fix my kitchen sink",
  "description": "The kitchen sink is leaking",
  "latitude": 40.7128,
  "longitude": -74.0060,
  "category": "plumbing",
  "urgency": "normal"
}
```

---

## **Alternative Documentation Access**

### **Markdown Documentation**
- **File**: `API_DOCUMENTATION.md`
- **Format**: Comprehensive markdown documentation
- **Access**: Open in any text editor or markdown viewer

### **OpenAPI Specification**
- **File**: `api-documentation.yaml`
- **Format**: Raw OpenAPI 3.0 specification
- **Access**: Can be imported into other API tools

### **Postman Collection**
- **File**: `otex-api.postman_collection.json` (in TESTING_GUIDE.md)
- **Format**: Postman import collection
- **Access**: Import into Postman for advanced testing

---

## **Troubleshooting**

### **Swagger UI Not Loading**
```bash
# Check if server is running
php artisan serve

# Verify route exists
php artisan route:list | grep documentation
```

### **404 Error**
```bash
# Clear route cache
php artisan route:clear

# Restart server
php artisan serve
```

### **Authentication Issues**
1. Make sure you have a valid JWT token
2. Include "Bearer " prefix before token
3. Token should be from `/login` endpoint

### **Missing Endpoints**
```bash
# Regenerate documentation
php artisan l5-swagger:generate

# Clear cache
php artisan cache:clear
```

---

## **Production Considerations**

### **Security**
- **Disable Swagger** in production environments
- **Use Environment Variables** to control access
- **IP Whitelisting** for documentation access

### **Performance**
- **Cache Documentation** for better performance
- **CDN** for static assets
- **Separate Documentation Server** for high traffic

---

## **Documentation Files Created**

- `api-documentation.yaml` - Complete OpenAPI specification
- `storage/api-docs.yaml` - Swagger UI source file
- `app/SwaggerDefinitions.php` - Swagger annotations
- `config/l5-swagger.php` - Swagger configuration

---

## **Success!**

Your Swagger documentation is now fully functional and provides:
- **Interactive API testing**
- **Complete endpoint documentation**
- **Authentication support**
- **Real-time validation**
- **Professional developer experience**

**Access now: http://localhost:8000/api/documentation**
