'use client';



import { useMemo } from 'react';

import DashboardLayout from '@/components/DashboardLayout';

import DynamicCRUD from '@/components/admin/DynamicCRUD';

import { Lock } from 'lucide-react';

import { useAuth } from '@/context/AuthenticationContext';



export default function PermissionsManagement() {

    const { refreshUser } = useAuth();



    const permissionSchema = useMemo(() => ({

        title: 'إدارة الصلاحيات',

        endpoint: '/admin/permissions',

        icon: Lock,

        onAfterSave: () => refreshUser(),

        fields: [

            {

                name: 'name',

                label: 'اسم الصلاحية',

                type: 'text',

                table: true,

                form: true,

                required: true,

                render: (val) => (

                    <span className="px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-[10px] font-bold uppercase tracking-widest">{val}</span>

                )

            },

            {

                name: 'guard_name',

                label: 'Guard Name',

                type: 'text',

                table: false,

                form: true,

                defaultValue: 'sanctum',

                required: true

            },

            {

                name: 'created_at',

                label: 'تاريخ الإنشاء',

                type: 'text',

                table: true,

                form: false,

                render: (val) => val ? new Date(val).toLocaleDateString('ar-SA') : '-'

            }

        ]

    }), [refreshUser]);



    return (

        <DashboardLayout>

            <DynamicCRUD config={permissionSchema} />

        </DashboardLayout>

    );

}

