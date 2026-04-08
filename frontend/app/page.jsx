'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthenticationContext';

// Landing Page Components
import Navbar from '@/components/landing/Navbar';
import Hero from '@/components/landing/Hero';
import TrustBar from '@/components/landing/TrustBar';
import DashboardMock from '@/components/landing/DashboardMock';
import Features from '@/components/landing/Features';
import Pricing from '@/components/landing/Pricing';
import FAQ from '@/components/landing/FAQ';
import Footer from '@/components/landing/Footer';

export default function LandingPage() {
    const { user, loading } = useAuth();
    const router = useRouter();

    // Public Access: Home page is open to all.
    // Dashboard redirect is now handled manually via the Hero component.

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-white">
                <div className="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin" />
            </div>
        );
    }

    // If user is not logged in, show the Landing Page
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
