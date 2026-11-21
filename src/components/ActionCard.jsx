import React, { useState } from 'react';
import { AlertTriangle, Check, X, Loader2, CheckCircle2, XCircle } from 'lucide-react';

export const ActionCard = ({ data, onApprove, onReject }) => {
  const [loadingAction, setLoadingAction] = useState(null);
  const [localStatus, setLocalStatus] = useState(data.status || 'pending');

  const preview = data.preview || {};
  const diff = preview.diff || {};
  const toolName = data.tool_name || 'wp_tool';
  const token = data.token;

  const isResolved = localStatus === 'approved' || localStatus === 'rejected' || data.status === 'approved' || data.status === 'rejected' || data.resolved;
  const isApproved = localStatus === 'approved' || data.status === 'approved';
  const isRejected = localStatus === 'rejected' || data.status === 'rejected';

  const handleApprove = async () => {
    setLoadingAction('approve');
    try {
      const ok = await onApprove(token);
      if (ok === true) {
        setLocalStatus('approved');
      }
    } catch {
      // Re-enable if error
    } finally {
      setLoadingAction(null);
    }
  };

  const handleReject = async () => {
    setLoadingAction('reject');
    try {
      const ok = await onReject(token);
      if (ok === true) {
        setLocalStatus('rejected');
      }
    } catch {
      // Re-enable if error
    } finally {
      setLoadingAction(null);
    }
  };

  return (
    <div className="my-3 border border-border rounded-xl bg-white p-3.5">
      {/* Header */}
      <div className="flex items-center justify-between gap-2 mb-2.5">
        <div className="flex items-center gap-2">
          {isApproved ? (
            <CheckCircle2 className="w-4 h-4 text-[#10b981]" />
          ) : isRejected ? (
            <XCircle className="w-4 h-4 text-gray-400" />
          ) : (
            <AlertTriangle className="w-4 h-4 text-gray-700" />
          )}
          <span className="text-xs font-semibold text-[#030712]">
            {isApproved ? 'Approved' : isRejected ? 'Cancelled' : (preview.summary || 'Needs Approval')}
          </span>
        </div>
        {/* Zero Rainbow Badge: neutral */}
        <span className="px-2 py-0.5 text-[11px] font-medium text-gray-800 bg-surface-muted border border-border rounded-md">
          {toolName}
        </span>
      </div>

      {preview.target_name && (
        <div className="text-[12px] text-gray-600 mb-2">
          Target: <span className="font-medium text-[#030712]">{preview.target_name}</span>
        </div>
      )}

      {/* Diff Table */}
      {Object.keys(diff).length > 0 && (
        <div className="bg-surface-muted border border-border rounded-lg p-2.5 mb-3 text-xs">
          <div className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
            Changes
          </div>
          <div className="space-y-2">
            {Object.entries(diff).map(([key, val]) => {
              const isMultiline = key.toLowerCase().includes('code') || 
                (typeof val.after === 'string' && val.after.includes('\n')) ||
                (typeof val.after === 'string' && val.after.length > 80);

              if (isMultiline) {
                return (
                  <div key={key} className="text-[11px] pb-2 border-b border-border/50 last:border-b-0 space-y-1.5">
                    <div className="text-gray-500 font-medium">{key}</div>
                    {val.before && (
                      <div className="space-y-0.5">
                        <span className="text-[10px] text-red-600 font-semibold">Before:</span>
                        <pre className="text-[11px] font-mono bg-red-50/60 border border-red-200 rounded-md p-2 overflow-x-auto max-h-28 whitespace-pre-wrap text-red-900">
                          {val.before}
                        </pre>
                      </div>
                    )}
                    <div className="space-y-0.5">
                      {val.before && <span className="text-[10px] text-emerald-600 font-semibold">After:</span>}
                      <pre className="text-[11px] font-mono bg-emerald-50/60 border border-emerald-200 rounded-md p-2 overflow-x-auto max-h-36 whitespace-pre-wrap text-emerald-950">
                        {val.after}
                      </pre>
                    </div>
                  </div>
                );
              }

              return (
                <div key={key} className="flex flex-col sm:grid sm:grid-cols-5 gap-1 sm:gap-2 items-start sm:items-baseline text-[11px] pb-1.5 sm:pb-0 border-b sm:border-b-0 border-border/50 last:border-b-0">
                  <span className="sm:col-span-1 text-gray-500 font-medium truncate">{key}</span>
                  <span className="sm:col-span-2 text-gray-400 line-through truncate font-medium w-full">
                    {val.before || '—'}
                  </span>
                  <span className="sm:col-span-2 text-[#030712] font-semibold truncate w-full">
                    {val.after || '—'}
                  </span>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Action Status / Buttons: Hide buttons when approved, rejected, or running */}
      <div className="flex items-center justify-end gap-2 pt-1 border-t border-border">
        {loadingAction === 'approve' ? (
          <div className="inline-flex items-center gap-1.5 text-xs text-gray-600 font-medium py-1 px-2">
            <Loader2 className="w-3.5 h-3.5 animate-spin" />
            <span>Running...</span>
          </div>
        ) : loadingAction === 'reject' ? (
          <div className="inline-flex items-center gap-1.5 text-xs text-gray-400 font-medium py-1 px-2">
            <Loader2 className="w-3.5 h-3.5 animate-spin" />
            <span>Cancelling...</span>
          </div>
        ) : isApproved ? (
          <div className="inline-flex items-center gap-1.5 text-xs text-[#10b981] font-medium py-1 px-2">
            <CheckCircle2 className="w-3.5 h-3.5" />
            <span>Approved</span>
          </div>
        ) : isRejected ? (
          <div className="inline-flex items-center gap-1.5 text-xs text-gray-500 font-medium py-1 px-2">
            <XCircle className="w-3.5 h-3.5" />
            <span>Cancelled</span>
          </div>
        ) : (
          <>
            <button
              type="button"
              id={`wpchat-action-reject-${token || 'btn'}`}
              disabled={loadingAction !== null}
              onClick={handleReject}
              className="wpchat-custom-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-800 bg-white hover:bg-gray-50 border border-border hover:border-black/25 active:border-black/25 rounded-xl transition-colors duration-150 disabled:opacity-50"
            >
              <X className="w-3.5 h-3.5" />
              <span>Cancel</span>
            </button>

            <button
              type="button"
              id={`wpchat-action-approve-${token || 'btn'}`}
              disabled={loadingAction !== null}
              onClick={handleApprove}
              className="wpchat-custom-btn inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-medium text-white bg-[#030712] hover:bg-black border border-transparent hover:border-white/35 active:border-white/35 rounded-xl transition-colors duration-150 disabled:opacity-50"
            >
              <Check className="w-3.5 h-3.5" />
              <span>Approve</span>
            </button>
          </>
        )}
      </div>
    </div>
  );
};
