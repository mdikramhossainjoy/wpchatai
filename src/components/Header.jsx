import React, { useState, useRef, useEffect } from 'react';
import { Plus, Settings, X, History, Trash2, Code2, RefreshCw, Download } from 'lucide-react';
import { Logo } from './Logo';
import { Pagination } from './Pagination';

export const Header = ({
  onNewChat,
  onOpenSettings,
  onOpenCodeStudio,
  onClose,
  sessions = [],
  activeSessionId,
  onSelectSession,
  onDeleteSession,
  hasApiKey,
  config,
  siteContext,
  onRefreshContext,
  isRefreshingContext,
  onExportChat,
}) => {
  const [historyOpen, setHistoryOpen] = useState(false);
  const [historyPage, setHistoryPage] = useState(1);
  const historyRef = useRef(null);

  const SESSIONS_PER_PAGE = 7;
  const totalHistoryPages = Math.ceil((sessions?.length || 0) / SESSIONS_PER_PAGE);
  const pagedSessions = (sessions || []).slice(
    (historyPage - 1) * SESSIONS_PER_PAGE,
    historyPage * SESSIONS_PER_PAGE
  );

  useEffect(() => {
    if (!historyOpen) return;
    const handleClickOutside = (e) => {
      if (historyRef.current && !historyRef.current.contains(e.target)) {
        setHistoryOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [historyOpen]);

  const i18n = config?.i18n || {};

  return (
    <header className="h-14 border-b border-border px-3 sm:px-4 flex items-center justify-between bg-white relative flex-shrink-0">
      <div className="flex items-center gap-2 min-w-0">
        {/* Title + Logo + Status indicator */}
        <div className="flex items-center gap-2 flex-shrink-0">
          <Logo variant="adaptive" size="sm" className="w-4 h-4" alt="WPChat" />
          <span className="font-semibold text-xs tracking-tight text-[#030712]">
            WPChat
          </span>
          <div className="flex items-center justify-center">
            {hasApiKey ? (
              <span className="w-1.5 h-1.5 rounded-full bg-[#10b981]" title="Connected" />
            ) : (
              <span className="w-1.5 h-1.5 rounded-full bg-gray-300" title="API Key Required" />
            )}
          </div>
        </div>
      </div>

      <div className="flex items-center gap-1 flex-shrink-0">
        {/* Live AJAX Sync Button */}
        <button
          type="button"
          id="wpchat-header-sync-btn"
          onClick={onRefreshContext}
          title="Refresh site data"
          className="wpchat-header-btn p-1.5 text-gray-600 hover:text-[#030712] hover:bg-surface-muted border border-transparent hover:border-black/25 active:border-black/25 rounded-lg transition-colors duration-150"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${isRefreshingContext ? 'animate-spin' : ''}`} />
        </button>

        {/* New Session Button */}
        <button
          type="button"
          id="wpchat-header-new-chat-btn"
          onClick={onNewChat}
          title={i18n.newChat || 'New Chat'}
          className="wpchat-header-btn p-1.5 text-gray-600 hover:text-[#030712] hover:bg-surface-muted border border-transparent hover:border-black/25 active:border-black/25 rounded-lg transition-colors duration-150"
        >
          <Plus className="w-4 h-4" />
        </button>

        {/* Chat History Dropdown */}
        <div className="relative" ref={historyRef}>
          <button
            type="button"
            id="wpchat-header-history-btn"
            onClick={() => setHistoryOpen(!historyOpen)}
            title={i18n.recentChats || 'Past Chats'}
            className="wpchat-header-btn p-1.5 text-gray-600 hover:text-[#030712] hover:bg-surface-muted border border-transparent hover:border-black/25 active:border-black/25 rounded-lg transition-colors duration-150"
          >
            <History className="w-4 h-4" />
          </button>

          {historyOpen && (
            <div className="absolute right-0 mt-2 w-64 rounded-xl bg-white border border-border z-50 animate-in fade-in zoom-in-95 duration-100 p-1.5 max-h-72 overflow-y-auto wpchat-scroll">
              <div className="px-2 py-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                {i18n.recentChats || 'Past Chats'}
              </div>
              {sessions.length === 0 ? (
                <div className="px-2 py-3 text-center text-xs text-gray-400">
                  {i18n.noPreviousChats || 'No chats yet'}
                </div>
              ) : (
                <>
                  <div className="space-y-0.5">
                    {pagedSessions.map((s) => (
                      <div
                        key={s.id}
                        className={`flex items-center justify-between group px-2 py-1.5 rounded-lg text-xs transition-colors duration-150 ${
                          Number(s.id) === Number(activeSessionId)
                            ? 'bg-surface-muted text-[#030712] font-medium'
                            : 'text-gray-600 hover:bg-gray-50'
                        }`}
                      >
                        <button
                          type="button"
                          onClick={() => {
                            onSelectSession(s.id);
                            setHistoryOpen(false);
                          }}
                          className="text-left flex-1 truncate mr-2"
                        >
                          {s.title || 'Chat'}
                        </button>
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            onDeleteSession(s.id);
                          }}
                          className="opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-red-500 rounded transition-opacity duration-150"
                          title="Delete chat"
                        >
                          <Trash2 className="w-3 h-3" />
                        </button>
                      </div>
                    ))}
                  </div>

                  {totalHistoryPages > 1 && (
                    <Pagination
                      compact
                      page={historyPage}
                      totalPages={totalHistoryPages}
                      totalItems={sessions.length}
                      pageSize={SESSIONS_PER_PAGE}
                      onPageChange={setHistoryPage}
                      className="mt-1 pt-1 border-t border-border"
                    />
                  )}
                </>
              )}
            </div>
          )}
        </div>

        {/* Code Studio Button */}
        <button
          type="button"
          id="wpchat-header-code-studio-btn"
          onClick={onOpenCodeStudio}
          title={i18n.codeStudio || 'Code Studio'}
          className="wpchat-header-btn p-1.5 text-gray-600 hover:text-[#030712] hover:bg-surface-muted border border-transparent hover:border-black/25 active:border-black/25 rounded-lg transition-colors duration-150"
        >
          <Code2 className="w-4 h-4" />
        </button>

        {/* Export Chat Button */}
        {onExportChat && (
          <button
            type="button"
            id="wpchat-header-export-btn"
            onClick={onExportChat}
            title="Export Chat"
            className="wpchat-header-btn p-1.5 text-gray-600 hover:text-[#030712] hover:bg-surface-muted border border-transparent hover:border-black/25 active:border-black/25 rounded-lg transition-colors duration-150"
          >
            <Download className="w-4 h-4" />
          </button>
        )}

        {/* Settings Button */}
        <button
          type="button"
          id="wpchat-header-settings-btn"
          onClick={onOpenSettings}
          title={i18n.settings || 'Settings'}
          className="wpchat-header-btn p-1.5 text-gray-600 hover:text-[#030712] hover:bg-surface-muted border border-transparent hover:border-black/25 active:border-black/25 rounded-lg transition-colors duration-150"
        >
          <Settings className="w-4 h-4" />
        </button>

        {/* Close Button */}
        <button
          type="button"
          id="wpchat-header-close-btn"
          onClick={onClose}
          title={i18n.closeDrawer || 'Close (Esc)'}
          className="wpchat-header-btn p-1.5 text-gray-600 hover:text-[#030712] hover:bg-surface-muted border border-transparent hover:border-black/25 active:border-black/25 rounded-lg transition-colors duration-150 ml-1"
        >
          <X className="w-4 h-4" />
        </button>
      </div>
    </header>
  );
};
