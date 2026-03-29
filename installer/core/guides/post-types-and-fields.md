---
title: "Post Types, Taxonomies & Custom Fields"
description: "Complete guide for AI assistants on creating and managing Post Types, Taxonomies, and Custom Fields in Klytos CMS."
---

# Post Types, Taxonomies & Custom Fields

This guide teaches you how to help administrators create structured content in Klytos using Post Types, Taxonomies, and Custom Fields.

## What is a Post Type?

A **Post Type** defines a type of content on the site. The built-in "Pages" post type handles standard web pages. Custom Post Types let administrators create specialized content collections.

**Examples:**
- **Real estate site:** Post Type "Properties" for listings
- **Online store:** Post Type "Products" for items
- **Portfolio:** Post Type "Projects" for case studies
- **Restaurant:** Post Type "Recipes" for dishes
- **Blog:** Post Type "Articles" for blog posts
- **Agency:** Post Type "Services" for service offerings

Each Post Type has its own listing, editor, and URL structure.

**Required data to create a Post Type:**
- `id` — machine name (lowercase, no spaces, e.g. "products")
- `name` — human-readable label (e.g. "Products")

**Optional:**
- `slug` — URL prefix (defaults to id)
- `slug_i18n` — localized URLs per language

## What are Taxonomies?

**Taxonomies** classify and organize content within a Post Type. They are grouping systems, like labels or folders.

There are two kinds:

### Hierarchical Taxonomies (like Categories)
Support parent/child nesting. Best for structured classification with clear hierarchy.

**Examples:**
- Properties → Property Type: Apartment > Studio, Apartment > Penthouse, House > Villa
- Products → Category: Electronics > Phones > Smartphones
- Recipes → Cuisine: European > Italian > Tuscan

### Flat Taxonomies (like Tags)
No nesting. Best for flexible, descriptive labels.

**Examples:**
- Properties → Features: pool, garage, garden, sea-view, elevator
- Products → Tags: sale, new-arrival, eco-friendly, bestseller
- Articles → Topics: technology, health, business

**When to suggest taxonomies:**
- The content needs to be filtered or grouped by visitors
- The administrator wants to classify entries by category, type, or tag
- The site needs faceted navigation (filter by multiple criteria)

**Required data to create a Taxonomy:**
- `id` — machine name (e.g. "property-type")
- `name` — human-readable label (e.g. "Property Type")
- `hierarchical` — true for categories, false for tags

## What are Custom Fields?

**Custom Fields** add structured data to each entry of a Post Type. They define the data schema — what information each entry should contain beyond the title and content.

Think of them as a form that the editor fills out for each entry.

**Examples:**
- **Property:** price (number), bedrooms (number), area_m2 (number), address (text), available_date (date), has_pool (toggle), energy_rating (select A-G), gallery (gallery)
- **Product:** price (number), sku (text), weight (number), color (color), sizes (checkbox_group), main_image (image)
- **Recipe:** prep_time (number), cook_time (number), servings (number), difficulty (select), ingredients (repeater), nutrition_info (json)
- **Event:** event_date (datetime), venue (text), ticket_url (url), ticket_price (number), max_capacity (number), is_online (toggle)

### Available Field Types (27 types)

#### Text Fields
| Type | Use when... | Example |
|------|-------------|---------|
| `text` | You need a single line of text | Product SKU, person name, short title |
| `textarea` | You need multiple lines of plain text | Short description, notes, address |
| `richtext` | You need formatted text with HTML | Detailed description, terms & conditions |
| `code` | You need to store code snippets | CSS overrides, embed codes, schema markup |
| `password` | You need a masked/hidden value | API key, access code |

#### Number Fields
| Type | Use when... | Example |
|------|-------------|---------|
| `number` | You need a numeric value (integer or decimal) | Price, quantity, rating, square meters |
| `range` | You need a number within a visual slider | Priority (1-10), opacity (0-100), quality score |

#### Date & Time Fields
| Type | Use when... | Example |
|------|-------------|---------|
| `date` | You need a calendar date (YYYY-MM-DD) | Publication date, birth date, deadline |
| `datetime` | You need date and time together | Event start, appointment, scheduled task |
| `time` | You need only a time (HH:MM) | Opening hours, show time, class schedule |

#### Choice Fields
| Type | Use when... | Example |
|------|-------------|---------|
| `select` | User picks ONE option from a dropdown | Country, status, category, difficulty level |
| `multiselect` | User picks MULTIPLE options from a dropdown | Languages spoken, available sizes, tags |
| `checkbox` | You need a simple yes/no toggle | "Featured?", "Available?", "Show on homepage?" |
| `checkbox_group` | User picks MULTIPLE options as checkboxes | Amenities (pool, gym, parking), features |
| `radio` | User picks ONE option as radio buttons | Payment method, gender, priority level |
| `toggle` | Same as checkbox but styled as a switch | "Active", "Published", "Notifications enabled" |

#### Media Fields
| Type | Use when... | Example |
|------|-------------|---------|
| `image` | You need a single image URL | Featured image, author photo, logo |
| `file` | You need a file URL (any type) | PDF brochure, downloadable resource, document |
| `gallery` | You need multiple images | Product photos, project gallery, before/after |

#### Data Fields
| Type | Use when... | Example |
|------|-------------|---------|
| `email` | You need a validated email address | Contact email, author email |
| `url` | You need a validated URL | External link, video URL, social profile |
| `phone` | You need a phone number | Contact phone, WhatsApp number |
| `color` | You need a hex color value | Brand color, accent color, background |
| `json` | You need to store structured JSON data | API config, complex settings, schema.org data |

#### Advanced Fields
| Type | Use when... | Example |
|------|-------------|---------|
| `repeater` | You need a repeatable group of sub-fields | Ingredients (name + quantity), team members (name + role + photo), FAQ (question + answer) |
| `relationship` | You need to reference other entries | Related products, author (from a "People" PT), parent company |

### Required vs Optional Fields

- **Required fields** (`required: true`): The entry cannot be saved without filling these in. The assistant MUST ask the user for these values when creating an entry.
- **Optional fields** (`required: false`, default): The entry can be saved without them.

**Always ask the administrator** whether a field should be required when creating it.

## Recommended Workflow

### When creating a Post Type:

1. **Ask the user** for the Post Type name and purpose
2. **Create the Post Type** with `klytos_create_post_type`
3. **Ask about Taxonomies:** "Would you like to classify your [name] by categories or tags? For example, a 'Properties' post type might have a 'Property Type' category (apartment, house, land) and 'Features' tags (pool, garage, garden)."
4. **Create Taxonomies** if requested with `klytos_add_taxonomy`
5. **Add Terms** to taxonomies if the user provides them with `klytos_add_term`
6. **Ask about Custom Fields:** "Would you like to add structured data fields to your [name]? For example, a 'Properties' post type might have fields for price, bedrooms, area, address, and photos. I can show you all available field types."
7. **Show field types** if requested with `klytos_get_field_types`
8. **Create Custom Fields** for each one with `klytos_add_custom_field`, asking for each:
   - Field ID and label
   - Field type (explain what it does if unclear)
   - Whether it should be **required** or optional
   - Options (for select, radio, checkbox_group types)
   - Validation rules if applicable (min/max, pattern, etc.)

### When creating an entry for a custom Post Type:

1. **Get the Post Type definition** with `klytos_get_post_type` to know its taxonomies
2. **List Custom Fields** with `klytos_list_custom_fields` to know what data is needed
3. **Inform the user** about:
   - Available taxonomies and their terms
   - Required Custom Fields (MUST be filled)
   - Optional Custom Fields (can be skipped)
4. **Create the entry** with `klytos_create_page` (with `post_type` parameter)
5. **Set field values** with `klytos_set_bulk_field_values`
6. **Assign taxonomy terms** (stored as meta with `tax.` prefix)

## Practical Example: Real Estate Site

```
1. Create Post Type:
   klytos_create_post_type(id="properties", name="Properties", slug="properties")

2. Add Taxonomies:
   klytos_add_taxonomy(post_type_id="properties", id="property-type", name="Property Type", hierarchical=true)
   klytos_add_taxonomy(post_type_id="properties", id="features", name="Features", hierarchical=false)

3. Add Terms:
   klytos_add_term(post_type_id="properties", taxonomy_id="property-type", name="Apartment")
   klytos_add_term(post_type_id="properties", taxonomy_id="property-type", name="House")
   klytos_add_term(post_type_id="properties", taxonomy_id="features", name="Pool")
   klytos_add_term(post_type_id="properties", taxonomy_id="features", name="Garage")

4. Add Custom Fields:
   klytos_add_custom_field(post_type_id="properties", id="price", type="number", label="Price (EUR)", required=true, validation={"min": 0})
   klytos_add_custom_field(post_type_id="properties", id="bedrooms", type="number", label="Bedrooms", required=true, validation={"min": 0, "max": 20, "integer": true})
   klytos_add_custom_field(post_type_id="properties", id="area_m2", type="number", label="Area (m²)", required=true, validation={"min": 1})
   klytos_add_custom_field(post_type_id="properties", id="address", type="text", label="Address", required=true)
   klytos_add_custom_field(post_type_id="properties", id="available_date", type="date", label="Available From")
   klytos_add_custom_field(post_type_id="properties", id="has_pool", type="toggle", label="Has Pool")
   klytos_add_custom_field(post_type_id="properties", id="energy_rating", type="select", label="Energy Rating", options=[{value:"A",label:"A"},{value:"B",label:"B"},{value:"C",label:"C"},{value:"D",label:"D"},{value:"E",label:"E"},{value:"F",label:"F"},{value:"G",label:"G"}])
   klytos_add_custom_field(post_type_id="properties", id="gallery", type="gallery", label="Photo Gallery")

5. Create an entry:
   klytos_create_page(slug="luxury-villa-marbella", title="Luxury Villa in Marbella", content_html="...", post_type="properties", meta_description="...")
   klytos_set_bulk_field_values(page_slug="luxury-villa-marbella", fields={"price": 850000, "bedrooms": 5, "area_m2": 320, "address": "Calle Sol 15, Marbella", "has_pool": true, "energy_rating": "B"})
```
