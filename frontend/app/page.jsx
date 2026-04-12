import Navbar from '@/components/landing/Navbar';
import Hero from '@/components/landing/Hero';
import TrustBar from '@/components/landing/TrustBar';
import DashboardMock from '@/components/landing/DashboardMock';
import Features from '@/components/landing/Features';
import Pricing from '@/components/landing/Pricing';
import FAQ from '@/components/landing/FAQ';
import Footer from '@/components/landing/Footer';

export const metadata = {
    title: 'ServiceHub | Standardized SaaS for FoodTech',
    description: 'The ultimate platform for service providers and customers in the FoodTech industry. Manage requests, subscriptions, and scale your business.',
    keywords: ['foodtech', 'saas', 'service provider', 'customer management', 'platform'],
    openGraph: {
        title: 'ServiceHub | FoodTech SaaS',
        description: 'Connect with providers and customers seamlessly.',
        type: 'website',
    }
};

export default function LandingPage() {
    // This is now a Server Component. It renders the skeleton of the landing page.
    // Interactive parts like Navbar and Hero handle their own Client-side state.

    return (
        <main className="relative bg-slate-50 selection:bg-primary/20 selection:text-primary min-h-screen overflow-x-hidden">

            {/* Structural Elements */}
            <Navbar />

            {/* Sections */}
            <Hero />
            <TrustBar />
            <DashboardMock />
            <Features />
            <Pricing />
            <FAQ />

            {/* Footer */}
            <Footer />

            {/* Floating CTA (Post-Hero) */}
            <div className="fixed bottom-8 right-8 z-40 hidden md:block">
                <a
                    href="/register"
                    className="px-6 py-3 bg-accent text-white rounded-full font-black text-xs uppercase tracking-widest shadow-2xl shadow-accent/20 hover:bg-accent-dark transition-all active:scale-95 flex items-center gap-2"
                >
                    Get Started
                </a>
            </div>

        </main>
    );
}
