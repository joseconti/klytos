# Taxonomy terms screen — extension points

Manifest entry 32. The screen lives at `installer/admin/taxonomy.php` and is
always scoped to ONE taxonomy of ONE post type: it refuses to render without
both `post_type` and `taxonomy` in the query string, because that is where the
product stores a taxonomy — inside the post type's own record.

It carries the **add-term form** (the `record-form` half of §32) and a list of
the taxonomy's terms. The five-column terms **table** of §32 is not built: its
`grid-template-columns` are DR-006's, and its `Count` column has no data source
in the product at all — no record anywhere is ever associated with a term.

Every hook below fires on `installer/admin/taxonomy.php` only.

## Actions

All six receive the same two arguments, in this order:

| Argument | Type | Meaning |
|---|---|---|
| `$postTypeId` | `string` | The post type the taxonomy belongs to |
| `$taxonomyId` | `string` | The taxonomy being viewed |

| Action | Where it fires |
|---|---|
| `admin.taxonomy.before` | At the top of the screen, above the success line and the error summary |
| `admin.taxonomy.before_cards` | Inside the card stack, above the add-term card |
| `admin.taxonomy.before_fields` | Inside the add-term `<form>`, above the Name field |
| `admin.taxonomy.after_fields` | Inside the add-term `<form>`, below the last field |
| `admin.taxonomy.after_cards` | Inside the card stack, below the Terms card |
| `admin.taxonomy.after` | After the card stack, at the tail of the screen |

Anything echoed from `before_fields` / `after_fields` is **inside the form**, so
a control added there posts with it. The handler reads only the fields it
knows, so an extra control needs its own `term.before_save` listener to be
stored.

```php
klytos_add_action( 'admin.taxonomy.after_fields', function ( string $postTypeId, string $taxonomyId ): void {
    if ( $taxonomyId !== 'product-category' ) {
        return;
    }
    echo '<p class="k-hint">Product categories drive the shop filters.</p>';
} );
```

## Filters

### `admin.taxonomy.parent_options`

The options offered by the **Parent** select. The select is rendered only when
the taxonomy is `hierarchical`, so this filter runs only then.

| Argument | Type | Meaning |
|---|---|---|
| `$options` | `array<int,array{slug:string,name:string}>` | One entry per existing term, in the manager's order |
| `$postTypeId` | `string` | The post type the taxonomy belongs to |
| `$taxonomyId` | `string` | The taxonomy being viewed |

Returns the array to render. The empty "no parent" option is not in this list
and cannot be removed by the filter: a hierarchical term must be able to have
no parent, and hiding that choice would make the first term of a taxonomy
impossible to create.

A slug returned here that is not a real term of this taxonomy is refused by the
handler with a field-level error, so the filter cannot smuggle in a parent the
store would reject.

```php
// Only top-level terms may be chosen as a parent — a two-level taxonomy.
klytos_add_filter( 'admin.taxonomy.parent_options', function ( array $options, string $postTypeId, string $taxonomyId ): array {
    $manager = klytos_app()->getPostTypeManager();

    return array_values( array_filter( $options, function ( array $option ) use ( $manager, $postTypeId, $taxonomyId ): bool {
        $term = $manager->getTerm( $postTypeId, $taxonomyId, $option['slug'] );
        return ( $term['parent'] ?? '' ) === '';
    } ) );
} );
```

## Related

- `term.before_save` / `term.after_save` / `term.before_delete` /
  `term.after_delete` — the manager's own hooks, which fire wherever a term is
  written, not only from this screen. See `docs/api/INDEX.md`.
- `admin.content_model.*` — entry 19 (`installer/admin/post-types.php`), which
  creates and deletes the taxonomies whose terms this screen edits, and which
  links each one here. It has no reference page of its own; its rows are in
  `docs/api/INDEX.md`.
