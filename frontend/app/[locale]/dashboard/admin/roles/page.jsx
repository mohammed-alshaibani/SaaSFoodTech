'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';
import DashboardLayout from '@/components/DashboardLayout';
import DynamicCRUD from '@/components/admin/DynamicCRUD';
import { Shield, Check } from 'lucide-react';
import { useAuth } from '@/context/AuthenticationContext';

export default function RolesManagement() {
    const { refreshUser } = useAuth();
    const [allPermissions, setAllPermissions] = useState([]);

    useEffect(() => {
        const fetchData = async () => {
            try {
                const res = await api.get('/admin/permissions?guard_name=sanctum');
                setAllPermissions(res.data.data || res.data || []);
            } catch (err) {
                console.error('Failed to load permissions:', err);
            }
        };
        fetchData();
    }, []);

    const schema = {
        title: 'إدارة الأدوار',
        endpoint: '/admin/roles',
        icon: Shield,
        onAfterSave: () => refreshUser(),
        fields: [
            {
                name: 'name',
                label: 'اسم الدور',
                type: 'text',
                table: true,
                form: true,
                required: true,
                render: (val) => (
                    <span className="px-3 py-1 bg-[#7C3AED]/10 text-[#7C3AED] rounded-full text-[10px] font-bold uppercase tracking-widest">{val}</span>
                )
            },
            {
                name: 'permissions',
                label: 'الصلاحيات',
                type: 'custom',
                table: false,
                form: true,
                defaultValue: [],
                renderForm: (val, onChange) => (
                    <div className="bg-gray-50/50 rounded-2xl p-4 border border-gray-100 max-h-[250px] overflow-y-auto custom-scrollbar">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            {allPermissions.map((perm) => (
                                <button
                                    key={perm.id}
                                    type="button"
                                    onClick={() => {
                                        const current = Array.isArray(val) ? val : [];
                                        const index = current.indexOf(perm.id);
                                        const next = [...current];
                                        if (index > -1) next.splice(index, 1);
                                        else next.push(perm.id);
                                        onChange(next);
                                    }}
                                    className={`flex items-center justify-between p-3 rounded-xl border-2 transition-all ${
                                        Array.isArray(val) && val.includes(perm.id)
                                            ? 'border-[#7C3AED] bg-white shadow-sm'
                                            : 'border-white bg-[#F8FAFC]'
                                    }`}
                                >
                                    <span className="text-xs font-bold truncate pr-2">{perm.name}</span>
                                    {Array.isArray(val) && val.includes(perm.id) && (
                                        <Check size={14} className="text-[#7C3AED]" strokeWidth={4} />
                                    )}
                                </button>
                            ))}
                        </div>
                    </div>
                )
            }
        ]
    };

    return (
        <DashboardLayout>
            <DynamicCRUD config={schema} />
        </DashboardLayout>
    );
}
