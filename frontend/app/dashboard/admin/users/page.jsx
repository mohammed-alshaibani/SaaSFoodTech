'use client';

import { useState, useCallback, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';
import DashboardLayout from '@/components/DashboardLayout';
import DynamicCRUD from '@/components/admin/DynamicCRUD';
import { Users, Mail, Shield, Key, X, Check, ArrowUpCircle, ArrowDownCircle } from 'lucide-react';
import { useAuth } from '@/context/AuthenticationContext';
import React from 'react';

export default function UsersManagement() {
    const router = useRouter();
    const { refreshUser } = useAuth();
    const [showPermissionsModal, setShowPermissionsModal] = useState(false);
    const [selectedUser, setSelectedUser] = useState(null);
    const [allPermissions, setAllPermissions] = useState([]);
    const [userPermissions, setUserPermissions] = useState([]);
    const [dynamicPlans, setDynamicPlans] = useState([]);

    React.useEffect(() => {
        api.get('/subscription/plans').then(res => {
            const plans = (res.data.data || []).map(p => ({ value: p.name, label: p.display_name || p.name }));
            if (plans.length > 0) setDynamicPlans(plans);
        }).catch(err => console.error("Failed to fetch dynamic plans", err));
    }, []);

    const openPermissionsModal = useCallback(async (user) => {
        setSelectedUser(user);
        try {
            const [permRes, userPermRes] = await Promise.all([
                api.get('/admin/permissions'),
                api.get(`/admin/users/${user.id}/permissions`)
            ]);
            setAllPermissions(permRes.data.data || permRes.data || []);
            setUserPermissions(userPermRes.data.data || userPermRes.data || []);
        } catch (err) {
            console.error('Failed to load permissions:', err);
        }
        setShowPermissionsModal(true);
    }, []);

    const togglePermission = useCallback(async (permissionId) => {
        if (!selectedUser) return;
        const hasPermission = userPermissions.some(p => p.id === permissionId);
        try {
            if (hasPermission) {
                await api.delete(`/admin/users/${selectedUser.id}/permissions/${permissionId}`);
            } else {
                await api.post(`/admin/users/${selectedUser.id}/permissions`, { permission: permissionId });
            }
            const res = await api.get(`/admin/users/${selectedUser.id}/permissions`);
            setUserPermissions(res.data.data || res.data || []);
            refreshUser();
        } catch (err) {
            console.error('Failed to update permission:', err);
        }
    }, [selectedUser, userPermissions, refreshUser]);

    const userSchema = useMemo(() => ({
        title: 'إدارة المستخدمين',
        endpoint: '/admin/users',
        icon: Users,
        onAfterSave: () => refreshUser(),
        fields: [
            {
                name: 'name',
                label: 'الاسم',
                type: 'text',
                table: true,
                form: true,
                required: true,
                render: (val) => (
                    <div className="flex items-center gap-4">
                        <div className="w-10 h-10 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-[#1A202C] font-black shadow-sm">
                            {val?.[0]?.toUpperCase()}
                        </div>
                        <span className="text-sm font-black text-[#1A202C]">{val}</span>
                    </div>
                )
            },
            {
                name: 'email',
                label: 'البريد الإلكتروني',
                type: 'email',
                table: true,
                form: true,
                required: true,
                render: (val) => (
                    <div className="flex items-center gap-2 text-gray-500">
                        <Mail size={14} /> {val}
                    </div>
                )
            },
            {
                name: 'plan',
                label: 'الخطة',
                type: 'select',
                table: true,
                form: true,
                defaultValue: 'free',
                options: dynamicPlans.length > 0 ? dynamicPlans : [
                    { value: 'free', label: 'مجاني' },
                    { value: 'premium', label: 'بريميوم' },
                    { value: 'enterprise', label: 'مؤسسي' }
                ],
                render: (val) => {
                    const labelStr = dynamicPlans.find(p => p.value === val)?.label || val;
                    return (
                        <span className="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest bg-gray-100 text-gray-700">
                            {labelStr}
                        </span>
                    );
                }
            },
            {
                name: 'role',
                label: 'الدور',
                type: 'select',
                table: true,
                form: true,
                options: [
                    { value: 'admin', label: 'مدير النظام' },
                    { value: 'provider_admin', label: 'مدير مزود خدمة' },
                    { value: 'provider_employee', label: 'موظف مزود خدمة' },
                    { value: 'customer', label: 'عميل' }
                ],
                render: (val, item) => (
                    <span className="px-3 py-1 bg-gray-50 text-gray-600 rounded-full text-[10px] font-bold uppercase tracking-widest border border-gray-100">
                        {item.roles?.[0]?.name || item.roles?.[0] || val || '-'}
                    </span>
                )
            },
            {
                name: 'password',
                label: 'كلمة المرور',
                type: 'password',
                table: false,
                form: true,
                required: false, // Optional on update
                placeholder: 'اتركه فارغاً للحفاظ على الحالية'
            }
        ]
    }), [refreshUser, dynamicPlans]);

    return (
        <DashboardLayout>
            <DynamicCRUD config={userSchema} />

            {showPermissionsModal && selectedUser && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
                    <div className="bg-white rounded-3xl p-8 w-full max-w-2xl shadow-2xl animate-in zoom-in-95 duration-200">
                        <div className="flex items-center justify-between mb-6">
                            <h2 className="text-2xl font-black text-[#1A202C]">
                                إدارة صلاحيات: {selectedUser.name}
                            </h2>
                            <button onClick={() => setShowPermissionsModal(false)} className="p-2 hover:bg-gray-100 rounded-xl transition">
                                <X size={20} />
                            </button>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-[50vh] overflow-y-auto p-1">
                            {allPermissions.map(permission => (
                                <button
                                    key={permission.id}
                                    onClick={() => togglePermission(permission.id || permission.name)}
                                    className={`flex items-center justify-between p-4 rounded-xl border-2 transition-all ${userPermissions.some(p => p.id === permission.id || p.name === permission.name)
                                        ? 'border-[#7C3AED] bg-purple-50/30'
                                        : 'border-gray-50 bg-gray-50/50 hover:bg-gray-100'
                                        }`}
                                >
                                    <span className="text-sm font-bold text-[#1A202C]">{permission.name}</span>
                                    {userPermissions.some(p => p.id === permission.id || p.name === permission.name) && (
                                        <Check size={18} className="text-[#7C3AED]" strokeWidth={3} />
                                    )}
                                </button>
                            ))}
                        </div>

                        <div className="mt-8 pt-6 border-t border-gray-100 flex justify-end">
                            <button
                                onClick={() => setShowPermissionsModal(false)}
                                className="px-6 py-2 bg-[#1A202C] text-white rounded-xl font-bold hover:bg-black transition"
                            >
                                إغلاق
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </DashboardLayout>
    );
}
