<?php
/**
 * TSEG Related Content Shortcode (procedural)
 *
 * Shortcode:
 * [tseg-related-content
 *   type="post|page|cpt"
 *   category="premises-liability,-medical-malpractice"
 *   location="southern-california,-beverly-hills"
 *   limit="8"
 *   relation="AND|OR"
 *   operator="IN|AND|NOT IN"
 *   orderby="date|title|name|menu_order|meta_value|meta_value_num|rand"
 *   order="ASC|DESC"
 *   display="list|grid|slider"
 *   columns="4,3,2,1"  ; lg,md,sm,xs (grid & slider)
 * ]
 *
 * ## Attributes
 *
 * | Attribute      | Default    | Description                                                                                 |
 * |----------------|------------|---------------------------------------------------------------------------------------------|
 * | type           | post       | Post type to query (`post`, `page`, or CPT slug).                                           |
 * | category       | (empty)    | Category **slugs** (CSV). Prefix a term with `-` to exclude.                                |
 * | location       | (empty)    | Location taxonomy **slugs** (CSV). Same exclude syntax as category.                         |
 * | limit          | 5          | Number of items to display.                                                                 |
 * | relation       | AND        | How taxonomy filters combine: `AND` or `OR`.                                                |
 * | operator       | IN         | Operator for **includes**: `IN`, `AND`, or `NOT IN`.                                        |
 * | orderby        | date       | Field to order by (`date`, `title`, `name`, etc.). Pages default to `title` if not set.     |
 * | order          | DESC       | Sort direction: `ASC` or `DESC`.                                                            |
 * | display        | list       | Layout: `list`, `grid`, or `slider`.                                                        |
 * | columns        | 4,3,2,1    | Responsive columns for grid/slider: `lg,md,sm,xs`.                                          |
 *
 * ### Notes
 * - Category & location values are treated as **slugs** (switch WP_Query `field` to `name` if you prefer names).
 * - Exclusions always use `NOT IN`. For the *location include*, `include_children` is `true` so a parent region includes its children; you can still exclude specific cities.
 * - For the slider, enqueue Slick’s JS/CSS and add a single global init:
 *   `jQuery(function($){ $('.slider-related-pa').slick(); });`
 * 
 * @author  John Palo
 * @version 1.0.0
 */

if ( ! class_exists( 'TSEG_Related_Content' ) ) {

	class TSEG_Related_Content {

		/** @var string Shortcode tag */
		const SHORTCODE = 'tseg-related-content';

		/**
		 * Register hooks.
		 *
		 * @return void
		 */
		public static function register() : void {
			add_shortcode( self::SHORTCODE, [ __CLASS__, 'render_shortcode' ] );
		}

		/**
		 * Shortcode callback.
		 *
		 * @param array<string,mixed> $raw_atts Raw shortcode attributes.
		 * @return string HTML
		 */
		public static function render_shortcode( $raw_atts ) : string {
			$atts = shortcode_atts( [
				'type'       => 'post',      // post, page, or any CPT
				'category'   => '',          // CSV of slugs; support -term for exclude
				'location'   => '',          // CSV of slugs; support -term for exclude
				'location_tax' => '',        // optional explicit taxonomy slug to use for "location"
				'limit'      => 5,
				'relation'   => 'AND',       // relation BETWEEN taxonomies
				'operator'   => 'IN',        // IN | AND | NOT IN (applies to includes)
				'orderby'    => 'date',
				'order'      => 'DESC',
				'display'    => 'list',      // list | grid | slider
				'columns'    => '4,3,2,1',   // lg,md,sm,xs
			], $raw_atts, self::SHORTCODE );

			// Post type sanity.
			$post_type = sanitize_key( $atts['type'] );
			if ( ! post_type_exists( $post_type ) ) {
				$post_type = 'post';
			}

			// Order logic (respect user-provided orderby/order; default title ASC for pages if not overridden).
			$post_order   = ( strtoupper( (string) $atts['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';
			$post_orderby = ! empty( $raw_atts['orderby'] ) ? sanitize_key( $atts['orderby'] ) : 'date';

			if ( 'page' === $post_type && empty( $raw_atts['orderby'] ) ) {
				$post_orderby = 'title';
				$post_order   = 'ASC';
			}
			if ( 'date' === $post_orderby && empty( $raw_atts['order'] ) ) {
				$post_order = 'DESC';
			}

			$post_id = get_the_ID();

			// Default CATEGORY from current post if empty (Yoast primary -> else all).
			if (
				'' === $atts['category'] &&
				taxonomy_exists( 'category' ) &&
				is_object_in_taxonomy( $post_type, 'category' )
			) {
				$atts['category'] = self::yoast_primary_or_all_slugs( (int) $post_id, 'category' );
			}

			// Build tax_query.
			$tax_query = [];
			$relation  = ( strtoupper( (string) $atts['relation'] ) === 'OR' ) ? 'OR' : 'AND';
			$operator  = in_array( strtoupper( (string) $atts['operator'] ), [ 'IN', 'AND', 'NOT IN' ], true )
				? strtoupper( (string) $atts['operator'] )
				: 'IN';

			// Category include/exclude.
			if ( taxonomy_exists( 'category' ) && is_object_in_taxonomy( $post_type, 'category' ) ) {
				$cat = self::parse_terms_csv( (string) $atts['category'] );

				if ( ! empty( $cat['include'] ) ) {
					$tax_query[] = [
						'taxonomy'         => 'category',
						'field'            => 'slug',
						'terms'            => $cat['include'],
						'operator'         => $operator, // IN | AND
						'include_children' => false,
					];
				}
				if ( ! empty( $cat['exclude'] ) ) {
					$tax_query[] = [ 'taxonomy' => 'category', 'operator' => 'EXISTS' ];
					$tax_query[] = [
						'taxonomy' => 'category',
						'field'    => 'slug',
						'terms'    => $cat['exclude'],
						'operator' => 'NOT IN',
					];
				}
			}

			// Resolve a "location-like" taxonomy (explicit override, or auto-detect attached to post type).
			$location_tax = self::resolve_location_taxonomy( $post_type, (string) $atts['location_tax'] );

			// Location include/exclude.
			if ( $location_tax && '' !== (string) $atts['location'] ) {
				$loc = self::parse_terms_csv( (string) $atts['location'] );

				if ( ! empty( $loc['include'] ) ) {
					$tax_query[] = [
						'taxonomy'         => $location_tax,
						'field'            => 'slug',
						'terms'            => $loc['include'],
						'operator'         => $operator, // IN | AND
						'include_children' => true,      // allow parent to include children; enables child exclusion later
					];
				}
				if ( ! empty( $loc['exclude'] ) ) {
					$tax_query[] = [ 'taxonomy' => $location_tax, 'operator' => 'EXISTS' ];
					$tax_query[] = [
						'taxonomy' => $location_tax,
						'field'    => 'slug',
						'terms'    => $loc['exclude'],
						'operator' => 'NOT IN',
					];
				}
			}

			if ( ! empty( $tax_query ) ) {
				$tax_query = array_merge( [ 'relation' => $relation ], $tax_query );
			}

			$args = [
				'post_type'           => $post_type,
				'post_status'         => 'publish',
				'posts_per_page'      => max( 1, (int) $atts['limit'] ),
				'post__not_in'        => [ (int) get_the_ID() ],
				'ignore_sticky_posts' => true,
				'orderby'             => $post_orderby,
				'order'               => $post_order,
			];

			if ( ! empty( $tax_query ) ) {
				$args['tax_query'] = $tax_query;
			}

			$q = new WP_Query( $args );
			if ( ! $q->have_posts() ) {
				return '';
			}

			// Prepare columns for grid/slider.
			$cols = self::parse_columns( (string) $atts['columns'] );

			// Dispatch rendering.
			$display = strtolower( trim( (string) $atts['display'] ) );
			$html    = self::render_dispatch( $q, $display, $cols );

			// Wrapper (keeps a stable parent class).
			return '<div class="tseg-related-content">' . $html . '</div>';
		}

		/**
		 * Return Yoast Primary term slug (if set) or a CSV of all term slugs assigned to the post.
		 *
		 * @param int    $post_id  Post ID.
		 * @param string $taxonomy Taxonomy slug.
		 * @return string CSV of slugs or empty string.
		 */
		protected static function yoast_primary_or_all_slugs( int $post_id, string $taxonomy ) : string {
			if ( class_exists( 'WPSEO_Primary_Term' ) ) {
				$primary    = new WPSEO_Primary_Term( $taxonomy, $post_id );
				$primary_id = (int) $primary->get_primary_term();
				if ( $primary_id ) {
					$term = get_term( $primary_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						return (string) $term->slug;
					}
				}
			}
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				return implode( ',', wp_list_pluck( $terms, 'slug' ) );
			}
			return '';
		}

		/**
		 * Split a CSV string into "include" and "exclude" arrays; trims, slugifies, unique.
		 * A leading "-" on a term means exclude.
		 *
		 * @param string $csv CSV input.
		 * @return array{include: string[], exclude: string[]}
		 */
		protected static function parse_terms_csv( string $csv ) : array {
			if ( '' === $csv ) {
				return [ 'include' => [], 'exclude' => [] ];
			}
			$items   = array_filter( array_map( 'trim', explode( ',', $csv ) ) );
			$include = [];
			$exclude = [];

			foreach ( $items as $item ) {
				if ( '' === $item ) {
					continue;
				}
				$is_excl = ( $item[0] === '-' );
				$term    = $is_excl ? substr( $item, 1 ) : $item;
				$term    = sanitize_title( $term ); // strict: slugs
				if ( '' === $term ) {
					continue;
				}
				if ( $is_excl ) {
					$exclude[] = $term;
				} else {
					$include[] = $term;
				}
			}

			return [
				'include' => array_values( array_unique( $include ) ),
				'exclude' => array_values( array_unique( $exclude ) ),
			];
		}

		/**
		 * Resolve a "location-like" taxonomy slug.
		 * Prefers explicit override, else finds a taxonomy attached to the post type whose name is
		 * "location"/"locations" or contains "location".
		 *
		 * @param string $post_type   Post type slug.
		 * @param string $override    Explicit taxonomy slug (optional).
		 * @return string Taxonomy slug or empty string if none found.
		 */
		protected static function resolve_location_taxonomy( string $post_type, string $override = '' ) : string {
			if ( $override ) {
				$try = sanitize_key( $override );
				if ( taxonomy_exists( $try ) && is_object_in_taxonomy( $post_type, $try ) ) {
					return $try;
				}
			}
			$tax_objs = get_object_taxonomies( $post_type, 'objects' );
			// Prefer exact matches.
			foreach ( $tax_objs as $tax ) {
				if ( in_array( $tax->name, [ 'location', 'locations' ], true ) ) {
					return $tax->name;
				}
			}
			// Else any taxonomy containing 'location'.
			foreach ( $tax_objs as $tax ) {
				if ( false !== strpos( $tax->name, 'location' ) ) {
					return $tax->name;
				}
			}
			return '';
		}

		/**
		 * Parse columns string "4,3,2,1" → [lg, md, sm, xs], clamped to 1..6.
		 *
		 * @param string $columns CSV values (lg,md,sm,xs).
		 * @return int[] Numeric columns array.
		 */
		protected static function parse_columns( string $columns ) : array {
			$defaults  = [ 4, 3, 2, 1 ];
			$parts     = array_map( 'trim', explode( ',', $columns ) );
			$resolved  = [];
			for ( $i = 0; $i < 4; $i++ ) {
				$v            = isset( $parts[ $i ] ) && is_numeric( $parts[ $i ] ) ? (int) $parts[ $i ] : $defaults[ $i ];
				$resolved[ $i ] = max( 1, min( 6, $v ) );
			}
			return $resolved;
		}

		/**
		 * Build Bootstrap row-cols class string from columns array.
		 *
		 * @param int[] $cols [lg, md, sm, xs].
		 * @return string Class name.
		 */
		protected static function bootstrap_row_classes( array $cols ) : string {
			list( $lg, $md, $sm, $xs ) = $cols;
			return sprintf(
				'row row-cols-%d row-cols-sm-%d row-cols-md-%d row-cols-lg-%d g-3',
				$xs,
				$sm,
				$md,
				$lg
			);
		}

		/**
		 * Render dispatcher.
		 *
		 * @param WP_Query $q       Query with results.
		 * @param string   $display 'list' | 'grid' | 'slider'.
		 * @param int[]    $cols    Columns array [lg,md,sm,xs].
		 * @return string HTML
		 */
		protected static function render_dispatch( WP_Query $q, string $display, array $cols ) : string {
			$display = in_array( $display, [ 'list', 'grid', 'slider' ], true ) ? $display : 'list';
			if ( 'grid' === $display ) {
				return self::render_grid( $q, $cols );
			}
			if ( 'slider' === $display ) {
				return self::render_slider( $q, $cols );
			}
			return self::render_list( $q );
		}

		/**
		 * Render List view.
		 *
		 * @param WP_Query $q Query.
		 * @return string HTML
		 */
		protected static function render_list( WP_Query $q ) : string {
			ob_start(); ?>
			<ol class="tseg-related-list list-unstyled m-0">
				<?php while ( $q->have_posts() ) : $q->the_post();
					$title = get_field( 'practice_area_page_title', get_the_ID() ) ?: get_the_title(); ?>
					<li class="tseg-related-item border-bottom py-2">
						<a href="<?= esc_url( get_the_permalink() ); ?>"
						   class="link-arrow link-arrow-circle d-flex align-items-center gap-2">
							<span><?= esc_html( $title ); ?></span>
						</a>
					</li>
				<?php endwhile; wp_reset_postdata(); ?>
			</ol>
			<?php
			return ob_get_clean();
		}

		/**
		 * Render Grid view.
		 *
		 * @param WP_Query $q    Query.
		 * @param int[]    $cols Columns [lg,md,sm,xs].
		 * @return string HTML
		 */
		protected static function render_grid( WP_Query $q, array $cols ) : string {
			$row_classes = self::bootstrap_row_classes( $cols );
			ob_start(); ?>
			<div class="<?= esc_attr( $row_classes ); ?>">
				<?php while ( $q->have_posts() ) : $q->the_post();
					$title = get_field( 'practice_area_page_title', get_the_ID() ) ?: get_the_title();
					$thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium' ); ?>
					<div class="col">
						<article class="card h-100 shadow-sm">
							<?php if ( $thumb ) : ?>
								<a href="<?= esc_url( get_the_permalink() ); ?>" class="ratio ratio-16x9">
									<img src="<?= esc_url( $thumb ); ?>" alt="" class="card-img-top object-fit-cover">
								</a>
							<?php endif; ?>
							<div class="card-body">
								<h3 class="h6 card-title mb-0">
									<a href="<?= esc_url( get_the_permalink() ); ?>" class="stretched-link text-decoration-none">
										<?= esc_html( $title ); ?>
									</a>
								</h3>
							</div>
						</article>
					</div>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Render Slider view (Slick-ready via data-slick; needs a global $('.slider-related-pa').slick();).
		 *
		 * @param WP_Query $q    Query.
		 * @param int[]    $cols Columns [lg,md,sm,xs].
		 * @return string HTML
		 */
		protected static function render_slider( WP_Query $q, array $cols ) : string {
			$uid = uniqid( 'slider_pa_' );

			$settings = [
				'slidesToShow'   => (int) $cols[0],
				'slidesToScroll' => 1,
				'arrows'         => true,
				'dots'           => true,
				'autoplay'       => true,
				'autoplaySpeed'  => 2000,
				'responsive'     => [
					[ 'breakpoint' => 1400, 'settings' => [ 'slidesToShow' => (int) $cols[1] ] ],
					[ 'breakpoint' => 992,  'settings' => [ 'slidesToShow' => (int) $cols[2] ] ],
					[ 'breakpoint' => 768,  'settings' => [ 'slidesToShow' => (int) $cols[3] ] ],
				],
			];
			$json = wp_json_encode( $settings );

			ob_start(); ?>
			<div class="<?= esc_attr( $uid ); ?> slider-related-pa slider-h100" data-slick='<?= esc_attr( $json ); ?>'>
				<?php while ( $q->have_posts() ) : $q->the_post();
					$title = get_field( 'practice_area_page_title', get_the_ID() ) ?: get_the_title();
					$link  = get_permalink();
					$icon  = get_field( 'practice_area_page_icon', get_the_ID() ) ?: '/wp-content/uploads/2025/05/gad-logo-2.png';
					$image = wp_get_attachment_image_url( get_field( 'practice_area_page_image', get_the_ID() ), 'medium' )
						?: wp_get_attachment_image_url( 122, 'medium' ); ?>
					<a href="<?= esc_url( $link ); ?>" class="text-decoration-none">
						<div class="practice-areas-card d-flex flex-column align-items-center gap-3 border p-4 text-decoration-none h-100 text-center mx-2 bg-gold-20 bg-image bg-overlay" data-bg="<?= esc_url( $image ); ?>">
							<div class="rounded-circle bg-white p-3 d-flex flex-row align-items-center justify-content-center" style="min-width:80px;min-height:80px;">
								<img class="img-fluid object-fit-contain" src="<?= esc_url( $icon ); ?>" alt="<?= esc_attr( $title ); ?>" width="50" height="50">
							</div>
							<h3 class="h5 text-white"><?= esc_html( $title ); ?></h3>
						</div>
					</a>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
			<?php
			return ob_get_clean();
		}
	}

	// Initialize.
	TSEG_Related_Content::register();
}
