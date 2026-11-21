import React from 'react';
import { Logo } from './Logo';

export const Launcher = ({ onClick, isOpen, hasApiKey }) => {
  if (isOpen) return null;

  return (
    <button
      type="button"
      onClick={onClick}
      className="fixed bottom-6 right-6 z-[99998] flex items-center gap-2.5 px-4 py-2.5 bg-[#030712] text-white rounded-full hover:bg-black transition-all duration-150 hover:scale-105 active:scale-95 group border border-white/20 hover:border-white/35 active:border-white/35"
      aria-label="Open WPChat Assistant"
    >
      <div className="relative flex items-center justify-center">
        <Logo variant="light" size="sm" className="w-4 h-4" alt="WPChat" />
        {hasApiKey && (
          <span className="absolute -top-1 -right-1 w-1.5 h-1.5 rounded-full bg-[#10b981]" />
        )}
      </div>
      <span className="text-xs font-semibold tracking-tight">
        WPChat
      </span>
      <span className="text-[10px] text-gray-400 font-medium bg-gray-900 border border-gray-700 px-1.5 py-0.5 rounded-full">
        AI
      </span>
    </button>
  );
};
