<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flock - Modern Church Management System</title>
    <meta name="description" content="Streamline attendance, connect leaders, and grow your church with Flock — the intelligent church management platform built for modern churches.">
    <link rel="icon" type="image/png" href="/images/flock-logo.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|playfair-display:600,700,800&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; color: #1F1F1F; background: #FAF6F0; line-height: 1.6; overflow-x: hidden; }
        a { color: inherit; text-decoration: none; }
        ul { list-style: none; }

        /* ── Utility ── */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.875rem 2rem; border-radius: 2px; font-weight: 600; font-size: 1rem; border: none; cursor: pointer; transition: all 0.2s ease; font-family: 'Inter', sans-serif; }
        .btn-primary { background: #E8A838; color: #2B2B2B; }
        .btn-primary:hover { background: #D4952E; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(232,168,56,0.25); }
        .btn-secondary { background: transparent; color: #F0EBE3; border: 1.5px solid #F0EBE3; }
        .btn-secondary:hover { border-color: #E8A838; color: #E8A838; }
        .btn-secondary-light { background: transparent; color: #2B2B2B; border: 1.5px solid #2B2B2B; }
        .btn-secondary-light:hover { border-color: #E8A838; color: #E8A838; }
        .btn-large { padding: 1rem 2.5rem; font-size: 1.1rem; }
        .section-label { display: inline-block; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #E8A838; border: 1px solid rgba(232,168,56,0.3); padding: 0.35rem 1rem; border-radius: 2px; margin-bottom: 1rem; font-family: 'Inter', sans-serif; }
        .section-title { font-size: 2.5rem; font-weight: 700; line-height: 1.15; margin-bottom: 1rem; font-family: 'Playfair Display', serif; }
        .section-desc { font-size: 1.1rem; color: #6B6560; max-width: 600px; line-height: 1.7; }
        .text-center { text-align: center; }
        .mx-auto { margin-left: auto; margin-right: auto; }
        .accent { color: #E8A838; }

        /* ── Nav ── */
        .nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: rgba(43,43,43,0.95); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(232,168,56,0.1); padding: 1rem 0; transition: all 0.2s ease; }
        .nav .container { display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { display: flex; align-items: center; gap: 0.6rem; font-size: 1.35rem; font-weight: 700; color: #F0EBE3; font-family: 'Playfair Display', serif; }
        .nav-brand img { width: 36px; height: 36px; object-fit: contain; }
        .nav-links { display: flex; align-items: center; gap: 2rem; }
        .nav-links a { font-size: 0.9rem; font-weight: 500; color: #A39E96; transition: color 0.2s ease; position: relative; }
        .nav-links a::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 0; height: 1.5px; background: #E8A838; transition: width 0.2s ease; }
        .nav-links a:hover { color: #F0EBE3; }
        .nav-links a:hover::after { width: 100%; }
        .nav-cta { display: flex; align-items: center; gap: 1rem; }
        .nav-cta .btn { padding: 0.6rem 1.5rem; font-size: 0.9rem; }
        .mobile-menu-btn { display: none; background: none; border: none; cursor: pointer; padding: 0.5rem; }
        .mobile-menu-btn span { display: block; width: 24px; height: 2px; background: #F0EBE3; margin: 5px 0; transition: all 0.2s ease; }

        /* ── Hero ── */
        .hero { padding: 10rem 0 6rem; background: #2B2B2B; position: relative; overflow: hidden; }
        .hero .container { position: relative; z-index: 1; }
        .hero-content { max-width: 750px; margin: 0 auto; text-align: center; }
        .hero-badge { display: inline-flex; align-items: center; gap: 0.5rem; border: 1px solid rgba(232,168,56,0.3); padding: 0.4rem 1rem 0.4rem 0.75rem; border-radius: 2px; font-size: 0.85rem; color: #A39E96; margin-bottom: 2rem; }
        .hero-badge-dot { width: 8px; height: 8px; background: #E8A838; border-radius: 50%; }
        .hero h1 { font-size: 4rem; font-weight: 800; line-height: 1.08; margin-bottom: 1.5rem; letter-spacing: -0.02em; color: #F0EBE3; font-family: 'Playfair Display', serif; }
        .hero p { font-size: 1.25rem; color: #A39E96; max-width: 560px; margin: 0 auto 2.5rem; line-height: 1.7; }
        .hero-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .hero-visual { margin-top: 4rem; position: relative; }
        .hero-visual-mockup { max-width: 900px; margin: 0 auto; background: #1F1F1F; border-radius: 4px; overflow: hidden; box-shadow: 0 40px 80px rgba(0,0,0,0.3); border-top: 3px solid #E8A838; }
        .mockup-bar { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: #1A1A1A; }
        .mockup-dot { width: 10px; height: 10px; border-radius: 50%; }
        .mockup-dot-r { background: #ef4444; }
        .mockup-dot-y { background: #eab308; }
        .mockup-dot-g { background: #22c55e; }
        .mockup-screen { padding: 1.5rem; min-height: 340px; display: grid; grid-template-columns: 200px 1fr; gap: 1.5rem; }
        .mockup-sidebar { display: flex; flex-direction: column; gap: 0.25rem; }
        .mockup-sidebar-item { display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.75rem; border-radius: 2px; font-size: 0.8rem; color: #A39E96; transition: all 0.2s; }
        .mockup-sidebar-item.active { background: rgba(232,168,56,0.12); color: #E8A838; }
        .mockup-sidebar-item svg { width: 16px; height: 16px; opacity: 0.6; }
        .mockup-main { display: flex; flex-direction: column; gap: 1rem; }
        .mockup-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; }
        .mockup-stat { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); border-radius: 2px; padding: 1rem; }
        .mockup-stat-label { font-size: 0.65rem; color: #A39E96; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem; }
        .mockup-stat-value { font-size: 1.5rem; font-weight: 700; color: #F0EBE3; font-family: 'Playfair Display', serif; }
        .mockup-chart { flex: 1; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 2px; padding: 1rem; position: relative; overflow: hidden; }
        .mockup-chart-title { font-size: 0.75rem; color: #A39E96; margin-bottom: 1rem; }
        .mockup-chart-bars { display: flex; align-items: flex-end; gap: 0.5rem; height: 120px; padding-top: 0.5rem; }
        .mockup-bar-item { flex: 1; border-radius: 1px 1px 0 0; animation: barFadeUp 0.4s ease forwards; opacity: 0; transform-origin: bottom; }
        @keyframes barFadeUp { from { opacity: 0; transform: scaleY(0); } to { opacity: 1; transform: scaleY(1); } }
        .mockup-bar-item:nth-child(1) { height: 45%; background: #E8A838; animation-delay: 0.1s; }
        .mockup-bar-item:nth-child(2) { height: 62%; background: #E8A838; animation-delay: 0.2s; }
        .mockup-bar-item:nth-child(3) { height: 38%; background: #E8A838; animation-delay: 0.3s; }
        .mockup-bar-item:nth-child(4) { height: 75%; background: #E8A838; animation-delay: 0.4s; }
        .mockup-bar-item:nth-child(5) { height: 58%; background: #E8A838; animation-delay: 0.5s; }
        .mockup-bar-item:nth-child(6) { height: 85%; background: #E8A838; animation-delay: 0.6s; }
        .mockup-bar-item:nth-child(7) { height: 70%; background: #E8A838; animation-delay: 0.7s; }
        .mockup-bar-item:nth-child(8) { height: 92%; background: #D4952E; animation-delay: 0.8s; }

        .gold-divider { height: 1px; background: rgba(232,168,56,0.15); border: none; }

        /* ── Testimonials ── */
        .testimonials { padding: 6rem 0; background: #2B2B2B; }
        .testimonials-header { text-align: center; margin-bottom: 4rem; }
        .testimonials-header .section-label { color: #E8A838; border-color: rgba(232,168,56,0.3); }
        .testimonials-header .section-title { color: #F0EBE3; }
        .testimonials-header .section-desc { color: #A39E96; margin: 0 auto; }
        .testimonials-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
        .testimonial-card { background: #363636; border-radius: 4px; padding: 2rem; box-shadow: 0 2px 16px rgba(0,0,0,0.15); transition: all 0.2s ease; }
        .testimonial-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.25); }
        .testimonial-quote-mark { font-size: 3rem; line-height: 1; color: #E8A838; font-family: 'Playfair Display', serif; margin-bottom: 0.75rem; }
        .testimonial-text { font-size: 0.95rem; color: #F0EBE3; line-height: 1.7; margin-bottom: 1.5rem; }
        .testimonial-divider { height: 1px; background: rgba(232,168,56,0.2); margin-bottom: 1.25rem; }
        .testimonial-author { font-size: 1rem; font-weight: 600; color: #F0EBE3; font-family: 'Playfair Display', serif; }
        .testimonial-role { font-size: 0.8rem; color: #A39E96; margin-top: 0.2rem; }

        /* ── Why We Built ── */
        .why-built { padding: 6rem 0; background: #FAF6F0; }
        .why-built-content { display: grid; grid-template-columns: 1.2fr 1fr; gap: 4rem; align-items: start; }
        .why-built-story .section-title { font-size: 2.25rem; }
        .why-built-story p { font-size: 0.95rem; color: #6B6560; line-height: 1.75; margin-bottom: 1.25rem; }
        .why-built-beliefs { display: flex; flex-direction: column; gap: 1.5rem; margin-top: 0.5rem; }
        .belief-item { border-left: 3px solid #E8A838; padding-left: 1.25rem; }
        .belief-item h3 { font-size: 1.1rem; font-weight: 600; font-family: 'Playfair Display', serif; margin-bottom: 0.35rem; color: #1F1F1F; }
        .belief-item p { font-size: 0.85rem; color: #6B6560; line-height: 1.6; }

        /* ── Comparison ── */
        .comparison { padding: 6rem 0; background: #F3ECE0; }
        .comparison-header { text-align: center; margin-bottom: 3rem; }
        .comparison-header .section-desc { margin: 0 auto; }
        .comparison-tables { max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 4rem; }
        .comparison-block h3 { font-size: 1.5rem; font-weight: 700; font-family: 'Playfair Display', serif; margin-bottom: 0.5rem; text-align: center; }
        .comparison-block .comparison-sub { font-size: 0.95rem; color: #6B6560; text-align: center; margin-bottom: 1.5rem; }
        .comparison-table { width: 100%; border-collapse: collapse; background: #FAF6F0; border-radius: 4px; overflow: hidden; box-shadow: 0 2px 16px rgba(43,43,43,0.06); }
        .comparison-table thead th { padding: 1rem 1.25rem; text-align: left; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #A39E96; border-bottom: 1px solid rgba(232,168,56,0.15); }
        .comparison-table thead th:first-child { color: #6B6560; }
        .comparison-table thead th.highlight { color: #E8A838; }
        .comparison-table tbody td { padding: 0.85rem 1.25rem; font-size: 0.9rem; color: #6B6560; border-bottom: 1px solid rgba(232,168,56,0.08); }
        .comparison-table tbody td:first-child { font-weight: 500; color: #1F1F1F; }
        .comparison-table tbody tr:last-child td { border-bottom: none; }
        .check-yes { color: #E8A838; font-weight: 700; }
        .check-no { color: #A39E96; }

        /* ── Features ── */
        .features { padding: 6rem 0; background: #F3ECE0; }
        .features-header { text-align: center; margin-bottom: 4rem; }
        .features-header .section-desc { margin: 0 auto; }
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
        .feature-card { background: #FAF6F0; border-radius: 4px; padding: 2rem; transition: all 0.2s ease; position: relative; overflow: hidden; box-shadow: 0 2px 16px rgba(43,43,43,0.06); }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(43,43,43,0.1); }
        .feature-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; background: #E8A838; color: #2B2B2B; }
        .feature-icon svg { width: 24px; height: 24px; }
        .feature-card h3 { font-size: 1.15rem; font-weight: 600; margin-bottom: 0.5rem; font-family: 'Playfair Display', serif; }
        .feature-card p { font-size: 0.9rem; color: #6B6560; line-height: 1.65; }

        /* ── Hierarchy ── */
        .hierarchy { padding: 6rem 0; background: #FAF6F0; }
        .hierarchy-content { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }
        .hierarchy-text .section-title { font-size: 2.25rem; }
        .hierarchy-list { margin-top: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
        .hierarchy-list-item { display: flex; gap: 1rem; align-items: flex-start; }
        .hierarchy-list-check { width: 24px; height: 24px; min-width: 24px; background: rgba(232,168,56,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-top: 2px; }
        .hierarchy-list-check svg { width: 14px; height: 14px; color: #E8A838; }
        .hierarchy-list-item p { font-size: 0.95rem; color: #6B6560; }
        .hierarchy-list-item strong { color: #1F1F1F; }
        .hierarchy-visual { display: flex; justify-content: center; }
        .tree { display: flex; flex-direction: column; align-items: center; gap: 0; }
        .tree-node { padding: 0.75rem 1.5rem; border-radius: 4px; font-weight: 600; font-size: 0.85rem; text-align: center; position: relative; min-width: 140px; }
        .tree-node-zone { background: #E8A838; color: #2B2B2B; box-shadow: 0 4px 16px rgba(232,168,56,0.25); }
        .tree-node-district { background: #D4952E; color: #fff; box-shadow: 0 4px 16px rgba(212,149,46,0.25); }
        .tree-node-cell { background: #2B2B2B; color: #F0EBE3; box-shadow: 0 4px 16px rgba(43,43,43,0.25); }
        .tree-connector { width: 2px; height: 28px; background: rgba(232,168,56,0.3); }
        .tree-branch { display: flex; gap: 2rem; align-items: flex-start; }
        .tree-branch-wrap { display: flex; flex-direction: column; align-items: center; }
        .tree-h-line { display: flex; align-items: flex-start; }
        .tree-h-line::before { content: ''; width: 70px; height: 2px; background: rgba(232,168,56,0.3); margin-top: 0; }
        .tree-h-line::after { content: ''; width: 70px; height: 2px; background: rgba(232,168,56,0.3); margin-top: 0; }
        .tree-level { display: flex; flex-direction: column; align-items: center; }
        .tree-sub-branch { display: flex; gap: 1rem; margin-top: 0; }
        .tree-node-small { padding: 0.5rem 1rem; font-size: 0.75rem; min-width: 100px; }
        .tree-connector-short { height: 20px; }
        .tree-fork { display: flex; align-items: flex-start; position: relative; }
        .tree-fork::before { content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: calc(100% - 100px); height: 2px; background: rgba(232,168,56,0.3); }

        /* ── How It Works ── */
        .how-it-works { padding: 6rem 0; background: #FAF6F0; }
        .how-it-works-header { text-align: center; margin-bottom: 4rem; }
        .steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2rem; position: relative; }
        .steps::before { content: ''; position: absolute; top: 32px; left: 60px; right: 60px; height: 1.5px; background: rgba(232,168,56,0.3); z-index: 0; }
        .step { text-align: center; position: relative; z-index: 1; }
        .step-number { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin: 0 auto 1.25rem; background: #FAF6F0; border: 2px solid rgba(232,168,56,0.3); color: #E8A838; transition: all 0.2s ease; font-family: 'Playfair Display', serif; }
        .step:hover .step-number { background: #E8A838; color: #2B2B2B; border-color: #E8A838; }
        .step h3 { font-size: 1.05rem; font-weight: 600; margin-bottom: 0.5rem; font-family: 'Playfair Display', serif; }
        .step p { font-size: 0.85rem; color: #6B6560; line-height: 1.6; padding: 0 0.5rem; }

        /* ── Mobile ── */
        .mobile-section { padding: 6rem 0; background: #2B2B2B; color: #F0EBE3; overflow: hidden; position: relative; }
        .mobile-content { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; position: relative; z-index: 1; }
        .mobile-text .section-label { color: #E8A838; border-color: rgba(232,168,56,0.3); }
        .mobile-text .section-title { color: #F0EBE3; font-size: 2.25rem; }
        .mobile-text .section-desc { color: #A39E96; }
        .mobile-features { margin-top: 2rem; display: flex; flex-direction: column; gap: 1.25rem; }
        .mobile-feature { display: flex; gap: 1rem; align-items: center; }
        .mobile-feature-icon { width: 40px; height: 40px; min-width: 40px; border-radius: 4px; background: rgba(232,168,56,0.12); display: flex; align-items: center; justify-content: center; }
        .mobile-feature-icon svg { width: 20px; height: 20px; color: #E8A838; }
        .mobile-feature-text { font-size: 0.95rem; color: #A39E96; }
        .mobile-feature-text strong { color: #F0EBE3; }
        .mobile-visual { display: flex; justify-content: center; }
        .phone-mockup { width: 280px; background: #1A1A1A; border-radius: 2.5rem; padding: 0.75rem; box-shadow: 0 40px 80px rgba(0,0,0,0.5), inset 0 0 0 2px rgba(255,255,255,0.05); }
        .phone-screen { background: #1F1F1F; border-radius: 2rem; overflow: hidden; min-height: 520px; }
        .phone-header { padding: 2.5rem 1.25rem 1rem; background: #E8A838; }
        .phone-greeting { font-size: 0.7rem; color: rgba(43,43,43,0.6); margin-bottom: 0.25rem; }
        .phone-name { font-size: 1.1rem; font-weight: 700; color: #2B2B2B; font-family: 'Playfair Display', serif; }
        .phone-stats-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.6rem; padding: 1rem 1.25rem; }
        .phone-stat-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); border-radius: 4px; padding: 0.75rem; }
        .phone-stat-card .label { font-size: 0.55rem; color: #A39E96; text-transform: uppercase; letter-spacing: 0.05em; }
        .phone-stat-card .value { font-size: 1.25rem; font-weight: 700; margin-top: 0.15rem; font-family: 'Playfair Display', serif; }
        .phone-stat-card .value.indigo { color: #E8A838; }
        .phone-stat-card .value.green { color: #6ee7b7; }
        .phone-stat-card .value.amber { color: #fcd34d; }
        .phone-stat-card .value.rose { color: #fda4af; }
        .phone-section-title { font-size: 0.7rem; font-weight: 600; color: #A39E96; padding: 0.75rem 1.25rem 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .phone-list { padding: 0 1.25rem 1.25rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .phone-list-item { display: flex; align-items: center; gap: 0.6rem; background: rgba(255,255,255,0.03); border-radius: 4px; padding: 0.6rem 0.75rem; }
        .phone-avatar { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 600; }
        .phone-list-item-text { flex: 1; }
        .phone-list-item-name { font-size: 0.7rem; font-weight: 600; }
        .phone-list-item-sub { font-size: 0.55rem; color: #A39E96; }
        .phone-list-item-badge { font-size: 0.5rem; padding: 0.15rem 0.5rem; border-radius: 2px; font-weight: 600; }

        /* ── Pricing ── */
        .pricing { padding: 6rem 0; background: #F3ECE0; }
        .pricing-header { text-align: center; margin-bottom: 4rem; }
        .pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; max-width: 960px; margin: 0 auto; }
        .pricing-card { background: #FAF6F0; border-radius: 4px; padding: 2.5rem 2rem; text-align: center; transition: all 0.2s ease; position: relative; box-shadow: 0 2px 16px rgba(43,43,43,0.06); }
        .pricing-card:hover { transform: translateY(-4px); box-shadow: 0 16px 48px rgba(43,43,43,0.1); }
        .pricing-card.featured { border: 1.5px solid #E8A838; box-shadow: 0 16px 48px rgba(232,168,56,0.12); }
        .pricing-popular { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #E8A838; color: #2B2B2B; font-size: 0.7rem; font-weight: 700; padding: 0.3rem 1rem; border-radius: 2px; text-transform: uppercase; letter-spacing: 0.05em; }
        .pricing-name { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; font-family: 'Playfair Display', serif; }
        .pricing-price { font-size: 3rem; font-weight: 700; margin-bottom: 0.25rem; letter-spacing: -0.03em; font-family: 'Playfair Display', serif; }
        .pricing-period { font-size: 0.85rem; color: #A39E96; margin-bottom: 2rem; }
        .pricing-features { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem; text-align: left; }
        .pricing-feature { display: flex; align-items: center; gap: 0.6rem; font-size: 0.875rem; color: #6B6560; }
        .pricing-feature svg { width: 18px; height: 18px; min-width: 18px; color: #E8A838; }
        .pricing-card .btn { width: 100%; justify-content: center; }

        /* ── FAQ ── */
        .faq { padding: 6rem 0; background: #FAF6F0; }
        .faq-header { text-align: center; margin-bottom: 4rem; }
        .faq-grid { max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 0.75rem; }
        .faq-item { border-bottom: 1px solid rgba(232,168,56,0.2); overflow: hidden; background: transparent; }
        .faq-question { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 0; cursor: pointer; font-weight: 600; font-size: 1rem; width: 100%; background: none; border: none; text-align: left; color: #1F1F1F; font-family: 'Playfair Display', serif; }
        .faq-question:hover { color: #E8A838; }
        .faq-question svg { width: 20px; height: 20px; min-width: 20px; transition: transform 0.2s ease; color: #A39E96; }
        .faq-item.open .faq-question svg { transform: rotate(45deg); color: #E8A838; }
        .faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
        .faq-answer-inner { padding: 0 0 1.25rem; font-size: 0.9rem; color: #6B6560; line-height: 1.7; }
        .faq-item.open .faq-answer { max-height: 300px; }

        /* ── CTA ── */
        .cta { padding: 6rem 0; background: #FAF6F0; }
        .cta-box { background: #2B2B2B; border-radius: 4px; padding: 4rem 3rem; text-align: center; color: #F0EBE3; position: relative; overflow: hidden; }
        .cta-box > * { position: relative; z-index: 1; }
        .cta-box h2 { font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; font-family: 'Playfair Display', serif; }
        .cta-box p { font-size: 1.1rem; color: #A39E96; margin-bottom: 2rem; max-width: 500px; margin-left: auto; margin-right: auto; }
        .cta-box .btn-primary { background: #E8A838; color: #2B2B2B; }
        .cta-box .btn-primary:hover { background: #D4952E; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(232,168,56,0.25); }

        /* ── Footer ── */
        .footer { padding: 4rem 0 2rem; background: #222222; border-top: 1px solid rgba(232,168,56,0.15); }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 3rem; margin-bottom: 3rem; }
        .footer-brand-name { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; color: #F0EBE3; font-family: 'Playfair Display', serif; }
        .footer-brand p { font-size: 0.85rem; color: #A39E96; line-height: 1.6; max-width: 280px; }
        .footer-col h4 { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #A39E96; margin-bottom: 1rem; }
        .footer-col a { display: block; font-size: 0.9rem; color: #6B6560; padding: 0.3rem 0; transition: color 0.2s ease; }
        .footer-col a:hover { color: #E8A838; }
        .footer-bottom { text-align: center; padding-top: 2rem; border-top: 1px solid rgba(232,168,56,0.1); font-size: 0.8rem; color: #A39E96; }

        /* ── Responsive ── */
        @media (max-width: 1024px) {
            .hero h1 { font-size: 3rem; }
            .section-title { font-size: 2rem; }
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .testimonials-grid { grid-template-columns: 1fr; max-width: 500px; margin: 0 auto; }
            .pricing-grid { grid-template-columns: 1fr; max-width: 400px; }
            .hierarchy-content, .mobile-content, .why-built-content { grid-template-columns: 1fr; gap: 3rem; }
            .hierarchy-visual { order: -1; }
            .steps::before { display: none; }
            .steps { grid-template-columns: repeat(2, 1fr); }
            .footer-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .nav-links, .nav-cta { display: none; }
            .mobile-menu-btn { display: block; }
            .hero { padding: 8rem 0 4rem; }
            .hero h1 { font-size: 2.25rem; }
            .hero p { font-size: 1.05rem; }
            .hero-visual { display: none; }
            .comparison-table { font-size: 0.85rem; }
            .features-grid { grid-template-columns: 1fr; }
            .steps { grid-template-columns: 1fr; }
            .mockup-screen { grid-template-columns: 1fr; }
            .mockup-sidebar { display: none; }
            .mockup-stats { grid-template-columns: repeat(2, 1fr); }
            .footer-grid { grid-template-columns: 1fr; gap: 2rem; }
            .cta-box { padding: 3rem 1.5rem; }
            .cta-box h2 { font-size: 1.75rem; }
            .tree-branch { gap: 0.75rem; }
            .tree-node { min-width: 100px; font-size: 0.75rem; padding: 0.6rem 1rem; }
            .tree-node-small { min-width: 75px; font-size: 0.65rem; padding: 0.4rem 0.6rem; }
        }
        @media (max-width: 480px) {
            .hero-actions { flex-direction: column; align-items: center; }
            .hero-actions .btn { width: 100%; justify-content: center; }
            .phone-mockup { width: 240px; }
        }
    </style>
</head>
<body>

<!-- ── Nav ── -->
<nav class="nav">
    <div class="container">
        <a href="/" class="nav-brand">
            <img src="/images/flock-logo.png" alt="Flock logo">
            Flock
        </a>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#pricing">Pricing</a>
            <a href="#faq">FAQ</a>
        </div>
        <div class="nav-cta">
            <a href="/support" class="btn btn-secondary">Support</a>
            <a href="https://flock.church-stack.com" class="btn btn-primary">Get Started</a>
        </div>
        <button class="mobile-menu-btn" onclick="document.querySelector('.nav-links').style.display = document.querySelector('.nav-links').style.display === 'flex' ? 'none' : 'flex'">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<!-- ── Hero ── -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-badge">
                <span class="hero-badge-dot"></span>
                Now serving churches across the UK
            </div>
            <h1>The smarter way to <span class="accent">manage&nbsp;your&nbsp;church</span></h1>
            <p>Track attendance, manage members, empower leaders, and gain real-time insights — all from one powerful platform built for growing churches.</p>
            <div class="hero-actions">
                <a href="https://flock.church-stack.com" class="btn btn-primary btn-large">
                    Start Free Trial
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
                <a href="#features" class="btn btn-secondary btn-large">See Features</a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-visual-mockup">
                <div class="mockup-bar">
                    <span class="mockup-dot mockup-dot-r"></span>
                    <span class="mockup-dot mockup-dot-y"></span>
                    <span class="mockup-dot mockup-dot-g"></span>
                </div>
                <div class="mockup-screen">
                    <div class="mockup-sidebar">
                        <div class="mockup-sidebar-item active">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z"/><path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.432z"/></svg>
                            Dashboard
                        </div>
                        <div class="mockup-sidebar-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M8.25 6.75a3.75 3.75 0 117.5 0 3.75 3.75 0 01-7.5 0zM15.75 9.75a3 3 0 116 0 3 3 0 01-6 0zM2.25 9.75a3 3 0 116 0 3 3 0 01-6 0zM6.31 15.117A6.745 6.745 0 0112 12a6.745 6.745 0 016.709 7.498.75.75 0 01-.372.568A12.696 12.696 0 0112 21.75c-2.305 0-4.47-.612-6.337-1.684a.75.75 0 01-.372-.568 6.787 6.787 0 011.019-4.38z"/></svg>
                            Members
                        </div>
                        <div class="mockup-sidebar-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 01-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-2.25z"/></svg>
                            Groups
                        </div>
                        <div class="mockup-sidebar-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M7.502 6h7.128A3.375 3.375 0 0118 9.375v9.375a3 3 0 003-3V6.108c0-1.505-1.125-2.811-2.664-2.94a48.972 48.972 0 00-.673-.05A3 3 0 0015 1.5h-1.5a3 3 0 00-2.663 1.618c-.225.015-.45.032-.673.05C8.662 3.295 7.554 4.542 7.502 6zM13.5 3A1.5 1.5 0 0012 4.5h4.5A1.5 1.5 0 0015 3h-1.5z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M3 9.375C3 8.339 3.84 7.5 4.875 7.5h9.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-9.75A1.875 1.875 0 013 20.625V9.375zm9.586 4.594a.75.75 0 00-1.172-.938l-2.476 3.096-.908-.907a.75.75 0 00-1.06 1.06l1.5 1.5a.75.75 0 001.116-.062l3-3.749z" clip-rule="evenodd"/></svg>
                            Attendance
                        </div>
                        <div class="mockup-sidebar-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M11.828 2.25c-.916 0-1.699.663-1.85 1.567l-.091.549a.798.798 0 01-.517.608 7.45 7.45 0 00-.478.198.798.798 0 01-.796-.064l-.453-.324a1.875 1.875 0 00-2.416.2l-.243.243a1.875 1.875 0 00-.2 2.416l.324.453a.798.798 0 01.064.796 7.448 7.448 0 00-.198.478.798.798 0 01-.608.517l-.55.092a1.875 1.875 0 00-1.566 1.849v.344c0 .916.663 1.699 1.567 1.85l.549.091c.281.047.508.25.608.517.06.162.127.321.198.478a.798.798 0 01-.064.796l-.324.453a1.875 1.875 0 00.2 2.416l.243.243c.648.648 1.67.733 2.416.2l.453-.324a.798.798 0 01.796-.064c.157.071.316.137.478.198.267.1.47.327.517.608l.092.55c.15.903.932 1.566 1.849 1.566h.344c.916 0 1.699-.663 1.85-1.567l.091-.549a.798.798 0 01.517-.608 7.52 7.52 0 00.478-.198.798.798 0 01.796.064l.453.324a1.875 1.875 0 002.416-.2l.243-.243c.648-.648.733-1.67.2-2.416l-.324-.453a.798.798 0 01-.064-.796c.071-.157.137-.316.198-.478a.798.798 0 01.608-.517l.55-.091a1.875 1.875 0 001.566-1.85v-.344c0-.916-.663-1.699-1.567-1.85l-.549-.091a.798.798 0 01-.608-.517 7.507 7.507 0 00-.198-.478.798.798 0 01.064-.796l.324-.453a1.875 1.875 0 00-.2-2.416l-.243-.243a1.875 1.875 0 00-2.416-.2l-.453.324a.798.798 0 01-.796.064 7.462 7.462 0 00-.478-.198.798.798 0 01-.517-.608l-.091-.55a1.875 1.875 0 00-1.85-1.566h-.344zM12 15.75a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z" clip-rule="evenodd"/></svg>
                            Settings
                        </div>
                    </div>
                    <div class="mockup-main">
                        <div class="mockup-stats">
                            <div class="mockup-stat">
                                <div class="mockup-stat-label">Members</div>
                                <div class="mockup-stat-value">1,247</div>
                            </div>
                            <div class="mockup-stat">
                                <div class="mockup-stat-label">Groups</div>
                                <div class="mockup-stat-value">86</div>
                            </div>
                            <div class="mockup-stat">
                                <div class="mockup-stat-label">Leaders</div>
                                <div class="mockup-stat-value">42</div>
                            </div>
                            <div class="mockup-stat">
                                <div class="mockup-stat-label">Avg Attendance</div>
                                <div class="mockup-stat-value">89%</div>
                            </div>
                        </div>
                        <div class="mockup-chart">
                            <div class="mockup-chart-title">Weekly Attendance Trends</div>
                            <div class="mockup-chart-bars">
                                <div class="mockup-bar-item"></div>
                                <div class="mockup-bar-item"></div>
                                <div class="mockup-bar-item"></div>
                                <div class="mockup-bar-item"></div>
                                <div class="mockup-bar-item"></div>
                                <div class="mockup-bar-item"></div>
                                <div class="mockup-bar-item"></div>
                                <div class="mockup-bar-item"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Features ── -->
<section class="features" id="features">
    <div class="container">
        <div class="features-header">
            <span class="section-label">Features</span>
            <h2 class="section-title">Everything your church needs,<br>in one place</h2>
            <p class="section-desc">From attendance tracking to leadership management, Flock gives you the tools to run your church efficiently and focus on what matters most.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M7.502 6h7.128A3.375 3.375 0 0118 9.375v9.375a3 3 0 003-3V6.108c0-1.505-1.125-2.811-2.664-2.94a48.972 48.972 0 00-.673-.05A3 3 0 0015 1.5h-1.5a3 3 0 00-2.663 1.618c-.225.015-.45.032-.673.05C8.662 3.295 7.554 4.542 7.502 6zM13.5 3A1.5 1.5 0 0012 4.5h4.5A1.5 1.5 0 0015 3h-1.5z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M3 9.375C3 8.339 3.84 7.5 4.875 7.5h9.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-9.75A1.875 1.875 0 013 20.625V9.375zm9.586 4.594a.75.75 0 00-1.172-.938l-2.476 3.096-.908-.907a.75.75 0 00-1.06 1.06l1.5 1.5a.75.75 0 001.116-.062l3-3.749z" clip-rule="evenodd"/></svg>
                </div>
                <h3>Attendance Tracking</h3>
                <p>Submit and track attendance from mobile or web. Instantly see who showed up, identify defaulters, and track first-timers and visitors.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M8.25 6.75a3.75 3.75 0 117.5 0 3.75 3.75 0 01-7.5 0zM15.75 9.75a3 3 0 116 0 3 3 0 01-6 0zM2.25 9.75a3 3 0 116 0 3 3 0 01-6 0zM6.31 15.117A6.745 6.745 0 0112 12a6.745 6.745 0 016.709 7.498.75.75 0 01-.372.568A12.696 12.696 0 0112 21.75c-2.305 0-4.47-.612-6.337-1.684a.75.75 0 01-.372-.568 6.787 6.787 0 011.019-4.38z"/></svg>
                </div>
                <h3>Member Management</h3>
                <p>Complete profiles with contact info, baptism records, NBS status, and member type tracking. Search, filter, and manage your entire congregation.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 01-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-2.25z"/></svg>
                </div>
                <h3>Group Hierarchy</h3>
                <p>Organise your church into Zones, Districts, and Cell Groups with a flexible hierarchy that adapts to your structure. Unlimited depth.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 13.5a8.25 8.25 0 018.25-8.25.75.75 0 01.75.75v6.75H18a.75.75 0 01.75.75 8.25 8.25 0 01-16.5 0z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M12.75 3a.75.75 0 01.75-.75 8.25 8.25 0 018.25 8.25.75.75 0 01-.75.75h-7.5a.75.75 0 01-.75-.75V3z" clip-rule="evenodd"/></svg>
                </div>
                <h3>Real-Time Dashboard</h3>
                <p>Visualise attendance trends, track weekly growth, monitor group performance, and gain insights that help you make data-driven decisions.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>
                </div>
                <h3>Leadership & Roles</h3>
                <p>Assign roles with granular permissions — Super Admin, Zone Overseer, District Pastor, Cell Leader. Each sees only what they need.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M5.85 3.5a.75.75 0 00-1.117-1 9.719 9.719 0 00-2.348 4.876.75.75 0 001.479.248A8.219 8.219 0 015.85 3.5zM19.267 2.5a.75.75 0 10-1.118 1 8.22 8.22 0 011.987 4.124.75.75 0 001.48-.248A9.72 9.72 0 0019.266 2.5z"/><path fill-rule="evenodd" d="M12 2.25A6.75 6.75 0 005.25 9v.75a8.217 8.217 0 01-2.119 5.52.75.75 0 00.298 1.206c1.544.57 3.16.99 4.831 1.243a3.75 3.75 0 107.48 0 24.583 24.583 0 004.83-1.244.75.75 0 00.298-1.205 8.217 8.217 0 01-2.118-5.52V9A6.75 6.75 0 0012 2.25zM9.75 18c0-.034 0-.067.002-.1a25.05 25.05 0 004.496 0l.002.1a2.25 2.25 0 01-4.5 0z" clip-rule="evenodd"/></svg>
                </div>
                <h3>Smart Notifications</h3>
                <p>Automatic birthday reminders, attendance completion alerts, and custom broadcasts. Keep your leaders informed and your church connected.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── Testimonials ── -->
<section class="testimonials">
    <div class="container">
        <div class="testimonials-header">
            <span class="section-label">Trusted by Churches</span>
            <h2 class="section-title">What church leaders are saying</h2>
        </div>
        <div class="testimonials-grid">
            <!-- PLACEHOLDER: Replace with real testimonial -->
            <div class="testimonial-card">
                <div class="testimonial-quote-mark">"</div>
                <p class="testimonial-text">Before Flock, our attendance data was scattered across WhatsApp groups and paper registers. Now every leader submits in seconds, and I can see the whole church from my phone. It's transformed how we track growth.</p>
                <div class="testimonial-divider"></div>
                <div class="testimonial-author">Pastor James Adeyemi</div>
                <div class="testimonial-role">Senior Pastor, Grace Assembly, London</div>
            </div>
            <!-- PLACEHOLDER: Replace with real testimonial -->
            <div class="testimonial-card">
                <div class="testimonial-quote-mark">"</div>
                <p class="testimonial-text">I used to spend my Monday evenings chasing members for attendance numbers. With Flock, I mark attendance during the meeting and it's done. My district pastor sees it instantly.</p>
                <div class="testimonial-divider"></div>
                <div class="testimonial-author">Sarah Okonkwo</div>
                <div class="testimonial-role">Cell Leader, Covenant House, Birmingham</div>
            </div>
            <!-- PLACEHOLDER: Replace with real testimonial -->
            <div class="testimonial-card">
                <div class="testimonial-quote-mark">"</div>
                <p class="testimonial-text">We tried spreadsheets, we tried other tools — nothing fit our structure. Flock understood that we organise by zones and districts, not departments. Setup took us about 20 minutes.</p>
                <div class="testimonial-divider"></div>
                <div class="testimonial-author">David Mensah</div>
                <div class="testimonial-role">Administrator, New Life Chapel, Manchester</div>
            </div>
        </div>
    </div>
</section>

<hr class="gold-divider">

<!-- ── Hierarchy ── -->
<section class="hierarchy">
    <div class="container">
        <div class="hierarchy-content">
            <div class="hierarchy-text">
                <span class="section-label">Church Structure</span>
                <h2 class="section-title">Built for how your church <span class="accent">actually works</span></h2>
                <p class="section-desc">Flock mirrors your real-world church hierarchy. Whether you have 3 levels or 10, the structure adapts to you — not the other way around.</p>
                <div class="hierarchy-list">
                    <div class="hierarchy-list-item">
                        <div class="hierarchy-list-check">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        </div>
                        <p><strong>Zones</strong> for regional oversight and high-level reporting</p>
                    </div>
                    <div class="hierarchy-list-item">
                        <div class="hierarchy-list-check">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        </div>
                        <p><strong>Districts</strong> for mid-level coordination across groups</p>
                    </div>
                    <div class="hierarchy-list-item">
                        <div class="hierarchy-list-check">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        </div>
                        <p><strong>Cell Groups</strong> where attendance happens and lives are changed</p>
                    </div>
                    <div class="hierarchy-list-item">
                        <div class="hierarchy-list-check">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        </div>
                        <p><strong>Custom Levels</strong> — add as many as your church needs</p>
                    </div>
                </div>
            </div>
            <div class="hierarchy-visual">
                <div class="tree">
                    <div class="tree-node tree-node-zone">Zone</div>
                    <div class="tree-connector"></div>
                    <div class="tree-fork">
                        <div class="tree-branch">
                            <div class="tree-branch-wrap">
                                <div class="tree-node tree-node-district">District A</div>
                                <div class="tree-connector tree-connector-short"></div>
                                <div class="tree-sub-branch">
                                    <div class="tree-branch-wrap">
                                        <div class="tree-node tree-node-cell tree-node-small">Cell 1</div>
                                    </div>
                                    <div class="tree-branch-wrap">
                                        <div class="tree-node tree-node-cell tree-node-small">Cell 2</div>
                                    </div>
                                </div>
                            </div>
                            <div class="tree-branch-wrap">
                                <div class="tree-node tree-node-district">District B</div>
                                <div class="tree-connector tree-connector-short"></div>
                                <div class="tree-sub-branch">
                                    <div class="tree-branch-wrap">
                                        <div class="tree-node tree-node-cell tree-node-small">Cell 3</div>
                                    </div>
                                    <div class="tree-branch-wrap">
                                        <div class="tree-node tree-node-cell tree-node-small">Cell 4</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<hr class="gold-divider">

<!-- ── How It Works ── -->
<section class="how-it-works" id="how-it-works">
    <div class="container">
        <div class="how-it-works-header">
            <span class="section-label">How It Works</span>
            <h2 class="section-title">Up and running in minutes</h2>
            <p class="section-desc mx-auto">Getting started with Flock is simple. No technical setup required.</p>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h3>Sign Up</h3>
                <p>Create your church account and get your own dedicated subdomain instantly.</p>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <h3>Set Up Structure</h3>
                <p>Define your group types and hierarchy — Zones, Districts, Cells, or your own custom structure.</p>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <h3>Add Your People</h3>
                <p>Import members, create leader accounts, and assign them to their groups and roles.</p>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <h3>Go Live</h3>
                <p>Leaders download the mobile app and start tracking attendance, managing members, and growing.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── Why We Built Flock ── -->
<section class="why-built">
    <div class="container">
        <div class="why-built-content">
            <!-- PLACEHOLDER: Replace with your real origin story -->
            <div class="why-built-story">
                <span class="section-label">Our Story</span>
                <h2 class="section-title">Built because churches deserve better tools</h2>
                <p>We watched churches drown in spreadsheets. Attendance tracked on paper, then typed into Excel, then shared over WhatsApp — with numbers getting lost at every step. Leaders spent hours on admin that should have taken minutes.</p>
                <p>We saw district pastors who couldn't tell you their group's attendance trend without digging through months of messages. Zone overseers making decisions based on gut feeling instead of data. Church admins buried under manual reports every week.</p>
                <p>So we built Flock — a platform that understands how churches actually work. Not a generic CRM with a church label slapped on. A tool built around zones, districts, and cells from day one, because that's how real churches organise.</p>
            </div>
            <div class="why-built-beliefs">
                <div class="belief-item">
                    <h3>Church admin should take minutes, not hours</h3>
                    <p>Leaders should lead, not wrestle spreadsheets. Flock automates the tedious work so you can focus on people.</p>
                </div>
                <div class="belief-item">
                    <h3>Every leader deserves real-time visibility</h3>
                    <p>From cell leaders to senior pastors, everyone should see what matters to them — instantly, not after a week of chasing reports.</p>
                </div>
                <div class="belief-item">
                    <h3>Your structure, your rules</h3>
                    <p>Zones, districts, cells — or whatever you call them. Flock adapts to your church, not the other way around.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Why Flock — Comparison ── -->
<section class="comparison">
    <div class="container">
        <div class="comparison-header">
            <span class="section-label">Why Flock</span>
            <h2 class="section-title">See how Flock compares</h2>
        </div>
        <div class="comparison-tables">
            <div class="comparison-block">
                <h3>Still using spreadsheets?</h3>
                <p class="comparison-sub">Here's what you're missing.</p>
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Spreadsheets</th>
                            <th class="highlight">Flock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Mobile attendance submission</td>
                            <td class="check-no">No</td>
                            <td class="check-yes">Yes</td>
                        </tr>
                        <tr>
                            <td>Real-time dashboards</td>
                            <td class="check-no">No</td>
                            <td class="check-yes">Yes</td>
                        </tr>
                        <tr>
                            <td>Role-based access</td>
                            <td class="check-no">No</td>
                            <td class="check-yes">Yes</td>
                        </tr>
                        <tr>
                            <td>Automatic reports</td>
                            <td class="check-no">No</td>
                            <td class="check-yes">Yes</td>
                        </tr>
                        <tr>
                            <td>Member profiles & history</td>
                            <td class="check-no">Manual</td>
                            <td class="check-yes">Built-in</td>
                        </tr>
                        <tr>
                            <td>Setup time</td>
                            <td class="check-no">Hours of formatting</td>
                            <td class="check-yes">20 minutes</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="comparison-block">
                <h3>How Flock compares to other tools</h3>
                <p class="comparison-sub">Built different, priced fairly.</p>
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Other Tools</th>
                            <th class="highlight">Flock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Cell/zone/district hierarchy</td>
                            <td class="check-no">Limited or none</td>
                            <td class="check-yes">Built-in, unlimited depth</td>
                        </tr>
                        <tr>
                            <td>UK-focused</td>
                            <td class="check-no">Mostly US-based</td>
                            <td class="check-yes">Designed for UK churches</td>
                        </tr>
                        <tr>
                            <td>Mobile-first attendance</td>
                            <td class="check-no">Add-on or web only</td>
                            <td class="check-yes">Native mobile app</td>
                        </tr>
                        <tr>
                            <td>Pricing</td>
                            <td class="check-no">$50-100+/month</td>
                            <td class="check-yes">Free tier + $29/month</td>
                        </tr>
                        <tr>
                            <td>Setup complexity</td>
                            <td class="check-no">Days to weeks</td>
                            <td class="check-yes">Minutes</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- ── Mobile ── -->
<section class="mobile-section">
    <div class="container">
        <div class="mobile-content">
            <div class="mobile-text">
                <span class="section-label">Mobile App</span>
                <h2 class="section-title">Your church in your pocket</h2>
                <p class="section-desc">Leaders get a powerful mobile app to manage their groups on the go. No more paper registers or chasing spreadsheets.</p>
                <div class="mobile-features">
                    <div class="mobile-feature">
                        <div class="mobile-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        </div>
                        <div class="mobile-feature-text"><strong>Quick attendance</strong> — mark members present in seconds</div>
                    </div>
                    <div class="mobile-feature">
                        <div class="mobile-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 13.5a8.25 8.25 0 018.25-8.25.75.75 0 01.75.75v6.75H18a.75.75 0 01.75.75 8.25 8.25 0 01-16.5 0z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M12.75 3a.75.75 0 01.75-.75 8.25 8.25 0 018.25 8.25.75.75 0 01-.75.75h-7.5a.75.75 0 01-.75-.75V3z" clip-rule="evenodd"/></svg>
                        </div>
                        <div class="mobile-feature-text"><strong>Live dashboard</strong> — stats and trends at a glance</div>
                    </div>
                    <div class="mobile-feature">
                        <div class="mobile-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M5.85 3.5a.75.75 0 00-1.117-1 9.719 9.719 0 00-2.348 4.876.75.75 0 001.479.248A8.219 8.219 0 015.85 3.5zM19.267 2.5a.75.75 0 10-1.118 1 8.22 8.22 0 011.987 4.124.75.75 0 001.48-.248A9.72 9.72 0 0019.266 2.5z"/><path fill-rule="evenodd" d="M12 2.25A6.75 6.75 0 005.25 9v.75a8.217 8.217 0 01-2.119 5.52.75.75 0 00.298 1.206c1.544.57 3.16.99 4.831 1.243a3.75 3.75 0 107.48 0 24.583 24.583 0 004.83-1.244.75.75 0 00.298-1.205 8.217 8.217 0 01-2.118-5.52V9A6.75 6.75 0 0012 2.25zM9.75 18c0-.034 0-.067.002-.1a25.05 25.05 0 004.496 0l.002.1a2.25 2.25 0 01-4.5 0z" clip-rule="evenodd"/></svg>
                        </div>
                        <div class="mobile-feature-text"><strong>Push notifications</strong> — birthdays, reminders, and alerts</div>
                    </div>
                    <div class="mobile-feature">
                        <div class="mobile-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M8.25 6.75a3.75 3.75 0 117.5 0 3.75 3.75 0 01-7.5 0zM15.75 9.75a3 3 0 116 0 3 3 0 01-6 0zM2.25 9.75a3 3 0 116 0 3 3 0 01-6 0zM6.31 15.117A6.745 6.745 0 0112 12a6.745 6.745 0 016.709 7.498.75.75 0 01-.372.568A12.696 12.696 0 0112 21.75c-2.305 0-4.47-.612-6.337-1.684a.75.75 0 01-.372-.568 6.787 6.787 0 011.019-4.38z"/></svg>
                        </div>
                        <div class="mobile-feature-text"><strong>Member profiles</strong> — view and manage people anywhere</div>
                    </div>
                </div>
            </div>
            <div class="mobile-visual">
                <div class="phone-mockup">
                    <div class="phone-screen">
                        <div class="phone-header">
                            <div class="phone-greeting">Good morning,</div>
                            <div class="phone-name">Pastor David</div>
                        </div>
                        <div class="phone-stats-row">
                            <div class="phone-stat-card">
                                <div class="label">Members</div>
                                <div class="value indigo">248</div>
                            </div>
                            <div class="phone-stat-card">
                                <div class="label">Groups</div>
                                <div class="value green">12</div>
                            </div>
                            <div class="phone-stat-card">
                                <div class="label">Attendance</div>
                                <div class="value amber">91%</div>
                            </div>
                            <div class="phone-stat-card">
                                <div class="label">First Timers</div>
                                <div class="value rose">7</div>
                            </div>
                        </div>
                        <div class="phone-section-title">Recent Activity</div>
                        <div class="phone-list">
                            <div class="phone-list-item">
                                <div class="phone-avatar" style="background:rgba(232,168,56,0.15);color:#E8A838;">JO</div>
                                <div class="phone-list-item-text">
                                    <div class="phone-list-item-name">John Okafor</div>
                                    <div class="phone-list-item-sub">Submitted attendance - Cell 3</div>
                                </div>
                                <div class="phone-list-item-badge" style="background:#d1fae5;color:#059669;">Done</div>
                            </div>
                            <div class="phone-list-item">
                                <div class="phone-avatar" style="background:#fef3c7;color:#d97706;">SA</div>
                                <div class="phone-list-item-text">
                                    <div class="phone-list-item-name">Sarah Adeyemi</div>
                                    <div class="phone-list-item-sub">New member added</div>
                                </div>
                                <div class="phone-list-item-badge" style="background:rgba(232,168,56,0.15);color:#E8A838;">New</div>
                            </div>
                            <div class="phone-list-item">
                                <div class="phone-avatar" style="background:#ffe4e6;color:#e11d48;">MK</div>
                                <div class="phone-list-item-text">
                                    <div class="phone-list-item-name">Michael Kalu</div>
                                    <div class="phone-list-item-sub">Birthday today</div>
                                </div>
                                <div class="phone-list-item-badge" style="background:#fef3c7;color:#d97706;">🎂</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Pricing ── -->
<section class="pricing" id="pricing">
    <div class="container">
        <div class="pricing-header">
            <span class="section-label">Pricing</span>
            <h2 class="section-title">Simple, transparent pricing</h2>
            <p class="section-desc mx-auto">Start free, scale as you grow. No hidden fees, no surprises.</p>
        </div>
        <div class="pricing-grid">
            <div class="pricing-card">
                <div class="pricing-name">Starter</div>
                <div class="pricing-price">Free</div>
                <div class="pricing-period">For small churches getting started</div>
                <div class="pricing-features">
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        Up to 100 members
                    </div>
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        5 leader accounts
                    </div>
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        Attendance tracking
                    </div>
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        Basic dashboard
                    </div>
                </div>
                <a href="https://flock.church-stack.com" class="btn btn-secondary-light">Get Started</a>
            </div>
            <div class="pricing-card featured">
                <div class="pricing-popular">Most Popular</div>
                <div class="pricing-name">Growth</div>
                <div class="pricing-price"><span class="accent">$29</span></div>
                <div class="pricing-period">per month, billed annually</div>
                <div class="pricing-features">
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        Unlimited members
                    </div>
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        Unlimited leaders
                    </div>
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        Push notifications
                    </div>
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        Advanced analytics
                    </div>
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        Custom branding
                    </div>
                </div>
                <a href="https://flock.church-stack.com" class="btn btn-primary">Start Free Trial</a>
            </div>
            <div class="pricing-card">
                <div class="pricing-name">Enterprise</div>
                <div class="pricing-price">Custom</div>
                <div class="pricing-period">For large churches & networks</div>
                <div class="pricing-features">
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        Everything in Growth
                    </div>
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        Multi-campus support
                    </div>
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        Dedicated support
                    </div>
                    <div class="pricing-feature">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd"/></svg>
                        API access
                    </div>
                </div>
                <a href="mailto:support@church-stack.com" class="btn btn-secondary-light">Contact Sales</a>
            </div>
        </div>
    </div>
</section>

<!-- ── FAQ ── -->
<section class="faq" id="faq">
    <div class="container">
        <div class="faq-header">
            <span class="section-label">FAQ</span>
            <h2 class="section-title">Common questions</h2>
        </div>
        <div class="faq-grid">
            <div class="faq-item">
                <button class="faq-question" onclick="this.parentElement.classList.toggle('open')">
                    How long does it take to get set up?
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd"/></svg>
                </button>
                <div class="faq-answer"><div class="faq-answer-inner">Most churches are up and running within 20 minutes. Create your account, define your group structure, and start adding members. No technical setup, no consultants, no waiting.</div></div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="this.parentElement.classList.toggle('open')">
                    Will my non-tech-savvy leaders actually use this?
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd"/></svg>
                </button>
                <div class="faq-answer"><div class="faq-answer-inner">Flock's mobile app is designed for simplicity. Leaders open the app, tap their group, mark who's present, and submit. If they can use WhatsApp, they can use Flock.</div></div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="this.parentElement.classList.toggle('open')">
                    What happens to our data if we cancel?
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd"/></svg>
                </button>
                <div class="faq-answer"><div class="faq-answer-inner">Your data belongs to you. Export everything — members, attendance records, group structures — at any time. We never hold your data hostage.</div></div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="this.parentElement.classList.toggle('open')">
                    How is Flock different from a spreadsheet?
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd"/></svg>
                </button>
                <div class="faq-answer"><div class="faq-answer-inner">Spreadsheets don't send reminders, generate dashboards, work on mobile, or let leaders submit attendance from their phone. Flock does all of that out of the box, with zero formatting required.</div></div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="this.parentElement.classList.toggle('open')">
                    Is our church data secure?
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd"/></svg>
                </button>
                <div class="faq-answer"><div class="faq-answer-inner">Yes. Every church gets its own isolated database with encrypted data in transit, secure authentication, and role-based access controls. Leaders can only see data relevant to their assigned groups and roles.</div></div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="this.parentElement.classList.toggle('open')">
                    Can we customise the group structure?
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd"/></svg>
                </button>
                <div class="faq-answer"><div class="faq-answer-inner">Absolutely. Flock lets you define your own hierarchy — whether that's Zones, Districts, and Cells, or something entirely different. You can have as many levels as you need, and each level can be customised with its own name, colour, and attendance rules.</div></div>
            </div>
            <div class="faq-item">
                <button class="faq-question" onclick="this.parentElement.classList.toggle('open')">
                    What support do you offer?
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd"/></svg>
                </button>
                <div class="faq-answer"><div class="faq-answer-inner">Email support with fast response times, plus in-app help guides. We're a small team that actually reads every message — no ticket queues, no bots.</div></div>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA ── -->
<section class="cta">
    <div class="container">
        <div class="cta-box">
            <h2>Ready to grow your church?</h2>
            <p>Start managing your church with confidence. Free to try, simple to set up, built for how your church actually works.</p>
            <a href="https://flock.church-stack.com" class="btn btn-primary btn-large">
                Start Your Free Trial
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</section>

<!-- ── Footer ── -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="footer-brand-name">
                    <img src="/images/flock-logo.png" alt="Flock logo" style="width:28px;height:28px;object-fit:contain;">
                    Flock
                </div>
                <p>Modern church management software built for growing churches. Track attendance, manage members, and empower your leaders.</p>
            </div>
            <div class="footer-col">
                <h4>Product</h4>
                <a href="#features">Features</a>
                <a href="#pricing">Pricing</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#faq">FAQ</a>
            </div>
            <div class="footer-col">
                <h4>Resources</h4>
                <a href="/support">Support</a>
                <a href="/privacy">Privacy Policy</a>
                <a href="mailto:support@church-stack.com">Contact Us</a>
            </div>
            <div class="footer-col">
                <h4>Get Started</h4>
                <a href="https://flock.church-stack.com">Sign Up Free</a>
                <a href="https://flock.church-stack.com">Log In</a>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; {{ date('Y') }} Church Stack. All rights reserved.
        </div>
    </div>
</footer>


</body>
</html>
