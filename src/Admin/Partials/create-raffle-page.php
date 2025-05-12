<?php
// Save as: wp-content/plugins/weebunz-core/admin/partials/create-raffle-page.php

// Security check
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('weebunz_messages'); ?>

    <div class="card">
        <form method="post" action="" class="weebunz-form">
            <?php wp_nonce_field('create_raffle', 'weebunz_raffle_nonce'); ?>

            <div class="form-field">
                <label for="title"><strong>Raffle Title</strong></label>
                <input type="text" 
                       id="title" 
                       name="title" 
                       class="regular-text" 
                       required>
                <p class="description">Enter a descriptive title for the raffle</p>
            </div>

            <div class="form-field">
                <label for="prize_description"><strong>Prize Description</strong></label>
                <textarea id="prize_description" 
                         name="prize_description" 
                         rows="5" 
                         class="large-text" 
                         required></textarea>
                <p class="description">Detailed description of the prize</p>
            </div>

            <div class="form-field">
                <label for="event_date"><strong>Event Date & Time</strong></label>
                <input type="datetime-local" 
                       id="event_date" 
                       name="event_date" 
                       required>
                <p class="description">When will the raffle draw take place?</p>
            </div>

            <div class="form-field">
                <label>
                    <input type="checkbox" 
                           id="is_live_event" 
                           name="is_live_event">
                    <strong>This is a live event</strong>
                </label>
                <p class="description">Check if this raffle will be drawn at a live event</p>
            </div>

            <div class="form-field">
                <label for="entry_limit"><strong>Entry Limit</strong></label>
                <input type="number" 
                       id="entry_limit" 
                       name="entry_limit" 
                       value="200" 
                       min="1" 
                       max="1000">
                <p class="description">Maximum number of entries allowed (default: 200)</p>
            </div>

            <p class="submit">
                <input type="submit" 
                       name="create_raffle" 
                       class="button button-primary" 
                       value="Create Raffle">
            </p>
        </form>
    </div>
</div>

<style>
.weebunz-form .form-field {
    margin-bottom: 20px;
}

.weebunz-form label {
    display: block;
    margin-bottom: 5px;
}

.weebunz-form input[type="text"],
.weebunz-form input[type="number"],
.weebunz-form input[type="datetime-local"],
.weebunz-form textarea {
    width: 100%;
    max-width: 500px;
}

.weebunz-form .description {
    color: #666;
    font-style: italic;
    margin-top: 5px;
}
</style>