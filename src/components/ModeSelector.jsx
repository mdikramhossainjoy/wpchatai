import React, { useState, useRef, useEffect } from 'react';
import { ShieldAlert, Zap, Compass, ChevronDown, Check } from 'lucide-react';

const MODES = [
  {
    id: 'copilot',
    name: 'Copilot',
    desc: 'Auto-run if safe, ask on destructive',
    icon: Compass,
  },
  {
    id: 'always_ask',
    name: 'Always Ask',
    desc: 'Always ask before any change',
    icon: ShieldAlert,
  },
  {
    id: 'turbo',
    name: 'Turbo',
    desc: 'Run fast with 1-click undo',
    icon: Zap,
  },
];

export const ModeSelector = ({ currentMode, onModeChange, dropUp = false }) => {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef(null);

  useEffect(() => {
    if (!isOpen) return;
    const handleClickOutside = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen]);

  const active = MODES.find((m) => m.id === currentMode) || MODES[1];
  const ActiveIcon = active.icon;

  return (
    <div className="relative inline-block text-left" ref={containerRef}>
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-gray-800 bg-surface-muted hover:bg-surface-secondary border border-border hover:border-black/25 active:border-black/25 rounded-lg transition-colors duration-150"
      >
        <ActiveIcon className="w-3.5 h-3.5 text-gray-600" />
        <span>{active.name}</span>
        <ChevronDown className="w-3 h-3 text-gray-400" />
      </button>

      {isOpen && (
        <div
          className={`absolute left-0 ${
            dropUp ? 'bottom-full mb-1.5' : 'top-full mt-1.5'
          } w-60 rounded-xl bg-white border border-border z-50 animate-in fade-in zoom-in-95 duration-100 p-1`}
        >
          <div className="px-2 py-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
            Approval Mode
          </div>
          {MODES.map((mode) => {
            const Icon = mode.icon;
            const isSelected = mode.id === currentMode;
            return (
              <button
                key={mode.id}
                type="button"
                onClick={() => {
                  onModeChange(mode.id);
                  setIsOpen(false);
                }}
                className={`w-full text-left flex items-start gap-2.5 px-2.5 py-2 rounded-lg text-xs transition-colors duration-150 ${
                  isSelected ? 'bg-surface-muted text-[#030712]' : 'text-gray-700 hover:bg-gray-50'
                }`}
              >
                <Icon className="w-4 h-4 text-gray-600 mt-0.5 flex-shrink-0" />
                <div className="flex-1 min-w-0">
                  <div className="font-medium flex items-center justify-between">
                    <span>{mode.name}</span>
                    {isSelected && <Check className="w-3.5 h-3.5 text-[#030712]" />}
                  </div>
                  <div className="text-[11px] text-gray-500 mt-0.5 leading-snug">
                    {mode.desc}
                  </div>
                </div>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
};
