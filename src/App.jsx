import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Header } from './components/Header';
import { MessageItem } from './components/MessageItem';
import { ChatInput } from './components/ChatInput';
import { SettingsModal } from './components/SettingsModal';
import { CodeSnippetsModal } from './components/CodeSnippetsModal';
import { Launcher } from './components/Launcher';
import { TypingIndicator } from './components/TypingIndicator';
import { useToast } from './components/Toast';
import { Bot, Sparkles, Key } from 'lucide-react';
import { Logo } from './components/Logo';

export const App = ({ config }) => {
  const { showToast } = useToast();
  const [isOpen, setIsOpen] = useState(false);
  const [hasApiKey, setHasApiKey] = useState(config.hasApiKey);
  const [sessions, setSessions] = useState([]);
  const [activeSessionId, setActiveSessionId] = useState(null);
  const [messages, setMessages] = useState([]);
  const [currentMode, setCurrentMode] = useState(config.defaultMode || 'copilot');
  const [isLoading, setIsLoading] = useState(false);
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [codeStudioOpen, setCodeStudioOpen] = useState(false);
  const [isDraft, setIsDraft] = useState(false);
  const [siteContext, setSiteContext] = useState(null);
  const [isRefreshingContext, setIsRefreshingContext] = useState(false);

  const messagesEndRef = useRef(null);
  const hasLoadedInitial = useRef(false);
  const skipNextLoadSessionIdRef = useRef(null);
  const loadMessagesAbortRef = useRef(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  // Occlusion Guard: Close on Escape key & Lock body scroll
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && isOpen && !settingsOpen && !codeStudioOpen) {
        setIsOpen(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);

    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [isOpen, settingsOpen, codeStudioOpen]);

  // Load Sessions
  const loadSessions = useCallback(async (autoSelectFirst = false) => {
    try {
      const res = await fetch(`${config.restUrl}sessions`, {
        headers: { 'X-WP-Nonce': config.nonce },
      });
      if (res.ok) {
        const data = await res.json();
        const sessionList = data.sessions || [];
        setSessions(sessionList);

        // Only auto-select on initial page load if not in draft mode
        if (autoSelectFirst && !hasLoadedInitial.current) {
          hasLoadedInitial.current = true;
          if (sessionList.length > 0 && !isDraft) {
            setActiveSessionId(sessionList[0].id);
            setCurrentMode(sessionList[0].mode || 'copilot');
          }
        }
      }
    } catch {
      // Quiet fail on initial fetch
    }
  }, [config, isDraft]);

  // Live Site Context fetcher (100% AJAX without page refresh)
  const fetchSiteContext = useCallback(async (showNotification = false) => {
    setIsRefreshingContext(true);
    try {
      const res = await fetch(`${config.restUrl}context`, {
        headers: { 'X-WP-Nonce': config.nonce },
      });
      if (res.ok) {
        const data = await res.json();
        setSiteContext(data);
        if (showNotification) {
          const parts = [];
          if (data.site_name) parts.push(data.site_name);
          if (data.active_theme?.name) parts.push(`Theme: ${data.active_theme.name}`);
          if (data.wp_version) parts.push(`WP v${data.wp_version}`);
          if (data.plugins_active_count !== undefined) parts.push(`${data.plugins_active_count} plugins`);
          if (data.woocommerce?.active) parts.push(`WooCommerce (${data.woocommerce.total_products || 0} products)`);
          if (data.elementor?.active) parts.push(`Elementor (v${data.elementor.version || 'active'})`);

          showToast(
            parts.length > 0 ? `Site synced: ${parts.join(' • ')}` : 'Site data updated',
            'success'
          );
        }
      }
    } catch {
      if (showNotification) {
        showToast('Could not update site data', 'error');
      }
    } finally {
      setIsRefreshingContext(false);
    }
  }, [config, showToast]);

  // Load Messages for active session with race-condition guard
  const loadMessages = useCallback(async (sessionId) => {
    if (!sessionId) {
      setMessages([]);
      return;
    }
    if (loadMessagesAbortRef.current) {
      loadMessagesAbortRef.current.abort();
    }
    const controller = new AbortController();
    loadMessagesAbortRef.current = controller;

    try {
      const res = await fetch(`${config.restUrl}sessions/${sessionId}/messages`, {
        headers: { 'X-WP-Nonce': config.nonce },
        signal: controller.signal,
      });
      if (res.ok) {
        const data = await res.json();
        setMessages(data.messages || []);
      }
    } catch (err) {
      if (err.name !== 'AbortError') {
        showToast('Could not load chats', 'error');
      }
    }
  }, [config, showToast]);

  useEffect(() => {
    loadSessions(true);
    fetchSiteContext(false);
  }, [loadSessions, fetchSiteContext]);

  useEffect(() => {
    if (isOpen) {
      fetchSiteContext(false);
    }
  }, [isOpen, fetchSiteContext]);

  useEffect(() => {
    if (!activeSessionId) return;
    if (skipNextLoadSessionIdRef.current === activeSessionId) {
      skipNextLoadSessionIdRef.current = null;
      return;
    }
    loadMessages(activeSessionId);
  }, [activeSessionId, loadMessages]);

  // Handle Send Message
  const handleSend = async (text) => {
    if (!hasApiKey) {
      setSettingsOpen(true);
      showToast('Please set your API key first', 'info');
      return;
    }

    // Optimistically show user message
    const tempUserMsg = {
      id: Date.now(),
      role: 'user',
      content: text,
      created_at: new Date().toISOString(),
    };
    setMessages((prev) => [...prev, tempUserMsg]);
    setIsLoading(true);

    try {
      const res = await fetch(`${config.restUrl}chat`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify({
          session_id: activeSessionId,
          message: text,
          mode: currentMode,
        }),
      });

      const data = await res.json();

      if (data.session_id && data.session_id !== activeSessionId) {
        skipNextLoadSessionIdRef.current = data.session_id;
        setActiveSessionId(data.session_id);
        loadSessions(false);
      }

      if (data.messages) {
        setMessages(data.messages);
      }

      if (!res.ok || data.status === 'error') {
        if (data.needs_auth) {
          setSettingsOpen(true);
        }
        showToast(data.error || 'Chat failed', 'error');
        setMessages((prev) => prev.filter((m) => m.id !== tempUserMsg.id));
        return;
      }

      setIsDraft(false);

      // Check for dynamic site state changes (e.g., plugins activated, WooCommerce status)
      fetchSiteContext(false);
    } catch (err) {
      setMessages((prev) => prev.filter((m) => m.id !== tempUserMsg.id));
      showToast('Connection error. Please try again.', 'error');
    } finally {
      setIsLoading(false);
    }
  };

  // Handle New Chat (clean draft mode - never reverts to previous)
  const handleNewChat = () => {
    if (loadMessagesAbortRef.current) {
      loadMessagesAbortRef.current.abort();
    }
    setIsDraft(true);
    setActiveSessionId(null);
    setMessages([]);
    showToast('New chat started', 'info');
  };

  // Handle Select Session from History
  const handleSelectSession = (sessionId) => {
    setIsDraft(false);
    setActiveSessionId(sessionId);
    const selected = sessions.find((s) => Number(s.id) === Number(sessionId));
    if (selected && selected.mode) {
      setCurrentMode(selected.mode);
    }
  };

  // Export Chat to Markdown
  const handleExportChat = () => {
    if (!messages || messages.length === 0) {
      showToast('No messages to export', 'info');
      return;
    }
    const sessionObj = sessions.find((s) => Number(s.id) === Number(activeSessionId));
    const title = sessionObj?.title || 'WPChat';
    let md = `# ${title}\n\n`;
    md += `*Exported on ${new Date().toLocaleString()}*\n\n---\n\n`;

    messages.forEach((m) => {
      const role = m.role === 'user' ? 'User' : 'WPChat';
      md += `### ${role}\n\n${m.content}\n\n`;
      if (m.tool_call_data && m.tool_call_data.tool_name) {
        md += `> **Action:** \`${m.tool_call_data.tool_name}\`\n\n`;
      }
    });

    const blob = new Blob([md], { type: 'text/markdown;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `wpchat-transcript-${activeSessionId || 'export'}.md`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    showToast('Chat exported', 'success');
  };

  // Handle Approve Action
  const handleApproveAction = useCallback(async (token) => {
    try {
      const res = await fetch(`${config.restUrl}action/approve`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify({ token }),
      });

      const data = await res.json();
      if (res.ok && data.success !== false) {
        showToast('Done!', 'success');
        if (data.messages) {
          setMessages(data.messages);
        }
        // Live sync site context via AJAX (in case plugins/settings/theme changed)
        fetchSiteContext(false);
        return true;
      } else {
        showToast(data.error || 'Action failed', 'error');
        return false;
      }
    } catch {
      showToast('Could not approve', 'error');
      return false;
    }
  }, [config, showToast, fetchSiteContext]);

  // Handle Reject Action
  const handleRejectAction = useCallback(async (token) => {
    try {
      const res = await fetch(`${config.restUrl}action/reject`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify({ token }),
      });

      const data = await res.json();
      if (res.ok && data.success !== false) {
        showToast('Cancelled', 'info');
        if (data.messages) {
          setMessages(data.messages);
        }
        return true;
      } else {
        showToast(data.error || 'Could not cancel', 'error');
        return false;
      }
    } catch {
      showToast('Could not cancel', 'error');
      return false;
    }
  }, [config, showToast]);

  // Handle Undo (Turbo mode)
  const handleUndoAction = useCallback(async (auditId) => {
    try {
      const res = await fetch(`${config.restUrl}action/undo`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify({ audit_id: auditId }),
      });

      const data = await res.json();
      if (res.ok) {
        showToast(data.message || 'Undone!', 'success');
        if (activeSessionId) {
          loadMessages(activeSessionId);
        }
      } else {
        showToast(data.message || 'Could not undo', 'error');
      }
    } catch {
      showToast('Could not undo', 'error');
    }
  }, [config, showToast, activeSessionId, loadMessages]);

  // Handle Plugin / Theme Visual Card Actions (1-Click Install & Activate)
  const handlePluginThemeAction = useCallback(async (actionType, item) => {
    if (!item) return;
    const isTheme = item.type === 'theme';
    const slug = item.slug || item.stylesheet;

    if (actionType === 'install') {
      if (isTheme) {
        handleSend(`Install and activate theme ${slug}`);
      } else {
        handleSend(`Install and activate plugin ${slug}`);
      }
    } else if (actionType === 'activate') {
      if (isTheme) {
        handleSend(`Switch active theme to ${slug}`);
      } else {
        const file = item.plugin_file || slug;
        handleSend(`Activate plugin ${file}`);
      }
    } else if (actionType === 'switch_theme') {
      handleSend(`Switch active theme to ${slug}`);
    }
  }, [handleSend]);

  // Handle Delete Session
  const handleDeleteSession = async (id) => {
    try {
      const res = await fetch(`${config.restUrl}sessions/${id}`, {
        method: 'DELETE',
        headers: { 'X-WP-Nonce': config.nonce },
      });
      if (res.ok) {
        showToast('Conversation deleted', 'info');
        if (Number(activeSessionId) === Number(id)) {
          setActiveSessionId(null);
          setMessages([]);
        }
        loadSessions();
      }
    } catch {
      showToast('Failed to delete conversation', 'error');
    }
  };

  const i18n = config?.i18n || {};
  const siteName = config?.siteName || 'WordPress';
  const hasWoo = siteContext?.woocommerce?.active;

  // Quick Starter Prompts (dynamic & WooCommerce-aware via AJAX)
  const starterPrompts = hasWoo
    ? [
        'Create coupon SAVE15 with 15% discount',
        'Show store sales and pending orders',
        'Scan media and fill missing alt text',
        'Audit admin accounts and security',
      ]
    : [
        'Scan media and fill missing alt text',
        i18n.promptRedirect || `Redirect logged-out users on ${siteName} checkout`,
        i18n.promptCss || 'Make navigation header sticky with CSS',
        'Audit admin accounts and security',
      ];

  return (
    <>
      {/* Floating Launcher Button */}
      <Launcher
        onClick={() => setIsOpen(true)}
        isOpen={isOpen}
        hasApiKey={hasApiKey}
      />

      {/* Backdrop overlay */}
      {isOpen && (
        <div
          onClick={() => setIsOpen(false)}
          className="fixed inset-0 wpchat-drawer-backdrop z-[99999] transition-opacity duration-300"
          aria-hidden="true"
        />
      )}

      {/* Drawer Container with Occlusion Guard & Full Responsive Adaptation */}
      <div
        id="wpchat-drawer-container"
        className={`fixed top-0 right-0 h-[100dvh] w-full sm:w-[460px] max-w-full sm:max-w-[95vw] bg-white border-l border-border z-[100000] flex flex-col transition-all duration-300 ${
          isOpen
            ? 'visible opacity-100 pointer-events-auto translate-x-0'
            : 'invisible opacity-0 pointer-events-none translate-x-full delay-300'
        }`}
      >
        {/* Header */}
        <Header
          onNewChat={handleNewChat}
          onOpenSettings={() => setSettingsOpen(true)}
          onOpenCodeStudio={() => setCodeStudioOpen(true)}
          onClose={() => setIsOpen(false)}
          sessions={sessions}
          activeSessionId={activeSessionId}
          onSelectSession={handleSelectSession}
          onDeleteSession={handleDeleteSession}
          hasApiKey={hasApiKey}
          config={config}
          siteContext={siteContext}
          onRefreshContext={() => fetchSiteContext(true)}
          isRefreshingContext={isRefreshingContext}
          onExportChat={handleExportChat}
        />

        {/* Message Thread Area */}
        <div className="flex-1 overflow-y-auto p-4 wpchat-scroll bg-white">
          {!hasApiKey ? (
            <div className="h-full flex flex-col items-center justify-center text-center p-6 text-gray-500">
              <Logo variant="avatar" size="lg" className="w-10 h-10 mb-3" alt="WPChat" />
              <h4 className="text-xs font-semibold text-[#030712] mb-1">
                {i18n.apiKeyRequired || 'API Key Required'}
              </h4>
              <p className="text-[11px] text-gray-500 max-w-[260px] mb-4 leading-relaxed">
                {i18n.apiKeyDesc || 'Configure an AI provider to activate WPChat autonomous copilot.'}
              </p>
              <button
                type="button"
                onClick={() => setSettingsOpen(true)}
                className="px-3.5 py-1.5 text-xs font-medium text-white bg-[#030712] hover:bg-black rounded-lg transition-colors duration-150"
              >
                {i18n.enterApiKey || 'Enter API Key'}
              </button>
            </div>
          ) : messages.length === 0 ? (
            <div className="h-full flex flex-col items-center justify-center text-center p-4">
              <Logo variant="avatar" size="lg" className="w-10 h-10 mb-3" alt="WPChat" />
              <h3 className="text-xs font-semibold text-[#030712] mb-1">
                {i18n.emptyTitle || `How can I help ${siteName}?`}
              </h3>
              <p className="text-[11px] text-gray-500 max-w-[280px] mb-6 leading-relaxed">
                {i18n.emptySubtitle || `AI copilot for ${siteName}. Ask questions or request changes.`}
              </p>

              {/* Zero Rainbow Badge Rule: Clean neutral chips */}
              <div className="w-full space-y-1.5">
                <div className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider text-left px-1 mb-1">
                  {i18n.suggestedActions || 'Suggested Actions'}
                </div>
                {starterPrompts.map((prompt, idx) => (
                  <button
                    key={idx}
                    type="button"
                    onClick={() => handleSend(prompt)}
                    className="w-full text-left text-xs text-gray-700 bg-surface-muted hover:bg-surface-secondary border border-border rounded-xl px-3 py-2 transition-colors duration-150 leading-snug"
                  >
                    {prompt}
                  </button>
                ))}
              </div>
            </div>
          ) : (
            <div className="space-y-1">
              {messages.map((msg, index) => (
                <MessageItem
                  key={msg.id || index}
                  message={msg}
                  onApprove={handleApproveAction}
                  onReject={handleRejectAction}
                  onUndo={handleUndoAction}
                  onAction={handlePluginThemeAction}
                />
              ))}
              {isLoading && <TypingIndicator />}
              <div ref={messagesEndRef} />
            </div>
          )}
        </div>

        {/* Input Form with Approval Mode Selector adjacent to Send Button */}
        <ChatInput
          onSend={handleSend}
          isLoading={isLoading}
          currentMode={currentMode}
          onModeChange={setCurrentMode}
          config={config}
          siteContext={siteContext}
        />
      </div>

      {/* Settings Modal */}
      <SettingsModal
        isOpen={settingsOpen}
        onClose={() => setSettingsOpen(false)}
        config={config}
        onSettingsSaved={(newConfig) => {
          if (newConfig.has_api_key) setHasApiKey(true);
          if (newConfig.approval_mode) setCurrentMode(newConfig.approval_mode);
        }}
      />

      {/* Code Studio Modal */}
      <CodeSnippetsModal
        isOpen={codeStudioOpen}
        onClose={() => setCodeStudioOpen(false)}
        config={config}
      />
    </>
  );
};
