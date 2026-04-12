'use client';

import React from 'react';
import { Search, Filter, ChevronDown } from 'lucide-react';

export function ProviderFilters({
    searchTerm,
    onSearchChange,
    statusFilter,
    onStatusFilterChange,
    sortBy,
    onSortByChange,
    showFilters,
    onToggleFilters,
    t
}) {
    return (
        <div className="space-y-4">
            <div className="flex flex-col lg:flex-row gap-4">
                <div className="flex-1 relative">
                    <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
                    <input
                        type="text"
                        placeholder={t('dashboard.searchPlaceholder') || 'Search...'}
                        value={searchTerm}
                        onChange={e => onSearchChange(e.target.value)}
                        className="w-full bg-white border border-gray-200 rounded-xl pl-12 pr-4 py-3 text-sm font-bold text-charcoal outline-none focus:ring-2 focus:ring-primary focus:border-primary transition shadow-sm"
                    />
                </div>
                <button
                    onClick={onToggleFilters}
                    className="px-6 py-3 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 hover:text-primary hover:border-primary/30 transition flex items-center gap-2 shadow-sm"
                >
                    <Filter size={18} /> {t('dashboard.filters') || 'Filters'} <ChevronDown size={18} className={`transition-transform ${showFilters ? 'rotate-180' : ''}`} />
                </button>
            </div>

            {showFilters && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 bg-white border border-gray-200 rounded-3xl animate-in fade-in slide-in-from-top-4 shadow-sm">
                    <div className="space-y-3">
                        <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">STATUS</label>
                        <select
                            value={statusFilter}
                            onChange={e => onStatusFilterChange(e.target.value)}
                            className="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold text-charcoal outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                        >
                            <option value="all">All</option>
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div className="space-y-3">
                        <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">SORT BY</label>
                        <select
                            value={sortBy}
                            onChange={e => onSortByChange(e.target.value)}
                            className="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold text-charcoal outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                        >
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="nearest">Distance</option>
                        </select>
                    </div>
                </div>
            )}
        </div>
    );
}
