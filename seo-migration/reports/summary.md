# SEO Migration Report — net-bazar.kz
Generated: 2026-10-12
Source: Satu.kz export + sitemap

---

## Product Matching Summary

| Metric | Count |
|--------|-------|
| Satu sitemap product URLs | 3802 |
| Excel export rows (total) | 5702 |
| Excel rows with Satu ID | 5702 |
| Excel rows **without SKU** | 25 |
| **Matched** (sitemap URL → Excel → SKU) | **3780** |
| Sitemap rows without SKU in Excel | 22 |
| Excel products **not in sitemap** (not published) | 1900 |
| Duplicate SKU entries in Excel | 0 |
| Satu IDs in sitemap not found in Excel | 0 |

> All 3802 sitemap URLs were found in the Excel export.
> 3780 of them have a valid SKU (article number) for Laravel matching.
> 22 sitemap rows have no article/SKU — these need manual review.

**DB verification skipped**: local MySQL is not running.
Run on production to verify which SKUs are present=1 in `products` table.

---

## Category Matching Summary

| Metric | Count |
|--------|-------|
| Satu sitemap category URLs | 94 |
| Excel groups rows | 188 |
| Category URLs found in Excel | 87 |
| Category URLs **not** in Excel | 7 |
| Unique Satu groups referenced by products in sitemap | 115 |

### 7 Category URLs not found in Excel groups sheet

- `/g8985220-elektricheskie-vstraivaemye-varochnye` (satu_id=8985220)
- `/g9133790-stiralnye-mashiny` (satu_id=9133790)
- `/g9161280-prisadki` (satu_id=9161280)
- `/g9161630-miksery-blendery-izmelchiteli` (satu_id=9161630)
- `/g9183650-pechi-grili` (satu_id=9183650)
- `/g9268570-dlya-posetitelej` (satu_id=9268570)
- `/g9568590-konditsionery` (satu_id=9568590)

---

## Output Files

| File | Description |
|------|-------------|
| `product_mapping.csv` | 3802 rows: old_url, satu_id, sku, status (MATCHED/NO_SKU) |
| `category_candidates.csv` | 94 rows: old_group_url, group_name, manual kaspi candidate |
| `summary.md` | This file |

---

## SEO URL Format Proposal

### Products

**Recommended canonical URL**: `/product/{sku}`

Laravel already uses this format. No change needed.

**301 redirects needed** (Satu → Laravel):
```
/p{satu_id}-{slug}.html  →  301  →  /product/{sku}
```

Route entries will be generated from `product_mapping.csv` (status=MATCHED).

**Example redirects:**

| Satu URL | Laravel URL |
|----------|-------------|
| `/p104372776-lavr-ln2137-036.html` | `/product/ln2137` |
| `/p{id}-samsung-sbs2795e.html` | `/product/sbs2795e` |
| `/p{id}-bosch-fvdi6136.html` | `/product/fvdi6136` |
| `/p{id}-samsung-vf96t50bx3.html` | `/product/vf96t50bx3` |
| `/p{id}-gs-bloc-900-white.html` | `/product/gs-bloc-gs-900-white` |

> Note: SKUs with spaces/special chars are already handled by `slug_sku()` normalizer:
> `GS BLOC GS 900 WHITE` → `gs-bloc-gs-900-white`
> `BIMO 15.01N WH` → `bimo-15-01n-wh`

### Categories

**Current Laravel URL**: `/catalog?category={kaspi_id}`
**Recommended**: keep as canonical, add 301 from Satu group URLs.

```
/g{satu_group_id}-{slug}  →  301  →  /catalog?category={kaspi_id}
```

**Sample mappings (10 confirmed):**

| Satu URL | Laravel Category |
|----------|-----------------|
| `/g9118427-otdelnostoyaschij-holodilnik` | Kaspi: Холодильники отдельностоящие |
| `/g9133790-stiralnye-mashiny` | Kaspi: Стиральные машины |
| `/g9161922-vytyazhki` | Kaspi: Вытяжки |
| `/g9526888-mikrovolnovye-pechi` | Kaspi: Микроволновые печи |
| `/g9320471-kulery-dlya-vody` | Kaspi: Кулеры для воды |
| `/g9254752-dispensery-kulery-dlya` | Kaspi: Диспенсеры/Кулеры |
| `/g9568591-duhovye-shkafy` | Kaspi: Духовые шкафы |
| `/g9568597-posudomoechnye-mashiny` | Kaspi: Посудомоечные машины |
| `/g9118424-vstraivaemyj-holodilnik` | Kaspi: Встраиваемые холодильники |
| `/g8531101-ofisnye-kresla-almaty` | Kaspi: Офисные кресла |

Full mapping in `category_candidates.csv`.

---

## Next Steps (not yet implemented)

1. **Verify 3780 SKUs against production DB** (`products` table, `present=1` filter)
2. **Check `catalog_sku_aliases`** for renamed SKUs (counts unknown until DB available)
3. **Implement 301 redirects** in `routes/web.php` or via middleware lookup table
4. **Map 94 Satu categories → Kaspi IDs** (87 confirmed in Excel, 7 need manual lookup)
5. **Handle 22 NO_SKU sitemap URLs** — find correct article numbers manually
6. **Meta tags**: import `HTML_заголовок` / `HTML_описание` from Excel into `products` table

---

## Risk Assessment

| Risk | Severity | Notes |
|------|----------|-------|
| 1900 products in Excel but not in sitemap | Low | Not published on Satu, no SEO equity to preserve |
| 22 sitemap URLs without SKU | Medium | ~0.6% of URLs, need manual SKU lookup |
| 7 category URLs not in Excel | Low | Minor categories, manual Kaspi mapping needed |
| SKU format with spaces | Low | Handled by normalizer (`GS BLOC` → `gs-bloc`) |
| DB unavailable for verification | High | Must run final check on production before DNS switch |
