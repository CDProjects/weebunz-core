<?php
namespace Weebunz\Database;

if (!defined("ABSPATH")) {
    exit;
}

// Assuming Logger class is in Weebunz\Util namespace and autoloaded
use Weebunz\Util\Logger;

class DataVerifier { // Changed class name from Data_Verifier to DataVerifier
    private $wpdb;
    private $expected_counts = [
        "quiz_categories" => 6,
        "quiz_types" => 3,
        "questions_pool" => 9,
        "question_answers" => 36,
        "winner_questions_pool" => 4
    ];

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        // Logger::debug("DataVerifier initialized"); // Uncomment if Logger is fully integrated and working
    }

    public function insert_missing_data() {
        try {
            // Logger::info("Starting data insertion process");
            $this->wpdb->query("START TRANSACTION");

            // Insert quiz categories if missing
            $categories_count = $this->wpdb->get_var(
                $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->wpdb->prefix}quiz_categories") // Added prepare
            );
            
            if ($categories_count == 0) {
                // Logger::debug("No quiz categories found, inserting default categories");
                $this->insert_quiz_categories();
            } else {
                // Logger::debug("Quiz categories already exist", ["count" => $categories_count]);
            }

            // Insert questions pool if missing
            $questions_count = $this->wpdb->get_var(
                $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->wpdb->prefix}questions_pool") // Added prepare
            );
            
            if ($questions_count == 0) {
                // Logger::debug("No questions found, inserting default questions");
                $this->insert_questions_pool();
            } else {
                // Logger::debug("Questions pool already exists", ["count" => $questions_count]);
            }

            // Insert winner questions if missing
            $winner_questions_count = $this->wpdb->get_var(
                $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->wpdb->prefix}winner_questions_pool") // Added prepare
            );
            
            if ($winner_questions_count == 0) {
                // Logger::debug("No winner questions found, inserting default questions");
                $this->insert_winner_questions();
            } else {
                // Logger::debug("Winner questions already exist", ["count" => $winner_questions_count]);
            }

            // Verify data counts
            $verification = $this->verify_counts();
            if (!$verification["success"]) {
                throw new \Exception("Data verification failed: " . esc_html($verification["message"]));
            }

            $this->wpdb->query("COMMIT");
            // Logger::info("Data insertion completed successfully");
            return true;

        } catch (\Exception $e) {
            $this->wpdb->query("ROLLBACK");
            // Logger::exception($e, ["context" => "insert_missing_data"]);
            error_log("WeeBunz Quiz Engine DataVerifier Error (insert_missing_data): " . $e->getMessage()); // Fallback logging
            return false;
        }
    }

    private function insert_quiz_categories() {
        try {
            // Logger::debug("Inserting quiz categories");
            
            $categories = [
                ["name" => "General Knowledge", "slug" => "general-knowledge", "description" => "Basic general knowledge questions"],
                ["name" => "Sports", "slug" => "sports", "description" => "Questions about various sports"],
                ["name" => "History", "slug" => "history", "description" => "Historical events and figures"],
                ["name" => "Science", "slug" => "science", "description" => "Scientific concepts and discoveries"],
                ["name" => "Entertainment", "slug" => "entertainment", "description" => "Movies, music, and pop culture"],
                ["name" => "Geography", "slug" => "geography", "description" => "World geography and landmarks"]
            ];

            foreach ($categories as $category) {
                $result = $this->wpdb->insert($this->wpdb->prefix . "quiz_categories", 
                    array(
                        "name" => sanitize_text_field($category["name"]),
                        "slug" => sanitize_key($category["slug"]),
                        "description" => sanitize_textarea_field($category["description"])
                    ),
                    array("%s", "%s", "%s")
                );
                if ($result === false) {
                    throw new \Exception("Failed to insert category: " . esc_html($category["name"]));
                }
                // Logger::debug("Category inserted", ["name" => $category["name"]]);
            }
            // Logger::info("Quiz categories insertion completed", ["count" => count($categories)]);
        } catch (\Exception $e) {
            // Logger::exception($e, ["context" => "insert_quiz_categories"]);
            error_log("WeeBunz Quiz Engine DataVerifier Error (insert_quiz_categories): " . $e->getMessage());
            throw $e;
        }
    }

    private function insert_questions_pool() {
        try {
            // Logger::debug("Inserting questions pool");
            $questions = [
                ["question_text" => "What is the capital of Finland?", "question_type" => "multiple_choice", "category" => "Geography", "difficulty_level" => "easy", "time_limit" => 10],
                ["question_text" => "Who won the FIFA World Cup in 2022?", "question_type" => "multiple_choice", "category" => "Sports", "difficulty_level" => "easy", "time_limit" => 10],
                ["question_text" => "What is the chemical symbol for gold?", "question_type" => "multiple_choice", "category" => "Science", "difficulty_level" => "easy", "time_limit" => 10],
                ["question_text" => "Which planet is known as the Red Planet?", "question_type" => "multiple_choice", "category" => "Science", "difficulty_level" => "medium", "time_limit" => 15],
                ["question_text" => "In which year did Finland gain independence?", "question_type" => "multiple_choice", "category" => "History", "difficulty_level" => "medium", "time_limit" => 15],
                ["question_text" => "Who painted the Mona Lisa?", "question_type" => "multiple_choice", "category" => "General Knowledge", "difficulty_level" => "medium", "time_limit" => 15],
                ["question_text" => "What is the most abundant element in the Universe?", "question_type" => "multiple_choice", "category" => "Science", "difficulty_level" => "hard", "time_limit" => 20],
                ["question_text" => "Which Finnish company was founded by Fredrik Idestam in 1865?", "question_type" => "multiple_choice", "category" => "History", "difficulty_level" => "hard", "time_limit" => 20],
                ["question_text" => "Who wrote the Kalevala?", "question_type" => "multiple_choice", "category" => "Entertainment", "difficulty_level" => "hard", "time_limit" => 20]
            ];

            foreach ($questions as $question) {
                $result = $this->wpdb->insert($this->wpdb->prefix . "questions_pool", 
                    array(
                        "question_text" => sanitize_textarea_field($question["question_text"]),
                        "question_type" => sanitize_key($question["question_type"]),
                        "category" => sanitize_text_field($question["category"]),
                        "difficulty_level" => sanitize_key($question["difficulty_level"]),
                        "time_limit" => intval($question["time_limit"])
                    ),
                    array("%s", "%s", "%s", "%s", "%d")
                );
                if ($result === false) {
                    throw new \Exception("Failed to insert question: " . esc_html($question["question_text"]));
                }
                $question_id = $this->wpdb->insert_id;
                $this->insert_question_answers($question_id);
                // Logger::debug("Question inserted", ["id" => $question_id, "text" => $question["question_text"]]);
            }
            // Logger::info("Questions pool insertion completed", ["count" => count($questions)]);
        } catch (\Exception $e) {
            // Logger::exception($e, ["context" => "insert_questions_pool"]);
             error_log("WeeBunz Quiz Engine DataVerifier Error (insert_questions_pool): " . $e->getMessage());
            throw $e;
        }
    }

    private function insert_question_answers($question_id) {
        try {
            $answers_map = [
                1 => [["Helsinki", 1], ["Stockholm", 0], ["Oslo", 0], ["Copenhagen", 0]],
                2 => [["Argentina", 1], ["France", 0], ["Brazil", 0], ["Germany", 0]],
                3 => [["Au", 1], ["Ag", 0], ["Fe", 0], ["Cu", 0]],
                4 => [["Mars", 1], ["Venus", 0], ["Jupiter", 0], ["Saturn", 0]],
                5 => [["1917", 1], ["1919", 0], ["1905", 0], ["1920", 0]],
                6 => [["Leonardo da Vinci", 1], ["Michelangelo", 0], ["Raphael", 0], ["Donatello", 0]],
                7 => [["Hydrogen", 1], ["Helium", 0], ["Oxygen", 0], ["Carbon", 0]],
                8 => [["Nokia", 1], ["Fiskars", 0], ["Fazer", 0], ["Valio", 0]],
                9 => [["Elias Lönnrot", 1], ["Jean Sibelius", 0], ["Aleksis Kivi", 0], ["Mika Waltari", 0]]
            ];

            // Determine which set of answers to use based on the question_id or a counter
            // This logic assumes question_ids are sequential starting from 1 for this map.
            // A more robust way would be to associate answers directly with question text or a unique key.
            $question_order_index = $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$this->wpdb->prefix}questions_pool WHERE id <= %d", $question_id));

            if (!isset($answers_map[$question_order_index])) {
                // Logger::warning("No answer set defined for question order index", ["index" => $question_order_index, "question_id" => $question_id]);
                return;
            }

            // Logger::debug("Inserting answers for question", ["question_id" => $question_id, "question_order_index" => $question_order_index]);

            foreach ($answers_map[$question_order_index] as $answer_data) {
                $result = $this->wpdb->insert(
                    $this->wpdb->prefix . "question_answers",
                    array(
                        "question_id" => $question_id,
                        "answer_text" => sanitize_text_field($answer_data[0]),
                        "is_correct" => intval($answer_data[1])
                    ),
                    array("%d", "%s", "%d")
                );
                if ($result === false) {
                    throw new \Exception("Failed to insert answer: " . esc_html($answer_data[0]));
                }
                // Logger::debug("Answer inserted", ["question_id" => $question_id, "text" => $answer_data[0], "correct" => $answer_data[1]]);
            }
        } catch (\Exception $e) {
            // Logger::exception($e, ["context" => "insert_question_answers", "question_id" => $question_id]);
            error_log("WeeBunz Quiz Engine DataVerifier Error (insert_question_answers): " . $e->getMessage());
            throw $e;
        }
    }

    private function insert_winner_questions() {
        try {
            // Logger::debug("Inserting winner questions");
            $questions = [
                ["question_text" => "What is the national animal of Finland?", "correct_answer" => "Brown Bear", "difficulty_level" => "easy"],
                ["question_text" => "What is the Finnish word for \"Hello\"?", "correct_answer" => "Hei", "difficulty_level" => "easy"],
                ["question_text" => "Name one of the two official languages of Finland", "correct_answer" => "Finnish or Swedish", "difficulty_level" => "medium"],
                ["question_text" => "What is the name of the Finnish national epic?", "correct_answer" => "Kalevala", "difficulty_level" => "hard"]
            ];

            foreach ($questions as $question) {
                $result = $this->wpdb->insert($this->wpdb->prefix . "winner_questions_pool", 
                    array(
                        "question_text" => sanitize_textarea_field($question["question_text"]),
                        "correct_answer" => sanitize_text_field($question["correct_answer"]),
                        "difficulty_level" => sanitize_key($question["difficulty_level"])
                    ),
                    array("%s", "%s", "%s")
                );
                if ($result === false) {
                    throw new \Exception("Failed to insert winner question: " . esc_html($question["question_text"]));
                }
                // Logger::debug("Winner question inserted", ["text" => $question["question_text"]]);
            }
            // Logger::info("Winner questions insertion completed", ["count" => count($questions)]);
        } catch (\Exception $e) {
            // Logger::exception($e, ["context" => "insert_winner_questions"]);
            error_log("WeeBunz Quiz Engine DataVerifier Error (insert_winner_questions): " . $e->getMessage());
            throw $e;
        }
    }

    private function verify_counts() {
        try {
            // Logger::debug("Verifying data counts");
            $issues = [];
            foreach ($this->expected_counts as $table_suffix => $expected) {
                $actual = $this->wpdb->get_var(
                    $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->wpdb->prefix}%s", $table_suffix) // Use prepare with %s for table name part
                );
                if (intval($actual) !== $expected) {
                    $message = esc_html($table_suffix) . ": expected " . intval($expected) . ", got " . intval($actual);
                    // Logger::warning("Count mismatch", ["table" => $table_suffix, "expected" => $expected, "actual" => $actual]);
                    $issues[] = $message;
                } else {
                    // Logger::debug("Count verified", ["table" => $table_suffix, "count" => $actual]);
                }
            }
            $success = empty($issues);
            // Logger::info("Data verification completed", ["success" => $success]);
            return [
                "success" => $success,
                "message" => $success ? "All counts verified" : implode("; ", $issues)
            ];
        } catch (\Exception $e) {
            // Logger::exception($e, ["context" => "verify_counts"]);
            error_log("WeeBunz Quiz Engine DataVerifier Error (verify_counts): " . $e->getMessage());
            throw $e;
        }
    }
}

