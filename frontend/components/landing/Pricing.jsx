'use client';

import { useState, useEffect } from 'react';
import { CheckCircle2, XCircle, Loader2 } from 'lucide-react';
import { useRouter } from 'next/navigation';
import { useI18n } from '@/context/I18nContext';
import { useAuth } from '@/context/AuthenticationContext';
import api from '@/lib/api';

export default function Pricing() {
    const { t, locale } = useI18n();
    const { user } = useAuth();
    const router = useRouter();

    const [plans, setPlans] = useState([]);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState(null);
    const [subscribeLoading, setSubscribeLoading] = useState(false);

    // Fetch plans from API
    useEffect(() => {
        const fetchPlans = async () => {
            try {
                const res = await api.get('/subscription/plans');
                const plansData = res.data?.data || [];
                // Sort by price (free first, then paid)
                const sortedPlans = plansData.sort((a, b) => (a.price || 0) - (b.price || 0));
                setPlans(sortedPlans);
            } catch (err) {
                console.error('Failed to fetch plans:', err);
                // Fallback to empty, component will show loading state
            } finally {
                setLoading(false);
            }
        };

        fetchPlans();
    }, []);

    // Transform API plan to component format
    const getPlanFeatures = (plan) => {
        const features = plan.features || {};
        const limits = plan.limits || {};
        
        // Build features list dynamically from plan data
        const featureList = [];
        
        // Request limit feature
        if (limits.requests_per_month === 'unlimited' || limits.requests === 'unlimited') {
            featureList.push({ name: locale === 'ar' ? 'طلبات خدمة غير محدودة' : 'Unlimited Service Requests', active: true });
        } else if (limits.requests_per_month || limits.requests) {
            const limit = limits.requests_per_month || limits.requests;
            featureList.push({ 
                name: locale === 'ar' ? `حتى ${limit} طلب خدمة` : `Up to ${limit} Service Requests`, 
                active: true 
            });
        }
        
        // Other features from plan.features object
        if (features.geolocation !== false) {
            featureList.push({ name: locale === 'ar' ? 'تصفية قريبة (50 كم)' : 'Nearby Filtering (50km)', active: true });
        }
        if (features.ai_enhancement) {
            featureList.push({ name: locale === 'ar' ? 'تحسين الوصف بالذكاء الاصطناعي' : 'AI Description Enhancement', active: true });
        } else {
            featureList.push({ name: locale === 'ar' ? 'تحسين الوصف بالذكاء الاصطناعي' : 'AI Description Enhancement', active: false });
        }
        if (features.rbac_management) {
            featureList.push({ name: locale === 'ar' ? 'إدارة الأدوار المتقدمة' : 'Advanced RBAC Management', active: true });
        } else {
            featureList.push({ name: locale === 'ar' ? 'إدارة الأدوار المتقدمة' : 'Advanced RBAC Management', active: false });
        }
        if (features.priority_support) {
            featureList.push({ name: locale === 'ar' ? 'دعم أولوي للمزودين' : 'Priority Provider Support', active: true });
        } else {
            featureList.push({ name: locale === 'ar' ? 'دعم أولوي للمزودين' : 'Priority Provider Support', active: false });
        }
        if (features.platform_stats) {
            featureList.push({ name: locale === 'ar' ? 'إحصاءات ورؤى المنصة' : 'Platform Stats & Insights', active: true });
        } else {
            featureList.push({ name: locale === 'ar' ? 'إحصاءات ورؤى المنصة' : 'Platform Stats & Insights', active: false });
        }
        
        return featureList;
    };

    // Transform API plans to tiers
    const TIERS = plans.map((plan, index) => {
        const isFree = (plan.price || 0) === 0;
        const isPopular = plan.is_popular || plan.popular || (!isFree && index === 1);
        
        // Get bilingual name and description
        const name = locale === 'ar' && plan.display_name ? plan.display_name : (plan.name || 'Plan');
        const desc = locale === 'ar' && plan.description_ar ? plan.description_ar : 
                     (plan.description || (isFree ? 
                        (locale === 'ar' ? 'مثالية للأفراد واستكشاف الخدمات الصغيرة.' : 'Ideal for individuals and small service explorations.') :
                        (locale === 'ar' ? 'الخيار الاحترافي لتوسيع نطاق أعمال الخدمات.' : 'The professional choice for scaling service businesses.')));
        
        // Determine CTA text
        let cta;
        if (!user) {
            cta = isFree ? (t('pricing.getStarted') || (locale === 'ar' ? 'ابدأ مجاناً' : 'Get Started Free')) : 
                          (t('dashboard.loginToUpgrade') || (locale === 'ar' ? 'سجل الدخول للترقية' : 'Login to Upgrade'));
        } else if (user.plan === plan.name || user.plan === plan.id) {
            cta = t('dashboard.activePlan') || (locale === 'ar' ? 'الخطة النشطة' : 'Active Plan');
        } else if (isFree) {
            cta = t('dashboard.downgrade') || (locale === 'ar' ? 'تخفيض' : 'Downgrade');
        } else {
            cta = t('dashboard.upgradeNow') || (locale === 'ar' ? 'اشترك الآن' : 'Upgrade Now');
        }
        
        return {
            id: plan.id || plan.name,
            name,
            price: plan.price || 0,
            desc,
            features: getPlanFeatures(plan),
            cta,
            popular: isPopular,
            isFree,
            planData: plan // Keep original data for subscription
        };
    });

    const handleSubscribe = async (tier) => {
        if (!user) {
            router.push(`/login?redirect=/dashboard&plan=${tier.id}`);
            return;
        }

        // If already on this plan, go to dashboard
        if (user.plan === tier.id || user.plan === tier.planData?.name) {
            router.push('/dashboard');
            return;
        }

        // Free plan - just redirect to dashboard
        if (tier.isFree) {
            router.push('/dashboard');
            return;
        }

        // Paid plan - request upgrade
        setSubscribeLoading(true);
        try {
            await api.post('/subscription/upgrade', { plan: tier.planData?.name || tier.id });
            const successMsg = locale === 'ar' ? 
                'تم إرسال طلب الترقية! يرجى انتظار موافقة المدير.' : 
                'Upgrade request sent! Please wait for admin approval.';
            setMessage({ text: successMsg, type: 'success' });
        } catch (err) {
            const errorMsg = locale === 'ar' ? 
                'فشل طلب الترقية.' : 
                'Failed to request upgrade.';
            setMessage({ text: errorMsg, type: 'error' });
        } finally {
            setSubscribeLoading(false);
            setTimeout(() => setMessage(null), 5000);
        }
    };

    if (loading) {
        return (
            <section id="pricing" className="py-24 bg-slate-50 relative overflow-hidden">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-center min-h-[400px]">
                        <Loader2 className="w-12 h-12 text-blue-600 animate-spin" />
                    </div>
                </div>
            </section>
        );
    }

    return (
        <section id="pricing" className="py-24 bg-slate-50 relative overflow-hidden">
            {/* Background Decor */}
            <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-blue-500/5 rounded-full blur-[100px] -z-10" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="text-center mb-16 space-y-4">
                    <h2 className="text-3xl md:text-5xl font-black text-gray-900 tracking-tight">
                        {t('pricing.title') || 'Transparent Scaling'} <span className="text-blue-500 underline decoration-blue-500/10 underline-offset-4 decoration-8">{t('pricing.strategy') || 'Strategy'}</span>.
                    </h2>
                    <p className="text-gray-500 font-medium max-w-xl mx-auto leading-relaxed">
                        {t('pricing.description') || 'No complicated contracts. Choose a plan that suits your current marketplace volume and upgrade as you grow.'}
                    </p>
                </div>

                {/* Pricing Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
                    {TIERS.length === 0 && (
                        <div className="col-span-2 text-center py-12">
                            <p className="text-gray-500">
                                {locale === 'ar' ? 'لا توجد خطط متاحة حالياً.' : 'No plans available at the moment.'}
                            </p>
                        </div>
                    )}
                    {TIERS.map((tier) => (
                        <div
                            key={tier.id}
                            className={`p-10 rounded-[2.5rem] border bg-white relative shadow-xl shadow-slate-200/50 flex flex-col h-full transform transition-all hover:scale-[1.02] ${tier.popular ? 'border-blue-600 ring-2 ring-blue-600/5' : 'border-gray-100'
                                }`}
                        >
                            {tier.popular && (
                                <div className="absolute -top-4 left-10 py-1.5 px-4 bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest rounded-full shadow-lg shadow-blue-600/20">
                                    {t('pricing.mostPopular') || 'Most Popular'}
                                </div>
                            )}

                            <div className="mb-10">
                                <h3 className="text-2xl font-black text-gray-900 mb-2">{tier.name}</h3>
                                <p className="text-sm text-gray-400 font-bold mb-8 uppercase tracking-widest leading-none">{t('pricing.startingAt') || 'Starting at'}</p>
                                <div className="flex items-baseline gap-1">
                                    <span className="text-5xl font-black text-gray-900 leading-none">${tier.price}</span>
                                    <span className="text-gray-400 font-medium tracking-tight">/mo</span>
                                </div>
                                <p className="text-sm text-gray-500 font-medium mt-6 leading-relaxed">
                                    {tier.desc}
                                </p>
                            </div>

                            <div className="space-y-4 mb-12 flex-grow">
                                {tier.features.map(f => (
                                    <div key={f.name} className="flex items-center gap-3">
                                        <div className={f.active ? 'text-blue-600' : 'text-slate-200'}>
                                            {f.active ? <CheckCircle2 size={18} strokeWidth={3} /> : <XCircle size={18} strokeWidth={2} />}
                                        </div>
                                        <span className={`text-[13px] font-bold tracking-tight ${f.active ? 'text-gray-700' : 'text-slate-300'}`}>
                                            {f.name}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            <button
                                onClick={() => handleSubscribe(tier)}
                                disabled={subscribeLoading}
                                className={`w-full py-4 rounded-2xl text-sm font-black transition-all shadow-lg active:scale-[0.98] text-center disabled:opacity-50 disabled:cursor-not-allowed ${tier.popular
                                    ? 'bg-blue-600 text-white hover:bg-blue-700 shadow-blue-600/10'
                                    : 'bg-slate-100 text-gray-800 hover:bg-slate-200'
                                    }`}
                            >
                                {subscribeLoading ? (
                                    <span className="flex items-center justify-center gap-2">
                                        <Loader2 className="w-4 h-4 animate-spin" />
                                        {locale === 'ar' ? 'جاري...' : 'Loading...'}
                                    </span>
                                ) : tier.cta}
                            </button>
                        </div>
                    ))}
                </div>
            </div>
            {message && (
                <div className={`fixed bottom-10 right-10 z-[60] px-8 py-5 rounded-[24px] text-sm font-black shadow-2xl animate-in slide-in-from-bottom-5 border ${message.type === 'error' ? 'bg-red-600 border-red-500 text-white' : 'bg-indigo-600 border-indigo-500 text-white'}`}>
                    {message.text}
                </div>
            )}
        </section>
    );
}
