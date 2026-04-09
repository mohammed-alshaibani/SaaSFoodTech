'use client';

import { useState } from 'react';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import { useRouter } from 'next/navigation';
import { Loader2 } from 'lucide-react';
import Link from 'next/link';

export default function RegisterForm() {
  const router = useRouter(); // Initialize router (cache busted)
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    role: '', // Mandatory role selection
    password: '',
    password_confirmation: '',
  });
  const [errors, setErrors] = useState({});
  const { login } = useAuth();
  const { t, isRTL } = useI18n();

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    // Clear error for this field when user starts typing
    if (errors[name]) {
      setErrors(prev => ({ ...prev, [name]: '' }));
    }
  };

  const handleRoleSelect = (role) => {
    setFormData(prev => ({ ...prev, role }));
    if (errors.role) {
      setErrors(prev => ({ ...prev, role: '' }));
    }
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.name.trim()) {
      newErrors.name = t('auth.nameRequired');
    }

    if (!formData.role) {
      newErrors.role = t('auth.roleRequired');
    }

    if (!formData.email.trim()) {
      newErrors.email = t('auth.emailRequired');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = t('auth.emailInvalid');
    }

    if (formData.role === 'provider' && !formData.company_name?.trim()) {
      newErrors.company_name = t('auth.companyName') + ' ' + t('auth.roleRequired');
    }

    // Password Policy Regex: 1 Uppercase, 1 Lowercase, 1 Number, 1 Special Char
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/;

    if (!formData.password) {
      newErrors.password = t('auth.passwordRequired');
    } else if (formData.password.length < 8) {
      newErrors.password = t('auth.passwordMinLength');
    } else if (!passwordRegex.test(formData.password)) {
      newErrors.password = t('auth.passwordCriteria');
    }

    if (!formData.password_confirmation) {
      newErrors.password_confirmation = t('auth.confirmPassword') + ' ' + t('auth.passwordRequired');
    } else if (formData.password !== formData.password_confirmation) {
      newErrors.password_confirmation = t('auth.passwordMismatch');
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!validateForm()) return;

    try {
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'}/register`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify(formData),
      });

      const data = await response.json();

      if (!response.ok) {
        // Handle Laravel validation errors (often nested in 'errors' object)
        if (data.errors) {
          const firstError = Object.values(data.errors)[0][0];
          throw new Error(firstError);
        }
        throw new Error(data.message || t('auth.registerError'));
      }

      // Backend returns access_token
      const { access_token, user } = data;
      const result = await login({ email: user.email, password: null, access_token });

      if (result.success) {
        // Redirect based on user role
        const role = user.roles?.[0] || 'customer';
        const dashboardPaths = {
          admin: '/dashboard/admin',
          customer: '/dashboard/customer',
          provider_admin: '/dashboard/provider',
          provider_employee: '/dashboard/provider'
        };
        router.push(dashboardPaths[role] || '/dashboard/customer');
      } else {
        throw new Error(result.error || 'Login failed after registration');
      }
    } catch (error) {
      setErrors({ general: error.message });
    }
  };

  return (
    <div className={`w-full max-w-md mx-auto ${isRTL ? 'text-right' : 'text-left'}`}>
      <form onSubmit={handleSubmit} className="space-y-5">

        {/* Role Selection */}
        <div className="mb-6">
          <label className="block text-sm font-semibold text-gray-700 mb-3">
            {t('auth.role')}
          </label>
          <div className="grid grid-cols-3 gap-3">
            <button
              type="button"
              onClick={() => handleRoleSelect('customer')}
              className={`py-3 px-2 border-2 rounded-xl transition-all flex flex-col items-center gap-1 text-center ${formData.role === 'customer'
                ? 'border-blue-600 bg-blue-50 text-blue-700'
                : 'border-gray-200 hover:border-gray-300 shadow-sm'
                }`}
            >
              <span className="font-bold text-sm tracking-tight">{t('auth.customer')}</span>
            </button>
            <button
              type="button"
              onClick={() => handleRoleSelect('provider')}
              className={`py-3 px-2 border-2 rounded-xl transition-all flex flex-col items-center gap-1 text-center ${formData.role === 'provider'
                ? 'border-blue-600 bg-blue-50 text-blue-700'
                : 'border-gray-200 hover:border-gray-300 shadow-sm'
                }`}
            >
              <span className="font-bold text-sm tracking-tight">{t('auth.provider')}</span>
            </button>
            <button
              type="button"
              onClick={() => handleRoleSelect('admin')}
              className={`py-3 px-2 border-2 rounded-xl transition-all flex flex-col items-center gap-1 text-center ${formData.role === 'admin'
                ? 'border-blue-600 bg-blue-50 text-blue-700'
                : 'border-gray-200 hover:border-gray-300 shadow-sm'
                }`}
            >
              <span className="font-bold text-sm tracking-tight">{t('auth.admin')}</span>
            </button>
          </div>
          {errors.role && (
            <p className="mt-2 text-xs text-red-600 font-medium">{errors.role}</p>
          )}
        </div>

        {/* Dynamic Company Name Field for Providers */}
        {formData.role === 'provider' && (
          <div className="animate-in fade-in slide-in-from-top-2 duration-300">
            <label htmlFor="company_name" className="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 px-1">
              {t('auth.companyName')}
            </label>
            <input
              type="text"
              id="company_name"
              name="company_name"
              value={formData.company_name}
              onChange={handleChange}
              className={`w-full px-4 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all ${errors.company_name ? 'border-red-500' : 'border-gray-100'
                } ${isRTL ? 'text-right' : 'text-left'}`}
              placeholder={t('auth.companyName')}
            />
            {errors.company_name && (
              <p className="mt-1 text-xs text-red-600 px-1">{errors.company_name}</p>
            )}
          </div>
        )}

        {/* Name Field */}
        <div>
          <label htmlFor="name" className="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 px-1">
            {t('auth.name')}
          </label>
          <input
            type="text"
            id="name"
            name="name"
            value={formData.name}
            onChange={handleChange}
            className={`w-full px-4 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all ${errors.name ? 'border-red-500' : 'border-gray-100'
              } ${isRTL ? 'text-right' : 'text-left'}`}
            placeholder={t('auth.name')}
          />
          {errors.name && (
            <p className="mt-1 text-xs text-red-600 px-1">{errors.name}</p>
          )}
        </div>

        {/* Email Field */}
        <div>
          <label htmlFor="email" className="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 px-1">
            {t('auth.email')}
          </label>
          <input
            type="email"
            id="email"
            name="email"
            value={formData.email}
            onChange={handleChange}
            className={`w-full px-4 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all ${errors.email ? 'border-red-500' : 'border-gray-100'
              } ${isRTL ? 'text-right' : 'text-left'}`}
            placeholder={t('auth.email')}
          />
          {errors.email && (
            <p className="mt-1 text-xs text-red-600 px-1">{errors.email}</p>
          )}
        </div>

        {/* Password Field */}
        <div>
          <label htmlFor="password" className="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 px-1">
            {t('auth.password')}
          </label>
          <input
            type="password"
            id="password"
            name="password"
            value={formData.password}
            onChange={handleChange}
            className={`w-full px-4 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all ${errors.password ? 'border-red-500' : 'border-gray-100'
              } ${isRTL ? 'text-right' : 'text-left'}`}
            placeholder="••••••••"
          />
          {errors.password && (
            <p className="mt-1 text-xs text-red-600 px-1 leading-relaxed">{errors.password}</p>
          )}
        </div>

        {/* Confirm Password Field */}
        <div>
          <label htmlFor="password_confirmation" className="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 px-1">
            {t('auth.confirmPassword')}
          </label>
          <input
            type="password"
            id="password_confirmation"
            name="password_confirmation"
            value={formData.password_confirmation}
            onChange={handleChange}
            className={`w-full px-4 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all ${errors.password_confirmation ? 'border-red-500' : 'border-gray-100'
              } ${isRTL ? 'text-right' : 'text-left'}`}
            placeholder="••••••••"
          />
          {errors.password_confirmation && (
            <p className="mt-1 text-xs text-red-600 px-1">{errors.password_confirmation}</p>
          )}
        </div>

        {/* General Error */}
        {errors.general && (
          <div className="p-3 bg-red-50 border border-red-200 rounded-xl">
            <p className="text-xs text-red-600 text-center font-medium">{errors.general}</p>
          </div>
        )}

        {/* Submit Button */}
        <button
          type="submit"
          className="w-full flex items-center justify-center px-4 py-4 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-200 active:scale-[0.98] transition-all mt-4"
        >
          {t('auth.signUp')}
        </button>

        {/* Login Link */}
        <div className="text-center pt-2">
          <p className="text-sm text-gray-500">
            {t('auth.alreadyHaveAccount')}{' '}
            <Link
              href="/login"
              className="text-blue-600 hover:text-blue-700 font-bold transition-colors"
            >
              {t('auth.signIn')}
            </Link>
          </p>
        </div>
      </form>
    </div>
  );
}
