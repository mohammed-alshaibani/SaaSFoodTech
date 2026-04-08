# Use the official Node.js 18 image as base
FROM node:18-alpine

# Set working directory
WORKDIR /app

# Copy package.json and package-lock.json
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy the rest of the application code
COPY . .

# Create .next directory and set permissions
RUN mkdir -p .next && chown -R node:node /app

# Switch to non-root user
USER node

# Expose port 3000
EXPOSE 3000

# Start the application
CMD ["npm", "run", "dev"]
