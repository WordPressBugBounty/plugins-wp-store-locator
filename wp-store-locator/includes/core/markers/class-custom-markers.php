<?php
/**
 * Custom marker repository.
 *
 * Owns the wpsl_custom_markers option and the SVG generation for
 * custom markers. Lives in core so both the admin manager UI and the
 * frontend marker resolution can use it.
 *
 * @author Tijmen Smit
 * @since 3.0.0
 */

namespace WPSL\Core\Markers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Markers {

    const OPTION_NAME = 'wpsl_custom_markers';

    /**
     * Per-request memo of get_data_uri() results, keyed by marker id.
     * Invalidated per id by save_marker() / delete_marker().
     *
     * @since 3.0.0
     * @var   array
     */
    private $data_uri_cache = [];

    /**
     * Attachment meta key that marks an image as a reusable marker logo.
     *
     * @since 3.0.0
     */
    const LOGO_META_KEY = '_wpsl_marker_logo';

    /**
     * Per-instance cache for get_reusable_logos().
     *
     * @since 3.0.0
     * @var   array|null
     */
    private $reusable_logos = null;

    /**
     * Where a marker can be referenced from, outside the library itself.
     *
     * The single authority: get_reference_counts() counts through this map and
     * release_references() clears through it, so a new reference target is
     * added once. 'meta_type' is what WordPress calls the object (and, with
     * 'meta' appended, the $wpdb table property); 'column' is what a row is
     * counted by, so a store using one marker for both its states counts as one.
     *
     * @since 3.0.0
     * @var   array[]
     */
    const REFERENCE_META = [
        'categories' => [
            'meta_type' => 'term',
            'column'    => 'term_id',
            'keys'      => [ 'wpsl_category_marker', 'wpsl_category_marker_active' ],
        ],
        'locations'  => [
            'meta_type' => 'post',
            'column'    => 'post_id',
            'keys'      => [ 'wpsl_location_marker', 'wpsl_location_marker_active' ],
        ],
    ];

    /**
     * The markers settings keys a marker can be chosen for.
     *
     * @since 3.0.0
     * @var   string[]
     */
    const SETTING_SLOTS = [ 'start_marker', 'store_marker', 'active_marker' ];

    /**
     * Marker shapes the SVG builder understands.
     *
     * @var string[]
     */
    const ALLOWED_SHAPES = [ 'classic_pin', 'rounded_pin', 'open_pin', 'circle', 'square', 'shield', 'diamond', 'hexagon', 'speech_bubble', 'flag' ];

    /**
     * Shapes that can carry text ( a letter, a number, a character ) in
     * place of an icon: every shape but the open pin, whose centre is a
     * transparent hole with nothing behind the text.
     *
     * @since 3.0.0
     * @var   string[]
     */
    const LABEL_SHAPES = [ 'classic_pin', 'rounded_pin', 'circle', 'square', 'shield', 'diamond', 'hexagon', 'speech_bubble', 'flag' ];

    /**
     * The `size` a marker gets when nothing asked for one.
     *
     * @since 3.0.0
     * @var   int
     */
    const DEFAULT_SIZE = 38;

    /**
     * How much transparent margin the artwork box carries around a silhouette.
     *
     * @since 3.0.0
     * @var   int
     */
    const SHAPE_MARGIN = 6;

    /**
     * Geometry per shape, and the authority for it.
     *
     * - 'viewbox' is the artwork box size ( silhouette + margin ), in SVG units.
     * - 'aspect' is the box's width/height ratio. Width follows from it, so a
     *   1:1 shape stays square and a taller shape ( pins, shield ) keeps its
     *   proportions at every size - see get_render_size().
     * - 'anchor' is where the geographic point sits: 'bottom' or 'center'.
     *   Every shape is bottom-anchored now; 'center' is still understood for a
     *   filter supplying its own artwork.
     * - 'anchor_x' ( optional ) moves the anchor off the box's horizontal
     *   centre, in SVG units. Only the flag needs it, to plant its pole ( x 3 )
     *   on the location rather than the middle of its cloth. Published as
     *   data-wpsl-anchor-x on the SVG root.
     * - 'origin' is the viewBox's min-x/min-y. The margin opens the box around
     *   the silhouette instead of moving SHAPE_BODIES, so the glyph sits where
     *   it always sat.
     * - 'size_units' is how many SVG units the marker's `size` ( in px ) covers.
     *   It pins a saved marker's on-screen height while the box around it
     *   changes: get_render_size() scales the box by size / size_units.
     *
     * The margin is equal on all four sides. That is safe because the anchor is
     * the published tip ( data-wpsl-anchor-y, converted against the viewBox by
     * getCustomMarkerGeometry() ), not the box's bottom edge - so room below the
     * artwork is just room. It does not lift the pin off its coordinate, it
     * stops the drop shadow being clipped, and it centres the silhouette in its
     * box for the flat previews that can only centre the <img>.
     *
     * @since 3.0.0
     * @var   array
     */
    const SHAPES = [
        'classic_pin'   => [ 'viewbox' => [ 40, 54 ], 'aspect' => 40 / 54, 'anchor' => 'bottom', 'origin' => [ -4, -4 ], 'size_units' => 46 ],
        'rounded_pin'   => [ 'viewbox' => [ 40, 50 ], 'aspect' => 40 / 50, 'anchor' => 'bottom', 'origin' => [ -4, -4 ], 'size_units' => 42 ],
        'open_pin'      => [ 'viewbox' => [ 44, 56 ], 'aspect' => 44 / 56, 'anchor' => 'bottom', 'origin' => [ -6, -6 ], 'size_units' => 48 ],
        'circle'        => [ 'viewbox' => [ 40, 40 ], 'aspect' => 1, 'anchor' => 'bottom', 'origin' => [ -4, -4 ], 'size_units' => 32 ],
        'square'        => [ 'viewbox' => [ 40, 40 ], 'aspect' => 1, 'anchor' => 'bottom', 'origin' => [ -4, -4 ], 'size_units' => 32 ],
        'shield'        => [ 'viewbox' => [ 36, 44 ], 'aspect' => 36 / 44, 'anchor' => 'bottom', 'origin' => [ -2, -4 ], 'size_units' => 36 ],
        'diamond'       => [ 'viewbox' => [ 40, 40 ], 'aspect' => 1, 'anchor' => 'bottom', 'origin' => [ -4, -4 ], 'size_units' => 32 ],
        'hexagon'       => [ 'viewbox' => [ 36, 40 ], 'aspect' => 36 / 40, 'anchor' => 'bottom', 'origin' => [ -2, -4 ], 'size_units' => 32 ],
        'speech_bubble' => [ 'viewbox' => [ 40, 44 ], 'aspect' => 40 / 44, 'anchor' => 'bottom', 'origin' => [ -4, -2 ], 'size_units' => 36 ],
        'flag'          => [ 'viewbox' => [ 46, 50 ], 'aspect' => 46 / 50, 'anchor' => 'bottom', 'anchor_x' => 3, 'origin' => [ -4, -2 ], 'size_units' => 42 ],
    ];

    /**
     * The bounding box of each silhouette, as [ min-x, min-y, max-x, max-y ].
     *
     * @since 3.0.0
     * @var   array
     */
    const SHAPE_BOUNDS = [
        'classic_pin'   => [ 2, 2, 30, 44 ],
        'rounded_pin'   => [ 2, 2, 30, 40 ],
        'open_pin'      => [ 0, 0, 32, 44 ],
        'circle'        => [ 2, 2, 30, 30 ],
        'square'        => [ 2, 2, 30, 30 ],
        'shield'        => [ 4, 2, 28, 34 ],
        'diamond'       => [ 2, 2, 30, 30 ],
        'hexagon'       => [ 4, 2, 28, 30 ],
        'speech_bubble' => [ 2, 4, 30, 36 ],
        'flag'          => [ 2, 4, 36, 42 ],
    ];

    /**
     * The silhouette of each shape, as the opening of its SVG element.
     * 
     * @since 3.0.0
     * @var   array
     */
    const SHAPE_BODIES = [
        'classic_pin'   => '<path d="M16 2C8.268 2 2 8.268 2 16c0 12.5 14 28 14 28s14-17.5 14-28c0-7.732-6.268-14-14-14z"',
        'rounded_pin'   => '<path d="M16 2c7.732 0 14 6.268 14 14 0 9-10 20-14 24-4-4-14-15-14-24 0-7.732 6.268-14 14-14z"',
        'open_pin'      => '<path fill-rule="evenodd" d="M16 0C7.164 0 0 7.164 0 16c0 12.5 16 28 16 28s16-15.5 16-28c0-8.836-7.164-16-16-16zM16 5.5a10.5 10.5 0 0 0 0 21 10.5 10.5 0 0 0 0-21z"',
        'circle'        => '<circle cx="16" cy="16" r="14"',
        'square'        => '<rect x="2" y="2" width="28" height="28" rx="6"',
        'shield'        => '<path d="M16 2C9.5 2 4 4.5 4 4.5v13.5c0 6.5 4.5 12.5 12 16 7.5-3.5 12-9.5 12-16V4.5S22.5 2 16 2z"',
        'diamond'       => '<path d="M16 2 L30 16 L16 30 L2 16 Z"',
        'hexagon'       => '<path d="M16 2 L28 9 L28 23 L16 30 L4 23 L4 9 Z"',
        'speech_bubble' => '<path d="M8 4 H24 A6 6 0 0 1 30 10 V22 A6 6 0 0 1 24 28 H20 L16 36 L12 28 H8 A6 6 0 0 1 2 22 V10 A6 6 0 0 1 8 4 Z"',
        'flag'          => '<path d="M2 4 L36 4 L28 16 L36 28 L4 28 L4 42 L2 42 Z"',
    ];

    /**
     * How much of each shape's own 24-unit icon box the icon glyph fills.
     *
     * @since 3.0.0
     * @var   array
     */
    const ICON_SCALE = [
        'classic_pin'   => 0.58,
        'rounded_pin'   => 0.6,
        'open_pin'      => 0.65,
        'circle'        => 0.65,
        'square'        => 0.7,
        'shield'        => 0.62,
        'diamond'       => 0.5,
        'hexagon'       => 0.6,
        'speech_bubble' => 0.6,
        'flag'          => 0.58,
    ];

    /**
     * How much larger than an icon a text glyph draws on each shape.
     *
     * @since 3.0.0
     * @var   array
     */
    const TEXT_SCALE = [
        'classic_pin' => 1.5,
        'rounded_pin' => 1.5,
    ];

    /**
     * Which shapes each non-icon centre may be used with.
     *
     * A dot reads as a marker's centre on a pin, and as a bullseye on anything
     * else, so it is offered only where it makes sense. Published to the editor
     * through wpslMarkerStudio rather than retyped in JS, for the same reason
     * the shape tables are.
     *
     * @since 3.0.0
     * @var   array
     */
    const CENTER_SHAPES = [
        'dot' => [ 'classic_pin', 'rounded_pin' ],
    ];

    /**
     * Shapes that cut a transparent hole, and where that hole is.
     *
     * @since 3.0.0
     * @var   array
     */
    const HOLE_SHAPES = [
        'open_pin' => [ 'cx' => '16', 'cy' => '16', 'r' => '10.5' ],
    ];

    /**
     * The radius a dot centre is authored at, per shape, in SVG units of the
     * 24x24 icon coordinate space (the same box the icon glyph is drawn in).
     *
     * @since 3.0.0
     * @var   array
     */
    const CENTER_DOT_RADIUS = [
        'classic_pin' => '10.1633',
        'rounded_pin' => '9.8246',
    ];

    /**
     * Image types a marker logo may not be.
     *
     * Everything else WordPress accepts as an image is allowed: PNG and WebP
     * actually work (they carry alpha, so the shape shows through), but a JPEG
     * or GIF is the user's call - refusing them would take working logos off
     * existing markers.
     *
     * Matched on extension as well as mime type: the mime is whatever was
     * registered when the upload was allowed, and the plugins that allow it
     * don't all register the same string.
     *
     * @since 3.0.0
     * @var   array
     */
    const LOGO_BLOCKED = [
        'mimes'      => [ 'image/svg+xml', 'image/svg' ],
        'extensions' => [ 'svg', 'svgz' ],
    ];

    /**
     * The largest a marker logo may be, in bytes (256 KB).
     *
     * The logo is base64-embedded into the SVG data URI, so its full byte size
     * travels inside every marker that references it - on the map, in picker
     * previews, in the store search JSON.
     * 
     * @since 3.0.0
     * @var   int
     */
    const LOGO_MAX_BYTES = 262144;

    /**
     * The pixel size a shape's marker should be rendered at.
     *
     * @since  3.0.0
     * @param  string $shape The marker shape.
     * @param  int    $size  The marker's on-screen height in px. Defaults to
     *                       self::DEFAULT_SIZE for callers that only size a
     *                       shape, not a specific saved marker.
     * @return array         [ width, height ] of the artwork box, in pixels.
     */
    public static function get_render_size( $shape, $size = self::DEFAULT_SIZE ) {
        $geometry = isset( self::SHAPES[ $shape ] ) ? self::SHAPES[ $shape ] : self::SHAPES['classic_pin'];
        $height   = (int) round( (int) $size * $geometry['viewbox'][1] / $geometry['size_units'] );
        $width    = (int) round( $height * $geometry['aspect'] );

        return [ $width, $height ];
    }

    /**
     * The transparent margin an image marker's box carries, in image pixels.
     *
     * The same proportional room a 32-SVG-unit shape gets from its 4-unit
     * margin (h / 8), floored at 1 so a tiny image still has room for its drop
     * shadow. One rounding rule, reproduced exactly by the browser builder.
     *
     * @since  3.0.0
     * @param  int $h The image's intrinsic height in pixels.
     * @return int
     */
    public static function get_image_margin( $h ) {
        return max( 1, (int) round( (int) $h / 8 ) );
    }

    /**
     * The pixel size an image marker's artwork box should be rendered at.
     *
     * @since  3.0.0
     * @param  int $w    The image's intrinsic width in pixels.
     * @param  int $h    The image's intrinsic height in pixels.
     * @param  int $size The marker's on-screen height in px.
     * @return array [ width, height ] of the artwork box, in pixels.
     */
    public static function get_image_render_size( $w, $h, $size = self::DEFAULT_SIZE ) {
        $w = (int) $w;
        $h = (int) $h;

        if ( $w < 1 || $h < 1 ) {
            return [ 0, 0 ];
        }

        $m      = self::get_image_margin( $h );
        $height = (int) round( (int) $size * ( $h + 2 * $m ) / $h );

        return [ (int) round( $height * ( $w + 2 * $m ) / ( $h + 2 * $m ) ), $height ];
    }

    /**
     * The corner radius an image marker's frame is drawn with, in image pixels.
     *
     * `image_radius` is stored as a percentage rather than a pixel count
     * because an image marker's user space IS the uploaded bitmap: 8 image
     * pixels is a bold curve on a 64px logo and nothing at all on a 1024px
     * one. Measured against the shorter side, so 50 rounds a square into a
     * circle and a wide image into a stadium -- whichever side runs out first
     * is the one that decides.
     *
     * Clamped here rather than trusted, because this is public and a marker
     * saved before the frame existed carries no value at all.
     *
     * @since  3.0.0
     * @param  int $w      The image's intrinsic width in pixels.
     * @param  int $h      The image's intrinsic height in pixels.
     * @param  int $radius The stored percentage, 0-50.
     * @return int
     */
    public static function get_image_frame_radius( $w, $h, $radius ) {
        $radius = max( 0, min( 50, (int) $radius ) );

        return (int) round( $radius * min( (int) $w, (int) $h ) / 100 );
    }

    /**
     * The outline width an image marker's frame is drawn with, in image pixels.
     *
     * Scaled off the image height (like the radius scales off the shorter side)
     * against the same /64 reference the drop shadow uses - so one step is
     * literally 1px on a 64px image and grows from there.
     *
     * @since  3.0.0
     * @param  int $h     The image's intrinsic height in pixels.
     * @param  int $width The stored slider step, 0-8.
     * @return int
     */
    public static function get_image_frame_stroke( $h, $width ) {
        $width = max( 0, min( 8, (int) $width ) );

        return $width ? max( 1, (int) round( $width * (int) $h / 64 ) ) : 0;
    }

    /**
     * The pixel height a shape's artwork box should be capped at in a
     * fixed-height preview (picker toggle and tiles), so its silhouette reads
     * as the same size as the bundled markers there.
     *
     * @since  3.0.0
     * @param  string     $shape  The marker shape.
     * @param  int        $target The silhouette height every shape should match, in px.
     * @param  array|null $dims   [ width, height ] of an image marker's bitmap; only read for shape 'image'.
     * @return int Preview <img> max-height, in px.
     */
    public static function get_picker_height( $shape, $target = 34, $dims = null ) {
        if ( 'image' === $shape && is_array( $dims ) && isset( $dims[1] ) && (int) $dims[1] > 0 ) {
            $h = (int) $dims[1];
            $m = self::get_image_margin( $h );

            return (int) round( (int) $target * ( $h + 2 * $m ) / $h );
        }

        $geometry = isset( self::SHAPES[ $shape ] ) ? self::SHAPES[ $shape ] : self::SHAPES['classic_pin'];
        $bounds   = isset( self::SHAPE_BOUNDS[ $shape ] ) ? self::SHAPE_BOUNDS[ $shape ] : self::SHAPE_BOUNDS['classic_pin'];

        $silhouette_ratio = ( $bounds[3] - $bounds[1] ) / $geometry['viewbox'][1];

        return (int) round( $target / $silhouette_ratio );
    }

    /**
     * Get all custom markers, keyed by id.
     *
     * @since  3.0.0
     * @return array
     */
    public function get_markers() {
        $markers = get_option( self::OPTION_NAME, [] );

        return is_array( $markers ) ? $markers : [];
    }

    /**
     * Sanitize a raw marker definition.
     *
     * @since  3.0.0
     * @param  array $input Raw marker fields.
     * @return array|\WP_Error Sanitized marker, or WP_Error on invalid input.
     */
    public function sanitize_marker( $input ) {
        $name = isset( $input['name'] ) ? sanitize_text_field( wp_unslash( $input['name'] ) ) : '';

        if ( '' === $name ) {
            return new \WP_Error( 'wpsl_marker_empty_name', __( 'The marker name cannot be empty.', 'wp-store-locator' ) );
        }

        $shape = isset( $input['shape'] ) ? sanitize_key( $input['shape'] ) : 'classic_pin';

        if ( 'image' !== $shape && ! isset( self::SHAPES[ $shape ] ) ) {
            return new \WP_Error( 'wpsl_marker_invalid_shape', __( 'Unknown marker shape.', 'wp-store-locator' ) );
        }

        $fill   = isset( $input['fill_color'] ) ? sanitize_hex_color( wp_unslash( $input['fill_color'] ) ) : '';
        $stroke = isset( $input['stroke_color'] ) ? sanitize_hex_color( wp_unslash( $input['stroke_color'] ) ) : '';

        /*
         * A logo has to be an image attachment that actually exists: the id is
         * resolved to a file and embedded into the marker's artwork, so an id
         * pointing at a PDF -- or at nothing -- must never reach the builder.
         */
        $logo_id = isset( $input['logo_id'] ) ? absint( $input['logo_id'] ) : 0;

        if ( $logo_id && ! self::is_usable_logo( $logo_id ) ) {
            $logo_id = 0;
        }

        $icon_name = isset( $input['icon_name'] ) ? sanitize_key( $input['icon_name'] ) : '';

        if ( 'dot' === $icon_name && ! in_array( $shape, self::CENTER_SHAPES['dot'], true ) ) {
            $icon_name = '';
        }

        if ( $logo_id ) {
            $icon_name = '';
        }

        /*
         * The text icon is only an icon while it has text and a shape that
         * can show it; and the text only means something on the text icon.
         */
        $label_text = isset( $input['label_text'] ) ? Marker_Label::sanitize( wp_unslash( $input['label_text'] ), Marker_Label::STUDIO_MAX_WEIGHT ) : '';

        if ( 'text' === $icon_name && ( '' === $label_text || ! in_array( $shape, self::LABEL_SHAPES, true ) ) ) {
            $icon_name = '';
        }

        if ( 'text' !== $icon_name ) {
            $label_text = '';
        }

        /*
         * A filled centre only makes sense on a shape with a hole.
         */
        $center_fill = isset( $input['center_fill'] ) ? filter_var( $input['center_fill'], FILTER_VALIDATE_BOOLEAN ) : false;

        if ( ! isset( self::HOLE_SHAPES[ $shape ] ) ) {
            $center_fill = false;
        }

        $center_color = isset( $input['center_color'] ) ? sanitize_hex_color( wp_unslash( $input['center_color'] ) ) : '';

        $image_w = 0;
        $image_h = 0;

        if ( 'image' === $shape ) {

            if ( ! $logo_id ) {
                return new \WP_Error( 'wpsl_marker_missing_image', __( 'Pick an image for the marker first.', 'wp-store-locator' ) );
            }

            $meta    = wp_get_attachment_metadata( $logo_id );
            $image_w = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
            $image_h = isset( $meta['height'] ) ? (int) $meta['height'] : 0;

            if ( $image_w < 1 || $image_h < 1 ) {
                $file = get_attached_file( $logo_id );
                $dims = $file ? wp_getimagesize( $file ) : false;

                if ( $dims ) {
                    $image_w = (int) $dims[0];
                    $image_h = (int) $dims[1];
                }
            }

            if ( $image_w < 1 || $image_h < 1 ) {
                return new \WP_Error( 'wpsl_marker_missing_image', __( 'The size of the image could not be read.', 'wp-store-locator' ) );
            }
        }

        $marker = [
            'id'           => isset( $input['id'] ) ? sanitize_key( $input['id'] ) : '',
            'name'         => $name,
            'shape'        => $shape,
            'fill_color'   => $fill ? $fill : '#1e5b83',
            'stroke_color' => $stroke ? $stroke : '#093857',
            'center_fill'  => $center_fill,
            /*
             * The fill colour, not white: the centre disc sits directly
             * behind the glyph, and the icon colour defaults to white, so
             * a white centre made a fresh open pin's icon invisible.
             */
            'center_color' => $center_color ? $center_color : '#1e5b83',
            'stroke_width' => isset( $input['stroke_width'] ) ? max( 0, min( 8, (int) $input['stroke_width'] ) ) : 1,
            'icon_name'    => $icon_name,
            'label_text'   => $label_text,
            'icon_color'   => ( isset( $input['icon_color'] ) && sanitize_hex_color( wp_unslash( $input['icon_color'] ) ) ) ? sanitize_hex_color( wp_unslash( $input['icon_color'] ) ) : '#ffffff',
            'logo_id'      => $logo_id,
            'size'         => isset( $input['size'] ) ? max( 28, min( 88, (int) $input['size'] ) ) : self::DEFAULT_SIZE,
            'icon_size'    => isset( $input['icon_size'] ) ? max( 50, min( 200, (int) $input['icon_size'] ) ) : 100,
            'shadow'       => isset( $input['shadow'] ) ? filter_var( $input['shadow'], FILTER_VALIDATE_BOOLEAN ) : false,
        ];

        if ( 'image' === $shape ) {
            $marker['icon_name']    = '';
            $marker['center_fill']  = false;
            $marker['icon_size']    = 100;
            $marker['image_w']      = $image_w;
            $marker['image_h']      = $image_h;
            $marker['image_frame']  = isset( $input['image_frame'] ) ? filter_var( $input['image_frame'], FILTER_VALIDATE_BOOLEAN ) : false;
            $marker['image_radius'] = isset( $input['image_radius'] ) ? max( 0, min( 50, (int) $input['image_radius'] ) ) : 0;
            $marker['image_fill']   = isset( $input['image_fill'] ) ? filter_var( $input['image_fill'], FILTER_VALIDATE_BOOLEAN ) : true;
        }

        return $marker;
    }

    /**
     * Persist a ( pre-sanitized ) marker. Generates an id when empty.
     *
     * @since  3.0.0
     * @param  array $marker Sanitized marker.
     * @return array|\WP_Error The saved marker including its id, or WP_Error when sanitization fails.
     */
    public function save_marker( $marker ) {
        $sanitized = $this->sanitize_marker( $marker );

        if ( is_wp_error( $sanitized ) ) {
            return $sanitized;
        }

        if ( empty( $sanitized['id'] ) ) {
            $sanitized['id'] = 'marker_' . uniqid();
        }

        $markers  = $this->get_markers();
        $existing = isset( $markers[ $sanitized['id'] ] ) ? $markers[ $sanitized['id'] ] : null;

        $markers[ $sanitized['id'] ] = $sanitized;

        // Not autoloaded, like the shapes collection: only map pages and the
        // Studio ever read it, and every other request would otherwise carry
        // the whole marker payload in alloptions.
        update_option( self::OPTION_NAME, $markers, false );

        unset( $this->data_uri_cache[ $sanitized['id'] ] );

        /*
         * An edit swaps the artwork under an id that stores and categories
         * already point at, and the cached results hold the artwork rather than
         * the id -- so a recolour is invisible on the map for a day unless the
         * cache goes with it.
         */
        if ( $existing && $existing != $sanitized ) {
            wpsl_flush_store_cache();
        }

        return $sanitized;
    }

    /**
     * Delete a marker by id.
     *
     * @since  3.0.0
     * @param  string $id Marker id.
     * @return bool Whether the marker existed and was removed.
     */
    public function delete_marker( $id ) {
        $markers = $this->get_markers();

        if ( ! isset( $markers[ $id ] ) ) {
            return false;
        }

        unset( $markers[ $id ] );
        update_option( self::OPTION_NAME, $markers, false );

        unset( $this->data_uri_cache[ $id ] );

        $this->release_references( [ 'custom:' . $id ] );

        return true;
    }

    /**
     * Build the SVG wrapper for an image marker: the uploaded bitmap IS the
     * marker, wrapped in the root attributes every consumer already reads.
     *
     * @since  3.0.0
     * @param  array  $marker_config Marker definition ( shape 'image' ).
     * @param  string $logo_src      The already-resolved image src.
     * @return string SVG markup, or '' without a usable image.
     */
    public static function get_image_svg_marker( $marker_config, $logo_src = '' ) {
        $w = isset( $marker_config['image_w'] ) ? (int) $marker_config['image_w'] : 0;
        $h = isset( $marker_config['image_h'] ) ? (int) $marker_config['image_h'] : 0;

        if ( $w < 1 || $h < 1 || '' === (string) $logo_src ) {
            return '';
        }

        $size   = ( isset( $marker_config['size'] ) && is_numeric( $marker_config['size'] ) ) ? (int) $marker_config['size'] : self::DEFAULT_SIZE;
        $shadow = isset( $marker_config['shadow'] ) && (bool) $marker_config['shadow'];

        $m           = self::get_image_margin( $h );
        $render_size = self::get_image_render_size( $w, $h, $size );

        $defs    = '';
        $content = '<image href="' . esc_attr( $logo_src ) . '" x="0" y="0" width="' . esc_attr( $w ) . '" height="' . esc_attr( $h ) . '" preserveAspectRatio="xMidYMid meet" />';

        if ( isset( $marker_config['image_frame'] ) && (bool) $marker_config['image_frame'] ) {
            $radius = isset( $marker_config['image_radius'] ) ? $marker_config['image_radius'] : 0;
            $width  = isset( $marker_config['stroke_width'] ) && is_numeric( $marker_config['stroke_width'] ) ? (int) $marker_config['stroke_width'] : 1;

            $r  = self::get_image_frame_radius( $w, $h, $radius );
            $sw = self::get_image_frame_stroke( $h, $width );

            // The same defaults get_svg_marker() opens with, so a config that
            // names no colors paints the frame the way it would paint a shape.
            $fill   = ! empty( $marker_config['fill_color'] ) ? $marker_config['fill_color'] : '#ff3b30';
            $stroke = ! empty( $marker_config['stroke_color'] ) ? $marker_config['stroke_color'] : '#ffffff';

            $rect = '<rect x="0" y="0" width="' . esc_attr( $w ) . '" height="' . esc_attr( $h ) . '"'
                . ' rx="' . esc_attr( $r ) . '" ry="' . esc_attr( $r ) . '"';

            $defs = '<clipPath id="wpsl-marker-frame">' . $rect . ' /></clipPath>';

            $content = '<g clip-path="url(#wpsl-marker-frame)">' . $content . '</g>';

            // Absent means on, unlike image_frame: a framed marker saved
            // before the background could be dropped was drawn with one.
            if ( ! isset( $marker_config['image_fill'] ) || (bool) $marker_config['image_fill'] ) {
                $content = $rect . ' fill="' . esc_attr( $fill ) . '" stroke="none" />' . $content;
            }

            if ( $sw ) {
                $content .= $rect . ' fill="none" stroke="' . esc_attr( $stroke ) . '" stroke-width="' . esc_attr( $sw ) . '" />';
            }
        }

        if ( $shadow ) {
            $dy            = max( 1, (int) round( $h / 32 ) );
            $std_deviation = max( 1, (int) round( 3 * $h / 64 ) );
            $defs         .= '<filter id="wpsl-marker-shadow" x="-50%" y="-50%" width="200%" height="200%">'
                . '<feDropShadow dx="0" dy="' . esc_attr( $dy ) . '" stdDeviation="' . esc_attr( $std_deviation ) . '" flood-color="#000000" flood-opacity="0.35" />'
                . '</filter>';
            $content       = '<g filter="url(#wpsl-marker-shadow)">' . $content . '</g>';
        }

        // One <defs> around whatever the two blocks above put in it, so the
        // unframed-with-shadow case still spells itself the way it always did.
        if ( '' !== $defs ) {
            $defs = '<defs>' . $defs . '</defs>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg"'
            . ' viewBox="' . esc_attr( -$m ) . ' ' . esc_attr( -$m ) . ' ' . esc_attr( $w + 2 * $m ) . ' ' . esc_attr( $h + 2 * $m ) . '"'
            . ' width="' . esc_attr( $render_size[0] ) . '" height="' . esc_attr( $render_size[1] ) . '"'
            . ' data-wpsl-anchor="bottom" data-wpsl-anchor-y="' . esc_attr( $h ) . '">'
            . $defs . $content . '</svg>';
    }

    /**
     * Build the SVG markup for a marker config.
     *
     * @since  3.0.0
     * @param  array  $marker_config   Marker definition.
     * @param  array  $lucide_svgs_map Optional icon-name => inner-SVG map, consulted before Lucide_Icons.
     * @param  string $logo_src        Optional already-resolved logo src. Takes precedence over the icon.
     * @return string SVG markup, or '' for an unknown shape.
     */
    public static function get_svg_marker( $marker_config, $lucide_svgs_map = [], $logo_src = '' ) {
        $fill   = ! empty( $marker_config['fill_color'] ) ? $marker_config['fill_color'] : '#ff3b30';
        $stroke = ! empty( $marker_config['stroke_color'] ) ? $marker_config['stroke_color'] : '#ffffff';
        $width  = ( isset( $marker_config['stroke_width'] ) && is_numeric( $marker_config['stroke_width'] ) ) ? (int) $marker_config['stroke_width'] : 1;
        $shape  = ! empty( $marker_config['shape'] ) ? $marker_config['shape'] : 'classic_pin';
        $icon   = ! empty( $marker_config['icon_name'] ) ? $marker_config['icon_name'] : '';

        // Text on this shape draws this much larger than an icon would.
        $text_scale = isset( self::TEXT_SCALE[ $shape ] ) ? self::TEXT_SCALE[ $shape ] : 1;
        $color  = ! empty( $marker_config['icon_color'] ) ? $marker_config['icon_color'] : '#ffffff';
        $size   = ( isset( $marker_config['size'] ) && is_numeric( $marker_config['size'] ) ) ? (int) $marker_config['size'] : self::DEFAULT_SIZE;
        $shadow = isset( $marker_config['shadow'] ) && (bool) $marker_config['shadow'];

        // An image marker is its own wrapper: no silhouette, no icon, no
        // paint -- only the size/shadow fields above apply, read again there.
        if ( 'image' === $shape ) {
            return self::get_image_svg_marker( $marker_config, $logo_src );
        }

        if ( ! isset( self::SHAPES[ $shape ] ) ) {
            return '';
        }

        $has_logo = '' !== (string) $logo_src;

        $path_content = '';
        $fill_icon    = false;
        $text_icon    = false;

        if ( ! $has_logo && ! empty( $icon ) ) {
            if ( 'dot' === $icon ) {
                if ( in_array( $shape, self::CENTER_SHAPES['dot'], true ) && isset( self::CENTER_DOT_RADIUS[ $shape ] ) ) {
                    $path_content = '<circle cx="12" cy="12" r="' . esc_attr( self::CENTER_DOT_RADIUS[ $shape ] ) . '" fill="' . esc_attr( $color ) . '" stroke="none" />';
                }
            } elseif ( 'text' === $icon ) {
                // Sanitized here as well as on save, so the Studio preview of
                // what is being typed matches what will be saved.
                if ( in_array( $shape, self::LABEL_SHAPES, true ) && isset( $marker_config['label_text'] ) ) {
                    $path_content = Marker_Label::markup( $marker_config['label_text'], $color, $text_scale );
                    $text_icon    = '' !== $path_content;
                }
            } elseif ( Phosphor_Icons::is_phosphor_name( $icon ) ) {
                $path_content = isset( $lucide_svgs_map[ $icon ] ) ? $lucide_svgs_map[ $icon ] : Phosphor_Icons::get( $icon );
                $fill_icon    = '' !== $path_content;
            } else {
                $path_content = isset( $lucide_svgs_map[ $icon ] ) ? $lucide_svgs_map[ $icon ] : Lucide_Icons::get( $icon );
            }
        }

        $scale = self::ICON_SCALE[ $shape ];

        /*
         * icon_size is a percentage multiplier on the per-shape ICON_SCALE:
         * 100 is the default, 50 shrinks the glyph to half, 150 grows it to
         * 1.5x. The translation is re-derived from the effective scale so the
         * icon stays centred at any size.
         */
        $icon_size = isset( $marker_config['icon_size'] ) ? (int) $marker_config['icon_size'] : 100;
        $multiplier = $icon_size / 100;
        $effective_scale = $scale * $multiplier;

        $tx = 16 - ( 12 * $effective_scale );
        $ty = 16 - ( 12 * $effective_scale );

        $transform = ' transform="translate(' . esc_attr( $tx ) . ', ' . esc_attr( $ty ) . ') scale(' . esc_attr( $effective_scale ) . ')"';

        /*
         * The icon group names what it holds ( logo / text / dot / icon /
         * none ) and the color it was painted with, so the frontend can swap
         * its content for a runtime label without rebuilding the marker.
         */
        if ( $has_logo ) {
            $icon_kind = 'logo';
        } elseif ( '' === $path_content ) {
            $icon_kind = 'none';
        } elseif ( $text_icon ) {
            $icon_kind = 'text';
        } elseif ( 'dot' === $icon ) {
            $icon_kind = 'dot';
        } else {
            $icon_kind = 'icon';
        }

        $group_open = '<g data-wpsl-icon="' . esc_attr( $icon_kind ) . '" data-wpsl-icon-color="' . esc_attr( $color ) . '"'
            . ' data-wpsl-text-scale="' . esc_attr( $text_scale ) . '"';

        if ( $has_logo ) {
            $icon_group = $group_open . $transform . '>'
                . '<image href="' . esc_attr( $logo_src ) . '" x="0" y="0" width="24" height="24" preserveAspectRatio="xMidYMid meet" />'
                . '</g>';
        } elseif ( $text_icon ) {
            // The <text> paints itself, so the group carries no paint.
            $icon_group = $group_open . $transform . '>' . $path_content . '</g>';
        } elseif ( $fill_icon ) {
            $icon_group = $group_open . ' fill="' . esc_attr( $color ) . '" stroke="none"' . $transform . '>' . $path_content . '</g>';
        } else {
            $icon_group = $group_open . ' fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $transform . '>' . $path_content . '</g>';
        }

        $paint = ' fill="' . esc_attr( $fill ) . '" stroke="' . esc_attr( $stroke ) . '" stroke-width="' . esc_attr( $width ) . '" stroke-linejoin="round" />';


        $body = self::SHAPE_BODIES[ $shape ] . $paint;

        $center = '';

        if ( ! empty( $marker_config['center_fill'] ) && isset( self::HOLE_SHAPES[ $shape ] ) ) {
            $hole         = self::HOLE_SHAPES[ $shape ];
            $center_color = ! empty( $marker_config['center_color'] ) ? $marker_config['center_color'] : '#ffffff';

            $center = '<circle cx="' . esc_attr( $hole['cx'] ) . '" cy="' . esc_attr( $hole['cy'] ) . '" r="' . esc_attr( $hole['r'] ) . '"'
                . ' fill="' . esc_attr( $center_color ) . '" stroke="none" />';
        }

        $geometry    = self::SHAPES[ $shape ];
        $render_size = self::get_render_size( $shape, $size );

        $defs    = '';
        $content = $center . $body . $icon_group;

        if ( $shadow ) {
            $defs     = '<defs><filter id="wpsl-marker-shadow" x="-50%" y="-50%" width="200%" height="200%">'
                . '<feDropShadow dx="0" dy="1" stdDeviation="1.5" flood-color="#000000" flood-opacity="0.35" />'
                . '</filter></defs>';
            $content = '<g filter="url(#wpsl-marker-shadow)">' . $content . '</g>';
        }

        /*
         * anchor_x is emitted only for shapes that declare one (the flag's
         * pole), in SVG units the JS converts against the viewBox.
         */
        $anchor_x = isset( $geometry['anchor_x'] ) ? ' data-wpsl-anchor-x="' . esc_attr( $geometry['anchor_x'] ) . '"' : '';

        /*
         * Publish the silhouette's tip (SHAPE_BOUNDS bottom + half the outline)
         * instead of the box's bottom edge, so the margin below the tip doesn't
         * hang the marker above its location; only for bottom-anchored shapes,
         * since a centred shape's box and silhouette centres already coincide.
         */
        $anchor_y = '';

        if ( 'bottom' === $geometry['anchor'] && isset( self::SHAPE_BOUNDS[ $shape ] ) ) {
            $box_bottom = $geometry['origin'][1] + $geometry['viewbox'][1];
            $tip        = self::SHAPE_BOUNDS[ $shape ][3] + ( $width / 2 );

            $anchor_y = ' data-wpsl-anchor-y="' . esc_attr( min( $tip, $box_bottom ) ) . '"';
        }

        /*
         * Whether the frontend may write a runtime label into the icon group:
         * only on a shape with something behind the text. The frontend reads
         * this rather than knowing the shape list itself.
         */
        // A logo is the marker's identity, so a runtime label leaves it
        // alone; the list badge still numbers the result.
        $labelable = ( ! $has_logo && in_array( $shape, self::LABEL_SHAPES, true ) ) ? ' data-wpsl-labelable="1"' : '';

        return '<svg xmlns="http://www.w3.org/2000/svg"'
            . ' viewBox="' . esc_attr( $geometry['origin'][0] ) . ' ' . esc_attr( $geometry['origin'][1] ) . ' ' . esc_attr( $geometry['viewbox'][0] ) . ' ' . esc_attr( $geometry['viewbox'][1] ) . '"'
            . ' width="' . esc_attr( $render_size[0] ) . '" height="' . esc_attr( $render_size[1] ) . '"'
            . ' data-wpsl-anchor="' . esc_attr( $geometry['anchor'] ) . '"' . $anchor_x . $anchor_y . $labelable . '>'
            . $defs . $content . '</svg>';
    }

    /**
     * The marker that stands in for a bundled pin while runtime labels are
     * on: a bundled image cannot carry a label, so the Studio's default pin
     * takes its place, in a darker paint for the active state.
     *
     * @since  3.0.0
     * @param  string $slot 'store' or 'active'.
     * @return string Data URI.
     */
    public static function get_label_fallback_uri( $slot = 'store' ) {
        $active = ( 'active' === $slot );

        $config = [
            'shape'        => 'classic_pin',
            'fill_color'   => $active ? '#093857' : '#1e5b83',
            'stroke_color' => $active ? '#1e5b83' : '#093857',
            'stroke_width' => 1,
            'icon_name'    => '',
            'icon_color'   => '#ffffff',
            'size'         => self::DEFAULT_SIZE,
            'icon_size'    => 100,
            'shadow'       => true,
        ];

        /**
         * The marker config a bundled pin is replaced with while runtime
         * labels are on.
         *
         * @since 3.0.0
         * @param array  $config The marker fields, in Custom_Markers::sanitize_marker() shape.
         * @param string $slot   'store' or 'active'.
         */
        $config = apply_filters( 'wpsl_marker_label_fallback', $config, $active ? 'active' : 'store' );

        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode( self::get_svg_marker( $config ) );
    }

    /**
     * Whether an attachment may be used as a marker logo.
     *
     * @since  3.0.0
     * @param  int $logo_id Attachment id.
     * @return boolean
     */
    public static function is_usable_logo( $logo_id ) {
        $logo_id = (int) $logo_id;

        if ( ! $logo_id || ! wp_attachment_is_image( $logo_id ) ) {
            return false;
        }

        $mime = get_post_mime_type( $logo_id );

        if ( is_string( $mime ) && in_array( strtolower( $mime ), self::LOGO_BLOCKED['mimes'], true ) ) {
            return false;
        }

        $file = get_attached_file( $logo_id );

        if ( ! $file || ! file_exists( $file ) ) {
            return false;
        }

        $extension = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );

        if ( in_array( $extension, self::LOGO_BLOCKED['extensions'], true ) ) {
            return false;
        }

        $bytes = filesize( $file );

        /**
         * The largest a marker logo may be, in bytes.
         *
         * Raise it for a site that genuinely needs a heavier logo, remembering
         * that the file is embedded in every copy of the marker and a map draws
         * one copy per store.
         *
         * @since 3.0.0
         * @param int $max     The cap, in bytes.
         * @param int $logo_id The attachment being checked.
         */
        $max = (int) apply_filters( 'wpsl_marker_logo_max_bytes', self::LOGO_MAX_BYTES, $logo_id );

        if ( false !== $bytes && $max > 0 && $bytes > $max ) {
            return false;
        }

        return true;
    }

    /**
     * Resolve a logo attachment to an embeddable base64 data URI.
     *
     * @since  3.0.0
     * @param  int $logo_id Attachment id.
     * @return string Data URI, or '' when the attachment cannot be used as a logo.
     */
    public static function get_logo_data_uri( $logo_id ) {
        static $cache = [];

        $logo_id = (int) $logo_id;

        if ( ! $logo_id ) {
            return '';
        }

        if ( isset( $cache[ $logo_id ] ) ) {
            return $cache[ $logo_id ];
        }

        $cache[ $logo_id ] = '';

        /*
         * Re-apply sanitize_marker()'s logo gate on read, so a marker saved
         * before the rule existed degrades to its plain shape if its logo is
         * now refused.
         */
        if ( ! self::is_usable_logo( $logo_id ) ) {
            return '';
        }

        $mime = get_post_mime_type( $logo_id );
        $file = get_attached_file( $logo_id );

        // A marker whose logo has been deleted from the Media Library degrades
        // to its plain shape rather than erroring -- see get_svg_marker().
        if ( ! $file || ! file_exists( $file ) ) {
            return '';
        }

        $contents = file_get_contents( $file );

        if ( false === $contents ) {
            return '';
        }

        $cache[ $logo_id ] = 'data:' . $mime . ';base64,' . base64_encode( $contents );

        return $cache[ $logo_id ];
    }

    /**
     * Data URI for a stored marker id, for use as a map marker image.
     *
     * @since  3.0.0
     * @param  string $id Marker id.
     * @return string Data URI, or '' when the id is unknown.
     */
    public function get_data_uri( $id ) {
        /*
         * Cached: the marker picker rebuilds every marker's SVG three times
         * (start/store/active), and multiple [wpsl_map] shortcodes resolve the
         * same marker once per map.
         */
        if ( isset( $this->data_uri_cache[ $id ] ) ) {
            return $this->data_uri_cache[ $id ];
        }

        $markers = $this->get_markers();

        if ( ! isset( $markers[ $id ] ) ) {
            return '';
        }

        $logo_src = isset( $markers[ $id ]['logo_id'] ) ? self::get_logo_data_uri( $markers[ $id ]['logo_id'] ) : '';

        $svg = self::get_svg_marker( $markers[ $id ], [], $logo_src );

        if ( '' === $svg ) {
            return '';
        }

        $this->data_uri_cache[ $id ] = 'data:image/svg+xml;charset=utf-8,' . rawurlencode( $svg );

        return $this->data_uri_cache[ $id ];
    }

    /**
     * Every logo available for reuse in the Marker Studio's Custom category.
     *
     * @since  3.0.0
     * @return array [ attachment_id => [ 'url' => string, 'thumb' => string, 'filename' => string, 'width' => int, 'height' => int ] ]
     */
    public function get_reusable_logos() {
        if ( null !== $this->reusable_logos ) {
            return $this->reusable_logos;
        }

        $ids = get_posts( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_key'       => self::LOGO_META_KEY,
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        ] );

        foreach ( $this->get_markers() as $marker ) {
            $logo_id = isset( $marker['logo_id'] ) ? absint( $marker['logo_id'] ) : 0;

            if ( $logo_id && ! in_array( $logo_id, $ids, true ) ) {
                $ids[] = $logo_id;
            }
        }

        $logos = [];

        foreach ( $ids as $id ) {
            $id  = absint( $id );
            $url = wp_get_attachment_url( $id );

            if ( ! $id || ! $url || isset( $logos[ $id ] ) ) {
                continue;
            }

            $thumb = wp_get_attachment_image_url( $id, 'thumbnail' );

            // Intrinsic size, so the image mode's preview can size itself
            // without a round trip; absent metadata (e.g. a non-image
            // attachment) degrades to 0 rather than a notice.
            $meta   = wp_get_attachment_metadata( $id );
            $width  = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
            $height = isset( $meta['height'] ) ? (int) $meta['height'] : 0;

            if ( $width < 1 || $height < 1 ) {
                $file = get_attached_file( $id );
                $dims = $file ? wp_getimagesize( $file ) : false;

                if ( $dims ) {
                    $width  = (int) $dims[0];
                    $height = (int) $dims[1];
                }
            }

            $logos[ $id ] = [
                'url'      => $url,
                'thumb'    => $thumb ? $thumb : $url,
                'filename' => basename( get_attached_file( $id ) ),
                'width'    => $width,
                'height'   => $height,
            ];
        }

        $this->reusable_logos = $logos;

        return $this->reusable_logos;
    }

    /**
     * Whether an attachment is still referenced as logo_id by any saved marker.
     *
     * Used to block untag_logo() from removing a logo's postmeta tag while a
     * marker still needs it - get_reusable_logos() mines saved markers for
     * logo_id, so a tag-only removal would be undone by the next page load
     * (the marker-referenced union brings the logo right back).
     *
     * @since  3.0.0
     * @param  int $logo_id Attachment id.
     * @return bool
     */
    public function is_logo_in_use( $logo_id ) {
        return [] !== $this->get_markers_using_logo( $logo_id );
    }

    /**
     * The saved markers referencing an attachment as their logo.
     *
     * @since  3.0.0
     * @param  int $logo_id Attachment id.
     * @return array[] { id, name } records, in the order they are stored.
     */
    public function get_markers_using_logo( $logo_id ) {
        $logo_id = absint( $logo_id );
        $using   = [];

        if ( ! $logo_id ) {
            return $using;
        }

        foreach ( $this->get_markers() as $marker ) {
            if ( absint( $marker['logo_id'] ?? 0 ) !== $logo_id ) {
                continue;
            }

            $name = isset( $marker['name'] ) ? trim( (string) $marker['name'] ) : '';

            $using[] = [
                'id' => isset( $marker['id'] ) ? (string) $marker['id'] : '',

                // sanitize_marker() will not save an empty name, but data saved
                // by an older version can carry one.
                'name' => '' !== $name ? $name : __( 'Untitled marker', 'wp-store-locator' ),
            ];
        }

        return $using;
    }

    /**
     * How often each custom marker is referenced from outside the library.
     *
     * @since  3.0.0
     * @return array[] [ 'categories' => int, 'locations' => int ], keyed by the stored "custom:{id}" value.
     */
    public function get_reference_counts() {
        global $wpdb;

        $counts = [];

        foreach ( self::REFERENCE_META as $type => $source ) {

            // "term" / "post" name the $wpdb table property as well.
            $table  = $wpdb->{ $source['meta_type'] . 'meta' };
            $column = $source['column'];

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table and column are a $wpdb property and a fixed literal from the map above; the values are prepared below.
            $select = "SELECT meta_value AS value, COUNT( DISTINCT {$column} ) AS total FROM {$table} WHERE meta_key IN ( %s, %s ) AND meta_value LIKE %s GROUP BY meta_value";

            $sql = $wpdb->prepare(
                $select, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built directly above out of fixed parts.
                $source['keys'][0],
                $source['keys'][1],
                $wpdb->esc_like( 'custom:' ) . '%'
            );

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above; no core API answers "which meta values are in use", and both callers are one-off admin screens.
            $rows = $wpdb->get_results( $sql, ARRAY_A );

            foreach ( (array) $rows as $row ) {
                $value = isset( $row['value'] ) ? (string) $row['value'] : '';

                if ( '' === $value ) {
                    continue;
                }

                if ( ! isset( $counts[ $value ] ) ) {
                    $counts[ $value ] = [
                        'categories' => 0,
                        'locations'  => 0,
                    ];
                }

                $counts[ $value ][ $type ] += isset( $row['total'] ) ? (int) $row['total'] : 0;
            }
        }

        return $counts;
    }

    /**
     * Let go of everything pointing at the given markers.
     *
     * @since  3.0.0
     * @param  string[] $values The stored "custom:{id}" values being released.
     * @return void
     */
    public function release_references( $values ) {
        $values = array_values( array_unique( array_filter( (array) $values, 'is_string' ) ) );

        if ( ! $values ) {
            return;
        }

        $this->release_marker_settings( $values );

        foreach ( $values as $value ) {
            foreach ( self::REFERENCE_META as $source ) {
                foreach ( $source['keys'] as $meta_key ) {
                    delete_metadata( $source['meta_type'], 0, $meta_key, $value, true );
                }
            }
        }

        // The category list table caches whether any category has a marker.
        delete_transient( 'wpsl_has_category_images' );

        // Clearing the meta the artwork was reached through is not enough on
        // its own -- the cache holds the artwork. See wpsl_flush_store_cache().
        wpsl_flush_store_cache();
    }

    /**
     * Return the marker settings slots naming one of these markers to their defaults.
     *
     * @since  3.0.0
     * @param  string[] $values The stored "custom:{id}" values being released.
     * @return void
     */
    private function release_marker_settings( $values ) {
        $markers  = get_option( 'wpsl_markers' );
        $settings = wpsl_get_service( 'wpsl_settings' );

        if ( ! is_array( $markers ) || ! $settings ) {
            return;
        }

        $changed = false;

        foreach ( self::SETTING_SLOTS as $key ) {
            if ( ! isset( $markers[ $key ] ) || ! in_array( $markers[ $key ], $values, true ) ) {
                continue;
            }

            $markers[ $key ] = $settings->get_default( 'markers', $key );
            $changed         = true;
        }

        if ( ! $changed ) {
            return;
        }

        update_option( 'wpsl_markers', $markers );

        $settings->clear_cache();
    }
}