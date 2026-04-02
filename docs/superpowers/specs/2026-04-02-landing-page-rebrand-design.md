# Landing Page Rebrand: Warm Charcoal & Gold

**Date**: 2026-04-02
**Status**: Approved
**Scope**: Rebrand `resources/views/welcome.blade.php` — CSS and markup changes only, no structural/content changes

## Motivation

The current gold/amber color scheme feels generic and doesn't convey the calm, trustworthy personality Flock needs. The rebrand draws directly from the dove logo's own visual language — warm gold on dark charcoal with a handcrafted, textured feel — and pairs it with serif typography to create a distinctive, non-AI-generated aesthetic.

## Constraints

- Keep the Flock dove logo as-is
- Keep all existing content sections and copy
- Keep existing JS behavior (counters, scroll animations, FAQ accordion)
- No CSS framework — continue with inline `<style>` block
- Must remain responsive at 1024px, 768px, and 480px breakpoints

---

## 1. Color Palette

| Role | Color | Hex |
|------|-------|-----|
| Dark primary (nav, hero, CTA, footer) | Charcoal | `#2B2B2B` |
| Dark secondary (cards on dark bg) | Soft charcoal | `#363636` |
| Dark footer | Darker charcoal | `#222222` |
| Light primary (content bg) | Warm off-white | `#FAF6F0` |
| Light secondary (alternating sections) | Cream | `#F3ECE0` |
| Accent | Warm gold | `#E8A838` |
| Accent hover | Deeper gold | `#D4952E` |
| Accent subtle (badges, highlights) | Light gold | `#FDF3E0` |
| Body text (on light) | Near-black | `#1F1F1F` |
| Secondary text (on light) | Warm gray | `#6B6560` |
| Body text (on dark) | Warm white | `#F0EBE3` |
| Muted text (on dark) | Warm gray | `#A39E96` |

## 2. Typography

- **Headings**: Playfair Display (serif), loaded from fonts.bunny.net, weights 600, 700, 800
- **Body**: Inter (keep current), weights 400, 500, 600
- **Accent labels** (badges, section tags): Inter, uppercase, 600 weight, letter-spacing 0.1em
- **Brand name "Flock"** in nav: Playfair Display

### Type Scale

| Element | Font | Size | Weight |
|---------|------|------|--------|
| Hero H1 | Playfair Display | 4rem (2.5rem mobile) | 800 |
| Section titles | Playfair Display | 2.5rem | 700 |
| Card titles | Playfair Display | 1.15rem | 600 |
| Stats numbers | Playfair Display | 3rem | 700 |
| Step numbers | Playfair Display | 2rem | 700 |
| Price amounts | Playfair Display | 2.5rem | 700 |
| FAQ questions | Playfair Display | 1.1rem | 600 |
| Body text | Inter | 1rem | 400 |
| Small labels | Inter | 0.8rem | 600 |

## 3. Texture & Feel

- Subtle paper-grain noise on light sections via CSS (pseudo-element with repeating radial gradient at ~3% opacity)
- No background gradients — flat, confident color blocks
- Cards: warm soft shadows (`0 2px 16px rgba(43,43,43,0.08)`), no borders
- Gold accent used sparingly: CTAs, active states, key highlights, divider lines
- Thin gold divider lines (`1px solid #E8A838` at ~20% opacity) between major sections

## 4. Component Design

### Navigation
- Dark charcoal background, semi-transparent (`rgba(43,43,43,0.95)`) when scrolled
- Logo + "Flock" in Playfair Display
- Nav links: warm white `#F0EBE3`, gold underline on hover (animated slide-in from left, 200ms)
- "Get Started" CTA: gold `#E8A838` background, charcoal `#2B2B2B` text, `border-radius: 2px`

### Hero Section
- Dark charcoal background
- Headline: Playfair Display, warm white, key phrase in flat gold `#E8A838` (no gradient)
- Subheading: Inter, warm gray `#A39E96`
- Badge: gold outline border, no filled background, static gold dot (no pulse animation)
- Primary CTA: gold bg, charcoal text | Secondary CTA: warm white outlined
- Dashboard mockup: gold accent bar on top, softer shadow (`0 8px 32px rgba(0,0,0,0.3)`)

### Feature Cards
- Section background: cream `#F3ECE0`
- Cards: warm off-white `#FAF6F0`, soft warm shadows, no borders, `border-radius: 4px`
- Icon: small gold circle with charcoal icon inside
- Title: Playfair Display | Body: Inter
- Hover: `translateY(-4px)`, shadow deepens, 200ms ease

### Stats Section
- Dark charcoal band
- Numbers: Playfair Display, 3rem, gold `#E8A838`
- Labels: Inter, warm gray `#A39E96`, uppercase, small

### How It Works
- Warm off-white background
- Step numbers: large Playfair Display numerals in gold
- Connected by thin gold horizontal line (vertical on mobile)
- Descriptions: Inter

### Pricing Section
- Cream background
- Cards: warm off-white, sharp corners (`border-radius: 4px`)
- Featured card: thin gold border all 4 sides, "Most Popular" badge gold bg with charcoal text
- Price numbers: Playfair Display

### FAQ Section
- Warm off-white background
- Questions: Playfair Display, weight 600
- Gold `+` toggle icon
- Thin gold bottom border on each item

### CTA Section
- Dark charcoal background
- Headline: Playfair Display, warm white
- Single centered gold CTA button
- No glow effects

### Footer
- Darker charcoal `#222222`
- Links: warm gray, gold on hover
- Thin gold line at top separating from content

## 5. Global Style Rules

- **Border radius**: buttons `2px`, cards `4px` (sharp, intentional, editorial)
- **Transitions**: 200ms ease on all hovers, no bouncy/spring animations
- **Shadows**: warm-toned (`rgba(43,43,43,0.08)`) not cool gray
- **Badge dot**: static gold (remove pulse animation)
- **Bar chart**: bars fade-up on scroll (0.4s ease), no bounce
- **Counter animation**: keep existing cubic easing logic

## 6. Responsive Behavior

### Breakpoints (unchanged)
- Desktop: 1024px+
- Tablet: 768px–1023px
- Mobile: <768px

### Key Adjustments
- Playfair Display headings scale: hero H1 4rem → 2.5rem on mobile
- Dashboard mockup: hidden on mobile (`display: none` below 768px)
- Pricing cards: stack vertically, featured card retains gold border
- Stats: 4-column → 2x2 grid (tablet) → vertical stack (mobile)
- "How It Works" connecting line: horizontal → vertical on mobile

## 7. Accessibility

| Combination | Ratio | WCAG |
|-------------|-------|------|
| Gold `#E8A838` on charcoal `#2B2B2B` | ~5.8:1 | AA large text |
| Near-black `#1F1F1F` on off-white `#FAF6F0` | ~15:1 | AAA |
| Warm white `#F0EBE3` on charcoal `#2B2B2B` | ~12:1 | AAA |
| Charcoal `#2B2B2B` on gold `#E8A838` (buttons) | ~5.8:1 | AA |

- Gold buttons use `#1F1F1F` text for readability
- Focus states: 2px gold outline ring with 2px offset
- All interactive elements: minimum 44px touch target on mobile

## 8. Performance

- Playfair Display loaded from fonts.bunny.net (weights 600, 700, 800 only)
- Paper texture: CSS-generated (no image download)
- No new JS — existing scroll/counter/accordion logic unchanged, timing adjustments only

## 9. Files Changed

- `resources/views/welcome.blade.php` — CSS overhaul + minor markup tweaks (font classes, adjusted structure where needed)
- No new files created
- Logo unchanged
