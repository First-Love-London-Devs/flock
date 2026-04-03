# Landing Page Engagement Fixes

**Date**: 2026-04-03
**Status**: Approved
**Scope**: Content additions and rewrites in `resources/views/welcome.blade.php`

## Motivation

The landing page design is polished but lacks social proof, storytelling, and objection-handling. Visitors have no reason to trust the product — no testimonials, no origin story, no competitor context, and inflated stats with no backing. These fixes address the engagement gap without changing the visual design system.

## Constraints

- Follow the existing Warm Charcoal & Gold design system (Playfair Display headings, charcoal/cream/gold palette, 2-4px border radius, no gradients)
- All new sections use inline CSS in the existing `<style>` block
- No new JS required
- Testimonials and founder story use realistic placeholder content marked with HTML comments for easy replacement
- No external images required

---

## 1. Remove Stats Section

Delete the entire `.stats` section and its CSS. The counter animation JS can stay (harmless) or be removed.

## 2. Add Testimonials Section

**Position**: After Features, before Hierarchy
**Background**: Charcoal `#2B2B2B`
**Layout**: 3 cards in a row, single column on mobile

Each card:
- Large gold opening quote mark (decorative, Playfair Display)
- Quote text in warm white `#F0EBE3`, Inter 0.95rem
- Divider: thin gold line
- Name in Playfair Display, white, weight 600
- Role + Church name in `#A39E96`, Inter

Sample testimonials (marked with `<!-- PLACEHOLDER: Replace with real testimonial -->` comments):

**Card 1 — Senior Pastor, large church:**
> "Before Flock, our attendance data was scattered across WhatsApp groups and paper registers. Now every leader submits in seconds, and I can see the whole church from my phone. It's transformed how we track growth."
— Pastor James Adeyemi, Grace Assembly, London

**Card 2 — Cell Leader, mid-size church:**
> "I used to spend my Monday evenings chasing members for attendance numbers. With Flock, I mark attendance during the meeting and it's done. My district pastor sees it instantly."
— Sarah Okonkwo, Cell Leader, Covenant House, Birmingham

**Card 3 — Church Administrator, small church:**
> "We tried spreadsheets, we tried other tools — nothing fit our structure. Flock understood that we organise by zones and districts, not departments. Setup took us about 20 minutes."
— David Mensah, Admin, New Life Chapel, Manchester

Card styling:
- Background: `#363636` (soft charcoal)
- Border-radius: 4px
- Padding: 2rem
- Box shadow: `0 2px 16px rgba(0,0,0,0.15)`
- Hover: subtle lift (`translateY(-4px)`)

## 3. Add "Why We Built Flock" Section

**Position**: After How It Works, before the new Comparison section
**Background**: Warm off-white `#FAF6F0`
**Layout**: Two columns — story left, beliefs right

**Left column — Story (marked as placeholder):**

Heading: "Built because churches deserve better tools"

Body (2-3 paragraphs):
> We watched churches drown in spreadsheets. Attendance tracked on paper, then typed into Excel, then shared over WhatsApp — with numbers getting lost at every step. Leaders spent hours on admin that should have taken minutes.
>
> We saw district pastors who couldn't tell you their group's attendance trend without digging through months of messages. Zone overseers making decisions based on gut feeling instead of data. Church admins buried under manual reports every week.
>
> So we built Flock — a platform that understands how churches actually work. Not a generic CRM with a church label slapped on. A tool built around zones, districts, and cells from day one, because that's how real churches organise.

**Right column — Beliefs (3-4 statements):**
Each belief: gold left border (3px), padding-left, Playfair Display heading, short Inter body text.

1. "Church admin should take minutes, not hours" — Leaders should lead, not wrestle spreadsheets.
2. "Every leader deserves real-time visibility" — From cell leaders to senior pastors, everyone should see what matters to them.
3. "Your structure, your rules" — Zones, districts, cells — or whatever you call them. Flock adapts to you.

## 4. Add "Why Flock" Comparison Section

**Position**: After Why We Built, before Mobile App
**Background**: Cream `#F3ECE0`

### Part A — vs Spreadsheets

Heading: "Still using spreadsheets?"
Subheading: "Here's what you're missing."

Two-column comparison table:

| | Spreadsheets | Flock |
|---|---|---|
| Mobile attendance submission | No | Yes |
| Real-time dashboards | No | Yes |
| Role-based access | No | Yes |
| Automatic reports | No | Yes |
| Member profiles & history | Manual | Built-in |
| Setup time | Hours of formatting | 20 minutes |

Styling: Clean table with gold checkmarks for Flock, muted X marks for Spreadsheets. Warm off-white card background.

### Part B — vs Other Church Tools

Heading: "How Flock compares"

| Feature | Other Tools | Flock |
|---|---|---|
| Cell/zone/district hierarchy | Limited or none | Built-in, unlimited depth |
| UK-focused | Mostly US-based | Designed for UK churches |
| Mobile-first attendance | Add-on or web only | Native mobile app |
| Pricing | $50-100+/month | Free tier + $29/month |
| Setup complexity | Days to weeks | Minutes |

Same table styling. Keep tone factual, not aggressive.

## 5. Rewrite FAQ Section

Replace existing 6 questions with 7 objection-handling questions:

1. **"How long does it take to get set up?"**
   Most churches are up and running within 20 minutes. Create your account, define your group structure, and start adding members. No technical setup, no consultants, no waiting.

2. **"Will my non-tech-savvy leaders actually use this?"**
   Flock's mobile app is designed for simplicity. Leaders open the app, tap their group, mark who's present, and submit. If they can use WhatsApp, they can use Flock.

3. **"What happens to our data if we cancel?"**
   Your data belongs to you. Export everything — members, attendance records, group structures — at any time. We never hold your data hostage.

4. **"How is Flock different from a spreadsheet?"**
   Spreadsheets don't send reminders, generate dashboards, work on mobile, or let leaders submit attendance from their phone. Flock does all of that out of the box, with zero formatting.

5. **"Is our church data secure?"**
   (Keep existing answer — it's good.) Every church gets its own isolated database with encrypted data in transit, secure authentication, and role-based access controls. Leaders can only see data relevant to their assigned groups and roles.

6. **"Can we customise the group structure?"**
   (Keep existing answer.) Flock lets you define your own hierarchy — whether that's Zones, Districts, and Cells, or something entirely different. You can have as many levels as you need.

7. **"What support do you offer?"**
   Email support with fast response times, plus in-app help guides. We're a small team that actually reads every message — no ticket queues, no bots.

## 6. Remove False "24/7 Support" Claims

- The stats section (which contained "24/7 Support Available") is already being removed
- Check footer and any other references — replace with "Dedicated Support" or remove

## 7. Final Section Order

1. Nav
2. Hero (unchanged)
3. ~~Stats~~ (removed)
4. Features (unchanged)
5. **Testimonials** (new) — charcoal bg
6. Hierarchy (unchanged)
7. How It Works (unchanged)
8. **Why We Built Flock** (new) — off-white bg
9. **Why Flock Comparison** (new) — cream bg
10. Mobile App (unchanged)
11. Pricing (unchanged)
12. FAQ (rewritten)
13. CTA (unchanged)
14. Footer (unchanged, minus any 24/7 claims)

## Files Changed

- `resources/views/welcome.blade.php` — section additions, FAQ rewrite, stats removal, new CSS
