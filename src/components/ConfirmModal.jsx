import React, { useEffect } from 'react';
import { AlertTriangle, Loader2, X } from 'lucide-react';

/**
 * Reusable Confirmation Modal with clean low-opacity borders and Google Sans typography
 */
export const ConfirmModal = ({
  isOpen,
  title = 'Confirm Action',
  message = 'Are you sure you want to proceed?',
  confirmText = 'Confirm',
  cancelText = 'Cancel',
  variant = 'danger', // 'danger' | 'primary'
  isLoading = false,
  onConfirm,
  onCancel,
}) => {
  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && !isLoading) {
        onCancel();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, isLoading, onCancel]);

  if (!isOpen) return null;

  return (
    <div
      id="wpchat-confirm-modal-overlay"
      role="dialog"
      aria-modal="true"
      onClick={(e) => {
        if (e.target === e.currentTarget && !isLoading) onCancel();
      }}
      className="fixed inset-0 z-[100020] wpchat-modal-backdrop flex items-center justify-center p-4 animate-in fade-in duration-100 font-sans"
    >
      <div
        id="wpchat-confirm-modal-card"
        className="w-full max-w-sm bg-white border border-border rounded-xl p-5 space-y-4 animate-in zoom-in-95 duration-100"
      >
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <div
              className={`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${
                variant === 'danger'
                  ? 'bg-red-50 text-red-600 border border-red-200/50'
                  : 'bg-surface-muted text-gray-800 border border-border'
              }`}
            >
              <AlertTriangle className="w-4 h-4" />
            </div>
            <h3 className="text-xs font-semibold text-[#030712]">{title}</h3>
          </div>

          <button
            type="button"
            disabled={isLoading}
            onClick={onCancel}
            className="p-1 text-gray-400 hover:text-black rounded transition-colors disabled:opacity-50"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        <p className="text-xs text-gray-600 leading-relaxed">{message}</p>

        <div className="flex items-center justify-end gap-2 pt-2 border-t border-border">
          <button
            type="button"
            disabled={isLoading}
            onClick={onCancel}
            className="px-3.5 py-1.5 text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 border border-border hover:border-black/25 rounded-lg transition-colors disabled:opacity-50"
          >
            {cancelText}
          </button>

          <button
            type="button"
            disabled={isLoading}
            onClick={onConfirm}
            className={`inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold text-white rounded-lg transition-colors disabled:opacity-50 ${
              variant === 'danger'
                ? 'bg-red-600 hover:bg-red-700 border border-red-700/30'
                : 'bg-[#030712] hover:bg-black border border-black/15'
            }`}
          >
            {isLoading && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
            <span>{confirmText}</span>
          </button>
        </div>
      </div>
    </div>
  );
};
