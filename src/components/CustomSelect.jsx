import React, { useState, useRef, useEffect } from 'react';
import { ChevronDown, Check } from 'lucide-react';

export const CustomSelect = ({
  id,
  options = [],
  value,
  onChange,
  placeholder = 'Select an option',
  disabled = false,
  className = '',
}) => {
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

  const selectedOption = options.find((opt) => opt.id === value || opt.value === value);

  return (
    <div
      id={`wpchat-select-wrapper-${id}`}
      ref={containerRef}
      className={`wpchat-nice-select-container relative select-none text-xs ${className}`}
    >
      {/* Trigger Button - jQuery Nice Select Style */}
      <button
        id={`wpchat-select-trigger-${id}`}
        type="button"
        disabled={disabled}
        onClick={() => !disabled && setIsOpen(!isOpen)}
        className={`wpchat-nice-select w-full flex items-center justify-between px-3 py-2 bg-surface-muted hover:bg-white border border-border hover:border-black/25 rounded-lg text-[#030712] transition-all duration-150 focus:outline-none focus:border-black/25 ${
          isOpen ? 'border-black/25 bg-white' : ''
        } ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}
      >
        <span className="wpchat-nice-select-current truncate font-medium">
          {selectedOption ? selectedOption.name || selectedOption.label : placeholder}
        </span>
        <ChevronDown
          className={`wpchat-nice-select-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-150 flex-shrink-0 ml-2 ${
            isOpen ? 'rotate-180 text-gray-700' : ''
          }`}
        />
      </button>

      {/* Dropdown Options List */}
      {isOpen && (
        <div
          id={`wpchat-select-dropdown-${id}`}
          className="wpchat-nice-select-dropdown absolute left-0 right-0 mt-1 bg-white border border-border rounded-xl z-50 py-1 max-h-56 overflow-y-auto wpchat-scroll animate-in fade-in zoom-in-95 duration-100"
        >
          {options.length === 0 ? (
            <div className="px-3 py-2 text-center text-gray-400 text-xs">
              No options available
            </div>
          ) : (
            options.map((opt) => {
              const optId = opt.id || opt.value;
              const isSelected = optId === value;
              return (
                <button
                  key={optId}
                  id={`wpchat-option-${id}-${optId}`}
                  type="button"
                  onClick={() => {
                    onChange(optId);
                    setIsOpen(false);
                  }}
                  className={`wpchat-nice-select-option w-full text-left px-3 py-2 flex items-center justify-between text-xs transition-colors duration-150 ${
                    isSelected
                      ? 'bg-surface-muted text-[#030712] font-semibold'
                      : 'text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  <div className="min-w-0 pr-2">
                    <div className="truncate font-medium">{opt.name || opt.label}</div>
                    {opt.description && (
                      <div className="text-[10px] text-gray-400 truncate mt-0.5">
                        {opt.description}
                      </div>
                    )}
                  </div>
                  {isSelected && (
                    <Check className="w-3.5 h-3.5 text-[#030712] flex-shrink-0" />
                  )}
                </button>
              );
            })
          )}
        </div>
      )}
    </div>
  );
};
