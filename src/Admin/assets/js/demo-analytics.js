// File: wp-content/plugins/weebunz-core/admin/js/demo-analytics.js

(function($) {
    'use strict';

    class DemoAnalytics {
        constructor() {
            this.stats = {
                responseTime: [],
                concurrentUsers: 1,
                serverLoad: 0
            };
            this.initializeMonitoring();
        }

        initializeMonitoring() {
            setInterval(() => this.updateStats(), 2000);
            this.attachToQuizEvents();
        }

        updateStats() {
            // Simulate realistic server stats
            this.stats.serverLoad = Math.random() * 30; // 0-30% load
            this.stats.concurrentUsers = Math.floor(Math.random() * 100) + 1;
            
            // Calculate average response time
            const avgResponse = this.stats.responseTime.length > 0 
                ? this.stats.responseTime.reduce((a, b) => a + b) / this.stats.responseTime.length 
                : 0;

            // Update UI
            $('#demo-stats').html(`
                <div class="demo-metrics">
                    <div class="metric">
                        <strong>Current Users:</strong> ${this.stats.concurrentUsers}
                        <small>(Scales to ${weebunzTest.demoStats.maxConcurrent})</small>
                    </div>
                    <div class="metric">
                        <strong>Avg Response:</strong> ${avgResponse.toFixed(2)}ms
                    </div>
                    <div class="metric">
                        <strong>Server Load:</strong> ${this.stats.serverLoad.toFixed(1)}%
                    </div>
                    <div class="target-platform">
                        <strong>Target Platform:</strong> ${weebunzTest.demoStats.targetPlatform}
                        <small>(${weebunzTest.demoStats.scalingCapacity})</small>
                    </div>
                </div>
            `);
        }

        attachToQuizEvents() {
            $(document).on('quiz:request:start', () => {
                const startTime = performance.now();
                $(document).one('quiz:request:end', () => {
                    const endTime = performance.now();
                    this.stats.responseTime.push(endTime - startTime);
                    // Keep only last 50 measurements
                    if (this.stats.responseTime.length > 50) {
                        this.stats.responseTime.shift();
                    }
                });
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        if ($('#quiz-mount-point').length) {
            window.weebunzDemoAnalytics = new DemoAnalytics();
        }
    });

})(jQuery);