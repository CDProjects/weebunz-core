// File: wp-content/plugins/weebunz-core/src/Admin/assets/js/api-test.js

(function($) {
    "use strict";
    
    $(document).ready(function() {
        console.log("WeeBunz API Test Tool Initialized");
        
        // Function to test the REST API
        function testRestAPI() {
            // Show testing message
            $("#api-test-results").html('<div class="notice notice-info"><p>Testing REST API connectivity...</p></div>');
            
            // Check if weebunzTest object exists
            if (!window.weebunzTest) {
                showError("The weebunzTest object is not defined. This likely means the script enqueuing is not working correctly.");
                return;
            }
            
            // Check if apiEndpoint is defined
            if (!window.weebunzTest.apiEndpoint) {
                showError("The weebunzTest.apiEndpoint is not defined. Check the wp_localize_script call in your WeebunzAdmin.php file.");
                return;
            }
            
            // Test a basic WordPress REST API endpoint first
            $.ajax({
                url: window.location.protocol + '//' + window.location.host + '/wp-json',
                method: 'GET',
                success: function(data) {
                    console.log("WordPress REST API is working", data);
                    testWeebunzAPI();
                },
                error: function(xhr, status, error) {
                    showError("WordPress REST API is not accessible. This usually means your permalink settings need to be updated to something other than 'Plain'. Current error: " + error);
                }
            });
        }
        
        function testWeebunzAPI() {
            const url = window.weebunzTest.apiEndpoint;
            
            // Now test the specific WeeBunz endpoint
            $.ajax({
                url: url,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', window.weebunzTest.nonce);
                },
                success: function(data) {
                    console.log("WeeBunz API is accessible", data);
                    showSuccess("API Connection Successful! The WeeBunz REST API is properly configured and accessible.");
                },
                error: function(xhr, status, error) {
                    if (xhr.status === 404) {
                        showError("WeeBunz API endpoints not found (404). The REST API route 'weebunz/v1' is not registered. Check your QuizController.php file and make sure it's being loaded.");
                    } else {
                        showError("WeeBunz API error: " + error + " (Status code: " + xhr.status + ")");
                    }
                }
            });
        }
        
        function showError(message) {
            const html = `
                <div class="notice notice-error">
                    <p><strong>REST API Error</strong></p>
                    <p>${message}</p>
                    <h3>Troubleshooting Steps:</h3>
                    <ol>
                        <li>Make sure you have pretty permalinks enabled (Settings > Permalinks > choose any option except "Plain")</li>
                        <li>Check that your .htaccess file is properly configured</li>
                        <li>Verify that the WeeBunz REST API is being registered in your plugin</li>
                        <li>Check for any JavaScript errors in the browser console</li>
                        <li>Make sure the 'weebunzTest' object is being correctly localized in your PHP code</li>
                    </ol>
                </div>
            `;
            $("#api-test-results").html(html);
        }
        
        function showSuccess(message) {
            const html = `
                <div class="notice notice-success">
                    <p><strong>SUCCESS!</strong></p>
                    <p>${message}</p>
                </div>
            `;
            $("#api-test-results").html(html);
        }
        
        // Add test button to the page
        if ($("#test-api-connection").length === 0) {
            const html = `
                <div class="api-test-tool">
                    <h2>REST API Connection Test</h2>
                    <p>Click the button below to test your REST API connection:</p>
                    <button id="test-api-connection" class="button button-primary">Test REST API Connection</button>
                    <div id="api-test-results" class="api-results mt-3"></div>
                </div>
            `;
            
            // Add it before the quiz interface
            $("#quiz-interface").before(html);
            
            // Add event listener
            $("#test-api-connection").on("click", function(e) {
                e.preventDefault();
                testRestAPI();
            });
        }
    });
})(jQuery);