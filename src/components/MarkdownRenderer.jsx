import React, { useMemo } from 'react';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

// Configure marked with GFM options
marked.setOptions({
  gfm: true,
  breaks: true,
});

// Configure DOMPurify hook to enforce safe links (prevent reverse tabnabbing and window hijacking)
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
  if (node.tagName === 'A') {
    node.setAttribute('target', '_blank');
    node.setAttribute('rel', 'noopener noreferrer');
  }
});

/**
 * Client-side safeguard sanitization filter
 */
function sanitizeContent(raw) {
  if (typeof raw !== 'string') return '';
  let text = raw;
  // 1. Strip reasoning and thought tags
  text = text.replace(/<think\b[^>]*>[\s\S]*?<\/think>/gi, '');
  text = text.replace(/<thought\b[^>]*>[\s\S]*?<\/thought>/gi, '');
  text = text.replace(/<think\b[^>]*>[\s\S]*$/gi, '');
  text = text.replace(/<thought\b[^>]*>[\s\S]*$/gi, '');
  text = text.replace(/\[(?:thought|internal|system|debug)\][\s\S]*?\[\/(?:thought|internal|system|debug)\]/gi, '');
  // 2. Strip control tokens
  text = text.replace(/<\|(?:im_start|im_end|system|assistant|user|endoftext|padding)[^>]*\|>/gi, '');
  text = text.replace(/<\|[a-z0-9_ -]+\|>/gi, '');
  text = text.replace(/\[\/?(?:INST|SYS|OUT)\]/gi, '');
  // 3. Fix leaked template tokens
  text = text.replace(/!replycontentm\b/gi, "I'm");
  text = text.replace(/!replycontent[a-z0-9_]*/gi, '');
  text = text.replace(/\[replycontent[a-z0-9_]*\]/gi, '');
  // 4. Strip broken single-letter image tags
  text = text.replace(/!\[[^\]]*\]\([a-z0-9]{1,2}\)/gi, '');
  return text.trim();
}

export const MarkdownRenderer = ({ content }) => {
  const html = useMemo(() => {
    if (!content) return '';
    try {
      const sanitized = sanitizeContent(content);
      const parsed = marked.parse(sanitized);
      if (typeof parsed !== 'string') return '';
      // Wrap all tables in a horizontally scrollable container for strict text no-wrap
      const wrapped = parsed
        .replace(/<table(\s*[^>]*)>/gi, '<div class="wpchat-table-wrapper"><table$1>')
        .replace(/<\/table>/gi, '</table></div>');
      return DOMPurify.sanitize(wrapped, {
        USE_PROFILES: { html: true },
        ADD_TAGS: ['div', 'table', 'thead', 'tbody', 'tr', 'th', 'td'],
        ADD_ATTR: ['class', 'target', 'rel'],
        FORBID_TAGS: ['form', 'input', 'button', 'svg', 'math', 'style', 'script', 'iframe'],
      });
    } catch {
      return DOMPurify.sanitize(sanitizeContent(content), {
        USE_PROFILES: { html: true },
      });
    }
  }, [content]);

  return (
    <div
      className="wpchat-prose text-xs text-[#030712] leading-relaxed break-words"
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
};
