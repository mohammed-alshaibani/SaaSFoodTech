'use client';

import { useState, useEffect, useRef } from 'react';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import { Bell, Check, Trash2, Clock, CheckCircle2, MoreVertical, X } from 'lucide-react';
import api from '@/lib/api';
import { useEcho } from '@/hooks/useEcho';

export default function NotificationBell() {
    const { user } = useAuth();
    const { t, language } = useI18n();
    const [notifications, setNotifications] = useState([]);
    const [isOpen, setIsOpen] = useState(false);
    const [deletingId, setDeletingId] = useState(null);
    const dropdownRef = useRef(null);
    const isRTL = language === 'ar';

    useEffect(() => {
        if (user) {
            fetchNotifications();
        }
    }, [user]);

    const fetchNotifications = async () => {
        try {
            const res = await api.get('/notifications');
            setNotifications(res.data.data);
        } catch (err) {
            console.error('Failed to fetch notifications', err);
        }
    };

    const markAllRead = async () => {
        try {
            await api.post('/notifications/mark-as-read');
            setNotifications(prev => prev.map(n => ({ ...n, read_at: new Date().toISOString() })));
        } catch (err) {
            console.error('Failed to mark notifications as read', err);
        }
    };

    const markSingleRead = async (id) => {
        try {
            await api.patch(`/notifications/${id}/read`);
            setNotifications(prev => prev.map(n => n.id === id ? { ...n, read_at: new Date().toISOString() } : n));
        } catch (err) {
            console.error('Failed to mark notification as read', id, err);
        }
    };

    const deleteNotification = async (id) => {
        setDeletingId(id);
        // Delay to allow animation
        setTimeout(async () => {
            try {
                await api.delete(`/notifications/${id}`);
                setNotifications(prev => prev.filter(n => n.id !== id));
            } catch (err) {
                console.error('Failed to delete notification', id, err);
            } finally {
                setDeletingId(null);
            }
        }, 300);
    };

    // Listen for real-time notifications via Reverb
    useEcho(`App.Models.User.${user?.id}`, 'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', (notification) => {
        setNotifications(prev => [notification, ...prev]);
    }, [user?.id]);

    const unreadCount = notifications.filter(n => !n.read_at).length;

    useEffect(() => {
        function handleClickOutside(event) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const getRelativeTime = (dateStr) => {
        const date = new Date(dateStr);
        const now = new Date();
        const diffInSecs = Math.floor((now - date) / 1000);

        if (diffInSecs < 60) return isRTL ? 'الآن' : 'Now';
        if (diffInSecs < 3600) return Math.floor(diffInSecs / 60) + (isRTL ? ' د' : 'm');
        if (diffInSecs < 86400) return Math.floor(diffInSecs / 3600) + (isRTL ? ' س' : 'h');
        return Math.floor(diffInSecs / 86400) + (isRTL ? ' ي' : 'd');
    };

    const getNotificationMessage = (n) => {
        const action = n.data?.action || n.action;
        const title = n.data?.title || n.title;

        switch (action) {
            case 'created': return `${t('notifications.newRequest') || 'New Request'}: ${title}`;
            case 'accepted_customer':
            case 'accepted_admin': return `${t('notifications.acceptedByProvider') || 'Provider accepted'}: ${title}`;
            case 'work_done_customer':
            case 'work_done_admin': return `${t('notifications.workDone') || 'Work done'}: ${title}`;
            case 'completed_provider':
            case 'completed_admin': return `${t('notifications.customerCompleted') || 'Customer completed'}: ${title}`;
            case 'nearby_request': return `${t('notifications.nearbyNew') || 'New request nearby'}: ${title}`;
            case 'dropped_customer': return `${t('notifications.providerDropped') || 'Provider dropped'}: ${title}`;
            default: return n.data?.message || n.message || title;
        }
    };

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="p-2 text-gray-400 hover:text-navy hover:bg-gray-50 rounded-xl transition-all relative"
            >
                <Bell size={20} />
                {unreadCount > 0 && (
                    <span className="absolute top-2 right-2 w-4 h-4 bg-red-500 text-white text-[10px] flex items-center justify-center rounded-full border-2 border-white font-bold leading-none animate-in zoom-in">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className={`
                    absolute mt-3 w-80 bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 overflow-hidden
                    ${isRTL ? 'left-0' : 'right-0'}
                    animate-in fade-in slide-in-from-top-2 duration-200
                `}>
                    <div className="px-5 py-4 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <h3 className="text-sm font-black text-navy">{t('notifications.title') || 'Notifications'}</h3>
                            {unreadCount > 0 && (
                                <span className="px-1.5 py-0.5 bg-red-100 text-red-600 text-[9px] font-black rounded-full">
                                    {unreadCount}
                                </span>
                            )}
                        </div>
                        {unreadCount > 0 && (
                            <button
                                onClick={markAllRead}
                                className="text-[10px] font-bold text-primary hover:text-charcoal flex items-center gap-1 uppercase tracking-wider"
                            >
                                <Check size={12} /> {t('notifications.markAllRead') || 'Mark all as read'}
                            </button>
                        )}
                    </div>

                    <div className="max-h-[380px] overflow-y-auto overflow-x-hidden scrollbar-thin">
                        {notifications.length === 0 ? (
                            <div className="p-10 text-center flex flex-col items-center gap-3">
                                <div className="p-4 bg-gray-50 rounded-full text-gray-300">
                                    <Bell size={24} />
                                </div>
                                <p className="text-xs font-bold text-gray-400 uppercase tracking-widest leading-loose">
                                    {t('notifications.empty') || 'No notifications yet'}
                                </p>
                            </div>
                        ) : (
                            notifications.map((n) => (
                                <div
                                    key={n.id}
                                    className={`
                                        px-5 py-4 border-b border-gray-50 hover:bg-gray-50 transition-all relative group overflow-hidden
                                        ${!n.read_at ? 'bg-primary/[0.03]' : 'bg-white'}
                                        ${deletingId === n.id ? 'opacity-0 scale-95 duration-300 translate-x-2' : 'opacity-100 scale-100'}
                                    `}
                                >
                                    <div className="flex gap-4">
                                        <div className={`
                                            mt-0.5 rounded-lg p-2.5 h-fit relative
                                            ${!n.read_at ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-400'}
                                        `}>
                                            <CheckCircle2 size={16} />
                                            {!n.read_at && (
                                                <span className="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                                            )}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex justify-between items-start mb-1">
                                                <p className={`text-[13px] leading-snug pr-6 ${!n.read_at ? 'text-charcoal font-black' : 'text-gray-600 font-bold'}`}>
                                                    {getNotificationMessage(n)}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-3 text-[10px] font-bold text-gray-400 uppercase tracking-tight">
                                                <span className="flex items-center gap-1">
                                                    <Clock size={10} /> {getRelativeTime(n.created_at)}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Item Actions */}
                                    <div className={`
                                        absolute top-2 ${isRTL ? 'left-2' : 'right-2'} flex gap-1
                                        opacity-0 group-hover:opacity-100 transition-opacity bg-white/80 backdrop-blur-sm p-1 rounded-lg
                                    `}>
                                        {!n.read_at && (
                                            <button
                                                onClick={() => markSingleRead(n.id)}
                                                className="p-1.5 text-emerald-500 hover:bg-emerald-50 rounded-md transition-colors"
                                                title={t('notifications.markRead') || 'Mark as Read'}
                                            >
                                                <Check size={14} />
                                            </button>
                                        )}
                                        <button
                                            onClick={() => deleteNotification(n.id)}
                                            className="p-1.5 text-red-500 hover:bg-red-50 rounded-md transition-colors"
                                            title={t('common.delete') || 'Delete'}
                                        >
                                            <Trash2 size={14} />
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    <div className="p-3 bg-gray-50/50 text-center">
                        <button className="text-[10px] font-black text-gray-400 hover:text-navy uppercase tracking-[0.2em] transition">
                            {t('notifications.viewAll') || 'View All Notifications'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
