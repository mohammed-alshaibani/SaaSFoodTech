'use client';

import { useState, useEffect, useCallback } from 'react';
import { useParams, useRouter } from 'next/navigation';
import api from '@/lib/api';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import RequestForm from '@/components/admin/RequestForm';
import { Loader2, ArrowLeft } from 'lucide-react';

const STATUS_LABELS = {
    pending: 'في الانتظار',
    accepted: 'مقبول',
    work_done: 'منجز',
    completed: 'مكتمل',
    cancelled: 'ملغي',
};

export default function RequestDetailsPage() {
    const { id } = useParams();
    const router = useRouter();
    const { isRTL } = useI18n();

    const [request, setRequest] = useState(null);
    const [loading, setLoading] = useState(true);
    const [editData, setEditData] = useState(null);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);

    const fetchRequest = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get(`/requests/${id}`);
            const data = res.data?.data || res.data;
            setRequest(data);
            setEditData({
                title: data.title || '',
                description: data.description || '',
                latitude: data.latitude || '',
                longitude: data.longitude || '',
                status: data.status || 'pending'
            });
        } catch (err) {
            console.error('Failed to fetch request:', err);
            setMessage({ type: 'error', text: isRTL ? 'فشل تحميل الطلب' : 'Failed to load request' });
        } finally {
            setLoading(false);
        }
    }, [id, isRTL]);

    useEffect(() => {
        fetchRequest();
    }, [fetchRequest]);

    const handleSave = async () => {
        setSaving(true);
        try {
            await api.put(`/requests/${id}`, editData);
            setMessage({ type: 'success', text: isRTL ? 'تم الحفظ بنجاح' : 'Saved successfully' });
            setTimeout(() => fetchRequest(), 500);
        } catch (err) {
            console.error('Update failed:', err);
            setMessage({ type: 'error', text: isRTL ? 'فشل الحفظ' : 'Failed to save' });
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <DashboardLayout>
                <div className="flex items-center justify-center p-20 min-h-[60vh]">
                    <Loader2 size={32} className="animate-spin text-[#7C3AED]" />
                </div>
            </DashboardLayout>
        );
    }

    if (!request) {
        return (
            <DashboardLayout>
                <div className="p-20 text-center">
                    <p className="text-gray-400 font-bold">{isRTL ? 'الطلب غير موجود' : 'Request not found'}</p>
                    <button onClick={() => router.back()} className="mt-4 text-[#7C3AED] font-bold">
                        {isRTL ? 'العودة للخلف' : 'Go Back'}
                    </button>
                </div>
            </DashboardLayout>
        );
    }

    return (
        <DashboardLayout>
            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4 mb-8">
                    <button
                        onClick={() => router.back()}
                        className="p-3 bg-white border border-gray-100 rounded-2xl hover:bg-gray-50 transition-all shadow-sm"
                    >
                        <ArrowLeft size={20} className={isRTL ? 'rotate-180' : ''} />
                    </button>
                    <div>
                        <h1 className="text-2xl font-black text-[#1A202C]">{isRTL ? 'إدارة الطلب' : 'Manage Request'}</h1>
                        <p className="text-gray-500 text-xs font-medium mt-1">{isRTL ? 'استخدام المكون الموحد للنظام' : 'Using unified system component'}</p>
                    </div>
                </div>

                {message && (
                    <div className={`p-4 rounded-2xl text-sm font-bold animate-in slide-in-from-top-2 duration-300 ${message.type === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}`}>
                        {message.text}
                    </div>
                )}

                <RequestForm
                    request={request}
                    editData={editData}
                    setEditData={setEditData}
                    onSave={handleSave}
                    loading={saving}
                    isRTL={isRTL}
                    STATUS_LABELS={STATUS_LABELS}
                    isAdmin={false}
                    mode="page"
                />
            </div>
        </DashboardLayout>
    );
}
