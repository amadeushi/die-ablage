---
name: Die ABLAGE / Die PARTEI
description: Shared research archive with a red and black press-office identity
colors:
  primary: "#b31229"
  primary-hover: "#8b0e20"
  ink: "#202022"
  muted: "#626265"
  ground: "#f4f4f2"
  white: "#ffffff"
  line: "#dededb"
  selected: "#f8e7e9"
  hover: "#eeeeeb"
  scrollbar: "#b6b6b2"
typography:
  display:
    fontFamily: "Barlow Condensed, sans-serif"
    fontSize: "52px"
    fontWeight: 700
    lineHeight: 0.98
    letterSpacing: "-.01em"
  body:
    fontFamily: "Barlow, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.5
  title:
    fontFamily: "Barlow, sans-serif"
    fontSize: "20px"
    fontWeight: 600
    lineHeight: 1.35
    letterSpacing: "-.012em"
  reading:
    fontFamily: "Georgia, serif"
    fontSize: "17px"
    lineHeight: 1.8
rounded:
  control: "4px"
  access: "2px"
  panel: "12px"
  dialog: "14px"
spacing:
  compact: "8px"
  standard: "16px"
  section: "24px"
  desktop-gutter: "40px"
  mobile-gutter: "20px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.white}"
    rounded: "{rounded.control}"
    padding: "11px 16px"
    height: "42px"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
  button-secondary:
    backgroundColor: "{colors.white}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "11px 16px"
  reader:
    backgroundColor: "{colors.white}"
    rounded: "{rounded.panel}"
---

# Design System: Die ABLAGE / Die PARTEI

## Overview

**Creative North Star: "The Party Press Office"**

Die ABLAGE is a shared research archive for a project of Die PARTEI. Its real wordmark, red and black hierarchy, condensed lettering and ruled lists make that affiliation visible. Conventional application controls keep capture, reading and access management clear. Humor belongs to the overview headline; operational labels remain literal.

**Key Characteristics:**
- Assertive condensed headlines.
- White reading surfaces and restrained paper gray.
- Dense source-led rows with explicit access labels.

## Colors

Deep red identifies primary actions, emphasis and active fields. Black carries headings, neutral gray carries metadata, and white separates reading and editing surfaces from the paper ground. Pale red is the selected field; neutral hover and scrollbar colors retain the same family. The CSS property `--blue` is a legacy name for the primary red. These are implementation colors, not a claim of an official PARTEI design manual.

## Typography

Barlow 400–700 and Barlow Condensed 600–800 are self-hosted from `/fonts/`. Display lettering uses Barlow Condensed 700, uppercase: 52px desktop, 47px at the compact breakpoint, 41px mobile. The Die ABLAGE identity uses 38px/700, reducing to 34px and 25px. List titles are Barlow 600 at 20px desktop and 18px mobile. Metadata and access labels are 10–14px. Reader titles use Barlow Condensed 600 at 34px; saved article text uses Georgia 17px/1.8 with a maximum 72ch measure.

**The Reading Rule.** Serif text belongs to saved reading content; the brand, headlines and operational interface use Barlow.

## Layout

Desktop uses a fixed 258px rail, 64px topbar and 40px main gutters. At 1150px the rail becomes 224px, gutters become 26px and the reader becomes an overlay. At 720px the rail becomes a top area: brand row, full-width new-clip action, horizontally scrolling collection navigation and secondary controls. Main gutters become 20px and the reader becomes full screen.

Clip rows group source, type, title, summary, collection and access state with rules between rows. An open desktop reader makes a two-column grid. Capture uses a 570px right sheet with a scrolling body and persistent footer. Sharing uses a centered dialog up to 490px wide.

## Elevation & Depth

Ordinary rows and navigation are flat. Borders and surface tone establish hierarchy. Capture sheet (`-12px 0 40px #2020221a`), sharing dialog (`0 20px 60px #20202233`), overlay reader (`0 12px 45px #20202226`) and toast (`0 6px 24px #20202233`) use soft directional shadows for temporary depth.

## Shapes

Most controls use 4px corners; access labels use 2px corners. Reading panels use 12px and the sharing dialog 14px. Avatars remain circular. Clip marks are compact vertical rectangles with consistent Lucide SVG icons.

## Components

Primary buttons use red with white text, 600 weight and a darker hover; secondary buttons use white with a thin neutral border and neutral hover. Disabled controls use .45 opacity. Keyboard focus uses a 3px red outline with 3px offset.

Search uses a white 46px field, 4px corners and a thin neutral border, reducing to 42px mobile. Field carets are red. Selected navigation uses pale red with darker red text; mobile collections scroll horizontally. List rows use neutral hover and pale red selection. Private and shared access labels use explicit text, compact borders and small SVG icons; shared labels use red text and pale red fields.

Reader content separates source, saved article and notes. Capture and sharing controls retain close actions, Escape handling and focus loops. The capture sheet enters over 250ms using cubic-bezier(.16,1,.3,1); rows transition background over 150ms. Reduced-motion settings disable animations and transitions. Text selection uses #f6c8cf with #621220 foreground; scrollbars use the neutral palette.

## Do's and Don'ts

### Do:
- Do preserve the real PARTEI wordmark and the red/black typographic hierarchy.
- Do keep source metadata, access state and ordinary controls readable.
- Do keep keyboard focus visible and respect reduced motion.

### Don't:
- Don't replace ruled clip rows with decorative metric cards.
- Don't use shadows to decorate ordinary navigation or clip rows.
- Don't introduce another accent family into interaction states.

## Public shared reader

Shared single clips use one primary headline, source/access metadata and continuous serif text. Exact standalone title repetitions at the start of saved text are omitted from display; stored content remains intact. Archived HTML is available in a separate sandboxed saved-copy view rather than an embedded scrolling frame. On phones, article surfaces merge into the paper background with one 20px gutter; article titles use 30px Barlow Condensed. Collections keep a desktop side list and use a native compact clip selector on phones. Explicit clip changes move focus and scroll to the article heading; previous/next and return-to-selection actions follow the notes. Source links and reading controls have at least 44px hit height.

Messenger previews use server-generated title and short plain-text excerpt for valid secret links, plus a static 1200×630 PARTEI/ABLAGE PNG using the existing fonts, colors and retained slogan. Invalid or revoked links return neutral HTML with 404 and no content-specific metadata. Public reader responses are no-store and noindex; external preview caches remain outside the app's control.

## Accessible private workspace and bounded loading

Private readers use continuous saved text; original archive HTML remains in a separate sandboxed view. Below 1150px the reader is a modal with focus containment, Escape, inert background and scroll locking; desktop retains its nonmodal side panel. One shared overlay hook handles reader, capture and sharing with trigger-specific focus restoration, including nested share-to-reader transitions. Navigation exposes aria-current, clip rows aria-expanded, clip type buttons aria-pressed. Private interactive hit areas are at least 44px and mobile input text is 16px.

Library lists use authenticated, server-filtered pages of 40 metadata entries with 220-character summaries, global/collection counts and stable ordering. Full content and notes are requested only when a clip opens. Search covers full text and notes on the server; requests are debounced and obsolete responses discarded. The private workspace is a lazy-loaded chunk with a retry error boundary. Self-hosted WOFF2 files preserve the existing Barlow lettering. Exact palette values now have semantic tokens; redundant same-context CSS declarations are removed without changing the visual world.
