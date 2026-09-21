<?php
/**
 * WPSL Progress Bar Utility
 * 
 * Reusable progress bar component for long-running operations
 * 
 * @author Tijmen Smit
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPSL_Progress_Bar {
    
    /**
     * Progress bar arguments
     * @var array
     */
    private $args = [];
    
    /**
     * Constructor
     *
     * @since 3.0.0
     * @param array $args {
     *     Optional. Progress bar arguments.
     *
     *     @type int    $current           Current count (default: 0)
     *     @type int    $total             Total count (default: 100)
     *     @type int    $percentage        Direct percentage (overrides current/total)
     *     @type bool   $show_percentage   Show percentage text (default: true)
     *     @type bool   $show_counts       Show current/total counts (default: true)
     *     @type string $size              Size: 'small', 'medium', 'large' (default: 'medium')
     *     @type string $label             Custom label text
     * }
     */
    public function __construct( $args = [] ) {
        $this->args = wp_parse_args( $args, [
            'current'         => 0,
            'total'           => 100,
            'percentage'      => false,
            'show_percentage' => true,
            'show_counts'     => true,
            'size'            => 'medium',
            'label'           => ''
        ] );
    }
    
    /**
     * Get calculated percentage
     * 
     * @return int Percentage (0-100)
     */
    private function get_percentage() {
        if ( false !== $this->args['percentage'] ) {
            return min( absint( $this->args['percentage'] ), 100 );
        }
        
        if ( $this->args['total'] <= 0 ) {
            return 0;
        }
        
        $percentage = ( $this->args['current'] / $this->args['total'] ) * 100;
        return min( round( $percentage ), 100 );
    }
    
    /**
     * Get progress bar size class
     * 
     * @return string CSS class
     */
    private function get_size_class() {
        $valid_sizes = [ 'small', 'medium', 'large' ];
        
        if ( in_array( $this->args['size'], $valid_sizes, true ) ) {
            return 'wpsl-progress-' . $this->args['size'];
        }
        
        return 'wpsl-progress-medium';
    }
    
    /**
     * Get progress label text
     * 
     * @return string Label HTML
     */
    private function get_label() {
        $parts = [];
        
        if ( ! empty( $this->args['label'] ) ) {
            $parts[] = esc_html( $this->args['label'] );
        }
        
        if ( $this->args['show_counts'] ) {
            $parts[] = sprintf(
                '%d / %d',
                absint( $this->args['current'] ),
                absint( $this->args['total'] )
            );
        }
        
        if ( $this->args['show_percentage'] ) {
            $parts[] = sprintf( '(%d%%)', $this->get_percentage() );
        }
        
        if ( empty( $parts ) ) {
            return '';
        }
        
        return '<div class="wpsl-progress-label">' . implode( ' ', $parts ) . '</div>';
    }
    
    /**
     * Render progress bar HTML
     * 
     * @return string HTML markup
     */
    public function render() {
        $percentage = $this->get_percentage();
        
        return sprintf(
            '<div class="wpsl-progress-bar %s">
                <div class="wpsl-progress-fill" style="width: %d%%"></div>
                %s
            </div>',
            esc_attr( $this->get_size_class() ),
            $percentage,
            $this->get_label()
        );
    }
    
    /**
     * Echo progress bar HTML
     * 
     * @return void
     */
    public function display() {
        echo $this->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output
    }
}