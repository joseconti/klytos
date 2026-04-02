---
name: site-builder-palettes
description: "Color palettes by sector and site type for the Site Builder — all 11 klytos_set_colors parameters."
trigger: When configuring site colors during the site building process.
---

# Color Palettes for Site Builder

This guide provides pre-designed color palettes for `klytos_set_colors`. Each palette includes all 11 required parameters.

## How Colors Work in Klytos

`klytos_set_colors` accepts these 11 parameters:

| Parameter | Purpose | Notes |
|-----------|---------|-------|
| `primary` | Main brand color | Buttons, links, active states |
| `secondary` | Supporting brand color | Secondary buttons, accents |
| `accent` | Highlight/call-to-action | Badges, alerts, special elements |
| `background` | Page background | Usually very light or very dark |
| `surface` | Card/component background | Slightly different from background |
| `text` | Main text color | High contrast against background |
| `text_muted` | Secondary text | Lower contrast, captions, hints |
| `border` | Border color | Subtle, between surface and text_muted |
| `success` | Success state | Usually green |
| `warning` | Warning state | Usually yellow/orange |
| `error` | Error state | Usually red |

## Derivation Rules

If the user only provides primary, secondary, and accent colors, derive the rest:

**For light themes:**
- `background`: `#FFFFFF` or `#F9FAFB`
- `surface`: `#F3F4F6` or `#F8F9FA`
- `text`: `#111827` or `#1F2937`
- `text_muted`: `#6B7280` or `#9CA3AF`
- `border`: `#E5E7EB` or `#D1D5DB`

**For dark themes:**
- `background`: `#0F172A` or `#111827`
- `surface`: `#1E293B` or `#1F2937`
- `text`: `#F9FAFB` or `#F1F5F9`
- `text_muted`: `#94A3B8` or `#9CA3AF`
- `border`: `#334155` or `#374151`

**State colors (universal defaults):**
- `success`: `#10B981`
- `warning`: `#F59E0B`
- `error`: `#EF4444`

---

## Palettes by Sector

### Technology

**Tech Modern (light)**
```json
{
  "primary": "#2563EB",
  "secondary": "#7C3AED",
  "accent": "#06B6D4",
  "background": "#FFFFFF",
  "surface": "#F8FAFC",
  "text": "#0F172A",
  "text_muted": "#64748B",
  "border": "#E2E8F0",
  "success": "#10B981",
  "warning": "#F59E0B",
  "error": "#EF4444"
}
```

**Tech Dark**
```json
{
  "primary": "#3B82F6",
  "secondary": "#8B5CF6",
  "accent": "#22D3EE",
  "background": "#0F172A",
  "surface": "#1E293B",
  "text": "#F1F5F9",
  "text_muted": "#94A3B8",
  "border": "#334155",
  "success": "#34D399",
  "warning": "#FBBF24",
  "error": "#F87171"
}
```

**Tech Minimal**
```json
{
  "primary": "#18181B",
  "secondary": "#3F3F46",
  "accent": "#2563EB",
  "background": "#FFFFFF",
  "surface": "#FAFAFA",
  "text": "#18181B",
  "text_muted": "#71717A",
  "border": "#E4E4E7",
  "success": "#10B981",
  "warning": "#F59E0B",
  "error": "#EF4444"
}
```

---

### Corporate / Business

**Corporate Blue**
```json
{
  "primary": "#1E40AF",
  "secondary": "#1E3A5F",
  "accent": "#F59E0B",
  "background": "#FFFFFF",
  "surface": "#F8FAFC",
  "text": "#1E293B",
  "text_muted": "#64748B",
  "border": "#CBD5E1",
  "success": "#059669",
  "warning": "#D97706",
  "error": "#DC2626"
}
```

**Corporate Neutral**
```json
{
  "primary": "#374151",
  "secondary": "#4B5563",
  "accent": "#0EA5E9",
  "background": "#FFFFFF",
  "surface": "#F9FAFB",
  "text": "#111827",
  "text_muted": "#6B7280",
  "border": "#E5E7EB",
  "success": "#10B981",
  "warning": "#F59E0B",
  "error": "#EF4444"
}
```

**Corporate Premium**
```json
{
  "primary": "#1E3A5F",
  "secondary": "#2D5F8B",
  "accent": "#C9A84C",
  "background": "#FAFBFC",
  "surface": "#FFFFFF",
  "text": "#1A2332",
  "text_muted": "#5A6A7A",
  "border": "#DDE3EA",
  "success": "#0D9488",
  "warning": "#E8A317",
  "error": "#C53030"
}
```

---

### Health / Wellness

**Health Trust**
```json
{
  "primary": "#0D9488",
  "secondary": "#0E7490",
  "accent": "#2563EB",
  "background": "#FFFFFF",
  "surface": "#F0FDFA",
  "text": "#134E4A",
  "text_muted": "#5EEAD4",
  "border": "#CCFBF1",
  "success": "#059669",
  "warning": "#D97706",
  "error": "#DC2626"
}
```

**Health Calm**
```json
{
  "primary": "#4F46E5",
  "secondary": "#7C3AED",
  "accent": "#06B6D4",
  "background": "#FAFBFF",
  "surface": "#F5F3FF",
  "text": "#1E1B4B",
  "text_muted": "#6B7280",
  "border": "#E0E7FF",
  "success": "#10B981",
  "warning": "#F59E0B",
  "error": "#EF4444"
}
```

---

### Creative / Design

**Creative Bold**
```json
{
  "primary": "#DC2626",
  "secondary": "#1E293B",
  "accent": "#FBBF24",
  "background": "#FFFFFF",
  "surface": "#FEF2F2",
  "text": "#1C1917",
  "text_muted": "#78716C",
  "border": "#E7E5E4",
  "success": "#10B981",
  "warning": "#F59E0B",
  "error": "#EF4444"
}
```

**Creative Minimal (dark)**
```json
{
  "primary": "#FAFAFA",
  "secondary": "#A1A1AA",
  "accent": "#F472B6",
  "background": "#09090B",
  "surface": "#18181B",
  "text": "#FAFAFA",
  "text_muted": "#71717A",
  "border": "#27272A",
  "success": "#34D399",
  "warning": "#FBBF24",
  "error": "#FB7185"
}
```

**Creative Warm**
```json
{
  "primary": "#C2410C",
  "secondary": "#92400E",
  "accent": "#0D9488",
  "background": "#FFFBEB",
  "surface": "#FEF3C7",
  "text": "#451A03",
  "text_muted": "#92400E",
  "border": "#FDE68A",
  "success": "#059669",
  "warning": "#D97706",
  "error": "#DC2626"
}
```

---

### Gastronomy / Food

**Gastro Warm**
```json
{
  "primary": "#92400E",
  "secondary": "#78350F",
  "accent": "#059669",
  "background": "#FFFBEB",
  "surface": "#FEF3C7",
  "text": "#451A03",
  "text_muted": "#A16207",
  "border": "#FDE68A",
  "success": "#059669",
  "warning": "#D97706",
  "error": "#DC2626"
}
```

**Gastro Elegant**
```json
{
  "primary": "#1C1917",
  "secondary": "#44403C",
  "accent": "#B45309",
  "background": "#FAFAF9",
  "surface": "#F5F5F4",
  "text": "#1C1917",
  "text_muted": "#78716C",
  "border": "#D6D3D1",
  "success": "#059669",
  "warning": "#D97706",
  "error": "#DC2626"
}
```

---

### Education

**Education Friendly**
```json
{
  "primary": "#2563EB",
  "secondary": "#7C3AED",
  "accent": "#F97316",
  "background": "#FFFFFF",
  "surface": "#EFF6FF",
  "text": "#1E3A5F",
  "text_muted": "#6B7280",
  "border": "#BFDBFE",
  "success": "#10B981",
  "warning": "#F59E0B",
  "error": "#EF4444"
}
```

**Education Calm**
```json
{
  "primary": "#059669",
  "secondary": "#0D9488",
  "accent": "#2563EB",
  "background": "#FFFFFF",
  "surface": "#ECFDF5",
  "text": "#064E3B",
  "text_muted": "#6B7280",
  "border": "#A7F3D0",
  "success": "#10B981",
  "warning": "#F59E0B",
  "error": "#EF4444"
}
```

---

### Legal / Finance

**Legal Authoritative**
```json
{
  "primary": "#1E3A5F",
  "secondary": "#374151",
  "accent": "#B45309",
  "background": "#FFFFFF",
  "surface": "#F8FAFC",
  "text": "#111827",
  "text_muted": "#6B7280",
  "border": "#D1D5DB",
  "success": "#059669",
  "warning": "#D97706",
  "error": "#DC2626"
}
```

**Finance Modern**
```json
{
  "primary": "#0F766E",
  "secondary": "#1E40AF",
  "accent": "#D97706",
  "background": "#FFFFFF",
  "surface": "#F0FDFA",
  "text": "#0F172A",
  "text_muted": "#64748B",
  "border": "#E2E8F0",
  "success": "#059669",
  "warning": "#D97706",
  "error": "#DC2626"
}
```

---

### NGO / Non-profit

**NGO Empathetic**
```json
{
  "primary": "#059669",
  "secondary": "#0E7490",
  "accent": "#F97316",
  "background": "#FFFFFF",
  "surface": "#ECFDF5",
  "text": "#064E3B",
  "text_muted": "#6B7280",
  "border": "#A7F3D0",
  "success": "#10B981",
  "warning": "#F59E0B",
  "error": "#EF4444"
}
```

**NGO Warm**
```json
{
  "primary": "#EA580C",
  "secondary": "#C2410C",
  "accent": "#0D9488",
  "background": "#FFFBEB",
  "surface": "#FFF7ED",
  "text": "#431407",
  "text_muted": "#9A3412",
  "border": "#FED7AA",
  "success": "#059669",
  "warning": "#D97706",
  "error": "#DC2626"
}
```

---

## How to Use This Guide

1. Determine the sector from Phase 1 discovery
2. Present 2-3 matching palettes to the user
3. If the user has corporate colors, use them as `primary` and/or `secondary`, then derive the rest
4. If the user provided a design reference (Phase 2), extract colors from that reference and use them instead of these presets
5. Always show the user a summary of the chosen palette before applying with `klytos_set_colors`
