<?php
/**
 * Admin Tools Page for WeeBunz Quiz Engine
 *
 * This file provides the UI for the Tools page, including sample data loading
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/admin/partials
 */

 // If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Import the PSR-4 autoloaded class
use Weebunz\Database\SampleDataLoader;

// Instantiate the loader
$sample_data_loader = new SampleDataLoader();

// Handle form submission for loading sample data
if ( isset( $_POST['weebunz_load_sample_data'] ) ) {
    check_admin_referer( 'weebunz_tools_nonce' );

    $result = $sample_data_loader->load_sample_data();

    if ( $result ) {
        echo '<div class="notice notice-success"><p>' 
             . esc_html__( 'Sample data loaded successfully!', 'weebunz-quiz-engine' ) 
             . '</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' 
             . esc_html__( 'Failed to load sample data. Check error logs.', 'weebunz-quiz-engine' ) 
             . '</p></div>';
    }
}

// Handle form submission for cleaning up sample data
if ( isset( $_POST['weebunz_cleanup_sample_data'] ) ) {
    check_admin_referer( 'weebunz_tools_nonce' );

    $result = $sample_data_loader->cleanup_sample_data();

    if ( $result ) {
        echo '<div class="notice notice-success"><p>' 
             . esc_html__( 'Sample data cleaned up successfully!', 'weebunz-quiz-engine' ) 
             . '</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' 
             . esc_html__( 'Failed to clean up sample data. Check error logs.', 'weebunz-quiz-engine' ) 
             . '</p></div>';
    }
}

// Check current status
$is_sample_data_loaded = $sample_data_loader->is_sample_data_loaded();
?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <div class="card">
        <h2><?php esc_html_e( 'Sample Data Management', 'weebunz-quiz-engine' ); ?></h2>
        <p><?php esc_html_e( 'Use these tools to load or remove sample quiz data for testing and demonstration purposes.', 'weebunz-quiz-engine' ); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field( 'weebunz_tools_nonce' ); ?>

            <?php if ( $is_sample_data_loaded ) : ?>
                <p><?php esc_html_e( 'Sample data is currently loaded.', 'weebunz-quiz-engine' ); ?></p>
                <p class="submit">
                    <input
                        type="submit"
                        name="weebunz_cleanup_sample_data"
                        id="weebunz_cleanup_sample_data"
                        class="button button-secondary"
                        value="<?php esc_attr_e( 'Clean Up Sample Data', 'weebunz-quiz-engine' ); ?>">
                </p>
            <?php else : ?>
                <p><?php esc_html_e( 'Sample data is not currently loaded.', 'weebunz-quiz-engine' ); ?></p>
                <p class="submit">
                    <input
                        type="submit"
                        name="weebunz_load_sample_data"
                        id="weebunz_load_sample_data"
                        class="button button-primary"
                        value="<?php esc_attr_e( 'Load Sample Data', 'weebunz-quiz-engine' ); ?>">
                </p>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h2><?php esc_html_e( 'Other Tools', 'weebunz-quiz-engine' ); ?></h2>
        <p><?php esc_html_e( 'Additional tools and utilities for the WeeBunz Quiz Engine.', 'weebunz-quiz-engine' ); ?></p>
        <ul>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=weebunz-quiz-engine-load-testing' ) ); ?>">
                    <?php esc_html_e( 'Load Testing Tool', 'weebunz-quiz-engine' ); ?>
                </a>
            </li>
            <!-- Add links to other tools here -->
        </ul>
    </div>
</div>
