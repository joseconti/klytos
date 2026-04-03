---
description: "Ready-to-use visual design patterns for creating rich page layouts. Read this guide when replicating a website design or creating visually complex pages. Every pattern uses <!-- wp:html --> blocks for full design freedom while maintaining Gutenberg editor compatibility."
globs: ["**/*.php", "**/*.html"]
alwaysApply: false
---

# Klytos CMS — Design Patterns Reference

## When to Use This Guide

Read this guide when:
- The user provides a design reference (URL or screenshot) to replicate
- You need to create visually rich pages beyond basic text content
- You need product grids, pricing tables, hero sections, testimonial sections, etc.
- The standard Gutenberg blocks (paragraph, heading, columns) are not enough for the desired design

## Key Principle: wp:html for Design Freedom

Every complex visual section should be wrapped in a `<!-- wp:html -->` block. This gives you TOTAL design freedom while keeping Gutenberg editor compatibility. The editor shows these as "Custom HTML" blocks.

For page-specific CSS, use the `custom_css` field in `klytos_create_page` / `klytos_update_page` instead of excessive inline styles.

## Available CSS Utilities

Klytos provides utility classes you can use inside your HTML. These are always available:
- Layout: `.klytos-container`, `.klytos-grid-2` to `.klytos-grid-6`, `.klytos-grid-auto`
- Cards: `.klytos-card`, `.klytos-card-hover`, `.klytos-card-bordered`, `.klytos-card-flat`
- Buttons: `.klytos-btn`, `.klytos-btn-primary`, `.klytos-btn-secondary`, `.klytos-btn-outline`, `.klytos-btn-ghost`, `.klytos-btn-lg`, `.klytos-btn-xl`, `.klytos-btn-sm`, `.klytos-btn-rounded`
- Sections: `.klytos-section-dark`, `.klytos-section-light`, `.klytos-section-primary`, `.klytos-section-gradient`
- Shadows: `.klytos-shadow-sm`, `.klytos-shadow`, `.klytos-shadow-md`, `.klytos-shadow-lg`, `.klytos-shadow-xl`
- Spacing: `.klytos-py-2` to `.klytos-py-6`, `.klytos-px-1`, `.klytos-px-2`, `.klytos-mt-0` to `.klytos-mt-3`, `.klytos-mb-0` to `.klytos-mb-3`
- Typography: `.klytos-text-center`, `.klytos-text-sm` to `.klytos-text-4xl`, `.klytos-font-bold`, `.klytos-font-semibold`, `.klytos-text-muted`
- Flex: `.klytos-flex`, `.klytos-flex-col`, `.klytos-flex-wrap`, `.klytos-items-center`, `.klytos-justify-center`, `.klytos-justify-between`
- Width: `.klytos-max-w-sm`, `.klytos-max-w-md`, `.klytos-max-w-lg`, `.klytos-max-w-xl`
- Effects: `.klytos-transition`, `.klytos-hover-scale`, `.klytos-rounded` to `.klytos-rounded-full`
- Responsive: `.klytos-hide-mobile`, `.klytos-show-mobile`, `.klytos-hide-sm`
- CSS Variables: `var(--klytos-primary)`, `var(--klytos-secondary)`, `var(--klytos-accent)`, `var(--klytos-text)`, `var(--klytos-text-muted)`, `var(--klytos-background)`, `var(--klytos-surface)`, `var(--klytos-border)`, `var(--klytos-radius)`, `var(--klytos-spacing)`, `var(--klytos-font-heading)`, `var(--klytos-font-body)`

---

## Pattern 1: Hero Section — Gradient with Two CTAs

Use this when you need an eye-catching full-width hero with a gradient background, large heading, subtitle, and two call-to-action buttons (one primary, one outline style).

<!-- wp:html -->
<div class="klytos-hero-gradient">
  <style>
    .klytos-hero-gradient {
      background: linear-gradient(135deg, var(--klytos-primary) 0%, var(--klytos-secondary) 100%);
      position: relative;
      overflow: hidden;
      padding: 80px 20px;
      min-height: 500px;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: white;
    }

    .klytos-hero-gradient::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 400px;
      height: 400px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      transform: translate(100px, -100px);
    }

    .klytos-hero-gradient::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 300px;
      height: 300px;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 50%;
      transform: translate(-50px, 50px);
    }

    .klytos-hero-gradient-content {
      position: relative;
      z-index: 2;
      max-width: 800px;
    }

    .klytos-hero-gradient h1 {
      font-size: 3.5rem;
      font-weight: 700;
      margin: 0 0 20px 0;
      line-height: 1.2;
    }

    .klytos-hero-gradient p {
      font-size: 1.25rem;
      margin: 0 0 40px 0;
      opacity: 0.95;
      line-height: 1.6;
    }

    .klytos-hero-gradient-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .klytos-hero-gradient a {
      padding: 15px 40px;
      font-size: 1rem;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      display: inline-block;
      border: 2px solid transparent;
    }

    .klytos-hero-gradient-primary {
      background: white;
      color: var(--klytos-primary);
    }

    .klytos-hero-gradient-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    .klytos-hero-gradient-outline {
      background: transparent;
      color: white;
      border: 2px solid white;
    }

    .klytos-hero-gradient-outline:hover {
      background: white;
      color: var(--klytos-primary);
      transform: translateY(-3px);
    }

    @media (max-width: 768px) {
      .klytos-hero-gradient {
        padding: 60px 20px;
        min-height: 400px;
      }

      .klytos-hero-gradient h1 {
        font-size: 2.25rem;
      }

      .klytos-hero-gradient p {
        font-size: 1rem;
      }
    }
  </style>

  <div class="klytos-hero-gradient-content">
    <h1>Build Amazing Websites Fast</h1>
    <p>Create stunning, high-converting pages without touching code. Drag, drop, and deploy in minutes.</p>
    <div class="klytos-hero-gradient-buttons">
      <a href="#" class="klytos-hero-gradient-primary">Get Started Free</a>
      <a href="#" class="klytos-hero-gradient-outline">Watch Demo</a>
    </div>
  </div>
</div>
<!-- /wp:html -->

---

## Pattern 2: Hero Section — Background Image with Overlay

Use this when you have a background image and want to display content on top with a dark overlay for readability.

<!-- wp:html -->
<div class="klytos-hero-image">
  <style>
    .klytos-hero-image {
      background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                  url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%23f0f0f0" width="1200" height="600"/><circle cx="200" cy="150" r="80" fill="%23e0e0e0"/><circle cx="1000" cy="450" r="120" fill="%23e0e0e0"/></svg>');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      min-height: 550px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      text-align: center;
      padding: 60px 20px;
      position: relative;
    }

    .klytos-hero-image-content {
      max-width: 700px;
      z-index: 2;
      position: relative;
    }

    .klytos-hero-image h1 {
      font-size: 3.25rem;
      font-weight: 700;
      margin: 0 0 20px 0;
      line-height: 1.2;
    }

    .klytos-hero-image p {
      font-size: 1.125rem;
      margin: 0 0 35px 0;
      opacity: 0.9;
      line-height: 1.6;
    }

    .klytos-hero-image-cta {
      display: inline-block;
      background: var(--klytos-primary);
      color: white;
      padding: 15px 45px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
      font-size: 1rem;
    }

    .klytos-hero-image-cta:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
    }

    @media (max-width: 768px) {
      .klytos-hero-image {
        min-height: 400px;
        background-attachment: scroll;
      }

      .klytos-hero-image h1 {
        font-size: 2rem;
      }

      .klytos-hero-image p {
        font-size: 1rem;
      }
    }
  </style>

  <div class="klytos-hero-image-content">
    <h1>Transform Your Business Today</h1>
    <p>Join thousands of companies using our platform to drive growth and efficiency.</p>
    <button class="klytos-hero-image-cta">Start Free Trial</button>
  </div>
</div>
<!-- /wp:html -->

---

## Pattern 3: Product/Pricing Cards Grid

Use this for displaying products, pricing tiers, or service offerings in a responsive 3-column grid with cards that have badges, pricing, features, and CTAs.

<!-- wp:html -->
<div class="klytos-pricing-section">
  <style>
    .klytos-pricing-section {
      padding: 60px 20px;
      background: var(--klytos-background);
    }

    .klytos-pricing-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .klytos-pricing-header {
      text-align: center;
      margin-bottom: 50px;
    }

    .klytos-pricing-header h2 {
      font-size: 2.5rem;
      font-weight: 700;
      margin: 0 0 15px 0;
      color: var(--klytos-text);
    }

    .klytos-pricing-header p {
      font-size: 1.125rem;
      color: var(--klytos-text-muted);
      margin: 0;
    }

    .klytos-pricing-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 30px;
    }

    .klytos-pricing-card {
      background: var(--klytos-surface);
      border-radius: 12px;
      padding: 40px 30px;
      position: relative;
      border: 1px solid var(--klytos-border);
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
    }

    .klytos-pricing-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
      border-color: var(--klytos-primary);
    }

    .klytos-pricing-card.featured {
      border: 2px solid var(--klytos-primary);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    .klytos-pricing-badge {
      position: absolute;
      top: -15px;
      left: 20px;
      background: var(--klytos-primary);
      color: white;
      padding: 6px 16px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .klytos-pricing-name {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--klytos-text);
      margin: 10px 0 0 0;
    }

    .klytos-pricing-price {
      font-size: 3rem;
      font-weight: 700;
      color: var(--klytos-primary);
      margin: 20px 0 10px 0;
    }

    .klytos-pricing-period {
      color: var(--klytos-text-muted);
      font-size: 1rem;
      margin-bottom: 25px;
    }

    .klytos-pricing-description {
      color: var(--klytos-text-muted);
      font-size: 0.95rem;
      margin-bottom: 30px;
      flex-grow: 1;
    }

    .klytos-pricing-features {
      list-style: none;
      padding: 0;
      margin: 0 0 30px 0;
    }

    .klytos-pricing-features li {
      padding: 12px 0;
      border-bottom: 1px solid var(--klytos-border);
      color: var(--klytos-text);
      font-size: 0.95rem;
      display: flex;
      align-items: center;
    }

    .klytos-pricing-features li:last-child {
      border-bottom: none;
    }

    .klytos-pricing-features li::before {
      content: '✓';
      color: var(--klytos-primary);
      font-weight: 700;
      margin-right: 12px;
      font-size: 1.2rem;
    }

    .klytos-pricing-cta {
      display: block;
      width: 100%;
      padding: 14px 20px;
      background: var(--klytos-primary);
      color: white;
      text-align: center;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
      font-size: 1rem;
    }

    .klytos-pricing-cta:hover {
      background: var(--klytos-secondary);
      transform: translateY(-2px);
    }

    .klytos-pricing-card.outline .klytos-pricing-cta {
      background: transparent;
      border: 2px solid var(--klytos-primary);
      color: var(--klytos-primary);
    }

    .klytos-pricing-card.outline .klytos-pricing-cta:hover {
      background: var(--klytos-primary);
      color: white;
    }

    @media (max-width: 768px) {
      .klytos-pricing-header h2 {
        font-size: 2rem;
      }

      .klytos-pricing-grid {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .klytos-pricing-price {
        font-size: 2.25rem;
      }
    }
  </style>

  <div class="klytos-pricing-container">
    <div class="klytos-pricing-header">
      <h2>Simple, Transparent Pricing</h2>
      <p>Choose the perfect plan for your needs</p>
    </div>

    <div class="klytos-pricing-grid">
      <div class="klytos-pricing-card outline">
        <div class="klytos-pricing-name">Starter</div>
        <div class="klytos-pricing-price">$29</div>
        <div class="klytos-pricing-period">per month</div>
        <p class="klytos-pricing-description">Perfect for individuals and small projects</p>
        <ul class="klytos-pricing-features">
          <li>Up to 5 projects</li>
          <li>10 GB storage</li>
          <li>Email support</li>
          <li>Basic analytics</li>
        </ul>
        <button class="klytos-pricing-cta">Get Started</button>
      </div>

      <div class="klytos-pricing-card featured">
        <div class="klytos-pricing-badge">Most Popular</div>
        <div class="klytos-pricing-name">Professional</div>
        <div class="klytos-pricing-price">$99</div>
        <div class="klytos-pricing-period">per month</div>
        <p class="klytos-pricing-description">Ideal for growing teams and agencies</p>
        <ul class="klytos-pricing-features">
          <li>Unlimited projects</li>
          <li>500 GB storage</li>
          <li>Priority support</li>
          <li>Advanced analytics</li>
          <li>Team collaboration</li>
        </ul>
        <button class="klytos-pricing-cta">Start Free Trial</button>
      </div>

      <div class="klytos-pricing-card outline">
        <div class="klytos-pricing-name">Enterprise</div>
        <div class="klytos-pricing-price">Custom</div>
        <div class="klytos-pricing-period">per month</div>
        <p class="klytos-pricing-description">For large organizations with unique needs</p>
        <ul class="klytos-pricing-features">
          <li>Everything in Professional</li>
          <li>Unlimited storage</li>
          <li>24/7 phone support</li>
          <li>Custom integrations</li>
          <li>SLA guarantee</li>
        </ul>
        <button class="klytos-pricing-cta">Contact Sales</button>
      </div>
    </div>
  </div>
</div>
<!-- /wp:html -->

---

## Pattern 4: Feature Grid with Icons

Use this pattern to showcase key features or capabilities in a clean grid layout with icon placeholders, headings, and descriptions.

<!-- wp:html -->
<div class="klytos-features-section">
  <style>
    .klytos-features-section {
      padding: 80px 20px;
      background: var(--klytos-background);
    }

    .klytos-features-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .klytos-features-header {
      text-align: center;
      margin-bottom: 60px;
    }

    .klytos-features-header h2 {
      font-size: 2.5rem;
      font-weight: 700;
      margin: 0 0 15px 0;
      color: var(--klytos-text);
    }

    .klytos-features-header p {
      font-size: 1.125rem;
      color: var(--klytos-text-muted);
      margin: 0;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }

    .klytos-features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 40px;
    }

    .klytos-feature-item {
      text-align: center;
      transition: all 0.3s ease;
    }

    .klytos-feature-item:hover {
      transform: translateY(-5px);
    }

    .klytos-feature-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, var(--klytos-primary), var(--klytos-secondary));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      font-size: 2.5rem;
      color: white;
    }

    .klytos-feature-item h3 {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--klytos-text);
      margin: 0 0 12px 0;
    }

    .klytos-feature-item p {
      color: var(--klytos-text-muted);
      font-size: 0.95rem;
      line-height: 1.6;
      margin: 0;
    }

    @media (max-width: 768px) {
      .klytos-features-section {
        padding: 60px 20px;
      }

      .klytos-features-header h2 {
        font-size: 2rem;
      }

      .klytos-features-grid {
        gap: 30px;
      }
    }
  </style>

  <div class="klytos-features-container">
    <div class="klytos-features-header">
      <h2>Powerful Features</h2>
      <p>Everything you need to build, manage, and scale your online presence</p>
    </div>

    <div class="klytos-features-grid">
      <div class="klytos-feature-item">
        <div class="klytos-feature-icon">⚡</div>
        <h3>Lightning Fast</h3>
        <p>Optimized performance with millisecond response times and global CDN coverage</p>
      </div>

      <div class="klytos-feature-item">
        <div class="klytos-feature-icon">🔒</div>
        <h3>Enterprise Security</h3>
        <p>Bank-level encryption and compliance with industry standards and regulations</p>
      </div>

      <div class="klytos-feature-item">
        <div class="klytos-feature-icon">📊</div>
        <h3>Advanced Analytics</h3>
        <p>Real-time insights and detailed reports to track performance and user behavior</p>
      </div>

      <div class="klytos-feature-item">
        <div class="klytos-feature-icon">🔧</div>
        <h3>Easy Integration</h3>
        <p>Connect with your favorite tools through APIs and pre-built integrations</p>
      </div>

      <div class="klytos-feature-item">
        <div class="klytos-feature-icon">👥</div>
        <h3>Team Collaboration</h3>
        <p>Work together seamlessly with role-based access and real-time collaboration features</p>
      </div>

      <div class="klytos-feature-item">
        <div class="klytos-feature-icon">🌍</div>
        <h3>Global Scale</h3>
        <p>Reach customers worldwide with multi-region deployment and localization support</p>
      </div>
    </div>
  </div>
</div>
<!-- /wp:html -->

---

## Pattern 5: Testimonials Section

Use this pattern to showcase customer testimonials with star ratings, quotes, author names, roles, and optional avatar placeholders.

<!-- wp:html -->
<div class="klytos-testimonials-section">
  <style>
    .klytos-testimonials-section {
      padding: 80px 20px;
      background: var(--klytos-surface);
    }

    .klytos-testimonials-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .klytos-testimonials-header {
      text-align: center;
      margin-bottom: 60px;
    }

    .klytos-testimonials-header h2 {
      font-size: 2.5rem;
      font-weight: 700;
      margin: 0 0 15px 0;
      color: var(--klytos-text);
    }

    .klytos-testimonials-header p {
      font-size: 1.125rem;
      color: var(--klytos-text-muted);
      margin: 0;
    }

    .klytos-testimonials-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 30px;
    }

    .klytos-testimonial-card {
      background: var(--klytos-background);
      border-radius: 12px;
      padding: 30px;
      border: 1px solid var(--klytos-border);
      transition: all 0.3s ease;
    }

    .klytos-testimonial-card:hover {
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
      border-color: var(--klytos-primary);
    }

    .klytos-testimonial-stars {
      display: flex;
      gap: 5px;
      margin-bottom: 15px;
    }

    .klytos-testimonial-stars svg {
      width: 18px;
      height: 18px;
      fill: #fbbf24;
    }

    .klytos-testimonial-quote {
      font-size: 1rem;
      color: var(--klytos-text);
      font-style: italic;
      margin: 0 0 25px 0;
      line-height: 1.6;
    }

    .klytos-testimonial-author-section {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .klytos-testimonial-avatar {
      width: 48px;
      height: 48px;
      background: linear-gradient(135deg, var(--klytos-primary), var(--klytos-secondary));
      border-radius: 50%;
      flex-shrink: 0;
    }

    .klytos-testimonial-author-info h4 {
      font-size: 1rem;
      font-weight: 600;
      color: var(--klytos-text);
      margin: 0 0 4px 0;
    }

    .klytos-testimonial-author-info p {
      font-size: 0.85rem;
      color: var(--klytos-text-muted);
      margin: 0;
    }

    @media (max-width: 768px) {
      .klytos-testimonials-section {
        padding: 60px 20px;
      }

      .klytos-testimonials-header h2 {
        font-size: 2rem;
      }

      .klytos-testimonials-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <div class="klytos-testimonials-container">
    <div class="klytos-testimonials-header">
      <h2>What Our Customers Say</h2>
      <p>Join hundreds of satisfied users</p>
    </div>

    <div class="klytos-testimonials-grid">
      <div class="klytos-testimonial-card">
        <div class="klytos-testimonial-stars">
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </div>
        <p class="klytos-testimonial-quote">"This platform completely transformed how we manage our projects. The interface is intuitive and the support team is outstanding."</p>
        <div class="klytos-testimonial-author-section">
          <div class="klytos-testimonial-avatar"></div>
          <div class="klytos-testimonial-author-info">
            <h4>Sarah Anderson</h4>
            <p>CEO, TechVision Inc</p>
          </div>
        </div>
      </div>

      <div class="klytos-testimonial-card">
        <div class="klytos-testimonial-stars">
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </div>
        <p class="klytos-testimonial-quote">"The best investment we made for our team. Productivity increased by 40% in the first month alone."</p>
        <div class="klytos-testimonial-author-section">
          <div class="klytos-testimonial-avatar"></div>
          <div class="klytos-testimonial-author-info">
            <h4>Michael Chen</h4>
            <p>Product Manager, Growth Labs</p>
          </div>
        </div>
      </div>

      <div class="klytos-testimonial-card">
        <div class="klytos-testimonial-stars">
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </div>
        <p class="klytos-testimonial-quote">"Excellent platform with exceptional customer service. They truly care about their users' success."</p>
        <div class="klytos-testimonial-author-section">
          <div class="klytos-testimonial-avatar"></div>
          <div class="klytos-testimonial-author-info">
            <h4>Emily Rodriguez</h4>
            <p>Founder, Creative Studio</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- /wp:html -->

---

## Pattern 6: Statistics/Counter Section

Use this pattern to showcase key metrics, achievements, or impressive numbers that build credibility.

<!-- wp:html -->
<div class="klytos-stats-section">
  <style>
    .klytos-stats-section {
      background: linear-gradient(135deg, var(--klytos-primary), var(--klytos-secondary));
      padding: 80px 20px;
      color: white;
    }

    .klytos-stats-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .klytos-stats-header {
      text-align: center;
      margin-bottom: 60px;
    }

    .klytos-stats-header h2 {
      font-size: 2.5rem;
      font-weight: 700;
      margin: 0 0 15px 0;
    }

    .klytos-stats-header p {
      font-size: 1.125rem;
      margin: 0;
      opacity: 0.95;
    }

    .klytos-stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 40px;
      text-align: center;
    }

    .klytos-stat-item h3 {
      font-size: 3.5rem;
      font-weight: 700;
      margin: 0 0 10px 0;
      line-height: 1;
    }

    .klytos-stat-item p {
      font-size: 1.125rem;
      margin: 0;
      opacity: 0.9;
    }

    @media (max-width: 768px) {
      .klytos-stats-section {
        padding: 60px 20px;
      }

      .klytos-stats-header h2 {
        font-size: 2rem;
      }

      .klytos-stats-grid {
        gap: 30px;
      }

      .klytos-stat-item h3 {
        font-size: 2.5rem;
      }
    }
  </style>

  <div class="klytos-stats-container">
    <div class="klytos-stats-header">
      <h2>By The Numbers</h2>
      <p>Our impact and growth speak for themselves</p>
    </div>

    <div class="klytos-stats-grid">
      <div class="klytos-stat-item">
        <h3>50K+</h3>
        <p>Active Users</p>
      </div>

      <div class="klytos-stat-item">
        <h3>150M+</h3>
        <p>Projects Completed</p>
      </div>

      <div class="klytos-stat-item">
        <h3>99.9%</h3>
        <p>Uptime Guarantee</p>
      </div>

      <div class="klytos-stat-item">
        <h3>24/7</h3>
        <p>Customer Support</p>
      </div>
    </div>
  </div>
</div>
<!-- /wp:html -->

---

## Pattern 7: Newsletter Signup Section

Use this pattern to drive email signups with a focused, visually appealing form section.

<!-- wp:html -->
<div class="klytos-newsletter-section">
  <style>
    .klytos-newsletter-section {
      padding: 80px 20px;
      background: var(--klytos-background);
    }

    .klytos-newsletter-container {
      max-width: 600px;
      margin: 0 auto;
      text-align: center;
    }

    .klytos-newsletter-header h2 {
      font-size: 2.25rem;
      font-weight: 700;
      color: var(--klytos-text);
      margin: 0 0 15px 0;
    }

    .klytos-newsletter-header p {
      font-size: 1.125rem;
      color: var(--klytos-text-muted);
      margin: 0 0 35px 0;
      line-height: 1.6;
    }

    .klytos-newsletter-form {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .klytos-newsletter-form input {
      flex: 1;
      min-width: 200px;
      padding: 14px 20px;
      border: 1px solid var(--klytos-border);
      border-radius: 6px;
      font-size: 1rem;
      background: var(--klytos-surface);
      color: var(--klytos-text);
      transition: all 0.3s ease;
    }

    .klytos-newsletter-form input:focus {
      outline: none;
      border-color: var(--klytos-primary);
      box-shadow: 0 0 0 3px rgba(var(--klytos-primary), 0.1);
    }

    .klytos-newsletter-form input::placeholder {
      color: var(--klytos-text-muted);
    }

    .klytos-newsletter-form button {
      padding: 14px 35px;
      background: var(--klytos-primary);
      color: white;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      white-space: nowrap;
    }

    .klytos-newsletter-form button:hover {
      background: var(--klytos-secondary);
      transform: translateY(-2px);
    }

    .klytos-newsletter-note {
      font-size: 0.85rem;
      color: var(--klytos-text-muted);
      margin: 0;
    }

    @media (max-width: 768px) {
      .klytos-newsletter-section {
        padding: 60px 20px;
      }

      .klytos-newsletter-header h2 {
        font-size: 1.75rem;
      }

      .klytos-newsletter-form {
        flex-direction: column;
      }

      .klytos-newsletter-form input,
      .klytos-newsletter-form button {
        width: 100%;
      }
    }
  </style>

  <div class="klytos-newsletter-container">
    <div class="klytos-newsletter-header">
      <h2>Stay Updated</h2>
      <p>Get the latest news, tips, and exclusive offers delivered to your inbox</p>
    </div>

    <form class="klytos-newsletter-form">
      <input type="email" placeholder="Enter your email address" required>
      <button type="submit">Subscribe</button>
    </form>

    <p class="klytos-newsletter-note">We respect your privacy. Unsubscribe at any time.</p>
  </div>
</div>
<!-- /wp:html -->

---

## Pattern 8: Comparison Table

Use this pattern to display feature comparisons between different tiers, products, or services.

<!-- wp:html -->
<div class="klytos-comparison-section">
  <style>
    .klytos-comparison-section {
      padding: 80px 20px;
      background: var(--klytos-background);
    }

    .klytos-comparison-container {
      max-width: 1100px;
      margin: 0 auto;
    }

    .klytos-comparison-header {
      text-align: center;
      margin-bottom: 50px;
    }

    .klytos-comparison-header h2 {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--klytos-text);
      margin: 0 0 15px 0;
    }

    .klytos-comparison-header p {
      font-size: 1.125rem;
      color: var(--klytos-text-muted);
      margin: 0;
    }

    .klytos-comparison-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--klytos-surface);
      border: 1px solid var(--klytos-border);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .klytos-comparison-table thead {
      background: var(--klytos-surface);
    }

    .klytos-comparison-table th {
      padding: 25px 20px;
      text-align: left;
      font-weight: 600;
      color: var(--klytos-text);
      border-bottom: 2px solid var(--klytos-border);
      font-size: 1rem;
    }

    .klytos-comparison-table th:first-child {
      width: 30%;
    }

    .klytos-comparison-table th:not(:first-child) {
      text-align: center;
    }

    .klytos-comparison-table td {
      padding: 20px;
      border-bottom: 1px solid var(--klytos-border);
      color: var(--klytos-text);
      font-size: 0.95rem;
    }

    .klytos-comparison-table tbody tr:hover {
      background: rgba(var(--klytos-primary), 0.02);
    }

    .klytos-comparison-table tbody tr:last-child td {
      border-bottom: none;
    }

    .klytos-comparison-table td:not(:first-child) {
      text-align: center;
    }

    .klytos-comparison-check {
      color: #10b981;
      font-weight: 700;
      font-size: 1.25rem;
    }

    .klytos-comparison-cross {
      color: #ef4444;
      font-weight: 700;
      font-size: 1.25rem;
    }

    .klytos-comparison-badge {
      display: inline-block;
      background: var(--klytos-primary);
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      margin-left: 8px;
    }

    .klytos-comparison-cta {
      padding: 12px 28px;
      background: var(--klytos-primary);
      color: white;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .klytos-comparison-cta:hover {
      background: var(--klytos-secondary);
    }

    @media (max-width: 768px) {
      .klytos-comparison-section {
        padding: 60px 20px;
      }

      .klytos-comparison-header h2 {
        font-size: 2rem;
      }

      .klytos-comparison-table {
        font-size: 0.85rem;
      }

      .klytos-comparison-table th,
      .klytos-comparison-table td {
        padding: 15px 10px;
      }

      .klytos-comparison-table th:first-child {
        width: 40%;
      }

      .klytos-comparison-badge {
        display: block;
        margin: 8px 0 0 0;
      }
    }
  </style>

  <div class="klytos-comparison-container">
    <div class="klytos-comparison-header">
      <h2>Plan Comparison</h2>
      <p>Choose the features that work best for you</p>
    </div>

    <table class="klytos-comparison-table">
      <thead>
        <tr>
          <th>Feature</th>
          <th>Starter</th>
          <th>Professional<span class="klytos-comparison-badge">Popular</span></th>
          <th>Enterprise</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Projects</td>
          <td><span class="klytos-comparison-check">5</span></td>
          <td><span class="klytos-comparison-check">Unlimited</span></td>
          <td><span class="klytos-comparison-check">Unlimited</span></td>
        </tr>
        <tr>
          <td>Storage</td>
          <td><span class="klytos-comparison-check">10GB</span></td>
          <td><span class="klytos-comparison-check">500GB</span></td>
          <td><span class="klytos-comparison-check">Unlimited</span></td>
        </tr>
        <tr>
          <td>Team Members</td>
          <td><span class="klytos-comparison-check">3</span></td>
          <td><span class="klytos-comparison-check">Unlimited</span></td>
          <td><span class="klytos-comparison-check">Unlimited</span></td>
        </tr>
        <tr>
          <td>Advanced Analytics</td>
          <td><span class="klytos-comparison-cross">X</span></td>
          <td><span class="klytos-comparison-check">Yes</span></td>
          <td><span class="klytos-comparison-check">Yes</span></td>
        </tr>
        <tr>
          <td>API Access</td>
          <td><span class="klytos-comparison-cross">X</span></td>
          <td><span class="klytos-comparison-check">Yes</span></td>
          <td><span class="klytos-comparison-check">Yes</span></td>
        </tr>
        <tr>
          <td>Priority Support</td>
          <td><span class="klytos-comparison-cross">X</span></td>
          <td><span class="klytos-comparison-check">Yes</span></td>
          <td><span class="klytos-comparison-check">Yes</span></td>
        </tr>
        <tr>
          <td>Custom Integrations</td>
          <td><span class="klytos-comparison-cross">X</span></td>
          <td><span class="klytos-comparison-cross">X</span></td>
          <td><span class="klytos-comparison-check">Yes</span></td>
        </tr>
        <tr>
          <td>SLA Guarantee</td>
          <td><span class="klytos-comparison-cross">X</span></td>
          <td><span class="klytos-comparison-cross">X</span></td>
          <td><span class="klytos-comparison-check">99.9%</span></td>
        </tr>
        <tr>
          <td></td>
          <td><button class="klytos-comparison-cta">Get Started</button></td>
          <td><button class="klytos-comparison-cta">Start Free Trial</button></td>
          <td><button class="klytos-comparison-cta">Contact Sales</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<!-- /wp:html -->

---

## Pattern 9: Team Grid

Use this pattern to showcase team members with avatars, names, roles, and social links.

<!-- wp:html -->
<div class="klytos-team-section">
  <style>
    .klytos-team-section {
      padding: 80px 20px;
      background: var(--klytos-background);
    }

    .klytos-team-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .klytos-team-header {
      text-align: center;
      margin-bottom: 60px;
    }

    .klytos-team-header h2 {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--klytos-text);
      margin: 0 0 15px 0;
    }

    .klytos-team-header p {
      font-size: 1.125rem;
      color: var(--klytos-text-muted);
      margin: 0;
    }

    .klytos-team-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 40px;
    }

    .klytos-team-member {
      text-align: center;
      transition: all 0.3s ease;
    }

    .klytos-team-member:hover {
      transform: translateY(-10px);
    }

    .klytos-team-avatar {
      width: 150px;
      height: 150px;
      background: linear-gradient(135deg, var(--klytos-primary), var(--klytos-secondary));
      border-radius: 12px;
      margin: 0 auto 20px;
      overflow: hidden;
    }

    .klytos-team-member h3 {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--klytos-text);
      margin: 0 0 5px 0;
    }

    .klytos-team-member p {
      color: var(--klytos-text-muted);
      font-size: 0.95rem;
      margin: 0 0 15px 0;
    }

    .klytos-team-social {
      display: flex;
      justify-content: center;
      gap: 12px;
    }

    .klytos-team-social a {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      background: var(--klytos-surface);
      border-radius: 50%;
      color: var(--klytos-primary);
      text-decoration: none;
      transition: all 0.3s ease;
      border: 1px solid var(--klytos-border);
    }

    .klytos-team-social a:hover {
      background: var(--klytos-primary);
      color: white;
      border-color: var(--klytos-primary);
    }

    @media (max-width: 768px) {
      .klytos-team-section {
        padding: 60px 20px;
      }

      .klytos-team-header h2 {
        font-size: 2rem;
      }

      .klytos-team-grid {
        gap: 30px;
      }
    }
  </style>

  <div class="klytos-team-container">
    <div class="klytos-team-header">
      <h2>Meet Our Team</h2>
      <p>Talented professionals dedicated to your success</p>
    </div>

    <div class="klytos-team-grid">
      <div class="klytos-team-member">
        <div class="klytos-team-avatar"></div>
        <h3>Sarah Johnson</h3>
        <p>Chief Executive Officer</p>
        <div class="klytos-team-social">
          <a href="#" title="LinkedIn">in</a>
          <a href="#" title="Twitter">x</a>
          <a href="#" title="Email">@</a>
        </div>
      </div>

      <div class="klytos-team-member">
        <div class="klytos-team-avatar"></div>
        <h3>Michael Torres</h3>
        <p>Head of Engineering</p>
        <div class="klytos-team-social">
          <a href="#" title="LinkedIn">in</a>
          <a href="#" title="Twitter">x</a>
          <a href="#" title="GitHub">gh</a>
        </div>
      </div>

      <div class="klytos-team-member">
        <div class="klytos-team-avatar"></div>
        <h3>Emma Williams</h3>
        <p>Chief Design Officer</p>
        <div class="klytos-team-social">
          <a href="#" title="LinkedIn">in</a>
          <a href="#" title="Twitter">x</a>
          <a href="#" title="Portfolio">www</a>
        </div>
      </div>

      <div class="klytos-team-member">
        <div class="klytos-team-avatar"></div>
        <h3>David Kumar</h3>
        <p>VP of Product</p>
        <div class="klytos-team-social">
          <a href="#" title="LinkedIn">in</a>
          <a href="#" title="Twitter">x</a>
          <a href="#" title="Email">@</a>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- /wp:html -->

---

## Pattern 10: Blog Post Cards Grid

Use this pattern to display blog posts or articles in an attractive grid with images, categories, titles, excerpts, and metadata.

<!-- wp:html -->
<div class="klytos-blog-section">
  <style>
    .klytos-blog-section {
      padding: 80px 20px;
      background: var(--klytos-background);
    }

    .klytos-blog-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .klytos-blog-header {
      text-align: center;
      margin-bottom: 60px;
    }

    .klytos-blog-header h2 {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--klytos-text);
      margin: 0 0 15px 0;
    }

    .klytos-blog-header p {
      font-size: 1.125rem;
      color: var(--klytos-text-muted);
      margin: 0;
    }

    .klytos-blog-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 30px;
    }

    .klytos-blog-card {
      background: var(--klytos-surface);
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid var(--klytos-border);
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
    }

    .klytos-blog-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
      border-color: var(--klytos-primary);
    }

    .klytos-blog-image {
      width: 100%;
      height: 200px;
      background: linear-gradient(135deg, var(--klytos-primary), var(--klytos-secondary));
      position: relative;
      overflow: hidden;
    }

    .klytos-blog-category {
      position: absolute;
      top: 15px;
      left: 15px;
      background: var(--klytos-primary);
      color: white;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .klytos-blog-content {
      padding: 25px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .klytos-blog-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--klytos-text);
      margin: 0 0 12px 0;
      line-height: 1.4;
    }

    .klytos-blog-excerpt {
      color: var(--klytos-text-muted);
      font-size: 0.95rem;
      margin: 0 0 20px 0;
      line-height: 1.6;
      flex-grow: 1;
    }

    .klytos-blog-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 15px;
      border-top: 1px solid var(--klytos-border);
      font-size: 0.85rem;
      color: var(--klytos-text-muted);
    }

    .klytos-blog-author {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .klytos-blog-avatar {
      width: 24px;
      height: 24px;
      background: var(--klytos-primary);
      border-radius: 50%;
    }

    @media (max-width: 768px) {
      .klytos-blog-section {
        padding: 60px 20px;
      }

      .klytos-blog-header h2 {
        font-size: 2rem;
      }

      .klytos-blog-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <div class="klytos-blog-container">
    <div class="klytos-blog-header">
      <h2>Latest Articles</h2>
      <p>Insights, tips, and best practices</p>
    </div>

    <div class="klytos-blog-grid">
      <div class="klytos-blog-card">
        <div class="klytos-blog-image">
          <span class="klytos-blog-category">Design</span>
        </div>
        <div class="klytos-blog-content">
          <h3 class="klytos-blog-title">10 Web Design Trends for 2026</h3>
          <p class="klytos-blog-excerpt">Discover the latest design trends that will shape the digital landscape. From minimalism to interactive elements, here's what's trending.</p>
          <div class="klytos-blog-meta">
            <span class="klytos-blog-author">
              <span class="klytos-blog-avatar"></span>
              Alex Chen
            </span>
            <span>Mar 15, 2026</span>
          </div>
        </div>
      </div>

      <div class="klytos-blog-card">
        <div class="klytos-blog-image">
          <span class="klytos-blog-category">Development</span>
        </div>
        <div class="klytos-blog-content">
          <h3 class="klytos-blog-title">Optimizing Web Performance for Better UX</h3>
          <p class="klytos-blog-excerpt">Learn proven techniques to improve your website's loading speed and overall performance. Every millisecond matters for conversion rates.</p>
          <div class="klytos-blog-meta">
            <span class="klytos-blog-author">
              <span class="klytos-blog-avatar"></span>
              Jordan Martinez
            </span>
            <span>Mar 10, 2026</span>
          </div>
        </div>
      </div>

      <div class="klytos-blog-card">
        <div class="klytos-blog-image">
          <span class="klytos-blog-category">Business</span>
        </div>
        <div class="klytos-blog-content">
          <h3 class="klytos-blog-title">The ROI of Investing in Website Redesign</h3>
          <p class="klytos-blog-excerpt">A comprehensive analysis of how modernizing your website can directly impact your bottom line. Real case studies included.</p>
          <div class="klytos-blog-meta">
            <span class="klytos-blog-author">
              <span class="klytos-blog-avatar"></span>
              Sam Thompson
            </span>
            <span>Mar 5, 2026</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- /wp:html -->

---

## Pattern 11: Footer — Multi-Column

Use this pattern to create a comprehensive footer with multiple link columns, brand information, and copyright notice.

<!-- wp:html -->
<footer class="klytos-footer">
  <style>
    .klytos-footer {
      background: linear-gradient(180deg, #1a1a1a 0%, #0f0f0f 100%);
      color: #e5e5e5;
      padding: 60px 20px 20px;
    }

    .klytos-footer-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .klytos-footer-content {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 40px;
      margin-bottom: 40px;
    }

    .klytos-footer-brand h3 {
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0 0 12px 0;
      color: white;
    }

    .klytos-footer-brand p {
      color: #a3a3a3;
      font-size: 0.95rem;
      margin: 0;
      line-height: 1.6;
    }

    .klytos-footer-column h4 {
      font-size: 1rem;
      font-weight: 600;
      margin: 0 0 20px 0;
      color: white;
    }

    .klytos-footer-links {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .klytos-footer-links li {
      margin-bottom: 12px;
    }

    .klytos-footer-links a {
      color: #a3a3a3;
      text-decoration: none;
      font-size: 0.95rem;
      transition: color 0.3s ease;
    }

    .klytos-footer-links a:hover {
      color: white;
    }

    .klytos-footer-bottom {
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      padding-top: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }

    .klytos-footer-copyright {
      font-size: 0.85rem;
      color: #a3a3a3;
      margin: 0;
    }

    .klytos-footer-legal {
      display: flex;
      gap: 20px;
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .klytos-footer-legal a {
      color: #a3a3a3;
      text-decoration: none;
      font-size: 0.85rem;
      transition: color 0.3s ease;
    }

    .klytos-footer-legal a:hover {
      color: white;
    }

    @media (max-width: 768px) {
      .klytos-footer {
        padding: 40px 20px;
      }

      .klytos-footer-content {
        gap: 30px;
      }

      .klytos-footer-bottom {
        flex-direction: column;
        text-align: center;
      }

      .klytos-footer-legal {
        flex-wrap: wrap;
        justify-content: center;
      }
    }
  </style>

  <div class="klytos-footer-container">
    <div class="klytos-footer-content">
      <div class="klytos-footer-brand">
        <h3>YourBrand</h3>
        <p>Building amazing web experiences that drive results. We're passionate about helping businesses succeed online.</p>
      </div>

      <div class="klytos-footer-column">
        <h4>Product</h4>
        <ul class="klytos-footer-links">
          <li><a href="#">Features</a></li>
          <li><a href="#">Pricing</a></li>
          <li><a href="#">Security</a></li>
          <li><a href="#">Roadmap</a></li>
          <li><a href="#">Updates</a></li>
        </ul>
      </div>

      <div class="klytos-footer-column">
        <h4>Company</h4>
        <ul class="klytos-footer-links">
          <li><a href="#">About Us</a></li>
          <li><a href="#">Blog</a></li>
          <li><a href="#">Careers</a></li>
          <li><a href="#">Media Kit</a></li>
          <li><a href="#">Contact</a></li>
        </ul>
      </div>

      <div class="klytos-footer-column">
        <h4>Resources</h4>
        <ul class="klytos-footer-links">
          <li><a href="#">Documentation</a></li>
          <li><a href="#">API Docs</a></li>
          <li><a href="#">Community</a></li>
          <li><a href="#">Support</a></li>
          <li><a href="#">Status</a></li>
        </ul>
      </div>
    </div>

    <div class="klytos-footer-bottom">
      <p class="klytos-footer-copyright">&copy; 2026 YourBrand. All rights reserved.</p>
      <ul class="klytos-footer-legal">
        <li><a href="#">Privacy Policy</a></li>
        <li><a href="#">Terms of Service</a></li>
        <li><a href="#">Cookie Settings</a></li>
      </ul>
    </div>
  </div>
</footer>
<!-- /wp:html -->

---

## Pattern 12: Call to Action — Full Width

Use this pattern to create a prominent, full-width CTA section that encourages action.

<!-- wp:html -->
<div class="klytos-cta-section">
  <style>
    .klytos-cta-section {
      background: linear-gradient(135deg, var(--klytos-primary) 0%, var(--klytos-accent) 100%);
      padding: 100px 20px;
      color: white;
      text-align: center;
    }

    .klytos-cta-container {
      max-width: 900px;
      margin: 0 auto;
    }

    .klytos-cta-section h2 {
      font-size: 3rem;
      font-weight: 700;
      margin: 0 0 20px 0;
      line-height: 1.2;
    }

    .klytos-cta-section p {
      font-size: 1.25rem;
      margin: 0 0 35px 0;
      opacity: 0.95;
      line-height: 1.6;
    }

    .klytos-cta-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .klytos-cta-btn {
      padding: 16px 40px;
      border: 2px solid white;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
      cursor: pointer;
      display: inline-block;
    }

    .klytos-cta-btn-primary {
      background: white;
      color: var(--klytos-primary);
    }

    .klytos-cta-btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
    }

    .klytos-cta-btn-secondary {
      background: transparent;
      color: white;
    }

    .klytos-cta-btn-secondary:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateY(-3px);
    }

    @media (max-width: 768px) {
      .klytos-cta-section {
        padding: 80px 20px;
      }

      .klytos-cta-section h2 {
        font-size: 2rem;
      }

      .klytos-cta-section p {
        font-size: 1rem;
      }

      .klytos-cta-buttons {
        flex-direction: column;
      }

      .klytos-cta-btn {
        width: 100%;
      }
    }
  </style>

  <div class="klytos-cta-container">
    <h2>Ready to Get Started?</h2>
    <p>Join thousands of successful businesses using our platform. Start free today, no credit card required.</p>
    <div class="klytos-cta-buttons">
      <a href="#" class="klytos-cta-btn klytos-cta-btn-primary">Start Free Trial</a>
      <a href="#" class="klytos-cta-btn klytos-cta-btn-secondary">Schedule a Demo</a>
    </div>
  </div>
</div>
<!-- /wp:html -->

---

## Pattern 13: Image + Text Side by Side (Alternating)

Use this pattern to create alternating image-left/text-right and image-right/text-left layouts with responsive stacking.

<!-- wp:html -->
<div class="klytos-image-text-section">
  <style>
    .klytos-image-text-section {
      padding: 80px 20px;
      background: var(--klytos-background);
    }

    .klytos-image-text-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .klytos-image-text-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
      margin-bottom: 80px;
    }

    .klytos-image-text-row:last-child {
      margin-bottom: 0;
    }

    .klytos-image-text-row.reverse {
      direction: rtl;
    }

    .klytos-image-text-row.reverse > * {
      direction: ltr;
    }

    .klytos-image-text-image {
      width: 100%;
      height: 400px;
      background: linear-gradient(135deg, var(--klytos-primary), var(--klytos-secondary));
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    .klytos-image-text-content h2 {
      font-size: 2.25rem;
      font-weight: 700;
      color: var(--klytos-text);
      margin: 0 0 20px 0;
      line-height: 1.2;
    }

    .klytos-image-text-content p {
      font-size: 1.0625rem;
      color: var(--klytos-text-muted);
      margin: 0 0 25px 0;
      line-height: 1.7;
    }

    .klytos-image-text-list {
      list-style: none;
      padding: 0;
      margin: 0 0 30px 0;
    }

    .klytos-image-text-list li {
      padding: 10px 0;
      color: var(--klytos-text);
      font-size: 1rem;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .klytos-image-text-list li::before {
      content: '✓';
      color: var(--klytos-primary);
      font-weight: 700;
      font-size: 1.25rem;
      flex-shrink: 0;
    }

    .klytos-image-text-cta {
      display: inline-block;
      background: var(--klytos-primary);
      color: white;
      padding: 14px 35px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
      font-size: 1rem;
    }

    .klytos-image-text-cta:hover {
      background: var(--klytos-secondary);
      transform: translateY(-3px);
    }

    @media (max-width: 768px) {
      .klytos-image-text-section {
        padding: 60px 20px;
      }

      .klytos-image-text-row {
        grid-template-columns: 1fr;
        gap: 30px;
        margin-bottom: 60px;
      }

      .klytos-image-text-row.reverse {
        direction: ltr;
      }

      .klytos-image-text-image {
        height: 300px;
      }

      .klytos-image-text-content h2 {
        font-size: 1.75rem;
      }

      .klytos-image-text-content p {
        font-size: 1rem;
      }
    }
  </style>

  <div class="klytos-image-text-container">
    <div class="klytos-image-text-row">
      <div class="klytos-image-text-image"></div>
      <div class="klytos-image-text-content">
        <h2>Powerful Features Built for Growth</h2>
        <p>Our platform combines intuitive design with powerful functionality to help you achieve your business goals faster.</p>
        <ul class="klytos-image-text-list">
          <li>Comprehensive analytics and reporting</li>
          <li>Real-time collaboration tools</li>
          <li>Seamless integrations with existing systems</li>
          <li>World-class security and compliance</li>
        </ul>
        <a href="#" class="klytos-image-text-cta">Learn More</a>
      </div>
    </div>

    <div class="klytos-image-text-row reverse">
      <div class="klytos-image-text-image"></div>
      <div class="klytos-image-text-content">
        <h2>Enterprise-Grade Security</h2>
        <p>Your data security is our top priority. We implement the latest encryption standards and compliance protocols to protect your sensitive information.</p>
        <ul class="klytos-image-text-list">
          <li>End-to-end encryption for all data</li>
          <li>SOC 2 Type II certified</li>
          <li>Regular security audits and penetration testing</li>
          <li>24/7 monitoring and threat detection</li>
        </ul>
        <a href="#" class="klytos-image-text-cta">View Security Details</a>
      </div>
    </div>

    <div class="klytos-image-text-row">
      <div class="klytos-image-text-image"></div>
      <div class="klytos-image-text-content">
        <h2>Scalable Infrastructure</h2>
        <p>Built to grow with your business. Our cloud-based infrastructure automatically scales to handle increasing demands without compromise.</p>
        <ul class="klytos-image-text-list">
          <li>Auto-scaling for peak traffic periods</li>
          <li>Global content delivery network</li>
          <li>99.9% uptime guarantee with SLA</li>
          <li>Disaster recovery and business continuity</li>
        </ul>
        <a href="#" class="klytos-image-text-cta">Explore Pricing</a>
      </div>
    </div>
  </div>
</div>
<!-- /wp:html -->

---

## Customization Tips

When using these patterns, remember:

1. **Replace placeholder content** with actual text, images, and links relevant to your page
2. **Adapt colors** by changing `var(--klytos-primary)` and other CSS variables to match your brand
3. **Modify grid columns** (`.klytos-grid-2` through `.klytos-grid-6`) based on your layout needs
4. **Adjust padding and spacing** using utility classes like `.klytos-py-4`, `.klytos-px-3`, etc.
5. **Extend with custom CSS** by adding a `<style>` block to any pattern that needs additional customization
6. **Keep responsiveness** in mind—all patterns include media queries for mobile devices
7. **Test accessibility**—ensure all interactive elements are keyboard accessible and have proper ARIA labels where needed

## Quick Reference: When to Use Each Pattern

- **Hero Sections** (Patterns 1-2): At the top of landing pages, service pages, and campaign pages
- **Grids** (Patterns 3, 4, 9, 10): Showcase products, features, team members, or content
- **Testimonials** (Pattern 5): Build trust and social proof on landing and about pages
- **Statistics** (Pattern 6): Emphasize achievements and growth
- **Newsletter** (Pattern 7): Drive email list growth
- **Comparison Tables** (Pattern 8): Help users choose between options
- **CTAs** (Pattern 12): Drive conversions and engagement
- **Image + Text** (Pattern 13): Tell stories and explain concepts
- **Footer** (Pattern 11): All pages need a footer

These patterns are production-ready and follow best practices for performance, accessibility, and modern web design.
