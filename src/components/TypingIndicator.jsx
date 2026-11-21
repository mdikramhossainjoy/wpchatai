import React from 'react';
import { Logo } from './Logo';

export const TypingIndicator = () => {
  return (
    <div className="flex items-start gap-2.5 my-3 animate-in fade-in duration-200">
      <Logo variant="avatar" size="sm" className="w-6 h-6 mt-0.5" alt="WPChat thinking" />

      <div className="rounded-2xl rounded-tl-sm bg-white border border-border px-4 py-3 inline-flex items-center gap-1.5">
        <span className="w-1.5 h-1.5 rounded-full bg-gray-400 wpchat-typing-dot" />
        <span className="w-1.5 h-1.5 rounded-full bg-gray-400 wpchat-typing-dot" />
        <span className="w-1.5 h-1.5 rounded-full bg-gray-400 wpchat-typing-dot" />
      </div>
    </div>
  );
};
