<?php
// File: src/Database/DataVerifier.php

namespace Weebunz\Database;

if (!defined("ABSPATH")) {
    exit;
}

// Using WordPress's error_log() for debugging this phase.
// If you have a custom Logger and prefer it:
// use Weebunz\Util\Logger; 

class DataVerifier {
    private $wpdb;
    private $expected_counts = [
        "quiz_categories"       => 6,
        "quiz_types"            => 3, // This count depends on DBManager::initialize_quiz_types()
        "questions_pool"        => 9,
        "question_answers"      => 36, // 9 questions * 4 answers each
        "winner_questions_pool" => 4
    ];

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        error_log("DataVerifier: __construct() called.");
    }

    public function insert_missing_data() {
        error_log("DataVerifier: insert_missing_data() started.");
        try {
            $this->wpdb->query("START TRANSACTION");
            error_log("DataVerifier: Transaction started.");

            // --- Insert quiz categories if missing ---
            $categories_table = $this->wpdb->prefix . "quiz_categories";
            $categories_count_query = "SELECT COUNT(*) FROM `{$categories_table}`";
            error_log("DataVerifier: Querying categories count with: " . $categories_count_query);
            $categories_count = $this->wpdb->get_var($categories_count_query);
            error_log("DataVerifier: Existing categories count: " . ($categories_count === null ? 'Error/Null' : $categories_count));
            
            if (intval($categories_count) == 0) {
                error_log("DataVerifier: No quiz categories found, calling insert_quiz_categories().");
                $this->insert_quiz_categories();
            } else {
                error_log("DataVerifier: Quiz categories already exist (Count: {$categories_count}). Skipping category insertion.");
            }

            // --- Insert questions pool if missing ---
            $questions_table = $this->wpdb->prefix . "questions_pool";
            $questions_count_query = "SELECT COUNT(*) FROM `{$questions_table}`";
            error_log("DataVerifier: Querying questions_pool count with: " . $questions_count_query);
            $questions_count = $this->wpdb->get_var($questions_count_query);
            error_log("DataVerifier: Existing questions_pool count: " . ($questions_count === null ? 'Error/Null' : $questions_count));
            
            if (intval($questions_count) == 0) {
                error_log("DataVerifier: No questions found in pool, calling insert_questions_pool().");
                $this->insert_questions_pool(); // This method also calls insert_question_answers()
            } else {
                error_log("DataVerifier: Questions pool already exists (Count: {$questions_count}). Skipping question insertion.");
            }

            // --- Insert winner questions if missing ---
            $winner_questions_table = $this->wpdb->prefix . "winner_questions_pool";
            $winner_questions_count_query = "SELECT COUNT(*) FROM `{$winner_questions_table}`";
            error_log("DataVerifier: Querying winner_questions_pool count with: " . $winner_questions_count_query);
            $winner_questions_count = $this->wpdb->get_var($winner_questions_count_query);
            error_log("DataVerifier: Existing winner_questions_pool count: " . ($winner_questions_count === null ? 'Error/Null' : $winner_questions_count));
            
            if (intval($winner_questions_count) == 0) {
                error_log("DataVerifier: No winner questions found, calling insert_winner_questions().");
                $this->insert_winner_questions();
            } else {
                error_log("DataVerifier: Winner questions already exist (Count: {$winner_questions_count}). Skipping winner question insertion.");
            }

            // --- Verify data counts ---
            error_log("DataVerifier: Calling verify_counts() after potential insertions.");
            $verification = $this->verify_counts();
            if (!$verification["success"]) {
                // This exception will be caught by the current method's catch block
                throw new \Exception("Data verification failed after insertions: " . esc_html($verification["message"]));
            }

            $this->wpdb->query("COMMIT");
            error_log("DataVerifier: Transaction committed. Data insertion/verification completed successfully.");
            return true;

        } catch (\Exception $e) {
            $this->wpdb->query("ROLLBACK");
            error_log("DataVerifier: Transaction rolled back due to exception.");
            error_log("DataVerifier: EXCEPTION in insert_missing_data(): " . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            return false;
        }
    }

    private function insert_quiz_categories() {
        error_log("DataVerifier: insert_quiz_categories() started.");
        try {
            $categories = [
                ["name" => "General Knowledge", "slug" => "general-knowledge", "description" => "Basic general knowledge questions"],
                ["name" => "Sports", "slug" => "sports", "description" => "Questions about various sports"],
                ["name" => "History", "slug" => "history", "description" => "Historical events and figures"],
                ["name" => "Science", "slug" => "science", "description" => "Scientific concepts and discoveries"],
                ["name" => "Entertainment", "slug" => "entertainment", "description" => "Movies, music, and pop culture"],
                ["name" => "Geography", "slug" => "geography", "description" => "World geography and landmarks"]
            ];
            $inserted_count = 0;
            $table_name = $this->wpdb->prefix . "quiz_categories";
            foreach ($categories as $category) {
                $result = $this->wpdb->insert(
                    $table_name, 
                    [
                        "name"        => sanitize_text_field($category["name"]),
                        "slug"        => sanitize_key($category["slug"]),
                        "description" => sanitize_textarea_field($category["description"])
                    ],
                    ["%s", "%s", "%s"]
                );
                if ($result === false) {
                    throw new \Exception("Failed to insert category: " . esc_html($category["name"]) . " | DB Error: " . $this->wpdb->last_error);
                }
                $inserted_count++;
                error_log("DataVerifier: Inserted category '{$category['name']}' into {$table_name}.");
            }
            error_log("DataVerifier: Quiz categories insertion completed. Inserted: {$inserted_count}");
        } catch (\Exception $e) {
            error_log("DataVerifier: EXCEPTION in insert_quiz_categories(): " . $e->getMessage());
            throw $e;
        }
    }

    private function insert_questions_pool() {
        error_log("DataVerifier: insert_questions_pool() started.");
        try {
            // Fetch existing category slugs and map them to their IDs
            $category_map = []; // Initialize the map
            $categories_table = $this->wpdb->prefix . "quiz_categories";
            $cats_results = $this->wpdb->get_results("SELECT id, slug FROM `{$categories_table}`"); // Fetch as standard array of objects
            if ($this->wpdb->last_error) {
                error_log("DataVerifier: DB Error fetching categories for map: " . $this->wpdb->last_error);
            } elseif ($cats_results) {
                foreach ($cats_results as $cat_obj) {
                    if (isset($cat_obj->slug) && isset($cat_obj->id)) {
                        $category_map[$cat_obj->slug] = $cat_obj->id; // Correctly map slug to ID
                    }
                }
            }
            error_log("DataVerifier: Category map for questions: " . print_r($category_map, true));

            $questions_data = [
                // Ensure 'category_slug' values below match slugs defined in insert_quiz_categories()
                ["question_text" => "What is the capital of Finland?", "question_type" => "multiple_choice", "category_slug" => "geography", "difficulty_level" => "easy", "time_limit" => 10],
                ["question_text" => "Who won the FIFA World Cup in 2022?", "question_type" => "multiple_choice", "category_slug" => "sports", "difficulty_level" => "easy", "time_limit" => 10],
                ["question_text" => "What is the chemical symbol for gold?", "question_type" => "multiple_choice", "category_slug" => "science", "difficulty_level" => "easy", "time_limit" => 10],
                ["question_text" => "Which planet is known as the Red Planet?", "question_type" => "multiple_choice", "category_slug" => "science", "difficulty_level" => "medium", "time_limit" => 15],
                ["question_text" => "In which year did Finland gain independence?", "question_type" => "multiple_choice", "category_slug" => "history", "difficulty_level" => "medium", "time_limit" => 15],
                ["question_text" => "Who painted the Mona Lisa?", "question_type" => "multiple_choice", "category_slug" => "general-knowledge", "difficulty_level" => "medium", "time_limit" => 15],
                ["question_text" => "What is the most abundant element in the Universe?", "question_type" => "multiple_choice", "category_slug" => "science", "difficulty_level" => "hard", "time_limit" => 20],
                ["question_text" => "Which Finnish company was founded by Fredrik Idestam in 1865?", "question_type" => "multiple_choice", "category_slug" => "history", "difficulty_level" => "hard", "time_limit" => 20],
                ["question_text" => "Who wrote the Kalevala?", "question_type" => "multiple_choice", "category_slug" => "entertainment", "difficulty_level" => "hard", "time_limit" => 20]
            ];

            $inserted_questions_count = 0;
            $questions_pool_table = $this->wpdb->prefix . "questions_pool";

            foreach ($questions_data as $question_item) {
                $category_id_to_insert = null;
                if (isset($question_item["category_slug"]) && isset($category_map[$question_item["category_slug"]])) {
                    $category_id_to_insert = $category_map[$question_item["category_slug"]];
                } else {
                    error_log("DataVerifier: Could not find category_id for slug: '" . ($question_item["category_slug"] ?? 'N/A') . "'. Question: '" . $question_item["question_text"] . "' will have NULL category_id.");
                    // Depending on your schema for questions_pool.category_id (NULLABLE or NOT NULL),
                    // you might need to handle this differently, e.g., skip insertion or use a default ID.
                    // Assuming category_id is NULLABLE as per your schema:
                    // `category_id` bigint(20) UNSIGNED,
                    // CONSTRAINT `fk_question_category` FOREIGN KEY (`category_id`) REFERENCES `{prefix}quiz_categories` (`id`) ON DELETE SET NULL
                }

                $result = $this->wpdb->insert(
                    $questions_pool_table, 
                    [
                        "question_text"    => sanitize_textarea_field($question_item["question_text"]),
                        "question_type"    => sanitize_key($question_item["question_type"]),
                        "category_id"      => $category_id_to_insert, // Use the mapped or null category_id
                        "difficulty_level" => sanitize_key($question_item["difficulty_level"]),
                        "time_limit"       => intval($question_item["time_limit"])
                    ],
                    ["%s", "%s", ($category_id_to_insert === null ? null : '%d'), "%s", "%d"] // Format for category_id is %d or null
                );

                if ($result === false) {
                    throw new \Exception("Failed to insert question: " . esc_html($question_item["question_text"]) . " | DB Error: " . $this->wpdb->last_error);
                }
                $question_id = $this->wpdb->insert_id;
                error_log("DataVerifier: Inserted question_id {$question_id} ('{$question_item['question_text']}') into {$questions_pool_table}.");
                $this->insert_question_answers($question_id);
                $inserted_questions_count++;
            }
            error_log("DataVerifier: Questions pool insertion completed. Inserted: {$inserted_questions_count}");
        } catch (\Exception $e) {
            error_log("DataVerifier: EXCEPTION in insert_questions_pool(): " . $e->getMessage());
            throw $e; // Re-throw
        }
    }

    private function insert_question_answers($question_id) {
        // This mapping remains fragile due to its reliance on insertion order.
        // Consider a more robust data structure for your default questions/answers if this becomes problematic.
        error_log("DataVerifier: insert_question_answers() for question_id: {$question_id}");
        try {
            $answers_map = [
                // Index (0-8) corresponds to the order in $questions_data in insert_questions_pool()
                0 => [["Helsinki", 1], ["Stockholm", 0], ["Oslo", 0], ["Copenhagen", 0]],
                1 => [["Argentina", 1], ["France", 0], ["Brazil", 0], ["Germany", 0]],
                2 => [["Au", 1], ["Ag", 0], ["Fe", 0], ["Cu", 0]],
                3 => [["Mars", 1], ["Venus", 0], ["Jupiter", 0], ["Saturn", 0]],
                4 => [["1917", 1], ["1919", 0], ["1905", 0], ["1920", 0]],
                5 => [["Leonardo da Vinci", 1], ["Michelangelo", 0], ["Raphael", 0], ["Donatello", 0]],
                6 => [["Hydrogen", 1], ["Helium", 0], ["Oxygen", 0], ["Carbon", 0]],
                7 => [["Nokia", 1], ["Fiskars", 0], ["Fazer", 0], ["Valio", 0]],
                8 => [["Elias Lönnrot", 1], ["Jean Sibelius", 0], ["Aleksis Kivi", 0], ["Mika Waltari", 0]]
            ];

            // Attempt to find the question's original data to determine its logical index for the map
            // This is still a bit of a hack. A better structure would associate answer sets directly.
            $question_text_from_db = $this->wpdb->get_var($this->wpdb->prepare("SELECT question_text FROM {$this->wpdb->prefix}questions_pool WHERE id = %d", $question_id));
            $question_order_index = -1;
            // Reference the $questions_data from the previous method to find the index.
            // This assumes $questions_data structure is consistent.
             $questions_data_for_answers = [ // Re-define or pass this data if needed
                ["question_text" => "What is the capital of Finland?"], ["question_text" => "Who won the FIFA World Cup in 2022?"],
                ["question_text" => "What is the chemical symbol for gold?"], ["question_text" => "Which planet is known as the Red Planet?"],
                ["question_text" => "In which year did Finland gain independence?"], ["question_text" => "Who painted the Mona Lisa?"],
                ["question_text" => "What is the most abundant element in the Universe?"], ["question_text" => "Which Finnish company was founded by Fredrik Idestam in 1865?"],
                ["question_text" => "Who wrote the Kalevala?"]
            ];
            foreach ($questions_data_for_answers as $index => $q_data) {
                if ($q_data['question_text'] === $question_text_from_db) {
                    $question_order_index = $index;
                    break;
                }
            }

            if ($question_order_index === -1 || !isset($answers_map[$question_order_index])) {
                error_log("DataVerifier: No answer set defined in answers_map for question_id {$question_id} (resolved index: {$question_order_index}). Skipping answers.");
                return;
            }
            
            error_log("DataVerifier: Found answer map for question_id {$question_id} at index {$question_order_index}.");
            $inserted_count = 0;
            $table_name = $this->wpdb->prefix . "question_answers";
            foreach ($answers_map[$question_order_index] as $answer_data) {
                $result = $this->wpdb->insert(
                    $table_name,
                    [
                        "question_id" => $question_id,
                        "answer_text" => sanitize_text_field($answer_data[0]),
                        "is_correct"  => intval($answer_data[1])
                    ],
                    ["%d", "%s", "%d"]
                );
                if ($result === false) {
                    throw new \Exception("Failed to insert answer: '" . esc_html($answer_data[0]) . "' for question_id " . $question_id . " | DB Error: " . $this->wpdb->last_error);
                }
                $inserted_count++;
            }
            error_log("DataVerifier: Inserted {$inserted_count} answers for question_id: {$question_id} into {$table_name}.");
        } catch (\Exception $e) {
            error_log("DataVerifier: EXCEPTION in insert_question_answers() for question_id {$question_id}: " . $e->getMessage());
            throw $e; // Re-throw
        }
    }

    private function insert_winner_questions() {
        error_log("DataVerifier: insert_winner_questions() started.");
        try {
            $questions = [
                ["question_text" => "What is the national animal of Finland?", "correct_answer" => "Brown Bear", "difficulty_level" => "easy"],
                ["question_text" => "What is the Finnish word for \"Hello\"?", "correct_answer" => "Hei", "difficulty_level" => "easy"],
                ["question_text" => "Name one of the two official languages of Finland", "correct_answer" => "Finnish or Swedish", "difficulty_level" => "medium"],
                ["question_text" => "What is the name of the Finnish national epic?", "correct_answer" => "Kalevala", "difficulty_level" => "hard"]
            ];
            $inserted_count = 0;
            $table_name = $this->wpdb->prefix . "winner_questions_pool";
            foreach ($questions as $question) {
                $result = $this->wpdb->insert(
                    $table_name, 
                    [
                        "question_text"    => sanitize_textarea_field($question["question_text"]),
                        "correct_answer"   => sanitize_text_field($question["correct_answer"]),
                        "difficulty_level" => sanitize_key($question["difficulty_level"])
                    ],
                    ["%s", "%s", "%s"]
                );
                if ($result === false) {
                    throw new \Exception("Failed to insert winner question: " . esc_html($question["question_text"]) . " | DB Error: " . $this->wpdb->last_error);
                }
                $inserted_count++;
                error_log("DataVerifier: Inserted winner question '{$question['question_text']}' into {$table_name}.");
            }
            error_log("DataVerifier: Winner questions insertion completed. Inserted: {$inserted_count}");
        } catch (\Exception $e) {
            error_log("DataVerifier: EXCEPTION in insert_winner_questions(): " . $e->getMessage());
            throw $e; // Re-throw
        }
    }

    private function verify_counts() {
        error_log("DataVerifier: verify_counts() called.");
        try {
            $issues = [];
            foreach ($this->expected_counts as $table_suffix => $expected) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_suffix)) {
                    error_log("DataVerifier: Invalid table suffix '{$table_suffix}' in verify_counts. Skipping.");
                    $issues[] = "Invalid table suffix: " . esc_html($table_suffix);
                    continue;
                }
                $table_name = $this->wpdb->prefix . $table_suffix;
                $query = "SELECT COUNT(*) FROM `{$table_name}`";
                
                $actual = $this->wpdb->get_var($query);

                if ($actual === null && !empty($this->wpdb->last_error)) {
                     // Check if the error is "table doesn't exist"
                    if (stripos($this->wpdb->last_error, "Table") !== false && stripos($this->wpdb->last_error, "exist") !== false) {
                        error_log("DataVerifier: Table `{$table_name}` does not exist. Count considered 0.");
                        $actual = 0; 
                    } else {
                        error_log("DataVerifier: SQL error counting table `{$table_name}`: " . $this->wpdb->last_error . " | Query: " . $query);
                        $issues[] = "Error counting `{$table_suffix}`: " . esc_html($this->wpdb->last_error);
                        continue; 
                    }
                }
                
                if (intval($actual) !== $expected) {
                    $message = esc_html($table_suffix) . ": expected " . intval($expected) . ", got " . intval($actual);
                    error_log("DataVerifier: Count mismatch for `{$table_name}`: expected {$expected}, got {$actual}.");
                    $issues[] = $message;
                } else {
                    error_log("DataVerifier: Count verified for `{$table_name}`: {$actual}.");
                }
            }
            $success = empty($issues);
            $final_message = $success ? "All counts verified" : implode("; ", $issues);
            error_log("DataVerifier: Data verification completed. Success: " . ($success ? 'Yes' : 'No') . ". Message: " . $final_message);
            return [
                "success" => $success,
                "message" => $final_message
            ];
        } catch (\Exception $e) {
            error_log("DataVerifier: EXCEPTION in verify_counts(): " . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            return ["success" => false, "message" => "Exception during count verification: " . $e->getMessage()];
        }
    }
}