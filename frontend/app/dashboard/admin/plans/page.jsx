'use client';

import { useState, useEffect, useCallback } from 'react';
import { useI18n } from '@/context/I18nContext';
import DashboardLayout from '@/components/DashboardLayout';
import { Package, Plus, X, Save, Check, Edit2, Trash2 } from 'lucide-react';
import api from '@/lib/api';

export default function PlansDashboard() {
    const { t, language } = useI18n();
    const isRTL = language === 'ar';
    
    const [plans, setPlans] = useState([]);
    const [showForm, setShowForm] = useState(false);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState(null);
    const [editingPlan, setEditingPlan] = useState(null);
    const [formData, setFormData] = useState({
        name: '',
        name_ar: '',
        description: '',
        description_ar: '',
        price: '',
        interval: 'monthly',
        request_limit: '',
        features: ['', '', '']
    });

    const fetchPlans = async () => {
        try {
            const res = await api.get('/admin/plans');
            setPlans(res.data.data || []);
        } catch (err) {
            console.error('Failed to fetch plans:', err);
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (editingPlan) {
                await api.patch(`/admin/plans/${editingPlan.id}`, {
                    name: formData.name,
                    name_ar: formData.name_ar,
                    description: formData.description,
                    description_ar: formData.description_ar,
                    price: parseFloat(formData.price),
                    interval: formData.interval,
                    limits: {
                        requests: parseInt(formData.request_limit)
                    },
                    features: formData.features.filter(f => f.trim() !== '')
                });
                setMessage({ type: 'success', text: t('plans.updated') || 'Plan updated successfully' });
            } else {
                await api.post('/admin/plans', {
                    name: formData.name,
                    name_ar: formData.name_ar,
                    description: formData.description,
                    description_ar: formData.description_ar,
                    price: parseFloat(formData.price),
                    interval: formData.interval,
                    limits: {
                        requests: parseInt(formData.request_limit)
                    },
                    features: formData.features.filter(f => f.trim() !== '')
                });
                setMessage({ type: 'success', text: t('plans.created') || 'Plan created successfully' });
            }
            setShowForm(false);
            setEditingPlan(null);
            setFormData({
                name: '',
                name_ar: '',
                description: '',
                description_ar: '',
                price: '',
                interval: 'monthly',
                request_limit: '',
                features: ['', '', '']
            });
            fetchPlans();
        } catch (err) {
            setMessage({ type: 'error', text: err.response?.data?.message || t('plans.saveError') || 'Failed to save plan' });
        }
    };

    const handleEdit = (plan) => {
        setEditingPlan(plan);
        setFormData({
            name: plan.name || '',
            name_ar: plan.name_ar || '',
            description: plan.description || '',
            description_ar: plan.description_ar || '',
            price: plan.price || '',
            interval: plan.interval || 'monthly',
            request_limit: plan.limits?.requests || '',
            features: (Array.isArray(plan.features) ? plan.features : []) || ['', '', '']
        });
        setShowForm(true);
    };

    const handleDelete = async (id) => {
        if (!confirm(t('plans.confirmDelete') || 'Are you sure you want to delete this plan?')) return;
        try {
            await api.delete(`/admin/plans/${id}`);
            setMessage({ type: 'success', text: t('plans.deleted') || 'Plan deleted successfully' });
            fetchPlans();
        } catch (err) {
            setMessage({ type: 'error', text: err.response?.data?.message || t('plans.deleteError') || 'Failed to delete plan' });
        }
    };

    const closeModal = () => {
        setShowForm(false);
        setEditingPlan(null);
        setFormData({
            name: '',
            name_ar: '',
            description: '',
            description_ar: '',
            price: '',
            interval: 'monthly',
            request_limit: '',
            features: ['', '', '']
        });
    };

    const handleFeatureChange = (index, value) => {
        const newFeatures = [...formData.features];
        newFeatures[index] = value;
        setFormData({ ...formData, features: newFeatures });
    };

    const addFeature = () => {
        setFormData({ ...formData, features: [...formData.features, ''] });
    };

    const removeFeature = (index) => {
        const newFeatures = formData.features.filter((_, i) => i !== index);
        setFormData({ ...formData, features: newFeatures });
    };

    useEffect(() => {
        fetchPlans();
    }, []);

    return (
        <DashboardLayout>
            <div className="space-y-8">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div className="flex items-center gap-5">
                        <div className="w-14 h-14 bg-gradient-to-tr from-[#7C3AED] to-purple-400 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-500/20">
                            <Package size={28} fill="currentColor" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-black text-[#1A202C] tracking-tight">
                                {t('plans.title')}
                            </h1>
                            <p className="text-gray-500 font-medium mt-1">
                                {t('plans.subtitle')}
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={() => setShowForm(true)}
                        className="flex items-center justify-center gap-2 px-6 py-3 bg-[#7C3AED] border-2 border-[#7C3AED] rounded-full text-sm font-bold text-white hover:bg-purple-700 transition-all shadow-sm"
                    >
                        <Plus size={18} />
                        {t('plans.new')}
                    </button>
                </div>

                {message && (
                    <div className={`p-4 rounded-xl border ${message.type === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'}`}>
                        <div className="flex items-center gap-2">
                            {message.type === 'error' ? <X size={20} /> : <Check size={20} />}
                            <span className="font-bold">{message.text}</span>
                        </div>
                    </div>
                )}

                {showForm && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-3xl p-8 w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                            <div className="flex items-center justify-between mb-6">
                                <h2 className="text-2xl font-black text-[#1A202C]">
                                    {editingPlan ? (t('plans.edit') || 'Edit Plan') : (t('plans.new') || 'New Plan')}
                                </h2>
                                <button onClick={closeModal} className="p-2 hover:bg-gray-100 rounded-xl transition">
                                    <X size={20} />
                                </button>
                            </div>
                            <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-2">
                                        {t('plans.nameEn')}
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.name}
                                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-2">
                                        {t('plans.nameAr')}
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.name_ar}
                                        onChange={(e) => setFormData({ ...formData, name_ar: e.target.value })}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        dir="rtl"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-2">
                                        {t('plans.descriptionEn')}
                                    </label>
                                    <textarea
                                        value={formData.description}
                                        onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        rows={3}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-2">
                                        {t('plans.descriptionAr')}
                                    </label>
                                    <textarea
                                        value={formData.description_ar}
                                        onChange={(e) => setFormData({ ...formData, description_ar: e.target.value })}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        rows={3}
                                        dir="rtl"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-2">
                                        {t('plans.price')}
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={formData.price}
                                        onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-2">
                                        {t('plans.interval')}
                                    </label>
                                    <select
                                        value={formData.interval}
                                        onChange={(e) => setFormData({ ...formData, interval: e.target.value })}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500"
                                    >
                                        <option value="monthly">{t('plans.monthly')}</option>
                                        <option value="yearly">{t('plans.yearly')}</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-2">
                                        {t('plans.requestLimit')}
                                    </label>
                                    <input
                                        type="number"
                                        value={formData.request_limit}
                                        onChange={(e) => setFormData({ ...formData, request_limit: e.target.value })}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        required
                                    />
                                </div>
                            </div>

                            <div>
                                <div className="flex items-center justify-between mb-4">
                                    <label className="block text-sm font-bold text-gray-700">
                                        {t('plans.features')}
                                    </label>
                                    <button
                                        type="button"
                                        onClick={addFeature}
                                        className="text-sm text-purple-600 hover:text-purple-700 font-bold"
                                    >
                                        {t('plans.addFeature')}
                                    </button>
                                </div>
                                {formData.features.map((feature, index) => (
                                    <div key={index} className="flex gap-2 mb-2">
                                        <input
                                            type="text"
                                            value={feature}
                                            onChange={(e) => handleFeatureChange(index, e.target.value)}
                                            placeholder={t('plans.featurePlaceholder')}
                                            className="flex-1 px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500"
                                        />
                                        {formData.features.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => removeFeature(index)}
                                                className="px-4 py-3 text-red-600 hover:bg-red-50 rounded-xl transition-colors"
                                            >
                                                <X size={20} />
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <div className="flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    className="px-6 py-3 border border-gray-200 rounded-xl font-bold text-gray-700 hover:bg-gray-50 transition-colors"
                                >
                                    {t('plans.cancel')}
                                </button>
                                <button
                                    type="submit"
                                    className="flex items-center gap-2 px-6 py-3 bg-[#7C3AED] text-white rounded-xl font-bold hover:bg-purple-700 transition-colors"
                                >
                                    <Save size={18} />
                                    {editingPlan ? (t('plans.update') || 'Update') : (t('plans.save') || 'Save')}
                                </button>
                            </div>
                        </form>
                    </div>
                    </div>
                )}

                {!showForm && plans.length === 0 && !loading && (
                    <div className="bg-white p-20 rounded-3xl border border-gray-100 shadow-sm text-center">
                        <Package size={48} className="mx-auto text-gray-300 mb-4" />
                        <p className="text-gray-500 font-bold">
                            {t('plans.noPlans') || 'لا توجد خطط حالياً'}
                        </p>
                        <p className="text-gray-400 text-sm mt-2">
                            {t('plans.noPlansDesc') || 'أنشئ خطتك الأولى للبدء'}
                        </p>
                    </div>
                )}

                {plans.length > 0 && (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {plans.map((plan) => (
                            <div key={plan.id} className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm relative group">
                                <div className="absolute top-4 right-4 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button onClick={() => handleEdit(plan)} className="p-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition">
                                        <Edit2 size={16} />
                                    </button>
                                    <button onClick={() => handleDelete(plan.id)} className="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                        <Trash2 size={16} />
                                    </button>
                                </div>
                                <h3 className="text-xl font-bold text-gray-900 mb-2">
                                    {isRTL ? plan.name_ar : plan.name}
                                </h3>
                                <p className="text-3xl font-black text-[#7C3AED] mb-4">
                                    ${plan.price}
                                    <span className="text-sm font-normal text-gray-500">
                                        /{plan.interval === 'monthly' ? t('plans.month') || 'month' : t('plans.year') || 'year'}
                                    </span>
                                </p>
                                <p className="text-gray-600 text-sm mb-4">
                                    {isRTL ? plan.description_ar : plan.description}
                                </p>
                                <div className="space-y-2">
                                    {(Array.isArray(plan.features) ? plan.features : []).map((feature, index) => (
                                        <div key={index} className="flex items-center gap-2 text-sm text-gray-600">
                                            <Check size={16} className="text-emerald-500" />
                                            {feature}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
