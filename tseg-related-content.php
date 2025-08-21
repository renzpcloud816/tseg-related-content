<?php
// Ensure functions don't already exist to prevent fatal errors.
if (!function_exists('tseg_related_content_primary_or_all_slugs')) {

    /**
     * Gets the primary term slug from Yoast SEO if available.
     *
     * If the Yoast SEO plugin is active and a primary term is set for the given
     * post and taxonomy, its slug is returned. Otherwise, it falls back to a
     * comma-separated string of all assigned term slugs.
     *
     * @param int    $post_id  The ID of the post to check.
     * @param string $taxonomy The slug of the taxonomy to query (e.g., 'category').
     * @return string A single primary term slug or a comma-separated list of all slugs. Returns an empty string if no terms are found.
     */
    function tseg_related_content_primary_or_all_slugs($post_id, $taxonomy) {
        if (class_exists('WPSEO_Primary_Term')) {
            $primary    = new WPSEO_Primary_Term($taxonomy, $post_id);
            $primary_id = (int) $primary->get_primary_term();
            if ($primary_id) {
                $term = get_term($primary_id, $taxonomy);
                if ($term && !is_wp_error($term)) {
                    return $term->slug;
                }
            }
        }
        $terms = get_the_terms($post_id, $taxonomy);
        if (!is_wp_error($terms) && $terms) {
            return implode(',', wp_list_pluck($terms, 'slug'));
        }
        return '';
    }

    /**
     * Parses a comma-separated string of column counts for Bootstrap breakpoints.
     *
     * Sanitizes a string like "4,3,2,1" into a validated array of integers
     * representing columns for [lg, md, sm, xs] breakpoints. Provides safe defaults.
     *
     * @param string $columns A CSV string of column counts.
     * @return int[] An array of four integers for [lg, md, sm, xs].
     */
    function tseg_parse_columns(string $columns): array {
        $defaults = [4, 3, 2, 1];
        $parts    = array_map('trim', explode(',', $columns));
        $cols     = [];
        for ($i = 0; $i < 4; $i++) {
            $v         = isset($parts[$i]) && is_numeric($parts[$i]) ? (int) $parts[$i] : $defaults[$i];
            $cols[$i] = max(1, min(6, $v)); // Clamp values between 1 and 6
        }
        return $cols; // [lg, md, sm, xs]
    }

    /**
     * Generates Bootstrap 5 row classes from an array of column counts.
     *
     * @param int[] $cols An array of four integers for [lg, md, sm, xs].
     * @return string The complete string of Bootstrap row classes.
     */
    function tseg_bootstrap_row_classes(array $cols): string {
        list($lg, $md, $sm, $xs) = $cols;
        return sprintf(
            'row row-cols-%d row-cols-sm-%d row-cols-md-%d row-cols-lg-%d g-3',
            $xs,
            $sm,
            $md,
            $lg
        );
    }

    /**
     * Renders a WP_Query result as a simple HTML list.
     *
     * @param WP_Query $q The WP_Query object containing the posts to render.
     * @return string The generated HTML for the list.
     */
    function tseg_render_list(WP_Query $q): string {
        ob_start(); ?>
        <ol class="tseg-related-list list-unstyled m-0">
            <?php while ($q->have_posts()) : $q->the_post();
                $rp_title = get_field('practice_area_page_title', get_the_ID()) ?: get_the_title(); ?>
                <li class="tseg-related-item border-bottom py-2">
                    <a href="<?= esc_url(get_the_permalink()); ?>" class="link-arrow link-arrow-circle d-flex align-items-center gap-2">
                        <span><?= esc_html($rp_title); ?></span>
                    </a>
                </li>
            <?php endwhile;
            wp_reset_postdata(); ?>
        </ol>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders a WP_Query result as a Bootstrap 5 grid.
     *
     * @param WP_Query $q    The WP_Query object containing the posts to render.
     * @param int[]    $cols An array of column counts for responsive breakpoints.
     * @return string The generated HTML for the grid.
     */
    function tseg_render_grid(WP_Query $q, array $cols): string {
        $row_classes = tseg_bootstrap_row_classes($cols);
        ob_start(); ?>
        <div class="<?= esc_attr($row_classes); ?>">
            <?php while ($q->have_posts()) : $q->the_post();
                $pa_title = get_field('practice_area_page_title', get_the_ID()) ?: get_the_title();
                $pa_link  = get_permalink();
                $pa_icon  = get_field('practice_area_page_icon', get_the_ID()) ?: '/wp-content/uploads/2025/05/gad-logo-2.png';
                $pa_image = wp_get_attachment_image_url(get_field('practice_area_page_image', get_the_ID()), 'medium') ?: wp_get_attachment_image_url(122, 'medium');
            ?>
                <div class="col">
                    <a href="<?= esc_url($pa_link); ?>" class="text-decoration-none d-block h-100">
                        <div class="practice-areas-card d-flex flex-column align-items-center gap-3 border p-4 text-decoration-none w-100 h-100 text-center bg-gold-20 bg-image bg-overlay" data-bg="<?= esc_url($pa_image); ?>">
                            <div class="rounded-circle bg-white d-flex flex-row align-items-center justify-content-center" style="min-width:50px;min-height: 50px;max-width:50px;max-height: 50px;">
                                <img class="img-fluid object-fit-contain" src="<?= esc_url($pa_icon); ?>" alt="<?= esc_attr($pa_title); ?>" width="32" height="32">
                            </div>
                            <h3 class="h6 text-white"><?= esc_html($pa_title); ?></h3>
                        </div>
                    </a>
                </div>
            <?php endwhile;
            wp_reset_postdata(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders a WP_Query result as a Slick slider.
     *
     * @param WP_Query $q    The WP_Query object containing the posts to render.
     * @param int[]    $cols An array of column counts used for slider settings.
     * @return string The generated HTML for the slider.
     */
    function tseg_render_slider(WP_Query $q, array $cols): string {
        $uid      = uniqid('slider_pa_'); // unique class
        $settings = [
            'slidesToShow'   => (int) $cols[0],
            'slidesToScroll' => 1,
            'arrows'         => true,
            'dots'           => true,
            'autoplay'       => true,
            'autoplaySpeed'  => 2000,
            'responsive'     => [
                ['breakpoint' => 1400, 'settings' => ['slidesToShow' => (int) $cols[1]]],
                ['breakpoint' => 992,  'settings' => ['slidesToShow' => (int) $cols[2]]],
                ['breakpoint' => 768,  'settings' => ['slidesToShow' => (int) $cols[3]]],
            ],
        ];
        $json_settings = wp_json_encode($settings);

        ob_start(); ?>
        <div class="<?= esc_attr($uid); ?> slider-related-pa slider-h100" data-slick='<?= esc_attr($json_settings); ?>'>
            <?php while ($q->have_posts()) : $q->the_post();
                $pa_title = get_field('practice_area_page_title', get_the_ID()) ?: get_the_title();
                $pa_link  = get_permalink();
                $pa_icon  = get_field('practice_area_page_icon', get_the_ID()) ?: '/wp-content/uploads/2025/05/gad-logo-2.png';
                $pa_image = wp_get_attachment_image_url(get_field('practice_area_page_image', get_the_ID()), 'medium') ?: wp_get_attachment_image_url(122, 'medium');
            ?>
                <a href="<?= esc_url($pa_link); ?>" class="text-decoration-none">
                    <div class="practice-areas-card d-flex flex-column align-items-center gap-3 border p-4 text-decoration-none h-100 text-center mx-2 bg-gold-20 bg-image bg-overlay" data-bg="<?= esc_url($pa_image); ?>">
                        <div class="rounded-circle bg-white p-3 d-flex flex-row align-items-center justify-content-center" style="min-width: 80px; min-height: 80px;">
                            <img class="img-fluid object-fit-contain" src="<?= esc_url($pa_icon); ?>" alt="<?= esc_attr($pa_title); ?>" width="50" height="50">
                        </div>
                        <h3 class="h5 text-white"><?= esc_html($pa_title); ?></h3>
                    </div>
                </a>
            <?php endwhile;
            wp_reset_postdata(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Dispatches to the correct rendering function based on the display type.
     *
     * @param WP_Query $q       The WP_Query object to be rendered.
     * @param string   $display The desired display type ('list', 'grid', or 'slider').
     * @param string   $columns A CSV string of column counts.
     * @return string The HTML output from the selected rendering function.
     */
    function tseg_render_related_dispatch(WP_Query $q, string $display, string $columns): string {
        $display = in_array($display, ['list', 'grid', 'slider'], true) ? $display : 'list';
        $cols    = tseg_parse_columns($columns);
        if ($display === 'grid')   return tseg_render_grid($q, $cols);
        if ($display === 'slider') return tseg_render_slider($q, $cols);
        return tseg_render_list($q);
    }

    /**
     * Registers the [tseg-related-content] shortcode.
     *
     * @param array $raw_atts User-provided shortcode attributes.
     * 'type'     => (string) Post type to query (post, page, etc.).
     * 'category' => (string) CSV of category slugs. Prefix with '-' to exclude.
     * 'location' => (string) CSV of location slugs. Prefix with '-' to exclude.
     * 'limit'    => (int)    Max number of posts to show.
     * 'relation' => (string) 'AND' or 'OR' relation between taxonomies.
     * 'operator' => (string) 'IN', 'AND', 'NOT IN' for terms within a taxonomy.
     * 'orderby'  => (string) Field to order by (e.g., 'date', 'title', 'rand').
     * 'order'    => (string) 'ASC' or 'DESC'.
     * 'display'  => (string) 'list', 'grid', or 'slider'.
     * 'columns'  => (string) CSV of column counts for grid/slider, e.g., "4,3,2,1".
     * @return string The rendered HTML of the related content.
     */
    add_shortcode('tseg-related-content', function ($raw_atts) {
        $atts = shortcode_atts([
            'type'     => 'post',
            'category' => '',
            'location' => '',
            'limit'    => 5,
            'relation' => 'AND',
            'operator' => 'IN',
            'orderby'  => 'date',
            'order'    => 'DESC',
            'display'  => 'list',
            'columns'  => '4,3,2,1'
        ], $raw_atts, 'tseg-related-content');

        $post_type = sanitize_key($atts['type']);
        if (!post_type_exists($post_type)) $post_type = 'post';

        // --- Order logic ---
        $post_order   = (strtoupper($atts['order']) === 'ASC') ? 'ASC' : 'DESC';
        $post_orderby = !empty($raw_atts['orderby']) ? sanitize_key($atts['orderby']) : 'date';

        // Smart defaults for orderby based on post type
        if ($post_type === 'page' && empty($raw_atts['orderby'])) {
            $post_orderby = 'title';
            $post_order   = 'ASC';
        }
        if ($post_orderby === 'date' && empty($raw_atts['order'])) {
            $post_order = 'DESC';
        }

        $post_id = get_the_ID();

        // --- Auto-detect terms if attributes are empty ---
        if ($atts['category'] === '' && taxonomy_exists('category') && is_object_in_taxonomy($post_type, 'category')) {
            $atts['category'] = tseg_related_content_primary_or_all_slugs($post_id, 'category');
        }
        // Example for another taxonomy:
        // if ($atts['location'] === '' && taxonomy_exists('locations') && is_object_in_taxonomy($post_type, 'locations')) {
        //     $atts['location'] = tseg_related_content_primary_or_all_slugs($post_id, 'locations');
        // }

        // --- Helper to parse terms for inclusion and exclusion ---
        $parse_terms = function (string $csv): array {
            if ($csv === '') return ['include' => [], 'exclude' => []];
            $items   = array_filter(array_map('trim', explode(',', $csv)));
            $include = [];
            $exclude = [];
            foreach ($items as $item) {
                if ($item === '') continue;
                $is_excl = ($item[0] === '-');
                $term    = $is_excl ? substr($item, 1) : $item;
                $term    = sanitize_title($term);
                if ($term === '') continue;
                if ($is_excl) {
                    $exclude[] = $term;
                } else {
                    $include[] = $term;
                }
            }
            return [
                'include' => array_values(array_unique($include)),
                'exclude' => array_values(array_unique($exclude)),
            ];
        };

        // --- Build Taxonomy Query ---
        $tax_query = [];
        $relation  = (strtoupper($atts['relation']) === 'OR') ? 'OR' : 'AND';
        $operator  = in_array(strtoupper($atts['operator']), ['IN', 'AND', 'NOT IN'], true) ? strtoupper($atts['operator']) : 'IN';

        $taxonomies_to_check = ['category' => $atts['category'], 'locations' => $atts['location']];

        foreach ($taxonomies_to_check as $tax_slug => $term_string) {
            if (taxonomy_exists($tax_slug) && is_object_in_taxonomy($post_type, $tax_slug)) {
                $parsed = $parse_terms($term_string);
                if ($parsed['include']) {
                    $tax_query[] = [
                        'taxonomy'         => $tax_slug,
                        'field'            => 'slug',
                        'terms'            => $parsed['include'],
                        'operator'         => $operator,
                        'include_children' => false,
                    ];
                }
                if ($parsed['exclude']) {
                    $tax_query[] = [
                        'taxonomy' => $tax_slug,
                        'field'    => 'slug',
                        'terms'    => $parsed['exclude'],
                        'operator' => 'NOT IN',
                    ];
                }
            }
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = $relation;
        }

        // --- Build Final WP_Query Arguments ---
        $args = [
            'post_type'           => $post_type,
            'post_status'         => 'publish',
            'posts_per_page'      => (int) $atts['limit'],
            'post__not_in'        => [$post_id],
            'ignore_sticky_posts' => true,
            'orderby'             => $post_orderby,
            'order'               => $post_order,
        ];

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $q = new WP_Query($args);
        if (!$q->have_posts()) return '';

        $html = tseg_render_related_dispatch($q, strtolower(trim((string) $atts['display'])), (string) $atts['columns']);

        return '<div class="tseg-related-content mb-3">' . $html . '</div>';
    });
}
