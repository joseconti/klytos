<?php
/**
 * Klytos — Seed Data
 * Creates the default blocks and page templates during installation.
 *
 * Called once during install.php after storage is initialized.
 * Seeds ~20 core blocks and 9 page templates with HTML/CSS/slot definitions.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

/**
 * Seed all core blocks and page templates into storage.
 *
 * @param BlockManager        $blocks    Block manager instance.
 * @param PageTemplateManager $templates Page template manager instance.
 */
function seedDefaultData(BlockManager $blocks, PageTemplateManager $templates): void
{
    seedCoreBlocks($blocks);
    seedCorePageTemplates($templates);
}

/**
 * Seed the ~20 core HTML blocks.
 * Each block has: id, name, category, scope, HTML template, slots, and sample data.
 */
function seedCoreBlocks(BlockManager $blocks): void
{
    $coreBlocks = [
        // ── Structure blocks ─────────────────────────────────
        [
            'id' => 'header', 'name' => 'Header', 'category' => 'structure', 'scope' => 'global',
            'html' => '<header class="klytos-header"><div class="klytos-container"><a href="/" class="site-logo">{{site_name}}</a>{{menu_html}}</div></header>',
            'slots' => [['name' => 'site_name', 'type' => 'text', 'label' => 'Site Name', 'required' => true]],
            'sample_data' => ['site_name' => 'My Site'],
        ],
        [
            'id' => 'footer', 'name' => 'Footer', 'category' => 'structure', 'scope' => 'global',
            'html' => '<footer class="klytos-footer"><div class="klytos-container"><p>&copy; {{year}} {{site_name}}</p><p>{{tagline}}</p></div></footer>',
            'slots' => [
                ['name' => 'site_name', 'type' => 'text', 'label' => 'Site Name'],
                ['name' => 'tagline', 'type' => 'text', 'label' => 'Footer Tagline'],
                ['name' => 'year', 'type' => 'text', 'label' => 'Year'],
            ],
            'sample_data' => ['site_name' => 'My Site', 'tagline' => 'Powered by Klytos', 'year' => date('Y')],
        ],
        [
            'id' => 'breadcrumb', 'name' => 'Breadcrumb', 'category' => 'structure', 'scope' => 'page',
            'html' => '{{breadcrumbs}}',
            'slots' => [],
            'sample_data' => [],
        ],
        [
            'id' => 'cookie-banner', 'name' => 'Cookie Banner', 'category' => 'structure', 'scope' => 'global',
            'html' => '<div class="klytos-cookie-banner" id="cookie-banner" style="display:none"><div class="klytos-container"><p>{{message}}</p><button onclick="document.getElementById(\'cookie-banner\').style.display=\'none\'">{{button_text}}</button></div></div>',
            'slots' => [
                ['name' => 'message', 'type' => 'text', 'label' => 'Banner Message'],
                ['name' => 'button_text', 'type' => 'text', 'label' => 'Button Text', 'default' => 'Accept'],
            ],
            'sample_data' => ['message' => 'This site uses only essential cookies.', 'button_text' => 'OK'],
        ],

        // ── Content blocks ───────────────────────────────────
        [
            'id' => 'hero', 'name' => 'Hero Section', 'category' => 'content', 'scope' => 'page',
            'html' => '<section class="klytos-hero" style="background:var(--klytos-primary);color:#fff;padding:4rem 0;text-align:center"><div class="klytos-container"><h1>{{heading}}</h1><p style="font-size:1.2rem;opacity:0.9;max-width:600px;margin:1rem auto">{{subheading}}</p><a href="{{cta_url}}" class="klytos-btn" style="display:inline-block;margin-top:1.5rem;padding:0.8rem 2rem;background:#fff;color:var(--klytos-primary);border-radius:var(--klytos-radius);font-weight:600;text-decoration:none">{{cta_text}}</a></div></section>',
            'slots' => [
                ['name' => 'heading', 'type' => 'text', 'label' => 'Main Heading', 'required' => true],
                ['name' => 'subheading', 'type' => 'text', 'label' => 'Subheading'],
                ['name' => 'cta_text', 'type' => 'text', 'label' => 'CTA Button Text', 'default' => 'Get Started'],
                ['name' => 'cta_url', 'type' => 'url', 'label' => 'CTA Button URL', 'default' => '#'],
            ],
            'sample_data' => ['heading' => 'Welcome', 'subheading' => 'Build your site with AI.', 'cta_text' => 'Get Started', 'cta_url' => '/contact/'],
        ],
        [
            'id' => 'text-block', 'name' => 'Text Block', 'category' => 'content', 'scope' => 'page',
            'html' => '<section class="klytos-text-block" style="padding:3rem 0"><div class="klytos-container"><h2>{{heading}}</h2><div class="content-body">{{content}}</div></div></section>',
            'slots' => [
                ['name' => 'heading', 'type' => 'text', 'label' => 'Section Heading'],
                ['name' => 'content', 'type' => 'richtext', 'label' => 'Content', 'required' => true],
            ],
            'sample_data' => ['heading' => 'About Us', 'content' => '<p>Your content here.</p>'],
        ],
        [
            'id' => 'image-text', 'name' => 'Image + Text', 'category' => 'content', 'scope' => 'page',
            'html' => '<section class="klytos-image-text" style="padding:3rem 0"><div class="klytos-container" style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;align-items:center"><div><img src="{{image_url}}" alt="{{image_alt}}" style="width:100%;border-radius:var(--klytos-radius)"></div><div><h2>{{heading}}</h2><p>{{text}}</p></div></div></section>',
            'slots' => [
                ['name' => 'image_url', 'type' => 'image', 'label' => 'Image', 'required' => true],
                ['name' => 'image_alt', 'type' => 'text', 'label' => 'Image Alt Text'],
                ['name' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['name' => 'text', 'type' => 'richtext', 'label' => 'Text Content'],
            ],
            'sample_data' => ['image_url' => '/assets/images/placeholder.jpg', 'image_alt' => 'Image', 'heading' => 'Our Story', 'text' => 'Tell your story here.'],
        ],
        [
            'id' => 'gallery', 'name' => 'Image Gallery', 'category' => 'content', 'scope' => 'page',
            'html' => '<section class="klytos-gallery" style="padding:3rem 0"><div class="klytos-container"><h2>{{heading}}</h2><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:1rem;margin-top:1.5rem">{{gallery_html}}</div></div></section>',
            'slots' => [
                ['name' => 'heading', 'type' => 'text', 'label' => 'Gallery Title'],
                ['name' => 'gallery_html', 'type' => 'html', 'label' => 'Gallery Images HTML'],
            ],
            'sample_data' => ['heading' => 'Gallery', 'gallery_html' => '<img src="/assets/images/placeholder.jpg" alt="Photo" style="width:100%;border-radius:var(--klytos-radius)">'],
        ],

        // ── Interaction blocks ────────────────────────────────
        [
            'id' => 'cta', 'name' => 'Call to Action', 'category' => 'interaction', 'scope' => 'page',
            'html' => '<section class="klytos-cta" style="padding:3rem 0;text-align:center;background:var(--klytos-surface)"><div class="klytos-container"><h2>{{heading}}</h2><p style="color:var(--klytos-text-muted);margin-bottom:1.5rem">{{text}}</p><a href="{{button_url}}" class="klytos-btn" style="display:inline-block;padding:0.8rem 2rem;background:var(--klytos-primary);color:#fff;border-radius:var(--klytos-radius);font-weight:600;text-decoration:none">{{button_text}}</a></div></section>',
            'slots' => [
                ['name' => 'heading', 'type' => 'text', 'label' => 'CTA Heading', 'required' => true],
                ['name' => 'text', 'type' => 'text', 'label' => 'CTA Description'],
                ['name' => 'button_text', 'type' => 'text', 'label' => 'Button Text', 'default' => 'Contact Us'],
                ['name' => 'button_url', 'type' => 'url', 'label' => 'Button URL', 'default' => '/contact/'],
            ],
            'sample_data' => ['heading' => 'Ready to Start?', 'text' => 'Get in touch today.', 'button_text' => 'Contact Us', 'button_url' => '/contact/'],
        ],
        [
            'id' => 'faq-accordion', 'name' => 'FAQ Accordion', 'category' => 'interaction', 'scope' => 'page',
            'html' => '<section class="klytos-faq" style="padding:3rem 0"><div class="klytos-container"><h2>{{heading}}</h2><div class="faq-list" style="margin-top:1.5rem">{{faq_html}}</div></div></section>',
            'slots' => [
                ['name' => 'heading', 'type' => 'text', 'label' => 'FAQ Section Title', 'default' => 'Frequently Asked Questions'],
                ['name' => 'faq_html', 'type' => 'html', 'label' => 'FAQ Items (HTML details/summary)'],
            ],
            'sample_data' => ['heading' => 'FAQ', 'faq_html' => '<details><summary>What is Klytos?</summary><p>An AI-powered CMS.</p></details>'],
        ],
        [
            'id' => 'stats-counter', 'name' => 'Statistics Counter', 'category' => 'interaction', 'scope' => 'page',
            'html' => '<section class="klytos-stats" style="padding:3rem 0;background:var(--klytos-surface)"><div class="klytos-container" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:2rem;text-align:center">{{stats_html}}</div></section>',
            'slots' => [
                ['name' => 'stats_html', 'type' => 'html', 'label' => 'Stats HTML'],
            ],
            'sample_data' => ['stats_html' => '<div><div style="font-size:2.5rem;font-weight:700;color:var(--klytos-primary)">500+</div><div style="color:var(--klytos-text-muted)">Clients</div></div>'],
        ],

        // ── Social proof blocks ──────────────────────────────
        [
            'id' => 'testimonials', 'name' => 'Testimonials', 'category' => 'social-proof', 'scope' => 'page',
            'html' => '<section class="klytos-testimonials" style="padding:3rem 0"><div class="klytos-container"><h2>{{heading}}</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.5rem;margin-top:1.5rem">{{testimonials_html}}</div></div></section>',
            'slots' => [
                ['name' => 'heading', 'type' => 'text', 'label' => 'Section Title', 'default' => 'What Our Clients Say'],
                ['name' => 'testimonials_html', 'type' => 'html', 'label' => 'Testimonial Cards HTML'],
            ],
            'sample_data' => ['heading' => 'Testimonials', 'testimonials_html' => '<div style="background:var(--klytos-surface);padding:1.5rem;border-radius:var(--klytos-radius)"><p>"Excellent service."</p><strong>— Client Name</strong></div>'],
        ],
        [
            'id' => 'team-grid', 'name' => 'Team Grid', 'category' => 'social-proof', 'scope' => 'page',
            'html' => '<section class="klytos-team" style="padding:3rem 0"><div class="klytos-container"><h2>{{heading}}</h2><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1.5rem;margin-top:1.5rem">{{team_html}}</div></div></section>',
            'slots' => [
                ['name' => 'heading', 'type' => 'text', 'label' => 'Section Title', 'default' => 'Our Team'],
                ['name' => 'team_html', 'type' => 'html', 'label' => 'Team Member Cards HTML'],
            ],
            'sample_data' => ['heading' => 'Our Team', 'team_html' => '<div style="text-align:center"><div style="width:100px;height:100px;border-radius:50%;background:var(--klytos-border);margin:0 auto 0.5rem"></div><strong>Name</strong><br><small>Role</small></div>'],
        ],
        [
            'id' => 'logo-bar', 'name' => 'Logo Bar', 'category' => 'social-proof', 'scope' => 'page',
            'html' => '<section class="klytos-logos" style="padding:2rem 0;background:var(--klytos-surface)"><div class="klytos-container" style="display:flex;justify-content:center;align-items:center;gap:3rem;flex-wrap:wrap;opacity:0.6">{{logos_html}}</div></section>',
            'slots' => [['name' => 'logos_html', 'type' => 'html', 'label' => 'Logo Images HTML']],
            'sample_data' => ['logos_html' => '<span style="font-size:1.5rem;font-weight:700">Brand 1</span><span style="font-size:1.5rem;font-weight:700">Brand 2</span>'],
        ],
        [
            'id' => 'map-embed', 'name' => 'Map Embed', 'category' => 'social-proof', 'scope' => 'page',
            'html' => '<section class="klytos-map" style="padding:3rem 0"><div class="klytos-container"><h2>{{heading}}</h2><p style="color:var(--klytos-text-muted);margin-bottom:1rem">{{address}}</p><div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:var(--klytos-radius)">{{map_iframe}}</div></div></section>',
            'slots' => [
                ['name' => 'heading', 'type' => 'text', 'label' => 'Section Title', 'default' => 'Find Us'],
                ['name' => 'address', 'type' => 'text', 'label' => 'Address'],
                ['name' => 'map_iframe', 'type' => 'html', 'label' => 'Map Iframe HTML'],
            ],
            'sample_data' => ['heading' => 'Our Location', 'address' => '123 Main St, City', 'map_iframe' => '<div style="background:var(--klytos-border);width:100%;height:300px;display:flex;align-items:center;justify-content:center;color:var(--klytos-text-muted)">Map placeholder</div>'],
        ],

        // ── Additional structure blocks ──────────────────────────
        [
            'id' => 'top-bar', 'name' => 'Top Bar', 'category' => 'structure', 'scope' => 'global',
            'html' => '<div class="klytos-top-bar" style="background:var(--klytos-text);color:#fff;font-size:0.85rem;padding:0.4rem 0"><div class="klytos-container" style="display:flex;justify-content:space-between;align-items:center"><span>{{phone}}</span><span>{{email}}</span><span>{{social_html}}</span></div></div>',
            'slots' => [
                ['name' => 'phone', 'type' => 'phone', 'label' => 'Phone Number'],
                ['name' => 'email', 'type' => 'email', 'label' => 'Email Address'],
                ['name' => 'social_html', 'type' => 'html', 'label' => 'Social Links HTML'],
            ],
            'sample_data' => ['phone' => '+1 234 567 890', 'email' => 'info@example.com', 'social_html' => ''],
        ],
        [
            'id' => 'menu', 'name' => 'Navigation Menu', 'category' => 'structure', 'scope' => 'global',
            'html' => '<nav class="klytos-nav" aria-label="Main navigation">{{menu_html}}</nav>',
            'slots' => [
                ['name' => 'menu_html', 'type' => 'html', 'label' => 'Menu HTML (auto-populated from menu manager)'],
            ],
            'sample_data' => ['menu_html' => '<ul class="klytos-menu"><li><a href="/">Home</a></li><li><a href="/about/">About</a></li><li><a href="/contact/">Contact</a></li></ul>'],
        ],
        [
            'id' => 'sidebar', 'name' => 'Sidebar', 'category' => 'structure', 'scope' => 'template',
            'html' => '<aside class="klytos-sidebar" style="padding:1.5rem;background:var(--klytos-surface);border-radius:var(--klytos-radius)">{{sidebar_html}}</aside>',
            'slots' => [
                ['name' => 'sidebar_html', 'type' => 'html', 'label' => 'Sidebar Content HTML'],
            ],
            'sample_data' => ['sidebar_html' => '<h3>Recent Posts</h3><ul><li><a href="#">Post title</a></li></ul>'],
        ],

        // ── Additional content blocks ────────────────────────────
        [
            'id' => 'video-embed', 'name' => 'Video Embed', 'category' => 'content', 'scope' => 'page',
            'html' => '<section class="klytos-video" style="padding:3rem 0"><div class="klytos-container"><h2>{{heading}}</h2><div style="position:relative;padding-bottom:{{aspect_ratio}};height:0;overflow:hidden;border-radius:var(--klytos-radius);margin-top:1.5rem"><iframe src="{{video_url}}" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" allowfullscreen></iframe></div></div></section>',
            'slots' => [
                ['name' => 'heading', 'type' => 'text', 'label' => 'Section Title'],
                ['name' => 'video_url', 'type' => 'url', 'label' => 'Video Embed URL', 'required' => true],
                ['name' => 'aspect_ratio', 'type' => 'select', 'label' => 'Aspect Ratio', 'options' => ['56.25%', '75%'], 'default' => '56.25%'],
            ],
            'sample_data' => ['heading' => 'Watch Our Video', 'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'aspect_ratio' => '56.25%'],
        ],
        [
            'id' => 'blog-list', 'name' => 'Blog List', 'category' => 'content', 'scope' => 'page',
            'html' => '<section class="klytos-blog-list" style="padding:3rem 0"><div class="klytos-container"><h2>{{heading}}</h2><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;margin-top:1.5rem">{{posts_html}}</div></div></section>',
            'slots' => [
                ['name' => 'heading', 'type' => 'text', 'label' => 'Section Title', 'default' => 'Latest Posts'],
                ['name' => 'posts_html', 'type' => 'html', 'label' => 'Post Cards HTML'],
            ],
            'sample_data' => ['heading' => 'Latest Posts', 'posts_html' => '<article class="klytos-card" style="padding:1.5rem"><h3><a href="#">Sample Post</a></h3><p style="color:var(--klytos-text-muted)">A brief excerpt of the blog post content...</p></article>'],
        ],

        // ── Additional interaction blocks ────────────────────────
        [
            'id' => 'contact-form', 'name' => 'Contact Form', 'category' => 'interaction', 'scope' => 'page',
            'html' => '<section class="klytos-contact-form" style="padding:3rem 0"><div class="klytos-container" style="max-width:600px"><h2>{{heading}}</h2>{{form_html}}</div></section>',
            'slots' => [
                ['name' => 'heading', 'type' => 'text', 'label' => 'Form Title', 'default' => 'Get in Touch'],
                ['name' => 'form_html', 'type' => 'html', 'label' => 'Form HTML'],
            ],
            'sample_data' => ['heading' => 'Contact Us', 'form_html' => '<form method="post" style="display:flex;flex-direction:column;gap:1rem;margin-top:1.5rem"><input type="text" name="name" placeholder="Your Name" required style="padding:0.75rem;border:1px solid var(--klytos-border);border-radius:var(--klytos-radius)"><input type="email" name="email" placeholder="Your Email" required style="padding:0.75rem;border:1px solid var(--klytos-border);border-radius:var(--klytos-radius)"><textarea name="message" rows="5" placeholder="Your Message" required style="padding:0.75rem;border:1px solid var(--klytos-border);border-radius:var(--klytos-radius)"></textarea><button type="submit" class="klytos-btn klytos-btn-primary">Send Message</button></form>'],
        ],
    ];

    foreach ($coreBlocks as $blockData) {
        try {
            $blocks->save($blockData);
        } catch (\Throwable $e) {
            error_log("Klytos Seed: failed to create block '{$blockData['id']}': " . $e->getMessage());
        }
    }
}

/**
 * Seed the 9 core page templates.
 * Each template defines a structure (ordered list of block IDs).
 */
function seedCorePageTemplates(PageTemplateManager $templates): void
{
    $coreTemplates = [
        [
            'type' => 'home', 'name' => 'Homepage',
            'description' => 'Landing page with hero, features, testimonials, and CTA.',
            'structure' => [
                ['block_id' => 'top-bar', 'order' => 0],
                ['block_id' => 'header', 'order' => 1],
                ['block_id' => 'hero', 'order' => 2],
                ['block_id' => 'text-block', 'order' => 3],
                ['block_id' => 'stats-counter', 'order' => 4],
                ['block_id' => 'testimonials', 'order' => 5],
                ['block_id' => 'cta', 'order' => 6],
                ['block_id' => 'footer', 'order' => 7],
            ],
            'status' => 'active',
        ],
        [
            'type' => 'page', 'name' => 'Standard Page',
            'description' => 'Simple page with header, content, and footer.',
            'structure' => [
                ['block_id' => 'top-bar', 'order' => 0],
                ['block_id' => 'header', 'order' => 1],
                ['block_id' => 'breadcrumb', 'order' => 2],
                ['block_id' => 'text-block', 'order' => 3],
                ['block_id' => 'footer', 'order' => 4],
            ],
            'status' => 'active',
        ],
        [
            'type' => 'contact', 'name' => 'Contact Page',
            'description' => 'Contact page with form, map, and info.',
            'structure' => [
                ['block_id' => 'top-bar', 'order' => 0],
                ['block_id' => 'header', 'order' => 1],
                ['block_id' => 'breadcrumb', 'order' => 2],
                ['block_id' => 'text-block', 'order' => 3],
                ['block_id' => 'contact-form', 'order' => 4],
                ['block_id' => 'map-embed', 'order' => 5],
                ['block_id' => 'footer', 'order' => 6],
            ],
            'status' => 'active',
        ],
        [
            'type' => 'landing', 'name' => 'Landing Page',
            'description' => 'Conversion-focused page with hero, benefits, social proof.',
            'structure' => [
                ['block_id' => 'hero', 'order' => 0],
                ['block_id' => 'image-text', 'order' => 1],
                ['block_id' => 'stats-counter', 'order' => 2],
                ['block_id' => 'testimonials', 'order' => 3],
                ['block_id' => 'logo-bar', 'order' => 4],
                ['block_id' => 'cta', 'order' => 5],
                ['block_id' => 'footer', 'order' => 6],
            ],
            'status' => 'active',
        ],
        [
            'type' => 'faq', 'name' => 'FAQ Page',
            'description' => 'Frequently asked questions with accordion.',
            'structure' => [
                ['block_id' => 'top-bar', 'order' => 0],
                ['block_id' => 'header', 'order' => 1],
                ['block_id' => 'breadcrumb', 'order' => 2],
                ['block_id' => 'faq-accordion', 'order' => 3],
                ['block_id' => 'cta', 'order' => 4],
                ['block_id' => 'footer', 'order' => 5],
            ],
            'status' => 'active',
        ],
        [
            'type' => 'team', 'name' => 'Team Page',
            'description' => 'Team members grid with bios.',
            'structure' => [
                ['block_id' => 'top-bar', 'order' => 0],
                ['block_id' => 'header', 'order' => 1],
                ['block_id' => 'breadcrumb', 'order' => 2],
                ['block_id' => 'text-block', 'order' => 3],
                ['block_id' => 'team-grid', 'order' => 4],
                ['block_id' => 'cta', 'order' => 5],
                ['block_id' => 'footer', 'order' => 6],
            ],
            'status' => 'active',
        ],
        [
            'type' => 'services', 'name' => 'Services Page',
            'description' => 'Service listings with hero, features, and social proof.',
            'structure' => [
                ['block_id' => 'top-bar', 'order' => 0],
                ['block_id' => 'header', 'order' => 1],
                ['block_id' => 'hero', 'order' => 2],
                ['block_id' => 'image-text', 'order' => 3],
                ['block_id' => 'cta', 'order' => 4],
                ['block_id' => 'testimonials', 'order' => 5],
                ['block_id' => 'footer', 'order' => 6],
            ],
            'status' => 'active',
        ],
        [
            'type' => 'gallery', 'name' => 'Gallery Page',
            'description' => 'Photo gallery layout.',
            'structure' => [
                ['block_id' => 'top-bar', 'order' => 0],
                ['block_id' => 'header', 'order' => 1],
                ['block_id' => 'breadcrumb', 'order' => 2],
                ['block_id' => 'gallery', 'order' => 3],
                ['block_id' => 'footer', 'order' => 4],
            ],
            'status' => 'active',
        ],
        [
            'type' => 'post', 'name' => 'Blog Post',
            'description' => 'Single article/blog post layout with sidebar.',
            'structure' => [
                ['block_id' => 'top-bar', 'order' => 0],
                ['block_id' => 'header', 'order' => 1],
                ['block_id' => 'breadcrumb', 'order' => 2],
                ['block_id' => 'text-block', 'order' => 3],
                ['block_id' => 'sidebar', 'order' => 4],
                ['block_id' => 'footer', 'order' => 5],
            ],
            'status' => 'active',
        ],
    ];

    foreach ($coreTemplates as $templateData) {
        try {
            $templates->save($templateData);
        } catch (\Throwable $e) {
            error_log("Klytos Seed: failed to create template '{$templateData['type']}': " . $e->getMessage());
        }
    }
}
