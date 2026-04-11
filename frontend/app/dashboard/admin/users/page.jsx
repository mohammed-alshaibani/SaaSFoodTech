'use client';

import { useState, useCallback, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';
import DashboardLayout from '@/components/DashboardLayout';
import DynamicCRUD from '@/components/admin/DynamicCRUD';
import { Users, Mail, Shield, Key, X, Check, ArrowRight } from 'lucide-react';
import { useAuth } from '@/context/AuthenticationContext';

export default function UsersManagement() {
    const router = useRouter();
    const { refreshUser } = useAuth();
    const [schema, setSchema] = useState(null);
    const [loadingSchema, setLoadingSchema] = useState(true);
    const [availableRoles, setAvailableRoles] = useState([]);
    const [showPermissionsModal, setShowPermissionsModal] = useState(false);
    const [selectedUser, setSelectedUser] = useState(null);
    const [allPermissions, setAllPermissions] = useState([]);
    const [userPermissions, setUserPermissions] = useState([]);

    // Fetch available roles for the select dropdown
    const fetchRoles = useCallback(async () => {
        try {
            const res = await api.get('/admin/roles');
            const roles = res.data.data || [];
            setAvailableRoles(roles);
        } catch (err) {
            console.error('Failed to load roles:', err);
        }
    }, []);

    const openPermissionsModal = useCallback(async (user) => {
        setSelectedUser(user);

        try {
            // Fetch all permissions
            const permRes = await api.get('/admin/permissions');
            setAllPermissions(permRes.data.data || permRes.data || []);

            // Fetch user's direct permissions
            const userPermRes = await api.get(`/admin/users/${user.id}/permissions`);
            setUserPermissions(userPermRes.data.data || userPermRes.data || []);
        } catch (err) {
            console.error('Failed to load permissions:', err);
        }

        setShowPermissionsModal(true);
    }, []);

    const grantPermission = useCallback(async (permissionId) => {
        if (!selectedUser) return;

        try {
            await api.post(`/admin/users/${selectedUser.id}/permissions`, {
                permission_id: permissionId
            });

            // Refresh user permissions
            const userPermRes = await api.get(`/admin/users/${selectedUser.id}/permissions`);
            setUserPermissions(userPermRes.data.data || userPermRes.data || []);
            refreshUser();
        } catch (err) {
            console.error('Failed to grant permission:', err);
            alert('Failed to grant permission');
        }
    }, [selectedUser, refreshUser]);

    const revokePermission = useCallback(async (permissionId) => {
        if (!selectedUser) return;

        try {
            await api.delete(`/admin/users/${selectedUser.id}/permissions/${permissionId}`);

            // Refresh user permissions
            const userPermRes = await api.get(`/admin/users/${selectedUser.id}/permissions`);
            setUserPermissions(userPermRes.data.data || userPermRes.data || []);
            refreshUser();
        } catch (err) {
            console.error('Failed to revoke permission:', err);
            alert('Failed to revoke permission');
        }
    }, [selectedUser, refreshUser]);

    // Generate dynamic schema based on API response
    useEffect(() => {
        const generateSchema = async () => {
            try {
                setLoadingSchema(true);
                await fetchRoles();

                const res = await api.get('/admin/users?per_page=1');
                const users = res.data.data || [];

                if (users.length === 0) {
                    // Fallback to default schema if no users exist
                    setSchema(getDefaultSchema());
                    setLoadingSchema(false);
                    return;
                }

                const sampleUser = users[0];
                const availableColumns = Object.keys(sampleUser);

                // Filter out nested objects, internal fields, and relationship fields
                const excludeColumns = ['id', 'created_at', 'updated_at', 'roles', 'permissions', 'email_verified_at', 'remember_token'];
                const tableColumns = availableColumns.filter(col => !excludeColumns.includes(col));

                const dynamicFields = tableColumns.map(col => {
                    const fieldConfig = {
                        name: col,
                        label: getColumnLabel(col),
                        type: getColumnType(col),
                        table: true,
                        form: true,
                        required: col === 'name' || col === 'email',
                    };

                    // Add custom render for name field
                    if (col === 'name') {
                        fieldConfig.render = (val) => (
                            <div className="flex items-center gap-4">
                                <div className="w-10 h-10 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-[#1A202C] font-black shadow-sm">
                                    {val?.[0]?.toUpperCase()}
                                </div>
                                <span className="text-sm font-black text-[#1A202C]">{val}</span>
                            </div>
                        );
                    }

                    // Add custom render for email field
                    if (col === 'email') {
                        fieldConfig.render = (val) => (
                            <div className="flex items-center gap-2 text-gray-500">
                                <Mail size={14} /> {val}
                            </div>
                        );
                    }

                    // Add custom render for plan field
                    if (col === 'plan') {
                        fieldConfig.type = 'select';
                        fieldConfig.options = [
                            { value: 'free', label: 'مجاني' },
                            { value: 'premium', label: 'بريميوم' },
                            { value: 'enterprise', label: 'مؤسسي' }
                        ];
                        fieldConfig.defaultValue = 'free';
                        fieldConfig.render = (val) => {
                            const labels = { free: 'مجاني', premium: 'بريميوم', enterprise: 'مؤسسي' };
                            const colors = {
                                free: 'bg-gray-100 text-gray-500',
                                premium: 'bg-purple-50 text-purple-600 border border-purple-200',
                                enterprise: 'bg-emerald-50 text-emerald-600 border border-emerald-200'
                            };
                            return (
                                <span className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest ${colors[val] || colors.free}`}>
                                    {labels[val] || val}
                                </span>
                            );
                        };
                    }

                    // Handle password field - only in form, not in table
                    if (col === 'password') {
                        fieldConfig.table = false;
                        fieldConfig.form = true;
                        fieldConfig.required = false; // Required only on create
                    }

                    // Handle latitude/longitude - exclude from table
                    if (col === 'latitude' || col === 'longitude') {
                        fieldConfig.table = false;
                    }

                    return fieldConfig;
                });

                // Add role field as a relationship (not a direct column)
                dynamicFields.push({
                    name: 'role',
                    label: 'الدور',
                    type: 'select',
                    table: true,
                    form: true,
                    options: availableRoles.map(role => ({
                        value: role.name,
                        label: role.name
                    })),
                    render: (val, item) => (
                        <span className="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-[10px] font-bold uppercase tracking-widest">
                            {item.roles?.[0]?.name || item.roles?.[0] || val || '-'}
                        </span>
                    )
                });

                // Add password field for create/edit
                dynamicFields.push({
                    name: 'password',
                    label: 'كلمة المرور',
                    type: 'password',
                    table: false,
                    form: true,
                    required: false // Required only on create
                });

                // Add roles navigation button to table
                dynamicFields.push({
                    name: 'roles_nav',
                    label: 'إدارة الأدوار',
                    type: 'custom',
                    table: true,
                    form: false,
                    render: (val, item) => (
                        <button
                            onClick={() => router.push('/dashboard/admin/roles')}
                            className="p-2 bg-purple-50 text-purple-600 rounded-xl hover:bg-purple-100 transition flex items-center gap-1"
                            title="إدارة الأدوار"
                        >
                            <Shield size={16} />
                        </button>
                    )
                });

                // Add permissions management action to table
                dynamicFields.push({
                    name: 'permissions',
                    label: 'الصلاحيات',
                    type: 'custom',
                    table: true,
                    form: false,
                    render: (val, item) => (
                        <button
                            onClick={() => openPermissionsModal(item)}
                            className="p-2 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-100 transition flex items-center gap-1"
                            title="إدارة الصلاحيات"
                        >
                            <Key size={16} />
                        </button>
                    )
                });

                setSchema({
                    title: 'إدارة المستخدمين',
                    endpoint: '/admin/users',
                    icon: Users,
                    onAfterSave: () => refreshUser(),
                    fields: dynamicFields
                });

            } catch (err) {
                console.error('Failed to generate schema:', err);
                // Fallback to default schema
                setSchema(getDefaultSchema());
            } finally {
                setLoadingSchema(false);
            }
        };

        generateSchema();
    }, [fetchRoles]);

    const getDefaultSchema = () => ({
        title: 'إدارة المستخدمين',
        endpoint: '/admin/users',
        icon: Users,
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
                name: 'password',
                label: 'كلمة المرور',
                type: 'password',
                table: false,
                form: true,
                required: false
            },
            {
                name: 'role',
                label: 'الدور',
                type: 'select',
                table: true,
                form: true,
                options: [
                    { value: 'admin', label: 'Admin' },
                    { value: 'provider_admin', label: 'Provider Admin' },
                    { value: 'customer', label: 'Customer' }
                ],
                render: (val, item) => (
                    <span className="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-[10px] font-bold uppercase tracking-widest">
                        {item.roles?.[0]?.name || item.roles?.[0] || val || '-'}
                    </span>
                )
            },
            {
                name: 'plan',
                label: 'الخطة',
                type: 'select',
                table: true,
                form: true,
                defaultValue: 'free',
                options: [
                    { value: 'free', label: 'مجاني' },
                    { value: 'premium', label: 'بريميوم' },
                    { value: 'enterprise', label: 'مؤسسي' }
                ],
                render: (val) => {
                    const labels = { free: 'مجاني', premium: 'بريميوم', enterprise: 'مؤسسي' };
                    const colors = {
                        free: 'bg-gray-100 text-gray-500',
                        premium: 'bg-purple-50 text-purple-600 border border-purple-200',
                        enterprise: 'bg-emerald-50 text-emerald-600 border border-emerald-200'
                    };
                    return (
                        <span className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest ${colors[val] || colors.free}`}>
                            {labels[val] || val}
                        </span>
                    );
                }
            }
        ]
    });

    const getColumnLabel = (columnName) => {
        const labels = {
            'name': 'الاسم',
            'email': 'البريد الإلكتروني',
            'password': 'كلمة المرور',
            'plan': 'الخطة',
            'latitude': 'خط العرض',
            'longitude': 'خط الطول',
        };
        return labels[columnName] || columnName;
    };

    const getColumnType = (columnName) => {
        if (columnName === 'email') return 'email';
        if (columnName === 'password') return 'password';
        if (columnName === 'plan') return 'select';
        return 'text';
    };

    if (loadingSchema || !schema) {
        return (
            <DashboardLayout>
                <div className="flex items-center justify-center min-h-[400px]">
                    <div className="animate-spin rounded-full h-12 w-12 border-4 border-[#7C3AED] border-t-transparent"></div>
                </div>
            </DashboardLayout>
        );
    }

    return (
        <DashboardLayout>
            <DynamicCRUD config={schema} />
            
            {/* Permissions Management Modal */}
            {showPermissionsModal && selectedUser && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
                    <div className="bg-white rounded-3xl p-8 w-full max-w-3xl shadow-2xl animate-in zoom-in-95 duration-200">
                        <div className="flex items-center justify-between mb-6">
                            <h2 className="text-2xl font-black text-[#1A202C]">
                                إدارة الصلاحيات - {selectedUser.name}
                            </h2>
                            <button
                                onClick={() => setShowPermissionsModal(false)}
                                className="p-2 hover:bg-gray-100 rounded-xl transition"
                            >
                                <X size={20} />
                            </button>
                        </div>

                        <div className="space-y-4 max-h-[60vh] overflow-y-auto custom-scrollbar">
                            {allPermissions.map(permission => (
                                <button
                                    key={permission.id}
                                    type="button"
                                    onClick={() => {
                                        const hasPermission = userPermissions.some(p => p.id === permission.id);
                                        if (hasPermission) {
                                            revokePermission(permission.id);
                                        } else {
                                            grantPermission(permission.id);
                                        }
                                    }}
                                    className={`w-full flex items-center justify-between p-4 rounded-xl border-2 transition-all ${
                                        userPermissions.some(p => p.id === permission.id)
                                            ? 'border-[#7C3AED] bg-white shadow-sm'
                                            : 'border-white bg-[#F8FAFC]'
                                    }`}
                                >
                                    <div className="flex items-center gap-3">
                                        <Key size={18} className={userPermissions.some(p => p.id === permission.id) ? 'text-[#7C3AED]' : 'text-gray-400'} />
                                        <span className="text-sm font-bold text-[#1A202C]">{permission.name}</span>
                                    </div>
                                    {userPermissions.some(p => p.id === permission.id) && (
                                        <Check size={18} className="text-[#7C3AED]" strokeWidth={4} />
                                    )}
                                </button>
                            ))}
                        </div>

                        <div className="pt-6 border-t border-gray-100 mt-6">
                            <p className="text-xs text-gray-400">
                                الصلاحيات المباشرة تمنح للمستخدم بالإضافة إلى صلاحيات دوره.
                            </p>
                        </div>
                    </div>
                </div>
            )}
        </DashboardLayout>
    );
}
