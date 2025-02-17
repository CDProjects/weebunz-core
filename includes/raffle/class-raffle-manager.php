<?php
namespace Weebunz\Raffle;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Logger;

class Raffle_Manager {
    private $wpdb;
    private $user_id;
    private $phone_answer_timeout;
    private $winner_question_timeout;

    public function __construct($user_id = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->user_id = $user_id;
        $this->phone_answer_timeout = get_option('weebunz_phone_answer_timeout', 30);
        $this->winner_question_timeout = get_option('weebunz_winner_question_timeout', 30);

        Logger::debug('Raffle_Manager initialized', [
            'user_id' => $this->user_id,
            'phone_timeout' => $this->phone_answer_timeout,
            'question_timeout' => $this->winner_question_timeout
        ]);
    }

    /**
     * Create a new raffle event
     */
    public function create_raffle_event($data) {
        try {
            Logger::info('Creating new raffle event', [
                'title' => $data['title'],
                'is_live' => !empty($data['is_live_event'])
            ]);

            $table = $this->wpdb->prefix . 'raffle_events';
            
            $raffle_data = [
                'title' => sanitize_text_field($data['title']),
                'prize_description' => wp_kses_post($data['prize_description']),
                'is_live_event' => !empty($data['is_live_event']),
                'event_date' => $data['event_date'],
                'status' => 'scheduled',
                'entry_limit' => isset($data['entry_limit']) ? intval($data['entry_limit']) : 200
            ];

            $result = $this->wpdb->insert($table, $raffle_data);
            
            if ($result === false) {
                throw new \Exception('Failed to create raffle event');
            }

            $raffle_id = $this->wpdb->insert_id;
            
            Logger::info('Raffle event created successfully', [
                'raffle_id' => $raffle_id,
                'entry_limit' => $raffle_data['entry_limit']
            ]);

            return $raffle_id;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'create_raffle_event',
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Add entries to a raffle
     */
    public function add_entries($raffle_id, $entries_data) {
        try {
            Logger::info('Adding entries to raffle', [
                'raffle_id' => $raffle_id,
                'entry_count' => count($entries_data)
            ]);

            $entries_table = $this->wpdb->prefix . 'raffle_entries';
            $raffle_table = $this->wpdb->prefix . 'raffle_events';

            // Get raffle details
            $raffle = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM $raffle_table WHERE id = %d",
                    $raffle_id
                )
            );

            if (!$raffle || $raffle->status !== 'scheduled') {
                Logger::warning('Invalid raffle status for adding entries', [
                    'raffle_id' => $raffle_id,
                    'status' => $raffle ? $raffle->status : 'not found'
                ]);
                return false;
            }

            // Get current entry count
            $current_entries = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $entries_table WHERE raffle_id = %d",
                    $raffle_id
                )
            );

            // Check entry limit
            $remaining_slots = $raffle->entry_limit - $current_entries;
            if ($remaining_slots <= 0) {
                Logger::warning('Raffle entry limit reached', [
                    'raffle_id' => $raffle_id,
                    'limit' => $raffle->entry_limit
                ]);
                return false;
            }

            // Insert entries
            $success = true;
            $entries_added = 0;
            foreach ($entries_data as $entry) {
                if ($remaining_slots <= 0) break;

                $entry_data = [
                    'raffle_id' => $raffle_id,
                    'user_id' => $this->user_id,
                    'entry_number' => $this->generate_entry_number($raffle_id),
                    'phone_number' => sanitize_text_field($entry['phone_number']),
                    'source_type' => $entry['source_type'],
                    'source_id' => $entry['source_id']
                ];

                $result = $this->wpdb->insert($entries_table, $entry_data);
                if (!$result) {
                    Logger::error('Failed to insert raffle entry', [
                        'raffle_id' => $raffle_id,
                        'entry_data' => $entry_data
                    ]);
                    $success = false;
                    break;
                }

                $entries_added++;
                $remaining_slots--;
            }

            Logger::info('Raffle entries added', [
                'raffle_id' => $raffle_id,
                'entries_added' => $entries_added,
                'success' => $success
            ]);

            return $success;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'add_entries',
                'raffle_id' => $raffle_id
            ]);
            return false;
        }
    }

    /**
     * Start raffle draw process
     */
    public function start_draw($raffle_id) {
        try {
            Logger::info('Starting raffle draw', ['raffle_id' => $raffle_id]);

            $raffle_table = $this->wpdb->prefix . 'raffle_events';
            $entries_table = $this->wpdb->prefix . 'raffle_entries';

            // Update raffle status
            $status_update = $this->wpdb->update(
                $raffle_table,
                ['status' => 'active'],
                ['id' => $raffle_id]
            );

            if ($status_update === false) {
                throw new \Exception('Failed to update raffle status');
            }

            // Get all entries
            $entries = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM $entries_table WHERE raffle_id = %d",
                    $raffle_id
                )
            );

            $result = [
                'total_entries' => count($entries),
                'draw_ready' => true
            ];

            Logger::info('Raffle draw started', [
                'raffle_id' => $raffle_id,
                'total_entries' => count($entries)
            ]);

            return $result;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'start_draw',
                'raffle_id' => $raffle_id
            ]);
            throw $e;
        }
    }

    /**
     * Draw a random entry
     */
    public function draw_winner($raffle_id) {
        try {
            Logger::info('Drawing raffle winner', ['raffle_id' => $raffle_id]);

            $entries_table = $this->wpdb->prefix . 'raffle_entries';
            $draws_table = $this->wpdb->prefix . 'raffle_draws';

            // Get random entry
            $entry = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM $entries_table 
                    WHERE raffle_id = %d 
                    ORDER BY RAND() 
                    LIMIT 1",
                    $raffle_id
                )
            );

            if (!$entry) {
                Logger::error('No entries found for raffle', ['raffle_id' => $raffle_id]);
                return false;
            }

            // Get unused winner question
            $question = $this->get_unused_winner_question();
            if (!$question) {
                Logger::error('No available winner questions', ['raffle_id' => $raffle_id]);
                return false;
            }

            // Record draw attempt
            $draw_data = [
                'raffle_id' => $raffle_id,
                'entry_id' => $entry->id,
                'winner_question_id' => $question->id
            ];

            $result = $this->wpdb->insert($draws_table, $draw_data);
            
            if ($result === false) {
                throw new \Exception('Failed to record draw attempt');
            }

            $draw_id = $this->wpdb->insert_id;

            Logger::info('Winner drawn successfully', [
                'raffle_id' => $raffle_id,
                'draw_id' => $draw_id,
                'entry_id' => $entry->id
            ]);

            return [
                'draw_id' => $draw_id,
                'entry' => $entry,
                'question' => $question
            ];

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'draw_winner',
                'raffle_id' => $raffle_id
            ]);
            return false;
        }
    }

    /**
     * Record phone answer attempt
     */
    public function record_phone_answer($draw_id, $answered) {
        try {
            Logger::info('Recording phone answer attempt', [
                'draw_id' => $draw_id,
                'answered' => $answered
            ]);

            $table = $this->wpdb->prefix . 'raffle_draws';
            
            $result = $this->wpdb->update(
                $table,
                [
                    'phone_answer_time' => $answered ? time() : null,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $draw_id]
            );

            if ($result === false) {
                throw new \Exception('Failed to record phone answer');
            }

            return true;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'record_phone_answer',
                'draw_id' => $draw_id
            ]);
            return false;
        }
    }

    /**
     * Record winner question answer
     */
    public function record_question_answer($draw_id, $answer_time, $correct) {
        try {
            Logger::info('Recording question answer', [
                'draw_id' => $draw_id,
                'answer_time' => $answer_time,
                'correct' => $correct
            ]);

            $table = $this->wpdb->prefix . 'raffle_draws';
            
            $result = $this->wpdb->update(
                $table,
                [
                    'question_answer_time' => $answer_time,
                    'answered_correctly' => $correct,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $draw_id]
            );

            if ($result === false) {
                throw new \Exception('Failed to record question answer');
            }

            return true;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'record_question_answer',
                'draw_id' => $draw_id
            ]);
            return false;
        }
    }

    /**
     * Complete raffle event
     */
    public function complete_raffle($raffle_id, $winner_entry_id = null) {
        try {
            Logger::info('Completing raffle event', [
                'raffle_id' => $raffle_id,
                'winner_entry_id' => $winner_entry_id
            ]);

            $table = $this->wpdb->prefix . 'raffle_events';
            
            $result = $this->wpdb->update(
                $table,
                [
                    'status' => 'completed',
                    'winner_entry_id' => $winner_entry_id,
                    'completed_at' => current_time('mysql')
                ],
                ['id' => $raffle_id]
            );

            if ($result === false) {
                throw new \Exception('Failed to complete raffle');
            }

            return true;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'complete_raffle',
                'raffle_id' => $raffle_id
            ]);
            return false;
        }
    }

    /**
     * Generate unique entry number
     */
    private function generate_entry_number($raffle_id) {
        try {
            $table = $this->wpdb->prefix . 'raffle_entries';
            
            do {
                $number = mt_rand(10000, 99999);
                $exists = $this->wpdb->get_var(
                    $this->wpdb->prepare(
                        "SELECT COUNT(*) FROM $table 
                        WHERE raffle_id = %d AND entry_number = %d",
                        $raffle_id,
                        $number
                    )
                );
            } while ($exists);

            Logger::debug('Generated entry number', [
                'raffle_id' => $raffle_id,
                'entry_number' => $number
            ]);

            return $number;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'generate_entry_number',
                'raffle_id' => $raffle_id
            ]);
            throw $e;
        }
    }

    /**
     * Get unused winner question
     */
    private function get_unused_winner_question() {
        try {
            $table = $this->wpdb->prefix . 'winner_questions_pool';
            
            $question = $this->wpdb->get_row(
                "SELECT * FROM $table 
                WHERE used_count = 0 
                OR last_used < DATE_SUB(NOW(), INTERVAL 1 MONTH) 
                ORDER BY used_count ASC, RAND() 
                LIMIT 1"
            );

            if ($question) {
                $update_result = $this->wpdb->update(
                    $table,
                    [
                        'used_count' => $question->used_count + 1,
                        'last_used' => current_time('mysql')
                    ],
                    ['id' => $question->id]
                );

                if ($update_result === false) {
                    throw new \Exception('Failed to update question usage count');
                }

                Logger::debug('Retrieved winner question', [
                    'question_id' => $question->id,
                    'used_count' => $question->used_count + 1
                ]);
            } else {
                Logger::warning('No available winner questions found');
            }

            return $question;

        } catch (\Exception $e) {
            Logger::exception($e,