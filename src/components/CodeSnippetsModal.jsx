import React, { useState, useEffect } from 'react';
import { X, Trash2, Power, AlertCircle, Copy, Check } from 'lucide-react';
import { Logo } from './Logo';
import { Pagination } from './Pagination';
import { useToast } from './Toast';
import { CustomCheckbox } from './CustomControls';
import { ConfirmModal } from './ConfirmModal';

export const CodeSnippetsModal = ({ isOpen, onClose, config }) => {
  const { showToast } = useToast();
  const [snippets, setSnippets] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [copiedId, setCopiedId] = useState(null);
  const [onlyActive, setOnlyActive] = useState(false);
  const [snippetPage, setSnippetPage] = useState(1);
  const SNIPPETS_PER_PAGE = 4;
  const [confirmModal, setConfirmModal] = useState({
    isOpen: false,
    title: '',
    message: '',
    confirmText: 'Delete Snippet',
    variant: 'danger',
    isLoading: false,
    onConfirm: null,
  });

  const fetchSnippets = () => {
    setIsLoading(true);
    fetch(`${config.restUrl}snippets`, {
      headers: { 'X-WP-Nonce': config.nonce },
    })
      .then((res) => res.json())
      .then((data) => setSnippets(data.snippets || []))
      .catch(() => showToast('Failed to load code snippets', 'error'))
      .finally(() => setIsLoading(false));
  };

  useEffect(() => {
    if (isOpen) {
      fetchSnippets();
    }
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  const handleToggle = async (snippetId, currentActive) => {
    try {
      const res = await fetch(`${config.restUrl}snippets/toggle`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify({ snippet_id: snippetId, active: !currentActive }),
      });
      if (res.ok) {
        showToast(`Snippet ${!currentActive ? 'activated' : 'deactivated'}`, 'success');
        setSnippets((prev) =>
          prev.map((s) => (s.id === snippetId ? { ...s, is_active: !currentActive } : s))
        );
      }
    } catch {
      showToast('Failed to toggle snippet', 'error');
    }
  };

  const handleDelete = (snippetId) => {
    setConfirmModal({
      isOpen: true,
      title: 'Delete Code Snippet',
      message: 'Delete snippet permanently?',
      confirmText: 'Delete Snippet',
      variant: 'danger',
      isLoading: false,
      onConfirm: async () => {
        try {
          const res = await fetch(`${config.restUrl}snippets/${snippetId}`, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': config.nonce },
          });
          if (res.ok) {
            showToast('Snippet deleted', 'info');
            setSnippets((prev) => prev.filter((s) => s.id !== snippetId));
          }
        } catch {
          showToast('Failed to delete snippet', 'error');
        } finally {
          setConfirmModal((prev) => ({ ...prev, isOpen: false }));
        }
      },
    });
  };

  const handleCopy = (id, code) => {
    navigator.clipboard.writeText(code);
    setCopiedId(id);
    showToast('Code copied to clipboard', 'info');
    setTimeout(() => setCopiedId(null), 2000);
  };

  return (
    <div
      role="dialog"
      aria-modal="true"
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
      className="fixed inset-0 z-[100010] flex items-center justify-center p-3 sm:p-4 wpchat-modal-backdrop animate-in fade-in duration-150"
    >
      <div className="bg-white border border-border rounded-xl w-full max-w-xl max-h-[90vh] sm:max-h-[85vh] flex flex-col p-4 sm:p-5 animate-in zoom-in-95 duration-100">
        {/* Header */}
        <div className="flex items-center justify-between pb-3 border-b border-border mb-3 flex-shrink-0">
          <div className="flex items-center gap-2">
            <Logo variant="dark" size="sm" className="w-5 h-5" alt="WPChat" />
            <h3 className="font-semibold text-xs text-[#030712]">
              WPChat Code Studio
            </h3>
            <span className="text-[11px] font-medium text-gray-500 bg-surface-muted border border-border px-1.5 py-0.5 rounded">
              {snippets.length} Snippets
            </span>
          </div>

          <div className="flex items-center gap-3">
            <CustomCheckbox
              id="filter-only-active"
              checked={onlyActive}
              onChange={setOnlyActive}
              label="Active only"
            />
            <button
              id="wpchat-snippets-close-btn"
              type="button"
              onClick={onClose}
              className="p-1 text-gray-400 hover:text-gray-600 transition-colors duration-150 rounded"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>

        {/* Content list */}
        <div className="flex-1 overflow-y-auto space-y-3 pr-1 wpchat-scroll">
          {isLoading ? (
            <div className="text-center py-8 text-xs text-gray-400">Loading snippets...</div>
          ) : snippets.length === 0 ? (
            <div className="text-center py-8 text-xs text-gray-400">
              No snippets yet. Ask WPChat to write PHP, CSS, or scripts.
            </div>
          ) : (
            (() => {
              const filtered = snippets.filter((s) => (!onlyActive ? true : s.is_active));
              const totalPages = Math.ceil(filtered.length / SNIPPETS_PER_PAGE);
              const paged = filtered.slice(
                (snippetPage - 1) * SNIPPETS_PER_PAGE,
                snippetPage * SNIPPETS_PER_PAGE
              );

              if (paged.length === 0) {
                return (
                  <div className="text-center py-8 text-xs text-gray-400">
                    No active snippets found.
                  </div>
                );
              }

              return (
                <>
                  {paged.map((snippet) => (
                    <div
                      key={snippet.id}
                      className="p-3 border border-border rounded-xl bg-surface-muted/60 hover:bg-surface-muted transition-colors duration-150 text-xs"
                    >
                      <div className="flex items-center justify-between gap-2 mb-2">
                        <div className="flex items-center gap-2 min-w-0">
                          <span className="font-semibold text-[#030712] truncate">
                            {snippet.title}
                          </span>
                          {/* Zero Rainbow Badge Rule */}
                          <span className="px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-800 bg-white border border-border rounded">
                            {snippet.code_type}
                          </span>
                        </div>

                        <div className="flex items-center gap-1.5 flex-shrink-0">
                          <button
                            type="button"
                            onClick={() => handleCopy(snippet.id, snippet.code_content)}
                            className="p-1 text-gray-400 hover:text-gray-600 transition-colors duration-150 rounded"
                            title="Copy code"
                          >
                            {copiedId === snippet.id ? (
                              <Check className="w-3.5 h-3.5 text-emerald-600" />
                            ) : (
                              <Copy className="w-3.5 h-3.5" />
                            )}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleToggle(snippet.id, snippet.is_active)}
                            className={`p-1 rounded transition-colors duration-150 ${
                              snippet.is_active
                                ? 'text-[#10b981] hover:bg-emerald-50'
                                : 'text-gray-400 hover:bg-gray-100'
                            }`}
                            title={snippet.is_active ? 'Deactivate snippet' : 'Activate snippet'}
                          >
                            <Power className="w-3.5 h-3.5" />
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDelete(snippet.id)}
                            className="p-1 text-gray-400 hover:text-red-500 transition-colors duration-150 rounded"
                            title="Delete snippet"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </div>

                      {snippet.error_log && (
                        <div className="flex items-center gap-1.5 p-2 mb-2 bg-red-50 border border-red-200 rounded-lg text-[11px] text-red-700">
                          <AlertCircle className="w-3.5 h-3.5 flex-shrink-0" />
                          <span className="truncate">{snippet.error_log}</span>
                        </div>
                      )}

                      <div className="relative">
                        <pre className="text-[11px] font-mono bg-white border border-border rounded-lg p-2.5 overflow-x-auto text-gray-800 max-h-32">
                          <code>{snippet.code_content}</code>
                        </pre>
                      </div>
                    </div>
                  ))}

                  {totalPages > 1 && (
                    <Pagination
                      compact
                      page={snippetPage}
                      totalPages={totalPages}
                      totalItems={filtered.length}
                      pageSize={SNIPPETS_PER_PAGE}
                      onPageChange={setSnippetPage}
                      className="pt-2"
                    />
                  )}
                </>
              );
            })()
          )}
        </div>

        {/* Footer */}
        <div className="pt-3 border-t border-border mt-3 flex items-center justify-between text-[11px] text-gray-500 flex-shrink-0">
          <span>Crash-safe sandbox</span>
          <button
            onClick={onClose}
            className="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 border border-border rounded-lg transition-colors duration-150"
          >
            Done
          </button>
        </div>
      </div>

      <ConfirmModal
        isOpen={confirmModal.isOpen}
        title={confirmModal.title}
        message={confirmModal.message}
        confirmText={confirmModal.confirmText}
        variant={confirmModal.variant}
        isLoading={confirmModal.isLoading}
        onConfirm={confirmModal.onConfirm}
        onCancel={() => setConfirmModal((prev) => ({ ...prev, isOpen: false }))}
      />
    </div>
  );
};
