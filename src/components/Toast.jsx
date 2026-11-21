import React, { createContext, useContext, useState, useCallback } from 'react';
import { CheckCircle2, AlertCircle, Info, X } from 'lucide-react';

const ToastContext = createContext(null);

export const useToast = () => {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return context;
};

export const ToastProvider = ({ children }) => {
  const [toasts, setToasts] = useState([]);

  const addToast = useCallback((message, type = 'info') => {
    const id = Math.random().toString(36).substring(2, 9);
    setToasts((prev) => [...prev, { id, message, type }]);

    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
    }, 3500);
  }, []);

  const removeToast = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  return (
    <ToastContext.Provider value={{ showToast: addToast }}>
      {children}
      {/* Strict Bottom-Center Anchor with unique IDs/classes */}
      <div
        id="wpchat-toast-container"
        className="wpchat-toast-wrapper fixed bottom-6 left-1/2 -translate-x-1/2 z-[999999] flex flex-col items-center gap-2 pointer-events-none max-w-[420px] w-full px-4"
      >
        {toasts.map((toast) => (
          <div
            key={toast.id}
            id={`wpchat-toast-${toast.id}`}
            className="wpchat-toast-item pointer-events-auto flex items-center justify-between gap-3 bg-white text-[#030712] border border-border hover:border-black/25 rounded-xl px-4 py-2.5 animate-in fade-in slide-in-from-bottom-2 duration-150 transition-colors shadow-none w-full"
            style={{ maxWidth: '420px' }}
          >
            <div className="flex items-center gap-2.5 min-w-0">
              {toast.type === 'success' && (
                <CheckCircle2 className="wpchat-toast-icon w-4 h-4 text-[#10b981] flex-shrink-0" />
              )}
              {toast.type === 'error' && (
                <AlertCircle className="wpchat-toast-icon w-4 h-4 text-[#ef4444] flex-shrink-0" />
              )}
              {toast.type === 'info' && (
                <Info className="wpchat-toast-icon w-4 h-4 text-gray-500 flex-shrink-0" />
              )}
              <span className="wpchat-toast-text text-[12px] font-medium leading-snug truncate">
                {toast.message}
              </span>
            </div>
            <button
              type="button"
              id={`wpchat-toast-close-${toast.id}`}
              onClick={() => removeToast(toast.id)}
              className="wpchat-toast-close text-gray-400 hover:text-gray-600 border border-transparent hover:border-black/25 active:border-black/25 transition-colors duration-150 p-0.5 rounded"
              aria-label="Dismiss toast"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
};
