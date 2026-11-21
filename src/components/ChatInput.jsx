import React, { useState, useRef, useEffect } from 'react';
import { ArrowUp, Loader2, Mic, MicOff, Sparkles, X, RefreshCw } from 'lucide-react';
import { ModeSelector } from './ModeSelector';
import { useToast } from './Toast';

const DEFAULT_FALLBACK_IDEAS = [
  { label: 'Clean Database', prompt: 'Clean all expired transients, post revisions, and optimize database tables.' },
  { label: 'Scan PHP Errors', prompt: 'Scan debug.log and report any recent fatal errors or critical warnings.' },
  { label: 'Fix Missing Alt Tags', prompt: 'Scan media library for images without alt tags and generate descriptive tags.' },
  { label: 'Audit Admins', prompt: 'Audit all administrator users and check file permissions for wp-config.php.' },
  { label: 'Create 15% Coupon', prompt: 'Create a 15% discount coupon named SAVE15 for all products.' },
  { label: 'Store Sales Summary', prompt: 'Show me recent order stats, total sales, and best-selling products.' },
];

export const ChatInput = ({ onSend, isLoading, currentMode, onModeChange, config, siteContext }) => {
  const { showToast } = useToast();
  const [text, setText] = useState('');
  const [isListening, setIsListening] = useState(false);
  const [recipesOpen, setRecipesOpen] = useState(false);
  const [dynamicIdeas, setDynamicIdeas] = useState([]);
  const [isLoadingIdeas, setIsLoadingIdeas] = useState(false);

  const textareaRef = useRef(null);
  const recognitionRef = useRef(null);
  const recipesRef = useRef(null);

  // Clean up speech recognition on unmount to prevent audio leaks
  useEffect(() => {
    return () => {
      if (recognitionRef.current) {
        try {
          recognitionRef.current.onstart = null;
          recognitionRef.current.onend = null;
          recognitionRef.current.onerror = null;
          recognitionRef.current.onresult = null;
          recognitionRef.current.abort();
        } catch {}
      }
    };
  }, []);

  // Reset textarea height when text cleared
  useEffect(() => {
    if (!text && textareaRef.current) {
      textareaRef.current.style.height = '36px';
      textareaRef.current.style.overflowY = 'hidden';
    }
  }, [text]);

  const adjustTextareaHeight = () => {
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
      const scrollH = textareaRef.current.scrollHeight;
      if (scrollH > 140) {
        textareaRef.current.style.height = '140px';
        textareaRef.current.style.overflowY = 'auto';
      } else {
        textareaRef.current.style.height = `${Math.max(scrollH, 36)}px`;
        textareaRef.current.style.overflowY = 'hidden';
      }
    }
  };

  const handleTextChange = (e) => {
    setText(e.target.value);
    adjustTextareaHeight();
  };

  useEffect(() => {
    if (!recipesOpen) return;
    const handleClickOutside = (e) => {
      if (recipesRef.current && !recipesRef.current.contains(e.target)) {
        setRecipesOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [recipesOpen]);

  const fetchDynamicIdeas = async (force = false) => {
    setIsLoadingIdeas(true);
    try {
      const url = `${config.restUrl}ideas${force ? '?force=1' : ''}`;
      const res = await fetch(url, {
        headers: { 'X-WP-Nonce': config.nonce },
      });
      if (res.ok) {
        const data = await res.json();
        if (data.ideas && data.ideas.length > 0) {
          setDynamicIdeas(data.ideas);
          return;
        }
      }
      setDynamicIdeas(DEFAULT_FALLBACK_IDEAS);
    } catch {
      setDynamicIdeas(DEFAULT_FALLBACK_IDEAS);
    } finally {
      setIsLoadingIdeas(false);
    }
  };

  const handleToggleIdeas = () => {
    const next = !recipesOpen;
    setRecipesOpen(next);
    if (next && dynamicIdeas.length === 0) {
      fetchDynamicIdeas(false);
    }
  };

  const toggleVoice = () => {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
      showToast('Voice dictation not supported in this browser. Try Chrome or Edge.', 'info');
      return;
    }

    if (isListening) {
      if (recognitionRef.current) {
        try {
          recognitionRef.current.stop();
        } catch {}
      }
      setIsListening(false);
      return;
    }

    try {
      const recognition = new SpeechRecognition();
      recognition.continuous = false;
      recognition.interimResults = false;
      recognition.lang = navigator.language || 'en-US';

      recognition.onstart = () => setIsListening(true);
      recognition.onend = () => setIsListening(false);
      recognition.onerror = () => setIsListening(false);
      recognition.onresult = (event) => {
        const transcript = event.results[0]?.[0]?.transcript;
        if (transcript) {
          setText((prev) => (prev ? `${prev} ${transcript}` : transcript));
        }
      };

      recognitionRef.current = recognition;
      recognition.start();
    } catch {
      setIsListening(false);
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const trimmed = text.trim();
    if (!trimmed || isLoading) return;
    onSend(trimmed);
    setText('');
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
      textareaRef.current.style.overflowY = 'hidden';
    }
  };

  const handleKeyDown = (e) => {
    if (e.isComposing || e.nativeEvent?.isComposing) return;
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(e);
    }
  };

  const handleSelectIdea = (prompt) => {
    setText(prompt);
    setRecipesOpen(false);
    if (textareaRef.current) {
      textareaRef.current.focus();
      setTimeout(adjustTextareaHeight, 0);
    }
  };

  const placeholderText =
    config?.i18n?.placeholder ||
    `Ask WPChat to build or edit ${config?.siteName || 'anything'}...`;

  const ideasList = dynamicIdeas.length > 0 ? dynamicIdeas : DEFAULT_FALLBACK_IDEAS;

  return (
    <div id="wpchat-chat-input-wrapper" className="border-t border-border bg-white p-3 flex-shrink-0 relative">
      {/* Dynamic AI Ideas Popover */}
      {recipesOpen && (
        <div
          ref={recipesRef}
          className="absolute left-3 bottom-24 z-50 w-72 bg-white border border-border rounded-xl p-2.5 animate-in fade-in zoom-in-95 duration-100"
        >
          <div className="flex items-center justify-between px-1.5 py-1 pb-2 border-b border-border mb-1.5">
            <span className="text-[11px] font-semibold text-[#030712] flex items-center gap-1.5">
              <Sparkles className="w-3 h-3 text-gray-700" />
              <span>AI Ideas (Dynamic)</span>
            </span>

            <div className="flex items-center gap-1">
              <button
                type="button"
                onClick={() => fetchDynamicIdeas(true)}
                disabled={isLoadingIdeas}
                title="Ask AI for fresh ideas"
                className="p-1 text-gray-400 hover:text-black rounded transition-colors disabled:opacity-50"
              >
                <RefreshCw className={`w-3 h-3 ${isLoadingIdeas ? 'animate-spin' : ''}`} />
              </button>
              <button
                type="button"
                onClick={() => setRecipesOpen(false)}
                className="text-gray-400 hover:text-black p-1 rounded"
              >
                <X className="w-3 h-3" />
              </button>
            </div>
          </div>

          <div className="space-y-1 max-h-52 overflow-y-auto wpchat-scroll">
            {isLoadingIdeas ? (
              <div className="py-6 text-center text-xs text-gray-500 flex flex-col items-center justify-center gap-2">
                <Loader2 className="w-4 h-4 animate-spin text-gray-700" />
                <span>AI generating ideas...</span>
              </div>
            ) : (
              ideasList.map((recipe, idx) => (
                <button
                  key={idx}
                  type="button"
                  onClick={() => handleSelectIdea(recipe.prompt)}
                  className="w-full text-left p-2 rounded-lg hover:bg-surface-muted transition-colors text-xs text-[#030712] group flex flex-col"
                >
                  <span className="font-semibold text-[11px] text-gray-800 group-hover:text-black">
                    {recipe.label}
                  </span>
                  <span className="text-[10px] text-gray-400 truncate w-full mt-0.5">
                    {recipe.prompt}
                  </span>
                </button>
              ))
            )}
          </div>
        </div>
      )}

      {/* Primary Input Form Area */}
      <form
        id="wpchat-chat-form"
        onSubmit={handleSubmit}
        className="wpchat-chat-form-container border border-border hover:border-black/25 focus-within:border-black/25 rounded-xl bg-surface-muted focus-within:bg-white transition-colors duration-150 p-2.5"
      >
        <textarea
          id="wpchat-chat-textarea"
          ref={textareaRef}
          rows={1}
          value={text}
          disabled={isLoading}
          onChange={handleTextChange}
          onKeyDown={handleKeyDown}
          placeholder={placeholderText}
          className="wpchat-textarea wpchat-custom-textarea w-full resize-none overflow-hidden bg-transparent text-[#030712] placeholder-gray-400 text-xs px-1 py-0.5 focus:outline-none max-h-[140px] leading-relaxed"
        />

        {/* Form Controls Bottom Row (Mode + Dynamic Ideas on left; Mic + Enter + Send on right) */}
        <div className="flex items-center justify-between pt-2 mt-1 border-t border-border/50 gap-2">
          {/* Left Controls */}
          <div className="flex items-center gap-1.5 flex-wrap min-w-0">
            <ModeSelector
              currentMode={currentMode}
              onModeChange={onModeChange}
              dropUp={true}
            />

            {/* Dynamic Ideas Button */}
            <button
              type="button"
              id="wpchat-quick-ideas-btn"
              onClick={handleToggleIdeas}
              title="Dynamic AI Ideas"
              className="text-[10px] font-semibold text-gray-700 bg-white hover:bg-gray-50 border border-border hover:border-black/25 active:border-black/25 px-2 py-1 rounded flex items-center gap-1 select-none transition-colors"
            >
              <Sparkles className="w-2.5 h-2.5 text-gray-600" />
              <span>Ideas</span>
            </button>
          </div>

          {/* Right Controls */}
          <div className="flex items-center gap-1.5 flex-shrink-0">
            {/* Voice Dictation Button */}
            <button
              type="button"
              id="wpchat-voice-btn"
              onClick={toggleVoice}
              title={isListening ? 'Listening... click to stop' : 'Voice dictation'}
              className={`p-1.5 rounded-lg border transition-colors ${
                isListening
                  ? 'bg-red-50 text-red-600 border-red-300 animate-pulse'
                  : 'bg-white text-gray-500 hover:text-black border-border hover:border-black/25 active:border-black/25 hover:bg-gray-50'
              }`}
            >
              {isListening ? <Mic className="w-3.5 h-3.5" /> : <MicOff className="w-3.5 h-3.5" />}
            </button>

            <span className="hidden sm:inline text-[10px] text-gray-400 select-none">
              {config?.i18n?.enterToSend || 'Enter'}
            </span>

            <button
              id="wpchat-send-btn"
              type="submit"
              disabled={!text.trim() || isLoading}
              className="wpchat-custom-btn wpchat-send-btn p-1.5 rounded-lg bg-[#030712] text-white hover:bg-black transition-colors duration-150 disabled:opacity-30 disabled:hover:bg-[#030712] flex-shrink-0"
              aria-label="Send message"
            >
              {isLoading ? (
                <Loader2 className="w-3.5 h-3.5 animate-spin" />
              ) : (
                <ArrowUp className="w-3.5 h-3.5" />
              )}
            </button>
          </div>
        </div>
      </form>

      {/* Status Pills: Strictly positioned BELOW the input area */}
      {/* Status Pills: Connected Site, WP Core & Plugins, Theme, Active Features */}
      <div id="wpchat-input-footer-status" className="flex items-center justify-between px-1 pt-2 text-[10px] text-gray-500 select-none">
        <div className="flex items-center gap-1.5 min-w-0 flex-wrap">
          {/* Connected Site Pill */}
          {config?.siteName && (
            <span
              id="wpchat-pill-site"
              title={`Site: ${config.siteName}`}
              className="text-gray-600 font-medium bg-surface-muted border border-border hover:border-black/25 px-2 py-0.5 rounded truncate max-w-[110px] transition-colors"
            >
              {config.siteName}
            </span>
          )}

          {/* Core & Plugins Count Pill */}
          {siteContext?.wp_version && (
            <span
              id="wpchat-pill-core"
              title={`WordPress ${siteContext.wp_version} with ${siteContext.plugins_active_count ?? 0} active plugins`}
              className="text-gray-700 font-medium bg-surface-muted border border-border hover:border-black/25 px-2 py-0.5 rounded flex items-center gap-1 transition-colors"
            >
              <span className="w-1.5 h-1.5 rounded-full bg-[#10b981]" />
              <span>WP {siteContext.wp_version} ({siteContext.plugins_active_count ?? 0} plugins)</span>
            </span>
          )}

          {/* Active Theme Pill */}
          {siteContext?.active_theme?.name && (
            <span
              id="wpchat-pill-theme"
              title={`Theme: ${siteContext.active_theme.name} v${siteContext.active_theme.version || '1.0'}`}
              className="text-gray-700 font-medium bg-surface-muted border border-border hover:border-black/25 px-2 py-0.5 rounded flex items-center gap-1 transition-colors"
            >
              <span className="w-1.5 h-1.5 rounded-full bg-[#10b981]" />
              <span className="truncate max-w-[120px]">{siteContext.active_theme.name}</span>
            </span>
          )}

          {/* Live WooCommerce / Builder / Core Status Pill */}
          {siteContext?.woocommerce?.active ? (
            <span
              id="wpchat-pill-woocommerce"
              title={`WooCommerce: ${siteContext.woocommerce.total_products || 0} products, ${siteContext.woocommerce.total_orders || 0} orders`}
              className="text-gray-800 font-medium bg-surface-muted border border-border hover:border-black/25 px-2 py-0.5 rounded flex items-center gap-1 transition-colors"
            >
              <span className="w-1.5 h-1.5 rounded-full bg-[#10b981]" />
              <span>WooCommerce Ready</span>
            </span>
          ) : siteContext?.elementor?.active ? (
            <span
              id="wpchat-pill-elementor"
              title={`Elementor Page Builder: ${siteContext.elementor.version || 'Active'}`}
              className="text-gray-800 font-medium bg-surface-muted border border-border hover:border-black/25 px-2 py-0.5 rounded flex items-center gap-1 transition-colors"
            >
              <span className="w-1.5 h-1.5 rounded-full bg-[#10b981]" />
              <span>Elementor Ready</span>
            </span>
          ) : siteContext ? (
            <span
              id="wpchat-pill-standard"
              title={`Standard WordPress Site: ${siteContext.counts?.posts || 0} posts, ${siteContext.counts?.pages || 0} pages`}
              className="text-gray-800 font-medium bg-surface-muted border border-border hover:border-black/25 px-2 py-0.5 rounded flex items-center gap-1 transition-colors"
            >
              <span className="w-1.5 h-1.5 rounded-full bg-[#10b981]" />
              <span>Standard Site Ready</span>
            </span>
          ) : null}
        </div>

        <span className="text-[10px] text-gray-400 font-medium flex-shrink-0">
          WPChat Copilot
        </span>
      </div>
    </div>
  );
};
