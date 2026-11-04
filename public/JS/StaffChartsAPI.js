/**
 * Staff Charts API Integration
 * Integrates Python chart generation with existing JavaScript functionality
 * Replaces only the chart rendering functions while preserving all other features
 */

// API Configuration
const CHART_API_BASE = 'http://localhost:5000/api';

// Chart API Functions - These replace the original chart rendering functions
class ChartAPI {
    constructor() {
        this.apiBase = CHART_API_BASE;
        this.chartsCache = new Map();
    }

    async generateAllCharts() {
        try {
            const response = await fetch(`${this.apiBase}/charts/generate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            const result = await response.json();
            if (result.success) {
                console.log('Charts generated successfully:', result.charts);
                return result.charts;
            } else {
                console.error('Chart generation failed:', result.error);
                return [];
            }
        } catch (error) {
            console.error('Error generating charts:', error);
            return [];
        }
    }

    async getStatusChart() {
        try {
            const response = await fetch(`${this.apiBase}/charts/status`);
            const result = await response.json();
            
            if (result.success) {
                return result.chart_url;
            } else {
                console.error('Status chart generation failed:', result.error);
                return null;
            }
        } catch (error) {
            console.error('Error generating status chart:', error);
            return null;
        }
    }

    async getYearDistributionChart() {
        try {
            const response = await fetch(`${this.apiBase}/charts/year-distribution`);
            const result = await response.json();
            
            if (result.success) {
                return result.chart_url;
            } else {
                console.error('Year distribution chart generation failed:', result.error);
                return null;
            }
        } catch (error) {
            console.error('Error generating year distribution chart:', error);
            return null;
        }
    }

    async getCollegeDistributionChart(batchFilter = 'all') {
        try {
            const url = `${this.apiBase}/charts/college-distribution${batchFilter !== 'all' ? `?batch_filter=${batchFilter}` : ''}`;
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.success) {
                return result.chart_url;
            } else {
                console.error('College distribution chart generation failed:', result.error);
                return null;
            }
        } catch (error) {
            console.error('Error generating college distribution chart:', error);
            return null;
        }
    }

    async getCourseDistributionChart(batchFilter = 'all', collegeFilter = 'all') {
        try {
            const params = new URLSearchParams();
            if (batchFilter !== 'all') params.append('batch_filter', batchFilter);
            if (collegeFilter !== 'all') params.append('college_filter', collegeFilter);
            
            const url = `${this.apiBase}/charts/course-distribution${params.toString() ? `?${params.toString()}` : ''}`;
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.success) {
                return result.chart_url;
            } else {
                console.error('Course distribution chart generation failed:', result.error);
                return null;
            }
        } catch (error) {
            console.error('Error generating course distribution chart:', error);
            return null;
        }
    }

    async getAlumniStats() {
        try {
            const response = await fetch(`${this.apiBase}/alumni/stats`);
            const result = await response.json();
            
            if (result.success) {
                return result.data;
            } else {
                console.error('Failed to get alumni stats:', result.error);
                return null;
            }
        } catch (error) {
            console.error('Error getting alumni stats:', error);
            return null;
        }
    }

    async getEngagementAnalytics() {
        try {
            const response = await fetch(`${this.apiBase}/analytics/engagement`);
            const result = await response.json();
            
            if (result.success) {
                return result.data;
            } else {
                console.error('Failed to get engagement analytics:', result.error);
                return null;
            }
        } catch (error) {
            console.error('Error getting engagement analytics:', error);
            return null;
        }
    }

    async getDemographicsAnalytics() {
        try {
            const response = await fetch(`${this.apiBase}/analytics/demographics`);
            const result = await response.json();
            
            if (result.success) {
                return result.data;
            } else {
                console.error('Failed to get demographics analytics:', result.error);
                return null;
            }
        } catch (error) {
            console.error('Error getting demographics analytics:', error);
            return null;
        }
    }
}

// Initialize Chart API
const chartAPI = new ChartAPI();

// Replacement Chart Rendering Functions
// These replace the original functions in StaffCommon.js

async function renderAlumniStatusChart() {
    const container = document.getElementById('alumniStatusChart');
    if (!container) return;

    // Show loading state
    container.innerHTML = '<div class="chart-loading">Loading chart...</div>';

    try {
        const chartUrl = await chartAPI.getStatusChart();
        
        if (chartUrl) {
            // Display the Python-generated chart
            container.innerHTML = `
                <div class="python-chart-container">
                    <img src="http://localhost:5000${chartUrl}" alt="Alumni Status Chart" class="chart-image" />
                    <div class="chart-timestamp">Generated at ${new Date().toLocaleString()}</div>
                </div>
            `;
        } else {
            container.innerHTML = '<div class="chart-error">Failed to load chart</div>';
        }
    } catch (error) {
        console.error('Error rendering alumni status chart:', error);
        container.innerHTML = '<div class="chart-error">Error loading chart</div>';
    }
}

async function renderYearDistribution() {
    const container = document.getElementById('yearDistribution');
    if (!container) return;

    // Show loading state
    container.innerHTML = '<div class="chart-loading">Loading chart...</div>';

    try {
        const chartUrl = await chartAPI.getYearDistributionChart();
        
        if (chartUrl) {
            // Display the Python-generated chart
            container.innerHTML = `
                <div class="python-chart-container">
                    <img src="http://localhost:5000${chartUrl}" alt="Year Distribution Chart" class="chart-image" />
                    <div class="chart-timestamp">Generated at ${new Date().toLocaleString()}</div>
                </div>
            `;
        } else {
            container.innerHTML = '<div class="chart-error">Failed to load chart</div>';
        }
    } catch (error) {
        console.error('Error rendering year distribution chart:', error);
        container.innerHTML = '<div class="chart-error">Error loading chart</div>';
    }
}

async function renderCollegeDistributionChart() {
    const container = document.getElementById('collegeDistributionChart');
    if (!container) return;

    // Show loading state
    container.innerHTML = '<div class="chart-loading">Loading chart...</div>';

    try {
        const batchFilter = selectedCollegeBatchFilter || 'all';
        const chartUrl = await chartAPI.getCollegeDistributionChart(batchFilter);
        
        if (chartUrl) {
            // Display the Python-generated chart
            container.innerHTML = `
                <div class="python-chart-container">
                    <img src="http://localhost:5000${chartUrl}" alt="College Distribution Chart" class="chart-image" />
                    <div class="chart-timestamp">Generated at ${new Date().toLocaleString()}</div>
                </div>
            `;
        } else {
            container.innerHTML = '<div class="chart-error">Failed to load chart</div>';
        }
    } catch (error) {
        console.error('Error rendering college distribution chart:', error);
        container.innerHTML = '<div class="chart-error">Error loading chart</div>';
    }
}

async function renderCourseDistributionChart() {
    const container = document.getElementById('courseDistributionChart');
    if (!container) return;

    // Show loading state
    container.innerHTML = '<div class="chart-loading">Loading chart...</div>';

    try {
        const batchFilter = selectedCourseBatchFilter || 'all';
        const collegeFilter = selectedCourseCollegeFilter || 'all';
        const chartUrl = await chartAPI.getCourseDistributionChart(batchFilter, collegeFilter);
        
        if (chartUrl) {
            // Display the Python-generated chart
            container.innerHTML = `
                <div class="python-chart-container">
                    <img src="http://localhost:5000${chartUrl}" alt="Course Distribution Chart" class="chart-image" />
                    <div class="chart-timestamp">Generated at ${new Date().toLocaleString()}</div>
                </div>
            `;
        } else {
            container.innerHTML = '<div class="chart-error">Failed to load chart</div>';
        }
    } catch (error) {
        console.error('Error rendering course distribution chart:', error);
        container.innerHTML = '<div class="chart-error">Error loading chart</div>';
    }
}

// Enhanced loadAlumni function that uses Python API for stats and charts
async function loadAlumni(sort = alumniSort) {
    const hasAlumniUi = Boolean(
        document.getElementById('totalAlumni') ||
        document.getElementById('alumniBatchGrid') ||
        document.getElementById('alumniTableBody') ||
        document.getElementById('alumniBatchDetailsState') ||
        document.getElementById('yearDistribution') ||
        document.getElementById('alumniStatusChart') ||
        document.getElementById('collegeDistributionChart') ||
        document.getElementById('courseDistributionChart')
    );
    if (!hasAlumniUi) return;

    alumniSort = sort;

    try {
        // Load alumni stats from Python API
        const stats = await chartAPI.getAlumniStats();
        
        if (stats) {
            // Update dashboard stats
            updateStatsFromAPI(stats);
        }

        // Load alumni batches (keep original logic)
        await loadAlumniBatches();
        
        // Render charts using Python API
        await renderAllCharts();
        
    } catch (error) {
        console.error('Failed to load alumni data:', error);
    }
}

// Update stats from API data
function updateStatsFromAPI(stats) {
    const totalAlumniEl = document.getElementById('totalAlumni');
    const activeAlumniEl = document.getElementById('activeAlumni');
    const totalEventsEl = document.getElementById('totalEvents');
    const engagementRateEl = document.getElementById('engagementRate');

    if (totalAlumniEl) totalAlumniEl.textContent = stats.total_alumni;
    if (activeAlumniEl) activeAlumniEl.textContent = stats.active_alumni;
    if (engagementRateEl) engagementRateEl.textContent = `${stats.engagement_rate}%`;
    
    // Keep existing events count logic
    if (totalEventsEl) totalEventsEl.textContent = eventsData.length;
}

// Render all charts
async function renderAllCharts() {
    const chartPromises = [
        renderAlumniStatusChart(),
        renderYearDistribution(),
        renderCollegeDistributionChart(),
        renderCourseDistributionChart()
    ];
    
    await Promise.allSettled(chartPromises);
}

// Event handler updates for filter changes
async function handleDashboardCollegeBatchChange() {
    const select = document.getElementById('collegeBatchFilter');
    selectedCollegeBatchFilter = select ? select.value : 'all';
    await renderCollegeDistributionChart();
}

async function handleDashboardCourseBatchChange() {
    const select = document.getElementById('courseBatchFilter');
    selectedCourseBatchFilter = select ? select.value : 'all';
    await renderCourseDistributionChart();
}

async function handleDashboardCourseCollegeChange() {
    const select = document.getElementById('courseCollegeFilter');
    selectedCourseCollegeFilter = select ? select.value : 'all';
    await renderCourseDistributionChart();
}

// API Health Check
async function checkAPIHealth() {
    try {
        const response = await fetch(`${CHART_API_BASE}/health`);
        const result = await response.json();
        
        if (result.status === 'healthy') {
            console.log('Chart API is healthy');
            return true;
        } else {
            console.warn('Chart API health check failed');
            return false;
        }
    } catch (error) {
        console.error('Chart API is not available:', error);
        return false;
    }
}

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', async () => {
    // Check API health
    const isAPIHealthy = await checkAPIHealth();
    
    if (isAPIHealthy) {
        console.log('Using Python API for chart generation');
        
        // Generate all charts on page load
        if (document.getElementById('alumniStatusChart') || 
            document.getElementById('yearDistribution') || 
            document.getElementById('collegeDistributionChart') || 
            document.getElementById('courseDistributionChart')) {
            await renderAllCharts();
        }
    } else {
        console.warn('Falling back to JavaScript chart generation');
        // Keep original JavaScript functions as fallback
        // (Original functions will still be available in StaffCommon.js)
    }
});

// Export for use in other scripts
window.ChartAPI = ChartAPI;
window.chartAPI = chartAPI;
window.renderAlumniStatusChart = renderAlumniStatusChart;
window.renderYearDistribution = renderYearDistribution;
window.renderCollegeDistributionChart = renderCollegeDistributionChart;
window.renderCourseDistributionChart = renderCourseDistributionChart;
window.checkAPIHealth = checkAPIHealth;
