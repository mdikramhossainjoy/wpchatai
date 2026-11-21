# WPChat Design System Specification

## Philosophy
Human-crafted, quiet, tactile software. Modeled after the craftsmanship of Linear, Figma, and GitHub. Never looks like an automated template or generic AI generator.

---

## 1. Zero "AI-Built" Signals
1. **No Gradients as Decoration**: Never use purple-to-pink, cyan, or multi-stop gradients on cards, buttons, or backgrounds.
2. **No Rainbow Badge Clutter**: Never scatter green/yellow/blue/purple pastel pills. Badges are neutral (`#f9fafb`, border `rgba(0,0,0,0.08)`).
3. **No Fluffy AI Copy**: Never write conversational filler ("As an AI assistant...", "I'd be delighted to help you with that!"). Use active verbs and direct human phrases: "Run safe actions directly", "Admins only", "Done!".
4. **No Heavy Fuzzy Drop-Shadows**: All shadows set to `none`. Depth is achieved via crisp 1px borders and surface color shifts.
5. **No Tracked-Out Uppercase Eyebrows**: Avoid `ALL-CAPS EYEBROWS` above headings. Use clean sentence case.

---

## 2. Palette & Tokens
| Token | Value | Purpose |
| :--- | :--- | :--- |
| `surface.DEFAULT` | `#ffffff` | Primary card and modal background |
| `surface.muted` | `#f9fafb` | Table header, subtle chip, and hover background |
| `surface.secondary` | `#f3f4f6` | Secondary input fill and active item |
| `text.primary` | `#030712` | Headings, primary text, dark action buttons |
| `text.muted` | `#4b5563` / `#6b7280` | Subtitles, helper text, timestamps |
| `border.DEFAULT` | `rgba(0, 0, 0, 0.08)` | Standard 1px structural border on all components |
| `border.hover` | `rgba(0, 0, 0, 0.25)` | Subtle 1px focus and hover border state |
| `accent.success` | `#10b981` | Approved state, status indicator dot |
| `accent.danger` | `#ef4444` | Reject / delete actions |

---

## 3. Typography
- **Font Stack**: `Google Sans`, `Google Sans Text`, `-apple-system`, `BlinkMacSystemFont`, `Segoe UI`, `Roboto`, `sans-serif`
- **Scale**:
  - `text-xs` (12px, line-height 1.6): Standard interface body, chat messages, input text.
  - `text-[11px]` / `text-[10px]`: Metadata, status pills, timestamps, keyboard shortcuts.
  - `text-sm` (14px): Main modal headings, drawer header title.
- **Weights**: Regular `400` for body, Medium `500` for inputs and buttons, Semibold `600` for headings and table headers.

---

## 4. Tables & Data Display
- **Container**: Wrapped in `.wpchat-table-wrapper` with `overflow-x: auto` and `max-width: 100%`.
- **Text Wrapping**: Strict `white-space: nowrap !important; word-break: keep-all;`. Columns never wrap or squash vertically.
- **Scrollbar**: Custom thin scrollbar (`height: 5px`, thumb `rgba(0, 0, 0, 0.18)`).
- **Cells**: Padding `0.45rem 0.75rem`, border `1px solid rgba(0, 0, 0, 0.08)`.

---

## 5. Micro-Interactions
- **Hover**: 1px border contrast shifts to `rgba(0, 0, 0, 0.25)`.
- **Active / Press**: `active:scale-[0.98]` for buttons.
- **Speed**: Snappy `100ms - 150ms` transitions. No floaty, delayed animations.
