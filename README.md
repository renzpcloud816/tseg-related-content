User Guide: [tseg-related-content] Shortcode
This guide explains how to use the [tseg-related-content] shortcode to display dynamic lists, grids, or sliders of related content on your website.

Basic Usage
At its simplest, the shortcode will find posts that share the same category as the current page and display them as a list.

[tseg-related-content]

Parameters (Attributes)
You can customize the output by adding parameters (also called attributes) to the shortcode.

type
Controls the type of content to display.

Default: post

Values: post, page, or the slug of any Custom Post Type (e.g., practice_area).

Example: Show pages instead of posts.

[tseg-related-content type="page"]

display
Controls how the content is formatted.

Default: list

Values: list, grid, slider

Example: Display the related posts in a grid.

[tseg-related-content display="grid"]

limit
Sets the maximum number of items to show.

Default: 5

Values: Any whole number.

Example: Show up to 10 items.

[tseg-related-content limit="10"]

category and location
Filter content by taxonomy terms. You can provide one or more slugs, separated by commas.

To EXCLUDE a term, add a minus sign (-) before the slug.

Example: Show posts from car-accidents OR hit-and-run.

[tseg-related-content category="car-accidents,hit-and-run"]

Example: Show posts from car-accidents but EXCLUDE any that are also in legal-updates.

[tseg-related-content category="car-accidents,-legal-updates"]

orderby and order
Controls the sorting of the results.

orderby Default: date (or title for pages)

orderby Values: date, title, rand (for random), menu_order.

order Default: DESC (Descending)

order Values: DESC, ASC (Ascending).

Example: Show pages in alphabetical order.

[tseg-related-content type="page" orderby="title" order="ASC"]

columns
Defines the number of columns for grid and slider displays at different screen sizes. The format is LG,MD,SM,XS.

Default: 4,3,2,1 (4 on large screens, 3 on medium, etc.)

Values: A comma-separated list of 4 numbers.

Example: Show a grid with 3 columns on large screens and 2 on medium screens.

[tseg-related-content display="grid" columns="3,2,1,1"]

relation
Controls the logic when you use BOTH category and location.

Default: AND

Values: AND (must match both), OR (can match either).

Example: Show posts that are in the los-angeles location OR the car-accidents category.

[tseg-related-content location="los-angeles" category="car-accidents" relation="OR"]

Practical Examples
1. Grid of Practice Areas in a Specific Location
This will display up to 6 "Practice Area" posts from the "Riverside" location, sorted alphabetically, in a 3-column grid.

[tseg-related-content type="practice_area" location="riverside" limit="6" display="grid" columns="3,2,1,1" orderby="title" order="ASC"]

2. Slider of Recent Blog Posts
This will display the 8 most recent posts in a slider, automatically using the categories of the current post.

[tseg-related-content type="post" limit="8" display="slider" columns="3,2,1,1"]

3. Simple List of Pages Excluding the "Contact Us" Page
This will list all pages alphabetically but skip the one with the slug contact-us. (Note: This requires knowing the page slug).

[tseg-related-content type="page" limit="99" orderby="title" order="ASC"]

(Excluding specific posts/pages by slug is not a direct feature, but you can achieve similar results with category exclusion.)
