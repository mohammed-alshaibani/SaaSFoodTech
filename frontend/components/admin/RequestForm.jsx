'use client';

import { FileText, Edit3, XCircle, Trash2, Loader2, CheckCircle2, MapPin, Save } from 'lucide-react';
import React from 'react';

/**
 * RequestForm Component
 * Shared between Admin and Customer views for consistency.
 */
export default function RequestForm({
    request,
    editData,
    setEditData,
    onSave,
    onDelete,
    onClose,
    loading,
    isRTL,
    STATUS_LABELS,
    isAdmin = false,
    mode = 'modal' // 'modal' or 'page'
}) {
    if (!request) return null;

    const content = (
        <div className={`bg-white ${mode === 'modal' ? 'rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden animate-in zoom-in-95 duration-200' : 'rounded-[32px] border border-gray-100'}`}>
            {/* Header */}
            <div className={`p-6 border-b border-gray-100 flex items-center justify-between ${mode === 'modal' ? 'bg-gray-50/50' : 'bg-white'}`}>
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center">
                        <Edit3 size={20} />
                    </div>
                    <h2 className="text-xl font-black text-[#1A202C]">
                        {isRTL ? 'بيانات الطلب' : 'Request Details'} #{request.id}
                    </h2>
                </div>
                {onClose && (
                    <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full transition-colors">
                        <XCircle size={24} className="text-gray-400" />
                    </button>
                )}
            </div>

            {/* Body */}
            <div className="p-8 space-y-8">
                <div className="space-y-6">
                    <div className="space-y-1.5">
                        <label className="text-xs font-black text-gray-400 uppercase tracking-widest">{isRTL ? 'موضوع الطلب' : 'Title'}</label>
                        <input
                            type="text"
                            value={editData.title}
                            onChange={(e) => setEditData({ ...editData, title: e.target.value })}
                            className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl font-bold text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] shadow-inner"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-xs font-black text-gray-400 uppercase tracking-widest">{isRTL ? 'وصف الطلب' : 'Description'}</label>
                        <textarea
                            value={editData.description}
                            onChange={(e) => setEditData({ ...editData, description: e.target.value })}
                            rows={5}
                            className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[#1A202C] outline-none focus:border-[#7C3AED] focus:ring-1 focus:ring-[#7C3AED] leading-relaxed shadow-inner resize-none"
                        />
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-1.5">
                            <label className="text-xs font-black text-gray-400 uppercase tracking-widest">{isRTL ? 'الحالة' : 'Status'}</label>
                            {isAdmin ? (
                                <select
                                    value={editData.status}
                                    onChange={(e) => setEditData({ ...editData, status: e.target.value })}
                                    className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl font-bold text-[#1A202C] outline-none focus:border-[#7C3AED]"
                                >
                                    {Object.entries(STATUS_LABELS).map(([v, l]) => (
                                        <option key={v} value={v}>{l}</option>
                                    ))}
                                </select>
                            ) : (
                                <div className="p-3 bg-gray-100 border border-gray-100 rounded-xl text-gray-500 font-bold text-sm">
                                    {isRTL ? STATUS_LABELS[request.status] : request.status}
                                </div>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <label className="text-xs font-black text-gray-400 uppercase tracking-widest">{isRTL ? 'العميل' : 'Customer'}</label>
                            <div className="p-3 bg-gray-50 border border-gray-100 rounded-xl text-gray-500 font-bold text-sm">
                                {request.customer?.name || '-'}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-4 p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <div className="flex-1 space-y-1">
                            <p className="text-[10px] font-black text-gray-400 uppercase">{isRTL ? 'إحداثيات الموقع' : 'Location'}</p>
                            <div className="flex gap-4">
                                <input
                                    type="text"
                                    value={editData.latitude}
                                    onChange={e => setEditData({ ...editData, latitude: e.target.value })}
                                    className="w-full bg-transparent border-b border-gray-200 font-mono text-sm outline-none focus:border-[#7C3AED]"
                                    placeholder="Lat"
                                />
                                <input
                                    type="text"
                                    value={editData.longitude}
                                    onChange={e => setEditData({ ...editData, longitude: e.target.value })}
                                    className="w-full bg-transparent border-b border-gray-200 font-mono text-sm outline-none focus:border-[#7C3AED]"
                                    placeholder="Lng"
                                />
                            </div>
                        </div>
                        <MapPin className="text-[#7C3AED]" size={24} />
                    </div>
                </div>
            </div>

            {/* Footer */}
            <div className={`p-6 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row justify-between items-center gap-4`}>
                <div className="w-full sm:w-auto">
                    {onDelete && (
                        <button
                            onClick={() => onDelete(request.id)}
                            disabled={loading}
                            className="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-red-50 text-red-600 font-bold hover:bg-red-100 transition-all flex items-center justify-center gap-2"
                        >
                            <Trash2 size={18} />
                            {isRTL ? 'حذف الطلب' : 'Delete Request'}
                        </button>
                    )}
                </div>

                <div className="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    {onClose && (
                        <button
                            onClick={onClose}
                            disabled={loading}
                            className="px-6 py-2.5 rounded-xl font-bold text-gray-500 hover:bg-white transition-all border border-gray-200"
                        >
                            {isRTL ? 'إلغاء' : 'Cancel'}
                        </button>
                    )}
                    <button
                        onClick={onSave}
                        disabled={loading}
                        className="px-10 py-2.5 rounded-xl bg-[#7C3AED] text-white font-bold hover:bg-[#6D28D9] transition-all flex items-center justify-center gap-2 shadow-lg shadow-[#7C3AED]/20"
                    >
                        {loading ? <Loader2 size={18} className="animate-spin" /> : <Save size={18} />}
                        {isRTL ? 'حفظ البيانات' : 'Save Details'}
                    </button>
                </div>
            </div>
        </div>
    );

    if (mode === 'modal') {
        return (
            <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm animate-in fade-in duration-200">
                {content}
            </div>
        );
    }

    return content;
}
