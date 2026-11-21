import React from 'react';
import { Check } from 'lucide-react';

/**
 * Custom Radio Component with unique WPChat classes and IDs
 */
export const CustomRadio = ({ id, name, value, checked, onChange, label, description }) => {
  return (
    <label
      id={`wpchat-radio-label-${id}`}
      htmlFor={`wpchat-radio-input-${id}`}
      className={`wpchat-radio-item flex items-start gap-2.5 p-2.5 rounded-xl border transition-all duration-150 cursor-pointer ${
        checked
          ? 'bg-surface-muted border-black/30'
          : 'bg-white border-border hover:bg-gray-50 hover:border-black/25'
      }`}
    >
      <input
        type="radio"
        id={`wpchat-radio-input-${id}`}
        name={name}
        value={value}
        checked={checked}
        onChange={() => onChange(value)}
        className="wpchat-radio-hidden sr-only"
      />
      {/* Custom styled circle indicator */}
      <span
        id={`wpchat-radio-circle-${id}`}
        className={`wpchat-radio-circle w-4 h-4 rounded-full border flex items-center justify-center flex-shrink-0 mt-0.5 transition-colors duration-150 ${
          checked ? 'border-black bg-[#030712]' : 'border-border bg-white hover:border-black/25'
        }`}
      >
        {checked && <span className="wpchat-radio-dot w-1.5 h-1.5 rounded-full bg-white" />}
      </span>

      <div className="flex-1 min-w-0">
        <span className="wpchat-radio-title block text-xs font-medium text-[#030712]">
          {label}
        </span>
        {description && (
          <span className="wpchat-radio-desc block text-[11px] text-gray-500 mt-0.5 leading-snug">
            {description}
          </span>
        )}
      </div>
    </label>
  );
};

/**
 * Custom Checkbox Component with unique WPChat classes and IDs
 */
export const CustomCheckbox = ({ id, checked, onChange, label, description, disabled = false }) => {
  return (
    <label
      id={`wpchat-checkbox-label-${id}`}
      htmlFor={`wpchat-checkbox-input-${id}`}
      className={`wpchat-checkbox-item flex items-start gap-2.5 cursor-pointer select-none ${
        disabled ? 'opacity-50 cursor-not-allowed' : ''
      }`}
    >
      <input
        type="checkbox"
        id={`wpchat-checkbox-input-${id}`}
        checked={checked}
        disabled={disabled}
        onChange={(e) => onChange(e.target.checked)}
        className="wpchat-checkbox-hidden sr-only"
      />
      {/* Custom styled square box */}
      <span
        id={`wpchat-checkbox-box-${id}`}
        className={`wpchat-checkbox-box w-4 h-4 rounded-md border flex items-center justify-center flex-shrink-0 mt-0.5 transition-colors duration-150 ${
          checked ? 'border-black bg-[#030712] text-white' : 'border-border bg-white hover:border-black/25'
        }`}
      >
        {checked && <Check className="w-3 h-3 stroke-[2.5]" />}
      </span>

      <div className="flex-1 min-w-0">
        <span className="wpchat-checkbox-title block text-xs font-medium text-[#030712]">
          {label}
        </span>
        {description && (
          <span className="wpchat-checkbox-desc block text-[11px] text-gray-500 mt-0.5 leading-snug">
            {description}
          </span>
        )}
      </div>
    </label>
  );
};
