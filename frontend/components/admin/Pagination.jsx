'use client';

import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export function Pagination({ currentPage, totalPages, onPageChange, totalEntries }) {
    if (totalPages <= 1) return null;

    return (
        <div className="flex flex-col md:flex-row items-center justify-between gap-6 py-4 px-4">
            <p className="text-[10px] font-bold text-gray-500 uppercase tracking-widest">
                PAGE {currentPage} OF {totalPages} // TOTAL {totalEntries} ENTRIES
            </p>
            <div className="flex gap-2">
                <button
                    disabled={currentPage <= 1}
                    onClick={() => onPageChange(currentPage - 1)}
                    className="w-10 h-10 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-gray-600 disabled:opacity-50 hover:bg-gray-50 transition"
                >
                    <ChevronLeft size={20} />
                </button>
                <button
                    disabled={currentPage >= totalPages}
                    onClick={() => onPageChange(currentPage + 1)}
                    className="w-10 h-10 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-gray-600 disabled:opacity-50 hover:bg-gray-50 transition"
                >
                    <ChevronRight size={20} />
                </button>
            </div>
        </div>
    );
}
