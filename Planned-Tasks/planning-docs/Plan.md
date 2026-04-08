Senior Fullstack Engineer – Take-Home
Assignment
Overview
The goal of this assignment is to evaluate your ability to design and build a realistic fullstack
platform from scratch.
You are expected to demonstrate:
● Strong backend fundamentals
● Ability to design clean APIs
● Solid frontend implementation
● Practical decision-making and trade-offs
This is not about building a perfect system, but about showing how you think and execute.
Project Description
Build a Service Marketplace Platform (MVP) where:
● Customers can create service requests
● Providers can view and accept nearby requests
● The system enforces role-based access
● Some features are gated behind a subscription
● A simple AI-powered feature is included
Requirements
1. Authentication & RBAC
Implement authentication and role-based access control with at least the following roles:
● Admin
● Provider
● Customer
Requirements:
● Users must only access resources permitted to their role
● RBAC must be enforced at the API level (not just UI)
2. Core Functionality
Customer
● Create a service request (title, description, location)
● View their own requests and statuses
Provider
● View available service requests
● Filter or retrieve nearby requests based on location
● Accept a request
● Update request status (e.g. accepted → completed)
Request Lifecycle
● pending → accepted → completed
3. Geolocation
● Store latitude and longitude for each request
● Implement a basic “nearby requests” feature (e.g. within X km radius)
No need for map UI (optional bonus)
4. Subscription / Feature Gating
Implement a simple subscription model:
Example:
● Free users can create up to 3 requests
● Paid users have no limit
Notes:
● No need to integrate real payment providers
● You can simulate subscription state in the system
5. AI Feature
Include one AI-powered feature. Examples:
● Generate or enhance service descriptions
● Categorize requests automatically
● Suggest pricing
You may use any external API or mock this functionality if needed.
6. Frontend
Provide a functional frontend that includes:
● At least two roles (e.g. Customer and Provider)
● Ability to perform core actions (create request, accept request, etc.)
● Clear separation of views or flows based on roles
7. API & Backend Design
● Design clean, well-structured APIs
● Use a database with a clear schema
● Organize your code in a maintainable way
8. Advanced RBAC (Bonus)
Extend the RBAC system to support dynamic permissions and role hierarchy.
Requirements:
● Permissions should not be hardcoded purely by role
● Example permissions:
○ request.create
○ request.accept
○ request.complete
○ request.view_all
● Implement a system where:
○ An Admin can assign or revoke permissions to Providers
○ A Provider Admin can manage permissions for other Providers (e.g.
employees)
○ A Provider Employee has limited permissions
● The system should support:
○ Assigning permissions dynamically
○ Enforcing permissions at the API level
○ A clear structure for roles, permissions, and their relationships
Deliverables
Please provide:
1. Source Code (GitHub repository)
2. README file including:
○ Setup instructions
○ Architecture overview
○ Key design decisions and trade-offs
○ Explanation of your RBAC design (how permissions are stored and enforced)
○ What you would improve with more time
3. API Documentation
○ Swagger / Postman collection / similar
4. Run Instructions
○ Preferably using Docker (optional but recommended)
Bonus (Optional)
You are not required to implement these, but they will be considered a plus:
● Dockerized setup
● CI/CD pipeline
● Background jobs (e.g. async processing)
● Caching
● Logging and error handling strategy
● Real-time updates (e.g. WebSockets)
Evaluation Criteria
We will evaluate based on:
● Code quality and structure
● API design and correctness
● Proper RBAC implementation (including dynamic permissions)
● Handling of edge cases and validation
● Simplicity and practicality of decisions
● Clarity of documentation
● Overall completeness
Important Notes
● Keep the solution simple and focused
● Avoid over-engineering
● Make reasonable assumptions where needed (document them)
● Prioritize correctness and clarity over completeness
Timeline
Please submit your solution within 7 days.
Follow-Up Discussion
As part of the next step, you will be asked to:
● Walk us through your implementation
● Explain your design decisions
● Discuss how you would scale this system for larger use cases
Good luck, and we look forward to reviewing your work