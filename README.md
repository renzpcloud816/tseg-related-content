# 📌 TSEG Related Content Shortcode User Guide

The `[tseg-related-content]` shortcode lets you display **related posts, pages, or custom post types** filtered by **categories** and **locations**.  
It supports includes, excludes, multiple layouts, and responsive columns.

---

## 🔹 Basic Usage

```text
[tseg-related-content]
```

This will display a default list of related posts.

---

## 🔹 Attributes

| Attribute      | Default    | Description                                                                 | Example                                                                 |
|----------------|------------|-----------------------------------------------------------------------------|-------------------------------------------------------------------------|
| `type`         | `post`     | Post type to query (`post`, `page`, or CPT slug).                           | `type="page"`                                                           |
| `category`     | *(empty)*  | Filter by category slugs (comma separated). Prefix with `-` to exclude.     | `category="premises-liability,-medical-malpractice"`                    |
| `location`     | *(empty)*  | Filter by location taxonomy slugs (comma separated). Same exclude syntax.   | `location="southern-california,-beverly-hills"`                         |
| `limit`        | `5`        | Number of posts to display.                                                 | `limit="8"`                                                             |
| `relation`     | `AND`      | How multiple taxonomy filters are combined: `AND` or `OR`.                  | `relation="OR"`                                                         |
| `operator`     | `IN`       | Operator for includes: `IN`, `AND`, or `NOT IN`.                           | `operator="AND"`                                                        |
| `orderby`      | `date`     | Order by field (`date`, `title`, `name`, etc.). Pages default to `title`.   | `orderby="title"`                                                       |
| `order`        | `DESC`     | Order direction (`ASC` or `DESC`).                                          | `order="ASC"`                                                           |
| `display`      | `list`     | Display mode: `list`, `grid`, or `slider`.                                 | `display="slider"`                                                      |
| `columns`      | `4,3,2,1`  | Responsive columns (lg, md, sm, xs). Used for grid & slider.                | `columns="5,3,2,1"`                                                     |

---

## 🔹 Examples

### 1. Simple related posts list
```text
[tseg-related-content type="post" category="premises-liability" limit="5"]
```

### 2. Exclude terms
```text
[tseg-related-content type="page" category="car-accidents,-slip-and-fall" location="southern-california,-beverly-hills" limit="10"]
```

### 3. Grid layout
```text
[tseg-related-content display="grid" columns="4,3,2,1" limit="8"]
```

### 4. Slider layout
```text
[tseg-related-content display="slider" columns="5,3,2,1" limit="12"]
```

---

## 🔹 Features Recap

- ✅ Works with posts, pages, and CPTs.  
- ✅ Supports **Yoast Primary Category** fallback if category is not set.  
- ✅ Include/exclude syntax using `-term`.  
- ✅ Display modes: **list, grid, slider**.  
- ✅ Responsive columns with the `columns` attribute.  
- ✅ Slider uses **per-instance data-slick settings**, initialized by one global script.  

---

## 🔹 Tips

- Always use **slugs** (not names) for `category` and `location`.  
- If your site uses **custom location taxonomy**, adjust the code `taxonomy_exists('locations')` to match your taxonomy name.  
- For grid/slider, tweak `columns` depending on screen sizes.  
- Use exclusions (`-slug`) to refine results (e.g., exclude a city within a region).  

