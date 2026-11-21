import React, { useState, memo } from 'react';
import { Wrench, RotateCcw, CheckCircle2, Loader2 } from 'lucide-react';
import { ActionCard } from './ActionCard';
import { MarkdownRenderer } from './MarkdownRenderer';
import { PluginThemeCard } from './PluginThemeCard';
import { Logo } from './Logo';

export const MessageItem = memo(({ message, onApprove, onReject, onUndo, onAction }) => {
  const [isUndoing, setIsUndoing] = useState(false);
  const isUser = message.role === 'user';
  const isTool = message.role === 'tool';
  const toolData = message.tool_call_data || {};

  const handleUndoClick = async (auditId) => {
    setIsUndoing(true);
    try {
      await onUndo(auditId);
    } finally {
      setIsUndoing(false);
    }
  };

  // Extract visual showcase items (WordPress plugins or themes)
  const showcaseItems =
    toolData.result?.results ||
    toolData.result?.plugins ||
    toolData.result?.themes ||
    (Array.isArray(toolData.result) ? toolData.result : null);

  const validShowcase = Array.isArray(showcaseItems)
    ? showcaseItems.filter(
        (it) =>
          it &&
          typeof it === 'object' &&
          (it.name || it.slug || it.stylesheet) &&
          (it.banner || it.icon || it.description || it.is_installed)
      )
    : [];

  // Tool message representation: Always rendered horizontally inline with the Bot Avatar on the SAME row
  if (isTool) {
    const isTurbo = toolData.is_turbo;
    const isRejected = toolData.rejected;
    const auditId = toolData.result?.audit_id;

    return (
      <div id={`wpchat-msg-tool-${message.id || 't'}`} className="my-2">
        <div className="flex items-center gap-2.5">
          <Logo variant="avatar" size="sm" className="w-6 h-6" alt="WPChat" />

          <div className="inline-flex items-center justify-between gap-3 px-3 py-1.5 rounded-xl bg-surface-muted border border-border text-xs max-w-[90%]">
            <div className="flex items-center gap-2 min-w-0">
              <Wrench className="w-3.5 h-3.5 text-gray-500 flex-shrink-0" aria-hidden="true" />
              <span className="text-[11px] text-gray-800 font-semibold truncate">
                {toolData.tool_name || 'Tool'}
              </span>
              {isRejected ? (
                <span className="text-[11px] text-gray-500 italic">Stopped</span>
              ) : (
                <span className="flex items-center gap-1 text-[11px] text-gray-600">
                  <CheckCircle2 className="w-3 h-3 text-[#10b981]" aria-hidden="true" />
                  <span>Done</span>
                </span>
              )}
            </div>

            {/* Turbo Mode Undo Tag & Button */}
            {isTurbo && auditId && (
              <div className="flex items-center gap-1.5 flex-shrink-0 ml-2">
                <span className="text-[10px] font-medium text-gray-500 bg-white border border-border px-1.5 py-0.5 rounded">
                  Turbo
                </span>
                <button
                  type="button"
                  id={`wpchat-undo-btn-${auditId}`}
                  disabled={isUndoing}
                  onClick={() => handleUndoClick(auditId)}
                  className="wpchat-custom-btn inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium text-gray-800 bg-white hover:bg-gray-50 border border-border rounded transition-colors duration-150 disabled:opacity-50"
                >
                  {isUndoing ? (
                    <Loader2 className="w-3 h-3 animate-spin" aria-hidden="true" />
                  ) : (
                    <RotateCcw className="w-3 h-3 text-gray-500" aria-hidden="true" />
                  )}
                  <span>Undo</span>
                </button>
              </div>
            )}
          </div>
        </div>

        {/* Visual Plugin & Theme Showcase Cards */}
        {validShowcase.length > 0 && (
          <div className="mt-2.5 ml-[34px] space-y-2.5 max-w-[95%]">
            {validShowcase.map((item, idx) => (
              <PluginThemeCard
                key={item.slug || item.stylesheet || item.file || idx}
                item={item}
                onAction={onAction}
              />
            ))}
          </div>
        )}
      </div>
    );
  }

  // User Message (Bubble Shape)
  if (isUser) {
    return (
      <div id={`wpchat-msg-user-${message.id || 'u'}`} className="flex items-start justify-end gap-2 my-2.5">
        <div className="max-w-[85%] bg-surface-muted border border-border text-[#030712] text-xs leading-relaxed rounded-2xl rounded-tr-sm px-4 py-2.5 whitespace-pre-wrap break-words">
          {message.content}
        </div>
      </div>
    );
  }

  // Model / Assistant Message (Bubble Shape with Bot Avatar)
  const hasCard = Boolean(toolData && toolData.preview);
  const hasShowcase = validShowcase.length > 0;

  // Do NOT render ghost avatar if assistant turn has neither text content nor a card nor showcase
  if (!message.content?.trim() && !hasCard && !hasShowcase) {
    return null;
  }

  return (
    <div id={`wpchat-msg-assistant-${message.id || 'a'}`} className="flex items-start gap-2.5 my-3">
      <Logo variant="avatar" size="sm" className="w-6 h-6 mt-0.5" alt="WPChat" />

      <div className="flex-1 min-w-0 max-w-[92%]">
        {/* Assistant Text in Bubble Shape */}
        {message.content && (
          <div className="rounded-2xl rounded-tl-sm bg-white border border-border px-4 py-3">
            <MarkdownRenderer content={message.content} />
          </div>
        )}

        {/* Action Card: Diff and status (buttons only when pending) */}
        {hasCard && (
          <div className="mt-2">
            <ActionCard
              data={toolData}
              onApprove={onApprove}
              onReject={onReject}
            />
          </div>
        )}

        {/* Visual Plugin & Theme Showcase Cards */}
        {hasShowcase && (
          <div className="mt-2.5 space-y-2.5">
            {validShowcase.map((item, idx) => (
              <PluginThemeCard
                key={item.slug || item.stylesheet || item.file || idx}
                item={item}
                onAction={onAction}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
});
