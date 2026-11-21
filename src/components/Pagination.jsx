import React, { memo } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export const Pagination = memo(({
  page = 1,
  totalPages = 1,
  totalItems,
  pageSize,
  onPageChange,
  onPageSizeChange,
  pageSizeOptions = [10, 20, 50],
  compact = false,
  className = '',
}) => {
  if (totalPages <= 1 && (!totalItems || totalItems <= (pageSize || 10))) {
    return null;
  }

  const handlePrev = () => {
    if (page > 1) {
      onPageChange(page - 1);
    }
  };

  const handleNext = () => {
    if (page < totalPages) {
      onPageChange(page + 1);
    }
  };

  const startItem = totalItems !== undefined && pageSize ? Math.min((page - 1) * pageSize + 1, totalItems) : null;
  const endItem = totalItems !== undefined && pageSize ? Math.min(page * pageSize, totalItems) : null;

  if (compact) {
    return (
      <div className={`flex items-center justify-between text-xs text-gray-500 py-1.5 px-1 ${className}`}>
        <span className="text-[11px] font-medium text-gray-600">
          Page {page} of {Math.max(1, totalPages)}
        </span>
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={handlePrev}
            disabled={page <= 1}
            title="Previous page"
            className="p-1 rounded border border-border bg-white text-gray-600 hover:text-black hover:border-black/25 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            <ChevronLeft className="w-3.5 h-3.5" />
          </button>
          <button
            type="button"
            onClick={handleNext}
            disabled={page >= totalPages}
            title="Next page"
            className="p-1 rounded border border-border bg-white text-gray-600 hover:text-black hover:border-black/25 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            <ChevronRight className="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className={`flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 mt-2 border-t border-border text-xs ${className}`}>
      {/* Item Range / Status */}
      <div className="flex items-center gap-2 text-gray-500">
        {totalItems !== undefined && startItem !== null && endItem !== null ? (
          <span>
            Showing <strong className="font-semibold text-[#030712]">{startItem}–{endItem}</strong> of{' '}
            <strong className="font-semibold text-[#030712]">{totalItems}</strong> entries
          </span>
        ) : (
          <span>
            Page <strong className="font-semibold text-[#030712]">{page}</strong> of{' '}
            <strong className="font-semibold text-[#030712]">{Math.max(1, totalPages)}</strong>
          </span>
        )}

        {/* Per-page selector if enabled */}
        {onPageSizeChange && pageSize && (
          <div className="hidden sm:flex items-center gap-1.5 ml-3 pl-3 border-l border-border text-gray-500">
            <span>Show</span>
            <select
              value={pageSize}
              onChange={(e) => onPageSizeChange(Number(e.target.value))}
              className="bg-white border border-border rounded px-2 py-0.5 text-xs text-[#030712] focus:outline-none focus:border-black"
            >
              {pageSizeOptions.map((opt) => (
                <option key={opt} value={opt}>
                  {opt}
                </option>
              ))}
            </select>
            <span>per page</span>
          </div>
        )}
      </div>

      {/* Prev / Page / Next Controls */}
      <div className="flex items-center gap-1.5">
        <button
          type="button"
          onClick={handlePrev}
          disabled={page <= 1}
          className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-700 bg-white border border-border rounded-lg hover:border-black/25 hover:text-black disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronLeft className="w-3.5 h-3.5" />
          <span>Previous</span>
        </button>

        <span className="px-2 py-1 text-[11px] font-semibold text-gray-600 bg-surface-muted border border-border rounded-lg min-w-[32px] text-center">
          {page} / {Math.max(1, totalPages)}
        </span>

        <button
          type="button"
          onClick={handleNext}
          disabled={page >= totalPages}
          className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-700 bg-white border border-border rounded-lg hover:border-black/25 hover:text-black disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          <span>Next</span>
          <ChevronRight className="w-3.5 h-3.5" />
        </button>
      </div>
    </div>
  );
});
