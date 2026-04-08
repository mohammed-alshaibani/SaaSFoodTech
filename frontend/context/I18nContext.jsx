'use client';

import { createContext, useContext, useState, useEffect } from 'react';

// Translation data
const translations = {
  ar: {
    auth: {
      login: 'تسجيل الدخول',
      register: 'إنشاء حساب',
      email: 'البريد الإلكتروني',
      password: 'كلمة المرور',
      confirmPassword: 'تأكيد كلمة المرور',
      signIn: 'دخول',
      signUp: 'تسجيل',
      alreadyHaveAccount: 'لديك حساب بالفعل؟',
      name: 'الاسم',
      forgotPassword: 'نسيت كلمة المرور؟',
      rememberMe: 'تذكرني',
      dontHaveAccount: 'ليس لديك حساب؟',
      loading: 'جاري التحميل...',
      loginSuccess: 'تم تسجيل الدخول بنجاح',
      registerSuccess: 'تم إنشاء الحساب بنجاح',
      loginError: 'فشل تسجيل الدخول. يرجى التحقق من بياناتك.',
      registerError: 'فشل إنشاء الحساب. يرجى المحاولة مرة أخرى.',
      emailRequired: 'البريد الإلكتروني مطلوب',
      emailInvalid: 'البريد الإلكتروني غير صالح',
      passwordRequired: 'كلمة المرور مطلوبة',
      passwordMinLength: 'يجب أن تكون كلمة المرور 8 أحرف على الأقل',
      passwordMismatch: 'كلمات المرور غير متطابقة',
      nameRequired: 'الاسم مطلوب',
      role: 'نوع الحساب',
      customer: 'عميل',
      provider: 'مزود خدمة',
      roleRequired: 'يرجى اختيار نوع الحساب',
      passwordCriteria: 'يجب أن تحتوي كلمة المرور على حرف كبير، حرف صغير، رقم، ورمز خاص',
      admin: 'مسؤول',
      companyName: 'اسم الشركة',
    },
    common: {
      home: 'الرئيسية',
      features: 'المميزات',
      pricing: 'الأسعار',
      faq: 'الأسئلة الشائعة',
      dashboard: 'لوحة التحكم',
      logout: 'تسجيل الخروج',
      profile: 'الملف الشخصي',
      settings: 'الإعدادات',
      language: 'اللغة',
    },
    hero: {
      badge: 'نظام بيئي لخدمات الشركات',
      title: 'تمكين',
      providers: 'المزودين',
      customers: 'العملاء',
      through: 'عبر التكنولوجيا الحديثة',
      description: 'السوق الموحد لطلبات الخدمات السلسة، والإدارة القائمة على الأدوار، والرؤى المدعومة بالذكاء الاصطناعي. صُمم للشركات التي تطلب الموثوقية.',
      getStarted: 'انضم إلى المركز اليوم',
      exploreFeatures: 'استكشف المميزات',
    },
    trust: {
      trustedBy: 'موثوق من قبل عمالقة الخدمات المبتكرة',
    },
    dashboard: {
      ecosystem: 'النظام البيئي الرقمي',
      title: 'منصة موحدة لكل',
      persona: 'مستخدم',
      description: 'من مركز قيادة المسؤول (Admin) إلى تجربة المزود عبر الهاتف، يقوم ServiceHub بمزامنة دورة حياة عملك في الوقت الفعلي.',
      insights: 'رؤى التحليل',
      requests: 'الطلبات',
      team: 'الفريق',
      activePlan: 'الخطة النشطة',
      enterprise: 'المؤسسات',
      liveTracking: 'تتبع مباشر لدورة الحياة',
      realtime: 'مزامنة لحظية',
      pending: 'قيد الانتظار',
      accepted: 'مقبول',
      complete: 'مكتمل',
      nearbyRadius: 'نطاق البحث القريب',
      geoEngine: 'محرك تحديد الموقع',
      scaleLimit: 'حد التوسع',
      unlimited: 'طلبات غير محدودة',
      paidTier: 'الفئة المدفوعة نشطة',
    },
    features: {
      engineered: 'مُصمم من أجل',
      reliability: 'الموثوقية',
      description: 'تم بناء محرك السوق الخاص بنا باستخدام أحدث التقنيات لضمان سلامة البيانات والسرعة والمرونة عبر المنصات منذ اليوم الأول.',
      rbac: 'إدارة أدوار متقدمة (RBAC)',
      rbacDesc: 'تحكم في الوصول قائم على الأدوار متعدد الطبقات مع مزامنة الأذونات الديناميكية. مسارات المشرف والمزود والعميل مفصلة بدقة على مستوى الـ API.',
      geo: 'محرك الجيولكيشن',
      geoDesc: 'تصفية قوية للطلبات القريبة ضمن نطاق 50 كم. يدعم الاستعلامات المكانية عالية الأداء مع نظام احتياطي للتطوير المحلي.',
      ai: 'أوصاف بالذكاء الاصطناعي',
      aiDesc: 'تحسين أوصاف الخدمات المدمج والمدعوم بالذكاء الاصطناعي. دع نماذج اللغة الكبيرة (LLMs) تعيد كتابة مسودات الطلبات لتصبح احترافية تلقائياً.',
      subscription: 'بوابات الاشتراك',
      subscriptionDesc: 'حقق أرباحاً من سوقك من خلال تقييد الميزات بمرونة. فرض قيود على إنشاء الطلبات للمستخدمين المجانيين وفتح آفاق التوسع للمشتركين المتميزين.',
      mobile: 'محسن للهواتف',
      mobileDesc: 'تنسيقات مستجيبة بالكامل مصممة لمزودي الخدمات أثناء التنقل. تحديثات الحالة في الوقت الفعلي ومسارات قبول مبسطة.',
      security: 'الأمن أولاً',
      securityDesc: 'إدارة جلسات بمستوى المؤسسات باستخدام ملفات تعريف الارتباط (httpOnly). حماية ضد هجمات XSS و CSRF بشكل افتراضي.',
    },
    pricing: {
      title: 'إستراتيجية',
      strategy: 'توسع شفافة',
      description: 'لا عقود معقدة. اختر الخطة التي تناسب حجم سوقك الحالي وقم بالترقية مع نموك.',
      freeTier: 'الفئة المجانية',
      freeDesc: 'مثالية للأفراد واستكشافات الخدمات الصغيرة.',
      proTier: 'فئة المحترفين (Pro)',
      proDesc: 'الخيار الاحترافي لتوسيع نطاق أعمال الخدمات.',
      startingAt: 'تبدأ من',
      mostPopular: 'الأكثر شيوعاً',
      getStarted: 'ابدأ مجاناً',
      upgrade: 'الترقية إلى Pro',
      feature1: 'حتى 3 طلبات خدمة',
      feature2: 'تصفية المواقع القريبة (50 كم)',
      feature3: 'تحسين الأوصاف بالذكاء الاصطناعي',
      feature4: 'إدارة متقدمة للأدوار (RBAC)',
      feature5: 'دعم ذو أولوية للمزودين',
      feature6: 'توسع غير محدود',
      feature7: 'طلبات خدمة غير محدودة',
      feature8: 'إحصائيات ورؤى المنصة',
    },
    faq: {
      title: 'الاستفسارات',
      inquiries: 'الشائعة',
      description: 'كل ما تحتاج لمعرفته حول بنية السوق المعمارية.',
      q1: 'كيف تعمل تصفية الطلبات القريبة؟',
      a1: 'يستخدم محركنا استعلامات مكانية محسنة للغاية (MySQL ST_Distance_Sphere) لجلب طلبات الخدمة ضمن نصف قطر 50 كم من موقعك الحالي. كما نوفر نظاماً حسابياً احتياطياً للتطوير المحلي على SQLite.',
      q2: 'هل تحسين الوصف بالذكاء الاصطناعي إلزامي؟',
      a2: 'ليس على الإطلاق. يمكن للعملاء اختيار "تحسين" أوصافهم عبر خدمة الذاء الاصطناعي المدمجة لدينا، ولكن يتم الاحتفاظ بالنص الأصلي في حال تجاوز الخدمة أو فشلها.',
      q3: 'هل يمكنني إدارة حسابات مزودين متعددة؟',
      a3: 'نعم. يدعم نظام RBAC المتقدم لدينا أدوار "مسؤول المزود" التي يمكنها إدارة الأذونات لعدة حسابات "موظف مزود" ضمن نفس المؤسسة.',
      q4: 'كيف يتم التعامل مع أمن الجلسات؟',
      a4: 'نحن نعطي الأولوية للأمن عبر استخدام ملفات تعريف الارتباط من نوع httpOnly و Secure و SameSite=Lax. هذا يقلل بفعالية من نواقل هجوم XSS و CSRF الشائعة.',
    },
    footer: {
      about: 'الجيل القادم من منصات سوق الخدمات للمؤسسات. نحدث الطريقة التي يتفاعل بها المزودون والعملاء.',
      solutions: 'الحلول',
      features: 'شبكة المميزات',
      pricing: 'فئات الاشتراك',
      api: 'بيئة تجربة الـ API',
      knowledge: 'المعرفة',
      architecture: 'البنية المعمارية',
      docs: 'التوثيق',
      ctaTitle: 'هل أنت جاهز لتحويل طريقة تقديم خدماتك؟',
      getStarted: 'ابدأ الآن',
      copyright: ' 2026 سوق SERVICEHUB. جميع الحقوق محفوظة.',
      privacy: 'بروتوكول الخصوصية',
      security: 'الامتثال الأمني',
    },
  },
  en: {
    auth: {
      login: 'Login',
      register: 'Register',
      email: 'Email',
      password: 'Password',
      confirmPassword: 'Confirm Password',
      signIn: 'Sign In',
      signUp: 'Sign Up',
      alreadyHaveAccount: 'Already have an account?',
      name: 'Name',
      forgotPassword: 'Forgot Password?',
      rememberMe: 'Remember Me',
      dontHaveAccount: "Don't have an account?",
      loading: 'Loading...',
      loginSuccess: 'Login successful',
      registerSuccess: 'Registration successful',
      loginError: 'Login failed. Please check your credentials.',
      registerError: 'Registration failed. Please try again.',
      emailRequired: 'Email is required',
      emailInvalid: 'Please enter a valid email',
      passwordRequired: 'Password is required',
      passwordMinLength: 'Password must be at least 8 characters',
      passwordMismatch: 'Passwords do not match',
      nameRequired: 'Name is required',
      role: 'Account Type',
      customer: 'Customer',
      provider: 'Service Provider',
      roleRequired: 'Please select an account type',
      passwordCriteria: 'Password must contain uppercase, lowercase, number, and special character',
      admin: 'Administrator',
      companyName: 'Company Name',
    },
    common: {
      dashboard: 'Dashboard',
      logout: 'Logout',
      profile: 'Profile',
      settings: 'Settings',
      home: 'Home',
      language: 'Language',
      features: 'Features',
      pricing: 'Pricing',
      faq: 'FAQ',
    },
    hero: {
      badge: 'Enterprise Service Ecosystem',
      title: 'Empowering',
      providers: 'Providers',
      customers: 'Customers',
      through: 'Through Modern Tech',
      description: 'The unified marketplace for seamless service requests, role-based management, and AI-driven insights. Built for enterprises demanding reliability.',
      getStarted: 'Join the Hub Today',
      exploreFeatures: 'Explore Features',
    },
    trust: {
      trustedBy: 'Trusted by Innovative Service Giants',
    },
    dashboard: {
      ecosystem: 'Digital Ecosystem',
      title: 'A Unified Platform For Every',
      persona: 'Persona',
      description: 'From the Admin command center to the Provider mobile experience ServiceHub synchronizes your business lifecycle in real-time.',
      insights: 'Insights',
      requests: 'Requests',
      team: 'Team',
      activePlan: 'Active Plan',
      enterprise: 'Enterprise',
      liveTracking: 'Live Lifecycle Tracking',
      realtime: 'Real-time sync',
      pending: 'Pending',
      accepted: 'Accepted',
      complete: 'Complete',
      nearbyRadius: 'Nearby Radius',
      geoEngine: 'Geolocation Engine',
      scaleLimit: 'Scale Limit',
      unlimited: 'Unlimited Requests',
      paidTier: 'Paid Tier Active',
    },
    features: {
      engineered: 'Engineered for',
      reliability: 'Reliability',
      description: 'Our marketplace engine is built with the modern stack to ensure data integrity, speed, and cross-platform flexibility from day one.',
      rbac: 'Advanced RBAC',
      rbacDesc: 'Multi-layered role-based access control with dynamic permission syncing. Admin, Provider, and Customer flows are strictly separated at the API level.',
      geo: 'Geolocation Engine',
      geoDesc: 'Powerful nearby request filtering with a 50km radius. Supports high-performance spatial queries with full local development fallback.',
      ai: 'AI Descriptions',
      aiDesc: 'Built-in AI-powered service description enhancement. Let LLMs rewrite rough request drafts into professional service briefs automatically.',
      subscription: 'Subscription Gating',
      subscriptionDesc: 'Monetize your marketplace with flexible feature-gating. Enforce creation limits for free users and unlock scale for premium subscribers.',
      mobile: 'Mobile-Optimized',
      mobileDesc: 'Fully responsive layouts designed for on-the-go service providers. Real-time status updates and simplified acceptance flows.',
      security: 'Security First',
      securityDesc: 'Enterprise-grade session management with httpOnly cookies. Protection against XSS and CSRF out of the box.',
    },
    pricing: {
      title: 'Transparent Scaling',
      strategy: 'Strategy',
      description: 'No complicated contracts. Choose a plan that suits your current marketplace volume and upgrade as you grow.',
      freeTier: 'Free Tier',
      freeDesc: 'Ideal for individuals and small service explorations.',
      proTier: 'Pro Tier',
      proDesc: 'The professional choice for scaling service businesses.',
      startingAt: 'Starting at',
      mostPopular: 'Most Popular',
      getStarted: 'Get Started Free',
      upgrade: 'Upgrade to Pro',
      feature1: 'Up to 3 Service Requests',
      feature2: 'Nearby Filtering (50km)',
      feature3: 'AI Description Enhancement',
      feature4: 'Advanced RBAC Management',
      feature5: 'Priority Provider Support',
      feature6: 'Unlimited Scaling',
      feature7: 'Unlimited Service Requests',
      feature8: 'Platform Stats & Insights',
    },
    faq: {
      title: 'Common',
      inquiries: 'Inquiries',
      description: 'Everything you need to know about the marketplace architecture.',
      q1: 'How does the nearby filtering work?',
      a1: 'Our engine uses highly optimized spatial queries (MySQL ST_Distance_Sphere) to retrieve service requests within a 50km radius of your current location. We also provide a trigonometric fallback for local development on SQLite.',
      q2: 'Is the AI description enhancement mandatory?',
      a2: 'Not at all. Customers can choose to \'Enhance\' their rough descriptions via our integrated AI service, but original text is preserved if the service is bypassed or fails.',
      q3: 'Can I manage multiple provider accounts?',
      a3: 'Yes. Our Advanced RBAC system supports \'Provider Admin\' roles that can manage permissions for multiple \'Provider Employee\' accounts within the same organization.',
      q4: 'How is session security handled?',
      a4: 'We prioritize security over convenience by using httpOnly, secure, SameSite=Lax cookies for session management. This effectively mitigates common XSS and CSRF attack vectors.',
    },
    footer: {
      about: 'The next-generation service marketplace platform for enterprises. Modernizing the way providers and customers interact.',
      solutions: 'Solutions',
      features: 'Feature Grid',
      pricing: 'Subscription Tiers',
      api: 'API Sandbox',
      knowledge: 'Knowledge',
      architecture: 'Architecture',
      docs: 'Documentation',
      ctaTitle: 'Ready to transform your delivery?',
      getStarted: 'Get Started',
      copyright: '© 2026 SERVICEHUB MARKETPLACE. ALL RIGHTS RESERVED.',
      privacy: 'Privacy Protocol',
      security: 'Security Compliance',
    },
  },
};

// Context
const I18nContext = createContext(undefined);

// Provider
export function I18nProvider({ children }) {
  const [locale, setLocale] = useState('ar'); // Default to Arabic

  // Load saved locale from localStorage on mount
  useEffect(() => {
    const savedLocale = localStorage.getItem('locale');
    if (savedLocale && translations[savedLocale]) {
      setLocale(savedLocale);
    }
  }, []);

  // Save locale to localStorage when it changes
  useEffect(() => {
    localStorage.setItem('locale', locale);
  }, [locale]);

  const t = (key) => {
    const keys = key.split('.');
    let value = translations[locale];

    for (const k of keys) {
      value = value?.[k];
    }

    return value || key;
  };

  const changeLanguage = (newLocale) => {
    if (translations[newLocale]) {
      setLocale(newLocale);
    }
  };

  const value = {
    locale,
    t,
    changeLanguage,
    isRTL: locale === 'ar',
  };

  return (
    <I18nContext.Provider value={value}>
      {children}
    </I18nContext.Provider>
  );
}

// Hook
export function useI18n() {
  const context = useContext(I18nContext);
  if (context === undefined) {
    throw new Error('useI18n must be used within an I18nProvider');
  }
  return context;
}
