'use client';

import { createContext } from 'react';

// Temporary bridge to new AuthenticationContext for backward compatibility
export { useAuth } from './AuthenticationContext';
export { AuthenticationProvider as AuthProvider } from './AuthenticationContext';

// Legacy export for compatibility
const AuthContext = createContext(undefined);
export default AuthContext;
